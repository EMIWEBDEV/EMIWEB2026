<template>
    <div class="container-fluid px-0 data-uji-container">
        <div class="card shadow-sm border-0 w-100 main-card">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start section-header">
                    <div class="d-flex align-items-center mb-3 header-content">
                        <i
                            class="fas fa-vial text-primary me-3 fa-2x header-icon"
                        ></i>
                        <div>
                            <h1 class="h2 fw-bold text-primary mb-1 main-title">
                                Kumpulan Data Validasi Hasil Trial
                            </h1>
                            <p class="text-muted mb-0 subtitle">
                                <i class="fas fa-building me-1"></i>
                                Kumpulan Data Validasi Hasil Trial laboratorium
                                PT. Evo Manufacturing Indonesia
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3 content-area">
                    <div class="card-body">
                        <div
                            v-if="loading.loadingListData"
                            class="text-center loading-state"
                        >
                            <div
                                class="d-flex justify-content-center py-4 loading-spinner"
                            >
                                <div
                                    class="spinner-border text-primary"
                                    role="status"
                                >
                                    <span class="visually-hidden"
                                        >Memuat...</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div v-else-if="listData.length > 0">
                            <div class="mb-3">
                                <div
                                    v-if="!Has_Standard_Configuration"
                                    class="alert alert-warning shadow-sm border-0 border-start border-5 border-warning fade show"
                                    role="alert"
                                >
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 me-3">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                width="24"
                                                height="24"
                                                fill="currentColor"
                                                class="bi bi-exclamation-triangle-fill"
                                                viewBox="0 0 16 16"
                                            >
                                                <path
                                                    d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"
                                                />
                                            </svg>
                                        </div>

                                        <div class="w-100">
                                            <h5
                                                class="alert-heading fw-bold text-uppercase mb-2"
                                                style="
                                                    font-size: 0.95rem;
                                                    letter-spacing: 0.5px;
                                                "
                                            >
                                                Perhatian: Standar Belum
                                                Dikonfigurasi
                                            </h5>

                                            <p
                                                class="mb-2"
                                                style="font-size: 0.9rem"
                                            >
                                                Sistem mendeteksi bahwa
                                                <strong>Standar Rentang</strong>
                                                (batas atas/bawah atau kriteria)
                                                untuk jenis analisa ini belum
                                                tersedia di database.
                                            </p>

                                            <div
                                                class="card card-body bg-warning bg-opacity-10 border-0 p-2 mb-3"
                                            >
                                                <ul
                                                    class="mb-0 ps-3"
                                                    style="
                                                        font-size: 0.9rem;
                                                        font-weight: 500;
                                                    "
                                                >
                                                    <li>
                                                        Hasil analisa akan
                                                        dianggap
                                                        <span
                                                            class="text-success fw-bold"
                                                            >"LAYAK"</span
                                                        >
                                                        secara otomatis.
                                                    </li>
                                                    <li>
                                                        Tidak ada validasi
                                                        otomatis terhadap nilai
                                                        input.
                                                    </li>
                                                </ul>
                                            </div>

                                            <p
                                                class="mb-0 text-muted fst-italic"
                                                style="
                                                    font-size: 0.75rem;
                                                    line-height: 1.4;
                                                "
                                            >
                                                *Mohon tinjau kembali urgensi
                                                standar ini. Segala risiko
                                                terkait kualitas data atau
                                                kesalahan validasi akibat
                                                ketiadaan standar konfigurasi
                                                menjadi tanggung jawab
                                                operasional sepenuhnya.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table
                                    class="table table-bordered table-nowrap align-middle mb-0"
                                >
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>No Transaksi</th>
                                            <th>No Sampel</th>
                                            <th>No PO</th>
                                            <th>No Split Po</th>
                                            <th>Batch</th>
                                            <th>Tanggal</th>
                                            <th
                                                v-for="param in template.parameter"
                                                :key="param.id_qc"
                                            >
                                                {{ param.nama_parameter }}
                                            </th>
                                            <template
                                                v-if="
                                                    Array.isArray(
                                                        template.formula
                                                    ) &&
                                                    template.formula.length >
                                                        0 &&
                                                    formulaAverages.length > 0
                                                "
                                            >
                                                <th
                                                    v-for="(
                                                        hitung, i
                                                    ) in template.formula"
                                                    :key="'hitung-header-' + i"
                                                >
                                                    {{ hitung.nama_kolom }}
                                                </th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, rowIndex) in listData"
                                            :key="rowIndex"
                                            :class="
                                                row.Kode_Analisa === 'MBLG-STR'
                                                    ? ''
                                                    : row.Flag_Layak === 'Y'
                                                    ? 'table-success'
                                                    : 'table-danger'
                                            "
                                        >
                                            <td>
                                                {{ rowIndex + 1 }}
                                            </td>
                                            <td>
                                                {{ row.No_Faktur }}
                                            </td>
                                            <td>{{ row.No_Po_Sampel }}</td>
                                            <td>{{ row.No_Po }}</td>
                                            <td>{{ row.No_Split_Po }}</td>
                                            <td>Batch {{ row.No_Batch }}</td>
                                            <td>
                                                {{ formatTanggal(row.Tanggal) }}
                                            </td>
                                            <td
                                                v-for="(
                                                    paramValue, pIndex
                                                ) in row.parameters"
                                                :key="`param-${rowIndex}-${pIndex}`"
                                            >
                                                {{ paramValue }}
                                            </td>
                                            <template
                                                v-if="
                                                    Array.isArray(
                                                        template.formula
                                                    ) &&
                                                    template.formula.length >
                                                        0 &&
                                                    formulaAverages.length > 0
                                                "
                                            >
                                                <td
                                                    v-for="(
                                                        formula, fIndex
                                                    ) in template.formula"
                                                    :key="`formula-${rowIndex}-${fIndex}`"
                                                >
                                                    {{
                                                        row.results[fIndex] &&
                                                        row.results[fIndex]
                                                            .value !==
                                                            undefined &&
                                                        row.results[fIndex]
                                                            .value !== null
                                                            ? row.results[
                                                                  fIndex
                                                              ].value
                                                            : "-"
                                                    }}
                                                </td>
                                            </template>
                                        </tr>
                                        <tr
                                            v-if="
                                                template.formula &&
                                                template.formula.length > 0 &&
                                                formulaAverages.length > 0
                                            "
                                            class="table-warning fw-bold"
                                        >
                                            <td
                                                :colspan="
                                                    7 +
                                                    (template.parameter
                                                        ? template.parameter
                                                              .length
                                                        : 0)
                                                "
                                                class="text-center"
                                            >
                                                <strong>Rata-Rata</strong>
                                            </td>
                                            <td
                                                v-for="(
                                                    avg, fIndex
                                                ) in formulaAverages"
                                                :key="'avg-formula-' + fIndex"
                                            >
                                                {{ avg }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- mungkin disini -->
                            <div
                                v-if="informasiData?.sesi_foto === 'Y'"
                                class="mt-5 pt-3"
                            >
                                <div class="d-flex align-items-center mb-4">
                                    <h4 class="fw-bold mb-0 text-dark">
                                        Dokumentasi Hasil Analisa
                                    </h4>
                                    <div
                                        class="flex-grow-1 border-top border-2 ms-4 border-light"
                                    ></div>
                                </div>

                                <div
                                    v-for="(item, index) in listData"
                                    :key="index"
                                    class="mb-5"
                                >
                                    <div
                                        v-if="
                                            item.foto_analisa &&
                                            item.foto_analisa.length > 0
                                        "
                                        class="card border border-light shadow-sm rounded-4 overflow-hidden"
                                    >
                                        <div
                                            class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center"
                                        >
                                            <span
                                                class="fw-bold text-secondary text-uppercase"
                                                style="
                                                    letter-spacing: 1px;
                                                    font-size: 0.85rem;
                                                "
                                            >
                                                <i
                                                    class="fas fa-camera me-2 text-primary"
                                                ></i>
                                                Identitas Sampel
                                            </span>
                                            <span
                                                class="badge bg-primary px-3 py-2 rounded-pill fs-6"
                                            >
                                                {{ item.No_Po_Sampel }}
                                            </span>
                                        </div>

                                        <div class="card-body p-3 bg-light">
                                            <div class="row g-3">
                                                <div
                                                    v-for="foto in item.foto_analisa"
                                                    :key="foto.Berkas_Key"
                                                    class="col-12 col-md-6"
                                                >
                                                    <div
                                                        class="bg-white border rounded shadow-sm p-2 h-100 d-flex flex-column"
                                                    >
                                                        <div
                                                            v-if="
                                                                !fotoBlobUrls[
                                                                    foto
                                                                        .Berkas_Key
                                                                ]
                                                            "
                                                            class="d-flex justify-content-center align-items-center bg-light w-100 rounded"
                                                            style="
                                                                height: 250px;
                                                            "
                                                        >
                                                            <div
                                                                class="spinner-grow text-primary"
                                                                role="status"
                                                            >
                                                                <span
                                                                    class="visually-hidden"
                                                                    >Memuat
                                                                    Gambar...</span
                                                                >
                                                            </div>
                                                        </div>
                                                        <el-image
                                                            v-else
                                                            class="w-100 d-block rounded"
                                                            style="
                                                                height: 250px;
                                                                object-fit: contain;
                                                                background-color: #f8f9fa;
                                                            "
                                                            :src="
                                                                fotoBlobUrls[
                                                                    foto
                                                                        .Berkas_Key
                                                                ]
                                                            "
                                                            :zoom-rate="1.2"
                                                            :max-scale="7"
                                                            :min-scale="0.2"
                                                            :preview-src-list="
                                                                item.foto_analisa
                                                                    .map(
                                                                        (f) =>
                                                                            fotoBlobUrls[
                                                                                f
                                                                                    .Berkas_Key
                                                                            ]
                                                                    )
                                                                    .filter(
                                                                        Boolean
                                                                    )
                                                            "
                                                            :initial-index="
                                                                item.foto_analisa.findIndex(
                                                                    (f) =>
                                                                        f.Berkas_Key ===
                                                                        foto.Berkas_Key
                                                                )
                                                            "
                                                            fit="contain"
                                                            hide-on-click-modal
                                                            @contextmenu.prevent
                                                            @dragstart.prevent
                                                        >
                                                            <template #error>
                                                                <div
                                                                    class="d-flex flex-column justify-content-center align-items-center bg-light text-muted w-100 h-100 rounded"
                                                                >
                                                                    <i
                                                                        class="fas fa-image-slash fa-3x mb-3 text-secondary"
                                                                    ></i>
                                                                    <span
                                                                        class="fw-medium"
                                                                        >Gambar
                                                                        gagal
                                                                        dimuat</span
                                                                    >
                                                                </div>
                                                            </template>
                                                        </el-image>

                                                        <div
                                                            class="mt-2 text-center flex-grow-1 d-flex align-items-center justify-content-center"
                                                        >
                                                            <span
                                                                class="text-secondary small fw-medium"
                                                            >
                                                                {{
                                                                    foto.Keterangan ||
                                                                    "Tidak ada keterangan"
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- end mungkin disini -->
                            <div
                                class="d-flex justify-content-end mt-3 gap-2 action-buttons-bottom"
                            >
                                <button
                                    class="btn btn-warning px-4 complete-btn text-white"
                                    @click="UjiUlang"
                                    data-bs-toggle="offcanvas"
                                    data-bs-target="#offcanvasRight"
                                    aria-controls="offcanvasRight"
                                >
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Uji Ulang (Reanalisis)
                                </button>

                                <button
                                    class="btn btn-success px-4 complete-btn"
                                    @click="selesaikanAnalisa(listData)"
                                >
                                    <i class="fas fa-check-circle me-1"></i>
                                    Simpan Hasil Analisa
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-center py-5 empty-state">
                            <div
                                class="d-flex justify-content-center mb-3 empty-animation"
                            >
                                <DotLottieVue
                                    style="height: 200px; width: 200px"
                                    autoplay
                                    loop
                                    src="/animation/empty.lottie"
                                />
                            </div>
                            <h5 class="text-muted mb-2 empty-title">
                                Data Tidak Ditemukan
                            </h5>
                            <p class="text-muted empty-message">
                                Tidak ada data hasil analisis yang tersedia saat
                                ini
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div
        class="offcanvas offcanvas-end"
        tabindex="-1"
        id="offcanvasRight"
        aria-labelledby="offcanvasRightLabel"
    >
        <div class="offcanvas-header">
            <h5 id="offcanvasRightLabel" class="mb-0">
                Form Uji Ulang Sampel Trial
                <i class="fas fa-desktop"></i>
            </h5>
            <button
                @click="resetForm()"
                type="button"
                class="btn-close text-reset"
                data-bs-dismiss="offcanvas"
                aria-label="Close"
            ></button>
        </div>
        <div class="offcanvas-body">
            <form @submit.prevent="submitReanalisis">
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label">No Uji Sebelumnya</label>
                        <input
                            :value="this.NoUjiSampelSebelumnya"
                            type="text"
                            disabled
                            class="form-control"
                        />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No Sampel Reanalisis</label>

                        <div v-if="loading.listData" class="placeholder-glow">
                            <span
                                class="placeholder col-12 bg-secondary rounded"
                                style="height: 38px; opacity: 0.3"
                            ></span>
                        </div>

                        <v-select
                            v-else-if="
                                listDataResampling && listDataResampling.length
                            "
                            v-model="selectedOptionReanalisis"
                            :options="listDataResampling"
                            label="name"
                            placeholder="--- Pilih No Sampel Reanalisis ---"
                            class="scrollable-select"
                        />

                        <div v-else class="text-muted fst-italic small">
                            Tidak ada data sampel untuk reanalisis.
                        </div>
                    </div>
                    <div class="d-grid">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="
                                loading.reanalisisAnalisa || loading.listData
                            "
                        >
                            <i class="bi bi-send-check me-2"></i>
                            Lakukan Uji Ulang
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script>
import ApexChart from "vue3-apexcharts";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import vSelect from "vue-select";
import { ElImage } from "element-plus";

export default {
    props: {
        Id_Jenis_Analisa: {
            type: [String, Number],
            default: null,
        },
        No_Sampel: {
            type: [String, Number],
            default: null,
        },
        No_Fak_Sub_Sampel: {
            type: [String, Number],
            default: null,
        },
        Has_Standard_Configuration: Boolean,
    },
    components: {
        DotLottieVue,
        apexchart: ApexChart,
        vSelect,
        ElImage,
    },
    data() {
        return {
            listData: [],
            listDataResampling: [],
            informasiData: {},
            dataTracking: [],
            formulaAverages: [],
            fotoBlobUrls: {},
            template: {
                parameter: [],
                formula: [],
            },
            loading: {
                loadingListData: false,
                listData: false,
                saveToDatabase: false,
                reanalisisAnalisa: false,
            },
            NoUjiSampelSebelumnya: null,
            selectedOptionReanalisis: null,
            form: {
                No_Po_Sampel: "",
                No_Sampel_Resampling_Origin: "",
            },
        };
    },
    computed: {
        timelineChartSeries() {
            if (!this.listData || this.listData.length === 0) {
                return [];
            }

            const prosesData = [];
            const tungguData = [];

            this.listData.forEach((item) => {
                if (!item.Tanggal_Registrasi || !item.Tanggal) {
                    return;
                }

                const regDate = new Date(item.Tanggal_Registrasi);
                const testDate = new Date(item.Tanggal);

                prosesData.push({
                    x: item.No_Faktur || item.No_Po_Sampel,
                    y: [regDate.getTime(), testDate.getTime()],
                });

                const tungguStart = new Date(regDate);
                tungguStart.setDate(regDate.getDate() + 1);
                tungguStart.setHours(0, 0, 0, 0);

                const tungguEnd = new Date(testDate);
                tungguEnd.setDate(testDate.getDate() - 1);
                tungguEnd.setHours(23, 59, 59, 999);

                if (tungguEnd.getTime() > tungguStart.getTime()) {
                    tungguData.push({
                        x: item.No_Faktur || item.No_Po_Sampel,
                        y: [tungguStart.getTime(), tungguEnd.getTime()],
                    });
                }
            });

            return [
                {
                    name: "Total Proses",
                    data: prosesData,
                },
                {
                    name: "Periode Tunggu (Idle)",
                    data: tungguData,
                },
            ];
        },

        timelineChartOptions() {
            return {
                chart: {
                    height: 350,
                    type: "rangeBar",
                    toolbar: {
                        show: true,
                    },
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        barHeight: "80%",
                        rangeBarGroupRows: true,
                    },
                },
                colors: ["#008FFB", "#FF4560"],
                fill: {
                    type: "solid",
                    opacity: 0.8,
                },
                xaxis: {
                    type: "datetime",
                    labels: {
                        datetimeUTC: false,
                        format: "dd MMM",
                    },
                },
                yaxis: {
                    show: true,
                    title: {
                        text: "Nomor Sampel",
                    },
                },
                tooltip: {
                    x: {
                        format: "dd MMMM yyyy",
                    },
                },
                legend: {
                    position: "top",
                },
                title: {
                    text: "Timeline Proses dari Registrasi hingga Pengujian",
                    align: "center",
                },
            };
        },
        summary() {
            if (!this.listData || this.listData.length === 0)
                return { show: false };
            const firstItem = this.listData[0];
            const isLayak = firstItem.Flag_Layak === "Y";
            if (isLayak) {
                return {
                    show: true,
                    title: "✅ Sampel Dinyatakan Layak Uji",
                    message:
                        "Hasil analisa sampel telah memenuhi rentang spesifikasi yang ditentukan.",
                    alertClass: "alert-success",
                    icon: "ri-checkbox-circle-line",
                };
            } else {
                return {
                    show: true,
                    title: "❌ Sampel Dinyatakan Tidak Layak Uji",
                    message:
                        "Hasil analisa sampel berada di luar rentang spesifikasi yang ditentukan.",
                    alertClass: "alert-danger",
                    icon: "ri-close-circle-line",
                };
            }
        },
        chartSeries() {
            if (!this.listData || this.listData.length === 0) return [];
            const dataValues = this.listData.map((row) =>
                row.results && row.results.length > 0
                    ? parseFloat(row.results[0].value)
                    : 0
            );
            return [{ name: "Hasil Akhir Analisa", data: dataValues }];
        },
        chartOptions() {
            if (!this.listData || this.listData.length === 0) return {};
            const categories = this.listData.map((row) => row.No_Po_Sampel);
            const firstValidItem = this.listData.find(
                (item) => item.Range_Awal !== null && item.Range_Akhir !== null
            );
            if (!firstValidItem) return {};
            const rangeAwal = parseFloat(firstValidItem.Range_Awal);
            const rangeAkhir = parseFloat(firstValidItem.Range_Akhir);
            const allValues = this.chartSeries[0]
                ? this.chartSeries[0].data
                : [];
            const yMin = Math.min(...allValues, rangeAwal) - 1;
            const yMax = Math.max(...allValues, rangeAkhir) + 1;
            return {
                chart: {
                    id: "scatterChart",
                    type: "scatter",
                    height: 350,
                    zoom: { enabled: true, type: "xy" },
                    toolbar: { show: true },
                },
                annotations: {
                    yaxis: [
                        {
                            y: rangeAwal,
                            y2: rangeAkhir,
                            borderColor: "#00E396",
                            fillColor: "#00E396",
                            opacity: 0.2,
                            label: {
                                borderColor: "#00E396",
                                style: { color: "#fff", background: "#00E396" },
                                text: "Rentang Spesifikasi",
                            },
                        },
                    ],
                },
                xaxis: {
                    categories: categories,
                    title: { text: "Nomor Sampel" },
                    tickPlacement: "on",
                },
                yaxis: {
                    title: { text: "Nilai Hasil Analisa" },
                    min: yMin,
                    max: yMax,
                },
                title: {
                    text: "Sebaran Hasil vs Rentang Spesifikasi",
                    align: "center",
                },
                legend: { position: "top" },
                tooltip: {
                    y: { formatter: (val) => val },
                    x: {
                        formatter: (val, opts) =>
                            `Sampel: ${categories[opts.dataPointIndex]}`,
                    },
                },
            };
        },
        radarChartSeries() {
            if (
                !this.listData ||
                this.listData.length === 0 ||
                !this.listData[0].parameters
            ) {
                return [];
            }
            return [
                {
                    name: `Profil Sampel ${
                        this.listData[0].No_Fak_Sub_Po ||
                        this.listData[0].No_Po_Sampel
                    }`,
                    data: this.listData[0].parameters.map(
                        (p) => parseFloat(p) || 0
                    ),
                },
            ];
        },
        radarChartOptions() {
            if (!this.listData || this.listData.length === 0) return {};
            let labels = [];
            if (
                this.template &&
                this.template.parameter &&
                this.template.parameter.length > 0
            ) {
                labels = this.template.parameter.map((p) => p.nama_parameter);
            } else if (this.listData[0].parameters) {
                labels = this.listData[0].parameters.map(
                    (_, index) => `Parameter ${index + 1}`
                );
            }
            return {
                chart: {
                    height: 350,
                    type: "radar",
                },
                title: {
                    text: "Profil Perbandingan Parameter",
                    align: "center",
                },
                xaxis: {
                    categories: labels,
                },
                yaxis: {
                    tickAmount: 4,
                    labels: {
                        formatter: function (val) {
                            return val.toFixed(2);
                        },
                    },
                },
                markers: {
                    size: 4,
                    colors: ["#FF4560"],
                    strokeColor: "#FF4560",
                    strokeWidth: 2,
                },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val.toFixed(4);
                        },
                    },
                },
            };
        },

        durationChartSeries() {
            if (!this.listData || this.listData.length === 0) {
                return [];
            }
            const durations = this.listData.map((item) => {
                if (!item.Tanggal_Registrasi || !item.Tanggal) {
                    return 0;
                }
                const regDate = new Date(item.Tanggal_Registrasi);
                const testDate = new Date(item.Tanggal);
                regDate.setHours(0, 0, 0, 0);
                testDate.setHours(0, 0, 0, 0);
                const diffTime = testDate.getTime() - regDate.getTime();
                const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
                return Math.max(0, diffDays);
            });
            return [
                {
                    name: "Lama Proses (Hari)",
                    data: durations,
                },
            ];
        },

        durationChartOptions() {
            if (!this.listData || this.listData.length === 0) {
                return {};
            }
            const categories = this.listData.map(
                (item) => item.No_Faktur || item.No_Po_Sampel
            );
            return {
                chart: {
                    type: "bar",
                    height: 350,
                    toolbar: { show: true },
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: "60%",
                        dataLabels: {
                            position: "top",
                        },
                    },
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return val + " hari";
                    },
                    offsetY: -20,
                    style: {
                        fontSize: "12px",
                        colors: ["#304758"],
                    },
                },
                xaxis: {
                    categories: categories,
                    title: {
                        text: "Nomor Faktur / Sampel",
                    },
                },
                yaxis: {
                    title: {
                        text: "Durasi Proses (Jumlah Hari)",
                    },
                    max: function (max) {
                        return max + 2;
                    },
                },
                tooltip: {
                    y: {
                        formatter: function (
                            val,
                            { series, seriesIndex, dataPointIndex, w }
                        ) {
                            const item = this.listData[dataPointIndex];
                            const reg = `Registrasi: ${item.Tanggal_Registrasi}`;
                            const test = `Pengujian: ${
                                item.Tanggal.split(" ")[0]
                            }`;
                            return `${val} hari (${reg} -> ${test})`;
                        }.bind(this),
                    },
                },
                title: {
                    text: "Jarak Waktu Antara Registrasi dan Pengujian Sampel",
                    align: "center",
                },
                colors: ["#008FFB"],
            };
        },
    },
    methods: {
        async fetchBlobPhotos() {
            if (this.informasiData?.sesi_foto !== "Y") return;

            const allKeys = [];

            this.listData.forEach((item) => {
                item.foto_analisa?.forEach((f) => {
                    allKeys.push(f.Berkas_Key);
                });
            });

            if (allKeys.length === 0) return;

            const tokenResponse = await axios.post(
                `/api/v1/formulator/hasil-uji/berkas/foto/token/bulk`,
                { keys: allKeys }
            );

            const tokenMap = tokenResponse.data;

            for (const key of allKeys) {
                const res = await axios.get(
                    `/api/v1/formulator/berkas/stream/foto-uji/${key}?token=${tokenMap[key]}`,
                    { responseType: "blob" }
                );

                this.fotoBlobUrls[key] = URL.createObjectURL(res.data);
            }
        },
        hapusSemuaBlobMemori() {
            if (this.fotoBlobUrls) {
                Object.values(this.fotoBlobUrls).forEach((url) => {
                    URL.revokeObjectURL(url);
                });
                this.fotoBlobUrls = {};
            }
        },
        getRowsForLog(idLog) {
            if (!this.dataTracking || this.dataTracking.length === 0) {
                return [];
            }

            const logTerpilih = this.dataTracking.find(
                (log) => log.Id_Log_Activity === idLog
            );

            if (!logTerpilih) {
                return [];
            }

            const numParamsInTemplate =
                this.selectedTemplating?.parameter?.length ?? 0;
            const numParamsInData = logTerpilih.parameter?.length ?? 0;
            const allResults = logTerpilih.hasil || [];

            if (
                numParamsInTemplate > 0 &&
                numParamsInData > numParamsInTemplate &&
                allResults.length > 1
            ) {
                const allParameters = logTerpilih.parameter || [];
                const totalRows = allResults.length;
                const restructuredData = [];

                for (let i = 0; i < totalRows; i++) {
                    const startIndex = i * numParamsInTemplate;
                    const endIndex = startIndex + numParamsInTemplate;

                    if (endIndex > allParameters.length) {
                        continue;
                    }

                    const paramsForThisRow = allParameters.slice(
                        startIndex,
                        endIndex
                    );
                    const resultForThisRow = [allResults[i]];

                    const normalizedParams = paramsForThisRow.map((param) => ({
                        nama: param.nama_parameter,
                        Value_Baru:
                            param?.Value_Baru ?? param?.Value_Parameter ?? null,
                        Value_Lama: param?.Value_Lama ?? null,
                    }));

                    const normalizedResults = resultForThisRow.map((res) => ({
                        Value_Baru:
                            res?.Value_Baru ?? res?.Hasil_Perhitungan ?? null,
                        Value_Lama: res?.Value_Lama ?? null,
                    }));

                    restructuredData.push({
                        parameter: normalizedParams,
                        hasil: normalizedResults,
                    });
                }
                return restructuredData;
            } else {
                const parameters = logTerpilih.parameter || [];
                const results = logTerpilih.hasil || [];

                const normalizedParams = parameters.map((param) => ({
                    nama: param.nama_parameter,
                    Value_Baru:
                        param?.Value_Baru ?? param?.Value_Parameter ?? null,
                    Value_Lama: param?.Value_Lama ?? null,
                }));

                const normalizedResults = results.map((res) => ({
                    Value_Baru:
                        res?.Value_Baru ?? res?.Hasil_Perhitungan ?? null,
                    Value_Lama: res?.Value_Lama ?? null,
                }));

                return [
                    {
                        parameter: normalizedParams,
                        hasil: normalizedResults,
                    },
                ];
            }
        },
        processItems(items, template) {
            if (!Array.isArray(items) || items.length === 0) {
                return { data: [], formulaAverages: [] };
            }

            const groupedByFaktur = items.reduce((acc, item) => {
                const key = item.No_Faktur;
                if (!acc[key]) {
                    acc[key] = [];
                }
                acc[key].push(item);
                return acc;
            }, {});

            const processedData = Object.values(groupedByFaktur).map(
                (group) => {
                    const firstItemInGroup = group[0];

                    const parameterResults = Array.isArray(
                        firstItemInGroup.parameter
                    )
                        ? firstItemInGroup.parameter.map(
                              (p) => p.Hasil_Analisa ?? "-"
                          )
                        : [];

                    const finalResults = group.map((item) => ({
                        value: item.Hasil_Akhir_Analisa ?? "-",
                        Flag_Layak: item.Flag_Layak,
                        Flag_FG: item.Flag_FG,
                        pembulatan: item.Pembulatan ?? 4,
                    }));

                    return {
                        No_Po: firstItemInGroup.No_Po || "-",
                        No_Split_Po: firstItemInGroup.No_Split_Po || "-",
                        No_Batch: firstItemInGroup.No_Batch || "-",
                        No_Faktur: firstItemInGroup.No_Faktur || "-",
                        Kode_Analisa: firstItemInGroup.Kode_Analisa || "-",
                        Flag_Layak: firstItemInGroup.Flag_Layak || "-",
                        Id_Mesin: firstItemInGroup.Id_Mesin || "-",
                        Id_Jenis_Analisa:
                            firstItemInGroup.Id_Jenis_Analisa || "-",
                        Tahapan_Ke: firstItemInGroup.Tahapan_Ke || "-",
                        Flag_Multi_QrCode:
                            firstItemInGroup.Flag_Multi_QrCode || "-",
                        No_Po_Sampel: firstItemInGroup.No_Po_Sampel || "-",
                        No_Fak_Sub_Po: firstItemInGroup.No_Fak_Sub_Po || "-",
                        Tanggal: firstItemInGroup.Tanggal_Pengujian || "-",
                        Tanggal_Registrasi:
                            firstItemInGroup.Tanggal_Registrasi || "-",
                        parameters: parameterResults,
                        results: finalResults,
                        Range_Awal: firstItemInGroup.Range_Awal,
                        Range_Akhir: firstItemInGroup.Range_Akhir,
                        foto_analisa: firstItemInGroup.foto_analisa || [],
                    };
                }
            );

            let numFormulaColumns = 0;
            if (template?.formula?.length > 0) {
                numFormulaColumns = template.formula.length;
            } else if (processedData.length > 0) {
                numFormulaColumns = processedData[0].results.length;
            }

            const formulaAverages = [];
            for (let i = 0; i < numFormulaColumns; i++) {
                let total = 0;
                let count = 0;
                let decimalPlaces = 4;

                processedData.forEach((row) => {
                    const result = row.results[i];
                    if (result && result.value !== "-") {
                        const val = parseFloat(result.value);
                        if (!isNaN(val)) {
                            total += val;
                            count++;
                            if (result.pembulatan) {
                                decimalPlaces = parseInt(result.pembulatan, 10);
                            }
                        }
                    }
                });

                if (count > 0) {
                    formulaAverages.push(
                        (total / count).toFixed(decimalPlaces)
                    );
                } else {
                    formulaAverages.push("-");
                }
            }

            return { data: processedData, formulaAverages };
        },
        getActivityStyle(jenis) {
            if (jenis === "save_draft") {
                return {
                    bg: "bg-warning",
                    icon: "fas fa-pencil-alt",
                };
            }
            if (jenis === "save_update") {
                return {
                    bg: "bg-primary",
                    icon: "fas fa-pencil-alt",
                };
            }
            if (jenis === "save_delete") {
                return {
                    bg: "bg-danger",
                    icon: "fas fa-trash",
                };
            }

            if (jenis === "save_submit") {
                return {
                    bg: "bg-success",
                    icon: "fas fa-check",
                };
            }

            if (jenis === "finished") {
                return {
                    bg: "bg-success",
                    icon: "fas fa-check",
                };
            }

            return {
                bg: "bg-danger",
                icon: "fas fa-times",
            };
        },
        async fetchHasilAnalisaByJenisAnalisa() {
            this.loading.loadingListData = true;
            try {
                const [response, parameterResponse] = await Promise.all([
                    axios.get(
                        `/api/v1/formulator/validasi-hasil/uji-trial/verifikasi-analisa/multi/${this.Id_Jenis_Analisa}/${this.No_Sampel}/${this.No_Fak_Sub_Sampel}`
                    ),
                    axios.get(
                        `/fetch/lab/lama/${this.Id_Jenis_Analisa}/parameter-perhitungan-old`
                    ),
                ]);

                const isValidResponse =
                    response?.status === 200 &&
                    parameterResponse?.status === 200 &&
                    response.data?.success;

                if (
                    isValidResponse &&
                    Array.isArray(response.data.result.sampel)
                ) {
                    this.template = parameterResponse.data.result || {
                        parameter: [],
                        formula: [],
                    };

                    const { data, formulaAverages } = this.processItems(
                        response.data.result.sampel,
                        this.template
                    );

                    this.listData = data;
                    this.formulaAverages = formulaAverages;
                    this.informasiData = response.data.result.informasi;

                    this.fetchBlobPhotos();
                } else {
                    throw new Error(
                        "Respons API tidak valid atau data tidak ditemukan."
                    );
                }
            } catch (error) {
                this.listData = [];
                this.formulaAverages = [];
                this.informasiData = null;
                this.dataTracking = [];
                this.template = { parameter: [], formula: [] };
            } finally {
                this.loading.loadingListData = false;
            }
        },
        formatTanggal(tanggalString) {
            const date = new Date(tanggalString);
            const options = { day: "2-digit", month: "short", year: "numeric" };
            return date.toLocaleDateString("en-GB", options);
        },
        jamKeMenit(jamStr) {
            const [jam, menit, detik] = jamStr.split(":").map(Number);
            return jam * 60 + menit + detik / 60;
        },
        async selesaikanAnalisa(item) {
            const confirmResult = await Swal.fire({
                title: "Konfirmasi Penyelesaian",
                text: "Anda akan menyelesaikan dan mengirimkan data analisis ini. Aksi ini tidak dapat dibatalkan. Lanjutkan?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#d33",
                confirmButtonText: "Ya, Selesaikan & Kirim!",
                cancelButtonText: "Batal",
            });

            if (!confirmResult.isConfirmed) return;

            this.loading.saveToDatabase = true;
            Swal.fire({
                title: "Mengirim Data...",
                text: "Mohon tunggu sebentar.",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const response = await axios.post(
                    "/api/v1/formulator/validasi-hasil/uji-trial/approve",
                    {
                        analyses: item,
                    }
                );

                if (response.data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: "Data analisis telah berhasil disimpan.",
                    }).then(
                        () => (location.href = "/validasi-hasil/uji-trial")
                    );
                } else {
                    throw new Error(
                        response.data.message || "Gagal menyimpan data."
                    );
                }
            } catch (error) {
                const errorMessage =
                    error.response?.data?.message ||
                    "Terjadi kesalahan pada server.";
                Swal.fire("Gagal!", errorMessage, "error");
            } finally {
                this.loading.saveToDatabase = false;
            }
        },

        async submitReanalisis() {
            this.loading.reanalisisAnalisa = true;
            try {
                const payload = {
                    No_Po_Sampel: this.form.No_Po_Sampel,
                    No_Sampel_Resampling_Origin: this.NoUjiSampelSebelumnya,
                    No_Sampel_Resampling: this.selectedOptionReanalisis.value,
                    Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                };
                const response = await axios.post(
                    "/api/v1/formulator/validasi-hasil/uji-trial/resampeling/reanalisis",
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (response.status !== 200 || !response.data.success) {
                    if (response.data.errors) {
                        this.errors = response.data.errors;
                    }
                    throw new Error(
                        response.data.message || "Gagal menyimpan data"
                    );
                }

                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: response.data.message,
                    timer: 1500,
                    showConfirmButton: false,
                }).then(() => {
                    location.href = "/validasi-hasil/uji-trial";
                });
            } catch (error) {
                Swal.fire("Error", error.message, "error");
            } finally {
                this.loading.reanalisisAnalisa = false;
            }
        },
        async fetchSubSampelUntukResampling() {
            this.loading.listData = true;
            try {
                const response = await axios.get(
                    `/api/v1/formulator/validasi-hasil/uji-trial/sub/all/${this.form.No_Po_Sampel}/${this.Id_Jenis_Analisa}`
                );
                if (response.status === 200 && response.data?.result) {
                    this.listDataResampling = response.data.result.map(
                        (item) => ({
                            value: item.No_Po_Multi,
                            name: `${item.No_Po_Multi}`,
                        })
                    );
                } else {
                    this.listDataResampling = [];
                }
            } catch (error) {
                this.listDataResampling = [];
            } finally {
                this.loading.listData = false;
            }
        },
        UjiUlang() {
            this.NoUjiSampelSebelumnya = this.No_Fak_Sub_Sampel;
            this.form = {
                No_Po_Sampel: this.No_Sampel,
                No_Sampel_Resampling_Origin: this.No_Fak_Sub_Sampel,
            };

            this.fetchSubSampelUntukResampling();
        },
        resetForm() {
            this.NoUjiSampelSebelumnya = "";
            this.form = {
                No_Po_Sampel: "",
                No_Sampel_Resampling_Origin: "",
            };
        },
    },
    mounted() {
        this.fetchHasilAnalisaByJenisAnalisa();
    },
    beforeUnmount() {
        this.hapusSemuaBlobMemori();
    },
};
</script>

<style scoped>
.skeleton {
    animation: pulse 1.5s infinite;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 37%, #f0f0f0 63%);
    background-size: 400% 100%;
    border-radius: 4px;
}

@keyframes pulse {
    0% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0 50%;
    }
}

.skeleton-line {
    height: 20px;
    margin-bottom: 10px;
}

.skeleton-btn {
    height: 40px;
    width: 100%;
    margin-bottom: 15px;
}

.skeleton-table-cell {
    height: 25px;
    margin: 5px 0;
}

/* Container Styles */
.data-uji-container {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.main-card {
    border-radius: 12px;
    overflow: hidden;
    background-color: #ffffff;
}

.section-header {
    padding: 0 1.5rem;
}

.header-content {
    padding-top: 1rem;
}

.header-icon {
    transition: transform 0.3s ease;
}

.header-icon:hover {
    transform: scale(1.1);
}

.main-title {
    font-size: 1.75rem;
    letter-spacing: -0.5px;
}

.subtitle {
    font-size: 0.95rem;
    opacity: 0.85;
}

.divider {
    height: 1px;
    background: linear-gradient(
        90deg,
        rgba(13, 110, 253, 0.1) 0%,
        rgba(13, 110, 253, 0.5) 50%,
        rgba(13, 110, 253, 0.1) 100%
    );
}

.confirm-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(25, 135, 84, 0.2);
}

.menu-btn {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.menu-btn:hover {
    background-color: rgba(108, 117, 125, 0.1);
}

/* Accordion Body Styles */
.inner-accordion-body {
    background-color: #f9fafb;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.loading-spinner {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>

<style>
.scroll-hint {
    font-size: 13px;
    color: #555;
    margin-bottom: 6px;
    text-align: left;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 0.3;
    }
    50% {
        opacity: 1;
    }
}

.table-scroll-wrapper {
    overflow-x: auto;
    width: 100%;
    position: relative;
    padding-bottom: 1rem;
    scrollbar-width: thin;
    scrollbar-color: #aaa transparent;
}

.table-scroll-wrapper::-webkit-scrollbar {
    height: 4px;
}

.table-scroll-wrapper::-webkit-scrollbar-thumb {
    background-color: #3f5189 !important;
    border-radius: 4px;
}

.modern-analysis-table {
    margin-left: 8px;
    min-width: 1200px;
    width: max-content;
    border-collapse: collapse;
    border: 1px solid #dee2e6; /* Border luar */
    font-size: 14px;
}

/* Tambahkan border di setiap sel */
.modern-analysis-table th,
.modern-analysis-table td {
    border: 1px solid #dee2e6;
    padding: 8px;
}

/* Pusatkan teks di thead */
.modern-analysis-table thead th {
    text-align: center;
    background-color: #f8f9fa; /* seperti Bootstrap thead-light */
    font-weight: 600;
    vertical-align: middle;
    white-space: nowrap;
    font-size: 12px;
}

/* Posisikan data sel */
.modern-analysis-table td {
    vertical-align: top;
    white-space: nowrap;
}

.custom-tooltip {
    padding: 12px;
    background: white;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.tooltip-header {
    font-weight: bold;
    font-size: 14px;
    color: #333;
    margin-bottom: 4px;
}

.tooltip-subheader {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}

.tooltip-row {
    display: flex;
    justify-content: space-between;
    margin: 6px 0;
    font-size: 13px;
}

.tooltip-row span:first-child {
    color: #777;
    margin-right: 12px;
}

.tooltip-row span:last-child {
    font-weight: 500;
    color: #333;
}
</style>

<style>
:root {
    --warna-primer: #4361ee;
    --warna-sekunder: #3f37c9;
    --warna-sukses: #4cc9f0;
    --warna-info: #4895ef;
    --warna-peringatan: #f72585;
    --warna-bahaya: #b5179e;
    --warna-latar: #f8f9fa;
    --warna-gelap: #212529;
    --warna-teks-primer: #2b2d42;
    --warna-teks-sekunder: #8d99ae;
    --radius-border: 12px;
    --bayangan: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transisi: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.header-kalkulator {
    text-align: center;
    margin-bottom: 1rem;
    padding-bottom: 1.5rem;
}

.judul-kalkulator {
    font-size: 2.2rem;
    font-weight: 700;
    color: #3f5189;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.deskripsi-kalkulator {
    font-size: 1.1rem;
    color: #35477b;
    max-width: 700px;
    margin: 0 auto;
}

.isi-dokumentasi {
    margin-bottom: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

/* Base Styles */
.calculation-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    color: #333;
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* Section Headers */
.section-header {
    margin-bottom: 1.5rem;
}

.section-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.section-badge i {
    margin-right: 0.5rem;
}

.formula-badge {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    border-left: 4px solid #0d6efd;
}

.result-badge {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
    border-left: 4px solid #198754;
}

.section-description {
    font-size: 0.9rem;
    color: #6c757d;
    margin-left: 0.25rem;
}

/* Parameter Table */
.parameter-table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.responsive-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.parameter-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.parameter-table th {
    background-color: #f8f9fa;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
}

.parameter-table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.parameter-index {
    font-weight: 500;
    color: #6c757d;
    width: 50px;
}

.parameter-name {
    font-weight: 500;
    min-width: 200px;
}

.parameter-unit {
    color: #6c757d;
    font-size: 0.85em;
    margin-left: 0.25rem;
}

.parameter-input-cell {
    min-width: 200px;
}

.input-group {
    display: flex;
    align-items: stretch;
}

.parameter-input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px 0 0 4px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.parameter-input:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.unit-display {
    background-color: #e9ecef;
    border: 1px solid #ced4da;
    border-left: 0;
    padding: 0.5rem 0.75rem;
    border-radius: 0 4px 4px 0;
    color: #495057;
}

/* Results Section */
.results-container {
    display: grid;
    gap: 1rem;
}

.result-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    border-left: 4px solid #198754;
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.result-title {
    font-weight: 600;
    color: #212529;
    display: flex;
    align-items: center;
}

.result-title i {
    color: #198754;
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

.result-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #198754;
    background-color: rgba(25, 135, 84, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    min-width: 80px;
    text-align: center;
}

.result-notes {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.notes-header {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.notes-header i {
    margin-right: 0.5rem;
}

.notes-content {
    font-size: 0.9rem;
    color: #495057;
    line-height: 1.5;
}

.result-footer {
    display: flex;
    justify-content: flex-end;
}

.calculation-method {
    font-size: 0.8rem;
    color: #6c757d;
}

.method-label {
    font-weight: 500;
    margin-right: 0.25rem;
}

/* Highlight Effect */
.parameter-row.highlighted {
    background-color: rgba(13, 110, 253, 0.05);
    transition: background-color 0.3s ease;
}

/* Responsive Layout */
@media (min-width: 992px) {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 1200px) {
    .calculation-container {
        grid-template-columns: 2fr 1fr;
    }
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.result-card {
    animation: fadeIn 0.3s ease-out forwards;
}

/* Print Styles */
@media print {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }

    .parameter-table-container,
    .result-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* animasi skeleton */
@keyframes pulseSkeleton {
    0% {
        background-color: #e0e0e0;
    }
    50% {
        background-color: #f0f0f0;
    }
    100% {
        background-color: #e0e0e0;
    }
}

.skeleton {
    animation: pulseSkeleton 1.5s infinite;
    border-radius: 8px;
}
.skeleton-image {
    width: 100%;
    height: 200px;
    margin-bottom: 16px;
}

.analysis-container {
    background: rgba(255, 255, 255, 0.98);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.analysis-container:hover {
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.12);
}

.section-title {
    font-weight: 800;
    font-size: 1.25rem;
    position: relative;
    padding-bottom: 16px;
    margin-bottom: 24px;
    color: #495057; /* Updated to use #495057 */
    letter-spacing: -0.5px;
}

.section-title::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    width: 80px;
    height: 5px;
    background: #495057;
    border-radius: 5px;
    box-shadow: 0 2px 8px rgba(19, 24, 50, 0.3);
}

.text-gradient {
    background: #495057;
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

/* Base Styles */
.cleaning-system-container {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8fafc;
    min-height: 100vh;
    color: #334155;
}

.system-header {
    background: linear-gradient(135deg, #456290 0%, #25335e 100%);
    color: white;
    padding: 1.5rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header-content {
    flex: 1;
}

.system-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: white;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.system-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0.25rem 0 0;
    font-weight: 400;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.btn-help {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-help:hover {
    background: rgba(255, 255, 255, 0.2);
}

.content-wrapper {
    max-width: 100%;
    margin: 2rem auto;
    padding: 0 2rem;
}

/* Panel Styles */
.search-panel,
.details-panel,
.template-panel,
.form-panel {
    background: white;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
    overflow: hidden;
}

.panel-header {
    padding: 1.25rem 1.5rem;
    background-color: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header.with-tabs {
    border-bottom: none;
}

.panel-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.panel-body {
    padding: 1.5rem;
}

/* Search Form */
.search-form {
    max-width: 600px;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #475569;
}

.input-with-button {
    display: flex;
    gap: 0.5rem;
}

.form-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 1rem;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
}

.btn-search {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-search:hover {
    background-color: #2563eb;
}

/* Detail Grid */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-label {
    font-weight: 500;
    color: #64748b;
}

.detail-value {
    font-weight: 500;
    color: #1e293b;
}

.detail-value.highlight {
    color: #3b82f6;
    font-weight: 600;
}

.status-badge {
    display: flex;
    gap: 0.5rem;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.badge.active {
    background-color: #d1fae5;
    color: #065f46;
}

.badge.priority {
    background-color: #3eb1df;
    color: #ffffff;
}

/* Notes Section */
.notes-section {
    background-color: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    margin-top: 1.5rem;
    border-left: 4px solid #60a5fa;
}

.notes-header {
    margin-bottom: 0.5rem;
}

.notes-label {
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notes-content {
    color: #475569;
    line-height: 1.5;
}

/* Form Panels */
.form-panel .panel-header {
    background-color: #f8fafc;
}

.btn-add {
    background-color: #10b981;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-add:hover {
    background-color: #059669;
}

.btn-add-param {
    background-color: #f59e0b;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    margin-right: 0.5rem;
}

.btn-add-param:hover {
    background-color: #d97706;
}

/* Multi Table */
.multi-table {
    overflow-x: auto;
}

.multi-table table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.multi-table th {
    background-color: #f1f5f9;
    color: #475569;
    font-weight: 600;
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.multi-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.multi-table input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.multi-table input:focus {
    outline: none;
    border-color: #60a5fa;
    box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}

.multi-table td.actions {
    text-align: center;
}
/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f1f5f9;
}

.btn-submit {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-submit:hover {
    background-color: #2563eb;
}

.btn-save {
    background-color: #8b5cf6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-save:hover {
    background-color: #7c3aed;
}

.modern-form {
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    overflow: hidden;

    margin: 0 auto;
}

.sample-info-card {
    background: #f8f9ff;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    gap: 32px;
    border: 1px solid #e0e7ff;
}

.info-item {
    display: flex;
    align-items: center;
}

.info-label {
    font-weight: 500;
    color: #4b5563;
    margin-right: 8px;
}

.info-value {
    font-weight: 600;
    color: #1e293b;
}

/* Modern Table Styles */
.analysis-table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.modern-analysis-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.modern-analysis-table th {
    background-color: #f8fafc;
    color: #64748b;
    font-weight: 600;
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-analysis-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #334155;
}

.modern-analysis-table tr:last-child td {
    border-bottom: none;
}

.parameter-name {
    font-weight: 500;
    min-width: 160px;
}

.parameter-hint {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 4px;
    font-weight: 400;
}

.parameter-method {
    color: #475569;
    font-size: 0.9rem;
}

.parameter-unit {
    color: #475569;
    font-weight: 500;
    text-align: center;
}

.parameter-input {
    min-width: 280px;
}

/* Dual Range Slider Styles */
.dual-range-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.range-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #64748b;
}

.range-slider-wrapper {
    position: relative;
    height: 24px;
    margin: 8px 0;
}

.range-track {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 4px;
    background: #e2e8f0;
    border-radius: 4px;
    transform: translateY(-50%);
    pointer-events: none;
}

.range-progress {
    position: absolute;
    height: 100%;
    background: #3b82f6;
    border-radius: 4px;
    pointer-events: none;
}

.modern-range {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}
button.btn-search:disabled {
    background-color: #ccc;
    cursor: not-allowed;
    border-color: #ccc;
}

.modern-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    position: relative;
    z-index: 3;
}

.range-values {
    display: flex;
    justify-content: space-between;
    margin-top: 4px;
}

.value-badge {
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #334155;
    font-weight: 500;
}

.min-value::before {
    content: "Min: ";
    opacity: 0.7;
}

.max-value::before {
    content: "Max: ";
    opacity: 0.7;
}

/* Modern Select Styles */
.modern-select-container {
    position: relative;
}

.modern-select {
    appearance: none;
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background-color: white;
    font-size: 0.9rem;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s ease;
    padding-right: 40px;
}

.modern-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #64748b;
    font-size: 0.8rem;
}

/* Modern Input Styles */
.modern-input-container {
    position: relative;
}

.modern-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #334155;
    transition: all 0.2s ease;
    padding-right: 40px;
}

.modern-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.input-unit {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 0.85rem;
    pointer-events: none;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #f1f5f9;
}

.action-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    font-size: 0.9rem;
}

.action-button i {
    font-size: 0.9rem;
}

.primary {
    background-color: #3b82f6;
    color: white;
}

.primary:hover {
    background-color: #2563eb;
}

.secondary {
    background-color: white;
    color: #3b82f6;
    border: 1px solid #e2e8f0;
}

.secondary:hover {
    background-color: #f8fafc;
}

/* handle slider */
/* Enhanced Range Slider Styles */
.range-slider-wrapper {
    position: relative;
    height: 32px;
    margin: 16px 0;
    padding: 0 8px;
}

.range-track {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    transform: translateY(-50%);
    overflow: hidden;
    display: flex;
}

.range-segment {
    height: 100%;
}

.red-segment {
    background: #ef4444;
}

.green-segment {
    background: #10b981;
}

.modern-range {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 3;
}

.range-handle {
    position: absolute;
    top: 50%;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    z-index: 4;
    cursor: grab;
    border: 2px solid #3b82f6;
    transition: all 0.2s ease;
}

.range-handle:hover {
    transform: translate(-50%, -50%) scale(1.1);
}

.range-handle:active {
    cursor: grabbing;
    transform: translate(-50%, -50%) scale(1.1);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.min-handle {
    z-index: 5;
}

.handle-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #3b82f6;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    margin-bottom: 8px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.range-handle:hover .handle-tooltip {
    opacity: 1;
}

.range-active {
    position: absolute;
    top: 50%;
    height: 6px;
    transform: translateY(-50%);
    z-index: 2;
    border-radius: 3px;
}
</style>

<style>
/* Completed Card Styles */
.completed-card {
    position: relative;
    opacity: 0.9;
    border-color: rgba(40, 167, 69, 0.3) !important;
}

.completed-card:hover {
    transform: none !important;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08) !important;
    border-color: rgba(40, 167, 69, 0.3) !important;
    cursor: not-allowed !important;
}

.completed-card::before {
    display: none !important;
}

.completed-card .analysis-icon {
    background: linear-gradient(135deg, #28a745, #218838) !important;
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.2) !important;
}

.completed-card:hover .analysis-icon {
    transform: none !important;
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.2) !important;
}

.completed-badge {
    position: absolute;
    top: 0px;
    right: 0px;
    background: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #28a745;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    z-index: 2;
}

.completed-text {
    font-size: 0.8rem;
    background: rgba(40, 167, 69, 0.15);
    color: #28a745;
    padding: 4px 8px;
    border-radius: 50px;
    margin-left: 8px;
    font-weight: 600;
}

.bg-success-soft {
    background-color: rgba(40, 167, 69, 0.15) !important;
    color: #28a745 !important;
}

/* Analysis Grid */
.analysis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
}

/* Warna khusus untuk badge */
.bg-primary-subtle {
    background-color: #e0f2fe !important;
    color: #0369a1 !important;
}

.bg-info-subtle {
    background-color: #e0f7fa !important;
    color: #00838f !important;
}

.bg-secondary-subtle {
    background-color: #e9ecef !important;
    color: #495057 !important;
}

.bg-success-subtle {
    background-color: #e6f7ee !important;
    color: #0d8050 !important;
}

.bg-warning-subtle {
    background-color: #fff3e0 !important;
    color: #e65100 !important;
}

/* Text Truncate */
.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Date Time Style */
.text-muted.small {
    font-size: 0.85em;
    color: #6c757d !important;
}

/* Hover Effect on Badges */
.badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.performance-chart-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.chart-wrapper {
    margin-bottom: 20px;
    border: 1px solid #eaeaea;
    border-radius: 8px;
    padding: 10px;
    background: white;
}

.legend-container {
    display: flex;
    justify-content: center;
    gap: 24px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    font-size: 14px;
    color: #555;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    margin-right: 8px;
    display: inline-block;
}
</style>
