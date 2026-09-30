<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gerbang akses Panel Bypass LIMS.
 *
 * Panel ini berjalan di luar alur login normal, jadi satu-satunya kunci
 * adalah token 64 karakter di env (BYPASS_LIMS_SECRET). Token boleh
 * dikirim lewat query ?gembok-secret=..., header X-Gembok-Secret, atau
 * diambil dari session setelah verifikasi pertama berhasil.
 */
class BypassLimsGate
{
    public const SESSION_KEY = 'bypass_lims_unlocked';

    public function handle(Request $request, Closure $next)
    {
        $secret = (string) env('BYPASS_LIMS_SECRET', '');

        // Tanpa konfigurasi env, panel dianggap tidak ada sama sekali.
        if ($secret === '') {
            abort(404);
        }

        $diberikan = (string) ($request->query('gembok-secret')
            ?? $request->header('X-Gembok-Secret')
            ?? '');

        if ($diberikan !== '' && hash_equals($secret, $diberikan)) {
            $request->session()->put(self::SESSION_KEY, hash('sha256', $secret));

            return $next($request);
        }

        // Sudah pernah membuka gembok di sesi ini.
        if (hash_equals(
            (string) $request->session()->get(self::SESSION_KEY, ''),
            hash('sha256', $secret)
        )) {
            return $next($request);
        }

        Log::warning('Percobaan akses Panel Bypass LIMS ditolak', [
            'ip'         => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path'       => $request->path(),
        ]);

        abort(404);
    }
}
