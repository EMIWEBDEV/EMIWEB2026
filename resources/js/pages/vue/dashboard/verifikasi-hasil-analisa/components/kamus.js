// Kamus bahasa Indonesia (Hunspell id_ID, LGPL — public/dict) untuk menilai
// catatan. Dimuat sekali, malas (saat editor pertama tampil), dan terpisah
// dari bundel utama lewat import dinamis.
//
// kataDikenal() mengembalikan null selama kamus belum/gagal dimuat — penilai
// harus melewati cek kamus, bukan menolak catatan. Membaca `siap` di dalam
// computed membuat penilaian dihitung ulang begitu kamus selesai dimuat.

import { shallowRef } from "vue";

const BASE = "/dict/id_ID";

/** Istilah lab, singkatan, dan bahasa sehari-hari yang tidak ada di kamus baku. */
const TAMBAHAN = new Set((
    "gak ga nggak enggak udah aja banget bgt gimana kayak kalo yg dgn tdk tp jg krn utk sdh blm " +
    "sampel batch lot po coa qc qa lab ph tpc cfu hplc ash brix pouch sachet mixing blending spek " +
    "spesifikasi uji ulang resampling retest trial formulator formulasi supplier vendor " +
    "online offline update upload download"
).split(" "));

const spell = shallowRef(null);
let janji = null;

/** Pasang kamus yang sudah dibuat (dipakai muatKamus dan pengujian). */
export function pasangKamus(instans) {
    spell.value = instans;
}

export function muatKamus() {
    if (!janji) {
        janji = (async () => {
            const [{ default: nspell }, aff, dic] = await Promise.all([
                import("nspell"),
                ...["aff", "dic"].map((e) => fetch(`${BASE}.${e}`).then((r) => {
                    if (!r.ok) throw new Error(`${r.status} ${BASE}.${e}`);
                    return r.text();
                })),
            ]);
            pasangKamus(nspell(aff, dic));
        })().catch((err) => {
            console.warn("Kamus catatan gagal dimuat; penilaian memakai aturan pola saja.", err);
        });
    }
    return janji;
}

/** true/false bila kamus siap; null bila belum. */
export function kataDikenal(kata) {
    const s = spell.value;
    if (!s) return null;
    const l = String(kata).toLowerCase();
    return TAMBAHAN.has(l) || s.correct(l) || s.correct(kata);
}
