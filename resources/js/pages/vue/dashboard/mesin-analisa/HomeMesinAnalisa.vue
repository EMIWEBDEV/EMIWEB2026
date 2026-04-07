<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Mesin Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Mesin Analisa PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-sm-auto">
                        <button
                            class="btn btn-primary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#myModal"
                        >
                            + Tambah Mesin Analisa
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
                <div class="col-12 mt-3">
                    <div
                        id="myModal"
                        class="modal flip"
                        tabindex="-1"
                        aria-labelledby="myModalLabel"
                        aria-hidden="true"
                        style="display: none"
                        data-bs-backdrop="static"
                    >
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="myModalLabel">
                                        {{
                                            isEdit
                                                ? "Form Edit Mesin Analisa"
                                                : "Form Tambah Mesin Analisa"
                                        }}
                                    </h5>
                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>
                                </div>
                                <form @submit.prevent="saveToDatabase">
                                    <div class="modal-body">
                                        <div
                                            class="mb-3"
                                            v-if="roles && roles.length > 1"
                                        >
                                            <label class="form-label">
                                                Pilih Penempatan
                                                <span class="text-danger"
                                                    >*</span
                                                >
                                            </label>

                                            <el-select
                                                v-model="form.Kode_Role"
                                                placeholder="-- Pilih Role --"
                                                clearable
                                            >
                                                <el-option
                                                    v-for="(
                                                        role, index
                                                    ) in roles"
                                                    :key="index"
                                                    :label="role.Nama_Role"
                                                    :value="role.Kode_Role"
                                                >
                                                    <span
                                                        style="
                                                            font-weight: bold;
                                                            color: var(
                                                                --el-text-color-primary
                                                            );
                                                        "
                                                    >
                                                        {{ role.Nama_Role }}
                                                    </span>
                                                </el-option>
                                            </el-select>
                                        </div>

                                        <div class="mb-3">
                                            <label
                                                for="Nama-Mesin-Analisa"
                                                class="form-label"
                                            >
                                                Nama Mesin Analisa
                                                <span class="text-danger"
                                                    >*</span
                                                >
                                            </label>
                                            <input
                                                type="text"
                                                placeholder="Masukan Nama Mesin Analisa"
                                                class="form-control"
                                                v-model="form.Nama_Mesin"
                                                required
                                            />
                                        </div>

                                        <div class="mb-3">
                                            <label
                                                for="Keterangan-Nama-Mesin"
                                                class="form-label"
                                            >
                                                Keterangan Nama Mesin Analisa
                                                <span class="text-danger"
                                                    >*</span
                                                >
                                            </label>
                                            <textarea
                                                rows="5"
                                                class="form-control"
                                                placeholder="Masukan Keterangan Untuk Mesin Analisa"
                                                v-model="form.Keterangan"
                                                required
                                            ></textarea>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <button
                                            type="button"
                                            class="btn btn-light"
                                            data-bs-dismiss="modal"
                                        >
                                            Tutup
                                        </button>
                                        <button
                                            type="submit"
                                            class="btn"
                                            :class="
                                                loading.loadingSaveToDatabase
                                                    ? 'btn-secondary disabled'
                                                    : 'btn-primary'
                                            "
                                            :disabled="
                                                loading.loadingSaveToDatabase
                                            "
                                        >
                                            {{
                                                loading.loadingSaveToDatabase
                                                    ? "Loading..."
                                                    : isEdit
                                                    ? "Edit Form"
                                                    : "Submit Form"
                                            }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

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
                                        <th>Divisi Mesin</th>
                                        <th>Nama Mesin</th>
                                        <th>Keterangan</th>
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
                                        <th>Divisi Mesin</th>
                                        <th>Nama Mesin</th>
                                        <th>Keterangan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in detailDataList"
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
                                            {{ item.Divisi_Mesin ?? "-" }}
                                        </td>
                                        <td>
                                            {{ item.Nama_Mesin ?? "-" }}
                                        </td>
                                        <td>
                                            {{ item.Keterangan ?? "-" }}
                                        </td>
                                        <td>
                                            <button
                                                @click="editData(item)"
                                                class="btn btn-warning"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#myModal"
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
                            v-if="pagination.totalData > 10"
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
import { debounce } from "lodash";
import axios from "axios";
import Swal from "sweetalert2";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { ElSelect, ElOption } from "element-plus";

export default {
    components: {
        DotLottieVue,
        ElSelect,
        ElOption,
    },
    props: {
        roles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            isEdit: false,
            searchQuery: "",
            detailDataList: [],
            form: {
                id: "",
                Kode_Role: "",
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
        async saveToDatabase() {
            this.loading.loadingSaveToDatabase = true;
            const isRoleRequiredButEmpty =
                this.roles && this.roles.length > 1 && !this.form.Kode_Role;

            if (
                !this.form.Nama_Mesin ||
                !this.form.Keterangan ||
                isRoleRequiredButEmpty
            ) {
                Swal.fire({
                    icon: "warning",
                    title: "Opss",
                    text: "Semua Form Wajib Di Isi Semua (Termasuk Pilihan Role)",
                });
                this.loading.loadingSaveToDatabase = false;
                return;
            }

            if (this.isEdit) {
                try {
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
                            error.response?.data?.message ||
                            error.message ||
                            "Terjadi Kesalahan",
                    });
                } finally {
                    this.loading.loadingSaveToDatabase = false;
                }
            } else {
                try {
                    const payload = {
                        Kode_Role: this.form.Kode_Role,
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
                            error.response?.data?.message ||
                            error.message ||
                            "Terjadi Kesalahan",
                    });
                } finally {
                    this.loading.loadingSaveToDatabase = false;
                }
            }
        },
        async fetchMesinAnalisa(page = 1, query = "") {
            this.loading.loadingDataList = true;
            try {
                if (query) {
                    const response = await axios.get(
                        `/api/v1/mesin-analisa/search`,
                        {
                            params: { q: query },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailDataList = response.data.result;
                        this.pagination.totalPage = 1;
                        this.pagination.totalData = this.detailDataList.length;
                    } else {
                        this.detailDataList = [];
                    }
                } else {
                    const response = await axios.get(
                        `/api/v1/mesin-analisa/current`,
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
                            },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailDataList = response.data.result;
                        this.pagination.page =
                            response.data.pagination.current_page;
                        this.pagination.totalPage =
                            response.data.pagination.total_pages;
                        this.pagination.totalData =
                            response.data.pagination.total;
                    } else {
                        this.detailDataList = [];
                    }
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchMesinAnalisa(this.pagination.page, this.searchQuery);
        }, 500),
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchMesinAnalisa(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchMesinAnalisa(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchMesinAnalisa(page, this.searchQuery);
            }
        },
        editData(item) {
            this.form = {
                id: item.No_Urut,
                Kode_Role: item.Kode_Role,
                Nama_Mesin: item.Nama_Mesin,
                Keterangan: item.Keterangan,
            };
            this.isEdit = true;
        },
        resetForm() {
            this.form = {
                id: "",
                Kode_Role: "",
                Nama_Mesin: "",
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
        this.fetchMesinAnalisa();
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

.skeleton-table thead th {
    padding: 12px;
    text-align: left;
    border: 1px solid #ccc;
    background-color: #f9f9f9;
}

.skeleton-row .skeleton-cell {
    position: relative;
    height: 40px;
    background: #e0e0e0;
    border-radius: 6px;
    margin: 6px 0;
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

@media (max-width: 600px) {
    .skeleton-cell {
        height: 30px;
    }
}
</style>
