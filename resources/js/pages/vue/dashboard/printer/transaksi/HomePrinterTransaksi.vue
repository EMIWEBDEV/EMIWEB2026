<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Settingan Template Printer
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Master Template Printer PT. Evo Manufacturing
                        Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-sm-auto">
                        <button
                            v-if="
                                pengguna === 'HENDRY' || pengguna === 'Hendry'
                            "
                            class="btn btn-primary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#myModal"
                        >
                            + Tambah Master Template Printer
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
                                        Form Tambah Printer Template
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
                                        <div class="col-12">
                                            <label
                                                class="form-label small fw-bold"
                                                >Nama Template (Master)</label
                                            >
                                            <el-select
                                                v-model="selectedTemplate"
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
                                                    :label="opt.Nama_Template"
                                                    :value="
                                                        opt.Id_Master_Printer_Templates
                                                    "
                                                />
                                            </el-select>
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
                                                    : "Simpan Setup"
                                            }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div
                        id="modalSwitchRole"
                        class="modal fade"
                        tabindex="-1"
                        data-bs-backdrop="static"
                        aria-hidden="true"
                    >
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header border-0">
                                    <h5 class="modal-title fw-bold">
                                        Konfirmasi Ganti Template
                                    </h5>
                                    <button
                                        type="button"
                                        class="btn-close"
                                        @click="cancelSwitch"
                                    ></button>
                                </div>

                                <div class="modal-body text-center">
                                    <dot-lottie-vue
                                        style="margin: 0 auto"
                                        autoplay
                                        loop
                                        src="/animation/warning-submit.json"
                                    ></dot-lottie-vue>

                                    <p class="text-muted mt-2">
                                        Anda akan mengubah template menjadi
                                        <strong class="text-dark">{{
                                            modalConfirm.targetItem
                                                ?.Nama_Template
                                        }}</strong
                                        >.
                                    </p>

                                    <div
                                        class="text-start bg-light p-3 rounded border mb-3"
                                    >
                                        <label
                                            class="form-label small fw-bold mb-1"
                                            >Pilih Role Akses:</label
                                        >
                                        <el-select
                                            v-model="selectedRoleSwitch"
                                            placeholder="Pilih Role"
                                            class="w-100"
                                            size="large"
                                            :disabled="isSingleRole"
                                        >
                                            <el-option
                                                v-for="role in userRoles"
                                                :key="role.Id_Role"
                                                :label="role.Nama_Role"
                                                :value="role.Id_Role"
                                            />
                                        </el-select>
                                        <small
                                            class="text-muted"
                                            v-if="isSingleRole"
                                        >
                                            *Otomatis terpilih karena anda hanya
                                            memiliki 1 akses.
                                        </small>
                                    </div>

                                    <div class="mt-2 p-2">
                                        <el-checkbox
                                            v-model="modalConfirm.isChecked"
                                        >
                                            Saya mengerti dan ingin mengubah
                                            settingan untuk role ini.
                                        </el-checkbox>
                                    </div>
                                </div>

                                <div
                                    class="modal-footer justify-content-center border-0"
                                >
                                    <button
                                        type="button"
                                        class="btn btn-light px-4"
                                        @click="cancelSwitch"
                                    >
                                        Batal
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-primary px-4"
                                        :disabled="
                                            !modalConfirm.isChecked ||
                                            !selectedRoleSwitch
                                        "
                                        @click="confirmSwitch"
                                    >
                                        <span v-if="loading.toggleId"
                                            >Loading...</span
                                        >
                                        <span v-else>Ubah Sekarang</span>
                                    </button>
                                </div>
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
                            v-if="lastHistory"
                            class="alert alert-primary shadow-sm border-0 mb-4"
                            role="alert"
                        >
                            <div class="d-flex align-items-start">
                                <div class="me-3 mt-1">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="24"
                                        height="24"
                                        fill="currentColor"
                                        class="bi bi-info-circle-fill"
                                        viewBox="0 0 16 16"
                                    >
                                        <path
                                            d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"
                                        />
                                    </svg>
                                </div>

                                <div class="flex-grow-1">
                                    <h5
                                        class="alert-heading fw-bold mb-1"
                                        style="font-size: 1rem"
                                    >
                                        Konfigurasi Terakhir
                                    </h5>
                                    <p class="mb-0 text-dark">
                                        Template diubah oleh
                                        <span
                                            class="badge bg-primary text-uppercase"
                                            >{{ lastHistory.Id_User }}</span
                                        >
                                        pada tanggal
                                        <strong>{{
                                            formatDate(lastHistory.Tanggal)
                                        }}</strong>
                                        pukul
                                        <strong>{{ lastHistory.Jam }}</strong
                                        >.
                                    </p>
                                    <hr
                                        class="my-2 border-primary opacity-25"
                                    />
                                    <p class="mb-0 small text-muted">
                                        <em
                                            >Catatan:
                                            {{ lastHistory.Keterangan }}</em
                                        >
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="detailDataList.length"
                            class="table-responsive"
                        >
                            <table
                                class="table table-bordered text-center align-middle"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px">No</th>
                                        <th>Nama Template (Master)</th>
                                        <th style="width: 150px">Aksi</th>
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
                                        <td class="text-start fw-bold">
                                            {{ item.Nama_Template }}
                                            <span
                                                v-if="item.Is_Active === 'Y'"
                                                class="badge bg-success ms-2"
                                                >Active</span
                                            >
                                        </td>
                                        <td>
                                            <el-switch
                                                v-model="item.Is_Active"
                                                active-value="Y"
                                                inactive-value="T"
                                                active-text="Aktif"
                                                inactive-text="Pilih"
                                                inline-prompt
                                                style="
                                                    --el-switch-on-color: #13ce66;
                                                    --el-switch-off-color: #909399;
                                                "
                                                :loading="
                                                    loading.toggleId ===
                                                    item.Id_Master_Printer_Templates
                                                "
                                                :disabled="
                                                    loading.toggleId !== null
                                                "
                                                :before-change="
                                                    () =>
                                                        handleBeforeSwitch(item)
                                                "
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <el-dialog
                            v-model="modalConfirm.visible"
                            title="Konfirmasi Perubahan Template"
                            width="450px"
                            center
                            :close-on-click-modal="false"
                            :show-close="false"
                        >
                            <div class="text-center">
                                <dot-lottie-vue
                                    style="
                                        height: 120px;
                                        width: 120px;
                                        margin: 0 auto;
                                    "
                                    autoplay
                                    loop
                                    src="/animation/warning-submit.json"
                                ></dot-lottie-vue>
                                <h4 class="mt-3">Ganti Template Printer?</h4>
                                <p class="text-muted">
                                    Anda akan mengubah template aktif menjadi
                                    <strong>{{
                                        modalConfirm.targetItem?.Nama_Template
                                    }}</strong
                                    >.
                                    <br />
                                    <span class="text-danger"
                                        >Semua proses print selanjutnya akan
                                        mengikuti settingan terbaru ini.</span
                                    >
                                </p>

                                <div class="mt-4 p-3 bg-light rounded border">
                                    <el-checkbox
                                        v-model="modalConfirm.isChecked"
                                    >
                                        Saya mengerti dan ingin mengubah
                                        settingan.
                                    </el-checkbox>
                                </div>
                            </div>

                            <template #footer>
                                <span class="dialog-footer">
                                    <el-button @click="cancelSwitch"
                                        >Batal</el-button
                                    >

                                    <el-button
                                        type="primary"
                                        :disabled="!modalConfirm.isChecked"
                                        @click="confirmSwitch"
                                        :loading="loading.toggleId !== null"
                                    >
                                        Ubah Sekarang
                                    </el-button>
                                </span>
                            </template>
                        </el-dialog>

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
    ElSwitch,
    ElDialog,
    ElCheckbox,
    ElButton,
} from "element-plus";

export default {
    components: {
        DotLottieVue,
        ElSelect,
        ElOption,
        ElSwitch,
        ElCheckbox,
        ElButton,
        ElDialog,
    },
    props: {
        lastHistory: {
            type: Object,
            default: null,
        },
        pengguna: {
            type: [String, Number],
            default: null,
        },
        userRoles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            isEdit: false,
            searchQuery: "",
            detailDataList: [],
            masterTemplateOptions: [],
            selectedTemplate: null,

            // State untuk Role Selection
            selectedRoleSwitch: null,

            form: {
                id: "",
                Nama_Template: "",
                Lebar_Label: "",
                Tinggi_Label: "",
                Gap_Antar_Label: "",
                Direction: 1,
            },
            loading: {
                loadingDataList: false,
                loadingSaveToDatabase: false,
                toggleId: null,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            modalConfirm: {
                targetItem: null,
                isChecked: false,
                resolvePromise: null,
                rejectPromise: null,
                bsModalInstance: null,
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
        // Cek apakah user punya lebih dari 1 role
        isSingleRole() {
            return this.userRoles.length === 1;
        },
    },
    methods: {
        formatDate(dateString) {
            if (!dateString) return "-";
            const options = { year: "numeric", month: "long", day: "numeric" };
            return new Date(dateString).toLocaleDateString("id-ID", options);
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
            if (!this.selectedTemplate) {
                ElMessage({
                    type: "warning",
                    message: "Silakan pilih master template terlebih dahulu.",
                });
                return;
            }

            this.loading.loadingSaveToDatabase = true;

            try {
                const url = "/api/v1/master-template-printer/set-first";
                const method = "post";

                const payload = {
                    Id_Master_Printer_Templates: this.selectedTemplate,
                };

                const response = await axios({
                    method: method,
                    url: url,
                    data: payload,
                });

                if (response.status === 200 || response.status === 201) {
                    ElMessage({
                        type: "success",
                        message:
                            response.data.message || "Template berhasil diset!",
                    });
                    location.reload();
                } else {
                    throw new Error("Gagal menyimpan data.");
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
                const response = await axios.get(
                    "/api/v1/master-template-printer/current-template",
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

        handleBeforeSwitch(item) {
            return new Promise((resolve, reject) => {
                this.modalConfirm.targetItem = item;
                this.modalConfirm.resolvePromise = resolve;
                this.modalConfirm.rejectPromise = reject;
                this.modalConfirm.isChecked = false;

                // Logic pemilihan Role
                if (this.userRoles.length === 1) {
                    this.selectedRoleSwitch = this.userRoles[0].Id_Role;
                } else {
                    this.selectedRoleSwitch = null;
                }

                // Buka Modal Bootstrap Manual
                const modalEl = document.getElementById("modalSwitchRole");
                if (modalEl) {
                    this.modalConfirm.bsModalInstance = new bootstrap.Modal(
                        modalEl
                    );
                    this.modalConfirm.bsModalInstance.show();
                }
            });
        },

        async confirmSwitch() {
            const item = this.modalConfirm.targetItem;

            // Cari Nama Role untuk dikirim ke backend (opsional, untuk log)
            const roleObj = this.userRoles.find(
                (r) => r.Id_Role === this.selectedRoleSwitch
            );
            const namaRole = roleObj ? roleObj.Nama_Role : "";

            this.loading.toggleId = item.Id_Master_Printer_Templates;

            try {
                const response = await axios.post(
                    "/api/v1/master-template-printer/toggle",
                    {
                        Id_Master_Printer_Templates:
                            item.Id_Master_Printer_Templates,
                        Id_Role: this.selectedRoleSwitch, // Kirim ID Role
                        Nama_Role: namaRole,
                    }
                );

                if (response.data.success) {
                    ElMessage({
                        type: "success",
                        message: response.data.message,
                    });

                    if (this.modalConfirm.resolvePromise) {
                        this.modalConfirm.resolvePromise(true);
                    }

                    // Tutup modal
                    if (this.modalConfirm.bsModalInstance) {
                        this.modalConfirm.bsModalInstance.hide();
                    }

                    window.location.reload();
                } else {
                    throw new Error(response.data.message);
                }
            } catch (error) {
                if (this.modalConfirm.rejectPromise) {
                    this.modalConfirm.rejectPromise();
                }

                ElMessage({
                    type: "error",
                    message:
                        error.response?.data?.message ||
                        "Gagal mengganti template.",
                });
            } finally {
                this.loading.toggleId = null;
            }
        },

        cancelSwitch() {
            if (this.modalConfirm.rejectPromise) {
                this.modalConfirm.rejectPromise();
            }
            this.modalConfirm.isChecked = false;
            this.selectedRoleSwitch = null;

            if (this.modalConfirm.bsModalInstance) {
                this.modalConfirm.bsModalInstance.hide();
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
