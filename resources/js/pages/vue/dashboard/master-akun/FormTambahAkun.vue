<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Tambah
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Akun Pengguna Untuk LAB PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <form @submit.prevent="submitFormToDatabase">
                        <div class="mb-3">
                            <label
                                for="Id_Mesin"
                                class="form-label fw-semibold"
                            >
                                Username <span class="text-danger">*</span>
                            </label>
                            <div
                                class="alert alert-warning alert-dismissible alert-label-icon label-arrow fade show material-shadow"
                                role="alert"
                            >
                                <i class="ri-error-warning-line label-icon"></i
                                ><strong>Perhatikan !</strong> - Jika Username
                                Belum Ada Pada Select Box Dibawah ini atau sudah
                                mengsearch secara berkala tidak ada, maka
                                username anda belum di buatkan, Maka Segera
                                Konfirmasi IT PT. Evo Manufacuring Indonesia
                            </div>
                            <v-select
                                v-if="akun && akun.length"
                                v-model="selectedOptionUsername"
                                :options="akun"
                                :get-option-label="
                                    (option) => `${option.UserID}`
                                "
                                label="UserID"
                                placeholder="--- Pilih Username ---"
                                class="scrollable-select"
                            />
                        </div>
                        <div class="mb-3">
                            <label for="Nama" class="form-label fw-semibold">
                                Nama Lengkap<span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                name="nama_lengkap"
                                id="nama_lengkap"
                                class="form-control"
                                placeholder="Masukkan Nama Lengkap"
                                v-model="form.Nama"
                            />
                        </div>
                        <div class="mb-3">
                            <label for="Nama" class="form-label fw-semibold">
                                Password<span class="text-danger">*</span>
                            </label>
                            <div
                                class="position-relative auth-pass-inputgroup mb-3"
                            >
                                <input
                                    :type="
                                        isPasswordVisible ? 'text' : 'password'
                                    "
                                    class="form-control pe-5 password-input"
                                    placeholder="Enter password"
                                    id="password-input"
                                    v-model="form.Password"
                                />
                                <button
                                    class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none"
                                    type="button"
                                    @click="togglePasswordVisibility"
                                >
                                    <i
                                        :class="
                                            isPasswordVisible
                                                ? 'ri-eye-off-fill'
                                                : 'ri-eye-fill'
                                        "
                                        class="align-middle"
                                    ></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label
                                for="pin-input"
                                class="form-label fw-semibold"
                            >
                                Pin<span class="text-danger">*</span>
                            </label>
                            <div
                                class="alert alert-danger alert-dismissible alert-label-icon label-arrow fade show material-shadow"
                                role="alert"
                            >
                                <i class="ri-error-warning-line label-icon"></i
                                ><strong>Perhatikan !</strong> - PIN Wajib 6
                                Digit Angka
                            </div>
                            <div
                                class="position-relative auth-pass-inputgroup mb-3"
                            >
                                <input
                                    type="number"
                                    class="form-control pe-5 password-input"
                                    placeholder="Masukkan PIN 6 Digit"
                                    id="pin-input"
                                    maxlength="6"
                                    @keypress="checkDigit"
                                    v-model="form.Pin"
                                />
                            </div>
                        </div>
                        <div class="mb-3 d-grid">
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
    props: {
        akun: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            form: {
                Nama: "",
                Password: "",
                Pin: "",
            },
            loading: {
                loadingSaveToDatabase: false,
            },
            selectedOptionUsername: null,
            isPasswordVisible: false,
        };
    },
    methods: {
        async submitFormToDatabase() {
            if (
                !this.selectedOptionUsername ||
                !this.form.Nama ||
                !this.form.Password ||
                !this.form.Pin
            ) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Semua field wajib diisi.",
                });
                return;
            }

            if (this.form.Pin.toString().length !== 6) {
                Swal.fire({
                    icon: "warning",
                    title: "PIN Tidak Valid",
                    text: "PIN harus terdiri dari 6 digit angka.",
                });
                return;
            }

            this.loading.loadingSaveToDatabase = true;

            try {
                const payload = {
                    UserId: this.selectedOptionUsername.UserID,
                    Nama: this.form.Nama,
                    Password: this.form.Password,
                    Pin: this.form.Pin,
                };

                const response = await axios.post("/proses_register", payload, {
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                    },
                });

                if (response.status === 201 && response.data) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: "Data berhasil disimpan!",
                    }).then(() => {
                        window.location.href = "/master-akun";
                    });
                } else {
                    throw new Error(
                        response.data.message ||
                            response.data.message?.error ||
                            "Gagal menyimpan data."
                    );
                }
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text:
                        error.response.data?.message ||
                        error.response.data?.message?.error ||
                        "Terjadi Kesalahan",
                });
            } finally {
                this.loading.loadingSaveToDatabase = false;
            }
        },
        togglePasswordVisibility() {
            this.isPasswordVisible = !this.isPasswordVisible;
        },

        checkDigit(event) {
            const input = event.target;
            const key = event.key;

            if (key.length === 1 && !/^\d$/.test(key)) {
                event.preventDefault();
                return;
            }

            if (input.value.length >= 6) {
                event.preventDefault();
                return;
            }
        },
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
