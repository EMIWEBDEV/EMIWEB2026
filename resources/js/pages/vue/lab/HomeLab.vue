<template>
    <div class="cleaning-system-container">
        <!-- Header Section -->
        <div class="system-header">
            <div class="header-content">
                <h1 class="system-title">
                    <i class="fas fa-vials"></i> Kontrol Kualitas Sampel
                </h1>
                <p class="system-subtitle">Sistem Manajemen Uji Sampel</p>
            </div>
            <div class="header-actions">
                <button class="btn-help">
                    <i class="fas fa-question-circle"></i> Bantuan
                </button>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="search-panel">
                <div class="panel-header">
                    <h2><i class="fas fa-search"></i> Cari Data Sampel</h2>
                </div>
                <div class="panel-body">
                    <div class="search-form">
                        <div class="form-group">
                            <label for="sampleNumber" class="form-label"
                                >Nomor Sampel</label
                            >
                            <div class="input-with-button">
                                <input
                                    type="search"
                                    id="sampleNumber"
                                    v-model="sampleNumber"
                                    class="form-input"
                                    placeholder="FSXXXX-XXXX"
                                    autocomplete="off"
                                    autofocus
                                />
                                <button
                                    @click="fetchDetails"
                                    class="btn-search"
                                >
                                    <i class="fas fa-search"></i> Cari
                                </button>
                                <button
                                    v-if="nomorSampel.sampleDetails"
                                    @click="resetDataSearch"
                                    class="btn-search bg-danger"
                                >
                                    <i class="fas fa-sync-alt"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="showIntro"
                    class="container d-flex justify-content-center align-items-center"
                >
                    <div class="d-flex flex-column text-center">
                        <DotLottieVue
                            style="height: 350px; width: 500px"
                            autoplay
                            loop
                            src="/animation/labAnimation.lottie"
                        />
                        <p class="mt-4 fw-semibold fs-5">
                            Segera Analisis, Masukan Nomor Sampel / Nomor
                            Transaksi Anda
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="nomorSampel.sampleDetails" class="details-panel">
                <div class="panel-header with-tabs">
                    <h2><i class="fas fa-clipboard-list"></i> Detail Sampel</h2>
                    <div class="status-badge">
                        <span class="badge active">
                            {{ nomorSampel.sampleDetails.nama_barang ?? "-" }}
                        </span>
                        <span class="badge priority">{{
                            nomorSampel.sampleDetails.kode_barang ?? "-"
                        }}</span>
                    </div>
                </div>

                <div class="panel-body">
                    <div class="detail-grid">
                        <div class="detail-column">
                            <div class="detail-item">
                                <span class="detail-label">No. PO</span>
                                <span class="detail-value">{{
                                    nomorSampel.sampleDetails.no_po
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">No. Sampel</span>
                                <span class="detail-value">{{
                                    nomorSampel.sampleDetails.no_sampel
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Mesin</span>
                                <span class="detail-value highlight">{{
                                    nomorSampel.sampleDetails.nama_mesin
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Seri Mesin</span>
                                <span class="detail-value">{{
                                    nomorSampel.sampleDetails.seri_mesin
                                }}</span>
                            </div>
                            <div
                                class="detail-item"
                                v-if="
                                    nomorSampel.sampleDetails.Berat_Sampel !== 0
                                "
                            >
                                <span class="detail-label"
                                    >Berat Sampel (Kg)</span
                                >
                                <span class="detail-value"
                                    >{{
                                        nomorSampel.sampleDetails.Berat_Sampel
                                    }}
                                    Kg</span
                                >
                            </div>
                            <div
                                class="detail-item"
                                v-if="
                                    nomorSampel.sampleDetails.Jumlah_Pcs !== 0
                                "
                            >
                                <span class="detail-label">Jumlah Pcs</span>
                                <span class="detail-value"
                                    >{{
                                        nomorSampel.sampleDetails.Jumlah_Pcs
                                    }}
                                    Pcs</span
                                >
                            </div>
                        </div>
                        <div class="detail-column">
                            <div class="detail-item">
                                <span class="detail-label">No. Split PO</span>
                                <span class="detail-value">{{
                                    nomorSampel.sampleDetails.no_split_po
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Tanggal</span>
                                <span class="detail-value"
                                    >{{
                                        formatTanggal(
                                            nomorSampel.sampleDetails.tanggal
                                        )
                                    }}
                                    {{ nomorSampel.sampleDetails.jam }}</span
                                >
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">No. Batch</span>
                                <span class="detail-value">{{
                                    nomorSampel.sampleDetails.no_batch
                                }}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label"
                                    >Jumlah Cetak QR</span
                                >
                                <span class="detail-value">{{
                                    nomorSampel.sampleDetails.jumlah_print
                                }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="notes-section">
                        <div class="notes-header">
                            <span class="notes-label"
                                ><i class="fas fa-sticky-note"></i> Catatan
                                Khusus</span
                            >
                        </div>
                        <div class="notes-content">
                            {{
                                nomorSampel.sampleDetails.keterangan ||
                                "Tidak ada catatan"
                            }}
                        </div>
                    </div>
                </div>

                <div class="panel-header">
                    <h2>
                        <i class="fas fa-file-alt"></i> Pilih Template Analisis
                    </h2>
                </div>
                <div class="panel-body">
                    <div
                        class="analysis-grid"
                        v-if="nomorSampel.sampleDetails.analisa.length"
                    >
                        <div
                            v-for="(item, index) in nomorSampel.sampleDetails
                                .analisa"
                            :key="index"
                            class="analysis-card"
                            :class="{ 'completed-card': item.is_done }"
                            @click="
                                !item.is_done &&
                                    handleClickLab(item.id, item.Kode_Analisa)
                            "
                        >
                            <div class="analysis-icon">
                                <i class="fas fa-flask"></i>
                                <div
                                    v-if="item.is_done"
                                    class="completed-badge"
                                >
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>

                            <div class="analysis-content">
                                <div class="analysis-badge">
                                    <span
                                        class="badge"
                                        :class="
                                            item.is_done
                                                ? 'bg-success-soft'
                                                : 'bg-primary-soft'
                                        "
                                    >
                                        {{ item.Kode_Analisa }}
                                    </span>
                                </div>

                                <h4 class="analysis-title">
                                    {{ item.Jenis_Analisa }}
                                    <span
                                        v-if="item.is_done"
                                        class="completed-text"
                                        >Selesai</span
                                    >
                                </h4>

                                <div
                                    class="analysis-meta"
                                    v-if="item.Nama_Mesin !== null"
                                >
                                    <span class="meta-item">
                                        <i class="fas fa-microscope"></i>
                                        {{ item.Nama_Mesin }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="!nomorSampel.sampleDetails.analisa.length"
                        class="d-flex justify-content-center"
                    >
                        <div class="flex-column align-content-center">
                            <DotLottieVue
                                style="height: 100px; width: 100px"
                                autoplay
                                loop
                                src="/animation/empty2.json"
                            />
                            <p class="text-center">Data Tidak Ditemukan !</p>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-if="loading.detailTemplate"
                class="text-center loading-state"
            >
                <div class="d-flex justify-content-center py-4 loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Memuat...</span>
                    </div>
                </div>
            </div>

            <div v-else>
                <div v-if="nomorSampel.sampleDetails">
                    <div
                        v-if="nomorSampel.sampleDetails.is_multi_print === 'Y'"
                    >
                        <div v-if="selectedTemplating">
                            <div class="search-panel">
                                <div class="panel-header">
                                    <h2>
                                        <i class="fas fa-search"></i> Cari Data
                                    </h2>
                                </div>
                                <div class="panel-body">
                                    <div class="search-form">
                                        <div class="form-group">
                                            <label
                                                for="sampleNumber-multi"
                                                class="form-label"
                                                >Nomor Multi Sampel</label
                                            >
                                            <div class="input-with-button">
                                                <input
                                                    type="search"
                                                    id="sampleNumber-multi"
                                                    v-model="samplePoMulti"
                                                    class="form-input"
                                                    placeholder="FSXXXX-XXXX"
                                                    autocomplete="off"
                                                    autofocus
                                                />
                                                <button
                                                    :disabled="
                                                        loading.multiSampel
                                                    "
                                                    @click="fetchNoMultiQrcode"
                                                    class="btn-search"
                                                >
                                                    <i
                                                        class="fas fa-search"
                                                    ></i>
                                                    Cari
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="showIntro"
                                    class="container d-flex justify-content-center align-items-center"
                                >
                                    <div class="d-flex flex-column text-center">
                                        <DotLottieVue
                                            style="height: 350px; width: 500px"
                                            autoplay
                                            loop
                                            src="/animation/labAnimation.lottie"
                                        />
                                        <p class="mt-4 fw-semibold fs-5">
                                            Segera Analisis, Masukan Nomor
                                            Sampel / Nomor Transaksi Anda
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="nomorSampel.multiSampel">
                                <div v-if="nomorSampel.multiSampel.is_done">
                                    <div class="form-panel modern-form">
                                        <div class="panel-header">
                                            <h2>
                                                <i
                                                    class="fas fa-flask me-2"
                                                ></i>
                                                Sample Analysis
                                            </h2>
                                            <p class="subtitle">
                                                Masukkan hasil analisis rinci
                                                untuk sampel
                                            </p>
                                        </div>
                                        <div
                                            v-if="
                                                loading.currentDataSubmitAnalisa
                                            "
                                            class="text-center loading-state"
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

                                        <div class="panel-body" v-else>
                                            <div class="celebration-container">
                                                <div class="confetti-left">
                                                    <DotLottieVue
                                                        class="uiWah"
                                                        autoplay
                                                        loop
                                                        src="/animation/confentie.json"
                                                    />
                                                </div>

                                                <!-- Confetti kanan -->
                                                <div class="confetti-right">
                                                    <DotLottieVue
                                                        class="uiWah"
                                                        autoplay
                                                        loop
                                                        src="/animation/confentie.json"
                                                    />
                                                </div>

                                                <div class="animation-wrapper">
                                                    <DotLottieVue
                                                        class="celebration-animation"
                                                        autoplay
                                                        loop
                                                        src="/animation/done.json"
                                                    />
                                                </div>

                                                <div
                                                    class="celebration-message"
                                                >
                                                    <h2 class="congrats-text">
                                                        Selesai
                                                    </h2>
                                                    <p class="sample-info">
                                                        Nomor uji sampel
                                                        <span
                                                            class="sample-number"
                                                            >{{
                                                                samplePoMulti
                                                            }}</span
                                                        >
                                                        telah berhasil
                                                        diselesaikan
                                                    </p>
                                                </div>

                                                <!-- Confetti CSS pure -->
                                                <div class="confetti"></div>
                                                <div class="confetti"></div>
                                                <div class="confetti"></div>
                                                <div class="confetti"></div>
                                                <div class="confetti"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else>
                                    <div
                                        v-if="
                                            selectedTemplating.formula !== null
                                        "
                                    >
                                        <MultiRumus
                                            :selectedTemplating="
                                                selectedTemplating
                                            "
                                            :is_multi_print="
                                                nomorSampel.sampleDetails
                                                    ?.is_multi_print ?? null
                                            "
                                            :no_ticket="
                                                nomorSampel.multiSampel
                                                    ?.no_ticket ?? null
                                            "
                                            :Id_Jenis_Analisa="
                                                reactiveIdJenisAnalisa
                                            "
                                            :No_Po_Sampel="
                                                nomorSampel.sampleDetails
                                                    .no_sampel ?? null
                                            "
                                            :Id_Mesin="
                                                nomorSampel.sampleDetails
                                                    .Id_Mesin ?? null
                                            "
                                            :kodeAnalisa="kodeAnalisa"
                                        />
                                    </div>
                                    <div v-else>
                                        <NotRumus
                                            :selectedTemplating="
                                                selectedTemplating
                                            "
                                            :is_multi_print="
                                                nomorSampel.sampleDetails
                                                    ?.is_multi_print ?? null
                                            "
                                            :Id_Jenis_Analisa="
                                                reactiveIdJenisAnalisa
                                            "
                                            :No_Po_Sampel="
                                                nomorSampel.sampleDetails
                                                    .no_sampel
                                            "
                                            :No_Fak_Sub_Po="samplePoMulti"
                                            :Id_Mesin="
                                                nomorSampel.sampleDetails
                                                    .Id_Mesin ?? null
                                            "
                                            :kodeAnalisa="kodeAnalisa"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else>
                        <div v-if="selectedTemplating">
                            <div v-if="selectedTemplating.formula !== null">
                                <MultiRumus
                                    :selectedTemplating="selectedTemplating"
                                    :Id_Jenis_Analisa="reactiveIdJenisAnalisa"
                                    :No_Po_Sampel="
                                        nomorSampel.sampleDetails.no_sampel
                                    "
                                    :is_multi_print="
                                        nomorSampel.multiSampel
                                            ?.is_multi_print ?? null
                                    "
                                    :Id_Mesin="
                                        nomorSampel.sampleDetails.Id_Mesin ??
                                        null
                                    "
                                    :kodeAnalisa="kodeAnalisa"
                                />
                            </div>
                            <div v-else>
                                <NotRumus
                                    :selectedTemplating="selectedTemplating"
                                    :Id_Jenis_Analisa="reactiveIdJenisAnalisa"
                                    :No_Po_Sampel="
                                        nomorSampel.sampleDetails.no_sampel
                                    "
                                    :kodeAnalisa="kodeAnalisa"
                                    :is_multi_print="
                                        nomorSampel.multiSampel
                                            ?.is_multi_print ?? null
                                    "
                                    :Id_Mesin="
                                        nomorSampel.sampleDetails.Id_Mesin ??
                                        null
                                    "
                                />
                            </div>
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
import Swal from "sweetalert2";
import throttle from "lodash/throttle";
import { reactive } from "vue";
import { defineAsyncComponent } from "vue";
const MultiRumus = defineAsyncComponent(() =>
    import("./perhitungan-backup/MultiRumus.vue")
);
const NotRumus = defineAsyncComponent(() =>
    import("./perhitungan-backup/NotRumus.vue")
);

export default {
    components: {
        DotLottieVue,
        MultiRumus,
        NotRumus,
    },
    data() {
        return {
            detailSampelMessage: "",
            multiSampelMessage: "",
            parameterSampelMessage: "",
            selectedTemplating: null,
            inputValues: reactive({}),
            sampleNumber: null,
            samplePoMulti: null,
            showIntro: true,
            reactiveIdJenisAnalisa: null,
            kodeAnalisa: null,
            nomorSampel: {
                sampleDetails: null,
                multiSampel: null,
            },
            loading: {
                detailSample: false,
                detailTemplate: false,
                multiSampel: false,
            },
        };
    },
    methods: {
        handleClickLab: throttle(async function (idJenisAnalisa, Kode_Analisa) {
            const selectedItem = this.nomorSampel.sampleDetails.analisa.find(
                (item) => item.id === idJenisAnalisa
            );

            if (selectedItem?.is_done) {
                this.activeCardId = null; // Reset active card jika sudah selesai
                return;
            }

            document.querySelectorAll(".analysis-card").forEach((card) => {
                card.classList.remove("active");
            });
            event.currentTarget.classList.add("active");

            this.loading.detailTemplate = true;
            this.reactiveIdJenisAnalisa = idJenisAnalisa;
            this.kodeAnalisa = Kode_Analisa;
            this.nomorSampel.multiSampel = null;
            const idMesin = this.nomorSampel.sampleDetails?.Id_Mesin;
            if (!idMesin) throw new Error("ID Mesin tidak ditemukan");

            try {
                const response = await axios.get(
                    `/fetch/lab/lama/${idJenisAnalisa}/parameter-perhitungan-old`
                );

                if (response.status === 200 && response.data?.result) {
                    this.selectedTemplating = response.data.result;
                    // Inisialisasi inputValues dengan id_qc sebagai key
                    this.selectedTemplating.parameter.forEach((param) => {
                        this.inputValues[param.id_qc] = null;
                    });
                } else {
                    this.selectedTemplating = null;
                }
            } catch (error) {
                this.selectedTemplating = null;

                let errorMessage = "Data Tidak Ditemukan";
                if (error?.message) {
                    errorMessage += `: ${error.message}`;
                } else if (error?.response?.data?.error) {
                    errorMessage += `: ${error.response.data.error}`;
                }

                Swal.fire("Peringatan", errorMessage, "warning");
            } finally {
                this.loading.detailTemplate = false;
            }
        }, 500),

        async fetchDetails() {
            this.detailSampelMessage = "";
            this.selectedTemplating = null;
            this.nomorSampel.multiSampel = null;
            document.querySelectorAll(".analysis-card").forEach((card) => {
                card.classList.remove("active");
            });
            this.loading.detailSample = true;

            try {
                const no_sampel = this.sampleNumber;

                if (!no_sampel) {
                    Swal.fire(
                        "Peringatan",
                        "Masukkan nomor sampel terlebih dahulu.",
                        "warning"
                    );
                    return;
                }

                sessionStorage.setItem("sampleNumber", no_sampel);

                const response = await axios.get(
                    `/api/v2/lab/detail-data-sampel/${no_sampel}`
                );

                // Blok pengecekan 404 di sini DIHAPUS karena Axios 404 akan langsung masuk ke catch

                if (response.data.finished === true) {
                    await Swal.fire({
                        icon: "success",
                        title: "🎉 Selesai! 🎉",
                        text: response.data.message,
                        confirmButtonText: "Tutup",
                    });
                    this.nomorSampel.sampleDetails = null;
                    this.showIntro = true;
                    return;
                }

                if (response.data.locked === true) {
                    await Swal.fire({
                        icon: "warning",
                        title: "⏰ Waktu Habis!",
                        html: `<b>Nomor Sampel:</b> ${no_sampel}<br><br>${response.data.message}`,
                        confirmButtonText: "Mengerti",
                        confirmButtonColor: "#d33",
                        customClass: {
                            popup: "animated fadeInDown faster",
                        },
                    });
                    this.nomorSampel.sampleDetails = null;
                    this.showIntro = true;
                    return;
                }

                if (
                    response.data.status_kondisi ===
                    "BUTUH_SELESAIKAN_SEBELUMNYA"
                ) {
                    await Swal.fire({
                        icon: "warning",
                        title: "⛔ Syarat Belum Terpenuhi",
                        html: `<b>Nomor Sampel:</b> ${no_sampel}<br><br>${response.data.message}`,
                        confirmButtonText: "Mengerti",
                        confirmButtonColor: "#d33",
                        customClass: {
                            popup: "animated fadeInDown faster",
                        },
                    });
                    this.nomorSampel.sampleDetails = null;
                    this.showIntro = true;
                    return;
                }

                // Kalau data ditemukan dan belum selesai
                this.nomorSampel.sampleDetails = response.data.result;
                this.showIntro = false;
            } catch (error) {
                console.log(error);
                this.nomorSampel.sampleDetails = null;
                this.showIntro = true;

                // ✅ TANGANI 404 DI SINI
                if (error.response && error.response.status === 404) {
                    this.detailSampelMessage = "Data Tidak Ditemukan";
                    await Swal.fire({
                        icon: "warning",
                        title: "Data Tidak Ditemukan",
                        text:
                            error.response.data.message ||
                            "Nomor sampel tidak tersedia. Periksa kembali nomor Anda.",
                        confirmButtonText: "Tutup",
                    });
                } else {
                    // Error lain (500, network error, dll) masuk ke sini
                    this.detailSampelMessage =
                        "Terjadi Kesalahan Dalam Mengambil Data";
                    await Swal.fire({
                        icon: "error",
                        title: "Terjadi Kesalahan",
                        text: "Gagal mengambil data sampel. Silakan coba lagi nanti.",
                        confirmButtonText: "Tutup",
                    });
                }
            } finally {
                this.loading.detailSample = false;
            }
        },

        async fetchNoMultiQrcode() {
            this.multiSampelMessage = "";
            this.nomorSampel.multiSampel = null;
            this.loading.multiSampel = true;
            try {
                const no_sampel = this.samplePoMulti;

                if (!no_sampel) {
                    Swal.fire(
                        "Peringatan",
                        "Masukkan nomor sampel terlebih dahulu.",
                        "warning"
                    );
                    return;
                }

                const response = await axios.get(
                    `/api/v3/${this.sampleNumber}/${no_sampel}/multi-print/${this.reactiveIdJenisAnalisa}`
                );
                if (
                    response.data.success === false &&
                    response.data.status === 404
                ) {
                    await Swal.fire({
                        icon: "warning",
                        title: "Data Tidak Ditemukan",
                        text: "Nomor sampel tidak tersedia. Periksa kembali nomor Anda.",
                        confirmButtonText: "Tutup",
                    });
                    this.nomorSampel.multiSampel = null;
                }
                this.nomorSampel.multiSampel = response.data.result;
            } catch (error) {
                console.log(error);
                this.nomorSampel.multiSampel = null;
                this.multiSampelMessage =
                    "Terjadi Kesalahan Dalam Mengambil Data";
                await Swal.fire({
                    icon: "error",
                    title: "Terjadi Kesalahan",
                    text:
                        error.response.data.message ||
                        error.message ||
                        "Gagal mengambil data sampel. Silakan coba lagi nanti.",
                    confirmButtonText: "Tutup",
                });
            } finally {
                this.loading.multiSampel = false;
            }
        },

        formatTanggal(tanggalString) {
            const date = new Date(tanggalString);
            const options = { day: "2-digit", month: "short", year: "numeric" };
            return date.toLocaleDateString("en-GB", options);
        },

        resetDataSearch() {
            sessionStorage.removeItem("sampleNumber");
            this.sampleNumber = null;
            this.selectedTemplating = null;
            this.nomorSampel.sampleDetails = null;
            this.showIntro = true;
        },
    },
    mounted() {
        const savedSampleNumber = sessionStorage.getItem("sampleNumber");
        if (savedSampleNumber) {
            this.sampleNumber = savedSampleNumber;
            this.fetchDetails();
        }

        const url = new URL(window.location.href);

        if (!url.searchParams.has("ts")) {
            const now = Date.now();
            const timezone =
                Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
            const lang = navigator.language || "en";
            const screenSize = `${window.screen.width}x${window.screen.height}`;
            const viewportSize = `${window.innerWidth}x${window.innerHeight}`;
            const platform = /Mobi/i.test(navigator.userAgent)
                ? "mobile"
                : "desktop";
            const referrer = document.referrer || "direct";
            const ua = navigator.userAgent;
            const connection = navigator.connection?.effectiveType || "unknown";
            const online = navigator.onLine;
            const touch =
                "ontouchstart" in window || navigator.maxTouchPoints > 0;
            const cookieEnabled = navigator.cookieEnabled;
            const pixelRatio = window.devicePixelRatio || 1;
            const colorDepth = screen.colorDepth || 24;

            const randomId = Math.random().toString(36).substring(2, 14);

            url.searchParams.set("ts", now);
            url.searchParams.set("tz", timezone);
            url.searchParams.set("lang", lang);
            url.searchParams.set("screen", screenSize);
            url.searchParams.set("viewport", viewportSize);
            url.searchParams.set("platform", platform);
            url.searchParams.set("ref", referrer);
            url.searchParams.set("rid", randomId);
            url.searchParams.set("ua", ua);
            url.searchParams.set("connection", connection);
            url.searchParams.set("online", online);
            url.searchParams.set("touch", touch);
            url.searchParams.set("cookie_enabled", cookieEnabled);
            url.searchParams.set("pixel_ratio", pixelRatio);
            url.searchParams.set("color_depth", colorDepth);
            url.searchParams.set("app_name", "MyLabVueApp");

            window.location.href = url.toString();
        }
    },
};
</script>

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

.modern-analysis-table th {
    background-color: #f8fafc;
    color: #64748b;
    font-weight: 600;
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-analysis-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    color: #334155;
}

.modern-analysis-table tr:last-child td {
    border-bottom: none;
}

.parameter-name {
    font-weight: 500;
    min-width: 160px;
}

.parameter-hint {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 4px;
    font-weight: 400;
}

.parameter-method {
    color: #475569;
    font-size: 0.9rem;
}

.parameter-unit {
    color: #475569;
    font-weight: 500;
    text-align: center;
}

.parameter-input {
    min-width: 280px;
}

/* Dual Range Slider Styles */
.dual-range-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.range-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: #64748b;
}

.range-slider-wrapper {
    position: relative;
    height: 24px;
    margin: 8px 0;
}

.range-track {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 4px;
    background: #e2e8f0;
    border-radius: 4px;
    transform: translateY(-50%);
    pointer-events: none;
}

.range-progress {
    position: absolute;
    height: 100%;
    background: #3b82f6;
    border-radius: 4px;
    pointer-events: none;
}

.modern-range {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 2;
}
button.btn-search:disabled {
    background-color: #ccc;
    cursor: not-allowed;
    border-color: #ccc;
}

.modern-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    position: relative;
    z-index: 3;
}

.range-values {
    display: flex;
    justify-content: space-between;
    margin-top: 4px;
}

.value-badge {
    font-size: 0.8rem;
    padding: 4px 8px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #334155;
    font-weight: 500;
}

.min-value::before {
    content: "Min: ";
    opacity: 0.7;
}

.max-value::before {
    content: "Max: ";
    opacity: 0.7;
}

/* Modern Select Styles */
.modern-select-container {
    position: relative;
}

.modern-select {
    appearance: none;
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background-color: white;
    font-size: 0.9rem;
    color: #334155;
    cursor: pointer;
    transition: all 0.2s ease;
    padding-right: 40px;
}

.modern-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.select-arrow {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    color: #64748b;
    font-size: 0.8rem;
}

/* Modern Input Styles */
.modern-input-container {
    position: relative;
}

.modern-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
    color: #334155;
    transition: all 0.2s ease;
    padding-right: 40px;
}

.modern-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}

.input-unit {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 0.85rem;
    pointer-events: none;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #f1f5f9;
}

.action-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    font-size: 0.9rem;
}

.action-button i {
    font-size: 0.9rem;
}

.primary {
    background-color: #3b82f6;
    color: white;
}

.primary:hover {
    background-color: #2563eb;
}

.secondary {
    background-color: white;
    color: #3b82f6;
    border: 1px solid #e2e8f0;
}

.secondary:hover {
    background-color: #f8fafc;
}

/* handle slider */
/* Enhanced Range Slider Styles */
.range-slider-wrapper {
    position: relative;
    height: 32px;
    margin: 16px 0;
    padding: 0 8px;
}

.range-track {
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    transform: translateY(-50%);
    overflow: hidden;
    display: flex;
}

.range-segment {
    height: 100%;
}

.red-segment {
    background: #ef4444;
}

.green-segment {
    background: #10b981;
}

.modern-range {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    margin: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 3;
}

.range-handle {
    position: absolute;
    top: 50%;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transform: translate(-50%, -50%);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    z-index: 4;
    cursor: grab;
    border: 2px solid #3b82f6;
    transition: all 0.2s ease;
}

.range-handle:hover {
    transform: translate(-50%, -50%) scale(1.1);
}

.range-handle:active {
    cursor: grabbing;
    transform: translate(-50%, -50%) scale(1.1);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.min-handle {
    z-index: 5;
}

.handle-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #3b82f6;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    white-space: nowrap;
    margin-bottom: 8px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.range-handle:hover .handle-tooltip {
    opacity: 1;
}

.range-active {
    position: absolute;
    top: 50%;
    height: 6px;
    transform: translateY(-50%);
    z-index: 2;
    border-radius: 3px;
}
</style>

<style>
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
/* Completed Card Styles */
.completed-card {
    position: relative;
    opacity: 0.9;
    border-color: rgba(40, 167, 69, 0.3) !important;
}

.completed-card:hover {
    transform: none !important;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08) !important;
    border-color: rgba(40, 167, 69, 0.3) !important;
    cursor: not-allowed !important;
}

.completed-card::before {
    display: none !important;
}

.completed-card .analysis-icon {
    background: linear-gradient(135deg, #28a745, #218838) !important;
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.2) !important;
}

.completed-card:hover .analysis-icon {
    transform: none !important;
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.2) !important;
}

.completed-badge {
    position: absolute;
    top: 0px;
    right: 0px;
    background: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #28a745;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    z-index: 2;
}

.completed-text {
    font-size: 0.8rem;
    background: rgba(40, 167, 69, 0.15);
    color: #28a745;
    padding: 4px 8px;
    border-radius: 50px;
    margin-left: 8px;
    font-weight: 600;
}

.bg-success-soft {
    background-color: rgba(40, 167, 69, 0.15) !important;
    color: #28a745 !important;
}

/* Analysis Grid */
.analysis-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
}

/* Analysis Card */
.analysis-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(240, 240, 240, 0.8);
    display: flex;
    align-items: flex-start;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.analysis-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.4),
        transparent
    );
    transition: 0.5s;
}

.analysis-card:hover::before {
    left: 100%;
}

.analysis-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 32px rgba(67, 97, 238, 0.2);
    border-color: rgba(67, 97, 238, 0.3);
}

.analysis-card:hover .analysis-icon {
    transform: rotate(10deg) scale(1.1);
    box-shadow: 0 8px 24px rgba(67, 97, 238, 0.3);
}

.analysis-icon {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white;
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    flex-shrink: 0;
    font-size: 1.5rem;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 6px 16px rgba(67, 97, 238, 0.2);
}

.analysis-content {
    flex: 1;
}

.analysis-badge {
    margin-bottom: 12px;
}

.badge.bg-primary-soft {
    background-color: rgba(67, 97, 238, 0.15);
    color: #495057; /* Updated to use #495057 */
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}

.analysis-card:hover .badge.bg-primary-soft {
    background-color: rgba(67, 97, 238, 0.25);
}

.analysis-title {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 12px;
    color: #495057; /* Updated to use #495057 */
    transition: all 0.3s ease;
}

.analysis-meta {
    font-size: 0.9rem;
    color: #495057; /* Updated to use #495057 */
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.meta-item i {
    font-size: 1rem;
    color: #4361ee; /* Only the icon keeps the accent color */
}

.analysis-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
    animation: fadeIn 0.3s ease;
}

.btn-outline-primary {
    border: 2px solid #4361ee;
    color: #495057; /* Updated to use #495057 */
    transition: all 0.3s ease;
    border-radius: 8px;
    padding: 6px 16px;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
}

.btn-outline-primary:hover {
    background-color: #4361ee;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Extended Active Card Styles with 10 color variations */
.analysis-card.active {
    transform: translateY(-4px);
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.15);
    border-color: transparent;
    position: relative;
    z-index: 1;
}

/* Color variations for 10 different cards */
.analysis-card.active:nth-child(10n + 1) {
    border-left: 4px solid #4361ee;
    background: linear-gradient(to right, rgba(67, 97, 238, 0.05), white);
}

.analysis-card.active:nth-child(10n + 2) {
    border-left: 4px solid #3a0ca3;
    background: linear-gradient(to right, rgba(58, 12, 163, 0.05), white);
}

.analysis-card.active:nth-child(10n + 3) {
    border-left: 4px solid #7209b7;
    background: linear-gradient(to right, rgba(114, 9, 183, 0.05), white);
}

.analysis-card.active:nth-child(10n + 4) {
    border-left: 4px solid #f72585;
    background: linear-gradient(to right, rgba(247, 37, 133, 0.05), white);
}

.analysis-card.active:nth-child(10n + 5) {
    border-left: 4px solid #4cc9f0;
    background: linear-gradient(to right, rgba(76, 201, 240, 0.05), white);
}

.analysis-card.active:nth-child(10n + 6) {
    border-left: 4px solid #4895ef;
    background: linear-gradient(to right, rgba(72, 149, 239, 0.05), white);
}

.analysis-card.active:nth-child(10n + 7) {
    border-left: 4px solid #560bad;
    background: linear-gradient(to right, rgba(86, 11, 173, 0.05), white);
}

.analysis-card.active:nth-child(10n + 8) {
    border-left: 4px solid #b5179e;
    background: linear-gradient(to right, rgba(181, 23, 158, 0.05), white);
}

.analysis-card.active:nth-child(10n + 9) {
    border-left: 4px solid #f15bb5;
    background: linear-gradient(to right, rgba(241, 91, 181, 0.05), white);
}

.analysis-card.active:nth-child(10n + 10) {
    border-left: 4px solid #2ec4b6;
    background: linear-gradient(to right, rgba(46, 196, 182, 0.05), white);
}

/* Active icon styles for 10 variations */
.analysis-card.active .analysis-icon {
    transform: scale(1.1);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.analysis-card.active:nth-child(10n + 1) .analysis-icon {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
}

.analysis-card.active:nth-child(10n + 2) .analysis-icon {
    background: linear-gradient(135deg, #3a0ca3, #7209b7);
}

.analysis-card.active:nth-child(10n + 3) .analysis-icon {
    background: linear-gradient(135deg, #7209b7, #f72585);
}

.analysis-card.active:nth-child(10n + 4) .analysis-icon {
    background: linear-gradient(135deg, #f72585, #4361ee);
}

.analysis-card.active:nth-child(10n + 5) .analysis-icon {
    background: linear-gradient(135deg, #4cc9f0, #4895ef);
}

.analysis-card.active:nth-child(10n + 6) .analysis-icon {
    background: linear-gradient(135deg, #4895ef, #560bad);
}

.analysis-card.active:nth-child(10n + 7) .analysis-icon {
    background: linear-gradient(135deg, #560bad, #b5179e);
}

.analysis-card.active:nth-child(10n + 8) .analysis-icon {
    background: linear-gradient(135deg, #b5179e, #f15bb5);
}

.analysis-card.active:nth-child(10n + 9) .analysis-icon {
    background: linear-gradient(135deg, #f15bb5, #2ec4b6);
}

.analysis-card.active:nth-child(10n + 10) .analysis-icon {
    background: linear-gradient(135deg, #2ec4b6, #4361ee);
}

/* Active badge styles for 10 variations */
.analysis-card.active .badge.bg-primary-soft {
    color: white;
    font-weight: 800;
}

.analysis-card.active:nth-child(10n + 1) .badge.bg-primary-soft {
    background-color: #4361ee;
}

.analysis-card.active:nth-child(10n + 2) .badge.bg-primary-soft {
    background-color: #3a0ca3;
}

.analysis-card.active:nth-child(10n + 3) .badge.bg-primary-soft {
    background-color: #7209b7;
}

.analysis-card.active:nth-child(10n + 4) .badge.bg-primary-soft {
    background-color: #f72585;
}

.analysis-card.active:nth-child(10n + 5) .badge.bg-primary-soft {
    background-color: #4cc9f0;
}

.analysis-card.active:nth-child(10n + 6) .badge.bg-primary-soft {
    background-color: #4895ef;
}

.analysis-card.active:nth-child(10n + 7) .badge.bg-primary-soft {
    background-color: #560bad;
}

.analysis-card.active:nth-child(10n + 8) .badge.bg-primary-soft {
    background-color: #b5179e;
}

.analysis-card.active:nth-child(10n + 9) .badge.bg-primary-soft {
    background-color: #f15bb5;
}

.analysis-card.active:nth-child(10n + 10) .badge.bg-primary-soft {
    background-color: #2ec4b6;
}

/* Active title styles */
.analysis-card.active .analysis-title {
    color: #212529;
    font-weight: 800;
    position: relative;
}

/* Add small indicator to active title */
.analysis-card.active .analysis-title::after {
    content: "";
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 40px;
    height: 3px;
    border-radius: 3px;
}

.analysis-card.active:nth-child(10n + 1) .analysis-title::after {
    background-color: #4361ee;
}

.analysis-card.active:nth-child(10n + 2) .analysis-title::after {
    background-color: #3a0ca3;
}

.analysis-card.active:nth-child(10n + 3) .analysis-title::after {
    background-color: #7209b7;
}

.analysis-card.active:nth-child(10n + 4) .analysis-title::after {
    background-color: #f72585;
}

.analysis-card.active:nth-child(10n + 5) .analysis-title::after {
    background-color: #4cc9f0;
}

.analysis-card.active:nth-child(10n + 6) .analysis-title::after {
    background-color: #4895ef;
}

.analysis-card.active:nth-child(10n + 7) .analysis-title::after {
    background-color: #560bad;
}

.analysis-card.active:nth-child(10n + 8) .analysis-title::after {
    background-color: #b5179e;
}

.analysis-card.active:nth-child(10n + 9) .analysis-title::after {
    background-color: #f15bb5;
}

.analysis-card.active:nth-child(10n + 10) .analysis-title::after {
    background-color: #2ec4b6;
}

/* Different pulse animations for each color */
.analysis-card.active:nth-child(10n + 1) {
    animation: pulse-blue 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 2) {
    animation: pulse-purple 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 3) {
    animation: pulse-violet 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 4) {
    animation: pulse-pink 1.5s ease infinite;
}

.analysis-card.active:nth-child(10n + 5) {
    animation: pulse-cyan 1.5s ease infinite;
}

@keyframes pulse-blue {
    0% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(67, 97, 238, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
    }
}

@keyframes pulse-purple {
    0% {
        box-shadow: 0 0 0 0 rgba(58, 12, 163, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(58, 12, 163, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(58, 12, 163, 0);
    }
}

@keyframes pulse-violet {
    0% {
        box-shadow: 0 0 0 0 rgba(114, 9, 183, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(114, 9, 183, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(114, 9, 183, 0);
    }
}

@keyframes pulse-pink {
    0% {
        box-shadow: 0 0 0 0 rgba(247, 37, 133, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(247, 37, 133, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(247, 37, 133, 0);
    }
}

@keyframes pulse-cyan {
    0% {
        box-shadow: 0 0 0 0 rgba(76, 201, 240, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(76, 201, 240, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(76, 201, 240, 0);
    }
}
</style>

<style scoped>
.celebration-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4f0fb 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
}

.confetti-left,
.confetti-right {
    position: absolute;
    top: 0;
    width: 40%; /* atur lebar confetti kiri dan kanan, sesuaikan */
    height: 100%;
    pointer-events: none; /* supaya klik ke konten utama tetap jalan */
    z-index: 10; /* supaya confetti di atas background */
}

.confetti-left {
    left: 0;
}

.confetti-right {
    right: 0;
}

.animation-wrapper {
    position: relative;
    z-index: 2;
}

.celebration-animation {
    height: 250px;
    width: 250px;
    -webkit-filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.2));
    filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.2));
}

.celebration-message {
    text-align: center;
    margin-top: 1.5rem;
    position: relative;
    z-index: 2;
}

.congrats-text {
    font-size: 2rem;
    color: #2c3e50;
    margin-bottom: 0.5rem;
    font-weight: 700;
    background: linear-gradient(to right, #3498db, #2ecc71);
    /* Vendor prefixes for background-clip */
    -webkit-background-clip: text;
    -moz-background-clip: text;
    background-clip: text;
    /* Vendor prefixes for text-fill-color */
    -webkit-text-fill-color: transparent;
    -moz-text-fill-color: transparent;
}

.sample-info {
    font-size: 1.2rem;
    color: #555;
    margin-bottom: 0;
}

.sample-number {
    font-weight: bold;
    color: #2980b9;
}

/* Confetti CSS Pure */
.confetti {
    position: absolute;
    width: 10px;
    height: 10px;
    background-color: #f00;
    opacity: 0;
    -webkit-animation: confetti 5s ease-in-out infinite;
    animation: confetti 5s ease-in-out infinite;
}

.confetti:nth-child(1) {
    background-color: #f00;
    left: 10%;
    -webkit-animation-delay: 0;
    animation-delay: 0;
}
.confetti:nth-child(2) {
    background-color: #0f0;
    left: 20%;
    -webkit-animation-delay: 0.5s;
    animation-delay: 0.5s;
}
.confetti:nth-child(3) {
    background-color: #00f;
    left: 30%;
    -webkit-animation-delay: 1.2s;
    animation-delay: 1.2s;
}
.confetti:nth-child(4) {
    background-color: #ff0;
    left: 40%;
    -webkit-animation-delay: 0.8s;
    animation-delay: 0.8s;
}
.confetti:nth-child(5) {
    background-color: #f0f;
    left: 50%;
    -webkit-animation-delay: 1.5s;
    animation-delay: 1.5s;
}

.uiWah {
    width: 100%;
    height: 100%;
}

@-webkit-keyframes confetti {
    0% {
        opacity: 0;
        -webkit-transform: translateY(0) rotate(0deg);
        transform: translateY(0) rotate(0deg);
    }
    10% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        -webkit-transform: translateY(500px) rotate(720deg);
        transform: translateY(500px) rotate(720deg);
    }
}

@keyframes confetti {
    0% {
        opacity: 0;
        -webkit-transform: translateY(0) rotate(0deg);
        transform: translateY(0) rotate(0deg);
    }
    10% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        -webkit-transform: translateY(500px) rotate(720deg);
        transform: translateY(500px) rotate(720deg);
    }
}

/* Animasi float */
@-webkit-keyframes float {
    0% {
        -webkit-transform: translateY(0px);
        transform: translateY(0px);
    }
    50% {
        -webkit-transform: translateY(-10px);
        transform: translateY(-10px);
    }
    100% {
        -webkit-transform: translateY(0px);
        transform: translateY(0px);
    }
}
@keyframes float {
    0% {
        -webkit-transform: translateY(0px);
        transform: translateY(0px);
    }
    50% {
        -webkit-transform: translateY(-10px);
        transform: translateY(-10px);
    }
    100% {
        -webkit-transform: translateY(0px);
        transform: translateY(0px);
    }
}

.celebration-animation {
    -webkit-animation: float 3s ease-in-out infinite;
    animation: float 3s ease-in-out infinite;
}
</style>
