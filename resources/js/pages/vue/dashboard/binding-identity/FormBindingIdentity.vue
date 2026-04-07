<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Tambah
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Data Identity Berdasarkan Computer Keys
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <form @submit.prevent="submitFormToDatabase">
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
                                Computer Keys <span class="text-danger">*</span>
                            </label>
                            <v-select
                                v-if="
                                    computerIdentityList &&
                                    computerIdentityList.length
                                "
                                v-model="selectedOptionIdentity"
                                :options="computerIdentityList"
                                label="name"
                                placeholder="--- Pilih Komputer Keys ---"
                                class="scrollable-select"
                            />
                        </div>

                        <div class="mb-3" v-if="loading.loadingMesinList">
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
                            <div class="mb-3 d-grid">
                                <div
                                    class="btn btn-success disabled placeholder-glow"
                                    style="height: 40px"
                                ></div>
                            </div>
                        </div>
                        <div class="mb-3" v-else>
                            <label
                                for="Id_Mesin"
                                class="form-label fw-semibold"
                            >
                                Nama Mesin <span class="text-danger">*</span>
                            </label>
                            <v-select
                                v-if="mesinList && mesinList.length"
                                v-model="selectedOptionMesin"
                                :options="mesinList"
                                label="name"
                                placeholder="--- Pilih Mesin ---"
                                class="scrollable-select"
                            />

                            <div class="mb-3 mt-3 d-grid">
                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    :disabled="loading.loadingSaveToDatabase"
                                >
                                    {{
                                        loading.loadingSaveToDatabase
                                            ? "Loading..."
                                            : "Kirimkan"
                                    }}
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
import vSelect from "vue-select";
import Swal from "sweetalert2";

export default {
    components: {
        vSelect,
    },
    data() {
        return {
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
                    "/fetch/biding-identity/mesin-list"
                );

                if (response.status === 200 && response.data?.result) {
                    this.mesinList = response.data.result.map((item) => ({
                        value: item.Id_Master_Mesin,
                        name: `${item.Nama_Mesin}`,
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
                    "/fetch/biding-identity/identity-computer"
                );
                if (response.status === 200 && response.data?.result) {
                    this.computerIdentityList = response.data.result.map(
                        (item) => ({
                            value: item.id,
                            name: `${item.Computer_Keys} ~ ${item.Keterangan}`,
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
            this.loading.loadingSaveToDatabase = true;
            try {
                if (
                    this.selectedOptionIdentity.value === null ||
                    this.selectedOptionMesin.value === null
                ) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Form Harus Di Isi Semua",
                    });
                    return;
                }

                const payload = {
                    Id_Identity: this.selectedOptionIdentity.value,
                    Id_Mesin: this.selectedOptionMesin.value,
                };
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                const response = await axios.post(
                    "/biding-identity/store",
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
                        window.location.href = "/biding-identity";
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
                    text: error.message || "Terjadi Kesalahan",
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
