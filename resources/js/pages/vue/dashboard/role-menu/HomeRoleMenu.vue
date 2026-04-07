<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Akses Halaman
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Akses Halaman Website Lab PT. Evo Manufacturing
                        Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-sm">
                        <div class="d-flex justify-content-sm-end">
                            <div class="search-box ms-2" v-if="menuList.length">
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
                    <div v-if="loading.menuLoading">
                        <div class="table-wrapper">
                            <table
                                class="skeleton-table"
                                aria-busy="true"
                                aria-label="Loading data"
                            >
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Icon Menu</th>
                                        <th>Nama Menu</th>
                                        <th>Url Menu (Route)</th>
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
                        <div v-if="menuList.length" class="table-responsive">
                            <table
                                class="table table-bordered text-center align-middle"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Icon Menu</th>
                                        <th>Nama Menu</th>
                                        <th>Sub Menu</th>
                                        <th>Url Menu (Route)</th>
                                        <th>Pengguna</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in menuList"
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
                                            <i :class="item.Icon_Menu"></i>
                                        </td>
                                        <td>{{ item.Nama_Menu }}</td>
                                        <td>{{ item.Nama_Sub_Menu ?? "-" }}</td>
                                        <td>/{{ item.Url_Menu }}</td>
                                        <td>{{ item.Id_User }}</td>
                                        <td>
                                            <button
                                                class="btn btn-warning"
                                                type="button"
                                                @click="editData(item)"
                                            >
                                                Edit
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination  -->
                        <div
                            v-if="menuList.length"
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
                            v-if="!menuList.length"
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
    <div class="modal fade" id="modalEditMenu" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Data Menu</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>
                <div class="modal-body">
                    <div class="col-12 mb-3">
                        <div class="form-group">
                            <label
                                for="Nama_Menu"
                                class="form-label fw-semibold"
                            >
                                Nama Menu
                                <span class="text-danger">*</span>
                            </label>
                            <v-select
                                v-if="menuCurrentList && menuCurrentList.length"
                                v-model="selectedOptionIdentity"
                                :options="menuCurrentList"
                                label="name"
                                placeholder="--- Pilih Menu Sidebar ---"
                                class="scrollable-select"
                            />
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <div class="form-group">
                            <label
                                for="Nama_Menu"
                                class="form-label fw-semibold"
                            >
                                Nama Pengguna
                                <span class="text-danger">*</span>
                            </label>
                            <v-select
                                v-if="userCurrentList && userCurrentList.length"
                                v-model="selectedOptionUser"
                                :options="userCurrentList"
                                label="name"
                                placeholder="--- Pilih Pengguna (User) ---"
                                class="scrollable-select"
                            />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="submitForm"
                    >
                        Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import { debounce } from "lodash";
import vSelect from "vue-select";

export default {
    components: {
        DotLottieVue,
        vSelect,
    },
    props: {
        item: Object,
        index: Number,
        UserId: {
            type: [String, Number],
            default: null,
        },
    },
    data() {
        return {
            menuList: [],
            menuCurrentList: [],
            subMenuCurrentList: [],
            userCurrentList: [],
            searchQuery: "",
            selectedOptionIdentity: null,
            selectedOptionUser: null,
            loading: {
                menuLoading: false,
                loadinMenuCurrentList: false,
                loadinMenuCurrentList: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            form: {
                id_role_menu: null,
                Id_Menu: null,
                Id_User: null,
                Id_Sub_Menu: null,
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
        async fetchMenuListCurrent() {
            this.loading.loadinMenuCurrentList = true;
            try {
                const response = await axios.get("/api/v1/master-menu");
                if (response.status === 200 && response.data?.result) {
                    this.menuCurrentList = response.data.result.map((item) => ({
                        value: item.Id_Menu,
                        name: `${item.Nama_Menu}`,
                    }));
                } else {
                    this.menuCurrentList = [];
                }
            } catch (error) {
                this.menuCurrentList = [];
            } finally {
                this.loading.loadinMenuCurrentList = false;
            }
        },
        async fetchPenggunaListCurrent() {
            try {
                const response = await axios.get("/api/v1/pengguna/current");
                if (response.status === 200 && response.data?.result) {
                    this.userCurrentList = response.data.result.map((item) => ({
                        value: item.UserId,
                        name: `${item.UserId} ~ ${item.Nama}`,
                    }));
                } else {
                    this.userCurrentList = [];
                }
            } catch (error) {
                this.userCurrentList = [];
            }
        },
        async fetchMasterMenu(page = 1, query = "") {
            this.loading.menuLoading = true;
            try {
                if (query) {
                    const response = await axios.get(
                        `/api/v1/role-menu/search/${this.UserId}`,
                        {
                            params: { q: query },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.menuList = response.data.result;
                        this.pagination.totalPage = 1;
                        this.pagination.totalData = this.menuList.length;
                    } else {
                        this.menuList = [];
                    }
                } else {
                    const response = await axios.get(
                        `/api/v1/role-menu/current/${this.UserId}`,
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
                            },
                        }
                    );

                    if (response.status === 200 && response.data?.data) {
                        this.menuList = response.data.data;
                        this.pagination.page = page;
                        this.pagination.totalPage = response.data.total_page;
                        this.pagination.totalData = response.data.total_data;
                    } else {
                        this.menuList = [];
                    }
                }
            } catch (error) {
                this.menuList = [];
            } finally {
                this.loading.menuLoading = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchMasterMenu(this.pagination.page, this.searchQuery);
        }, 500),

        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchMasterMenu(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchMasterMenu(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchMasterMenu(page, this.searchQuery);
            }
        },
        async submitForm() {
            this.errors = {};
            this.loading.menuSaveToDatabase = true;
            try {
                const payload = {
                    Id_Menu: this.selectedOptionIdentity.value,
                    Id_User: this.selectedOptionUser.value,
                    Id_Sub_Menu: null,
                };
                const response = await axios.put(
                    `/api/v1/role-menu/update/${this.form.id_role_menu}`,
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (response.status !== 201 || !response.data.success) {
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
                this.loading.menuSaveToDatabase = false;
            }
        },
        editData(item) {
            this.isEdit = true;

            // Buka modal (gunakan ID modal sesuai dengan komponen/modal yang kamu gunakan)
            const modal = new bootstrap.Modal(
                document.getElementById("modalEditMenu")
            );
            modal.show();

            // Cari dan set data v-select berdasarkan ID dari item
            this.selectedOptionIdentity =
                this.menuCurrentList.find(
                    (menu) => menu.value === item.Id_Menu
                ) || null;
            this.selectedOptionUser =
                this.userCurrentList.find(
                    (user) => user.value === item.Id_User
                ) || null;

            // Simpan ke form jika perlu
            this.form = {
                id_role_menu: item.Id_Role_Menu,
                Id_Menu: item.Id_Menu,
                Id_User: item.Id_User,
                Id_Sub_Menu: item.Id_Sub_Menu || null,
            };
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        this.fetchMasterMenu();
        this.fetchMenuListCurrent();
        this.fetchPenggunaListCurrent();
    },
};
</script>

<style>
button:disabled {
    background-color: #6c757d !important; /* abu-abu gelap */
    border-color: #6c757d !important;
    opacity: 0.7;
    cursor: not-allowed;
}
</style>
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
