<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light"
    data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-preloader="disable">

{{--
    Root view untuk sandbox /trial-ui.

    Memuat CSS Velzon yang SAMA PERSIS dengan aplikasi (lewat layouts.head-css),
    sehingga seluruh modul yang di-mount di dalamnya tampil identik dengan
    menu aslinya.

    Yang tidak disertakan hanyalah topbar dan sidebar aplikasi, karena
    keduanya memanggil Auth::user()->UserId — sedangkan halaman ini harus
    bisa dibuka sebelum user memilih identitas. Setelah user dipilih, sesi
    login asli terbentuk dan modul-modul lama berjalan apa adanya.
--}}

<head>
    <meta charset="utf-8" />
    <title>LIMS Workflow | Sandbox Alur Pengujian</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta content="Laboratory Information Management System" name="description" />
    <meta content="PT. Evo Nusa Bersaudara" name="author" />

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @include('layouts.head-css')
    @stack('css')

    @vite('resources/js/vueapp.js')
    @inertiaHead

    <style>
        /* Halaman sandbox berdiri sendiri: tanpa offset sidebar/topbar Velzon. */
        body { background: #f3f3f9; }
        .main-content { margin-left: 0 !important; }
        .page-content { padding: 0 !important; }
    </style>
</head>

<body>
    @inertia

    <script src="{{ URL::asset('assets/libs/bootstrap/bootstrap.min.js') }}"></script>
    <script src="{{ URL::asset('assets/js/app.min.js') }}"></script>
</body>

</html>
