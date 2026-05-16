<template>
    <div class="camera-panel modern-form">
        <div
            class="panel-header d-flex justify-content-between align-items-md-center flex-column flex-md-row mb-4 gap-3"
        >
            <div>
                <h2 class="h5 fw-bold mb-1">
                    <i class="fas fa-camera me-2"></i> Dokumentasi
                </h2>
                <p
                    class="subtitle text-danger mb-0"
                    v-if="getValidPhotosCount === 0"
                >
                    <i class="fas fa-asterisk me-1" style="font-size: 10px"></i>
                    Wajib melampirkan minimal 1 foto hasil produk sebelum submit
                </p>
                <p class="subtitle text-success mb-0" v-else>
                    <i class="fas fa-check-circle me-1"></i>
                    {{ getValidPhotosCount }} Foto berhasil didokumentasikan
                </p>
            </div>
            <button
                @click="addRow"
                class="btn btn-outline-primary rounded-pill shadow-sm fw-bold"
            >
                <i class="fas fa-plus me-2"></i> Tambah Baris Baru
            </button>
        </div>

        <div class="table-responsive shadow-sm rounded-4 border bg-white">
            <table
                class="table table-bordered table-hover mb-0 align-middle w-100"
            >
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 50px">No</th>
                        <!-- Keterangan dikasih 50% biar berbagi ruang adil sama Gambar -->
                        <th style="width: 25%">Keterangan (Opsional)</th>
                        <th>Gambar</th>
                        <!-- Kolom aksi di-press biar ukurannya ngepas tombol aja -->
                        <th style="width: 1%; white-space: nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Empty State (colspan jadi 4 karena sekarang ada 4 kolom) -->
                    <tr v-if="sortedRows.length === 0">
                        <td colspan="4" class="text-center py-4 text-muted">
                            Belum ada daftar dokumentasi. Silakan klik "Tambah
                            Baris Baru".
                        </td>
                    </tr>

                    <!-- Baris Data -->
                    <tr v-for="(row, index) in sortedRows" :key="row.id">
                        <!-- 1. No -->
                        <td class="text-center fw-bold">{{ index + 1 }}</td>

                        <!-- 2. Keterangan -->
                        <td>
                            <textarea
                                class="form-control form-control-sm border shadow-none bg-white rounded-3 custom-textarea w-100"
                                v-model="row.note"
                                @input="emitData"
                                rows="3"
                                placeholder="Tambahkan keterangan..."
                                style="resize: none"
                            ></textarea>
                        </td>

                        <!-- 3. Gambar -->
                        <td class="text-center">
                            <div
                                v-if="row.url"
                                class="position-relative d-inline-block"
                            >
                                <el-image
                                    :src="row.url"
                                    :preview-src-list="[row.url]"
                                    fit="cover"
                                    class="rounded border shadow-sm"
                                    style="cursor: pointer"
                                />
                            </div>
                            <div
                                v-else
                                class="text-muted p-3 bg-light rounded border-dashed"
                            >
                                <i
                                    class="fas fa-image fa-2x mb-2 text-secondary"
                                ></i>
                                <br /><small>Belum ada foto</small>
                            </div>
                        </td>

                        <!-- 4. Aksi -->
                        <td style="width: 1%; white-space: nowrap">
                            <div
                                class="d-flex justify-content-center gap-2 px-2"
                            >
                                <button
                                    @click="openCameraModal(row.id)"
                                    :class="[
                                        'btn btn-sm shadow-sm fw-bold action-btn',
                                        row.url
                                            ? 'btn-outline-warning'
                                            : 'btn-primary',
                                    ]"
                                >
                                    <i class="fas fa-camera me-1"></i>
                                    {{ row.url ? "Retake Foto" : "Ambil Foto" }}
                                </button>
                                <button
                                    @click="removeRow(row.id)"
                                    class="btn btn-outline-danger btn-sm shadow-sm action-btn"
                                >
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <el-dialog
            v-model="isModalVisible"
            title="Ambil Dokumentasi Foto"
            fullscreen
            center
            :before-close="closeCameraModal"
            destroy-on-close
        >
            <!-- Tambahkan h-100 biar flex container meregang penuh -->
            <div class="d-flex flex-column gap-3 h-100">
                <div class="input-group shadow-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="fas fa-video text-primary"></i>
                    </span>
                    <select
                        class="form-select border-start-0"
                        v-model="selectedCameraId"
                        @change="switchCamera"
                        :disabled="isCameraLoading"
                    >
                        <option
                            value=""
                            disabled
                            v-if="availableCameras.length === 0"
                        >
                            Mencari kamera...
                        </option>
                        <option
                            v-for="(cam, idx) in availableCameras"
                            :key="cam.deviceId"
                            :value="cam.deviceId"
                        >
                            {{ cam.label || "Kamera " + (idx + 1) }}
                        </option>
                    </select>
                </div>

                <!-- Tinggi disesuaikan dengan calc() agar responsif terhadap layar -->
                <!-- Container luar untuk memastikan posisi tetap di tengah dengan tinggi dinamis -->
                <div
                    class="d-flex justify-content-center align-items-center w-100"
                    style="min-height: calc(100vh - 250px)"
                >
                    <!-- Wrapper kamera diberi max-width dan aspect-ratio agar rasionya tetap terjaga -->
                    <div
                        class="camera-wrapper bg-dark rounded-4 overflow-hidden position-relative shadow-sm"
                        style="
                            width: 100%;
                            max-width: 800px;
                            aspect-ratio: 16/9;
                        "
                    >
                        <video
                            id="modal-video-stream"
                            autoplay
                            playsinline
                            class="w-100 h-100 object-fit-cover position-absolute top-0 start-0"
                        ></video>

                        <div
                            v-if="isCameraLoading"
                            class="position-absolute w-100 h-100 text-white d-flex flex-column align-items-center justify-content-center z-3"
                            style="background-color: rgba(0, 0, 0, 0.5)"
                        >
                            <div
                                class="spinner-border mb-2"
                                role="status"
                            ></div>
                            <span>Menyalakan Kamera...</span>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="dialog-footer d-flex justify-content-center gap-2">
                    <el-button @click="closeCameraModal" plain>Batal</el-button>
                    <el-button
                        type="primary"
                        @click="capturePhoto"
                        :loading="isCameraLoading"
                        class="px-4 fw-bold"
                    >
                        <i class="fas fa-camera me-2"></i> Capture & Simpan
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <canvas ref="canvasElement" style="display: none"></canvas>
    </div>
</template>

<script>
import { ElDialog, ElButton, ElImage, ElMessage } from "element-plus";

export default {
    name: "CameraCaptureTable",
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
    components: {
        ElDialog,
        ElButton,
        ElImage,
    },
    data() {
        return {
            tableRows: [],
            isModalVisible: false,
            activeRowId: null,
            availableCameras: [],
            selectedCameraId: "",
            stream: null,
            videoTrack: null,
            isCameraLoading: false,
        };
    },
    computed: {
        sortedRows() {
            return [...this.tableRows].sort((a, b) => {
                if (!a.url && b.url) return -1;
                if (a.url && !b.url) return 1;
                return 0;
            });
        },
        getValidPhotosCount() {
            return this.tableRows.filter((row) => row.url !== null).length;
        },
    },
    async mounted() {
        await this.loadDeviceList();
        this.addRow();
    },
    beforeUnmount() {
        this.stopCamera();
        this.tableRows.forEach((row) => {
            if (row.url) URL.revokeObjectURL(row.url);
        });
    },
    methods: {
        addRow() {
            const newRow = {
                id:
                    Date.now().toString() +
                    Math.random().toString(36).substring(2, 5),
                url: null,
                file: null,
                fileName: "",
                note: "",
            };
            this.tableRows.push(newRow);
            this.emitData();
        },
        removeRow(id) {
            const index = this.tableRows.findIndex((r) => r.id === id);
            if (index !== -1) {
                if (this.tableRows[index].url) {
                    URL.revokeObjectURL(this.tableRows[index].url);
                }
                this.tableRows.splice(index, 1);
                this.emitData();
            }
        },
        async loadDeviceList() {
            try {
                const initialStream = await navigator.mediaDevices.getUserMedia(
                    { video: true }
                );
                const devices = await navigator.mediaDevices.enumerateDevices();

                this.availableCameras = devices.filter(
                    (device) => device.kind === "videoinput"
                );

                if (this.availableCameras.length > 0) {
                    this.selectedCameraId = this.availableCameras[0].deviceId;
                }
                initialStream.getTracks().forEach((track) => track.stop());
            } catch (error) {
                console.error(error);
            }
        },
        async openCameraModal(rowId) {
            this.activeRowId = rowId;
            this.isModalVisible = true;

            this.$nextTick(() => {
                this.startCamera();
            });
        },
        closeCameraModal() {
            this.stopCamera();
            this.isModalVisible = false;
            this.activeRowId = null;
        },
        async switchCamera() {
            await this.startCamera();
        },
        async startCamera() {
            this.stopCamera();
            this.isCameraLoading = true;

            try {
                const constraints = {
                    video: this.selectedCameraId
                        ? {
                              deviceId: { exact: this.selectedCameraId },
                              width: { ideal: 1920 },
                              height: { ideal: 1080 },
                          }
                        : { facingMode: "environment" },
                };

                this.stream = await navigator.mediaDevices.getUserMedia(
                    constraints
                );
                this.videoTrack = this.stream.getVideoTracks()[0];

                const videoEl = document.getElementById("modal-video-stream");
                if (videoEl) {
                    videoEl.srcObject = this.stream;
                }
            } catch (error) {
                ElMessage({
                    message:
                        "Gagal menyalakan kamera. Pastikan izin kamera diberikan atau kamera tidak sedang dipakai aplikasi lain.",
                    type: "error",
                    duration: 5000,
                });
            } finally {
                this.isCameraLoading = false;
            }
        },
        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
                this.videoTrack = null;
            }
        },
        capturePhoto() {
            if (!this.videoTrack) return;

            const videoEl = document.getElementById("modal-video-stream");
            const canvas = this.$refs.canvasElement;
            const context = canvas.getContext("2d");

            const settings = this.videoTrack.getSettings();
            canvas.width = settings.width || videoEl.videoWidth;
            canvas.height = settings.height || videoEl.videoHeight;

            context.drawImage(videoEl, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) return;

                const sizeMB = (blob.size / (1024 * 1024)).toFixed(2);
                const uniqueId = Date.now();
                const newUrl = URL.createObjectURL(blob);

                const rowIndex = this.tableRows.findIndex(
                    (r) => r.id === this.activeRowId
                );
                if (rowIndex !== -1) {
                    if (this.tableRows[rowIndex].url) {
                        URL.revokeObjectURL(this.tableRows[rowIndex].url);
                    }

                    this.tableRows[rowIndex].url = newUrl;
                    this.tableRows[rowIndex].file = blob;
                    this.tableRows[
                        rowIndex
                    ].fileName = `Foto_${this.sampleNumber}_${uniqueId}_${sizeMB}MB.png`;
                }

                this.emitData();
                this.closeCameraModal();
            }, "image/png");
        },
        emitData() {
            const validPhotos = this.tableRows.filter(
                (row) => row.url !== null
            );
            this.$emit("status-photo", validPhotos);
        },
    },
};
</script>

<style scoped>
.modern-form {
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
    background-color: #000;
    position: relative;
    width: 100%;
    margin: 0 auto;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.object-fit-cover {
    object-fit: cover;
}
.action-btn {
    transition: all 0.2s ease-in-out;
}
.action-btn:hover {
    transform: translateY(-2px);
}
.action-btn:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}
.border-dashed {
    border: 2px dashed #dee2e6 !important;
}
.custom-textarea:focus {
    background-color: #f8f9fa !important;
}
</style>
