<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Edit
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Jenis Analisa Pada LAB PT. EVO MANUFACTURING INDONESIA
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-lg-12">
                    <div class="row">
                        <div
                            class="col-md-3 mb-3"
                            v-if="roles && roles.length > 1"
                        >
                            <label
                                for="Kode_Role"
                                class="form-label fw-semibold"
                            >
                                Role / Penempatan
                                <span class="text-danger">*</span>
                            </label>
                            <el-select
                                v-model="form.Kode_Role"
                                placeholder="--- Pilih Role ---"
                                style="width: 100%"
                                filterable
                            >
                                <el-option
                                    v-for="(role, index) in roles"
                                    :key="index"
                                    :label="role.Nama_Role || role.Kode_Role"
                                    :value="role.Kode_Role"
                                />
                            </el-select>
                        </div>

                        <div
                            :class="
                                roles && roles.length > 1
                                    ? 'col-md-3'
                                    : 'col-md-4'
                            "
                            class="mb-3"
                        >
                            <label
                                for="Id_Jenis_Analisa"
                                class="form-label fw-semibold"
                            >
                                Jenis Analisa
                                <span class="text-danger">*</span>
                            </label>
                            <v-select
                                v-if="options && options.length"
                                v-model="selectedOption"
                                :options="options"
                                label="name"
                                placeholder="--- Pilih Jenis Analisa ---"
                            />
                        </div>

                        <div
                            :class="
                                roles && roles.length > 1
                                    ? 'col-md-3'
                                    : 'col-md-4'
                            "
                            class="mb-3"
                        >
                            <label
                                for="Nama_Kolom"
                                class="form-label fw-semibold"
                            >
                                Nama Kolom Perhitungan
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="Nama_Kolom"
                                id="Nama_Kolom"
                                class="form-control"
                                placeholder="Masukan Nama Kolom perhitungan..."
                                v-model="form.Nama_Kolom"
                            />
                        </div>

                        <div
                            :class="
                                roles && roles.length > 1
                                    ? 'col-md-3'
                                    : 'col-md-4'
                            "
                            class="mb-3"
                        >
                            <label for="digit" class="form-label fw-semibold">
                                Digit Belakang Koma
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                type="number"
                                name="digit"
                                id="digit"
                                class="form-control"
                                placeholder="Contoh: 2"
                                v-model="form.Hasil_Perhitungan"
                            />
                        </div>
                    </div>

                    <hr />
                    <div class="row">
                        <label for="Rumus" class="form-label fw-semibold">
                            Rumus Perhitungan
                            <span class="text-danger">*</span>
                        </label>

                        <div class="mt-2">
                            <div
                                class="alert alert-info d-flex align-items-center gap-2"
                                role="alert"
                            >
                                <i class="ri-information-line fs-4"></i>
                                <div>
                                    <strong>Catatan Penting:</strong
                                    ><br /><br />
                                    <strong
                                        >Petunjuk Saat Mengedit Rumus:</strong
                                    ><br />
                                    Sebelum Anda mulai mengedit rumus pada
                                    halaman ini, sangat disarankan untuk menekan
                                    tombol <strong>"Bersihkan"</strong> terlebih
                                    dahulu. Hal ini bertujuan untuk menghapus
                                    data yang tersimpan sementara (cache) dan
                                    menghindari konflik data lama dengan yang
                                    baru. <br /><br />
                                    Dengan membersihkan terlebih dahulu, Anda
                                    akan mendapatkan form yang benar-benar
                                    kosong sehingga proses pengeditan rumus
                                    dapat berjalan lebih lancar, akurat, dan
                                    sesuai dengan hasil yang Anda harapkan.
                                    <br /><br />
                                    Terima kasih atas perhatian dan kerja
                                    samanya.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="qc-parameter-container">
                                <div class="qc-parameter-section">
                                    <div class="section-header">
                                        <h5 class="section-title">
                                            <i
                                                class="fas fa-clipboard-check"
                                            ></i>
                                            Quality Control Parameters
                                        </h5>
                                    </div>

                                    <div
                                        v-if="loading.dataQualityControl"
                                        class="loading-overlay"
                                    >
                                        <i class="fas fa-spinner fa-spin"></i>
                                        Loading parameters...
                                    </div>

                                    <div
                                        class="scrollable-parameter-grid"
                                        v-if="!loading.dataQualityControl"
                                    >
                                        <div
                                            v-if="
                                                !loading.dataQualityControl &&
                                                !parameterRequested
                                            "
                                            class="alert alert-info mt-2"
                                        >
                                            <i class="fas fa-info-circle"></i>
                                            Silahkan Pilih Jenis Analisa
                                            Terlebih Dahulu.
                                        </div>

                                        <div
                                            v-if="
                                                !loading.dataQualityControl &&
                                                parameterRequested &&
                                                parameterValues.length === 0
                                            "
                                            class="alert alert-warning mt-2"
                                        >
                                            <i
                                                class="fas fa-exclamation-circle"
                                            ></i>
                                            Parameter Dari Jenis Analisa Tidak
                                            Ditemukan.
                                        </div>

                                        <div class="parameter-grid">
                                            <div
                                                class="parameter-card"
                                                v-for="(
                                                    item, index
                                                ) in parameterValues"
                                                :key="index"
                                                @click="
                                                    addParameter(
                                                        item.id,
                                                        item.Keterangan
                                                    )
                                                "
                                                :class="{
                                                    'active-parameter':
                                                        selectedParam ===
                                                        item.id,
                                                }"
                                            >
                                                <div class="parameter-icon">
                                                    <i
                                                        class="fas"
                                                        :class="
                                                            getParameterIcon(
                                                                item.Kategori
                                                            )
                                                        "
                                                    ></i>
                                                </div>
                                                <div class="parameter-content">
                                                    <div
                                                        class="parameter-value"
                                                    >
                                                        {{ item.Keterangan }}
                                                    </div>
                                                    <div class="parameter-meta">
                                                        <span
                                                            class="parameter-label"
                                                        >
                                                            <i
                                                                class="fas fa-ruler"
                                                            ></i>
                                                            {{ item.Satuan }}
                                                        </span>
                                                        <span
                                                            class="parameter-badge"
                                                            v-if="item.isNew"
                                                        >
                                                            <i
                                                                class="fas fa-star"
                                                            ></i>
                                                            New
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="parameter-action">
                                                    <i
                                                        class="fas fa-plus-circle"
                                                    ></i>
                                                </div>
                                                <div
                                                    class="parameter-hover-effect"
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="section-footer">
                                        <span class="item-count"
                                            >{{
                                                parameterValues.length
                                            }}
                                            parameters available</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="formula-builder">
                                <div class="formula-header">
                                    <h3 class="formula-title">
                                        <i class="fas fa-square-root-alt"></i>
                                        Pembuat Rumus
                                    </h3>
                                </div>

                                <div class="formula-display-container">
                                    <div class="formula-preview">
                                        {{
                                            formulaText ||
                                            "[Rumus akan muncul di sini]"
                                        }}
                                    </div>
                                </div>

                                <div class="formula-controls">
                                    <div class="control-group">
                                        <h6>Operator Matematika</h6>
                                        <div class="operator-buttons">
                                            <button
                                                class="btn-control btn-operator"
                                                v-for="op in [
                                                    {
                                                        display: '+',
                                                        value: '+',
                                                    },
                                                    {
                                                        display: '-',
                                                        value: '-',
                                                    },
                                                    {
                                                        display: '×',
                                                        value: '*',
                                                    },
                                                    {
                                                        display: '÷',
                                                        value: '/',
                                                    },
                                                    {
                                                        display: '%',
                                                        value: '%',
                                                    },
                                                ]"
                                                :key="op.value"
                                                @click="
                                                    addOperator(
                                                        op.value,
                                                        op.display
                                                    )
                                                "
                                            >
                                                {{ op.display }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="control-group">
                                        <h6>Fungsi & Simbol</h6>
                                        <div class="function-buttons">
                                            <button
                                                class="btn-control btn-function"
                                                v-for="fn in ['(', ')', '.']"
                                                :key="fn"
                                                @click="
                                                    fn === '(' || fn === ')'
                                                        ? addParenthesis(fn)
                                                        : addDot()
                                                "
                                            >
                                                {{ fn }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="control-group">
                                        <h6>Angka</h6>
                                        <div class="number-buttons">
                                            <button
                                                class="btn-control btn-number"
                                                v-for="num in [
                                                    7, 8, 9, 4, 5, 6, 1, 2, 3,
                                                    0,
                                                ]"
                                                :key="num"
                                                @click="addNumber(num)"
                                            >
                                                {{ num }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="control-group">
                                        <h6>Fungsi Tambahan</h6>
                                        <div class="function-buttons">
                                            <button
                                                class="btn-control btn-function"
                                                @click="addFunction('SUM')"
                                            >
                                                SUM
                                            </button>
                                            <button
                                                class="btn-control btn-function"
                                                @click="addFunction('AVG')"
                                            >
                                                AVG
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="formula-actions">
                                    <button
                                        class="btn-action btn-clear"
                                        @click="clear"
                                    >
                                        <i class="fas fa-eraser"></i> Bersihkan
                                    </button>
                                    <button
                                        class="btn-action btn-backspace"
                                        @click="backspace"
                                    >
                                        <i class="fas fa-backspace"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr />
                    <div class="form-check me-auto mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="modalConfirmCheckbox"
                            v-model="isSubmitDone"
                            required
                        />
                        <label
                            class="form-check-label"
                            for="modalConfirmCheckbox"
                        >
                            Saya telah membaca dan memahami seluruh instruksi
                            pengeditan rumus, termasuk anjuran untuk menekan
                            tombol <strong>"Bersihkan"</strong> sebelum memulai.
                            Saya bertanggung jawab penuh atas hasil pengeditan
                            yang saya lakukan.
                        </label>
                    </div>

                    <div class="d-grid">
                        <button
                            class="btn btn-primary"
                            @click="saveToDatabase"
                            :disabled="loading.saveToDatabase || !isSubmitDone"
                        >
                            {{
                                loading.saveToDatabase
                                    ? "Loading..."
                                    : "Edit Data"
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import vSelect from "vue-select";
import Swal from "sweetalert2";
import { ElMessage, ElSelect, ElOption } from "element-plus";

export default {
    components: {
        vSelect,
        ElMessage,
        ElSelect,
        ElOption,
    },
    props: {
        getData: {
            type: Object,
            default: () => null,
        },
        roles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            selectedParam: null,
            selectedOption: null,
            parameterRequested: false,
            parameterValues: [],
            parameterTemplate: [],
            formulaTokens: [],
            isSubmitDone: false,
            options: [],
            loading: {
                dataJenisAnalisa: false,
                dataQualityControl: false,
                saveToDatabase: false,
            },
            form: {
                Kode_Role: null,
                Nama_Kolom: "",
                Hasil_Perhitungan: "",
            },
        };
    },
    computed: {
        formulaText() {
            if (this.formulaTokens.length === 0) {
                return "[Rumus akan muncul di sini]";
            }
            let text = "";
            for (let i = 0; i < this.formulaTokens.length; i++) {
                const token = this.formulaTokens[i];
                const prevToken = i > 0 ? this.formulaTokens[i - 1] : null;

                if (
                    i > 0 &&
                    token.type !== ")" &&
                    token.type !== "comma" &&
                    prevToken.type !== "(" &&
                    prevToken.type !== "function("
                ) {
                    text += " ";
                }
                text += token.display;
            }
            return text;
        },
        rawFormula() {
            return this.formulaTokens.map((token) => token.code).join("");
        },
    },
    methods: {
        async fetchDataJenisAnalisa() {
            this.loading.dataJenisAnalisa = true;
            try {
                const response = await axios.get("/jenis-analisa/for-select");
                if (response.status === 200 && response.data?.result) {
                    this.options = response.data.result.map((item) => ({
                        value: item.id,
                        name: `${item.Kode_Analisa ?? "Tidak Ada Data"} ~ ${
                            item.Jenis_Analisa ?? "-"
                        }${item.Nama_Mesin ? " ~ " + item.Nama_Mesin : ""}`,
                    }));
                } else {
                    this.options = [];
                }
            } catch (error) {
                this.options = [];
            } finally {
                this.loading.dataJenisAnalisa = false;
            }
        },
        async fetchDataQualityControl(idJenisAnalisa) {
            this.loading.dataQualityControl = true;
            this.parameterRequested = true;
            try {
                const response = await axios.get(
                    `/jenis-analisa/qc-for-select/${idJenisAnalisa}`
                );
                if (response.status === 200 && response.data?.result) {
                    this.parameterTemplate = response.data.result;
                    this.parameterValues = this.parameterTemplate.map(
                        (item) => ({
                            Keterangan:
                                item.keterangan || "KETERANGAN TIDAK ADA",
                            Satuan: item.satuan || "%",
                            id: item.Id_Quality_Control,
                            Kategori: item.Kategori,
                        })
                    );
                } else {
                    this.parameterTemplate = [];
                    this.parameterValues = [];
                }
            } catch (error) {
                this.parameterTemplate = [];
                this.parameterValues = [];
            } finally {
                this.loading.dataQualityControl = false;
            }
        },
        async saveToDatabase() {
            this.loading.saveToDatabase = true;
            try {
                if (
                    this.roles &&
                    this.roles.length > 1 &&
                    !this.form.Kode_Role
                ) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Role / Penempatan harus dipilih.",
                    });
                    this.loading.saveToDatabase = false;
                    return;
                }

                if (
                    !this.selectedOption?.value ||
                    !this.rawFormula ||
                    !this.form.Nama_Kolom ||
                    !this.form.Hasil_Perhitungan
                ) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Data yang dipilih tidak lengkap atau rumus kosong.",
                    });
                    this.loading.saveToDatabase = false;
                    return;
                }

                const payload = {
                    Kode_Role: this.form.Kode_Role,
                    Id_Jenis_Analisa: this.selectedOption.value,
                    Rumus: this.rawFormula,
                    Nama_Kolom: this.form.Nama_Kolom,
                    Hasil_Perhitungan: this.form.Hasil_Perhitungan,
                };

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                const response = await axios.put(
                    `/perhitungan/rumus/update/${this.getData.id}`,
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                if (response.status === 200 && response.data) {
                    await Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: "Data berhasil diperbarui!",
                    });
                    window.location.href = "/perhitungan-rumus";
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Gagal memperbarui data.",
                    });
                }
            } catch (error) {
                console.error("Error updating data:", error);
                Swal.fire({
                    icon: "error",
                    title: "Oops...",
                    text:
                        error.response?.data?.message ||
                        error.message ||
                        "Terjadi Kesalahan",
                });
            } finally {
                this.loading.saveToDatabase = false;
            }
        },

        getLastToken() {
            return this.formulaTokens.length > 0
                ? this.formulaTokens[this.formulaTokens.length - 1]
                : null;
        },
        isOperand(token) {
            if (!token) return false;
            return ["parameter", "number", ")"].includes(token.type);
        },
        addNumber(num) {
            const lastToken = this.getLastToken();
            if (!lastToken) {
                this.formulaTokens.push({
                    display: String(num),
                    code: String(num),
                    type: "number",
                });
                return;
            }
            if (lastToken.type === "number") {
                lastToken.display += String(num);
                lastToken.code += String(num);
                return;
            }
            if (["parameter", ")"].includes(lastToken.type)) {
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 3000,
                    icon: "warning",
                    title: "Masukkan operator terlebih dahulu!",
                });
                return;
            }
            this.formulaTokens.push({
                display: String(num),
                code: String(num),
                type: "number",
            });
        },
        addOperator(value, displayValue = value) {
            const lastToken = this.getLastToken();
            if (
                !lastToken ||
                ["operator", "(", "function("].includes(lastToken.type)
            ) {
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 3000,
                    icon: "warning",
                    title: "Pilih parameter atau angka dulu!",
                });
                return;
            }
            this.formulaTokens.push({
                display: displayValue,
                code: value,
                type: "operator",
            });
        },
        addDot() {
            const lastToken = this.getLastToken();
            if (!lastToken) {
                this.formulaTokens.push({
                    display: "0.",
                    code: "0.",
                    type: "number",
                });
                return;
            }
            if (lastToken.type === "number") {
                if (lastToken.code.includes(".")) {
                    Swal.fire({
                        toast: true,
                        position: "top-end",
                        showConfirmButton: false,
                        timer: 3000,
                        icon: "warning",
                        title: "Angka sudah memiliki titik desimal.",
                    });
                    return;
                }
                lastToken.display += ".";
                lastToken.code += ".";
                return;
            }
            this.formulaTokens.push({
                display: "0.",
                code: "0.",
                type: "number",
            });
        },
        addParameter(id, name) {
            const lastToken = this.getLastToken();
            if (this.isOperand(lastToken)) {
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 3000,
                    icon: "warning",
                    title: "Masukkan operator terlebih dahulu!",
                });
                return;
            }
            const unclosedSumOrAvg =
                this.rawFormula.match(/(SUM|AVG)\(([^)]*)$/);
            if (unclosedSumOrAvg) {
                const content = unclosedSumOrAvg[2];
                if (content && !content.endsWith(",")) {
                    this.formulaTokens.push({
                        display: ",",
                        code: ",",
                        type: "comma",
                    });
                }
            }
            this.formulaTokens.push({
                display: name,
                code: `[${id}]`,
                type: "parameter",
            });
        },
        addFunction(functionName) {
            const lastToken = this.getLastToken();
            if (this.isOperand(lastToken)) {
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 3000,
                    icon: "warning",
                    title: "Masukkan operator terlebih dahulu!",
                });
                return;
            }
            this.formulaTokens.push({
                display: `${functionName}(`,
                code: `${functionName}(`,
                type: "function(",
            });
        },
        addParenthesis(paren) {
            this.formulaTokens.push({
                display: paren,
                code: paren,
                type: paren,
            });
        },
        clear() {
            this.formulaTokens = [];
        },
        backspace() {
            this.formulaTokens.pop();
        },
        getParameterIcon(kategori) {
            const iconMap = {
                temperature: "fa-thermometer-half",
                pressure: "fa-tachometer-alt",
                weight: "fa-weight",
                volume: "fa-flask",
                time: "fa-clock",
                dimension: "fa-ruler-combined",
                default: "fa-cube",
            };
            return iconMap[kategori?.toLowerCase()] || iconMap.default;
        },

        initializeFormulaFromRaw(rawFormula) {
            if (!rawFormula || this.parameterValues.length === 0) {
                this.formulaTokens = [];
                return;
            }

            const regex = /\[(\d+)\]|SUM\(|AVG\(|[()+\-*/%,]|\d+(\.\d+)?/g;
            const parts = rawFormula.match(regex);
            if (!parts) return;

            const newTokens = [];
            const operatorDisplayMap = {
                "*": "×",
                "/": "÷",
            };

            for (const part of parts) {
                if (part.startsWith("[")) {
                    const id = part.replace(/\[|\]/g, "");
                    const param = this.parameterValues.find((p) => p.id == id);
                    if (param) {
                        newTokens.push({
                            display: param.Keterangan,
                            code: `[${id}]`,
                            type: "parameter",
                        });
                    }
                } else if (/\d/.test(part)) {
                    newTokens.push({
                        display: part,
                        code: part,
                        type: "number",
                    });
                } else if (["+", "-", "*", "/", "%"].includes(part)) {
                    newTokens.push({
                        display: operatorDisplayMap[part] || part,
                        code: part,
                        type: "operator",
                    });
                } else if (part === "SUM(" || part === "AVG(") {
                    newTokens.push({
                        display: part,
                        code: part,
                        type: "function(",
                    });
                } else if (["(", ")", ","].includes(part)) {
                    newTokens.push({ display: part, code: part, type: part });
                }
            }
            this.formulaTokens = newTokens;
        },
    },
    watch: {
        selectedOption(newVal, oldVal) {
            if (newVal && newVal.value && newVal.value !== oldVal?.value) {
                this.fetchDataQualityControl(newVal.value);
                this.clear();
            }
        },
    },
    mounted() {
        this.fetchDataJenisAnalisa().then(() => {
            if (this.getData) {
                this.form.Kode_Role = this.getData.Kode_Role;
                this.form.Nama_Kolom = this.getData.Nama_Kolom;
                this.form.Hasil_Perhitungan = this.getData.Hasil_Perhitungan;

                this.selectedOption = this.options.find(
                    (opt) =>
                        parseInt(opt.value) ===
                        parseInt(this.getData.Id_Jenis_Analisa)
                );

                if (this.selectedOption) {
                    this.fetchDataQualityControl(
                        this.selectedOption.value
                    ).then(() => {
                        this.initializeFormulaFromRaw(this.getData.Rumus);
                    });
                }
            }
        });
    },
};
</script>

<style scoped>
.vs__dropdown-menu {
    max-height: 100px !important;
    overflow-y: auto !important;
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
.section-title {
    color: #2c3e50;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

.formula-builder {
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
}

.formula-header {
    background: linear-gradient(135deg, #29569f 0%, #3a7bd5 100%);
    color: white;
    padding: 15px 20px;
}

.formula-title {
    margin: 0;
    font-size: 1.3rem;
    color: white;
}

.formula-display-container {
    padding: 20px;
    background-color: #f9f9f9;
    border-bottom: 1px solid #eee;
}

.formula-preview {
    font-size: 1.2rem;
    min-height: 40px;
    color: #2c3e50;
    margin-bottom: 5px;
    white-space: pre-wrap;
}

.formula-raw {
    font-family: monospace;
    color: #7f8c8d;
    font-size: 0.9rem;
    min-height: 20px;
}

.formula-controls {
    padding: 15px;
}

.control-group {
    margin-bottom: 15px;
}

.control-group h6 {
    color: #7f8c8d;
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.operator-buttons,
.function-buttons,
.number-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.btn-control {
    border: none;
    border-radius: 6px;
    padding: 10px 15px;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-operator {
    background-color: #e3f2fd;
    color: #1976d2;
}

.btn-function {
    background-color: #f5f5f5;
    color: #424242;
}

.btn-number {
    background-color: #f0f0f0;
    color: #212121;
}

.btn-control:hover {
    filter: brightness(95%);
}

.formula-actions {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    background-color: #f5f5f5;
    border-top: 1px solid #eee;
}

.btn-action {
    border: none;
    border-radius: 6px;
    padding: 8px 15px;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-clear {
    background-color: #ffebee;
    color: #c62828;
}

.btn-backspace {
    background-color: #fff8e1;
    color: #f57f17;
}

.btn-save {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.btn-action:hover {
    filter: brightness(95%);
}

.qc-parameter-container {
    max-width: 900px;
    margin: 0 auto;
    font-family: "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

.qc-parameter-section {
    background-color: #ffffff;
    border-radius: 14px;
    padding: 20px 20px 10px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
    border: 1px solid #e8ebf0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f3f7;
}

.section-title {
    color: #2c3e50;
    font-size: 1.15rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: #4a90e2;
    font-size: 1.1em;
}

.refresh-btn {
    background: none;
    border: none;
    color: #7f8c8d;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s;
    padding: 5px;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.refresh-btn:hover {
    background-color: #f5f7fa;
    color: #4a90e2;
    transform: rotate(90deg);
}

.scrollable-parameter-grid {
    max-height: 450px;
    overflow-y: auto;
    padding-right: 8px;
    margin-bottom: 15px;
}

/* Scrollbar styling */
.scrollable-parameter-grid::-webkit-scrollbar {
    width: 6px;
}

.scrollable-parameter-grid::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 10px;
}

.scrollable-parameter-grid::-webkit-scrollbar-thumb {
    background: #d1d9e6;
    border-radius: 10px;
}

.scrollable-parameter-grid::-webkit-scrollbar-thumb:hover {
    background: #b8c2d0;
}

.parameter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.parameter-card {
    background-color: white;
    border: 1px solid #e3e9f2;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
    height: 90px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
}

.parameter-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(74, 144, 226, 0.12);
    border-color: #d6e4ff;
}

.parameter-card.active-parameter {
    border-color: #4a90e2;
    background-color: #f8fbff;
}

.parameter-icon {
    width: 40px;
    height: 40px;
    background-color: #f0f7ff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
    color: #4a90e2;
    font-size: 1.1rem;
}

.parameter-content {
    flex-grow: 1;
    min-width: 0;
}

.parameter-value {
    font-weight: 600;
    font-size: 0.95rem;
    color: #2c3e50;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 5px;
}

.parameter-meta {
    display: flex;
    gap: 12px;
    align-items: center;
}

.parameter-label {
    font-size: 0.75rem;
    color: #7f8c8d;
    display: flex;
    align-items: center;
    gap: 4px;
}

.parameter-label i {
    font-size: 0.7rem;
    color: #95a5a6;
}

.parameter-badge {
    font-size: 0.65rem;
    background-color: #fff8e6;
    color: #f39c12;
    padding: 2px 6px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 3px;
}

.parameter-action {
    color: #bdc3c7;
    font-size: 1.1rem;
    transition: all 0.3s;
    margin-left: 10px;
    opacity: 0;
    transform: translateX(5px);
}

.parameter-card:hover .parameter-action {
    opacity: 1;
    transform: translateX(0);
    color: #4a90e2;
}

.parameter-hover-effect {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100%;
    background: linear-gradient(
        90deg,
        rgba(74, 144, 226, 0.05) 0%,
        rgba(74, 144, 226, 0) 70%
    );
    opacity: 0;
    transition: opacity 0.3s;
}

.parameter-card:hover .parameter-hover-effect {
    opacity: 1;
}

.section-footer {
    font-size: 0.75rem;
    color: #95a5a6;
    text-align: center;
    padding-top: 10px;
    border-top: 1px solid #f0f3f7;
}

/* Animation for new items */
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

.parameter-card {
    animation: fadeIn 0.4s ease-out forwards;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .parameter-grid {
        grid-template-columns: 1fr;
    }

    .scrollable-parameter-grid {
        max-height: 350px;
    }

    .parameter-card {
        height: 85px;
    }
}
</style>
