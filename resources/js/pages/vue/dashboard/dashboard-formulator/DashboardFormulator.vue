<template>
    <div class="container-fluid dflm-wrap">

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- HERO BANNER                                             -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="dflm-hero mb-4">
            <div class="dflm-hero-bg"></div>
            <div class="dflm-hero-mesh"></div>
            <div class="dflm-hero-inner">
                <div class="dflm-hero-left">
                    <div class="dflm-hero-avatar">
                        <i class="ri-flask-2-line"></i>
                    </div>
                    <div>
                        <div class="dflm-hero-tag">
                            Formulator · Activity Center
                        </div>
                        <h2 class="dflm-hero-name text-white mb-1">
                            Selamat datang, {{ namaPengguna }}
                        </h2>
                        <p class="dflm-hero-desc mb-0">
                            Panel Aktivitas Uji Formulator —
                            <strong>PT. Evo Manufacturing Indonesia</strong>
                        </p>
                    </div>
                </div>
                <div class="dflm-hero-right">
                    <div class="dflm-hero-clock-block">
                        <div class="dflm-clock">{{ liveClock }}</div>
                        <div class="dflm-date">{{ currentDate }}</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                        <span class="dflm-tat-pill" v-if="tat.hours !== null">
                            <i class="ri-timer-line"></i>
                            TAT avg <strong>{{ tat.hours }}j {{ tat.minutes }}m</strong>
                            <span class="opacity-75 ms-1 fs-11">{{ tat.period }}</span>
                        </span>
                        <button class="dflm-refresh-btn" @click="refreshAll" :disabled="anyLoading">
                            <i :class="anyLoading ? 'ri-loader-4-line dflm-spin' : 'ri-refresh-line'"></i>
                            Segarkan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- OVERVIEW STRIP — 6 KPI Columns                         -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body pb-0">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="dflm-hdr-dot" style="background:#405189"></div>
                        <h6 class="mb-0 fw-semibold fs-14">Ringkasan Aktivitas Hari Ini</h6>
                    </div>
                    <span class="badge bg-light text-muted fs-11">
                        <i class="ri-calendar-2-line me-1"></i>{{ todayLabel }}
                    </span>
                </div>

                <!-- Skeleton -->
                <div v-if="loading.kpi" class="dflm-strip-grid pb-3">
                    <div v-for="n in 6" :key="n" class="dflm-strip-col">
                        <div class="dflm-shimmer rounded" style="height:80px"></div>
                    </div>
                </div>

                <!-- Data -->
                <div v-else class="dflm-strip-grid">
                    <div
                        class="dflm-strip-col"
                        v-for="(w, i) in kpiToday"
                        :key="i"
                        :style="{ '--dflm-sc': w.color }"
                    >
                        <div class="dflm-strip-top"></div>
                        <div class="dflm-strip-body">
                            <div class="dflm-strip-icon-wrap" :style="{ background: w.bg }">
                                <i :class="w.icon" :style="{ color: w.color }"></i>
                            </div>
                            <div class="dflm-strip-val">{{ w.value.toLocaleString('id-ID') }}</div>
                            <div class="dflm-strip-lbl">{{ w.title }}</div>
                            <div class="dflm-strip-sub">{{ w.sub }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ROW 1: Tren Aktivitas (7) + Distribusi Status (5)       -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <!-- Area Trend -->
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h6 class="card-title fw-semibold mb-1">Tren Registrasi Sampel</h6>
                                <p class="text-muted fs-12 mb-0">Jumlah sampel masuk & selesai per hari</p>
                            </div>
                            <div class="dflm-day-tabs">
                                <button
                                    v-for="d in [7, 14, 30]"
                                    :key="d"
                                    :class="['dflm-day-tab', trenDays === d ? 'active' : '']"
                                    @click="setTrenDays(d)"
                                >{{ d }}H</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <div v-if="loading.tren" class="dflm-chart-sk dflm-shimmer" style="height:240px"></div>
                        <apexchart
                            v-else
                            type="area"
                            height="240"
                            :options="trenOptions"
                            :series="trenSeries"
                        />
                    </div>
                </div>
            </div>

            <!-- Donut distribusi aktivitas -->
            <div class="col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="card-title fw-semibold mb-1">Distribusi Jenis Analisa</h6>
                        <p class="text-muted fs-12 mb-0">Frekuensi per jenis uji (kumulatif)</p>
                    </div>
                    <div class="card-body pt-2">
                        <div v-if="loading.distribusi" class="dflm-chart-sk dflm-shimmer" style="height:240px"></div>
                        <apexchart
                            v-else
                            type="donut"
                            height="240"
                            :options="donutOptions"
                            :series="donutSeries"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- ROW 2: Horizontal Bar — Frekuensi per Jenis            -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                    <div>
                        <h6 class="card-title fw-semibold mb-1">
                            Frekuensi Uji per Jenis Analisa
                        </h6>
                        <p class="text-muted fs-12 mb-0">Distribusi lengkap seluruh aktivitas formulator</p>
                    </div>
                    <span class="badge bg-navy-subtle text-navy fs-11">
                        <i class="ri-bar-chart-horizontal-line me-1"></i>All-time
                    </span>
                </div>
            </div>
            <div class="card-body pt-2">
                <div v-if="loading.distribusi" class="dflm-chart-sk dflm-shimmer" :style="{ height: hbarHeight + 'px' }"></div>
                <apexchart
                    v-else
                    type="bar"
                    :height="hbarHeight"
                    :options="hbarOptions"
                    :series="hbarSeries"
                />
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════ -->
        <!-- AKTIVITAS TERBARU                                       -->
        <!-- ═══════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="card-title fw-semibold mb-1">Aktivitas Terbaru</h6>
                        <p class="text-muted fs-12 mb-0">10 aktivitas pengujian terkini oleh formulator</p>
                    </div>
                    <span class="badge bg-light text-muted fs-11">
                        <i class="ri-refresh-line me-1"></i>Live
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div v-if="loading.aktivitas" class="p-3">
                    <div v-for="n in 5" :key="n" class="dflm-shimmer rounded mb-2" style="height:44px"></div>
                </div>
                <div v-else-if="!aktivitas.length" class="text-center py-5 text-muted">
                    <i class="ri-inbox-line fs-2 d-block mb-2"></i>Belum ada aktivitas
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-hover dflm-table mb-0">
                        <thead>
                            <tr>
                                <th>No. Faktur</th>
                                <th>No. Sampel</th>
                                <th>Jenis Analisa</th>
                                <th>Aktivitas</th>
                                <th>Formulator</th>
                                <th>Tanggal & Jam</th>
                                <th>Status</th>
                                <th>Foto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(a, i) in aktivitas" :key="i">
                                <td class="fw-medium font-monospace fs-12">{{ a.No_Faktur }}</td>
                                <td class="font-monospace fs-12">{{ a.No_Po_Sampel }}</td>
                                <td>
                                    <span class="dflm-text-clamp" :title="a.Jenis_Analisa">
                                        {{ a.Jenis_Analisa }}
                                    </span>
                                </td>
                                <td>
                                    <span :class="['badge', aktivitasBadgeClass(a.Kode_Aktivitas_Lab)]">
                                        {{ a.Kode_Aktivitas_Lab || '-' }}
                                    </span>
                                </td>
                                <td class="fw-medium">{{ a.Id_User }}</td>
                                <td class="fs-12 text-muted">{{ a.Tanggal }}<br><small>{{ a.Jam }}</small></td>
                                <td>
                                    <span :class="['badge', a.Flag_Selesai === 'Y' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning']">
                                        {{ a.Flag_Selesai === 'Y' ? 'Selesai' : 'Proses' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span v-if="a.Flag_Foto === 'Y'" class="badge bg-navy-subtle text-navy">
                                        <i class="ri-image-line"></i>
                                    </span>
                                    <span v-else class="text-muted fs-12">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</template>

<script>
import axios from 'axios';

const PALETTE = [
    '#405189','#0ab39c','#f7b84b','#4b93f7','#dc2626',
    '#0891b2','#65a30d','#6f42c1','#0d9488','#ca8a04',
    '#405189','#16a34a','#ea580c','#2563eb','#db2777',
];

export default {
    name: 'DashboardFormulator',

    props: {
        namaPengguna: { type: String, default: 'Formulator' },
    },

    data() {
        return {
            liveClock:   '',
            currentDate: '',
            clockTimer:  null,
            trenDays:    7,

            tat: { hours: null, minutes: null, period: '' },

            kpiToday:  [],
            tren:      { categories: [], series: [] },
            distribusi:{ labels: [], data: [] },
            aktivitas: [],

            loading: {
                kpi:       true,
                tren:      true,
                distribusi:true,
                aktivitas: true,
            },
        };
    },

    computed: {
        anyLoading() {
            return Object.values(this.loading).some(Boolean);
        },

        todayLabel() {
            return new Date().toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
        },

        /* ── Tren Area ── */
        trenOptions() {
            return {
                chart: {
                    type: 'area',
                    toolbar: { show: false },
                    fontFamily: 'Inter, "Segoe UI", sans-serif',
                    animations: { enabled: true, easing: 'easeinout', speed: 500 },
                },
                colors: ['#405189', '#0ab39c'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 100] },
                },
                stroke: { curve: 'smooth', width: [2.5, 2] },
                xaxis: {
                    categories: this.tren.categories,
                    labels: { style: { fontSize: '11px', fontFamily: 'Inter, sans-serif' } },
                    axisBorder: { show: false },
                    axisTicks:  { show: false },
                },
                yaxis: { labels: { style: { fontSize: '11px' } } },
                tooltip: {
                    shared: true, intersect: false,
                    y: { formatter: v => v.toLocaleString('id-ID') },
                },
                legend: {
                    position: 'top', fontSize: '12px', fontFamily: 'Inter, sans-serif',
                    markers: { radius: 4 },
                },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                markers: { size: 0, hover: { size: 4 } },
            };
        },
        trenSeries() { return this.tren.series; },

        /* ── Donut ── */
        donutOptions() {
            return {
                chart: { type: 'donut', fontFamily: 'Inter, "Segoe UI", sans-serif' },
                labels: this.distribusi.labels,
                colors: PALETTE,
                legend: { position: 'bottom', fontSize: '11px', fontFamily: 'Inter, sans-serif' },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '68%',
                            labels: {
                                show: true,
                                total: {
                                    show: true, label: 'Total Uji', fontSize: '12px', fontWeight: 700,
                                    formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('id-ID'),
                                },
                            },
                        },
                    },
                },
                dataLabels: { enabled: false },
                stroke: { width: 2, colors: ['#fff'] },
                tooltip: { y: { formatter: v => v.toLocaleString('id-ID') + ' uji' } },
            };
        },
        donutSeries() { return this.distribusi.data; },

        /* ── Horizontal Bar ── */
        hbarHeight() {
            const count = this.distribusi.labels.length || 6;
            return Math.max(280, count * 40);
        },
        hbarOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'Inter, "Segoe UI", sans-serif' },
                colors: PALETTE,
                plotOptions: {
                    bar: {
                        horizontal: true,
                        distributed: true,
                        borderRadius: 5,
                        barHeight: '55%',
                        dataLabels: { position: 'right' },
                    },
                },
                dataLabels: {
                    enabled: true,
                    offsetX: 6,
                    style: { fontSize: '11px', fontWeight: '600', colors: ['#495057'] },
                    formatter: v => v.toLocaleString('id-ID'),
                },
                xaxis: {
                    categories: this.distribusi.labels,
                    labels: { style: { fontSize: '11px' } },
                },
                yaxis: { labels: { style: { fontSize: '12px', fontWeight: '500', colors: ['#374151'] }, maxWidth: 220 } },
                legend: { show: false },
                grid: { borderColor: '#f1f5f9', xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
                tooltip: { y: { formatter: v => v.toLocaleString('id-ID') + ' uji' } },
            };
        },
        hbarSeries() {
            return [{ name: 'Jumlah Uji', data: this.distribusi.data }];
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

        setTrenDays(d) {
            this.trenDays = d;
            this.fetchTren();
        },

        async fetchKpi() {
            this.loading.kpi = true;
            try {
                const { data } = await axios.get('/api/v1/flm/kpi-hari-ini');
                this.kpiToday = data.result || [];
            } finally {
                this.loading.kpi = false;
            }
        },

        async fetchTren() {
            this.loading.tren = true;
            try {
                const { data } = await axios.get('/api/v1/flm/tren-aktivitas', { params: { days: this.trenDays } });
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

        async fetchAktivitas() {
            this.loading.aktivitas = true;
            try {
                const { data } = await axios.get('/api/v1/flm/aktivitas-terbaru');
                this.aktivitas = data.result || [];
            } finally {
                this.loading.aktivitas = false;
            }
        },

        async fetchTat() {
            try {
                const { data } = await axios.get('/api/v1/flm/kpi-tat');
                this.tat = data.result || { hours: 0, minutes: 0, period: '' };
            } catch { /* silent */ }
        },

        refreshAll() {
            this.fetchKpi();
            this.fetchTren();
            this.fetchDistribusi();
            this.fetchAktivitas();
            this.fetchTat();
        },

        aktivitasBadgeClass(kode) {
            const map = {
                LCKV: 'bg-primary-subtle text-primary',
                ANL:  'bg-navy-subtle text-navy',
                PLT:  'bg-danger-subtle text-danger',
            };
            return map[kode] || 'bg-secondary-subtle text-secondary';
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
.dflm-wrap { padding: 1.25rem 1.5rem; background: #f8f9fc; min-height: 100vh; }

/* ══════════════════════════════════════════════════════════ */
/* HERO                                                        */
/* ══════════════════════════════════════════════════════════ */
.dflm-hero {
    position: relative;
    border-radius: 18px;
    overflow: hidden;
    padding: 0;
    min-height: 140px;
}
.dflm-hero-bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #405189 0%, #2c3b74 55%, #19254d 100%);
}
.dflm-hero-mesh {
    position: absolute; inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
    background-size: 24px 24px;
}
.dflm-hero-inner {
    position: relative; z-index: 2;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 1.25rem;
    padding: 1.75rem 2rem;
}
.dflm-hero-left  { display: flex; align-items: center; gap: 1.25rem; }
.dflm-hero-right { display: flex; flex-direction: column; align-items: flex-end; gap: .75rem; }

.dflm-hero-avatar {
    width: 60px; height: 60px; border-radius: 16px;
    background: rgba(255,255,255,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; color: #b8c8e8; flex-shrink: 0;
}
.dflm-hero-tag {
    font-size: 11px; font-weight: 600; letter-spacing: .08em;
    text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: .25rem;
}
.dflm-hero-name  { font-size: 1.35rem; font-weight: 700; }
.dflm-hero-desc  { font-size: 13px; color: rgba(255,255,255,0.7); }

.dflm-hero-clock-block { text-align: right; }
.dflm-clock {
    font-size: 1.6rem; font-weight: 700; font-variant-numeric: tabular-nums;
    color: #fff; letter-spacing: .04em;
}
.dflm-date { font-size: 12px; color: rgba(255,255,255,0.65); margin-top: .1rem; }

.dflm-tat-pill {
    display: inline-flex; align-items: center; gap: .4rem;
    background: rgba(255,255,255,0.15); backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 20px;
    padding: .35rem .85rem; font-size: 12px; color: #fff;
}
.dflm-refresh-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);
    color: #fff; border-radius: 10px; padding: .45rem 1rem;
    font-size: 13px; font-weight: 500; cursor: pointer; transition: background .2s;
}
.dflm-refresh-btn:hover:not(:disabled) { background: rgba(255,255,255,0.3); }
.dflm-refresh-btn:disabled { opacity: .6; cursor: not-allowed; }

/* ══════════════════════════════════════════════════════════ */
/* OVERVIEW STRIP                                              */
/* ══════════════════════════════════════════════════════════ */
.dflm-hdr-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.dflm-strip-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0;
    border-top: 1px solid #f0f0f0;
}
.dflm-strip-col {
    border-right: 1px solid #f0f0f0;
    padding-bottom: 1rem;
    position: relative;
}
.dflm-strip-col:last-child { border-right: none; }
.dflm-strip-top {
    height: 3px;
    background: var(--dflm-sc, #405189);
    border-radius: 0 0 3px 3px;
}
.dflm-strip-body { padding: 1rem .85rem .5rem; text-align: center; }
.dflm-strip-icon-wrap {
    width: 40px; height: 40px; border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: .6rem;
}
.dflm-strip-val {
    font-size: 1.55rem; font-weight: 700; color: #1e293b;
    line-height: 1; margin-bottom: .3rem;
}
.dflm-strip-lbl { font-size: 12px; font-weight: 600; color: #374151; }
.dflm-strip-sub { font-size: 11px; color: #9ca3af; margin-top: .15rem; }

/* ══════════════════════════════════════════════════════════ */
/* DAY TABS                                                    */
/* ══════════════════════════════════════════════════════════ */
.dflm-day-tabs { display: flex; gap: 4px; }
.dflm-day-tab {
    padding: .3rem .7rem; border-radius: 8px; font-size: 12px; font-weight: 500;
    border: 1px solid #e5e7eb; background: #fff; color: #6b7280; cursor: pointer; transition: all .15s;
}
.dflm-day-tab.active, .dflm-day-tab:hover {
    background: #405189; border-color: #405189; color: #fff;
}

/* ══════════════════════════════════════════════════════════ */
/* TABLE                                                       */
/* ══════════════════════════════════════════════════════════ */
.dflm-table thead th {
    background: #f8f9fc; font-size: 11px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .04em; color: #6b7280;
    border-bottom: 1px solid #f0f0f0; padding: .75rem 1rem; white-space: nowrap;
}
.dflm-table tbody td { padding: .65rem 1rem; font-size: 13px; vertical-align: middle; }
.dflm-table tbody tr:hover td { background: #f0f2f5; }
.dflm-text-clamp {
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; max-width: 200px; font-size: 12px;
}

/* ══════════════════════════════════════════════════════════ */
/* SHIMMER / SKELETON                                          */
/* ══════════════════════════════════════════════════════════ */
.dflm-shimmer {
    background: linear-gradient(90deg, #f0f2f5 25%, #e6e9ef 50%, #f0f2f5 75%);
    background-size: 200% 100%;
    animation: dflm-shimmer-anim 1.4s infinite;
}
@keyframes dflm-shimmer-anim {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.dflm-chart-sk { border-radius: 8px; }

/* ══════════════════════════════════════════════════════════ */
/* SPIN                                                        */
/* ══════════════════════════════════════════════════════════ */
.dflm-spin { animation: dflm-spin-anim .7s linear infinite; display: inline-block; }
@keyframes dflm-spin-anim { to { transform: rotate(360deg); } }

/* ══════════════════════════════════════════════════════════ */
/* BADGE HELPERS                                               */
/* ══════════════════════════════════════════════════════════ */
.bg-navy-subtle  { background: rgba(64,81,137,0.12) !important; }
.text-navy       { color: #405189 !important; }

/* ══════════════════════════════════════════════════════════ */
/* RESPONSIVE                                                  */
/* ══════════════════════════════════════════════════════════ */
@media (max-width: 1400px) {
    .dflm-strip-grid { grid-template-columns: repeat(3, 1fr); }
    .dflm-strip-col:nth-child(3) { border-right: none; }
    .dflm-strip-col:nth-child(4), .dflm-strip-col:nth-child(5), .dflm-strip-col:nth-child(6) {
        border-top: 1px solid #f0f0f0;
    }
}
@media (max-width: 991px) {
    .dflm-hero-inner  { padding: 1.25rem 1.25rem; }
    .dflm-clock       { font-size: 1.3rem; }
    .dflm-strip-val   { font-size: 1.3rem; }
    .dflm-strip-grid  { grid-template-columns: repeat(2, 1fr); }
    .dflm-strip-col:nth-child(even) { border-right: none; }
    .dflm-strip-col:nth-child(n+3)  { border-top: 1px solid #f0f0f0; }
}
@media (max-width: 768px) {
    .dflm-wrap { padding: .75rem; }
    .dflm-hero-inner { flex-direction: column; align-items: flex-start; }
    .dflm-hero-right { align-items: flex-start; }
    .dflm-hero-avatar { width: 46px; height: 46px; font-size: 22px; }
    .dflm-hero-name { font-size: 1.1rem; }
}
@media (max-width: 576px) {
    .dflm-strip-grid  { grid-template-columns: repeat(1, 1fr); }
    .dflm-strip-col   { border-right: none; border-top: 1px solid #f0f0f0; }
    .dflm-strip-col:first-child { border-top: none; }
}
</style>
