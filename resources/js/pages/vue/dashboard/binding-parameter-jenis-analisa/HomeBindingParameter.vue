<template>
    <div class="container-fluid px-0">
        <div class="card shadow-sm border-0 w-100">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Parameter Jenis Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Parameter Berdasarkan Jenis Analisa PT. Evo
                        Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div
                    class="d-flex justify-content-center justify-content-lg-start"
                >
                    <a
                        href="/binding-jenis-analisa/create/form"
                        class="btn btn-primary"
                        type="button"
                    >
                        + Tambah Parameter Jenis Analisa
                    </a>
                </div>

                <div class="col-12 mt-3">
                    <ListSkeleton :page="5" v-if="loading.loadingListData" />

                    <div class="list-group" v-else>
                        <div v-if="listData.length">
                            <div
                                v-for="(item, index) in listData"
                                :key="index"
                                class="list-group-item list-group-item-action position-relative d-flex justify-content-between align-items-center p-3 mb-3 border rounded shadow-sm bg-white"
                            >
                                <div class="d-flex align-items-center">
                                    <div
                                        class="bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center rounded me-3"
                                        style="width: 48px; height: 48px"
                                    >
                                        <i
                                            class="fas fa-network-wired fs-5"
                                        ></i>
                                    </div>

                                    <div>
                                        <a
                                            :href="
                                                '/binding-jenis-analisa/' +
                                                (item.Id_Jenis_Analisa ||
                                                    item.id)
                                            "
                                            class="fw-bold text-dark text-decoration-none stretched-link d-block mb-1"
                                        >
                                            {{ item.kode_analisa }}
                                        </a>
                                        <div class="small text-muted">
                                            {{ item.jenis_analisa }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="d-flex align-items-center gap-3 position-relative"
                                    style="z-index: 2"
                                >
                                    <span
                                        class="badge bg-primary bg-gradient rounded-pill px-3 py-2 shadow-sm"
                                    >
                                        {{ item.total_data ?? 0 }} Parameter
                                    </span>

                                    <a
                                        :href="
                                            '/binding-jenis-analisa/edit/form/' +
                                            (item.Id_Jenis_Analisa || item.id)
                                        "
                                        class="btn btn-sm btn-outline-warning d-flex align-items-center fw-semibold shadow-sm bg-white"
                                    >
                                        <i class="bi bi-pencil-square me-1"></i>
                                        Edit
                                    </a>
                                </div>
                            </div>
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
                    "/fetch/binding-jenis-analisa"
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
