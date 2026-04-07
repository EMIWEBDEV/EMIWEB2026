@extends('layouts.master')
@section('title', 'Daftar Lembur')

@section('content')
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

    <style>
        .offcanvas-end {
            width: 320px;
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

        .custom-alert {
            display: flex;
            align-items: start;
            background-color: #FEF3C7;
            /* soft yellow */
            border: 1px solid #FCD34D;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 16px 0;
            position: relative;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            font-family: 'Segoe UI', sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }

        .custom-alert .icon {
            font-size: 20px;
            margin-right: 12px;
            margin-top: 2px;
        }

        .custom-alert .content {
            flex: 1;
            color: #92400E;
        }

        .custom-alert .close-btn {
            background: transparent;
            border: none;
            font-size: 20px;
            color: #92400E;
            cursor: pointer;
            margin-left: 10px;
        }


        /* Responsive Adjustments */
        @media (max-width: 767.98px) {
            .card-body {
                padding: 1.5rem !important;
            }


        }
    </style>

    <div class="container-fluid mx-auto px-0">
        <div class="container">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="mb-4 text-center text-md-start">
                        <h1 class="text-2xl md:text-3xl font-bold text-primary">
                            On Proccess Lembur
                        </h1>


                        <p class="text-sm md:text-base text-muted">
                            Pengajuan Lembur Kerja PT. Evo Nusa Bersaudara
                        </p>
                        <div class="divider my-3"></div>
                    </div>

                    {{-- Button Ajukan Lembur --}}
                    <div class="d-flex justify-content-center justify-content-lg-end">
                        <a href="{{ route('absensi.lemburcreate') }}" class="btn btn-success">
                            + Ajukan Lembur
                        </a>
                    </div>

                    @if ($data->isEmpty())
                        <div class="d-flex flex-column justify-content-center align-items-center text-center"
                            style="height: 100vh;">
                            <lottie-player src="{{ asset('animation/empty-data.json') }}" background="transparent"
                                speed="1" style="width: 300px; height: 300px;" loop autoplay></lottie-player>
                            <p>Data Tidak Ada</p>
                        </div>
                    @else
                        @php
                            $hasOnProgress = $data
                                ->filter(function ($item) {
                                    return $item->Created_At == $item->Updated_At;
                                })
                                ->isNotEmpty();
                        @endphp

                        @if ($hasOnProgress)
                            <div class="mb-3">
                                <div class="custom-alert warning-alert">
                                    <div class="icon">
                                        ⚠️
                                    </div>
                                    <div class="content">
                                        <strong>Perhatian!</strong> Jangan lupa menyelesaikan dan mengonfirmasi pekerjaan
                                        lembur
                                        Anda.
                                        Setelah selesai, segera tekan tombol <strong>Finish</strong> untuk mengakhiri
                                        lembur.
                                    </div>
                                    <button class="close-btn" onclick="this.parentElement.style.display='none';">×</button>
                                </div>
                            </div>
                        @endif

                        <div class="col-12">
                            <div class="table-responsive">
                                <table id="myTable" class="table table-lg">
                                    <thead>
                                        <th>No</th>
                                        <th>Nomor Lembur</th>
                                        <th>Tanggal Lembur</th>
                                        <th>Jam Mulai</th>
                                        <th>Jam Selesai</th>
                                        <th>Berkas</th>
                                        <th>Status</th>
                                        <th>Waktu Penyelesaian</th>
                                        <th>Aksi</th>
                                    </thead>
                                    <tbody>
                                        @php
                                            $no = 1;
                                        @endphp
                                        @foreach ($data as $item)
                                            <tr>
                                                <td>{{ $no++ }}</td>
                                                <td>{{ $item->no_transaksi ?? 'N/A' }}</td>
                                                <td>{{ $item->tanggal }}
                                                </td>
                                                <td>{{ $item->jam_mulai ?? 'N/A' }} WIB</td>
                                                <td>{{ $item->jam_selesai ?? 'N/A' }} WIB</td>
                                                <td>
                                                    <span class="badge bg-info" style="cursor: pointer;"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalFileBuktiDukung{{ $item->id }}">
                                                        <i class="bi bi-eye"></i>
                                                    </span>
                                                    <div class="modal fade" id="modalFileBuktiDukung{{ $item->id }}"
                                                        tabindex="-1" role="dialog" aria-labelledby="modal1Label"
                                                        aria-hidden="true">
                                                        <div class="modal-dialog modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="modal1Label">Berkas
                                                                    </h5>
                                                                    <button type="button"
                                                                        class="btn btn-sm btn-label-danger btn-icon"
                                                                        data-bs-dismiss="modal" aria-label="Close">
                                                                        <i class="bi bi-x text-danger text-2xl"
                                                                            style="font-size: 20px !important;"></i>
                                                                    </button>
                                                                </div>
                                                                <div class="container col-lg-12 mb-2 mt-1 text-center">
                                                                    <img src="{{ route('image.showbuktidukung', ['filename' => $item->bukti_dukung]) }}"
                                                                        alt="Bukti Dukung Lembur" class="img-fluid" />
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <a href="{{ route('image.showbuktidukung', ['filename' => $item->bukti_dukung]) }}"
                                                                        download class="btn btn-success">
                                                                        <i class="bi bi-download me-1"></i> Download
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($item->Created_At == $item->Updated_At)
                                                        @if ($item->expired === true)
                                                            <span class="badge bg-danger text-white">Expired</span>
                                                        @else
                                                            <span class="badge bg-warning text-white">On Progress</span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-success text-white">Selesai</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($item->Created_At == $item->Updated_At)
                                                        <span class="badge bg-secondary text-white">Belum Selesai</span>
                                                    @else
                                                        {{ \Carbon\Carbon::parse($item->Updated_At)->translatedFormat('d F Y H:i') }}
                                                        WIB
                                                    @endif
                                                </td>
                                                <td>
                                                    @if ($item->expired === true)
                                                        -
                                                    @else
                                                        @if ($item->Created_At === $item->Updated_At)
                                                            <button class="btn btn-primary" type="button"
                                                                data-bs-toggle="offcanvas"
                                                                data-bs-target="#offcanvasRight{{ $item->id }}"
                                                                aria-controls="offcanvasRight{{ $item->id }}">
                                                                Finish
                                                            </button>

                                                            <div class="offcanvas offcanvas-end" tabindex="-1"
                                                                id="offcanvasRight{{ $item->id }}"
                                                                aria-labelledby="offcanvasRightLabel{{ $item->id }}">
                                                                <div class="offcanvas-header">
                                                                    <h5 id="offcanvasRightLabel{{ $item->id }}"
                                                                        class="mb-0">Konfirmasi Selesai Lembur</h5>
                                                                    <button type="button" class="btn-close text-reset"
                                                                        data-bs-dismiss="offcanvas"
                                                                        aria-label="Close"></button>
                                                                </div>
                                                                <div class="offcanvas-body">
                                                                    <form
                                                                        action="{{ route('absensi.lembur.confirmdonesubmision', $item->id) }}"
                                                                        method="POST" enctype="multipart/form-data">
                                                                        @csrf
                                                                        <div class="col-12">
                                                                            <div class="form-group">
                                                                                <label
                                                                                    for="label-alasan-{{ $item->id }}"
                                                                                    class="form-label fw-semibold">
                                                                                    Deskripsi <span
                                                                                        class="text-danger">*</span>
                                                                                </label>
                                                                                <small class="m-detail form-text">
                                                                                    <div class="form-text">
                                                                                        Masukan Deskripsi Konfirmasi lembur
                                                                                        anda secara jelas, sebagai laporan
                                                                                        anda kepada pimpinan
                                                                                    </div>
                                                                                </small>
                                                                                <textarea name="keterangan_konfirmasi" id="label-alasan-{{ $item->id }}"
                                                                                    placeholder="Masukan Deskripsi/Penyampaian Selesai Sebagai Laporan" class="form-control" rows="5"></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <div class="form-group">
                                                                                <label class="form-label fw-semibold">Bukti
                                                                                    Dukung <span
                                                                                        class="text-danger">*</span></label>
                                                                                <small class="form-text mb-2">
                                                                                    Pilih Salah Satu Menu Dibawah Untuk
                                                                                    Pemberian Bukti Dukung
                                                                                </small>
                                                                                <div
                                                                                    class="d-flex flex-column flex-md-row gap-2 mb-3">
                                                                                    <!-- Upload File -->
                                                                                    <button type="button"
                                                                                        id="btnUpload{{ $item->id }}"
                                                                                        class="btn btn-outline-primary flex-grow-1 py-2"
                                                                                        title="Upload gambar jika tidak ingin menggunakan kamera">
                                                                                        <i class="bi bi-upload me-2"></i>
                                                                                        Upload Gambar
                                                                                    </button>
                                                                                    <input type="file"
                                                                                        id="fileInput{{ $item->id }}"
                                                                                        accept="image/png, image/jpeg, image/jpg, image/webp"
                                                                                        class="d-none" />

                                                                                    <!-- Kamera (toggle) -->
                                                                                    <button type="button"
                                                                                        id="btnToggleCamera{{ $item->id }}"
                                                                                        class="btn btn-outline-success flex-grow-1 py-2">
                                                                                        <i id="iconCamera{{ $item->id }}"
                                                                                            class="bi bi-camera me-2"></i>
                                                                                        Ambil Foto
                                                                                    </button>
                                                                                </div>

                                                                                <!-- Kamera Live Preview -->
                                                                                <div id="cameraContainer{{ $item->id }}"
                                                                                    style="display:none;"
                                                                                    class="text-center mb-3">
                                                                                    <video id="video{{ $item->id }}"
                                                                                        autoplay playsinline width="100%"
                                                                                        class="rounded-3 border"></video>
                                                                                    <div
                                                                                        class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
                                                                                        <button type="button"
                                                                                            id="btnFrontCamera{{ $item->id }}"
                                                                                            class="btn btn-sm btn-outline-primary"
                                                                                            title="Kamera Depan">Depan</button>
                                                                                        <button type="button"
                                                                                            id="btnBackCamera{{ $item->id }}"
                                                                                            class="btn btn-sm btn-outline-primary"
                                                                                            title="Kamera Belakang">Belakang</button>
                                                                                        <button type="button"
                                                                                            id="btnCapture{{ $item->id }}"
                                                                                            class="btn btn-sm btn-danger"
                                                                                            title="Ambil Foto">
                                                                                            <i
                                                                                                class="bi bi-camera me-1"></i>
                                                                                            Ambil Foto
                                                                                        </button>
                                                                                    </div>
                                                                                </div>

                                                                                <!-- Preview Gambar -->
                                                                                <div id="previewContainer{{ $item->id }}"
                                                                                    style="display:none;"
                                                                                    class="text-center mb-3 position-relative">
                                                                                    <img id="previewImage{{ $item->id }}"
                                                                                        src="" alt="Preview"
                                                                                        class="img-fluid rounded-3 border" />
                                                                                    <button type="button"
                                                                                        id="btnClear{{ $item->id }}"
                                                                                        class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2"
                                                                                        title="Hapus gambar">
                                                                                        <i class="bi bi-trash"></i>
                                                                                    </button>
                                                                                </div>

                                                                                <input type="hidden" name="bukti_dukung"
                                                                                    id="buktiDukung{{ $item->id }}" />
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-12">
                                                                            <div class="d-grid">
                                                                                <button type="submit"
                                                                                    class="btn btn-primary">
                                                                                    <i class="bi bi-send-check me-2"></i>
                                                                                    Submit Form
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="badge bg-success poin" style="cursor: pointer"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalFileBuktiDukungConfirmed{{ $item->id }}">
                                                                Done</div>
                                                            <div class="modal fade"
                                                                id="modalFileBuktiDukungConfirmed{{ $item->id }}"
                                                                tabindex="-1" role="dialog"
                                                                aria-labelledby="modal1Label" aria-hidden="true">
                                                                <div class="modal-dialog modal-lg">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="modal1Label">
                                                                                Berkas
                                                                            </h5>
                                                                            <button type="button"
                                                                                class="btn btn-sm btn-label-danger btn-icon"
                                                                                data-bs-dismiss="modal"
                                                                                aria-label="Close">
                                                                                <i class="bi bi-x text-danger text-2xl"
                                                                                    style="font-size: 20px !important;"></i>
                                                                            </button>
                                                                        </div>
                                                                        <div
                                                                            class="container col-lg-12 mb-2 mt-1 text-center">
                                                                            <img src="{{ route('image.showconfirmeddone', ['filename' => $item->bukti_pengerjaan_selesai]) }}"
                                                                                alt="Bukti Dukung Lembur"
                                                                                class="img-fluid" />
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <a href="{{ route('image.showconfirmeddone', ['filename' => $item->bukti_pengerjaan_selesai]) }}"
                                                                                download class="btn btn-success">
                                                                                <i class="bi bi-download me-1"></i>
                                                                                Download
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

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

    @push('vueapp')
        <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet" type="text/css" />
        <link href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap.min.css" rel="stylesheet"
            type="text/css" />
        <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet"
            type="text/css"  />
    @endpush

    @push('scripts')
        <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#myTable').DataTable({
                    pageLength: 5, // default tampil 5 row
                    lengthMenu: [5, 10, 25, 50, 100]
                });
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                @foreach ($data as $item)
                    (function() {
                        const id = "{{ $item->id }}";
                        const btnUpload = document.getElementById('btnUpload' + id);
                        const fileInput = document.getElementById('fileInput' + id);
                        const btnToggleCamera = document.getElementById('btnToggleCamera' + id);
                        const cameraContainer = document.getElementById('cameraContainer' + id);
                        const video = document.getElementById('video' + id);
                        const btnFrontCamera = document.getElementById('btnFrontCamera' + id);
                        const btnBackCamera = document.getElementById('btnBackCamera' + id);
                        const btnCapture = document.getElementById('btnCapture' + id);
                        const previewContainer = document.getElementById('previewContainer' + id);
                        const previewImage = document.getElementById('previewImage' + id);
                        const btnClear = document.getElementById('btnClear' + id);
                        const buktiDukung = document.getElementById('buktiDukung' + id);
                        const iconCamera = document.getElementById('iconCamera' + id);

                        if (!btnUpload || !fileInput || !btnToggleCamera || !cameraContainer || !video || !
                            btnFrontCamera || !btnBackCamera || !btnCapture || !previewContainer || !previewImage ||
                            !btnClear || !buktiDukung || !iconCamera) {
                            console.warn(`Elemen tidak ditemukan untuk ID ${id}, lewati binding.`);
                            return;
                        }
                        let stream = null;
                        let photoTaken = false;
                        let currentFacingMode = 'environment';

                        async function openCamera() {
                            try {
                                if (stream) {
                                    stopStream();
                                }
                                stream = await navigator.mediaDevices.getUserMedia({
                                    video: {
                                        facingMode: currentFacingMode
                                    }
                                });
                                video.srcObject = stream;
                                cameraContainer.style.display = 'block';
                                photoTaken = false;
                                previewContainer.style.display = 'none';
                                iconCamera.className = 'bi bi-x-circle me-2';
                                btnToggleCamera.textContent = ' Tutup Kamera';
                                btnToggleCamera.prepend(iconCamera);
                            } catch (e) {
                                alert('Tidak dapat mengakses kamera. Pastikan izin sudah diberikan.');
                                console.error(e);
                            }
                        }

                        function stopStream() {
                            if (stream) {
                                stream.getTracks().forEach(track => track.stop());
                                stream = null;
                            }
                            cameraContainer.style.display = 'none';
                            iconCamera.className = 'bi bi-camera me-2';
                            btnToggleCamera.textContent = ' Ambil Foto';
                            btnToggleCamera.prepend(iconCamera);
                        }

                        btnToggleCamera.addEventListener('click', () => {
                            if (!stream) {
                                openCamera();
                            } else {
                                stopStream();
                            }
                        });

                        btnFrontCamera.addEventListener('click', () => {
                            currentFacingMode = 'user';
                            if (stream) {
                                stopStream();
                                openCamera();
                            }
                        });

                        btnBackCamera.addEventListener('click', () => {
                            currentFacingMode = 'environment';
                            if (stream) {
                                stopStream();
                                openCamera();
                            }
                        });

                        btnCapture.addEventListener('click', () => {
                            if (!stream) return alert('Kamera belum aktif.');

                            const canvas = document.createElement('canvas');
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            const ctx = canvas.getContext('2d');
                            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                            canvas.toBlob(blob => {
                                if (!blob) return;

                                const fileName = `bukti_dukung_${Date.now()}.jpg`;
                                const file = new File([blob], fileName, {
                                    type: 'image/jpeg'
                                });

                                const imageUrl = URL.createObjectURL(blob);
                                previewImage.src = imageUrl;
                                previewContainer.style.display = 'block';

                                const reader = new FileReader();
                                reader.onloadend = () => {
                                    buktiDukung.value = reader.result;
                                };
                                reader.readAsDataURL(file);

                                photoTaken = true;
                                stopStream();
                            }, 'image/jpeg', 0.95);
                        });

                        btnUpload.addEventListener('click', () => {
                            fileInput.click();
                        });

                        fileInput.addEventListener('change', () => {
                            const file = fileInput.files[0];
                            if (!file) return;

                            if (!file.type.startsWith('image/')) {
                                alert('Hanya file gambar yang diperbolehkan!');
                                return;
                            }

                            previewImage.src = URL.createObjectURL(file);
                            previewContainer.style.display = 'block';

                            const reader = new FileReader();
                            reader.onloadend = () => {
                                buktiDukung.value = reader.result;
                            };
                            reader.readAsDataURL(file);

                            photoTaken = true;
                            stopStream();
                        });

                        btnClear.addEventListener('click', () => {
                            previewImage.src = '';
                            previewContainer.style.display = 'none';
                            buktiDukung.value = '';
                            photoTaken = false;
                        });
                    })();
                @endforeach
            });
        </script>
    @endpush





@endsection
