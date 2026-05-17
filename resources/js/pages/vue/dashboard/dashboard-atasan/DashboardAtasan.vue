<template>
    <div class="at-page">

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- HERO HEADER                                        -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="at-hero">
            <div class="at-hero-bg"></div>
            <div class="at-hero-content">
                <div class="at-hero-left">
                    <div class="at-hero-icon"><i class="ri-dashboard-3-line"></i></div>
                    <div>
                        <h2 class="at-hero-title">Dashboard Monitoring Laboratorium</h2>
                        <p class="at-hero-sub">
                            Ringkasan menyeluruh performa &amp; operasional lab —
                            <strong>{{ namaPengguna }}</strong>
                        </p>
                    </div>
                </div>
                <div class="at-hero-right">
                    <div class="at-hero-clock">
                        <div class="at-clock-time">{{ liveClock }}</div>
                        <div class="at-clock-date">{{ currentDate }}</div>
                    </div>
                    <div class="at-periode-pill" v-if="kpi.periode">
                        <i class="ri-calendar-check-line"></i>
                        Periode: <strong>{{ kpi.periode }}</strong>
                    </div>
                    <button class="at-refresh-btn" @click="refreshAll" :disabled="anyLoading">
                        <i :class="anyLoading ? 'ri-loader-4-line at-spin' : 'ri-refresh-line'"></i>
                        Segarkan
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- KPI BULANAN (6 cards)                              -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="at-section-label">
            <i class="ri-pulse-line"></i> KPI Bulan Berjalan
        </div>
        <div class="at-kpi6">
            <template v-if="loading.kpi">
                <div v-for="n in 6" :key="n" class="at-kpi-card at-sk">
                    <div class="at-sk-icon at-shimmer"></div>
                    <div style="flex:1">
                        <div class="at-sk-line w55 at-shimmer mb2"></div>
                        <div class="at-sk-line w35 at-shimmer mb2"></div>
                        <div class="at-sk-line w45 at-shimmer"></div>
                    </div>
                </div>
            </template>
            <template v-else>
                <div
                    v-for="(c, i) in kpiCards"
                    :key="i"
                    class="at-kpi-card"
                    :style="{ '--accent': c.color }"
                >
                    <div class="at-kpi-left">
                        <div class="at-kpi-icon"><i :class="c.icon"></i></div>
                        <div class="at-kpi-body">
                            <div class="at-kpi-label">{{ c.label }}</div>
                            <div class="at-kpi-value">{{ c.value }}</div>
                            <div class="at-kpi-sub">{{ c.sub }}</div>
                        </div>
                    </div>
                    <div class="at-kpi-trend" v-if="c.trend !== undefined">
                        <span :class="c.trend >= 0 ? 'at-up' : 'at-dn'">
                            <i :class="c.trend >= 0 ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
                        </span>
                    </div>
                </div>
            </template>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- TREN BULANAN — full width                          -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="at-card">
            <div class="at-card-head">
                <div>
                    <div class="at-card-title">Tren Aktivitas Laboratorium {{ currentYear }}</div>
                    <div class="at-card-sub">Registrasi sampel, uji selesai, dan validasi final per bulan</div>
                </div>
                <div class="at-legend-pills">
                    <span class="at-pill" style="--c:#405189">Registrasi</span>
                    <span class="at-pill" style="--c:#0ab39c">Uji Selesai</span>
                    <span class="at-pill" style="--c:#f7b84b">Validasi</span>
                </div>
            </div>
            <div class="at-card-body">
                <div v-if="loading.tren" class="at-chart-sk at-shimmer"></div>
                <apexchart v-else type="area" height="300" :options="trenOptions" :series="trenSeries" />
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- ROW: Beban Mesin + Status Overall                  -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="at-row-6-4">
            <!-- Beban Mesin horizontal bar -->
            <div class="at-card">
                <div class="at-card-head">
                    <div>
                        <div class="at-card-title">Beban Kerja Per Mesin</div>
                        <div class="at-card-sub">Total &amp; selesai per mesin analisa</div>
                    </div>
                </div>
                <div class="at-card-body">
                    <div v-if="loading.beban" class="at-chart-sk at-shimmer" style="height:280px"></div>
                    <apexchart v-else type="bar" height="280" :options="bebanOptions" :series="bebanSeries" />
                </div>
            </div>

            <!-- Donut status overall -->
            <div class="at-card">
                <div class="at-card-head">
                    <div class="at-card-title">Status Sampel Keseluruhan</div>
                    <div class="at-card-sub">Distribusi status semua sampel</div>
                </div>
                <div class="at-card-body at-flex-center">
                    <div v-if="loading.status" class="at-donut-sk at-shimmer"></div>
                    <apexchart v-else type="donut" height="280" :options="statusDonutOptions" :series="statusDonutSeries" />
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- ROW: Pass Rate + Top Analis (bulan ini)            -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="at-row-half">
            <!-- Pass Rate per Jenis Analisa -->
            <div class="at-card">
                <div class="at-card-head">
                    <div>
                        <div class="at-card-title">Pass Rate per Jenis Analisa</div>
                        <div class="at-card-sub">Persentase hasil layak per kategori pengujian</div>
                    </div>
                    <div class="at-badge-ok">Target ≥ 95%</div>
                </div>
                <div class="at-card-body">
                    <div v-if="loading.passRate" class="at-chart-sk at-shimmer" style="height:300px"></div>
                    <apexchart v-else type="bar" height="300" :options="passRateOptions" :series="passRateSeries" />
                </div>
            </div>

            <!-- Top Analis bulan ini -->
            <div class="at-card">
                <div class="at-card-head">
                    <div>
                        <div class="at-card-title">Produktivitas Analis — Bulan Ini</div>
                        <div class="at-card-sub">Jumlah pengujian selesai per analis</div>
                    </div>
                </div>
                <div class="at-card-body">
                    <div v-if="loading.topAnalis" class="at-chart-sk at-shimmer" style="height:300px"></div>
                    <apexchart v-else type="bar" height="300" :options="topAnalisOptions" :series="topAnalisSeries" />
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- RINGKASAN VALIDASI FINAL                           -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="at-card">
            <div class="at-card-head">
                <div>
                    <div class="at-card-title">Ringkasan Validasi Final — Bulan Ini</div>
                    <div class="at-card-sub">Status persetujuan hasil pengujian akhir</div>
                </div>
            </div>
            <div class="at-card-body">
                <div v-if="loading.kpi" class="at-validasi-sk">
                    <div v-for="n in 3" :key="n" class="at-validasi-sk-item at-shimmer"></div>
                </div>
                <div v-else class="at-validasi-summary">
                    <div class="at-vs-item at-vs-total">
                        <div class="at-vs-icon"><i class="ri-file-list-3-line"></i></div>
                        <div class="at-vs-body">
                            <div class="at-vs-val">{{ kpi.validasi_bulan ?? 0 }}</div>
                            <div class="at-vs-lbl">Total Validasi</div>
                        </div>
                    </div>
                    <div class="at-vs-divider"></div>
                    <div class="at-vs-item at-vs-ok">
                        <div class="at-vs-icon"><i class="ri-checkbox-circle-line"></i></div>
                        <div class="at-vs-body">
                            <div class="at-vs-val">{{ kpi.validasi_ok ?? 0 }}</div>
                            <div class="at-vs-lbl">Disetujui (OK)</div>
                        </div>
                    </div>
                    <div class="at-vs-divider"></div>
                    <div class="at-vs-item at-vs-fg">
                        <div class="at-vs-icon"><i class="ri-trophy-line"></i></div>
                        <div class="at-vs-body">
                            <div class="at-vs-val">{{ kpi.validasi_fg ?? 0 }}</div>
                            <div class="at-vs-lbl">Finish Good (FG)</div>
                        </div>
                    </div>
                    <div class="at-vs-divider"></div>
                    <div class="at-vs-item at-vs-pct">
                        <div class="at-vs-icon"><i class="ri-percent-line"></i></div>
                        <div class="at-vs-body">
                            <div class="at-vs-val">{{ validasiOkPct }}%</div>
                            <div class="at-vs-lbl">Approval Rate</div>
                        </div>
                    </div>
                    <div class="at-vs-divider"></div>
                    <div class="at-vs-item at-vs-tat">
                        <div class="at-vs-icon"><i class="ri-timer-2-line"></i></div>
                        <div class="at-vs-body">
                            <div class="at-vs-val">{{ kpi.tat_hours }}j {{ kpi.tat_minutes }}m</div>
                            <div class="at-vs-lbl">Rata-rata TAT</div>
                        </div>
                    </div>
                    <div class="at-vs-divider"></div>
                    <div class="at-vs-item at-vs-pass">
                        <div class="at-vs-icon"><i class="ri-shield-check-line"></i></div>
                        <div class="at-vs-body">
                            <div class="at-vs-val">{{ kpi.pass_rate_pct ?? 0 }}%</div>
                            <div class="at-vs-lbl">Pass Rate Uji</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- STATUS CARDS — all-time totals                     -->
        <!-- ═══════════════════════════════════════════════════ -->
        <div class="at-section-label">
            <i class="ri-stack-line"></i> Rekap Keseluruhan
        </div>
        <div class="at-stat4">
            <div class="at-stat-card" v-for="(s, i) in statusAllCards" :key="i" :style="{ '--c': s.color }">
                <i :class="['at-stat-icon', s.icon]"></i>
                <div class="at-stat-val">{{ s.value.toLocaleString('id-ID') }}</div>
                <div class="at-stat-lbl">{{ s.label }}</div>
            </div>
        </div>

    </div>
</template>

<script>
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';

export default {
    components: { apexchart: VueApexCharts },

    props: {
        namaPengguna: { type: String, default: 'Atasan' },
    },

    data() {
        const now = new Date();
        return {
            currentDate: now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
            currentYear: now.getFullYear(),
            liveClock: '',
            clockTimer: null,

            kpi: {},
            tren: { categories: [], series: [] },
            beban: { categories: [], total: [], selesai: [] },
            statusOverall: { selesai: 0, pending: 0, trial: 0, close_po: 0 },
            passRate: { categories: [], data: [] },
            topAnalis: { categories: [], data: [] },

            loading: {
                kpi: false,
                tren: false,
                beban: false,
                status: false,
                passRate: false,
                topAnalis: false,
            },
        };
    },

    computed: {
        anyLoading() { return Object.values(this.loading).some(Boolean); },

        kpiCards() {
            const k = this.kpi;
            return [
                {
                    icon: 'ri-test-tube-line', color: '#405189',
                    label: 'Registrasi Sampel', sub: 'Bulan ini',
                    value: (k.sampel_bulan_ini ?? 0).toLocaleString('id-ID'),
                },
                {
                    icon: 'ri-checkbox-circle-line', color: '#0ab39c',
                    label: 'Uji Selesai', sub: 'Bulan ini',
                    value: (k.uji_selesai ?? 0).toLocaleString('id-ID'),
                },
                {
                    icon: 'ri-shield-check-line', color: '#4b93f7',
                    label: 'Pass Rate', sub: 'Hasil layak / total uji',
                    value: (k.pass_rate_pct ?? 0) + '%',
                },
                {
                    icon: 'ri-timer-2-line', color: '#f7b84b',
                    label: 'Rata-rata TAT', sub: 'Turn Around Time',
                    value: `${k.tat_hours ?? 0}j ${k.tat_minutes ?? 0}m`,
                },
                {
                    icon: 'ri-file-list-3-line', color: '#6f42c1',
                    label: 'Validasi Final', sub: 'Bulan ini',
                    value: (k.validasi_bulan ?? 0).toLocaleString('id-ID'),
                },
                {
                    icon: 'ri-flask-line', color: '#f06548',
                    label: 'Trial Produksi', sub: 'Sampel trial bulan ini',
                    value: (k.sampel_trial ?? 0).toLocaleString('id-ID'),
                },
            ];
        },

        validasiOkPct() {
            const total = this.kpi.validasi_bulan ?? 0;
            const ok    = this.kpi.validasi_ok    ?? 0;
            return total > 0 ? (ok / total * 100).toFixed(1) : '0.0';
        },

        statusAllCards() {
            const s = this.statusOverall;
            return [
                { icon: 'ri-checkbox-circle-line', color: '#0ab39c', label: 'Selesai',       value: s.selesai  ?? 0 },
                { icon: 'ri-time-line',             color: '#f7b84b', label: 'Pending',        value: s.pending  ?? 0 },
                { icon: 'ri-flask-line',            color: '#4b93f7', label: 'Trial Produksi', value: s.trial    ?? 0 },
                { icon: 'ri-lock-line',             color: '#f06548', label: 'Close PO',       value: s.close_po ?? 0 },
            ];
        },

        // ── Tren Bulanan ──────────────────────────────────────
        trenOptions() {
            return {
                chart: { type: 'area', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                colors: ['#405189', '#0ab39c', '#f7b84b'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.04, stops: [0, 100] } },
                stroke: { curve: 'smooth', width: [2.5, 2.5, 2] },
                xaxis: { categories: this.tren.categories, labels: { style: { fontSize: '12px' } }, axisBorder: { show: false } },
                yaxis: { labels: { style: { fontSize: '12px' } } },
                tooltip: { shared: true, intersect: false },
                legend: { show: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
                dataLabels: { enabled: false },
                markers: { size: 0, hover: { size: 5 } },
            };
        },
        trenSeries() { return this.tren.series; },

        // ── Beban Mesin ───────────────────────────────────────
        bebanOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                plotOptions: { bar: { horizontal: true, borderRadius: 4, dataLabels: { position: 'top' }, barHeight: '65%' } },
                colors: ['#405189', '#0ab39c'],
                xaxis: { categories: this.beban.categories, labels: { style: { fontSize: '11px' } } },
                yaxis: { labels: { style: { fontSize: '11px' }, maxWidth: 120 } },
                legend: { position: 'top', fontSize: '12px' },
                dataLabels: { enabled: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
                tooltip: { shared: true, intersect: false },
            };
        },
        bebanSeries() {
            return [
                { name: 'Total Sampel', data: this.beban.total },
                { name: 'Selesai',      data: this.beban.selesai },
            ];
        },

        // ── Status Donut ──────────────────────────────────────
        statusDonutOptions() {
            return {
                chart: { type: 'donut', fontFamily: 'Inter, sans-serif' },
                labels: ['Selesai', 'Pending', 'Trial', 'Close PO'],
                colors: ['#0ab39c', '#f7b84b', '#4b93f7', '#f06548'],
                legend: { position: 'bottom', fontSize: '12px' },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '72%',
                            labels: {
                                show: true,
                                total: { show: true, label: 'Total', fontSize: '13px', fontWeight: 700 },
                            },
                        },
                    },
                },
                dataLabels: { enabled: false },
                stroke: { width: 2, colors: ['#fff'] },
            };
        },
        statusDonutSeries() {
            const s = this.statusOverall;
            return [s.selesai ?? 0, s.pending ?? 0, s.trial ?? 0, s.close_po ?? 0];
        },

        // ── Pass Rate ─────────────────────────────────────────
        passRateOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: '60%',
                        dataLabels: { position: 'top' },
                    },
                },
                colors: [({ value }) => value >= 95 ? '#0ab39c' : value >= 80 ? '#f7b84b' : '#f06548'],
                xaxis: {
                    categories: this.passRate.categories,
                    min: 0, max: 100,
                    labels: { formatter: v => v + '%', style: { fontSize: '11px' } },
                },
                yaxis: { labels: { style: { fontSize: '11px' }, maxWidth: 130 } },
                dataLabels: {
                    enabled: true,
                    formatter: v => v + '%',
                    style: { fontSize: '11px', colors: ['#495057'] },
                    offsetX: 24,
                },
                annotations: {
                    xaxis: [{ x: 95, borderColor: '#f06548', strokeDashArray: 4, label: { text: '95%', style: { color: '#f06548', fontSize: '11px', background: 'transparent' } } }],
                },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
                legend: { show: false },
            };
        },
        passRateSeries() {
            return [{ name: 'Pass Rate', data: this.passRate.data }];
        },

        // ── Top Analis ────────────────────────────────────────
        topAnalisOptions() {
            return {
                chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: '60%',
                        distributed: true,
                    },
                },
                colors: ['#405189','#0ab39c','#f7b84b','#f06548','#4b93f7','#6f42c1','#e83e8c','#20c997'],
                xaxis: { categories: this.topAnalis.categories, labels: { style: { fontSize: '11px' } } },
                yaxis: { labels: { style: { fontSize: '11px', fontWeight: 600 }, maxWidth: 100 } },
                dataLabels: { enabled: true, style: { fontSize: '11px', colors: ['#fff'] } },
                legend: { show: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 3 },
                tooltip: { y: { formatter: v => v + ' pengujian' } },
            };
        },
        topAnalisSeries() {
            return [{ name: 'Uji Selesai', data: this.topAnalis.data }];
        },
    },

    methods: {
        tickClock() {
            const now = new Date();
            this.liveClock = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },

        async fetchKpi() {
            this.loading.kpi = true;
            try {
                const r = await axios.get('/api/v1/dashboard-atasan/kpi-bulanan');
                this.kpi = r.data?.result ?? {};
            } catch { this.kpi = {}; }
            finally { this.loading.kpi = false; }
        },

        async fetchTren() {
            this.loading.tren = true;
            try {
                const r = await axios.get('/api/v1/dashboard-atasan/tren-bulanan');
                this.tren = r.data?.result ?? { categories: [], series: [] };
            } catch { this.tren = { categories: [], series: [] }; }
            finally { this.loading.tren = false; }
        },

        async fetchBeban() {
            this.loading.beban = true;
            try {
                const r = await axios.get('/api/v1/dashboard-atasan/beban-mesin');
                const d = r.data?.result ?? {};
                this.beban = {
                    categories: d.categories ?? [],
                    total:   d.total   ?? [],
                    selesai: d.selesai ?? [],
                };
            } catch { this.beban = { categories: [], total: [], selesai: [] }; }
            finally { this.loading.beban = false; }
        },

        async fetchStatus() {
            this.loading.status = true;
            try {
                const r = await axios.get('/api/v1/dashboard-atasan/status-overall');
                this.statusOverall = r.data?.result ?? {};
            } catch { this.statusOverall = {}; }
            finally { this.loading.status = false; }
        },

        async fetchPassRate() {
            this.loading.passRate = true;
            try {
                const r = await axios.get('/api/v1/dashboard-atasan/pass-rate-jenis');
                this.passRate = r.data?.result ?? { categories: [], data: [] };
            } catch { this.passRate = { categories: [], data: [] }; }
            finally { this.loading.passRate = false; }
        },

        async fetchTopAnalis() {
            this.loading.topAnalis = true;
            try {
                const r = await axios.get('/api/v1/dashboard-atasan/top-analis');
                this.topAnalis = r.data?.result ?? { categories: [], data: [] };
            } catch { this.topAnalis = { categories: [], data: [] }; }
            finally { this.loading.topAnalis = false; }
        },

        refreshAll() {
            Promise.all([
                this.fetchKpi(),
                this.fetchTren(),
                this.fetchBeban(),
                this.fetchStatus(),
                this.fetchPassRate(),
                this.fetchTopAnalis(),
            ]);
        },
    },

    mounted() {
        this.tickClock();
        this.clockTimer = setInterval(this.tickClock, 1000);
        this.refreshAll();
    },

    beforeUnmount() {
        clearInterval(this.clockTimer);
    },
};
</script>

<style scoped>
/* ── Base ─────────────────────────────────────────────────── */
.at-page {
    font-family: 'Inter', 'Segoe UI', sans-serif;
    color: #343a40;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ── Hero Header ──────────────────────────────────────────── */
.at-hero {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    padding: 28px 28px;
    color: #fff;
}

.at-hero-bg {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, #405189 0%, #2d3a6b 45%, #1a2346 100%);
    z-index: 0;
}
.at-hero-bg::after {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='28'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
}

.at-hero-content {
    position: relative; z-index: 1;
    display: flex; align-items: center;
    justify-content: space-between;
    flex-wrap: wrap; gap: 16px;
}

.at-hero-left { display: flex; align-items: center; gap: 16px; }

.at-hero-icon {
    width: 56px; height: 56px; border-radius: 14px;
    background: rgba(255,255,255,.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 28px; flex-shrink: 0;
    backdrop-filter: blur(6px);
}

.at-hero-title { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
.at-hero-sub   { font-size: 13px; opacity: .75; margin: 0; }

.at-hero-right {
    display: flex; align-items: center;
    gap: 12px; flex-wrap: wrap;
}

.at-hero-clock { text-align: right; }
.at-clock-time { font-size: 26px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: .5px; }
.at-clock-date { font-size: 11px; opacity: .7; }

.at-periode-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2); border-radius: 20px;
    font-size: 12px; backdrop-filter: blur(4px);
}

.at-refresh-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.3); border-radius: 9px;
    color: #fff; font-size: 13px; font-weight: 600;
    cursor: pointer; transition: background .2s;
    backdrop-filter: blur(4px);
}
.at-refresh-btn:hover:not(:disabled) { background: rgba(255,255,255,.25); }
.at-refresh-btn:disabled { opacity: .5; cursor: not-allowed; }

/* ── Section Label ────────────────────────────────────────── */
.at-section-label {
    font-size: 12px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .8px; color: #878a99;
    display: flex; align-items: center; gap: 6px;
    margin-bottom: -8px;
}

/* ── KPI 6-col Grid ───────────────────────────────────────── */
.at-kpi6 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}

.at-kpi-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    padding: 18px 20px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px;
    border-left: 4px solid var(--accent, #405189);
    transition: box-shadow .2s, transform .2s;
}
.at-kpi-card:hover {
    box-shadow: 0 6px 20px rgba(64,81,137,.1);
    transform: translateY(-2px);
}
.at-kpi-left { display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0; }

.at-kpi-icon {
    width: 46px; height: 46px; border-radius: 11px;
    background: color-mix(in srgb, var(--accent, #405189) 12%, transparent);
    color: var(--accent, #405189);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; flex-shrink: 0;
}
.at-kpi-body { flex: 1; min-width: 0; }
.at-kpi-label { font-size: 11px; color: #878a99; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.at-kpi-value { font-size: 22px; font-weight: 800; color: var(--accent, #405189); line-height: 1.2; }
.at-kpi-sub   { font-size: 11px; color: #adb5bd; margin-top: 2px; }

.at-kpi-trend { font-size: 20px; flex-shrink: 0; }
.at-up { color: #0ab39c; }
.at-dn { color: #f06548; }

/* ── Card ─────────────────────────────────────────────────── */
.at-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    overflow: hidden;
}
.at-card-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 18px 22px 0; gap: 10px;
}
.at-card-title { font-size: 14px; font-weight: 700; color: #212529; }
.at-card-sub   { font-size: 11px; color: #878a99; margin-top: 3px; }
.at-card-body  { padding: 14px 22px 18px; }
.at-card-body.at-flex-center { display: flex; justify-content: center; align-items: center; }

/* ── Legend Pills ─────────────────────────────────────────── */
.at-legend-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 2px; }
.at-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600;
    background: color-mix(in srgb, var(--c, #405189) 12%, transparent);
    color: var(--c, #405189);
}
.at-pill::before {
    content: ''; width: 7px; height: 7px; border-radius: 50%;
    background: var(--c, #405189); display: inline-block;
}

/* ── Badge ────────────────────────────────────────────────── */
.at-badge-ok {
    padding: 4px 10px; background: rgba(240,101,72,.1);
    color: #f06548; border-radius: 6px;
    font-size: 11px; font-weight: 600; white-space: nowrap;
}

/* ── Grid Layouts ─────────────────────────────────────────── */
.at-row-6-4 {
    display: grid;
    grid-template-columns: 6fr 4fr;
    gap: 18px;
}
.at-row-half {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

/* ── Stat 4 ───────────────────────────────────────────────── */
.at-stat4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}
.at-stat-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 14px;
    padding: 24px 20px;
    display: flex; flex-direction: column; align-items: center;
    gap: 8px; text-align: center;
    transition: box-shadow .2s, transform .2s;
    border-top: 4px solid var(--c, #405189);
}
.at-stat-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,.07);
    transform: translateY(-2px);
}
.at-stat-icon { font-size: 28px; color: var(--c, #405189); }
.at-stat-val  { font-size: 26px; font-weight: 800; color: #212529; }
.at-stat-lbl  { font-size: 12px; color: #878a99; }

/* ── Validasi Summary ─────────────────────────────────────── */
.at-validasi-summary {
    display: flex; align-items: center;
    flex-wrap: wrap; gap: 0;
    background: #f8f9fc; border-radius: 12px;
    overflow: hidden;
}
.at-vs-item {
    flex: 1; min-width: 140px;
    display: flex; align-items: center; gap: 14px;
    padding: 20px 24px;
}
.at-vs-divider {
    width: 1px; background: #e9ecef; align-self: stretch;
    flex-shrink: 0;
}
.at-vs-icon {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.at-vs-val  { font-size: 20px; font-weight: 800; color: #212529; }
.at-vs-lbl  { font-size: 11px; color: #878a99; margin-top: 2px; }

.at-vs-total .at-vs-icon { background: rgba(64,81,137,.12); color: #405189; }
.at-vs-ok    .at-vs-icon { background: rgba(10,179,156,.12); color: #0ab39c; }
.at-vs-fg    .at-vs-icon { background: rgba(75,147,247,.12); color: #4b93f7; }
.at-vs-pct   .at-vs-icon { background: rgba(247,184,75,.12); color: #f7b84b; }
.at-vs-tat   .at-vs-icon { background: rgba(111,66,193,.12); color: #6f42c1; }
.at-vs-pass  .at-vs-icon { background: rgba(240,101,72,.12); color: #f06548; }

/* ── Skeleton ─────────────────────────────────────────────── */
@keyframes at-shimmer {
    0%   { background-position: -600px 0; }
    100% { background-position:  600px 0; }
}
.at-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 1200px 100%;
    animation: at-shimmer 1.5s infinite linear;
    border-radius: 6px;
}
.at-sk.at-kpi-card { pointer-events: none; }
.at-sk-icon  { width: 46px; height: 46px; border-radius: 11px; flex-shrink: 0; }
.at-sk-line  { height: 13px; border-radius: 4px; }
.at-sk-line.w55 { width: 55%; }
.at-sk-line.w35 { width: 35%; }
.at-sk-line.w45 { width: 45%; }
.mb2 { margin-bottom: 8px; }

.at-chart-sk { height: 300px; border-radius: 8px; }
.at-donut-sk { width: 200px; height: 200px; border-radius: 50%; }
.at-validasi-sk { display: flex; gap: 12px; height: 84px; }
.at-validasi-sk-item { flex: 1; border-radius: 10px; }

/* ── Spinner ──────────────────────────────────────────────── */
@keyframes at-spin { to { transform: rotate(360deg); } }
.at-spin { display: inline-block; animation: at-spin .7s linear infinite; }

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 1200px) {
    .at-kpi6   { grid-template-columns: repeat(3, 1fr); }
    .at-stat4  { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 992px) {
    .at-row-6-4  { grid-template-columns: 1fr; }
    .at-row-half { grid-template-columns: 1fr; }
    .at-kpi6     { grid-template-columns: repeat(2, 1fr); }
    .at-hero-title { font-size: 17px; }
}
@media (max-width: 700px) {
    .at-kpi6     { grid-template-columns: 1fr 1fr; }
    .at-stat4    { grid-template-columns: 1fr 1fr; }
    .at-hero     { padding: 20px 16px; }
    .at-clock-time { font-size: 20px; }
    .at-hero-title { font-size: 15px; }
    .at-hero-icon  { width: 44px; height: 44px; font-size: 22px; }
    .at-validasi-summary { flex-direction: column; }
    .at-vs-divider { width: 100%; height: 1px; align-self: auto; }
}
@media (max-width: 480px) {
    .at-kpi6  { grid-template-columns: 1fr; }
    .at-stat4 { grid-template-columns: 1fr 1fr; }
    .at-kpi-value { font-size: 18px; }
    .at-legend-pills { display: none; }
}
</style>
