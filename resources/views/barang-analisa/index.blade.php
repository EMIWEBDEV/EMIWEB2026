@extends('layouts.master2')


@section('title', 'Daftar Barang Analisa - PT.Evo Nusa Bersaudara')

@section('content')
    @push('css')
        <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
        <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
            type="text/css" />
        <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css"  />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@latest/dist/frappe-gantt.css">
        <link rel="stylesheet" href="https://unpkg.com/frappe-gantt/dist/frappe-gantt.css">
    @endpush


    <style>
        .offcanvas-end {
            width: 400px;
            /* Lebar offcanvas */
            box-shadow: -4px 0 12px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }

        .offcanvas-header {
            border-bottom: 1px solid #eaeaea;
        }

        .m-detail .form-text {
            margin-top: -8px !important;
        }


        /* Base Styles */
        .card {
            border-radius: 12px;
            overflow: hidden;
        }

        .divider {
            height: 2px;
            background: linear-gradient(90deg,
                    rgba(13, 110, 253, 0.1) 0%,
                    rgba(13, 110, 253, 0.5) 50%,
                    rgba(13, 110, 253, 0.1) 100%);
        }

        @media (max-width: 600px) {
            ..offcanvas-end {
                width: 100%;
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 767.98px) {
            .card-body {
                padding: 1.5rem !important;
            }


        }
    </style>


    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Barang Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Barang Analisa PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="mb-3">
                    <a href="{{ route('barangjenisanalis.create') }}" class="btn btn-primary" type="button">
                        + Tambah Barang Analisa Berdasarkan Barang
                    </a>
                </div>
                <div class="col-12 mt-3">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center" id="myTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode Barang</th>
                                    <th>Kode Analisa</th>
                                    <th>Jenis Analisa</th>
                                    <th>Nama Barang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $no = 1;
                                @endphp
                                @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $no++ }}</td>
                                        <td>{{ $item->kode_barang ?? '-' }}</td>
                                        <td>{{ $item->kode_analisa ?? '-' }}</td>
                                        <td>{{ $item->jenis_analisa ?? '-' }}</td>
                                        <td>{{ $item->nama_barang ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <script>
            Swal.fire({
                title: "Berhasil",
                text: "{{ session('success') }}",
                icon: "success"
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                title: "Ops...",
                text: "{{ session('error') }}",
                icon: "error"
            });
        </script>
    @endif
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
@push('js')
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

    <script>
        $(document).ready(function() {

            $('#myTable').DataTable({
                pageLength: 50, // default tampil 5 row
                lengthMenu: [50, 100],
            });
        });
    </script>
@endpush
