@php
    // --- 1. LOGIKA PENGECEKAN AKSES (DI DALAM BLADE) ---
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Auth;

    $targetUrl = '/'; // Default fallback (misal ke login/root jika user null)
    $destinasiNama = 'Halaman Utama';

    if (Auth::check()) {
        $user = Auth::user();
        $userId = $user->UserId;

        // Cek Akses Dashboard
        $hasDashboardAccess = DB::table('N_EMI_LAB_Role_Menu')
            ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
            ->where('N_EMI_LAB_Role_Menu.Id_User', $userId)
            ->where('N_EMI_LAB_Menus.Nama_Menu', 'Dashboard')
            ->exists();

        // Cek Akses Home (Registrasi Sampel)
        $hasHomeAccess = DB::table('N_EMI_LAB_Role_Menu')
            ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
            ->where('N_EMI_LAB_Role_Menu.Id_User', $userId)
            ->where('N_EMI_LAB_Menus.Nama_Menu', 'Registrasi Sampel')
            ->exists();

        // Tentukan URL Tujuan berdasarkan Prioritas
        if ($hasDashboardAccess) {
            $targetUrl = '/dashboard';
            $destinasiNama = 'Dashboard';
        } elseif ($hasHomeAccess) {
            $targetUrl = '/home';
            $destinasiNama = 'Home';
        } else {
            $targetUrl = '/master-template-printer-transaksi';
            $destinasiNama = 'Master Template';
        }
    } else {
        // Jika session habis / belum login, lempar ke login
        $targetUrl = '/login'; 
        $destinasiNama = 'Halaman Login';
    }
@endphp

@extends('layouts.master-without-nav')

@section('content')
<div class="auth-page-wrapper py-5 d-flex justify-content-center align-items-center min-vh-100">

    <div class="auth-page-content overflow-hidden p-0">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-7 col-lg-8">
                    <div class="text-center">
                        <img src="{{ asset("assets/images/403.png") }}" alt="error img" width="300">
                        <div class="mt-3">
                            <h3 class="text-uppercase">403 - Akses Tidak Diizinkan 😡</h3>
                            <p class="text-muted mb-4">Anda tidak memiliki hak akses ke halaman yang dituju.</p>
                            
                            <div class="alert alert-info" role="alert">
                                Sistem memeriksa hak akses Anda... <br>
                                Mengalihkan ke <strong>{{ $destinasiNama }}</strong> dalam <span id="countdown" class="fw-bold text-danger">3</span> detik.
                            </div>

                            <a href="{{ url($targetUrl) }}" class="btn btn-success">
                                <i class="mdi mdi-location-enter me-1"></i> Langsung ke {{ $destinasiNama }}
                            </a>

                        </div>
                    </div>
                </div></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ URL::asset('assets/libs/particles.js/particles.js.min.js') }}"></script>
    <script src="{{ URL::asset('assets/js/pages/particles.app.js') }}"></script>
    <script src="{{ URL::asset('assets/js/pages/password-addon.init.js') }}"></script>

    <script>
        // Ambil URL dari variabel PHP di atas
        var targetUrl = "{{ $targetUrl }}";
        var timeLeft = 3;
        var elem = document.getElementById('countdown');
        
        // Fungsi hitung mundur
        var timerId = setInterval(function() {
            if (timeLeft <= 0) {
                clearTimeout(timerId);
                window.location.href = targetUrl; // Pindah halaman
            } else {
                elem.innerHTML = timeLeft;
                timeLeft--;
            }
        }, 1000);
    </script>
@endsection