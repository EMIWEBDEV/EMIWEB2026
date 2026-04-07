<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Tambah
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Barang Analisa Pada LAB PT. EVO MANUFACTURING INDONESIA
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-lg-12">
                    <form @submit.prevent="saveData">
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

                        <div v-if="loading.init" class="text-center py-5">
                            <div
                                class="spinner-border text-primary"
                                role="status"
                            ></div>
                        </div>

                        <div v-else>
                            <transition-group name="list" tag="div">
                                <div
                                    v-for="(row, index) in formRows"
                                    :key="row.key"
                                    class="card mb-4 border shadow-sm"
                                >
                                    <div
                                        class="card-header bg-primary-subtle d-flex justify-content-between align-items-center"
                                    >
                                        <h6 class="mb-0 fw-bold text-primary">
                                            Konfigurasi #{{ index + 1 }}
                                        </h6>
                                        <button
                                            v-if="formRows.length > 1"
                                            type="button"
                                            class="btn btn-danger btn-sm"
                                            @click="removeRow(index)"
                                        >
                                            Hapus
                                        </button>
                                    </div>

                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">
                                                Jenis Analisa
                                                <span class="text-danger"
                                                    >*</span
                                                >
                                            </label>
                                            <el-select
                                                v-model="row.Id_Jenis_Analisa"
                                                placeholder="-- Pilih Jenis Analisa --"
                                                class="w-100"
                                                filterable
                                                clearable
                                            >
                                                <el-option
                                                    v-for="item in options.jenisAnalisa"
                                                    :key="item.id"
                                                    :label="`${item.Kode_Analisa} ~ ${item.Jenis_Analisa}`"
                                                    :value="item.id"
                                                />
                                            </el-select>
                                        </div>

                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label
                                                    class="form-label fw-bold"
                                                    >Pilih Mesin</label
                                                >
                                                <div
                                                    class="text-muted small mb-1"
                                                >
                                                    Dapat memilih lebih dari 1
                                                    mesin sekaligus
                                                </div>
                                                <el-select
                                                    v-model="
                                                        row.Id_Master_Mesin
                                                    "
                                                    multiple
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    placeholder="-- Pilih Mesin --"
                                                    class="w-100"
                                                    filterable
                                                    @change="
                                                        (val) =>
                                                            handleSmartSelectAll(
                                                                val,
                                                                row,
                                                                'Id_Master_Mesin'
                                                            )
                                                    "
                                                >
                                                    <el-option
                                                        label="-- PILIH SEMUA MESIN --"
                                                        value="ALL"
                                                        class="fw-bold text-primary bg-light"
                                                    />
                                                    <el-option
                                                        v-for="item in options.mesin"
                                                        :key="
                                                            item.Id_Master_Mesin
                                                        "
                                                        :label="item.Nama_Mesin"
                                                        :value="
                                                            item.Id_Master_Mesin
                                                        "
                                                    />
                                                </el-select>
                                            </div>

                                            <div class="col-12">
                                                <label
                                                    class="form-label fw-bold"
                                                    >Pilih Barang</label
                                                >
                                                <div
                                                    class="text-muted small mb-1"
                                                >
                                                    Dapat memilih lebih dari 1
                                                    barang (Harus dipilih
                                                    manual)
                                                </div>
                                                <el-select
                                                    v-model="row.Kode_Barang"
                                                    multiple
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    placeholder="-- Pilih Barang --"
                                                    class="w-100"
                                                    filterable
                                                >
                                                    <el-option
                                                        v-for="item in options.barang"
                                                        :key="item.Kode_Barang"
                                                        :label="`${item.Kode_Barang} ~ ${item.Nama}`"
                                                        :value="
                                                            item.Kode_Barang
                                                        "
                                                    />
                                                </el-select>
                                            </div>

                                            <div class="col-12">
                                                <label
                                                    class="form-label fw-bold"
                                                    >Pilih User</label
                                                >
                                                <div
                                                    class="text-muted small mb-1"
                                                >
                                                    Dapat memilih lebih dari 1
                                                    user sekaligus
                                                </div>
                                                <el-select
                                                    v-model="row.Id_User"
                                                    multiple
                                                    collapse-tags
                                                    collapse-tags-tooltip
                                                    placeholder="-- Pilih User --"
                                                    class="w-100"
                                                    filterable
                                                    @change="
                                                        (val) =>
                                                            handleSmartSelectAll(
                                                                val,
                                                                row,
                                                                'Id_User'
                                                            )
                                                    "
                                                >
                                                    <el-option
                                                        label="-- PILIH SEMUA USER --"
                                                        value="ALL"
                                                        class="fw-bold text-primary bg-light"
                                                    />
                                                    <el-option
                                                        v-for="item in options.user"
                                                        :key="item.UserId"
                                                        :label="`${item.UserId} ~ ${item.Nama}`"
                                                        :value="item.UserId"
                                                    />
                                                </el-select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition-group>

                            <div
                                class="d-flex justify-content-between mt-4 border-top pt-3"
                            >
                                <button
                                    type="button"
                                    class="btn btn-outline-primary"
                                    @click="addRow"
                                >
                                    Tambah Konfigurasi
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-success px-5"
                                    :disabled="loading.save"
                                >
                                    <span
                                        v-if="loading.save"
                                        class="spinner-border spinner-border-sm me-2"
                                    ></span>
                                    <span v-else>Simpan Semua</span>
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
import axios from "axios";
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
            selectedRole: "",
            options: {
                jenisAnalisa: [],
                barang: [],
                mesin: [],
                user: [],
            },
            formRows: [],
            loading: {
                init: true,
                save: false,
            },
        };
    },
    mounted() {
        this.fetchAllOptions();
        this.addRow();
    },
    methods: {
        async fetchAllOptions() {
            this.loading.init = true;
            try {
                const [resJenis, resBarang, resMesin, resUser] =
                    await Promise.all([
                        axios.get(
                            "/api/v1/barang-analisa/option/jenis-analisa"
                        ),
                        axios.get(
                            "/api/v1/barang-analisa/option/varian-barang"
                        ),
                        axios.get("/api/v1/barang-analisa/option/mesin"),
                        axios.get("/api/v1/barang-analisa/option/user"),
                    ]);

                this.options.jenisAnalisa = resJenis.data.result;
                this.options.barang = resBarang.data.result;
                this.options.mesin = resMesin.data.result;
                this.options.user = resUser.data.result;
            } catch (error) {
                ElMessage.error("Gagal memuat data opsi.");
            } finally {
                this.loading.init = false;
            }
        },

        addRow() {
            this.formRows.push({
                key: Date.now(),
                Id_Jenis_Analisa: "",
                Id_Master_Mesin: [],
                Kode_Barang: [],
                Id_User: [],
            });
        },

        removeRow(index) {
            if (this.formRows.length > 1) {
                this.formRows.splice(index, 1);
            }
        },

        handleSmartSelectAll(val, row, field) {
            const isSelectAllSelected = val.includes("ALL");

            if (isSelectAllSelected) {
                if (val[val.length - 1] === "ALL") {
                    row[field] = ["ALL"];
                    ElMessage.info("Mode Semua Data Dipilih");
                } else {
                    row[field] = val.filter((item) => item !== "ALL");
                }
            }
        },

        async saveData() {
            if (this.roles.length > 1 && !this.selectedRole) {
                ElMessage.error("Pilih Penempatan / Role terlebih dahulu!");
                return;
            }

            for (let i = 0; i < this.formRows.length; i++) {
                const row = this.formRows[i];
                if (
                    !row.Id_Jenis_Analisa ||
                    !row.Kode_Barang.length ||
                    !row.Id_Master_Mesin.length ||
                    !row.Id_User.length
                ) {
                    ElMessage.warning(
                        `Baris #${i + 1}: Semua inputan wajib diisi.`
                    );
                    return;
                }
            }

            this.loading.save = true;
            try {
                const payload = {
                    Kode_Role: this.selectedRole,
                    group: this.formRows,
                };

                const response = await axios.post(
                    "/api/v1/barang-jenis/analisa/store",
                    payload
                );

                if (
                    response.status === 200 ||
                    response.data.status === "success"
                ) {
                    ElMessage.success("Data berhasil disimpan!");
                    window.location.reload();
                }
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    ElMessage.error(
                        "Validasi Error. Cek inputan anda. Sepertinya kurang lengkap"
                    );
                } else {
                    ElMessage.error(
                        error.response?.data?.message ||
                            "Terjadi kesalahan sistem."
                    );
                }
            } finally {
                this.loading.save = false;
            }
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
