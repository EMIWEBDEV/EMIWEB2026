<template>
    <div class="container-fluid px-0 hasil-analisa-page">

        <!-- ===== PRINT MODAL ===== -->
        <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header" style="background:#405189">
                        <h5 class="modal-title text-white">
                            <i class="fas fa-file-export me-2"></i>Buat Laporan Analisa
                        </h5>
                        <button type="button" class="btn-close btn-close-white" @click="closePrintModal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Step indicator -->
                        <div class="steps-progress mb-4">
                            <div class="step" :class="{ active: currentStep >= 1, done: currentStep > 1 }">
                                <div class="step-number"><i v-if="currentStep > 1" class="fas fa-check"></i><span v-else>1</span></div>
                                <div class="step-label">Pilih Jenis Analisa</div>
                            </div>
                            <div class="step" :class="{ active: currentStep >= 2, done: currentStep > 2 }">
                                <div class="step-number"><i v-if="currentStep > 2" class="fas fa-check"></i><span v-else>2</span></div>
                                <div class="step-label">Atur Periode</div>
                            </div>
                            <div class="step" :class="{ active: currentStep >= 3 }">
                                <div class="step-number">3</div>
                                <div class="step-label">Preview & Cetak</div>
                            </div>
                        </div>

                        <!-- Step 1 -->
                        <div v-show="currentStep === 1">
                            <h6 class="fw-semibold mb-3"><i class="fas fa-filter me-2 text-primary"></i>Pilih Jenis Analisa</h6>
                            <div class="analysis-selector">
                                <div v-for="item in listDataJenisAnalisa" :key="item.id"
                                     class="analysis-option"
                                     :class="{ selected: selectedAnalysis.includes(item.id) }"
                                     @click="toggleAnalysisSelection(item)">
                                    <div class="option-icon"><i class="fas fa-flask"></i></div>
                                    <div class="option-details">
                                        <span class="badge bg-primary-subtle text-primary mb-1">{{ item.Kode_Analisa }}</span>
                                        <h6 class="mb-0">{{ item.Jenis_Analisa }}</h6>
                                    </div>
                                    <div class="option-check"><i class="fas fa-check"></i></div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div v-show="currentStep === 2">
                            <h6 class="fw-semibold mb-3"><i class="far fa-calendar-alt me-2 text-primary"></i>Atur Periode Laporan</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small">Dari Tanggal</label>
                                    <input type="date" class="form-control" v-model="startDate" :max="endDate || today" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Sampai Tanggal</label>
                                    <input type="date" class="form-control" v-model="endDate" :min="startDate" />
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div v-show="currentStep === 3">
                            <h6 class="fw-semibold mb-3"><i class="fas fa-eye me-2 text-primary"></i>Ringkasan Laporan</h6>
                            <div class="alert alert-warning small">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Format <strong>Excel</strong> masih dalam pengembangan. Disarankan gunakan <strong>PDF</strong>.
                            </div>
                            <div class="card border">
                                <div class="card-body">
                                    <div class="summary-item">
                                        <span>Jenis Analisa</span>
                                        <strong>{{ selectedAnalysisNames || "-" }}</strong>
                                    </div>
                                    <div class="summary-item">
                                        <span>Periode</span>
                                        <strong>{{ formattedStartDate }} — {{ formattedEndDate }}</strong>
                                    </div>
                                    <div class="summary-item">
                                        <span>Mesin</span>
                                        <v-select style="flex:1" v-if="listDataMesin.length" v-model="selectedListMesin"
                                                  :options="listDataMesin" label="name" placeholder="--- Pilih Mesin ---" />
                                    </div>
                                    <div class="summary-item">
                                        <span>Jenis Cetakan</span>
                                        <select class="form-select form-select-sm flex-1" v-model="selectedJenisPrint">
                                            <option value="">Pilih Jenis Cetakan</option>
                                            <option value="ringkas">Cetak Ringkas (Hasil Akhir)</option>
                                            <option v-if="showPszOption" value="psz">Final Report Particle Size</option>
                                            <option value="detail">Cetak Detail (Per Analisa)</option>
                                        </select>
                                    </div>
                                    <div class="summary-item">
                                        <span>Format</span>
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" value="excel" v-model="exportFormat" id="fExcel" />
                                                <label class="form-check-label" for="fExcel"><i class="far fa-file-excel text-success me-1"></i>Excel</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" value="pdf" v-model="exportFormat" id="fPdf" />
                                                <label class="form-check-label" for="fPdf"><i class="far fa-file-pdf text-danger me-1"></i>PDF</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="isPszReportSelected" class="alert alert-warning mt-3 small">
                                <strong>Peringatan!</strong> Laporan ini hanya mencetak data Particle Size (PSZ).
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" v-model="pszConfirmation" id="pszCheck" />
                                    <label class="form-check-label fw-semibold" for="pszCheck">Saya mengerti dan setuju.</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" @click="prevStep" :disabled="currentStep === 1">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </button>
                        <button type="button" class="btn btn-primary" @click="nextStep" v-if="currentStep < 3">
                            Lanjut <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                        <button type="button" class="btn btn-success" @click="generateReport"
                                v-if="currentStep === 3" :disabled="isGenerateButtonDisabled">
                            <i class="fas fa-file-export me-1"></i>
                            {{ isGenerateButtonDisabled ? "Centang persetujuan dulu" : "Generate Laporan" }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- ===== END PRINT MODAL ===== -->

        <div class="card shadow-sm border-0 w-100 page-card">
            <!-- Page header -->
            <div class="page-header px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <div class="page-icon">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0">Hasil Analisa</h5>
                        <p class="text-muted small mb-0">Koleksi data analisis laboratorium PT. Evo Manufacturing Indonesia</p>
                    </div>
                </div>
                <button @click="togglePrintModal" class="btn btn-primary btn-sm d-flex align-items-center gap-2">
                    <i class="fas fa-file-export"></i>
                    <span>Buat Laporan</span>
                </button>
            </div>

            <!-- Split layout -->
            <div class="row g-0 split-layout">
                <!-- ===== LEFT SIDEBAR: Jenis Analisa ===== -->
                <div class="col-lg-3 col-md-4 sidebar-col border-end">
                    <div class="sidebar-head px-3 py-2 border-bottom">
                        <span class="text-uppercase small fw-semibold text-muted" style="letter-spacing:.05em">Jenis Analisa</span>
                    </div>

                    <div v-if="loading.loadingListData" class="p-3">
                        <div v-for="n in 5" :key="n" class="skeleton-item mb-2"></div>
                    </div>

                    <div v-else-if="listData.length" class="sidebar-list">
                        <a v-for="(item, index) in listData" :key="index"
                           href="#"
                           @click.prevent="selectJenisAnalisa(item)"
                           class="sidebar-item"
                           :class="{ active: selectedItem && selectedItem.Id_Jenis_Analisa === item.Id_Jenis_Analisa }">
                            <div class="sidebar-item-icon">
                                <i class="fas fa-flask"></i>
                            </div>
                            <div class="sidebar-item-body">
                                <span class="sidebar-kode">{{ item.Kode_Analisa }}</span>
                                <span class="sidebar-name">{{ item.Jenis_Analisa }}</span>
                            </div>
                            <i class="fas fa-chevron-right sidebar-chevron"></i>
                        </a>
                    </div>

                    <div v-else class="p-4 text-center text-muted small">
                        <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                        Tidak ada data
                    </div>
                </div>

                <!-- ===== RIGHT PANEL: Detail ===== -->
                <div class="col-lg-9 col-md-8 detail-col">
                    <!-- Prompt: belum pilih -->
                    <div v-if="!selectedItem" class="empty-prompt d-flex flex-column align-items-center justify-content-center">
                        <DotLottieVue style="height:180px;width:180px" autoplay loop src="/animation/empty.lottie" />
                        <h6 class="text-muted fw-semibold mt-2 mb-1">Pilih jenis analisa</h6>
                        <p class="text-muted small mb-0">Klik salah satu jenis analisa di panel kiri untuk melihat data</p>
                    </div>

                    <!-- Data panel -->
                    <div v-else class="detail-panel-content">

                        <!-- ── Sub-header with stats ── -->
                        <div class="detail-subheader px-4 py-3 border-bottom">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="detail-icon">
                                        <i class="fas fa-flask"></i>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-2">
                                            <h6 class="mb-0 fw-bold">{{ selectedItem.Jenis_Analisa }}</h6>
                                            <span class="badge-kode">{{ selectedItem.Kode_Analisa }}</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-3 mt-1">
                                            <span class="stat-chip">
                                                <i class="fas fa-layer-group me-1"></i>
                                                <strong>{{ pagination.totalData }}</strong> sampel
                                            </span>
                                            <span v-if="hasActiveFilter" class="stat-chip stat-chip-active">
                                                <i class="fas fa-filter me-1"></i>Filter aktif
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <button v-if="hasActiveFilter" @click="resetFilters" class="btn-reset-filter">
                                    <i class="fas fa-times me-1"></i>Reset Filter
                                </button>
                            </div>
                        </div>

                        <!-- ── Compact filter bar ── -->
                        <div class="filter-bar px-4 py-2 border-bottom">
                            <div class="filter-row">
                                <!-- Search -->
                                <div class="filter-search">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-transparent border-end-0">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                        <input type="text" class="form-control border-start-0 ps-0"
                                               placeholder="Cari No. PO, Batch, Mesin..."
                                               v-model="searchQuery" />
                                    </div>
                                </div>

                                <!-- Date range -->
                                <div class="filter-date-group">
                                    <div class="filter-date-wrapper">
                                        <i class="fas fa-calendar-alt text-muted filter-date-icon"></i>
                                        <input type="date" class="form-control form-control-sm filter-date-input"
                                               v-model="filters.tanggal.mulai" title="Dari tanggal" />
                                        <span class="filter-date-sep">—</span>
                                        <input type="date" class="form-control form-control-sm filter-date-input"
                                               v-model="filters.tanggal.selesai" title="Sampai tanggal" />
                                    </div>
                                </div>

                                <!-- QRCode -->
                                <div class="filter-select-wrap">
                                    <i class="fas fa-qrcode filter-select-icon text-muted"></i>
                                    <v-select :options="filterOptions.qrcode"
                                              placeholder="QR Code"
                                              v-model="filters.qrcode"
                                              :clearable="true"
                                              class="vs-compact" />
                                </div>

                                <!-- Status -->
                                <div class="filter-select-wrap">
                                    <i class="fas fa-circle-check filter-select-icon text-muted"></i>
                                    <v-select :options="filterOptions.status"
                                              placeholder="Status"
                                              v-model="filters.status"
                                              :clearable="true"
                                              class="vs-compact" />
                                </div>

                                <!-- Tipe Produksi -->
                                <div class="filter-select-wrap">
                                    <i class="fas fa-industry filter-select-icon text-muted"></i>
                                    <v-select :options="filterOptions.tipeProduksi"
                                              placeholder="Produksi"
                                              v-model="filters.tipeProduksi"
                                              :clearable="true"
                                              class="vs-compact" />
                                </div>
                            </div>

                            <!-- Active filter chips -->
                            <div v-if="hasActiveFilter" class="filter-chips">
                                <span v-if="searchQuery" class="filter-chip">
                                    <i class="fas fa-search me-1"></i>{{ searchQuery }}
                                    <button @click="searchQuery = ''" class="chip-remove">&times;</button>
                                </span>
                                <span v-if="filters.tanggal.mulai || filters.tanggal.selesai" class="filter-chip">
                                    <i class="fas fa-calendar me-1"></i>
                                    {{ filters.tanggal.mulai || '…' }} — {{ filters.tanggal.selesai || '…' }}
                                    <button @click="filters.tanggal.mulai = ''; filters.tanggal.selesai = ''" class="chip-remove">&times;</button>
                                </span>
                                <span v-if="filters.qrcode" class="filter-chip">
                                    <i class="fas fa-qrcode me-1"></i>{{ filters.qrcode.label }}
                                    <button @click="filters.qrcode = null" class="chip-remove">&times;</button>
                                </span>
                                <span v-if="filters.status" class="filter-chip">
                                    <i class="fas fa-circle-check me-1"></i>{{ filters.status.label }}
                                    <button @click="filters.status = null" class="chip-remove">&times;</button>
                                </span>
                                <span v-if="filters.tipeProduksi" class="filter-chip"
                                      :class="filters.tipeProduksi.value === 'trial' ? 'chip-warning' : 'chip-success'">
                                    <i class="fas fa-industry me-1"></i>{{ filters.tipeProduksi.label }}
                                    <button @click="filters.tipeProduksi = null" class="chip-remove">&times;</button>
                                </span>
                            </div>
                        </div>

                        <!-- List loading -->
                        <div v-if="loading.loadingDetail" class="p-4">
                            <div v-for="n in 5" :key="n" class="skeleton-card mb-3"></div>
                        </div>

                        <!-- List data -->
                        <div v-else-if="Object.keys(listDetail).length" class="detail-list px-4 py-3">
                            <a v-for="[kode, item] in Object.entries(listDetail)" :key="kode"
                               :href="`/lab/hasil-analisa/${selectedItem.Id_Jenis_Analisa}/${kode}/${item.flag_multi}`"
                               class="data-card">
                                <!-- Card header row -->
                                <div class="dc-header">
                                    <div class="dc-icon">
                                        <i class="fas fa-vial"></i>
                                    </div>
                                    <div class="dc-title">
                                        <div class="dc-name">{{ item.nama_barang }}</div>
                                        <div class="dc-sub d-flex align-items-center gap-1 flex-wrap mt-1">
                                            <small class="text-muted">{{ kode }}</small>

                                            <span v-if="item.flag_multi === 'Y'" class="badge bg-primary-subtle text-primary badge-sm">
                                                <i class="fas fa-clone me-1"></i>Multi QRCode
                                            </span>
                                            <span v-else class="badge bg-secondary-subtle text-secondary badge-sm">
                                                <i class="fas fa-qrcode me-1"></i>Single QRCode
                                            </span>

                                            <!-- Tipe Produksi badge -->
                                            <span v-if="item.tipe_produksi === 'Trial Produksi'"
                                                  class="badge bg-warning-subtle text-warning badge-sm">
                                                <i class="fas fa-flask me-1"></i>Trial Produksi
                                            </span>
                                            <span v-else class="badge bg-success-subtle text-success badge-sm">
                                                <i class="fas fa-industry me-1"></i>Produksi
                                            </span>
                                        </div>
                                    </div>
                                    <div v-if="item.status_keputusan" class="dc-status"
                                         :class="{
                                             'status-terima': item.status_keputusan.toLowerCase() === 'terima',
                                             'status-tolak':  item.status_keputusan.toLowerCase() === 'tolak',
                                             'status-batal':  item.status_keputusan.toLowerCase() === 'dibatalkan',
                                         }">
                                        <i class="fas"
                                           :class="{
                                               'fa-check-circle':  item.status_keputusan.toLowerCase() === 'terima',
                                               'fa-times-circle':  item.status_keputusan.toLowerCase() === 'tolak',
                                               'fa-ban':           item.status_keputusan.toLowerCase() === 'dibatalkan',
                                           }"></i>
                                        <span>{{ item.status_keputusan }}</span>
                                    </div>
                                </div>

                                <!-- Card detail grid -->
                                <div class="dc-details">
                                    <div class="dc-detail-item">
                                        <i class="fas fa-cogs"></i>
                                        <div>
                                            <div class="dc-detail-label">Mesin</div>
                                            <div class="dc-detail-value">{{ item.nama_mesin || "-" }}</div>
                                        </div>
                                    </div>
                                    <div class="dc-detail-item">
                                        <i class="fas fa-receipt"></i>
                                        <div>
                                            <div class="dc-detail-label">No. PO / Split</div>
                                            <div class="dc-detail-value">
                                                {{ item.no_po || "-" }}<span v-if="item.no_split_po"> / {{ item.no_split_po }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dc-detail-item">
                                        <i class="fas fa-hashtag"></i>
                                        <div>
                                            <div class="dc-detail-label">No. Batch</div>
                                            <div class="dc-detail-value">{{ item.no_batch || "-" }}</div>
                                        </div>
                                    </div>
                                    <div class="dc-detail-item">
                                        <i class="fas fa-calendar-plus"></i>
                                        <div>
                                            <div class="dc-detail-label">Tgl Registrasi</div>
                                            <div class="dc-detail-value">{{ formatDateTime(item.tanggal_pengajuan, item.jam_pengajuan) }}</div>
                                        </div>
                                    </div>
                                    <div class="dc-detail-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div>
                                            <div class="dc-detail-label">Tgl Pengujian</div>
                                            <div class="dc-detail-value">{{ formatDateTime(item.tanggal_pengujian, item.jam_pengujian) }}</div>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            <!-- Pagination -->
                            <div class="row align-items-center mt-3" v-if="pagination.totalData > 0">
                                <div class="col-sm text-muted small">
                                    Menampilkan <strong>{{ Object.keys(listDetail).length }}</strong> dari <strong>{{ pagination.totalData }}</strong> data
                                </div>
                                <div class="col-sm-auto mt-2 mt-sm-0">
                                    <ul class="pagination pagination-sm pagination-separated justify-content-center mb-0">
                                        <li class="page-item" :class="{ disabled: pagination.page === 1 }">
                                            <a href="#" class="page-link" @click.prevent="prevPage">←</a>
                                        </li>
                                        <li class="page-item" v-for="page in visiblePages" :key="page"
                                            :class="{ active: page === pagination.page }">
                                            <a href="#" class="page-link" @click.prevent="changePage(page)">{{ page }}</a>
                                        </li>
                                        <li class="page-item" :class="{ disabled: pagination.page === pagination.totalPage }">
                                            <a href="#" class="page-link" @click.prevent="nextPage">→</a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-else class="d-flex flex-column align-items-center justify-content-center py-5 px-4">
                            <DotLottieVue style="height:160px;width:160px" autoplay loop src="/animation/empty.lottie" />
                            <h6 class="text-muted fw-semibold mt-2 mb-1">Tidak ada data</h6>
                            <p class="text-muted small mb-0">Tidak ada data sesuai filter yang dipilih.</p>
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
import { debounce } from "lodash";
import vSelect from "vue-select";

export default {
    components: { DotLottieVue, vSelect },

    props: {
        selected_id: { type: String, default: null },
    },

    data() {
        return {
            // Sidebar
            listData: [],
            selectedItem: null,

            // Detail
            listDetail: {},
            searchQuery: "",
            filters: {
                tanggal: { mulai: "", selesai: "" },
                qrcode: null,
                status: { label: "Diterima", value: "terima" },
                tipeProduksi: null,
            },
            filterOptions: {
                qrcode: [
                    { label: "Multi QRCode",   value: "multi" },
                    { label: "Single QRCode",  value: "single" },
                ],
                status: [
                    { label: "Semua Status", value: null },
                    { label: "Diterima",     value: "terima" },
                    { label: "Ditolak",      value: "tolak" },
                    { label: "Dibatalkan",   value: "dibatalkan" },
                ],
                tipeProduksi: [
                    { label: "Produksi",       value: "produksi" },
                    { label: "Trial Produksi", value: "trial" },
                ],
            },
            pagination: { page: 1, limit: 10, totalPage: 0, totalData: 0 },

            loading: { loadingListData: false, loadingDetail: false, loadingListDataMesin: false },

            // Print modal
            printModal: null,
            currentStep: 1,
            listDataJenisAnalisa: [],
            listDataMesin: [],
            selectedAnalysis: [],
            selectedIsPerhitungan: [],
            selectedJenisPrint: "",
            selectedListMesin: null,
            startDate: "",
            endDate: "",
            exportFormat: "pdf",
            pszConfirmation: false,
        };
    },

    computed: {
        today() { return new Date().toISOString().split("T")[0]; },
        formattedStartDate() { return this.startDate ? new Date(this.startDate).toLocaleDateString("id-ID") : "-"; },
        formattedEndDate()   { return this.endDate   ? new Date(this.endDate).toLocaleDateString("id-ID")   : "-"; },
        selectedAnalysisNames() {
            return (this.listDataJenisAnalisa || [])
                .filter(i => (this.selectedAnalysis || []).includes(i.id))
                .map(i => i.Jenis_Analisa).join(", ");
        },
        showPszOption() {
            if (!this.selectedAnalysis.length) return false;
            return this.listDataJenisAnalisa
                .filter(i => this.selectedAnalysis.includes(i.id))
                .some(i => i.Kode_Analisa && i.Kode_Analisa.includes("PSZ"));
        },
        isPszReportSelected() { return this.selectedJenisPrint === "psz"; },
        isGenerateButtonDisabled() { return this.isPszReportSelected && !this.pszConfirmation; },
        hasActiveFilter() {
            return !!(
                this.searchQuery ||
                this.filters.tanggal.mulai ||
                this.filters.tanggal.selesai ||
                this.filters.qrcode ||
                (this.filters.status && this.filters.status.value !== null) ||
                this.filters.tipeProduksi
            );
        },
        visiblePages() {
            const total   = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = Math.max(1, current - 2);
            let end   = Math.min(total, current + 2);
            if (end - start < 4) {
                if (start === 1) end = Math.min(5, total);
                else             start = Math.max(1, end - 4);
            }
            const pages = [];
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
    },

    watch: {
        searchQuery() { this.debouncedFetchDetail(); },
        filters: {
            handler() { this.debouncedFetchDetail(); },
            deep: true,
        },
        selectedJenisPrint(val) {
            if (val !== "psz") this.pszConfirmation = false;
        },
    },

    methods: {
        // ── Sidebar ──────────────────────────────────────────────
        async fetchHasilAnalisa() {
            this.loading.loadingListData = true;
            try {
                const res = await axios.get("/api/v1/lab/hasil-analisa/uji-sampel");
                if (res.status === 200 && Array.isArray(res.data?.result)) {
                    this.listData = res.data.result;
                    if (this.selected_id) {
                        const found = this.listData.find(i => i.Id_Jenis_Analisa === this.selected_id);
                        if (found) this.selectJenisAnalisa(found);
                    }
                }
            } catch (e) {
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },

        selectJenisAnalisa(item) {
            if (this.selectedItem?.Id_Jenis_Analisa === item.Id_Jenis_Analisa) return;
            this.selectedItem = item;
            this.listDetail   = {};
            this.pagination   = { ...this.pagination, page: 1, totalPage: 0, totalData: 0 };
            this.searchQuery  = "";
            this.filters      = { tanggal: { mulai: "", selesai: "" }, qrcode: null, status: { label: "Diterima", value: "terima" }, tipeProduksi: null };
            this.fetchDetail(1);
        },

        // ── Detail ───────────────────────────────────────────────
        async fetchDetail(page = 1) {
            if (!this.selectedItem) return;
            this.loading.loadingDetail = true;
            try {
                const params = {
                    page,
                    q:             this.searchQuery,
                    limit:         this.pagination.limit,
                    qrcode:        this.filters.qrcode        ? this.filters.qrcode.value        : null,
                    status:        this.filters.status        ? this.filters.status.value        : null,
                    tipe_produksi: this.filters.tipeProduksi  ? this.filters.tipeProduksi.value  : null,
                };
                if (this.filters.tanggal.mulai && this.filters.tanggal.selesai) {
                    params.tanggal_mulai    = this.filters.tanggal.mulai;
                    params.tanggal_selesai  = this.filters.tanggal.selesai;
                }
                const res = await axios.get(`/api/v1/lab/hasil-analisa/${this.selectedItem.Id_Jenis_Analisa}`, { params });
                if (res.status === 200 && res.data?.result) {
                    this.listDetail  = res.data.result.data_sampel;
                    this.pagination  = { ...this.pagination, ...res.data.result.pagination };
                } else {
                    this.listDetail  = {};
                    this.pagination.totalData = 0;
                }
            } catch (e) {
                this.listDetail = {};
                this.pagination.totalData = 0;
            } finally {
                this.loading.loadingDetail = false;
            }
        },

        resetFilters() {
            this.searchQuery = "";
            this.filters = {
                tanggal: { mulai: "", selesai: "" },
                qrcode: null,
                status: null,
                tipeProduksi: null,
            };
        },

        debouncedFetchDetail: debounce(function () {
            this.pagination.page = 1;
            this.fetchDetail(1);
        }, 500),

        nextPage() { if (this.pagination.page < this.pagination.totalPage) this.fetchDetail(this.pagination.page + 1); },
        prevPage() { if (this.pagination.page > 1)                         this.fetchDetail(this.pagination.page - 1); },
        changePage(p) { if (p !== this.pagination.page) this.fetchDetail(p); },

        formatDateTime(dateStr, timeStr) {
            if (!dateStr || !timeStr) return "-";
            const date = new Date(`${dateStr.split(" ")[0]}T${timeStr}`);
            return new Intl.DateTimeFormat("id-ID", {
                day: "2-digit", month: "short", year: "numeric",
                hour: "2-digit", minute: "2-digit", hour12: false,
            }).format(date).replace(".", ":");
        },

        // ── Print modal ──────────────────────────────────────────
        async fetchJenisAnalisa() {
            try {
                const res = await axios.get("/jenis-analisa-current/for-select");
                this.listDataJenisAnalisa = res.data?.result ?? [];
            } catch { this.listDataJenisAnalisa = []; }
        },
        async fetchListMesin() {
            try {
                const res = await axios.get("/api/v1/lab/mesin/export-hasil-analisa");
                const opts = (res.data?.result ?? []).map(i => ({ value: i.Id_Master_Mesin, name: i.Nama_Mesin }));
                this.listDataMesin = [{ value: "all", name: "Semua Mesin" }, ...opts];
            } catch { this.listDataMesin = [{ value: "all", name: "Semua Mesin" }]; }
        },

        togglePrintModal() {
            if (!this.printModal) {
                this.printModal = new bootstrap.Modal(document.getElementById("printModal"));
            }
            this.currentStep         = parseInt(sessionStorage.getItem("printStep") || "1");
            this.selectedAnalysis    = JSON.parse(sessionStorage.getItem("printSelectedAnalysis")    || "[]");
            this.selectedIsPerhitungan = JSON.parse(sessionStorage.getItem("printSelectedIsPerhitungan") || "[]");
            this.startDate           = sessionStorage.getItem("printStartDate")   || "";
            this.endDate             = sessionStorage.getItem("printEndDate")     || "";
            this.exportFormat        = sessionStorage.getItem("printExportFormat") || "pdf";
            this.selectedJenisPrint  = sessionStorage.getItem("printJenisCetak")  || "";
            this.printModal.show();
        },
        closePrintModal() {
            this.printModal.hide();
            ["printStep","printSelectedAnalysis","printSelectedIsPerhitungan","printStartDate","printEndDate","printExportFormat","printJenisCetak"]
                .forEach(k => sessionStorage.removeItem(k));
            this.currentStep = 1; this.selectedAnalysis = []; this.selectedIsPerhitungan = [];
            this.startDate = ""; this.endDate = ""; this.exportFormat = "pdf"; this.selectedJenisPrint = "";
        },
        nextStep() {
            if (this.currentStep === 1 && !this.selectedAnalysis.length) {
                return Swal.fire("Peringatan", "Pilih minimal satu jenis analisa.", "warning");
            }
            if (this.currentStep === 2 && (!this.startDate || !this.endDate)) {
                return Swal.fire("Peringatan", "Isi periode tanggal lengkap.", "warning");
            }
            if (this.currentStep === 3 && !this.selectedJenisPrint) {
                return Swal.fire("Peringatan", "Pilih jenis cetakan.", "warning");
            }
            this.currentStep++;
        },
        prevStep() { this.currentStep--; },
        toggleAnalysisSelection(item) {
            const idx = this.selectedAnalysis.indexOf(item.id);
            if (idx > -1) {
                this.selectedAnalysis.splice(idx, 1);
                this.selectedIsPerhitungan.splice(idx, 1);
            } else {
                this.selectedAnalysis.push(item.id);
                this.selectedIsPerhitungan.push(item.Flag_Perhitungan);
            }
        },

        async generateReport() {
            Swal.fire({ title: "Mohon Tunggu", html: "Laporan sedang diproses...", allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
                let urlLink, payload;
                const fmt = this.exportFormat;
                if (this.selectedJenisPrint === "psz") {
                    const pszItem = this.listDataJenisAnalisa.find(i => this.selectedAnalysis.includes(i.id) && i.Kode_Analisa.includes("PSZ"));
                    if (!pszItem) return Swal.fire("Peringatan", "Analisa Particle Size harus dipilih.", "warning");
                    urlLink = fmt === "pdf" ? "/rekap-sampel/pdf/particle-size" : "/rekap-sampel/excell/particle-size";
                    payload = { analysis: pszItem.id, Flag_Perhitungan: pszItem.Flag_Perhitungan, startDate: this.startDate, endDate: this.endDate, format: "pdf", Id_Master_Mesin: this.selectedListMesin?.value };
                } else {
                    const isRingkas = this.selectedJenisPrint === "ringkas";
                    urlLink = fmt === "pdf" ? (isRingkas ? "/api/v2/rekap-sampel/pdf" : "/rekap-sampel/pdf") : (isRingkas ? "/api/v2/rekap-sampel/excell" : "/api/v1/download-rekap/analisa");
                    payload = { analysis: this.selectedAnalysis, Flag_Perhitungan: this.selectedIsPerhitungan, startDate: this.startDate, endDate: this.endDate, format: fmt, Id_Master_Mesin: this.selectedListMesin?.value };
                }
                const res = await axios.post(urlLink, payload, { headers: { "X-CSRF-TOKEN": csrf }, responseType: "blob" });
                Swal.close();
                const blob = new Blob([res.data], { type: res.headers["content-type"] });
                const url  = window.URL.createObjectURL(blob);
                const link = document.createElement("a");
                link.href  = url;
                let fileName = `laporan-rekap.${fmt === "pdf" ? "pdf" : "xlsx"}`;
                const cd = res.headers["content-disposition"] || res.headers["Content-Disposition"];
                if (cd) { const m = cd.match(/filename\*?=(?:(?:UTF-8'')?["']?)([^;"']+)/i); if (m?.[1]) fileName = decodeURIComponent(m[1]); }
                link.setAttribute("download", fileName);
                document.body.appendChild(link); link.click(); link.remove();
                window.URL.revokeObjectURL(url);
            } catch (err) {
                try {
                    const reader = new FileReader();
                    reader.onload = () => {
                        try { const p = JSON.parse(reader.result); Swal.fire({ icon: "warning", title: "Tidak Ditemukan", text: p?.message || "Terjadi kesalahan." }); }
                        catch { Swal.fire({ icon: "error", title: "Gagal", text: "Terjadi kesalahan internal." }); }
                    };
                    reader.readAsText(err.response?.data);
                } catch { Swal.fire({ icon: "error", title: "Gagal", text: "Terjadi kesalahan tidak terduga." }); }
            }
        },
    },

    mounted() {
        this.fetchHasilAnalisa();
        this.fetchJenisAnalisa();
        this.fetchListMesin();
        if (sessionStorage.getItem("printStep")) this.togglePrintModal();
    },
};
</script>

<style scoped>
/* ── Page layout ─────────────────────────────────────── */
.hasil-analisa-page { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }

.page-card { border-radius: 12px; overflow: hidden; }

.page-header { background: #fff; }

.page-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: linear-gradient(135deg, #405189, #2a3a6e);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
}

.split-layout { min-height: 65vh; }

/* ── Sidebar ─────────────────────────────────────────── */
.sidebar-col { background: #fafbfc; }
.sidebar-head { background: #f4f6f9; }

.sidebar-list { overflow-y: auto; max-height: calc(100vh - 220px); }

.sidebar-item {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; text-decoration: none; color: inherit;
    border-left: 3px solid transparent;
    border-bottom: 1px solid #f0f2f5;
    transition: background .15s, border-color .15s;
    cursor: pointer;
}
.sidebar-item:hover { background: #eef1f8; color: inherit; }
.sidebar-item.active {
    background: #eef1f8;
    border-left-color: #405189;
}
.sidebar-item-icon {
    width: 34px; height: 34px; flex-shrink: 0; border-radius: 8px;
    background: rgba(64, 81, 137, .1); color: #405189;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem;
}
.sidebar-item.active .sidebar-item-icon { background: #405189; color: #fff; }
.sidebar-item-body { flex: 1; min-width: 0; }
.sidebar-kode {
    display: block; font-size: .7rem; font-weight: 700;
    color: #405189; text-transform: uppercase; letter-spacing: .04em;
}
.sidebar-name {
    display: block; font-size: .82rem; font-weight: 500;
    color: #343a40; line-height: 1.3;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sidebar-chevron { font-size: .7rem; color: #adb5bd; flex-shrink: 0; opacity: 0; transition: opacity .15s; }
.sidebar-item:hover .sidebar-chevron,
.sidebar-item.active .sidebar-chevron { opacity: 1; }

/* ── Detail panel ────────────────────────────────────── */
.detail-col { background: #fff; display: flex; flex-direction: column; }

.empty-prompt { min-height: 65vh; text-align: center; }

.detail-subheader { background: #f8f9fc; }
.detail-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: linear-gradient(135deg, #405189, #2a3a6e);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}

.badge-kode {
    display: inline-block; font-size: .7rem; font-weight: 700;
    background: rgba(64,81,137,.12); color: #405189;
    padding: .2em .6em; border-radius: 4px; letter-spacing: .04em;
    text-transform: uppercase;
}

.stat-chip {
    font-size: .75rem; color: #6c757d;
    display: inline-flex; align-items: center;
}
.stat-chip-active { color: #f7b84b; }

.btn-reset-filter {
    font-size: .75rem; font-weight: 600; color: #f06548;
    background: rgba(240,101,72,.08); border: 1px solid rgba(240,101,72,.2);
    border-radius: 6px; padding: .3em .8em; cursor: pointer;
    transition: background .15s;
}
.btn-reset-filter:hover { background: rgba(240,101,72,.15); }

/* ── Compact filter bar ──────────────────────────────── */
.filter-bar { background: #fafbfc; }

.filter-row {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
}

.filter-search {
    flex: 1 1 220px; min-width: 200px;
}
.filter-search .input-group { border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; background: #fff; }
.filter-search .input-group-text { border: none; background: transparent; }
.filter-search .form-control { border: none; font-size: .82rem; }
.filter-search .form-control:focus { box-shadow: none; }

.filter-date-group { flex: 0 0 auto; }
.filter-date-wrapper {
    display: flex; align-items: center; gap: 4px;
    background: #fff; border: 1px solid #dee2e6;
    border-radius: 6px; padding: 3px 8px;
}
.filter-date-icon { font-size: .75rem; flex-shrink: 0; }
.filter-date-input {
    border: none !important; box-shadow: none !important;
    padding: 0 !important; font-size: .8rem;
    width: 116px; background: transparent;
}
.filter-date-sep { color: #adb5bd; font-size: .8rem; flex-shrink: 0; }

.filter-select-wrap {
    display: flex; align-items: center; gap: 5px;
    flex: 0 0 auto;
}
.filter-select-icon { font-size: .75rem; flex-shrink: 0; }

/* v-select compact override */
:deep(.vs-compact) { min-width: 130px; }
:deep(.vs-compact .vs__dropdown-toggle) {
    padding: 2px 6px;
    min-height: calc(1.5em + .5rem + 2px);
    font-size: .8rem;
    border-radius: 6px;
    border-color: #dee2e6;
    background: #fff;
}
:deep(.vs-compact .vs__selected) { font-size: .8rem; margin: 1px 2px; }
:deep(.vs-compact .vs__search) { font-size: .8rem; }

/* Active filter chips */
.filter-chips {
    display: flex; align-items: center; flex-wrap: wrap; gap: 6px;
    padding-top: 8px;
}
.filter-chip {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: .72rem; font-weight: 500;
    background: rgba(64,81,137,.08); color: #405189;
    border: 1px solid rgba(64,81,137,.2);
    border-radius: 20px; padding: .2em .65em;
}
.chip-warning { background: rgba(247,184,75,.1); color: #c98f00; border-color: rgba(247,184,75,.3); }
.chip-success { background: rgba(10,179,156,.1); color: #0a8f6e; border-color: rgba(10,179,156,.3); }

.chip-remove {
    background: none; border: none; padding: 0; margin-left: 2px;
    font-size: .85rem; line-height: 1; cursor: pointer; color: inherit; opacity: .7;
}
.chip-remove:hover { opacity: 1; }

/* ── Data cards ──────────────────────────────────────── */
.data-card {
    display: block; text-decoration: none; color: inherit;
    background: #fff; border: 1px solid #e9ecef;
    border-radius: 10px; padding: 14px 16px; margin-bottom: .75rem;
    box-shadow: 0 2px 4px rgba(0,0,0,.04);
    transition: transform .2s, box-shadow .2s, border-color .2s;
}
.data-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(64,81,137,.12);
    border-color: #c5cce8;
}
.dc-header { display: flex; align-items: flex-start; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid #f1f3f5; }
.dc-icon {
    width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%;
    background: rgba(64,81,137,.1); color: #405189;
    display: flex; align-items: center; justify-content: center; font-size: 1rem;
}
.dc-title { flex: 1; min-width: 0; }
.dc-name  { font-weight: 600; color: #212529; font-size: .9rem; }
.badge-sm { font-size: .7rem; padding: .25em .55em; }

.dc-status {
    padding: .3em .75em; border-radius: 20px;
    font-size: .72rem; font-weight: 600; text-transform: capitalize;
    display: flex; align-items: center; gap: .35rem; flex-shrink: 0;
}
.status-terima { background: rgba(10,179,156,.12); color: #0ab39c; }
.status-tolak  { background: rgba(240,101,72,.12);  color: #f06548; }
.status-batal  { background: rgba(108,117,125,.12); color: #6c757d; }

.dc-details {
    padding-top: 12px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: .75rem;
}
.dc-detail-item { display: flex; align-items: center; gap: .6rem; font-size: .82rem; }
.dc-detail-item > i { color: #adb5bd; font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
.dc-detail-label { color: #6c757d; font-size: .75rem; }
.dc-detail-value { color: #212529; font-weight: 600; }

/* ── Print modal ─────────────────────────────────────── */
.steps-progress {
    display: flex; justify-content: space-between; position: relative;
}
.steps-progress::before {
    content: ""; position: absolute; top: 16px; left: 0; right: 0;
    height: 2px; background: #e9ecef; z-index: 0;
}
.step { display: flex; flex-direction: column; align-items: center; position: relative; z-index: 1; flex: 1; }
.step-number {
    width: 32px; height: 32px; border-radius: 50%;
    background: #e9ecef; border: 2px solid #e9ecef;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem; margin-bottom: 6px;
    transition: all .25s;
}
.step.active .step-number  { background: #405189; border-color: #405189; color: #fff; }
.step.done .step-number    { background: #0ab39c; border-color: #0ab39c; color: #fff; }
.step-label { font-size: .78rem; color: #6c757d; }
.step.active .step-label   { color: #405189; font-weight: 600; }

.analysis-selector { max-height: 340px; overflow-y: auto; }
.analysis-option {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 14px; border-radius: 8px;
    border: 1px solid #dee2e6; cursor: pointer;
    transition: all .2s; background: #fff; margin-bottom: 6px;
}
.analysis-option:hover  { border-color: #86b7fe; box-shadow: 0 0 0 3px rgba(64,81,137,.1); }
.analysis-option.selected { border-color: #405189; background: rgba(64,81,137,.06); }
.option-icon {
    width: 36px; height: 36px; border-radius: 8px;
    background: rgba(64,81,137,.1); color: #405189;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.option-details { flex: 1; }
.option-details h6 { font-size: .9rem; }
.option-check {
    width: 22px; height: 22px; border-radius: 50%;
    background: #405189; display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: .7rem; opacity: 0; transition: opacity .2s;
}
.analysis-option.selected .option-check { opacity: 1; }

.summary-item {
    display: flex; align-items: center; gap: 12px;
    padding: 8px 0; border-bottom: 1px dashed #e9ecef;
}
.summary-item:last-child { border-bottom: none; }
.summary-item > span { width: 110px; color: #6c757d; font-size: .85rem; flex-shrink: 0; }
.summary-item .flex-1 { flex: 1; }

/* ── Skeletons ───────────────────────────────────────── */
.skeleton-item {
    height: 52px; border-radius: 6px;
    background: linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.2s infinite;
}
.skeleton-card {
    height: 100px; border-radius: 10px;
    background: linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.2s infinite;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ── v-select size override ──────────────────────────── */
:deep(.vs-sm .vs__dropdown-toggle) {
    padding: 1px 4px;
    min-height: calc(1.5em + .5rem + 2px);
    font-size: .875rem;
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 767px) {
    .sidebar-col { border-right: none !important; border-bottom: 1px solid #dee2e6; }
    .sidebar-list { max-height: 220px; }
    .empty-prompt { min-height: 40vh; }
}
</style>
