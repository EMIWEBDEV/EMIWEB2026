<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Master Serial RS232
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Konfigurasi Serial Port untuk Komunikasi
                        Perangkat
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-sm-auto">
                        <button
                            class="btn btn-primary"
                            type="button"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#offcanvasRight"
                            aria-controls="offcanvasRight"
                            @click="resetForm"
                        >
                            + Tambah Konfigurasi
                        </button>
                    </div>
                    <div class="col-sm">
                        <div class="d-flex justify-content-sm-end">
                            <div class="search-box ms-2">
                                <input
                                    type="search"
                                    class="form-control search"
                                    placeholder="Cari berdasarkan COM Target..."
                                    v-model="searchQuery"
                                />
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="offcanvas offcanvas-end"
                    tabindex="-1"
                    id="offcanvasRight"
                    aria-labelledby="offcanvasRightLabel"
                    ref="offcanvasRight"
                >
                    <div class="offcanvas-header">
                        <h5 id="offcanvasRightLabel" class="mb-0">
                            {{
                                isEdit
                                    ? "Edit Konfigurasi Serial"
                                    : "Tambah Konfigurasi Serial"
                            }}
                            <i class="fas fa-cogs ms-1"></i>
                        </h5>
                        <button
                            @click="resetForm()"
                            type="button"
                            class="btn-close text-reset"
                            data-bs-dismiss="offcanvas"
                            aria-label="Close"
                        ></button>
                    </div>
                    <div class="offcanvas-body">
                        <form @submit.prevent="submitForm">
                            <div class="mb-3">
                                <label
                                    for="com_target"
                                    class="form-label fw-semibold"
                                >
                                    COM Target
                                    <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="com_target"
                                    placeholder="Contoh: COM3"
                                    v-model="form.COM_TARGET"
                                    required
                                />
                            </div>

                            <div class="mb-3">
                                <label
                                    for="baud_rate"
                                    class="form-label fw-semibold"
                                >
                                    Baud Rate <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="com_target"
                                    placeholder="Contoh: 9600"
                                    v-model="form.BAUD_RATE"
                                    required
                                />
                            </div>

                            <div class="mb-3">
                                <label for="bit" class="form-label fw-semibold">
                                    Data Bits <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="com_target"
                                    placeholder="Contoh: 8"
                                    v-model="form.BIT"
                                    required
                                />
                            </div>
                            <div class="mb-3">
                                <label for="bit" class="form-label fw-semibold">
                                    Parity <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    class="form-control"
                                    id="com_target"
                                    placeholder="Contoh: Even"
                                    v-model="form.Parity"
                                    required
                                />
                            </div>
                            <div class="mb-3">
                                <label for="bit" class="form-label fw-semibold">
                                    stopBits <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="number"
                                    class="form-control"
                                    id="com_target"
                                    placeholder="Contoh: 1"
                                    v-model="form.Stop_Bits"
                                    required
                                />
                            </div>

                            <div class="mt-4">
                                <div class="d-grid">
                                    <button
                                        :disabled="loading.isSaving"
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-send-check me-2"></i>
                                        {{
                                            isEdit
                                                ? "Simpan Perubahan"
                                                : "Tambah Data"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div v-if="loading.isLoading">
                        <div class="table-wrapper">
                            <table
                                class="skeleton-table"
                                aria-busy="true"
                                aria-label="Loading data"
                            >
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>COM Target</th>
                                        <th>Baud Rate</th>
                                        <th>Data Bits</th>
                                        <th>Parity</th>
                                        <th>Stop Bits</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        class="skeleton-row"
                                        v-for="n in 5"
                                        :key="n"
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
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-else>
                        <div
                            v-if="paginatedData.length"
                            class="table-responsive"
                        >
                            <table
                                class="table table-bordered table-hover text-center align-middle"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>COM Target</th>
                                        <th>Baud Rate</th>
                                        <th>Data Bits</th>
                                        <th>Parity</th>
                                        <th>Stop Bits</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in paginatedData"
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
                                            <span class="badge bg-primary">{{
                                                item.COM_TARGET
                                            }}</span>
                                        </td>
                                        <td>{{ item.BAUD_RATE }}</td>
                                        <td>{{ item.BIT }}</td>
                                        <td>{{ item.Parity }}</td>
                                        <td>{{ item.Stop_Bits }}</td>
                                        <td>
                                            <button
                                                @click="editData(item)"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#offcanvasRight"
                                                class="btn btn-sm btn-warning me-2"
                                            >
                                                <i class="fas fa-edit me-1"></i>
                                                Edit
                                            </button>
                                            <button
                                                @click="deleteData(item.id)"
                                                class="btn btn-sm btn-danger"
                                            >
                                                <i
                                                    class="fas fa-trash me-1"
                                                ></i>
                                                Hapus
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="align-items-center mt-3 row g-3 text-center text-sm-start"
                            v-if="paginatedData.length > 0"
                        >
                            <div class="col-sm">
                                <div class="text-muted">
                                    Menampilkan
                                    <span class="fw-semibold">{{
                                        paginatedData.length
                                    }}</span>
                                    dari
                                    <span class="fw-semibold">{{
                                        pagination.totalData
                                    }}</span>
                                    hasil
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

                        <div
                            v-if="!paginatedData.length"
                            class="d-flex justify-content-center mt-4"
                        >
                            <div
                                class="flex-column align-content-center text-center"
                            >
                                <DotLottieVue
                                    style="height: 200px; width: 200px"
                                    autoplay
                                    loop
                                    src="/animation/empty.lottie"
                                />
                                <p class="mt-2 text-muted fw-bold">
                                    {{
                                        searchQuery
                                            ? "Data tidak ditemukan!"
                                            : "Belum ada data konfigurasi."
                                    }}
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
import { Offcanvas } from "bootstrap";
import { debounce } from "lodash";
import Swal from "sweetalert2";
import CryptoJS from "crypto-js";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";

const STORAGE_KEY = "SSID_serial_config";
const SECRET_KEY =
    import.meta.env.VITE_SECRET_KEY || "default-secret-key-12345";

export default {
    components: {
        DotLottieVue,
    },
    data() {
        return {
            serialData: [],
            filteredData: [],
            paginatedData: [],
            searchQuery: "",
            loading: {
                isLoading: false,
                isSaving: false,
            },
            pagination: {
                page: 1,
                limit: 5,
                totalPage: 0,
                totalData: 0,
            },
            form: {
                id: null,
                COM_TARGET: "",
                BAUD_RATE: "",
                Parity: "",
                BIT: "",
                Stop_Bits: "",
            },
            isEdit: false,
            offcanvasInstance: null,
        };
    },
    computed: {
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            if (total <= 5) {
                return Array.from({ length: total }, (_, i) => i + 1);
            }
            if (current <= 3) {
                return [1, 2, 3, 4, "...", total];
            }
            if (current >= total - 2) {
                return [1, "...", total - 3, total - 2, total - 1, total];
            }
            return [1, "...", current - 1, current, current + 1, "...", total];
        },
    },
    methods: {
        encryptData(data) {
            const stringData = JSON.stringify(data);
            return CryptoJS.AES.encrypt(stringData, SECRET_KEY).toString();
        },

        decryptData(encryptedData) {
            try {
                const bytes = CryptoJS.AES.decrypt(encryptedData, SECRET_KEY);
                const decryptedString = bytes.toString(CryptoJS.enc.Utf8);
                return JSON.parse(decryptedString);
            } catch (error) {
                console.error("Gagal mendekripsi data:", error);
                return null;
            }
        },

        loadDataFromLocalStorage() {
            this.loading.isLoading = true;
            const encryptedData = localStorage.getItem(STORAGE_KEY);
            if (encryptedData) {
                const decrypted = this.decryptData(encryptedData);
                this.serialData = decrypted || [];
            } else {
                this.serialData = [];
            }
            this.filterAndPaginateData();
            this.loading.isLoading = false;
        },

        saveDataToLocalStorage() {
            const encryptedData = this.encryptData(this.serialData);
            localStorage.setItem(STORAGE_KEY, encryptedData);
        },

        submitForm() {
            this.loading.isSaving = true;

            if (this.isEdit) {
                // Mode Edit
                const index = this.serialData.findIndex(
                    (item) => item.id === this.form.id
                );
                if (index !== -1) {
                    this.serialData[index] = { ...this.form };
                }
            } else {
                // Mode Tambah
                this.serialData.unshift({
                    ...this.form,
                    id: Date.now(), // Gunakan timestamp sebagai ID unik
                });
            }

            this.saveDataToLocalStorage();
            this.filterAndPaginateData();

            Swal.fire({
                icon: "success",
                title: "Berhasil!",
                text: `Data berhasil ${
                    this.isEdit ? "diperbarui" : "ditambahkan"
                }.`,
                timer: 1500,
                showConfirmButton: false,
            });

            this.offcanvasInstance.hide();
            this.resetForm();
            location.reload();
            this.loading.isSaving = false;
        },

        editData(item) {
            this.isEdit = true;
            this.form = { ...item };
        },

        deleteData(id) {
            Swal.fire({
                title: "Anda Yakin?",
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Ya, hapus!",
                cancelButtonText: "Batal",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.serialData = this.serialData.filter(
                        (item) => item.id !== id
                    );
                    this.saveDataToLocalStorage();
                    this.filterAndPaginateData();
                    Swal.fire(
                        "Terhapus!",
                        "Data konfigurasi telah dihapus.",
                        "success"
                    );
                }
            });
        },

        resetForm() {
            this.isEdit = false;
            this.form = {
                id: null,
                COM_TARGET: "",
                BAUD_RATE: "",
                BIT: "",
                Parity: "",
                Stop_Bits: "",
            };
        },

        filterAndPaginateData() {
            const query = this.searchQuery.toLowerCase();
            if (query) {
                this.filteredData = this.serialData.filter((item) =>
                    item.COM_TARGET.toLowerCase().includes(query)
                );
            } else {
                this.filteredData = [...this.serialData];
            }

            this.pagination.totalData = this.filteredData.length;
            this.pagination.totalPage = Math.ceil(
                this.filteredData.length / this.pagination.limit
            );
            if (this.pagination.page > this.pagination.totalPage) {
                this.pagination.page = this.pagination.totalPage || 1;
            }

            const start = (this.pagination.page - 1) * this.pagination.limit;
            const end = start + this.pagination.limit;
            this.paginatedData = this.filteredData.slice(start, end);
        },

        handleSearch: debounce(function () {
            this.pagination.page = 1;
            this.filterAndPaginateData();
        }, 300),

        changePage(page) {
            if (page === "...") return;
            this.pagination.page = page;
            this.filterAndPaginateData();
        },

        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.pagination.page++;
                this.filterAndPaginateData();
            }
        },

        prevPage() {
            if (this.pagination.page > 1) {
                this.pagination.page--;
                this.filterAndPaginateData();
            }
        },
    },
    watch: {
        searchQuery() {
            this.handleSearch();
        },
    },
    mounted() {
        this.loadDataFromLocalStorage();
        const offcanvasElement = this.$refs.offcanvasRight;
        this.offcanvasInstance = new Offcanvas(offcanvasElement);
    },
};
</script>

<style scoped>
/* Style untuk skeleton loading */
.skeleton-table {
    width: 100%;
}
.skeleton-cell {
    width: 100%;
    height: 20px;
    background-color: #e0e0e0;
    border-radius: 4px;
    animation: pulse 1.5s infinite ease-in-out;
}
.skeleton-row td {
    padding: 12px 8px;
}
@keyframes pulse {
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

.divider {
    border-top: 1px solid #dee2e6;
}
</style>
