@extends('layouts.master')



@section('css')
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
    <!--datatable responsive css-->
    <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
        type="text/css" />
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css" />
@endsection

@section('content')

<div class="card">
        <h1 class="mt-4 ms-3">Detail Perusahaan</h1>
        <hr>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>Kode Perusahaan</th>
                                <td>{{ $detail[0]['Kode_Perusahaan'] }}</td>
                            </tr>
                            <tr>
                                <th>Negara</th>
                                <td>{{ $detail[0]['Negara'] }}</td>
                            </tr>
                            <tr>
                                <th>Provinsi</th>
                                <td>{{ $detail[0]['Provinsi'] }}</td>
                            </tr>
                            <tr>
                                <th>Kabupaten/Kota</th>
                                <td>{{ $detail[0]['Kabupaten_Kota'] }}</td>
                            </tr>
                            <tr>
                                <th>Status Pajak</th>
                                <td>{{ $detail[0]['Status_Pajak'] }}</td>
                            </tr>
                            <tr>
                                <th>NPWP</th>
                                <td>{{ $detail[0]['Npwp'] }}</td>
                            </tr>
                            <tr>
                                <th>Cabang</th>
                                <td>{{ $detail[0]['Cabang'] }}</td>
                            </tr>
                            <tr>
                                <th>Nama Nasabah</th>
                                <td>{{ $detail[0]['Nama_Nasabah'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="col-md-6">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>Jabatan</th>
                                <td>{{ $detail[0]['Jabatan'] }}</td>
                            </tr>
                            <tr>
                                <th>Cara Bayar</th>
                                <td>{{ $detail[0]['Cara_Bayar'] }}</td>
                            </tr>
                            <tr>
                                <th>Tempo</th>
                                <td>{{ $detail[0]['Tempo'] }}</td>
                            </tr>
                            <tr>
                                <th>Tipe Pembayaran</th>
                                <td>{{ $detail[0]['Tipe_Pembayaran'] }}</td>
                            </tr>
                            <tr>
                                <th>Jenis Ekspedisi</th>
                                <td>{{ $detail[0]['Jenis_Ekspedisi_2'] }}</td>
                            </tr>
                            <tr>
                                <th>Bank</th>
                                <td>{{ $detail[0]['Bank'] }}</td>
                            </tr>
                            <tr>
                                <th>No Rekening</th>
                                <td>{{ $detail[0]['No_Rekening'] }}</td>
                            </tr>
                            <tr>
                                <th>Swift Code</th>
                                <td>{{ $detail[0]['Swift_Code'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        
            <a href="/new/list_perusahaan" class="btn btn-primary mt-3">Kembali</a>
        </div>
    </div>


@endsection

@section('script')
    <script></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    <script src="{{ URL::asset('assets/libs/prismjs/prismjs.min.js') }}"></script>
    <script src="https://cdn.lordicon.com/libs/mssddfmo/lord-icon-2.1.0.js"></script>
    <script src="{{ URL::asset('assets/js/pages/modal.init.js') }}"></script>


    <script src="{{ URL::asset('assets/js/pages/datatables.init.js') }}"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="{{ URL::asset('assets/js/pages/form-wizard.init.js') }}"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ URL::asset('/assets/js/app.min.js') }}"></script>
@endsection
