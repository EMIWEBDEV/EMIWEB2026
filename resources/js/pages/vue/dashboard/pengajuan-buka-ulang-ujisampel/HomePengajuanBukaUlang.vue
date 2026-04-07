<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Pengajuan Buka Ulang Pengujian Sampel
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Pengajuan Buka Ulang Pengujian Sampel PT. Evo
                        Manufacturing Indonesia
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
                            + Tambah Data Pengajuan Buka Uji Sampel
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
                                    : "Pembukaan Uji Sampel Yang Tertutup"
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
                                        Nomor Sampel
                                        <span class="text-danger">*</span>
                                    </label>
                                    <v-select
                                        v-if="
                                            nomorSampelPo &&
                                            nomorSampelPo.length
                                        "
                                        v-model="selectedOptionPoSampel"
                                        :options="nomorSampelPo"
                                        label="name"
                                        placeholder="--- Pilih Nomor Sampel ---"
                                        class="scrollable-select"
                                    />
                                    <small
                                        v-if="
                                            !nomorSampelPo.length && messageInfo
                                        "
                                        class="text-danger"
                                    >
                                        <br />
                                        {{ messageInfo }}
                                    </small>
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label
                                        for="Waktu Mulai"
                                        class="form-label fw-semibold"
                                    >
                                        Waktu Mulai
                                        <span class="text-danger">*</span>
                                    </label>
                                    <el-date-picker
                                        v-model="form.Waktu_Mulai"
                                        type="datetime"
                                        placeholder="Pilih tanggal dan waktu"
                                        style="width: 100%"
                                        :teleported="false"
                                    />
                                </div>
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label
                                        for="Waktu Mulai"
                                        class="form-label fw-semibold"
                                    >
                                        Waktu Akhir
                                        <span class="text-danger">*</span>
                                    </label>

                                    <el-date-picker
                                        v-model="form.Waktu_Akhir"
                                        type="datetime"
                                        placeholder="Pilih tanggal dan waktu"
                                        style="width: 100%"
                                        :teleported="false"
                                        :disabled-date="disabledEndDate"
                                        :disabled-hours="disabledEndHours"
                                        :disabled-minutes="disabledEndMinutes"
                                        @change="handleEndChange"
                                    />
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
                                    <textarea
                                        placeholder="Masukan Alasan Dibuka Uji Sampel"
                                        class="form-control"
                                        rows="4"
                                        v-model="form.Keterangan"
                                    ></textarea>
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
                                        <th>No Sampel</th>
                                        <th>Waktu Mulai</th>
                                        <th>Waktu Akhir</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        class="skeleton-row"
                                        v-for="(item, index) in 5"
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
                                        <th>No Sampel</th>
                                        <th>Waktu Mulai</th>
                                        <th>Waktu Akhir</th>
                                        <th>Keterangan</th>
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
                                            {{ item.No_Sampel }}
                                        </td>

                                        <td>
                                            {{ formatDate(item.Waktu_Mulai) }}
                                        </td>
                                        <td>
                                            {{ formatDate(item.Waktu_Akhir) }}
                                        </td>
                                        <td>
                                            <button
                                                class="btn btn-info"
                                                data-bs-toggle="modal"
                                                :data-bs-target="
                                                    '#modal-' +
                                                    item.Id_Pengajuan_Buka_Ulang
                                                "
                                            >
                                                Lihat
                                            </button>

                                            <!-- Modal -->
                                            <div
                                                class="modal fade"
                                                :id="
                                                    'modal-' +
                                                    item.Id_Pengajuan_Buka_Ulang
                                                "
                                                tabindex="-1"
                                                :aria-labelledby="
                                                    'label-' +
                                                    item.Id_Pengajuan_Buka_Ulang
                                                "
                                                aria-hidden="true"
                                            >
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div
                                                            class="modal-header"
                                                        >
                                                            <h5
                                                                class="modal-title"
                                                                :id="
                                                                    'label-' +
                                                                    item.Id_Pengajuan_Buka_Ulang
                                                                "
                                                            >
                                                                Detail Pengajuan
                                                            </h5>
                                                            <button
                                                                type="button"
                                                                class="btn-close"
                                                                data-bs-dismiss="modal"
                                                                aria-label="Close"
                                                            ></button>
                                                        </div>
                                                        <div
                                                            class="modal-body text-start"
                                                        >
                                                            <p>
                                                                <strong
                                                                    >No
                                                                    Sampel:</strong
                                                                >
                                                                {{
                                                                    item.No_Sampel
                                                                }}
                                                            </p>
                                                            <p>
                                                                <strong
                                                                    >Tanggal:</strong
                                                                >
                                                                {{
                                                                    item.Tanggal
                                                                }}
                                                            </p>
                                                            <p>
                                                                <strong
                                                                    >Jam:</strong
                                                                >
                                                                {{ item.Jam }}
                                                            </p>
                                                            <p>
                                                                <strong
                                                                    >Waktu
                                                                    Mulai:</strong
                                                                >
                                                                {{
                                                                    formatDate(
                                                                        item.Waktu_Mulai
                                                                    )
                                                                }}
                                                            </p>
                                                            <p>
                                                                <strong
                                                                    >Waktu
                                                                    Akhir:</strong
                                                                >
                                                                {{
                                                                    formatDate(
                                                                        item.Waktu_Akhir
                                                                    )
                                                                }}
                                                            </p>
                                                            <p>
                                                                <strong
                                                                    >Keterangan:</strong
                                                                >
                                                                {{
                                                                    item.Keterangan
                                                                }}
                                                            </p>
                                                        </div>
                                                        <div
                                                            class="modal-footer"
                                                        >
                                                            <button
                                                                type="button"
                                                                class="btn btn-light"
                                                                data-bs-dismiss="modal"
                                                            >
                                                                Close
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- End Modal -->
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
import vSelect from "vue-select";
import { ElDatePicker, ElMessage } from "element-plus";

export default {
    components: {
        DotLottieVue,
        vSelect,
        ElDatePicker,
        ElMessage,
    },
    props: {
        item: Object,
        index: Number,
    },
    data() {
        return {
            selectedOptionPoSampel: null,
            isKeyVisible: {},
            identityKomputer: [],
            nomorSampelPo: [],
            searchQuery: "",
            loading: {
                identityLoading: false,
                loadingPoSampel: false,
                identitySaveToDatabase: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },

            form: {
                Waktu_Mulai: "",
                Waktu_Akhir: "",
                Keterangan: "",
            },
            errors: {},
            isEdit: false,
            messageInfo: "",
            showSelect: true,
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
        handleEndChange(value) {
            if (!value || !this.form.Waktu_Mulai) return;

            const start = new Date(this.form.Waktu_Mulai);
            const minEnd = new Date(start.getTime() + 5 * 60000);
            const selected = new Date(value);

            // Jika hanya pilih tanggal (jam masih 00:00 default picker)
            const isDefaultTime =
                selected.getHours() === 0 && selected.getMinutes() === 0;

            if (isDefaultTime) {
                selected.setHours(minEnd.getHours());
                selected.setMinutes(minEnd.getMinutes());
                selected.setSeconds(0);

                this.form.Waktu_Akhir = new Date(selected);
                return; // stop supaya tidak lanjut ke validasi bawah
            }

            // Kalau memang user pilih waktu kurang dari minimal
            if (selected < minEnd) {
                this.form.Waktu_Akhir = minEnd;

                ElMessage({
                    type: "warning",
                    message: "Waktu akhir minimal 5 menit setelah waktu mulai.",
                    grouping: true,
                });
            }
        },

        disabledEndDate(date) {
            if (!this.form.Waktu_Mulai) return false;

            const start = new Date(this.form.Waktu_Mulai);
            const minEnd = new Date(start.getTime() + 5 * 60000);

            return (
                date <
                new Date(
                    minEnd.getFullYear(),
                    minEnd.getMonth(),
                    minEnd.getDate()
                )
            );
        },

        disabledEndHours() {
            if (!this.form.Waktu_Mulai) return [];

            const start = new Date(this.form.Waktu_Mulai);
            const minEnd = new Date(start.getTime() + 5 * 60000);

            const selectedDate = this.form.Waktu_Akhir
                ? new Date(this.form.Waktu_Akhir)
                : minEnd;

            if (selectedDate.toDateString() !== minEnd.toDateString()) {
                return [];
            }

            const disabled = [];
            for (let i = 0; i < minEnd.getHours(); i++) {
                disabled.push(i);
            }

            return disabled;
        },

        disabledEndMinutes(hour) {
            if (!this.form.Waktu_Mulai) return [];

            const start = new Date(this.form.Waktu_Mulai);
            const minEnd = new Date(start.getTime() + 5 * 60000);

            const selectedDate = this.form.Waktu_Akhir
                ? new Date(this.form.Waktu_Akhir)
                : minEnd;

            if (
                selectedDate.toDateString() !== minEnd.toDateString() ||
                hour !== minEnd.getHours()
            ) {
                return [];
            }

            const disabled = [];
            for (let i = 0; i < minEnd.getMinutes(); i++) {
                disabled.push(i);
            }

            return disabled;
        },

        formatDate(datetime) {
            if (!datetime) return "-";

            const date = new Date(datetime);
            const options = {
                day: "numeric",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                hour12: false,
            };

            return new Intl.DateTimeFormat("id-ID", options).format(date);
        },
        async fetchPoSampel() {
            this.loading.loadingPoSampel = true;
            this.messageInfo = "";
            try {
                const response = await axios.get(
                    "/api/v1/list-pengajuan-uji-buka-sampel/no-sampel/current"
                );

                if (response.status === 200) {
                    if (response.data.result.length) {
                        this.nomorSampelPo = response.data.result.map(
                            (item) => ({
                                value: item.No_Sampel,
                                name: `${item.No_Sampel}`,
                            })
                        );
                    } else {
                        this.nomorSampelPo = [];
                        this.messageInfo =
                            response.data.message || "Tidak ada data sampel.";
                    }
                } else {
                    this.nomorSampelPo = [];
                    this.messageInfo = "Gagal memuat data.";
                }
            } catch (error) {
                this.nomorSampelPo = [];
                this.messageInfo = "Terjadi kesalahan saat memuat data.";
            } finally {
                this.loading.loadingPoSampel = false;
            }
        },
        async fetchIdentityKomputer(page = 1, query = "") {
            this.loading.identityLoading = true;
            try {
                if (query) {
                    const response = await axios.get(
                        "/api/v1/pengajuan-uji-buka-sampel/search",
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
                        "/api/v1/pengajuan-uji-buka-sampel/current",
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

        async submitForm() {
            this.errors = {};
            this.loading.identitySaveToDatabase = true;

            if (this.isEdit) {
                try {
                    const response = await axios.put(
                        `/pengajuan-uji-buka-sampel/update/${this.form.id}`,
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
                    const payload = {
                        No_Sampel: this.selectedOptionPoSampel.value,
                        ...this.form,
                    };
                    const response = await axios.post(
                        "/pengajuan-uji-buka-sampel/store",
                        payload,
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status !== 201) {
                        if (response.data.errors) {
                            this.errors = response.data.errors;
                        }
                        throw new Error(
                            response.data.message || "Gagal menyimpan data"
                        );
                    }

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
                Waktu_Mulai: "",
                Waktu_Akhir: "",
                Keterangan: "",
            };

            this.isEdit = false;
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        "form.Waktu_Mulai"(val) {
            if (!val) {
                this.form.Waktu_Akhir = "";
                return;
            }

            const minEnd = new Date(new Date(val).getTime() + 5 * 60000);

            if (
                this.form.Waktu_Akhir &&
                new Date(this.form.Waktu_Akhir) < minEnd
            ) {
                this.form.Waktu_Akhir = "";

                ElMessage({
                    type: "warning",
                    message: "Waktu akhir minimal 5 menit setelah waktu mulai.",
                    grouping: true,
                });
            }
        },
    },
    mounted() {
        this.fetchIdentityKomputer();
        this.fetchPoSampel();
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
