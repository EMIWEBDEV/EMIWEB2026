<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mesin sinkronisasi Master Barang Uji Lab -> N_EMI_LAB_Barang_Analisa (ALL varian).
 *
 * Bisa dipakai 2 cara:
 *  - Antre  : SyncBarangUjiMasterJob::dispatch()  -> diproses `php artisan queue:work` (lokal)
 *  - Inline : (new SyncBarangUjiMasterJob())->handle() -> langsung (Cloud Run, tanpa worker)
 *
 * Aman: idempotent (NOT EXISTS), chunked, dan dikunci sp_getapplock supaya tidak overlap.
 */
class SyncBarangUjiMasterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const SYNC_MARKER  = 'SYSTEM';
    public const CHUNK        = 500;
    public const MAX_PER_CALL = 5000;

    /** Batas aman parameter per statement (SQL Server maksimum 2100). */
    public const MAX_BIND_PARAMS = 2000;

    public const T_MASTER  = 'N_EMI_LAB_Barang_Analisa_Master';
    public const T_SINKRON = 'N_EMI_LAB_Barang_Analisa_Sinkron';
    public const T_TARGET  = 'N_EMI_LAB_Barang_Analisa';

    public $tries = 1;
    public $timeout = 600;

    /** Nama queue job ini (config/queue.php -> barang_uji_sync). */
    public static function queueName(): string
    {
        return config('queue.barang_uji_sync', 'sync-barangujilab');
    }

    /** @var int|null null = semua aturan aktif; isi id = satu aturan saja */
    protected $ruleId;

    /** @var array|null definisi aturan LAMA yang baris SYSTEM-nya harus dicabut */
    protected $revoke;

    /** @var bool true = hanya mencabut, tidak melakukan sync (dipakai saat hapus aturan) */
    protected $skipSync;

    public function __construct($ruleId = null, ?array $revoke = null, bool $skipSync = false)
    {
        $this->ruleId   = $ruleId;
        $this->revoke   = $revoke;
        $this->skipSync = $skipSync;
        // Pakai onQueue() dari trait Queueable — jangan deklarasi ulang properti $queue.
        $this->onQueue(self::queueName());
    }

    /**
     * @return array{inserted:int,revoked:int,more:bool,skipped:bool}
     */
    public function handle(): array
    {
        $result = $this->runExclusive(function () {
            // 1) Cabut baris hasil-sync (SYSTEM) dari definisi aturan LAMA (saat edit/hapus).
            $revoked = $this->revoke ? $this->revokeOldRows($this->revoke) : 0;

            // 2) Sync definisi aturan yang baru/aktif (kecuali khusus hapus).
            $totalInserted = 0;
            $anyMore = false;

            if (!$this->skipSync) {
                $q = DB::table(self::T_MASTER)->where('Flag_Aktif', 'Y');
                if ($this->ruleId !== null) {
                    $q->where('id', $this->ruleId);
                }
                foreach ($q->get() as $rule) {
                    $s = $this->runSyncForRule($rule);
                    $totalInserted += $s['inserted'];
                    $anyMore = $anyMore || $s['more'];
                    if ($totalInserted >= self::MAX_PER_CALL) { $anyMore = true; break; }
                }
                $this->tandaiBarangBaru();
            }

            return ['inserted' => $totalInserted, 'revoked' => $revoked, 'more' => $anyMore, 'skipped' => false];
        });

        if ($result === null) {
            Log::channel('BarangUjiMasterController')->info('Sync dilewati (proses lain sedang berjalan).');
            return ['inserted' => 0, 'revoked' => 0, 'more' => true, 'skipped' => true];
        }

        Log::channel('BarangUjiMasterController')->info('Sync selesai', $result);
        return $result;
    }

    /**
     * Hapus baris di Barang_Analisa yang DIBUAT sistem (Id_User_Menginput='SYSTEM')
     * untuk kombinasi aturan lama. Baris input MANUAL tidak akan tersentuh.
     *
     * @param array{Id_User:string,Id_Jenis_Analisa:int,Kode_Role:string,Id_Master_Mesin:?int} $old
     */
    private function revokeOldRows(array $old): int
    {
        $q = DB::table(self::T_TARGET)
            ->where('Id_User_Menginput', self::SYNC_MARKER)
            ->where('Id_User', $old['Id_User'])
            ->where('Id_Jenis_Analisa', $old['Id_Jenis_Analisa'])
            ->where('Kode_Role', $old['Kode_Role']);

        // Mesin spesifik -> cabut mesin itu saja. ALL (null) -> cabut semua mesin aturan ini.
        if (array_key_exists('Id_Master_Mesin', $old) && $old['Id_Master_Mesin'] !== null) {
            $q->where('Id_Master_Mesin', $old['Id_Master_Mesin']);
        }

        return $q->delete();
    }

    /**
     * Kombinasi (barang x mesin) yang BELUM ada di Barang_Analisa untuk sebuah aturan.
     * Dipakai juga oleh controller untuk preview.
     */
    public static function targetQuery($rule)
    {
        $barangSub = DB::table('N_EMI_View_Barang')
            ->select('Kode_Barang', DB::raw('MAX(Nama) as Nama'))
            ->groupBy('Kode_Barang');

        $mesinSub = DB::table('EMI_Master_Mesin')->select('Id_Master_Mesin', 'Nama_Mesin');
        if ($rule->Id_Master_Mesin !== null) {
            $mesinSub->where('Id_Master_Mesin', $rule->Id_Master_Mesin);
        }

        return DB::query()
            ->fromSub($barangSub, 'v')
            ->crossJoinSub($mesinSub, 'm')
            ->whereNotExists(function ($q) use ($rule) {
                $q->select(DB::raw(1))
                  ->from(self::T_TARGET . ' as ba')
                  ->whereColumn('ba.Kode_Barang', 'v.Kode_Barang')
                  ->whereColumn('ba.Id_Master_Mesin', 'm.Id_Master_Mesin')
                  ->where('ba.Id_User', $rule->Id_User)
                  ->where('ba.Id_Jenis_Analisa', $rule->Id_Jenis_Analisa)
                  ->where('ba.Kode_Role', $rule->Kode_Role);
            })
            ->select('v.Kode_Barang', 'v.Nama', 'm.Id_Master_Mesin', 'm.Nama_Mesin');
    }

    private function runSyncForRule($rule): array
    {
        [$tanggal, $jam] = $this->now();
        $inserted = 0;

        while ($inserted < self::MAX_PER_CALL) {
            $chunk = self::targetQuery($rule)->orderBy('v.Kode_Barang')->limit(self::CHUNK)->get();
            if ($chunk->isEmpty()) {
                return ['inserted' => $inserted, 'more' => false];
            }

            $rows = $chunk->map(fn ($r) => [
                'Id_Jenis_Analisa'  => $rule->Id_Jenis_Analisa,
                'Id_Master_Mesin'   => $r->Id_Master_Mesin,
                'Id_User'           => $rule->Id_User,
                'Kode_Barang'       => $r->Kode_Barang,
                'Kode_Role'         => $rule->Kode_Role,
                'Kode_Perusahaan'   => $rule->Kode_Perusahaan ?? '001',
                'Tanggal'           => $tanggal,
                'Jam'               => $jam,
                'Id_User_Menginput' => self::SYNC_MARKER,
                'Flag_Aktif'        => 'Y',
            ])->all();

            // SQL Server: maksimum 2100 parameter per statement.
            // Jumlah parameter = jumlah_baris x jumlah_kolom, jadi baris per INSERT dihitung dinamis.
            $perInsert = max(1, intdiv(self::MAX_BIND_PARAMS, max(1, count($rows[0]))));
            foreach (array_chunk($rows, $perInsert) as $part) {
                DB::table(self::T_TARGET)->insert($part);
            }
            $inserted += count($rows);

            if (count($rows) < self::CHUNK) {
                return ['inserted' => $inserted, 'more' => false];
            }
        }

        return ['inserted' => $inserted, 'more' => true];
    }

    private function tandaiBarangBaru(): void
    {
        [$tanggal, $jam] = $this->now();
        DB::statement("
            INSERT INTO " . self::T_SINKRON . " (Kode_Barang, Kode_Perusahaan, Tanggal, Jam, Id_User_Menginput)
            SELECT DISTINCT v.Kode_Barang, '001', ?, ?, ?
            FROM N_EMI_View_Barang v
            WHERE NOT EXISTS (SELECT 1 FROM " . self::T_SINKRON . " s WHERE s.Kode_Barang = v.Kode_Barang)
        ", [$tanggal, $jam, self::SYNC_MARKER]);
    }

    /**
     * Kunci eksklusif lintas-instance (SQL Server application lock).
     * Timeout 0 -> kalau sedang berjalan, langsung null (dilewati), tidak menumpuk.
     */
    private function runExclusive(callable $fn)
    {
        DB::beginTransaction();
        try {
            $row = DB::selectOne("
                DECLARE @res int;
                EXEC @res = sp_getapplock @Resource = N'sync-barangujilab',
                    @LockMode = 'Exclusive', @LockOwner = 'Transaction', @LockTimeout = 0;
                SELECT @res AS res;
            ");

            if (!$row || $row->res < 0) {
                DB::rollBack();
                return null;
            }

            $result = $fn();
            DB::commit();
            return $result;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function now(): array
    {
        $waktu = DB::select('SELECT dbo.Get_Date_Time() as DateTimeNow');
        $dt = $waktu[0]->DateTimeNow;
        return [date('Y-m-d', strtotime($dt)), date('H:i:s', strtotime($dt))];
    }
}
