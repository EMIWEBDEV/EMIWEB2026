<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // Penanda sesi sandbox (/trial-ui). Dibagikan ke seluruh halaman
            // supaya menu dapat mengarahkan tahapan tertentu ke modul
            // pembaharuan, sementara sesi login biasa tetap memakai jalur
            // lama. Bernilai false pada sesi biasa.
            //
            // Keberadaan sesi diperiksa lebih dulu: middleware ini juga
            // berjalan pada rute yang tidak memakai sesi (mis. halaman awal
            // sebelum login), dan memanggil session() di sana melemparkan
            // "Session store not set on request".
            'sandbox' => $request->hasSession()
                && $request->session()->get('sandbox_trial', false) === true,
        ]);
    }
}
