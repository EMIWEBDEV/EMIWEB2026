<?php

namespace App\Http\Controllers\VerifikasiHasilAnalisa;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UjiSampelController;
use App\Services\CakupanMesinService;
use App\Services\IdentitasPoService;
use App\Services\RincianHasilAnalisaService;
use App\Services\SampelDummyService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Vinkla\Hashids\Facades\Hashids;

/**
 * VALIDASI TRIAL PRODUKSI — modul pembaharuan (sandbox).
 *
 * Tahap pertama dari tiga layar sandbox: Validasi -> Verifikasi ->
 * Finalisasi. Hanya dibuka dari sesi /trial-ui oleh pelaksana (ratna, jati,
 * roby); vengine ditolak (middleware 'sandbox.tahap:VAL').
 *
 * SAMA DENGAN MODUL LAMA (/validasi-trial/produksi):
 *   - Antrean: baris uji yang sudah dikirim analis dan belum divalidasi
 *     (Status NULL, Flag_Selesai NULL, Status_Keputusan_Sampel 'menunggu'),
 *     PO trial produksi.
 *   - Hak: jenis analisa pada hak konten "Validasi Trial Produksi" user.
 *   - Penyimpanan: fungsi validasi modul lama apa adanya
 *     (UjiSampelController::storeConfirmedUjiSampelV2) — aturan
 *     validasinya persis sama, tidak ada salinan logika. Uji ulang memakai
 *     endpoint resampling modul lama langsung dari layar.
 *
 * BEDANYA — mengikuti layar Verifikasi:
 *   - Antrean per SAMPEL + KLASIFIKASI, bukan per jenis analisa; seluruh
 *     analisa yang menunggu tampil dalam satu tabel.
 *   - Tabel hasil sama dengan Verifikasi & Finalisasi (RincianHasilAnalisa):
 *     kriteria kelayakan, dasar penilaiannya, dan tanda "belum ada di
 *     master" — plus parameter perhitungan untuk diperiksa validator.
 *   - Hanya sampel mesin AUTOCLAVE, supaya satu siklus bersambung.
 *   - Contoh data (database demo saja) untuk mencoba satu siklus.
 *
 * Modul lama tidak diubah sama sekali.
 */
class ValidasiSandboxController extends Controller
{
    /** Nama menu hak konten yang dipakai modul lama. */
    private const HAK_KONTEN = 'Validasi Trial Produksi';

    public function __construct(
        private CakupanMesinService $cakupan,
        private IdentitasPoService $identitas,
        private RincianHasilAnalisaService $rincian,
        private SampelDummyService $dummy
    ) {
    }

    public function index()
    {
        return inertia('vue/dashboard/verifikasi-hasil-analisa/ValidasiSandbox');
    }

    // ------------------------------------------------------------------
    // Antrean
    // ------------------------------------------------------------------

    /**
     * Antrean validasi: satu baris per sampel + klasifikasi.
     *
     * Parameter: q, page, limit, tanggal_mulai, tanggal_selesai,
     * qrcode (multi|single), status (lolos|tidak_lolos).
     *
     * Antrean yang menunggu validasi selalu kecil, jadi seluruhnya diambil
     * sekali lalu disaring dan dipotong per halaman di sini — dengan begitu
     * status lolos dapat dihitung dari penilaian yang SAMA dengan tabel
     * (bukan dari Flag_Layak tersimpan).
     */
    public function daftar(Request $request)
    {
        try {
            $limit     = max(5, min(50, (int) $request->input('limit', 12)));
            $idAnalisa = $this->analisaDiizinkan();

            $meta = [
                'hak'           => $this->ringkasHak($idAnalisa),
                'cakupan_mesin' => $this->cakupan->nama(),
                'dummy'         => $this->dummy->status() + ['skenario' => SampelDummyService::skenario()],
            ];

            if (empty($idAnalisa)) {
                return $this->hasil(collect(), $limit, 1, 0, $meta + [
                    'pesan' => 'Akun ini belum memiliki hak validasi jenis analisa apa pun.',
                ]);
            }

            $semua = $this->kelompokkan($this->barisMenunggu($idAnalisa)->get());
            $total = $semua->count();
            $data  = $this->saring($semua, $request)->values();

            $hal = max(1, (int) $request->input('page', 1));
            $hal = min($hal, max(1, (int) ceil($data->count() / $limit)));

            return $this->hasil($data, $limit, $hal, $total, $meta);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat antrean validasi.');
        }
    }

    /**
     * Rincian satu sampel + klasifikasi: tabel hasil (sama dengan
     * Verifikasi), parameter perhitungan, foto, sub sampel tiap analisa,
     * dan siapa pengambil keputusan berikutnya.
     */
    public function rincian(Request $request)
    {
        $request->validate(['no_sampel' => 'required|string', 'klasifikasi' => 'required|string']);

        try {
            $noSampel = $request->input('no_sampel');
            $kode     = $request->input('klasifikasi');

            $rows = $this->barisMenunggu($this->analisaDiizinkan())
                ->where('u.No_Po_Sampel', $noSampel)
                ->where('ja.Kode_Aktivitas_Lab', $kode)
                ->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada analisa yang menunggu validasi pada sampel ini.',
                ], 404);
            }

            $f  = $rows->first();
            $po = (object) ['Kode_Barang' => $f->Kode_Barang, 'Id_Mesin' => $f->Id_Mesin];
            $this->rincian->siapkanMaster($rows->pluck('Id_Jenis_Analisa')->all(), $po);

            $r = $this->rincian->rincian(
                $rows,
                $po,
                $this->rincian->petaResampling($noSampel),
                $this->rincian->petaPembanding($rows->pluck('Id_Pembanding')->filter()->unique()->all()),
                $this->rincian->petaValidasi([$noSampel])
            );

            $foto = $this->rincian->petaFoto($rows->pluck('No_Faktur')->all());
            $r['analisa'] = $r['analisa']
                ->map(fn ($a) => $a + ['Foto' => $foto[$a['No_Faktur']] ?? []])
                ->values();

            // Palatabilitas dinilai komparatif — angka saja, tanpa layak /
            // tidak layak — jadi tidak ada yang "belum dapat dinilai".
            if ($r['Butuh_Pembanding']) {
                $r['Jumlah_Tidak_Layak'] = 0;
                $r['Jumlah_Tanpa_Master'] = 0;
            }

            return response()->json([
                'success' => true,
                'result'  => $r + [
                    'parameter' => $this->rincian->parameterPerhitungan($rows),
                    // Untuk jendela validasi & uji ulang: per jenis analisa.
                    'daftar'    => $this->daftarAnalisa($rows),
                    'keputusan' => $this->pengambilKeputusan($kode, $rows),
                ],
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat rincian hasil uji.');
        }
    }

    /**
     * Simpan validasi.
     *
     * items: [{ No_Sampel, Kode_Aktivitas_Lab, analisa?: [id ter-hash],
     *           sub?: { id ter-hash: No_Fak_Sub_Po } }]
     *
     * Baris yang divalidasi dibaca ulang di server — hanya yang masih
     * menunggu, dalam hak user dan cakupan mesin — lalu diserahkan ke fungsi
     * validasi modul lama dalam SATU permintaan (satu transaksi).
     */
    public function validasi(Request $request)
    {
        $request->validate(['items' => 'required|array|min:1']);

        try {
            $izin    = $this->analisaDiizinkan();
            $payload = [];
            $jumlah  = 0;

            foreach ($request->input('items') as $it) {
                $pilih = collect($it['analisa'] ?? [])
                    ->map(fn ($h) => Hashids::connection('custom')->decode((string) $h)[0] ?? null)
                    ->filter()->map(fn ($x) => (int) $x)->all();
                $sub = collect($it['sub'] ?? [])
                    ->mapWithKeys(fn ($v, $h) => [(int) (Hashids::connection('custom')->decode((string) $h)[0] ?? 0) => $v])
                    ->filter()->all();

                $rows = $this->barisMenunggu($pilih ? array_values(array_intersect($izin, $pilih)) : $izin)
                    ->where('u.No_Po_Sampel', (string) ($it['No_Sampel'] ?? ''))
                    ->where('ja.Kode_Aktivitas_Lab', (string) ($it['Kode_Aktivitas_Lab'] ?? ''))
                    ->get();

                foreach ($rows->groupBy('Id_Jenis_Analisa') as $idJa => $g) {
                    $subDipilih = $sub[(int) $idJa] ?? null;
                    $g = $subDipilih ? $g->where('No_Fak_Sub_Po', $subDipilih) : $g;
                    if ($g->isEmpty()) continue;
                    $jumlah++;

                    // Satu entri per sub sampel — bentuk yang sama dengan
                    // tombol "Konfirmasi & Simpan" modul lama.
                    foreach ($g->groupBy(fn ($x) => (string) $x->No_Fak_Sub_Po) as $baris) {
                        $a = $baris->first();
                        $payload[] = [
                            'No_Po_Sampel'      => $a->No_Po_Sampel,
                            'No_Fak_Sub_Po'     => $a->No_Fak_Sub_Po,
                            'Id_Jenis_Analisa'  => (int) $a->Id_Jenis_Analisa,
                            'Id_Mesin'          => $a->Id_Mesin,
                            'Flag_Multi_QrCode' => $a->Flag_Multi_QrCode,
                            'Tahapan_Ke'        => (int) $a->Tahapan_Ke,
                        ];
                    }
                }
            }

            if (empty($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada analisa yang menunggu validasi — mungkin sudah divalidasi dari layar lain.',
                ], 422);
            }

            $res = app(UjiSampelController::class)->storeConfirmedUjiSampelV2(
                Request::create('/api/v2/uji-sampel/confirmed', 'POST', ['analyses' => $payload]));

            if ($res->getStatusCode() !== 200) {
                return $res;
            }

            return response()->json([
                'success' => true,
                'message' => $jumlah . ' analisa divalidasi dan diteruskan ke verifikasi.',
                'result'  => ['jumlah' => $jumlah],
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal menyimpan validasi.');
        }
    }

    /** Buat contoh data siap validasi (database demo saja). */
    public function buatDummy(Request $request)
    {
        try {
            $skenario = (string) $request->input('skenario', 'semua');
            $hasil = $skenario === 'semua'
                ? $this->dummy->buatSemua()
                : [$this->dummy->buat($skenario)];

            $nomor = collect($hasil)->pluck('No_Sampel')->implode(', ');

            return response()->json([
                'success' => true,
                'message' => count($hasil) === 1
                    ? "Contoh {$hasil[0]['judul']} dibuat: {$nomor}."
                    : count($hasil) . " contoh dibuat: {$nomor}.",
                'result'  => $hasil,
            ], 200);

        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal membuat contoh data.');
        }
    }

    /** Hapus seluruh contoh data (database demo saja). */
    public function hapusDummy()
    {
        try {
            $hapus = $this->dummy->hapusSemua();

            return response()->json([
                'success' => true,
                'message' => $hapus
                    ? count($hapus) . ' contoh dihapus: ' . implode(', ', $hapus) . '.'
                    : 'Tidak ada contoh data untuk dihapus.',
                'result'  => $hapus,
            ], 200);

        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal menghapus contoh data.');
        }
    }

    // ------------------------------------------------------------------
    // Pembantu
    // ------------------------------------------------------------------

    /** Jenis analisa yang boleh divalidasi user — sama dengan modul lama. */
    private function analisaDiizinkan(): array
    {
        $konten = Session::get('user_permissions')['permission_konten'][self::HAK_KONTEN] ?? [];

        return collect(is_array($konten) ? $konten : [])
            ->filter(fn ($a) => ($a['flag'] ?? null) === 'Y' && isset($a['id_jenis_analisa']))
            ->pluck('id_jenis_analisa')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** Hak validasi user per klasifikasi, untuk ditampilkan. */
    private function ringkasHak(array $idAnalisa): array
    {
        if (empty($idAnalisa)) {
            return [];
        }

        return DB::table('N_EMI_LAB_Jenis_Analisa as ja')
            ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl',
                'kl.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab')
            ->whereIn('ja.id', $idAnalisa)
            ->groupBy('ja.Kode_Aktivitas_Lab', 'kl.Nama_Aktivitas', 'kl.Urutan')
            ->orderBy('kl.Urutan')
            ->select('ja.Kode_Aktivitas_Lab', 'kl.Nama_Aktivitas', DB::raw('COUNT(*) as Jumlah_Analisa'))
            ->get()
            ->map(fn ($k) => [
                'kode'   => $k->Kode_Aktivitas_Lab,
                'nama'   => $k->Nama_Aktivitas ?? $k->Kode_Aktivitas_Lab,
                'jumlah' => (int) $k->Jumlah_Analisa,
            ])
            ->all();
    }

    /**
     * Baris uji yang menunggu validasi — kriteria modul lama + cakupan mesin.
     * Kolomnya sama dengan daftar kerja verifikasi, supaya dapat langsung
     * disusun RincianHasilAnalisaService.
     */
    private function barisMenunggu(array $idAnalisa)
    {
        return DB::table('N_EMI_LAB_Uji_Sampel as u')
            ->join('N_EMI_LAB_PO_Sampel as p', 'p.No_Sampel', '=', 'u.No_Po_Sampel')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'u.Id_Jenis_Analisa')
            ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl',
                'kl.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab')
            ->leftJoin('N_EMI_LAB_Users as us', 'us.UserId', '=', 'u.Id_User')
            ->leftJoin('EMI_Master_Mesin as m', 'm.Id_Master_Mesin', '=', 'p.Id_Mesin')
            ->whereIn('u.Id_Jenis_Analisa', $idAnalisa ?: [0])
            ->whereNull('u.Status')
            ->whereNull('u.Flag_Selesai')
            ->where('u.Status_Keputusan_Sampel', 'menunggu')
            ->where('p.Flag_Trial_Produksi', 'Y')
            ->tap(fn ($q) => $this->cakupan->batasi($q, 'p.Id_Mesin'))
            ->select(
                'u.No_Po_Sampel', 'u.No_Fak_Sub_Po', 'u.No_Faktur', 'u.Id_Jenis_Analisa',
                'u.Hasil', 'u.Nilai_Hasil_String', 'u.Range_Awal', 'u.Range_Akhir',
                'u.Flag_Layak', 'u.Flag_Perhitungan', 'u.Tahapan_Ke', 'u.Flag_Multi_QrCode',
                'u.Id_Session', 'u.Id_Pembanding', 'u.Tanggal', 'u.Jam', 'u.Id_User',
                'us.Nama as Nama_User_Input',
                'ja.Jenis_Analisa', 'ja.Kode_Analisa', 'ja.Kode_Aktivitas_Lab',
                'kl.Nama_Aktivitas', 'kl.Urutan',
                'p.No_Po', 'p.No_Split_Po', 'p.No_Batch', 'p.Kode_Barang', 'p.Id_Mesin',
                'p.Tanggal as Tanggal_Daftar', 'p.Jam as Jam_Daftar', 'm.Nama_Mesin'
            )
            // Urutan baris dipertahankan: baris palatabilitas dipasangkan ke
            // parameternya menurut urutan.
            ->orderByDesc('u.No_Po_Sampel');
    }

    /** Satu baris antrean per sampel + klasifikasi, lengkap dengan penilaiannya. */
    private function kelompokkan(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        // Master kelayakan dimuat sekali per barang + mesin.
        foreach ($rows->groupBy(fn ($r) => $r->Kode_Barang . '|' . $r->Id_Mesin) as $g) {
            $f = $g->first();
            $this->rincian->siapkanMaster($g->pluck('Id_Jenis_Analisa')->all(),
                (object) ['Kode_Barang' => $f->Kode_Barang, 'Id_Mesin' => $f->Id_Mesin]);
        }

        return $rows->groupBy(fn ($r) => $r->No_Po_Sampel . '|' . $r->Kode_Aktivitas_Lab)
            ->map(function ($g) {
                $f   = $g->first();
                $po  = (object) ['Kode_Barang' => $f->Kode_Barang, 'Id_Mesin' => $f->Id_Mesin];
                $plt = $f->Kode_Aktivitas_Lab === 'PLT';

                // Penilaian sama dengan tabel: tidak layak / belum ada di
                // master dihitung per jenis analisa. Palatabilitas dinilai
                // komparatif, jadi tidak dihitung.
                $nilai = $g->groupBy('Id_Jenis_Analisa')->map(function ($a) use ($po) {
                    $ev = $a->map(fn ($r) => $this->rincian->evaluasiKelayakan($r, $po)['layak']);
                    return $ev->contains('T') ? 'T' : ($ev->contains('N') ? 'N' : 'Y');
                });
                $tidak   = $plt ? 0 : $nilai->filter(fn ($x) => $x === 'T')->count();
                $tanpa   = $plt ? 0 : $nilai->filter(fn ($x) => $x === 'N')->count();
                // Nama analisanya, supaya kartu antrean menyebut yang mana.
                $nama    = $g->pluck('Jenis_Analisa', 'Id_Jenis_Analisa');
                $namaDgn = fn (string $v) => $plt ? [] : $nilai->filter(fn ($x) => $x === $v)
                    ->keys()->map(fn ($id) => $nama[$id] ?? $id)->values()->all();
                $terakhir = $g->sortByDesc(fn ($r) => substr((string) $r->Tanggal, 0, 10) . ' ' . $r->Jam)->first();

                return [
                    'No_Sampel'          => $f->No_Po_Sampel,
                    'Kode_Aktivitas_Lab' => $f->Kode_Aktivitas_Lab,
                    'Nama_Aktivitas'     => $f->Nama_Aktivitas ?? $f->Kode_Aktivitas_Lab,
                    'Urutan'             => $f->Urutan ?? 99,
                    'Jumlah_Analisa'     => $nilai->count(),
                    'Jumlah_Tidak_Layak' => $tidak,
                    'Jumlah_Tanpa_Master'=> $tanpa,
                    'Analisa_Tidak_Layak'=> $namaDgn('T'),
                    'Analisa_Tanpa_Master' => $namaDgn('N'),
                    'Butuh_Pembanding'   => $plt,
                    'Status_Sampel'      => $tidak > 0 ? 'Tidak Lolos Uji' : 'Lolos Uji',
                    'Putaran'            => (int) $g->max('Tahapan_Ke'),
                    'Multi'              => $g->contains('Flag_Multi_QrCode', 'Y'),
                    'Tanggal'            => $terakhir->Tanggal,
                    'Jam'                => $terakhir->Jam,
                    'Penguji'            => $g->map(fn ($r) => trim((string) $r->Nama_User_Input) ?: $r->Id_User)
                        ->filter()->unique()->values()->all(),
                    'Analisa'            => $g->pluck('Jenis_Analisa')->unique()->values()->all(),
                    'Tanggal_Uji'        => $g->map(fn ($r) => substr((string) $r->Tanggal, 0, 10))->unique()->values()->all(),
                    'No_Po'              => $f->No_Po,
                    'No_Split_Po'        => $f->No_Split_Po,
                    'No_Batch'           => $f->No_Batch,
                    'Kode_Barang'        => trim((string) $f->Kode_Barang),
                    'Nama_Barang'        => $this->identitas->namaBarang($f->Kode_Barang),
                    'Kode_Formula'       => $this->identitas->formula($f->No_Po),
                    'Nama_Mesin'         => trim((string) $f->Nama_Mesin) ?: null,
                    'Tanggal_Registrasi' => $f->Tanggal_Daftar,
                    'Jam_Registrasi'     => $f->Jam_Daftar,
                ];
            })
            ->sortBy(fn ($x) => [$x['Tanggal'], $x['Jam'], $x['No_Sampel']])
            ->reverse()
            ->values();
    }

    /** Pencarian & saringan — sama dengan modul lama, pencarian diperluas. */
    private function saring(Collection $data, Request $request): Collection
    {
        $cari = trim((string) $request->input('q', ''));
        if ($cari !== '') {
            $data = $data->filter(fn ($x) => IdentitasPoService::cocok($cari, array_merge([
                $x['No_Sampel'], $x['No_Po'], $x['No_Split_Po'], $x['No_Batch'], $x['Kode_Barang'],
                $x['Nama_Barang'], $x['Kode_Formula'], $x['Nama_Aktivitas'], $x['Kode_Aktivitas_Lab'],
            ], $x['Analisa'])));
        }

        // Tanggal uji: cukup salah satu analisa yang diuji dalam rentang.
        $mulai   = $request->input('tanggal_mulai');
        $selesai = $request->input('tanggal_selesai');
        if ($mulai && $selesai) {
            $data = $data->filter(fn ($x) => collect($x['Tanggal_Uji'])
                ->contains(fn ($t) => $t >= $mulai && $t <= $selesai));
        }

        $qr = $request->input('qrcode');
        if ($qr === 'multi')  $data = $data->filter(fn ($x) => $x['Multi']);
        if ($qr === 'single') $data = $data->filter(fn ($x) => !$x['Multi']);

        $status = $request->input('status');
        if ($status === 'lolos')       $data = $data->filter(fn ($x) => $x['Jumlah_Tidak_Layak'] === 0);
        if ($status === 'tidak_lolos') $data = $data->filter(fn ($x) => $x['Jumlah_Tidak_Layak'] > 0);

        return $data;
    }

    /** Ringkas tiap jenis analisa: untuk jendela validasi & uji ulang. */
    private function daftarAnalisa(Collection $rows): array
    {
        $po = (object) ['Kode_Barang' => $rows->first()->Kode_Barang, 'Id_Mesin' => $rows->first()->Id_Mesin];

        return $rows->groupBy('Id_Jenis_Analisa')->map(function ($g, $id) use ($po) {
            $a  = $g->first();
            $ev = $g->map(fn ($r) => $this->rincian->evaluasiKelayakan($r, $po)['layak']);

            return [
                'id'     => Hashids::connection('custom')->encode((int) $id),
                'nama'   => $a->Jenis_Analisa,
                'kode'   => trim((string) $a->Kode_Analisa),
                'multi'  => $a->Flag_Multi_QrCode === 'Y',
                'sub'    => $g->pluck('No_Fak_Sub_Po')->filter()->unique()->values()->all(),
                'layak'  => $a->Kode_Aktivitas_Lab === 'PLT' ? null
                    : ($ev->contains('T') ? 'T' : ($ev->contains('N') ? 'N' : 'Y')),
                'putaran'=> (int) $g->max('Tahapan_Ke'),
            ];
        })->values()->all();
    }

    /**
     * Pengambil keputusan tahap berikutnya: verifikator klasifikasi ini dan
     * pemegang finalisasi. Di sandbox, finalisasi dipegang user bertahap FIN.
     */
    private function pengambilKeputusan(string $kode, Collection $rows): array
    {
        $fin = TrialUiController::penggunaTahap('FIN');
        $nama = DB::table('N_EMI_LAB_Users')
            ->whereIn(DB::raw('LOWER(UserId)'), $fin ?: ['-'])
            ->where(fn ($q) => $q->whereNull('Flag_Aktif')->orWhere('Flag_Aktif', 'Y'))
            ->select('UserId', 'Nama')
            ->get()
            ->map(fn ($u) => ['id' => $u->UserId, 'nama' => trim((string) $u->Nama) ?: $u->UserId])
            ->values()
            ->all();

        return [
            'verifikasi' => $this->rincian->petugasVerifikasi($kode, $rows->pluck('Id_Jenis_Analisa')->all()),
            'finalisasi' => $nama,
        ];
    }

    private function hasil(Collection $data, int $limit, int $hal, int $total, array $meta)
    {
        $jumlah = $data->count();

        return response()->json([
            'success' => true,
            'result'  => array_merge([
                'data'          => $data->forPage($hal, $limit)->values(),
                'pagination'    => [
                    'page'      => $hal,
                    'limit'     => $limit,
                    'totalPage' => max(1, (int) ceil($jumlah / $limit)),
                    'totalData' => $jumlah,
                ],
                'total_antrean' => $total,
            ], $meta),
        ], 200);
    }

    private function gagal(\Exception $e, string $pesan)
    {
        Log::error(__CLASS__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

        return response()->json(['success' => false, 'message' => $pesan], 500);
    }
}
