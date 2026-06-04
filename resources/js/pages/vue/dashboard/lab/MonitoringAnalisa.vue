<template>
    <div class="mon-root">

        <!-- ══════════════ TOP BAR ══════════════ -->
        <div class="mon-topbar">
            <div class="mon-topbar-left">
                <div class="mon-topbar-icon">
                    <i class="ri-pulse-line"></i>
                </div>
                <div>
                    <div class="mon-topbar-title">Monitoring Analisa</div>
                    <div class="mon-topbar-sub">Tracking real-time pengujian produksi &amp; trial produksi</div>
                </div>
            </div>
            <button class="mon-btn-refresh" :disabled="loading" @click="fetchData()">
                <i class="ri-refresh-line" :class="{ 'mon-spin': loading }"></i> Refresh
            </button>
        </div>

        <!-- ══════════════ FILTER BAR ══════════════ -->
        <div class="mon-filters">
            <div class="mon-search-wrap">
                <i class="ri-search-line mon-search-ico"></i>
                <input class="mon-search-inp" type="text" placeholder="Cari No. PO, No. Split, No. Sampel, Kode Barang…"
                    v-model="filters.search" @input="debounceFetch" />
                <button v-if="filters.search" class="mon-search-x" @click="filters.search='';fetchData()">
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="mon-filter-chip">
                <i class="ri-calendar-line"></i>
                <input type="date" class="mon-chip-inp" v-model="filters.tanggal_dari" @change="fetchData()" />
                <span class="mon-chip-sep">—</span>
                <input type="date" class="mon-chip-inp" v-model="filters.tanggal_sampai" @change="fetchData()" />
            </div>
            <div class="mon-preset-group">
                <button class="mon-preset-btn" @click="setPreset('today')">Hari Ini</button>
                <button class="mon-preset-btn" @click="setPreset('3d')">3 Hari</button>
                <button class="mon-preset-btn" @click="setPreset('7d')">7 Hari</button>
                <button class="mon-preset-btn" @click="setPreset('month')">Bulan Ini</button>
            </div>
            <div class="mon-filter-chip">
                <i class="ri-filter-3-line"></i>
                <select class="mon-chip-sel" v-model="filters.tipe" @change="fetchData()">
                    <option value="">Semua Tipe</option>
                    <option value="produksi">Produksi</option>
                    <option value="trial">Trial Produksi</option>
                </select>
            </div>
            <div class="mon-filter-chip">
                <i class="ri-flag-line"></i>
                <select class="mon-chip-sel" v-model="filters.status" @change="fetchData()">
                    <option value="">Semua Status</option>
                    <option value="belum_input">Belum Diinput</option>
                    <option value="menunggu_validasi">Menunggu Validasi</option>
                    <option value="resampling">Resampling</option>
                    <option value="menunggu_finalisasi">Menunggu Finalisasi</option>
                    <option value="selesai">Selesai</option>
                </select>
            </div>
            <button class="mon-btn-reset" title="Reset filter" @click="resetFilters">
                <i class="ri-delete-bin-line"></i>
            </button>
        </div>

        <!-- ══════════════ DATE RANGE ALERT ══════════════ -->
        <div class="mon-range-alert" :class="'mon-range-' + dateRangeInfo.type">
            <i :class="dateRangeInfo.type === 'warning' ? 'ri-alert-line' : 'ri-information-line'"></i>
            <span>{{ dateRangeInfo.text }}</span>
        </div>

        <!-- ══════════════ KPI CARDS ══════════════ -->
        <div class="mon-kpi-row">
            <div class="mon-kpi mon-kpi-total" @click="setStatus('')">
                <i class="ri-bar-chart-box-line mon-kpi-icon"></i>
                <div class="mon-kpi-num">{{ summary.total_sampel }}</div>
                <div class="mon-kpi-lbl">Total Sampel</div>
                <div class="mon-kpi-sub">{{ summary.total_splits }} No.Split PO</div>
            </div>
            <div class="mon-kpi mon-kpi-belum" @click="setStatus('belum_input')" :class="{ 'mon-kpi-active': filters.status === 'belum_input' }">
                <i class="ri-inbox-unarchive-line mon-kpi-icon"></i>
                <div class="mon-kpi-num">{{ summary.belum_input }}</div>
                <div class="mon-kpi-lbl">Belum Diinput</div>
                <div class="mon-kpi-sub">Menunggu Analyzer</div>
            </div>
            <div class="mon-kpi mon-kpi-validasi" @click="setStatus('menunggu_validasi')" :class="{ 'mon-kpi-active': filters.status === 'menunggu_validasi' }">
                <i class="ri-time-line mon-kpi-icon"></i>
                <div class="mon-kpi-num">{{ summary.menunggu_validasi }}</div>
                <div class="mon-kpi-lbl">Menunggu Validasi</div>
                <div class="mon-kpi-sub">Perlu dikonfirmasi</div>
            </div>
            <div class="mon-kpi mon-kpi-resamp" @click="setStatus('resampling')" :class="{ 'mon-kpi-active': filters.status === 'resampling' }">
                <i class="ri-loop-left-line mon-kpi-icon"></i>
                <div class="mon-kpi-num">{{ summary.resampling }}</div>
                <div class="mon-kpi-lbl">Resampling</div>
                <div class="mon-kpi-sub">Sedang diulang</div>
            </div>
            <div class="mon-kpi mon-kpi-final" @click="setStatus('menunggu_finalisasi')" :class="{ 'mon-kpi-active': filters.status === 'menunggu_finalisasi' }">
                <i class="ri-git-commit-line mon-kpi-icon"></i>
                <div class="mon-kpi-num">{{ summary.menunggu_finalisasi }}</div>
                <div class="mon-kpi-lbl">Menunggu Finalisasi</div>
                <div class="mon-kpi-sub">Autoclave – perlu close</div>
            </div>
            <div class="mon-kpi mon-kpi-selesai" @click="setStatus('selesai')" :class="{ 'mon-kpi-active': filters.status === 'selesai' }">
                <i class="ri-checkbox-circle-line mon-kpi-icon"></i>
                <div class="mon-kpi-num">{{ summary.selesai }}</div>
                <div class="mon-kpi-lbl">Selesai</div>
                <div class="mon-kpi-sub">Telah difinalisasi</div>
            </div>
        </div>

        <!-- ══════════════ MAIN LAYOUT ══════════════ -->
        <div class="mon-body">

            <!-- ─────── LEFT: Split list ─────── -->
            <div class="mon-left" :class="{ 'mon-left-hidden': isMobile && selectedSplit }">
                <div class="mon-panel-hdr">
                    <span><i class="ri-layout-grid-line"></i> Daftar No. Split PO</span>
                    <span class="mon-panel-count">{{ pagination.total }} split</span>
                </div>

                <div v-if="loading" class="mon-skeleton-wrap">
                    <div v-for="i in 7" :key="i" class="mon-skeleton"></div>
                </div>

                <div v-else-if="!loading && splits.length === 0" class="mon-empty">
                    <i class="ri-inbox-2-line"></i>
                    <div>Tidak ada data</div>
                    <small>Coba ubah filter pencarian</small>
                </div>

                <div v-else class="mon-split-list">
                    <div v-for="sp in splits" :key="sp.no_split_po"
                        class="mon-split-card"
                        :class="[
                            'mon-acc-' + sp.status_split,
                            { 'mon-split-active': selectedSplit && selectedSplit.no_split_po === sp.no_split_po }
                        ]"
                        @click="selectSplit(sp)">
                        <div class="mon-sc-head">
                            <div class="mon-sc-ids">
                                <span class="mon-sc-po">{{ sp.no_po }}</span>
                                <span class="mon-sc-slash">/</span>
                                <span class="mon-sc-split">{{ sp.no_split_po }}</span>
                            </div>
                            <span class="mon-tipe-badge" :class="sp.flag_trial ? 'mon-trial' : 'mon-produksi'">
                                {{ sp.flag_trial ? 'Trial' : 'Prod' }}
                            </span>
                        </div>
                        <div class="mon-sc-barang"><i class="ri-box-3-line"></i> {{ sp.kode_barang }}</div>
                        <div class="mon-sc-foot">
                            <span class="mon-status-pill" :class="pillClass(sp.status_split)">
                                <i :class="statusIcon(sp.status_split)"></i>
                                {{ statusLabel(sp.status_split) }}
                            </span>
                            <div class="mon-sc-micros">
                                <span v-if="sp.status_counts.belum_input" class="mon-micro mon-m-belum">{{ sp.status_counts.belum_input }}</span>
                                <span v-if="sp.status_counts.menunggu_validasi" class="mon-micro mon-m-val">{{ sp.status_counts.menunggu_validasi }}</span>
                                <span v-if="sp.status_counts.resampling" class="mon-micro mon-m-res">{{ sp.status_counts.resampling }}</span>
                                <span v-if="sp.status_counts.menunggu_finalisasi" class="mon-micro mon-m-fin">{{ sp.status_counts.menunggu_finalisasi }}</span>
                                <span v-if="sp.status_counts.selesai" class="mon-micro mon-m-sel">{{ sp.status_counts.selesai }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mon-pager" v-if="pagination.totalPage > 1">
                    <button class="mon-pg" :disabled="pagination.page === 1" @click="goPage(pagination.page - 1)">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>
                    <template v-for="p in pageRange" :key="p">
                        <button v-if="p !== '...'" class="mon-pg" :class="{ 'mon-pg-active': p === pagination.page }" @click="goPage(p)">{{ p }}</button>
                        <span v-else class="mon-pg-dots">…</span>
                    </template>
                    <button class="mon-pg" :disabled="pagination.page === pagination.totalPage" @click="goPage(pagination.page + 1)">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>
            </div>

            <!-- ─────── RIGHT: Detail panel ─────── -->
            <div class="mon-right" :class="{ 'mon-right-show': isMobile && selectedSplit }">

                <!-- Placeholder -->
                <div v-if="!selectedSplit" class="mon-placeholder">
                    <i class="ri-cursor-line mon-ph-icon"></i>
                    <div class="mon-ph-title">Pilih No. Split PO</div>
                    <div class="mon-ph-sub">Klik salah satu panel di kiri untuk melihat detail sampel</div>
                </div>

                <!-- Detail -->
                <template v-else>
                    <!-- Detail header -->
                    <div class="mon-det-hdr">
                        <div class="mon-det-hdr-left">
                            <button v-if="isMobile" class="mon-btn-back" @click="selectedSplit = null">
                                <i class="ri-arrow-left-line"></i>
                            </button>
                            <div>
                                <div class="mon-det-title">
                                    {{ selectedSplit.no_split_po }}
                                    <span class="mon-tipe-badge ms-2" :class="selectedSplit.flag_trial ? 'mon-trial' : 'mon-produksi'">
                                        {{ selectedSplit.flag_trial ? 'Trial Produksi' : 'Produksi' }}
                                    </span>
                                </div>
                                <div class="mon-det-sub">
                                    <i class="ri-shopping-bag-line"></i> {{ selectedSplit.no_po }}
                                    &nbsp;·&nbsp;
                                    <i class="ri-box-3-line"></i> {{ selectedSplit.kode_barang }}
                                    &nbsp;·&nbsp;
                                    {{ selectedSplit.total_sampel }} sampel
                                </div>
                            </div>
                        </div>
                        <span class="mon-status-pill mon-pill-lg" :class="pillClass(selectedSplit.status_split)">
                            <i :class="statusIcon(selectedSplit.status_split)"></i>
                            {{ statusLabel(selectedSplit.status_split) }}
                        </span>
                    </div>

                    <!-- Pipeline indicator -->
                    <div class="mon-pipeline">
                        <div v-for="(step, i) in pipeline" :key="step.key"
                            class="mon-pipe-step"
                            :class="{ 'mon-pipe-active': isPipeActive(step.key), 'mon-pipe-done': isPipeDone(step.key) }">
                            <div class="mon-pipe-dot"><i :class="step.icon"></i></div>
                            <div class="mon-pipe-lbl">{{ step.label }}</div>
                            <div v-if="i < pipeline.length - 1" class="mon-pipe-line"></div>
                        </div>
                    </div>

                    <!-- Detail loading skeleton -->
                    <div v-if="detailLoading" class="mon-detail-loading">
                        <div v-for="i in 3" :key="i" class="mon-det-skel"></div>
                        <div class="mon-det-load-lbl"><i class="ri-loader-4-line mon-spin"></i> Memuat data analisa…</div>
                    </div>

                    <!-- Sections -->
                    <div v-else class="mon-sections">
                        <div v-for="sec in sections" :key="sec.key" class="mon-section">
                            <div class="mon-sec-hdr" :class="'mon-sec-' + sec.key" @click="toggleSec(sec.key)">
                                <div class="mon-sec-left">
                                    <i :class="sec.icon"></i>
                                    <span>{{ sec.label }}</span>
                                    <span class="mon-sec-badge">{{ secBadgeCount(sec.key) }}</span>
                                </div>
                                <i class="ri-arrow-down-s-line mon-sec-arrow" :class="{ 'mon-sec-open': openSecs[sec.key] }"></i>
                            </div>

                            <div v-show="openSecs[sec.key]" class="mon-sec-body">
                                <div v-if="getSampel(sec.key).length === 0" class="mon-sec-empty">
                                    <i class="ri-check-line"></i> Tidak ada sampel dalam kondisi ini
                                </div>

                                <div v-for="sm in getSampel(sec.key)" :key="sm.no_sampel" class="mon-card">
                                    <!-- Card header -->
                                    <div class="mon-card-hdr">
                                        <div class="mon-card-ids">
                                            <span class="mon-card-sampel">{{ sm.no_sampel }}</span>
                                            <template v-if="sm.no_fak_sub_po_list && sm.no_fak_sub_po_list.length">
                                                <span class="mon-sub-sep">Sub:</span>
                                                <span v-for="(sub, si) in sm.no_fak_sub_po_list" :key="si" class="mon-card-sub">{{ sub }}</span>
                                            </template>
                                        </div>
                                        <span class="mon-status-pill mon-pill-sm" :class="pillClass(sm.status)">
                                            <i :class="statusIcon(sm.status)"></i>
                                            {{ statusLabel(sm.status) }}
                                        </span>
                                    </div>
                                    <div class="mon-card-meta">
                                        <span><i class="ri-stack-line"></i> Batch {{ sm.no_batch || '-' }}</span>
                                        <span><i class="ri-calendar-line"></i> {{ formatDate(sm.tanggal) }}</span>
                                        <span><i class="ri-settings-3-line"></i> {{ sm.nama_mesin || '-' }}</span>
                                        <span v-if="sm.flag_fg" class="mon-fg-badge"><i class="ri-fire-line"></i> Autoclave (FG)</span>
                                    </div>

                                    <!-- No activities yet -->
                                    <div v-if="!sm.activities || (!sm.activities.LCKV && !sm.activities.ANL && !sm.activities.PLT)"
                                        class="mon-card-no-act">
                                        <i class="ri-inbox-unarchive-line"></i>
                                        Belum ada data analisa – menunggu analyzer menginput hasil uji
                                    </div>

                                    <!-- ── LCKV section ── -->
                                    <div v-if="sm.activities && sm.activities.LCKV && sm.activities.LCKV.length" class="mon-act-block mon-act-lckv">
                                        <div class="mon-act-hdr">
                                            <div class="mon-act-title">
                                                <span class="mon-act-badge mon-badge-lckv">LCKV</span>
                                                Look View
                                            </div>
                                            <button v-if="sm.activities.LCKV.some(g => g.rows.some(r => r.has_foto))" class="mon-btn-foto" @click.stop="openFoto(sm.activities.LCKV, sm.no_sampel)" :disabled="loadingFoto">
                                                <i class="ri-image-line"></i> {{ totalFoto(sm.activities.LCKV) }} Foto
                                            </button>
                                        </div>
                                        <div v-for="group in sm.activities.LCKV" :key="group.kode_analisa" class="mon-analisa-group">
                                            <div class="mon-analisa-lbl">
                                                <span class="mon-kode-tag">{{ group.kode_analisa }}</span>{{ group.jenis_analisa }}
                                            </div>
                                            <div class="mon-tbl-scroll">
                                                <table class="mon-act-tbl">
                                                    <thead><tr>
                                                        <th class="col-no">#</th>
                                                        <th class="col-faktur">No Transaksi</th>
                                                        <th class="col-info">No Sampel</th>
                                                        <th class="col-info">No PO</th>
                                                        <th class="col-info">Split</th>
                                                        <th class="col-info">Batch</th>
                                                        <th class="col-info">Tgl Reg</th>
                                                        <th class="col-info">Tgl Uji</th>
                                                        <th v-if="secHasSub(group.rows)" class="col-info">Sub</th>
                                                        <th v-if="secHasResamp(group.rows)">Tahap</th>
                                                        <th v-for="p in group.template" :key="p.id_qc" class="col-param">{{ p.nama_parameter }}</th>
                                                        <th class="col-hasil">Hasil</th>
                                                        <th class="col-status">Status</th>
                                                    </tr></thead>
                                                    <tbody>
                                                        <tr v-for="(row, i) in group.rows" :key="row.no_faktur">
                                                            <td class="col-no">{{ i + 1 }}</td>
                                                            <td class="col-faktur">{{ row.no_faktur }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_sampel }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_po }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_split_po }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_batch || '—' }}</td>
                                                            <td class="col-info td-muted">{{ formatDate(sm.tanggal) }}</td>
                                                            <td class="col-info">{{ formatDate(row.tanggal_uji) }}</td>
                                                            <td v-if="secHasSub(group.rows)" class="col-sub">{{ row.no_fak_sub_po || '—' }}</td>
                                                            <td v-if="secHasResamp(group.rows)">
                                                                <span v-if="row.flag_resampling === 'Y'" class="mon-resamp-tag">R{{ row.tahapan_ke }}</span>
                                                                <span v-else class="col-dash">—</span>
                                                            </td>
                                                            <td v-for="(pval, pi) in row.parameters" :key="pi" class="col-param-val">{{ pval !== null && pval !== undefined ? pval : '—' }}</td>
                                                            <td class="col-hasil" :class="resultClass(row)">
                                                                <template v-if="row.hasil !== null && row.hasil !== undefined && row.hasil !== ''">{{ row.hasil }}</template>
                                                                <span v-else class="mon-pending">—</span>
                                                            </td>
                                                            <td class="col-status"><i :class="row.flag_layak === 'Y' ? 'ri-checkbox-circle-fill res-ok' : (row.status_keputusan ? 'ri-close-circle-fill res-ng' : 'ri-time-line res-pending')"></i></td>
                                                        </tr>
                                                        <tr v-if="group.flag_perhitungan === 'Y' && groupAvg(group.rows) !== null" class="mon-avg-row">
                                                            <td :colspan="rowColspan(group)" class="avg-label">Rata-Rata</td>
                                                            <td class="avg-val">{{ groupAvg(group.rows) }}</td>
                                                            <td></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── ANL section ── -->
                                    <div v-if="sm.activities && sm.activities.ANL && sm.activities.ANL.length" class="mon-act-block mon-act-anl">
                                        <div class="mon-act-hdr">
                                            <div class="mon-act-title"><span class="mon-act-badge mon-badge-anl">ANL</span> Analisa Lab</div>
                                        </div>
                                        <div v-for="group in sm.activities.ANL" :key="group.kode_analisa" class="mon-analisa-group">
                                            <div class="mon-analisa-lbl">
                                                <span class="mon-kode-tag">{{ group.kode_analisa }}</span>{{ group.jenis_analisa }}
                                            </div>
                                            <div class="mon-tbl-scroll">
                                                <table class="mon-act-tbl">
                                                    <thead><tr>
                                                        <th class="col-no">#</th>
                                                        <th class="col-faktur">No Transaksi</th>
                                                        <th class="col-info">No Sampel</th>
                                                        <th class="col-info">No PO</th>
                                                        <th class="col-info">Split</th>
                                                        <th class="col-info">Batch</th>
                                                        <th class="col-info">Tgl Reg</th>
                                                        <th class="col-info">Tgl Uji</th>
                                                        <th v-if="secHasSub(group.rows)" class="col-info">Sub</th>
                                                        <th v-if="secHasResamp(group.rows)">Tahap</th>
                                                        <th v-for="p in group.template" :key="p.id_qc" class="col-param">{{ p.nama_parameter }}</th>
                                                        <th class="col-hasil">Hasil</th>
                                                        <th class="col-status">Status</th>
                                                    </tr></thead>
                                                    <tbody>
                                                        <tr v-for="(row, i) in group.rows" :key="row.no_faktur">
                                                            <td class="col-no">{{ i + 1 }}</td>
                                                            <td class="col-faktur">{{ row.no_faktur }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_sampel }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_po }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_split_po }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_batch || '—' }}</td>
                                                            <td class="col-info td-muted">{{ formatDate(sm.tanggal) }}</td>
                                                            <td class="col-info">{{ formatDate(row.tanggal_uji) }}</td>
                                                            <td v-if="secHasSub(group.rows)" class="col-sub">{{ row.no_fak_sub_po || '—' }}</td>
                                                            <td v-if="secHasResamp(group.rows)">
                                                                <span v-if="row.flag_resampling === 'Y'" class="mon-resamp-tag">R{{ row.tahapan_ke }}</span>
                                                                <span v-else class="col-dash">—</span>
                                                            </td>
                                                            <td v-for="(pval, pi) in row.parameters" :key="pi" class="col-param-val">{{ pval !== null && pval !== undefined ? pval : '—' }}</td>
                                                            <td class="col-hasil" :class="resultClass(row)">
                                                                <template v-if="row.hasil !== null && row.hasil !== undefined && row.hasil !== ''">{{ row.hasil }}</template>
                                                                <span v-else class="mon-pending">—</span>
                                                            </td>
                                                            <td class="col-status"><i :class="row.flag_layak === 'Y' ? 'ri-checkbox-circle-fill res-ok' : (row.status_keputusan ? 'ri-close-circle-fill res-ng' : 'ri-time-line res-pending')"></i></td>
                                                        </tr>
                                                        <tr v-if="group.flag_perhitungan === 'Y' && groupAvg(group.rows) !== null" class="mon-avg-row">
                                                            <td :colspan="rowColspan(group)" class="avg-label">Rata-Rata</td>
                                                            <td class="avg-val">{{ groupAvg(group.rows) }}</td>
                                                            <td></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── PLT section ── -->
                                    <div v-if="sm.activities && sm.activities.PLT && sm.activities.PLT.length" class="mon-act-block mon-act-plt">
                                        <div class="mon-act-hdr">
                                            <div class="mon-act-title"><span class="mon-act-badge mon-badge-plt">PLT</span> Palatabilitas</div>
                                        </div>
                                        <div v-for="group in sm.activities.PLT" :key="group.kode_analisa" class="mon-analisa-group">
                                            <div class="mon-analisa-lbl">
                                                <span class="mon-kode-tag">{{ group.kode_analisa }}</span>{{ group.jenis_analisa }}
                                            </div>
                                            <div class="mon-tbl-scroll">
                                                <table class="mon-act-tbl">
                                                    <thead><tr>
                                                        <th class="col-no">#</th>
                                                        <th class="col-pembanding">Pembanding</th>
                                                        <th class="col-faktur">No Transaksi</th>
                                                        <th class="col-info">No Sampel</th>
                                                        <th class="col-info">No PO</th>
                                                        <th class="col-info">Split</th>
                                                        <th class="col-info">Batch</th>
                                                        <th class="col-info">Tgl Reg</th>
                                                        <th class="col-info">Tgl Uji</th>
                                                        <th v-if="secHasResamp(group.rows)">Tahap</th>
                                                        <th v-for="p in group.template" :key="p.id_qc" class="col-param">{{ p.nama_parameter }}</th>
                                                        <th class="col-hasil">Hasil</th>
                                                        <th class="col-status">Status</th>
                                                    </tr></thead>
                                                    <tbody>
                                                        <tr v-for="(row, i) in group.rows" :key="row.no_faktur">
                                                            <td class="col-no">{{ i + 1 }}</td>
                                                            <td class="td-pembanding">{{ row.nama_pembanding || '—' }}</td>
                                                            <td class="col-faktur">{{ row.no_faktur }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_sampel }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_po }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_split_po }}</td>
                                                            <td class="col-info td-muted">{{ sm.no_batch || '—' }}</td>
                                                            <td class="col-info td-muted">{{ formatDate(sm.tanggal) }}</td>
                                                            <td class="col-info">{{ formatDate(row.tanggal_uji) }}</td>
                                                            <td v-if="secHasResamp(group.rows)">
                                                                <span v-if="row.flag_resampling === 'Y'" class="mon-resamp-tag">R{{ row.tahapan_ke }}</span>
                                                                <span v-else class="col-dash">—</span>
                                                            </td>
                                                            <td v-for="(pval, pi) in row.parameters" :key="pi" class="col-param-val">{{ pval !== null && pval !== undefined ? pval : '—' }}</td>
                                                            <td class="col-hasil" :class="resultClass(row)">
                                                                <template v-if="row.hasil !== null && row.hasil !== undefined && row.hasil !== ''">{{ row.hasil }}</template>
                                                                <span v-else class="mon-pending">—</span>
                                                            </td>
                                                            <td class="col-status"><i :class="row.flag_layak === 'Y' ? 'ri-checkbox-circle-fill res-ok' : (row.status_keputusan ? 'ri-close-circle-fill res-ng' : 'ri-time-line res-pending')"></i></td>
                                                        </tr>
                                                        <tr v-if="group.flag_perhitungan === 'Y' && groupAvg(group.rows) !== null" class="mon-avg-row">
                                                            <td :colspan="rowColspan(group, true)" class="avg-label">Rata-Rata</td>
                                                            <td class="avg-val">{{ groupAvg(group.rows) }}</td>
                                                            <td></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div><!-- /mon-sections -->
                </template>
            </div><!-- /mon-right -->
        </div><!-- /mon-body -->

        <!-- ══════════════ FOTO MODAL ══════════════ -->
        <teleport to="body">
            <transition name="mon-modal-fade">
                <div v-if="fotoModal.show" class="mon-modal-overlay" @click.self="closeFoto">
                    <div class="mon-modal">
                        <div class="mon-modal-hdr">
                            <div>
                                <i class="ri-image-2-line me-2"></i>
                                <strong>Foto Look View</strong>
                                <span class="mon-modal-sub ms-2">{{ fotoModal.no_sampel }}</span>
                            </div>
                            <button class="mon-modal-close" @click="closeFoto"><i class="ri-close-line"></i></button>
                        </div>
                        <div class="mon-modal-body">
                            <div v-if="fotoModal.loading" class="mon-foto-loading">
                                <i class="ri-loader-4-line mon-spin"></i> Memuat foto…
                            </div>
                            <div v-else-if="fotoModal.urls.length === 0" class="mon-foto-empty">
                                <i class="ri-image-2-line"></i>
                                <div>Tidak ada foto tersedia</div>
                            </div>
                            <div v-else class="mon-foto-grid">
                                <div v-for="(item, idx) in fotoModal.urls" :key="idx" class="mon-polaroid"
                                    @click="openLightbox(idx)">
                                    <div class="mon-polaroid-img-wrap">
                                        <img :src="item.url" :alt="item.keterangan||'Foto '+(idx+1)"
                                            class="mon-polaroid-img"
                                            @error="item.error = true" />
                                        <div v-if="item.error" class="mon-foto-err">
                                            <i class="ri-image-2-line"></i>
                                        </div>
                                        <div class="mon-polaroid-overlay"><i class="ri-zoom-in-line"></i></div>
                                    </div>
                                    <div class="mon-polaroid-caption">{{ item.keterangan || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Lightbox -->
            <transition name="mon-modal-fade">
                <div v-if="lightbox.show" class="mon-lightbox" @click.self="lightbox.show = false">
                    <button class="mon-lb-close" @click="lightbox.show = false"><i class="ri-close-line"></i></button>
                    <button class="mon-lb-nav mon-lb-prev" @click="lbNav(-1)" :disabled="lightbox.idx === 0">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>
                    <img :src="fotoModal.urls[lightbox.idx]?.url" class="mon-lb-img" />
                    <div v-if="fotoModal.urls[lightbox.idx]?.keterangan" class="mon-lb-caption">{{ fotoModal.urls[lightbox.idx].keterangan }}</div>
                    <button class="mon-lb-nav mon-lb-next" @click="lbNav(1)" :disabled="lightbox.idx === fotoModal.urls.length - 1">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                    <div class="mon-lb-counter">{{ lightbox.idx + 1 }} / {{ fotoModal.urls.length }}</div>
                </div>
            </transition>
        </teleport>

    </div>
</template>

<script>
import axios from "axios";

const SECTIONS = [
    { key: "belum_input",         label: "Belum Diinput",         icon: "ri-inbox-unarchive-line" },
    { key: "sedang_proses",       label: "Sedang Proses",         icon: "ri-loader-4-line" },
    { key: "menunggu_finalisasi", label: "Menunggu Finalisasi",   icon: "ri-git-commit-line" },
    { key: "selesai",             label: "Selesai",               icon: "ri-checkbox-circle-line" },
];

const PIPELINE = [
    { key: "belum_input",         label: "Belum Mulai",     icon: "ri-inbox-unarchive-line" },
    { key: "sedang_proses",       label: "Sedang Proses",   icon: "ri-flask-line" },
    { key: "menunggu_finalisasi", label: "Finalisasi",      icon: "ri-git-commit-line" },
    { key: "selesai",             label: "Selesai",         icon: "ri-checkbox-circle-line" },
];

const STATUS_ORDER = { belum_input: 0, menunggu_validasi: 1, resampling: 1, menunggu_finalisasi: 2, selesai: 3 };

function fmtLocalDate(d) {
    return d.getFullYear() + '-'
        + String(d.getMonth() + 1).padStart(2, '0') + '-'
        + String(d.getDate()).padStart(2, '0');
}

function getDefaultDates() {
    const today = new Date();
    const from  = new Date(today);
    from.setDate(today.getDate() - 2); // default 3 hari
    return { dari: fmtLocalDate(from), sampai: fmtLocalDate(today) };
}

export default {
    name: "MonitoringAnalisa",
    data() {
        return {
            loading: false, loadingFoto: false,
            detailLoading: false, selectedSplitDetail: null, detailCache: {},
            splits: [], summary: {
                total_splits: 0, total_sampel: 0, belum_input: 0,
                menunggu_validasi: 0, resampling: 0, menunggu_finalisasi: 0, selesai: 0,
            },
            pagination: { page: 1, limit: 15, totalPage: 1, total: 0 },
            selectedSplit: null,
            isMobile: false,
            filters: { search: "", tanggal_dari: getDefaultDates().dari, tanggal_sampai: getDefaultDates().sampai, tipe: "", status: "", kode_barang: "" },
            openSecs: { belum_input: true, sedang_proses: true, menunggu_finalisasi: true, selesai: false },
            sections: SECTIONS,
            pipeline: PIPELINE,
            fotoModal: { show: false, loading: false, urls: [], no_sampel: "" },
            lightbox: { show: false, idx: 0 },
            _timer: null,
        };
    },
    computed: {
        dateRangeInfo() {
            const dari   = this.filters.tanggal_dari;
            const sampai = this.filters.tanggal_sampai;
            const fmt = v => {
                const d = new Date(v);
                return isNaN(d) ? v : d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
            };
            const def = getDefaultDates();
            if (dari === def.dari && sampai === def.sampai) {
                return { type: "default", text: `Menampilkan 3 hari terakhir (${fmt(dari)} s.d. ${fmt(sampai)}). Ubah filter tanggal di atas untuk melihat periode lain.` };
            }
            if (!dari && !sampai) {
                return { type: "warning", text: "Tidak ada filter tanggal aktif — semua data ditampilkan. Disarankan menggunakan filter rentang tanggal agar tidak terlalu berat." };
            }
            const diffMs   = new Date(sampai) - new Date(dari);
            const diffDays = !isNaN(diffMs) ? Math.round(diffMs / 86400000) + 1 : "?";
            return { type: "custom", text: `Filter tanggal aktif: ${fmt(dari) || "?"} s.d. ${fmt(sampai) || "?"} (${diffDays} hari).` };
        },
        pageRange() {
            const { page: p, totalPage: t } = this.pagination;
            if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1);
            if (p <= 4) return [1, 2, 3, 4, 5, "...", t];
            if (p >= t - 3) return [1, "...", t - 4, t - 3, t - 2, t - 1, t];
            return [1, "...", p - 1, p, p + 1, "...", t];
        },
    },
    methods: {
        async fetchData(page = 1) {
            this.loading = true;
            this.detailCache = {};
            try {
                const res = await axios.get("/api/v1/monitoring-analisa", {
                    params: { page, limit: this.pagination.limit, ...this.filters },
                });
                if (res.data.success) {
                    this.splits     = res.data.result.splits;
                    this.summary    = res.data.result.summary;
                    this.pagination = { ...this.pagination, ...res.data.pagination };
                    if (this.selectedSplit) {
                        const fresh = this.splits.find(s => s.no_split_po === this.selectedSplit.no_split_po);
                        if (fresh) {
                            this.selectedSplit = fresh;
                            this.selectedSplitDetail = null;
                            this.fetchDetail(fresh);
                        } else {
                            this.selectedSplit = null;
                            this.selectedSplitDetail = null;
                        }
                    }
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        debounceFetch() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.fetchData(), 420);
        },
        goPage(p) {
            if (p < 1 || p > this.pagination.totalPage) return;
            this.pagination.page = p;
            this.fetchData(p);
        },
        selectSplit(sp) {
            this.selectedSplit = sp;
            this.selectedSplitDetail = null;
            this.fetchDetail(sp);
        },
        async fetchDetail(sp) {
            const key = sp.no_split_po;
            if (this.detailCache[key]) {
                if (this.selectedSplit && this.selectedSplit.no_split_po === key) {
                    this.selectedSplitDetail = this.detailCache[key];
                }
                return;
            }
            this.detailLoading = true;
            try {
                const res = await axios.get('/api/v1/monitoring-analisa/detail', {
                    params: { no_split_po: key },
                });
                if (res.data.success) {
                    this.detailCache[key] = res.data.sampel;
                    if (this.selectedSplit && this.selectedSplit.no_split_po === key) {
                        this.selectedSplitDetail = res.data.sampel;
                    }
                }
            } catch (e) {
                console.error(e);
            } finally {
                if (this.selectedSplit && this.selectedSplit.no_split_po === key) {
                    this.detailLoading = false;
                }
            }
        },
        setStatus(s) { this.filters.status = s; this.fetchData(); },
        resetFilters() {
            const { dari, sampai } = getDefaultDates();
            this.filters = { search: "", tanggal_dari: dari, tanggal_sampai: sampai, tipe: "", status: "", kode_barang: "" };
            this.fetchData();
        },
        toggleSec(key) { this.openSecs[key] = !this.openSecs[key]; },
        getSampel(secKey) {
            const all = this.selectedSplitDetail;
            if (!all) return [];
            if (secKey === "belum_input")         return all.filter(s => s.status === "belum_input");
            if (secKey === "sedang_proses")        return all.filter(s => ["menunggu_validasi", "resampling"].includes(s.status));
            if (secKey === "menunggu_finalisasi")  return all.filter(s => s.status === "menunggu_finalisasi");
            if (secKey === "selesai")              return all.filter(s => s.status === "selesai");
            return [];
        },
        secBadgeCount(secKey) {
            if (!this.selectedSplit) return 0;
            if (this.detailLoading || !this.selectedSplitDetail) {
                const sc = this.selectedSplit.status_counts || {};
                if (secKey === 'belum_input')        return sc.belum_input || 0;
                if (secKey === 'sedang_proses')      return (sc.menunggu_validasi || 0) + (sc.resampling || 0);
                if (secKey === 'menunggu_finalisasi')return sc.menunggu_finalisasi || 0;
                if (secKey === 'selesai')            return sc.selesai || 0;
                return 0;
            }
            return this.getSampel(secKey).length;
        },
        isPipeActive(stepKey) {
            if (!this.selectedSplit) return false;
            const sp = this.selectedSplit.status_split;
            const stepOrd = STATUS_ORDER[stepKey] ?? 0;
            const spOrd   = STATUS_ORDER[sp] ?? 0;
            return stepOrd === spOrd;
        },
        isPipeDone(stepKey) {
            if (!this.selectedSplit) return false;
            const sp = this.selectedSplit.status_split;
            return (STATUS_ORDER[stepKey] ?? 0) < (STATUS_ORDER[sp] ?? 0);
        },
        totalFoto(lckvGroups) {
            return lckvGroups.reduce((sum, g) => sum + g.rows.reduce((s2, r) => s2 + (r.foto_count || 0), 0), 0);
        },
        async openFoto(lckvGroups, noSampel) {
            this.fotoModal = { show: true, loading: true, urls: [], no_sampel: noSampel };
            const allFotos = [];
            lckvGroups.forEach(g => g.rows.forEach(r => {
                if (r.berkas_keys) {
                    r.berkas_keys.forEach((k, i) => {
                        allFotos.push({ key: k, keterangan: (r.berkas_keterangan || [])[i] || '' });
                    });
                }
            }));
            if (allFotos.length === 0) {
                this.fotoModal.loading = false;
                return;
            }
            const allKeys = allFotos.map(f => f.key);
            try {
                const res = await axios.post("/api/v1/lab/hasil-uji/berkas/foto/token/bulk", { keys: allKeys });
                const tokenMap = res.data;
                this.fotoModal.urls = allFotos.map(f => ({
                    url: `/api/v1/lab/berkas/stream/foto-uji/${f.key}?token=${tokenMap[f.key]}`,
                    keterangan: f.keterangan,
                    error: false,
                }));
            } catch (e) {
                console.error(e);
                this.fotoModal.urls = [];
            } finally {
                this.fotoModal.loading = false;
            }
        },
        closeFoto() { this.fotoModal.show = false; this.lightbox.show = false; },
        openLightbox(idx) { this.lightbox = { show: true, idx }; },
        lbNav(dir) {
            const next = this.lightbox.idx + dir;
            if (next >= 0 && next < this.fotoModal.urls.length) this.lightbox.idx = next;
        },
        formatDate(v) {
            if (!v) return "-";
            const d = new Date(v);
            return isNaN(d) ? v : d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
        },
        statusLabel(s) {
            return { belum_input: "Belum Diinput", menunggu_validasi: "Menunggu Validasi", resampling: "Resampling", menunggu_finalisasi: "Menunggu Finalisasi", selesai: "Selesai" }[s] || s;
        },
        statusIcon(s) {
            return { belum_input: "ri-inbox-unarchive-line", menunggu_validasi: "ri-time-line", resampling: "ri-loop-left-line", menunggu_finalisasi: "ri-git-commit-line", selesai: "ri-checkbox-circle-line" }[s] || "ri-question-line";
        },
        pillClass(s) {
            return { belum_input: "pill-belum", menunggu_validasi: "pill-val", resampling: "pill-res", menunggu_finalisasi: "pill-fin", selesai: "pill-sel" }[s] || "";
        },
        resultClass(a) {
            if (a.hasil === null || a.hasil === undefined || a.hasil === "") return "res-pending";
            if (a.flag_layak === "Y") return "res-ok";
            if (a.status_keputusan) return "res-ng";
            return "res-pending";
        },
        secHasSub(items) {
            return items.some(a => a.no_fak_sub_po);
        },
        secHasResamp(items) {
            return items.some(a => a.flag_resampling === "Y");
        },
        groupAvg(rows) {
            const nums = rows
                .map(r => parseFloat(r.hasil))
                .filter(v => !isNaN(v));
            if (!nums.length) return null;
            return (nums.reduce((s, v) => s + v, 0) / nums.length).toFixed(4);
        },
        setPreset(p) {
            const today = new Date();
            const from  = new Date(today);
            if (p === 'today') {
                this.filters.tanggal_dari = this.filters.tanggal_sampai = fmtLocalDate(today);
            } else if (p === '3d') {
                from.setDate(today.getDate() - 2);
                this.filters.tanggal_dari = fmtLocalDate(from); this.filters.tanggal_sampai = fmtLocalDate(today);
            } else if (p === '7d') {
                from.setDate(today.getDate() - 6);
                this.filters.tanggal_dari = fmtLocalDate(from); this.filters.tanggal_sampai = fmtLocalDate(today);
            } else if (p === 'month') {
                const y = today.getFullYear(), m = String(today.getMonth() + 1).padStart(2, '0');
                this.filters.tanggal_dari = `${y}-${m}-01`; this.filters.tanggal_sampai = fmtLocalDate(today);
            }
            this.fetchData();
        },
        // colspan for rata-rata row: 8 fixed cols (LCKV/ANL) or 9 (PLT) + conditionals + params
        rowColspan(group, isPlt = false) {
            const base = isPlt ? 9 : 8;
            return base
                + (!isPlt && this.secHasSub(group.rows) ? 1 : 0)
                + (this.secHasResamp(group.rows) ? 1 : 0)
                + group.template.length;
        },
        checkMobile() { this.isMobile = window.innerWidth < 800; },
    },
    mounted() {
        this.fetchData();
        this.checkMobile();
        window.addEventListener("resize", this.checkMobile);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.checkMobile);
    },
};
</script>

<style scoped>
/* ── Root ─────────────────────────────────────────────────────────────── */
:root { --prim: #405189; --prim-light: #eef0f9; --prim-dark: #2e3a6e; }
.mon-root { background: #f3f6fb; min-height: 100vh; font-family: 'Inter','Segoe UI',sans-serif; }

/* ── Date Range Alert ─────────────────────────────────────────────────── */
.mon-range-alert {
    display: flex; align-items: center; gap: 8px;
    margin: 0 16px 0; padding: 9px 14px;
    border-radius: 7px; font-size: 0.8rem; line-height: 1.4;
    border-left: 4px solid;
}
.mon-range-alert i { font-size: 1rem; flex-shrink: 0; }
.mon-range-default {
    background: #eef0f9; color: #405189; border-color: #405189;
}
.mon-range-custom {
    background: #e8f4fd; color: #1565c0; border-color: #42a5f5;
}
.mon-range-warning {
    background: #fff8e1; color: #b45309; border-color: #f59e0b;
}

/* ── Top Bar ──────────────────────────────────────────────────────────── */
.mon-topbar {
    background: #405189;
    padding: 16px 28px;
    display: flex; align-items: center; justify-content: space-between;
}
.mon-topbar-left { display: flex; align-items: center; gap: 14px; }
.mon-topbar-icon {
    width: 42px; height: 42px; background: rgba(255,255,255,.18);
    border-radius: 11px; display: flex; align-items: center; justify-content: center;
    font-size: 21px; color: #fff;
}
.mon-topbar-title { font-size: 1.1rem; font-weight: 700; color: #fff; }
.mon-topbar-sub   { font-size: .75rem; color: rgba(255,255,255,.72); margin-top: 2px; }
.mon-btn-refresh {
    display: flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
    color: #fff; padding: 7px 16px; border-radius: 8px; font-size: .82rem; cursor: pointer;
}
.mon-btn-refresh:hover:not(:disabled) { background: rgba(255,255,255,.25); }
.mon-btn-refresh:disabled { opacity: .5; cursor: not-allowed; }

/* ── Filter Bar ───────────────────────────────────────────────────────── */
.mon-filters {
    background: #fff; border-bottom: 1px solid #e6e9f0;
    padding: 12px 28px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.mon-search-wrap { position: relative; flex: 1 1 220px; min-width: 180px; }
.mon-search-ico  { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: 14px; }
.mon-search-inp  { width: 100%; padding: 8px 30px 8px 32px; border: 1.5px solid #dee2e6; border-radius: 8px; font-size: .83rem; background: #f8fafc; outline: none; }
.mon-search-inp:focus { border-color: #405189; background: #fff; }
.mon-search-x    { position: absolute; right: 7px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #adb5bd; cursor: pointer; }
.mon-filter-chip { display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1.5px solid #dee2e6; border-radius: 8px; padding: 5px 10px; font-size: .8rem; color: #6c757d; }
.mon-filter-chip i { color: #405189; }
.mon-chip-inp  { border: none; background: transparent; font-size: .8rem; outline: none; }
.mon-chip-sep  { color: #adb5bd; }
.mon-chip-sel  { border: none; background: transparent; font-size: .8rem; outline: none; cursor: pointer; }
.mon-btn-reset { background: #fff5f5; border: 1.5px solid #fcc; color: #e03131; border-radius: 8px; padding: 7px 11px; cursor: pointer; }
.mon-btn-reset:hover { background: #ffe3e3; }
.mon-preset-group { display: flex; gap: 4px; }
.mon-preset-btn { background: #eef0f9; border: 1.5px solid #c5cde8; color: #405189; border-radius: 7px; padding: 5px 10px; font-size: .75rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.mon-preset-btn:hover { background: #405189; color: #fff; border-color: #405189; }

/* ── KPI Cards ────────────────────────────────────────────────────────── */
.mon-kpi-row { display: flex; gap: 12px; padding: 18px 28px 10px; flex-wrap: wrap; }
.mon-kpi {
    flex: 1 1 130px; background: #fff; border-radius: 13px; padding: 14px 16px;
    border-left: 4px solid transparent; cursor: pointer; position: relative;
    box-shadow: 0 1px 5px rgba(0,0,0,.05); transition: transform .15s, box-shadow .15s;
}
.mon-kpi:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,.09); }
.mon-kpi-active { box-shadow: 0 0 0 2px #405189 !important; }
.mon-kpi-icon { font-size: 1.4rem; margin-bottom: 6px; }
.mon-kpi-num  { font-size: 1.55rem; font-weight: 800; color: #212529; line-height: 1; }
.mon-kpi-lbl  { font-size: .78rem; font-weight: 600; color: #495057; margin-top: 4px; }
.mon-kpi-sub  { font-size: .68rem; color: #adb5bd; margin-top: 2px; }
.mon-kpi-total  { border-left-color: #405189; } .mon-kpi-total .mon-kpi-icon  { color: #405189; }
.mon-kpi-belum  { border-left-color: #868e96; } .mon-kpi-belum .mon-kpi-icon  { color: #868e96; }
.mon-kpi-validasi{ border-left-color: #f8ab00; } .mon-kpi-validasi .mon-kpi-icon { color: #f8ab00; }
.mon-kpi-resamp { border-left-color: #fd7e14; } .mon-kpi-resamp .mon-kpi-icon { color: #fd7e14; }
.mon-kpi-final  { border-left-color: #3bc8e7; } .mon-kpi-final .mon-kpi-icon  { color: #3bc8e7; }
.mon-kpi-selesai{ border-left-color: #0ab39c; } .mon-kpi-selesai .mon-kpi-icon{ color: #0ab39c; }

/* ── Body ─────────────────────────────────────────────────────────────── */
.mon-body { display: grid; grid-template-columns: 320px 1fr; gap: 14px; padding: 6px 28px 40px; min-height: calc(100vh - 280px); }

/* ── Left panel ───────────────────────────────────────────────────────── */
.mon-left {
    background: #fff; border-radius: 13px; box-shadow: 0 1px 5px rgba(0,0,0,.05);
    display: flex; flex-direction: column; overflow: hidden;
    max-height: calc(100vh - 280px);
}
.mon-panel-hdr {
    padding: 13px 16px; background: #405189; color: #fff;
    display: flex; align-items: center; justify-content: space-between;
    font-size: .84rem; font-weight: 600; flex-shrink: 0;
}
.mon-panel-count { background: rgba(255,255,255,.25); border-radius: 20px; padding: 2px 10px; font-size: .72rem; font-weight: 700; }
.mon-split-list  { overflow-y: auto; flex: 1; padding: 10px; }

/* Split cards */
.mon-split-card {
    border-radius: 10px; border: 1.5px solid #e6e9f0; padding: 12px 13px; margin-bottom: 7px;
    cursor: pointer; transition: border-color .2s, box-shadow .2s; background: #fff;
    border-left-width: 4px;
}
.mon-split-card:hover  { border-color: #405189; box-shadow: 0 2px 10px rgba(64,81,137,.1); }
.mon-split-active      { border-color: #405189 !important; background: #eef0f9 !important; }
.mon-acc-belum_input         { border-left-color: #adb5bd; }
.mon-acc-menunggu_validasi   { border-left-color: #f8ab00; }
.mon-acc-resampling          { border-left-color: #fd7e14; }
.mon-acc-menunggu_finalisasi { border-left-color: #3bc8e7; }
.mon-acc-selesai             { border-left-color: #0ab39c; }
.mon-sc-head  { display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; margin-bottom: 4px; }
.mon-sc-ids   { display: flex; align-items: center; gap: 2px; min-width: 0; }
.mon-sc-po    { font-size: .8rem; font-weight: 700; color: #212529; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mon-sc-slash { color: #ced4da; margin: 0 2px; }
.mon-sc-split { font-size: .72rem; color: #6c757d; white-space: nowrap; }
.mon-sc-barang { font-size: .74rem; color: #6c757d; margin-bottom: 7px; }
.mon-sc-foot  { display: flex; align-items: center; justify-content: space-between; gap: 4px; flex-wrap: wrap; }
.mon-sc-micros { display: flex; gap: 4px; }
.mon-micro    { border-radius: 20px; padding: 1px 7px; font-size: .63rem; font-weight: 700; }
.mon-m-belum { background: #f1f3f5; color: #868e96; }
.mon-m-val   { background: #fff8e6; color: #b08800; }
.mon-m-res   { background: #fff3ea; color: #c94e00; }
.mon-m-fin   { background: #eafbff; color: #2490a7; }
.mon-m-sel   { background: #e6faf7; color: #0a7a65; }

/* ── Status Pills ─────────────────────────────────────────────────────── */
.mon-tipe-badge { border-radius: 20px; padding: 2px 7px; font-size: .63rem; font-weight: 700; flex-shrink: 0; }
.mon-trial    { background: #fff0e6; color: #fd7e14; border: 1px solid #ffd8b2; }
.mon-produksi { background: #eef0f9; color: #405189; border: 1px solid #c5cde8; }
.mon-status-pill { display: inline-flex; align-items: center; gap: 4px; border-radius: 20px; padding: 3px 9px; font-size: .7rem; font-weight: 600; white-space: nowrap; }
.mon-pill-lg  { font-size: .78rem; padding: 5px 13px; }
.mon-pill-sm  { font-size: .67rem; padding: 2px 7px; }
.pill-belum { background: #f1f3f5; color: #495057; border: 1px solid #ced4da; }
.pill-val   { background: #fff8e6; color: #b08800; border: 1px solid #ffe08a; }
.pill-res   { background: #fff3ea; color: #c94e00; border: 1px solid #ffc78a; }
.pill-fin   { background: #eafbff; color: #2490a7; border: 1px solid #b3eaf5; }
.pill-sel   { background: #e6faf7; color: #0a7a65; border: 1px solid #9ee5d6; }

/* ── Pagination ───────────────────────────────────────────────────────── */
.mon-pager { display: flex; align-items: center; gap: 4px; padding: 10px 12px; border-top: 1px solid #f0f1f5; flex-shrink: 0; flex-wrap: wrap; }
.mon-pg { width: 30px; height: 30px; border-radius: 7px; border: 1.5px solid #dee2e6; background: #fff; cursor: pointer; font-size: .78rem; display: flex; align-items: center; justify-content: center; }
.mon-pg:hover:not(:disabled) { background: #eef0f9; border-color: #405189; color: #405189; }
.mon-pg:disabled { opacity: .4; cursor: not-allowed; }
.mon-pg-active { background: #405189 !important; border-color: #405189 !important; color: #fff !important; }
.mon-pg-dots { font-size: .75rem; color: #adb5bd; padding: 0 4px; }

/* ── Right panel ──────────────────────────────────────────────────────── */
.mon-right {
    background: #fff; border-radius: 13px; box-shadow: 0 1px 5px rgba(0,0,0,.05);
    overflow-y: auto; max-height: calc(100vh - 280px);
    display: flex; flex-direction: column;
}
.mon-placeholder { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #adb5bd; padding: 60px; }
.mon-ph-icon  { font-size: 50px; margin-bottom: 14px; opacity: .45; }
.mon-ph-title { font-size: .98rem; font-weight: 600; }
.mon-ph-sub   { font-size: .8rem; margin-top: 5px; text-align: center; }

/* Detail header */
.mon-det-hdr { padding: 16px 20px; background: #405189; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-shrink: 0; }
.mon-det-hdr-left { display: flex; align-items: flex-start; gap: 10px; }
.mon-btn-back { background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.3); border-radius: 8px; width: 32px; height: 32px; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.mon-det-title { font-size: 1rem; font-weight: 700; color: #fff; display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.mon-det-sub   { font-size: .77rem; color: rgba(255,255,255,.75); margin-top: 4px; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }

/* Pipeline */
.mon-pipeline { display: flex; align-items: center; padding: 14px 22px; background: #eef0f9; border-bottom: 1px solid #dde0ef; flex-shrink: 0; }
.mon-pipe-step { display: flex; align-items: center; position: relative; }
.mon-pipe-dot {
    width: 34px; height: 34px; border-radius: 50%; background: #dde0ef; color: #adb5bd;
    display: flex; align-items: center; justify-content: center; font-size: 16px;
    border: 2px solid #dde0ef; z-index: 1; flex-shrink: 0;
}
.mon-pipe-lbl  { font-size: .68rem; color: #6c757d; font-weight: 600; position: absolute; bottom: -18px; left: 50%; transform: translateX(-50%); white-space: nowrap; }
.mon-pipe-line { flex: 1; height: 2px; background: #dde0ef; min-width: 30px; margin: 0 4px; }
.mon-pipe-active .mon-pipe-dot { background: #405189; color: #fff; border-color: #405189; box-shadow: 0 0 0 4px rgba(64,81,137,.18); }
.mon-pipe-active .mon-pipe-lbl { color: #405189; }
.mon-pipe-done .mon-pipe-dot   { background: #0ab39c; color: #fff; border-color: #0ab39c; }
.mon-pipe-done .mon-pipe-lbl   { color: #0ab39c; }

/* Sections */
.mon-sections { flex: 1; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.mon-section  { border: 1.5px solid #e6e9f0; border-radius: 11px; overflow: hidden; }
.mon-sec-hdr  {
    padding: 11px 14px; cursor: pointer; display: flex; align-items: center; justify-content: space-between;
    font-size: .84rem; font-weight: 600; user-select: none; transition: background .15s;
}
.mon-sec-hdr:hover { filter: brightness(.97); }
.mon-sec-left { display: flex; align-items: center; gap: 8px; }
.mon-sec-badge { background: rgba(0,0,0,.1); border-radius: 20px; padding: 1px 8px; font-size: .7rem; font-weight: 700; }
.mon-sec-arrow { transition: transform .2s; }
.mon-sec-open  { transform: rotate(180deg); }

.mon-sec-belum_input         { background: #f1f3f5; color: #495057; }
.mon-sec-sedang_proses       { background: #fff8e6; color: #b08800; }
.mon-sec-menunggu_finalisasi { background: #eafbff; color: #2490a7; }
.mon-sec-selesai             { background: #e6faf7; color: #0a7a65; }

.mon-sec-body  { padding: 10px 12px; display: flex; flex-direction: column; gap: 10px; }
.mon-sec-empty { padding: 14px; text-align: center; font-size: .8rem; color: #adb5bd; display: flex; align-items: center; justify-content: center; gap: 6px; }

/* Sampel card */
.mon-card { border: 1.5px solid #e6e9f0; border-radius: 10px; overflow: hidden; }
.mon-card-hdr { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; padding: 10px 14px; background: #405189; }
.mon-card-ids { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.mon-card-sampel { font-size: .88rem; font-weight: 700; color: #fff; }
.mon-sub-sep  { font-size: .72rem; color: rgba(255,255,255,.7); }
.mon-card-sub { font-size: .72rem; color: rgba(255,255,255,.9); background: rgba(255,255,255,.18); border-radius: 4px; padding: 1px 6px; }
.mon-card-meta { display: flex; align-items: center; gap: 14px; padding: 8px 14px; background: #eef0f9; font-size: .75rem; color: #6c757d; flex-wrap: wrap; }
.mon-card-meta i { color: #405189; margin-right: 3px; }
.mon-fg-badge { background: #fff3ea; color: #c94e00; border-radius: 6px; padding: 1px 7px; font-size: .7rem; font-weight: 600; }
.mon-card-no-act { padding: 16px 14px; font-size: .8rem; color: #adb5bd; display: flex; align-items: center; gap: 8px; }

/* Activity blocks */
.mon-act-block  { border-top: 1px solid #f0f1f5; }
.mon-act-hdr    { display: flex; align-items: center; justify-content: space-between; padding: 9px 14px; }
.mon-act-title  { display: flex; align-items: center; gap: 6px; font-size: .8rem; font-weight: 600; color: #495057; }
.mon-act-badge  { border-radius: 5px; padding: 1px 6px; font-size: .62rem; font-weight: 800; }
.mon-badge-lckv { background: #f3e8ff; color: #7c3aed; }
.mon-badge-anl  { background: #eef0f9; color: #405189; }
.mon-badge-plt  { background: #e6faf7; color: #0a7a65; }
.mon-act-lckv .mon-act-hdr { background: #fdf8ff; }
.mon-act-anl  .mon-act-hdr { background: #f8f9ff; }
.mon-act-plt  .mon-act-hdr { background: #f0fdf9; }
.mon-btn-foto { display: flex; align-items: center; gap: 5px; background: #405189; color: #fff; border: none; border-radius: 7px; padding: 5px 12px; font-size: .74rem; font-weight: 600; cursor: pointer; transition: background .15s; }
.mon-btn-foto:hover { background: #2e3a6e; }
.mon-btn-foto:disabled { opacity: .6; cursor: wait; }

/* Activity table */
.mon-tbl-scroll { overflow-x: auto; }
.mon-act-tbl {
    width: 100%; border-collapse: collapse; font-size: .76rem; min-width: 800px;
    border: 1px solid #dee2e6;
}
.mon-act-tbl thead th {
    padding: 6px 10px; background: #f1f3f9; text-align: left;
    font-size: .67rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .4px; color: #6c757d;
    border: 1px solid #dee2e6; white-space: nowrap;
}
.mon-act-tbl tbody td {
    padding: 6px 10px; border: 1px solid #e6e9f0;
    vertical-align: middle; white-space: nowrap;
}
.mon-act-tbl tbody tr:hover td { background: #f8f9fc; }
.col-no { width: 36px; text-align: center; }
.mon-act-tbl td.col-no { color: #adb5bd; font-size: .68rem; }
.col-hasil { text-align: right; font-weight: 600; min-width: 80px; }
.col-status { text-align: center; width: 48px; font-size: .9rem; }
.col-sub { color: #6c757d; font-size: .72rem; }
.col-dash { color: #dee2e6; }
.col-pembanding { min-width: 120px; }
.td-pembanding { font-weight: 600; color: #0369a1; font-size: .76rem; }
.col-info { min-width: 90px; font-size: .72rem; }
.td-muted { color: #6c757d; }
.mon-avg-row td { background: #fff9e6 !important; border-top: 2px solid #ffd43b !important; }
.avg-label { text-align: right; font-weight: 700; font-size: .74rem; color: #856404; padding-right: 14px !important; }
.avg-val   { font-weight: 700; color: #856404; text-align: right; }
.mon-resamp-tag { background: #fff3ea; color: #c94e00; border: 1px solid #ffc78a; border-radius: 4px; padding: 1px 5px; font-size: .62rem; font-weight: 700; }
.res-ok      { color: #0a7a65; }
.res-ng      { color: #c92a2a; }
.res-pending { color: #adb5bd; }
.mon-pending { font-size: .72rem; font-weight: 400; color: #adb5bd; }

/* ── Skeleton ─────────────────────────────────────────────────────────── */
.mon-skeleton-wrap { padding: 10px; }
.mon-skeleton { height: 82px; border-radius: 10px; margin-bottom: 7px; background: linear-gradient(90deg,#f0f1f5 25%,#e8eaef 50%,#f0f1f5 75%); background-size: 400%; animation: shimmer 1.4s infinite; }
@keyframes shimmer { 0%{background-position:100% 0} 100%{background-position:-100% 0} }
.mon-detail-loading { padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.mon-det-skel { height: 64px; border-radius: 10px; background: linear-gradient(90deg,#f0f1f5 25%,#e8eaef 50%,#f0f1f5 75%); background-size: 400%; animation: shimmer 1.4s infinite; }
.mon-det-load-lbl { text-align: center; color: #adb5bd; font-size: .8rem; display: flex; align-items: center; justify-content: center; gap: 7px; padding: 6px 0; }
.mon-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#adb5bd; padding:40px; gap:6px; }
.mon-empty i { font-size: 40px; }

/* ── Foto Modal ───────────────────────────────────────────────────────── */
.mon-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.65); z-index: 9998; display: flex; align-items: center; justify-content: center; }
.mon-modal { background: #fff; border-radius: 14px; width: min(98vw, 1160px); max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.mon-modal-hdr { padding: 14px 18px; background: #405189; color: #fff; display: flex; align-items: center; justify-content: space-between; font-size: .88rem; flex-shrink: 0; }
.mon-modal-sub  { color: rgba(255,255,255,.75); font-size: .78rem; }
.mon-modal-close { background: rgba(255,255,255,.18); border: none; color: #fff; border-radius: 7px; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.mon-modal-body { overflow-y: auto; padding: 20px; flex: 1; }
.mon-foto-loading, .mon-foto-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; color: #adb5bd; gap: 10px; font-size: .88rem; }
.mon-foto-loading i { font-size: 30px; }
.mon-foto-empty i   { font-size: 38px; }
.mon-foto-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
@media(max-width: 700px) { .mon-foto-grid { grid-template-columns: repeat(2, 1fr); } }
.mon-polaroid { background: #fff; border-radius: 3px; padding: 10px 10px 0; box-shadow: 0 3px 10px rgba(0,0,0,.18), 0 1px 3px rgba(0,0,0,.1); transition: transform .2s, box-shadow .2s; cursor: pointer; }
.mon-polaroid:hover { transform: scale(1.04) rotate(-0.5deg); box-shadow: 0 8px 24px rgba(0,0,0,.22); }
.mon-polaroid-img-wrap { width: 100%; aspect-ratio: 1/1; overflow: hidden; background: #f0f1f5; border-radius: 1px; position: relative; }
.mon-polaroid-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mon-polaroid-overlay { position: absolute; inset: 0; background: rgba(64,81,137,.35); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .18s; color: #fff; font-size: 1.4rem; }
.mon-polaroid:hover .mon-polaroid-overlay { opacity: 1; }
.mon-polaroid-caption { font-size: .84rem; font-weight: 700; text-align: center; padding: 10px 8px 13px; color: #1e293b; line-height: 1.45; word-break: break-word; border-top: 2px solid #e8ecf4; margin-top: 1px; background: #fff; }
.mon-foto-err { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #adb5bd; font-size: 30px; background: #f0f1f5; }

/* ── Lightbox ─────────────────────────────────────────────────────────── */
.mon-lightbox { position: fixed; inset: 0; background: rgba(0,0,0,.92); z-index: 9999; display: flex; align-items: center; justify-content: center; }
.mon-lb-img   { max-width: 90vw; max-height: 85vh; border-radius: 10px; object-fit: contain; }
.mon-lb-close { position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,.15); border: none; color: #fff; border-radius: 8px; width: 36px; height: 36px; cursor: pointer; font-size: 18px; display: flex; align-items: center; justify-content: center; }
.mon-lb-nav   { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,.15); border: none; color: #fff; border-radius: 8px; width: 42px; height: 42px; cursor: pointer; font-size: 22px; display: flex; align-items: center; justify-content: center; }
.mon-lb-nav:disabled { opacity: .3; cursor: not-allowed; }
.mon-lb-prev  { left: 16px; }
.mon-lb-next  { right: 16px; }
.mon-lb-counter { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.5); color: #fff; border-radius: 20px; padding: 4px 14px; font-size: .8rem; }
.mon-lb-caption { position: absolute; bottom: 58px; left: 50%; transform: translateX(-50%); background: rgba(255,255,255,.13); color: #fff; border-radius: 8px; padding: 8px 22px; font-size: .92rem; font-weight: 600; max-width: 80vw; text-align: center; word-break: break-word; }

/* ── Modal transition ─────────────────────────────────────────────────── */
.mon-modal-fade-enter-active, .mon-modal-fade-leave-active { transition: opacity .2s; }
.mon-modal-fade-enter-from, .mon-modal-fade-leave-to { opacity: 0; }

/* ── Spin ─────────────────────────────────────────────────────────────── */
.mon-spin { animation: spin .8s linear infinite; display: inline-block; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Utilities ────────────────────────────────────────────────────────── */
.me-2 { margin-right: 6px; }
.ms-2 { margin-left: 6px; }

/* ── Analisa group (per-jenis-analisa block) ──────────────────────────── */
.mon-analisa-group { border-top: 1px solid #f0f1f5; }
.mon-analisa-lbl {
    display: flex; align-items: center; gap: 7px;
    padding: 6px 14px; background: #f8f9fa;
    font-size: .74rem; font-weight: 600; color: #495057;
}
.mon-kode-tag {
    display: inline-block; background: #405189; color: #fff;
    border-radius: 4px; padding: 1px 7px; font-size: .62rem; font-weight: 800;
}
.col-param     { min-width: 100px; }
.col-param-val { text-align: right; font-variant-numeric: tabular-nums; }
.col-faktur    { font-size: .72rem; color: #495057; font-family: monospace; }

/* ── Responsive ───────────────────────────────────────────────────────── */
@media (max-width: 860px) {
    .mon-body { grid-template-columns: 1fr; }
    .mon-right { display: none; }
    .mon-right-show { display: flex; }
    .mon-left-hidden { display: none; }
    .mon-kpi { flex: 1 1 calc(50% - 12px); }
    .mon-topbar, .mon-filters, .mon-kpi-row, .mon-body { padding-left: 14px; padding-right: 14px; }
}
@media (max-width: 520px) {
    .mon-kpi { flex: 1 1 100%; }
    .mon-pipeline { gap: 0; }
    .mon-pipe-lbl { display: none; }
}
</style>
