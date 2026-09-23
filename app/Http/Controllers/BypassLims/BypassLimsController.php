<?php

namespace App\Http\Controllers\BypassLims;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Panel Bypass LIMS - LIMS Toolkit.
 *
 * Kumpulan tindakan administratif yang melewati alur normal aplikasi:
 *  1. Tambah sub sampel (multi QR) pada sampel yang sudah teregistrasi
 *  2. Batalkan registrasi sampel
 *  3. Batalkan uji sampel per jenis analisa
 *  4. Atur template printer TSC (ukuran label + item TEXT/QRCODE)
 *
 * Seluruh endpoint di sini hanya bisa diakses lewat middleware bypass-lims.
 */
class BypassLimsController extends Controller
{
    /** Channel log yang dipakai untuk jejak audit panel ini. */
    private const LOG_CHANNEL = 'stack';

    public function index(Request $request)
    {
        // Panel berdiri sendiri di luar layout dashboard (tanpa sidebar/topbar),
        // karena diakses tanpa sesi login biasa.
        Inertia::setRootView('bypass-lims');

        return inertia('vue/dashboard/bypass-lims/HomeBypassLims', [
            'gembokSecret' => $request->query('gembok-secret'),
        ]);
    }

    /**
     * Waktu server SQL, dipakai agar Tanggal/Jam konsisten dengan modul lain.
     */
    private function waktuServer(): array
    {
        $dt = DB::select("SELECT dbo.Get_Date_Time() as DateTimeNow")[0]->DateTimeNow;

        return [
            'tanggal' => date('Y-m-d', strtotime($dt)),
            'jam'     => date('H:i:s', strtotime($dt)),
        ];
    }

    /**
     * Catat setiap tindakan bypass supaya tetap ada jejak audit.
     */
    private function catatJejak(string $aksi, array $konteks): void
    {
        Log::channel(self::LOG_CHANNEL)->warning("[BYPASS-LIMS] {$aksi}", $konteks + [
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Pesan error yang aman dikirim ke klien.
     *
     * Detail asli (termasuk pesan driver SQL Server yang bisa membocorkan nama
     * objek database) hanya masuk log, sementara operator cukup menerima kode
     * rujukan untuk dilaporkan ke IT.
     */
    private function pesanAman(\Throwable $e, string $pesan): string
    {
        $kode = strtoupper(substr(md5($e->getFile() . $e->getLine() . microtime()), 0, 8));

        Log::channel(self::LOG_CHANNEL)->error("[BYPASS-LIMS][{$kode}] " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return "{$pesan} (Kode: {$kode})";
    }

    /* =====================================================================
     |  FITUR 1 - TAMBAH SUB SAMPEL
     ===================================================================== */

    /**
     * Tentukan apakah sebuah sampel boleh punya sub sampel.
     *
     * Sumber kebenarannya adalah master mesin, sama seperti yang dipakai modul
     * registrasi dan cetak ulang QR: mesin dengan Flag_Multi_Qrcode = 'Y' dan
     * Jumlah_Print_QRCode > 1 mencetak beberapa QR per sampel, sedangkan mesin
     * lain hanya menghasilkan satu QR (single) sehingga tidak punya sub sampel.
     *
     * @param  object  $sampel  Baris PO Sampel yang sudah di-join ke master mesin.
     */
    private function periksaKelayakanMultiQr($sampel): array
    {
        $flag         = $sampel->Flag_Multi_Qrcode ?? null;
        $jumlahPrint  = (int) ($sampel->Jumlah_Print_QRCode ?? 0);
        $adaMesin     = ! empty($sampel->Id_Mesin);
        $mesinDikenal = ! empty($sampel->Nama_Mesin);

        if (! $adaMesin || ! $mesinDikenal) {
            return [
                'boleh'   => false,
                'mode'    => 'TIDAK_DIKENAL',
                'alasan'  => 'Mesin pada sampel ini tidak ditemukan di master mesin, '
                    . 'sehingga status multi QR code tidak bisa dipastikan.',
                'flag'          => $flag,
                'jumlah_print'  => $jumlahPrint,
                'nama_mesin'    => $sampel->Nama_Mesin ?? null,
            ];
        }

        if ($flag !== 'Y') {
            return [
                'boleh'  => false,
                'mode'   => 'SINGLE',
                'alasan' => 'Sampel ini single QR code karena mesin '
                    . ($sampel->Nama_Mesin ?? '-')
                    . ' tidak diset multi QR code, sehingga tidak bisa ditambahkan sub sampel.',
                'flag'         => $flag,
                'jumlah_print' => $jumlahPrint,
                'nama_mesin'   => $sampel->Nama_Mesin ?? null,
            ];
        }

        // Master mesin mensyaratkan Jumlah_Print_QRCode > 1 untuk mode multi.
        if ($jumlahPrint <= 1) {
            return [
                'boleh'  => false,
                'mode'   => 'MULTI_TIDAK_VALID',
                'alasan' => 'Mesin ' . ($sampel->Nama_Mesin ?? '-')
                    . ' ditandai multi QR code, tetapi Jumlah Print QRCode-nya '
                    . $jumlahPrint . '. Perbaiki dulu di master mesin.',
                'flag'         => $flag,
                'jumlah_print' => $jumlahPrint,
                'nama_mesin'   => $sampel->Nama_Mesin ?? null,
            ];
        }

        return [
            'boleh'  => true,
            'mode'   => 'MULTI',
            'alasan' => 'Mesin ' . ($sampel->Nama_Mesin ?? '-')
                . ' diset multi QR code dengan ' . $jumlahPrint . ' print per sampel.',
            'flag'         => $flag,
            'jumlah_print' => $jumlahPrint,
            'nama_mesin'   => $sampel->Nama_Mesin ?? null,
        ];
    }

    /**
     * Ambil baris PO Sampel lengkap dengan atribut multi QR dari master mesin.
     */
    private function ambilSampelDenganMesin(string $noSampel)
    {
        return DB::table('N_EMI_LAB_PO_Sampel as po')
            ->leftJoin('EMI_Master_Mesin as m', 'po.Id_Mesin', '=', 'm.Id_Master_Mesin')
            ->where('po.No_Sampel', $noSampel)
            ->select(
                'po.*',
                'm.Nama_Mesin',
                'm.Flag_Multi_Qrcode',
                'm.Jumlah_Print_QRCode'
            )
            ->first();
    }

    /**
     * Cari sampel berikut informasi input, mesin, dan total sub sampel.
     */
    public function cariSampelSubSampel(Request $request)
    {
        $request->validate(['no_sampel' => 'required|string|max:50']);

        $noSampel = trim($request->input('no_sampel'));

        try {
            $sampel = DB::table('N_EMI_LAB_PO_Sampel as po')
                ->leftJoin('N_EMI_LAB_Users as u', 'po.Id_User', '=', 'u.UserId')
                ->leftJoin('EMI_Master_Mesin as m', 'po.Id_Mesin', '=', 'm.Id_Master_Mesin')
                ->leftJoin('N_EMI_View_Barang as b', 'po.Kode_Barang', '=', 'b.Kode_Barang')
                ->where('po.No_Sampel', $noSampel)
                ->select(
                    'po.id',
                    'po.No_Sampel',
                    'po.No_Po',
                    'po.No_Split_Po',
                    'po.No_Batch',
                    'po.Kode_Barang',
                    'po.Keterangan',
                    'po.Status',
                    'po.Flag_Selesai',
                    'po.Tanggal',
                    'po.Jam',
                    'po.Id_User',
                    'po.Id_Mesin',
                    'u.Nama as Nama_User',
                    'm.Nama_Mesin',
                    'm.Seri_Mesin',
                    // Penentu multi/single QR ada di master mesin, bukan di PO Sampel.
                    'm.Flag_Multi_Qrcode',
                    'm.Jumlah_Print_QRCode',
                    'b.Nama as Nama_Barang'
                )
                ->first();

            if (! $sampel) {
                return response()->json([
                    'success' => false,
                    'message' => "Nomor sampel {$noSampel} tidak ditemukan.",
                ], 404);
            }

            $subSampel = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
                ->where('No_Po_Sampel', $noSampel)
                ->orderBy('Id_Po_Sampel_Multi')
                ->select('Id_Po_Sampel_Multi as id', 'No_Po_Multi', 'Kode_Barang', 'Status', 'Tanggal', 'Jam')
                ->get();

            $kelayakan = $this->periksaKelayakanMultiQr($sampel);

            return response()->json([
                'success' => true,
                'result'  => [
                    'sampel'           => $sampel,
                    'sub_sampel'       => $subSampel,
                    'total_sub_sampel' => $subSampel->count(),
                    'multi_qrcode'     => $kelayakan,
                ],
            ]);
        } catch (\Exception $e) {
            $this->catatJejak('gagal cari sampel (sub sampel)', [
                'no_sampel' => $noSampel,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Terjadi kesalahan saat mengambil data.'),
            ], 500);
        }
    }

    /**
     * Pratinjau nomor sub sampel yang akan dibuat bila ditambah N buah.
     * Tidak menyentuh database sama sekali.
     */
    public function previewSubSampel(Request $request)
    {
        $request->validate([
            'no_sampel' => 'required|string|max:50',
            'jumlah'    => 'required|integer|min:1|max:200',
        ]);

        $noSampel = trim($request->input('no_sampel'));
        $jumlah   = (int) $request->input('jumlah');

        $sampel = $this->ambilSampelDenganMesin($noSampel);

        if (! $sampel) {
            return response()->json([
                'success' => false,
                'message' => "Nomor sampel {$noSampel} tidak ditemukan.",
            ], 404);
        }

        // Sampel single QR tidak punya pratinjau sub sampel sama sekali.
        $kelayakan = $this->periksaKelayakanMultiQr($sampel);

        if (! $kelayakan['boleh']) {
            return response()->json([
                'success'      => false,
                'message'      => $kelayakan['alasan'],
                'multi_qrcode' => $kelayakan,
            ], 422);
        }

        $existing = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
            ->where('No_Po_Sampel', $noSampel)
            ->orderBy('Id_Po_Sampel_Multi')
            ->select('Id_Po_Sampel_Multi as id', 'No_Po_Multi', 'Status', 'Tanggal', 'Jam')
            ->get();

        $urutTerakhir = $this->urutanSubSampelTerakhir($noSampel, $existing);

        $baru = [];
        for ($i = 1; $i <= $jumlah; $i++) {
            $baru[] = [
                'No_Po_Multi' => $noSampel . '-' . ($urutTerakhir + $i),
                'urutan'      => $urutTerakhir + $i,
            ];
        }

        return response()->json([
            'success' => true,
            'result'  => [
                'sebelum'       => $existing,
                'sesudah_baru'  => $baru,
                'total_sebelum' => $existing->count(),
                'total_sesudah' => $existing->count() + $jumlah,
            ],
        ]);
    }

    /**
     * Ambil nomor urut sub sampel tertinggi dari sufiks "-N" pada No_Po_Multi.
     * Memakai nilai maksimum sufiks (bukan jumlah baris) supaya tidak bentrok
     * kalau ada sub sampel yang pernah dihapus di tengah.
     */
    private function urutanSubSampelTerakhir(string $noSampel, $existing = null): int
    {
        $existing = $existing ?? DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
            ->where('No_Po_Sampel', $noSampel)
            ->get();

        $maks = 0;
        foreach ($existing as $row) {
            $potongan = strrchr($row->No_Po_Multi, '-');
            $suffix   = $potongan === false ? '' : substr($potongan, 1);

            if (is_numeric($suffix)) {
                $maks = max($maks, (int) $suffix);
            }
        }

        return $maks;
    }

    /**
     * Simpan sub sampel tambahan.
     *
     * Id_User, Tanggal, dan Jam sengaja disalin dari baris PO Sampel induk
     * (bukan dari user yang sedang membuka panel) sesuai permintaan, supaya
     * data tambahan menyatu dengan registrasi aslinya.
     */
    public function tambahSubSampel(Request $request)
    {
        $request->validate([
            'no_sampel' => 'required|string|max:50',
            'jumlah'    => 'required|integer|min:1|max:200',
        ]);

        $noSampel = trim($request->input('no_sampel'));
        $jumlah   = (int) $request->input('jumlah');

        DB::beginTransaction();

        try {
            $sampel = $this->ambilSampelDenganMesin($noSampel);

            if (! $sampel) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Nomor sampel {$noSampel} tidak ditemukan.",
                ], 404);
            }

            if ($sampel->Status === 'Y') {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Sampel {$noSampel} sudah dibatalkan, sub sampel tidak bisa ditambah.",
                ], 422);
            }

            // Penjaga utama: hanya sampel dari mesin multi QR yang boleh punya
            // sub sampel. Dicek ulang di server agar tidak bisa dilewati dari UI.
            $kelayakan = $this->periksaKelayakanMultiQr($sampel);

            if (! $kelayakan['boleh']) {
                DB::rollBack();

                return response()->json([
                    'success'      => false,
                    'message'      => $kelayakan['alasan'],
                    'multi_qrcode' => $kelayakan,
                ], 422);
            }

            // Kunci baris sub sampel milik nomor ini supaya dua permintaan
            // bersamaan tidak menghasilkan No_Po_Multi kembar.
            $sebelum = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
                ->where('No_Po_Sampel', $noSampel)
                ->orderBy('Id_Po_Sampel_Multi')
                ->lockForUpdate()
                ->get();

            $urutTerakhir = $this->urutanSubSampelTerakhir($noSampel, $sebelum);

            // Ikut baris induk supaya konsisten dengan registrasi aslinya.
            $payload = [];
            for ($i = 1; $i <= $jumlah; $i++) {
                $payload[] = [
                    'Kode_Perusahaan' => $sampel->Kode_Perusahaan ?? '001',
                    'No_Po_Multi'     => $noSampel . '-' . ($urutTerakhir + $i),
                    'Kode_Barang'     => $sampel->Kode_Barang,
                    'No_Po_Sampel'    => $noSampel,
                    'Status'          => null,
                    'Tanggal'         => $sampel->Tanggal,
                    'Jam'             => $sampel->Jam,
                ];
            }

            DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')->insert($payload);

            // Diambil sebelum commit supaya potret "sesudah" benar-benar
            // mencerminkan hasil transaksi ini, bukan perubahan sesi lain.
            $sesudah = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
                ->where('No_Po_Sampel', $noSampel)
                ->orderBy('Id_Po_Sampel_Multi')
                ->get();

            DB::commit();

            $nomorBaru = array_column($payload, 'No_Po_Multi');

            $this->catatJejak('tambah sub sampel', [
                'no_sampel' => $noSampel,
                'jumlah'    => $jumlah,
                'nomor'     => $nomorBaru,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menambah {$jumlah} sub sampel pada {$noSampel}.",
                'result'  => [
                    'sebelum'       => $sebelum,
                    'sesudah'       => $sesudah,
                    'ditambahkan'   => $nomorBaru,
                    'total_sebelum' => $sebelum->count(),
                    'total_sesudah' => $sesudah->count(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->catatJejak('gagal tambah sub sampel', [
                'no_sampel' => $noSampel,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Gagal menyimpan sub sampel.'),
            ], 500);
        }
    }

    /* =====================================================================
     |  FITUR 2 - BATALKAN REGISTRASI SAMPEL
     ===================================================================== */

    /**
     * Cari sampel beserta ringkasan uji, untuk konfirmasi sebelum dibatalkan.
     */
    public function cariSampelRegistrasi(Request $request)
    {
        $request->validate(['no_sampel' => 'required|string|max:50']);

        $noSampel = trim($request->input('no_sampel'));

        try {
            $sampel = DB::table('N_EMI_LAB_PO_Sampel as po')
                ->leftJoin('N_EMI_LAB_Users as u', 'po.Id_User', '=', 'u.UserId')
                ->leftJoin('EMI_Master_Mesin as m', 'po.Id_Mesin', '=', 'm.Id_Master_Mesin')
                ->leftJoin('N_EMI_View_Barang as b', 'po.Kode_Barang', '=', 'b.Kode_Barang')
                ->where('po.No_Sampel', $noSampel)
                ->select(
                    'po.id',
                    'po.No_Sampel',
                    'po.No_Po',
                    'po.No_Split_Po',
                    'po.No_Batch',
                    'po.Kode_Barang',
                    'po.Keterangan',
                    'po.Status',
                    'po.Flag_Selesai',
                    'po.Tanggal',
                    'po.Jam',
                    'po.Id_User',
                    'u.Nama as Nama_User',
                    'm.Nama_Mesin',
                    'b.Nama as Nama_Barang'
                )
                ->first();

            if (! $sampel) {
                return response()->json([
                    'success' => false,
                    'message' => "Nomor sampel {$noSampel} tidak ditemukan.",
                ], 404);
            }

            $jumlahUji = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $noSampel)
                ->whereNull('Status')
                ->count();

            $jumlahSub = DB::table('N_EMI_LAB_PO_Sampel_Multi_QrCode')
                ->where('No_Po_Sampel', $noSampel)
                ->count();

            return response()->json([
                'success' => true,
                'result'  => [
                    'sampel'           => $sampel,
                    'jumlah_uji_aktif' => $jumlahUji,
                    'total_sub_sampel' => $jumlahSub,
                    'sudah_dibatalkan' => $sampel->Status === 'Y',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Terjadi kesalahan saat mengambil data.'),
            ], 500);
        }
    }

    /**
     * Batalkan registrasi sampel dengan mem-flag Status = 'Y' di PO Sampel.
     */
    public function batalkanRegistrasi(Request $request)
    {
        $request->validate([
            'no_sampel' => 'required|string|max:50',
            'alasan'    => 'nullable|string|max:255',
            'ikut_uji'  => 'nullable|boolean',
        ]);

        $noSampel = trim($request->input('no_sampel'));
        $alasan   = $request->input('alasan');
        $ikutUji  = (bool) $request->input('ikut_uji', false);

        DB::beginTransaction();

        try {
            $sampel = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $noSampel)
                ->first();

            if (! $sampel) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Nomor sampel {$noSampel} tidak ditemukan.",
                ], 404);
            }

            // Semua baris dengan No_Sampel sama ikut dibatalkan, karena analisa
            // khusus tersimpan sebagai baris terpisah dengan nomor sampel sama.
            $barisAktif = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $noSampel)
                ->whereNull('Status')
                ->get();

            if ($barisAktif->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Sampel {$noSampel} sudah berstatus dibatalkan.",
                ], 422);
            }

            // Keterangan diperbarui per baris supaya catatan asli tiap baris
            // tidak saling menimpa.
            foreach ($barisAktif as $baris) {
                $update = ['Status' => 'Y'];

                if ($alasan) {
                    $update['Keterangan'] = trim(($baris->Keterangan ?? '') . ' [BATAL BYPASS: ' . $alasan . ']');
                }

                DB::table('N_EMI_LAB_PO_Sampel')
                    ->where('id', $baris->id)
                    ->update($update);
            }

            $terpengaruh = $barisAktif->count();

            $ujiDibatalkan = 0;
            if ($ikutUji) {
                $ujiDibatalkan = DB::table('N_EMI_LAB_Uji_Sampel')
                    ->where('No_Po_Sampel', $noSampel)
                    ->whereNull('Status')
                    ->update(['Status' => 'Y']);
            }

            DB::commit();

            $this->catatJejak('batalkan registrasi sampel', [
                'no_sampel'      => $noSampel,
                'baris_po'       => $terpengaruh,
                'uji_dibatalkan' => $ujiDibatalkan,
                'alasan'         => $alasan,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Registrasi sampel {$noSampel} berhasil dibatalkan.",
                'result'  => [
                    'baris_po_dibatalkan' => $terpengaruh,
                    'uji_dibatalkan'      => $ujiDibatalkan,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->catatJejak('gagal batalkan registrasi', [
                'no_sampel' => $noSampel,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Gagal membatalkan registrasi.'),
            ], 500);
        }
    }

    /* =====================================================================
     |  FITUR 3 - BATALKAN UJI SAMPEL
     ===================================================================== */

    /**
     * Cari sampel beserta daftar jenis analisa yang sudah diuji, dikelompokkan
     * per jenis analisa supaya bisa dipilih mana yang mau dibatalkan.
     */
    public function cariUjiSampel(Request $request)
    {
        $request->validate(['no_sampel' => 'required|string|max:50']);

        $noSampel = trim($request->input('no_sampel'));

        try {
            $sampel = DB::table('N_EMI_LAB_PO_Sampel as po')
                ->leftJoin('N_EMI_LAB_Users as u', 'po.Id_User', '=', 'u.UserId')
                ->leftJoin('EMI_Master_Mesin as m', 'po.Id_Mesin', '=', 'm.Id_Master_Mesin')
                ->leftJoin('N_EMI_View_Barang as b', 'po.Kode_Barang', '=', 'b.Kode_Barang')
                ->where('po.No_Sampel', $noSampel)
                ->select(
                    'po.id',
                    'po.No_Sampel',
                    'po.No_Po',
                    'po.No_Split_Po',
                    'po.No_Batch',
                    'po.Kode_Barang',
                    'po.Keterangan',
                    'po.Status',
                    'po.Flag_Selesai',
                    'po.Tanggal',
                    'po.Jam',
                    'po.Id_User',
                    'u.Nama as Nama_User',
                    'm.Nama_Mesin',
                    'b.Nama as Nama_Barang'
                )
                ->first();

            if (! $sampel) {
                return response()->json([
                    'success' => false,
                    'message' => "Nomor sampel {$noSampel} tidak ditemukan.",
                ], 404);
            }

            // Rekap uji per jenis analisa: yang aktif (Status NULL) bisa dibatalkan.
            $analisa = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->leftJoin('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->leftJoin('N_EMI_LAB_Users as u', 'us.Id_User', '=', 'u.UserId')
                ->where('us.No_Po_Sampel', $noSampel)
                ->groupBy(
                    'us.Id_Jenis_Analisa',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa'
                )
                ->select(
                    'us.Id_Jenis_Analisa',
                    'ja.Kode_Analisa',
                    'ja.Jenis_Analisa',
                    DB::raw('COUNT(*) as total_baris'),
                    DB::raw("SUM(CASE WHEN us.Status IS NULL THEN 1 ELSE 0 END) as total_aktif"),
                    DB::raw("SUM(CASE WHEN us.Status = 'Y' THEN 1 ELSE 0 END) as total_batal"),
                    DB::raw('MIN(us.Tanggal) as tanggal_awal'),
                    DB::raw('MAX(us.Tanggal) as tanggal_akhir'),
                    DB::raw('MAX(us.Jam) as jam_terakhir'),
                    DB::raw('MAX(us.Id_User) as id_user_input')
                )
                ->orderBy('ja.Jenis_Analisa')
                ->get();

            // Lengkapi nama penginput per jenis analisa.
            $idUsers = $analisa->pluck('id_user_input')->filter()->unique()->all();
            $namaMap = [];
            if (! empty($idUsers)) {
                $namaMap = DB::table('N_EMI_LAB_Users')
                    ->whereIn('UserId', $idUsers)
                    ->pluck('Nama', 'UserId')
                    ->toArray();
            }

            $analisa = $analisa->map(function ($row) use ($namaMap) {
                $row->Nama_User_Input = $namaMap[$row->id_user_input] ?? $row->id_user_input;
                $row->total_aktif     = (int) $row->total_aktif;
                $row->total_batal     = (int) $row->total_batal;
                $row->total_baris     = (int) $row->total_baris;

                return $row;
            });

            return response()->json([
                'success' => true,
                'result'  => [
                    'sampel'            => $sampel,
                    'analisa'           => $analisa,
                    'total_jenis'       => $analisa->count(),
                    'total_uji_aktif'   => (int) $analisa->sum('total_aktif'),
                    'total_uji_batal'   => (int) $analisa->sum('total_batal'),
                ],
            ]);
        } catch (\Exception $e) {
            $this->catatJejak('gagal cari uji sampel', [
                'no_sampel' => $noSampel,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Terjadi kesalahan saat mengambil data.'),
            ], 500);
        }
    }

    /**
     * Batalkan uji sampel untuk jenis analisa terpilih (atau semua).
     *
     * Pembatalan bersifat soft: baris uji di-flag Status = 'Y' sehingga jejak
     * lama tetap tersimpan, sementara Flag_Selesai pada PO Sampel direset ke
     * NULL supaya sampel bisa diinput ulang lewat registrasi normal.
     */
    public function batalkanUjiSampel(Request $request)
    {
        $request->validate([
            'no_sampel'    => 'required|string|max:50',
            'semua'        => 'nullable|boolean',
            'id_analisa'   => 'nullable|array',
            'id_analisa.*' => 'integer',
            'alasan'       => 'nullable|string|max:255',
        ]);

        $noSampel  = trim($request->input('no_sampel'));
        $semua     = (bool) $request->input('semua', false);
        $idAnalisa = array_values(array_unique(array_map('intval', (array) $request->input('id_analisa', []))));
        $alasan    = $request->input('alasan');

        if (! $semua && empty($idAnalisa)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu jenis analisa, atau centang "Semua Analisa".',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $sampel = DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $noSampel)
                ->first();

            if (! $sampel) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Nomor sampel {$noSampel} tidak ditemukan.",
                ], 404);
            }

            $query = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $noSampel)
                ->whereNull('Status');

            if (! $semua) {
                $query->whereIn('Id_Jenis_Analisa', $idAnalisa);
            }

            // Simpan nomor faktur terdampak untuk ditampilkan sebagai bukti.
            $terdampak = (clone $query)
                ->select('No_Faktur', 'Id_Jenis_Analisa')
                ->get();

            if ($terdampak->isEmpty()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada uji aktif yang cocok untuk dibatalkan.',
                ], 422);
            }

            $jumlahDibatalkan = $query->update(['Status' => 'Y']);

            $sisaAktif = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $noSampel)
                ->whereNull('Status')
                ->count();

            // Reset penanda selesai supaya sampel bisa diuji ulang. Hanya baris
            // yang benar-benar ditandai selesai yang disentuh.
            DB::table('N_EMI_LAB_PO_Sampel')
                ->where('No_Sampel', $noSampel)
                ->where('Flag_Selesai', 'Y')
                ->update(['Flag_Selesai' => null]);

            DB::commit();

            $this->catatJejak('batalkan uji sampel', [
                'no_sampel'  => $noSampel,
                'semua'      => $semua,
                'id_analisa' => $idAnalisa,
                'jumlah'     => $jumlahDibatalkan,
                'alasan'     => $alasan,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil membatalkan {$jumlahDibatalkan} baris uji pada sampel {$noSampel}.",
                'result'  => [
                    'jumlah_dibatalkan' => $jumlahDibatalkan,
                    'faktur_terdampak'  => $terdampak->pluck('No_Faktur')->unique()->values(),
                    'sisa_uji_aktif'    => $sisaAktif,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->catatJejak('gagal batalkan uji sampel', [
                'no_sampel' => $noSampel,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Gagal membatalkan uji sampel.'),
            ], 500);
        }
    }

    /* =====================================================================
     |  FITUR 4 - ATUR TEMPLATE PRINTER
     ===================================================================== */

    /**
     * Daftar template untuk dropdown pemilihan.
     */
    public function daftarTemplatePrinter()
    {
        try {
            $data = DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->select(
                    'Id_Master_Printer_Templates',
                    'Nama_Template',
                    'Lebar_Label',
                    'Tinggi_Label',
                    'Gap_Antar_Label',
                    'Direction',
                    'Flag_Aktif'
                )
                ->orderByDesc('Id_Master_Printer_Templates')
                ->get();

            return response()->json([
                'success' => true,
                'result'  => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Terjadi kesalahan saat mengambil data.'),
            ], 500);
        }
    }

    /**
     * Detail satu template: ukuran label + seluruh item TEXT/QRCODE.
     */
    public function detailTemplatePrinter(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        try {
            $master = DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->where('Id_Master_Printer_Templates', (int) $request->input('id'))
                ->first();

            if (! $master) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template tidak ditemukan.',
                ], 404);
            }

            $items = DB::table('N_EMI_LAB_Printer_Template_Items')
                ->where('Id_Master_Printer_Templates', $master->Id_Master_Printer_Templates)
                ->orderBy('Id_Printer_Template_Items')
                ->get();

            return response()->json([
                'success' => true,
                'result'  => [
                    'master' => $master,
                    'items'  => $items,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Terjadi kesalahan saat mengambil data.'),
            ], 500);
        }
    }

    /**
     * Simpan ukuran label dan seluruh item template.
     *
     * Item dikirim utuh: baris lama dihapus lalu ditulis ulang, supaya urutan
     * dan penghapusan item di UI langsung tercermin di database.
     */
    public function simpanTemplatePrinter(Request $request)
    {
        $request->validate([
            'id'                => 'required|integer',
            'Nama_Template'     => 'required|string|max:255',
            'Lebar_Label'       => 'required|integer|min:1|max:500',
            'Tinggi_Label'      => 'required|integer|min:1|max:500',
            'Gap_Antar_Label'   => 'nullable|integer|min:0|max:100',
            'Direction'         => 'required|integer|in:0,1',
            'items'              => 'nullable|array',
            'items.*.Jenis'      => 'required|string|in:TEXT,QRCODE',
            'items.*.Posisi_X'   => 'required|integer|min:0|max:9999',
            'items.*.Posisi_Y'   => 'required|integer|min:0|max:9999',
            'items.*.Isi_Konten' => 'nullable|string|max:500',
            // Nilai berikut ikut disisipkan mentah ke perintah TSPL, jadi
            // dibatasi ketat ke himpunan yang dikenal printer.
            'items.*.Rotation'   => 'nullable|integer|in:0,90,180,270',
            'items.*.Scale_X'    => 'nullable|integer|min:1|max:10',
            'items.*.Scale_Y'    => 'nullable|integer|min:1|max:10',
            // Font TSC bisa "1".."8" atau nama font internal, tetapi tetap
            // dibatasi alfanumerik karena ikut disisipkan ke perintah TSPL.
            'items.*.Font'       => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9._]+$/'],
            'items.*.Qr_Ecc'     => 'nullable|string|in:L,M,Q,H',
            'items.*.Qr_Size'    => 'nullable|integer|min:1|max:20',
            // Nilai asli di database berbentuk "M2,S7" (model + mask TSPL),
            // jadi polanya dilonggarkan tapi tetap dibatasi karakter aman
            // karena nilai ini disisipkan mentah ke perintah TSPL.
            'items.*.Qr_Model'   => ['nullable', 'string', 'max:20', 'regex:/^M[12](,S\d{1,2})?$/'],
        ]);

        $id    = (int) $request->input('id');
        $items = (array) $request->input('items', []);

        DB::beginTransaction();

        try {
            $master = DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->where('Id_Master_Printer_Templates', $id)
                ->first();

            if (! $master) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Template tidak ditemukan.',
                ], 404);
            }

            $waktu = $this->waktuServer();

            DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->where('Id_Master_Printer_Templates', $id)
                ->update([
                    'Nama_Template'   => $request->input('Nama_Template'),
                    'Lebar_Label'     => (int) $request->input('Lebar_Label'),
                    'Tinggi_Label'    => (int) $request->input('Tinggi_Label'),
                    'Gap_Antar_Label' => (int) $request->input('Gap_Antar_Label', 0),
                    'Direction'       => (int) $request->input('Direction'),
                ]);

            // Tulis ulang item agar hasil edit di UI persis sama dengan di DB.
            DB::table('N_EMI_LAB_Printer_Template_Items')
                ->where('Id_Master_Printer_Templates', $id)
                ->delete();

            $payload = [];
            foreach ($items as $item) {
                $isQr = ($item['Jenis'] ?? 'TEXT') === 'QRCODE';

                $payload[] = [
                    'Id_Master_Printer_Templates' => $id,
                    'Jenis'       => $item['Jenis'],
                    'Lebar_Label' => (int) ($item['Lebar_Label'] ?? 0),
                    'Posisi_X'    => (int) ($item['Posisi_X'] ?? 0),
                    'Posisi_Y'    => (int) ($item['Posisi_Y'] ?? 0),
                    'Font'        => $isQr ? null : ($item['Font'] ?? '1'),
                    'Rotation'    => (int) ($item['Rotation'] ?? 0),
                    // Skala hanya berlaku untuk TEXT; pada QRCODE dibiarkan
                    // NULL agar sama dengan data yang ditulis modul registrasi.
                    'Scale_X'     => $isQr ? null : (int) ($item['Scale_X'] ?? 1),
                    'Scale_Y'     => $isQr ? null : (int) ($item['Scale_Y'] ?? 1),
                    'Isi_Konten'  => $item['Isi_Konten'] ?? '',
                    'Qr_Ecc'      => $isQr ? ($item['Qr_Ecc'] ?? 'H') : null,
                    'Qr_Size'     => $isQr ? ($item['Qr_Size'] ?? 6) : null,
                    'Qr_Model'    => $isQr ? ($item['Qr_Model'] ?? 'M2,S7') : null,
                    'Tanggal'     => $waktu['tanggal'],
                    'Jam'         => $waktu['jam'],
                    'Id_User'     => $master->Id_User ?? 'SYSTEM',
                    'Flag_Aktif'  => 'Y',
                ];
            }

            if (! empty($payload)) {
                DB::table('N_EMI_LAB_Printer_Template_Items')->insert($payload);
            }

            DB::commit();

            $this->catatJejak('ubah template printer', [
                'id_template' => $id,
                'nama'        => $request->input('Nama_Template'),
                'jumlah_item' => count($payload),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template printer berhasil disimpan.',
                'result'  => ['jumlah_item' => count($payload)],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->catatJejak('gagal simpan template printer', [
                'id_template' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Gagal menyimpan template printer.'),
            ], 500);
        }
    }

    /**
     * Bangun TSPL uji coba dari template, dikembalikan sebagai print job yang
     * dikirim browser ke agen printer (URL_CLIENT) - sama seperti modul lain.
     */
    public function testPrintTemplate(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        try {
            $master = DB::table('N_EMI_LAB_Master_Printer_Templates')
                ->where('Id_Master_Printer_Templates', (int) $request->input('id'))
                ->first();

            if (! $master) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template tidak ditemukan.',
                ], 404);
            }

            $items = DB::table('N_EMI_LAB_Printer_Template_Items')
                ->where('Id_Master_Printer_Templates', $master->Id_Master_Printer_Templates)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Printer_Template_Items')
                ->get();

            if ($items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template belum punya item untuk dicetak.',
                ], 422);
            }

            $tspl = $this->bangunTspl($items, $this->contohDataLabel());

            return response()->json([
                'success'     => true,
                'message'     => 'Print job uji coba siap dikirim.',
                'printer_url' => rtrim((string) env('URL_CLIENT'), '/'),
                'print_jobs'  => [[
                    'width'     => (int) $master->Lebar_Label,
                    'height'    => (int) $master->Tinggi_Label,
                    'gap'       => (int) $master->Gap_Antar_Label,
                    'direction' => (int) ($master->Direction ?? 1),
                    'data'      => $tspl,
                ]],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->pesanAman($e, 'Gagal menyiapkan test print.'),
            ], 500);
        }
    }

    /**
     * Data contoh untuk placeholder saat pratinjau / test print.
     */
    private function contohDataLabel(): array
    {
        return [
            '{nama_sampel}'   => 'CONTOH SAMPEL',
            '{qrData}'        => 'FS' . date('my') . '-0001',
            '{no_split}'      => 'SP-000000',
            '{batch}'         => 'BATCH-01',
            '{tanggal}'       => date('d M Y'),
            '{namaMesin}'     => 'MESIN UJI',
            '{jenis_analisa}' => 'TEST PRINT',
        ];
    }

    /**
     * Susun perintah TSPL dari item template - format identik dengan modul
     * registrasi supaya hasil test print sama persis dengan cetakan asli.
     */
    private function bangunTspl($items, array $replacements): string
    {
        $tspl = '';

        foreach ($items as $item) {
            $konten = str_replace(
                array_keys($replacements),
                array_values($replacements),
                (string) $item->Isi_Konten
            );

            // TSPL memakai backslash sebagai escape di dalam string berkutip,
            // jadi kutip ganda pada konten harus dilolosi agar perintah utuh.
            $konten = str_replace(['\\', '"'], ['\\\\', '\\"'], $konten);

            if ($item->Jenis === 'TEXT') {
                $tspl .= "TEXT {$item->Posisi_X},{$item->Posisi_Y},\"{$item->Font}\",{$item->Rotation},{$item->Scale_X},{$item->Scale_Y},\"{$konten}\"\r\n";
            } elseif ($item->Jenis === 'QRCODE') {
                $tspl .= "QRCODE {$item->Posisi_X},{$item->Posisi_Y},{$item->Qr_Ecc},{$item->Qr_Size},A,{$item->Rotation},{$item->Qr_Model},\"{$konten}\"\r\n";
            }
        }

        return $tspl;
    }
}
