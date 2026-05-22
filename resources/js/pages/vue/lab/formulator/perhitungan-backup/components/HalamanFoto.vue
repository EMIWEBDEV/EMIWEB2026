<template>
    <div class="camera-panel modern-form">
        <!-- ===== PANEL HEADER ===== -->
        <div
            class="panel-header d-flex justify-content-between align-items-md-center flex-column flex-md-row mb-4 gap-3"
        >
            <div>
                <h2 class="h5 fw-bold mb-1">
                    <i class="fas fa-camera me-2"></i> Dokumentasi
                </h2>
                <p class="subtitle text-danger mb-0" v-if="getValidPhotosCount === 0">
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
                class="btn btn-outline-primary rounded-pill shadow-sm fw-bold btn-tambah"
            >
                <i class="fas fa-plus me-2"></i> Tambah Baris Baru
            </button>
        </div>

        <!-- ===== TABEL (Desktop/Tablet ≥ md) ===== -->
        <div class="d-none d-md-block table-responsive shadow-sm rounded-4 border bg-white">
            <table class="table table-bordered table-hover mb-0 align-middle w-100">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 50px">No</th>
                        <th style="width: 25%">Keterangan (Opsional)</th>
                        <th>Gambar</th>
                        <th style="width: 1%; white-space: nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="sortedRows.length === 0">
                        <td colspan="4" class="text-center py-4 text-muted">
                            Belum ada daftar dokumentasi. Silakan klik "Tambah Baris Baru".
                        </td>
                    </tr>
                    <tr v-for="(row, index) in sortedRows" :key="row.id">
                        <td class="text-center fw-bold">{{ index + 1 }}</td>
                        <td>
                            <textarea
                                class="form-control form-control-sm border shadow-none bg-white rounded-3 w-100"
                                v-model="row.note"
                                @input="emitData"
                                rows="3"
                                placeholder="Tambahkan keterangan..."
                                style="resize: none"
                            ></textarea>
                        </td>
                        <td class="text-center">
                            <div v-if="row.url" class="d-flex justify-content-center">
                                <img
                                    :src="row.url"
                                    class="foto-preview rounded border shadow-sm"
                                    @click="openPreview(row.url)"
                                    style="cursor: zoom-in"
                                    alt="Foto dokumentasi"
                                />
                            </div>
                            <div v-else class="empty-foto mx-auto">
                                <i class="fas fa-image fa-2x mb-2 text-secondary"></i>
                                <br /><small>Belum ada foto</small>
                            </div>
                        </td>
                        <td style="width: 1%; white-space: nowrap">
                            <div class="d-flex justify-content-center gap-2 px-2">
                                <button
                                    @click="openCameraModal(row.id)"
                                    :class="['btn btn-sm shadow-sm fw-bold action-btn', row.url ? 'btn-outline-warning' : 'btn-primary']"
                                >
                                    <i class="fas fa-camera me-1"></i>
                                    {{ row.url ? "Retake" : "Ambil Foto" }}
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

        <!-- ===== CARD LIST (Mobile < md) ===== -->
        <div class="d-md-none">
            <div v-if="sortedRows.length === 0" class="text-center py-4 text-muted bg-white rounded-4 border shadow-sm">
                <i class="fas fa-images fa-2x mb-2 text-secondary d-block"></i>
                Belum ada daftar dokumentasi. Silakan klik "Tambah Baris Baru".
            </div>
            <div
                v-for="(row, index) in sortedRows"
                :key="row.id"
                class="mobile-card mb-3"
            >
                <div class="mobile-card-no">{{ index + 1 }}</div>

                <div class="mobile-foto-wrapper">
                    <img
                        v-if="row.url"
                        :src="row.url"
                        class="mobile-foto"
                        @click="openPreview(row.url)"
                        alt="Foto dokumentasi"
                    />
                    <div v-else class="mobile-foto-empty">
                        <i class="fas fa-image fa-2x text-secondary mb-1"></i>
                        <small class="text-muted">Belum ada foto</small>
                    </div>
                </div>

                <div class="mobile-section-label">Keterangan (Opsional)</div>
                <textarea
                    class="form-control form-control-sm border shadow-none bg-white rounded-3 w-100"
                    v-model="row.note"
                    @input="emitData"
                    rows="2"
                    placeholder="Tambahkan keterangan..."
                    style="resize: none"
                ></textarea>

                <div class="mobile-aksi-row">
                    <button
                        @click="openCameraModal(row.id)"
                        :class="['btn btn-sm fw-bold flex-fill', row.url ? 'btn-outline-warning' : 'btn-primary']"
                    >
                        <i class="fas fa-camera me-1"></i>
                        {{ row.url ? "Retake Foto" : "Ambil Foto" }}
                    </button>
                    <button
                        @click="removeRow(row.id)"
                        class="btn btn-outline-danger btn-sm"
                    >
                        <i class="fas fa-trash me-1"></i> Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== CUSTOM CAMERA OVERLAY (Teleport, z-index 999999) ===== -->
        <Teleport to="body">
            <Transition name="cam-fade">
                <div
                    v-if="isCameraVisible"
                    class="cam-overlay"
                    @keydown.esc="closeCameraModal"
                    tabindex="-1"
                >
                    <div class="cam-backdrop" @click="closeCameraModal"></div>
                    <div class="cam-dialog">
                        <div class="cam-header">
                            <div class="cam-header-title">
                                <i class="fas fa-camera me-2"></i>
                                Ambil Dokumentasi Foto
                            </div>
                            <button class="cam-close-btn" @click="closeCameraModal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="cam-selector-bar">
                            <i class="fas fa-video cam-selector-icon"></i>
                            <select
                                class="cam-select"
                                v-model="selectedCameraId"
                                @change="switchCamera"
                                :disabled="isCameraLoading"
                            >
                                <option value="" disabled v-if="availableCameras.length === 0">
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

                        <div class="cam-video-area">
                            <video
                                ref="videoStream"
                                autoplay
                                playsinline
                                class="cam-video"
                            ></video>
                            <div v-if="isCameraLoading" class="cam-loading">
                                <div class="spinner-border text-light mb-2" role="status"></div>
                                <span class="fw-semibold small">Menyalakan Kamera...</span>
                            </div>
                        </div>

                        <div class="cam-footer">
                            <button type="button" class="cam-btn cam-btn-cancel" @click="closeCameraModal">
                                <i class="fas fa-times me-1"></i> Batal
                            </button>
                            <button
                                type="button"
                                class="cam-btn cam-btn-capture"
                                @click="capturePhoto"
                                :disabled="isCameraLoading"
                            >
                                <span v-if="isCameraLoading" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="fas fa-camera me-2"></i>
                                Capture & Simpan
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ===== PREVIEW FOTO MODAL ===== -->
        <div class="modal fade" id="previewFotoModalFormulator" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content border-0 bg-transparent shadow-none">
                    <div class="modal-body p-0 position-relative">
                        <button
                            type="button"
                            class="btn btn-dark btn-sm position-absolute top-0 end-0 m-2 z-3 rounded-circle"
                            style="width: 34px; height: 34px"
                            @click="closePreview"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                        <img
                            :src="previewUrl"
                            class="w-100 rounded"
                            style="object-fit: contain; max-height: 90vh"
                            alt="Preview foto"
                        />
                    </div>
                </div>
            </div>
        </div>

        <canvas ref="canvasElement" style="display: none"></canvas>
    </div>
</template>

<script>
export default {
    name: "CameraCaptureTableFormulator",
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
            tableRows: [],
            activeRowId: null,
            availableCameras: [],
            selectedCameraId: "",
            stream: null,
            videoTrack: null,
            isCameraLoading: false,
            isCameraVisible: false,
            previewUrl: null,
            previewModalInstance: null,
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
        this.$nextTick(() => {
            const previewEl = document.getElementById("previewFotoModalFormulator");
            if (previewEl) {
                this.previewModalInstance = new bootstrap.Modal(previewEl);
            }
        });
    },
    beforeUnmount() {
        this.stopCamera();
        this.tableRows.forEach((row) => {
            if (row.url) URL.revokeObjectURL(row.url);
        });
        if (this.previewModalInstance) this.previewModalInstance.dispose();
    },
    methods: {
        addRow() {
            this.tableRows.push({
                id: Date.now().toString() + Math.random().toString(36).substring(2, 5),
                url: null,
                file: null,
                fileName: "",
                note: "",
            });
            this.emitData();
        },
        removeRow(id) {
            const index = this.tableRows.findIndex((r) => r.id === id);
            if (index !== -1) {
                if (this.tableRows[index].url) URL.revokeObjectURL(this.tableRows[index].url);
                this.tableRows.splice(index, 1);
                this.emitData();
            }
        },
        async loadDeviceList() {
            try {
                const initialStream = await navigator.mediaDevices.getUserMedia({ video: true });
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.availableCameras = devices.filter((d) => d.kind === "videoinput");
                if (this.availableCameras.length > 0) {
                    this.selectedCameraId = this.availableCameras[0].deviceId;
                }
                initialStream.getTracks().forEach((t) => t.stop());
            } catch (error) {
                console.error(error);
            }
        },
        async openCameraModal(rowId) {
            this.activeRowId = rowId;
            this.isCameraVisible = true;
            document.body.style.overflow = "hidden";
            this.$nextTick(() => this.startCamera());
        },
        closeCameraModal() {
            this.stopCamera();
            this.isCameraVisible = false;
            this.activeRowId = null;
            document.body.style.overflow = "";
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
                        ? { deviceId: { exact: this.selectedCameraId }, width: { ideal: 1920 }, height: { ideal: 1080 } }
                        : { facingMode: "environment" },
                };
                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                this.videoTrack = this.stream.getVideoTracks()[0];
                const videoEl = this.$refs.videoStream;
                if (videoEl) videoEl.srcObject = this.stream;
            } catch {
                const toastEl = document.createElement("div");
                toastEl.className = "toast align-items-center text-bg-danger border-0 position-fixed bottom-0 end-0 m-3";
                toastEl.setAttribute("role", "alert");
                toastEl.style.zIndex = "9999999";
                toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">Gagal menyalakan kamera. Pastikan izin kamera diberikan.</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
                document.body.appendChild(toastEl);
                if (window.bootstrap) {
                    new bootstrap.Toast(toastEl, { delay: 5000 }).show();
                    toastEl.addEventListener("hidden.bs.toast", () => toastEl.remove());
                }
            } finally {
                this.isCameraLoading = false;
            }
        },
        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach((t) => t.stop());
                this.stream = null;
                this.videoTrack = null;
            }
        },
        capturePhoto() {
            if (!this.videoTrack) return;
            const videoEl = this.$refs.videoStream;
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
                const rowIndex = this.tableRows.findIndex((r) => r.id === this.activeRowId);
                if (rowIndex !== -1) {
                    if (this.tableRows[rowIndex].url) URL.revokeObjectURL(this.tableRows[rowIndex].url);
                    this.tableRows[rowIndex].url = newUrl;
                    this.tableRows[rowIndex].file = blob;
                    this.tableRows[rowIndex].fileName = `Foto_${this.sampleNumber}_${uniqueId}_${sizeMB}MB.png`;
                }
                this.emitData();
                this.closeCameraModal();
            }, "image/png");
        },
        openPreview(url) {
            this.previewUrl = url;
            if (this.previewModalInstance) this.previewModalInstance.show();
        },
        closePreview() {
            if (this.previewModalInstance) this.previewModalInstance.hide();
        },
        emitData() {
            const validPhotos = this.tableRows.filter((row) => row.url !== null);
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
@media (max-width: 575.98px) {
    .btn-tambah { width: 100%; }
}
.foto-preview {
    width: 120px;
    height: 80px;
    object-fit: cover;
    transition: transform 0.15s ease;
}
.foto-preview:hover { transform: scale(1.04); }
.empty-foto {
    text-align: center;
    padding: 16px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    width: 120px;
}
.action-btn { transition: all 0.18s ease-in-out; }
.action-btn:hover { transform: translateY(-2px); }

/* Mobile card */
.mobile-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    position: relative;
}
.mobile-card-no {
    position: absolute;
    top: 14px;
    left: 14px;
    background: #f1f5f9;
    color: #475569;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    border: 1px solid #e2e8f0;
    z-index: 1;
}
.mobile-foto-wrapper {
    margin-top: 38px;
    margin-bottom: 12px;
    display: flex;
    justify-content: center;
}
.mobile-foto {
    width: 100%;
    max-width: 340px;
    height: 200px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    cursor: zoom-in;
}
.mobile-foto-empty {
    width: 100%;
    max-width: 340px;
    height: 150px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 10px;
}
.mobile-section-label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 6px;
}
.mobile-aksi-row {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #e2e8f0;
}
.mobile-aksi-row .btn { flex: 1; padding: 9px 0; font-size: 0.88rem; }

/* Camera overlay transition */
.cam-fade-enter-active, .cam-fade-leave-active { transition: opacity 0.22s ease; }
.cam-fade-enter-from, .cam-fade-leave-to { opacity: 0; }

/* Camera overlay — z-index 999999, covers everything */
.cam-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
}
.cam-backdrop { display: none; }
.cam-dialog {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    background: #1a1a1a;
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
.cam-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #405189;
    color: #fff;
    flex-shrink: 0;
    min-height: 52px;
}
.cam-header-title { font-size: 15px; font-weight: 600; color: #fff; }
.cam-close-btn {
    width: 32px; height: 32px;
    border-radius: 8px;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff; font-size: 15px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.cam-selector-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    background: #fff;
    border-bottom: 1px solid #e9ecef;
    flex-shrink: 0;
}
.cam-selector-icon { color: #405189; font-size: 16px; flex-shrink: 0; }
.cam-select {
    flex: 1; border: 1px solid #dee2e6; border-radius: 8px;
    padding: 8px 12px; font-size: 14px; background: #fff;
    outline: none; -webkit-appearance: none; appearance: none;
}
.cam-select:focus { border-color: #405189; }
.cam-select:disabled { opacity: 0.6; }
.cam-video-area {
    flex: 1 1 auto; position: relative;
    background: #000; overflow: hidden; min-height: 0;
}
.cam-video { width: 100%; height: 100%; object-fit: cover; display: block; }
.cam-loading {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.6);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 10px; color: #fff; z-index: 2;
}
.cam-footer {
    display: flex; align-items: center; justify-content: center;
    gap: 12px; padding: 12px 16px;
    background: #fff; flex-shrink: 0;
    border-top: 1px solid #e9ecef;
}
.cam-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 10px 20px; border-radius: 10px;
    font-size: 14px; font-weight: 600; border: none; cursor: pointer;
    transition: all 0.18s ease; min-height: 44px;
}
.cam-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.cam-btn-cancel { background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; }
.cam-btn-capture { background: #405189; color: #fff; flex: 1; max-width: 240px; }
.cam-btn-capture:hover:not(:disabled) { background: #344370; }

/* Tablet (576–991px): fullscreen, footer sedikit lebih besar */
@media (min-width: 576px) and (max-width: 991.98px) {
    .cam-footer {
        padding: 14px 20px;
    }
    .cam-btn {
        min-height: 48px;
        font-size: 15px;
    }
}

/* Desktop (≥ 992px): fullscreen overlay, video 16:9 centered */
@media (min-width: 992px) {
    .cam-dialog { padding: 0; }
    .cam-video-area {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #111;
    }
    .cam-video {
        height: 100%;
        width: auto;
        max-width: 100%;
        aspect-ratio: 16 / 9;
        object-fit: cover;
    }
}
</style>
