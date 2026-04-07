<?php

namespace App\Exports;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Concerns\FromCollection;

class CustomerExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $user = Session::get('access_token');
        $role = Session::get('user.role_id');

        // dd($user);
        $title = 'Customer';
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $user
        ])->post(env('SRV_URL') . 'customer/aktif');
        $data = $response['data']['data'];
        // dd(collect($data));
        return collect($data);
    }
}
