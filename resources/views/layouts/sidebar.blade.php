
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

    <div id="scrollbar">
        <div class="container-fluid">
            @php
                $menus = DB::table('N_EMI_LAB_Role_Menu')
                    ->select(
                        'N_EMI_LAB_Menus.Nama_Menu',
                        'N_EMI_LAB_Menus.Url_Menu',
                        'N_EMI_LAB_Menus.Icon_Menu',
                        'N_EMI_LAB_Sub_Menus.Nama_Sub_Menu',
                        'N_EMI_LAB_Role_Menu.Id_User',
                    )
                    ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
                    ->leftJoin(
                        'N_EMI_LAB_Sub_Menus',
                        'N_EMI_LAB_Role_Menu.Id_Sub_Menu',
                        '=',
                        'N_EMI_LAB_Sub_Menus.Id_Sub_Menu',
                    )
                    ->where('N_EMI_LAB_Role_Menu.Id_User', Auth::user()->UserId)
                    ->get()
                    ->groupBy('Nama_Menu');

            @endphp
            <ul class="navbar-nav" id="navbar-nav">
     {{-- Header utama --}}
     <li class="menu-title"><span>menu</span></li>

     {{-- 1. Dashboard paling atas --}}
     @foreach ($menus as $menuName => $items)
         @php
             $firstItem = $items->first();
             $isActive = request()->is($firstItem->Url_Menu . '*') ? 'active' : '';
         @endphp

         @if ($firstItem->Url_Menu === 'dashboard')
             <li class="nav-item">
                 <a href="{{ url($firstItem->Url_Menu) }}" class="nav-link {{ $isActive }}">
                     <i class="{{ $firstItem->Icon_Menu }}"></i>
                     <span>{{ $menuName }}</span>
                 </a>
             </li>
         @endif
     @endforeach

    
     @php
         $produkUrls = ['hasil-analisa/produk-rilis-all', '/pembatalan-po/selesai-diclose'];
         $produkHeaderPrinted = false;
     @endphp

     @foreach ($menus as $menuName => $items)
         @php
             $firstItem = $items->first();
         @endphp

         @if (in_array($firstItem->Url_Menu, $produkUrls))
             @if (!$produkHeaderPrinted)
                 <li class="menu-title"><span>Produk</span></li>
                 @php $produkHeaderPrinted = true; @endphp
             @endif

             @php
                 $isActive = request()->is($firstItem->Url_Menu . '*') ? 'active' : '';
             @endphp
             <li class="nav-item">
                 <a href="{{ url($firstItem->Url_Menu) }}" class="nav-link {{ $isActive }}">
                     <i class="{{ $firstItem->Icon_Menu }}"></i>
                     <span>{{ $menuName }}</span>
                 </a>
             </li>
         @endif
     @endforeach


     @foreach ($menus as $menuName => $items)
         @php
             $firstItem = $items->first();
         @endphp

         @if ($firstItem->Url_Menu === 'mesin-analisa')
             <li class="menu-title"><span>Laboratorium</span></li>
         @endif
     @endforeach

     {{-- 2.1 Sub menu untuk Produk --}}

     {{-- 3. Render menu lainnya (selain dashboard & produk) --}}
     @foreach ($menus as $menuName => $items)
         @php
             $firstItem = $items->first();
             $isActive = request()->is($firstItem->Url_Menu . '*') ? 'active' : '';
             // PENAMBAHAN LOGIKA: URL yang sudah ditampilkan di section lain dikecualikan
             $excludeUrls = ['dashboard', 'hasil-analisa/produk-rilis-all', '/pembatalan-po/selesai-diclose'];
         @endphp

         @if (!in_array($firstItem->Url_Menu, $excludeUrls))
             <li class="nav-item">
                 <a href="{{ url($firstItem->Url_Menu) }}" class="nav-link {{ $isActive }}">
                     <i class="{{ $firstItem->Icon_Menu }}"></i>
                     <span>{{ $menuName }}</span>

                     {{-- Badge kondisi --}}
                     @if ($firstItem->Url_Menu === 'tentang')
                         <span class="badge badge-pill bg-danger" data-key="t-hot">🚀 Baru</span>
                         {{-- @elseif ($firstItem->Url_Menu === 'lab/confirmed-analisis')
                             <span class="badge badge-pill bg-beta" title="Fitur ini sudah bisa digunakan, namun belum versi final.">🔄 Beta</span>
                         @elseif ($firstItem->Url_Menu === 'hasil-analisa/validasi-close-sampel')
                             <div class="ms-1">
                                 <span class="badge badge-pill bg-beta" title="Fitur ini sudah bisa digunakan, namun belum versi final.">🔄 Beta</span>
                             </div>
                         @elseif ($firstItem->Url_Menu === 'lab/resampling/current')
                             <div class="ms-2">
                                 <span class="badge badge-pill bg-expreimental" title="Experimental Terhadap Fitur ini masih dalam tahap uji coba. Bisa berubah drastis.">🚧 EXP</span>
                             </div>
                         @elseif ($firstItem->Url_Menu === 'progress-sistem/uji-analisa')
                             <div class="ms-2">
                                 <span class="badge badge-pill bg-expreimental" title="Experimental Terhadap Fitur ini masih dalam tahap uji coba. Bisa berubah drastis.">🚧 EXP</span>
                             </div>
                         @elseif ($firstItem->Url_Menu === '/pengajuan-uji-buka-sampel/current')
                             <div class="ms-2">
                                 <span class="badge badge-pill bg-beta" title="Fitur ini sudah bisa digunakan, namun belum versi final.">🔄 Beta</span>
                             </div> --}}
                     @endif
                 </a>

                 {{-- Sub Menu --}}
                 @if ($items->whereNotNull('Nama_Sub_Menu')->count())
                     <ul class="nav-submenu">
                         @foreach ($items->whereNotNull('Nama_Sub_Menu') as $sub)
                             @php
                                 $isSubActive = request()->is($sub->Url_Sub_Menu . '*') ? 'active' : '';
                             @endphp
                             <li>
                                 <a href="{{ url($sub->Url_Sub_Menu) }}" class="{{ $isSubActive }}">
                                     {{ $sub->Nama_Sub_Menu }}
                                 </a>
                             </li>
                         @endforeach
                     </ul>
                 @endif
             </li>
         @endif
     @endforeach
 </ul>
        </div>
    </div>
    <div class="sidebar-background"></div>
</div>

<div class="vertical-overlay"></div>
