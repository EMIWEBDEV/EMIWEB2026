<template>
    <div class="container-fluid px-0 data-uji-container">
        <div class="card shadow-sm border-0 w-100 main-card">
            <div class="card-body">
                <!-- Header Section -->
                <div class="mb-4 text-center text-md-start section-header">
                    <div class="d-flex align-items-center mb-3 header-content">
                        <i
                            class="fas fa-vial text-primary me-3 fa-2x header-icon"
                        ></i>
                        <div>
                            <h1 class="h2 fw-bold text-primary mb-1 main-title">
                                Kumpulan Data Uji Analisis
                            </h1>
                            <p class="text-muted mb-0 subtitle">
                                <i class="fas fa-building me-1"></i>
                                Koleksi lengkap data uji laboratorium PT. Evo
                                Manufacturing Indonesia
                            </p>
                        </div>
                    </div>
                    <div class="divider bg-primary opacity-25 my-3"></div>
                </div>

                <div class="col-12 mt-3 content-area">
                    <div class="row mb-3">
                        <div class="col-lg-12 mb-3">
                            <label class="form-label">Tanggal</label>
                            <div
                                class="date-picker-wrapper"
                                style="min-width: 240px"
                            >
                                <el-date-picker
                                    v-model="dateRange"
                                    type="daterange"
                                    range-separator="-"
                                    start-placeholder="Mulai"
                                    end-placeholder="Akhir"
                                    format="DD MMM YYYY"
                                    value-format="YYYY-MM-DD"
                                    :size="'large'"
                                    @change="handleFilterChange"
                                    class="w-100 shadow-sm"
                                />
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Jenis QrCode</label>
                            <div
                                class="select-picker-wrapper"
                                style="min-width: 180px"
                            >
                                <el-select
                                    v-model="filterQr"
                                    placeholder="Tipe QR Code"
                                    size="large"
                                    clearable
                                    @change="handleFilterChange"
                                    class="w-100 shadow-sm"
                                >
                                    <el-option label="Multi QR" value="Y" />
                                    <el-option label="Single QR" value="T" />
                                </el-select>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label">Pencarian Global</label>
                            <div
                                class="search-wrapper position-relative flex-grow-1"
                            >
                                <span class="search-icon">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control ps-5 rounded-3 shadow-sm border-0 custom-search h-100"
                                    placeholder="Cari PO, Sampel, atau Barang..."
                                    v-model="search"
                                    @input="handleSearch"
                                />
                                <button
                                    v-if="search"
                                    @click="clearSearch"
                                    class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-decoration-none text-muted pe-3"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <hr />
                    <ListSkeleton :page="5" v-if="loading.loadingListData" />

                    <div v-else>
                        <div v-if="listData.length" class="row g-3">
                            <div
                                class="col-12"
                                v-for="(item, index) in listData"
                                :key="index"
                            >
                                <div
                                    class="card border-0 shadow-sm rounded-4 overflow-hidden hover-card"
                                >
                                    <div class="card-body p-0">
                                        <div class="d-flex align-items-stretch">
                                            <div
                                                class="status-strip bg-primary"
                                            ></div>

                                            <div class="p-3 p-md-4 w-100">
                                                <div
                                                    class="d-flex flex-column flex-lg-row gap-3 justify-content-between"
                                                >
                                                    <div
                                                        class="d-flex flex-column gap-3 flex-grow-1"
                                                    >
                                                        <div
                                                            class="d-flex align-items-center flex-wrap gap-2"
                                                        >
                                                            <span
                                                                class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill px-3 py-2"
                                                            >
                                                                <i
                                                                    class="fas fa-file-invoice me-1"
                                                                ></i>
                                                                {{ item.No_Po }}
                                                            </span>
                                                            <span
                                                                class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 rounded-pill px-3 py-2"
                                                            >
                                                                <i
                                                                    class="fas fa-code-branch me-1"
                                                                ></i>
                                                                {{
                                                                    item.No_Split_Po
                                                                }}
                                                            </span>
                                                            <span
                                                                v-if="
                                                                    item.Flag_Multi_QrCode ===
                                                                    'Y'
                                                                "
                                                                class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10 rounded-pill px-2 py-2"
                                                            >
                                                                Multi QR
                                                            </span>
                                                        </div>

                                                        <div
                                                            class="d-flex align-items-start gap-3"
                                                        >
                                                            <div
                                                                class="icon-box bg-light rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 mt-1"
                                                            >
                                                                <i
                                                                    class="fas fa-box-open text-dark fa-lg"
                                                                ></i>
                                                            </div>
                                                            <div>
                                                                <h6
                                                                    class="fw-bold text-dark mb-1 fs-5"
                                                                >
                                                                    {{
                                                                        item.Nama_Barang
                                                                    }}
                                                                </h6>
                                                                <div
                                                                    class="d-flex align-items-center gap-3 text-muted small mt-2"
                                                                >
                                                                    <div
                                                                        class="d-flex align-items-center gap-1 bg-light px-2 py-1 rounded"
                                                                    >
                                                                        <i
                                                                            class="fas fa-barcode"
                                                                        ></i>
                                                                        <span
                                                                            class="fw-semibold text-dark"
                                                                        >
                                                                            {{
                                                                                item.No_Po_Sampel
                                                                            }}
                                                                        </span>
                                                                    </div>
                                                                    <div
                                                                        class="d-flex align-items-center gap-1 bg-light px-2 py-1 rounded"
                                                                    >
                                                                        <i
                                                                            class="fas fa-tag"
                                                                        ></i>
                                                                        <span>{{
                                                                            item.Kode_Barang
                                                                        }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="d-flex flex-column justify-content-between align-items-lg-end border-top border-lg-0 pt-3 pt-lg-0 gap-3"
                                                    >
                                                        <div
                                                            class="text-muted small d-flex align-items-center gap-2"
                                                        >
                                                            <i
                                                                class="far fa-calendar-alt"
                                                            ></i>
                                                            {{
                                                                formatTanggal(
                                                                    item.Tanggal
                                                                )
                                                            }}
                                                            <span
                                                                class="vr"
                                                            ></span>
                                                            <i
                                                                class="far fa-clock"
                                                            ></i>
                                                            {{ item.Jam }}
                                                        </div>

                                                        <div class="d-flex">
                                                            <a
                                                                :href="`/finalisasi/formulator/hasil-uji-trial/by/${item.No_Po_Sampel}/${item.No_Split_Po}`"
                                                                class="btn btn-outline-primary rounded-pill px-4 btn-sm fw-semibold"
                                                            >
                                                                Detail
                                                                <i
                                                                    class="fas fa-arrow-right"
                                                                ></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-5">
                            <div class="d-flex justify-content-center mb-3">
                                <DotLottieVue
                                    style="height: 200px; width: 200px"
                                    autoplay
                                    loop
                                    src="/animation/empty.lottie"
                                />
                            </div>
                            <h5 class="text-muted mb-2 fw-bold">
                                Data Tidak Ditemukan
                            </h5>
                            <button
                                v-if="search || dateRange || filterQr"
                                @click="resetFilter"
                                class="btn btn-primary rounded-pill px-4 mt-2"
                            >
                                Reset Filter
                            </button>
                        </div>

                        <div
                            v-if="listData.length > 0"
                            class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top"
                        >
                            <span class="text-muted small">
                                <b>{{ pagination.from }}</b
                                >-<b>{{ pagination.to }}</b> dari
                                <b>{{ pagination.total }}</b>
                            </span>
                            <nav>
                                <ul class="pagination pagination-sm mb-0 gap-1">
                                    <li
                                        class="page-item"
                                        :class="{
                                            disabled:
                                                pagination.current_page === 1,
                                        }"
                                    >
                                        <button
                                            class="page-link rounded-2 border-0"
                                            @click="
                                                changePage(
                                                    pagination.current_page - 1
                                                )
                                            "
                                        >
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                    </li>
                                    <li
                                        v-for="page in visiblePages"
                                        :key="page"
                                        class="page-item"
                                        :class="{
                                            active:
                                                pagination.current_page ===
                                                page,
                                        }"
                                    >
                                        <button
                                            class="page-link rounded-2 border-0 shadow-none"
                                            @click="changePage(page)"
                                        >
                                            {{ page }}
                                        </button>
                                    </li>
                                    <li
                                        class="page-item"
                                        :class="{
                                            disabled:
                                                pagination.current_page ===
                                                pagination.last_page,
                                        }"
                                    >
                                        <button
                                            class="page-link rounded-2 border-0"
                                            @click="
                                                changePage(
                                                    pagination.current_page + 1
                                                )
                                            "
                                        >
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import ListSkeleton from "@/pages/vue/ui/ListSkeleton.vue";
import { ElDatePicker, ElSelect, ElOption } from "element-plus";

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
        ElDatePicker,
        ElSelect,
        ElOption,
    },
    data() {
        return {
            listData: [],
            search: "",
            dateRange: [],
            filterQr: "", // Variable untuk filter QR
            searchTimeout: null,
            pagination: {
                current_page: 1,
                last_page: 1,
                total: 0,
                per_page: 10,
                from: 0,
                to: 0,
            },
            loading: { loadingListData: false },
        };
    },
    computed: {
        visiblePages() {
            let pages = [];
            let start = Math.max(1, this.pagination.current_page - 2);
            let end = Math.min(
                this.pagination.last_page,
                this.pagination.current_page + 2
            );
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
    },
    methods: {
        handleSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.pagination.current_page = 1;
                this.fetchData();
            }, 500);
        },
        handleFilterChange() {
            this.pagination.current_page = 1;
            this.fetchData();
        },
        clearSearch() {
            this.search = "";
            this.handleSearch();
        },
        resetFilter() {
            this.search = "";
            this.dateRange = [];
            this.filterQr = ""; // Reset filter QR
            this.pagination.current_page = 1;
            this.fetchData();
        },
        changePage(page) {
            if (page >= 1 && page <= this.pagination.last_page) {
                this.pagination.current_page = page;
                this.fetchData();
                window.scrollTo({ top: 0, behavior: "smooth" });
            }
        },
        async fetchData() {
            this.loading.loadingListData = true;
            try {
                const params = {
                    page: this.pagination.current_page,
                    limit: this.pagination.per_page,
                    search: this.search,
                    qr_type: this.filterQr,
                };

                if (this.dateRange && this.dateRange.length === 2) {
                    params.start_date = this.dateRange[0];
                    params.end_date = this.dateRange[1];
                }

                const response = await axios.get(
                    "/api/v1/formualtor/finalisasi/hasil-uji-trial/current",
                    { params }
                );

                if (response.status === 200 && response.data?.success) {
                    this.listData = response.data.result;
                    const meta = response.data.pagination;
                    this.pagination = {
                        current_page: meta.current_page,
                        last_page: meta.total_pages,
                        total: meta.total,
                        per_page: meta.per_page,
                        from: meta.from,
                        to: meta.to,
                    };
                } else {
                    this.listData = [];
                }
            } catch (error) {
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },
        formatTanggal(tanggalString) {
            if (!tanggalString) return "-";
            return new Date(tanggalString).toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },
    },
    mounted() {
        this.fetchData();
    },
};
</script>

<style scoped>
.skeleton {
    animation: pulse 1.5s infinite;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 37%, #f0f0f0 63%);
    background-size: 400% 100%;
    border-radius: 4px;
}

@keyframes pulse {
    0% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0 50%;
    }
}

.skeleton-line {
    height: 20px;
    margin-bottom: 10px;
}

.skeleton-btn {
    height: 40px;
    width: 100%;
    margin-bottom: 15px;
}

.skeleton-table-cell {
    height: 25px;
    margin: 5px 0;
}

/* Container Styles */
.data-uji-container {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.main-card {
    border-radius: 12px;
    overflow: hidden;
    background-color: #ffffff;
}

.section-header {
    padding: 0 1.5rem;
}

.header-content {
    padding-top: 1rem;
}

.header-icon {
    transition: transform 0.3s ease;
}

.header-icon:hover {
    transform: scale(1.1);
}

.main-title {
    font-size: 1.75rem;
    letter-spacing: -0.5px;
}

.subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
}

.divider {
    height: 1px;
    background: linear-gradient(
        90deg,
        rgba(13, 110, 253, 0.1) 0%,
        rgba(13, 110, 253, 0.5) 50%,
        rgba(13, 110, 253, 0.1) 100%
    );
}

/* Accordion Styles */
.custom-accordion {
    --bs-accordion-border-width: 0;
}

.accordion-item-custom {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.accordion-item-custom:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: rgba(13, 110, 253, 0.2);
}

.accordion-btn {
    background-color: #ffffff;
    box-shadow: none;
}

.accordion-btn:not(.collapsed) {
    background-color: rgba(13, 110, 253, 0.05);
    color: #0d6efd;
}

.accordion-btn:focus {
    box-shadow: none;
    border-color: rgba(13, 110, 253, 0.2);
}

.icon-wrapper {
    transition: all 0.3s ease;
}

.accordion-btn:hover .icon-wrapper {
    background-color: rgba(13, 110, 253, 0.15);
}

.analysis-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-text {
    min-width: 0;
}

.badge-container {
    flex-wrap: wrap;
}

.code-badge {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
    display: inline-flex;
    align-items: center;
}

.date-badge {
    display: inline-flex;
    align-items: center;
}

.analysis-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.action-buttons {
    flex-shrink: 0;
}

.confirm-btn {
    transition: all 0.2s ease;
    min-width: 110px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.confirm-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(25, 135, 84, 0.2);
}

.menu-btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.menu-btn:hover {
    background-color: rgba(108, 117, 125, 0.1);
}

/* Accordion Body Styles */
.inner-accordion-body {
    background-color: #f9fafb;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.loading-spinner {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Tab Styles */
.result-tabs {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.nav-tabs-custom .nav-link {
    border: none;
    padding: 0.75rem 1.5rem;
    color: #6c757d;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    position: relative;
    margin-bottom: -1px;
}

.nav-tabs-custom .nav-link.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background-color: transparent;
}

.nav-tabs-custom .nav-link:hover:not(.active) {
    color: #495057;
    border-bottom-color: rgba(13, 110, 253, 0.2);
}

/* Nested Accordion Styles */
.nested-accordion-item {
    background-color: #ffffff;
    border-radius: 8px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    margin-bottom: 12px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.nested-accordion-btn {
    border-radius: 8px !important;
    padding: 0.75rem 1.25rem;
}

.nested-accordion-btn:not(.collapsed) {
    background-color: rgba(13, 110, 253, 0.05);
    color: #0d6efd;
}

.nested-accordion-body {
    padding: 1.25rem;
    border-radius: 0 0 8px 8px;
}

/* Detail Card Styles */
.detail-card {
    background-color: transparent;
}

.detail-header {
    border-radius: 8px 8px 0 0 !important;
}

.detail-title {
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.time-value {
    font-size: 0.85em;
}

/* Action Buttons */
.action-buttons-bottom {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.complete-btn {
    transition: all 0.3s ease;
    min-width: 180px;
}

.complete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.25);
}

/* Empty State Styles */
.empty-state {
    animation: fadeIn 0.5s ease;
}

.empty-title {
    font-size: 1.25rem;
    font-weight: 500;
}

.empty-message {
    max-width: 400px;
    margin: 0 auto;
}

.empty-action {
    transition: all 0.3s ease;
    padding: 0.5rem 1.5rem;
}

.empty-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .header-icon {
        margin-bottom: 1rem;
    }

    .accordion-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .action-buttons {
        margin-top: 1rem;
        width: 100%;
        justify-content: flex-end;
    }

    .detail-col {
        width: 100%;
    }

    .detail-row {
        flex-direction: column;
    }
}

@media (max-width: 576px) {
    .main-title {
        font-size: 1.5rem;
    }

    .nav-tabs-custom .nav-link {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .complete-btn {
        width: 100%;
    }
}
</style>

<style>
.informasi-penting {
    margin: 1.5rem 0;
    animation: fadeIn 0.5s ease;
}

.info-container {
    background-color: rgba(13, 110, 253, 0.08);
    border-left: 4px solid #0d6efd;
    border-radius: 0 8px 8px 0;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    transition: all 0.3s ease;
}

.info-container:hover {
    background-color: rgba(13, 110, 253, 0.12);
    transform: translateX(3px);
}

.info-icon {
    color: #0d6efd;
    font-size: 1.5rem;
    margin-right: 1rem;
    margin-top: 0.2rem;
}

.info-content {
    flex: 1;
}

.info-title {
    color: #0d6efd;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.info-text {
    color: #495057;
    line-height: 1.7;
    margin-bottom: 0.75rem;
}

.info-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
    }
}

@media (max-width: 768px) {
    .info-container {
        flex-direction: column;
    }

    .info-icon {
        margin-bottom: 0.5rem;
    }
}

.badge {
    transition: all 0.2s ease;
}

.badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}
</style>

<style>
:root {
    --warna-primer: #4361ee;
    --warna-sekunder: #3f37c9;
    --warna-sukses: #4cc9f0;
    --warna-info: #4895ef;
    --warna-peringatan: #f72585;
    --warna-bahaya: #b5179e;
    --warna-latar: #f8f9fa;
    --warna-gelap: #212529;
    --warna-teks-primer: #2b2d42;
    --warna-teks-sekunder: #8d99ae;
    --radius-border: 12px;
    --bayangan: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transisi: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.header-kalkulator {
    text-align: center;
    margin-bottom: 1rem;
    padding-bottom: 1.5rem;
}

.judul-kalkulator {
    font-size: 2.2rem;
    font-weight: 700;
    color: #3f5189;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.deskripsi-kalkulator {
    font-size: 1.1rem;
    color: #35477b;
    max-width: 700px;
    margin: 0 auto;
}

.isi-dokumentasi {
    margin-bottom: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

/* Base Styles */
.calculation-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    color: #333;
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* Section Headers */
.section-header {
    margin-bottom: 1.5rem;
}

.section-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.section-badge i {
    margin-right: 0.5rem;
}

.formula-badge {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    border-left: 4px solid #0d6efd;
}

.result-badge {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
    border-left: 4px solid #198754;
}

.section-description {
    font-size: 0.9rem;
    color: #6c757d;
    margin-left: 0.25rem;
}

/* Parameter Table */
.parameter-table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.responsive-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.parameter-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.parameter-table th {
    background-color: #f8f9fa;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
}

.parameter-table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.parameter-index {
    font-weight: 500;
    color: #6c757d;
    width: 50px;
}

.parameter-name {
    font-weight: 500;
    min-width: 200px;
}

.parameter-unit {
    color: #6c757d;
    font-size: 0.85em;
    margin-left: 0.25rem;
}

.parameter-input-cell {
    min-width: 200px;
}

.input-group {
    display: flex;
    align-items: stretch;
}

.parameter-input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px 0 0 4px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.parameter-input:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.unit-display {
    background-color: #e9ecef;
    border: 1px solid #ced4da;
    border-left: 0;
    padding: 0.5rem 0.75rem;
    border-radius: 0 4px 4px 0;
    color: #495057;
}

/* Results Section */
.results-container {
    display: grid;
    gap: 1rem;
}

.result-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    border-left: 4px solid #198754;
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.result-title {
    font-weight: 600;
    color: #212529;
    display: flex;
    align-items: center;
}

.result-title i {
    color: #198754;
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

.result-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #198754;
    background-color: rgba(25, 135, 84, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    min-width: 80px;
    text-align: center;
}

.result-notes {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.notes-header {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.notes-header i {
    margin-right: 0.5rem;
}

.notes-content {
    font-size: 0.9rem;
    color: #495057;
    line-height: 1.5;
}

.result-footer {
    display: flex;
    justify-content: flex-end;
}

.calculation-method {
    font-size: 0.8rem;
    color: #6c757d;
}

.method-label {
    font-weight: 500;
    margin-right: 0.25rem;
}

/* Highlight Effect */
.parameter-row.highlighted {
    background-color: rgba(13, 110, 253, 0.05);
    transition: background-color 0.3s ease;
}

/* Responsive Layout */
@media (min-width: 992px) {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 1200px) {
    .calculation-container {
        grid-template-columns: 2fr 1fr;
    }
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.result-card {
    animation: fadeIn 0.3s ease-out forwards;
}

/* Print Styles */
@media print {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }

    .parameter-table-container,
    .result-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* animasi skeleton */
@keyframes pulseSkeleton {
    0% {
        background-color: #e0e0e0;
    }
    50% {
        background-color: #f0f0f0;
    }
    100% {
        background-color: #e0e0e0;
    }
}

.skeleton {
    animation: pulseSkeleton 1.5s infinite;
    border-radius: 8px;
}
.skeleton-image {
    width: 100%;
    height: 200px;
    margin-bottom: 16px;
}

.analysis-container {
    background: rgba(255, 255, 255, 0.98);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.analysis-container:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
}

.section-title {
    font-weight: 800;
    font-size: 1.25rem;
    position: relative;
    padding-bottom: 16px;
    margin-bottom: 24px;
    color: #495057; /* Updated to use #495057 */
    letter-spacing: -0.5px;
}

.section-title::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    width: 80px;
    height: 5px;
    background: #495057;
    border-radius: 5px;
    box-shadow: 0 2px 8px rgba(19, 24, 50, 0.3);
}

.text-gradient {
    background: #495057;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* Base Styles */
.cleaning-system-container {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8fafc;
    min-height: 100vh;
    color: #334155;
}

.system-header {
    background: linear-gradient(135deg, #456290 0%, #25335e 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header-content {
    flex: 1;
}

.system-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.system-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0.25rem 0 0;
    font-weight: 400;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.btn-help {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-help:hover {
    background: rgba(255, 255, 255, 0.2);
}

.content-wrapper {
    max-width: 100%;
    margin: 2rem auto;
    padding: 0 2rem;
}

/* Panel Styles */
.search-panel,
.details-panel,
.template-panel,
.form-panel {
    background: white;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.panel-header {
    padding: 1.25rem 1.5rem;
    background-color: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header.with-tabs {
    border-bottom: none;
}

.panel-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.panel-body {
    padding: 1.5rem;
}

/* Search Form */
.search-form {
    max-width: 600px;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #475569;
}

.input-with-button {
    display: flex;
    gap: 0.5rem;
}

.form-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
}

.btn-search {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-search:hover {
    background-color: #2563eb;
}

/* Detail Grid */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-label {
    font-weight: 500;
    color: #64748b;
}

.detail-value {
    font-weight: 500;
    color: #1e293b;
}

.detail-value.highlight {
    color: #3b82f6;
    font-weight: 600;
}

.status-badge {
    display: flex;
    gap: 0.5rem;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge.active {
    background-color: #d1fae5;
    color: #065f46;
}

.badge.priority {
    background-color: #3eb1df;
    color: #ffffff;
}

/* Notes Section */
.notes-section {
    background-color: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1.5rem;
    border-left: 4px solid #60a5fa;
}

.notes-header {
    margin-bottom: 0.5rem;
}

.notes-label {
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notes-content {
    color: #475569;
    line-height: 1.5;
}

/* Form Panels */
.form-panel .panel-header {
    background-color: #f8fafc;
}

.btn-add {
    background-color: #10b981;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-add:hover {
    background-color: #059669;
}

.btn-add-param {
    background-color: #f59e0b;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    margin-right: 0.5rem;
}

.btn-add-param:hover {
    background-color: #d97706;
}

/* Multi Table */
.multi-table {
    overflow-x: auto;
}

.multi-table table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.multi-table th {
    background-color: #f1f5f9;
    color: #475569;
    font-weight: 600;
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.multi-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.multi-table input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.multi-table input:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}

.multi-table td.actions {
    text-align: center;
}
/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f1f5f9;
}

.btn-submit {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-submit:hover {
    background-color: #2563eb;
}

.btn-save {
    background-color: #8b5cf6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-save:hover {
    background-color: #7c3aed;
}

.modern-form {
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    overflow: hidden;

    margin: 0 auto;
}

.sample-info-card {
    background: #f8f9ff;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    gap: 32px;
    border: 1px solid #e0e7ff;
}

.info-item {
    display: flex;
    align-items: center;
}

.info-label {
    font-weight: 500;
    color: #4b5563;
    margin-right: 8px;
}

.info-value {
    font-weight: 600;
    color: #1e293b;
}

/* Modern Table Styles */
.analysis-table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.modern-analysis-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

/* Search Bar */
.search-wrapper .search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
}
.custom-search {
    height: 48px;
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}
.custom-search:focus {
    background-color: #fff;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
}

/* Card Styling */
.hover-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.02);
}
.hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.08) !important;
}
.status-strip {
    width: 5px;
    min-height: 100%;
}
.icon-box {
    width: 50px;
    height: 50px;
}

/* Pagination Custom */
.page-link {
    color: #6c757d;
    min-width: 32px;
    text-align: center;
    margin: 0 2px;
}
.page-item.active .page-link {
    background-color: #0d6efd;
    color: white;
}
.page-item.disabled .page-link {
    background-color: transparent;
    opacity: 0.5;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .status-strip {
        width: 100%;
        height: 4px;
        position: absolute;
        top: 0;
    }
}
</style>
