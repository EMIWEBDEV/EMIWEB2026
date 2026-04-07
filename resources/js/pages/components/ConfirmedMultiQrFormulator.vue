<template>
    <div
        class="accordion custom-accordionwithicon custom-accordion-border accordion-border-box accordion-secondary"
        :id="'accordion-parent-' + listMulti.No_Po_Sampel"
    >
        <div class="d-flex align-items-center mb-3">
            <h6 class="mb-0 flex-grow-1 fw-semibold text-primary">
                <i class="fas fa-list-check me-2"></i>Daftar Nomor Sub PO Sampel
            </h6>
        </div>
        <div
            class="accordion-item mt-2 material-shadow"
            v-for="(subData, subKey) in listMulti.sub_sampel"
            :key="subKey"
        >
            <h2 class="accordion-header" :id="'heading-' + subKey">
                <button
                    class="accordion-button collapsed"
                    type="button"
                    data-bs-toggle="collapse"
                    :data-bs-target="'#collapse-' + subKey"
                    aria-expanded="false"
                    :aria-controls="'collapse-' + subKey"
                >
                    <i class="fas fa-layer-group text-info me-2"></i>
                    {{ subKey }}
                </button>
            </h2>
            <div
                :id="'collapse-' + subKey"
                class="accordion-collapse collapse"
                :aria-labelledby="'heading-' + subKey"
                :data-bs-parent="'#accordion-parent-' + listMulti.No_Po_Sampel"
            >
                <div class="accordion-body">
                    <div
                        class="alert alert-info d-flex align-items-center gap-2"
                        role="alert"
                    >
                        <i class="ri-information-line fs-4"></i>
                        <div>
                            <strong>Keterangan:</strong>
                            Baris dengan
                            <span class="text-danger fw-bold">latar merah</span>
                            menunjukkan hasil
                            <u>tidak masuk rentang nilai</u> (di bawah rentang
                            awal), sedangkan
                            <span class="text-success fw-bold"
                                >latar hijau</span
                            >
                            menunjukkan hasil
                            <u
                                >berada dalam rentang yang diharapkan atau
                                melebihi rentang akhir</u
                            >.
                        </div>
                    </div>
                    <span
                        class="badge bg-primary mb-3"
                        v-if="subData.data[0].is_resampling === true"
                        >Hasil Uji Tahapan Ke {{ subData.data[0].Tahapan_Ke }}
                    </span>
                    <div class="table-responsive">
                        <table
                            class="table table-bordered table-nowrap align-middle"
                        >
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>No_Faktur</th>
                                    <th>No Sampel</th>
                                    <th>No Sub Sampel</th>
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
                                    <th
                                        v-for="(hitung, i) in template.formula"
                                        :key="'hitung-header-' + i"
                                    >
                                        {{ hitung.nama_kolom }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, rowIndex) in subData.data"
                                    :key="rowIndex"
                                    :class="
                                        row.Flag_Layak === 'Y'
                                            ? 'table-success'
                                            : 'table-danger'
                                    "
                                >
                                    <td>{{ rowIndex + 1 }}</td>
                                    <td>{{ row.No_Faktur }}</td>
                                    <td>{{ row.No_Po_Sampel }}</td>
                                    <td>{{ row.No_Fak_Sub_Po }}</td>
                                    <td>{{ row.No_Po }}</td>
                                    <td>{{ row.No_Split_Po }}</td>
                                    <td>Batch {{ row.No_Batch }}</td>
                                    <td>{{ formatTanggal(row.Tanggal) }}</td>
                                    <td
                                        v-for="(
                                            paramValue, pIndex
                                        ) in row.parameters"
                                        :key="`param-${rowIndex}-${pIndex}`"
                                    >
                                        {{ paramValue }}
                                    </td>
                                    <td
                                        v-for="(
                                            formula, fIndex
                                        ) in template.formula"
                                        :key="`formula-${rowIndex}-${fIndex}`"
                                    >
                                        {{
                                            row.results[fIndex] !== undefined &&
                                            row.results[fIndex] !== null
                                                ? row.results[fIndex]
                                                : "-"
                                        }}
                                    </td>
                                </tr>
                                <tr class="table-warning fw-bold">
                                    <td
                                        :colspan="8 + template.parameter.length"
                                        class="text-center"
                                    >
                                        <strong>Rata-Rata</strong>
                                    </td>
                                    <td
                                        v-for="(
                                            avg, fIndex
                                        ) in subData.formulaAverages"
                                        :key="'avg-formula-' + fIndex"
                                    >
                                        {{ avg }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        class="d-flex justify-content-end mt-3 gap-2 action-buttons-bottom"
                    >
                        <button
                            v-if="subData.data[0].is_resampling === true"
                            class="btn btn-warning px-4 complete-btn text-white"
                            @click.stop="UjiUlang(subData.data)"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#offcanvasRight"
                            aria-controls="offcanvasRight"
                        >
                            <i class="fas fa-sync-alt me-1"></i>
                            Uji Ulang (Reanalisis)
                        </button>

                        <button
                            class="btn btn-success px-4 complete-btn"
                            @click.stop="selesaikanAnalisa(subData)"
                        >
                            <i class="fas fa-check-circle me-1"></i>
                            Simpan Hasil Analisa
                        </button>
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
                Form Resampling Analisa
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
                        <v-select
                            v-if="listData && listData.length"
                            v-model="selectedOptionReanalisis"
                            :options="listData"
                            label="name"
                            placeholder="--- Pilih No Sampel Reanalisis ---"
                            class="scrollable-select"
                        />
                    </div>
                    <div class="d-grid">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="loading.reanalisisAnalisa"
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
import axios from "axios";
import Swal from "sweetalert2";
import vSelect from "vue-select";

export default {
    name: "ConfirmedMultiQr",
    components: {
        vSelect,
    },
    data() {
        return {
            listData: [],
            NoUjiSampelSebelumnya: null,
            selectedOptionReanalisis: null,
            loading: {
                listData: false,
                saveToDatabase: false,
                reanalisisAnalisa: false,
            },
            form: {
                No_Po_Sampel: "",
                No_Sampel_Resampling_Origin: "",
            },
        };
    },
    props: {
        listMulti: {
            type: Object,
            default: () => ({}),
        },
        template: {
            type: Object,
            default: () => ({}),
        },
    },
    methods: {
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

            if (!result.isConfirmed) return;

            // Validasi awal
            if (!item?.data || !Array.isArray(item.data)) {
                Swal.fire(
                    "Error!",
                    "Data analisis tidak valid atau kosong.",
                    "error"
                );
                return;
            }

            const templateParams = Array.isArray(this.template?.parameter)
                ? this.template.parameter
                : [];
            const templateFormulas = Array.isArray(this.template?.formula)
                ? this.template.formula
                : [];

            if (!templateParams.length && !templateFormulas.length) {
                Swal.fire(
                    "Error!",
                    "Template parameter dan formula tidak ditemukan.",
                    "error"
                );
                return;
            }

            const analyses = item.data.map((row) => {
                const parametersData = {};
                const resultsData = {};

                const rowParameters = Array.isArray(row.parameters)
                    ? row.parameters
                    : [];
                const rowResults = Array.isArray(row.results)
                    ? row.results
                    : [];

                // Mapping parameter
                templateParams.forEach((param, index) => {
                    parametersData[param.nama_parameter] =
                        index < rowParameters.length
                            ? rowParameters[index]
                            : null;
                });

                // Mapping formula
                templateFormulas.forEach((formula, index) => {
                    resultsData[formula.nama_kolom] =
                        index < rowResults.length ? rowResults[index] : null;
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
                    No_Fak_Sub_Po: row.No_Fak_Sub_Po || null,
                    No_Split_Po: row.No_Split_Po || null,
                    Tanggal: row.Tanggal || null,
                    parameters: parametersData,
                    results: resultsData,
                };
            });

            const payload = { analyses };

            this.loading.saveToDatabase = true;

            try {
                Swal.fire({
                    title: "Mengirim Data...",
                    text: "Mohon tunggu sebentar.",
                    allowOutsideClick: false,
                    allowEscapeKey: false,
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
                const errorMessage =
                    error.response?.data?.message ||
                    "Terjadi kesalahan pada server. Silakan coba lagi.";
                Swal.fire("Gagal!", errorMessage, "error");
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
        async fetchSubSampelUntukResampling() {
            this.loading.listData = true;
            try {
                const response = await axios.get(
                    `/api/v1/lab/no-uji/sampel/sub/all/${this.form.No_Po_Sampel}`
                );
                if (response.status === 200 && response.data?.result) {
                    this.listData = response.data.result.map((item) => ({
                        value: item.No_Po_Multi,
                        name: `${item.No_Po_Multi}`,
                    }));
                } else {
                    this.listData = [];
                }
            } catch (error) {
                this.listData = [];
            } finally {
                this.loading.listData = false;
            }
        },
        async submitReanalisis() {
            this.loading.reanalisisAnalisa = true;
            try {
                const payload = {
                    No_Po_Sampel: this.form.No_Po_Sampel,
                    No_Sampel_Resampling_Origin: this.NoUjiSampelSebelumnya,
                    No_Sampel_Resampling: this.selectedOptionReanalisis.value,
                };
                const response = await axios.post(
                    "/api/v1/lab/resampeling/reanalisis",
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
                    location.reload();
                });
            } catch (error) {
                Swal.fire("Error", error.message, "error");
            } finally {
                this.loading.reanalisisAnalisa = false;
            }
        },
        // async selesaikanAnalisa(item) {
        //     const result = await Swal.fire({
        //         title: "Konfirmasi Penyelesaian",
        //         text: "Anda akan menyelesaikan dan mengirimkan data analisis ini. Aksi ini tidak dapat dibatalkan. Lanjutkan?",
        //         icon: "warning",
        //         showCancelButton: true,
        //         confirmButtonColor: "#28a745", // Warna hijau untuk tombol konfirmasi
        //         cancelButtonColor: "#d33",
        //         confirmButtonText: "Ya, Selesaikan & Kirim!",
        //         cancelButtonText: "Batal",
        //     });

        //     if (!result.isConfirmed) {
        //         return;
        //     }

        //     const analyses = item.data.map((row) => {
        //         const parametersData = {};
        //         this.template.parameter.forEach((param, index) => {
        //             parametersData[param.nama_parameter] =
        //                 row.parameters[index];
        //         });

        //         const resultsData = {};
        //         this.template.formula.forEach((formula, index) => {
        //             resultsData[formula.nama_kolom] = row.results[index];
        //         });
        //         return {
        //             No_Po: row.No_Po,
        //             No_Batch: row.No_Batch,
        //             Kode_Barang: row.Kode_Barang,
        //             Seri_Mesin: row.Seri_Mesin,
        //             Nama_Mesin: row.Nama_Mesin,
        //             Catatan: row.Catatan,
        //             Flag_Multi_QrCode: row.Flag_Multi_QrCode,
        //             Id_Jenis_Analisa: row.Id_Jenis_Analisa,
        //             Tanggal_Pengujian: row.Tanggal_Pengujian,
        //             Jam_Pengujian: row.Jam_Pengujian,
        //             Tanggal_Pengajuan: row.Tanggal_Pengajuan,
        //             Jam_Pengajuan: row.Jam_Pengajuan,
        //             No_Faktur: row.No_Faktur,
        //             No_Po_Sampel: row.No_Po_Sampel,
        //             No_Fak_Sub_Po: row.No_Fak_Sub_Po,
        //             No_Split_Po: row.No_Split_Po,
        //             Tanggal: row.Tanggal,
        //             parameters: parametersData,
        //             results: resultsData,
        //         };
        //     });

        //     const payload = {
        //         analyses: analyses,
        //     };

        //     this.loading.saveToDatabase = true;
        //     try {
        //         Swal.fire({
        //             title: "Mengirim Data...",
        //             text: "Mohon tunggu sebentar.",
        //             allowOutsideClick: false,
        //             didOpen: () => {
        //                 Swal.showLoading();
        //             },
        //         });

        //         const response = await axios.post(
        //             "/uji-sampel/confirmed",
        //             payload,
        //             {
        //                 headers: {
        //                     "X-CSRF-TOKEN": document
        //                         .querySelector('meta[name="csrf-token"]')
        //                         .getAttribute("content"),
        //                 },
        //             }
        //         );

        //         if (response.status === 200 && response.data.success) {
        //             Swal.fire({
        //                 icon: "success",
        //                 title: "Berhasil",
        //                 text: "Data analisis telah berhasil disimpan.",
        //             }).then(() => {
        //                 location.reload();
        //             });
        //         } else {
        //             throw new Error(
        //                 response.data.message || "Gagal menyimpan data."
        //             );
        //         }
        //     } catch (error) {
        //         console.error("Terjadi error saat mengirim data:", error);
        //         let errorMessage =
        //             "Terjadi kesalahan pada server. Silakan coba lagi.";
        //         if (error.response?.data?.message) {
        //             errorMessage = error.response.data.message;
        //         }
        //         Swal.fire("Gagal!", errorMessage, "error");
        //     } finally {
        //         this.loading.saveToDatabase = false;
        //     }
        // },
        formatTanggal(tanggalString) {
            const date = new Date(tanggalString);
            const options = { day: "2-digit", month: "short", year: "numeric" };
            return date.toLocaleDateString("en-GB", options);
        },
        UjiUlang(item) {
            this.NoUjiSampelSebelumnya = item[0].No_Fak_Sub_Po;
            this.form = {
                No_Po_Sampel: item[0].No_Po_Sampel,
                No_Sampel_Resampling_Origin: item[0].No_Fak_Sub_Po,
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
};
</script>

<style>
.complete-btn {
    transition: all 0.3s ease;
    min-width: 180px;
}

.complete-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(25, 135, 84, 0.25);
}
</style>
