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
                            ? `Menyiapkan Canvas... (${cameraCountdown}s)`
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
                const devices = await navigator.mediaDevices.enumerateDevices();

                this.availableCameras = devices.filter(
                    (device) => device.kind === "videoinput"
                );

                if (this.availableCameras.length > 0) {
                    this.selectedCameraId = this.availableCameras[0].deviceId;
                    await this.startCamera();
                } else {
                    throw new Error("No camera found");
                }
            } catch (error) {
                console.error(error);
            }
        },
        async startCamera() {
            if (this.stream) return;
            this.isLoadingCamera = true;
            this.cameraCountdown = 5;
            this.cameraResolution = "";

            try {
                const constraints = {
                    video: {
                        deviceId: this.selectedCameraId
                            ? { exact: this.selectedCameraId }
                            : undefined,
                        width: { ideal: 4096 },
                        height: { ideal: 2160 },
                    },
                    audio: false,
                };

                this.stream = await navigator.mediaDevices.getUserMedia(
                    constraints
                );

                this.videoTrack = this.stream.getVideoTracks()[0];

                if (!this.videoTrack) {
                    throw new Error("Video track tidak ditemukan");
                }

                if (this.$refs.videoElement) {
                    this.$refs.videoElement.srcObject = this.stream;

                    this.$refs.videoElement.onloadedmetadata = () => {
                        const settings = this.videoTrack.getSettings();

                        console.log(
                            "REAL CAMERA:",
                            settings.width + "x" + settings.height
                        );

                        this.formatResolutionInfo(
                            settings.width,
                            settings.height
                        );
                    };

                    this.startCountdown();
                }
            } catch (error) {
                console.error("Camera error:", error);
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
        async compressToTargetSize(canvas, targetMB) {
            const targetBytes = targetMB * 1024 * 1024;

            let minQ = 0.1;
            let maxQ = 1.0;
            let bestImage = null;

            for (let i = 0; i < 10; i++) {
                const midQ = (minQ + maxQ) / 2;
                const imageData = canvas.toDataURL("image/jpeg", midQ);
                const base64String = imageData.split(",")[1];
                const byteLength = atob(base64String).length;

                if (byteLength > targetBytes) {
                    maxQ = midQ;
                } else {
                    minQ = midQ;
                    bestImage = imageData;
                }
            }

            return bestImage;
        },
        async capturePhoto() {
            if (this.cameraCountdown > 0) return;

            const video = this.$refs.videoElement;
            const canvas = this.$refs.canvasElement;
            const context = canvas.getContext("2d");

            if (!this.videoTrack) return;

            const settings = this.videoTrack.getSettings();
            const width = settings.width;
            const height = settings.height;

            canvas.width = width;
            canvas.height = height;

            context.drawImage(video, 0, 0, width, height);

            // 🎯 GANTI SESUAI KEBUTUHAN
            const targetMB = 20; // ubah jadi 2 / 3 / 4

            const imageData = await this.compressToTargetSize(canvas, targetMB);

            this.photoData = imageData;
            this.hasPhoto = true;

            const base64String = imageData.split(",")[1];
            const byteLength = atob(base64String).length;
            const sizeMB = (byteLength / (1024 * 1024)).toFixed(2);

            console.log("📸 Final Size:", sizeMB + " MB");

            localStorage.setItem(this.storageKey, imageData);
            this.stopCamera();
            this.$emit("status-photo", true);
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
