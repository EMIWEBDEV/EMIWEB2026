<template>
    <!--
        Tampilan catatan keputusan. Isinya selalu melewati bersihkanHtml:
        HTML dari editor tampil terformat (tebal, miring, garis bawah,
        daftar), catatan lama berupa teks polos tampil apa adanya — tidak
        pernah sebagai HTML mentah, dan tidak ada tag/atribut lain yang lolos.

        'judul' (opsional) menamai jenis catatannya di riwayat, mis.
        "Justifikasi keputusan" — lihat judulRiwayatCatatan.
    -->
    <div class="ic">
        <span v-if="judul" class="ic-j">{{ judul }}</span>
        <div class="ic-isi" v-html="aman"></div>
    </div>
</template>

<script>
import { bersihkanHtml } from "./catatan";

export default {
    name: "IsiCatatan",
    props: {
        isi: { type: String, default: "" },
        judul: { type: String, default: "" },
    },
    computed: {
        aman() {
            return bersihkanHtml(this.isi);
        },
    },
};
</script>

<style scoped>
.ic { overflow-wrap: anywhere; line-height: 1.5; }
.ic-j { display: block; margin-bottom: 2px; font-size: .56rem; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; opacity: .7; }
.ic :deep(p) { margin: 0; }
.ic :deep(p + p), .ic :deep(p + ul), .ic :deep(p + ol),
.ic :deep(ul + p), .ic :deep(ol + p), .ic :deep(ul + ol), .ic :deep(ol + ul) { margin-top: 3px; }
.ic :deep(ul), .ic :deep(ol) { margin: 0; padding-left: 1.4em; }
.ic :deep(ul) { list-style: disc; }
.ic :deep(ul ul) { list-style: circle; }
.ic :deep(ol) { list-style: decimal; }
.ic :deep(ol ol) { list-style: lower-alpha; }
.ic :deep(li) { margin: 1px 0; }
.ic :deep(strong) { font-weight: 600; }

/* Daftar centang: kotak digambar dengan warna teks, jadi terbaca di latar apa pun. */
.ic :deep(li[data-list]) { list-style: none; position: relative; }
.ic :deep(li[data-list])::before { content: ""; position: absolute; left: -1.25em; top: .3em; width: .85em; height: .85em;
    box-sizing: border-box; border: 1.5px solid currentColor; border-radius: 3px; opacity: .5; }
.ic :deep(li[data-list="checked"])::before { opacity: .9; }
.ic :deep(li[data-list="checked"])::after { content: ""; position: absolute; left: -.97em; top: .4em; width: .28em; height: .5em;
    border: solid currentColor; border-width: 0 2px 2px 0; transform: rotate(45deg); }
</style>
