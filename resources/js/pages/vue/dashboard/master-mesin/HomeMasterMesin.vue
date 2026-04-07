<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Master Mesin
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Master Mesin PT. Evo Manufacturing Indonesia
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
                            + Tambah Master Mesin
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
                                        {{
                                            isEdit
                                                ? "Form Edit Mesin Analisa"
                                                : "Form Tambah Mesin Analisa"
                                        }}
                                    </h5>
                                    <button
                                        v-if="isEdit"
                                        @click="resetForm()"
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>
                                    <button
                                        v-else
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>
                                </div>
                                <form @submit.prevent="saveToDatabase">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label
                                                for="Nama-Mesin-Analisa"
                                                class="form-label"
                                                >Nama Mesin
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <input
                                                type="text"
                                                placeholder="Masukan Nama Mesin"
                                                class="form-control"
                                                v-model="form.Nama_Mesin"
                                                required
                                            />
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                for="seri-mesin"
                                                class="form-label"
                                                >Seri Mesin
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <input
                                                type="text"
                                                placeholder="Masukan Seri Mesin"
                                                class="form-control"
                                                v-model="form.Seri_Mesin"
                                                required
                                            />
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                for="Keterangan-Nama-Mesin"
                                                class="form-label"
                                                >Keterangan Nama Mesin
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <textarea
                                                rows="5"
                                                class="form-control"
                                                placeholder="Masukan Keterangan Untuk Mesin Analisa"
                                                v-model="form.Keterangan"
                                                required
                                            ></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                for="Keterangan-Nama-Mesin"
                                                class="form-label"
                                                >Divisi Mesin
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >

                                            <v-select
                                                v-if="
                                                    divisiMesinList &&
                                                    divisiMesinList.length
                                                "
                                                v-model="selectedOptionMesin"
                                                :options="divisiMesinList"
                                                label="name"
                                                placeholder="--- Pilih Divisi Mesin ---"
                                                class="scrollable-select"
                                            />
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                for="Keterangan-Nama-Mesin"
                                                class="form-label"
                                                >Multi Print Qrcode
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div
                                                        class="form-check form-radio-primary mb-3"
                                                    >
                                                        <input
                                                            class="form-check-input"
                                                            type="radio"
                                                            name="formradiocolor1"
                                                            id="formradioRight5"
                                                            value="Y"
                                                            v-model="
                                                                form.Flag_Multi_Qrcode
                                                            "
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            for="formradioRight5"
                                                        >
                                                            Ya
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div
                                                        class="form-check form-radio-primary mb-3"
                                                    >
                                                        <input
                                                            class="form-check-input"
                                                            type="radio"
                                                            name="formradiocolor1"
                                                            id="formradioRight5"
                                                            value=""
                                                            v-model="
                                                                form.Flag_Multi_Qrcode
                                                            "
                                                        />
                                                        <label
                                                            class="form-check-label"
                                                            for="formradioRight5"
                                                        >
                                                            Tidak
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            class="mb-3"
                                            v-if="
                                                form.Flag_Multi_Qrcode === 'Y'
                                            "
                                        >
                                            <label
                                                for="Jumlah-Print-QrCode"
                                                class="form-label"
                                            >
                                                Jumlah Print QrCode
                                                <span class="text-danger"
                                                    >*</span
                                                >
                                            </label>
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                pattern="[0-9\s]{1,10}"
                                                class="form-control"
                                                placeholder="Masukan Jumlah Print QrCode"
                                                v-model="
                                                    form.Jumlah_Print_QRCode
                                                "
                                                @keydown="checkDigit"
                                                required
                                            />
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button
                                            type="button"
                                            class="btn btn-light"
                                            data-bs-dismiss="modal"
                                            v-if="isEdit"
                                            @click="resetForm()"
                                        >
                                            Tutup
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-light"
                                            data-bs-dismiss="modal"
                                            v-else
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
                                                    ? "Loading..."
                                                    : isEdit
                                                    ? "Perbarui"
                                                    : "Kirimkan"
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
                                        <th>Seri Mesin</th>
                                        <th>Keterangan</th>
                                        <th>Multi QrCode</th>
                                        <th>Jumlah Print</th>
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
                                        <th>Divisi Mesin</th>
                                        <th>Nama Mesin</th>
                                        <th>Seri Mesin</th>
                                        <th>Keterangan</th>
                                        <th>Multi QrCode</th>
                                        <th>Jumlah Print</th>
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
                                            {{ item.Seri_Mesin ?? "-" }}
                                        </td>
                                        <td>
                                            {{ item.Keterangan ?? "-" }}
                                        </td>
                                        <td class="text-center">
                                            <i
                                                v-if="
                                                    item.Flag_Multi_Qrcode ===
                                                    'Y'
                                                "
                                                class="fas fa-check text-success"
                                            ></i>
                                            <i
                                                v-else
                                                class="fas fa-times text-danger"
                                            ></i>
                                        </td>

                                        <td>
                                            {{ item.Jumlah_Print_QRCode ?? 1 }}
                                        </td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-warning"
                                                @click="editData(item)"
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
import vSelect from "vue-select";

export default {
    components: {
        DotLottieVue,
        vSelect,
    },
    data() {
        return {
            searchQuery: "",
            detailDataList: [],
            divisiMesinList: [],
            selectedOptionMesin: null,
            form: {
                Nama_Mesin: "",
                Keterangan: "",
                Seri_Mesin: "",
                Flag_Multi_Qrcode: "",
                Jumlah_Print_QRCode: 1,
            },
            loading: {
                loadingDataList: false,
                loadingDivisiMesinList: false,
                loadingSaveToDatabase: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
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
        async fetchMesinAnalisa(page = 1, query = "") {
            this.loading.loadingDataList = true;
            try {
                if (query) {
                    const response = await axios.get(
                        `/api/v1/master-mesin/search`,
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
                        `/api/v1/master-mesin/current`,
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
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
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        async fetchDivisiMesinList() {
            this.loading.loadingDivisiMesinList = true;
            try {
                const response = await axios.get(
                    "/api/v1/divisi-mesin/current"
                );

                if (response.status === 200 && response.data?.result) {
                    this.divisiMesinList = response.data.result.map((item) => ({
                        value: item.Id_Divisi,
                        name: `${item.Kode_Divisi} ~ ${item.Keterangan}`,
                    }));
                } else {
                    this.divisiMesinList = [];
                }
            } catch (error) {
                this.divisiMesinList = [];
            } finally {
                this.loading.loadingMesinList = false;
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
        checkDigit(event) {
            if (event.key.length === 1 && isNaN(Number(event.key))) {
                event.preventDefault();
            }
        },
        async saveToDatabase() {
            this.loading.loadingSaveToDatabase = true;

            try {
                if (
                    !this.form.Nama_Mesin ||
                    !this.form.Seri_Mesin ||
                    !this.form.Keterangan ||
                    !this.selectedOptionMesin
                ) {
                    Swal.fire({
                        icon: "warning",
                        title: "Opss",
                        text: "Semua form wajib diisi!",
                    });
                    return;
                }

                const flag = this.form.Flag_Multi_Qrcode;
                let jumlahPrint = Number(this.form.Jumlah_Print_QRCode);

                // Validasi flag Multi_Qrcode
                if (flag !== "Y" && flag !== null && flag !== "") {
                    Swal.fire({
                        icon: "warning",
                        title: "Opss",
                        text: "Pilihan Multi QRCode hanya boleh bernilai 'Y' atau kosong/null.",
                    });
                    return;
                }

                // Validasi jumlah print berdasarkan flag
                if (flag === "Y") {
                    if (
                        !jumlahPrint ||
                        isNaN(jumlahPrint) ||
                        jumlahPrint <= 1
                    ) {
                        Swal.fire({
                            icon: "warning",
                            title: "Opss",
                            text: "Jumlah Print QRCode wajib lebih dari 1 jika menggunakan Multi QRCode.",
                        });
                        return;
                    }
                } else {
                    // Jika null atau kosong, default 1
                    jumlahPrint = 1;
                }

                const payload = {
                    Nama_Mesin: this.form.Nama_Mesin,
                    Keterangan: this.form.Keterangan,
                    Seri_Mesin: this.form.Seri_Mesin,
                    Id_Divisi_Mesin: this.selectedOptionMesin.value,
                    Flag_Multi_Qrcode: flag || null,
                    Jumlah_Print_QRCode: jumlahPrint,
                };

                if (this.isEdit) {
                    const response = await axios.put(
                        `/api/v1/divisi-mesin/by-update/${this.form.Id_Master_Mesin}`,
                        payload
                    );

                    if (response.status === 200 && response.data) {
                        this.resetForm();
                        const modal = bootstrap.Modal.getInstance(
                            document.getElementById("myModal")
                        );
                        modal.hide();

                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Data berhasil Diupdate!",
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
                } else {
                    const response = await axios.post(
                        "/api/v1/master-mesin/store",
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
        },
        editData(item) {
            this.form = {
                Nama_Mesin: item.Nama_Mesin,
                Keterangan: item.Keterangan,
                Seri_Mesin: item.Seri_Mesin,
                Flag_Multi_Qrcode: item.Flag_Multi_Qrcode || "",
                Jumlah_Print_QRCode: item.Jumlah_Print_QRCode || 1,
            };

            this.selectedOptionMesin = this.divisiMesinList.find(
                (divisi) => divisi.id === item.Id_Divisi_Mesin
            );

            this.form.Id_Master_Mesin = item.Id_Master_Mesin;

            this.isEdit = true;

            const modal = new bootstrap.Modal(
                document.getElementById("myModal")
            );
            modal.show();
        },
        resetForm() {
            this.form = {
                Nama_Mesin: "",
                Keterangan: "",
                Seri_Mesin: "",
                Flag_Multi_Qrcode: "",
                Jumlah_Print_QRCode: 1,
            };
            this.selectedOptionMesin = null;
            this.isEdit = false;
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        "form.Flag_Multi_Qrcode"(newVal) {
            if (newVal !== "Y") {
                this.form.Jumlah_Print_QRCode = "";
            }
        },
    },
    mounted() {
        this.fetchMesinAnalisa();
        this.fetchDivisiMesinList();
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
