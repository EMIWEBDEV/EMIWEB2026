<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckUserMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) {
            return abort(401, 'Anda Belum Login');
        }

        $userId = $user->UserId;
        $currentPath = '/' . ltrim($request->path(), '/');
        

        // Daftar mapping menu induk dan anak
        $menuGroups = [
            '/dashboard' => [
                '/api/v1/dashboard/current-hari-ini',
                '/api/v1/dashboard/current-all-time',
                '/api/v1/dashboard/grafik/jumlah-uji-perhari',
                '/api/v1/dashboard/grafik/pie-status-uji-sampel',
                '/api/v1/dashboard/grafik/frekuensi-uji-sampel-berdasarkan-jenis-analisa',
                '/api/v1/dashboard/grafik/scatter-sebaran-hasil'
            ],
            '/master-akun' => [
                '/api/v1/master-akun/current',
                '/master-akun/tambah-akun',
                '/proses-update/akun',
                '/api/v1/master-akun/status-akun/{UserId}',
                '/api/v1/master-akun/search'
            ],
            '/master/menu' => [
                '/api/v1/master-menu',
                '/api/v1/master-menu/store',
                '/api/v1/master-menu/current',
                '/api/v1/master-menu/search',
                '/api/v1/master-menu/update/{Id_Menu}',
            ],
            '/identity-ssidevo' => [
                '/identity-ssidevo/gaskeun',
                '/generate-key',
                '/data/identity-komputer',
                '/data-search/identity-komputer',
                '/api/v1/identity-ssidevo/{id}',
                
            ],
            '/master-mesin' => [
                '/api/v1/master-mesin/current',
                '/api/v1/master-mesin/search',
                '/api/v1/master-mesin/store',
                '/api/v1/divisi-mesin/by-update/{id}',
            ],
            '/biding-identity' => [
                '/biding-identity/create/mesin-identity',
                '/biding-identity/store',
                '/biding-identity/update/{id}',
                '/fetch/biding-identity',
                '/biding-identity/page/{id}'
            ],
            '/role/home-menu' => [
                '/api/v1/pengguna/current',
                '/role/menu/{UserId}',
                '/role/home-menu',
                '/api/v1/role-menu/current/{UserId}',
                '/api/v1/role-menu/search/{UserId}',
                '/api/v1/role-menu/home-current',
                '/api/v1/role-menu/store',
                '/api/v1/role-menu/update/{id_role_menu}',
            ],
            '/jenis-analisa' => [
                '/fetch/jenis-analisa-current',
                '/fetch/detail/jenis-analisa/{jenis_analisa}',
                '/jenis-analisa/create',
                '/jenis-analisa/store',
                '/jenis-analisa/detail/page-current/{jenis_analisa}',
                '/jenis-analisa/update/{id}'
            ],
            '/perhitungan-rumus' => [
                '/fetch/perhitungan-rumus',
                '/fetch/perhitungan-rumus/show/{id_jenis_analisa}',
                '/perhitungan-rumus/show/{id_jenis_analisa}',
                '/perhitungan/rumus/create',
                '/perhitungan/rumus/edit/{id}',
                '/perhitungan/rumus/store',
                '/perhitungan/rumus/update/{id}',
                '/search/perhitungan-rumus/show/{id_jenis_analisa}'
            ],
            '/binding-jenis-analisa' => [
                '/binding-jenis-analisa/create/form',
                '/binding-jenis-analisa/store',
                '/fetch/binding-jenis-analisa',
                '/binding-jenis-analisa/{id_jenis_analisa}',
                '/fetch/binding-jenis-analisa/detail/{id_jenis_analisa}',
                '/fetch/search/binding-jenis-analisa/{id_jenis_analisa}',
                '/binding-jenis-analisa/edit/form/{id}',
                '/binding-jenis-analisa/update/{id}',
                '/api/v1/binding-jenis-analisa/store',
                '/api/v1/binding-jenis-analisa/update/{id}'
            ],
            '/barang-jenis-analisa' => [
                '/barang-jenis/analisa/create',
                '/api/v1/barang-jenis-analisa/current',
                '/barang-jenis-analisa/show/{id}',
                '/api/v1/barang-jenis/analisa/store'
            ],
            '/lab/hasil-analisa' => [
                '/lab/hasil-analisa/{id_jenis_analisa}',
                '/lab/hasil-analisa/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}',
                '/lab/hasil-analisa/multi/{id_jenis_analisa}/{no_po_sampel}/{flag_multi}/{no_sub}',
                '/api/v1/lab/hasil-analisa/uji-sampel',
                '/rekap-sampel/pdf/particle-size',
                '/api/v2/rekap-sampel/pdf',
                '/api/v1/lab/hasil-analisa/no-multi/{id_jenis_analisa}/{no_po_sampel}'
            ],
            '/lab/home' => [
                '/lab/detail-data-sampel/{no_sampel}',
                '/api/v2/lab/detail-data-sampel/{no_sampel}',
                '/fetch/lab/{id_analisa}/parameter-perhitungan',
                '/api/v1/{no_po_sampel}/no-multi/{id_jenis_analisa}',
                '/api/v1/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}',
                '/api/v1/detail/{no_PO_Multiqr}/multi-print/{id_jenis_analisa}',
                '/api/v1/detail-split/{no_PO_Multiqr}/not-print/{id_jenis_analisa}'
            ],
            '/standar-hasil-analisa/current' => [
                '/standar-hasil-analisa/tambah',
                '/standar-hasil-analisa/store',
                '/api/v1/standar-rentang-analisa/current',
                '/api/v1/jenis-analisa/standar',
                '/api/v1/daftar-barang/standar/{id_jenis_analisa}',
                '/api/v1/list-mesin/standar/{id_jenis_analisa}',
                '/api/v1/list-kolom-perhitungan/standar/{id_jenis_analisa}'
            ],
            '/master-template-printer' => [
                '/api/v1/master-template-printer/current',
            ],
            '/master-template-printer-items' => [
                '/api/v1/master-template-printer/items/store',
            ],
            '/master-template-printer-transaksi' => [
                '/api/v1/master-template-printer/set-first',
                '/api/v1/master-template-printer/toggle', 
                '/api/v1/master-template-printer/current-template'
            ],
        ];

        // Ambil daftar parent menu yang dimiliki user dari DB
        $allowedParents = DB::table('N_EMI_LAB_Role_Menu as rm')
            ->join('N_EMI_LAB_Menus as m', 'rm.Id_Menu', '=', 'm.Id_Menu')
            ->where('rm.Id_User', $userId)
            ->pluck('m.Url_Menu')
            ->map(fn($url) => '/' . trim($url, '/'))
            ->toArray();

        // Kumpulkan semua path yang diizinkan dari parent yang dimiliki user
        $allowedPaths = [];
        foreach ($allowedParents as $parent) {
            $allowedPaths[] = $parent; // izinkan akses ke route induk
            if (isset($menuGroups[$parent])) {
                $allowedPaths = array_merge($allowedPaths, $menuGroups[$parent]);
            }
        }

        // Ubah path dinamis jadi regex-friendly
        $allowedRegex = collect($allowedPaths)->map(function ($path) {
            return '#^' . preg_replace('/\{[^\/]+\}/', '[^/]+', $path) . '$#';
        });

        // Cek akses
        $hasAccess = $allowedRegex->contains(function ($pattern) use ($currentPath) {
            return preg_match($pattern, $currentPath);
        });

        if (!$hasAccess) {
            return abort(403, 'Akses tidak diizinkan ke halaman ini.');
        }

        return $next($request);
    }
}
