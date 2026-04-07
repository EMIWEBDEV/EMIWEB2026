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
                                Kumpulan Data Hasil Trial
                            </h1>
                            <p class="text-muted mb-0 subtitle">
                                <i class="fas fa-building me-1"></i>
                                Koleksi lengkap data analisis laboratorium PT.
                                Evo Manufacturing Indonesia
                            </p>
                        </div>
                    </div>
                    <div class="divider bg-primary opacity-25 my-3"></div>
                </div>

                <ListSkeleton :page="5" v-if="loading.loadingListData" />

                <div class="col-12 mt-3 content-area" v-else>
                    <div class="card card-body mb-4 filter-card">
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
                                        placeholder="Cari No. PO, Split PO, Batch, Mesin..."
                                        v-model="searchQuery"
                                    />
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label small"
                                    >Dari Tgl Pengujian</label
                                >
                                <input
                                    type="date"
                                    class="form-control"
                                    v-model="filters.tanggal.mulai"
                                />
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label small"
                                    >Sampai Tgl Pengujian</label
                                >
                                <input
                                    type="date"
                                    class="form-control"
                                    v-model="filters.tanggal.selesai"
                                />
                            </div>
                            <div class="col-lg-2 col-md-6">
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
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label small">Status</label>
                                <v-select
                                    :options="filterOptions.status"
                                    placeholder="Semua Status"
                                    v-model="filters.status"
                                    :clearable="true"
                                ></v-select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3">
                        <h6 class="mb-0 flex-grow-1 fw-semibold text-primary">
                            <i class="fas fa-list-check me-2"></i>Daftar Nomor
                            PO Sampel
                        </h6>
                    </div>

                    <div v-if="Object.keys(listData).length > 0">
                        <a
                            v-for="[kode, item] in Object.entries(listData)"
                            :key="kode"
                            :href="`/formulator/hasil-trial/${id_jenis_analisa}/${kode}/${item.flag_multi}`"
                            class="data-card"
                        >
                            <div class="card-header">
                                <div class="card-icon-wrapper">
                                    <i class="fas fa-vial"></i>
                                </div>
                                <div class="card-title">
                                    <h6 class="mb-0">{{ item.nama_barang }}</h6>
                                    <div>
                                        <small class="text-muted">{{
                                            kode
                                        }}</small>

                                        <span
                                            v-if="item.flag_multi === 'Y'"
                                            class="badge rounded-pill bg-primary fw-normal ms-2"
                                        >
                                            <i class="fas fa-clone me-1"></i>
                                            Multi QRCode
                                        </span>

                                        <span
                                            v-else
                                            class="badge rounded-pill bg-secondary fw-normal ms-2"
                                        >
                                            <i class="fas fa-qrcode me-1"></i>
                                            Single QRCode
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="item.status_keputusan"
                                    class="status-badge"
                                    :class="{
                                        'badge-success':
                                            item.status_keputusan.toLowerCase() ===
                                            'terima',
                                        'badge-danger':
                                            item.status_keputusan.toLowerCase() ===
                                            'tolak',
                                    }"
                                >
                                    <i
                                        class="fas"
                                        :class="{
                                            'fa-check-circle':
                                                item.status_keputusan.toLowerCase() ===
                                                'terima',
                                            'fa-times-circle':
                                                item.status_keputusan.toLowerCase() ===
                                                'tolak',
                                        }"
                                    ></i>
                                    <span>{{ item.status_keputusan }}</span>
                                </div>
                            </div>

                            <div class="card-details">
                                <div class="detail-item">
                                    <i class="icon fas fa-cogs"></i>
                                    <div>
                                        <div class="label">Nama Mesin</div>
                                        <div class="value">
                                            {{ item.nama_mesin || "-" }}
                                        </div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="icon fas fa-receipt"></i>
                                    <div>
                                        <div class="label">No. PO / Split</div>
                                        <div class="value">
                                            {{ item.no_po || "-" }}
                                            <span v-if="item.no_split_po"
                                                >/ {{ item.no_split_po }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="icon fas fa-hashtag"></i>
                                    <div>
                                        <div class="label">No. Batch</div>
                                        <div class="value">
                                            {{ item.no_batch || "-" }}
                                        </div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="icon fas fa-calendar-plus"></i>
                                    <div>
                                        <div class="label">Tgl Registrasi</div>
                                        <div class="value">
                                            {{
                                                formatDateTime(
                                                    item.tanggal_pengajuan,
                                                    item.jam_pengajuan
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <i class="icon fas fa-calendar-check"></i>
                                    <div>
                                        <div class="label">Tgl Pengujian</div>
                                        <div class="value">
                                            {{
                                                formatDateTime(
                                                    item.tanggal_pengujian,
                                                    item.jam_pengujian
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <div v-else class="text-center py-5 empty-state">
                        <div
                            class="d-flex justify-content-center mb-3 empty-animation"
                        >
                            <DotLottieVue
                                style="height: 200px; width: 200px"
                                autoplay
                                loop
                                src="/animation/empty.lottie"
                            />
                        </div>
                        <h5 class="text-muted mb-2 empty-title">
                            Data Tidak Ditemukan
                        </h5>
                        <p class="text-muted empty-message">
                            Tidak ada data hasil analisis yang tersedia saat ini
                            atau sesuai pencarian Anda.
                        </p>
                    </div>

                    <div
                        class="row align-items-center mt-4"
                        v-if="pagination.totalData > 0"
                    >
                        <div class="col-sm">
                            <div class="text-muted">
                                Menampilkan
                                <span class="fw-semibold">{{
                                    Object.keys(listData).length
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
                                class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-start mb-0"
                            >
                                <li
                                    class="page-item"
                                    :class="{ disabled: pagination.page === 1 }"
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
                                        active: page === pagination.page,
                                    }"
                                >
                                    <a
                                        href="#"
                                        class="page-link"
                                        @click.prevent="changePage(page)"
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
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import ListSkeleton from "@/pages/vue/ui/ListSkeleton.vue";
import { debounce } from "lodash";
import vSelect from "vue-select";

export default {
    props: {
        id_jenis_analisa: {
            type: [String, Number],
            default: null,
        },
    },
    components: {
        DotLottieVue,
        ListSkeleton,
        vSelect,
    },
    data() {
        return {
            listData: {},
            loading: {
                loadingListData: false,
                saveToDatabase: false,
            },
            isSubmitDone: false,
            no_po_sampel: null,
            searchQuery: "",
            filters: {
                tanggal: {
                    mulai: "",
                    selesai: "",
                },
                qrcode: null,
                status: { label: "Diterima", value: "terima" },
            },
            filterOptions: {
                // Hapus `mesin: []` dari sini
                qrcode: [
                    { label: "Multi QRCode", value: "multi" },
                    { label: "Single QRCode", value: "single" },
                ],
                status: [
                    { label: "Semua Status", value: null },
                    { label: "Diterima", value: "terima" },
                    { label: "Ditolak", value: "tolak" },
                    { label: "Dibatalkan", value: "dibatalkan" },
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
    },
    methods: {
        async fetchHasilAnalisaByJenisAnalisa(page = 1) {
            this.loading.loadingListData = true;
            try {
                const params = {
                    page: page,
                    q: this.searchQuery,
                    limit: this.pagination.limit,
                    qrcode: this.filters.qrcode
                        ? this.filters.qrcode.value
                        : null,
                    status: this.filters.status
                        ? this.filters.status.value
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
                    `/api/v1/formulator/hasil-trial/${this.id_jenis_analisa}`,
                    { params }
                );

                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result.data_sampel;
                    this.pagination = {
                        ...this.pagination,
                        ...response.data.result.pagination,
                    };
                } else {
                    this.listData = {};
                    this.pagination.totalData = 0;
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                this.listData = {};
                this.pagination.totalData = 0;
            } finally {
                this.loading.loadingListData = false;
            }
        },
        // Fungsi debounce untuk membatasi panggilan API saat mengetik
        debouncedFetch: debounce(function () {
            this.pagination.page = 1;
            this.fetchHasilAnalisaByJenisAnalisa(1);
        }, 500),

        // Fungsi Pagination
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchHasilAnalisaByJenisAnalisa(this.pagination.page + 1);
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchHasilAnalisaByJenisAnalisa(this.pagination.page - 1);
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchHasilAnalisaByJenisAnalisa(page);
            }
        },
        formatDateTime(dateStr, timeStr) {
            if (!dateStr || !timeStr) return "-";

            const date = new Date(`${dateStr.split(" ")[0]}T${timeStr}`);
            const options = {
                day: "2-digit",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                hour12: false,
            };
            return new Intl.DateTimeFormat("id-ID", options)
                .format(date)
                .replace(".", ":");
        },
    },
    watch: {
        // Satu watcher untuk semua filter, termasuk pencarian
        searchQuery() {
            this.debouncedFetch();
        },
        filters: {
            handler() {
                this.debouncedFetch();
            },
            deep: true, // Ini penting agar watcher mendeteksi perubahan di dalam object
        },
    },
    mounted() {
        this.fetchHasilAnalisaByJenisAnalisa();
    },
    mounted() {
        this.fetchHasilAnalisaByJenisAnalisa();
    },
};
</script>

<style scoped>
/* baru */
.vs__dropdown-toggle {
    border-color: #ced4da !important;
    border-radius: 0.375rem !important;
}

.vs--open .vs__dropdown-toggle {
    border-color: #86b7fe !important;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
}

.filter-card .form-label {
    margin-bottom: 0.25rem;
    color: #6c757d;
}

.data-card {
    background-color: #ffffff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    margin-bottom: 1rem;
    padding: 1rem 1.25rem;
    text-decoration: none;
    color: inherit;
    display: block;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.data-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.08);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f1f3f5;
}

.card-icon-wrapper {
    background-color: rgba(13, 110, 253, 0.1); /* Warna primary transparan */
    color: #0d6efd; /* Warna primary */
    min-width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.card-title {
    flex-grow: 1;
}

.card-title h6 {
    font-weight: 600;
    color: #212529;
}

.card-title small {
    color: #6c757d;
    font-size: 0.85rem;
}

.status-badge {
    padding: 0.4em 0.8em;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.status-badge.badge-success {
    background-color: rgba(25, 135, 84, 0.15); /* Warna success transparan */
    color: #198754; /* Warna success */
}

.status-badge.badge-danger {
    background-color: rgba(220, 53, 69, 0.15); /* Warna danger transparan */
    color: #dc3545; /* Warna danger */
}

.card-details {
    padding-top: 1rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.85rem;
}

.detail-item .icon {
    color: #adb5bd;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

.detail-item .label {
    color: #6c757d;
    font-size: 0.8rem;
}

.detail-item .value {
    color: #212529;
    font-weight: 600;
}
/* end baru */
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
</style>
