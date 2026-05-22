<template>
    <div class="container-fluid px-0 plt-page">
        <!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
        <div class="plt-topbar mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="plt-icon-wrap">
                    <i class="fas fa-flask fa-lg text-white"></i>
                </div>
                <div>
                    <h1 class="h4 fw-bold mb-0 text-dark">Manajemen Uji Palatabilitas</h1>
                    <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                        <span class="badge bg-primary-subtle text-primary px-2 py-1">
                            <i class="fas fa-barcode me-1"></i>{{ No_Po_Sampel }}
                        </span>
                        <span v-if="nama_barang" class="text-muted small">{{ nama_barang }}</span>
                        <span v-if="session" :class="sessionBadgeClass" class="badge px-2 py-1">
                            <i :class="session.is_final ? 'fas fa-lock me-1' : 'fas fa-pencil-alt me-1'"></i>
                            {{ sessionStatusLabel }}
                        </span>
                    </div>
                </div>
                <div class="ms-auto">
                    <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Kembali
                    </a>
                </div>
            </div>
        </div>

        <!-- ══ NO PLT CONFIGURED ═══════════════════════════════════════════════ -->
        <div v-if="!loading.session && plt_analisa.length === 0" class="text-center py-5">
            <div class="mb-3 text-muted" style="font-size:4rem; opacity:0.3"><i class="fas fa-flask"></i></div>
            <h5 class="text-muted">Sampel Ini Tidak Memerlukan Uji Palatabilitas</h5>
            <p class="text-muted small">Tidak ada konfigurasi analisa PLT untuk barang ini.</p>
        </div>

        <!-- ══ LOADING ══════════════════════════════════════════════════════════ -->
        <div v-else-if="loading.session" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-3 text-muted">Memuat data sesi PLT...</p>
        </div>

        <!-- ══ MAIN CONTENT ════════════════════════════════════════════════════ -->
        <div v-else class="row g-4">

            <!-- ── STEP 1 : SESSION ─────────────────────────────────────────── -->
            <div class="col-12">
                <div class="plt-card">
                    <div class="plt-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="step-num">1</span>
                            <div>
                                <h6 class="mb-0 fw-semibold">Sesi PLT</h6>
                                <p class="mb-0 text-muted small">Setiap sampel memiliki satu sesi PLT</p>
                            </div>
                        </div>
                        <div v-if="!session">
                            <button class="btn btn-primary btn-sm" @click="createSession"
                                    :disabled="loading.createSession">
                                <span v-if="loading.createSession" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="fas fa-plus me-1"></i>
                                Mulai Sesi PLT
                            </button>
                        </div>
                        <div v-else>
                            <span class="badge rounded-pill px-3 py-2"
                                  :style="session.is_final ? 'background:#198754' : 'background:#0ea5e9'">
                                <i :class="session.is_final ? 'fas fa-check me-1' : 'fas fa-spinner fa-spin me-1'"></i>
                                {{ session.is_final ? 'Final' : 'Sedang Berjalan' }}
                            </span>
                        </div>
                    </div>
                    <div v-if="!session" class="plt-card-body text-center py-4">
                        <div class="text-muted mb-2" style="font-size:2.5rem; opacity:0.3">
                            <i class="fas fa-flask"></i>
                        </div>
                        <p class="text-muted mb-3">Belum ada sesi PLT untuk sampel ini.</p>
                        <button class="btn btn-primary" @click="createSession" :disabled="loading.createSession">
                            <span v-if="loading.createSession" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="fas fa-play me-2"></i>
                            Mulai Sesi PLT
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── STEP 2 : PEMBANDING ─────────────────────────────────────── -->
            <div v-if="session" class="col-12">
                <div class="plt-card">
                    <div class="plt-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="step-num">2</span>
                            <div>
                                <h6 class="mb-0 fw-semibold">Produk Pembanding</h6>
                                <p class="mb-0 text-muted small">
                                    Daftar produk yang dibandingkan dalam pengujian
                                </p>
                            </div>
                        </div>
                        <span class="badge bg-info-subtle text-info px-3 py-2">
                            {{ session.pembanding ? session.pembanding.length : 0 }} Produk
                        </span>
                    </div>
                    <div class="plt-card-body">
                        <!-- Pembanding list -->
                        <div v-if="session.pembanding && session.pembanding.length > 0" class="mb-4">
                            <div v-for="p in session.pembanding" :key="p.id_pembanding"
                                 class="pembanding-row d-flex align-items-center justify-content-between p-3 mb-2 rounded-3 border">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="pembanding-num">{{ p.urutan }}</div>
                                    <div>
                                        <div class="fw-semibold">{{ p.nama_pembanding }}</div>
                                        <div v-if="p.kode_barang_pembanding" class="text-muted small">
                                            <i class="fas fa-barcode me-1"></i>{{ p.kode_barang_pembanding }}
                                        </div>
                                    </div>
                                </div>
                                <button v-if="!session.is_final"
                                        class="btn btn-sm btn-outline-danger rounded-pill"
                                        @click="removePembanding(p.id_pembanding)"
                                        :disabled="loading.removePembanding === p.id_pembanding">
                                    <span v-if="loading.removePembanding === p.id_pembanding"
                                          class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div v-else-if="!session.is_final" class="text-muted small mb-3">
                            Belum ada produk pembanding. Tambahkan minimal satu.
                        </div>

                        <!-- Add pembanding form -->
                        <div v-if="!session.is_final" class="add-pembanding-form p-3 rounded-3 border-dashed">
                            <h6 class="fw-semibold mb-3 text-muted">
                                <i class="fas fa-plus-circle me-1 text-success"></i>Tambah Produk Pembanding
                            </h6>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small mb-1">Nama Produk*</label>
                                    <input type="text" class="form-control"
                                           placeholder="Contoh: Brand A 30kg"
                                           v-model="newPembanding.nama"
                                           @keydown.enter="addPembanding" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-1">Kode Barang</label>
                                    <input type="text" class="form-control"
                                           placeholder="Opsional"
                                           v-model="newPembanding.kode"
                                           @keydown.enter="addPembanding" />
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-success w-100"
                                            @click="addPembanding"
                                            :disabled="!newPembanding.nama.trim() || loading.addPembanding">
                                        <span v-if="loading.addPembanding"
                                              class="spinner-border spinner-border-sm me-1"></span>
                                        <i v-else class="fas fa-plus me-1"></i>
                                        Tambah
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STEP 3 : HASIL ANALISA MATRIX ──────────────────────────── -->
            <div v-if="session && session.pembanding && session.pembanding.length > 0" class="col-12">
                <div class="plt-card">
                    <div class="plt-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="step-num">3</span>
                            <div>
                                <h6 class="mb-0 fw-semibold">Input Hasil Analisa</h6>
                                <p class="mb-0 text-muted small">
                                    Masukkan hasil uji untuk setiap kombinasi produk &amp; jenis analisa
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge rounded-pill px-3 py-2"
                                  :style="progressPercent === 100 ? 'background:#198754' : 'background:#f59e0b; color:#fff'">
                                <i class="fas fa-tasks me-1"></i>
                                {{ draftDoneCount }}/{{ totalSlotCount }} Terisi
                            </span>
                            <button class="btn btn-sm btn-outline-secondary" @click="loadSementara"
                                    :disabled="loading.sementara">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="plt-card-body">
                        <!-- Progress bar -->
                        <div class="mb-3">
                            <div class="progress" style="height:6px; border-radius:3px;">
                                <div class="progress-bar bg-success" :style="`width:${progressPercent}%`"
                                     role="progressbar"></div>
                            </div>
                        </div>

                        <!-- Results Matrix Table -->
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle plt-matrix-table">
                                <thead>
                                    <tr>
                                        <th class="plt-th-pembanding">
                                            <i class="fas fa-box me-1 text-muted"></i>Produk Pembanding
                                        </th>
                                        <th v-for="analisa in plt_analisa" :key="analisa.id_jenis_analisa"
                                            class="plt-th-analisa text-center">
                                            <div class="fw-bold text-info">{{ analisa.Kode_Analisa }}</div>
                                            <div class="small text-muted fw-normal">{{ analisa.Jenis_Analisa }}</div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="p in session.pembanding" :key="p.id_pembanding">
                                        <td class="plt-td-pembanding">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="pembanding-num-sm">{{ p.urutan }}</span>
                                                <div>
                                                    <div class="fw-semibold small">{{ p.nama_pembanding }}</div>
                                                    <div v-if="p.kode_barang_pembanding"
                                                         class="text-muted" style="font-size:0.72rem;">
                                                        {{ p.kode_barang_pembanding }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td v-for="analisa in plt_analisa" :key="analisa.id_jenis_analisa"
                                            class="text-center plt-td-cell"
                                            :class="{ 'plt-cell-done': getCellStatus(p.id_pembanding, analisa.id_jenis_analisa) === 'F' }">
                                            <!-- Final state -->
                                            <div v-if="getCellStatus(p.id_pembanding, analisa.id_jenis_analisa) === 'F'"
                                                 class="plt-cell-final">
                                                <div class="fw-semibold text-success">
                                                    {{ getCellValue(p.id_pembanding, analisa.id_jenis_analisa) ?? '-' }}
                                                </div>
                                                <div class="small text-success mt-1">
                                                    <i class="fas fa-check-circle"></i> Terkirim
                                                </div>
                                            </div>
                                            <!-- Draft / Empty input -->
                                            <div v-else class="plt-cell-input">
                                                <input
                                                    type="number"
                                                    step="any"
                                                    class="form-control form-control-sm text-center plt-input"
                                                    :class="{ 'is-filled': getCellValue(p.id_pembanding, analisa.id_jenis_analisa) !== null }"
                                                    :value="getCellValue(p.id_pembanding, analisa.id_jenis_analisa)"
                                                    @change="e => queueDraft(p.id_pembanding, analisa.id_jenis_analisa, e.target.value)"
                                                    placeholder="—"
                                                    :disabled="session.is_final || loading.saveDraft[draftKey(p.id_pembanding, analisa.id_jenis_analisa)]"
                                                />
                                                <div v-if="getCellValue(p.id_pembanding, analisa.id_jenis_analisa) !== null"
                                                     class="small text-warning mt-1">
                                                    <i class="fas fa-clock"></i> Draft
                                                </div>
                                                <div v-if="loading.saveDraft[draftKey(p.id_pembanding, analisa.id_jenis_analisa)]"
                                                     class="small text-muted mt-1">
                                                    <span class="spinner-border spinner-border-sm"></span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STEP 4 : FINALISASI ────────────────────────────────────── -->
            <div v-if="session && session.pembanding && session.pembanding.length > 0 && !session.is_final"
                 class="col-12">
                <div class="plt-card plt-card-finalisasi">
                    <div class="plt-card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <h6 class="fw-bold mb-1">
                                    <i class="fas fa-paper-plane me-2 text-primary"></i>Finalisasi Data PLT
                                </h6>
                                <p class="text-muted small mb-0">
                                    Setelah difinalisasi, data dikirim ke sistem dan tidak dapat diubah kembali.
                                </p>
                            </div>
                            <button class="btn btn-primary btn-lg px-5"
                                    @click="finalisasi"
                                    :disabled="!allSlotsFilled || loading.finalisasi">
                                <span v-if="loading.finalisasi" class="spinner-border spinner-border-sm me-2"></span>
                                <i v-else class="fas fa-paper-plane me-2"></i>
                                Finalisasi
                            </button>
                        </div>
                        <div v-if="!allSlotsFilled" class="alert alert-warning mt-3 mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>
                                Lengkapi semua hasil sebelum finalisasi.
                                ({{ draftDoneCount }}/{{ totalSlotCount }} terisi)
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── FINAL CONFIRMATION ──────────────────────────────────────── -->
            <div v-if="session && session.is_final" class="col-12">
                <div class="alert alert-success d-flex align-items-center gap-3 shadow-sm border-0 p-4 rounded-3">
                    <div style="font-size:2rem"><i class="fas fa-check-circle text-success"></i></div>
                    <div>
                        <h6 class="mb-1 fw-bold">Sesi PLT Telah Difinalisasi</h6>
                        <p class="mb-0 small text-muted">
                            Data hasil uji palatabilitas telah dikirim ke sistem dan siap untuk divalidasi.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script>
import axios from "axios";

export default {
    props: {
        No_Po_Sampel: { type: String, required: true },
        plt_analisa:  { type: Array, default: () => [] },
        nama_barang:  { type: String, default: "" },
    },

    data() {
        return {
            session:            null,
            draftMap:           {},
            resamplingPending:  [],
            resamplingInputMap: {},
            newPembanding: { nama: "", kode: "" },
            loading: {
                session:              true,
                createSession:        false,
                addPembanding:        false,
                removePembanding:     null,
                sementara:            false,
                finalisasi:           false,
                saveDraft:            {},
                finalisasiResampling: false,
            },
        };
    },

    computed: {
        sessionBadgeClass() {
            if (!this.session) return "";
            return this.session.is_final
                ? "bg-success text-white"
                : "bg-warning text-dark";
        },
        sessionStatusLabel() {
            if (!this.session) return "";
            return this.session.is_final ? "Final" : "Sedang Berjalan";
        },
        totalSlotCount() {
            if (!this.session || !this.session.pembanding) return 0;
            return this.session.pembanding.length * this.plt_analisa.length;
        },
        draftDoneCount() {
            return Object.values(this.draftMap).filter(
                (v) => v !== null && v !== undefined && v !== ""
            ).length;
        },
        progressPercent() {
            if (this.totalSlotCount === 0) return 0;
            return Math.round((this.draftDoneCount / this.totalSlotCount) * 100);
        },
        allSlotsFilled() {
            return this.totalSlotCount > 0 && this.draftDoneCount >= this.totalSlotCount;
        },
    },

    methods: {
        draftKey(idPembanding, idJenisAnalisa) {
            return `${idPembanding}|${idJenisAnalisa}`;
        },

        getCellValue(idPembanding, idJenisAnalisa) {
            const entry = this.draftMap[this.draftKey(idPembanding, idJenisAnalisa)];
            if (!entry) return null;
            return entry.nilai_hasil_string !== null && entry.nilai_hasil_string !== undefined
                ? entry.nilai_hasil_string
                : entry.hasil;
        },

        getCellStatus(idPembanding, idJenisAnalisa) {
            const entry = this.draftMap[this.draftKey(idPembanding, idJenisAnalisa)];
            return entry?.status ?? null;
        },

        async loadSession() {
            this.loading.session = true;
            try {
                const res = await axios.get("/api/v1/palatabilitas/session", {
                    params: { no_po_sampel: this.No_Po_Sampel },
                });
                if (res.data.success && res.data.result) {
                    this.session = res.data.result;
                    await this.loadSementara();
                } else {
                    this.session = null;
                }
            } catch (e) {
                console.error(e);
                this.session = null;
            } finally {
                this.loading.session = false;
            }
        },

        async loadSementara() {
            if (!this.session) return;
            this.loading.sementara = true;
            try {
                const res = await axios.get("/api/v1/palatabilitas/sementara", {
                    params: { no_po_sampel: this.No_Po_Sampel },
                });
                if (res.data.success) {
                    const map = {};
                    res.data.result.forEach((row) => {
                        const key = this.draftKey(row.id_pembanding, row.id_jenis_analisa);
                        map[key] = {
                            no_urut:  row.no_urut,
                            hasil:    row.hasil,
                            nilai_hasil_string: row.nilai_hasil_string,
                            status:   row.status,
                        };
                    });
                    this.draftMap = map;
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading.sementara = false;
            }
        },

        async createSession() {
            this.loading.createSession = true;
            try {
                await axios.post("/api/v1/palatabilitas/session", {
                    no_po_sampel: this.No_Po_Sampel,
                });
                await this.loadSession();
            } catch (e) {
                const msg = e.response?.data?.message ?? "Gagal membuat sesi PLT.";
                Swal.fire("Gagal", msg, "error");
            } finally {
                this.loading.createSession = false;
            }
        },

        async addPembanding() {
            if (!this.newPembanding.nama.trim()) return;
            this.loading.addPembanding = true;
            try {
                await axios.post("/api/v1/palatabilitas/pembanding", {
                    no_po_sampel:           this.No_Po_Sampel,
                    nama_pembanding:        this.newPembanding.nama.trim(),
                    kode_barang_pembanding: this.newPembanding.kode.trim() || null,
                });
                this.newPembanding = { nama: "", kode: "" };
                await this.loadSession();
            } catch (e) {
                const msg = e.response?.data?.message ?? "Gagal menambah pembanding.";
                Swal.fire("Gagal", msg, "error");
            } finally {
                this.loading.addPembanding = false;
            }
        },

        async removePembanding(idPembanding) {
            const confirm = await Swal.fire({
                title: "Hapus pembanding?",
                text: "Hapus produk pembanding ini dari sesi PLT?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Ya, Hapus",
                cancelButtonText: "Batal",
            });
            if (!confirm.isConfirmed) return;
            this.loading.removePembanding = idPembanding;
            try {
                await axios.delete(`/api/v1/palatabilitas/pembanding/${idPembanding}`);
                await this.loadSession();
            } catch (e) {
                const msg = e.response?.data?.message ?? "Gagal menghapus pembanding.";
                Swal.fire("Gagal", msg, "error");
            } finally {
                this.loading.removePembanding = null;
            }
        },

        async queueDraft(idPembanding, idJenisAnalisa, nilai) {
            if (nilai === "" || nilai === null) return;
            const key = this.draftKey(idPembanding, idJenisAnalisa);
            this.$set(this.loading.saveDraft, key, true);
            try {
                const isNumeric = !isNaN(parseFloat(nilai)) && isFinite(nilai);
                await axios.post("/api/v1/palatabilitas/sementara", {
                    id_session:        this.session.id_session,
                    id_pembanding:     idPembanding,
                    id_jenis_analisa:  idJenisAnalisa,
                    no_po_sampel:      this.No_Po_Sampel,
                    hasil:             isNumeric ? parseFloat(nilai) : null,
                    nilai_hasil_string: !isNumeric ? nilai : null,
                    flag_string:       !isNumeric ? "Y" : "T",
                });
                // Update local draft map
                this.$set(this.draftMap, key, {
                    hasil:              isNumeric ? parseFloat(nilai) : null,
                    nilai_hasil_string: !isNumeric ? nilai : null,
                    status:             null,
                });
            } catch (e) {
                const msg = e.response?.data?.message ?? "Gagal menyimpan draft.";
                Swal.fire("Gagal", msg, "error");
            } finally {
                this.$set(this.loading.saveDraft, key, false);
            }
        },

        async finalisasi() {
            const confirm = await Swal.fire({
                title: "Finalisasi Data PLT?",
                html: `<p>Data hasil uji palatabilitas akan dikirim ke sistem dan <strong>tidak dapat diubah kembali</strong>.</p>
                       <p class="mb-0 text-muted small">Total: ${this.draftDoneCount} data dari ${this.totalSlotCount} slot</p>`,
                icon: "question",
                showCancelButton: true,
                confirmButtonColor: "#0d6efd",
                cancelButtonColor: "#6c757d",
                confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Ya, Finalisasi',
                cancelButtonText: "Batal",
            });
            if (!confirm.isConfirmed) return;

            this.loading.finalisasi = true;
            Swal.fire({
                title: "Memproses...",
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
            try {
                const res = await axios.post("/api/v1/palatabilitas/finalisasi", {
                    no_po_sampel: this.No_Po_Sampel,
                });
                Swal.fire({
                    icon: "success",
                    title: "Berhasil!",
                    text: res.data.message ?? "Data PLT berhasil difinalisasi.",
                    timer: 2500,
                    showConfirmButton: false,
                }).then(() => {
                    this.loadSession();
                });
            } catch (e) {
                const msg = e.response?.data?.message ?? "Gagal finalisasi data PLT.";
                Swal.fire("Gagal", msg, "error");
            } finally {
                this.loading.finalisasi = false;
            }
        },
    },

    mounted() {
        this.loadSession();
    },
};
</script>

<style scoped>
.plt-page {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

/* Top bar */
.plt-topbar {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-left: 4px solid #0ea5e9;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
}

.plt-icon-wrap {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Cards */
.plt-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.07);
    overflow: hidden;
}

.plt-card-header {
    padding: 1.1rem 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid rgba(0, 0, 0, 0.07);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.plt-card-body {
    padding: 1.5rem;
}

.plt-card-finalisasi {
    border: 2px solid #0d6efd;
    background: linear-gradient(135deg, #f0f9ff, #fff);
}

/* Step number */
.step-num {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #0ea5e9, #0284c7);
    color: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}

/* Pembanding rows */
.pembanding-row {
    background: #f8fafc;
    transition: background 0.15s;
}
.pembanding-row:hover {
    background: #e0f2fe;
}

.pembanding-num {
    width: 28px;
    height: 28px;
    background: #0ea5e9;
    color: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    flex-shrink: 0;
}

.pembanding-num-sm {
    width: 22px;
    height: 22px;
    background: #0ea5e9;
    color: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.7rem;
    flex-shrink: 0;
}

/* Add form dashed border */
.border-dashed {
    border: 2px dashed #cbd5e1 !important;
    background: #f8fafc;
}

/* Matrix table */
.plt-matrix-table {
    border-collapse: collapse;
}

.plt-th-pembanding {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    color: #0369a1;
    font-weight: 600;
    min-width: 200px;
    white-space: nowrap;
}

.plt-th-analisa {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    color: #166534;
    font-weight: 600;
    min-width: 160px;
}

.plt-td-pembanding {
    background: #f0f9ff;
    min-width: 200px;
}

.plt-td-cell {
    padding: 0.75rem;
    min-width: 160px;
    transition: background 0.15s;
}

.plt-cell-done {
    background: #f0fdf4 !important;
}

.plt-cell-final {
    padding: 0.25rem;
}

.plt-cell-input {
    padding: 0.25rem;
}

.plt-input {
    max-width: 130px;
    margin: 0 auto;
    text-align: center;
    border-radius: 8px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.plt-input.is-filled {
    border-color: #f59e0b;
    background: #fffbeb;
}

.plt-input:focus {
    border-color: #0ea5e9;
    box-shadow: 0 0 0 0.2rem rgba(14, 165, 233, 0.2);
}
</style>
