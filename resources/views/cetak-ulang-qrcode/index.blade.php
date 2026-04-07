@extends('layouts.master2')


@section('title', 'Cetak Ulang QRCODE - PT.Evo Nusa Bersaudara')

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
                        History Transaksi PO Sampel
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Seluruh Transaksi Production Order PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="d-flex justify-content-center justify-content-lg-start mb-3">
                    <a href="/home" class="btn btn-danger">
                        <i class="fas fa-arrow-left"></i> kembali
                    </a>

                </div>
                <div class="col-12 mt-3">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle" id="myTable">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>No Transaksi</th>
                                    <th>Split PO</th>
                                    <th>No Batch</th>
                                    <th>Nama Mesin</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $no = 1; @endphp
                                @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $no++ }}</td>
                                        <td>
                                            {{ $item->Tanggal }} {{ \Carbon\Carbon::parse($item->Jam)->format('H:i:s') }}
                                        </td>
                                        <td>{{ $item->No_Sampel ?? '-' }}</td>
                                        <td>{{ $item->No_Split_Po ?? '-' }}</td>
                                        <td>{{ $item->No_Batch ?? '-' }}</td>
                                        <td>{{ $item->Nama_Mesin ?? '-' }}</td>
                                        <td>
                                            <form action="{{ route('quisy.cetakUlangQrCodestore', [$item->No_Sampel,$item->Id_Master_Mesin]) }}"
                                                method="POST">
                                                @csrf
                                                <button class="btn btn-primary">Cetak QrCode</button>
                                            </form>
                                        </td>
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
