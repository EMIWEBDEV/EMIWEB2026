<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Jobs\SyncBarangUjiMasterJob;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Barang Uji Lab (template per user) + sinkronisasi ke N_EMI_LAB_Barang_Analisa.
 *
 * - Master: aturan "user X dapat analisa Y di mesin Z (atau semua mesin)".
 * - Sync   : materialisasi ALL varian dari N_EMI_View_Barang ke N_EMI_LAB_Barang_Analisa.
 *            Idempotent (NOT EXISTS), chunked (anti-bottleneck), bertanda Id_User_Menginput='SYSTEM'.
 * - Pemicu : tombol manual (dengan preview) ATAU Cloud Tasks 'sync-barangujilab' (runTask).
 */
class BarangUjiMasterController extends Controller
{
    private const ALL         = 'ALL';
    private const SYNC_MARKER  = 'SYSTEM';

    // Mesin sinkronisasi (chunk, batas per panggilan, tabel Sinkron) ada di SyncBarangUjiMasterJob.
    private const T_MASTER = 'N_EMI_LAB_Barang_Analisa_Master';
    private const T_TARGET = 'N_EMI_LAB_Barang_Analisa';

    public function index()
    {
        $permissions = Session::get('user_permissions', []);
        $actions     = Arr::get($permissions, 'permissions.Master_Barang_Uji_Lab', []);

        return inertia('vue/dashboard/barang-uji-master/HomeBarangUjiMaster', [
            'canDelete' => in_array('DELETE', $actions),
        ]);
    }

    public function create()
    {
        $roles = Session::get('User_Roles') ?? [];
        return inertia('vue/dashboard/barang-uji-master/FormBarangUjiMaster', ['roles' => $roles]);
    }

    public function edit($id)
    {
        $roles = Session::get('User_Roles') ?? [];
        return inertia('vue/dashboard/barang-uji-master/FormEditBarangUjiMaster', ['roles' => $roles, 'id' => $id]);
    }

    /* ─────────────── Opsi dropdown ─────────────── */

    public function getOptionUser()
    {
        try {
            $user = DB::table('N_EMI_LAB_Users')->select('UserId', 'Nama')->orderBy('Nama')->get();
            return ResponseHelper::success($user, 'Data User berhasil diambil');
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    public function getOptionJenisAnalisa()
    {
        try {
            $data = DB::table('N_EMI_LAB_Jenis_Analisa')->select('id', 'Jenis_Analisa', 'Kode_Analisa')->orderBy('Kode_Analisa')->get();
            foreach ($data as $item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
            }
            return ResponseHelper::success($data, 'Data Jenis Analisa berhasil diambil');
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    public function getOptionMesin()
    {
        try {
            $mesin = DB::table('EMI_Master_Mesin')->select('Id_Master_Mesin', 'Nama_Mesin', 'Seri_Mesin')->orderBy('Nama_Mesin')->get();
            foreach ($mesin as $item) {
                $item->Id_Master_Mesin = Hashids::connection('custom')->encode($item->Id_Master_Mesin);
            }
            return ResponseHelper::success($mesin, 'Data Mesin berhasil diambil');
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /* ─────────────── Daftar master ─────────────── */

    public function getData(Request $request)
    {
        $kodeRoles = collect(Session::get('User_Roles', []))->pluck('Kode_Role')->toArray();
        if (empty($kodeRoles)) {
            return response()->json(['success' => false, 'status' => 404, 'message' => 'Data tidak ditemukan (Role kosong).'], 404);
        }

        try {
            $limit   = (int) $request->input('limit', 10);
            $keyword = strtolower(trim($request->input('q', '')));

            $query = DB::table(self::T_MASTER . ' as M')
                ->leftJoin('N_EMI_LAB_Jenis_Analisa as J', 'M.Id_Jenis_Analisa', '=', 'J.id')
                ->leftJoin('EMI_Master_Mesin as MS', 'M.Id_Master_Mesin', '=', 'MS.Id_Master_Mesin')
                ->leftJoin('N_EMI_LAB_Users as U', 'M.Id_User', '=', 'U.UserId')
                ->where(function ($w) use ($kodeRoles) {
                    $w->whereIn('M.Kode_Role', $kodeRoles)->orWhereNull('M.Kode_Role');
                })
                ->select(
                    'M.id',
                    'M.Id_User',
                    'U.Nama as nama_user',
                    'M.Id_Jenis_Analisa',
                    'J.Kode_Analisa as kode_analisa',
                    'J.Jenis_Analisa as jenis_analisa',
                    'M.Id_Master_Mesin',
                    'MS.Nama_Mesin as nama_mesin',
                    'M.Kode_Role',
                    'M.Flag_Aktif'
                );

            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->whereRaw('LOWER(U.Nama) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(M.Id_User) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(J.Kode_Analisa) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(J.Jenis_Analisa) LIKE ?', ["%{$keyword}%"])
                      ->orWhereRaw('LOWER(MS.Nama_Mesin) LIKE ?', ["%{$keyword}%"]);
                });
            }

            $paginator = $query->orderBy('M.id', 'desc')->paginate($limit);

            if ($paginator->isEmpty()) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Data Tidak Ditemukan'], 404);
            }

            $items = collect($paginator->items())->map(function ($item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
                return $item;
            });

            return response()->json([
                'success'    => true,
                'status'     => 200,
                'message'    => 'Data Ditemukan',
                'result'     => $items,
                'page'       => $paginator->currentPage(),
                'total_page' => $paginator->lastPage(),
                'total_data' => $paginator->total(),
            ], 200);
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    public function detail($id)
    {
        try {
            $rowId = $this->decode($id);
            if ($rowId === null) return ResponseHelper::error('Format ID tidak valid.', 400);

            $row = DB::table(self::T_MASTER)->where('id', $rowId)->first();
            if (!$row) return ResponseHelper::error('Data tidak ditemukan.', 404);

            return ResponseHelper::success([
                'Id_User'          => $row->Id_User,
                'Id_Jenis_Analisa' => Hashids::connection('custom')->encode($row->Id_Jenis_Analisa),
                'Id_Master_Mesin'  => $row->Id_Master_Mesin === null ? self::ALL : Hashids::connection('custom')->encode($row->Id_Master_Mesin),
                'Kode_Role'        => $row->Kode_Role,
                'Flag_Aktif'       => $row->Flag_Aktif,
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /* ─────────────── Simpan / ubah / hapus ─────────────── */

    public function store(Request $request)
    {
        $roles      = Session::get('User_Roles') ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Id_User'                 => 'required',
            'Kode_Role'               => $jumlahRole > 1 ? 'required' : 'nullable',
            'items'                   => 'required|array|min:1',
            'items.*.Id_Jenis_Analisa'=> 'required',
            'items.*.Id_Master_Mesin' => 'required',
        ], [
            'Id_User.required'                  => 'User wajib dipilih!',
            'Kode_Role.required'                => 'Penempatan/Role Tidak Boleh Kosong!',
            'items.*.Id_Jenis_Analisa.required' => 'Jenis Analisa wajib diisi!',
            'items.*.Id_Master_Mesin.required'  => 'Mesin wajib dipilih (boleh SEMUA MESIN)!',
        ]);

        $kodeRoleValid = ($jumlahRole === 1)
            ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role'])
            : $request->Kode_Role;

        [$tanggal, $jam] = $this->now();
        $admin = Auth::user()->UserId ?? self::SYNC_MARKER;

        DB::beginTransaction();
        try {
            $inserted = 0;
            $duplikat = 0;

            foreach ($request->items as $item) {
                $idJenis = $this->decode($item['Id_Jenis_Analisa']);
                $idMesin = ($item['Id_Master_Mesin'] === self::ALL) ? null : $this->decode($item['Id_Master_Mesin']);

                $exists = DB::table(self::T_MASTER)
                    ->where('Id_User', $request->Id_User)
                    ->where('Id_Jenis_Analisa', $idJenis)
                    ->where('Kode_Role', $kodeRoleValid)
                    ->where(fn ($q) => $idMesin === null ? $q->whereNull('Id_Master_Mesin') : $q->where('Id_Master_Mesin', $idMesin))
                    ->exists();

                if ($exists) { $duplikat++; continue; }

                DB::table(self::T_MASTER)->insert([
                    'Id_User'           => $request->Id_User,
                    'Id_Jenis_Analisa'  => $idJenis,
                    'Id_Master_Mesin'   => $idMesin,
                    'Kode_Role'         => $kodeRoleValid,
                    'Kode_Perusahaan'   => '001',
                    'Flag_Aktif'        => 'Y',
                    'Tanggal'           => $tanggal,
                    'Jam'               => $jam,
                    'Id_User_Menginput' => $admin,
                ]);
                $inserted++;
            }

            DB::commit();

            $msg = $duplikat > 0
                ? "Tersimpan {$inserted} aturan. {$duplikat} duplikat diabaikan."
                : 'Semua aturan berhasil disimpan.';
            return ResponseHelper::success(['inserted' => $inserted, 'duplikat' => $duplikat], $msg, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail($e);
        }
    }

    public function update(Request $request, $id)
    {
        $rowId = $this->decode($id);
        if ($rowId === null) return ResponseHelper::error('Format ID tidak valid.', 400);

        $roles      = Session::get('User_Roles') ?? [];
        $jumlahRole = count($roles);

        $request->validate([
            'Id_User'          => 'required',
            'Id_Jenis_Analisa' => 'required',
            'Id_Master_Mesin'  => 'required',
            'Kode_Role'        => $jumlahRole > 1 ? 'required' : 'nullable',
        ]);

        $kodeRoleValid = ($jumlahRole === 1)
            ? (is_object($roles[0]) ? $roles[0]->Kode_Role : $roles[0]['Kode_Role'])
            : $request->Kode_Role;

        DB::beginTransaction();
        try {
            $idJenis = $this->decode($request->Id_Jenis_Analisa);
            $idMesin = ($request->Id_Master_Mesin === self::ALL) ? null : $this->decode($request->Id_Master_Mesin);

            // Simpan definisi LAMA untuk mencabut baris hasil-sync yang tidak relevan lagi.
            $old = DB::table(self::T_MASTER)->where('id', $rowId)->first();
            if (!$old) {
                DB::rollBack();
                return ResponseHelper::error('Aturan tidak ditemukan.', 404);
            }
            $oldDef = [
                'Id_User'          => $old->Id_User,
                'Id_Jenis_Analisa' => $old->Id_Jenis_Analisa,
                'Kode_Role'        => $old->Kode_Role,
                'Id_Master_Mesin'  => $old->Id_Master_Mesin,
            ];

            $dup = DB::table(self::T_MASTER)
                ->where('id', '<>', $rowId)
                ->where('Id_User', $request->Id_User)
                ->where('Id_Jenis_Analisa', $idJenis)
                ->where('Kode_Role', $kodeRoleValid)
                ->where(fn ($q) => $idMesin === null ? $q->whereNull('Id_Master_Mesin') : $q->where('Id_Master_Mesin', $idMesin))
                ->exists();

            if ($dup) {
                DB::rollBack();
                return ResponseHelper::error('Kombinasi aturan tersebut sudah ada.', 422);
            }

            $flagAktif = $request->input('Flag_Aktif', 'Y');

            DB::table(self::T_MASTER)->where('id', $rowId)->update([
                'Id_User'          => $request->Id_User,
                'Id_Jenis_Analisa' => $idJenis,
                'Id_Master_Mesin'  => $idMesin,
                'Kode_Role'        => $kodeRoleValid,
                'Flag_Aktif'       => $flagAktif,
            ]);

            DB::commit();

            // Rekonsiliasi di latar belakang: cabut baris hasil-sync definisi lama,
            // lalu sync ulang definisi baru (kalau masih aktif). Baris manual tidak tersentuh.
            SyncBarangUjiMasterJob::dispatch(
                $flagAktif === 'Y' ? $rowId : null,
                $oldDef,
                $flagAktif !== 'Y' // nonaktif -> cabut saja, tidak sync
            );

            return ResponseHelper::success(
                ['queued' => true],
                'Aturan diperbarui. Penyesuaian data barang uji diproses di latar belakang.',
                200
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail($e);
        }
    }

    public function destroy($id)
    {
        try {
            $rowId = $this->decode($id);
            if ($rowId === null) return ResponseHelper::error('Format ID tidak valid.', 400);

            $old = DB::table(self::T_MASTER)->where('id', $rowId)->first();
            if (!$old) return ResponseHelper::error('Data tidak ditemukan.', 404);

            $oldDef = [
                'Id_User'          => $old->Id_User,
                'Id_Jenis_Analisa' => $old->Id_Jenis_Analisa,
                'Kode_Role'        => $old->Kode_Role,
                'Id_Master_Mesin'  => $old->Id_Master_Mesin,
            ];

            DB::table(self::T_MASTER)->where('id', $rowId)->delete();

            // Cabut baris hasil-sync milik aturan ini di latar belakang (skipSync = true).
            SyncBarangUjiMasterJob::dispatch(null, $oldDef, true);

            return ResponseHelper::success(
                ['queued' => true],
                'Aturan dihapus. Pencabutan data barang uji diproses di latar belakang.',
                200
            );
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /* ─────────────── Preview (sebelum vs akan di-sync) ─────────────── */

    public function preview($id)
    {
        try {
            $rowId = $this->decode($id);
            if ($rowId === null) return ResponseHelper::error('Format ID tidak valid.', 400);

            $rule = DB::table(self::T_MASTER)->where('id', $rowId)->first();
            if (!$rule) return ResponseHelper::error('Aturan tidak ditemukan.', 404);

            // Data yang AKAN di-sync (baru / hijau) — kombinasi barang×mesin yang belum ada.
            $toSyncCount = SyncBarangUjiMasterJob::targetQuery($rule)->count();
            $toSync      = SyncBarangUjiMasterJob::targetQuery($rule)->orderBy('v.Kode_Barang')->limit(200)->get();

            // Data SEBELUM (sudah ada di Barang_Analisa untuk aturan ini).
            $beforeCount = DB::table(self::T_TARGET)
                ->where('Id_User', $rule->Id_User)
                ->where('Id_Jenis_Analisa', $rule->Id_Jenis_Analisa)
                ->where('Kode_Role', $rule->Kode_Role)
                ->where(fn ($q) => $rule->Id_Master_Mesin === null ? $q : $q->where('Id_Master_Mesin', $rule->Id_Master_Mesin))
                ->count();

            $before = DB::table(self::T_TARGET . ' as ba')
                ->leftJoin('EMI_Master_Mesin as m', 'ba.Id_Master_Mesin', '=', 'm.Id_Master_Mesin')
                ->where('ba.Id_User', $rule->Id_User)
                ->where('ba.Id_Jenis_Analisa', $rule->Id_Jenis_Analisa)
                ->where('ba.Kode_Role', $rule->Kode_Role)
                ->where(fn ($q) => $rule->Id_Master_Mesin === null ? $q : $q->where('ba.Id_Master_Mesin', $rule->Id_Master_Mesin))
                ->select('ba.Kode_Barang', 'm.Nama_Mesin')
                ->orderBy('ba.Kode_Barang')
                ->limit(200)
                ->get();

            return ResponseHelper::success([
                'rule' => [
                    'Id_User'   => $rule->Id_User,
                    'Kode_Role' => $rule->Kode_Role,
                    'all_mesin' => $rule->Id_Master_Mesin === null,
                ],
                'before'       => $before,
                'before_count' => $beforeCount,
                'to_sync'      => $toSync,
                'to_sync_count'=> $toSyncCount,
            ], $toSyncCount > 0 ? 'Ada data baru untuk disinkron.' : 'Tidak ada data baru.');
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /* ─────────────── Progress (untuk polling widget) ─────────────── */

    /**
     * Jumlah job sinkronisasi yang masih menunggu/diproses di antrian.
     * Dipakai widget untuk memantau sampai selesai (pending = 0).
     */
    public function progress()
    {
        try {
            $queue = SyncBarangUjiMasterJob::queueName();
            $table = config('queue.connections.database.table', 'N_EMI_LAB_Jobs');

            $pending = DB::table($table)->where('queue', $queue)->count();

            return ResponseHelper::success([
                'queue'   => $queue,
                'pending' => $pending,
                'running' => $pending > 0,
            ], 'ok', 200);
        } catch (\Exception $e) {
            // Kalau pakai Cloud Tasks (tabel jobs tak terpakai) -> anggap tidak ada antrian lokal.
            return ResponseHelper::success(['queue' => null, 'pending' => 0, 'running' => false], 'ok', 200);
        }
    }

    /* ─────────────── Sync (materialisasi) ─────────────── */

    public function sync($id)
    {
        try {
            $rowId = $this->decode($id);
            if ($rowId === null) return ResponseHelper::error('Format ID tidak valid.', 400);

            $rule = DB::table(self::T_MASTER)->where('id', $rowId)->where('Flag_Aktif', 'Y')->first();
            if (!$rule) return ResponseHelper::error('Aturan tidak ditemukan / nonaktif.', 404);

            // Dikirim ke queue (non-blocking). Request TIDAK menunggu ribuan insert selesai,
            // supaya tidak timeout / menggantung. Progres dipantau lewat endpoint preview.
            SyncBarangUjiMasterJob::dispatch($rowId);

            return ResponseHelper::success(
                ['queued' => true],
                'Sync dikirim ke antrian dan diproses di latar belakang. Angka akan diperbarui otomatis.',
                200
            );
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /**
     * Endpoint untuk Cloud Tasks 'sync-barangujilab' (tanpa sesi login).
     * Amankan dengan header token: X-Sync-Token harus sama dengan env SYNC_BARANG_TOKEN.
     */
    public function runTask(Request $request)
    {
        $token = env('SYNC_BARANG_TOKEN');
        if (!$token || $request->header('X-Sync-Token') !== $token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            // Cukup antrekan. Driver queue yang menentukan eksekusinya:
            //  - cloudtasks (produksi) : Google Cloud Tasks yang memanggil balik & menjalankan job
            //  - database/sync (lokal) : diproses `php artisan queue:work`
            // Tidak ada worker manual / Artisan::call di sini.
            SyncBarangUjiMasterJob::dispatch();

            Log::channel('BarangUjiMasterController')->info('Sync diantrekan.', [
                'queue' => SyncBarangUjiMasterJob::queueName(),
            ]);

            return response()->json([
                'success' => true,
                'queued'  => true,
                'queue'   => SyncBarangUjiMasterJob::queueName(),
            ], 200);
        } catch (\Exception $e) {
            Log::channel('BarangUjiMasterController')->error(__METHOD__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    /**
     * Bersihkan baris duplikat 'SYSTEM' (user+analisa+barang+mesin+role) — sisakan 1 (utamakan baris manual).
     */
    public function cleanupDuplikat()
    {
        try {
            $sql = "
                WITH d AS (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY Id_User, Id_Jenis_Analisa, Kode_Barang, Id_Master_Mesin, Kode_Role
                               ORDER BY CASE WHEN Id_User_Menginput = ? THEN 1 ELSE 0 END, id
                           ) AS rn
                    FROM " . self::T_TARGET . "
                )
                DELETE FROM d WHERE rn > 1
            ";
            $deleted = DB::delete($sql, [self::SYNC_MARKER]);

            return ResponseHelper::success(['deleted' => $deleted], "Duplikat dibersihkan: {$deleted} baris dihapus.", 200);
        } catch (\Exception $e) {
            return $this->fail($e);
        }
    }

    /* ─────────────── Helper internal ─────────────── */

    private function now(): array
    {
        $waktu = DB::select('SELECT dbo.Get_Date_Time() as DateTimeNow');
        $dt = $waktu[0]->DateTimeNow;
        return [date('Y-m-d', strtotime($dt)), date('H:i:s', strtotime($dt))];
    }

    private function decode($hash)
    {
        try {
            $d = Hashids::connection('custom')->decode($hash);
            return $d[0] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function fail(\Exception $e)
    {
        Log::channel('BarangUjiMasterController')->error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
    }
}
