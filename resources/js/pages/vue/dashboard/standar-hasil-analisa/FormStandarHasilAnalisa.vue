<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Tambah
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Jenis Analisa Pada LAB PT. EVO MANUFACTURING INDONESIA
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-lg-12">
                    <div class="row g-3 mb-4" v-if="!mode">
                        <div class="col-md-6">
                            <div
                                class="card h-100 border-0 shadow-sm cursor-pointer hover-card"
                                style="
                                    background: linear-gradient(
                                        135deg,
                                        #e3f2fd 0%,
                                        #ffffff 100%
                                    );
                                    border-left: 5px solid #0d6efd !important;
                                "
                                @click="selectMode('perhitungan')"
                            >
                                <div class="card-body p-4 text-center">
                                    <div class="mb-3 text-primary">
                                        <i class="fas fa-calculator fa-3x"></i>
                                    </div>
                                    <h5 class="fw-bold text-primary">
                                        Analisa Perhitungan
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        Input standar untuk analisa yang
                                        menggunakan rumus, range angka
                                        (min-max), dan kalkulasi mesin.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div
                                class="card h-100 border-0 shadow-sm cursor-pointer hover-card"
                                style="
                                    background: linear-gradient(
                                        135deg,
                                        #e8f5e9 0%,
                                        #ffffff 100%
                                    );
                                    border-left: 5px solid #198754 !important;
                                "
                                @click="selectMode('non')"
                            >
                                <div class="card-body p-4 text-center">
                                    <div class="mb-3 text-success">
                                        <i class="fas fa-poll-h fa-3x"></i>
                                    </div>
                                    <h5 class="fw-bold text-success">
                                        Analisa Non Perhitungan
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        Input standar untuk analisa kualitatif,
                                        deskriptif, atau berdasarkan kriteria
                                        teks (Contoh: Negatif/Positif).
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mb-3">
                        <button
                            class="btn btn-outline-secondary btn-sm mb-3"
                            @click="resetMode"
                        >
                            <i class="fas fa-arrow-left"></i> Kembali ke Pilihan
                        </button>
                        <h6 class="fw-bold text-uppercase border-bottom pb-2">
                            Mode:
                            <span
                                :class="
                                    mode === 'perhitungan'
                                        ? 'text-primary'
                                        : 'text-success'
                                "
                                >{{
                                    mode === "perhitungan"
                                        ? "Perhitungan Angka"
                                        : "Non Perhitungan (Kualitatif)"
                                }}</span
                            >
                        </h6>
                    </div>

                    <form @submit.prevent="saveToDatabase" v-if="mode">
                        <div
                            v-if="roles && roles.length > 1"
                            class="mb-4 p-3 bg-primary bg-opacity-10 border border-primary rounded shadow-sm"
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

                        <div
                            class="mb-3"
                            v-if="loading.loadingOptionJenisAnalisaList"
                        >
                            <div
                                class="d-flex justify-content-center align-content-center"
                            >
                                <div
                                    class="spinner-border text-primary"
                                    role="status"
                                ></div>
                            </div>
                        </div>

                        <div v-else>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label fw-bold">
                                        Jenis Analisa
                                        <span class="text-danger">*</span>
                                    </label>
                                    <el-select
                                        v-model="selectedJenisAnalisaList"
                                        placeholder="--- Pilih Jenis Analisa ---"
                                        class="w-100"
                                        filterable
                                        value-key="value"
                                    >
                                        <el-option
                                            v-for="item in optionsJenisAnalisa"
                                            :key="item.value"
                                            :label="item.name"
                                            :value="item"
                                        />
                                    </el-select>
                                </div>
                            </div>

                            <div v-if="mode === 'perhitungan'">
                                <div class="d-flex justify-content-start mb-3">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        @click="addPerhitunganRow"
                                    >
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Tambah Konfigurasi
                                    </button>
                                </div>

                                <div
                                    v-for="(item, index) in formPerhitunganList"
                                    :key="index"
                                    class="card mb-3 border shadow-sm"
                                >
                                    <div
                                        class="card-header bg-light d-flex justify-content-between align-items-center"
                                    >
                                        <h6 class="fw-bold m-0 text-primary">
                                            Konfigurasi #{{ index + 1 }}
                                        </h6>
                                        <button
                                            v-if="
                                                formPerhitunganList.length > 1
                                            "
                                            type="button"
                                            class="btn btn-sm btn-danger"
                                            @click="removePerhitunganRow(index)"
                                        >
                                            Hapus
                                        </button>
                                    </div>

                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label
                                                    class="form-label fw-semibold"
                                                >
                                                    Nama Mesin
                                                    <span class="text-danger"
                                                        >*</span
                                                    >
                                                </label>
                                                <el-select
                                                    v-model="
                                                        item.selectedMesinList
                                                    "
                                                    placeholder="--- Pilih Mesin ---"
                                                    class="w-100"
                                                    filterable
                                                    clearable
                                                    value-key="value"
                                                    :loading="
                                                        loading.loadingOptionMesinList
                                                    "
                                                    :disabled="
                                                        !selectedJenisAnalisaList
                                                    "
                                                >
                                                    <el-option
                                                        v-for="opt in optionsMesinList"
                                                        :key="opt.value"
                                                        :label="opt.name"
                                                        :value="opt"
                                                    />
                                                </el-select>
                                            </div>

                                            <div class="col-12">
                                                <label
                                                    class="form-label fw-semibold"
                                                >
                                                    Nama Kolom Perhitungan
                                                    <span class="text-danger"
                                                        >*</span
                                                    >
                                                </label>
                                                <el-select
                                                    v-model="
                                                        item.selectedPerhitunganList
                                                    "
                                                    placeholder="--- Pilih Kolom Perhitungan ---"
                                                    class="w-100"
                                                    filterable
                                                    clearable
                                                    value-key="value"
                                                    :loading="
                                                        loading.loadingOptionPerhitunganList
                                                    "
                                                    :disabled="
                                                        !selectedJenisAnalisaList
                                                    "
                                                >
                                                    <el-option
                                                        v-for="opt in optionsPerhitunganList"
                                                        :key="opt.value"
                                                        :label="opt.name"
                                                        :value="opt"
                                                    />
                                                </el-select>
                                            </div>

                                            <div class="col-12">
                                                <label
                                                    class="form-label fw-semibold"
                                                >
                                                    Nama Barang
                                                    <span class="text-danger"
                                                        >*</span
                                                    >
                                                </label>
                                                <el-select
                                                    v-model="
                                                        item.selectedDaftarBarangList
                                                    "
                                                    placeholder="--- Pilih Barang ---"
                                                    class="w-100"
                                                    filterable
                                                    multiple
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    :loading="
                                                        loading.loadingOptionDaftarBarangList
                                                    "
                                                    :disabled="
                                                        !selectedJenisAnalisaList
                                                    "
                                                    @change="
                                                        (val) =>
                                                            handleSmartSelectAll(
                                                                val,
                                                                item
                                                            )
                                                    "
                                                >
                                                    <el-option
                                                        label="-- PILIH SEMUA BARANG --"
                                                        value="ALL"
                                                        class="fw-bold text-primary bg-light"
                                                    />
                                                    <el-option
                                                        v-for="opt in optionsDaftarBarangList"
                                                        :key="opt.value"
                                                        :label="opt.name"
                                                        :value="opt.value"
                                                    />
                                                </el-select>
                                            </div>

                                            <div class="col-md-6">
                                                <label
                                                    class="form-label fw-semibold"
                                                >
                                                    Range Awal
                                                    <span class="text-danger"
                                                        >*</span
                                                    >
                                                </label>
                                                <input
                                                    type="number"
                                                    step="any"
                                                    class="form-control"
                                                    v-model="item.Range_Awal"
                                                    placeholder="0.00"
                                                />
                                            </div>
                                            <div class="col-md-6">
                                                <label
                                                    class="form-label fw-semibold"
                                                >
                                                    Range Akhir
                                                    <span class="text-danger"
                                                        >*</span
                                                    >
                                                </label>
                                                <input
                                                    type="number"
                                                    step="any"
                                                    class="form-control"
                                                    v-model="item.Range_Akhir"
                                                    placeholder="10.00"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid mt-4">
                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-lg"
                                        :disabled="
                                            loading.loadingSaveToDatabase
                                        "
                                    >
                                        <span
                                            v-if="loading.loadingSaveToDatabase"
                                            class="spinner-border spinner-border-sm me-2"
                                        ></span>
                                        {{
                                            loading.loadingSaveToDatabase
                                                ? "Menyimpan..."
                                                : "Simpan Data Perhitungan"
                                        }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="mode === 'non'">
                                <div class="d-flex justify-content-start mb-3">
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        @click="addNonRow"
                                    >
                                        <i class="bi bi-plus-circle me-1"></i>
                                        Tambah Baris
                                    </button>
                                </div>
                                <div
                                    v-for="(item, index) in formNonList"
                                    :key="index"
                                    class="card mb-3 border shadow-sm"
                                >
                                    <div class="card-body">
                                        <div
                                            class="d-flex justify-content-between align-items-center mb-3"
                                        >
                                            <h6 class="fw-bold m-0">
                                                Data Ke-{{ index + 1 }}
                                            </h6>
                                            <button
                                                v-if="formNonList.length > 1"
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                @click="removeNonRow(index)"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                        <div class="row">
                                            <div
                                                class="col-md-12 mb-3"
                                                v-if="isSwitchMode"
                                            >
                                                <label
                                                    class="form-label fw-semibold"
                                                >
                                                    Pilih Kriteria (Switch)
                                                    <span class="text-danger"
                                                        >*</span
                                                    >
                                                </label>
                                                <el-select
                                                    v-model="
                                                        item.Selected_Switch
                                                    "
                                                    placeholder="--- Pilih Kriteria (Switch) ---"
                                                    class="w-100"
                                                    filterable
                                                    value-key="Id_Switch"
                                                    @change="
                                                        (val) =>
                                                            handleSwitchChange(
                                                                val,
                                                                item
                                                            )
                                                    "
                                                    :loading="
                                                        loading.loadingKomponenAnalisa
                                                    "
                                                >
                                                    <el-option
                                                        v-for="opt in optionsSwitch"
                                                        :key="opt.Id_Switch"
                                                        :label="
                                                            opt.Label_Keterangan
                                                        "
                                                        :value="opt"
                                                    />
                                                </el-select>
                                            </div>

                                            <template v-else>
                                                <div class="col-md-6 mb-3">
                                                    <label
                                                        class="form-label fw-semibold"
                                                    >
                                                        Nilai Kriteria (Value)
                                                        <span
                                                            class="text-danger"
                                                            >*</span
                                                        >
                                                    </label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        v-model="
                                                            item.Nilai_Kriteria
                                                        "
                                                        placeholder="Contoh: -999999 atau Text"
                                                    />
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label
                                                        class="form-label fw-semibold"
                                                    >
                                                        Keterangan Kriteria
                                                        (Label)
                                                        <span
                                                            class="text-danger"
                                                            >*</span
                                                        >
                                                    </label>
                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        v-model="
                                                            item.Keterangan_Kriteria
                                                        "
                                                        placeholder="Contoh: Negatif / Positif"
                                                    />
                                                </div>
                                            </template>
                                        </div>
                                        <div class="mb-3">
                                            <label
                                                class="form-label fw-semibold"
                                            >
                                                Status Kelayakan
                                                <span class="text-danger"
                                                    >*</span
                                                >
                                            </label>
                                            <div class="d-flex gap-4 mt-1">
                                                <div class="form-check">
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        :name="
                                                            'Flag_Layak_' +
                                                            index
                                                        "
                                                        :id="'layak_y_' + index"
                                                        value="Y"
                                                        v-model="
                                                            item.Flag_Layak
                                                        "
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        :for="
                                                            'layak_y_' + index
                                                        "
                                                    >
                                                        <span
                                                            class="badge bg-success-subtle text-success border border-success px-3 py-1"
                                                            >LAYAK (Y)</span
                                                        >
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        :name="
                                                            'Flag_Layak_' +
                                                            index
                                                        "
                                                        :id="'layak_t_' + index"
                                                        value="T"
                                                        v-model="
                                                            item.Flag_Layak
                                                        "
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        :for="
                                                            'layak_t_' + index
                                                        "
                                                    >
                                                        <span
                                                            class="badge bg-danger-subtle text-danger border border-danger px-3 py-1"
                                                            >TIDAK LAYAK
                                                            (T)</span
                                                        >
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid mt-4">
                                    <button
                                        type="submit"
                                        class="btn btn-success btn-lg"
                                        :disabled="
                                            loading.loadingSaveToDatabase
                                        "
                                    >
                                        <span
                                            v-if="loading.loadingSaveToDatabase"
                                            class="spinner-border spinner-border-sm me-2"
                                        ></span>
                                        {{
                                            loading.loadingSaveToDatabase
                                                ? "Menyimpan..."
                                                : "Simpan Data Non-Hitung"
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import Swal from "sweetalert2";
import { ElMessage, ElSelect, ElOption } from "element-plus";

export default {
    components: { ElSelect, ElOption },
    props: {
        roles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            mode: null,
            selectedRole: "",
            selectedJenisAnalisaList: null,
            optionsJenisAnalisa: [],
            optionsDaftarBarangList: [],
            optionsMesinList: [],
            optionsPerhitunganList: [],
            isSwitchMode: false,
            optionsSwitch: [],
            loading: {
                loadingOptionJenisAnalisaList: false,
                loadingOptionMesinList: false,
                loadingOptionDaftarBarangList: false,
                loadingOptionPerhitunganList: false,
                loadingSaveToDatabase: false,
                loadingKomponenAnalisa: false,
            },
            formPerhitunganList: [
                {
                    selectedMesinList: null,
                    selectedPerhitunganList: null,
                    selectedDaftarBarangList: [],
                    Range_Awal: "",
                    Range_Akhir: "",
                },
            ],
            formNonList: [
                {
                    Nilai_Kriteria: "",
                    Keterangan_Kriteria: "",
                    Flag_Layak: "",
                    Selected_Switch: null,
                },
            ],
        };
    },
    watch: {
        selectedJenisAnalisaList(newVal) {
            if (newVal && newVal.value) {
                this.fetchDaftarBarang();
                if (this.mode === "perhitungan") {
                    this.fetchDaftarNamaPerhitungan();
                    this.fetchDaftarMesin();
                } else if (this.mode === "non") {
                    this.fetchKomponenAnalisa(newVal.value);
                }
            } else {
                this.resetDependentOptions();
            }
        },
    },
    methods: {
        async fetchKomponenAnalisa(idJenisAnalisa) {
            this.loading.loadingKomponenAnalisa = true;
            this.isSwitchMode = false;
            this.optionsSwitch = [];

            this.formNonList = [
                {
                    Nilai_Kriteria: "",
                    Keterangan_Kriteria: "",
                    Flag_Layak: "",
                    Selected_Switch: null,
                },
            ];

            try {
                const response = await axios.get(
                    `/api/v1/jenis-analisa/komponen/${idJenisAnalisa}`
                );
                if (response.status === 200 && response.data?.success) {
                    this.isSwitchMode = response.data.is_switch;
                    this.optionsSwitch = response.data.options || [];
                }
            } catch (error) {
                console.error("Gagal mengambil komponen analisa", error);
            } finally {
                this.loading.loadingKomponenAnalisa = false;
            }
        },
        selectMode(selectedMode) {
            this.mode = selectedMode;
            this.resetForm();
            this.fetchJenisAnalisaList();
        },
        handleSwitchChange(val, item) {
            if (val) {
                // Mapping otomatis ke kolom database yang sesungguhnya
                item.Nilai_Kriteria = val.Keterangan;
                item.Keterangan_Kriteria = val.Label_Keterangan;
            } else {
                item.Nilai_Kriteria = "";
                item.Keterangan_Kriteria = "";
            }
        },
        resetMode() {
            this.mode = null;
            this.resetForm();
        },
        resetForm() {
            this.selectedJenisAnalisaList = null;
            this.resetDependentOptions();
            this.formPerhitunganList = [
                {
                    selectedMesinList: null,
                    selectedPerhitunganList: null,
                    selectedDaftarBarangList: [],
                    Range_Awal: "",
                    Range_Akhir: "",
                },
            ];
            this.isSwitchMode = false;
            this.formNonList = [
                {
                    Nilai_Kriteria: "",
                    Keterangan_Kriteria: "",
                    Flag_Layak: "",
                    Selected_Switch: null,
                },
            ];
        },
        resetDependentOptions() {
            this.optionsPerhitunganList = [];
            this.optionsDaftarBarangList = [];
            this.optionsMesinList = [];
        },
        addPerhitunganRow() {
            this.formPerhitunganList.push({
                selectedMesinList: null,
                selectedPerhitunganList: null,
                selectedDaftarBarangList: [],
                Range_Awal: "",
                Range_Akhir: "",
            });
        },
        removePerhitunganRow(index) {
            if (this.formPerhitunganList.length > 1) {
                this.formPerhitunganList.splice(index, 1);
            }
        },
        addNonRow() {
            this.formNonList.push({
                Nilai_Kriteria: "",
                Keterangan_Kriteria: "",
                Flag_Layak: "",
                Selected_Switch: null,
            });
        },
        removeNonRow(index) {
            if (this.formNonList.length > 1) {
                this.formNonList.splice(index, 1);
            }
        },
        handleSmartSelectAll(val, item) {
            const isSelectAllSelected = val.includes("ALL");
            if (isSelectAllSelected) {
                const allValues = this.optionsDaftarBarangList.map(
                    (opt) => opt.value
                );
                item.selectedDaftarBarangList = allValues;
                ElMessage.info("Semua Barang Telah Dipilih");
            }
        },
        async fetchJenisAnalisaList() {
            this.loading.loadingOptionJenisAnalisaList = true;
            try {
                const typeParam =
                    this.mode === "non" ? "non-perhitungan" : "perhitungan";
                const response = await axios.get(
                    `/api/v1/jenis-analisa/standar?type=${typeParam}`
                );
                if (response.status === 200 && response.data?.result) {
                    this.optionsJenisAnalisa = response.data.result.map(
                        (item) => ({
                            value: item.id,
                            name: `${item.Kode_Analisa} ~ ${item.Jenis_Analisa}`,
                        })
                    );
                } else {
                    this.optionsJenisAnalisa = [];
                }
            } catch (error) {
                this.optionsJenisAnalisa = [];
            } finally {
                this.loading.loadingOptionJenisAnalisaList = false;
            }
        },
        async fetchDaftarBarang() {
            if (!this.selectedJenisAnalisaList) return;
            this.loading.loadingOptionDaftarBarangList = true;
            try {
                const response = await axios.get(
                    `/api/v1/daftar-barang/standar/${this.selectedJenisAnalisaList.value}`
                );
                this.optionsDaftarBarangList =
                    response.data?.result?.map((item) => ({
                        value: item.Kode_Barang,
                        name: `${item.Kode_Barang} ~ ${item.Nama}`,
                    })) || [];
            } catch (e) {
                this.optionsDaftarBarangList = [];
            } finally {
                this.loading.loadingOptionDaftarBarangList = false;
            }
        },
        async fetchDaftarMesin() {
            if (!this.selectedJenisAnalisaList) return;
            this.loading.loadingOptionMesinList = true;
            try {
                const response = await axios.get(
                    `/api/v1/list-mesin/standar/${this.selectedJenisAnalisaList.value}`
                );
                this.optionsMesinList =
                    response.data?.result?.map((item) => ({
                        value: item.Id_Master_Mesin,
                        name: `${item.Nama_Mesin} ~ ${item.Seri_Mesin}`,
                    })) || [];
            } catch (e) {
                this.optionsMesinList = [];
            } finally {
                this.loading.loadingOptionMesinList = false;
            }
        },
        async fetchDaftarNamaPerhitungan() {
            if (!this.selectedJenisAnalisaList) return;
            this.loading.loadingOptionPerhitunganList = true;
            try {
                const response = await axios.get(
                    `/api/v1/list-kolom-perhitungan/standar/${this.selectedJenisAnalisaList.value}`
                );
                this.optionsPerhitunganList =
                    response.data?.result?.map((item) => ({
                        value: item.id,
                        name: item.Nama_Kolom,
                    })) || [];
            } catch (e) {
                this.optionsPerhitunganList = [];
            } finally {
                this.loading.loadingOptionPerhitunganList = false;
            }
        },
        async saveToDatabase() {
            if (this.roles.length > 1 && !this.selectedRole) {
                this.showAlert(
                    "warning",
                    "Peringatan",
                    "Pilih Penempatan / Role terlebih dahulu!"
                );
                return;
            }

            this.loading.loadingSaveToDatabase = true;
            if (!this.selectedJenisAnalisaList?.value) {
                this.showAlert("warning", "Harap pilih Jenis Analisa!");
                this.loading.loadingSaveToDatabase = false;
                return;
            }

            const payload = {
                Kode_Role: this.selectedRole,
                Mode: this.mode,
                Id_Jenis_Analisa: this.selectedJenisAnalisaList.value,
            };

            if (this.mode === "perhitungan") {
                const invalid = this.formPerhitunganList.some(
                    (item) =>
                        !item.selectedMesinList?.value ||
                        !item.selectedPerhitunganList?.value ||
                        !item.selectedDaftarBarangList ||
                        item.selectedDaftarBarangList.length === 0 ||
                        item.Range_Awal === "" ||
                        item.Range_Akhir === ""
                );
                if (invalid) {
                    this.showAlert(
                        "warning",
                        "Harap lengkapi semua data baris Perhitungan (Termasuk Barang)!"
                    );
                    this.loading.loadingSaveToDatabase = false;
                    return;
                }
                payload.Items_Perhitungan = this.formPerhitunganList.map(
                    (item) => ({
                        Id_Master_Mesin: item.selectedMesinList.value,
                        Id_Perhitungan: item.selectedPerhitunganList.value,
                        Kode_Barang: item.selectedDaftarBarangList,
                        Range_Awal: item.Range_Awal,
                        Range_Akhir: item.Range_Akhir,
                    })
                );
            } else {
                const invalid = this.formNonList.some(
                    (item) =>
                        !item.Nilai_Kriteria ||
                        !item.Keterangan_Kriteria ||
                        !item.Flag_Layak
                );
                if (invalid) {
                    this.showAlert(
                        "warning",
                        "Harap lengkapi semua baris data Non-Perhitungan!"
                    );
                    this.loading.loadingSaveToDatabase = false;
                    return;
                }
                payload.Items_Non = this.formNonList;
            }

            try {
                const response = await axios.post(
                    "/api/v1/standar-hasil-analisa/store",
                    payload
                );
                if (response.status === 201) {
                    Swal.fire(
                        "Berhasil",
                        response.data.message,
                        "success"
                    ).then(() => {
                        window.location.reload();
                    });
                }
            } catch (error) {
                if (error.response?.status === 409) {
                    this.showAlert(
                        "warning",
                        "Data Duplikat",
                        error.response.data.message
                    );
                } else {
                    this.showAlert(
                        "error",
                        "Gagal",
                        "Terjadi kesalahan sistem."
                    );
                }
            } finally {
                this.loading.loadingSaveToDatabase = false;
            }
        },
        showAlert(icon, title, text = "") {
            Swal.fire({ icon, title, text });
        },
    },
};
</script>

<style>
.hover-card:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}
.cursor-pointer {
    cursor: pointer;
}

.vs__dropdown-menu {
    max-height: 100px !important;
    overflow-y: auto !important;
}

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
