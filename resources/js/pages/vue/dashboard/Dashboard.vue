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
            <div
                class="col-md-4 col-lg-4 mb-4"
                v-for="(widget, index) in loading.fetchWidgetHariIniLoading
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
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3 text-dark fw-bold">📊 Total Keseluruhan</h4>
            </div>

            <!-- Skeleton -->
            <template v-if="loading.fetchWidgetAllTimeLoading">
                <div
                    class="col-md-4 col-lg-3 mb-4"
                    v-for="n in 4"
                    :key="'skeleton-' + n"
                >
                    <div class="widget-card skeleton-card">
                        <div class="skeleton-icon shimmer"></div>
                        <div class="widget-body">
                            <div class="skeleton-title shimmer"></div>
                            <div class="skeleton-subtitle shimmer"></div>
                            <div class="skeleton-value shimmer"></div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Real Data -->
            <template v-else>
                <div
                    class="col-md-4 col-lg-3 mb-4"
                    v-for="(widget, index) in totalWidgets"
                    :key="'total-' + index"
                >
                    <div class="widget-card">
                        <div
                            class="widget-icon"
                            :style="{ color: widget.color }"
                        >
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
            </template>
        </div>

        <!-- <div class="row mb-4">
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
        </div> -->
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
                    x: {
                        formatter: function (seriesName, opts) {
                            const seriesDisplayName =
                                opts.w.config.series[opts.seriesIndex].name;
                            return `Titik ke-${
                                opts.dataPointIndex + 1
                            } (${seriesDisplayName})`;
                        },
                    },
                    y: {
                        formatter: function (val) {
                            return `Hasil: ${val.toFixed(4)}`;
                        },
                    },
                },
                legend: {
                    position: "top",
                },
            },
            loading: {
                fetchWidgetHariIniLoading: false,
                fetchWidgetAllTimeLoading: false,
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
        this.fetchWidgetHariIni();
        this.fetchWidgetAllTime();
        // this.fetchChartUjiPerHari();
        // this.fetchChartFrekeunsiUjiSampel();
        // this.fetchPiePersentaseStatusSampel();
        // this.fetchScatterSebaranHasilAnalisa();
    },
};
</script>

<style scoped>
/* Skeleton Base */
.skeleton-card {
    position: relative;
    overflow: hidden;
}

.skeleton-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: #e0e0e0;
    margin-bottom: 1rem;
}

.skeleton-title {
    width: 70%;
    height: 16px;
    background: #e0e0e0;
    border-radius: 6px;
    margin-bottom: 10px;
}

.skeleton-subtitle {
    width: 50%;
    height: 14px;
    background: #e0e0e0;
    border-radius: 6px;
    margin-bottom: 15px;
}

.skeleton-value {
    width: 40%;
    height: 28px;
    background: #e0e0e0;
    border-radius: 8px;
}

/* Shimmer Animation */
.shimmer {
    position: relative;
    overflow: hidden;
}

.shimmer::after {
    content: "";
    position: absolute;
    top: 0;
    left: -150%;
    height: 100%;
    width: 150%;
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.6) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    animation: shimmerAs 1.5s infinite;
}

@keyframes shimmerAs {
    100% {
        left: 100%;
    }
}

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
