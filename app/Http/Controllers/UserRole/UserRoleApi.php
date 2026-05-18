<?php

namespace App\Http\Controllers\UserRole;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserRoleApi extends Controller
{
    public function getList(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $page   = max(1, (int) $request->query('page', 1));
            $limit  = max(1, (int) $request->query('limit', 15));
            $offset = ($page - 1) * $limit;

            $query = DB::table('N_EMI_LAB_User_Roles as ur')
                ->join('N_EMI_LAB_Users as u', 'ur.Id_User', '=', 'u.UserId')
                ->join('N_EMI_LAB_Roles as r', 'ur.Id_Role', '=', 'r.Id_Role')
                ->select('ur.Id_User', 'ur.Id_Role', 'u.Nama', 'r.Nama_Role', 'r.Kode_Role', 'r.Deskripsi');

            if ($search !== '') {
                $like = '%' . strtolower($search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(u.Nama) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(r.Nama_Role) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(ur.Id_User) LIKE ?', [$like]);
                });
            }

            $total = $query->count();
            $data  = (clone $query)->offset($offset)->limit($limit)->get();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'data'    => $data,
                'meta'    => [
                    'page'       => $page,
                    'limit'      => $limit,
                    'total'      => $total,
                    'total_page' => (int) ceil($total / $limit) ?: 1,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('UserRoleApi@getList: ' . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function getStats()
    {
        try {
            $totalUsers       = DB::table('N_EMI_LAB_User_Roles')->distinct('Id_User')->count('Id_User');
            $totalRoles       = DB::table('N_EMI_LAB_Roles')->where('Flag_Aktif', 'Y')->count();
            $totalAssignments = DB::table('N_EMI_LAB_User_Roles')->count();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'data'    => [
                    'total_users'       => $totalUsers,
                    'total_roles'       => $totalRoles,
                    'total_assignments' => $totalAssignments,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('UserRoleApi@getStats: ' . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function getOptionUsers()
    {
        try {
            $data = DB::table('N_EMI_LAB_Users')
                ->where('Kode_Perusahaan', '001')
                ->select('UserId', 'Nama')
                ->orderBy('Nama')
                ->get();

            return response()->json(['success' => true, 'status' => 200, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('UserRoleApi@getOptionUsers: ' . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function getOptionRoles()
    {
        try {
            $data = DB::table('N_EMI_LAB_Roles')
                ->where('Flag_Aktif', 'Y')
                ->select('Id_Role', 'Nama_Role', 'Kode_Role', 'Deskripsi')
                ->orderBy('Nama_Role')
                ->get();

            return response()->json(['success' => true, 'status' => 200, 'data' => $data]);
        } catch (\Exception $e) {
            Log::error('UserRoleApi@getOptionRoles: ' . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function getGroupedList(Request $request)
    {
        try {
            $search = $request->input('search', '');

            $query = DB::table('N_EMI_LAB_User_Roles as ur')
                ->join('N_EMI_LAB_Users as u', 'ur.Id_User', '=', 'u.UserId')
                ->join('N_EMI_LAB_Roles as r', 'ur.Id_Role', '=', 'r.Id_Role')
                ->select('ur.Id_User', 'u.Nama', 'ur.Id_Role', 'r.Kode_Role', 'r.Nama_Role', 'r.Deskripsi')
                ->orderBy('u.Nama');

            if ($search !== '') {
                $like = '%' . strtolower($search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereRaw('LOWER(u.Nama) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(r.Nama_Role) LIKE ?', [$like]);
                });
            }

            $rows = $query->get();

            $grouped = [];
            foreach ($rows as $row) {
                if (!isset($grouped[$row->Id_User])) {
                    $grouped[$row->Id_User] = [
                        'Id_User' => $row->Id_User,
                        'Nama'    => $row->Nama,
                        'roles'   => [],
                    ];
                }
                $grouped[$row->Id_User]['roles'][] = [
                    'Id_Role'   => $row->Id_Role,
                    'Kode_Role' => $row->Kode_Role,
                    'Nama_Role' => $row->Nama_Role,
                    'Deskripsi' => $row->Deskripsi,
                ];
            }

            return response()->json([
                'success' => true,
                'data'    => array_values($grouped),
            ]);
        } catch (\Exception $e) {
            Log::error('UserRoleApi@getGroupedList: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'Id_Users'   => 'required|array|min:1',
            'Id_Users.*' => 'required|string',
            'Id_Roles'   => 'required|array|min:1',
            'Id_Roles.*' => 'required|integer',
        ]);

        DB::beginTransaction();
        try {
            $inserted = 0;
            $skipped  = 0;

            foreach ($request->Id_Users as $userId) {
                if (!DB::table('N_EMI_LAB_Users')->where('UserId', $userId)->exists()) {
                    continue;
                }
                foreach ($request->Id_Roles as $roleId) {
                    $exists = DB::table('N_EMI_LAB_User_Roles')
                        ->where('Id_User', $userId)
                        ->where('Id_Role', $roleId)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    DB::table('N_EMI_LAB_User_Roles')->insert([
                        'Id_User' => $userId,
                        'Id_Role' => $roleId,
                    ]);
                    $inserted++;
                }
            }

            DB::commit();

            if ($inserted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Semua kombinasi yang dipilih sudah ada sebelumnya.',
                ], 422);
            }

            $msg = "Berhasil menambahkan {$inserted} penetapan role.";
            if ($skipped > 0) {
                $msg .= " {$skipped} kombinasi dilewati karena sudah ada.";
            }

            return response()->json(['success' => true, 'status' => 201, 'message' => $msg], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UserRoleApi@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'Id_User' => 'required|string',
            'Id_Role' => 'required|integer',
        ]);

        $exists = DB::table('N_EMI_LAB_User_Roles')
            ->where('Id_User', $request->Id_User)
            ->where('Id_Role', $request->Id_Role)
            ->exists();

        if (!$exists) {
            return response()->json(['success' => false, 'status' => 404, 'message' => 'Data tidak ditemukan.'], 404);
        }

        DB::beginTransaction();
        try {
            DB::table('N_EMI_LAB_User_Roles')
                ->where('Id_User', $request->Id_User)
                ->where('Id_Role', $request->Id_Role)
                ->delete();

            DB::commit();
            return response()->json(['success' => true, 'status' => 200, 'message' => 'Role berhasil dihapus dari pengguna.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UserRoleApi@destroy: ' . $e->getMessage());
            return response()->json(['success' => false, 'status' => 500, 'message' => 'Terjadi Kesalahan'], 500);
        }
    }
}
