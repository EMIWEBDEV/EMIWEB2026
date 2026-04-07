<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-topbar="light" data-sidebar-image="none">

<head>
    <meta charset="utf-8" />
    <title>Laboratory Information Management System | PT EVO MANUFACTURING INDONESIA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="B2B Dashboard" name="description" />
    <meta content="PT. Evo Nusa Bersaudara" name="author" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- App favicon -->
    {{-- <link rel="shortcut icon" href="{{ URL::asset('assets/images/logo.png') }}"> --}}
    <link rel="shortcut icon" href="https://images.glints.com/unsafe/glints-dashboard.oss-ap-southeast-1.aliyuncs.com/company-logo/cd26ec0ff7e9ffe4e6f684a6c25d586e.jpeg">
    @include('layouts.head-css')
</head>

@yield('body')

@yield('content')

@include('layouts.vendor-scripts')
</body>

</html>
