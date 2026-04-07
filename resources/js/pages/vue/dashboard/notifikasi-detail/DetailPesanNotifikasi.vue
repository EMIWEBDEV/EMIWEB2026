<template>
    <div class="notification-container mb-3">
        <div class="header">
            <h3 class="title">Daftar Notifikasi</h3>
            <div class="filter-controls">
                <button
                    @click="filterAll"
                    :class="[
                        'filter-btn',
                        activeFilter === 'all' ? 'active' : '',
                    ]"
                >
                    Semua
                </button>

                <button
                    @click="filterUnread"
                    :class="[
                        'filter-btn',
                        activeFilter === 'unread' ? 'active' : '',
                    ]"
                >
                    Belum Dibaca ({{ noReadCount }})
                </button>

                <button
                    class="filter-btn"
                    :disabled="isMarkingAllRead"
                    @click="markAllAsRead"
                >
                    <span v-if="isMarkingAllRead">
                        <span
                            class="spinner-border spinner-border-sm me-1"
                        ></span>
                        Menandai...
                    </span>
                    <span v-else> Tandai Semua Sudah Dibaca </span>
                </button>
            </div>
        </div>
        <div class="notification-list" v-if="loading.loadingListData">
            <div
                class="notification-card skeleton-card"
                v-for="n in 5"
                :key="n"
            >
                <div class="notification-header">
                    <div class="notification-badge shimmer-circle"></div>
                    <div class="notification-meta">
                        <div class="shimmer-line w-60"></div>
                        <div class="shimmer-line w-40 mt-2"></div>
                        <div class="shimmer-line w-50 mt-2"></div>
                        <div class="shimmer-line w-30 mt-2"></div>
                    </div>
                    <div class="notification-arrow shimmer-line w-10"></div>
                </div>
                <div class="notification-content">
                    <div class="content-grid">
                        <div class="details-section">
                            <div class="shimmer-line w-70 mb-2"></div>
                            <div class="shimmer-line w-50 mb-2"></div>
                            <div class="shimmer-line w-60 mb-2"></div>
                            <div class="shimmer-line w-40 mb-2"></div>
                            <div class="shimmer-line w-80 mb-2"></div>
                        </div>
                        <div class="qr-section">
                            <div class="product-card">
                                <div class="product-header">
                                    <div class="shimmer-line w-60 mb-2"></div>
                                </div>
                                <div class="product-body">
                                    <div class="product-info">
                                        <div
                                            class="shimmer-line w-50 mb-2"
                                        ></div>
                                        <div
                                            class="shimmer-line w-40 mb-2"
                                        ></div>
                                        <div
                                            class="shimmer-line w-30 mb-2"
                                        ></div>
                                    </div>
                                    <div class="qr-container">
                                        <div
                                            class="shimmer-box qr-placeholder"
                                        ></div>
                                        <div
                                            class="shimmer-line w-30 mt-2"
                                        ></div>
                                    </div>
                                </div>
                                <hr class="dashed" />
                                <div class="container">
                                    <div class="shimmer-line w-40 mb-2"></div>
                                    <div class="badge-grid mb-3">
                                        <div class="shimmer-badge"></div>
                                        <div class="shimmer-badge"></div>
                                        <div class="shimmer-badge"></div>
                                        <div class="shimmer-badge"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="notification-list" v-else>
            <div
                class="notification-card"
                v-for="(notif, index) in notifications"
                :key="index"
                @click="toggleCard(index)"
                :class="{
                    'notification-card-unread': notif.Flag_Baca === null,
                    'is-active': activeIndex === index,
                }"
            >
                <div class="notification-header">
                    <div class="notification-badge status-ready">
                        <i class="ri-flask-line"></i>
                    </div>
                    <div class="notification-meta">
                        <h4 class="notification-title">Sampel Masuk</h4>
                        <div class="notification-sender">
                            Dikirim oleh
                            <span class="sender-name">{{ notif.Id_User }}</span>
                        </div>
                        <div class="notification-code">
                            Nomor Sampel: {{ notif.No_Sampel || "-" }}
                        </div>
                        <div class="notification-time">
                            <i class="mdi mdi-clock-outline"></i>
                            {{ notif.Tanggal }} •
                            {{ notif.Jam }}
                        </div>
                    </div>
                    <div class="notification-arrow">
                        <i class="mdi mdi-chevron-down"></i>
                    </div>
                </div>

                <div
                    class="notification-content"
                    v-show="activeIndex === index"
                >
                    <div class="content-grid">
                        <div class="details-section">
                            <div class="detail-item">
                                <span class="detail-label">Nomor Sampel:</span>
                                <span class="detail-value">{{
                                    notif.No_Sampel || "-"
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Kode Barang:</span>
                                <span class="detail-value">{{
                                    notif.Kode_Barang || "-"
                                }}</span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label"
                                    >Dikirim Melalui:</span
                                >
                                <span class="detail-value">{{
                                    notif.Id_User || "-"
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Tipe QrCode:</span>
                                <div v-if="notif.Flag_Multi_QrCode === 'Y'">
                                    <span class="detail-value tipeqr"
                                        >Multi QrCode</span
                                    >
                                </div>
                                <div v-else>
                                    <span class="detail-value tipeqr"
                                        >Single QrCode</span
                                    >
                                </div>
                            </div>
                            <div class="detail-item">
                                <span
                                    class="detail-label"
                                    v-if="notif.Jumlah_Pcs !== null"
                                    >Jumlah Pcs</span
                                >
                                <span
                                    class="detail-label"
                                    v-if="notif.Berat_Sampel !== 0"
                                    >Berat Sampel</span
                                >
                                <div v-if="notif.Berat_Sampel !== 0">
                                    <span class="detail-value tipeqr"
                                        >{{ notif.Berat_Sampel }} Kg</span
                                    >
                                </div>
                                <div v-if="notif.Jumlah_Pcs !== null">
                                    <span class="detail-value tipeqr"
                                        >{{ notif.Jumlah_Pcs }} Pcs</span
                                    >
                                </div>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Catatan:</span>
                                <span class="detail-value">{{
                                    notif.Keterangan || "-"
                                }}</span>
                            </div>
                        </div>

                        <div class="qr-section">
                            <div class="product-card">
                                <div class="product-header">
                                    <h5 class="product-name">
                                        {{ notif.Nama_Barang || "-" }}
                                    </h5>
                                </div>
                                <div class="product-body">
                                    <div class="product-info">
                                        <div class="info-item">
                                            {{ notif.No_Sampel || "-" }}
                                        </div>
                                        <div class="info-item">
                                            {{ notif.No_Split_Po || "-" }}
                                        </div>
                                        <div class="info-item">
                                            Batch {{ notif.No_Batch || "-" }}
                                        </div>
                                        <div class="info-item">
                                            {{ notif.Tanggal || "-" }}
                                        </div>
                                    </div>
                                    <div class="qr-container">
                                        <div class="machine-name">
                                            {{ notif.Nama_Mesin || "-" }}
                                        </div>
                                        <div class="qr-code-wrapper">
                                            <qrcode-vue
                                                :value="notif.No_Sampel"
                                                :size="120"
                                                level="H"
                                                foreground="#2c3e50"
                                                background="transparent"
                                            />
                                            <div class="qr-code-label">
                                                SCAN ME
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="dashed" />
                                <div class="container">
                                    <p>Analisa Yang Akan Dilakukan</p>
                                    <div class="badge-grid mb-3">
                                        <span
                                            class="badge"
                                            v-for="(
                                                item, index
                                            ) in notif.Analisa"
                                            :key="index"
                                            >{{ item }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="notif.multi_qrcode.length > 0">
                        <hr />

                        <div class="mb-3">
                            <h6 class="mb-3">Sub QrCode</h6>
                            <div
                                class="d-flex flex-wrap gap-2 justify-content-center"
                            >
                                <div
                                    class="qr-section"
                                    v-for="(data, keys) in notif.multi_qrcode"
                                    :key="keys"
                                >
                                    <div class="product-card">
                                        <div class="product-header">
                                            <h5 class="product-name">
                                                {{ data.Nama_Barang || "-" }}
                                            </h5>
                                        </div>
                                        <div class="product-body">
                                            <div class="product-info">
                                                <div class="info-item">
                                                    {{ data.No_Sampel || "-" }}
                                                </div>
                                                <div class="info-item">
                                                    {{
                                                        data.No_Split_Po || "-"
                                                    }}
                                                </div>
                                                <div class="info-item">
                                                    Batch
                                                    {{ data.No_Batch || "-" }}
                                                </div>
                                                <div class="info-item">
                                                    {{ data.Tanggal || "-" }}
                                                </div>
                                            </div>
                                            <div class="qr-container">
                                                <div class="machine-name">
                                                    {{ data.Nama_Mesin || "-" }}
                                                </div>
                                                <div class="qr-code-wrapper">
                                                    <qrcode-vue
                                                        :value="data.No_Sampel"
                                                        :size="120"
                                                        level="H"
                                                        foreground="#2c3e50"
                                                        background="transparent"
                                                    />
                                                    <div class="qr-code-label">
                                                        SCAN ME
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="dashed" />
                                        <div class="container">
                                            <p>Analisa Yang Akan Dilakukan</p>
                                            <div class="badge-grid mb-3">
                                                <span
                                                    class="badge"
                                                    v-for="(
                                                        item, index
                                                    ) in notif.Analisa"
                                                    :key="index"
                                                    >{{ item }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr />
                    <p class="fst-italic fw-bold">
                        Note: Jika nama analisa tidak muncul di bawah qrCode
                        yaitu <strong>Analisa yang akan dilakukan</strong> atau
                        <strong>muncul tetapi belum sesuai</strong>. maka harus
                        tambah terlebih dahulu di barang uji laboratorium
                    </p>
                </div>
            </div>
            <div
                v-if="totalPage > 1"
                class="d-flex justify-content-between align-items-center mt-4"
            >
                <button
                    class="btn btn-secondary"
                    :disabled="page === 1"
                    @click="prevPage"
                >
                    &laquo; Sebelumnya
                </button>
                <span>Halaman {{ page }} dari {{ totalPage }}</span>
                <button
                    class="btn btn-primary"
                    :disabled="page === totalPage"
                    @click="nextPage"
                >
                    Selanjutnya &raquo;
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
                                this.$el.querySelectorAll(".notification-card")[
                                    index
                                ];
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
        formatTanggal(datetime) {
            const options = { day: "2-digit", month: "long", year: "numeric" };
            return new Date(datetime).toLocaleDateString("id-ID", options);
        },
        formatJam(datetime) {
            return new Date(datetime).toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
            });
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
/* Shimmer animation */
@keyframes shimmer {
    0% {
        background-position: -200px 0;
    }
    100% {
        background-position: 200px 0;
    }
}

.skeleton-card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    margin-bottom: 10px;
    padding: 15px;
    background-color: #fff;
}

.shimmer-line,
.shimmer-box,
.shimmer-circle,
.shimmer-badge {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 400% 100%;
    animation: shimmer 1.5s infinite linear;
    border-radius: 6px;
    height: 16px;
}

.shimmer-box {
    height: 100px;
    width: 100%;
}

.shimmer-circle {
    border-radius: 50%;
    width: 40px;
    height: 40px;
}

.shimmer-badge {
    height: 24px;
    width: 90px;
    border-radius: 20px;
    margin-right: 8px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 400% 100%;
    animation: shimmer 1.5s infinite linear;
}

/* Utility width classes */
.w-10 {
    width: 10%;
}
.w-30 {
    width: 30%;
}
.w-40 {
    width: 40%;
}
.w-50 {
    width: 50%;
}
.w-60 {
    width: 60%;
}
.w-70 {
    width: 70%;
}
.w-80 {
    width: 80%;
}

.container p {
    font-weight: bold;
    margin-bottom: 15px;
    color: #333;
}
.badge-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 4px;
    max-height: 100px; /* Height for exactly 3 badges */
    overflow-y: auto;
}

/* Scrollbar styling */
.badge-grid::-webkit-scrollbar {
    width: 6px;
}

.badge-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.badge-grid::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.badge-grid::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.badge {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    font-size: 8px;
    font-weight: 500;
    color: white;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    min-height: 24px;
}

/* 10 different badge colors */
.badge:nth-child(10n + 1) {
    background-color: #3b82f6;
} /* blue */
.badge:nth-child(10n + 2) {
    background-color: #10b981;
} /* emerald */
.badge:nth-child(10n + 3) {
    background-color: #f59e0b;
} /* amber */
.badge:nth-child(10n + 4) {
    background-color: #ef4444;
} /* red */
.badge:nth-child(10n + 5) {
    background-color: #8b5cf6;
} /* violet */
.badge:nth-child(10n + 6) {
    background-color: #ec4899;
} /* pink */
.badge:nth-child(10n + 7) {
    background-color: #14b8a6;
} /* teal */
.badge:nth-child(10n + 8) {
    background-color: #f97316;
} /* orange */
.badge:nth-child(10n + 9) {
    background-color: #6366f1;
} /* indigo */
.badge:nth-child(10n + 10) {
    background-color: #64748b;
} /* slate */

.notification-container {
    margin: 0 auto;

    font-family: "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans",
        sans-serif;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}
hr.dashed {
    border: none;
    border-top: 2px dashed #6c757d; /* warna abu-abu gelap */
    margin: 1rem 0;
}

.title {
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

.filter-controls {
    display: flex;
    gap: 10px;
}

.filter-btn {
    padding: 6px 12px;
    border-radius: 20px;
    border: 1px solid #e0e0e0;
    background: white;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-btn.active {
    background: #405189;
    color: white;
    border-color: #405189;
}

.filter-btn:hover {
    background: #f5f5f5;
}

.filter-btn.active:hover {
    background: #35457b;
}

.notification-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.notification-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
    cursor: pointer;
}
.notification-card-unread {
    background: #f7f4f4;
}

.notification-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.notification-card.is-active {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.notification-header {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    position: relative;
}

.notification-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.status-ready {
    background-color: rgba(46, 204, 113, 0.1);
    color: #2ecc71;
}

.status-process {
    background-color: rgba(52, 152, 219, 0.1);
    color: #3498db;
}

.status-complete {
    background-color: rgba(155, 89, 182, 0.1);
    color: #9b59b6;
}

.status-problem {
    background-color: rgba(231, 76, 60, 0.1);
    color: #e74c3c;
}

.notification-meta {
    flex-grow: 1;
}

.notification-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px 0;
    color: #2c3e50;
}

.notification-sender {
    font-size: 13px;
    color: #7f8c8d;
    margin-bottom: 2px;
}

.sender-name {
    color: #3498db;
    font-weight: 500;
}

.tipeqr {
    color: #0a93ee !important;
    font-weight: 500;
}

.notification-code {
    font-size: 13px;
    color: #95a5a6;
    margin-bottom: 4px;
}

.notification-time {
    font-size: 12px;
    color: #bdc3c7;
    display: flex;
    align-items: center;
    gap: 4px;
}

.notification-arrow {
    color: #bdc3c7;
    transition: transform 0.3s;
}

.notification-card.is-active .notification-arrow {
    transform: rotate(180deg);
}

.notification-content {
    padding: 0 20px;
    max-height: 0;
    overflow: hidden;
    transition: all 0.3s ease;
}

.notification-card.is-active .notification-content {
    padding: 0 20px 20px;
    max-height: none;
    height: auto;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.details-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-item {
    display: flex;
}

.detail-label {
    font-weight: 600;
    color: #7f8c8d;
    min-width: 120px;
    font-size: 14px;
}

.detail-value {
    color: #34495e;
    font-size: 14px;
}

.qr-section {
    display: flex;
    justify-content: center;
}

.product-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    width: 100%;
    max-width: 400px;
    overflow: hidden;
}

.product-header {
    padding: 12px 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.product-name {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.product-body {
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-info {
    font-size: 12px;
    color: #7f8c8d;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-right: 15px;
}

.info-item {
    white-space: nowrap;
}

.qr-container {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.machine-name {
    font-size: 12px;
    font-weight: 600;
    color: #3498db;
    margin-bottom: 8px;
    text-align: center;
}

.qr-code-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.qr-code-label {
    font-size: 10px;
    color: #95a5a6;
    margin-top: 5px;
    letter-spacing: 1px;
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .qr-section {
        justify-content: flex-start;
        margin-top: 20px;
    }
}
</style>
