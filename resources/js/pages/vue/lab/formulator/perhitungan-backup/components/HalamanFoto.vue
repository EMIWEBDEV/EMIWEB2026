<template>
    <div class="camera-panel modern-form">
        <!-- ===== PANEL HEADER ===== -->
        <div class="panel-header d-flex justify-content-between align-items-md-center flex-column flex-md-row mb-4 gap-3">
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
            <button @click="addRow" class="btn btn-outline-primary rounded-pill shadow-sm fw-bold btn-tambah">
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
                                <button @click="removeRow(row.id)" class="btn btn-outline-danger btn-sm shadow-sm action-btn">
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
            <div v-for="(row, index) in sortedRows" :key="row.id" class="mobile-card mb-3">
                <div class="mobile-card-no">{{ index + 1 }}</div>
                <div class="mobile-foto-wrapper">
                    <img v-if="row.url" :src="row.url" class="mobile-foto" @click="openPreview(row.url)" alt="Foto dokumentasi" />
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
                    <button @click="removeRow(row.id)" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-trash me-1"></i> Hapus
                    </button>
                </div>
            </div>
        </div>

        <!-- ===== OVERLAY FOTO (Teleport, z-index 999999) ===== -->
        <Teleport to="body">
            <Transition name="cam-fade">
                <div v-if="isCameraVisible" class="cam-overlay" @keydown.esc="closeCameraModal" tabindex="-1">
                    <div class="cam-backdrop" @click="closeCameraModal"></div>
                    <div class="cam-dialog">

                        <!-- Header -->
                        <div class="cam-header">
                            <div class="cam-header-title">
                                <i class="fas fa-camera me-2"></i> Dokumentasi Foto
                            </div>
                            <button class="cam-close-btn" @click="closeCameraModal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Tab Bar -->
                        <div class="cam-tabs">
                            <button
                                :class="['cam-tab', activeTab === 'langsung' ? 'cam-tab-active' : '']"
                                @click="switchTab('langsung')"
                            >
                                <i class="fas fa-camera me-2"></i> Langsung
                            </button>
                            <button
                                :class="['cam-tab', activeTab === 'upload' ? 'cam-tab-active' : '']"
                                @click="switchTab('upload')"
                            >
                                <i class="fas fa-upload me-2"></i> Upload Foto
                            </button>
                        </div>

                        <!-- ── TAB: LANGSUNG (Kamera) ── -->
                        <template v-if="activeTab === 'langsung'">
                            <div class="cam-selector-bar">
                                <i class="fas fa-video cam-selector-icon"></i>
                                <select class="cam-select" v-model="selectedCameraId" @change="switchCamera" :disabled="isCameraLoading">
                                    <option value="" disabled v-if="availableCameras.length === 0">Mencari kamera...</option>
                                    <option v-for="(cam, idx) in availableCameras" :key="cam.deviceId" :value="cam.deviceId">
                                        {{ cam.label || "Kamera " + (idx + 1) }}
                                    </option>
                                </select>
                            </div>
                            <div class="cam-video-area">
                                <video ref="videoStream" autoplay playsinline class="cam-video"></video>
                                <div v-if="isCameraLoading" class="cam-loading">
                                    <div class="spinner-border text-light mb-2" role="status"></div>
                                    <span class="fw-semibold small">Menyalakan Kamera...</span>
                                </div>
                            </div>
                            <div class="cam-footer">
                                <button type="button" class="cam-btn cam-btn-cancel" @click="closeCameraModal">
                                    <i class="fas fa-times me-1"></i> Batal
                                </button>
                                <button type="button" class="cam-btn cam-btn-capture" @click="capturePhoto" :disabled="isCameraLoading">
                                    <span v-if="isCameraLoading" class="spinner-border spinner-border-sm me-2"></span>
                                    <i v-else class="fas fa-camera me-2"></i>
                                    Capture & Simpan
                                </button>
                            </div>
                        </template>

                        <!-- ── TAB: UPLOAD FOTO ── -->
                        <template v-else>
                            <div class="upload-area-wrapper">
                                <!-- Dropzone -->
                                <div
                                    class="upload-dropzone"
                                    :class="{ 'upload-dropzone-drag': isDragging }"
                                    @click="triggerFileInput"
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop.prevent="handleDrop"
                                >
                                    <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-primary"></i>
                                    <p class="mb-1 fw-semibold text-dark">Klik atau seret foto ke sini</p>
                                    <p class="text-muted small mb-0">
                                        JPG, PNG, HEIC (iPhone) &mdash; bisa pilih beberapa sekaligus
                                    </p>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept="image/*,.heic,.heif"
                                        multiple
                                        style="display: none"
                                        @change="handleFileChange"
                                    />
                                </div>

                                <!-- Daftar file yang dipilih -->
                                <div v-if="uploadFiles.length > 0" class="upload-file-list">
                                    <div class="upload-file-count">
                                        <i class="fas fa-images me-1 text-primary"></i>
                                        <span class="fw-semibold">{{ uploadFiles.filter(f => !f.converting).length }}</span>
                                        <span class="text-muted"> / {{ uploadFiles.length }} foto siap</span>
                                        <span v-if="uploadFiles.some(f => f.converting)" class="ms-2 text-warning small">
                                            <span class="spinner-border spinner-border-sm me-1"></span>Mengkonversi HEIC...
                                        </span>
                                    </div>

                                    <div v-for="(uf, i) in uploadFiles" :key="uf.id" class="upload-file-item">
                                        <!-- Thumbnail -->
                                        <div class="upload-thumb-wrapper">
                                            <div v-if="uf.converting" class="upload-thumb-loading">
                                                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                                <small class="text-muted mt-1">HEIC</small>
                                            </div>
                                            <img v-else-if="uf.url" :src="uf.url" class="upload-thumb" alt="preview" />
                                            <div v-else class="upload-thumb-empty">
                                                <i class="fas fa-exclamation-circle text-danger"></i>
                                            </div>
                                        </div>

                                        <!-- Info + Keterangan -->
                                        <div class="upload-file-info">
                                            <div class="upload-file-name" :title="uf.displayName">{{ uf.displayName }}</div>
                                            <div class="upload-file-size">{{ uf.sizeMB }} MB</div>
                                            <textarea
                                                class="form-control form-control-sm mt-1 upload-note-input"
                                                v-model="uf.note"
                                                rows="2"
                                                placeholder="Keterangan foto (opsional)..."
                                                style="resize: none"
                                            ></textarea>
                                        </div>

                                        <!-- Tombol Hapus -->
                                        <button @click="removeUploadFile(i)" class="upload-remove-btn" title="Hapus foto ini">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="cam-footer">
                                <button type="button" class="cam-btn cam-btn-cancel" @click="closeCameraModal">
                                    <i class="fas fa-times me-1"></i> Batal
                                </button>
                                <button
                                    type="button"
                                    class="cam-btn cam-btn-capture"
                                    @click="saveUploadedFiles"
                                    :disabled="uploadFiles.length === 0 || uploadFiles.some(f => f.converting)"
                                >
                                    <i class="fas fa-check me-2"></i>
                                    Simpan
                                    <span v-if="uploadFiles.filter(f => !f.converting).length > 0">
                                        {{ uploadFiles.filter(f => !f.converting).length }}
                                    </span>
                                    Foto
                                </button>
                            </div>
                        </template>

                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ===== BOOTSTRAP MODAL: PREVIEW FOTO ===== -->
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
                        <img :src="previewUrl" class="w-100 rounded" style="object-fit: contain; max-height: 90vh" alt="Preview foto" />
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
            // tab state
            activeTab: "langsung",
            // upload state
            uploadFiles: [],
            isDragging: false,
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
        this.uploadFiles.forEach((f) => {
            if (f.url) URL.revokeObjectURL(f.url);
        });
        if (this.previewModalInstance) this.previewModalInstance.dispose();
    },
    methods: {
        // ── Row management ──────────────────────────────────────────
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

        // ── Camera ──────────────────────────────────────────────────
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
            this.activeTab = "langsung";
            this.uploadFiles = [];
            this.isCameraVisible = true;
            document.body.style.overflow = "hidden";
            this.$nextTick(() => this.startCamera());
        },
        closeCameraModal() {
            this.stopCamera();
            this.uploadFiles.forEach((f) => {
                if (f.url) URL.revokeObjectURL(f.url);
            });
            this.uploadFiles = [];
            this.isDragging = false;
            this.isCameraVisible = false;
            this.activeRowId = null;
            document.body.style.overflow = "";
        },
        switchTab(tab) {
            if (tab === this.activeTab) return;
            if (tab === "langsung") {
                this.activeTab = "langsung";
                this.$nextTick(() => this.startCamera());
            } else {
                this.stopCamera();
                this.activeTab = "upload";
            }
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

        // ── Upload foto ─────────────────────────────────────────────
        triggerFileInput() {
            this.$refs.fileInput.click();
        },
        handleFileChange(event) {
            const files = Array.from(event.target.files || []);
            if (files.length) this.processFiles(files);
            event.target.value = "";
        },
        handleDrop(event) {
            this.isDragging = false;
            const files = Array.from(event.dataTransfer.files || []).filter((f) =>
                f.type.startsWith("image/") || /\.(heic|heif)$/i.test(f.name)
            );
            if (files.length) this.processFiles(files);
        },
        async processFiles(files) {
            for (const file of files) {
                const id = Date.now().toString() + Math.random().toString(36).substring(2, 9);
                const entry = {
                    id,
                    file: null,
                    url: null,
                    displayName: file.name,
                    sizeMB: (file.size / (1024 * 1024)).toFixed(2),
                    note: "",
                    converting: this.isHeicFile(file),
                };
                this.uploadFiles.push(entry);
                this.processOneFile(file, id);
            }
        },
        async processOneFile(file, id) {
            try {
                const converted = await this.convertHeicIfNeeded(file);
                const url = URL.createObjectURL(converted);
                const idx = this.uploadFiles.findIndex((f) => f.id === id);
                if (idx !== -1) {
                    this.uploadFiles[idx].file = converted;
                    this.uploadFiles[idx].url = url;
                    this.uploadFiles[idx].sizeMB = (converted.size / (1024 * 1024)).toFixed(2);
                    this.uploadFiles[idx].displayName = converted.name;
                    this.uploadFiles[idx].converting = false;
                }
            } catch (err) {
                console.error("Gagal memproses file:", err);
                const idx = this.uploadFiles.findIndex((f) => f.id === id);
                if (idx !== -1) this.uploadFiles[idx].converting = false;
            }
        },
        isHeicFile(file) {
            return (
                /\.(heic|heif)$/i.test(file.name) ||
                file.type === "image/heic" ||
                file.type === "image/heif"
            );
        },
        async convertHeicIfNeeded(file) {
            if (!this.isHeicFile(file)) return file;
            try {
                const heic2any = (await import("heic2any")).default;
                const result = await heic2any({ blob: file, toType: "image/jpeg", quality: 0.9 });
                const blob = Array.isArray(result) ? result[0] : result;
                const newName = file.name.replace(/\.(heic|heif)$/i, ".jpg");
                return new File([blob], newName, { type: "image/jpeg" });
            } catch (err) {
                console.warn("Konversi HEIC gagal, menggunakan file asli:", err);
                return file;
            }
        },
        removeUploadFile(index) {
            const removed = this.uploadFiles.splice(index, 1)[0];
            if (removed && removed.url) URL.revokeObjectURL(removed.url);
        },
        saveUploadedFiles() {
            const readyFiles = this.uploadFiles.filter((f) => !f.converting && f.url && f.file);
            if (readyFiles.length === 0) return;

            const activeIdx = this.tableRows.findIndex((r) => r.id === this.activeRowId);

            if (activeIdx !== -1) {
                const first = readyFiles[0];
                if (this.tableRows[activeIdx].url) URL.revokeObjectURL(this.tableRows[activeIdx].url);
                this.tableRows[activeIdx].url = first.url;
                this.tableRows[activeIdx].file = first.file;
                this.tableRows[activeIdx].fileName = first.displayName;
                if (first.note) this.tableRows[activeIdx].note = first.note;
            }

            const startIdx = activeIdx !== -1 ? 1 : 0;
            for (let i = startIdx; i < readyFiles.length; i++) {
                const f = readyFiles[i];
                this.tableRows.push({
                    id: Date.now().toString() + Math.random().toString(36).substring(2, 5) + i,
                    url: f.url,
                    file: f.file,
                    fileName: f.displayName,
                    note: f.note || "",
                });
            }

            this.uploadFiles = [];
            this.emitData();

            this.stopCamera();
            this.isDragging = false;
            this.isCameraVisible = false;
            this.activeRowId = null;
            document.body.style.overflow = "";
        },

        // ── Preview ─────────────────────────────────────────────────
        openPreview(url) {
            this.previewUrl = url;
            if (this.previewModalInstance) this.previewModalInstance.show();
        },
        closePreview() {
            if (this.previewModalInstance) this.previewModalInstance.hide();
        },

        // ── Emit ────────────────────────────────────────────────────
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
    width: 120px; height: 80px;
    object-fit: cover; transition: transform 0.15s ease;
}
.foto-preview:hover { transform: scale(1.04); }
.empty-foto {
    text-align: center; padding: 16px;
    background: #f8f9fa; border: 2px dashed #dee2e6;
    border-radius: 8px; width: 120px;
}
.action-btn { transition: all 0.18s ease-in-out; }
.action-btn:hover { transform: translateY(-2px); }

/* Mobile card */
.mobile-card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; padding: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); position: relative;
}
.mobile-card-no {
    position: absolute; top: 14px; left: 14px;
    background: #f1f5f9; color: #475569;
    padding: 3px 10px; border-radius: 6px;
    font-size: 0.82rem; font-weight: 600;
    border: 1px solid #e2e8f0; z-index: 1;
}
.mobile-foto-wrapper { margin-top: 38px; margin-bottom: 12px; display: flex; justify-content: center; }
.mobile-foto {
    width: 100%; max-width: 340px; height: 200px;
    object-fit: cover; border-radius: 10px;
    border: 1px solid #e2e8f0; cursor: zoom-in;
}
.mobile-foto-empty {
    width: 100%; max-width: 340px; height: 150px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px;
}
.mobile-section-label { font-size: 0.82rem; font-weight: 600; color: #64748b; margin-bottom: 6px; }
.mobile-aksi-row { display: flex; gap: 10px; margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e2e8f0; }
.mobile-aksi-row .btn { flex: 1; padding: 9px 0; font-size: 0.88rem; }
@media (min-width: 576px) and (max-width: 991.98px) {
    .mobile-foto { max-width: 420px; height: 230px; }
}

/* ════ CAMERA OVERLAY ════ */
.cam-fade-enter-active, .cam-fade-leave-active { transition: opacity 0.22s ease; }
.cam-fade-enter-from, .cam-fade-leave-to { opacity: 0; }
.cam-overlay {
    position: fixed; inset: 0; z-index: 999999;
    display: flex; align-items: center; justify-content: center;
    background: #000;
}
.cam-backdrop { display: none; }
.cam-dialog {
    width: 100%; height: 100%; display: flex; flex-direction: column;
    background: #1a1a1a;
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
}
.cam-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; background: #405189; color: #fff;
    flex-shrink: 0; min-height: 52px;
}
.cam-header-title { font-size: 15px; font-weight: 600; color: #fff; }
.cam-close-btn {
    width: 32px; height: 32px; border-radius: 8px;
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    color: #fff; font-size: 15px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}

/* Tab Bar */
.cam-tabs {
    display: flex; background: #f8f9fa;
    border-bottom: 2px solid #e9ecef; flex-shrink: 0;
}
.cam-tab {
    flex: 1; padding: 11px 16px; background: transparent;
    border: none; border-bottom: 3px solid transparent; margin-bottom: -2px;
    font-weight: 600; font-size: 14px; color: #6c757d; cursor: pointer; transition: all 0.18s;
}
.cam-tab:hover { color: #405189; background: rgba(64,81,137,0.05); }
.cam-tab-active { color: #405189; border-bottom-color: #405189; background: #fff; }

.cam-selector-bar {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; background: #fff;
    border-bottom: 1px solid #e9ecef; flex-shrink: 0;
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
    position: absolute; inset: 0; background: rgba(0,0,0,0.6);
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 10px; color: #fff; z-index: 2;
}

/* Upload area */
.upload-area-wrapper {
    flex: 1 1 auto; overflow-y: auto;
    padding: 16px; background: #f8f9fa; min-height: 0;
}
.upload-dropzone {
    border: 2px dashed #c7d2fe; border-radius: 12px;
    padding: 28px 20px; text-align: center;
    background: #fff; cursor: pointer; transition: all 0.2s; user-select: none;
}
.upload-dropzone:hover,
.upload-dropzone-drag { border-color: #405189; background: #f0f4ff; }
.upload-file-list { margin-top: 14px; }
.upload-file-count {
    display: flex; align-items: center; font-size: 13px;
    margin-bottom: 10px; padding: 6px 10px;
    background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;
}
.upload-file-item {
    display: flex; align-items: flex-start; gap: 12px;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: 12px; margin-bottom: 10px;
}
.upload-thumb-wrapper {
    width: 72px; height: 72px; flex-shrink: 0; border-radius: 8px;
    overflow: hidden; background: #f1f5f9; border: 1px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
}
.upload-thumb { width: 100%; height: 100%; object-fit: cover; }
.upload-thumb-loading { display: flex; flex-direction: column; align-items: center; gap: 4px; }
.upload-thumb-empty { font-size: 22px; }
.upload-file-info { flex: 1; min-width: 0; }
.upload-file-name {
    font-size: 12px; font-weight: 600; color: #1e293b;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%;
}
.upload-file-size { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.upload-note-input { font-size: 12px !important; border-color: #e2e8f0 !important; }
.upload-remove-btn {
    flex-shrink: 0; width: 30px; height: 30px; border-radius: 7px;
    background: #fee2e2; border: 1px solid #fecaca; color: #dc2626;
    font-size: 12px; cursor: pointer; display: flex;
    align-items: center; justify-content: center; transition: all 0.15s; margin-top: 2px;
}
.upload-remove-btn:hover { background: #dc2626; color: #fff; }

.cam-footer {
    display: flex; align-items: center; justify-content: center;
    gap: 12px; padding: 12px 16px;
    background: #fff; flex-shrink: 0; border-top: 1px solid #e9ecef;
}
.cam-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 10px 20px; border-radius: 10px;
    font-size: 14px; font-weight: 600; border: none; cursor: pointer;
    transition: all 0.18s ease; min-height: 44px;
}
.cam-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.cam-btn-cancel { background: #f1f3f5; color: #495057; border: 1px solid #dee2e6; }
.cam-btn-cancel:hover { background: #e9ecef; }
.cam-btn-capture { background: #405189; color: #fff; flex: 1; max-width: 240px; }
.cam-btn-capture:hover:not(:disabled) { background: #344370; }

@media (min-width: 992px) {
    .cam-dialog { padding: 0; }
    .cam-video-area { display: flex; align-items: center; justify-content: center; background: #111; }
    .cam-video { height: 100%; width: auto; max-width: 100%; aspect-ratio: 16 / 9; object-fit: cover; }
}
@media (min-width: 576px) and (max-width: 991.98px) {
    .cam-footer { padding: 14px 20px; }
    .cam-btn { min-height: 48px; font-size: 15px; }
}
</style>
