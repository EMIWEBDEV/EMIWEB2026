<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CONTOH DATA MOCKUP — siklus Validasi -> Verifikasi -> Finalisasi -> Hasil
 * Analisa yang datanya disimpan di SESSION STORAGE browser.
 *
 * Service ini hanya MEMBACA database: sampel template, master analisa,
 * kriteria kelayakan, pembanding palatabilitas, foto, dan nama akun. Hasilnya
 * JSON sampel "menunggu validasi" yang disimpan browser; seluruh keputusan
 * sesudahnya (validasi, uji ulang, verifikasi, finalisasi) terjadi di
 * browser. Tidak ada satu baris pun yang ditulis ke database, sehingga aman
 * dijalankan di database mana pun.
 *
 * Penilaian kelayakan disusun oleh RincianHasilAnalisaService — sama dengan
 * layar Verifikasi — sehingga contoh data tampil persis seperti data asli.
 *
 * Skenario per klasifikasi:
 *   layak | tidak_layak | tanpa_master | campuran   (Look View, Analisa Lab)
 *   angka                                           (Palatabilitas)
 *   tidak                                           (klasifikasi tidak diikutkan)
 */
class MockupContohService
{
    public const SKENARIO = [
        'layak'        => 'Semua layak',
        'tidak_layak'  => 'Ada yang tidak layak',
        'tanpa_master' => 'Belum ada di master',
        'campuran'     => 'Campuran',
    ];

    /** Kode analisa Look View yang diberi foto. */
    private const WARNA = 'P-QA-LV-WARNA', TEKSTUR = 'P-QA-LV-TEKSTUR-POUCH';

    public function __construct(
        private SampelDummyService $dummy,
        private RincianHasilAnalisaService $rincian,
        private IdentitasPoService $identitas
    ) {
    }

    /**
     * @param  string[] $nomor     nomor sampel contoh (disusun browser)
     * @param  array    $opsi      ['LCKV' => skenario|'tidak', 'ANL' => …, 'PLT' => 'angka'|'tidak',
     *                              'foto_warna' => 0..5, 'foto_tekstur' => 0..5]
     * @param  string   $sekarang  waktu browser 'Y-m-d H:i:s' — seluruh waktu contoh
     *                             disusun mundur dari sini
     * @return array[]
     */
    public function buat(array $nomor, array $opsi, string $sekarang): array
    {
        $tpl = (string) config('sandbox.sampel_template');
        $po  = DB::table('N_EMI_LAB_PO_Sampel')->where('No_Sampel', $tpl)->first();
        if (!$po) {
            throw new RuntimeException("Sampel template {$tpl} tidak ditemukan.");
        }

        $pilih = [
            'LCKV' => $this->skenarioSah($opsi['LCKV'] ?? 'layak'),
            'ANL'  => $this->skenarioSah($opsi['ANL'] ?? 'layak'),
            'PLT'  => ($opsi['PLT'] ?? 'angka') === 'tidak' ? 'tidak' : 'angka',
        ];
        if (!array_filter($pilih, fn ($s) => $s !== 'tidak')) {
            throw new RuntimeException('Pilih sekurang-kurangnya satu klasifikasi.');
        }

        // ---- Bahan bersama (sekali untuk semua sampel) ----------------
        $uji = $this->dummy->barisBerlaku($tpl);
        $ja  = DB::table('N_EMI_LAB_Jenis_Analisa as ja')
            ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl', 'kl.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab')
            ->whereIn('ja.id', $uji->pluck('Id_Jenis_Analisa')->unique()->all())
            ->select('ja.id', 'ja.Jenis_Analisa', 'ja.Kode_Analisa', 'ja.Kode_Aktivitas_Lab', 'kl.Nama_Aktivitas', 'kl.Urutan')
            ->get()->keyBy('id');
        $pengguna = DB::table('N_EMI_LAB_Users')
            ->whereIn('UserId', $uji->pluck('Id_User')->push($po->Id_User)->filter()->unique()->all())
            ->pluck('Nama', 'UserId');
        $kode     = $ja->map(fn ($j) => trim((string) $j->Kode_Analisa))->all();
        $kriteria = $this->dummy->kriteriaLookView($kode);
        $foto     = $this->dummy->stokFoto(30);
        $po2      = (object) ['Kode_Barang' => $po->Kode_Barang, 'Id_Mesin' => $po->Id_Mesin];
        $mesin    = DB::table('EMI_Master_Mesin')->where('Id_Master_Mesin', $po->Id_Mesin)->value('Nama_Mesin');
        $pembanding = $this->rincian->petaPembanding($uji->pluck('Id_Pembanding')->filter()->unique()->all());
        $this->rincian->siapkanMaster($uji->pluck('Id_Jenis_Analisa')->all(), $po2);

        // Garis waktu template dipadatkan: registrasi 2 jam lalu, hasil
        // terakhir 5 menit lalu, urutan kejadian tetap sama.
        $akhir  = Carbon::parse($sekarang);
        $tReg   = $this->waktu($po->Tanggal, $po->Jam);
        $tAkhir = $uji->map(fn ($r) => $this->waktu($r->Tanggal, $r->Jam))->max();
        $rentang = max(1, $tReg->diffInSeconds($tAkhir));
        $geser  = fn (Carbon $t) => $akhir->copy()->subMinutes(125)
            ->addSeconds((int) round(120 * 60 * max(0, $tReg->diffInSeconds($t, false)) / $rentang));

        $hasil   = [];
        $iFoto   = 0;
        foreach (array_values($nomor) as $i => $ns) {
            $ns   = (string) $ns;
            $sub  = fn ($s) => $s === null ? null : $ns . substr($s, strlen($tpl));
            $seq  = 0;
            $petaFaktur = [];

            // ---- Baris uji per skenario ------------------------------
            $baris = $uji->filter(fn ($r) => ($pilih[$ja[$r->Id_Jenis_Analisa]->Kode_Aktivitas_Lab ?? ''] ?? 'tidak') !== 'tidak')
                ->map(function ($r) use ($ja, $kode, $kriteria, $pilih, $ns, $sub, $pengguna, $geser, &$seq, &$petaFaktur) {
                    $j  = $ja[$r->Id_Jenis_Analisa];
                    $sk = $pilih[$j->Kode_Aktivitas_Lab] === 'angka' ? 'layak' : $pilih[$j->Kode_Aktivitas_Lab];
                    $b  = $this->dummy->terapkanSkenario((array) $r, $kode[$r->Id_Jenis_Analisa] ?? '', $sk, $kriteria);
                    $petaFaktur[$r->No_Faktur] ??= 'FUS' . date('my') . '-' . substr($ns, -4) . '-' . str_pad(++$seq, 2, '0', STR_PAD_LEFT);
                    $t = $geser($this->waktu($r->Tanggal, $r->Jam));

                    return (object) array_merge($b, [
                        'No_Faktur'          => $petaFaktur[$r->No_Faktur],
                        'No_Po_Sampel'       => $ns,
                        'No_Fak_Sub_Po'      => $sub($r->No_Fak_Sub_Po),
                        'Tanggal'            => $t->toDateString(),
                        'Jam'                => $t->format('H:i:s'),
                        'Tahapan_Ke'         => 1,
                        'Jenis_Analisa'      => $j->Jenis_Analisa,
                        'Kode_Analisa'       => trim((string) $j->Kode_Analisa),
                        'Kode_Aktivitas_Lab' => $j->Kode_Aktivitas_Lab,
                        'Nama_User_Input'    => trim((string) ($pengguna[$r->Id_User] ?? '')) ?: $r->Id_User,
                        '_Faktur_Asal'       => $r->No_Faktur,
                    ]);
                })->values();

            // ---- Per klasifikasi: tabel yang sama dengan Verifikasi ----
            $klasifikasi = [];
            foreach ($baris->groupBy('Kode_Aktivitas_Lab') as $k => $rows) {
                // Rincian disusun dari faktur TEMPLATE, karena sebagian isinya
                // dibaca per faktur — mis. nama parameter palatabilitas yang
                // belum terdaftar di binding (TINGKAT KONSUMSI) diambil dari
                // detail uji. Faktur contoh belum ada di database, jadi baru
                // dipasang sesudahnya.
                $asal = $rows->map(fn ($x) => (object) array_merge((array) $x, [
                    'No_Faktur' => $x->_Faktur_Asal,
                ]));
                $r = $this->rincian->rincian($asal, $po2, [], $pembanding, $this->rincian->petaValidasi([]));
                $j = $ja[$rows->first()->Id_Jenis_Analisa];

                // Foto Look View sesuai pilihan (berkas berbeda-beda).
                $jumlahFoto = [self::WARNA => (int) ($opsi['foto_warna'] ?? 0), self::TEKSTUR => (int) ($opsi['foto_tekstur'] ?? 0)];
                $analisa = $r['analisa']->map(function ($a) use ($jumlahFoto, $foto, &$iFoto, $petaFaktur) {
                    $a['No_Faktur'] = $petaFaktur[$a['No_Faktur']] ?? $a['No_Faktur'];
                    $n = $jumlahFoto[trim((string) $a['Kode_Analisa'])] ?? 0;
                    $a['Foto'] = [];
                    for ($x = 1; $x <= min($n, 5) && $foto->isNotEmpty(); $x++) {
                        $f = $foto[$iFoto++ % $foto->count()];
                        $a['Foto'][] = ['key' => $f->Berkas_Key, 'keterangan' => "Foto {$x} dari {$n}"];
                    }
                    return $a + ['Multi' => null];
                })->values()->all();

                // Parameter perhitungan: dibaca dari baris template, lalu
                // dinomori ulang mengikuti contoh.
                $param = collect($this->rincian->parameterPerhitungan($asal))->map(function ($p) use ($petaFaktur, $sub) {
                    $p['baris'] = array_map(fn ($b) => array_merge($b, [
                        'No_Faktur'     => $petaFaktur[$b['No_Faktur']] ?? $b['No_Faktur'],
                        'No_Sampel_Uji' => $sub($b['No_Sampel_Uji']) ?? $b['No_Sampel_Uji'],
                    ]), $p['baris']);
                    return $p;
                })->all();

                $klasifikasi[] = [
                    'kode'               => $k,
                    'nama'               => $j->Nama_Aktivitas ?? $k,
                    'urutan'             => (int) ($j->Urutan ?? 99),
                    'skenario'           => $pilih[$k],
                    'Butuh_Pembanding'   => $r['Butuh_Pembanding'],
                    'Pembanding'         => $r['Pembanding'],
                    'Matriks_Pembanding' => $r['Matriks_Pembanding'],
                    'Multi'              => $rows->contains('Flag_Multi_QrCode', 'Y'),
                    'analisa'            => $analisa,
                    'parameter'          => $param,
                    'petugas_verifikasi' => $this->rincian->petugasVerifikasi($k, $rows->pluck('Id_Jenis_Analisa')->all()),
                ];
            }
            usort($klasifikasi, fn ($a, $b) => $a['urutan'] <=> $b['urutan']);

            $reg = $geser($tReg);
            $hasil[] = [
                'No_Sampel'    => $ns,
                'Judul'        => $this->judul($pilih),
                'Opsi'         => $pilih + ['foto_warna' => (int) ($opsi['foto_warna'] ?? 0),
                                            'foto_tekstur' => (int) ($opsi['foto_tekstur'] ?? 0)],
                'No_Po'        => $po->No_Po,
                'No_Split_Po'  => $po->No_Split_Po,
                'No_Batch'     => $po->No_Batch,
                'Kode_Barang'  => trim((string) $po->Kode_Barang),
                'Nama_Barang'  => $this->identitas->namaBarang($po->Kode_Barang)
                    ?? DB::table('N_EMI_View_Barang')->where('Kode_Barang', $po->Kode_Barang)->value('Nama'),
                'Kode_Formula' => $this->identitas->formula($po->No_Po)
                    ?? DB::table('EMI_Order_Produksi')->where('No_Faktur', $po->No_Po)->value('Kode_Formula'),
                'Id_Mesin'     => (int) $po->Id_Mesin,
                'Nama_Mesin'   => trim((string) $mesin) ?: null,
                'Registrasi'   => [
                    'id'    => $po->Id_User,
                    'nama'  => trim((string) ($pengguna[$po->Id_User] ?? '')) ?: $po->Id_User,
                    'waktu' => $reg->format('Y-m-d H:i:s'),
                ],
                'klasifikasi'  => $klasifikasi,
            ];
        }

        return $hasil;
    }

    private function skenarioSah(string $s): string
    {
        return $s === 'tidak' || isset(self::SKENARIO[$s]) ? $s : 'layak';
    }

    private function judul(array $pilih): string
    {
        $nama = ['LCKV' => 'Look View', 'ANL' => 'Analisa Lab', 'PLT' => 'Palatabilitas'];

        return collect($pilih)
            ->reject(fn ($s) => $s === 'tidak')
            ->map(fn ($s, $k) => $nama[$k] . ': ' . ($s === 'angka' ? 'angka' : mb_strtolower(self::SKENARIO[$s])))
            ->implode(' · ');
    }

    private function waktu($tanggal, $jam): Carbon
    {
        return Carbon::parse(substr((string) $tanggal, 0, 10) . ' ' . ($jam ?: '00:00:00'));
    }
}
