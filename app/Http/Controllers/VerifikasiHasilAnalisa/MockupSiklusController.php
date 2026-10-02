<?php

namespace App\Http\Controllers\VerifikasiHasilAnalisa;

use App\Http\Controllers\Controller;
use App\Services\MockupContohService;
use App\Services\RincianHasilAnalisaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use RuntimeException;

/**
 * MOCKUP SIKLUS — Validasi -> Verifikasi -> Finalisasi -> Hasil Analisa.
 *
 * Seluruh data siklus disimpan di SESSION STORAGE browser (tab yang sama,
 * lintas pergantian akun lewat /trial-ui). Server hanya:
 *   - menyajikan halaman beserta hak akun yang sedang login;
 *   - membuat contoh data dari master yang asli (MockupContohService);
 *   - menyajikan master tingkat keputusan.
 * Tidak ada yang ditulis ke database, sehingga modul lama dan data asli
 * tidak tersentuh.
 */
class MockupSiklusController extends Controller
{
    public function __construct(
        private MockupContohService $contoh,
        private RincianHasilAnalisaService $rincian
    ) {
    }

    public function validasi()   { return $this->halaman('validasi'); }
    public function verifikasi() { return $this->halaman('verifikasi'); }
    public function finalisasi() { return $this->halaman('finalisasi'); }
    public function hasil()      { return $this->halaman('hasil'); }

    /** Master untuk layar mockup: tingkat keputusan & skenario contoh. */
    public function master()
    {
        try {
            $keputusan = Schema::hasTable('N_EMI_LAB_Verifikasi_Keputusan')
                ? DB::table('N_EMI_LAB_Verifikasi_Keputusan')->where('Flag_Aktif', 'Y')->orderBy('Urutan')
                    ->select('Kode_Keputusan', 'Nama_Keputusan', 'Keterangan', 'Warna_Badge', 'Ikon',
                        'Flag_Wajib_Catatan', 'Panjang_Min_Catatan')->get()
                : collect();

            // Cadangan bila master belum dimigrasi (mis. database produksi).
            if ($keputusan->isEmpty()) {
                $keputusan = collect([
                    ['Kode_Keputusan' => 'REKOMENDASI', 'Nama_Keputusan' => 'Direkomendasikan', 'Warna_Badge' => 'ok',
                     'Ikon' => 'ri-checkbox-circle-line', 'Flag_Wajib_Catatan' => 'T', 'Panjang_Min_Catatan' => 0,
                     'Keterangan' => 'Hasil memenuhi syarat dan dapat diteruskan.'],
                    ['Kode_Keputusan' => 'REKOM_BERSYARAT', 'Nama_Keputusan' => 'Direkomendasikan bersyarat', 'Warna_Badge' => 'warn',
                     'Ikon' => 'ri-error-warning-line', 'Flag_Wajib_Catatan' => 'Y', 'Panjang_Min_Catatan' => 15,
                     'Keterangan' => 'Dapat diteruskan dengan catatan yang perlu diperhatikan.'],
                    ['Kode_Keputusan' => 'TIDAK_REKOM', 'Nama_Keputusan' => 'Tidak direkomendasikan', 'Warna_Badge' => 'bad',
                     'Ikon' => 'ri-close-circle-line', 'Flag_Wajib_Catatan' => 'Y', 'Panjang_Min_Catatan' => 15,
                     'Keterangan' => 'Hasil tidak dapat diteruskan.'],
                ]);
            }

            return response()->json([
                'success' => true,
                'result'  => [
                    'keputusan' => $keputusan->values(),
                    'skenario'  => MockupContohService::SKENARIO,
                ],
            ], 200);

        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal memuat master mockup.');
        }
    }

    /**
     * Buat contoh data: body { nomor: [...], sekarang: 'Y-m-d H:i:s',
     * opsi: { LCKV, ANL, PLT, foto_warna, foto_tekstur } }.
     */
    public function contoh(Request $request)
    {
        $request->validate([
            'nomor'    => 'required|array|min:1|max:5',
            'nomor.*'  => 'required|string|max:30',
            'sekarang' => 'required|date_format:Y-m-d H:i:s',
            'opsi'     => 'required|array',
        ]);

        try {
            return response()->json([
                'success' => true,
                'result'  => $this->contoh->buat($request->input('nomor'), $request->input('opsi'),
                    $request->input('sekarang')),
            ], 200);

        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return $this->gagal($e, 'Gagal membuat contoh data.');
        }
    }

    // ------------------------------------------------------------------

    private function halaman(string $tahap)
    {
        $user   = Auth::user();
        $id     = $user->UserId;
        $tahapan = TrialUiController::tahapanUntuk($id);

        return inertia('vue/dashboard/verifikasi-hasil-analisa/mockup/MockupSiklus', [
            'tahap'        => $tahap,
            'pengguna'     => ['id' => $id, 'nama' => trim((string) $user->Nama) ?: $id],
            'tahapan'      => $tahapan,
            'hakValidasi'  => $this->hakValidasi(),
            'kewenangan'   => $this->kewenanganVerifikasi($id),
            'akun'         => $this->akunSandbox(),
        ]);
    }

    /** Jenis analisa yang boleh divalidasi user — sama dengan modul lama. */
    private function hakValidasi(): array
    {
        $konten = Session::get('user_permissions')['permission_konten']['Validasi Trial Produksi'] ?? [];

        return collect(is_array($konten) ? $konten : [])
            ->filter(fn ($a) => ($a['flag'] ?? null) === 'Y' && isset($a['id_jenis_analisa']))
            ->pluck('id_jenis_analisa')->map(fn ($x) => (int) $x)->unique()->values()->all();
    }

    /**
     * Kewenangan verifikasi user per klasifikasi: null = seluruh analisa,
     * larik = analisa tertentu. Bila tabel kewenangan belum ada, seluruh
     * klasifikasi dianggap boleh ('*').
     */
    private function kewenanganVerifikasi(string $id): array
    {
        if (!Schema::hasTable('N_EMI_LAB_Verifikasi_Kewenangan')) {
            return ['*' => null];
        }

        $hasil = [];
        foreach ($this->rincian->petaKewenanganVerifikasi() as $kode => $petugas) {
            foreach ($petugas as $p) {
                if (strcasecmp($p['id'], $id) === 0) {
                    $hasil[$kode] = $p['analisa'];
                }
            }
        }

        return $hasil;
    }

    /**
     * Akun sandbox beserta tahapnya dan hak validasinya — dipakai layar
     * untuk menyebut antrean yang menunggu akun lain.
     */
    private function akunSandbox(): array
    {
        $users = DB::table('N_EMI_LAB_Users')
            ->whereIn(DB::raw('LOWER(UserId)'), ['ratna', 'jati', 'roby', 'vengine'])
            ->pluck('Nama', 'UserId');

        $hak = DB::table('N_EMI_LAB_Role_Konten_Access as rka')
            ->join('N_EMI_LAB_Page_Access_2 as pa', 'pa.Id_Page_Access', '=', 'rka.Id_Page_Access')
            ->join('N_EMI_LAB_Menus as m', 'm.Id_Menu', '=', 'pa.Id_Menu')
            ->whereIn('pa.Id_User', $users->keys()->all())
            ->where('m.Nama_Menu', 'Validasi Trial Produksi')
            ->where('rka.Flag_Diizinkan', 'Y')
            ->whereNotNull('rka.Id_Jenis_Analisa')
            ->select('pa.Id_User', 'rka.Id_Jenis_Analisa')
            ->get()
            ->groupBy(fn ($r) => strtolower($r->Id_User));

        return $users->map(fn ($nama, $id) => [
            'id'      => $id,
            'nama'    => trim((string) $nama) ?: $id,
            'tahapan' => TrialUiController::tahapanUntuk($id),
            'validasi'=> $hak->get(strtolower($id), collect())->pluck('Id_Jenis_Analisa')->map(fn ($x) => (int) $x)->values()->all(),
        ])->values()->all();
    }

    private function gagal(\Exception $e, string $pesan)
    {
        Log::error(__CLASS__ . ': ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

        return response()->json(['success' => false, 'message' => $pesan], 500);
    }
}
