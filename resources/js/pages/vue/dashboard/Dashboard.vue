<template>
    <div class="az-page">

        <!-- ── Header ─────────────────────────────────────── -->
        <div class="az-header">
            <div class="az-header-left">
                <div class="az-header-icon"><i class="ri-microscope-line"></i></div>
                <div>
                    <h4 class="az-header-title">Dashboard Laboratorium</h4>
                    <p class="az-header-sub">
                        Selamat datang, <strong>{{ namaPengguna }}</strong>
                        <span class="az-header-date">— {{ currentDate }}</span>
                    </p>
                </div>
            </div>
            <div class="az-header-right">
                <!-- TAT Badge -->
                <div class="az-tat-badge" v-if="tat.hours !== null">
                    <i class="ri-timer-line"></i>
                    <span>TAT {{ tat.hours }}j {{ tat.minutes }}m</span>
                    <small>{{ tat.period }}</small>
                </div>
                <button class="az-refresh-btn" @click="refreshAll" :disabled="anyLoading">
                    <i :class="anyLoading ? 'ri-loader-4-line az-spin' : 'ri-refresh-line'"></i>
                    Segarkan
                </button>
            </div>
        </div>

        <!-- ── KPI Hari Ini ──────────────────────────────── -->
        <div class="az-section-label">
            <i class="ri-sun-line"></i> Data Hari Ini
        </div>
        <div class="az-kpi-grid">
            <template v-if="loading.kpiToday">
                <div v-for="n in 6" :key="n" class="az-kpi-card az-sk">
                    <div class="az-sk-icon az-shimmer"></div>
                    <div style="flex:1">
                        <div class="az-sk-line w60 az-shimmer mb2"></div>
                        <div class="az-sk-line w40 az-shimmer mb2"></div>
                        <div class="az-sk-line w50 az-shimmer"></div>
                    </div>
                </div>
            </template>
            <template v-else>
                <div v-for="(w,i) in kpiToday" :key="i" class="az-kpi-card">
                    <div class="az-kpi-icon" :style="{ background: w.color+'18', color: w.color }">
                        <i :class="w.icon || 'ri-bar-chart-line'"></i>
                    </div>
                    <div class="az-kpi-body">
                        <div class="az-kpi-label">{{ w.title }}</div>
                        <div class="az-kpi-value" :style="{ color: w.color }">{{ (w.value||0).toLocaleString('id-ID') }}</div>
                        <div class="az-kpi-sub">{{ w.subtitle }}</div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ── Charts Row 1: Tren + Donut ───────────────── -->
        <div class="az-row-7-3">
            <!-- Area Trend -->
            <div class="az-card">
                <div class="az-card-head">
                    <div>
                        <div class="az-card-title">Tren Uji Sampel</div>
                        <div class="az-card-sub">Jumlah pengujian per hari</div>
                    </div>
                    <div class="az-day-btns">
                        <button
                            v-for="d in [7,14,30]"
                            :key="d"
                            :class="['az-day-btn', trendDays===d && 'active']"
                            @click="changeTrendDays(d)"
                        >{{ d }}H</button>
                    </div>
                </div>
                <div class="az-card-body">
                    <div v-if="loading.trend" class="az-chart-sk az-shimmer"></div>
                    <apexchart v-else type="area" height="260" :options="trendOptions" :series="trendSeries" />
                </div>
            </div>
            <!-- Donut Status -->
            <div class="az-card">
                <div class="az-card-head">
                    <div class="az-card-title">Status Penyelesaian</div>
                </div>
                <div class="az-card-body az-center">
                    <div v-if="loading.pie" class="az-donut-sk az-shimmer"></div>
                    <apexchart v-else type="donut" height="260" :options="pieOptions" :series="pieSeries" />
                </div>
            </div>
        </div>

        <!-- ── Charts Row 2: Frekuensi + Scatter ────────── -->
        <div class="az-row-half">
            <!-- Radar frekuensi jenis analisa -->
            <div class="az-card">
                <div class="az-card-head">
                    <div class="az-card-title">Frekuensi Jenis Analisa</div>
                    <div class="az-card-sub">Distribusi pengujian berdasarkan jenis</div>
                </div>
                <div class="az-card-body">
                    <div v-if="loading.radar" class="az-chart-sk az-shimmer"></div>
                    <apexchart v-else type="radar" height="280" :options="radarOptions" :series="radarSeries" />
                </div>
            </div>
            <!-- Bar: Jumlah uji per jenis (vertical) -->
            <div class="az-card">
                <div class="az-card-head">
                    <div class="az-card-title">Volume Per Jenis Analisa</div>
                    <div class="az-card-sub">Total sampel per kategori pengujian</div>
                </div>
                <div class="az-card-body">
                    <div v-if="loading.barJenis" class="az-chart-sk az-shimmer"></div>
                    <apexchart v-else type="bar" height="280" :options="barJenisOptions" :series="barJenisSeries" />
                </div>
            </div>
        </div>

        <!-- ── KPI Total Keseluruhan ─────────────────────── -->
        <div class="az-section-label">
            <i class="ri-database-2-line"></i> Total Keseluruhan
        </div>
        <div class="az-kpi-grid-4">
            <template v-if="loading.kpiAll">
                <div v-for="n in 4" :key="n" class="az-kpi-card az-sk">
                    <div class="az-sk-icon az-shimmer"></div>
                    <div style="flex:1">
                        <div class="az-sk-line w60 az-shimmer mb2"></div>
                        <div class="az-sk-line w40 az-shimmer"></div>
                    </div>
                </div>
            </template>
            <template v-else>
                <div v-for="(w,i) in kpiAll" :key="i" class="az-kpi-card">
                    <div class="az-kpi-icon" :style="{ background: w.color+'18', color: w.color }">
                        <i :class="w.icon || 'ri-bar-chart-line'"></i>
                    </div>
                    <div class="az-kpi-body">
                        <div class="az-kpi-label">{{ w.title }}</div>
                        <div class="az-kpi-value" :style="{ color: w.color }">{{ (w.value||0).toLocaleString('id-ID') }}</div>
                        <div class="az-kpi-sub">{{ w.subtitle }}</div>
                    </div>
                </div>
            </template>
        </div>

        <!-- ── Aktivitas Terbaru ─────────────────────────── -->
        <div class="az-card">
            <div class="az-card-head">
                <div>
                    <div class="az-card-title">Aktivitas Pengujian Terbaru</div>
                    <div class="az-card-sub">5 pengujian terakhir yang selesai</div>
                </div>
            </div>
            <div class="az-card-body az-no-pad">
                <div v-if="loading.aktivitas" class="az-table-sk">
                    <div v-for="n in 5" :key="n" class="az-table-sk-row az-shimmer"></div>
                </div>
                <template v-else>
                    <div v-if="aktivitasList.length === 0" class="az-empty-row">Belum ada data aktivitas</div>
                    <div v-else class="az-table-wrap">
                        <table class="az-table">
                            <thead>
                                <tr>
                                    <th>No Faktur</th>
                                    <th>No Sampel</th>
                                    <th>Jenis Analisa</th>
                                    <th>Analis</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, i) in aktivitasList" :key="i">
                                    <td class="az-mono">{{ item.id || '-' }}</td>
                                    <td class="az-mono">{{ item.no_sampel || '-' }}</td>
                                    <td>
                                        <span class="az-badge-jenis">{{ item.jenis_analisa || '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="az-badge-user">{{ item.user || '-' }}</span>
                                    </td>
                                    <td class="az-text-muted">{{ item.tanggal || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>

    </div>
</template>

<script>
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';

const PALETTE = ['#405189','#0ab39c','#f7b84b','#f06548','#4b93f7','#6f42c1'];

export default {
    components: { apexchart: VueApexCharts },

    props: {
        namaPengguna: { type: String, default: 'Analis' },
        aksesSpesial: { type: Boolean, default: false },
    },

    data() {
        const now = new Date();
        return {
            currentDate: now.toLocaleDateString('id-ID', { weekday:'long', year:'numeric', month:'long', day:'numeric' }),

            kpiToday: [],
            kpiAll: [],
            tat: { hours: null, minutes: null, period: '' },
            aktivitasList: [],

            // Chart raw data
            trend: { categories: [], series: [] },
            trendDays: 14,
            pie:  { series: [], labels: [] },
            radar: { series: [], labels: [] },
            barJenis: { categories: [], data: [] },

            loading: {
                kpiToday: false,
                kpiAll: false,
                trend: false,
                pie: false,
                radar: false,
                barJenis: false,
                aktivitas: false,
                tat: false,
            },
        };
    },

    computed: {
        anyLoading() {
            return Object.values(this.loading).some(Boolean);
        },

        trendOptions() {
            return {
                chart: { type: 'area', toolbar: { show: false }, sparkline: { enabled: false } },
                colors: ['#405189', '#0ab39c'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 } },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: { categories: this.trend.categories, labels: { style: { fontSize: '11px' } } },
                yaxis: { labels: { style: { fontSize: '11px' } } },
                tooltip: { shared: true, intersect: false },
                legend: { position: 'top' },
                grid: { borderColor: '#f1f1f1' },
                dataLabels: { enabled: false },
            };
        },

        trendSeries() { return this.trend.series; },

        pieOptions() {
            return {
                chart: { type: 'donut' },
                labels: this.pie.labels,
                colors: ['#0ab39c', '#f7b84b'],
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: { pie: { donut: { size: '70%', labels: { show: true, total: { show: true, label: 'Total' } } } } },
                dataLabels: { enabled: true, formatter: (v) => v.toFixed(1) + '%' },
            };
        },

        pieSeries() { return this.pie.series; },

        radarOptions() {
            return {
                chart: { type: 'radar', toolbar: { show: false } },
                colors: ['#405189'],
                labels: this.radar.labels,
                fill: { opacity: 0.2 },
                stroke: { width: 2 },
                markers: { size: 4 },
                yaxis: { show: false },
                xaxis: { labels: { style: { fontSize: '11px' } } },
            };
        },

        radarSeries() {
            return [{ name: 'Frekuensi', data: this.radar.series }];
        },

        barJenisOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false } },
                colors: PALETTE,
                plotOptions: { bar: { distributed: true, borderRadius: 4, columnWidth: '55%' } },
                xaxis: { categories: this.barJenis.categories, labels: { style: { fontSize: '10px' }, rotate: -30 } },
                yaxis: { labels: { style: { fontSize: '11px' } } },
                legend: { show: false },
                dataLabels: { enabled: false },
                grid: { borderColor: '#f1f1f1' },
            };
        },

        barJenisSeries() {
            return [{ name: 'Jumlah Uji', data: this.barJenis.data }];
        },
    },

    methods: {
        async fetchKpiToday() {
            this.loading.kpiToday = true;
            try {
                const res = await axios.get('/api/v1/dashboard/current-hari-ini');
                this.kpiToday = res.data?.result ?? [];
            } catch { this.kpiToday = []; }
            finally { this.loading.kpiToday = false; }
        },

        async fetchKpiAll() {
            this.loading.kpiAll = true;
            try {
                const res = await axios.get('/api/v1/dashboard/current-all-time');
                this.kpiAll = res.data?.result ?? [];
            } catch { this.kpiAll = []; }
            finally { this.loading.kpiAll = false; }
        },

        async fetchTat() {
            this.loading.tat = true;
            try {
                const res = await axios.get('/api/v1/dashboard/analyzer/kpi-tat');
                const r = res.data?.result ?? {};
                this.tat = { hours: r.hours ?? 0, minutes: r.minutes ?? 0, period: r.period ?? '' };
            } catch { this.tat = { hours: 0, minutes: 0, period: '' }; }
            finally { this.loading.tat = false; }
        },

        async fetchTrend() {
            this.loading.trend = true;
            try {
                const res = await axios.get('/api/v1/dashboard/grafik/jumlah-uji-perhari', { params: { days: this.trendDays } });
                const r = res.data?.result ?? {};
                // existing endpoint returns chartLineSeries / chartLineOptions
                const cats = r.chartLineOptions?.xaxis?.categories ?? [];
                const series = r.chartLineSeries ?? [];
                this.trend = { categories: cats, series };
            } catch { this.trend = { categories: [], series: [] }; }
            finally { this.loading.trend = false; }
        },

        async fetchPie() {
            this.loading.pie = true;
            try {
                const res = await axios.get('/api/v1/dashboard/grafik/pie-status-uji-sampel');
                const r = res.data?.result ?? {};
                this.pie = {
                    series: r.chartPieSeries ?? [],
                    labels: r.chartPieOptions?.labels ?? [],
                };
            } catch { this.pie = { series: [], labels: [] }; }
            finally { this.loading.pie = false; }
        },

        async fetchRadar() {
            this.loading.radar = true;
            try {
                const res = await axios.get('/api/v1/dashboard/grafik/frekuensi-uji-sampel-berdasarkan-jenis-analisa');
                const r = res.data?.result ?? {};
                const raw = r.chartBarSeries?.[0]?.data ?? [];
                const labels = r.chartBarOptions?.labels ?? [];
                this.radar = { series: raw, labels };
                // reuse same data for bar
                this.barJenis = { categories: labels, data: raw };
            } catch {
                this.radar = { series: [], labels: [] };
                this.barJenis = { categories: [], data: [] };
            }
            finally { this.loading.radar = false; this.loading.barJenis = false; }
        },

        async fetchAktivitas() {
            this.loading.aktivitas = true;
            try {
                const res = await axios.get('/api/v1/dashboard/analyzer/aktivitas-terbaru');
                this.aktivitasList = res.data?.result ?? [];
            } catch { this.aktivitasList = []; }
            finally { this.loading.aktivitas = false; }
        },

        changeTrendDays(d) {
            this.trendDays = d;
            this.fetchTrend();
        },

        refreshAll() {
            this.fetchKpiToday();
            this.fetchKpiAll();
            this.fetchTat();
            this.fetchTrend();
            this.fetchPie();
            this.fetchRadar();
            this.fetchAktivitas();
        },
    },

    mounted() {
        this.refreshAll();
    },
};
</script>

<style scoped>
/* ── Base ─────────────────────────────────────────────── */
.az-page {
    font-family: 'Inter', 'Segoe UI', sans-serif;
    color: #343a40;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ── Header ───────────────────────────────────────────── */
.az-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 20px 24px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 6px rgba(64,81,137,.08);
    border: 1px solid #e9ecef;
}

.az-header-left { display: flex; align-items: center; gap: 14px; }
.az-header-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.az-header-icon {
    width: 48px; height: 48px; border-radius: 13px;
    background: rgba(64,81,137,.12); color: #405189;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; flex-shrink: 0;
}

.az-header-title { font-size: 17px; font-weight: 700; color: #212529; margin: 0 0 3px; }
.az-header-sub   { font-size: 12px; color: #878a99; margin: 0; }
.az-header-date  { opacity: .75; }

.az-tat-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; background: rgba(64,81,137,.08);
    border: 1px solid rgba(64,81,137,.2); border-radius: 20px;
    font-size: 12px; font-weight: 600; color: #405189;
}
.az-tat-badge small { font-weight: 400; color: #878a99; font-size: 11px; }

.az-refresh-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: #405189; color: #fff;
    border: none; border-radius: 9px; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: background .2s;
}
.az-refresh-btn:hover:not(:disabled) { background: #35457b; }
.az-refresh-btn:disabled { opacity: .6; cursor: not-allowed; }

/* ── Section Label ────────────────────────────────────── */
.az-section-label {
    font-size: 12px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .8px; color: #878a99;
    display: flex; align-items: center; gap: 6px;
    margin-bottom: -8px;
}

/* ── KPI Grid ─────────────────────────────────────────── */
.az-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
.az-kpi-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.az-kpi-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: box-shadow .2s, transform .2s;
}
.az-kpi-card:hover {
    box-shadow: 0 4px 14px rgba(64,81,137,.1);
    transform: translateY(-1px);
}
.az-kpi-icon {
    width: 46px; height: 46px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.az-kpi-body { flex: 1; min-width: 0; }
.az-kpi-label { font-size: 12px; color: #878a99; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.az-kpi-value { font-size: 22px; font-weight: 700; line-height: 1.2; margin-bottom: 2px; }
.az-kpi-sub   { font-size: 11px; color: #adb5bd; }

/* ── Charts Layout ────────────────────────────────────── */
.az-row-7-3 {
    display: grid;
    grid-template-columns: 7fr 3fr;
    gap: 16px;
}
.az-row-half {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* ── Card ─────────────────────────────────────────────── */
.az-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
}
.az-card-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 16px 20px 0; gap: 8px;
}
.az-card-title { font-size: 14px; font-weight: 700; color: #212529; }
.az-card-sub   { font-size: 11px; color: #878a99; margin-top: 2px; }
.az-card-body  { padding: 14px 20px 16px; }
.az-card-body.az-no-pad { padding: 0; }
.az-card-body.az-center { display: flex; justify-content: center; }

/* ── Day Buttons ──────────────────────────────────────── */
.az-day-btns { display: flex; gap: 4px; }
.az-day-btn {
    padding: 4px 10px; border: 1px solid #dee2e6; border-radius: 6px;
    font-size: 11px; font-weight: 600; background: #fff; color: #878a99;
    cursor: pointer; transition: .15s;
}
.az-day-btn.active { background: #405189; color: #fff; border-color: #405189; }

/* ── Table ────────────────────────────────────────────── */
.az-table-wrap { overflow-x: auto; }
.az-table {
    width: 100%; border-collapse: collapse;
    font-size: 13px;
}
.az-table thead tr { background: #f8f9fc; border-bottom: 1px solid #e9ecef; }
.az-table th {
    padding: 10px 16px; text-align: left;
    font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .6px; color: #878a99; white-space: nowrap;
}
.az-table tbody tr { border-bottom: 1px solid #f0f2f5; transition: background .15s; }
.az-table tbody tr:last-child { border-bottom: none; }
.az-table tbody tr:hover { background: #f8f9fc; }
.az-table td { padding: 11px 16px; color: #495057; vertical-align: middle; }

.az-mono { font-family: 'Courier New', monospace; font-size: 12px; }
.az-text-muted { color: #878a99; font-size: 12px; }

.az-badge-jenis {
    display: inline-block; padding: 3px 8px;
    background: rgba(64,81,137,.1); color: #405189;
    border-radius: 5px; font-size: 11px; font-weight: 600;
}
.az-badge-user {
    display: inline-block; padding: 3px 8px;
    background: rgba(10,179,156,.1); color: #0ab39c;
    border-radius: 5px; font-size: 11px; font-weight: 600;
}

.az-empty-row {
    text-align: center; padding: 32px; color: #adb5bd; font-size: 13px;
}

/* ── Skeleton ─────────────────────────────────────────── */
@keyframes az-shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position:  400px 0; }
}
.az-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 800px 100%;
    animation: az-shimmer 1.5s infinite linear;
    border-radius: 4px;
}
.az-sk.az-kpi-card { pointer-events: none; }
.az-sk-icon { width: 46px; height: 46px; border-radius: 11px; flex-shrink: 0; }
.az-sk-line { height: 13px; }
.az-sk-line.w60 { width: 60%; }
.az-sk-line.w40 { width: 40%; }
.az-sk-line.w50 { width: 50%; }
.mb2 { margin-bottom: 8px; }
.az-chart-sk { height: 260px; border-radius: 8px; }
.az-donut-sk  { width: 200px; height: 200px; border-radius: 50%; align-self: center; }
.az-table-sk { padding: 8px 0; }
.az-table-sk-row { height: 46px; margin: 2px 0; border-radius: 0; }

/* ── Spinner ──────────────────────────────────────────── */
@keyframes az-spin { to { transform: rotate(360deg); } }
.az-spin { display: inline-block; animation: az-spin .7s linear infinite; }

/* ── Responsive ───────────────────────────────────────── */
@media (max-width: 1100px) {
    .az-kpi-grid-4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 900px) {
    .az-row-7-3  { grid-template-columns: 1fr; }
    .az-row-half { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
    .az-kpi-grid  { grid-template-columns: repeat(2, 1fr); }
    .az-kpi-grid-4 { grid-template-columns: repeat(2, 1fr); }
    .az-header { padding: 14px 16px; }
    .az-header-title { font-size: 15px; }
    .az-tat-badge small { display: none; }
}
@media (max-width: 420px) {
    .az-kpi-grid  { grid-template-columns: 1fr; }
    .az-kpi-grid-4 { grid-template-columns: 1fr; }
}
</style>
