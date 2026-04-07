@extends('layouts.master2')


@section('title', 'Binding Mac Adress Komputer - PT.Evo Nusa Bersaudara')

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
                        Kumpulan Nama Mesin
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Mesin Berdasarkan Computer Keys Komputer PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12 mt-3">
                    <div class="table-responsive">
                        <table class="table table-bordered text-center" id="myTable">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Mesin</th>
                                    <th>Seri Mesin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $no = 1;
                                @endphp
                                @foreach ($data as $item)
                                    <tr>
                                        <td>{{ $no++ }}</td>
                                        <td>{{ $item->Nama_Mesin ?? '-' }}</td>
                                        <td>{{ $item->Seri_Mesin ?? '-' }}</td>
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
            // update Progress Task
            $(document).on('change', '.progress-select', function() {
                let progress = $(this).val();
                let taskId = $(this).data('task');

                $.ajax({
                    url: '/task/updateProgress',
                    type: 'POST',
                    data: {
                        Progress_Id: progress,
                        Task_Id: taskId
                    },
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Progress berhasil diupdate.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // ✅ Update tampilan UI kalau perlu di sini (jika reload tidak digunakan)
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Gagal memperbarui progress.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                        console.error('Status :', status);
                        console.error('XHR :', xhr);
                        console.error('Error :', error);
                        console.error('Response Text:', xhr.responseText);
                    }
                });
            });



            //get-data-Modal
            $(document).on('click', '#editButton', function() {
                $(this).addClass('edit-task-trigger-click');

                let option = {
                    'backdrop': 'static'
                };
                $('#editModal').modal(option);
            })

            $('#editModal').on('shown.bs.modal', function() {
                let el = $('.edit-task-trigger-click');
                let row = el.closest('.search-row');

                let id = el.data('task-id');
                let Task = row.children('#task-name').text();
                let Start_Date = row.children('#Start_Date').data('start');
                let End_Date = row.children('#End_Date').data('end');
                let Project = el.data('value');
                $('#Id_taskModal').val(id);
                $('#TaskModal').val(Task);
                $('#Start_DateModal').val(Start_Date);
                $('#End_DateModal').val(End_Date);
                $('#SelectProject').val(Project);
            })

            $('#editModal').on('hide.bs.modal', function() {
                $('.edit-task-trigger-click').removeClass('edit-task-trigger-click');
                $("#editForm").trigger('reset');
            })
        });
    </script>
@endpush
