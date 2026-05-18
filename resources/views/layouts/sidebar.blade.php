
<style>
    .bg-beta {
        background: #7c91c3; /* Lebih lembut dan matching */
        color: #ffffff;
    }

    .bg-expreimental {
        background: #e3a008; /* Amber/Oranye-Gold sebagai penanda experimental */
        color: #ffffff;
    }

    .bg-new {
        background: #f04438; /* Warna merah fresh */
        color: #ffffff;
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
                <img style="border-radius: 20px" src="{{ URL::asset('assets/images/evo.png') }}" alt=""
                    height="22">
            </span>
            <span class="logo-lg">
                <img style="border-radius: 20px" src="{{ URL::asset('assets/images/evo.png') }}" alt=""
                    height="60">
            </span>
        </a>
        <!-- Sidebar Toggle Button -->
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover" onclick="toggleSidebar()">
            <i class="fas fa-circle"></i>
        </button>
    </div>
    {{-- start --}}
    <div id="scrollbar">
        <div class="container-fluid">
            @php
                $idUser = auth()->check() ? auth()->user()->UserId : '';

                $allMenus = DB::table('N_EMI_LAB_Menus as m')
                    ->join('N_EMI_LAB_Page_Access_2 as pa', 'm.Id_Menu', '=', 'pa.Id_Menu')
                    ->where('m.Kode_Perusahaan', '001')
                    ->where('pa.Id_User', $idUser)
                    ->select('m.*', 'pa.Urutan_Menu')
                    ->orderBy('pa.Urutan_Menu', 'asc')
                    ->get();

                // Dashboard group: semua menu bertanda Nama_Header = 'Dashboard'
                $dashboardMenus = $allMenus->where('Nama_Header', 'Dashboard')->values();

                // Menu lainnya (non-dashboard)
                $headerMenus = $allMenus
                    ->whereNotNull('Nama_Header')
                    ->where('Nama_Header', '!=', 'Dashboard')
                    ->groupBy('Nama_Header');

                $bottomMenus = $allMenus->whereNull('Nama_Header');
            @endphp

            <ul class="navbar-nav" id="navbar-nav">

                <li class="menu-title"><span data-key="t-menu">Menu</span></li>

                {{-- ── Dashboard: 1 item = direct link, >1 = collapse ── --}}
                @if ($dashboardMenus->count() === 1)
                    @php
                        $singleDash = $dashboardMenus->first();
                        $urlSingle  = ltrim($singleDash->Url_Menu, '/');
                        $isSingleActive = (request()->is($urlSingle . '*') || request()->is($singleDash->Url_Menu . '*')) ? 'active' : '';
                    @endphp
                    <li class="nav-item">
                        <a href="{{ url($singleDash->Url_Menu) }}" class="nav-link menu-link {{ $isSingleActive }}">
                            <i class="fas fa-home"></i>
                            <span data-key="t-dashboard">Dashboard</span>
                        </a>
                    </li>
                @elseif ($dashboardMenus->count() > 1)
                    @php
                        $isDashGroupActive = $dashboardMenus->contains(function ($m) {
                            $u = ltrim($m->Url_Menu, '/');
                            return request()->is($u . '*') || request()->is($m->Url_Menu . '*');
                        });
                    @endphp
                    <li class="nav-item">
                        <a class="nav-link menu-link {{ $isDashGroupActive ? 'active' : '' }}"
                           href="#sidebarDashboardGroup"
                           data-bs-toggle="collapse"
                           role="button"
                           aria-expanded="{{ $isDashGroupActive ? 'true' : 'false' }}"
                           aria-controls="sidebarDashboardGroup">
                            <i class="fas fa-home"></i>
                            <span data-key="t-dashboard">Dashboard</span>
                        </a>
                        <div class="collapse menu-dropdown {{ $isDashGroupActive ? 'show' : '' }}" id="sidebarDashboardGroup">
                            <ul class="nav nav-sm flex-column">
                                @foreach ($dashboardMenus as $dashItem)
                                    @php
                                        $urlDashItem = ltrim($dashItem->Url_Menu, '/');
                                        $isDashItemActive = (request()->is($urlDashItem . '*') || request()->is($dashItem->Url_Menu . '*')) ? 'active' : '';
                                    @endphp
                                    <li class="nav-item">
                                        <a href="{{ url($dashItem->Url_Menu) }}"
                                           class="nav-link {{ $isDashItemActive }}"
                                           data-key="t-{{ \Str::slug($dashItem->Nama_Menu) }}">
                                            <i class="{{ $dashItem->Icon_Menu }}"></i>
                                            {{ $dashItem->Nama_Menu }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($headerMenus->count() > 0)
                <li class="menu-title"><span data-key="t-laboratorium">Laboratorium</span></li>
                @endif

                @foreach ($headerMenus as $headerName => $menusInHeader)
                    @php
                        $headerId = 'sidebar' . \Str::slug($headerName);
                        
                        $isHeaderActive = $menusInHeader->contains(function ($value) {
                            $urlCheck = ltrim($value->Url_Menu, '/');
                            return request()->is($urlCheck . '*') || request()->is($value->Url_Menu . '*');
                        });

                        $groupedBySub = $menusInHeader->groupBy('Sub_Header');
                    @endphp

                    <li class="nav-item">
                        <a class="nav-link menu-link {{ $isHeaderActive ? 'active' : '' }}" href="#{{ $headerId }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isHeaderActive ? 'true' : 'false' }}" aria-controls="{{ $headerId }}">
                            <i class="{{ $headerName == 'Master Data' ? 'ri-database-2-line' : 'ri-flask-line' }}"></i>
                            <span data-key="t-{{ \Str::slug($headerName) }}">{{ $headerName }}</span>
                        </a>
                        
                        <div class="collapse menu-dropdown {{ $isHeaderActive ? 'show' : '' }}" id="{{ $headerId }}">
                            <ul class="nav nav-sm flex-column">
                                @foreach ($groupedBySub as $subHeaderName => $menusInSub)
                                    @if (empty($subHeaderName))
                                        @foreach ($menusInSub as $menu)
                                            @php
                                                $urlMenu = ltrim($menu->Url_Menu, '/');
                                                $isActive = request()->is($urlMenu . '*') || request()->is($menu->Url_Menu . '*') ? 'active' : '';
                                            @endphp
                                            <li class="nav-item">
                                                <a href="{{ url($menu->Url_Menu) }}" class="nav-link {{ $isActive }}" data-key="t-{{ \Str::slug($menu->Nama_Menu) }}">
                                                    <i class="{{ $menu->Icon_Menu }}"></i> {{ $menu->Nama_Menu }}
                                                </a>
                                            </li>
                                        @endforeach
                                    @else
                                        @php
                                            $subHeaderId = 'sidebar' . \Str::slug($headerName) . \Str::slug($subHeaderName);
                                            $isSubActive = $menusInSub->contains(function ($value) {
                                                $urlCheck = ltrim($value->Url_Menu, '/');
                                                return request()->is($urlCheck . '*') || request()->is($value->Url_Menu . '*');
                                            });
                                        @endphp
                                        <li class="nav-item">
                                            <a href="#{{ $subHeaderId }}" class="nav-link {{ $isSubActive ? 'active' : '' }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ $isSubActive ? 'true' : 'false' }}" aria-controls="{{ $subHeaderId }}" data-key="t-{{ \Str::slug($subHeaderName) }}">
                                                {{ $subHeaderName }}
                                            </a>
                                            <div class="collapse menu-dropdown {{ $isSubActive ? 'show' : '' }}" id="{{ $subHeaderId }}">
                                                <ul class="nav nav-sm flex-column">
                                                    @foreach ($menusInSub as $menu)
                                                        @php
                                                            $urlSubMenu = ltrim($menu->Url_Menu, '/');
                                                            $isMenuActive = request()->is($urlSubMenu . '*') || request()->is($menu->Url_Menu . '*') ? 'active' : '';
                                                        @endphp
                                                        <li class="nav-item">
                                                            <a href="{{ url($menu->Url_Menu) }}" class="nav-link {{ $isMenuActive }}" data-key="t-{{ \Str::slug($menu->Nama_Menu) }}">
                                                                <i class="{{ $menu->Icon_Menu }}"></i> {{ $menu->Nama_Menu }}
                                                            </a>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endforeach

                @if ($bottomMenus->count() > 0)
                    <li class="menu-title"><span data-key="t-lainnya">Lainnya</span></li>
                    
                    @foreach ($bottomMenus as $menu)
                        @php
                            $urlBottom = ltrim($menu->Url_Menu, '/');
                            $isActiveBottom = request()->is($urlBottom . '*') || request()->is($menu->Url_Menu . '*') ? 'active' : '';
                        @endphp
                        <li class="nav-item">
                            <a href="{{ url($menu->Url_Menu) }}" class="nav-link menu-link {{ $isActiveBottom }}">
                                <i class="{{ $menu->Icon_Menu }}"></i>
                                <span data-key="t-{{ \Str::slug($menu->Nama_Menu) }}">{{ $menu->Nama_Menu }}</span>

                                @if ($menu->Url_Menu === '/tentang')
                                    <span class="badge badge-pill bg-danger" data-key="t-hot">Baru</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                @endif
            </ul>
        </div>
    </div>
    {{-- end --}}
    <div class="sidebar-background"></div>
</div>

<div class="vertical-overlay"></div>
