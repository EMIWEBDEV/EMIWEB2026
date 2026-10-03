<template>
    <!--
        Editor catatan keputusan (Quill 2): tebal, miring, garis bawah, dan
        daftar bernomor / poin / centang. Judul, warna, tautan, dan gambar
        tidak tersedia — tempelan dari luar ikut disederhanakan ke format itu.

        v-model berisi HTML dari deltaKeHtml (bukan ekspor bawaan Quill).
        Catatan wajib dinilai langsung di bawah editor dengan aturan yang sama
        seperti saat menyimpan (periksaCatatanHtml); catatan opsional bebas,
        hanya dibatasi panjang maksimal.

        'jenis' mengikuti bobot keputusan (lihat aturanCatatan): ringkas,
        kondisi, atau justifikasi — tinggi area tulis dan poin panduannya
        menyesuaikan. Poin panduan hanya membantu, tidak menghalangi simpan.
    -->
    <div class="ec" :class="['is-' + jenis, { 'is-nonaktif': nonaktif, 'is-fokus': fokus, 'is-salah': tampilSalah, 'is-ok': tampilOk }]">
        <div class="ec-q"><div ref="wadah"></div></div>
        <div class="ec-bar" aria-hidden="true"><span :style="{ width: persen + '%' }"></span></div>
        <div class="ec-f" aria-live="polite">
            <span class="ec-psn"><i :class="ikon"></i><span>{{ pesan }}</span></span>
            <span class="ec-hit">
                <template v-if="wajib">
                    <span title="Karakter bermakna: spasi berlebih, tanda baca, kata pengisi, pengulangan kata, dan ketikan asal tidak dihitung.">
                        <b>{{ angka(status.jumlah) }}</b>/{{ angka(status.min) }} karakter
                    </span>
                    <span v-if="dekatMaks" class="ec-maks" :class="{ 'is-lebih': status.panjang > status.maks }"
                          title="Panjang catatan terhadap batas maksimal.">
                        · {{ angka(status.panjang) }}/{{ angka(status.maks) }} maks.
                    </span>
                </template>
                <span v-else :class="{ 'ec-maks is-lebih': status.panjang > status.maks }" title="Batas panjang catatan.">
                    <b>{{ angka(status.panjang) }}</b>/{{ angka(status.maks) }} karakter
                </span>
            </span>
        </div>
        <div v-if="tampilSalah && !status.kosong && status.tidakDihitung.length" class="ec-nb">
            Tidak dihitung: {{ status.tidakDihitung.join(", ") }}.
        </div>
        <!-- Panduan isi: kalimat informasi (bukan pilihan), muncul setelah mulai menulis. -->
        <div v-if="komponen.length && !status.kosong" class="ec-pn" :class="{ 'is-lengkap': !belumTermuat.length }"
             title="Panduan isi catatan — hanya informasi, tidak menghalangi penyimpanan.">
            <i :class="belumTermuat.length ? 'ri-information-line' : 'ri-checkbox-circle-line'"></i>
            <span v-if="belumTermuat.length">Belum terlihat dalam catatan: <b>{{ belumTermuat.join(", ") }}</b>.</span>
            <span v-else>Seluruh poin {{ jenis === 'justifikasi' ? 'justifikasi' : 'kondisi' }} sudah termuat.</span>
        </div>
    </div>
</template>

<script>
import { markRaw } from "vue";
import Quill from "quill";
import "quill/dist/quill.snow.css";
import { adalahHtml, bersihkanHtml, deltaKeHtml, htmlKeTeks, komponenCatatan, periksaCatatanHtml } from "./catatan";
import { muatKamus } from "./kamus";

const JUDUL = {
    bold: "Tebal (Ctrl+B)", italic: "Miring (Ctrl+I)", underline: "Garis bawah (Ctrl+U)",
    ordered: "Daftar bernomor", bullet: "Daftar poin", check: "Daftar centang",
};

export default {
    name: "EditorCatatan",
    props: {
        modelValue: { type: String, default: "" },
        placeholder: { type: String, default: "" },
        min: { type: Number, default: 15 },
        maks: { type: Number, default: 500 },
        wajib: { type: Boolean, default: false },
        // Nama keputusan: catatan wajib yang hanya mengulangnya tidak diterima.
        label: { type: String, default: "" },
        // 'ringkas' | 'kondisi' | 'justifikasi' (aturanCatatan).
        jenis: { type: String, default: "ringkas" },
        // Terkunci (mis. keputusan belum dipilih).
        nonaktif: { type: Boolean, default: false },
        // Kata kunci nama analisa sampel, untuk mengenali temuan (istilahAnalisa).
        istilah: { type: Array, default: () => [] },
    },
    emits: ["update:modelValue"],
    data() {
        return { html: this.modelValue || "", fokus: false, disentuh: false };
    },
    computed: {
        status() {
            return periksaCatatanHtml(this.html, { min: this.min, maks: this.maks, wajib: this.wajib, label: this.label, istilah: this.istilah });
        },
        komponen() {
            return this.wajib ? komponenCatatan(htmlKeTeks(this.html), this.jenis, this.istilah) : [];
        },
        belumTermuat() { return this.komponen.filter((k) => !k.ada).map((k) => k.label); },
        tampilSalah() { return !this.nonaktif && !this.status.ok && (this.disentuh || !this.status.kosong); },
        // Catatan opsional tidak dinilai, jadi tidak ada tanda "memenuhi syarat".
        tampilOk() { return this.wajib && this.status.ok && !this.status.kosong; },
        dekatMaks() { return this.status.panjang >= this.status.maks * 0.8; },
        persen() {
            const s = this.status;
            if (!this.wajib) return s.panjang > s.maks ? 100 : 0;
            if (!s.min) return s.jumlah ? 100 : 0;
            return Math.min(100, Math.round((s.jumlah / s.min) * 100));
        },
        pesan() {
            const s = this.status;
            if (this.nonaktif) return "Pilih tingkat keputusan terlebih dahulu.";
            if (this.tampilSalah) return s.pesan[0];
            if (!this.wajib) return s.kosong ? "Opsional · boleh dikosongkan." : "Opsional · tanpa minimal karakter, tetapi tidak boleh asal ketik.";
            if (this.tampilOk) return "Catatan memenuhi syarat.";
            return `Wajib diisi · minimal ${this.angka(s.min)} karakter bermakna.`;
        },
        ikon() {
            if (this.tampilSalah) return "ri-error-warning-line";
            if (this.tampilOk) return "ri-checkbox-circle-line";
            return "ri-information-line";
        },
    },
    watch: {
        // Nilai dari luar (mis. membuka revisi) dimuat ulang; ketikan sendiri tidak.
        modelValue(v) {
            if ((v || "") !== this.html) this.muat(v);
        },
        nonaktif(v) {
            if (this.q) this.q.enable(!v);
        },
        placeholder(v) {
            if (this.q) this.q.root.setAttribute("data-placeholder", v || "");
        },
    },
    mounted() {
        muatKamus();
        this.q = markRaw(new Quill(this.$refs.wadah, {
            theme: "snow",
            placeholder: this.placeholder,
            formats: ["bold", "italic", "underline", "list", "indent"],
            modules: {
                toolbar: [["bold", "italic", "underline"], [{ list: "ordered" }, { list: "bullet" }, { list: "check" }]],
                // Tab di awal butir daftar membuat sub-daftar; di luar itu Tab
                // berpindah fokus seperti kolom isian biasa.
                keyboard: { bindings: { tab: false } },
                // Gambar yang ditempel atau diseret tidak diterima.
                uploader: { mimetypes: [] },
            },
        }));
        const root = this.q.root;
        root.setAttribute("role", "textbox");
        root.setAttribute("aria-multiline", "true");
        root.setAttribute("aria-label", "Catatan");
        this.judulTombol();
        this.muat(this.modelValue);
        this.q.enable(!this.nonaktif);
        this.q.on("text-change", this.ubah);
        this.q.on("selection-change", this.ubahFokus);
    },
    beforeUnmount() {
        if (!this.q) return;
        this.q.off("text-change", this.ubah);
        this.q.off("selection-change", this.ubahFokus);
        this.q = null;
    },
    methods: {
        /** Isi editor dari HTML catatan (atau teks polos catatan lama). */
        muat(isi) {
            const q = this.q;
            if (!q) return;
            if (!isi) q.setContents([], "silent");
            else if (adalahHtml(isi)) q.setContents(q.clipboard.convert({ html: bersihkanHtml(isi) }), "silent");
            else q.setText(String(isi), "silent");
            this.html = this.hasilHtml();
        },
        hasilHtml() {
            return this.q.getText().trim() ? deltaKeHtml(this.q.getContents()) : "";
        },
        ubah() {
            this.disentuh = true;
            this.html = this.hasilHtml();
            this.$emit("update:modelValue", this.html);
        },
        ubahFokus(range) {
            this.fokus = !!range;
        },
        fokuskan() {
            if (this.q) this.q.focus();
        },
        angka(n) {
            return Number(n || 0).toLocaleString("id-ID");
        },
        /** Keterangan tombol toolbar dalam bahasa Indonesia. */
        judulTombol() {
            const tb = this.q.getModule("toolbar");
            if (!tb || !tb.container) return;
            tb.container.setAttribute("aria-label", "Format catatan");
            tb.container.querySelectorAll("button").forEach((b) => {
                const kelas = [...b.classList].find((c) => c.startsWith("ql-"));
                const nama = kelas ? kelas.slice(3) : "";
                const t = nama === "list" ? JUDUL[b.value] : JUDUL[nama];
                if (t) {
                    b.title = t;
                    b.setAttribute("aria-label", t);
                }
            });
        },
    },
};
</script>

<style scoped>
.ec { border: 1px solid #ced4da; border-radius: 6px; background: #fff; overflow: hidden;
    transition: border-color .15s, box-shadow .15s; }
.ec.is-fokus { border-color: #a3add6; box-shadow: 0 0 0 .2rem rgba(64, 81, 137, .12); }
.ec.is-salah { border-color: #f5b3a5; }
.ec.is-salah.is-fokus { box-shadow: 0 0 0 .2rem rgba(240, 101, 72, .12); }
.ec.is-ok { border-color: #9fdccf; }

/* Toolbar */
.ec :deep(.ql-toolbar.ql-snow) { border: 0; border-bottom: 1px solid #eef0f4; background: #f8f9fc; padding: 4px 6px;
    font-family: inherit; }
.ec :deep(.ql-toolbar.ql-snow .ql-formats) { margin-right: 6px; }
.ec :deep(.ql-toolbar.ql-snow .ql-formats + .ql-formats) { border-left: 1px solid #e3e6ef; padding-left: 6px; }
.ec :deep(.ql-snow.ql-toolbar button) { width: 28px; height: 26px; padding: 4px 5px; border-radius: 4px; }
.ec :deep(.ql-snow.ql-toolbar button:hover),
.ec :deep(.ql-snow.ql-toolbar button:focus-visible) { background: #eceff8; }
.ec :deep(.ql-snow.ql-toolbar button.ql-active) { background: #e2e6f4; }
.ec :deep(.ql-snow.ql-toolbar .ql-stroke) { stroke: #6c757d; }
.ec :deep(.ql-snow.ql-toolbar .ql-fill) { fill: #6c757d; }
.ec :deep(.ql-snow.ql-toolbar button:hover .ql-stroke),
.ec :deep(.ql-snow.ql-toolbar button.ql-active .ql-stroke) { stroke: #405189; }
.ec :deep(.ql-snow.ql-toolbar button:hover .ql-fill),
.ec :deep(.ql-snow.ql-toolbar button.ql-active .ql-fill) { fill: #405189; }

/* Area tulis — tingginya mengikuti bobot keputusan */
.ec :deep(.ql-container.ql-snow) { border: 0; font-family: inherit; font-size: .8rem; }
.ec :deep(.ql-editor) { min-height: 92px; max-height: 240px; overflow-y: auto; padding: 9px 11px; line-height: 1.55;
    color: #212529; }
.ec.is-kondisi :deep(.ql-editor) { min-height: 124px; max-height: 280px; }
.ec.is-justifikasi :deep(.ql-editor) { min-height: 164px; max-height: 320px; }
.ec :deep(.ql-editor.ql-blank::before) { left: 11px; right: 11px; font-style: normal; color: #adb5bd; }
.ec :deep(.ql-editor li[data-list="checked"] > .ql-ui),
.ec :deep(.ql-editor li[data-list="unchecked"] > .ql-ui) { color: #405189; }

/* Terkunci sampai keputusan dipilih */
.ec.is-nonaktif { background: #f8f9fa; }
.ec.is-nonaktif :deep(.ql-toolbar) { opacity: .5; pointer-events: none; }
.ec.is-nonaktif :deep(.ql-editor) { cursor: not-allowed; background: #f8f9fa; }

/* Penilaian */
.ec-bar { height: 2px; background: #eef0f4; }
.ec-bar span { display: block; height: 100%; background: #ced4da; transition: width .2s ease, background-color .2s ease; }
.ec.is-salah .ec-bar span { background: #f06548; }
.ec.is-ok .ec-bar span { background: #0ab39c; }
.ec-f { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; padding: 5px 10px 6px;
    font-size: .68rem; background: #fcfcfd; }
.ec-psn { display: flex; gap: 5px; align-items: flex-start; line-height: 1.4; color: #878a99; min-width: 0; }
.ec-psn i { font-size: .8rem; line-height: 1.1; }
.ec.is-salah .ec-psn { color: #d0472b; }
.ec.is-ok .ec-psn { color: #0a8f7c; }
.ec-hit { flex-shrink: 0; white-space: nowrap; color: #878a99; font-variant-numeric: tabular-nums; }
.ec-hit b { color: #495057; }
.ec.is-salah .ec-hit b { color: #d0472b; }
.ec.is-ok .ec-hit b { color: #0a8f7c; }
.ec-maks { color: #b98900; }
.ec-maks.is-lebih, .ec-maks.is-lebih b { color: #d0472b; font-weight: 600; }
.ec-nb { padding: 0 10px 6px 29px; margin-top: -3px; font-size: .64rem; color: #adb5bd; background: #fcfcfd; }

/* Panduan isi (kondisi & justifikasi): kalimat informasi, bukan pilihan */
.ec-pn { display: flex; gap: 5px; align-items: flex-start; padding: 0 10px 7px; font-size: .66rem; line-height: 1.4;
    color: #878a99; background: #fcfcfd; cursor: default; }
.ec-pn i { font-size: .78rem; line-height: 1.15; }
.ec-pn b { font-weight: 600; color: #6c757d; }
.ec-pn.is-lengkap { color: #0a8f7c; }
</style>
