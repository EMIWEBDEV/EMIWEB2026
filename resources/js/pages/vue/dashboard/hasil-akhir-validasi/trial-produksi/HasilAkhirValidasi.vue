<template>
    <div class="fin-root">
        <div class="fin-topbar">
            <div class="fin-topbar-left">
                <div class="fin-topbar-icon-wrap"><i class="ri-test-tube-line"></i></div>
                <div><span class="fin-topbar-title">Finalisasi Trial Produksi</span><span class="fin-topbar-sub">Close sampel & rekam hasil akhir trial produksi</span></div>
            </div>
            <div class="fin-topbar-right">
                <div v-if="pagination.total > 0" class="fin-stat-badge">
                    <span class="fin-stat-num">{{ pagination.total }}</span><span class="fin-stat-lbl">Menunggu</span>
                </div>
                <span class="fin-status-chip"><i class="ri-test-tube-line me-1"></i>Siap Finalisasi</span>
            </div>
        </div>

        <div class="fin-body">
            <!-- LEFT -->
            <div class="fin-left" :class="{ 'fin-hidden-mobile': detailVisible && isMobile }">
                <div class="fin-filter-bar">
                    <div class="fin-search-wrap">
                        <i class="ri-search-line fin-search-icon"></i>
                        <input type="text" class="fin-search-input" placeholder="Cari No. Sampel, PO, Barang..." v-model="searchQuery" @input="debounceFetch" />
                        <button v-if="searchQuery" class="fin-search-x" @click="searchQuery='';fetchList()"><i class="ri-close-line"></i></button>
                    </div>
                    <div class="fin-filter-row">
                        <input type="date" class="fin-date-input" v-model="filters.startDate" @change="fetchList()" /><span class="fin-sep">—</span>
                        <input type="date" class="fin-date-input" v-model="filters.endDate" @change="fetchList()" />
                        <select class="fin-select" v-model="filters.qrType" @change="fetchList()"><option value="">Semua QR</option><option value="Y">Multi QR</option><option value="T">Single QR</option></select>
                        <button class="fin-btn-reset" @click="resetFilters" title="Reset"><i class="ri-filter-off-line"></i></button>
                    </div>
                </div>
                <div class="fin-list">
                    <div v-if="loading.list" class="p-3"><div v-for="i in 5" :key="i" class="fin-skeleton mb-2"></div></div>
                    <div v-else-if="listData.length === 0" class="fin-empty-list">
                        <i class="ri-inbox-2-line"></i><p>Tidak ada data siap finalisasi</p>
                        <button class="btn btn-sm btn-outline-warning" @click="resetFilters"><i class="ri-refresh-line me-1"></i>Reset</button>
                    </div>
                    <div v-else>
                        <div class="fin-checkall-bar">
                            <label class="fin-checkall-label"><input type="checkbox" class="fin-checkall-cb" :checked="allCurrentPageChecked" :indeterminate.prop="someCurrentPageChecked && !allCurrentPageChecked" @change="toggleCheckAll" /><span>Pilih Semua</span></label>
                            <span v-if="selectedItems.length > 0" class="fin-checkall-count">{{ selectedItems.length }} dipilih</span>
                        </div>
                        <div v-for="(item, idx) in listData" :key="idx" class="fin-item-wrap">
                            <input type="checkbox" class="fin-item-checkbox" :checked="isChecked(item)" @change="toggleBulk(item)" @click.stop />
                            <button @click="selectItem(item)" class="fin-item" :class="{ 'fin-item--active': isActive(item), 'fin-item--checked': isChecked(item) }">
                                <div class="fin-item-accent"></div>
                                <div class="fin-item-body">
                                    <div class="fin-item-top"><span class="fin-item-title">{{ item.No_Po_Sampel }}</span><span class="fin-badge fin-badge--trial">{{ item.Total_Jenis_Analisa }} Analisa</span></div>
                                    <div class="fin-item-sub" v-if="item.Nama_Barang">{{ item.Kode_Barang }} — {{ item.Nama_Barang }}</div>
                                    <div class="fin-item-meta">
                                        <span class="fin-chip fin-chip--blue"><i class="ri-file-list-3-line"></i>{{ item.No_Po }}</span>
                                        <span class="fin-chip" :class="item.Flag_Multi_QrCode==='Y'?'fin-chip--blue':'fin-chip--gray'"><i class="ri-qr-code-line"></i>{{ item.Flag_Multi_QrCode==='Y'?'Multi':'Single' }}</span>
                                        <span class="fin-chip fin-chip--trial"><i class="ri-test-tube-line"></i>Trial</span>
                                        <span class="fin-chip fin-chip--gray" v-if="item.Nama_Mesin"><i class="ri-settings-3-line"></i>{{ item.Nama_Mesin }}</span>
                                        <span class="fin-chip fin-chip--gray" v-if="item.Tanggal"><i class="ri-calendar-line"></i>{{ formatDate(item.Tanggal) }}</span>
                                    </div>
                                </div>
                                <i class="ri-arrow-right-s-line fin-item-arrow"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="selectedItems.length > 0" class="fin-bulk-bar">
                    <span class="fin-bulk-count"><i class="ri-checkbox-multiple-line me-1"></i>{{ selectedItems.length }} dipilih</span>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-light" @click="selectedItems = []">Batal</button>
                        <button class="btn btn-sm fw-semibold fin-btn-trial" @click="confirmBulk"><i class="ri-git-commit-line me-1"></i>Finalisasi Bulk</button>
                    </div>
                </div>
                <div class="fin-list-footer" v-if="pagination.totalPage > 1">
                    <span class="fin-page-info">{{ listData.length }} / {{ pagination.total }}</span>
                    <div class="fin-page-btns">
                        <button class="fin-page-btn" :disabled="pagination.page===1" @click="changePage(pagination.page-1)"><i class="ri-arrow-left-s-line"></i></button>
                        <span class="fin-page-current">{{ pagination.page }} / {{ pagination.totalPage }}</span>
                        <button class="fin-page-btn" :disabled="pagination.page===pagination.totalPage" @click="changePage(pagination.page+1)"><i class="ri-arrow-right-s-line"></i></button>
                    </div>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="fin-right" :class="{ 'fin-hidden-mobile': !detailVisible && isMobile }">
                <div v-if="isMobile && detailVisible" class="fin-mobile-back"><button class="btn btn-sm btn-soft-secondary" @click="detailVisible = false"><i class="ri-arrow-left-line me-1"></i>Daftar</button></div>
                <div v-if="!selectedItem" class="fin-detail-empty">
                    <div class="fin-detail-empty-inner"><div class="fin-empty-icon-wrap"><i class="ri-test-tube-line"></i></div><h6>Pilih sampel trial untuk difinalisasi</h6><p>Klik item di daftar kiri untuk melihat detail analisa dan melakukan finalisasi.</p></div>
                </div>
                <template v-else>
                    <div class="fin-detail-header">
                        <div class="fin-dh-main">
                            <div class="fin-dh-icon"><i class="ri-test-tube-line"></i></div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fin-dh-title">{{ selectedItem.Nama_Barang || selectedItem.Kode_Barang }}</div>
                                <div class="fin-dh-sampel">{{ selectedItem.No_Po_Sampel }}</div>
                                <div class="fin-dh-badges">
                                    <span class="fin-badge fin-badge--primary"><i class="ri-receipt-line me-1"></i>{{ selectedItem.No_Po }}</span>
                                    <span class="fin-badge fin-badge--gray"><i class="ri-git-branch-line me-1"></i>{{ selectedItem.No_Split_Po }}</span>
                                    <span class="fin-badge" :class="selectedItem.Flag_Multi_QrCode==='Y'?'fin-badge--primary':'fin-badge--gray'"><i class="ri-qr-code-line me-1"></i>{{ selectedItem.Flag_Multi_QrCode==='Y'?'Multi QR':'Single QR' }}</span>
                                    <span class="fin-badge fin-badge--trial"><i class="ri-test-tube-line me-1"></i>Trial Produksi</span>
                                    <span class="fin-badge fin-badge--mesin" v-if="selectedItem.Nama_Mesin"><i class="ri-settings-3-line me-1"></i>{{ selectedItem.Nama_Mesin }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="fin-kpi-row" v-if="detailData.length > 0">
                            <div class="fin-kpi"><span class="fin-kpi-num">{{ detailData.length }}</span><span class="fin-kpi-lbl">Total</span></div>
                            <div class="fin-kpi fin-kpi--success"><span class="fin-kpi-num">{{ detailData.filter(d=>d.Flag_Layak!=='T').length }}</span><span class="fin-kpi-lbl">Lolos</span></div>
                            <div class="fin-kpi fin-kpi--danger"><span class="fin-kpi-num">{{ detailData.filter(d=>d.Flag_Layak==='T').length }}</span><span class="fin-kpi-lbl">Tidak Lolos</span></div>
                        </div>
                    </div>
                    <div class="fin-tabs">
                        <button class="fin-tab" :class="{'fin-tab--active':activeTab==='analisa'}" @click="activeTab='analisa'"><i class="ri-flask-line me-1"></i>Detail Analisa</button>
                        <button class="fin-tab" :class="{'fin-tab--active':activeTab==='timeline'}" @click="activeTab='timeline';loadTimeline()"><i class="ri-timeline-view me-1"></i>Timeline</button>
                    </div>
                    <div class="fin-detail-body">
                        <div v-if="loading.detail" class="fin-loading-state"><div class="spinner-border" style="color:#d97706"></div><p class="mt-3 text-muted small">Memuat data analisa trial...</p></div>
                        <template v-else-if="activeTab==='analisa'">
                            <div v-if="detailData.length===0" class="fin-loading-state"><i class="ri-inbox-2-line fs-1 text-muted"></i><p class="text-muted small mt-2">Tidak ada data analisa</p></div>
                            <div v-else>
                                <div v-for="section in detailSections" :key="section.group" class="fin-section">
                                    <button class="fin-section-hdr" @click="toggleSection(section.group)">
                                        <div class="fin-section-hdr-left">
                                            <div class="fin-section-icon" :style="{background:section.bg}"><i :class="section.icon"></i></div>
                                            <div><span class="fin-section-title">{{ section.label }}</span><span class="fin-section-sub">{{ section.items.length }} jenis analisa</span></div>
                                        </div>
                                        <div class="fin-section-hdr-right">
                                            <span v-if="section.failCount>0" class="fin-badge fin-badge--danger"><i class="ri-close-circle-line me-1"></i>{{ section.failCount }} TL</span>
                                            <span v-else class="fin-badge fin-badge--success"><i class="ri-checkbox-circle-line me-1"></i>Semua Lolos</span>
                                            <i class="fin-chevron ri-arrow-up-s-line" :class="{'collapsed':!isSectionOpen(section.group)}"></i>
                                        </div>
                                    </button>
                                    <div v-show="isSectionOpen(section.group)" class="fin-section-body">
                                        <div v-for="analisa in section.items" :key="analisa.key" class="fin-analisa-panel">
                                            <button class="fin-analisa-hdr" :class="analisa.Flag_Layak==='T'?'fin-analisa-hdr--danger':'fin-analisa-hdr--success'" @click="toggleAnalisa(analisa)">
                                                <div class="fin-analisa-hdr-left">
                                                    <div class="fin-analisa-hdr-dot" :class="analisa.Flag_Layak==='T'?'dot--danger':'dot--success'"></div>
                                                    <div>
                                                        <span class="fin-analisa-hdr-title">{{ analisa.Jenis_Analisa }}</span>
                                                        <div class="fin-analisa-hdr-meta">
                                                            <span class="fin-chip fin-chip--gray" style="font-size:.64rem;">{{ analisa.Kode_Analisa }}</span>
                                                            <span v-if="analisa.is_plt && analisa.Nama_Pembanding" class="fin-chip fin-chip--cyan" style="font-size:.64rem;"><i class="ri-flask-line"></i>{{ analisa.Nama_Pembanding }}</span>
                                                            <span v-if="analisa.Flag_Perhitungan==='Y'" class="fin-chip fin-chip--purple" style="font-size:.64rem;"><i class="ri-calculator-line"></i>Perhitungan</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                    <span class="fin-badge" :class="analisa.Flag_Layak==='T'?'fin-badge--danger':'fin-badge--success'"><i :class="analisa.Flag_Layak==='T'?'ri-close-circle-line':'ri-checkbox-circle-line'" class="me-1"></i>{{ analisa.Flag_Layak==='T'?'Tidak Lolos':'Lolos' }}</span>
                                                    <i class="fin-chevron ri-arrow-up-s-line" :class="{'collapsed':!isAnalisaOpen(analisa.key)}"></i>
                                                </div>
                                            </button>
                                            <div v-show="isAnalisaOpen(analisa.key)" class="fin-analisa-table-wrap">
                                                <div v-if="loadingTable[analisa.key]" class="fin-table-loading"><span class="spinner-border spinner-border-sm me-2" style="color:#d97706"></span><span class="text-muted small">Memuat data...</span></div>
                                                <div v-else-if="!tableRows[analisa.key]||tableRows[analisa.key].length===0" class="text-center py-3 text-muted small"><i class="ri-inbox-2-line me-1"></i>Tidak ada data</div>
                                                <div v-else>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered align-middle mb-0 fin-data-table">
                                                            <thead>
                                                                <tr>
                                                                    <th class="text-center fin-th-no">#</th>
                                                                    <th v-if="analisa.is_plt" class="fin-th-plt"><i class="ri-flask-line me-1"></i>Pembanding</th>
                                                                    <th>No Transaksi</th><th>No Sampel</th><th>No PO</th><th>No Split Po</th>
                                                                    <th v-if="selectedItem.Flag_Multi_QrCode==='Y'">No Sub Sampel</th>
                                                                    <th>Tanggal</th>
                                                                    <template v-if="hasTemplateData(analisa.key)">
                                                                        <th v-for="param in getTemplate(analisa.key).parameter" :key="param.id_qc">{{ param.nama_parameter }}<small v-if="param.satuan" class="d-block fw-normal opacity-75" style="font-size:.7em;">{{ param.satuan }}</small></th>
                                                                        <th v-for="f in getTemplate(analisa.key).formula" :key="f.id||f.nama_kolom" class="fin-th-formula">{{ f.nama_kolom }}</th>
                                                                    </template>
                                                                    <th v-else>Hasil</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr v-for="(row,ri) in tableRows[analisa.key]" :key="ri" :class="row.Flag_Layak==='T'?'fin-row--danger':'fin-row--success'">
                                                                    <td class="text-center fw-semibold fin-td-no">{{ ri+1 }}</td>
                                                                    <td v-if="analisa.is_plt" class="fin-td-plt"><span class="fin-pembanding">{{ row.Nama_Pembanding||'—' }}</span></td>
                                                                    <td class="fin-td-mono">{{ row.No_Faktur||'-' }}</td>
                                                                    <td class="fin-td-mono">{{ row.No_Po_Sampel||'-' }}</td>
                                                                    <td>{{ row.No_Po||'-' }}</td><td>{{ row.No_Split_Po||'-' }}</td>
                                                                    <td v-if="selectedItem.Flag_Multi_QrCode==='Y'">{{ row.No_Fak_Sub_Po||'-' }}</td>
                                                                    <td>{{ formatDate(row.Tanggal) }}</td>
                                                                    <template v-if="hasTemplateData(analisa.key)">
                                                                        <td v-for="(val,pi) in (row.parameters||[])" :key="pi">{{ val }}</td>
                                                                        <template v-if="getTemplate(analisa.key).formula?.length>0">
                                                                            <td v-for="(res,fi) in (row.results||[])" :key="fi" class="fin-td-formula fw-semibold">{{ res?.value??'-' }}</td>
                                                                        </template>
                                                                    </template>
                                                                    <td v-else class="fw-semibold">{{ row.Hasil_Akhir_Analisa??'-' }}</td>
                                                                </tr>
                                                                <tr v-if="analisa.Flag_Perhitungan==='Y'&&getTemplate(analisa.key).formula?.length>0&&(tableAverages[analisa.key]||[]).length>0" class="fin-row--rata">
                                                                    <td :colspan="getBaseColCount(analisa)" class="text-end pe-3 fw-bold fst-italic text-secondary">Rata-Rata</td>
                                                                    <td v-for="(avg,ai) in (tableAverages[analisa.key]||[])" :key="ai" class="fin-td-formula fw-bold">{{ avg }}</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div v-if="tableHasFotos(analisa.key)" class="fin-foto-strip"><button class="fin-foto-btn" @click="openFotoModal(analisa.key)"><i class="ri-image-2-line me-1"></i>Lihat Foto ({{ fotoCount(analisa.key) }})</button></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-else-if="activeTab==='timeline'">
                            <div v-if="loading.timeline" class="fin-loading-state"><div class="spinner-border spinner-border-sm" style="color:#d97706"></div><p class="text-muted small mt-2">Memuat...</p></div>
                            <div v-else-if="auditLog.length===0" class="fin-loading-state"><i class="ri-history-line fs-1 text-muted"></i><p class="text-muted small mt-2">Belum ada riwayat aktivitas</p></div>
                            <div v-else class="fin-vtl">
                                <div class="fin-vtl-hdr"><i class="ri-history-line me-2"></i>Riwayat Proses Sampel</div>
                                <div class="fin-vtl-steps">
                                    <div v-for="(log,li) in expandedAuditLog" :key="li" class="fin-vtl-step" :class="getStepClass(log)">
                                        <div class="fin-vtl-indicator">
                                            <div class="fin-vtl-dot"><i :class="getStepIcon(log)"></i></div>
                                            <div v-if="li<expandedAuditLog.length-1" class="fin-vtl-line"></div>
                                        </div>
                                        <div class="fin-vtl-body">
                                            <div class="fin-vtl-row1"><span class="fin-vtl-badge" :class="getStepBadgeClass(log)">{{ formatAksi(log.Jenis_Aksi) }}</span><span v-if="log.Sub_Aksi" class="fin-vtl-sub" :class="log.Sub_Aksi==='TOLAK'?'text-danger':'text-success'">{{ log.Sub_Aksi }}</span></div>
                                            <div class="fin-vtl-meta"><span><i class="ri-user-3-line me-1"></i>{{ log.Nama_User||log.Id_User }}</span><span><i class="ri-time-line me-1"></i>{{ formatDate(log.Tanggal) }}<template v-if="log.Jam"> · {{ log.Jam.substring(0,5) }}</template></span></div>
                                            <div v-if="log.details&&log.details.length" class="fin-vtl-details">
                                                <div class="fin-vtl-details-hdr"><i class="ri-microscope-line me-1"></i>{{ log.details.length }} analisa</div>
                                                <div v-for="d in log.details" :key="d.Id_Jenis_Analisa+'_'+d.Jam" class="fin-vtl-detail-row">
                                                    <i class="ri-arrow-right-s-line text-muted"></i>{{ d.Nama_Jenis_Analisa }}
                                                </div>
                                            </div>
                                            <div v-if="log.Keterangan" class="fin-vtl-note"><i class="ri-chat-3-line me-1"></i>{{ log.Keterangan }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="fin-action-footer">
                        <div class="d-flex align-items-center gap-3">
                            <div class="fin-action-info flex-grow-1">
                                <span v-if="detailData.filter(d=>d.Flag_Layak==='T').length>0" class="fin-action-warn"><i class="ri-alert-line me-1"></i>{{ detailData.filter(d=>d.Flag_Layak==='T').length }} analisa tidak lolos — status TIDAK OK</span>
                                <span v-else class="fin-action-ok"><i class="ri-checkbox-circle-line me-1"></i>Semua analisa lolos uji</span>
                            </div>
                            <button class="btn fw-semibold px-4 fin-btn-trial" @click="confirmSingle" :disabled="loading.submitting">
                                <span v-if="loading.submitting" class="spinner-border spinner-border-sm me-2"></span><i v-else class="ri-git-commit-line me-1"></i>Finalisasi Sampel
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- CONFIRM MODAL -->
        <div v-if="modal.show" class="fin-modal-backdrop" @click.self="modal.show=false">
            <div class="fin-modal">
                <div class="fin-modal-hdr fin-modal-hdr--trial"><i class="ri-test-tube-line me-2 fs-5"></i><div><div class="fin-modal-title">{{ modal.isBulk?'Bulk Finalisasi Trial':'Konfirmasi Finalisasi Trial' }}</div><div class="fin-modal-sub">{{ modal.isBulk?selectedItems.length+' sampel akan difinalisasi':'Sampel trial akan di-close dan dikunci' }}</div></div><button class="fin-modal-close" @click="modal.show=false"><i class="ri-close-line"></i></button></div>
                <div class="fin-modal-body">
                    <div v-if="!modal.isBulk&&selectedItem"><div class="fin-confirm-row"><span class="fin-confirm-lbl">Sampel</span><code class="fin-confirm-val">{{ selectedItem.No_Po_Sampel }}</code></div><div class="fin-confirm-row"><span class="fin-confirm-lbl">Barang</span><span class="fin-confirm-val">{{ selectedItem.Nama_Barang }}</span></div><div class="fin-confirm-row"><span class="fin-confirm-lbl">No. PO</span><span class="fin-confirm-val">{{ selectedItem.No_Po }}</span></div><div v-if="detailData.filter(d=>d.Flag_Layak==='T').length>0" class="fin-modal-warn"><i class="ri-alert-line me-2"></i>{{ detailData.filter(d=>d.Flag_Layak==='T').length }} analisa tidak lolos. Status akhir TIDAK OK.</div></div>
                    <div v-else-if="modal.isBulk"><p class="text-muted small mb-3">Sampel yang akan difinalisasi:</p><div v-for="(it,si) in selectedItems" :key="si" class="fin-bulk-row"><code class="fin-bulk-code">{{ it.No_Po_Sampel }}</code><span class="text-muted small">{{ it.Nama_Barang }}</span></div></div>
                </div>
                <div class="fin-modal-ftr"><button class="btn btn-light" @click="modal.show=false">Batal</button><button class="btn fw-semibold fin-btn-trial" @click="submitFinalisasi" :disabled="loading.submitting"><span v-if="loading.submitting" class="spinner-border spinner-border-sm me-2"></span><i v-else class="ri-git-commit-line me-1"></i>Konfirmasi & Finalisasi</button></div>
            </div>
        </div>

        <!-- ERROR 422 MODAL -->
        <div v-if="errorModal.show" class="fin-modal-backdrop" @click.self="errorModal.show=false">
            <div class="fin-modal">
                <div class="fin-modal-hdr" style="background:linear-gradient(135deg,#991b1b,#ef4444);">
                    <i class="ri-error-warning-line me-2 fs-5"></i>
                    <div>
                        <div class="fin-modal-title">Analisa Belum Lengkap</div>
                        <div class="fin-modal-sub">{{ errorModal.noSampel }}</div>
                    </div>
                    <button class="fin-modal-close" @click="errorModal.show=false"><i class="ri-close-line"></i></button>
                </div>
                <div class="fin-modal-body">
                    <p class="text-muted small mb-3">{{ errorModal.message }}</p>
                    <div v-if="errorModal.missingAnalisa.length > 0">
                        <div class="fw-semibold text-danger small mb-2"><i class="ri-alert-line me-1"></i>Analisa yang belum dilakukan:</div>
                        <div v-for="(analisa, idx) in errorModal.missingAnalisa" :key="idx" class="fin-error-analisa-row">
                            <i class="ri-close-circle-line text-danger me-2"></i>
                            <span>{{ analisa }}</span>
                        </div>
                    </div>
                </div>
                <div class="fin-modal-ftr"><button class="btn btn-danger" @click="errorModal.show=false"><i class="ri-check-line me-1"></i>Mengerti</button></div>
            </div>
        </div>

        <!-- FOTO MODAL + LIGHTBOX via teleport — same structure as monitoring -->
        <teleport to="body">
            <transition name="fin-foto-fade">
                <div v-if="fotoModal.show" class="fin-foto-overlay" @click.self="fotoModal.show=false">
                    <div class="fin-foto-modal">
                        <div class="fin-foto-modal-hdr">
                            <div><i class="ri-image-2-line me-2"></i><strong>Foto Analisa</strong><span class="fin-foto-modal-sub ms-2">{{ fotoModal.photos.length }} foto</span></div>
                            <button class="fin-foto-modal-close" @click="fotoModal.show=false"><i class="ri-close-line"></i></button>
                        </div>
                        <div class="fin-foto-modal-body">
                            <div v-if="fotoModal.loading" class="fin-foto-loading"><div class="spinner-border spinner-border-sm me-2" style="color:#d97706;"></div><span class="text-muted small">Memuat foto...</span></div>
                            <div v-else-if="fotoModal.photos.length===0" class="fin-foto-empty"><i class="ri-image-line fs-1 text-muted"></i><p class="text-muted small mt-2">Tidak ada foto tersedia</p></div>
                            <div v-else class="fin-foto-grid">
                                <div v-for="(photo,pi) in fotoModal.photos" :key="pi" class="fin-polaroid" @click="lightbox={show:true,url:photo.url,keterangan:photo.keterangan}">
                                    <div class="fin-polaroid-img-wrap"><img :src="photo.url" class="fin-polaroid-img" :alt="photo.keterangan||'Foto '+(pi+1)" /><div class="fin-polaroid-overlay"><i class="ri-zoom-in-line"></i></div></div>
                                    <div class="fin-polaroid-caption">{{ photo.keterangan || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
            <transition name="fin-foto-fade">
                <div v-if="lightbox.show" class="fin-lightbox" @click="lightbox.show=false">
                    <button class="fin-lightbox-close" @click.stop="lightbox.show=false"><i class="ri-close-line"></i></button>
                    <div class="fin-lightbox-inner" @click.stop>
                        <img :src="lightbox.url" class="fin-lightbox-img" />
                        <div v-if="lightbox.keterangan" class="fin-lightbox-caption">{{ lightbox.keterangan }}</div>
                    </div>
                </div>
            </transition>
        </teleport>
    </div>
</template>

<script>
import axios from "axios";
export default {
    data() {
        return {
            listData:[], selectedItem:null, detailData:[], auditLog:[],
            activeTab:"analisa", searchQuery:"", searchTimeout:null,
            filters:{startDate:"",endDate:"",qrType:""},
            pagination:{page:1,totalPage:1,total:0,limit:10},
            loading:{list:false,detail:false,timeline:false,submitting:false},
            selectedItems:[], isMobile:false, detailVisible:false,
            modal:{show:false,isBulk:false},
            errorModal:{show:false,message:'',missingAnalisa:[],noSampel:''},
            openSections:[], openAnalisas:[],
            loadingTable:{}, templates:{}, tableRows:{}, tableAverages:{}, tableRawFotos:{},
            blobUrlCache:{},
            fotoModal:{show:false,photos:[],loading:false},
            lightbox:{show:false,url:'',keterangan:''},
        };
    },
    computed: {
        allCurrentPageChecked() { return this.listData.length>0&&this.listData.every(i=>this.isChecked(i)); },
        someCurrentPageChecked() { return this.listData.some(i=>this.isChecked(i)); },
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
                if (byUser.size <= 1) {
                    result.push(log);
                } else {
                    for (const [uid, items] of byUser) {
                        result.push({ ...log, Id_User: uid, Nama_User: uid, Tanggal: items[0].Tanggal || log.Tanggal, Jam: items[0].Jam || log.Jam, details: items, _expanded: true });
                    }
                }
            }
            return result;
        },
        detailSections() {
            const gDef={ANL:{label:'Analisa Lab',icon:'ri-flask-line',bg:'linear-gradient(135deg,#405189,#2e3a64)'},PLT:{label:'Palatabilitas',icon:'ri-heart-pulse-line',bg:'linear-gradient(135deg,#0ab39c,#0891b2)'},LCKV:{label:'Look View',icon:'ri-eye-line',bg:'linear-gradient(135deg,#7c3aed,#5b21b6)'}};
            const order={ANL:1,PLT:2,LCKV:3};
            const grouped={};
            this.detailData.forEach(item=>{
                const g=item.Kode_Aktivitas_Lab||'ANL';
                if(!grouped[g]){const d=gDef[g]||{label:g,icon:'ri-flask-line',bg:'linear-gradient(135deg,#405189,#2e3a64)'};grouped[g]={...d,group:g,items:[],failCount:0};}
                const key=`${item.Id_Jenis_Analisa}_${item.Nama_Pembanding||''}`;
                if(!grouped[g].items.find(i=>i.key===key))grouped[g].items.push({...item,key});
                if(item.Flag_Layak==='T')grouped[g].failCount++;
            });
            return Object.values(grouped).sort((a,b)=>(order[a.group]||99)-(order[b.group]||99));
        },
    },
    methods: {
        isSectionOpen(group){return this.openSections.includes(group);},
        isAnalisaOpen(key){return this.openAnalisas.includes(key);},
        toggleSection(group){const idx=this.openSections.indexOf(group);if(idx>-1)this.openSections.splice(idx,1);else this.openSections.push(group);},
        toggleAnalisa(analisa){const key=analisa.key;const idx=this.openAnalisas.indexOf(key);if(idx>-1){this.openAnalisas.splice(idx,1);return;}this.openAnalisas.push(key);if(!this.tableRows[key]&&!this.loadingTable[key])this.fetchAnalisaTable(analisa);},
        debounceFetch(){clearTimeout(this.searchTimeout);this.searchTimeout=setTimeout(()=>{this.pagination.page=1;this.fetchList();},400);},
        async fetchList(){
            this.loading.list=true;
            try{
                const params={page:this.pagination.page,limit:this.pagination.limit,search:this.searchQuery,qr_type:this.filters.qrType};
                if(this.filters.startDate)params.start_date=this.filters.startDate;
                if(this.filters.endDate)params.end_date=this.filters.endDate;
                const res=await axios.get("/api/v1/finalisai/trial-produksi/current",{params});
                if(res.data?.success){this.listData=res.data.result?.data||res.data.result||[];const meta=res.data.pagination||res.data.result?.pagination||{};this.pagination={page:meta.current_page||meta.page||1,totalPage:meta.total_pages||meta.totalPage||1,total:meta.total||meta.totalData||0,limit:meta.per_page||meta.limit||10};}
                else this.listData=[];
            }catch{this.listData=[];}finally{this.loading.list=false;}
        },
        async selectItem(item){
            this.selectedItem=item;this.activeTab="analisa";this.auditLog=[];this.detailVisible=true;
            this.openSections=[];this.openAnalisas=[];this.templates={};this.tableRows={};this.tableAverages={};this.tableRawFotos={};
            await this.fetchDetail(item.No_Po_Sampel);
            this.openSections=this.detailSections.map(s=>s.group);
            const allItems=this.detailSections.flatMap(s=>s.items);
            this.openAnalisas=allItems.map(a=>a.key);
            allItems.forEach(a=>this.fetchAnalisaTable(a));
        },
        async fetchDetail(noSampel){this.loading.detail=true;this.detailData=[];try{const res=await axios.get(`/api/v1/finalisai/trial-produksi/hasil-validasi/${noSampel}`);this.detailData=res.data?.result||[];}catch{this.detailData=[];}finally{this.loading.detail=false;}},
        async fetchAnalisaTable(analisa){
            const key=analisa.key;const idJA=analisa.Id_Jenis_Analisa;const noSampel=this.selectedItem.No_Po_Sampel;
            this.loadingTable={...this.loadingTable,[key]:true};
            try{
                const [dataRes,templateRes]=await Promise.all([
                    axios.get(`/api/v1/hasil-final-keputusan/${idJA}/single-qrcode/${noSampel}`).catch(()=>null),
                    axios.get(`/fetch/lab/lama/${idJA}/parameter-perhitungan-old`).catch(()=>null),
                ]);
                const template=templateRes?.data?.result||{parameter:[],formula:[]};
                const sampel=dataRes?.data?.result?.sampel||[];
                const {data,formulaAverages}=this.processItems(sampel,template);
                this.templates={...this.templates,[key]:template};
                this.tableRows={...this.tableRows,[key]:data};
                this.tableAverages={...this.tableAverages,[key]:formulaAverages};
                const fotos=[];data.forEach(row=>(row.foto_analisa||[]).forEach(f=>fotos.push(f)));
                if(fotos.length)this.tableRawFotos={...this.tableRawFotos,[key]:fotos};
            }catch(err){console.error(err);this.tableRows={...this.tableRows,[key]:[]};
            }finally{this.loadingTable={...this.loadingTable,[key]:false};}
        },
        processItems(items,template){
            if(!Array.isArray(items)||items.length===0)return{data:[],formulaAverages:[]};
            const tplParams=template?.parameter?.length||0;
            const tplFormula=template?.formula?.length||0;
            if(!(tplParams>0||tplFormula>0)){
                return{data:items.map(item=>({No_Po:item.No_Po||'-',No_Split_Po:item.No_Split_Po||'-',No_Faktur:item.No_Faktur||'-',Flag_Layak:item.Flag_Layak||'-',No_Po_Sampel:item.No_Po_Sampel||'-',No_Fak_Sub_Po:item.No_Fak_Sub_Po||'-',Tanggal:item.Tanggal_Pengujian||'-',Nama_Pembanding:item.Nama_Pembanding||null,Hasil_Akhir_Analisa:this.formatHasil(item.Hasil_Akhir_Analisa),parameters:[],results:[],foto_analisa:item.foto_analisa||[]})),formulaAverages:[]};
            }
            const grouped=items.reduce((acc,item)=>{const k=item.No_Faktur;if(!acc[k])acc[k]=[];acc[k].push(item);return acc;},{});
            const processedData=Object.values(grouped).map(group=>{
                const first=group[0];
                const detailParams=Array.isArray(first.parameter)?first.parameter:[];
                let parameterResults,finalResults;
                if(detailParams.length>0){parameterResults=detailParams.map(p=>this.formatHasil(p.Hasil_Analisa));finalResults=tplFormula>0?group.map(item=>({value:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak,pembulatan:item.Pembulatan??4})):[];}
                else if(group.length>1&&tplParams>0&&tplFormula===0){parameterResults=group.map(item=>this.formatHasil(item.Hasil_Akhir_Analisa));finalResults=[];}
                else{parameterResults=[];finalResults=group.map(item=>({value:this.formatHasil(item.Hasil_Akhir_Analisa),Flag_Layak:item.Flag_Layak,pembulatan:item.Pembulatan??4}));}
                return{No_Po:first.No_Po||'-',No_Split_Po:first.No_Split_Po||'-',No_Faktur:first.No_Faktur||'-',Flag_Layak:first.Flag_Layak||'-',No_Po_Sampel:first.No_Po_Sampel||'-',No_Fak_Sub_Po:first.No_Fak_Sub_Po||'-',Tanggal:first.Tanggal_Pengujian||'-',Nama_Pembanding:first.Nama_Pembanding||null,Hasil_Akhir_Analisa:this.formatHasil(first.Hasil_Akhir_Analisa),parameters:parameterResults,results:finalResults,foto_analisa:first.foto_analisa||[]};
            });
            const formulaAverages=[];
            for(let i=0;i<tplFormula;i++){let total=0,count=0,dp=4;processedData.forEach(row=>{const r=row.results[i];if(r&&r.value!=='-'){const v=parseFloat(r.value);if(!isNaN(v)){total+=v;count++;if(r.pembulatan)dp=parseInt(r.pembulatan,10);}}});formulaAverages.push(count>0?(total/count).toFixed(dp):'-');}
            return{data:processedData,formulaAverages};
        },
        formatHasil(val){if(val===null||val===undefined)return'-';const s=String(val).trim();if(!s||s==='null'||s==='undefined')return'-';if(/^-?\d+\.0+$/.test(s))return String(Math.trunc(parseFloat(s)));return s;},
        hasTemplateData(key){const t=this.templates[key];return t&&((t.parameter?.length||0)>0||(t.formula?.length||0)>0);},
        getTemplate(key){return this.templates[key]||{parameter:[],formula:[]};},
        getBaseColCount(analisa){let c=6;if(analisa.is_plt)c++;if(this.selectedItem?.Flag_Multi_QrCode==='Y')c++;return c+(this.getTemplate(analisa.key).parameter?.length||0);},
        tableHasFotos(key){return(this.tableRawFotos[key]||[]).length>0;},
        fotoCount(key){return(this.tableRawFotos[key]||[]).length;},
        async openFotoModal(key){
            const fotos=this.tableRawFotos[key]||[];
            if(!fotos.length)return;
            this.fotoModal={show:true,photos:[],loading:true};
            try{
                const keysToFetch=fotos.map(f=>f.Berkas_Key).filter(k=>k&&!this.blobUrlCache[k]);
                if(keysToFetch.length>0){
                    const tokenRes=await axios.post('/api/v1/lab/hasil-uji/berkas/foto/token/bulk',{keys:keysToFetch});
                    const tokenMap=tokenRes.data||{};
                    await Promise.all(keysToFetch.map(async k=>{
                        try{
                            const res=await axios.get(`/api/v1/lab/berkas/stream/foto-uji/${k}?token=${tokenMap[k]}`,{responseType:'blob'});
                            this.blobUrlCache[k]=URL.createObjectURL(res.data);
                        }catch{}
                    }));
                }
                this.fotoModal={show:true,loading:false,photos:fotos.map(f=>({url:this.blobUrlCache[f.Berkas_Key]||'',keterangan:f.Keterangan||f.keterangan||''})).filter(p=>p.url)};
            }catch(e){
                console.error('openFotoModal error:',e);
                this.fotoModal={show:true,loading:false,photos:[]};
            }
        },
        async loadTimeline(){if(this.auditLog.length>0||!this.selectedItem)return;this.loading.timeline=true;try{const res=await axios.get(`/api/v1/log-aksi/by-sampel/${this.selectedItem.No_Po_Sampel}`);this.auditLog=res.data?.result||[];}catch{this.auditLog=[];}finally{this.loading.timeline=false;}},
        isActive(item){return this.selectedItem?.No_Po_Sampel===item.No_Po_Sampel;},
        isChecked(item){return this.selectedItems.some(i=>i.No_Po_Sampel===item.No_Po_Sampel);},
        toggleBulk(item){const idx=this.selectedItems.findIndex(i=>i.No_Po_Sampel===item.No_Po_Sampel);if(idx>-1)this.selectedItems.splice(idx,1);else this.selectedItems.push(item);},
        toggleCheckAll(){if(this.allCurrentPageChecked){this.listData.forEach(item=>{const idx=this.selectedItems.findIndex(i=>i.No_Po_Sampel===item.No_Po_Sampel);if(idx>-1)this.selectedItems.splice(idx,1);});}else{this.listData.forEach(item=>{if(!this.isChecked(item))this.selectedItems.push(item);});}},
        changePage(p){if(p>=1&&p<=this.pagination.totalPage){this.pagination.page=p;this.fetchList();}},
        resetFilters(){this.searchQuery="";this.filters={startDate:"",endDate:"",qrType:""};this.pagination.page=1;this.fetchList();},
        confirmSingle(){this.modal.isBulk=false;this.modal.show=true;},
        confirmBulk(){this.modal.isBulk=true;this.modal.show=true;},
        async submitFinalisasi(){
            this.loading.submitting=true;
            try{
                if(this.modal.isBulk){
                    const res=await axios.post("/api/v1/finalisai/trial-produksi/hasil-analisa-close/finalisasi/bulk",{no_sampel_list:this.selectedItems.map(i=>i.No_Po_Sampel)});
                    if(res.data?.success){const sukses=res.data.result?.berhasil||[];this.selectedItems=this.selectedItems.filter(i=>!sukses.includes(i.No_Po_Sampel));if(this.selectedItem&&sukses.includes(this.selectedItem.No_Po_Sampel)){this.selectedItem=null;this.detailData=[];}this.showToast("success",`${sukses.length} sampel berhasil difinalisasi`);this.fetchList();}
                    else this.showToast("error",res.data?.message||"Gagal finalisasi bulk");
                }else{
                    const res=await axios.post(`/api/v1/finalisai/trial-produksi/hasil-analisa-close/finalisasi/${this.selectedItem.No_Po_Sampel}`);
                    if(res.data?.success){this.showToast("success","Sampel trial berhasil difinalisasi");this.selectedItem=null;this.detailData=[];this.detailVisible=false;this.fetchList();}
                    else this.showToast("error",res.data?.message||"Gagal finalisasi");
                }
                this.modal.show=false;
            }catch(e){
                const errData=e.response?.data;
                if(e.response?.status===422&&errData?.detail){
                    this.modal.show=false;
                    this.errorModal={
                        show:true,
                        message:errData.message||"Data analisa belum lengkap",
                        missingAnalisa:errData.detail.Analisa_Kurang||[],
                        noSampel:errData.detail.No_Sampel||''
                    };
                }else{
                    this.showToast("error",errData?.message||"Terjadi kesalahan");
                }
            }
            finally{this.loading.submitting=false;}
        },
        showToast(type,msg){const el=document.createElement("div");el.className=`fin-toast fin-toast--${type==='success'?'success':'error'}`;el.innerHTML=`<i class="${type==='success'?'ri-checkbox-circle-line':'ri-close-circle-line'} me-2"></i>${msg}`;document.body.appendChild(el);setTimeout(()=>el.remove(),3500);},
        formatDate(d){if(!d)return"-";return new Date(d).toLocaleDateString("id-ID",{day:"2-digit",month:"short",year:"numeric"});},
        formatAksi(aksi){const map={INPUT_ANALYZER:"Input Analyzer",VALIDASI_PRODUKSI:"Validasi Produksi",VALIDASI_TRIAL_PRODUKSI:"Validasi Trial",FINALISASI_PRODUKSI:"Finalisasi Produksi",FINALISASI_TRIAL_PRODUKSI:"Finalisasi Trial",VALIDASI_FORMULATOR:"Validasi Formulator",PRAFINALISASI_FORMULATOR:"Pra-Finalisasi",FINALISASI_FORMULATOR:"Finalisasi Formulator"};return map[aksi]||aksi;},
        getStepClass(log){if(log.Sub_Aksi==='TOLAK')return'vtl-rejected';if(log.Jenis_Aksi?.includes('FINALISASI'))return'vtl-final';return'vtl-done';},
        getStepIcon(log){if(log.Sub_Aksi==='TOLAK')return'ri-close-line';if(log.Jenis_Aksi==='INPUT_ANALYZER')return'ri-test-tube-line';if(log.Jenis_Aksi?.includes('FINALISASI'))return'ri-git-commit-line';return'ri-check-line';},
        getStepBadgeClass(log){if(log.Sub_Aksi==='TOLAK')return'vtl-badge--danger';if(log.Jenis_Aksi==='INPUT_ANALYZER')return'vtl-badge--info';if(log.Jenis_Aksi?.includes('FINALISASI'))return'vtl-badge--primary';return'vtl-badge--success';},
        checkMobile(){this.isMobile=window.innerWidth<768;},
    },
    mounted(){this.checkMobile();window.addEventListener("resize",this.checkMobile);this.fetchList();},
    beforeUnmount(){window.removeEventListener("resize",this.checkMobile);},
};
</script>

<style scoped>
.fin-root{display:flex;flex-direction:column;height:100vh;overflow:hidden;background:#fdf8f1;font-family:'Segoe UI',system-ui,sans-serif;}
/* TOP BAR - orange theme */
.fin-topbar{display:flex;align-items:center;justify-content:space-between;padding:0 20px;height:54px;background:#fff;border-bottom:1px solid #fde68a;flex-shrink:0;gap:12px;}
.fin-topbar-left{display:flex;align-items:center;gap:10px;}
.fin-topbar-icon-wrap{width:34px;height:34px;border-radius:8px;background:linear-gradient(135deg,#92400e,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;flex-shrink:0;}
.fin-topbar-title{font-weight:700;font-size:.9rem;color:#0f172a;display:block;line-height:1.2;}
.fin-topbar-sub{font-size:.68rem;color:#94a3b8;display:block;}
.fin-topbar-right{display:flex;align-items:center;gap:8px;}
.fin-stat-badge{display:flex;flex-direction:column;align-items:center;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:3px 9px;}
.fin-stat-num{font-weight:700;font-size:1rem;color:#d97706;line-height:1;}
.fin-stat-lbl{font-size:.58rem;color:#fcd34d;text-transform:uppercase;letter-spacing:.4px;}
.fin-status-chip{display:inline-flex;align-items:center;padding:4px 11px;border-radius:20px;font-size:.73rem;font-weight:600;background:#fffbeb;color:#d97706;border:1px solid #fde68a;}
/* BODY */
.fin-body{display:flex;flex:1;overflow:hidden;}
/* LEFT - orange accents */
.fin-left{width:360px;min-width:300px;display:flex;flex-direction:column;border-right:1px solid #fde68a;background:#fff;overflow:hidden;}
.fin-filter-bar{padding:10px 12px;border-bottom:1px solid #fef9c3;flex-shrink:0;}
.fin-search-wrap{position:relative;margin-bottom:7px;}
.fin-search-icon{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.82rem;}
.fin-search-input{width:100%;padding:7px 28px;border:1px solid #e2e8f0;border-radius:7px;font-size:.8rem;outline:none;background:#f8fafc;}
.fin-search-input:focus{border-color:#fcd34d;box-shadow:0 0 0 3px rgba(252,211,77,.15);background:#fff;}
.fin-search-x{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:none;background:none;color:#94a3b8;cursor:pointer;font-size:.82rem;padding:0;}
.fin-filter-row{display:flex;gap:5px;align-items:center;flex-wrap:wrap;}
.fin-date-input{flex:1;min-width:98px;padding:4px 7px;border:1px solid #e2e8f0;border-radius:5px;font-size:.76rem;}
.fin-sep{color:#94a3b8;font-size:.8rem;flex-shrink:0;}
.fin-select{flex:1;min-width:86px;padding:4px 7px;border:1px solid #e2e8f0;border-radius:5px;font-size:.76rem;}
.fin-btn-reset{padding:4px 9px;border:1px solid #fecaca;border-radius:5px;background:#fff;color:#ef4444;cursor:pointer;font-size:.78rem;}
.fin-list{flex:1;overflow-y:auto;}
.fin-skeleton{height:66px;background:linear-gradient(90deg,#fef9c3 25%,#fde68a 37%,#fef9c3 63%);background-size:400% 100%;border-radius:7px;animation:vz-pulse 1.4s infinite;}
@keyframes vz-pulse{0%{background-position:100% 50%}100%{background-position:0 50%}}
.fin-empty-list{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 16px;color:#94a3b8;gap:8px;text-align:center;}
.fin-empty-list i{font-size:1.8rem;}
.fin-empty-list p{font-size:.8rem;margin:0;}
.fin-checkall-bar{display:flex;align-items:center;justify-content:space-between;padding:7px 12px;border-bottom:1px solid #fef9c3;background:#fffbeb;}
.fin-checkall-label{display:flex;align-items:center;gap:7px;font-size:.78rem;font-weight:600;cursor:pointer;color:#374151;}
.fin-checkall-cb{cursor:pointer;accent-color:#d97706;}
.fin-checkall-count{font-size:.73rem;color:#d97706;font-weight:700;}
.fin-item-wrap{display:flex;align-items:stretch;border-bottom:1px solid #fef9c3;}
.fin-item-checkbox{flex-shrink:0;margin:auto 9px;cursor:pointer;accent-color:#d97706;}
.fin-item{flex:1;display:flex;align-items:center;border:none;background:none;cursor:pointer;padding:9px 10px 9px 0;text-align:left;position:relative;transition:background .12s;}
.fin-item:hover{background:#fffbeb;}
.fin-item--active{background:#fef3c7 !important;}
.fin-item--checked{background:#f0fdf4 !important;}
.fin-item-accent{width:3px;height:100%;position:absolute;left:0;top:0;background:transparent;border-radius:0 2px 2px 0;}
.fin-item--active .fin-item-accent{background:#d97706;}
.fin-item-body{flex:1;overflow:hidden;padding-left:2px;}
.fin-item-top{display:flex;align-items:center;justify-content:space-between;gap:5px;margin-bottom:2px;}
.fin-item-title{font-weight:700;font-size:.8rem;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.fin-item-sub{font-size:.69rem;color:#64748b;margin-bottom:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.fin-item-meta{display:flex;gap:3px;flex-wrap:wrap;}
.fin-item-arrow{color:#cbd5e1;font-size:.95rem;flex-shrink:0;}
.fin-chip{display:inline-flex;align-items:center;gap:2px;padding:1px 6px;border-radius:3px;font-size:.64rem;font-weight:600;}
.fin-chip i{font-size:.66rem;}
.fin-chip--blue{background:#eef2ff;color:#6366f1;}
.fin-chip--gray{background:#f1f5f9;color:#64748b;}
.fin-chip--trial{background:#fffbeb;color:#d97706;}
.fin-chip--cyan{background:#ecfeff;color:#0891b2;}
.fin-chip--purple{background:#f5f3ff;color:#7c3aed;}
.fin-badge{display:inline-flex;align-items:center;padding:1px 7px;border-radius:4px;font-size:.68rem;font-weight:600;white-space:nowrap;}
.fin-badge i{font-size:.68rem;}
.fin-badge--primary{background:rgba(64,81,137,.1);color:#405189;}
.fin-badge--trial{background:rgba(217,119,6,.1);color:#d97706;}
.fin-badge--success{background:rgba(10,179,156,.1);color:#0ab39c;border:1px solid rgba(10,179,156,.2);}
.fin-badge--danger{background:rgba(240,101,72,.1);color:#f06548;border:1px solid rgba(240,101,72,.2);}
.fin-badge--gray{background:#f1f5f9;color:#475569;}
.fin-badge--mesin{background:rgba(100,116,139,.1);color:#475569;border:1px solid rgba(100,116,139,.2);}
.fin-bulk-bar{padding:9px 12px;background:#92400e;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.fin-bulk-count{font-size:.8rem;}
.fin-btn-trial{background:#d97706;color:#fff;border:none;border-radius:6px;padding:5px 14px;}
.fin-btn-trial:hover{background:#b45309;color:#fff;}
.fin-list-footer{display:flex;align-items:center;justify-content:space-between;padding:7px 12px;border-top:1px solid #fde68a;background:#fff;flex-shrink:0;}
.fin-page-info{font-size:.72rem;color:#94a3b8;}
.fin-page-btns{display:flex;align-items:center;gap:5px;}
.fin-page-btn{width:26px;height:26px;border:1px solid #e2e8f0;border-radius:5px;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;}
.fin-page-btn:disabled{opacity:.4;cursor:not-allowed;}
.fin-page-current{font-size:.75rem;color:#475569;font-weight:600;}
/* RIGHT */
.fin-right{flex:1;display:flex;flex-direction:column;overflow:hidden;background:#fdf8f1;}
.fin-detail-empty{flex:1;display:flex;align-items:center;justify-content:center;}
.fin-detail-empty-inner{text-align:center;color:#94a3b8;max-width:250px;}
.fin-empty-icon-wrap{width:60px;height:60px;border-radius:50%;background:rgba(217,119,6,.1);color:#d97706;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.6rem;}
.fin-detail-empty-inner h6{color:#475569;font-weight:600;margin-bottom:6px;}
.fin-detail-empty-inner p{font-size:.8rem;margin:0;}
/* DETAIL HEADER */
.fin-detail-header{padding:13px 16px;background:#fff;border-bottom:1px solid #fde68a;flex-shrink:0;}
.fin-dh-main{display:flex;align-items:flex-start;gap:10px;margin-bottom:9px;}
.fin-dh-icon{width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,#92400e,#d97706);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.fin-dh-title{font-weight:700;font-size:.9rem;color:#0f172a;}
.fin-dh-sampel{font-size:.72rem;color:#64748b;margin:1px 0 5px;font-family:monospace;}
.fin-dh-badges{display:flex;gap:3px;flex-wrap:wrap;}
.fin-kpi-row{display:flex;gap:7px;}
.fin-kpi{flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:6px 9px;display:flex;flex-direction:column;align-items:center;}
.fin-kpi--success{background:rgba(10,179,156,.06);border-color:rgba(10,179,156,.25);}
.fin-kpi--danger{background:rgba(240,101,72,.06);border-color:rgba(240,101,72,.25);}
.fin-kpi-num{font-weight:700;font-size:1.2rem;color:#0f172a;line-height:1;}
.fin-kpi--success .fin-kpi-num{color:#0ab39c;}
.fin-kpi--danger .fin-kpi-num{color:#f06548;}
.fin-kpi-lbl{font-size:.62rem;color:#94a3b8;margin-top:2px;text-transform:uppercase;letter-spacing:.3px;}
/* TABS - orange active */
.fin-tabs{display:flex;border-bottom:1px solid #fde68a;background:#fff;flex-shrink:0;padding:0 14px;}
.fin-tab{padding:9px 14px;border:none;background:none;font-size:.8rem;font-weight:500;color:#94a3b8;cursor:pointer;border-bottom:2px solid transparent;display:flex;align-items:center;gap:4px;transition:.12s;}
.fin-tab--active{color:#d97706;border-bottom-color:#d97706;font-weight:600;}
.fin-tab-count{background:#d97706;color:#fff;border-radius:10px;padding:1px 6px;font-size:.6rem;font-weight:700;}
/* DETAIL BODY */
.fin-detail-body{flex:1;overflow-y:auto;padding:10px 12px;}
.fin-loading-state{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:130px;gap:7px;}
/* SECTIONS - use Velzon-aligned table, orange for section borders */
.fin-section{background:#fff;border-radius:9px;margin-bottom:7px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.06);border:1px solid #fef9c3;}
.fin-section-hdr{width:100%;display:flex;align-items:center;justify-content:space-between;padding:11px 14px;background:none;border:none;cursor:pointer;transition:background .12s;gap:8px;}
.fin-section-hdr:hover{background:#fffbeb;}
.fin-section-hdr-left{display:flex;align-items:center;gap:9px;}
.fin-section-icon{width:32px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.85rem;flex-shrink:0;}
.fin-section-title{font-weight:700;font-size:.83rem;color:#0f172a;display:block;text-align:left;}
.fin-section-sub{font-size:.65rem;color:#94a3b8;display:block;text-align:left;}
.fin-section-hdr-right{display:flex;align-items:center;gap:7px;flex-shrink:0;}
.fin-chevron{font-size:1.05rem;color:#94a3b8;transition:transform .2s;}
.fin-chevron.collapsed{transform:rotate(180deg);}
.fin-section-body{border-top:1px solid #fef9c3;}
.fin-analisa-panel{border-bottom:1px solid #fef9c3;}
.fin-analisa-panel:last-child{border-bottom:none;}
.fin-analisa-hdr{width:100%;display:flex;align-items:center;justify-content:space-between;padding:9px 14px;background:none;border:none;cursor:pointer;transition:background .12s;gap:8px;}
.fin-analisa-hdr:hover{background:#fffdf7;}
.fin-analisa-hdr--success{border-left:3px solid #0ab39c;}
.fin-analisa-hdr--danger{border-left:3px solid #f06548;}
.fin-analisa-hdr-left{display:flex;align-items:flex-start;gap:9px;flex:1;min-width:0;text-align:left;}
.fin-analisa-hdr-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:5px;}
.dot--success{background:#0ab39c;}
.dot--danger{background:#f06548;}
.fin-analisa-hdr-title{font-weight:600;font-size:.82rem;color:#0f172a;display:block;}
.fin-analisa-hdr-meta{display:flex;gap:3px;flex-wrap:wrap;margin-top:2px;}
.fin-analisa-table-wrap{padding-bottom:10px;background:#fffdf7;}
.fin-table-loading{display:flex;align-items:center;padding:14px 16px;}
/* TABLE - Velzon-aligned (same for all 3 variants) */
.fin-data-table{font-size:.77rem;}
.fin-data-table thead th{background:#405189;color:#fff;font-weight:600;font-size:.7rem;white-space:nowrap;padding:7px 9px;border-color:#2e3a64;}
.fin-data-table td{padding:5px 9px;vertical-align:middle;border-color:#e9ecf0;}
.fin-th-no{width:32px;}
.fin-th-plt{background:#0891b2 !important;color:#fff !important;}
.fin-th-formula{background:#3a4d86 !important;color:#c7d2fe !important;}
.fin-td-no{width:32px;color:#64748b;}
.fin-td-plt{background:rgba(8,145,178,.05);border-left:3px solid #0891b2 !important;}
.fin-td-formula{background:rgba(64,81,137,.04);}
.fin-td-mono{font-family:monospace;font-size:.74rem;}
.fin-pembanding{font-weight:700;font-size:.74rem;color:#0369a1;}
.fin-row--success{background:rgba(10,179,156,.06);}
.fin-row--success td{border-color:rgba(10,179,156,.15) !important;}
.fin-row--danger{background:rgba(240,101,72,.06);}
.fin-row--danger td{border-color:rgba(240,101,72,.15) !important;}
.fin-row--rata{background:rgba(247,184,75,.1);}
.fin-row--rata td{border-color:rgba(247,184,75,.3) !important;}
/* FOTO */
.fin-foto-strip{padding:7px 14px 0;}
.fin-foto-btn{display:inline-flex;align-items:center;padding:4px 11px;border:1px solid #fde68a;border-radius:5px;background:#fffbeb;color:#d97706;font-size:.76rem;font-weight:600;cursor:pointer;}
.fin-foto-overlay{position:fixed;inset:0;background:rgba(0,0,0,.68);z-index:9990;display:flex;align-items:center;justify-content:center;}
.fin-foto-modal{background:#fff;border-radius:14px;width:min(98vw,1160px);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);}
.fin-foto-modal-hdr{padding:14px 18px;background:linear-gradient(135deg,#1e293b,#334155);color:#fff;display:flex;align-items:center;justify-content:space-between;font-size:.88rem;flex-shrink:0;}
.fin-foto-modal-sub{color:rgba(255,255,255,.75);font-size:.78rem;}
.fin-foto-modal-close{background:rgba(255,255,255,.18);border:none;color:#fff;border-radius:7px;width:30px;height:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;}
.fin-foto-modal-body{overflow-y:auto;padding:22px;flex:1;}
.fin-foto-fade-enter-active,.fin-foto-fade-leave-active{transition:opacity .2s;}
.fin-foto-fade-enter-from,.fin-foto-fade-leave-to{opacity:0;}
.fin-foto-loading{display:flex;align-items:center;justify-content:center;min-height:120px;}
.fin-foto-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:120px;}
.fin-foto-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;}
@media(max-width:700px){.fin-foto-grid{grid-template-columns:repeat(2,1fr);}}
.fin-polaroid{background:#fff;border-radius:3px;padding:10px 10px 0;box-shadow:0 3px 10px rgba(0,0,0,.18),0 1px 3px rgba(0,0,0,.1);transition:transform .2s,box-shadow .2s;cursor:pointer;}
.fin-polaroid:hover{transform:scale(1.04) rotate(-0.5deg);box-shadow:0 8px 24px rgba(0,0,0,.22);}
.fin-polaroid-img-wrap{width:100%;aspect-ratio:1/1;overflow:hidden;background:#f0f2f5;border-radius:1px;position:relative;}
.fin-polaroid-img{width:100%;height:100%;object-fit:cover;display:block;}
.fin-polaroid-overlay{position:absolute;inset:0;background:rgba(217,119,6,.35);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .18s;color:#fff;font-size:1.4rem;}
.fin-polaroid:hover .fin-polaroid-overlay{opacity:1;}
.fin-polaroid-caption{font-size:.84rem;font-weight:700;text-align:center;padding:10px 8px 13px;color:#1e293b;line-height:1.45;word-break:break-word;border-top:2px solid #fde68a;margin-top:1px;background:#fff;}
.fin-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.93);z-index:1080;display:flex;align-items:center;justify-content:center;padding:20px;cursor:zoom-out;}
.fin-lightbox-close{position:absolute;top:16px;right:16px;border:none;background:rgba(255,255,255,.15);color:#fff;border-radius:50%;width:38px;height:38px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;cursor:pointer;transition:background .15s;}
.fin-lightbox-close:hover{background:rgba(255,255,255,.3);}
.fin-lightbox-inner{display:flex;flex-direction:column;align-items:center;max-width:90vw;max-height:90vh;cursor:default;}
.fin-lightbox-img{max-width:100%;max-height:80vh;object-fit:contain;border-radius:4px;box-shadow:0 8px 40px rgba(0,0,0,.6);}
.fin-lightbox-caption{margin-top:14px;background:rgba(255,255,255,.12);color:#fff;font-size:.92rem;font-weight:600;text-align:center;max-width:600px;padding:8px 20px;border-radius:8px;word-break:break-word;}
/* TIMELINE - Velzon */
.fin-vtl{padding:2px;}
.fin-vtl-hdr{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#64748b;padding:0 2px 10px;display:flex;align-items:center;}
.fin-vtl-steps{display:flex;flex-direction:column;}
.fin-vtl-step{display:flex;gap:10px;}
.fin-vtl-indicator{display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:30px;}
.fin-vtl-dot{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;flex-shrink:0;border:2px solid;}
.vtl-done .fin-vtl-dot{background:rgba(10,179,156,.1);border-color:#0ab39c;color:#0ab39c;}
.vtl-rejected .fin-vtl-dot{background:rgba(240,101,72,.1);border-color:#f06548;color:#f06548;}
.vtl-final .fin-vtl-dot{background:rgba(217,119,6,.1);border-color:#d97706;color:#d97706;}
.fin-vtl-line{width:2px;flex:1;min-height:10px;background:#e2e8f0;margin:2px 0;}
.vtl-done .fin-vtl-line{background:#0ab39c;}
.fin-vtl-body{padding-bottom:18px;flex:1;min-width:0;}
.fin-vtl-row1{display:flex;align-items:center;gap:7px;margin-bottom:3px;flex-wrap:wrap;}
.fin-vtl-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:4px;font-size:.7rem;font-weight:700;}
.vtl-badge--success{background:rgba(10,179,156,.1);color:#0ab39c;}
.vtl-badge--danger{background:rgba(240,101,72,.1);color:#f06548;}
.vtl-badge--primary{background:rgba(217,119,6,.1);color:#d97706;}
.vtl-badge--info{background:#ecfeff;color:#0e7490;}
.fin-vtl-sub{font-size:.68rem;font-weight:700;}
.fin-vtl-meta{display:flex;gap:10px;flex-wrap:wrap;font-size:.73rem;color:#64748b;margin-bottom:3px;}
.fin-vtl-details{font-size:.7rem;color:#64748b;background:#fffbeb;border-radius:5px;padding:5px 9px;margin-top:3px;}
.fin-vtl-details-hdr{font-weight:600;color:#475569;margin-bottom:2px;}
.fin-vtl-detail-row{display:flex;align-items:flex-start;gap:2px;line-height:1.6;}
.fin-vtl-user-group{border-left:2px solid #e2e8f0;margin-left:4px;padding-left:7px;margin-top:5px;}
.fin-vtl-user-hdr{display:flex;align-items:center;gap:4px;font-weight:600;color:#334155;font-size:.71rem;margin-bottom:2px;}
.fin-vtl-user-name{color:#405189;}
.fin-vtl-user-time{color:#94a3b8;font-weight:400;font-size:.66rem;}
.fin-vtl-note{font-size:.72rem;color:#64748b;margin-top:3px;font-style:italic;padding:3px 7px;background:#fef9c3;border-left:2px solid #fde68a;}
/* ACTION FOOTER */
.fin-action-footer{padding:11px 16px;background:#fff;border-top:1px solid #fde68a;flex-shrink:0;}
.fin-action-info{font-size:.78rem;}
.fin-action-warn{color:#b45309;font-weight:500;}
.fin-action-ok{color:#0ab39c;font-weight:500;}
/* MODALS */
.fin-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:1050;display:flex;align-items:center;justify-content:center;padding:14px;backdrop-filter:blur(2px);}
.fin-modal{background:#fff;border-radius:12px;width:100%;max-width:460px;box-shadow:0 20px 50px rgba(0,0,0,.22);overflow:hidden;}
.fin-modal--wide{max-width:600px;}
.fin-modal-hdr{display:flex;align-items:center;gap:10px;padding:14px 18px;color:#fff;}
.fin-modal-hdr--trial{background:linear-gradient(135deg,#92400e,#d97706);}
.fin-modal-hdr--neutral{background:linear-gradient(135deg,#1e293b,#334155);}
.fin-modal-title{font-weight:700;font-size:.92rem;}
.fin-modal-sub{font-size:.7rem;opacity:.85;}
.fin-modal-close{margin-left:auto;border:none;background:rgba(255,255,255,.2);color:#fff;border-radius:5px;padding:3px 7px;cursor:pointer;}
.fin-modal-body{padding:18px;}
.fin-confirm-row{display:flex;gap:10px;margin-bottom:7px;align-items:flex-start;}
.fin-confirm-lbl{font-size:.74rem;color:#64748b;min-width:86px;flex-shrink:0;padding-top:1px;}
.fin-confirm-val{font-size:.82rem;font-weight:600;color:#0f172a;}
.fin-modal-warn{padding:9px 12px;border-radius:7px;font-size:.8rem;margin-top:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;}
.fin-bulk-row{display:flex;align-items:center;gap:9px;padding:5px 9px;border-radius:5px;background:#fffbeb;margin-bottom:3px;}
.fin-bulk-code{font-size:.76rem;color:#d97706;font-family:monospace;}
.fin-modal-ftr{display:flex;justify-content:flex-end;gap:9px;padding:11px 18px;background:#fffbeb;border-top:1px solid #fde68a;}
.fin-error-analisa-row{display:flex;align-items:center;padding:7px 11px;border-radius:6px;background:#fff5f5;border:1px solid #fecaca;margin-bottom:4px;font-size:.82rem;font-weight:500;color:#991b1b;}
.fin-hidden-mobile{display:none !important;}
@media(min-width:768px){.fin-hidden-mobile{display:flex !important;}}
.fin-mobile-back{padding:9px 12px;border-bottom:1px solid #e2e8f0;flex-shrink:0;background:#fff;}
.fin-toast{position:fixed;bottom:18px;right:18px;padding:11px 16px;border-radius:9px;color:#fff;font-size:.82rem;z-index:9999;display:flex;align-items:center;animation:vz-slide-in .3s ease;box-shadow:0 4px 14px rgba(0,0,0,.16);}
.fin-toast--success{background:#0ab39c;}
.fin-toast--error{background:#f06548;}
@keyframes vz-slide-in{from{transform:translateX(110%);opacity:0}to{transform:translateX(0);opacity:1}}
</style>
