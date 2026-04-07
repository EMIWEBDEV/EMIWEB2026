<template>
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="flex-grow-1">
                    <h4 class="fs-16 mb-1">Halo, {{ namaPengguna }} 👋</h4>
                    <p class="text-muted mb-0">
                        Berikut ringkasan aktivitas laboratorium Anda hari ini.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3 text-dark fw-bold">📅 Data Hari Ini</h4>
            </div>
            <div class="col-lg-9">
                <div class="row">
                    <div
                        class="col-md-4 col-lg-4 mb-4"
                        v-for="(
                            widget, index
                        ) in loading.fetchWidgetHariIniLoading
                            ? [1, 2, 3]
                            : todayWidgets"
                        :key="index"
                    >
                        <div class="widget-card">
                            <div
                                class="widget-icon"
                                v-if="!loading.fetchWidgetHariIniLoading"
                                :style="{ color: widget.color }"
                            >
                                <i :class="widget.icon"></i>
                            </div>
                            <div
                                class="widget-icon skeleton"
                                v-else
                                style="height: 40px; width: 40px"
                            ></div>

                            <div class="widget-body">
                                <h5 v-if="!loading.fetchWidgetHariIniLoading">
                                    {{ widget.title }}
                                </h5>
                                <div
                                    class="skeleton"
                                    v-else
                                    style="
                                        height: 20px;
                                        width: 70%;
                                        margin-bottom: 10px;
                                    "
                                ></div>

                                <p v-if="!loading.fetchWidgetHariIniLoading">
                                    {{ widget.subtitle }}
                                </p>
                                <div
                                    class="skeleton"
                                    v-else
                                    style="
                                        height: 16px;
                                        width: 60%;
                                        margin-bottom: 10px;
                                    "
                                ></div>

                                <h2
                                    v-if="!loading.fetchWidgetHariIniLoading"
                                    :style="{ color: widget.color }"
                                >
                                    {{ widget.value }}
                                </h2>
                                <div
                                    class="skeleton"
                                    v-else
                                    style="height: 30px; width: 50%"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <h5 class="mb-3 text-dark fw-bold">
                    ⏱️ Waktu Penyelesaian Rata-Rata
                </h5>

                <div class="col-12 mb-3">
                    <div
                        class="card modern-card tat-card text-center p-3 shadow-sm"
                    >
                        <div v-if="loading.fetchTATLoading">
                            <div
                                class="skeleton skeleton-tat-title mx-auto"
                            ></div>
                            <div
                                class="skeleton skeleton-tat-value mx-auto"
                            ></div>
                            <div
                                class="skeleton skeleton-tat-badge mx-auto"
                            ></div>
                        </div>

                        <div v-else>
                            <p class="text-muted mb-2 fs-14">
                                Waktu rata-rata (registrasi s/d selesai uji)
                            </p>
                            <h2 class="display-5 fw-bold text-primary mb-3">
                                {{ avgTurnaroundTime.hours }}
                                <span class="fs-5 text-muted"> jam</span>
                                {{ avgTurnaroundTime.minutes }}
                                <span class="fs-5 text-muted"> mnt</span>
                            </h2>

                            <div class="tat-badge-wrapper">
                                <span class="tat-badge">
                                    <strong>{{
                                        avgTurnaroundTime.period
                                    }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3 text-dark fw-bold">📊 Total Keseluruhan</h4>
            </div>
            <div
                class="col-md-4 col-lg-3 mb-4"
                v-for="(widget, index) in totalWidgets"
                :key="'total-' + index"
            >
                <div class="widget-card">
                    <div class="widget-icon" :style="{ color: widget.color }">
                        <i :class="widget.icon"></i>
                    </div>
                    <div class="widget-body">
                        <h5>{{ widget.title }}</h5>
                        <p>{{ widget.subtitle }}</p>
                        <h2 :style="{ color: widget.color }">
                            {{ widget.value }}
                        </h2>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3 text-dark fw-bold">📈 Grafik Analisa Sampel</h4>
            </div>

            <div class="col-md-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <apexchart
                            type="line"
                            :options="chartLineOptions"
                            :series="chartLineSeries"
                        />
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <apexchart
                            type="radar"
                            :options="chartBarOptions"
                            :series="chartBarSeries"
                        />
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <apexchart
                            type="pie"
                            height="300"
                            :options="chartPieOptions"
                            :series="chartPieSeries"
                        />
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-lg-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <apexchart
                            type="scatter"
                            height="300"
                            :options="chartScatterOptions"
                            :series="chartScatterSeries"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ApexChart from "vue3-apexcharts";
import axios from "axios";

export default {
    components: {
        apexchart: ApexChart,
    },
    props: {
        namaPengguna: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            colorPalette: [
                "#6366F1",
                "#F59E0B",
                "#EF4444",
                "#10B981",
                "#3B82F6",
                "#8B5CF6",
                "#EC4899",
                "#F97316",
                "#14B8A6",
                "#EAB308",
                "#6B7280",
                "#0EA5E9",
                "#A855F7",
            ],
            avgTurnaroundTime: { hours: 0, minutes: 0 },
            todayWidgets: [],
            totalWidgets: [],
            chartLineSeries: [],
            chartLineOptions: {},
            chartBarSeries: [],
            chartBarOptions: {},
            chartPieSeries: [],
            chartPieOptions: {},
            chartScatterSeries: [],
            chartScatterOptions: {
                chart: {
                    type: "scatter",
                    height: 450,
                    zoom: {
                        enabled: true,
                        type: "xy",
                    },
                },
                title: {
                    text: "Sebaran Hasil Analisa untuk Deteksi Anomali",
                    align: "left",
                },
                xaxis: {
                    title: {
                        text: "Urutan Pengujian (Indeks Data)",
                    },
                    tickAmount: 10,
                    labels: { show: false },
                },
                yaxis: {
                    title: {
                        text: "Nilai Hasil",
                    },
                    labels: {
                        formatter: (val) => val.toFixed(0),
                    },
                },
                annotations: {
                    yaxis: [
                        {
                            y: 0,
                            borderColor: "#FF0000",
                            label: {
                                borderColor: "#FF0000",
                                style: {
                                    color: "#fff",
                                    background: "#FF0000",
                                },
                                text: "Batas Nol",
                            },
                        },
                    ],
                },
                tooltip: {
                    custom: function ({
                        series,
                        seriesIndex,
                        dataPointIndex,
                        w,
                    }) {
                        const point =
                            w.config.series[seriesIndex].data[dataPointIndex];
                        return `
            <div style="padding:5px">
                <strong>${w.config.series[seriesIndex].name}</strong><br/>
                Titik ke-${point.x}<br/>
                Hasil: ${point.y.toFixed(4)}<br/>
                No Sampel: ${point.no_po_sampel}<br/>
                No Sub: ${point.no_fak_sub_po}
            </div>
        `;
                    },
                },

                legend: {
                    position: "top",
                },
            },
            loading: {
                fetchWidgetHariIniLoading: false,
                fetchWidgetAllTimeLoading: false,
                fetchTATLoading: false,
            },
        };
    },
    methods: {
        async fetchWidgetHariIni() {
            this.loading.fetchWidgetHariIniLoading = true;
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/current-hari-ini"
                );
                if (response.status === 200 && response.data?.result) {
                    this.todayWidgets = response.data.result;
                } else {
                    this.todayWidgets = [];
                }
            } catch (error) {
                this.todayWidgets = [];
            } finally {
                this.loading.fetchWidgetHariIniLoading = false;
            }
        },
        async fetchTAT() {
            this.loading.fetchTATLoading = true;
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/analyzer/kpi-tat"
                );
                if (response.status === 200) {
                    this.avgTurnaroundTime = response.data.result;
                }
            } catch (error) {
                console.error("Gagal fetch TAT:", error);
            } finally {
                this.loading.fetchTATLoading = false;
            }
        },
        async fetchWidgetAllTime() {
            this.loading.fetchWidgetAllTimeLoading = true;
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/current-all-time"
                );
                if (response.status === 200 && response.data?.result) {
                    this.totalWidgets = response.data.result;
                } else {
                    this.totalWidgets = [];
                }
            } catch (error) {
                this.totalWidgets = [];
            } finally {
                this.loading.fetchWidgetAllTimeLoading = false;
            }
        },
        async fetchChartUjiPerHari() {
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/grafik/jumlah-uji-perhari"
                );
                if (response.status === 200 && response.data?.result) {
                    this.chartLineSeries = response.data.result.chartLineSeries;
                    this.chartLineOptions =
                        response.data.result.chartLineOptions;
                }
            } catch (error) {
                console.error("Gagal fetch chart:", error);
            }
        },
        async fetchChartFrekeunsiUjiSampel() {
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/grafik/frekuensi-uji-sampel-berdasarkan-jenis-analisa"
                );
                if (response.status === 200 && response.data?.result) {
                    const result = response.data.result;

                    this.chartBarSeries = result.chartBarSeries;
                    this.chartBarOptions = result.chartBarOptions;
                }
            } catch (error) {
                console.error("Gagal fetch chart:", error);
            }
        },
        async fetchPiePersentaseStatusSampel() {
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/grafik/pie-status-uji-sampel"
                );
                if (response.status === 200 && response.data?.result) {
                    const result = response.data.result;

                    this.chartPieSeries = result.chartPieSeries;
                    this.chartPieOptions = result.chartPieOptions;
                }
            } catch (error) {
                console.error("Gagal fetch pie chart:", error);
            }
        },
        async fetchScatterSebaranHasilAnalisa() {
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/grafik/scatter-sebaran-hasil"
                );
                if (response.status === 200 && response.data?.result) {
                    this.chartScatterSeries =
                        response.data.result.chartScatterSeries;
                }
            } catch (error) {
                console.error("Gagal fetch scatter chart:", error);
            }
        },
    },
    mounted() {
        this.fetchTAT();
        this.fetchWidgetHariIni();
        this.fetchWidgetAllTime();
        this.fetchChartUjiPerHari();
        this.fetchChartFrekeunsiUjiSampel();
        this.fetchPiePersentaseStatusSampel();
        this.fetchScatterSebaranHasilAnalisa();
    },
};
</script>

<style scoped>
.tat-badge-wrapper {
    margin-top: 0.5rem;
}
.tat-badge {
    display: inline-block;
    padding: 0.4rem 0.85rem;
    font-size: 13px;
    font-weight: 500;
    color: #4b5563; /* Abu-abu tua */
    background-color: #f3f4f6; /* Abu-abu muda */
    border-radius: 50px; /* Bentuk pil */
}
/* General Styling */
.text-primary {
    color: #6366f1 !important;
}
.bg-light-success {
    background-color: rgba(22, 163, 74, 0.1);
}
.text-success {
    color: #16a34a !important;
}

/* Skeleton Loader */
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: 8px;
}
@keyframes shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Modern Card Style */
.modern-card {
    background: #ffffff;
    border: none;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(27, 46, 94, 0.05);
    height: 100%;
    transition: all 0.3s ease;
}
.modern-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(27, 46, 94, 0.08);
}

/* Small Widget Card */
.widget-card-sm {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(27, 46, 94, 0.04);
    padding: 1rem;
    height: 100%;
}
.widget-body-sm {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.widget-icon-sm {
    font-size: 20px;
    height: 48px;
    width: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.widget-title-sm {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 4px;
}
.widget-value-sm {
    font-size: 22px;
    font-weight: 700;
    color: #111827;
}

/* Activity List */
.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.list-group-item p {
    line-height: 1.4;
}
</style>

<style scoped>
.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 400% 100%;
    animation: shimmer 1.2s infinite;
    border-radius: 8px;
}

@keyframes shimmer {
    0% {
        background-position: -400px 0;
    }
    100% {
        background-position: 400px 0;
    }
}

.widget-card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    padding: 24px;
    height: 100%;
    transition: 0.3s ease-in-out;
}
.widget-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
}
.widget-icon {
    font-size: 36px;
    margin-bottom: 16px;
}
.widget-body h5 {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 4px;
}
.widget-body p {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 12px;
}
.widget-body h2 {
    font-size: 28px;
    font-weight: 700;
}
</style>
