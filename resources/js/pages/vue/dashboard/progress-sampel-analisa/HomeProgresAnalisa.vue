<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Status Data Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Status Data Analisa PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label for="search-input" class="form-label"
                            >Pencarian Umum</label
                        >
                        <input
                            type="search"
                            class="form-control"
                            id="search-input"
                            v-model="filters.search"
                            placeholder="Cari No PO, No Sampel, dll..."
                        />
                    </div>
                    <div class="col-md-3">
                        <label for="filter-status" class="form-label"
                            >Filter Status Analisa</label
                        >
                        <select
                            class="form-select"
                            id="filter-status"
                            v-model="filters.status"
                        >
                            <option value="">Semua Status</option>
                            <option
                                v-for="status in statusOptions"
                                :key="status"
                                :value="status"
                            >
                                {{ status }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filter-no-sampel" class="form-label"
                            >Filter No. Sampel</label
                        >
                        <input
                            type="search"
                            class="form-control"
                            id="filter-no-sampel"
                            v-model="filters.no_sampel"
                            placeholder="Masukkan No. Sampel..."
                        />
                    </div>
                    <div class="col-md-3">
                        <label for="filter-batch" class="form-label"
                            >Filter Batch</label
                        >
                        <input
                            type="search"
                            class="form-control"
                            id="filter-batch"
                            v-model="filters.batch"
                            placeholder="No. Batch..."
                        />
                    </div>
                    <div class="col-md-3">
                        <label for="filter-date-produksi" class="form-label"
                            >Tgl. PO Sampel (Produksi)</label
                        >
                        <input
                            type="date"
                            class="form-control"
                            id="filter-date-produksi"
                            v-model="filters.date"
                            :max="maxDate"
                        />
                    </div>

                    <div class="col-md-3">
                        <label for="filter-date-lab" class="form-label"
                            >Tgl. Uji (Laboratorium)</label
                        >
                        <input
                            type="date"
                            class="form-control"
                            id="filter-date-lab"
                            v-model="filters.tanggal_uji"
                            :max="maxDate"
                        />
                    </div>

                    <div class="col-md-3">
                        <label for="filter-pembatalan" class="form-label"
                            >Sampel Dibatalkan</label
                        >
                        <select
                            class="form-select"
                            id="filter-pembatalan"
                            v-model="filters.pembatalan"
                        >
                            <option value="">
                                Tidak Ditampilkan (Default)
                            </option>
                            <option value="tampilkan">
                                Tampilkan yang Dibatalkan
                            </option>
                        </select>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div v-if="loading.loadingDataList">
                        <div class="table-wrapper">
                            <table
                                class="skeleton-table"
                                aria-busy="true"
                                aria-label="Loading data"
                            >
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>No Po</th>
                                        <th>No Split</th>
                                        <th>Batch</th>
                                        <th>Kode Barang</th>
                                        <th>No Sampel</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="n in 5"
                                        :key="n"
                                        class="skeleton-row"
                                    >
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else>
                        <div
                            v-if="detailDataList.length"
                            class="table-responsive"
                        >
                            <table
                                class="table table-bordered text-center align-middle"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>No Po</th>
                                        <th>No Split</th>
                                        <th>Batch Ke</th>
                                        <th>Kode Barang</th>
                                        <th>No Sampel</th>
                                        <th>Nama Mesin</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in detailDataList"
                                        :key="item.id"
                                    >
                                        <td>
                                            {{
                                                (pagination.page - 1) *
                                                    pagination.limit +
                                                index +
                                                1
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                formatDate(item.Tanggal ?? "-")
                                            }}
                                        </td>
                                        <td>{{ item.No_Po ?? "-" }}</td>
                                        <td>{{ item.No_Split_Po ?? "-" }}</td>
                                        <td>
                                            {{ item.No_Batch ?? "-" }}
                                        </td>
                                        <td>{{ item.Kode_Barang ?? "-" }}</td>
                                        <td>{{ item.No_Sampel ?? "-" }}</td>
                                        <td>{{ item.Nama_Mesin ?? "-" }}</td>
                                        <td>
                                            <span
                                                class="status-badge"
                                                :class="
                                                    getStatusClass(item.Status)
                                                "
                                            >
                                                {{ item.Status }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            class="align-items-center mt-2 row g-3 text-center text-sm-start"
                            v-if="detailDataList.length"
                        >
                            <div class="col-sm">
                                <div class="text-muted">
                                    Total Data
                                    <span class="fw-semibold">{{
                                        pagination.totalData
                                    }}</span>
                                    Hasil
                                </div>
                            </div>
                            <div class="col-sm-auto">
                                <ul
                                    class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-start mb-0"
                                >
                                    <li
                                        class="page-item"
                                        :class="{
                                            disabled: pagination.page === 1,
                                        }"
                                    >
                                        <a
                                            href="#"
                                            class="page-link"
                                            @click="prevPage"
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
                                            @click="changePage(page)"
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
                                            @click="nextPage"
                                            >→</a
                                        >
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div
                            v-if="!detailDataList.length"
                            class="d-flex justify-content-center"
                        >
                            <div class="flex-column align-content-center">
                                <DotLottieVue
                                    style="height: 100px; width: 100px"
                                    autoplay
                                    loop
                                    src="/animation/empty2.json"
                                />
                                <p class="text-center">
                                    Data Tidak Ditemukan !
                                </p>
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
import { debounce } from "lodash";
import Swal from "sweetalert2";

export default {
    components: {
        DotLottieVue,
    },
    data() {
        return {
            isEdit: false,
            searchQuery: "",
            detailDataList: [],
            form: {
                Nama_Mesin: "",
                Keterangan: "",
            },
            loading: {
                loadingDataList: false,
                loadingSaveToDatabase: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            filters: {
                search: "",
                status: "",
                no_sampel: "",
                batch: "",
                date: "",
                tanggal_uji: "", // filter tanggal uji lab
                pembatalan: "", // filter sampel dibatalkan
            },
            // Opsi untuk dropdown status
            statusOptions: [
                "Selesai (Analisa Final dan Siap Digunakan)",
                "Menunggu Finalisasi Data Analisa",
                "Sedang Dalam Proses Re-Analisa (Resample)",
                "Dalam Proses (Sebagian masih diuji dan sebagian menunggu validasi)",
                "Belum Dimulai (Menunggu Uji Analisa)",
                "Status Konflik/Tidak Dikenali",
            ],
        };
    },
    computed: {
        maxDate() {
            // Mengembalikan tanggal hari ini dalam format YYYY-MM-DD yang dibutuhkan oleh <input type="date">
            return new Date().toISOString().slice(0, 10);
        },
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
        formatDate(dateString) {
            if (!dateString) return "-";
            const date = new Date(dateString);
            return date.toLocaleDateString("id-ID", {
                day: "numeric",
                month: "long",
                year: "numeric",
            });
        },
        getStatusClass(status) {
            if (!status) return "badge-konflik";

            switch (status) {
                case "Selesai (Analisa Final dan Siap Digunakan)":
                    return "badge-selesai";
                case "Menunggu Finalisasi Data Analisa":
                    return "badge-proses";
                case "Sedang Dalam Proses Re-Analisa (Resample)":
                    return "badge-reanalisa";
                case "Dalam Proses (Sebagian masih diuji dan sebagian menunggu validasi)":
                    return "badge-proses";
                case "Belum Dimulai (Menunggu Uji Analisa)":
                    return "badge-belum-dimulai";
                case "Status Konflik/Tidak Dikenali":
                default:
                    return "badge-konflik";
            }
        },
        async saveToDatabase() {
            this.loading.loadingSaveToDatabase = true;
            if (this.isEdit) {
                try {
                    if (!this.form.Nama_Mesin) {
                        Swal.fire({
                            icon: "warning",
                            title: "Opss",
                            text: "Semua Form Wajib Di Isi Semua",
                        });
                        return;
                    }

                    const response = await axios.put(
                        `/api/v1/mesin-analisa/update/${this.form.id}`,
                        this.form
                    );

                    if (response.status === 200 && response.data) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: response.data.message,
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Gagal",
                            text: "Gagal menyimpan data.",
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: "error",
                        title: "Opss...",
                        text:
                            error.response.data.message ||
                            error.message ||
                            "Terjadi Kesalahan",
                    });
                } finally {
                    this.loading.loadingSaveToDatabase = false;
                }
            } else {
                try {
                    if (!this.form.Nama_Mesin) {
                        Swal.fire({
                            icon: "warning",
                            title: "Opss",
                            text: "Semua Form Wajib Di Isi Semua",
                        });
                        return;
                    }
                    const payload = {
                        Nama_Mesin: this.form.Nama_Mesin,
                        Keterangan: this.form.Keterangan,
                    };
                    const response = await axios.post(
                        "/api/v1/mesin-analisa/store",
                        payload
                    );

                    if (response.status === 201 && response.data) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Data berhasil disimpan!",
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Gagal",
                            text: "Gagal menyimpan data.",
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: "error",
                        title: "Opss...",
                        text:
                            error.response.data.message ||
                            error.message ||
                            "Terjadi Kesalahan",
                    });
                } finally {
                    this.loading.loadingSaveToDatabase = false;
                }
            }
        },
        async fetchData(page = 1) {
            this.loading.loadingDataList = true;
            try {
                // [MODIFIKASI] Menambahkan parameter baru ke API call
                const params = {
                    page,
                    limit: this.pagination.limit,
                    search: this.filters.search,
                    status: this.filters.status,
                    no_sampel: this.filters.no_sampel,
                    batch: this.filters.batch,
                    date: this.filters.date,
                    filter_tanggal_uji: this.filters.tanggal_uji, // parameter baru
                    filter_pembatalan: this.filters.pembatalan, // parameter baru
                };

                // Membersihkan parameter yang kosong
                Object.keys(params).forEach(
                    (key) =>
                        (params[key] === null || params[key] === "") &&
                        delete params[key]
                );

                const response = await axios.get(
                    `/api/v1/progress-sistem/uji-analisa/current`,
                    { params }
                );

                if (response.status === 200 && response.data?.result) {
                    this.detailDataList = response.data.result;
                    this.pagination.page = response.data.page;
                    this.pagination.totalPage = response.data.total_page;
                    this.pagination.totalData = response.data.total_data;
                } else {
                    this.detailDataList = [];
                    this.pagination.totalData = 0;
                    this.pagination.totalPage = 0;
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                Swal.fire({
                    icon: "error",
                    title: "Gagal Memuat Data",
                    text:
                        error.response?.data?.message ||
                        "Terjadi kesalahan pada server.",
                });
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },

        handleFilterChange: debounce(function () {
            this.fetchData(1);
        }, 500), // delay 500ms

        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchData(this.pagination.page + 1);
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchData(this.pagination.page - 1);
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchData(page);
            }
        },
    },
    watch: {
        filters: {
            handler() {
                this.handleFilterChange();
            },
            deep: true, // Wajib untuk mendeteksi perubahan di dalam objek
        },
    },
    mounted() {
        this.fetchData();
    },
};
</script>
<style scoped>
/* Base Styles */
.app-container {
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
    min-height: 100vh;
    padding: 2rem;
}

/* Card Styles */
.glass-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.18);
}

/* Header Section */
.header-section {
    position: relative;
    padding-bottom: 1rem;
}

.gradient-text {
    background: linear-gradient(90deg, #3b82f6 0%, #8b5cf6 50%, #ec4899 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    display: inline-block;
}

.divider {
    height: 3px;
    background: linear-gradient(
        90deg,
        rgba(59, 130, 246, 0) 0%,
        rgba(59, 130, 246, 0.6) 50%,
        rgba(59, 130, 246, 0) 100%
    );
    border-radius: 3px;
}

/* Filter Section */
.filter-section {
    margin-bottom: 1.5rem;
}

.form-floating {
    position: relative;
}

.floating-input,
.floating-select {
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    padding: 1rem 0.75rem;
    transition: all 0.3s ease;
    background-color: rgba(255, 255, 255, 0.8);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.floating-input:focus,
.floating-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
    outline: none;
}

.floating-label {
    color: #64748b;
    font-weight: 500;
    padding: 0.5rem 0.75rem;
    display: flex;
    align-items: center;
}

/* Table Styles */
.modern-table {
    --bs-table-bg: transparent;
    --bs-table-striped-bg: rgba(241, 245, 249, 0.5);
    --bs-table-hover-bg: rgba(226, 232, 240, 0.5);
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.table-header {
    background: linear-gradient(90deg, #3b82f6 0%, #6366f1 100%);
    color: white;
}

.table-header th {
    padding: 1rem;
    font-weight: 600;
    text-align: center;
    vertical-align: middle;
    border: none;
}

.table-header th:first-child {
    border-top-left-radius: 12px;
    border-bottom-left-radius: 12px;
}

.table-header th:last-child {
    border-top-right-radius: 12px;
    border-bottom-right-radius: 12px;
}

.table-row {
    transition: all 0.2s ease;
    cursor: pointer;
    border-radius: 8px;
}

.table-row td {
    padding: 1rem;
    text-align: center;
    vertical-align: middle;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    background-color: white;
}

.table-row:hover td {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5em 1em;
    font-size: 0.85rem;
    font-weight: 600;
    line-height: 1.2;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 50px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.status-icon {
    margin-right: 0.5em;
    font-size: 0.9em;
}

/* Status Colors */
.badge-selesai {
    background-color: #10b981;
    color: white;
}
.badge-selesai .status-icon {
    color: #d1fae5;
}

.badge-proses {
    background-color: #3b82f6;
    color: white;
}
.badge-proses .status-icon {
    color: #bfdbfe;
}

.badge-reanalisa {
    background-color: #f97316;
    color: white;
}
.badge-reanalisa .status-icon {
    color: #fed7aa;
}

.badge-menunggu {
    background-color: #f59e0b;
    color: white;
}
.badge-menunggu .status-icon {
    color: #fef3c7;
}

.badge-belum-dimulai {
    background-color: #64748b;
    color: white;
}
.badge-belum-dimulai .status-icon {
    color: #e2e8f0;
}

.badge-konflik {
    background-color: #ef4444;
    color: white;
}
.badge-konflik .status-icon {
    color: #fee2e2;
}

/* Pagination */
.pagination-container {
    display: flex;
    gap: 0.5rem;
}

.pagination-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background-color: white;
    color: #64748b;
    font-weight: 600;
    transition: all 0.2s ease;
    cursor: pointer;
}

.pagination-button:hover:not(.disabled) {
    background-color: #f1f5f9;
    color: #3b82f6;
    border-color: #bfdbfe;
}

.pagination-button.active {
    background-color: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.pagination-button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Empty State */
.empty-state {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 400px;
    background-color: rgba(241, 245, 249, 0.5);
    border-radius: 16px;
    padding: 2rem;
}

.empty-content {
    text-align: center;
    max-width: 400px;
}

/* Skeleton Loading */
.skeleton-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 0.5rem;
}

.skeleton-table thead th {
    padding: 1rem;
    text-align: center;
    background-color: #f1f5f9;
    color: #64748b;
    font-weight: 600;
    border-radius: 8px;
}

.skeleton-row td {
    padding: 1rem;
    background-color: white;
    border-radius: 8px;
}

.skeleton-cell {
    position: relative;
    height: 20px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.skeleton-cell::after {
    content: "";
    position: absolute;
    top: 0;
    left: -150px;
    width: 150px;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.6),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    100% {
        left: 100%;
    }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .app-container {
        padding: 1rem;
    }

    .glass-card {
        border-radius: 12px;
    }

    .table-header th {
        padding: 0.75rem;
        font-size: 0.85rem;
    }

    .table-row td {
        padding: 0.75rem;
        font-size: 0.85rem;
    }

    .status-badge {
        font-size: 0.75rem;
        padding: 0.4em 0.8em;
    }
}

@media (max-width: 576px) {
    .pagination-container {
        gap: 0.25rem;
    }

    .pagination-button {
        width: 35px;
        height: 35px;
        font-size: 0.85rem;
    }
}
</style>
