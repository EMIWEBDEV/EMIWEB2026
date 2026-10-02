<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * CONTOH DATA SANDBOX — bahan mencoba satu siklus:
 *
 *   Validasi -> Verifikasi -> Finalisasi
 *
 * Menyalin sampel template (config sandbox.sampel_template) menjadi sampel
 * trial produksi BARU yang hasil ujinya sudah dikirim dan menunggu
 * validasi — keadaan yang sama dengan sampel sungguhan setelah analis
 * menekan "kirim". Tiap salinan diberi SKENARIO penilaian:
 *
 *   layak        semua analisa di dalam standar / kriteria layak;
 *                WARNA (Look View) berfoto 2
 *   tidak_layak  ASH & PROTEIN di luar batas, WARNA "Warna Tidak Merata",
 *                AROMA "Tidak Berbau"; WARNA berfoto 5
 *   tanpa_master Analisa Lab tanpa batas min–max, Look View dengan hasil
 *                yang tidak terdaftar di kriteria — keduanya "belum dapat
 *                dinilai"
 *   campuran     ketiganya dalam satu sampel; WARNA berfoto 2, TEKSTUR 5
 *
 * Palatabilitas di semua skenario sama: dinilai komparatif, angka saja.
 *
 * Batas min–max disimpan pada baris uji — seperti yang dicatat saat
 * pengujian — sehingga master rentang di database tidak diubah.
 *
 * Yang disalin: PO sampel & sub QR, baris uji putaran terakhir (bukan yang
 * ditolak) beserta parameter perhitungannya, foto (kunci berkas yang sudah
 * ada — berkasnya tidak digandakan), sesi & pembanding palatabilitas, dan
 * jejak kirim hasil. Jejak validasi / verifikasi / finalisasi dimulai nol.
 *
 * PENGAMAN: hanya berjalan di database config sandbox.database_dummy (demo).
 */
class SampelDummyService
{
    public const PENANDA = 'Sampel dummy sandbox';

    /** Kode analisa template (FS0926-0001). */
    private const ASH = 'AA', PROTEIN = 'PRA', SALT = 'ST', MIKRO_EC = 'MEC', MIKRO_YM = 'MYM', MIKRO_AC = 'MAC';
    private const TEKSTUR = 'P-QA-LV-TEKSTUR-POUCH', WARNA = 'P-QA-LV-WARNA', AROMA = 'P-QA-LV-AROMA';

    /** Batas min–max yang dicatat saat pengujian, per skenario. */
    private const BATAS_LAYAK = [
        self::ASH => [0, 16], self::PROTEIN => [3, 6], self::SALT => [1.5, 4],
        self::MIKRO_EC => [0, 100], self::MIKRO_YM => [0, 100], self::MIKRO_AC => [0, 100],
    ];

    /** Nomor uji terakhir yang dipakai dalam satu pembuatan. */
    private ?int $nomorUji = null;

    /** @return array<string, array{judul:string, keterangan:string}> */
    public static function skenario(): array
    {
        return [
            'layak'        => ['judul' => 'Semua layak',
                               'keterangan' => 'Seluruh hasil di dalam standar; Look View 2 foto'],
            'tidak_layak'  => ['judul' => 'Ada yang tidak layak',
                               'keterangan' => 'ASH & PROTEIN di luar batas, 2 Look View tidak layak; 5 foto'],
            'tanpa_master' => ['judul' => 'Belum ada di master',
                               'keterangan' => 'Tanpa batas min–max & hasil Look View tidak terdaftar'],
            'campuran'     => ['judul' => 'Campuran',
                               'keterangan' => 'Layak, tidak layak, dan belum ada di master sekaligus'],
        ];
    }

    /** @return array{tersedia: bool, template: ?string, alasan: ?string} */
    public function status(): array
    {
        $template = (string) config('sandbox.sampel_template');

        if (DB::connection()->getDatabaseName() !== config('sandbox.database_dummy')) {
            return ['tersedia' => false, 'template' => null,
                    'alasan' => 'Contoh data hanya dapat dibuat di database demo.'];
        }

        if (!DB::table('N_EMI_LAB_PO_Sampel')->where('No_Sampel', $template)->exists()) {
            return ['tersedia' => false, 'template' => null,
                    'alasan' => "Sampel template {$template} tidak ditemukan."];
        }

        return ['tersedia' => true, 'template' => $template, 'alasan' => null];
    }

    /** Keempat skenario sekaligus, berurutan. */
    public function buatSemua(): array
    {
        return array_map(fn ($s) => $this->buat($s), array_keys(self::skenario()));
    }

    /**
     * Buat satu sampel contoh siap validasi.
     *
     * @return array{No_Sampel:string, skenario:string, judul:string, template:string, analisa:array}
     */
    public function buat(string $skenario): array
    {
        $daftar = self::skenario();
        if (!isset($daftar[$skenario])) {
            throw new RuntimeException("Skenario \"{$skenario}\" tidak dikenal.");
        }

        $status = $this->status();
        if (!$status['tersedia']) {
            throw new RuntimeException($status['alasan']);
        }

        $tpl = $status['template'];
        $this->nomorUji = null;

        return DB::transaction(function () use ($tpl, $skenario, $daftar) {
            $po  = DB::table('N_EMI_LAB_PO_Sampel')->where('No_Sampel', $tpl)->first();
            $uji = $this->barisBerlaku($tpl);

            if ($uji->isEmpty()) {
                throw new RuntimeException("Sampel template {$tpl} tidak memiliki hasil uji.");
            }

            $kode = DB::table('N_EMI_LAB_Jenis_Analisa')
                ->whereIn('id', $uji->pluck('Id_Jenis_Analisa')->unique()->all())
                ->pluck('Kode_Analisa', 'id')
                ->map(fn ($k) => trim((string) $k));

            $sekarang = Carbon::parse(DB::selectOne(
                "SELECT CONVERT(varchar(19), dbo.Get_Date_Time(), 120) AS t")->t);

            // Geser utuh: hasil terakhir template = 3 menit lalu, urutan
            // kejadian tetap sama.
            $terakhir = $uji->map(fn ($r) => $this->waktu($r->Tanggal, $r->Jam))->max();
            $geser    = (int) $terakhir->diffInSeconds($sekarang->copy()->subMinutes(3), false);
            $baru     = fn ($tanggal, $jam) => $this->waktu($tanggal, $jam)->addSeconds($geser);

            $noSampel = $this->nomorSampelBaru();
            $gantiSub = fn ($sub) => $sub === null ? null : $noSampel . substr($sub, strlen($tpl));

            // 1. PO sampel — pendaftarnya tetap pendaftar template.
            $reg   = $baru($po->Tanggal, $po->Jam);
            $baris = (array) $po;
            unset($baris['id']);
            DB::table('N_EMI_LAB_PO_Sampel')->insert(array_merge($baris, [
                'No_Sampel'    => $noSampel,
                'Tanggal'      => $reg->toDateString(),
                'Jam'          => $reg->format('H:i:s'),
                'Keterangan'   => self::PENANDA . ' — ' . $daftar[$skenario]['judul'] . ' (salinan ' . $tpl . ')',
                'Status'       => null,
                'Flag_Selesai' => null,
                'Flag_Baca'    => null,
            ]));

            // 2. Sub QR (multi QR)
            DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->where('No_Po_Sampel', $tpl)->get()
                ->each(function ($m) use ($noSampel, $gantiSub, $reg) {
                    $b = (array) $m;
                    unset($b['Id_Po_Sampel_Multi']);
                    DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->insert(array_merge($b, [
                        'No_Po_Multi'  => $gantiSub($m->No_Po_Multi),
                        'No_Po_Sampel' => $noSampel,
                        'Tanggal'      => $reg->toDateString(),
                        'Jam'          => $reg->format('H:i:s'),
                        'Status'       => null,
                        'Flag_Selesai' => null,
                    ]));
                });

            // 3. Sesi & pembanding palatabilitas
            [$petaSesi, $petaPembanding] = $this->salinPalatabilitas(
                $uji->pluck('Id_Session')->filter()->unique()->all(), $noSampel, $baru);

            // 4. Baris uji — urutan dipertahankan (baris palatabilitas
            //    dipasangkan ke parameternya menurut urutan baris).
            $petaFaktur = [];
            foreach ($uji->pluck('No_Faktur')->unique() as $lama) {
                $petaFaktur[$lama] = $this->nomorUjiBaru();
            }
            $kriteria = $this->kriteriaLookView($kode->all());

            $sisip = $uji->map(function ($r) use ($noSampel, $gantiSub, $baru, $petaFaktur, $petaSesi, $petaPembanding,
                                                 $kode, $skenario, $kriteria) {
                $t = $baru($r->Tanggal, $r->Jam);

                $b = array_merge((array) $r, [
                    'No_Faktur'               => $petaFaktur[$r->No_Faktur],
                    'No_Po_Sampel'            => $noSampel,
                    'No_Fak_Sub_Po'           => $gantiSub($r->No_Fak_Sub_Po),
                    'Tanggal'                 => $t->toDateString(),
                    'Jam'                     => $t->format('H:i:s'),
                    'Status'                  => null,
                    'Flag_Selesai'            => null,
                    'Flag_Final'              => null,
                    'Flag_Resampling'         => null,
                    'Status_Keputusan_Sampel' => 'menunggu',
                    'Tahapan_Ke'              => 1,
                    'Id_Session'              => $r->Id_Session ? ($petaSesi[$r->Id_Session] ?? null) : null,
                    'Id_Pembanding'           => $r->Id_Pembanding ? ($petaPembanding[$r->Id_Pembanding] ?? null) : null,
                ]);

                return $this->terapkanSkenario($b, $kode[$r->Id_Jenis_Analisa] ?? '', $skenario, $kriteria);
            })->values()->all();

            foreach (array_chunk($sisip, 50) as $potong) {
                DB::table('N_EMI_LAB_Uji_Sampel')->insert($potong);
            }

            // 5. Parameter perhitungan
            DB::table('N_EMI_LAB_Uji_Sampel_Detail')
                ->whereIn('No_Faktur_Uji_Sample', array_keys($petaFaktur))
                ->orderBy('Id_Uji_Sample_Detail')
                ->get()
                ->each(function ($d) use ($petaFaktur, $baru) {
                    $b = (array) $d;
                    unset($b['Id_Uji_Sample_Detail']);
                    $t = $d->Tanggal ? $baru($d->Tanggal, $d->Jam) : null;
                    DB::table('N_EMI_LAB_Uji_Sampel_Detail')->insert(array_merge($b, [
                        'No_Faktur_Uji_Sample' => $petaFaktur[$d->No_Faktur_Uji_Sample],
                        'Tanggal'              => $t?->toDateString(),
                        'Jam'                  => $t?->format('H:i:s') ?? $d->Jam,
                    ]));
                });

            // 6. Foto Look View sesuai skenario.
            $this->salinFoto($uji, $kode->all(), $skenario, $petaFaktur, $noSampel, $sekarang);

            // 7. Jejak kirim hasil (siapa yang menekan "kirim").
            $analisa = $uji->pluck('Id_Jenis_Analisa')->unique()->all();
            DB::table('N_EMI_LAB_Activity_Uji_Sampel')
                ->where('No_Po_Sampel', $tpl)
                ->whereIn('Id_Jenis_Analisa', $analisa)
                ->orderBy('Id_Log_Activity')
                ->get()
                ->each(function ($a) use ($noSampel, $gantiSub, $baru) {
                    $b = (array) $a;
                    unset($b['Id_Log_Activity']);
                    $t = $baru($a->Tanggal, $a->Jam);
                    DB::table('N_EMI_LAB_Activity_Uji_Sampel')->insert(array_merge($b, [
                        'No_Po_Sampel'  => $noSampel,
                        'No_Fak_Sub_Po' => $gantiSub($a->No_Fak_Sub_Po),
                        'Keterangan'    => self::PENANDA,
                        'Tanggal'       => $t->toDateString(),
                        'Jam'           => $t->format('H:i:s'),
                    ]));
                });

            return [
                'No_Sampel' => $noSampel,
                'skenario'  => $skenario,
                'judul'     => $daftar[$skenario]['judul'],
                'template'  => $tpl,
                'analisa'   => DB::table('N_EMI_LAB_Jenis_Analisa')->whereIn('id', $analisa)
                    ->pluck('Kode_Aktivitas_Lab')->countBy()->all(),
            ];
        });
    }

    /**
     * Hapus seluruh sampel contoh beserta jejaknya — hanya yang bertanda
     * PENANDA, hanya di database demo. Berkas foto tidak disentuh: kuncinya
     * milik sampel lain.
     *
     * @return string[] nomor sampel yang dihapus
     */
    public function hapusSemua(): array
    {
        if (DB::connection()->getDatabaseName() !== config('sandbox.database_dummy')) {
            throw new RuntimeException('Contoh data hanya dapat dihapus di database demo.');
        }

        $sampel = DB::table('N_EMI_LAB_PO_Sampel')
            ->where('Keterangan', 'like', self::PENANDA . '%')
            ->pluck('No_Sampel')->all();

        if (empty($sampel)) {
            return [];
        }

        DB::transaction(function () use ($sampel) {
            $faktur = DB::table('N_EMI_LAB_Uji_Sampel')->whereIn('No_Po_Sampel', $sampel)
                ->pluck('No_Faktur')->unique()->values()->all();
            $sesi = DB::table('N_EMI_LAB_Palatabilitas_Session')->whereIn('No_Po_Sampel', $sampel)
                ->pluck('Id_Session')->all();
            $log = DB::table('N_EMI_LAB_Log_Aksi')->whereIn('No_Sampel', $sampel)->pluck('Id_Log_Aksi')->all();

            $ada = fn ($t) => Schema::hasTable($t);

            if ($ada('N_EMI_LAB_Verifikasi_Header')) {
                $ver = DB::table('N_EMI_LAB_Verifikasi_Header')->whereIn('No_Sampel', $sampel)
                    ->pluck('Id_Verifikasi')->all();
                foreach (['N_EMI_LAB_Verifikasi_Riwayat', 'N_EMI_LAB_Verifikasi_Detail'] as $t) {
                    if ($ada($t) && $ver) DB::table($t)->whereIn('Id_Verifikasi', $ver)->delete();
                }
                DB::table('N_EMI_LAB_Verifikasi_Header')->whereIn('No_Sampel', $sampel)->delete();
            }
            if ($log) DB::table('N_EMI_LAB_Log_Aksi_Detail')->whereIn('Id_Log_Aksi', $log)->delete();
            DB::table('N_EMI_LAB_Log_Aksi')->whereIn('No_Sampel', $sampel)->delete();
            foreach (['N_EMI_LAB_Hasil_Uji_Approval_Aktivitas', 'N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final'] as $t) {
                if ($ada($t)) DB::table($t)->whereIn('No_Sampel', $sampel)->delete();
            }
            DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')->whereIn('No_Po_Sampel', $sampel)->delete();
            DB::table('N_EMI_LAB_Activity_Uji_Sampel')->whereIn('No_Po_Sampel', $sampel)->delete();
            DB::table('N_EMI_LAB_Berkas_Uji_Lab')->whereIn('No_Sampel', $sampel)->delete();
            foreach (array_chunk($faktur, 1000) as $b) {
                DB::table('N_EMI_LAB_Uji_Sampel_Detail')->whereIn('No_Faktur_Uji_Sample', $b)->delete();
            }
            DB::table('N_EMI_LAB_Uji_Sampel')->whereIn('No_Po_Sampel', $sampel)->delete();
            if ($sesi) {
                DB::table('N_EMI_LAB_Palatabilitas_Pembanding')->whereIn('Id_Session', $sesi)->delete();
                DB::table('N_EMI_LAB_Palatabilitas_Session')->whereIn('Id_Session', $sesi)->delete();
            }
            DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->whereIn('No_Po_Sampel', $sampel)->delete();
            DB::table('N_EMI_LAB_PO_Sampel')->whereIn('No_Sampel', $sampel)->delete();
        });

        return $sampel;
    }

    // ------------------------------------------------------------------
    // Skenario
    // ------------------------------------------------------------------

    /**
     * Terapkan skenario pada satu baris uji.
     *
     * Analisa perhitungan: batas min–max dicatat di baris (atau dikosongkan
     * untuk "belum ada di master"). Look View: hasil diganti dengan kriteria
     * layak / tidak layak dari master, atau hasil yang tidak terdaftar.
     */
    public function terapkanSkenario(array $b, string $kode, string $skenario, array $kriteria): array
    {
        if (isset(self::BATAS_LAYAK[$kode])) {
            $batas = match ($skenario) {
                'layak'        => self::BATAS_LAYAK[$kode],
                'tidak_layak'  => [self::ASH => [0, 10], self::PROTEIN => [4, 6]][$kode] ?? self::BATAS_LAYAK[$kode],
                'tanpa_master' => null,
                'campuran'     => [self::ASH => [0, 10], self::PROTEIN => [3, 6], self::SALT => [1.5, 4]][$kode] ?? null,
            };

            // Sebagai teks: INSERT banyak baris di SQL Server menyamakan tipe
            // tiap kolom antarbaris — angka PHP bercampur teks '-965…0' dari
            // baris lain membuat kolomnya dipaksa int dan gagal.
            $b['Range_Awal']  = isset($batas) ? (string) $batas[0] : null;
            $b['Range_Akhir'] = isset($batas) ? (string) $batas[1] : null;
            $b['Flag_Layak']  = $batas === null ? 'Y'
                : (((float) $b['Hasil'] >= $batas[0] && (float) $b['Hasil'] <= $batas[1]) ? 'Y' : 'T');

            return $b;
        }

        if (!isset($kriteria[$kode])) {
            return $b;
        }

        // Hasil Look View per skenario: 'L' = kriteria layak (seperti
        // template), 'T' = kriteria tidak layak, 'X' = tidak terdaftar.
        $pilih = [
            'layak'        => [],
            'tidak_layak'  => [self::WARNA => 'T', self::AROMA => 'T'],
            'tanpa_master' => [self::WARNA => 'X', self::TEKSTUR => 'X', self::AROMA => 'X'],
            'campuran'     => [self::TEKSTUR => 'T', self::AROMA => 'X'],
        ][$skenario][$kode] ?? null;

        if ($pilih === 'T' && ($k = $kriteria[$kode]['T'] ?? null)) {
            $b['Hasil'] = $k->Nilai_Kriteria;
            $b['Nilai_Hasil_String'] = $k->Keterangan_Kriteria;
            $b['Flag_Layak'] = 'T';
        } elseif ($pilih === 'X') {
            // Nilai yang tidak ada di daftar kriteria mana pun: kelayakannya
            // tidak dapat ditetapkan.
            $b['Hasil'] = (string) (-100000000 - crc32($kode) % 1000);
            $b['Nilai_Hasil_String'] = [
                self::WARNA => 'Coklat Pudar', self::TEKSTUR => 'Agak Berair', self::AROMA => 'Bau Asam',
            ][$kode] ?? 'Tidak terdaftar';
        }

        return $b;
    }

    /** Kriteria Look View tidak layak pertama per analisa, dari master. */
    public function kriteriaLookView(array $kode): array
    {
        $peta = [];

        foreach ([self::WARNA, self::TEKSTUR, self::AROMA] as $k) {
            $idJa = array_search($k, $kode, true);
            if ($idJa === false) continue;

            $tidak = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $idJa)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Layak', 'T')
                ->orderBy('Nilai_Kriteria')
                ->first();

            $peta[$k] = ['T' => $tidak];
        }

        return $peta;
    }

    /**
     * Foto Look View: jumlah per analisa mengikuti skenario. Foto diambil
     * dari berkas yang sudah ada di database (berkas berbeda-beda), supaya
     * galeri memperlihatkan gambar yang tidak sama.
     */
    private function salinFoto($uji, array $kode, string $skenario, array $petaFaktur, string $noSampel, Carbon $sekarang): void
    {
        $jumlah = [
            'layak'        => [self::WARNA => 2],
            'tidak_layak'  => [self::WARNA => 5],
            'tanpa_master' => [self::WARNA => 1],
            'campuran'     => [self::WARNA => 2, self::TEKSTUR => 5],
        ][$skenario];

        $kolom = array_flip(Schema::getColumnListing('N_EMI_LAB_Berkas_Uji_Lab'));
        $stok = $this->stokFoto();
        $i = 0;

        foreach ($jumlah as $k => $n) {
            $idJa = array_search($k, $kode, true);
            $r = $idJa === false ? null : $uji->firstWhere('Id_Jenis_Analisa', $idJa);
            if (!$r || $stok->isEmpty()) continue;

            $faktur = $petaFaktur[$r->No_Faktur];
            for ($x = 1; $x <= $n; $x++) {
                $f = $stok[$i++ % $stok->count()];
                $b = [
                    'No_Faktur'  => $faktur,
                    'No_Sampel'  => $noSampel,
                    'Berkas_Key' => $f->Berkas_Key,
                    'File_Path'  => $f->File_Path,
                    'Keterangan' => "Foto {$x} dari {$n}",
                ];
                if (isset($kolom['Id_User'])) $b['Id_User'] = $r->Id_User;
                if (isset($kolom['Tahapan_Ke'])) $b['Tahapan_Ke'] = 1;
                if (isset($kolom['Dibuat_Pada'])) $b['Dibuat_Pada'] = $sekarang->toDateTimeString();
                DB::table('N_EMI_LAB_Berkas_Uji_Lab')->insert($b);
            }

            DB::table('N_EMI_LAB_Uji_Sampel')->where('No_Faktur', $faktur)->update(['Flag_Foto' => 'Y']);
        }
    }

    /**
     * Foto berbeda-beda yang sudah ada di database, terbaru lebih dulu —
     * dipakai sebagai foto contoh (kunci berkasnya saja; berkasnya tidak
     * digandakan).
     */
    public function stokFoto(int $batas = 20)
    {
        $kolom = array_flip(Schema::getColumnListing('N_EMI_LAB_Berkas_Uji_Lab'));

        return DB::table('N_EMI_LAB_Berkas_Uji_Lab')
            ->when(isset($kolom['Flag_Nonaktif']), fn ($q) => $q->whereNull('Flag_Nonaktif'))
            ->select('Berkas_Key', 'File_Path', DB::raw('MIN(Id_Berkas_Lab) as urut'))
            ->groupBy('Berkas_Key', 'File_Path')
            ->orderByDesc('urut')
            ->limit($batas)
            ->get()
            ->values();
    }

    // ------------------------------------------------------------------
    // Salinan
    // ------------------------------------------------------------------

    /**
     * Baris uji template yang berlaku: putaran terakhir tiap analisa, tanpa
     * baris yang ditolak lewat resampling. Urutan baris dipertahankan.
     */
    public function barisBerlaku(string $tpl)
    {
        $semua = DB::table('N_EMI_LAB_Uji_Sampel')
            ->where('No_Po_Sampel', $tpl)
            ->whereNull('Status')
            ->where(fn ($q) => $q->whereNull('Status_Keputusan_Sampel')
                ->orWhere('Status_Keputusan_Sampel', '<>', 'tolak'))
            ->get();

        $putaran = $semua->groupBy('Id_Jenis_Analisa')
            ->map(fn ($g) => (int) $g->max('Tahapan_Ke'));

        return $semua
            ->filter(fn ($r) => (int) $r->Tahapan_Ke === $putaran[$r->Id_Jenis_Analisa])
            ->values();
    }

    /**
     * Salin sesi palatabilitas dan produk pembandingnya.
     *
     * @return array{0: array<int,int>, 1: array<int,int>} peta Id lama -> baru
     */
    private function salinPalatabilitas(array $idSesi, string $noSampel, callable $baru): array
    {
        $petaSesi = [];
        $petaPembanding = [];

        foreach (DB::table('N_EMI_LAB_Palatabilitas_Session')->whereIn('Id_Session', $idSesi ?: [0])->get() as $s) {
            $b = (array) $s;
            unset($b['Id_Session']);
            $t = $baru($s->Tanggal_Buat, $s->Jam_Buat);
            $petaSesi[$s->Id_Session] = (int) DB::table('N_EMI_LAB_Palatabilitas_Session')->insertGetId(array_merge($b, [
                'No_Po_Sampel'         => $noSampel,
                'No_Faktur_Uji_Sampel' => null,
                'Tanggal_Buat'         => $t->toDateTimeString(),
                'Jam_Buat'             => $t->format('H:i:s'),
                // Sesi contoh belum difinalisasi: hasilnya menunggu validasi.
                'Tanggal_Final'        => null,
                'Jam_Final'            => null,
                'Id_User_Final'        => null,
            ]), 'Id_Session');

            DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                ->where('Id_Session', $s->Id_Session)
                ->orderBy('Id_Pembanding')
                ->get()
                ->each(function ($p) use (&$petaPembanding, $petaSesi, $s, $baru) {
                    $b = (array) $p;
                    unset($b['Id_Pembanding']);
                    $t = $p->Tanggal ? $baru($p->Tanggal, $p->Jam) : null;
                    $petaPembanding[$p->Id_Pembanding] = (int) DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                        ->insertGetId(array_merge($b, [
                            'Id_Session' => $petaSesi[$s->Id_Session],
                            'Tanggal'    => $t?->toDateTimeString(),
                            'Jam'        => $t?->format('H:i:s') ?? $p->Jam,
                        ]), 'Id_Pembanding');
                });
        }

        return [$petaSesi, $petaPembanding];
    }

    /** Nomor sampel berikutnya — skema POSampleController (FSmmyy-nnnn). */
    private function nomorSampelBaru(): string
    {
        $prefix = 'FS' . date('m') . date('y');

        // UPDLOCK + HOLDLOCK: dua permintaan serentak tidak mendapat nomor sama.
        $akhir = DB::selectOne(
            "SELECT TOP 1 No_Sampel FROM N_EMI_LAB_PO_Sampel WITH (UPDLOCK, HOLDLOCK)
             WHERE No_Sampel LIKE ? ORDER BY id DESC", [$prefix . '-%'])?->No_Sampel;

        $nomor = $akhir ? (int) substr($akhir, strpos($akhir, '-') + 1) : 0;

        do {
            $calon = $prefix . '-' . str_pad(++$nomor, 4, '0', STR_PAD_LEFT);
        } while (DB::table('N_EMI_LAB_PO_Sampel')->where('No_Sampel', $calon)->exists());

        return $calon;
    }

    /** Nomor uji berikutnya — skema UjiSampelController (FUSmmyy-nnnn). */
    private function nomorUjiBaru(): string
    {
        $prefix = 'FUS' . date('m') . date('y');

        if ($this->nomorUji === null) {
            $this->nomorUji = (int) (DB::selectOne(
                "SELECT MAX(TRY_CAST(SUBSTRING(No_Faktur, ?, 10) AS INT)) AS n
                 FROM N_EMI_LAB_Uji_Sampel WITH (UPDLOCK, HOLDLOCK)
                 WHERE No_Faktur LIKE ?", [strlen($prefix) + 2, $prefix . '-%'])->n ?? 0);
        }

        return $prefix . '-' . str_pad(++$this->nomorUji, 4, '0', STR_PAD_LEFT);
    }

    private function waktu($tanggal, $jam): Carbon
    {
        return Carbon::parse(substr((string) $tanggal, 0, 10) . ' ' . ($jam ?: '00:00:00'));
    }
}
