@php
    use Illuminate\Support\Facades\DB;

    $userId = Auth::user()->UserId;

    $menuDashboard = DB::table('N_EMI_LAB_Role_Menu')
    ->where('Id_User', $userId)
    ->where('Id_Menu', 1) 
    ->exists();

    $countNotifikasi = DB::tabLe("N_EMI_LAB_PO_Sampel")
                        ->whereNull('Flag_Baca')->count() ?? 0;
    
    $getListNotifikasi = DB::table("N_EMI_LAB_PO_Sampel as ps")
        ->join("EMI_Master_Mesin as mm", "ps.Id_Mesin", "=", "mm.Id_Master_Mesin")
        ->select(
            'ps.No_Sampel',
            'ps.Flag_Baca',
            'ps.Tanggal',
            'ps.Jam',
            'ps.Id_User',
            'mm.Flag_Multi_QrCode',
            'mm.Nama_Mesin' 
        )
        ->orderByRaw("CASE WHEN ps.Flag_Baca IS NULL THEN 0 ELSE 1 END")
        ->orderBy('ps.Tanggal', 'desc')
        ->orderBy('ps.Jam', 'desc')
        ->limit(10)
        ->get();

@endphp

<style>
    .noread {
        background: #F3F3F9; 
    }
    .quest:hover{
      color: #405189 !important;
    }
    .view-all-fixed {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: #ffffff;
        padding: 10px 15px;
        border-top: 1px solid #dee2e6;
        z-index: 1050;
    }
</style>
<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box horizontal-logo">
                    <a href="index" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="{{ URL::asset('assets/images/logo.png') }}" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ URL::asset('assets/images/logo.png') }}" alt="" height="17">
                        </span>
                    </a>

                    <a href="index" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="{{ URL::asset('assets/images/logo.png') }}" alt="" height="22">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ URL::asset('assets/images/logo.png') }}" alt="" height="17">
                        </span>
                    </a>
                </div>

                <button type="button" class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger"
                    id="topnav-hamburger-icon">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>
            </div>

            <div class="d-flex align-items-center">
               

                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button" class="btn btn-icon btn-topbar btn-ghost-secondary rounded-circle"
                        data-toggle="fullscreen">
                        <i class='bx bx-fullscreen fs-22'></i>
                    </button>
                </div>
                @if ($menuDashboard)
                 <div class="dropdown topbar-head-dropdown ms-1 header-item" id="notificationDropdown">
                    <button type="button" class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle" id="page-header-notifications-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                        <i class='bx bx-bell fs-22'></i>
                       @if ($countNotifikasi > 0)
                        <span class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger">{{ $countNotifikasi }}<span class="visually-hidden">unread messages</span></span>
                       @endif
                    </button>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" aria-labelledby="page-header-notifications-dropdown">

                                <div class="dropdown-head bg-primary bg-pattern rounded-top">
                                    <div class="p-3">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <h6 class="m-0 fs-16 fw-semibold text-white"> Notifikasi </h6>
                                            </div>
                                            <div class="col-auto dropdown-tabs">
                                                @if ($countNotifikasi > 0)
                                                    <span class="badge bg-light text-body fs-13"> {{ $countNotifikasi }} Baru</span>
                                               @endif
                                              
                                            </div>
                                        </div>
                                    </div>

                                    <div class="px-2 pt-2">
                                        <ul class="nav nav-tabs dropdown-tabs nav-tabs-custom" data-dropdown-tabs="true" id="notificationItemsTab" role="tablist">
                                            <li class="nav-item waves-effect waves-light">
                                                <a class="nav-link active quest" data-bs-toggle="tab" href="#all-noti-tab" role="tab" aria-selected="true">
                                                    @if ($countNotifikasi > 0)
                                                        Pesan Belum Dibaca ({{ $countNotifikasi }})
                                                    @else
                                                        Semua Notifikasi
                                                    @endif
                                                    
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="tab-content position-relative" id="notificationItemsTabContent">
                                    <div class="tab-pane fade show active py-2 ps-2" id="all-noti-tab" role="tabpanel">
                                        <div data-simplebar style="max-height: 300px; padding-bottom: 50px;" class="pe-2">
                                            @if($getListNotifikasi && count($getListNotifikasi) > 0)
                                                @foreach($getListNotifikasi as $notif)
                                                    @php
                                                        $isUnread = is_null($notif->Flag_Baca);
                                                        \Carbon\Carbon::setLocale('id');
                                                        try {
                                                            $formattedDate = \Carbon\Carbon::parse($notif->Tanggal)->translatedFormat('d F Y');
                                                        } catch (\Exception $e) {
                                                            $formattedDate = '-';
                                                        }
                                            
                                                        // Tentukan label QrCode
                                                        $qrCodeLabel = ($notif->Flag_Multi_QrCode === 'Y') ? 'Multi QrCode' : 'Single QrCode';
                                                    @endphp
                                            
                                                    <div class="text-reset notification-item d-block dropdown-item position-relative {{ $isUnread ? 'noread' : '' }} mb-1">
                                                        <div class="d-flex">
                                                            <div class="avatar-xs me-3 flex-shrink-0">
                                                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-16">
                                                                    <i class="ri-flask-line"></i>
                                                                </span> 
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <a href="{{ '/notifikasi/current?kode=' . ($notif->No_Sampel ?? '') }}" class="stretched-link nop">
                                                                    <h6 class="lh-base">
                                                                        <b class="text-dark">
                                                                            Sampel Baru Masuk
                                                                        </b>
                                                                        <br>
                                                                        
                                                                        <span class="d-block mt-1">
                                                                            <i class="ri-user-line text-secondary me-1"></i>
                                                                            <span class="text-muted">Dikirim oleh:</span>
                                                                            <span class="text-primary fw-semibold">{{ $notif->Id_User ?? 'Pengirim Tidak Diketahui' }}</span>
                                                                        </span>
                                                                        
                                                                        <span class="d-block mt-1">
                                                                            <i class="ri-hashtag text-secondary me-1"></i>
                                                                            <span class="text-muted">Nomor Sampel:</span>
                                                                            <span class="text-dark">{{ $notif->No_Sampel ?? '-' }}</span>
                                                                        </span>
                                                            
                                                                        <span class="d-block mt-1">
                                                                            <i class="ri-cpu-line text-secondary me-1"></i>
                                                                            <span class="text-muted">Nama Mesin:</span>
                                                                            <span class="text-dark">{{ $notif->Nama_Mesin ?? '-' }}</span>
                                                                        </span>
                                                            
                                                                        <span class="d-block mt-1">
                                                                            <i class="ri-qr-code-line text-secondary me-1"></i>
                                                                            <span class="text-muted">Jenis QR:</span>
                                                                            <span class="text-success fw-semibold">{{ $qrCodeLabel }}</span>
                                                                        </span>
                                                                    </h6>
                                                                </a>
                                                            
                                                                <p class="mb-0 fs-11 fw-medium text-uppercase text-muted mt-2">
                                                                    <i class="mdi mdi-clock-outline me-1"></i> {{ $formattedDate }}
                                                                </p>
                                                            </div>
                                                            
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="text-center text-muted">
                                                    <i class="ri-notification-off-line fs-24"></i>
                                                    <p class="mt-2 mb-0">Tidak ada notifikasi saat ini.</p>
                                                </div>
                                            @endif
                                        
                                            <div class="view-all-fixed">
                                                <a href="/notifikasi/current" class="btn btn-soft-success w-100 rounded-0 shadow">
                                                    Lihat Semua Notifications <i class="ri-arrow-right-line align-middle"></i>
                                                </a>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                 @endif


                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn" id="page-header-user-dropdown" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                           
                            <img class="rounded-circle header-profile-user"
                                src="{{ URL::asset('assets/images/users/users.png') }}" alt="Header Avatar">
                            <span class="text-start ms-xl-2">
                                <span
                                    class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ Auth::user()->Nama ?? "-" }}</span>
                                {{-- <span
                                    class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ Auth::user()->name }}</span>
                            </span>
                            <span class="d-none d-xl-block ms-1 fs-12 text-muted user-name-sub-text">Founder</span> --}}
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @php
                            $userId = Auth::user()->UserId;

                            $hasAccess = DB::table('N_EMI_LAB_Role_Menu as rm')
                                ->join('N_EMI_LAB_Menus as m', 'rm.Id_Menu', '=', 'm.Id_Menu')
                                ->where('rm.Id_User', $userId)
                                ->where('m.Nama_Menu', 'Registrasi Sampel')
                                ->exists();
                        @endphp

                        <!-- item-->
                        <h6 class="dropdown-header">Welcome {{ Auth::user()->Nama ?? "-" }}</h6>
                        <button class="dropdown-item" data-bs-toggle="modal"
                            data-bs-target="#gantiPasswordModal{{ Session::get('user.id') }}"><i
                                class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i><span
                                class="align-middle">Ganti Password</button>

                        @if ($hasAccess)
                                <button class="dropdown-item" data-bs-toggle="modal"
                                    data-bs-target="#gantiPin{{ Session::get('user.id') }}">
                                    <i class="mdi mdi-lock-reset text-muted fs-16 align-middle me-1"></i>
                                    <span class="align-middle">Ganti Pin</span>
                                </button>
                            @endif
                            
                            


                        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#logoutModal"> <i
                                class="bx bx-power-off font-size-16 align-middle me-1"></i> Logout</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</header>
