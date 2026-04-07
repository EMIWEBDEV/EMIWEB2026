<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogLabActivityDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payloadsActivityHasilDetail;
    protected $payloadsActivityParameterDetail;

    /**
     * Create a new job instance.
     *
     * @param array $payloadsActivityHasilDetail
     * @param array $payloadsActivityParameterDetail
     * @return void
     */
    public function __construct(array $payloadsActivityHasilDetail, array $payloadsActivityParameterDetail)
    {
        $this->payloadsActivityHasilDetail = $payloadsActivityHasilDetail;
        $this->payloadsActivityParameterDetail = $payloadsActivityParameterDetail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Lakukan bulk insert untuk log detail di background
            if (!empty($this->payloadsActivityHasilDetail)) {
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail')->insert($this->payloadsActivityHasilDetail);
                 Log::info('Background job: Inserted N_EMI_LAB_Activity_Uji_Sampel_Hasil_Detail', ['count' => count($this->payloadsActivityHasilDetail)]);
            }

            if (!empty($this->payloadsActivityParameterDetail)) {
                DB::table('N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail')->insert($this->payloadsActivityParameterDetail);
                Log::info('Background job: Inserted N_EMI_LAB_Activity_Uji_Sampel_Parameter_Detail', ['count' => count($this->payloadsActivityParameterDetail)]);
            }

        } catch (\Exception $e) {
            Log::error('❌ GAGAL MENJALANKAN JOB LogLabActivityDetailsJob', [
                'errorMessage' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                // 'payloadHasil' => json_encode($this->payloadsActivityHasilDetail), // Uncomment jika perlu debug payload
                // 'payloadParameter' => json_encode($this->payloadsActivityParameterDetail) // Uncomment jika perlu debug payload
            ]);

            // Anda bisa melempar ulang exception agar job di-retry (jika dikonfigurasi)
            // throw $e;
        }
    }
}