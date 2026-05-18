<template>
    <div class="lhm-wrap">
        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- TITLE BAR                                                  -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="lhm-title-bar bg-primary">
            <div class="lhm-title-left">
                <div class="lhm-title-icon">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        style="width: 20px; height: 20px"
                    >
                        <path
                            d="M9.5 2a.5.5 0 0 0 0 1H11v1.07A7.001 7.001 0 0 0 12 18a7 7 0 0 0 1-13.93V3h1.5a.5.5 0 0 0 0-1h-5ZM12 5a5 5 0 1 1 0 10A5 5 0 0 1 12 5Zm-1 2v3.586l-1.707 1.707.707.707 2-2A.5.5 0 0 0 12 10.5V7h-1Z"
                        />
                    </svg>
                </div>
                <div>
                    <div class="lhm-title-main">Trial Sampel</div>
                    <div class="lhm-title-sub">
                        LIMS · PT. Evo Manufacturing Indonesia
                    </div>
                </div>
            </div>
            <div class="lhm-title-right">
                <div class="lhm-day-tabs">
                    <button
                        v-for="d in dayOptions"
                        :key="d.val"
                        :class="['lhm-day-tab', days === d.val ? 'active' : '']"
                        @click="setDays(d.val)"
                    >
                        {{ d.label }}
                    </button>
                </div>
                <button
                    class="lhm-refresh-btn"
                    @click="fetchSampleList"
                    :disabled="loadingList"
                >
                    <i
                        :class="
                            loadingList
                                ? 'ri-loader-4-line lhm-spin'
                                : 'ri-refresh-line'
                        "
                    ></i>
                </button>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- MAIN PANELS                                                -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="lhm-panels">
            <!-- ── LEFT: Sample list ─────────────────────────────── -->
            <div class="lhm-list-col">
                <!-- Search bar (debounced server-side) -->
                <div class="lhm-search-bar">
                    <i class="ri-search-line"></i>
                    <input
                        v-model="searchQuery"
                        @input="onSearchInput"
                        placeholder="Cari No. Sampel / Barang / PO..."
                        class="lhm-search-input"
                    />
                    <span
                        v-if="searchQuery"
                        class="lhm-search-clear"
                        @click="clearSearch"
                        >×</span
                    >
                </div>

                <!-- Filter tabs -->
                <div class="lhm-filter-tabs">
                    <button
                        :class="[
                            'lhm-filter-tab',
                            filter === 'semua' ? 'active' : '',
                        ]"
                        @click="setFilter('semua')"
                    >
                        Semua
                        <span class="lhm-filter-badge">{{
                            summary.semua
                        }}</span>
                    </button>
                    <button
                        :class="[
                            'lhm-filter-tab',
                            filter === 'belum_selesai' ? 'active' : '',
                        ]"
                        @click="setFilter('belum_selesai')"
                    >
                        Belum Selesai
                        <span
                            class="lhm-filter-badge lhm-filter-badge--pending"
                            >{{ summary.belum_selesai }}</span
                        >
                    </button>
                    <button
                        :class="[
                            'lhm-filter-tab',
                            filter === 'selesai' ? 'active' : '',
                        ]"
                        @click="setFilter('selesai')"
                    >
                        Finalisasi
                        <span class="lhm-filter-badge lhm-filter-badge--done">{{
                            summary.selesai
                        }}</span>
                    </button>
                </div>

                <!-- Skeleton loading -->
                <template v-if="loadingList">
                    <div v-for="n in 4" :key="n" class="lhm-card lhm-skeleton">
                        <div class="lhm-sk-line" style="width: 55%"></div>
                        <div
                            class="lhm-sk-line"
                            style="width: 80%; height: 10px; margin-top: 6px"
                        ></div>
                        <div class="d-flex gap-2 mt-2">
                            <div class="lhm-sk-chip"></div>
                            <div class="lhm-sk-chip"></div>
                            <div class="lhm-sk-chip"></div>
                        </div>
                    </div>
                </template>

                <!-- Empty state -->
                <div v-else-if="!sampleList.length" class="lhm-list-empty">
                    <i class="ri-inbox-line"></i>
                    <p>
                        {{
                            pagination.total === 0
                                ? "Tidak ada sampel pada periode ini"
                                : "Tidak ada hasil yang sesuai"
                        }}
                    </p>
                </div>

                <!-- Sample cards -->
                <template v-else>
                    <div
                        v-for="sample in sampleList"
                        :key="sample.no_sampel"
                        class="lhm-card"
                        :class="{
                            'lhm-card--closed': sample.is_selesai,
                            'lhm-card--selected':
                                selectedSample &&
                                selectedSample.no_sampel === sample.no_sampel,
                        }"
                    >
                        <!-- Card header -->
                        <div class="lhm-card-head">
                            <div class="lhm-card-head-left">
                                <span class="lhm-sampel-no">{{
                                    sample.no_sampel
                                }}</span>
                                <span
                                    v-if="sample.is_selesai"
                                    class="lhm-badge lhm-badge--closed"
                                >
                                    <i class="ri-checkbox-circle-line me-1"></i
                                    >Finalisasi
                                </span>
                            </div>
                            <div class="lhm-card-head-right">
                                <span class="lhm-card-date">{{
                                    formatTanggal(sample.tanggal)
                                }}</span>
                                <button
                                    class="lhm-qr-view-btn"
                                    @click.stop="openQrModal(sample)"
                                    title="Lihat QR Code"
                                >
                                    <i class="ri-qr-code-line"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Card meta -->
                        <div class="lhm-card-meta">
                            <span
                                ><i class="ri-barcode-box-line"></i>
                                {{ sample.no_po }}</span
                            >
                            <span
                                ><i class="ri-settings-3-line"></i>
                                {{ sample.nama_mesin || "-" }}</span
                            >
                            <span
                                ><i class="ri-user-line"></i>
                                {{ sample.registrar }}</span
                            >
                            <span
                                v-if="
                                    sample.is_multi_print === 'Y' &&
                                    sample.multi_qr_list &&
                                    sample.multi_qr_list.length
                                "
                            >
                                <i class="ri-qr-code-line"></i>
                                {{ sample.multi_qr_list.length }} Sub QR
                            </span>
                        </div>
                        <div class="lhm-card-barang">
                            {{ sample.nama_barang }}
                        </div>

                        <!-- Analisa chips -->
                        <div
                            v-if="sample.analisa && sample.analisa.length"
                            class="lhm-chips"
                        >
                            <button
                                v-for="analisa in sample.analisa"
                                :key="analisa.id"
                                class="lhm-chip"
                                :class="chipClass(analisa, sample)"
                                :disabled="sample.is_selesai"
                                :title="analisa.Kode_Analisa"
                                @click="
                                    !sample.is_selesai &&
                                        selectAnalisa(sample, analisa)
                                "
                            >
                                <i
                                    v-if="analisa.is_done"
                                    class="ri-check-line"
                                ></i>
                                <i
                                    v-else-if="
                                        analisa.is_started && !analisa.is_done
                                    "
                                    class="ri-loader-4-line lhm-spin"
                                ></i>
                                <i
                                    v-else-if="analisa.has_resampling"
                                    class="ri-refresh-line"
                                ></i>
                                {{ analisa.Jenis_Analisa }}
                            </button>
                        </div>
                        <div v-else class="lhm-no-analisa">
                            <i class="ri-information-line me-1"></i>Tidak ada
                            analisa terkonfigurasi untuk akun ini
                        </div>
                    </div>
                </template>

                <!-- Pagination -->
                <div
                    v-if="!loadingList && pagination.total_pages > 1"
                    class="lhm-pagination"
                >
                    <button
                        class="lhm-page-btn"
                        :disabled="pagination.current_page <= 1"
                        @click="goToPage(pagination.current_page - 1)"
                    >
                        <i class="ri-arrow-left-s-line"></i> Sebelumnya
                    </button>
                    <span class="lhm-page-info">
                        <strong>{{ pagination.current_page }}</strong> /
                        {{ pagination.total_pages }}
                    </span>
                    <button
                        class="lhm-page-btn"
                        :disabled="
                            pagination.current_page >= pagination.total_pages
                        "
                        @click="goToPage(pagination.current_page + 1)"
                    >
                        Berikutnya <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>
                <div
                    v-if="!loadingList && pagination.total > 0"
                    class="lhm-pagination-info"
                >
                    Menampilkan {{ pagination.from }}–{{ pagination.to }} dari
                    {{ pagination.total }} sampel
                </div>
            </div>
            <!-- /lhm-list-col -->

            <!-- ── RIGHT: Form panel ──────────────────────────────── -->
            <div id="lhm-form-panel" class="lhm-form-col">
                <!-- Empty state -->
                <div
                    v-if="!selectedSample && !loading.detailTemplate"
                    class="lhm-form-empty"
                >
                    <DotLottieVue
                        style="height: 220px; width: 280px"
                        autoplay
                        loop
                        src="/animation/labAnimation.lottie"
                    />
                    <p class="lhm-form-empty-text">
                        Pilih analisa dari daftar sampel di sebelah kiri untuk
                        memulai
                    </p>
                </div>

                <!-- Active sample banner -->
                <div v-if="selectedSample" class="lhm-active-banner">
                    <div class="lhm-banner-left">
                        <div class="lhm-banner-sampel">
                            {{ selectedSample.no_sampel }}
                        </div>
                        <div class="lhm-banner-meta">
                            <span>{{ selectedSample.nama_barang }}</span>
                            <span class="lhm-sep">·</span>
                            <span>{{ selectedSample.nama_mesin }}</span>
                            <span class="lhm-sep">·</span>
                            <span>No. PO: {{ selectedSample.no_po }}</span>
                            <span
                                v-if="
                                    selectedSample.no_batch &&
                                    selectedSample.no_batch !== '-'
                                "
                                class="lhm-sep"
                                >·</span
                            >
                            <span
                                v-if="
                                    selectedSample.no_batch &&
                                    selectedSample.no_batch !== '-'
                                "
                                >Batch: {{ selectedSample.no_batch }}</span
                            >
                            <span class="lhm-sep">·</span>
                            <span>Oleh: {{ selectedSample.registrar }}</span>
                        </div>
                    </div>
                    <button
                        class="lhm-banner-close"
                        @click="clearSelection"
                        title="Tutup"
                    >
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <!-- Loading template -->
                <div v-if="loading.detailTemplate" class="lhm-form-loading">
                    <div
                        class="spinner-border text-primary"
                        role="status"
                    ></div>
                    <span>Memuat template analisa...</span>
                </div>

                <!-- Form area -->
                <div
                    v-if="
                        selectedSample &&
                        selectedTemplating &&
                        !loading.detailTemplate
                    "
                >
                    <!-- ── Multi-print: sub-sample selection list ── -->
                    <div
                        v-if="
                            nomorSampel.sampleDetails &&
                            nomorSampel.sampleDetails.is_multi_print === 'Y'
                        "
                    >
                        <!-- Step 1: Pilih sub sampel dari list -->
                        <div
                            v-if="!nomorSampel.multiSampel"
                            class="lhm-qr-panel"
                        >
                            <div class="lhm-qr-panel-head">
                                <i class="ri-qr-code-line lhm-qr-icon"></i>
                                <div>
                                    <div class="lhm-qr-title">
                                        Pilih Nomor Sub Sampel (Multi QR)
                                    </div>
                                    <div class="lhm-qr-sub">
                                        {{
                                            selectedSample &&
                                            selectedSample.multi_qr_list
                                                ? selectedSample.multi_qr_list
                                                      .length
                                                : 0
                                        }}
                                        sub sampel tersedia
                                    </div>
                                </div>
                            </div>
                            <div class="lhm-qr-list">
                                <button
                                    v-for="qr in selectedSample &&
                                    selectedSample.multi_qr_list
                                        ? selectedSample.multi_qr_list
                                        : []"
                                    :key="qr.no_po_multi"
                                    class="lhm-qr-row"
                                    :class="{
                                        'lhm-qr-row--done':
                                            qr.flag_selesai === 'Y',
                                        'lhm-qr-row--resampling':
                                            qr.is_resampling_origin,
                                    }"
                                    :disabled="
                                        qr.flag_selesai === 'Y' ||
                                        qr.is_resampling_origin ||
                                        loading.multiSampel === qr.no_po_multi
                                    "
                                    @click="selectSubSampel(qr.no_po_multi)"
                                >
                                    <span class="lhm-qr-row-label">
                                        <i
                                            class="ri-qr-code-line"
                                            style="
                                                font-size: 14px;
                                                flex-shrink: 0;
                                            "
                                        ></i>
                                        {{ qr.no_po_multi }}
                                    </span>
                                    <span
                                        v-if="qr.flag_selesai === 'Y'"
                                        class="lhm-qr-badge lhm-qr-badge--done"
                                    >
                                        <i class="ri-check-double-line"></i>
                                        Selesai
                                    </span>
                                    <span
                                        v-else-if="qr.is_resampling_origin"
                                        class="lhm-qr-badge lhm-qr-badge--resamp"
                                    >
                                        <i class="ri-refresh-line"></i>
                                        Resampling
                                    </span>
                                    <span
                                        v-else-if="
                                            loading.multiSampel ===
                                            qr.no_po_multi
                                        "
                                    >
                                        <span
                                            class="spinner-border spinner-border-sm text-primary"
                                        ></span>
                                    </span>
                                    <i
                                        v-else
                                        class="ri-arrow-right-s-line lhm-qr-arrow"
                                    ></i>
                                </button>
                                <div
                                    v-if="
                                        !selectedSample ||
                                        !selectedSample.multi_qr_list ||
                                        !selectedSample.multi_qr_list.length
                                    "
                                    class="lhm-qr-empty"
                                >
                                    <i class="ri-information-line me-1"></i
                                    >Tidak ada sub sampel terdaftar
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Form setelah sub sampel dipilih -->
                        <div v-if="nomorSampel.multiSampel">
                            <button
                                class="lhm-qr-back"
                                @click="nomorSampel.multiSampel = null"
                            >
                                <i class="ri-arrow-left-s-line me-1"></i>Pilih
                                Sub Sampel Lain
                            </button>
                            <div
                                v-if="nomorSampel.multiSampel.is_done"
                                class="lhm-done-state"
                            >
                                <DotLottieVue
                                    style="height: 140px; width: 180px"
                                    autoplay
                                    loop
                                    src="/animation/done.json"
                                />
                                <p class="fw-semibold text-success">
                                    Sub sampel
                                    <strong>{{ samplePoMulti }}</strong> sudah
                                    selesai dianalisa
                                </p>
                            </div>
                            <div v-else>
                                <MultiRumus
                                    v-if="selectedTemplating.formula !== null"
                                    :selectedTemplating="selectedTemplating"
                                    :is_multi_print="
                                        nomorSampel.sampleDetails
                                            ?.is_multi_print ?? null
                                    "
                                    :no_ticket="
                                        nomorSampel.multiSampel?.no_ticket ??
                                        null
                                    "
                                    :Id_Jenis_Analisa="reactiveIdJenisAnalisa"
                                    :No_Po_Sampel="
                                        nomorSampel.sampleDetails.no_sampel ??
                                        null
                                    "
                                    :Id_Mesin="
                                        nomorSampel.sampleDetails.Id_Mesin ??
                                        null
                                    "
                                    :kodeAnalisa="kodeAnalisa"
                                    :sampleNumber="sampleNumber"
                                    :Flag_Foto="Flag_Foto"
                                />
                                <NotRumus
                                    v-else
                                    :selectedTemplating="selectedTemplating"
                                    :is_multi_print="
                                        nomorSampel.sampleDetails
                                            ?.is_multi_print ?? null
                                    "
                                    :Id_Jenis_Analisa="reactiveIdJenisAnalisa"
                                    :No_Po_Sampel="
                                        nomorSampel.sampleDetails.no_sampel
                                    "
                                    :No_Fak_Sub_Po="samplePoMulti"
                                    :Id_Mesin="
                                        nomorSampel.sampleDetails.Id_Mesin ??
                                        null
                                    "
                                    :kodeAnalisa="kodeAnalisa"
                                    :Flag_Foto="Flag_Foto"
                                    :sampleNumber="sampleNumber"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- ── Single (non-multi-print) ── -->
                    <div v-else>
                        <MultiRumus
                            v-if="selectedTemplating.formula !== null"
                            :selectedTemplating="selectedTemplating"
                            :Id_Jenis_Analisa="reactiveIdJenisAnalisa"
                            :No_Po_Sampel="nomorSampel.sampleDetails.no_sampel"
                            :is_multi_print="
                                nomorSampel.sampleDetails?.is_multi_print ??
                                null
                            "
                            :Id_Mesin="
                                nomorSampel.sampleDetails.Id_Mesin ?? null
                            "
                            :kodeAnalisa="kodeAnalisa"
                            :Flag_Foto="Flag_Foto"
                            :sampleNumber="sampleNumber"
                        />
                        <NotRumus
                            v-else
                            :selectedTemplating="selectedTemplating"
                            :Id_Jenis_Analisa="reactiveIdJenisAnalisa"
                            :No_Po_Sampel="nomorSampel.sampleDetails.no_sampel"
                            :kodeAnalisa="kodeAnalisa"
                            :is_multi_print="
                                nomorSampel.sampleDetails?.is_multi_print ??
                                null
                            "
                            :Id_Mesin="
                                nomorSampel.sampleDetails.Id_Mesin ?? null
                            "
                            :Flag_Foto="Flag_Foto"
                            :sampleNumber="sampleNumber"
                        />
                    </div>
                </div>
                <!-- /form area -->
            </div>
            <!-- /lhm-form-col -->
        </div>
        <!-- /lhm-panels -->

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- QR CODE MODAL                                              -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div
            v-if="qrModal.visible"
            class="lhm-modal-overlay"
            @click.self="closeQrModal"
        >
            <div class="lhm-modal-box">
                <!-- Modal header -->
                <div class="lhm-modal-header bg-primary">
                    <div class="lhm-modal-title">
                        <i class="ri-qr-code-line me-2"></i>
                        QR Code ·
                        {{ qrModal.sample && qrModal.sample.no_sampel }}
                    </div>
                    <button class="lhm-modal-close" @click="closeQrModal">
                        <i class="ri-close-line"></i>
                    </button>
                </div>

                <!-- Modal body -->
                <div class="lhm-modal-body" v-if="qrModal.sample">
                    <!-- Sample info strip -->
                    <div class="lhm-modal-info">
                        <span
                            ><i class="ri-box-3-line me-1"></i
                            >{{ qrModal.sample.nama_barang }}</span
                        >
                        <span
                            ><i class="ri-barcode-box-line me-1"></i
                            >{{ qrModal.sample.no_po }}</span
                        >
                        <span
                            ><i class="ri-settings-3-line me-1"></i
                            >{{ qrModal.sample.nama_mesin }}</span
                        >
                        <span
                            v-if="
                                qrModal.sample.no_batch &&
                                qrModal.sample.no_batch !== '-'
                            "
                        >
                            <i class="ri-stack-line me-1"></i>Batch
                            {{ qrModal.sample.no_batch }}
                        </span>
                    </div>

                    <!-- Single QR (non-multi) -->
                    <div
                        v-if="qrModal.sample.is_multi_print !== 'Y'"
                        class="lhm-modal-single-qr"
                    >
                        <div class="lhm-ticket">
                            <div class="lhm-ticket-header">
                                <div class="lhm-ticket-icon">
                                    <i class="ri-flask-line"></i>
                                </div>
                                <div class="lhm-ticket-name">
                                    {{ qrModal.sample.nama_barang }}
                                </div>
                            </div>
                            <div class="lhm-ticket-body">
                                <div class="lhm-ticket-info">
                                    <div class="lhm-ticket-row">
                                        <i class="ri-barcode-line"></i
                                        ><span>{{
                                            qrModal.sample.no_sampel
                                        }}</span>
                                    </div>
                                    <div class="lhm-ticket-row">
                                        <i class="ri-file-list-3-line"></i
                                        ><span>{{
                                            qrModal.sample.no_split_po || "-"
                                        }}</span>
                                    </div>
                                    <div class="lhm-ticket-row">
                                        <i class="ri-calendar-line"></i
                                        ><span>{{
                                            formatTanggal(
                                                qrModal.sample.tanggal
                                            )
                                        }}</span>
                                    </div>
                                    <div class="lhm-ticket-row">
                                        <i class="ri-settings-3-line"></i
                                        ><span>{{
                                            qrModal.sample.nama_mesin || "-"
                                        }}</span>
                                    </div>
                                </div>
                                <div class="lhm-ticket-qr">
                                    <qrcode-vue
                                        :value="qrModal.sample.no_sampel"
                                        :size="120"
                                        level="H"
                                        foreground="#1e293b"
                                        background="transparent"
                                    />
                                    <div class="lhm-qr-label">SCAN ME</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Multi QR grid -->
                    <div v-else class="lhm-modal-multi-qr">
                        <div class="lhm-modal-multi-title">
                            <i class="ri-qr-code-line me-1"></i>Sub QR Code
                            <span class="badge bg-primary ms-1">{{
                                qrModal.sample.multi_qr_list
                                    ? qrModal.sample.multi_qr_list.length
                                    : 0
                            }}</span>
                        </div>
                        <div class="lhm-multi-qr-grid">
                            <div
                                v-for="qr in qrModal.sample.multi_qr_list"
                                :key="qr.no_po_multi"
                                class="lhm-ticket lhm-ticket--sm"
                                :class="{
                                    'lhm-ticket--done': qr.flag_selesai === 'Y',
                                }"
                            >
                                <div class="lhm-ticket-header">
                                    <div
                                        class="lhm-ticket-icon lhm-ticket-icon--sm"
                                    >
                                        <i class="ri-qr-code-line"></i>
                                    </div>
                                    <div class="lhm-ticket-name">
                                        {{ qrModal.sample.nama_barang }}
                                    </div>
                                </div>
                                <div class="lhm-ticket-body">
                                    <div class="lhm-ticket-info">
                                        <div class="lhm-ticket-row">
                                            <i class="ri-barcode-line"></i
                                            ><span>{{ qr.no_po_multi }}</span>
                                        </div>
                                        <div class="lhm-ticket-row">
                                            <i class="ri-calendar-line"></i
                                            ><span>{{
                                                formatTanggal(
                                                    qrModal.sample.tanggal
                                                )
                                            }}</span>
                                        </div>
                                    </div>
                                    <div class="lhm-ticket-qr">
                                        <qrcode-vue
                                            :value="qr.no_po_multi"
                                            :size="90"
                                            level="H"
                                            foreground="#1e293b"
                                            background="transparent"
                                        />
                                        <div class="lhm-qr-label">SCAN ME</div>
                                        <span
                                            v-if="qr.flag_selesai === 'Y'"
                                            class="lhm-qr-done-badge"
                                        >
                                            <i class="ri-check-double-line"></i>
                                            Selesai
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /modal-body -->
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import Swal from "sweetalert2";
import { reactive } from "vue";
import { defineAsyncComponent } from "vue";
import QrcodeVue from "qrcode.vue";

const MultiRumus = defineAsyncComponent(() =>
    import("./perhitungan-backup/MultiRumusFormulator.vue")
);
const NotRumus = defineAsyncComponent(() =>
    import("./perhitungan-backup/NotRumusFormulator.vue")
);

export default {
    name: "HomeLabFormulatorTrial",
    components: { DotLottieVue, MultiRumus, NotRumus, QrcodeVue },

    data() {
        return {
            /* list state */
            sampleList: [],
            loadingList: false,
            days: 7,
            searchQuery: "",
            filter: "semua",
            searchTimer: null,
            dayOptions: [
                { val: 1, label: "Hari Ini" },
                { val: 7, label: "7 Hari" },
                { val: 14, label: "14 Hari" },
                { val: 30, label: "30 Hari" },
            ],

            /* pagination */
            pagination: {
                current_page: 1,
                per_page: 20,
                total: 0,
                total_pages: 1,
                from: 0,
                to: 0,
            },

            /* filter tab badge counts */
            summary: {
                semua: 0,
                selesai: 0,
                belum_selesai: 0,
            },

            /* selection */
            selectedSample: null,
            selectedActiveAnalisaId: null,

            /* form state */
            selectedTemplating: null,
            inputValues: reactive({}),
            sampleNumber: null,
            samplePoMulti: null,
            Flag_Foto: "T",
            reactiveIdJenisAnalisa: null,
            kodeAnalisa: null,
            nomorSampel: {
                sampleDetails: null,
                multiSampel: null,
            },
            loading: {
                detailTemplate: false,
                multiSampel: null,
            },

            /* QR modal */
            qrModal: {
                visible: false,
                sample: null,
            },
        };
    },

    methods: {
        /* ── List fetch ─────────────────────────────────────────── */
        async fetchSampleList() {
            this.loadingList = true;
            try {
                const { data } = await axios.get(
                    "/api/v1/formulator/uji-sampel/daftar",
                    {
                        params: {
                            days: this.days,
                            page: this.pagination.current_page,
                            filter: this.filter,
                            search: this.searchQuery,
                        },
                    }
                );
                this.sampleList = data.result || [];
                this.pagination = data.pagination || this.pagination;
                this.summary = data.summary || this.summary;
            } catch {
                Swal.fire(
                    "Gagal",
                    "Tidak dapat memuat daftar sampel.",
                    "error"
                );
            } finally {
                this.loadingList = false;
            }
        },

        setDays(d) {
            this.days = d;
            this.pagination.current_page = 1;
            this.fetchSampleList();
        },

        setFilter(f) {
            this.filter = f;
            this.pagination.current_page = 1;
            this.fetchSampleList();
        },

        goToPage(p) {
            this.pagination.current_page = p;
            this.fetchSampleList();
        },

        onSearchInput() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => {
                this.pagination.current_page = 1;
                this.fetchSampleList();
            }, 400);
        },

        clearSearch() {
            this.searchQuery = "";
            this.pagination.current_page = 1;
            this.fetchSampleList();
        },

        /* ── Select analisa ─────────────────────────────────────── */
        async selectAnalisa(sample, analisa) {
            this.sampleNumber = sample.no_sampel;
            this.selectedSample = sample;
            this.selectedActiveAnalisaId = analisa.id;
            this.reactiveIdJenisAnalisa = analisa.id;
            this.kodeAnalisa = analisa.Kode_Analisa;
            this.samplePoMulti = null;
            this.selectedTemplating = null;
            this.nomorSampel.multiSampel = null;
            this.nomorSampel.sampleDetails = {
                id: null,
                nama_barang: sample.nama_barang,
                no_sampel: sample.no_sampel,
                Berat_Sampel: 0,
                Jumlah_Pcs: 0,
                no_po: sample.no_po,
                tanggal: sample.tanggal,
                jam: sample.jam,
                no_split_po: sample.no_split_po,
                no_batch: sample.no_batch,
                nama_mesin: sample.nama_mesin,
                seri_mesin: "",
                keterangan: "",
                kode_barang: sample.kode_barang,
                Id_Mesin: sample.Id_Mesin,
                kode_perusahaan: "",
                is_multi_print: sample.is_multi_print,
                jumlah_print: sample.jumlah_print,
                is_resampling: false,
                analisa: sample.analisa.map((a) => ({
                    id: a.id,
                    Kode_Analisa: a.Kode_Analisa,
                    Jenis_Analisa: a.Jenis_Analisa,
                    Nama_Mesin: null,
                    is_done: a.is_done,
                })),
            };

            this.loading.detailTemplate = true;
            try {
                const response = await axios.get(
                    `/api/v1/formulator/uji-trial/${analisa.id}/parameter-perhitungan-old`
                );
                if (response.status === 200 && response.data?.result) {
                    this.selectedTemplating = response.data.result;
                    this.Flag_Foto = response.data.result?.sesi_foto ?? "T";
                    (this.selectedTemplating.parameter || []).forEach((p) => {
                        this.inputValues[p.id_qc] = null;
                    });
                } else {
                    this.selectedTemplating = null;
                }
            } catch (err) {
                this.selectedTemplating = null;
                Swal.fire(
                    "Peringatan",
                    err?.response?.data?.message ||
                        err?.message ||
                        "Template analisa tidak ditemukan.",
                    "warning"
                );
            } finally {
                this.loading.detailTemplate = false;
            }

            this.$nextTick(() => {
                const el = document.getElementById("lhm-form-panel");
                if (el && window.innerWidth < 1024) {
                    el.scrollIntoView({ behavior: "smooth", block: "start" });
                }
            });
        },

        clearSelection() {
            this.selectedSample = null;
            this.selectedActiveAnalisaId = null;
            this.selectedTemplating = null;
            this.nomorSampel.sampleDetails = null;
            this.nomorSampel.multiSampel = null;
            this.samplePoMulti = null;
        },

        /* ── Multi-QR sub-sample ────────────────────────────────── */
        async selectSubSampel(noPoMulti) {
            this.samplePoMulti = noPoMulti;
            await this.fetchNoMultiQrcode();
        },

        async fetchNoMultiQrcode() {
            this.nomorSampel.multiSampel = null;
            const no_sampel = this.samplePoMulti;
            if (!no_sampel) return;
            this.loading.multiSampel = no_sampel;
            try {
                const response = await axios.get(
                    `/api/v1/formulator/${this.sampleNumber}/${no_sampel}/multi-print/${this.reactiveIdJenisAnalisa}`
                );
                if (
                    response.data.success === false &&
                    response.data.status === 404
                ) {
                    await Swal.fire({
                        icon: "warning",
                        title: "Data Tidak Ditemukan",
                        text: "Nomor sub sampel tidak tersedia.",
                        confirmButtonText: "Tutup",
                    });
                    return;
                }
                this.nomorSampel.multiSampel = response.data.result;
            } catch (error) {
                this.nomorSampel.multiSampel = null;
                await Swal.fire({
                    icon: "error",
                    title: "Terjadi Kesalahan",
                    text:
                        error?.response?.data?.message ||
                        error.message ||
                        "Gagal mengambil data sub sampel.",
                    confirmButtonText: "Tutup",
                });
            } finally {
                this.loading.multiSampel = null;
            }
        },

        /* ── Chip styling ───────────────────────────────────────── */
        chipClass(analisa, sample) {
            if (sample.is_selesai) return "lhm-chip--disabled";
            if (analisa.is_done) return "lhm-chip--done";
            if (analisa.has_resampling) return "lhm-chip--resampling";
            if (analisa.is_started) return "lhm-chip--progress";
            if (
                this.selectedActiveAnalisaId === analisa.id &&
                this.selectedSample?.no_sampel === sample.no_sampel
            )
                return "lhm-chip--active";
            return "lhm-chip--pending";
        },

        formatTanggal(val) {
            if (!val) return "-";
            const d = new Date(val);
            return d.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },

        /* ── QR modal ───────────────────────────────────────────── */
        openQrModal(sample) {
            this.qrModal.sample = sample;
            this.qrModal.visible = true;
            document.body.style.overflow = "hidden";
        },

        closeQrModal() {
            this.qrModal.visible = false;
            this.qrModal.sample = null;
            document.body.style.overflow = "";
        },
    },

    mounted() {
        this.fetchSampleList();
        window.addEventListener(
            "keydown",
            (this._onKeydown = (e) => {
                if (e.key === "Escape" && this.qrModal.visible)
                    this.closeQrModal();
            })
        );
    },

    beforeUnmount() {
        clearTimeout(this.searchTimer);
        window.removeEventListener("keydown", this._onKeydown);
        document.body.style.overflow = "";
    },
};
</script>

<style scoped>
/* ════════════════════════════════════════════════════════════ */
/* WRAP                                                          */
/* ════════════════════════════════════════════════════════════ */
.lhm-wrap {
    display: flex;
    flex-direction: column;
    height: 100vh;
    background: #f4f6fb;
    overflow: hidden;
}

/* ════════════════════════════════════════════════════════════ */
/* TITLE BAR                                                     */
/* ════════════════════════════════════════════════════════════ */
.lhm-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.85rem 1.25rem;
    flex-shrink: 0;
}
.lhm-title-left {
    display: flex;
    align-items: center;
    gap: 0.85rem;
}
.lhm-title-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.lhm-title-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: rgba(255, 255, 255, 0.75);
    flex-shrink: 0;
}
.lhm-title-main {
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}
.lhm-title-sub {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.6);
}

.lhm-day-tabs {
    display: flex;
    gap: 3px;
}
.lhm-day-tab {
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: transparent;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    transition: all 0.15s;
}
.lhm-day-tab.active,
.lhm-day-tab:hover {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.5);
}

.lhm-refresh-btn {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.15s;
}
.lhm-refresh-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.3);
}
.lhm-refresh-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ════════════════════════════════════════════════════════════ */
/* PANELS                                                        */
/* ════════════════════════════════════════════════════════════ */
.lhm-panels {
    display: flex;
    flex: 1;
    overflow: hidden;
}

/* ── Left list ── */
.lhm-list-col {
    width: 400px;
    min-width: 300px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-right: 1px solid #e5e7eb;
    overflow-y: auto;
}

/* ── Search bar ── */
.lhm-search-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1rem;
    border-bottom: 1px solid #f0f0f0;
    background: #f8f9fc;
    flex-shrink: 0;
    color: #9ca3af;
    position: sticky;
    top: 0;
    z-index: 3;
}
.lhm-search-input {
    flex: 1;
    border: none;
    background: transparent;
    font-size: 13px;
    outline: none;
    color: #374151;
}
.lhm-search-clear {
    cursor: pointer;
    font-size: 16px;
    color: #9ca3af;
    line-height: 1;
    padding: 0 2px;
}
.lhm-search-clear:hover {
    color: #374151;
}

/* ── Filter tabs ── */
.lhm-filter-tabs {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    flex-shrink: 0;
    position: sticky;
    top: 44px;
    z-index: 2;
}
.lhm-filter-tab {
    flex: 1;
    padding: 0.55rem 0.4rem;
    font-size: 12px;
    font-weight: 500;
    border: none;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    white-space: nowrap;
}
.lhm-filter-tab:hover {
    color: #374151;
    background: #f8f9fc;
}
.lhm-filter-tab.active {
    color: var(--vz-primary);
    border-bottom-color: var(--vz-primary);
}

.lhm-filter-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9px;
    font-size: 10px;
    font-weight: 700;
    background: #e5e7eb;
    color: #6b7280;
}
.lhm-filter-badge--pending {
    background: #fef3c7;
    color: #92400e;
}
.lhm-filter-badge--done {
    background: #dcfce7;
    color: #166534;
}

/* ── Sample card ── */
.lhm-card {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f0f2f5;
    transition: background 0.15s;
    cursor: default;
}
.lhm-card:hover {
    background: #f8f9fc;
}
.lhm-card--selected {
    background: rgba(var(--vz-primary-rgb), 0.07) !important;
    border-left: 3px solid var(--vz-primary);
}
.lhm-card--closed {
    opacity: 0.65;
    background: #f9fafb;
}
.lhm-card--closed .lhm-sampel-no {
    color: #9ca3af;
}

.lhm-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.3rem;
    flex-wrap: wrap;
}
.lhm-card-head-left {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.lhm-sampel-no {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    font-family: monospace;
}
.lhm-card-date {
    font-size: 11px;
    color: #9ca3af;
    white-space: nowrap;
}

.lhm-badge {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 20px;
    line-height: 1;
}
.lhm-badge--closed {
    background: #f3f4f6;
    color: #6b7280;
}

.lhm-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.75rem;
    font-size: 11px;
    color: #6b7280;
    margin-bottom: 0.25rem;
}
.lhm-card-meta i {
    font-size: 11px;
    margin-right: 2px;
}
.lhm-card-barang {
    font-size: 12px;
    color: #374151;
    font-weight: 500;
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Analisa chips ── */
.lhm-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    max-height: 112px;
    overflow-y: auto;
    padding-right: 2px;
}
.lhm-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 11px;
    font-weight: 500;
    padding: 0.28rem 0.65rem;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lhm-chip--pending {
    background: rgba(var(--vz-primary-rgb), 0.1);
    color: var(--vz-primary);
}
.lhm-chip--pending:hover {
    background: rgba(var(--vz-primary-rgb), 0.18);
}
.lhm-chip--active {
    background: var(--vz-primary);
    color: #fff;
}
.lhm-chip--progress {
    background: #fef3c7;
    color: #92400e;
    cursor: pointer;
}
.lhm-chip--progress:hover {
    background: #fde68a;
}
.lhm-chip--done {
    background: #dcfce7;
    color: #166534;
    cursor: default;
}
.lhm-chip--disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}
.lhm-chip--resampling {
    background: #fff3cd;
    color: #664d03;
    border: 1px solid #ffc107;
    cursor: pointer;
}
.lhm-chip--resampling:hover {
    background: #ffe69c;
}

.lhm-no-analisa {
    font-size: 11px;
    color: #9ca3af;
    font-style: italic;
}

/* ── List empty ── */
.lhm-list-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    color: #9ca3af;
    text-align: center;
}
.lhm-list-empty i {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
}
.lhm-list-empty p {
    font-size: 13px;
    margin: 0;
}

/* skeleton */
.lhm-skeleton {
    cursor: default;
    pointer-events: none;
    animation: lhm-shimmer 1.4s infinite;
    background: linear-gradient(90deg, #f0f2f5 25%, #e8eaf0 50%, #f0f2f5 75%);
    background-size: 200% 100%;
}
@keyframes lhm-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
.lhm-sk-line {
    height: 14px;
    border-radius: 4px;
    background: rgba(0, 0, 0, 0.07);
}
.lhm-sk-chip {
    height: 24px;
    width: 60px;
    border-radius: 20px;
    background: rgba(0, 0, 0, 0.07);
}

/* ── Pagination ── */
.lhm-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.65rem 1rem;
    border-top: 1px solid #f0f0f0;
    background: #fff;
    flex-shrink: 0;
    gap: 0.5rem;
    position: sticky;
    bottom: 0;
    z-index: 2;
}
.lhm-page-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f8f9fc;
    font-size: 12px;
    color: #374151;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.lhm-page-btn:hover:not(:disabled) {
    background: rgba(var(--vz-primary-rgb), 0.07);
    border-color: var(--vz-primary);
    color: var(--vz-primary);
}
.lhm-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.lhm-page-info {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}
.lhm-pagination-info {
    font-size: 11px;
    color: #9ca3af;
    text-align: center;
    padding: 0.25rem 1rem 0.6rem;
    flex-shrink: 0;
}

/* ── Right form panel ── */
.lhm-form-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    background: #f4f6fb;
    min-width: 0;
}

.lhm-form-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3rem 2rem;
}
.lhm-form-empty-text {
    font-size: 14px;
    color: #6b7280;
    margin-top: 0.5rem;
    max-width: 300px;
}

.lhm-active-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 0.75rem 1.25rem;
    background: #fff;
    border-bottom: 2px solid var(--vz-primary);
    flex-shrink: 0;
}
.lhm-banner-left {
    flex: 1;
    min-width: 0;
}
.lhm-banner-sampel {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
    font-family: monospace;
}
.lhm-banner-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.2rem 0.5rem;
    font-size: 12px;
    color: #6b7280;
    margin-top: 0.15rem;
}
.lhm-sep {
    color: #d1d5db;
}
.lhm-banner-close {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    flex-shrink: 0;
    background: #f3f4f6;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.lhm-banner-close:hover {
    background: #e5e7eb;
    color: #374151;
}

.lhm-form-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 3rem;
    color: #6b7280;
    font-size: 14px;
}

/* ── QR sub-sample panel ── */
.lhm-qr-panel {
    margin: 1rem;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
}
.lhm-qr-panel-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-bottom: 1px solid #f0f0f0;
    background: #f8f9fc;
}
.lhm-qr-icon {
    font-size: 22px;
    color: var(--vz-primary);
    flex-shrink: 0;
}
.lhm-qr-title {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}
.lhm-qr-sub {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 2px;
}
.lhm-qr-list {
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.lhm-qr-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #fff;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 13px;
    font-family: monospace;
    font-weight: 600;
    color: #1e293b;
    text-align: left;
}
.lhm-qr-row:not(:disabled):hover {
    background: rgba(var(--vz-primary-rgb), 0.06);
    border-color: var(--vz-primary);
}
.lhm-qr-row:disabled {
    cursor: not-allowed;
}
.lhm-qr-row--done {
    background: #f0fdf4;
    color: #166534;
    border-color: #bbf7d0;
    opacity: 0.85;
}
.lhm-qr-row--resampling {
    background: #fffbeb;
    color: #92400e;
    border-color: #fde68a;
    opacity: 0.9;
}
.lhm-qr-row-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.lhm-qr-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    font-size: 10px;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 20px;
    font-family: sans-serif;
    white-space: nowrap;
}
.lhm-qr-badge--done {
    background: #dcfce7;
    color: #166534;
}
.lhm-qr-badge--resamp {
    background: #fef3c7;
    color: #92400e;
}
.lhm-qr-arrow {
    font-size: 18px;
    color: #9ca3af;
}
.lhm-qr-empty {
    font-size: 12px;
    color: #9ca3af;
    padding: 0.75rem;
    text-align: center;
    font-style: italic;
}
.lhm-qr-back {
    display: inline-flex;
    align-items: center;
    margin: 1rem 1rem 0.5rem;
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f8f9fc;
    font-size: 12px;
    color: #6b7280;
    cursor: pointer;
    transition: background 0.15s;
}
.lhm-qr-back:hover {
    background: #e5e7eb;
    color: #374151;
}

.lhm-done-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem;
    text-align: center;
}

/* spin */
.lhm-spin {
    animation: lhm-spin-anim 0.7s linear infinite;
    display: inline-block;
}
@keyframes lhm-spin-anim {
    to {
        transform: rotate(360deg);
    }
}

/* ════════════════════════════════════════════════════════════ */
/* RESPONSIVE                                                    */
/* ════════════════════════════════════════════════════════════ */
@media (max-width: 1023px) {
    .lhm-wrap {
        height: auto;
        overflow: visible;
    }
    .lhm-panels {
        flex-direction: column;
        overflow: visible;
    }
    .lhm-list-col {
        width: 100%;
        min-width: 0;
        border-right: none;
        border-bottom: 1px solid #e5e7eb;
        max-height: 480px;
        overflow-y: auto;
    }
    .lhm-form-col {
        overflow-y: visible;
    }
}

@media (max-width: 767px) {
    .lhm-title-bar {
        padding: 0.65rem 0.85rem;
    }
    .lhm-title-main {
        font-size: 0.9rem;
    }
    .lhm-title-sub {
        display: none;
    }
    .lhm-day-tabs {
        gap: 2px;
    }
    .lhm-day-tab {
        padding: 0.25rem 0.45rem;
        font-size: 11px;
    }
    .lhm-card {
        padding: 0.7rem 0.85rem;
    }
    .lhm-list-col {
        max-height: 420px;
    }
    .lhm-filter-tab {
        font-size: 11px;
        padding: 0.45rem 0.25rem;
    }
    .lhm-pagination {
        padding: 0.5rem 0.75rem;
    }
    .lhm-page-btn {
        padding: 0.3rem 0.5rem;
        font-size: 11px;
    }
    .lhm-active-banner {
        padding: 0.6rem 0.85rem;
    }
    .lhm-banner-sampel {
        font-size: 13px;
    }
    .lhm-modal-box {
        width: 96vw;
        max-height: 92vh;
    }
    .lhm-multi-qr-grid {
        grid-template-columns: 1fr;
    }
}

/* ════════════════════════════════════════════════════════════ */
/* CARD HEAD RIGHT + QR VIEW BUTTON                             */
/* ════════════════════════════════════════════════════════════ */
.lhm-card-head-right {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-shrink: 0;
}
.lhm-qr-view-btn {
    width: 26px;
    height: 26px;
    border-radius: 7px;
    border: 1px solid #e5e7eb;
    background: #f8f9fc;
    color: #6b7280;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    flex-shrink: 0;
}
.lhm-qr-view-btn:hover {
    background: rgba(var(--vz-primary-rgb), 0.1);
    color: var(--vz-primary);
    border-color: var(--vz-primary);
}

/* ════════════════════════════════════════════════════════════ */
/* QR MODAL                                                      */
/* ════════════════════════════════════════════════════════════ */
.lhm-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(3px);
}
.lhm-modal-box {
    background: #fff;
    border-radius: 16px;
    width: 600px;
    max-width: 96vw;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}
.lhm-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1.25rem;
    flex-shrink: 0;
}
.lhm-modal-title {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
}
.lhm-modal-close {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    font-size: 17px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.lhm-modal-close:hover {
    background: rgba(255, 255, 255, 0.3);
}

.lhm-modal-body {
    overflow-y: auto;
    flex: 1;
    padding: 1rem;
}
.lhm-modal-info {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem 0.75rem;
    font-size: 12px;
    color: #6b7280;
    padding: 0.5rem 0.75rem;
    background: #f8f9fc;
    border-radius: 8px;
    margin-bottom: 1rem;
}
.lhm-modal-info i {
    color: var(--vz-primary);
}

/* Ticket card */
.lhm-modal-single-qr {
    display: flex;
    justify-content: center;
    padding: 0.5rem 0;
}
.lhm-ticket {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    overflow: hidden;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}
.lhm-ticket--sm {
    max-width: 100%;
}
.lhm-ticket--done {
    opacity: 0.7;
    background: #f8fffe;
}

.lhm-ticket-header {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.7rem 1rem;
    background: linear-gradient(
        135deg,
        rgba(var(--vz-primary-rgb), 0.08) 0%,
        rgba(var(--vz-primary-rgb), 0.04) 100%
    );
    border-bottom: 1px solid #f0f0f0;
}
.lhm-ticket-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--vz-primary);
    color: #fff;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.lhm-ticket-icon--sm {
    width: 28px;
    height: 28px;
    font-size: 13px;
    border-radius: 8px;
}
.lhm-ticket-name {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}

.lhm-ticket-body {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
}
.lhm-ticket-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}
.lhm-ticket-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 12px;
    color: #4b5563;
}
.lhm-ticket-row i {
    font-size: 13px;
    color: #9ca3af;
    flex-shrink: 0;
}
.lhm-ticket-qr {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}
.lhm-qr-label {
    font-size: 9px;
    font-weight: 700;
    color: #9ca3af;
    letter-spacing: 0.08em;
}
.lhm-qr-done-badge {
    font-size: 9px;
    font-weight: 700;
    padding: 0.15rem 0.4rem;
    background: #dcfce7;
    color: #166534;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
}

/* Multi QR grid */
.lhm-modal-multi-title {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
}
.lhm-multi-qr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 0.75rem;
}
</style>
