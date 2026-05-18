<template>
    <div class="container-fluid dflma-wrap">

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- HERO BANNER                                             -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="dflma-hero mb-4">
            <div class="dflma-hero-bg"></div>
            <div class="dflma-hero-mesh"></div>
            <div class="dflma-hero-inner">
                <div class="dflma-hero-left">
                    <div class="dflma-hero-avatar">
                        <i class="ri-command-line"></i>
                    </div>
                    <div>
                        <div class="dflma-hero-tag">Formulator · Ops Command</div>
                        <h2 class="dflma-hero-name text-white mb-1">Selamat, {{ namaPengguna }}</h2>
                        <p class="dflma-hero-desc mb-0">
                            Panel Eksekutif Kinerja Formulator —
                            <strong>PT. Evo Manufacturing Indonesia</strong>
                        </p>
                    </div>
                </div>
                <div class="dflma-hero-right">
                    <div class="dflma-hero-clock-block">
                        <div class="dflma-clock">{{ liveClock }}</div>
                        <div class="dflma-date">{{ currentDate }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                        <span class="dflma-periode-pill" v-if="kpi.periode">
                            <i class="ri-calendar-event-line"></i>
                            Periode: <strong>{{ kpi.periode }}</strong>
                        </span>
                        <button class="dflma-refresh-btn" @click="refreshAll" :disabled="anyLoading">
                            <i :class="anyLoading ? 'ri-loader-4-line dflma-spin' : 'ri-refresh-line'"></i>
                            Segarkan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ALERT CARDS (4 KPI Utama)                               -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6" v-for="(a, i) in alertCards" :key="i">
                <div class="card border-0 shadow-sm h-100 card-animate">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <span :class="['badge fs-11 mb-1', a.labelClass]">{{ a.tag }}</span>
                                <div :class="['dflma-alert-val', a.valClass]">{{ a.value }}</div>
                            </div>
                            <div class="dflma-icon-sm flex-shrink-0" :style="{ background: a.iconBgHex }">
                                <i :class="a.icon" :style="{ color: a.iconFgHex }"></i>
                            </div>
                        </div>
                        <p class="text-muted fs-12 mb-2">{{ a.desc }}</p>
                        <div v-if="a.pct !== undefined" class="dflma-progress-wrap">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fs-11 text-muted">{{ a.pctLabel }}</span>
                                <span class="fs-11 fw-semibold">{{ a.pct }}%</span>
                            </div>
                            <div class="progress dflma-prog" style="height:5px">
                                <div
                                    class="progress-bar"
                                    :class="a.barClass"
                                    :style="{ width: Math.min(a.pct, 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- OVERVIEW STRIP — Per Aktivitas                          -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body pb-0">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dflma-hdr-dot" style="background:#405189"></div>
                        <h6 class="mb-0 fw-semibold fs-14">Kinerja per Aktivitas — Bulan Ini</h6>
                    </div>
                    <span class="badge bg-light text-muted fs-11">
                        <i class="ri-bar-chart-grouped-line me-1"></i>LCKV · ANL · PLT
                    </span>
                </div>

                <!-- Skeleton -->
                <div v-if="loading.aktivitasData" class="dflma-aktivitas-grid pb-3">
                    <div v-for="n in 3" :key="n" class="dflma-shimmer rounded" style="height:110px"></div>
                </div>

                <!-- Data -->
                <div v-else-if="perAktivitas.length" class="dflma-aktivitas-grid pb-3">
                    <div
                        v-for="(ak, i) in perAktivitas"
                        :key="i"
                        class="dflma-ak-card"
                        :style="{ '--dflma-ak': aktivitasColor(ak.Kode_Aktivitas_Lab) }"
                    >
                        <div class="dflma-ak-top"></div>
                        <div class="dflma-ak-body">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="dflma-ak-badge" :style="{ background: aktivitasColor(ak.Kode_Aktivitas_Lab) + '22', color: aktivitasColor(ak.Kode_Aktivitas_Lab) }">
                                    {{ ak.Kode_Aktivitas_Lab }}
                                </span>
                                <span class="fs-12 fw-semibold text-dark">{{ ak.Nama_Aktivitas }}</span>
                            </div>
                            <div class="row g-2 text-center mt-1">
                                <div class="col-4">
                                    <div class="dflma-ak-num">{{ (ak.total || 0).toLocaleString('id-ID') }}</div>
                                    <div class="dflma-ak-lbl">Total</div>
                                </div>
                                <div class="col-4">
                                    <div class="dflma-ak-num text-success">{{ (ak.selesai || 0).toLocaleString('id-ID') }}</div>
                                    <div class="dflma-ak-lbl">Selesai</div>
                                </div>
                                <div class="col-4">
                                    <div class="dflma-ak-num text-info">{{ (ak.dengan_foto || 0).toLocaleString('id-ID') }}</div>
                                    <div class="dflma-ak-lbl">Dgn Foto</div>
                                </div>
                            </div>
                            <div class="progress mt-2" style="height:4px">
                                <div
                                    class="progress-bar"
                                    :style="{ width: ak.total > 0 ? (ak.selesai / ak.total * 100) + '%' : '0%', background: aktivitasColor(ak.Kode_Aktivitas_Lab) }"
                                ></div>
                            </div>
                            <div class="fs-11 text-muted mt-1">
                                {{ ak.total > 0 ? (ak.selesai / ak.total * 100).toFixed(0) : 0 }}% completion rate
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-4 text-muted pb-3">Belum ada data aktivitas bulan ini</div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ROW 1: Tren Bulanan (7) + Status Validasi Panel (5)     -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <!-- Tren Bulanan Area Chart -->
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="card-title fw-semibold mb-1">Tren Bulanan Tahun Berjalan</h6>
                        <p class="text-muted fs-12 mb-0">Registrasi sampel, penyelesaian, dan validasi final per bulan</p>
                    </div>
                    <div class="card-body pt-2">
                        <div v-if="loading.tren" class="dflma-chart-sk dflma-shimmer" style="height:280px"></div>
                        <apexchart v-else type="area" height="280" :options="trenOptions" :series="trenSeries" />
                    </div>
                </div>
            </div>

            <!-- Validasi Summary -->
            <div class="col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="card-title fw-semibold mb-1">Ringkasan Validasi</h6>
                        <p class="text-muted fs-12 mb-0">Status pra-final dan validasi final keseluruhan</p>
                    </div>
                    <div class="card-body">
                        <div v-if="loading.validasi">
                            <div v-for="n in 4" :key="n" class="dflma-shimmer rounded mb-2" style="height:48px"></div>
                        </div>
                        <div v-else class="dflma-validasi-list">
                            <div class="dflma-vitem" v-for="(v, i) in validasiItems" :key="i">
                                <div class="dflma-vicon" :style="{ background: v.bg, color: v.color }">
                                    <i :class="v.icon"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <span class="dflma-vlbl">{{ v.label }}</span>
                                        <span class="dflma-vval" :style="{ color: v.color }">{{ (v.value || 0).toLocaleString('id-ID') }}</span>
                                    </div>
                                    <div class="dflma-vsub">{{ v.sub }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ROW 2: Distribusi Jenis (6) + Top Formulator (6)        -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="card-title fw-semibold mb-1">Frekuensi per Jenis Analisa</h6>
                        <p class="text-muted fs-12 mb-0">Distribusi seluruh jenis uji formulator (kumulatif)</p>
                    </div>
                    <div class="card-body pt-2">
                        <div v-if="loading.distribusi" class="dflma-chart-sk dflma-shimmer" :style="{ height: hbarHeight + 'px' }"></div>
                        <apexchart v-else type="bar" :height="hbarHeight" :options="hbarOptions" :series="hbarSeries" />
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <h6 class="card-title fw-semibold mb-1">Produktivitas Formulator</h6>
                                <p class="text-muted fs-12 mb-0">Peringkat berdasarkan jumlah uji selesai bulan ini</p>
                            </div>
                            <span class="badge bg-warning-subtle text-warning fs-11">
                                <i class="ri-trophy-line me-1"></i>Top 8
                            </span>
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <div v-if="loading.topFormulator" class="dflma-chart-sk dflma-shimmer" style="height:280px"></div>
                        <apexchart v-else type="bar" height="280" :options="topFormulatorOptions" :series="topFormulatorSeries" />
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- SAMPEL TERBARU TABLE                                    -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div>
                        <h6 class="card-title fw-semibold mb-1">Sampel Trial Terbaru</h6>
                        <p class="text-muted fs-12 mb-0">15 sampel terakhir beserta status proses dan foto</p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary fs-11">
                        <i class="ri-list-check-3 me-1"></i>Live
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div v-if="loading.sampel" class="p-3">
                    <div v-for="n in 5" :key="n" class="dflma-shimmer rounded mb-2" style="height:46px"></div>
                </div>
                <div v-else-if="!sampelTerbaru.length" class="text-center py-5 text-muted">
                    <i class="ri-inbox-line fs-2 d-block mb-2"></i>Belum ada data sampel
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-hover dflma-table mb-0">
                        <thead>
                            <tr>
                                <th>No. Sampel</th>
                                <th>No. PO</th>
                                <th>Split / Batch</th>
                                <th>Kode Barang</th>
                                <th>Formulator</th>
                                <th>Tanggal</th>
                                <th>Uji</th>
                                <th>Foto</th>
                                <th>Status</th>
                                <th>Validasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(s, i) in sampelTerbaru" :key="i">
                                <td class="fw-semibold font-monospace fs-12">{{ s.No_Sampel }}</td>
                                <td class="font-monospace fs-12">{{ s.No_Po }}</td>
                                <td class="fs-12 text-muted">{{ s.No_Split_Po }}<br><small>Batch {{ s.No_Batch }}</small></td>
                                <td class="font-monospace fs-12">{{ s.Kode_Barang }}</td>
                                <td class="fw-medium">{{ s.Id_User }}</td>
                                <td class="fs-12 text-muted">{{ s.Tanggal }}<br><small>{{ s.Jam }}</small></td>
                                <td class="text-center">
                                    <div class="dflma-uji-pill">
                                        <span class="text-success fw-semibold">{{ s.Selesai_Uji }}</span>
                                        <span class="text-muted">/</span>
                                        <span>{{ s.Total_Uji }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span
                                        v-if="s.Jumlah_Foto > 0"
                                        class="badge bg-navy-subtle text-navy"
                                        :title="`${s.Jumlah_Foto} foto tersedia`"
                                    >
                                        <i class="ri-image-line me-1"></i>{{ s.Jumlah_Foto }}
                                    </span>
                                    <span v-else class="text-muted fs-12">—</span>
                                </td>
                                <td>
                                    <span :class="['badge fs-11', statusBadge(s.Status).cls]">
                                        <i :class="statusBadge(s.Status).icon + ' me-1'"></i>
                                        {{ s.Status }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span v-if="s.Flag_Validasi === 'Y'" class="badge bg-success-subtle text-success">
                                        <i class="ri-shield-check-line me-1"></i>Divalidasi
                                    </span>
                                    <span v-else class="badge bg-secondary-subtle text-secondary">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- FOTO TERBARU GALLERY                                    -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div>
                        <h6 class="card-title fw-semibold mb-1">
                            <i class="ri-image-2-line me-2 text-navy"></i>Dokumentasi Foto Uji
                        </h6>
                        <p class="text-muted fs-12 mb-0">12 entri foto terbaru dari hasil pengujian formulator</p>
                    </div>
                    <span class="badge bg-navy-subtle text-navy fs-11">
                        Total: {{ kpi.foto_all ? kpi.foto_all.toLocaleString('id-ID') : 0 }} foto
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div v-if="loading.foto">
                    <div class="dflma-foto-grid">
                        <div v-for="n in 8" :key="n" class="dflma-shimmer rounded" style="height:140px"></div>
                    </div>
                </div>
                <div v-else-if="!fotoTerbaru.length" class="text-center py-5 text-muted">
                    <i class="ri-image-line fs-2 d-block mb-2"></i>Belum ada dokumentasi foto
                </div>
                <div v-else class="dflma-foto-grid">
                    <div class="dflma-foto-card" v-for="(f, i) in fotoTerbaru" :key="i">
                        <div class="dflma-foto-preview">
                            <i class="ri-file-image-line"></i>
                            <span class="dflma-foto-ext">{{ getFileExt(f.File_Path) }}</span>
                        </div>
                        <div class="dflma-foto-info">
                            <div class="dflma-foto-faktur font-monospace">{{ f.No_Faktur }}</div>
                            <div class="dflma-foto-sampel text-muted">{{ f.No_Sampel }}</div>
                            <div class="dflma-foto-jenis" :title="f.Jenis_Analisa">
                                {{ f.Jenis_Analisa || 'N/A' }}
                            </div>
                            <div class="dflma-foto-meta">
                                <i class="ri-user-line me-1"></i>{{ f.Id_User || '-' }}
                                <span class="ms-2">
                                    <i class="ri-calendar-line me-1"></i>{{ f.Tanggal }}
                                </span>
                            </div>
                            <div v-if="f.Keterangan" class="dflma-foto-ket">
                                {{ f.Keterangan }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ALL-TIME REKAP (4 cards bottom)                         -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="dflma-section-label mb-3">
            <i class="ri-database-2-line"></i>
            Rekap Kumulatif Keseluruhan
        </div>
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6" v-for="(s, i) in allTimeCards" :key="i">
                <div class="card card-animate border-0 shadow-sm h-100">
                    <div class="card-body text-center py-4">
                        <div class="dflma-icon-lg mx-auto mb-3" :style="{ background: s.iconBgHex }">
                            <i :class="s.icon" :style="{ color: s.iconFgHex, fontSize: '2rem' }"></i>
                        </div>
                        <div :class="['dflma-alltime-val', s.valClass]">
                            {{ (s.value || 0).toLocaleString('id-ID') }}
                        </div>
                        <div class="fs-13 fw-semibold text-dark mb-2">{{ s.label }}</div>
                        <span :class="['badge fs-11', s.badgeClass]">{{ s.badge }}</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import axios from 'axios';

const PALETTE = [
    '#405189','#0ab39c','#f7b84b','#4b93f7','#dc2626',
    '#0891b2','#6f42c1','#0d9488','#ca8a04','#db2777',
    '#16a34a','#ea580c','#2563eb','#65a30d','#0284c7',
];

export default {
    name: 'DashboardFormulatorAtasan',

    props: {
        namaPengguna: { type: String, default: 'Atasan' },
    },

    data() {
        return {
            liveClock:   '',
            currentDate: '',
            clockTimer:  null,

            kpi:           {},
            perAktivitas:  [],
            tren:          { categories: [], series: [] },
            distribusi:    { labels: [], data: [] },
            topFormulator: { categories: [], data: [] },
            sampelTerbaru: [],
            fotoTerbaru:   [],
            validasi:      { pra_final: {}, validasi_final: {} },

            loading: {
                kpi:           true,
                aktivitasData: true,
                tren:          true,
                distribusi:    true,
                topFormulator: true,
                sampel:        true,
                foto:          true,
                validasi:      true,
            },
        };
    },

    computed: {
        anyLoading() {
            return Object.values(this.loading).some(Boolean);
        },

        /* ── Alert Cards ── */
        alertCards() {
            const k = this.kpi;
            const selesaiPct = (k.sampel_bulan_ini ?? 0) > 0
                ? Math.round(((k.sampel_selesai ?? 0) / k.sampel_bulan_ini) * 100) : 0;
            const pr    = k.pass_rate_pct ?? 0;
            const prOk  = pr >= 90;
            const valPct = (k.sampel_all ?? 0) > 0
                ? Math.round(((k.validasi_all ?? 0) / k.sampel_all) * 100) : 0;
            const fotoBulan = k.foto_bulan ?? 0;

            return [
                {
                    tag: 'Sampel Bulan Ini', value: (k.sampel_bulan_ini ?? 0).toLocaleString('id-ID'),
                    desc: 'Total registrasi trial bulan berjalan',
                    icon: 'ri-flask-2-line', iconBgHex: 'rgba(64,81,137,.14)', iconFgHex: '#405189',
                    labelClass: 'bg-primary-subtle text-primary', valClass: 'dflma-alert-val-primary',
                    pct: selesaiPct, pctLabel: 'Telah selesai', barClass: 'bg-primary',
                },
                {
                    tag: 'Pass Rate Uji', value: pr + '%',
                    desc: prOk ? 'Kualitas pengujian sangat baik' : 'Perlu peningkatan kualitas',
                    icon: prOk ? 'ri-shield-check-line' : 'ri-alert-line',
                    iconBgHex: prOk ? 'rgba(5,150,105,.14)' : 'rgba(220,38,38,.14)',
                    iconFgHex: prOk ? '#059669' : '#dc2626',
                    labelClass: prOk ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger',
                    valClass: prOk ? 'dflma-alert-val-success' : 'dflma-alert-val-danger',
                    pct: pr, pctLabel: 'Actual', barClass: prOk ? 'bg-success' : 'bg-danger',
                },
                {
                    tag: 'Validasi Formulator', value: (k.validasi_bulan ?? 0).toLocaleString('id-ID'),
                    desc: `${valPct}% sampel telah divalidasi dari total keseluruhan`,
                    icon: 'ri-check-double-line', iconBgHex: 'rgba(5,150,105,.14)', iconFgHex: '#059669',
                    labelClass: 'bg-success-subtle text-success', valClass: 'dflma-alert-val-success',
                    pct: valPct, pctLabel: 'Approval rate', barClass: 'bg-success',
                },
                {
                    tag: 'Dokumentasi Foto', value: fotoBulan.toLocaleString('id-ID'),
                    desc: `Total ${(k.foto_all ?? 0).toLocaleString('id-ID')} foto tersimpan di sistem`,
                    icon: 'ri-image-2-line', iconBgHex: 'rgba(64,81,137,.14)', iconFgHex: '#405189',
                    labelClass: 'bg-primary-subtle text-primary', valClass: 'dflma-alert-val-primary',
                },
            ];
        },

        /* ── Validasi Items ── */
        validasiItems() {
            const pf = this.validasi.pra_final    || {};
            const vf = this.validasi.validasi_final || {};
            return [
                { icon: 'ri-test-tube-line',       bg: 'rgba(64,81,137,.12)', color: '#405189',  label: 'Pra-Final Selesai',      value: pf.total    ?? 0, sub: `${pf.bulan_ini ?? 0} bulan ini` },
                { icon: 'ri-checkbox-circle-line',  bg: 'rgba(5,150,105,.12)',  color: '#059669', label: 'Pra-Final OK',           value: pf.ok       ?? 0, sub: 'Flag_Ok = Y' },
                { icon: 'ri-flag-2-line',           bg: 'rgba(217,119,6,.12)',  color: '#d97706', label: 'Pra-Final FG',           value: pf.fg       ?? 0, sub: 'Siap Finalisasi' },
                { icon: 'ri-award-line',            bg: 'rgba(5,150,105,.12)',  color: '#059669', label: 'Validasi Final Selesai',  value: vf.total    ?? 0, sub: `${vf.bulan_ini ?? 0} bulan ini` },
                { icon: 'ri-verified-badge-line',   bg: 'rgba(64,81,137,.12)', color: '#405189',  label: 'Validasi Final OK',      value: vf.ok       ?? 0, sub: 'Disetujui QC' },
                { icon: 'ri-star-smile-line',       bg: 'rgba(217,119,6,.12)',  color: '#d97706', label: 'Validasi Final FG',      value: vf.fg       ?? 0, sub: 'Finished Goods' },
            ];
        },

        /* ── All-time cards ── */
        allTimeCards() {
            const k = this.kpi;
            return [
                { icon: 'ri-check-double-line', iconBgHex: 'rgba(5,150,105,.14)',   iconFgHex: '#059669', label: 'Total Sampel Selesai',    value: k.selesai_all   ?? 0, valClass: 'text-success', badge: 'Selesai',    badgeClass: 'bg-success-subtle text-success' },
                { icon: 'ri-image-2-line',       iconBgHex: 'rgba(64,81,137,.14)',   iconFgHex: '#405189', label: 'Total Foto Dokumentasi',  value: k.foto_all      ?? 0, valClass: 'text-primary', badge: 'Berkas Foto', badgeClass: 'bg-primary-subtle text-primary' },
                { icon: 'ri-shield-check-line',  iconBgHex: 'rgba(64,81,137,.14)',   iconFgHex: '#405189', label: 'Divalidasi Formulator',   value: k.validasi_all  ?? 0, valClass: 'text-primary', badge: 'Validasi',   badgeClass: 'bg-primary-subtle text-primary' },
                { icon: 'ri-lock-2-line',        iconBgHex: 'rgba(220,38,38,.14)',   iconFgHex: '#dc2626', label: 'PO Ditutup',              value: k.close_po_all  ?? 0, valClass: 'text-danger',  badge: 'Closed PO',  badgeClass: 'bg-danger-subtle text-danger' },
            ];
        },

        /* ── Tren Options ── */
        trenOptions() {
            return {
                chart: {
                    type: 'area', toolbar: { show: false },
                    fontFamily: 'Inter, "Segoe UI", sans-serif',
                    animations: { enabled: true, easing: 'easeinout', speed: 600 },
                },
                colors: ['#405189','#0ab39c','#4b93f7','#f7b84b'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02, stops: [0, 100] },
                },
                stroke: { curve: 'smooth', width: [2.5, 2, 2, 2] },
                xaxis: {
                    categories: this.tren.categories,
                    labels: { style: { fontSize: '11px', fontFamily: 'Inter, sans-serif' } },
                    axisBorder: { show: false }, axisTicks: { show: false },
                },
                yaxis: { labels: { style: { fontSize: '11px' } } },
                tooltip: { shared: true, intersect: false, y: { formatter: v => v.toLocaleString('id-ID') } },
                legend: { position: 'top', fontSize: '12px', fontFamily: 'Inter, sans-serif', markers: { radius: 4 } },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                markers: { size: 0, hover: { size: 4 } },
            };
        },
        trenSeries() { return this.tren.series; },

        /* ── Horizontal Bar ── */
        hbarHeight() {
            const count = this.distribusi.labels.length || 6;
            return Math.max(280, count * 40);
        },
        hbarOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, "Segoe UI", sans-serif' },
                colors: PALETTE,
                plotOptions: {
                    bar: { horizontal: true, distributed: true, borderRadius: 5, barHeight: '55%', dataLabels: { position: 'right' } },
                },
                dataLabels: {
                    enabled: true, offsetX: 6,
                    style: { fontSize: '11px', fontWeight: '600', colors: ['#374151'] },
                    formatter: v => v.toLocaleString('id-ID'),
                },
                xaxis: {
                    categories: this.distribusi.labels,
                    labels: { style: { fontSize: '11px' } },
                },
                yaxis: { labels: { style: { fontSize: '12px', fontWeight: '500', colors: ['#374151'] }, maxWidth: 200 } },
                legend: { show: false },
                grid: { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                tooltip: { y: { formatter: v => v.toLocaleString('id-ID') + ' uji' } },
            };
        },
        hbarSeries() {
            return [{ name: 'Jumlah Uji', data: this.distribusi.data }];
        },

        /* ── Top Formulator ── */
        topFormulatorOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, "Segoe UI", sans-serif' },
                colors: PALETTE,
                plotOptions: {
                    bar: { horizontal: true, distributed: true, borderRadius: 5, barHeight: '60%', dataLabels: { position: 'right' } },
                },
                dataLabels: {
                    enabled: true, offsetX: 6,
                    style: { fontSize: '11px', fontWeight: '600', colors: ['#374151'] },
                    formatter: v => v.toLocaleString('id-ID'),
                },
                xaxis: {
                    categories: this.topFormulator.categories,
                    labels: { style: { fontSize: '11px' } },
                },
                yaxis: { labels: { style: { fontSize: '13px', fontWeight: '600', colors: ['#374151'] }, maxWidth: 120 } },
                legend: { show: false },
                grid: { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                tooltip: { y: { formatter: v => v.toLocaleString('id-ID') + ' uji selesai' } },
            };
        },
        topFormulatorSeries() {
            return [{ name: 'Uji Selesai', data: this.topFormulator.data }];
        },
    },

    methods: {
        startClock() {
            const tick = () => {
                const now = new Date();
                this.liveClock   = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                this.currentDate = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            };
            tick();
            this.clockTimer = setInterval(tick, 1000);
        },

        aktivitasColor(kode) {
            const map = { LCKV: '#405189', ANL: '#0ab39c', PLT: '#dc2626' };
            return map[kode] || '#6b7280';
        },

        statusBadge(status) {
            const map = {
                'Selesai':             { cls: 'bg-success-subtle text-success', icon: 'ri-check-double-line' },
                'Sedang Analisa':      { cls: 'bg-primary-subtle text-primary', icon: 'ri-microscope-line' },
                'Menunggu Analisa':    { cls: 'bg-warning-subtle text-warning', icon: 'ri-time-line' },
                'Menunggu Finalisasi': { cls: 'bg-info-subtle text-info',       icon: 'ri-hourglass-line' },
            };
            return map[status] || { cls: 'bg-secondary-subtle text-secondary', icon: 'ri-question-line' };
        },

        getFileExt(path) {
            if (!path) return 'FILE';
            const parts = path.split('.');
            return (parts[parts.length - 1] || 'FILE').toUpperCase();
        },

        async fetchKpi() {
            this.loading.kpi = true;
            try {
                const { data } = await axios.get('/api/v1/flm/atasan/kpi-rekap');
                this.kpi = data.result || {};
            } finally {
                this.loading.kpi = false;
            }
        },

        async fetchPerAktivitas() {
            this.loading.aktivitasData = true;
            try {
                const { data } = await axios.get('/api/v1/flm/atasan/per-aktivitas');
                this.perAktivitas = data.result || [];
            } finally {
                this.loading.aktivitasData = false;
            }
        },

        async fetchTren() {
            this.loading.tren = true;
            try {
                const { data } = await axios.get('/api/v1/flm/atasan/tren-bulanan');
                this.tren = data.result || { categories: [], series: [] };
            } finally {
                this.loading.tren = false;
            }
        },

        async fetchDistribusi() {
            this.loading.distribusi = true;
            try {
                const { data } = await axios.get('/api/v1/flm/distribusi-jenis');
                this.distribusi = data.result || { labels: [], data: [] };
            } finally {
                this.loading.distribusi = false;
            }
        },

        async fetchTopFormulator() {
            this.loading.topFormulator = true;
            try {
                const { data } = await axios.get('/api/v1/flm/atasan/top-formulator');
                this.topFormulator = data.result || { categories: [], data: [] };
            } finally {
                this.loading.topFormulator = false;
            }
        },

        async fetchSampel() {
            this.loading.sampel = true;
            try {
                const { data } = await axios.get('/api/v1/flm/atasan/sampel-terbaru');
                this.sampelTerbaru = data.result || [];
            } finally {
                this.loading.sampel = false;
            }
        },

        async fetchFoto() {
            this.loading.foto = true;
            try {
                const { data } = await axios.get('/api/v1/flm/atasan/foto-terbaru');
                this.fotoTerbaru = data.result || [];
            } finally {
                this.loading.foto = false;
            }
        },

        async fetchValidasi() {
            this.loading.validasi = true;
            try {
                const { data } = await axios.get('/api/v1/flm/atasan/status-validasi');
                this.validasi = data.result || { pra_final: {}, validasi_final: {} };
            } finally {
                this.loading.validasi = false;
            }
        },

        refreshAll() {
            this.fetchKpi();
            this.fetchPerAktivitas();
            this.fetchTren();
            this.fetchDistribusi();
            this.fetchTopFormulator();
            this.fetchSampel();
            this.fetchFoto();
            this.fetchValidasi();
        },
    },

    mounted() {
        this.startClock();
        this.refreshAll();
    },

    beforeUnmount() {
        if (this.clockTimer) clearInterval(this.clockTimer);
    },
};
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════ */
/* WRAP                                                        */
/* ══════════════════════════════════════════════════════════ */
.dflma-wrap { padding: 1.25rem 1.5rem; background: #f8f9fc; min-height: 100vh; }

/* ══════════════════════════════════════════════════════════ */
/* HERO                                                        */
/* ══════════════════════════════════════════════════════════ */
.dflma-hero {
    position: relative; border-radius: 18px; overflow: hidden; min-height: 145px;
}
.dflma-hero-bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #405189 0%, #2c3b74 55%, #19254d 100%);
}
.dflma-hero-mesh {
    position: absolute; inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
    background-size: 22px 22px;
}
.dflma-hero-inner {
    position: relative; z-index: 2;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
    gap: 1.25rem; padding: 1.75rem 2rem;
}
.dflma-hero-left  { display: flex; align-items: center; gap: 1.25rem; }
.dflma-hero-right { display: flex; flex-direction: column; align-items: flex-end; gap: .75rem; }

.dflma-hero-avatar {
    width: 62px; height: 62px; border-radius: 16px;
    background: rgba(255,255,255,0.12); backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; color: #b8c8e8; flex-shrink: 0;
}
.dflma-hero-tag {
    font-size: 11px; font-weight: 600; letter-spacing: .08em;
    text-transform: uppercase; color: rgba(255,255,255,0.55); margin-bottom: .25rem;
}
.dflma-hero-name  { font-size: 1.35rem; font-weight: 700; }
.dflma-hero-desc  { font-size: 13px; color: rgba(255,255,255,0.65); }

.dflma-hero-clock-block { text-align: right; }
.dflma-clock {
    font-size: 1.65rem; font-weight: 700; font-variant-numeric: tabular-nums;
    color: #fff; letter-spacing: .04em;
}
.dflma-date { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: .1rem; }

.dflma-periode-pill {
    display: inline-flex; align-items: center; gap: .4rem;
    background: rgba(255,255,255,0.13); backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 20px;
    padding: .35rem .85rem; font-size: 12px; color: #fff;
}
.dflma-refresh-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3);
    color: #fff; border-radius: 10px; padding: .45rem 1rem;
    font-size: 13px; font-weight: 500; cursor: pointer; transition: background .2s;
}
.dflma-refresh-btn:hover:not(:disabled) { background: rgba(255,255,255,0.28); }
.dflma-refresh-btn:disabled { opacity: .6; cursor: not-allowed; }

/* ══════════════════════════════════════════════════════════ */
/* ALERT CARDS                                                 */
/* ══════════════════════════════════════════════════════════ */
.dflma-icon-sm {
    width: 52px; height: 52px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.dflma-alert-val         { font-size: 1.7rem; font-weight: 700; line-height: 1; }
.dflma-alert-val-primary { color: #405189; }
.dflma-alert-val-success { color: #059669; }
.dflma-alert-val-danger  { color: #dc2626; }
.dflma-alert-val-violet  { color: #405189; }
.dflma-prog              { border-radius: 10px; overflow: hidden; }

/* ══════════════════════════════════════════════════════════ */
/* HEADER DOT                                                  */
/* ══════════════════════════════════════════════════════════ */
.dflma-hdr-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

/* ══════════════════════════════════════════════════════════ */
/* PER AKTIVITAS GRID                                          */
/* ══════════════════════════════════════════════════════════ */
.dflma-aktivitas-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;
}
.dflma-ak-card {
    border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff;
}
.dflma-ak-top { height: 4px; background: var(--dflma-ak, #405189); }
.dflma-ak-body { padding: 1rem; }
.dflma-ak-badge {
    font-size: 11px; font-weight: 700; padding: .2rem .55rem; border-radius: 6px;
    letter-spacing: .05em; flex-shrink: 0;
}
.dflma-ak-num { font-size: 1.3rem; font-weight: 700; line-height: 1; }
.dflma-ak-lbl { font-size: 11px; color: #9ca3af; margin-top: .15rem; }

/* ══════════════════════════════════════════════════════════ */
/* VALIDASI LIST                                               */
/* ══════════════════════════════════════════════════════════ */
.dflma-validasi-list { display: flex; flex-direction: column; gap: .75rem; }
.dflma-vitem {
    display: flex; align-items: center; gap: .85rem;
    padding: .65rem .85rem; border-radius: 10px; background: #f9fafb;
    border: 1px solid #f0f0f0;
}
.dflma-vicon {
    width: 38px; height: 38px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.dflma-vlbl { font-size: 13px; font-weight: 500; color: #374151; }
.dflma-vval { font-size: 1rem; font-weight: 700; }
.dflma-vsub { font-size: 11px; color: #9ca3af; }

/* ══════════════════════════════════════════════════════════ */
/* TABLE                                                       */
/* ══════════════════════════════════════════════════════════ */
.dflma-table thead th {
    background: #f8f9fc; font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .04em; color: #6b7280;
    border-bottom: 1px solid #e5e7eb; padding: .75rem 1rem; white-space: nowrap;
}
.dflma-table tbody td { padding: .65rem 1rem; font-size: 13px; vertical-align: middle; }
.dflma-table tbody tr:hover td { background: #f0f2f5; }
.dflma-uji-pill { display: inline-flex; align-items: center; gap: .2rem; font-size: 13px; font-weight: 600; }

/* ══════════════════════════════════════════════════════════ */
/* FOTO GALLERY                                                */
/* ══════════════════════════════════════════════════════════ */
.dflma-foto-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}
.dflma-foto-card {
    border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;
    background: #fff; transition: box-shadow .2s, transform .15s;
}
.dflma-foto-card:hover { box-shadow: 0 4px 16px rgba(64,81,137,0.12); transform: translateY(-2px); }
.dflma-foto-preview {
    height: 100px; background: linear-gradient(135deg, #f0f2f5 0%, #e6e9ef 100%);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    font-size: 2.5rem; color: #405189;
}
.dflma-foto-ext {
    font-size: 11px; font-weight: 700; color: #405189;
    background: rgba(64,81,137,0.1); border-radius: 4px; padding: .1rem .4rem;
    margin-top: .3rem;
}
.dflma-foto-info    { padding: .75rem; }
.dflma-foto-faktur  { font-size: 12px; font-weight: 700; font-family: monospace; color: #1e293b; }
.dflma-foto-sampel  { font-size: 11px; color: #9ca3af; font-family: monospace; }
.dflma-foto-jenis   { font-size: 12px; font-weight: 500; color: #374151; margin-top: .25rem; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.dflma-foto-meta    { font-size: 11px; color: #6b7280; margin-top: .35rem; }
.dflma-foto-ket     { font-size: 11px; color: #405189; margin-top: .25rem; font-style: italic; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

/* ══════════════════════════════════════════════════════════ */
/* ALL-TIME CARDS                                              */
/* ══════════════════════════════════════════════════════════ */
.dflma-icon-lg {
    width: 64px; height: 64px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
}
.dflma-alltime-val { font-size: 1.8rem; font-weight: 700; line-height: 1; margin-bottom: .3rem; }

/* ══════════════════════════════════════════════════════════ */
/* SECTION LABEL                                               */
/* ══════════════════════════════════════════════════════════ */
.dflma-section-label {
    display: inline-flex; align-items: center; gap: .5rem;
    font-size: 12px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
    color: #6b7280; padding: .3rem .75rem;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 20px;
}

/* ══════════════════════════════════════════════════════════ */
/* SHIMMER / SKELETON                                          */
/* ══════════════════════════════════════════════════════════ */
.dflma-shimmer {
    background: linear-gradient(90deg, #f0f2f5 25%, #e6e9ef 50%, #f0f2f5 75%);
    background-size: 200% 100%;
    animation: dflma-shimmer-anim 1.4s infinite;
}
@keyframes dflma-shimmer-anim {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.dflma-chart-sk { border-radius: 8px; }

/* ══════════════════════════════════════════════════════════ */
/* SPIN                                                        */
/* ══════════════════════════════════════════════════════════ */
.dflma-spin { animation: dflma-spin-anim .7s linear infinite; display: inline-block; }
@keyframes dflma-spin-anim { to { transform: rotate(360deg); } }

/* ══════════════════════════════════════════════════════════ */
/* BADGE HELPERS                                               */
/* ══════════════════════════════════════════════════════════ */
.bg-navy-subtle   { background: rgba(64,81,137,0.12) !important; }
.text-navy        { color: #405189 !important; }

/* ══════════════════════════════════════════════════════════ */
/* RESPONSIVE                                                  */
/* ══════════════════════════════════════════════════════════ */
@media (max-width: 1200px) {
    .dflma-aktivitas-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 991px) {
    .dflma-hero-inner { padding: 1.25rem 1.25rem; }
    .dflma-clock { font-size: 1.3rem; }
    .dflma-aktivitas-grid { grid-template-columns: repeat(1, 1fr); }
}
@media (max-width: 768px) {
    .dflma-wrap { padding: .75rem; }
    .dflma-hero-inner { flex-direction: column; align-items: flex-start; }
    .dflma-hero-right { align-items: flex-start; }
    .dflma-hero-avatar { width: 48px; height: 48px; font-size: 22px; }
    .dflma-hero-name { font-size: 1.1rem; }
    .dflma-foto-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
}
@media (max-width: 576px) {
    .dflma-foto-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 420px) {
    .dflma-foto-grid { grid-template-columns: repeat(1, 1fr); }
}
</style>
