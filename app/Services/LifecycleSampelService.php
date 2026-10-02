<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sample Lifecycle — perjalanan satu sampel dari registrasi sampai finalisasi.
 *
 * Dipakai bersama layar Verifikasi dan Finalisasi. Alurnya:
 *
 *   Registrasi
 *     └ per klasifikasi (Look View, Analisa Lab, Palatabilitas — berjalan
 *       sendiri-sendiri):
 *         Putaran 1: Uji sampel → Validasi
 *                    → bila ditolak: Resampling
 *         Putaran 2: Uji sampel resampling → Validasi   (dst.)
 *         Verifikasi (+ revisinya)
 *   Finalisasi
 *
 * ATURAN MEMBACA DATA — hasil pemeriksaan data production 26-09-2026:
 *
 *  1. Jangkar sampel adalah No_Po_Sampel, yang tidak pernah berubah.
 *     No_Fak_Sub_Po (multi QR: FS0926-0018-1, -2, ...) hanya keterangan tiap
 *     putaran: saat resampling multi QR, analisa pindah ke QR lain; pada
 *     sampel tunggal nomornya tetap sama. No_Faktur TIDAK dipakai sebagai
 *     kunci — ada nomor faktur yang terpakai di dua sampel berbeda.
 *
 *  2. Putaran = Tahapan_Ke, dikoreksi dengan log resampling. Sebagian baris
 *     putaran ulang tersimpan dengan Tahapan_Ke yang salah (kode resampling
 *     lama mengambil tahapan tanpa menyaring jenis analisa). Baris yang
 *     diinput SETELAH resampling ke putaran N, pada nomor sampel tujuannya,
 *     dianggap putaran N.
 *
 *  3. "Ditolak" diturunkan dari log resampling, BUKAN dari
 *     Status_Keputusan_Sampel. Validasi putaran berikutnya pada sampel tunggal
 *     pernah ikut mengubah baris putaran lama yang ditolak menjadi 'terima'.
 *
 *  4. Penguji = Uji_Sampel.Id_User (pembuat draf hasil); waktu = saat
 *     dikirim (jam server). Pengirim dibaca dari Activity_Uji_Sampel; bila
 *     berbeda orang, keduanya disebut.
 *
 *  5. Validator dibaca berurutan dari: approval realtime (VALIDASI), log aksi
 *     validasi, Detail_Final pra-migrasi / realtime, dan — khusus
 *     palatabilitas — pelaku finalisasi sesi. Baris hasil backfill tidak
 *     dipakai. Yang tidak tercatat di mana pun DITANDAI, tidak ditebak.
 *
 *  6. Satu analisa bisa punya banyak baris dalam satu putaran (ulangan
 *     pengukuran, atau parameter palatabilitas per pembanding). Semuanya
 *     diringkas menjadi satu baris analisa.
 *
 * Read-only terhadap seluruh tabel. Sumber yang tabel/kolomnya belum ada
 * (mis. production sebelum migrasi) dilewati, bukan menggagalkan.
 */
class LifecycleSampelService
{
    /** Tingkat (warna) kejadian — sama dengan kelas di layar. */
    public const OK   = 'ok';
    public const WARN = 'warn';
    public const BAD  = 'bad';
    public const INFO = 'info';
    public const RUN  = 'run';
    public const WAIT = 'wait';
    public const MIG  = 'mig';

    private const AKTIVITAS_KIRIM = ['save_submit', 'save_submit_resampling'];
    private const AKTIVITAS_DRAF  = ['save_draft', 'save_update', 'save_delete'];

    /** @var array<string, bool> */
    private array $cacheSkema = [];

    /** @var array<string, string> UserId => Nama */
    private array $nama = [];

    /** @var array<int|string, string> Id_Pembanding => Nama_Pembanding */
    private array $pembanding = [];

    public function __construct(private RincianHasilAnalisaService $rincian)
    {
    }

    /**
     * Susun lifecycle satu sampel.
     *
     * @param  array  $opsi {
     *     @type array|null $klasifikasi  batasi ke kode klasifikasi ini (kewenangan
     *                                    verifikator); null = seluruhnya
     * }
     * @return array|null null bila sampel tidak ditemukan
     */
    public function susun(string $noSampel, array $opsi = []): ?array
    {
        $po = $this->sampel($noSampel);
        if (!$po) {
            return null;
        }

        $batas = $opsi['klasifikasi'] ?? null;

        $uji        = $this->barisUji($noSampel);
        $logRs      = $this->logResampling($noSampel);
        $aktivitas  = $this->aktivitas($noSampel);
        $validasi   = $this->sumberValidator($noSampel);
        $foto       = $this->foto($noSampel);
        $sesiPlt    = $this->sesiPalatabilitas($noSampel);
        $verifikasi = $this->verifikasi($noSampel);
        $final      = $this->finalisasi($noSampel);
        $master     = $this->klasifikasiMaster();

        $this->muatNama(array_merge(
            [$po->Id_User],
            $uji->pluck('Id_User')->all(),
            $logRs->pluck('Id_User')->all(),
            $aktivitas->pluck('Id_User')->all(),
            collect($validasi)->flatten(1)->pluck('Id_User')->all(),
            $sesiPlt->pluck('Id_User_Buat')->all(),
            $sesiPlt->pluck('Id_User_Final')->all(),
            collect($verifikasi['riwayat'])->pluck('Id_User')->all(),
            collect($final['log'])->pluck('Id_User')->all(),
            [$final['header']->Id_User ?? null],
        ));

        $this->pembanding = $this->rincian->petaPembanding(
            $uji->pluck('Id_Pembanding')->filter()->unique()->values()->all());
        $this->rincian->siapkanMaster($uji->pluck('Id_Jenis_Analisa')->all(), $po);

        $catatanData = [];
        $this->tetapkanPutaran($uji, $logRs, $catatanData);

        // ---- per klasifikasi ------------------------------------------------
        $klasifikasi = [];
        foreach ($uji->groupBy('Kode_Aktivitas_Lab') as $kode => $baris) {
            if ($batas !== null && !in_array($kode, $batas, true)) {
                continue;
            }

            $klasifikasi[] = $this->susunKlasifikasi(
                (string) $kode, $baris, $po, $master,
                $logRs->filter(fn ($l) => in_array((int) $l->Id_Jenis_Analisa,
                    $baris->pluck('Id_Jenis_Analisa')->map(fn ($x) => (int) $x)->all(), true)),
                $aktivitas, $validasi, $foto, $sesiPlt,
                $verifikasi, $final['header'] !== null, $catatanData
            );
        }

        usort($klasifikasi, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

        $registrasi = $this->kejadianRegistrasi($po, $klasifikasi);
        $finalisasi = $this->kejadianFinalisasi($final, $klasifikasi);

        $mulai = $registrasi['waktu'];
        $akhir = collect($finalisasi)->where('jenis', 'fin')->where('audit', false)->pluck('waktu')->filter()->max();

        return [
            'sampel' => [
                'No_Sampel'           => $po->No_Sampel,
                'No_Po'               => $po->No_Po,
                'No_Split_Po'         => $po->No_Split_Po,
                'No_Batch'            => $po->No_Batch,
                'Kode_Barang'         => $po->Kode_Barang,
                'Nama_Barang'         => trim((string) $po->Nama_Barang) ?: '-',
                'Nama_Mesin'          => trim((string) $po->Nama_Mesin) ?: null,
                'Flag_Trial_Produksi' => $po->Flag_Trial_Produksi,
                'Multi_QrCode'        => $uji->contains(fn ($r) => !empty($r->No_Fak_Sub_Po)),
            ],
            'posisi'      => $this->posisi($klasifikasi, $final),
            'waktu'       => ['mulai' => $mulai, 'akhir' => $akhir, 'selesai' => $akhir !== null],
            'fase'        => $this->fase($klasifikasi, $final),
            'registrasi'  => [$registrasi],
            'klasifikasi' => $klasifikasi,
            'finalisasi'  => $finalisasi,
            'catatan_data' => array_values(array_unique($catatanData)),
        ];
    }

    // ======================================================================
    // Pengambilan data
    // ======================================================================

    private function sampel(string $noSampel): ?object
    {
        return DB::table('N_EMI_LAB_PO_Sampel as p')
            ->where('p.No_Sampel', $noSampel)
            ->select('p.No_Sampel', 'p.No_Po', 'p.No_Split_Po', 'p.No_Batch',
                'p.Kode_Barang', 'p.Tanggal', 'p.Jam', 'p.Id_User', 'p.Id_Mesin',
                'p.Flag_Trial_Produksi', 'p.Flag_Selesai',
                DB::raw('(SELECT TOP 1 b.Nama FROM N_EMI_View_Barang b
                          WHERE b.Kode_Barang = p.Kode_Barang) AS Nama_Barang'),
                DB::raw('(SELECT TOP 1 m.Nama_Mesin FROM EMI_Master_Mesin m
                          WHERE m.Id_Master_Mesin = p.Id_Mesin) AS Nama_Mesin'))
            ->first();
    }

    /** Semua baris uji sampel ini, termasuk putaran yang ditolak. */
    private function barisUji(string $noSampel): Collection
    {
        return DB::table('N_EMI_LAB_Uji_Sampel as u')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'u.Id_Jenis_Analisa')
            ->where('u.No_Po_Sampel', $noSampel)
            // Status 'Y' = baris dibatalkan (bypass), bukan bagian perjalanan.
            ->where(fn ($q) => $q->whereNull('u.Status')->orWhere('u.Status', '<>', 'Y'))
            ->whereNotNull('ja.Kode_Aktivitas_Lab')
            ->select('u.No_Faktur', 'u.No_Po_Sampel', 'u.No_Fak_Sub_Po', 'u.Id_Jenis_Analisa',
                'u.Hasil', 'u.Nilai_Hasil_String', 'u.Range_Awal', 'u.Range_Akhir',
                'u.Flag_Layak', 'u.Tahapan_Ke', 'u.Flag_Resampling', 'u.Status_Keputusan_Sampel',
                'u.Flag_Selesai', 'u.Flag_Final', 'u.Tanggal', 'u.Jam', 'u.Id_User',
                'u.Id_Session', 'u.Id_Pembanding',
                'ja.Jenis_Analisa', 'ja.Kode_Aktivitas_Lab', 'ja.Flag_Perhitungan')
            ->get()
            ->map(function ($r) {
                $r->Waktu = $this->waktu($r->Tanggal, $r->Jam);
                $r->Id_Jenis_Analisa = (int) $r->Id_Jenis_Analisa;
                return $r;
            })
            ->sortBy(fn ($r) => $r->Waktu . '|' . $r->No_Faktur)
            ->values();
    }

    private function logResampling(string $noSampel): Collection
    {
        $kolom = ['Id_Resampling', 'No_Po_Sampel', 'Id_Jenis_Analisa', 'Tahapan_Ke',
            'No_Sampel_Resampling_Origin', 'No_Sampel_Resampling', 'Keterangan',
            'Tanggal', 'Jam', 'Id_User', 'Flag_Selesai_Resampling', 'Id_Pembanding'];
        if ($this->adaKolom('N_EMI_LAB_Uji_Sampel_Resampling_Log', 'Alasan')) {
            $kolom[] = 'Alasan';
        }

        return DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
            ->where('No_Po_Sampel', $noSampel)
            ->select($kolom)
            ->get()
            ->map(function ($r) {
                $r->Waktu = $this->waktu($r->Tanggal, $r->Jam);
                $r->Id_Jenis_Analisa = (int) $r->Id_Jenis_Analisa;
                $r->Tahapan_Ke = (int) ($r->Tahapan_Ke ?: 2);
                $r->Alasan = $r->Alasan ?? null;
                return $r;
            })
            ->sortBy(fn ($r) => sprintf('%04d', $r->Tahapan_Ke) . $r->Waktu . sprintf('%09d', $r->Id_Resampling))
            ->values();
    }

    private function aktivitas(string $noSampel): Collection
    {
        $rows = DB::table('N_EMI_LAB_Activity_Uji_Sampel')
            ->where('No_Po_Sampel', $noSampel)
            ->select('Id_Log_Activity', 'No_Fak_Sub_Po', 'Jenis_Aktivitas', 'Id_Jenis_Analisa',
                'Id_User', 'Tanggal', 'Jam')
            ->get()
            ->map(function ($r) {
                $r->Waktu = $this->waktu($r->Tanggal, $r->Jam);
                $r->Id_Jenis_Analisa = (int) $r->Id_Jenis_Analisa;
                return $r;
            });

        // Perubahan nilai & alasan pada draf (hanya dibaca untuk draf).
        $idDraf = $rows->whereIn('Jenis_Aktivitas', self::AKTIVITAS_DRAF)->pluck('Id_Log_Activity')->all();
        $ubah = collect();
        $alasan = collect();
        foreach (array_chunk($idDraf, 500) as $bagian) {
            $ubah = $ubah->concat(DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')
                ->whereIn('Id_Log_Activity_Sampel', $bagian)
                ->whereColumn('Value_Lama', '<>', 'Value_Baru')
                ->select('Id_Log_Activity_Sampel', 'Id_Jenis_Analisa', 'Value_Lama', 'Value_Baru')
                ->get());
            $alasan = $alasan->concat(DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')
                ->whereIn('Id_Log_Activity_Sampel', $bagian)
                ->whereNotNull('Alasan_Mengubah_Data')
                ->where('Alasan_Mengubah_Data', '<>', '')
                ->select('Id_Log_Activity_Sampel', 'Alasan_Mengubah_Data')
                ->get());
        }
        $ubah = $ubah->groupBy('Id_Log_Activity_Sampel');
        $alasan = $alasan->groupBy('Id_Log_Activity_Sampel');

        return $rows->map(function ($r) use ($ubah, $alasan) {
            $r->Perubahan = ($ubah[$r->Id_Log_Activity] ?? collect())->values();
            $r->Alasan = ($alasan[$r->Id_Log_Activity] ?? collect())
                ->pluck('Alasan_Mengubah_Data')->map(fn ($a) => trim((string) $a))
                ->filter()->unique()->values()->all();
            return $r;
        })->sortBy('Waktu')->values();
    }

    /**
     * Seluruh catatan validator sampel ini, per sumber.
     *
     * @return array{approval: Collection, log: Collection, detail: Collection}
     */
    private function sumberValidator(string $noSampel): array
    {
        $approval = collect();
        if ($this->adaTabel('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')) {
            $approval = DB::table('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
                ->where('No_Sampel', $noSampel)
                ->where('Jenis_Approval', 'VALIDASI')
                ->where('Sumber_Pencatatan', 'VALIDASI')
                ->select('No_Sub_Sampel', 'Id_Jenis_Analisa', 'Tahapan_Ke', 'Id_User',
                    'Tanggal', 'Jam', 'Keterangan', 'Id_Approval_Aktivitas')
                ->get();
        }

        $log = DB::table('N_EMI_LAB_Log_Aksi as l')
            ->join('N_EMI_LAB_Log_Aksi_Detail as d', 'd.Id_Log_Aksi', '=', 'l.Id_Log_Aksi')
            ->where('l.No_Sampel', $noSampel)
            ->where('l.Jenis_Aksi', 'like', 'VALIDASI%')
            ->where('l.Sub_Aksi', 'SETUJU')
            ->select('d.Id_Jenis_Analisa', 'l.Id_Log_Aksi',
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(d.Id_User)), ''), l.Id_User) AS Id_User"),
                DB::raw('COALESCE(d.Tanggal, CONVERT(varchar(10), l.Tanggal, 120)) AS Tanggal'),
                DB::raw('COALESCE(d.Jam, CONVERT(varchar(8), l.Jam, 108)) AS Jam'))
            ->get();

        $detail = collect();
        if ($this->adaTabel('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')) {
            $q = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')
                ->where('No_Sampel', $noSampel)
                ->whereNotNull('Id_User')
                ->select('No_Sub_Sampel', 'Id_Jenis_Analisa', 'Tahapan_Ke', 'Id_User',
                    'Tanggal', 'Jam', 'Id_Uji_Validasi_Detail_Final');
            // Baris backfill tidak dipakai: versi lama skripnya menyalin
            // penginput. Sebelum migrasi kolom ini belum ada, dan seluruh
            // barisnya ditulis kode validasi lama (validator asli).
            if ($this->adaKolom('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final', 'Sumber_Pencatatan')) {
                $q->where(fn ($w) => $w->whereNull('Sumber_Pencatatan')
                    ->orWhereIn('Sumber_Pencatatan', ['VALIDASI', 'PRA_MIGRASI']));
            }
            $detail = $q->get();
        }

        $norm = function (Collection $c) {
            return $c->map(function ($r) {
                $r->Waktu = $this->waktu($r->Tanggal, $r->Jam);
                $r->Id_Jenis_Analisa = (int) $r->Id_Jenis_Analisa;
                $r->Tahapan_Ke = isset($r->Tahapan_Ke) ? (int) ($r->Tahapan_Ke ?: 1) : null;
                return $r;
            })->sortBy('Waktu')->values();
        };

        return [
            'approval' => $norm($approval),
            'log'      => $norm($log),
            'detail'   => $norm($detail),
        ];
    }

    /** Jumlah foto per No_Faktur: aktif & dinonaktifkan. */
    private function foto(string $noSampel): array
    {
        $nonaktif = $this->adaKolom('N_EMI_LAB_Berkas_Uji_Lab', 'Flag_Nonaktif');

        $rows = DB::table('N_EMI_LAB_Berkas_Uji_Lab')
            ->where('No_Sampel', $noSampel)
            ->select('No_Faktur', DB::raw($nonaktif ? "ISNULL(Flag_Nonaktif, 'T') AS Nonaktif" : "'T' AS Nonaktif"))
            ->get();

        $peta = [];
        foreach ($rows as $r) {
            $k = $r->No_Faktur;
            $peta[$k] ??= ['aktif' => 0, 'nonaktif' => 0];
            $peta[$k][$r->Nonaktif === 'Y' ? 'nonaktif' : 'aktif']++;
        }

        return $peta;
    }

    private function sesiPalatabilitas(string $noSampel): Collection
    {
        return DB::table('N_EMI_LAB_Palatabilitas_Session')
            ->where('No_Po_Sampel', $noSampel)
            ->select('Id_Session', 'Status_Session', 'Tanggal_Buat', 'Jam_Buat', 'Id_User_Buat',
                'Tanggal_Final', 'Jam_Final', 'Id_User_Final')
            ->get()
            ->map(function ($r) {
                $r->Waktu_Buat = $this->waktu($r->Tanggal_Buat, $r->Jam_Buat);
                $r->Waktu_Final = $r->Id_User_Final ? $this->waktu($r->Tanggal_Final, $r->Jam_Final) : null;
                return $r;
            });
    }

    /** @return array{header: Collection, riwayat: Collection} */
    private function verifikasi(string $noSampel): array
    {
        if (!$this->adaTabel('N_EMI_LAB_Verifikasi_Header')) {
            return ['header' => collect(), 'riwayat' => collect()];
        }

        $header = DB::table('N_EMI_LAB_Verifikasi_Header')
            ->where('No_Sampel', $noSampel)
            ->get()
            ->keyBy('Kode_Aktivitas_Lab');

        $riwayat = $header->isEmpty() ? collect() : DB::table('N_EMI_LAB_Verifikasi_Riwayat')
            ->whereIn('Id_Verifikasi', $header->pluck('Id_Verifikasi')->all())
            ->orderBy('Id_Riwayat')
            ->get()
            ->map(function ($r) {
                $r->Waktu = $this->waktu($r->Tanggal, $r->Jam);
                return $r;
            });

        return ['header' => $header, 'riwayat' => $riwayat];
    }

    /** @return array{header: ?object, log: Collection} */
    private function finalisasi(string $noSampel): array
    {
        $header = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->where('No_Sampel', $noSampel)
            ->first();
        if ($header) {
            $header->Waktu = $this->waktu($header->Tanggal, $header->Jam);
        }

        $log = DB::table('N_EMI_LAB_Log_Aksi')
            ->where('No_Sampel', $noSampel)
            ->where('Jenis_Aksi', 'like', 'FINALISASI%')
            ->select('Id_Log_Aksi', 'Jenis_Aksi', 'Sub_Aksi', 'Keterangan', 'Id_User', 'Tanggal', 'Jam')
            ->orderBy('Id_Log_Aksi')
            ->get()
            ->map(function ($r) {
                $r->Waktu = $this->waktu($r->Tanggal, $r->Jam);
                return $r;
            });

        return ['header' => $header, 'log' => $log];
    }

    /**
     * @return array<string, object> kode => {Nama_Aktivitas, Urutan}
     *
     * Master tiga baris yang hampir tidak pernah berubah; disimpan 10 menit.
     */
    private function klasifikasiMaster(): array
    {
        return Cache::remember('lifecycle:klasifikasi:' . DB::connection()->getDatabaseName(), 600,
            fn () => DB::table('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab')
                ->select('Kode_Aktivitas_Lab', 'Nama_Aktivitas', 'Urutan')
                ->get()
                ->map(fn ($r) => (object) (array) $r)
                ->keyBy('Kode_Aktivitas_Lab')
                ->all());
    }

    // ======================================================================
    // Putaran
    // ======================================================================

    /**
     * Tetapkan putaran tiap baris uji (properti ->Putaran).
     *
     * Dasarnya Tahapan_Ke. Bila ada resampling ke putaran N untuk analisa ini,
     * baris analisa yang diinput SESUDAH permintaan resampling itu adalah
     * putaran N, meski Tahapan_Ke-nya tersimpan lebih kecil. Hasil putaran
     * sebelumnya pasti sudah diinput sebelum resampling diminta, karena
     * resampling adalah keputusan atas hasil itu.
     *
     * Nomor sampel tujuan sengaja tidak disyaratkan: di production ada uji
     * ulang yang diinput pada QR asal, bukan QR tujuan resampling.
     */
    private function tetapkanPutaran(Collection $uji, Collection $logRs, array &$catatan): void
    {
        $logPer = $logRs->groupBy('Id_Jenis_Analisa');
        $dikoreksi = 0;

        foreach ($uji as $r) {
            $putaran = max(1, (int) ($r->Tahapan_Ke ?: 1));

            foreach ($logPer[$r->Id_Jenis_Analisa] ?? [] as $l) {
                if ($l->Tahapan_Ke > $putaran && $r->Waktu !== null
                    && $l->Waktu !== null && $r->Waktu >= $l->Waktu) {
                    $putaran = $l->Tahapan_Ke;
                }
            }

            if ($putaran !== max(1, (int) ($r->Tahapan_Ke ?: 1))) {
                $dikoreksi++;
            }
            $r->Putaran = $putaran;
        }

        if ($dikoreksi > 0) {
            $catatan[] = 'Nomor putaran pada ' . $dikoreksi . ' baris uji tidak cocok dengan log resampling; '
                . 'putarannya disusun ulang dari waktu permintaan resampling.';
        }
    }

    // ======================================================================
    // Penyusunan kejadian
    // ======================================================================

    private function susunKlasifikasi(string $kode, Collection $baris, object $po, array $master,
                                      Collection $logRs, Collection $aktivitas, array $validasi,
                                      array $foto, Collection $sesiPlt, array $verifikasi,
                                      bool $sudahFinal, array &$catatanData): array
    {
        $plt       = $kode === 'PLT';
        $idAnalisa = $baris->pluck('Id_Jenis_Analisa')->unique()->values()->all();
        $maks      = max((int) $baris->max('Putaran'), (int) ($logRs->max('Tahapan_Ke') ?? 1));
        $kejadian  = [];
        $alur      = [];

        // Evaluasi kelayakan per baris sekali saja.
        $evaluasi = [];
        foreach ($baris as $r) {
            $evaluasi[spl_object_id($r)] = $this->rincian->evaluasiKelayakan($r, $po);
        }

        $ringkasPutaran = [];   // [putaran][idAnalisa] => ringkasan hasil, untuk "sebelumnya"

        for ($p = 1; $p <= $maks; $p++) {
            $barisP = $baris->where('Putaran', $p);
            $logKe  = $logRs->where('Tahapan_Ke', $p + 1);   // resampling yang MENUTUP putaran ini
            $logDari = $logRs->where('Tahapan_Ke', $p);      // resampling yang MEMBUKA putaran ini

            // Draf (hanya mode audit) sebelum hasil dikirim.
            foreach ($this->kejadianDraf($kode, $p, $idAnalisa, $aktivitas, $logRs, $baris) as $d) {
                $kejadian[] = $d;
            }

            if ($barisP->isEmpty() && $logKe->isNotEmpty()) {
                // Ada resampling yang menutup putaran ini, tetapi baris
                // hasilnya tidak ada lagi di sistem. Rantainya tetap
                // ditampilkan utuh, dengan penanda data tidak lengkap.
                $kejadian[] = $this->kejadian([
                    'id' => "uji-$kode-$p", 'jenis' => 'uji', 'klasifikasi' => $kode, 'putaran' => $p,
                    'tingkat' => self::MIG,
                    'judul' => $p > 1 ? 'Uji sampel resampling' : 'Uji sampel',
                    'label' => 'Data tidak ditemukan', 'waktu' => null,
                    'ringkas' => 'Hasil putaran ' . $p . ' untuk '
                        . $this->daftarNama($logKe->pluck('Id_Jenis_Analisa'), $baris)
                        . ' tidak tersimpan di sistem, tetapi tercatat diresampling.',
                ]);
                $kejadian[] = $this->kejadianResampling($kode, $p, $logKe, $baris, false);
                $alur[] = ['jenis' => 'uji', 'tingkat' => self::MIG];
                $alur[] = ['jenis' => 'rs', 'tingkat' => self::WARN];
                $catatanData[] = 'Sebagian hasil putaran yang diresampling tidak tersimpan lagi di sistem.';
                continue;
            }

            if ($barisP->isEmpty()) {
                // Resampling sudah diminta, tetapi hasil ulangnya belum diinput.
                if ($p > 1 && $logDari->isNotEmpty()) {
                    $kejadian[] = $this->kejadian([
                        'id' => "uji-$kode-$p", 'jenis' => 'uji', 'klasifikasi' => $kode, 'putaran' => $p,
                        'tingkat' => self::WAIT, 'judul' => 'Uji sampel resampling',
                        'label' => 'Menunggu', 'waktu' => null,
                        'ringkas' => 'Menunggu hasil uji ulang untuk '
                            . $this->daftarNama($logDari->pluck('Id_Jenis_Analisa'), $baris) . '.',
                    ]);
                    $alur[] = ['jenis' => 'uji', 'tingkat' => self::WAIT];
                }
                continue;
            }

            // ---- Uji sampel -------------------------------------------------
            $analisaUji = [];
            foreach ($barisP->groupBy('Id_Jenis_Analisa') as $idJa => $rows) {
                $ringkas = $this->ringkasHasil($rows, $evaluasi, $plt);
                $ringkasPutaran[$p][$idJa] = $ringkas;

                $penguji = $rows->pluck('Id_User')->filter()->unique()->values();
                $pengirim = $this->pengirim($rows, $aktivitas);

                $analisaUji[] = array_merge($ringkas, [
                    'id_jenis_analisa' => (int) $idJa,
                    'nama'        => $rows->first()->Jenis_Analisa,
                    'sub'         => $this->subTampil($rows),
                    'penguji'     => $penguji->map(fn ($u) => $this->orang($u))->all(),
                    'pengirim'    => $pengirim->diff($penguji)->map(fn ($u) => $this->orang($u))->values()->all(),
                    'waktu'       => $rows->max('Waktu'),
                    'sebelumnya'  => $p > 1 ? ($ringkasPutaran[$p - 1][$idJa] ?? null) : null,
                    'foto'        => $this->jumlahFoto($rows, $foto),
                ]);
            }

            $pengujiSemua = collect($analisaUji)->pluck('penguji')->flatten(1)->unique('id')->values();
            $pengirimSemua = collect($analisaUji)->pluck('pengirim')->flatten(1)->unique('id')->values();
            $fotoSemua = collect($analisaUji)->pluck('foto');
            $jumlahTl = collect($analisaUji)->where('status', 'bad')->count();

            $kejadian[] = $this->kejadian([
                'id' => "uji-$kode-$p", 'jenis' => 'uji', 'klasifikasi' => $kode, 'putaran' => $p,
                'tingkat' => self::OK,
                'judul' => $p > 1 ? 'Uji sampel resampling' : 'Uji sampel',
                'label' => count($analisaUji) . ' analisa',
                'waktu' => $barisP->min('Waktu'),
                'waktu_akhir' => $barisP->max('Waktu'),
                'pelaku' => $pengujiSemua->map(fn ($o) => $o + ['peran' => 'Penguji'])
                    ->concat($pengirimSemua->map(fn ($o) => $o + ['peran' => 'Pengirim']))
                    ->values()->all(),
                'ringkas' => $jumlahTl > 0
                    ? $jumlahTl . ' analisa di luar standar.'
                    : null,
                'analisa' => $analisaUji,
                'foto' => [
                    'aktif' => $fotoSemua->sum('aktif'),
                    'nonaktif' => $fotoSemua->sum('nonaktif'),
                ],
                'sumber' => 'N_EMI_LAB_Uji_Sampel ' . $barisP->pluck('No_Faktur')->unique()->take(3)->implode(', ')
                    . ($barisP->pluck('No_Faktur')->unique()->count() > 3 ? ', …' : ''),
            ]);
            $alur[] = ['jenis' => 'uji', 'tingkat' => self::OK];

            // ---- Validasi ---------------------------------------------------
            $analisaVal = [];
            foreach ($barisP->groupBy('Id_Jenis_Analisa') as $idJa => $rows) {
                $idJa = (int) $idJa;
                $rs = $logKe->firstWhere('Id_Jenis_Analisa', $idJa);
                $ringkas = $ringkasPutaran[$p][$idJa];

                if ($rs) {
                    $analisaVal[] = [
                        'id_jenis_analisa' => $idJa,
                        'nama' => $rows->first()->Jenis_Analisa,
                        'keputusan' => 'resampling',
                        'status' => $ringkas['status'],
                        'validator' => $this->orang($rs->Id_User),
                        'waktu' => $rs->Waktu,
                        'tidak_tercatat' => false,
                    ];
                    continue;
                }

                $divalidasi = $rows->contains(fn ($r) => $r->Flag_Selesai === 'Y');
                if (!$divalidasi) {
                    $analisaVal[] = [
                        'id_jenis_analisa' => $idJa,
                        'nama' => $rows->first()->Jenis_Analisa,
                        'keputusan' => 'menunggu',
                        'status' => $ringkas['status'],
                        'validator' => null, 'waktu' => null, 'tidak_tercatat' => false,
                    ];
                    continue;
                }

                $v = $this->validatorPutaran($idJa, $p, $rows, $baris, $validasi, $plt ? $sesiPlt : collect());

                $analisaVal[] = [
                    'id_jenis_analisa' => $idJa,
                    'nama' => $rows->first()->Jenis_Analisa,
                    'keputusan' => 'diterima',
                    'status' => $ringkas['status'],
                    'validator' => $v ? $this->orang($v['id']) : null,
                    'waktu' => $v['waktu'] ?? null,
                    'sumber' => $v['sumber'] ?? null,
                    'tidak_tercatat' => !$v,
                ];
            }

            $kejadian[] = $this->kejadianValidasi($kode, $p, $analisaVal);
            $alur[] = ['jenis' => 'val', 'tingkat' => end($kejadian)['tingkat']];

            // ---- Resampling yang menutup putaran ini -------------------------
            if ($logKe->isNotEmpty()) {
                $kejadian[] = $this->kejadianResampling($kode, $p, $logKe, $baris, true);
                $alur[] = ['jenis' => 'rs', 'tingkat' => self::WARN];
            }
        }

        // ---- Verifikasi ------------------------------------------------------
        foreach ($this->kejadianVerifikasi($kode, $verifikasi, $kejadian, $sudahFinal, $idAnalisa) as $v) {
            $kejadian[] = $v;
        }
        $verAkhir = collect($kejadian)->where('jenis', 'ver')->where('audit', false)->last();
        $alur[] = ['jenis' => 'ver', 'tingkat' => $verAkhir['tingkat'] ?? self::WAIT];

        // ---- Sesi palatabilitas (audit) -------------------------------------
        if ($plt) {
            foreach ($sesiPlt as $s) {
                $kejadian[] = $this->kejadian([
                    'id' => 'sesi-buka-' . $s->Id_Session, 'jenis' => 'sesi', 'klasifikasi' => $kode,
                    'putaran' => 1, 'audit' => true, 'tingkat' => self::INFO,
                    'judul' => 'Sesi uji dibuka', 'label' => 'Sesi #' . $s->Id_Session,
                    'waktu' => $s->Waktu_Buat,
                    'pelaku' => $s->Id_User_Buat ? [$this->orang($s->Id_User_Buat) + ['peran' => 'Pembuka sesi']] : [],
                    'sumber' => 'N_EMI_LAB_Palatabilitas_Session #' . $s->Id_Session,
                ]);
                if ($s->Waktu_Final) {
                    $kejadian[] = $this->kejadian([
                        'id' => 'sesi-final-' . $s->Id_Session, 'jenis' => 'sesi', 'klasifikasi' => $kode,
                        'putaran' => 1, 'audit' => true, 'tingkat' => self::INFO,
                        'judul' => 'Sesi uji difinalisasi', 'label' => 'Sesi #' . $s->Id_Session,
                        'waktu' => $s->Waktu_Final,
                        'pelaku' => [$this->orang($s->Id_User_Final) + ['peran' => 'Finalisasi sesi']],
                        'sumber' => 'N_EMI_LAB_Palatabilitas_Session #' . $s->Id_Session,
                    ]);
                }
            }
        }

        // Urutan tampil: per putaran; di dalam putaran menurut waktu, lalu
        // jenis (draf, uji, validasi, resampling); verifikasi paling akhir.
        $urutJenis = ['sesi' => 0, 'draf' => 1, 'uji' => 2, 'val' => 3, 'rs' => 4, 'ver' => 9];
        usort($kejadian, function ($a, $b) use ($urutJenis) {
            $pa = $a['jenis'] === 'ver' ? 999 : ($a['putaran'] ?? 1);
            $pb = $b['jenis'] === 'ver' ? 999 : ($b['putaran'] ?? 1);
            if ($pa !== $pb) return $pa <=> $pb;
            $ja = $urutJenis[$a['jenis']] ?? 5;
            $jb = $urutJenis[$b['jenis']] ?? 5;
            if (in_array($a['jenis'], ['draf', 'sesi'], true) || in_array($b['jenis'], ['draf', 'sesi'], true)) {
                return [($a['waktu'] ?? '9999'), $ja] <=> [($b['waktu'] ?? '9999'), $jb];
            }
            if ($ja !== $jb) return $ja <=> $jb;
            return ($a['waktu'] ?? '9999') <=> ($b['waktu'] ?? '9999');
        });

        $jumlahRs = $logRs->count();
        $namaRs = $this->daftarNama($logRs->pluck('Id_Jenis_Analisa')->unique(), $baris);
        $putaranTampil = max(1, (int) $baris->max('Putaran'));

        return [
            'kode'    => $kode,
            'nama'    => $master[$kode]->Nama_Aktivitas ?? $this->labelKlasifikasi($kode),
            'urutan'  => (int) ($master[$kode]->Urutan ?? 99),
            'jumlah_analisa' => count($idAnalisa),
            'putaran' => $putaranTampil,
            'jumlah_resampling' => $jumlahRs,
            'ringkas' => $putaranTampil . ' putaran'
                . ($jumlahRs ? ' · resampling ' . $namaRs : ''),
            'status'  => $this->statusKlasifikasi($kejadian),
            'alur'    => $alur,
            'kejadian' => array_values($kejadian),
        ];
    }

    /** Validator satu analisa pada satu putaran; null bila tidak tercatat. */
    private function validatorPutaran(int $idJa, int $p, Collection $rows, Collection $semua,
                                      array $sumber, Collection $sesiPlt): ?array
    {
        $subs = $rows->map(fn ($r) => $this->normalSub($r->No_Fak_Sub_Po, $r->No_Po_Sampel))->unique()->all();
        $mulai = $rows->min('Waktu');
        // Batas atas: input putaran berikutnya (validasi putaran ini pasti
        // terjadi sebelum itu).
        $akhir = $semua->where('Id_Jenis_Analisa', $idJa)->where('Putaran', '>', $p)->min('Waktu');

        $cocokSub = fn ($x) => in_array($this->normalSub($x->No_Sub_Sampel ?? null, $rows->first()->No_Po_Sampel), $subs, true);
        $dalamWaktu = fn ($x) => $x->Waktu !== null
            && ($mulai === null || $x->Waktu >= $mulai)
            && ($akhir === null || $x->Waktu < $akhir);

        // Tahapan pada catatan validasi mengikuti Tahapan_Ke baris uji, yang
        // pada sebagian data lama tersimpan salah. Karena itu, bila tidak ada
        // yang cocok tahapannya, dipakai catatan dalam rentang waktu putaran.
        $pilih = function (Collection $c) use ($idJa, $p, $cocokSub, $dalamWaktu) {
            $milik = $c->filter(fn ($x) => $x->Id_Jenis_Analisa === $idJa);
            $urut = fn ($x) => ($cocokSub($x) ? '0' : '1') . $x->Waktu;

            return $milik->filter(fn ($x) => ($x->Tahapan_Ke ?? 1) === $p)->sortBy($urut)->first()
                ?? $milik->filter($dalamWaktu)->sortBy($urut)->first();
        };

        // 1. Approval realtime.
        $a = $pilih($sumber['approval']);
        if ($a) {
            return ['id' => $a->Id_User, 'waktu' => $a->Waktu,
                'sumber' => 'N_EMI_LAB_Hasil_Uji_Approval_Aktivitas #' . $a->Id_Approval_Aktivitas];
        }

        // 2. Log aksi validasi di rentang waktu putaran ini.
        $l = $sumber['log']->filter(fn ($x) => $x->Id_Jenis_Analisa === $idJa && $dalamWaktu($x))->first();
        if ($l) {
            return ['id' => $l->Id_User, 'waktu' => $l->Waktu, 'sumber' => 'N_EMI_LAB_Log_Aksi #' . $l->Id_Log_Aksi];
        }

        // 3. Detail_Final realtime / pra-migrasi.
        $d = $pilih($sumber['detail']);
        if ($d) {
            return ['id' => $d->Id_User, 'waktu' => $d->Waktu,
                'sumber' => 'N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final #' . $d->Id_Uji_Validasi_Detail_Final];
        }

        // 4. Palatabilitas: pelaku finalisasi sesi.
        $idSesi = $rows->pluck('Id_Session')->filter()->unique()->all();
        $s = $sesiPlt->filter(fn ($x) => $x->Waktu_Final && (empty($idSesi) || in_array($x->Id_Session, $idSesi)))
            ->sortBy('Waktu_Final')->first();
        if ($s) {
            return ['id' => $s->Id_User_Final, 'waktu' => $s->Waktu_Final,
                'sumber' => 'N_EMI_LAB_Palatabilitas_Session #' . $s->Id_Session];
        }

        return null;
    }

    /**
     * Masih ada analisa yang menunggu validasi.
     *
     * Tidak cukup melihat tingkat kejadian Validasi: begitu satu analisa
     * diresampling, tingkatnya menjadi peringatan meski analisa lain dalam
     * putaran itu masih menunggu.
     */
    private function adaMenungguValidasi($kejadian): bool
    {
        return collect($kejadian)->contains(fn ($k) => $k['jenis'] === 'val'
            && (in_array($k['tingkat'], [self::WAIT, self::RUN], true)
                || collect($k['keputusan'] ?? [])->contains('keputusan', 'menunggu')));
    }

    private function kejadianValidasi(string $kode, int $p, array $analisa): array
    {
        $a = collect($analisa);
        $diterima   = $a->where('keputusan', 'diterima');
        $resampling = $a->where('keputusan', 'resampling');
        $menunggu   = $a->where('keputusan', 'menunggu');
        $tlDiterima = $diterima->where('status', 'bad')->count();

        if ($diterima->isEmpty() && $resampling->isEmpty()) {
            $tingkat = self::WAIT;
            $label = 'Menunggu ' . $menunggu->count();
        } else {
            $bagian = [];
            if ($diterima->count()) $bagian[] = $diterima->count() . ' diterima';
            if ($resampling->count()) $bagian[] = $resampling->count() . ' resampling';
            if ($menunggu->count()) $bagian[] = $menunggu->count() . ' menunggu';
            $label = implode(' · ', $bagian);

            if ($resampling->count() || $tlDiterima) {
                $tingkat = self::WARN;
            } elseif ($menunggu->count()) {
                $tingkat = self::RUN;
            } elseif ($diterima->every(fn ($x) => $x['tidak_tercatat'])) {
                $tingkat = self::MIG;
            } else {
                $tingkat = self::OK;
            }
        }

        $ringkas = [];
        if ($resampling->count()) {
            $ringkas[] = $resampling->pluck('nama')->implode(', ') . ' diputuskan resampling.';
        }
        if ($tlDiterima) {
            $ringkas[] = $tlDiterima . ' analisa di luar standar tetap diterima.';
        }

        $validator = $a->pluck('validator')->filter()->unique('id')->values()
            ->map(fn ($o) => $o + ['peran' => 'Validator'])->all();

        return $this->kejadian([
            'id' => "val-$kode-$p", 'jenis' => 'val', 'klasifikasi' => $kode, 'putaran' => $p,
            'tingkat' => $tingkat,
            'judul' => $p > 1 ? 'Validasi resampling' : 'Validasi',
            'label' => $label,
            'waktu' => $a->pluck('waktu')->filter()->min(),
            'waktu_akhir' => $a->pluck('waktu')->filter()->max(),
            'pelaku' => $validator,
            'ringkas' => $ringkas ? implode(' ', $ringkas) : null,
            'keputusan' => $analisa,
        ]);
    }

    private function kejadianResampling(string $kode, int $p, Collection $log, Collection $baris,
                                        bool $asalAda): array
    {
        $item = $log->map(fn ($l) => [
            'id_jenis_analisa' => $l->Id_Jenis_Analisa,
            'nama'     => $this->daftarNama(collect([$l->Id_Jenis_Analisa]), $baris),
            'asal'     => $l->No_Sampel_Resampling_Origin,
            'baru'     => $l->No_Sampel_Resampling,
            'sama'     => $l->No_Sampel_Resampling_Origin === $l->No_Sampel_Resampling,
            'oleh'     => $this->orang($l->Id_User),
            'waktu'    => $l->Waktu,
            'alasan'   => $l->Alasan ? trim((string) $l->Alasan) : null,
            'keterangan' => $l->Keterangan ? trim((string) $l->Keterangan) : null,
            'selesai'  => $l->Flag_Selesai_Resampling === 'Y'
                || $baris->where('Id_Jenis_Analisa', $l->Id_Jenis_Analisa)->where('Putaran', '>', $p)->isNotEmpty(),
            'sumber'   => 'N_EMI_LAB_Uji_Sampel_Resampling_Log #' . $l->Id_Resampling,
        ])->unique(fn ($x) => $x['id_jenis_analisa'] . '|' . $x['asal'] . '|' . $x['baru'])->values();

        $alasan = $item->pluck('alasan')->filter()->unique()->values();

        return $this->kejadian([
            'id' => "rs-$kode-$p", 'jenis' => 'rs', 'klasifikasi' => $kode, 'putaran' => $p,
            'tingkat' => self::WARN,
            'judul' => 'Resampling',
            'label' => $item->count() === 1 ? $item->first()['nama'] : $item->count() . ' analisa',
            'waktu' => $item->pluck('waktu')->filter()->min(),
            'pelaku' => $item->pluck('oleh')->filter()->unique('id')->values()
                ->map(fn ($o) => $o + ['peran' => 'Peminta resampling'])->all(),
            'resampling' => $item->all(),
            'alasan' => $alasan->isNotEmpty() ? $alasan->implode(' · ') : null,
            'ringkas' => $asalAda ? 'Hasil putaran ' . $p . ' tetap tersimpan sebagai data ditolak.' : null,
            'sumber' => $item->pluck('sumber')->implode(', '),
        ]);
    }

    /** @return array<int, array> */
    private function kejadianVerifikasi(string $kode, array $verifikasi, array $kejadian,
                                        bool $sudahFinal, array $idAnalisa): array
    {
        $header = $verifikasi['header'][$kode] ?? null;

        // Sampel lama yang difinalisasi sebelum tahap verifikasi ada: tahap
        // ini memang tidak pernah dilalui, bukan sedang ditunggu.
        if (!$header && $sudahFinal) {
            return [$this->kejadian([
                'id' => "ver-$kode", 'jenis' => 'ver', 'klasifikasi' => $kode,
                'tingkat' => self::MIG, 'judul' => 'Verifikasi', 'label' => 'Tidak melalui verifikasi',
                'waktu' => null,
                'ringkas' => 'Sampel sudah difinalisasi tanpa rekomendasi verifikator '
                    . '(difinalisasi sebelum tahap verifikasi berjalan).',
            ])];
        }

        if (!$header) {
            // Belum siap diverifikasi: masih ada analisa yang menunggu
            // validasi, atau hasil uji ulang yang belum masuk.
            $belumValid = $this->adaMenungguValidasi($kejadian)
                || collect($kejadian)->contains(fn ($k) => $k['jenis'] === 'uji' && $k['tingkat'] === self::WAIT);

            // Siapa yang bertugas memverifikasi klasifikasi ini, supaya jelas
            // rekomendasinya ditunggu dari siapa.
            $petugas = $this->rincian->petugasVerifikasi($kode, $idAnalisa);
            $nama = collect($petugas)->pluck('nama')->implode(', ');

            return [$this->kejadian([
                'id' => "ver-$kode", 'jenis' => 'ver', 'klasifikasi' => $kode,
                'tingkat' => self::WAIT, 'judul' => 'Verifikasi', 'label' => 'Menunggu',
                'waktu' => null,
                'pelaku' => collect($petugas)->map(fn ($p) => [
                    'id' => $p['id'], 'nama' => $p['nama'],
                    'peran' => $p['sebagian'] ? 'Bertugas (' . $p['jumlah'] . ' analisa)' : 'Bertugas',
                ])->all(),
                'ringkas' => empty($petugas)
                    ? 'Belum ada verifikator yang ditugaskan untuk klasifikasi ini.'
                    : ($belumValid
                        ? 'Menunggu validasi selesai, lalu rekomendasi dari ' . $nama . '.'
                        : 'Menunggu rekomendasi dari ' . $nama . '.'),
            ])];
        }

        $riwayat = $verifikasi['riwayat']->where('Id_Verifikasi', $header->Id_Verifikasi)->values();
        if ($riwayat->isEmpty()) {
            $riwayat = collect([(object) [
                'Id_Riwayat' => 0, 'Status_Sebelum' => null, 'Status_Sesudah' => $header->Kode_Status,
                'Aksi' => null, 'Catatan' => $header->Catatan, 'Id_User' => $header->Id_User,
                'Nama_User' => $header->Nama_User, 'Sumber_Aksi' => null,
                'Waktu' => $this->waktu($header->Tanggal_Keputusan, $header->Jam_Keputusan),
            ]]);
        }

        $hasil = [];
        $akhir = $riwayat->count() - 1;
        foreach ($riwayat as $i => $r) {
            [$label, $tingkat] = $this->statusVerifikasi($r->Status_Sesudah);
            $revisi = $i > 0;

            $hasil[] = $this->kejadian([
                'id' => "ver-$kode-" . ($r->Id_Riwayat ?: $i), 'jenis' => 'ver', 'klasifikasi' => $kode,
                'audit' => $i < $akhir,
                'tingkat' => $tingkat,
                'judul' => $revisi ? 'Verifikasi direvisi' : 'Verifikasi',
                'label' => $label,
                'waktu' => $r->Waktu,
                'pelaku' => [$this->orang($r->Id_User, $r->Nama_User) + ['peran' => 'Verifikator']],
                'catatan' => $r->Catatan ? trim((string) $r->Catatan) : null,
                'ringkas' => $revisi
                    ? 'Revisi ke-' . $i . ', sebelumnya ' . strtolower($this->statusVerifikasi($r->Status_Sebelum)[0]) . '.'
                    : ($r->Sumber_Aksi === 'BULK' ? 'Diputuskan lewat rekomendasi massal.' : null),
                'sumber' => $r->Id_Riwayat ? 'N_EMI_LAB_Verifikasi_Riwayat #' . $r->Id_Riwayat
                    : 'N_EMI_LAB_Verifikasi_Header #' . $header->Id_Verifikasi,
            ]);
        }

        return $hasil;
    }

    /** Draf, perubahan draf, dan draf dihapus — hanya tampil di mode audit. */
    private function kejadianDraf(string $kode, int $p, array $idAnalisa, Collection $aktivitas,
                                  Collection $logRs, Collection $baris): array
    {
        $draf = $aktivitas->filter(fn ($a) => in_array($a->Jenis_Aktivitas, self::AKTIVITAS_DRAF, true)
            && in_array($a->Id_Jenis_Analisa, $idAnalisa, true));

        $hasil = [];
        foreach ($draf as $a) {
            // Putaran draf: 1 + jumlah resampling analisa ini yang terjadi
            // sebelum draf ditulis.
            $putaran = 1 + $logRs->where('Id_Jenis_Analisa', $a->Id_Jenis_Analisa)
                ->filter(fn ($l) => $l->Waktu !== null && $a->Waktu !== null && $l->Waktu <= $a->Waktu)->count();
            if ($putaran !== $p) {
                continue;
            }

            $nama = $this->daftarNama(collect([$a->Id_Jenis_Analisa]), $baris);
            [$judul, $tingkat] = match ($a->Jenis_Aktivitas) {
                'save_delete' => ['Draf dihapus', self::WARN],
                'save_update' => ['Draf diubah', self::WARN],
                default       => [$a->Perubahan->isNotEmpty() ? 'Draf diubah' : 'Draf disimpan', self::INFO],
            };
            if ($a->Perubahan->isNotEmpty()) {
                $tingkat = self::WARN;
            }

            $hasil[] = $this->kejadian([
                'id' => 'draf-' . $a->Id_Log_Activity, 'jenis' => 'draf', 'klasifikasi' => $kode,
                'putaran' => $p, 'audit' => true, 'tingkat' => $tingkat,
                'judul' => $judul, 'label' => $nama,
                'waktu' => $a->Waktu,
                'pelaku' => [$this->orang($a->Id_User) + ['peran' => 'Penguji']],
                'perubahan' => $a->Perubahan->map(fn ($u) => [
                    'lama' => $u->Value_Lama, 'baru' => $u->Value_Baru,
                ])->values()->all(),
                'alasan' => $a->Alasan ? implode(' · ', $a->Alasan) : null,
                'sumber' => 'N_EMI_LAB_Activity_Uji_Sampel #' . $a->Id_Log_Activity,
            ]);
        }

        return $hasil;
    }

    private function kejadianRegistrasi(object $po, array $klasifikasi): array
    {
        $bagian = array_filter([
            $po->No_Po ? 'PO ' . $po->No_Po : null,
            $po->No_Batch !== null ? 'batch ' . $po->No_Batch : null,
            trim((string) $po->Nama_Mesin) !== '' ? 'mesin ' . trim($po->Nama_Mesin) : null,
            $po->Flag_Trial_Produksi === 'Y' ? 'trial produksi' : null,
        ]);

        return $this->kejadian([
            'id' => 'reg', 'jenis' => 'reg', 'tingkat' => self::OK,
            'judul' => 'Registrasi sampel', 'label' => 'Selesai',
            'waktu' => $this->waktu($po->Tanggal, $po->Jam),
            'pelaku' => $po->Id_User ? [$this->orang($po->Id_User) + ['peran' => 'Pendaftar']] : [],
            'ringkas' => $bagian ? implode(' · ', $bagian) . '.' : null,
            'jadwal' => array_map(fn ($k) => $k['nama'] . ' · ' . $k['jumlah_analisa'] . ' analisa', $klasifikasi),
            'sumber' => 'N_EMI_LAB_PO_Sampel ' . $po->No_Sampel,
        ]);
    }

    /** @return array<int, array> */
    private function kejadianFinalisasi(array $final, array $klasifikasi): array
    {
        $hasil = [];
        $ver = collect($klasifikasi)->map(fn ($k) => collect($k['kejadian'])
            ->where('jenis', 'ver')->where('audit', false)->last());
        $semuaTerverifikasi = $ver->isNotEmpty()
            && $ver->every(fn ($v) => $v && !in_array($v['tingkat'], [self::WAIT, self::MIG], true));

        if ($semuaTerverifikasi) {
            $hasil[] = $this->kejadian([
                'id' => 'siap', 'jenis' => 'siap', 'audit' => true, 'tingkat' => self::INFO,
                'judul' => 'Siap difinalisasi',
                'label' => $ver->count() . '/' . $ver->count() . ' terverifikasi',
                'waktu' => $ver->pluck('waktu')->filter()->max(),
                'pelaku' => [['id' => null, 'nama' => 'Sistem', 'peran' => 'otomatis']],
                'ringkas' => 'Semua klasifikasi sudah punya rekomendasi verifikator.',
            ]);
        }

        $header = $final['header'];
        $log = $final['log'];

        // Finalisasi ulang: catatan sebelum yang terakhir hanya di mode audit.
        foreach ($log as $i => $l) {
            if ($header && $i === $log->count() - 1) {
                break;
            }
            $hasil[] = $this->kejadian([
                'id' => 'fin-log-' . $l->Id_Log_Aksi, 'jenis' => 'fin', 'audit' => true, 'tingkat' => self::INFO,
                'judul' => 'Finalisasi tercatat', 'label' => $l->Sub_Aksi ?: 'Tercatat',
                'waktu' => $l->Waktu,
                'pelaku' => [$this->orang($l->Id_User) + ['peran' => 'Finalisasi']],
                'catatan' => $l->Keterangan ? trim((string) $l->Keterangan) : null,
                'sumber' => 'N_EMI_LAB_Log_Aksi #' . $l->Id_Log_Aksi,
            ]);
        }

        if ($header) {
            $lolos = $header->Flag_Ok !== 'T';
            $logAkhir = $log->last();
            $hasil[] = $this->kejadian([
                'id' => 'fin', 'jenis' => 'fin', 'tingkat' => $lolos ? self::OK : self::BAD,
                'judul' => 'Finalisasi', 'label' => $lolos ? 'Lolos uji' : 'Tidak lolos',
                'waktu' => $header->Waktu,
                'pelaku' => $header->Id_User ? [$this->orang($header->Id_User) + ['peran' => 'Finalisasi']] : [],
                'catatan' => $logAkhir && $logAkhir->Keterangan ? trim((string) $logAkhir->Keterangan) : null,
                'sumber' => 'N_EMI_LAB_Hasil_Uji_Validasi_Final #' . $header->Id_Uji_Validasi_Final,
            ]);
        } else {
            $hasil[] = $this->kejadian([
                'id' => 'fin', 'jenis' => 'fin', 'tingkat' => self::WAIT,
                'judul' => 'Finalisasi', 'label' => 'Menunggu', 'waktu' => null,
                'ringkas' => $semuaTerverifikasi
                    ? 'Semua klasifikasi sudah diverifikasi, menunggu keputusan akhir.'
                    : 'Sampel belum difinalisasi.',
            ]);
        }

        return $hasil;
    }

    // ======================================================================
    // Ringkasan status
    // ======================================================================

    private function statusKlasifikasi(array $kejadian): array
    {
        $k = collect($kejadian)->where('audit', false);
        $ver = $k->where('jenis', 'ver')->last();

        if ($ver && $ver['tingkat'] === self::MIG) {
            return ['label' => 'Tanpa verifikasi', 'tingkat' => self::MIG];
        }
        if ($ver && $ver['tingkat'] !== self::WAIT) {
            return ['label' => $ver['label'], 'tingkat' => $ver['tingkat']];
        }
        if ($k->contains(fn ($x) => $x['jenis'] === 'uji' && $x['tingkat'] === self::WAIT)) {
            return ['label' => 'Menunggu uji ulang', 'tingkat' => self::WARN];
        }
        if ($this->adaMenungguValidasi($k)) {
            return ['label' => 'Menunggu validasi', 'tingkat' => self::RUN];
        }

        return ['label' => 'Menunggu verifikasi', 'tingkat' => self::RUN];
    }

    private function posisi(array $klasifikasi, array $final): array
    {
        if ($final['header']) {
            $lolos = $final['header']->Flag_Ok !== 'T';
            return [
                'label' => $lolos ? 'Selesai · lolos uji' : 'Selesai · tidak lolos',
                'tingkat' => $lolos ? self::OK : self::BAD,
                'keterangan' => null,
            ];
        }

        $status = collect($klasifikasi)->map(fn ($k) => $k['status'] + ['nama' => $k['nama']]);

        foreach ([['Menunggu uji ulang', self::WARN, 'Menunggu uji sampel resampling'],
                  ['Menunggu validasi', self::RUN, 'Menunggu validasi'],
                  ['Menunggu verifikasi', self::RUN, 'Menunggu verifikasi']] as [$cari, $tingkat, $judul]) {
            $nama = $status->where('label', $cari)->pluck('nama');
            if ($nama->isNotEmpty()) {
                return ['label' => $judul, 'tingkat' => $tingkat, 'keterangan' => $nama->implode(', ')];
            }
        }

        if ($status->isEmpty()) {
            return ['label' => 'Menunggu uji sampel', 'tingkat' => self::WAIT, 'keterangan' => null];
        }

        return ['label' => 'Menunggu finalisasi', 'tingkat' => self::RUN, 'keterangan' => null];
    }

    private function fase(array $klasifikasi, array $final): array
    {
        $semua = collect($klasifikasi)->pluck('kejadian')->flatten(1)->where('audit', false);
        $tingkat = function (Collection $k) {
            if ($k->isEmpty()) return self::WAIT;
            if ($k->contains('tingkat', self::BAD)) return self::BAD;
            if ($k->every(fn ($x) => $x['tingkat'] === self::WAIT)) return self::WAIT;
            if ($k->contains(fn ($x) => in_array($x['tingkat'], [self::WAIT, self::RUN], true))) return self::RUN;
            if ($k->contains('tingkat', self::WARN)) return self::WARN;
            return self::OK;
        };

        // Keputusan resampling sudah punya fase sendiri; di fase validasi
        // hanya dinilai hasil akhirnya.
        $valAkhir = collect($klasifikasi)->map(fn ($k) => collect($k['kejadian'])->where('jenis', 'val')->last())->filter();

        $fase = [
            ['kode' => 'REG', 'label' => 'Registrasi', 'tingkat' => self::OK],
            ['kode' => 'UJI', 'label' => 'Uji sampel', 'tingkat' => $tingkat($semua->where('jenis', 'uji'))],
            ['kode' => 'VAL', 'label' => 'Validasi', 'tingkat' => $tingkat($valAkhir->map(function ($v) {
                // Validasi yang diterima tetapi validatornya tidak tercatat
                // tetap dihitung selesai.
                if ($v['tingkat'] === self::MIG) $v['tingkat'] = self::OK;
                return $v;
            }))],
        ];

        $rs = $semua->where('jenis', 'rs');
        if ($rs->isNotEmpty()) {
            $fase[] = ['kode' => 'RS', 'label' => 'Resampling', 'tingkat' => self::WARN,
                'jumlah' => collect($klasifikasi)->sum('jumlah_resampling')];
        }

        $ver = collect($klasifikasi)->map(fn ($k) => collect($k['kejadian'])->where('jenis', 'ver')->where('audit', false)->last())->filter();
        $fase[] = ['kode' => 'VER', 'label' => 'Verifikasi', 'tingkat' => $ver->isNotEmpty() && $ver->every(fn ($v) => $v['tingkat'] === self::MIG)
            ? self::MIG
            : $tingkat($ver->map(function ($v) {
                if ($v['tingkat'] === self::MIG) $v['tingkat'] = self::OK;
                return $v;
            }))];

        $fase[] = ['kode' => 'FIN', 'label' => 'Finalisasi', 'tingkat' => $final['header']
            ? ($final['header']->Flag_Ok === 'T' ? self::BAD : self::OK)
            : self::WAIT];

        return $fase;
    }

    // ======================================================================
    // Pembantu
    // ======================================================================

    /**
     * Ringkas hasil satu analisa dalam satu putaran: nilai (atau ulangan),
     * standar, dan kelayakan.
     */
    private function ringkasHasil(Collection $rows, array $evaluasi, bool $plt): array
    {
        $ev = $rows->map(fn ($r) => $evaluasi[spl_object_id($r)]);
        $nilai = $rows->map(fn ($r) => $r->Nilai_Hasil_String !== null && $r->Nilai_Hasil_String !== ''
            ? $r->Nilai_Hasil_String : $r->Hasil)->values();
        $angka = $nilai->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (float) $v);

        if ($plt) {
            $status = 'info';
        } elseif ($ev->contains('layak', 'T')) {
            $status = 'bad';
        } elseif ($ev->contains(fn ($e) => $e['layak'] === RincianHasilAnalisaService::LAYAK_TIDAK_TENTU)) {
            $status = 'na';
        } else {
            $status = 'ok';
        }

        $e1 = $ev->first();
        $standar = null;
        if (($e1['dasar'] ?? null) === 'RENTANG') {
            $standar = ['jenis' => 'rentang', 'min' => $e1['rincian']['min'], 'max' => $e1['rincian']['max']];
        } elseif (($e1['dasar'] ?? null) === 'KRITERIA') {
            $standar = ['jenis' => 'kriteria', 'teks' => $e1['rincian']['kriteria_terpilih'] ?? null];
        }

        return [
            'jumlah'  => $rows->count(),
            'nilai'   => $nilai->take(40)->all(),
            // Palatabilitas: tiap nilai milik satu pembanding.
            'rincian' => $plt ? $rows->take(40)->map(fn ($r) => [
                'pembanding' => $r->Id_Pembanding ? ($this->pembanding[$r->Id_Pembanding] ?? ('Pembanding #' . $r->Id_Pembanding)) : null,
                'nilai'      => $r->Nilai_Hasil_String !== null && $r->Nilai_Hasil_String !== '' ? $r->Nilai_Hasil_String : $r->Hasil,
            ])->values()->all() : null,
            'min'     => $angka->count() > 1 ? $angka->min() : null,
            'maks'    => $angka->count() > 1 ? $angka->max() : null,
            'standar' => $standar,
            'status'  => $status,
            'keterangan' => $plt ? null : ($e1['ringkas'] ?? null),
            'jumlah_tidak_layak' => $ev->where('layak', 'T')->count(),
        ];
    }

    /** Pengirim hasil (Activity save_submit) untuk baris-baris ini. */
    private function pengirim(Collection $rows, Collection $aktivitas): Collection
    {
        $waktu = $rows->pluck('Waktu')->filter()->unique()->all();
        $idJa = $rows->first()->Id_Jenis_Analisa;
        $sub = $this->normalSub($rows->first()->No_Fak_Sub_Po, $rows->first()->No_Po_Sampel);

        return $aktivitas->filter(fn ($a) => in_array($a->Jenis_Aktivitas, self::AKTIVITAS_KIRIM, true)
            && $a->Id_Jenis_Analisa === $idJa
            && $this->normalSub($a->No_Fak_Sub_Po, $rows->first()->No_Po_Sampel) === $sub
            && in_array($a->Waktu, $waktu, true))
            ->pluck('Id_User')->filter()->unique()->values();
    }

    private function jumlahFoto(Collection $rows, array $foto): array
    {
        $aktif = 0;
        $nonaktif = 0;
        foreach ($rows->pluck('No_Faktur')->unique() as $f) {
            $aktif += $foto[$f]['aktif'] ?? 0;
            $nonaktif += $foto[$f]['nonaktif'] ?? 0;
        }

        return ['aktif' => $aktif, 'nonaktif' => $nonaktif];
    }

    private function subTampil(Collection $rows): ?string
    {
        $sub = $rows->pluck('No_Fak_Sub_Po')->filter()->unique()->values();

        return $sub->isEmpty() ? null : $sub->implode(', ');
    }

    private function daftarNama(Collection $idJa, Collection $baris): string
    {
        return $idJa->unique()->map(function ($id) use ($baris) {
            $r = $baris->firstWhere('Id_Jenis_Analisa', (int) $id);
            return $r ? $r->Jenis_Analisa : ('analisa #' . $id);
        })->implode(', ');
    }

    private function statusVerifikasi(?string $kode): array
    {
        return match ($kode) {
            'REKOMENDASI'         => ['Direkomendasikan', self::OK],
            'REKOM_BERSYARAT'     => ['Bersyarat', self::WARN],
            'TIDAK_REKOM'         => ['Tidak direkomendasikan', self::BAD],
            'DISETUJUI'           => ['Disetujui', self::OK],
            'DISETUJUI_BERSYARAT' => ['Disetujui bersyarat', self::WARN],
            'DITOLAK'             => ['Ditolak', self::BAD],
            'MENUNGGU', null, ''  => ['Menunggu', self::WAIT],
            default               => [ucfirst(strtolower(str_replace('_', ' ', $kode))), self::INFO],
        };
    }

    private function labelKlasifikasi(string $kode): string
    {
        return ['ANL' => 'Analisa Lab', 'PLT' => 'Uji Palatabilitas', 'LCKV' => 'Look View'][$kode] ?? $kode;
    }

    /** Bentuk seragam satu kejadian. */
    private function kejadian(array $k): array
    {
        return $k + [
            'klasifikasi' => null, 'putaran' => null, 'audit' => false,
            'waktu' => null, 'waktu_akhir' => null, 'pelaku' => [],
            'ringkas' => null, 'catatan' => null, 'alasan' => null, 'sumber' => null,
        ];
    }

    private function orang(?string $id, ?string $namaCadangan = null): array
    {
        $id = $id !== null ? trim($id) : null;

        return [
            'id'   => $id,
            'nama' => ($id && isset($this->nama[$id])) ? $this->nama[$id] : ($namaCadangan ?: ($id ?: 'Tidak tercatat')),
        ];
    }

    private function muatNama(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($x) => $x !== null ? trim((string) $x) : null, $ids))));

        foreach (array_chunk($ids, 500) as $bagian) {
            foreach (DB::table('N_EMI_LAB_Users')->whereIn('UserId', $bagian)->pluck('Nama', 'UserId') as $id => $n) {
                $this->nama[trim((string) $id)] = trim((string) $n) ?: $id;
            }
        }
    }

    /**
     * Tanggal + jam menjadi "Y-m-d H:i:s" yang dapat diurutkan. Tanggal bisa
     * berupa date, datetime, atau teks; jam bisa varchar atau time.
     */
    private function waktu($tanggal, $jam): ?string
    {
        if ($tanggal === null || $tanggal === '') {
            return null;
        }

        $t = substr((string) $tanggal, 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) {
            return null;
        }

        $j = $jam !== null && $jam !== '' ? substr((string) $jam, 0, 8) : substr((string) $tanggal, 11, 8);
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $j)) {
            $j = '00:00:00';
        } elseif (strlen($j) === 5) {
            $j .= ':00';
        }

        return $t . ' ' . $j;
    }

    private function normalSub($sub, string $noSampel): string
    {
        $sub = trim((string) $sub);

        return ($sub === '' || $sub === $noSampel || $sub === '-') ? '' : $sub;
    }

    /**
     * Keberadaan tabel/kolom migrasi. Disimpan 10 menit: setelah migrasi
     * dijalankan, sumber baru terbaca paling lambat 10 menit kemudian.
     */
    private function adaTabel(string $tabel): bool
    {
        return $this->cacheSkema['t:' . $tabel] ??= (bool) Cache::remember(
            'lifecycle:skema:' . DB::connection()->getDatabaseName() . ':t:' . $tabel, 600,
            fn () => Schema::hasTable($tabel));
    }

    private function adaKolom(string $tabel, string $kolom): bool
    {
        return $this->cacheSkema['k:' . $tabel . '.' . $kolom] ??= $this->adaTabel($tabel) && (bool) Cache::remember(
            'lifecycle:skema:' . DB::connection()->getDatabaseName() . ':k:' . $tabel . '.' . $kolom, 600,
            fn () => Schema::hasColumn($tabel, $kolom));
    }
}
