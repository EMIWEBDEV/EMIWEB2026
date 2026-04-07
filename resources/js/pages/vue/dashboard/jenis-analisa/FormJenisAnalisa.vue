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
                    <form @submit.prevent="saveToDatabase">
                        <div
                            class="mb-3"
                            v-if="
                                loading.loadingOptionMesinList ||
                                loading.loadingOptionKategori
                            "
                        >
                            <div v-for="n in 4" :key="n">
                                <div class="placeholder-glow mb-1">
                                    <div
                                        class="placeholder col-4"
                                        style="height: 20px"
                                    ></div>
                                </div>
                                <div class="form-control placeholder-glow p-2">
                                    <div
                                        class="placeholder col-12 rounded"
                                        style="height: 40px"
                                    ></div>
                                </div>
                            </div>
                            <div class="mb-3 d-grid">
                                <div
                                    class="btn btn-success disabled placeholder-glow"
                                    style="height: 40px"
                                ></div>
                            </div>
                        </div>

                        <div v-else>
                            <div class="mb-3" v-if="roles && roles.length > 1">
                                <label class="form-label">
                                    Pilih Penempatan
                                    <span class="text-danger">*</span>
                                </label>
                                <el-select
                                    v-model="form.Kode_Role"
                                    placeholder="-- Pilih Role --"
                                    clearable
                                    @change="handleRoleChange"
                                >
                                    <el-option
                                        v-for="(role, index) in roles"
                                        :key="index"
                                        :label="role.Nama_Role"
                                        :value="role.Kode_Role"
                                    >
                                        <span
                                            style="
                                                font-weight: bold;
                                                color: var(
                                                    --el-text-color-primary
                                                );
                                            "
                                        >
                                            {{ role.Nama_Role }}
                                        </span>
                                    </el-option>
                                </el-select>
                            </div>

                            <div
                                v-for="(item, index) in form.analisa_list"
                                :key="index"
                                class="card mb-4 shadow-sm border-0"
                            >
                                <div
                                    class="card-header bg-light d-flex justify-content-between align-items-center"
                                >
                                    <h6 class="mb-0 fw-bold">
                                        Konfigurasi Analisa #{{ index + 1 }}
                                    </h6>
                                    <button
                                        v-if="index > 0"
                                        type="button"
                                        class="btn btn-sm btn-danger"
                                        @click="removeKonfigurasi(index)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3" v-if="isFLM">
                                        <label class="form-label fw-semibold">
                                            Kategori Analisa
                                            <span class="text-danger">*</span>
                                        </label>
                                        <el-select
                                            v-model="item.Kode_Aktivitas_Lab"
                                            placeholder="-- Pilih Kategori Analisa --"
                                            clearable
                                            class="w-100"
                                        >
                                            <el-option
                                                v-for="kat in optionsKategori"
                                                :key="
                                                    kat.Id_Klasifikasi_Aktivitas_Lab
                                                "
                                                :label="kat.Nama_Aktivitas"
                                                :value="kat.Kode_Aktivitas_Lab"
                                            >
                                                <span
                                                    style="
                                                        font-weight: bold;
                                                        color: var(
                                                            --el-text-color-primary
                                                        );
                                                    "
                                                >
                                                    {{ kat.Nama_Aktivitas }}
                                                </span>
                                            </el-option>
                                        </el-select>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label
                                                class="form-label fw-semibold"
                                                >Kode Analisa
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <input
                                                type="text"
                                                class="form-control"
                                                placeholder="Masukkan Kode Analisa"
                                                v-model="item.Kode_Analisa"
                                            />
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label
                                                class="form-label fw-semibold"
                                                >Jenis Analisa
                                                <span class="text-danger"
                                                    >*</span
                                                ></label
                                            >
                                            <input
                                                type="text"
                                                class="form-control"
                                                placeholder="Masukkan Jenis Analisa"
                                                v-model="item.Jenis_Analisa"
                                            />
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-semibold"
                                            >Nama Mesin</label
                                        >
                                        <v-select
                                            v-if="
                                                optionsMesinList &&
                                                optionsMesinList.length
                                            "
                                            v-model="item.selectedMesin"
                                            :options="optionsMesinList"
                                            label="name"
                                            placeholder="--- Pilih Mesin ---"
                                        />
                                    </div>

                                    <div
                                        class="toggle-card border rounded p-3 mb-3"
                                        :class="{
                                            'active-toggle':
                                                item.Sifat_Kegiatan === 'Rutin',
                                        }"
                                    >
                                        <div
                                            class="d-flex align-items-center justify-content-between"
                                        >
                                            <div
                                                class="d-flex align-items-center gap-3"
                                            >
                                                <div
                                                    class="icon-box"
                                                    :class="
                                                        item.Sifat_Kegiatan ===
                                                        'Rutin'
                                                            ? 'text-success'
                                                            : 'text-secondary'
                                                    "
                                                >
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >
                                                        <rect
                                                            x="3"
                                                            y="4"
                                                            width="18"
                                                            height="18"
                                                            rx="2"
                                                            ry="2"
                                                        ></rect>
                                                        <line
                                                            x1="16"
                                                            y1="2"
                                                            x2="16"
                                                            y2="6"
                                                        ></line>
                                                        <line
                                                            x1="8"
                                                            y1="2"
                                                            x2="8"
                                                            y2="6"
                                                        ></line>
                                                        <line
                                                            x1="3"
                                                            y1="10"
                                                            x2="21"
                                                            y2="10"
                                                        ></line>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-bold">
                                                        Sifat Kegiatan Analisa
                                                    </h6>
                                                    <span
                                                        class="text-muted small d-block"
                                                    >
                                                        Saat ini:
                                                        <strong
                                                            :class="
                                                                item.Sifat_Kegiatan ===
                                                                'Rutin'
                                                                    ? 'text-success'
                                                                    : ''
                                                            "
                                                            >{{
                                                                item.Sifat_Kegiatan ===
                                                                "Rutin"
                                                                    ? "Rutin"
                                                                    : "Berkala"
                                                            }}</strong
                                                        >.
                                                        {{
                                                            item.Sifat_Kegiatan ===
                                                            "Rutin"
                                                                ? "Dilakukan jadwal harian/mingguan/bulanan."
                                                                : "Dilakukan sesekali sesuai kebutuhan."
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <el-switch
                                                    v-model="
                                                        item.Sifat_Kegiatan
                                                    "
                                                    active-value="Rutin"
                                                    inactive-value="Berkala"
                                                    style="
                                                        --el-switch-on-color: #198754;
                                                    "
                                                    size="large"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="toggle-card border rounded p-3 mb-3"
                                        :class="{
                                            'active-toggle':
                                                item.Flag_Perhitungan === 'Y',
                                        }"
                                    >
                                        <div
                                            class="d-flex align-items-center justify-content-between"
                                        >
                                            <div
                                                class="d-flex align-items-center gap-3"
                                            >
                                                <div
                                                    class="icon-box"
                                                    :class="
                                                        item.Flag_Perhitungan ===
                                                        'Y'
                                                            ? 'text-success'
                                                            : 'text-secondary'
                                                    "
                                                >
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >
                                                        <rect
                                                            x="4"
                                                            y="2"
                                                            width="16"
                                                            height="20"
                                                            rx="2"
                                                            ry="2"
                                                        ></rect>
                                                        <line
                                                            x1="8"
                                                            y1="6"
                                                            x2="16"
                                                            y2="6"
                                                        ></line>
                                                        <line
                                                            x1="16"
                                                            y1="14"
                                                            x2="16"
                                                            y2="18"
                                                        ></line>
                                                        <path
                                                            d="M16 10h.01"
                                                        ></path>
                                                        <path
                                                            d="M12 10h.01"
                                                        ></path>
                                                        <path
                                                            d="M8 10h.01"
                                                        ></path>
                                                        <path
                                                            d="M12 14h.01"
                                                        ></path>
                                                        <path
                                                            d="M8 14h.01"
                                                        ></path>
                                                        <path
                                                            d="M12 18h.01"
                                                        ></path>
                                                        <path
                                                            d="M8 18h.01"
                                                        ></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-bold">
                                                        Memakai Perhitungan
                                                        Berdasarkan Parameter?
                                                    </h6>
                                                    <span
                                                        class="text-muted small d-block"
                                                    >
                                                        Saat ini:
                                                        <strong
                                                            :class="
                                                                item.Flag_Perhitungan ===
                                                                'Y'
                                                                    ? 'text-success'
                                                                    : ''
                                                            "
                                                            >{{
                                                                item.Flag_Perhitungan ===
                                                                "Y"
                                                                    ? "YA"
                                                                    : "TIDAK"
                                                            }}</strong
                                                        >.
                                                        {{
                                                            item.Flag_Perhitungan ===
                                                            "Y"
                                                                ? "Analisis membutuhkan kalkulasi sistem."
                                                                : "Hanya menginputkan hasil akhir saja."
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <el-switch
                                                    v-model="
                                                        item.Flag_Perhitungan
                                                    "
                                                    active-value="Y"
                                                    :inactive-value="null"
                                                    style="
                                                        --el-switch-on-color: #198754;
                                                    "
                                                    size="large"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="isFLM"
                                        class="toggle-card border rounded p-3 mb-3"
                                        :class="{
                                            'active-toggle':
                                                item.Flag_Foto === 'Y',
                                        }"
                                    >
                                        <div
                                            class="d-flex align-items-center justify-content-between"
                                        >
                                            <div
                                                class="d-flex align-items-center gap-3"
                                            >
                                                <div
                                                    class="icon-box"
                                                    :class="
                                                        item.Flag_Foto === 'Y'
                                                            ? 'text-success'
                                                            : 'text-secondary'
                                                    "
                                                >
                                                    <svg
                                                        width="24"
                                                        height="24"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >
                                                        <path
                                                            d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"
                                                        ></path>
                                                        <circle
                                                            cx="12"
                                                            cy="13"
                                                            r="4"
                                                        ></circle>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-bold">
                                                        Membutuhkan Foto Bukti?
                                                    </h6>
                                                    <span
                                                        class="text-muted small d-block"
                                                    >
                                                        Saat ini:
                                                        <strong
                                                            :class="
                                                                item.Flag_Foto ===
                                                                'Y'
                                                                    ? 'text-success'
                                                                    : ''
                                                            "
                                                            >{{
                                                                item.Flag_Foto ===
                                                                "Y"
                                                                    ? "YA"
                                                                    : "TIDAK"
                                                            }}</strong
                                                        >.
                                                        {{
                                                            item.Flag_Foto ===
                                                            "Y"
                                                                ? "Wajib melampirkan foto saat melakukan penginputan hasil."
                                                                : "Tidak diwajibkan untuk melampirkan foto."
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div>
                                                <el-switch
                                                    v-model="item.Flag_Foto"
                                                    active-value="Y"
                                                    inactive-value="T"
                                                    style="
                                                        --el-switch-on-color: #198754;
                                                    "
                                                    size="large"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <button
                                    type="button"
                                    class="btn btn-outline-primary w-100 fw-bold border-dashed"
                                    @click="addKonfigurasi"
                                >
                                    + Tambah Konfigurasi
                                </button>
                            </div>

                            <div class="mb-3 d-grid">
                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    :disabled="loading.loadingSaveToDatabase"
                                >
                                    <span v-if="loading.loadingSaveToDatabase"
                                        >Loading...</span
                                    >
                                    <span v-else>Submit Data</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import vSelect from "vue-select";
import { ElSelect, ElOption, ElSwitch } from "element-plus";
import axios from "axios";
import Swal from "sweetalert2";

export default {
    components: {
        vSelect,
        ElSelect,
        ElOption,
        ElSwitch, // Jangan lupa di register
    },
    props: {
        roles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            optionsMesinList: [],
            optionsKategori: [],
            loading: {
                loadingOptionMesinList: false,
                loadingOptionKategori: false,
                loadingSaveToDatabase: false,
            },
            form: {
                Kode_Role: "",
                analisa_list: [
                    {
                        Kode_Analisa: "",
                        Jenis_Analisa: "",
                        selectedMesin: null,
                        Sifat_Kegiatan: "Rutin",
                        Flag_Perhitungan: null,
                        Flag_Foto: "T", // Default false value (TIDAK)
                        Kode_Aktivitas_Lab: "",
                    },
                ],
            },
        };
    },
    computed: {
        isFLM() {
            if (this.roles.length === 1 && this.roles[0].Kode_Role === "FLM")
                return true;
            return this.form.Kode_Role === "FLM";
        },
    },
    watch: {
        isFLM: {
            immediate: true,
            handler(val) {
                if (val && this.optionsKategori.length === 0) {
                    this.fetchKategoriAnalisa();
                }
            },
        },
    },
    methods: {
        addKonfigurasi() {
            this.form.analisa_list.push({
                Kode_Analisa: "",
                Jenis_Analisa: "",
                selectedMesin: null,
                Sifat_Kegiatan: "Rutin",
                Flag_Perhitungan: "",
                Flag_Foto: "T",
                Kode_Aktivitas_Lab: "",
            });
        },
        removeKonfigurasi(index) {
            this.form.analisa_list.splice(index, 1);
        },
        handleRoleChange() {
            if (this.isFLM && this.optionsKategori.length === 0) {
                this.fetchKategoriAnalisa();
            }
        },
        async fetchKategoriAnalisa() {
            this.loading.loadingOptionKategori = true;
            try {
                const response = await axios.get(
                    "/api/v1/klasifikasi-analisa/option/current"
                );
                if (response.status === 200 && response.data?.result) {
                    this.optionsKategori = response.data.result;
                } else {
                    this.optionsKategori = [];
                }
            } catch (error) {
                this.optionsKategori = [];
            } finally {
                this.loading.loadingOptionKategori = false;
            }
        },
        async fetchMesinList() {
            this.loading.loadingOptionMesinList = true;
            try {
                const response = await axios.get("/api/v1/mesin-analisa/list");
                if (response.status === 200 && response.data?.result) {
                    this.optionsMesinList = response.data.result.map(
                        (item) => ({
                            value: item.No_Urut,
                            name: `${item.Divisi_Mesin} ~ ${item.Nama_Mesin}`,
                        })
                    );
                } else {
                    this.optionsMesinList = [];
                }
            } catch (error) {
                this.optionsMesinList = [];
            } finally {
                this.loading.loadingOptionMesinList = false;
            }
        },
        async saveToDatabase() {
            this.loading.loadingSaveToDatabase = true;
            const isRoleRequiredButEmpty =
                this.roles && this.roles.length > 1 && !this.form.Kode_Role;

            try {
                if (isRoleRequiredButEmpty) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Penempatan wajib dipilih.",
                    });
                    this.loading.loadingSaveToDatabase = false;
                    return;
                }

                let isValid = true;
                let isFLMValid = true;

                for (const item of this.form.analisa_list) {
                    if (!item.Kode_Analisa || !item.Jenis_Analisa) {
                        isValid = false;
                    }
                    if (
                        this.isFLM &&
                        (!item.Kode_Aktivitas_Lab || !item.Flag_Foto)
                    ) {
                        isFLMValid = false;
                    }
                }

                if (!isValid) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Terdapat Kode Analisa atau Jenis Analisa yang kosong.",
                    });
                    this.loading.loadingSaveToDatabase = false;
                    return;
                }

                if (!isFLMValid) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Kategori Analisa atau Flag Foto wajib dipilih untuk role FLM.",
                    });
                    this.loading.loadingSaveToDatabase = false;
                    return;
                }

                const payload = {
                    Kode_Role: this.form.Kode_Role,
                    data_analisa: this.form.analisa_list.map((item) => ({
                        Kode_Analisa: item.Kode_Analisa,
                        Jenis_Analisa: item.Jenis_Analisa,
                        Flag_Perhitungan: item.Flag_Perhitungan,
                        Sifat_Kegiatan: item.Sifat_Kegiatan,
                        Id_Mesin: item.selectedMesin
                            ? item.selectedMesin.value
                            : null,
                        Kode_Aktivitas_Lab: this.isFLM
                            ? item.Kode_Aktivitas_Lab
                            : null,
                        Flag_Foto: this.isFLM ? item.Flag_Foto : "T",
                    })),
                };

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                const response = await axios.post(
                    "/jenis-analisa/store",
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                if (response.status === 201 && response.data) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: "Data berhasil disimpan!",
                    }).then(() => {
                        window.location.href = "/jenis-analisa";
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Gagal menyimpan data.",
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text: error.response?.data?.message || "Terjadi Kesalahan",
                });
            } finally {
                this.loading.loadingSaveToDatabase = false;
            }
        },
    },
    mounted() {
        this.fetchMesinList();
        if (this.roles.length === 1 && this.roles[0].Kode_Role === "FLM") {
            this.fetchKategoriAnalisa();
        }
    },
};
</script>

<style>
/* CSS Custom Agar Mirip Design UI-nya */
.border-dashed {
    border-style: dashed !important;
    border-width: 2px !important;
}

.toggle-card {
    transition: all 0.3s ease;
    background-color: #ffffff;
    border-color: #e5e7eb !important;
}

/* State ketika ON (Aktif) */
.toggle-card.active-toggle {
    background-color: #f2fbf7 !important; /* Warna hijau muda transparan mirip gambar */
    border-color: #198754 !important; /* Border hijau */
}

.icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
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
