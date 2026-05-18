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
use Illuminate\Support\Str;
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
        if (app()->environment('production')) {
            if (!$this->verifyCloudflareCaptcha($request)) {
                return back()
                    ->withErrors(['captcha' => 'Please complete the security check'])
                    ->withInput($request->except('password'));
            }
        }

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

        if (app()->environment('production')) {
            if (!Hash::check(env('SALT_PREFIX') . $request->Password . env('SALT_SUFFIX'), $user->Password)) {
                return back()->with('error', "Password Anda Salah");
            }
        }

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
            

            $userAccess = DB::table('N_EMI_LAB_Page_Access_2 as pa')
                ->join('N_EMI_LAB_Role_Menu_Access as rma', 'rma.Id_Page_Access', '=', 'pa.Id_Page_Access')
                ->join('N_EMI_LAB_Klasifikasi_Aksi as ka', 'ka.Id_Klasifikasi_Actions', '=', 'rma.Id_Aksi') 
                ->leftJoin('N_EMI_LAB_Menus as m', 'm.Id_Menu', '=', 'pa.Id_Menu')
                ->where('pa.Id_User', $userId)
                ->where('rma.Flag_Diizinkan', 'Y')
                ->select(
                    'pa.Id_Page_Access',
                    'm.Nama_Menu as Jenis_page',
                    'ka.Nama_Aksi',
                    'm.Nama_Menu',
                    'm.Nama_Menu as Nama_Header',
                    'm.Icon_Menu',
                    'm.Url_Menu',
                    'pa.Urutan_Menu'
                )
                ->orderBy('pa.Urutan_Menu', 'ASC')
                ->get();

            $permissions = [];
            $permissionLabels = [];
            $pageAccessIds = [];
            $firstRedirectUrl = ''; 

           foreach ($userAccess as $index => $access) {
                // $page menyimpan nama asli yang ada spasinya (contoh: "Finalisasi Trial Produksi")
                $page = $access->Jenis_page;
                
                // $permissionKey mengubah spasi jadi underscore (contoh: "Finalisasi_Trial_Produksi")
                $permissionKey = str_replace(' ', '_', $page);

                $url = Str::start($access->Url_Menu, '/');

                // --- BAGIAN PERMISSIONS (GUNAKAN $permissionKey) ---
                if (!isset($permissions[$permissionKey])) {
                    $permissions[$permissionKey] = [];
                }
                if (!in_array($access->Nama_Aksi, $permissions[$permissionKey])) {
                    $permissions[$permissionKey][] = $access->Nama_Aksi;
                }

                // --- BAGIAN LABEL/UI (TETAP GUNAKAN $page) ---
                if (!isset($permissionLabels[$page])) {
                    $permissionLabels[$page] = [
                        'nama_menu'   => $access->Nama_Menu,
                        'nama_header' => $access->Nama_Header,
                        'icon'        => $access->Icon_Menu,
                        'url'         => $url,
                    ];
                }

                if (empty($firstRedirectUrl)) {
                    $firstRedirectUrl = $url;
                }

                if (!in_array($access->Id_Page_Access, $pageAccessIds)) {
                    $pageAccessIds[] = $access->Id_Page_Access; 
                }
            }

            if (empty($permissions)) {
                Auth::logout();
                return back()->with('error', "Anda belum memiliki hak akses menu. Silahkan hubungi Administrator.");
            }

            $permissionKonten = [];

            if (!empty($pageAccessIds)) {
                $userKontenAccess = DB::table('N_EMI_LAB_Role_Konten_Access as rka')
                    ->join('N_EMI_LAB_Page_Access_2 as pa', 'rka.Id_Page_Access', '=', 'pa.Id_Page_Access')
                    ->leftJoin('N_EMI_LAB_Menus as m', 'm.Id_Menu', '=', 'pa.Id_Menu')
                    ->leftJoin('N_EMI_LAB_Jenis_Analisa as ja', 'rka.Id_Jenis_Analisa', '=', 'ja.id')
                    ->whereIn('rka.Id_Page_Access', $pageAccessIds)
                    ->where('rka.Flag_Diizinkan', 'Y')
                    ->select(
                        'm.Nama_Menu as Jenis_page',
                        'rka.Id_Jenis_Analisa',
                        'rka.Kategori',
                        'rka.Flag_Diizinkan',
                        'ja.Jenis_Analisa as Nama_Analisa'
                    )
                    ->get();

                foreach ($userKontenAccess as $konten) {
                    $page = $konten->Jenis_page;

                    if (!isset($permissionKonten[$page])) {
                        $permissionKonten[$page] = [];
                    }

                    if ($konten->Id_Jenis_Analisa) {
                        $permissionKonten[$page][] = [
                            'id_jenis_analisa' => $konten->Id_Jenis_Analisa,
                            'nama_analisa'     => $konten->Nama_Analisa ?? null,
                            'flag'             => $konten->Flag_Diizinkan
                        ];
                    } elseif ($konten->Kategori) {
                        $permissionKonten[$page][] = [
                            'kategori' => $konten->Kategori,
                            'flag'     => $konten->Flag_Diizinkan
                        ];
                    }
                }
            }

            Session::put('user_permissions', [
                'id'               => $user->UserId,
                'username'         => $user->UserId,
                'nama'             => $user->Nama ?? null,
                'permissions'      => $permissions,
                'permission_label' => $permissionLabels,
                'permission_konten'=> $permissionKonten
            ]);
            // dd($firstRedirectUrl, Session::get('user_permissions'));

            $request->session()->save();
            return redirect($firstRedirectUrl)->with('success', 'Login berhasil!');

        } catch (\Exception $e) {
            Log::channel('AuthController')->error('Login Error: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return back()->with('error', "Terjadi Kesalahan Sistem.");
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
