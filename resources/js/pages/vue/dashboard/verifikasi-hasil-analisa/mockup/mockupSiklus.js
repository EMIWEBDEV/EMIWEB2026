// =====================================================================
// MOCKUP SIKLUS — Validasi -> Verifikasi -> Finalisasi -> Hasil Analisa
// =====================================================================
// Seluruh data siklus disimpan di BROWSER (localStorage): bertahan saat
// berganti akun lewat /trial-ui, antar-tab, dan saat browser dibuka ulang,
// sampai dikosongkan dari menu Contoh data. Server hanya membuat contoh data
// (dari master asli, baca saja); semua keputusan sesudahnya terjadi di sini.
//
// Mengapa bukan sessionStorage: skrip layout template (assets/js/layout.js)
// menjalankan sessionStorage.clear() + reload setiap kali atribut tata
// letak <html> berubah antar-halaman — termasuk saat berganti akun — sehingga
// data mockup di sana ikut terhapus.
//
// Bentuk baris analisa sama dengan RincianHasilAnalisaService (tabel
// Verifikasi), ditambah status mockup:
//   _status  'menunggu' | 'diterima'      _putaran  1, 2, …
// Per klasifikasi:
//   riwayat     baris putaran yang diresampling (ditolak), beserta alasannya
//   resampling  permintaan uji ulang: { id, nama, putaran, asal, baru, sama,
//               alasan, oleh, waktu, selesai, waktu_ulang }
//   verifikasi  { kode, nama, warna, catatan, oleh, waktu, revisi } | null
// Per sampel:
//   finalisasi  { kode, nama, warna, catatan, oleh, waktu } | null
// catatan: HTML terbatas dari EditorCatatan yang sudah disaring
// (components/catatan.js); tampilkan hanya lewat IsiCatatan.

import { judulRiwayatCatatan } from "../components/catatan";

export const KUNCI = "lims-mockup-siklus";
const VERSI = 1;

/* ------------------------------------------------------------------ */
/* Penyimpanan                                                          */
/* ------------------------------------------------------------------ */

export function baca() {
    try {
        const s = JSON.parse(localStorage.getItem(KUNCI) || "null");
        if (s && s.versi === VERSI && Array.isArray(s.sampel)) return s;
    } catch (e) { /* data rusak: mulai baru */ }
    return { versi: VERSI, sampel: [] };
}

/** @returns {boolean} false bila penyimpanan penuh / tidak tersedia. */
export function simpan(state) {
    try {
        localStorage.setItem(KUNCI, JSON.stringify(state));
        return true;
    } catch (e) {
        return false;
    }
}

export function kosongkan() {
    try { localStorage.removeItem(KUNCI); } catch (e) { /* abaikan */ }
    return { versi: VERSI, sampel: [] };
}

/** Waktu lokal "YYYY-MM-DD HH:mm:ss" — sama bentuknya dengan waktu server. */
export function sekarang(tambahDetik = 0) {
    const d = new Date(Date.now() + tambahDetik * 1000);
    const dua = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${dua(d.getMonth() + 1)}-${dua(d.getDate())} `
        + `${dua(d.getHours())}:${dua(d.getMinutes())}:${dua(d.getSeconds())}`;
}

/** Nomor contoh berikutnya: FSmmyy-M001, M002, … (tidak bentrok dengan nomor asli). */
export function nomorBaru(state, jumlah) {
    const d = new Date();
    const awalan = "FS" + String(d.getMonth() + 1).padStart(2, "0") + String(d.getFullYear()).slice(-2) + "-M";
    const terpakai = state.sampel
        .map((s) => (s.No_Sampel.startsWith(awalan) ? parseInt(s.No_Sampel.slice(awalan.length), 10) : 0))
        .filter((n) => !isNaN(n));
    let n = terpakai.length ? Math.max(...terpakai) : 0;
    return Array.from({ length: jumlah }, () => awalan + String(++n).padStart(3, "0"));
}

/** Contoh dari server -> sampel mockup siap validasi. */
export function tambahContoh(state, daftar) {
    daftar.forEach((s) => {
        s.dibuat = sekarang();
        s.finalisasi = null;
        s.klasifikasi.forEach((k) => {
            k.riwayat = [];
            k.resampling = [];
            k.verifikasi = null;
            k.riwayatVerifikasi = [];
            k.analisa.forEach((a) => { a._status = "menunggu"; a._putaran = 1; });
        });
        state.sampel.unshift(s);
    });
    return state;
}

export function hapusSampel(state, noSampel) {
    state.sampel = state.sampel.filter((s) => s.No_Sampel !== noSampel);
    return state;
}

/* ------------------------------------------------------------------ */
/* Pembantu                                                             */
/* ------------------------------------------------------------------ */

export const cariSampel = (state, no) => state.sampel.find((s) => s.No_Sampel === no) || null;
export const cariKlasifikasi = (s, kode) => (s ? s.klasifikasi.find((k) => k.kode === kode) || null : null);

const salin = (x) => JSON.parse(JSON.stringify(x));
const pisahWaktu = (w) => ({ tanggal: String(w).slice(0, 10), jam: String(w).slice(11, 19) });
const waktuBaris = (a) => `${String(a.Tanggal).slice(0, 10)} ${String(a.Jam || "00:00:00").slice(0, 8)}`;

/** Baris dikelompokkan per jenis analisa (ulangan / palatabilitas berbaris banyak). */
export function perAnalisa(rows) {
    const peta = new Map();
    rows.forEach((a) => {
        if (!peta.has(a.Id_Jenis_Analisa)) peta.set(a.Id_Jenis_Analisa, []);
        peta.get(a.Id_Jenis_Analisa).push(a);
    });
    return [...peta.entries()].map(([id, baris]) => ({ id, nama: baris[0].Nama_Jenis_Analisa, baris }));
}

/** Kelayakan satu jenis analisa: 'T' bila ada baris tidak layak, 'N' bila belum dinilai. */
export function layakAnalisa(baris, plt) {
    if (plt) return null;
    if (baris.some((a) => a.Flag_Layak === "T")) return "T";
    if (baris.some((a) => a.Flag_Layak === "N")) return "N";
    return "Y";
}

/** Analisa yang boleh diurus akun ini: hak = daftar id; null = semua. */
const boleh = (hak, id) => hak === null || hak.includes(Number(id));

/* ------------------------------------------------------------------ */
/* Transisi                                                             */
/* ------------------------------------------------------------------ */

/** Validasi analisa tertentu (id jenis analisa) pada satu klasifikasi. */
export function validasi(state, noSampel, kode, ids, pengguna) {
    const k = cariKlasifikasi(cariSampel(state, noSampel), kode);
    if (!k) return 0;
    const w = pisahWaktu(sekarang());
    const oleh = { id: pengguna.id, nama: pengguna.nama, tanggal: w.tanggal, jam: w.jam };
    const set = new Set(ids.map(Number));
    let n = 0;
    perAnalisa(k.analisa).forEach(({ id, baris }) => {
        if (!set.has(Number(id)) || baris[0]._status !== "menunggu") return;
        baris.forEach((a) => { a._status = "diterima"; a.Validasi = { ...oleh }; });
        (k.Matriks_Pembanding || []).forEach((m) => {
            if (Number(m.Id_Jenis_Analisa) === Number(id)) m.Validasi = { ...oleh };
        });
        n++;
    });
    return n;
}

/**
 * Minta uji ulang satu analisa. Hasil putaran ini disimpan sebagai
 * riwayat (ditolak) beserta alasannya; analisanya menunggu hasil uji ulang.
 */
export function ujiUlang(state, noSampel, kode, id, alasan, subBaru, pengguna) {
    const k = cariKlasifikasi(cariSampel(state, noSampel), kode);
    if (!k) return false;
    const baris = k.analisa.filter((a) => Number(a.Id_Jenis_Analisa) === Number(id) && a._status === "menunggu");
    if (!baris.length) return false;
    const waktu = sekarang();
    const putaran = baris[0]._putaran;
    const matriks = (k.Matriks_Pembanding || []).find((m) => Number(m.Id_Jenis_Analisa) === Number(id)) || null;

    k.riwayat.push({
        id: Number(id), putaran, nama: baris[0].Nama_Jenis_Analisa,
        baris: salin(baris), matriks: matriks ? salin(matriks) : null,
        alasan, oleh: { id: pengguna.id, nama: pengguna.nama }, waktu,
    });
    const asal = baris[0].No_Sampel_Uji;
    k.resampling.push({
        id: Number(id), nama: baris[0].Nama_Jenis_Analisa, putaran,
        asal, baru: subBaru || asal, sama: !subBaru,
        alasan, oleh: { id: pengguna.id, nama: pengguna.nama }, waktu,
        selesai: false, waktu_ulang: null,
    });
    k.analisa = k.analisa.filter((a) => !baris.includes(a));
    if (matriks) k.Matriks_Pembanding = k.Matriks_Pembanding.filter((m) => m !== matriks);
    return true;
}

/**
 * Simulasi hasil uji ulang masuk dari analis: nilai baru dibuat menurut
 * pilihan ('Y' layak / 'T' tidak layak) lalu analisa kembali menunggu
 * validasi sebagai putaran berikutnya.
 */
export function masukkanUjiUlang(state, noSampel, kode, id, hasil) {
    const k = cariKlasifikasi(cariSampel(state, noSampel), kode);
    if (!k) return false;
    const rs = [...k.resampling].reverse().find((r) => r.id === Number(id) && !r.selesai);
    const lama = [...k.riwayat].reverse().find((r) => r.id === Number(id));
    if (!rs || !lama) return false;

    const waktu = sekarang();
    const w = pisahWaktu(waktu);
    const putaran = lama.putaran + 1;
    const jumlahUlang = k.resampling.filter((r) => r.id === Number(id)).length;

    const baru = lama.baris.map((a, i) => {
        const b = salin(a);
        b._status = "menunggu";
        b._putaran = putaran;
        b.Tahapan_Ke = putaran;
        b.No_Sampel_Uji = rs.baru;
        b.No_Faktur = `${a.No_Faktur.replace(/-R\d+$/, "")}-R${putaran}`;
        b.Tanggal = w.tanggal;
        b.Jam = w.jam;
        b.Validasi = null;
        b.Input = { ...(a.Input || {}), tanggal: w.tanggal, jam: w.jam };
        b.Jumlah_Resampling = jumlahUlang;
        b.Detail_Resampling = k.resampling
            .filter((r) => r.id === Number(id))
            .map((r) => ({ asal: r.asal, ulang: r.baru, keterangan: r.sama ? "nomor sama" : null }));
        b.Foto = a.Foto || [];
        nilaiUlang(b, hasil, i, k.Butuh_Pembanding);
        return b;
    });
    k.analisa.push(...baru);

    if (lama.matriks) {
        const m = salin(lama.matriks);
        m.Validasi = null;
        m.Input = { ...(m.Input || {}), tanggal: w.tanggal, jam: w.jam };
        Object.values(m.sel || {}).forEach((daftar) => daftar.forEach((c) => {
            const n = Number(c.angka);
            if (!isNaN(n)) { c.angka = geserAngka(n); c.hasil = c.angka; }
        }));
        k.Matriks_Pembanding.push(m);
    }

    rs.selesai = true;
    rs.waktu_ulang = waktu;
    return true;
}

/** Nilai baru satu baris uji ulang sesuai pilihan hasil. */
function nilaiUlang(b, hasil, i, plt) {
    const r = b.Rincian_Kelayakan || {};

    if (plt) {
        const n = Number(b.Hasil);
        if (!isNaN(n)) b.Hasil = geserAngka(n);
        return;
    }

    if (b.Dasar_Kelayakan === "RENTANG") {
        const min = Number(r.min), max = Number(r.max);
        const lebar = Math.max(Math.abs(max - min), 1);
        const nilai = hasil === "T"
            ? bulat(max + lebar * (0.15 + 0.05 * i))
            : bulat(min + lebar * (0.45 + 0.05 * i));
        b.Hasil = nilai;
        b.Flag_Layak = hasil === "T" ? "T" : "Y";
        b.Ringkas_Kelayakan = hasil === "T" ? "Melebihi batas maksimum" : "Berada dalam rentang standar";
        b.Rincian_Kelayakan = { ...r, nilai, di_bawah_min: false, di_atas_max: hasil === "T" };
        return;
    }

    if (b.Dasar_Kelayakan === "KRITERIA" || b.Dasar_Kelayakan === "KRITERIA_TIDAK_COCOK") {
        const pilihan = r.pilihan || [];
        const cocok = pilihan.find((p) => p.layak === (hasil === "T" ? "T" : "Y")) || pilihan[0];
        if (!cocok) return;
        b.Nilai_Hasil_String = cocok.keterangan;
        b.Flag_Layak = cocok.layak || "Y";
        b.Dasar_Kelayakan = "KRITERIA";
        b.Ringkas_Kelayakan = `Hasil "${cocok.keterangan}" termasuk kriteria ${b.Flag_Layak === "T" ? "tidak " : ""}layak`;
        b.Rincian_Kelayakan = {
            kriteria_terpilih: cocok.keterangan,
            layak_terpilih: b.Flag_Layak,
            jumlah_kriteria: pilihan.length,
            jumlah_layak: pilihan.filter((p) => p.layak === "Y").length,
            jumlah_tidak: pilihan.filter((p) => p.layak === "T").length,
            daftar_layak: pilihan.filter((p) => p.layak === "Y").map((p) => p.keterangan),
            pilihan: pilihan.map((p) => ({ ...p, terpilih: p.keterangan === cocok.keterangan })),
        };
        return;
    }

    // Belum ada di master: nilai baru, tetap belum dapat dinilai.
    const n = Number(b.Hasil);
    if (!isNaN(n)) b.Hasil = geserAngka(n);
}

const bulat = (n) => Math.round(n * 100) / 100;
const geserAngka = (n) => bulat(n * (0.9 + Math.random() * 0.2));

/** Rekomendasi verifikator untuk satu klasifikasi (juga revisi). */
export function verifikasi(state, noSampel, kode, keputusan, catatan, pengguna) {
    const s = cariSampel(state, noSampel);
    const k = cariKlasifikasi(s, kode);
    if (!k || s.finalisasi) return false;
    const revisi = k.verifikasi ? (k.verifikasi.revisi || 0) + 1 : 0;
    k.verifikasi = {
        kode: keputusan.Kode_Keputusan, nama: keputusan.Nama_Keputusan, warna: keputusan.Warna_Badge,
        catatan: catatan || null, oleh: { id: pengguna.id, nama: pengguna.nama }, waktu: sekarang(), revisi,
    };
    k.riwayatVerifikasi.push({ ...k.verifikasi });
    return true;
}

/** Keputusan akhir satu sampel. */
export function finalisasi(state, noSampel, keputusan, catatan, pengguna) {
    const s = cariSampel(state, noSampel);
    if (!s || s.finalisasi || !siapFinal(s)) return false;
    s.finalisasi = {
        kode: keputusan.Kode_Keputusan, nama: keputusan.Nama_Keputusan, warna: keputusan.Warna_Badge,
        catatan: catatan || null, oleh: { id: pengguna.id, nama: pengguna.nama }, waktu: sekarang(),
    };
    return true;
}

/* ------------------------------------------------------------------ */
/* Keadaan                                                              */
/* ------------------------------------------------------------------ */

/** Klasifikasi selesai divalidasi: ada hasil, semuanya diterima, tak ada uji ulang tertunda. */
export const tervalidasi = (k) => k.analisa.length > 0
    && k.analisa.every((a) => a._status === "diterima")
    && !k.resampling.some((r) => !r.selesai);

export const siapFinal = (s) => s.klasifikasi.every((k) => tervalidasi(k) && k.verifikasi);

/** Saran keputusan akhir: tingkat terendah dari seluruh rekomendasi. */
export function saranFinal(s) {
    const kode = s.klasifikasi.map((k) => k.verifikasi && k.verifikasi.kode);
    if (kode.includes("TIDAK_REKOM")) return "TIDAK_REKOM";
    if (kode.includes("REKOM_BERSYARAT")) return "REKOM_BERSYARAT";
    return "REKOMENDASI";
}

/** Ringkas satu klasifikasi untuk kartu antrean. */
function ringkasKlasifikasi(s, k, baris) {
    const plt = k.Butuh_Pembanding;
    const grup = perAnalisa(baris);
    const layak = grup.map((g) => ({ nama: g.nama, l: layakAnalisa(g.baris, plt) }));
    const akhir = baris.reduce((m, a) => (waktuBaris(a) > m ? waktuBaris(a) : m), "");
    return {
        kunci: s.No_Sampel + "|" + k.kode,
        No_Sampel: s.No_Sampel,
        Kode_Aktivitas_Lab: k.kode,
        Nama_Aktivitas: k.nama,
        Butuh_Pembanding: plt,
        Jumlah_Analisa: grup.length,
        Analisa_Tidak_Layak: layak.filter((x) => x.l === "T").map((x) => x.nama),
        Analisa_Tanpa_Master: layak.filter((x) => x.l === "N").map((x) => x.nama),
        Putaran: baris.reduce((m, a) => Math.max(m, a._putaran || 1), 1),
        Multi: !!k.Multi,
        Waktu: akhir,
        Penguji: [...new Set(baris.map((a) => a.Input && a.Input.nama).filter(Boolean))],
        No_Po: s.No_Po, No_Split_Po: s.No_Split_Po, No_Batch: s.No_Batch,
        Kode_Barang: s.Kode_Barang, Nama_Barang: s.Nama_Barang, Kode_Formula: s.Kode_Formula,
        Nama_Mesin: s.Nama_Mesin, Judul: s.Judul,
    };
}

/** Antrean validasi akun ini (hak = id jenis analisa yang boleh divalidasi). */
export function antreanValidasi(state, hak) {
    const hasil = [];
    state.sampel.forEach((s) => {
        if (s.finalisasi) return;
        s.klasifikasi.forEach((k) => {
            const menunggu = k.analisa.filter((a) => a._status === "menunggu" && boleh(hak, a.Id_Jenis_Analisa));
            const ulang = k.resampling.filter((r) => !r.selesai && boleh(hak, r.id));
            if (!menunggu.length && !ulang.length) return;
            const it = ringkasKlasifikasi(s, k, menunggu);
            it.Menunggu_Ulang = ulang.map((r) => r.nama);
            if (!menunggu.length) it.Waktu = ulang[ulang.length - 1].waktu;
            hasil.push(it);
        });
    });
    return urutkan(hasil);
}

/** Klasifikasi yang boleh diverifikasi akun ini ({kode: ids|null}, '*' = semua). */
export const bolehVerifikasi = (kewenangan, kode) => "*" in kewenangan || kode in kewenangan;

/** Antrean verifikasi: 'menunggu' atau 'sudah' (revisi masih boleh sebelum final). */
export function antreanVerifikasi(state, kewenangan, mode = "menunggu") {
    const hasil = [];
    state.sampel.forEach((s) => {
        if (s.finalisasi) return;
        s.klasifikasi.forEach((k) => {
            if (!tervalidasi(k) || !bolehVerifikasi(kewenangan, k.kode)) return;
            if ((mode === "menunggu") === !!k.verifikasi) return;
            const it = ringkasKlasifikasi(s, k, k.analisa);
            it.Verifikasi = k.verifikasi;
            hasil.push(it);
        });
    });
    return urutkan(hasil);
}

/** Antrean finalisasi: sampel yang seluruh klasifikasinya lolos validasi. */
export function antreanFinalisasi(state, mode = "siap") {
    return state.sampel
        .filter((s) => !s.finalisasi && s.klasifikasi.every(tervalidasi))
        .filter((s) => mode === "semua" || (mode === "siap") === siapFinal(s))
        .map((s) => ringkasSampel(s));
}

/** Hasil analisa: sampel yang sudah difinalisasi. */
export function daftarHasil(state) {
    return state.sampel.filter((s) => s.finalisasi).map((s) => ringkasSampel(s))
        .sort((a, b) => (a.Final_Waktu < b.Final_Waktu ? 1 : -1));
}

function ringkasSampel(s) {
    const semua = s.klasifikasi.flatMap((k) => (k.Butuh_Pembanding ? [] : perAnalisa(k.analisa)
        .map((g) => ({ nama: g.nama, l: layakAnalisa(g.baris, false) }))));
    return {
        kunci: s.No_Sampel,
        No_Sampel: s.No_Sampel,
        klasifikasi: s.klasifikasi.map((k) => ({
            kode: k.kode, nama: k.nama, verifikasi: k.verifikasi, jumlah: perAnalisa(k.analisa).length,
        })),
        Jumlah_Analisa: s.klasifikasi.reduce((n, k) => n + perAnalisa(k.analisa).length, 0),
        Belum_Verifikasi: s.klasifikasi.filter((k) => !k.verifikasi).length,
        Siap: siapFinal(s),
        Saran: siapFinal(s) ? saranFinal(s) : null,
        Analisa_Tidak_Layak: semua.filter((x) => x.l === "T").map((x) => x.nama),
        Analisa_Tanpa_Master: semua.filter((x) => x.l === "N").map((x) => x.nama),
        Finalisasi: s.finalisasi,
        Final_Waktu: s.finalisasi ? s.finalisasi.waktu : "",
        No_Po: s.No_Po, No_Split_Po: s.No_Split_Po, No_Batch: s.No_Batch,
        Kode_Barang: s.Kode_Barang, Nama_Barang: s.Nama_Barang, Kode_Formula: s.Kode_Formula,
        Nama_Mesin: s.Nama_Mesin, Judul: s.Judul, Waktu: s.dibuat,
    };
}

const urutkan = (x) => x.sort((a, b) => (a.Waktu < b.Waktu ? 1 : a.Waktu > b.Waktu ? -1 : a.kunci.localeCompare(b.kunci)));

/** Angka stepper untuk seluruh akun. */
export function hitungTahap(state) {
    let validasi = 0, verifikasi = 0;
    state.sampel.forEach((s) => {
        if (s.finalisasi) return;
        s.klasifikasi.forEach((k) => {
            if (!tervalidasi(k)) validasi++;
            else if (!k.verifikasi) verifikasi++;
        });
    });
    return {
        validasi,
        verifikasi,
        finalisasi: state.sampel.filter((s) => !s.finalisasi && siapFinal(s)).length,
        hasil: state.sampel.filter((s) => s.finalisasi).length,
    };
}

/* ------------------------------------------------------------------ */
/* Sample Lifecycle — bentuk sama dengan LifecycleSampelService         */
/* ------------------------------------------------------------------ */

const TINGKAT_KEPUTUSAN = { ok: "ok", warn: "warn", bad: "bad" };

function statusAnalisa(a, plt) {
    if (plt) return "info";
    return { Y: "ok", T: "bad", N: "na" }[a.Flag_Layak] || "na";
}

function standarAnalisa(a) {
    const r = a.Rincian_Kelayakan || {};
    if (a.Dasar_Kelayakan === "RENTANG") return { jenis: "rentang", min: r.min, max: r.max };
    if (a.Dasar_Kelayakan === "KRITERIA") return { jenis: "kriteria", teks: r.kriteria_terpilih };
    return null;
}

/** Baris analisa untuk kartu "Uji sampel". */
function analisaUji(rows, plt, sebelumnya) {
    return perAnalisa(rows).map(({ id, nama, baris }) => {
        const a = baris[0];
        const nilai = baris.map((b) => b.Nilai_Hasil_String || b.Hasil);
        const angka = baris.map((b) => Number(b.Hasil)).filter((n) => !isNaN(n));
        const lama = sebelumnya && sebelumnya[id];
        return {
            id_jenis_analisa: id,
            nama,
            status: plt ? "info" : (baris.some((b) => b.Flag_Layak === "T") ? "bad" : statusAnalisa(a, false)),
            nilai,
            jumlah: plt ? 1 : baris.length,
            min: angka.length > 1 && !a.Nilai_Hasil_String ? Math.min(...angka) : null,
            maks: angka.length > 1 && !a.Nilai_Hasil_String ? Math.max(...angka) : null,
            standar: plt ? null : standarAnalisa(a),
            rincian: plt ? baris.map((b) => ({ nilai: b.Hasil })) : null,
            sebelumnya: lama || null,
            sub: a.No_Sampel_Uji,
            penguji: a.Input ? [{ id: a.Input.id, nama: a.Input.nama }] : [],
            waktu: waktuBaris(a),
            foto: (a.Foto || []).length ? { aktif: a.Foto.length, nonaktif: 0 } : null,
        };
    });
}

function kejadianUji(k, rows, putaran, sebelumnya) {
    const plt = k.Butuh_Pembanding;
    const waktu = rows.map(waktuBaris).sort();
    const analisa = analisaUji(rows, plt, sebelumnya);
    const penguji = [];
    rows.forEach((a) => {
        if (a.Input && !penguji.some((p) => p.id === a.Input.id)) {
            penguji.push({ id: a.Input.id, nama: a.Input.nama, peran: "Penguji" });
        }
    });
    const tl = analisa.filter((a) => a.status === "bad").length;
    return {
        id: `uji-${k.kode}-${putaran}`, jenis: "uji", klasifikasi: k.kode, putaran,
        waktu: waktu[0], waktu_akhir: waktu[waktu.length - 1],
        tingkat: "ok", judul: putaran > 1 ? "Uji sampel resampling" : "Uji sampel",
        label: `${analisa.length} analisa`, pelaku: penguji,
        ringkas: tl ? `${tl} analisa di luar standar.` : null, analisa,
    };
}

function kejadianValidasi(k, putaran, rowsAkhir, ditolak) {
    const keputusan = [];
    perAnalisa(rowsAkhir).forEach(({ id, nama, baris }) => {
        const a = baris[0];
        keputusan.push({
            id_jenis_analisa: id, nama,
            keputusan: a._status === "diterima" ? "diterima" : "menunggu",
            validator: a.Validasi ? { id: a.Validasi.id, nama: a.Validasi.nama } : null,
            waktu: a.Validasi ? `${a.Validasi.tanggal} ${a.Validasi.jam}` : null,
            status: statusAnalisa(a, k.Butuh_Pembanding),
        });
    });
    ditolak.forEach((r) => keputusan.push({
        id_jenis_analisa: r.id, nama: r.nama, keputusan: "resampling",
        validator: r.oleh, waktu: r.waktu, status: "bad",
    }));

    const diterima = keputusan.filter((x) => x.keputusan === "diterima").length;
    const rs = keputusan.filter((x) => x.keputusan === "resampling").length;
    const menunggu = keputusan.filter((x) => x.keputusan === "menunggu").length;
    const bagian = [];
    if (diterima) bagian.push(`${diterima} diterima`);
    if (rs) bagian.push(`${rs} resampling`);
    if (menunggu) bagian.push(`${menunggu} menunggu`);
    const tingkat = rs ? "warn" : menunggu ? (diterima ? "run" : "wait") : "ok";
    const waktu = keputusan.map((x) => x.waktu).filter(Boolean).sort();
    const validator = [];
    keputusan.forEach((x) => {
        if (x.validator && !validator.some((v) => v.id === x.validator.id)) {
            validator.push({ ...x.validator, peran: "Validator" });
        }
    });
    return {
        id: `val-${k.kode}-${putaran}`, jenis: "val", klasifikasi: k.kode, putaran,
        waktu: waktu[0] || null, waktu_akhir: waktu[waktu.length - 1] || null,
        tingkat, judul: putaran > 1 ? "Validasi resampling" : "Validasi",
        label: menunggu && !diterima && !rs ? `Menunggu ${menunggu}` : bagian.join(" · "),
        pelaku: validator,
        ringkas: rs ? ditolak.map((r) => r.nama).join(", ") + " diputuskan resampling." : null,
        keputusan,
    };
}

/**
 * Rakit Sample Lifecycle satu sampel mockup.
 *
 * hanya: kode klasifikasi. Layar Validasi & Verifikasi bekerja per
 * klasifikasi, jadi lifecycle-nya hanya memuat klasifikasi yang dibuka —
 * klasifikasi lain tidak ikut di jalur, jadwal registrasi, strip fase,
 * maupun posisi. Finalisasi & Hasil Analisa (null) memuat seluruhnya.
 */
export function lifecycle(s, hanya = null) {
    const daftarKl = hanya ? s.klasifikasi.filter((k) => k.kode === hanya) : s.klasifikasi;
    const klasifikasi = daftarKl.map((k) => {
        const kejadian = [];
        const alur = [];
        const maksPutaran = Math.max(1, ...k.analisa.map((a) => a._putaran || 1),
            ...k.riwayat.map((r) => r.putaran + 1));

        for (let p = 1; p <= maksPutaran; p++) {
            // Baris putaran ini: yang masih berlaku + yang ditolak di putaran ini.
            const ditolak = k.riwayat.filter((r) => r.putaran === p);
            const berlaku = k.analisa.filter((a) => (a._putaran || 1) === p);
            const rows = [...berlaku, ...ditolak.flatMap((r) => r.baris)];
            const ulangTertunda = k.resampling.filter((r) => !r.selesai && r.putaran === p - 1);

            if (rows.length) {
                // Nilai putaran sebelumnya, untuk kartu uji resampling.
                const sebelumnya = {};
                if (p > 1) {
                    k.riwayat.filter((r) => r.putaran === p - 1).forEach((r) => {
                        const a = r.baris[0];
                        sebelumnya[r.id] = {
                            nilai: r.baris.map((b) => b.Nilai_Hasil_String || b.Hasil),
                            jumlah: k.Butuh_Pembanding ? 1 : r.baris.length,
                            status: statusAnalisa(a, k.Butuh_Pembanding),
                        };
                    });
                }
                kejadian.push(kejadianUji(k, rows, p, sebelumnya));
                alur.push({ jenis: "uji", tingkat: "ok" });
                const val = kejadianValidasi(k, p, berlaku, k.resampling.filter((r) => r.putaran === p));
                kejadian.push(val);
                alur.push({ jenis: "val", tingkat: val.tingkat });

                k.resampling.filter((r) => r.putaran === p).forEach((r, i) => {
                    kejadian.push({
                        id: `rs-${k.kode}-${p}-${i}`, jenis: "rs", klasifikasi: k.kode, putaran: p,
                        waktu: r.waktu, tingkat: "warn", judul: "Resampling", label: r.nama,
                        pelaku: [{ ...r.oleh, peran: "Peminta resampling" }],
                        ringkas: `Hasil putaran ${p} tetap tersimpan sebagai data ditolak.`,
                        alasan: r.alasan,
                        resampling: [{
                            nama: r.nama, selesai: r.selesai, sama: r.sama, asal: r.asal, baru: r.baru,
                            oleh: r.oleh, waktu: r.waktu,
                            keterangan: r.sama ? "Reanalisa tanpa multi QR (sampel sama)" : "Pindah ke sub sampel baru",
                        }],
                    });
                    alur.push({ jenis: "rs", tingkat: "warn" });
                });
            } else if (ulangTertunda.length) {
                kejadian.push({
                    id: `uji-${k.kode}-${p}`, jenis: "uji", klasifikasi: k.kode, putaran: p,
                    waktu: null, tingkat: "wait", judul: "Uji sampel resampling", label: "Menunggu",
                    ringkas: "Menunggu hasil uji ulang untuk " + ulangTertunda.map((r) => r.nama).join(", ") + ".",
                });
                alur.push({ jenis: "uji", tingkat: "wait" });
            }
        }

        // Verifikasi
        const tervalid = tervalidasi(k);
        if (k.verifikasi) {
            (k.riwayatVerifikasi.length ? k.riwayatVerifikasi : [k.verifikasi]).forEach((v, i, semua) => {
                kejadian.push({
                    id: `ver-${k.kode}-${i}`, jenis: "ver", klasifikasi: k.kode, waktu: v.waktu,
                    tingkat: TINGKAT_KEPUTUSAN[v.warna] || "ok",
                    judul: v.revisi ? `Verifikasi (revisi ${v.revisi})` : "Verifikasi",
                    label: v.nama, pelaku: [{ ...v.oleh, peran: "Verifikator" }],
                    catatan: v.catatan, catatan_judul: judulRiwayatCatatan(v.kode, "verifikasi"),
                    ringkas: i < semua.length - 1 ? "Digantikan oleh revisi berikutnya." : null,
                });
            });
        } else {
            const petugas = k.petugas_verifikasi || [];
            const nama = petugas.map((p) => p.nama).join(", ");
            kejadian.push({
                id: `ver-${k.kode}`, jenis: "ver", klasifikasi: k.kode, waktu: null, tingkat: "wait",
                judul: "Verifikasi", label: "Menunggu",
                pelaku: petugas.map((p) => ({ id: p.id, nama: p.nama, peran: p.sebagian ? `Bertugas (${p.jumlah} analisa)` : "Bertugas" })),
                ringkas: !petugas.length ? "Belum ada verifikator yang ditugaskan untuk klasifikasi ini."
                    : (tervalid ? `Menunggu rekomendasi dari ${nama}.` : `Menunggu validasi selesai, lalu rekomendasi dari ${nama}.`),
            });
        }
        alur.push({ jenis: "ver", tingkat: k.verifikasi ? (TINGKAT_KEPUTUSAN[k.verifikasi.warna] || "ok") : "wait" });

        const adaUlang = k.resampling.some((r) => !r.selesai);
        const menungguVal = k.analisa.some((a) => a._status === "menunggu");
        const status = k.verifikasi
            ? { label: k.verifikasi.nama, tingkat: TINGKAT_KEPUTUSAN[k.verifikasi.warna] || "ok" }
            : adaUlang ? { label: "Menunggu uji ulang", tingkat: "warn" }
                : menungguVal ? { label: "Menunggu validasi", tingkat: "run" }
                    : { label: "Menunggu verifikasi", tingkat: "run" };
        const namaRs = [...new Set(k.resampling.map((r) => r.nama))];
        const putaranTampil = Math.max(1, ...k.analisa.map((a) => a._putaran || 1));

        return {
            kode: k.kode, nama: k.nama, urutan: k.urutan,
            jumlah_analisa: perAnalisa([...k.analisa, ...k.riwayat.flatMap((r) => r.baris)]).length,
            putaran: putaranTampil, jumlah_resampling: k.resampling.length,
            ringkas: `${putaranTampil} putaran` + (namaRs.length ? ` · resampling ${namaRs.join(", ")}` : ""),
            status, alur, kejadian,
        };
    });

    const reg = {
        id: "reg", jenis: "reg", waktu: s.Registrasi.waktu, tingkat: "ok",
        judul: "Registrasi sampel", label: "Selesai",
        pelaku: [{ id: s.Registrasi.id, nama: s.Registrasi.nama, peran: "Pendaftar" }],
        ringkas: `PO ${s.No_Po} · batch ${s.No_Batch} · mesin ${s.Nama_Mesin || "-"} · trial produksi.`,
        jadwal: daftarKl.map((k) => `${k.nama} · ${perAnalisa([...k.analisa, ...k.riwayat.flatMap((r) => r.baris)]).length} analisa`),
    };

    const fin = s.finalisasi
        ? [{
            id: "fin", jenis: "fin", waktu: s.finalisasi.waktu, tingkat: TINGKAT_KEPUTUSAN[s.finalisasi.warna] || "ok",
            judul: "Finalisasi", label: s.finalisasi.nama,
            pelaku: [{ ...s.finalisasi.oleh, peran: "Finalisasi" }], catatan: s.finalisasi.catatan,
            catatan_judul: judulRiwayatCatatan(s.finalisasi.kode, "finalisasi"),
        }]
        : [{ id: "fin", jenis: "fin", waktu: null, tingkat: "wait", judul: "Finalisasi", label: "Menunggu",
             ringkas: "Sampel belum difinalisasi." }];

    // Strip fase
    const semuaVal = daftarKl.every(tervalidasi);
    const adaUlang = daftarKl.some((k) => k.resampling.some((r) => !r.selesai));
    const jumlahRs = daftarKl.reduce((n, k) => n + k.resampling.length, 0);
    const semuaVer = daftarKl.every((k) => k.verifikasi);
    const sebagianVer = daftarKl.some((k) => k.verifikasi);
    const fase = [
        { kode: "REG", label: "Registrasi", tingkat: "ok" },
        { kode: "UJI", label: "Uji sampel", tingkat: adaUlang ? "run" : "ok" },
        { kode: "VAL", label: "Validasi", tingkat: semuaVal ? "ok" : "run" },
    ];
    if (jumlahRs) fase.push({ kode: "RS", label: "Resampling", tingkat: "warn", jumlah: jumlahRs });
    fase.push({ kode: "VER", label: "Verifikasi", tingkat: semuaVer ? "ok" : sebagianVer ? "run" : "wait" });
    fase.push({ kode: "FIN", label: "Finalisasi", tingkat: s.finalisasi ? (TINGKAT_KEPUTUSAN[s.finalisasi.warna] || "ok") : "wait" });

    // Posisi sekarang
    let posisi;
    if (s.finalisasi) {
        posisi = { label: "Selesai · " + s.finalisasi.nama, tingkat: TINGKAT_KEPUTUSAN[s.finalisasi.warna] || "ok", keterangan: null };
    } else {
        const cari = (label) => klasifikasi.filter((k) => k.status.label === label).map((k) => k.nama);
        const urut = [["Menunggu uji ulang", "warn", "Menunggu uji sampel resampling"],
                      ["Menunggu validasi", "run", "Menunggu validasi"],
                      ["Menunggu verifikasi", "run", "Menunggu verifikasi"]];
        const kena = urut.find(([l]) => cari(l).length);
        posisi = kena
            ? { label: kena[2], tingkat: kena[1], keterangan: cari(kena[0]).join(", ") }
            : { label: "Menunggu finalisasi", tingkat: "run",
                // Satu klasifikasi saja: finalisasi tetap menunggu seluruh klasifikasi sampel.
                keterangan: hanya && !siapFinal(s) ? "setelah seluruh klasifikasi terverifikasi" : null };
    }

    return {
        sampel: { No_Sampel: s.No_Sampel },
        posisi, fase, registrasi: [reg], klasifikasi, finalisasi: fin, catatan_data: [],
    };
}
