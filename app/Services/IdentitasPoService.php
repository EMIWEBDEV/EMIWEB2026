<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Identitas barang tiap PO trial produksi di antrean sandbox: nama barang
 * dan nomor formula (FRM).
 *
 * Dipakai layar Validasi dan Finalisasi sandbox untuk tampilan sekaligus
 * pencarian ("cari nama barang / FRM").
 *
 * Diambil sekali untuk seluruh antrean — jumlah PO-nya kecil — bukan lewat
 * subquery per baris uji: N_EMI_View_Barang berat bila dipindai berulang
 * (produksi: ±380 ms subquery per baris vs ±140 ms sekali ambil). Formula
 * berasal dari order produksinya: EMI_Order_Produksi.No_Faktur = No_Po
 * sampel (produksi: 59 dari 59 PO trial cocok, satu PO satu order).
 *
 * Kunci larik dinormalkan (lihat kunci()) karena SQL Server membandingkan
 * tanpa peduli huruf besar-kecil dan spasi di ujung, sedangkan larik PHP
 * tidak.
 */
class IdentitasPoService
{
    /** @var array{barang: array<string,string>, formula: array<string,string>}|null */
    private ?array $peta = null;

    public function __construct(private CakupanMesinService $cakupan)
    {
    }

    /**
     * Seluruh PO trial produksi yang belum selesai, dalam cakupan mesin.
     *
     * @return array{barang: array<string,string>, formula: array<string,string>}
     */
    public function peta(): array
    {
        if ($this->peta !== null) {
            return $this->peta;
        }

        $po = DB::table('N_EMI_LAB_PO_Sampel as p')
            ->where('p.Flag_Trial_Produksi', 'Y')
            ->whereNull('p.Flag_Selesai')
            ->tap(fn ($q) => $this->cakupan->batasi($q, 'p.Id_Mesin'))
            ->select('p.No_Po', 'p.Kode_Barang')
            ->distinct()
            ->get();

        $barang = [];
        foreach ($po->pluck('Kode_Barang')->filter()->unique()->chunk(1000) as $kode) {
            DB::table('N_EMI_View_Barang')
                ->whereIn('Kode_Barang', $kode->values())
                ->select('Kode_Barang', DB::raw('MAX(Nama) as Nama'))
                ->groupBy('Kode_Barang')
                ->get()
                ->each(function ($b) use (&$barang) {
                    $barang[self::kunci($b->Kode_Barang)] = trim((string) $b->Nama);
                });
        }

        $formula = [];
        foreach ($po->pluck('No_Po')->filter()->unique()->chunk(1000) as $no) {
            DB::table('EMI_Order_Produksi')
                ->whereIn('No_Faktur', $no->values())
                ->whereNotNull('Kode_Formula')
                ->select('No_Faktur', 'Kode_Formula')
                ->get()
                ->each(function ($o) use (&$formula) {
                    $formula[self::kunci($o->No_Faktur)] = trim((string) $o->Kode_Formula);
                });
        }

        return $this->peta = ['barang' => $barang, 'formula' => $formula];
    }

    public function namaBarang($kodeBarang): ?string
    {
        return ($this->peta()['barang'][self::kunci($kodeBarang)] ?? '') ?: null;
    }

    public function formula($noPo): ?string
    {
        return ($this->peta()['formula'][self::kunci($noPo)] ?? '') ?: null;
    }

    /** Kode barang yang namanya memuat kata ini. */
    public function kodeBarangCocok(string $kata): array
    {
        return array_keys(array_filter($this->peta()['barang'],
            fn ($nama) => mb_stripos($nama, $kata) !== false));
    }

    /** Nomor PO yang nomor formulanya memuat kata ini. */
    public function poCocok(string $kata): array
    {
        return array_keys(array_filter($this->peta()['formula'],
            fn ($frm) => mb_stripos($frm, $kata) !== false));
    }

    /**
     * Apakah kata kunci cocok dengan teks-teks ini.
     *
     * Beberapa kata dicocokkan masing-masing, di teks mana pun —
     * "life cat tuna" menemukan "LIFE CAT 400GR TUNA ADULT".
     */
    public static function cocok(string $cari, array $teks): bool
    {
        $gabung = mb_strtolower(implode(' | ', array_map('strval', $teks)));

        foreach (self::kata($cari) as $kata) {
            if (!str_contains($gabung, mb_strtolower($kata))) {
                return false;
            }
        }

        return true;
    }

    /** Kata-kata pencarian, tanpa yang kosong. */
    public static function kata(string $cari): array
    {
        return preg_split('/\s+/u', trim($cari), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** Kunci larik untuk kode/nomor dari database: tanpa spasi ujung, huruf besar. */
    public static function kunci($nilai): string
    {
        return strtoupper(trim((string) $nilai));
    }
}
