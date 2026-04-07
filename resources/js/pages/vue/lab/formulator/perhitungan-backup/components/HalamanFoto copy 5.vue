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
            availableCameras: [],
            selectedCameraId: null,
        };
    },
    mounted() {
        this.initDeviceList();
    },
    beforeUnmount() {
        this.stopCamera();
        if (this.photoData) {
            URL.revokeObjectURL(this.photoData);
        }
    },
    methods: {
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

            try {
                const videoConstraints = {
                    width: { ideal: 3840 },
                    height: { ideal: 2160 },
                    frameRate: { ideal: 15, max: 20 },
                };

                if (this.selectedCameraId) {
                    videoConstraints.deviceId = {
                        exact: this.selectedCameraId,
                    };
                } else {
                    videoConstraints.facingMode = "environment";
                }

                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: videoConstraints,
                    audio: false,
                });
                this.videoTrack = this.stream.getVideoTracks()[0];

                if (!this.videoTrack) {
                    throw new Error("Video track tidak ditemukan");
                }

                if (this.$refs.videoElement) {
                    this.$refs.videoElement.srcObject = this.stream;
                    this.$refs.videoElement.onloadedmetadata = () => {
                        const settings = this.videoTrack.getSettings();
                        this.formatResolutionInfo(
                            settings.width,
                            settings.height
                        );
                    };
                    this.startCountdown();
                } else {
                    this.stopCamera();
                }
            } catch (error) {
                ElMessage({
                    message:
                        "Gagal mengakses kamera. Pastikan Anda memberikan izin akses kamera pada browser.",
                    type: "error",
                    duration: 5000,
                    showClose: true,
                });
            } finally {
                this.isLoadingCamera = false;
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
            if (this.cameraCountdown > 0 || !this.videoTrack) return;

            const video = this.$refs.videoElement;
            const canvas = this.$refs.canvasElement;
            const context = canvas.getContext("2d");

            // Ambil ukuran REAL dari kamera (4K)
            const settings = this.videoTrack.getSettings();
            const width = settings.width;
            const height = settings.height;

            canvas.width = width;
            canvas.height = height;

            context.drawImage(video, 0, 0, width, height);

            // 🔥 EKSPOR KE PNG AGAR 100% ORIGINAL & TIDAK ADA KOMPRESI SAMA SEKALI (~10MB+)
            canvas.toBlob((blob) => {
                if (!blob) return;

                // Hitung ukuran asli file
                const sizeMB = (blob.size / (1024 * 1024)).toFixed(2);

                // Set nama file menjadi .png
                const fileName = `Foto_Lab_${this.sampleNumber}_${sizeMB}MB.png`;
                const url = URL.createObjectURL(blob);

                // Tampilkan hasil di UI
                this.photoData = url;
                this.hasPhoto = true;

                // Trigger Download Otomatis
                const link = document.createElement("a");
                link.href = url;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Stop kamera
                this.stopCamera();
                this.$emit("status-photo", true);

                console.log("📸 ===== INFO FOTO (DOWNLOAD ORIGINAL) =====");
                console.log("Resolusi      :", width + " x " + height);
                console.log("Ukuran Asli   :", sizeMB + " MB");
                console.log("Format        : PNG (Lossless)");
                console.log("============================================");
            }, "image/png"); // KUNCI UTAMANYA DI SINI
        },

        // Pastikan juga menambahkan pembersihan memori (URL.revokeObjectURL) di retakePhoto
        retakePhoto() {
            if (this.photoData && this.photoData.startsWith("blob:")) {
                URL.revokeObjectURL(this.photoData);
            }
            this.photoData = null;
            this.hasPhoto = false;
            this.cameraResolution = "";
            localStorage.removeItem(this.storageKey);
            this.$emit("status-photo", false);
            this.startCamera();
        },
        retakePhoto() {
            if (this.photoData) {
                URL.revokeObjectURL(this.photoData);
            }
            this.photoData = null;
            this.hasPhoto = false;
            this.cameraResolution = "";
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
