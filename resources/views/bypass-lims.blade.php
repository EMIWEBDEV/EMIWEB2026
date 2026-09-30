<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light"
    data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none">

<head>
    <meta charset="utf-8" />
    <title>LIMS Toolkit | Panel Bypass</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta content="Panel Bypass LIMS" name="description" />
    <meta content="PT. Evo Nusa Bersaudara" name="author" />
    <link rel="shortcut icon" href="">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @include('layouts.head-css')
    @stack('css')
    @vite('resources/js/vueapp.js')
    @inertiaHead
</head>

<body>
    @inertia

    <script src="{{ URL::asset('assets/libs/bootstrap/bootstrap.min.js') }}"></script>
</body>

</html>
