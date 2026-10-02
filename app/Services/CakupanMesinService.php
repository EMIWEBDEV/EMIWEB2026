<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Cakupan mesin modul Verifikasi & Finalisasi pembaharuan: AUTOCLAVE saja.
 *
 * Kedua tahap hanya melayani sampel yang diproses di mesin AUTOCLAVE.
 * Sampel dari mesin lain tidak masuk antrean, dan rekomendasi maupun
 * keputusan atasnya ditolak di server — menyembunyikannya di layar saja
 * tidak cukup, karena permintaan dapat dikirim langsung.
 *
 * Mesin dikenali dari NAMANYA di EMI_Master_Mesin, bukan dari id: id
 * berbeda antarlingkungan (demo, produksi), sedangkan namanya tetap. Nama
 * dicocokkan sebagai awalan, sehingga "AUTOCLAVE 2" ikut tercakup.
 */
class CakupanMesinService
{
    /** Awalan nama mesin yang dilayani. */
    public const AWALAN_NAMA = 'AUTOCLAVE';

    /** @var array<int, string>|null  [id mesin => nama], dibaca sekali per permintaan */
    private ?array $mesin = null;

    /** Mesin dalam cakupan: [id => nama]. */
    public function mesin(): array
    {
        if ($this->mesin === null) {
            $this->mesin = DB::table('EMI_Master_Mesin')
                ->whereRaw('UPPER(LTRIM(Nama_Mesin)) LIKE ?', [self::AWALAN_NAMA . '%'])
                ->orderBy('Id_Master_Mesin')
                ->pluck('Nama_Mesin', 'Id_Master_Mesin')
                ->map(fn ($nama) => trim((string) $nama))
                ->all();
        }

        return $this->mesin;
    }

    /** Id mesin dalam cakupan. */
    public function id(): array
    {
        return array_keys($this->mesin());
    }

    /** Nama mesin dalam cakupan, untuk ditampilkan dan disebut di pesan. */
    public function nama(): array
    {
        return array_values(array_unique($this->mesin())) ?: [self::AWALAN_NAMA];
    }

    /**
     * Batasi query ke sampel bermesin dalam cakupan.
     *
     * Bila master tidak memuat mesin AUTOCLAVE sama sekali, whereIn dengan
     * daftar kosong menghasilkan nol baris — gagal tertutup, bukan terbuka.
     */
    public function batasi($query, string $kolomIdMesin)
    {
        return $query->whereIn($kolomIdMesin, $this->id());
    }

    /** Apakah mesin ini termasuk cakupan. */
    public function mencakup($idMesin): bool
    {
        return $idMesin !== null && $idMesin !== ''
            && array_key_exists((int) $idMesin, $this->mesin());
    }
}
