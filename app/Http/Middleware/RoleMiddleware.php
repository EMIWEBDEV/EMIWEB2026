<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$allowedRoles)
    {
        // cek login
        if (!Auth::check()) {
            return redirect('/login')->with('error', 'Silahkan login terlebih dahulu');
        }

        $userId = Auth::user()->UserId;

        // ambil SEMUA role user (bukan first)
        $userRoles = DB::table('N_EMI_LAB_User_Roles as ur')
            ->join('N_EMI_LAB_Roles as r', 'ur.Id_Role', '=', 'r.Id_Role')
            ->where('ur.Id_User', $userId)
            ->where('r.Flag_Aktif', 'Y')
            ->pluck('r.Kode_Role') // ambil semua role
            ->toArray();

        // jika user tidak punya role sama sekali
        if (empty($userRoles)) {
            abort(403, 'User tidak memiliki role');
        }

        // cek apakah salah satu role user cocok dengan allowedRoles
        $isAllowed = count(array_intersect($userRoles, $allowedRoles)) > 0;

        if (!$isAllowed) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini');
        }

        return $next($request);
    }
}
