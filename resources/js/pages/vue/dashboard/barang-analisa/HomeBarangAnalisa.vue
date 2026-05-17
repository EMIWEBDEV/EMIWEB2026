<template>
    <div class="ba-page">
        <!-- Page Header -->
        <div class="ba-page-header">
            <div class="ba-page-header-left">
                <div class="ba-page-icon">
                    <i class="ri-microscope-line"></i>
                </div>
                <div>
                    <h4 class="ba-page-title">Barang Uji Laboratorium</h4>
                    <p class="ba-page-subtitle">Kelola barang analisa berdasarkan jenis analisa laboratorium</p>
                </div>
            </div>
            <a href="/barang-jenis/analisa/create" class="ba-add-btn">
                <i class="ri-add-line"></i>
                <span>Tambah Barang Analisa</span>
            </a>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading.loadingListData" class="ba-grid">
            <div class="ba-card ba-skeleton" v-for="n in 6" :key="n">
                <div class="ba-card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="ba-sk-icon"></div>
                        <div style="flex: 1">
                            <div class="ba-sk-line w-60 mb-2"></div>
                            <div class="ba-sk-line w-40"></div>
                        </div>
                    </div>
                </div>
                <div class="ba-card-footer">
                    <div class="ba-sk-line w-30"></div>
                    <div class="ba-sk-badge"></div>
                </div>
            </div>
        </div>

        <!-- Grid View -->
        <div v-else>
            <div v-if="listData.length" class="ba-grid">
                <a
                    v-for="(item, index) in listData"
                    :key="index"
                    :href="'/barang-jenis-analisa/show/' + item.Id_Jenis_Analisa"
                    class="ba-card"
                >
                    <div class="ba-card-body">
                        <div class="ba-card-inner">
                            <div class="ba-card-icon-wrap">
                                <i class="ri-test-tube-2-line"></i>
                            </div>
                            <div class="ba-card-info">
                                <div class="ba-card-code">{{ item.kode_analisa || '-' }}</div>
                                <div class="ba-card-name">{{ item.jenis_analisa || '-' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="ba-card-footer">
                        <span class="ba-card-footer-label">
                            <i class="ri-database-2-line me-1"></i>
                            Total Barang
                        </span>
                        <div class="ba-card-count-wrap">
                            <span class="ba-card-count">{{ item.total_data ?? 0 }}</span>
                            <span class="ba-card-count-unit">item</span>
                            <i class="ri-arrow-right-s-line ba-card-arrow"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Empty State -->
            <div v-else class="ba-empty">
                <DotLottieVue
                    style="height: 160px; width: 160px"
                    autoplay
                    loop
                    src="/animation/empty.lottie"
                />
                <div class="ba-empty-title">Belum Ada Data</div>
                <div class="ba-empty-sub">Belum ada jenis analisa yang terdaftar</div>
                <a href="/barang-jenis/analisa/create" class="ba-add-btn mt-3">
                    <i class="ri-add-line"></i>
                    Tambah Sekarang
                </a>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";

export default {
    components: {
        DotLottieVue,
    },
    data() {
        return {
            listData: [],
            loading: {
                loadingListData: false,
            },
        };
    },
    methods: {
        async fetchDataJenisAnalisa() {
            this.loading.loadingListData = true;
            try {
                const response = await axios.get(
                    "/api/v1/barang-jenis-analisa/current"
                );
                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                } else {
                    this.listData = [];
                }
            } catch (error) {
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },
    },
    mounted() {
        this.fetchDataJenisAnalisa();
    },
};
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.ba-page {
    font-family: "Inter", "Segoe UI", sans-serif;
    color: #343a40;
}

/* ── Page Header ─────────────────────────────────────────── */
.ba-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
    padding: 20px 24px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 4px rgba(64, 81, 137, 0.08);
    border: 1px solid #e9ecef;
}

.ba-page-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.ba-page-icon {
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

.ba-page-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin: 0 0 3px;
}

.ba-page-subtitle {
    font-size: 12px;
    color: #878a99;
    margin: 0;
}

.ba-add-btn {
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
    border: none;
    cursor: pointer;
}

.ba-add-btn:hover {
    background: #35457b;
    color: #fff;
    transform: translateY(-1px);
}

/* ── Grid ────────────────────────────────────────────────── */
.ba-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

@media (max-width: 992px) {
    .ba-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 576px) {
    .ba-grid { grid-template-columns: 1fr; }
}

/* ── Card ────────────────────────────────────────────────── */
.ba-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.2s, border-color 0.2s, transform 0.2s;
    overflow: hidden;
}

.ba-card:hover {
    box-shadow: 0 6px 18px rgba(64, 81, 137, 0.12);
    border-color: rgba(64, 81, 137, 0.3);
    transform: translateY(-2px);
}

.ba-card-body {
    padding: 18px 18px 14px;
    flex: 1;
}

.ba-card-inner {
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.ba-card-icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #405189;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.ba-card:nth-child(8n+1) .ba-card-icon-wrap { background: #405189; }
.ba-card:nth-child(8n+2) .ba-card-icon-wrap { background: #0ab39c; }
.ba-card:nth-child(8n+3) .ba-card-icon-wrap { background: #f06548; }
.ba-card:nth-child(8n+4) .ba-card-icon-wrap { background: #f7b84b; }
.ba-card:nth-child(8n+5) .ba-card-icon-wrap { background: #4b93f7; }
.ba-card:nth-child(8n+6) .ba-card-icon-wrap { background: #6f42c1; }
.ba-card:nth-child(8n+7) .ba-card-icon-wrap { background: #e83e8c; }
.ba-card:nth-child(8n+8) .ba-card-icon-wrap { background: #20c997; }

.ba-card-info {
    flex: 1;
    min-width: 0;
}

.ba-card-code {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #405189;
    margin-bottom: 4px;
}

.ba-card-name {
    font-size: 14px;
    font-weight: 600;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.3;
}

.ba-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 18px;
    background: #f8f9fc;
    border-top: 1px solid #f0f2f5;
}

.ba-card-footer-label {
    font-size: 11px;
    color: #878a99;
    display: flex;
    align-items: center;
}

.ba-card-count-wrap {
    display: flex;
    align-items: center;
    gap: 3px;
}

.ba-card-count {
    font-size: 14px;
    font-weight: 700;
    color: #405189;
}

.ba-card-count-unit {
    font-size: 11px;
    color: #878a99;
}

.ba-card-arrow {
    font-size: 16px;
    color: #adb5bd;
    transition: transform 0.2s;
}

.ba-card:hover .ba-card-arrow {
    transform: translateX(3px);
    color: #405189;
}

/* ── Empty State ─────────────────────────────────────────── */
.ba-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px dashed #dee2e6;
}

.ba-empty-title {
    font-size: 15px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 6px;
}

.ba-empty-sub {
    font-size: 13px;
    color: #878a99;
}

/* ── Skeleton ────────────────────────────────────────────── */
@keyframes ba-shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}

.ba-sk-line,
.ba-sk-icon,
.ba-sk-badge {
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 800px 100%;
    animation: ba-shimmer 1.5s infinite linear;
    border-radius: 4px;
    height: 14px;
}

.ba-sk-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    flex-shrink: 0;
}

.ba-sk-badge {
    width: 52px;
    height: 20px;
    border-radius: 10px;
}

.ba-sk-line.w-40 { width: 40%; }
.ba-sk-line.w-60 { width: 60%; }
.ba-sk-line.w-30 { width: 30%; }

.ba-skeleton .ba-card-footer {
    background: #f8f9fc;
    border-top: 1px solid #f0f2f5;
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
