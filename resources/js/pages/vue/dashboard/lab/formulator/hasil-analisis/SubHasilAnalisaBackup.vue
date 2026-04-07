<template>
    <div class="container-fluid px-0 data-uji-container">
        <div class="card shadow-sm border-0 w-100 main-card">
            <div class="card-body">
                <!-- Header Section -->
                <div class="mb-4 text-center text-md-start section-header">
                    <div class="d-flex align-items-center mb-3 header-content">
                        <i
                            class="fas fa-vial text-primary me-3 fa-2x header-icon"
                        ></i>
                        <div>
                            <h1 class="h2 fw-bold text-primary mb-1 main-title">
                                Kumpulan Data Hasil Analisis
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
                    <div class="d-flex align-items-center mb-3">
                        <h6 class="mb-0 flex-grow-1 fw-semibold text-primary">
                            <i class="fas fa-list-check me-2"></i>Daftar Nomor
                            PO Sampel
                        </h6>
                    </div>
                    <a
                        v-for="[kode, item] in Object.entries(
                            listData.data_sampel || {}
                        )"
                        :key="kode"
                        :href="`/lab/hasil-analisa/${id_jenis_analisa}/${kode}/${item.flag_multi}`"
                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-3"
                    >
                        <div
                            class="d-flex w-100 justify-content-between align-items-center"
                        >
                            <div class="d-flex align-items-center">
                                <i
                                    class="fas fa-file-alt text-primary me-2"
                                ></i>
                                <span class="fw-bold text-dark">{{
                                    kode
                                }}</span>
                            </div>
                            <div v-if="item.is_selesai === null">
                                <!-- <button
                                    @click.stop.prevent
                                    type="button"
                                    class="btn btn-success d-flex align-items-center"
                                    data-bs-toggle="modal"
                                    data-bs-target="#staticBackdrop"
                                    @click="konfirmasiModal(kode)"
                                >
                                    Close Nomor Sampel
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </button> -->
                            </div>
                        </div>
                    </a>

                    <!-- <div
                        class="modal fade"
                        id="staticBackdrop"
                        data-bs-backdrop="static"
                        data-bs-keyboard="false"
                        tabindex="-1"
                        role="dialog"
                        aria-labelledby="staticBackdropLabel"
                        aria-hidden="true"
                    >
                        <div
                            class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
                            role="document"
                        >
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5
                                        class="modal-title"
                                        id="staticBackdropLabel"
                                    >
                                        <i
                                            class="ri-error-warning-line me-2"
                                        ></i>
                                        Konfirmasi Finalisasi Data Analisis
                                    </h5>
                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
                                        aria-label="Close"
                                    ></button>
                                </div>

                                <div class="modal-body p-4">
                                    <div class="text-center">
                                        <DotLottieVue
                                            style="
                                                height: 150px;
                                                width: 100%;
                                                max-width: 300px;
                                                margin: 0 auto;
                                            "
                                            autoplay
                                            loop
                                            src="/animation/warning-submit.json"
                                        />
                                    </div>

                                    <div class="mt-4">
                                        <h3 class="text-center fw-bold">
                                            Peringatan Penting!
                                        </h3>
                                        <hr />

                                        <p class="fs-15">
                                            Anda akan melakukan finalisasi data
                                            untuk
                                            <strong
                                                >Nomor PO Sampel:
                                                {{ no_po_sampel }}</strong
                                            >. Mohon perhatikan informasi di
                                            bawah ini dengan saksama sebelum
                                            melanjutkan:
                                        </p>

                                        <div
                                            class="alert alert-info bg-light"
                                            role="alert"
                                        >
                                            <h6 class="alert-heading">
                                                <i
                                                    class="ri-calculator-line me-1"
                                                ></i>
                                                Validitas Data
                                            </h6>
                                            <p class="mb-0">
                                                Data hasil analisis yang
                                                ditampilkan telah dihitung dan
                                                diproses secara sistematis
                                                berdasarkan rumus dan standar
                                                yang telah ditetapkan oleh
                                                perusahaan. Sistem telah
                                                memastikan akurasi perhitungan
                                                sesuai input yang diberikan.
                                            </p>
                                        </div>

                                        <div
                                            class="alert alert-danger"
                                            role="alert"
                                        >
                                            <h6 class="alert-heading">
                                                <i
                                                    class="ri-lock-line me-1"
                                                ></i>
                                                Tindakan Final (Tidak Dapat
                                                Dibatalkan)
                                            </h6>
                                            <p>
                                                Dengan menekan tombol
                                                <strong
                                                    >"Ya, Saya Yakin &
                                                    Finalisasi"</strong
                                                >, Anda secara sadar mengunci
                                                data untuk Nomor PO Sampel ini.
                                            </p>
                                            <hr />
                                            <p class="mb-0 fw-bold">
                                                Setelah difinalisasi, Anda atau
                                                siapa pun TIDAK AKAN BISA LAGI
                                                menambah, mengubah, ataupun
                                                menghapus data analisis apa pun
                                                yang terkait dengan Nomor PO
                                                Sampel ini. Status data akan
                                                menjadi permanen.
                                            </p>
                                        </div>

                                        <p class="text-muted fs-14">
                                            Pastikan semua data analisis yang
                                            diperlukan sudah lengkap dan benar
                                            sebelum melanjutkan. Proses ini
                                            tidak dapat diulang atau dibatalkan.
                                        </p>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <div class="form-check me-auto">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="modalConfirmCheckbox"
                                            v-model="isSubmitDone"
                                            required
                                        />
                                        <label
                                            class="form-check-label"
                                            for="checkboxPersetujuan"
                                        >
                                            Saya telah membaca, memahami, dan
                                            bertanggung jawab penuh.
                                        </label>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        data-bs-dismiss="modal"
                                        @click="clearNoPoSampel"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        :disabled="
                                            loading.saveToDatabase ||
                                            !isSubmitDone
                                        "
                                        type="button"
                                        class="btn btn-danger"
                                        id="tombolFinalisasi"
                                        @click="submitForm"
                                    >
                                        <i
                                            class="ri-check-double-line me-1"
                                        ></i>
                                        {{
                                            loading.saveToDatabase
                                                ? "Loading...."
                                                : "Ya, Saya Yakin & Finalisasi"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div> -->

                    <div
                        v-if="
                            !listData.data_sampel ||
                            Object.keys(listData.data_sampel).length === 0
                        "
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
                saveToDatabase: false,
            },
            isSubmitDone: false,
            no_po_sampel: null,
        };
    },
    methods: {
        async fetchHasilAnalisaByJenisAnalisa() {
            this.loading.loadingListData = true;
            try {
                const response = await axios.get(
                    `/api/v1/lab/hasil-analisa/${this.id_jenis_analisa}`
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
