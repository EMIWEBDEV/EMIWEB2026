// Pembantu waktu untuk Sample Lifecycle. Waktu dari server berbentuk
// "YYYY-MM-DD HH:mm:ss" (jam server lab), dibaca sebagai waktu lokal.

/** "2026-09-24 13:25:08" -> Date lokal; null bila tidak terbaca. */
export function uraiWaktu(s) {
    if (!s) return null;
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (!m) return null;
    return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0));
}

/** Durasi ringkas: "7 mnt", "1 jam 5 mnt", "2 hari 3 jam" (lengkap: semua bagian). */
export function durasi(ms, lengkap = false) {
    const mnt = Math.floor(ms / 60000);
    if (!(mnt >= 1)) return "";
    const h = Math.floor(mnt / 1440);
    const j = Math.floor((mnt % 1440) / 60);
    const m = mnt % 60;
    const p = [];
    if (h) p.push(h + " hari");
    if (j) p.push(j + " jam");
    if (m) p.push(m + " mnt");
    return (lengkap ? p : p.slice(0, 2)).join(" ");
}

const HARI_PANJANG = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
const BULAN_PANJANG = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli",
    "Agustus", "September", "Oktober", "November", "Desember"];

/** "Kamis, 24 September 2026" — pemisah hari pada tampilan kronologis. */
export function tanggalPanjang(s) {
    const d = uraiWaktu(s);
    if (!d) return "";
    return `${HARI_PANJANG[d.getDay()]}, ${d.getDate()} ${BULAN_PANJANG[d.getMonth()]} ${d.getFullYear()}`;
}
