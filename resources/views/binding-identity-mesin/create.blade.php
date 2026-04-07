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
                        Data Identity Berdasarkan Computer Keys
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div class="col-12">
                    <form action="{{ route('bidingidentity.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="Id_Identity" class="form-label fw-semibold">Computer Keys <span
                                    class="text-danger">*</span></label>
                            <select name="Id_Identity" id="Id_Identity"
                                class="js-example-basic-single @error('Id_Identity') is-invalid shake @enderror"
                                data-placeholder="-- Pilih Identity Computer --">
                                <option value=""></option>
                                @foreach ($identity as $i)
                                    <option value="{{ $i->id }}"
                                        {{ old('Id_Identity') == $i->id ? 'selected' : '' }}>
                                        {{ $i->Computer_Keys ?? 'Tidak Ada Data' }} ~ {{ $i->Keterangan ?? '-' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Id_Identity')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>


                        <div class="mb-3">
                            <label for="mesin_id" class="form-label fw-semibold">Nama Mesin <span
                                    class="text-danger">*</span></label>
                            <select name="Id_Mesin" id="mesin_id"
                                class="js-mesin @error('Id_Mesin') is-invalid shake @enderror"
                                data-placeholder="-- Pilih Mesin --">
                                <option value=""></option>
                                @foreach ($mesin as $m)
                                    <option value="{{ $m->Id_Master_Mesin }}"
                                        {{ old('Id_Mesin') == $m->Id_Master_Mesin ? 'selected' : '' }}>
                                        {{ $m->Nama_Mesin }} ~ {{ $m->Seri_Mesin }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Id_Mesin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                       
                        <div class="mb-3 d-grid">
                            <button type="submit" class="btn btn-success">Submit</button>
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
            $(document).ready(function() {
                $('.js-mesin').select2({
                    placeholder: $(this).data('placeholder'),
                    allowClear: true

                });
            });
        </script>
    @endpush
@endsection
