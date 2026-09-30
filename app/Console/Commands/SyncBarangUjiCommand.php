<?php

namespace App\Console\Commands;

use App\Jobs\SyncBarangUjiMasterJob;
use Illuminate\Console\Command;

class SyncBarangUjiCommand extends Command
{
    protected $signature = 'barang-uji:sync
                            {--now : Jalankan langsung di proses ini (tanpa antrian)}
                            {--rule= : Sinkron hanya untuk 1 id aturan master}';

    protected $description = 'Sinkronisasi Master Barang Uji Lab ke N_EMI_LAB_Barang_Analisa (semua varian)';

    public function handle(): int
    {
        $ruleId = $this->option('rule') ?: null;

        if ($this->option('now')) {
            $this->info('Menjalankan sinkronisasi langsung...');
            $r = (new SyncBarangUjiMasterJob($ruleId))->handle();

            if (!empty($r['skipped'])) {
                $this->warn('Dilewati: sinkronisasi lain sedang berjalan.');
                return self::SUCCESS;
            }

            $this->info("Selesai. Ditambahkan: {$r['inserted']} baris." . ($r['more'] ? ' Masih ada sisa, jalankan lagi.' : ''));
            return self::SUCCESS;
        }

        SyncBarangUjiMasterJob::dispatch($ruleId);
        $this->info('Job diantrekan ke queue: ' . SyncBarangUjiMasterJob::queueName());

        return self::SUCCESS;
    }
}
