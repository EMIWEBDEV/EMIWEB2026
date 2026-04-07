<template>
    <div class="container-fluid px-0">
        <div class="card shadow-sm border-0 w-100">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Jenis Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Jenis Analisa PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="col-12 mt-3">
                    <div class="row mb-4 align-items-center">
                        <!-- Kiri -->
                        <div class="col-md-6 col-12 mb-2 mb-md-0">
                            <div
                                class="d-flex justify-content-center justify-content-md-start"
                            >
                                <a
                                    href="/jenis-analisa/create"
                                    class="btn btn-primary"
                                >
                                    + Tambah Jenis Analisa
                                </a>
                            </div>
                        </div>

                        <!-- Kanan -->
                        <div class="col-md-6 col-12">
                            <div class="input-group shadow-sm">
                                <span
                                    class="input-group-text bg-white border-end-0"
                                >
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control border-start-0"
                                    placeholder="Cari Kode atau Jenis Analisa..."
                                    v-model="searchQuery"
                                    @keyup.enter="handleSearch"
                                />
                                <button
                                    class="btn btn-primary"
                                    @click="handleSearch"
                                >
                                    Cari
                                </button>
                            </div>
                        </div>
                    </div>

                    <ListSkeleton
                        :page="limit"
                        v-if="loading.loadingListData"
                    />

                    <div class="list-group" v-else>
                        <div v-if="listData.length">
                            <a
                                :href="
                                    '/jenis-analisa/detail/page-current/' +
                                    item.Jenis_Analisa
                                "
                                class="list-group-item list-group-item-action mb-3 shadow-sm border-light-subtle rounded-3 p-3 transition-hover"
                                v-for="(item, index) in listData"
                                :key="index"
                            >
                                <div
                                    class="d-flex justify-content-between align-items-start"
                                >
                                    <div class="w-100">
                                        <div
                                            class="d-flex align-items-center mb-3"
                                        >
                                            <span
                                                class="badge bg-kode text-white px-3 py-2 rounded-pill me-3 shadow-sm"
                                                style="font-size: 0.9rem"
                                            >
                                                <i
                                                    class="bi bi-upc-scan text-info me-2"
                                                ></i
                                                >{{ item.Kode_Analisa }}
                                            </span>
                                            <h5 class="mb-0 fw-bold text-dark">
                                                {{ item.Jenis_Analisa }}
                                            </h5>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            <span
                                                v-if="isFLM"
                                                class="badge rounded-pill px-3 py-2"
                                                :class="
                                                    item.Nama_Aktivitas
                                                        ? 'bg-primary bg-opacity-10 text-primary border border-primary'
                                                        : 'bg-light text-muted border'
                                                "
                                            >
                                                <i
                                                    class="bi bi-tag-fill me-1"
                                                ></i>
                                                {{
                                                    item.Nama_Aktivitas
                                                        ? item.Nama_Aktivitas
                                                        : "Tanpa Kategori"
                                                }}
                                            </span>

                                            <span
                                                class="badge rounded-pill px-3 py-2"
                                                :class="
                                                    item.Flag_Perhitungan ===
                                                    'Y'
                                                        ? 'bg-success bg-opacity-10 text-success border border-success'
                                                        : 'bg-light text-muted border'
                                                "
                                            >
                                                <i
                                                    class="bi"
                                                    :class="
                                                        item.Flag_Perhitungan ===
                                                        'Y'
                                                            ? 'bi-calculator-fill'
                                                            : 'bi-dash-circle'
                                                    "
                                                ></i>
                                                {{
                                                    item.Flag_Perhitungan ===
                                                    "Y"
                                                        ? "Ada Perhitungan"
                                                        : "Tanpa Perhitungan"
                                                }}
                                            </span>

                                            <span
                                                v-if="isFLM"
                                                class="badge rounded-pill px-3 py-2"
                                                :class="
                                                    item.Flag_Foto === 'Y'
                                                        ? 'bg-warning bg-opacity-10 text-warning border border-warning'
                                                        : 'bg-light text-muted border'
                                                "
                                            >
                                                <i
                                                    class="bi"
                                                    :class="
                                                        item.Flag_Foto === 'Y'
                                                            ? 'bi-camera-fill'
                                                            : 'bi-camera'
                                                    "
                                                ></i>
                                                {{
                                                    item.Flag_Foto === "Y"
                                                        ? "Wajib Foto"
                                                        : "Tanpa Foto"
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="d-flex flex-column align-items-end ms-3 mt-1"
                                    >
                                        <span
                                            class="badge bg-primary bg-gradient rounded-pill px-3 py-2 fs-6 shadow-sm d-flex align-items-center"
                                        >
                                            <i class="bi bi-database me-2"></i>
                                            {{ item.total_data ?? 0 }} Data
                                        </span>
                                    </div>
                                </div>
                            </a>

                            <nav
                                aria-label="Page navigation"
                                class="mt-4"
                                v-if="pagination.total_pages > 1"
                            >
                                <ul class="pagination justify-content-center">
                                    <li
                                        class="page-item"
                                        :class="{
                                            disabled:
                                                pagination.current_page === 1,
                                        }"
                                    >
                                        <button
                                            class="page-link"
                                            @click="
                                                changePage(
                                                    pagination.current_page - 1
                                                )
                                            "
                                        >
                                            Previous
                                        </button>
                                    </li>

                                    <li
                                        class="page-item"
                                        v-for="page in pagination.total_pages"
                                        :key="page"
                                        :class="{
                                            active:
                                                pagination.current_page ===
                                                page,
                                        }"
                                    >
                                        <button
                                            class="page-link"
                                            @click="changePage(page)"
                                        >
                                            {{ page }}
                                        </button>
                                    </li>

                                    <li
                                        class="page-item"
                                        :class="{
                                            disabled:
                                                pagination.current_page ===
                                                pagination.total_pages,
                                        }"
                                    >
                                        <button
                                            class="page-link"
                                            @click="
                                                changePage(
                                                    pagination.current_page + 1
                                                )
                                            "
                                        >
                                            Next
                                        </button>
                                    </li>
                                </ul>
                            </nav>
                        </div>

                        <div
                            v-if="!listData.length"
                            class="d-flex justify-content-center mt-5"
                        >
                            <div class="flex-column align-content-center">
                                <DotLottieVue
                                    style="height: 200px; width: 200px"
                                    autoplay
                                    loop
                                    src="/animation/empty.lottie"
                                />
                                <p
                                    class="text-center text-muted fw-semibold mt-2"
                                >
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

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
    },
    props: {
        roles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            listData: [],
            loading: {
                loadingListData: false,
            },
            // State tambahan untuk Search & Pagination
            searchQuery: "",
            limit: 5,
            pagination: {
                current_page: 1,
                total_pages: 1,
                total: 0,
            },
        };
    },
    computed: {
        isFLM() {
            return this.roles.some((role) => role.Kode_Role === "FLM");
        },
    },
    methods: {
        async fetchDataJenisAnalisa() {
            this.loading.loadingListData = true;
            try {
                // Menambahkan params untuk dikirim ke backend
                const response = await axios.get(
                    "/fetch/jenis-analisa-current",
                    {
                        params: {
                            search: this.searchQuery,
                            page: this.pagination.current_page,
                            limit: this.limit,
                        },
                    }
                );

                // Menyesuaikan dengan struktur response dari Helper baru
                if (response.status === 200 && response.data?.success) {
                    this.listData = response.data.result;

                    // Update state pagination
                    this.pagination = {
                        current_page: response.data.pagination.current_page,
                        total_pages: response.data.pagination.total_pages,
                        total: response.data.pagination.total,
                    };
                } else {
                    this.listData = [];
                }
            } catch (error) {
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },

        // Method untuk mengeksekusi pencarian
        handleSearch() {
            this.pagination.current_page = 1; // Reset ke halaman 1 setiap kali mencari
            this.fetchDataJenisAnalisa();
        },

        // Method untuk perpindahan halaman
        changePage(page) {
            if (page >= 1 && page <= this.pagination.total_pages) {
                this.pagination.current_page = page;
                this.fetchDataJenisAnalisa();
            }
        },
    },
    mounted() {
        this.fetchDataJenisAnalisa();
    },
};
</script>

<style scoped>
.bg-kode {
    background: #536395;
}
.transition-hover {
    transition: all 0.3s ease-in-out;
    border: 1px solid #e9ecef;
}

.transition-hover:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
    border-color: #0d6efd;
}
</style>
