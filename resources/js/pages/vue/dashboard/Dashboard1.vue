<template>
    <div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="flex-grow-1">
                        <h4 class="fs-18 mb-1">
                            Selamat Datang Kembali, {{ namaPengguna }}! 👋
                        </h4>
                        <p class="text-muted mb-0">
                            Dashboard Analitik untuk monitoring performa
                            laboratorium.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Bagian kiri -->
            <div class="col-lg-8">
                <!-- Ringkasan Hari Ini -->
                <div class="row">
                    <div class="col-12">
                        <h5 class="mb-3 text-dark fw-bold">
                            📅 Ringkasan Hari Ini
                        </h5>
                    </div>
                    <div
                        class="col-md-6 col-lg-3 mb-4"
                        v-for="(
                            widget, index
                        ) in loading.fetchWidgetHariIniLoading
                            ? [1, 2, 3, 4]
                            : todayWidgets"
                        :key="index"
                    >
                        <div
                            v-if="loading.fetchWidgetHariIniLoading"
                            class="widget-card-sm skeleton"
                            style="height: 120px"
                        ></div>
                        <div v-else class="widget-card-sm">
                            <div class="widget-body-sm">
                                <div
                                    class="widget-icon-sm"
                                    :style="{
                                        backgroundColor: widget.color + '20',
                                        color: widget.color,
                                    }"
                                >
                                    <i :class="widget.icon"></i>
                                </div>
                                <div class="widget-content-sm">
                                    <p class="widget-title-sm">
                                        {{ widget.title }}
                                    </p>
                                    <h4 class="widget-value-sm">
                                        {{ widget.value }}
                                    </h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="col-12">
                        <h5 class="mb-3 text-dark fw-bold">
                            📈 Tren Pengujian 7 Hari Terakhir
                        </h5>
                        <div class="card modern-card">
                            <div class="card-body">
                                <apexchart
                                    type="area"
                                    height="350"
                                    :options="chartLineOptions"
                                    :series="chartLineSeries"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="col-12">
                        <h5 class="mb-3 text-dark fw-bold">📈 Trend Analisa</h5>
                        <div class="card modern-card">
                            <div class="card-body">
                                <apexchart
                                    type="radar"
                                    :options="chartBarOptions"
                                    :series="chartBarSeries"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bagian kanan -->
            <div class="col-lg-4">
                <div class="row">
                    <div class="col-12">
                        <h5 class="mb-3 text-dark fw-bold">
                            ⏱️ Key Performance Indicator (Total Keseluruhan)
                        </h5>
                    </div>
                    <div class="col-12 mb-4">
                        <div class="card modern-card text-center p-3">
                            <div
                                v-if="loading.fetchTATLoading"
                                class="skeleton mx-auto"
                                style="
                                    height: 80px;
                                    width: 80%;
                                    border-radius: 12px;
                                "
                            ></div>
                            <div v-else>
                                <p class="text-muted mb-2 fs-14">
                                    Waktu Respon Rata-Rata
                                </p>
                                <h2 class="display-5 fw-bold text-primary mb-1">
                                    {{ avgTurnaroundTime.hours }}
                                    <span class="fs-5 text-muted"> jam</span>
                                    {{ avgTurnaroundTime.minutes }}
                                    <span class="fs-5 text-muted"> mnt</span>
                                </h2>
                                <p class="text-muted small">
                                    (Dari registrasi hingga selesai uji)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Uji -->
                <div class="row">
                    <div class="col-12">
                        <h5 class="mb-3 text-dark fw-bold">
                            📊 Status Penyelesaian Uji (Total)
                        </h5>
                    </div>
                    <div class="col-12 mb-4">
                        <div class="card modern-card">
                            <div class="card-body">
                                <apexchart
                                    type="donut"
                                    height="250"
                                    :options="chartDonutOptions"
                                    :series="chartDonutSeries"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aktivitas Terbaru -->
                <div class="row">
                    <div class="col-12">
                        <h5 class="mb-3 text-dark fw-bold">
                            ⚡ Aktivitas Terbaru
                        </h5>
                    </div>
                    <div class="col-12 mb-4">
                        <div class="card modern-card">
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <!-- Skeleton loading -->
                                    <template
                                        v-if="loading.fetchAktivitasLoading"
                                    >
                                        <li
                                            v-for="n in 5"
                                            :key="n"
                                            class="list-group-item d-flex align-items-center border-0 px-0"
                                        >
                                            <div
                                                class="skeleton"
                                                style="
                                                    width: 40px;
                                                    height: 40px;
                                                    border-radius: 50%;
                                                "
                                            ></div>
                                            <div class="ms-3" style="flex: 1">
                                                <div
                                                    class="skeleton mb-2"
                                                    style="
                                                        height: 16px;
                                                        width: 80%;
                                                    "
                                                ></div>
                                                <div
                                                    class="skeleton"
                                                    style="
                                                        height: 12px;
                                                        width: 50%;
                                                    "
                                                ></div>
                                            </div>
                                        </li>
                                    </template>

                                    <!-- Data aktivitas -->
                                    <template v-else>
                                        <li
                                            v-for="activity in recentActivities"
                                            :key="activity.id"
                                            class="list-group-item d-flex align-items-center border-0 px-0"
                                        >
                                            <div
                                                class="activity-icon bg-light-success text-success"
                                            >
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <div class="ms-3">
                                                <p class="fw-bold mb-0 fs-14">
                                                    {{ activity.no_sampel }}
                                                </p>
                                                <p
                                                    class="text-muted mb-0 fs-12"
                                                >
                                                    {{ activity.jenis_analisa }}
                                                    oleh {{ activity.user }}
                                                </p>
                                            </div>
                                            <small class="ms-auto text-muted">{{
                                                activity.waktu
                                            }}</small>
                                        </li>

                                        <!-- Fallback kalau kosong -->
                                        <li
                                            v-if="recentActivities.length === 0"
                                            class="list-group-item text-center text-muted"
                                        >
                                            Tidak ada aktivitas terbaru.
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import ApexChart from "vue3-apexcharts";
import axios from "axios";
import moment from "moment"; // Pastikan sudah install: npm install moment

export default {
    components: {
        apexchart: ApexChart,
    },
    props: {
        namaPengguna: {
            type: String,
            default: "Analis",
        },
    },
    data() {
        return {
            todayWidgets: [],
            // Data baru
            avgTurnaroundTime: { hours: 0, minutes: 0 },
            recentActivities: [],
            analystPerformanceSeries: [],
            analystPerformanceOptions: {},
            // Data Grafik Lama (disesuaikan)
            chartLineSeries: [],
            chartLineOptions: {},
            chartDonutSeries: [],
            chartDonutOptions: {},
            chartBarSeries: [],
            chartBarOptions: {},
            // Loading states
            loading: {
                fetchWidgetHariIniLoading: false,
                fetchTATLoading: false,
                fetchAktivitasLoading: false,
                fetchKinerjaLoading: false,
            },
        };
    },
    methods: {
        async fetchWidgetHariIni() {
            this.loading.fetchWidgetHariIniLoading = true;
            try {
                // Endpoint API Anda untuk widget hari ini
                const response = await axios.get(
                    "/api/v1/dashboard/current-hari-ini"
                );
                if (response.status === 200) {
                    // Ambil 4 data pertama saja
                    this.todayWidgets = response.data.result.slice(0, 4);
                }
            } catch (error) {
                console.error("Gagal fetch widget hari ini:", error);
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
        async fetchAktivitasTerbaru() {
            this.loading.fetchAktivitasLoading = true;
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/analyzer/aktivitas-terbaru"
                );
                if (response.status === 200) {
                    this.recentActivities = response.data.result.map(
                        (activity) => {
                            // Format waktu menggunakan moment.js
                            activity.waktu = moment(activity.tanggal).fromNow();
                            return activity;
                        }
                    );
                }
            } catch (error) {
                console.error("Gagal fetch aktivitas terbaru:", error);
            } finally {
                this.loading.fetchAktivitasLoading = false;
            }
        },
        // async fetchKinerjaAnalis() {
        //     this.loading.fetchKinerjaLoading = true;
        //     try {
        //         const response = await axios.get(
        //             "/api/v1/dashboard/analyzer/kinerja-analis"
        //         );
        //         if (response.status === 200) {
        //             this.analystPerformanceSeries = response.data.result.series;
        //             this.analystPerformanceOptions = {
        //                 ...response.data.result.options,
        //                 chart: {
        //                     type: "bar",
        //                     height: 350,
        //                     toolbar: { show: false },
        //                 },
        //                 plotOptions: {
        //                     bar: { borderRadius: 4, horizontal: true },
        //                 },
        //                 dataLabels: {
        //                     enabled: true,
        //                     textAnchor: "start",
        //                     style: { colors: ["#fff"] },
        //                     formatter: (val) => val,
        //                     offsetX: 0,
        //                 },
        //                 tooltip: { theme: "dark" },
        //                 colors: ["#6366F1"],
        //             };
        //         }
        //     } catch (error) {
        //         console.error("Gagal fetch kinerja analis:", error);
        //     } finally {
        //         this.loading.fetchKinerjaLoading = false;
        //     }
        // },
        async fetchChartUjiPerHari() {
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/grafik/jumlah-uji-perhari"
                );
                if (response.status === 200) {
                    this.chartLineSeries = response.data.result.chartLineSeries;
                    this.chartLineOptions = {
                        ...response.data.result.chartLineOptions,
                        chart: {
                            type: "area",
                            height: 350,
                            toolbar: { show: false },
                            zoom: { enabled: false },
                        },
                        dataLabels: { enabled: false },
                        stroke: { curve: "smooth", width: 2 },
                        colors: ["#3B82F6"],
                        fill: {
                            type: "gradient",
                            gradient: { opacityFrom: 0.6, opacityTo: 0.05 },
                        },
                        markers: {
                            size: 4,
                            strokeWidth: 0,
                            hover: { size: 7 },
                        },
                        tooltip: {
                            theme: "light",
                            x: { format: "dd MMM yyyy" },
                        },
                    };
                }
            } catch (error) {
                console.error("Gagal fetch chart tren:", error);
            }
        },
        async fetchDonutStatusSampel() {
            try {
                const response = await axios.get(
                    "/api/v1/dashboard/grafik/pie-status-uji-sampel"
                );
                if (response.status === 200) {
                    this.chartDonutSeries = response.data.result.chartPieSeries;
                    this.chartDonutOptions = {
                        ...response.data.result.chartPieOptions,
                        chart: { type: "donut", height: 250 },
                        dataLabels: {
                            enabled: true,
                            formatter: (val) => `${val.toFixed(1)}%`,
                        },
                        legend: {
                            position: "bottom",
                            horizontalAlign: "center",
                            itemMargin: { horizontal: 10 },
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    labels: {
                                        show: true,
                                        total: {
                                            show: true,
                                            label: "Total Uji",
                                        },
                                    },
                                },
                            },
                        },
                    };
                }
            } catch (error) {
                console.error("Gagal fetch donut chart:", error);
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
    },
    mounted() {
        this.fetchWidgetHariIni();
        this.fetchTAT();
        this.fetchAktivitasTerbaru();
        this.fetchChartUjiPerHari();
        this.fetchDonutStatusSampel();
        this.fetchChartFrekeunsiUjiSampel();
    },
};
</script>

<style scoped>
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
