<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class ExpeditionController extends Controller
{
    public function expedition()
    {

        $token = session()->get('access_token');
        $users =  session()->get('user');
        $AuthSP = $users['Auth_SP'];
        $username = $users['username'];
        $KodeSupplier = $users['Kode_Supplier'];
        $Lokasi = $users['Lokasi'];

        // if (Gate::denies('create-penawaran-expedition')) {
        //     abort(404);
        // } 

        $params = [
            "kode_perusahaan" => env('KODE_PERUSAHAAN'),
            "id_user" => $username,
            "auth_sp" => $AuthSP,
            "version" => env('VERSION'),
            "nama_api" => env('NAMA_API_EXPEDITION'),
            "locale" => env('LOCALE'),
        ];
        // First API call
        function fetchData($endpoint, $token, $params)
        {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->post(env('SRV_URL') . $endpoint, $params);

            if ($response->successful() && isset($response['data']['data'])) {
                return $response['data']['data'];
            } else {
                return [];
            }
        }

        $kota = fetchData('/expedition/get_kota', $token, $params);
        if ($kota instanceof \Illuminate\Http\JsonResponse) {
            return $kota;
        }
        $kode_biaya = fetchData('/expedition/get_kode_biaya', $token, $params);
        if ($kode_biaya instanceof \Illuminate\Http\JsonResponse) {
            return $kode_biaya;
        }
        $perusahaan = fetchData('/expedition/get_perusahaan', $token, $params);
        if ($perusahaan instanceof \Illuminate\Http\JsonResponse) {
            return $perusahaan;
        }

        $kendaraan = fetchData('/expedition/get_kendaraan', $token, $params);
        if ($kendaraan instanceof \Illuminate\Http\JsonResponse) {
            return $kendaraan;
        }

        $jenisEkspedisi = fetchData('/expedition/get_jenisEkspedisi', $token, $params);
        if ($jenisEkspedisi instanceof \Illuminate\Http\JsonResponse) {
            return $jenisEkspedisi;
        }
        $menuArray = [];
        $menu = fetchData('/get_role_menu', $token, $params);
        if ($menu instanceof \Illuminate\Http\JsonResponse) {
            return $menu;
        }
        // dd($perusahaan);
        foreach ($menu as $user_role) {
            // Akses Menu menggunakan indeks array
            if (isset($user_role['Menu'])) {
                // Memproses Menu yang ada dalam array
                $menuArray[$user_role['Menu']][] = $user_role['username'];
            }
        }
        if (!array_key_exists('create-penawaran-expedition', $menuArray)) {
            abort(404, 'Not Found');
        }

        $faktur = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ])->post(env('SRV_URL') . '/faktur/get_expedition');


        $dataFaktur = [];

        if ($faktur) {
            $dataFaktur = $faktur->body();
        }

        // dd($kode_biaya);

        return inertia('vue/Expedition', [
            'Token' => $token,
            'AuthSP' => $AuthSP,
            'UserId' => $username,
            'KodeSupplier' => $KodeSupplier,
            'Lokasi' => $Lokasi,
            'dataKota' => $kota,
            'dataKendaraan' => $kendaraan,
            'dataJenisEkspedisi' => $jenisEkspedisi,
            'dataPerusahaan' => $perusahaan,
            'dataFaktur' => $dataFaktur,
            'kode_biaya' => $kode_biaya
        ]);


        // if ($faktur->successful()) {
        //     return inertia('vue/Expedition', [
        //         'Token' =>$token,
        //         'AuthSP' =>$AuthSP,
        //         'UserId' =>$username,
        //         'KodeSupplier' =>$KodeSupplier,
        //         'Lokasi' =>$Lokasi,
        //         'dataKota' => $kota,
        //         'dataKendaraan' => $kendaraan,
        //         'dataJenisEkspedisi' => $jenisEkspedisi,
        //         'dataPerusahaan' => $perusahaan,
        //         'dataFaktur' => $faktur->body(), 
        //     ]);
        // } else {
        //     return response()->json([
        //         'error' => 'Failed to fetch data from ' . 'faktur/get_bahan_baku',
        //         'details' => $faktur->body()
        //     ], $faktur->status());
        // }
    }

    public function approvalExpedition()
    {
        $token = session()->get('access_token');
        $users =  session()->get('user');
        $AuthSP = $users['Auth_SP'];
        $username = $users['username'];
        $Lokasi = $users['Lokasi'];
        $role = $users['role_id'];

        if (empty($username)) { // Ensure $role is an integer for type-safety
            abort(404);
        }


        // if (Gate::denies('list-approval-penawaran-expedition')) {
        //     abort(403,'Under Maintenance'); // Use 403 for forbidden access
        // }

        // dd($authSP);
        $params = [
            "kode_perusahaan" => env('KODE_PERUSAHAAN'),
            "id_user" => $username,
            "auth_sp" => $AuthSP,
            "version" => env('VERSION'),
            "nama_api" => env('NAMA_API_EXPEDITION'),
            "locale" => env('LOCALE'),
        ];
        // First API call
        function fetchData($endpoint, $token, $params)
        {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->post(env('SRV_URL') . $endpoint, $params);

            if ($response->successful() && isset($response['data']['data'])) {
                return $response['data']['data'];
            } else {
                return [];
            }
        }

        $approval = fetchData('/approval_expedition/get_approval_admin', $token, $params);
        if ($approval instanceof \Illuminate\Http\JsonResponse) {
            return $approval;
        }
        $menuArray = [];
        $menu = fetchData('/get_role_menu', $token, $params);
        if ($menu instanceof \Illuminate\Http\JsonResponse) {
            return $menu;
        }
        foreach ($menu as $user_role) {
            // Akses Menu menggunakan indeks array
            if (isset($user_role['Menu'])) {
                // Memproses Menu yang ada dalam array
                $menuArray[$user_role['Menu']][] = $user_role['username'];
            }
        }
        if (!array_key_exists('create-penawaran-expedition', $menuArray)) {
            abort(404, 'Not Found');
        }
        // dd($approval);
        return inertia('vue/ApprovalExpedition', ['dataApproval' => $approval]);
    }

    public function approvalExpeditionUser()
    {
        $token = session()->get('access_token');
        $users =  session()->get('user');
        $AuthSP = $users['Auth_SP'];
        $username = $users['username'];
        $KodeSupplier = $users['Kode_Supplier'];
        $Lokasi = $users['Lokasi'];
        // if (Gate::denies('list-approval-penawaran-expedition')) {
        //     abort(403,'Under Maintenance'); // Use 403 for forbidden access
        // }
        // dd($authSP);
        $params = [
            "kode_perusahaan" => env('KODE_PERUSAHAAN'),
            "id_user" => $username,
            "auth_sp" => $AuthSP,
            "version" => env('VERSION'),
            "nama_api" => env('NAMA_API_EXPEDITION'),
            "locale" => env('LOCALE'),
        ];
        // First API call
        function fetchData($endpoint, $token, $params)
        {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->post(env('SRV_URL') . $endpoint, $params);

            if ($response->successful() && isset($response['data']['data'])) {
                return $response['data']['data'];
            } else {
                return [];
                // return response()->json([
                //     'error' => 'Failed to fetch data from ' . $endpoint,
                //     'details' => $response->body()
                // ], $response->status());
            }
        }

        $approval = fetchData('/approval_expedition/get_approval_user', $token, $params);
        if ($approval instanceof \Illuminate\Http\JsonResponse) {
            return $approval;
        }
        $menuArray = [];
        $menu = fetchData('/get_role_menu', $token, $params);
        if ($menu instanceof \Illuminate\Http\JsonResponse) {
            return $menu;
        }
        foreach ($menu as $user_role) {
            // Akses Menu menggunakan indeks array
            if (isset($user_role['Menu'])) {
                // Memproses Menu yang ada dalam array
                $menuArray[$user_role['Menu']][] = $user_role['username'];
            }
        }
        if (!array_key_exists('create-penawaran-expedition', $menuArray)) {
            abort(404, 'Not Found');
        }

        return inertia('vue/ApprovalExpeditionUser', ['dataApproval' => $approval]);
    }

    public function detailApprovalExpedition()
    {
        $token = session()->get('access_token');
        $users =  session()->get('user');
        $AuthSP = $users['Auth_SP'];
        $username = $users['username'];
        $Lokasi = $users['Lokasi'];
        $role = $users['role_id'];
        // if (Gate::denies('list-approval-penawaran-expedition')) {
        //     abort(403,'Under Maintenance'); // Use 403 for forbidden access
        // }
        // dd($users);

        if (!$username) {
            abort(404);
        }

        return inertia('vue/DetailApprovalExpedition', [
            'Token' => $token,
            'AuthSP' => $AuthSP,
            'UserId' => $username,
            'Lokasi' => $Lokasi,

        ]);
    }

    public function detailApprovalExpeditionUser(Request $request)
    {
        $nomorPenawaran = $request->input('NoPenawaran');
        $noPenawaran = str_replace('@', '/', $nomorPenawaran);
        $token = session()->get('access_token');
        $users =  session()->get('user');
        $AuthSP = $users['Auth_SP'];
        $username = $users['username'];
        $Lokasi = $users['Lokasi'];
        // dd($nomorPenawaran);
        // if (Gate::denies('list-approval-penawaran-expedition')) {
        //     abort(403,'Under Maintenance'); // Use 403 for forbidden access
        // }
        $params = [
            "kode_perusahaan" => env('KODE_PERUSAHAAN'),
            "id_user" => $username,
            "auth_sp" => $AuthSP,
            "version" => env('VERSION'),
            "nama_api" => env('NAMA_API_EXPEDITION'),
            "locale" => env('LOCALE'),
            "noPenawaran" => $noPenawaran,
        ];

        function fetchData($endpoint, $token, $params)
        {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->post(env('SRV_URL') . $endpoint, $params);

            if ($response->successful() && isset($response['data']['data'])) {
                return $response['data']['data'];
            } else {
                return [];
                // return response()->json([
                //     'error' => 'Failed to fetch data from ' . $endpoint,
                //     'details' => $response->body()
                // ], $response->status());
            }
        }

        $detailData = fetchData('/approval_expedition/get_approval_detail', $token, $params);
        if ($detailData instanceof \Illuminate\Http\JsonResponse) {
            return $detailData;
        }
        $menuArray = [];
        $menu = fetchData('/get_role_menu', $token, $params);
        if ($menu instanceof \Illuminate\Http\JsonResponse) {
            return $menu;
        }
        foreach ($menu as $user_role) {
            // Akses Menu menggunakan indeks array
            if (isset($user_role['Menu'])) {
                // Memproses Menu yang ada dalam array
                $menuArray[$user_role['Menu']][] = $user_role['username'];
            }
        }
        if (!array_key_exists('create-penawaran-expedition', $menuArray)) {
            abort(404, 'Not Found');
        }
        // dd($detailData);
        foreach ($detailData as $item) {
            if (!isset($item['Ekspedisi']) || $item['Ekspedisi'] !== $username) {
                abort(404);
            }
        }
        return inertia('vue/DetailApprovalExpeditionUser', [
            'dataDetail' => $detailData,
            'Token' => $token,
            'AuthSP' => $AuthSP,
            'UserId' => $username,
            'Lokasi' => $Lokasi,

        ]);
    }

    public function tambahApprovalExpedition()
    {
        $token = session()->get('access_token');
        $users =  session()->get('user');
        $AuthSP = $users['Auth_SP'];
        $username = $users['username'];
        $KodeSupplier = $users['Kode_Supplier'];
        $Lokasi = $users['Lokasi'];
        $params = [
            "kode_perusahaan" => env('KODE_PERUSAHAAN'),
            "id_user" => $username,
            "auth_sp" => $AuthSP,
            "version" => env('VERSION'),
            "nama_api" => env('NAMA_API_EXPEDITION'),
            "locale" => env('LOCALE'),
        ];
        // First API call
        function fetchData($endpoint, $token, $params)
        {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ])->post(env('SRV_URL') . $endpoint, $params);

            if ($response->successful() && isset($response['data']['data'])) {
                return $response['data']['data'];
            } else {
                return [];
            }
        }
        $status = fetchData('/approval_expedition/get_status', $token, $params);
        if ($status instanceof \Illuminate\Http\JsonResponse) {
            return $status;
        }
        $kota = fetchData('/expedition/get_kota', $token, $params);
        if ($kota instanceof \Illuminate\Http\JsonResponse) {
            return $kota;
        }
        $kendaraan = fetchData('/expedition/get_kendaraan', $token, $params);
        if ($kendaraan instanceof \Illuminate\Http\JsonResponse) {
            return $kendaraan;
        }
        $approval_compare = fetchData('/approval_expedition/get_approval_compare', $token, $params);
        if ($approval_compare instanceof \Illuminate\Http\JsonResponse) {
            return $approval_compare;
        }
        $faktur = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ])->post(env('SRV_URL') . '/faktur/get_approval_expedition');

        if ($faktur->successful()) {
            return inertia('vue/TambahApprovalExpedition', [
                'dataKendaraan' => $kendaraan,
                'dataKota' => $kota,
                'dataApproval' => $approval_compare,
                'dataStatus' => $status,
                'Token' => $token,
                'AuthSP' => $AuthSP,
                'UserId' => $username,
                'KodeSupplier' => $KodeSupplier,
                'Lokasi' => $Lokasi,
                'dataFaktur' => $faktur->body(),
            ]);
        } else {
            return response()->json([
                'error' => 'Failed to fetch data from ' . 'faktur/get_expedition',
                'details' => $faktur->body()
            ], $faktur->status());
        }
        return inertia('vue/TambahApprovalExpedition');
    }
}
