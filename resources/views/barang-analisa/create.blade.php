@extends('layouts.master2')


@section('title', 'Tambah Binding Identity Komputer - PT.Evo Nusa Bersaudara')

@section('content')
    @push('css')
        <style>
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

            .form-control,
            .form-control-lg {
                border-radius: 8px;
                border: 1px solid #dee2e6;
                transition: all 0.3s ease;
            }

            .form-control:focus {
                border-color: #86b7fe;
                box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            }

            .btn {
                border-radius: 8px;
                transition: all 0.3s ease;
                font-weight: 500;
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

            @media (max-width: 767.98px) {
                .card-body {
                    padding: 1.5rem !important;
                }

                .btn {
                    padding: 0.5rem !important;
                    font-size: 0.875rem;
                }

                .preview-image {
                    max-height: 300px;
                }
            }
        </style>
    @endpush

    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-xl md:text-3xl font-bold text-primary">
                        Form Tambah
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Data Barang Berdasarkan Jenis Analisa
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <form action="{{ route('barangjenisanalis.store') }}" method="POST" id="mainForm">
                        @csrf

                        {{-- Container untuk menampung Card yang digenerate --}}
                        <div id="dynamic_field_container">
                            {{-- Jika ada error validasi (old input), loop PHP disini untuk render ulang (opsional, agak kompleks di blade). 
                                Untuk simplifikasi, kita start kosong atau render 1 row default. --}}
                        </div>

                        <div class="d-flex justify-content-between mt-3">
                            <button type="button" name="add" id="add_row" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Tambah Konfigurasi (Card)
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Simpan Semua
                            </button>
                        </div>
                    </form>
                    <div id="row_template" style="display: none;">
                        <div class="card mb-4 border-primary shadow-sm row-item">
                            <div class="card-header d-flex justify-content-between align-items-center bg-primary-subtle">
                                <h6 class="mb-0 fw-bold text-primary">Konfigurasi #<span class="row-number"></span></h6>
                                <button type="button" class="btn btn-danger btn-sm remove_row">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                            <div class="card-body">
                                
                                {{-- Jenis Analisa (Single Select - Tidak butuh Select All) --}}
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Jenis Analisa</label>
                                    <select class="form-control template-jenis">
                                        <option value="">-- Pilih Jenis Analisa --</option>
                                        @foreach ($jenisAnalisa as $i)
                                            <option value="{{ $i->id }}">{{ $i->Kode_Analisa }} ~ {{ $i->Jenis_Analisa }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="row">
                                    {{-- Mesin (Multiple + Select All) --}}
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">Pilih Mesin</label>
                                        <select class="form-control template-mesin" multiple>
                                            {{-- Opsi Select All --}}
                                            <option value="SELECT_ALL" class="fw-bold text-primary">-- PILIH SEMUA MESIN --</option>
                                            @foreach ($mesin as $m)
                                                <option value="{{ $m->Id_Master_Mesin }}">{{ $m->Nama_Mesin }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Barang (Multiple + Select All) --}}
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">Pilih Barang</label>
                                        <select class="form-control template-barang" multiple>
                                            {{-- Opsi Select All --}}
                                            <option value="SELECT_ALL" class="fw-bold text-primary">-- PILIH SEMUA BARANG --</option>
                                            @foreach ($barang as $b)
                                                <option value="{{ $b->Kode_Barang }}">{{ $b->Kode_Barang }} ~ {{ $b->Nama }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- User (Multiple + Select All) --}}
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-bold">Pilih User</label>
                                        <select class="form-control template-user" multiple>
                                            {{-- Opsi Select All --}}
                                            <option value="SELECT_ALL" class="fw-bold text-primary">-- PILIH SEMUA USER --</option>
                                            @foreach ($user as $u)
                                                <option value="{{ $u->UserId }}">{{ $u->UserId }} ~ {{ $u->Nama }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
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

    @push('js')
        <script>
            $(document).ready(function() {
                let rowIndex = 0;

                function updateDeleteButtons() {
                    let totalRows = $('#dynamic_field_container .row-item').length;
                    if (totalRows <= 1) {
                        $('.remove_row').hide();
                    } else {
                        $('.remove_row').show();
                    }
                }

                function attachSelectAllBehavior(selectElement) {
                    selectElement.on('select2:select', function (e) {
                        let selectedId = e.params.data.id;
                        if (selectedId === 'SELECT_ALL') {
                            let allValues = [];
                            
                            $(this).find('option').each(function() {
                                let val = $(this).val();
                                // Masukkan semua value KECUALI 'SELECT_ALL' dan yang kosong
                                if (val !== 'SELECT_ALL' && val !== '') {
                                    allValues.push(val);
                                }
                            });
                            $(this).val(allValues).trigger('change');
                        }
                    });
                }

                function addNewRow() {
                    let template = $('#row_template').html();
                    let newRow = $(template);

                    newRow.find('.row-number').text(rowIndex + 1);

                    newRow.find('.template-jenis').attr('name', `group[${rowIndex}][Id_Jenis_Analisa]`);
                    newRow.find('.template-mesin').attr('name', `group[${rowIndex}][Id_Master_Mesin][]`);
                    newRow.find('.template-barang').attr('name', `group[${rowIndex}][Kode_Barang][]`);
                    newRow.find('.template-user').attr('name',  `group[${rowIndex}][Id_User][]`);

                    $('#dynamic_field_container').append(newRow);

                    newRow.find('select').each(function() {
                        let isMultiple = $(this).attr('multiple');
                        let $select = $(this); 

                        $select.select2({
                            placeholder: isMultiple ? "-- Pilih Multiple Data --" : "-- Pilih Data --",
                            allowClear: true,
                            width: '100%',
                            closeOnSelect: !isMultiple
                        });
                        if (isMultiple) {
                            attachSelectAllBehavior($select);
                        }
                    });

                    rowIndex++;
                    updateDeleteButtons();
                }

                $('#add_row').click(function() {
                    addNewRow();
                });

                $(document).on('click', '.remove_row', function() {
                    if ($('#dynamic_field_container .row-item').length > 1) {
                        $(this).closest('.row-item').remove();
                        updateDeleteButtons();
                    }
                });

                addNewRow();
            });
        </script>
    @endpush
@endsection
