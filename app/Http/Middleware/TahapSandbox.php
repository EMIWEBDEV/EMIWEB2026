<?php

namespace App\Http\Middleware;

use App\Http\Controllers\VerifikasiHasilAnalisa\TrialUiController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga halaman sandbox per tahap alur: UJI, VAL, VER, FIN.
 *
 *   Route::middleware('sandbox.tahap:VAL')
 *
 * Tahap 'SEMUA' hanya mensyaratkan sesi sandbox (mis. Hasil Analisa mockup).
 *
 * Syaratnya dua:
 *   1. Sesi dibentuk dari /trial-ui (penanda 'sandbox_trial'). Sesi login
 *      biasa diarahkan ke modul lama — modul pembaharuan tidak pernah
 *      menggantikannya di luar sandbox.
 *   2. Tahap itu milik user (TrialUiController::tahapanUntuk). Mis. vengine
 *      hanya mendapat Finalisasi, sehingga Validasi ditolak untuknya.
 */
class TahapSandbox
{
    /** Modul lama pengganti tiap tahap, untuk sesi login biasa. */
    private const MODUL_LAMA = [
        'VAL' => '/validasi-trial/produksi',
        'VER' => '/verifikasi-hasil-analisa',
        'FIN' => '/finalisai/trial-produksi',
    ];

    private const NAMA_TAHAP = [
        'UJI' => 'Uji Sampel',
        'VAL' => 'Validasi',
        'VER' => 'Verifikasi',
        'FIN' => 'Finalisasi',
    ];

    public function handle(Request $request, Closure $next, string $tahap): Response
    {
        $api = $request->expectsJson() || $request->is('api/*');

        if ($request->session()?->get('sandbox_trial') !== true) {
            return $api
                ? response()->json([
                    'success' => false,
                    'message' => 'Halaman ini hanya tersedia dari sesi sandbox (/trial-ui).',
                ], 403)
                : redirect(self::MODUL_LAMA[$tahap] ?? '/');
        }

        if ($tahap !== 'SEMUA'
            && !in_array($tahap, TrialUiController::tahapanUntuk(Auth::user()?->UserId), true)) {
            $pesan = (self::NAMA_TAHAP[$tahap] ?? $tahap) . ' sandbox tidak tersedia untuk akun ini.';

            if ($api) {
                return response()->json(['success' => false, 'message' => $pesan], 403);
            }

            abort(403, $pesan);
        }

        return $next($request);
    }
}
