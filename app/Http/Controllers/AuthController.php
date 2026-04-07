<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use Exception;
use GuzzleHttp\Client;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{

    public function form_login()
    {
        $title = "Login";
        return view('auth.login', compact('title'));
        // return view("errors.503");
        
    }
    public function changePasswordSubmitRegisterSampel(Request $request)
    {
        $request->validate([
            'UserId' => 'required|string',
            'Password' => 'required|string',
        ]);
    
        $user = User::where('UserId', $request->UserId)->first();
    
        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Akun Tidak Terdaftar'
            ], 404);
        }
    
        if ($user->Flag_Aktif === null) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Akun Anda Telah Di Nonaktifkan, Silahkan Hubungi IT Pusat PT EVO Manufacturing'
            ], 403);
        }
    
        if (!Hash::check(env('SALT_PREFIX') . $request->Password . env('SALT_SUFFIX'), $user->Password)) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Password Anda Salah'
            ], 401);
        }
    
        try {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Ganti User Berhasil',
                'result' => $user->UserId
            ], 200);
        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Terjadi Kesalahan'
            ], 500);
        }
    }
    
    public function proses_login(Request $request)
    {
        // if (!$this->verifyCloudflareCaptcha($request)) {
        //      return back()
        //          ->withErrors(['captcha' => 'Please complete the security check'])
        //          ->withInput($request->except('password'));
        // }

        $request->validate([
            'UserId' => 'required|string',
            'Password' => 'required|string',

        ]);

        $user = User::where('UserId', $request->UserId)->first();
 
        if (!$user) {
            return back()->with('error', "Akun Tidak Terdaftar");
        }

        if($user->Flag_Aktif === null){
            return back()->with('error', "Akun Anda Telah Di Nonaktifkan, Silahkan Hubungi IT Pusat PT EVO Manufacturing");
        }

        // if (!Hash::check(env('SALT_PREFIX') . $request->Password . env('SALT_SUFFIX'), $user->Password)) {
        //     return back()->with('error', "Password Anda Salah");
        // }

        try {
            Auth::login($user);
            $request->session()->regenerate();
            $userId = $user->UserId;


            $userRoles = DB::table('N_EMI_LAB_User_Roles as ur')
                        ->join('N_EMI_LAB_Roles as r','ur.Id_Role','=','r.Id_Role')
                        ->where('ur.Id_User', $userId)
                        ->get(['r.Id_Role','r.Kode_Role', 'r.Nama_Role', 'r.Deskripsi'])
                        ->toArray();

            $request->session()->put('User_Roles', $userRoles);

            $hasDashboardAccess = DB::table('N_EMI_LAB_Role_Menu')
                ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
                ->where('N_EMI_LAB_Role_Menu.Id_User', $userId)
                ->where('N_EMI_LAB_Menus.Nama_Menu', 'Dashboard')
                ->exists();

            $hasHomeAccess = DB::table('N_EMI_LAB_Role_Menu')
                ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
                ->where('N_EMI_LAB_Role_Menu.Id_User', $userId)
                ->where('N_EMI_LAB_Menus.Nama_Menu', 'Registrasi Sampel')
                ->exists();

            $hasMaterialAccess = DB::table('N_EMI_LAB_Role_Menu')
                ->join('N_EMI_LAB_Menus', 'N_EMI_LAB_Role_Menu.Id_Menu', '=', 'N_EMI_LAB_Menus.Id_Menu')
                ->where('N_EMI_LAB_Role_Menu.Id_User', $userId)
                ->where('N_EMI_LAB_Menus.Nama_Menu', 'Registrasi Material')
                ->exists();

            if ($hasDashboardAccess) {
                return redirect('/dashboard');
            } elseif ($hasHomeAccess) {
                return redirect('/home');
            } elseif ($hasMaterialAccess) {
                return redirect('/registrasi-material');
            } else {
                return redirect('/master-template-printer-transaksi');
            }

        } catch (\Exception $e) {
            Log::channel('AuthController')->error('Login Error: ' . $e->getMessage());
            return back()->with('error', "Terjadi Kesalahan");
        }
    }

    public function gantiPassword(Request $request)
    {
        $request->validate([
            'PasswordLama' => 'required',
            'Password' => [
                'required',
                'min:6',
                'regex:/^(?=.*[a-zA-Z])(?=.*[\W_]).{6,}$/'
            ],
        ], [
            'Password.required' => 'Password baru wajib diisi',
            'Password.min' => 'Password baru minimal 6 karakter',
            'Password.regex' => 'Password harus mengandung huruf dan simbol',
        ]);
        

        $user = User::where('UserId', Auth::user()->UserId)->first();

        if (!$user) {
            return ResponseHelper::error("Username Tidak Terdaftar", 400);
        }

        if (!Hash::check(env('SALT_PREFIX') . $request->Password . env('SALT_SUFFIX'), $user->Password)) {
            return ResponseHelper::error("Password Lama Salah", 400);
        }

        if (Hash::check($request->PasswordBaru, $user->Password)) {
            return ResponseHelper::error("Password Baru Tidak Boleh Sama Dengan Password Lama", 400);
        }

        try {
           DB::table('N_EMI_LAB_Users')
                ->where('UserId', $user->UserId)
                ->update([
                    'Password' => Hash::make(env('SALT_PREFIX') . $request->Password . env('SALT_SUFFIX')),
                ]);
            return back()->with('success', "Berhasil Mengupdate Password");
        } catch (\Exception $e) {
            Log::channel('AuthController')->error('Login Error: ' . $e->getMessage());
            return back()->with('error', "Terjadi Kesalahan");
        }
    }
    public function gantiPin(Request $request)
    {
      
        $request->validate([
            'PinLama' => 'required|string',
            'Pin' => 'required|string|min:6', 
        ]);

        // Ambil data user berdasarkan UserId
        $user = User::where('UserId', Auth::user()->UserId)->first();

        // Cek apakah user ada
        if (!$user) {
            return ResponseHelper::error("Username Tidak Terdaftar", 400);
        }

        // Cek apakah password lama yang diberikan benar
        if (!Hash::check($request->PinLama, $user->Pin)) {
            return ResponseHelper::error("Pin Lama Salah", 400);
        }

        // Cek apakah password baru sama dengan password lama
        if (Hash::check($request->Pin, $user->Pin)) {
            return ResponseHelper::error("Pin Baru Tidak Boleh Sama Dengan Password Lama", 400);
        }

        try {
           DB::table('N_EMI_LAB_Users')
                ->where('UserId', $user->UserId)
                ->update([
                    'Pin' => Hash::make($request->Pin)
                ]);

            return back()->with('success', "Berhasil Mengupdate Pin");
        } catch (\Exception $e) {
            Log::channel('AuthController')->error('Login Error: ' . $e->getMessage());
            return back()->with('error', "Terjadi Kesalahan");
        }
    }
    
    private function verifyCloudflareCaptcha(Request $request): bool
    {
        $captchaResponse = $request->input('cf-turnstile-response');

        if (!$captchaResponse) {
            return false;
        }

        try {
            $client = new Client();
            $response = $client->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'form_params' => [
                    'secret' => env('CLOUDFLARE_TURNSTILE_SECRET'),
                    'response' => $captchaResponse,
                    'remoteip' => $request->ip()
                ]
            ]);

            $body = json_decode($response->getBody(), true);
            return $body['success'] ?? false;
        } catch (\Exception $e) {
            Log::error('Cloudflare Turnstile Verification Failed: ' . $e->getMessage());
            return false;
        }
    }

    public function logout()
    {

        try {
            Auth::logout();
            return redirect('/logout-clear');
        } catch (Exception $error) {
            return redirect('dashboard')->with('message', $error);
        }
    }
    public function logoutclear()
    {
        return view("auth.logout-clear");
    }

    public function proses_register(Request $request)
    {
        $request->validate([
            'UserId' => 'required',
            'Nama' => 'required',
            'Password' => 'required',
            'Pin' => 'required'
        ], [
            'Nama.required' => "Nama lengkap Tidak boleh kosong !",
            'UserId.required' => "username Tidak boleh kosong !",
            'Password.required' => "password Tidak boleh kosong !",
            'Pin.required' => "Pin Tidak boleh kosong !",
        ]);

        DB::beginTransaction();

        // $user = DB::table('N_EMI_View_Users')->where('UserID', $request->UserId)->first();
        // if (!$user) {
        //    return ResponseHelper::error("Username Belum Terdaftar", 400);
        // }

        // $user = DB::table('N_EMI_LAB_Users')->where('UserId', $request->UserId)->first();
        // if ($user) {
        //    return ResponseHelper::error("Username Telah Terdaftar", 400);
        // }

        try {

            $payload = [
                'Kode_Perusahaan' => '001',
                'UserId' => $request->UserId,
                'Nama' => $request->Nama,
                'Password' => Hash::make(env('SALT_PREFIX') . $request->Password . env('SALT_SUFFIX')),
                'Pin' => Hash::make($request->Pin),
                'Flag_Aktif' => 'Y'
            ];

            DB::table('N_EMI_LAB_Users')->insert($payload);

            DB::commit();
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan"
            ], 201);
        }catch(\Exception $e){
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
            Log::error($e->getMessage());
        }

    }
    
    public function proses_update_akun(Request $request)
    {
        
        $request->validate([
            'UserId' => 'required',
            'Nama' => 'required',
            'Password' => 'required',
            'Pin' => 'required'
        ], [
            'Nama.required' => "Nama lengkap tidak boleh kosong!",
            'Password.required' => "Password tidak boleh kosong!",
            'Pin.required' => "Pin tidak boleh kosong!",
        ]);

        DB::beginTransaction();

        try {
            $user = DB::table('N_EMI_LAB_Users')->where('UserId', $request->UserId)->first();

            if (!$user) {
                return ResponseHelper::error("User tidak ditemukan", 404);
            }

            $updateData = [
                'Nama' => $request->Nama,
                'Password' => Hash::make(env('SALT_PREFIX') . $request->Password . env('SALT_SUFFIX')),
                'Pin' => Hash::make($request->Pin),
            ];

            DB::table('N_EMI_LAB_Users')->where('UserId', $request->UserId)->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data akun berhasil diperbarui"
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal update akun: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => 'Terjadi kesalahan saat memperbarui data',
                    'detail' => $e->getMessage()
                ]
            ], 500);
        }
    }
    public function form_register()
    {
        $userId = collect(DB::table('N_EMI_View_Users')->get());

        return inertia('vue/dashboard/master-akun/FormTambahAkun', [
            'akun' => $userId
        ]);
    }
    public function ViewMasterAkun()
    {
        return inertia('vue/dashboard/master-akun/HomeMasterAkun');
    }

    public function getDataAkunPengguna(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $total = DB::table('N_EMI_LAB_Users')->count();

            $rows = DB::table('N_EMI_LAB_Users')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->toArray(); 
    
            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan !",
                ], 404);
            }
    
            $data = array_map(function ($item) {
                return [
                    'Id_Lab_Users' => Hashids::connection('custom')->encode($item->Id_Lab_Users),
                    'Kode_Perusahaan' => $item->Kode_Perusahaan,
                    'UserId' => $item->UserId,
                    'Nama' => $item->Nama,
                    'Flag_Aktif' => $item->Flag_Aktif,
                    'Id_Jabatan' => $item->Id_Jabatan !== null
                        ? Hashids::connection('custom')->encode($item->Id_Jabatan)
                        : null,
                ];
            }, $rows);
    
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $data,
                'page' => $page,
                'total_page' => ceil($total / $limit),
                'total_data' => $total
            ], 200);
            
        } catch (\Exception $e) {
            Log::channel('AuthController')->error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }

    public function getDataAkunPenggunaJson()
    {
        try {
            $getData = DB::table('N_EMI_LAB_Users')
                ->get();

            if ($getData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Data Tidak Ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'result' => $getData,
            ]);
        } catch (\Exception $e) {
            Log::channel('AuthController')->error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ], 500);
        }
    }
    public function searchDataPengguna(Request $request)
    {
       try {
           $keyword = $request->input('q');

            if (empty($keyword)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => [
                        'error' => "Kata kunci pencarian tidak boleh kosong."
                    ]
                ], 400);
            }

            $getData = DB::table('N_EMI_LAB_Users')
                ->whereRaw('LOWER(UserId) LIKE ?', ['%' . strtolower($keyword) . '%'])
                ->get()
                ->toArray();
            
            if(empty($getData)){
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => "Data Tidak Ditemukan !",
                ], 404);
            }

            $data = array_map(function ($item) {
                return [
                    'Id_Lab_Users' => Hashids::connection('custom')->encode($item->Id_Lab_Users),
                    'Kode_Perusahaan' => $item->Kode_Perusahaan,
                    'UserId' => $item->UserId,
                    'Nama' => $item->Nama,
                    'Flag_Aktif' => $item->Flag_Aktif,
                    'Id_Jabatan' => $item->Id_Jabatan !== null
                        ? Hashids::connection('custom')->encode($item->Id_Jabatan)
                        : null,
                ];
            }, $getData);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Data Ditemukan",
                'result' => $data,
            ], 200);
       }catch(\Exception $e){
        Log::channel('AuthController')->error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => $e->getMessage()
                ]
            ]);
       }
    }

    public function updateStatusAkun($UserId)
    {
        $getData = DB::table('N_EMI_LAB_Users')
            ->where('UserId', $UserId)
            ->first();

        if (!$getData) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => "Data Tidak Ditemukan !",
            ], 404);
        }

        try {
            DB::beginTransaction();

            $newStatus = $getData->Flag_Aktif === 'Y' ? null : 'Y';

            DB::table('N_EMI_LAB_Users')
                ->where('UserId', $UserId)
                ->update([
                    'Flag_Aktif' => $newStatus
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => $newStatus === 'Y' ? 'Berhasil Mengaktifkan Akun' : 'Berhasil Non Aktifkan Akun',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('AuthController')->error('Login Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }


}
