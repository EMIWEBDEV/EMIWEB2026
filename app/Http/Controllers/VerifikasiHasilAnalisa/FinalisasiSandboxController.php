<?php

namespace App\Http\Controllers\VerifikasiHasilAnalisa;

use App\Http\Controllers\Controller;
use App\Services\CakupanMesinService;
use App\Services\IdentitasPoService;
use App\Services\RincianHasilAnalisaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FINALISASI TRIAL PRODUKSI — modul pembaharuan (sandbox).
 *
 * Halaman ini hanya dibuka dari sesi sandbox (/trial-ui). Sidebar
 * mengarahkan ke sini bila sesi bertanda 'sandbox_trial'; sesi login biasa
 * tetap membuka modul lama di /finalisai/trial-produksi.
 *
 * MODUL INI TERISOLASI:
 *   - Berkas, rute, dan tabelnya terpisah dari modul finalisasi lama.
 *   - Membaca hasil verifikasi (N_EMI_LAB_Verifikasi_Header) sebagai
 *     syarat masuk antrean — inilah yang belum dilakukan modul lama.
 *   - Belum menulis apa pun; tahap penyimpanan menyusul setelah rancangan
 *     pada docs/pembaharuan/MOCKUP-FINALISASI.md disetujui.
 */
class FinalisasiSandboxController extends Controller
{
    private const T_VERIFIKASI = 'N_EMI_LAB_Verifikasi_Header';

    /**
     * $rincian: disusun oleh service yang sama dengan layar Verifikasi,
     * sehingga kriteria, kelayakan, penginput, dan validator yang tampil di
     * sini persis sama dengan yang dilihat verifikator.
     *
     * $cakupan: finalisasi hanya melayani sampel mesin AUTOCLAVE.
     *
     * $identitas: nama barang & nomor formula tiap PO — sama dengan layar
     * Validasi sandbox.
     */
    public function __construct(
        private RincianHasilAnalisaService $rincian,
        private CakupanMesinService $cakupan,
        private IdentitasPoService $identitas
    ) {
    }

    /** Halaman finalisasi versi pembaharuan. */
    public function index()
    {
        return inertia('vue/dashboard/verifikasi-hasil-analisa/FinalisasiSandbox');
    }

    /**
     * Daftar sampel yang siap difinalisasi.
     *
     * Syaratnya berbeda dari modul lama: sampel baru masuk antrean bila
     * SELURUH klasifikasinya sudah diverifikasi. Modul lama menyimpulkannya
     * dari Flag_Selesai pada baris uji saja, sehingga sampel yang belum
     * diverifikasi pun bisa difinalisasi.
     */
    public function daftar(Request $request)
    {
        try {
            $cari = trim((string) $request->input('search', ''));

            // Kolom yang dipilih sama dengan daftar kerja verifikasi, supaya
            // rincian tiap klasifikasi dapat disusun oleh service yang sama.
            $uji = DB::table('N_EMI_LAB_Uji_Sampel as u')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'u.Id_Jenis_Analisa')
                ->join('N_EMI_LAB_PO_Sampel as p', 'p.No_Sampel', '=', 'u.No_Po_Sampel')
                ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl',
                    'kl.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab')
                // Bucket finalisasi = sampel yang BELUM difinalisasi.
                // Begitu keputusan finalisasi ditetapkan, Flag_Final terisi
                // dan sampelnya hilang dari daftar ini — tidak perlu status
                // tersendiri untuk menandainya.
                ->where('u.Flag_Selesai', 'Y')
                ->whereNull('u.Status')
                ->whereNull('u.Flag_Final')
                // Hanya hasil putaran yang berlaku (lihat tanpaPutaranDitolak).
                ->tap(fn ($q) => $this->rincian->tanpaPutaranDitolak($q))
                ->where('p.Flag_Trial_Produksi', 'Y')
                ->whereNull('p.Flag_Selesai')
                // Finalisasi hanya untuk sampel mesin AUTOCLAVE.
                ->tap(fn ($q) => $this->cakupan->batasi($q, 'p.Id_Mesin'))
                ->leftJoin('N_EMI_LAB_Users as us', 'us.UserId', '=', 'u.Id_User')
                ->select(
                    'u.No_Po_Sampel', 'u.No_Fak_Sub_Po', 'u.No_Faktur', 'u.Id_Jenis_Analisa',
                    'u.Hasil', 'u.Nilai_Hasil_String', 'u.Range_Awal', 'u.Range_Akhir',
                    'u.Flag_Layak', 'u.Flag_Perhitungan', 'u.Tahapan_Ke',
                    'u.Id_Session', 'u.Id_Pembanding', 'u.Tanggal', 'u.Jam', 'u.Id_User',
                    // Pemilik baris uji adalah PENGINPUT hasil.
                    'us.Nama as Nama_User_Input',
                    'ja.Jenis_Analisa', 'ja.Kode_Analisa',
                    'ja.Kode_Aktivitas_Lab', 'kl.Nama_Aktivitas', 'kl.Urutan',
                    'p.No_Po', 'p.No_Split_Po', 'p.No_Batch', 'p.Kode_Barang',
                    'p.Id_Mesin', 'p.Tanggal as Tanggal_Daftar', 'p.Jam as Jam_Daftar',
                    DB::raw('(SELECT TOP 1 m.Nama_Mesin FROM EMI_Master_Mesin m
                              WHERE m.Id_Master_Mesin = p.Id_Mesin) AS Nama_Mesin')
                )
                // Urutan sama dengan daftar kerja verifikasi: baris parameter
                // palatabilitas dipasangkan ke nama parameternya menurut
                // urutan baris.
                ->orderByDesc('u.No_Po_Sampel')
                ->get();

            // Seluruh isi antrean butuh finalisasi — sampel yang sudah
            // difinalisasi keluar dengan sendirinya — jadi tidak ada saringan
            // status, hanya pencarian.
            $totalAntrean = $uji->pluck('No_Po_Sampel')->unique()->count();

            // Pencarian dicocokkan di sini, bukan di SQL: nama barang dan
            // nomor formula tidak ada di tabel uji. Antreannya kecil
            // (produksi: ±1.100 baris uji, ±200 ms), jadi tetap ringan.
            if ($cari !== '') {
                $uji = $uji->filter(fn ($r) => IdentitasPoService::cocok($cari, [
                    $r->No_Po_Sampel, $r->No_Po, $r->Kode_Barang,
                    $this->identitas->namaBarang($r->Kode_Barang),
                    $this->identitas->formula($r->No_Po),
                ]))->values();
            }

            if ($uji->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'result'  => [
                        'data'          => [],
                        'total_antrean' => $totalAntrean,
                        'cakupan_mesin' => $this->cakupan->nama(),
                        'pagination'    => ['page' => 1, 'limit' => max(5, min(50, (int) $request->input('limit', 10))),
                                            'total_data' => 0, 'total_page' => 1, 'dari' => 0, 'sampai' => 0],
                    ],
                ], 200);
            }

            // Dipecah per 1.000: SQL Server membatasi 2.100 parameter.
            $verifikasi = $uji->pluck('No_Po_Sampel')->unique()->chunk(1000)
                ->flatMap(fn ($no) => DB::table(self::T_VERIFIKASI)->whereIn('No_Sampel', $no->values())->get())
                ->groupBy('No_Sampel');

            // Tahap 1 — ringkas, untuk seluruh sampel yang cocok: cukup untuk
            // paginasi. Rincian analisa baru disusun untuk sampel yang tampil
            // (tahap 2).
            $data = $uji
                ->groupBy('No_Po_Sampel')
                ->map(function ($rows, $noSampel) use ($verifikasi) {
                    $f  = $rows->first();
                    $vh = $verifikasi->get($noSampel, collect());

                    // Ringkasan per klasifikasi: berapa analisa, dan apa
                    // rekomendasi verifikatornya.
                    $klasifikasi = $rows
                        ->groupBy('Kode_Aktivitas_Lab')
                        ->map(function ($isi, $kode) use ($vh) {
                            $v = $vh->firstWhere('Kode_Aktivitas_Lab', $kode);

                            return [
                                'Kode_Aktivitas_Lab' => $kode,
                                'Nama_Aktivitas'     => $isi->first()->Nama_Aktivitas ?? $kode,
                                'Urutan'             => $isi->first()->Urutan ?? 99,
                                'Jumlah_Analisa'     => $isi->pluck('Id_Jenis_Analisa')->unique()->count(),
                                'Kode_Status'        => $v->Kode_Status ?? 'MENUNGGU',
                                'Kode_Keputusan'     => $v->Kode_Keputusan ?? null,
                                'Catatan'            => $v->Catatan ?? null,
                                'Nama_User'          => $v->Nama_User ?? null,
                                'Tanggal_Keputusan'  => $v->Tanggal_Keputusan ?? null,
                                'Jam_Keputusan'      => $v->Jam_Keputusan ?? null,
                                'Revisi_Ke'          => (int) ($v->Revisi_Ke ?? 0),
                                '_rows'              => $isi,
                            ];
                        })
                        ->sortBy('Urutan')
                        ->values();

                    $belum = $klasifikasi->where('Kode_Status', 'MENUNGGU')->count();

                    return [
                        'No_Sampel'        => $noSampel,
                        'No_Po'            => $f->No_Po,
                        'No_Split_Po'      => $f->No_Split_Po,
                        'No_Batch'         => $f->No_Batch,
                        'Kode_Barang'      => trim((string) $f->Kode_Barang),
                        'Nama_Barang'      => $this->identitas->namaBarang($f->Kode_Barang) ?? '-',
                        'Kode_Formula'     => $this->identitas->formula($f->No_Po),
                        'Id_Mesin'         => $f->Id_Mesin,
                        'Nama_Mesin'       => trim((string) $f->Nama_Mesin) ?: null,
                        'Tanggal'          => $f->Tanggal_Daftar,
                        'Jam'              => $f->Jam_Daftar,
                        'Jumlah_Analisa'   => $rows->pluck('Id_Jenis_Analisa')->unique()->count(),
                        'klasifikasi'      => $klasifikasi,
                        // Siap difinalisasi hanya bila tidak ada klasifikasi
                        // yang masih menunggu rekomendasi verifikator.
                        'Siap'             => $belum === 0,
                        'Belum_Verifikasi' => $belum,
                        'Saran'            => $this->saranKeputusan($klasifikasi),
                        // Status finalisasi sampel. Selama keputusan belum
                        // ditetapkan, seluruh sampel berstatus MENUNGGU —
                        // sama polanya dengan layar Verifikasi.
                        'Kode_Status'      => 'MENUNGGU',
                    ];
                })
                ->sortByDesc('No_Sampel')
                ->values();

            $perHal  = max(5, min(50, (int) $request->input('limit', 10)));
            $total   = $data->count();
            $maksHal = max(1, (int) ceil($total / $perHal));
            $hal     = min(max(1, (int) $request->input('page', 1)), $maksHal);

            return response()->json([
                'success' => true,
                'result'  => [
                    'data'       => $this->lengkapiRincian($data->forPage($hal, $perHal)->values()),
                    // Jumlah seluruh antrean, sebelum pencarian.
                    'total_antrean' => $totalAntrean,
                    // Ditampilkan di layar agar jelas mengapa sampel mesin
                    // lain tidak pernah muncul di antrean.
                    'cakupan_mesin' => $this->cakupan->nama(),
                    'pagination' => [
                        'page'       => $hal,
                        'limit'      => $perHal,
                        'total_data' => $total,
                        'total_page' => $maksHal,
                        'dari'       => $total ? (($hal - 1) * $perHal) + 1 : 0,
                        'sampai'     => min($hal * $perHal, $total),
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Gagal memuat daftar finalisasi.',
                'debug'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tahap 2 — rincian analisa tiap klasifikasi untuk sampel yang tampil.
     *
     * Bentuknya sama persis dengan daftar kerja verifikasi (kriteria,
     * kelayakan, resampling, penginput, validator), ditambah daftar foto
     * pada analisa yang memilikinya.
     */
    private function lengkapiRincian($halaman)
    {
        $baris      = $halaman->flatMap(fn ($it) => $it['klasifikasi']->flatMap(fn ($k) => $k['_rows']));
        $pembanding = $this->rincian->petaPembanding(
            $baris->pluck('Id_Pembanding')->filter()->unique()->all());
        $validasi   = $this->rincian->petaValidasi($halaman->pluck('No_Sampel')->all());
        $foto       = $this->petaFoto($baris->pluck('No_Faktur')->filter()->unique()->values()->all());
        $petaUlang  = $this->rincian->petaResamplingBanyak($halaman->pluck('No_Sampel')->all());

        return $halaman->map(function ($it) use ($pembanding, $validasi, $foto, $petaUlang) {
            // Batas rentang di master bergantung pada barang dan mesin sampel.
            $po         = (object) ['Kode_Barang' => $it['Kode_Barang'], 'Id_Mesin' => $it['Id_Mesin']];
            $resampling = $petaUlang[$it['No_Sampel']] ?? [];

            $it['klasifikasi'] = $it['klasifikasi']->map(function ($k) use ($po, $resampling, $pembanding, $validasi, $foto) {
                $rows = $k['_rows'];
                unset($k['_rows']);

                $r = $this->rincian->rincian($rows, $po, $resampling, $pembanding, $validasi);

                $r['analisa'] = $r['analisa']
                    ->map(fn ($a) => $a + ['Foto' => $foto[$a['No_Faktur']] ?? []])
                    ->values();

                // Siapa yang bertugas memverifikasi klasifikasi ini — supaya
                // "belum diverifikasi" jelas menunggu siapa.
                $r['Petugas_Verifikasi'] = $this->rincian->petugasVerifikasi(
                    $k['Kode_Aktivitas_Lab'], $rows->pluck('Id_Jenis_Analisa')->all());

                return $k + $r;
            })->values();

            return $it;
        });
    }

    /**
     * Berkas foto per faktur uji.
     *
     * Foto analisa lab tersimpan di N_EMI_LAB_Berkas_Uji_Lab — bukan
     * N_EMI_LIMS_Berkas_Uji_Lab, yang milik modul formulator. Hanya kuncinya
     * yang dikirim; gambarnya diambil lewat endpoint stream modul lab, yang
     * sudah menangani token dan perizinannya.
     *
     * @return array<string, array<int, array{key:string, keterangan:?string}>>
     */
    private function petaFoto(array $faktur): array
    {
        $peta = [];

        foreach (array_chunk($faktur, 1000) as $bagian) {
            DB::table('N_EMI_LAB_Berkas_Uji_Lab')
                ->whereIn('No_Faktur', $bagian)
                ->orderBy('Id_Berkas_Lab')
                ->select('No_Faktur', 'Berkas_Key', 'Keterangan')
                ->get()
                ->each(function ($b) use (&$peta) {
                    $peta[$b->No_Faktur][] = ['key' => $b->Berkas_Key, 'keterangan' => $b->Keterangan];
                });
        }

        return $peta;
    }

    /**
     * Saran tingkat keputusan berdasarkan rekomendasi verifikator.
     *
     * Aturannya: tingkat terendah menang. Satu klasifikasi yang tidak
     * direkomendasikan menurunkan saran untuk keseluruhan sampel.
     */
    private function saranKeputusan($klasifikasi): array
    {
        $status = $klasifikasi->pluck('Kode_Status');

        if ($status->contains('MENUNGGU')) {
            return ['kode' => null, 'warna' => 'n',
                    'teks' => 'Menunggu verifikasi'];
        }
        if ($status->contains('TIDAK_REKOM')) {
            return ['kode' => 'TIDAK_REKOM', 'warna' => 'bad',
                    'teks' => 'Tidak Direkomendasikan'];
        }
        if ($status->contains('REKOM_BERSYARAT')) {
            return ['kode' => 'REKOM_BERSYARAT', 'warna' => 'warn',
                    'teks' => 'Direkomendasikan Bersyarat'];
        }

        return ['kode' => 'REKOMENDASI', 'warna' => 'ok',
                'teks' => 'Direkomendasikan'];
    }


    /**
     * Master tingkat rekomendasi.
     *
     * Dipakai bersama dengan modul verifikasi: tingkatannya sama persis,
     * termasuk aturan wajib-catatan dan panjang minimalnya, sehingga tidak
     * perlu master tersendiri.
     */
    public function masterKeputusan()
    {
        try {
            $rows = DB::table('N_EMI_LAB_Verifikasi_Keputusan as k')
                ->join('N_EMI_LAB_Verifikasi_Status as s', 's.Kode_Status', '=', 'k.Kode_Status')
                ->where('k.Flag_Aktif', 'Y')
                ->orderBy('k.Urutan')
                ->select('k.Kode_Keputusan', 'k.Nama_Keputusan', 'k.Keterangan',
                    'k.Kode_Status', 's.Nama_Status', 'k.Flag_Wajib_Catatan',
                    'k.Panjang_Min_Catatan', 'k.Flag_Boleh_Lanjut',
                    'k.Flag_Perlu_Tindak', 'k.Warna_Badge', 'k.Ikon')
                ->get();

            return response()->json(['success' => true, 'result' => $rows], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat master keputusan.');
        }
    }

    /**
     * Simpan keputusan finalisasi.
     *
     * Belum menulis ke tabel mana pun: struktur tabel finalisasi baru
     * (N_EMI_LAB_Finalisasi_*) menunggu persetujuan rancangan pada
     * docs/pembaharuan/MOCKUP-FINALISASI.md. Yang sudah berjalan di sini
     * adalah seluruh pemeriksaannya, sehingga aturannya dapat diuji lebih
     * dulu tanpa meninggalkan data yang mungkin perlu dibongkar lagi.
     */
    public function simpanKeputusan(Request $request)
    {
        $request->validate([
            'keputusan' => 'required|string',
            'items'     => 'required|array|min:1',
        ]);

        try {
            $kep = DB::table('N_EMI_LAB_Verifikasi_Keputusan')
                ->where('Kode_Keputusan', $request->input('keputusan'))
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (!$kep) {
                return response()->json([
                    'success' => false, 'status' => 422,
                    'message' => 'Tingkat rekomendasi tidak dikenali.',
                ], 422);
            }

            $catatan = trim((string) $request->input('catatan', ''));

            if ($kep->Flag_Wajib_Catatan === 'Y') {
                if ($catatan === '') {
                    return response()->json([
                        'success' => false, 'status' => 422,
                        'message' => 'Keputusan "' . $kep->Nama_Keputusan
                            . '" wajib disertai catatan sebagai dasar keputusan.',
                    ], 422);
                }

                $min = (int) ($kep->Panjang_Min_Catatan ?? 0);
                if ($min > 0 && mb_strlen($catatan) < $min) {
                    return response()->json([
                        'success' => false, 'status' => 422,
                        'message' => 'Catatan terlalu singkat. Jelaskan alasan dengan '
                            . 'sekurang-kurangnya ' . $min . ' karakter agar jejak audit bermakna.',
                    ], 422);
                }
            }

            // Hanya sampel mesin AUTOCLAVE yang boleh difinalisasi. Diperiksa
            // di server, karena permintaan dapat dikirim tanpa melalui layar.
            $noSampel = collect($request->input('items', []))
                ->pluck('No_Sampel')->filter()->unique()->values();
            $mesin    = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereIn('No_Sampel', $noSampel->all())
                ->pluck('Id_Mesin', 'No_Sampel');
            $luar     = $noSampel
                ->reject(fn ($ns) => $this->cakupan->mencakup($mesin[$ns] ?? null))
                ->values();

            if ($luar->isNotEmpty()) {
                return response()->json([
                    'success' => false, 'status' => 422,
                    'message' => 'Finalisasi hanya untuk sampel mesin '
                        . implode(', ', $this->cakupan->nama())
                        . '. Di luar cakupan: ' . $luar->implode(', ') . '.',
                ], 422);
            }

            // Sampel yang klasifikasinya belum lengkap diverifikasi tidak
            // boleh difinalisasi — inilah gerbang yang tidak dimiliki modul
            // lama.
            $belum = [];
            foreach ($request->input('items', []) as $it) {
                $noSampel = $it['No_Sampel'] ?? null;
                if (!$noSampel) continue;

                $adaMenunggu = DB::table('N_EMI_LAB_Uji_Sampel as u')
                    ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'u.Id_Jenis_Analisa')
                    ->leftJoin(self::T_VERIFIKASI . ' as v', function ($j) {
                        $j->on('v.No_Sampel', '=', 'u.No_Po_Sampel')
                          ->on('v.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab');
                    })
                    ->where('u.No_Po_Sampel', $noSampel)
                    ->where('u.Flag_Selesai', 'Y')
                    ->whereNull('u.Status')
                    ->whereNull('v.Id_Verifikasi')
                    ->exists();

                if ($adaMenunggu) {
                    $belum[] = $noSampel;
                }
            }

            if (!empty($belum)) {
                return response()->json([
                    'success' => false, 'status' => 422,
                    'message' => 'Sampel berikut masih menunggu rekomendasi verifikator: '
                        . implode(', ', $belum) . '.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => count($request->input('items')) . ' sampel: '
                    . $kep->Nama_Keputusan
                    . ' — pemeriksaan lolos, penyimpanan menunggu persetujuan rancangan tabel.',
                'result'  => [
                    'tersimpan'      => false,
                    'alasan'         => 'Tabel N_EMI_LAB_Finalisasi_* belum dibuat.',
                    'kode_keputusan' => $kep->Kode_Keputusan,
                ],
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal menyimpan keputusan finalisasi.');
        }
    }

    private function gagal(\Exception $e, string $pesan)
    {
        return response()->json([
            'success' => false, 'status' => 500,
            'message' => $pesan, 'debug' => $e->getMessage(),
        ], 500);
    }
}
