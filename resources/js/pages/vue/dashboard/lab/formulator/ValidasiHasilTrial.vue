<template>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0 main-card">
                    <div class="card-body">
                        <div class="mb-4">
                            <div class="d-flex align-items-center">
                                <i
                                    class="fas fa-vial text-primary me-3 fa-2x"
                                ></i>
                                <div>
                                    <h1 class="h2 fw-bold text-primary mb-1">
                                        Kumpulan Data Trial
                                    </h1>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-building me-1"></i>
                                        Koleksi Data Trial laboratorium PT. Evo
                                        Manufacturing Indonesia
                                    </p>
                                </div>
                            </div>
                            <hr class="my-4" />
                        </div>

                        <div
                            class="alert alert-primary d-flex align-items-start"
                            role="alert"
                        >
                            <i class="fas fa-info-circle fa-lg me-3 mt-1"></i>
                            <div>
                                <h5 class="alert-heading fw-bold">Penting!</h5>
                                <p class="mb-0">
                                    Data yang ditampilkan merupakan hasil submit
                                    terakhir dari analisa laboratorium. Untuk
                                    menjaga keamanan data, segera lakukan
                                    <strong>konfirmasi penyelesaian</strong>
                                    setelah verifikasi. Data yang sudah
                                    dikonfirmasi akan difinalisasi dan tidak
                                    dapat diubah kembali.
                                </p>
                            </div>
                        </div>

                        <div class="card card-body my-4 filter-card">
                            <div class="row g-3">
                                <div class="col-lg-12">
                                    <label class="form-label small"
                                        >Pencarian Global</label
                                    >
                                    <div class="input-group">
                                        <span class="input-group-text"
                                            ><i class="fas fa-search"></i
                                        ></span>
                                        <input
                                            type="text"
                                            class="form-control"
                                            placeholder="Cari No. Sampel, PO, Split PO, Batch..."
                                            v-model="searchQuery"
                                        />
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6">
                                    <label class="form-label small"
                                        >Dari Tanggal Uji</label
                                    >
                                    <input
                                        type="date"
                                        class="form-control"
                                        v-model="filters.tanggal.mulai"
                                    />
                                </div>
                                <div class="col-lg-6 col-md-6">
                                    <label class="form-label small"
                                        >Sampai Tanggal Uji</label
                                    >
                                    <input
                                        type="date"
                                        class="form-control"
                                        v-model="filters.tanggal.selesai"
                                    />
                                </div>
                                <div class="col-lg-12">
                                    <label class="form-label small"
                                        >Tipe QRCode</label
                                    >
                                    <v-select
                                        :options="filterOptions.qrcode"
                                        placeholder="Semua Tipe"
                                        v-model="filters.qrcode"
                                        :clearable="true"
                                    ></v-select>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 content-area">
                            <ListSkeleton
                                :count="10"
                                v-if="loading.loadingListData"
                            />

                            <div v-else-if="listData.length > 0">
                                <div class="list-container">
                                    <a
                                        v-for="(item, kode) in listData"
                                        :key="kode"
                                        :href="
                                            item.Flag_Multi_QrCode === 'Y'
                                                ? `/validasi/hasil/uji-trial/confirmed-analisis/${item.No_Po_Sampel}/multi/${item.Id_Jenis_Analisa}`
                                                : `/validasi/hasil/uji-trial/confirmed-analisis/${item.No_Po_Sampel}/single-qrCode`
                                        "
                                        class="analisa-card"
                                        :class="[
                                            statusClass(item),
                                            {
                                                'status-lolos':
                                                    item.Status_Sampel ===
                                                    'Lolos Uji',
                                                'status-tidak':
                                                    item.Status_Sampel ===
                                                    'Tidak Lolos Uji',
                                            },
                                        ]"
                                    >
                                        <!-- STATUS ICON -->
                                        <div class="analisa-card-status">
                                            <i
                                                class="fas"
                                                :class="statusIcon(item)"
                                            ></i>
                                        </div>

                                        <!-- KONTEN -->
                                        <div class="analisa-card-content">
                                            <!-- HEADER -->
                                            <div class="content-header">
                                                <div class="title-group">
                                                    <h6>
                                                        {{
                                                            item.po_info
                                                                ?.Kode_Barang
                                                        }}-{{
                                                            item.Nama_Barang
                                                        }}
                                                    </h6>
                                                    <h5 class="title">
                                                        {{ item.Jenis_Analisa }}
                                                    </h5>
                                                    <div class="subtitle-group">
                                                        <span
                                                            class="subtitle-kode"
                                                            >{{
                                                                item.No_Po_Sampel
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="
                                                                item.Flag_Multi_QrCode ===
                                                                'Y'
                                                            "
                                                            class="badge-custom badge-multi"
                                                        >
                                                            <i
                                                                class="fas fa-clone"
                                                            ></i>
                                                            Multi QR
                                                        </span>
                                                        <span
                                                            v-else
                                                            class="badge-custom badge-single"
                                                        >
                                                            <i
                                                                class="fas fa-qrcode"
                                                            ></i>
                                                            Single QR
                                                        </span>
                                                    </div>
                                                </div>
                                                <span
                                                    :class="[
                                                        'badge-custom',
                                                        getBadgeClass(item),
                                                    ]"
                                                >
                                                    <i
                                                        :class="[
                                                            'fas',
                                                            statusIcon(item),
                                                        ]"
                                                    ></i>
                                                    {{ item.Status_Sampel }}
                                                </span>
                                            </div>

                                            <div class="content-details">
                                                <div class="detail-pair">
                                                    <span class="label"
                                                        ><i
                                                            class="fas fa-cogs"
                                                        ></i>
                                                        Nama Mesin</span
                                                    >
                                                    <span class="value">{{
                                                        item.po_info
                                                            ?.Nama_Mesin || "-"
                                                    }}</span>
                                                </div>

                                                <div class="detail-pair">
                                                    <span class="label"
                                                        ><i
                                                            class="fas fa-receipt"
                                                        ></i>
                                                        No. PO</span
                                                    >
                                                    <span class="value"
                                                        >{{
                                                            item.po_info
                                                                ?.No_Po || "-"
                                                        }}
                                                    </span>
                                                </div>

                                                <div class="detail-pair">
                                                    <span class="label"
                                                        ><i
                                                            class="fas fa-receipt"
                                                        ></i>
                                                        No. Split PO</span
                                                    >
                                                    <span class="value"
                                                        >{{
                                                            item.po_info
                                                                .No_Split_Po
                                                        }}
                                                    </span>
                                                </div>

                                                <div class="detail-pair">
                                                    <span class="label"
                                                        ><i
                                                            class="fas fa-hashtag"
                                                        ></i>
                                                        No. Batch</span
                                                    >
                                                    <span class="value"
                                                        >Batch
                                                        {{
                                                            item.po_info
                                                                ?.No_Batch ||
                                                            "-"
                                                        }}</span
                                                    >
                                                </div>

                                                <div class="detail-pair">
                                                    <span class="label"
                                                        ><i
                                                            class="fas fa-calendar-plus"
                                                        ></i>
                                                        Tgl Registrasi
                                                        Sampel</span
                                                    >
                                                    <span class="value"
                                                        >{{
                                                            formatTanggal(
                                                                item.Tanggal_Registrasi
                                                            )
                                                        }}
                                                        {{
                                                            item.Jam_Registrasi
                                                        }}</span
                                                    >
                                                </div>

                                                <div class="detail-pair">
                                                    <span class="label"
                                                        ><i
                                                            class="fas fa-flask"
                                                        ></i>
                                                        Tgl Pengujian</span
                                                    >
                                                    <span class="value"
                                                        >{{
                                                            formatTanggal(
                                                                item.Tanggal
                                                            )
                                                        }}
                                                        {{ item.Jam }}</span
                                                    >
                                                </div>

                                                <div class="detail-pair">
                                                    <span class="label"
                                                        ><i
                                                            class="fas fa-user"
                                                        ></i>
                                                        Analyzer</span
                                                    >
                                                    <span class="value">{{
                                                        item.Id_User || "-"
                                                    }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                </div>

                                <div
                                    class="row align-items-center mt-4"
                                    v-if="
                                        pagination.totalData > pagination.limit
                                    "
                                >
                                    <div class="col-sm">
                                        <div class="text-muted">
                                            Menampilkan
                                            <span class="fw-semibold">{{
                                                listData.length
                                            }}</span>
                                            dari
                                            <span class="fw-semibold">{{
                                                pagination.totalData
                                            }}</span>
                                            Data
                                        </div>
                                    </div>
                                    <div class="col-sm-auto mt-3 mt-sm-0">
                                        <ul
                                            class="pagination pagination-separated pagination-sm mb-0"
                                        >
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled:
                                                        pagination.page === 1,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="prevPage"
                                                    >←</a
                                                >
                                            </li>
                                            <li
                                                class="page-item"
                                                v-for="page in visiblePages"
                                                :key="page"
                                                :class="{
                                                    active:
                                                        page ===
                                                        pagination.page,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="
                                                        changePage(page)
                                                    "
                                                    >{{ page }}</a
                                                >
                                            </li>
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled:
                                                        pagination.page ===
                                                        pagination.totalPage,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="nextPage"
                                                    >→</a
                                                >
                                            </li>
                                        </ul>
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
                                <h5 class="text-muted mb-2">
                                    Data Tidak Ditemukan
                                </h5>
                                <p class="text-muted">{{ emptyMessage }}</p>
                                <button
                                    @click="resetFiltersAndFetch"
                                    class="btn btn-primary mt-3"
                                >
                                    <i class="fas fa-sync-alt me-1"></i> Muat
                                    Ulang & Reset Filter
                                </button>
                            </div>
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
import ConfirmedMultiQr from "../../../../components/ConfirmedMultiQrFormulator.vue";
import vSelect from "vue-select";
import { debounce } from "lodash";

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
        ConfirmedMultiQr,
        vSelect,
    },
    data() {
        return {
            listData: [], // listData akan berupa array
            loading: {
                loadingListData: false,
            },
            searchQuery: "",
            filters: {
                tanggal: {
                    mulai: "",
                    selesai: "",
                },
                qrcode: null,
            },
            filterOptions: {
                qrcode: [
                    { label: "Multi QRCode", value: "multi" },
                    { label: "Single QRCode", value: "single" },
                ],
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

        isFiltering() {
            return (
                this.searchQuery ||
                this.filters.tanggal.mulai ||
                this.filters.qrcode
            );
        },
        emptyMessage() {
            if (this.isFiltering) {
                return "Tidak ada data yang cocok dengan kriteria pencarian atau filter Anda.";
            }
            return "Saat ini belum ada data uji analisis yang tersedia.";
        },
    },
    methods: {
        statusClass(item) {
            const status = item.Status_Sampel.toLowerCase();
            if (status === "lolos uji") return "status-terima";
            if (status === "tidak lolos uji") return "status-tolak";
            return "";
        },

        statusIcon(item) {
            const status = item.Status_Sampel.toLowerCase();
            if (status === "lolos uji") return "fa-check-circle";
            if (status === "tidak lolos uji") return "fa-times-circle";
            return "fa-flask";
        },
        getBadgeClass(item) {
            const status = item.Status_Sampel?.toLowerCase() || "";
            if (status === "lolos uji") return "badge-success";
            if (status === "tidak lolos uji") return "badge-danger";
            return "badge-secondary"; // default
        },
        async fetchConfirmedUjiAnalisa(page = 1) {
            this.loading.loadingListData = true;
            try {
                const params = {
                    page: page,
                    q: this.searchQuery,
                    limit: this.pagination.limit,
                    qrcode: this.filters.qrcode
                        ? this.filters.qrcode.value
                        : null,
                };

                if (
                    this.filters.tanggal.mulai &&
                    this.filters.tanggal.selesai
                ) {
                    params.tanggal_mulai = this.filters.tanggal.mulai;
                    params.tanggal_selesai = this.filters.tanggal.selesai;
                }

                const response = await axios.get(
                    "/api/v1/formulator/validasi-hasil/uji-trial/current",
                    { params }
                );

                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result.data;
                    this.pagination = response.data.result.pagination;
                } else {
                    this.listData = [];
                    this.pagination.totalData = 0;
                }
            } catch (error) {
                console.error(
                    "Gagal mengambil data confirmed uji analisa:",
                    error
                );
                this.listData = [];
                this.pagination.totalData = 0;
            } finally {
                this.loading.loadingListData = false;
            }
        },

        // Fungsi debounce untuk memanggil fetch
        debouncedFetch: debounce(function () {
            this.pagination.page = 1;
            this.fetchConfirmedUjiAnalisa(1);
        }, 500),

        // Fungsi-fungsi untuk navigasi halaman
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchConfirmedUjiAnalisa(this.pagination.page + 1);
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchConfirmedUjiAnalisa(this.pagination.page - 1);
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchConfirmedUjiAnalisa(page);
            }
        },

        // Helper untuk format tanggal jika dibutuhkan di template
        formatTanggal(tanggalString) {
            if (!tanggalString) return "-";
            const date = new Date(tanggalString);
            const options = { day: "2-digit", month: "short", year: "numeric" };
            return date.toLocaleDateString("id-ID", options);
        },

        getDetailLink(item) {
            return item.Flag_Multi_QrCode === "Y"
                ? `/lab/confirmed-analisis/v2/${item.No_Po_Sampel}/multi`
                : `/lab/confirmed-analisis/v2/${item.No_Po_Sampel}/single-qrCode`;
        },
    },
    watch: {
        // Watcher untuk memantau perubahan pada global search
        searchQuery() {
            this.debouncedFetch();
        },
        // Watcher untuk memantau perubahan pada semua filter
        filters: {
            handler() {
                this.debouncedFetch();
            },
            deep: true,
        },
    },
    mounted() {
        this.fetchConfirmedUjiAnalisa();
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: #2563eb;
    --success: #16a34a;
    --danger: #dc2626;
    --warning: #d97706;
    --info: #0891b2;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-300: #d1d5db;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --gray-600: #4b5563;
    --gray-700: #374151;
    --gray-800: #1f2937;
    --gray-900: #111827;
    --border-radius: 12px;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.analisa-card {
    display: flex;
    background-color: white;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--gray-200);
    text-decoration: none;
    color: inherit;
    transition: all 0.2s ease;
    overflow: hidden;
}

.analisa-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Status border */
.status-lolos {
    border-left: 4px solid var(--success);
}

.status-tidak {
    border-left: 4px solid var(--danger);
}

.status-default {
    border-left: 4px solid var(--gray-400);
}

/* Status icon section */
.analisa-card-status {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    background-color: var(--gray-50);
}

.status-lolos .analisa-card-status {
    background-color: #f0fdf4;
    color: var(--success);
}

.status-tidak .analisa-card-status {
    background-color: #fef2f2;
    color: var(--danger);
}

.status-default .analisa-card-status {
    background-color: var(--gray-50);
    color: var(--gray-500);
}

.analisa-card-status i {
    font-size: 24px;
}

/* Main content */
.analisa-card-content {
    flex-grow: 1;
    padding: 20px;
}

/* Header section */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.title-group h6 {
    font-size: 14px;
    color: var(--gray-500);
    font-weight: 500;
    margin-bottom: 4px;
}

.title-group h5 {
    font-size: 18px;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 8px;
}

.subtitle-group {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.subtitle-kode {
    font-family: "Courier New", monospace;
    font-size: 13px;
    color: var(--gray-600);
    background-color: var(--gray-100);
    padding: 4px 8px;
    border-radius: 4px;
}

/* Badges */
.badge-custom {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 100px;
}

.badge-multi {
    background-color: #eff6ff;
    color: var(--primary);
}

.badge-single {
    background-color: var(--gray-100);
    color: var(--gray-600);
}

.badge-success {
    background-color: #dcfce7;
    color: var(--success);
}

.badge-danger {
    background-color: #fef2f2;
    color: var(--danger);
}

.badge-secondary {
    background-color: var(--gray-100);
    color: var(--gray-600);
}

/* Details grid */
.content-details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.detail-pair {
    display: flex;
    flex-direction: column;
}

.detail-pair .label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--gray-500);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

.detail-pair .label i {
    font-size: 14px;
    width: 16px;
}

.detail-pair .value {
    font-size: 14px;
    font-weight: 500;
    color: var(--gray-800);
    word-break: break-word;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .content-details {
        grid-template-columns: 1fr;
    }

    .analisa-card {
        flex-direction: column;
    }

    .analisa-card-status {
        width: 100%;
        padding: 12px;
        border-bottom: 1px solid var(--gray-200);
    }
}

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
</style>
