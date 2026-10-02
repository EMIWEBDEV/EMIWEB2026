<?php

namespace App\Http\Controllers\VerifikasiHasilAnalisa;

use App\Http\Controllers\Controller;
use App\Services\CakupanMesinService;
use App\Services\LifecycleSampelService;
use App\Services\RincianHasilAnalisaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * VERIFIKASI HASIL ANALISA — step antara Validasi dan Finalisasi.
 *
 * Alur:   Uji Sampel -> Validasi Hasil Analisa -> [VERIFIKASI] -> Finalisasi
 *
 * Pada tahap ini penanggung jawab per klasifikasi aktivitas (Look View,
 * Analisa Lab, Palatabilitas) memberi REKOMENDASI atas hasil yang sudah
 * divalidasi analis, disertai catatan. Keputusan menerima atau menolak
 * sampel bukan di sini, melainkan pada tahap finalisasi — karena itu
 * tingkatannya: direkomendasikan, bersyarat, atau tidak direkomendasikan.
 *
 * MODUL INI TERISOLASI:
 *   - Hanya membaca tabel lama (Uji_Sampel, Jenis_Analisa, PO_Sampel, Users).
 *   - Hanya menulis ke tabel N_EMI_LAB_Verifikasi_* yang dibuat khusus.
 *   - Tidak mengubah Flag_Selesai, Flag_Final, maupun data validasi lain.
 *   - Rekomendasi hanya DICATAT; finalisasi tidak diblokir.
 *
 * Login memakai sesi terpisah ('verifikasi_user') agar tidak mengganggu
 * sesi aplikasi utama, sehingga bisa berganti user untuk pengujian.
 */
class VerifikasiHasilAnalisaController extends Controller
{

    private const T_HEADER  = 'N_EMI_LAB_Verifikasi_Header';
    private const T_DETAIL  = 'N_EMI_LAB_Verifikasi_Detail';
    private const T_RIWAYAT = 'N_EMI_LAB_Verifikasi_Riwayat';
    private const T_WENANG  = 'N_EMI_LAB_Verifikasi_Kewenangan';
    private const T_KEPUTUSAN = 'N_EMI_LAB_Verifikasi_Keputusan';

    /**
     * $rincian: penilaian kelayakan, resampling, penginput, dan validator —
     * dipakai bersama layar Finalisasi supaya keduanya menilai hasil dengan
     * cara yang sama.
     *
     * $cakupan: verifikasi hanya melayani sampel mesin AUTOCLAVE.
     *
     * $jejakSampel: Sample Lifecycle — dipakai juga oleh layar Finalisasi.
     */
    public function __construct(
        private RincianHasilAnalisaService $rincian,
        private CakupanMesinService $cakupan,
        private LifecycleSampelService $jejakSampel
    ) {
    }

    // ------------------------------------------------------------------
    // Halaman
    // ------------------------------------------------------------------

    /**
     * Halaman verifikasi — memakai layout aplikasi (Velzon) seperti menu lain.
     */
    public function index()
    {
        return inertia('vue/dashboard/verifikasi-hasil-analisa/VerifikasiHasilAnalisa');
    }

    /**
     * Workspace /trial-ui — halaman mandiri dengan sidebar tahapan sendiri
     * (Uji Sampel, Validasi, Verifikasi, Finalisasi), lepas dari layout dan
     * sesi aplikasi utama sehingga dapat dipakai untuk peragaan alur penuh.
     */

    /**
     * Ringkasan lintas tahapan untuk sidebar workspace: berapa sampel berada
     * di setiap tahap. Read-only terhadap tabel lama.
     */
    public function ringkasanTahapan()
    {
        try {
            $ujiSampel = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereNull('Flag_Selesai')
                ->count();

            $validasi = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('Flag_Selesai', 'Y')
                ->whereNull('Status')
                ->distinct()
                ->count('No_Po_Sampel');

            // Angka verifikasi mengikuti cakupan antreannya: AUTOCLAVE saja.
            $menungguVerifikasi = DB::table('N_EMI_LAB_Uji_Sampel as u')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'u.Id_Jenis_Analisa')
                ->join('N_EMI_LAB_PO_Sampel as p', 'p.No_Sampel', '=', 'u.No_Po_Sampel')
                ->where('u.Flag_Selesai', 'Y')
                ->whereNull('u.Status')
                ->tap(fn ($q) => $this->rincian->tanpaPutaranDitolak($q))
                ->tap(fn ($q) => $this->cakupan->batasi($q, 'p.Id_Mesin'))
                ->whereNotNull('ja.Kode_Aktivitas_Lab')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from(self::T_HEADER . ' as v')
                      ->whereColumn('v.No_Sampel', 'u.No_Po_Sampel')
                      ->whereColumn('v.Kode_Aktivitas_Lab', 'ja.Kode_Aktivitas_Lab');
                })
                ->distinct()
                ->count(DB::raw('CONCAT(u.No_Po_Sampel, ja.Kode_Aktivitas_Lab)'));

            $sudahVerifikasi = DB::table(self::T_HEADER)
                ->whereIn('Kode_Status', ['REKOMENDASI', 'REKOM_BERSYARAT',
                                          'TIDAK_REKOM'])
                ->tap(fn ($q) => $this->cakupan->batasi($q, 'Id_Mesin'))
                ->count();

            $finalisasi = DB::table('N_EMI_LAB_Hasil_Uji_Validasi_Final')->count();

            return response()->json([
                'success' => true,
                'result'  => [
                    ['kode' => 'UJI',  'nama' => 'Uji Sampel',   'jumlah' => $ujiSampel,
                     'ket' => 'sampel belum selesai'],
                    ['kode' => 'VAL',  'nama' => 'Validasi',     'jumlah' => $validasi,
                     'ket' => 'sampel tervalidasi'],
                    ['kode' => 'VER',  'nama' => 'Verifikasi',   'jumlah' => $menungguVerifikasi,
                     'ket' => 'menunggu keputusan', 'selesai' => $sudahVerifikasi],
                    ['kode' => 'FIN',  'nama' => 'Finalisasi',   'jumlah' => $finalisasi,
                     'ket' => 'sampel difinalisasi'],
                ],
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat ringkasan tahapan.');
        }
    }

    // ------------------------------------------------------------------
    // Profil verifikator
    // ------------------------------------------------------------------

    /**
     * Profil verifikator yang sedang login.
     *
     * Tidak ada pemilihan user: identitas mengikuti sesi login aplikasi,
     * dan kewenangan klasifikasi dibaca dari N_EMI_LAB_Verifikasi_Kewenangan
     * milik user tersebut.
     */
    /**
     * Master jenis keputusan verifikasi.
     *
     * Dibaca dari tabel, bukan di-hardcode, sehingga penambahan jenis
     * keputusan baru cukup lewat INSERT tanpa mengubah aplikasi.
     */
    public function masterKeputusan()
    {
        try {
            $rows = DB::table(self::T_KEPUTUSAN . ' as k')
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

    public function profil()
    {
        try {
            $sesi = $this->verifikatorAktif();

            if (!$sesi) {
                return response()->json([
                    'success' => false,
                    'status'  => 401,
                    'message' => 'Sesi tidak ditemukan. Silakan login kembali.',
                ], 401);
            }

            return response()->json(['success' => true, 'result' => $sesi], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat profil verifikator.');
        }
    }

    /**
     * Identitas verifikator aktif = user yang sedang login, lengkap dengan
     * kewenangan klasifikasinya. Mengembalikan null bila belum login.
     */
    private function verifikatorAktif(): ?array
    {
        if (!Auth::check()) {
            return null;
        }

        $userId = Auth::user()->UserId;

        $nama = DB::table('N_EMI_LAB_Users')
            ->where('UserId', $userId)
            ->value('Nama');

        $wenang = DB::table(self::T_WENANG . ' as k')
            ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl',
                'kl.Kode_Aktivitas_Lab', '=', 'k.Kode_Aktivitas_Lab')
            ->where('k.Id_User', $userId)
            ->where('k.Flag_Aktif', 'Y')
            ->select('k.Kode_Aktivitas_Lab', 'kl.Nama_Aktivitas', 'k.Id_Jenis_Analisa')
            ->get();

        return [
            'Id_User'   => $userId,
            'Nama'      => $nama ?: $userId,
            // Ditampilkan di layar agar jelas mengapa sampel mesin lain
            // tidak pernah muncul di antrean.
            'cakupan_mesin' => $this->cakupan->nama(),
            'aktivitas' => $wenang->groupBy('Kode_Aktivitas_Lab')
                ->map(fn ($g, $kode) => [
                    'Kode_Aktivitas_Lab' => $kode,
                    'Nama_Aktivitas'     => $g->first()->Nama_Aktivitas ?? $kode,
                    'Jumlah_Analisa'     => $g->whereNotNull('Id_Jenis_Analisa')->count(),
                    'Semua_Analisa'      => $g->whereNull('Id_Jenis_Analisa')->isNotEmpty(),
                ])->values()->all(),
        ];
    }

    // ------------------------------------------------------------------
    // Daftar kerja
    // ------------------------------------------------------------------

    /**
     * Daftar kerja verifikator: satu baris = satu (sampel x klasifikasi).
     *
     * Sengaja mengembalikan hasil analisanya sekaligus, sehingga verifikator
     * bisa langsung memutuskan tanpa klik tambahan.
     */
    public function daftarKerja(Request $request)
    {
        try {
            // Identitas verifikator diambil dari user yang LOGIN — tidak ada
            // pemilihan terpisah. Kewenangan klasifikasi ditentukan tabel
            // N_EMI_LAB_Verifikasi_Kewenangan untuk user tersebut.
            $sesi = $this->verifikatorAktif();

            if (!$sesi) {
                return response()->json([
                    'success' => false,
                    'status'  => 401,
                    'message' => 'Sesi tidak ditemukan. Silakan login kembali.',
                ], 401);
            }

            $userId = $sesi['Id_User'];
            $wenang = $this->kewenangan($userId);

            if ($wenang->isEmpty()) {
                // Dibedakan dari "tidak ada data": tanpa penanda ini, layar
                // hanya menampilkan nol tanpa menjelaskan bahwa penyebabnya
                // adalah kewenangan yang belum terdaftar.
                return response()->json([
                    'success' => true,
                    'result'  => ['data' => [], 'ringkasan' => $this->ringkasanKosong(),
                                  'pagination' => $this->paginasiKosong(),
                                  'tanpa_kewenangan' => true],
                ], 200);
            }

            $kodeAktivitas   = $wenang->pluck('Kode_Aktivitas_Lab')->unique()->values()->all();
            $analisaSpesifik = $wenang->whereNotNull('Id_Jenis_Analisa')
                ->pluck('Id_Jenis_Analisa')->unique()->values()->all();
            $aktivitasPenuh  = $wenang->whereNull('Id_Jenis_Analisa')
                ->pluck('Kode_Aktivitas_Lab')->unique()->values()->all();

            $filterStatus = $request->input('status');
            $cari         = $request->input('search');

            // Analisa yang sudah divalidasi dan masuk kewenangan user ini.
            // Nama barang diambil lewat subquery, bukan JOIN: N_EMI_View_Barang
            // menyimpan beberapa baris untuk satu Kode_Barang, sehingga JOIN
            // biasa akan menggandakan jumlah analisa berkali lipat.
            $uji = DB::table('N_EMI_LAB_Uji_Sampel as u')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'u.Id_Jenis_Analisa')
                ->join('N_EMI_LAB_PO_Sampel as p', 'p.No_Sampel', '=', 'u.No_Po_Sampel')
                ->leftJoin('N_EMI_LAB_Users as us', 'us.UserId', '=', 'u.Id_User')
                ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl',
                    'kl.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab')
                ->where('u.Flag_Selesai', 'Y')
                ->whereNull('u.Status')
                // Hanya hasil putaran yang berlaku; putaran yang ditolak
                // lewat resampling terbaca di Sample Lifecycle.
                ->tap(fn ($q) => $this->rincian->tanpaPutaranDitolak($q))
                // Verifikasi hanya untuk sampel mesin AUTOCLAVE.
                ->tap(fn ($q) => $this->cakupan->batasi($q, 'p.Id_Mesin'))
                ->whereIn('ja.Kode_Aktivitas_Lab', $kodeAktivitas)
                ->where(function ($q) use ($analisaSpesifik, $aktivitasPenuh) {
                    if (!empty($aktivitasPenuh)) {
                        $q->whereIn('ja.Kode_Aktivitas_Lab', $aktivitasPenuh);
                    }
                    if (!empty($analisaSpesifik)) {
                        $q->orWhereIn('u.Id_Jenis_Analisa', $analisaSpesifik);
                    }
                })
                ->when($cari, fn ($q) => $q->where(function ($w) use ($cari) {
                    $w->where('u.No_Po_Sampel', 'like', "%{$cari}%")
                      ->orWhere('p.No_Po', 'like', "%{$cari}%")
                      ->orWhere('p.Kode_Barang', 'like', "%{$cari}%");
                }))
                ->select(
                    'u.No_Po_Sampel', 'u.No_Fak_Sub_Po', 'u.No_Faktur', 'u.Id_Jenis_Analisa',
                    'u.Hasil', 'u.Nilai_Hasil_String', 'u.Range_Awal', 'u.Range_Akhir',
                    'u.Flag_Layak', 'u.Flag_Perhitungan', 'u.Tahapan_Ke',
                    'u.Id_Session', 'u.Id_Pembanding', 'u.Tanggal', 'u.Jam', 'u.Id_User',
                    'ja.Jenis_Analisa', 'ja.Kode_Analisa', 'ja.Kode_Aktivitas_Lab',
                    'p.No_Po', 'p.No_Split_Po', 'p.No_Batch', 'p.Kode_Barang',
                    'p.Id_Mesin', 'p.Flag_Trial_Produksi',
                    DB::raw('(SELECT TOP 1 b.Nama FROM N_EMI_View_Barang b
                              WHERE b.Kode_Barang = p.Kode_Barang) AS Nama_Barang'),
                    // Nomor PO dan batch kerap sama antar sampel; mesinlah
                    // yang membedakannya, sekaligus menentukan batas kelayakan
                    // mana yang berlaku. Diambil lewat subquery agar tidak
                    // menggandakan baris seperti JOIN.
                    DB::raw('(SELECT TOP 1 m.Nama_Mesin FROM EMI_Master_Mesin m
                              WHERE m.Id_Master_Mesin = p.Id_Mesin) AS Nama_Mesin'),
                    // Pemilik baris uji adalah PENGINPUT hasil; validatornya
                    // dibaca terpisah dari jejak validasi.
                    'us.Nama as Nama_User_Input',
                    'kl.Nama_Aktivitas'
                )
                ->orderByDesc('u.No_Po_Sampel')
                ->get();

            if ($uji->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'result'  => ['data' => [], 'ringkasan' => $this->ringkasanKosong(),
                                  'pagination' => $this->paginasiKosong()],
                ], 200);
            }

            // Keputusan yang sudah ada, untuk menempelkan statusnya.
            $keputusan = DB::table(self::T_HEADER)
                ->whereIn('No_Sampel', $uji->pluck('No_Po_Sampel')->unique()->values())
                ->get()
                ->keyBy(fn ($h) => $h->No_Sampel . '|' . $h->Kode_Aktivitas_Lab);

            // Tahap 1 — ringkas, untuk SELURUH antrean: cukup untuk angka
            // tab, saringan status, urutan, dan paginasi. Penilaian
            // kelayakan, resampling, dan jejak validasi jauh lebih mahal,
            // sehingga baru disusun untuk item yang tampil (tahap 2).
            $data = $uji
                ->groupBy(fn ($r) => $r->No_Po_Sampel . '|' . $r->Kode_Aktivitas_Lab)
                ->map(function ($rows, $key) use ($keputusan) {
                    $f = $rows->first();
                    $h = $keputusan->get($key);

                    return [
                        'kunci'               => $key,
                        'No_Sampel'           => $f->No_Po_Sampel,
                        'No_Po'               => $f->No_Po,
                        'No_Split_Po'         => $f->No_Split_Po,
                        'No_Batch'            => $f->No_Batch,
                        'Kode_Barang'         => $f->Kode_Barang,
                        'Nama_Barang'         => trim((string) $f->Nama_Barang) ?: '-',
                        'Id_Mesin'            => $f->Id_Mesin,
                        'Nama_Mesin'          => trim((string) $f->Nama_Mesin) ?: null,
                        'Flag_Trial_Produksi' => $f->Flag_Trial_Produksi,
                        'Kode_Aktivitas_Lab'  => $f->Kode_Aktivitas_Lab,
                        'Nama_Aktivitas'      => $f->Nama_Aktivitas ?? $this->labelAktivitas($f->Kode_Aktivitas_Lab),
                        // Dihitung per JENIS analisa, bukan per baris. Pada
                        // palatabilitas satu jenis analisa tersimpan sebagai
                        // beberapa baris (satu per parameter), sehingga
                        // menghitung baris akan melaporkan 21 untuk 3 analisa.
                        'Jumlah_Analisa'      => $f->Kode_Aktivitas_Lab === 'PLT'
                            ? $rows->pluck('Id_Jenis_Analisa')->unique()->count()
                            : $rows->count(),
                        'Kode_Status'         => $h->Kode_Status ?? 'MENUNGGU',
                        'Id_Verifikasi'       => $h->Id_Verifikasi ?? null,
                        'Catatan'             => $h->Catatan ?? null,
                        'Verifikator'         => $h->Nama_User ?? $h->Id_User ?? null,
                        'Tanggal_Keputusan'   => $h->Tanggal_Keputusan ?? null,
                        'Jam_Keputusan'       => $h->Jam_Keputusan ?? null,
                        'Revisi_Ke'           => $h->Revisi_Ke ?? 0,
                        '_rows'               => $rows,
                    ];
                })
                ->sortBy([['Kode_Status', 'asc'], ['No_Sampel', 'desc']])
                ->values();

            // Ringkasan dihitung dari SELURUH data, bukan hanya halaman yang
            // tampil, supaya angka pada kartu status tetap menggambarkan
            // keseluruhan beban kerja verifikator.
            $ringkasan = [
                'Total'     => $data->count(),
                'Menunggu'  => $data->where('Kode_Status', 'MENUNGGU')->count(),
                'Rekomendasi'  => $data->where('Kode_Status', 'REKOMENDASI')->count(),
                'Bersyarat'    => $data->where('Kode_Status', 'REKOM_BERSYARAT')->count(),
                'Tidak'        => $data->where('Kode_Status', 'TIDAK_REKOM')->count(),
            ];

            if ($filterStatus) {
                $data = $data->where('Kode_Status', $filterStatus)->values();
            }

            // Pagination
            $perPage = max(5, min(50, (int) $request->input('limit', 10)));
            $page    = max(1, (int) $request->input('page', 1));
            $total   = $data->count();
            $maksHal = max(1, (int) ceil($total / $perPage));
            $page    = min($page, $maksHal);

            // Tahap 2 — rincian lengkap, hanya untuk item di halaman ini.
            $halaman    = $data->forPage($page, $perPage)->values();
            $barisHal   = $halaman->flatMap(fn ($it) => $it['_rows']);
            $sampelHal  = $halaman->pluck('No_Sampel')->unique()->values()->all();
            $pembanding = $this->rincian->petaPembanding(
                $barisHal->pluck('Id_Pembanding')->filter()->unique()->all());
            $validasi   = $this->rincian->petaValidasi($sampelHal);
            $resampling = $this->rincian->petaResamplingBanyak($sampelHal);

            $halaman = $halaman->map(function ($it) use ($pembanding, $validasi, $resampling) {
                $rows = $it['_rows'];
                unset($it['_rows']);

                // Konteks evaluasi kelayakan: batas rentang di master
                // bergantung pada barang dan mesin sampel.
                $po = (object) [
                    'Kode_Barang' => $it['Kode_Barang'],
                    'Id_Mesin'    => $it['Id_Mesin'],
                ];

                return $it + $this->rincian->rincian(
                    $rows, $po, $resampling[$it['No_Sampel']] ?? [], $pembanding, $validasi);
            });

            return response()->json([
                'success' => true,
                'result'  => [
                    'data'      => $halaman,
                    'ringkasan' => $ringkasan,
                    'pagination' => [
                        'page'       => $page,
                        'limit'      => $perPage,
                        'total_data' => $total,
                        'total_page' => $maksHal,
                        'dari'       => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                        'sampai'     => min($page * $perPage, $total),
                    ],
                    'verifikator' => $sesi,
                ],
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat daftar kerja.');
        }
    }

    // ------------------------------------------------------------------
    // Keputusan
    // ------------------------------------------------------------------

    /**
     * Simpan keputusan untuk satu atau banyak (sampel x klasifikasi) sekaligus.
     *
     * Satu endpoint melayani aksi satuan maupun massal: bedanya hanya pada
     * jumlah item yang dikirim. Setiap keputusan menulis header, snapshot
     * detail, dan satu baris riwayat.
     */
    public function simpanKeputusan(Request $request)
    {
        $request->validate([
            'items'                        => 'required|array|min:1',
            'items.*.No_Sampel'            => 'required|string',
            'items.*.Kode_Aktivitas_Lab'   => 'required|string',
            'keputusan'                    => 'required|string',
            'catatan'                      => 'nullable|string|max:1000',
        ]);

        $sesi = $this->verifikatorAktif();

        if (!$sesi) {
            return response()->json([
                'success' => false, 'status' => 401,
                'message' => 'Sesi tidak ditemukan. Silakan login kembali.',
            ], 401);
        }

        // Aturan keputusan dibaca dari master, bukan dari kode program,
        // sehingga penambahan jenis keputusan tidak menuntut perubahan di sini.
        $kep = DB::table(self::T_KEPUTUSAN)
            ->where('Kode_Keputusan', $request->input('keputusan'))
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (!$kep) {
            return response()->json([
                'success' => false, 'status' => 422,
                'message' => 'Jenis keputusan tidak dikenali.',
            ], 422);
        }

        $catatan = trim((string) $request->input('catatan'));
        $minLen  = (int) ($kep->Panjang_Min_Catatan ?? 0);

        if ($kep->Flag_Wajib_Catatan === 'Y') {
            if ($catatan === '') {
                return response()->json([
                    'success' => false, 'status' => 422,
                    'message' => 'Keputusan "' . $kep->Nama_Keputusan
                        . '" wajib disertai catatan sebagai dasar keputusan.',
                ], 422);
            }

            if ($minLen > 0 && mb_strlen($catatan) < $minLen) {
                return response()->json([
                    'success' => false, 'status' => 422,
                    'message' => 'Catatan terlalu singkat. Jelaskan alasan dengan '
                        . 'sekurang-kurangnya ' . $minLen . ' karakter agar jejak audit bermakna.',
                ], 422);
            }
        }

        $status   = $kep->Kode_Status;
        $aksi     = $kep->Kode_Keputusan;
        $items    = $request->input('items');
        $sumber   = count($items) > 1 ? 'BULK' : 'SATUAN';
        $berhasil = [];
        $gagal    = [];

        DB::beginTransaction();

        try {
            $waktu     = DB::select('SELECT dbo.Get_Date_Time() as DateTimeNow');
            $dt        = $waktu[0]->DateTimeNow;
            $tanggal   = date('Y-m-d', strtotime($dt));
            $jam       = date('H:i:s', strtotime($dt));
            $userId    = $sesi['Id_User'];
            $namaUser  = $sesi['Nama'] ?? $userId;
            $wenang    = $this->kewenangan($userId);
            $mesin     = DB::table('N_EMI_LAB_PO_Sampel')
                ->whereIn('No_Sampel', collect($items)->pluck('No_Sampel')->unique()->values()->all())
                ->pluck('Id_Mesin', 'No_Sampel');

            foreach ($items as $item) {
                $noSampel = $item['No_Sampel'];
                $kodeAkt  = $item['Kode_Aktivitas_Lab'];

                // Kewenangan diperiksa ulang di server — tidak bergantung UI.
                $boleh = $wenang->where('Kode_Aktivitas_Lab', $kodeAkt)->isNotEmpty();

                if (!$boleh) {
                    $gagal[] = [
                        'sampel' => $noSampel,
                        'reason' => "Tidak berwenang atas klasifikasi {$kodeAkt}",
                    ];
                    continue;
                }

                // Begitu pula cakupan mesin: hanya sampel AUTOCLAVE.
                if (!$this->cakupan->mencakup($mesin[$noSampel] ?? null)) {
                    $gagal[] = [
                        'sampel' => $noSampel,
                        'reason' => 'Verifikasi hanya untuk sampel mesin '
                            . implode(', ', $this->cakupan->nama()),
                    ];
                    continue;
                }

                $analisa = $this->analisaSampel($noSampel, $kodeAkt, $wenang);

                if ($analisa->isEmpty()) {
                    $gagal[] = [
                        'sampel' => $noSampel,
                        'reason' => 'Tidak ada analisa tervalidasi pada klasifikasi ini',
                    ];
                    continue;
                }

                $idVerifikasi = $this->tulisKeputusan(
                    $noSampel, $kodeAkt, $analisa, $status, $catatan,
                    $userId, $namaUser, $tanggal, $jam, $aksi, $sumber,
                    $kep->Kode_Keputusan
                );

                $berhasil[] = [
                    'No_Sampel'          => $noSampel,
                    'Kode_Aktivitas_Lab' => $kodeAkt,
                    'Id_Verifikasi'      => $idVerifikasi,
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => count($berhasil) . ' item: ' . $kep->Nama_Keputusan
                    . (count($gagal) ? ' — ' . count($gagal) . ' gagal' : ''),
                'result'  => ['berhasil' => $berhasil, 'gagal' => $gagal],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->gagal($e, 'Gagal menyimpan keputusan verifikasi.');
        }
    }

    /**
     * Sample Lifecycle — perjalanan satu sampel dari registrasi sampai
     * finalisasi: uji sampel, validasi, resampling beserta putaran ulangnya,
     * verifikasi, dan finalisasi. Penyusunannya di LifecycleSampelService.
     *
     * Verifikator hanya melihat klasifikasi yang menjadi kewenangannya —
     * verifikator Analisa Lab tidak perlu disuguhi Look View yang bukan
     * tanggung jawabnya. Layar Finalisasi meminta ?semua=1 karena menilai
     * sampel secara utuh.
     *
     * Read-only terhadap seluruh tabel.
     */
    public function lifecycle($no_sampel, Request $request = null)
    {
        try {
            $sesi   = $this->verifikatorAktif();
            $wenang = $sesi
                ? collect($sesi['aktivitas'])->pluck('Kode_Aktivitas_Lab')->all()
                : [];
            $semua  = $request && $request->boolean('semua');

            $hasil = $this->jejakSampel->susun((string) $no_sampel, [
                'klasifikasi' => (!$semua && !empty($wenang)) ? $wenang : null,
            ]);

            if (!$hasil) {
                return response()->json([
                    'success' => false, 'status' => 404,
                    'message' => 'Sampel tidak ditemukan.',
                ], 404);
            }

            return response()->json(['success' => true, 'result' => $hasil], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat sample lifecycle.');
        }
    }

    /** Riwayat keputusan satu (sampel x klasifikasi). */
    public function riwayat($no_sampel, $kode_aktivitas)
    {
        try {
            $header = DB::table(self::T_HEADER)
                ->where('No_Sampel', $no_sampel)
                ->where('Kode_Aktivitas_Lab', $kode_aktivitas)
                ->first();

            $riwayat = $header
                ? DB::table(self::T_RIWAYAT)
                    ->where('Id_Verifikasi', $header->Id_Verifikasi)
                    ->orderByDesc('Id_Riwayat')
                    ->get()
                : collect();

            return response()->json([
                'success' => true,
                'result'  => ['header' => $header, 'riwayat' => $riwayat],
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat riwayat.');
        }
    }

    // ------------------------------------------------------------------
    // Pembantu internal
    // ------------------------------------------------------------------

    /**
     * Tulis header + snapshot detail + satu baris riwayat.
     *
     * Header bersifat upsert: keputusan yang diubah menaikkan Revisi_Ke dan
     * menambah baris riwayat baru, tanpa menghapus jejak sebelumnya.
     */
    private function tulisKeputusan(
        string $noSampel, string $kodeAkt, $analisa, string $status,
        string $catatan, string $userId, string $namaUser,
        string $tanggal, string $jam, string $aksi, string $sumber,
        ?string $kodeKeputusan = null
    ): int {
        $f  = $analisa->first();
        // Subquery, bukan JOIN — lihat catatan di daftarKerja().
        $po = DB::table('N_EMI_LAB_PO_Sampel as p')
            ->where('p.No_Sampel', $noSampel)
            ->select('p.No_Po', 'p.No_Split_Po', 'p.No_Batch', 'p.Kode_Barang',
                'p.Id_Mesin', 'p.Flag_Trial_Produksi',
                DB::raw('(SELECT TOP 1 b.Nama FROM N_EMI_View_Barang b
                          WHERE b.Kode_Barang = p.Kode_Barang) AS Nama_Barang'),
                DB::raw('(SELECT TOP 1 m.Nama_Mesin FROM EMI_Master_Mesin m
                          WHERE m.Id_Master_Mesin = p.Id_Mesin) AS Nama_Mesin'))
            ->first();

        // Palatabilitas menyimpan satu baris per parameter, sehingga jumlah
        // baris bukan jumlah analisa. Yang dicatat sebagai jejak audit adalah
        // jumlah JENIS analisanya, sama dengan yang tampil di layar.
        $jumlahAnalisa = $kodeAkt === 'PLT'
            ? $analisa->pluck('Id_Jenis_Analisa')->unique()->count()
            : $analisa->count();

        // Kelayakan dinilai ulang dari master agar angka yang tersimpan
        // sebagai jejak audit sejalan dengan yang dilihat verifikator di
        // layar, bukan mengikuti Flag_Layak lama pada baris uji.
        $evaluasi = $analisa->mapWithKeys(fn ($r) => [
            $this->rincian->kunciBaris($r) => $this->rincian->evaluasiKelayakan($r, $po),
        ]);

        $lama = DB::table(self::T_HEADER)
            ->where('No_Sampel', $noSampel)
            ->where('Kode_Aktivitas_Lab', $kodeAkt)
            ->first();

        $payload = [
            'No_Sampel'           => $noSampel,
            'No_Sub_Sampel'       => null,   // keputusan berlaku untuk seluruh sampel
            'No_Po'               => $po->No_Po ?? null,
            'No_Split_Po'         => $po->No_Split_Po ?? null,
            'No_Batch'            => $po->No_Batch ?? null,
            'Kode_Barang'         => $po->Kode_Barang ?? null,
            'Nama_Barang'         => trim((string) ($po->Nama_Barang ?? '')) ?: null,
            'Id_Mesin'            => $po->Id_Mesin ?? null,
            // Nama disalin, bukan hanya id, supaya jejak audit tetap terbaca
            // bila master mesin berubah di kemudian hari.
            'Nama_Mesin'          => trim((string) ($po->Nama_Mesin ?? '')) ?: null,
            'Flag_Trial_Produksi' => $po->Flag_Trial_Produksi ?? null,
            'Kode_Aktivitas_Lab'  => $kodeAkt,
            'Nama_Aktivitas'      => $f->Nama_Aktivitas ?? $this->labelAktivitas($kodeAkt),
            'Kode_Status'         => $status,
            'Kode_Keputusan'      => $kodeKeputusan,
            'Catatan'             => $catatan !== '' ? $catatan : null,
            'Id_User'             => $userId,
            'Nama_User'           => $namaUser,
            'Tanggal_Keputusan'   => $tanggal,
            'Jam_Keputusan'       => $jam,
            'Jumlah_Analisa'      => $jumlahAnalisa,
            'Jumlah_Tidak_Layak'  => $evaluasi->where('layak', 'T')->count(),
            'Diubah_Pada'         => now(),
        ];

        if ($lama) {
            $payload['Revisi_Ke'] = ((int) $lama->Revisi_Ke) + 1;

            DB::table(self::T_HEADER)
                ->where('Id_Verifikasi', $lama->Id_Verifikasi)
                ->update($payload);

            $idVerifikasi = (int) $lama->Id_Verifikasi;

            // Snapshot lama dibuang, diganti kondisi saat keputusan terbaru.
            DB::table(self::T_DETAIL)->where('Id_Verifikasi', $idVerifikasi)->delete();
        } else {
            $payload['Revisi_Ke']   = 0;
            $payload['Dibuat_Pada'] = now();

            $idVerifikasi = (int) DB::table(self::T_HEADER)->insertGetId($payload);
        }

        // Validator tiap analisa dari jejak validasi — sumber yang sama dengan
        // tampilan. Sebelumnya kolom *_Validasi diisi Id_User baris uji, yang
        // sebenarnya PENGINPUT hasil.
        $petaValidasi = $this->rincian->petaValidasi([$noSampel]);
        $validator = $analisa->mapWithKeys(fn ($r) => [
            $this->rincian->kunciBaris($r) => $this->rincian->validatorBaris($r, $petaValidasi),
        ]);

        $detail = $analisa->map(fn ($r) => [
            'Id_Verifikasi'      => $idVerifikasi,
            'Id_Jenis_Analisa'   => $r->Id_Jenis_Analisa,
            'Nama_Jenis_Analisa' => $r->Jenis_Analisa,
            'Kode_Analisa'       => $r->Kode_Analisa,
            'No_Sub_Sampel'      => $r->No_Fak_Sub_Po,
            'Tahapan_Ke'         => $r->Tahapan_Ke,
            'Hasil'              => $r->Hasil,
            'Nilai_Hasil_String' => $r->Nilai_Hasil_String,
            'Range_Awal'         => $r->Range_Awal,
            'Range_Akhir'        => $r->Range_Akhir,
            // 'N' = belum dapat dinilai (master tidak ada), bukan 'Y'.
            'Flag_Layak'         => $evaluasi[$this->rincian->kunciBaris($r)]['layak'],
            'Flag_Perhitungan'   => $r->Flag_Perhitungan,
            'Id_Session'         => $r->Id_Session,
            'Id_Pembanding'      => $r->Id_Pembanding,
            // NULL bila validatornya tidak tercatat di jejak mana pun.
            'Id_User_Validasi'   => $validator[$this->rincian->kunciBaris($r)]['id'] ?? null,
            'Nama_User_Validasi' => $validator[$this->rincian->kunciBaris($r)]['nama'] ?? null,
            'Tanggal_Validasi'   => !empty($validator[$this->rincian->kunciBaris($r)]['tanggal'])
                ? substr((string) $validator[$this->rincian->kunciBaris($r)]['tanggal'], 0, 10) : null,
            'Jam_Validasi'       => !empty($validator[$this->rincian->kunciBaris($r)]['jam'])
                ? substr((string) $validator[$this->rincian->kunciBaris($r)]['jam'], 0, 8) : null,
            'Kode_Status'        => $status,
            'Dibuat_Pada'        => now(),
        ])->all();

        foreach (array_chunk($detail, 500) as $bagian) {
            DB::table(self::T_DETAIL)->insert($bagian);
        }

        DB::table(self::T_RIWAYAT)->insert([
            'Id_Verifikasi'      => $idVerifikasi,
            'No_Sampel'          => $noSampel,
            'Kode_Aktivitas_Lab' => $kodeAkt,
            'Status_Sebelum'     => $lama->Kode_Status ?? 'MENUNGGU',
            'Status_Sesudah'     => $status,
            'Kode_Keputusan'     => $kodeKeputusan,
            'Aksi'               => $lama ? 'REVISI' : $aksi,
            'Catatan'            => $catatan !== '' ? $catatan : null,
            'Id_User'            => $userId,
            'Nama_User'          => $namaUser,
            'Tanggal'            => $tanggal,
            'Jam'                => $jam,
            'Dibuat_Pada'        => now(),
            'Sumber_Aksi'        => $sumber,
            'Jumlah_Analisa'     => $jumlahAnalisa,
        ]);

        return $idVerifikasi;
    }

    /** Analisa tervalidasi pada satu (sampel x klasifikasi), dibatasi kewenangan. */
    private function analisaSampel(string $noSampel, string $kodeAkt, $wenang)
    {
        $spesifik = $wenang->where('Kode_Aktivitas_Lab', $kodeAkt)
            ->whereNotNull('Id_Jenis_Analisa')->pluck('Id_Jenis_Analisa')->all();
        $penuh = $wenang->where('Kode_Aktivitas_Lab', $kodeAkt)
            ->whereNull('Id_Jenis_Analisa')->isNotEmpty();

        return DB::table('N_EMI_LAB_Uji_Sampel as u')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ja.id', '=', 'u.Id_Jenis_Analisa')
            ->leftJoin('N_EMI_LAB_Users as us', 'us.UserId', '=', 'u.Id_User')
            ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl',
                'kl.Kode_Aktivitas_Lab', '=', 'ja.Kode_Aktivitas_Lab')
            ->where('u.No_Po_Sampel', $noSampel)
            ->where('u.Flag_Selesai', 'Y')
            ->whereNull('u.Status')
            ->tap(fn ($q) => $this->rincian->tanpaPutaranDitolak($q))
            ->where('ja.Kode_Aktivitas_Lab', $kodeAkt)
            ->when(!$penuh && !empty($spesifik),
                fn ($q) => $q->whereIn('u.Id_Jenis_Analisa', $spesifik))
            ->select(
                'u.Id_Jenis_Analisa', 'u.No_Fak_Sub_Po', 'u.Tahapan_Ke',
                'u.Hasil', 'u.Nilai_Hasil_String', 'u.Range_Awal', 'u.Range_Akhir',
                'u.Flag_Layak', 'u.Flag_Perhitungan', 'u.Id_Session', 'u.Id_Pembanding',
                'u.No_Po_Sampel', 'u.No_Faktur', 'u.Tanggal', 'u.Jam', 'u.Id_User',
                'ja.Jenis_Analisa', 'ja.Kode_Analisa',
                'us.Nama as Nama_User_Input', 'kl.Nama_Aktivitas'
            )
            ->get();
    }

    /** @return \Illuminate\Support\Collection */
    private function kewenangan(string $userId)
    {
        return DB::table(self::T_WENANG)
            ->where('Id_User', $userId)
            ->where('Flag_Aktif', 'Y')
            ->select('Kode_Aktivitas_Lab', 'Id_Jenis_Analisa', 'Flag_Approve', 'Flag_Reject')
            ->get();
    }

    private function labelAktivitas(?string $kode): string
    {
        return [
            'ANL'  => 'Analisa Lab',
            'PLT'  => 'Uji Palatabilitas',
            'LCKV' => 'Look View',
        ][$kode] ?? ($kode ?: 'Aktivitas');
    }

    private function ringkasanKosong(): array
    {
        return ['Total' => 0, 'Menunggu' => 0, 'Rekomendasi' => 0,
                'Bersyarat' => 0, 'Tidak' => 0];
    }

    private function paginasiKosong(): array
    {
        return ['page' => 1, 'limit' => 10, 'total_data' => 0,
                'total_page' => 1, 'dari' => 0, 'sampai' => 0];
    }

    private function gagal(\Exception $e, string $pesan)
    {
        Log::error(__CLASS__ . ': ' . $e->getMessage(), [
            'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);

        return response()->json([
            'success' => false,
            'status'  => 500,
            'message' => $pesan,
        ], 500);
    }
}
