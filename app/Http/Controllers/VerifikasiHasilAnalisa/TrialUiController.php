<?php

namespace App\Http\Controllers\VerifikasiHasilAnalisa;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * TRIAL UI — ruang peragaan alur LIMS dari ujung ke ujung.
 *
 *   Uji Sampel  ->  Validasi  ->  Verifikasi  ->  Finalisasi
 *
 * Halaman ini TIDAK memerlukan login aplikasi. Pengguna memilih salah satu
 * verifikator (ratna / jati / roby), lalu sesi login asli dibentuk di sini
 * dengan cara yang sama persis seperti AuthController::login(): Auth::login(),
 * User_Roles, dan user_permissions. Akibatnya seluruh controller lama berjalan
 * apa adanya tanpa satu baris pun diubah, dan keempat tahapan tampil identik
 * dengan menu aslinya.
 *
 * Keluar dari sandbox memakai logout yang sama, sehingga sesi benar-benar
 * dibersihkan seperti logout biasa.
 *
 * ISOLASI: kelas ini hanya MEMBACA tabel hak akses dan MEMBENTUK sesi.
 * Tidak ada satu pun tabel transaksi yang diubah dari sini.
 */
class TrialUiController extends Controller
{
    /**
     * Pembagian peran di sandbox.
     *
     *   ratna / jati / roby  -> Uji Sampel, Validasi, Verifikasi
     *                           (Validasi & Verifikasi versi sandbox)
     *   roby                 -> + REV: hak merevisi rekomendasi verifikasi
     *                           yang sudah tercatat. Tanpa REV, rekomendasi
     *                           yang sudah diberikan terkunci.
     *   vengine              -> Finalisasi saja, atas data yang sudah
     *                           diinput dan disetujui ketiga user di atas
     *
     * Pemisahan ini membuat alur terasa nyata: pekerjaan mengalir dari
     * pelaksana ke penanggung jawab rilis, bukan dikerjakan satu orang.
     */
    private const TAHAP_PELAKSANA = ['UJI', 'VAL', 'VER'];
    private const TAHAP_FINALISASI = ['FIN'];

    private const PERAN = [
        'ratna'   => self::TAHAP_PELAKSANA,
        'jati'    => self::TAHAP_PELAKSANA,
        'roby'    => ['UJI', 'VAL', 'VER', 'REV'],
        'vengine' => self::TAHAP_FINALISASI,
    ];

    /** User yang boleh dipakai di sandbox. */
    private const USER_SANDBOX = ['ratna', 'jati', 'roby', 'vengine'];

    /**
     * Halaman sandbox.
     *
     * Bila sesi sudah terbentuk, halaman kerangka langsung ditampilkan;
     * bila belum, kerangka tetap tampil dan pemilihan user dilakukan dari UI.
     */
    /**
     * Pintu masuk sandbox.
     *
     * Bila sesi belum ada, tampilkan pemilih user memakai root view ringan
     * (layout aplikasi butuh Auth::user() pada topbar/sidebar, jadi belum
     * bisa dipakai di titik ini).
     *
     * Begitu sesi terbentuk, pengguna dialihkan ke halaman tahap pertama
     * miliknya — memakai LAYOUT APLIKASI YANG ASLI, lengkap dengan sidebar
     * dan topbar. Dengan begitu tampilannya 100% sama dengan LIMS biasa.
     */
    public function index()
    {
        // Membuka /trial-ui selalu menampilkan pemilih user, meski sesi masih
        // aktif — itulah cara berpindah akun. Sesi lama dibersihkan lebih dulu
        // supaya identitas tidak tercampur.
        if (Auth::check()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        Inertia::setRootView('trial-ui');

        try {
            return inertia('vue/dashboard/verifikasi-hasil-analisa/TrialUi', [
                'sesi' => null,
            ])->toResponse(request());
        } finally {
            Inertia::setRootView('app');
        }
    }

    /**
     * Tahap sandbox milik user: UJI, VAL, VER, dan/atau FIN.
     *
     * Dipakai middleware 'sandbox.tahap' untuk menjaga halaman sandbox per
     * tahap — mis. Validasi hanya untuk pelaksana, tidak untuk vengine.
     */
    public static function tahapanUntuk(?string $userId): array
    {
        return self::PERAN[strtolower((string) $userId)] ?? [];
    }

    /**
     * User sandbox yang memegang satu tahap, mis. 'FIN' -> ['vengine'].
     * Dipakai untuk menyebut siapa pengambil keputusan tahap berikutnya.
     */
    public static function penggunaTahap(string $tahap): array
    {
        return array_keys(array_filter(self::PERAN, fn ($t) => in_array($tahap, $t, true)));
    }

    /** Halaman tahap pertama sesuai peran user. */
    public static function urlTahapPertama(string $userId): string
    {
        $tahap = self::tahapanUntuk($userId);
        $awal  = $tahap[0] ?? 'UJI';

        // Sandbox kini dimulai dari mockup siklus: pelaksana di Validasi
        // (contoh data dibuat di sana), vengine di Finalisasi.
        return [
            'UJI' => '/mockup/validasi',
            'VAL' => '/mockup/validasi',
            'VER' => '/mockup/verifikasi',
            'FIN' => '/mockup/finalisasi',
        ][$awal] ?? '/mockup/hasil-analisa';
    }

    /** Daftar user sandbox beserta kewenangan verifikasinya. */
    public function daftarUser()
    {
        try {
            $users = DB::table('N_EMI_LAB_Users')
                ->whereIn(DB::raw('LOWER(UserId)'), self::USER_SANDBOX)
                ->select('UserId', 'Nama', 'Flag_Aktif')
                ->get();

            $wenang = DB::table('N_EMI_LAB_Verifikasi_Kewenangan as k')
                ->leftJoin('N_EMI_LIMS_Klasifikasi_Aktivitas_Lab as kl',
                    'kl.Kode_Aktivitas_Lab', '=', 'k.Kode_Aktivitas_Lab')
                ->where('k.Flag_Aktif', 'Y')
                ->select('k.Id_User', 'k.Kode_Aktivitas_Lab', 'kl.Nama_Aktivitas',
                    'k.Id_Jenis_Analisa')
                ->get()
                ->groupBy(fn ($x) => strtolower($x->Id_User));

            // Menu yang dimiliki tiap user — supaya terlihat tahap apa saja
            // yang bisa dipakai user tersebut di sandbox.
            $menu = DB::table('N_EMI_LAB_Page_Access_2 as pa')
                ->join('N_EMI_LAB_Menus as m', 'm.Id_Menu', '=', 'pa.Id_Menu')
                ->whereIn(DB::raw('LOWER(pa.Id_User)'), self::USER_SANDBOX)
                ->whereIn('m.Url_Menu', [
                    '/lab/home', '/validasi-trial/produksi',
                    '/verifikasi-hasil-analisa', '/finalisai/trial-produksi',
                ])
                ->select('pa.Id_User', 'm.Nama_Menu', 'm.Url_Menu')
                ->get()
                ->groupBy(fn ($x) => strtolower($x->Id_User));

            $hasil = $users->map(function ($u) use ($wenang, $menu) {
                $kunci = strtolower($u->UserId);
                $w     = $wenang->get($kunci, collect());
                $m     = $menu->get($kunci, collect());

                return [
                    'Id_User'   => $u->UserId,
                    'Nama'      => $u->Nama,
                    'tahapan'   => self::PERAN[$kunci] ?? [],
                    'peran'     => ($kunci === 'vengine')
                        ? 'Finalisasi hasil yang sudah diverifikasi'
                        : 'Uji sampel, validasi, dan verifikasi',
                    'menu'      => $m->pluck('Nama_Menu')->unique()->values(),
                    'aktivitas' => $w->groupBy('Kode_Aktivitas_Lab')
                        ->map(fn ($g, $kode) => [
                            'Kode_Aktivitas_Lab' => $kode,
                            'Nama_Aktivitas'     => $g->first()->Nama_Aktivitas ?? $kode,
                            'Jumlah_Analisa'     => $g->whereNotNull('Id_Jenis_Analisa')->count(),
                            'Semua_Analisa'      => $g->whereNull('Id_Jenis_Analisa')->isNotEmpty(),
                        ])->values(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'result'  => $hasil,
                'aktif'   => $this->infoSesi(),
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat daftar user sandbox.');
        }
    }

    /**
     * Masuk sebagai salah satu user sandbox.
     *
     * Membentuk sesi dengan urutan yang sama seperti AuthController::login()
     * agar seluruh modul lama menemukan data yang diharapkannya.
     */
    public function masuk(Request $request)
    {
        $request->validate(['Id_User' => 'required|string']);

        $idUser = $request->input('Id_User');

        if (!in_array(strtolower($idUser), self::USER_SANDBOX, true)) {
            return response()->json([
                'success' => false,
                'message' => 'User ini tidak tersedia untuk sandbox.',
            ], 403);
        }

        $user = User::where('UserId', $idUser)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "Akun \"{$idUser}\" tidak ditemukan.",
            ], 404);
        }

        try {
            Auth::login($user);
            $request->session()->regenerate();

            $this->bangunSesiHakAkses($request, $user->UserId);

            // Penanda sandbox. Sesi yang dibentuk dari /trial-ui ditandai di
            // sini supaya tampilan dapat mengarahkan menu ke modul
            // pembaharuan tanpa mengubah rute maupun perilaku modul lama.
            // Sesi login biasa tidak memiliki kunci ini, sehingga jalur lama
            // tetap berjalan apa adanya.
            $request->session()->put('sandbox_trial', true);

            $request->session()->save();

            return response()->json([
                'success'  => true,
                'message'  => 'Masuk sebagai ' . ($user->Nama ?: $user->UserId),
                'result'   => $this->infoSesi(),
                // Frontend mengarahkan ke sini: halaman asli dengan layout
                // aplikasi lengkap (sidebar + topbar), bukan halaman sandbox.
                'redirect' => self::urlTahapPertama($user->UserId),
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal membentuk sesi sandbox.');
        }
    }

    /** Keluar: membersihkan sesi seperti logout biasa. */
    public function keluar(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Sesi sandbox diakhiri.',
        ], 200);
    }

    /** Status sesi sekarang — dipakai UI untuk menandai siapa yang aktif. */
    public function sesi()
    {
        return response()->json([
            'success' => true,
            'result'  => $this->infoSesi(),
        ], 200);
    }

    // ------------------------------------------------------------------
    // Pembantu
    // ------------------------------------------------------------------

    /**
     * Susun User_Roles dan user_permissions persis seperti AuthController.
     *
     * Disalin apa adanya (bukan memanggil AuthController) agar perubahan di
     * sini tidak pernah berisiko memengaruhi alur login aplikasi yang asli.
     */
    private function bangunSesiHakAkses(Request $request, string $userId): void
    {
        $userRoles = DB::table('N_EMI_LAB_User_Roles as ur')
            ->join('N_EMI_LAB_Roles as r', 'ur.Id_Role', '=', 'r.Id_Role')
            ->where('ur.Id_User', $userId)
            ->get(['r.Id_Role', 'r.Kode_Role', 'r.Nama_Role', 'r.Deskripsi'])
            ->toArray();

        $request->session()->put('User_Roles', $userRoles);

        $userAccess = DB::table('N_EMI_LAB_Page_Access_2 as pa')
            ->join('N_EMI_LAB_Role_Menu_Access as rma', 'rma.Id_Page_Access', '=', 'pa.Id_Page_Access')
            ->join('N_EMI_LAB_Klasifikasi_Aksi as ka', 'ka.Id_Klasifikasi_Actions', '=', 'rma.Id_Aksi')
            ->leftJoin('N_EMI_LAB_Menus as m', 'm.Id_Menu', '=', 'pa.Id_Menu')
            ->where('pa.Id_User', $userId)
            ->where('rma.Flag_Diizinkan', 'Y')
            ->select('pa.Id_Page_Access', 'm.Nama_Menu as Jenis_page', 'ka.Nama_Aksi',
                'm.Nama_Menu', 'm.Nama_Menu as Nama_Header', 'm.Icon_Menu',
                'm.Url_Menu', 'pa.Urutan_Menu')
            ->orderBy('pa.Urutan_Menu', 'ASC')
            ->get();

        $permissions      = [];
        $permissionLabels = [];
        $pageAccessIds    = [];

        foreach ($userAccess as $access) {
            $page          = $access->Jenis_page;
            $permissionKey = str_replace(' ', '_', $page);
            $url           = Str::start($access->Url_Menu ?? '', '/');

            if (!isset($permissions[$permissionKey])) {
                $permissions[$permissionKey] = [];
            }
            if (!in_array($access->Nama_Aksi, $permissions[$permissionKey])) {
                $permissions[$permissionKey][] = $access->Nama_Aksi;
            }

            if (!isset($permissionLabels[$page])) {
                $permissionLabels[$page] = [
                    'nama_menu'   => $access->Nama_Menu,
                    'nama_header' => $access->Nama_Header,
                    'icon'        => $access->Icon_Menu,
                    'url'         => $url,
                ];
            }

            if (!in_array($access->Id_Page_Access, $pageAccessIds)) {
                $pageAccessIds[] = $access->Id_Page_Access;
            }
        }

        $permissionKonten = [];

        if (!empty($pageAccessIds)) {
            $userKontenAccess = DB::table('N_EMI_LAB_Role_Konten_Access as rka')
                ->join('N_EMI_LAB_Page_Access_2 as pa', 'rka.Id_Page_Access', '=', 'pa.Id_Page_Access')
                ->leftJoin('N_EMI_LAB_Menus as m', 'm.Id_Menu', '=', 'pa.Id_Menu')
                ->leftJoin('N_EMI_LAB_Jenis_Analisa as ja', 'rka.Id_Jenis_Analisa', '=', 'ja.id')
                ->whereIn('rka.Id_Page_Access', $pageAccessIds)
                ->where('rka.Flag_Diizinkan', 'Y')
                ->select('m.Nama_Menu as Jenis_page', 'rka.Id_Jenis_Analisa',
                    'rka.Kategori', 'rka.Flag_Diizinkan',
                    'ja.Jenis_Analisa as Nama_Analisa')
                ->get();

            foreach ($userKontenAccess as $konten) {
                $page = $konten->Jenis_page;

                if (!isset($permissionKonten[$page])) {
                    $permissionKonten[$page] = [];
                }

                if ($konten->Id_Jenis_Analisa) {
                    $permissionKonten[$page][] = [
                        'id_jenis_analisa' => $konten->Id_Jenis_Analisa,
                        'nama_analisa'     => $konten->Nama_Analisa ?? null,
                        'flag'             => $konten->Flag_Diizinkan,
                    ];
                } elseif ($konten->Kategori) {
                    $permissionKonten[$page][] = [
                        'kategori' => $konten->Kategori,
                        'flag'     => $konten->Flag_Diizinkan,
                    ];
                }
            }
        }

        $user = User::where('UserId', $userId)->first();

        Session::put('user_permissions', [
            'id'                => $userId,
            'username'          => $userId,
            'nama'              => $user->Nama ?? null,
            'permissions'       => $permissions,
            'permission_label'  => $permissionLabels,
            'permission_konten' => $permissionKonten,
        ]);
    }

    private function infoSesi(): ?array
    {
        if (!Auth::check()) {
            return null;
        }

        $p     = Session::get('user_permissions');
        $id    = Auth::user()->UserId;
        $kunci = strtolower($id);

        return [
            'Id_User' => $id,
            'Nama'    => $p['nama'] ?? $id,
            'menu'    => array_keys($p['permission_label'] ?? []),
            // Dibaca dari penanda sesi, bukan dari daftar nama user: akun
            // yang sama bisa login lewat jalur biasa, dan sesi itu bukan
            // sandbox meski namanya terdaftar di sini.
            'sandbox' => Session::get('sandbox_trial', false) === true,
            // Tahap yang boleh dibuka user ini; UI hanya menampilkan tab-nya.
            'tahapan' => self::PERAN[$kunci] ?? [],
            'peran'   => ($kunci === 'vengine')
                ? 'Finalisasi hasil yang sudah diverifikasi'
                : 'Uji sampel, validasi, dan verifikasi',
        ];
    }

    private function gagal(\Exception $e, string $pesan)
    {
        Log::error(__CLASS__ . ': ' . $e->getMessage(), [
            'file' => $e->getFile(), 'line' => $e->getLine(),
        ]);

        return response()->json(['success' => false, 'message' => $pesan], 500);
    }
}
