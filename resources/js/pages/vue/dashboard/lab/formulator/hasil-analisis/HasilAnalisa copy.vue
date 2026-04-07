<template>
    <div class="container-fluid px-0 data-uji-container">
        <!-- Print Modal -->
        <div
            class="modal fade"
            id="printModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title text-white">
                            <i class="fas fa-print me-2"></i>
                            Cetak Laporan Analisis
                        </h5>
                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            @click="closePrintModal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <!-- Step Progress Bar -->
                        <div class="steps-progress mb-4">
                            <div
                                class="step"
                                :class="{ active: currentStep === 1 }"
                            >
                                <div class="step-number">1</div>
                                <div class="step-label">
                                    Pilih Jenis Analisa
                                </div>
                            </div>
                            <div
                                class="step"
                                :class="{ active: currentStep === 2 }"
                            >
                                <div class="step-number">2</div>
                                <div class="step-label">Atur Periode</div>
                            </div>
                            <div
                                class="step"
                                :class="{ active: currentStep === 3 }"
                            >
                                <div class="step-number">3</div>
                                <div class="step-label">Preview & Cetak</div>
                            </div>
                        </div>

                        <div
                            class="alert alert-warning d-flex align-items-start gap-2"
                            role="alert"
                        >
                            <i
                                class="fas fa-exclamation-triangle text-warning fa-lg mt-1"
                            ></i>
                            <div>
                                <strong>Perhatian!</strong><br />
                                Data yang akan dicetak hanya mencakup
                                <strong
                                    >nomor sampel yang sudah ditutup
                                    (closed)</strong
                                >
                                atau <strong>telah difinalisasi</strong>.
                                <br />
                                Jika Anda belum memfinalisasi data, silakan
                                lakukan pengecekan terlebih dahulu melalui
                                halaman berikut:
                                <br />
                                <a
                                    href="/lab/hasil-analisa"
                                    class="fw-bold text-decoration-underline"
                                    >Lihat Data Belum Finalisasi</a
                                >
                            </div>
                        </div>

                        <!-- Step 1: Select Analysis Type -->
                        <div v-show="currentStep === 1" class="step-content">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="fas fa-filter me-2"></i>
                                Pilih Jenis Analisa
                            </h6>
                            <div class="analysis-selector">
                                <div
                                    v-for="item in listDataJenisAnalisa"
                                    :key="item.id"
                                    class="analysis-option"
                                    :class="{
                                        selected: selectedAnalysis === item.id,
                                    }"
                                    @click="toggleAnalysisSelection(item)"
                                >
                                    <div class="option-icon">
                                        <i class="fas fa-flask"></i>
                                    </div>
                                    <div class="option-details">
                                        <span
                                            class="badge bg-primary-soft mb-1"
                                        >
                                            {{ item.Kode_Analisa }}
                                        </span>
                                        <h6>{{ item.Jenis_Analisa }}</h6>
                                    </div>
                                    <div class="option-check">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Set Date Range -->
                        <div v-show="currentStep === 2" class="step-content">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="far fa-calendar-alt me-2"></i>
                                Atur Periode Laporan
                            </h6>
                            <div class="date-range-picker">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label"
                                            >Dari Tanggal</label
                                        >
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="far fa-calendar"></i>
                                            </span>
                                            <input
                                                type="date"
                                                class="form-control"
                                                v-model="startDate"
                                                :max="endDate || today"
                                            />
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"
                                            >Sampai Tanggal</label
                                        >
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="far fa-calendar"></i>
                                            </span>
                                            <input
                                                type="date"
                                                class="form-control"
                                                v-model="endDate"
                                                :min="startDate"
                                                :max="today"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Preview & Print -->
                        <div v-show="currentStep === 3" class="step-content">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="fas fa-eye me-2"></i>
                                Ringkasan Laporan
                            </h6>
                            <div class="report-summary">
                                <div class="summary-card">
                                    <div class="summary-header">
                                        <i class="fas fa-info-circle"></i>
                                        <h5>Detail Laporan</h5>
                                    </div>
                                    <div class="summary-body">
                                        <div class="summary-item">
                                            <span>Jenis Analisa:</span>
                                            <strong>
                                                {{
                                                    selectedAnalysisNames || "-"
                                                }}
                                            </strong>
                                        </div>
                                        <div class="summary-item">
                                            <span>Periode:</span>
                                            <strong>
                                                {{ formattedStartDate }} -
                                                {{ formattedEndDate }}
                                            </strong>
                                        </div>
                                        <div class="summary-item">
                                            <span>Format:</span>
                                            <div class="format-options">
                                                <div
                                                    class="form-check form-check-inline"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        id="formatExcel"
                                                        value="excel"
                                                        v-model="exportFormat"
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        for="formatExcel"
                                                    >
                                                        <i
                                                            class="far fa-file-excel text-success me-1"
                                                        ></i>
                                                        Excel
                                                    </label>
                                                </div>
                                                <div
                                                    class="form-check form-check-inline"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        id="formatPdf"
                                                        value="pdf"
                                                        v-model="exportFormat"
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        for="formatPdf"
                                                    >
                                                        <i
                                                            class="far fa-file-pdf text-danger me-1"
                                                        ></i>
                                                        Pdf
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-note mt-3">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Laporan akan dihasilkan berdasarkan
                                        kriteria di atas. Pastikan data sudah
                                        benar sebelum mencetak.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            @click="prevStep"
                            :disabled="currentStep === 1"
                        >
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="nextStep"
                            v-if="currentStep < 3"
                        >
                            Lanjut <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                        <button
                            type="button"
                            class="btn btn-success"
                            @click="generateReport"
                            v-if="currentStep === 3"
                        >
                            <i class="fas fa-file-export me-1"></i> Generate
                            Laporan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 w-100 main-card">
            <div class="card-body">
                <!-- Header Section -->
                <div class="mb-4 text-center text-md-start section-header">
                    <div
                        class="d-flex justify-content-between align-items-start"
                    >
                        <div
                            class="d-flex align-items-center mb-3 header-content"
                        >
                            <i
                                class="fas fa-vial text-primary me-3 fa-2x header-icon"
                            ></i>
                            <div>
                                <h1
                                    class="h2 fw-bold text-primary mb-1 main-title"
                                >
                                    Kumpulan Data Hasil Analisis
                                </h1>
                                <p class="text-muted mb-0 subtitle">
                                    <i class="fas fa-building me-1"></i>
                                    Koleksi lengkap data analisis laboratorium
                                    PT. Evo Manufacturing Indonesia
                                </p>
                            </div>
                        </div>
                        <div class="print-controls">
                            <button
                                @click="togglePrintModal"
                                class="btn btn-print-action"
                                title="Buat Laporan"
                                aria-label="Buat Laporan"
                            >
                                <i class="fas fa-file-export"></i>
                                <span class="btn-text">Buat Laporan</span>
                                <div class="tooltip">
                                    Klik untuk membuat laporan analisis
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="divider bg-primary opacity-25 my-3"></div>
                </div>

                <div class="col-12 mt-3 content-area">
                    <ListSkeleton :page="5" v-if="loading.loadingListData" />
                    <div class="row" v-else>
                        <template v-if="listData.length">
                            <div
                                class="col-lg-4 mb-3"
                                v-for="(item, index) in listData"
                                :key="index"
                            >
                                <a
                                    :href="
                                        '/lab/hasil-analisa/' +
                                        item.Id_Jenis_Analisa
                                    "
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
    components: {
        DotLottieVue,
        ListSkeleton,
    },
    data() {
        return {
            listData: [],
            listDataJenisAnalisa: [],
            loading: {
                loadingListData: false,
                loadingListDataJenisAnalisa: false,
            },
            printModal: null,
            currentStep: 1,
            selectedAnalysis: null,
            selectedIsPerhitungan: null,

            startDate: "",
            endDate: "",
            exportFormat: "excel",
            datePresets: [
                { label: "Minggu Ini", value: "week" },
                { label: "Bulan Ini", value: "month" },
                { label: "3 Bulan Terakhir", value: "3months" },
                { label: "Tahun Ini", value: "year" },
            ],
        };
    },
    watch: {
        currentStep(val) {
            sessionStorage.setItem("printStep", val);
        },
        selectedAnalysis(val) {
            sessionStorage.setItem("printSelectedAnalysis", val ?? "");
        },
        startDate(val) {
            sessionStorage.setItem("printStartDate", val ?? "");
        },
        endDate(val) {
            sessionStorage.setItem("printEndDate", val ?? "");
        },
        exportFormat(val) {
            sessionStorage.setItem("printExportFormat", val);
        },
    },
    computed: {
        today() {
            return new Date().toISOString().split("T")[0];
        },
        formattedStartDate() {
            return this.startDate
                ? new Date(this.startDate).toLocaleDateString("id-ID")
                : "-";
        },
        formattedEndDate() {
            return this.endDate
                ? new Date(this.endDate).toLocaleDateString("id-ID")
                : "-";
        },
        selectedAnalysisNames() {
            const selected = this.listDataJenisAnalisa.find(
                (item) => item.id === this.selectedAnalysis
            );
            return selected ? selected.Jenis_Analisa : null;
        },
    },
    methods: {
        async retryFetch(fn, maxRetry = 2, delay = 500) {
            let attempt = 0;
            let lastError;

            while (attempt <= maxRetry) {
                try {
                    return await fn();
                } catch (error) {
                    lastError = error;
                    attempt++;
                    if (attempt > maxRetry) break;
                    await new Promise((resolve) => setTimeout(resolve, delay));
                }
            }

            throw lastError;
        },

        async fetchHasilAnalisa() {
            this.loading.loadingListData = true;
            try {
                const response = await this.retryFetch(
                    () => axios.get("/api/v1/lab/hasil-analisa/uji-sampel"),
                    2, // Retry maksimal 2 kali
                    500 // Delay 500ms antara percobaan
                );

                if (
                    response.status === 200 &&
                    Array.isArray(response.data?.result)
                ) {
                    this.listData = response.data.result;
                } else {
                    this.listData = [];
                    console.warn("Respons tidak valid atau data kosong.");
                }
            } catch (error) {
                console.error("Gagal mengambil data hasil analisa:", error);
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },
        async fetchJenisAnalisa() {
            this.loading.loadingListDataJenisAnalisa = true;
            try {
                const response = await axios.get(
                    "/jenis-analisa-current/for-select"
                );
                if (response.status === 200 && response.data?.result) {
                    this.listDataJenisAnalisa = response.data.result;
                } else {
                    this.listDataJenisAnalisa = [];
                }
            } catch (error) {
                this.listDataJenisAnalisa = [];
            } finally {
                this.loading.loadingListDataJenisAnalisa = false;
            }
        },
        togglePrintModal() {
            if (!this.printModal) {
                this.printModal = new bootstrap.Modal(
                    document.getElementById("printModal")
                );
            }

            const savedStep = sessionStorage.getItem("printStep");
            const savedAnalysis = sessionStorage.getItem(
                "printSelectedAnalysis"
            );
            const savedStart = sessionStorage.getItem("printStartDate");
            const savedEnd = sessionStorage.getItem("printEndDate");
            const savedFormat = sessionStorage.getItem("printExportFormat");

            this.currentStep = savedStep ? parseInt(savedStep) : 1;
            this.selectedAnalysis = savedAnalysis || null;
            this.startDate = savedStart || "";
            this.endDate = savedEnd || "";
            this.exportFormat = savedFormat || "excel";

            this.printModal.show();
        },

        closePrintModal() {
            this.printModal.hide();
            this.resetPrintForm();

            sessionStorage.removeItem("printStep");
            sessionStorage.removeItem("printSelectedAnalysis");
            sessionStorage.removeItem("printStartDate");
            sessionStorage.removeItem("printEndDate");
            sessionStorage.removeItem("printExportFormat");
        },

        nextStep() {
            if (this.validateCurrentStep()) {
                this.currentStep++;
            }
        },
        prevStep() {
            this.currentStep--;
        },
        validateCurrentStep() {
            if (this.currentStep === 1 && !this.selectedAnalysis) {
                this.showToast(
                    "Peringatan",
                    "Pilih minimal satu jenis analisa",
                    "warning"
                );
                return false;
            }
            if (this.currentStep === 2 && (!this.startDate || !this.endDate)) {
                this.showToast(
                    "Peringatan",
                    "Isi periode tanggal lengkap",
                    "warning"
                );
                return false;
            }
            return true;
        },

        toggleAnalysisSelection(item) {
            if (this.selectedAnalysis === item.id) {
                this.selectedAnalysis = null;
                this.selectedIsPerhitungan = null;
            } else {
                this.selectedAnalysis = item.id;
                this.selectedIsPerhitungan = item.Flag_Perhitungan;
            }
        },

        async generateReport() {
            Swal.fire({
                title: "Mohon Tunggu",
                html: "Laporan sedang diproses dan dibuat...",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                const payload = {
                    analysis: this.selectedAnalysis,
                    Flag_Perhitungan: this.selectedIsPerhitungan,
                    startDate: this.startDate,
                    endDate: this.endDate,
                    format: this.exportFormat,
                };

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                const exportFormat = this.exportFormat;
                let response;

                if (exportFormat === "pdf") {
                    response = await axios.post("/rekap-sampel/pdf", payload, {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                        responseType: "blob",
                    });
                } else {
                    response = await axios.post(
                        "/api/v1/download-rekap/analisa",
                        payload,
                        {
                            headers: {
                                "X-CSRF-TOKEN": csrfToken,
                            },
                            responseType: "blob",
                        }
                    );
                }

                Swal.close();

                const blob = new Blob([response.data], {
                    type: response.headers["content-type"],
                });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement("a");
                link.href = url;

                // Default file name
                let fileName = `laporan-rekap.${
                    exportFormat === "pdf" ? "pdf" : "xlsx"
                }`;

                // Ambil file name dari header jika ada
                const contentDisposition =
                    response.headers["content-disposition"] ||
                    response.headers["Content-Disposition"];

                if (contentDisposition) {
                    const fileNameMatch = contentDisposition.match(
                        /filename\*?=(?:(?:UTF-8'')?["']?)([^;"']+)/i
                    );
                    if (fileNameMatch && fileNameMatch[1]) {
                        fileName = decodeURIComponent(fileNameMatch[1]);
                    }
                }

                link.setAttribute("download", fileName);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);

                this.showToast(
                    "Sukses",
                    "Laporan berhasil diunduh.",
                    "success"
                );
            } catch (error) {
                console.error("Gagal membuat laporan:", error);

                try {
                    const reader = new FileReader();
                    reader.onload = () => {
                        try {
                            const responseText = reader.result;
                            const parsed = JSON.parse(responseText);
                            const message =
                                parsed?.message ||
                                "Terjadi kesalahan saat membuat laporan.";

                            Swal.fire({
                                icon: "warning",
                                title: "Tidak Ditemukan",
                                text: message,
                            });
                        } catch (parseError) {
                            Swal.fire({
                                icon: "error",
                                title: "Gagal Memproses Laporan",
                                text: "Terjadi kesalahan internal. Silakan coba lagi nanti.",
                            });
                        }
                    };
                    reader.readAsText(error.response?.data);
                } catch (readError) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal Memproses Laporan",
                        text: "Terjadi kesalahan tidak terduga.",
                    });
                }
            }
        },

        resetPrintForm() {
            this.currentStep = 1;
            this.selectedAnalysis = null;
            this.selectedIsPerhitungan = null;

            this.startDate = "";
            this.endDate = "";
            this.exportFormat = "excel";
        },
        showToast(title, message, type) {
            console.log(`[${type}] ${title}: ${message}`);
        },
    },
    mounted() {
        this.fetchHasilAnalisa();
        this.fetchJenisAnalisa();

        const savedStep = sessionStorage.getItem("printStep");
        if (savedStep) {
            this.togglePrintModal(); // langsung buka modal jika ada session
        }
    },
};
</script>

<style scoped>
/* Existing styles remain */
.print-controls {
    position: sticky;
    top: 20px;
    z-index: 100;
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
    padding-right: 15px;
}

.btn-print-action {
    background: linear-gradient(135deg, #405189 0%, #384677 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-print-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.btn-print-action:active {
    transform: translateY(0);
}

.btn-print-action i {
    font-size: 1.1rem;
}

.btn-text {
    font-size: 0.95rem;
}

.tooltip {
    position: absolute;
    bottom: -40px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.btn-print-action:hover .tooltip {
    opacity: 1;
}

/* Perbaikan untuk tombol di modal */
.modal-footer .btn {
    min-width: 120px;
    padding: 10px 15px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}
/* New styles for print feature */
.btn-print-floating {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    border-radius: 50px;
    padding: 12px 20px;
    font-weight: 600;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.btn-print-floating:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

/* Steps progress */
.steps-progress {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-bottom: 30px;
}

.steps-progress::before {
    content: "";
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 3px;
    background-color: #e9ecef;
    z-index: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    flex: 1;
}

.step.active .step-number {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.step.active .step-label {
    color: #0d6efd;
    font-weight: 600;
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.step-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-align: center;
}

/* Analysis selector */
.analysis-selector {
    max-height: 400px; /* Atur sesuai kebutuhan */
    overflow-y: auto;
    padding-right: 8px; /* Supaya isi tidak terpotong oleh scrollbar */
}

/* Optional: Kustom scrollbar (untuk Webkit-based browsers seperti Chrome, Edge, Safari) */
.analysis-selector::-webkit-scrollbar {
    width: 8px;
}

.analysis-selector::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.analysis-selector::-webkit-scrollbar-thumb {
    background: #405189;
    border-radius: 4px;
}

.analysis-selector::-webkit-scrollbar-thumb:hover {
    background: #1e2849;
}

.analysis-option {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: white;
}

.analysis-option:hover {
    border-color: #86b7fe;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
}

.analysis-option.selected {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.option-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: rgba(13, 110, 253, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: #0d6efd;
    font-size: 1.1rem;
}

.option-details {
    flex: 1;
}

.option-details h6 {
    margin: 0;
    font-size: 0.95rem;
}

.option-check {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #0d6efd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.analysis-option.selected .option-check {
    opacity: 1;
}

/* Date range picker */
.date-range-picker {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.date-presets {
    background-color: white;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

/* Report summary */
.report-summary {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.summary-card {
    background-color: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    overflow: hidden;
}

.summary-header {
    background-color: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
}

.summary-header i {
    color: #0d6efd;
    margin-right: 10px;
    font-size: 1.1rem;
}

.summary-header h5 {
    margin: 0;
    font-size: 1rem;
}

.summary-body {
    padding: 15px;
}

.summary-item {
    display: flex;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px dashed #e9ecef;
}

.summary-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.summary-item span {
    width: 120px;
    color: #6c757d;
    font-size: 0.9rem;
}

.summary-item strong {
    flex: 1;
    font-weight: 500;
}

.format-options {
    display: flex;
    gap: 15px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .steps-progress {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }

    .steps-progress::before {
        display: none;
    }

    .step {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }

    .step-number {
        margin-bottom: 0;
    }

    .summary-item {
        flex-direction: column;
        gap: 5px;
    }

    .summary-item span {
        width: auto;
    }
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
