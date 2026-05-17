<template>
    <div class="container-fluid py-3 validasi-page">
        <!-- Page Header -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-1 fw-semibold">Validasi Hasil Analisa</h4>
                        <p class="text-muted mb-0 small">
                            <i class="ri-information-line me-1"></i>
                            Data hasil submit terakhir — konfirmasi untuk finalisasi
                        </p>
                    </div>
                    <span class="badge bg-warning-subtle text-warning fs-12 px-3 py-2">
                        <i class="ri-time-line me-1"></i> Menunggu Validasi
                    </span>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- LEFT PANEL: SAMPLE LIST                                 -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="col-xl-4 col-lg-5" :class="{ 'd-none d-lg-flex flex-column': detailVisible && isMobile }">
                <div class="card h-100 border-0 shadow-sm">
                    <!-- Filter Section -->
                    <div class="card-header border-bottom bg-white py-3">
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-transparent border-end-0">
                                        <i class="ri-search-line text-muted"></i>
                                    </span>
                                    <input
                                        type="text"
                                        class="form-control border-start-0 ps-0"
                                        placeholder="Cari No. Sampel, PO, Batch..."
                                        v-model="searchQuery"
                                    />
                                </div>
                            </div>
                            <div class="col-6">
                                <input
                                    type="date"
                                    class="form-control form-control-sm"
                                    v-model="filters.tanggal.mulai"
                                    title="Dari Tanggal"
                                />
                            </div>
                            <div class="col-6">
                                <input
                                    type="date"
                                    class="form-control form-control-sm"
                                    v-model="filters.tanggal.selesai"
                                    title="Sampai Tanggal"
                                />
                            </div>
                            <div class="col-12">
                                <select class="form-select form-select-sm" v-model="filters.qrcode">
                                    <option value="">Semua Tipe QRCode</option>
                                    <option value="multi">Multi QRCode</option>
                                    <option value="single">Single QRCode</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- List Items -->
                    <div class="card-body p-0 list-scroll">
                        <!-- Skeleton -->
                        <div v-if="loading.list" class="p-3">
                            <div v-for="i in 5" :key="i" class="list-item-skeleton mb-2 p-3 rounded">
                                <div class="skeleton-line mb-2" style="width:60%;height:14px"></div>
                                <div class="skeleton-line mb-1" style="width:80%;height:12px"></div>
                                <div class="skeleton-line" style="width:40%;height:12px"></div>
                            </div>
                        </div>

                        <!-- Empty -->
                        <div v-else-if="listData.length === 0" class="text-center py-5 px-3">
                            <i class="ri-inbox-line fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted small mb-0">{{ emptyMessage }}</p>
                            <button @click="resetFiltersAndFetch" class="btn btn-sm btn-soft-primary mt-2">
                                <i class="ri-refresh-line me-1"></i>Reset Filter
                            </button>
                        </div>

                        <!-- List -->
                        <div v-else>
                            <button
                                v-for="(item, idx) in listData"
                                :key="idx"
                                @click="selectItem(item)"
                                class="list-item w-100 text-start border-0 bg-transparent px-3 py-3"
                                :class="{
                                    'active': selectedItem && selectedItem.No_Po_Sampel === item.No_Po_Sampel && selectedItem.Id_Jenis_Analisa === item.Id_Jenis_Analisa,
                                    'border-success-left': item.Status_Sampel === 'Lolos Uji',
                                    'border-danger-left': item.Status_Sampel === 'Tidak Lolos Uji',
                                }"
                            >
                                <div class="d-flex align-items-start gap-2">
                                    <div class="status-dot mt-1 flex-shrink-0"
                                        :class="item.Status_Sampel === 'Lolos Uji' ? 'bg-success' : 'bg-danger'">
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <span class="fw-semibold text-dark text-truncate small">{{ item.Jenis_Analisa }}</span>
                                            <span class="badge ms-1 flex-shrink-0"
                                                :class="item.Status_Sampel === 'Lolos Uji' ? 'badge-soft-success' : 'badge-soft-danger'"
                                                style="font-size:10px">
                                                {{ item.Status_Sampel }}
                                            </span>
                                        </div>
                                        <div class="text-muted" style="font-size:11px">
                                            {{ item.po_info?.Kode_Barang }} — {{ item.Nama_Barang }}
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                                            <code class="text-primary" style="font-size:10px">{{ item.No_Po_Sampel }}</code>
                                            <span class="badge badge-soft-primary" style="font-size:10px" v-if="item.Flag_Multi_QrCode === 'Y'">
                                                <i class="ri-qr-code-line"></i> Multi
                                            </span>
                                            <span class="badge badge-soft-secondary" style="font-size:10px" v-else>
                                                <i class="ri-qr-code-line"></i> Single
                                            </span>
                                            <span class="text-muted" style="font-size:10px">
                                                <i class="ri-calendar-line"></i>
                                                {{ formatTanggal(item.Tanggal) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <!-- Pagination -->
                        <div v-if="pagination.totalPage > 1" class="border-top px-3 py-2 d-flex align-items-center justify-content-between">
                            <span class="text-muted" style="font-size:11px">
                                {{ listData.length }}/{{ pagination.totalData }} data
                            </span>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                    :disabled="pagination.page === 1"
                                    @click="changePage(pagination.page - 1)">
                                    <i class="ri-arrow-left-s-line"></i>
                                </button>
                                <span class="btn btn-sm btn-primary py-0 px-2" style="cursor:default">
                                    {{ pagination.page }}/{{ pagination.totalPage }}
                                </span>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                    :disabled="pagination.page === pagination.totalPage"
                                    @click="changePage(pagination.page + 1)">
                                    <i class="ri-arrow-right-s-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- RIGHT PANEL: DETAIL VIEW                                -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="col-xl-8 col-lg-7" :class="{ 'd-none': !detailVisible && isMobile }">

                <!-- Mobile back button -->
                <button v-if="isMobile && detailVisible"
                    class="btn btn-sm btn-soft-secondary mb-2"
                    @click="detailVisible = false">
                    <i class="ri-arrow-left-line me-1"></i>Kembali ke Daftar
                </button>

                <!-- Empty state (no selection) -->
                <div v-if="!selectedItem" class="card border-0 shadow-sm h-100 d-flex align-items-center justify-content-center">
                    <div class="text-center py-5">
                        <i class="ri-flask-line fs-1 text-muted d-block mb-3"></i>
                        <h6 class="text-muted">Pilih sampel dari daftar</h6>
                        <p class="text-muted small">Klik item di kiri untuk melihat detail dan melakukan validasi</p>
                    </div>
                </div>

                <!-- Detail content -->
                <div v-else>
                    <!-- Sample Info Card -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <h5 class="fw-semibold mb-1">{{ selectedItem.Jenis_Analisa }}</h5>
                                    <div class="text-muted small mb-2">
                                        {{ selectedItem.po_info?.Kode_Barang }} — {{ selectedItem.Nama_Barang }}
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge badge-soft-primary">
                                            <i class="ri-barcode-line me-1"></i>{{ selectedItem.No_Po_Sampel }}
                                        </span>
                                        <span class="badge badge-soft-info" v-if="selectedItem.Flag_Multi_QrCode === 'Y'">
                                            <i class="ri-qr-code-line me-1"></i>Multi QR
                                        </span>
                                        <span class="badge badge-soft-secondary" v-else>
                                            <i class="ri-qr-code-line me-1"></i>Single QR
                                        </span>
                                        <span class="badge badge-soft-success" v-if="selectedItem.Status_Sampel === 'Lolos Uji'">
                                            <i class="ri-checkbox-circle-line me-1"></i>Lolos Uji
                                        </span>
                                        <span class="badge badge-soft-danger" v-else>
                                            <i class="ri-close-circle-line me-1"></i>Tidak Lolos Uji
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end small text-muted">
                                    <div><i class="ri-settings-3-line me-1"></i>{{ selectedItem.po_info?.Nama_Mesin || '-' }}</div>
                                    <div><i class="ri-receipt-line me-1"></i>{{ selectedItem.po_info?.No_Po || '-' }} / {{ selectedItem.po_info?.No_Split_Po || '-' }}</div>
                                    <div><i class="ri-stack-line me-1"></i>Batch {{ selectedItem.po_info?.No_Batch || '-' }}</div>
                                    <div><i class="ri-user-line me-1"></i>{{ selectedItem.Id_User || '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Multi QR: Sub-PO Tabs -->
                    <div v-if="selectedItem.Flag_Multi_QrCode === 'Y'" class="card border-0 shadow-sm mb-3">
                        <div class="card-header border-bottom bg-white py-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="ri-layers-line text-primary"></i>
                                <span class="fw-semibold small">Pilih Sub Sampel</span>
                                <span class="badge bg-secondary ms-auto" v-if="subPoList.length">{{ subPoList.length }} sub sampel</span>
                            </div>
                        </div>
                        <div class="card-body py-2 px-3">
                            <div v-if="loading.subPo" class="text-center py-3">
                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                <span class="ms-2 small text-muted">Memuat sub sampel...</span>
                            </div>
                            <div v-else-if="subPoList.length === 0" class="text-muted small py-2">
                                Tidak ada sub sampel ditemukan.
                            </div>
                            <div v-else class="d-flex flex-wrap gap-2 py-1">
                                <button
                                    v-for="(sub, si) in subPoList"
                                    :key="si"
                                    @click="selectSubPo(sub)"
                                    class="btn btn-sm"
                                    :class="selectedSubPo && selectedSubPo.No_Fak_Sub_Po === sub.No_Fak_Sub_Po ? 'btn-primary' : 'btn-outline-secondary'"
                                >
                                    <i class="ri-file-list-3-line me-1"></i>
                                    {{ sub.No_Fak_Sub_Po || sub.No_Po_Sampel }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Data Panel -->
                    <div class="card border-0 shadow-sm">
                        <!-- Loading detail -->
                        <div v-if="loading.detail" class="card-body text-center py-5">
                            <div class="spinner-border text-primary mb-3"></div>
                            <p class="text-muted small mb-0">Memuat data hasil analisa...</p>
                        </div>

                        <!-- Prompt to select sub-PO for Multi QR -->
                        <div v-else-if="selectedItem.Flag_Multi_QrCode === 'Y' && !selectedSubPo" class="card-body text-center py-5">
                            <i class="ri-cursor-line fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted small">Pilih sub sampel di atas untuk melihat data hasil analisa</p>
                        </div>

                        <!-- Data content -->
                        <div v-else-if="detailData.length > 0" class="card-body p-3">
                            <!-- Standard config warning -->
                            <div v-if="!hasStandardConfiguration"
                                class="alert alert-warning border-warning border-start border-4 py-2 mb-3"
                                role="alert">
                                <div class="d-flex gap-2">
                                    <i class="ri-alert-line text-warning mt-1 flex-shrink-0"></i>
                                    <div>
                                        <strong class="d-block small">Standar Belum Dikonfigurasi</strong>
                                        <span class="small text-muted">Tidak ada standar rentang untuk jenis analisa ini. Hasil akan dianggap layak secara otomatis.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Duration Chart -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:11px;letter-spacing:.5px">
                                    <i class="ri-bar-chart-2-line me-1"></i>Durasi Proses (Hari)
                                </h6>
                                <apexchart
                                    height="200"
                                    type="bar"
                                    :options="durationChartOptions"
                                    :series="durationChartSeries"
                                ></apexchart>
                            </div>

                            <!-- Data Table -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:11px;letter-spacing:.5px">
                                    <i class="ri-table-line me-1"></i>Data Hasil Analisa
                                </h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered table-nowrap align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center" style="width:40px">No</th>
                                                <th>No Transaksi</th>
                                                <th>No Sampel</th>
                                                <th>No PO</th>
                                                <th>No Split PO</th>
                                                <th>Batch</th>
                                                <th>Tanggal</th>
                                                <th v-for="param in template.parameter" :key="param.id_qc">
                                                    {{ param.nama_parameter }}
                                                </th>
                                                <template v-if="template.formula && template.formula.length > 0 && formulaAverages.length > 0">
                                                    <th v-for="(f, fi) in template.formula" :key="'fh-'+fi">
                                                        {{ f.nama_kolom }}
                                                    </th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(row, ri) in detailData" :key="ri"
                                                :class="row.Kode_Analisa === 'MBLG-STR' ? '' : row.Flag_Layak === 'Y' ? 'table-success' : 'table-danger'">
                                                <td class="text-center">{{ ri + 1 }}</td>
                                                <td>{{ row.No_Faktur }}</td>
                                                <td>{{ row.No_Po_Sampel }}</td>
                                                <td>{{ row.No_Po }}</td>
                                                <td>{{ row.No_Split_Po }}</td>
                                                <td>Batch {{ row.No_Batch }}</td>
                                                <td>{{ formatTanggal(row.Tanggal) }}</td>
                                                <td v-for="(pv, pi) in row.parameters" :key="'p-'+ri+'-'+pi">{{ pv }}</td>
                                                <template v-if="template.formula && template.formula.length > 0 && formulaAverages.length > 0">
                                                    <td v-for="(f, fi) in template.formula" :key="'fc-'+ri+'-'+fi">
                                                        {{ row.results[fi] && row.results[fi].value !== undefined ? row.results[fi].value : '-' }}
                                                    </td>
                                                </template>
                                            </tr>
                                            <!-- Average row -->
                                            <tr v-if="template.formula && template.formula.length > 0 && formulaAverages.length > 0"
                                                class="table-warning fw-bold">
                                                <td :colspan="7 + (template.parameter ? template.parameter.length : 0)" class="text-end">
                                                    <strong>Rata-Rata</strong>
                                                </td>
                                                <td v-for="(avg, ai) in formulaAverages" :key="'avg-'+ai">{{ avg }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Photos Section -->
                            <div v-if="informasiData && informasiData.sesi_foto === 'Y'" class="mb-4">
                                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:11px;letter-spacing:.5px">
                                    <i class="ri-camera-line me-1"></i>Dokumentasi Foto Analisa
                                </h6>
                                <div v-for="(row, ri) in detailData" :key="'foto-'+ri">
                                    <div v-if="row.foto_analisa && row.foto_analisa.length > 0" class="mb-3">
                                        <p class="small text-muted mb-2">
                                            <i class="ri-barcode-line me-1"></i>{{ row.No_Faktur }}
                                        </p>
                                        <div class="row g-2">
                                            <div v-for="foto in row.foto_analisa" :key="foto.Berkas_Key" class="col-6 col-md-4 col-lg-3">
                                                <div class="border rounded p-1 h-100">
                                                    <div v-if="!fotoBlobUrls[foto.Berkas_Key]"
                                                        class="d-flex align-items-center justify-content-center bg-light rounded"
                                                        style="height:120px">
                                                        <div class="spinner-grow spinner-grow-sm text-primary"></div>
                                                    </div>
                                                    <el-image
                                                        v-else
                                                        class="w-100 rounded"
                                                        style="height:120px;object-fit:cover"
                                                        :src="fotoBlobUrls[foto.Berkas_Key]"
                                                        :preview-src-list="row.foto_analisa.map(f => fotoBlobUrls[f.Berkas_Key]).filter(Boolean)"
                                                        :initial-index="row.foto_analisa.findIndex(f => f.Berkas_Key === foto.Berkas_Key)"
                                                        fit="cover"
                                                        hide-on-click-modal
                                                        @contextmenu.prevent
                                                        @dragstart.prevent
                                                    >
                                                        <template #error>
                                                            <div class="d-flex align-items-center justify-content-center bg-light text-muted w-100 h-100 rounded" style="height:120px">
                                                                <i class="ri-image-line fs-4"></i>
                                                            </div>
                                                        </template>
                                                    </el-image>
                                                    <p class="text-center text-muted mb-0 mt-1" style="font-size:10px">
                                                        {{ foto.Keterangan || '-' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Timeline Chart -->
                            <div class="mb-4">
                                <h6 class="fw-semibold mb-3 text-muted text-uppercase" style="font-size:11px;letter-spacing:.5px">
                                    <i class="ri-calendar-event-line me-1"></i>Timeline Proses Sampel
                                </h6>
                                <apexchart
                                    height="250"
                                    type="rangeBar"
                                    :options="timelineChartOptions"
                                    :series="timelineChartSeries"
                                ></apexchart>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-end gap-2 border-top pt-3">
                                <button
                                    class="btn btn-warning text-white"
                                    @click="openReanalisis"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#offcanvasReanalisis"
                                >
                                    <i class="ri-refresh-line me-1"></i>Uji Ulang
                                </button>
                                <button
                                    class="btn btn-success"
                                    @click="selesaikanAnalisa"
                                    :disabled="loading.saving"
                                >
                                    <span v-if="loading.saving" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="ri-check-double-line me-1"></i>
                                    Simpan & Konfirmasi
                                </button>
                            </div>
                        </div>

                        <!-- No data -->
                        <div v-else-if="!loading.detail" class="card-body text-center py-5">
                            <i class="ri-file-unknow-line fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted small">Tidak ada data hasil analisa tersedia.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offcanvas: Reanalisis / Uji Ulang -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasReanalisis" aria-labelledby="offcanvasReanalisisLabel">
            <div class="offcanvas-header border-bottom">
                <h5 id="offcanvasReanalisisLabel" class="mb-0 fw-semibold">
                    <i class="ri-refresh-line me-2 text-warning"></i>Uji Ulang (Reanalisis)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" @click="resetReanalisisForm"></button>
            </div>
            <div class="offcanvas-body">
                <div class="alert alert-warning-subtle border-warning border-start border-4 mb-3">
                    <small>Pilih nomor sampel reanalisis untuk menggantikan pengujian saat ini.</small>
                </div>
                <form @submit.prevent="submitReanalisis">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No Uji Sebelumnya</label>
                        <input :value="reanalisisForm.noUjiSebelumnya" type="text" disabled class="form-control form-control-sm" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">No Sampel Reanalisis</label>
                        <div v-if="loading.reanalisisOptions" class="placeholder-glow">
                            <span class="placeholder col-12 bg-secondary rounded" style="height:38px;opacity:.3"></span>
                        </div>
                        <v-select
                            v-else
                            v-model="reanalisisForm.selectedOption"
                            :options="reanalisisOptions"
                            label="name"
                            placeholder="--- Pilih No Sampel ---"
                        />
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" :disabled="loading.reanalisis || loading.reanalisisOptions">
                            <span v-if="loading.reanalisis" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="ri-send-plane-line me-1"></i>
                            Lakukan Uji Ulang
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import ApexChart from "vue3-apexcharts";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { ElImage } from "element-plus";
import axios from "axios";
import vSelect from "vue-select";
import { debounce } from "lodash";

export default {
    components: { apexchart: ApexChart, DotLottieVue, ElImage, vSelect },

    data() {
        return {
            // ── List state ──────────────────────────────────────────────
            listData: [],
            searchQuery: "",
            filters: { tanggal: { mulai: "", selesai: "" }, qrcode: "" },
            pagination: { page: 1, limit: 10, totalPage: 0, totalData: 0 },
            loading: { list: false, subPo: false, detail: false, saving: false, reanalisis: false, reanalisisOptions: false },

            // ── Selection state ──────────────────────────────────────────
            selectedItem: null,
            detailVisible: false,
            isMobile: window.innerWidth < 992,

            // ── Multi-QR sub-PO state ────────────────────────────────────
            subPoList: [],
            selectedSubPo: null,

            // ── Detail data ──────────────────────────────────────────────
            detailData: [],
            formulaAverages: [],
            informasiData: null,
            hasStandardConfiguration: true,
            template: { parameter: [], formula: [] },
            fotoBlobUrls: {},

            // ── Reanalisis form ──────────────────────────────────────────
            reanalisisOptions: [],
            reanalisisForm: { noUjiSebelumnya: null, selectedOption: null, noPo: null, idJenisAnalisa: null },
        };
    },

    computed: {
        emptyMessage() {
            return (this.searchQuery || this.filters.tanggal.mulai || this.filters.qrcode)
                ? "Tidak ada data sesuai filter yang diterapkan."
                : "Belum ada data uji analisa menunggu validasi.";
        },

        durationChartSeries() {
            if (!this.detailData.length) return [];
            return [{
                name: "Lama Proses (Hari)",
                data: this.detailData.map((item) => {
                    if (!item.Tanggal_Registrasi || !item.Tanggal) return 0;
                    const diff = new Date(item.Tanggal).getTime() - new Date(item.Tanggal_Registrasi).getTime();
                    return Math.max(0, Math.round(diff / 86400000));
                }),
            }];
        },

        durationChartOptions() {
            if (!this.detailData.length) return {};
            return {
                chart: { type: "bar", height: 200, toolbar: { show: false } },
                plotOptions: { bar: { columnWidth: "50%", dataLabels: { position: "top" } } },
                dataLabels: { enabled: true, formatter: (v) => v + " hari", offsetY: -18, style: { fontSize: "11px", colors: ["#304758"] } },
                xaxis: { categories: this.detailData.map((i) => i.No_Faktur || i.No_Po_Sampel), title: { text: "Nomor Faktur" } },
                yaxis: { title: { text: "Hari" } },
                colors: ["#008FFB"],
            };
        },

        timelineChartSeries() {
            if (!this.detailData.length) return [];
            const proses = [], tunggu = [];
            this.detailData.forEach((item) => {
                if (!item.Tanggal_Registrasi || !item.Tanggal) return;
                const reg = new Date(item.Tanggal_Registrasi);
                const test = new Date(item.Tanggal);
                proses.push({ x: item.No_Faktur || item.No_Po_Sampel, y: [reg.getTime(), test.getTime()] });
                const ts = new Date(reg); ts.setDate(reg.getDate() + 1); ts.setHours(0, 0, 0, 0);
                const te = new Date(test); te.setDate(test.getDate() - 1); te.setHours(23, 59, 59, 999);
                if (te.getTime() > ts.getTime()) tunggu.push({ x: item.No_Faktur || item.No_Po_Sampel, y: [ts.getTime(), te.getTime()] });
            });
            return [{ name: "Total Proses", data: proses }, { name: "Periode Tunggu", data: tunggu }];
        },

        timelineChartOptions() {
            return {
                chart: { type: "rangeBar", height: 250, toolbar: { show: true } },
                plotOptions: { bar: { horizontal: true, barHeight: "70%", rangeBarGroupRows: true } },
                colors: ["#008FFB", "#FF4560"],
                fill: { type: "solid", opacity: 0.8 },
                xaxis: { type: "datetime", labels: { datetimeUTC: false, format: "dd MMM" } },
                yaxis: { title: { text: "Nomor Sampel" } },
                legend: { position: "top" },
                title: { text: "Timeline Registrasi – Pengujian", align: "center" },
            };
        },
    },

    methods: {
        // ── Fetch list ─────────────────────────────────────────────────
        async fetchList(page = 1) {
            this.loading.list = true;
            try {
                const params = { page, limit: this.pagination.limit, q: this.searchQuery, qrcode: this.filters.qrcode || null };
                if (this.filters.tanggal.mulai && this.filters.tanggal.selesai) {
                    params.tanggal_mulai = this.filters.tanggal.mulai;
                    params.tanggal_selesai = this.filters.tanggal.selesai;
                }
                const res = await axios.get("/api/v2/lab/confirmed-selesai/uji-sampel", { params });
                if (res.status === 200 && res.data?.result) {
                    this.listData = res.data.result.data;
                    this.pagination = res.data.result.pagination;
                } else {
                    this.listData = [];
                }
            } catch (e) {
                this.listData = [];
            } finally {
                this.loading.list = false;
            }
        },

        debouncedFetch: debounce(function () {
            this.pagination.page = 1;
            this.fetchList(1);
        }, 500),

        changePage(page) {
            if (page !== this.pagination.page) this.fetchList(page);
        },

        resetFiltersAndFetch() {
            this.searchQuery = "";
            this.filters = { tanggal: { mulai: "", selesai: "" }, qrcode: "" };
            this.fetchList(1);
        },

        // ── Select item ────────────────────────────────────────────────
        async selectItem(item) {
            this.selectedItem = item;
            this.selectedSubPo = null;
            this.subPoList = [];
            this.detailData = [];
            this.formulaAverages = [];
            this.informasiData = null;
            this.hasStandardConfiguration = true;
            this.template = { parameter: [], formula: [] };
            this.revokeBlobUrls();
            this.detailVisible = true;

            if (item.Flag_Multi_QrCode === "Y") {
                await this.fetchSubPoList(item.No_Po_Sampel, item.Id_Jenis_Analisa);
            } else {
                await this.fetchDetailSingle(item.No_Po_Sampel, item.Id_Jenis_Analisa);
            }
        },

        // ── Fetch sub-PO list (multi QR) ───────────────────────────────
        async fetchSubPoList(noPo, idJenisAnalisa) {
            this.loading.subPo = true;
            try {
                const res = await axios.get(`/api/v2/lab/validasi-selesai/uji-sampel/${noPo}/${idJenisAnalisa}`);
                if (res.data?.success) this.subPoList = res.data.result || [];
                else this.subPoList = [];
            } catch {
                this.subPoList = [];
            } finally {
                this.loading.subPo = false;
            }
        },

        // ── Select sub-PO ──────────────────────────────────────────────
        async selectSubPo(sub) {
            this.selectedSubPo = sub;
            this.detailData = [];
            this.formulaAverages = [];
            this.revokeBlobUrls();
            await this.fetchDetailMulti(
                this.selectedItem.No_Po_Sampel,
                sub.No_Fak_Sub_Po,
                this.selectedItem.Id_Jenis_Analisa
            );
        },

        // ── Fetch detail (Multi QR) ────────────────────────────────────
        async fetchDetailMulti(noPo, noFakSub, idJenisAnalisa) {
            this.loading.detail = true;
            try {
                const [dataRes, tplRes] = await Promise.all([
                    axios.get(`/api/v2/lab/verifikasi-analisa/multi/${idJenisAnalisa}/${noPo}/${noFakSub}`),
                    axios.get(`/fetch/lab/lama/${idJenisAnalisa}/parameter-perhitungan-old`),
                ]);
                if (dataRes.data?.success && Array.isArray(dataRes.data.result?.sampel)) {
                    this.template = tplRes.data?.result || { parameter: [], formula: [] };
                    this.informasiData = dataRes.data.result.informasi;
                    this.hasStandardConfiguration = dataRes.data.result.informasi?.has_standard_configuration ?? true;
                    const { data, formulaAverages } = this.processItems(dataRes.data.result.sampel, this.template);
                    this.detailData = data;
                    this.formulaAverages = formulaAverages;
                    this.fetchBlobPhotos();
                }
            } catch (e) {
                this.detailData = [];
            } finally {
                this.loading.detail = false;
            }
        },

        // ── Fetch detail (Single QR) ───────────────────────────────────
        async fetchDetailSingle(noPo, idJenisAnalisa) {
            this.loading.detail = true;
            try {
                const [dataRes, tplRes] = await Promise.all([
                    axios.get(`/api/v2/lab/verifikasi-analisa/single-qrcode/${idJenisAnalisa}/${noPo}`),
                    axios.get(`/fetch/lab/lama/${idJenisAnalisa}/parameter-perhitungan-old`),
                ]);
                if (dataRes.data?.success && Array.isArray(dataRes.data.result?.sampel)) {
                    this.template = tplRes.data?.result || { parameter: [], formula: [] };
                    this.informasiData = dataRes.data.result.informasi;
                    this.hasStandardConfiguration = dataRes.data.result.informasi?.has_standard_configuration ?? true;
                    const { data, formulaAverages } = this.processItems(dataRes.data.result.sampel, this.template);
                    this.detailData = data;
                    this.formulaAverages = formulaAverages;
                    this.fetchBlobPhotos();
                }
            } catch {
                this.detailData = [];
            } finally {
                this.loading.detail = false;
            }
        },

        // ── Process items (same logic as verfikasiv2pcs.vue) ──────────
        processItems(items, template) {
            if (!Array.isArray(items) || items.length === 0) return { data: [], formulaAverages: [] };
            const grouped = items.reduce((acc, item) => {
                const key = item.No_Faktur;
                if (!acc[key]) acc[key] = [];
                acc[key].push(item);
                return acc;
            }, {});

            const processedData = Object.values(grouped).map((group) => {
                const first = group[0];
                const parameterResults = Array.isArray(first.parameter)
                    ? first.parameter.map((p) => p.Hasil_Analisa ?? "-")
                    : [];
                const finalResults = group.map((item) => ({
                    value: item.Hasil_Akhir_Analisa ?? "-",
                    Flag_Layak: item.Flag_Layak,
                    Flag_FG: item.Flag_FG,
                    pembulatan: item.Pembulatan ?? 4,
                }));
                return {
                    No_Po: first.No_Po || "-",
                    No_Split_Po: first.No_Split_Po || "-",
                    No_Batch: first.No_Batch || "-",
                    No_Faktur: first.No_Faktur || "-",
                    Kode_Analisa: first.Kode_Analisa || "-",
                    Flag_Layak: first.Flag_Layak || "-",
                    No_Po_Sampel: first.No_Po_Sampel || "-",
                    No_Fak_Sub_Po: first.No_Fak_Sub_Po || "-",
                    Tanggal: first.Tanggal_Pengujian || "-",
                    Tanggal_Registrasi: first.Tanggal_Registrasi || "-",
                    parameters: parameterResults,
                    results: finalResults,
                    Range_Awal: first.Range_Awal,
                    Range_Akhir: first.Range_Akhir,
                    foto_analisa: first.foto_analisa || [],
                };
            });

            const numFormula = template?.formula?.length || (processedData[0]?.results?.length ?? 0);
            const formulaAverages = [];
            for (let i = 0; i < numFormula; i++) {
                let total = 0, count = 0, dec = 4;
                processedData.forEach((row) => {
                    const r = row.results[i];
                    if (r && r.value !== "-") {
                        const v = parseFloat(r.value);
                        if (!isNaN(v)) { total += v; count++; if (r.pembulatan) dec = parseInt(r.pembulatan); }
                    }
                });
                formulaAverages.push(count > 0 ? (total / count).toFixed(dec) : "-");
            }
            return { data: processedData, formulaAverages };
        },

        // ── Photos ─────────────────────────────────────────────────────
        async fetchBlobPhotos() {
            if (this.informasiData?.sesi_foto !== "Y") return;
            const allKeys = this.detailData.flatMap((item) => (item.foto_analisa || []).map((f) => f.Berkas_Key));
            if (!allKeys.length) return;
            const tokenRes = await axios.post("/api/v1/lab/hasil-uji/berkas/foto/token/bulk", { keys: allKeys });
            const tokenMap = tokenRes.data;
            for (const key of allKeys) {
                const res = await axios.get(`/api/v1/lab/berkas/stream/foto-uji/${key}?token=${tokenMap[key]}`, { responseType: "blob" });
                this.fotoBlobUrls[key] = URL.createObjectURL(res.data);
            }
        },

        revokeBlobUrls() {
            Object.values(this.fotoBlobUrls).forEach((url) => URL.revokeObjectURL(url));
            this.fotoBlobUrls = {};
        },

        // ── Confirm / Simpan ───────────────────────────────────────────
        async selesaikanAnalisa() {
            const confirm = await Swal.fire({
                title: "Konfirmasi Penyelesaian",
                text: "Data analisis akan difinalisasi dan tidak dapat diubah kembali. Lanjutkan?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#d33",
                confirmButtonText: "Ya, Konfirmasi!",
                cancelButtonText: "Batal",
            });
            if (!confirm.isConfirmed) return;

            this.loading.saving = true;
            Swal.fire({ title: "Menyimpan...", allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
            try {
                const res = await axios.post("/api/v2/uji-sampel/confirmed", { analyses: this.detailData });
                if (res.data.success) {
                    Swal.fire({ icon: "success", title: "Berhasil", text: "Data analisis berhasil dikonfirmasi." }).then(() => {
                        this.selectedItem = null;
                        this.detailData = [];
                        this.fetchList(this.pagination.page);
                    });
                } else {
                    throw new Error(res.data.message || "Gagal menyimpan.");
                }
            } catch (e) {
                Swal.fire("Gagal!", e.response?.data?.message || e.message || "Terjadi kesalahan.", "error");
            } finally {
                this.loading.saving = false;
            }
        },

        // ── Reanalisis ─────────────────────────────────────────────────
        openReanalisis() {
            if (!this.selectedItem) return;
            const noFak = this.selectedSubPo?.No_Fak_Sub_Po || this.selectedItem.No_Po_Sampel;
            this.reanalisisForm = {
                noUjiSebelumnya: noFak,
                selectedOption: null,
                noPo: this.selectedItem.No_Po_Sampel,
                idJenisAnalisa: this.selectedItem.Id_Jenis_Analisa,
            };
            this.fetchReanalisisOptions();
        },

        async fetchReanalisisOptions() {
            this.loading.reanalisisOptions = true;
            try {
                const res = await axios.get(
                    `/api/v1/lab/laboratorium/no-uji/sampel/sub/all/${this.reanalisisForm.noPo}/${this.reanalisisForm.idJenisAnalisa}`
                );
                if (res.data?.result) {
                    this.reanalisisOptions = res.data.result.map((i) => ({ value: i.No_Po_Multi, name: i.No_Po_Multi }));
                } else {
                    this.reanalisisOptions = [];
                }
            } catch {
                this.reanalisisOptions = [];
            } finally {
                this.loading.reanalisisOptions = false;
            }
        },

        async submitReanalisis() {
            this.loading.reanalisis = true;
            try {
                const res = await axios.post("/api/v1/lab/resampeling/reanalisis", {
                    No_Po_Sampel: this.reanalisisForm.noPo,
                    No_Sampel_Resampling_Origin: this.reanalisisForm.noUjiSebelumnya,
                    No_Sampel_Resampling: this.reanalisisForm.selectedOption?.value,
                    Id_Jenis_Analisa: this.reanalisisForm.idJenisAnalisa,
                });
                if (res.data.success) {
                    Swal.fire({ icon: "success", title: "Berhasil", text: res.data.message, timer: 1500, showConfirmButton: false })
                        .then(() => this.fetchList(1));
                } else {
                    throw new Error(res.data.message || "Gagal menyimpan.");
                }
            } catch (e) {
                Swal.fire("Error", e.message, "error");
            } finally {
                this.loading.reanalisis = false;
            }
        },

        resetReanalisisForm() {
            this.reanalisisForm = { noUjiSebelumnya: null, selectedOption: null, noPo: null, idJenisAnalisa: null };
            this.reanalisisOptions = [];
        },

        // ── Helpers ────────────────────────────────────────────────────
        formatTanggal(tanggalString) {
            if (!tanggalString) return "-";
            const date = new Date(tanggalString);
            return date.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
        },

        handleResize() {
            this.isMobile = window.innerWidth < 992;
        },
    },

    watch: {
        searchQuery() { this.debouncedFetch(); },
        filters: { handler() { this.debouncedFetch(); }, deep: true },
    },

    mounted() {
        this.fetchList();
        window.addEventListener("resize", this.handleResize);
    },

    beforeUnmount() {
        this.revokeBlobUrls();
        window.removeEventListener("resize", this.handleResize);
    },
};
</script>

<style scoped>
/* ── Layout ─────────────────────────────────────────────────────────── */
.validasi-page {
    min-height: calc(100vh - 120px);
}

.list-scroll {
    overflow-y: auto;
    max-height: calc(100vh - 260px);
}

/* ── List items ─────────────────────────────────────────────────────── */
.list-item {
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background 0.15s ease;
    position: relative;
}

.list-item:hover {
    background-color: #f8f9fa;
}

.list-item.active {
    background-color: #eff6ff;
    border-left: 3px solid #3b82f6;
}

.list-item.border-success-left {
    border-left: 3px solid transparent;
}
.list-item.border-success-left:not(.active) {
    border-left-color: #198754;
}
.list-item.border-danger-left:not(.active) {
    border-left: 3px solid #dc3545;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Skeleton ───────────────────────────────────────────────────────── */
.list-item-skeleton {
    border: 1px solid #f0f0f0;
}
.skeleton-line {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 400% 100%;
    animation: shimmer 1.4s infinite;
    border-radius: 4px;
}
@keyframes shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

/* ── Badge soft variants (Velzon-compatible) ────────────────────────── */
.badge-soft-primary   { background-color: #dbeafe; color: #1d4ed8; }
.badge-soft-success   { background-color: #dcfce7; color: #166534; }
.badge-soft-danger    { background-color: #fee2e2; color: #991b1b; }
.badge-soft-warning   { background-color: #fef3c7; color: #92400e; }
.badge-soft-info      { background-color: #dbeafe; color: #1e40af; }
.badge-soft-secondary { background-color: #f1f5f9; color: #475569; }

/* ── Table tweaks ───────────────────────────────────────────────────── */
.table thead th { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }
.table tbody td { font-size: 13px; }
</style>
