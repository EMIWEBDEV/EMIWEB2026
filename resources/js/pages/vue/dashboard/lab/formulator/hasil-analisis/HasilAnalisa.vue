<template>
    <div class="ha-root">
        <!-- TOP BAR -->
        <div class="ha-topbar">
            <div class="ha-topbar-left">
                <div class="ha-topbar-icon"><i class="ri-flask-line"></i></div>
                <div>
                    <span class="ha-topbar-title">Hasil Analisa Formulator</span>
                    <span class="ha-topbar-sub">Kumpulan data hasil pengujian trial produksi</span>
                </div>
            </div>
            <div class="ha-topbar-right">
                <template v-if="selectedJenis">
                    <div class="ha-topbar-jenis">
                        <span class="ha-topbar-jenis-chip"><i class="ri-flask-line me-1"></i>{{ selectedJenis.Kode_Analisa }}</span>
                        <span class="ha-topbar-jenis-name">{{ selectedJenis.Jenis_Analisa }}</span>
                    </div>
                    <div v-if="pagination.totalData > 0" class="ha-stat-badge">
                        <span class="ha-stat-num">{{ pagination.totalData }}</span>
                        <span class="ha-stat-lbl">Sampel</span>
                    </div>
                </template>
                <span v-else class="ha-topbar-hint"><i class="ri-arrow-left-line me-1"></i>Pilih analisa di panel kiri</span>
                <button class="ha-print-btn" @click="togglePrintModal"><i class="ri-printer-line me-1"></i>Cetak Laporan</button>
            </div>
        </div>

        <!-- BODY -->
        <div class="ha-body">
            <!-- LEFT -->
            <div class="ha-left" :class="{ 'ha-hidden-mobile': detailVisible && isMobile }">

                <!-- Jenis Analisa badge filter -->
                <div class="ha-jenis-panel">
                    <div class="ha-jenis-header">
                        <span class="ha-jenis-title"><i class="ri-flask-line me-1"></i>Jenis Analisa</span>
                        <span v-if="loading.jenis" class="spinner-border spinner-border-sm text-primary" style="width:14px;height:14px;"></span>
                        <span v-else class="ha-jenis-total">{{ jenisAnalisaList.length }}</span>
                    </div>
                    <div class="ha-jenis-search-wrap">
                        <i class="ri-search-line ha-jenis-search-icon"></i>
                        <input type="text" class="ha-jenis-search" placeholder="Cari jenis analisa..." v-model="jenisSearch" />
                        <button v-if="jenisSearch" class="ha-search-x" @click="jenisSearch=''"><i class="ri-close-line"></i></button>
                    </div>
                    <div v-if="loading.jenis" class="ha-jenis-loading"><span class="spinner-border spinner-border-sm text-muted me-2"></span><span class="text-muted small">Memuat...</span></div>
                    <div v-else-if="filteredJenisList.length===0" class="ha-jenis-empty"><span class="text-muted small">Tidak ada jenis analisa</span></div>
                    <div v-else class="ha-jenis-badges">
                        <button v-for="j in filteredJenisList" :key="j.Id_Jenis_Analisa"
                            class="ha-jenis-badge" :class="selectedJenisId===j.Id_Jenis_Analisa?'ha-jenis-badge--active':''"
                            @click="selectJenis(j)" :title="j.Jenis_Analisa">
                            <i class="ri-flask-line me-1"></i>
                            <span class="ha-jenis-badge-code">{{ j.Kode_Analisa }}</span>
                            <span class="ha-jenis-badge-name">{{ j.Jenis_Analisa }}</span>
                        </button>
                    </div>
                </div>

                <div class="ha-left-divider"></div>

                <div v-if="!selectedJenisId" class="ha-left-empty">
                    <i class="ri-hand-pointing-up-line"></i>
                    <p>Pilih jenis analisa di atas</p>
                </div>
                <template v-else>
                    <div class="ha-filter-bar">
                        <div class="ha-search-wrap">
                            <i class="ri-search-line ha-search-icon"></i>
                            <input type="text" class="ha-search-input" placeholder="Cari No. PO, Batch, Mesin..."
                                v-model="searchQuery" @input="debounceFetch" />
                            <button v-if="searchQuery" class="ha-search-x" @click="searchQuery='';fetchSamples()"><i class="ri-close-line"></i></button>
                        </div>
                        <div class="ha-filter-row">
                            <input type="date" class="ha-date-input" v-model="filters.startDate" @change="fetchSamples()" />
                            <span class="ha-sep">—</span>
                            <input type="date" class="ha-date-input" v-model="filters.endDate" @change="fetchSamples()" />
                        </div>
                        <div class="ha-filter-row ha-filter-row--inline">
                            <select class="ha-select" v-model="filters.qrcode" @change="fetchSamples()">
                                <option value="">Semua QR</option>
                                <option value="multi">Multi QR</option>
                                <option value="single">Single QR</option>
                            </select>
                            <select class="ha-select" v-model="filters.status" @change="fetchSamples()">
                                <option value="terima">Diterima</option>
                                <option value="">Semua Status</option>
                                <option value="tolak">Ditolak</option>
                            </select>
                            <button class="ha-btn-reset" @click="resetFilters" title="Reset"><i class="ri-filter-off-line"></i></button>
                        </div>
                    </div>
                    <div class="ha-list">
                        <div v-if="loading.list" class="p-3"><div v-for="i in 5" :key="i" class="ha-skeleton mb-2"></div></div>
                        <div v-else-if="sampleList.length === 0" class="ha-empty-list">
                            <i class="ri-inbox-2-line"></i><p>Tidak ada data sampel</p>
                        </div>
                        <div v-else>
                            <button v-for="item in sampleList" :key="item.No_Po_Sampel"
                                class="ha-item" :class="{ 'ha-item--active': isActive(item) }"
                                @click="selectSample(item)">
                                <div class="ha-item-accent"></div>
                                <div class="ha-item-body">
                                    <div class="ha-item-top">
                                        <span class="ha-item-title">{{ item.No_Po_Sampel }}</span>
                                        <span class="ha-item-jenis-badge">{{ selectedJenis?.Kode_Analisa || '-' }}</span>
                                    </div>
                                    <div class="ha-item-sub" v-if="item.nama_barang">{{ item.nama_barang }}</div>
                                    <div class="ha-item-meta">
                                        <span class="ha-chip ha-chip--blue" v-if="item.no_po"><i class="ri-file-list-3-line"></i>{{ item.no_po }}</span>
                                        <span class="ha-chip ha-chip--gray" v-if="item.nama_mesin"><i class="ri-settings-3-line"></i>{{ item.nama_mesin }}</span>
                                    </div>
                                    <div class="ha-item-date" v-if="item.tanggal_pengujian">
                                        <i class="ri-calendar-check-line me-1"></i>{{ formatDate(item.tanggal_pengujian) }}
                                    </div>
                                </div>
                                <i class="ri-arrow-right-s-line ha-item-arrow"></i>
                            </button>
                        </div>
                    </div>
                    <div class="ha-list-footer">
                        <span class="ha-page-info">
                            {{ sampleList.length > 0 ? ((pagination.page-1)*pagination.limit+1)+'–'+((pagination.page-1)*pagination.limit+sampleList.length) : 0 }}
                            dari {{ pagination.totalData }}
                        </span>
                        <div class="ha-page-btns" v-if="pagination.totalPage > 1">
                            <button class="ha-page-btn" :disabled="pagination.page===1" @click="changePage(1)" title="Pertama"><i class="ri-skip-back-line"></i></button>
                            <button class="ha-page-btn" :disabled="pagination.page===1" @click="changePage(pagination.page-1)"><i class="ri-arrow-left-s-line"></i></button>
                            <span class="ha-page-current">{{ pagination.page }} / {{ pagination.totalPage }}</span>
                            <button class="ha-page-btn" :disabled="pagination.page===pagination.totalPage" @click="changePage(pagination.page+1)"><i class="ri-arrow-right-s-line"></i></button>
                            <button class="ha-page-btn" :disabled="pagination.page===pagination.totalPage" @click="changePage(pagination.totalPage)" title="Terakhir"><i class="ri-skip-forward-line"></i></button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- RIGHT -->
            <div class="ha-right" :class="{ 'ha-hidden-mobile': !detailVisible && isMobile }">
                <div v-if="isMobile && detailVisible" class="ha-mobile-back"><button class="btn btn-sm btn-soft-secondary" @click="detailVisible=false"><i class="ri-arrow-left-line me-1"></i>Daftar</button></div>
                <div v-if="!selectedSample" class="ha-detail-empty">
                    <div class="ha-detail-empty-inner">
                        <div class="ha-empty-icon-wrap"><i class="ri-flask-line"></i></div>
                        <h6>Pilih sampel untuk melihat hasil analisa</h6>
                        <p v-if="!selectedJenisId">Pilih jenis analisa di topbar terlebih dahulu.</p>
                        <p v-else>Klik sampel di daftar kiri untuk melihat detail hasil analisa.</p>
                    </div>
                </div>
                <template v-else>
                    <!-- Header -->
                    <div class="ha-detail-header">
                        <div class="ha-dh-main">
                            <div class="ha-dh-icon"><i class="ri-flask-line"></i></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="ha-dh-title">{{ selectedSample.nama_barang || selectedSample.No_Po_Sampel }}</div>
                                <div class="ha-dh-sampel">{{ selectedSample.No_Po_Sampel }}</div>
                                <div class="ha-dh-badges">
                                    <span class="ha-badge ha-badge--primary" v-if="selectedSample.no_po"><i class="ri-receipt-line me-1"></i>{{ selectedSample.no_po }}</span>
                                    <span class="ha-badge ha-badge--gray" v-if="selectedSample.no_split_po"><i class="ri-git-branch-line me-1"></i>{{ selectedSample.no_split_po }}</span>
                                    <span class="ha-badge" :class="selectedSample.flag_multi==='Y'?'ha-badge--primary':'ha-badge--gray'">
                                        <i class="ri-qr-code-line me-1"></i>{{ selectedSample.flag_multi==='Y'?'Multi QR':'Single QR' }}
                                    </span>
                                    <span class="ha-badge ha-badge--form"><i class="ri-shield-star-line me-1"></i>Formulator</span>
                                </div>
                            </div>
                        </div>
                        <!-- PLT banner -->
                        <div v-if="informasiData?.is_plt && informasiData?.plt_pembanding?.length" class="ha-plt-banner">
                            <div class="ha-plt-icon"><i class="ri-heart-pulse-line"></i></div>
                            <div>
                                <div class="ha-plt-title">Uji Palatabilitas</div>
                                <div class="ha-plt-chips">
                                    <span v-for="(pb, i) in informasiData.plt_pembanding" :key="i" class="ha-plt-chip"><i class="ri-flask-line me-1"></i>{{ pb?.nama || pb }}</span>
                                </div>
                            </div>
                        </div>
                        <!-- Multi QR sub-sample selector -->
                        <div v-if="selectedSample.flag_multi === 'Y'" class="ha-sub-bar">
                            <span class="ha-sub-label"><i class="ri-qr-code-line me-1"></i>Sub Sampel:</span>
                            <div v-if="loading.sub" class="ha-sub-loading"><span class="spinner-border spinner-border-sm me-1" style="color:#405189;"></span><span class="text-muted small">Memuat...</span></div>
                            <div v-else class="ha-sub-pills">
                                <button v-for="sub in subSamples" :key="sub.No_Fak_Sub_Po"
                                    class="ha-sub-pill" :class="{ 'ha-sub-pill--active': selectedSub === sub.No_Fak_Sub_Po }"
                                    @click="selectSub(sub.No_Fak_Sub_Po)">
                                    <i class="ri-qr-code-line me-1"></i>{{ sub.No_Fak_Sub_Po }}
                                </button>
                            </div>
                        </div>
                        <!-- Info row -->
                        <div class="ha-info-row" v-if="informasiData">
                            <div class="ha-info-item" v-if="informasiData.No_Batch"><span class="ha-info-lbl">Batch</span><span class="ha-info-val">{{ informasiData.No_Batch }}</span></div>
                            <div class="ha-info-item" v-if="informasiData.Seri_Mesin"><span class="ha-info-lbl">Seri Mesin</span><span class="ha-info-val">{{ informasiData.Seri_Mesin }}</span></div>
                            <div class="ha-info-item" v-if="informasiData.Tanggal_Pengujian"><span class="ha-info-lbl">Tgl Uji</span><span class="ha-info-val">{{ formatDate(informasiData.Tanggal_Pengujian) }}</span></div>
                        </div>
                    </div>
                    <!-- Tabs -->
                    <div class="ha-tabs">
                        <button class="ha-tab" :class="{'ha-tab--active':activeTab==='analisa'}" @click="activeTab='analisa'"><i class="ri-flask-line me-1"></i>Hasil Analisa</button>
                        <button class="ha-tab" :class="{'ha-tab--active':activeTab==='timeline'}" @click="activeTab='timeline';loadTimeline()">
                            <i class="ri-timeline-view me-1"></i>Timeline
                        </button>
                    </div>
                    <!-- Content -->
                    <div class="ha-detail-body">
                        <div v-if="loading.detail" class="ha-loading-state"><div class="spinner-border" style="color:#405189;"></div><p class="mt-3 text-muted small">Memuat data analisa...</p></div>
                        <div v-else-if="selectedSample.flag_multi==='Y' && !selectedSub" class="ha-loading-state">
                            <i class="ri-qr-code-line fs-1 text-muted"></i><p class="text-muted small mt-2">Pilih sub sampel di atas untuk melihat detail</p>
                        </div>
                        <!-- ANALISA TAB -->
                        <template v-else-if="activeTab==='analisa'">
                            <div v-if="tableRows.length===0" class="ha-loading-state"><i class="ri-inbox-2-line fs-1 text-muted"></i><p class="text-muted small mt-2">Tidak ada data hasil analisa</p></div>
                            <div v-else>
                                <div v-if="informasiData?.is_sop" class="ha-sop-bar">
                                    <i class="ri-bar-chart-line me-1"></i><span class="ha-sop-label">Range SOP:</span>
                                    <span class="ha-sop-val">{{ informasiData.Range_Awal }} — {{ informasiData.Range_Akhir }}</span>
                                    <span v-if="informasiData?.Catatan" class="ha-sop-note ms-2"><i class="ri-sticky-note-line me-1"></i>{{ informasiData.Catatan }}</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0 ha-data-table">
                                        <thead>
                                            <tr>
                                                <th class="text-center ha-th-no">#</th>
                                                <th v-if="informasiData?.is_plt" class="ha-th-plt"><i class="ri-flask-line me-1"></i>Pembanding</th>
                                                <th>No Transaksi</th><th>No Sampel</th><th>No PO</th><th>No Split Po</th>
                                                <th v-if="selectedSample.flag_multi==='Y'">No Sub Sampel</th>
                                                <th>Tanggal</th>
                                                <template v-if="hasTemplate">
                                                    <th v-for="param in template.parameter" :key="param.id_qc">
                                                        {{ param.nama_parameter }}<small v-if="param.satuan" class="d-block fw-normal opacity-75" style="font-size:.7em;">{{ param.satuan }}</small>
                                                    </th>
                                                    <th v-for="f in template.formula" :key="f.id||f.nama_kolom" class="ha-th-formula">{{ f.nama_kolom }}</th>
                                                </template>
                                                <th v-else>Hasil</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(row,ri) in tableRows" :key="ri" :class="getRowClass(row)">
                                                <td class="text-center fw-semibold ha-td-no">{{ ri+1 }}</td>
                                                <td v-if="informasiData?.is_plt" class="ha-td-plt"><span class="ha-pembanding">{{ row.Nama_Pembanding||'—' }}</span></td>
                                                <td class="ha-td-mono">{{ row.No_Faktur||'-' }}</td>
                                                <td class="ha-td-mono">{{ row.No_Po_Sampel||'-' }}</td>
                                                <td>{{ row.No_Po||'-' }}</td><td>{{ row.No_Split_Po||'-' }}</td>
                                                <td v-if="selectedSample.flag_multi==='Y'">{{ row.No_Fak_Sub_Po||'-' }}</td>
                                                <td>{{ formatDate(row.Tanggal) }}</td>
                                                <template v-if="hasTemplate">
                                                    <td v-for="(val,pi) in (row.parameters||[])" :key="pi">{{ val }}</td>
                                                    <template v-if="template.formula?.length>0">
                                                        <td v-for="(res,fi) in (row.results||[])" :key="fi" class="ha-td-formula fw-semibold">{{ res?.value??'-' }}</td>
                                                    </template>
                                                </template>
                                                <td v-else class="fw-semibold">{{ row.Hasil_Akhir_Analisa??'-' }}</td>
                                            </tr>
                                            <tr v-if="(informasiData?.Flag_Perhitungan==='Y'||tableRows[0]?.Flag_Perhitungan==='Y') && template.formula?.length>0 && formulaAverages.length>0" class="ha-row--rata">
                                                <td :colspan="getBaseColCount()" class="text-end pe-3 fw-bold fst-italic text-secondary">Rata-Rata</td>
                                                <td v-for="(avg,ai) in formulaAverages" :key="ai" class="ha-td-formula fw-bold">{{ avg }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Foto strip -->
                            <div v-if="allFotos().length>0" class="ha-foto-strip">
                                <button class="ha-foto-btn" @click="openFotoModal"><i class="ri-image-2-line me-1"></i>Lihat Foto ({{ allFotos().length }})</button>
                            </div>
                        </template>
                        <!-- TIMELINE TAB -->
                        <template v-else-if="activeTab==='timeline'">
                            <div v-if="loading.timeline" class="ha-loading-state"><div class="spinner-border spinner-border-sm" style="color:#405189;"></div><p class="text-muted small mt-2">Memuat...</p></div>
                            <div v-else-if="auditLog.length===0" class="ha-loading-state"><i class="ri-history-line fs-1 text-muted"></i><p class="text-muted small mt-2">Belum ada riwayat aktivitas</p></div>
                            <div v-else class="ha-vtl">
                                <div class="ha-vtl-hdr"><i class="ri-history-line me-2"></i>Riwayat Proses Sampel</div>
                                <div class="ha-vtl-steps">
                                    <div v-for="(log,li) in expandedAuditLog" :key="li" class="ha-vtl-step" :class="getStepClass(log)">
                                        <div class="ha-vtl-indicator">
                                            <div class="ha-vtl-dot"><i :class="getStepIcon(log)"></i></div>
                                            <div v-if="li<expandedAuditLog.length-1" class="ha-vtl-line"></div>
                                        </div>
                                        <div class="ha-vtl-body">
                                            <div class="ha-vtl-row1"><span class="ha-vtl-badge" :class="getStepBadgeClass(log)">{{ formatAksi(log.Jenis_Aksi) }}</span><span v-if="log.Sub_Aksi" class="ha-vtl-sub" :class="log.Sub_Aksi==='TOLAK'?'text-danger':'text-success'">{{ log.Sub_Aksi }}</span></div>
                                            <div class="ha-vtl-meta"><span><i class="ri-user-3-line me-1"></i>{{ log.Nama_User||log.Id_User }}</span><span><i class="ri-time-line me-1"></i>{{ formatDate(log.Tanggal) }}<template v-if="log.Jam"> · {{ log.Jam.substring(0,5) }}</template></span></div>
                                            <div v-if="log.details&&log.details.length" class="ha-vtl-details"><div class="ha-vtl-details-hdr"><i class="ri-microscope-line me-1"></i>{{ log.details.length }} analisa</div><div v-for="d in log.details" :key="d.Id_Jenis_Analisa+'_'+d.Jam" class="ha-vtl-detail-row"><i class="ri-arrow-right-s-line text-muted"></i>{{ d.Nama_Jenis_Analisa }}</div></div>
                                            <div v-if="log.Keterangan" class="ha-vtl-note"><i class="ri-chat-3-line me-1"></i>{{ log.Keterangan }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Print Modal -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ri-printer-line me-2"></i>Cetak Laporan Analisis</h5>
                    <button type="button" class="btn-close btn-close-white" @click="closePrintModal"></button>
                </div>
                <div class="modal-body">
                    <div class="pr-steps-progress mb-4">
                        <div class="pr-step" :class="{active:currentStep===1}"><div class="pr-step-num">1</div><div class="pr-step-lbl">Pilih Jenis Analisa</div></div>
                        <div class="pr-step" :class="{active:currentStep===2}"><div class="pr-step-num">2</div><div class="pr-step-lbl">Atur Periode</div></div>
                        <div class="pr-step" :class="{active:currentStep===3}"><div class="pr-step-num">3</div><div class="pr-step-lbl">Preview & Cetak</div></div>
                    </div>
                    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                        <i class="ri-error-warning-line text-warning fs-5 mt-1"></i>
                        <div><strong>Perhatian!</strong><br />Data yang akan dicetak hanya mencakup <strong>nomor sampel yang sudah ditutup (closed)</strong> atau <strong>telah difinalisasi</strong>.</div>
                    </div>
                    <!-- Step 1 -->
                    <div v-show="currentStep===1" class="pr-step-content">
                        <h6 class="fw-bold mb-3 text-primary"><i class="ri-flask-line me-2"></i>Pilih Jenis Analisa</h6>
                        <div class="pr-analysis-selector">
                            <div v-for="item in jenisAnalisaList" :key="item.Id_Jenis_Analisa"
                                class="pr-analysis-option mb-2" :class="{selected:selectedAnalysis===item.Id_Jenis_Analisa}"
                                @click="toggleAnalysisSelection(item)">
                                <div class="pr-option-icon"><i class="ri-flask-line"></i></div>
                                <div class="pr-option-details">
                                    <span class="badge bg-primary-soft mb-1">{{ item.Kode_Analisa }}</span>
                                    <h6>{{ item.Jenis_Analisa }}</h6>
                                </div>
                                <div class="pr-option-check"><i class="ri-check-line"></i></div>
                            </div>
                        </div>
                    </div>
                    <!-- Step 2 -->
                    <div v-show="currentStep===2" class="pr-step-content">
                        <h6 class="fw-bold mb-3 text-primary"><i class="ri-calendar-line me-2"></i>Atur Periode Laporan</h6>
                        <div class="pr-date-picker">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Dari Tanggal</label>
                                    <input type="date" class="form-control" v-model="startDate" :max="endDate||today" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sampai Tanggal</label>
                                    <input type="date" class="form-control" v-model="endDate" :min="startDate" :max="today" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Step 3 -->
                    <div v-show="currentStep===3" class="pr-step-content">
                        <h6 class="fw-bold mb-3 text-primary"><i class="ri-eye-line me-2"></i>Ringkasan Laporan</h6>
                        <div class="pr-summary">
                            <div class="pr-summary-card">
                                <div class="pr-summary-hdr"><i class="ri-information-line"></i><h5>Detail Laporan</h5></div>
                                <div class="pr-summary-body">
                                    <div class="pr-summary-item"><span>Jenis Analisa:</span><strong>{{ selectedAnalysisName || '-' }}</strong></div>
                                    <div class="pr-summary-item"><span>Periode:</span><strong>{{ formattedStartDate }} - {{ formattedEndDate }}</strong></div>
                                    <div class="pr-summary-item">
                                        <span>Format:</span>
                                        <div class="d-flex gap-3">
                                            <div class="form-check"><input class="form-check-input" type="radio" id="pr-fmt-excel-f" value="excel" v-model="exportFormat"><label class="form-check-label" for="pr-fmt-excel-f"><i class="ri-file-excel-line text-success me-1"></i>Excel</label></div>
                                            <div class="form-check"><input class="form-check-input" type="radio" id="pr-fmt-pdf-f" value="pdf" v-model="exportFormat"><label class="form-check-label" for="pr-fmt-pdf-f"><i class="ri-file-pdf-line text-danger me-1"></i>PDF</label></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-info mt-3"><i class="ri-information-line me-2"></i>Laporan akan dihasilkan berdasarkan kriteria di atas. Pastikan data sudah benar sebelum mencetak.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" @click="prevStep" :disabled="currentStep===1"><i class="ri-arrow-left-line me-1"></i>Kembali</button>
                    <button type="button" class="btn btn-primary" @click="nextStep" v-if="currentStep<3">Lanjut<i class="ri-arrow-right-line ms-1"></i></button>
                    <button type="button" class="btn btn-success" @click="generateReport" v-if="currentStep===3"><i class="ri-file-download-line me-1"></i>Generate Laporan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LIGHTBOX -->
    <div v-if="lightbox.show" class="ha-lightbox" @click="lightbox.show=false">
        <button class="ha-lightbox-close" @click.stop="lightbox.show=false"><i class="ri-close-line"></i></button>
        <div class="ha-lightbox-inner" @click.stop>
            <img :src="lightbox.url" class="ha-lightbox-img" />
            <div v-if="lightbox.keterangan" class="ha-lightbox-caption">{{ lightbox.keterangan }}</div>
        </div>
    </div>

    <!-- FOTO MODAL - Polaroid Grid -->
    <div v-if="fotoModal.show" class="ha-foto-backdrop" @click.self="fotoModal.show=false">
        <div class="ha-foto-modal">
            <div class="ha-foto-modal-hdr">
                <span><i class="ri-image-2-line me-2"></i>Foto Analisa <span class="text-muted" style="font-size:.75rem;">({{ fotoModal.photos.length }} foto)</span></span>
                <button @click="fotoModal.show=false"><i class="ri-close-line"></i></button>
            </div>
            <div class="ha-foto-grid-wrap">
                <div v-if="fotoModal.loading" class="ha-foto-state"><span class="spinner-border spinner-border-sm me-2" style="color:#405189;"></span><span class="text-muted small">Memuat foto...</span></div>
                <div v-else-if="fotoModal.photos.length===0" class="ha-foto-state"><i class="ri-image-line fs-1 text-muted"></i><p class="text-muted small mt-2">Tidak ada foto</p></div>
                <div v-else class="ha-foto-grid">
                    <div v-for="(photo,pi) in fotoModal.photos" :key="pi" class="ha-polaroid" @click="lightbox={show:true,url:photo.url,keterangan:photo.keterangan}">
                        <div class="ha-polaroid-img-wrap">
                            <img :src="photo.url" class="ha-polaroid-img" :alt="photo.keterangan||'Foto '+(pi+1)" />
                            <div class="ha-polaroid-overlay"><i class="ri-zoom-in-line"></i></div>
                        </div>
                        <div class="ha-polaroid-caption">{{ photo.keterangan || '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
export default {
    props: {
        selected_id:            { type: [String, Number], default: null },
        initial_no_po_sampel:   { type: String, default: null },
        initial_flag_multi:     { type: String, default: null },
        initial_no_sub:         { type: String, default: null },
    },
    data() {
        return {
            jenisAnalisaList: [], selectedJenisId: "", selectedJenis: null,
            jenisSearch: "",
            loading: { jenis: false, list: false, detail: false, sub: false, timeline: false },
            sampleList: [], selectedSample: null,
            pagination: { page: 1, limit: 20, totalPage: 1, totalData: 0 },
            searchQuery: "", searchTimeout: null,
            filters: { startDate: "", endDate: "", qrcode: "", status: "terima" },
            subSamples: [], selectedSub: null,
            template: { parameter: [], formula: [] },
            tableRows: [], formulaAverages: [], informasiData: null,
            activeTab: "analisa", auditLog: [],
            isMobile: false, detailVisible: false,
            blobUrlCache: {},
            fotoModal: { show: false, photos: [], loading: false },
            lightbox: { show: false, url: '', keterangan: '' },

            // Print / Cetak Laporan
            printModal: null,
            currentStep: 1,
            selectedAnalysis: null,
            selectedIsPerhitungan: null,
            startDate: "",
            endDate: "",
            exportFormat: "excel",
        };
    },
    watch: {
        currentStep(val) { sessionStorage.setItem("printStep", val); },
        selectedAnalysis(val) { sessionStorage.setItem("printSelectedAnalysis", val ?? ""); },
        startDate(val) { sessionStorage.setItem("printStartDate", val ?? ""); },
        endDate(val) { sessionStorage.setItem("printEndDate", val ?? ""); },
        exportFormat(val) { sessionStorage.setItem("printExportFormat", val); },
    },
    computed: {
        expandedAuditLog() {
            const result = [];
            for (const log of this.auditLog) {
                if (!log.details || log.details.length === 0) { result.push(log); continue; }
                const byUser = new Map();
                for (const d of log.details) {
                    const uid = d.Id_User || '—';
                    if (!byUser.has(uid)) byUser.set(uid, []);
                    byUser.get(uid).push(d);
                }
                if (byUser.size <= 1) { result.push(log); } else {
                    for (const [uid, items] of byUser) {
                        result.push({ ...log, Id_User: uid, Nama_User: uid, Tanggal: items[0].Tanggal || log.Tanggal, Jam: items[0].Jam || log.Jam, details: items, _expanded: true });
                    }
                }
            }
            return result;
        },
        hasTemplate() { return (this.template.parameter?.length||0)>0||(this.template.formula?.length||0)>0; },
        filteredJenisList() {
            if (!this.jenisSearch.trim()) return this.jenisAnalisaList;
            const q = this.jenisSearch.toLowerCase();
            return this.jenisAnalisaList.filter(j => j.Jenis_Analisa?.toLowerCase().includes(q) || j.Kode_Analisa?.toLowerCase().includes(q));
        },
        today() { return new Date().toISOString().split("T")[0]; },
        formattedStartDate() { return this.startDate ? new Date(this.startDate).toLocaleDateString("id-ID") : "-"; },
        formattedEndDate() { return this.endDate ? new Date(this.endDate).toLocaleDateString("id-ID") : "-"; },
        selectedAnalysisName() { const f = this.jenisAnalisaList.find(j => j.Id_Jenis_Analisa === this.selectedAnalysis); return f ? f.Jenis_Analisa : null; },
    },
    methods: {
        async fetchJenisAnalisa() {
            this.loading.jenis = true;
            try {
                const res = await axios.get("/api/v1/formulator/hasil-trial/uji-trial");
                this.jenisAnalisaList = res.data?.result || [];
                if (this.selected_id) {
                    const found = this.jenisAnalisaList.find(j => j.Id_Jenis_Analisa === this.selected_id);
                    if (found) {
                        await this.selectJenis(found);
                        if (this.initial_no_po_sampel) {
                            const sample = this.sampleList.find(s => s.No_Po_Sampel === this.initial_no_po_sampel);
                            if (sample) await this.selectSample(sample);
                            if (this.initial_no_sub && this.subSamples.length > 0) await this.selectSub(this.initial_no_sub);
                        }
                    }
                }
            } catch { this.jenisAnalisaList = []; } finally { this.loading.jenis = false; }
        },
        async selectJenis(jenis) {
            if (this.selectedJenisId === jenis.Id_Jenis_Analisa) return;
            this.selectedJenisId = jenis.Id_Jenis_Analisa; this.selectedJenis = jenis;
            this.sampleList = []; this.selectedSample = null;
            this.pagination = { page: 1, limit: 20, totalPage: 1, totalData: 0 };
            this.searchQuery = ""; this.filters = { startDate: "", endDate: "", qrcode: "", status: "terima" };
            this.resetDetail(); await this.fetchSamples();
        },
        debounceFetch() { clearTimeout(this.searchTimeout); this.searchTimeout = setTimeout(() => { this.pagination.page=1; this.fetchSamples(); }, 400); },
        async fetchSamples() {
            if (!this.selectedJenisId) return;
            this.loading.list = true;
            try {
                const params = { page: this.pagination.page, limit: this.pagination.limit, q: this.searchQuery };
                if (this.filters.qrcode) params.qrcode = this.filters.qrcode;
                if (this.filters.status) params.status = this.filters.status;
                if (this.filters.startDate && this.filters.endDate) { params.tanggal_mulai = this.filters.startDate; params.tanggal_selesai = this.filters.endDate; }
                const res = await axios.get(`/api/v1/formulator/hasil-trial/${this.selectedJenisId}`, { params });
                if (res.data?.result) {
                    const data = res.data.result.data_sampel || {};
                    this.sampleList = Object.entries(data).map(([no_po_sampel, item]) => ({ ...item, No_Po_Sampel: no_po_sampel }));
                    const pg = res.data.result.pagination || {};
                    this.pagination = { page: pg.page||1, limit: pg.limit||10, totalPage: pg.totalPage||1, totalData: pg.totalData||this.sampleList.length };
                } else { this.sampleList = []; }
            } catch { this.sampleList = []; } finally { this.loading.list = false; }
        },
        changePage(p) { if (p>=1&&p<=this.pagination.totalPage){this.pagination.page=p;this.fetchSamples();} },
        resetFilters() { this.searchQuery=""; this.filters={startDate:"",endDate:"",qrcode:"",status:"terima"}; this.pagination.page=1; this.fetchSamples(); },
        async selectSample(item) {
            this.selectedSample = item; this.detailVisible = true; this.activeTab = "analisa"; this.resetDetail();
            if (item.flag_multi === 'Y') await this.fetchSubSamples(item.No_Po_Sampel);
            else await this.fetchDetail(item.No_Po_Sampel, null);
        },
        async fetchSubSamples(noSampel) {
            this.loading.sub = true; this.subSamples = []; this.selectedSub = null;
            try {
                const res = await axios.get(`/api/v1/formulator/hasil-trial/sub/${this.selectedJenisId}/${noSampel}`);
                this.subSamples = res.data?.result || [];
                if (this.subSamples.length > 0) await this.selectSub(this.subSamples[0].No_Fak_Sub_Po);
            } catch { this.subSamples = []; } finally { this.loading.sub = false; }
        },
        async selectSub(noFakSub) { this.selectedSub = noFakSub; await this.fetchDetail(this.selectedSample.No_Po_Sampel, noFakSub); },
        async fetchDetail(noSampel, noSub) {
            if (!this.selectedJenisId) return;
            this.loading.detail = true; this.tableRows = []; this.formulaAverages = []; this.informasiData = null;
            try {
                const isSingle = !noSub;
                const dataUrl = isSingle
                    ? `/api/v1/formulator/hasil-trial/uji-trial/no-multi/${this.selectedJenisId}/${noSampel}`
                    : `/api/v1/formulator/hasil-trial/uji-trial/multi/${this.selectedJenisId}/${noSampel}/Y/${noSub}`;
                const [dataRes, templateRes] = await Promise.all([
                    axios.get(dataUrl).catch(() => null),
                    axios.get(`/api/v1/formulator/uji-trial/${this.selectedJenisId}/parameter-perhitungan-old`).catch(() => null),
                ]);
                this.template = templateRes?.data?.result || { parameter: [], formula: [] };
                const result = dataRes?.data?.result || {};
                const sampel = result.sampel || [];
                this.informasiData = result.informasi || null;
                if (sampel.length > 0 && this.informasiData) {
                    const first = sampel[0];
                    if (!this.informasiData.Flag_Perhitungan) this.informasiData.Flag_Perhitungan = first.Flag_Perhitungan;
                    if (!this.informasiData.No_Po)           this.informasiData.No_Po           = first.No_Po;
                    if (!this.informasiData.No_Split_Po)     this.informasiData.No_Split_Po     = first.No_Split_Po;
                    if (!this.informasiData.No_Batch)        this.informasiData.No_Batch        = first.No_Batch;
                    if (this.informasiData.is_sop === undefined) this.informasiData.is_sop     = first.is_sop;
                    if (this.informasiData.Range_Awal  === undefined) this.informasiData.Range_Awal  = first.Range_Awal;
                    if (this.informasiData.Range_Akhir === undefined) this.informasiData.Range_Akhir = first.Range_Akhir;
                }
                const { data, formulaAverages } = this.processItems(sampel, this.template);
                this.tableRows = data; this.formulaAverages = formulaAverages;
            } catch (err) { console.error(err); this.tableRows = []; } finally { this.loading.detail = false; }
        },
        processItems(items, template) {
            if (!Array.isArray(items)||items.length===0) return{data:[],formulaAverages:[]};
            const tplParams=template?.parameter?.length||0; const tplFormula=template?.formula?.length||0;
            if(!(tplParams>0||tplFormula>0)){return{data:items.map(item=>({No_Faktur:item.No_Faktur||'-',No_Po_Sampel:item.No_Po_Sampel||'-',No_Fak_Sub_Po:item.No_Fak_Sub_Po||'-',No_Po:item.No_Po||'-',No_Split_Po:item.No_Split_Po||'-',Tanggal:item.Tanggal_Pengujian||'-',Nama_Pembanding:item.Nama_Pembanding||null,Hasil_Akhir_Analisa:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak||null,Flag_Perhitungan:item.Flag_Perhitungan,parameters:[],results:[],foto_analisa:item.foto_analisa||[]})),formulaAverages:[]};}
            const grouped=items.reduce((acc,item)=>{const k=item.No_Faktur;if(!acc[k])acc[k]=[];acc[k].push(item);return acc;},{});
            const processedData=Object.values(grouped).map(group=>{
                const first=group[0]; const detailParams=Array.isArray(first.parameter)?first.parameter:[];
                let parameterResults,finalResults;
                if(detailParams.length>0){parameterResults=detailParams.map(p=>this.formatHasil(p.Hasil_Analisa));finalResults=tplFormula>0?group.map(item=>({value:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak,pembulatan:item.Pembulatan??4})):[];}
                else if(group.length>1&&tplParams>0&&tplFormula===0){parameterResults=group.map(item=>this.formatHasil(item.Hasil_Akhir_Analisa));finalResults=[];}
                else{parameterResults=[];finalResults=tplFormula>0?group.map(item=>({value:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak,pembulatan:item.Pembulatan??4})):[];}
                const worstLayak=group.some(i=>i.Flag_Layak==='T')?'T':group.some(i=>i.Flag_Layak==='Y')?'Y':null;
                return{No_Faktur:first.No_Faktur||'-',No_Po_Sampel:first.No_Po_Sampel||'-',No_Fak_Sub_Po:first.No_Fak_Sub_Po||'-',No_Po:first.No_Po||'-',No_Split_Po:first.No_Split_Po||'-',Tanggal:first.Tanggal_Pengujian||'-',Nama_Pembanding:first.Nama_Pembanding||null,Hasil_Akhir_Analisa:this.formatHasil(first.Hasil_Akhir_Analisa),Flag_Layak:worstLayak,Flag_Perhitungan:first.Flag_Perhitungan,Range_Awal:first.Range_Awal,Range_Akhir:first.Range_Akhir,is_sop:first.is_sop,parameters:parameterResults,results:finalResults,foto_analisa:group.flatMap(i=>i.foto_analisa||[])};
            });
            const formulaAverages=[];
            for(let i=0;i<tplFormula;i++){let total=0,count=0,dp=4;processedData.forEach(row=>{const r=row.results[i];if(r&&r.value!=='-'){const v=parseFloat(r.value);if(!isNaN(v)){total+=v;count++;if(r.pembulatan)dp=parseInt(r.pembulatan,10);}}});formulaAverages.push(count>0?(total/count).toFixed(dp):'-');}
            return{data:processedData,formulaAverages};
        },
        formatHasil(val){if(val===null||val===undefined)return'-';const s=String(val).trim();if(!s||s==='null'||s==='undefined')return'-';if(/^-?\d+\.0+$/.test(s))return String(Math.trunc(parseFloat(s)));return s;},
        getBaseColCount(){let c=6;if(this.informasiData?.is_plt)c++;if(this.selectedSample?.flag_multi==='Y')c++;return c+(this.template.parameter?.length||0);},
        getRowClass(row){if(row.Flag_Layak==='T')return'ha-row--ng';if(row.Flag_Layak==='Y')return'ha-row--ok';if(row.is_sop&&row.Range_Awal!==null&&row.Range_Akhir!==null){const v=parseFloat(row.Hasil_Akhir_Analisa);if(!isNaN(v))return v>=row.Range_Awal&&v<=row.Range_Akhir?'ha-row--ok':'ha-row--ng';}return'';},
        async loadTimeline(){if(this.auditLog.length>0||!this.selectedSample)return;this.loading.timeline=true;try{const res=await axios.get(`/api/v1/log-aksi/by-sampel/${this.selectedSample.No_Po_Sampel}`);this.auditLog=res.data?.result||[];}catch{this.auditLog=[];}finally{this.loading.timeline=false;}},
        resetDetail(){this.subSamples=[];this.selectedSub=null;this.tableRows=[];this.formulaAverages=[];this.informasiData=null;this.template={parameter:[],formula:[]};this.auditLog=[];this.activeTab="analisa";this.fotoModal={show:false,photos:[],loading:false};},
        allFotos(){return this.tableRows.flatMap(r=>r.foto_analisa||[]);},
        async openFotoModal(){
            const fotos=this.allFotos();
            if(!fotos.length)return;
            this.fotoModal={show:true,photos:[],loading:true};
            try{
                const keysToFetch=fotos.map(f=>f.Berkas_Key).filter(k=>k&&!this.blobUrlCache[k]);
                if(keysToFetch.length>0){
                    const tokenRes=await axios.post('/api/v1/formulator/hasil-uji/berkas/foto/token/bulk',{keys:keysToFetch});
                    const tokenMap=tokenRes.data||{};
                    await Promise.all(keysToFetch.map(async k=>{
                        try{const res=await axios.get(`/api/v1/formulator/berkas/stream/foto-uji/${k}?token=${tokenMap[k]}`,{responseType:'blob'});this.blobUrlCache[k]=URL.createObjectURL(res.data);}catch{}
                    }));
                }
                this.fotoModal={show:true,loading:false,photos:fotos.map(f=>({url:this.blobUrlCache[f.Berkas_Key]||'',keterangan:f.Keterangan||f.keterangan||''})).filter(p=>p.url)};
            }catch(e){this.fotoModal={show:true,loading:false,photos:[]};}
        },
        isActive(item){return this.selectedSample?.No_Po_Sampel===item.No_Po_Sampel;},
        formatDate(d){if(!d)return'-';try{return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});}catch{return d;}},
        formatAksi(aksi){const map={INPUT_ANALYZER:'Input Analyzer',VALIDASI_PRODUKSI:'Validasi Produksi',VALIDASI_TRIAL_PRODUKSI:'Validasi Trial',FINALISASI_PRODUKSI:'Finalisasi Produksi',FINALISASI_TRIAL_PRODUKSI:'Finalisasi Trial',VALIDASI_FORMULATOR:'Validasi Formulator',PRAFINALISASI_FORMULATOR:'Pra-Finalisasi',FINALISASI_FORMULATOR:'Finalisasi Formulator'};return map[aksi]||aksi;},
        getStepClass(log){if(log.Sub_Aksi==='TOLAK')return'vtl-rejected';if(log.Jenis_Aksi?.includes('FINALISASI'))return'vtl-final';return'vtl-done';},
        getStepIcon(log){if(log.Sub_Aksi==='TOLAK')return'ri-close-line';if(log.Jenis_Aksi==='INPUT_ANALYZER')return'ri-test-tube-line';if(log.Jenis_Aksi?.includes('FINALISASI'))return'ri-shield-star-line';return'ri-check-line';},
        getStepBadgeClass(log){if(log.Sub_Aksi==='TOLAK')return'vtl-badge--danger';if(log.Jenis_Aksi==='INPUT_ANALYZER')return'vtl-badge--info';if(log.Jenis_Aksi?.includes('FINALISASI'))return'vtl-badge--final';return'vtl-badge--success';},
        checkMobile(){this.isMobile=window.innerWidth<768;},

        // ─── Print / Cetak Laporan ─────────────────────────────────────
        togglePrintModal() {
            if (!this.printModal) {
                this.printModal = new bootstrap.Modal(document.getElementById("printModal"));
            }
            const savedStep = sessionStorage.getItem("printStep");
            const savedAnalysis = sessionStorage.getItem("printSelectedAnalysis");
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
        nextStep() { if (this.validateCurrentStep()) this.currentStep++; },
        prevStep() { this.currentStep--; },
        validateCurrentStep() {
            if (this.currentStep === 1 && !this.selectedAnalysis) {
                Swal.fire({ icon: "warning", title: "Peringatan", text: "Pilih minimal satu jenis analisa" });
                return false;
            }
            if (this.currentStep === 2 && (!this.startDate || !this.endDate)) {
                Swal.fire({ icon: "warning", title: "Peringatan", text: "Isi periode tanggal lengkap" });
                return false;
            }
            return true;
        },
        toggleAnalysisSelection(item) {
            if (this.selectedAnalysis === item.Id_Jenis_Analisa) {
                this.selectedAnalysis = null; this.selectedIsPerhitungan = null;
            } else {
                this.selectedAnalysis = item.Id_Jenis_Analisa; this.selectedIsPerhitungan = item.Flag_Perhitungan;
            }
        },
        async generateReport() {
            Swal.fire({ title: "Mohon Tunggu", html: "Laporan sedang diproses dan dibuat...", allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            try {
                const payload = { analysis: this.selectedAnalysis, Flag_Perhitungan: this.selectedIsPerhitungan, startDate: this.startDate, endDate: this.endDate, format: this.exportFormat };
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
                let response;
                if (this.exportFormat === "pdf") {
                    response = await axios.post("/rekap-sampel/pdf", payload, { headers: { "X-CSRF-TOKEN": csrfToken }, responseType: "blob" });
                } else {
                    response = await axios.post("/api/v1/download-rekap/analisa", payload, { headers: { "X-CSRF-TOKEN": csrfToken }, responseType: "blob" });
                }
                Swal.close();
                const blob = new Blob([response.data], { type: response.headers["content-type"] });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement("a");
                link.href = url;
                let fileName = `laporan-rekap.${this.exportFormat === "pdf" ? "pdf" : "xlsx"}`;
                const contentDisposition = response.headers["content-disposition"] || response.headers["Content-Disposition"];
                if (contentDisposition) { const m = contentDisposition.match(/filename\*?=(?:(?:UTF-8'')?["']?)([^;"']+)/i); if (m && m[1]) fileName = decodeURIComponent(m[1]); }
                link.setAttribute("download", fileName);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
                Swal.fire({ icon: "success", title: "Sukses", text: "Laporan berhasil diunduh.", timer: 2000, showConfirmButton: false });
            } catch (error) {
                try {
                    const reader = new FileReader();
                    reader.onload = () => {
                        try {
                            const parsed = JSON.parse(reader.result);
                            Swal.fire({ icon: "warning", title: "Tidak Ditemukan", text: parsed?.message || "Terjadi kesalahan saat membuat laporan." });
                        } catch { Swal.fire({ icon: "error", title: "Gagal Memproses Laporan", text: "Terjadi kesalahan internal. Silakan coba lagi nanti." }); }
                    };
                    reader.readAsText(error.response?.data);
                } catch { Swal.fire({ icon: "error", title: "Gagal Memproses Laporan", text: "Terjadi kesalahan tidak terduga." }); }
            }
        },
        resetPrintForm() {
            this.currentStep = 1; this.selectedAnalysis = null; this.selectedIsPerhitungan = null;
            this.startDate = ""; this.endDate = ""; this.exportFormat = "excel";
        },
    },
    mounted(){
        this.checkMobile();
        window.addEventListener('resize',this.checkMobile);
        this.fetchJenisAnalisa();
        const savedPrintStep = sessionStorage.getItem("printStep");
        if (savedPrintStep) { this.$nextTick(() => this.togglePrintModal()); }
    },
    beforeUnmount(){window.removeEventListener('resize',this.checkMobile);},
};
</script>

<style scoped>
.ha-root{display:flex;flex-direction:column;height:100vh;overflow:hidden;background:#f0f2f5;font-family:'Segoe UI',system-ui,sans-serif;}
/* TOP BAR - Velzon primary */
.ha-topbar{display:flex;align-items:center;justify-content:space-between;padding:0 20px;min-height:54px;background:#fff;border-bottom:1px solid #e2e8f0;flex-shrink:0;gap:12px;flex-wrap:wrap;}
.ha-topbar-left{display:flex;align-items:center;gap:10px;}
.ha-topbar-icon{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#2e3a64,#405189);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;flex-shrink:0;}
.ha-topbar-title{font-weight:700;font-size:.9rem;color:#0f172a;display:block;line-height:1.2;}
.ha-topbar-sub{font-size:.68rem;color:#94a3b8;display:block;}
.ha-topbar-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.ha-jenis-wrap{display:flex;align-items:center;gap:7px;}
.ha-jenis-label{font-size:.74rem;font-weight:600;color:#64748b;white-space:nowrap;}
.ha-jenis-select-wrap{position:relative;}
.ha-jenis-select-icon{position:absolute;left:8px;top:50%;transform:translateY(-50%);color:#405189;font-size:.82rem;pointer-events:none;}
.ha-jenis-select{padding:5px 10px 5px 26px;border:1px solid #c7d2fe;border-radius:7px;font-size:.78rem;font-weight:600;color:#405189;background:#eef2ff;outline:none;cursor:pointer;min-width:220px;max-width:340px;}
.ha-jenis-select:focus{border-color:#818cf8;box-shadow:0 0 0 3px rgba(196,181,253,.15);}
.ha-stat-badge{display:flex;flex-direction:column;align-items:center;background:#eef2ff;border:1px solid #c7d2fe;border-radius:7px;padding:3px 9px;}
.ha-stat-num{font-weight:700;font-size:1rem;color:#405189;line-height:1;}
.ha-stat-lbl{font-size:.58rem;color:#818cf8;text-transform:uppercase;letter-spacing:.4px;}
.ha-body{display:flex;flex:1;overflow:hidden;}
/* LEFT */
.ha-left{width:380px;min-width:300px;display:flex;flex-direction:column;border-right:1px solid #c7d2fe;background:#fff;overflow:hidden;}
/* Jenis analisa panel */
.ha-jenis-panel{flex-shrink:0;padding:10px 12px;border-bottom:1px solid #eef2ff;}
.ha-jenis-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;}
.ha-jenis-title{font-size:.72rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.4px;}
.ha-jenis-total{font-size:.68rem;background:#eef2ff;color:#405189;padding:1px 6px;border-radius:10px;font-weight:700;}
.ha-jenis-search-wrap{position:relative;margin-bottom:7px;}
.ha-jenis-search-icon{position:absolute;left:7px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.75rem;}
.ha-jenis-search{width:100%;padding:5px 24px 5px 24px;border:1px solid #e2e8f0;border-radius:6px;font-size:.76rem;outline:none;background:#f8fafc;}
.ha-jenis-search:focus{border-color:#818cf8;background:#fff;}
.ha-jenis-loading,.ha-jenis-empty{display:flex;align-items:center;justify-content:center;padding:8px;}
.ha-jenis-badges{display:flex;flex-direction:column;gap:3px;max-height:200px;overflow-y:auto;padding-right:2px;}
.ha-jenis-badge{display:flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;border:1px solid transparent;background:#f8fafc;cursor:pointer;text-align:left;font-size:.77rem;transition:all .12s;width:100%;color:#374151;}
.ha-jenis-badge:hover{background:#eef2ff;border-color:#c7d2fe;}
.ha-jenis-badge--active{background:#405189 !important;color:#fff !important;border-color:#405189;}
.ha-jenis-badge--active .ha-jenis-badge-code,.ha-jenis-badge--active .ha-jenis-badge-name{color:#fff;}
.ha-jenis-badge-code{font-weight:700;font-size:.72rem;flex-shrink:0;}
.ha-jenis-badge-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.74rem;flex:1;}
.ha-item-jenis-badge{display:inline-flex;align-items:center;padding:1px 6px;border-radius:4px;font-size:.63rem;font-weight:700;background:rgba(64,81,137,.1);color:#405189;flex-shrink:0;white-space:nowrap;}
.ha-topbar-jenis{display:flex;align-items:center;gap:6px;}
.ha-topbar-jenis-chip{display:inline-flex;align-items:center;padding:3px 9px;border-radius:5px;font-size:.72rem;font-weight:700;background:rgba(64,81,137,.1);color:#405189;}
.ha-topbar-jenis-name{font-size:.8rem;font-weight:600;color:#374151;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ha-topbar-hint{font-size:.76rem;color:#94a3b8;font-style:italic;}
.ha-left-divider{height:1px;background:#f1f5f9;flex-shrink:0;}
.ha-left-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:24px;color:#94a3b8;text-align:center;gap:8px;}
.ha-left-empty i{font-size:2rem;} .ha-left-empty p{font-size:.8rem;margin:0;}
/* pagination footer */
.ha-list-footer{display:flex;align-items:center;justify-content:space-between;padding:7px 12px;border-top:1px solid #c7d2fe;background:#fff;flex-shrink:0;gap:6px;}
.ha-page-info{font-size:.69rem;color:#94a3b8;flex-shrink:0;}
.ha-page-btns{display:flex;align-items:center;gap:3px;}
.ha-page-btn{width:24px;height:24px;border:1px solid #e2e8f0;border-radius:5px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:.12s;}
.ha-page-btn:hover:not(:disabled){border-color:#818cf8;color:#405189;}
.ha-page-btn:disabled{opacity:.35;cursor:not-allowed;}
.ha-page-current{font-size:.72rem;color:#475569;font-weight:600;padding:0 4px;white-space:nowrap;}
.ha-filter-bar{padding:8px 12px;border-bottom:1px solid #eef2ff;flex-shrink:0;}
.ha-search-wrap{position:relative;margin-bottom:7px;}
.ha-search-icon{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.82rem;}
.ha-search-input{width:100%;padding:7px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:.8rem;outline:none;background:#f8fafc;}
.ha-search-input:focus{border-color:#818cf8;box-shadow:0 0 0 3px rgba(196,181,253,.15);background:#fff;}
.ha-search-x{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:none;background:none;color:#94a3b8;cursor:pointer;font-size:.82rem;padding:0;}
.ha-filter-row{display:flex;gap:5px;align-items:center;margin-bottom:5px;}
.ha-filter-row--inline{flex-wrap:wrap;}
.ha-date-input{flex:1;min-width:98px;padding:4px 7px;border:1px solid #e2e8f0;border-radius:5px;font-size:.76rem;}
.ha-sep{color:#94a3b8;font-size:.8rem;flex-shrink:0;}
.ha-select{flex:1;min-width:80px;padding:4px 7px;border:1px solid #e2e8f0;border-radius:5px;font-size:.76rem;background:#fff;}
.ha-btn-reset{padding:4px 9px;border:1px solid #fecaca;border-radius:5px;background:#fff;color:#ef4444;cursor:pointer;font-size:.78rem;}
.ha-list{flex:1;overflow-y:auto;}
.ha-skeleton{height:78px;background:linear-gradient(90deg,#eef2ff 25%,#c7d2fe 37%,#eef2ff 63%);background-size:400% 100%;border-radius:7px;animation:ha-pulse 1.4s infinite;}
@keyframes ha-pulse{0%{background-position:100% 50%}100%{background-position:0 50%}}
.ha-empty-list{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 16px;color:#94a3b8;gap:8px;text-align:center;}
.ha-empty-list i{font-size:1.8rem;} .ha-empty-list p{font-size:.8rem;margin:0;}
.ha-item{width:100%;display:flex;align-items:center;border:none;background:none;cursor:pointer;padding:10px 12px;text-align:left;position:relative;transition:background .12s;border-bottom:1px solid #eef2ff;}
.ha-item:hover{background:#f8fafc;} .ha-item--active{background:#eef2ff !important;}
.ha-item-accent{width:3px;height:100%;position:absolute;left:0;top:0;background:transparent;border-radius:0 2px 2px 0;}
.ha-item--active .ha-item-accent{background:#405189;}
.ha-item-body{flex:1;overflow:hidden;padding-left:2px;}
.ha-item-top{display:flex;align-items:center;justify-content:space-between;gap:5px;margin-bottom:2px;}
.ha-item-title{font-weight:700;font-size:.8rem;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ha-item-sub{font-size:.69rem;color:#64748b;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ha-item-meta{display:flex;gap:3px;flex-wrap:wrap;margin-bottom:2px;}
.ha-item-date{font-size:.67rem;color:#94a3b8;}
.ha-item-arrow{color:#cbd5e1;font-size:.95rem;flex-shrink:0;}
.ha-chip{display:inline-flex;align-items:center;gap:2px;padding:1px 6px;border-radius:3px;font-size:.64rem;font-weight:600;}
.ha-chip i{font-size:.66rem;}
.ha-chip--blue{background:#eef2ff;color:#6366f1;} .ha-chip--gray{background:#f1f5f9;color:#64748b;}
.ha-badge{display:inline-flex;align-items:center;padding:1px 7px;border-radius:4px;font-size:.68rem;font-weight:600;white-space:nowrap;}
.ha-badge i{font-size:.68rem;}
.ha-badge--primary{background:rgba(64,81,137,.1);color:#405189;}
.ha-badge--form{background:rgba(64,81,137,.1);color:#405189;}
.ha-badge--success{background:rgba(10,179,156,.1);color:#0ab39c;border:1px solid rgba(10,179,156,.2);}
.ha-badge--gray{background:#f1f5f9;color:#475569;}
.ha-list-footer{display:flex;align-items:center;justify-content:space-between;padding:7px 12px;border-top:1px solid #c7d2fe;background:#fff;flex-shrink:0;}
.ha-page-info{font-size:.72rem;color:#94a3b8;}
.ha-page-btns{display:flex;align-items:center;gap:5px;}
.ha-page-btn{width:26px;height:26px;border:1px solid #e2e8f0;border-radius:5px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;}
.ha-page-btn:disabled{opacity:.4;cursor:not-allowed;}
.ha-page-current{font-size:.75rem;color:#475569;font-weight:600;}
/* RIGHT */
.ha-right{flex:1;display:flex;flex-direction:column;overflow:hidden;background:#f0f2f5;}
.ha-detail-empty{flex:1;display:flex;align-items:center;justify-content:center;}
.ha-detail-empty-inner{text-align:center;color:#94a3b8;max-width:280px;}
.ha-empty-icon-wrap{width:60px;height:60px;border-radius:50%;background:rgba(64,81,137,.1);color:#405189;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.6rem;}
.ha-detail-empty-inner h6{color:#475569;font-weight:600;margin-bottom:6px;}
.ha-detail-empty-inner p{font-size:.8rem;margin:0;}
.ha-detail-header{padding:13px 16px;background:#fff;border-bottom:1px solid #c7d2fe;flex-shrink:0;}
.ha-dh-main{display:flex;align-items:flex-start;gap:10px;margin-bottom:9px;}
.ha-dh-icon{width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,#2e3a64,#405189);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.ha-dh-title{font-weight:700;font-size:.9rem;color:#0f172a;}
.ha-dh-sampel{font-size:.72rem;color:#64748b;margin:1px 0 5px;font-family:monospace;}
.ha-dh-badges{display:flex;gap:3px;flex-wrap:wrap;}
.ha-plt-banner{display:flex;align-items:flex-start;gap:9px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1px solid #bae6fd;border-radius:8px;padding:9px 12px;margin-bottom:9px;}
.ha-plt-icon{width:30px;height:30px;border-radius:7px;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.85rem;}
.ha-plt-title{font-size:.7rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px;}
.ha-plt-chips{display:flex;flex-wrap:wrap;gap:4px;}
.ha-plt-chip{background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;padding:2px 9px;border-radius:12px;font-size:.73rem;font-weight:600;}
.ha-sub-bar{display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;}
.ha-sub-label{font-size:.73rem;font-weight:600;color:#475569;white-space:nowrap;}
.ha-sub-loading{display:flex;align-items:center;}
.ha-sub-pills{display:flex;flex-wrap:wrap;gap:4px;}
.ha-sub-pill{padding:4px 11px;border:1px solid #c7d2fe;border-radius:6px;background:#f8fafc;color:#475569;font-size:.76rem;font-weight:600;cursor:pointer;transition:.12s;}
.ha-sub-pill:hover{border-color:#818cf8;color:#405189;}
.ha-sub-pill--active{background:#405189;color:#fff;border-color:#405189;}
.ha-info-row{display:flex;flex-wrap:wrap;gap:6px 16px;}
.ha-info-item{display:flex;align-items:baseline;gap:5px;}
.ha-info-lbl{font-size:.68rem;color:#94a3b8;} .ha-info-val{font-size:.76rem;font-weight:600;color:#374151;font-family:monospace;}
.ha-sop-bar{display:flex;align-items:center;gap:6px;padding:6px 12px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:7px;margin-bottom:8px;font-size:.76rem;color:#405189;flex-wrap:wrap;}
.ha-sop-label{font-weight:700;} .ha-sop-val{font-weight:600;font-family:monospace;} .ha-sop-note{color:#64748b;font-style:italic;}
.ha-tabs{display:flex;border-bottom:1px solid #c7d2fe;background:#fff;flex-shrink:0;padding:0 14px;}
.ha-tab{padding:9px 14px;border:none;background:none;font-size:.8rem;font-weight:500;color:#94a3b8;cursor:pointer;border-bottom:2px solid transparent;display:flex;align-items:center;gap:4px;transition:.12s;}
.ha-tab--active{color:#405189;border-bottom-color:#405189;font-weight:600;}
.ha-tab-count{background:#405189;color:#fff;border-radius:10px;padding:1px 6px;font-size:.6rem;font-weight:700;}
.ha-detail-body{flex:1;overflow-y:auto;padding:10px 12px;}
.ha-loading-state{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:180px;gap:8px;}
/* TABLE - Velzon-aligned */
.ha-data-table{font-size:.77rem;}
.ha-data-table thead th{background:#405189;color:#fff;font-weight:600;font-size:.7rem;white-space:nowrap;padding:7px 9px;border-color:#2e3a64;}
.ha-data-table td{padding:5px 9px;vertical-align:middle;border-color:#e9ecf0;}
.ha-th-no{width:32px;} .ha-th-plt{background:#0891b2 !important;color:#fff !important;}
.ha-th-formula{background:#3a4d86 !important;color:#c7d2fe !important;}
.ha-td-no{width:32px;color:#64748b;}
.ha-td-plt{background:rgba(8,145,178,.05);border-left:3px solid #0891b2 !important;}
.ha-td-formula{background:rgba(64,81,137,.04);}
.ha-td-mono{font-family:monospace;font-size:.74rem;}
.ha-pembanding{font-weight:700;font-size:.74rem;color:#0369a1;}
.ha-row--ok{background:rgba(10,179,156,.06);}
.ha-row--ok td{border-color:rgba(10,179,156,.15) !important;}
.ha-row--ng{background:rgba(240,101,72,.06);}
.ha-row--ng td{border-color:rgba(240,101,72,.15) !important;}
.ha-row--rata{background:rgba(64,81,137,.04);}
.ha-row--rata td{border-color:rgba(64,81,137,.15) !important;}
/* TIMELINE */
.ha-vtl{padding:2px;} .ha-vtl-hdr{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;padding:0 2px 10px;display:flex;align-items:center;}
.ha-vtl-steps{display:flex;flex-direction:column;} .ha-vtl-step{display:flex;gap:10px;}
.ha-vtl-indicator{display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:30px;}
.ha-vtl-dot{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;flex-shrink:0;border:2px solid;}
.vtl-done .ha-vtl-dot{background:rgba(10,179,156,.1);border-color:#0ab39c;color:#0ab39c;}
.vtl-rejected .ha-vtl-dot{background:rgba(240,101,72,.1);border-color:#f06548;color:#f06548;}
.vtl-final .ha-vtl-dot{background:rgba(64,81,137,.1);border-color:#405189;color:#405189;}
.ha-vtl-line{width:2px;flex:1;min-height:10px;background:#e2e8f0;margin:2px 0;}
.vtl-done .ha-vtl-line{background:#0ab39c;}
.ha-vtl-body{padding-bottom:18px;flex:1;min-width:0;}
.ha-vtl-row1{display:flex;align-items:center;gap:7px;margin-bottom:3px;flex-wrap:wrap;}
.ha-vtl-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:4px;font-size:.7rem;font-weight:700;}
.vtl-badge--success{background:rgba(10,179,156,.1);color:#0ab39c;} .vtl-badge--danger{background:rgba(240,101,72,.1);color:#f06548;}
.vtl-badge--final{background:rgba(64,81,137,.1);color:#405189;} .vtl-badge--info{background:#ecfeff;color:#0e7490;}
.ha-vtl-sub{font-size:.68rem;font-weight:700;}
.ha-vtl-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:.73rem;color:#64748b;margin-bottom:3px;}
.ha-vtl-details{font-size:.7rem;color:#64748b;background:#eef2ff;border-radius:5px;padding:5px 9px;margin-top:3px;}
.ha-vtl-details-hdr{font-weight:600;color:#475569;margin-bottom:2px;}
.ha-vtl-detail-row{display:flex;align-items:flex-start;gap:2px;line-height:1.6;}
.ha-vtl-note{font-size:.72rem;color:#64748b;margin-top:3px;font-style:italic;padding:3px 7px;background:#eef2ff;border-left:2px solid #c7d2fe;}
.ha-hidden-mobile{display:none !important;}
@media(min-width:768px){.ha-hidden-mobile{display:flex !important;}}
.ha-mobile-back{padding:9px 12px;border-bottom:1px solid #e2e8f0;flex-shrink:0;background:#fff;}
.ha-foto-strip{padding:7px 0 0;}
.ha-foto-btn{display:inline-flex;align-items:center;padding:4px 11px;border:1px solid #c7d2fe;border-radius:5px;background:#eef2ff;color:#405189;font-size:.76rem;font-weight:600;cursor:pointer;}
.ha-foto-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1060;display:flex;align-items:center;justify-content:center;padding:14px;backdrop-filter:blur(2px);}
.ha-foto-modal{background:#fff;border-radius:12px;width:100%;max-width:760px;box-shadow:0 20px 50px rgba(0,0,0,.25);overflow:hidden;}
.ha-foto-modal-hdr{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;background:linear-gradient(135deg,#2e3a64,#405189);color:#fff;font-weight:600;font-size:.88rem;}
.ha-foto-modal-hdr button{border:none;background:rgba(255,255,255,.2);color:#fff;border-radius:5px;padding:3px 8px;cursor:pointer;font-size:.9rem;}
.ha-foto-grid-wrap{max-height:72vh;overflow-y:auto;padding:16px;}
.ha-foto-state{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:120px;}
.ha-foto-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
@media(max-width:560px){.ha-foto-grid{grid-template-columns:repeat(2,1fr);}}
.ha-polaroid{background:#fff;border-radius:3px;padding:10px 10px 0;box-shadow:0 3px 10px rgba(0,0,0,.18),0 1px 3px rgba(0,0,0,.1);transition:transform .2s,box-shadow .2s;cursor:pointer;}
.ha-polaroid:hover{transform:scale(1.04) rotate(-0.5deg);box-shadow:0 8px 24px rgba(0,0,0,.22);}
.ha-polaroid-img-wrap{width:100%;aspect-ratio:1/1;overflow:hidden;background:#f0f2f5;border-radius:1px;position:relative;}
.ha-polaroid-img{width:100%;height:100%;object-fit:cover;display:block;}
.ha-polaroid-overlay{position:absolute;inset:0;background:rgba(64,81,137,.35);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .18s;color:#fff;font-size:1.4rem;}
.ha-polaroid:hover .ha-polaroid-overlay{opacity:1;}
.ha-polaroid-caption{font-size:.72rem;text-align:center;padding:8px 4px 10px;color:#475569;font-weight:500;line-height:1.3;min-height:34px;display:flex;align-items:center;justify-content:center;}
/* LIGHTBOX */
.ha-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:1080;display:flex;align-items:center;justify-content:center;padding:20px;cursor:zoom-out;}
.ha-lightbox-close{position:absolute;top:16px;right:16px;border:none;background:rgba(255,255,255,.15);color:#fff;border-radius:50%;width:38px;height:38px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;cursor:pointer;transition:background .15s;}
.ha-lightbox-close:hover{background:rgba(255,255,255,.3);}
.ha-lightbox-inner{display:flex;flex-direction:column;align-items:center;max-width:90vw;max-height:90vh;cursor:default;}
.ha-lightbox-img{max-width:100%;max-height:80vh;object-fit:contain;border-radius:4px;box-shadow:0 8px 40px rgba(0,0,0,.6);}
.ha-lightbox-caption{margin-top:12px;color:#e2e8f0;font-size:.82rem;font-weight:500;text-align:center;max-width:500px;}

/* ─── CETAK LAPORAN BUTTON ─── */
.ha-print-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:none;border-radius:8px;background:linear-gradient(135deg,#405189,#2e3a64);color:#fff;font-size:.78rem;font-weight:600;cursor:pointer;box-shadow:0 2px 8px rgba(64,81,137,.35);transition:all .2s;white-space:nowrap;}
.ha-print-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(64,81,137,.45);}
.ha-print-btn:active{transform:translateY(0);}

/* ─── PRINT MODAL STEPS ─── */
.pr-steps-progress{display:flex;justify-content:space-between;position:relative;}
.pr-steps-progress::before{content:"";position:absolute;top:15px;left:0;right:0;height:3px;background:#e9ecef;z-index:0;}
.pr-step{display:flex;flex-direction:column;align-items:center;position:relative;z-index:1;flex:1;}
.pr-step-num{width:32px;height:32px;border-radius:50%;background:#e9ecef;display:flex;align-items:center;justify-content:center;margin-bottom:8px;border:2px solid #e9ecef;transition:all .3s;font-size:.85rem;font-weight:700;color:#6c757d;}
.pr-step-lbl{font-size:.8rem;color:#6c757d;text-align:center;}
.pr-step.active .pr-step-num{background:#405189;color:#fff;border-color:#405189;}
.pr-step.active .pr-step-lbl{color:#405189;font-weight:600;}

/* ─── PRINT MODAL ANALYSIS SELECTOR ─── */
.pr-analysis-selector{max-height:360px;overflow-y:auto;padding-right:4px;}
.pr-analysis-selector::-webkit-scrollbar{width:6px;}
.pr-analysis-selector::-webkit-scrollbar-track{background:#f1f1f1;border-radius:4px;}
.pr-analysis-selector::-webkit-scrollbar-thumb{background:#405189;border-radius:4px;}
.pr-analysis-option{display:flex;align-items:center;padding:11px 14px;border-radius:8px;border:1px solid #dee2e6;cursor:pointer;transition:all .2s;background:#fff;}
.pr-analysis-option:hover{border-color:#86b7fe;box-shadow:0 0 0 3px rgba(64,81,137,.1);}
.pr-analysis-option.selected{border-color:#405189;background:rgba(64,81,137,.05);}
.pr-option-icon{width:38px;height:38px;border-radius:8px;background:rgba(64,81,137,.1);display:flex;align-items:center;justify-content:center;margin-right:14px;color:#405189;font-size:1rem;flex-shrink:0;}
.pr-option-details{flex:1;}
.pr-option-details h6{margin:0;font-size:.9rem;}
.pr-option-check{width:22px;height:22px;border-radius:50%;background:#405189;display:flex;align-items:center;justify-content:center;color:#fff;opacity:0;transition:opacity .2s;font-size:.75rem;}
.pr-analysis-option.selected .pr-option-check{opacity:1;}
.badge.bg-primary-soft{background:rgba(64,81,137,.12);color:#405189;font-weight:700;padding:3px 8px;border-radius:5px;font-size:.72rem;}

/* ─── PRINT MODAL DATE & SUMMARY ─── */
.pr-date-picker{background:#f8f9fa;padding:18px;border-radius:8px;}
.pr-summary{background:#f8f9fa;padding:18px;border-radius:8px;}
.pr-summary-card{background:#fff;border-radius:8px;border:1px solid #dee2e6;overflow:hidden;}
.pr-summary-hdr{background:#f8f9fa;padding:11px 14px;border-bottom:1px solid #dee2e6;display:flex;align-items:center;gap:8px;}
.pr-summary-hdr i{color:#405189;font-size:1rem;}
.pr-summary-hdr h5{margin:0;font-size:.95rem;}
.pr-summary-body{padding:14px;}
.pr-summary-item{display:flex;margin-bottom:11px;padding-bottom:11px;border-bottom:1px dashed #e9ecef;}
.pr-summary-item:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none;}
.pr-summary-item span{width:120px;color:#6c757d;font-size:.85rem;flex-shrink:0;}
.pr-summary-item strong{flex:1;font-weight:500;}
</style>
