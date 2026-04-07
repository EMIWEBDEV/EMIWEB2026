<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Master Komputer Key
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Identity Key Komputer PT. Evo Manufacturing
                        Indonesia
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
                        >
                            + Tambah Identity Komputer
                        </button>
                    </div>
                    <div class="col-sm">
                        <div class="d-flex justify-content-sm-end">
                            <div class="search-box ms-2">
                                <input
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

                <!-- offcanvas -->
                <div
                    class="offcanvas offcanvas-end"
                    tabindex="-1"
                    id="offcanvasRight"
                    aria-labelledby="offcanvasRightLabel"
                >
                    <div class="offcanvas-header">
                        <h5 id="offcanvasRightLabel" class="mb-0">
                            {{
                                isEdit
                                    ? "Edit Daftar Key Identity Komputer"
                                    : "Penambahan Daftar Key Identity Komputer"
                            }}
                            <i class="fas fa-desktop"></i>
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
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label
                                        for="Computer_Keys"
                                        class="form-label fw-semibold"
                                    >
                                        Key Komputer
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            id="Computer_Keys"
                                            class="form-control"
                                            placeholder="Tekan Generate untuk membuat key"
                                            v-model="form.Computer_Keys"
                                            readonly
                                            required
                                        />
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary"
                                            @click="generateKey"
                                        >
                                            Generate Key
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label
                                        for="Keterangan"
                                        class="form-label fw-semibold"
                                    >
                                        Keterangan
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="Keterangan"
                                        placeholder="Contoh: Komputer A"
                                        v-model="form.Keterangan"
                                    />
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-grid">
                                    <button
                                        :disabled="
                                            loading.identitySaveToDatabase
                                        "
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-send-check me-2"></i>
                                        {{
                                            isEdit ? "Edit Form" : "Submit Form"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div v-if="loading.identityLoading">
                        <div class="table-wrapper">
                            <table
                                class="skeleton-table"
                                aria-busy="true"
                                aria-label="Loading data"
                            >
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Komputer Key</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- 5 Baris Skeleton -->
                                    <tr class="skeleton-row">
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
                                    <tr class="skeleton-row">
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
                                    <tr class="skeleton-row">
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
                                    <tr class="skeleton-row">
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
                                    <tr class="skeleton-row">
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
                            v-if="identityKomputer.length"
                            class="table-responsive"
                        >
                            <table
                                class="table table-bordered text-center align-middle"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Komputer Key</th>
                                        <th>Keterangan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            item, index
                                        ) in identityKomputer"
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
                                            <span
                                                v-if="item.Computer_Keys"
                                                class="badge bg-secondary"
                                            >
                                                {{
                                                    isKeyVisible[index]
                                                        ? item.Computer_Keys
                                                        : "••••••••"
                                                }}
                                            </span>
                                            <button
                                                v-if="item.Computer_Keys"
                                                type="button"
                                                class="btn btn-sm btn-outline-primary ms-2"
                                                @click="toggleKey(index)"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <span v-else class="text-muted"
                                                >-</span
                                            >
                                        </td>

                                        <td>{{ item.Keterangan }}</td>
                                        <td>
                                            <button
                                                @click="editData(item)"
                                                data-bs-toggle="offcanvas"
                                                data-bs-target="#offcanvasRight"
                                                aria-controls="offcanvasRight"
                                                class="btn btn-warning"
                                            >
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
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
                            v-if="!identityKomputer.length"
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
            identityKomputer: [],
            searchQuery: "",
            loading: {
                identityLoading: false,
                identitySaveToDatabase: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },

            form: {
                Computer_Keys: "",
                Keterangan: "",
            },
            errors: {},
            isEdit: false,
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
        async fetchIdentityKomputer(page = 1, query = "") {
            this.loading.identityLoading = true;
            try {
                if (query) {
                    const response = await axios.get(
                        "/data-search/identity-komputer",
                        {
                            params: { q: query },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.identityKomputer = response.data.result;
                        this.pagination.totalPage = 1;
                        this.pagination.totalData =
                            this.identityKomputer.length;
                    } else {
                        this.identityKomputer = [];
                    }
                } else {
                    const response = await axios.get(
                        "/data/identity-komputer",
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
                            },
                        }
                    );

                    if (response.status === 200 && response.data?.data) {
                        this.identityKomputer = response.data.data;
                        this.pagination.page = page;
                        this.pagination.totalPage = response.data.total_page;
                        this.pagination.totalData = response.data.total_data;
                    } else {
                        this.identityKomputer = [];
                    }
                }
            } catch (error) {
                this.identityKomputer = [];
            } finally {
                this.loading.identityLoading = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchIdentityKomputer(this.pagination.page, this.searchQuery);
        }, 500),

        async generateKey() {
            try {
                const response = await axios.post(
                    "/generate-key",
                    {},
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            Accept: "application/json",
                        },
                    }
                );

                if (response.status !== 200) {
                    throw new Error("Gagal generate key");
                }

                this.form.Computer_Keys = response.data.key;
            } catch (error) {
                Swal.fire("Error", error.message, "error");
            }
        },

        async submitForm() {
            this.errors = {};
            this.loading.identitySaveToDatabase = true;

            if (this.isEdit) {
                try {
                    const response = await axios.put(
                        `/api/v1/identity-ssidevo/${this.form.id}`,
                        this.form,
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (
                        response.status !== 200 ||
                        response.data.status !== "success"
                    ) {
                        if (response.data.errors) {
                            this.errors = response.data.errors;
                        }
                        throw new Error(
                            response.data.message || "Gagal menyimpan data"
                        );
                    }

                    localStorage.setItem(
                        "SSID_EVO",
                        response.data.computer_key
                    );

                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: response.data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => {
                        location.reload();
                    });
                } catch (error) {
                    Swal.fire("Error", error.message, "error");
                } finally {
                    this.loading.identitySaveToDatabase = false;
                }
            } else {
                try {
                    const response = await axios.post(
                        "/identity-ssidevo/gaskeun",
                        this.form,
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (
                        response.status !== 200 ||
                        response.data.status !== "success"
                    ) {
                        if (response.data.errors) {
                            this.errors = response.data.errors;
                        }
                        throw new Error(
                            response.data.message || "Gagal menyimpan data"
                        );
                    }

                    localStorage.setItem(
                        "SSID_EVO",
                        response.data.computer_key
                    );

                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: response.data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => {
                        location.reload();
                    });
                } catch (error) {
                    Swal.fire("Error", error.message, "error");
                } finally {
                    this.loading.identitySaveToDatabase = false;
                }
            }
        },
        toFormData(obj) {
            const formData = new FormData();
            for (const key in obj) {
                formData.append(key, obj[key]);
            }
            return formData;
        },
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchIdentityKomputer(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchIdentityKomputer(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchIdentityKomputer(page, this.searchQuery);
            }
        },
        toggleKey(index) {
            this.isKeyVisible[index] = !this.isKeyVisible[index];
        },
        toggleKey(index) {
            this.isKeyVisible[index] = !this.isKeyVisible[index];
        },
        editData(item) {
            this.form = {
                id: item.id,
                Computer_Keys: item.Computer_Keys,
                Keterangan: item.Keterangan,
            };
            this.isEdit = true;
        },
        resetForm() {
            this.form = {
                Computer_Keys: "",
                Keterangan: "",
            };

            this.isEdit = false;
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        this.fetchIdentityKomputer();
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
