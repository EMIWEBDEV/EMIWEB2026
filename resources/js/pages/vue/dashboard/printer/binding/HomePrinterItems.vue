<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Master Template Printer Items
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Master Template Printer Items PT. Evo
                        Manufacturing Indonesia
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
                            + Tambah Master Template Printer Items
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
                        <div
                            class="modal-dialog modal-xl modal-dialog-centered"
                        >
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="myModalLabel">
                                        Form Printer Template Items
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
                                            class="d-flex justify-content-between align-items-center mb-3"
                                        >
                                            <h6 class="m-0 fw-bold">
                                                Item Konfigurasi (TEXT / QRCODE)
                                            </h6>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-success"
                                                @click="addRow"
                                            >
                                                <i class="bi bi-plus"></i>
                                                Tambah Item
                                            </button>
                                        </div>

                                        <div
                                            v-for="(item, index) in items"
                                            :key="index"
                                            class="card mb-3 shadow-sm border-primary"
                                        >
                                            <div class="card-body">
                                                <div
                                                    class="d-flex justify-content-between mb-2"
                                                >
                                                    <span
                                                        class="badge bg-primary"
                                                        >Sequence #{{
                                                            index + 1
                                                        }}</span
                                                    >
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-danger"
                                                        @click="
                                                            removeRow(index)
                                                        "
                                                    >
                                                        Hapus
                                                    </button>
                                                </div>

                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <label
                                                            class="form-label small fw-bold"
                                                            >Nama Template
                                                            (Master)</label
                                                        >
                                                        <el-select
                                                            v-model="
                                                                item.Id_Master_Printer_Templates
                                                            "
                                                            placeholder="Pilih Master Template"
                                                            filterable
                                                            class="w-100"
                                                            size="large"
                                                        >
                                                            <el-option
                                                                v-for="opt in masterTemplateOptions"
                                                                :key="
                                                                    opt.Id_Master_Printer_Templates
                                                                "
                                                                :label="
                                                                    opt.Nama_Template
                                                                "
                                                                :value="
                                                                    opt.Id_Master_Printer_Templates
                                                                "
                                                            />
                                                        </el-select>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label
                                                            class="form-label small"
                                                            >Jenis
                                                            Command</label
                                                        >
                                                        <el-select
                                                            v-model="item.Jenis"
                                                            placeholder="Pilih Jenis"
                                                            class="w-100"
                                                        >
                                                            <el-option
                                                                label="TEXT"
                                                                value="TEXT"
                                                            />
                                                            <el-option
                                                                label="QRCODE"
                                                                value="QRCODE"
                                                            />
                                                            <el-option
                                                                label="BARCODE"
                                                                value="BARCODE"
                                                            />
                                                        </el-select>
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label
                                                            class="form-label small"
                                                            >Posisi X</label
                                                        >
                                                        <el-input-number
                                                            v-model="
                                                                item.Posisi_X
                                                            "
                                                            :min="0"
                                                            class="w-100"
                                                            controls-position="right"
                                                        />
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label
                                                            class="form-label small"
                                                            >Posisi Y</label
                                                        >
                                                        <el-input-number
                                                            v-model="
                                                                item.Posisi_Y
                                                            "
                                                            :min="0"
                                                            class="w-100"
                                                            controls-position="right"
                                                        />
                                                    </div>

                                                    <div class="col-md-3">
                                                        <label
                                                            class="form-label small"
                                                            >Rotation (0, 90,
                                                            180, 270)</label
                                                        >
                                                        <el-input-number
                                                            v-model="
                                                                item.Rotation
                                                            "
                                                            :min="0"
                                                            :max="270"
                                                            :step="90"
                                                            class="w-100"
                                                            controls-position="right"
                                                        />
                                                    </div>

                                                    <div class="col-12">
                                                        <label
                                                            class="form-label small fw-bold"
                                                            >Isi Konten
                                                            (Variabel/String)</label
                                                        >
                                                        <el-input
                                                            v-model="
                                                                item.Isi_Konten
                                                            "
                                                            placeholder='Contoh: "Batch $batch" atau "$namaMesin"'
                                                        />
                                                    </div>

                                                    <template
                                                        v-if="
                                                            item.Jenis ===
                                                            'TEXT'
                                                        "
                                                    >
                                                        <div class="col-md-4">
                                                            <label
                                                                class="form-label small"
                                                                >Font
                                                                Type</label
                                                            >
                                                            <el-select
                                                                v-model="
                                                                    item.Font
                                                                "
                                                                class="w-100"
                                                            >
                                                                <el-option
                                                                    label="1 (Kecil)"
                                                                    value="1"
                                                                />
                                                                <el-option
                                                                    label="2 (Sedang)"
                                                                    value="2"
                                                                />
                                                                <el-option
                                                                    label="3 (Besar)"
                                                                    value="3"
                                                                />
                                                                <el-option
                                                                    label="4 (Bold)"
                                                                    value="4"
                                                                />
                                                                <el-option
                                                                    label="0 (Simbol)"
                                                                    value="0"
                                                                />
                                                            </el-select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label
                                                                class="form-label small"
                                                                >Scale X
                                                                (Horizontal)</label
                                                            >
                                                            <el-input-number
                                                                v-model="
                                                                    item.Scale_X
                                                                "
                                                                :min="1"
                                                                class="w-100"
                                                                controls-position="right"
                                                            />
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label
                                                                class="form-label small"
                                                                >Scale Y
                                                                (Vertical)</label
                                                            >
                                                            <el-input-number
                                                                v-model="
                                                                    item.Scale_Y
                                                                "
                                                                :min="1"
                                                                class="w-100"
                                                                controls-position="right"
                                                            />
                                                        </div>
                                                    </template>

                                                    <template
                                                        v-if="
                                                            item.Jenis ===
                                                            'QRCODE'
                                                        "
                                                    >
                                                        <div class="col-md-4">
                                                            <label
                                                                class="form-label small"
                                                                >QR ECC
                                                                Level</label
                                                            >
                                                            <el-select
                                                                v-model="
                                                                    item.Qr_Ecc
                                                                "
                                                                class="w-100"
                                                            >
                                                                <el-option
                                                                    label="L (7%)"
                                                                    value="L"
                                                                />
                                                                <el-option
                                                                    label="M (15%)"
                                                                    value="M"
                                                                />
                                                                <el-option
                                                                    label="Q (25%)"
                                                                    value="Q"
                                                                />
                                                                <el-option
                                                                    label="H (30%)"
                                                                    value="H"
                                                                />
                                                            </el-select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label
                                                                class="form-label small"
                                                                >Cell Width
                                                                (Size)</label
                                                            >
                                                            <el-input-number
                                                                v-model="
                                                                    item.Qr_Size
                                                                "
                                                                :min="1"
                                                                :max="10"
                                                                class="w-100"
                                                                controls-position="right"
                                                            />
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label
                                                                class="form-label small"
                                                                >Mode /
                                                                Model</label
                                                            >
                                                            <el-input
                                                                v-model="
                                                                    item.Qr_Model
                                                                "
                                                                placeholder="A, M2, dll"
                                                            />
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
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
                                            class="btn btn-primary"
                                            :disabled="
                                                loading.loadingSaveToDatabase
                                            "
                                        >
                                            {{
                                                loading.loadingSaveToDatabase
                                                    ? "Menyimpan..."
                                                    : "Simpan Konfigurasi"
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
                                        <th>Nama Template</th>
                                        <th>Lebar</th>
                                        <th>Tinggi</th>
                                        <th>Gap</th>
                                        <th>Direction</th>
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
                                        <th>Nama Template</th>
                                        <th>Lebar</th>
                                        <th>Tinggi</th>
                                        <th>Gap</th>
                                        <th>Direction</th>
                                        <th>Status</th>
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
                                        <td>{{ item.Nama_Template }}</td>
                                        <td>{{ item.Lebar_Label }} mm</td>
                                        <td>{{ item.Tinggi_Label }} mm</td>
                                        <td>{{ item.Gap_Antar_Label }} mm</td>
                                        <td>{{ item.Direction }}</td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="
                                                    item.Flag_Aktif === 'Y'
                                                        ? 'bg-success'
                                                        : 'bg-danger'
                                                "
                                            >
                                                {{
                                                    item.Flag_Aktif === "Y"
                                                        ? "Aktif"
                                                        : "Non Aktif"
                                                }}
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
import {
    ElMessage,
    ElSelect,
    ElOption,
    ElInput,
    ElInputNumber,
} from "element-plus";
export default {
    components: {
        DotLottieVue,
        ElSelect,
        ElOption,
        ElInput,
        ElInputNumber,
    },
    data() {
        return {
            isEdit: false,
            searchQuery: "",
            masterTemplateOptions: [],
            detailDataList: [],
            selectedMasterTemplate: "",
            items: [
                {
                    Id_Master_Printer_Templates: null,
                    Jenis: "TEXT",
                    Lebar_Label: null,
                    Posisi_X: 0,
                    Posisi_Y: 0,
                    Font: "1",
                    Rotation: 0,
                    Scale_X: 1,
                    Scale_Y: 1,
                    Isi_Konten: "",
                    Qr_Ecc: "",
                    Qr_Size: null,
                    Qr_Model: "",
                },
            ],
            loading: {
                save: false,
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
        addRow() {
            this.items.push({
                Id_Master_Printer_Templates: null,
                Jenis: "TEXT",
                Lebar_Label: null,
                Posisi_X: 0,
                Posisi_Y: 0,
                Font: "1",
                Rotation: 0,
                Scale_X: 1,
                Scale_Y: 1,
                Isi_Konten: "",
                Qr_Ecc: "",
                Qr_Size: null,
                Qr_Model: "",
            });
        },

        removeRow(index) {
            this.items.splice(index, 1);
        },
        async fetchTemplateOptions() {
            try {
                const response = await axios.get(
                    "/api/v1/master-template-printer/option/master-template"
                );

                if (response.data.success) {
                    this.masterTemplateOptions = response.data.result;
                }
            } catch (error) {
                console.error(error);
            }
        },

        async saveToDatabase() {
            this.loading.loadingSaveToDatabase = true;
            try {
                const payload = {
                    items: this.items,
                };

                let url = "/api/v1/master-template-printer/items/store";
                let method = "post";

                if (this.isEdit) {
                    url = `/api/v1/master-template-printer/update/${this.form.id}`;
                    method = "put";
                }

                const response = await axios({
                    method: method,
                    url: url,
                    data: payload,
                });

                if (
                    (response.status === 200 || response.status === 201) &&
                    response.data
                ) {
                    ElMessage({
                        type: "success",
                        message:
                            response.data.message || "Data berhasil disimpan!",
                    });
                    location.reload();
                } else {
                    ElMessage({
                        type: "error",
                        message: "Gagal menyimpan data.",
                    });
                }
            } catch (error) {
                ElMessage({
                    type: "error",
                    message:
                        error.response?.data?.message ||
                        error.message ||
                        "Terjadi Kesalahan",
                });
            } finally {
                this.loading.loadingSaveToDatabase = false;
            }
        },
        async fetchMasterTemplate(page = 1, query = "") {
            this.loading.loadingDataList = true;
            try {
                const response = await axios.post(
                    "/api/v1/master-template-printer/current",
                    {},
                    {
                        params: {
                            page: page,
                            limit: this.pagination.limit,
                            search: query,
                        },
                    }
                );

                if (response.status === 200 && response.data?.result) {
                    this.detailDataList = response.data.result;
                    this.pagination.page = page;
                    this.pagination.totalPage = response.data.total_page;
                    this.pagination.totalData = response.data.total_data;
                } else {
                    this.detailDataList = [];
                }
            } catch (error) {
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchMasterTemplate(this.pagination.page, this.searchQuery);
        }, 500),
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchMasterTemplate(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchMasterTemplate(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchMasterTemplate(page, this.searchQuery);
            }
        },
        editData(item) {
            this.form = {
                id: item.Id_Master_Printer_Templates,
                Nama_Template: item.Nama_Template,
                Lebar_Label: item.Lebar_Label,
                Tinggi_Label: item.Tinggi_Label,
                Gap_Antar_Label: item.Gap_Antar_Label,
                Direction: item.Direction,
            };
            this.isEdit = true;
        },
        resetForm() {
            this.form = {
                id: "",
                Nama_Template: "",
                Lebar_Label: "",
                Tinggi_Label: "",
                Gap_Antar_Label: "",
                Direction: 1,
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
        this.fetchMasterTemplate();
        this.fetchTemplateOptions();
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
