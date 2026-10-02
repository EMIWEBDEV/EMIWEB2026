<template>
    <!--
        Pintu masuk sandbox /trial-ui.

        Halaman ini HANYA memilih user. Setelah sesi terbentuk, pengguna
        dialihkan ke halaman LIMS yang asli — lengkap dengan sidebar dan
        topbar Velzon — sehingga tampilannya 100% sama dengan menu biasa.
    -->
    <div class="tu-gate">
        <div class="tu-card">

            <div class="tu-card-h">
                <div class="tu-logo"><i class="ri-flask-line"></i></div>
                <div class="tu-logo-t">
                    <b>LIMS Workflow</b>
                    <em>Sandbox alur pengujian laboratorium</em>
                </div>
            </div>

            <div class="tu-flow">
                <span class="tu-flow-i"><i class="ri-test-tube-line"></i>Uji Sampel</span>
                <i class="ri-arrow-right-s-line"></i>
                <span class="tu-flow-i"><i class="ri-check-double-line"></i>Validasi</span>
                <i class="ri-arrow-right-s-line"></i>
                <span class="tu-flow-i"><i class="ri-shield-check-line"></i>Verifikasi</span>
                <i class="ri-arrow-right-s-line"></i>
                <span class="tu-flow-i"><i class="ri-award-line"></i>Finalisasi</span>
            </div>

            <div class="tu-card-b">
                <p class="tu-lead">Pilih identitas untuk memulai</p>

                <div v-if="memuat" class="tu-load">
                    <div class="spinner-border spinner-border-sm"></div><span>Memuat…</span>
                </div>

                <p v-else-if="!daftarUser.length" class="tu-kosong">
                    User sandbox belum tersedia. Jalankan skrip seed terlebih dahulu.
                </p>

                <template v-else>
                    <!-- Pelaksana: uji sampel, validasi, verifikasi -->
                    <div class="tu-grup">
                        <span class="tu-grup-l">Pelaksana &mdash; input, validasi, dan verifikasi</span>
                        <button v-for="u in pelaksana" :key="u.Id_User" class="tu-up" @click="masuk(u)">
                            <span class="tu-up-av">{{ inisial(u.Nama) }}</span>
                            <span class="tu-up-t">
                                <b>{{ u.Nama }}</b>
                                <em>{{ u.Id_User }}</em>
                                <span v-if="u.aktivitas.length" class="tu-up-k">
                                    <span v-for="a in u.aktivitas" :key="a.Kode_Aktivitas_Lab"
                                          class="tu-k" :class="'akt-' + a.Kode_Aktivitas_Lab.toLowerCase()">
                                        {{ a.Nama_Aktivitas }}
                                        <em>{{ a.Semua_Analisa ? 'semua analisa' : a.Jumlah_Analisa + ' analisa' }}</em>
                                    </span>
                                </span>
                                <span v-if="(u.tahapan || []).includes('REV')" class="tu-up-note">
                                    <i class="ri-edit-circle-line"></i>Hak revisi rekomendasi verifikasi
                                </span>
                            </span>
                            <i class="ri-arrow-right-s-line tu-up-go"></i>
                        </button>
                    </div>

                    <!-- Penanggung jawab rilis -->
                    <div v-if="finalisator.length" class="tu-grup">
                        <span class="tu-grup-l">Penanggung jawab rilis &mdash; finalisasi</span>
                        <button v-for="u in finalisator" :key="u.Id_User" class="tu-up tu-up--fin" @click="masuk(u)">
                            <span class="tu-up-av tu-up-av--fin">{{ inisial(u.Nama) }}</span>
                            <span class="tu-up-t">
                                <b>{{ u.Nama }}</b>
                                <em>{{ u.Id_User }}</em>
                                <span class="tu-up-note">
                                    <i class="ri-information-line"></i>
                                    Memfinalisasi hasil yang sudah diverifikasi pelaksana
                                </span>
                            </span>
                            <i class="ri-arrow-right-s-line tu-up-go"></i>
                        </button>
                    </div>
                </template>
            </div>

            <div class="tu-card-f">
                <i class="ri-information-line"></i>
                Sesi dibentuk seperti login biasa. Seluruh menu tampil dengan
                sidebar dan tampilan LIMS yang sama.
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";

export default {
    name: "TrialUi",
    props: { sesi: { type: Object, default: null } },
    data() {
        return { memuat: false, daftarUser: [] };
    },
    computed: {
        pelaksana() {
            return this.daftarUser.filter((u) => (u.tahapan || []).includes("UJI"));
        },
        finalisator() {
            return this.daftarUser.filter(
                (u) => (u.tahapan || []).includes("FIN") && !(u.tahapan || []).includes("UJI")
            );
        },
    },
    mounted() { this.muatUser(); },
    methods: {
        async muatUser() {
            this.memuat = true;
            try {
                const r = await axios.get("/api/v1/trial-ui/user");
                this.daftarUser = r.data?.result || [];
            } catch { this.daftarUser = []; }
            finally { this.memuat = false; }
        },
        async masuk(u) {
            try {
                const r = await axios.post("/api/v1/trial-ui/masuk", { Id_User: u.Id_User });
                if (r.data?.success) {
                    // Pindah ke halaman LIMS asli — layout, sidebar, dan
                    // topbar seluruhnya sama dengan menu biasa.
                    window.location.href = r.data.redirect || "/lab/home";
                } else {
                    this.toast("error", r.data?.message || "Gagal masuk");
                }
            } catch (e) {
                this.toast("error", e.response?.data?.message || "Gagal masuk");
            }
        },
        inisial(n) {
            return n ? n.trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase() : "?";
        },
        toast(tipe, pesan) {
            const ok = tipe === "success";
            const el = document.createElement("div");
            el.setAttribute("style",
                `position:fixed;bottom:20px;right:20px;z-index:10090;display:flex;align-items:center;gap:8px;` +
                `max-width:min(92vw,420px);padding:11px 17px;border-radius:6px;color:#fff;font-size:.82rem;` +
                `font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.18);background:${ok ? "#0ab39c" : "#f06548"};`);
            const i = document.createElement("i");
            i.className = ok ? "ri-checkbox-circle-line" : "ri-error-warning-line";
            const t = document.createElement("span");
            t.textContent = pesan || "";
            el.appendChild(i); el.appendChild(t);
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        },
    },
};
</script>

<style scoped>
.tu-gate { min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 26px 16px; background: #405189; }

.tu-card { width: 100%; max-width: 520px; background: #fff; border-radius: 8px;
    overflow: hidden; box-shadow: 0 16px 44px rgba(0,0,0,.22); }

.tu-card-h { display: flex; align-items: center; gap: 12px; padding: 18px 22px;
    background: #405189; color: #fff; }
.tu-logo { width: 40px; height: 40px; border-radius: 7px; background: rgba(255,255,255,.17);
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.tu-logo-t { display: flex; flex-direction: column; line-height: 1.32; }
.tu-logo-t b { font-size: 1rem; }
.tu-logo-t em { font-style: normal; font-size: .7rem; opacity: .82; }

.tu-flow { display: flex; align-items: center; justify-content: center; gap: 3px;
    flex-wrap: wrap; padding: 10px 14px; background: #f8f9fc; border-bottom: 1px solid #e9ebec; }
.tu-flow-i { display: inline-flex; align-items: center; gap: 4px; font-size: .71rem;
    font-weight: 600; color: #878a99; }
.tu-flow-i i { font-size: .82rem; }
.tu-flow > i { color: #ced4da; }

.tu-card-b { padding: 18px 22px 8px; }
.tu-lead { font-size: .82rem; color: #495057; font-weight: 600; margin: 0 0 13px; }
.tu-load { display: flex; align-items: center; justify-content: center; gap: 9px;
    padding: 30px; color: #878a99; font-size: .79rem; }
.tu-kosong { text-align: center; color: #878a99; font-size: .79rem; padding: 24px 0; }

.tu-grup { margin-bottom: 15px; }
.tu-grup-l { display: block; font-size: .63rem; text-transform: uppercase;
    letter-spacing: .06em; color: #878a99; font-weight: 700; margin-bottom: 7px; }

.tu-up { display: flex; align-items: center; gap: 11px; width: 100%; text-align: left;
    background: #fff; border: 1px solid #e9ebec; border-radius: 6px; padding: 11px 13px;
    margin-bottom: 7px; cursor: pointer; transition: border-color .14s, background .14s; }
.tu-up:hover { border-color: #405189; background: #f8f9fc; }
.tu-up--fin:hover { border-color: #0ab39c; background: rgba(10,179,156,.05); }
.tu-up-av { width: 38px; height: 38px; border-radius: 6px; background: #405189; color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700;
    font-size: .76rem; flex-shrink: 0; }
.tu-up-av--fin { background: #0ab39c; }
.tu-up-t { flex: 1; min-width: 0; }
.tu-up-t > b { display: block; font-size: .83rem; color: #495057; }
.tu-up-t > em { display: block; font-style: normal; font-size: .66rem; color: #878a99;
    font-family: ui-monospace, monospace; margin-bottom: 4px; }
.tu-up-k { display: flex; flex-wrap: wrap; gap: 4px; }
.tu-k { font-size: .62rem; font-weight: 700; padding: 2px 7px; border-radius: 3px;
    color: #fff; background: #878a99; }
.tu-k em { font-style: normal; opacity: .82; font-weight: 600; margin-left: 3px; }
.akt-anl { background: #405189; } .akt-lckv { background: #0ab39c; } .akt-plt { background: #7c3aed; }
.tu-up-note { display: flex; align-items: center; gap: 4px; font-size: .66rem; color: #878a99; }
.tu-up-go { color: #ced4da; font-size: 1.15rem; }

.tu-card-f { display: flex; align-items: flex-start; gap: 7px; padding: 12px 22px;
    background: #f8f9fc; border-top: 1px solid #e9ebec; font-size: .7rem; color: #878a99; }
.tu-card-f i { margin-top: 1px; }

@media (max-width: 560px) {
    .tu-gate { padding: 14px 10px; }
    .tu-card-h { padding: 15px 16px; }
    .tu-card-b { padding: 15px 16px 6px; }
}
</style>
