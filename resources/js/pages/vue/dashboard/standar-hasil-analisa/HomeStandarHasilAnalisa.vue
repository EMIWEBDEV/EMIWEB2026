<template>
    <div class="sha-page">
        <!-- Page Header -->
        <div class="sha-page-header">
            <div class="sha-page-header-left">
                <div class="sha-page-icon">
                    <i class="ri-scales-3-line"></i>
                </div>
                <div>
                    <h4 class="sha-page-title">Kriteria Kelayakan Analisa</h4>
                    <p class="sha-page-subtitle">Standar rentang hasil analisa PT. Evo Manufacturing Indonesia</p>
                </div>
            </div>
            <div class="sha-header-actions">
                <div class="sha-total-chip" v-if="!loading.loadingRumusPerhitungan">
                    <i class="ri-database-2-line"></i>
                    <span>Total:</span>
                    <strong>{{ pagination.totalData }}</strong>
                    <span>kriteria</span>
                </div>
                <a href="/standar-hasil-analisa/tambah" class="sha-add-btn">
                    <i class="ri-add-line"></i>
                    <span>Tambah Data</span>
                </a>
            </div>
        </div>

        <!-- Search Card -->
        <div class="sha-search-card">
            <div class="sha-search-wrap">
                <i class="ri-search-line sha-search-icon"></i>
                <input
                    type="search"
                    class="sha-search-input"
                    placeholder="Cari kode barang, nama barang, mesin..."
                    v-model="searchQuery"
                />
                <button v-if="searchQuery" class="sha-search-clear" @click="searchQuery = ''">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="sha-search-hints" v-if="!loading.loadingRumusPerhitungan && groupedData.length">
                <span class="sha-hint-chip sha-hint-ok">
                    <i class="ri-checkbox-circle-line"></i>
                    {{ totalLayak }} Layak
                </span>
                <span class="sha-hint-chip sha-hint-fail">
                    <i class="ri-close-circle-line"></i>
                    {{ totalTidakLayak }} Tidak Layak
                </span>
                <span class="sha-hint-chip sha-hint-group">
                    <i class="ri-folder-2-line"></i>
                    {{ groupedData.length }} Jenis Analisa
                </span>
            </div>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading.loadingRumusPerhitungan" class="sha-accordion-list">
            <div class="sha-accordion-item" v-for="n in 5" :key="n">
                <div class="sha-accordion-header sha-skeleton-header">
                    <div class="sha-sk-circle"></div>
                    <div class="sha-sk-line w-40"></div>
                    <div style="flex:1"></div>
                    <div class="sha-sk-badge"></div>
                    <div class="sha-sk-badge"></div>
                </div>
            </div>
        </div>

        <!-- Accordion Groups -->
        <div v-else class="sha-accordion-list">
            <div
                v-for="(group, gi) in groupedData"
                :key="gi"
                class="sha-accordion-item"
                :class="{ 'is-open': openGroups.includes(gi) }"
            >
                <!-- Accordion Header -->
                <div class="sha-accordion-header" @click="toggleGroup(gi)">
                    <div class="sha-group-icon" :class="'sha-icon-color-' + (gi % 8)">
                        <i class="ri-test-tube-2-line"></i>
                    </div>
                    <div class="sha-group-meta">
                        <div class="sha-group-name">{{ group.name }}</div>
                        <div class="sha-group-sub">{{ group.items.length }} kriteria terdaftar</div>
                    </div>
                    <div class="sha-group-stats">
                        <span v-if="group.layakCount > 0" class="sha-stat-pill sha-stat-ok">
                            <i class="ri-checkbox-circle-line"></i>
                            {{ group.layakCount }} Layak
                        </span>
                        <span v-if="group.tidakLayakCount > 0" class="sha-stat-pill sha-stat-fail">
                            <i class="ri-close-circle-line"></i>
                            {{ group.tidakLayakCount }} Tidak Layak
                        </span>
                    </div>
                    <div class="sha-accordion-chevron">
                        <i class="ri-arrow-down-s-line"></i>
                    </div>
                </div>

                <!-- Accordion Body -->
                <div class="sha-accordion-body" v-show="openGroups.includes(gi)">
                    <div class="sha-item-list">
                        <div
                            v-for="(item, ii) in group.items"
                            :key="ii"
                            class="sha-item-card"
                            :class="item.Flag_Layak === 'Y' ? 'sha-item-ok' : 'sha-item-fail'"
                        >
                            <!-- Left: Tipe + Barang -->
                            <div class="sha-item-section sha-item-barang">
                                <span class="sha-tipe-badge"
                                    :class="item.Status_Analisa === 'Perhitungan' ? 'sha-tipe-hitung' : 'sha-tipe-non'">
                                    {{ item.Status_Analisa ?? '-' }}
                                </span>
                                <div class="sha-item-barang-name">{{ item.Nama_Barang ?? '-' }}</div>
                                <div class="sha-item-kode" v-if="item.Kode_Barang && item.Kode_Barang !== '-'">
                                    <i class="ri-barcode-line me-1"></i>{{ item.Kode_Barang }}
                                </div>
                            </div>

                            <!-- Middle: Mesin + Kolom -->
                            <div class="sha-item-section sha-item-detail">
                                <div class="sha-detail-row" v-if="item.Nama_Mesin && item.Nama_Mesin !== '-'">
                                    <i class="ri-settings-3-line sha-detail-icon"></i>
                                    <span>{{ item.Nama_Mesin }}</span>
                                </div>
                                <div class="sha-detail-row" v-if="item.Nama_Kolom && item.Nama_Kolom !== '-' && item.Nama_Kolom !== 0">
                                    <i class="ri-function-line sha-detail-icon sha-detail-icon-purple"></i>
                                    <span class="sha-kolom-text">{{ item.Nama_Kolom }}</span>
                                </div>
                                <div class="sha-detail-row" v-if="(!item.Nama_Mesin || item.Nama_Mesin === '-') && (!item.Nama_Kolom || item.Nama_Kolom === '-' || item.Nama_Kolom === 0)">
                                    <span class="sha-no-detail">— tidak ada detail mesin/kolom —</span>
                                </div>
                            </div>

                            <!-- Right: Range + Status -->
                            <div class="sha-item-section sha-item-range-status">
                                <div class="sha-range-block">
                                    <div class="sha-range-label">Rentang Nilai</div>
                                    <div class="sha-range-row">
                                        <span class="sha-range-chip sha-range-min">
                                            <i class="ri-arrow-down-s-line"></i>
                                            {{ item.Range_Awal ?? '-' }}
                                        </span>
                                        <span class="sha-range-dash">—</span>
                                        <span class="sha-range-chip sha-range-max">
                                            <i class="ri-arrow-up-s-line"></i>
                                            {{ item.Range_Akhir ?? '-' }}
                                        </span>
                                    </div>
                                    <div class="sha-range-bar-wrap">
                                        <div class="sha-range-bar"
                                            :class="item.Flag_Layak === 'Y' ? 'sha-bar-ok' : 'sha-bar-fail'">
                                        </div>
                                    </div>
                                </div>
                                <div class="sha-item-status">
                                    <span v-if="item.Flag_Layak === 'Y'" class="sha-status-badge sha-status-layak">
                                        <i class="ri-checkbox-circle-fill"></i>
                                        Layak
                                    </span>
                                    <span v-else class="sha-status-badge sha-status-tidak">
                                        <i class="ri-close-circle-fill"></i>
                                        Tidak Layak
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!groupedData.length" class="sha-empty">
                <DotLottieVue
                    style="height: 120px; width: 120px"
                    autoplay
                    loop
                    src="/animation/empty2.json"
                />
                <div class="sha-empty-title">Data Tidak Ditemukan</div>
                <div class="sha-empty-sub" v-if="searchQuery">
                    Tidak ada hasil untuk "<strong>{{ searchQuery }}</strong>"
                </div>
                <div class="sha-empty-sub" v-else>Belum ada kriteria kelayakan yang terdaftar</div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="!loading.loadingRumusPerhitungan && pagination.totalPage > 1" class="sha-pagination">
            <div class="sha-pagination-info">
                Menampilkan halaman <strong>{{ pagination.page }}</strong> dari <strong>{{ pagination.totalPage }}</strong>
                &nbsp;·&nbsp; Total <strong>{{ pagination.totalData }}</strong> data
            </div>
            <div class="sha-pagination-controls">
                <button class="sha-page-btn" :disabled="pagination.page === 1" @click="prevPage">
                    <i class="ri-arrow-left-s-line"></i>
                </button>
                <button
                    v-for="page in visiblePages"
                    :key="page"
                    class="sha-page-btn"
                    :class="{ active: page === pagination.page }"
                    @click="changePage(page)"
                >{{ page }}</button>
                <button class="sha-page-btn" :disabled="pagination.page === pagination.totalPage" @click="nextPage">
                    <i class="ri-arrow-right-s-line"></i>
                </button>
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
            detailRumusPerhitungan: [],
            openGroups: [],
            loading: {
                loadingRumusPerhitungan: false,
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
        groupedData() {
            const groups = {};
            this.detailRumusPerhitungan.forEach((item) => {
                const key = item.Jenis_Analisa ?? '-';
                if (!groups[key]) {
                    groups[key] = { name: key, items: [], layakCount: 0, tidakLayakCount: 0 };
                }
                groups[key].items.push(item);
                if (item.Flag_Layak === 'Y') groups[key].layakCount++;
                else groups[key].tidakLayakCount++;
            });
            return Object.values(groups);
        },
        totalLayak() {
            return this.detailRumusPerhitungan.filter(i => i.Flag_Layak === 'Y').length;
        },
        totalTidakLayak() {
            return this.detailRumusPerhitungan.filter(i => i.Flag_Layak !== 'Y').length;
        },
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = Math.max(1, current - 2);
            let end = Math.min(total, start + 4);
            if (end - start < 4) start = Math.max(1, end - 4);
            const pages = [];
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
    },
    methods: {
        toggleGroup(gi) {
            const idx = this.openGroups.indexOf(gi);
            if (idx === -1) this.openGroups.push(gi);
            else this.openGroups.splice(idx, 1);
        },
        expandAll() {
            this.openGroups = this.groupedData.map((_, i) => i);
        },

        async fetchDetailRumusPerhitungan(page = 1) {
            this.loading.loadingRumusPerhitungan = true;
            this.openGroups = [];
            this.pagination.page = page;

            const params = { page: this.pagination.page, limit: this.pagination.limit };
            if (this.searchQuery) params.search = this.searchQuery;

            try {
                const response = await axios.get(
                    `/api/v1/standar-rentang-analisa/current`,
                    { params, withCredentials: true }
                );

                if (response.status === 200 && response.data?.result) {
                    this.detailRumusPerhitungan = response.data.result;
                    this.pagination.totalPage = response.data.total_page;
                    this.pagination.totalData = response.data.total_data;
                    // Auto-expand first group
                    if (this.groupedData.length > 0) this.openGroups = [0];
                } else {
                    this.detailRumusPerhitungan = [];
                    this.pagination.totalPage = 0;
                    this.pagination.totalData = 0;
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                this.detailRumusPerhitungan = [];
                if (error.response?.status === 404) {
                    this.pagination.totalPage = 0;
                    this.pagination.totalData = 0;
                }
            } finally {
                this.loading.loadingRumusPerhitungan = false;
            }
        },

        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchDetailRumusPerhitungan(1);
        }, 500),

        nextPage() {
            if (this.pagination.page < this.pagination.totalPage)
                this.fetchDetailRumusPerhitungan(this.pagination.page + 1);
        },
        prevPage() {
            if (this.pagination.page > 1)
                this.fetchDetailRumusPerhitungan(this.pagination.page - 1);
        },
        changePage(page) {
            if (page !== this.pagination.page)
                this.fetchDetailRumusPerhitungan(page);
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        this.fetchDetailRumusPerhitungan();
    },
};
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.sha-page {
    font-family: "Inter", "Segoe UI", sans-serif;
    color: #343a40;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* ── Page Header ─────────────────────────────────────────── */
.sha-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 20px 24px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(64, 81, 137, 0.08);
    border: 1px solid #e9ecef;
}

.sha-page-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.sha-page-icon {
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

.sha-page-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin: 0 0 3px;
}

.sha-page-subtitle {
    font-size: 12px;
    color: #878a99;
    margin: 0;
}

.sha-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.sha-total-chip {
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

.sha-total-chip strong {
    color: #405189;
    font-size: 15px;
}

.sha-add-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    background: #405189;
    color: #fff;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.2s, transform 0.15s;
}

.sha-add-btn:hover {
    background: #35457b;
    color: #fff;
    transform: translateY(-1px);
}

/* ── Search Card ─────────────────────────────────────────── */
.sha-search-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 14px 18px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}

.sha-search-wrap {
    position: relative;
    width: 340px;
}

.sha-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #adb5bd;
    font-size: 15px;
    pointer-events: none;
}

.sha-search-input {
    width: 100%;
    padding: 9px 36px;
    border: 1px solid #dee2e6;
    border-radius: 9px;
    font-size: 13px;
    color: #343a40;
    background: #fff;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.sha-search-input:focus {
    border-color: #405189;
    box-shadow: 0 0 0 3px rgba(64, 81, 137, 0.1);
}

.sha-search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #adb5bd;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
}

.sha-search-hints {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.sha-hint-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.sha-hint-ok    { background: rgba(10,179,156,.1);  color: #0ab39c; border: 1px solid rgba(10,179,156,.2); }
.sha-hint-fail  { background: rgba(240,101,72,.1);  color: #f06548; border: 1px solid rgba(240,101,72,.2); }
.sha-hint-group { background: rgba(64,81,137,.08);  color: #405189; border: 1px solid rgba(64,81,137,.15); }

/* ── Accordion List ──────────────────────────────────────── */
.sha-accordion-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* ── Accordion Item ──────────────────────────────────────── */
.sha-accordion-item {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    transition: box-shadow 0.2s, border-color 0.2s;
}

.sha-accordion-item.is-open {
    border-color: rgba(64, 81, 137, 0.25);
    box-shadow: 0 3px 12px rgba(64, 81, 137, 0.08);
}

/* ── Accordion Header ────────────────────────────────────── */
.sha-accordion-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s;
}

.sha-accordion-header:hover {
    background: #f8f9fc;
}

.sha-group-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #fff;
    flex-shrink: 0;
}

.sha-icon-color-0 { background: #405189; }
.sha-icon-color-1 { background: #0ab39c; }
.sha-icon-color-2 { background: #f06548; }
.sha-icon-color-3 { background: #f7b84b; }
.sha-icon-color-4 { background: #4b93f7; }
.sha-icon-color-5 { background: #6f42c1; }
.sha-icon-color-6 { background: #e83e8c; }
.sha-icon-color-7 { background: #20c997; }

.sha-group-meta {
    flex: 1;
    min-width: 0;
}

.sha-group-name {
    font-size: 14px;
    font-weight: 700;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sha-group-sub {
    font-size: 11px;
    color: #878a99;
    margin-top: 2px;
}

.sha-group-stats {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.sha-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.sha-stat-ok   { background: rgba(10,179,156,.1);  color: #0ab39c; }
.sha-stat-fail { background: rgba(240,101,72,.1);  color: #f06548; }

.sha-accordion-chevron {
    color: #adb5bd;
    font-size: 22px;
    flex-shrink: 0;
    transition: transform 0.25s;
}

.sha-accordion-item.is-open .sha-accordion-chevron {
    transform: rotate(180deg);
}

/* ── Accordion Body ──────────────────────────────────────── */
.sha-accordion-body {
    border-top: 1px solid #f0f2f5;
    padding: 12px 16px;
    background: #fafbff;
}

/* ── Item List ───────────────────────────────────────────── */
.sha-item-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* ── Item Card ───────────────────────────────────────────── */
.sha-item-card {
    display: grid;
    grid-template-columns: 2fr 1.5fr 1.5fr;
    gap: 0;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    transition: box-shadow 0.15s;
}

.sha-item-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.07);
}

.sha-item-ok   { border-left: 3px solid #0ab39c; }
.sha-item-fail { border-left: 3px solid #f06548; }

.sha-item-section {
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 5px;
}

.sha-item-section + .sha-item-section {
    border-left: 1px solid #f0f2f5;
}

/* ── Barang Section ──────────────────────────────────────── */
.sha-item-barang {}

.sha-tipe-badge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    width: fit-content;
}

.sha-tipe-hitung { background: rgba(64,81,137,.1);  color: #405189; }
.sha-tipe-non    { background: rgba(99,102,241,.1); color: #6366f1; }

.sha-item-barang-name {
    font-size: 13px;
    font-weight: 600;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sha-item-kode {
    display: flex;
    align-items: center;
    font-size: 11px;
    font-family: "Courier New", monospace;
    color: #6c757d;
    background: #f3f6f9;
    padding: 2px 7px;
    border-radius: 4px;
    width: fit-content;
}

/* ── Detail Section ──────────────────────────────────────── */
.sha-detail-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #495057;
}

.sha-detail-icon {
    font-size: 13px;
    color: #adb5bd;
    flex-shrink: 0;
}

.sha-detail-icon-purple {
    color: #6366f1;
}

.sha-kolom-text {
    font-weight: 600;
    color: #6366f1;
}

.sha-no-detail {
    font-size: 11px;
    color: #ced4da;
    font-style: italic;
}

/* ── Range + Status Section ──────────────────────────────── */
.sha-item-range-status {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.sha-range-block {
    flex: 1;
}

.sha-range-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #adb5bd;
    margin-bottom: 5px;
}

.sha-range-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 5px;
}

.sha-range-chip {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 700;
}

.sha-range-min { background: rgba(247,184,75,.15); color: #b07d00; }
.sha-range-max { background: rgba(64,81,137,.1);   color: #405189; }

.sha-range-dash {
    color: #dee2e6;
    font-size: 12px;
}

.sha-range-bar-wrap {
    width: 100%;
    height: 5px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.sha-range-bar {
    height: 100%;
    width: 100%;
    border-radius: 3px;
}

.sha-bar-ok   { background: linear-gradient(to right, #f7b84b, #0ab39c); }
.sha-bar-fail { background: linear-gradient(to right, #f06548, #ef4444); }

.sha-item-status {
    flex-shrink: 0;
}

.sha-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.sha-status-layak { background: rgba(10,179,156,.1); color: #0ab39c; }
.sha-status-tidak { background: rgba(240,101,72,.1); color: #f06548; }

/* ── Empty State ─────────────────────────────────────────── */
.sha-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 50px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px dashed #dee2e6;
}

.sha-empty-title {
    font-size: 15px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 6px;
}

.sha-empty-sub {
    font-size: 13px;
    color: #878a99;
}

/* ── Pagination ──────────────────────────────────────────── */
.sha-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.sha-pagination-info {
    font-size: 12px;
    color: #6c757d;
}

.sha-pagination-info strong { color: #343a40; }

.sha-pagination-controls {
    display: flex;
    align-items: center;
    gap: 4px;
}

.sha-page-btn {
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

.sha-page-btn:hover:not(:disabled):not(.active) {
    background: #f8f9fc;
    border-color: #405189;
    color: #405189;
}

.sha-page-btn.active {
    background: #405189;
    border-color: #405189;
    color: #fff;
    font-weight: 600;
}

.sha-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

/* ── Skeleton ────────────────────────────────────────────── */
@keyframes sha-shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}

.sha-sk-line, .sha-sk-circle, .sha-sk-badge {
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 800px 100%;
    animation: sha-shimmer 1.5s infinite linear;
    border-radius: 4px;
    height: 14px;
}

.sha-sk-circle {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    flex-shrink: 0;
}

.sha-sk-badge {
    width: 72px;
    height: 24px;
    border-radius: 20px;
}

.sha-sk-line.w-40 { width: 40%; }

.sha-skeleton-header { cursor: default; }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 900px) {
    .sha-item-card {
        grid-template-columns: 1fr 1fr;
    }

    .sha-item-range-status {
        grid-column: 1 / -1;
        flex-direction: row;
        border-top: 1px solid #f0f2f5;
        border-left: none !important;
    }
}

@media (max-width: 600px) {
    .sha-item-card {
        grid-template-columns: 1fr;
    }

    .sha-item-section + .sha-item-section {
        border-left: none;
        border-top: 1px solid #f0f2f5;
    }

    .sha-group-stats { display: none; }

    .sha-search-wrap { width: 100%; }

    .sha-page-header { flex-direction: column; align-items: flex-start; }
}
</style>
