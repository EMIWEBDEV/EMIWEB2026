<template>
    <div class="notif-page">
        <!-- Page Header -->
        <div class="notif-page-header">
            <div class="notif-page-header-left">
                <div class="notif-page-icon">
                    <i class="ri-notification-3-line"></i>
                </div>
                <div>
                    <h4 class="notif-page-title">Daftar Notifikasi</h4>
                    <p class="notif-page-subtitle">Notifikasi sampel masuk dari laboratorium</p>
                </div>
            </div>
            <div class="notif-page-header-right">
                <div class="notif-stat-chip">
                    <i class="ri-mail-unread-line"></i>
                    <span>Belum Dibaca</span>
                    <span class="notif-stat-badge">{{ noReadCount }}</span>
                </div>
                <button
                    class="btn btn-soft-primary btn-sm d-flex align-items-center gap-2"
                    :disabled="isMarkingAllRead"
                    @click="markAllAsRead"
                >
                    <span v-if="isMarkingAllRead" class="spinner-border spinner-border-sm"></span>
                    <i v-else class="ri-check-double-line"></i>
                    <span>{{ isMarkingAllRead ? 'Menandai...' : 'Tandai Semua Dibaca' }}</span>
                </button>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="notif-filter-bar">
            <button
                class="notif-filter-tab"
                :class="{ active: activeFilter === 'all' }"
                @click="filterAll"
            >
                <i class="ri-list-check-2"></i>
                <span>Semua</span>
            </button>
            <button
                class="notif-filter-tab"
                :class="{ active: activeFilter === 'unread' }"
                @click="filterUnread"
            >
                <i class="ri-mail-unread-line"></i>
                <span>Belum Dibaca</span>
                <span v-if="noReadCount > 0" class="notif-tab-count">{{ noReadCount }}</span>
            </button>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loading.loadingListData" class="notif-list">
            <div class="notif-card notif-skeleton" v-for="n in 4" :key="n">
                <div class="notif-card-header">
                    <div class="sk-circle"></div>
                    <div class="notif-card-meta" style="flex: 1">
                        <div class="sk-line w-40"></div>
                        <div class="sk-line w-60 mt-2"></div>
                        <div class="sk-line w-30 mt-2"></div>
                    </div>
                    <div class="sk-line w-10"></div>
                </div>
            </div>
        </div>

        <!-- Notification List -->
        <div v-else class="notif-list">
            <div
                v-for="(notif, index) in notifications"
                :key="index"
                class="notif-card"
                :class="{
                    'is-unread': notif.Flag_Baca === null,
                    'is-open': activeIndex === index,
                }"
                @click="toggleCard(index)"
            >
                <!-- Card Header (always visible) -->
                <div class="notif-card-header">
                    <div class="notif-avatar" :class="notif.Flag_Baca === null ? 'avatar-primary' : 'avatar-secondary'">
                        <i class="ri-flask-line"></i>
                    </div>
                    <div class="notif-card-meta">
                        <div class="notif-card-top-row">
                            <span class="notif-card-type">Sampel Masuk</span>
                            <span v-if="notif.Flag_Baca === null" class="notif-unread-dot">
                                <i class="ri-checkbox-blank-circle-fill me-1" style="font-size:8px"></i> Baru
                            </span>
                        </div>
                        <div class="notif-card-title">{{ notif.No_Sampel || '-' }}</div>
                        <div class="notif-card-info-row">
                            <span class="notif-card-info-item">
                                <i class="ri-user-line"></i>
                                {{ notif.Id_User || '-' }}
                            </span>
                            <span class="notif-card-info-item">
                                <i class="ri-time-line"></i>
                                {{ notif.Tanggal }} &bull; {{ notif.Jam }}
                            </span>
                        </div>
                    </div>
                    <div class="notif-card-chevron" :class="{ rotated: activeIndex === index }">
                        <i class="ri-arrow-down-s-line"></i>
                    </div>
                </div>

                <!-- Card Expanded Detail -->
                <div class="notif-card-body" v-show="activeIndex === index" @click.stop>
                    <div class="notif-detail-grid">
                        <!-- Left: Details -->
                        <div class="notif-detail-section">
                            <div class="notif-detail-group-title">
                                <i class="ri-information-line me-1"></i> Informasi Sampel
                            </div>
                            <table class="notif-detail-table">
                                <tr>
                                    <td class="notif-detail-label">Nomor Sampel</td>
                                    <td class="notif-detail-sep">:</td>
                                    <td class="notif-detail-val fw-semibold text-primary">{{ notif.No_Sampel || '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="notif-detail-label">Kode Barang</td>
                                    <td class="notif-detail-sep">:</td>
                                    <td class="notif-detail-val">{{ notif.Kode_Barang || '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="notif-detail-label">Dikirim Oleh</td>
                                    <td class="notif-detail-sep">:</td>
                                    <td class="notif-detail-val">
                                        <span class="badge badge-soft-info">{{ notif.Id_User || '-' }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="notif-detail-label">Tipe QrCode</td>
                                    <td class="notif-detail-sep">:</td>
                                    <td class="notif-detail-val">
                                        <span v-if="notif.Flag_Multi_QrCode === 'Y'" class="badge badge-soft-warning">
                                            <i class="ri-qr-code-line me-1"></i>Multi QrCode
                                        </span>
                                        <span v-else class="badge badge-soft-success">
                                            <i class="ri-qr-code-line me-1"></i>Single QrCode
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="notif.Berat_Sampel !== 0">
                                    <td class="notif-detail-label">Berat Sampel</td>
                                    <td class="notif-detail-sep">:</td>
                                    <td class="notif-detail-val"><strong>{{ notif.Berat_Sampel }}</strong> Kg</td>
                                </tr>
                                <tr v-if="notif.Jumlah_Pcs !== null">
                                    <td class="notif-detail-label">Jumlah Pcs</td>
                                    <td class="notif-detail-sep">:</td>
                                    <td class="notif-detail-val"><strong>{{ notif.Jumlah_Pcs }}</strong> Pcs</td>
                                </tr>
                                <tr>
                                    <td class="notif-detail-label">Catatan</td>
                                    <td class="notif-detail-sep">:</td>
                                    <td class="notif-detail-val">{{ notif.Keterangan || '-' }}</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Right: Sample Ticket -->
                        <div class="notif-ticket-section">
                            <div class="notif-sample-ticket">
                                <div class="notif-ticket-header">
                                    <div class="notif-ticket-icon">
                                        <i class="ri-test-tube-line"></i>
                                    </div>
                                    <div class="notif-ticket-name">{{ notif.Nama_Barang || '-' }}</div>
                                </div>
                                <div class="notif-ticket-body">
                                    <div class="notif-ticket-info">
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-barcode-line"></i>
                                            <span>{{ notif.No_Sampel || '-' }}</span>
                                        </div>
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-file-list-3-line"></i>
                                            <span>{{ notif.No_Split_Po || '-' }}</span>
                                        </div>
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-stack-line"></i>
                                            <span>Batch {{ notif.No_Batch || '-' }}</span>
                                        </div>
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-calendar-line"></i>
                                            <span>{{ notif.Tanggal || '-' }}</span>
                                        </div>
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-settings-3-line"></i>
                                            <span>{{ notif.Nama_Mesin || '-' }}</span>
                                        </div>
                                    </div>
                                    <div class="notif-ticket-qr">
                                        <qrcode-vue
                                            :value="notif.No_Sampel"
                                            :size="110"
                                            level="H"
                                            foreground="#2c3e50"
                                            background="transparent"
                                        />
                                        <div class="notif-qr-label">SCAN ME</div>
                                    </div>
                                </div>
                                <div class="notif-ticket-divider"></div>
                                <div class="notif-ticket-analisa">
                                    <div class="notif-analisa-title">
                                        <i class="ri-test-tube-2-line me-1"></i>
                                        Analisa Yang Akan Dilakukan
                                    </div>
                                    <div class="notif-analisa-badges">
                                        <span
                                            class="notif-analisa-badge"
                                            v-for="(item, ai) in notif.Analisa"
                                            :key="ai"
                                        >{{ item }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Multi QR Section -->
                    <div v-if="notif.multi_qrcode && notif.multi_qrcode.length > 0" class="notif-multi-qr-section">
                        <div class="notif-multi-qr-title">
                            <i class="ri-qr-code-line me-1"></i>
                            Sub QrCode
                            <span class="badge badge-soft-primary ms-2">{{ notif.multi_qrcode.length }}</span>
                        </div>
                        <div class="notif-multi-qr-grid">
                            <div
                                class="notif-sample-ticket notif-sub-ticket"
                                v-for="(data, ki) in notif.multi_qrcode"
                                :key="ki"
                            >
                                <div class="notif-ticket-header">
                                    <div class="notif-ticket-icon notif-ticket-icon-sm">
                                        <i class="ri-qr-code-line"></i>
                                    </div>
                                    <div class="notif-ticket-name">{{ data.Nama_Barang || '-' }}</div>
                                </div>
                                <div class="notif-ticket-body">
                                    <div class="notif-ticket-info">
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-barcode-line"></i>
                                            <span>{{ data.No_Sampel || '-' }}</span>
                                        </div>
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-file-list-3-line"></i>
                                            <span>{{ data.No_Split_Po || '-' }}</span>
                                        </div>
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-stack-line"></i>
                                            <span>Batch {{ data.No_Batch || '-' }}</span>
                                        </div>
                                        <div class="notif-ticket-info-row">
                                            <i class="ri-calendar-line"></i>
                                            <span>{{ data.Tanggal || '-' }}</span>
                                        </div>
                                    </div>
                                    <div class="notif-ticket-qr">
                                        <qrcode-vue
                                            :value="data.No_Sampel"
                                            :size="90"
                                            level="H"
                                            foreground="#2c3e50"
                                            background="transparent"
                                        />
                                        <div class="notif-qr-label">SCAN ME</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="notif-note-bar">
                        <i class="ri-information-line me-1 flex-shrink-0"></i>
                        <span>
                            Jika nama analisa tidak muncul di bawah QR Code, tambahkan terlebih dahulu di
                            <strong>Barang Uji Laboratorium</strong>.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!notifications.length" class="notif-empty">
                <div class="notif-empty-icon"><i class="ri-notification-off-line"></i></div>
                <div class="notif-empty-title">Tidak Ada Notifikasi</div>
                <div class="notif-empty-sub">Belum ada notifikasi masuk saat ini</div>
            </div>

            <!-- Pagination -->
            <div v-if="totalPage > 1" class="notif-pagination">
                <button class="notif-page-btn" :disabled="page === 1" @click="prevPage">
                    <i class="ri-arrow-left-s-line"></i> Sebelumnya
                </button>
                <div class="notif-page-info">
                    <span class="notif-page-current">{{ page }}</span>
                    <span class="notif-page-sep">/</span>
                    <span>{{ totalPage }}</span>
                </div>
                <button class="notif-page-btn" :disabled="page === totalPage" @click="nextPage">
                    Selanjutnya <i class="ri-arrow-right-s-line"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import QrcodeVue from "qrcode.vue";
import axios from "axios";

export default {
    components: {
        QrcodeVue,
    },
    props: {
        initialKode: {
            type: String,
            default: null,
        },
        initialPage: {
            type: Number,
            default: 1,
        },
    },
    data() {
        return {
            activeIndex: null,
            notifications: [],
            activeFilter: "all",
            noReadCount: 0,
            loading: {
                loadingListData: false,
            },
            isMarkingAllRead: false,
            page: 1,
            totalPage: 1,
            limit: 5,
            targetKode: this.initialKode,
        };
    },
    methods: {
        async fetchListNotifikasi(filterType = null) {
            this.loading.loadingListData = true;

            if (filterType === "all") {
                this.activeFilter = "all";
                if (!this.targetKode) this.page = 1;
            } else if (filterType === "unread") {
                this.activeFilter = "unread";
                this.page = 1;
            }

            let params = {
                page: this.page,
                limit: this.limit,
            };

            if (this.activeFilter === "unread") {
                params.filter = "unread";
            }

            try {
                const response = await axios.get("/api/v1/notifikasi/current", {
                    params: params,
                });

                this.notifications = response.data.result || [];
                this.totalPage = response.data.total_page;

                const countRes = await axios.get(
                    "/api/v1/notifikasi-count/no-read"
                );
                this.noReadCount = countRes.data?.result || 0;

                if (this.targetKode && this.activeFilter === "all") {
                    const index = this.findActiveIndexByKode(this.targetKode);
                    if (index !== -1) {
                        this.activeIndex = index;
                        this.$nextTick(() => {
                            const el =
                                this.$el.querySelectorAll(".notif-card")[index];
                            if (el) {
                                el.scrollIntoView({
                                    behavior: "smooth",
                                    block: "center",
                                });
                            }
                        });
                    }
                } else {
                    this.activeIndex = null;
                }
            } catch (error) {
                console.error("Gagal ambil notifikasi:", error);
                this.notifications = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },

        nextPage() {
            if (this.page < this.totalPage) {
                this.page++;
                this.fetchListNotifikasi();
            }
        },
        prevPage() {
            if (this.page > 1) {
                this.page--;
                this.fetchListNotifikasi();
            }
        },

        filterUnread() {
            this.targetKode = null;
            this.fetchListNotifikasi("unread");
        },

        filterAll() {
            this.page = 1;
            this.targetKode = null;
            this.fetchListNotifikasi("all");
        },

        async markAllAsRead() {
            if (this.isMarkingAllRead) return;
            this.isMarkingAllRead = true;

            try {
                const response = await axios.put(
                    "/api/v1/notifikasi/update/all-read"
                );

                if (response.status === 200) {
                    this.fetchListNotifikasi();
                }
            } catch (error) {
                console.error("Gagal menandai semua notifikasi:", error);
            } finally {
                this.isMarkingAllRead = false;
            }
        },

        toggleCard(index) {
            this.activeIndex = this.activeIndex === index ? null : index;
        },
        findActiveIndexByKode(kode) {
            return this.notifications.findIndex(
                (notif) => notif.No_Sampel === kode
            );
        },
    },
    mounted() {
        this.page = this.initialPage;
        this.fetchListNotifikasi();
    },
};
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────── */
.notif-page {
    font-family: "Inter", "Segoe UI", sans-serif;
    color: #343a40;
}

/* ── Page Header ─────────────────────────────────────────── */
.notif-page-header {
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

.notif-page-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.notif-page-icon {
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

.notif-page-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin: 0 0 3px;
}

.notif-page-subtitle {
    font-size: 12px;
    color: #878a99;
    margin: 0;
}

.notif-page-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.notif-stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f3f6f9;
    border-radius: 20px;
    font-size: 12px;
    color: #6c757d;
    border: 1px solid #e2e8f0;
}

.notif-stat-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    background: #405189;
    color: #fff;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

.btn-soft-primary {
    background: rgba(64, 81, 137, 0.1);
    color: #405189;
    border: 1px solid rgba(64, 81, 137, 0.2);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 500;
    padding: 7px 14px;
    transition: all 0.2s;
    cursor: pointer;
}

.btn-soft-primary:hover:not(:disabled) {
    background: #405189;
    color: #fff;
}

.btn-soft-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* ── Filter Tabs ─────────────────────────────────────────── */
.notif-filter-bar {
    display: flex;
    gap: 4px;
    margin-bottom: 16px;
    background: #fff;
    border-radius: 10px;
    padding: 5px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    width: fit-content;
}

.notif-filter-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 7px;
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    color: #6c757d;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.notif-filter-tab:hover {
    background: #f8f9fa;
    color: #405189;
}

.notif-filter-tab.active {
    background: #405189;
    color: #fff;
}

.notif-tab-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    background: rgba(255,255,255,0.25);
    border-radius: 9px;
    font-size: 10px;
    font-weight: 700;
}

.notif-filter-tab:not(.active) .notif-tab-count {
    background: #ef4444;
    color: #fff;
}

/* ── Notification List ───────────────────────────────────── */
.notif-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* ── Notification Card ───────────────────────────────────── */
.notif-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: box-shadow 0.2s, border-color 0.2s;
    cursor: pointer;
}

.notif-card:hover {
    box-shadow: 0 4px 12px rgba(64, 81, 137, 0.1);
    border-color: rgba(64, 81, 137, 0.2);
}

.notif-card.is-unread {
    border-left: 3px solid #405189;
    background: #fafbff;
}

.notif-card.is-open {
    box-shadow: 0 4px 16px rgba(64, 81, 137, 0.12);
    border-color: rgba(64, 81, 137, 0.3);
}

/* ── Card Header ─────────────────────────────────────────── */
.notif-card-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
}

.notif-avatar {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.avatar-primary {
    background: rgba(64, 81, 137, 0.12);
    color: #405189;
}

.avatar-secondary {
    background: #f3f6f9;
    color: #878a99;
}

.notif-card-meta {
    flex: 1;
    min-width: 0;
}

.notif-card-top-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 3px;
}

.notif-card-type {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #878a99;
}

.notif-unread-dot {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 600;
    color: #405189;
    background: rgba(64, 81, 137, 0.1);
    padding: 2px 7px;
    border-radius: 10px;
}

.notif-card-title {
    font-size: 14px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notif-card-info-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.notif-card-info-item {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #878a99;
}

.notif-card-chevron {
    color: #adb5bd;
    font-size: 20px;
    flex-shrink: 0;
    transition: transform 0.25s;
}

.notif-card-chevron.rotated {
    transform: rotate(180deg);
}

/* ── Card Body (expanded) ────────────────────────────────── */
.notif-card-body {
    padding: 0 18px 18px;
    border-top: 1px solid #f0f2f5;
}

.notif-detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    padding-top: 16px;
}

.notif-detail-group-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #878a99;
    margin-bottom: 10px;
}

.notif-detail-table {
    width: 100%;
    border-collapse: collapse;
}

.notif-detail-table tr td {
    padding: 5px 0;
    vertical-align: top;
    font-size: 13px;
}

.notif-detail-label {
    color: #6c757d;
    font-weight: 500;
    min-width: 110px;
    white-space: nowrap;
}

.notif-detail-sep {
    padding: 5px 8px !important;
    color: #adb5bd;
}

.notif-detail-val {
    color: #343a40;
}

/* ── Sample Ticket ───────────────────────────────────────── */
.notif-ticket-section {
    display: flex;
    justify-content: center;
}

.notif-sample-ticket {
    background: #f8f9fc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    width: 100%;
    max-width: 340px;
    overflow: hidden;
}

.notif-ticket-header {
    padding: 10px 14px;
    background: #405189;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notif-ticket-icon {
    width: 28px;
    height: 28px;
    background: rgba(255,255,255,0.2);
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 14px;
    flex-shrink: 0;
}

.notif-ticket-icon-sm {
    width: 24px;
    height: 24px;
    font-size: 12px;
}

.notif-ticket-name {
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notif-ticket-body {
    padding: 12px 14px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.notif-ticket-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
    flex: 1;
    min-width: 0;
}

.notif-ticket-info-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    color: #6c757d;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notif-ticket-info-row i {
    font-size: 12px;
    color: #adb5bd;
    flex-shrink: 0;
}

.notif-ticket-qr {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
}

.notif-qr-label {
    font-size: 9px;
    letter-spacing: 1.5px;
    color: #adb5bd;
    margin-top: 4px;
    font-weight: 600;
}

.notif-ticket-divider {
    height: 1px;
    background: repeating-linear-gradient(
        to right,
        #dee2e6 0px,
        #dee2e6 6px,
        transparent 6px,
        transparent 10px
    );
    margin: 0 14px;
}

.notif-ticket-analisa {
    padding: 10px 14px 12px;
}

.notif-analisa-title {
    font-size: 11px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.notif-analisa-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.notif-analisa-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
    color: #fff;
}

.notif-analisa-badge:nth-child(10n+1)  { background: #3b82f6; }
.notif-analisa-badge:nth-child(10n+2)  { background: #10b981; }
.notif-analisa-badge:nth-child(10n+3)  { background: #f59e0b; }
.notif-analisa-badge:nth-child(10n+4)  { background: #ef4444; }
.notif-analisa-badge:nth-child(10n+5)  { background: #8b5cf6; }
.notif-analisa-badge:nth-child(10n+6)  { background: #ec4899; }
.notif-analisa-badge:nth-child(10n+7)  { background: #14b8a6; }
.notif-analisa-badge:nth-child(10n+8)  { background: #f97316; }
.notif-analisa-badge:nth-child(10n+9)  { background: #6366f1; }
.notif-analisa-badge:nth-child(10n+10) { background: #64748b; }

/* ── Multi QR Section ────────────────────────────────────── */
.notif-multi-qr-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid #f0f2f5;
}

.notif-multi-qr-title {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.notif-multi-qr-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.notif-sub-ticket {
    max-width: 260px;
}

/* ── Note Bar ────────────────────────────────────────────── */
.notif-note-bar {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 14px;
    padding: 10px 14px;
    background: #fff8e6;
    border: 1px solid #ffc107;
    border-radius: 8px;
    font-size: 12px;
    color: #856404;
}

/* ── Badges ──────────────────────────────────────────────── */
.badge-soft-primary { background: rgba(64,81,137,.12); color: #405189; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
.badge-soft-info    { background: rgba(17,156,218,.1);  color: #119bda; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
.badge-soft-warning { background: rgba(240,185,11,.12); color: #b07d00; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
.badge-soft-success { background: rgba(10,179,156,.12); color: #0ab39c; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }

/* ── Empty State ─────────────────────────────────────────── */
.notif-empty {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px dashed #dee2e6;
}

.notif-empty-icon {
    font-size: 48px;
    color: #dee2e6;
    margin-bottom: 12px;
}

.notif-empty-title {
    font-size: 15px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 4px;
}

.notif-empty-sub {
    font-size: 13px;
    color: #878a99;
}

/* ── Pagination ──────────────────────────────────────────── */
.notif-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 8px;
    padding: 12px;
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.notif-page-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 14px;
    border-radius: 7px;
    border: 1px solid #dee2e6;
    background: #fff;
    font-size: 12px;
    color: #495057;
    cursor: pointer;
    transition: all 0.2s;
}

.notif-page-btn:hover:not(:disabled) {
    background: #405189;
    color: #fff;
    border-color: #405189;
}

.notif-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.notif-page-info {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    color: #6c757d;
}

.notif-page-current {
    font-weight: 700;
    color: #405189;
}

.notif-page-sep {
    color: #dee2e6;
}

/* ── Skeleton ────────────────────────────────────────────── */
@keyframes sk-shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}

.sk-line, .sk-circle {
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 800px 100%;
    animation: sk-shimmer 1.5s infinite linear;
    border-radius: 4px;
    height: 14px;
}

.sk-circle {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    flex-shrink: 0;
}

.sk-line.w-10 { width: 10%; }
.sk-line.w-30 { width: 30%; }
.sk-line.w-40 { width: 40%; }
.sk-line.w-60 { width: 60%; }

.notif-skeleton .notif-card-header {
    cursor: default;
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 768px) {
    .notif-detail-grid {
        grid-template-columns: 1fr;
    }

    .notif-ticket-section {
        justify-content: flex-start;
    }

    .notif-page-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .notif-page-header-right {
        width: 100%;
    }
}
</style>
