<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Master Akun
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Akun Pengguna Untuk LAB PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-sm-auto">
                        <a
                            href="/master-akun/tambah-akun"
                            class="btn btn-primary"
                            type="button"
                        >
                            + Tambah Akun
                        </a>
                    </div>
                    <div class="col-sm">
                        <div class="d-flex justify-content-sm-end">
                            <div class="search-box ms-2">
                                <input
                                    v-if="masterAkunList.length"
                                    type="search"
                                    class="form-control search"
                                    placeholder="Search..."
                                    v-model="searchQuery"
                                    @input="handleSearch"
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
                >
                    <div class="offcanvas-header">
                        <h5 id="offcanvasRightLabel" class="mb-0">
                            Edit Akun Pengguna
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
                        <form @submit.prevent="submitFormToDatabase">
                            <div class="mb-3">
                                <label
                                    for="Nama"
                                    class="form-label fw-semibold"
                                >
                                    Username<span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="Username"
                                    id="Username"
                                    class="form-control"
                                    placeholder="Masukkan Username"
                                    v-model="form.UserId"
                                />
                            </div>
                            <div class="mb-3">
                                <label
                                    for="Nama"
                                    class="form-label fw-semibold"
                                >
                                    Nama Lengkap<span class="text-danger"
                                        >*</span
                                    >
                                </label>
                                <input
                                    type="text"
                                    name="nama_lengkap"
                                    id="nama_lengkap"
                                    class="form-control"
                                    placeholder="Masukkan Nama Lengkap"
                                    v-model="form.Nama"
                                />
                            </div>
                            <div class="mb-3">
                                <label
                                    for="Nama"
                                    class="form-label fw-semibold"
                                >
                                    Password<span class="text-danger">*</span>
                                </label>
                                <div
                                    class="position-relative auth-pass-inputgroup mb-3"
                                >
                                    <input
                                        :type="
                                            isPasswordVisible
                                                ? 'text'
                                                : 'password'
                                        "
                                        class="form-control pe-5 password-input"
                                        placeholder="Enter password"
                                        id="password-input"
                                        v-model="form.Password"
                                    />
                                    <button
                                        class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none"
                                        type="button"
                                        @click="togglePasswordVisibility"
                                    >
                                        <i
                                            :class="
                                                isPasswordVisible
                                                    ? 'ri-eye-off-fill'
                                                    : 'ri-eye-fill'
                                            "
                                            class="align-middle"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label
                                    for="pin-input"
                                    class="form-label fw-semibold"
                                >
                                    Pin<span class="text-danger">*</span>
                                </label>
                                <div
                                    class="alert alert-danger alert-dismissible alert-label-icon label-arrow fade show material-shadow"
                                    role="alert"
                                >
                                    <i
                                        class="ri-error-warning-line label-icon"
                                    ></i
                                    ><strong>Perhatikan !</strong> - PIN Wajib 6
                                    Digit Angka
                                </div>
                                <div
                                    class="position-relative auth-pass-inputgroup mb-3"
                                >
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        pattern="\d*"
                                        maxlength="6"
                                        class="form-control pe-5 password-input"
                                        placeholder="Masukkan PIN 6 Digit"
                                        id="pin-input"
                                        @keypress="checkDigit"
                                        v-model="form.Pin"
                                    />
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    Edit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div v-if="loading.masterAkunCurrent">
                        <div class="table-wrapper">
                            <table
                                class="skeleton-table"
                                aria-busy="true"
                                aria-label="Loading data"
                            >
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Username</th>
                                        <th>Nama Lengkap</th>
                                        <th>Status Akun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        class="skeleton-row"
                                        v-for="(item, index) in 10"
                                        :key="index"
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
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else>
                        <div
                            v-if="masterAkunList.length"
                            class="table-responsive"
                        >
                            <table
                                class="table table-bordered text-center align-middle"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Username</th>
                                        <th>Nama Lengkap</th>
                                        <th>Status Akun</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in masterAkunList"
                                        :key="index"
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
                                            {{ item.UserId }}
                                        </td>
                                        <td>{{ item.Nama }}</td>
                                        <td class="text-center align-middle">
                                            <div
                                                class="d-flex justify-content-center"
                                            >
                                                <div
                                                    class="form-check form-switch form-switch-success"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        role="switch"
                                                        :id="`SwitchCheck3-${index}`"
                                                        :checked="
                                                            item.Flag_Aktif ===
                                                            'Y'
                                                        "
                                                        @change="
                                                            toggleStatusAkun(
                                                                item.UserId
                                                            )
                                                        "
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <button
                                                class="btn btn-warning"
                                                @click="editData(item)"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#offcanvasRight"
                                                aria-controls="offcanvasRight"
                                            >
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-if="masterAkunList.length"
                            class="align-items-center mt-2 row g-3 text-center text-sm-start"
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
                                    <!-- Prev Button -->
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
                                    <!-- Page Numbers -->
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
                                    <!-- Next Button -->
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
                            v-if="!masterAkunList.length"
                            class="d-flex justify-content-center"
                        >
                            <div class="flex-column align-content-center">
                                <DotLottieVue
                                    style="height: 200px; width: 200px"
                                    autoplay
                                    loop
                                    src="/animation/empty.lottie"
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
    props: {
        item: Object,
        index: Number,
    },
    data() {
        return {
            isKeyVisible: {},
            masterAkunList: [],
            searchQuery: "",
            loading: {
                masterAkunCurrent: false,
                loadingSaveToDatabase: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            form: {
                Nama: "",
                UserId: "",
                Password: "",
                Pin: "",
            },
            isPasswordVisible: false,
        };
    },

    methods: {
        async fetchMasterAkun(page = 1, query = "") {
            this.loading.masterAkunCurrent = true;
            try {
                if (query) {
                    const response = await axios.get(
                        "/api/v1/master-akun/search",
                        {
                            params: { q: query },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.masterAkunList = response.data.result;
                        this.pagination.totalPage = 1;
                        this.pagination.totalData = this.masterAkunList.length;
                    } else {
                        this.masterAkunList = [];
                    }
                } else {
                    const response = await axios.get(
                        "/api/v1/master-akun/current",
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
                            },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.masterAkunList = response.data.result;
                        this.pagination.page = page;
                        this.pagination.totalPage = response.data.total_page;
                        this.pagination.totalData = response.data.total_data;
                    } else {
                        this.masterAkunList = [];
                    }
                }
            } catch (error) {
                this.masterAkunList = [];
            } finally {
                this.loading.masterAkunCurrent = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchMasterAkun(this.pagination.page, this.searchQuery);
        }, 500),
        async toggleStatusAkun(UserId) {
            try {
                const response = await axios.post(
                    `/api/v1/master-akun/status-akun/${UserId}`
                );
                if (response.status === 200 && response.data.success) {
                    Swal.fire({
                        icon: "success",
                        title: response.data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    this.fetchMasterAkun(
                        this.pagination.page,
                        this.searchQuery
                    );
                }
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal Update Status",
                    text: error.response?.data?.message || "Terjadi kesalahan.",
                });
            }
        },
        async submitFormToDatabase() {
            if (!this.form.Nama || !this.form.Password || !this.form.Pin) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Semua field wajib diisi.",
                });
                return;
            }

            if (this.form.Pin.toString().length !== 6) {
                Swal.fire({
                    icon: "warning",
                    title: "PIN Tidak Valid",
                    text: "PIN harus terdiri dari 6 digit angka.",
                });
                return;
            }

            this.loading.loadingSaveToDatabase = true;

            try {
                const payload = {
                    UserId: this.form.UserId,
                    Nama: this.form.Nama,
                    Password: this.form.Password,
                    Pin: this.form.Pin,
                };

                const response = await axios.put(
                    "/proses-update/akun",
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (response.status === 200 && response.data) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: response.data.message,
                    }).then(() => {
                        window.location.href = "/master-akun";
                    });
                } else {
                    throw new Error(
                        response.data.message ||
                            response.data.message?.error ||
                            "Gagal menyimpan data."
                    );
                }
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text:
                        error.response.data?.message ||
                        error.response.data?.message?.error ||
                        "Terjadi Kesalahan",
                });
            } finally {
                this.loading.loadingSaveToDatabase = false;
            }
        },
        editData(item) {
            this.form = {
                Nama: item.Nama,
                UserId: item.UserId,
                Password: "",
                Pin: "",
            };
        },
        resetForm() {
            this.form = {
                Nama: "",
                UserId: "",
                Password: "",
                Pin: "",
            };
        },
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchMasterAkun(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchMasterAkun(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchMasterAkun(page, this.searchQuery);
            }
        },
        togglePasswordVisibility() {
            this.isPasswordVisible = !this.isPasswordVisible;
        },

        checkDigit(event) {
            const input = event.target;
            const key = event.key;

            if (key.length === 1 && !/^\d$/.test(key)) {
                event.preventDefault();
                return;
            }

            if (input.value.length >= 6) {
                event.preventDefault();
                return;
            }
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        this.fetchMasterAkun();
    },
};
</script>

<style>
.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table.skeleton-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

/* Header dengan border */
.skeleton-table thead th {
    padding: 12px;
    text-align: left;
    border: 1px solid #ccc;
    background-color: #f9f9f9;
}

/* Skeleton baris data */
.skeleton-row .skeleton-cell {
    position: relative;
    height: 40px;
    background: #e0e0e0;
    border-radius: 6px;
    margin: 6px 0;
    overflow: hidden;
}

/* Efek shimmer */
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
        rgba(255, 255, 255, 0.4),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    100% {
        left: 100%;
    }
}

/* Responsive height adjustment */
@media (max-width: 600px) {
    .skeleton-cell {
        height: 30px;
    }
}
</style>
