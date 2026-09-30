<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Sinkronisasi Master Barang Uji Lab -> berkala tiap 10 menit.
        // withoutOverlapping(): kalau jadwal sebelumnya belum kelar, yang baru dilewati (anti bottleneck).
        // Pengaman utama anti-overlap ada di dalam job (sp_getapplock, lintas-instance).
        $schedule->command('barang-uji:sync')
                 ->everyTenMinutes()
                 ->withoutOverlapping(15);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
