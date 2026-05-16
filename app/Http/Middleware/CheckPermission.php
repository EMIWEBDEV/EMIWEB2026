<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permissionKey, string $requiredAction): Response
    {

        if (!Auth::check()) {
            return $request->expectsJson()
                        ? response()->json(['message' => 'Akses ditolak. Anda belum login.'], 401)
                        : redirect()->route('login.form'); 
        }

        $permissions = Session::get('user_permissions', []);
        $userPermissionsForMenu = Arr::get($permissions, 'permissions.' . $permissionKey, []);

    

        if (!in_array($requiredAction, $userPermissionsForMenu)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Anda tidak memiliki hak akses untuk melakukan tindakan ini.'], 403);
            }
            
            abort(403, 'ANDA TIDAK MEMILIKI HAK AKSES.');
        }

        return $next($request);
    }
}