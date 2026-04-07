<template>
    <div class="container-fluid px-0 data-uji-container">
        <div class="card shadow-sm border-0 w-100 main-card">
            <div class="card-body">
                <!-- Header Section -->
                <div class="mb-4 text-center text-md-start section-header">
                    <div class="d-flex align-items-center mb-3 header-content">
                        <i
                            class="fas fa-vial text-primary me-3 fa-2x header-icon"
                        ></i>
                        <div>
                            <h1 class="h2 fw-bold text-primary mb-1 main-title">
                                Kumpulan Data Uji Analisis
                            </h1>
                            <p class="text-muted mb-0 subtitle">
                                <i class="fas fa-building me-1"></i>
                                Koleksi lengkap data uji laboratorium PT. Evo
                                Manufacturing Indonesia
                            </p>
                        </div>
                    </div>
                    <div class="divider bg-primary opacity-25 my-3"></div>
                </div>

                <div class="informasi-penting">
                    <div class="info-container">
                        <i class="fas fa-info-circle info-icon"></i>
                        <div class="info-content">
                            <h5 class="info-title">Penting!</h5>
                            <p class="info-text">
                                Data yang ditampilkan merupakan hasil submit
                                terakhir dari analisa laboratorium. Untuk
                                menjaga keamanan data dan memastikan kesesuaian
                                dengan inputan aktual, segera lakukan
                                <strong>konfirmasi penyelesaian</strong> setelah
                                verifikasi. Data yang sudah dikonfirmasi akan
                                difinalisasi dan tidak dapat diubah kembali.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3 content-area">
                    <ListSkeleton :page="5" v-if="loading.loadingListData" />

                    <div class="list-group" v-else>
                        <!-- Data Available -->
                        <div v-if="listData.length">
                            <div
                                class="accordion custom-accordion"
                                id="accordionBorderedMain"
                            >
                                <!-- Accordion Item Loop -->
                                <div
                                    class="accordion-item mb-3 border-0 shadow-sm rounded-3 accordion-item-custom"
                                    v-for="(item, index) in listData"
                                    :key="`item-${index}`"
                                    @click="
                                        fetchConfirmedUjiAnalisaSecond(
                                            item.Id_Jenis_Analisa
                                        )
                                    "
                                >
                                    <!-- Accordion Header -->
                                    <h2
                                        class="accordion-header"
                                        :id="`accordionHeader-${index}`"
                                    >
                                        <button
                                            class="accordion-button collapsed py-3 accordion-btn"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            :data-bs-target="`#collapse-${index}`"
                                            :aria-controls="`collapse-${index}`"
                                            aria-expanded="false"
                                        >
                                            <div
                                                class="d-flex w-100 align-items-center accordion-content"
                                            >
                                                <!-- Icon and Main Info -->
                                                <div
                                                    class="d-flex align-items-center flex-grow-1 main-info"
                                                >
                                                    <div
                                                        class="bg-primary bg-opacity-10 p-2 rounded-3 me-3 icon-wrapper"
                                                    >
                                                        <i
                                                            class="fas fa-flask text-primary fa-lg analysis-icon"
                                                        ></i>
                                                    </div>

                                                    <div
                                                        class="d-flex flex-column info-text"
                                                    >
                                                        <div
                                                            class="d-flex align-items-center mb-1 badge-container"
                                                        >
                                                            <span
                                                                class="badge bg-primary bg-opacity-10 text-primary fw-semibold me-2 code-badge"
                                                            >
                                                                <i
                                                                    class="fas fa-barcode me-1"
                                                                ></i>
                                                                {{
                                                                    item.Kode_Analisa ||
                                                                    "Tidak Ada Kode"
                                                                }}
                                                            </span>
                                                            <span
                                                                class="badge bg-light text-muted small date-badge"
                                                            >
                                                                <i
                                                                    class="far fa-calendar-alt me-1"
                                                                ></i>
                                                                Terakhir
                                                                diperbarui:
                                                                {{
                                                                    formatTanggal(
                                                                        item.Tanggal
                                                                    )
                                                                }}
                                                            </span>
                                                        </div>
                                                        <h6
                                                            class="mb-0 fw-bold text-dark analysis-name"
                                                        >
                                                            {{
                                                                item.Jenis_Analisa ||
                                                                "Jenis Analisis Tidak Tersedia"
                                                            }}
                                                        </h6>
                                                    </div>
                                                </div>

                                                <!-- Action Buttons -->
                                                <div
                                                    class="d-flex ms-auto action-buttons"
                                                >
                                                    <button
                                                        class="btn btn-success btn-sm rounded-pill px-3 me-2 confirm-btn"
                                                    >
                                                        <i
                                                            class="fas fa-check-circle me-1"
                                                        ></i>
                                                        Konfirmasi
                                                    </button>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>

                                    <!-- Accordion Content -->
                                    <div
                                        class="accordion-collapse collapse accordion-body-custom"
                                        :id="`collapse-${index}`"
                                        :aria-labelledby="`accordionHeader-${index}`"
                                        data-bs-parent="#accordionBorderedMain"
                                    >
                                        <div
                                            class="accordion-body pt-3 inner-accordion-body"
                                        >
                                            <div
                                                v-if="loading.loadingSecondData"
                                                class="loading-state"
                                            >
                                                <div
                                                    class="d-flex justify-content-center py-4 loading-spinner"
                                                >
                                                    <div
                                                        class="spinner-border text-primary"
                                                        role="status"
                                                    >
                                                        <span
                                                            class="visually-hidden"
                                                            >Memuat...</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Loaded Content -->
                                            <div v-else class="loaded-content">
                                                <div
                                                    class="accordion custom-accordion"
                                                    :id="`nestedAccordion-${index}`"
                                                    @click.stop
                                                >
                                                    <div
                                                        class="d-flex align-items-center mb-3"
                                                    >
                                                        <h6
                                                            class="mb-0 flex-grow-1 fw-semibold text-primary"
                                                        >
                                                            <i
                                                                class="fas fa-list-check me-2"
                                                            ></i
                                                            >Daftar Nomor PO
                                                            Sampel
                                                        </h6>
                                                    </div>
                                                    <div
                                                        class="accordion-item border-0 mb-2 nested-accordion-item"
                                                        v-for="(
                                                            second, secondIndex
                                                        ) in listSecondData"
                                                        :key="`second-${index}-${secondIndex}`"
                                                    >
                                                        <h2
                                                            class="accordion-header"
                                                            :id="`nestedHeader-${index}-${secondIndex}`"
                                                        >
                                                            <button
                                                                class="accordion-button collapsed bg-light nested-accordion-btn"
                                                                type="button"
                                                                data-bs-toggle="collapse"
                                                                :data-bs-target="`#nestedCollapse-${index}-${secondIndex}`"
                                                                aria-expanded="false"
                                                                :aria-controls="`nestedCollapse-${index}-${secondIndex}`"
                                                            >
                                                                <i
                                                                    class="fas fa-file-alt text-primary me-2"
                                                                ></i>
                                                                {{
                                                                    second.No_Po_Sampel ||
                                                                    "Nomor PO Sampel Tidak Tersedia"
                                                                }}
                                                            </button>
                                                        </h2>

                                                        <div
                                                            class="accordion-collapse collapse nested-accordion-collapse"
                                                            :id="`nestedCollapse-${index}-${secondIndex}`"
                                                            :aria-labelledby="`nestedHeader-${index}-${secondIndex}`"
                                                            :data-bs-parent="`#nestedAccordion-${index}`"
                                                        >
                                                            <div
                                                                class="accordion-body bg-white pt-3 nested-accordion-body"
                                                            >
                                                                <template
                                                                    v-if="
                                                                        second.flag_multi ===
                                                                        'Y'
                                                                    "
                                                                >
                                                                    <ConfirmedMultiQr
                                                                        :listMulti="
                                                                            second
                                                                        "
                                                                        :template="
                                                                            template
                                                                        "
                                                                    />
                                                                </template>

                                                                <template
                                                                    v-else
                                                                >
                                                                    <div
                                                                        class="table-responsive"
                                                                    >
                                                                        <table
                                                                            class="table table-bordered table-nowrap align-middle"
                                                                        >
                                                                            <thead
                                                                                class="table-light"
                                                                            >
                                                                                <tr>
                                                                                    <th>
                                                                                        No
                                                                                    </th>
                                                                                    <th>
                                                                                        No
                                                                                        Faktur
                                                                                    </th>
                                                                                    <th>
                                                                                        No
                                                                                        Sampel
                                                                                    </th>
                                                                                    <th>
                                                                                        No
                                                                                        PO
                                                                                    </th>
                                                                                    <th>
                                                                                        No
                                                                                        Split
                                                                                        Po
                                                                                    </th>
                                                                                    <th>
                                                                                        No
                                                                                        Batch
                                                                                    </th>

                                                                                    <th>
                                                                                        Tanggal
                                                                                    </th>
                                                                                    <th
                                                                                        v-for="param in template.parameter"
                                                                                        :key="
                                                                                            param.id_qc
                                                                                        "
                                                                                    >
                                                                                        {{
                                                                                            param.nama_parameter
                                                                                        }}
                                                                                    </th>
                                                                                    <th
                                                                                        v-for="(
                                                                                            hitung,
                                                                                            i
                                                                                        ) in template.formula"
                                                                                        :key="
                                                                                            'hitung-header-' +
                                                                                            i
                                                                                        "
                                                                                    >
                                                                                        {{
                                                                                            hitung.nama_kolom
                                                                                        }}
                                                                                    </th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <tr
                                                                                    v-for="(
                                                                                        row,
                                                                                        rowIndex
                                                                                    ) in second.data"
                                                                                    :key="
                                                                                        rowIndex
                                                                                    "
                                                                                >
                                                                                    <td>
                                                                                        {{
                                                                                            rowIndex +
                                                                                            1
                                                                                        }}
                                                                                    </td>
                                                                                    <td>
                                                                                        {{
                                                                                            row.No_Faktur
                                                                                        }}
                                                                                    </td>
                                                                                    <td>
                                                                                        {{
                                                                                            row.No_Po_Sampel
                                                                                        }}
                                                                                    </td>

                                                                                    <td>
                                                                                        {{
                                                                                            row.No_Po
                                                                                        }}
                                                                                    </td>
                                                                                    <td>
                                                                                        {{
                                                                                            row.No_Split_Po
                                                                                        }}
                                                                                    </td>
                                                                                    <td>
                                                                                        {{
                                                                                            row.No_Batch
                                                                                        }}
                                                                                    </td>

                                                                                    <td>
                                                                                        {{
                                                                                            formatTanggal(
                                                                                                row.Tanggal
                                                                                            )
                                                                                        }}
                                                                                    </td>
                                                                                    <td
                                                                                        v-for="(
                                                                                            paramValue,
                                                                                            pIndex
                                                                                        ) in row.parameters"
                                                                                        :key="`param-${rowIndex}-${pIndex}`"
                                                                                    >
                                                                                        {{
                                                                                            paramValue
                                                                                        }}
                                                                                    </td>
                                                                                    <td
                                                                                        v-for="(
                                                                                            formula,
                                                                                            fIndex
                                                                                        ) in template.formula"
                                                                                        :key="`formula-${rowIndex}-${fIndex}`"
                                                                                    >
                                                                                        {{
                                                                                            row
                                                                                                .results[
                                                                                                fIndex
                                                                                            ] !==
                                                                                                undefined &&
                                                                                            row
                                                                                                .results[
                                                                                                fIndex
                                                                                            ] !==
                                                                                                null
                                                                                                ? row
                                                                                                      .results[
                                                                                                      fIndex
                                                                                                  ]
                                                                                                : "-"
                                                                                        }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr
                                                                                    class="table-warning fw-bold"
                                                                                >
                                                                                    <td
                                                                                        :colspan="
                                                                                            7 +
                                                                                            template
                                                                                                .parameter
                                                                                                .length
                                                                                        "
                                                                                        class="text-center"
                                                                                    >
                                                                                        <strong
                                                                                            >Rata-Rata</strong
                                                                                        >
                                                                                    </td>
                                                                                    <td
                                                                                        v-for="(
                                                                                            avg,
                                                                                            fIndex
                                                                                        ) in second.formulaAverages"
                                                                                        :key="
                                                                                            'avg-formula-' +
                                                                                            fIndex
                                                                                        "
                                                                                    >
                                                                                        {{
                                                                                            avg
                                                                                        }}
                                                                                    </td>
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div
                                                                        class="d-flex justify-content-end mt-3 action-buttons-bottom"
                                                                    >
                                                                        <button
                                                                            :disabled="
                                                                                loading.saveToDatabase
                                                                            "
                                                                            class="btn btn-success px-4 complete-btn"
                                                                            @click.stop="
                                                                                selesaikanAnalisa(
                                                                                    second
                                                                                )
                                                                            "
                                                                        >
                                                                            <i
                                                                                class="fas fa-check-circle me-1"
                                                                            ></i>
                                                                            {{
                                                                                loading.saveToDatabase
                                                                                    ? "Loading..."
                                                                                    : " Selesaikan Analisis"
                                                                            }}
                                                                        </button>
                                                                    </div>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="!listData.length"
                            class="text-center py-5 empty-state"
                        >
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
                                Tidak ada data uji analisis yang tersedia saat
                                ini
                            </p>
                            <button class="btn btn-primary mt-3 empty-action">
                                <i class="fas fa-sync-alt me-1"></i>
                                Muat Ulang Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import ListSkeleton from "@/pages/vue/ui/ListSkeleton.vue";
import ConfirmedMultiQr from "../../../components/ConfirmedMultiQr.vue";

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
        ConfirmedMultiQr,
    },
    data() {
        return {
            listData: [],
            listSecondData: [],
            template: [],
            loading: {
                loadingListData: false,
                loadingSecondData: false,
                loadingTemplate: false,
                loadinPerhitungan: false,
                saveToDatabase: false,
            },
        };
    },

    methods: {
        async fetchConfirmedUjiAnalisa() {
            this.loading.loadingListData = true;
            try {
                const response = await axios.get(
                    "/api/v1/lab/confirmed-selesai/uji-sampel"
                );
                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result;
                } else {
                    this.listData = [];
                }
            } catch (error) {
                console.log(error);
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },

        processItems(items, template) {
            if (!Array.isArray(items) || items.length === 0) {
                console.warn("Items is not an array or is empty:", items);
                return { data: [], formulaAverages: [] };
            }

            const fakturGroups = {};
            items.forEach((item) => {
                if (!item) return;

                const key = `${item.No_Faktur || "-"}__${
                    item.No_Po_Sampel || "-"
                }__${item.No_Fak_Sub_Po || "-"}`;

                if (!fakturGroups[key]) {
                    fakturGroups[key] = {
                        No_Po: item.No_Po || "-",
                        No_Split_Po: item.No_Split_Po || "-",
                        No_Faktur: item.No_Faktur || "-",
                        No_Po_Sampel: item.No_Po_Sampel || "-",
                        No_Fak_Sub_Po: item.No_Fak_Sub_Po || "-",
                        Tanggal: item.Tanggal_Pengujian || "-",
                        Jam_Pengujian: item.Jam_Pengujian || "-",
                        Tanggal_Pengajuan: item.Tanggal_Pengajuan || "-",
                        Jam_Pengajuan: item.Jam_Pengajuan || "-",
                        No_Batch: item.No_Batch || "-",
                        Flag_Layak: item.Flag_Layak || "-",
                        Tahapan_Ke: item.Tahapan_Ke || "-",
                        is_resampling: item.is_resampling || "-",
                        Kode_Barang: item.Kode_Barang || "-",
                        Seri_Mesin: item.Seri_Mesin || "-",
                        Nama_Mesin: item.Nama_Mesin || "-",
                        Catatan: item.Catatan || "-",
                        Flag_Multi_QrCode: item.Flag_Multi_QrCode || null,
                        Flag_Perhitungan: item.Flag_Perhitungan || null,
                        Id_Jenis_Analisa: item.Id_Jenis_Analisa || "-",
                        parameters: Array.isArray(item.parameter)
                            ? item.parameter.map((p) => p.hasil_analisa || "-")
                            : [],

                        results: [],
                    };
                }

                if (
                    item.Hasil_Akhir_Analisa !== undefined &&
                    item.Hasil_Akhir_Analisa !== null
                ) {
                    fakturGroups[key].results.push({
                        value: item.Hasil_Akhir_Analisa,
                        pembulatan: parseInt(item.Pembulatan, 10) || 2, // Use this item's rounding, default to 2
                    });
                }
            });

            const groupedData = Object.values(fakturGroups);
            const formulaAverages = (template?.formula || []).map(
                (_, formulaIndex) => {
                    const firstRelevantResult = groupedData.find(
                        (d) => d.results[formulaIndex]
                    )?.results[formulaIndex];
                    const decimalPlaces = firstRelevantResult
                        ? firstRelevantResult.pembulatan
                        : 2;

                    let total = 0;
                    let count = 0;

                    groupedData.forEach((row) => {
                        const resultItem = row.results[formulaIndex];
                        if (
                            resultItem &&
                            resultItem.value !== undefined &&
                            resultItem.value !== null
                        ) {
                            const val = parseFloat(resultItem.value);
                            if (!isNaN(val)) {
                                total += val;
                                count++;
                            }
                        }
                    });

                    if (count > 0) {
                        return (total / count).toFixed(decimalPlaces);
                    } else {
                        return "-";
                    }
                }
            );

            const data = groupedData.map((group) => ({
                ...group,
                results: group.results.map((res) => res.value || "-"),
            }));

            return { data, formulaAverages };
        },
        async fetchConfirmedUjiAnalisaSecond(idJenisAnalisa) {
            this.loading.loadingSecondData = true;
            this.listSecondData = [];
            try {
                const [ujiSampelResponse, parameterResponse] =
                    await Promise.all([
                        axios.get(
                            `/api/v2/lab/confirmed-selesai/uji-sampel/by/${idJenisAnalisa}`
                        ),

                        axios.get(
                            `/fetch/lab/lama/${idJenisAnalisa}/parameter-perhitungan-old`
                        ),
                    ]);

                // Validasi response lebih ketat
                const isValidResponse =
                    ujiSampelResponse?.status === 200 &&
                    parameterResponse?.status === 200 &&
                    ujiSampelResponse.data?.success &&
                    parameterResponse.data?.success;

                if (!isValidResponse) {
                    throw new Error("Invalid API response");
                }

                const rawData =
                    ujiSampelResponse.data.result?.data_sampel || {};
                this.template = parameterResponse.data.result || {
                    parameter: [],
                    formula: [],
                };

                const processedAccordionData = {};

                for (const sampleKey in rawData) {
                    if (!rawData.hasOwnProperty(sampleKey)) continue;

                    const sampleGroup = rawData[sampleKey];
                    if (!sampleGroup) continue;

                    const isMulti = sampleGroup?.flag_multi === "Y";

                    try {
                        if (isMulti) {
                            const subSampelData = {};
                            let flagPerhitungan = null;

                            // Pastikan sampleGroup.sampel ada
                            if (sampleGroup.sampel) {
                                for (const subSampleKey in sampleGroup.sampel) {
                                    if (
                                        !sampleGroup.sampel.hasOwnProperty(
                                            subSampleKey
                                        )
                                    )
                                        continue;

                                    const items =
                                        sampleGroup.sampel[subSampleKey];
                                    if (!Array.isArray(items)) continue;

                                    if (items[0]?.Flag_Perhitungan) {
                                        flagPerhitungan =
                                            items[0].Flag_Perhitungan;
                                    }

                                    subSampelData[subSampleKey] =
                                        this.processItems(items, this.template);
                                }
                            }

                            processedAccordionData[sampleKey] = {
                                No_Po_Sampel: sampleKey,
                                flag_multi: "Y",
                                Flag_Perhitungan: flagPerhitungan,
                                sub_sampel: subSampelData,
                            };
                        } else {
                            const items = Array.isArray(sampleGroup)
                                ? sampleGroup
                                : [sampleGroup];
                            const processedData = this.processItems(
                                items,
                                this.template
                            );
                            const flagPerhitungan =
                                items[0]?.Flag_Perhitungan || null;

                            processedAccordionData[sampleKey] = {
                                No_Po_Sampel: sampleKey,
                                flag_multi: null,
                                Flag_Perhitungan: flagPerhitungan,
                                data: processedData.data,
                                formulaAverages: processedData.formulaAverages,
                            };
                        }
                    } catch (error) {
                        continue;
                    }
                }

                this.listSecondData = Object.values(processedAccordionData);
            } catch (error) {
                console.error("Error fetching data:", {
                    error: error.message,
                    stack: error.stack,
                });
                this.listSecondData = [];
                this.template = { parameter: [], formula: [] };
                this.detailInformasi = [];
            } finally {
                this.loading.loadingSecondData = false;
            }
        },

        formatTanggal(tanggalString) {
            const date = new Date(tanggalString);
            const options = { day: "2-digit", month: "short", year: "numeric" };
            return date.toLocaleDateString("en-GB", options);
        },

        async selesaikanAnalisa(item) {
            const result = await Swal.fire({
                title: "Konfirmasi Penyelesaian",
                text: "Anda akan menyelesaikan dan mengirimkan data analisis ini. Aksi ini tidak dapat dibatalkan. Lanjutkan?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#d33",
                confirmButtonText: "Ya, Selesaikan & Kirim!",
                cancelButtonText: "Batal",
            });

            if (!result.isConfirmed) {
                return;
            }

            // Validasi item.data sebelum diproses
            if (!item?.data || !Array.isArray(item.data)) {
                Swal.fire("Error!", "Data analisis tidak valid.", "error");
                return;
            }

            // Pastikan template dan parameter ada
            const templateParams = this.template?.parameter || [];
            const templateFormulas = this.template?.formula || [];

            const analyses = item.data.map((row) => {
                const parametersData = {};
                const resultsData = {};

                // Handle parameters dengan aman
                templateParams.forEach((param, index) => {
                    parametersData[param.nama_parameter] =
                        row.parameters && index < row.parameters.length
                            ? row.parameters[index]
                            : null;
                });

                // Handle formula results dengan aman
                templateFormulas.forEach((formula, index) => {
                    resultsData[formula.nama_kolom] =
                        row.results && index < row.results.length
                            ? row.results[index]
                            : null;
                });

                return {
                    No_Po: row.No_Po || null,
                    No_Batch: row.No_Batch || null,
                    Kode_Barang: row.Kode_Barang || null,
                    Seri_Mesin: row.Seri_Mesin || null,
                    Nama_Mesin: row.Nama_Mesin || null,
                    Catatan: row.Catatan || null,
                    Flag_Multi_QrCode: row.Flag_Multi_QrCode || null,
                    Id_Jenis_Analisa: row.Id_Jenis_Analisa || null,
                    Tanggal_Pengujian: row.Tanggal_Pengujian || null,
                    Jam_Pengujian: row.Jam_Pengujian || null,
                    Tanggal_Pengajuan: row.Tanggal_Pengajuan || null,
                    Jam_Pengajuan: row.Jam_Pengajuan || null,
                    No_Faktur: row.No_Faktur || null,
                    No_Po_Sampel: row.No_Po_Sampel || null,
                    No_Split_Po: row.No_Split_Po || null,
                    Tanggal: row.Tanggal || null,
                    parameters: parametersData,
                    results: resultsData,
                };
            });

            const payload = {
                analyses: analyses,
            };

            this.loading.saveToDatabase = true;
            try {
                Swal.fire({
                    title: "Mengirim Data...",
                    text: "Mohon tunggu sebentar.",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

                const response = await axios.post(
                    "/uji-sampel/confirmed",
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (response.status === 200 && response.data.success) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: "Data analisis telah berhasil disimpan.",
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(
                        response.data.message || "Gagal menyimpan data."
                    );
                }
            } catch (error) {
                console.error("Terjadi error saat mengirim data:", error);
                let errorMessage =
                    "Terjadi kesalahan pada server. Silakan coba lagi.";
                if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                }
                Swal.fire("Gagal!", errorMessage, "error");
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
    },

    mounted() {
        this.fetchConfirmedUjiAnalisa();
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

/* Accordion Styles */
.custom-accordion {
    --bs-accordion-border-width: 0;
}

.accordion-item-custom {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.accordion-item-custom:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-color: rgba(13, 110, 253, 0.2);
}

.accordion-btn {
    background-color: #ffffff;
    box-shadow: none;
}

.accordion-btn:not(.collapsed) {
    background-color: rgba(13, 110, 253, 0.05);
    color: #0d6efd;
}

.accordion-btn:focus {
    box-shadow: none;
    border-color: rgba(13, 110, 253, 0.2);
}

.icon-wrapper {
    transition: all 0.3s ease;
}

.accordion-btn:hover .icon-wrapper {
    background-color: rgba(13, 110, 253, 0.15);
}

.analysis-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.info-text {
    min-width: 0;
}

.badge-container {
    flex-wrap: wrap;
}

.code-badge {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
    display: inline-flex;
    align-items: center;
}

.date-badge {
    display: inline-flex;
    align-items: center;
}

.analysis-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.action-buttons {
    flex-shrink: 0;
}

.confirm-btn {
    transition: all 0.2s ease;
    min-width: 110px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
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

/* Tab Styles */
.result-tabs {
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.nav-tabs-custom .nav-link {
    border: none;
    padding: 0.75rem 1.5rem;
    color: #6c757d;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    position: relative;
    margin-bottom: -1px;
}

.nav-tabs-custom .nav-link.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background-color: transparent;
}

.nav-tabs-custom .nav-link:hover:not(.active) {
    color: #495057;
    border-bottom-color: rgba(13, 110, 253, 0.2);
}

/* Nested Accordion Styles */
.nested-accordion-item {
    background-color: #ffffff;
    border-radius: 8px !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    margin-bottom: 12px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.nested-accordion-btn {
    border-radius: 8px !important;
    padding: 0.75rem 1.25rem;
}

.nested-accordion-btn:not(.collapsed) {
    background-color: rgba(13, 110, 253, 0.05);
    color: #0d6efd;
}

.nested-accordion-body {
    padding: 1.25rem;
    border-radius: 0 0 8px 8px;
}

/* Detail Card Styles */
.detail-card {
    background-color: transparent;
}

.detail-header {
    border-radius: 8px 8px 0 0 !important;
}

.detail-title {
    font-size: 1.1rem;
    display: flex;
    align-items: center;
}

.time-value {
    font-size: 0.85em;
}

/* Action Buttons */
.action-buttons-bottom {
    animation: fadeInUp 0.3s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.complete-btn {
    transition: all 0.3s ease;
    min-width: 180px;
}

.complete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.25);
}

/* Empty State Styles */
.empty-state {
    animation: fadeIn 0.5s ease;
}

.empty-title {
    font-size: 1.25rem;
    font-weight: 500;
}

.empty-message {
    max-width: 400px;
    margin: 0 auto;
}

.empty-action {
    transition: all 0.3s ease;
    padding: 0.5rem 1.5rem;
}

.empty-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .header-icon {
        margin-bottom: 1rem;
    }

    .accordion-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .action-buttons {
        margin-top: 1rem;
        width: 100%;
        justify-content: flex-end;
    }

    .detail-col {
        width: 100%;
    }

    .detail-row {
        flex-direction: column;
    }
}

@media (max-width: 576px) {
    .main-title {
        font-size: 1.5rem;
    }

    .nav-tabs-custom .nav-link {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .complete-btn {
        width: 100%;
    }
}
</style>

<style>
.informasi-penting {
    margin: 1.5rem 0;
    animation: fadeIn 0.5s ease;
}

.info-container {
    background-color: rgba(13, 110, 253, 0.08);
    border-left: 4px solid #0d6efd;
    border-radius: 0 8px 8px 0;
    padding: 1.25rem;
    display: flex;
    align-items: flex-start;
    transition: all 0.3s ease;
}

.info-container:hover {
    background-color: rgba(13, 110, 253, 0.12);
    transform: translateX(3px);
}

.info-icon {
    color: #0d6efd;
    font-size: 1.5rem;
    margin-right: 1rem;
    margin-top: 0.2rem;
}

.info-content {
    flex: 1;
}

.info-title {
    color: #0d6efd;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.info-text {
    color: #495057;
    line-height: 1.7;
    margin-bottom: 0.75rem;
}

.info-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
    }
}

@media (max-width: 768px) {
    .info-container {
        flex-direction: column;
    }

    .info-icon {
        margin-bottom: 0.5rem;
    }
}

.badge {
    transition: all 0.2s ease;
}

.badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
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
</style>
