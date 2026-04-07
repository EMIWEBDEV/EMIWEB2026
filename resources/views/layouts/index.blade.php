@extends('layouts.master2')


@section('title', 'Jenis Analisa - PT.Evo Nusa Bersaudara')

@section('content')
    @push('css')
        <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
        <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
            type="text/css" />
        <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css"  />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@latest/dist/frappe-gantt.css">
        <link rel="stylesheet" href="https://unpkg.com/frappe-gantt/dist/frappe-gantt.css">
        <style>
            @media (max-width: 767.98px) {
                .card-body {
                    padding: 1.5rem !important;
                }


            }
        </style>
    @endpush

    <div class="container-fluid px-0">
        <div class="card shadow-sm border-0 w-100">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Master Jenis Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Jenis Analisa PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="d-flex justify-content-center justify-content-lg-start">
                    <a href="{{ route('jenisanalisa.create') }}" class="btn btn-primary" type="button">
                        + Tambah Jenis Analisa
                    </a>
                </div>

                <div class="col-12 mt-3">
                    <div class="list-group shadow">
                        @foreach ($groupedData as $item)
                            <a href="{{ route('jenisanalisa.show', $item->Jenis_Analisa) }}"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <i class="bi bi-hdd-network text-primary me-2"></i>
                                    <div class="fw-bold text-dark">{{ $item->Jenis_Analisa ?? 'Tanpa Keterangan' }}</div>
                                    <div class="small text-muted">{{ $item->Kode_Analisa }}</div>
                                    <div class="small text-muted">
                                       Memakai Perhitungan: @if ($item->Flag_Perhitungan === 'Y')
                                           <i class="fas fa-check" style="color: green;"></i>
                                           @else
                                           -
                                       @endif
                                    </div>
                                </div>
                                <span class="badge bg-primary rounded-pill">{{ $item->total_data }} Data</span>
                            </a>
                        @endforeach
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    @push('js')
        <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>

        <script>
            $(document).ready(function() {
                $('#myTable').DataTable({
                    pageLength: 50,
                    lengthMenu: [50, 100],
                });
            });
        </script>
    @endpush
@endsection
