<template>
    <div class="vld-root">
        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- TOP BAR                                                        -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="vld-topbar">
            <div class="vld-topbar-left">
                <i class="ri-test-tube-line vld-topbar-icon"></i>
                <div>
                    <span class="vld-topbar-title">Validasi Hasil Trial</span>
                    <span class="vld-topbar-sub"
                        >Konfirmasi data formulator sebelum finalisasi</span
                    >
                </div>
            </div>
            <div class="vld-topbar-right">
                <div class="vld-stat" v-if="stats.total > 0">
                    <span class="vld-stat-num">{{ stats.total }}</span>
                    <span class="vld-stat-lbl">Total</span>
                </div>
                <span
                    class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2"
                >
                    <i class="ri-time-line me-1"></i>Menunggu Validasi
                </span>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- MAIN LAYOUT                                                     -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div class="vld-body">
            <!-- ─────────────────────────────── LEFT PANEL ─────────────── -->
            <div
                class="vld-left"
                :class="{ 'vld-hidden-mobile': detailVisible && isMobile }"
            >
                <!-- Filter toolbar -->
                <div class="vld-filter-bar">
                    <div class="vld-search-wrap">
                        <i class="ri-search-line vld-search-icon"></i>
                        <input
                            type="text"
                            class="vld-search-input"
                            placeholder="Cari No. Sampel, PO, Batch..."
                            v-model="searchQuery"
                        />
                    </div>
                    <div class="vld-filter-row">
                        <input
                            type="date"
                            class="vld-date-input"
                            v-model="filters.tanggal.mulai"
                            title="Dari Tanggal"
                        />
                        <span
                            class="text-muted"
                            style="font-size: 11px; flex-shrink: 0"
                            >—</span
                        >
                        <input
                            type="date"
                            class="vld-date-input"
                            v-model="filters.tanggal.selesai"
                            title="Sampai Tanggal"
                        />
                        <select class="vld-select" v-model="filters.qrcode">
                            <option value="">Semua QR</option>
                            <option value="multi">Multi QR</option>
                            <option value="single">Single QR</option>
                        </select>
                    </div>
                </div>

                <!-- List area -->
                <div class="vld-list">
                    <div v-if="loading.list" class="p-3">
                        <div
                            v-for="i in 7"
                            :key="i"
                            class="vld-skeleton mb-2"
                        ></div>
                    </div>

                    <div
                        v-else-if="listData.length === 0"
                        class="vld-empty-list"
                    >
                        <i class="ri-inbox-2-line"></i>
                        <p>{{ emptyMessage }}</p>
                        <button
                            class="btn btn-sm btn-soft-primary"
                            @click="resetFiltersAndFetch"
                        >
                            <i class="ri-refresh-line me-1"></i>Reset
                        </button>
                    </div>

                    <div v-else>
                        <div
                            v-for="(item, idx) in listData"
                            :key="idx"
                            class="vld-item-wrap"
                        >
                            <input
                                type="checkbox"
                                class="vld-item-checkbox"
                                :checked="isSelectedBulk(item)"
                                @change="toggleBulkSelect(item)"
                                @click.stop
                            />
                            <button
                                @click="selectItem(item)"
                                class="vld-item"
                                :class="{
                                    'vld-item--active': isSelected(item),
                                    'vld-item--checked': isSelectedBulk(item),
                                    'vld-item--lolos':
                                        item.Status_Sampel === 'Lolos Uji',
                                    'vld-item--tidak':
                                        item.Status_Sampel === 'Tidak Lolos Uji',
                                }"
                            >
                                <div class="vld-item-accent"></div>
                                <div class="vld-item-body">
                                    <div class="vld-item-top">
                                        <span class="vld-item-title">{{
                                            item.Jenis_Analisa
                                        }}</span>
                                        <span
                                            class="vld-badge"
                                            :class="
                                                item.Status_Sampel === 'Lolos Uji'
                                                    ? 'vld-badge--success'
                                                    : 'vld-badge--danger'
                                            "
                                        >
                                            {{
                                                item.Status_Sampel === "Lolos Uji"
                                                    ? "Lolos Uji"
                                                    : "Tidak Lolos"
                                            }}
                                        </span>
                                    </div>
                                    <div class="vld-item-sub">
                                        {{ item.po_info?.Kode_Barang || "" }}
                                        <template v-if="item.Nama_Barang"
                                            >— {{ item.Nama_Barang }}</template
                                        >
                                    </div>
                                    <div class="vld-item-meta">
                                        <code class="vld-code">{{
                                            item.No_Po_Sampel
                                        }}</code>
                                        <span
                                            class="vld-chip"
                                            :class="
                                                item.Flag_Multi_QrCode === 'Y'
                                                    ? 'vld-chip--blue'
                                                    : 'vld-chip--gray'
                                            "
                                        >
                                            <i class="ri-qr-code-line"></i>
                                            {{
                                                item.Flag_Multi_QrCode === "Y"
                                                    ? "Multi"
                                                    : "Single"
                                            }}
                                        </span>
                                        <span
                                            class="vld-chip vld-chip--gray"
                                            v-if="item.Tanggal"
                                        >
                                            <i class="ri-calendar-line"></i
                                            >{{ formatTanggal(item.Tanggal) }}
                                        </span>
                                    </div>
                                </div>
                                <i class="ri-arrow-right-s-line vld-item-arrow"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Bulk action bar -->
                <div v-if="selectedItems.length > 0" class="vld-bulk-bar">
                    <span class="vld-bulk-count"
                        ><i class="ri-checkbox-multiple-line me-1"></i
                        >{{ selectedItems.length }} analisa dipilih</span
                    >
                    <div class="d-flex gap-2 flex-wrap">
                        <button
                            class="btn btn-sm btn-outline-light"
                            @click="selectedItems = []"
                        >
                            Batal
                        </button>
                        <button
                            class="btn btn-sm fw-semibold"
                            style="background:#0ea5e9;color:#fff;border:none;"
                            @click="openBulkModal"
                        >
                            <i class="ri-refresh-line me-1"></i>Resampling
                        </button>
                        <button
                            class="btn btn-sm btn-primary fw-semibold"
                            @click="openBulkSimpanModal"
                        >
                            <i class="ri-check-double-line me-1"></i>Simpan Validasi
                        </button>
                    </div>
                </div>

                <!-- Pagination footer -->
                <div class="vld-list-footer" v-if="pagination.totalPage > 1">
                    <span class="vld-page-info"
                        >{{ listData.length }} /
                        {{ pagination.totalData }}</span
                    >
                    <div class="vld-page-btns">
                        <button
                            class="vld-page-btn"
                            :disabled="pagination.page === 1"
                            @click="changePage(pagination.page - 1)"
                        >
                            <i class="ri-arrow-left-s-line"></i>
                        </button>
                        <span class="vld-page-current"
                            >{{ pagination.page }} /
                            {{ pagination.totalPage }}</span
                        >
                        <button
                            class="vld-page-btn"
                            :disabled="pagination.page === pagination.totalPage"
                            @click="changePage(pagination.page + 1)"
                        >
                            <i class="ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ─────────────────────────────── RIGHT PANEL ────────────── -->
            <div
                class="vld-right"
                :class="{ 'vld-hidden-mobile': !detailVisible && isMobile }"
            >
                <!-- Mobile back -->
                <div v-if="isMobile && detailVisible" class="vld-mobile-back">
                    <button
                        class="btn btn-sm btn-soft-secondary"
                        @click="detailVisible = false"
                    >
                        <i class="ri-arrow-left-line me-1"></i>Daftar
                    </button>
                </div>

                <!-- ── EMPTY STATE ─────────────────────────────────────── -->
                <div v-if="!selectedItem" class="vld-detail-empty">
                    <div class="vld-detail-empty-inner">
                        <div class="vld-empty-icon-wrap">
                            <i class="ri-test-tube-line"></i>
                        </div>
                        <h6>Pilih sampel untuk validasi</h6>
                        <p>
                            Klik salah satu item dari daftar di sebelah kiri
                            untuk melihat detail hasil analisa dan melakukan
                            konfirmasi.
                        </p>
                    </div>
                </div>

                <!-- ── DETAIL CONTENT ──────────────────────────────────── -->
                <template v-else>
                    <!-- Sticky sample header -->
                    <div class="vld-detail-header">
                        <div class="vld-dh-main">
                            <div
                                class="vld-dh-icon"
                                :class="
                                    selectedItem.Status_Sampel === 'Lolos Uji'
                                        ? 'vld-dh-icon--success'
                                        : 'vld-dh-icon--danger'
                                "
                            >
                                <i
                                    :class="
                                        selectedItem.Status_Sampel ===
                                        'Lolos Uji'
                                            ? 'ri-checkbox-circle-line'
                                            : 'ri-close-circle-line'
                                    "
                                ></i>
                            </div>
                            <div>
                                <div class="vld-dh-title">
                                    {{ selectedItem.Jenis_Analisa }}
                                </div>
                                <div class="vld-dh-sub">
                                    {{ selectedItem.po_info?.Kode_Barang }}
                                    <template v-if="selectedItem.Nama_Barang">
                                        —
                                        {{ selectedItem.Nama_Barang }}</template
                                    >
                                </div>
                                <div class="vld-dh-badges">
                                    <span class="vld-badge vld-badge--blue">
                                        <i class="ri-barcode-line me-1"></i
                                        >{{ selectedItem.No_Po_Sampel }}
                                    </span>
                                    <span
                                        class="vld-badge"
                                        :class="
                                            selectedItem.Flag_Multi_QrCode ===
                                            'Y'
                                                ? 'vld-badge--blue'
                                                : 'vld-badge--gray'
                                        "
                                    >
                                        <i class="ri-qr-code-line me-1"></i>
                                        {{
                                            selectedItem.Flag_Multi_QrCode ===
                                            "Y"
                                                ? "Multi QR"
                                                : "Single QR"
                                        }}
                                    </span>
                                    <span
                                        class="vld-badge"
                                        :class="
                                            selectedItem.Status_Sampel ===
                                            'Lolos Uji'
                                                ? 'vld-badge--success'
                                                : 'vld-badge--danger'
                                        "
                                    >
                                        <i
                                            :class="
                                                selectedItem.Status_Sampel ===
                                                'Lolos Uji'
                                                    ? 'ri-checkbox-circle-line'
                                                    : 'ri-close-circle-line'
                                            "
                                            class="me-1"
                                        ></i>
                                        {{ selectedItem.Status_Sampel }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="vld-dh-meta">
                            <div class="vld-dh-meta-row">
                                <i class="ri-settings-3-line"></i
                                >{{ selectedItem.po_info?.Nama_Mesin || "—" }}
                            </div>
                            <div class="vld-dh-meta-row">
                                <i class="ri-receipt-line"></i
                                >{{ selectedItem.po_info?.No_Po || "—" }}
                            </div>
                            <div class="vld-dh-meta-row">
                                <i class="ri-git-branch-line"></i
                                >{{ selectedItem.po_info?.No_Split_Po || "—" }}
                            </div>
                            <div class="vld-dh-meta-row">
                                <i class="ri-stack-line"></i>Batch
                                {{ selectedItem.po_info?.No_Batch || "—" }}
                            </div>
                            <div class="vld-dh-meta-row">
                                <i class="ri-user-3-line"></i
                                >{{ selectedItem.Id_User || "—" }}
                            </div>
                        </div>
                    </div>

                    <!-- Sub-PO selector (Multi QR only) -->
                    <div
                        v-if="selectedItem.Flag_Multi_QrCode === 'Y'"
                        class="vld-subpo-bar"
                    >
                        <span class="vld-subpo-label"
                            ><i class="ri-layers-line me-1"></i>Sub Sampel</span
                        >
                        <div v-if="loading.subPo" class="vld-subpo-loading">
                            <span
                                class="spinner-border spinner-border-sm text-primary"
                            ></span>
                            <span
                                class="ms-2 text-muted"
                                style="font-size: 12px"
                                >Memuat...</span
                            >
                        </div>
                        <div v-else class="vld-subpo-tabs">
                            <button
                                v-for="(sub, si) in subPoList"
                                :key="si"
                                @click="selectSubPo(sub)"
                                class="vld-subpo-tab"
                                :class="{
                                    'vld-subpo-tab--active':
                                        selectedSubPo &&
                                        selectedSubPo.No_Fak_Sub_Po ===
                                            sub.No_Fak_Sub_Po,
                                }"
                            >
                                <i class="ri-file-list-3-line me-1"></i>
                                {{ sub.No_Fak_Sub_Po }}
                            </button>
                            <span
                                v-if="subPoList.length === 0"
                                class="text-muted"
                                style="font-size: 12px"
                                >Tidak ada sub sampel</span
                            >
                        </div>
                        <span class="vld-subpo-count" v-if="subPoList.length"
                            >{{ subPoList.length }} sub</span
                        >
                    </div>

                    <!-- Scrollable detail body -->
                    <div class="vld-detail-body">
                        <div v-if="loading.detail" class="vld-loading-state">
                            <div class="spinner-border text-primary"></div>
                            <p class="mt-3 text-muted small">
                                Memuat data hasil analisa...
                            </p>
                        </div>

                        <div
                            v-else-if="
                                selectedItem.Flag_Multi_QrCode === 'Y' &&
                                !selectedSubPo
                            "
                            class="vld-loading-state"
                        >
                            <i class="ri-cursor-line fs-1 text-muted"></i>
                            <p class="mt-2 text-muted small">
                                Pilih sub sampel di atas
                            </p>
                        </div>

                        <template v-else-if="detailData.length > 0">
                            <!-- Standard config warning -->
                            <div
                                v-if="!hasStandardConfiguration"
                                class="vld-alert-warn mb-3"
                            >
                                <i class="ri-alert-line me-2"></i>
                                <div>
                                    <strong>Standar belum dikonfigurasi</strong>
                                    <span
                                        class="d-block text-muted"
                                        style="font-size: 11px"
                                        >Tidak ada standar rentang — hasil
                                        dianggap layak secara otomatis.</span
                                    >
                                </div>
                            </div>

                            <!-- Mini stats row -->
                            <div class="vld-mini-stats mb-3">
                                <div class="vld-ms-item">
                                    <span class="vld-ms-val">{{
                                        detailData.length
                                    }}</span>
                                    <span class="vld-ms-lbl">Sampel Uji</span>
                                </div>
                                <div class="vld-ms-item vld-ms-item--success">
                                    <span class="vld-ms-val">{{
                                        detailData.filter(
                                            (d) => d.Flag_Layak === "Y"
                                        ).length
                                    }}</span>
                                    <span class="vld-ms-lbl">Layak</span>
                                </div>
                                <div class="vld-ms-item vld-ms-item--danger">
                                    <span class="vld-ms-val">{{
                                        detailData.filter(
                                            (d) => d.Flag_Layak !== "Y"
                                        ).length
                                    }}</span>
                                    <span class="vld-ms-lbl">Tidak Layak</span>
                                </div>
                                <div
                                    class="vld-ms-item vld-ms-item--info"
                                    v-if="formulaAverages.length > 0"
                                >
                                    <span class="vld-ms-val">{{
                                        formulaAverages[0]
                                    }}</span>
                                    <span class="vld-ms-lbl">Rata-rata</span>
                                </div>
                            </div>

                            <!-- Section: Chart -->
                            <div class="vld-section">
                                <div
                                    class="vld-section-hd"
                                    @click="sections.chart = !sections.chart"
                                    style="cursor: pointer"
                                >
                                    <span
                                        ><i
                                            class="ri-bar-chart-2-line me-2 text-primary"
                                        ></i
                                        >Durasi Proses</span
                                    >
                                    <i
                                        :class="
                                            sections.chart
                                                ? 'ri-arrow-up-s-line'
                                                : 'ri-arrow-down-s-line'
                                        "
                                        class="text-muted"
                                    ></i>
                                </div>
                                <div
                                    v-if="sections.chart"
                                    class="vld-section-body p-0"
                                >
                                    <apexchart
                                        height="180"
                                        type="bar"
                                        :options="durationChartOptions"
                                        :series="durationChartSeries"
                                    ></apexchart>
                                </div>
                            </div>

                            <!-- Section: Data Table -->
                            <div class="vld-section">
                                <div class="vld-section-hd">
                                    <span
                                        ><i
                                            class="ri-table-line me-2 text-primary"
                                        ></i
                                        >Data Hasil Analisa</span
                                    >
                                    <span
                                        class="badge bg-primary-subtle text-primary"
                                        >{{ detailData.length }} baris</span
                                    >
                                </div>
                                <div class="vld-section-body p-0">
                                    <div class="table-responsive">
                                        <table
                                            class="table table-sm table-bordered align-middle mb-0 vld-table"
                                        >
                                            <thead>
                                                <tr>
                                                    <th
                                                        class="text-center"
                                                        style="width: 36px"
                                                    >
                                                        #
                                                    </th>
                                                    <th>No Transaksi</th>
                                                    <th>No Sampel</th>
                                                    <th>No PO</th>
                                                    <th>Split PO</th>
                                                    <th>Batch</th>
                                                    <th>Tanggal</th>
                                                    <th
                                                        v-for="param in template.parameter"
                                                        :key="param.id_qc"
                                                    >
                                                        {{
                                                            param.nama_parameter
                                                        }}
                                                    </th>
                                                    <template
                                                        v-if="
                                                            template.formula &&
                                                            template.formula
                                                                .length > 0 &&
                                                            formulaAverages.length >
                                                                0
                                                        "
                                                    >
                                                        <th
                                                            v-for="(
                                                                f, fi
                                                            ) in template.formula"
                                                            :key="'fh-' + fi"
                                                            class="text-primary"
                                                        >
                                                            {{ f.nama_kolom }}
                                                        </th>
                                                    </template>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(
                                                        row, ri
                                                    ) in detailData"
                                                    :key="ri"
                                                    :class="
                                                        row.Kode_Analisa ===
                                                        'MBLG-STR'
                                                            ? ''
                                                            : row.Flag_Layak ===
                                                              'Y'
                                                            ? 'table-success'
                                                            : 'table-danger'
                                                    "
                                                >
                                                    <td
                                                        class="text-center fw-semibold"
                                                    >
                                                        {{ ri + 1 }}
                                                    </td>
                                                    <td>{{ row.No_Faktur }}</td>
                                                    <td>
                                                        {{ row.No_Po_Sampel }}
                                                    </td>
                                                    <td>{{ row.No_Po }}</td>
                                                    <td>
                                                        {{ row.No_Split_Po }}
                                                    </td>
                                                    <td>{{ row.No_Batch }}</td>
                                                    <td>
                                                        {{
                                                            formatTanggal(
                                                                row.Tanggal
                                                            )
                                                        }}
                                                    </td>
                                                    <td
                                                        v-for="(
                                                            pv, pi
                                                        ) in row.parameters"
                                                        :key="
                                                            'p-' + ri + '-' + pi
                                                        "
                                                    >
                                                        {{ pv }}
                                                    </td>
                                                    <template
                                                        v-if="
                                                            template.formula &&
                                                            template.formula
                                                                .length > 0 &&
                                                            formulaAverages.length >
                                                                0
                                                        "
                                                    >
                                                        <td
                                                            v-for="(
                                                                f, fi
                                                            ) in template.formula"
                                                            :key="
                                                                'fc-' +
                                                                ri +
                                                                '-' +
                                                                fi
                                                            "
                                                            class="fw-semibold"
                                                        >
                                                            {{
                                                                row.results[
                                                                    fi
                                                                ] &&
                                                                row.results[fi]
                                                                    .value !==
                                                                    undefined
                                                                    ? row
                                                                          .results[
                                                                          fi
                                                                      ].value
                                                                    : "—"
                                                            }}
                                                        </td>
                                                    </template>
                                                </tr>
                                                <!-- Rata-rata row -->
                                                <tr
                                                    v-if="
                                                        template.formula &&
                                                        template.formula
                                                            .length > 0 &&
                                                        formulaAverages.length >
                                                            0
                                                    "
                                                    class="vld-row--avg"
                                                >
                                                    <td
                                                        :colspan="
                                                            7 +
                                                            (template.parameter
                                                                ? template
                                                                      .parameter
                                                                      .length
                                                                : 0)
                                                        "
                                                        class="text-end fw-bold pe-3"
                                                    >
                                                        Rata-Rata
                                                    </td>
                                                    <td
                                                        v-for="(
                                                            avg, ai
                                                        ) in formulaAverages"
                                                        :key="'avg-' + ai"
                                                        class="fw-bold text-primary"
                                                    >
                                                        {{ avg }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Section: Photos -->
                            <div
                                v-if="
                                    informasiData &&
                                    informasiData.sesi_foto === 'Y'
                                "
                                class="vld-section"
                            >
                                <div
                                    class="vld-section-hd"
                                    @click="sections.foto = !sections.foto"
                                    style="cursor: pointer"
                                >
                                    <span
                                        ><i
                                            class="ri-camera-line me-2 text-primary"
                                        ></i
                                        >Dokumentasi Foto</span
                                    >
                                    <i
                                        :class="
                                            sections.foto
                                                ? 'ri-arrow-up-s-line'
                                                : 'ri-arrow-down-s-line'
                                        "
                                        class="text-muted"
                                    ></i>
                                </div>
                                <div
                                    v-if="sections.foto"
                                    class="vld-section-body"
                                >
                                    <template
                                        v-for="(row, ri) in detailData"
                                        :key="'fp-' + ri"
                                    >
                                        <div
                                            v-if="
                                                row.foto_analisa &&
                                                row.foto_analisa.length > 0
                                            "
                                            class="mb-3"
                                        >
                                            <p
                                                class="text-muted mb-2"
                                                style="font-size: 11px"
                                            >
                                                <i
                                                    class="ri-barcode-line me-1"
                                                ></i
                                                >{{ row.No_Faktur }}
                                            </p>
                                            <div class="row g-2">
                                                <div
                                                    v-for="foto in row.foto_analisa"
                                                    :key="foto.Berkas_Key"
                                                    class="col-6 col-md-4 col-xl-3"
                                                >
                                                    <div
                                                        class="border rounded overflow-hidden"
                                                    >
                                                        <div
                                                            v-if="
                                                                !fotoBlobUrls[
                                                                    foto
                                                                        .Berkas_Key
                                                                ]
                                                            "
                                                            class="d-flex align-items-center justify-content-center bg-light"
                                                            style="
                                                                height: 100px;
                                                            "
                                                        >
                                                            <div
                                                                class="spinner-grow spinner-grow-sm text-primary"
                                                            ></div>
                                                        </div>
                                                        <el-image
                                                            v-else
                                                            class="w-100"
                                                            style="
                                                                height: 100px;
                                                                display: block;
                                                            "
                                                            :src="
                                                                fotoBlobUrls[
                                                                    foto
                                                                        .Berkas_Key
                                                                ]
                                                            "
                                                            :preview-src-list="
                                                                row.foto_analisa
                                                                    .map(
                                                                        (f) =>
                                                                            fotoBlobUrls[
                                                                                f
                                                                                    .Berkas_Key
                                                                            ]
                                                                    )
                                                                    .filter(
                                                                        Boolean
                                                                    )
                                                            "
                                                            :initial-index="
                                                                row.foto_analisa.findIndex(
                                                                    (f) =>
                                                                        f.Berkas_Key ===
                                                                        foto.Berkas_Key
                                                                )
                                                            "
                                                            fit="cover"
                                                            hide-on-click-modal
                                                            @contextmenu.prevent
                                                            @dragstart.prevent
                                                        >
                                                            <template #error>
                                                                <div
                                                                    class="d-flex align-items-center justify-content-center bg-light text-muted w-100"
                                                                    style="
                                                                        height: 100px;
                                                                    "
                                                                >
                                                                    <i
                                                                        class="ri-image-line fs-4"
                                                                    ></i>
                                                                </div>
                                                            </template>
                                                        </el-image>
                                                        <p
                                                            class="text-center text-muted mb-0 py-1"
                                                            style="
                                                                font-size: 10px;
                                                            "
                                                        >
                                                            {{
                                                                foto.Keterangan ||
                                                                "—"
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Section: Timeline -->
                            <div class="vld-section">
                                <div
                                    class="vld-section-hd"
                                    @click="
                                        sections.timeline = !sections.timeline
                                    "
                                    style="cursor: pointer"
                                >
                                    <span
                                        ><i
                                            class="ri-calendar-event-line me-2 text-primary"
                                        ></i
                                        >Timeline Proses</span
                                    >
                                    <i
                                        :class="
                                            sections.timeline
                                                ? 'ri-arrow-up-s-line'
                                                : 'ri-arrow-down-s-line'
                                        "
                                        class="text-muted"
                                    ></i>
                                </div>
                                <div
                                    v-if="sections.timeline"
                                    class="vld-section-body p-0"
                                >
                                    <apexchart
                                        height="200"
                                        type="rangeBar"
                                        :options="timelineChartOptions"
                                        :series="timelineChartSeries"
                                    ></apexchart>
                                </div>
                            </div>
                        </template>

                        <div
                            v-else-if="!loading.detail"
                            class="vld-loading-state"
                        >
                            <i class="ri-file-unknow-line fs-1 text-muted"></i>
                            <p class="mt-2 text-muted small">
                                Tidak ada data hasil analisa.
                            </p>
                        </div>
                    </div>

                    <!-- Sticky action bar -->
                    <div
                        class="vld-action-bar"
                        v-if="
                            detailData.length > 0 ||
                            (selectedItem &&
                                selectedItem.Flag_Multi_QrCode !== 'Y')
                        "
                    >
                        <div
                            class="vld-action-info"
                            v-if="
                                selectedSubPo ||
                                selectedItem.Flag_Multi_QrCode !== 'Y'
                            "
                        >
                            <i class="ri-information-line text-muted me-1"></i>
                            <span class="text-muted" style="font-size: 11px">
                                {{
                                    selectedSubPo
                                        ? selectedSubPo.No_Fak_Sub_Po
                                        : selectedItem.No_Po_Sampel
                                }}
                            </span>
                        </div>
                        <div class="vld-action-info" v-else></div>
                        <div class="d-flex gap-2">
                            <button
                                class="btn btn-sm btn-warning text-white"
                                v-if="detailData.length > 0"
                                @click="openReanalisis"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#offcanvasReanalisisFormulatorTrial"
                            >
                                <i class="ri-refresh-line me-1"></i>Uji Ulang
                            </button>
                            <button
                                class="btn btn-sm btn-success"
                                v-if="detailData.length > 0"
                                @click="selesaikanAnalisa"
                                :disabled="loading.saving"
                            >
                                <span
                                    v-if="loading.saving"
                                    class="spinner-border spinner-border-sm me-1"
                                ></span>
                                <i v-else class="ri-check-double-line me-1"></i>
                                Konfirmasi & Simpan
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════ -->
        <!-- OFFCANVAS: REANALISIS                                          -->
        <!-- ══════════════════════════════════════════════════════════════ -->
        <div
            class="offcanvas offcanvas-end"
            tabindex="-1"
            id="offcanvasReanalisisFormulatorTrial"
        >
            <div class="offcanvas-header border-bottom">
                <h5 class="mb-0 fw-semibold fs-6">
                    <i class="ri-refresh-line me-2 text-warning"></i>Uji Ulang
                    (Reanalisis)
                </h5>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    @click="resetReanalisisForm"
                ></button>
            </div>
            <div class="offcanvas-body">
                <!-- ── SINGLE QR MODE ─────────────────────────────────── -->
                <template v-if="reanalisisForm.isSingle">
                    <div class="vld-reanalisis-info-box mb-4">
                        <div class="vld-rib-icon">
                            <i class="ri-qr-code-line"></i>
                        </div>
                        <div>
                            <p class="fw-semibold mb-1" style="font-size: 13px">
                                Single QRCode Terdeteksi
                            </p>
                            <p
                                class="text-muted mb-0"
                                style="font-size: 12px; line-height: 1.6"
                            >
                                Sampel ini menggunakan
                                <strong>Single QRCode</strong>, sehingga
                                pengujian ulang akan menggunakan nomor sampel
                                yang sama.
                            </p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold small"
                            >No Sampel (akan diuji ulang)</label
                        >
                        <div class="d-flex align-items-center gap-2">
                            <input
                                :value="reanalisisForm.noUjiSebelumnya"
                                type="text"
                                disabled
                                class="form-control form-control-sm bg-light"
                            />
                            <span
                                class="badge bg-secondary-subtle text-secondary border"
                                style="white-space: nowrap; font-size: 10px"
                            >
                                <i class="ri-qr-code-line me-1"></i>Single QR
                            </span>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="submitReanalisisSingle"
                            :disabled="loading.reanalisis"
                        >
                            <span
                                v-if="loading.reanalisis"
                                class="spinner-border spinner-border-sm me-2"
                            ></span>
                            <i v-else class="ri-send-plane-line me-1"></i>
                            Lakukan Uji Ulang
                        </button>
                    </div>
                </template>

                <!-- ── MULTI QR MODE ──────────────────────────────────── -->
                <template v-else>
                    <div
                        class="alert alert-warning border-0 border-start border-warning border-3 py-2 mb-4"
                    >
                        <small class="text-muted"
                            >Pilih nomor sub sampel reanalisis untuk
                            menggantikan pengujian saat ini.</small
                        >
                    </div>
                    <form @submit.prevent="submitReanalisis">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small"
                                >No Uji Sebelumnya</label
                            >
                            <input
                                :value="reanalisisForm.noUjiSebelumnya"
                                type="text"
                                disabled
                                class="form-control form-control-sm bg-light"
                            />
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold small"
                                >No Sampel Reanalisis</label
                            >
                            <div
                                v-if="loading.reanalisisOptions"
                                class="placeholder-glow"
                            >
                                <span
                                    class="placeholder col-12 bg-secondary rounded"
                                    style="height: 38px; opacity: 0.3"
                                ></span>
                            </div>
                            <v-select
                                v-else
                                v-model="reanalisisForm.selectedOption"
                                :options="reanalisisOptions"
                                label="name"
                                placeholder="— Pilih No Sampel —"
                            />
                        </div>
                        <div class="d-grid">
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="
                                    loading.reanalisis ||
                                    loading.reanalisisOptions ||
                                    !reanalisisForm.selectedOption
                                "
                            >
                                <span
                                    v-if="loading.reanalisis"
                                    class="spinner-border spinner-border-sm me-2"
                                ></span>
                                <i v-else class="ri-send-plane-line me-1"></i>
                                Lakukan Uji Ulang
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    <!-- BULK VALIDASI FORMULATOR MODAL -->
    <!-- RESAMPLING MODAL -->
    <div class="modal fade" id="bulkValidasiFormulatorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info bg-opacity-10 border-info border-opacity-25">
                    <h5 class="modal-title fw-bold">
                        <i class="ri-refresh-line me-2 text-info"></i>
                        Resampling Analisa
                        <span class="badge bg-info text-white ms-2">{{ selectedItems.length }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div v-if="loading.bulkSubPo" class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm text-info me-2"></div>
                        Memuat opsi sub sampel...
                    </div>
                    <template v-else>
                        <p class="text-muted small mb-3">
                            <i class="ri-information-line me-1 text-info"></i>
                            Untuk item <strong>Multi QR</strong>, pilih nomor sub sampel yang akan diresampling.
                        </p>
                        <div
                            v-for="(item, idx) in selectedItems"
                            :key="idx"
                            class="border rounded-3 p-3 mb-3"
                            :class="item.Flag_Multi_QrCode === 'Y' ? 'border-info border-opacity-50 bg-info bg-opacity-10' : 'border-secondary border-opacity-25'"
                        >
                            <div class="d-flex align-items-start gap-3">
                                <div class="rounded-circle bg-info bg-opacity-25 d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px">
                                    <i class="ri-refresh-line text-info"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold">{{ item.Jenis_Analisa }}</div>
                                    <div class="d-flex gap-2 flex-wrap mt-1">
                                        <code class="small bg-light px-2 py-1 rounded">{{ item.No_Po_Sampel }}</code>
                                        <span class="badge" :class="item.Flag_Multi_QrCode === 'Y' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'">
                                            <i class="ri-qr-code-line me-1"></i>{{ item.Flag_Multi_QrCode === "Y" ? "Multi QR" : "Single QR" }}
                                        </span>
                                        <span class="badge" :class="item.Status_Sampel === 'Lolos Uji' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                            {{ item.Status_Sampel }}
                                        </span>
                                    </div>
                                    <div v-if="item.Flag_Multi_QrCode === 'Y'" class="mt-2">
                                        <label class="form-label small fw-semibold mb-1">
                                            <i class="ri-list-check me-1 text-info"></i>Pilih No. Sub Sampel untuk Resampling:
                                        </label>
                                        <select class="form-select form-select-sm" v-model="bulkSubPoSelected[item.No_Po_Sampel + '|' + item.Id_Jenis_Analisa]">
                                            <option :value="undefined" disabled>— Pilih Sub Sampel —</option>
                                            <option v-for="sub in bulkSubPoMap[item.No_Po_Sampel + '|' + item.Id_Jenis_Analisa] || []" :key="sub.No_Fak_Sub_Po" :value="sub.No_Fak_Sub_Po">
                                                {{ sub.No_Fak_Sub_Po }}
                                            </option>
                                        </select>
                                        <div v-if="!(bulkSubPoMap[item.No_Po_Sampel + '|' + item.Id_Jenis_Analisa] || []).length" class="text-danger small mt-1">
                                            <i class="ri-error-warning-line me-1"></i>Tidak ada sub sampel tersedia.
                                        </div>
                                    </div>
                                    <div v-else class="mt-1 text-muted small">
                                        <i class="ri-information-line me-1"></i>Single QR — tidak memerlukan pilihan sub sampel
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-info text-white fw-semibold" :disabled="!canSubmitBulk || loading.bulkSubmit || loading.bulkSubPo" @click="submitBulk">
                        <span v-if="loading.bulkSubmit" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-refresh-line me-1"></i>
                        Resampling {{ selectedItems.length }} Analisa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SIMPAN VALIDASI MODAL -->
    <div class="modal fade" id="bulkSimpanValidasiFormulatorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary bg-opacity-10 border-primary border-opacity-25">
                    <h5 class="modal-title fw-bold">
                        <i class="ri-check-double-line me-2 text-primary"></i>
                        Simpan Validasi
                        <span class="badge bg-primary ms-2">{{ selectedItems.length }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        <i class="ri-information-line me-1 text-primary"></i>
                        Analisa berikut akan disimpan sebagai <strong>hasil validasi</strong>. Untuk item Multi QR, semua sub sampel pada analisa tersebut akan divalidasi sekaligus.
                    </p>
                    <div
                        v-for="(item, idx) in selectedItems"
                        :key="idx"
                        class="border rounded-3 p-3 mb-2 d-flex align-items-start gap-3"
                        :class="item.Flag_Multi_QrCode === 'Y' ? 'border-primary border-opacity-50 bg-primary bg-opacity-10' : 'border-secondary border-opacity-25'"
                    >
                        <div
                            class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                            :class="item.Flag_Multi_QrCode === 'Y' ? 'bg-primary bg-opacity-25' : 'bg-success bg-opacity-10'"
                            style="width:36px;height:36px"
                        >
                            <i :class="item.Flag_Multi_QrCode === 'Y' ? 'ri-layers-line text-primary' : 'ri-check-line text-success'"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold small">{{ item.Jenis_Analisa }}</div>
                            <div class="d-flex gap-2 flex-wrap mt-1">
                                <code class="small bg-light px-2 py-1 rounded">{{ item.No_Po_Sampel }}</code>
                                <span class="badge" :class="item.Flag_Multi_QrCode === 'Y' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'">
                                    <i class="ri-qr-code-line me-1"></i>{{ item.Flag_Multi_QrCode === 'Y' ? 'Multi QR' : 'Single QR' }}
                                </span>
                                <span class="badge" :class="item.Status_Sampel === 'Lolos Uji' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                    {{ item.Status_Sampel }}
                                </span>
                            </div>
                            <div class="mt-1 small" :class="item.Flag_Multi_QrCode === 'Y' ? 'text-primary' : 'text-success'">
                                <i :class="item.Flag_Multi_QrCode === 'Y' ? 'ri-layers-line me-1' : 'ri-checkbox-circle-line me-1'"></i>
                                {{ item.Flag_Multi_QrCode === 'Y' ? 'Semua sub sampel akan divalidasi' : 'Siap divalidasi' }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary fw-semibold" :disabled="loading.bulkSubmit" @click="submitBulkSimpan">
                        <span v-if="loading.bulkSubmit" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-check-double-line me-1"></i>
                        Simpan Validasi {{ selectedItems.length }} Analisa
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ApexChart from "vue3-apexcharts";
import { ElImage } from "element-plus";
import axios from "axios";
import vSelect from "vue-select";
import { debounce } from "lodash";

export default {
    components: { apexchart: ApexChart, ElImage, vSelect },

    data() {
        return {
            listData: [],
            searchQuery: "",
            filters: { tanggal: { mulai: "", selesai: "" }, qrcode: "" },
            pagination: { page: 1, limit: 12, totalPage: 0, totalData: 0 },
            loading: {
                list: false,
                subPo: false,
                detail: false,
                saving: false,
                reanalisis: false,
                reanalisisOptions: false,
                bulkSubPo: false,
                bulkSubmit: false,
            },

            selectedItem: null,
            detailVisible: false,
            isMobile: window.innerWidth < 992,

            subPoList: [],
            selectedSubPo: null,

            selectedItems: [],
            bulkSubPoMap: {},
            bulkSubPoSelected: {},
            bulkModalInstance: null,
            bulkSimpanModalInstance: null,

            detailData: [],
            formulaAverages: [],
            informasiData: null,
            hasStandardConfiguration: true,
            template: { parameter: [], formula: [] },
            fotoBlobUrls: {},

            sections: { chart: true, foto: true, timeline: false },

            reanalisisOptions: [],
            reanalisisForm: {
                isSingle: false,
                noUjiSebelumnya: null,
                selectedOption: null,
                noPo: null,
                idJenisAnalisa: null,
            },
        };
    },

    computed: {
        stats() {
            return { total: this.pagination.totalData };
        },

        canSubmitBulk() {
            if (!this.selectedItems.length) return false;
            return this.selectedItems.every((item) => {
                if (item.Flag_Multi_QrCode === "Y") {
                    const key = item.No_Po_Sampel + "|" + item.Id_Jenis_Analisa;
                    return !!this.bulkSubPoSelected[key];
                }
                return true;
            });
        },

        emptyMessage() {
            return this.searchQuery ||
                this.filters.tanggal.mulai ||
                this.filters.qrcode
                ? "Tidak ada data sesuai filter."
                : "Belum ada data menunggu validasi.";
        },

        durationChartSeries() {
            if (!this.detailData.length) return [];
            return [
                {
                    name: "Lama Proses (Hari)",
                    data: this.detailData.map((item) => {
                        if (!item.Tanggal_Registrasi || !item.Tanggal) return 0;
                        return Math.max(
                            0,
                            Math.round(
                                (new Date(item.Tanggal) -
                                    new Date(item.Tanggal_Registrasi)) /
                                    86400000
                            )
                        );
                    }),
                },
            ];
        },

        durationChartOptions() {
            if (!this.detailData.length) return {};
            return {
                chart: {
                    type: "bar",
                    height: 180,
                    toolbar: { show: false },
                    fontFamily: "inherit",
                },
                plotOptions: {
                    bar: {
                        columnWidth: "45%",
                        dataLabels: { position: "top" },
                    },
                },
                dataLabels: {
                    enabled: true,
                    formatter: (v) => v + "h",
                    offsetY: -14,
                    style: { fontSize: "11px", colors: ["#304758"] },
                },
                xaxis: {
                    categories: this.detailData.map(
                        (i) => i.No_Faktur || i.No_Po_Sampel
                    ),
                    labels: { style: { fontSize: "10px" } },
                },
                yaxis: { labels: { style: { fontSize: "10px" } } },
                grid: { padding: { top: 0, bottom: 0 } },
                colors: ["#405189"],
            };
        },

        timelineChartSeries() {
            if (!this.detailData.length) return [];
            const proses = [],
                tunggu = [];
            this.detailData.forEach((item) => {
                if (!item.Tanggal_Registrasi || !item.Tanggal) return;
                const reg = new Date(item.Tanggal_Registrasi),
                    test = new Date(item.Tanggal);
                proses.push({
                    x: item.No_Faktur || item.No_Po_Sampel,
                    y: [reg.getTime(), test.getTime()],
                });
                const ts = new Date(reg);
                ts.setDate(reg.getDate() + 1);
                ts.setHours(0, 0, 0, 0);
                const te = new Date(test);
                te.setDate(test.getDate() - 1);
                te.setHours(23, 59, 59, 999);
                if (te > ts)
                    tunggu.push({
                        x: item.No_Faktur || item.No_Po_Sampel,
                        y: [ts.getTime(), te.getTime()],
                    });
            });
            return [
                { name: "Total Proses", data: proses },
                { name: "Periode Tunggu", data: tunggu },
            ];
        },

        timelineChartOptions() {
            return {
                chart: {
                    type: "rangeBar",
                    height: 200,
                    toolbar: { show: false },
                    fontFamily: "inherit",
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        barHeight: "60%",
                        rangeBarGroupRows: true,
                    },
                },
                colors: ["#405189", "#f06548"],
                fill: { type: "solid", opacity: 0.85 },
                xaxis: {
                    type: "datetime",
                    labels: {
                        datetimeUTC: false,
                        format: "dd MMM",
                        style: { fontSize: "10px" },
                    },
                },
                yaxis: { labels: { style: { fontSize: "10px" } } },
                legend: { position: "top", fontSize: "11px" },
                grid: { padding: { top: 0 } },
            };
        },
    },

    methods: {
        isSelected(item) {
            return (
                this.selectedItem &&
                this.selectedItem.No_Po_Sampel === item.No_Po_Sampel &&
                this.selectedItem.Id_Jenis_Analisa === item.Id_Jenis_Analisa
            );
        },

        async fetchList(page = 1) {
            this.loading.list = true;
            try {
                const params = {
                    page,
                    limit: this.pagination.limit,
                    q: this.searchQuery,
                    qrcode: this.filters.qrcode || null,
                };
                if (
                    this.filters.tanggal.mulai &&
                    this.filters.tanggal.selesai
                ) {
                    params.tanggal_mulai = this.filters.tanggal.mulai;
                    params.tanggal_selesai = this.filters.tanggal.selesai;
                }
                const res = await axios.get(
                    "/api/v1/formulator/validasi-hasil/uji-trial/current",
                    { params }
                );
                if (res.data?.result) {
                    this.listData = res.data.result.data;
                    this.pagination = res.data.result.pagination;
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
            this.filters = { tanggal: { mulai: "", selesai: "" }, qrcode: "" };
            this.fetchList(1);
        },

        async selectItem(item) {
            this.selectedItem = item;
            this.selectedSubPo = null;
            this.subPoList = [];
            this.clearDetail();
            this.detailVisible = true;
            if (item.Flag_Multi_QrCode === "Y") {
                await this.fetchSubPoList(
                    item.No_Po_Sampel,
                    item.Id_Jenis_Analisa
                );
            } else {
                await this.fetchDetailSingle(
                    item.No_Po_Sampel,
                    item.Id_Jenis_Analisa
                );
            }
        },

        clearDetail() {
            this.detailData = [];
            this.formulaAverages = [];
            this.informasiData = null;
            this.hasStandardConfiguration = true;
            this.template = { parameter: [], formula: [] };
            this.revokeBlobUrls();
        },

        async fetchSubPoList(noPo, idJenisAnalisa) {
            this.loading.subPo = true;
            try {
                const res = await axios.get(
                    `/api/v1/formulator/validasi-hasil/uji-trial/sub/menunggu/${noPo}/${idJenisAnalisa}`
                );
                this.subPoList = res.data?.success ? res.data.result || [] : [];
            } catch {
                this.subPoList = [];
            } finally {
                this.loading.subPo = false;
            }
        },

        async selectSubPo(sub) {
            this.selectedSubPo = sub;
            this.clearDetail();
            await this.fetchDetailMulti(
                this.selectedItem.No_Po_Sampel,
                sub.No_Fak_Sub_Po,
                this.selectedItem.Id_Jenis_Analisa
            );
        },

        async fetchDetailMulti(noPo, noFakSub, idJenisAnalisa) {
            this.loading.detail = true;
            try {
                const [dataRes, tplRes] = await Promise.all([
                    axios.get(
                        `/api/v1/formulator/validasi-hasil/uji-trial/verifikasi-analisa/multi/${idJenisAnalisa}/${noPo}/${noFakSub}`
                    ),
                    axios.get(
                        `/api/v1/formulator/uji-trial/${idJenisAnalisa}/parameter-perhitungan-old`
                    ),
                ]);
                this.applyDetail(dataRes, tplRes);
            } catch {
                this.detailData = [];
            } finally {
                this.loading.detail = false;
            }
        },

        async fetchDetailSingle(noPo, idJenisAnalisa) {
            this.loading.detail = true;
            try {
                const [dataRes, tplRes] = await Promise.all([
                    axios.get(
                        `/api/v1/formulator/validasi-hasil/uji-trial/verifikasi-analisa/single-qrcode/${idJenisAnalisa}/${noPo}`
                    ),
                    axios.get(
                        `/api/v1/formulator/uji-trial/${idJenisAnalisa}/parameter-perhitungan-old`
                    ),
                ]);
                this.applyDetail(dataRes, tplRes);
            } catch {
                this.detailData = [];
            } finally {
                this.loading.detail = false;
            }
        },

        applyDetail(dataRes, tplRes) {
            if (
                dataRes.data?.success &&
                Array.isArray(dataRes.data.result?.sampel)
            ) {
                this.template = tplRes.data?.result || {
                    parameter: [],
                    formula: [],
                };
                this.informasiData = dataRes.data.result.informasi;
                this.hasStandardConfiguration =
                    dataRes.data.result.informasi?.has_standard_configuration ??
                    true;
                const { data, formulaAverages } = this.processItems(
                    dataRes.data.result.sampel,
                    this.template
                );
                this.detailData = data;
                this.formulaAverages = formulaAverages;
                this.fetchBlobPhotos();
            }
        },

        processItems(items, template) {
            if (!Array.isArray(items) || !items.length)
                return { data: [], formulaAverages: [] };
            const grouped = items.reduce((acc, item) => {
                (acc[item.No_Faktur] = acc[item.No_Faktur] || []).push(item);
                return acc;
            }, {});
            const processedData = Object.values(grouped).map((group) => {
                const first = group[0];
                return {
                    No_Po: first.No_Po || "—",
                    No_Split_Po: first.No_Split_Po || "—",
                    No_Batch: first.No_Batch || "—",
                    No_Faktur: first.No_Faktur || "—",
                    Kode_Analisa: first.Kode_Analisa || "—",
                    Flag_Layak: first.Flag_Layak || "—",
                    No_Po_Sampel: first.No_Po_Sampel || "—",
                    No_Fak_Sub_Po: first.No_Fak_Sub_Po || "—",
                    Id_Mesin: first.Id_Mesin,
                    Id_Jenis_Analisa: first.Id_Jenis_Analisa,
                    Tahapan_Ke: first.Tahapan_Ke,
                    Flag_Multi_QrCode: first.Flag_Multi_QrCode,
                    Tanggal: first.Tanggal_Pengujian || "—",
                    Tanggal_Registrasi: first.Tanggal_Registrasi || "—",
                    parameters: Array.isArray(first.parameter)
                        ? first.parameter.map((p) => p.Hasil_Analisa ?? "—")
                        : [],
                    results: group.map((item) => ({
                        value: item.Hasil_Akhir_Analisa ?? "—",
                        Flag_Layak: item.Flag_Layak,
                        pembulatan: item.Pembulatan ?? 4,
                    })),
                    Range_Awal: first.Range_Awal,
                    Range_Akhir: first.Range_Akhir,
                    foto_analisa: first.foto_analisa || [],
                };
            });
            const numFormula =
                template?.formula?.length ||
                (processedData[0]?.results?.length ?? 0);
            const formulaAverages = Array.from(
                { length: numFormula },
                (_, i) => {
                    let total = 0,
                        count = 0,
                        dec = 4;
                    processedData.forEach((row) => {
                        const r = row.results[i];
                        if (r && r.value !== "—") {
                            const v = parseFloat(r.value);
                            if (!isNaN(v)) {
                                total += v;
                                count++;
                                if (r.pembulatan) dec = parseInt(r.pembulatan);
                            }
                        }
                    });
                    return count > 0 ? (total / count).toFixed(dec) : "—";
                }
            );
            return { data: processedData, formulaAverages };
        },

        async fetchBlobPhotos() {
            if (this.informasiData?.sesi_foto !== "Y") return;
            const allKeys = this.detailData.flatMap((item) =>
                (item.foto_analisa || []).map((f) => f.Berkas_Key)
            );
            if (!allKeys.length) return;
            const tokenRes = await axios.post(
                "/api/v1/formulator/hasil-uji/berkas/foto/token/bulk",
                { keys: allKeys }
            );
            const tokenMap = tokenRes.data;
            for (const key of allKeys) {
                const res = await axios.get(
                    `/api/v1/formulator/berkas/stream/foto-uji/${key}?token=${tokenMap[key]}`,
                    { responseType: "blob" }
                );
                this.fotoBlobUrls[key] = URL.createObjectURL(res.data);
            }
        },

        revokeBlobUrls() {
            Object.values(this.fotoBlobUrls).forEach((url) =>
                URL.revokeObjectURL(url)
            );
            this.fotoBlobUrls = {};
        },

        async selesaikanAnalisa() {
            const r = await Swal.fire({
                title: "Konfirmasi",
                text: "Data akan difinalisasi dan tidak bisa diubah kembali. Lanjutkan?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#198754",
                confirmButtonText: "Ya, Konfirmasi!",
                cancelButtonText: "Batal",
            });
            if (!r.isConfirmed) return;
            this.loading.saving = true;
            Swal.fire({
                title: "Menyimpan...",
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });
            try {
                const res = await axios.post(
                    "/api/v1/formulator/validasi-hasil/uji-trial/approve",
                    { analyses: this.detailData }
                );
                if (res.data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: "Data analisa berhasil dikonfirmasi.",
                        timer: 2000,
                    }).then(() => {
                        this.selectedItem = null;
                        this.clearDetail();
                        this.fetchList(this.pagination.page);
                    });
                } else throw new Error(res.data.message || "Gagal menyimpan.");
            } catch (e) {
                Swal.fire(
                    "Gagal!",
                    e.response?.data?.message || e.message,
                    "error"
                );
            } finally {
                this.loading.saving = false;
            }
        },

        openReanalisis() {
            if (!this.selectedItem) return;
            const isSingle = this.selectedItem.Flag_Multi_QrCode !== "Y";
            const noFak =
                this.selectedSubPo?.No_Fak_Sub_Po ||
                this.selectedItem.No_Po_Sampel;
            this.reanalisisForm = {
                isSingle,
                noUjiSebelumnya: noFak,
                selectedOption: null,
                noPo: this.selectedItem.No_Po_Sampel,
                idJenisAnalisa: this.selectedItem.Id_Jenis_Analisa,
            };
            if (!isSingle) this.fetchReanalisisOptions();
        },

        async fetchReanalisisOptions() {
            this.loading.reanalisisOptions = true;
            try {
                const res = await axios.get(
                    `/api/v1/formulator/validasi-hasil/uji-trial/sub/all/${this.reanalisisForm.noPo}/${this.reanalisisForm.idJenisAnalisa}`
                );
                this.reanalisisOptions = res.data?.result
                    ? res.data.result.map((i) => ({
                          value: i.No_Po_Multi,
                          name: i.No_Po_Multi,
                      }))
                    : [];
            } catch {
                this.reanalisisOptions = [];
            } finally {
                this.loading.reanalisisOptions = false;
            }
        },

        async submitReanalisis() {
            this.loading.reanalisis = true;
            try {
                const res = await axios.post(
                    "/api/v1/formulator/validasi-hasil/uji-trial/resampeling/reanalisis",
                    {
                        No_Po_Sampel: this.reanalisisForm.noPo,
                        No_Sampel_Resampling_Origin:
                            this.reanalisisForm.noUjiSebelumnya,
                        No_Sampel_Resampling:
                            this.reanalisisForm.selectedOption?.value,
                        Id_Jenis_Analisa: this.reanalisisForm.idJenisAnalisa,
                    }
                );
                if (res.data.success) {
                    this.closeReanalisisOffcanvas();
                    this.resetReanalisisForm();
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: res.data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => {
                        this.selectedItem = null;
                        this.selectedSubPo = null;
                        this.clearDetail();
                        this.fetchList(1);
                    });
                } else throw new Error(res.data.message);
            } catch (e) {
                Swal.fire("Error", e.message, "error");
            } finally {
                this.loading.reanalisis = false;
            }
        },

        async submitReanalisisSingle() {
            this.loading.reanalisis = true;
            try {
                const res = await axios.post(
                    "/api/v1/formulator/validasi-hasil/uji-trial/resampling-single/reanalisis",
                    {
                        No_Po_Sampel: this.reanalisisForm.noPo,
                        No_Sampel: this.reanalisisForm.noUjiSebelumnya,
                        Id_Jenis_Analisa: this.reanalisisForm.idJenisAnalisa,
                    }
                );
                if (res.data.success) {
                    this.closeReanalisisOffcanvas();
                    this.resetReanalisisForm();
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: res.data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => {
                        this.selectedItem = null;
                        this.selectedSubPo = null;
                        this.clearDetail();
                        this.fetchList(1);
                    });
                } else throw new Error(res.data.message || "Gagal menyimpan.");
            } catch (e) {
                Swal.fire(
                    "Error",
                    e.response?.data?.message || e.message,
                    "error"
                );
            } finally {
                this.loading.reanalisis = false;
            }
        },

        closeReanalisisOffcanvas() {
            const el = document.getElementById("offcanvasReanalisisFormulatorTrial");
            if (el) {
                const instance = bootstrap.Offcanvas.getInstance(el);
                if (instance) instance.hide();
            }
        },

        resetReanalisisForm() {
            this.reanalisisForm = {
                isSingle: false,
                noUjiSebelumnya: null,
                selectedOption: null,
                noPo: null,
                idJenisAnalisa: null,
            };
            this.reanalisisOptions = [];
        },

        formatTanggal(s) {
            if (!s) return "—";
            return new Date(s).toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
        },

        isSelectedBulk(item) {
            return this.selectedItems.some(
                (s) =>
                    s.No_Po_Sampel === item.No_Po_Sampel &&
                    s.Id_Jenis_Analisa === item.Id_Jenis_Analisa
            );
        },

        toggleBulkSelect(item) {
            const idx = this.selectedItems.findIndex(
                (s) =>
                    s.No_Po_Sampel === item.No_Po_Sampel &&
                    s.Id_Jenis_Analisa === item.Id_Jenis_Analisa
            );
            if (idx >= 0) this.selectedItems.splice(idx, 1);
            else this.selectedItems.push(item);
        },

        async openBulkModal() {
            this.bulkSubPoMap = {};
            this.bulkSubPoSelected = {};
            this.loading.bulkSubPo = true;
            this.bulkModalInstance?.show();

            const multiItems = this.selectedItems.filter(
                (i) => i.Flag_Multi_QrCode === "Y"
            );
            await Promise.all(
                multiItems.map(async (item) => {
                    const key = item.No_Po_Sampel + "|" + item.Id_Jenis_Analisa;
                    try {
                        const res = await axios.get(
                            `/api/v1/formulator/validasi-hasil/uji-trial/sub/menunggu/${item.No_Po_Sampel}/${item.Id_Jenis_Analisa}`
                        );
                        this.bulkSubPoMap[key] = res.data?.success
                            ? res.data.result || []
                            : [];
                    } catch {
                        this.bulkSubPoMap[key] = [];
                    }
                })
            );
            this.loading.bulkSubPo = false;
        },

        async submitBulk() {
            this.loading.bulkSubmit = true;
            try {
                const analyses = this.selectedItems.map((item) => ({
                    No_Po_Sampel: item.No_Po_Sampel,
                    Id_Jenis_Analisa: item.Id_Jenis_Analisa,
                    Flag_Multi_QrCode: item.Flag_Multi_QrCode,
                    No_Fak_Sub_Po:
                        item.Flag_Multi_QrCode === "Y"
                            ? this.bulkSubPoSelected[
                                  item.No_Po_Sampel + "|" + item.Id_Jenis_Analisa
                              ]
                            : null,
                }));

                const res = await axios.post(
                    "/api/v1/formulator/validasi-hasil/uji-trial/bulk/approve",
                    { analyses }
                );
                if (res.data.success) {
                    this.bulkModalInstance?.hide();
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: res.data.message,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(() => {
                        this.selectedItems = [];
                        this.fetchList(this.pagination.page);
                    });
                } else throw new Error(res.data.message || "Gagal");
            } catch (e) {
                Swal.fire(
                    "Gagal!",
                    e.response?.data?.message || e.message,
                    "error"
                );
            } finally {
                this.loading.bulkSubmit = false;
            }
        },

        openBulkSimpanModal() {
            this.bulkSimpanModalInstance?.show();
        },

        async submitBulkSimpan() {
            this.loading.bulkSubmit = true;
            try {
                const analyses = this.selectedItems.map((item) => ({
                    No_Po_Sampel: item.No_Po_Sampel,
                    Id_Jenis_Analisa: item.Id_Jenis_Analisa,
                    Flag_Multi_QrCode: item.Flag_Multi_QrCode,
                    No_Fak_Sub_Po: null,
                }));
                const res = await axios.post(
                    "/api/v1/formulator/validasi-hasil/uji-trial/bulk/approve",
                    { analyses }
                );
                if (res.data.success) {
                    this.bulkSimpanModalInstance?.hide();
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: res.data.message,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(() => {
                        this.selectedItems = [];
                        this.fetchList(this.pagination.page);
                    });
                } else throw new Error(res.data.message || "Gagal");
            } catch (e) {
                Swal.fire("Gagal!", e.response?.data?.message || e.message, "error");
            } finally {
                this.loading.bulkSubmit = false;
            }
        },

        handleResize() {
            this.isMobile = window.innerWidth < 992;
        },
    },

    watch: {
        searchQuery() {
            this.debouncedFetch();
        },
        filters: {
            handler() {
                this.debouncedFetch();
            },
            deep: true,
        },
    },

    mounted() {
        this.fetchList();
        window.addEventListener("resize", this.handleResize);
        const el = document.getElementById("bulkValidasiFormulatorModal");
        if (el) this.bulkModalInstance = new bootstrap.Modal(el);
        const el2 = document.getElementById("bulkSimpanValidasiFormulatorModal");
        if (el2) this.bulkSimpanModalInstance = new bootstrap.Modal(el2);
    },
    beforeUnmount() {
        this.revokeBlobUrls();
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
.vld-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.1;
}
.vld-stat-num {
    font-size: 18px;
    font-weight: 700;
    color: #1a1d23;
}
.vld-stat-lbl {
    font-size: 10px;
    color: #878a99;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

/* ── Main split layout ────────────────────────────────────────────────── */
.vld-body {
    display: flex;
    flex: 1;
    overflow: hidden;
    gap: 0;
}

/* ════════════════════════════════════════════════════════════════════════
   LEFT PANEL
   ════════════════════════════════════════════════════════════════════════ */
.vld-left {
    width: 340px;
    min-width: 280px;
    max-width: 380px;
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
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
}
.vld-search-wrap {
    position: relative;
}
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
.vld-search-input:focus {
    border-color: #405189;
    box-shadow: 0 0 0 2px rgba(64, 81, 137, 0.12);
}
.vld-filter-row {
    display: flex;
    gap: 5px;
    align-items: center;
}
.vld-date-input {
    flex: 1;
    min-width: 0;
    padding: 5px 6px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 11px;
    background: #fff;
    outline: none;
}
.vld-date-input:focus {
    border-color: #405189;
}
.vld-select {
    padding: 5px 6px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 11px;
    background: #fff;
    outline: none;
    cursor: pointer;
    flex-shrink: 0;
}
.vld-select:focus {
    border-color: #405189;
}

.vld-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}
.vld-list::-webkit-scrollbar {
    width: 4px;
}
.vld-list::-webkit-scrollbar-track {
    background: transparent;
}
.vld-list::-webkit-scrollbar-thumb {
    background: #dee2e6;
    border-radius: 4px;
}

.vld-skeleton {
    height: 72px;
    border-radius: 8px;
    background: linear-gradient(90deg, #f0f2f5 25%, #e4e7ec 50%, #f0f2f5 75%);
    background-size: 400% 100%;
    animation: vld-shimmer 1.4s infinite;
}
@keyframes vld-shimmer {
    0% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0 50%;
    }
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
.vld-empty-list i {
    font-size: 36px;
    color: #ced4da;
}
.vld-empty-list p {
    margin: 0;
}

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
.vld-item:hover {
    background: #f8f9fa;
}
.vld-item--active {
    background: #eef0f9 !important;
}

.vld-item-accent {
    width: 3px;
    flex-shrink: 0;
    background: transparent;
    transition: background 0.15s;
}
.vld-item--lolos .vld-item-accent {
    background: #0ab39c;
}
.vld-item--tidak .vld-item-accent {
    background: #f06548;
}
.vld-item--active .vld-item-accent {
    width: 4px;
}
.vld-item--active.vld-item--lolos .vld-item-accent {
    background: #0ab39c;
}
.vld-item--active.vld-item--tidak .vld-item-accent {
    background: #f06548;
}

.vld-item-body {
    flex: 1;
    min-width: 0;
    padding: 10px 8px 10px 10px;
}
.vld-item-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 6px;
    margin-bottom: 2px;
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
.vld-item-sub {
    font-size: 10px;
    color: #878a99;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vld-item-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
.vld-item-arrow {
    align-self: center;
    flex-shrink: 0;
    padding: 0 6px;
    color: #ced4da;
    font-size: 16px;
}
.vld-item--active .vld-item-arrow {
    color: #405189;
}

.vld-list-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-top: 1px solid #e9ebec;
    background: #f8f9fa;
    flex-shrink: 0;
}
.vld-page-info {
    font-size: 11px;
    color: #878a99;
}
.vld-page-btns {
    display: flex;
    align-items: center;
    gap: 4px;
}
.vld-page-btn {
    width: 26px;
    height: 26px;
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
.vld-page-btn:hover:not(:disabled) {
    background: #405189;
    color: #fff;
    border-color: #405189;
}
.vld-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.vld-page-current {
    font-size: 11px;
    color: #495057;
    min-width: 36px;
    text-align: center;
}

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
.vld-detail-empty-inner {
    text-align: center;
    max-width: 320px;
}
.vld-empty-icon-wrap {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #eef0f9;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}
.vld-empty-icon-wrap i {
    font-size: 32px;
    color: #405189;
}
.vld-detail-empty-inner h6 {
    font-weight: 600;
    color: #1a1d23;
    margin-bottom: 8px;
}
.vld-detail-empty-inner p {
    font-size: 12px;
    color: #878a99;
    line-height: 1.6;
    margin: 0;
}

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
.vld-dh-main {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex: 1;
    min-width: 0;
}
.vld-dh-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.vld-dh-icon--success {
    background: #d1f8ef;
    color: #0ab39c;
}
.vld-dh-icon--danger {
    background: #fde8e4;
    color: #f06548;
}
.vld-dh-title {
    font-size: 14px;
    font-weight: 700;
    color: #1a1d23;
    line-height: 1.2;
    margin-bottom: 2px;
}
.vld-dh-sub {
    font-size: 11px;
    color: #878a99;
    margin-bottom: 6px;
}
.vld-dh-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.vld-dh-meta {
    display: flex;
    flex-direction: column;
    gap: 2px;
    text-align: right;
    flex-shrink: 0;
}
.vld-dh-meta-row {
    font-size: 11px;
    color: #878a99;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 4px;
}
.vld-dh-meta-row i {
    font-size: 11px;
}

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
.vld-subpo-label {
    font-size: 11px;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
    flex-shrink: 0;
}
.vld-subpo-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    flex: 1;
}
.vld-subpo-loading {
    display: flex;
    align-items: center;
}
.vld-subpo-count {
    font-size: 10px;
    color: #878a99;
    white-space: nowrap;
    flex-shrink: 0;
}
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
.vld-subpo-tab:hover {
    border-color: #405189;
    color: #405189;
    background: #eef0f9;
}
.vld-subpo-tab--active {
    background: #405189;
    border-color: #405189;
    color: #fff;
    font-weight: 600;
}

.vld-detail-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
}
.vld-detail-body::-webkit-scrollbar {
    width: 5px;
}
.vld-detail-body::-webkit-scrollbar-track {
    background: transparent;
}
.vld-detail-body::-webkit-scrollbar-thumb {
    background: #dee2e6;
    border-radius: 4px;
}

.vld-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #878a99;
}

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
.vld-section-body {
    padding: 12px 14px;
}

.vld-mini-stats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.vld-ms-item {
    flex: 1;
    min-width: 80px;
    background: #fff;
    border: 1px solid #e9ebec;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.vld-ms-item--success {
    border-left: 3px solid #0ab39c;
}
.vld-ms-item--danger {
    border-left: 3px solid #f06548;
}
.vld-ms-item--info {
    border-left: 3px solid #405189;
}
.vld-ms-val {
    font-size: 20px;
    font-weight: 700;
    color: #1a1d23;
    line-height: 1.1;
}
.vld-ms-lbl {
    font-size: 10px;
    color: #878a99;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    margin-top: 2px;
}
.vld-ms-item--success .vld-ms-val {
    color: #0ab39c;
}
.vld-ms-item--danger .vld-ms-val {
    color: #f06548;
}
.vld-ms-item--info .vld-ms-val {
    color: #405189;
}

.vld-alert-warn {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    background: #fff8ec;
    border: 1px solid #f7b731;
    border-radius: 8px;
    padding: 10px 12px;
    font-size: 12px;
    color: #856404;
}

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
.vld-table tbody td {
    font-size: 12px;
    padding: 7px 10px;
}
.vld-row--avg {
    background: #fffbeb !important;
}

.vld-code {
    font-family: "Courier New", monospace;
    font-size: 11px;
    color: #405189;
    background: #eef0f9;
    padding: 1px 5px;
    border-radius: 4px;
}

.vld-badge {
    display: inline-flex;
    align-items: center;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 20px;
}
.vld-badge--success {
    background: #d1f8ef;
    color: #0ab39c;
}
.vld-badge--danger {
    background: #fde8e4;
    color: #f06548;
}
.vld-badge--blue {
    background: #eef0f9;
    color: #405189;
}
.vld-badge--gray {
    background: #f0f2f5;
    color: #6c757d;
}

.vld-chip {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 10px;
    font-weight: 500;
}
.vld-chip--blue {
    background: #eef0f9;
    color: #405189;
}
.vld-chip--gray {
    background: #f0f2f5;
    color: #6c757d;
}

.vld-action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    background: #fff;
    border-top: 1px solid #e9ebec;
    flex-shrink: 0;
    gap: 10px;
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
.vld-hidden-mobile {
    display: none !important;
}
@media (min-width: 992px) {
    .vld-hidden-mobile {
        display: flex !important;
    }
}
@media (max-width: 991px) {
    .vld-root {
        height: calc(100vh - 60px);
    }
    .vld-left {
        width: 100%;
        max-width: 100%;
        border-right: none;
    }
    .vld-right {
        width: 100%;
    }
    .vld-body {
        flex-direction: column;
    }
    .vld-dh-meta {
        display: none;
    }
}
@media (max-width: 480px) {
    .vld-topbar {
        flex-direction: column;
        align-items: flex-start;
    }
    .vld-mini-stats {
        flex-direction: row;
    }
    .vld-ms-item {
        min-width: calc(50% - 4px);
    }
}

.vld-reanalisis-info-box {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: #eef0f9;
    border: 1px solid #c5cae9;
    border-left: 4px solid #405189;
    border-radius: 10px;
    padding: 14px;
}
.vld-rib-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #405189;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

/* ── Bulk select ──────────────────────────────────────────────────────── */
.vld-item-wrap {
    position: relative;
    display: flex;
    align-items: stretch;
}
.vld-item-checkbox {
    position: absolute;
    left: 6px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    width: 15px;
    height: 15px;
    cursor: pointer;
    accent-color: #405189;
}
.vld-item-wrap .vld-item {
    padding-left: 28px;
    width: 100%;
}
.vld-item--checked {
    background: #eef0f9 !important;
    border-left: 3px solid #405189 !important;
}
.vld-bulk-bar {
    position: sticky;
    bottom: 0;
    background: #1a1d23;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    gap: 10px;
    z-index: 10;
    border-top: 2px solid #405189;
}
.vld-bulk-count {
    font-size: 13px;
    font-weight: 600;
}
</style>
