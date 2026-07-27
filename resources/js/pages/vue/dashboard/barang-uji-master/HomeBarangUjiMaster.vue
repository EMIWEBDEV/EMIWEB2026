<template>
    <div class="bm-page">
        <div class="bm-header">
            <div class="bm-header-left">
                <div class="bm-icon"><i class="ri-flask-line"></i></div>
                <div>
                    <h4 class="bm-title">Master Barang Uji Lab</h4>
                    <p class="bm-subtitle">
                        Atur analisa per user &amp; mesin, lalu <b>Sync</b> ke barang uji lab (semua varian) —
                        preview dulu sebelum dijalankan.
                    </p>
                </div>
            </div>
            <div class="bm-header-actions">
                <button class="bm-btn-ghost" @click="cleanupDuplikat" :disabled="busy.cleanup">
                    <i class="ri-brush-line"></i><span>Bersihkan Duplikat</span>
                </button>
                <a href="/barang-uji-master/create" class="bm-btn-primary">
                    <i class="ri-add-line"></i><span>Tambah Aturan</span>
                </a>
            </div>
        </div>

        <div class="bm-card">
            <div class="bm-toolbar">
                <div class="bm-search">
                    <i class="ri-search-line"></i>
                    <input v-model="keyword" type="text" placeholder="Cari user / analisa / mesin..." @input="onSearch" />
                </div>
                <span class="bm-total" v-if="totalData">{{ totalData }} aturan</span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle bm-table mb-0">
                    <thead>
                        <tr>
                            <th style="width: 46px">#</th>
                            <th>User</th>
                            <th>Jenis Analisa</th>
                            <th>Mesin</th>
                            <th>Role</th>
                            <th>Aktif</th>
                            <th class="text-end" style="width: 150px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading"><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Memuat data...</td></tr>
                        <tr v-else-if="!listData.length"><td colspan="7" class="text-center py-4 text-muted">Belum ada aturan master.</td></tr>
                        <tr v-for="(item, index) in listData" :key="item.id">
                            <td>{{ (page - 1) * limit + index + 1 }}</td>
                            <td>
                                <div class="fw-semibold">{{ item.nama_user || item.Id_User }}</div>
                                <div class="text-muted small">{{ item.Id_User }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ item.kode_analisa || '-' }}</div>
                                <div class="text-muted small">{{ item.jenis_analisa || '-' }}</div>
                            </td>
                            <td>
                                <span v-if="item.Id_Master_Mesin === null" class="bm-badge-all">SEMUA MESIN</span>
                                <span v-else>{{ item.nama_mesin || '-' }}</span>
                            </td>
                            <td><span class="bm-role">{{ item.Kode_Role || '-' }}</span></td>
                            <td>
                                <span :class="item.Flag_Aktif === 'Y' ? 'bm-on' : 'bm-off'">
                                    {{ item.Flag_Aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="bm-act bm-act-sync" title="Sync" @click="openSync(item)"><i class="ri-refresh-line"></i></button>
                                <a :href="'/barang-uji-master/edit/' + item.id" class="bm-act bm-act-edit" title="Edit"><i class="ri-pencil-line"></i></a>
                                <button v-if="canDelete" class="bm-act bm-act-del" title="Hapus" @click="hapus(item)"><i class="ri-delete-bin-6-line"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="bm-pagination" v-if="totalPage > 1">
                <button class="bm-page-btn" :disabled="page <= 1" @click="goPage(page - 1)"><i class="ri-arrow-left-s-line"></i></button>
                <span class="bm-page-info">Halaman {{ page }} / {{ totalPage }}</span>
                <button class="bm-page-btn" :disabled="page >= totalPage" @click="goPage(page + 1)"><i class="ri-arrow-right-s-line"></i></button>
            </div>
        </div>

        <!-- WIDGET LATAR BELAKANG (pojok kanan) -->
        <transition name="bm-widget-fade">
            <div v-if="bgJob.show" class="bm-widget" :class="{ 'bm-widget--done': bgJob.done }">
                <div class="bm-widget-spin">
                    <i v-if="bgJob.done" class="ri-checkbox-circle-line"></i>
                    <span v-else class="spinner-border spinner-border-sm"></span>
                </div>
                <div class="bm-widget-body">
                    <div class="bm-widget-title">
                        {{ bgJob.done ? 'Selesai diproses' : 'Diproses di latar belakang' }}
                    </div>
                    <div class="bm-widget-msg">
                        <template v-if="bgJob.done">Data barang uji sudah disesuaikan.</template>
                        <template v-else>
                            {{ bgJob.msg }}
                            <span v-if="bgJob.pending > 0">· {{ bgJob.pending }} job antre</span>
                        </template>
                    </div>
                </div>
                <button class="bm-widget-close" @click="dismissBgJob"><i class="ri-close-line"></i></button>
            </div>
        </transition>

        <!-- MODAL SYNC / PREVIEW -->
        <div v-if="sync.show" class="bm-modal-backdrop" @click.self="closeSync">
            <div class="bm-modal">
                <div class="bm-modal-hdr">
                    <div>
                        <div class="bm-modal-title"><i class="ri-refresh-line me-2"></i>Sinkronisasi Barang Uji Lab</div>
                        <div class="bm-modal-sub" v-if="sync.rule">
                            {{ sync.rule.Id_User }} · {{ sync.rule.Kode_Role || '-' }} ·
                            {{ sync.rule.all_mesin ? 'Semua Mesin' : 'Mesin tertentu' }}
                        </div>
                    </div>
                    <button class="bm-modal-close" @click="closeSync"><i class="ri-close-line"></i></button>
                </div>

                <div class="bm-modal-body">
                    <div v-if="sync.loading" class="text-center py-4 text-muted">
                        <span class="spinner-border spinner-border-sm me-2"></span> Menghitung data...
                    </div>

                    <template v-else>
                        <div class="bm-summary">
                            <div class="bm-sum-box bm-sum-before">
                                <div class="bm-sum-num">{{ sync.beforeCount }}</div>
                                <div class="bm-sum-label">Sudah ada</div>
                            </div>
                            <div class="bm-sum-box bm-sum-new">
                                <div class="bm-sum-num">{{ sync.toSyncCount }}</div>
                                <div class="bm-sum-label">Akan ditambah</div>
                            </div>
                        </div>

                        <div v-if="sync.toSyncCount === 0" class="bm-empty-sync">
                            <i class="ri-checkbox-circle-line"></i> Semua sudah tersinkron. Tidak ada data baru.
                        </div>

                        <div v-else>
                            <div class="bm-list-title bm-list-title-new">
                                Data baru yang akan di-sync (hijau)
                                <span v-if="sync.toSyncCount > sync.toSync.length" class="text-muted small">
                                    — menampilkan {{ sync.toSync.length }} dari {{ sync.toSyncCount }}
                                </span>
                            </div>
                            <div class="bm-list">
                                <div v-for="(b, i) in sync.toSync" :key="'n' + i" class="bm-row bm-row-new">
                                    <i class="ri-add-circle-line"></i>
                                    <span class="bm-row-kode">{{ b.Kode_Barang }}</span>
                                    <span class="bm-row-nama">{{ b.Nama }}</span>
                                    <span class="bm-row-mesin">{{ b.Nama_Mesin }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="sync.before.length" class="mt-3">
                            <div class="bm-list-title">Data sebelumnya (sudah ada)</div>
                            <div class="bm-list bm-list-before">
                                <div v-for="(b, i) in sync.before" :key="'o' + i" class="bm-row">
                                    <i class="ri-check-line"></i>
                                    <span class="bm-row-kode">{{ b.Kode_Barang }}</span>
                                    <span class="bm-row-mesin">{{ b.Nama_Mesin }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="bm-modal-ftr">
                    <span v-if="sync.processing" class="bm-processing">
                        <span class="spinner-border spinner-border-sm me-2"></span>
                        Diproses di latar belakang…
                    </span>
                    <button class="bm-btn-ghost" :disabled="sync.loading" @click="loadPreview(false)">
                        <i class="ri-refresh-line"></i> Segarkan
                    </button>
                    <button class="bm-btn-ghost" @click="closeSync">Tutup</button>
                    <button
                        class="bm-btn-primary"
                        :disabled="sync.loading || sync.running || sync.processing || sync.toSyncCount === 0"
                        @click="runSync"
                    >
                        <span v-if="sync.running" class="spinner-border spinner-border-sm me-2"></span>
                        <i v-else class="ri-play-line me-1"></i>
                        Jalankan Sync
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { ElMessage, ElMessageBox } from "element-plus";

export default {
    props: { canDelete: { type: Boolean, default: false } },
    data() {
        return {
            listData: [], loading: false, keyword: "",
            page: 1, limit: 10, totalPage: 1, totalData: 0, searchTimer: null,
            busy: { cleanup: false },
            bgJob: { show: false, msg: "", done: false, pending: 0, running: false, timer: null, poll: null, ticks: 0 },
            sync: {
                show: false, loading: false, running: false,
                processing: false, pollTimer: null, pollCount: 0,
                item: null, rule: null,
                before: [], beforeCount: 0, toSync: [], toSyncCount: 0,
            },
        };
    },
    mounted() {
        this.fetchData();
        this.checkBgJob();
    },
    beforeUnmount() {
        this.stopPolling();
        this.stopBgPoll();
        if (this.bgJob.timer) clearTimeout(this.bgJob.timer);
    },
    methods: {
        async fetchData() {
            this.loading = true;
            try {
                const { data } = await axios.get("/api/v1/barang-uji-master/current", {
                    params: { page: this.page, limit: this.limit, q: this.keyword.trim() },
                });
                if (data.success) {
                    this.listData = data.result;
                    this.totalPage = data.total_page;
                    this.totalData = data.total_data;
                } else { this.listData = []; this.totalPage = 1; this.totalData = 0; }
            } catch { this.listData = []; this.totalPage = 1; this.totalData = 0; }
            finally { this.loading = false; }
        },
        onSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => { this.page = 1; this.fetchData(); }, 350);
        },
        goPage(p) { if (p < 1 || p > this.totalPage) return; this.page = p; this.fetchData(); },

        async openSync(item) {
            this.sync.show = true;
            this.sync.item = item;
            this.sync.loading = true;
            this.sync.rule = null;
            this.sync.before = []; this.sync.beforeCount = 0;
            this.sync.toSync = []; this.sync.toSyncCount = 0;
            await this.loadPreview();
        },
        async loadPreview(silent = false) {
            if (!silent) this.sync.loading = true;
            try {
                const { data } = await axios.get("/api/v1/barang-uji-master/preview/" + this.sync.item.id);
                const r = data.result;
                this.sync.rule = r.rule;
                this.sync.before = r.before || [];
                this.sync.beforeCount = r.before_count || 0;
                this.sync.toSync = r.to_sync || [];
                this.sync.toSyncCount = r.to_sync_count || 0;
            } catch (e) {
                if (!silent) {
                    ElMessage.error(e.response?.data?.message || "Gagal memuat preview.");
                    this.closeSync();
                }
            } finally { if (!silent) this.sync.loading = false; }
        },

        // Kirim ke antrian — TIDAK menunggu insert selesai (anti timeout).
        async runSync() {
            this.sync.running = true;
            try {
                const { data } = await axios.post("/api/v1/barang-uji-master/sync/" + this.sync.item.id);
                ElMessage.success(data.message || "Sync dikirim ke antrian.");
                this.sync.processing = true;
                this.startPolling();
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal mengirim sync ke antrian.");
                this.sync.processing = false;
            } finally { this.sync.running = false; }
        },

        startPolling() {
            this.stopPolling();
            this.sync.pollCount = 0;
            this.sync.pollTimer = setInterval(async () => {
                this.sync.pollCount++;
                await this.loadPreview(true);
                if (this.sync.toSyncCount === 0) {
                    this.stopPolling();
                    this.sync.processing = false;
                    ElMessage.success("Sinkronisasi selesai.");
                    this.fetchData();
                } else if (this.sync.pollCount >= 60) {
                    // berhenti memantau setelah ~3 menit; user bisa tekan Segarkan
                    this.stopPolling();
                    this.sync.processing = false;
                }
            }, 3000);
        },
        stopPolling() {
            if (this.sync.pollTimer) {
                clearInterval(this.sync.pollTimer);
                this.sync.pollTimer = null;
            }
        },
        closeSync() {
            this.stopPolling();
            this.sync.processing = false;
            this.sync.show = false;
        },

        async hapus(item) {
            try {
                await ElMessageBox.confirm(
                    "Aturan ini akan dihapus, DAN seluruh data barang uji hasil sync (semua varian × mesin) untuk aturan ini akan dikosongkan di latar belakang. Data yang diinput manual tidak tersentuh. Lanjutkan?",
                    "Hapus Aturan & Kosongkan Data",
                    { confirmButtonText: "Ya, Hapus & Kosongkan", cancelButtonText: "Batal", type: "warning" }
                );
            } catch { return; }
            try {
                const { data } = await axios.delete("/api/v1/barang-uji-master/delete/" + item.id);
                ElMessage.success(data.message || "Aturan dihapus.");
                this.showBgJob("Mencabut data barang uji (hapus aturan)...");
                if (this.listData.length === 1 && this.page > 1) this.page--;
                this.fetchData();
            } catch (e) { ElMessage.error(e.response?.data?.message || "Gagal menghapus."); }
        },

        // ── Widget latar belakang ──
        checkBgJob() {
            const raw = localStorage.getItem("bm_bg_job");
            if (!raw) return;
            localStorage.removeItem("bm_bg_job");
            try {
                const data = JSON.parse(raw);
                // hanya tampilkan kalau baru (<2 menit)
                if (Date.now() - (data.t || 0) < 120000) this.showBgJob(data.msg);
            } catch { /* abaikan */ }
        },
        showBgJob(msg) {
            this.bgJob.msg = msg || "Menyesuaikan data barang uji...";
            this.bgJob.show = true;
            this.bgJob.done = false;
            this.bgJob.pending = 0;
            this.bgJob.running = false;
            this.bgJob.ticks = 0;
            this.startBgPoll();
        },
        startBgPoll() {
            this.stopBgPoll();
            const tick = async () => {
                this.bgJob.ticks++;
                try {
                    const { data } = await axios.get("/api/v1/barang-uji-master/progress");
                    this.bgJob.pending = data.result?.pending ?? 0;

                    // Selesai kalau: sudah pernah lihat job jalan lalu antrian kosong,
                    // ATAU sudah beberapa kali cek dan memang kosong (job cepat/sudah kelar).
                    if (!this.bgJob.running && this.bgJob.pending > 0) this.bgJob.running = true;

                    if (this.bgJob.pending === 0 && (this.bgJob.running || this.bgJob.ticks >= 3)) {
                        this.finishBgJob();
                        return;
                    }
                } catch { /* diamkan, lanjut poll */ }

                if (this.bgJob.ticks >= 100) { this.finishBgJob(); return; } // ~5 menit batas aman
            };
            tick();
            this.bgJob.poll = setInterval(tick, 3000);
        },
        finishBgJob() {
            this.stopBgPoll();
            this.bgJob.done = true;
            this.fetchData();
            if (this.bgJob.timer) clearTimeout(this.bgJob.timer);
            this.bgJob.timer = setTimeout(() => { this.bgJob.show = false; }, 4000);
        },
        stopBgPoll() {
            if (this.bgJob.poll) { clearInterval(this.bgJob.poll); this.bgJob.poll = null; }
        },
        dismissBgJob() {
            this.stopBgPoll();
            this.bgJob.show = false;
            if (this.bgJob.timer) clearTimeout(this.bgJob.timer);
        },

        async cleanupDuplikat() {
            try {
                await ElMessageBox.confirm(
                    "Bersihkan baris duplikat hasil sync di barang uji lab (baris manual dipertahankan)?",
                    "Bersihkan Duplikat", { confirmButtonText: "Ya, Bersihkan", cancelButtonText: "Batal", type: "warning" }
                );
            } catch { return; }
            this.busy.cleanup = true;
            try {
                const { data } = await axios.post("/api/v1/barang-uji-master/cleanup-duplikat");
                ElMessage.success(data.message || "Duplikat dibersihkan.");
            } catch (e) { ElMessage.error(e.response?.data?.message || "Gagal membersihkan."); }
            finally { this.busy.cleanup = false; }
        },
    },
};
</script>

<style scoped>
.bm-page { font-family: "Inter", "Segoe UI", sans-serif; color: #343a40; }
.bm-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; padding: 20px 24px; background: #fff; border-radius: 12px; border: 1px solid #e9ecef; box-shadow: 0 1px 4px rgba(64,81,137,.08); }
.bm-header-left { display: flex; align-items: center; gap: 14px; }
.bm-header-actions { display: flex; gap: 8px; }
.bm-icon { width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0; background: rgba(64,81,137,.1); color: #405189; display: flex; align-items: center; justify-content: center; font-size: 22px; }
.bm-title { font-size: 16px; font-weight: 700; color: #212529; margin: 0 0 3px; }
.bm-subtitle { font-size: 12px; color: #878a99; margin: 0; max-width: 620px; }
.bm-btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; background: #405189; color: #fff; border-radius: 9px; font-size: 13px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: background .2s; }
.bm-btn-primary:hover { background: #35457b; color: #fff; }
.bm-btn-primary:disabled { opacity: .6; cursor: not-allowed; }
.bm-btn-ghost { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; background: #fff; color: #495057; border: 1px solid #e9ecef; border-radius: 9px; font-size: 13px; font-weight: 600; cursor: pointer; }
.bm-btn-ghost:hover { border-color: #adb5bd; }
.bm-card { background: #fff; border: 1px solid #e9ecef; border-radius: 12px; overflow: hidden; }
.bm-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #f0f2f5; }
.bm-search { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #f8f9fc; border: 1px solid #e9ecef; border-radius: 8px; flex: 1; max-width: 420px; color: #878a99; }
.bm-search input { border: none; background: transparent; outline: none; width: 100%; font-size: 13px; color: #343a40; }
.bm-total { font-size: 12px; color: #878a99; white-space: nowrap; }
.bm-table thead th { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: #878a99; font-weight: 600; background: #f8f9fc; border-bottom: 1px solid #e9ecef; }
.bm-table tbody td { font-size: 13px; border-bottom: 1px solid #f2f4f7; }
.bm-badge-all { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #0a7d6b; background: rgba(10,179,156,.12); border: 1px solid rgba(10,179,156,.3); }
.bm-role { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; color: #405189; background: rgba(64,81,137,.1); }
.bm-on { color: #0a7d6b; font-weight: 600; font-size: 12px; }
.bm-off { color: #adb5bd; font-weight: 600; font-size: 12px; }
.bm-act { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 7px; border: 1px solid #e9ecef; background: #fff; color: #495057; cursor: pointer; margin-left: 4px; text-decoration: none; transition: all .15s; }
.bm-act-sync:hover { border-color: #0ab39c; color: #0ab39c; background: rgba(10,179,156,.08); }
.bm-act-edit:hover { border-color: #405189; color: #405189; background: rgba(64,81,137,.08); }
.bm-act-del:hover { border-color: #f06548; color: #f06548; background: rgba(240,101,72,.08); }
.bm-pagination { display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding: 12px 18px; }
.bm-page-info { font-size: 12px; color: #878a99; }
.bm-page-btn { width: 32px; height: 32px; border-radius: 7px; border: 1px solid #e9ecef; background: #fff; color: #495057; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.bm-page-btn:disabled { opacity: .4; cursor: not-allowed; }

/* Modal */
.bm-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 1050; padding: 16px; }
.bm-modal { background: #fff; border-radius: 14px; width: 100%; max-width: 640px; max-height: 88vh; display: flex; flex-direction: column; overflow: hidden; }
.bm-modal-hdr { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: linear-gradient(135deg,#0ab39c,#405189); color: #fff; }
.bm-modal-title { font-size: 15px; font-weight: 700; }
.bm-modal-sub { font-size: 12px; opacity: .9; margin-top: 2px; }
.bm-modal-close { background: rgba(255,255,255,.15); border: none; color: #fff; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; }
.bm-modal-body { padding: 18px 20px; overflow-y: auto; }
.bm-modal-ftr { display: flex; align-items: center; justify-content: flex-end; gap: 9px; padding: 12px 20px; border-top: 1px solid #f0f2f5; }
.bm-processing { margin-right: auto; display: inline-flex; align-items: center; font-size: 12.5px; font-weight: 600; color: #0a7d6b; }
.bm-summary { display: flex; gap: 12px; margin-bottom: 16px; }
.bm-sum-box { flex: 1; border-radius: 10px; padding: 14px; text-align: center; border: 1px solid #e9ecef; }
.bm-sum-before { background: #f8f9fc; }
.bm-sum-new { background: rgba(10,179,156,.08); border-color: rgba(10,179,156,.3); }
.bm-sum-num { font-size: 24px; font-weight: 800; color: #212529; }
.bm-sum-new .bm-sum-num { color: #0a7d6b; }
.bm-sum-label { font-size: 12px; color: #878a99; }
.bm-empty-sync { text-align: center; padding: 18px; color: #0a7d6b; font-weight: 600; background: rgba(10,179,156,.08); border-radius: 10px; }
.bm-list-title { font-size: 12px; font-weight: 700; color: #495057; margin-bottom: 6px; }
.bm-list-title-new { color: #0a7d6b; }
.bm-list { max-height: 240px; overflow-y: auto; border: 1px solid #f0f2f5; border-radius: 8px; }
.bm-list-before { max-height: 160px; }
.bm-row { display: flex; align-items: center; gap: 8px; padding: 7px 11px; font-size: 12.5px; border-bottom: 1px solid #f5f6f8; }
.bm-row i { color: #adb5bd; }
.bm-row-new { background: rgba(10,179,156,.06); }
.bm-row-new i { color: #0ab39c; }
.bm-row-kode { font-family: monospace; font-weight: 600; color: #343a40; }
.bm-row-nama { color: #6b7280; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bm-row-mesin { color: #878a99; font-size: 11px; }

/* Widget latar belakang (pojok kanan) */
.bm-widget {
    position: fixed; top: 84px; right: 22px; z-index: 1080;
    display: flex; align-items: center; gap: 12px;
    background: #fff; border: 1px solid #e9ecef; border-left: 4px solid #0ab39c;
    border-radius: 12px; padding: 12px 14px; width: 320px;
    box-shadow: 0 10px 30px rgba(64,81,137,.18);
}
.bm-widget-spin { color: #0ab39c; display: flex; font-size: 20px; }
.bm-widget--done { border-left-color: #0ab39c; }
.bm-widget-body { flex: 1; min-width: 0; }
.bm-widget-title { font-size: 12.5px; font-weight: 700; color: #212529; }
.bm-widget-msg { font-size: 11.5px; color: #6b7280; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bm-widget-close { background: transparent; border: none; color: #adb5bd; cursor: pointer; font-size: 16px; line-height: 1; }
.bm-widget-close:hover { color: #6b7280; }
.bm-widget-fade-enter-active, .bm-widget-fade-leave-active { transition: all .3s ease; }
.bm-widget-fade-enter-from, .bm-widget-fade-leave-to { opacity: 0; transform: translateX(20px); }
</style>
