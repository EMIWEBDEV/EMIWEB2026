@extends('layouts.master2')


@section('title', 'Daftar Mac Adress Komputer - PT.Evo Nusa Bersaudara')

@section('content')
    @push('css')
        <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
        <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
            type="text/css" />
        <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css"  />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/frappe-gantt@latest/dist/frappe-gantt.css">
        <link rel="stylesheet" href="https://unpkg.com/frappe-gantt/dist/frappe-gantt.css">
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


            @keyframes shake {
                0% {
                    transform: translateX(0);
                }

                20% {
                    transform: translateX(-5px);
                }

                40% {
                    transform: translateX(5px);
                }

                60% {
                    transform: translateX(-5px);
                }

                80% {
                    transform: translateX(5px);
                }

                100% {
                    transform: translateX(0);
                }
            }

            .shake {
                animation: shake 0.3s ease-in-out;
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
    @endpush


    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Master Komputer Key
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Identity Key Komputer PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="d-flex justify-content-center justify-content-lg-start">
                    <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#offcanvasRight" aria-controls="offcanvasRight">
                        + Tambah Identity Komputer
                    </button>
                </div>
                <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight"
                    aria-labelledby="offcanvasRightLabel">
                    <div class="offcanvas-header">
                        <h5 id="offcanvasRightLabel" class="mb-0">Penambahan Daftar Key Identity Komputer <i
                                class="fas fa-desktop"></i></h5>
                        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"
                            aria-label="Close"></button>
                    </div>
                    <div class="offcanvas-body">
                        <form id="identityForm">
                            @csrf
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label for="Computer_Keys" class="form-label fw-semibold">
                                        Key Komputer <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" name="Computer_Keys" id="Computer_Keys"
                                            class="form-control @error('Computer_Keys') is-invalid shake @enderror"
                                            placeholder="Tekan Generate untuk membuat key"
                                            value="{{ old('Computer_Keys', $generatedKey ?? '') }}" readonly required
                                            onkeydown="return false" onpaste="return false" oncut="return false"
                                            oncontextmenu="return false">
                                        <button type="button" class="btn btn-outline-secondary"
                                            onclick="generateKey()">Generate Key</button>
                                    </div>
                                    @error('Computer_Keys')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label for="Keterangan" class="form-label fw-semibold">
                                        Keterangan <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                        class="form-control  @error('Keterangan') is-invalid shake @enderror"
                                        name="Keterangan" id="Keterangan" placeholder="Contoh: Komputer A"
                                        value="{{ old('Keterangan') }}">
                                    @error('Keterangan')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send-check me-2"></i>
                                        Submit Form
                                    </button>
                                </div>
                            </div>
                        </form>


                    </div>
                </div>
                <div class="col-12 mt-3">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle" id="myTable">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Kode Perusahaan</th>
                                    <th>Komputer Key</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $no = 1; @endphp
                                @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $no++ }}</td>
                                        <td>{{ $item->Kode_Perusahaan ?? '-' }}</td>
                                        <td>
                                            @if ($item->Computer_Keys)
                                                <span class="badge bg-secondary"
                                                    id="key-badge-{{ $loop->index }}">••••••••</span>
                                                <button type="button" class="btn btn-sm btn-outline-primary ms-2"
                                                    onclick="toggleKey({{ $loop->index }}, '{{ $item->Computer_Keys }}')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->Keterangan ?? '-' }}</td>
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
                pageLength: 50,
                lengthMenu: [50, 100],
            });
        });
    </script>

    <script>
        function toggleKey(index, keyValue) {
            const badge = document.getElementById('key-badge-' + index);
            if (badge.innerText === '••••••••') {
                badge.innerText = keyValue;
            } else {
                badge.innerText = '••••••••';
            }
        }
    </script>

    <script>
        async function generateKey() {
            try {
                const response = await fetch("{{ route('identity.generate') }}", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                        "Accept": "application/json"
                    }
                });

                if (!response.ok) throw new Error("Gagal generate key");

                const data = await response.json();
                document.getElementById("Computer_Keys").value = data.key;
            } catch (error) {
                Swal.fire("Error", error.message, "error");
            }
        }
        document.getElementById('identityForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch("{{ route('identity.store') }}", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                        "Accept": "application/json"
                    },
                    body: formData
                });

                const result = await response.json();

                if (!response.ok || result.status !== 'success') {
                    throw new Error(result.message || "Gagal menyimpan data");
                }

                localStorage.setItem('SSID_EVO', result.computer_key);

                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: result.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });

            } catch (error) {
                Swal.fire("Error", error.message, "error");
            }
        });
    </script>
@endpush
