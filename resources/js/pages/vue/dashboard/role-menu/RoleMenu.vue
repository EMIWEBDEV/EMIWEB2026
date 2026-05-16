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
                                <i class="ri-airplay-line label-icon"></i>
                                <strong>Info</strong> - Icon Hanya untuk Font
                                Awesome Dilarang Icon Lain Seperti Remix, Box
                                Dan lainnya
                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="alert"
                                    aria-label="Close"
                                ></button>
                            </div>

                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label
                                        for="Nama_Pengguna"
                                        class="form-label fw-semibold"
                                    >
                                        Nama Pengguna
                                        <span class="text-danger">*</span>
                                    </label>
                                    <el-select
                                        v-if="
                                            userCurrentList &&
                                            userCurrentList.length
                                        "
                                        v-model="selectedOptionUser"
                                        multiple
                                        collapse-tags
                                        collapse-tags-tooltip
                                        :max-collapse-tags="2"
                                        placeholder="--- Pilih Pengguna (User) ---"
                                        style="width: 100%"
                                        clearable
                                    >
                                        <el-option
                                            v-for="item in userCurrentList"
                                            :key="item.value"
                                            :label="item.name"
                                            :value="item.value"
                                        />
                                    </el-select>
                                </div>
                            </div>

                            <!-- Bagian el-select Menu -->
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label
                                        for="Nama_Menu"
                                        class="form-label fw-semibold"
                                    >
                                        Nama Menu
                                        <span class="text-danger">*</span>
                                    </label>

                                    <!-- Peringatan jika belum pilih user -->
                                    <div
                                        v-if="selectedOptionUser.length === 0"
                                        class="text-muted small mb-2"
                                    >
                                        *Pilih pengguna terlebih dahulu untuk
                                        melihat ketersediaan menu.
                                    </div>

                                    <el-select
                                        v-if="
                                            menuCurrentList &&
                                            menuCurrentList.length
                                        "
                                        v-model="selectedOptionIdentity"
                                        multiple
                                        collapse-tags
                                        collapse-tags-tooltip
                                        :max-collapse-tags="2"
                                        placeholder="--- Pilih Menu Sidebar ---"
                                        style="width: 100%"
                                        clearable
                                        @change="handleMenuChange"
                                        :disabled="
                                            selectedOptionUser.length === 0
                                        "
                                    >
                                        <el-option
                                            label="-- Pilih Semua Menu --"
                                            value="ALL"
                                            class="fw-bold text-primary"
                                        />
                                        <!-- Gunakan processedMenuOptions dari computed -->
                                        <el-option
                                            v-for="item in processedMenuOptions"
                                            :key="item.value"
                                            :label="item.label"
                                            :value="item.value"
                                            :disabled="item.disabled"
                                        />
                                    </el-select>
                                </div>
                            </div>

                            <div
                                v-if="selectedOptionIdentity.length > 0"
                                class="col-12 mb-3"
                            >
                                <label class="form-label fw-semibold"
                                    >Atur Urutan Menu</label
                                >
                                <div
                                    v-for="menuId in selectedOptionIdentity"
                                    :key="menuId"
                                    class="row mb-2 align-items-center"
                                >
                                    <div class="col-md-5">
                                        <span>{{ getMenuName(menuId) }}</span>
                                    </div>
                                    <div class="col-md-5">
                                        <input
                                            type="number"
                                            class="form-control"
                                            v-model.number="urutanMenu[menuId]"
                                            placeholder="Masukkan Urutan"
                                            required
                                            min="1"
                                        />
                                    </div>
                                    <div class="col-md-2 text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            @click="removeMenu(menuId)"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-grid">
                                    <button
                                        :disabled="loading.menuSaveToDatabase"
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="bi bi-send-check me-2"></i>
                                        {{
                                            loading.menuSaveToDatabase
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
import { ElSelect, ElOption } from "element-plus";

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
        vSelect,
        ElSelect,
        ElOption,
    },
    data() {
        return {
            listData: [],
            menuList: [],
            menuCurrentList: [],
            subMenuCurrentList: [],
            userCurrentList: [],
            searchQuery: "",
            selectedOptionIdentity: [],
            selectedOptionUser: [],
            urutanMenu: {},
            loading: {
                loadingListData: false,
                menuSaveToDatabase: false,
                loadinMenuCurrentList: false,
            },
            errors: {},
        };
    },
    computed: {
        processedMenuOptions() {
            // Jika belum ada user yang dipilih, kembalikan menu default (semua aktif)
            if (
                !this.selectedOptionUser ||
                this.selectedOptionUser.length === 0
            ) {
                return this.menuCurrentList.map((m) => ({
                    ...m,
                    label: m.name,
                    disabled: false,
                }));
            }

            return this.menuCurrentList.map((menu) => {
                // Filter dari user yang dipilih, siapa saja yang sudah punya menu ini di listData (database existing)
                const ownedBySelectedUsers = this.selectedOptionUser.filter(
                    (userId) => {
                        return this.listData.some(
                            (data) =>
                                data.Id_User === userId &&
                                data.Id_Menu === menu.value
                        );
                    }
                );

                let customLabel = menu.name;
                let isDisabled = false;

                if (ownedBySelectedUsers.length > 0) {
                    if (
                        ownedBySelectedUsers.length ===
                        this.selectedOptionUser.length
                    ) {
                        // Kondisi 1: SEMUA user yang dipilih SUDAH PUNYA menu ini
                        customLabel = `${menu.name} (Sudah dimiliki semua user terpilih)`;
                        isDisabled = true; // Matikan opsi
                    } else {
                        // Kondisi 2: HANYA SEBAGIAN user yang dipilih yang sudah punya
                        customLabel = `${
                            menu.name
                        } (Sudah dimiliki: ${ownedBySelectedUsers.join(", ")})`;
                        isDisabled = false; // Tetap nyalakan agar user lain bisa ditambahkan
                    }
                }

                return {
                    ...menu,
                    label: customLabel,
                    disabled: isDisabled,
                };
            });
        },
    },
    methods: {
        getMenuName(id) {
            const menu = this.menuCurrentList.find((m) => m.value === id);
            return menu ? menu.name : "";
        },
        updateUrutan() {
            let items = this.selectedOptionIdentity.map((id) => {
                return {
                    id: id,
                    urutan: this.urutanMenu[id] || 999999,
                };
            });

            items.sort((a, b) => a.urutan - b.urutan);

            const newUrutan = {};
            items.forEach((item, index) => {
                newUrutan[item.id] = index + 1;
            });

            this.urutanMenu = newUrutan;

            this.selectedOptionIdentity = items.map((item) => item.id);
        },
        handleMenuChange(val) {
            if (val.includes("ALL")) {
                this.selectedOptionIdentity = this.menuCurrentList.map(
                    (item) => item.value
                );
            }
            this.updateUrutan();
        },
        removeMenu(id) {
            this.selectedOptionIdentity = this.selectedOptionIdentity.filter(
                (menuId) => menuId !== id
            );
            this.updateUrutan();
        },
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

                this.selectedOptionIdentity.forEach((menuId) => {
                    this.selectedOptionUser.forEach((userId) => {
                        payload.push({
                            Id_Menu: menuId,
                            Id_User: userId,
                            Id_Sub_Menu: null,
                            Urutan_Menu: this.urutanMenu[menuId] || 1,
                        });
                    });
                });

                const response = await axios.post(
                    "/api/v1/role-menu/store",
                    { data: payload },
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
