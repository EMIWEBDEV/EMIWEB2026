@extends('layouts.master2')


@section('title', 'Binding Mac Adress Komputer - PT.Evo Nusa Bersaudara')

@section('content')
    <div class="container-fluid px-0">
        <div class="card shadow-sm border-0 w-100">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Binding Komputer Keys
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Mesin Berdasarkan Komputer Keys Komputer PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="d-flex justify-content-center justify-content-lg-start">
                    <a href="{{ route('bidingidentity.create') }}" class="btn btn-primary" type="button">
                        + Tambah Binding Mesin
                    </a>
                </div>

                <div class="col-12 mt-3">
                    <div class="list-group shadow">
                        @foreach ($groupedData as $item)
                            {{-- {{ dd($item) }} --}}
                            <a href="{{ route('bidingidentity.detail', $item->Id_Identity) }}"
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <i class="bi bi-hdd-network text-primary me-2"></i>
                                    <div class="fw-bold text-dark">{{ $item->Keterangan ?? 'Tanpa Keterangan' }}</div>
                                    <div class="small text-muted">{{ $item->Computer_Keys }}</div>
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
@endsection
