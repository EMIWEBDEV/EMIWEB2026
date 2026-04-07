<template>
    <div class="chart-container">
        <div class="mb-3">
            <apexchart
                type="line"
                height="350"
                :options="lineChartOptions"
                :series="chartSeries"
            />
        </div>

        <div class="row">
            <div class="col-lg-6">
                <div class="mb-3">
                    <apexchart
                        type="bar"
                        height="350"
                        :options="barChartOptions"
                        :series="barSeries"
                    />
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mb-3">
                    <apexchart
                        type="pie"
                        height="350"
                        :options="pieChartOptions"
                        :series="pieSeries"
                    />
                </div>
            </div>
        </div>
        <!-- <div class="mb-3">
            <apexchart
                type="heatmap"
                height="400"
                :options="heatmapChartOptions"
                :series="heatmapSeries"
            />
        </div> -->
        <!-- <div class="mb-3">
            <apexchart
                type="rangeBar"
                height="400"
                :options="rangeBarOptions"
                :series="rangeBarSeries"
            />
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
    data() {
        return {
            rawData: [],
            rawDataBar: [],
            rawDataPie: [],
            rawDataHeatMap: [
                {
                    tanggal: "2025-07-10",
                    jam: "13:49:05",
                    berat: 1.7,
                    id_mesin: "1",
                },
                {
                    tanggal: "2025-07-10",
                    jam: "13:50:59",
                    berat: 1.7,
                    id_mesin: "3",
                },
                {
                    tanggal: "2025-07-10",
                    jam: "13:51:19",
                    berat: 1.7,
                    id_mesin: "1",
                },
                {
                    tanggal: "2025-07-11",
                    jam: "11:10:49",
                    berat: 56.81,
                    id_mesin: "2",
                },
            ],
            rawDataRange: [
                {
                    id: 213,
                    id_mesin: "MC01",
                    start: "2025-07-10T13:49:05",
                    end: "2025-07-10T14:04:05",
                },
                {
                    id: 214,
                    id_mesin: "MC03",
                    start: "2025-07-10T13:50:59",
                    end: "2025-07-10T14:05:59",
                },
                {
                    id: 215,
                    id_mesin: "MC01",
                    start: "2025-07-10T13:51:19",
                    end: "2025-07-10T14:06:19",
                },
                {
                    id: 216,
                    id_mesin: "MC02",
                    start: "2025-07-11T11:10:49",
                    end: "2025-07-11T11:25:49",
                },
            ],
        };
    },
    computed: {
        rangeBarSeries() {
            const grouped = {};

            this.rawDataRange.forEach((item) => {
                if (!grouped[item.id_mesin]) grouped[item.id_mesin] = [];
                grouped[item.id_mesin].push({
                    x: `No Sampel ${item.id}`,
                    y: [
                        new Date(item.start).getTime(),
                        new Date(item.end).getTime(),
                    ],
                });
            });

            return Object.entries(grouped).map(([mesin, tasks]) => ({
                name: mesin,
                data: tasks,
            }));
        },
        rangeBarOptions() {
            return {
                chart: {
                    type: "rangeBar",
                    height: 400,
                    toolbar: { show: true },
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        barHeight: "50%",
                    },
                },
                xaxis: {
                    type: "datetime",
                    title: {
                        text: "Waktu Proses",
                    },
                },
                title: {
                    text: "Timeline Proses Uji per Mesin",
                    align: "center",
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val, opts) {
                        const start = new Date(val[0]);
                        const end = new Date(val[1]);
                        const dur = Math.round((val[1] - val[0]) / 60000);
                        return `${start.toLocaleTimeString()} - ${end.toLocaleTimeString()} (${dur} min)`;
                    },
                },
                tooltip: {
                    x: {
                        format: "yyyy-MM-dd HH:mm:ss",
                    },
                },
                legend: {
                    position: "top",
                },
            };
        },

        heatmapSeries() {
            const mesinMap = {};

            this.rawDataHeatMap.forEach((item) => {
                const idMesin = `MC${item.id_mesin}`; // Misalnya "MC1"
                const tanggal = item.tanggal;

                if (!mesinMap[idMesin]) {
                    mesinMap[idMesin] = {};
                }

                if (!mesinMap[idMesin][tanggal]) {
                    mesinMap[idMesin][tanggal] = 0;
                }

                mesinMap[idMesin][tanggal]++;
            });

            return Object.entries(mesinMap).map(([idMesin, tanggalMap]) => ({
                name: idMesin,
                data: Object.entries(tanggalMap).map(([tanggal, count]) => ({
                    x: tanggal,
                    y: count,
                })),
            }));
        },

        heatmapChartOptions() {
            return {
                chart: {
                    type: "heatmap",
                    toolbar: { show: true },
                },
                title: {
                    text: "Frekuensi Penggunaan Mesin per Hari (Heatmap)",
                    align: "center",
                },
                dataLabels: {
                    enabled: true,
                },
                xaxis: {
                    type: "category",
                    title: {
                        text: "Tanggal",
                    },
                    labels: {
                        rotate: -45,
                    },
                },
                yaxis: {
                    title: {
                        text: "ID Mesin",
                    },
                },
                colors: ["#00A9A5"],
                tooltip: {
                    y: {
                        formatter: (val) => `${val} Sampel`,
                    },
                },
            };
        },

        pieSeries() {
            const total = this.rawDataPie.length;
            const khusus = this.rawDataPie.filter(
                (d) => d.flag_khusus === "Y"
            ).length;
            const umum = total - khusus;
            return [umum, khusus]; // Index 0 = Umum, Index 1 = Khusus
        },
        pieChartOptions() {
            return {
                labels: ["Sampel Umum", "Sampel Khusus"],
                title: {
                    text: "Distribusi Sampel Berdasarkan Tujuan Pengujian",
                    align: "center",
                },
                legend: {
                    position: "bottom",
                },
                tooltip: {
                    y: {
                        formatter: (val, opts) => {
                            const total = this.rawDataPie.length;
                            const percent = ((val / total) * 100).toFixed(1);
                            return `${val} Sampel (${percent}%)`;
                        },
                    },
                },
                dataLabels: {
                    formatter: (val, opts) => {
                        return `${val.toFixed(1)}%`;
                    },
                },
            };
        },

        groupedByIdMesin() {
            const countMap = {};
            this.rawDataBar.forEach((item) => {
                const key = item.Nama_Mesin;
                const jumlah = parseInt(item.Jumlah_Sampel, 10) || 0;
                if (countMap[key]) {
                    countMap[key] += jumlah;
                } else {
                    countMap[key] = jumlah;
                }
            });
            return countMap;
        },

        barSeries() {
            return [
                {
                    name: "Jumlah Sampel",
                    data: Object.values(this.groupedByIdMesin),
                },
            ];
        },
        barChartOptions() {
            return {
                chart: {
                    type: "bar",
                    toolbar: { show: true },
                },
                title: {
                    text: "Jumlah Sampel Permesin",
                    align: "center",
                },
                xaxis: {
                    categories: Object.keys(this.groupedByIdMesin),
                    title: {
                        text: "Nama Mesin",
                    },
                },
                yaxis: {
                    title: {
                        text: "Jumlah Sampel",
                    },
                },
                tooltip: {
                    y: {
                        formatter: (val) => `${val} Sampel`,
                    },
                },
                plotOptions: {
                    bar: {
                        distributed: true,
                    },
                },
                dataLabels: {
                    enabled: true,
                },
            };
        },

        chartSeries() {
            if (!this.rawData.length) return [];

            const seriesBerat = {
                name: "Berat (kg)",
                data: this.rawData.map((item) => item.Berat_Sampel), // biarkan 0 tetap tampil
                yAxisIndex: 0,
            };

            const seriesPcs = {
                name: "Jumlah (pcs)",
                data: this.rawData.map((item) => item.Jumlah_Pcs), // biarkan 0 tetap tampil
                yAxisIndex: 1,
            };

            return [seriesBerat, seriesPcs];
        },

        chartCategories() {
            if (!this.rawData.length) return [];
            return this.rawData.map((item) => `${item.Tanggal} ${item.Jam}`);
        },

        lineChartOptions() {
            return {
                chart: {
                    type: "line",
                    zoom: { enabled: true },
                    toolbar: { show: true },
                },
                stroke: {
                    curve: "smooth",
                    width: [2, 2],
                },
                xaxis: {
                    type: "category",
                    categories: this.chartCategories,
                    title: { text: "Tanggal dan Jam" },
                    labels: {
                        rotate: -45,
                        trim: true,
                        style: { fontSize: "10px" },
                    },
                },
                yaxis: [
                    {
                        seriesName: "Berat (kg)",
                        axisTicks: { show: true },
                        axisBorder: { show: true, color: "#008FFB" },
                        labels: { style: { colors: "#008FFB" } },
                        title: {
                            text: "Berat (kg)",
                            style: { color: "#008FFB" },
                        },
                    },
                    {
                        seriesName: "Jumlah (pcs)",
                        opposite: true,
                        axisTicks: { show: true },
                        axisBorder: { show: true, color: "#00E396" },
                        labels: { style: { colors: "#00E396" } },
                        title: {
                            text: "Jumlah (pcs)",
                            style: { color: "#00E396" },
                        },
                    },
                ],
                title: {
                    text: "Tren Berat & Jumlah Sampel",
                    align: "center",
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: (val, { seriesIndex }) => {
                            return seriesIndex === 0
                                ? `${val} kg`
                                : `${val} pcs`;
                        },
                    },
                },
                legend: {
                    position: "top",
                },
            };
        },
    },
    methods: {
        async fetchTrenBeratSampelRegistarsi() {
            try {
                const response = await axios.get(
                    "/api/v1/grafik/registrasi-sampel/tren-uji-sampel"
                );
                if (response.status === 200 && response.data?.result) {
                    this.rawData = response.data.result;
                }
            } catch (error) {
                console.error("Gagal fetch chart:", error);
            }
        },
        async fetchJumlahSampelPerMesin() {
            try {
                const response = await axios.get(
                    "/api/v1/grafik/registrasi-sampel/jumlah-sampel-permesin"
                );
                if (response.status === 200 && response.data?.result) {
                    this.rawDataBar = response.data.result;
                }
            } catch (error) {
                console.error("Gagal fetch chart:", error);
            }
        },
        async fetchDistribuasiTujuanSampel() {
            try {
                const response = await axios.get(
                    "/api/v1/grafik/registrasi-sampel/distribusi-tujuan-pengujian"
                );
                if (response.status === 200 && response.data?.result) {
                    this.rawDataPie = response.data.result;
                }
            } catch (error) {
                console.error("Gagal fetch chart:", error);
            }
        },
    },
    mounted() {
        this.fetchTrenBeratSampelRegistarsi();
        this.fetchJumlahSampelPerMesin();
        this.fetchDistribuasiTujuanSampel();
    },
};
</script>

<style scoped>
.chart-container {
    max-width: 100%;
    margin: auto;
}
.mb-3 {
    margin-bottom: 1.5rem;
}
</style>
