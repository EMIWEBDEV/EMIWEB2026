<template>
    <div class="dlb-page">

        <!-- ── Hero Banner ─────────────────────────────────── -->
        <div class="dlb-hero">
            <div class="dlb-hero-dots"></div>
            <div class="dlb-hero-inner">
                <div class="dlb-hero-left">
                    <div class="dlb-hero-icon-box">
                        <i class="ri-microscope-line"></i>
                    </div>
                    <div>
                        <div class="dlb-hero-tag">
                            <i class="ri-flask-line"></i>
                            LIMS &middot; Lab Analyzer
                        </div>
                        <h2 class="dlb-hero-title">Dashboard Laboratorium</h2>
                        <p class="dlb-hero-sub">
                            Selamat datang, <strong>{{ namaPengguna }}</strong>
                            <span class="dlb-hero-date">— {{ currentDate }}</span>
                        </p>
                    </div>
                </div>
                <div class="dlb-hero-right">
                    <div class="dlb-tat-pill" v-if="tat.hours !== null">
                        <i class="ri-timer-2-line dlb-pill-icon"></i>
                        <div>
                            <div class="dlb-pill-value">{{ tat.hours }}j {{ tat.minutes }}m</div>
                            <div class="dlb-pill-label">Rata-rata TAT</div>
                        </div>
                    </div>
                    <div class="dlb-clock-pill">
                        <i class="ri-time-line dlb-pill-icon"></i>
                        <div>
                            <div class="dlb-pill-value">{{ currentTime }}</div>
                            <div class="dlb-pill-label">Waktu Sekarang</div>
                        </div>
                    </div>
                    <button class="dlb-refresh-btn" @click="refreshAll" :disabled="anyLoading">
                        <i :class="anyLoading ? 'ri-loader-4-line dlb-spin' : 'ri-refresh-line'"></i>
                        Segarkan
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Overview Strip (KPI Hari Ini) ──────────────── -->
        <div class="dlb-strip-card">
            <div class="dlb-strip-header">
                <i class="ri-sun-line"></i>
                <span>Data Aktivitas Hari Ini</span>
                <span class="dlb-strip-period">{{ currentDate }}</span>
            </div>
            <template v-if="loading.kpiToday">
                <div class="dlb-strip-grid">
                    <div v-for="n in 6" :key="n" class="dlb-strip-col dlb-strip-sk">
                        <div class="dlb-sk-line w50 dlb-shimmer" style="height:28px;margin-bottom:8px;"></div>
                        <div class="dlb-sk-line w70 dlb-shimmer" style="height:12px;margin-bottom:4px;"></div>
                        <div class="dlb-sk-line w40 dlb-shimmer" style="height:10px;"></div>
                    </div>
                </div>
            </template>
            <div v-else class="dlb-strip-grid">
                <div
                    v-for="(item, i) in kpiToday"
                    :key="i"
                    class="dlb-strip-col"
                    :style="{ '--dlb-sc': item.color || PALETTE[i % PALETTE.length] }"
                >
                    <div class="dlb-strip-icon-row">
                        <i :class="item.icon || 'ri-bar-chart-line'" class="dlb-strip-icon"></i>
                    </div>
                    <div class="dlb-strip-value">{{ (item.value || 0).toLocaleString('id-ID') }}</div>
                    <div class="dlb-strip-label">{{ item.title }}</div>
                    <div class="dlb-strip-sub">{{ item.subtitle }}</div>
                </div>
            </div>
        </div>

        <!-- ── Charts Row 1: Tren + Donut ─────────────────── -->
        <div class="dlb-row-7-3">
            <div class="dlb-card">
                <div class="dlb-card-head">
                    <div>
                        <div class="dlb-card-title">Tren Uji Sampel</div>
                        <div class="dlb-card-sub">Jumlah pengujian per hari dalam periode terpilih</div>
                    </div>
                    <div class="dlb-day-btns">
                        <button
                            v-for="d in [7, 14, 30]"
                            :key="d"
                            :class="['dlb-day-btn', trendDays === d && 'active']"
                            @click="changeTrendDays(d)"
                        >{{ d }}H</button>
                    </div>
                </div>
                <div class="dlb-card-body">
                    <div v-if="loading.trend" class="dlb-chart-sk dlb-shimmer"></div>
                    <apexchart v-else type="area" height="270" :options="trendOptions" :series="trendSeries" />
                </div>
            </div>
            <div class="dlb-card">
                <div class="dlb-card-head">
                    <div>
                        <div class="dlb-card-title">Status Penyelesaian</div>
                        <div class="dlb-card-sub">Proporsi pengujian selesai vs. pending</div>
                    </div>
                </div>
                <div class="dlb-card-body dlb-center">
                    <div v-if="loading.pie" class="dlb-donut-sk dlb-shimmer"></div>
                    <apexchart v-else type="donut" height="270" :options="pieOptions" :series="pieSeries" />
                </div>
            </div>
        </div>

        <!-- ── Charts Row 2: Volume Per Jenis (full-width hbar) ── -->
        <div class="dlb-card">
            <div class="dlb-card-head">
                <div>
                    <div class="dlb-card-title">Volume Per Jenis Analisa</div>
                    <div class="dlb-card-sub">Total pengujian berdasarkan kategori analisa — keseluruhan data</div>
                </div>
            </div>
            <div class="dlb-card-body">
                <div v-if="loading.radar" class="dlb-chart-sk dlb-shimmer"></div>
                <apexchart v-else type="bar" :height="hbarHeight" :options="hbarOptions" :series="hbarSeries" />
            </div>
        </div>

        <!-- ── Total Keseluruhan ───────────────────────────── -->
        <div class="dlb-section-label">
            <i class="ri-database-2-line"></i>
            <span>Total Keseluruhan</span>
        </div>
        <div class="dlb-alltime-grid">
            <template v-if="loading.kpiAll">
                <div v-for="n in 4" :key="n" class="dlb-alltime-card">
                    <div class="dlb-sk-circle dlb-shimmer"></div>
                    <div class="dlb-sk-line w50 dlb-shimmer" style="height:24px;margin:14px auto 6px;"></div>
                    <div class="dlb-sk-line w70 dlb-shimmer" style="height:12px;margin:0 auto 4px;"></div>
                    <div class="dlb-sk-line w40 dlb-shimmer" style="height:10px;margin:0 auto;"></div>
                </div>
            </template>
            <template v-else>
                <div v-for="(item, i) in kpiAll" :key="i" class="dlb-alltime-card">
                    <div class="dlb-alltime-icon" :style="{ background: item.color + '18', color: item.color }">
                        <i :class="item.icon || 'ri-bar-chart-line'"></i>
                    </div>
                    <div class="dlb-alltime-value" :style="{ color: item.color }">
                        {{ (item.value || 0).toLocaleString('id-ID') }}
                    </div>
                    <div class="dlb-alltime-label">{{ item.title }}</div>
                    <div class="dlb-alltime-sub">{{ item.subtitle }}</div>
                </div>
            </template>
        </div>

        <!-- ── Aktivitas Pengujian Terbaru ────────────────── -->
        <div class="dlb-card">
            <div class="dlb-card-head">
                <div>
                    <div class="dlb-card-title">Aktivitas Pengujian Terbaru</div>
                    <div class="dlb-card-sub">5 pengujian terakhir yang telah diselesaikan</div>
                </div>
                <span class="dlb-count-badge">{{ aktivitasList.length }} data</span>
            </div>
            <div class="dlb-card-body dlb-no-pad">
                <template v-if="loading.aktivitas">
                    <div v-for="n in 5" :key="n" class="dlb-sk-row dlb-shimmer"></div>
                </template>
                <template v-else>
                    <div v-if="!aktivitasList.length" class="dlb-empty">
                        <i class="ri-inbox-line"></i>
                        <p>Belum ada data aktivitas pengujian</p>
                    </div>
                    <div v-else class="dlb-table-wrap">
                        <table class="dlb-table">
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
                                    <td><code class="dlb-code">{{ item.id || '-' }}</code></td>
                                    <td><span class="dlb-badge-sampel">{{ item.no_sampel || '-' }}</span></td>
                                    <td><span class="dlb-badge-jenis">{{ item.jenis_analisa || '-' }}</span></td>
                                    <td><span class="dlb-badge-user">{{ item.user || '-' }}</span></td>
                                    <td class="dlb-muted">{{ item.tanggal || '-' }}</td>
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

const PALETTE = ['#405189', '#0ab39c', '#f7b84b', '#f06548', '#4b93f7', '#6f42c1'];

export default {
    components: { apexchart: VueApexCharts },

    props: {
        namaPengguna: { type: String, default: 'Analis' },
        aksesSpesial: { type: Boolean, default: false },
    },

    data() {
        const now = new Date();
        return {
            PALETTE,
            currentDate: now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
            currentTime: now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
            clockTimer: null,

            kpiToday: [],
            kpiAll: [],
            tat: { hours: null, minutes: null },
            aktivitasList: [],

            trend: { categories: [], series: [] },
            trendDays: 14,
            pie: { series: [], labels: [] },
            radar: { series: [], labels: [] },

            loading: {
                kpiToday: false,
                kpiAll: false,
                trend: false,
                pie: false,
                radar: false,
                aktivitas: false,
            },
        };
    },

    computed: {
        anyLoading() {
            return Object.values(this.loading).some(Boolean);
        },

        trendOptions() {
            return {
                chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false } },
                colors: ['#405189', '#0ab39c'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [20, 100] },
                },
                stroke: { curve: 'smooth', width: 2 },
                xaxis: {
                    categories: this.trend.categories,
                    labels: { style: { fontSize: '11px' } },
                },
                yaxis: { labels: { style: { fontSize: '11px' } } },
                tooltip: { shared: true, intersect: false },
                legend: { position: 'top', fontSize: '12px' },
                grid: { borderColor: '#f2f2f2', strokeDashArray: 4 },
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
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: { show: true, total: { show: true, label: 'Total' } },
                        },
                    },
                },
                dataLabels: { enabled: true, formatter: v => v.toFixed(1) + '%' },
                stroke: { width: 2 },
            };
        },

        pieSeries() { return this.pie.series; },

        hbarHeight() {
            const count = this.radar.labels.length || 6;
            return Math.max(260, count * 38);
        },

        hbarOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, zoom: { enabled: false } },
                colors: PALETTE,
                plotOptions: {
                    bar: {
                        horizontal: true,
                        distributed: true,
                        borderRadius: 5,
                        barHeight: '58%',
                        dataLabels: { position: 'right' },
                    },
                },
                dataLabels: {
                    enabled: true,
                    offsetX: 6,
                    style: { fontSize: '12px', fontWeight: '600', colors: ['#495057'] },
                    formatter: v => v.toLocaleString('id-ID'),
                },
                xaxis: {
                    categories: this.radar.labels,
                    labels: { style: { fontSize: '11px' } },
                },
                yaxis: {
                    labels: {
                        style: { fontSize: '12px', fontWeight: '500', colors: ['#495057'] },
                        maxWidth: 200,
                    },
                },
                legend: { show: false },
                grid: {
                    borderColor: '#f2f2f2',
                    xaxis: { lines: { show: true } },
                    yaxis: { lines: { show: false } },
                },
                tooltip: {
                    y: { formatter: v => v.toLocaleString('id-ID') + ' pengujian' },
                },
            };
        },

        hbarSeries() {
            return [{ name: 'Jumlah Uji', data: this.radar.series }];
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
            try {
                const res = await axios.get('/api/v1/dashboard/analyzer/kpi-tat');
                const r = res.data?.result ?? {};
                this.tat = { hours: r.hours ?? 0, minutes: r.minutes ?? 0 };
            } catch { this.tat = { hours: 0, minutes: 0 }; }
        },

        async fetchTrend() {
            this.loading.trend = true;
            try {
                const res = await axios.get('/api/v1/dashboard/grafik/jumlah-uji-perhari', { params: { days: this.trendDays } });
                const r = res.data?.result ?? {};
                this.trend = {
                    categories: r.chartLineOptions?.xaxis?.categories ?? [],
                    series: r.chartLineSeries ?? [],
                };
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
            } catch {
                this.radar = { series: [], labels: [] };
            } finally {
                this.loading.radar = false;
            }
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

        startClock() {
            this.clockTimer = setInterval(() => {
                this.currentTime = new Date().toLocaleTimeString('id-ID', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                });
            }, 1000);
        },
    },

    mounted() {
        this.refreshAll();
        this.startClock();
    },

    beforeUnmount() {
        if (this.clockTimer) clearInterval(this.clockTimer);
    },
};
</script>

<style scoped>
/* ── Base ─────────────────────────────────────────────────── */
.dlb-page {
    font-family: 'Inter', 'Segoe UI', sans-serif;
    color: #343a40;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ── Hero ─────────────────────────────────────────────────── */
.dlb-hero {
    position: relative;
    background: linear-gradient(135deg, #405189 0%, #2c3b74 55%, #19254d 100%);
    border-radius: 16px;
    overflow: hidden;
    padding: 28px 32px;
}

.dlb-hero-dots {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,.08) 1px, transparent 1px);
    background-size: 24px 24px;
    pointer-events: none;
}

.dlb-hero-inner {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 18px;
}

.dlb-hero-left {
    display: flex;
    align-items: center;
    gap: 18px;
}

.dlb-hero-icon-box {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: rgba(255,255,255,.15);
    backdrop-filter: blur(6px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,.2);
}

.dlb-hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.25);
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255,255,255,.9);
    letter-spacing: .4px;
    margin-bottom: 8px;
}

.dlb-hero-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 5px;
    line-height: 1.2;
}

.dlb-hero-sub {
    font-size: 13px;
    color: rgba(255,255,255,.7);
    margin: 0;
}

.dlb-hero-date {
    opacity: .7;
    font-size: 12px;
}

.dlb-hero-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.dlb-tat-pill,
.dlb-clock-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 12px;
    padding: 10px 16px;
    backdrop-filter: blur(4px);
}

.dlb-pill-icon {
    font-size: 20px;
    color: rgba(255,255,255,.8);
}

.dlb-pill-value {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}

.dlb-pill-label {
    font-size: 10px;
    color: rgba(255,255,255,.6);
    margin-top: 1px;
}

.dlb-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    background: rgba(255,255,255,.15);
    border: 1.5px solid rgba(255,255,255,.3);
    border-radius: 10px;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
    backdrop-filter: blur(4px);
}

.dlb-refresh-btn:hover:not(:disabled) { background: rgba(255,255,255,.25); }
.dlb-refresh-btn:disabled { opacity: .55; cursor: not-allowed; }

/* ── Overview Strip ───────────────────────────────────────── */
.dlb-strip-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    box-shadow: 0 1px 5px rgba(64,81,137,.07);
    overflow: hidden;
}

.dlb-strip-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 20px;
    border-bottom: 1px solid #f0f2f5;
    font-size: 12px;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: .7px;
}

.dlb-strip-period {
    margin-left: auto;
    font-size: 11px;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    color: #adb5bd;
}

.dlb-strip-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
}

.dlb-strip-col {
    padding: 18px 16px;
    border-right: 1px solid #f0f2f5;
    position: relative;
    transition: background .15s;
}

.dlb-strip-col:last-child { border-right: none; }

.dlb-strip-col::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--dlb-sc, #405189);
    border-radius: 0 0 3px 3px;
}

.dlb-strip-col:hover { background: #fafbff; }

.dlb-strip-icon-row {
    margin-bottom: 8px;
}

.dlb-strip-icon {
    font-size: 18px;
    color: var(--dlb-sc, #405189);
    opacity: .85;
}

.dlb-strip-value {
    font-size: 26px;
    font-weight: 800;
    color: var(--dlb-sc, #405189);
    line-height: 1;
    margin-bottom: 5px;
    font-variant-numeric: tabular-nums;
}

.dlb-strip-label {
    font-size: 11px;
    font-weight: 600;
    color: #495057;
    line-height: 1.3;
    margin-bottom: 2px;
}

.dlb-strip-sub {
    font-size: 10px;
    color: #adb5bd;
}

.dlb-strip-sk { pointer-events: none; }

/* ── Charts Layout ────────────────────────────────────────── */
.dlb-row-7-3 {
    display: grid;
    grid-template-columns: 7fr 3fr;
    gap: 18px;
}

.dlb-row-half {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

/* ── Card ─────────────────────────────────────────────────── */
.dlb-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 5px rgba(64,81,137,.06);
}

.dlb-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 18px 20px 0;
    gap: 10px;
}

.dlb-card-title { font-size: 14px; font-weight: 700; color: #212529; margin-bottom: 2px; }
.dlb-card-sub   { font-size: 11px; color: #878a99; }
.dlb-card-body  { padding: 14px 20px 18px; }
.dlb-card-body.dlb-no-pad { padding: 0; }
.dlb-card-body.dlb-center { display: flex; justify-content: center; align-items: center; }

/* ── Day Buttons ──────────────────────────────────────────── */
.dlb-day-btns { display: flex; gap: 4px; flex-shrink: 0; }
.dlb-day-btn {
    padding: 4px 11px;
    border: 1px solid #dee2e6;
    border-radius: 7px;
    font-size: 11px;
    font-weight: 600;
    background: #fff;
    color: #878a99;
    cursor: pointer;
    transition: .15s;
}
.dlb-day-btn.active { background: #405189; color: #fff; border-color: #405189; }
.dlb-day-btn:hover:not(.active) { background: #f8f9fa; }

/* ── Section Label ────────────────────────────────────────── */
.dlb-section-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: #878a99;
    margin-bottom: -4px;
}

/* ── All-time Cards ───────────────────────────────────────── */
.dlb-alltime-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.dlb-alltime-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    padding: 24px 16px 20px;
    text-align: center;
    transition: box-shadow .2s, transform .2s;
    box-shadow: 0 1px 5px rgba(64,81,137,.06);
}
.dlb-alltime-card:hover {
    box-shadow: 0 6px 18px rgba(64,81,137,.1);
    transform: translateY(-2px);
}

.dlb-alltime-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin: 0 auto 14px;
}

.dlb-alltime-value {
    font-size: 30px;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 6px;
    font-variant-numeric: tabular-nums;
}

.dlb-alltime-label {
    font-size: 12px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 3px;
}

.dlb-alltime-sub {
    font-size: 11px;
    color: #adb5bd;
}

/* ── Count Badge ──────────────────────────────────────────── */
.dlb-count-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    background: rgba(64,81,137,.08);
    color: #405189;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

/* ── Table ────────────────────────────────────────────────── */
.dlb-table-wrap { overflow-x: auto; }
.dlb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.dlb-table thead tr { border-bottom: 2px solid #e9ecef; background: #f8f9fc; }
.dlb-table th {
    padding: 10px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #878a99;
    white-space: nowrap;
}
.dlb-table td { padding: 11px 16px; border-bottom: 1px solid #f0f2f5; vertical-align: middle; }
.dlb-table tbody tr:last-child td { border-bottom: none; }
.dlb-table tbody tr:hover { background: #f8f9fc; }

.dlb-code { font-family: 'Courier New', monospace; font-size: 12px; color: #6c757d; }
.dlb-muted { color: #878a99; font-size: 12px; }

.dlb-badge-sampel {
    display: inline-block; padding: 3px 9px;
    background: rgba(64,81,137,.1); color: #405189;
    border-radius: 6px; font-size: 12px; font-weight: 600; font-family: monospace;
}
.dlb-badge-jenis {
    display: inline-block; padding: 3px 9px;
    background: rgba(64,81,137,.08); color: #405189;
    border-radius: 6px; font-size: 11px; font-weight: 600;
}
.dlb-badge-user {
    display: inline-block; padding: 3px 9px;
    background: rgba(10,179,156,.1); color: #0ab39c;
    border-radius: 20px; font-size: 11px; font-weight: 600;
}

.dlb-empty {
    text-align: center;
    padding: 40px;
    color: #adb5bd;
}
.dlb-empty i { font-size: 36px; display: block; margin-bottom: 8px; }
.dlb-empty p { font-size: 13px; margin: 0; }

/* ── Skeleton / Shimmer ───────────────────────────────────── */
@keyframes dlb-shimmer {
    0%   { background-position: -600px 0; }
    100% { background-position:  600px 0; }
}

.dlb-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 1200px 100%;
    animation: dlb-shimmer 1.5s infinite linear;
    border-radius: 6px;
}

.dlb-sk-line { height: 13px; border-radius: 4px; display: block; }
.w40 { width: 40%; }
.w50 { width: 50%; }
.w60 { width: 60%; }
.w70 { width: 70%; }
.mb2 { margin-bottom: 8px; }

.dlb-chart-sk  { height: 270px; border-radius: 8px; }
.dlb-donut-sk  { width: 210px; height: 210px; border-radius: 50%; }
.dlb-sk-circle { width: 56px; height: 56px; border-radius: 14px; margin: 0 auto; }

.dlb-sk-row {
    height: 48px;
    margin: 0;
    border-bottom: 1px solid #f8f9fc;
    border-radius: 0;
}

/* ── Spinner ──────────────────────────────────────────────── */
@keyframes dlb-spin { to { transform: rotate(360deg); } }
.dlb-spin { display: inline-block; animation: dlb-spin .75s linear infinite; }

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 1400px) {
    .dlb-strip-grid { grid-template-columns: repeat(3, 1fr); }
    .dlb-strip-col:nth-child(3) { border-right: none; }
    .dlb-strip-col:nth-child(4) { border-top: 1px solid #f0f2f5; }
    .dlb-strip-col:nth-child(5) { border-top: 1px solid #f0f2f5; }
    .dlb-strip-col:nth-child(6) { border-top: 1px solid #f0f2f5; border-right: none; }
}

@media (max-width: 1100px) {
    .dlb-alltime-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 992px) {
    .dlb-row-7-3  { grid-template-columns: 1fr; }
    .dlb-row-half { grid-template-columns: 1fr; }
    .dlb-hero { padding: 22px 22px; }
    .dlb-hero-title { font-size: 18px; }
}

@media (max-width: 768px) {
    .dlb-strip-grid { grid-template-columns: repeat(2, 1fr); }
    .dlb-strip-col { border-right: 1px solid #f0f2f5 !important; border-top: 1px solid #f0f2f5; }
    .dlb-strip-col:nth-child(odd)  { border-right: 1px solid #f0f2f5 !important; }
    .dlb-strip-col:nth-child(even) { border-right: none !important; }
    .dlb-strip-col:nth-child(1),
    .dlb-strip-col:nth-child(2)    { border-top: none; }
    .dlb-hero-right { gap: 8px; }
    .dlb-tat-pill, .dlb-clock-pill { padding: 8px 12px; }
    .dlb-pill-value { font-size: 13px; }
}

@media (max-width: 576px) {
    .dlb-strip-grid { grid-template-columns: 1fr 1fr; }
    .dlb-alltime-grid { grid-template-columns: 1fr 1fr; }
    .dlb-hero { padding: 18px 16px; }
    .dlb-hero-left { gap: 12px; }
    .dlb-hero-icon-box { width: 48px; height: 48px; font-size: 22px; }
    .dlb-hero-title { font-size: 16px; }
    .dlb-hero-right { width: 100%; }
    .dlb-tat-pill, .dlb-clock-pill { flex: 1; }
    .dlb-refresh-btn { width: 100%; justify-content: center; }
    .dlb-clock-pill { display: none; }
    .dlb-strip-value { font-size: 22px; }
}

@media (max-width: 420px) {
    .dlb-strip-grid   { grid-template-columns: 1fr; }
    .dlb-alltime-grid { grid-template-columns: 1fr; }
    .dlb-strip-col { border-right: none !important; }
}
</style>
