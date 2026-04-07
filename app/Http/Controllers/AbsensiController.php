<?php

namespace App\Http\Controllers;

use Log;
use App\Models\Lembur;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AbsensiController extends Controller
{
    //
    public function indexTest()
    {
        return view('test');
    }

    public function indexLembur()
    {
       $userId = 1; // Ganti ini dengan auth()->id() jika ingin ambil user login

    $data = DB::table('KPI_lemburs_Test')
        ->where('user_id', $userId)
        ->orderByDesc('no_transaksi')
        ->get()
        ->map(function ($item) {
            // Pastikan tanggal valid dan bisa diparse
            try {
                $tanggalLembur = Carbon::parse($item->tanggal)->startOfDay();
            } catch (\Exception $e) {
                $item->expired = true; // Jika gagal parsing, anggap expired
                return $item;
            }

            $now = Carbon::now()->startOfDay();
            $batasValid = $tanggalLembur->copy()->addDay();

            $item->expired = $now->gt($batasValid); // true jika sudah lewat batas

            return $item;
        });

    return view("lembur.index", ['data' => $data]);
    }

    
    public function createLembur(){
        return inertia('vue/lembur/LemburForm');
    }
    public function indexLemburSesudah(){
        return inertia('vue/lembur/LemburSesudahForm');
    }

    public function show($filename)
    {
        // Path file di storage/app/public/lembur/bukti-dukung/
        $path = 'public/lembur/bukti-dukung/' . $filename;

        if (!Storage::exists($path)) {
            abort(404);
        }

        // Ambil file dari storage
        $file = Storage::get($path);

        // Ambil mime type file (jpeg, png, dll)
        $mimeType = Storage::mimeType($path);

        return response($file, 200)->header('Content-Type', $mimeType);
    }
    public function showBuktiSelesaiConfirmed($filename)
    {
        // Path file di storage/app/public/lembur/bukti-dukung/
        $path = 'public/lembur/bukti-dukung-confirm/' . $filename;

        if (!Storage::exists($path)) {
            abort(404);
        }

        // Ambil file dari storage
        $file = Storage::get($path);

        // Ambil mime type file (jpeg, png, dll)
        $mimeType = Storage::mimeType($path);

        return response($file, 200)->header('Content-Type', $mimeType);
    }

   public function lemburSubmissionStore(Request $request)
   {
         // validasi inputan ~ by frans
        $request->validate([
            'tanggal'       => 'required|date',
            'jam_mulai'     => 'required',
            'jam_selesai'   => 'required',
            'keterangan'    => 'required',
            'bukti_dukung'  => 'required|file|mimes:jpg,jpeg,png',
        ], [
            'tanggal.required'      => 'Tanggal lembur harus diisi',
            'jam_mulai.required'    => 'Jam mulai tidak boleh kosong',
            'jam_selesai.required'  => 'Jam selesai tidak boleh kosong',
            'keterangan.required'   => 'Alasan lembur tidak boleh kosong',
            'bukti_dukung.required'   => 'Bukti Dukung tidak boleh kosong',
        ]);


        //validasi checking lastTicket ~ by frans
        $prefix = 'FPL-';
        $datePart = Carbon::now()->format('d/m'); 

        // Cari no_transaksi terakhir berdasarkan urutan 4 digit terakhir
        $lastRecord = DB::table('KPI_lemburs_Test')
            ->orderBy('no_transaksi', 'desc')
            ->first();

        if (!$lastRecord) {
            $newNumber = 1;
        } else {
            $lastNoTrans = $lastRecord->no_transaksi;
            $lastNumberStr = substr($lastNoTrans, -4); // ambil 4 digit terakhir
            $lastNumber = (int) $lastNumberStr;
            $newNumber = $lastNumber + 1;
        }

        $newNumberStr = str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        $newNoTransaksi = $prefix . $datePart . '-' . $newNumberStr;

        // mengatasi serangan XSS
        // sanitasi inputan dan membebaskan seluruh tag html atau script yang tidak di inginkan (mengatasi susupan malware) 
        $cleanContent = strip_tags($request->input('keterangan'));

        // Validasi jam selesai tidak boleh lebih awal dari jam mulai
        $jamMulai = Carbon::createFromFormat('H:i', $request->jam_mulai);
        $jamSelesai = Carbon::createFromFormat('H:i', $request->jam_selesai);

        if ($jamSelesai->lt($jamMulai)) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => "Jam selesai tidak boleh lebih awal dari jam mulai",
            ], 400);
        }


        DB::beginTransaction();

        try {
           
            $data = [
                'no_transaksi'  => $newNoTransaksi,      
                'tanggal'       => $request->tanggal,
                'jam_mulai'     => $request->jam_mulai,
                'jam_selesai'   => $request->jam_selesai,
                'keterangan'    => $cleanContent,
                'user_id'       => 1,            
            ];

         
            if ($request->hasFile('bukti_dukung')) {
                $file         = $request->file('bukti_dukung');
                $noTransaksi = str_replace('/', '', $data['no_transaksi']);
                $tanggal    = date('Y-m-d');
                $randomStr = Str::random(10);
                $originalExtension = $file->getClientOriginalExtension();
                $filename     = $noTransaksi. '_'. $tanggal . '-' . $randomStr. '.' . $originalExtension;

                // Simpan ke storage/app/public/lembur/bukti-dukung
                $file->storeAs('public/lembur/bukti-dukung', $filename);

                // Masukkan nama file ke array data
                $data['bukti_dukung'] = $filename;
            }

            Lembur::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => "Data Berhasil Disimpan"
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            dd();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => [
                    'error' => "Error Terjadi Kesalahan, Lonjakan Traffic",
                    'eror_detail' => $e->getMessage(),
                ]
            ], 500);
            
        }
   }
    public function lemburDoneConfirmedStore(Request $request, $id)
    {
       
        $request->validate([
            'keterangan_konfirmasi' => 'required',
        ], [
            'keterangan_konfirmasi.required' => 'Deskripsi Konfirmasi Selesai Tidak Boleh Kosong !',
        ]);


        // Bersihkan konten dari tag HTML
        $cleanContent = strip_tags($request->input('keterangan_konfirmasi'));

        DB::beginTransaction();
        try {
             $lembur = DB::table('KPI_lemburs_Test')
                ->where('id', $id)
                ->first();

            $dataToUpdate = [
                'keterangan_konfirmasi' => $cleanContent,
                'updated_at' => now(),
            ];

            // Jika ada gambar bukti_dukung dikirim
            if ($request->filled('bukti_dukung')) {
                $base64Image = $request->input('bukti_dukung');

                // Cek format base64 dan ekstrak datanya
                if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
                    $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                    $type = strtolower($type[1]);

                    if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                        throw new \Exception('Format gambar tidak didukung.');
                    }

                    $imageData = base64_decode($imageData);
                    if ($imageData === false) {
                        throw new \Exception('Base64 decode gagal.');
                    }
                    $noTransaksi = str_replace('/', '', $lembur->no_transaksi) ?? "-";
                    $tanggal = date('Y-m-d');
                    $randomStr = Str::random(10);
                    // date('Y-m-d H:i:s', strtotime());
                    $filename = $noTransaksi . '_' . $tanggal . '_' . $randomStr. '.' .$type;
                    Storage::put("public/lembur/bukti-dukung-confirm/{$filename}", $imageData);

                    $dataToUpdate['bukti_pengerjaan_selesai'] = $filename;
                } else {
                    throw new \Exception('Format base64 tidak valid.');
                }
            }

            DB::table('KPI_lemburs_Test')->where('id', $id)->update($dataToUpdate);

            DB::commit();

            return redirect()->route("absensi.lembur")
                ->with('success', "Berhasil Mengirimkan konfirmasi selesai mengerjakan lembur");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', "Terjadi kesalahan: " . $e->getMessage());
        }
    }


   

}
