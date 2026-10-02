<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Mengalihkan sesi sandbox (/trial-ui) ke modul pembaharuan.
 *
 * Mengandalkan pengalihan tautan di sidebar saja tidak cukup: URL modul
 * lama masih dapat dibuka dari riwayat peramban, penanda, halaman yang
 * terlanjur dirender, atau ketikan langsung. Middleware ini menutup celah
 * itu di lapisan permintaan.
 *
 * Hanya berlaku pada sesi bertanda 'sandbox_trial'. Sesi login biasa tidak
 * tersentuh sama sekali, sehingga modul lama berjalan apa adanya.
 */
class AlihkanSesiSandbox
{
    /**
     * Pemetaan URL modul lama ke penggantinya.
     *
     * Kunci dicocokkan terhadap awal path, sehingga URL berparameter
     * pelacakan (?uid=…&trace_id=…) tetap tertangkap.
     */
    private const PETA = [
        'validasi-trial/produksi'  => '/mockup/validasi',
        'finalisai/trial-produksi' => '/mockup/finalisasi',
        'lab/hasil-analisa'        => '/mockup/hasil-analisa',
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->session()?->get('sandbox_trial') !== true) {
            return $next($request);
        }

        $path = trim($request->path(), '/');

        foreach (self::PETA as $lama => $baru) {
            // Cocokkan persis atau sebagai awalan ruas path, supaya
            // '/finalisai/trial-produksi-lain' tidak ikut teralih.
            if ($path === $lama || str_starts_with($path, $lama . '/')) {
                return redirect($baru);
            }
        }

        return $next($request);
    }
}
