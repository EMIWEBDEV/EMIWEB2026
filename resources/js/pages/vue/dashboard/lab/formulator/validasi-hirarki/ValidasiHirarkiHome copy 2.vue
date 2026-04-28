<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Pra-Finalisasi
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Pra-Finalisasi Uji Trial PT. Evo Manufacturing
                        Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-4 mb-3">
                    <div
                        class="col-lg-12 d-flex justify-content-between align-items-center gap-3"
                    >
                        <div class="search-box flex-grow-1">
                            <input
                                type="search"
                                class="form-control search"
                                placeholder="Search..."
                                v-model="searchQuery"
                                @input="handleSearch"
                            />
                            <i class="ri-search-line search-icon"></i>
                        </div>

                        <div>
                            <button
                                type="button"
                                class="btn btn-primary"
                                @click="togglePrintModal"
                            >
                                <i class="ri-printer-line me-1"></i> Cetak
                                Laporan
                            </button>
                        </div>
                    </div>
                </div>

                <ul class="nav nav-tabs nav-justified mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a
                            class="nav-link"
                            :class="{ active: activeTab === 'prafinalisasi' }"
                            @click.prevent="switchTab('prafinalisasi')"
                            href="#"
                            role="tab"
                            :aria-selected="activeTab === 'prafinalisasi'"
                        >
                            <i class="ri-flask-line me-1 align-middle"></i> List
                            Pra Finalisasi
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a
                            class="nav-link"
                            :class="{ active: activeTab === 'desktop' }"
                            @click.prevent="switchTab('desktop')"
                            href="#"
                            role="tab"
                            :aria-selected="activeTab === 'desktop'"
                        >
                            <i class="ri-computer-line me-1 align-middle"></i>
                            Informasi Desktop
                        </a>
                    </li>
                </ul>

                <!-- tabs -->
                <div class="tab-content text-muted">
                    <div
                        class="tab-pane"
                        :class="{ active: activeTab === 'prafinalisasi' }"
                    >
                        <div class="col-12 mt-3">
                            <div
                                class="modal fade"
                                id="modalValidasi"
                                tabindex="-1"
                                aria-labelledby="modalValidasiLabel"
                                aria-hidden="true"
                                data-bs-backdrop="static"
                            >
                                <div
                                    class="modal-dialog modal-xl modal-dialog-centered"
                                >
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5
                                                class="modal-title fw-bold"
                                                id="modalValidasiLabel"
                                            >
                                                Validasi Sampel:
                                                <span class="text-primary">{{
                                                    selectedSampel
                                                }}</span>
                                            </h5>
                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                                aria-label="Close"
                                                @click="resetModalValidasi"
                                            ></button>
                                        </div>
                                        <div class="modal-body pb-0">
                                            <div
                                                v-if="loading.loadingModal"
                                                class="d-flex justify-content-center py-5"
                                            >
                                                <div
                                                    class="spinner-border text-primary"
                                                    role="status"
                                                >
                                                    <span
                                                        class="visually-hidden"
                                                        >Loading...</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                v-else-if="
                                                    listKlasifikasi.length > 0
                                                "
                                            >
                                                <div
                                                    style="
                                                        overflow-x: auto;
                                                        overflow-y: hidden;
                                                    "
                                                    class="pb-2"
                                                >
                                                    <el-steps
                                                        :active="activeStep"
                                                        finish-status="success"
                                                        align-center
                                                        class="mb-5 mt-3"
                                                        style="min-width: 600px"
                                                    >
                                                        <el-step
                                                            v-for="(
                                                                step, index
                                                            ) in listKlasifikasi"
                                                            :key="index"
                                                            :title="
                                                                step.Nama_Aktivitas
                                                            "
                                                        ></el-step>
                                                    </el-steps>
                                                </div>

                                                <div
                                                    v-if="currentStepData"
                                                    class="card border shadow-none mb-4"
                                                >
                                                    <div
                                                        class="card-header bg-light"
                                                    >
                                                        <h6
                                                            class="mb-0 fw-bold"
                                                        >
                                                            Daftar Item:
                                                            {{
                                                                currentStepData.Nama_Aktivitas
                                                            }}
                                                        </h6>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <template
                                                            v-if="
                                                                (currentStepData.data_analisa &&
                                                                    currentStepData
                                                                        .data_analisa
                                                                        .length >
                                                                        0) ||
                                                                (currentStepData.pending_analisa &&
                                                                    currentStepData
                                                                        .pending_analisa
                                                                        .length >
                                                                        0)
                                                            "
                                                        >
                                                            <div
                                                                class="p-3 border-bottom bg-light"
                                                            >
                                                                <div
                                                                    v-if="
                                                                        currentStepData.status_step ===
                                                                            'DISETUJUI' ||
                                                                        currentStepData.status_step ===
                                                                            'DITOLAK'
                                                                    "
                                                                    class="alert shadow-sm border-0 border-start border-4 d-flex align-items-center mb-0"
                                                                    :class="
                                                                        currentStepData.status_step ===
                                                                        'DISETUJUI'
                                                                            ? 'alert-success border-success'
                                                                            : 'alert-danger border-danger'
                                                                    "
                                                                    role="alert"
                                                                >
                                                                    <i
                                                                        :class="
                                                                            currentStepData.status_step ===
                                                                            'DISETUJUI'
                                                                                ? 'ri-checkbox-circle-fill text-success'
                                                                                : 'ri-close-circle-fill text-danger'
                                                                        "
                                                                        class="fs-24 me-3"
                                                                    ></i>
                                                                    <div>
                                                                        <h6
                                                                            class="mb-0 fw-bold"
                                                                            :class="
                                                                                currentStepData.status_step ===
                                                                                'DISETUJUI'
                                                                                    ? 'text-success'
                                                                                    : 'text-danger'
                                                                            "
                                                                        >
                                                                            Tahapan
                                                                            Selesai
                                                                            ({{
                                                                                currentStepData.status_step ===
                                                                                "DISETUJUI"
                                                                                    ? "Disetujui"
                                                                                    : "Ditolak"
                                                                            }})
                                                                        </h6>
                                                                        <p
                                                                            class="mb-0 small text-dark"
                                                                        >
                                                                            Tahapan
                                                                            ini
                                                                            telah
                                                                            divalidasi
                                                                            dan
                                                                            dikunci
                                                                            dari
                                                                            sistem.
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    v-else-if="
                                                                        currentStepData.pending_analisa &&
                                                                        currentStepData
                                                                            .pending_analisa
                                                                            .length >
                                                                            0
                                                                    "
                                                                    class="alert alert-warning shadow-sm border-0 border-start border-warning border-4 d-flex align-items-start mb-0"
                                                                    role="alert"
                                                                >
                                                                    <i
                                                                        class="ri-error-warning-fill fs-24 text-warning me-3 mt-1"
                                                                    ></i>
                                                                    <div>
                                                                        <h6
                                                                            class="mb-1 fw-bold text-warning-emphasis"
                                                                        >
                                                                            Perhatian:
                                                                            Ada
                                                                            Analisa
                                                                            Belum
                                                                            Selesai
                                                                        </h6>
                                                                        <p
                                                                            class="mb-2 small text-dark"
                                                                        >
                                                                            Daftar
                                                                            analisa
                                                                            di
                                                                            bawah
                                                                            ini
                                                                            belum
                                                                            diselesaikan
                                                                            atau
                                                                            dikirim
                                                                            oleh
                                                                            Lab:
                                                                        </p>
                                                                        <ul
                                                                            class="mb-2 ps-3 small fw-medium text-dark"
                                                                        >
                                                                            <li
                                                                                v-for="(
                                                                                    nama,
                                                                                    idx
                                                                                ) in currentStepData.pending_analisa"
                                                                                :key="
                                                                                    idx
                                                                                "
                                                                            >
                                                                                {{
                                                                                    nama
                                                                                }}
                                                                            </li>
                                                                        </ul>
                                                                        <div
                                                                            class="form-check mt-2"
                                                                        >
                                                                            <input
                                                                                class="form-check-input"
                                                                                type="checkbox"
                                                                                id="ackIncomplete"
                                                                                v-model="
                                                                                    acknowledgementChecked
                                                                                "
                                                                            />
                                                                            <label
                                                                                class="form-check-label small fw-bold text-dark"
                                                                                for="ackIncomplete"
                                                                            >
                                                                                Saya
                                                                                menyetujui
                                                                                /
                                                                                menolak
                                                                                tahapan
                                                                                ini
                                                                                meskipun
                                                                                terdapat
                                                                                daftar
                                                                                analisa
                                                                                yang
                                                                                belum
                                                                                diselesaikan.
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    v-else
                                                                    class="alert alert-info shadow-sm border-0 border-start border-info border-4 d-flex align-items-center mb-0"
                                                                    role="alert"
                                                                >
                                                                    <i
                                                                        class="ri-information-fill fs-24 text-info me-3"
                                                                    ></i>
                                                                    <div>
                                                                        <h6
                                                                            class="mb-0 fw-bold text-info"
                                                                        >
                                                                            Siap
                                                                            Divalidasi!
                                                                        </h6>
                                                                        <p
                                                                            class="mb-0 small text-dark"
                                                                        >
                                                                            Semua
                                                                            analisa
                                                                            pada
                                                                            tahap
                                                                            ini
                                                                            sudah
                                                                            selesai
                                                                            dan
                                                                            siap
                                                                            untuk
                                                                            Anda
                                                                            setujui
                                                                            atau
                                                                            tolak.
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div
                                                                v-if="
                                                                    currentStepData.data_analisa &&
                                                                    currentStepData
                                                                        .data_analisa
                                                                        .length >
                                                                        0
                                                                "
                                                                class="table-responsive"
                                                                style="
                                                                    max-height: 400px;
                                                                    overflow-y: auto;
                                                                "
                                                            >
                                                                <table
                                                                    class="table table-hover align-middle mb-0 text-center"
                                                                >
                                                                    <thead
                                                                        class="table-light"
                                                                        style="
                                                                            position: sticky;
                                                                            top: 0;
                                                                            z-index: 1;
                                                                        "
                                                                    >
                                                                        <tr>
                                                                            <th>
                                                                                No
                                                                            </th>
                                                                            <th>
                                                                                No
                                                                                Sub
                                                                                Sampel
                                                                            </th>
                                                                            <th>
                                                                                Jenis
                                                                                Analisa
                                                                            </th>
                                                                            <th>
                                                                                Hasil
                                                                                /
                                                                                Keterangan
                                                                            </th>
                                                                            <th>
                                                                                Status
                                                                            </th>
                                                                            <th>
                                                                                Foto
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <tr
                                                                            v-for="(
                                                                                item,
                                                                                idx
                                                                            ) in currentStepData.data_analisa"
                                                                            :key="
                                                                                idx
                                                                            "
                                                                        >
                                                                            <td>
                                                                                {{
                                                                                    idx +
                                                                                    1
                                                                                }}
                                                                            </td>
                                                                            <td
                                                                                class="fw-medium text-muted"
                                                                            >
                                                                                {{
                                                                                    item.No_Fak_Sub_Po &&
                                                                                    item.No_Fak_Sub_Po !==
                                                                                        item.No_Po_Sampel
                                                                                        ? item.No_Fak_Sub_Po
                                                                                        : "-"
                                                                                }}
                                                                            </td>
                                                                            <td
                                                                                class="fw-medium"
                                                                            >
                                                                                {{
                                                                                    item.Jenis_Analisa
                                                                                }}
                                                                            </td>
                                                                            <td
                                                                                class="fw-bold"
                                                                            >
                                                                                <span
                                                                                    v-if="
                                                                                        item.Flag_Perhitungan !==
                                                                                            'Y' &&
                                                                                        item.Keterangan_Kriteria
                                                                                    "
                                                                                    class="text-primary"
                                                                                >
                                                                                    {{
                                                                                        item.Keterangan_Kriteria
                                                                                    }}
                                                                                </span>
                                                                                <span
                                                                                    v-else
                                                                                >
                                                                                    {{
                                                                                        item.Hasil ??
                                                                                        "-"
                                                                                    }}
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <span
                                                                                    class="badge bg-success"
                                                                                    v-if="
                                                                                        item.Flag_Approval ===
                                                                                        'Y'
                                                                                    "
                                                                                    >Disetujui</span
                                                                                >
                                                                                <span
                                                                                    class="badge bg-danger"
                                                                                    v-else-if="
                                                                                        item.Flag_Approval ===
                                                                                        'T'
                                                                                    "
                                                                                    >Ditolak</span
                                                                                >
                                                                                <span
                                                                                    class="badge bg-warning text-dark"
                                                                                    v-else
                                                                                    >Menunggu</span
                                                                                >
                                                                            </td>
                                                                            <td>
                                                                                <template
                                                                                    v-if="
                                                                                        item.Flag_Foto ===
                                                                                        'Y'
                                                                                    "
                                                                                >
                                                                                    <button
                                                                                        v-if="
                                                                                            item.File_Url
                                                                                        "
                                                                                        @click="
                                                                                            lihatFoto(
                                                                                                item.File_Url
                                                                                            )
                                                                                        "
                                                                                        class="btn btn-sm btn-outline-info rounded-pill px-3"
                                                                                    >
                                                                                        <i
                                                                                            class="ri-image-line align-middle me-1"
                                                                                        ></i>
                                                                                        Lihat
                                                                                        Foto
                                                                                    </button>
                                                                                    <span
                                                                                        v-else
                                                                                        class="text-danger small fst-italic"
                                                                                        >Belum
                                                                                        Diupload</span
                                                                                    >
                                                                                </template>
                                                                                <span
                                                                                    v-else
                                                                                    class="text-muted small"
                                                                                    >-</span
                                                                                >
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </template>
                                                        <template v-else>
                                                            <div
                                                                class="text-center p-5"
                                                            >
                                                                <i
                                                                    class="ri-inbox-line text-muted"
                                                                    style="
                                                                        font-size: 3rem;
                                                                    "
                                                                ></i>
                                                                <p
                                                                    class="mt-2 text-muted fw-medium"
                                                                >
                                                                    Data belum
                                                                    ada untuk
                                                                    klasifikasi
                                                                    ini.
                                                                </p>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div
                                                    v-else
                                                    class="card border shadow-none mb-4"
                                                >
                                                    <div
                                                        class="card-body p-5 text-center"
                                                    >
                                                        <i
                                                            class="ri-inbox-line text-muted"
                                                            style="
                                                                font-size: 3rem;
                                                            "
                                                        ></i>
                                                        <p
                                                            class="mt-2 text-muted fw-medium"
                                                        >
                                                            Tahapan data belum
                                                            tersedia di sistem.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="modal-footer d-flex justify-content-between bg-light"
                                        >
                                            <div>
                                                <button
                                                    v-if="activeStep > 0"
                                                    type="button"
                                                    class="btn btn-secondary"
                                                    @click="activeStep--"
                                                >
                                                    <i
                                                        class="ri-arrow-left-line align-middle me-1"
                                                    ></i>
                                                    Kembali
                                                </button>
                                            </div>

                                            <div
                                                class="d-flex align-items-center"
                                            >
                                                <template
                                                    v-if="
                                                        currentStepData &&
                                                        (currentStepData.status_step ===
                                                            'DITOLAK' ||
                                                            currentStepData.status_step ===
                                                                'DISETUJUI')
                                                    "
                                                >
                                                    <div
                                                        class="d-flex align-items-center me-3"
                                                        :class="
                                                            currentStepData.status_step ===
                                                            'DISETUJUI'
                                                                ? 'text-success'
                                                                : 'text-danger'
                                                        "
                                                    >
                                                        <i
                                                            :class="
                                                                currentStepData.status_step ===
                                                                'DISETUJUI'
                                                                    ? 'ri-checkbox-circle-fill'
                                                                    : 'ri-close-circle-fill'
                                                            "
                                                            class="me-1 fs-16"
                                                        ></i>
                                                        <span class="fw-bold">
                                                            Tahapan ini telah
                                                            {{
                                                                currentStepData.status_step ===
                                                                "DISETUJUI"
                                                                    ? "Disetujui"
                                                                    : "Ditolak"
                                                            }}
                                                        </span>
                                                    </div>
                                                </template>

                                                <template
                                                    v-else-if="
                                                        currentStepData &&
                                                        currentStepData.status_step ===
                                                            'TERKUNCI'
                                                    "
                                                >
                                                    <div
                                                        class="d-flex align-items-center text-warning text-dark px-3 py-1 bg-warning-subtle rounded me-3"
                                                    >
                                                        <i
                                                            class="ri-lock-fill me-1 fs-16"
                                                        ></i>
                                                        <span class="fw-medium"
                                                            >Tahap ini belum
                                                            dapat diakses.</span
                                                        >
                                                    </div>
                                                </template>

                                                <template
                                                    v-if="
                                                        currentStepData &&
                                                        currentStepData.status_step ===
                                                            'MENUNGGU VALIDASI'
                                                    "
                                                >
                                                    <button
                                                        type="button"
                                                        class="btn btn-danger me-2"
                                                        :disabled="
                                                            loading.loadingAction ||
                                                            (currentStepData.pending_analisa &&
                                                                currentStepData
                                                                    .pending_analisa
                                                                    .length >
                                                                    0 &&
                                                                !acknowledgementChecked)
                                                        "
                                                        @click="openModalTolak"
                                                    >
                                                        <i
                                                            class="ri-close-circle-line align-middle me-1"
                                                        ></i>
                                                        Tolak
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-success"
                                                        :disabled="
                                                            loading.loadingAction ||
                                                            (currentStepData.pending_analisa &&
                                                                currentStepData
                                                                    .pending_analisa
                                                                    .length >
                                                                    0 &&
                                                                !acknowledgementChecked)
                                                        "
                                                        @click="setujuiValidasi"
                                                    >
                                                        <i
                                                            class="ri-check-double-line align-middle me-1"
                                                        ></i>
                                                        Setujui
                                                    </button>
                                                </template>

                                                <button
                                                    v-if="hasNextStepAvailable"
                                                    type="button"
                                                    class="btn btn-primary ms-3"
                                                    @click="activeStep++"
                                                >
                                                    Lanjut
                                                    <i
                                                        class="ri-arrow-right-line align-middle ms-1"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="modal fade"
                                id="modalAlasanTolak"
                                tabindex="-1"
                                aria-hidden="true"
                                data-bs-backdrop="static"
                            >
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content shadow-lg">
                                        <div
                                            class="modal-header bg-danger text-white"
                                        >
                                            <h5
                                                class="modal-title text-capitalize text-white"
                                            >
                                                <i
                                                    class="ri-error-warning-line me-1"
                                                ></i>
                                                Alasan Penolakan
                                            </h5>
                                            <button
                                                type="button"
                                                class="btn-close btn-close-white"
                                                data-bs-dismiss="modal"
                                                aria-label="Close"
                                            ></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label fw-bold"
                                                    >Mengapa sampel ini
                                                    ditolak?</label
                                                >
                                                <textarea
                                                    class="form-control"
                                                    rows="4"
                                                    v-model="formTolak.alasan"
                                                    placeholder="Masukkan alasan minimal 8 karakter..."
                                                ></textarea>
                                                <div
                                                    class="form-text text-danger"
                                                    v-if="
                                                        formTolak.alasan
                                                            .length > 0 &&
                                                        formTolak.alasan
                                                            .length < 8
                                                    "
                                                >
                                                    Minimal 8 karakter
                                                    (Sekarang:
                                                    {{
                                                        formTolak.alasan.length
                                                    }})
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button
                                                type="button"
                                                class="btn btn-light"
                                                data-bs-dismiss="modal"
                                            >
                                                Batal
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-danger"
                                                :disabled="
                                                    formTolak.alasan.length <
                                                        8 ||
                                                    loading.loadingAction
                                                "
                                                @click="submitTolak"
                                            >
                                                {{
                                                    loading.loadingAction
                                                        ? "Mengirim..."
                                                        : "Kirim Penolakan"
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="modal fade"
                                id="modalAlasanBatal"
                                tabindex="-1"
                                aria-hidden="true"
                                data-bs-backdrop="static"
                            >
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content shadow-lg">
                                        <div
                                            class="modal-header bg-danger text-white"
                                        >
                                            <h5
                                                class="modal-title text-capitalize text-white"
                                            >
                                                <i
                                                    class="ri-error-warning-line me-1"
                                                ></i>
                                                Alasan Pembatalan Sampel
                                            </h5>
                                            <button
                                                type="button"
                                                class="btn-close btn-close-white"
                                                data-bs-dismiss="modal"
                                                aria-label="Close"
                                            ></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label
                                                    class="form-label fw-bold"
                                                    >Mengapa sampel ini
                                                    dibatalkan secara
                                                    keseluruhan?</label
                                                >
                                                <textarea
                                                    class="form-control"
                                                    rows="4"
                                                    v-model="formBatal.alasan"
                                                    placeholder="Masukkan alasan minimal 8 karakter..."
                                                ></textarea>
                                                <div
                                                    class="form-text text-danger"
                                                    v-if="
                                                        formBatal.alasan
                                                            .length > 0 &&
                                                        formBatal.alasan
                                                            .length < 8
                                                    "
                                                >
                                                    Minimal 8 karakter
                                                    (Sekarang:
                                                    {{
                                                        formBatal.alasan.length
                                                    }})
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button
                                                type="button"
                                                class="btn btn-light"
                                                data-bs-dismiss="modal"
                                            >
                                                Kembali
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-danger"
                                                :disabled="
                                                    formBatal.alasan.length <
                                                        8 ||
                                                    loading.loadingAction
                                                "
                                                @click="submitBatal"
                                            >
                                                {{
                                                    loading.loadingAction
                                                        ? "Memproses..."
                                                        : "Batalkan Sampel"
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="loading.loadingDataList">
                                <div class="table-wrapper">
                                    <table
                                        class="skeleton-table"
                                        aria-busy="true"
                                    >
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>No Sampel</th>
                                                <th>Lock View</th>
                                                <th>Analisa Lab</th>
                                                <th>Palatabilitas</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="n in 5"
                                                :key="n"
                                                class="skeleton-row"
                                            >
                                                <td v-for="col in 6" :key="col">
                                                    <div
                                                        class="skeleton-cell"
                                                    ></div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div v-else>
                                <div
                                    v-if="detailDataList.length"
                                    class="table-responsive shadow-sm rounded"
                                >
                                    <table
                                        class="table table-bordered text-center align-middle"
                                    >
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-nowrap">No</th>
                                                <th class="text-nowrap">
                                                    No Sampel
                                                </th>
                                                <th class="text-nowrap">
                                                    Lock View
                                                </th>
                                                <th class="text-nowrap">
                                                    Analisa Lab
                                                </th>
                                                <th class="text-nowrap">
                                                    Palatabilitas
                                                </th>
                                                <th class="text-nowrap">
                                                    Aksi
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(
                                                    item, index
                                                ) in detailDataList"
                                                :key="index"
                                            >
                                                <td class="fw-medium">
                                                    {{
                                                        (pagination.page - 1) *
                                                            pagination.limit +
                                                        index +
                                                        1
                                                    }}
                                                </td>
                                                <td
                                                    class="fw-bold text-primary text-nowrap"
                                                >
                                                    <i
                                                        class="ri-qr-code-line me-1 align-middle text-muted"
                                                    ></i
                                                    >{{ item.No_Po_Sampel }}
                                                </td>
                                                <td class="text-nowrap">
                                                    <span
                                                        :class="
                                                            getBadgeClass(
                                                                item.status_lock_view
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            :class="
                                                                getBadgeIcon(
                                                                    item.status_lock_view
                                                                )
                                                            "
                                                        ></i
                                                        >{{
                                                            item.status_lock_view
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="text-nowrap">
                                                    <span
                                                        :class="
                                                            getBadgeClass(
                                                                item.status_analisa_lab
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            :class="
                                                                getBadgeIcon(
                                                                    item.status_analisa_lab
                                                                )
                                                            "
                                                        ></i
                                                        >{{
                                                            item.status_analisa_lab
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="text-nowrap">
                                                    <span
                                                        :class="
                                                            getBadgeClass(
                                                                item.status_palatabilitas
                                                            )
                                                        "
                                                    >
                                                        <i
                                                            :class="
                                                                getBadgeIcon(
                                                                    item.status_palatabilitas
                                                                )
                                                            "
                                                        ></i
                                                        >{{
                                                            item.status_palatabilitas
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="text-nowrap">
                                                    <div
                                                        class="d-flex justify-content-center gap-2"
                                                    >
                                                        <template
                                                            v-if="
                                                                !isSelesaiSemua(
                                                                    item
                                                                )
                                                            "
                                                        >
                                                            <button
                                                                @click="
                                                                    validasiData(
                                                                        item
                                                                    )
                                                                "
                                                                class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm d-flex align-items-center"
                                                            >
                                                                <i
                                                                    class="ri-shield-check-line me-1 fs-14"
                                                                ></i
                                                                >Validasi
                                                            </button>
                                                        </template>

                                                        <template v-else>
                                                            <button
                                                                @click="
                                                                    showModalFinalisasi(
                                                                        item
                                                                    )
                                                                "
                                                                class="btn btn-sm btn-success rounded-pill px-3 shadow-sm d-flex align-items-center"
                                                            >
                                                                <i
                                                                    class="ri-check-double-line me-1 fs-14"
                                                                ></i
                                                                >Validasi Final
                                                            </button>
                                                        </template>

                                                        <button
                                                            v-if="
                                                                item.has_validasi ==
                                                                1
                                                            "
                                                            @click="
                                                                openModalBatal(
                                                                    item.No_Po_Sampel
                                                                )
                                                            "
                                                            class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm d-flex align-items-center"
                                                        >
                                                            <i
                                                                class="ri-delete-bin-line me-1 fs-14"
                                                            ></i
                                                            >Batalkan Sampel
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div
                                    class="align-items-center mt-3 row g-3 text-center text-sm-start"
                                    v-if="
                                        pagination.totalData > pagination.limit
                                    "
                                >
                                    <div class="col-sm">
                                        <div class="text-muted">
                                            Total Data
                                            <span
                                                class="fw-semibold text-primary"
                                                >{{
                                                    pagination.totalData
                                                }}</span
                                            >
                                            Hasil
                                        </div>
                                    </div>
                                    <div class="col-sm-auto">
                                        <ul
                                            class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-start mb-0"
                                        >
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled:
                                                        pagination.page === 1,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="prevPage"
                                                    ><i
                                                        class="ri-arrow-left-s-line align-middle"
                                                    ></i
                                                ></a>
                                            </li>
                                            <li
                                                class="page-item"
                                                v-for="page in visiblePages"
                                                :key="page"
                                                :class="{
                                                    active:
                                                        page ===
                                                        pagination.page,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="
                                                        changePage(page)
                                                    "
                                                    >{{ page }}</a
                                                >
                                            </li>
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled:
                                                        pagination.page ===
                                                        pagination.totalPage,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="nextPage"
                                                    ><i
                                                        class="ri-arrow-right-s-line align-middle"
                                                    ></i
                                                ></a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div
                                    v-if="!detailDataList.length"
                                    class="d-flex justify-content-center mt-4 text-center"
                                >
                                    <div class="flex-column align-items-center">
                                        <DotLottieVue
                                            style="
                                                height: 120px;
                                                width: 120px;
                                                margin: 0 auto;
                                            "
                                            autoplay
                                            loop
                                            src="/animation/empty2.json"
                                        />
                                        <h6 class="text-muted mt-3 fw-medium">
                                            <i
                                                class="ri-error-warning-line align-middle me-1"
                                            ></i
                                            >Data Tidak Ditemukan!
                                        </h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="tab-pane"
                        :class="{ active: activeTab === 'desktop' }"
                    >
                        <div
                            class="alert alert-info border-0 shadow-sm mt-3 mb-3 d-flex align-items-start"
                        >
                            <i
                                class="ri-information-fill fs-20 me-3 mt-1 text-info"
                            ></i>
                            <div>
                                <strong class="text-info"
                                    >Informasi Laporan Desktop:</strong
                                ><br />
                                <span class="text-dark small"
                                    >Data pada daftar ini hanya bersifat
                                    informasi dan melacak status validasi sampel
                                    yang telah diterima oleh tim Formulator
                                    Desktop.</span
                                >
                            </div>
                        </div>

                        <div class="col-12">
                            <div v-if="loading.loadingDesktop">
                                <div class="table-wrapper">
                                    <table
                                        class="skeleton-table"
                                        aria-busy="true"
                                    >
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Nama Mesin</th>
                                                <th>Nama Barang</th>
                                                <th>No PO</th>
                                                <th>No Split</th>
                                                <th>Status Penerimaan</th>
                                                <th>Last Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="n in 5"
                                                :key="n"
                                                class="skeleton-row"
                                            >
                                                <td v-for="col in 7" :key="col">
                                                    <div
                                                        class="skeleton-cell"
                                                    ></div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div v-else>
                                <div
                                    v-if="desktopDataList.length"
                                    class="table-responsive shadow-sm rounded"
                                >
                                    <table
                                        class="table table-bordered text-center align-middle mb-0"
                                    >
                                        <thead class="table-light">
                                            <tr>
                                                <th width="50">#</th>
                                                <th>Nama Mesin</th>
                                                <th>Nama Barang</th>
                                                <th>No PO</th>
                                                <th>No Split PO</th>
                                                <th>Status Penerimaan</th>
                                                <th>Last Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template
                                                v-for="(
                                                    item, index
                                                ) in desktopDataList"
                                                :key="
                                                    item.No_Split_Po +
                                                    '_' +
                                                    item.Nama_Mesin
                                                "
                                            >
                                                <tr class="master-row bg-white">
                                                    <td>
                                                        <button
                                                            class="btn btn-sm btn-light border toggle-btn text-primary"
                                                            :class="{
                                                                expanded:
                                                                    expandedRows.includes(
                                                                        item.No_Split_Po +
                                                                            '_' +
                                                                            item.Nama_Mesin
                                                                    ),
                                                            }"
                                                            @click="
                                                                toggleDetail(
                                                                    item.No_Split_Po +
                                                                        '_' +
                                                                        item.Nama_Mesin
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="toggle-icon fw-bold"
                                                                >{{
                                                                    expandedRows.includes(
                                                                        item.No_Split_Po +
                                                                            "_" +
                                                                            item.Nama_Mesin
                                                                    )
                                                                        ? "▼"
                                                                        : "▶"
                                                                }}</span
                                                            >
                                                        </button>
                                                    </td>
                                                    <td class="fw-medium">
                                                        {{ item.Nama_Mesin }}
                                                    </td>
                                                    <td class="fw-medium">
                                                        {{ item.Nama_Barang }}
                                                    </td>
                                                    <td class="text-nowrap">
                                                        {{ item.No_Po }}
                                                    </td>
                                                    <td
                                                        class="fw-bold text-primary text-nowrap"
                                                    >
                                                        {{ item.No_Split_Po }}
                                                    </td>
                                                    <td class="text-nowrap">
                                                        <span
                                                            class="badge mb-1 px-2 py-1"
                                                            :class="
                                                                item.Status_Desktop ===
                                                                'Selesai Diterima'
                                                                    ? 'bg-success'
                                                                    : item.Status_Desktop ===
                                                                      'Diterima Sebagian'
                                                                    ? 'bg-warning text-dark'
                                                                    : 'bg-danger'
                                                            "
                                                        >
                                                            {{
                                                                item.Status_Desktop
                                                            }} </span
                                                        ><br />
                                                        <small
                                                            class="text-muted fw-medium"
                                                            >{{ item.count_y }}
                                                            /
                                                            {{
                                                                item.total_sampel
                                                            }}
                                                            Sampel</small
                                                        >
                                                    </td>
                                                    <td class="text-nowrap">
                                                        <div
                                                            v-if="
                                                                item.Last_Update_Tanggal
                                                            "
                                                        >
                                                            <span
                                                                class="d-block small fw-bold text-dark"
                                                                >{{
                                                                    item.Last_Update_Tanggal
                                                                }}</span
                                                            >
                                                            <span
                                                                class="d-block small text-muted"
                                                                >{{
                                                                    item.Last_Update_Jam
                                                                }}</span
                                                            >
                                                            <span
                                                                class="d-block small text-primary fw-medium"
                                                                ><i
                                                                    class="ri-user-line me-1"
                                                                ></i
                                                                >{{
                                                                    item.Validasi_Oleh
                                                                }}</span
                                                            >
                                                        </div>
                                                        <span
                                                            v-else
                                                            class="text-muted"
                                                            >-</span
                                                        >
                                                    </td>
                                                </tr>

                                                <tr
                                                    v-show="
                                                        expandedRows.includes(
                                                            item.No_Split_Po +
                                                                '_' +
                                                                item.Nama_Mesin
                                                        )
                                                    "
                                                    class="detail-row bg-light"
                                                >
                                                    <td
                                                        colspan="7"
                                                        class="text-start p-3 border-bottom"
                                                    >
                                                        <div
                                                            class="bg-white p-3 border rounded shadow-sm"
                                                        >
                                                            <h6
                                                                class="fw-bold mb-3 text-primary"
                                                            >
                                                                <i
                                                                    class="ri-list-check me-2"
                                                                ></i
                                                                >Rincian Sampel:
                                                                {{
                                                                    item.No_Split_Po
                                                                }}
                                                                ({{
                                                                    item.Nama_Mesin
                                                                }})
                                                            </h6>
                                                            <table
                                                                class="table table-sm table-bordered text-center align-middle mb-0"
                                                            >
                                                                <thead
                                                                    class="table-secondary"
                                                                >
                                                                    <tr>
                                                                        <th>
                                                                            No
                                                                            Sampel
                                                                        </th>
                                                                        <th>
                                                                            Status
                                                                            Validasi
                                                                            Formulator
                                                                        </th>
                                                                        <th>
                                                                            Tgl
                                                                            Verifikasi
                                                                        </th>
                                                                        <th>
                                                                            Jam
                                                                            Verifikasi
                                                                        </th>
                                                                        <th>
                                                                            Diverifikasi
                                                                            Oleh
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <tr
                                                                        v-for="child in item.Detail_Sampel"
                                                                        :key="
                                                                            child.No_Sampel
                                                                        "
                                                                    >
                                                                        <td
                                                                            class="fw-bold"
                                                                        >
                                                                            {{
                                                                                child.No_Sampel
                                                                            }}
                                                                        </td>
                                                                        <td>
                                                                            <span
                                                                                class="badge"
                                                                                :class="
                                                                                    child.Flag_Validasi_Formulator_Desktop ===
                                                                                    'Y'
                                                                                        ? 'bg-success'
                                                                                        : 'bg-secondary'
                                                                                "
                                                                            >
                                                                                {{
                                                                                    child.Flag_Validasi_Formulator_Desktop ===
                                                                                    "Y"
                                                                                        ? "Sudah Diterima"
                                                                                        : "Menunggu"
                                                                                }}
                                                                            </span>
                                                                        </td>
                                                                        <td>
                                                                            {{
                                                                                child.Tanggal_Validasi_Formulator_Desktop ||
                                                                                "-"
                                                                            }}
                                                                        </td>
                                                                        <td>
                                                                            {{
                                                                                child.Jam_Validasi_Formulator_Desktop ||
                                                                                "-"
                                                                            }}
                                                                        </td>
                                                                        <td>
                                                                            {{
                                                                                child.Id_User_Validasi_Formulator_Desktop ||
                                                                                "-"
                                                                            }}
                                                                        </td>
                                                                    </tr>
                                                                    <tr
                                                                        v-if="
                                                                            !item.Detail_Sampel ||
                                                                            item
                                                                                .Detail_Sampel
                                                                                .length ===
                                                                                0
                                                                        "
                                                                    >
                                                                        <td
                                                                            colspan="5"
                                                                            class="text-muted fst-italic"
                                                                        >
                                                                            Tidak
                                                                            ada
                                                                            detail
                                                                            sampel.
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div
                                    class="align-items-center mt-3 row g-3 text-center text-sm-start"
                                    v-if="
                                        paginationDesktop.totalData >
                                        paginationDesktop.limit
                                    "
                                >
                                    <div class="col-sm">
                                        <div class="text-muted">
                                            Total Data
                                            <span
                                                class="fw-semibold text-primary"
                                                >{{
                                                    paginationDesktop.totalData
                                                }}</span
                                            >
                                            Hasil
                                        </div>
                                    </div>
                                    <div class="col-sm-auto">
                                        <ul
                                            class="pagination pagination-separated pagination-sm justify-content-center justify-content-sm-start mb-0"
                                        >
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled:
                                                        paginationDesktop.page ===
                                                        1,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="
                                                        prevPageDesktop
                                                    "
                                                    ><i
                                                        class="ri-arrow-left-s-line align-middle"
                                                    ></i
                                                ></a>
                                            </li>
                                            <li
                                                class="page-item"
                                                v-for="page in visiblePagesDesktop"
                                                :key="page"
                                                :class="{
                                                    active:
                                                        page ===
                                                        paginationDesktop.page,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="
                                                        changePageDesktop(page)
                                                    "
                                                    >{{ page }}</a
                                                >
                                            </li>
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled:
                                                        paginationDesktop.page ===
                                                        paginationDesktop.totalPage,
                                                }"
                                            >
                                                <a
                                                    href="#"
                                                    class="page-link"
                                                    @click.prevent="
                                                        nextPageDesktop
                                                    "
                                                    ><i
                                                        class="ri-arrow-right-s-line align-middle"
                                                    ></i
                                                ></a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div
                                    v-if="!desktopDataList.length"
                                    class="d-flex justify-content-center mt-4 text-center"
                                >
                                    <div class="flex-column align-items-center">
                                        <DotLottieVue
                                            style="
                                                height: 120px;
                                                width: 120px;
                                                margin: 0 auto;
                                            "
                                            autoplay
                                            loop
                                            src="/animation/empty2.json"
                                        />
                                        <h6 class="text-muted mt-3 fw-medium">
                                            <i
                                                class="ri-error-warning-line align-middle me-1"
                                            ></i
                                            >Data Desktop Tidak Ditemukan!
                                        </h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- modal -->
        <div
            class="modal fade"
            id="printModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-primary">
                        <h5 class="modal-title text-white">
                            <i class="fas fa-print me-2"></i>
                            Cetak Laporan Analisis Sampel
                        </h5>
                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            @click="closePrintModal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div
                            class="steps-progress mb-4 d-flex justify-content-center gap-4"
                        >
                            <div
                                class="step"
                                :class="{ active: currentStep === 1 }"
                            >
                                <div
                                    class="step-number bg-primary text-white rounded-circle text-center"
                                    style="
                                        width: 30px;
                                        height: 30px;
                                        line-height: 30px;
                                    "
                                >
                                    1
                                </div>
                                <div
                                    class="step-label mt-1 text-center fw-bold"
                                >
                                    Pilih Sampel
                                </div>
                            </div>
                            <div
                                class="step"
                                :class="{ active: currentStep === 2 }"
                            >
                                <div
                                    class="step-number text-center rounded-circle"
                                    :class="
                                        currentStep === 2
                                            ? 'bg-primary text-white'
                                            : 'bg-secondary text-white'
                                    "
                                    style="
                                        width: 30px;
                                        height: 30px;
                                        line-height: 30px;
                                    "
                                >
                                    2
                                </div>
                                <div
                                    class="step-label mt-1 text-center fw-bold"
                                >
                                    Preview & Cetak
                                </div>
                            </div>
                        </div>

                        <div
                            class="alert alert-warning d-flex align-items-start gap-2"
                            role="alert"
                        >
                            <i
                                class="fas fa-exclamation-triangle text-warning fa-lg mt-1"
                            ></i>
                            <div>
                                <strong>Perhatian!</strong><br />
                                Pilih nomor sampel yang ingin Anda cetak
                                laporannya. Pastikan sampel tersebut sudah
                                difinalisasi.
                            </div>
                        </div>

                        <div v-show="currentStep === 1" class="step-content">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="fas fa-flask me-2"></i> Pilih Nomor
                                Sampel
                            </h6>

                            <div class="input-group mb-3">
                                <span class="input-group-text"
                                    ><i class="fas fa-search"></i
                                ></span>
                                <input
                                    type="text"
                                    class="form-control"
                                    v-model="searchSampleQuery"
                                    placeholder="Cari berdasarkan Nomor Sampel..."
                                />
                            </div>

                            <div
                                class="analysis-selector list-group"
                                style="max-height: 300px; overflow-y: auto"
                            >
                                <button
                                    v-for="item in filteredPrintSamples"
                                    :key="item.No_Po_Sampel"
                                    type="button"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                    :class="{
                                        active:
                                            selectedPrintSample ===
                                            item.No_Po_Sampel,
                                    }"
                                    @click="
                                        selectedPrintSample = item.No_Po_Sampel
                                    "
                                >
                                    <div>
                                        <i class="fas fa-vial me-2"></i>
                                        <span class="fw-bold">{{
                                            item.No_Po_Sampel
                                        }}</span>
                                    </div>
                                    <i
                                        v-if="
                                            selectedPrintSample ===
                                            item.No_Po_Sampel
                                        "
                                        class="fas fa-check-circle text-white"
                                    ></i>
                                </button>

                                <div
                                    v-if="filteredPrintSamples.length === 0"
                                    class="text-center text-muted p-3"
                                >
                                    <i class="fas fa-box-open fa-2x mb-2"></i>
                                    <p>Data sampel tidak ditemukan.</p>
                                </div>
                            </div>
                        </div>

                        <div v-show="currentStep === 2" class="step-content">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="fas fa-eye me-2"></i> Ringkasan
                                Laporan
                            </h6>

                            <div class="report-summary">
                                <div class="summary-card">
                                    <div class="summary-header">
                                        <i class="fas fa-info-circle"></i>
                                        <h5>Detail Laporan</h5>
                                    </div>
                                    <div class="summary-body">
                                        <div class="summary-item">
                                            <span>Nomor Sampel Terpilih:</span>
                                            <strong
                                                class="ms-2 text-primary fs-5"
                                                >{{
                                                    selectedPrintSample || "-"
                                                }}</strong
                                            >
                                        </div>

                                        <div class="summary-item mb-3">
                                            <span class="d-block mb-1"
                                                >Jenis Cetakan:</span
                                            >
                                            <el-select
                                                v-model="selectedJenisPrint"
                                                placeholder="Pilih Jenis Cetakan"
                                                style="width: 100%"
                                            >
                                                <el-option
                                                    label="Cetak Ringkas (Hasil Akhir Analisa Saja)"
                                                    value="ringkas"
                                                />

                                                <el-option
                                                    label="Cetak Detail (Per Analisa)"
                                                    value="detail"
                                                />
                                            </el-select>
                                        </div>

                                        <div class="summary-item">
                                            <span>Format:</span>
                                            <div class="format-options">
                                                <div
                                                    class="form-check form-check-inline"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        id="formatExcel"
                                                        value="excel"
                                                        v-model="exportFormat"
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        for="formatExcel"
                                                    >
                                                        <i
                                                            class="far fa-file-excel text-success me-1"
                                                        ></i>
                                                        Excel
                                                    </label>
                                                </div>
                                                <!-- <div
                                                    class="form-check form-check-inline"
                                                >
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        id="formatPdf"
                                                        value="pdf"
                                                        v-model="exportFormat"
                                                    />
                                                    <label
                                                        class="form-check-label"
                                                        for="formatPdf"
                                                    >
                                                        <i
                                                            class="far fa-file-pdf text-danger me-1"
                                                        ></i>
                                                        Pdf
                                                    </label>
                                                </div> -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-note mt-3">
                                    <div
                                        v-if="isPszReportSelected"
                                        class="psz-warning-section mt-3"
                                    >
                                        <div
                                            class="alert alert-warning d-flex align-items-start gap-2"
                                            role="alert"
                                        >
                                            <i
                                                class="fas fa-exclamation-triangle text-warning fa-lg mt-1"
                                            ></i>
                                            <div>
                                                <strong
                                                    >Peringatan Khusus!</strong
                                                ><br />
                                                Anda memilih "Final Report
                                                Particle Size". Laporan ini
                                                <strong
                                                    >hanya akan mencetak data
                                                    dari analisa Particle Size
                                                    (PSZ)</strong
                                                >, meskipun Anda memilih
                                                beberapa jenis analisa lain
                                                (contoh: Ash, Moisture, dll).

                                                <!-- Checkbox di bawah teks -->
                                                <div class="form-check mt-2">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        v-model="
                                                            pszConfirmation
                                                        "
                                                        id="pszConfirmCheck"
                                                    />
                                                    <label
                                                        class="form-check-label fw-bold"
                                                        for="pszConfirmCheck"
                                                    >
                                                        Saya mengerti dan setuju
                                                        untuk melanjutkan.
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Laporan akan dihasilkan berdasarkan
                                        kriteria di atas. Pastikan data sudah
                                        benar sebelum mencetak.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            @click="currentStep--"
                            v-if="currentStep === 2"
                        >
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="currentStep++"
                            v-if="currentStep === 1"
                            :disabled="!selectedPrintSample"
                        >
                            Lanjut <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                        <button
                            type="button"
                            class="btn btn-success"
                            @click="generateReport"
                            v-if="currentStep === 2"
                            :disabled="
                                !selectedPrintSample || loading.loadingAction
                            "
                        >
                            <i class="fas fa-file-export me-1"></i>
                            {{
                                loading.loadingAction
                                    ? "Memproses..."
                                    : "Generate Laporan"
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { debounce } from "lodash";
import axios from "axios";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import {
    ElSelect,
    ElOption,
    ElMessage,
    ElStep,
    ElSteps,
    ElMessageBox,
    ElNotification,
} from "element-plus";

export default {
    components: {
        DotLottieVue,
        ElStep,
        ElSteps,
        ElMessageBox,
        ElNotification,
        ElSelect,
        ElOption,
    },
    data() {
        return {
            activeTab: "prafinalisasi",
            searchQuery: "",
            detailDataList: [],
            listKlasifikasi: [],
            detailValidasiData: [],
            selectedJenisPrint: "",
            selectedSampel: "",
            activeStep: 0,

            formTolak: { alasan: "" },
            formBatal: { noPo: "", alasan: "" },
            loading: {
                loadingDataList: false,
                loadingModal: false,
                loadingAction: false,
                loadingDesktop: false,
            },
            pagination: { page: 1, limit: 10, totalPage: 0, totalData: 0 },
            desktopDataList: [],
            paginationDesktop: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            expandedRows: [],

            currentStep: 1,
            searchSampleQuery: "",
            selectedPrintSample: null,
            exportFormat: "excel",
            acknowledgementChecked: false,
        };
    },
    computed: {
        currentStepData() {
            if (!this.detailValidasiData.length || !this.listKlasifikasi.length)
                return null;
            const activeIndex =
                this.activeStep >= this.listKlasifikasi.length
                    ? this.listKlasifikasi.length - 1
                    : this.activeStep;
            const currentAktivitas =
                this.listKlasifikasi[activeIndex].Kode_Aktivitas_Lab;
            return this.detailValidasiData.find(
                (item) => item.Kode_Aktivitas_Lab === currentAktivitas
            );
        },
        isStepTerkunci() {
            return (
                this.currentStepData &&
                this.currentStepData.status_step === "TERKUNCI"
            );
        },
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = Math.max(1, current - 2);
            let end = Math.min(total, current + 2);
            if (total <= 5) {
                start = 1;
                end = total;
            }
            const pages = [];
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
        hasNextStepAvailable() {
            if (!this.detailValidasiData.length || !this.currentStepData)
                return false;
            if (this.currentStepData.status_step === "MENUNGGU VALIDASI")
                return false;
            const nextIndex = this.activeStep + 1;
            if (nextIndex < this.detailValidasiData.length) {
                const nextStep = this.detailValidasiData[nextIndex];
                return nextStep.status_step !== "TERKUNCI";
            }
            return false;
        },
        filteredPrintSamples() {
            if (!this.searchSampleQuery) return this.detailDataList;
            return this.detailDataList.filter((item) =>
                item.No_Po_Sampel.toLowerCase().includes(
                    this.searchSampleQuery.toLowerCase()
                )
            );
        },
        showPszOption() {
            return true;
        },
        isPszReportSelected() {
            return this.selectedJenisPrint === "psz";
        },
        isGenerateButtonDisabled() {
            if (!this.selectedPrintSample) return true;
            if (!this.selectedJenisPrint) return true;
            if (this.isPszReportSelected && !this.pszConfirmation) return true;
            if (this.loading.loadingAction) return true;
            return false;
        },
    },
    watch: {
        activeStep() {
            this.acknowledgementChecked = false;
        },
    },
    methods: {
        switchTab(tab) {
            this.activeTab = tab;
            this.searchQuery = "";
            if (tab === "desktop" && this.desktopDataList.length === 0) {
                this.fetchDesktop();
            } else if (
                tab === "prafinalisasi" &&
                this.detailDataList.length === 0
            ) {
                this.fetchValidasi();
            }
        },
        toggleDetail(splitPo) {
            const index = this.expandedRows.indexOf(splitPo);
            if (index > -1) {
                this.expandedRows.splice(index, 1);
            } else {
                this.expandedRows.push(splitPo);
            }
        },
        togglePrintModal() {
            this.currentStep = 1;
            this.searchSampleQuery = "";
            this.selectedPrintSample = null;
            const modal = new bootstrap.Modal(
                document.getElementById("printModal")
            );
            modal.show();
        },
        closePrintModal() {
            const modalElement = document.getElementById("printModal");
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();
        },
        async generateReport() {
            Swal.fire({
                title: "Mohon Tunggu",
                html: "Laporan sedang diproses dan dibuat...",
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");
                let urlLink;
                const exportFormat = this.exportFormat;

                if (this.selectedJenisPrint === "psz") {
                    if (!this.pszConfirmation) {
                        Swal.fire(
                            "Peringatan",
                            "Konfirmasi PSZ harus dicentang.",
                            "warning"
                        );
                        return;
                    }
                    urlLink =
                        exportFormat === "pdf"
                            ? "/rekap-sampel/pdf/particle-size"
                            : "/rekap-sampel/excell/particle-size";
                } else {
                    const isRingkas = this.selectedJenisPrint === "ringkas";
                    urlLink =
                        exportFormat === "pdf"
                            ? isRingkas
                                ? "/api/v2/formulator/rekap-sampel/pdf"
                                : "/api/v1/formulator/rekap-sampel/pdf"
                            : isRingkas
                            ? "/api/v2/formulator/rekap-sampel/excell/pra-finalisasi"
                            : "/api/v1/formulator/download-rekap/analisa/pra-finalisasi";
                }

                const payload = {
                    No_Po_Sampel: this.selectedPrintSample,
                    format: exportFormat,
                };

                const response = await axios.post(urlLink, payload, {
                    headers: { "X-CSRF-TOKEN": csrfToken },
                    responseType: "blob",
                });

                Swal.close();

                const blob = new Blob([response.data], {
                    type: response.headers["content-type"],
                });
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement("a");
                link.href = url;

                let fileName = `laporan-rekap-sampel-${
                    this.selectedPrintSample
                }.${exportFormat === "pdf" ? "pdf" : "xlsx"}`;
                const contentDisposition =
                    response.headers["content-disposition"] ||
                    response.headers["Content-Disposition"];

                if (contentDisposition) {
                    const fileNameMatch = contentDisposition.match(
                        /filename\*?=(?:(?:UTF-8'')?["']?)([^;"']+)/i
                    );
                    if (fileNameMatch && fileNameMatch[1]) {
                        fileName = decodeURIComponent(fileNameMatch[1]);
                    }
                }

                link.setAttribute("download", fileName);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);

                ElMessage.success("Laporan berhasil diunduh.");
            } catch (error) {
                console.error("Gagal membuat laporan:", error);
                try {
                    const reader = new FileReader();
                    reader.onload = () => {
                        try {
                            const responseText = reader.result;
                            const parsed = JSON.parse(responseText);
                            const message =
                                parsed?.message ||
                                "Terjadi kesalahan saat membuat laporan.";
                            Swal.fire({
                                icon: "warning",
                                title: "Gagal/Tidak Ditemukan",
                                text: message,
                            });
                        } catch (parseError) {
                            Swal.fire({
                                icon: "error",
                                title: "Gagal Memproses Laporan",
                                text: "Terjadi kesalahan internal. Silakan coba lagi nanti.",
                            });
                        }
                    };
                    reader.readAsText(error.response?.data);
                } catch (readError) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal Memproses Laporan",
                        text: "Terjadi kesalahan tidak terduga.",
                    });
                }
            }
        },
        getBadgeIcon(status) {
            if (status === "DISETUJUI")
                return "ri-checkbox-circle-fill align-middle fs-14 me-1";
            if (status === "TIDAK ADA")
                return "ri-close-circle-fill align-middle fs-14 me-1";
            return "ri-timer-fill align-middle fs-14 me-1";
        },
        getBadgeClass(status) {
            if (status === "DISETUJUI") return "badge bg-success";
            if (status === "TIDAK ADA") return "badge bg-secondary";
            return "badge bg-warning text-dark";
        },
        async fetchKlasifikasiAktivitas() {
            try {
                const response = await axios.get(
                    "/api/v1/validasi/pra-finalisasi/options/klasifikasi-lab"
                );
                if (response.status === 200)
                    this.listKlasifikasi = response.data.result;
            } catch (error) {
                console.error(error);
            }
        },
        async validasiData(item) {
            this.selectedSampel = item.No_Po_Sampel;
            this.activeStep = 0;
            this.detailValidasiData = [];
            this.acknowledgementChecked = false;
            const modalElement = document.getElementById("modalValidasi");
            if (modalElement)
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            this.loading.loadingModal = true;
            try {
                const response = await axios.get(
                    `/api/v1/validasi/pra-finalisasi/detail/by/${item.No_Po_Sampel}`
                );
                if (response.status === 200 && response.data.result?.steps) {
                    this.detailValidasiData = response.data.result.steps;
                    const activeIndex = this.detailValidasiData.findIndex(
                        (step) => step.status_step === "MENUNGGU VALIDASI"
                    );
                    if (activeIndex !== -1) this.activeStep = activeIndex;
                    else if (
                        this.detailValidasiData.every(
                            (step) => step.status_step === "DISETUJUI"
                        )
                    )
                        this.activeStep = this.listKlasifikasi.length;
                    else {
                        const firstNotOk = this.detailValidasiData.findIndex(
                            (step) => step.status_step !== "DISETUJUI"
                        );
                        this.activeStep = firstNotOk !== -1 ? firstNotOk : 0;
                    }
                }
            } catch (error) {
                ElMessage.error("Gagal memuat detail.");
            } finally {
                this.loading.loadingModal = false;
            }
        },
        goToNextStep() {
            if (this.hasNextStepAvailable) {
                this.activeStep++;
            }
        },
        lihatFoto(url) {
            if (url) window.open(url, "_blank", "noopener,noreferrer");
        },
        isSelesaiSemua(item) {
            return (
                item.status_lock_view === "DISETUJUI" &&
                item.status_analisa_lab === "DISETUJUI" &&
                item.status_palatabilitas === "DISETUJUI"
            );
        },
        resetModalValidasi() {
            this.selectedSampel = "";
            this.detailValidasiData = [];
            this.activeStep = 0;
            this.acknowledgementChecked = false;
        },
        async setujuiValidasi() {
            if (!this.currentStepData) return;
            this.loading.loadingAction = true;
            try {
                const payload = {
                    No_Po_Sampel: this.selectedSampel,
                    Kode_Aktivitas_Lab: this.currentStepData.Kode_Aktivitas_Lab,
                    Status_Action: "setuju",
                    Force_Submit: this.acknowledgementChecked,
                    Items: this.currentStepData.data_analisa.map((item) => ({
                        No_Fak_Sub_Po: item.No_Fak_Sub_Po,
                    })),
                };
                const res = await axios.post(
                    "/api/v1/validasi/pra-finalisasi/store-hirarki",
                    payload
                );
                if (res.status === 200) {
                    ElMessage.success("Tahap berhasil disetujui.");
                    this.validasiData({ No_Po_Sampel: this.selectedSampel });
                    this.fetchValidasi();
                }
            } catch (e) {
                ElMessage.error("Gagal menyetujui.");
            } finally {
                this.loading.loadingAction = false;
            }
        },
        openModalTolak() {
            this.formTolak.alasan = "";
            const modal = new bootstrap.Modal(
                document.getElementById("modalAlasanTolak")
            );
            modal.show();
        },
        async submitTolak() {
            this.loading.loadingAction = true;
            try {
                const payload = {
                    No_Po_Sampel: this.selectedSampel,
                    Kode_Aktivitas_Lab: this.currentStepData.Kode_Aktivitas_Lab,
                    Status_Action: "tolak",
                    Alasan: this.formTolak.alasan,
                    Force_Submit: this.acknowledgementChecked,
                    Items: this.currentStepData.data_analisa.map((item) => ({
                        No_Fak_Sub_Po: item.No_Fak_Sub_Po,
                    })),
                };
                const res = await axios.post(
                    "/api/v1/validasi/pra-finalisasi/store-hirarki",
                    payload
                );
                if (res.status === 200) {
                    ElMessage.warning("Data berhasil ditolak.");
                    bootstrap.Modal.getInstance(
                        document.getElementById("modalAlasanTolak")
                    ).hide();
                    this.validasiData({ No_Po_Sampel: this.selectedSampel });
                    this.fetchValidasi();
                }
            } catch (e) {
                ElMessage.error("Gagal menolak.");
            } finally {
                this.loading.loadingAction = false;
            }
        },
        openModalBatal(noPo) {
            this.formBatal.noPo = noPo;
            this.formBatal.alasan = "";
            const modal = new bootstrap.Modal(
                document.getElementById("modalAlasanBatal")
            );
            modal.show();
        },
        async submitBatal() {
            this.loading.loadingAction = true;
            try {
                const payload = {
                    No_Po_Sampel: this.formBatal.noPo,
                    Alasan: this.formBatal.alasan,
                };
                const res = await axios.post(
                    "/api/v1/formulator/validasi/pra-finalisasi/cancel",
                    payload
                );
                if (res.status === 200) {
                    ElMessage.success(
                        "Sampel berhasil dibatalkan secara keseluruhan."
                    );
                    bootstrap.Modal.getInstance(
                        document.getElementById("modalAlasanBatal")
                    ).hide();
                    this.fetchValidasi();
                }
            } catch (e) {
                ElMessage.error("Gagal membatalkan sampel.");
            } finally {
                this.loading.loadingAction = false;
            }
        },
        async showModalFinalisasi(item) {
            try {
                await ElMessageBox.confirm(
                    `Apakah Anda yakin ingin memfinalisasi sampel ${item.No_Po_Sampel}?`,
                    "Konfirmasi Finalisasi",
                    {
                        confirmButtonText: "Ya, Finalisasi",
                        cancelButtonText: "Batal",
                        type: "success",
                        center: true,
                    }
                );

                this.loading.loadingAction = true;

                await axios.post(
                    `/api/v1/formulator/validasi/pra-finalisasi/approve`,
                    { No_Po_Sampel: item.No_Po_Sampel }
                );

                ElMessage.success("Sampel Berhasil Di-Finalisasi.");
                this.fetchValidasi();
            } catch (error) {
                if (error !== "cancel") {
                    if (error.response && error.response.status === 422) {
                        const responseData = error.response.data;
                        const daftarBermasalah =
                            responseData.detail?.Analisa_Bermasalah || [];

                        let htmlList = "";
                        if (daftarBermasalah.length > 0) {
                            htmlList =
                                "<ul class='mt-2 ps-3 mb-0 text-start' style='font-size: 13px;'>";
                            daftarBermasalah.forEach((analisa) => {
                                htmlList += `<li><strong>${analisa}</strong></li>`;
                            });
                            htmlList += "</ul>";
                        }

                        ElNotification({
                            title: "Perhatian",
                            message: `<div class='text-start'>${responseData.message}${htmlList}</div>`,
                            dangerouslyUseHTMLString: true,
                            type: "warning",
                            duration: 8000,
                        });
                    } else {
                        ElMessage.error(
                            error.response?.data?.message ||
                                "Gagal melakukan finalisasi."
                        );
                    }
                }
            } finally {
                this.loading.loadingAction = false;
            }
        },
        async fetchValidasi(page = 1, query = "") {
            this.loading.loadingDataList = true;
            try {
                const res = await axios.get(
                    "/api/v1/validasi/pra-finalisasi/current-home",
                    { params: { page, limit: 10, search: query } }
                );
                if (res.status === 200 && res.data?.result) {
                    this.detailDataList = res.data.result;
                    this.pagination = {
                        page: res.data.pagination.current_page,
                        totalPage: res.data.pagination.total_pages,
                        totalData: res.data.pagination.total,
                        limit: res.data.pagination.per_page,
                    };
                }
            } catch (e) {
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        async fetchDesktop(page = 1, query = "") {
            this.loading.loadingDesktop = true;
            try {
                // GANTI URL INI SESUAI DENGAN ROUTE API BACKEND ANDA
                const res = await axios.get(
                    "/api/v1/validasi/pra-finalisasi/informasi/validasi-desktop",
                    { params: { page, limit: 10, search: query } }
                );
                if (res.status === 200 && res.data?.result) {
                    this.desktopDataList = res.data.result;
                    this.paginationDesktop = {
                        page: res.data.pagination.current_page,
                        totalPage: res.data.pagination.total_pages,
                        totalData: res.data.pagination.total,
                        limit: res.data.pagination.per_page,
                    };
                }
            } catch (e) {
                this.desktopDataList = [];
            } finally {
                this.loading.loadingDesktop = false;
            }
        },
        nextPageDesktop() {
            if (this.paginationDesktop.page < this.paginationDesktop.totalPage)
                this.fetchDesktop(
                    this.paginationDesktop.page + 1,
                    this.searchQuery
                );
        },
        prevPageDesktop() {
            if (this.paginationDesktop.page > 1)
                this.fetchDesktop(
                    this.paginationDesktop.page - 1,
                    this.searchQuery
                );
        },
        changePageDesktop(page) {
            if (page !== this.paginationDesktop.page)
                this.fetchDesktop(page, this.searchQuery);
        },

        handleSearch: debounce(function () {
            if (this.activeTab === "prafinalisasi") {
                this.pagination.page = 1;
                this.fetchValidasi(1, this.searchQuery);
            } else {
                this.paginationDesktop.page = 1;
                this.fetchDesktop(1, this.searchQuery);
            }
        }, 500),

        nextPage() {
            if (this.pagination.page < this.pagination.totalPage)
                this.fetchValidasi(this.pagination.page + 1, this.searchQuery);
        },
        prevPage() {
            if (this.pagination.page > 1)
                this.fetchValidasi(this.pagination.page - 1, this.searchQuery);
        },
        changePage(page) {
            if (page !== this.pagination.page)
                this.fetchValidasi(page, this.searchQuery);
        },
    },
    mounted() {
        this.fetchKlasifikasiAktivitas();
        this.fetchValidasi();
    },
};
</script>

<style scoped>
.toggle-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.toggle-btn.expanded {
    background-color: #3a0ca3 !important;
    color: white !important;
    border-color: #3a0ca3 !important;
}
.nav-tabs-custom .nav-item .nav-link.active {
    color: #3a0ca3;
    background-color: transparent;
    border-bottom: 2px solid #3a0ca3;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table.skeleton-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.skeleton-table thead th {
    padding: 12px;
    text-align: left;
    border: 1px solid #ccc;
    background-color: #f9f9f9;
}

.skeleton-row .skeleton-cell {
    position: relative;
    height: 40px;
    background: #e0e0e0;
    border-radius: 6px;
    margin: 6px 0;
    overflow: hidden;
}

.skeleton-cell::after {
    content: "";
    position: absolute;
    top: 0;
    left: -150px;
    width: 150px;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.4),
        transparent
    );
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    100% {
        left: 100%;
    }
}

@media (max-width: 600px) {
    .skeleton-cell {
        height: 30px;
    }
}
</style>
<style>
/* Existing styles remain */
.print-controls {
    position: sticky;
    top: 20px;
    z-index: 100;
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
    padding-right: 15px;
}

.btn-print-action {
    background: linear-gradient(135deg, #405189 0%, #384677 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-print-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.btn-print-action:active {
    transform: translateY(0);
}

.btn-print-action i {
    font-size: 1.1rem;
}

.btn-text {
    font-size: 0.95rem;
}

.tooltip {
    position: absolute;
    bottom: -40px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.btn-print-action:hover .tooltip {
    opacity: 1;
}

/* Perbaikan untuk tombol di modal */
.modal-footer .btn {
    min-width: 120px;
    padding: 10px 15px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}
/* New styles for print feature */
.btn-print-floating {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    border-radius: 50px;
    padding: 12px 20px;
    font-weight: 600;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.btn-print-floating:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

/* Steps progress */
.steps-progress {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-bottom: 30px;
}

.steps-progress::before {
    content: "";
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 3px;
    background-color: #e9ecef;
    z-index: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    flex: 1;
}

.step.active .step-number {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.step.active .step-label {
    color: #0d6efd;
    font-weight: 600;
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.step-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-align: center;
}

/* Analysis selector */
.analysis-selector {
    max-height: 400px; /* Atur sesuai kebutuhan */
    overflow-y: auto;
    padding-right: 8px; /* Supaya isi tidak terpotong oleh scrollbar */
}

/* Optional: Kustom scrollbar (untuk Webkit-based browsers seperti Chrome, Edge, Safari) */
.analysis-selector::-webkit-scrollbar {
    width: 8px;
}

.analysis-selector::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.analysis-selector::-webkit-scrollbar-thumb {
    background: #405189;
    border-radius: 4px;
}

.analysis-selector::-webkit-scrollbar-thumb:hover {
    background: #1e2849;
}

.analysis-option {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: white;
}

.analysis-option:hover {
    border-color: #86b7fe;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
}

.analysis-option.selected {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.option-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: rgba(13, 110, 253, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: #0d6efd;
    font-size: 1.1rem;
}

.option-details {
    flex: 1;
}

.option-details h6 {
    margin: 0;
    font-size: 0.95rem;
}

.option-check {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #0d6efd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.analysis-option.selected .option-check {
    opacity: 1;
}

/* Date range picker */
.date-range-picker {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.date-presets {
    background-color: white;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

/* Report summary */
.report-summary {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.summary-card {
    background-color: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    overflow: hidden;
}

.summary-header {
    background-color: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
}

.summary-header i {
    color: #0d6efd;
    margin-right: 10px;
    font-size: 1.1rem;
}

.summary-header h5 {
    margin: 0;
    font-size: 1rem;
}

.summary-body {
    padding: 15px;
}

.summary-item {
    display: flex;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px dashed #e9ecef;
}

.summary-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.summary-item span {
    width: 120px;
    color: #6c757d;
    font-size: 0.9rem;
}

.summary-item strong {
    flex: 1;
    font-weight: 500;
}

.format-options {
    display: flex;
    gap: 15px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .steps-progress {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }

    .steps-progress::before {
        display: none;
    }

    .step {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }

    .step-number {
        margin-bottom: 0;
    }

    .summary-item {
        flex-direction: column;
        gap: 5px;
    }

    .summary-item span {
        width: auto;
    }
}
</style>
<style>
/* Existing styles remain */
.print-controls {
    position: sticky;
    top: 20px;
    z-index: 100;
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
    padding-right: 15px;
}

.btn-print-action {
    background: linear-gradient(135deg, #405189 0%, #384677 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px 20px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn-print-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.btn-print-action:active {
    transform: translateY(0);
}

.btn-print-action i {
    font-size: 1.1rem;
}

.btn-text {
    font-size: 0.95rem;
}

.tooltip {
    position: absolute;
    bottom: -40px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.btn-print-action:hover .tooltip {
    opacity: 1;
}

/* Perbaikan untuk tombol di modal */
.modal-footer .btn {
    min-width: 120px;
    padding: 10px 15px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-success {
    background-color: #28a745;
    border-color: #28a745;
}

.btn-success:hover {
    background-color: #218838;
    border-color: #1e7e34;
}
/* New styles for print feature */
.btn-print-floating {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
    border-radius: 50px;
    padding: 12px 20px;
    font-weight: 600;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.btn-print-floating:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
}

/* Steps progress */
.steps-progress {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin-bottom: 30px;
}

.steps-progress::before {
    content: "";
    position: absolute;
    top: 15px;
    left: 0;
    right: 0;
    height: 3px;
    background-color: #e9ecef;
    z-index: 0;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    flex: 1;
}

.step.active .step-number {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.step.active .step-label {
    color: #0d6efd;
    font-weight: 600;
}

.step-number {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.step-label {
    font-size: 0.85rem;
    color: #6c757d;
    text-align: center;
}

/* Analysis selector */
.analysis-selector {
    max-height: 400px; /* Atur sesuai kebutuhan */
    overflow-y: auto;
    padding-right: 8px; /* Supaya isi tidak terpotong oleh scrollbar */
}

/* Optional: Kustom scrollbar (untuk Webkit-based browsers seperti Chrome, Edge, Safari) */
.analysis-selector::-webkit-scrollbar {
    width: 8px;
}

.analysis-selector::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.analysis-selector::-webkit-scrollbar-thumb {
    background: #405189;
    border-radius: 4px;
}

.analysis-selector::-webkit-scrollbar-thumb:hover {
    background: #1e2849;
}

.analysis-option {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    cursor: pointer;
    transition: all 0.2s ease;
    background-color: white;
}

.analysis-option:hover {
    border-color: #86b7fe;
    box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
}

.analysis-option.selected {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

.option-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background-color: rgba(13, 110, 253, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: #0d6efd;
    font-size: 1.1rem;
}

.option-details {
    flex: 1;
}

.option-details h6 {
    margin: 0;
    font-size: 0.95rem;
}

.option-check {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #0d6efd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.analysis-option.selected .option-check {
    opacity: 1;
}

/* Date range picker */
.date-range-picker {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.date-presets {
    background-color: white;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

/* Report summary */
.report-summary {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.summary-card {
    background-color: white;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    overflow: hidden;
}

.summary-header {
    background-color: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
}

.summary-header i {
    color: #0d6efd;
    margin-right: 10px;
    font-size: 1.1rem;
}

.summary-header h5 {
    margin: 0;
    font-size: 1rem;
}

.summary-body {
    padding: 15px;
}

.summary-item {
    display: flex;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px dashed #e9ecef;
}

.summary-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.summary-item span {
    width: 120px;
    color: #6c757d;
    font-size: 0.9rem;
}

.summary-item strong {
    flex: 1;
    font-weight: 500;
}

.format-options {
    display: flex;
    gap: 15px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .steps-progress {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }

    .steps-progress::before {
        display: none;
    }

    .step {
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }

    .step-number {
        margin-bottom: 0;
    }

    .summary-item {
        flex-direction: column;
        gap: 5px;
    }

    .summary-item span {
        width: auto;
    }
}
</style>
