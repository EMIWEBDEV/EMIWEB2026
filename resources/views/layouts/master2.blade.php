<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-topbar="light" data-sidebar="dark"
    data-sidebar-size="lg" data-sidebar-image="none">

<head>
    <meta charset="utf-8" />
    <title>Laboratory Information Management System | PT EVO MANUFACTURING INDONESIA</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="ERP EMI`" name="description" />
    <meta content="PT. Evo Nusa Bersaudara" name="author" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- App favicon -->
    <link rel="shortcut icon" href="https://images.glints.com/unsafe/glints-dashboard.oss-ap-southeast-1.aliyuncs.com/company-logo/cd26ec0ff7e9ffe4e6f684a6c25d586e.jpeg">
    @include('layouts.head-css')
    @stack('css')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="{{ asset("assets/js/app.min.js") }}"></script>
    
</head>

<style>
.modal-content {
    border-radius: 12px;
    overflow: hidden;
}
.modal-header {
    border-bottom: none;
    padding: 1rem 1.5rem;
}
.modal-body {
    padding-bottom: 0;
}
.modal-footer {
    background-color: #f8f9fa;
    border-top: none;
    padding: 1rem 1.5rem 1.5rem;
}
.ps-text {
    color: white !important;
}
.bg-soft-warning {
    background-color: rgba(255, 193, 7, 0.1) !important;
}
.bg-soft-info {
    background-color: rgba(13, 110, 253, 0.1) !important;
}
.rounded-1 {
    border-radius: 8px !important;
}
</style>

@section('body')
    @include('layouts.body')
@show

    <div id="layout-wrapper">


    @include('layouts.topbar')
    @include('layouts.sidebar')
    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
               {{-- <div id="EmiLab-Informasi" class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="EmiLab-InformasiLabel" aria-hidden="true">
                   <div class="modal-dialog modal-dialog-centered modal-xl">
                        <div class="modal-dialog modal-dialog-centered modal-xl">
                            <div class="modal-content border-0 overflow-hidden shadow-lg">
                                <div class="modal-header bg-primary text-white py-3 px-4 d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-megaphone-fill fs-4 me-2"></i>
                                        <h5 class="modal-title text-white fw-bold mb-0">Pemberitahuan Sistem</h5>
                                    </div>
                                    <div id="notifLottie" style="width: 50px; height: 50px;"></div>
                                </div>

                                <div class="modal-body p-4">

                                    <div class="d-flex align-items-center mb-4 bg-soft-success rounded p-3 shadow-sm">
                                        <div class="badge bg-success text-white fs-6 fw-semibold me-3 px-3 py-2 rounded-pill">
                                            <i class="bi bi-check-circle me-2"></i>VERSI 2.0.0
                                        </div>
                                        <div>
                                            <h6 class="fw-semibold mb-0 text-success">Sistem Sudah Resmi Dirilis</h6>
                                            <p class="text-muted small mb-0">Versi stabil – semua fitur utama telah siap digunakan sepenuhnya</p>
                                        </div>
                                    </div>
                                     <div class="alert alert-primary border border-3 border-primary rounded-3 shadow-sm mb-4">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 text-primary">
                                                <i class="bi bi-info-circle-fill fs-3"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h5 class="fw-bold text-primary mb-2">🔹 Peningkatan Mekanisme Finalisasi</h5>
                                                <p class="mb-1 text-dark">
                                                    Mengingat adanya ketidak konsistenan data pada proses <strong>finalisasi</strong>, sistem telah diupgrade dengan pendekatan baru:
                                                    <em>seluruh analisa yang didaftarkan pada uji laboratorium kini wajib dilengkapi</em>.
                                                </p>
                                                <p class="mb-0 text-muted small">
                                                    Dengan mekanisme ini, hasil finalisasi menjadi lebih <strong>efisien</strong>, <strong>transparan</strong>, dan 
                                                    <strong>terjamin keakuratannya</strong> sehingga meminimalisir perbedaan data antar tahap pengujian.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-dark border border-3 border-dark rounded-3 shadow-sm mb-4">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 text-dark">
                                                <i class="bi bi-box-seam fs-3"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h5 class="fw-bold text-dark mb-2">📦 Menu Baru: Daftar Produk Rilis</h5>
                                                <p class="mb-1 text-dark">
                                                    Kini tersedia menu <strong>"Daftar Produk Rilis"</strong> yang memberikan informasi penting mengenai status kelayakan setiap <em>Nomor Produksi Order</em>.
                                                </p>
                                                <ul class="ps-3 mb-0 small text-muted">
                                                    <li>Menunjukkan apakah suatu batch produksi sudah <span class="fw-bold text-success">Layak</span> atau <span class="fw-bold text-danger">Tidak Layak</span> rilis.</li>
                                                    <li>Memudahkan tim produksi dan laboratorium untuk melakukan verifikasi akhir sebelum distribusi.</li>
                                                    <li>Meningkatkan efisiensi dan transparansi proses rilis produk.</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="alert alert-info border border-info border-2 rounded-3 mb-4 shadow-sm">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 text-info">
                                                <i class="bi bi-stars fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="fw-bold mb-2 text-info">🔹 Pembaruan Terbaru</h6>
                                                <ul class="ps-3 mb-0">
                                                    <li class="mb-2 fw-semibold text-dark">
                                                        Analisa Mikrobiologi <span class="text-primary">AC</span>, 
                                                        <span class="text-primary">EC</span>, 
                                                        <span class="text-primary">Salmonella</span>, 
                                                        dan <span class="text-primary">YM</span> sudah dapat digunakan
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-light-secondary border border-secondary border-2 rounded-3 mb-4">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 text-secondary">
                                                <i class="bi bi-tools fs-4"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="fw-semibold mb-2 text-secondary">Fitur Tracking Data Sementara Dinonaktifkan</h6>
                                                <p class="text-muted small mb-0">
                                                    Fitur tracking penginputan data saat ini <strong>di-takedown sementara</strong> untuk peningkatan
                                                    <em>user experience</em> pada sisi layout dan performa sistem.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                            
                                    <div class="bg-light rounded-2 p-3 mt-4 shadow-sm">
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-clock-history text-muted me-2"></i>
                                            <span class="text-muted small">Pemberitahuan ini muncul setiap 20 menit</span>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-megaphone text-muted me-2"></i>
                                            <span class="text-muted small">Untuk pembaruan terbaru, kunjungi 
                                                <a href="/tentang" class="text-primary text-decoration-none fw-semibold">Pembaharuan Sistem</a>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer bg-light border-0 pt-0 pb-3 px-4">
                                    <button type="button" id="understandBtn" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold">
                                        <i class="bi bi-check-circle me-2"></i> Saya Mengerti
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
               </div> --}}

                @yield('content')
            </div>
            <!-- container-fluid -->
        </div>
        <!-- End Page-content -->
        @include('layouts.footer')
    </div>
    <!-- end main content-->
</div>
<!-- END layout-wrapper -->

@include('layouts.customizer')

<script src="https://unpkg.com/lottie-web@5.10.2/build/player/lottie.min.js"></script>
@include('layouts.vendor-scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    lottie.loadAnimation({
        container: document.getElementById('notifLottie'),
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: '{{ asset('animation/Notification.json') }}' 
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('EmiLab-Informasi'));
        const understandBtn = document.getElementById('understandBtn');
        
       
        const notificationStatus = localStorage.getItem('betaNotification');
        const now = new Date().getTime();
        
        if (!notificationStatus || (now - JSON.parse(notificationStatus).timestamp) > 20 * 60 * 1000) {
            modal.show();
        }
        
        understandBtn.addEventListener('click', function() {
            localStorage.setItem('betaNotification', JSON.stringify({
                understood: true,
                timestamp: now
            }));
            modal.hide();
        });
    });
</script>
@stack('js')
</body>

</html>
