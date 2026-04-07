<template>
    <div class="camera-panel modern-form">
        <div class="panel-header">
            <h2><i class="fas fa-camera me-2"></i> Foto Hasil Uji Lab</h2>
            <p class="subtitle text-danger mb-0" v-if="!hasPhoto">
                * Wajib melampirkan foto hasil produk sebelum submit
            </p>
            <p class="subtitle text-success mb-0" v-else>
                * Foto berhasil diambil
            </p>
        </div>

        <div class="panel-body">
            <div
                v-if="!hasPhoto"
                class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 gap-2"
            >
                <div
                    v-if="availableCameras.length > 1"
                    class="d-flex align-items-center flex-grow-1"
                    style="max-width: 500px"
                >
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-video text-primary"></i>
                        </span>
                        <select
                            class="form-select border-start-0"
                            v-model="selectedCameraId"
                            @change="startCamera"
                            :disabled="isLoadingCamera || cameraCountdown > 0"
                        >
                            <option
                                v-for="cam in availableCameras"
                                :key="cam.deviceId"
                                :value="cam.deviceId"
                            >
                                {{ cam.label || "Kamera Tidak Dikenal" }}
                            </option>
                        </select>
                    </div>
                </div>

                <div v-if="cameraResolution" class="text-md-end">
                    <span
                        class="badge bg-primary bg-gradient px-3 py-2 shadow-sm"
                        style="font-size: 13px; font-weight: 500"
                    >
                        <i class="fas fa-arrows-alt-h me-1 opacity-75"></i>
                        {{ cameraResolution }}
                    </span>
                </div>
            </div>

            <div class="camera-wrapper" :class="{ 'has-photo': hasPhoto }">
                <div v-show="!hasPhoto" class="video-container">
                    <video ref="videoElement" autoplay playsinline></video>
                    <div class="scanner-overlay"></div>
                </div>

                <div v-show="hasPhoto" class="result-container">
                    <img
                        :src="photoData"
                        alt="Hasil Capture"
                        class="captured-image"
                    />
                </div>

                <canvas ref="canvasElement" style="display: none"></canvas>
            </div>

            <div
                class="camera-actions mt-4 d-flex justify-content-center gap-3"
            >
                <!-- <button
                    v-if="!hasPhoto && isFocusSupported"
                    @click="triggerFocus"
                    class="btn btn-outline-info btn-lg rounded-pill px-4 shadow-sm action-btn"
                    :disabled="
                        isLoadingCamera || isFocusing || cameraCountdown > 0
                    "
                >
                    <i
                        class="fas"
                        :class="
                            isFocusing ? 'fa-spinner fa-spin' : 'fa-crosshairs'
                        "
                    ></i>
                    {{ isFocusing ? "Memfokuskan..." : "Fokus" }}
                </button> -->

                <button
                    v-if="!hasPhoto"
                    @click="capturePhoto"
                    class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm action-btn"
                    :disabled="isLoadingCamera || cameraCountdown > 0"
                >
                    <i
                        class="fas"
                        :class="
                            cameraCountdown > 0
                                ? 'fa-spinner fa-spin'
                                : 'fa-camera'
                        "
                    ></i>
                    {{
                        isLoadingCamera
                            ? "Akses Kamera..."
                            : cameraCountdown > 0
                            ? `Menyiapkan Cnavas... (${cameraCountdown}s)`
                            : "Ambil Foto"
                    }}
                </button>

                <button
                    v-else
                    @click="retakePhoto"
                    class="btn btn-warning btn-lg rounded-pill px-5 shadow-sm text-dark action-btn"
                >
                    <i class="fas fa-redo me-2"></i> Ambil Ulang
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ElMessage } from "element-plus";

export default {
    name: "CameraCapture",
    props: {
        sampleNumber: {
            type: [String, Number],
            required: true,
        },
        storageKey: {
            type: String,
            required: true,
        },
    },
    data() {
        return {
            stream: null,
            videoTrack: null,
            photoData: null,
            hasPhoto: false,
            isLoadingCamera: true,
            cameraCountdown: 0,
            countdownTimer: null,
            cameraResolution: "",
            isFocusSupported: false,
            isFocusing: false,
            availableCameras: [],
            selectedCameraId: null,
        };
    },
    watch: {
        storageKey(newVal, oldVal) {
            if (newVal !== oldVal) {
                this.checkExistingPhoto();
            }
        },
    },
    mounted() {
        this.checkExistingPhoto();
    },
    beforeUnmount() {
        this.stopCamera();
    },
    methods: {
        checkExistingPhoto() {
            const savedPhoto = localStorage.getItem(this.storageKey);
            if (savedPhoto) {
                this.photoData = savedPhoto;
                this.hasPhoto = true;
                this.stopCamera();
                this.$emit("status-photo", true);
            } else {
                this.photoData = null;
                this.hasPhoto = false;
                this.$emit("status-photo", false);
                this.initDeviceList();
            }
        },
        async initDeviceList() {
            try {
                const initialStream = await navigator.mediaDevices.getUserMedia(
                    { video: true, audio: false }
                );

                const devices = await navigator.mediaDevices.enumerateDevices();
                this.availableCameras = devices.filter(
                    (device) => device.kind === "videoinput"
                );

                initialStream.getTracks().forEach((track) => track.stop());

                if (this.availableCameras.length > 0) {
                    this.selectedCameraId = this.availableCameras[0].deviceId;
                    this.startCamera();
                } else {
                    throw new Error("No camera found");
                }
            } catch (error) {
                ElMessage({
                    message: "Gagal mendeteksi perangkat kamera.",
                    type: "error",
                    duration: 5000,
                });
                this.isLoadingCamera = false;
            }
        },
        async startCamera() {
            this.stopCamera();
            this.isLoadingCamera = true;
            this.cameraCountdown = 5;
            this.cameraResolution = "";
            this.isFocusSupported = false;

            try {
                const constraints = {
                    video: {},
                    audio: false,
                };

                // Tetap pakai variable kamu
                if (this.selectedCameraId) {
                    constraints.video.deviceId = {
                        exact: this.selectedCameraId,
                    };
                } else {
                    constraints.video.facingMode = "environment";
                }

                this.stream = await navigator.mediaDevices.getUserMedia(
                    constraints
                );

                // Ambil track utama
                this.videoTrack = this.stream.getVideoTracks()[0];

                if (!this.videoTrack) {
                    throw new Error("Video track tidak ditemukan");
                }

                // Ambil capability kamera (jika didukung browser)
                const capabilities = this.videoTrack.getCapabilities
                    ? this.videoTrack.getCapabilities()
                    : null;

                // 🔥 Paksa resolusi maksimal kamera
                if (capabilities && capabilities.width && capabilities.height) {
                    await this.videoTrack.applyConstraints({
                        width: capabilities.width.max,
                        height: capabilities.height.max,
                    });
                }

                // 🔥 Setting Focus (tetap pakai logic kamu)
                if (
                    capabilities &&
                    capabilities.focusMode &&
                    capabilities.focusMode.length > 0
                ) {
                    this.isFocusSupported = true;

                    if (capabilities.focusMode.includes("continuous")) {
                        await this.videoTrack.applyConstraints({
                            advanced: [{ focusMode: "continuous" }],
                        });
                    } else if (capabilities.focusMode.includes("single-shot")) {
                        await this.videoTrack.applyConstraints({
                            advanced: [{ focusMode: "single-shot" }],
                        });
                    }
                }

                // Set ke video element
                if (this.$refs.videoElement) {
                    this.$refs.videoElement.srcObject = this.stream;

                    this.$refs.videoElement.onloadedmetadata = () => {
                        // 🔥 Ambil resolusi REAL yang dipakai kamera
                        const settings = this.videoTrack.getSettings();

                        const width = settings.width;
                        const height = settings.height;

                        this.formatResolutionInfo(width, height);
                    };

                    this.startCountdown();
                } else {
                    this.stopCamera();
                }
            } catch (error) {
                let errorMessage =
                    "Gagal mengakses kamera. Pastikan Anda memberikan izin akses kamera pada browser.";

                if (error.name === "NotReadableError") {
                    errorMessage =
                        "Kamera sedang digunakan oleh tab atau aplikasi lain. Mohon tutup aplikasi tersebut lalu coba lagi.";
                }

                ElMessage({
                    message: errorMessage,
                    type: "error",
                    duration: 5000,
                    showClose: true,
                });
            } finally {
                this.isLoadingCamera = false;
            }
        },
        async triggerFocus() {
            if (!this.videoTrack || !this.isFocusSupported) return;
            this.isFocusing = true;

            try {
                const capabilities = this.videoTrack.getCapabilities();
                if (capabilities.focusMode.includes("single-shot")) {
                    await this.videoTrack.applyConstraints({
                        advanced: [{ focusMode: "single-shot" }],
                    });
                    if (capabilities.focusMode.includes("continuous")) {
                        setTimeout(async () => {
                            if (this.videoTrack) {
                                await this.videoTrack.applyConstraints({
                                    advanced: [{ focusMode: "continuous" }],
                                });
                            }
                        }, 1500);
                    }
                } else if (capabilities.focusMode.includes("continuous")) {
                    if (capabilities.focusMode.includes("manual")) {
                        await this.videoTrack.applyConstraints({
                            advanced: [{ focusMode: "manual" }],
                        });
                    }
                    setTimeout(async () => {
                        if (this.videoTrack) {
                            await this.videoTrack.applyConstraints({
                                advanced: [{ focusMode: "continuous" }],
                            });
                        }
                    }, 500);
                }
            } catch (error) {
                ElMessage({
                    message: "Gagal memfokuskan kamera.",
                    type: "warning",
                    duration: 3000,
                });
            } finally {
                setTimeout(() => {
                    this.isFocusing = false;
                }, 1000);
            }
        },
        formatResolutionInfo(width, height) {
            const megapixel = ((width * height) / 1000000).toFixed(1);
            const ratio = width / height;
            let ratioText = "";

            if (Math.abs(ratio - 16 / 9) < 0.1) {
                ratioText = "16:9";
            } else if (Math.abs(ratio - 4 / 3) < 0.1) {
                ratioText = "4:3";
            } else {
                ratioText = "Custom";
            }
            this.cameraResolution = `${megapixel}MP ${ratioText} (${width}x${height})`;
        },
        startCountdown() {
            if (this.countdownTimer) clearInterval(this.countdownTimer);
            this.countdownTimer = setInterval(() => {
                if (this.cameraCountdown > 0) {
                    this.cameraCountdown--;
                } else {
                    clearInterval(this.countdownTimer);
                }
            }, 1000);
        },
        stopCamera() {
            if (this.countdownTimer) clearInterval(this.countdownTimer);
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
                this.videoTrack = null;
            }
        },
        capturePhoto() {
            if (this.cameraCountdown > 0) return;

            const video = this.$refs.videoElement;
            const canvas = this.$refs.canvasElement;
            const context = canvas.getContext("2d");

            if (!this.videoTrack) return;

            // Ambil ukuran REAL dari kamera
            const settings = this.videoTrack.getSettings();
            const width = settings.width;
            const height = settings.height;

            canvas.width = width;
            canvas.height = height;

            context.drawImage(video, 0, 0, width, height);

            const imageData = canvas.toDataURL("image/png");

            this.photoData = imageData;
            this.hasPhoto = true;

            /* ================================
       🔥 CONSOLE INFO UKURAN FOTO
    ================================= */

            // Hitung megapixel
            const megapixel = ((width * height) / 1000000).toFixed(2);

            // Hitung ukuran base64 dalam byte
            const base64String = imageData.split(",")[1];
            const byteLength = atob(base64String).length;

            const sizeKB = (byteLength / 1024).toFixed(2);
            const sizeMB = (byteLength / (1024 * 1024)).toFixed(2);

            // Perkiraan raw pixel (tanpa kompresi)
            const rawSizeMB = ((width * height * 4) / (1024 * 1024)).toFixed(2);

            console.log("📸 ===== INFO FOTO =====");
            console.log("Resolusi       :", width + " x " + height);
            console.log("Megapixel      :", megapixel + " MP");
            console.log("Ukuran File    :", sizeKB + " KB (" + sizeMB + " MB)");
            console.log("Perkiraan RAW  :", rawSizeMB + " MB (RGBA)");
            console.log("Base64 Length  :", imageData.length);
            console.log("========================");

            /* ================================ */

            try {
                localStorage.setItem(this.storageKey, imageData);
                this.stopCamera();
                this.$emit("status-photo", true);
            } catch (error) {
                ElMessage({
                    message: "Memori lokal penuh, gagal menyimpan foto.",
                    type: "error",
                    duration: 5000,
                    showClose: true,
                });

                this.hasPhoto = false;
                this.photoData = null;
            }
        },
        retakePhoto() {
            this.photoData = null;
            this.hasPhoto = false;
            this.cameraResolution = "";
            localStorage.removeItem(this.storageKey);
            this.$emit("status-photo", false);
            this.startCamera();
        },
    },
};
</script>

<style scoped>
.camera-panel {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    margin-top: 20px;
    border: 1px solid #eaeaea;
    padding: 20px;
}
.panel-header {
    margin-bottom: 20px;
}
.camera-wrapper {
    position: relative;
    width: 100%;
    max-width: 600px;
    margin: 0 auto;
    border-radius: 12px;
    overflow: hidden;

    display: flex;
    align-items: center;
    justify-content: center;
}
.video-container,
.result-container {
    width: 100%;
    height: 100%;
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
}
video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #1a1a1a;
}
.captured-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #1a1a1a;
}

.action-btn {
    transition: all 0.3s ease;
}
.action-btn:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}
</style>
