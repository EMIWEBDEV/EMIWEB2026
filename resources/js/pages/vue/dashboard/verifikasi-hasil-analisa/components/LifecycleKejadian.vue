<template>
    <!--
        Satu kejadian pada garis waktu Sample Lifecycle: registrasi, uji
        sampel, validasi, resampling, verifikasi, finalisasi, atau (mode
        audit) draf dan sesi. Bentuk datanya dari LifecycleSampelService.
    -->
    <li class="kj" :class="['t-' + k.tingkat, 'j-' + k.jenis, { 'is-audit': k.audit }]">
        <span class="kj-dot"><i :class="ikon"></i></span>

        <div class="kj-c">
            <div class="kj-h">
                <b class="kj-judul">{{ k.judul }}</b>
                <span v-if="k.label" class="kj-pill" :class="'p-' + k.tingkat">{{ k.label }}</span>
            </div>

            <!-- Siapa · kapan -->
            <div class="kj-m">
                <span v-if="chip" class="kj-kl">{{ chip }}</span>
                <span v-for="(o, i) in pelakuTampil" :key="'p' + i" class="kj-org">
                    <i class="ri-user-3-line"></i><b>{{ o.nama }}</b>
                    <small v-if="o.peran">{{ o.peran }}<template v-if="audit && o.id && o.id !== o.nama"> · {{ o.id }}</template></small>
                </span>
                <span v-if="pelakuLebih" class="kj-org kj-lebih">+{{ pelakuLebih }} lainnya</span>
                <span v-if="k.waktu" class="kj-wkt"><i class="ri-time-line"></i>{{ rentangWaktu }}</span>
            </div>

            <p v-if="k.ringkas" class="kj-r">{{ k.ringkas }}</p>

            <!-- Registrasi: klasifikasi yang dijadwalkan -->
            <div v-if="k.jadwal && k.jadwal.length" class="kj-jadwal">
                <span v-for="(j, i) in k.jadwal" :key="i">{{ j }}</span>
            </div>

            <!-- Uji sampel: satu baris per analisa -->
            <div v-if="k.analisa && k.analisa.length" class="kj-an">
                <div v-for="a in k.analisa" :key="a.id_jenis_analisa" class="kj-an-i" :class="'s-' + a.status">
                    <div class="kj-an-h">
                        <span class="kj-an-n">{{ a.nama }}</span>
                        <span v-if="a.status !== 'info'" class="kj-st" :class="'s-' + a.status">{{ labelStatus(a.status) }}</span>
                    </div>

                    <!-- Palatabilitas: nilai hasil saja, seperti tampilan lama -->
                    <div v-if="a.rincian" class="kj-plt">
                        <span v-for="(r, i) in a.rincian.slice(0, lihatSemua ? 40 : 4)" :key="i" class="kj-plt-i">
                            {{ nilai(r.nilai) }}
                        </span>
                        <button v-if="a.rincian.length > 4 && !lihatSemua" type="button" class="kj-plt-more"
                                @click="lihatSemua = true">+{{ a.rincian.length - 4 }}</button>
                    </div>
                    <div v-else class="kj-an-v">
                        <span class="kj-hasil">{{ hasil(a) }}</span>
                        <span v-if="standar(a)" class="kj-std">standar {{ standar(a) }}</span>
                    </div>

                    <div v-if="a.sebelumnya" class="kj-sblm">
                        putaran sebelumnya: <b>{{ hasil(a.sebelumnya) }}</b>
                        <span v-if="a.sebelumnya.status !== 'info'" class="kj-st" :class="'s-' + a.sebelumnya.status">{{ labelStatus(a.sebelumnya.status) }}</span>
                    </div>

                    <div class="kj-an-m">
                        <span v-if="a.sub"><i class="ri-qr-code-line"></i>{{ a.sub }}</span>
                        <span v-if="a.penguji && a.penguji.length && tampilPengujiBaris"><i class="ri-flask-line"></i>{{ nama(a.penguji) }}</span>
                        <span v-if="a.pengirim && a.pengirim.length" class="kj-kirim">dikirim {{ nama(a.pengirim) }}</span>
                        <span v-if="audit && a.waktu">{{ jam(a.waktu, true) }}</span>
                        <span v-if="a.foto && (a.foto.aktif || a.foto.nonaktif)">
                            <i class="ri-image-line"></i>{{ a.foto.aktif }} foto<template v-if="a.foto.nonaktif"> · {{ a.foto.nonaktif }} nonaktif</template>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Validasi: keputusan per analisa -->
            <div v-if="k.keputusan && k.keputusan.length" class="kj-kp">
                <div v-for="a in k.keputusan" :key="a.id_jenis_analisa" class="kj-kp-i">
                    <span class="kj-kp-n">{{ a.nama }}</span>
                    <span class="kj-kp-k" :class="'k-' + a.keputusan">{{ labelKeputusan(a.keputusan) }}</span>
                    <span v-if="a.tidak_tercatat" class="kj-kp-v is-none">validator tidak tercatat</span>
                    <span v-else-if="a.validator && tampilValidatorBaris" class="kj-kp-v">
                        {{ a.validator.nama }}<em v-if="a.waktu">{{ jam(a.waktu, audit) }}</em>
                    </span>
                    <span v-if="a.status === 'bad' && a.keputusan === 'diterima'" class="kj-kp-tl">di luar standar</span>
                </div>
            </div>

            <!-- Resampling: asal -> tujuan, siapa, alasan -->
            <div v-if="k.resampling && k.resampling.length" class="kj-rs">
                <div v-for="(r, i) in k.resampling" :key="i" class="kj-rs-i">
                    <div class="kj-rs-h">
                        <b>{{ r.nama }}</b>
                        <span class="kj-st" :class="r.selesai ? 's-ok' : 's-wait'">{{ r.selesai ? 'Sudah diuji ulang' : 'Menunggu uji ulang' }}</span>
                    </div>
                    <dl>
                        <dt>Sampel</dt>
                        <dd class="mono">
                            <template v-if="r.sama">{{ r.asal }} <small>(nomor sama)</small></template>
                            <template v-else>{{ r.asal }} → {{ r.baru }}</template>
                        </dd>
                        <dt>Diminta</dt>
                        <dd>{{ r.oleh ? r.oleh.nama : '-' }}<em v-if="r.waktu">{{ jam(r.waktu, audit) }}</em></dd>
                        <template v-if="audit && r.keterangan">
                            <dt>Catatan sistem</dt>
                            <dd class="kj-sys">{{ r.keterangan }}</dd>
                        </template>
                    </dl>
                </div>
            </div>

            <div v-if="k.alasan" class="kj-alasan">
                <span>Alasan</span>{{ k.alasan }}
            </div>
            <div v-else-if="k.jenis === 'rs'" class="kj-alasan is-kosong">
                <span>Alasan</span>tidak diisi saat resampling diminta
            </div>

            <!-- Draf (audit): perubahan nilai -->
            <div v-if="k.perubahan && k.perubahan.length" class="kj-ubah">
                <span v-for="(u, i) in k.perubahan" :key="i">{{ nilai(u.lama) }} → <b>{{ nilai(u.baru) }}</b></span>
            </div>

            <div v-if="k.catatan" class="kj-cat"><span>{{ k.catatan_judul || 'Catatan' }}</span><IsiCatatan :isi="k.catatan" /></div>

        </div>
    </li>
</template>

<script>
import { uraiWaktu } from "./lifecycleWaktu";
import IsiCatatan from "./IsiCatatan.vue";

const HARI = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];
const BLN = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];

export default {
    name: "LifecycleKejadian",
    components: { IsiCatatan },
    props: {
        k: { type: Object, required: true },
        audit: { type: Boolean, default: false },
        // Label kecil di baris pelaku, mis. "Putaran 2 · resampling".
        chip: { type: String, default: null },
    },
    data() {
        return { lihatSemua: false };
    },
    computed: {
        ikon() {
            const k = this.k;
            if (k.jenis === "uji" && k.tingkat === "wait") return "ri-time-line";
            return {
                reg: "ri-file-add-line",
                uji: "ri-flask-line",
                val: "ri-checkbox-circle-line",
                rs: "ri-refresh-line",
                ver: "ri-shield-check-line",
                fin: "ri-flag-2-line",
                siap: "ri-information-line",
                draf: "ri-draft-line",
                sesi: "ri-play-circle-line",
            }[k.jenis] || "ri-circle-line";
        },
        /**
         * Nama validator per analisa hanya perlu diulang bila berbeda-beda
         * atau di mode audit; bila seragam, cukup disebut di kepala kartu.
         */
        tampilValidatorBaris() {
            if (this.audit) return true;
            const id = new Set((this.k.keputusan || []).filter((a) => a.validator).map((a) => a.validator.id));
            return id.size > 1;
        },
        tampilPengujiBaris() {
            if (this.audit) return true;
            const id = new Set((this.k.analisa || []).map((a) => (a.penguji || []).map((o) => o.id).join(",")));
            return id.size > 1;
        },
        pelakuTampil() {
            return (this.k.pelaku || []).slice(0, 3);
        },
        pelakuLebih() {
            return Math.max(0, (this.k.pelaku || []).length - 3);
        },
        rentangWaktu() {
            const a = this.jam(this.k.waktu, this.audit, true);
            const b = this.k.waktu_akhir && this.k.waktu_akhir !== this.k.waktu ? this.k.waktu_akhir : null;
            if (!b) return a;
            // Hari yang sama cukup jamnya saja.
            return String(b).slice(0, 10) === String(this.k.waktu).slice(0, 10)
                ? a + "–" + String(b).slice(11, this.audit ? 19 : 16)
                : a + " – " + this.jam(b, this.audit, true);
        },
    },
    methods: {
        /** "Kam 24 Sep 13:25" — tahun disebut bila bukan tahun berjalan. */
        jam(s, detik = false, lengkap = true) {
            const d = uraiWaktu(s);
            if (!d) return "";
            const dua = (n) => String(n).padStart(2, "0");
            const j = dua(d.getHours()) + ":" + dua(d.getMinutes()) + (detik ? ":" + dua(d.getSeconds()) : "");
            if (!lengkap) return j;
            const thn = d.getFullYear() !== new Date().getFullYear() ? " " + d.getFullYear() : "";
            return `${HARI[d.getDay()]} ${d.getDate()} ${BLN[d.getMonth()]}${thn} ${j}`;
        },
        nilai(v) {
            if (v === null || v === undefined || v === "") return "–";
            const s = String(v).trim();
            const n = Number(s);
            return s !== "" && Number.isFinite(n)
                ? n.toLocaleString("id-ID", { maximumFractionDigits: 4 })
                : s;
        },
        /** Satu nilai, atau ringkasan ulangan: "2 ulangan · 1,93–1,99". */
        hasil(a) {
            if (!a) return "–";
            if ((a.jumlah || 0) <= 1) return this.nilai((a.nilai || [])[0]);
            if (a.min !== null && a.min !== undefined) {
                return `${a.jumlah} ulangan · ${this.nilai(a.min)}–${this.nilai(a.maks)}`;
            }
            return `${a.jumlah} ulangan · ` + (a.nilai || []).slice(0, 3).map(this.nilai).join(", ");
        },
        standar(a) {
            const s = a.standar;
            if (!s) return "";
            if (s.jenis === "kriteria") return s.teks || "";
            const min = s.min !== null && s.min !== undefined ? this.nilai(s.min) : null;
            const max = s.max !== null && s.max !== undefined ? this.nilai(s.max) : null;
            if (min !== null && max !== null) return `${min} – ${max}`;
            if (min !== null) return `≥ ${min}`;
            if (max !== null) return `≤ ${max}`;
            return "";
        },
        labelStatus(s) {
            return { ok: "Layak", bad: "Tidak layak", na: "Tanpa standar" }[s] || s;
        },
        labelKeputusan(s) {
            return { diterima: "Diterima", resampling: "Resampling", menunggu: "Menunggu" }[s] || s;
        },
        nama(daftar) {
            return (daftar || []).map((o) => o.nama).join(", ");
        },
    },
};
</script>

<style scoped>
.kj { position: relative; display: grid; grid-template-columns: 24px minmax(0, 1fr); gap: 9px; padding-bottom: 9px; }
.kj::before { content: ""; position: absolute; left: 11px; top: 24px; bottom: 0; width: 2px; background: #e9ebec; }
.kj:last-child::before { display: none; }

.kj-dot { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: .72rem; color: #fff; background: #ced4da; position: relative; z-index: 1; }
.t-ok   .kj-dot { background: #0ab39c; }
.t-warn .kj-dot { background: #f7b84b; color: #3d2a03; }
.t-bad  .kj-dot { background: #f06548; }
.t-info .kj-dot { background: #299cdb; }
.t-run  .kj-dot { background: #405189; }
.t-wait .kj-dot { background: #e9ebec; color: #878a99; }
.t-mig  .kj-dot { background: repeating-linear-gradient(45deg, #ced4da 0 3px, #fff 3px 6px); color: #495057; border: 1px solid #ced4da; }

.kj-c { min-width: 0; background: #fff; border: 1px solid #e9ebec; border-radius: 6px; padding: 8px 10px; }
.j-rs .kj-c { border: 1px dashed #f7b84b; background: #fffaf0; }
.t-bad .kj-c { border-color: #f5a898; }
.t-wait .kj-c { background: #fbfbfd; border-style: dashed; }
.is-audit .kj-c { background: #fbfbfd; }

.kj-h { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.kj-judul { font-size: .76rem; color: #343a40; font-weight: 600; line-height: 1.35; }
.kj-pill { flex-shrink: 0; white-space: nowrap; font-size: .62rem; font-weight: 600; padding: 2px 8px;
    border-radius: 999px; background: #f3f6f9; color: #6c757d; }
.p-ok   { background: #daf4f0; color: #067a6a; }
.p-warn { background: #fef3e0; color: #9a6405; }
.p-bad  { background: #fde6e1; color: #bf3718; }
.p-info { background: #e0f0fa; color: #17699a; }
.p-run  { background: #e4e7f3; color: #405189; }
.p-mig  { background: #f3f6f9; color: #6c757d; }
.j-rs .kj-pill { background: #fff; }

.kj-m { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 10px; margin-top: 3px; font-size: .66rem; color: #878a99; }
.kj-m > span { display: inline-flex; align-items: center; gap: 3px; min-width: 0; }
.kj-org b { color: #495057; font-weight: 500; }
.kj-org small { font-size: .6rem; color: #adb5bd; }
.kj-lebih { color: #6c757d; }
.kj-kl { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .6rem; color: #405189;
    background: #e4e7f3; border-radius: 3px; padding: 0 5px; }

.kj-r { margin: 6px 0 0; font-size: .72rem; color: #495057; }
.kj-jadwal { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.kj-jadwal span { font-size: .62rem; padding: 1px 6px; border: 1px solid #e3e6ec; border-radius: 3px; background: #f8f9fc; color: #495057; }

/* Hasil per analisa */
.kj-an { margin-top: 7px; display: flex; flex-direction: column; }
.kj-an-i { padding: 5px 0; border-top: 1px solid #f1f3f5; }
.kj-an-i:first-child { border-top: none; padding-top: 0; }
.kj-an-h { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
.kj-an-n { font-size: .7rem; font-weight: 600; color: #343a40; min-width: 0; overflow-wrap: anywhere; }
.kj-st { flex-shrink: 0; font-size: .6rem; font-weight: 600; white-space: nowrap; color: #6c757d; }
.kj-st.s-ok { color: #067a6a; }
.kj-st.s-bad { color: #d4431f; }
.kj-st.s-na { color: #9a6405; }
.kj-st.s-info { color: #17699a; }
.kj-st.s-wait { color: #878a99; }
.kj-an-i.s-bad { background: linear-gradient(90deg, #fdf0ed, transparent 70%); margin: 0 -10px; padding-left: 10px; padding-right: 10px; }
.kj-an-v { display: flex; flex-wrap: wrap; align-items: baseline; gap: 2px 10px; margin-top: 1px; }
.kj-hasil { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .7rem; color: #212529;
    font-variant-numeric: tabular-nums; }
.kj-std { font-size: .62rem; color: #878a99; }
.kj-sblm { font-size: .62rem; color: #878a99; margin-top: 1px; }
.kj-sblm b { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 500; color: #6c757d; }
.kj-sblm .kj-st { margin-left: 5px; }
.kj-an-m { display: flex; flex-wrap: wrap; gap: 1px 9px; margin-top: 2px; font-size: .6rem; color: #878a99; }
.kj-an-m span { display: inline-flex; align-items: center; gap: 3px; min-width: 0; }
.kj-an-m i { font-size: .66rem; color: #adb5bd; }
.kj-kirim { color: #405189; }

.kj-plt { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 3px; }
.kj-plt-i { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .62rem; color: #0369a1;
    background: #f0f9ff; border: 1px solid #d5ecf7; border-radius: 3px; padding: 0 5px; }
.kj-plt-more { font-size: .6rem; border: 1px dashed #b9dcef; background: #fff; color: #0369a1; border-radius: 3px;
    padding: 0 5px; cursor: pointer; }

/* Keputusan validasi */
.kj-kp { margin-top: 7px; display: flex; flex-direction: column; gap: 3px; }
.kj-kp-i { display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: 8px; font-size: .66rem; }
.kj-kp-n { color: #343a40; font-weight: 500; min-width: 0; overflow-wrap: anywhere; }
.kj-kp-k { font-size: .6rem; font-weight: 600; white-space: nowrap; }
.k-diterima { color: #067a6a; }
.k-resampling { color: #9a6405; }
.k-menunggu { color: #878a99; }
.kj-kp-v { grid-column: 1 / -1; color: #6c757d; font-size: .62rem; }
.kj-kp-v em { font-style: normal; color: #adb5bd; margin-left: 5px; }
.kj-kp-v.is-none { font-style: italic; color: #adb5bd; }
.kj-kp-tl { grid-column: 1 / -1; font-size: .6rem; color: #d4431f; }

/* Resampling */
.kj-rs { margin-top: 7px; display: flex; flex-direction: column; gap: 6px; }
.kj-rs-i { background: #fff; border: 1px solid #fbe3b6; border-radius: 5px; padding: 6px 8px; }
.kj-rs-h { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; font-size: .68rem; color: #343a40; }
.kj-rs dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 2px 10px; margin: 4px 0 0; font-size: .64rem; }
.kj-rs dt { color: #878a99; font-weight: 400; }
.kj-rs dd { margin: 0; color: #343a40; min-width: 0; overflow-wrap: anywhere; }
.kj-rs dd em { font-style: normal; color: #878a99; margin-left: 5px; }
.kj-rs dd small { color: #878a99; }
.kj-sys { color: #878a99 !important; font-style: italic; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

.kj-alasan { margin-top: 6px; font-size: .68rem; color: #495057; background: #fff; border: 1px solid #fbe3b6;
    border-radius: 5px; padding: 5px 8px; }
.kj-alasan span, .kj-cat span { display: block; font-size: .56rem; text-transform: uppercase; letter-spacing: .06em;
    font-weight: 700; color: #9a6405; margin-bottom: 1px; }
.kj-alasan.is-kosong { color: #adb5bd; font-style: italic; }
.kj-ubah { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; font-size: .64rem; }
.kj-ubah span { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #fef3e0; border-radius: 3px; padding: 0 5px; color: #6c757d; }
.kj-ubah b { color: #343a40; font-weight: 600; }
.kj-cat { margin: 6px 0 0; font-size: .7rem; color: #495057; background: #f8f9fc; border: 1px solid #eef0f4;
    border-radius: 5px; padding: 5px 8px; }
.kj-cat span { color: #878a99; }
</style>
