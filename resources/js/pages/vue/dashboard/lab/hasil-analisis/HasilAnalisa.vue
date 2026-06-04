<template>
    <div class="ha-root">
        <!-- ══════════ TOP BAR ══════════ -->
        <div class="ha-topbar">
            <div class="ha-topbar-left">
                <div class="ha-topbar-icon"><i class="ri-flask-line"></i></div>
                <div>
                    <span class="ha-topbar-title">Hasil Analisa Lab</span>
                    <span class="ha-topbar-sub">Kumpulan data hasil pengujian laboratorium</span>
                </div>
            </div>
            <div class="ha-topbar-right">
                <!-- Switch Mode -->
                <div class="ha-mode-switch">
                    <button class="ha-mode-btn" :class="{ 'ha-mode-btn--active': viewMode === 'per-prd' }" @click="setMode('per-prd')">
                        <i class="ri-archive-line me-1"></i>Per PO
                    </button>
                    <button class="ha-mode-btn" :class="{ 'ha-mode-btn--active': viewMode === 'per-analisa' }" @click="setMode('per-analisa')">
                        <i class="ri-flask-line me-1"></i>Per Analisa
                    </button>
                </div>
                <!-- Stats -->
                <div class="ha-stat-badge" v-if="pagination.totalData > 0">
                    <span class="ha-stat-num">{{ pagination.totalData }}</span>
                    <span class="ha-stat-lbl">Sampel</span>
                </div>
                <!-- Per Analisa: jenis aktif chip -->
                <div v-if="viewMode === 'per-analisa' && selectedJenis" class="ha-topbar-jenis">
                    <i :class="getAktivitasIcon(selectedJenis.Kode_Aktivitas_Lab)" class="me-1" style="color:#405189;"></i>
                    <span class="ha-topbar-jenis-name">{{ selectedJenis.Jenis_Analisa }}</span>
                </div>
                <span v-else-if="viewMode === 'per-analisa'" class="ha-topbar-hint">Pilih jenis analisa di panel kiri</span>
            </div>
        </div>

        <!-- ══════════ BODY ══════════ -->
        <div class="ha-body">
            <!-- ──────── LEFT PANEL ──────── -->
            <div class="ha-left" :class="{ 'ha-hidden-mobile': detailVisible && isMobile }">

                <!-- ── Mode: Per Analisa → jenis analisa selector (compact horizontal chips) ── -->
                <div v-if="viewMode === 'per-analisa'" class="ha-analisa-selector">
                    <div class="ha-analisa-selector-hdr">
                        <span class="ha-analisa-selector-title"><i class="ri-flask-line me-1"></i>Pilih Jenis Analisa</span>
                        <div class="ha-analisa-search-wrap">
                            <i class="ri-search-line ha-analisa-search-icon"></i>
                            <input type="text" class="ha-analisa-search" placeholder="Cari..." v-model="jenisSearch" />
                        </div>
                    </div>
                    <div v-if="loading.jenis" class="ha-analisa-loading"><span class="spinner-border spinner-border-sm text-muted me-1"></span><span class="text-muted small">Memuat...</span></div>
                    <div v-else class="ha-analisa-chips">
                        <button v-for="j in filteredJenisList" :key="j.Id_Jenis_Analisa"
                            class="ha-analisa-chip" :class="[selectedJenisId===j.Id_Jenis_Analisa?'ha-analisa-chip--active':'', 'ha-analisa-chip--'+getAktivitasKey(j.Kode_Aktivitas_Lab)]"
                            @click="selectJenis(j)" :title="j.Jenis_Analisa">
                            <i :class="getAktivitasIcon(j.Kode_Aktivitas_Lab)" style="font-size:.7rem;"></i>
                            <span>{{ j.Kode_Analisa }}</span>
                        </button>
                    </div>
                </div>

                <!-- ── Filter bar (shared) ── -->
                <div class="ha-filter-bar">
                    <div class="ha-search-wrap">
                        <i class="ri-search-line ha-search-icon"></i>
                        <input type="text" class="ha-search-input" :placeholder="viewMode==='per-prd'?'Cari No. Sampel, PO, Barang, Mesin...':'Cari No. PO, Batch, Mesin...'"
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
                        <select class="ha-select" v-model="filters.tipeProduksi" @change="fetchSamples()">
                            <option value="">Semua Tipe</option>
                            <option value="trial">Trial</option>
                            <option value="produksi">Produksi</option>
                        </select>
                        <button class="ha-btn-reset" @click="resetFilters" title="Reset"><i class="ri-filter-off-line"></i></button>
                    </div>
                </div>

                <!-- ── Per Analisa: require jenis selection ── -->
                <div v-if="viewMode === 'per-analisa' && !selectedJenisId" class="ha-left-empty">
                    <i class="ri-flask-line"></i>
                    <p>Pilih jenis analisa di atas</p>
                </div>

                <!-- ── Sample list ── -->
                <template v-else>
                    <div class="ha-list">
                        <div v-if="loading.list" class="p-3"><div v-for="i in 5" :key="i" class="ha-skeleton mb-2"></div></div>
                        <div v-else-if="sampleList.length === 0" class="ha-empty-list">
                            <i class="ri-inbox-2-line"></i>
                            <p>Tidak ada data sampel</p>
                            <button class="btn btn-sm btn-outline-primary mt-2" @click="resetFilters"><i class="ri-refresh-line me-1"></i>Reset</button>
                        </div>
                        <div v-else>
                            <button v-for="item in sampleList" :key="getItemKey(item)"
                                class="ha-item" :class="{ 'ha-item--active': isActive(item) }"
                                @click="selectSample(item)">
                                <div class="ha-item-accent"></div>
                                <div class="ha-item-body">
                                    <div class="ha-item-top">
                                        <span class="ha-item-title">{{ getNoSampel(item) }}</span>
                                        <div class="d-flex gap-1 flex-shrink-0">
                                            <!-- Per Analisa: show jenis analisa badge -->
                                            <span v-if="viewMode==='per-analisa' && selectedJenis" class="ha-item-analisa-badge" :class="'ha-akv--'+getAktivitasKey(selectedJenis.Kode_Aktivitas_Lab)">
                                                <i :class="getAktivitasIcon(selectedJenis.Kode_Aktivitas_Lab)" style="font-size:.6rem;"></i>
                                                {{ selectedJenis.Kode_Analisa }}
                                            </span>
                                            <!-- Per PRD: show total analisa badge -->
                                            <span v-if="viewMode==='per-prd'" class="ha-item-count-badge">
                                                {{ item.Total_Analisa }} analisa
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ha-item-sub" v-if="getNamaBarang(item)">{{ getNamaBarang(item) }}</div>
                                    <div class="ha-item-meta">
                                        <span class="ha-chip ha-chip--blue" v-if="getNoPoItem(item)">
                                            <i class="ri-file-list-3-line"></i>{{ getNoPoItem(item) }}
                                        </span>
                                        <span class="ha-chip" :class="getMultiFlag(item) === 'Y' ? 'ha-chip--blue' : 'ha-chip--gray'">
                                            <i class="ri-qr-code-line"></i>{{ getMultiFlag(item) === 'Y' ? 'Multi' : 'Single' }}
                                        </span>
                                        <span class="ha-chip" :class="getTipeProduksi(item).includes('Trial') ? 'ha-chip--orange' : 'ha-chip--green'">
                                            {{ getTipeProduksi(item).includes('Trial') ? 'Trial' : 'Produksi' }}
                                        </span>
                                        <span class="ha-chip ha-chip--gray" v-if="getNamaMesin(item)">
                                            <i class="ri-settings-3-line"></i>{{ getNamaMesin(item) }}
                                        </span>
                                    </div>
                                    <div class="ha-item-date" v-if="getTanggal(item)">
                                        <i class="ri-calendar-check-line me-1"></i>{{ formatDate(getTanggal(item)) }}
                                        <span v-if="getJam(item)" class="ms-1">{{ getJam(item).substring(0,5) }}</span>
                                    </div>
                                </div>
                                <i class="ri-arrow-right-s-line ha-item-arrow"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ── Pagination ── -->
                    <div class="ha-list-footer">
                        <span class="ha-page-info">
                            {{ sampleList.length > 0
                                ? ((pagination.page-1)*pagination.limit+1) + '–' + ((pagination.page-1)*pagination.limit+sampleList.length)
                                : 0 }}
                            dari {{ pagination.totalData }}
                        </span>
                        <div class="ha-page-btns" v-if="pagination.totalPage > 1">
                            <button class="ha-page-btn" :disabled="pagination.page===1" @click="changePage(1)" title="Pertama"><i class="ri-skip-back-line"></i></button>
                            <button class="ha-page-btn" :disabled="pagination.page===1" @click="changePage(pagination.page-1)"><i class="ri-arrow-left-s-line"></i></button>
                            <span class="ha-page-current">{{ pagination.page }}/{{ pagination.totalPage }}</span>
                            <button class="ha-page-btn" :disabled="pagination.page===pagination.totalPage" @click="changePage(pagination.page+1)"><i class="ri-arrow-right-s-line"></i></button>
                            <button class="ha-page-btn" :disabled="pagination.page===pagination.totalPage" @click="changePage(pagination.totalPage)" title="Terakhir"><i class="ri-skip-forward-line"></i></button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- ──────── RIGHT PANEL ──────── -->
            <div class="ha-right" :class="{ 'ha-hidden-mobile': !detailVisible && isMobile }">
                <div v-if="isMobile && detailVisible" class="ha-mobile-back">
                    <button class="btn btn-sm btn-soft-secondary" @click="detailVisible=false"><i class="ri-arrow-left-line me-1"></i>Daftar</button>
                </div>

                <!-- Empty state -->
                <div v-if="!selectedSample" class="ha-detail-empty">
                    <div class="ha-detail-empty-inner">
                        <div class="ha-empty-icon-wrap"><i class="ri-flask-line"></i></div>
                        <h6>Pilih sampel untuk melihat hasil analisa</h6>
                        <p v-if="viewMode==='per-analisa' && !selectedJenisId">Pilih jenis analisa dulu, lalu klik sampel.</p>
                        <p v-else>Klik sampel di daftar kiri.</p>
                    </div>
                </div>

                <template v-else>
                    <!-- ── HEADER ── -->
                    <div class="ha-detail-header">
                        <div class="ha-dh-main">
                            <div class="ha-dh-icon"><i class="ri-flask-line"></i></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="ha-dh-title">{{ getDetailNamaBarang() }}</div>
                                <div class="ha-dh-sampel">{{ getDetailNoSampel() }}</div>
                                <div class="ha-dh-badges">
                                    <span class="ha-badge ha-badge--blue" v-if="getDetailNoPo()"><i class="ri-receipt-line me-1"></i>{{ getDetailNoPo() }}</span>
                                    <span class="ha-badge ha-badge--gray" v-if="getDetailNoSplitPo()"><i class="ri-git-branch-line me-1"></i>{{ getDetailNoSplitPo() }}</span>
                                    <span class="ha-badge" :class="getMultiFlag(selectedSample)==='Y'?'ha-badge--blue':'ha-badge--gray'">
                                        <i class="ri-qr-code-line me-1"></i>{{ getMultiFlag(selectedSample)==='Y'?'Multi QR':'Single QR' }}
                                    </span>
                                    <span class="ha-badge" :class="getTipeProduksi(selectedSample).includes('Trial')?'ha-badge--orange':'ha-badge--success'">
                                        <i :class="getTipeProduksi(selectedSample).includes('Trial')?'ri-test-tube-line':'ri-industry-line'" class="me-1"></i>
                                        {{ getTipeProduksi(selectedSample) }}
                                    </span>
                                    <!-- Per Analisa: show jenis analisa badge in header -->
                                    <span v-if="viewMode==='per-analisa'&&selectedJenis" class="ha-badge" :class="'ha-badge--akv-'+getAktivitasKey(selectedJenis.Kode_Aktivitas_Lab)">
                                        <i :class="getAktivitasIcon(selectedJenis.Kode_Aktivitas_Lab)" class="me-1"></i>{{ selectedJenis.Jenis_Analisa }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- PLT banner -->
                        <div v-if="informasiData?.is_plt && informasiData?.plt_pembanding?.length" class="ha-plt-banner">
                            <div class="ha-plt-icon"><i class="ri-heart-pulse-line"></i></div>
                            <div>
                                <div class="ha-plt-title">Uji Palatabilitas — Produk Pembanding</div>
                                <div class="ha-plt-chips">
                                    <span v-for="(pb,i) in informasiData.plt_pembanding" :key="i" class="ha-plt-chip"><i class="ri-flask-line me-1"></i>{{ pb?.nama||pb }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Multi QR sub-sample pills (Per Analisa mode) -->
                        <div v-if="viewMode==='per-analisa' && getMultiFlag(selectedSample)==='Y'" class="ha-sub-bar">
                            <span class="ha-sub-label"><i class="ri-qr-code-line me-1"></i>Sub Sampel:</span>
                            <div v-if="loading.sub" class="ha-sub-loading"><span class="spinner-border spinner-border-sm text-primary me-1"></span><span class="text-muted small">Memuat...</span></div>
                            <div v-else class="ha-sub-pills">
                                <button v-for="sub in subSamples" :key="sub.No_Fak_Sub_Po"
                                    class="ha-sub-pill" :class="{'ha-sub-pill--active':selectedSub===sub.No_Fak_Sub_Po}"
                                    @click="selectSub(sub.No_Fak_Sub_Po)">
                                    <i class="ri-qr-code-line me-1"></i>{{ sub.No_Fak_Sub_Po }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── TABS ── -->
                    <div class="ha-tabs">
                        <button class="ha-tab" :class="{'ha-tab--active':activeTab==='analisa'}" @click="activeTab='analisa'">
                            <i class="ri-flask-line me-1"></i>Hasil Analisa
                        </button>
                        <button class="ha-tab" :class="{'ha-tab--active':activeTab==='timeline'}" @click="activeTab='timeline';loadTimeline()">
                            <i class="ri-timeline-view me-1"></i>Timeline
                            <span v-if="auditLog.length>0" class="ha-tab-count">{{ auditLog.length }}</span>
                        </button>
                    </div>

                    <!-- ── DETAIL BODY ── -->
                    <div class="ha-detail-body">

                        <!-- LOADING -->
                        <div v-if="loading.detail" class="ha-loading-state">
                            <div class="spinner-border text-primary"></div>
                            <p class="mt-3 text-muted small">Memuat data analisa...</p>
                        </div>

                        <!-- PER PRD: waiting for jenis analisa list load -->
                        <div v-else-if="viewMode==='per-prd' && activeTab==='analisa' && jenisPerPrd.length===0" class="ha-loading-state">
                            <i class="ri-inbox-2-line fs-1 text-muted"></i>
                            <p class="text-muted small mt-2">Tidak ada data hasil analisa untuk sampel ini</p>
                        </div>

                        <!-- PER ANALISA: waiting for sub-sample selection -->
                        <div v-else-if="viewMode==='per-analisa' && getMultiFlag(selectedSample)==='Y' && !selectedSub" class="ha-loading-state">
                            <i class="ri-qr-code-line fs-1 text-muted"></i>
                            <p class="text-muted small mt-2">Pilih sub sampel di atas</p>
                        </div>

                        <!-- ════ ANALISA TAB ════ -->
                        <template v-else-if="activeTab==='analisa'">

                            <!-- ═══ PER PRD: sections per group ═══ -->
                            <template v-if="viewMode==='per-prd'">
                                <div v-for="section in detailSections" :key="section.group" class="ha-section">
                                    <button class="ha-section-hdr" @click="toggleSection(section.group)">
                                        <div class="ha-section-hdr-l">
                                            <div class="ha-section-icon" :style="{background:section.bg}"><i :class="section.icon"></i></div>
                                            <div>
                                                <span class="ha-section-title">{{ section.label }}</span>
                                                <span class="ha-section-sub">{{ section.items.length }} jenis analisa</span>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-7 flex-shrink-0">
                                            <span v-if="section.failCount>0" class="ha-badge ha-badge--danger"><i class="ri-close-circle-line me-1"></i>{{ section.failCount }} TL</span>
                                            <span v-else class="ha-badge ha-badge--success"><i class="ri-checkbox-circle-line me-1"></i>Lolos</span>
                                            <i class="ha-chevron ri-arrow-up-s-line" :class="{'collapsed':!isSectionOpen(section.group)}"></i>
                                        </div>
                                    </button>
                                    <div v-show="isSectionOpen(section.group)" class="ha-section-body">
                                        <div v-for="analisa in section.items" :key="analisa.key" class="ha-analisa-panel">
                                            <button class="ha-analisa-hdr" :class="analisa.Flag_Layak==='T'?'ha-analisa-hdr--danger':'ha-analisa-hdr--success'" @click="toggleAnalisa(analisa)">
                                                <div class="ha-analisa-hdr-l">
                                                    <div class="ha-analisa-dot" :class="analisa.Flag_Layak==='T'?'dot--danger':'dot--success'"></div>
                                                    <div>
                                                        <span class="ha-analisa-hdr-title">{{ analisa.Jenis_Analisa }}</span>
                                                        <div class="ha-analisa-hdr-meta">
                                                            <span class="ha-chip ha-chip--gray" style="font-size:.62rem;">{{ analisa.Kode_Analisa }}</span>
                                                            <span v-if="analisa.is_plt&&analisa.Nama_Pembanding" class="ha-chip ha-chip--cyan" style="font-size:.62rem;"><i class="ri-flask-line"></i>{{ analisa.Nama_Pembanding }}</span>
                                                            <span v-if="analisa.Flag_Perhitungan==='Y'" class="ha-chip ha-chip--purple" style="font-size:.62rem;"><i class="ri-calculator-line"></i>Perhitungan</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                    <span class="ha-badge" :class="analisa.Flag_Layak==='T'?'ha-badge--danger':'ha-badge--success'"><i :class="analisa.Flag_Layak==='T'?'ri-close-circle-line':'ri-checkbox-circle-line'" class="me-1"></i>{{ analisa.Flag_Layak==='T'?'Tidak Lolos':'Lolos' }}</span>
                                                    <i class="ha-chevron ri-arrow-up-s-line" :class="{'collapsed':!isAnalisaOpen(analisa.key)}"></i>
                                                </div>
                                            </button>
                                            <div v-show="isAnalisaOpen(analisa.key)" class="ha-analisa-table-wrap">
                                                <div v-if="loadingTable[analisa.key]" class="ha-table-loading"><span class="spinner-border spinner-border-sm text-primary me-2"></span><span class="text-muted small">Memuat...</span></div>
                                                <div v-else-if="!tableByKey[analisa.key]||tableByKey[analisa.key].length===0" class="text-center py-3 text-muted small"><i class="ri-inbox-2-line me-1"></i>Tidak ada data</div>
                                                <div v-else>
                                                    <div v-if="templateByKey[analisa.key]?.is_sop" class="ha-sop-bar mx-3 mt-2">
                                                        <i class="ri-bar-chart-line me-1"></i><span class="ha-sop-label">Range SOP:</span>
                                                        <span class="ha-sop-val">{{ templateByKey[analisa.key].Range_Awal }} — {{ templateByKey[analisa.key].Range_Akhir }}</span>
                                                    </div>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered align-middle mb-0 ha-data-table">
                                                            <thead>
                                                                <tr>
                                                                    <th class="text-center ha-th-no">#</th>
                                                                    <th v-if="analisa.is_plt" class="ha-th-plt"><i class="ri-flask-line me-1"></i>Pembanding</th>
                                                                    <th>No Transaksi</th><th>No Sampel</th><th>No PO</th><th>No Split Po</th>
                                                                    <th v-if="getMultiFlag(selectedSample)==='Y'">No Sub Sampel</th>
                                                                    <th>Tanggal</th>
                                                                    <template v-if="hasTemplateKey(analisa.key)">
                                                                        <th v-for="param in getTemplateKey(analisa.key).parameter" :key="param.id_qc">
                                                                            {{ param.nama_parameter }}<small v-if="param.satuan" class="d-block fw-normal opacity-75" style="font-size:.7em;">{{ param.satuan }}</small>
                                                                        </th>
                                                                        <th v-for="f in getTemplateKey(analisa.key).formula" :key="f.id||f.nama_kolom" class="ha-th-formula">{{ f.nama_kolom }}</th>
                                                                    </template>
                                                                    <th v-else>Hasil</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr v-for="(row,ri) in tableByKey[analisa.key]" :key="ri" :class="getRowClass(row)">
                                                                    <td class="text-center fw-semibold ha-td-no">{{ ri+1 }}</td>
                                                                    <td v-if="analisa.is_plt" class="ha-td-plt"><span class="ha-pembanding">{{ row.Nama_Pembanding||'—' }}</span></td>
                                                                    <td class="ha-td-mono">{{ row.No_Faktur||'-' }}</td>
                                                                    <td class="ha-td-mono">{{ row.No_Po_Sampel||'-' }}</td>
                                                                    <td>{{ row.No_Po||'-' }}</td><td>{{ row.No_Split_Po||'-' }}</td>
                                                                    <td v-if="getMultiFlag(selectedSample)==='Y'">{{ row.No_Fak_Sub_Po||'-' }}</td>
                                                                    <td>{{ formatDate(row.Tanggal) }}</td>
                                                                    <template v-if="hasTemplateKey(analisa.key)">
                                                                        <td v-for="(val,pi) in (row.parameters||[])" :key="pi">{{ val }}</td>
                                                                        <template v-if="getTemplateKey(analisa.key).formula?.length>0">
                                                                            <td v-for="(res,fi) in (row.results||[])" :key="fi" class="ha-td-formula fw-semibold">{{ res?.value??'-' }}</td>
                                                                        </template>
                                                                    </template>
                                                                    <td v-else class="fw-semibold">{{ row.Hasil_Akhir_Analisa??'-' }}</td>
                                                                </tr>
                                                                <tr v-if="(analisa.Flag_Perhitungan==='Y'||tableByKey[analisa.key][0]?.Flag_Perhitungan==='Y') && getTemplateKey(analisa.key).formula?.length>0 && (avgByKey[analisa.key]||[]).length>0" class="ha-row--rata">
                                                                    <td :colspan="getBaseColCount(analisa)" class="text-end pe-3 fw-bold fst-italic text-secondary">Rata-Rata</td>
                                                                    <td v-for="(avg,ai) in (avgByKey[analisa.key]||[])" :key="ai" class="ha-td-formula fw-bold">{{ avg }}</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- ═══ PER ANALISA: single table ═══ -->
                            <template v-else>
                                <div v-if="tableRows.length===0" class="ha-loading-state">
                                    <i class="ri-inbox-2-line fs-1 text-muted"></i>
                                    <p class="text-muted small mt-2">Tidak ada data hasil analisa</p>
                                </div>
                                <div v-else>
                                    <div v-if="informasiData?.is_sop" class="ha-sop-bar">
                                        <i class="ri-bar-chart-line me-1 text-primary"></i>
                                        <span class="ha-sop-label">Range SOP:</span>
                                        <span class="ha-sop-val">{{ informasiData.Range_Awal }} — {{ informasiData.Range_Akhir }}</span>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0 ha-data-table">
                                            <thead>
                                                <tr>
                                                    <th class="text-center ha-th-no">#</th>
                                                    <th v-if="informasiData?.is_plt" class="ha-th-plt"><i class="ri-flask-line me-1"></i>Pembanding</th>
                                                    <th>No Transaksi</th><th>No Sampel</th><th>No PO</th><th>No Split Po</th>
                                                    <th v-if="getMultiFlag(selectedSample)==='Y'">No Sub Sampel</th>
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
                                                    <td v-if="getMultiFlag(selectedSample)==='Y'">{{ row.No_Fak_Sub_Po||'-' }}</td>
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
                                                    <td :colspan="getBaseColCountSingle()" class="text-end pe-3 fw-bold fst-italic text-secondary">Rata-Rata</td>
                                                    <td v-for="(avg,ai) in formulaAverages" :key="ai" class="ha-td-formula fw-bold">{{ avg }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </template>

                        <!-- ════ TIMELINE TAB ════ -->
                        <template v-else-if="activeTab==='timeline'">
                            <div v-if="loading.timeline" class="ha-loading-state"><div class="spinner-border spinner-border-sm text-primary"></div><p class="text-muted small mt-2">Memuat...</p></div>
                            <div v-else-if="auditLog.length===0" class="ha-loading-state"><i class="ri-history-line fs-1 text-muted"></i><p class="text-muted small mt-2">Belum ada riwayat</p></div>
                            <div v-else class="ha-vtl">
                                <div class="ha-vtl-hdr"><i class="ri-history-line me-2"></i>Riwayat Proses Sampel</div>
                                <div class="ha-vtl-steps">
                                    <div v-for="(log,li) in auditLog" :key="li" class="ha-vtl-step" :class="getStepClass(log)">
                                        <div class="ha-vtl-indicator">
                                            <div class="ha-vtl-dot"><i :class="getStepIcon(log)"></i></div>
                                            <div v-if="li<auditLog.length-1" class="ha-vtl-line"></div>
                                        </div>
                                        <div class="ha-vtl-body">
                                            <div class="ha-vtl-row1">
                                                <span class="ha-vtl-badge" :class="getStepBadgeClass(log)">{{ formatAksi(log.Jenis_Aksi) }}</span>
                                                <span v-if="log.Sub_Aksi" class="ha-vtl-sub" :class="log.Sub_Aksi==='TOLAK'?'text-danger':'text-success'">{{ log.Sub_Aksi }}</span>
                                            </div>
                                            <div class="ha-vtl-meta">
                                                <span><i class="ri-user-3-line me-1"></i>{{ log.Nama_User||log.Id_User }}</span>
                                                <span><i class="ri-time-line me-1"></i>{{ formatDate(log.Tanggal) }}<template v-if="log.Jam"> · {{ log.Jam.substring(0,5) }}</template></span>
                                            </div>
                                            <div v-if="log.details&&log.details.length" class="ha-vtl-details">
                                                <div class="ha-vtl-details-hdr"><i class="ri-microscope-line me-1"></i>{{ log.details.length }} analisa</div>
                                                <div v-for="d in log.details" :key="d.Id_Jenis_Analisa" class="ha-vtl-detail-row">
                                                    <i class="ri-arrow-right-s-line text-muted"></i>{{ d.Nama_Jenis_Analisa }}<span v-if="d.Tanggal" class="text-muted ms-1" style="font-size:.65rem;"> · {{ formatDate(d.Tanggal) }}</span>
                                                </div>
                                            </div>
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
</template>

<script>
import axios from "axios";
export default {
    props: {
        selected_id:          { type: [String, Number], default: null },
        initial_no_po_sampel: { type: String, default: null },
        initial_flag_multi:   { type: String, default: null },
        initial_no_sub:       { type: String, default: null },
    },
    data() {
        return {
            // View mode
            viewMode: 'per-prd', // 'per-prd' | 'per-analisa'

            // Jenis Analisa (for Per Analisa mode)
            jenisAnalisaList: [],
            selectedJenisId: "",
            selectedJenis: null,
            jenisSearch: "",
            loading: { jenis: false, list: false, detail: false, sub: false, timeline: false },

            // Sample list
            sampleList: [],
            selectedSample: null,
            pagination: { page: 1, limit: 20, totalPage: 1, totalData: 0 },
            searchQuery: "",
            searchTimeout: null,
            filters: { startDate: "", endDate: "", qrcode: "", status: "terima", tipeProduksi: "" },

            // Sub-samples (multi QR, Per Analisa mode)
            subSamples: [],
            selectedSub: null,

            // Per PRD: jenis analisa list per sample + per-analisa table data
            jenisPerPrd: [],
            openSections: [],
            openAnalisas: [],
            loadingTable: {},
            templateByKey: {},
            tableByKey: {},
            avgByKey: {},

            // Per Analisa: single table
            template: { parameter: [], formula: [] },
            tableRows: [],
            formulaAverages: [],
            informasiData: null,

            // UI
            activeTab: "analisa",
            auditLog: [],
            isMobile: false,
            detailVisible: false,
        };
    },
    computed: {
        hasTemplate() { return (this.template.parameter?.length||0)>0||(this.template.formula?.length||0)>0; },
        filteredJenisList() {
            if (!this.jenisSearch.trim()) return this.jenisAnalisaList;
            const q = this.jenisSearch.toLowerCase();
            return this.jenisAnalisaList.filter(j => j.Jenis_Analisa?.toLowerCase().includes(q) || j.Kode_Analisa?.toLowerCase().includes(q));
        },
        detailSections() {
            const gDef = {
                ANL:  { label:'Analisa Lab',  icon:'ri-flask-line',       bg:'linear-gradient(135deg,#405189,#2e3a64)' },
                PLT:  { label:'Palatabilitas', icon:'ri-heart-pulse-line', bg:'linear-gradient(135deg,#0ab39c,#0891b2)' },
                LCKV: { label:'Look View',     icon:'ri-eye-line',          bg:'linear-gradient(135deg,#7c3aed,#5b21b6)' },
            };
            const order = { ANL:1, PLT:2, LCKV:3 };
            const grouped = {};
            this.jenisPerPrd.forEach(item => {
                const g = item.Kode_Aktivitas_Lab || 'ANL';
                if (!grouped[g]) { const d = gDef[g]||{label:g,icon:'ri-flask-line',bg:'linear-gradient(135deg,#405189,#2e3a64)'}; grouped[g]={...d,group:g,items:[],failCount:0}; }
                const key = `${item.Id_Jenis_Analisa}_${item.Nama_Pembanding||''}`;
                if (!grouped[g].items.find(i=>i.key===key)) grouped[g].items.push({...item,key});
                if (item.Flag_Layak==='T') grouped[g].failCount++;
            });
            return Object.values(grouped).sort((a,b)=>(order[a.group]||99)-(order[b.group]||99));
        },
    },
    methods: {
        // ─── Mode switching ──────────────────────────────────────────
        setMode(mode) {
            if (this.viewMode === mode) return;
            this.viewMode = mode;
            this.selectedSample = null;
            this.sampleList = [];
            this.pagination = { page: 1, limit: 20, totalPage: 1, totalData: 0 };
            this.searchQuery = "";
            this.filters = { startDate:"", endDate:"", qrcode:"", status:"terima", tipeProduksi:"" };
            this.resetDetail();
            if (mode === 'per-analisa') {
                if (this.jenisAnalisaList.length === 0) this.fetchJenisAnalisa();
                this.selectedJenisId = "";
                this.selectedJenis = null;
            } else {
                this.fetchSamples();
            }
        },

        // ─── Jenis Analisa (Per Analisa mode) ───────────────────────
        async fetchJenisAnalisa() {
            this.loading.jenis = true;
            try { const res = await axios.get("/api/v1/lab/hasil-analisa/uji-sampel"); this.jenisAnalisaList = res.data?.result||[]; }
            catch { this.jenisAnalisaList=[]; } finally { this.loading.jenis=false; }
        },
        async selectJenis(jenis) {
            if (this.selectedJenisId === jenis.Id_Jenis_Analisa) return;
            this.selectedJenisId = jenis.Id_Jenis_Analisa; this.selectedJenis = jenis;
            this.selectedSample = null; this.sampleList = [];
            this.pagination = { page:1, limit:20, totalPage:1, totalData:0 };
            this.searchQuery = ""; this.resetDetail();
            await this.fetchSamples();
        },

        // ─── Aktivitas helpers ───────────────────────────────────────
        getAktivitasIcon(k) { return {ANL:'ri-flask-line',PLT:'ri-heart-pulse-line',LCKV:'ri-eye-line'}[k]||'ri-flask-line'; },
        getAktivitasKey(k)  { return {ANL:'anl',PLT:'plt',LCKV:'lckv'}[k]||'anl'; },

        // ─── Item field extractors (handle both API formats) ─────────
        getNoSampel(item)    { return item.No_Po_Sampel||'-'; },
        getNamaBarang(item)  { return item.Nama_Barang||item.nama_barang||''; },
        getNoPoItem(item)    { return item.No_Po||item.no_po||''; },
        getMultiFlag(item)   { return item.Flag_Multi_QrCode||item.flag_multi||''; },
        getTipeProduksi(item){ return item.Tipe_Produksi||item.tipe_produksi||''; },
        getNamaMesin(item)   { return item.Nama_Mesin||item.nama_mesin||''; },
        getTanggal(item)     { return item.Tanggal_Uji||item.tanggal_pengujian||''; },
        getJam(item)         { return item.Jam_Uji||item.jam_pengujian||''; },
        getItemKey(item)     { return item.No_Po_Sampel; },
        getDetailNamaBarang(){ return this.selectedSample ? this.getNamaBarang(this.selectedSample)||this.getNoSampel(this.selectedSample) : ''; },
        getDetailNoSampel()  { return this.selectedSample ? this.getNoSampel(this.selectedSample) : ''; },
        getDetailNoPo()      { return this.selectedSample ? this.getNoPoItem(this.selectedSample) : ''; },
        getDetailNoSplitPo() { return this.selectedSample?.No_Split_Po||this.selectedSample?.no_split_po||''; },

        // ─── Sample list ─────────────────────────────────────────────
        debounceFetch() { clearTimeout(this.searchTimeout); this.searchTimeout=setTimeout(()=>{ this.pagination.page=1; this.fetchSamples(); },400); },
        async fetchSamples() {
            if (this.viewMode==='per-analisa' && !this.selectedJenisId) return;
            this.loading.list = true;
            try {
                const params = { page:this.pagination.page, limit:this.pagination.limit, q:this.searchQuery };
                if (this.filters.qrcode)       params.qrcode       = this.filters.qrcode;
                if (this.filters.status)       params.status        = this.filters.status;
                if (this.filters.tipeProduksi) params.tipe_produksi = this.filters.tipeProduksi;
                if (this.filters.startDate && this.filters.endDate) { params.tanggal_mulai=this.filters.startDate; params.tanggal_selesai=this.filters.endDate; }

                let res;
                if (this.viewMode === 'per-prd') {
                    res = await axios.get('/api/v1/lab/hasil-analisa/per-produk/semua', { params });
                    if (res.data?.result) {
                        this.sampleList = res.data.result.data || [];
                        const pg = res.data.result.pagination||{};
                        this.pagination = { page:pg.page||1, limit:pg.limit||20, totalPage:pg.totalPage||1, totalData:pg.totalData||this.sampleList.length };
                    } else { this.sampleList=[]; }
                } else {
                    res = await axios.get(`/api/v1/lab/hasil-analisa/${this.selectedJenisId}`, { params });
                    if (res.data?.result) {
                        const data = res.data.result.data_sampel||{};
                        this.sampleList = Object.entries(data).map(([no_po_sampel,item])=>({...item,No_Po_Sampel:no_po_sampel}));
                        const pg = res.data.result.pagination||{};
                        this.pagination = { page:pg.page||1, limit:pg.limit||20, totalPage:pg.totalPage||1, totalData:pg.totalData||this.sampleList.length };
                    } else { this.sampleList=[]; }
                }
            } catch { this.sampleList=[]; } finally { this.loading.list=false; }
        },
        changePage(p) { if(p>=1&&p<=this.pagination.totalPage){this.pagination.page=p;this.fetchSamples();} },
        resetFilters() { this.searchQuery=""; this.filters={startDate:"",endDate:"",qrcode:"",status:"terima",tipeProduksi:""}; this.pagination.page=1; this.fetchSamples(); },

        // ─── Sample selection ─────────────────────────────────────────
        async selectSample(item) {
            this.selectedSample = item; this.detailVisible = true; this.activeTab = "analisa"; this.resetDetail();
            if (this.viewMode === 'per-prd') {
                await this.loadJenisPerPrd(item.No_Po_Sampel);
            } else {
                if (this.getMultiFlag(item)==='Y') await this.fetchSubSamples(item.No_Po_Sampel);
                else await this.fetchDetail(item.No_Po_Sampel, null);
            }
        },

        // ─── Per PRD: load all jenis analisa for sample ───────────────
        async loadJenisPerPrd(noSampel) {
            this.loading.detail = true;
            try {
                const res = await axios.get(`/api/v1/lab/hasil-analisa/per-produk/detail-jenis/${noSampel}`);
                this.jenisPerPrd = res.data?.result || [];
                // Open all sections and load all tables immediately
                const allSections = this.detailSections.map(s=>s.group);
                this.openSections = [...allSections];
                const allItems = this.detailSections.flatMap(s=>s.items);
                this.openAnalisas = allItems.map(a=>a.key);
                // Load tables in parallel
                allItems.forEach(a => this.fetchAnalisaTableByKey(a));
            } catch { this.jenisPerPrd=[]; } finally { this.loading.detail=false; }
        },

        isSectionOpen(group)  { return this.openSections.includes(group); },
        isAnalisaOpen(key)    { return this.openAnalisas.includes(key); },
        toggleSection(group)  { const i=this.openSections.indexOf(group); if(i>-1)this.openSections.splice(i,1); else this.openSections.push(group); },
        toggleAnalisa(analisa){ const key=analisa.key; const i=this.openAnalisas.indexOf(key); if(i>-1){this.openAnalisas.splice(i,1);return;} this.openAnalisas.push(key); if(!this.tableByKey[key]&&!this.loadingTable[key])this.fetchAnalisaTableByKey(analisa); },

        async fetchAnalisaTableByKey(analisa) {
            const key = analisa.key;
            const idJA = analisa.Id_Jenis_Analisa;
            const noSampel = this.selectedSample.No_Po_Sampel;
            this.loadingTable = { ...this.loadingTable, [key]:true };
            try {
                const [dataRes, templateRes] = await Promise.all([
                    axios.get(`/api/v2/lab/hasil-analisa/no-multi/${idJA}/${noSampel}`).catch(()=>null),
                    axios.get(`/fetch/lab/lama/${idJA}/parameter-perhitungan-old`).catch(()=>null),
                ]);
                const tmpl = templateRes?.data?.result || { parameter:[], formula:[] };
                const sampel = dataRes?.data?.result?.sampel || [];
                const info = dataRes?.data?.result?.informasi || null;
                const { data, formulaAverages } = this.processItems(sampel, tmpl);
                this.templateByKey = { ...this.templateByKey, [key]: { ...tmpl, is_sop:info?.is_sop, Range_Awal:info?.Range_Awal, Range_Akhir:info?.Range_Akhir } };
                this.tableByKey = { ...this.tableByKey, [key]: data };
                this.avgByKey = { ...this.avgByKey, [key]: formulaAverages };
            } catch { this.tableByKey={...this.tableByKey,[key]:[]}; }
            finally { this.loadingTable={...this.loadingTable,[key]:false}; }
        },

        hasTemplateKey(key) { const t=this.templateByKey[key]; return t&&((t.parameter?.length||0)>0||(t.formula?.length||0)>0); },
        getTemplateKey(key) { return this.templateByKey[key]||{parameter:[],formula:[]}; },
        getBaseColCount(analisa) {
            let c=6; if(analisa.is_plt)c++; if(this.getMultiFlag(this.selectedSample)==='Y')c++;
            return c+(this.getTemplateKey(analisa.key).parameter?.length||0);
        },

        // ─── Per Analisa: sub-samples ─────────────────────────────────
        async fetchSubSamples(noSampel) {
            this.loading.sub=true; this.subSamples=[]; this.selectedSub=null;
            try { const res=await axios.get(`/api/v1/lab/hasil-analisa/sub/${this.selectedJenisId}/${noSampel}`); this.subSamples=res.data?.result||[]; if(this.subSamples.length>0)await this.selectSub(this.subSamples[0].No_Fak_Sub_Po); }
            catch { this.subSamples=[]; } finally { this.loading.sub=false; }
        },
        async selectSub(noFakSub) { this.selectedSub=noFakSub; await this.fetchDetail(this.selectedSample.No_Po_Sampel, noFakSub); },

        // ─── Per Analisa: single detail table ────────────────────────
        async fetchDetail(noSampel, noSub) {
            if(!this.selectedJenisId)return;
            this.loading.detail=true; this.tableRows=[]; this.formulaAverages=[]; this.informasiData=null;
            try {
                const isSingle=!noSub;
                const dataUrl=isSingle?`/api/v2/lab/hasil-analisa/no-multi/${this.selectedJenisId}/${noSampel}`:`/api/v2/lab/hasil-analisa/multi/${this.selectedJenisId}/${noSampel}/Y/${noSub}`;
                const [dataRes,tmplRes]=await Promise.all([axios.get(dataUrl).catch(()=>null),axios.get(`/fetch/lab/lama/${this.selectedJenisId}/parameter-perhitungan-old`).catch(()=>null)]);
                this.template=tmplRes?.data?.result||{parameter:[],formula:[]};
                const result=dataRes?.data?.result||{};
                const sampel=result.sampel||[];
                this.informasiData=result.informasi||null;
                if(sampel.length>0&&this.informasiData){const f=sampel[0];if(!this.informasiData.Flag_Perhitungan)this.informasiData.Flag_Perhitungan=f.Flag_Perhitungan;if(this.informasiData.is_sop===undefined)this.informasiData.is_sop=f.is_sop;if(this.informasiData.Range_Awal===undefined)this.informasiData.Range_Awal=f.Range_Awal;if(this.informasiData.Range_Akhir===undefined)this.informasiData.Range_Akhir=f.Range_Akhir;}
                const {data,formulaAverages}=this.processItems(sampel,this.template);
                this.tableRows=data; this.formulaAverages=formulaAverages;
            } catch { this.tableRows=[]; } finally { this.loading.detail=false; }
        },
        getBaseColCountSingle() {
            let c=6; if(this.informasiData?.is_plt)c++; if(this.getMultiFlag(this.selectedSample)==='Y')c++;
            return c+(this.template.parameter?.length||0);
        },

        // ─── processItems ─────────────────────────────────────────────
        processItems(items, template) {
            if (!Array.isArray(items)||items.length===0) return{data:[],formulaAverages:[]};
            const tplP=template?.parameter?.length||0; const tplF=template?.formula?.length||0;
            if(!(tplP>0||tplF>0)){return{data:items.map(item=>({No_Faktur:item.No_Faktur||'-',No_Po_Sampel:item.No_Po_Sampel||'-',No_Fak_Sub_Po:item.No_Fak_Sub_Po||'-',No_Po:item.No_Po||'-',No_Split_Po:item.No_Split_Po||'-',Tanggal:item.Tanggal_Pengujian||'-',Nama_Pembanding:item.Nama_Pembanding||null,Hasil_Akhir_Analisa:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak||null,Flag_Perhitungan:item.Flag_Perhitungan,parameters:[],results:[]})),formulaAverages:[]};}
            const grouped=items.reduce((acc,item)=>{const k=item.No_Faktur;if(!acc[k])acc[k]=[];acc[k].push(item);return acc;},{});
            const processedData=Object.values(grouped).map(group=>{
                const first=group[0]; const dp=Array.isArray(first.parameter)?first.parameter:[];
                let pR,fR;
                if(dp.length>0){pR=dp.map(p=>this.formatHasil(p.Hasil_Analisa));fR=tplF>0?group.map(item=>({value:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak,pembulatan:item.Pembulatan??4})):[];}
                else if(group.length>1&&tplP>0&&tplF===0){pR=group.map(item=>this.formatHasil(item.Hasil_Akhir_Analisa));fR=[];}
                else{pR=[];fR=tplF>0?group.map(item=>({value:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak,pembulatan:item.Pembulatan??4})):[];}
                const wL=group.some(i=>i.Flag_Layak==='T')?'T':group.some(i=>i.Flag_Layak==='Y')?'Y':null;
                return{No_Faktur:first.No_Faktur||'-',No_Po_Sampel:first.No_Po_Sampel||'-',No_Fak_Sub_Po:first.No_Fak_Sub_Po||'-',No_Po:first.No_Po||'-',No_Split_Po:first.No_Split_Po||'-',Tanggal:first.Tanggal_Pengujian||'-',Nama_Pembanding:first.Nama_Pembanding||null,Hasil_Akhir_Analisa:this.formatHasil(first.Hasil_Akhir_Analisa),Flag_Layak:wL,Flag_Perhitungan:first.Flag_Perhitungan,Range_Awal:first.Range_Awal,Range_Akhir:first.Range_Akhir,is_sop:first.is_sop,parameters:pR,results:fR};
            });
            const fa=[];for(let i=0;i<tplF;i++){let t=0,c=0,d=4;processedData.forEach(row=>{const r=row.results[i];if(r&&r.value!=='-'){const v=parseFloat(r.value);if(!isNaN(v)){t+=v;c++;if(r.pembulatan)d=parseInt(r.pembulatan,10);}}});fa.push(c>0?(t/c).toFixed(d):'-');}
            return{data:processedData,formulaAverages:fa};
        },
        formatHasil(val){if(val===null||val===undefined)return'-';const s=String(val).trim();if(!s||s==='null')return'-';if(/^-?\d+\.0+$/.test(s))return String(Math.trunc(parseFloat(s)));return s;},
        getRowClass(row){if(row.Flag_Layak==='T')return'ha-row--ng';if(row.Flag_Layak==='Y')return'ha-row--ok';if(row.is_sop&&row.Range_Awal!==null&&row.Range_Akhir!==null){const v=parseFloat(row.Hasil_Akhir_Analisa);if(!isNaN(v))return v>=row.Range_Awal&&v<=row.Range_Akhir?'ha-row--ok':'ha-row--ng';}return'';},

        // ─── Timeline ──────────────────────────────────────────────────
        async loadTimeline(){if(this.auditLog.length>0||!this.selectedSample)return;this.loading.timeline=true;try{const res=await axios.get(`/api/v1/log-aksi/by-sampel/${this.selectedSample.No_Po_Sampel}`);this.auditLog=res.data?.result||[];}catch{this.auditLog=[];}finally{this.loading.timeline=false;}},

        // ─── Helpers ───────────────────────────────────────────────────
        resetDetail(){this.jenisPerPrd=[];this.openSections=[];this.openAnalisas=[];this.loadingTable={};this.templateByKey={};this.tableByKey={};this.avgByKey={};this.subSamples=[];this.selectedSub=null;this.tableRows=[];this.formulaAverages=[];this.informasiData=null;this.template={parameter:[],formula:[]};this.auditLog=[];this.activeTab="analisa";},
        isActive(item){return this.selectedSample?.No_Po_Sampel===item.No_Po_Sampel;},
        formatDate(d){if(!d)return'-';try{return new Date(d).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'});}catch{return d;}},
        formatAksi(aksi){const m={INPUT_ANALYZER:'Input Analyzer',VALIDASI_PRODUKSI:'Validasi Produksi',VALIDASI_TRIAL_PRODUKSI:'Validasi Trial',FINALISASI_PRODUKSI:'Finalisasi Produksi',FINALISASI_TRIAL_PRODUKSI:'Finalisasi Trial',VALIDASI_FORMULATOR:'Validasi Formulator',PRAFINALISASI_FORMULATOR:'Pra-Finalisasi',FINALISASI_FORMULATOR:'Finalisasi Formulator'};return m[aksi]||aksi;},
        getStepClass(log){if(log.Sub_Aksi==='TOLAK')return'vtl-rejected';if(log.Jenis_Aksi?.includes('FINALISASI'))return'vtl-final';return'vtl-done';},
        getStepIcon(log){if(log.Sub_Aksi==='TOLAK')return'ri-close-line';if(log.Jenis_Aksi==='INPUT_ANALYZER')return'ri-test-tube-line';if(log.Jenis_Aksi?.includes('FINALISASI'))return'ri-git-commit-line';return'ri-check-line';},
        getStepBadgeClass(log){if(log.Sub_Aksi==='TOLAK')return'vtl-badge--danger';if(log.Jenis_Aksi==='INPUT_ANALYZER')return'vtl-badge--info';if(log.Jenis_Aksi?.includes('FINALISASI'))return'vtl-badge--primary';return'vtl-badge--success';},
        checkMobile(){this.isMobile=window.innerWidth<768;},
    },
    async mounted() {
        this.checkMobile();
        window.addEventListener('resize', this.checkMobile);
        // Pre-load jenis analisa list in background (for Per Analisa mode switch)
        this.fetchJenisAnalisa();
        // Default: Per PRD → langsung load semua sampel
        if (!this.selected_id) {
            await this.fetchSamples();
        } else {
            // Deep-link: switch to Per Analisa mode and select the jenis analisa
            this.viewMode = 'per-analisa';
            await this.fetchJenisAnalisa();
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
    },
    beforeUnmount() { window.removeEventListener('resize', this.checkMobile); },
};
</script>

<style scoped>
/* ─── ROOT ─── */
.ha-root{display:flex;flex-direction:column;height:100vh;overflow:hidden;background:#f0f2f5;font-family:'Segoe UI',system-ui,sans-serif;}

/* ─── TOP BAR ─── */
.ha-topbar{display:flex;align-items:center;justify-content:space-between;padding:0 20px;height:52px;background:#fff;border-bottom:1px solid #e2e8f0;flex-shrink:0;gap:12px;box-shadow:0 1px 2px rgba(0,0,0,.05);}
.ha-topbar-left{display:flex;align-items:center;gap:10px;}
.ha-topbar-icon{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#405189,#2e3a64);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;}
.ha-topbar-title{font-weight:700;font-size:.88rem;color:#0f172a;display:block;line-height:1.2;}
.ha-topbar-sub{font-size:.66rem;color:#94a3b8;display:block;}
.ha-topbar-right{display:flex;align-items:center;gap:10px;}

/* ── Mode switch ── */
.ha-mode-switch{display:flex;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;background:#f8fafc;}
.ha-mode-btn{padding:5px 14px;border:none;background:transparent;font-size:.76rem;font-weight:500;color:#64748b;cursor:pointer;transition:.15s;display:flex;align-items:center;white-space:nowrap;}
.ha-mode-btn:hover{background:#f1f5f9;}
.ha-mode-btn--active{background:#405189;color:#fff;font-weight:600;}

.ha-stat-badge{display:flex;flex-direction:column;align-items:center;background:#eef2ff;border:1px solid #c7d2fe;border-radius:7px;padding:3px 9px;}
.ha-stat-num{font-weight:700;font-size:.95rem;color:#405189;line-height:1;}
.ha-stat-lbl{font-size:.56rem;color:#818cf8;text-transform:uppercase;letter-spacing:.4px;}
.ha-topbar-jenis{display:flex;align-items:center;gap:5px;}
.ha-topbar-jenis-name{font-size:.78rem;font-weight:600;color:#374151;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ha-topbar-hint{font-size:.74rem;color:#94a3b8;font-style:italic;}

/* ─── BODY ─── */
.ha-body{display:flex;flex:1;overflow:hidden;}

/* ─── LEFT PANEL ─── */
.ha-left{width:380px;min-width:310px;display:flex;flex-direction:column;border-right:1px solid #e2e8f0;background:#fff;overflow:hidden;}

/* ── Per Analisa: jenis analisa selector (compact horizontal chips) ── */
.ha-analisa-selector{flex-shrink:0;padding:8px 12px;border-bottom:1px solid #f1f5f9;background:#fafbff;}
.ha-analisa-selector-hdr{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;}
.ha-analisa-selector-title{font-size:.7rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;}
.ha-analisa-search-wrap{position:relative;flex:1;max-width:160px;}
.ha-analisa-search-icon{position:absolute;left:6px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.72rem;pointer-events:none;}
.ha-analisa-search{width:100%;padding:4px 8px 4px 22px;border:1px solid #e2e8f0;border-radius:5px;font-size:.73rem;outline:none;background:#fff;}
.ha-analisa-search:focus{border-color:#818cf8;}
.ha-analisa-loading{display:flex;align-items:center;padding:4px;}
.ha-analisa-chips{display:flex;flex-wrap:wrap;gap:4px;max-height:108px;overflow-y:auto;}
.ha-analisa-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:5px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;font-size:.73rem;font-weight:600;color:#475569;transition:.12s;white-space:nowrap;}
.ha-analisa-chip:hover{border-color:#818cf8;color:#405189;background:#eef2ff;}
.ha-analisa-chip--active,.ha-analisa-chip--anl.ha-analisa-chip--active{background:#405189;color:#fff;border-color:#405189;}
.ha-analisa-chip--plt.ha-analisa-chip--active{background:#0891b2;color:#fff;border-color:#0891b2;}
.ha-analisa-chip--lckv.ha-analisa-chip--active{background:#7c3aed;color:#fff;border-color:#7c3aed;}

/* ── Filter bar ── */
.ha-filter-bar{padding:8px 12px;border-bottom:1px solid #f1f5f9;flex-shrink:0;}
.ha-search-wrap{position:relative;margin-bottom:6px;}
.ha-search-icon{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.8rem;}
.ha-search-input{width:100%;padding:6px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:.78rem;outline:none;background:#f8fafc;}
.ha-search-input:focus{border-color:#818cf8;box-shadow:0 0 0 2px rgba(129,140,248,.1);background:#fff;}
.ha-search-x{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:none;background:none;color:#94a3b8;cursor:pointer;font-size:.78rem;padding:0;}
.ha-filter-row{display:flex;gap:5px;align-items:center;margin-bottom:4px;}
.ha-filter-row--inline{flex-wrap:wrap;}
.ha-date-input{flex:1;min-width:88px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:5px;font-size:.72rem;}
.ha-sep{color:#94a3b8;font-size:.78rem;flex-shrink:0;}
.ha-select{flex:1;min-width:74px;padding:4px 6px;border:1px solid #e2e8f0;border-radius:5px;font-size:.72rem;background:#fff;}
.ha-btn-reset{padding:4px 8px;border:1px solid #fecaca;border-radius:5px;background:#fff;color:#ef4444;cursor:pointer;font-size:.74rem;}

/* ── List ── */
.ha-left-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;padding:24px;color:#94a3b8;text-align:center;gap:8px;}
.ha-left-empty i{font-size:2rem;} .ha-left-empty p{font-size:.8rem;margin:0;}
.ha-list{flex:1;overflow-y:auto;}
.ha-skeleton{height:78px;background:linear-gradient(90deg,#f1f5f9 25%,#e2e8f0 37%,#f1f5f9 63%);background-size:400% 100%;border-radius:7px;animation:ha-pulse 1.4s infinite;}
@keyframes ha-pulse{0%{background-position:100% 50%}100%{background-position:0 50%}}
.ha-empty-list{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 16px;color:#94a3b8;gap:7px;text-align:center;}
.ha-empty-list i{font-size:1.8rem;} .ha-empty-list p{font-size:.8rem;margin:0;}
.ha-item{width:100%;display:flex;align-items:center;border:none;background:none;cursor:pointer;padding:9px 12px;text-align:left;position:relative;transition:background .12s;border-bottom:1px solid #f1f5f9;}
.ha-item:hover{background:#f8fafc;} .ha-item--active{background:#eef2ff !important;}
.ha-item-accent{width:3px;height:100%;position:absolute;left:0;top:0;background:transparent;}
.ha-item--active .ha-item-accent{background:#405189;}
.ha-item-body{flex:1;overflow:hidden;padding-left:2px;}
.ha-item-top{display:flex;align-items:center;justify-content:space-between;gap:5px;margin-bottom:2px;}
.ha-item-title{font-weight:700;font-size:.78rem;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;}
.ha-item-sub{font-size:.68rem;color:#64748b;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.ha-item-meta{display:flex;gap:3px;flex-wrap:wrap;margin-bottom:2px;}
.ha-item-date{font-size:.65rem;color:#94a3b8;}
.ha-item-arrow{color:#cbd5e1;font-size:.9rem;flex-shrink:0;}
/* Per PRD badge (total analisa) */
.ha-item-count-badge{display:inline-flex;align-items:center;padding:1px 6px;border-radius:4px;font-size:.62rem;font-weight:700;background:rgba(64,81,137,.1);color:#405189;flex-shrink:0;white-space:nowrap;}
/* Per Analisa badge (jenis analisa) */
.ha-item-analisa-badge{display:inline-flex;align-items:center;gap:2px;padding:1px 6px;border-radius:4px;font-size:.62rem;font-weight:700;flex-shrink:0;white-space:nowrap;}
.ha-akv--anl.ha-item-analisa-badge{background:rgba(64,81,137,.1);color:#405189;}
.ha-akv--plt.ha-item-analisa-badge{background:rgba(8,145,178,.1);color:#0891b2;}
.ha-akv--lckv.ha-item-analisa-badge{background:rgba(124,58,237,.1);color:#7c3aed;}

.ha-chip{display:inline-flex;align-items:center;gap:2px;padding:1px 5px;border-radius:3px;font-size:.62rem;font-weight:600;}
.ha-chip i{font-size:.62rem;}
.ha-chip--blue{background:#eef2ff;color:#405189;} .ha-chip--gray{background:#f1f5f9;color:#64748b;}
.ha-chip--orange{background:#fff7ed;color:#ea580c;} .ha-chip--green{background:#f0fdf4;color:#15803d;}
.ha-chip--cyan{background:#ecfeff;color:#0891b2;} .ha-chip--purple{background:#f5f3ff;color:#7c3aed;}

/* ── Pagination ── */
.ha-list-footer{display:flex;align-items:center;justify-content:space-between;padding:6px 12px;border-top:1px solid #f1f5f9;background:#fff;flex-shrink:0;gap:6px;}
.ha-page-info{font-size:.67rem;color:#94a3b8;flex-shrink:0;}
.ha-page-btns{display:flex;align-items:center;gap:2px;}
.ha-page-btn{width:23px;height:23px;border:1px solid #e2e8f0;border-radius:4px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.78rem;transition:.12s;}
.ha-page-btn:hover:not(:disabled){border-color:#818cf8;color:#405189;}
.ha-page-btn:disabled{opacity:.35;cursor:not-allowed;}
.ha-page-current{font-size:.7rem;color:#475569;font-weight:600;padding:0 3px;white-space:nowrap;}

/* ─── RIGHT PANEL ─── */
.ha-right{flex:1;display:flex;flex-direction:column;overflow:hidden;background:#f0f2f5;}
.ha-detail-empty{flex:1;display:flex;align-items:center;justify-content:center;}
.ha-detail-empty-inner{text-align:center;color:#94a3b8;max-width:260px;}
.ha-empty-icon-wrap{width:58px;height:58px;border-radius:50%;background:rgba(64,81,137,.1);color:#405189;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.5rem;}
.ha-detail-empty-inner h6{color:#475569;font-weight:600;margin-bottom:6px;}
.ha-detail-empty-inner p{font-size:.78rem;margin:0;}

/* ─── DETAIL HEADER ─── */
.ha-detail-header{padding:12px 16px;background:#fff;border-bottom:1px solid #e2e8f0;flex-shrink:0;}
.ha-dh-main{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;}
.ha-dh-icon{width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#405189,#2e3a64);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;}
.ha-dh-title{font-weight:700;font-size:.86rem;color:#0f172a;}
.ha-dh-sampel{font-size:.7rem;color:#64748b;margin:1px 0 4px;font-family:monospace;}
.ha-dh-badges{display:flex;gap:3px;flex-wrap:wrap;}
.ha-badge{display:inline-flex;align-items:center;padding:1px 6px;border-radius:4px;font-size:.67rem;font-weight:600;white-space:nowrap;}
.ha-badge i{font-size:.67rem;}
.ha-badge--blue{background:#eef2ff;color:#405189;}
.ha-badge--gray{background:#f1f5f9;color:#475569;}
.ha-badge--orange{background:#fff7ed;color:#ea580c;}
.ha-badge--success{background:rgba(10,179,156,.1);color:#0ab39c;}
.ha-badge--danger{background:rgba(240,101,72,.1);color:#f06548;border:1px solid rgba(240,101,72,.2);}
.ha-badge--akv-anl{background:rgba(64,81,137,.1);color:#405189;}
.ha-badge--akv-plt{background:rgba(8,145,178,.1);color:#0891b2;}
.ha-badge--akv-lckv{background:rgba(124,58,237,.1);color:#7c3aed;}
.ha-plt-banner{display:flex;align-items:flex-start;gap:9px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border:1px solid #bae6fd;border-radius:7px;padding:7px 11px;margin-bottom:8px;}
.ha-plt-icon{width:26px;height:26px;border-radius:6px;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.78rem;}
.ha-plt-title{font-size:.66rem;font-weight:700;color:#0369a1;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px;}
.ha-plt-chips{display:flex;flex-wrap:wrap;gap:3px;}
.ha-plt-chip{background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;padding:2px 8px;border-radius:10px;font-size:.7rem;font-weight:600;}
.ha-sub-bar{display:flex;align-items:center;gap:7px;margin-bottom:7px;flex-wrap:wrap;}
.ha-sub-label{font-size:.7rem;font-weight:600;color:#475569;white-space:nowrap;}
.ha-sub-loading{display:flex;align-items:center;}
.ha-sub-pills{display:flex;flex-wrap:wrap;gap:3px;}
.ha-sub-pill{padding:3px 9px;border:1px solid #e2e8f0;border-radius:5px;background:#f8fafc;color:#475569;font-size:.72rem;font-weight:600;cursor:pointer;transition:.12s;}
.ha-sub-pill:hover{border-color:#818cf8;color:#405189;}
.ha-sub-pill--active{background:#405189;color:#fff;border-color:#405189;}
.ha-sop-bar{display:flex;align-items:center;gap:5px;padding:5px 10px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:6px;margin-bottom:7px;font-size:.73rem;color:#405189;flex-wrap:wrap;}
.ha-sop-label{font-weight:700;} .ha-sop-val{font-weight:600;font-family:monospace;}

/* ─── TABS ─── */
.ha-tabs{display:flex;border-bottom:1px solid #e2e8f0;background:#fff;flex-shrink:0;padding:0 14px;}
.ha-tab{padding:8px 13px;border:none;background:none;font-size:.78rem;font-weight:500;color:#94a3b8;cursor:pointer;border-bottom:2px solid transparent;display:flex;align-items:center;gap:4px;transition:.12s;}
.ha-tab--active{color:#405189;border-bottom-color:#405189;font-weight:600;}
.ha-tab-count{background:#405189;color:#fff;border-radius:10px;padding:1px 5px;font-size:.58rem;font-weight:700;}

/* ─── DETAIL BODY ─── */
.ha-detail-body{flex:1;overflow-y:auto;padding:10px 12px;}
.ha-loading-state{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:180px;gap:8px;}

/* ─── SECTIONS (Per PRD) ─── */
.ha-section{background:#fff;border-radius:9px;margin-bottom:7px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #e8ecf4;}
.ha-section-hdr{width:100%;display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:none;border:none;cursor:pointer;transition:background .12s;gap:8px;}
.ha-section-hdr:hover{background:#f8fafc;}
.ha-section-hdr-l{display:flex;align-items:center;gap:9px;}
.ha-section-icon{width:30px;height:30px;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.82rem;flex-shrink:0;}
.ha-section-title{font-weight:700;font-size:.82rem;color:#0f172a;display:block;text-align:left;}
.ha-section-sub{font-size:.63rem;color:#94a3b8;display:block;text-align:left;}
.ha-chevron{font-size:1rem;color:#94a3b8;transition:transform .2s;}
.ha-chevron.collapsed{transform:rotate(180deg);}
.ha-section-body{border-top:1px solid #f1f5f9;}
.ha-analisa-panel{border-bottom:1px solid #f1f5f9;}
.ha-analisa-panel:last-child{border-bottom:none;}
.ha-analisa-hdr{width:100%;display:flex;align-items:center;justify-content:space-between;padding:8px 14px;background:none;border:none;cursor:pointer;transition:background .12s;gap:8px;}
.ha-analisa-hdr:hover{background:#fafafa;}
.ha-analisa-hdr--success{border-left:3px solid #0ab39c;} .ha-analisa-hdr--danger{border-left:3px solid #f06548;}
.ha-analisa-hdr-l{display:flex;align-items:flex-start;gap:8px;flex:1;min-width:0;text-align:left;}
.ha-analisa-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:5px;}
.dot--success{background:#0ab39c;} .dot--danger{background:#f06548;}
.ha-analisa-hdr-title{font-weight:600;font-size:.8rem;color:#0f172a;display:block;}
.ha-analisa-hdr-meta{display:flex;gap:3px;flex-wrap:wrap;margin-top:2px;}
.ha-analisa-table-wrap{padding-bottom:8px;background:#f9fafb;}
.ha-table-loading{display:flex;align-items:center;padding:12px 16px;}
.gap-7{gap:7px;}

/* ─── TABLE ─── */
.ha-data-table{font-size:.75rem;}
.ha-data-table thead th{background:#405189;color:#fff;font-weight:600;font-size:.68rem;white-space:nowrap;padding:6px 8px;border-color:#2e3a64;}
.ha-data-table td{padding:5px 8px;vertical-align:middle;border-color:#e9ecf0;}
.ha-th-no{width:28px;} .ha-th-plt{background:#0891b2 !important;color:#fff !important;} .ha-th-formula{background:#3a4d86 !important;color:#c7d2fe !important;}
.ha-td-no{width:28px;color:#64748b;}
.ha-td-plt{background:rgba(8,145,178,.05);border-left:3px solid #0891b2 !important;}
.ha-td-formula{background:rgba(64,81,137,.04);}
.ha-td-mono{font-family:monospace;font-size:.72rem;}
.ha-pembanding{font-weight:700;font-size:.72rem;color:#0369a1;}
.ha-row--ok{background:rgba(10,179,156,.06);} .ha-row--ok td{border-color:rgba(10,179,156,.14)!important;}
.ha-row--ng{background:rgba(240,101,72,.06);} .ha-row--ng td{border-color:rgba(240,101,72,.14)!important;}
.ha-row--rata{background:rgba(247,184,75,.08);} .ha-row--rata td{border-color:rgba(247,184,75,.24)!important;}

/* ─── TIMELINE ─── */
.ha-vtl{padding:2px;} .ha-vtl-hdr{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;padding:0 2px 10px;display:flex;align-items:center;}
.ha-vtl-steps{display:flex;flex-direction:column;} .ha-vtl-step{display:flex;gap:10px;}
.ha-vtl-indicator{display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:28px;}
.ha-vtl-dot{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;flex-shrink:0;border:2px solid;}
.vtl-done .ha-vtl-dot{background:rgba(10,179,156,.1);border-color:#0ab39c;color:#0ab39c;}
.vtl-rejected .ha-vtl-dot{background:rgba(240,101,72,.1);border-color:#f06548;color:#f06548;}
.vtl-final .ha-vtl-dot{background:rgba(64,81,137,.1);border-color:#405189;color:#405189;}
.ha-vtl-line{width:2px;flex:1;min-height:10px;background:#e2e8f0;margin:2px 0;}
.vtl-done .ha-vtl-line{background:#0ab39c;}
.ha-vtl-body{padding-bottom:16px;flex:1;min-width:0;}
.ha-vtl-row1{display:flex;align-items:center;gap:6px;margin-bottom:3px;flex-wrap:wrap;}
.ha-vtl-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:4px;font-size:.68rem;font-weight:700;}
.vtl-badge--success{background:rgba(10,179,156,.1);color:#0ab39c;} .vtl-badge--danger{background:rgba(240,101,72,.1);color:#f06548;}
.vtl-badge--primary{background:rgba(64,81,137,.1);color:#405189;} .vtl-badge--info{background:#ecfeff;color:#0e7490;}
.ha-vtl-sub{font-size:.66rem;font-weight:700;}
.ha-vtl-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:.71rem;color:#64748b;margin-bottom:3px;}
.ha-vtl-details{font-size:.68rem;color:#64748b;background:#f8fafc;border-radius:5px;padding:5px 9px;margin-top:3px;}
.ha-vtl-details-hdr{font-weight:600;color:#475569;margin-bottom:2px;}
.ha-vtl-detail-row{display:flex;align-items:flex-start;gap:2px;line-height:1.6;}
.ha-vtl-note{font-size:.7rem;color:#64748b;margin-top:3px;font-style:italic;padding:3px 7px;background:#f8fafc;border-left:2px solid #e2e8f0;}

/* ─── MOBILE ─── */
.ha-hidden-mobile{display:none !important;}
@media(min-width:768px){.ha-hidden-mobile{display:flex !important;}}
.ha-mobile-back{padding:9px 12px;border-bottom:1px solid #e2e8f0;flex-shrink:0;background:#fff;}
</style>
