@extends('layouts.master2')


@section('title', 'Tambah Jenis Analisa - PT.Evo Nusa Bersaudara')

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
                        Jenis Analisa Pada LAB PT. EVO MANUFACTURING INDONESIA
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <div class="alert alert-warning d-flex align-items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                            class="bi bi-exclamation-triangle me-2" viewBox="0 0 16 16">
                            <path
                                d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.146.146 0 0 1 .054.057l6.857 10.667c.036.06.035.124.002.183a.163.163 0 0 1-.054.06.116.116 0 0 1-.066.017H1.146a.115.115 0 0 1-.066-.017.163.163 0 0 1-.054-.06.176.176 0 0 1 .002-.183L7.884 2.073a.147.147 0 0 1 .054-.057zm1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566z" />
                            <path
                                d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995z" />
                        </svg>
                        <div>
                            Masukkan sesuai urutan parameter dari kiri ke kanan. Bertanggung jawab atas ketidaksesuaian
                            hasil karena menentukan posisi dan hasil analisa.
                        </div>
                    </div>

                    <form action="{{ route('bindingjenisanalisa.update', $id) }}" method="POST">
                        @csrf
                        @method('PUT')
                    
                        <div class="mb-3">
                            <label for="Id_Jenis_Analisa" class="form-label fw-semibold">Jenis Analisa <span class="text-danger">*</span></label>
                            <select class="js-example-basic-single form-control @error('Id_Jenis_Analisa') is-invalid shake @enderror"
                                name="Id_Jenis_Analisa" id="Id_Jenis_Analisa" data-placeholder="-- Pilih Jenis Analisis --">
                                <option value=""></option>
                                @foreach ($jenisAnalisa as $i)
                                    <option value="{{ $i->id }}"
                                        
                                        {{ old('Id_Jenis_Analisa', $edit->Id_Jenis_Analisa ?? '') == $i->id ? 'selected' : '' }}>
                    
                                        {{ $i->Kode_Analisa ?? 'Tidak Ada Data' }} ~ {{ $i->Jenis_Analisa ?? '-' }}
                                        @if ($i->Nama_Mesin)
                                            ~ {{ $i->Nama_Mesin }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('Id_Jenis_Analisa')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    
                        <div class="mb-3">
                            <label for="Id_Quality_Control" class="form-label fw-semibold">Parameter Quality <span class="text-danger">*</span></label>
                            <select class="js-example-basic-single form-control @error('Id_Quality_Control') is-invalid shake @enderror"
                                name="Id_Quality_Control" id="Id_Quality_Control" data-placeholder="-- Pilih Parameter Quality --">
                                <option value=""></option>
                                @foreach ($qualityControl as $i)
                                    <option value="{{ $i->Id_QC_Formula }}"
                                        {{-- Disesuaikan menggunakan variabel $edit dan properti Id_QC_Formula --}}
                                        {{ old('Id_Quality_Control', $edit->Id_Quality_Control ?? '') == $i->Id_QC_Formula ? 'selected' : '' }}>
                    
                                        {{ $i->Keterangan ?? 'Tidak Ada Data' }} ~ {{ $i->Satuan ?? '-' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Id_Quality_Control')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    
                        <div class="mb-3">
                            <label for="Keterangan" class="form-label fw-semibold">Keterangan</label>
                            <textarea name="Keterangan" id="Keterangan" rows="4" placeholder="Masukan Keterangan (Opsional)"
                                class="form-control">{{-- Disesuaikan menggunakan variabel $edit --}}{{ old('Keterangan', $edit->Keterangan ?? '') }}</textarea>
                            @error('Keterangan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    
                        <div class="mb-3 d-grid">
                            {{-- Disesuaikan dengan tombol Update berwarna kuning --}}
                            <button type="submit" class="btn btn-warning">Update</button>
                        </div>
                    </form>
                    

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
                $('.js-example-basic-single').select2({
                    placeholder: $(this).data('placeholder'),
                    allowClear: true
                });
            });
        </script>
    @endpush
@endsection
