<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

class PalatabilitasController extends Controller
{
    private function encode($id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    private function decode(string $id): ?int
    {
        $decoded = Hashids::connection('custom')->decode($id);
        return $decoded[0] ?? null;
    }

    private function getPltAnalisaFull(string $noPoSampel): \Illuminate\Support\Collection
    {
        $po = DB::table('N_EMI_LAB_PO_Sampel')
            ->where('No_Sampel', $noPoSampel)
            ->select('Kode_Barang', 'Id_Mesin')
            ->first();

        if (!$po) return collect();

        return DB::table('N_EMI_LAB_Barang_Analisa as ba')
            ->join('N_EMI_LAB_Jenis_Analisa as ja', 'ba.Id_Jenis_Analisa', '=', 'ja.id')
            ->where('ba.Kode_Barang', $po->Kode_Barang)
            ->where('ba.Id_Master_Mesin', $po->Id_Mesin)
            ->where('ba.Flag_Aktif', 'Y')
            ->where('ba.Kode_Role', 'LAB')
            ->where('ja.Kode_Aktivitas_Lab', 'PLT')
            ->select('ja.id', 'ja.Jenis_Analisa', 'ja.Kode_Analisa')
            ->get()
            ->unique('id')
            ->map(fn($ja) => [
                'id_raw'           => $ja->id,
                'id_jenis_analisa' => $this->encode($ja->id),
                'Jenis_Analisa'    => $ja->Jenis_Analisa,
                'Kode_Analisa'     => $ja->Kode_Analisa,
            ])
            ->values();
    }

    private function getPltAnalisaIds(string $noPoSampel): array
    {
        return $this->getPltAnalisaFull($noPoSampel)
            ->pluck('id_raw')->filter()->values()->all();
    }

    /* ─────────────────────────────────────────────────────────── */
    /* GET /api/v1/palatabilitas/session                            */
    /* ─────────────────────────────────────────────────────────── */

    public function getSession(Request $request)
    {
        try {
            $noPoSampel = $request->query('no_po_sampel');
            $pltAnalisa = $this->getPltAnalisaFull($noPoSampel);

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json([
                    'success'     => true,
                    'status'      => 200,
                    'result'      => null,
                    'butuh_plt'   => $pltAnalisa->isNotEmpty(),
                    'plt_analisa' => $pltAnalisa->map(fn($a) => collect($a)->except('id_raw')),
                ]);
            }

            $pltAnalisaIds = $pltAnalisa->pluck('id_raw')->filter()->values()->all();

            $pembanding = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                ->where('Id_Session', $session->Id_Session)
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Pembanding', 'Urutan', 'Nama_Pembanding', 'Kode_Barang_Pembanding')
                ->orderBy('Urutan')
                ->orderBy('Id_Pembanding')
                ->get()
                ->map(fn($p) => [
                    'id_pembanding'          => $this->encode($p->Id_Pembanding),
                    'urutan'                 => $p->Urutan,
                    'nama_pembanding'        => $p->Nama_Pembanding,
                    'kode_barang_pembanding' => $p->Kode_Barang_Pembanding,
                ]);

            $sudahFinal = !empty($pltAnalisaIds)
                ? DB::table('N_EMI_LAB_Uji_Sampel')
                    ->where('No_Po_Sampel', $noPoSampel)
                    ->where('Id_Session', $session->Id_Session)
                    ->whereIn('Id_Jenis_Analisa', $pltAnalisaIds)
                    ->whereNull('Flag_Resampling')
                    ->count()
                : 0;

            $totalSlot = count($pltAnalisaIds) * $pembanding->count();

            return response()->json([
                'success'     => true,
                'status'      => 200,
                'result'      => [
                    'id_session'         => $this->encode($session->Id_Session),
                    'id_session_raw'     => $session->Id_Session,
                    'no_po_sampel'       => $session->No_Po_Sampel,
                    'kode_aktivitas_lab' => $session->Kode_Aktivitas_Lab,
                    'status_session'     => $session->Status_Session,
                    'pembanding'         => $pembanding->values(),
                    'sudah_final'        => $sudahFinal,
                    'total_slot'         => $totalSlot,
                    'plt_analisa'        => $pltAnalisa->map(fn($a) => collect($a)->except('id_raw')),
                ],
                'butuh_plt'   => $pltAnalisa->isNotEmpty(),
                'plt_analisa' => $pltAnalisa->map(fn($a) => collect($a)->except('id_raw')),
            ]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* POST /api/v1/palatabilitas/session                           */
    /* ─────────────────────────────────────────────────────────── */

    public function createSession(Request $request)
    {
        try {
            $noPoSampel = $request->input('no_po_sampel');

            $exists = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->exists();

            if ($exists) {
                return response()->json(['success' => false, 'status' => 409, 'message' => 'Sesi PLT sudah ada untuk sampel ini.'], 409);
            }

            $idSession = DB::table('N_EMI_LAB_Palatabilitas_Session')->insertGetId([
                'No_Po_Sampel'       => $noPoSampel,
                'Kode_Perusahaan'    => '001',
                'Kode_Aktivitas_Lab' => 'PLT',
                'Status_Session'     => 'D',
                'Tanggal_Buat'       => DB::raw('dbo.Get_Date_Time()'),
                'Jam_Buat'           => date('H:i:s'),
                'Id_User_Buat'       => Auth::user()->UserId,
                'Kode_Role'          => 'LAB',
            ], 'Id_Session');

            return response()->json([
                'success'    => true,
                'status'     => 201,
                'message'    => 'Sesi PLT berhasil dibuat.',
                'id_session' => $this->encode($idSession),
            ], 201);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* POST /api/v1/palatabilitas/pembanding                        */
    /* ─────────────────────────────────────────────────────────── */

    public function addPembanding(Request $request)
    {
        try {
            $noPoSampel     = $request->input('no_po_sampel');
            $namaPembanding = trim($request->input('nama_pembanding', ''));

            if ($namaPembanding === '') {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Nama pembanding tidak boleh kosong.'], 422);
            }

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Sesi PLT tidak ditemukan.'], 404);
            }

            if ($session->Status_Session === 'F') {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Sesi sudah difinalisasi, tidak dapat menambah pembanding.'], 422);
            }

            $urutan = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                ->where('Id_Session', $session->Id_Session)
                ->count() + 1;

            $idPembanding = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')->insertGetId([
                'Id_Session'      => $session->Id_Session,
                'Kode_Perusahaan' => '001',
                'Urutan'          => $urutan,
                'Nama_Pembanding' => $namaPembanding,
                'Flag_Aktif'      => 'Y',
                'Tanggal'         => DB::raw('dbo.Get_Date_Time()'),
                'Jam'             => date('H:i:s'),
                'Id_User'         => Auth::user()->UserId,
                'Kode_Role'       => 'LAB',
            ], 'Id_Pembanding');

            return response()->json([
                'success'       => true,
                'status'        => 201,
                'message'       => 'Pembanding berhasil ditambahkan.',
                'id_pembanding' => $this->encode($idPembanding),
            ], 201);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* DELETE /api/v1/palatabilitas/pembanding/{id}                 */
    /* ─────────────────────────────────────────────────────────── */

    public function removePembanding(string $id)
    {
        try {
            $idRaw = $this->decode($id);
            if (!$idRaw) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Pembanding tidak ditemukan.'], 404);
            }

            $pembanding = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                ->where('Id_Pembanding', $idRaw)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (!$pembanding) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Pembanding tidak ditemukan.'], 404);
            }

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('Id_Session', $pembanding->Id_Session)
                ->first();

            if ($session && $session->Status_Session === 'F') {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Sesi sudah difinalisasi.'], 422);
            }

            DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                ->where('Id_Pembanding', $idRaw)
                ->update(['Flag_Aktif' => 'T']);

            DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                ->where('Id_Pembanding', $idRaw)
                ->whereNull('Status')
                ->delete();

            return response()->json(['success' => true, 'status' => 200, 'message' => 'Pembanding berhasil dihapus.']);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* GET /api/v1/palatabilitas/sementara                          */
    /* ─────────────────────────────────────────────────────────── */

    public function getSementara(Request $request)
    {
        try {
            $noPoSampel = $request->query('no_po_sampel');

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json(['success' => true, 'status' => 200, 'result' => []]);
            }

            $rows = DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                ->where('Id_Session', $session->Id_Session)
                ->whereNull('Status')
                ->select('No_Urut', 'Id_Pembanding', 'Id_Jenis_Analisa', 'Hasil', 'Nilai_Hasil_String')
                ->get()
                ->map(fn($r) => [
                    'id_sementara'       => $this->encode($r->No_Urut),
                    'id_pembanding'      => $this->encode($r->Id_Pembanding),
                    'id_jenis_analisa'   => $this->encode($r->Id_Jenis_Analisa),
                    'hasil'              => $r->Hasil,
                    'nilai_hasil_string' => $r->Nilai_Hasil_String,
                ]);

            return response()->json(['success' => true, 'status' => 200, 'result' => $rows]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* POST /api/v1/palatabilitas/sementara                         */
    /* ─────────────────────────────────────────────────────────── */

    public function saveSementara(Request $request)
    {
        try {
            $noPoSampel      = $request->input('no_po_sampel');
            $idPembandingEnc = $request->input('id_pembanding');
            $idAnalisaEnc    = $request->input('id_jenis_analisa');
            $hasil           = $request->input('hasil');
            $nilaiString     = $request->input('nilai_hasil_string');

            $idPembanding = $this->decode($idPembandingEnc);
            $idAnalisa    = $this->decode($idAnalisaEnc);

            if (!$idPembanding || !$idAnalisa) {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'ID tidak valid.'], 422);
            }

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Sesi tidak ditemukan.'], 404);
            }

            if ($session->Status_Session === 'F') {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Sesi sudah difinalisasi.'], 422);
            }

            $existing = DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                ->where('Id_Session', $session->Id_Session)
                ->where('Id_Pembanding', $idPembanding)
                ->where('Id_Jenis_Analisa', $idAnalisa)
                ->whereNull('Status')
                ->first();

            $commonPayload = [
                'Hasil'              => $hasil !== null ? (float) $hasil : null,
                'Nilai_Hasil_String' => $nilaiString ?? null,
                'Tanggal'            => DB::raw('dbo.Get_Date_Time()'),
                'Jam'                => date('H:i:s'),
                'Id_User'            => Auth::user()->UserId,
                'Kode_Role'          => 'LAB',
            ];

            if ($existing) {
                DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                    ->where('No_Urut', $existing->No_Urut)
                    ->update($commonPayload);
                $noUrut = $existing->No_Urut;
            } else {
                $noUrut = DB::table('N_EMI_LAB_Palatabilitas_Sementara')->insertGetId(array_merge($commonPayload, [
                    'Id_Session'      => $session->Id_Session,
                    'Id_Pembanding'   => $idPembanding,
                    'Id_Jenis_Analisa'=> $idAnalisa,
                    'No_Po_Sampel'    => $noPoSampel,
                    'Kode_Perusahaan' => '001',
                ]), 'No_Urut');
            }

            return response()->json([
                'success'      => true,
                'status'       => 200,
                'message'      => 'Data draft berhasil disimpan.',
                'id_sementara' => $this->encode($noUrut),
            ]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* DELETE /api/v1/palatabilitas/sementara/{id}                  */
    /* ─────────────────────────────────────────────────────────── */

    public function deleteSementara(string $id)
    {
        try {
            $idRaw = $this->decode($id);
            if (!$idRaw) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Data tidak ditemukan.'], 404);
            }

            DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                ->where('No_Urut', $idRaw)
                ->whereNull('Status')
                ->delete();

            return response()->json(['success' => true, 'status' => 200, 'message' => 'Data draft dihapus.']);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* POST /api/v1/palatabilitas/finalisasi                        */
    /* ─────────────────────────────────────────────────────────── */

    public function finalisasi(Request $request)
    {
        DB::beginTransaction();
        try {
            $noPoSampel = $request->input('no_po_sampel');
            $userId     = Auth::user()->UserId;

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Sesi PLT tidak ditemukan.'], 404);
            }

            if ($session->Status_Session === 'F') {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Sesi sudah difinalisasi sebelumnya.'], 422);
            }

            $pembandingList = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                ->where('Id_Session', $session->Id_Session)
                ->where('Flag_Aktif', 'Y')
                ->get();

            if ($pembandingList->isEmpty()) {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Belum ada produk pembanding. Tambahkan minimal satu pembanding terlebih dahulu.'], 422);
            }

            $pltAnalisaIds = $this->getPltAnalisaIds($noPoSampel);

            if (empty($pltAnalisaIds)) {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Tidak ada analisa PLT terkonfigurasi untuk sampel ini.'], 422);
            }

            $sementaraList = DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                ->where('Id_Session', $session->Id_Session)
                ->whereNull('Status')
                ->get()
                ->keyBy(fn($r) => $r->Id_Pembanding . '|' . $r->Id_Jenis_Analisa);

            $dt = DB::raw('dbo.Get_Date_Time()');

            $currentMonth = date('m');
            $currentYear  = date('y');
            $prefix       = 'FUS' . $currentMonth . $currentYear;
            $prefixLen    = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLen])
                ->value('max_number') ?? 0;

            $insertedCount = 0;
            $sementaraIds  = [];

            foreach ($pembandingList as $pembanding) {
                foreach ($pltAnalisaIds as $idAnalisa) {
                    $key      = $pembanding->Id_Pembanding . '|' . $idAnalisa;
                    $draftRow = $sementaraList->get($key);
                    $hasilNum = $draftRow ? $draftRow->Hasil : null;
                    $hasilStr = $draftRow ? $draftRow->Nilai_Hasil_String : null;

                    $alreadyIn = DB::table('N_EMI_LAB_Uji_Sampel')
                        ->where('No_Po_Sampel', $noPoSampel)
                        ->where('Id_Session', $session->Id_Session)
                        ->where('Id_Pembanding', $pembanding->Id_Pembanding)
                        ->where('Id_Jenis_Analisa', $idAnalisa)
                        ->whereNull('Flag_Resampling')
                        ->exists();

                    if ($alreadyIn) continue;

                    $lastNumber++;
                    $noFaktur = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                    DB::table('N_EMI_LAB_Uji_Sampel')->insert([
                        'No_Faktur'               => $noFaktur,
                        'Kode_Perusahaan'         => '001',
                        'Id_Jenis_Analisa'        => $idAnalisa,
                        'Hasil'                   => $hasilNum,
                        'Nilai_Hasil_String'      => $hasilStr,
                        'Flag_Perhitungan'        => null,
                        'Flag_String'             => $hasilStr !== null ? 'Y' : null,
                        'Status'                  => null,
                        'Flag_Selesai'            => 'Y',
                        'Tanggal'                 => $dt,
                        'Jam'                     => date('H:i:s'),
                        'Id_User'                 => $userId,
                        'No_Po_Sampel'            => $noPoSampel,
                        'Status_Keputusan_Sampel' => 'menunggu',
                        'Tahapan_Ke'              => 1,
                        'Id_Session'              => $session->Id_Session,
                        'Id_Pembanding'           => $pembanding->Id_Pembanding,
                    ]);

                    $insertedCount++;

                    if ($draftRow) {
                        $sementaraIds[] = $draftRow->No_Urut;
                    }
                }
            }

            if (!empty($sementaraIds)) {
                foreach (array_chunk($sementaraIds, 500) as $chunk) {
                    DB::table('N_EMI_LAB_Palatabilitas_Sementara')
                        ->whereIn('No_Urut', $chunk)
                        ->update(['Status' => 'F']);
                }
            }

            DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('Id_Session', $session->Id_Session)
                ->update([
                    'Status_Session' => 'F',
                    'Tanggal_Final'  => $dt,
                    'Jam_Final'      => date('H:i:s'),
                    'Id_User_Final'  => $userId,
                ]);

            DB::commit();

            return response()->json([
                'success'        => true,
                'status'         => 200,
                'message'        => 'Palatabilitas berhasil difinalisasi. ' . $insertedCount . ' data tersimpan ke Uji Sampel.',
                'inserted_count' => $insertedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* GET /api/v1/palatabilitas/kelengkapan                        */
    /* ─────────────────────────────────────────────────────────── */

    public function getKelengkapan(Request $request)
    {
        try {
            $noPoSampel    = $request->query('no_po_sampel');
            $pltAnalisaIds = $this->getPltAnalisaIds($noPoSampel);

            if (empty($pltAnalisaIds)) {
                return response()->json([
                    'success' => true,
                    'status'  => 200,
                    'result'  => ['ada_plt' => false, 'lengkap' => true, 'pesan' => null],
                ]);
            }

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => true,
                    'status'  => 200,
                    'result'  => [
                        'ada_plt' => true,
                        'lengkap' => false,
                        'pesan'   => 'Belum ada sesi PLT. Buka halaman palatabilitas untuk membuat sesi.',
                    ],
                ]);
            }

            $pembandingCount = DB::table('N_EMI_LAB_Palatabilitas_Pembanding')
                ->where('Id_Session', $session->Id_Session)
                ->where('Flag_Aktif', 'Y')
                ->count();

            $totalSlot  = count($pltAnalisaIds) * $pembandingCount;
            $sudahFinal = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Id_Session', $session->Id_Session)
                ->whereIn('Id_Jenis_Analisa', $pltAnalisaIds)
                ->whereNull('Flag_Resampling')
                ->count();

            $lengkap = $session->Status_Session === 'F' && ($totalSlot === 0 || $sudahFinal >= $totalSlot);

            return response()->json([
                'success' => true,
                'status'  => 200,
                'result'  => [
                    'ada_plt'           => true,
                    'lengkap'           => $lengkap,
                    'status_session'    => $session->Status_Session,
                    'jumlah_pembanding' => $pembandingCount,
                    'total_slot'        => $totalSlot,
                    'sudah_final'       => $sudahFinal,
                    'pesan'             => $lengkap ? null : 'Data PLT belum lengkap. ' . $sudahFinal . '/' . $totalSlot . ' slot terisi.',
                ],
            ]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* GET /api/v1/palatabilitas/hasil                              */
    /* ─────────────────────────────────────────────────────────── */

    public function getHasil(Request $request)
    {
        try {
            $noPoSampel = $request->query('no_po_sampel');

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json(['success' => true, 'status' => 200, 'result' => []]);
            }

            $rows = DB::table('N_EMI_LAB_Uji_Sampel as us')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'us.Id_Jenis_Analisa', '=', 'ja.id')
                ->join('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'us.Id_Pembanding', '=', 'pb.Id_Pembanding')
                ->where('us.No_Po_Sampel', $noPoSampel)
                ->where('us.Id_Session', $session->Id_Session)
                ->whereNull('us.Flag_Resampling')
                ->select(
                    'us.No_Faktur',
                    'us.Id_Jenis_Analisa',
                    'us.Id_Pembanding',
                    'us.Hasil',
                    'us.Nilai_Hasil_String',
                    'ja.Jenis_Analisa',
                    'ja.Kode_Analisa',
                    'pb.Nama_Pembanding'
                )
                ->get()
                ->map(fn($r) => [
                    'no_faktur'          => $r->No_Faktur,
                    'id_jenis_analisa'   => $this->encode($r->Id_Jenis_Analisa),
                    'id_pembanding'      => $this->encode($r->Id_Pembanding),
                    'hasil'              => $r->Hasil,
                    'nilai_hasil_string' => $r->Nilai_Hasil_String,
                    'jenis_analisa'      => $r->Jenis_Analisa,
                    'kode_analisa'       => $r->Kode_Analisa,
                    'nama_pembanding'    => $r->Nama_Pembanding,
                ]);

            return response()->json(['success' => true, 'status' => 200, 'result' => $rows]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* GET /api/v1/palatabilitas/resampling-pending                  */
    /* ─────────────────────────────────────────────────────────── */

    public function getResamplingPending(Request $request)
    {
        try {
            $noPoSampel = $request->query('no_po_sampel');

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json(['success' => true, 'status' => 200, 'result' => []]);
            }

            $pending = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log as rl')
                ->join('N_EMI_LAB_Palatabilitas_Pembanding as pb', 'rl.Id_Pembanding', '=', 'pb.Id_Pembanding')
                ->join('N_EMI_LAB_Jenis_Analisa as ja', 'rl.Id_Jenis_Analisa', '=', 'ja.id')
                ->where('rl.Id_Session', $session->Id_Session)
                ->whereNull('rl.Flag_Selesai_Resampling')
                ->select(
                    'rl.Id_Resampling',
                    'rl.Id_Pembanding',
                    'rl.Id_Jenis_Analisa',
                    'rl.Tahapan_Ke',
                    'pb.Nama_Pembanding',
                    'pb.Kode_Barang_Pembanding',
                    'pb.Urutan',
                    'ja.Jenis_Analisa',
                    'ja.Kode_Analisa'
                )
                ->orderBy('pb.Urutan')
                ->orderBy('rl.Id_Pembanding')
                ->get()
                ->map(fn($r) => [
                    'id_resampling'          => $this->encode($r->Id_Resampling),
                    'id_pembanding'          => $this->encode($r->Id_Pembanding),
                    'id_jenis_analisa'       => $this->encode($r->Id_Jenis_Analisa),
                    'tahapan_ke'             => $r->Tahapan_Ke,
                    'nama_pembanding'        => $r->Nama_Pembanding,
                    'kode_barang_pembanding' => $r->Kode_Barang_Pembanding,
                    'jenis_analisa'          => $r->Jenis_Analisa,
                    'kode_analisa'           => $r->Kode_Analisa,
                ]);

            return response()->json(['success' => true, 'status' => 200, 'result' => $pending]);

        } catch (\Exception $e) {
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server.'], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* POST /api/v1/palatabilitas/finalisasi-resampling             */
    /* ─────────────────────────────────────────────────────────── */

    public function finalisasiResampling(Request $request)
    {
        DB::beginTransaction();
        try {
            $noPoSampel = $request->input('no_po_sampel');
            $entries    = $request->input('entries', []);
            $userId     = Auth::user()->UserId;

            $session = DB::table('N_EMI_LAB_Palatabilitas_Session')
                ->where('No_Po_Sampel', $noPoSampel)
                ->where('Kode_Aktivitas_Lab', 'PLT')
                ->first();

            if (!$session) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Sesi PLT tidak ditemukan.'], 404);
            }

            if (empty($entries)) {
                return response()->json(['success' => false, 'status' => 422, 'message' => 'Tidak ada data resampling yang dikirim.'], 422);
            }

            $dt           = DB::raw('dbo.Get_Date_Time()');
            $currentMonth = date('m');
            $currentYear  = date('y');
            $prefix       = 'FUS' . $currentMonth . $currentYear;
            $prefixLen    = strlen($prefix);

            $lastNumber = DB::table('N_EMI_LAB_Uji_Sampel')
                ->where('No_Faktur', 'like', $prefix . '-%')
                ->selectRaw("MAX(CAST(SUBSTRING(No_Faktur, ? + 2, 10) AS INT)) as max_number", [$prefixLen])
                ->value('max_number') ?? 0;

            $insertedCount = 0;

            foreach ($entries as $entry) {
                $idResamplingRaw = $this->decode($entry['id_resampling'] ?? '');
                $idPembandingRaw = $this->decode($entry['id_pembanding'] ?? '');
                $idAnalisaRaw    = $this->decode($entry['id_jenis_analisa'] ?? '');

                if (!$idResamplingRaw || !$idPembandingRaw || !$idAnalisaRaw) continue;

                $log = DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                    ->where('Id_Resampling', $idResamplingRaw)
                    ->where('Id_Session', $session->Id_Session)
                    ->whereNull('Flag_Selesai_Resampling')
                    ->first();

                if (!$log) continue;

                $hasilNum = (isset($entry['hasil']) && $entry['hasil'] !== '' && $entry['hasil'] !== null)
                    ? (float) $entry['hasil'] : null;
                $hasilStr = (isset($entry['nilai_hasil_string']) && $entry['nilai_hasil_string'] !== '')
                    ? $entry['nilai_hasil_string'] : null;

                $lastNumber++;
                $noFaktur = $prefix . '-' . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);

                DB::table('N_EMI_LAB_Uji_Sampel')->insert([
                    'No_Faktur'               => $noFaktur,
                    'Kode_Perusahaan'         => '001',
                    'Id_Jenis_Analisa'        => $idAnalisaRaw,
                    'Hasil'                   => $hasilNum,
                    'Nilai_Hasil_String'      => $hasilStr,
                    'Flag_Perhitungan'        => null,
                    'Flag_String'             => $hasilStr !== null ? 'Y' : null,
                    'Status'                  => null,
                    'Flag_Selesai'            => 'Y',
                    'Flag_Resampling'         => 'Y',
                    'Tanggal'                 => $dt,
                    'Jam'                     => date('H:i:s'),
                    'Id_User'                 => $userId,
                    'No_Po_Sampel'            => $noPoSampel,
                    'Status_Keputusan_Sampel' => 'menunggu',
                    'Tahapan_Ke'              => $log->Tahapan_Ke,
                    'Id_Session'              => $session->Id_Session,
                    'Id_Pembanding'           => $idPembandingRaw,
                ]);

                DB::table('N_EMI_LAB_Uji_Sampel_Resampling_Log')
                    ->where('Id_Resampling', $idResamplingRaw)
                    ->update(['Flag_Selesai_Resampling' => 'Y']);

                $insertedCount++;
            }

            DB::commit();

            return response()->json([
                'success'        => true,
                'status'         => 200,
                'message'        => 'Resampling PLT berhasil difinalisasi. ' . $insertedCount . ' data tersimpan ke Uji Sampel.',
                'inserted_count' => $insertedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UjiSampelController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()], 500);
        }
    }

    /* ─────────────────────────────────────────────────────────── */
    /* GET /lab/palatabilitas/{no_po_sampel}  (Inertia page)        */
    /* ─────────────────────────────────────────────────────────── */

    public function viewPalatabilitasManagement(string $no_po_sampel)
    {
        $po = DB::table('N_EMI_LAB_PO_Sampel as po')
            ->leftJoin('N_EMI_View_Barang as brg', 'brg.Kode_Barang', '=', 'po.Kode_Barang')
            ->where('po.No_Sampel', $no_po_sampel)
            ->select('po.No_Sampel', 'po.Kode_Barang', 'po.Id_Mesin', 'brg.Nama as Nama_Barang')
            ->first();

        if (!$po) {
            abort(404, 'Data sampel tidak ditemukan.');
        }

        $pltAnalisa = $this->getPltAnalisaFull($no_po_sampel)
            ->map(fn($a) => collect($a)->except('id_raw')->all());

        return Inertia::render('vue/dashboard/lab/palatabilitas/PalatabilitasManagement', [
            'No_Po_Sampel' => $no_po_sampel,
            'plt_analisa'  => $pltAnalisa->values(),
            'nama_barang'  => $po->Nama_Barang ?? $no_po_sampel,
        ]);
    }
}
