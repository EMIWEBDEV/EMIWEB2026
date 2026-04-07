<template>
    <div class="container-fluid px-0">
        <div class="card shadow-sm border-0 w-100">
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
                <div
                    class="d-flex justify-content-center justify-content-lg-start"
                >
                    <button
                        class="btn btn-primary"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#offcanvasRight"
                        aria-controls="offcanvasRight"
                    >
                        + Tambah Akses Halaman
                    </button>
                </div>

                <div
                    class="offcanvas offcanvas-end"
                    tabindex="-1"
                    id="offcanvasRight"
                    aria-labelledby="offcanvasRightLabel"
                >
                    <div class="offcanvas-header">
                        <h5 id="offcanvasRightLabel" class="mb-0">
                            Penambahan Daftar Menu Website EMI LAB
                            <i class="fas fa-desktop"></i>
                        </h5>
                        <button
                            type="button"
                            class="btn-close text-reset"
                            data-bs-dismiss="offcanvas"
                            aria-label="Close"
                        ></button>
                    </div>
                    <div class="offcanvas-body">
                        <form @submit.prevent="submitForm">
                            <div
                                class="alert alert-info alert-dismissible alert-label-icon label-arrow fade show material-shadow"
                                role="alert"
                            >
                                <i class="ri-airplay-line label-icon"></i
                                ><strong>Info</strong> - Icon Hanya untuk Font
                                Awesome Dilarang Icon Lain Seperti Remix, Box
                                Dan lainnya
                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss=" alert"
                                    aria-label="Close"
                                ></button>
                            </div>
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
                                        v-if="
                                            menuCurrentList &&
                                            menuCurrentList.length
                                        "
                                        v-model="selectedOptionIdentity"
                                        :options="menuCurrentList"
                                        label="name"
                                        placeholder="--- Pilih Menu Sidebar ---"
                                        class="scrollable-select"
                                        multiple
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
                                        v-if="
                                            userCurrentList &&
                                            userCurrentList.length
                                        "
                                        v-model="selectedOptionUser"
                                        :options="userCurrentList"
                                        label="name"
                                        placeholder="--- Pilih Pengguna (User) ---"
                                        class="scrollable-select"
                                        multiple
                                    />
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-grid">
                                    <button
                                        :disabled="menuSaveToDatabase"
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-send-check me-2"></i>
                                        {{
                                            menuSaveToDatabase
                                                ? "Loading..."
                                                : "Submit Form"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <ListSkeleton :page="5" v-if="loading.loadingListData" />

                    <div class="list-group" v-else>
                        <div v-if="listData.length">
                            <a
                                :href="'/role/menu/' + item.Id_User"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-3"
                                v-for="(item, index) in listData"
                                :key="index"
                            >
                                <div>
                                    <div class="fw-bold text-dark">
                                        <i
                                            class="fas fa-user text-primary me-2"
                                        ></i>
                                        {{ item.Id_User }}
                                    </div>
                                </div>
                                <span class="badge bg-primary rounded-pill"
                                    >{{ item.total_data ?? 0 }} Data</span
                                >
                            </a>
                        </div>
                        <div
                            v-if="!listData.length"
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
import ListSkeleton from "@/pages/vue/ui/ListSkeleton.vue";
import Swal from "sweetalert2";
import vSelect from "vue-select";

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
        vSelect,
    },
    data() {
        return {
            listData: [],
            menuList: [],
            menuCurrentList: [],
            subMenuCurrentList: [],
            userCurrentList: [],
            searchQuery: "",
            selectedOptionIdentity: null,
            selectedOptionUser: null,
            loading: {
                loadingListData: false,
                menuSaveToDatabase: false,
            },
        };
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
        async fetchBindingIdentityKomputer() {
            this.loading.loadingListData = true;
            try {
                const response = await axios.get(
                    "/api/v1/role-menu/home-current"
                );
                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                } else {
                    this.listData = [];
                }
            } catch (error) {
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },
        async submitForm() {
            this.errors = {};
            this.loading.menuSaveToDatabase = true;
            try {
                const payload = [];

                this.selectedOptionIdentity.forEach((menu) => {
                    this.selectedOptionUser.forEach((user) => {
                        payload.push({
                            Id_Menu: menu.value,
                            Id_User: user.value,
                            Id_Sub_Menu: null,
                        });
                    });
                });

                const response = await axios.post(
                    "/api/v1/role-menu/store",
                    { data: payload }, // kirim array
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
    },
    mounted() {
        this.fetchBindingIdentityKomputer();
        this.fetchMenuListCurrent();
        this.fetchPenggunaListCurrent();
    },
};
</script>
