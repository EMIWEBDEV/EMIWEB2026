<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian hasil analisa — dipakai bersama layar Verifikasi dan Finalisasi.
 *
 * Kedua layar menampilkan tabel hasil yang sama: kriteria kelayakan beserta
 * dasar penilaiannya, resampling, siapa yang menginput, dan siapa yang
 * memvalidasi. Disusun di satu tempat supaya satu hasil tidak pernah dinilai
 * dengan cara berbeda di dua layar.
 *
 * PENGINPUT vs VALIDATOR
 * ----------------------
 * Id_User, Tanggal, dan Jam pada N_EMI_LAB_Uji_Sampel ditulis saat hasil
 * disimpan — itulah PENGINPUT. Validasi hanya mengubah Flag_Selesai, sehingga
 * validatornya tidak ada di baris uji dan harus dibaca dari jejak validasi
 * (lihat petaValidasi()).
 *
 * Read-only terhadap seluruh tabel.
 */
class RincianHasilAnalisaService
{
    /**
     * Penanda kelayakan yang TIDAK DAPAT DITETAPKAN.
     *
     * Layak / tidak layak hanya boleh dinyatakan bila ada kriteria kelayakan
     * di master: batas min-max untuk analisa perhitungan, atau daftar kriteria
     * untuk analisa non-perhitungan. Tanpa itu, sistem tidak punya dasar apa
     * pun untuk menilai — dan menampilkannya sebagai "Layak" berarti
     * meloloskan hasil yang belum pernah diuji terhadap kriteria. Karena itu
     * keadaan tersebut diberi nilai tersendiri, bukan 'Y' dan bukan 'T'.
     */
    public const LAYAK_TIDAK_TENTU = 'N';

    /**
     * Master kelayakan per permintaan. Satu analisa palatabilitas tersimpan
     * sebagai beberapa baris, dan tanpa ini masternya dibaca ulang per baris.
     *
     * @var array<string, mixed>
     */
    private array $cacheKriteria = [];

    /** @var array<string, object|null> */
    private array $cacheRentang = [];

    /**
     * Kunci satu baris uji, dipakai untuk memasangkan baris dengan hasil
     * evaluasinya.
     *
     * Ulangan pengukuran dalam satu kiriman berbagi analisa, sub-sampel, dan
     * jam — bahkan kadang nomor faktur. Karena itu nomor faktur dan nilai
     * hasil ikut menjadi kunci; tanpa itu seluruh ulangan dinilai memakai
     * evaluasi satu baris saja. Ulangan yang benar-benar kembar (faktur dan
     * nilai sama) memang menghasilkan evaluasi yang sama.
     */
    public function kunciBaris(object $r): string
    {
        return implode('_', [
            $r->Id_Jenis_Analisa, $r->No_Fak_Sub_Po, $r->Id_Pembanding, $r->Jam,
            $r->No_Faktur ?? '', $r->Hasil ?? '', $r->Nilai_Hasil_String ?? '',
        ]);
    }

    /**
     * Rincian satu (sampel x klasifikasi) siap tampil.
     *
     * Baris uji wajib memuat kolom pilihan daftar kerja verifikasi, termasuk
     * nama penginput sebagai Nama_User_Input.
     *
     * @param  Collection   $rows        baris N_EMI_LAB_Uji_Sampel satu klasifikasi
     * @param  object|null  $po          konteks sampel: Kode_Barang, Id_Mesin
     * @param  array        $resampling  petaResampling() milik sampel ini
     * @param  array        $pembanding  petaPembanding()
     * @param  array        $validasi    petaValidasi()
     */
    public function rincian(Collection $rows, ?object $po, array $resampling,
                            array $pembanding, array $validasi): array
    {
        $f = $rows->first();

        // Kelayakan dievaluasi ulang di sini agar pembaca melihat DASAR
        // penilaian, bukan sekadar flag tersimpan.
        $evaluasi = $rows->mapWithKeys(fn ($r) => [
            $this->kunciBaris($r) => $this->evaluasiKelayakan($r, $po),
        ]);

        // Palatabilitas dinilai dengan membandingkan sampel terhadap produk
        // pembanding, sehingga bentuk tabelnya berbeda: satu baris per jenis
        // analisa, tiap pembanding menjadi kolom tersendiri.
        $butuhPembanding = $f->Kode_Aktivitas_Lab === 'PLT';

        return [
            'Jumlah_Tidak_Layak'  => $evaluasi->where('layak', 'T')->count(),
            'Jumlah_Tanpa_Master' => $evaluasi->whereIn('dasar', ['TANPA_MASTER', 'KRITERIA_TIDAK_COCOK'])->count(),
            'Jumlah_Resampling'   => collect($resampling)
                ->only($rows->pluck('Id_Jenis_Analisa')->unique()->all())
                ->sum(fn ($x) => $x['jumlah'] ?? 0),
            'analisa'             => $rows->map(function ($r) use ($evaluasi, $pembanding, $resampling, $validasi, $f) {
                $ev = $evaluasi[$this->kunciBaris($r)];

                return [
                    'Id_Jenis_Analisa'   => $r->Id_Jenis_Analisa,
                    'Nama_Jenis_Analisa' => $r->Jenis_Analisa,
                    'Kode_Analisa'       => $r->Kode_Analisa,
                    // Dinamai "Sampel" di UI; sumbernya No_Fak_Sub_Po.
                    'No_Sampel_Uji'      => $r->No_Fak_Sub_Po ?: $f->No_Po_Sampel,
                    'No_Faktur'          => $r->No_Faktur,
                    'Tahapan_Ke'         => $r->Tahapan_Ke,
                    'Hasil'              => $r->Hasil,
                    'Nilai_Hasil_String' => $r->Nilai_Hasil_String,
                    'Flag_Perhitungan'   => $r->Flag_Perhitungan,
                    // Hasil evaluasi transparan
                    'Flag_Layak'         => $ev['layak'],
                    'Flag_Layak_Db'      => $r->Flag_Layak,
                    'Dasar_Kelayakan'    => $ev['dasar'],
                    'Ringkas_Kelayakan'  => $ev['ringkas'],
                    'Rincian_Kelayakan'  => $ev['rincian'],
                    'Range_Min'          => $ev['rincian']['min'] ?? null,
                    'Range_Max'          => $ev['rincian']['max'] ?? null,
                    'Jumlah_Resampling'  => $resampling[$r->Id_Jenis_Analisa]['jumlah'] ?? 0,
                    'Detail_Resampling'  => $resampling[$r->Id_Jenis_Analisa]['sampel'] ?? [],
                    'Id_Session'         => $r->Id_Session,
                    'Id_Pembanding'      => $r->Id_Pembanding,
                    'Nama_Pembanding'    => $r->Id_Pembanding
                        ? ($pembanding[$r->Id_Pembanding] ?? null) : null,
                    'Tanggal'            => $r->Tanggal,
                    'Jam'                => $r->Jam,
                    'Id_User'            => $r->Id_User,
                    'Input'              => $this->jejakInput($r),
                    // null bila validatornya tidak tercatat di mana pun.
                    'Validasi'           => $this->validatorBaris($r, $validasi),
                ];
            })->values(),

            // Hanya terisi untuk klasifikasi yang memakai pembanding. Kolom
            // disusun di sini, bukan di layar, supaya urutannya mengikuti
            // master dan tetap sama di seluruh tampilan.
            'Butuh_Pembanding'    => $butuhPembanding,
            'Pembanding'          => $butuhPembanding
                ? $this->kolomPembanding($rows, $pembanding) : [],
            'Matriks_Pembanding'  => $butuhPembanding
                ? $this->matriksPembanding($rows, $evaluasi, $validasi) : [],
        ];
    }

    // ------------------------------------------------------------------
    // Verifikator yang bertugas
    // ------------------------------------------------------------------

    /** @var array<string, array<int, array>>|null */
    private ?array $cacheKewenangan = null;

    /**
     * Kewenangan verifikasi yang aktif, per klasifikasi — dari
     * N_EMI_LAB_Verifikasi_Kewenangan. Satu akun bisa berwenang atas
     * seluruh klasifikasi (Id_Jenis_Analisa NULL) atau analisa tertentu saja.
     * Hanya akun yang masih aktif yang dihitung.
     *
     * @return array<string, array<int, array{id:string, nama:string, analisa:int[]|null}>>
     */
    public function petaKewenanganVerifikasi(): array
    {
        if ($this->cacheKewenangan !== null) {
            return $this->cacheKewenangan;
        }

        if (!Schema::hasTable('N_EMI_LAB_Verifikasi_Kewenangan')) {
            return $this->cacheKewenangan = [];
        }

        $peta = [];
        $rows = DB::table('N_EMI_LAB_Verifikasi_Kewenangan as k')
            ->join('N_EMI_LAB_Users as u', 'u.UserId', '=', 'k.Id_User')
            ->where('k.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Flag_Aktif')->orWhere('u.Flag_Aktif', 'Y'))
            ->select('k.Kode_Aktivitas_Lab', 'k.Id_User', 'k.Id_Jenis_Analisa', 'u.Nama')
            ->get();

        foreach ($rows->groupBy('Kode_Aktivitas_Lab') as $kode => $isi) {
            foreach ($isi->groupBy('Id_User') as $idUser => $milik) {
                $semua = $milik->contains(fn ($r) => $r->Id_Jenis_Analisa === null);
                $peta[$kode][] = [
                    'id'      => (string) $idUser,
                    'nama'    => trim((string) $milik->first()->Nama) ?: (string) $idUser,
                    'analisa' => $semua ? null : $milik->pluck('Id_Jenis_Analisa')->map(fn ($x) => (int) $x)->all(),
                ];
            }
        }

        return $this->cacheKewenangan = $peta;
    }

    /**
     * Siapa yang bertugas memverifikasi satu klasifikasi pada satu sampel:
     * akun yang berwenang atas seluruh klasifikasi, atau atas sebagian
     * analisa yang ada di sampel ini.
     *
     * @param  int[]  $idAnalisa  jenis analisa klasifikasi ini pada sampel
     * @return array<int, array{id:string, nama:string, sebagian:bool, jumlah:int}>
     */
    public function petugasVerifikasi(string $kode, array $idAnalisa): array
    {
        $idAnalisa = array_values(array_unique(array_map('intval', $idAnalisa)));
        $hasil = [];

        foreach ($this->petaKewenanganVerifikasi()[$kode] ?? [] as $w) {
            $cakup = $w['analisa'] === null ? $idAnalisa : array_values(array_intersect($idAnalisa, $w['analisa']));
            if (empty($cakup)) {
                continue;
            }
            $hasil[] = [
                'id'       => $w['id'],
                'nama'     => $w['nama'],
                'sebagian' => count($cakup) < count($idAnalisa),
                'jumlah'   => count($cakup),
            ];
        }

        usort($hasil, fn ($a, $b) => [$a['sebagian'], $a['nama']] <=> [$b['sebagian'], $b['nama']]);

        return $hasil;
    }

    // ------------------------------------------------------------------
    // Putaran yang ditolak
    // ------------------------------------------------------------------

    /**
     * Saring baris uji milik putaran yang sudah DITOLAK lewat resampling,
     * sehingga layar hanya menilai hasil putaran yang berlaku. Riwayat
     * putaran lama tetap terbaca di Sample Lifecycle.
     *
     * Sebuah baris dianggap putaran yang ditolak bila:
     *   - Status_Keputusan_Sampel-nya 'tolak', ATAU
     *   - ada permintaan resampling ke putaran yang lebih tinggi untuk
     *     analisa (dan pembanding) yang sama, yang diminta SESUDAH baris itu
     *     diinput. Syarat kedua menangkap data lama yang statusnya sempat
     *     tertimpa menjadi 'terima' oleh validasi putaran berikutnya.
     *
     * Jam yang tidak terbaca membuat baris TETAP ditampilkan (tidak ditebak).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function tanpaPutaranDitolak($query, string $a = 'u')
    {
        return $query
            ->where(fn ($q) => $q->whereNull("$a.Status_Keputusan_Sampel")
                ->orWhere("$a.Status_Keputusan_Sampel", '<>', 'tolak'))
            ->whereNotExists(function ($q) use ($a) {
                $q->select(DB::raw(1))
                    ->from('N_EMI_LAB_Uji_Sampel_Resampling_Log as rsl')
                    ->whereColumn('rsl.No_Po_Sampel', "$a.No_Po_Sampel")
                    ->whereColumn('rsl.Id_Jenis_Analisa', "$a.Id_Jenis_Analisa")
                    ->where(fn ($w) => $w->whereNull('rsl.Id_Pembanding')
                        ->orWhereColumn('rsl.Id_Pembanding', "$a.Id_Pembanding"))
                    ->whereRaw("rsl.Tahapan_Ke > ISNULL($a.Tahapan_Ke, 1)")
                    ->whereRaw("CAST(CONVERT(date, rsl.Tanggal) AS datetime)"
                        . " + CAST(ISNULL(TRY_CONVERT(time(0), rsl.Jam), CAST(rsl.Tanggal AS time(0))) AS datetime)"
                        . " >= CAST(CONVERT(date, $a.Tanggal) AS datetime)"
                        . " + CAST(TRY_CONVERT(time(0), $a.Jam) AS datetime)");
            });
    }

    // ------------------------------------------------------------------
    // Penginput & validator
    // ------------------------------------------------------------------

    /** Penginput hasil: pemilik baris uji. */
    public function jejakInput(object $r): array
    {
        return [
            'id'      => $r->Id_User,
            'nama'    => ($r->Nama_User_Input ?? null) ?: $r->Id_User,
            'tanggal' => $r->Tanggal,
            'jam'     => $r->Jam,
        ];
    }

    /**
     * Siapa yang memvalidasi tiap analisa pada sampel-sampel ini.
     *
     * Tiga sumber, dibaca berurutan:
     *
     *   1. N_EMI_LAB_Hasil_Uji_Approval_Aktivitas, Jenis_Approval 'VALIDASI'
     *      yang ditulis saat validasi berlangsung (Sumber_Pencatatan
     *      'VALIDASI'). Mencatat sub-sampel, sehingga dicocokkan per baris.
     *   2. N_EMI_LAB_Log_Aksi_Detail di bawah Log_Aksi 'VALIDASI_*' / 'SETUJU' —
     *      jalur validasi yang belum menulis ke tabel approval.
     *   3. N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final ber-Sumber 'VALIDASI' atau
     *      'PRA_MIGRASI' — ditulis kode validasi saat validasi berlangsung,
     *      termasuk kode lama sebelum migrasi (di production, Id_User-nya
     *      terbukti validator, bukan penginput).
     *
     * Baris ber-Sumber 'BACKFILL' TIDAK dipakai: versi awal skrip backfill
     * menyalin Id_User baris uji, yaitu penginput. Memakainya akan menampilkan
     * penginput sebagai validator — kesalahan yang justru hendak diperbaiki.
     *
     * Bila satu analisa tercatat lebih dari sekali, yang dipakai tahapan
     * tertinggi, lalu waktu paling awal: saat itulah Flag_Selesai berubah,
     * dan baris sesudahnya hanya pengulangan penekanan tombol.
     *
     * @return array{sub: array, log: array, analisa: array}
     */
    public function petaValidasi(array $noSampel): array
    {
        $peta = ['sub' => [], 'log' => [], 'analisa' => [], 'detail_sub' => [], 'detail' => []];
        $noSampel = array_values(array_unique(array_filter($noSampel)));

        if (empty($noSampel)) {
            return $peta;
        }

        $approval = collect();
        $log      = collect();
        $detail   = collect();

        // Tabel/kolom migrasi belum tentu ada (production sebelum migrasi).
        $adaApproval = Schema::hasTable('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas');
        $adaSumber   = Schema::hasColumn('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final', 'Sumber_Pencatatan');

        // Dipecah agar tidak melampaui batas 2100 parameter SQL Server.
        foreach (array_chunk($noSampel, 500) as $bagian) {
            if ($adaApproval) {
                $approval = $approval->concat(
                    DB::table('N_EMI_LAB_Hasil_Uji_Approval_Aktivitas')
                        ->whereIn('No_Sampel', $bagian)
                        ->where('Jenis_Approval', 'VALIDASI')
                        ->where('Sumber_Pencatatan', 'VALIDASI')
                        ->select('No_Sampel', 'No_Sub_Sampel', 'Id_Jenis_Analisa', 'Tahapan_Ke',
                            'Id_User', 'Nama_User', 'Tanggal', 'Jam')
                        ->get()
                );
            }

            $detail = $detail->concat(
                DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final')
                    ->whereIn('No_Sampel', $bagian)
                    ->whereNotNull('Id_User')
                    ->when($adaSumber, fn ($q) => $q->where(fn ($w) => $w
                        ->whereNull('Sumber_Pencatatan')
                        ->orWhereIn('Sumber_Pencatatan', ['VALIDASI', 'PRA_MIGRASI'])))
                    ->select('No_Sampel', 'No_Sub_Sampel', 'Id_Jenis_Analisa', 'Tahapan_Ke',
                        'Id_User', 'Tanggal', 'Jam')
                    ->get()
            );

            $log = $log->concat(
                DB::table('N_EMI_LAB_Log_Aksi as l')
                    ->join('N_EMI_LAB_Log_Aksi_Detail as d', 'd.Id_Log_Aksi', '=', 'l.Id_Log_Aksi')
                    ->whereIn('l.No_Sampel', $bagian)
                    ->where('l.Jenis_Aksi', 'like', 'VALIDASI%')
                    ->where('l.Sub_Aksi', 'SETUJU')
                    ->select('l.No_Sampel', 'd.Id_Jenis_Analisa',
                        DB::raw('COALESCE(d.Id_User, l.Id_User) AS Id_User'),
                        DB::raw('COALESCE(d.Tanggal, l.Tanggal) AS Tanggal'),
                        DB::raw('COALESCE(d.Jam, l.Jam) AS Jam'))
                    ->get()
            );
        }

        // Nama dibaca dari master user; nama tersalin pada approval hanya
        // cadangan bila akunnya sudah tidak ada.
        $nama = DB::table('N_EMI_LAB_Users')
            ->whereIn('UserId', $approval->concat($log)->concat($detail)->pluck('Id_User')->filter()->unique()->values()->all())
            ->pluck('Nama', 'UserId');

        $jejak = fn ($x, string $sumber) => [
            'id'      => $x->Id_User,
            'nama'    => ($nama[$x->Id_User] ?? null) ?: (($x->Nama_User ?? null) ?: $x->Id_User),
            'tanggal' => $x->Tanggal,
            'jam'     => $x->Jam,
            'sumber'  => $sumber,
        ];

        $urut = fn ($x) => sprintf('%05d', 99999 - (int) ($x->Tahapan_Ke ?? 1))
            . $this->stempel($x->Tanggal, $x->Jam);

        foreach ($approval->sortBy($urut) as $x) {
            $sub = $this->normalSub($x->No_Sub_Sampel, $x->No_Sampel);
            $peta['sub'][$x->No_Sampel . '|' . $x->Id_Jenis_Analisa . '|' . $sub] ??= $jejak($x, 'APPROVAL');
            $peta['analisa'][$x->No_Sampel . '|' . $x->Id_Jenis_Analisa] ??= $jejak($x, 'APPROVAL');
        }

        foreach ($log->sortBy($urut) as $x) {
            $peta['log'][$x->No_Sampel . '|' . $x->Id_Jenis_Analisa] ??= $jejak($x, 'LOG_AKSI');
        }

        foreach ($detail->sortBy($urut) as $x) {
            $sub = $this->normalSub($x->No_Sub_Sampel, $x->No_Sampel);
            $peta['detail_sub'][$x->No_Sampel . '|' . $x->Id_Jenis_Analisa . '|' . $sub] ??= $jejak($x, 'DETAIL_FINAL');
            $peta['detail'][$x->No_Sampel . '|' . $x->Id_Jenis_Analisa] ??= $jejak($x, 'DETAIL_FINAL');
        }

        return $peta;
    }

    /**
     * Validator satu baris uji, atau null bila tidak tercatat.
     *
     * Approval pada sub-sampel yang sama didahulukan. Bila tidak ada, log
     * aksi pada analisa yang sama; lalu approval pada sub-sampel lain —
     * sebagian jalur validasi tidak mengirim sub-sampelnya, sehingga
     * approvalnya tercatat pada sub-sampel pertama yang ditemukan. Terakhir
     * Detail_Final (realtime / pra-migrasi).
     */
    public function validatorBaris(object $r, array $peta): ?array
    {
        $analisa = $r->No_Po_Sampel . '|' . $r->Id_Jenis_Analisa;
        $sub     = $this->normalSub($r->No_Fak_Sub_Po, $r->No_Po_Sampel);

        return $peta['sub'][$analisa . '|' . $sub]
            ?? $peta['log'][$analisa]
            ?? $peta['analisa'][$analisa]
            ?? ($peta['detail_sub'][$analisa . '|' . $sub] ?? null)
            ?? ($peta['detail'][$analisa] ?? null)
            ?? null;
    }

    /**
     * Jejak dengan waktu paling akhir.
     *
     * Tanggal dan jam tersimpan di dua kolom, sehingga keduanya dibandingkan
     * bersama. Mengambil MAX masing-masing secara terpisah menghasilkan waktu
     * yang tidak pernah terjadi — tanggal dari satu baris, jam dari baris lain.
     *
     * @param  Collection  $jejak  berisi array bertanda 'tanggal' dan 'jam'
     */
    public function terakhir(Collection $jejak): ?array
    {
        return $jejak->filter(fn ($j) => is_array($j) && !empty($j['tanggal']))
            ->sortByDesc(fn ($j) => $this->stempel($j['tanggal'], $j['jam']))
            ->first();
    }

    /** Tanggal + jam sebagai teks yang dapat diurutkan: "2026-09-24 09:49:08". */
    private function stempel($tanggal, $jam): string
    {
        return substr((string) $tanggal, 0, 10) . ' ' . substr((string) $jam, 0, 8);
    }

    /**
     * Sub-sampel dalam bentuk yang dapat dibandingkan.
     *
     * Sampel tunggal tercatat NULL pada satu tabel dan sama dengan nomor
     * sampel pada tabel lain; keduanya disamakan menjadi string kosong.
     */
    private function normalSub($sub, string $noSampel): string
    {
        $sub = trim((string) $sub);

        return ($sub === '' || $sub === $noSampel || $sub === '-') ? '' : $sub;
    }

    // ------------------------------------------------------------------
    // Kelayakan
    // ------------------------------------------------------------------

    /**
     * Jelaskan DASAR kelayakan satu analisa, bukan sekadar Y/T.
     *
     * Tiga jalur penilaian di sistem ini:
     *
     *   1. RENTANG   — analisa perhitungan (Flag_Perhitungan='Y'). Layak bila
     *                  Range_Awal <= hasil <= Range_Akhir. Batas diambil dari
     *                  N_EMI_LAB_Standar_Rentang berdasarkan kombinasi
     *                  (jenis analisa x kode barang x mesin).
     *
     *   2. KRITERIA  — analisa non-perhitungan (look view, palatabilitas).
     *                  Nilai hasil berupa kode numerik yang dipetakan ke
     *                  N_EMI_LAB_Standar_Rentang_Non_Perhitungan; masternya
     *                  yang menentukan layak/tidak beserta keterangannya.
     *
     *   3. TANPA MASTER — tidak ada standar terdaftar untuk analisa ini.
     *                  Sistem menganggapnya layak, tetapi itu ASUMSI, bukan
     *                  hasil pengujian terhadap standar. Verifikator wajib
     *                  diberi tahu agar tidak salah menyimpulkan.
     *
     * @return array{dasar:string, layak:string, ringkas:string, rincian:array}
     */
    public function evaluasiKelayakan($u, ?object $po): array
    {
        $hasil  = $u->Hasil;
        $perhit = $u->Flag_Perhitungan === 'Y';

        // Flag_Layak yang tersimpan di baris uji sengaja TIDAK dipakai
        // sebagai penentu. Nilai itu hanya rekaman keputusan terdahulu dan
        // pada data lama kerap berisi 'Y' meski masternya tidak pernah ada.
        // Penilaian di sini selalu dihitung ulang dari master.

        // ---- Jalur 2: kriteria non-perhitungan ----------------------------
        if (!$perhit) {
            $kriteria = $this->kriteriaNonPerhitungan($u->Id_Jenis_Analisa);

            if ($kriteria->isEmpty()) {
                return [
                    'dasar'   => 'TANPA_MASTER',
                    'layak'   => self::LAYAK_TIDAK_TENTU,
                    'ringkas' => 'Kriteria kelayakan belum diatur',
                    'rincian' => [
                        'catatan' => 'Analisa ini belum punya kriteria kelayakan di master, '
                            . 'sehingga hasilnya tidak dapat dinyatakan layak maupun tidak layak.',
                    ],
                ];
            }

            // Dicocokkan sebagai angka, bukan string. Kedua kolom bertipe
            // float, sehingga nilai yang sama dapat tersaji berbeda
            // ("-831565647" vs "-831565647.0") dan perbandingan teks akan
            // meleset — hasil yang sah lalu terbaca "tidak terdaftar".
            $samaDengan = function ($a, $b): bool {
                if ($a === null || $b === null) return false;
                if (is_numeric($a) && is_numeric($b)) {
                    return abs((float) $a - (float) $b) < 0.000001;
                }
                return (string) $a === (string) $b;
            };

            $cocok = $kriteria->first(fn ($k) => $samaDengan($k->Nilai_Kriteria, $hasil));

            if (!$cocok) {
                // Hasil tersimpan tidak ada padanannya di master. Umumnya
                // karena kriteria pernah diubah atau dihapus setelah sampel
                // diuji, sehingga nilai lama menjadi yatim.
                return [
                    'dasar'   => 'KRITERIA_TIDAK_COCOK',
                    'layak'   => self::LAYAK_TIDAK_TENTU,
                    'ringkas' => 'Hasil tidak terdaftar pada kriteria mana pun',
                    'rincian' => [
                        'nilai_tersimpan' => $u->Nilai_Hasil_String ?: $hasil,
                        'jumlah_kriteria' => $kriteria->count(),
                        'jumlah_layak'    => $kriteria->where('Flag_Layak', 'Y')->count(),
                        'jumlah_tidak'    => $kriteria->where('Flag_Layak', 'T')->count(),
                        'daftar_layak'    => $kriteria->where('Flag_Layak', 'Y')
                            ->pluck('Keterangan_Kriteria')->values(),
                        'pilihan'         => $kriteria->map(fn ($k) => [
                            'keterangan' => $k->Keterangan_Kriteria,
                            'layak'      => $k->Flag_Layak,
                            'terpilih'   => false,
                        ])->values(),
                        'catatan' => 'Hasil tersimpan tidak menyerupai satu pun kriteria aktif. '
                            . 'Biasanya karena kriteria di master berubah setelah sampel diuji, '
                            . 'sehingga kelayakannya tidak dapat ditetapkan.',
                    ],
                ];
            }

            $layakCocok = $cocok->Flag_Layak ?: self::LAYAK_TIDAK_TENTU;

            return [
                'dasar'   => 'KRITERIA',
                'layak'   => $layakCocok,
                // Sebutkan kriteria yang cocok BESERTA putusannya, supaya
                // verifikator tidak perlu menebak arti "5 kriteria".
                'ringkas' => $layakCocok === 'T'
                    ? 'Hasil "' . $cocok->Keterangan_Kriteria . '" termasuk kriteria tidak layak'
                    : 'Hasil "' . $cocok->Keterangan_Kriteria . '" termasuk kriteria layak',
                'rincian' => [
                    'kriteria_terpilih' => $cocok->Keterangan_Kriteria,
                    'layak_terpilih'    => $layakCocok,
                    'jumlah_kriteria'   => $kriteria->count(),
                    'jumlah_layak'      => $kriteria->where('Flag_Layak', 'Y')->count(),
                    'jumlah_tidak'      => $kriteria->where('Flag_Layak', 'T')->count(),
                    // Daftar kriteria yang dianggap layak. Ditampilkan apa
                    // adanya di kolom supaya hasil di luar daftar ini
                    // langsung terlihat tanpa membuka tooltip.
                    'daftar_layak'      => $kriteria->where('Flag_Layak', 'Y')
                        ->pluck('Keterangan_Kriteria')->values(),
                    'pilihan'           => $kriteria->map(fn ($k) => [
                        'keterangan' => $k->Keterangan_Kriteria,
                        'layak'      => $k->Flag_Layak,
                        'terpilih'   => $samaDengan($k->Nilai_Kriteria, $hasil),
                    ])->values(),
                ],
            ];
        }

        // ---- Jalur 1: rentang perhitungan ---------------------------------
        // Batas pada baris uji dipakai lebih dulu (itulah yang berlaku saat
        // pengujian); master hanya dirujuk bila baris uji tidak menyimpannya.
        $min = $u->Range_Awal;
        $max = $u->Range_Akhir;
        $sumberBatas = 'baris uji';

        if ($min === null && $max === null && $po) {
            $master = $this->rentangMaster($u->Id_Jenis_Analisa, $po->Kode_Barang, $po->Id_Mesin);

            if ($master) {
                $min = $master->Range_Awal;
                $max = $master->Range_Akhir;
                $sumberBatas = 'master standar rentang';
            }
        }

        if ($min === null && $max === null) {
            return [
                'dasar'   => 'TANPA_MASTER',
                'layak'   => self::LAYAK_TIDAK_TENTU,
                'ringkas' => 'Kriteria kelayakan belum diatur',
                'rincian' => [
                    'catatan' => 'Belum ada batas min/max di master untuk kombinasi analisa, '
                        . 'barang, dan mesin ini, sehingga hasilnya tidak dapat dinyatakan '
                        . 'layak maupun tidak layak.',
                    'kode_barang' => $po->Kode_Barang ?? null,
                    'id_mesin'    => $po->Id_Mesin ?? null,
                ],
            ];
        }

        $nilai   = $hasil === null ? null : (float) $hasil;
        $dibawah = $nilai !== null && $min !== null && $nilai < (float) $min;
        $diatas  = $nilai !== null && $max !== null && $nilai > (float) $max;
        $layak   = ($nilai === null || $dibawah || $diatas) ? 'T' : 'Y';

        $ringkas = $layak === 'Y'
            ? 'Berada dalam rentang standar'
            : ($nilai === null
                ? 'Hasil kosong'
                : ($dibawah ? 'Di bawah batas minimum' : 'Melebihi batas maksimum'));

        return [
            'dasar'   => 'RENTANG',
            'layak'   => $layak,
            'ringkas' => $ringkas,
            'rincian' => [
                'min'           => $min,
                'max'           => $max,
                'nilai'         => $nilai,
                'sumber_batas'  => $sumberBatas,
                'di_bawah_min'  => $dibawah,
                'di_atas_max'   => $diatas,
            ],
        ];
    }

    /**
     * Muat master kelayakan banyak analisa sekaligus — dua query untuk satu
     * sampel, bukan satu-dua query per analisa. Hasilnya mengisi cache yang
     * sama dengan yang dibaca evaluasiKelayakan(), jadi penilaiannya tidak
     * berubah.
     */
    public function siapkanMaster(array $idJenisAnalisa, ?object $po): void
    {
        $id = array_values(array_unique(array_map('intval', array_filter($idJenisAnalisa))));
        $id = array_values(array_filter($id, fn ($x) => !isset($this->cacheKriteria[(string) $x])));
        if (empty($id)) {
            return;
        }

        foreach (array_chunk($id, 500) as $bagian) {
            $kriteria = DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->whereIn('Id_Jenis_Analisa', $bagian)
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Jenis_Analisa', 'Nilai_Kriteria', 'Keterangan_Kriteria', 'Flag_Layak')
                ->get()
                ->groupBy(fn ($r) => (string) (int) $r->Id_Jenis_Analisa);

            foreach ($bagian as $x) {
                $this->cacheKriteria[(string) $x] = ($kriteria[(string) $x] ?? collect())
                    ->map(fn ($r) => (object) [
                        'Nilai_Kriteria'      => $r->Nilai_Kriteria,
                        'Keterangan_Kriteria' => $r->Keterangan_Kriteria,
                        'Flag_Layak'          => $r->Flag_Layak,
                    ])->values();
            }

            if ($po) {
                $rentang = DB::table('N_EMI_LAB_Standar_Rentang')
                    ->whereIn('Id_Jenis_Analisa', $bagian)
                    ->where('Kode_Barang', $po->Kode_Barang)
                    ->where('Id_Master_Mesin', $po->Id_Mesin)
                    ->select('Id_Jenis_Analisa', 'Range_Awal', 'Range_Akhir')
                    ->get()
                    ->groupBy(fn ($r) => (string) (int) $r->Id_Jenis_Analisa);

                foreach ($bagian as $x) {
                    $kunci = $x . '|' . $po->Kode_Barang . '|' . $po->Id_Mesin;
                    if (!array_key_exists($kunci, $this->cacheRentang)) {
                        $r = ($rentang[(string) $x] ?? collect())->first();
                        $this->cacheRentang[$kunci] = $r
                            ? (object) ['Range_Awal' => $r->Range_Awal, 'Range_Akhir' => $r->Range_Akhir]
                            : null;
                    }
                }
            }
        }
    }

    private function kriteriaNonPerhitungan($idJenisAnalisa): Collection
    {
        return $this->cacheKriteria[(string) $idJenisAnalisa] ??=
            DB::table('N_EMI_LAB_Standar_Rentang_Non_Perhitungan')
                ->where('Id_Jenis_Analisa', $idJenisAnalisa)
                ->where('Flag_Aktif', 'Y')
                ->select('Nilai_Kriteria', 'Keterangan_Kriteria', 'Flag_Layak')
                ->get();
    }

    private function rentangMaster($idJenisAnalisa, $kodeBarang, $idMesin): ?object
    {
        $kunci = $idJenisAnalisa . '|' . $kodeBarang . '|' . $idMesin;

        // array_key_exists, bukan ??=: "tidak ada master" juga disimpan.
        if (!array_key_exists($kunci, $this->cacheRentang)) {
            $this->cacheRentang[$kunci] = DB::table('N_EMI_LAB_Standar_Rentang')
                ->where('Id_Jenis_Analisa', $idJenisAnalisa)
                ->where('Kode_Barang', $kodeBarang)
                ->where('Id_Master_Mesin', $idMesin)
                ->select('Range_Awal', 'Range_Akhir')
                ->first();
        }

        return $this->cacheRentang[$kunci];
    }

    // ------------------------------------------------------------------
    // Resampling
    // ------------------------------------------------------------------

    /**
     * Riwayat pengulangan uji (resampling) per jenis analisa.
     *
     * Bukan sekadar jumlah: menyertakan nomor sampel asal dan sampel ulang
     * (mis. FS0926-0001-1 diulang menjadi FS0926-0001-2), sehingga
     * verifikator tahu hasil mana yang sedang ia nilai.
     *
     * @return array<int, array{jumlah:int, sampel:array<int,array>}>
     */
    public function petaResampling(string $noSampel): array
    {
        return $this->petaResamplingBanyak([$noSampel])[$noSampel] ?? [];
    }

    /**
     * petaResampling() untuk banyak sampel sekaligus — dua query untuk
     * seluruh halaman, bukan dua query per sampel.
     *
     * @return array<string, array> peta resampling per nomor sampel
     */
    public function petaResamplingBanyak(array $noSampel): array
    {
        $noSampel = array_values(array_unique(array_filter($noSampel)));
        $hasil    = array_fill_keys($noSampel, []);

        if (empty($noSampel)) {
            return $hasil;
        }

        // Dibandingkan seperti SQL Server membandingkannya: tanpa membedakan
        // huruf besar-kecil dan spasi di ujung.
        $normal = fn ($v) => strtoupper(trim((string) $v));
        $milik  = [];
        foreach ($noSampel as $ns) {
            $milik[$normal($ns)] = $ns;
        }

        $log  = collect();
        $flag = collect();

        foreach (array_chunk($noSampel, 500) as $bagian) {
            // Sumber utama: log resampling, memuat pasangan asal -> ulang.
            $log = $log->concat(DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                ->where(function ($q) use ($bagian) {
                    $q->whereIn('No_Po_Sampel', $bagian)
                      ->orWhereIn('No_Sampel_Resampling_Origin', $bagian);
                })
                ->select('No_Po_Sampel', 'Id_Jenis_Analisa', 'No_Sampel_Resampling_Origin',
                    'No_Sampel_Resampling', 'Tahapan_Ke', 'Keterangan',
                    'Tanggal', 'Jam', 'Id_User', 'Id_Resampling')
                ->get());

            // Cadangan: baris uji bertanda Flag_Resampling, dipakai bila log
            // belum terisi (data lama sebelum log resampling dipakai).
            $flag = $flag->concat(DB::table('N_EMI_LAB_Uji_Sampel')
                ->whereIn('No_Po_Sampel', $bagian)
                ->where('Flag_Resampling', 'Y')
                ->select('No_Po_Sampel', 'Id_Jenis_Analisa', 'No_Fak_Sub_Po', 'Tahapan_Ke')
                ->get());
        }

        // Satu baris log dapat milik dua sampel: sebagai sampelnya sendiri
        // dan sebagai asal resampling sampel lain.
        $logPer = [];
        foreach ($log->sortBy('Id_Resampling') as $r) {
            $pemilik = array_unique(array_filter([
                $milik[$normal($r->No_Po_Sampel)] ?? null,
                $milik[$normal($r->No_Sampel_Resampling_Origin)] ?? null,
            ]));
            foreach ($pemilik as $ns) {
                $logPer[$ns][] = $r;
            }
        }

        foreach ($noSampel as $ns) {
            $hasil[$ns] = $this->susunResampling(
                collect($logPer[$ns] ?? []),
                $flag->filter(fn ($r) => $normal($r->No_Po_Sampel) === $normal($ns))
            );
        }

        return $hasil;
    }

    /** Susun peta resampling satu sampel dari baris log dan baris uji. */
    private function susunResampling(Collection $log, Collection $flag): array
    {
        $peta = [];

        foreach ($log->groupBy('Id_Jenis_Analisa') as $ja => $rows) {
            $peta[$ja] = [
                'jumlah' => $rows->count(),
                'sampel' => $rows->map(fn ($r) => [
                    'asal'       => $r->No_Sampel_Resampling_Origin,
                    'ulang'      => $r->No_Sampel_Resampling,
                    'tahapan'    => $r->Tahapan_Ke,
                    'keterangan' => $r->Keterangan,
                    'tanggal'    => $r->Tanggal,
                    'jam'        => $r->Jam,
                    'user'       => $r->Id_User,
                ])->values()->all(),
            ];
        }

        foreach ($flag->groupBy('Id_Jenis_Analisa') as $ja => $rows) {
            if (isset($peta[$ja])) {
                continue;   // log lebih rinci, jangan ditimpa
            }

            $peta[$ja] = [
                'jumlah' => $rows->count(),
                'sampel' => $rows->map(fn ($r) => [
                    'asal'       => null,
                    'ulang'      => $r->No_Fak_Sub_Po,
                    'tahapan'    => $r->Tahapan_Ke,
                    'keterangan' => null,
                    'tanggal'    => null,
                    'jam'        => null,
                    'user'       => null,
                ])->values()->all(),
            ];
        }

        return $peta;
    }

    // ------------------------------------------------------------------
    // Palatabilitas
    // ------------------------------------------------------------------

    public function petaPembanding(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
            ->whereIn('Id_Pembanding', $ids)
            ->pluck('Nama_Pembanding', 'Id_Pembanding')
            ->toArray();
    }

    /**
     * Daftar kolom pembanding untuk satu sampel, terurut sesuai master.
     *
     * Urutan diambil dari kolom Urutan pada master pembanding supaya susunan
     * kolom tetap sama di setiap layar. Baris tanpa pembanding tetap diberi
     * satu kolom agar hasilnya tidak hilang dari tabel.
     */
    private function kolomPembanding($rows, array $petaNama): array
    {
        $ids = $rows->pluck('Id_Pembanding')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            // Palatabilitas tanpa sesi pembanding: tetap satu kolom hasil.
            return [['id' => null, 'nama' => 'Hasil Uji', 'urutan' => 1]];
        }

        $urutan = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
            ->whereIn('Id_Pembanding', $ids->all())
            ->pluck('Urutan', 'Id_Pembanding')
            ->toArray();

        return $ids
            ->map(fn ($id) => [
                'id'     => $id,
                'nama'   => $petaNama[$id] ?? ('Pembanding ' . $id),
                'urutan' => $urutan[$id] ?? 99,
            ])
            ->sortBy('urutan')
            ->values()
            ->all();
    }

    /**
     * Susun hasil palatabilitas menjadi matriks: satu baris per jenis
     * analisa, satu sel per pembanding.
     *
     * Satu jenis analisa dapat menyimpan beberapa nilai untuk pembanding yang
     * sama (mis. beberapa responden). Nilai-nilai itu dikumpulkan apa adanya
     * dalam satu sel, bukan dirata-rata, karena verifikator perlu melihat
     * sebaran aslinya.
     */
    private function matriksPembanding($rows, $evaluasi, array $validasi): array
    {
        $parameter = $this->parameterAnalisa(
            $rows->pluck('Id_Jenis_Analisa')->unique()->values()->all(),
            $rows
        );

        return $rows
            ->groupBy('Id_Jenis_Analisa')
            ->map(function ($baris) use ($evaluasi, $parameter, $validasi) {
                $f     = $baris->first();
                $daftarParam = $parameter[$f->Id_Jenis_Analisa] ?? [];

                $sel = $baris->groupBy(fn ($r) => $r->Id_Pembanding ?? '_')
                    ->map(function ($isi) use ($evaluasi, $daftarParam) {
                        // Setiap baris uji menyimpan SATU parameter, berurutan
                        // sesuai daftar parameter jenis analisa tersebut.
                        // Tanpa pasangan ini, angka di layar berdiri sendiri
                        // tanpa keterangan — "54,7" tidak berarti apa pun.
                        return $isi->values()->map(function ($r, $i) use ($evaluasi, $daftarParam) {
                            $ev = $evaluasi[$this->kunciBaris($r)] ?? null;
                            $p  = $daftarParam[$i] ?? null;

                            return [
                                'hasil'  => $r->Nilai_Hasil_String ?: $r->Hasil,
                                'angka'  => $r->Hasil,
                                'teks'   => $r->Nilai_Hasil_String,
                                'param'  => $p['nama'] ?? null,
                                'satuan' => $p['satuan'] ?? null,
                                'layak'  => $ev['layak'] ?? null,
                                'dasar'  => $ev['dasar'] ?? null,
                            ];
                        })->all();
                    })->all();

                return [
                    'Id_Jenis_Analisa'   => $f->Id_Jenis_Analisa,
                    'Nama_Jenis_Analisa' => $f->Jenis_Analisa,
                    'Kode_Analisa'       => $f->Kode_Analisa,
                    'Jumlah_Nilai'       => $baris->count(),
                    'Parameter'          => $daftarParam,
                    'Input'              => $this->terakhir($baris->map(fn ($r) => $this->jejakInput($r))),
                    'Validasi'           => $this->terakhir(
                        $baris->map(fn ($r) => $this->validatorBaris($r, $validasi))->filter()
                    ),
                    // Kunci sel memakai id pembanding; '_' untuk yang tanpa.
                    'sel'                => $sel,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Daftar parameter (quality control) per jenis analisa.
     *
     * Satu jenis analisa palatabilitas diuji atas beberapa parameter —
     * Pilihan Sampel, Pilihan Kontrol, Waktu, dan seterusnya. Hasilnya
     * tersimpan sebagai beberapa baris pada N_EMI_LAB_Uji_Sampel, berurutan
     * mengikuti daftar ini, sehingga nama parameternya dapat dipasangkan
     * kembali untuk ditampilkan.
     */
    private function parameterAnalisa(array $idAnalisa, $rows = null): array
    {
        if (empty($idAnalisa)) {
            return [];
        }

        $peta = $this->parameterDariBinding($idAnalisa);

        // Sebagian jenis analisa belum terdaftar di tabel binding walau
        // detail parameternya sudah tersimpan. Untuk itu daftar parameter
        // disusun ulang dari detail hasil uji, supaya kolomnya tetap
        // bernama dan tidak berakhir kosong di layar.
        if ($rows !== null) {
            foreach ($idAnalisa as $id) {
                if (!empty($peta[$id])) {
                    continue;
                }

                $faktur = $rows->where('Id_Jenis_Analisa', $id)
                    ->pluck('No_Faktur')->filter()->unique()->values()->all();

                if (empty($faktur)) {
                    continue;
                }

                $dariDetail = $this->parameterDariDetail($faktur);
                if (!empty($dariDetail)) {
                    $peta[$id] = $dariDetail;
                }
            }
        }

        return $peta;
    }

    /** Parameter menurut master binding jenis analisa. */
    private function parameterDariBinding(array $idAnalisa): array
    {
        return DB::table('N_EMI_LAB_Binding_Jenis_Analisa as b')
            ->leftJoin('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'b.Id_Quality_Control')
            ->whereIn('b.Id_Jenis_Analisa', $idAnalisa)
            ->orderBy('b.Id_Jenis_Analisa')
            ->orderBy('b.Id_Quality_Control')
            ->select('b.Id_Jenis_Analisa', 'b.Id_Quality_Control',
                'q.Kode_Uji', 'q.Keterangan', 'q.Satuan')
            ->get()
            ->groupBy('Id_Jenis_Analisa')
            ->map(fn ($g) => $g->map(fn ($p) => [
                'id'     => $p->Id_Quality_Control,
                'kode'   => $p->Kode_Uji,
                'nama'   => $p->Keterangan ?: ('Parameter ' . $p->Id_Quality_Control),
                // 'NONE' pada master berarti tanpa satuan.
                'satuan' => $this->satuanParam($p->Satuan, $p->Keterangan),
            ])->values()->all())
            ->all();
    }

    /**
     * Satuan yang layak ditampilkan di samping nama parameter.
     *
     * 'NONE' pada master berarti tanpa satuan. Satuan juga disembunyikan
     * bila sudah tertulis pada namanya, supaya tidak terbaca ganda seperti
     * "Sampel (%) (%)".
     */
    private function satuanParam(?string $satuan, ?string $nama): ?string
    {
        if (!$satuan || $satuan === 'NONE') {
            return null;
        }

        return str_contains((string) $nama, $satuan) ? null : $satuan;
    }

    /**
     * Parameter disusun ulang dari detail hasil uji.
     *
     * Dipakai ketika jenis analisa belum terdaftar pada tabel binding.
     * Urutan mengikuti Id_Quality_Control, sama dengan urutan penyimpanan
     * baris hasil, sehingga pasangan nilai dan namanya tetap tepat.
     */
    private function parameterDariDetail(array $faktur): array
    {
        return DB::table('N_EMI_LAB_Uji_Sampel_Detail as d')
            ->leftJoin('EMI_Quality_Control as q', 'q.Id_QC_Formula', '=', 'd.Id_Quality_Control')
            ->whereIn('d.No_Faktur_Uji_Sample', $faktur)
            ->distinct()
            ->orderBy('d.Id_Quality_Control')
            ->select('d.Id_Quality_Control', 'q.Kode_Uji', 'q.Keterangan', 'q.Satuan')
            ->get()
            ->map(fn ($p) => [
                'id'     => $p->Id_Quality_Control,
                'kode'   => $p->Kode_Uji,
                'nama'   => $p->Keterangan ?: ('Parameter ' . $p->Id_Quality_Control),
                'satuan' => $this->satuanParam($p->Satuan, $p->Keterangan),
            ])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Foto & parameter perhitungan
    // ------------------------------------------------------------------

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
    public function petaFoto(array $faktur): array
    {
        $peta = [];

        foreach (array_chunk(array_values(array_unique(array_filter($faktur))), 1000) as $bagian) {
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
     * Parameter masukan analisa perhitungan per nomor uji — mis. berat
     * sampel dan volume titran — supaya validator dapat memeriksa hasil
     * akhir terhadap angka asalnya.
     *
     * Analisa non-perhitungan (Look View) dan palatabilitas dilewati: nilai
     * parameternya sudah tampil sebagai hasil atau matriks pembanding.
     * Baris wajib memuat kolom daftar kerja (Jenis_Analisa, Kode_Aktivitas_Lab).
     *
     * @return array<int, array{Id_Jenis_Analisa:int, Nama_Jenis_Analisa:string,
     *               parameter:array, baris:array}>
     */
    public function parameterPerhitungan(Collection $rows): array
    {
        $rows = $rows->filter(fn ($r) => $r->Flag_Perhitungan === 'Y'
            && ($r->Kode_Aktivitas_Lab ?? null) !== 'PLT')->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $detail = collect();
        foreach ($rows->pluck('No_Faktur')->filter()->unique()->chunk(1000) as $bagian) {
            $detail = $detail->concat(DB::table('N_EMI_LAB_Uji_Sampel_Detail')
                ->whereIn('No_Faktur_Uji_Sample', $bagian->values())
                ->orderBy('Id_Uji_Sample_Detail')
                ->select('No_Faktur_Uji_Sample', 'Id_Quality_Control', 'Value_Parameter')
                ->get());
        }
        $detail = $detail->groupBy('No_Faktur_Uji_Sample');

        $nama = $this->parameterAnalisa($rows->pluck('Id_Jenis_Analisa')->unique()->values()->all(), $rows);

        return $rows->groupBy('Id_Jenis_Analisa')
            ->map(function ($g, $id) use ($detail, $nama) {
                $param = $nama[$id] ?? [];

                $baris = $g->unique('No_Faktur')->map(function ($r) use ($detail, $param) {
                    $nilai = $detail->get($r->No_Faktur, collect())
                        ->keyBy(fn ($d) => (string) $d->Id_Quality_Control);

                    return [
                        'No_Faktur'     => $r->No_Faktur,
                        'No_Sampel_Uji' => $r->No_Fak_Sub_Po ?: $r->No_Po_Sampel,
                        'Hasil'         => $r->Hasil,
                        'nilai'         => array_map(fn ($p) => isset($nilai[(string) $p['id']])
                            ? (float) $nilai[(string) $p['id']]->Value_Parameter : null, $param),
                    ];
                })->values()->all();

                return [
                    'Id_Jenis_Analisa'   => (int) $id,
                    'Nama_Jenis_Analisa' => $g->first()->Jenis_Analisa,
                    'parameter'          => $param,
                    'baris'              => $baris,
                ];
            })
            ->filter(fn ($x) => !empty($x['parameter']))
            ->values()
            ->all();
    }
}
