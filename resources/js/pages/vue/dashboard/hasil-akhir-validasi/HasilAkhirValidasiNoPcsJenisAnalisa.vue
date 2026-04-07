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
                                Kumpulan Data Validasi Hasil Analisis
                            </h1>
                            <p class="text-muted mb-0 subtitle">
                                <i class="fas fa-building me-1"></i>
                                Kumpulan Data Validasi Hasil Analisis
                                laboratorium PT. Evo Manufacturing Indonesia
                            </p>
                        </div>
                    </div>
                    <div class="divider bg-primary opacity-25 my-3"></div>
                </div>

                <div class="informasi-penting">
                    <div class="info-container">
                        <i
                            class="fas fa-exclamation-triangle text-danger fa-lg me-2"
                        ></i>
                        <div class="info-content">
                            <h5 class="info-title text-danger fw-bold">
                                PERINGATAN FINALISASI DATA
                            </h5>
                            <p class="info-text">
                                Data yang ditampilkan adalah hasil input dan
                                submit terakhir dari proses analisa
                                laboratorium. <br /><br />
                                <strong
                                    >Pastikan seluruh data sudah lengkap dan
                                    benar</strong
                                >, termasuk hasil analisa dari setiap parameter
                                yang diperlukan. Jika masih ada data yang belum
                                masuk, proses finalisasi
                                <u>tidak boleh</u> dilakukan. <br /><br />
                                Setelah data dikonfirmasi, sistem akan
                                <span class="text-danger fw-bold"
                                    >mengunci data</span
                                >
                                dan menyebarkan status final ke modul terkait
                                lainnya (monitoring, laporan, validasi akhir,
                                dll). <br /><br />
                                <strong
                                    >Data yang sudah difinalisasi TIDAK DAPAT
                                    DIBATALKAN atau DIEDIT kembali.</strong
                                >
                                Lakukan finalisasi hanya jika Anda sudah 100%
                                yakin bahwa data tersebut telah lengkap, valid,
                                dan sesuai standar laboratorium.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3 content-area">
                    <ListSkeleton :page="5" v-if="loading.loadingListData" />
                    <div class="row" v-else>
                        <template v-if="listData.length">
                            <hr class="mt-3" />
                            <div
                                class="d-flex justify-content-between align-items-start flex-wrap gap-2 w-100"
                            >
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="finalisasiCheck"
                                        v-model="finalisasiDisetujui"
                                    />
                                    <label
                                        class="form-check-label text-danger fw-semibold"
                                        for="finalisasiCheck"
                                    >
                                        Saya bertanggung jawab penuh atas data
                                        yang akan difinalisasi dan telah
                                        melakukan verifikasi menyeluruh.
                                    </label>
                                </div>

                                <div>
                                    <button
                                        @click="submitFinalisasiData(listData)"
                                        class="btn btn-danger d-flex align-items-center gap-1"
                                        :disabled="!finalisasiDisetujui"
                                    >
                                        <i class="fas fa-check-circle"></i>
                                        Finalisasi Data
                                    </button>
                                </div>
                            </div>
                            <div
                                class="col-lg-4 mb-3"
                                v-for="(item, index) in listData"
                                :key="index"
                            >
                                <a
                                    :href="`/hasil-analisa/validasi-close-sampel/moment-final/${item.No_Po_Sampel}/multi/${item.No_Fak_Sub_Po}/${item.Id_Jenis_Analisa}`"
                                >
                                    <div class="analysis-card">
                                        <div class="analysis-icon">
                                            <i class="fas fa-flask"></i>
                                        </div>

                                        <div class="analysis-content">
                                            <div class="analysis-badge">
                                                <span
                                                    class="badge bg-primary-soft"
                                                >
                                                    {{ item.Kode_Analisa }}
                                                </span>
                                            </div>

                                            <h4 class="analysis-title">
                                                {{ item.Jenis_Analisa }}
                                            </h4>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </template>
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
                                Tidak ada data hasil analisis yang tersedia saat
                                ini
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
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import ListSkeleton from "@/pages/vue/ui/ListSkeleton.vue";

export default {
    props: {
        No_Sampel: [String, Number],
    },
    components: {
        ListSkeleton,
        DotLottieVue,
    },
    data() {
        return {
            listData: [],
            listSecondData: [],
            finalisasiDisetujui: false,
            template: [],
            loading: {
                loadingListData: false,
            },
        };
    },

    methods: {
        async fetchConfirmedUjiAnalisa() {
            this.loading.loadingListData = true;
            try {
                const response = await axios.get(
                    `/api/v1/lab/validasi-akhir-close/uji-sampel/${this.No_Sampel}`
                );
                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                } else {
                    this.listData = [];
                }
            } catch (error) {
                console.log(error);
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },
        formatTanggal(tanggalString) {
            const date = new Date(tanggalString);
            const options = { day: "2-digit", month: "short", year: "numeric" };
            return date.toLocaleDateString("en-GB", options);
        },
        async submitFinalisasiData(item) {
            if (!item || item.length === 0) {
                return Swal.fire({
                    icon: "warning",
                    title: "Data Kosong",
                    text: "Tidak ada data yang akan difinalisasi!",
                });
            }

            const konfirmasi = await Swal.fire({
                title: "Yakin Finalisasi Data Ini?",
                html: `
        <strong class="text-danger">PERINGATAN!</strong><br/>
        Finalisasi akan mengunci data secara permanen dan mempengaruhi sistem lain seperti monitoring dan pelaporan.<br/><br/>
        Pastikan seluruh data analisa sudah lengkap dan benar.<br/>
        <strong class="text-warning">Data TIDAK DAPAT diubah atau dibatalkan setelah finalisasi!</strong>
    `,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, finalisasi sekarang",
                cancelButtonText: "Batal",
            });

            if (!konfirmasi.isConfirmed) return;

            Swal.fire({
                title: "Mengirim Data...",
                html: "Mohon tunggu sebentar.",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                await axios.post(
                    `/api/v1/hasil-analisa-close/finalisasi/${this.No_Sampel}`,
                    item
                );

                await Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: "Data berhasil difinalisasi dan dikirim!",
                });

                window.location.href = "/hasil-analisa/validasi-close-sampel";
            } catch (error) {
                console.error("Gagal mengirim data:", error);

                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Terjadi kesalahan saat mengirim data!",
                });
            }
        },
    },

    mounted() {
        this.fetchConfirmedUjiAnalisa();
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

/* Accordion Styles */
.custom-accordion {
    --bs-accordion-border-width: 0;
}

.accordion-item-custom {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.accordion-item-custom:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: rgba(13, 110, 253, 0.2);
}

.accordion-btn {
    background-color: #ffffff;
    box-shadow: none;
}

.accordion-btn:not(.collapsed) {
    background-color: rgba(13, 110, 253, 0.05);
    color: #0d6efd;
}

.accordion-btn:focus {
    box-shadow: none;
    border-color: rgba(13, 110, 253, 0.2);
}

.icon-wrapper {
    transition: all 0.3s ease;
}

.accordion-btn:hover .icon-wrapper {
    background-color: rgba(13, 110, 253, 0.15);
}

.analysis-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-text {
    min-width: 0;
}

.badge-container {
    flex-wrap: wrap;
}

.code-badge {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
    display: inline-flex;
    align-items: center;
}

.date-badge {
    display: inline-flex;
    align-items: center;
}

.analysis-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.action-buttons {
    flex-shrink: 0;
}

.confirm-btn {
    transition: all 0.2s ease;
    min-width: 110px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.confirm-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(25, 135, 84, 0.2);
}

.menu-btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.menu-btn:hover {
    background-color: rgba(108, 117, 125, 0.1);
}

/* Accordion Body Styles */
.inner-accordion-body {
    background-color: #f9fafb;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.loading-spinner {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Tab Styles */
.result-tabs {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.nav-tabs-custom .nav-link {
    border: none;
    padding: 0.75rem 1.5rem;
    color: #6c757d;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    position: relative;
    margin-bottom: -1px;
}

.nav-tabs-custom .nav-link.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background-color: transparent;
}

.nav-tabs-custom .nav-link:hover:not(.active) {
    color: #495057;
    border-bottom-color: rgba(13, 110, 253, 0.2);
}

/* Nested Accordion Styles */
.nested-accordion-item {
    background-color: #ffffff;
    border-radius: 8px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    margin-bottom: 12px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.nested-accordion-btn {
    border-radius: 8px !important;
    padding: 0.75rem 1.25rem;
}

.nested-accordion-btn:not(.collapsed) {
    background-color: rgba(13, 110, 253, 0.05);
    color: #0d6efd;
}

.nested-accordion-body {
    padding: 1.25rem;
    border-radius: 0 0 8px 8px;
}

/* Detail Card Styles */
.detail-card {
    background-color: transparent;
}

.detail-header {
    border-radius: 8px 8px 0 0 !important;
}

.detail-title {
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.time-value {
    font-size: 0.85em;
}

/* Action Buttons */
.action-buttons-bottom {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.complete-btn {
    transition: all 0.3s ease;
    min-width: 180px;
}

.complete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.25);
}

/* Empty State Styles */
.empty-state {
    animation: fadeIn 0.5s ease;
}

.empty-title {
    font-size: 1.25rem;
    font-weight: 500;
}

.empty-message {
    max-width: 400px;
    margin: 0 auto;
}

.empty-action {
    transition: all 0.3s ease;
    padding: 0.5rem 1.5rem;
}

.empty-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .header-icon {
        margin-bottom: 1rem;
    }

    .accordion-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .action-buttons {
        margin-top: 1rem;
        width: 100%;
        justify-content: flex-end;
    }

    .detail-col {
        width: 100%;
    }

    .detail-row {
        flex-direction: column;
    }
}

@media (max-width: 576px) {
    .main-title {
        font-size: 1.5rem;
    }

    .nav-tabs-custom .nav-link {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .complete-btn {
        width: 100%;
    }
}
</style>

<style>
.informasi-penting {
    margin: 1.5rem 0;
    animation: fadeIn 0.5s ease;
}

.info-container {
    background-color: rgba(13, 110, 253, 0.08);
    border-left: 4px solid #0d6efd;
    border-radius: 0 8px 8px 0;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    transition: all 0.3s ease;
}

.info-container:hover {
    background-color: rgba(13, 110, 253, 0.12);
    transform: translateX(3px);
}

.info-icon {
    color: #0d6efd;
    font-size: 1.5rem;
    margin-right: 1rem;
    margin-top: 0.2rem;
}

.info-content {
    flex: 1;
}

.info-title {
    color: #0d6efd;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.info-text {
    color: #495057;
    line-height: 1.7;
    margin-bottom: 0.75rem;
}

.info-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
    }
}

@media (max-width: 768px) {
    .info-container {
        flex-direction: column;
    }

    .info-icon {
        margin-bottom: 0.5rem;
    }
}

.badge {
    transition: all 0.2s ease;
}

.badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}
</style>

<style>
:root {
    --warna-primer: #4361ee;
    --warna-sekunder: #3f37c9;
    --warna-sukses: #4cc9f0;
    --warna-info: #4895ef;
    --warna-peringatan: #f72585;
    --warna-bahaya: #b5179e;
    --warna-latar: #f8f9fa;
    --warna-gelap: #212529;
    --warna-teks-primer: #2b2d42;
    --warna-teks-sekunder: #8d99ae;
    --radius-border: 12px;
    --bayangan: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transisi: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.header-kalkulator {
    text-align: center;
    margin-bottom: 1rem;
    padding-bottom: 1.5rem;
}

.judul-kalkulator {
    font-size: 2.2rem;
    font-weight: 700;
    color: #3f5189;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.deskripsi-kalkulator {
    font-size: 1.1rem;
    color: #35477b;
    max-width: 700px;
    margin: 0 auto;
}

.isi-dokumentasi {
    margin-bottom: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

/* Base Styles */
.calculation-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    color: #333;
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* Section Headers */
.section-header {
    margin-bottom: 1.5rem;
}

.section-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.section-badge i {
    margin-right: 0.5rem;
}

.formula-badge {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    border-left: 4px solid #0d6efd;
}

.result-badge {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
    border-left: 4px solid #198754;
}

.section-description {
    font-size: 0.9rem;
    color: #6c757d;
    margin-left: 0.25rem;
}

/* Parameter Table */
.parameter-table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.responsive-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.parameter-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.parameter-table th {
    background-color: #f8f9fa;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
}

.parameter-table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.parameter-index {
    font-weight: 500;
    color: #6c757d;
    width: 50px;
}

.parameter-name {
    font-weight: 500;
    min-width: 200px;
}

.parameter-unit {
    color: #6c757d;
    font-size: 0.85em;
    margin-left: 0.25rem;
}

.parameter-input-cell {
    min-width: 200px;
}

.input-group {
    display: flex;
    align-items: stretch;
}

.parameter-input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px 0 0 4px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.parameter-input:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.unit-display {
    background-color: #e9ecef;
    border: 1px solid #ced4da;
    border-left: 0;
    padding: 0.5rem 0.75rem;
    border-radius: 0 4px 4px 0;
    color: #495057;
}

/* Results Section */
.results-container {
    display: grid;
    gap: 1rem;
}

.result-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    border-left: 4px solid #198754;
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.result-title {
    font-weight: 600;
    color: #212529;
    display: flex;
    align-items: center;
}

.result-title i {
    color: #198754;
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

.result-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #198754;
    background-color: rgba(25, 135, 84, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    min-width: 80px;
    text-align: center;
}

.result-notes {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.notes-header {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.notes-header i {
    margin-right: 0.5rem;
}

.notes-content {
    font-size: 0.9rem;
    color: #495057;
    line-height: 1.5;
}

.result-footer {
    display: flex;
    justify-content: flex-end;
}

.calculation-method {
    font-size: 0.8rem;
    color: #6c757d;
}

.method-label {
    font-weight: 500;
    margin-right: 0.25rem;
}

/* Highlight Effect */
.parameter-row.highlighted {
    background-color: rgba(13, 110, 253, 0.05);
    transition: background-color 0.3s ease;
}

/* Responsive Layout */
@media (min-width: 992px) {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 1200px) {
    .calculation-container {
        grid-template-columns: 2fr 1fr;
    }
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.result-card {
    animation: fadeIn 0.3s ease-out forwards;
}

/* Print Styles */
@media print {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }

    .parameter-table-container,
    .result-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* animasi skeleton */
@keyframes pulseSkeleton {
    0% {
        background-color: #e0e0e0;
    }
    50% {
        background-color: #f0f0f0;
    }
    100% {
        background-color: #e0e0e0;
    }
}

.skeleton {
    animation: pulseSkeleton 1.5s infinite;
    border-radius: 8px;
}
.skeleton-image {
    width: 100%;
    height: 200px;
    margin-bottom: 16px;
}

.analysis-container {
    background: rgba(255, 255, 255, 0.98);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.analysis-container:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
}

.section-title {
    font-weight: 800;
    font-size: 1.25rem;
    position: relative;
    padding-bottom: 16px;
    margin-bottom: 24px;
    color: #495057; /* Updated to use #495057 */
    letter-spacing: -0.5px;
}

.section-title::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    width: 80px;
    height: 5px;
    background: #495057;
    border-radius: 5px;
    box-shadow: 0 2px 8px rgba(19, 24, 50, 0.3);
}

.text-gradient {
    background: #495057;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* Base Styles */
.cleaning-system-container {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8fafc;
    min-height: 100vh;
    color: #334155;
}

.system-header {
    background: linear-gradient(135deg, #456290 0%, #25335e 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header-content {
    flex: 1;
}

.system-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.system-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0.25rem 0 0;
    font-weight: 400;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.btn-help {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-help:hover {
    background: rgba(255, 255, 255, 0.2);
}

.content-wrapper {
    max-width: 100%;
    margin: 2rem auto;
    padding: 0 2rem;
}

/* Panel Styles */
.search-panel,
.details-panel,
.template-panel,
.form-panel {
    background: white;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.panel-header {
    padding: 1.25rem 1.5rem;
    background-color: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header.with-tabs {
    border-bottom: none;
}

.panel-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.panel-body {
    padding: 1.5rem;
}

/* Search Form */
.search-form {
    max-width: 600px;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #475569;
}

.input-with-button {
    display: flex;
    gap: 0.5rem;
}

.form-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
}

.btn-search {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-search:hover {
    background-color: #2563eb;
}

/* Detail Grid */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-label {
    font-weight: 500;
    color: #64748b;
}

.detail-value {
    font-weight: 500;
    color: #1e293b;
}

.detail-value.highlight {
    color: #3b82f6;
    font-weight: 600;
}

.status-badge {
    display: flex;
    gap: 0.5rem;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge.active {
    background-color: #d1fae5;
    color: #065f46;
}

.badge.priority {
    background-color: #3eb1df;
    color: #ffffff;
}

/* Notes Section */
.notes-section {
    background-color: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1.5rem;
    border-left: 4px solid #60a5fa;
}

.notes-header {
    margin-bottom: 0.5rem;
}

.notes-label {
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notes-content {
    color: #475569;
    line-height: 1.5;
}

/* Form Panels */
.form-panel .panel-header {
    background-color: #f8fafc;
}

.btn-add {
    background-color: #10b981;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-add:hover {
    background-color: #059669;
}

.btn-add-param {
    background-color: #f59e0b;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    margin-right: 0.5rem;
}

.btn-add-param:hover {
    background-color: #d97706;
}

/* Multi Table */
.multi-table {
    overflow-x: auto;
}

.multi-table table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.multi-table th {
    background-color: #f1f5f9;
    color: #475569;
    font-weight: 600;
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.multi-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.multi-table input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.multi-table input:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}

.multi-table td.actions {
    text-align: center;
}
/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f1f5f9;
}

.btn-submit {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-submit:hover {
    background-color: #2563eb;
}

.btn-save {
    background-color: #8b5cf6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-save:hover {
    background-color: #7c3aed;
}

.modern-form {
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    overflow: hidden;

    margin: 0 auto;
}

.sample-info-card {
    background: #f8f9ff;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    gap: 32px;
    border: 1px solid #e0e7ff;
}

.info-item {
    display: flex;
    align-items: center;
}

.info-label {
    font-weight: 500;
    color: #4b5563;
    margin-right: 8px;
}

.info-value {
    font-weight: 600;
    color: #1e293b;
}

/* Modern Table Styles */
.analysis-table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.modern-analysis-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}
</style>

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

/* Analysis Card */
.analysis-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(240, 240, 240, 0.8);
    display: flex;
    align-items: flex-start;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.analysis-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.4),
        transparent
    );
    transition: 0.5s;
}

.analysis-card:hover::before {
    left: 100%;
}

.analysis-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 32px rgba(67, 97, 238, 0.2);
    border-color: rgba(67, 97, 238, 0.3);
}

.analysis-card:hover .analysis-icon {
    transform: rotate(10deg) scale(1.1);
    box-shadow: 0 8px 24px rgba(67, 97, 238, 0.3);
}

.analysis-icon {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white;
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    flex-shrink: 0;
    font-size: 1.5rem;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 6px 16px rgba(67, 97, 238, 0.2);
}

.analysis-content {
    flex: 1;
}

.analysis-badge {
    margin-bottom: 12px;
}

.badge.bg-primary-soft {
    background-color: rgba(67, 97, 238, 0.15);
    color: #495057; /* Updated to use #495057 */
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.analysis-card:hover .badge.bg-primary-soft {
    background-color: rgba(67, 97, 238, 0.25);
}

.analysis-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 12px;
    color: #495057; /* Updated to use #495057 */
    transition: all 0.3s ease;
}

.analysis-meta {
    font-size: 0.9rem;
    color: #495057; /* Updated to use #495057 */
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.meta-item i {
    font-size: 1rem;
    color: #4361ee; /* Only the icon keeps the accent color */
}

.analysis-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
    animation: fadeIn 0.3s ease;
}

.btn-outline-primary {
    border: 2px solid #4361ee;
    color: #495057; /* Updated to use #495057 */
    transition: all 0.3s ease;
    border-radius: 8px;
    padding: 6px 16px;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
}

.btn-outline-primary:hover {
    background-color: #4361ee;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Extended Active Card Styles with 10 color variations */
.analysis-card.active {
    transform: translateY(-4px);
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.15);
    border-color: transparent;
    position: relative;
    z-index: 1;
}

/* Color variations for 10 different cards */
.analysis-card.active:nth-child(10n + 1) {
    border-left: 4px solid #4361ee;
    background: linear-gradient(to right, rgba(67, 97, 238, 0.05), white);
}

.analysis-card.active:nth-child(10n + 2) {
    border-left: 4px solid #3a0ca3;
    background: linear-gradient(to right, rgba(58, 12, 163, 0.05), white);
}

.analysis-card.active:nth-child(10n + 3) {
    border-left: 4px solid #7209b7;
    background: linear-gradient(to right, rgba(114, 9, 183, 0.05), white);
}

.analysis-card.active:nth-child(10n + 4) {
    border-left: 4px solid #f72585;
    background: linear-gradient(to right, rgba(247, 37, 133, 0.05), white);
}

.analysis-card.active:nth-child(10n + 5) {
    border-left: 4px solid #4cc9f0;
    background: linear-gradient(to right, rgba(76, 201, 240, 0.05), white);
}

.analysis-card.active:nth-child(10n + 6) {
    border-left: 4px solid #4895ef;
    background: linear-gradient(to right, rgba(72, 149, 239, 0.05), white);
}

.analysis-card.active:nth-child(10n + 7) {
    border-left: 4px solid #560bad;
    background: linear-gradient(to right, rgba(86, 11, 173, 0.05), white);
}

.analysis-card.active:nth-child(10n + 8) {
    border-left: 4px solid #b5179e;
    background: linear-gradient(to right, rgba(181, 23, 158, 0.05), white);
}

.analysis-card.active:nth-child(10n + 9) {
    border-left: 4px solid #f15bb5;
    background: linear-gradient(to right, rgba(241, 91, 181, 0.05), white);
}

.analysis-card.active:nth-child(10n + 10) {
    border-left: 4px solid #2ec4b6;
    background: linear-gradient(to right, rgba(46, 196, 182, 0.05), white);
}

/* Active icon styles for 10 variations */
.analysis-card.active .analysis-icon {
    transform: scale(1.1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.analysis-card.active:nth-child(10n + 1) .analysis-icon {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
}

.analysis-card.active:nth-child(10n + 2) .analysis-icon {
    background: linear-gradient(135deg, #3a0ca3, #7209b7);
}

.analysis-card.active:nth-child(10n + 3) .analysis-icon {
    background: linear-gradient(135deg, #7209b7, #f72585);
}

.analysis-card.active:nth-child(10n + 4) .analysis-icon {
    background: linear-gradient(135deg, #f72585, #4361ee);
}

.analysis-card.active:nth-child(10n + 5) .analysis-icon {
    background: linear-gradient(135deg, #4cc9f0, #4895ef);
}

.analysis-card.active:nth-child(10n + 6) .analysis-icon {
    background: linear-gradient(135deg, #4895ef, #560bad);
}

.analysis-card.active:nth-child(10n + 7) .analysis-icon {
    background: linear-gradient(135deg, #560bad, #b5179e);
}

.analysis-card.active:nth-child(10n + 8) .analysis-icon {
    background: linear-gradient(135deg, #b5179e, #f15bb5);
}

.analysis-card.active:nth-child(10n + 9) .analysis-icon {
    background: linear-gradient(135deg, #f15bb5, #2ec4b6);
}

.analysis-card.active:nth-child(10n + 10) .analysis-icon {
    background: linear-gradient(135deg, #2ec4b6, #4361ee);
}

/* Active badge styles for 10 variations */
.analysis-card.active .badge.bg-primary-soft {
    color: white;
    font-weight: 800;
}

.analysis-card.active:nth-child(10n + 1) .badge.bg-primary-soft {
    background-color: #4361ee;
}

.analysis-card.active:nth-child(10n + 2) .badge.bg-primary-soft {
    background-color: #3a0ca3;
}

.analysis-card.active:nth-child(10n + 3) .badge.bg-primary-soft {
    background-color: #7209b7;
}

.analysis-card.active:nth-child(10n + 4) .badge.bg-primary-soft {
    background-color: #f72585;
}

.analysis-card.active:nth-child(10n + 5) .badge.bg-primary-soft {
    background-color: #4cc9f0;
}

.analysis-card.active:nth-child(10n + 6) .badge.bg-primary-soft {
    background-color: #4895ef;
}

.analysis-card.active:nth-child(10n + 7) .badge.bg-primary-soft {
    background-color: #560bad;
}

.analysis-card.active:nth-child(10n + 8) .badge.bg-primary-soft {
    background-color: #b5179e;
}

.analysis-card.active:nth-child(10n + 9) .badge.bg-primary-soft {
    background-color: #f15bb5;
}

.analysis-card.active:nth-child(10n + 10) .badge.bg-primary-soft {
    background-color: #2ec4b6;
}

/* Active title styles */
.analysis-card.active .analysis-title {
    color: #212529;
    font-weight: 800;
    position: relative;
}

/* Add small indicator to active title */
.analysis-card.active .analysis-title::after {
    content: "";
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 40px;
    height: 3px;
    border-radius: 3px;
}

.analysis-card.active:nth-child(10n + 1) .analysis-title::after {
    background-color: #4361ee;
}

.analysis-card.active:nth-child(10n + 2) .analysis-title::after {
    background-color: #3a0ca3;
}

.analysis-card.active:nth-child(10n + 3) .analysis-title::after {
    background-color: #7209b7;
}

.analysis-card.active:nth-child(10n + 4) .analysis-title::after {
    background-color: #f72585;
}

.analysis-card.active:nth-child(10n + 5) .analysis-title::after {
    background-color: #4cc9f0;
}

.analysis-card.active:nth-child(10n + 6) .analysis-title::after {
    background-color: #4895ef;
}

.analysis-card.active:nth-child(10n + 7) .analysis-title::after {
    background-color: #560bad;
}

.analysis-card.active:nth-child(10n + 8) .analysis-title::after {
    background-color: #b5179e;
}

.analysis-card.active:nth-child(10n + 9) .analysis-title::after {
    background-color: #f15bb5;
}

.analysis-card.active:nth-child(10n + 10) .analysis-title::after {
    background-color: #2ec4b6;
}

/* Different pulse animations for each color */
.analysis-card.active:nth-child(10n + 1) {
    animation: pulse-blue 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 2) {
    animation: pulse-purple 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 3) {
    animation: pulse-violet 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 4) {
    animation: pulse-pink 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 5) {
    animation: pulse-cyan 1.5s ease infinite;
}

@keyframes pulse-blue {
    0% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(67, 97, 238, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
    }
}

@keyframes pulse-purple {
    0% {
        box-shadow: 0 0 0 0 rgba(58, 12, 163, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(58, 12, 163, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(58, 12, 163, 0);
    }
}

@keyframes pulse-violet {
    0% {
        box-shadow: 0 0 0 0 rgba(114, 9, 183, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(114, 9, 183, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(114, 9, 183, 0);
    }
}

@keyframes pulse-pink {
    0% {
        box-shadow: 0 0 0 0 rgba(247, 37, 133, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(247, 37, 133, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(247, 37, 133, 0);
    }
}

@keyframes pulse-cyan {
    0% {
        box-shadow: 0 0 0 0 rgba(76, 201, 240, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(76, 201, 240, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(76, 201, 240, 0);
    }
}
</style>
