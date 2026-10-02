<template>
    <!--
        Sample Lifecycle — dipakai bersama layar Verifikasi dan Finalisasi.

        Alur: Registrasi → per klasifikasi (Uji sampel → Validasi → bila
        ditolak: Resampling → Uji sampel resampling → Validasi …) →
        Verifikasi → Finalisasi. Klasifikasi berjalan sendiri-sendiri, jadi
        tampil sebagai jalur terpisah (berdampingan saat panel lebar); isi
        tiap jalur tersusun kronologis per hari dan selalu lengkap — termasuk
        draf, sesi uji, dan revisi verifikasi.

        Data dari /api/v1/verifikasi-hasil-analisa/lifecycle (LifecycleSampelService).
        Nilai 'loading' menandai sedang dimuat; null berarti tidak tersedia.
    -->
    <div class="lc" :style="{ '--kolom': kolom }">
        <div v-if="data === 'loading'" class="lc-state">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <span>Memuat…</span>
        </div>

        <div v-else-if="!data || !data.fase" class="lc-state lc-state--empty">
            <i class="ri-route-line"></i>
            <p>Riwayat sampel tidak tersedia.</p>
        </div>

        <template v-else>
            <!-- Layar Validasi: siapa yang mengambil keputusan berikutnya.
                 Jejak di bawahnya berhenti di tahap validasi. -->
            <div v-if="keputusan" class="lc-kep">
                <span class="lc-kep-h"><i class="ri-user-star-line"></i>Keputusan berikutnya</span>
                <div class="lc-kep-r">
                    <span class="lc-kep-k"><i class="ri-shield-check-line"></i>Verifikasi</span>
                    <span class="lc-kep-v">
                        <template v-if="(keputusan.verifikasi || []).length">
                            <b v-for="p in keputusan.verifikasi" :key="p.id">{{ p.nama }}</b>
                        </template>
                        <em v-else>Belum ada verifikator yang ditugaskan</em>
                        <small v-if="keputusan.klasifikasi">{{ keputusan.klasifikasi }}</small>
                    </span>
                </div>
                <div class="lc-kep-r">
                    <span class="lc-kep-k"><i class="ri-flag-line"></i>Finalisasi</span>
                    <span class="lc-kep-v">
                        <template v-if="(keputusan.finalisasi || []).length">
                            <b v-for="p in keputusan.finalisasi" :key="p.id">{{ p.nama }}</b>
                        </template>
                        <em v-else>Belum ada akun finalisasi</em>
                        <small>seluruh klasifikasi sampel</small>
                    </span>
                </div>
            </div>

            <!-- Posisi sekarang -->
            <div class="lc-pos" :class="'t-' + data.posisi.tingkat">
                <span class="lc-pos-l">{{ data.posisi.label }}</span>
                <span v-if="data.posisi.keterangan" class="lc-pos-k">{{ data.posisi.keterangan }}</span>
            </div>

            <!-- Strip fase; resampling hanya muncul bila memang terjadi -->
            <ol class="lc-fase" aria-label="Tahapan sampel">
                <li v-for="f in fase" :key="f.kode" :class="'t-' + f.tingkat">
                    <i></i><span>{{ labelFase(f.label) }}<template v-if="f.jumlah > 1"> {{ f.jumlah }}×</template></span>
                </li>
            </ol>

            <div v-if="data.catatan_data && data.catatan_data.length" class="lc-data">
                <i class="ri-information-line"></i>
                <div><p v-for="(c, i) in data.catatan_data" :key="i">{{ c }}</p></div>
            </div>

            <!-- Saring klasifikasi — hanya bila lebih dari satu -->
            <div v-if="opsiFilter.length > 2" class="lc-ctl lc-chips" role="group" aria-label="Saring klasifikasi">
                <button v-for="o in opsiFilter" :key="o.kode" type="button" class="lc-chip"
                        :class="{ on: filter === o.kode }" @click="filter = o.kode">
                    {{ o.label }}
                </button>
            </div>
            <div v-else class="lc-ctl-garis"></div>

            <section v-if="(data.registrasi || []).length" class="lc-sec">
                <h6 class="lc-sec-h">Registrasi</h6>
                <ol class="lc-rail">
                    <LifecycleKejadian v-for="k in data.registrasi || []" :key="k.id" :k="k" :audit="true" />
                </ol>
            </section>

            <div v-if="jalur.length" class="lc-lanes">
                <div v-for="kl in jalur" :key="kl.kode" class="lc-lane">
                    <div class="lc-lane-h">
                        <span class="lc-lane-ic"><i :class="ikonKlasifikasi(kl.kode)"></i></span>
                        <span class="lc-lane-t">
                            <b>{{ kl.nama }}</b>
                            <small>{{ kl.ringkas }}</small>
                        </span>
                        <span class="lc-pill" :class="'p-' + kl.status.tingkat">{{ kl.status.label }}</span>
                    </div>

                    <!-- Alur ringkas: uji › validasi › (resampling › uji › validasi) › verifikasi -->
                    <ol class="lc-alur" aria-label="Alur ringkas">
                        <li v-for="(a, i) in kl.alurTampil" :key="i" :class="'t-' + a.tingkat">
                            <i></i>{{ labelAlur(a.jenis) }}
                        </li>
                    </ol>

                    <!-- Isi jalur: kronologis, dipisah per hari -->
                    <ol v-if="kl.item.length" class="lc-rail">
                        <template v-for="it in kl.item" :key="it.kunci">
                            <li v-if="it.grp" class="lc-grp">{{ it.grp }}</li>
                            <LifecycleKejadian v-else :k="it.k" :audit="true" :chip="it.chip" />
                        </template>
                    </ol>
                </div>
            </div>

            <section v-if="!sampaiValidasi && (data.finalisasi || []).length" class="lc-sec">
                <h6 class="lc-sec-h">Finalisasi</h6>
                <ol class="lc-rail">
                    <LifecycleKejadian v-for="k in data.finalisasi || []" :key="k.id" :k="k" :audit="true" />
                </ol>
            </section>
        </template>
    </div>
</template>

<script>
import LifecycleKejadian from "./LifecycleKejadian.vue";
import { tanggalPanjang } from "./lifecycleWaktu";

export default {
    name: "LifecycleSampel",
    components: { LifecycleKejadian },
    props: {
        // null = tidak tersedia, 'loading' = sedang dimuat, objek = data.
        data: { type: [Object, String], default: null },
        // 'validasi': jejak berhenti di tahap validasi (layar Validasi) —
        // verifikasi & finalisasi tampil sebagai pengambil keputusan di atas.
        batas: { type: String, default: null },
        // { klasifikasi, verifikasi: [{id,nama}], finalisasi: [{id,nama}] }
        keputusan: { type: Object, default: null },
    },
    data() {
        return {
            filter: "semua",
        };
    },
    computed: {
        /** Jumlah jalur berdampingan saat panel lebar (maks. 3). */
        kolom() {
            return Math.max(1, Math.min(this.jalur.length || 1, 3));
        },
        siap() {
            return this.data && typeof this.data === "object" && this.data.fase;
        },
        sampaiValidasi() { return this.batas === "validasi"; },
        /** Strip fase — tanpa Verifikasi & Finalisasi bila dibatasi. */
        fase() {
            if (!this.siap) return [];
            return this.sampaiValidasi
                ? this.data.fase.filter((f) => f.kode !== "VER" && f.kode !== "FIN")
                : this.data.fase;
        },
        opsiFilter() {
            if (!this.siap) return [];
            return [{ kode: "semua", label: "Semua" }]
                .concat((this.data.klasifikasi || []).map((k) => ({ kode: k.kode, label: k.nama })));
        },
        /**
         * Jalur per klasifikasi. Isinya kronologis: diurutkan menurut waktu
         * dan dipisah per hari; yang belum terjadi (tanpa waktu) di akhir.
         * Bila klasifikasi melalui lebih dari satu putaran, setiap kartu
         * diberi label putarannya.
         */
        jalur() {
            if (!this.siap) return [];
            return (this.data.klasifikasi || [])
                .filter((kl) => this.filter === "semua" || this.filter === kl.kode)
                .map((kl) => {
                    const banyakPutaran = (kl.putaran || 1) > 1;
                    // Dibatasi sampai validasi: kartu verifikasi tidak ikut.
                    const kejadian = (kl.kejadian || [])
                        .filter((k) => !this.sampaiValidasi || k.jenis !== "ver");
                    const bertanggal = kejadian
                        .map((k, i) => ({ k, i }))
                        .filter((x) => x.k.waktu)
                        .sort((a, b) => (a.k.waktu < b.k.waktu ? -1 : a.k.waktu > b.k.waktu ? 1 : a.i - b.i))
                        .map((x) => x.k);
                    const menunggu = kejadian.filter((k) => !k.waktu);

                    const item = [];
                    let hari = null;
                    bertanggal.forEach((k) => {
                        const h = String(k.waktu).slice(0, 10);
                        if (h !== hari) {
                            hari = h;
                            item.push({ kunci: kl.kode + "-hari-" + h, grp: tanggalPanjang(k.waktu) });
                        }
                        item.push({ kunci: k.id, k, chip: this.chipPutaran(k, banyakPutaran) });
                    });
                    if (menunggu.length) {
                        item.push({ kunci: kl.kode + "-menunggu", grp: "Belum terjadi" });
                        menunggu.forEach((k) => item.push({ kunci: k.id, k, chip: this.chipPutaran(k, banyakPutaran) }));
                    }

                    const alurTampil = (kl.alur || [])
                        .filter((a) => !this.sampaiValidasi || a.jenis !== "ver");
                    return { ...kl, item, alurTampil };
                });
        },
    },
    watch: {
        // Sampel berganti: saringan kembali ke semua.
        "data.sampel.No_Sampel"() {
            this.filter = "semua";
        },
    },
    methods: {
        /** Label putaran pada kartu — hanya bila klasifikasinya diresampling. */
        chipPutaran(k, banyakPutaran) {
            if (!banyakPutaran || k.jenis === "ver" || !k.putaran) return null;
            return k.putaran > 1 ? `Putaran ${k.putaran} · resampling` : "Putaran 1";
        },
        /**
         * Label fase di panel sempit: kata panjang diberi titik pemenggalan
         * (soft hyphen) supaya turun baris rapi, tidak menempel ke label
         * sebelahnya.
         */
        labelFase(t) {
            return String(t)
                .replace("Resampling", "Resam­pling")
                .replace("Verifikasi", "Verifi­kasi")
                .replace("Finalisasi", "Finali­sasi")
                .replace("Registrasi", "Regis­trasi");
        },
        labelAlur(j) {
            return { uji: "Uji sampel", val: "Validasi", rs: "Resampling", ver: "Verifikasi" }[j] || j;
        },
        ikonKlasifikasi(kode) {
            return { LCKV: "ri-eye-line", ANL: "ri-flask-line", PLT: "ri-heart-3-line" }[kode] || "ri-folder-line";
        },
    },
};
</script>

<style scoped>
.lc { container: lcp / inline-size; }
/* Panel diperbesar di layar lebar: isi tidak direntang ke ruang kosong —
   satu jalur paling lebar ±640px, tiga jalur ±1920px. */
.lc { max-width: calc(var(--kolom, 3) * 640px); margin-inline: auto; }

.lc-state { display: flex; align-items: center; justify-content: center; gap: 9px;
    padding: 40px 16px; color: #878a99; font-size: .78rem; }
.lc-state--empty { flex-direction: column; gap: 3px; text-align: center; }
.lc-state--empty i { font-size: 2.1rem; opacity: .35; }
.lc-state--empty p { margin: 0; }

/* Pengambil keputusan berikutnya (layar Validasi) */
.lc-kep { border: 1px solid #dde3f4; background: #f7f9fe; border-radius: 7px; padding: 9px 11px 10px;
    margin-bottom: 10px; }
.lc-kep-h { display: flex; align-items: center; gap: 5px; font-size: .6rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em; color: #405189; margin-bottom: 6px; }
.lc-kep-r { display: flex; align-items: flex-start; gap: 10px; padding: 5px 0; }
.lc-kep-r + .lc-kep-r { border-top: 1px dashed #dde3f4; }
.lc-kep-k { display: inline-flex; align-items: center; gap: 4px; width: 84px; flex-shrink: 0;
    font-size: .66rem; font-weight: 600; color: #878a99; padding-top: 1px; }
.lc-kep-k i { color: #405189; }
.lc-kep-v { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.lc-kep-v b { font-size: .76rem; color: #343a40; font-weight: 600; }
.lc-kep-v em { font-style: italic; font-size: .7rem; color: #adb5bd; }
.lc-kep-v small { font-size: .62rem; color: #878a99; }

/* Posisi */
.lc-pos { display: flex; flex-direction: column; gap: 2px; padding: 8px 11px; border-radius: 6px;
    background: #f8f9fc; border: 1px solid #e9ebec; }
.lc-pos-l { font-size: .78rem; font-weight: 600; color: #343a40; display: flex; align-items: center; gap: 6px; }
.lc-pos-l::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #ced4da; flex-shrink: 0; }
.lc-pos.t-ok .lc-pos-l::before { background: #0ab39c; }
.lc-pos.t-warn .lc-pos-l::before { background: #f7b84b; }
.lc-pos.t-bad .lc-pos-l::before { background: #f06548; }
.lc-pos.t-run .lc-pos-l::before { background: #405189; }
.lc-pos-k { font-size: .66rem; color: #878a99; padding-left: 14px; }

/* Fase */
.lc-fase { list-style: none; display: flex; margin: 11px 0 0; padding: 0; }
.lc-fase li { flex: 1; position: relative; text-align: center; font-size: .58rem; color: #878a99; min-width: 0; }
.lc-fase li i { display: block; width: 10px; height: 10px; margin: 0 auto 4px; border-radius: 50%;
    background: #e9ebec; position: relative; z-index: 1; }
.lc-fase li::before { content: ""; position: absolute; top: 4px; left: -50%; right: 50%; height: 2px; background: #e9ebec; }
.lc-fase li:first-child::before { display: none; }
.lc-fase li span { display: block; padding: 0 3px; line-height: 1.25; hyphens: manual; overflow-wrap: break-word; }
.lc-fase .t-ok i, .lc-fase .t-ok::before, .lc-fase .t-warn::before, .lc-fase .t-bad::before { background: #0ab39c; }
.lc-fase .t-warn i { background: #f7b84b; }
.lc-fase .t-bad i { background: #f06548; }
.lc-fase .t-run i { background: #405189; }
.lc-fase .t-mig i { background: repeating-linear-gradient(45deg, #ced4da 0 2px, #fff 2px 4px); border: 1px solid #ced4da; }
.lc-fase .t-ok span, .lc-fase .t-warn span, .lc-fase .t-bad span, .lc-fase .t-run span { color: #495057; }

.lc-data { display: flex; gap: 7px; margin-top: 10px; padding: 6px 9px; border-radius: 5px;
    background: #f8f9fc; border: 1px dashed #dfe3ea; font-size: .64rem; color: #6c757d; }
.lc-data i { color: #adb5bd; margin-top: 1px; }
.lc-data p { margin: 0; }

/* Kendali */
.lc-ctl { margin: 11px 0 12px; padding: 9px 0; border-top: 1px solid #f1f3f5; border-bottom: 1px solid #f1f3f5; }
.lc-ctl-garis { margin: 11px 0 12px; border-top: 1px solid #f1f3f5; }
.lc-chips { display: flex; flex-wrap: wrap; gap: 5px; }
.lc-chip { border: 1px solid #dfe3ea; background: #fff; color: #495057; border-radius: 999px;
    font-size: .62rem; font-weight: 500; padding: 2px 9px; cursor: pointer; }
.lc-chip.on { border-color: #405189; background: #e4e7f3; color: #405189; }
.lc-chip:focus-visible { outline: 2px solid #299cdb; outline-offset: 1px; }

/* Bagian & jalur */
.lc-sec + .lc-lanes, .lc-lanes + .lc-sec { margin-top: 12px; }
.lc-sec-h { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; font-size: .58rem; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase; color: #878a99; }
.lc-sec-h::after { content: ""; flex: 1; height: 1px; background: #f1f3f5; }

.lc-lanes { display: grid; gap: 10px; grid-template-columns: minmax(0, 1fr); }
@container lcp (min-width: 880px) {
    .lc-lanes { grid-template-columns: repeat(var(--kolom, 1), minmax(0, 1fr)); align-items: start; }
}
.lc-lane { background: #f8f9fc; border: 1px solid #eef0f4; border-radius: 7px; padding: 9px; min-width: 0; }
.lc-lane-h { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.lc-lane-ic { width: 26px; height: 26px; border-radius: 6px; background: #e4e7f3; color: #405189;
    display: flex; align-items: center; justify-content: center; font-size: .8rem; flex-shrink: 0; }
.lc-lane-t { display: flex; flex-direction: column; min-width: 0; flex: 1; }
.lc-lane-t b { font-size: .74rem; color: #343a40; }
.lc-lane-t small { font-size: .6rem; color: #878a99; }
.lc-pill { font-size: .6rem; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: #f3f6f9;
    color: #6c757d; white-space: nowrap; }
.p-ok { background: #daf4f0; color: #067a6a; }
.p-warn { background: #fef3e0; color: #9a6405; }
.p-bad { background: #fde6e1; color: #bf3718; }
.p-run { background: #e4e7f3; color: #405189; }
.p-info { background: #e0f0fa; color: #17699a; }

.lc-alur { list-style: none; display: flex; flex-wrap: wrap; align-items: center; gap: 3px 0; margin: 7px 0 9px;
    padding: 0; font-size: .6rem; color: #495057; }
.lc-alur li { display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
.lc-alur li + li::before { content: "›"; color: #adb5bd; margin: 0 5px; }
.lc-alur i { width: 7px; height: 7px; border-radius: 50%; background: #ced4da; }
.lc-alur .t-ok i { background: #0ab39c; }
.lc-alur .t-warn i { background: #f7b84b; }
.lc-alur .t-bad i { background: #f06548; }
.lc-alur .t-run i { background: #405189; }
.lc-alur .t-wait i { background: #e9ebec; border: 1px solid #ced4da; }
.lc-alur .t-mig i { background: repeating-linear-gradient(45deg, #ced4da 0 2px, #fff 2px 3px); border: 1px solid #ced4da; }

.lc-rail { list-style: none; margin: 0; padding: 0; }
.lc-grp { position: relative; padding: 1px 0 7px 33px; font-size: .56rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: #878a99; }
.lc-grp::before { content: ""; position: absolute; left: 11px; top: 0; bottom: 0; width: 2px; background: #e9ebec; }
.lc-rail > .lc-grp:first-child::before { top: 8px; }

</style>
