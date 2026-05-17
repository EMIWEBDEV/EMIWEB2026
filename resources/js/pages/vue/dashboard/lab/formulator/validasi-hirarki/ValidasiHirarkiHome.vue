<template>
    <div class="vld-root">
        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- TOP BAR                                                        -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="vld-topbar">
            <div class="vld-topbar-left">
                <i class="ri-shield-check-line vld-topbar-icon"></i>
                <div>
                    <span class="vld-topbar-title">Pra-Finalisasi</span>
                    <span class="vld-topbar-sub">Validasi tahapan sampel sebelum finalisasi</span>
                </div>
            </div>
            <div class="vld-topbar-right">
                <div class="vld-stat" v-if="stats.total > 0">
                    <span class="vld-stat-num">{{ stats.total }}</span>
                    <span class="vld-stat-lbl">Total</span>
                </div>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                    <i class="ri-time-line me-1"></i>Menunggu Validasi
                </span>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- MAIN LAYOUT                                                     -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="vld-body">
            <!-- ─────────────────────────────── LEFT PANEL ─────────────── -->
            <div class="vld-left" :class="{ 'vld-hidden-mobile': detailVisible && isMobile }">
                <!-- Filter toolbar -->
                <div class="vld-filter-bar">
                    <div class="vld-search-wrap">
                        <i class="ri-search-line vld-search-icon"></i>
                        <input
                            type="text"
                            class="vld-search-input"
                            placeholder="Cari No. Sampel..."
                            v-model="searchQuery"
                        />
                    </div>
                </div>

                <!-- List area -->
                <div class="vld-list">
                    <div v-if="loading.list" class="p-3">
                        <div v-for="i in 7" :key="i" class="vld-skeleton mb-2"></div>
                    </div>

                    <div v-else-if="listData.length === 0" class="vld-empty-list">
                        <i class="ri-inbox-2-line"></i>
                        <p>{{ emptyMessage }}</p>
                        <button class="btn btn-sm btn-soft-primary" @click="resetFiltersAndFetch">
                            <i class="ri-refresh-line me-1"></i>Reset
                        </button>
                    </div>

                    <div v-else>
                        <button
                            v-for="(item, idx) in listData"
                            :key="idx"
                            @click="selectItem(item)"
                            class="vld-item"
                            :class="{
                                'vld-item--active': isSelected(item),
                                'vld-item--lolos': isSelesaiSemua(item),
                                'vld-item--tidak': hasDitolak(item),
                                'vld-item--warn': !isSelesaiSemua(item) && !hasDitolak(item),
                            }"
                        >
                            <div class="vld-item-accent"></div>
                            <div class="vld-item-body">
                                <div class="vld-item-top">
                                    <span class="vld-item-title">{{ item.No_Po_Sampel }}</span>
                                    <span
                                        class="vld-badge"
                                        :class="isSelesaiSemua(item) ? 'vld-badge--success' : hasDitolak(item) ? 'vld-badge--danger' : 'vld-badge--orange'"
                                    >
                                        {{ isSelesaiSemua(item) ? 'Selesai' : hasDitolak(item) ? 'Ditolak' : 'Proses' }}
                                    </span>
                                </div>
                                <div class="vld-item-meta">
                                    <span class="vld-chip" :class="getChipClass(item.status_lock_view)">
                                        <i class="ri-lock-line"></i> Lock
                                    </span>
                                    <span class="vld-chip" :class="getChipClass(item.status_analisa_lab)">
                                        <i class="ri-flask-line"></i> Lab
                                    </span>
                                    <span class="vld-chip" :class="getChipClass(item.status_palatabilitas)">
                                        <i class="ri-heart-pulse-line"></i> Palat
                                    </span>
                                </div>
                            </div>
                            <i class="ri-arrow-right-s-line vld-item-arrow"></i>
                        </button>
                    </div>
                </div>

                <!-- Pagination footer -->
                <div class="vld-list-footer" v-if="pagination.totalPage > 1">
                    <span class="vld-page-info">{{ listData.length }} / {{ pagination.totalData }}</span>
                    <div class="vld-page-btns">
                        <button class="vld-page-btn" :disabled="pagination.page === 1" @click="changePage(pagination.page - 1)">
                            <i class="ri-arrow-left-s-line"></i>
                        </button>
                        <span class="vld-page-current">{{ pagination.page }} / {{ pagination.totalPage }}</span>
                        <button class="vld-page-btn" :disabled="pagination.page === pagination.totalPage" @click="changePage(pagination.page + 1)">
                            <i class="ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ─────────────────────────────── RIGHT PANEL ────────────── -->
            <div class="vld-right" :class="{ 'vld-hidden-mobile': !detailVisible && isMobile }">
                <!-- Mobile back -->
                <div v-if="isMobile && detailVisible" class="vld-mobile-back">
                    <button class="btn btn-sm btn-soft-secondary" @click="detailVisible = false">
                        <i class="ri-arrow-left-line me-1"></i>Daftar
                    </button>
                </div>

                <!-- ── EMPTY STATE ─────────────────────────────────────── -->
                <div v-if="!selectedItem" class="vld-detail-empty">
                    <div class="vld-detail-empty-inner">
                        <div class="vld-empty-icon-wrap">
                            <i class="ri-shield-check-line"></i>
                        </div>
                        <h6>Pilih sampel untuk validasi</h6>
                        <p>Klik salah satu item dari daftar di sebelah kiri untuk melihat detail tahapan validasi dan melakukan konfirmasi.</p>
                    </div>
                </div>

                <!-- ── DETAIL CONTENT ──────────────────────────────────── -->
                <template v-else>
                    <!-- Sticky sample header -->
                    <div class="vld-detail-header">
                        <div class="vld-dh-main">
                            <div class="vld-dh-icon" :class="isSelesaiSemua(selectedItem) ? 'vld-dh-icon--success' : 'vld-dh-icon--info'">
                                <i :class="isSelesaiSemua(selectedItem) ? 'ri-checkbox-circle-line' : 'ri-shield-check-line'"></i>
                            </div>
                            <div>
                                <div class="vld-dh-title">{{ selectedItem.No_Po_Sampel }}</div>
                                <div class="vld-dh-sub">Validasi Pra-Finalisasi</div>
                                <div class="vld-dh-badges">
                                    <span class="vld-badge vld-badge--blue">
                                        <i class="ri-barcode-line me-1"></i>{{ selectedItem.No_Po_Sampel }}
                                    </span>
                                    <span class="vld-badge" :class="getBadgeClass(selectedItem.status_lock_view)">
                                        <i class="ri-lock-line me-1"></i>Lock: {{ selectedItem.status_lock_view }}
                                    </span>
                                    <span class="vld-badge" :class="getBadgeClass(selectedItem.status_analisa_lab)">
                                        <i class="ri-flask-line me-1"></i>Lab: {{ selectedItem.status_analisa_lab }}
                                    </span>
                                    <span class="vld-badge" :class="getBadgeClass(selectedItem.status_palatabilitas)">
                                        <i class="ri-heart-pulse-line me-1"></i>Palat: {{ selectedItem.status_palatabilitas }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step tabs bar -->
                    <div class="vld-subpo-bar" v-if="listKlasifikasi.length > 0">
                        <span class="vld-subpo-label"><i class="ri-git-branch-line me-1"></i>Tahapan</span>
                        <div v-if="loading.detail" class="vld-subpo-loading">
                            <span class="spinner-border spinner-border-sm text-primary"></span>
                            <span class="ms-2 text-muted" style="font-size: 12px">Memuat...</span>
                        </div>
                        <div v-else class="vld-subpo-tabs">
                            <button
                                v-for="(step, si) in listKlasifikasi"
                                :key="si"
                                @click="activeStep = si"
                                class="vld-subpo-tab"
                                :class="{
                                    'vld-subpo-tab--active': activeStep === si,
                                    'vld-subpo-tab--ok': getStepStatus(step) === 'DISETUJUI',
                                    'vld-subpo-tab--fail': getStepStatus(step) === 'DITOLAK',
                                    'vld-subpo-tab--locked': getStepStatus(step) === 'TERKUNCI',
                                }"
                            >
                                <i :class="getStepIcon(step)" class="me-1"></i>
                                {{ step.Nama_Aktivitas }}
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable detail body -->
                    <div class="vld-detail-body">
                        <!-- Loading -->
                        <div v-if="loading.detail" class="vld-loading-state">
                            <div class="spinner-border text-primary"></div>
                            <p class="mt-3 text-muted small">Memuat data validasi...</p>
                        </div>

                        <!-- No detail data yet -->
                        <div v-else-if="detailValidasiData.length === 0" class="vld-loading-state">
                            <i class="ri-file-unknow-line fs-1 text-muted"></i>
                            <p class="mt-2 text-muted small">Tidak ada data validasi.</p>
                        </div>

                        <!-- Step content -->
                        <template v-else-if="currentStepData">
                            <!-- Step status alert -->
                            <div v-if="currentStepData.status_step === 'DISETUJUI'" class="vld-alert-ok mb-3">
                                <i class="ri-checkbox-circle-line me-2"></i>
                                <div><strong>Tahapan ini telah Disetujui</strong></div>
                            </div>
                            <div v-else-if="currentStepData.status_step === 'DITOLAK'" class="vld-alert-no mb-3">
                                <i class="ri-close-circle-line me-2"></i>
                                <div>
                                    <strong>Tahapan ini telah Ditolak</strong>
                                    <span v-if="currentStepData.alasan_tolak" class="d-block text-muted" style="font-size: 11px">Alasan: {{ currentStepData.alasan_tolak }}</span>
                                </div>
                            </div>
                            <div v-else-if="currentStepData.status_step === 'TERKUNCI'" class="vld-alert-warn mb-3">
                                <i class="ri-lock-line me-2"></i>
                                <div>
                                    <strong>Tahap ini belum dapat diakses</strong>
                                    <span class="d-block text-muted" style="font-size: 11px">Selesaikan tahapan sebelumnya terlebih dahulu.</span>
                                </div>
                            </div>

                            <!-- Pending analisa warning -->
                            <div v-if="currentStepData.pending_analisa && currentStepData.pending_analisa.length > 0" class="vld-alert-warn mb-3">
                                <i class="ri-alert-line me-2 flex-shrink-0"></i>
                                <div style="flex: 1">
                                    <strong>{{ currentStepData.pending_analisa.length }} analisa belum selesai</strong>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <span
                                            v-for="(nama, ni) in currentStepData.pending_analisa"
                                            :key="ni"
                                            class="badge bg-warning-subtle text-warning border border-warning-subtle"
                                            style="font-size: 10px; font-weight: 500"
                                        >{{ nama }}</span>
                                    </div>
                                    <label class="d-flex align-items-center gap-2 mt-2 mb-0" style="font-size: 12px; cursor: pointer;">
                                        <input type="checkbox" v-model="acknowledgementChecked" />
                                        Saya memahami dan tetap ingin melanjutkan
                                    </label>
                                </div>
                            </div>

                            <!-- Mini stats -->
                            <div class="vld-mini-stats mb-3" v-if="currentStepData.data_analisa && currentStepData.data_analisa.length > 0">
                                <div class="vld-ms-item">
                                    <span class="vld-ms-val">{{ currentStepData.data_analisa.length }}</span>
                                    <span class="vld-ms-lbl">Total</span>
                                </div>
                                <div class="vld-ms-item vld-ms-item--success">
                                    <span class="vld-ms-val">{{ currentStepData.data_analisa.filter(d => d.Flag_Layak === 'Y').length }}</span>
                                    <span class="vld-ms-lbl">Layak</span>
                                </div>
                                <div class="vld-ms-item vld-ms-item--danger">
                                    <span class="vld-ms-val">{{ currentStepData.data_analisa.filter(d => d.Flag_Layak === 'T' || d.Flag_Layak === 'N').length }}</span>
                                    <span class="vld-ms-lbl">Tidak Layak</span>
                                </div>
                            </div>

                            <!-- ANL step: grouped by Jenis_Analisa -->
                            <template v-if="currentStepData.Kode_Aktivitas_Lab === 'ANL' && groupedANLData.length > 0">
                                <div v-for="(group, gi) in groupedANLData" :key="gi" class="vld-section">
                                    <div class="vld-section-hd">
                                        <span>
                                            <i class="ri-flask-line me-2 text-primary"></i>{{ group.Jenis_Analisa }}
                                        </span>
                                        <span :class="group.is_layak ? 'badge bg-success-subtle text-success' : 'badge bg-danger-subtle text-danger'">
                                            {{ group.is_layak ? 'Layak' : 'Tidak Layak' }}
                                        </span>
                                    </div>
                                    <div class="vld-section-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0 vld-table text-center text-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 36px">#</th>
                                                        <th>No Sub Sampel</th>
                                                        <th>No PO</th>
                                                        <th>Split PO</th>
                                                        <th>Batch</th>
                                                        <th>Tanggal</th>
                                                        <th v-for="(header, hIdx) in group.paramHeaders" :key="'h-' + hIdx">{{ header }}</th>
                                                        <th>Hasil / Ket</th>
                                                        <th>Status</th>
                                                        <th>Foto</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(item, idx) in group.items"
                                                        :key="idx"
                                                        :class="{ 'table-success': item.Flag_Layak === 'Y', 'table-danger': item.Flag_Layak === 'T' || item.Flag_Layak === 'N' }"
                                                    >
                                                        <td class="text-center fw-semibold">{{ idx + 1 }}</td>
                                                        <td class="fw-medium text-dark">
                                                            {{ item.No_Fak_Sub_Po && item.No_Fak_Sub_Po !== item.No_Po_Sampel ? item.No_Fak_Sub_Po : item.No_Po_Sampel }}
                                                        </td>
                                                        <td>{{ item.No_Po }}</td>
                                                        <td>{{ item.No_Split_Po }}</td>
                                                        <td>Batch {{ item.No_Batch }}</td>
                                                        <td>{{ formatTanggal(item.Tanggal_Registrasi) }}</td>
                                                        <td v-for="(param, pIdx) in item.parameters" :key="'p-' + pIdx">{{ param.Hasil_Analisa }}</td>
                                                        <td class="fw-bolder">
                                                            <span v-if="item.Flag_Perhitungan !== 'Y' && item.Keterangan_Kriteria" class="text-primary">{{ item.Keterangan_Kriteria }}</span>
                                                            <span v-else>{{ item.Hasil ?? '—' }}</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success" v-if="item.Flag_Approval === 'Y'">Disetujui</span>
                                                            <span class="badge bg-danger" v-else-if="item.Flag_Approval === 'T'">Ditolak</span>
                                                            <span class="badge bg-warning text-dark" v-else>Menunggu</span>
                                                        </td>
                                                        <td>
                                                            <template v-if="item.Flag_Foto === 'Y'">
                                                                <button v-if="item.File_Url" @click="lihatFoto(item.File_Url)" class="btn btn-sm btn-outline-info rounded-pill px-2">
                                                                    <i class="ri-image-line me-1"></i>Lihat
                                                                </button>
                                                                <span v-else class="text-danger small fst-italic">Belum</span>
                                                            </template>
                                                            <span v-else class="text-muted small">—</span>
                                                        </td>
                                                    </tr>
                                                    <tr v-if="group.Flag_Perhitungan === 'Y'" class="vld-row--avg">
                                                        <td :colspan="6 + group.paramHeaders.length" class="text-end fw-bold pe-3">Rata-Rata</td>
                                                        <td class="fw-bold text-primary">{{ group.RataRataHasil }}</td>
                                                        <td colspan="3"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Non-ANL step: simple table -->
                            <template v-else-if="currentStepData.data_analisa && currentStepData.data_analisa.length > 0">
                                <div class="vld-section">
                                    <div class="vld-section-hd">
                                        <span><i class="ri-table-line me-2 text-primary"></i>Data Analisa</span>
                                        <span class="badge bg-primary-subtle text-primary">{{ currentStepData.data_analisa.length }} baris</span>
                                    </div>
                                    <div class="vld-section-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0 vld-table text-center text-nowrap">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 36px">#</th>
                                                        <th>No Sub Sampel</th>
                                                        <th>Jenis Analisa</th>
                                                        <th>Hasil Akhir</th>
                                                        <th>Status</th>
                                                        <th>Foto</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(item, idx) in currentStepData.data_analisa"
                                                        :key="idx"
                                                        :class="{ 'table-success': item.Flag_Layak === 'Y', 'table-danger': item.Flag_Layak === 'T' || item.Flag_Layak === 'N' }"
                                                    >
                                                        <td class="text-center fw-semibold">{{ idx + 1 }}</td>
                                                        <td class="fw-medium">
                                                            {{ item.No_Fak_Sub_Po && item.No_Fak_Sub_Po !== item.No_Po_Sampel ? item.No_Fak_Sub_Po : item.No_Po_Sampel }}
                                                        </td>
                                                        <td>{{ item.Jenis_Analisa || '—' }}</td>
                                                        <td class="fw-bolder">
                                                            <span v-if="item.Flag_Perhitungan !== 'Y' && item.Keterangan_Kriteria" class="text-primary">{{ item.Keterangan_Kriteria }}</span>
                                                            <span v-else>{{ item.Hasil ?? '—' }}</span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success" v-if="item.Flag_Approval === 'Y'">Disetujui</span>
                                                            <span class="badge bg-danger" v-else-if="item.Flag_Approval === 'T'">Ditolak</span>
                                                            <span class="badge bg-warning text-dark" v-else>Menunggu</span>
                                                        </td>
                                                        <td>
                                                            <template v-if="item.Flag_Foto === 'Y'">
                                                                <button v-if="item.File_Url" @click="lihatFoto(item.File_Url)" class="btn btn-sm btn-outline-info rounded-pill px-2">
                                                                    <i class="ri-image-line me-1"></i>Lihat
                                                                </button>
                                                                <span v-else class="text-danger small fst-italic">Belum</span>
                                                            </template>
                                                            <span v-else class="text-muted small">—</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Pending analisa table -->
                            <div v-if="currentStepData.pending_analisa && currentStepData.pending_analisa.length > 0" class="vld-section">
                                <div class="vld-section-hd">
                                    <span><i class="ri-time-line me-2 text-warning"></i>Analisa Belum Selesai</span>
                                    <span class="badge bg-warning-subtle text-warning">{{ currentStepData.pending_analisa.length }} item</span>
                                </div>
                                <div class="vld-section-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0 vld-table text-center text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th style="width: 36px">#</th>
                                                    <th>Jenis Analisa</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(nama, idx) in currentStepData.pending_analisa" :key="idx" class="table-warning">
                                                    <td class="fw-semibold">{{ idx + 1 }}</td>
                                                    <td class="text-start fw-medium">{{ nama }}</td>
                                                    <td><span class="badge bg-warning text-dark">Belum Selesai</span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- No step data -->
                        <div v-else-if="!loading.detail" class="vld-loading-state">
                            <i class="ri-file-unknow-line fs-1 text-muted"></i>
                            <p class="mt-2 text-muted small">Tidak ada data untuk tahapan ini.</p>
                        </div>
                    </div>

                    <!-- Sticky action bar -->
                    <div class="vld-action-bar">
                        <div class="vld-action-info" v-if="currentStepData">
                            <i class="ri-information-line text-muted me-1"></i>
                            <span class="text-muted" style="font-size: 11px">
                                {{ currentStepData.Nama_Aktivitas }} — {{ currentStepData.status_step }}
                            </span>
                        </div>
                        <div class="vld-action-info" v-else></div>
                        <div class="d-flex gap-2 flex-wrap">
                            <!-- Cancel whole sample -->
                            <button
                                class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#offcanvasBatal"
                                @click="openBatal"
                            >
                                <i class="ri-close-line me-1"></i>Batalkan
                            </button>

                            <!-- Finalisasi when all steps approved -->
                            <button
                                v-if="allStepsApproved"
                                class="btn btn-sm btn-primary"
                                @click="doFinalisasi(selectedItem)"
                                :disabled="loading.action"
                            >
                                <span v-if="loading.action" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="ri-check-double-line me-1"></i>Finalisasi
                            </button>

                            <!-- Next step -->
                            <button
                                v-if="hasNextStepAvailable"
                                class="btn btn-sm btn-outline-primary"
                                @click="activeStep++"
                            >
                                Lanjut <i class="ri-arrow-right-line ms-1"></i>
                            </button>

                            <!-- Reject + Approve for waiting step -->
                            <template v-if="currentStepData && currentStepData.status_step === 'MENUNGGU VALIDASI'">
                                <button
                                    class="btn btn-sm btn-danger"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#offcanvasTolak"
                                    @click="openTolak"
                                    :disabled="loading.action || pendingBlocked"
                                >
                                    <i class="ri-close-circle-line me-1"></i>Tolak
                                </button>
                                <button
                                    class="btn btn-sm btn-success"
                                    @click="setujuiValidasi"
                                    :disabled="loading.action || pendingBlocked"
                                >
                                    <span v-if="loading.action" class="spinner-border spinner-border-sm me-1"></span>
                                    <i v-else class="ri-check-double-line me-1"></i>Setujui
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- OFFCANVAS: TOLAK                                               -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasTolak">
            <div class="offcanvas-header border-bottom">
                <h5 class="mb-0 fw-semibold fs-6">
                    <i class="ri-close-circle-line me-2 text-danger"></i>Alasan Penolakan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" @click="formTolak.alasan = ''"></button>
            </div>
            <div class="offcanvas-body">
                <div class="mb-3">
                    <p class="text-muted small">
                        Berikan alasan penolakan untuk tahapan
                        <strong>{{ currentStepData?.Nama_Aktivitas }}</strong>
                        sampel <strong>{{ selectedItem?.No_Po_Sampel }}</strong>.
                    </p>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Alasan Penolakan</label>
                    <textarea
                        class="form-control"
                        rows="4"
                        v-model="formTolak.alasan"
                        placeholder="Masukkan alasan minimal 8 karakter..."
                    ></textarea>
                    <div class="form-text text-danger" v-if="formTolak.alasan.length > 0 && formTolak.alasan.length < 8">
                        Minimal 8 karakter (Sekarang: {{ formTolak.alasan.length }})
                    </div>
                </div>
                <div class="d-grid">
                    <button
                        type="button"
                        class="btn btn-danger"
                        :disabled="formTolak.alasan.length < 8 || loading.action"
                        @click="submitTolak"
                    >
                        <span v-if="loading.action" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="ri-send-plane-line me-1"></i>
                        Kirim Penolakan
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- OFFCANVAS: BATALKAN SAMPEL                                     -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasBatal">
            <div class="offcanvas-header border-bottom">
                <h5 class="mb-0 fw-semibold fs-6">
                    <i class="ri-error-warning-line me-2 text-danger"></i>Batalkan Sampel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" @click="formBatal.alasan = ''"></button>
            </div>
            <div class="offcanvas-body">
                <div class="mb-3">
                    <div class="alert alert-danger border-0 border-start border-danger border-3 py-2">
                        <small>Pembatalan bersifat <strong>permanen</strong> dan akan membatalkan seluruh proses validasi untuk sampel ini.</small>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold small">Alasan Pembatalan</label>
                    <textarea
                        class="form-control"
                        rows="4"
                        v-model="formBatal.alasan"
                        placeholder="Masukkan alasan minimal 8 karakter..."
                    ></textarea>
                    <div class="form-text text-danger" v-if="formBatal.alasan.length > 0 && formBatal.alasan.length < 8">
                        Minimal 8 karakter (Sekarang: {{ formBatal.alasan.length }})
                    </div>
                </div>
                <div class="d-grid">
                    <button
                        type="button"
                        class="btn btn-danger"
                        :disabled="formBatal.alasan.length < 8 || loading.action"
                        @click="submitBatal"
                    >
                        <span v-if="loading.action" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="ri-alert-line me-1"></i>
                        Batalkan Sampel
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { debounce } from "lodash";

export default {
    data() {
        return {
            listData: [],
            searchQuery: "",
            pagination: { page: 1, limit: 12, totalPage: 0, totalData: 0 },
            loading: { list: false, detail: false, action: false },

            selectedItem: null,
            detailVisible: false,
            isMobile: window.innerWidth < 992,

            listKlasifikasi: [],
            detailValidasiData: [],
            activeStep: 0,
            templateDataMap: {},
            acknowledgementChecked: false,

            formTolak: { alasan: "" },
            formBatal: { alasan: "" },
        };
    },

    computed: {
        stats() {
            return { total: this.pagination.totalData };
        },

        emptyMessage() {
            return this.searchQuery
                ? "Tidak ada data sesuai pencarian."
                : "Belum ada data menunggu validasi.";
        },

        currentStepData() {
            if (!this.detailValidasiData.length || !this.listKlasifikasi.length) return null;
            const idx = Math.min(this.activeStep, this.listKlasifikasi.length - 1);
            const kode = this.listKlasifikasi[idx].Kode_Aktivitas_Lab;
            return this.detailValidasiData.find(s => s.Kode_Aktivitas_Lab === kode) || null;
        },

        groupedANLData() {
            if (!this.currentStepData || this.currentStepData.Kode_Aktivitas_Lab !== "ANL") return [];
            const groups = {};
            (this.currentStepData.data_analisa || []).forEach(item => {
                if (!groups[item.Jenis_Analisa]) {
                    groups[item.Jenis_Analisa] = {
                        Jenis_Analisa: item.Jenis_Analisa,
                        Flag_Perhitungan: item.Flag_Perhitungan,
                        Digit_Desimal: item.Digit_Desimal !== null ? parseInt(item.Digit_Desimal) : 2,
                        items: [],
                        paramHeaders: [],
                        is_layak: true,
                    };
                }
                const g = groups[item.Jenis_Analisa];
                if (item.Id_Jenis_Analisa_Hash && this.templateDataMap[item.Id_Jenis_Analisa_Hash]) {
                    if (g.paramHeaders.length === 0) {
                        g.paramHeaders = this.templateDataMap[item.Id_Jenis_Analisa_Hash].map(p => p.nama_parameter);
                    }
                } else if (g.paramHeaders.length === 0 && Array.isArray(item.parameters)) {
                    g.paramHeaders = item.parameters.map((_, i) => `Param ${i + 1}`);
                }
                g.items.push(item);
                if (item.Flag_Layak === "T" || item.Flag_Layak === "N") g.is_layak = false;
            });
            return Object.values(groups).map(group => {
                if (group.Flag_Perhitungan === "Y") {
                    let total = 0, count = 0;
                    group.items.forEach(i => {
                        const v = parseFloat(i.Hasil);
                        if (!isNaN(v)) { total += v; count++; }
                    });
                    group.RataRataHasil = count > 0 ? (total / count).toFixed(group.Digit_Desimal) : "—";
                } else {
                    group.RataRataHasil = null;
                }
                return group;
            });
        },

        hasNextStepAvailable() {
            if (!this.currentStepData) return false;
            if (this.currentStepData.status_step === "MENUNGGU VALIDASI") return false;
            const nextIdx = this.activeStep + 1;
            if (nextIdx >= this.listKlasifikasi.length) return false;
            const nextKode = this.listKlasifikasi[nextIdx].Kode_Aktivitas_Lab;
            const next = this.detailValidasiData.find(s => s.Kode_Aktivitas_Lab === nextKode);
            return next && next.status_step !== "TERKUNCI";
        },

        allStepsApproved() {
            return (
                this.detailValidasiData.length > 0 &&
                this.detailValidasiData.every(s => s.status_step === "DISETUJUI")
            );
        },

        pendingBlocked() {
            if (!this.currentStepData) return false;
            return !!(
                this.currentStepData.pending_analisa &&
                this.currentStepData.pending_analisa.length > 0 &&
                !this.acknowledgementChecked
            );
        },
    },

    watch: {
        searchQuery() { this.debouncedFetch(); },
        activeStep() { this.acknowledgementChecked = false; },
    },

    methods: {
        isSelected(item) {
            return this.selectedItem && this.selectedItem.No_Po_Sampel === item.No_Po_Sampel;
        },

        isSelesaiSemua(item) {
            return (
                item.status_lock_view === "DISETUJUI" &&
                item.status_analisa_lab === "DISETUJUI" &&
                item.status_palatabilitas === "DISETUJUI"
            );
        },

        hasDitolak(item) {
            return (
                item.status_lock_view === "DITOLAK" ||
                item.status_analisa_lab === "DITOLAK" ||
                item.status_palatabilitas === "DITOLAK"
            );
        },

        getChipClass(status) {
            if (status === "DISETUJUI") return "vld-chip--success";
            if (status === "DITOLAK") return "vld-chip--danger";
            if (status === "TIDAK ADA") return "vld-chip--gray";
            return "vld-chip--orange";
        },

        getBadgeClass(status) {
            if (status === "DISETUJUI") return "vld-badge--success";
            if (status === "DITOLAK") return "vld-badge--danger";
            if (status === "TIDAK ADA") return "vld-badge--gray";
            return "vld-badge--orange";
        },

        getStepStatus(step) {
            const found = this.detailValidasiData.find(s => s.Kode_Aktivitas_Lab === step.Kode_Aktivitas_Lab);
            return found ? found.status_step : "TERKUNCI";
        },

        getStepIcon(step) {
            const status = this.getStepStatus(step);
            if (status === "DISETUJUI") return "ri-checkbox-circle-fill";
            if (status === "DITOLAK") return "ri-close-circle-fill";
            if (status === "TERKUNCI") return "ri-lock-fill";
            return "ri-time-fill";
        },

        async fetchList(page = 1) {
            this.loading.list = true;
            try {
                const res = await axios.get("/api/v1/validasi/pra-finalisasi/current-home", {
                    params: { page, limit: this.pagination.limit, search: this.searchQuery },
                });
                if (res.data?.result) {
                    this.listData = res.data.result;
                    const pg = res.data.pagination;
                    this.pagination = {
                        page: pg.current_page,
                        totalPage: pg.total_pages,
                        totalData: pg.total,
                        limit: pg.per_page,
                    };
                } else {
                    this.listData = [];
                }
            } catch {
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
            this.fetchList(1);
        },

        async selectItem(item) {
            this.selectedItem = item;
            this.detailVisible = true;
            this.activeStep = 0;
            this.detailValidasiData = [];
            this.acknowledgementChecked = false;
            await this.fetchDetail(item.No_Po_Sampel);
        },

        async fetchDetail(noSampel) {
            this.loading.detail = true;
            try {
                const res = await axios.get(`/api/v1/validasi/pra-finalisasi/detail/by/${noSampel}`);
                if (res.status === 200 && res.data?.result?.steps) {
                    this.detailValidasiData = res.data.result.steps;

                    const anlStep = this.detailValidasiData.find(s => s.Kode_Aktivitas_Lab === "ANL");
                    if (anlStep && anlStep.data_analisa) {
                        const hashes = [...new Set(anlStep.data_analisa.map(d => d.Id_Jenis_Analisa_Hash).filter(Boolean))];
                        hashes.forEach(h => this.fetchTemplateParameter(h));
                    }

                    const waitingIdx = this.detailValidasiData.findIndex(s => s.status_step === "MENUNGGU VALIDASI");
                    if (waitingIdx !== -1) {
                        this.activeStep = waitingIdx;
                    } else if (this.detailValidasiData.every(s => s.status_step === "DISETUJUI")) {
                        this.activeStep = Math.max(0, this.listKlasifikasi.length - 1);
                    } else {
                        const firstNotOk = this.detailValidasiData.findIndex(s => s.status_step !== "DISETUJUI");
                        this.activeStep = firstNotOk !== -1 ? firstNotOk : 0;
                    }
                }
            } catch {
                this.detailValidasiData = [];
            } finally {
                this.loading.detail = false;
            }
        },

        async fetchTemplateParameter(idHash) {
            if (!idHash || this.templateDataMap[idHash]) return;
            try {
                const res = await axios.get(`/fetch/lab/lama/${idHash}/parameter-perhitungan-old`);
                if (res.data?.success && res.data?.result) {
                    this.templateDataMap[idHash] = res.data.result.parameter || [];
                }
            } catch {}
        },

        async fetchKlasifikasiAktivitas() {
            try {
                const res = await axios.get("/api/v1/validasi/pra-finalisasi/options/klasifikasi-lab");
                if (res.status === 200) this.listKlasifikasi = res.data.result;
            } catch {}
        },

        async setujuiValidasi() {
            if (!this.currentStepData) return;
            this.loading.action = true;
            try {
                const payload = {
                    No_Po_Sampel: this.selectedItem.No_Po_Sampel,
                    Kode_Aktivitas_Lab: this.currentStepData.Kode_Aktivitas_Lab,
                    Status_Action: "setuju",
                    Force_Submit: this.acknowledgementChecked,
                    Items: (this.currentStepData.data_analisa || []).map(item => ({
                        No_Fak_Sub_Po: item.No_Fak_Sub_Po,
                    })),
                };
                const res = await axios.post("/api/v1/validasi/pra-finalisasi/store-hirarki", payload);
                if (res.status === 200) {
                    Swal.fire({ icon: "success", title: "Berhasil", text: "Tahap berhasil disetujui.", timer: 1500, showConfirmButton: false });
                    this.fetchDetail(this.selectedItem.No_Po_Sampel);
                    this.fetchList(this.pagination.page);
                }
            } catch (e) {
                Swal.fire("Gagal!", e.response?.data?.message || "Gagal menyetujui.", "error");
            } finally {
                this.loading.action = false;
            }
        },

        openTolak() { this.formTolak.alasan = ""; },
        openBatal() { this.formBatal.alasan = ""; },

        async submitTolak() {
            this.loading.action = true;
            try {
                const payload = {
                    No_Po_Sampel: this.selectedItem.No_Po_Sampel,
                    Kode_Aktivitas_Lab: this.currentStepData.Kode_Aktivitas_Lab,
                    Status_Action: "tolak",
                    Alasan: this.formTolak.alasan,
                    Force_Submit: this.acknowledgementChecked,
                    Items: (this.currentStepData.data_analisa || []).map(item => ({
                        No_Fak_Sub_Po: item.No_Fak_Sub_Po,
                    })),
                };
                const res = await axios.post("/api/v1/validasi/pra-finalisasi/store-hirarki", payload);
                if (res.status === 200) {
                    const el = document.getElementById("offcanvasTolak");
                    if (el) bootstrap.Offcanvas.getInstance(el)?.hide();
                    Swal.fire({ icon: "warning", title: "Ditolak", text: "Tahap berhasil ditolak.", timer: 1500, showConfirmButton: false });
                    this.fetchDetail(this.selectedItem.No_Po_Sampel);
                    this.fetchList(this.pagination.page);
                }
            } catch (e) {
                Swal.fire("Gagal!", e.response?.data?.message || "Gagal menolak.", "error");
            } finally {
                this.loading.action = false;
            }
        },

        async submitBatal() {
            this.loading.action = true;
            try {
                const res = await axios.post("/api/v1/formulator/validasi/pra-finalisasi/cancel", {
                    No_Po_Sampel: this.selectedItem.No_Po_Sampel,
                    Alasan: this.formBatal.alasan,
                });
                if (res.status === 200) {
                    const el = document.getElementById("offcanvasBatal");
                    if (el) bootstrap.Offcanvas.getInstance(el)?.hide();
                    Swal.fire({ icon: "success", title: "Berhasil", text: "Sampel berhasil dibatalkan.", timer: 1500, showConfirmButton: false })
                        .then(() => {
                            this.selectedItem = null;
                            this.detailVisible = false;
                            this.fetchList(1);
                        });
                }
            } catch (e) {
                Swal.fire("Gagal!", e.response?.data?.message || "Gagal membatalkan.", "error");
            } finally {
                this.loading.action = false;
            }
        },

        async doFinalisasi(item) {
            const r = await Swal.fire({
                title: "Konfirmasi Finalisasi",
                text: `Finalisasi sampel ${item.No_Po_Sampel}? Tindakan ini tidak dapat dibatalkan.`,
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#405189",
                confirmButtonText: "Ya, Finalisasi!",
                cancelButtonText: "Batal",
            });
            if (!r.isConfirmed) return;
            this.loading.action = true;
            Swal.fire({ title: "Memproses...", allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const res = await axios.post("/api/v1/formulator/validasi/pra-finalisasi/approve", {
                    No_Po_Sampel: item.No_Po_Sampel,
                });
                if (res.status === 200) {
                    Swal.fire({ icon: "success", title: "Berhasil!", text: "Sampel berhasil di-finalisasi.", timer: 2000 })
                        .then(() => {
                            this.selectedItem = null;
                            this.detailVisible = false;
                            this.fetchList(1);
                        });
                }
            } catch (e) {
                if (e.response?.status === 422) {
                    const daftar = e.response.data?.detail?.Analisa_Bermasalah || [];
                    Swal.fire({
                        icon: "warning",
                        title: "Perhatian",
                        html: `<div>${e.response.data.message}${daftar.length ? '<ul class="text-start mt-2 ps-3">' + daftar.map(a => `<li><strong>${a}</strong></li>`).join("") + "</ul>" : ""}</div>`,
                    });
                } else {
                    Swal.fire("Gagal!", e.response?.data?.message || "Gagal finalisasi.", "error");
                }
            } finally {
                this.loading.action = false;
            }
        },

        lihatFoto(url) {
            if (url) window.open(url, "_blank", "noopener,noreferrer");
        },

        formatTanggal(s) {
            if (!s) return "—";
            return new Date(s).toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },

        handleResize() {
            this.isMobile = window.innerWidth < 992;
        },
    },

    mounted() {
        this.fetchKlasifikasiAktivitas();
        this.fetchList();
        window.addEventListener("resize", this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.handleResize);
    },
};
</script>

<style scoped>
/* ════════════════════════════════════════════════════════════════════════
   ROOT
   ════════════════════════════════════════════════════════════════════════ */
.vld-root {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    font-family: inherit;
}

/* ── Top bar ──────────────────────────────────────────────────────────── */
.vld-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 20px;
    background: #fff;
    border-bottom: 1px solid #e9ebec;
    flex-shrink: 0;
    gap: 12px;
    flex-wrap: wrap;
}
.vld-topbar-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.vld-topbar-icon {
    font-size: 22px;
    color: #405189;
    background: #eef0f9;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.vld-topbar-title {
    display: block;
    font-weight: 600;
    font-size: 15px;
    color: #1a1d23;
    line-height: 1.2;
}
.vld-topbar-sub {
    display: block;
    font-size: 11px;
    color: #878a99;
    line-height: 1.3;
}
.vld-topbar-right {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.vld-stat { display: flex; flex-direction: column; align-items: center; line-height: 1.1; }
.vld-stat-num { font-size: 18px; font-weight: 700; color: #1a1d23; }
.vld-stat-lbl { font-size: 10px; color: #878a99; text-transform: uppercase; letter-spacing: 0.4px; }

/* ── Main split layout ────────────────────────────────────────────────── */
.vld-body { display: flex; flex: 1; overflow: hidden; }

/* ════════════════════════════════════════════════════════════════════════
   LEFT PANEL
   ════════════════════════════════════════════════════════════════════════ */
.vld-left {
    width: 320px;
    min-width: 260px;
    max-width: 360px;
    display: flex;
    flex-direction: column;
    border-right: 1px solid #e9ebec;
    background: #fff;
    flex-shrink: 0;
}

.vld-filter-bar {
    padding: 10px 12px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ebec;
    flex-shrink: 0;
}
.vld-search-wrap { position: relative; }
.vld-search-icon {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    color: #878a99;
    font-size: 13px;
    pointer-events: none;
}
.vld-search-input {
    width: 100%;
    padding: 6px 10px 6px 28px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 12px;
    background: #fff;
    outline: none;
    transition: border-color 0.2s;
}
.vld-search-input:focus { border-color: #405189; box-shadow: 0 0 0 2px rgba(64,81,137,.12); }

.vld-list { flex: 1; overflow-y: auto; overflow-x: hidden; }
.vld-list::-webkit-scrollbar { width: 4px; }
.vld-list::-webkit-scrollbar-track { background: transparent; }
.vld-list::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }

.vld-skeleton {
    height: 66px;
    border-radius: 8px;
    background: linear-gradient(90deg, #f0f2f5 25%, #e4e7ec 50%, #f0f2f5 75%);
    background-size: 400% 100%;
    animation: vld-shimmer 1.4s infinite;
}
@keyframes vld-shimmer {
    0% { background-position: 100% 50%; }
    100% { background-position: 0 50%; }
}

.vld-empty-list {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 20px;
    color: #878a99;
    gap: 8px;
    font-size: 12px;
    text-align: center;
}
.vld-empty-list i { font-size: 36px; color: #ced4da; }
.vld-empty-list p { margin: 0; }

.vld-item {
    display: flex;
    align-items: stretch;
    width: 100%;
    background: transparent;
    border: none;
    border-bottom: 1px solid #f0f2f5;
    padding: 0;
    cursor: pointer;
    transition: background 0.15s;
    text-align: left;
}
.vld-item:hover { background: #f8f9fa; }
.vld-item--active { background: #eef0f9 !important; }

.vld-item-accent { width: 3px; flex-shrink: 0; background: transparent; transition: background 0.15s; }
.vld-item--lolos .vld-item-accent { background: #0ab39c; }
.vld-item--tidak .vld-item-accent { background: #f06548; }
.vld-item--warn .vld-item-accent { background: #f7b731; }
.vld-item--active .vld-item-accent { width: 4px; }

.vld-item-body { flex: 1; min-width: 0; padding: 10px 8px 10px 10px; }
.vld-item-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 4px;
}
.vld-item-title {
    font-size: 12px;
    font-weight: 600;
    color: #1a1d23;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}
.vld-item-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
.vld-item-arrow { align-self: center; flex-shrink: 0; padding: 0 6px; color: #ced4da; font-size: 16px; }
.vld-item--active .vld-item-arrow { color: #405189; }

.vld-list-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-top: 1px solid #e9ebec;
    background: #f8f9fa;
    flex-shrink: 0;
}
.vld-page-info { font-size: 11px; color: #878a99; }
.vld-page-btns { display: flex; align-items: center; gap: 4px; }
.vld-page-btn {
    width: 26px; height: 26px;
    border: 1px solid #ced4da;
    border-radius: 5px;
    background: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: #495057;
    transition: all 0.15s;
}
.vld-page-btn:hover:not(:disabled) { background: #405189; color: #fff; border-color: #405189; }
.vld-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.vld-page-current { font-size: 11px; color: #495057; min-width: 36px; text-align: center; }

/* ════════════════════════════════════════════════════════════════════════
   RIGHT PANEL
   ════════════════════════════════════════════════════════════════════════ */
.vld-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f3f6f9;
    min-width: 0;
}

.vld-mobile-back {
    padding: 8px 12px;
    background: #fff;
    border-bottom: 1px solid #e9ebec;
    flex-shrink: 0;
}

.vld-detail-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
}
.vld-detail-empty-inner { text-align: center; max-width: 320px; }
.vld-empty-icon-wrap {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: #eef0f9;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}
.vld-empty-icon-wrap i { font-size: 32px; color: #405189; }
.vld-detail-empty-inner h6 { font-weight: 600; color: #1a1d23; margin-bottom: 8px; }
.vld-detail-empty-inner p { font-size: 12px; color: #878a99; line-height: 1.6; margin: 0; }

/* ── Sticky detail header ─────────────────────────────────────────────── */
.vld-detail-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 16px;
    background: #fff;
    border-bottom: 1px solid #e9ebec;
    flex-shrink: 0;
    flex-wrap: wrap;
}
.vld-dh-main { display: flex; align-items: flex-start; gap: 12px; flex: 1; min-width: 0; }
.vld-dh-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.vld-dh-icon--success { background: #d1f8ef; color: #0ab39c; }
.vld-dh-icon--info { background: #eef0f9; color: #405189; }
.vld-dh-title { font-size: 14px; font-weight: 700; color: #1a1d23; line-height: 1.2; margin-bottom: 2px; }
.vld-dh-sub { font-size: 11px; color: #878a99; margin-bottom: 6px; }
.vld-dh-badges { display: flex; flex-wrap: wrap; gap: 4px; }

/* ── Step tabs bar ────────────────────────────────────────────────────── */
.vld-subpo-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    background: #fafbfc;
    border-bottom: 1px solid #e9ebec;
    flex-shrink: 0;
    flex-wrap: wrap;
}
.vld-subpo-label { font-size: 11px; font-weight: 600; color: #495057; white-space: nowrap; flex-shrink: 0; }
.vld-subpo-tabs { display: flex; flex-wrap: wrap; gap: 5px; flex: 1; }
.vld-subpo-loading { display: flex; align-items: center; }

.vld-subpo-tab {
    padding: 4px 10px;
    border-radius: 20px;
    border: 1px solid #ced4da;
    background: #fff;
    font-size: 11px;
    color: #495057;
    cursor: pointer;
    transition: all 0.15s;
    display: flex;
    align-items: center;
    gap: 4px;
}
.vld-subpo-tab:hover { border-color: #405189; color: #405189; background: #eef0f9; }
.vld-subpo-tab--active { background: #405189; border-color: #405189; color: #fff; font-weight: 600; }
.vld-subpo-tab--ok { border-color: #0ab39c; color: #0ab39c; }
.vld-subpo-tab--ok.vld-subpo-tab--active { background: #0ab39c; border-color: #0ab39c; color: #fff; }
.vld-subpo-tab--fail { border-color: #f06548; color: #f06548; }
.vld-subpo-tab--fail.vld-subpo-tab--active { background: #f06548; border-color: #f06548; color: #fff; }
.vld-subpo-tab--locked { border-color: #adb5bd; color: #adb5bd; cursor: not-allowed; }

/* ── Scrollable body ──────────────────────────────────────────────────── */
.vld-detail-body { flex: 1; overflow-y: auto; padding: 14px 16px; }
.vld-detail-body::-webkit-scrollbar { width: 5px; }
.vld-detail-body::-webkit-scrollbar-track { background: transparent; }
.vld-detail-body::-webkit-scrollbar-thumb { background: #dee2e6; border-radius: 4px; }

.vld-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #878a99;
}

/* Section cards */
.vld-section {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e9ebec;
    margin-bottom: 10px;
    overflow: hidden;
}
.vld-section-hd {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 600;
    color: #1a1d23;
    background: #fafbfc;
    border-bottom: 1px solid #e9ebec;
    user-select: none;
}
.vld-section-body { padding: 12px 14px; }

/* Mini stats */
.vld-mini-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.vld-ms-item {
    flex: 1; min-width: 80px;
    background: #fff;
    border: 1px solid #e9ebec;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.vld-ms-item--success { border-left: 3px solid #0ab39c; }
.vld-ms-item--danger { border-left: 3px solid #f06548; }
.vld-ms-val { font-size: 20px; font-weight: 700; color: #1a1d23; line-height: 1.1; }
.vld-ms-lbl { font-size: 10px; color: #878a99; text-transform: uppercase; letter-spacing: 0.4px; margin-top: 2px; }
.vld-ms-item--success .vld-ms-val { color: #0ab39c; }
.vld-ms-item--danger .vld-ms-val { color: #f06548; }

/* Alerts */
.vld-alert-warn, .vld-alert-ok, .vld-alert-no {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
}
.vld-alert-warn { background: #fff8ec; border: 1px solid #f7b731; color: #856404; }
.vld-alert-ok { background: #d1f8ef; border: 1px solid #0ab39c; color: #0a5a4a; }
.vld-alert-no { background: #fde8e4; border: 1px solid #f06548; color: #7b2f20; }

/* Table */
.vld-table thead tr th {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    white-space: nowrap;
    background: #f3f6f9;
    color: #495057;
    border-bottom: 2px solid #e9ebec;
    padding: 7px 10px;
}
.vld-table tbody td { font-size: 12px; padding: 7px 10px; }
.vld-row--avg { background: #fffbeb !important; }

/* Badges */
.vld-badge {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
}
.vld-badge--success { background: #d1f8ef; color: #0ab39c; }
.vld-badge--danger { background: #fde8e4; color: #f06548; }
.vld-badge--blue { background: #eef0f9; color: #405189; }
.vld-badge--gray { background: #f0f2f5; color: #6c757d; }
.vld-badge--orange { background: #fef3c7; color: #d97706; }

/* Chips */
.vld-chip {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    font-weight: 500;
}
.vld-chip--success { background: #d1f8ef; color: #0ab39c; }
.vld-chip--danger { background: #fde8e4; color: #f06548; }
.vld-chip--blue { background: #eef0f9; color: #405189; }
.vld-chip--gray { background: #f0f2f5; color: #6c757d; }
.vld-chip--orange { background: #fef3c7; color: #d97706; }

/* ── Sticky action bar ────────────────────────────────────────────────── */
.vld-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    background: #fff;
    border-top: 1px solid #e9ebec;
    flex-shrink: 0;
    gap: 10px;
    flex-wrap: wrap;
}
.vld-action-info {
    font-size: 11px;
    color: #878a99;
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
}

/* ════════════════════════════════════════════════════════════════════════
   RESPONSIVE
   ════════════════════════════════════════════════════════════════════════ */
.vld-hidden-mobile { display: none !important; }

@media (min-width: 992px) {
    .vld-hidden-mobile { display: flex !important; }
}

@media (max-width: 991px) {
    .vld-root { height: calc(100vh - 60px); }
    .vld-left { width: 100%; max-width: 100%; border-right: none; }
    .vld-right { width: 100%; }
    .vld-body { flex-direction: column; }
}

@media (max-width: 480px) {
    .vld-topbar { flex-direction: column; align-items: flex-start; }
    .vld-topbar-right { flex-wrap: wrap; }
    .vld-mini-stats { flex-direction: row; }
    .vld-ms-item { min-width: calc(50% - 4px); }
}
</style>
