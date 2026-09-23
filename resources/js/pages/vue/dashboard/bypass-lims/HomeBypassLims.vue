<template>
    <div class="bp">
        <!-- ================= TOPBAR ================= -->
        <header class="bp-topbar">
            <div class="bp-topbar__inner">
                <div class="bp-brand">
                    <div class="bp-brand__mark">
                        <svg viewBox="0 0 40 40" fill="none">
                            <defs>
                                <linearGradient id="gBrand" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#5a6bb0" />
                                    <stop offset="100%" stop-color="#0ab39c" />
                                </linearGradient>
                            </defs>
                            <path
                                d="M20 3 L34 10 V22 C34 30 27.5 35.5 20 37.5 C12.5 35.5 6 30 6 22 V10 Z"
                                fill="url(#gBrand)"
                            />
                            <path
                                d="M15 19.5 L18.5 23 L25.5 16"
                                stroke="#fff"
                                stroke-width="2.6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </div>
                    <div class="bp-brand__txt">
                        <h1>LIMS Toolkit</h1>
                        <p>Panel Bypass &middot; PT. Evo Manufacturing Indonesia</p>
                    </div>
                </div>

                <nav v-if="menuAktif" class="bp-crumb">
                    <button class="bp-crumb__home" @click="kembaliKeMenu">
                        <i class="ri-apps-2-line"></i> Menu
                    </button>
                    <i class="ri-arrow-right-s-line bp-crumb__sep"></i>
                    <span class="bp-crumb__now">{{ kartuAktif.judul }}</span>
                </nav>

                <div class="bp-topbar__right">
                    <div class="bp-clock">
                        <i class="ri-time-line"></i>
                        <span>{{ jamSekarang }}</span>
                    </div>
                    <span class="bp-chip-live">
                        <span class="bp-dot"></span> Mode Bypass
                    </span>
                </div>
            </div>
        </header>

        <!-- ================= HERO / MENU ================= -->
        <main class="bp-main">
            <transition name="bp-fade" mode="out-in">
                <!-- ---------- HALAMAN MENU ---------- -->
                <section v-if="!menuAktif" key="menu">
                    <div class="bp-hero">
                        <div class="bp-hero__bg">
                            <svg viewBox="0 0 1200 320" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="gHero" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0%" stop-color="#2c3163" />
                                        <stop offset="55%" stop-color="#405189" />
                                        <stop offset="100%" stop-color="#0ab39c" />
                                    </linearGradient>
                                    <pattern
                                        id="pGrid"
                                        width="34"
                                        height="34"
                                        patternUnits="userSpaceOnUse"
                                    >
                                        <path
                                            d="M34 0H0V34"
                                            fill="none"
                                            stroke="rgba(255,255,255,.07)"
                                            stroke-width="1"
                                        />
                                    </pattern>
                                </defs>
                                <rect width="1200" height="320" fill="url(#gHero)" />
                                <rect width="1200" height="320" fill="url(#pGrid)" />
                                <circle cx="1040" cy="60" r="180" fill="rgba(255,255,255,.05)" />
                                <circle cx="1150" cy="250" r="120" fill="rgba(255,255,255,.04)" />
                            </svg>
                        </div>

                        <div class="bp-hero__body">
                            <div class="bp-hero__left">
                                <span class="bp-hero__tag">
                                    <i class="ri-shield-keyhole-line"></i>
                                    Akses Terproteksi Gembok
                                </span>
                                <h2>
                                    Alat Bantu Administratif
                                    <span>Laboratorium</span>
                                </h2>
                                <p>
                                    Empat perkakas untuk menangani kasus yang tidak
                                    bisa diselesaikan lewat alur normal — menambah
                                    sub sampel, membatalkan registrasi, membatalkan
                                    uji, dan menyetel template printer TSC.
                                </p>

                                <div class="bp-hero__meta">
                                    <div class="bp-hero__metaitem">
                                        <i class="ri-server-line"></i>
                                        <div>
                                            <strong>{{ hostAplikasi }}</strong>
                                            <small>Server Aplikasi</small>
                                        </div>
                                    </div>
                                    <div class="bp-hero__metaitem">
                                        <i class="ri-calendar-check-line"></i>
                                        <div>
                                            <strong>{{ tanggalSekarang }}</strong>
                                            <small>Tanggal Akses</small>
                                        </div>
                                    </div>
                                    <div class="bp-hero__metaitem">
                                        <i class="ri-fingerprint-line"></i>
                                        <div>
                                            <strong>{{ sidikGembok }}</strong>
                                            <small>Sidik Kunci</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bp-hero__art">
                                <svg viewBox="0 0 260 260" fill="none">
                                    <circle
                                        cx="130"
                                        cy="130"
                                        r="96"
                                        stroke="rgba(255,255,255,.22)"
                                        stroke-width="1.5"
                                        stroke-dasharray="5 7"
                                        class="bp-spin"
                                    />
                                    <circle
                                        cx="130"
                                        cy="130"
                                        r="72"
                                        stroke="rgba(255,255,255,.3)"
                                        stroke-width="1.5"
                                    />
                                    <rect
                                        x="98"
                                        y="116"
                                        width="64"
                                        height="52"
                                        rx="9"
                                        fill="#fff"
                                        fill-opacity=".95"
                                    />
                                    <path
                                        d="M110 116v-11a20 20 0 0 1 40 0v11"
                                        stroke="#fff"
                                        stroke-width="7"
                                        stroke-linecap="round"
                                    />
                                    <circle cx="130" cy="138" r="7" fill="#405189" />
                                    <rect
                                        x="127"
                                        y="141"
                                        width="6"
                                        height="14"
                                        rx="3"
                                        fill="#405189"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Peringatan -->
                    <div class="bp-alert">
                        <div class="bp-alert__icon">
                            <i class="ri-alarm-warning-line"></i>
                        </div>
                        <div class="bp-alert__txt">
                            <strong>Perhatian — data produksi langsung.</strong>
                            Setiap tindakan di panel ini mengubah basis data tanpa
                            melewati validasi berjenjang. Seluruh aksi tercatat di
                            log sistem beserta alamat IP dan waktu eksekusi.
                        </div>
                    </div>

                    <!-- KARTU 2x2 -->
                    <div class="row g-4">
                        <div
                            v-for="(kartu, i) in daftarKartu"
                            :key="kartu.kode"
                            class="col-12 col-lg-6"
                        >
                            <article
                                class="bp-card"
                                :class="`bp-card--${kartu.warna}`"
                                :style="{ animationDelay: `${i * 70}ms` }"
                                @click="bukaMenu(kartu.kode)"
                            >
                                <div class="bp-card__glow"></div>

                                <div class="bp-card__top">
                                    <div class="bp-card__icon">
                                        <svg viewBox="0 0 48 48" fill="none">
                                            <rect
                                                width="48"
                                                height="48"
                                                rx="14"
                                                :fill="`url(#g-${kartu.kode})`"
                                            />
                                            <defs>
                                                <linearGradient
                                                    :id="`g-${kartu.kode}`"
                                                    x1="0"
                                                    y1="0"
                                                    x2="1"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="0%"
                                                        :stop-color="kartu.grad[0]"
                                                    />
                                                    <stop
                                                        offset="100%"
                                                        :stop-color="kartu.grad[1]"
                                                    />
                                                </linearGradient>
                                            </defs>
                                            <g
                                                transform="translate(12,12)"
                                                stroke="#fff"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                fill="none"
                                            >
                                                <path :d="kartu.path" />
                                            </g>
                                        </svg>
                                    </div>

                                    <span class="bp-card__no">
                                        {{ String(i + 1).padStart(2, "0") }}
                                    </span>
                                </div>

                                <h3 class="bp-card__title">{{ kartu.judul }}</h3>
                                <p class="bp-card__desc">{{ kartu.deskripsi }}</p>

                                <ul class="bp-card__points">
                                    <li v-for="p in kartu.poin" :key="p">
                                        <i class="ri-checkbox-circle-fill"></i>
                                        {{ p }}
                                    </li>
                                </ul>

                                <div class="bp-card__foot">
                                    <span class="bp-card__tag">{{ kartu.tabel }}</span>
                                    <span class="bp-card__cta">
                                        Buka Perkakas
                                        <i class="ri-arrow-right-line"></i>
                                    </span>
                                </div>
                            </article>
                        </div>
                    </div>

                    <!-- Referensi status -->
                    <div class="bp-legend">
                        <h4>
                            <i class="ri-book-2-line"></i>
                            Referensi Status Data
                        </h4>
                        <div class="row g-3">
                            <div
                                v-for="ref in referensiStatus"
                                :key="ref.kode"
                                class="col-6 col-lg-3"
                            >
                                <div class="bp-legend__item">
                                    <span
                                        class="bp-legend__badge"
                                        :class="`bg-${ref.warna}-subtle text-${ref.warna}`"
                                    >
                                        {{ ref.kode }}
                                    </span>
                                    <div>
                                        <strong>{{ ref.judul }}</strong>
                                        <small>{{ ref.arti }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ---------- FITUR 1: SUB SAMPEL ---------- -->
                <section v-else-if="menuAktif === 'sub-sampel'" key="sub">
                    <PanelHead
                        :kartu="kartuAktif"
                        @kembali="kembaliKeMenu"
                    />

                    <div class="bp-search">
                        <div class="bp-search__field">
                            <i class="ri-search-2-line"></i>
                            <input
                                v-model.trim="sub.noSampel"
                                type="text"
                                placeholder="Masukkan nomor sampel — contoh: FS0926-0001"
                                @keyup.enter="cariSubSampel"
                            />
                        </div>
                        <button
                            class="bp-btn bp-btn--primary"
                            :disabled="sub.loading || !sub.noSampel"
                            @click="cariSubSampel"
                        >
                            <span v-if="sub.loading" class="bp-spinner"></span>
                            <i v-else class="ri-search-line"></i>
                            Cari Sampel
                        </button>
                    </div>

                    <EmptyState
                        v-if="!sub.data && !sub.loading"
                        ikon="ri-inbox-line"
                        judul="Belum ada sampel dimuat"
                        pesan="Masukkan nomor sampel di atas untuk melihat informasi registrasi dan sub sampelnya."
                    />

                    <template v-if="sub.data">
                        <!-- Status multi / single QR code -->
                        <div
                            class="bp-qrstate"
                            :class="bolehTambahSub ? 'bp-qrstate--ok' : 'bp-qrstate--no'"
                        >
                            <div class="bp-qrstate__ic">
                                <i
                                    :class="
                                        bolehTambahSub
                                            ? 'ri-qr-code-line'
                                            : 'ri-forbid-2-line'
                                    "
                                ></i>
                            </div>
                            <div class="bp-qrstate__body">
                                <div class="bp-qrstate__top">
                                    <strong>
                                        {{
                                            bolehTambahSub
                                                ? "Sampel Multi QR Code"
                                                : "Sampel Single QR Code"
                                        }}
                                    </strong>
                                    <span
                                        class="bp-badge"
                                        :class="bolehTambahSub ? 'bp-badge--ok' : 'bp-badge--err'"
                                    >
                                        {{ sub.data.multi_qrcode.mode }}
                                    </span>
                                </div>
                                <p>{{ sub.data.multi_qrcode.alasan }}</p>

                                <div class="bp-qrstate__meta">
                                    <span>
                                        <i class="ri-settings-5-line"></i>
                                        Mesin:
                                        <b>{{ sub.data.multi_qrcode.nama_mesin || "—" }}</b>
                                    </span>
                                    <span>
                                        <i class="ri-flag-line"></i>
                                        Flag Multi QR:
                                        <b>{{ sub.data.multi_qrcode.flag || "NULL" }}</b>
                                    </span>
                                    <span>
                                        <i class="ri-printer-line"></i>
                                        Jumlah Print QR:
                                        <b>{{ sub.data.multi_qrcode.jumlah_print }}</b>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi -->
                        <div class="bp-sect">
                            <div class="bp-sect__head">
                                <h3><i class="ri-information-2-line"></i> Informasi Sampel</h3>
                                <StatusPill :sampel="sub.data.sampel" />
                            </div>

                            <KartuSampel :sampel="sub.data.sampel" warna="primary" />

                            <div class="bp-metrics">
                                <MetricBox
                                    :nilai="sub.data.total_sub_sampel"
                                    label="Sub Sampel Saat Ini"
                                    ikon="ri-qr-code-line"
                                    warna="primary"
                                />
                                <MetricBox
                                    :nilai="sub.jumlah || 0"
                                    label="Akan Ditambahkan"
                                    ikon="ri-add-circle-line"
                                    warna="success"
                                />
                                <MetricBox
                                    :nilai="sub.data.total_sub_sampel + (sub.jumlah || 0)"
                                    label="Total Setelah Simpan"
                                    ikon="ri-sum"
                                    warna="info"
                                />
                            </div>
                        </div>

                        <!-- Jumlah -->
                        <div v-if="bolehTambahSub" class="bp-sect">
                            <div class="bp-sect__head">
                                <h3><i class="ri-add-box-line"></i> Jumlah Sub Sampel Ditambah</h3>
                            </div>

                            <div class="bp-qty">
                                <button
                                    v-for="n in [1, 2, 3, 5, 10]"
                                    :key="n"
                                    class="bp-qty__btn"
                                    :class="{ 'is-on': sub.jumlah === n }"
                                    @click="pilihJumlahSub(n)"
                                >
                                    +{{ n }}
                                </button>
                                <div class="bp-qty__custom">
                                    <label>Lainnya</label>
                                    <input
                                        v-model.number="sub.jumlah"
                                        type="number"
                                        min="1"
                                        max="200"
                                        @input="mintaPreviewSub"
                                    />
                                </div>
                            </div>

                            <p class="bp-note">
                                <i class="ri-lightbulb-flash-line"></i>
                                Sub sampel baru mewarisi <b>Id_User</b>, <b>Tanggal</b>,
                                dan <b>Jam</b> dari registrasi induk — bukan dari sesi
                                yang membuka panel ini.
                            </p>
                        </div>

                        <!-- Sebelum / Sesudah -->
                        <div v-if="bolehTambahSub && sub.preview" class="row g-4">
                            <div class="col-12 col-xl-6">
                                <div class="bp-sect bp-sect--flat h-100">
                                    <div class="bp-sect__head">
                                        <h3 class="text-muted">
                                            <i class="ri-history-line"></i> Sebelum
                                        </h3>
                                        <span class="bp-badge bp-badge--muted">
                                            {{ sub.preview.total_sebelum }} sub
                                        </span>
                                    </div>
                                    <div class="bp-list">
                                        <p
                                            v-if="!sub.preview.sebelum.length"
                                            class="bp-list__empty"
                                        >
                                            Belum ada sub sampel.
                                        </p>
                                        <div
                                            v-for="it in sub.preview.sebelum"
                                            :key="it.id"
                                            class="bp-list__row"
                                        >
                                            <span class="bp-list__no">{{ it.No_Po_Multi }}</span>
                                            <small>
                                                {{ formatTanggal(it.Tanggal) }}
                                                {{ jamSaja(it.Jam) }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-xl-6">
                                <div class="bp-sect bp-sect--ok h-100">
                                    <div class="bp-sect__head">
                                        <h3 class="text-success">
                                            <i class="ri-add-circle-line"></i> Sesudah
                                        </h3>
                                        <span class="bp-badge bp-badge--ok">
                                            {{ sub.preview.total_sesudah }} sub
                                        </span>
                                    </div>
                                    <div class="bp-list">
                                        <div
                                            v-for="it in sub.preview.sesudah_baru"
                                            :key="it.No_Po_Multi"
                                            class="bp-list__row bp-list__row--new"
                                        >
                                            <span class="bp-list__no">{{ it.No_Po_Multi }}</span>
                                            <span class="bp-badge bp-badge--ok">BARU</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="bolehTambahSub" class="bp-actions">
                            <button
                                class="bp-btn bp-btn--success bp-btn--lg"
                                :disabled="sub.saving || !sub.jumlah"
                                @click="simpanSubSampel"
                            >
                                <span v-if="sub.saving" class="bp-spinner"></span>
                                <i v-else class="ri-save-3-line"></i>
                                Simpan {{ sub.jumlah }} Sub Sampel
                            </button>
                        </div>
                    </template>
                </section>

                <!-- ---------- FITUR 2: BATAL REGISTRASI ---------- -->
                <section v-else-if="menuAktif === 'registrasi'" key="reg">
                    <PanelHead :kartu="kartuAktif" @kembali="kembaliKeMenu" />

                    <div class="bp-search">
                        <div class="bp-search__field">
                            <i class="ri-search-2-line"></i>
                            <input
                                v-model.trim="reg.noSampel"
                                type="text"
                                placeholder="Masukkan nomor sampel — contoh: FS0926-0001"
                                @keyup.enter="cariRegistrasi"
                            />
                        </div>
                        <button
                            class="bp-btn bp-btn--warning"
                            :disabled="reg.loading || !reg.noSampel"
                            @click="cariRegistrasi"
                        >
                            <span v-if="reg.loading" class="bp-spinner"></span>
                            <i v-else class="ri-search-line"></i>
                            Cari Sampel
                        </button>
                    </div>

                    <EmptyState
                        v-if="!reg.data && !reg.loading"
                        ikon="ri-file-search-line"
                        judul="Belum ada sampel dimuat"
                        pesan="Cari nomor sampel untuk meninjau registrasinya sebelum dibatalkan."
                    />

                    <template v-if="reg.data">
                        <div
                            v-if="reg.data.sudah_dibatalkan"
                            class="bp-alert bp-alert--danger"
                        >
                            <div class="bp-alert__icon">
                                <i class="ri-close-circle-line"></i>
                            </div>
                            <div class="bp-alert__txt">
                                <strong>Sampel sudah dibatalkan.</strong>
                                Registrasi ini sudah berstatus <code>Y</code> dan
                                tidak bisa dibatalkan lagi.
                            </div>
                        </div>

                        <div class="bp-sect">
                            <div class="bp-sect__head">
                                <h3><i class="ri-information-2-line"></i> Informasi Sampel</h3>
                                <StatusPill :sampel="reg.data.sampel" />
                            </div>

                            <KartuSampel :sampel="reg.data.sampel" warna="warning" />

                            <div class="bp-metrics">
                                <MetricBox :nilai="reg.data.jumlah_uji_aktif" label="Uji Masih Aktif" ikon="ri-flask-line" warna="danger" />
                                <MetricBox :nilai="reg.data.total_sub_sampel" label="Sub Sampel" ikon="ri-qr-code-line" warna="primary" />
                                <MetricBox
                                    :nilai="reg.data.sampel.Flag_Selesai === 'Y' ? 'Selesai' : 'Berjalan'"
                                    label="Status Uji"
                                    ikon="ri-progress-4-line"
                                    warna="info"
                                />
                            </div>
                        </div>

                        <div v-if="!reg.data.sudah_dibatalkan" class="bp-sect">
                            <div class="bp-sect__head">
                                <h3><i class="ri-edit-2-line"></i> Detail Pembatalan</h3>
                            </div>

                            <label class="bp-label">Alasan Pembatalan <span>(opsional)</span></label>
                            <input
                                v-model.trim="reg.alasan"
                                type="text"
                                class="bp-input"
                                maxlength="255"
                                placeholder="Contoh: salah input mesin"
                            />

                            <div
                                v-if="reg.data.jumlah_uji_aktif > 0"
                                class="bp-switch"
                                :class="{ 'is-on': reg.ikutUji }"
                                @click="reg.ikutUji = !reg.ikutUji"
                            >
                                <span class="bp-switch__track">
                                    <span class="bp-switch__knob"></span>
                                </span>
                                <div>
                                    <strong>Batalkan juga hasil ujinya</strong>
                                    <small>
                                        {{ reg.data.jumlah_uji_aktif }} baris uji aktif
                                        ikut diberi status dibatalkan.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div v-if="!reg.data.sudah_dibatalkan" class="bp-actions">
                            <button
                                class="bp-btn bp-btn--danger bp-btn--lg"
                                :disabled="reg.saving"
                                @click="prosesBatalRegistrasi"
                            >
                                <span v-if="reg.saving" class="bp-spinner"></span>
                                <i v-else class="ri-close-circle-line"></i>
                                Batalkan Registrasi Sampel
                            </button>
                        </div>
                    </template>
                </section>

                <!-- ---------- FITUR 3: BATAL UJI ---------- -->
                <section v-else-if="menuAktif === 'uji-sampel'" key="uji">
                    <PanelHead :kartu="kartuAktif" @kembali="kembaliKeMenu" />

                    <div class="bp-search">
                        <div class="bp-search__field">
                            <i class="ri-search-2-line"></i>
                            <input
                                v-model.trim="uji.noSampel"
                                type="text"
                                placeholder="Masukkan nomor sampel — contoh: FS0926-0001"
                                @keyup.enter="cariUji"
                            />
                        </div>
                        <button
                            class="bp-btn bp-btn--danger"
                            :disabled="uji.loading || !uji.noSampel"
                            @click="cariUji"
                        >
                            <span v-if="uji.loading" class="bp-spinner"></span>
                            <i v-else class="ri-search-line"></i>
                            Cari Sampel
                        </button>
                    </div>

                    <EmptyState
                        v-if="!uji.data && !uji.loading"
                        ikon="ri-flask-line"
                        judul="Belum ada sampel dimuat"
                        pesan="Cari nomor sampel untuk melihat jenis analisa yang sudah diuji."
                    />

                    <template v-if="uji.data">
                        <div class="bp-sect">
                            <div class="bp-sect__head">
                                <h3><i class="ri-information-2-line"></i> Informasi Sampel</h3>
                                <StatusPill :sampel="uji.data.sampel" />
                            </div>

                            <KartuSampel :sampel="uji.data.sampel" warna="danger" />

                            <div class="bp-metrics">
                                <MetricBox :nilai="uji.data.total_jenis" label="Jenis Analisa" ikon="ri-test-tube-line" warna="info" />
                                <MetricBox :nilai="uji.data.total_uji_aktif" label="Baris Uji Aktif" ikon="ri-play-circle-line" warna="success" />
                                <MetricBox :nilai="uji.data.total_uji_batal" label="Sudah Dibatalkan" ikon="ri-close-circle-line" warna="danger" />
                            </div>
                        </div>

                        <!-- Pilih analisa -->
                        <div class="bp-sect">
                            <div class="bp-sect__head">
                                <h3><i class="ri-list-check-2"></i> Pilih Jenis Analisa</h3>
                                <div
                                    class="bp-switch bp-switch--sm"
                                    :class="{ 'is-on': uji.semua }"
                                    @click="uji.semua = !uji.semua; toggleSemuaAnalisa()"
                                >
                                    <span class="bp-switch__track">
                                        <span class="bp-switch__knob"></span>
                                    </span>
                                    <strong>Semua Analisa</strong>
                                </div>
                            </div>

                            <p v-if="!uji.data.analisa.length" class="bp-list__empty">
                                Sampel ini belum punya data uji.
                            </p>

                            <div v-else class="bp-table-wrap">
                                <table class="bp-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 48px"></th>
                                            <th>Jenis Analisa</th>
                                            <th class="text-center">Aktif</th>
                                            <th class="text-center">Batal</th>
                                            <th>Penginput</th>
                                            <th>Waktu Uji</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="row in uji.data.analisa"
                                            :key="row.Id_Jenis_Analisa"
                                            :class="{
                                                'is-picked': uji.semua || uji.dipilih.includes(row.Id_Jenis_Analisa),
                                                'is-off': row.total_aktif === 0,
                                            }"
                                            @click="pilihBarisAnalisa(row)"
                                        >
                                            <td>
                                                <span
                                                    class="bp-check"
                                                    :class="{
                                                        'is-on': uji.semua || uji.dipilih.includes(row.Id_Jenis_Analisa),
                                                    }"
                                                >
                                                    <i class="ri-check-line"></i>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="bp-table__name">
                                                    {{ row.Jenis_Analisa || "(tanpa nama)" }}
                                                </div>
                                                <small class="text-muted">{{ row.Kode_Analisa }}</small>
                                            </td>
                                            <td class="text-center">
                                                <span
                                                    class="bp-badge"
                                                    :class="row.total_aktif ? 'bp-badge--ok' : 'bp-badge--muted'"
                                                >
                                                    {{ row.total_aktif }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="bp-badge bp-badge--err">
                                                    {{ row.total_batal }}
                                                </span>
                                            </td>
                                            <td>{{ row.Nama_User_Input }}</td>
                                            <td>
                                                <small>
                                                    {{ formatTanggal(row.tanggal_akhir) }}
                                                    {{ jamSaja(row.jam_terakhir) }}
                                                </small>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="bp-sect">
                            <label class="bp-label">Alasan Pembatalan <span>(opsional)</span></label>
                            <input
                                v-model.trim="uji.alasan"
                                type="text"
                                class="bp-input"
                                maxlength="255"
                                placeholder="Contoh: hasil uji keliru, perlu input ulang"
                            />
                            <p class="bp-note mt-3">
                                <i class="ri-information-line"></i>
                                Baris uji lama tetap tersimpan sebagai jejak audit
                                (status <b>Y</b>). Penanda selesai direset agar sampel
                                bisa diinput ulang lewat registrasi normal.
                            </p>
                        </div>

                        <div class="bp-actions">
                            <button
                                class="bp-btn bp-btn--danger bp-btn--lg"
                                :disabled="uji.saving || !bisaBatalkanUji"
                                @click="prosesBatalUji"
                            >
                                <span v-if="uji.saving" class="bp-spinner"></span>
                                <i v-else class="ri-delete-bin-line"></i>
                                {{ labelTombolBatalUji }}
                            </button>
                        </div>
                    </template>
                </section>

                <!-- ---------- FITUR 4: PRINTER ---------- -->
                <section v-else-if="menuAktif === 'printer'" key="prn">
                    <PanelHead :kartu="kartuAktif" @kembali="kembaliKeMenu" />

                    <div class="bp-search">
                        <div class="bp-search__field bp-search__field--select">
                            <i class="ri-price-tag-3-line"></i>
                            <select v-model="printer.idTerpilih">
                                <option :value="null" disabled>
                                    -- Pilih template printer --
                                </option>
                                <option
                                    v-for="t in printer.daftar"
                                    :key="t.Id_Master_Printer_Templates"
                                    :value="t.Id_Master_Printer_Templates"
                                >
                                    {{ t.Nama_Template }} ({{ t.Lebar_Label }}×{{ t.Tinggi_Label }} mm)
                                </option>
                            </select>
                        </div>
                        <button
                            class="bp-btn bp-btn--info"
                            :disabled="printer.loading || !printer.idTerpilih"
                            @click="muatDetailTemplate"
                        >
                            <span v-if="printer.loading" class="bp-spinner"></span>
                            <i v-else class="ri-download-2-line"></i>
                            Pilih Template
                        </button>
                    </div>

                    <EmptyState
                        v-if="!printer.master && !printer.loading"
                        ikon="ri-printer-line"
                        judul="Belum ada template dipilih"
                        pesan="Pilih salah satu template untuk mengatur ukuran label dan posisi elemennya."
                    />

                    <template v-if="printer.master">
                        <div class="row g-4">
                            <!-- Pengaturan -->
                            <div class="col-12 col-xxl-7">
                                <div class="bp-sect">
                                    <div class="bp-sect__head">
                                        <h3><i class="ri-ruler-2-line"></i> Ukuran Label</h3>
                                        <span class="bp-badge bp-badge--info">
                                            TSC 203 dpi
                                        </span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="bp-label">Nama Template</label>
                                            <input
                                                v-model.trim="printer.master.Nama_Template"
                                                type="text"
                                                class="bp-input"
                                            />
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="bp-label">Lebar (mm)</label>
                                            <input v-model.number="printer.master.Lebar_Label" type="number" min="1" max="500" class="bp-input" />
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="bp-label">Tinggi (mm)</label>
                                            <input v-model.number="printer.master.Tinggi_Label" type="number" min="1" max="500" class="bp-input" />
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="bp-label">Gap (mm)</label>
                                            <input v-model.number="printer.master.Gap_Antar_Label" type="number" min="0" max="100" class="bp-input" />
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label class="bp-label">Direction</label>
                                            <select v-model.number="printer.master.Direction" class="bp-input">
                                                <option :value="0">0</option>
                                                <option :value="1">1</option>
                                            </select>
                                        </div>
                                    </div>

                                    <p class="bp-note mt-3">
                                        <i class="ri-ruler-line"></i>
                                        Label
                                        <b>{{ printer.master.Lebar_Label }}×{{ printer.master.Tinggi_Label }} mm</b>
                                        setara
                                        <b>{{ mmKeDot(printer.master.Lebar_Label) }}×{{ mmKeDot(printer.master.Tinggi_Label) }} dot</b>.
                                        Posisi X/Y elemen memakai satuan dot (8 dot = 1 mm).
                                    </p>
                                </div>

                                <div class="bp-sect">
                                    <div class="bp-sect__head">
                                        <h3><i class="ri-layout-masonry-line"></i> Elemen Label</h3>
                                        <div class="d-flex gap-2">
                                            <button class="bp-btn bp-btn--ghost bp-btn--sm" @click="tambahItem('TEXT')">
                                                <i class="ri-text"></i> Teks
                                            </button>
                                            <button class="bp-btn bp-btn--ghost bp-btn--sm" @click="tambahItem('QRCODE')">
                                                <i class="ri-qr-code-line"></i> QR
                                            </button>
                                        </div>
                                    </div>

                                    <p v-if="!printer.items.length" class="bp-list__empty">
                                        Belum ada elemen. Tambahkan teks atau QR code.
                                    </p>

                                    <div
                                        v-for="(item, idx) in printer.items"
                                        :key="idx"
                                        class="bp-el"
                                        :class="{ 'is-on': printer.itemAktif === idx }"
                                        @click="printer.itemAktif = idx"
                                    >
                                        <div class="bp-el__head">
                                            <span
                                                class="bp-badge"
                                                :class="item.Jenis === 'QRCODE' ? 'bp-badge--dark' : 'bp-badge--info'"
                                            >
                                                <i :class="item.Jenis === 'QRCODE' ? 'ri-qr-code-line' : 'ri-text'"></i>
                                                {{ item.Jenis }} #{{ idx + 1 }}
                                            </span>
                                            <button class="bp-el__del" @click.stop="hapusItem(idx)">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>

                                        <label class="bp-label">Isi Konten</label>
                                        <input
                                            v-model="item.Isi_Konten"
                                            type="text"
                                            class="bp-input bp-input--sm"
                                            placeholder="Teks bebas atau {placeholder}"
                                        />
                                        <div class="bp-ph">
                                            <button
                                                v-for="ph in placeholders"
                                                :key="ph"
                                                type="button"
                                                class="bp-ph__btn"
                                                @click.stop="sisipkanPlaceholder(item, ph)"
                                            >
                                                {{ ph }}
                                            </button>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-4 col-md-3">
                                                <label class="bp-label bp-label--xs">Posisi X</label>
                                                <input v-model.number="item.Posisi_X" type="number" class="bp-input bp-input--sm" />
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <label class="bp-label bp-label--xs">Posisi Y</label>
                                                <input v-model.number="item.Posisi_Y" type="number" class="bp-input bp-input--sm" />
                                            </div>
                                            <div class="col-4 col-md-3">
                                                <label class="bp-label bp-label--xs">Rotasi</label>
                                                <select v-model.number="item.Rotation" class="bp-input bp-input--sm">
                                                    <option :value="0">0°</option>
                                                    <option :value="90">90°</option>
                                                    <option :value="180">180°</option>
                                                    <option :value="270">270°</option>
                                                </select>
                                            </div>

                                            <template v-if="item.Jenis === 'TEXT'">
                                                <div class="col-4 col-md-3">
                                                    <label class="bp-label bp-label--xs">Font</label>
                                                    <select v-model="item.Font" class="bp-input bp-input--sm">
                                                        <option v-for="f in fontTsc" :key="f.nilai" :value="f.nilai">
                                                            {{ f.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <label class="bp-label bp-label--xs">Skala X</label>
                                                    <select v-model.number="item.Scale_X" class="bp-input bp-input--sm">
                                                        <option v-for="s in skala" :key="s" :value="s">{{ s }}×</option>
                                                    </select>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <label class="bp-label bp-label--xs">Skala Y</label>
                                                    <select v-model.number="item.Scale_Y" class="bp-input bp-input--sm">
                                                        <option v-for="s in skala" :key="s" :value="s">{{ s }}×</option>
                                                    </select>
                                                </div>
                                            </template>

                                            <template v-else>
                                                <div class="col-4 col-md-3">
                                                    <label class="bp-label bp-label--xs">Ukuran QR</label>
                                                    <select v-model.number="item.Qr_Size" class="bp-input bp-input--sm">
                                                        <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                                                    </select>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <label class="bp-label bp-label--xs">Koreksi</label>
                                                    <select v-model="item.Qr_Ecc" class="bp-input bp-input--sm">
                                                        <option value="L">L · 7%</option>
                                                        <option value="M">M · 15%</option>
                                                        <option value="Q">Q · 25%</option>
                                                        <option value="H">H · 30%</option>
                                                    </select>
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <label class="bp-label bp-label--xs">Model</label>
                                                    <select v-model="item.Qr_Model" class="bp-input bp-input--sm">
                                                        <option
                                                            v-for="m in modelQr"
                                                            :key="m.nilai"
                                                            :value="m.nilai"
                                                        >
                                                            {{ m.label }}
                                                        </option>
                                                    </select>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <div class="bp-actions bp-actions--split">
                                    <button
                                        class="bp-btn bp-btn--success bp-btn--lg"
                                        :disabled="printer.saving"
                                        @click="simpanTemplate"
                                    >
                                        <span v-if="printer.saving" class="bp-spinner"></span>
                                        <i v-else class="ri-save-3-line"></i>
                                        Simpan Template
                                    </button>
                                    <button
                                        class="bp-btn bp-btn--ghost bp-btn--lg"
                                        :disabled="printer.printing"
                                        @click="testPrint"
                                    >
                                        <span v-if="printer.printing" class="bp-spinner"></span>
                                        <i v-else class="ri-printer-line"></i>
                                        Test Print
                                    </button>
                                </div>
                            </div>

                            <!-- Pratinjau -->
                            <div class="col-12 col-xxl-5">
                                <div class="bp-sect bp-sticky">
                                    <div class="bp-sect__head">
                                        <h3><i class="ri-eye-line"></i> Pratinjau Label</h3>
                                        <span class="bp-badge bp-badge--muted">
                                            {{ skalaPreview.toFixed(2) }}×
                                        </span>
                                    </div>

                                    <div class="bp-canvas">
                                        <div class="bp-label-paper" :style="gayaLabel">
                                            <div
                                                v-for="(item, idx) in printer.items"
                                                :key="idx"
                                                class="bp-el-prev"
                                                :class="{ 'is-on': printer.itemAktif === idx }"
                                                :style="gayaElemen(item)"
                                                @click="printer.itemAktif = idx"
                                            >
                                                <div
                                                    v-if="item.Jenis === 'QRCODE'"
                                                    class="bp-qr"
                                                    :style="gayaQr(item)"
                                                >
                                                    <i class="ri-qr-code-line"></i>
                                                </div>
                                                <span v-else>{{ contohTeks(item.Isi_Konten) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bp-specs">
                                        <div><span>Ukuran fisik</span><b>{{ printer.master.Lebar_Label }}×{{ printer.master.Tinggi_Label }} mm</b></div>
                                        <div><span>Resolusi</span><b>{{ mmKeDot(printer.master.Lebar_Label) }}×{{ mmKeDot(printer.master.Tinggi_Label) }} dot</b></div>
                                        <div><span>Gap antar label</span><b>{{ printer.master.Gap_Antar_Label }} mm</b></div>
                                        <div><span>Jumlah elemen</span><b>{{ printer.items.length }}</b></div>
                                    </div>

                                    <label class="bp-label mt-3">Perintah TSPL</label>
                                    <pre class="bp-code">{{ tsplPreview }}</pre>
                                </div>
                            </div>
                        </div>
                    </template>
                </section>
            </transition>
        </main>

        <footer class="bp-foot">
            <span>LIMS Toolkit &middot; Panel Bypass</span>
            <span>PT. Evo Manufacturing Indonesia</span>
        </footer>
    </div>
</template>

<script>
import axios from "axios";
import Swal from "sweetalert2";
import QrcodeVue from "qrcode.vue";

// Gaya bersama (tidak di-scope) supaya komponen anak ikut tergaya.
import "./komponen/bypass-ui.css";

// Komponen dipisah jadi SFC karena build produksi memakai Vue runtime-only,
// sehingga komponen dengan opsi `template` string tidak akan dirender.
import InfoCell from "./komponen/InfoCell.vue";
import MetricBox from "./komponen/MetricBox.vue";
import PanelHead from "./komponen/PanelHead.vue";
import StatusPill from "./komponen/StatusPill.vue";
import EmptyState from "./komponen/EmptyState.vue";
import KartuSampel from "./komponen/KartuSampel.vue";

/** Dot per mm untuk printer 203 dpi (TSC TE244 / TTP-213). */
const DOT_PER_MM = 8;

export default {
    name: "HomeBypassLims",
    components: {
        QrcodeVue,
        InfoCell,
        MetricBox,
        PanelHead,
        StatusPill,
        EmptyState,
        KartuSampel,
    },

    props: {
        gembokSecret: { type: String, default: null },
    },

    data() {
        return {
            menuAktif: null,
            jamSekarang: "",
            timerJam: null,

            daftarKartu: [
                {
                    kode: "sub-sampel",
                    judul: "Menambahkan Sub Sampel",
                    deskripsi:
                        "Tambah sub sampel (multi QR) pada nomor sampel yang sudah teregistrasi.",
                    ikonRi: "ri-add-box-line",
                    warna: "primary",
                    grad: ["#405189", "#6b7cc4"],
                    tabel: "PO_Sampel_Multi_QrCode",
                    path: "M12 4v16M4 12h16",
                    poin: [
                        "Lihat penginput, mesin, dan total sub sampel",
                        "Bandingkan tampilan sebelum dan sesudah",
                        "Mewarisi Id_User, tanggal, dan jam induk",
                    ],
                },
                {
                    kode: "registrasi",
                    judul: "Membatalkan Registrasi Sampel",
                    deskripsi:
                        "Beri status dibatalkan pada registrasi sampel yang keliru.",
                    ikonRi: "ri-file-close-line",
                    warna: "warning",
                    grad: ["#f7b84b", "#ffd08a"],
                    tabel: "N_EMI_LAB_PO_Sampel",
                    path: "M6 4h9l5 5v11a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zM10 12l6 6M16 12l-6 6",
                    poin: [
                        "Tinjau data sampel sebelum membatalkan",
                        "Opsi ikut membatalkan uji yang masih aktif",
                        "Alasan pembatalan tercatat di keterangan",
                    ],
                },
                {
                    kode: "uji-sampel",
                    judul: "Membatalkan Uji Sampel",
                    deskripsi:
                        "Batalkan hasil uji per jenis analisa agar bisa diinput ulang.",
                    ikonRi: "ri-flask-line",
                    warna: "danger",
                    grad: ["#f06548", "#ff9b85"],
                    tabel: "N_EMI_LAB_Uji_Sampel",
                    path: "M9 3v6l-5 9a2 2 0 0 0 2 3h12a2 2 0 0 0 2-3l-5-9V3M8 3h8M7 15h10",
                    poin: [
                        "Rekap uji dikelompokkan per jenis analisa",
                        "Pilih sebagian atau centang semua analisa",
                        "Jejak lama tetap tersimpan untuk audit",
                    ],
                },
                {
                    kode: "printer",
                    judul: "Mengatur Template Printer",
                    deskripsi:
                        "Atur ukuran label, posisi teks, dan QR code untuk printer TSC.",
                    ikonRi: "ri-printer-line",
                    warna: "info",
                    grad: ["#299cdb", "#6fc7f5"],
                    tabel: "Master_Printer_Templates",
                    path: "M6 9V3h12v6M6 18H4a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M6 14h12v7H6z",
                    poin: [
                        "Pratinjau label skala nyata beserta TSPL",
                        "Atur posisi X/Y, font, skala, dan ukuran QR",
                        "Test print langsung ke printer TSC",
                    ],
                },
            ],

            referensiStatus: [
                { kode: "NULL", judul: "Status Aktif", arti: "Data masih berlaku dan terbaca alur normal.", warna: "success" },
                { kode: "Y", judul: "Dibatalkan", arti: "Baris di-flag batal, tetap tersimpan untuk audit.", warna: "danger" },
                { kode: "Y", judul: "Flag Selesai", arti: "Seluruh uji sampel sudah dinyatakan selesai.", warna: "info" },
                { kode: "8", judul: "Dot per mm", arti: "Konversi satuan printer TSC pada 203 dpi.", warna: "warning" },
            ],

            sub: { noSampel: "", jumlah: 1, loading: false, saving: false, data: null, preview: null },
            reg: { noSampel: "", alasan: "", ikutUji: false, loading: false, saving: false, data: null },
            uji: { noSampel: "", alasan: "", semua: false, dipilih: [], loading: false, saving: false, data: null },
            printer: {
                daftar: [], idTerpilih: null, master: null, items: [],
                itemAktif: null, loading: false, saving: false, printing: false,
            },

            placeholders: [
                "{qrData}", "{nama_sampel}", "{no_split}",
                "{batch}", "{tanggal}", "{namaMesin}", "{jenis_analisa}",
            ],

            fontTsc: [
                { nilai: "1", label: "1 · 8×12" },
                { nilai: "2", label: "2 · 12×20" },
                { nilai: "3", label: "3 · 16×24" },
                { nilai: "4", label: "4 · 24×32" },
                { nilai: "5", label: "5 · 32×48" },
                { nilai: "0", label: "0 · Monotype" },
            ],

            skala: [1, 2, 3, 4, 5, 6, 8, 10],

            // Model QR TSPL. Nilai di database memakai bentuk "M2,S7"
            // (model + mask), jadi opsi di sini harus mencakup keduanya.
            modelQr: [
                { nilai: "M2,S7", label: "M2,S7 · ukuran tetap" },
                { nilai: "M2", label: "M2 · standar" },
                { nilai: "M1", label: "M1 · lawas" },
            ],
        };
    },

    computed: {
        kartuAktif() {
            return (
                this.daftarKartu.find((k) => k.kode === this.menuAktif) ||
                this.daftarKartu[0]
            );
        },

        hostAplikasi() {
            return typeof window !== "undefined" ? window.location.host : "-";
        },

        tanggalSekarang() {
            return new Date().toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "long",
                year: "numeric",
            });
        },

        /** Potongan awal-akhir kunci, cukup untuk memastikan kunci yang benar. */
        sidikGembok() {
            const s = this.gembokSecret || "";
            if (s.length < 12) return "—";

            return `${s.slice(0, 4)}…${s.slice(-4)}`;
        },

        /**
         * Sub sampel hanya boleh ditambah bila mesin sampel diset multi QR code.
         * Server memutuskan ini; frontend sekadar mengikuti hasilnya.
         */
        bolehTambahSub() {
            return this.sub.data?.multi_qrcode?.boleh === true;
        },

        bisaBatalkanUji() {
            if (!this.uji.data) return false;
            if (this.uji.semua) return this.uji.data.total_uji_aktif > 0;

            return this.uji.dipilih.length > 0;
        },

        labelTombolBatalUji() {
            if (this.uji.semua) return "Batalkan Semua Analisa";
            if (!this.uji.dipilih.length) return "Pilih Analisa Dahulu";

            return `Batalkan ${this.uji.dipilih.length} Jenis Analisa`;
        },

        /** Skala pratinjau: lebar label dipetakan ke area maksimal 360 px. */
        skalaPreview() {
            const lebarDot = this.mmKeDot(this.printer.master?.Lebar_Label || 50);

            return lebarDot ? 360 / lebarDot : 1;
        },

        gayaLabel() {
            const m = this.printer.master;
            if (!m) return {};

            return {
                width: `${this.mmKeDot(m.Lebar_Label) * this.skalaPreview}px`,
                height: `${this.mmKeDot(m.Tinggi_Label) * this.skalaPreview}px`,
            };
        },

        tsplPreview() {
            const m = this.printer.master;
            if (!m) return "";

            let out = `SIZE ${m.Lebar_Label} mm, ${m.Tinggi_Label} mm\r\n`;
            out += `GAP ${m.Gap_Antar_Label || 0} mm, 0\r\n`;
            out += `DIRECTION ${m.Direction ?? 1}\r\n`;
            out += `CLS\r\n`;

            this.printer.items.forEach((it) => {
                // Samakan pelolosan kutip dengan yang dilakukan server.
                const isi = this.contohTeks(it.Isi_Konten)
                    .split("\\").join("\\\\")
                    .split('"').join('\\"');

                if (it.Jenis === "TEXT") {
                    out += `TEXT ${it.Posisi_X},${it.Posisi_Y},"${it.Font}",${it.Rotation},${it.Scale_X},${it.Scale_Y},"${isi}"\r\n`;
                } else {
                    out += `QRCODE ${it.Posisi_X},${it.Posisi_Y},${it.Qr_Ecc},${it.Qr_Size},A,${it.Rotation},${it.Qr_Model},"${isi}"\r\n`;
                }
            });

            return out + "PRINT 1,1\r\n";
        },
    },

    mounted() {
        axios.defaults.headers.common["X-CSRF-TOKEN"] =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

        // Kunci dikirim lewat header agar tidak ikut tercatat di log akses.
        if (this.gembokSecret) {
            axios.defaults.headers.common["X-Gembok-Secret"] = this.gembokSecret;
        }

        this.perbaruiJam();
        this.timerJam = setInterval(this.perbaruiJam, 1000);
    },

    beforeUnmount() {
        if (this.timerJam) clearInterval(this.timerJam);
    },

    methods: {
        perbaruiJam() {
            this.jamSekarang = new Date().toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
            });
        },

        bukaMenu(kode) {
            this.menuAktif = kode;

            if (kode === "printer" && !this.printer.daftar.length) {
                this.muatDaftarTemplate();
            }

            window.scrollTo({ top: 0, behavior: "smooth" });
        },

        kembaliKeMenu() {
            this.menuAktif = null;
            window.scrollTo({ top: 0, behavior: "smooth" });
        },

        mmKeDot(mm) {
            return Math.round((Number(mm) || 0) * DOT_PER_MM);
        },

        formatTanggal(nilai) {
            if (!nilai) return "—";

            const d = new Date(String(nilai).replace(" ", "T"));
            if (isNaN(d.getTime())) return nilai;

            return d.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },

        jamSaja(nilai) {
            if (!nilai) return "";

            // Kolom Jam bisa berupa "08:15:00" atau datetime penuh.
            const cocok = String(nilai).match(/\d{2}:\d{2}(:\d{2})?/);

            return cocok ? cocok[0] : String(nilai);
        },

        pesanError(e, bawaan) {
            return e?.response?.data?.message || e?.message || bawaan || "Terjadi kesalahan.";
        },

        alertGagal(pesan) {
            Swal.fire({ icon: "error", title: "Gagal", text: pesan });
        },

        /** Escape isi database sebelum disisipkan ke HTML dialog. */
        esc(v) {
            if (v === null || v === undefined || v === "") return "—";

            return String(v).replace(
                /[&<>"']/g,
                (c) =>
                    ({
                        "&": "&amp;",
                        "<": "&lt;",
                        ">": "&gt;",
                        '"': "&quot;",
                        "'": "&#39;",
                    }[c])
            );
        },

        /**
         * Susun ringkasan dua bagian untuk dialog konfirmasi: identitas sampel
         * di atas, lalu dampak perubahan yang akan terjadi di bawah.
         */
        ringkasanHtml(identitas, dampak, catatan) {
            const barisIdentitas = identitas
                .map(
                    ([k, v]) =>
                        `<div class="sw-row"><span>${this.esc(k)}</span><b>${this.esc(v)}</b></div>`
                )
                .join("");

            const barisDampak = dampak
                .map(
                    ([k, v, jenis]) =>
                        `<div class="sw-row sw-row--${jenis || "biasa"}">` +
                        `<span>${this.esc(k)}</span><b>${this.esc(v)}</b></div>`
                )
                .join("");

            return `
                <div class="sw-wrap">
                    <div class="sw-sect">
                        <div class="sw-head"><i class="ri-file-list-3-line"></i> Detail Sampel</div>
                        ${barisIdentitas}
                    </div>
                    <div class="sw-sect sw-sect--impact">
                        <div class="sw-head"><i class="ri-exchange-line"></i> Dampak Perubahan</div>
                        ${barisDampak}
                    </div>
                    ${
                        catatan
                            ? `<div class="sw-note"><i class="ri-information-line"></i> ${this.esc(catatan)}</div>`
                            : ""
                    }
                </div>
            `;
        },

        /* ---------- fitur 1 ---------- */

        async cariSubSampel() {
            this.sub.loading = true;
            this.sub.data = null;
            this.sub.preview = null;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/sub-sampel/cari", {
                    no_sampel: this.sub.noSampel,
                });

                this.sub.data = data.result;

                // Sampel single QR tidak punya sub sampel, jadi tidak perlu pratinjau.
                if (this.bolehTambahSub) await this.mintaPreviewSub();
            } catch (e) {
                this.alertGagal(this.pesanError(e, "Sampel tidak ditemukan."));
            } finally {
                this.sub.loading = false;
            }
        },

        pilihJumlahSub(n) {
            this.sub.jumlah = n;
            this.mintaPreviewSub();
        },

        async mintaPreviewSub() {
            if (!this.sub.data || !this.sub.jumlah || this.sub.jumlah < 1) return;
            if (!this.bolehTambahSub) return;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/sub-sampel/preview", {
                    no_sampel: this.sub.data.sampel.No_Sampel,
                    jumlah: this.sub.jumlah,
                });

                this.sub.preview = data.result;
            } catch (e) {
                // Pratinjau bersifat bantu-bantu; kegagalan tidak memblokir alur.
                this.sub.preview = null;
            }
        },

        async simpanSubSampel() {
            const s = this.sub.data.sampel;
            const p = this.sub.preview;

            const nomorBaru = (p?.sesudah_baru || [])
                .map((x) => x.No_Po_Multi)
                .join(", ");

            const konfirmasi = await Swal.fire({
                icon: null,
                title: "Ringkasan Penambahan Sub Sampel",
                html: this.ringkasanHtml(
                    [
                        ["Nomor Sampel", s.No_Sampel],
                        ["Nama Barang", s.Nama_Barang],
                        ["Kode Barang", s.Kode_Barang],
                        ["Nomor PO", s.No_Po],
                        ["Split PO", s.No_Split_Po],
                        ["Batch", s.No_Batch],
                        ["Mesin", s.Nama_Mesin],
                        ["Diinput Oleh", s.Nama_User || s.Id_User],
                        [
                            "Waktu Input",
                            `${this.formatTanggal(s.Tanggal)} ${this.jamSaja(s.Jam)}`,
                        ],
                    ],
                    [
                        ["Sub sampel sekarang", `${p?.total_sebelum ?? 0}`],
                        ["Ditambahkan", `+${this.sub.jumlah}`, "tambah"],
                        ["Total setelah simpan", `${p?.total_sesudah ?? 0}`, "hasil"],
                        ["Nomor baru", nomorBaru || "—"],
                    ],
                    "Sub sampel baru mewarisi Id_User, Tanggal, dan Jam dari registrasi induk."
                ),
                width: 640,
                showCancelButton: true,
                confirmButtonText: "Saya Paham, Simpan",
                cancelButtonText: "Batal",
                confirmButtonColor: "#0ab39c",
                reverseButtons: true,
            });

            if (!konfirmasi.isConfirmed) return;

            this.sub.saving = true;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/sub-sampel/tambah", {
                    no_sampel: this.sub.data.sampel.No_Sampel,
                    jumlah: this.sub.jumlah,
                });

                await Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    html: `${data.message}<br><small>${data.result.ditambahkan.join(", ")}</small>`,
                });

                await this.cariSubSampel();
            } catch (e) {
                this.alertGagal(this.pesanError(e));
            } finally {
                this.sub.saving = false;
            }
        },

        /* ---------- fitur 2 ---------- */

        async cariRegistrasi() {
            this.reg.loading = true;
            this.reg.data = null;
            this.reg.ikutUji = false;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/registrasi/cari", {
                    no_sampel: this.reg.noSampel,
                });

                this.reg.data = data.result;
            } catch (e) {
                this.alertGagal(this.pesanError(e, "Sampel tidak ditemukan."));
            } finally {
                this.reg.loading = false;
            }
        },

        async prosesBatalRegistrasi() {
            const s = this.reg.data.sampel;

            const konfirmasi = await Swal.fire({
                icon: null,
                title: "Ringkasan Pembatalan Registrasi",
                html:
                    this.ringkasanHtml(
                        [
                            ["Nomor Sampel", s.No_Sampel],
                            ["Nama Barang", s.Nama_Barang],
                            ["Kode Barang", s.Kode_Barang],
                            ["Nomor PO", s.No_Po],
                            ["Split PO", s.No_Split_Po],
                            ["Batch", s.No_Batch],
                            ["Mesin", s.Nama_Mesin],
                            ["Diinput Oleh", s.Nama_User || s.Id_User],
                            [
                                "Waktu Input",
                                `${this.formatTanggal(s.Tanggal)} ${this.jamSaja(s.Jam)}`,
                            ],
                        ],
                        [
                            ["Status registrasi", "Aktif → Dibatalkan (Y)", "bahaya"],
                            [
                                "Hasil uji aktif",
                                this.reg.ikutUji
                                    ? `${this.reg.data.jumlah_uji_aktif} baris ikut dibatalkan`
                                    : `${this.reg.data.jumlah_uji_aktif} baris dibiarkan`,
                                this.reg.ikutUji ? "bahaya" : "biasa",
                            ],
                            ["Sub sampel terkait", `${this.reg.data.total_sub_sampel}`],
                            ["Alasan", this.reg.alasan || "(tidak diisi)"],
                        ],
                        "Data lama tetap tersimpan sebagai jejak audit."
                    ) +
                    '<div class="sw-confirm">Ketik <b>BATAL</b> untuk melanjutkan</div>',
                width: 640,
                input: "text",
                inputPlaceholder: "BATAL",
                showCancelButton: true,
                confirmButtonText: "Saya Paham, Batalkan",
                cancelButtonText: "Tidak",
                confirmButtonColor: "#f06548",
                reverseButtons: true,
                inputValidator: (v) => (v !== "BATAL" ? "Ketik persis: BATAL" : undefined),
            });

            if (!konfirmasi.isConfirmed) return;

            this.reg.saving = true;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/registrasi/batalkan", {
                    no_sampel: this.reg.data.sampel.No_Sampel,
                    alasan: this.reg.alasan || null,
                    ikut_uji: this.reg.ikutUji,
                });

                await Swal.fire({ icon: "success", title: "Berhasil", text: data.message });
                await this.cariRegistrasi();
            } catch (e) {
                this.alertGagal(this.pesanError(e));
            } finally {
                this.reg.saving = false;
            }
        },

        /* ---------- fitur 3 ---------- */

        async cariUji() {
            this.uji.loading = true;
            this.uji.data = null;
            this.uji.dipilih = [];
            this.uji.semua = false;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/uji-sampel/cari", {
                    no_sampel: this.uji.noSampel,
                });

                this.uji.data = data.result;
            } catch (e) {
                this.alertGagal(this.pesanError(e, "Sampel tidak ditemukan."));
            } finally {
                this.uji.loading = false;
            }
        },

        toggleSemuaAnalisa() {
            // Saat "semua" aktif, centang per-baris tidak dipakai lagi.
            if (this.uji.semua) this.uji.dipilih = [];
        },

        pilihBarisAnalisa(row) {
            if (this.uji.semua || row.total_aktif === 0) return;

            const i = this.uji.dipilih.indexOf(row.Id_Jenis_Analisa);
            if (i === -1) this.uji.dipilih.push(row.Id_Jenis_Analisa);
            else this.uji.dipilih.splice(i, 1);
        },

        async prosesBatalUji() {
            const s = this.uji.data.sampel;

            // Daftar nama analisa yang benar-benar akan dibatalkan.
            const terpilih = this.uji.semua
                ? this.uji.data.analisa.filter((a) => a.total_aktif > 0)
                : this.uji.data.analisa.filter((a) =>
                      this.uji.dipilih.includes(a.Id_Jenis_Analisa)
                  );

            const namaAnalisa = terpilih
                .map((a) => a.Jenis_Analisa || a.Kode_Analisa)
                .join(", ");

            const barisKena = terpilih.reduce((t, a) => t + a.total_aktif, 0);

            const konfirmasi = await Swal.fire({
                icon: null,
                title: "Ringkasan Pembatalan Uji Sampel",
                html:
                    this.ringkasanHtml(
                        [
                            ["Nomor Sampel", s.No_Sampel],
                            ["Nama Barang", s.Nama_Barang],
                            ["Kode Barang", s.Kode_Barang],
                            ["Nomor PO", s.No_Po],
                            ["Batch", s.No_Batch],
                            ["Mesin", s.Nama_Mesin],
                            ["Diinput Oleh", s.Nama_User || s.Id_User],
                            [
                                "Waktu Input",
                                `${this.formatTanggal(s.Tanggal)} ${this.jamSaja(s.Jam)}`,
                            ],
                        ],
                        [
                            [
                                "Cakupan",
                                this.uji.semua
                                    ? "Semua analisa"
                                    : `${terpilih.length} jenis analisa terpilih`,
                                "bahaya",
                            ],
                            ["Analisa dibatalkan", namaAnalisa || "—"],
                            ["Baris uji terdampak", `${barisKena}`, "bahaya"],
                            [
                                "Sisa uji aktif",
                                `${Math.max(this.uji.data.total_uji_aktif - barisKena, 0)}`,
                                "hasil",
                            ],
                            ["Flag Selesai", "Direset agar bisa diinput ulang"],
                            ["Alasan", this.uji.alasan || "(tidak diisi)"],
                        ],
                        "Baris uji lama tetap tersimpan dengan status Y sebagai jejak audit."
                    ) +
                    '<div class="sw-confirm">Ketik <b>BATAL</b> untuk melanjutkan</div>',
                width: 660,
                input: "text",
                inputPlaceholder: "BATAL",
                showCancelButton: true,
                confirmButtonText: "Saya Paham, Batalkan",
                cancelButtonText: "Tidak",
                confirmButtonColor: "#f06548",
                reverseButtons: true,
                inputValidator: (v) => (v !== "BATAL" ? "Ketik persis: BATAL" : undefined),
            });

            if (!konfirmasi.isConfirmed) return;

            this.uji.saving = true;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/uji-sampel/batalkan", {
                    no_sampel: this.uji.data.sampel.No_Sampel,
                    semua: this.uji.semua,
                    id_analisa: this.uji.dipilih,
                    alasan: this.uji.alasan || null,
                });

                await Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    html: `${data.message}<br><small>Sisa uji aktif: ${data.result.sisa_uji_aktif}</small>`,
                });

                await this.cariUji();
            } catch (e) {
                this.alertGagal(this.pesanError(e));
            } finally {
                this.uji.saving = false;
            }
        },

        /* ---------- fitur 4 ---------- */

        async muatDaftarTemplate() {
            try {
                const { data } = await axios.get("/api/v1/bypass-lims/printer/templates");
                this.printer.daftar = data.result || [];
            } catch (e) {
                this.alertGagal(this.pesanError(e, "Gagal memuat template."));
            }
        },

        async muatDetailTemplate() {
            this.printer.loading = true;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/printer/detail", {
                    id: this.printer.idTerpilih,
                });

                const m = data.result.master;

                this.printer.master = {
                    ...m,
                    Lebar_Label: Number(m.Lebar_Label) || 50,
                    Tinggi_Label: Number(m.Tinggi_Label) || 30,
                    Gap_Antar_Label: Number(m.Gap_Antar_Label) || 0,
                    Direction: Number(m.Direction ?? 1),
                };

                this.printer.items = (data.result.items || []).map((it) => ({
                    Jenis: it.Jenis || "TEXT",
                    Posisi_X: Number(it.Posisi_X) || 0,
                    Posisi_Y: Number(it.Posisi_Y) || 0,
                    Font: String(it.Font ?? "1"),
                    Rotation: Number(it.Rotation) || 0,
                    Scale_X: Number(it.Scale_X) || 1,
                    Scale_Y: Number(it.Scale_Y) || 1,
                    Isi_Konten: it.Isi_Konten || "",
                    Qr_Ecc: it.Qr_Ecc || "H",
                    Qr_Size: Number(it.Qr_Size) || 6,
                    Qr_Model: it.Qr_Model || "M2,S7",
                    Lebar_Label: Number(it.Lebar_Label) || 0,
                }));

                // Nilai lama yang belum ada di daftar opsi tetap ditambahkan,
                // supaya dropdown tidak tampil kosong dan nilainya tidak hilang.
                this.printer.items.forEach((it) => {
                    if (it.Jenis !== "QRCODE" || !it.Qr_Model) return;

                    if (!this.modelQr.some((m) => m.nilai === it.Qr_Model)) {
                        this.modelQr.push({
                            nilai: it.Qr_Model,
                            label: `${it.Qr_Model} · dari data`,
                        });
                    }
                });

                this.printer.itemAktif = this.printer.items.length ? 0 : null;
            } catch (e) {
                this.alertGagal(this.pesanError(e, "Gagal memuat template."));
            } finally {
                this.printer.loading = false;
            }
        },

        tambahItem(jenis) {
            this.printer.items.push({
                Jenis: jenis,
                Posisi_X: 10,
                Posisi_Y: 10,
                Font: "2",
                Rotation: 0,
                Scale_X: 1,
                Scale_Y: 1,
                Isi_Konten: jenis === "QRCODE" ? "{qrData}" : "{nama_sampel}",
                Qr_Ecc: "H",
                Qr_Size: 6,
                Qr_Model: "M2,S7",
                Lebar_Label: 0,
            });

            this.printer.itemAktif = this.printer.items.length - 1;
        },

        hapusItem(idx) {
            this.printer.items.splice(idx, 1);

            if (this.printer.itemAktif === idx) this.printer.itemAktif = null;
            else if (this.printer.itemAktif > idx) this.printer.itemAktif -= 1;
        },

        sisipkanPlaceholder(item, ph) {
            item.Isi_Konten = `${item.Isi_Konten || ""}${ph}`;
        },

        contohTeks(isi) {
            const contoh = {
                "{nama_sampel}": "CONTOH SAMPEL",
                "{qrData}": "FS0926-0001",
                "{no_split}": "SP-000000",
                "{batch}": "BATCH-01",
                "{tanggal}": "01 Jan 2026",
                "{namaMesin}": "MESIN UJI",
                "{jenis_analisa}": "TEST PRINT",
            };

            let hasil = String(isi || "");
            Object.keys(contoh).forEach((k) => {
                hasil = hasil.split(k).join(contoh[k]);
            });

            return hasil;
        },

        gayaElemen(item) {
            const s = this.skalaPreview;

            const gaya = {
                left: `${(Number(item.Posisi_X) || 0) * s}px`,
                top: `${(Number(item.Posisi_Y) || 0) * s}px`,
                transform: `rotate(${Number(item.Rotation) || 0}deg)`,
                transformOrigin: "left top",
            };

            if (item.Jenis === "TEXT") {
                // Tinggi dasar font TSC (dot) dikali skala Y lalu skala pratinjau.
                const tinggiFont =
                    { 1: 12, 2: 20, 3: 24, 4: 32, 5: 48, 0: 16 }[item.Font] || 20;

                gaya.fontSize = `${tinggiFont * (Number(item.Scale_Y) || 1) * s}px`;
                gaya.letterSpacing = `${((Number(item.Scale_X) || 1) - 1) * s}px`;
            }

            return gaya;
        },

        gayaQr(item) {
            // TSPL: ukuran modul QR (cell width) dalam dot, versi QR ~21 modul.
            const sisi = (Number(item.Qr_Size) || 6) * 21 * this.skalaPreview;

            return {
                width: `${sisi}px`,
                height: `${sisi}px`,
                fontSize: `${sisi * 0.85}px`,
            };
        },

        async simpanTemplate() {
            const konfirmasi = await Swal.fire({
                icon: "question",
                title: "Simpan Template?",
                html: `Template <b>${this.printer.master.Nama_Template}</b> akan diperbarui beserta ${this.printer.items.length} elemen.`,
                showCancelButton: true,
                confirmButtonText: "Ya, Simpan",
                cancelButtonText: "Batal",
                confirmButtonColor: "#0ab39c",
            });

            if (!konfirmasi.isConfirmed) return;

            this.printer.saving = true;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/printer/simpan", {
                    id: this.printer.master.Id_Master_Printer_Templates,
                    Nama_Template: this.printer.master.Nama_Template,
                    Lebar_Label: this.printer.master.Lebar_Label,
                    Tinggi_Label: this.printer.master.Tinggi_Label,
                    Gap_Antar_Label: this.printer.master.Gap_Antar_Label,
                    Direction: this.printer.master.Direction,
                    items: this.printer.items,
                });

                await Swal.fire({ icon: "success", title: "Berhasil", text: data.message });
                await this.muatDaftarTemplate();
            } catch (e) {
                this.alertGagal(this.pesanError(e));
            } finally {
                this.printer.saving = false;
            }
        },

        async testPrint() {
            this.printer.printing = true;

            try {
                const { data } = await axios.post("/api/v1/bypass-lims/printer/test-print", {
                    id: this.printer.master.Id_Master_Printer_Templates,
                });

                if (!data.printer_url) {
                    throw new Error("URL agen printer belum dikonfigurasi (URL_CLIENT).");
                }

                for (const job of data.print_jobs || []) {
                    await axios.post(`${data.printer_url}/print`, job);
                }

                Swal.fire({
                    icon: "success",
                    title: "Terkirim",
                    text: "Print job uji coba sudah dikirim ke printer.",
                });
            } catch (e) {
                this.alertGagal(
                    this.pesanError(e, "Gagal mengirim ke printer. Pastikan agen printer aktif.")
                );
            } finally {
                this.printer.printing = false;
            }
        },
    },
};
</script>

<style scoped>
/* ============ TOKEN VELZON ============ */
.bp {
    --c-primary: #405189;
    --c-success: #0ab39c;
    --c-info: #299cdb;
    --c-warning: #f7b84b;
    --c-danger: #f06548;
    --c-dark: #212529;
    --c-body: #212529;
    --c-muted: #878a99;
    --c-line: #e9ebec;
    --c-bg: #f3f3f9;
    --c-card: #fff;
    --r: 12px;

    min-height: 100vh;
    background: var(--c-bg);
    color: var(--c-body);
    font-size: 0.875rem;
    display: flex;
    flex-direction: column;
}

/* ============ TOPBAR ============ */
.bp-topbar {
    position: sticky;
    top: 0;
    z-index: 30;
    background: linear-gradient(90deg, #1b1f3b, #2c3163 60%, #34407c);
    box-shadow: 0 1px 14px rgba(0, 0, 0, 0.2);
}

.bp-topbar__inner {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.7rem 1.5rem;
    flex-wrap: wrap;
}

.bp-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.bp-brand__mark {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
}
.bp-brand__mark svg {
    width: 100%;
    height: 100%;
}

.bp-brand__txt h1 {
    font-size: 1.05rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    letter-spacing: 0.2px;
}
.bp-brand__txt p {
    font-size: 0.72rem;
    color: rgba(255, 255, 255, 0.62);
    margin: 0;
}

.bp-crumb {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-left: 0.5rem;
    font-size: 0.8rem;
    min-width: 0;
}
.bp-crumb__home {
    background: rgba(255, 255, 255, 0.1);
    border: 0;
    color: #fff;
    padding: 0.32rem 0.7rem;
    border-radius: 7px;
    font-weight: 500;
    transition: background 0.15s;
}
.bp-crumb__home:hover {
    background: rgba(255, 255, 255, 0.2);
}
.bp-crumb__sep {
    color: rgba(255, 255, 255, 0.4);
}
.bp-crumb__now {
    color: #fff;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bp-topbar__right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.bp-clock {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.78rem;
    font-variant-numeric: tabular-nums;
    background: rgba(255, 255, 255, 0.08);
    padding: 0.3rem 0.65rem;
    border-radius: 7px;
}

.bp-chip-live {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(240, 101, 72, 0.16);
    border: 1px solid rgba(240, 101, 72, 0.45);
    color: #ffb3a3;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}

.bp-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #f06548;
    animation: bpPulse 1.6s infinite;
}

@keyframes bpPulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(240, 101, 72, 0.6); }
    50% { opacity: 0.6; box-shadow: 0 0 0 5px rgba(240, 101, 72, 0); }
}

/* ============ MAIN ============ */
.bp-main {
    flex: 1;
    padding: 1.5rem;
}

/* ============ HERO ============ */
.bp-hero {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 1.25rem;
    box-shadow: 0 8px 26px rgba(44, 49, 99, 0.22);
}

.bp-hero__bg {
    position: absolute;
    inset: 0;
}
.bp-hero__bg svg {
    width: 100%;
    height: 100%;
    display: block;
}

.bp-hero__body {
    position: relative;
    display: flex;
    align-items: center;
    gap: 2rem;
    padding: 2rem 2.25rem;
}

.bp-hero__left {
    flex: 1;
    min-width: 0;
}

.bp-hero__tag {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #fff;
    padding: 0.3rem 0.8rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
    margin-bottom: 0.85rem;
}

.bp-hero__left h2 {
    color: #fff;
    font-size: 1.9rem;
    font-weight: 700;
    line-height: 1.2;
    margin: 0 0 0.6rem;
}
.bp-hero__left h2 span {
    display: block;
    background: linear-gradient(90deg, #9fe8dc, #d9f0ff);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.bp-hero__left p {
    color: rgba(255, 255, 255, 0.78);
    max-width: 640px;
    margin: 0 0 1.25rem;
    line-height: 1.65;
}

.bp-hero__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
}

.bp-hero__metaitem {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 10px;
    padding: 0.55rem 0.85rem;
}
.bp-hero__metaitem i {
    font-size: 1.15rem;
    color: #9fe8dc;
}
.bp-hero__metaitem strong {
    display: block;
    color: #fff;
    font-size: 0.82rem;
    line-height: 1.2;
}
.bp-hero__metaitem small {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.68rem;
}

.bp-hero__art {
    width: 200px;
    flex-shrink: 0;
}
.bp-hero__art svg {
    width: 100%;
    height: auto;
}

.bp-spin {
    transform-origin: 130px 130px;
    animation: bpSpin 22s linear infinite;
}
@keyframes bpSpin {
    to { transform: rotate(360deg); }
}

/* ============ ALERT ============ */
.bp-alert {
    display: flex;
    gap: 0.85rem;
    align-items: flex-start;
    background: #fff8ec;
    border: 1px solid #ffe0aa;
    border-left: 4px solid var(--c-warning);
    border-radius: var(--r);
    padding: 0.9rem 1.1rem;
    margin-bottom: 1.25rem;
}

.bp-alert--danger {
    background: #fff0ed;
    border-color: #ffcabe;
    border-left-color: var(--c-danger);
}

.bp-alert__icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: var(--c-warning);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    flex-shrink: 0;
}
.bp-alert--danger .bp-alert__icon {
    background: var(--c-danger);
}

.bp-alert__txt {
    font-size: 0.83rem;
    line-height: 1.6;
    color: #6b5320;
}
.bp-alert--danger .bp-alert__txt {
    color: #8a3323;
}
.bp-alert__txt code {
    background: rgba(0, 0, 0, 0.07);
    padding: 0 0.3rem;
    border-radius: 4px;
}

/* ============ KARTU MENU ============ */
.bp-card {
    position: relative;
    height: 100%;
    background: var(--c-card);
    border: 1px solid var(--c-line);
    border-radius: 16px;
    padding: 1.5rem;
    cursor: pointer;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    animation: bpRise 0.45s ease both;
}

@keyframes bpRise {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}

.bp-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 34px rgba(30, 32, 55, 0.14);
}

.bp-card__glow {
    position: absolute;
    top: -60px;
    right: -60px;
    width: 190px;
    height: 190px;
    border-radius: 50%;
    opacity: 0;
    transition: opacity 0.25s ease;
    pointer-events: none;
}
.bp-card:hover .bp-card__glow {
    opacity: 0.12;
}

.bp-card--primary { border-top: 3px solid var(--c-primary); }
.bp-card--warning { border-top: 3px solid var(--c-warning); }
.bp-card--danger  { border-top: 3px solid var(--c-danger); }
.bp-card--info    { border-top: 3px solid var(--c-info); }

.bp-card--primary .bp-card__glow { background: var(--c-primary); }
.bp-card--warning .bp-card__glow { background: var(--c-warning); }
.bp-card--danger  .bp-card__glow { background: var(--c-danger); }
.bp-card--info    .bp-card__glow { background: var(--c-info); }

.bp-card--primary:hover { border-color: var(--c-primary); }
.bp-card--warning:hover { border-color: var(--c-warning); }
.bp-card--danger:hover  { border-color: var(--c-danger); }
.bp-card--info:hover    { border-color: var(--c-info); }

.bp-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.bp-card__icon {
    width: 52px;
    height: 52px;
}
.bp-card__icon svg {
    width: 100%;
    height: 100%;
    transition: transform 0.25s ease;
}
.bp-card:hover .bp-card__icon svg {
    transform: scale(1.07) rotate(-4deg);
}

.bp-card__no {
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--c-line);
    line-height: 1;
    transition: color 0.2s;
}
.bp-card--primary:hover .bp-card__no { color: rgba(64, 81, 137, 0.22); }
.bp-card--warning:hover .bp-card__no { color: rgba(247, 184, 75, 0.3); }
.bp-card--danger:hover  .bp-card__no { color: rgba(240, 101, 72, 0.25); }
.bp-card--info:hover    .bp-card__no { color: rgba(41, 156, 219, 0.25); }

.bp-card__title {
    font-size: 1.08rem;
    font-weight: 700;
    margin: 0 0 0.4rem;
    color: var(--c-dark);
}

.bp-card__desc {
    font-size: 0.83rem;
    color: var(--c-muted);
    margin: 0 0 1rem;
    line-height: 1.6;
}

.bp-card__points {
    list-style: none;
    padding: 0;
    margin: 0 0 1.15rem;
    flex-grow: 1;
}
.bp-card__points li {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: #5c6070;
    padding: 0.24rem 0;
}
.bp-card__points i {
    font-size: 0.9rem;
    margin-top: 1px;
    flex-shrink: 0;
}
.bp-card--primary .bp-card__points i { color: var(--c-primary); }
.bp-card--warning .bp-card__points i { color: var(--c-warning); }
.bp-card--danger  .bp-card__points i { color: var(--c-danger); }
.bp-card--info    .bp-card__points i { color: var(--c-info); }

.bp-card__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border-top: 1px dashed var(--c-line);
    padding-top: 0.9rem;
    flex-wrap: wrap;
}

.bp-card__tag {
    font-family: ui-monospace, "SFMono-Regular", Menlo, monospace;
    font-size: 0.68rem;
    color: var(--c-muted);
    background: var(--c-bg);
    padding: 0.24rem 0.5rem;
    border-radius: 6px;
}

.bp-card__cta {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 600;
}
.bp-card__cta i {
    transition: transform 0.2s ease;
}
.bp-card:hover .bp-card__cta i {
    transform: translateX(4px);
}
.bp-card--primary .bp-card__cta { color: var(--c-primary); }
.bp-card--warning .bp-card__cta { color: #b8860b; }
.bp-card--danger  .bp-card__cta { color: var(--c-danger); }
.bp-card--info    .bp-card__cta { color: var(--c-info); }

/* ============ LEGEND ============ */
.bp-legend {
    margin-top: 1.5rem;
    background: var(--c-card);
    border: 1px solid var(--c-line);
    border-radius: var(--r);
    padding: 1.25rem;
}
.bp-legend h4 {
    font-size: 0.92rem;
    font-weight: 700;
    margin: 0 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.bp-legend h4 i { color: var(--c-primary); }

.bp-legend__item {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    background: var(--c-bg);
    border-radius: 10px;
    padding: 0.7rem 0.85rem;
    height: 100%;
}
.bp-legend__badge {
    font-family: ui-monospace, Menlo, monospace;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.28rem 0.5rem;
    border-radius: 6px;
    flex-shrink: 0;
}
.bp-legend__item strong {
    display: block;
    font-size: 0.8rem;
}
.bp-legend__item small {
    color: var(--c-muted);
    font-size: 0.7rem;
    line-height: 1.35;
    display: block;
}

/* ============ PANEL HEAD ============ */





/* ============ SEARCH ============ */
.bp-search {
    display: flex;
    gap: 0.75rem;
    background: var(--c-card);
    border: 1px solid var(--c-line);
    border-radius: var(--r);
    padding: 1rem;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.bp-search__field {
    position: relative;
    flex: 1;
    min-width: 240px;
}
.bp-search__field > i {
    position: absolute;
    left: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--c-muted);
    font-size: 1.05rem;
    pointer-events: none;
}
.bp-search__field input,
.bp-search__field select {
    width: 100%;
    height: 46px;
    padding: 0 1rem 0 2.6rem;
    border: 1px solid var(--c-line);
    border-radius: 9px;
    background: var(--c-bg);
    font-size: 0.9rem;
    color: var(--c-body);
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.bp-search__field input {
    text-transform: uppercase;
}
.bp-search__field input::placeholder {
    text-transform: none;
    color: var(--c-muted);
}
.bp-search__field input:focus,
.bp-search__field select:focus {
    outline: 0;
    background: #fff;
    border-color: var(--c-primary);
    box-shadow: 0 0 0 3px rgba(64, 81, 137, 0.12);
}

/* ============ TOMBOL ============ */
.bp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    height: 46px;
    padding: 0 1.35rem;
    border: 1px solid transparent;
    border-radius: 9px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #fff;
    cursor: pointer;
    white-space: nowrap;
    transition: filter 0.15s, transform 0.1s, box-shadow 0.15s;
}
.bp-btn:hover:not(:disabled) {
    filter: brightness(1.07);
    box-shadow: 0 5px 14px rgba(0, 0, 0, 0.14);
}
.bp-btn:active:not(:disabled) { transform: translateY(1px); }
.bp-btn:disabled { opacity: 0.55; cursor: not-allowed; }

.bp-btn--primary { background: var(--c-primary); }
.bp-btn--success { background: var(--c-success); }
.bp-btn--info    { background: var(--c-info); }
.bp-btn--warning { background: var(--c-warning); color: #664d03; }
.bp-btn--danger  { background: var(--c-danger); }
.bp-btn--ghost {
    background: var(--c-card);
    border-color: var(--c-line);
    color: var(--c-body);
}
.bp-btn--ghost:hover:not(:disabled) { background: var(--c-bg); }

.bp-btn--lg { height: 52px; font-size: 0.95rem; }
.bp-btn--sm { height: 34px; padding: 0 0.8rem; font-size: 0.78rem; }

.bp-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: bpSpin 0.7s linear infinite;
}
.bp-btn--ghost .bp-spinner,
.bp-btn--warning .bp-spinner {
    border-color: rgba(0, 0, 0, 0.18);
    border-top-color: var(--c-body);
}

/* ============ SECTION ============ */
.bp-sect {
    background: var(--c-card);
    border: 1px solid var(--c-line);
    border-radius: var(--r);
    padding: 1.25rem;
    margin-bottom: 1.25rem;
}
.bp-sect--flat { background: var(--c-card); }
.bp-sect--ok {
    background: #f0fdf9;
    border-color: rgba(10, 179, 156, 0.4);
}

.bp-sect__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1.1rem;
    padding-bottom: 0.85rem;
    border-bottom: 1px solid var(--c-line);
    flex-wrap: wrap;
}
.bp-sect__head h3 {
    font-size: 0.95rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.45rem;
}
.bp-sect__head h3 i { color: var(--c-primary); }

.bp-sticky { position: sticky; top: 82px; }

/* ============ INFO CELL ============ */


/* ============ METRIC ============ */



/* ============ QTY ============ */
.bp-qty {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0.55rem;
}
.bp-qty__btn {
    min-width: 62px;
    height: 46px;
    border-radius: 10px;
    border: 1.5px solid var(--c-line);
    background: var(--c-card);
    font-weight: 700;
    font-size: 0.92rem;
    color: var(--c-body);
    transition: all 0.15s;
}
.bp-qty__btn:hover {
    border-color: var(--c-success);
    color: var(--c-success);
}
.bp-qty__btn.is-on {
    background: var(--c-success);
    border-color: var(--c-success);
    color: #fff;
    box-shadow: 0 5px 14px rgba(10, 179, 156, 0.32);
}

.bp-qty__custom { display: flex; flex-direction: column; gap: 0.25rem; }
.bp-qty__custom label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: var(--c-muted);
}
.bp-qty__custom input {
    width: 120px;
    height: 46px;
    border: 1.5px solid var(--c-line);
    border-radius: 10px;
    padding: 0 0.8rem;
    font-weight: 600;
    background: var(--c-bg);
}
.bp-qty__custom input:focus {
    outline: 0;
    background: #fff;
    border-color: var(--c-success);
}

/* ============ NOTE / LABEL / INPUT ============ */
.bp-note {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    background: rgba(41, 156, 219, 0.08);
    border-radius: 9px;
    padding: 0.7rem 0.85rem;
    font-size: 0.79rem;
    color: #1f6a94;
    line-height: 1.6;
    margin: 1rem 0 0;
}
.bp-note i { font-size: 0.95rem; margin-top: 1px; flex-shrink: 0; }

.bp-label {
    display: block;
    font-size: 0.78rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
    color: var(--c-body);
}
.bp-label span { color: var(--c-muted); font-weight: 400; }
.bp-label--xs { font-size: 0.68rem; margin-bottom: 0.2rem; color: var(--c-muted); }

.bp-input {
    width: 100%;
    height: 44px;
    padding: 0 0.9rem;
    border: 1px solid var(--c-line);
    border-radius: 9px;
    background: var(--c-bg);
    font-size: 0.88rem;
    color: var(--c-body);
    transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
}
.bp-input:focus {
    outline: 0;
    background: #fff;
    border-color: var(--c-primary);
    box-shadow: 0 0 0 3px rgba(64, 81, 137, 0.12);
}
.bp-input--sm { height: 36px; font-size: 0.8rem; padding: 0 0.65rem; }

/* ============ SWITCH ============ */
.bp-switch {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1.1rem;
    padding: 0.85rem 1rem;
    background: var(--c-bg);
    border: 1px solid var(--c-line);
    border-radius: 10px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
}
.bp-switch:hover { border-color: var(--c-muted); }
.bp-switch.is-on {
    background: rgba(240, 101, 72, 0.07);
    border-color: rgba(240, 101, 72, 0.45);
}
.bp-switch--sm {
    margin-top: 0;
    padding: 0.35rem 0.7rem;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.bp-switch__track {
    width: 42px;
    height: 23px;
    border-radius: 999px;
    background: #ced4da;
    padding: 3px;
    flex-shrink: 0;
    transition: background 0.2s;
}
.bp-switch.is-on .bp-switch__track { background: var(--c-danger); }

.bp-switch__knob {
    display: block;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    background: #fff;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}
.bp-switch.is-on .bp-switch__knob { transform: translateX(19px); }

.bp-switch strong { display: block; font-size: 0.84rem; }
.bp-switch small { color: var(--c-muted); font-size: 0.74rem; }

/* ============ LIST ============ */
.bp-list { max-height: 330px; overflow-y: auto; }
.bp-list__empty {
    text-align: center;
    color: var(--c-muted);
    padding: 2rem 0;
    margin: 0;
    font-size: 0.85rem;
}
.bp-list__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    background: var(--c-bg);
    margin-bottom: 0.4rem;
    font-size: 0.83rem;
}
.bp-list__row--new {
    background: #d5f6ef;
    border-left: 3px solid var(--c-success);
}
.bp-list__no {
    font-family: ui-monospace, Menlo, monospace;
    font-weight: 600;
}

/* ============ BADGE ============ */

/* ============ TABEL ============ */
.bp-table-wrap {
    overflow-x: auto;
    border: 1px solid var(--c-line);
    border-radius: 10px;
}
.bp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.84rem;
    min-width: 660px;
}
.bp-table thead th {
    background: var(--c-bg);
    padding: 0.75rem 0.85rem;
    text-align: left;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--c-muted);
    font-weight: 700;
    border-bottom: 1px solid var(--c-line);
    white-space: nowrap;
}
.bp-table tbody td {
    padding: 0.7rem 0.85rem;
    border-bottom: 1px solid var(--c-line);
    vertical-align: middle;
}
.bp-table tbody tr {
    cursor: pointer;
    transition: background 0.12s;
}
.bp-table tbody tr:hover { background: var(--c-bg); }
.bp-table tbody tr.is-picked { background: rgba(240, 101, 72, 0.07); }
.bp-table tbody tr.is-off { opacity: 0.45; cursor: not-allowed; }
.bp-table tbody tr:last-child td { border-bottom: 0; }
.bp-table__name { font-weight: 600; }

.bp-check {
    width: 21px;
    height: 21px;
    border-radius: 6px;
    border: 1.5px solid #ced4da;
    display: flex;
    align-items: center;
    justify-content: center;
    color: transparent;
    font-size: 0.8rem;
    transition: all 0.15s;
}
.bp-check.is-on {
    background: var(--c-danger);
    border-color: var(--c-danger);
    color: #fff;
}

/* ============ EDITOR ELEMEN ============ */
.bp-el {
    border: 1px solid var(--c-line);
    border-radius: 11px;
    padding: 0.9rem;
    margin-bottom: 0.75rem;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s, box-shadow 0.15s;
}
.bp-el:hover { border-color: #c9ced3; }
.bp-el.is-on {
    border-color: var(--c-info);
    background: #f2faff;
    box-shadow: 0 0 0 3px rgba(41, 156, 219, 0.1);
}

.bp-el__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.6rem;
}
.bp-el__del {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    border: 1px solid rgba(240, 101, 72, 0.35);
    background: transparent;
    color: var(--c-danger);
    transition: background 0.15s;
}
.bp-el__del:hover { background: rgba(240, 101, 72, 0.12); }

.bp-ph {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-top: 0.4rem;
}
.bp-ph__btn {
    border: 1px dashed var(--c-line);
    background: var(--c-bg);
    color: var(--c-muted);
    border-radius: 5px;
    padding: 0.14rem 0.42rem;
    font-size: 0.68rem;
    font-family: ui-monospace, Menlo, monospace;
    transition: all 0.15s;
}
.bp-ph__btn:hover {
    border-color: var(--c-info);
    color: var(--c-info);
    background: #fff;
}

/* ============ PRATINJAU LABEL ============ */
.bp-canvas {
    background: repeating-conic-gradient(#e7eaec 0% 25%, #f5f6f7 0% 50%) 50% / 18px 18px;
    border-radius: 10px;
    padding: 1.15rem;
    display: flex;
    justify-content: center;
    overflow: auto;
}

.bp-label-paper {
    position: relative;
    background: #fff;
    border: 1px dashed #adb5bd;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.12);
    flex-shrink: 0;
    overflow: hidden;
}

.bp-el-prev {
    position: absolute;
    white-space: nowrap;
    line-height: 1;
    font-family: "Courier New", monospace;
    color: #000;
    cursor: pointer;
    outline: 1px solid transparent;
}
.bp-el-prev.is-on {
    outline: 1px dashed var(--c-info);
    background: rgba(41, 156, 219, 0.1);
}

.bp-qr {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.bp-specs {
    margin-top: 1rem;
    display: grid;
    gap: 0.4rem;
}
.bp-specs div {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.79rem;
    padding: 0.42rem 0.65rem;
    background: var(--c-bg);
    border-radius: 7px;
}
.bp-specs span { color: var(--c-muted); }

.bp-code {
    background: #1b1f3b;
    color: #a9dcff;
    border-radius: 9px;
    padding: 0.85rem;
    font-size: 0.7rem;
    line-height: 1.55;
    max-height: 190px;
    overflow: auto;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
}

/* ============ KARTU TIKET SAMPEL + QR ============ */









/* perforasi */

.bp-tiket__qr :deep(canvas),



/* ============ STATUS MULTI / SINGLE QR ============ */
.bp-qrstate {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
    border-radius: var(--r);
    border: 1px solid var(--c-line);
    padding: 1rem 1.15rem;
    margin-bottom: 1.25rem;
}
.bp-qrstate--ok {
    background: #f0fdf9;
    border-color: rgba(10, 179, 156, 0.42);
    border-left: 4px solid var(--c-success);
}
.bp-qrstate--no {
    background: #fff0ed;
    border-color: rgba(240, 101, 72, 0.42);
    border-left: 4px solid var(--c-danger);
}

.bp-qrstate__ic {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    color: #fff;
    flex-shrink: 0;
}
.bp-qrstate--ok .bp-qrstate__ic { background: var(--c-success); }
.bp-qrstate--no .bp-qrstate__ic { background: var(--c-danger); }

.bp-qrstate__body { flex: 1; min-width: 0; }

.bp-qrstate__top {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    flex-wrap: wrap;
    margin-bottom: 0.25rem;
}
.bp-qrstate__top strong { font-size: 0.95rem; }

.bp-qrstate__body p {
    font-size: 0.83rem;
    line-height: 1.6;
    margin: 0 0 0.6rem;
}
.bp-qrstate--ok .bp-qrstate__body p { color: #0a6d5f; }
.bp-qrstate--no .bp-qrstate__body p { color: #a33c25; }

.bp-qrstate__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}
.bp-qrstate__meta span {
    display: inline-flex;
    align-items: center;
    gap: 0.32rem;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid var(--c-line);
    border-radius: 7px;
    padding: 0.3rem 0.6rem;
    font-size: 0.75rem;
    color: var(--c-muted);
}
.bp-qrstate__meta b {
    color: var(--c-body);
    font-family: ui-monospace, Menlo, monospace;
}

/* ============ EMPTY ============ */

/* ============ ACTIONS ============ */
/* Diberi jarak atas & bawah supaya tombol tidak menempel ke kartu di atasnya
   dan tetap punya ruang sebelum footer. */
.bp-actions {
    display: grid;
    margin-top: 1.5rem;
    margin-bottom: 2rem;
}
.bp-actions--split { grid-template-columns: 2fr 1fr; gap: 0.75rem; }

/* Ruang napas di dasar tiap panel fitur. */
.bp-main > section { padding-bottom: 1rem; }

/* ============ FOOTER ============ */
.bp-foot {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--c-line);
    background: var(--c-card);
    font-size: 0.76rem;
    color: var(--c-muted);
}

/* ============ TRANSISI ============ */
.bp-fade-enter-active, .bp-fade-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.bp-fade-enter-from { opacity: 0; transform: translateY(8px); }
.bp-fade-leave-to { opacity: 0; transform: translateY(-8px); }

/* ============ RESPONSIF ============ */
@media (max-width: 1199.98px) {
    .bp-hero__art { width: 150px; }
    .bp-hero__left h2 { font-size: 1.6rem; }
}

@media (max-width: 991.98px) {
    .bp-main { padding: 1rem; }
    .bp-hero__body { flex-direction: column; align-items: flex-start; padding: 1.5rem; gap: 1.25rem; }
    .bp-hero__art { width: 120px; align-self: center; }
    .bp-sticky { position: static; }
    .bp-actions--split { grid-template-columns: 1fr; }
}

@media (max-width: 767.98px) {
    .bp-topbar__inner { padding: 0.6rem 1rem; gap: 0.6rem; }
    .bp-brand__txt h1 { font-size: 0.95rem; }
    .bp-clock { display: none; }
    .bp-crumb { order: 3; width: 100%; margin-left: 0; }
    .bp-hero__left h2 { font-size: 1.35rem; }
    .bp-hero__meta { width: 100%; }
    .bp-hero__metaitem { flex: 1 1 100%; }
    .bp-card { padding: 1.15rem; }
    .bp-search { padding: 0.85rem; }
    .bp-btn { width: 100%; }
    .bp-sect { padding: 1rem; }
    .bp-metrics { grid-template-columns: 1fr; }
}

@media (max-width: 575.98px) {
    .bp-qty__btn { flex: 1 1 calc(33% - 0.4rem); min-width: 0; }
    .bp-qty__custom { flex: 1 1 100%; }
    .bp-qty__custom input { width: 100%; }
    .bp-foot { justify-content: center; text-align: center; }
}
</style>

<!-- Dialog SweetAlert dirender di luar komponen, jadi style-nya tidak di-scope. -->
<style>
.swal2-popup .sw-wrap {
    text-align: left;
    font-size: 0.85rem;
    color: #212529;
}

.swal2-popup .sw-sect {
    border: 1px solid #e9ebec;
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-bottom: 0.75rem;
    background: #fbfcfd;
}

.swal2-popup .sw-sect--impact {
    background: #fff8ec;
    border-color: #ffe0aa;
}

.swal2-popup .sw-head {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #878a99;
    padding-bottom: 0.55rem;
    margin-bottom: 0.55rem;
    border-bottom: 1px solid #e9ebec;
}

.swal2-popup .sw-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.3rem 0;
    line-height: 1.45;
}

.swal2-popup .sw-row > span {
    color: #878a99;
    font-size: 0.78rem;
    flex-shrink: 0;
}

.swal2-popup .sw-row > b {
    text-align: right;
    word-break: break-word;
    font-weight: 600;
}

.swal2-popup .sw-row--tambah > b { color: #0ab39c; }
.swal2-popup .sw-row--hasil > b { color: #405189; }
.swal2-popup .sw-row--bahaya > b { color: #f06548; }

.swal2-popup .sw-note {
    display: flex;
    align-items: flex-start;
    gap: 0.45rem;
    background: rgba(41, 156, 219, 0.1);
    border-radius: 8px;
    padding: 0.6rem 0.75rem;
    font-size: 0.76rem;
    color: #1f6a94;
    line-height: 1.55;
}

.swal2-popup .sw-confirm {
    margin-top: 0.85rem;
    text-align: center;
    font-size: 0.82rem;
    color: #c23d23;
}

.swal2-popup .sw-confirm b {
    font-family: ui-monospace, Menlo, monospace;
    background: rgba(240, 101, 72, 0.12);
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.swal2-popup .swal2-input {
    text-align: center;
    font-family: ui-monospace, Menlo, monospace;
    letter-spacing: 2px;
    text-transform: uppercase;
}
</style>
