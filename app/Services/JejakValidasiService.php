<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Satu pintu pencatatan jejak validasi.
 *
 * LATAR BELAKANG
 * --------------
 * Sebelumnya setiap controller mencatat sendiri ke
 * N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final, dan pencatatan itu bersarang di
 * dalam cabang `if ($flagFG && $flagPerhitungan === 'Y')`. Akibatnya analisa
 * non-perhitungan — seluruh palatabilitas (PLT) dan look view (LCKV), yang di
 * master memang ber-Flag_Perhitungan NULL — tidak pernah tercatat sama sekali,
 * meski validasinya benar-benar terjadi.
 *
 * Kelas ini memisahkan dua keputusan yang dahulu tercampur:
 *
 *   1. Flag_Perhitungan  -> menentukan CARA menghitung kelayakan
 *                           (dari rumus, atau dari keputusan manual/foto).
 *   2. Pencatatan jejak  -> SELALU dilakukan untuk setiap analisa yang
 *                           divalidasi, apa pun kode aktivitasnya.
 *
 * Dengan begitu tidak ada lagi validasi yang hilang jejaknya.
 *
 * Semua method di sini idempoten: dipanggil dua kali untuk analisa yang sama
 * tidak menggandakan baris (mencegah double-submit dari klik ganda).
 *
 * @see docs/pembaharuan/sql/23-09-2026/ untuk migrasi struktur yang menyertai.
 */
class JejakValidasiService
{
    /** Ditulis saat user memvalidasi satu analisa. */
    public const SUMBER_VALIDASI = 'VALIDASI';

    /** Ditulis saat sampel difinalisasi. */
    public const SUMBER_FINALISASI = 'FINALISASI';

    /** Persetujuan per analisa oleh petugas lab. */
    public const APPROVAL_VALIDASI = 'VALIDASI';

    /** Persetujuan berjenjang (validasi hirarki formulator). */
    public const APPROVAL_HIRARKI = 'APPROVAL_HIRARKI';

    /** Persetujuan akhir atas keseluruhan sampel. */
    public const APPROVAL_FINALISASI = 'FINALISASI';

    private const TABEL_DETAIL   = 'N_EMI_LAB_Hasil_Uji_Validasi_Detail_Final';
    private const TABEL_APPROVAL = 'N_EMI_LAB_Hasil_Uji_Approval_Aktivitas';

    /**
     * Catat jejak satu analisa yang baru divalidasi.
     *
     * Dipanggil untuk SETIAP analisa tanpa kecuali — ANL, PLT, maupun LCKV.
     * Tidak ada lagi penyaringan berdasarkan Flag_Perhitungan.
     *
     * @param  array  $data {
     *     @type string      $No_Sampel         wajib
     *     @type string|null $No_Sub_Sampel     No_Fak_Sub_Po; default No_Sampel
     *     @type int         $Id_Jenis_Analisa  wajib
     *     @type string      $Id_User           wajib
     *     @type string|null $Flag_Layak        'Y'|'T'; default 'Y'
     *     @type int|null    $Tahapan_Ke        default 1
     *     @type string|null $Tanggal           'Y-m-d'
     *     @type string|null $Jam               'H:i:s'
     *     @type int|null    $Id_Session        konteks PLT
     *     @type int|null    $Id_Pembanding     konteks PLT
     *     @type string|null $Flag_Resampling
     * }
     * @return bool true bila baris baru ditulis, false bila sudah ada.
     */
    public function catatDetail(array $data): bool
    {
        $noSampel = $data['No_Sampel'] ?? null;
        $idJenis  = $data['Id_Jenis_Analisa'] ?? null;

        if (empty($noSampel) || empty($idJenis)) {
            return false;
        }

        // Konteks diambil dari Uji_Sampel — sumber kebenaran — bukan dari
        // request. Frontend tidak selalu mengirim No_Fak_Sub_Po/Id_Session,
        // dan pernah mengirim placeholder tampilan "—" sebagai sub-sampel.
        $konteks = $this->konteksUjiSampel($noSampel, $idJenis, $data);

        $subSampel  = $konteks['No_Sub_Sampel'];
        $tahapan    = $konteks['Tahapan_Ke'];
        $pembanding = $konteks['Id_Pembanding'];

        // Idempoten: satu analisa (+pembanding, untuk PLT) hanya sekali.
        $sudahAda = DB::table(self::TABEL_DETAIL)
            ->where('No_Sampel', $noSampel)
            ->where('Id_Jenis_Analisa', $idJenis)
            ->when($subSampel === null,
                fn ($q) => $q->whereNull('No_Sub_Sampel'),
                fn ($q) => $q->where('No_Sub_Sampel', $subSampel))
            ->where('Tahapan_Ke', $tahapan)
            ->when($pembanding === null,
                fn ($q) => $q->whereNull('Id_Pembanding'),
                fn ($q) => $q->where('Id_Pembanding', $pembanding))
            ->exists();

        if ($sudahAda) {
            return false;
        }

        $master = $this->masterAnalisa($idJenis);

        DB::table(self::TABEL_DETAIL)->insert([
            'No_Sampel'          => $noSampel,
            'No_Sub_Sampel'      => $subSampel,
            'Id_Jenis_Analisa'   => $idJenis,
            'Tahapan_Ke'         => $tahapan,
            'Flag_Layak'         => $data['Flag_Layak'] ?? 'Y',
            'Flag_Resampling'    => $konteks['Flag_Resampling'],
            'Tanggal'            => $data['Tanggal'] ?? null,
            'Jam'                => $data['Jam'] ?? null,
            'Id_User'            => $data['Id_User'] ?? null,
            'Kode_Aktivitas_Lab' => $master->Kode_Aktivitas_Lab ?? null,
            'Nama_Jenis_Analisa' => $master->Jenis_Analisa ?? null,
            'Id_Session'         => $konteks['Id_Session'],
            'Id_Pembanding'      => $pembanding,
            // Sambungkan langsung bila header-nya sudah ada (mis. validasi
            // susulan setelah sampel pernah difinalisasi). Bila belum ada,
            // tetap NULL dan akan diisi saat finalisasi.
            'Id_Uji_Validasi_Final' => $this->idHeaderSampel($noSampel),
            'Sumber_Pencatatan'  => self::SUMBER_VALIDASI,
            'Dibuat_Pada'        => now(),
        ]);

        return true;
    }

    /**
     * Catat SIAPA yang menyetujui satu analisa.
     *
     * Ini yang menjawab kebutuhan pelaporan: "untuk PO, sampel, dan batch ini,
     * look view di-approve siapa, analisa lab siapa, palatabilitas siapa."
     *
     * @param  array  $data  lihat catatDetail(), ditambah:
     *     @type string      $Jenis_Approval   default APPROVAL_VALIDASI
     *     @type string|null $Flag_Approval    'Y'|'T'; default 'Y'
     *     @type string|null $Keterangan
     * @return bool true bila baris baru ditulis.
     */
    public function catatApproval(array $data): bool
    {
        $noSampel = $data['No_Sampel'] ?? null;
        $idJenis  = $data['Id_Jenis_Analisa'] ?? null;
        $idUser   = $data['Id_User'] ?? null;

        if (empty($noSampel) || empty($idJenis) || empty($idUser)) {
            return false;
        }

        $master = $this->masterAnalisa($idJenis);

        // Tanpa kode aktivitas, baris ini tidak berguna untuk pelaporan.
        if (empty($master->Kode_Aktivitas_Lab)) {
            return false;
        }

        // Konteks dari Uji_Sampel, bukan dari request — lihat catatDetail().
        $konteks = $this->konteksUjiSampel($noSampel, $idJenis, $data);

        $subSampel  = $konteks['No_Sub_Sampel'];
        $jenisAppr  = $data['Jenis_Approval'] ?? self::APPROVAL_VALIDASI;
        $pembanding = $konteks['Id_Pembanding'];

        $sudahAda = DB::table(self::TABEL_APPROVAL)
            ->where('No_Sampel', $noSampel)
            ->when($subSampel === null,
                fn ($q) => $q->whereNull('No_Sub_Sampel'),
                fn ($q) => $q->where('No_Sub_Sampel', $subSampel))
            ->where('Id_Jenis_Analisa', $idJenis)
            ->where('Jenis_Approval', $jenisAppr)
            ->where('Id_User', $idUser)
            // Setiap putaran resampling adalah persetujuan tersendiri. Hanya
            // berlaku bila kunci unik tabel sudah memuat Tahapan_Ke (migrasi
            // 26-09-2026); sebelum itu tetap satu approval per analisa.
            ->when($this->kunciApprovalPerTahapan(),
                fn ($q) => $q->where('Tahapan_Ke', $konteks['Tahapan_Ke']))
            ->when($pembanding === null,
                fn ($q) => $q->whereNull('Id_Pembanding'),
                fn ($q) => $q->where('Id_Pembanding', $pembanding))
            ->exists();

        if ($sudahAda) {
            return false;
        }

        $po   = $this->infoPo($noSampel);
        $user = $this->infoUser($idUser);

        try {
            DB::table(self::TABEL_APPROVAL)->insert([
                'No_Sampel'           => $noSampel,
                'No_Sub_Sampel'       => $subSampel,
                'No_Po'               => $po->No_Po ?? null,
                'No_Split_Po'         => $po->No_Split_Po ?? null,
                'No_Batch'            => $po->No_Batch ?? null,
                'Kode_Barang'         => $po->Kode_Barang ?? null,
                'Kode_Aktivitas_Lab'  => $master->Kode_Aktivitas_Lab,
                'Nama_Aktivitas'      => $master->Nama_Aktivitas ?? null,
                'Id_Jenis_Analisa'    => $idJenis,
                'Nama_Jenis_Analisa'  => $master->Jenis_Analisa ?? null,
                'Tahapan_Ke'          => $konteks['Tahapan_Ke'],
                'Id_Session'          => $konteks['Id_Session'],
                'Id_Pembanding'       => $pembanding,
                'Id_User'             => $idUser,
                'Nama_User'           => $user->Nama ?? null,
                'Jenis_Approval'      => $jenisAppr,
                'Flag_Approval'       => $data['Flag_Approval'] ?? 'Y',
                'Flag_Layak'          => $data['Flag_Layak'] ?? null,
                'Keterangan'          => $data['Keterangan'] ?? null,
                'Tanggal'             => $data['Tanggal'] ?? null,
                'Jam'                 => $data['Jam'] ?? null,
                'Dibuat_Pada'         => now(),
                'Flag_Trial_Produksi' => $po->Flag_Trial_Produksi ?? null,
                'Flag_Resampling'     => $konteks['Flag_Resampling'],
                'Sumber_Pencatatan'   => self::SUMBER_VALIDASI,
            ]);
        } catch (QueryException $e) {
            // Bentrok kunci unik = persetujuan yang sama sudah tercatat
            // (mis. klik ganda bersamaan). Bukan kegagalan validasi.
            if (in_array($e->errorInfo[1] ?? null, [2601, 2627], true)) {
                return false;
            }
            throw $e;
        }

        return true;
    }

    /**
     * Apakah kunci unik approval sudah memuat Tahapan_Ke
     * (docs/sql/26-09-2026-lifecycle/01-STRUKTUR-LIFECYCLE.sql)?
     * Disimpan 10 menit agar tidak diperiksa di setiap validasi.
     */
    private function kunciApprovalPerTahapan(): bool
    {
        return (bool) Cache::remember('jejak-validasi:kunci-approval-tahapan:' . DB::connection()->getDatabaseName(), 600,
            fn () => DB::table('sys.indexes as i')
                ->join('sys.index_columns as ic', fn ($j) => $j->on('ic.object_id', '=', 'i.object_id')
                    ->on('ic.index_id', '=', 'i.index_id'))
                ->join('sys.columns as c', fn ($j) => $j->on('c.object_id', '=', 'ic.object_id')
                    ->on('c.column_id', '=', 'ic.column_id'))
                ->whereRaw("i.object_id = OBJECT_ID('" . self::TABEL_APPROVAL . "')")
                ->where('i.name', 'UX_ApprovalAktivitas_NonPlt')
                ->where('c.name', 'Tahapan_Ke')
                ->exists());
    }

    /**
     * Jalan pintas: catat detail dan approval sekaligus.
     * Inilah yang dipanggil controller saat sebuah analisa divalidasi.
     */
    public function catatValidasi(array $data): void
    {
        $this->catatDetail($data);
        $this->catatApproval($data);
    }

    /**
     * Catat persetujuan finalisasi untuk seluruh analisa dalam satu sampel.
     *
     * @return int jumlah baris approval yang ditulis.
     */
    public function catatFinalisasi(string $noSampel, string $idUser, array $opsi = []): int
    {
        $detail = DB::table(self::TABEL_DETAIL)
            ->where('No_Sampel', $noSampel)
            ->select('Id_Jenis_Analisa', 'No_Sub_Sampel', 'Tahapan_Ke')
            ->distinct()
            ->get();

        $ditulis = 0;

        foreach ($detail as $d) {
            $berhasil = $this->catatApproval([
                'No_Sampel'        => $noSampel,
                'No_Sub_Sampel'    => $d->No_Sub_Sampel,
                'Id_Jenis_Analisa' => $d->Id_Jenis_Analisa,
                'Tahapan_Ke'       => $d->Tahapan_Ke,
                'Id_User'          => $idUser,
                'Jenis_Approval'   => self::APPROVAL_FINALISASI,
                'Flag_Approval'    => 'Y',
                'Flag_Layak'       => $opsi['Flag_Ok'] ?? null,
                'Tanggal'          => $opsi['Tanggal'] ?? null,
                'Jam'              => $opsi['Jam'] ?? null,
            ]);

            if ($berhasil) {
                $ditulis++;
            }
        }

        return $ditulis;
    }

    /**
     * Sambungkan baris detail sebuah sampel ke header finalisasinya.
     * Dipanggil setelah header dibuat, supaya relasi FK terisi.
     */
    public function sambungkanKeHeader(string $noSampel, int $idUjiValidasiFinal): int
    {
        return DB::table(self::TABEL_DETAIL)
            ->where('No_Sampel', $noSampel)
            ->whereNull('Id_Uji_Validasi_Final')
            ->update(['Id_Uji_Validasi_Final' => $idUjiValidasiFinal]);
    }

    /**
     * Apakah sampel ini punya analisa yang ditandai TIDAK LAYAK?
     *
     * Dipakai untuk menentukan Flag_Ok pada header. Mencakup SELURUH
     * aktivitas (ANL, PLT, LCKV) — sebelumnya palatabilitas dan look view
     * yang tidak layak tidak pernah ikut diperhitungkan, sehingga produk
     * bisa berstatus "Lolos Uji" padahal ada tahapan yang gagal.
     *
     * @param  array  $kodeAktivitas  batasi ke aktivitas tertentu; kosong = semua.
     */
    public function adaYangTidakLayak(string $noSampel, array $kodeAktivitas = []): bool
    {
        return DB::table(self::TABEL_DETAIL)
            ->where('No_Sampel', $noSampel)
            ->where('Flag_Layak', 'T')
            ->when(!empty($kodeAktivitas),
                fn ($q) => $q->whereIn('Kode_Aktivitas_Lab', $kodeAktivitas))
            ->exists();
    }

    /**
     * Ambil konteks sebenarnya satu analisa dari N_EMI_LAB_Uji_Sampel.
     *
     * Request dari frontend tidak bisa dipercaya penuh untuk kolom-kolom ini:
     *   - No_Fak_Sub_Po kadang tidak dikirim, dan pernah dikirim sebagai
     *     placeholder tampilan "—" (em dash) alih-alih NULL.
     *   - Id_Session / Id_Pembanding (konteks palatabilitas) tidak pernah
     *     ikut dikirim sama sekali.
     *
     * Uji_Sampel adalah sumber kebenaran: di sanalah nilai-nilai itu ditulis
     * saat hasil uji disimpan. Nilai dari request hanya dipakai sebagai
     * cadangan bila barisnya tidak ditemukan.
     *
     * Aturan No_Sub_Sampel mengikuti Uji_Sampel apa adanya:
     *   - bukan multi QR  -> NULL
     *   - multi QR        -> No_Fak_Sub_Po (mis. FS0926-0002-1)
     *
     * @return array{No_Sub_Sampel: string|null, Tahapan_Ke: int, Id_Session: int|null, Id_Pembanding: int|null, Flag_Resampling: string|null}
     */
    private function konteksUjiSampel(string $noSampel, int $idJenis, array $data): array
    {
        $subDiminta = $this->bersihkanSubSampel($data['No_Sub_Sampel'] ?? null, $noSampel);

        $baris = DB::table('N_EMI_LAB_Uji_Sampel')
            ->where('No_Po_Sampel', $noSampel)
            ->where('Id_Jenis_Analisa', $idJenis)
            // Bila pemanggil menyebut sub-sampel tertentu (multi QR), pakai
            // baris itu; kalau tidak, ambil baris mana pun untuk analisa ini.
            ->when($subDiminta !== null, fn ($q) => $q->where('No_Fak_Sub_Po', $subDiminta))
            ->when(isset($data['Id_Pembanding']) && $data['Id_Pembanding'] !== null,
                fn ($q) => $q->where('Id_Pembanding', $data['Id_Pembanding']))
            ->orderByDesc('Tahapan_Ke')
            ->select('No_Fak_Sub_Po', 'Tahapan_Ke', 'Id_Session', 'Id_Pembanding',
                'Flag_Resampling', 'Flag_Multi_QrCode')
            ->first();

        if (!$baris) {
            return [
                'No_Sub_Sampel'   => $subDiminta,
                'Tahapan_Ke'      => $data['Tahapan_Ke'] ?? 1,
                'Id_Session'      => $data['Id_Session'] ?? null,
                'Id_Pembanding'   => $data['Id_Pembanding'] ?? null,
                'Flag_Resampling' => $data['Flag_Resampling'] ?? null,
            ];
        }

        return [
            'No_Sub_Sampel'   => $baris->No_Fak_Sub_Po,
            'Tahapan_Ke'      => $baris->Tahapan_Ke ?? ($data['Tahapan_Ke'] ?? 1),
            'Id_Session'      => $baris->Id_Session ?? ($data['Id_Session'] ?? null),
            'Id_Pembanding'   => $baris->Id_Pembanding ?? ($data['Id_Pembanding'] ?? null),
            'Flag_Resampling' => $baris->Flag_Resampling ?? ($data['Flag_Resampling'] ?? null),
        ];
    }

    /**
     * Bersihkan nilai sub-sampel dari request.
     *
     * Mengembalikan NULL untuk: nilai kosong, placeholder tampilan ("—", "-"),
     * dan nilai yang sama persis dengan No_Sampel — karena sampel tunggal
     * seharusnya ber-No_Sub_Sampel NULL, sama seperti di Uji_Sampel.
     */
    private function bersihkanSubSampel($nilai, string $noSampel): ?string
    {
        if ($nilai === null) {
            return null;
        }

        $nilai = trim((string) $nilai);

        if ($nilai === '' || $nilai === '-' || $nilai === '—' || $nilai === '–') {
            return null;
        }

        return $nilai === $noSampel ? null : $nilai;
    }

    /** Id header finalisasi milik sampel ini, bila sudah ada. */
    private function idHeaderSampel(string $noSampel): ?int
    {
        $id = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')
            ->where('No_Sampel', $noSampel)
            ->value('Id_Uji_Validasi_Final');

        return $id ? (int) $id : null;
    }

    // ----------------------------------------------------------------------
    // Pembantu internal — hasil di-cache per request agar tidak query ulang.
    // ----------------------------------------------------------------------

    /** @var array<int, object|null> */
    private array $cacheAnalisa = [];

    /** @var array<string, object|null> */
    private array $cachePo = [];

    /** @var array<string, object|null> */
    private array $cacheUser = [];

    private function masterAnalisa(int $idJenisAnalisa): ?object
    {
        if (!array_key_exists($idJenisAnalisa, $this->cacheAnalisa)) {
            $this->cacheAnalisa[$idJenisAnalisa] = DB::table('N_EMI_LAB_Jenis_Analisa as ja')
                ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as k',
                    'k.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab')
                ->where('ja.id', $idJenisAnalisa)
                ->select('ja.id', 'ja.Jenis_Analisa', 'ja.Kode_Aktivitas_Lab',
                    'ja.Flag_Perhitungan', 'k.Nama_Aktivitas')
                ->first();
        }

        return $this->cacheAnalisa[$idJenisAnalisa];
    }

    private function infoPo(string $noSampel): ?object
    {
        if (!array_key_exists($noSampel, $this->cachePo)) {
            $this->cachePo[$noSampel] = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $noSampel)
                ->select('No_Po', 'No_Split_Po', 'No_Batch', 'Kode_Barang',
                    'Flag_Trial_Produksi')
                ->first();
        }

        return $this->cachePo[$noSampel];
    }

    private function infoUser(string $idUser): ?object
    {
        if (!array_key_exists($idUser, $this->cacheUser)) {
            $this->cacheUser[$idUser] = DB::table('N_EMI_LAB_Users')
                ->where('UserId', $idUser)
                ->select('UserId', 'Nama')
                ->first();
        }

        return $this->cacheUser[$idUser];
    }
}
