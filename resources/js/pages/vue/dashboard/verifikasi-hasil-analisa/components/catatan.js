// Catatan keputusan (verifikasi & finalisasi) berformat teks kaya terbatas:
// tebal, miring, garis bawah, dan daftar (bernomor, poin, centang).
//
// Tiga tugas modul ini:
//   1. deltaKeHtml   — menyusun HTML dari Delta Quill. Ekspor HTML bawaan
//                      Quill (getSemanticHTML) sengaja TIDAK dipakai: versi
//                      2.0.3 punya advisory XSS di fitur itu (GHSA-v3m3-f69x-jf25).
//   2. bersihkanHtml — menyaring HTML sebelum ditampilkan: hanya tag yang
//                      diizinkan, tanpa atribut apa pun (kecuali penanda
//                      centang), disusun ulang dari teksnya — bukan disalin.
//                      Catatan lama berupa teks polos tetap tampil apa adanya.
//   3. periksaCatatan — menilai mutu catatan. Yang dihitung hanya "karakter
//                      bermakna", sehingga syarat minimal tidak bisa dipenuhi
//                      dengan "haaaaaaaa", "asdfgh", "tes tes tes", atau
//                      sekadar menyalin nama keputusan. Bila kamus bahasa
//                      Indonesia sudah dimuat (kamus.js), kata yang tidak ada
//                      di kamus ikut dinilai.

import { kataDikenal } from "./kamus";

/* ------------------------------------------------------------------ */
/* HTML                                                                */
/* ------------------------------------------------------------------ */

const ESC = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ESC[c]);

/** Tag yang boleh tampil, dipetakan ke bentuk bakunya. */
const IZIN = { P: "p", BR: "br", STRONG: "strong", B: "strong", EM: "em", I: "em", U: "u", OL: "ol", UL: "ul", LI: "li" };
/** Dibuang beserta seluruh isinya. */
const BUANG = new Set(["SCRIPT", "STYLE", "TEMPLATE", "IFRAME", "FRAME", "OBJECT", "EMBED", "NOSCRIPT", "TEXTAREA",
    "SELECT", "OPTION", "BUTTON", "INPUT", "FORM", "SVG", "MATH", "HEAD", "TITLE", "META", "LINK", "BASE", "IMG",
    "PICTURE", "SOURCE", "VIDEO", "AUDIO", "CANVAS", "MAP"]);
/** Blok lain (judul, div, kutipan, …) diturunkan menjadi paragraf biasa. */
const BLOK = new Set(["DIV", "H1", "H2", "H3", "H4", "H5", "H6", "BLOCKQUOTE", "PRE", "SECTION", "ARTICLE", "HEADER",
    "FOOTER", "ASIDE", "NAV", "MAIN", "ADDRESS", "FIGURE", "FIGCAPTION", "TABLE", "TR", "DL", "DT", "DD"]);

/**
 * Apakah isinya HTML, bukan teks polos catatan lama. HTML dari editor dan
 * dari bersihkanHtml selalu diawali tag blok, sehingga teks polos yang
 * kebetulan memuat "<b>" atau "pH < 5" tetap dianggap teks polos.
 */
export function adalahHtml(s) {
    return /^\s*<(p|ul|ol|li|div|h[1-6]|strong|b|em|i|u|span|br)\b[^>]*>/i.test(String(s || ""));
}

/** Teks polos -> HTML aman; baris baru dipertahankan. */
function teksKeHtml(s) {
    const t = String(s).replace(/\r\n?/g, "\n").trim();
    return t ? "<p>" + esc(t).replace(/\n/g, "<br>") + "</p>" : "";
}

function susun(nodes, dalam) {
    let out = "";
    nodes.forEach((n) => {
        if (n.nodeType === 3) {
            out += esc(n.nodeValue.replace(/ /g, " "));
            return;
        }
        if (n.nodeType !== 1 || dalam > 16) return; // komentar dsb. dibuang
        const tag = n.tagName.toUpperCase();
        if (BUANG.has(tag)) return;
        const t = IZIN[tag];
        if (t === "br") {
            out += "<br>";
            return;
        }
        const isi = susun(n.childNodes, dalam + 1);
        if (t === "li") {
            const d = n.getAttribute("data-list");
            out += `<li${d === "checked" || d === "unchecked" ? ` data-list="${d}"` : ""}>${isi}</li>`;
        } else if (t) {
            out += `<${t}>${isi}</${t}>`;
        } else if (BLOK.has(tag)) {
            out += susunBlok(n.childNodes, dalam + 1);
        } else {
            out += isi; // span, a, font, … : dibuka, isinya tetap
        }
    });
    return out;
}

/**
 * Isi setingkat blok: teks & format lepas dibungkus <p>, butir daftar lepas
 * dibungkus <ul>. Hasilnya selalu diawali <p>/<ul>/<ol> (lihat adalahHtml).
 */
function susunBlok(nodes, dalam) {
    let out = "";
    let lepas = "";
    const tuang = () => {
        if (lepas.replace(/<br>/g, "").trim()) out += `<p>${lepas.trim()}</p>`;
        lepas = "";
    };
    nodes.forEach((n) => {
        const html = susun([n], dalam);
        if (/^<(p|ul|ol)>/.test(html)) {
            tuang();
            out += html;
        } else if (/^<li[ >]/.test(html)) {
            tuang();
            out += `<ul>${html}</ul>`;
        } else {
            lepas += html;
        }
    });
    tuang();
    return out;
}

/** Paragraf kosong di awal/akhir dibuang, yang beruntun diringkas. */
function rapikan(html) {
    const kosong = "<p>(?:\\s|<br>)*</p>";
    return html
        .replace(new RegExp(`(?:${kosong}){2,}`, "g"), "<p><br></p>")
        .replace(new RegExp(`^(?:${kosong})+|(?:${kosong})+$`, "g"), "")
        .trim();
}

/**
 * HTML aman untuk v-html. Disusun ulang dari pohon DOM hasil DOMParser
 * (dokumen inert: skrip tidak jalan, gambar tidak dimuat) — hanya tag
 * dalam IZIN, tanpa atribut, teks di-escape.
 */
export function bersihkanHtml(isi) {
    if (!isi) return "";
    const s = String(isi);
    if (!adalahHtml(s)) return teksKeHtml(s);
    if (typeof DOMParser === "undefined") return teksKeHtml(htmlKeTeks(s));
    const doc = new DOMParser().parseFromString(`<!doctype html><body>${s}</body>`, "text/html");
    return rapikan(susunBlok(doc.body.childNodes, 0));
}

/** Teks polos dari HTML catatan — untuk penilaian mutu dan pratinjau. */
export function htmlKeTeks(isi) {
    const s = String(isi || "");
    if (!adalahHtml(s)) return s.trim();
    return s
        .replace(/<(script|style|template)\b[\s\S]*?<\/\1\s*>/gi, " ")
        .replace(/<br\s*\/?>|<\/?(p|li|ul|ol|div|h[1-6])\b[^>]*>/gi, "\n")
        .replace(/<[^>]*>/g, "")
        .replace(/&nbsp;/g, " ").replace(/&lt;/g, "<").replace(/&gt;/g, ">")
        .replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/&amp;/g, "&")
        .replace(/[ \t]+\n/g, "\n").replace(/\n{2,}/g, "\n")
        .trim();
}

/* ---------------- Delta Quill -> HTML ---------------- */

function bungkus(html, a) {
    if (a.underline) html = `<u>${html}</u>`;
    if (a.italic) html = `<em>${html}</em>`;
    if (a.bold) html = `<strong>${html}</strong>`;
    return html;
}

/** Baris-baris daftar (boleh bertingkat & berganti jenis) -> <ol>/<ul>. */
function daftarKeHtml(baris) {
    let out = "";
    const tumpuk = []; // { tag, liBuka }
    const tutup = (s) => (s.liBuka ? "</li>" : "") + `</${s.tag}>`;
    baris.forEach((b) => {
        const tag = b.list === "ordered" ? "ol" : "ul";
        while (tumpuk.length > b.indent + 1) out += tutup(tumpuk.pop());
        while (tumpuk.length < b.indent + 1) {
            const atas = tumpuk[tumpuk.length - 1];
            if (atas && !atas.liBuka) {
                out += "<li>";
                atas.liBuka = true;
            }
            const t = tumpuk.length === b.indent ? tag : "ul";
            out += `<${t}>`;
            tumpuk.push({ tag: t, liBuka: false });
        }
        const atas = tumpuk[tumpuk.length - 1];
        if (atas.tag !== tag) {
            out += tutup(atas) + `<${tag}>`;
            atas.tag = tag;
            atas.liBuka = false;
        } else if (atas.liBuka) {
            out += "</li>";
        }
        const cek = b.list === "checked" || b.list === "unchecked" ? ` data-list="${b.list}"` : "";
        out += `<li${cek}>${b.html || "<br>"}`;
        atas.liBuka = true;
    });
    while (tumpuk.length) out += tutup(tumpuk.pop());
    return out;
}

/** Delta Quill -> HTML catatan. Hanya format yang diizinkan yang terbaca. */
export function deltaKeHtml(delta) {
    const ops = (delta && delta.ops) || [];
    const baris = [];
    let isi = "";
    ops.forEach((op) => {
        if (typeof op.insert !== "string") return; // sisipan non-teks diabaikan
        const a = op.attributes || {};
        op.insert.split("\n").forEach((teks, i) => {
            if (i > 0) {
                // "\n" menutup baris; atribut barisnya melekat pada op ini.
                const list = ["ordered", "bullet", "checked", "unchecked"].includes(a.list) ? a.list : null;
                baris.push({ html: isi, list, indent: list ? Math.max(0, Math.min(8, Number(a.indent) || 0)) : 0 });
                isi = "";
            }
            if (teks) isi += bungkus(esc(teks.replace(/\t/g, " ")), a);
        });
    });
    if (isi) baris.push({ html: isi, list: null, indent: 0 });

    let out = "";
    for (let i = 0; i < baris.length;) {
        if (!baris[i].list) {
            out += `<p>${baris[i].html || "<br>"}</p>`;
            i++;
            continue;
        }
        const grup = [];
        while (i < baris.length && baris[i].list) grup.push(baris[i++]);
        out += daftarKeHtml(grup);
    }
    return rapikan(out);
}

/* ------------------------------------------------------------------ */
/* Mutu catatan                                                        */
/* ------------------------------------------------------------------ */

/** Kata pengisi: boleh ditulis, tetapi tidak dihitung. */
const PENGISI = new Set(["tes", "test", "testing", "coba", "cobacoba", "asd", "qwe", "lorem", "ipsum", "dolor", "amet",
    "bla", "blabla", "haha", "hehe", "hihi", "huhu", "wkwk", "wkwkwk", "xixi", "hmm", "hm", "ok", "oke", "okay", "okey",
    "oce", "sip", "yes", "done", "xx", "xxx", "abc", "abcd", "none", "null"]);

/** Deret tombol keyboard/abjad: 5 berurutan (maju/mundur) = ketikan asal. */
const DERET = ["qwertyuiop", "asdfghjkl", "zxcvbnm", "abcdefghijklmnopqrstuvwxyz"];
const POTONGAN_DERET = (() => {
    const s = new Set(["asdf", "qwer", "zxcv", "hjkl", "fdsa", "rewq", "vcxz", "lkjh"]);
    DERET.forEach((d) => [d, [...d].reverse().join("")].forEach((r) => {
        for (let i = 0; i + 5 <= r.length; i++) s.add(r.slice(i, i + 5));
    }));
    return [...s];
})();

/**
 * Jenis satu kata: 'kata' | 'angka' | 'pengisi' | 'sampah' (+ sebab).
 * Aturannya sengaja longgar untuk bahasa Indonesia/Inggris sehari-hari
 * dan singkatan lab (pH, TPC, cfu, HPLC), ketat untuk ketikan asal.
 */
export function jenisKata(asli) {
    const k = String(asli).normalize("NFD").replace(/\p{M}/gu, "").toLowerCase();
    const huruf = k.replace(/[^\p{L}]/gu, "");
    if (!huruf) return { jenis: "angka" };
    if (/^[ivxlcdm]+$/.test(k) && asli === String(asli).toUpperCase()) return { jenis: "kata" }; // angka romawi
    if (/(\p{L})\1{2,}/u.test(huruf)) return { jenis: "sampah", sebab: "berulang" }; // haaaa, okeee
    if (huruf.length >= 6 && /^(\p{L}{1,3})\1{2,}\p{L}{0,2}$/u.test(huruf)) return { jenis: "sampah", sebab: "pola" }; // hahaha, asdasdasd
    if (/[bcdfghjklmnpqrstvwxz]{6,}/.test(huruf) || (huruf.length >= 5 && !/[aeiouy]/.test(huruf))
        || POTONGAN_DERET.some((p) => huruf.includes(p))) return { jenis: "sampah", sebab: "acak" }; // sdfghjk, qwerty
    if (PENGISI.has(huruf)) return { jenis: "pengisi" };
    return { jenis: "kata" };
}

const SEBAB = { berulang: "huruf berulang", pola: "pola berulang", acak: "ketikan acak" };
const potong = (s) => (s.length > 14 ? s.slice(0, 13) + "…" : s);
const angka = (n) => Number(n).toLocaleString("id-ID");

/**
 * Nilai mutu catatan (teks polos).
 *
 * Karakter bermakna = kata-kata yang dihitung, digabung satu spasi (tanda
 * baca & spasi berlebih tidak ikut). Tidak dihitung: ketikan asal, kata
 * pengisi (tes, ok, …), kata sama yang berurutan ("tekstur tekstur"), dan
 * kemunculan ketiga dst. dari kata yang sama.
 * Catatan WAJIB ditolak bila: ada ketikan asal, isinya hanya nama
 * keputusan, didominasi kata yang diulang-ulang, kurang dari minimal, kurang
 * dari 2 kata, atau melewati maksimal.
 * Catatan OPSIONAL — boleh kosong dan tanpa minimal; bila diisi tetap ditolak
 * jika berisi ketikan asal / banyak kata di luar kamus, dan dibatasi panjang
 * maksimal.
 *
 * @param {string} teks
 * @param {{min?:number, maks?:number, wajib?:boolean, label?:string, istilah?:string[]}} opsi
 *        label: nama keputusan — catatan wajib yang hanya mengulangnya ditolak.
 *        istilah: kata kunci nama analisa (istilahAnalisa) — tidak diperiksa kamus.
 */
export function periksaCatatan(teks, opsi = {}) {
    const min = Math.max(0, Number(opsi.min) || 0);
    const maks = Number(opsi.maks) || 500;
    const polos = String(teks || "").normalize("NFKC").replace(/\s+/g, " ").trim();
    const hasil = { ok: false, kosong: !polos, panjang: polos.length, jumlah: 0, kata: 0, min, maks,
        pesan: [], tidakDihitung: [] };
    const terlaluPanjang = () => `Terlalu panjang — maksimal ${angka(maks)} karakter (lebih ${angka(hasil.panjang - maks)}).`;

    if (!polos) {
        hasil.ok = !opsi.wajib;
        if (opsi.wajib) hasil.pesan.push("Catatan wajib diisi untuk keputusan ini.");
        return hasil;
    }

    // Catatan opsional: tanpa minimal dan tanpa syarat isi, tetapi bila diisi
    // tidak boleh ketikan asal / kata di luar kamus (cek di bawah).
    const ketat = !!opsi.wajib;

    const label = new Set((String(opsi.label || "").toLowerCase().match(/[\p{L}\p{N}]+/gu)) || []);
    const token = polos.match(/[\p{L}\p{N}]+/gu) || [];
    const hitung = [];
    const sampah = [];
    const frek = {};
    let pengisi = 0, ulang = 0, sebelumnya = "";
    token.forEach((t) => {
        const j = jenisKata(t);
        if (j.jenis === "sampah") {
            sampah.push({ t, sebab: j.sebab });
            return;
        }
        if (j.jenis === "pengisi") {
            pengisi++;
            return;
        }
        // Pengulangan tidak menambah makna: kata sama yang berurutan dihitung
        // sekali, dan satu kata paling banyak dihitung dua kali — kata
        // sambung seperti "yang" atau "di" tetap wajar terhitung.
        const kunci = t.toLowerCase();
        if (kunci === sebelumnya) {
            ulang++;
            return;
        }
        sebelumnya = kunci;
        frek[kunci] = (frek[kunci] || 0) + 1;
        if (frek[kunci] > 2) {
            ulang++;
            return;
        }
        hitung.push({ t, kata: j.jenis === "kata" });
    });

    const kata = hitung.filter((h) => h.kata);
    const kataUnik = new Set(kata.map((h) => h.t.toLowerCase()));
    const hanyaLabel = ketat && label.size > 0 && kata.length > 0 && [...kataUnik].every((k) => label.has(k));
    // Didominasi segelintir kata yang diulang-ulang ("warna aroma warna aroma …").
    const monoton = ketat && token.length >= 6 && new Set(token.map((t) => t.toLowerCase())).size / token.length < 0.5;
    // Kata di luar kamus: dilewati bila kamus belum siap, singkatan (≥3 huruf
    // kapital semua), kata berangka, dan istilah analisa sampel. Satu-dua salah
    // ketik atau nama orang ditoleransi; ditolak bila lebih dari 30% kata.
    const istilah = new Set(opsi.istilah || []);
    const asing = [];
    let dicek = 0;
    new Map(kata.map((h) => [h.t.toLowerCase(), h.t])).forEach((t, k) => {
        if (t.length < 3 || /\d/.test(t) || t === t.toUpperCase() || istilah.has(k)) return;
        const ok = kataDikenal(t);
        if (ok === null) return;
        dicek++;
        if (!ok) asing.push(t);
    });
    const banyakAsing = dicek > 0 && asing.length / dicek > 0.3;

    hasil.kata = hanyaLabel ? 0 : kataUnik.size;
    hasil.jumlah = hanyaLabel ? 0 : hitung.map((h) => h.t).join(" ").length;
    if (pengisi) hasil.tidakDihitung.push("kata pengisi (tes, ok, …)");
    if (ulang) hasil.tidakDihitung.push("pengulangan kata");
    if (sampah.length) hasil.tidakDihitung.push("ketikan asal");

    if (sampah.length) {
        const contoh = [...new Set(sampah.map((s) => "“" + potong(s.t) + "”"))].slice(0, 2).join(", ");
        const sebab = [...new Set(sampah.map((s) => SEBAB[s.sebab]))].join(" / ");
        hasil.pesan.push(`Hapus ${contoh} — ${sebab} tidak dianggap catatan.`);
    }
    if (hanyaLabel) hasil.pesan.push("Jelaskan dasar keputusannya — jangan hanya menulis nama keputusan.");
    else if (monoton) hasil.pesan.push("Terlalu banyak pengulangan kata — tuliskan penjelasan yang lebih beragam.");
    if (banyakAsing) {
        const contoh = asing.slice(0, 3).map((t) => "“" + potong(t) + "”").join(", ");
        hasil.pesan.push(`Banyak kata tidak dikenali (${contoh}${asing.length > 3 ? ", …" : ""}) — periksa ejaannya.`);
    }
    if (!ketat) { /* opsional: tidak ada syarat minimal */ }
    else if (hasil.jumlah < min) hasil.pesan.push(`Kurang ${angka(min - hasil.jumlah)} karakter bermakna lagi.`);
    else if (min > 0 && hasil.kata < 2) hasil.pesan.push("Tulis minimal 2 kata penjelasan, bukan hanya angka atau kode.");
    if (hasil.panjang > maks) hasil.pesan.push(terlaluPanjang());

    hasil.ok = hasil.pesan.length === 0;
    return hasil;
}

/** Penilaian langsung dari HTML catatan (dipakai editor & saat menyimpan). */
export function periksaCatatanHtml(html, opsi = {}) {
    return periksaCatatan(htmlKeTeks(html), opsi);
}

/* ------------------------------------------------------------------ */
/* Aturan per tingkat keputusan                                        */
/* ------------------------------------------------------------------ */

// Bobot catatan mengikuti bobot keputusannya:
//   ringkas     — keputusan positif (Direkomendasikan): opsional & bebas.
//   kondisi     — keputusan bersyarat: kondisi/temuan yang menjadi syarat dan
//                 perhatian berikutnya. Wajib, 50–1.000 karakter.
//   justifikasi — keputusan yang menahan proses (Tidak Direkomendasikan):
//                 dasar objektif, temuan, dampak, tindak lanjut. Wajib,
//                 100–1.500 karakter.
// Kode mengikuti master N_EMI_LAB_Verifikasi_Keputusan; wajib/tidaknya dari
// Flag_Wajib_Catatan, dan Panjang_Min_Catatan master berlaku bila lebih besar.
const JENIS_KODE = {
    REKOMENDASI: "ringkas", SETUJU_PENUH: "ringkas",
    REKOM_BERSYARAT: "kondisi", SETUJU_BERSYARAT: "kondisi",
    TIDAK_REKOM: "justifikasi", TOLAK: "justifikasi",
};

const JENIS = {
    ringkas: {
        min: 0, maks: 500,
        judul: { verifikasi: "Catatan Verifikasi", finalisasi: "Catatan Finalisasi" },
        riwayat: { verifikasi: "Catatan verifikasi", finalisasi: "Catatan finalisasi" },
        bantuan: "Opsional. Tambahkan ringkasan singkat bila ada informasi yang perlu diketahui pada tahap berikutnya.",
        contoh: "Contoh: Seluruh parameter sesuai spesifikasi.",
        komponen: [],
    },
    kondisi: {
        min: 50, maks: 1000,
        judul: "Kondisi Rekomendasi",
        riwayat: "Kondisi rekomendasi",
        bantuan: "Jelaskan kondisi yang membuat hasil hanya dapat diteruskan dengan syarat: parameter atau temuan yang perlu diperhatikan, serta pemantauan atau tindak lanjut pada proses berikutnya.",
        contoh: "Contoh: Kadar air 11,8% mendekati batas maksimal 12%. Hasil dapat diteruskan dengan pemantauan kadar air pada batch berikutnya.",
        komponen: ["temuan", "tindak"],
    },
    justifikasi: {
        min: 100, maks: 1500,
        judul: "Justifikasi Keputusan",
        riwayat: "Justifikasi keputusan",
        bantuan: "Uraikan dasar keputusan secara objektif agar dapat dipahami tanpa konfirmasi ulang: temuan atau parameter yang tidak memenuhi, acuan kriterianya, dampak terhadap proses, dan tindak lanjut yang diperlukan.",
        contoh: "Contoh: ASH 14,3% melebihi batas maksimal 12% pada spesifikasi produk, sehingga sampel belum memenuhi kriteria kelayakan untuk finalisasi. Diperlukan evaluasi formula bersama R&D dan uji ulang sebelum trial berikutnya.",
        komponen: ["temuan", "acuan", "dampak", "tindak"],
    },
};

/**
 * Poin panduan catatan. Hanya informasi — tidak menghalangi penyimpanan,
 * karena penjelasan yang baik bisa ditulis dengan kata apa pun. Label
 * ditulis huruf kecil karena tampil di dalam kalimat ("Belum terlihat …").
 */
const KOMPONEN = {
    temuan: {
        label: "temuan/parameter", labelKondisi: "kondisi/temuan",
        judul: "Parameter atau hasil yang menjadi temuan, sebaiknya beserta nilainya.",
        pola: /\d|temuan|tidak sesuai|tidak memenuhi|menyimpang|penyimpangan|deviasi|melebihi|melampaui|di bawah|di atas|mendekati|tidak layak|out of spec|\boos\b|kontaminasi|cacat|abnormal|anomali|parameter|kadar/,
    },
    acuan: {
        label: "acuan kriteria",
        judul: "Batas spesifikasi, standar, atau kriteria yang menjadi pembanding.",
        pola: /spesifikasi|\bspek\b|standar|batas|kriteria|syarat|master|acuan|rentang|\brange\b|toleransi|minimal|minimum|maksimal|maksimum|\bmaks\b|\bmin\b|\bsni\b|regulasi|ketentuan|target/,
    },
    dampak: {
        label: "dampak/risiko",
        judul: "Akibat temuan terhadap proses, produk, atau keputusan berikutnya.",
        pola: /dampak|risiko|resiko|sebab|akibat|sehingga|pengaruh|tertahan|ditahan|terhenti|tidak dapat diteruskan|belum dapat diteruskan|tidak bisa diteruskan|belum memenuhi|keamanan|mutu produk|kualitas produk|konsumen|pelanggan/,
    },
    tindak: {
        label: "tindak lanjut", labelKondisi: "pemantauan/tindak lanjut",
        judul: "Tindakan atau pemantauan yang diperlukan berikutnya.",
        pola: /perlu|harus|tindak lanjut|tindaklanjut|investigasi|evaluasi|uji ulang|analisa ulang|analisis ulang|retest|resampling|revisi|perbaik|koordinasi|konfirmasi|disarankan|sarankan|trial ulang|\bcapa\b|eskalasi|tinjau|pantau|monitor|awasi/,
    },
};

/**
 * Aturan & teks catatan untuk satu tingkat keputusan (baris master).
 * @param {object|null} kep   baris master keputusan; null = belum dipilih
 * @param {string} tahap      'verifikasi' | 'finalisasi'
 */
export function aturanCatatan(kep, tahap = "verifikasi") {
    let jenis = kep ? JENIS_KODE[kep.Kode_Keputusan] : null;
    if (kep && !jenis) {
        jenis = kep.Flag_Boleh_Lanjut === "T" ? "justifikasi" : kep.Flag_Wajib_Catatan === "Y" ? "kondisi" : "ringkas";
    }
    const j = JENIS[jenis || "ringkas"];
    const pilih = (v) => (typeof v === "string" ? v : v[tahap] || v.verifikasi);
    const wajib = !!kep && kep.Flag_Wajib_Catatan === "Y";
    return {
        jenis: jenis || "ringkas",
        wajib,
        min: wajib ? Math.max(15, j.min, Number(kep.Panjang_Min_Catatan) || 0) : 0,
        maks: j.maks,
        judul: kep ? pilih(j.judul) : "Catatan",
        bantuan: kep ? j.bantuan : "Pilih tingkat keputusan untuk melihat ketentuan catatannya.",
        contoh: kep ? j.contoh : "",
    };
}

/** Judul catatan pada riwayat (kartu keputusan, Sample Lifecycle). */
export function judulRiwayatCatatan(kode, tahap = "verifikasi") {
    const r = JENIS[JENIS_KODE[kode] || "ringkas"].riwayat;
    return typeof r === "string" ? r : r[tahap] || r.verifikasi;
}

/**
 * Poin panduan yang sudah/belum termuat dalam catatan.
 * @param {string}   teks     teks polos catatan
 * @param {string}   jenis    'kondisi' | 'justifikasi' (lainnya: tanpa poin)
 * @param {string[]} istilah  kata kunci nama analisa sampel (huruf kecil) —
 *                            menyebut salah satunya dianggap menyebut temuan
 */
export function komponenCatatan(teks, jenis, istilah = []) {
    const j = JENIS[jenis];
    if (!j || !j.komponen.length) return [];
    const t = String(teks || "").normalize("NFKC").toLowerCase().replace(/\s+/g, " ");
    const kata = new Set(t.match(/[\p{L}\p{N}]+/gu) || []);
    return j.komponen.map((kode) => {
        const k = KOMPONEN[kode];
        const ada = k.pola.test(t) || (kode === "temuan" && istilah.some((x) => kata.has(x)));
        return { kode, label: (jenis === "kondisi" && k.labelKondisi) || k.label, judul: k.judul, ada };
    });
}

/**
 * Kata kunci nama analisa untuk deteksi temuan: "ASH ANALYSIS" -> "ash",
 * "TEKSTUR POUCH (QA)" -> "tekstur", "pouch". Kata umum dibuang.
 */
export function istilahAnalisa(namaAnalisa) {
    const umum = new Set(["analysis", "analisa", "analisis", "uji", "test", "qa", "qc", "lab", "hasil"]);
    const s = new Set();
    (namaAnalisa || []).forEach((n) => (String(n).toLowerCase().match(/\p{L}{3,}/gu) || [])
        .forEach((w) => { if (!umum.has(w)) s.add(w); }));
    return [...s];
}
