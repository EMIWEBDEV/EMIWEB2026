@extends('layouts.master-without-nav')

@section('content')
<style>
    /* --- SHIMMER EFFECT (Sesuai kode asli Anda) --- */
    .shimmer-wrapper {
        position: relative;
        display: inline-block;
        overflow: hidden;
    }
    
    .shimmer-img {
        display: block;
        border-radius: 0.375rem;
    }
    
    .shimmer-wrapper::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        height: 100%;
        width: 100%;
        background: linear-gradient(120deg, transparent 0%, rgba(255, 255, 255, 0.6) 50%, transparent 100%);
        animation: shimmer 1.5s infinite;
        pointer-events: none;
    }

    @keyframes shimmer {
        0% { left: -100%; }
        100% { left: 100%; }
    }

    /* --- TURNSTILE ENTERPRISE STYLE --- */
    .turnstile-container {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        min-height: 65px; /* Tinggi standar Turnstile agar layout tidak lompat saat loading */
        margin-bottom: 1rem;
    }

    /* Kita tidak memaksa width 100% ke iframe agar tidak gepeng/pecah gambarnya.
       Enterprise style lebih mengutamakan proporsi yang pas. */
    
    /* Responsive Logic:
       Turnstile lebarnya fix 300px. Jika layar < 350px, kita kecilkan skalanya
       agar tidak menabrak pinggir layar. */
    @media (max-width: 380px) {
        .turnstile-container {
            transform: scale(0.90); /* Kecilkan sedikit */
            transform-origin: center;
            margin-left: -5px; /* Kompensasi margin jika scale mengecil */
            margin-right: -5px;
        }
    }

    @media (max-width: 320px) {
        .turnstile-container {
            transform: scale(0.80); /* Kecilkan lebih banyak untuk HP jadul/kecil */
        }
    }
</style>

<div class="auth-page-wrapper min-vh-100 d-flex align-items-center">
    <div class="auth-one-bg-position auth-one-bg" id="auth-particles">
        <div class="bg-overlay"></div>
        <div class="shape">
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink"
                viewBox="0 0 1440 120">
                <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z"></path>
            </svg>
        </div>
    </div>

    <div class="auth-page-content w-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8">
                    <div class="card border-0 shadow-lg">
                        <div class="row g-0">
                            <div class="col-md-6 bg-light d-none d-md-block">
                                <div class="h-100 d-flex align-items-center justify-content-center">
                                    <img src="{{ asset('assets/images/login-image.png') }}" alt="Login Banner" 
                                         class="img-fluid p-4" style="max-height: 400px;">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card-body p-4 p-lg-5">
                                    <div class="d-flex justify-content-center mb-4 shimmer-wrapper">
                                        <img src="{{ asset('assets/images/evo.png') }}" 
                                             width="70px" alt="Logo" class="rounded shimmer-img">
                                    </div>
                                    
                                    <div class="text-center mb-4">
                                        <h4 class="text-primary">
                                            <i class="ri-user-smile-line me-1"></i> Selamat Datang
                                        </h4>
                                        <p class="text-muted">Silakan Login untuk melanjutkan</p>
                                    </div>

                                    @if (session()->has('message'))
                                        <div class="alert alert-success alert-dismissible fade show">
                                            <i class="ri-check-double-line me-1"></i>
                                            <strong>{{ session('message') }}</strong>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('proses_login') }}" class="needs-validation" novalidate>
                                        @csrf
                                        <input type="hidden" name="tz" id="tz-input">

                                        <div class="mb-4">
                                            <label for="username" class="form-label">
                                                <i class="ri-user-line me-1"></i> Username
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light">
                                                    <i class="ri-user-3-line text-muted"></i>
                                                </span>
                                                <input type="text" class="form-control" id="username" name="UserId" 
                                                       placeholder="Masukkan username" required>
                                            </div>
                                        </div>
                                    
                                        <div class="mb-4">
                                            <label class="form-label" for="password-input">
                                                <i class="ri-lock-line me-1"></i> Password
                                            </label>
                                            <div class="input-group position-relative">
                                                <span class="input-group-text bg-light">
                                                    <i class="ri-lock-2-line text-muted"></i>
                                                </span>
                                                <input type="password" class="form-control pe-5" name="Password" 
                                                       id="password-input" placeholder="Masukkan password" required>
                                                <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon" 
                                                        type="button" id="password-addon" style="z-index: 10;">
                                                    <i class="ri-eye-fill align-middle"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="animate__animated animate__fadeInUp" style="transform: scale(0.85); transform-origin: center; margin-left: -20px;">
                                            <div class="captcha-container">
                                                <div class="cf-turnstile"
                                                    data-sitekey="{{ config('services.turnstile.sitekey') }}"
                                                    data-theme="light"
                                                    data-size="flexible">
                                                </div>
                                            </div>
                                            @error('captcha')
                                            <div class="text-danger text-center small mb-3 animate__animated animate__headShake">
                                                <i class="fas fa-exclamation-triangle me-1"></i> {{ $message }}
                                            </div>
                                            @enderror
                                        </div>
                                
                                        <div class="mt-4">
                                            <button class="btn btn-success w-100 shadow-sm" type="submit">
                                                <i class="ri-login-circle-line me-1"></i> Masuk
                                            </button>
                                        </div>
                                        <small class="d-flex justify-content-center mt-3 text-muted fs-12">Versi 2.2.0</small>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="text-center">
                        <p id="footer-text" class="mb-0 text-muted"></p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>

@if(session('error'))
<script>
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: "{{ session('error') }}",
        showConfirmButton: true,
        confirmButtonColor: '#0ab39c' // Warna Enterprise Teal/Green
    });
</script>
@endif

<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<script>
  const year = new Date().getFullYear();
  const footer = document.getElementById("footer-text");
  footer.innerHTML = `&copy; ${year} EMI LAB - Dashboard. Crafted with <i class="mdi mdi-heart text-danger"></i> by Evo Nusa`;
</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        document.getElementById('tz-input').value = tz;
    });
</script>

@endsection

@section('script')
    <script src="{{ URL::asset('assets/libs/particles.js/particles.js.min.js') }}"></script>
    <script src="{{ URL::asset('assets/js/pages/particles.app.js') }}"></script>
    <script src="{{ URL::asset('assets/js/pages/password-addon.init.js') }}"></script>
@endsection