<template>
    <div class="bad-page">
        <!-- Breadcrumb / Back -->
        <div class="bad-breadcrumb">
            <a href="/barang-jenis-analisa" class="bad-back-link">
                <i class="ri-arrow-left-s-line"></i>
                Kembali ke Daftar Jenis Analisa
            </a>
        </div>

        <!-- Page Header -->
        <div class="bad-page-header">
            <div class="bad-page-header-left">
                <div class="bad-page-icon">
                    <i class="ri-test-tube-2-line"></i>
                </div>
                <div>
                    <h4 class="bad-page-title">Detail Barang Analisa</h4>
                    <p class="bad-page-subtitle">Daftar barang yang terdaftar pada jenis analisa ini</p>
                </div>
            </div>
            <div class="bad-total-chip" v-if="!loading.loadingDataList">
                <i class="ri-database-2-line"></i>
                <span>Total:</span>
                <strong>{{ pagination.totalData }}</strong>
                <span>data</span>
            </div>
        </div>

        <!-- Search & Table Card -->
        <div class="bad-card">
            <!-- Search Bar -->
            <div class="bad-card-toolbar">
                <div class="bad-search-wrap">
                    <i class="ri-search-line bad-search-icon"></i>
                    <input
                        type="search"
                        class="bad-search-input"
                        placeholder="Cari kode barang, nama barang..."
                        v-model="searchQuery"
                        @input="handleSearch"
                    />
                    <button
                        v-if="searchQuery"
                        class="bad-search-clear"
                        @click="searchQuery = ''; debouncedSearch()"
                    >
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            </div>

            <!-- Loading Skeleton -->
            <div v-if="loading.loadingDataList" class="bad-table-wrap">
                <table class="bad-table">
                    <thead>
                        <tr>
                            <th style="width: 50px">#</th>
                            <th>Kode Analisa</th>
                            <th>Jenis Analisa</th>
                            <th>Kode Barang</th>
                            <th>Nama Barang</th>
                            <th>Nama Mesin</th>
                            <th>Pengguna</th>
                            <th style="width: 90px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="n in 8" :key="n">
                            <td><div class="bad-sk-line" style="width: 24px"></div></td>
                            <td><div class="bad-sk-line" style="width: 80px"></div></td>
                            <td><div class="bad-sk-line" style="width: 120px"></div></td>
                            <td><div class="bad-sk-line" style="width: 90px"></div></td>
                            <td><div class="bad-sk-line" style="width: 150px"></div></td>
                            <td><div class="bad-sk-line" style="width: 100px"></div></td>
                            <td><div class="bad-sk-line" style="width: 80px"></div></td>
                            <td><div class="bad-sk-line" style="width: 60px; border-radius: 10px"></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Data Table -->
            <div v-else>
                <div v-if="detailDataList.length" class="bad-table-wrap">
                    <table class="bad-table">
                        <thead>
                            <tr>
                                <th style="width: 50px">#</th>
                                <th>Kode Analisa</th>
                                <th>Jenis Analisa</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Nama Mesin</th>
                                <th>Pengguna</th>
                                <th style="width: 90px; text-align: center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in detailDataList" :key="index">
                                <td class="bad-cell-no">
                                    {{ (pagination.page - 1) * pagination.limit + index + 1 }}
                                </td>
                                <td>
                                    <span class="bad-code-badge">{{ item.kode_analisa ?? '-' }}</span>
                                </td>
                                <td class="bad-cell-analisa">{{ item.jenis_analisa ?? '-' }}</td>
                                <td>
                                    <span class="bad-kode-text">{{ item.kode_barang ?? '-' }}</span>
                                </td>
                                <td class="bad-cell-name">{{ item.nama_barang ?? '-' }}</td>
                                <td>
                                    <span class="bad-mesin-text">
                                        <i class="ri-settings-3-line me-1"></i>
                                        {{ item.nama_mesin ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="bad-user-badge">
                                        <i class="ri-user-3-line me-1"></i>
                                        {{ item.Id_User ?? '-' }}
                                    </span>
                                </td>
                                <td style="text-align: center">
                                    <span v-if="item.Flag_Aktif === 'Y'" class="bad-status-badge bad-status-active">
                                        <i class="ri-checkbox-circle-line me-1"></i>Aktif
                                    </span>
                                    <span v-else class="bad-status-badge bad-status-inactive">
                                        <i class="ri-close-circle-line me-1"></i>Nonaktif
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="!detailDataList.length" class="bad-empty">
                    <DotLottieVue
                        style="height: 120px; width: 120px"
                        autoplay
                        loop
                        src="/animation/empty2.json"
                    />
                    <div class="bad-empty-title">Data Tidak Ditemukan</div>
                    <div class="bad-empty-sub" v-if="searchQuery">
                        Tidak ada hasil untuk "<strong>{{ searchQuery }}</strong>"
                    </div>
                    <div class="bad-empty-sub" v-else>Belum ada barang yang terdaftar</div>
                </div>

                <!-- Pagination -->
                <div v-if="detailDataList.length" class="bad-pagination">
                    <div class="bad-pagination-info">
                        Menampilkan
                        <strong>{{ (pagination.page - 1) * pagination.limit + 1 }}</strong>
                        –
                        <strong>{{ Math.min(pagination.page * pagination.limit, pagination.totalData) }}</strong>
                        dari <strong>{{ pagination.totalData }}</strong> data
                    </div>
                    <div class="bad-pagination-controls">
                        <button
                            class="bad-page-btn"
                            :disabled="pagination.page === 1"
                            @click="prevPage"
                        >
                            <i class="ri-arrow-left-s-line"></i>
                        </button>
                        <button
                            v-for="page in visiblePages"
                            :key="page"
                            class="bad-page-btn"
                            :class="{ active: page === pagination.page }"
                            @click="changePage(page)"
                        >
                            {{ page }}
                        </button>
                        <button
                            class="bad-page-btn"
                            :disabled="pagination.page === pagination.totalPage"
                            @click="nextPage"
                        >
                            <i class="ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import { debounce } from "lodash";

export default {
    components: {
        DotLottieVue,
    },
    props: {
        id: {
            type: [String, Number],
            required: true,
        },
        item: Object,
        index: Number,
    },
    data() {
        return {
            searchQuery: "",
            detailDataList: [],
            loading: {
                loadingDataList: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
        };
    },
    computed: {
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = current - 2;
            let end = current + 2;

            if (start < 1) {
                start = 1;
                end = Math.min(5, total);
            }

            if (end > total) {
                end = total;
                start = Math.max(1, total - 4);
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },
    },
    methods: {
        async fetchDetailBindingIdentity(page = 1, query = "") {
            this.loading.loadingDataList = true;
            try {
                if (query) {
                    const idJenisAnalisa = this.id;
                    const response = await axios.get(
                        `/api/v1/barang-jenis-analisa/${idJenisAnalisa}/search`,
                        {
                            params: { q: query },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailDataList = response.data.result;
                        this.pagination.totalPage = 1;
                        this.pagination.totalData = this.detailDataList.length;
                    } else {
                        this.detailDataList = [];
                    }
                } else {
                    const idJenisAnalisa = this.id;
                    const response = await axios.get(
                        `/api/v1/detail/barang-jenis-analisa/${idJenisAnalisa}`,
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
                            },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailDataList = response.data.result;
                        this.pagination.page = page;
                        this.pagination.totalPage = response.data.total_page;
                        this.pagination.totalData = response.data.total_data;
                    } else {
                        this.detailDataList = [];
                    }
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchDetailBindingIdentity(
                this.pagination.page,
                this.searchQuery
            );
        }, 500),
        handleSearch() {
            this.debouncedSearch();
        },
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchDetailBindingIdentity(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchDetailBindingIdentity(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchDetailBindingIdentity(page, this.searchQuery);
            }
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        this.fetchDetailBindingIdentity();
    },
};
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.bad-page {
    font-family: "Inter", "Segoe UI", sans-serif;
    color: #343a40;
}

/* ── Breadcrumb ──────────────────────────────────────────── */
.bad-breadcrumb {
    margin-bottom: 14px;
}

.bad-back-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    color: #6c757d;
    text-decoration: none;
    transition: color 0.2s;
}

.bad-back-link:hover {
    color: #405189;
}

/* ── Page Header ─────────────────────────────────────────── */
.bad-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
    padding: 20px 24px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(64, 81, 137, 0.08);
    border: 1px solid #e9ecef;
}

.bad-page-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.bad-page-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: rgba(64, 81, 137, 0.1);
    color: #405189;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.bad-page-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin: 0 0 3px;
}

.bad-page-subtitle {
    font-size: 12px;
    color: #878a99;
    margin: 0;
}

.bad-total-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    background: rgba(64, 81, 137, 0.08);
    border-radius: 20px;
    font-size: 13px;
    color: #495057;
    border: 1px solid rgba(64, 81, 137, 0.15);
}

.bad-total-chip strong {
    color: #405189;
    font-size: 15px;
}

/* ── Card ────────────────────────────────────────────────── */
.bad-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 4px rgba(64, 81, 137, 0.06);
    overflow: hidden;
}

/* ── Toolbar ─────────────────────────────────────────────── */
.bad-card-toolbar {
    padding: 16px 20px;
    border-bottom: 1px solid #f0f2f5;
    background: #fcfcfd;
}

.bad-search-wrap {
    position: relative;
    max-width: 360px;
}

.bad-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #adb5bd;
    font-size: 15px;
    pointer-events: none;
}

.bad-search-input {
    width: 100%;
    padding: 9px 36px;
    border: 1px solid #dee2e6;
    border-radius: 9px;
    font-size: 13px;
    color: #343a40;
    background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
}

.bad-search-input:focus {
    border-color: #405189;
    box-shadow: 0 0 0 3px rgba(64, 81, 137, 0.1);
}

.bad-search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #adb5bd;
    cursor: pointer;
    padding: 2px;
    font-size: 14px;
    display: flex;
    align-items: center;
}

.bad-search-clear:hover {
    color: #6c757d;
}

/* ── Table ───────────────────────────────────────────────── */
.bad-table-wrap {
    overflow-x: auto;
}

.bad-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    white-space: nowrap;
}

.bad-table thead tr {
    background: #f8f9fc;
    border-bottom: 2px solid #e9ecef;
}

.bad-table thead th {
    padding: 11px 16px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}

.bad-table tbody tr {
    border-bottom: 1px solid #f0f2f5;
    transition: background 0.15s;
}

.bad-table tbody tr:hover {
    background: #f8f9fc;
}

.bad-table tbody tr:last-child {
    border-bottom: none;
}

.bad-table tbody td {
    padding: 12px 16px;
    color: #343a40;
}

.bad-cell-no {
    color: #878a99 !important;
    font-weight: 500;
    text-align: center;
}

.bad-code-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    background: rgba(64, 81, 137, 0.1);
    color: #405189;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.4px;
}

.bad-cell-analisa {
    font-weight: 500;
    color: #212529;
}

.bad-kode-text {
    font-family: "Courier New", monospace;
    font-size: 12px;
    color: #495057;
}

.bad-cell-name {
    font-weight: 500;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.bad-mesin-text {
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    color: #6c757d;
}

.bad-user-badge {
    display: inline-flex;
    align-items: center;
    font-size: 12px;
    color: #6c757d;
    background: #f3f6f9;
    padding: 3px 9px;
    border-radius: 20px;
    border: 1px solid #e9ecef;
}

.bad-status-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 9px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
}

.bad-status-active {
    background: rgba(10, 179, 156, 0.1);
    color: #0ab39c;
}

.bad-status-inactive {
    background: rgba(240, 101, 72, 0.1);
    color: #f06548;
}

/* ── Empty State ─────────────────────────────────────────── */
.bad-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 50px 20px;
}

.bad-empty-title {
    font-size: 15px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 6px;
}

.bad-empty-sub {
    font-size: 13px;
    color: #878a99;
}

/* ── Pagination ──────────────────────────────────────────── */
.bad-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #f0f2f5;
    background: #fcfcfd;
}

.bad-pagination-info {
    font-size: 12px;
    color: #6c757d;
}

.bad-pagination-info strong {
    color: #343a40;
}

.bad-pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.bad-page-btn {
    min-width: 34px;
    height: 34px;
    padding: 0 10px;
    border: 1px solid #dee2e6;
    border-radius: 7px;
    background: #fff;
    font-size: 13px;
    color: #495057;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.bad-page-btn:hover:not(:disabled):not(.active) {
    background: #f8f9fc;
    border-color: #405189;
    color: #405189;
}

.bad-page-btn.active {
    background: #405189;
    border-color: #405189;
    color: #fff;
    font-weight: 600;
}

.bad-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ── Skeleton ────────────────────────────────────────────── */
@keyframes bad-shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}

.bad-sk-line {
    display: inline-block;
    height: 14px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 800px 100%;
    animation: bad-shimmer 1.5s infinite linear;
    border-radius: 4px;
}
</style>
