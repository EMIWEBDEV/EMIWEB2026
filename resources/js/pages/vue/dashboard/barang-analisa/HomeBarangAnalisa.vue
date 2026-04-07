<template>
    <div class="container-fluid px-0">
        <div class="card shadow-sm border-0 w-100">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Barang Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Barang Berdasarkan Jenis Analisa PT. Evo
                        Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div
                    class="d-flex justify-content-center justify-content-lg-start"
                >
                    <a
                        href="/barang-jenis/analisa/create"
                        class="btn btn-primary"
                        type="button"
                    >
                        + Tambah Barang Analisa Jenis Analisa
                    </a>
                </div>

                <div class="col-12 mt-3">
                    <ListSkeleton :page="5" v-if="loading.loadingListData" />

                    <div class="list-group" v-else>
                        <div v-if="listData.length">
                            <a
                                :href="
                                    '/barang-jenis-analisa/show/' +
                                    item.Id_Jenis_Analisa
                                "
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-3"
                                v-for="(item, index) in listData"
                                :key="index"
                            >
                                <div>
                                    <i
                                        class="bi bi-hdd-network text-primary me-2"
                                    ></i>
                                    <div class="fw-bold text-dark">
                                        {{ item.kode_analisa }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ item.jenis_analisa }}
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

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
    },
    data() {
        return {
            listData: [],
            loading: {
                loadingListData: false,
            },
        };
    },
    methods: {
        async fetchDataJenisAnalisa() {
            this.loading.loadingListData = true;
            try {
                const response = await axios.get(
                    "/api/v1/barang-jenis-analisa/current"
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
    },
    mounted() {
        this.fetchDataJenisAnalisa();
    },
};
</script>
