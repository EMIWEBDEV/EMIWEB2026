
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
    {{-- Pencarian menu: murni di sisi klien, menyaring menu yang SUDAH dirender
         sehingga hak akses pengguna otomatis terhormati. --}}
    <div class="menu-search-box">
        <div class="menu-search-input">
            <i class="ri-search-line menu-search-icon"></i>
            <input type="text"
                   id="sidebarMenuSearch"
                   class="form-control"
                   placeholder="Cari menu..."
                   autocomplete="off"
                   spellcheck="false">
            <button type="button" id="sidebarMenuSearchClear" class="menu-search-clear" hidden>
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div id="sidebarMenuSearchEmpty" class="menu-search-empty" hidden>
            <i class="ri-inbox-line"></i>
            <span>Menu tidak ditemukan</span>
        </div>
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

<style>
    /* ================= KOTAK PENCARIAN MENU ================= */
    .menu-search-box {
        padding: 10px 16px 6px;
    }

    .menu-search-input {
        position: relative;
    }

    .menu-search-input .form-control {
        height: 36px;
        padding: 0 30px 0 32px;
        font-size: 0.8125rem;
        border-radius: 6px;
        background-color: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #fff;
        transition: background-color .15s, border-color .15s;
    }

    .menu-search-input .form-control::placeholder {
        color: rgba(255, 255, 255, 0.45);
    }

    .menu-search-input .form-control:focus {
        background-color: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.35);
        box-shadow: none;
        color: #fff;
    }

    .menu-search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 15px;
        color: rgba(255, 255, 255, 0.5);
        pointer-events: none;
    }

    .menu-search-clear {
        position: absolute;
        right: 6px;
        top: 50%;
        transform: translateY(-50%);
        width: 22px;
        height: 22px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        font-size: 13px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .menu-search-clear:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .menu-search-empty {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
        padding: 8px 10px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.06);
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.75rem;
    }

    /* Penanda potongan kata yang cocok */
    .menu-search-hit {
        background: #f7b84b;
        color: #1b1f3b;
        border-radius: 2px;
        padding: 0 1px;
        font-weight: 600;
    }

    /* Sembunyikan kotak pencarian saat sidebar dalam mode ikon kecil */
    .vertical-menu-sm-hover .menu-search-box,
    [data-sidebar-size="sm"] .menu-search-box,
    [data-sidebar-size="sm-hover"] .menu-search-box {
        display: none;
    }
</style>

<script>
(function () {
    'use strict';

    /**
     * Pencarian menu sidebar.
     *
     * Bekerja sepenuhnya di sisi klien terhadap menu yang SUDAH dirender Blade.
     * Karena Blade hanya merender menu sesuai N_EMI_LAB_Page_Access_2, menu yang
     * tidak menjadi hak akses pengguna memang tidak ada di DOM sehingga mustahil
     * ikut muncul di hasil pencarian.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var input     = document.getElementById('sidebarMenuSearch');
        var clearBtn  = document.getElementById('sidebarMenuSearchClear');
        var emptyMsg  = document.getElementById('sidebarMenuSearchEmpty');
        var navRoot   = document.getElementById('navbar-nav');

        if (!input || !navRoot) return;

        /* ---------- Kumpulkan seluruh item menu yang bisa diklik ---------- */
        var items = [];

        navRoot.querySelectorAll('a.nav-link').forEach(function (link) {
            // Lewati tautan pembuka collapse (bukan menu tujuan).
            if (link.getAttribute('data-bs-toggle') === 'collapse') return;

            var li = link.closest('li.nav-item');
            if (!li) return;

            // Simpan teks asli agar penyorotan bisa dibatalkan.
            var labelNode = link.querySelector('span[data-key]');
            var teks = (labelNode ? labelNode.textContent : link.textContent) || '';
            teks = teks.replace(/\s+/g, ' ').trim();

            // Buang label lencana seperti "Baru" agar tidak ikut tercari.
            link.querySelectorAll('.badge').forEach(function (b) {
                teks = teks.replace(b.textContent.trim(), '').trim();
            });

            items.push({
                li: li,
                link: link,
                labelNode: labelNode,
                teksAsli: teks,
                teksCari: teks.toLowerCase()
            });
        });

        if (!items.length) return;

        /* ---------- Simpan setiap wadah collapse beserta induknya ---------- */
        var collapses = [];

        navRoot.querySelectorAll('.collapse.menu-dropdown').forEach(function (box) {
            collapses.push({
                box: box,
                li: box.closest('li.nav-item'),
                toggle: navRoot.querySelector('a[href="#' + box.id + '"]'),
                // Keadaan awal dicatat supaya bisa dipulihkan saat pencarian dikosongkan.
                terbukaAwal: box.classList.contains('show')
            });
        });

        var judulKategori = navRoot.querySelectorAll('li.menu-title');

        /* ---------- Penyorotan teks yang cocok ---------- */
        function escapeHtml(s) {
            return s.replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function sorot(item, kata) {
            var target = item.labelNode;
            if (!target) return;

            if (!kata) {
                target.textContent = item.teksAsli;
                return;
            }

            var posisi = item.teksCari.indexOf(kata);
            if (posisi === -1) {
                target.textContent = item.teksAsli;
                return;
            }

            var depan  = item.teksAsli.slice(0, posisi);
            var cocok  = item.teksAsli.slice(posisi, posisi + kata.length);
            var buntut = item.teksAsli.slice(posisi + kata.length);

            target.innerHTML = escapeHtml(depan)
                + '<mark class="menu-search-hit">' + escapeHtml(cocok) + '</mark>'
                + escapeHtml(buntut);
        }

        /* ---------- Kembalikan sidebar ke keadaan semula ---------- */
        function pulihkan() {
            items.forEach(function (it) {
                it.li.hidden = false;
                sorot(it, '');
            });

            collapses.forEach(function (c) {
                if (c.li) c.li.hidden = false;
                c.box.classList.toggle('show', c.terbukaAwal);
                if (c.toggle) {
                    c.toggle.setAttribute('aria-expanded', c.terbukaAwal ? 'true' : 'false');
                    c.toggle.classList.toggle('collapsed', !c.terbukaAwal);
                }
            });

            judulKategori.forEach(function (t) { t.hidden = false; });
            emptyMsg.hidden = true;
        }

        /* ---------- Saring menu sesuai kata kunci ---------- */
        function saring(kata) {
            var jumlahCocok = 0;

            items.forEach(function (it) {
                var cocok = it.teksCari.indexOf(kata) !== -1;
                it.li.hidden = !cocok;
                sorot(it, cocok ? kata : '');
                if (cocok) jumlahCocok++;
            });

            // Wadah collapse hanya ditampilkan bila masih menyisakan item cocok.
            // Diproses dari dalam ke luar agar sub-header ikut diperhitungkan.
            for (var i = collapses.length - 1; i >= 0; i--) {
                var c = collapses[i];
                var adaIsi = c.box.querySelector('li.nav-item:not([hidden])') !== null;

                if (c.li) c.li.hidden = !adaIsi;

                // Buka otomatis supaya hasil pencarian langsung terlihat.
                c.box.classList.toggle('show', adaIsi);
                if (c.toggle) {
                    c.toggle.setAttribute('aria-expanded', adaIsi ? 'true' : 'false');
                    c.toggle.classList.toggle('collapsed', !adaIsi);
                }
            }

            // Judul kategori disembunyikan bila seluruh menu di bawahnya tersembunyi.
            judulKategori.forEach(function (judul) {
                var ada = false;
                var n = judul.nextElementSibling;

                while (n && !n.classList.contains('menu-title')) {
                    if (n.classList.contains('nav-item') && !n.hidden) { ada = true; break; }
                    n = n.nextElementSibling;
                }

                judul.hidden = !ada;
            });

            emptyMsg.hidden = jumlahCocok > 0;
        }

        /* ---------- Penanganan input (ditunda sesaat agar ringan) ---------- */
        var timer = null;

        function jalankan() {
            var kata = input.value.trim().toLowerCase();
            clearBtn.hidden = kata.length === 0;

            if (!kata) { pulihkan(); return; }
            saring(kata);
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(jalankan, 120);
        });

        // Enter membuka menu pertama yang cocok.
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                input.value = '';
                jalankan();
                input.blur();
                return;
            }

            if (e.key === 'Enter') {
                var pertama = items.find(function (it) { return !it.li.hidden; });
                if (pertama && pertama.link.href) {
                    e.preventDefault();
                    window.location.href = pertama.link.href;
                }
            }
        });

        clearBtn.addEventListener('click', function () {
            input.value = '';
            jalankan();
            input.focus();
        });

        // Pintasan Ctrl+K / Cmd+K untuk melompat ke kotak pencarian.
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                input.focus();
                input.select();
            }
        });
    });
})();
</script>
