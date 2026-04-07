<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Tambah
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Sub Jenis Analisa Berdasarkan Jenis Analisa Berkala Keys
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <form
                        @submit.prevent="submitFormToDatabase"
                        v-loading="loading.loadingSaveToDatabase"
                        element-loading-text="Menyimpan Data..."
                    >
                        <div
                            v-if="roles && roles.length > 1"
                            class="mb-4 p-3 bg-primary bg-opacity-10 border border-primary rounded"
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
                            v-if="loading.loadinComputerIdentityList"
                        >
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

                        <div class="mb-3" v-else>
                            <label
                                for="Id_Identity"
                                class="form-label fw-semibold"
                            >
                                Jenis Analisa Utama
                                <span class="text-danger">*</span>
                            </label>
                            <v-select
                                v-if="
                                    computerIdentityList &&
                                    computerIdentityList.length
                                "
                                v-model="selectedOptionIdentity"
                                :options="computerIdentityList"
                                label="name"
                                placeholder="--- Pilih Jenis Analisa ---"
                                class="scrollable-select"
                            />
                        </div>

                        <div class="mb-4" v-if="loading.loadingMesinList">
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

                        <div class="mb-4" v-else>
                            <label
                                for="Id_Mesin"
                                class="form-label fw-semibold"
                            >
                                Sub Analisa (Multiple)
                                <span class="text-danger">*</span>
                            </label>
                            <v-select
                                v-if="mesinList && mesinList.length"
                                v-model="selectedOptionMesin"
                                :options="mesinList"
                                label="name"
                                placeholder="--- Pilih Sub Analisa ---"
                                class="scrollable-select"
                                :multiple="true"
                                :close-on-select="false"
                                :clearable="true"
                            />
                        </div>

                        <div class="d-grid">
                            <button
                                type="submit"
                                class="btn btn-success py-2 fw-bold"
                                :disabled="
                                    loading.loadingSaveToDatabase ||
                                    loading.loadingMesinList ||
                                    loading.loadinComputerIdentityList
                                "
                            >
                                {{
                                    loading.loadingSaveToDatabase
                                        ? "Menyimpan..."
                                        : "Simpan Data"
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
import vSelect from "vue-select";
import Swal from "sweetalert2";
import { ElSelect, ElOption, vLoading } from "element-plus";

export default {
    components: {
        vSelect,
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
    },
    data() {
        return {
            selectedRole: "",
            selectedOptionIdentity: null,
            selectedOptionMesin: null,
            mesinList: [],
            computerIdentityList: [],
            loading: {
                loadingMesinList: false,
                loadinComputerIdentityList: false,
                loadingSaveToDatabase: false,
            },
        };
    },
    methods: {
        async fetchMesinList() {
            this.loading.loadingMesinList = true;
            try {
                const response = await axios.get(
                    "/api/v1/jenis-analisa-rutin/current"
                );

                if (response.status === 200 && response.data?.result) {
                    this.mesinList = response.data.result.map((item) => ({
                        value: item.id,
                        name: `${item.Kode_Analisa} ~ ${item.Jenis_Analisa}`,
                    }));
                } else {
                    this.mesinList = [];
                }
            } catch (error) {
                this.mesinList = [];
            } finally {
                this.loading.loadingMesinList = false;
            }
        },
        async fetchComputerIdentityList() {
            this.loading.loadinComputerIdentityList = true;
            try {
                const response = await axios.get(
                    "/api/v1/jenis-analisa-berkala/current"
                );
                if (response.status === 200 && response.data?.result) {
                    this.computerIdentityList = response.data.result.map(
                        (item) => ({
                            value: item.id,
                            name: `${item.Jenis_Analisa}`,
                        })
                    );
                } else {
                    this.computerIdentityList = [];
                }
            } catch (error) {
                this.computerIdentityList = [];
            } finally {
                this.loading.loadinComputerIdentityList = false;
            }
        },
        async submitFormToDatabase() {
            if (this.roles.length > 1 && !this.selectedRole) {
                Swal.fire({
                    icon: "error",
                    title: "Peringatan",
                    text: "Pilih Penempatan / Role terlebih dahulu!",
                });
                return;
            }

            if (
                !this.selectedOptionIdentity ||
                !this.selectedOptionIdentity.value ||
                !this.selectedOptionMesin ||
                !this.selectedOptionMesin.length
            ) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Form Harus Di Isi Semua",
                });
                return;
            }

            this.loading.loadingSaveToDatabase = true;

            try {
                const subJenisIds = this.selectedOptionMesin.map(
                    (m) => m.value
                );

                const payload = {
                    Kode_Role: this.selectedRole,
                    Id_Jenis_Analisa: this.selectedOptionIdentity.value,
                    Id_Sub_Jenis_Analisa_List: subJenisIds,
                };

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                const response = await axios.post(
                    "/api/v1/jenis-analisa-rutin/store",
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
                        window.location.href = "/sub-jenis-analisa/all";
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: response.data.message || "Gagal menyimpan data.",
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text:
                        error.response?.data?.message ||
                        error.message ||
                        "Terjadi Kesalahan",
                });
            } finally {
                this.loading.loadingSaveToDatabase = false;
            }
        },
    },
    mounted() {
        this.fetchComputerIdentityList();
        this.fetchMesinList();
    },
};
</script>

<style>
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
