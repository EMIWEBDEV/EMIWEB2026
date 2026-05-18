<template>
    <div class="dqa-page">
        <!-- Header -->
        <div class="dqa-header">
            <div class="dqa-header-left">
                <div class="dqa-header-icon">
                    <i class="ri-qr-code-line"></i>
                </div>
                <div>
                    <h4 class="dqa-header-title">
                        Dashboard QA Registrasi Sampel
                    </h4>
                    <p class="dqa-header-sub">
                        Monitor registrasi dan status sampel laboratorium secara
                        real-time
                    </p>
                </div>
            </div>
            <div class="dqa-header-right">
                <div class="dqa-date-badge">
                    <i class="ri-calendar-line me-1"></i>{{ currentDate }}
                </div>
                <button
                    class="dqa-refresh-btn"
                    @click="refreshAll"
                    :disabled="anyLoading"
                >
                    <i
                        :class="
                            anyLoading
                                ? 'ri-loader-4-line dqa-spin'
                                : 'ri-refresh-line'
                        "
                    ></i>
                    Segarkan
                </button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="dqa-kpi-grid">
            <template v-if="loading.kpi">
                <div v-for="n in 6" :key="n" class="dqa-kpi-card">
                    <div class="dqa-sk-icon dqa-shimmer"></div>
                    <div style="flex: 1">
                        <div class="dqa-sk-line w60 mb1 dqa-shimmer"></div>
                        <div class="dqa-sk-line w40 mb1 dqa-shimmer"></div>
                        <div class="dqa-sk-line w30 dqa-shimmer"></div>
                    </div>
                </div>
            </template>
            <template v-else>
                <div
                    v-for="(item, i) in kpiCards"
                    :key="i"
                    class="dqa-kpi-card"
                >
                    <div
                        class="dqa-kpi-icon"
                        :style="{ background: item.bg, color: item.color }"
                    >
                        <i :class="item.icon"></i>
                    </div>
                    <div class="dqa-kpi-body">
                        <div class="dqa-kpi-label">{{ item.title }}</div>
                        <div
                            class="dqa-kpi-value"
                            :style="{ color: item.color }"
                        >
                            {{ item.value.toLocaleString("id-ID") }}
                        </div>
                        <div class="dqa-kpi-sub">{{ item.sub }}</div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Charts Row 1: Trend + Donut -->
        <div class="dqa-row dqa-row-7-3">
            <div class="dqa-chart-card">
                <div class="dqa-chart-head">
                    <div>
                        <div class="dqa-chart-title">
                            Tren Registrasi Sampel
                        </div>
                        <div class="dqa-chart-sub">
                            Pergerakan sampel dalam periode terpilih
                        </div>
                    </div>
                    <div class="dqa-days-filter">
                        <button
                            v-for="d in [7, 14, 30]"
                            :key="d"
                            :class="[
                                'dqa-day-btn',
                                trendDays === d && 'active',
                            ]"
                            @click="changeTrendDays(d)"
                        >
                            {{ d }}H
                        </button>
                    </div>
                </div>
                <div
                    v-if="loading.trend"
                    class="dqa-chart-placeholder dqa-shimmer"
                ></div>
                <apexchart
                    v-else
                    type="area"
                    height="260"
                    :options="trendChartOptions"
                    :series="trendChart.series"
                />
            </div>
            <div class="dqa-chart-card">
                <div class="dqa-chart-head">
                    <div>
                        <div class="dqa-chart-title">Status Keseluruhan</div>
                        <div class="dqa-chart-sub">Distribusi semua sampel</div>
                    </div>
                </div>
                <div
                    v-if="loading.donut"
                    class="dqa-chart-placeholder dqa-shimmer"
                ></div>
                <apexchart
                    v-else
                    type="donut"
                    height="260"
                    :options="donutOptions"
                    :series="donutSeries"
                />
            </div>
        </div>

        <!-- Charts Row 2: Per Mesin + Per User -->
        <div class="dqa-row">
            <div class="dqa-chart-card col-lg-12">
                <div class="dqa-chart-head">
                    <div>
                        <div class="dqa-chart-title">Distribusi Per Mesin</div>
                        <div class="dqa-chart-sub">
                            Total sampel per mesin analyzer
                        </div>
                    </div>
                </div>
                <div
                    v-if="loading.distribusiMesin"
                    class="dqa-chart-placeholder dqa-shimmer"
                ></div>
                <apexchart
                    v-else
                    type="bar"
                    height="260"
                    :options="mesinBarOptions"
                    :series="mesinBarSeries"
                />
            </div>
        </div>

        <!-- Recent Activity Table -->
        <div class="dqa-table-card">
            <div class="dqa-chart-head">
                <div>
                    <div class="dqa-chart-title">
                        Aktivitas Registrasi Terbaru
                    </div>
                    <div class="dqa-chart-sub">
                        10 sampel yang baru saja didaftarkan
                    </div>
                </div>
                <span class="dqa-count-badge"
                    >{{ aktivitasList.length }} data</span
                >
            </div>

            <!-- Table Skeleton -->
            <template v-if="loading.aktivitas">
                <div v-for="n in 5" :key="n" class="dqa-sk-row">
                    <div class="dqa-sk-line w20 dqa-shimmer"></div>
                    <div class="dqa-sk-line w25 dqa-shimmer"></div>
                    <div class="dqa-sk-line w20 dqa-shimmer"></div>
                    <div class="dqa-sk-line w15 dqa-shimmer"></div>
                    <div class="dqa-sk-line w10 dqa-shimmer"></div>
                </div>
            </template>

            <!-- Table -->
            <div v-else class="dqa-table-wrap">
                <table class="dqa-table">
                    <thead>
                        <tr>
                            <th>No. Sampel</th>
                            <th>Kode Barang</th>
                            <th>No. PO</th>
                            <th>Mesin</th>
                            <th>Registrar</th>
                            <th>Tanggal &amp; Jam</th>
                            <th>Berat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in aktivitasList" :key="i">
                            <td>
                                <span class="dqa-sampel-badge">{{
                                    item.No_Sampel
                                }}</span>
                                <span
                                    v-if="item.Flag_Trial_Produksi === 'Y'"
                                    class="dqa-flag-trial ms-1"
                                    >Trial</span
                                >
                                <span
                                    v-if="item.Flag_Khusus === 'Y'"
                                    class="dqa-flag-khusus ms-1"
                                    >Khusus</span
                                >
                            </td>
                            <td>
                                <code class="dqa-kode-text">{{
                                    item.Kode_Barang || "-"
                                }}</code>
                            </td>
                            <td class="dqa-muted-text">
                                {{ item.No_Po || "-" }}
                            </td>
                            <td>{{ item.Nama_Mesin || "-" }}</td>
                            <td>
                                <span class="dqa-user-chip">{{
                                    item.Id_User
                                }}</span>
                            </td>
                            <td class="dqa-muted-text">
                                {{ item.Tanggal }} {{ item.Jam }}
                            </td>
                            <td class="dqa-muted-text">
                                {{ item.Berat_Sampel || "-" }}
                            </td>
                            <td>
                                <span
                                    :class="[
                                        'dqa-status-badge',
                                        item.Flag_Selesai === 'Y'
                                            ? 'done'
                                            : 'pending',
                                    ]"
                                >
                                    {{
                                        item.Flag_Selesai === "Y"
                                            ? "Selesai"
                                            : "Pending"
                                    }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!aktivitasList.length">
                            <td colspan="8" class="dqa-empty-row">
                                <i class="ri-inbox-line me-2"></i>Tidak ada data
                                aktivitas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import ApexChart from "vue3-apexcharts";
import axios from "axios";

export default {
    components: { apexchart: ApexChart },

    data() {
        return {
            currentDate: new Date().toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            }),
            trendDays: 7,
            kpiCards: [],
            trendChart: { categories: [], series: [] },
            statusRingkasan: { selesai: 0, pending: 0, trial: 0 },
            distribusiMesin: { categories: [], data: [] },
            distribusiUser: { categories: [], data: [] },
            aktivitasList: [],
            loading: {
                kpi: false,
                trend: false,
                donut: false,
                distribusiMesin: false,
                distribusiUser: false,
                aktivitas: false,
            },
        };
    },

    computed: {
        anyLoading() {
            return Object.values(this.loading).some(Boolean);
        },
        trendChartOptions() {
            return {
                chart: {
                    type: "area",
                    height: 260,
                    toolbar: { show: false },
                    zoom: { enabled: false },
                },
                colors: ["#405189", "#0ab39c", "#f7b84b"],
                fill: {
                    type: "gradient",
                    gradient: {
                        shadeIntensity: 1,
                        inverseColors: false,
                        opacityFrom: 0.45,
                        opacityTo: 0.05,
                        stops: [20, 100],
                    },
                },
                stroke: { curve: "smooth", width: 2 },
                xaxis: {
                    categories: this.trendChart.categories,
                    labels: { style: { fontSize: "11px" } },
                },
                yaxis: { labels: { style: { fontSize: "11px" } } },
                dataLabels: { enabled: false },
                grid: { borderColor: "#f2f2f2", strokeDashArray: 4 },
                legend: { position: "top", fontSize: "12px" },
                tooltip: { shared: true, intersect: false },
            };
        },
        donutSeries() {
            const { selesai, pending, trial } = this.statusRingkasan;
            return [selesai, pending, trial];
        },
        donutOptions() {
            return {
                chart: { type: "donut", height: 260 },
                colors: ["#0ab39c", "#f7b84b", "#4b93f7"],
                labels: ["Selesai", "Pending", "Trial Produksi"],
                legend: { position: "bottom", fontSize: "12px" },
                dataLabels: {
                    enabled: true,
                    formatter: (val) => val.toFixed(1) + "%",
                },
                plotOptions: { pie: { donut: { size: "62%" } } },
                stroke: { width: 2 },
            };
        },
        mesinBarSeries() {
            return [{ name: "Total Sampel", data: this.distribusiMesin.data }];
        },
        mesinBarOptions() {
            return {
                chart: { type: "bar", height: 260, toolbar: { show: false } },
                colors: ["#405189"],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        dataLabels: { position: "top" },
                    },
                },
                dataLabels: {
                    enabled: true,
                    offsetX: 6,
                    style: { fontSize: "11px", colors: ["#495057"] },
                },
                xaxis: {
                    categories: this.distribusiMesin.categories,
                    labels: { style: { fontSize: "11px" } },
                },
                yaxis: { labels: { style: { fontSize: "11px" } } },
                grid: {
                    borderColor: "#f2f2f2",
                    xaxis: { lines: { show: true } },
                    yaxis: { lines: { show: false } },
                },
            };
        },
        userBarSeries() {
            return [{ name: "Total Sampel", data: this.distribusiUser.data }];
        },
        userBarOptions() {
            return {
                chart: { type: "bar", height: 260, toolbar: { show: false } },
                colors: ["#0ab39c"],
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        dataLabels: { position: "top" },
                    },
                },
                dataLabels: {
                    enabled: true,
                    offsetX: 6,
                    style: { fontSize: "11px", colors: ["#495057"] },
                },
                xaxis: {
                    categories: this.distribusiUser.categories,
                    labels: { style: { fontSize: "11px" } },
                },
                yaxis: { labels: { style: { fontSize: "11px" } } },
                grid: {
                    borderColor: "#f2f2f2",
                    xaxis: { lines: { show: true } },
                    yaxis: { lines: { show: false } },
                },
            };
        },
    },

    methods: {
        async fetchKpi() {
            this.loading.kpi = true;
            try {
                const res = await axios.get(
                    "/api/v1/dashboard-qa/kpi-hari-ini"
                );
                this.kpiCards = res.data?.result ?? [];
            } catch {
                this.kpiCards = [];
            } finally {
                this.loading.kpi = false;
            }
        },
        async fetchTrend() {
            this.loading.trend = true;
            try {
                const res = await axios.get(
                    "/api/v1/dashboard-qa/grafik/tren-sampel",
                    {
                        params: { days: this.trendDays },
                    }
                );
                this.trendChart = res.data?.result ?? {
                    categories: [],
                    series: [],
                };
            } catch {
                this.trendChart = { categories: [], series: [] };
            } finally {
                this.loading.trend = false;
            }
        },
        async fetchStatusRingkasan() {
            this.loading.donut = true;
            try {
                const res = await axios.get(
                    "/api/v1/dashboard-qa/grafik/status-ringkasan"
                );
                this.statusRingkasan = res.data?.result ?? {
                    selesai: 0,
                    pending: 0,
                    trial: 0,
                };
            } catch {
                this.statusRingkasan = { selesai: 0, pending: 0, trial: 0 };
            } finally {
                this.loading.donut = false;
            }
        },
        async fetchDistribusiMesin() {
            this.loading.distribusiMesin = true;
            try {
                const res = await axios.get(
                    "/api/v1/dashboard-qa/grafik/distribusi-mesin"
                );
                this.distribusiMesin = res.data?.result ?? {
                    categories: [],
                    data: [],
                };
            } catch {
                this.distribusiMesin = { categories: [], data: [] };
            } finally {
                this.loading.distribusiMesin = false;
            }
        },
        async fetchDistribusiUser() {
            this.loading.distribusiUser = true;
            try {
                const res = await axios.get(
                    "/api/v1/dashboard-qa/grafik/distribusi-user"
                );
                this.distribusiUser = res.data?.result ?? {
                    categories: [],
                    data: [],
                };
            } catch {
                this.distribusiUser = { categories: [], data: [] };
            } finally {
                this.loading.distribusiUser = false;
            }
        },
        async fetchAktivitas() {
            this.loading.aktivitas = true;
            try {
                const res = await axios.get(
                    "/api/v1/dashboard-qa/aktivitas-terbaru"
                );
                this.aktivitasList = res.data?.result ?? [];
            } catch {
                this.aktivitasList = [];
            } finally {
                this.loading.aktivitas = false;
            }
        },
        async changeTrendDays(days) {
            this.trendDays = days;
            await this.fetchTrend();
        },
        async refreshAll() {
            await Promise.all([
                this.fetchKpi(),
                this.fetchTrend(),
                this.fetchStatusRingkasan(),
                this.fetchDistribusiMesin(),
                this.fetchDistribusiUser(),
                this.fetchAktivitas(),
            ]);
        },
    },

    mounted() {
        this.refreshAll();
    },
};
</script>

<style scoped>
/* ── Base ──────────────────────────────────────────────────── */
.dqa-page {
    font-family: "Inter", "Segoe UI", sans-serif;
    color: #343a40;
}

/* ── Header ────────────────────────────────────────────────── */
.dqa-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 20px 24px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 4px rgba(64, 81, 137, 0.08);
    margin-bottom: 20px;
}
.dqa-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.dqa-header-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: rgba(64, 81, 137, 0.1);
    color: #405189;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.dqa-header-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
    margin: 0 0 3px;
}
.dqa-header-sub {
    font-size: 12px;
    color: #878a99;
    margin: 0;
}
.dqa-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}
.dqa-date-badge {
    padding: 6px 14px;
    border-radius: 20px;
    background: rgba(64, 81, 137, 0.08);
    color: #405189;
    font-size: 12px;
    font-weight: 500;
}
.dqa-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 8px;
    background: #405189;
    color: #fff;
    border: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.dqa-refresh-btn:hover:not(:disabled) {
    background: #35457b;
}
.dqa-refresh-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}
.dqa-spin {
    display: inline-block;
    animation: dqa-spin 0.8s linear infinite;
}
@keyframes dqa-spin {
    to {
        transform: rotate(360deg);
    }
}

/* ── KPI Grid ──────────────────────────────────────────────── */
.dqa-kpi-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 992px) {
    .dqa-kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 576px) {
    .dqa-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.dqa-kpi-card {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s, transform 0.2s;
}
.dqa-kpi-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}
.dqa-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.dqa-kpi-label {
    font-size: 12px;
    color: #878a99;
    margin-bottom: 4px;
}
.dqa-kpi-value {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 2px;
}
.dqa-kpi-sub {
    font-size: 11px;
    color: #adb5bd;
}

/* ── Chart Rows ────────────────────────────────────────────── */
.dqa-row {
    display: grid;
    gap: 16px;
    margin-bottom: 20px;
}
.dqa-row-7-3 {
    grid-template-columns: 7fr 3fr;
}
.dqa-row-half {
    grid-template-columns: 1fr 1fr;
}
@media (max-width: 992px) {
    .dqa-row-7-3,
    .dqa-row-half {
        grid-template-columns: 1fr;
    }
}

.dqa-chart-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    padding: 20px;
}
.dqa-chart-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}
.dqa-chart-title {
    font-size: 14px;
    font-weight: 600;
    color: #212529;
    margin-bottom: 2px;
}
.dqa-chart-sub {
    font-size: 12px;
    color: #878a99;
}
.dqa-chart-placeholder {
    height: 260px;
    border-radius: 8px;
}

/* ── Days Filter ───────────────────────────────────────────── */
.dqa-days-filter {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}
.dqa-day-btn {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid #dee2e6;
    background: #fff;
    color: #6c757d;
    cursor: pointer;
    transition: all 0.15s;
}
.dqa-day-btn.active {
    background: #405189;
    color: #fff;
    border-color: #405189;
}
.dqa-day-btn:hover:not(.active) {
    background: #f8f9fa;
    border-color: #adb5bd;
}

/* ── Table ─────────────────────────────────────────────────── */
.dqa-table-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
    padding: 20px;
    margin-bottom: 20px;
}
.dqa-count-badge {
    padding: 4px 10px;
    border-radius: 20px;
    background: rgba(64, 81, 137, 0.08);
    color: #405189;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}
.dqa-table-wrap {
    overflow-x: auto;
    margin-top: 4px;
}
.dqa-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.dqa-table thead tr {
    border-bottom: 2px solid #e9ecef;
}
.dqa-table th {
    padding: 10px 12px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    white-space: nowrap;
}
.dqa-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f2f2f2;
    vertical-align: middle;
}
.dqa-table tbody tr:hover {
    background: #f8f9fc;
}
.dqa-muted-text {
    color: #6c757d;
}
.dqa-sampel-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(64, 81, 137, 0.1);
    color: #405189;
    font-size: 12px;
    font-weight: 600;
    font-family: monospace;
}
.dqa-kode-text {
    font-family: monospace;
    font-size: 12px;
    color: #6c757d;
}
.dqa-user-chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    background: rgba(10, 179, 156, 0.1);
    color: #0ab39c;
    font-size: 11px;
    font-weight: 600;
}
.dqa-flag-trial {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(75, 147, 247, 0.1);
    color: #4b93f7;
    font-size: 10px;
    font-weight: 600;
}
.dqa-flag-khusus {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(111, 66, 193, 0.1);
    color: #6f42c1;
    font-size: 10px;
    font-weight: 600;
}
.dqa-status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.dqa-status-badge.done {
    background: rgba(10, 179, 156, 0.12);
    color: #0ab39c;
}
.dqa-status-badge.pending {
    background: rgba(247, 184, 75, 0.12);
    color: #d08f00;
}
.dqa-empty-row {
    text-align: center;
    color: #adb5bd;
    padding: 32px;
}

/* ── Skeleton / Shimmer ────────────────────────────────────── */
@keyframes dqa-shimmer {
    0% {
        background-position: -600px 0;
    }
    100% {
        background-position: 600px 0;
    }
}
.dqa-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
    background-size: 1200px 100%;
    animation: dqa-shimmer 1.5s infinite linear;
    border-radius: 6px;
}
.dqa-sk-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    flex-shrink: 0;
}
.dqa-sk-line {
    height: 14px;
    border-radius: 4px;
}
.w60 {
    width: 60%;
}
.w40 {
    width: 40%;
}
.w30 {
    width: 30%;
}
.w25 {
    width: 25%;
}
.w20 {
    width: 20%;
}
.w15 {
    width: 15%;
}
.w10 {
    width: 10%;
}
.mb1 {
    margin-bottom: 6px;
}
.dqa-sk-row {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #f2f2f2;
}
</style>
