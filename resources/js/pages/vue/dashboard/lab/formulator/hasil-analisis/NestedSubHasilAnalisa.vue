<template>
    <div class="container-fluid px-0 data-uji-container">
        <div class="card shadow-sm border-0 w-100 main-card">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start section-header">
                    <div class="d-flex align-items-center mb-3 header-content">
                        <i
                            class="fas fa-vial text-primary me-3 fa-2x header-icon"
                        ></i>
                        <div>
                            <h1 class="h2 fw-bold text-primary mb-1 main-title">
                                Kumpulan Data Hasil Trial
                            </h1>
                            <p class="text-muted mb-0 subtitle">
                                <i class="fas fa-building me-1"></i>
                                Koleksi lengkap data analisis laboratorium PT.
                                Evo Manufacturing Indonesia
                            </p>
                        </div>
                    </div>
                    <div class="divider bg-primary opacity-25 my-3"></div>
                </div>

                <ListSkeleton :page="5" v-if="loading.loadingListData" />

                <div class="col-12 mt-3 content-area" v-else>
                    <div v-if="listData.length">
                        <div class="d-flex align-items-center mb-3">
                            <h6
                                class="mb-0 flex-grow-1 fw-semibold text-primary"
                            >
                                <i class="fas fa-list-check me-2"></i>Daftar
                                Nomor PO & Sub PO Sampel
                            </h6>
                        </div>

                        <a
                            v-for="(item, index) in listData"
                            :key="index"
                            :href="`/formulator/hasil-trial/multi/${id_jenis_analisa}/${item.No_Po_Sampel}/${flag_multi}/${item.No_Fak_Sub_Po}`"
                            class="list-group-item list-group-item-action d-flex justify-between align-items-center mb-3"
                        >
                            <div>
                                <div class="fw-bold text-dark mb-1">
                                    <i
                                        class="fas fa-file-alt text-primary me-2"
                                    ></i>

                                    {{ item.No_Po_Sampel }}
                                </div>

                                <div
                                    class="small text-muted d-flex align-items-center"
                                >
                                    <span class="badge bg-primary ms-2">{{
                                        item.No_Fak_Sub_Po
                                    }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div
                        v-if="!listData.length"
                        class="text-center py-5 empty-state"
                    >
                        <div
                            class="d-flex justify-content-center mb-3 empty-animation"
                        >
                            <DotLottieVue
                                style="height: 200px; width: 200px"
                                autoplay
                                loop
                                src="/animation/empty.lottie"
                            />
                        </div>
                        <h5 class="text-muted mb-2 empty-title">
                            Data Tidak Ditemukan
                        </h5>
                        <p class="text-muted empty-message">
                            Tidak ada data hasil analisis yang tersedia saat ini
                        </p>
                        <button class="btn btn-primary mt-3 empty-action">
                            <i class="fas fa-sync-alt me-1"></i>
                            Muat Ulang Data
                        </button>
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
    props: {
        id_jenis_analisa: {
            type: [String, Number],
            default: null,
        },
        no_po_sampel: {
            type: [String, Number],
            default: null,
        },
        flag_multi: {
            type: [String, Number],
            default: null,
        },
    },
    components: {
        DotLottieVue,
        ListSkeleton,
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
        async fetchHasilAnalisaByJenisAnalisa() {
            this.loading.loadingListData = true;
            try {
                const response = await axios.get(
                    `/api/v1/formulator/hasil-trial/sub/${this.id_jenis_analisa}/${this.no_po_sampel}`
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
        this.fetchHasilAnalisaByJenisAnalisa();
    },
};
</script>

<style scoped>
.skeleton {
    animation: pulse 1.5s infinite;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 37%, #f0f0f0 63%);
    background-size: 400% 100%;
    border-radius: 4px;
}

@keyframes pulse {
    0% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0 50%;
    }
}

.skeleton-line {
    height: 20px;
    margin-bottom: 10px;
}

.skeleton-btn {
    height: 40px;
    width: 100%;
    margin-bottom: 15px;
}

.skeleton-table-cell {
    height: 25px;
    margin: 5px 0;
}

/* Container Styles */
.data-uji-container {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.main-card {
    border-radius: 12px;
    overflow: hidden;
    background-color: #ffffff;
}

.section-header {
    padding: 0 1.5rem;
}

.header-content {
    padding-top: 1rem;
}

.header-icon {
    transition: transform 0.3s ease;
}

.header-icon:hover {
    transform: scale(1.1);
}

.main-title {
    font-size: 1.75rem;
    letter-spacing: -0.5px;
}

.subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
}

.divider {
    height: 1px;
    background: linear-gradient(
        90deg,
        rgba(13, 110, 253, 0.1) 0%,
        rgba(13, 110, 253, 0.5) 50%,
        rgba(13, 110, 253, 0.1) 100%
    );
}
</style>
