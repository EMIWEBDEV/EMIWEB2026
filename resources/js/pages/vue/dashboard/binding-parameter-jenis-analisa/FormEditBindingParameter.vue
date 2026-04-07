<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Edit
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Parameter Jenis Analisa Pada LAB PT. EVO MANUFACTURING
                        INDONESIA
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <form
                        @submit.prevent="submitForm"
                        v-loading="isFetchingData"
                        element-loading-text="Memuat Data Analisa..."
                        element-loading-background="rgba(255, 255, 255, 0.8)"
                    >
                        <div
                            v-if="roles && roles.length > 1"
                            class="card mb-4 shadow-sm border-primary"
                        >
                            <div
                                class="card-body bg-primary bg-opacity-10 rounded"
                            >
                                <label class="form-label fw-bold text-primary">
                                    Pilih Penempatan / Role
                                    <span class="text-danger">*</span>
                                </label>
                                <el-select
                                    v-model="selectedRole"
                                    placeholder="-- Pilih Role Penempatan --"
                                    filterable
                                    size="large"
                                    style="width: 100%"
                                >
                                    <el-option
                                        v-for="(role, idx) in roles"
                                        :key="idx"
                                        :label="role.Kode_Role"
                                        :value="role.Kode_Role"
                                    />
                                </el-select>
                            </div>
                        </div>

                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Edit Parameter Analisa</h5>
                            </div>

                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Jenis Analisa
                                        <span class="text-danger">*</span>
                                    </label>
                                    <el-select
                                        v-model="form.Id_Jenis_Analisa"
                                        placeholder="-- Pilih Jenis Analisis --"
                                        filterable
                                        style="width: 100%"
                                    >
                                        <el-option
                                            v-for="opt in listJenisAnalisa"
                                            :key="opt.id"
                                            :label="`${
                                                opt.Kode_Analisa ??
                                                'Tidak Ada Data'
                                            } ~ ${opt.Jenis_Analisa ?? '-'} ${
                                                opt.Nama_Mesin
                                                    ? '~ ' + opt.Nama_Mesin
                                                    : ''
                                            }`"
                                            :value="opt.id"
                                        />
                                    </el-select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Parameter Quality
                                        <span class="text-danger">*</span>
                                    </label>
                                    <el-select
                                        v-model="form.Id_Quality_Control"
                                        multiple
                                        collapse-tags
                                        collapse-tags-tooltip
                                        placeholder="-- Pilih Parameter Quality --"
                                        filterable
                                        style="width: 100%"
                                    >
                                        <el-option
                                            v-for="opt in listQualityControl"
                                            :key="opt.Id_QC_Formula"
                                            :label="`${
                                                opt.Keterangan ??
                                                'Tidak Ada Data'
                                            } ~ ${opt.Satuan ?? '-'}`"
                                            :value="opt.Id_QC_Formula"
                                        />
                                    </el-select>
                                </div>

                                <div
                                    v-if="form.Id_Quality_Control.length > 0"
                                    class="alert alert-info mt-4 shadow-sm border-0 bg-info bg-opacity-10"
                                >
                                    <h6
                                        class="alert-heading fw-bold mb-2 text-primary"
                                    >
                                        Visualisasi Tabel Pengujian
                                    </h6>
                                    <p class="mb-3 text-sm text-dark">
                                        Berikut adalah tata letak kolom yang
                                        akan terbentuk pada layar pengujian.
                                        Data tersusun berurutan dari kiri ke
                                        kanan.
                                    </p>

                                    <div
                                        class="table-responsive bg-white rounded shadow-sm border"
                                    >
                                        <table
                                            class="table table-bordered mb-0 text-center align-middle"
                                        >
                                            <thead class="table-light">
                                                <tr>
                                                    <th
                                                        v-for="(
                                                            qcId, idx
                                                        ) in form.Id_Quality_Control"
                                                        :key="'th-' + qcId"
                                                        class="text-nowrap"
                                                    >
                                                        <span
                                                            class="badge bg-secondary mb-1"
                                                            >Posisi
                                                            {{ idx + 1 }}</span
                                                        >
                                                        <br />Input Manual
                                                    </th>
                                                    <th
                                                        v-if="
                                                            checkFlagPerhitungan(
                                                                form.Id_Jenis_Analisa
                                                            )
                                                        "
                                                        class="text-nowrap bg-warning bg-opacity-25"
                                                    >
                                                        <span
                                                            class="badge bg-dark mb-1"
                                                            >Posisi Akhir</span
                                                        >
                                                        <br />Hasil Perhitungan
                                                        (Otomatis)
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td
                                                        v-for="qcId in form.Id_Quality_Control"
                                                        :key="'td-' + qcId"
                                                        class="fw-semibold text-primary"
                                                    >
                                                        {{ getQcLabel(qcId) }}
                                                    </td>
                                                    <td
                                                        v-if="
                                                            checkFlagPerhitungan(
                                                                form.Id_Jenis_Analisa
                                                            )
                                                        "
                                                        class="fw-bold text-success bg-warning bg-opacity-10"
                                                    >
                                                        [Nilai Kalkulasi]
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div
                                    v-if="form.Id_Quality_Control.length > 0"
                                    class="mt-4 p-3 bg-light rounded"
                                >
                                    <h6 class="fw-bold mb-3">
                                        Detail Keterangan Quality Control:
                                    </h6>
                                    <div
                                        v-for="qcId in form.Id_Quality_Control"
                                        :key="qcId"
                                        class="mb-3 ps-3 border-start border-3 border-primary"
                                    >
                                        <label
                                            class="form-label fw-semibold text-primary"
                                        >
                                            Posisi
                                            {{
                                                form.Id_Quality_Control.indexOf(
                                                    qcId
                                                ) + 1
                                            }}
                                            ~ {{ getQcLabel(qcId) }}
                                        </label>
                                        <textarea
                                            v-model="form.Keterangans[qcId]"
                                            rows="2"
                                            placeholder="Masukan Keterangan (Opsional)"
                                            class="form-control"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mb-4">
                            <button
                                type="submit"
                                class="btn btn-success px-5"
                                :disabled="isLoading || isFetchingData"
                            >
                                {{
                                    isLoading
                                        ? "Menyimpan Perubahan..."
                                        : "Update Data"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { ElMessage, ElSelect, ElOption, vLoading } from "element-plus";

export default {
    components: {
        ElSelect,
        ElOption,
    },
    directives: {
        loading: vLoading,
    },
    props: {
        roles: {
            type: Array,
            default: () => [],
        },
        id: {
            type: String,
            required: true,
        },
    },
    data() {
        return {
            isFetchingData: true, // Set true di awal agar langsung loading
            isLoading: false,
            selectedRole: "",
            listJenisAnalisa: [],
            listQualityControl: [],
            form: {
                Id_Jenis_Analisa: "",
                Id_Quality_Control: [],
                Keterangans: {},
            },
        };
    },
    async mounted() {
        try {
            // Gunakan Promise.all agar fetch master data berjalan paralel dan lebih cepat
            await Promise.all([
                this.fetchJenisAnalisa(),
                this.fetchQualityControl(),
            ]);

            // Fetch data edit (yang spesifik) dijalankan setelah master data siap
            await this.fetchEditData();
        } finally {
            // Matikan loading spinner terlepas dari sukses/gagal
            this.isFetchingData = false;
        }
    },
    methods: {
        getQcLabel(id) {
            const qc = this.listQualityControl.find(
                (opt) => opt.Id_QC_Formula === id
            );
            return qc
                ? `${qc.Keterangan ?? "Tidak Ada Data"} ~ ${qc.Satuan ?? "-"}`
                : "Data Tidak Ditemukan";
        },
        checkFlagPerhitungan(idJenisAnalisa) {
            if (!idJenisAnalisa) return false;
            const analisa = this.listJenisAnalisa.find(
                (opt) => opt.id === idJenisAnalisa
            );
            return analisa && analisa.Flag_Perhitungan === "Y";
        },
        async fetchJenisAnalisa() {
            try {
                const response = await axios.get(
                    "/api/v1/binding-jenis-analisa/option/jenis-analisa"
                );
                this.listJenisAnalisa = response.data.result || response.data;
            } catch (error) {
                ElMessage.error("Gagal mengambil data Jenis Analisa");
            }
        },
        async fetchQualityControl() {
            try {
                const response = await axios.get(
                    "/api/v1/binding-jenis-analisa/option/quality-control"
                );
                this.listQualityControl = response.data.result || response.data;
            } catch (error) {
                ElMessage.error("Gagal mengambil data Quality Control");
            }
        },
        async fetchEditData() {
            try {
                const response = await axios.get(
                    `/api/v1/binding-jenis-analisa/by/${this.id}`
                );
                const data = response.data.result || response.data;

                this.selectedRole = data.Kode_Role;
                this.form.Id_Jenis_Analisa = data.Id_Jenis_Analisa;
                this.form.Id_Quality_Control = data.Id_Quality_Control;
                this.form.Keterangans = data.Keterangans;
            } catch (error) {
                ElMessage.error("Gagal memuat data eksisting untuk diedit");
            }
        },
        async submitForm() {
            if (this.roles.length > 1 && !this.selectedRole) {
                ElMessage.error("Pilih Penempatan / Role terlebih dahulu!");
                return;
            }

            if (
                !this.form.Id_Jenis_Analisa ||
                this.form.Id_Quality_Control.length === 0
            ) {
                ElMessage.error(
                    "Lengkapi Jenis Analisa dan Parameter Quality!"
                );
                return;
            }

            this.isLoading = true;
            try {
                const payload = {
                    Kode_Role: this.selectedRole,
                    Id_Jenis_Analisa: this.form.Id_Jenis_Analisa,
                    Id_Quality_Control: this.form.Id_Quality_Control,
                    Keterangans: this.form.Keterangans,
                };

                const response = await axios.put(
                    `/api/v1/binding-jenis-analisa/update/${this.id}`,
                    payload
                );

                ElMessage.success(
                    response.data.message || "Data Berhasil Diperbarui"
                );

                window.location.href = "/binding-jenis-analisa";
            } catch (error) {
                const errMsg =
                    error.response?.data?.message ||
                    "Terjadi kesalahan pada server";
                ElMessage.error(errMsg);
            } finally {
                this.isLoading = false;
            }
        },
    },
};
</script>

<style>
.card {
    border-radius: 12px;
    overflow: hidden;
}

.divider {
    height: 2px;
    background: linear-gradient(
        90deg,
        rgba(13, 110, 253, 0.1) 0%,
        rgba(13, 110, 253, 0.5) 50%,
        rgba(13, 110, 253, 0.1) 100%
    );
}

.form-control,
.form-control-lg {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.btn {
    border-radius: 8px;
    transition: all 0.3s ease;
    font-weight: 500;
}

@keyframes shake {
    0% {
        transform: translateX(0);
    }

    20% {
        transform: translateX(-5px);
    }

    40% {
        transform: translateX(5px);
    }

    60% {
        transform: translateX(-5px);
    }

    80% {
        transform: translateX(5px);
    }

    100% {
        transform: translateX(0);
    }
}

.shake {
    animation: shake 0.3s ease-in-out;
}

@media (max-width: 767.98px) {
    .card-body {
        padding: 1.5rem !important;
    }

    .btn {
        padding: 0.5rem !important;
        font-size: 0.875rem;
    }

    .preview-image {
        max-height: 300px;
    }
}
</style>
