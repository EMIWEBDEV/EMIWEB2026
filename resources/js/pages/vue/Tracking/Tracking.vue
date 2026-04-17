<template>
    <div class="tracking-page">
        <div
            class="global-progress-bar"
            :class="{ 'is-loading': isBackgroundRefreshing }"
        ></div>
        <TrackingHeader
            class="pg-header"
            :title="pageMeta.title"
            :subtitle="pageMeta.subtitle"
            :date-label="date"
            :clock="clock"
            :date="syncDate"
            :live-text="pageMeta.liveText"
        />

        <TrackingFilterBar
            class="pg-filter"
            :filters="filters"
            :flow-filter="filters.flow_filter"
            :flow-filter-modes="flowFilterModes"
            :prd-options="prdOptions"
            :split-options="splitOptions"
            :batch-options="batchOptions"
            :line-options="lineOptions"
            :status-options="statusOptions"
            :active-status="filters.status"
            :loading="loading || loadingMore"
            :summary="summary"
            @update-filter="updateFilter"
            @set-flow-filter="setFlowFilter"
            @set-status="setStatus"
            @apply="applyFilter"
            @reset="resetFilter"
        />

        <div v-if="errorMessage" class="tracking-alert pg-alert">
            {{ errorMessage }}
        </div>

        <div v-if="isEmptyState" class="tracking-empty-wrap">
            <div class="tracking-empty-card">
                <div class="tracking-empty-illustration" aria-hidden="true">
                    <div class="empty-orb orb-a"></div>
                    <div class="empty-orb orb-b"></div>
                    <div class="empty-board">
                        <div class="empty-row"></div>
                        <div class="empty-row"></div>
                        <div class="empty-row short"></div>
                    </div>
                </div>
                <h3 class="tracking-empty-title">{{ emptyStateTitle }}</h3>
                <p class="tracking-empty-text">{{ emptyStateDescription }}</p>
                <div class="tracking-empty-actions">
                    <button
                        type="button"
                        class="tracking-empty-btn"
                        @click="resetFilter"
                    >
                        Reset Filter
                    </button>
                </div>
            </div>
        </div>

        <TrackingBoard
            v-else
            class="pg-board"
            :columns="boardColumns"
            :loading="loading"
        />

        <div
            ref="loadMoreTrigger"
            class="load-more-trigger"
            style="height: 1px; width: 100%"
        ></div>

        <transition name="fade-up">
            <div
                v-if="loadingMore"
                class="tracking-load-more"
                aria-live="polite"
            >
                <div class="tracking-load-more__spinner"></div>
                <div>
                    <div class="tracking-load-more__title">
                        Memuat data berikutnya
                    </div>
                    <div class="tracking-load-more__text">
                        Mohon tunggu, batch 10 data sedang diambil.
                    </div>
                </div>
            </div>
        </transition>

        <transition name="fade-up">
            <div
                v-if="
                    !loadingMore &&
                    tracking.records.length > 0 &&
                    !tracking.pagination.hasMore
                "
                class="tracking-end-message"
            >
                Semua data telah ditampilkan
            </div>
        </transition>

        <TrackingFooter class="pg-footer" :meta="pageMeta" />
    </div>
</template>

<script>
import axios from "axios";
import TrackingHeader from "./components/TrackingHeader.vue";
import TrackingFilterBar from "./components/TrackingFilterBar.vue";
import TrackingBoard from "./components/TrackingBoard.vue";
import TrackingFooter from "./components/TrackingFooter.vue";

const DEFAULT_REFRESH_INTERVAL_MS = 5000;
const INACTIVE_REFRESH_INTERVAL_MS = 15000;

export default {
    components: {
        TrackingHeader,
        TrackingFilterBar,
        TrackingBoard,
        TrackingFooter,
    },
    data() {
        return {
            loading: false,
            loadingMore: false,
            isBackgroundRefreshing: false,
            errorMessage: "",
            requestSeq: 0,
            activeRequestSeq: 0,
            clock: "--:--:--",
            date: "--/--/----",
            clockTimerId: null,
            refreshTimerId: null,
            refreshIntervalMs: DEFAULT_REFRESH_INTERVAL_MS,
            scrollHandlerId: null,
            visibilityHandler: null,
            observer: null,
            pageMeta: {
                title: "Production Tracking",
                subtitle: "Tracking produksi berbasis data controller",
                dateLabel: "—",
                liveText: "LIVE",
                supervisor: "—",
                shift: "—",
                line: "—",
            },
            tracking: {
                columns: [],
                records: [],
                options: {
                    prd: [],
                    no_split: [],
                    no_split_by_prd: {},
                    batch: [],
                    line: [],
                    status: [],
                },
                summary: {
                    total: 0,
                    done: 0,
                    running: 0,
                    pending: 0,
                },
                pagination: {
                    page: 1,
                    perPage: 10,
                    total: 0,
                    lastPage: 1,
                    hasMore: false,
                },
            },
            filters: {
                from: "",
                to: "",
                prd: "",
                no_split: "",
                batch: "",
                line: "",
                status: "all",
                flow_filter: "full_process",
                running_scope: "record",
            },
        };
    },
    computed: {
        isEmptyState() {
            return (
                !this.loading &&
                !this.loadingMore &&
                this.tracking.records.length === 0
            );
        },

        emptyStateTitle() {
            if (this.filters.flow_filter === "live_running") {
                return this.filters.running_scope === "lane"
                    ? "Tidak ada mesin yang sedang running"
                    : "Tidak ada nomor yang sedang running";
            }

            return "Tidak ada data tracking";
        },

        emptyStateDescription() {
            if (this.filters.flow_filter === "live_running") {
                if (this.filters.running_scope === "lane") {
                    return "Tidak ada lane berstatus running untuk filter saat ini. Coba ubah periode, line, atau status.";
                }

                return "Tidak ada record dengan lane berstatus running untuk filter saat ini. Coba ubah periode, PRD order, atau status.";
            }

            return "Tidak ada record yang cocok dengan filter saat ini. Coba ubah periode, PRD, batch, atau status untuk melihat data lain.";
        },

        statusOptions() {
            return this.tracking.options.status || [];
        },
        prdOptions() {
            return this.tracking.options.prd;
        },

        batchOptions() {
            return this.tracking.options.batch;
        },

        splitOptions() {
            const selectedPrd = this.filters.prd || "";
            const mapByPrd = this.tracking.options.no_split_by_prd || {};

            if (selectedPrd && Array.isArray(mapByPrd[selectedPrd])) {
                return mapByPrd[selectedPrd];
            }

            return this.tracking.options.no_split || [];
        },

        lineOptions() {
            return this.tracking.options.line || [];
        },

        flowFilterModes() {
            return this.tracking.options.flow_filter_modes || [];
        },

        summary() {
            return this.tracking.summary;
        },

        boardColumns() {
            return this.tracking.columns.map((column) => {
                const cards = this.tracking.records
                    .map((record) => {
                        const lane = (record.lanes || []).find(
                            (item) => item.column_key === column.key
                        );

                        if (!lane) {
                            return null;
                        }

                        return {
                            recordId: record.group_key || record.id,
                            prd: record.prd || record.prd_order || "-",
                            batch: record.batch,
                            date: record.date,
                            fullDone: record.full_done,
                            lane,
                            column,
                        };
                    })
                    .filter(Boolean);

                const quantity = cards.reduce((sum, card) => {
                    const value = Number(card.lane.qty || 0);

                    return sum + value;
                }, 0);

                const temperatureCards = cards.filter(
                    (card) =>
                        card.lane.suhu !== null && card.lane.suhu !== undefined
                );

                const temperatureAverage = temperatureCards.length
                    ? Math.round(
                          temperatureCards.reduce(
                              (sum, card) => sum + Number(card.lane.suhu),
                              0
                          ) / temperatureCards.length
                      )
                    : null;

                const quantityLabel = this.formatNumber(quantity);
                const temperatureLabel =
                    temperatureAverage === null
                        ? "—"
                        : `${temperatureAverage}°C`;

                return {
                    ...column,
                    cards,
                    count: cards.length,
                    meta:
                        column.unitLabel === "box"
                            ? `Box: <b>${quantityLabel}</b> · Suhu avg: <b>${temperatureLabel}</b>`
                            : `Pcs: <b>${quantityLabel}</b> · Suhu avg: <b>${temperatureLabel}</b>`,
                };
            });
        },
    },
    mounted() {
        this.syncClock();
        this.syncDate();
        this.fetchTrackingData({ page: 1, append: false });
        this.clockTimerId = setInterval(this.syncClock, 1000);
        this.restartRefreshPolling();
        this.scrollHandlerId = () => {
            this.handleWindowScroll();
        };
        this.setupIntersectionObserver();
        this.visibilityHandler = () => this.handleVisibilityChange();
        document.addEventListener("visibilitychange", this.visibilityHandler);
        // window.addEventListener("scroll", this.scrollHandlerId, {
        //     passive: true,
        // });
        // window.addEventListener("resize", this.scrollHandlerId);
    },
    beforeUnmount() {
        if (this.clockTimerId) {
            clearInterval(this.clockTimerId);
        }

        this.stopRefreshPolling();

        // if (this.scrollHandlerId) {
        //     window.removeEventListener("scroll", this.scrollHandlerId);
        //     window.removeEventListener("resize", this.scrollHandlerId);
        // }

        if (this.observer) {
            this.observer.disconnect();
        }

        if (this.visibilityHandler) {
            document.removeEventListener(
                "visibilitychange",
                this.visibilityHandler
            );
        }
    },
    methods: {
        setupIntersectionObserver() {
            // Konfigurasi observer
            const options = {
                root: null, // menggunakan viewport browser
                // rootMargin '800px' artinya: trigger fungsi loadMoreTrackingData
                // SAAT elemen trigger masih berjarak 800 pixel di bawah layar.
                // Semakin besar angkanya, semakin awal dia nge-load sebelum Anda mentok.
                rootMargin: "800px",
                threshold: 0,
            };

            this.observer = new IntersectionObserver((entries) => {
                const target = entries[0];
                // Jika elemen trigger mendekati layar DAN masih ada sisa page
                if (target.isIntersecting && this.tracking.pagination.hasMore) {
                    // Panggil fungsi load more yang sudah Anda buat
                    this.loadMoreTrackingData();
                }
            }, options);

            // Pasangkan observer ke elemen ref="loadMoreTrigger"
            if (this.$refs.loadMoreTrigger) {
                this.observer.observe(this.$refs.loadMoreTrigger);
            }
        },
        syncClock() {
            const now = new Date();
            this.clock = [now.getHours(), now.getMinutes(), now.getSeconds()]
                .map((value) => String(value).padStart(2, "0"))
                .join(":");
        },
        syncDate() {
            const now = new Date();

            const bulan = [
                "Januari",
                "Februari",
                "Maret",
                "April",
                "Mei",
                "Juni",
                "Juli",
                "Agustus",
                "September",
                "Oktober",
                "November",
                "Desember",
            ];

            const tanggal = now.getDate();
            const namaBulan = bulan[now.getMonth()];
            const tahun = now.getFullYear();

            this.date = `${tanggal} ${namaBulan} ${tahun}`;
        },

        // async fetchTrackingData({
        //     page = 1,
        //     append = false,
        //     silent = false,
        // } = {}) {
        //     const requestSeq = ++this.requestSeq;
        //     this.activeRequestSeq = requestSeq;

        //     if (silent) {
        //         this.isBackgroundRefreshing = true;
        //     } else if (append) {
        //         this.loadingMore = true;
        //     } else {
        //         this.loading = true;
        //         this.loadingMore = false;
        //     }

        //     if (!silent) this.errorMessage = "";

        //     try {
        //         const response = await axios.get("/tracking/show", {
        //             params: {
        //                 ...this.filters,
        //                 page,
        //                 per_page: 10,
        //             },
        //         });

        //         if (requestSeq !== this.activeRequestSeq) {
        //             return;
        //         }

        //         this.applyPayload(response.data, { append });
        //         if (!append) {
        //             let newInterval = Number(
        //                 response?.data?.meta?.refreshIntervalMs
        //             );
        //             if (isNaN(newInterval) || newInterval <= 0) {
        //                 newInterval = DEFAULT_REFRESH_INTERVAL_MS;
        //             }
        //             const intervalChanged =
        //                 this.refreshIntervalMs !== newInterval;
        //             this.refreshIntervalMs = newInterval;

        //             if (!silent || intervalChanged) {
        //                 this.restartRefreshPolling();
        //             }
        //         }
        //         this.$nextTick(() => {
        //             this.maybeLoadMore();
        //         });
        //     } catch (error) {
        //         if (requestSeq !== this.activeRequestSeq) {
        //             return;
        //         }

        //         // ... (handling fail state but keeping previous filters)
        //         if (!silent) {
        //             this.errorMessage =
        //                 "Data tracking gagal dimuat dari controller.";
        //             this.applyPayload(
        //                 {
        //                     meta: this.pageMeta,
        //                     columns: [],
        //                     records: [],
        //                     options: {
        //                         prd: [],
        //                         no_split: [],
        //                         no_split_by_prd: {},
        //                         batch: [],
        //                         line: [],
        //                         status: [],
        //                     },
        //                     summary: {
        //                         total: 0,
        //                         done: 0,
        //                         running: 0,
        //                         pending: 0,
        //                     },
        //                     pagination: {
        //                         page: 1,
        //                         perPage: 10,
        //                         total: 0,
        //                         lastPage: 1,
        //                         hasMore: false,
        //                     },
        //                     filters: { ...this.filters, page: 1, per_page: 10 },
        //                 },
        //                 { append: false }
        //             );
        //         }
        //         console.error(error);
        //     } finally {
        //         if (requestSeq === this.activeRequestSeq) {
        //             this.loading = false;
        //             this.loadingMore = false;
        //             this.isBackgroundRefreshing = false;
        //         }
        //     }
        // },

        async fetchTrackingData({
            page = 1,
            perPage = 10,
            append = false,
            silent = false,
            restorePage = null,
        } = {}) {
            const requestSeq = ++this.requestSeq;
            this.activeRequestSeq = requestSeq;

            if (silent) {
                this.isBackgroundRefreshing = true;
            } else if (append) {
                this.loadingMore = true;
            } else {
                this.loading = true;
                this.loadingMore = false;
            }

            if (!silent) this.errorMessage = "";

            try {
                const response = await axios.get("/tracking/show", {
                    params: {
                        ...this.filters,
                        page,
                        per_page: perPage,
                        _t: silent ? Date.now() : undefined,
                    },
                });

                if (requestSeq !== this.activeRequestSeq) {
                    return;
                }

                // 1. Simpan posisi scroll sebelum data baru memodifikasi DOM
                const savedScrollPosition = window.scrollY;

                this.applyPayload(response.data, { append, restorePage });

                // 2. Atur pergerakan scroll setelah Vue merender DOM
                this.$nextTick(() => {
                    if (silent) {
                        // Auto-refresh: Kunci layar persis di posisi semula (tanpa animasi)
                        window.scrollTo({
                            top: savedScrollPosition,
                            behavior: "auto",
                        });
                    }

                    if (append) {
                        // Load Data Baru: Geser layar ke bawah sedikit dengan mulus
                        window.scrollBy({
                            top: 350, // Akan mendorong layar sejauh 350px ke bawah
                            behavior: "smooth",
                        });
                    }
                });

                if (this.observer && this.$refs.loadMoreTrigger) {
                    this.observer.unobserve(this.$refs.loadMoreTrigger);
                    this.observer.observe(this.$refs.loadMoreTrigger);
                }

                // this.applyPayload(response.data, { append, restorePage });

                if (!append) {
                    let newInterval = Number(
                        response?.data?.meta?.refreshIntervalMs
                    );
                    if (isNaN(newInterval) || newInterval <= 0) {
                        newInterval = DEFAULT_REFRESH_INTERVAL_MS;
                    }
                    const intervalChanged =
                        this.refreshIntervalMs !== newInterval;
                    this.refreshIntervalMs = newInterval;

                    if (!silent || intervalChanged) {
                        this.restartRefreshPolling();
                    }
                }
                // this.$nextTick(() => {
                //     this.maybeLoadMore();
                // });
            } catch (error) {
                if (requestSeq !== this.activeRequestSeq) {
                    return;
                }

                if (!silent) {
                    this.errorMessage =
                        "Data tracking gagal dimuat dari controller.";
                    this.applyPayload(
                        {
                            meta: this.pageMeta,
                            columns: [],
                            records: [],
                            options: {
                                prd: [],
                                no_split: [],
                                no_split_by_prd: {},
                                batch: [],
                                line: [],
                                status: [],
                            },
                            summary: {
                                total: 0,
                                done: 0,
                                running: 0,
                                pending: 0,
                            },
                            pagination: {
                                page: 1,
                                perPage: 10,
                                total: 0,
                                lastPage: 1,
                                hasMore: false,
                            },
                            filters: { ...this.filters, page: 1, per_page: 10 },
                        },
                        { append: false }
                    );
                }
                console.error(error);
            } finally {
                if (requestSeq === this.activeRequestSeq) {
                    this.loading = false;
                    this.loadingMore = false;
                    this.isBackgroundRefreshing = false;
                }
            }
        },
        // applyPayload(payload, { append = false } = {}) {
        //     this.pageMeta = {
        //         ...this.pageMeta,
        //         ...(payload.meta || {}),
        //     };

        //     const nextRecords = payload.records || [];
        //     const existingRecords = append ? this.tracking.records : [];

        //     this.tracking = {
        //         columns: payload.columns || [],
        //         records: [...existingRecords, ...nextRecords],
        //         options: payload.options || {
        //             prd: [],
        //             no_split: [],
        //             no_split_by_prd: {},
        //             batch: [],
        //             line: [],
        //             status: [],
        //         },
        //         summary: payload.summary || {
        //             total: 0,
        //             done: 0,
        //             running: 0,
        //             pending: 0,
        //         },
        //         pagination: payload.pagination || {
        //             page: 1,
        //             perPage: 10,
        //             total: 0,
        //             lastPage: 1,
        //             hasMore: false,
        //         },
        //     };

        //     if (payload.filters) {
        //         this.filters = { ...payload.filters };
        //     }
        // },

        applyPayload(payload, { append = false, restorePage = null } = {}) {
            this.pageMeta = {
                ...this.pageMeta,
                ...(payload.meta || {}),
            };

            const nextRecords = payload.records || [];
            const existingRecords = append ? this.tracking.records : [];

            this.tracking = {
                columns: payload.columns || [],
                records: [...existingRecords, ...nextRecords],
                options: payload.options || {
                    prd: [],
                    no_split: [],
                    no_split_by_prd: {},
                    batch: [],
                    line: [],
                    status: [],
                },
                summary: payload.summary || {
                    total: 0,
                    done: 0,
                    running: 0,
                    pending: 0,
                },
                pagination: payload.pagination || {
                    page: 1,
                    perPage: 10,
                    total: 0,
                    lastPage: 1,
                    hasMore: false,
                },
            };

            // --- TAMBAHKAN LOGIKA INI ---
            // Jika ini hasil background refresh, kembalikan posisi halaman & hitung ulang sisa halamannya
            if (restorePage !== null) {
                const total = this.tracking.pagination.total || 0;
                const standardPerPage = 10;
                const recalculatedLastPage =
                    total > 0 ? Math.ceil(total / standardPerPage) : 1;

                this.tracking.pagination.page = restorePage;
                this.tracking.pagination.perPage = standardPerPage;
                this.tracking.pagination.lastPage = recalculatedLastPage;
                this.tracking.pagination.hasMore =
                    restorePage < recalculatedLastPage;
            }
            // -----------------------------

            if (payload.filters) {
                this.filters = { ...payload.filters };
            }
        },

        restartRefreshPolling() {
            this.stopRefreshPolling();

            const isHidden = typeof document !== "undefined" && document.hidden;

            let intervalMs = Number(this.refreshIntervalMs);
            if (isNaN(intervalMs) || intervalMs <= 0) {
                intervalMs = DEFAULT_REFRESH_INTERVAL_MS;
            }

            const interval = isHidden
                ? INACTIVE_REFRESH_INTERVAL_MS
                : Math.max(1000, intervalMs);

            this.refreshTimerId = setInterval(() => {
                this.autoRefreshTick();
            }, interval);
        },

        stopRefreshPolling() {
            if (this.refreshTimerId) {
                clearInterval(this.refreshTimerId);
                this.refreshTimerId = null;
            }
        },

        handleVisibilityChange() {
            this.restartRefreshPolling();
        },

        // autoRefreshTick() {
        //     if (
        //         this.loading ||
        //         this.loadingMore ||
        //         this.isBackgroundRefreshing
        //     ) {
        //         return;
        //     }

        //     // Keep lazy-load pages stable; refresh live feed from first page only.
        //     if ((this.tracking.pagination.page || 1) > 1) {
        //         return;
        //     }

        //     this.fetchTrackingData({ page: 1, append: false, silent: true });
        // },

        autoRefreshTick() {
            if (
                this.loading ||
                this.loadingMore ||
                this.isBackgroundRefreshing
            ) {
                return;
            }

            // HAPUS KODE INI: if ((this.tracking.pagination.page || 1) > 1) { return; }

            // Ambil jumlah page saat ini
            const currentPage = this.tracking.pagination.page || 1;
            const currentPerPage = 10; // Default per_page standar aplikasi Anda
            // Fetch dari page 1, tapi tarik semua data yang sudah terbuka/diload
            const totalToFetch = currentPage * currentPerPage;

            this.fetchTrackingData({
                page: 1,
                perPage: totalToFetch,
                append: false,
                silent: true,
                restorePage: currentPage, // Parameter bantuan untuk menjaga state pagination
            });
        },
        handleWindowScroll() {
            if (
                this.loading ||
                this.loadingMore ||
                this.isBackgroundRefreshing
            ) {
                return;
            }

            if (!this.tracking.pagination.hasMore) {
                return;
            }

            const threshold = 320;
            const viewportBottom = window.scrollY + window.innerHeight;
            const documentBottom = document.documentElement.scrollHeight;

            if (viewportBottom >= documentBottom - threshold) {
                this.loadMoreTrackingData();
            }
        },

        maybeLoadMore() {
            if (
                this.loading ||
                this.loadingMore ||
                this.isBackgroundRefreshing
            ) {
                return;
            }

            if (!this.tracking.pagination.hasMore) {
                return;
            }

            const threshold = 320;
            const viewportBottom = window.scrollY + window.innerHeight;
            const documentBottom = document.documentElement.scrollHeight;

            if (documentBottom <= viewportBottom + threshold) {
                this.loadMoreTrackingData();
            }
        },

        loadMoreTrackingData() {
            if (
                this.loading ||
                this.loadingMore ||
                this.isBackgroundRefreshing ||
                !this.tracking.pagination.hasMore
            ) {
                return;
            }

            const nextPage = (this.tracking.pagination.page || 1) + 1;
            this.fetchTrackingData({ page: nextPage, append: true });
        },

        setFlowFilter(mode) {
            if (this.filters.flow_filter === mode) {
                return;
            }

            this.filters = {
                ...this.filters,
                flow_filter: mode,
                running_scope: mode === "live_running" ? "lane" : "record",
            };
            this.tracking.records = [];
            this.fetchTrackingData({ page: 1, append: false });
        },

        updateFilter({ field, value }) {
            if (field === "line") {
                this.filters = {
                    ...this.filters,
                    line: value,
                    prd: "",
                    no_split: "",
                    batch: "",
                };

                this.applyFilter();

                return;
            }

            if (field === "prd") {
                this.filters = {
                    ...this.filters,
                    prd: value,
                    no_split: "",
                    batch: "",
                };

                this.applyFilter();

                return;
            }

            if (field === "no_split") {
                this.filters = {
                    ...this.filters,
                    no_split: value,
                    batch: "",
                };

                this.applyFilter();

                return;
            }

            if (field === "batch") {
                this.filters = {
                    ...this.filters,
                    batch: value,
                };

                this.applyFilter();

                return;
            }

            this.filters = {
                ...this.filters,
                [field]: value,
            };
        },

        setStatus(status) {
            this.filters = {
                ...this.filters,
                status,
            };
            this.applyFilter();
        },

        applyFilter() {
            this.tracking.records = [];
            this.fetchTrackingData({ page: 1, append: false });
        },

        resetFilter() {
            this.filters = {
                from: "",
                to: "",
                prd: "",
                no_split: "",
                batch: "",
                line: "",
                status: "all",
                flow_filter: "full_process",
                running_scope: "record",
            };
            this.tracking.records = [];
            this.fetchTrackingData({ page: 1, append: false });
        },

        formatNumber(value) {
            return new Intl.NumberFormat("id-ID").format(value || 0);
        },
    },
};
</script>

<style>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");

html,
body {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    -webkit-font-smoothing: antialiased;
}
</style>

<style scoped>
/* --- PESAN AKHIR DATA --- */
.tracking-end-message {
    margin: 24px 24px 30px;
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    color: #64748b; /* Warna abu-abu yang kalem */
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

/* Garis tipis estetis di kiri dan kanan teks */
.tracking-end-message::before,
.tracking-end-message::after {
    content: "";
    height: 1px;
    width: 40px;
    background: #cbd5e1;
    border-radius: 2px;
}
.load-more-trigger,
.tracking-load-more {
    overflow-anchor: none;
}

/* Memastikan area list utama menjadi jangkar scroll yang stabil */
.pg-board {
    overflow-anchor: auto;
}
/* --- LOADING BAR TIPIS DI ATAS LAYAR --- */
.global-progress-bar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background-color: transparent;
    z-index: 9999; /* Pastikan di atas segalanya */
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    pointer-events: none; /* Agar tidak menghalangi klik */
}

.global-progress-bar.is-loading {
    opacity: 1;
    visibility: visible;
}

.global-progress-bar::before {
    content: "";
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 30%; /* Lebar garis yang jalan */
    /* Menggunakan warna gradien agar lebih halus */
    background: linear-gradient(
        90deg,
        transparent,
        #4f46e5,
        #4f46e5,
        transparent
    );
    animation: running-line 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes running-line {
    0% {
        transform: translateX(-100%);
    }
    100% {
        /* Pindah sejauh 300% dari lebar layar */
        transform: translateX(400%);
    }
}
.tracking-page {
    font-family: "Inter", sans-serif;
    background: #f5f6fa;
    color: #1e2330;
    min-height: 100vh;
    overflow-x: clip;
    display: flex;
    flex-direction: column;
    padding-bottom: 70px; /* Clear space for fixed footer */
}

.tracking-alert {
    margin: 16px 24px;
    padding: 12px 16px;
    border: 1px solid #f3d38b;
    background: #fff7e1;
    color: #8a5b08;
    font-size: 0.875rem;
    border-radius: 6px;
    font-family: "Inter", sans-serif;
}

.tracking-empty-wrap {
    padding: 20px 24px 12px;
}

.tracking-empty-card {
    position: relative;
    overflow: hidden;
    border: 1px solid #dbe4ee;
    border-radius: 20px;
    background: radial-gradient(
            circle at top left,
            rgba(79, 70, 229, 0.08),
            transparent 34%
        ),
        linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    padding: 34px 28px 30px;
    text-align: center;
}

.tracking-empty-illustration {
    position: relative;
    width: 220px;
    height: 160px;
    margin: 0 auto 20px;
}

.empty-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(0.2px);
    opacity: 0.95;
    animation: emptyFloat 5s ease-in-out infinite;
}

.orb-a {
    width: 74px;
    height: 74px;
    left: 16px;
    top: 16px;
    background: linear-gradient(135deg, #c7d2fe, #818cf8);
}

.orb-b {
    width: 52px;
    height: 52px;
    right: 20px;
    bottom: 18px;
    background: linear-gradient(135deg, #fce7f3, #f472b6);
    animation-delay: 0.9s;
}

.empty-board {
    position: absolute;
    inset: 24px 26px 20px;
    border-radius: 16px;
    border: 1px solid #d8e2ef;
    background: linear-gradient(180deg, #ffffff, #f7faff);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9);
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
    padding: 18px;
}

.empty-row {
    height: 14px;
    border-radius: 999px;
    background: linear-gradient(
        90deg,
        rgba(79, 70, 229, 0.18),
        rgba(79, 70, 229, 0.05)
    );
    animation: shimmer 2.2s ease-in-out infinite;
}

.empty-row.short {
    width: 68%;
}

.tracking-empty-title {
    margin: 0;
    font-size: 20px;
    font-weight: 800;
    color: #1e2330;
}

.tracking-empty-text {
    margin: 10px auto 0;
    max-width: 620px;
    font-size: 13px;
    line-height: 1.7;
    color: #667085;
}

.tracking-empty-actions {
    margin-top: 18px;
    display: flex;
    justify-content: center;
}

.tracking-empty-btn {
    border: none;
    border-radius: 999px;
    padding: 11px 18px;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    box-shadow: 0 12px 24px rgba(79, 70, 229, 0.24);
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.tracking-empty-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 28px rgba(79, 70, 229, 0.3);
}

.tracking-load-more {
    margin: 10px 24px 0;
    padding: 12px 16px;
    border: 1px solid #dbe4ff;
    border-radius: 14px;
    background: linear-gradient(
        135deg,
        rgba(79, 70, 229, 0.06),
        rgba(236, 72, 153, 0.06)
    );
    display: flex;
    align-items: center;
    gap: 12px;
    color: #334155;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

.tracking-load-more__spinner {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 2px solid rgba(79, 70, 229, 0.18);
    border-top-color: #4f46e5;
    animation: spin-board 0.9s linear infinite;
    flex-shrink: 0;
}

.tracking-load-more__title {
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 2px;
}

.tracking-load-more__text {
    font-size: 12px;
    color: #64748b;
}

.fade-up-enter-active,
.fade-up-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-up-enter-from,
.fade-up-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@keyframes emptyFloat {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

@keyframes shimmer {
    0%,
    100% {
        opacity: 0.75;
        transform: translateX(0);
    }
    50% {
        opacity: 1;
        transform: translateX(4px);
    }
}

@media (max-width: 768px) {
    .tracking-page {
        padding-bottom: 0; /* Reset bottom padding because footer is up top */
    }
    .pg-header {
        order: 1;
    }
    .pg-footer {
        order: 2;
        border-top: none !important;
        border-bottom: 1px solid #e8eaf0;
        margin-top: 0 !important;
    }
    .pg-filter {
        order: 3;
    }
    .pg-alert {
        order: 4;
    }
    .pg-board {
        order: 5;
        flex: 1;
    }

    .tracking-empty-wrap {
        padding: 16px 16px 10px;
    }

    .tracking-empty-card {
        padding: 28px 18px 24px;
    }

    .tracking-empty-illustration {
        width: 180px;
        height: 132px;
    }

    .tracking-empty-title {
        font-size: 18px;
    }

    .tracking-load-more {
        margin: 10px 16px 0;
    }
}
</style>
