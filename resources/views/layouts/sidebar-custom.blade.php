<style>
    .navbar-menu {
        width: 100px !important;
    }
    .main-content {
        margin-left: 100px !important;
    }
    #page-topbar {
        left: 0px !important;
    }

    .footer {
        left: 100px !important;
    }
    
</style>
<div class="app-menu navbar-menu">
    <!-- LOGO -->
    <div class="navbar-brand-box">
        <!-- Dark Logo-->
        <a class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ URL::asset('assets/images/evo.png') }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img src="{{ URL::asset('assets/images/evo.png') }}" alt="" height="60">
            </span>
        </a>
        <!-- Light Logo-->
        <a class="logo logo-light">
            <span class="logo-sm">
                <img style="border-radius: 20px" src="{{ URL::asset('assets/images/evo.png') }}" alt="" height="22">
            </span>
            <span class="logo-lg">
                <img style="border-radius: 20px" src="{{ URL::asset('assets/images/evo.png') }}" alt="" height="60">
            </span>
        </a>
        <!-- Sidebar Toggle Button -->
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover" onclick="toggleSidebar()">
            <i class="fas fa-circle"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">
            <ul class="navbar-nav" id="navbar-nav">
                {{-- <li class="menu-title"><span>@lang('translation.menu')</span></li> --}}
                <li class="nav-item">
                    {{-- <a href="{{ url('dashboard') }}"
                        class="nav-link menu-link {{ request()->is('dashboard') ? 'active' : '' }}">
                        <i class="fas fa-home"></i><span>Dashboard</span>
                    </a> --}}
                </li>


            </ul>
        </div>
        <!-- Sidebar -->
    </div>
    <div class="sidebar-background"></div>
</div>

<div class="vertical-overlay"></div>