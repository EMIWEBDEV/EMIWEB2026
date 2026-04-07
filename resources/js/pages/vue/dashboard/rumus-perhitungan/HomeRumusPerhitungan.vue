<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Rumus Perhitungan Jenis Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Rumus Perhitungan Berdasarkan Jenis Analisa PT.
                        Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <a href="/perhitungan/rumus/create" class="btn btn-primary">
                        + Tambah Rumus Perhitungan
                    </a>
                </div>

                <div class="col-12 mt-3">
                    <ListSkeleton
                        :page="5"
                        v-if="loading.loadingRumusPerhitungan"
                    />
                    <div class="list-group" v-else>
                        <div v-if="dataRumusPerhitungan.length">
                            <a
                                :href="
                                    '/perhitungan-rumus/show/' +
                                    item.Id_Jenis_Analisa
                                "
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-3"
                                v-for="(item, index) in dataRumusPerhitungan"
                                :key="index"
                            >
                                <div>
                                    <i
                                        class="bi bi-hdd-network text-primary me-2"
                                    ></i>
                                    <div class="fw-bold text-dark">
                                        {{ item.jenis_analisa }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ item.kode_analisa }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ item.nama_mesin }}
                                    </div>
                                </div>
                                <span class="badge bg-primary rounded-pill"
                                    >{{ item.total_data ?? 0 }} Data</span
                                >
                            </a>
                        </div>
                        <div
                            v-if="!dataRumusPerhitungan.length"
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
            dataRumusPerhitungan: [],
            loading: {
                loadingRumusPerhitungan: false,
            },
        };
    },
    methods: {
        async fetchDataJenisAnalisa() {
            this.loading.loadingRumusPerhitungan = true;
            try {
                const response = await axios.get("/fetch/perhitungan-rumus");
                if (response.status === 200 && response.data?.result) {
                    this.dataRumusPerhitungan = response.data.result;
                } else {
                    this.dataRumusPerhitungan = [];
                }
            } catch (error) {
                this.dataRumusPerhitungan = [];
            } finally {
                this.loading.loadingRumusPerhitungan = false;
            }
        },
    },
    mounted() {
        this.fetchDataJenisAnalisa();
    },
};
</script>
