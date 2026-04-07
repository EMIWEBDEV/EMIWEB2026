<template>
    <div class="form-panel modern-form">
        <div class="panel-header">
            <h2><i class="fas fa-flask me-2"></i> Sample Analysis</h2>
            <p class="subtitle">Masukkan hasil analisis rinci untuk sampel</p>
        </div>
        <div
            v-if="loading.currentDataSubmitAnalisa"
            class="text-center loading-state"
        >
            <div class="d-flex justify-content-center py-4 loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Memuat...</span>
                </div>
            </div>
        </div>

        <div class="panel-body" v-else>
            <div class="analysis-table-container">
                <div class="mb-3 mt-2 d-flex justify-content-between p-2">
                    <button
                        @click="addRow"
                        class="btn btn-primary"
                        v-if="!dataSampel.length"
                    >
                        <i class="fas fa-plus"></i> Tambah Baris
                    </button>
                    <button
                        @click="addRow"
                        class="btn btn-primary"
                        v-else
                        :disabled="!isSubmitDone"
                    >
                        <i class="fas fa-plus"></i> Tambah Baris
                    </button>
                    <button
                        v-if="dataSampel.length"
                        class="btn btn-info"
                        data-bs-toggle="modal"
                        data-bs-target="#myModal"
                    >
                        <i class="fas fa-eye"></i> Lihat Data Yang Di Submit
                    </button>
                </div>

                <div
                    id="myModal"
                    class="modal fade"
                    tabindex="-1"
                    aria-labelledby="myModalLabel"
                    aria-hidden="true"
                    style="display: none"
                >
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="myModalLabel">
                                    Kumpulan Hasil Analisa
                                </h5>
                            </div>
                            <div class="modal-body">
                                <ul
                                    class="nav nav-tabs nav-border-top nav-border-top-primary mb-3"
                                    role="tablist"
                                >
                                    <li class="nav-item" role="presentation">
                                        <a
                                            class="nav-link active"
                                            data-bs-toggle="tab"
                                            href="#nav-border-top-settings"
                                            role="tab"
                                            aria-selected="true"
                                        >
                                            <i class="fas fa-info-circle"></i>
                                            Informasi
                                        </a>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <a
                                            class="nav-link"
                                            data-bs-toggle="tab"
                                            href="#nav-border-top-messages"
                                            role="tab"
                                            aria-selected="false"
                                            tabindex="-1"
                                        >
                                            <i class="fas fa-history"></i>
                                            Riwayat Pengisian
                                        </a>
                                    </li>
                                </ul>
                                <div class="tab-content text-muted">
                                    <div
                                        class="tab-pane active show"
                                        id="nav-border-top-settings"
                                        role="tabpanel"
                                    >
                                        <div
                                            class="details-panel"
                                            v-for="(item, index) in dataSampel"
                                            :key="index"
                                        >
                                            <div class="panel-header with-tabs">
                                                <h2>
                                                    <i
                                                        class="fas fa-clipboard-list"
                                                    ></i>
                                                    Detail Sampel
                                                </h2>
                                                <div class="status-badge">
                                                    <span class="badge active">
                                                        {{
                                                            item.No_Po_Sampel ??
                                                            ""
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            item.No_Fak_Sub_Po !==
                                                            null
                                                        "
                                                        class="badge priority"
                                                        >{{
                                                            item.No_Fak_Sub_Po ??
                                                            ""
                                                        }}</span
                                                    >
                                                </div>
                                            </div>

                                            <div class="panel-body">
                                                <div class="detail-grid">
                                                    <div class="detail-column">
                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >No.Po</span
                                                            >
                                                            <span
                                                                class="detail-value highlight"
                                                                >{{
                                                                    item.No_Po
                                                                }}</span
                                                            >
                                                        </div>

                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >Kode
                                                                Barang:</span
                                                            >
                                                            <span
                                                                class="detail-value"
                                                                >{{
                                                                    item.Kode_Barang
                                                                }}</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >Tanggal
                                                                Pengajuan Uji
                                                                Sampel</span
                                                            >
                                                            <span
                                                                class="detail-value"
                                                                >{{
                                                                    formatTanggal(
                                                                        item.Tanggal_Po_Sampel
                                                                    )
                                                                }}</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >Jam Pengajuan
                                                                Uji Sampel</span
                                                            >
                                                            <span
                                                                class="detail-value"
                                                                >{{
                                                                    item.Jam_Po_Sampel
                                                                }}</span
                                                            >
                                                        </div>
                                                    </div>
                                                    <div class="detail-column">
                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >No. Split
                                                                PO</span
                                                            >
                                                            <span
                                                                class="detail-value"
                                                                >{{
                                                                    item.No_Split_Po
                                                                }}</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >Tanggal Analisa
                                                                Sampel</span
                                                            >
                                                            <span
                                                                class="detail-value"
                                                                >{{
                                                                    formatTanggal(
                                                                        item.Tanggal_Pengujian_Sampel
                                                                    )
                                                                }}</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >Jam Analisa
                                                                Sampel</span
                                                            >
                                                            <span
                                                                class="detail-value"
                                                                >{{
                                                                    item.Jam_Pengujian_Sampel
                                                                }}</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="detail-item"
                                                        >
                                                            <span
                                                                class="detail-label"
                                                                >No Batch</span
                                                            >
                                                            <span
                                                                class="detail-value"
                                                                >{{
                                                                    item.No_Batch
                                                                }}</span
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="notes-section">
                                                    <div class="notes-header">
                                                        <span
                                                            class="notes-label"
                                                            ><i
                                                                class="fas fa-sticky-note"
                                                            ></i>
                                                            Catatan
                                                        </span>
                                                    </div>
                                                    <div class="notes-content">
                                                        {{
                                                            item.Catatan_Po_Sampel ||
                                                            "-"
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <hr />
                                        <div
                                            class="performance-chart-container"
                                        >
                                            <h2 class="text-lg font-bold mb-4">
                                                Grafik Waktu Pengujian Sampel
                                            </h2>

                                            <div
                                                class="chart-wrapper"
                                                v-if="series.length > 0"
                                            >
                                                <apexchart
                                                    type="area"
                                                    height="300"
                                                    :options="chartOptions"
                                                    :series="series"
                                                />
                                            </div>
                                        </div>
                                        <hr />
                                        <h5>Hasil Analisa</h5>
                                        <div
                                            class="alert alert-info d-flex align-items-center gap-2"
                                            role="alert"
                                        >
                                            <i
                                                class="ri-information-line fs-4"
                                            ></i>
                                            <div>
                                                <strong>Catatan:</strong> Nilai
                                                hasil analisa dan parameter
                                                ditampilkan mengikuti format
                                                sistem, yaitu secara
                                                <strong
                                                    >default menggunakan 4 digit
                                                    di belakang koma</strong
                                                >
                                                (contoh: <code>1.2345</code>).
                                                <br />
                                                Jika Anda melihat perbedaan
                                                tampilan seperti angka nol
                                                tambahan di belakang (misalnya
                                                <code>1.2300</code>), itu hanya
                                                bersifat format tampilan. Nilai
                                                aslinya tetap akurat sesuai
                                                input dan tidak berubah secara
                                                matematis.
                                                <br />
                                                Format ini digunakan untuk
                                                menjaga konsistensi data dan
                                                memudahkan proses verifikasi
                                                hasil analisa.
                                            </div>
                                        </div>

                                        <div
                                            class="table-responsive"
                                            v-for="(
                                                item, index
                                            ) in currentDataSubmitAnalisa"
                                            :key="index"
                                        >
                                            <table
                                                class="table table-bordered table-nowrap align-middle mb-0 text-center"
                                            >
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>No</th>
                                                        <th
                                                            v-for="param in selectedTemplating.parameter"
                                                            :key="param.id_qc"
                                                        >
                                                            {{
                                                                param.nama_parameter
                                                            }}
                                                        </th>
                                                        <th
                                                            v-for="(
                                                                hitung, i
                                                            ) in selectedTemplating.formula"
                                                            :key="
                                                                'hitung-header-' +
                                                                i
                                                            "
                                                        >
                                                            {{
                                                                hitung.nama_kolom
                                                            }}
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr
                                                        v-for="(
                                                            item, rowIndex
                                                        ) in formattedCurrentDataSubmitAnalisa"
                                                        :key="'row-' + rowIndex"
                                                    >
                                                        <td>
                                                            {{ rowIndex + 1 }}
                                                        </td>

                                                        <td
                                                            v-for="(
                                                                param, i
                                                            ) in item.parameter"
                                                            :key="'param-' + i"
                                                        >
                                                            {{
                                                                param.Value_Parameter ??
                                                                0
                                                            }}
                                                        </td>
                                                        <td
                                                            v-for="(
                                                                hitung, i
                                                            ) in item.hasil"
                                                            :key="'hasil-' + i"
                                                        >
                                                            {{
                                                                hitung.Hasil_Perhitungan ??
                                                                0
                                                            }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- tab pane untuk riwayat tracking pengisian (log) -->
                                    <div
                                        class="tab-pane"
                                        id="nav-border-top-messages"
                                        role="tabpanel"
                                    >
                                        <div class="profile-timeline">
                                            <div
                                                class="accordion accordion-flush"
                                                id="accordionFlushExample"
                                            >
                                                <div
                                                    class="accordion-item border-0"
                                                    v-for="(
                                                        item, index
                                                    ) in dataTracking"
                                                    :key="index"
                                                >
                                                    <div
                                                        class="accordion-header"
                                                        :id="`heading${index}`"
                                                    >
                                                        <a
                                                            class="accordion-button p-2 shadow-none"
                                                            data-bs-toggle="collapse"
                                                            :href="`#collapse${index}`"
                                                            aria-expanded="true"
                                                            :aria-controls="`collapse${index}`"
                                                        >
                                                            <div
                                                                class="d-flex align-items-center"
                                                            >
                                                                <div
                                                                    class="flex-shrink-0 avatar-xs"
                                                                >
                                                                    <div
                                                                        class="avatar-title rounded-circle material-shadow"
                                                                        :class="
                                                                            getActivityStyle(
                                                                                item.Jenis_Aktivitas
                                                                            ).bg
                                                                        "
                                                                    >
                                                                        <i
                                                                            :class="
                                                                                getActivityStyle(
                                                                                    item.Jenis_Aktivitas
                                                                                )
                                                                                    .icon
                                                                            "
                                                                        ></i>
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    class="d-flex gap-3 ms-3"
                                                                >
                                                                    <h6
                                                                        class="fs-15 mb-0 fw-semibold"
                                                                    >
                                                                        {{
                                                                            item.No_Po ??
                                                                            ""
                                                                        }}
                                                                        -
                                                                        <span
                                                                            class="fw-normal"
                                                                        >
                                                                            {{
                                                                                item.No_Split_Po
                                                                            }}
                                                                        </span>
                                                                        -
                                                                        <span
                                                                            class="fw-normal"
                                                                        >
                                                                            {{
                                                                                item.No_Po_Sampel
                                                                            }}
                                                                        </span>
                                                                    </h6>
                                                                    <span
                                                                        class="badge bg-primary"
                                                                        ><i
                                                                            class="fas fa-user"
                                                                        ></i>
                                                                        {{
                                                                            item.Id_User
                                                                        }}</span
                                                                    >
                                                                </div>
                                                            </div>
                                                        </a>
                                                    </div>
                                                    <div
                                                        :id="`collapse${index}`"
                                                        class="accordion-collapse collapse show"
                                                        :aria-labelledby="`heading${index}`"
                                                        data-bs-parent="#accordionExample"
                                                    >
                                                        <div
                                                            class="accordion-body ms-2 ps-5 pt-0"
                                                        >
                                                            <div
                                                                class="d-flex flex-wrap align-items-center gap-2 mb-2"
                                                            >
                                                                <template
                                                                    v-if="
                                                                        item.No_Fak_Sub_Po !==
                                                                        null
                                                                    "
                                                                >
                                                                    <span
                                                                        class="badge bg-primary-subtle text-primary-emphasis"
                                                                    >
                                                                        <i
                                                                            class="fas fa-receipt me-1"
                                                                        ></i>
                                                                        No Sub
                                                                        Multi
                                                                        QrCode:
                                                                        {{
                                                                            item.No_Fak_Sub_Po
                                                                        }}
                                                                    </span>
                                                                </template>

                                                                <template
                                                                    v-if="
                                                                        item.No_Fak_Sub_Po !==
                                                                        null
                                                                    "
                                                                >
                                                                    <span
                                                                        class="badge bg-info-subtle text-info-emphasis"
                                                                    >
                                                                        <i
                                                                            class="fas fa-box me-1"
                                                                        ></i>
                                                                        No
                                                                        Batch:
                                                                        {{
                                                                            item.No_Batch
                                                                        }}
                                                                    </span>
                                                                </template>

                                                                <span
                                                                    class="badge bg-success-subtle text-success-emphasis"
                                                                >
                                                                    <i
                                                                        class="fas fa-clipboard-list me-1"
                                                                    ></i>
                                                                    Kode/Jenis
                                                                    Analisa:
                                                                    {{
                                                                        item.Kode_Analisa
                                                                    }}/
                                                                    {{
                                                                        item.Jenis_Analisa
                                                                    }}
                                                                </span>

                                                                <span
                                                                    class="badge"
                                                                    :class="
                                                                        item.Flag_Perhitungan ===
                                                                        'Y'
                                                                            ? 'bg-warning-subtle text-warning-emphasis'
                                                                            : 'bg-light text-dark'
                                                                    "
                                                                >
                                                                    <i
                                                                        class="fas"
                                                                        :class="
                                                                            item.Flag_Perhitungan ===
                                                                            'Y'
                                                                                ? 'fa-qrcode'
                                                                                : 'fa-qrcode'
                                                                        "
                                                                    ></i>
                                                                    {{
                                                                        item.Flag_Perhitungan ===
                                                                        "Y"
                                                                            ? "Multi QR Code"
                                                                            : "Single QR Code"
                                                                    }}
                                                                </span>
                                                            </div>
                                                            <h5
                                                                class="mb-2 text-truncate"
                                                                style="
                                                                    max-width: 100%;
                                                                "
                                                            >
                                                                {{
                                                                    item.Keterangan
                                                                }}
                                                            </h5>
                                                            <div
                                                                class="d-flex align-items-center text-muted small"
                                                            >
                                                                <i
                                                                    class="fas fa-calendar-alt me-1"
                                                                ></i>
                                                                <span>{{
                                                                    formatTanggal(
                                                                        item.Tanggal
                                                                    )
                                                                }}</span>
                                                                <span
                                                                    class="mx-2"
                                                                    >•</span
                                                                >
                                                                <i
                                                                    class="fas fa-clock me-1"
                                                                ></i>
                                                                <span>{{
                                                                    item.Jam
                                                                }}</span>
                                                            </div>
                                                            <hr />
                                                            <div
                                                                class="notes-section mb-3"
                                                                v-if="
                                                                    item.Jenis_Aktivitas ===
                                                                        'save_delete' ||
                                                                    item.Jenis_Aktivitas ===
                                                                        'save_update'
                                                                "
                                                            >
                                                                <div
                                                                    class="notes-header"
                                                                >
                                                                    <span
                                                                        class="notes-label"
                                                                        ><i
                                                                            class="fas fa-sticky-note"
                                                                        ></i>
                                                                        <span
                                                                            v-if="
                                                                                item.Jenis_Aktivitas ===
                                                                                'save_delete'
                                                                            "
                                                                            >Alasan
                                                                            Menghapus
                                                                            Analisa</span
                                                                        >
                                                                        <span
                                                                            v-if="
                                                                                item.Jenis_Aktivitas ===
                                                                                'save_update'
                                                                            "
                                                                            >Alasan
                                                                            Mengedit
                                                                            Analisa</span
                                                                        >
                                                                    </span>
                                                                </div>
                                                                <div
                                                                    class="notes-content"
                                                                >
                                                                    {{
                                                                        item.Alasan ||
                                                                        "-"
                                                                    }}
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="alert alert-info d-flex align-items-center gap-2"
                                                                role="alert"
                                                            >
                                                                <i
                                                                    class="ri-information-line fs-4"
                                                                ></i>
                                                                <div>
                                                                    <strong
                                                                        >Catatan:</strong
                                                                    >
                                                                    Nilai hasil
                                                                    analisa dan
                                                                    parameter
                                                                    ditampilkan
                                                                    mengikuti
                                                                    format
                                                                    sistem,
                                                                    yaitu secara
                                                                    <strong
                                                                        >default
                                                                        menggunakan
                                                                        4 digit
                                                                        di
                                                                        belakang
                                                                        koma</strong
                                                                    >
                                                                    (contoh:
                                                                    <code
                                                                        >1.2345</code
                                                                    >).
                                                                    <br />
                                                                    Jika Anda
                                                                    melihat
                                                                    perbedaan
                                                                    tampilan
                                                                    seperti
                                                                    angka nol
                                                                    tambahan di
                                                                    belakang
                                                                    (misalnya
                                                                    <code
                                                                        >1.2300</code
                                                                    >), itu
                                                                    hanya
                                                                    bersifat
                                                                    format
                                                                    tampilan.
                                                                    Nilai
                                                                    aslinya
                                                                    tetap akurat
                                                                    sesuai input
                                                                    dan tidak
                                                                    berubah
                                                                    secara
                                                                    matematis.
                                                                    <br />
                                                                    Format ini
                                                                    digunakan
                                                                    untuk
                                                                    menjaga
                                                                    konsistensi
                                                                    data dan
                                                                    memudahkan
                                                                    proses
                                                                    verifikasi
                                                                    hasil
                                                                    analisa.
                                                                </div>
                                                            </div>
                                                            <div
                                                                class="table-responsive"
                                                            >
                                                                <table
                                                                    class="modern-analysis-table text-center"
                                                                >
                                                                    <thead
                                                                        class="text-sm"
                                                                    >
                                                                        <tr>
                                                                            <th
                                                                                v-for="param in selectedTemplating.parameter"
                                                                                :key="
                                                                                    param.id_qc
                                                                                "
                                                                            >
                                                                                {{
                                                                                    param.nama_parameter
                                                                                }}
                                                                            </th>
                                                                            <th
                                                                                v-for="formula in selectedTemplating.formula"
                                                                                :key="
                                                                                    formula.id_rumus
                                                                                "
                                                                            >
                                                                                {{
                                                                                    formula.nama_kolom
                                                                                }}
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <tr
                                                                            v-for="(
                                                                                row,
                                                                                rowIndex
                                                                            ) in getRowsForLog(
                                                                                item.Id_Log_Activity
                                                                            )"
                                                                            :key="
                                                                                rowIndex
                                                                            "
                                                                        >
                                                                            <td
                                                                                v-for="(
                                                                                    param,
                                                                                    paramIndex
                                                                                ) in row.parameter"
                                                                                :key="
                                                                                    'param-' +
                                                                                    paramIndex
                                                                                "
                                                                            >
                                                                                <span
                                                                                    v-if="
                                                                                        param.Value_Baru
                                                                                    "
                                                                                    :class="{
                                                                                        'text-success':
                                                                                            item.Jenis_Aktivitas ===
                                                                                            'save_submit',
                                                                                        'text-danger':
                                                                                            item.Jenis_Aktivitas ===
                                                                                            'save_delete',
                                                                                        'text-dark':
                                                                                            item.Jenis_Aktivitas !==
                                                                                                'save_submit' &&
                                                                                            item.Jenis_Aktivitas !==
                                                                                                'save_delete',
                                                                                    }"
                                                                                >
                                                                                    {{
                                                                                        param.Value_Baru
                                                                                    }}
                                                                                </span>

                                                                                <span
                                                                                    v-else
                                                                                    class="text-muted"
                                                                                >
                                                                                    Parameter
                                                                                    Ini
                                                                                    Belum
                                                                                    Ada
                                                                                    Hasil
                                                                                    Analisa
                                                                                </span>

                                                                                <span
                                                                                    v-if="
                                                                                        param.Value_Lama !==
                                                                                            null &&
                                                                                        param.Value_Lama !=
                                                                                            param.Value_Baru
                                                                                    "
                                                                                    class="text-danger ms-2"
                                                                                >
                                                                                    <s
                                                                                        >{{
                                                                                            param.Value_Lama
                                                                                        }}</s
                                                                                    >
                                                                                </span>
                                                                            </td>

                                                                            <td
                                                                                v-for="(
                                                                                    result,
                                                                                    resultIndex
                                                                                ) in row.hasil"
                                                                                :key="
                                                                                    'hasil-' +
                                                                                    resultIndex
                                                                                "
                                                                            >
                                                                                <span
                                                                                    :class="{
                                                                                        'text-success':
                                                                                            item.Jenis_Aktivitas ===
                                                                                            'save_submit',
                                                                                        'text-danger':
                                                                                            item.Jenis_Aktivitas ===
                                                                                            'save_delete',
                                                                                        'text-dark':
                                                                                            item.Jenis_Aktivitas !==
                                                                                                'save_submit' &&
                                                                                            item.Jenis_Aktivitas !==
                                                                                                'save_delete',
                                                                                    }"
                                                                                >
                                                                                    {{
                                                                                        result.Value_Baru
                                                                                    }}
                                                                                </span>

                                                                                <span
                                                                                    v-if="
                                                                                        result.Value_Lama !==
                                                                                            null &&
                                                                                        result.Value_Lama !=
                                                                                            result.Value_Baru
                                                                                    "
                                                                                    class="text-danger ms-2"
                                                                                >
                                                                                    <s
                                                                                        >{{
                                                                                            result.Value_Lama
                                                                                        }}</s
                                                                                    >
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <div
                                                                style="
                                                                    margin-top: 15px;
                                                                    font-size: 0.9em;
                                                                "
                                                            >
                                                                <strong
                                                                    >Keterangan:</strong
                                                                >
                                                                <ul
                                                                    style="
                                                                        padding-left: 20px;
                                                                        margin-top: 5px;
                                                                    "
                                                                >
                                                                    <li>
                                                                        Nilai
                                                                        Normal:
                                                                        Data
                                                                        terbaru.
                                                                    </li>
                                                                    <li>
                                                                        <span
                                                                            style="
                                                                                color: red;
                                                                            "
                                                                            ><s
                                                                                >Nilai
                                                                                Tercoret</s
                                                                            ></span
                                                                        >: Data
                                                                        sebelumnya
                                                                        yang
                                                                        telah
                                                                        diubah.
                                                                    </li>
                                                                    <li>
                                                                        <span
                                                                            style="
                                                                                color: red;
                                                                            "
                                                                            >Nilai
                                                                            Bewarna
                                                                            Merah</span
                                                                        >: Nilai
                                                                        Yang
                                                                        Dihapus
                                                                    </li>
                                                                    <li>
                                                                        <span
                                                                            class="text-success"
                                                                            >Nilai
                                                                            Berwarna
                                                                            Hijau</span
                                                                        >: Nilai
                                                                        yang
                                                                        sudah
                                                                        disimpan
                                                                        secara
                                                                        permanen.
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button
                                    type="button"
                                    class="btn btn-light"
                                    data-bs-dismiss="modal"
                                >
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3 container-fluid">
                    <div
                        class="alert alert-danger alert-dismissible alert-label-icon label-arrow fade show material-shadow"
                        role="alert"
                    >
                        <i class="ri-error-warning-line label-icon"></i
                        ><strong>Perhatikan !</strong> - Jika menggunakan Koma
                        maka ganti dengan tanda titik, contoh (27.8912)
                    </div>
                </div>

                <div class="mb-3 container-fluid" v-if="dataSampel.length">
                    <div
                        class="alert alert-success alert-dismissible alert-label-icon label-arrow fade show material-shadow"
                        role="alert"
                    >
                        <i class="ri-airplay-line label-icon"></i
                        ><strong>Info 🎉</strong> - Untuk Melihat data yang
                        pernah disubmit, silahkan tekan button di ujung kanan
                        atas 😊
                    </div>
                </div>

                <div class="table-scroll-wrapper">
                    <table class="modern-analysis-table">
                        <thead>
                            <tr>
                                <th
                                    v-for="param in selectedTemplating.parameter"
                                    :key="param.id_qc"
                                >
                                    {{ param.nama_parameter }}
                                </th>
                                <th
                                    v-for="(
                                        hitung, i
                                    ) in selectedTemplating.formula"
                                    :key="'hitung-header-' + i"
                                >
                                    {{ hitung.nama_kolom }}
                                </th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="(row, rowIndex) in rows" :key="rowIndex">
                                <td
                                    v-for="param in selectedTemplating.parameter"
                                    :key="param.id_qc"
                                >
                                    <div
                                        v-if="param.type_inputan === 'Input'"
                                        class="input-container"
                                    >
                                        <input
                                            :id="
                                                'input-' +
                                                rowIndex +
                                                '-' +
                                                param.id_qc
                                            "
                                            type="number"
                                            class="form-control"
                                            :class="{
                                                'is-editing':
                                                    !row.lockedInputs[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ].Value_Parameter !== null,
                                            }"
                                            step="any"
                                            pattern="[0-9]+([.][0-9]+)?"
                                            :placeholder="param.nama_parameter"
                                            v-model="
                                                row.inputValues[param.id_qc]
                                            "
                                            @input="handleInputChange(rowIndex)"
                                            @keydown="checkDigit"
                                            @paste="handlePaste"
                                            :readonly="
                                                row.lockedInputs[param.id_qc]
                                            "
                                            required
                                        />

                                        <div class="input-actions">
                                            <button
                                                v-if="
                                                    row.lockedInputs[
                                                        param.id_qc
                                                    ]
                                                "
                                                @click="
                                                    unlockInput(
                                                        rowIndex,
                                                        param.id_qc
                                                    )
                                                "
                                                class="btn-action-icon btn-edit"
                                            >
                                                <i
                                                    class="fas fa-pencil-alt fa-sm"
                                                ></i>
                                            </button>

                                            <div
                                                class="button-group"
                                                v-if="
                                                    !row.lockedInputs[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ].Value_Parameter !== null
                                                "
                                            >
                                                <button
                                                    @click="
                                                        saveChange(
                                                            rowIndex,
                                                            param.id_qc
                                                        )
                                                    "
                                                    class="btn-action-icon btn-glamor"
                                                    data-tooltip="Simpan"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#myModalEdit"
                                                >
                                                    <i
                                                        class="fas fa-check fa-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    @click="
                                                        cancelChange(
                                                            rowIndex,
                                                            param.id_qc
                                                        )
                                                    "
                                                    class="btn-action-icon btn-cancel"
                                                    data-tooltip="Batal"
                                                >
                                                    <i
                                                        class="fas fa-times fa-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-else-if="
                                            param.type_inputan === 'Switch'
                                        "
                                        class="input-container"
                                    >
                                        <select
                                            :id="
                                                'input-' +
                                                rowIndex +
                                                '-' +
                                                param.id_qc
                                            "
                                            class="form-control"
                                            :class="{
                                                'is-editing':
                                                    !row.lockedInputs[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ].Value_Parameter !== null,
                                            }"
                                            v-model="
                                                row.inputValues[param.id_qc]
                                            "
                                            @change="
                                                handleInputChange(rowIndex)
                                            "
                                            :disabled="
                                                row.lockedInputs[param.id_qc]
                                            "
                                        >
                                            <option
                                                :value="null"
                                                disabled
                                                hidden
                                            >
                                                Pilih Hasil
                                                {{ param.nama_parameter }}
                                            </option>
                                            <option
                                                v-for="(
                                                    opt, idx
                                                ) in param.option"
                                                :key="idx"
                                                :value="opt.value"
                                            >
                                                {{ opt.label }}
                                            </option>
                                        </select>

                                        <div class="input-actions">
                                            <button
                                                v-if="
                                                    row.lockedInputs[
                                                        param.id_qc
                                                    ]
                                                "
                                                @click="
                                                    unlockInput(
                                                        rowIndex,
                                                        param.id_qc
                                                    )
                                                "
                                                class="btn-action-icon btn-edit"
                                                data-tooltip="Update Nilai"
                                                type="button"
                                            >
                                                <i
                                                    class="fas fa-pencil-alt fa-sm"
                                                ></i>
                                            </button>

                                            <div
                                                class="button-group"
                                                v-if="
                                                    !row.lockedInputs[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ] &&
                                                    row.draftDetails[
                                                        param.id_qc
                                                    ].Value_Parameter !== null
                                                "
                                            >
                                                <button
                                                    @click="
                                                        saveChange(
                                                            rowIndex,
                                                            param.id_qc
                                                        )
                                                    "
                                                    class="btn-action-icon btn-glamor"
                                                    data-tooltip="Simpan"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#myModalEdit"
                                                    type="button"
                                                >
                                                    <i
                                                        class="fas fa-check fa-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    @click="
                                                        cancelChange(
                                                            rowIndex,
                                                            param.id_qc
                                                        )
                                                    "
                                                    class="btn-action-icon btn-cancel"
                                                    data-tooltip="Batal"
                                                    type="button"
                                                >
                                                    <i
                                                        class="fas fa-times fa-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td
                                    v-for="(
                                        hitung, i
                                    ) in selectedTemplating.formula"
                                    :key="'hitung-body-' + i"
                                >
                                    <input
                                        type="number"
                                        class="form-control"
                                        readonly
                                        disabled
                                        :value="
                                            row.formulaResults[hitung.rumus]
                                        "
                                    />
                                </td>
                                <td>
                                    <div v-if="!isEditing">
                                        <button
                                            @click="removeRow(rowIndex)"
                                            class="modern-delete-btn"
                                            v-if="
                                                Object.keys(row.draftDetails)
                                                    .length > 0 ||
                                                rows.length > 1
                                            "
                                            aria-label="Delete row"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- modal edit data yang ada di draft -->
                <div
                    id="myModalEdit"
                    class="modal fade"
                    tabindex="-1"
                    aria-labelledby="myModalLabel"
                    aria-hidden="true"
                    style="display: none"
                    data-bs-backdrop="static"
                >
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="myModalLabel">
                                    Form Konfirmasi Keterangan
                                </h5>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label"
                                        >Alasan Mengubah Hasil Analisa
                                        <small class="text-danger"
                                            >*</small
                                        ></label
                                    >
                                    <textarea
                                        v-model="reasonForChange"
                                        rows="5"
                                        class="form-control"
                                        placeholder="Masukan Alasan Mengubah Nilai Analisa...."
                                    ></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button
                                    type="button"
                                    class="btn btn-light"
                                    data-bs-dismiss="modal"
                                    :disabled="loading.editForDatabase"
                                    @click="isEditing = false"
                                >
                                    Batal
                                </button>
                                <button
                                    :disabled="loading.editForDatabase"
                                    type="button"
                                    class="btn btn-primary"
                                    @click="
                                        updateAnalysisSementaraForDraft(
                                            editingRowIndex,
                                            editingParamIdQc
                                        )
                                    "
                                >
                                    {{
                                        loading.saveToDatabase
                                            ? "Loading..."
                                            : " Update Analisa "
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- modal hapus data yang ada di draft -->
                <div
                    id="myModalHapus"
                    class="modal zoomIn"
                    tabindex="-1"
                    aria-labelledby="myModalLabel"
                    aria-hidden="true"
                    style="display: none"
                    data-bs-backdrop="static"
                >
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="myModalLabel">
                                    Form Konfirmasi Keterangan
                                </h5>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label"
                                        >Alasan Menghapus Hasil Analisa
                                        <small class="text-danger"
                                            >*</small
                                        ></label
                                    >
                                    <textarea
                                        v-model="reasonForChange"
                                        rows="5"
                                        class="form-control"
                                        placeholder="Masukan Alasan Menghapus Nilai Analisa...."
                                    ></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"
                                        >Untuk konfirmasi, Ketik
                                        <strong class="text-danger"
                                            >delete/hasil-analisa</strong
                                        >
                                        di bawah ini
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Ketikan Atau Copy Yang Tulisan Warna Merah..."
                                        v-model="hapuskey"
                                    />
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button
                                    type="button"
                                    class="btn btn-light"
                                    data-bs-dismiss="modal"
                                    @click="clearModalHapus"
                                    :disabled="loading.deleteForDatabase"
                                >
                                    Batal
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-danger"
                                    :disabled="loading.deleteForDatabase"
                                    @click="deleteAnalysisSementaraForDraft"
                                >
                                    {{
                                        loading.deleteForDatabase
                                            ? "Loading..."
                                            : "Hapus Analisa"
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- modal informasi submit -->
            <div
                id="myModalInformasiSubmit"
                class="modal fade"
                tabindex="-1"
                aria-labelledby="myModalLabel"
                aria-hidden="true"
                style="display: none"
                data-bs-backdrop="static"
            >
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                Konfirmasi dan Pernyataan Tanggung Jawab
                            </h5>
                            <button
                                type="button"
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal"
                                aria-label="Close"
                            ></button>
                        </div>
                        <div class="modal-body">
                            <div class="container-fluid">
                                <div class="d-flex justify-content-center mb-4">
                                    <DotLottieVue
                                        style="height: 150px; width: 300px"
                                        autoplay
                                        loop
                                        src="/animation/warning-submit.json"
                                    />
                                </div>

                                <div
                                    class="informasi-konfirmasi"
                                    style="
                                        max-height: 400px;
                                        overflow-y: auto;
                                        padding: 10px;
                                        border: 1px solid #eee;
                                        border-radius: 5px;
                                    "
                                >
                                    <h6 class="text-danger">
                                        PERHATIAN: Harap baca seluruh informasi
                                        berikut sebelum melanjutkan
                                    </h6>

                                    <div class="mb-3">
                                        <h6>1. Tanggung Jawab Analisis</h6>
                                        <p>
                                            Dengan menekan tombol "Submit
                                            Analysis", Anda menyatakan bahwa:
                                        </p>
                                        <ul>
                                            <li>
                                                Anda bertanggung jawab penuh
                                                atas semua hasil analisa ini
                                            </li>
                                            <li>
                                                Data yang digunakan telah
                                                diverifikasi kebenarannya
                                            </li>
                                            <li>
                                                Parameter yang dimasukkan telah
                                                sesuai dengan ketentuan
                                            </li>
                                            <li>
                                                Anda memahami implikasi dari
                                                hasil analisa ini
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="mb-3">
                                        <h6>2. Proses Analisis Sistem</h6>
                                        <p>
                                            Analisis ini menggunakan perhitungan
                                            otomatis dengan ketentuan:
                                        </p>
                                        <ul>
                                            <li>
                                                Rumus perhitungan telah
                                                ditetapkan oleh sistem
                                            </li>
                                            <li>
                                                Perhitungan dilakukan secara
                                                real-time seperti Excel
                                            </li>
                                            <li>
                                                Hasil bergantung pada parameter
                                                yang dimasukkan
                                            </li>
                                            <li>
                                                Sistem tidak bertanggung jawab
                                                atas kesalahan input data
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="mb-3">
                                        <h6>3. Keterbatasan Analisis</h6>
                                        <p>
                                            Analisis ini memiliki beberapa
                                            keterbatasan:
                                        </p>
                                        <ul>
                                            <li>
                                                Hasil hanya seakurat data yang
                                                dimasukkan
                                            </li>
                                            <li>
                                                Tidak memperhitungkan faktor
                                                eksternal yang tidak terukur
                                            </li>
                                            <li>
                                                Interval kepercayaan berdasarkan
                                                asumsi distribusi normal
                                            </li>
                                            <li>
                                                Perlu verifikasi manual untuk
                                                kasus khusus
                                            </li>
                                        </ul>
                                    </div>

                                    <div class="mb-3">
                                        <h6>4. Penggunaan Hasil</h6>
                                        <p>Hasil analisis ini:</p>
                                        <ul>
                                            <li>
                                                Hanya untuk tujuan pengambilan
                                                keputusan pendukung
                                            </li>
                                            <li>
                                                Bukan merupakan jaminan mutlak
                                            </li>
                                            <li>
                                                Harus diinterpretasikan oleh
                                                profesional terkait
                                            </li>
                                            <li>
                                                Tidak menggantikan analisis
                                                komprehensif
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Checkbox Konfirmasi -->
                                    <div class="form-check mt-4">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="modalConfirmCheckbox"
                                            v-model="modalConfirmed"
                                            required
                                        />
                                        <label
                                            class="form-check-label fw-bold"
                                            for="modalConfirmCheckbox"
                                        >
                                            Saya telah membaca dan memahami
                                            semua informasi di atas, dan
                                            bertanggung jawab penuh atas hasil
                                            analisis ini.
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-light"
                                data-bs-dismiss="modal"
                                @click="modalConfirmed = false"
                            >
                                <i class="fas fa-times me-2"></i>Tutup
                            </button>
                            <button
                                :disabled="
                                    !modalConfirmed || loading.saveToDatabase
                                "
                                @click="
                                    modalConfirmed ? submitAnalysis() : null
                                "
                                class="btn btn-primary"
                                :class="{ 'disabled-opacity': !modalConfirmed }"
                            >
                                <i class="fas fa-paper-plane me-2"></i>
                                {{
                                    loading.saveToDatabase
                                        ? "Loading..."
                                        : "Submit Analysis"
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-check mt-4" v-if="dataSampel.length">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="modalConfirmCheckbox"
                    v-model="isSubmitDone"
                    required
                />
                <label
                    class="form-check-label fw-bold"
                    for="modalConfirmCheckbox"
                >
                    Saya Ingin Menambahkan Analisa Lagi, Memang Benar Data
                    Analisa Sebelumnya Pernah Di Submit. Namun Ada Data Lagi
                    Yang Harus Di submit. Dan Saya bertanggung Jawab Atas
                    tindakan yang saya ambil dan memahami konsekuensinya
                </label>
            </div>
            <div class="form-actions" v-if="!isEditing">
                <button
                    :disabled="loading.saveToDatabase"
                    class="action-button secondary"
                    @click="submitAnalysisSementara"
                    v-if="!dataSampel.length"
                >
                    <i class="fas fa-save"></i>
                    {{
                        loading.saveToDatabase
                            ? "Loading..."
                            : "Simpan As Draft"
                    }}
                </button>
                <button
                    :disabled="!isSubmitDone"
                    class="action-button secondary"
                    @click="submitAnalysisSementara"
                    v-else
                >
                    <i class="fas fa-save"></i>
                    {{
                        loading.saveToDatabase
                            ? "Loading..."
                            : "Simpan As Draft"
                    }}
                </button>
                <button
                    data-bs-toggle="modal"
                    data-bs-target="#myModalInformasiSubmit"
                    class="action-button primary"
                    v-if="!dataSampel.length"
                >
                    <i class="fas fa-paper-plane"></i>
                    {{
                        loading.saveToDatabase
                            ? "Loading..."
                            : "Submit Analysis"
                    }}
                </button>
                <button
                    data-bs-toggle="modal"
                    data-bs-target="#myModalInformasiSubmit"
                    class="action-button primary"
                    v-else
                    :disabled="!isSubmitDone"
                >
                    <i class="fas fa-paper-plane"></i>
                    {{
                        loading.saveToDatabase
                            ? "Loading..."
                            : "Submit Analysis"
                    }}
                </button>
            </div>
        </div>
    </div>
    <div
        v-if="loading.saveToDatabase"
        class="loading-overlay d-flex justify-content-center align-items-center"
    >
        <div class="loading-box text-center p-4 shadow">
            <DotLottieVue
                style="height: 150px; width: 300px"
                autoplay
                loop
                src="/animation/loading.json"
            />
            <small class="mt-3 mb-0 text-white">
                Mohon tunggu, sedang menyimpan data...
            </small>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import { evaluate, round } from "mathjs";
import Swal from "sweetalert2";
import axios from "axios";
import ApexChart from "vue3-apexcharts";
import vSelect from "vue-select";

export default {
    components: {
        apexchart: ApexChart,
        DotLottieVue,
        vSelect,
    },
    props: {
        selectedTemplating: Object,
        is_multi_print: {
            type: String,
            default: null,
        },
        no_ticket: {
            type: String,
            default: null,
        },
        No_Po_Sampel: {
            type: String,
            default: null,
        },
        kodeAnalisa: {
            type: String,
            default: null,
        },
        Id_Jenis_Analisa: [Number, String],
        Id_Mesin: [Number, String],
    },
    data() {
        return {
            rows: [],
            idLogYangDitampilkan: null,
            modalConfirmed: false,
            dataSampel: [],
            dataTracking: [],

            currentDataSubmitAnalisa: [],
            loading: {
                currentDataSubmitAnalisa: false,
                dataTracking: false,
                saveToDatabase: false,
                deleteForDatabase: false,
                editForDatabase: false,
            },
            rawData: [],
            chartOptions: {
                chart: {
                    type: "area",
                    toolbar: {
                        show: true,
                    },
                },
                xaxis: {
                    categories: ["Pengajuan", "Analisa"],
                    title: {
                        text: "Aktivitas",
                        style: { fontSize: "12px", fontWeight: "bold" },
                    },
                },
                yaxis: {
                    title: {
                        text: "Jam",
                        style: { fontSize: "12px", fontWeight: "bold" },
                    },
                    labels: {
                        formatter: (val) => {
                            const jam = Math.floor(val / 60);
                            const menit = Math.floor(val % 60);
                            return `${jam.toString().padStart(2, "0")}:${menit
                                .toString()
                                .padStart(2, "0")}`;
                        },
                    },
                },
                tooltip: {
                    y: {
                        formatter: (val) => {
                            const jam = Math.floor(val / 60);
                            const menit = Math.floor(val % 60);
                            const detik = Math.round((val % 1) * 60);
                            return `${jam.toString().padStart(2, "0")}:${menit
                                .toString()
                                .padStart(2, "0")}:${detik
                                .toString()
                                .padStart(2, "0")}`;
                        },
                        title: {
                            formatter: () => "Jam",
                        },
                    },
                },
                dataLabels: {
                    enabled: true,
                    formatter: (val) => {
                        const jam = Math.floor(val / 60);
                        const menit = Math.floor(val % 60);
                        return `${jam}:${menit.toString().padStart(2, "0")}`;
                    },
                },
            },
            isEditing: false,
            isSubmitDone: false,

            // update
            editingRowIndex: null,
            editingParamIdQc: null,
            editingNoUrut: null,
            reasonForChange: "",

            // DELETE
            deleteRowIndex: null,
            deleteNoSementara: null,
            hapuskey: "",
        };
    },
    watch: {
        selectedTemplating: {
            handler() {
                this.initializeRows();
            },
            deep: true,
            immediate: true,
        },
    },
    mounted() {
        this.fetchDraftData();
    },
    computed: {
        formattedCurrentDataSubmitAnalisa() {
            if (
                !this.currentDataSubmitAnalisa ||
                this.currentDataSubmitAnalisa.length === 0
            ) {
                return [];
            }

            const sourceData = this.currentDataSubmitAnalisa[0];
            const allParameters = sourceData.parameter || [];
            const allResults = sourceData.hasil || [];

            // Dapatkan jumlah parameter dan hasil per baris dari template yang aktif
            const paramsPerRow =
                this.selectedTemplating?.parameter?.length ?? 0;
            const resultsPerRow = this.selectedTemplating?.formula?.length ?? 0;

            // Hitung total baris berdasarkan parameter
            const totalRows = Math.floor(allParameters.length / paramsPerRow);
            const restructuredData = [];

            for (let i = 0; i < totalRows; i++) {
                const paramStart = i * paramsPerRow;
                const paramEnd = paramStart + paramsPerRow;

                const resultStart = i * resultsPerRow;
                const resultEnd = resultStart + resultsPerRow;

                // Antisipasi jika panjang hasil kurang
                const paramSlice = allParameters.slice(paramStart, paramEnd);
                const resultSlice = allResults.slice(resultStart, resultEnd);

                restructuredData.push({
                    parameter: paramSlice,
                    hasil: resultSlice,
                });
            }

            return restructuredData;
        },

        series() {
            return this.rawData.map((item) => {
                const jamPO = this.jamKeMenit(item.Jam_Po_Sampel);
                const jamAnalisa = this.jamKeMenit(item.Jam_Pengujian_Sampel);

                return {
                    name: item.No_Po_Sampel,
                    data: [jamPO, jamAnalisa],
                };
            });
        },
    },
    methods: {
        getRowsForLog(idLog) {
            if (!this.dataTracking || this.dataTracking.length === 0) return [];

            const logTerpilih = this.dataTracking.find(
                (log) => log.Id_Log_Activity === idLog
            );

            if (!logTerpilih) return [];

            const numParamsInTemplate =
                this.selectedTemplating?.parameter?.length ?? 0;
            const numResultsInTemplate =
                this.selectedTemplating?.formula?.length ?? 0;

            const allParameters = logTerpilih.parameter || [];
            const allResults = logTerpilih.hasil || [];

            if (
                numParamsInTemplate > 0 &&
                allParameters.length > numParamsInTemplate &&
                allResults.length > numResultsInTemplate
            ) {
                const totalRows = Math.floor(
                    allParameters.length / numParamsInTemplate
                );
                const restructuredData = [];

                for (let i = 0; i < totalRows; i++) {
                    const paramStart = i * numParamsInTemplate;
                    const paramEnd = paramStart + numParamsInTemplate;

                    const resultStart = i * numResultsInTemplate;
                    const resultEnd = resultStart + numResultsInTemplate;

                    const paramsForThisRow = allParameters.slice(
                        paramStart,
                        paramEnd
                    );
                    const resultsForThisRow = allResults.slice(
                        resultStart,
                        resultEnd
                    );

                    restructuredData.push({
                        parameter: paramsForThisRow.map((param) => ({
                            nama: param.nama_parameter,
                            Value_Baru:
                                param?.Value_Baru ??
                                param?.Value_Parameter ??
                                null,
                            Value_Lama: param?.Value_Lama ?? null,
                        })),
                        hasil: resultsForThisRow.map((res) => ({
                            Value_Baru:
                                res?.Value_Baru ??
                                res?.Hasil_Perhitungan ??
                                null,
                            Value_Lama: res?.Value_Lama ?? null,
                        })),
                    });
                }

                return restructuredData;
            } else {
                const normalizedParams = allParameters.map((param) => ({
                    nama: param.nama_parameter,
                    Value_Baru:
                        param?.Value_Baru ?? param?.Value_Parameter ?? null,
                    Value_Lama: param?.Value_Lama ?? null,
                }));

                const normalizedResults = allResults.map((res) => ({
                    Value_Baru:
                        res?.Value_Baru ?? res?.Hasil_Perhitungan ?? null,
                    Value_Lama: res?.Value_Lama ?? null,
                }));

                return [
                    {
                        parameter: normalizedParams,
                        hasil: normalizedResults,
                    },
                ];
            }
        },

        async fetchDraftData() {
            if (this.is_multi_print === "Y") {
                if (!this.no_ticket) {
                    this.initializeRows();
                    return;
                }

                this.loading.currentDataSubmitAnalisa = true;
                this.loading.dataTracking = true;
                try {
                    const [
                        response,
                        currentDataHasilAnalisa,
                        currentDataTracking,
                    ] = await Promise.all([
                        axios.get(
                            `/api/v1/detail/${this.no_ticket}/multi-print/${this.Id_Jenis_Analisa}`
                        ),
                        axios.get(
                            `/api/v1/detail-split/${this.no_ticket}/multi-print/${this.Id_Jenis_Analisa}`
                        ),
                        axios.get(
                            `/api/v1/tracking-detail/${this.No_Po_Sampel}/${this.no_ticket}/multi-print/${this.Id_Jenis_Analisa}/analisa`
                        ),
                    ]);

                    const hasilAnalisa =
                        currentDataHasilAnalisa.data.result || [];
                    const draft = response.data.result?.is_draft;
                    const submitData = response.data.result?.is_submit || [];
                    const getDataTrackingCurrent =
                        currentDataTracking.data.result || [];

                    this.currentDataSubmitAnalisa = hasilAnalisa;
                    this.dataSampel = submitData;
                    this.rawData = submitData;
                    this.dataTracking = getDataTrackingCurrent;

                    if (Array.isArray(draft) && draft.length > 0) {
                        this.rows = draft.map((groupItem) => {
                            const newRow = {
                                inputValues: {},
                                formulaResults: {},
                                lockedInputs: {},
                                draftDetails: {},
                                hasilDraft: {},
                            };

                            const parameterList = groupItem.parameter || [];
                            const hasilList = groupItem.hasil || [];

                            this.selectedTemplating.parameter.forEach(
                                (param) => {
                                    const detailItem = parameterList.find(
                                        (d) =>
                                            d.Id_Quality_Control === param.id_qc
                                    );

                                    if (detailItem) {
                                        newRow.inputValues[param.id_qc] =
                                            detailItem.Value_Parameter;
                                        newRow.draftDetails[param.id_qc] =
                                            detailItem;
                                        newRow.lockedInputs[param.id_qc] =
                                            detailItem.Value_Parameter !== null;
                                    } else {
                                        newRow.inputValues[param.id_qc] = null;
                                        newRow.draftDetails[param.id_qc] = null;
                                        newRow.lockedInputs[
                                            param.id_qc
                                        ] = false;
                                    }
                                }
                            );

                            this.selectedTemplating.formula.forEach(
                                (formula) => {
                                    const hasil = hasilList.find(
                                        (h) => h.Rumus === formula.rumus
                                    );
                                    newRow.formulaResults[formula.rumus] = {
                                        nilai: hasil
                                            ? hasil.Hasil_Perhitungan || 0
                                            : 0,
                                        digit: formula.digit ?? null,
                                        range_awal: formula.Range_Awal ?? null,
                                        range_akhir:
                                            formula.Range_Akhir ?? null,
                                    };

                                    newRow.hasilDraft[formula.rumus] = hasil
                                        ? {
                                              No_Urut: hasil.No_Urut ?? null,
                                              No_Sementara:
                                                  hasil.No_Sementara ?? null,
                                          }
                                        : {
                                              No_Urut: null,
                                              No_Sementara: null,
                                          };
                                }
                            );

                            return newRow;
                        });

                        this.rows.forEach((_, index) =>
                            this.calculateAllFormulas(index)
                        );
                    } else {
                        this.initializeRows();
                    }
                } catch (error) {
                    this.currentDataSubmitAnalisa = [];
                    this.dataSampel = [];
                    this.rawData = [];
                    this.dataTracking = [];
                    Swal.fire(
                        "Error",
                        "Gagal memuat data draft dari server.",
                        "error"
                    );
                    this.initializeRows();
                } finally {
                    this.loading.currentDataSubmitAnalisa = false;
                    this.loading.dataTracking = false;
                }
            } else {
                if (!this.No_Po_Sampel) {
                    this.initializeRows();
                    return;
                }

                this.loading.currentDataSubmitAnalisa = true;
                this.loading.dataTracking = true;

                try {
                    const [
                        response,
                        currentDataHasilAnalisa,
                        currentDataTracking,
                    ] = await Promise.all([
                        axios.get(
                            `/api/v1/${this.No_Po_Sampel}/no-multi/${this.Id_Jenis_Analisa}`
                        ),
                        axios.get(
                            `/api/v1/detail-split/${this.No_Po_Sampel}/not-print/${this.Id_Jenis_Analisa}`
                        ),
                        axios.get(
                            `/api/v1/tracking-detail/not-print/${this.No_Po_Sampel}/${this.Id_Jenis_Analisa}/analisa`
                        ),
                    ]);

                    const hasilAnalisa =
                        currentDataHasilAnalisa.data.result || [];
                    const draft = response.data.result?.is_draft;
                    const submitData = response.data.result?.is_submit || [];
                    const getDataTrackingCurrent =
                        currentDataTracking.data.result || [];

                    this.currentDataSubmitAnalisa = hasilAnalisa;
                    this.dataSampel = submitData;
                    this.rawData = submitData;
                    this.dataTracking = getDataTrackingCurrent;

                    if (Array.isArray(draft) && draft.length > 0) {
                        this.rows = draft.map((groupItem) => {
                            const newRow = {
                                inputValues: {},
                                formulaResults: {},
                                lockedInputs: {},
                                draftDetails: {},
                                hasilDraft: {}, // ✅ untuk menyimpan No_Urut & No_Sementara per rumus
                            };

                            const parameterList = groupItem.parameter || [];
                            const hasilList = groupItem.hasil || [];
                            console.log(hasilList, groupItem);

                            this.selectedTemplating.parameter.forEach(
                                (param) => {
                                    const detailItem = parameterList.find(
                                        (d) =>
                                            d.Id_Quality_Control === param.id_qc
                                    );

                                    if (detailItem) {
                                        newRow.inputValues[param.id_qc] =
                                            detailItem.Value_Parameter;
                                        newRow.draftDetails[param.id_qc] =
                                            detailItem;
                                        newRow.lockedInputs[param.id_qc] =
                                            detailItem.Value_Parameter !== null;
                                    } else {
                                        newRow.inputValues[param.id_qc] = null;
                                        newRow.draftDetails[param.id_qc] = null;
                                        newRow.lockedInputs[
                                            param.id_qc
                                        ] = false;
                                    }
                                }
                            );

                            this.selectedTemplating.formula.forEach(
                                (formula) => {
                                    const hasil = hasilList.find(
                                        (h) => h.Rumus === formula.rumus
                                    );
                                    newRow.formulaResults[formula.rumus] = {
                                        nilai: hasil
                                            ? hasil.Hasil_Perhitungan || 0
                                            : 0,
                                        digit: formula.digit ?? null,
                                        range_awal: formula.Range_Awal ?? null,
                                        range_akhir:
                                            formula.Range_Akhir ?? null,
                                    };

                                    newRow.hasilDraft[formula.rumus] = hasil
                                        ? {
                                              No_Urut: hasil.No_Urut ?? null,
                                              No_Sementara:
                                                  hasil.No_Sementara ?? null,
                                          }
                                        : {
                                              No_Urut: null,
                                              No_Sementara: null,
                                          };
                                }
                            );

                            return newRow;
                        });

                        this.rows.forEach((_, index) =>
                            this.calculateAllFormulas(index)
                        );
                    } else {
                        this.initializeRows();
                    }
                } catch (error) {
                    console.error("Gagal memuat data non-multi-print:", error);
                    this.currentDataSubmitAnalisa = [];
                    this.dataSampel = [];
                    this.rawData = [];
                    this.dataTracking = [];
                    Swal.fire(
                        "Error",
                        "Gagal memuat data draft dari server.",
                        "error"
                    );
                    this.initializeRows();
                } finally {
                    this.loading.currentDataSubmitAnalisa = false;
                    this.loading.dataTracking = false;
                }
            }
        },
        getActivityStyle(jenis) {
            if (jenis === "save_draft") {
                return {
                    bg: "bg-warning",
                    icon: "fas fa-pencil-alt",
                };
            }
            if (jenis === "save_update") {
                return {
                    bg: "bg-primary",
                    icon: "fas fa-pencil-alt",
                };
            }
            if (jenis === "save_delete") {
                return {
                    bg: "bg-danger",
                    icon: "fas fa-trash",
                };
            }

            if (jenis === "save_submit") {
                return {
                    // bg: "bg-info",
                    // icon: "fas fa-microscope",
                    bg: "bg-success",
                    icon: "fas fa-check",
                };
            }

            if (jenis === "finished") {
                return {
                    bg: "bg-success",
                    icon: "fas fa-check",
                };
            }

            return {
                bg: "bg-danger",
                icon: "fas fa-times",
            };
        },

        initializeRows() {
            this.rows = [];
            this.addRow();
        },
        addRow() {
            const newRow = {
                inputValues: {},
                formulaResults: {},
                lockedInputs: {},
                draftDetails: {},
            };

            this.selectedTemplating.parameter.forEach((param) => {
                newRow.inputValues[param.id_qc] = null;
                newRow.lockedInputs[param.id_qc] = false;
            });

            this.selectedTemplating.formula.forEach((formula) => {
                newRow.formulaResults[formula.rumus] = 0;
            });
            this.rows.push(newRow);
        },
        unlockInput(rowIndex, id_qc) {
            this.isEditing = true;
            this.rows[rowIndex].lockedInputs[id_qc] = false;
            this.$nextTick(() =>
                document.getElementById(`input-${rowIndex}-${id_qc}`).focus()
            );
        },
        removeRow(rowIndex) {
            const row = this.rows[rowIndex];
            const isDraftRow = Object.keys(row.draftDetails).length > 0;

            if (isDraftRow) {
                const representativeDetail = Object.values(row.draftDetails)[0];
                this.deleteNoSementara = representativeDetail.No_Sementara;
                this.deleteRowIndex = rowIndex;
                const modal = new bootstrap.Modal(
                    document.getElementById("myModalHapus")
                );
                modal.show();
            } else {
                if (this.rows.length > 1) {
                    this.rows.splice(rowIndex, 1);
                }
            }
        },
        clearModalHapus() {
            this.deleteNoSementara = null;
        },
        saveChange(rowIndex, id_qc) {
            const row = this.rows[rowIndex];
            const originalData = row.draftDetails[id_qc];

            // Simpan indeks baris dan id_qc yang sedang diedit
            this.editingRowIndex = rowIndex;
            this.editingParamIdQc = id_qc;
            this.editingNoUrut = originalData.No_Urut;

            // Reset alasan saat modal dibuka
            this.reasonForChange = "";
        },
        cancelChange(rowIndex, id_qc) {
            const row = this.rows[rowIndex];
            const originalValue = row.draftDetails[id_qc].Value_Parameter;
            row.inputValues[id_qc] = originalValue;
            row.lockedInputs[id_qc] = true;
            this, (this.isEditing = false);
            this.handleInputChange(rowIndex);
        },
        handleInputChange(rowIndex) {
            this.calculateAllFormulas(rowIndex);
        },
        calculateAllFormulas(rowIndex) {
            const row = this.rows[rowIndex];
            if (!this.selectedTemplating?.formula || !row) return;

            this.selectedTemplating.formula.forEach((formula) => {
                const digit =
                    Number.isInteger(parseInt(formula.digit)) &&
                    parseInt(formula.digit) >= 0
                        ? parseInt(formula.digit)
                        : 2;
                const result = this.calculateFormula(
                    formula.rumus,
                    digit,
                    row.inputValues
                );

                row.formulaResults[formula.rumus] = result;
            });
        },
        calculateFormula(formula, decimalPlaces = 2, inputValues) {
            try {
                let processedFormula = formula;

                // Mendefinisikan logika untuk setiap fungsi kustom
                const customFunctions = {
                    AVG: (values) => {
                        if (!values.length) return 0;
                        const sum = values.reduce((acc, val) => acc + val, 0);
                        return sum / values.length;
                    },
                    SUM: (values) => {
                        return values.reduce((acc, val) => acc + val, 0);
                    },
                    // Tambahkan fungsi kustom lainnya di sini jika perlu
                };

                // --- Langkah 1 & 2: Cari, hitung, dan ganti semua fungsi kustom ---
                for (const funcName in customFunctions) {
                    // Regex untuk menemukan semua fungsi dengan nama yang sesuai, contoh: /AVG\(([^)]+)\)/g
                    const regex = new RegExp(`${funcName}\\(([^)]+?)\\)`, "g");

                    processedFormula = processedFormula.replace(
                        regex,
                        (match, argsString) => {
                            // `match` adalah teks lengkap, misal: "AVG([param2],[param3])"
                            // `argsString` adalah isinya, misal: "[param2],[param3]"

                            // Ekstrak ID parameter dari argumen
                            const paramIds = (
                                argsString.match(/\[([^\]]+)\]/g) || []
                            ).map((p) => p.replace(/[\[\]]/g, ""));

                            // Dapatkan nilainya dari inputValues
                            const values = paramIds
                                .map((id) => parseFloat(inputValues[id]))
                                .filter((v) => !isNaN(v));

                            // Hitung hasilnya menggunakan logika yang sudah didefinisikan
                            const result = customFunctions[funcName](values);
                            return result; // Gantikan "AVG(...)" dengan hasil numeriknya
                        }
                    );
                }

                // Setelah langkah ini, rumus " [param1] - (AVG([param2],[param3])) "
                // akan menjadi " [param1] - (15.5) " (misalnya)

                // --- Langkah 3: Ganti placeholder parameter yang tersisa ---
                let finalFormula = processedFormula.replace(
                    /\[([^\]]+)\]/g,
                    (match, id_qc) => {
                        return parseFloat(inputValues[id_qc]) || 0;
                    }
                );

                // Sekarang, rumus menjadi " 20 - (15.5) " (misalnya)

                // --- Langkah 4: Evaluasi ekspresi matematika akhir ---
                const finalResult = evaluate(finalFormula);

                if (typeof finalResult === "number" && isFinite(finalResult)) {
                    // Gunakan pembulatan standar sebelum mengubah ke string
                    return round(finalResult, decimalPlaces).toFixed(
                        decimalPlaces
                    );
                }

                return (0).toFixed(decimalPlaces);
            } catch (e) {
                console.error(
                    "Error saat menghitung rumus kompleks:",
                    formula,
                    e
                );
                return (0).toFixed(decimalPlaces); // Kembali ke nilai default jika ada error
            }
        },
        async submitAnalysis() {
            this.loading.saveToDatabase = true;

            const modalElement = document.getElementById(
                "myModalInformasiSubmit"
            );
            const modalInstance =
                bootstrap.Modal.getInstance(modalElement) ||
                new bootstrap.Modal(modalElement);
            modalInstance.hide();

            for (const [index, row] of this.rows.entries()) {
                const allInputsFilled = this.selectedTemplating.parameter.every(
                    (param) =>
                        row.inputValues[param.id_qc] !== null &&
                        row.inputValues[param.id_qc] !== ""
                );
                if (!allInputsFilled) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: `Data pada baris ${
                            index + 1
                        } tidak lengkap. Mohon isi semua parameter.`,
                    });
                    this.loading.saveToDatabase = false;
                    return;
                }
            }

            try {
                if (this.is_multi_print === "Y") {
                    const payload = this.rows.map((row) => {
                        const parameters =
                            this.selectedTemplating.parameter.map((param) => {
                                const value = row.inputValues[param.id_qc];
                                const detail = row.draftDetails[param.id_qc];

                                return {
                                    Id_Quality_Control: param.id_qc,
                                    Value_Parameter:
                                        value === "" || value === null
                                            ? null
                                            : value,
                                    No_Urut: detail ? detail.No_Urut : null,
                                    RV_INT: detail ? detail.RV_INT : null,
                                    No_Sementara: detail
                                        ? detail.No_Sementara
                                        : null,
                                };
                            });

                        const formulas = this.selectedTemplating.formula.map(
                            (formula) => ({
                                Id_Jenis_Analisa: formula.Id_Jenis_Analisa,
                                Id_Perhitungan: formula.id,
                                Hasil_Perhitungan:
                                    row.formulaResults[formula.rumus] || 0,
                                Rumus: formula.rumus,
                                Digit: formula.digit,
                                Range_Awal: formula.Range_Awal,
                                Range_Akhir: formula.Range_Akhir,
                            })
                        );

                        const firstDetail = Object.values(row.draftDetails)[0];
                        const noSementaraForRow = firstDetail
                            ? firstDetail.No_Sementara
                            : null;

                        const result = {
                            No_Po_Sampel: this.No_Po_Sampel,
                            Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                            No_Sementara: noSementaraForRow,
                            parameters,
                            formulas,
                            is_multi_print: this.is_multi_print,
                            id_mesin: this.Id_Mesin,
                        };

                        if (this.is_multi_print === "Y") {
                            result.No_Po_Multi_Sampel = this.no_ticket;
                        }

                        return result;
                    });

                    const response = await axios.post(
                        "/api/v2/uji-sampel/store-multi-rumus",
                        { analyses: payload },
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status === 201 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Semua data analisis berhasil disimpan!",
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                } else {
                    const payload = this.rows.map((row) => {
                        const parameters =
                            this.selectedTemplating.parameter.map((param) => {
                                const value = row.inputValues[param.id_qc];
                                const detail = row.draftDetails[param.id_qc];

                                return {
                                    Id_Quality_Control: param.id_qc,
                                    Value_Parameter:
                                        value === "" || value === null
                                            ? null
                                            : value,
                                    No_Urut: detail ? detail.No_Urut : null,
                                    RV_INT: detail ? detail.RV_INT : null,
                                    No_Sementara: detail
                                        ? detail.No_Sementara
                                        : null,
                                };
                            });

                        const formulas = this.selectedTemplating.formula.map(
                            (formula) => ({
                                Id_Perhitungan: formula.id,
                                Id_Jenis_Analisa: formula.Id_Jenis_Analisa,
                                Hasil_Perhitungan:
                                    row.formulaResults[formula.rumus] || 0,
                                Rumus: formula.rumus,
                                Digit: formula.digit,
                                Range_Awal: formula.Range_Awal,
                                Range_Akhir: formula.Range_Akhir,
                            })
                        );

                        const firstDetail = Object.values(row.draftDetails)[0];
                        const noSementaraForRow = firstDetail
                            ? firstDetail.No_Sementara
                            : null;

                        const result = {
                            No_Po_Sampel: this.No_Po_Sampel,
                            Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                            No_Sementara: noSementaraForRow,
                            parameters,
                            formulas,
                            is_multi_print: this.is_multi_print,
                            id_mesin: this.Id_Mesin,
                        };

                        if (this.is_multi_print === "Y") {
                            result.No_Po_Multi_Sampel = this.no_ticket;
                        }

                        return result;
                    });
                    const response = await axios.post(
                        "/uji-sampel/store-multi-rumus-not-multipleqr",
                        { analyses: payload },
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status === 201 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Semua data analisis berhasil disimpan!",
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                }
            } catch (error) {
                console.error(error);
                let errorMessage = "Terjadi Kesalahan";
                if (error.response && error.response.data) {
                    errorMessage =
                        error.response.data.message ||
                        error.response.data.error;
                } else if (error.message) {
                    errorMessage = error.message;
                }
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text: errorMessage,
                });
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
        async submitAnalysisSementara() {
            this.loading.saveToDatabase = true;

            for (const [index, row] of this.rows.entries()) {
                const isAnyInputFilled = this.selectedTemplating.parameter.some(
                    (param) =>
                        row.inputValues[param.id_qc] !== null &&
                        row.inputValues[param.id_qc] !== ""
                );

                if (!isAnyInputFilled) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: `Data pada baris ${
                            index + 1
                        } tidak memiliki input sama sekali. Minimal 1 parameter harus diisi.`,
                    });
                    this.loading.saveToDatabase = false;
                    return;
                }
            }

            try {
                if (this.is_multi_print === "Y") {
                    const payload = this.rows.map((row) => {
                        const parameters =
                            this.selectedTemplating.parameter.map((param) => {
                                const value = row.inputValues[param.id_qc];
                                const detail = row.draftDetails[param.id_qc];
                                console.log(
                                    "DEBUG VALUE:",
                                    param.nama_parameter,
                                    value
                                );

                                return {
                                    Id_Quality_Control: param.id_qc,
                                    Value_Parameter:
                                        value === "" || value === null
                                            ? null
                                            : value,
                                    No_Urut: detail ? detail.No_Urut : null,
                                    No_Sementara: detail
                                        ? detail.No_Sementara
                                        : null,
                                };
                            });

                        const formulas = this.selectedTemplating.formula.map(
                            (formula) => {
                                const rumusKey = formula.rumus;
                                const hasilDraft =
                                    row.hasilDraft?.[rumusKey] || {};

                                return {
                                    Id_Perhitungan: formula.id,
                                    Id_Jenis_Analisa: formula.Id_Jenis_Analisa,
                                    Hasil_Perhitungan:
                                        row.formulaResults[rumusKey] || 0,
                                    Rumus: formula.rumus,
                                    Digit: formula.digit,
                                    No_Urut: hasilDraft.No_Urut || null,
                                    No_Sementara:
                                        hasilDraft.No_Sementara || null,
                                };
                            }
                        );

                        const firstDetail = Object.values(row.draftDetails)[0];
                        const noSementaraForRow = firstDetail
                            ? firstDetail.No_Sementara
                            : null;

                        // Objek utama yang akan dikirim
                        const result = {
                            No_Po_Sampel: this.No_Po_Sampel,
                            Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                            No_Sementara: noSementaraForRow,
                            parameters,
                            formulas,
                            is_multi_print: this.is_multi_print,
                        };

                        if (this.is_multi_print === "Y") {
                            result.No_Po_Multi_Sampel = this.no_ticket;
                        }

                        return result;
                    });

                    // Endpoint mungkin perlu disesuaikan untuk menerima array
                    const response = await axios.post(
                        "/uji-sampel/store-multi-rumus/sementara",
                        { analyses: payload },
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status === 201 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Semua data analisis berhasil disimpan di draft!",
                        }).then(() => {
                            this.fetchDraftData();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                } else {
                    const payload = this.rows.map((row) => {
                        const parameters =
                            this.selectedTemplating.parameter.map((param) => {
                                // AMBIL NILAI DARI INPUT
                                const value = row.inputValues[param.id_qc];
                                const detail = row.draftDetails[param.id_qc];

                                return {
                                    Id_Quality_Control: param.id_qc,
                                    Value_Parameter:
                                        value === "" || value === null
                                            ? null
                                            : value,
                                    No_Urut: detail ? detail.No_Urut : null,
                                    No_Sementara: detail
                                        ? detail.No_Sementara
                                        : null,
                                };
                            });

                        const formulas = this.selectedTemplating.formula.map(
                            (formula) => {
                                const rumusKey = formula.rumus;
                                const hasilDraft =
                                    row.hasilDraft?.[rumusKey] || {};

                                return {
                                    Id_Perhitungan: formula.id,
                                    Id_Jenis_Analisa: formula.Id_Jenis_Analisa,
                                    Hasil_Perhitungan:
                                        row.formulaResults[rumusKey] || 0,
                                    Rumus: formula.rumus,
                                    Digit: formula.digit,
                                    No_Urut: hasilDraft.No_Urut || null,
                                    No_Sementara:
                                        hasilDraft.No_Sementara || null,
                                };
                            }
                        );

                        const firstDetail = Object.values(row.draftDetails)[0];
                        const noSementaraForRow = firstDetail
                            ? firstDetail.No_Sementara
                            : null;

                        // Objek utama yang akan dikirim
                        const result = {
                            No_Po_Sampel: this.No_Po_Sampel,
                            Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                            No_Sementara: noSementaraForRow,
                            parameters,
                            formulas,
                            is_multi_print: this.is_multi_print,
                        };

                        if (this.is_multi_print === "Y") {
                            result.No_Po_Multi_Sampel = this.no_ticket;
                        }

                        return result;
                    });

                    const response = await axios.post(
                        "/uji-sampel/store-not-rumus/sementara",
                        { analyses: payload }
                    );

                    if (response.status === 201 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Semua data analisis berhasil disimpan di draft!",
                        }).then(() => {
                            this.fetchDraftData();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                }
            } catch (error) {
                let errorMessage = "Terjadi Kesalahan";
                if (error.response && error.response.data) {
                    errorMessage =
                        error.response.data.message ||
                        error.response.data.error;
                } else if (error.message) {
                    errorMessage = error.message;
                }
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text: errorMessage,
                });
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
        async updateAnalysisSementaraForDraft() {
            const rowIndex = this.editingRowIndex;

            if (rowIndex === null) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Tidak ada data yang sedang diedit untuk disimpan.",
                });
                return;
            }

            if (!this.reasonForChange) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Alasan mengubah hasil analisa harus diisi.",
                });
                return;
            }

            if (
                this.reasonForChange.trim().length < 10 ||
                /^[\W_]+$/.test(this.reasonForChange.trim())
            ) {
                Swal.fire({
                    icon: "warning",
                    title: "Peringatan",
                    text: "Alasan perubahan harus diisi dengan jujur dan jelas, minimal 10 karakter dan tidak boleh hanya simbol atau tanda baca.",
                });
                return;
            }

            this.loading.editForDatabase = true;

            const row = this.rows[rowIndex];

            const isAnyInputFilled = this.selectedTemplating.parameter.some(
                (param) =>
                    row.inputValues[param.id_qc] !== null &&
                    row.inputValues[param.id_qc] !== ""
            );

            if (!isAnyInputFilled) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: `Data pada baris ${
                        rowIndex + 1
                    } tidak memiliki input sama sekali. Minimal 1 parameter harus diisi.`,
                });
                this.loading.saveToDatabase = false;
                return;
            }

            try {
                if (this.is_multi_print === "Y") {
                    const noSementara =
                        Object.values(row.draftDetails)[0]?.No_Sementara || "";
                    const parameters = this.selectedTemplating.parameter.map(
                        (param) => {
                            const originalData =
                                row.draftDetails[param.id_qc] || {};
                            return {
                                Id_Quality_Control: param.id_qc,
                                Value_Parameter:
                                    row.inputValues[param.id_qc] || 0,
                                Value_Parameter_Lama:
                                    originalData.Value_Parameter || 0,
                                No_Urut: originalData.No_Urut || 0,
                                Id_User: originalData.Id_User || 0,
                                RV_INT: originalData.RV_INT || 0,
                                IdUjiSample: originalData.IdUjiSample || 0,
                            };
                        }
                    );

                    // Prepare formulas data
                    const formulas = this.selectedTemplating.formula.map(
                        (formula) => ({
                            Id_Jenis_Analisa: formula.Id_Jenis_Analisa,
                            Hasil_Perhitungan:
                                parseFloat(row.formulaResults[formula.rumus]) ||
                                0,
                            Rumus: formula.rumus,
                            Digit: formula.digit,
                        })
                    );

                    const originalData =
                        row.draftDetails[this.editingParamIdQc];
                    const newValue = row.inputValues[this.editingParamIdQc];

                    const payload = {
                        analyses: [
                            {
                                No_Urut: this.editingNoUrut,
                                No_Po_Sampel: this.No_Po_Sampel,
                                No_Sementara: noSementara,
                                Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                                RV_INT: originalData.RV_INT,
                                reason: this.reasonForChange,
                                parameters: parameters,
                                Value_Parameter_Lama:
                                    originalData.Value_Parameter,
                                Value_Parameter_Baru: newValue,
                                formulas: formulas,
                                is_multi_print: this.is_multi_print,
                                No_Po_Multi_Sampel:
                                    this.is_multi_print === "Y"
                                        ? this.no_ticket
                                        : null,
                            },
                        ],
                    };

                    const response = await axios.post(
                        `/uji-sampel/store-multi-rumus/sementara/change-update/${rowIndex}`,
                        payload,
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status === 201 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Data analisis berhasil disimpan!",
                        }).then(() => {
                            this.selectedTemplating.parameter.forEach(
                                (param) => {
                                    row.lockedInputs[param.id_qc] = true;
                                }
                            );

                            this.reasonForChange = "";
                            (this.isEditing = false),
                                (this.editingRowIndex = null);
                            this.editingParamIdQc = null;
                            $("#myModalEdit").modal("hide");
                            this.fetchDraftData();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                } else {
                    const noSementara =
                        Object.values(row.draftDetails)[0]?.No_Sementara || "";
                    // Prepare parameters data
                    const parameters = this.selectedTemplating.parameter.map(
                        (param) => {
                            const originalData =
                                row.draftDetails[param.id_qc] || {};
                            return {
                                Id_Quality_Control: param.id_qc,
                                Value_Parameter:
                                    row.inputValues[param.id_qc] || 0,
                                Value_Parameter_Lama:
                                    originalData.Value_Parameter || 0,
                                No_Urut: originalData.No_Urut || 0,
                                Id_User: originalData.Id_User || 0,
                                RV_INT: originalData.RV_INT || 0,
                                IdUjiSample: originalData.IdUjiSample || 0,
                            };
                        }
                    );

                    const formulas = this.selectedTemplating.formula.map(
                        (formula) => ({
                            Id_Jenis_Analisa: formula.Id_Jenis_Analisa,
                            Hasil_Perhitungan:
                                parseFloat(row.formulaResults[formula.rumus]) ||
                                0,
                            Rumus: formula.rumus,
                            Digit: formula.digit,
                        })
                    );

                    const originalData =
                        row.draftDetails[this.editingParamIdQc];
                    const newValue = row.inputValues[this.editingParamIdQc];

                    const payload = {
                        analyses: [
                            {
                                No_Urut: this.editingNoUrut,
                                No_Po_Sampel: this.No_Po_Sampel,
                                No_Sementara: noSementara,
                                Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                                RV_INT: originalData.RV_INT,
                                reason: this.reasonForChange,
                                parameters: parameters,
                                Value_Parameter_Lama:
                                    originalData.Value_Parameter,
                                Value_Parameter_Baru: newValue,
                                formulas: formulas,
                                is_multi_print: this.is_multi_print,
                                No_Po_Multi_Sampel:
                                    this.is_multi_print === "Y"
                                        ? this.no_ticket
                                        : null,
                            },
                        ],
                    };

                    const response = await axios.post(
                        `/uji-sampel/store-not-rumus/sementara/change-update/${rowIndex}`,
                        payload,
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status === 201 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Data analisis berhasil disimpan!",
                        }).then(() => {
                            // Lock all inputs in the row
                            this.selectedTemplating.parameter.forEach(
                                (param) => {
                                    row.lockedInputs[param.id_qc] = true;
                                }
                            );

                            // Reset form
                            this.reasonForChange = "";
                            this.editingRowIndex = null;
                            (this.isEditing = false),
                                (this.editingParamIdQc = null);
                            $("#myModalEdit").modal("hide");
                            this.fetchDraftData();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                }
            } catch (error) {
                console.error(error);
                let errorMessage = "Terjadi Kesalahan";
                if (error.response && error.response.data) {
                    if (error.response.data.errors) {
                        // Handle validation errors
                        const errors = Object.values(
                            error.response.data.errors
                        ).flat();
                        errorMessage = errors.join("\n");
                    } else {
                        errorMessage =
                            error.response.data.message ||
                            error.response.data.error;
                    }
                } else if (error.message) {
                    errorMessage = error.message;
                }
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    html: errorMessage.replace(/\n/g, "<br>"), // Display multiple errors with line breaks
                });
            } finally {
                this.loading.editForDatabase = false;
            }
        },
        async deleteAnalysisSementaraForDraft() {
            const rowIndex = this.deleteRowIndex;
            const nomorSementara = this.deleteNoSementara;

            if (rowIndex === null) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Tidak ada data",
                });
                return;
            }

            if (nomorSementara === null) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Tidak ada data yang sedang diedit untuk disimpan.",
                });
                return;
            }

            if (!this.hapuskey) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Alasan Menghapus hasil analisa harus diisi.",
                });
                return;
            }

            if (this.hapuskey !== "delete/hasil-analisa") {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Kunci Untuk Menghapus Analisa Tidak Boleh Kosong",
                });
                return;
            }

            this.loading.deleteForDatabase = true;

            const row = this.rows[rowIndex];
            const parameters = this.selectedTemplating.parameter.map(
                (param) => {
                    const originalData = row.draftDetails[param.id_qc] || {};
                    return {
                        Id_Quality_Control: param.id_qc,
                        Value_Parameter: row.inputValues[param.id_qc] || 0,
                        Value_Parameter_Lama: originalData.Value_Parameter || 0,
                        No_Urut: originalData.No_Urut || 0,
                        Id_User: originalData.Id_User || 0,
                        RV_INT: originalData.RV_INT || 0,
                        IdUjiSample: originalData.IdUjiSample || 0,
                    };
                }
            );
            const formulas = this.selectedTemplating.formula.map((formula) => ({
                Id_Jenis_Analisa: formula.Id_Jenis_Analisa,
                Hasil_Perhitungan:
                    parseFloat(row.formulaResults[formula.rumus]) || 0,
                Rumus: formula.rumus,
                Digit: formula.digit,
            }));
            const noSementara =
                Object.values(row.draftDetails)[0]?.No_Sementara || "";

            const payload = {
                analyses: [
                    {
                        No_Sementara: noSementara,
                        No_Po_Sampel: this.No_Po_Sampel,
                        Id_Jenis_Analisa: this.Id_Jenis_Analisa,
                        reason: this.reasonForChange,
                        parameters: parameters,
                        formulas: formulas,
                        is_multi_print: this.is_multi_print,
                        No_Po_Multi_Sampel:
                            this.is_multi_print === "Y" ? this.no_ticket : null,
                    },
                ],
            };

            if (this.is_multi_print === "Y") {
                try {
                    const response = await axios.post(
                        `/uji-sampel/store-multi-rumus/sementara/hapus-data/${nomorSementara}`,
                        payload,
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status === 200 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Yeay, Data Terhapus !",
                        }).then(() => {
                            this.selectedTemplating.parameter.forEach(
                                (param) => {
                                    row.lockedInputs[param.id_qc] = true;
                                }
                            );
                            this.hapuskey = "";
                            this.reasonForChange = "";
                            this.deleteNoSementara = null;
                            this.rows.splice(rowIndex, 1);
                            $("#myModalHapus").modal("hide");
                            this.fetchDraftData();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                } catch (error) {
                    console.error(error);
                    let errorMessage = "Terjadi Kesalahan";
                    if (error.response && error.response.data) {
                        if (error.response.data.errors) {
                            // Handle validation errors
                            const errors = Object.values(
                                error.response.data.errors
                            ).flat();
                            errorMessage = errors.join("\n");
                        } else {
                            errorMessage =
                                error.response.data.message ||
                                error.response.data.error;
                        }
                    } else if (error.message) {
                        errorMessage = error.message;
                    }
                    Swal.fire({
                        icon: "error",
                        title: "Opss...",
                        html: errorMessage.replace(/\n/g, "<br>"),
                    });
                } finally {
                    this.loading.deleteForDatabase = false;
                }
            } else {
                try {
                    const response = await axios.post(
                        `/uji-sampel/store-not-rumus/sementara/hapus-data/${nomorSementara}`,
                        payload,
                        {
                            headers: {
                                "X-CSRF-TOKEN": document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute("content"),
                            },
                        }
                    );

                    if (response.status === 200 && response.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil",
                            text: "Yeay, Data Terhapus !",
                        }).then(() => {
                            this.selectedTemplating.parameter.forEach(
                                (param) => {
                                    row.lockedInputs[param.id_qc] = true;
                                }
                            );

                            this.hapuskey = "";
                            this.reasonForChange = "";
                            this.deleteNoSementara = null;
                            this.rows.splice(rowIndex, 1);
                            $("#myModalHapus").modal("hide");
                            this.fetchDraftData();
                        });
                    } else {
                        throw new Error(
                            response.data.message || "Gagal menyimpan data."
                        );
                    }
                } catch (error) {
                    console.error(error);
                    let errorMessage = "Terjadi Kesalahan";
                    if (error.response && error.response.data) {
                        if (error.response.data.errors) {
                            // Handle validation errors
                            const errors = Object.values(
                                error.response.data.errors
                            ).flat();
                            errorMessage = errors.join("\n");
                        } else {
                            errorMessage =
                                error.response.data.message ||
                                error.response.data.error;
                        }
                    } else if (error.message) {
                        errorMessage = error.message;
                    }
                    Swal.fire({
                        icon: "error",
                        title: "Opss...",
                        html: errorMessage.replace(/\n/g, "<br>"), // Display multiple errors with line breaks
                    });
                } finally {
                    this.loading.deleteForDatabase = false;
                }
            }
        },
        checkDigit(event) {
            const allowedKeys = [
                "Backspace",
                "Delete",
                "ArrowLeft",
                "ArrowRight",
            ];

            if (!/[\d.]/.test(event.key) && !allowedKeys.includes(event.key)) {
                event.preventDefault();
            }
        },
        handlePaste(event) {
            const pastedData = event.clipboardData.getData("Text");
            if (!/^[\d.]+$/.test(pastedData)) {
                event.preventDefault();
            }
        },
        formatTanggal(tanggalString) {
            const date = new Date(tanggalString);
            const options = { day: "2-digit", month: "short", year: "numeric" };
            return date.toLocaleDateString("en-GB", options);
        },
        jamKeMenit(jamStr) {
            const [jam, menit, detik] = jamStr.split(":").map(Number);
            return jam * 60 + menit + detik / 60;
        },
    },
};
</script>

<style scoped>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4); /* efek gelap */
    backdrop-filter: blur(5px); /* efek blur */
    z-index: 1050; /* pastikan di atas semua elemen */
    pointer-events: auto; /* cegah klik di belakang */
}

.loading-box {
    min-width: 320px;
    max-width: 400px;
}

.loading-box p {
    font-size: 1.1rem;
    color: #ffffff !important;
}

.loading-spinner {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.disabled-opacity {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-primary:disabled {
    background-color: #6c757d;
    border-color: #6c757d;
    opacity: 0.65;
    cursor: not-allowed;
}

.psz-wide-column {
    min-width: 250px;
}

.action-button:disabled {
    background-color: #6c757d;
    border-color: #6c757d;
    opacity: 0.65;
    color: black;
    cursor: not-allowed;
}

/* Badge Custom Style */
.badge {
    padding: 0.5em 0.75em;
    font-size: 0.8em;
    font-weight: 500;
    letter-spacing: 0.05em;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    transition: all 0.2s ease;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.badge i {
    font-size: 0.9em;
    margin-right: 0.3em;
}

/* Warna khusus untuk badge */
.bg-primary-subtle {
    background-color: #e0f2fe !important;
    color: #0369a1 !important;
}

.bg-info-subtle {
    background-color: #e0f7fa !important;
    color: #00838f !important;
}

.bg-secondary-subtle {
    background-color: #e9ecef !important;
    color: #495057 !important;
}

.bg-success-subtle {
    background-color: #e6f7ee !important;
    color: #0d8050 !important;
}

.bg-warning-subtle {
    background-color: #fff3e0 !important;
    color: #e65100 !important;
}

/* Text Truncate */
.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Date Time Style */
.text-muted.small {
    font-size: 0.85em;
    color: #6c757d !important;
}

/* Hover Effect on Badges */
.badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
.performance-chart-container {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.chart-wrapper {
    margin-bottom: 20px;
    border: 1px solid #eaeaea;
    border-radius: 8px;
    padding: 10px;
    background: white;
}

.legend-container {
    display: flex;
    justify-content: center;
    gap: 24px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    font-size: 14px;
    color: #555;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    margin-right: 8px;
    display: inline-block;
}
</style>

<style>
.scroll-hint {
    font-size: 13px;
    color: #555;
    margin-bottom: 6px;
    text-align: left;
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 0.3;
    }
    50% {
        opacity: 1;
    }
}

.table-scroll-wrapper {
    overflow-x: auto;
    width: 100%;
    position: relative;
    padding-bottom: 1rem;
    scrollbar-width: thin;
    scrollbar-color: #aaa transparent;
}

.table-scroll-wrapper::-webkit-scrollbar {
    height: 4px;
}

.table-scroll-wrapper::-webkit-scrollbar-thumb {
    background-color: #3f5189 !important;
    border-radius: 4px;
}

.modern-analysis-table {
    margin-left: 8px;
    min-width: 1200px;
    width: max-content;
    border-collapse: collapse;
    border: 1px solid #dee2e6; /* Border luar */
    font-size: 14px;
}

/* Tambahkan border di setiap sel */
.modern-analysis-table th,
.modern-analysis-table td {
    border: 1px solid #dee2e6;
    padding: 8px;
}

/* Pusatkan teks di thead */
.modern-analysis-table thead th {
    text-align: center;
    background-color: #f8f9fa; /* seperti Bootstrap thead-light */
    font-weight: 600;
    vertical-align: middle;
    white-space: nowrap;
    font-size: 12px;
}

/* Posisikan data sel */
.modern-analysis-table td {
    vertical-align: top;
    white-space: nowrap;
}

.custom-tooltip {
    padding: 12px;
    background: white;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.tooltip-header {
    font-weight: bold;
    font-size: 14px;
    color: #333;
    margin-bottom: 4px;
}

.tooltip-subheader {
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
}

.tooltip-row {
    display: flex;
    justify-content: space-between;
    margin: 6px 0;
    font-size: 13px;
}

.tooltip-row span:first-child {
    color: #777;
    margin-right: 12px;
}

.tooltip-row span:last-child {
    font-weight: 500;
    color: #333;
}
</style>

<style>
/* Kontainer utama untuk input dan tombol */
.input-container {
    position: relative;
    display: flex;
    align-items: center;
}

/* Gaya khusus untuk input saat dalam mode edit */
.form-control.is-editing {
    border-color: #86b7fe;
    background-color: #f0f8ff; /* Warna biru muda yang sangat lembut */
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* Kontainer untuk semua tombol aksi, diposisikan di kanan */
.input-actions {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
}

.button-group {
    display: flex;
    align-items: center;
    gap: 4px; /* Jarak antar tombol simpan & batal */
}

/* Gaya dasar untuk SEMUA tombol ikon (Edit, Simpan, Batal) */
.btn-action-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px; /* Ukuran tombol */
    height: 28px; /* Ukuran tombol */
    border-radius: 50%; /* Membuat tombol menjadi lingkaran */
    border: none;
    color: white;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    opacity: 1;
    transform: scale(1);
}

.btn-action-icon:hover {
    transform: scale(1.1); /* Sedikit membesar saat di-hover */
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

/* Tombol Edit (Pensil) */
.btn-edit {
    background-color: #6c757d; /* Abu-abu sekunder */
    color: #fff;
}
.btn-edit:hover {
    background-color: #5a6268;
}

/* Tombol Simpan (Centang) */
.btn-glamor {
    background-color: #28a745; /* Hijau sukses */
}
.btn-glamor:hover {
    background-color: #218838;
}

/* Tombol Batal (Silang) */
.btn-cancel {
    background-color: #dc3545; /* Merah bahaya */
}
.btn-cancel:hover {
    background-color: #c82333;
}

/* * --- TOOLTIP KUSTOM ---
 * Menggunakan atribut [data-tooltip]
 */
[data-tooltip] {
    position: relative;
}

[data-tooltip]::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 125%; /* Posisi di atas tombol */
    left: 50%;
    transform: translateX(-50%);

    background-color: #343a40; /* Latar belakang gelap */
    color: #fff;
    font-size: 12px;
    font-weight: 500;
    padding: 6px 10px;
    border-radius: 6px;
    white-space: nowrap;

    opacity: 0; /* Sembunyikan secara default */
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
    z-index: 10;
}

/* Tampilkan tooltip saat tombol di-hover */
[data-tooltip]:hover::after {
    opacity: 1;
    visibility: visible;
}

.modern-delete-btn {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 50%;
    background: transparent;
    color: #ff4444;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.modern-delete-btn:hover {
    background: rgba(255, 68, 68, 0.1);
    transform: scale(1.05);
}

.modern-delete-btn:active {
    transform: scale(0.95);
}

.modern-delete-btn::after {
    content: "";
    position: absolute;
    width: 100%;
    height: 100%;
    border: 1px solid #ff4444;
    border-radius: 50%;
    opacity: 0;
    transition: all 0.3s ease;
}

.modern-delete-btn:hover::after {
    opacity: 0.3;
    transform: scale(1.2);
}

.modern-delete-btn i {
    font-size: 14px;
    transition: transform 0.2s ease;
}

.modern-delete-btn:hover i {
    transform: scale(1.1);
}

.header-kalkulator {
    text-align: center;
    margin-bottom: 1rem;
    padding-bottom: 1.5rem;
}

.judul-kalkulator {
    font-size: 2.2rem;
    font-weight: 700;
    color: #3f5189;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.deskripsi-kalkulator {
    font-size: 1.1rem;
    color: #35477b;
    max-width: 700px;
    margin: 0 auto;
}

.isi-dokumentasi {
    margin-bottom: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

/* Base Styles */
.calculation-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 4rem;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    color: #333;
    width: 100%; /* menggantikan max-width */
    margin: 0; /* hapus auto center */
    padding: 1rem;
}

/* Section Headers */
.section-header {
    margin-bottom: 1.5rem;
}

.section-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 0.5rem;
}

.section-badge i {
    margin-right: 0.5rem;
}

.formula-badge {
    background-color: rgba(13, 110, 253, 0.1);
    color: #0d6efd;
    border-left: 4px solid #0d6efd;
}

.result-badge {
    background-color: rgba(25, 135, 84, 0.1);
    color: #198754;
    border-left: 4px solid #198754;
}

.section-description {
    font-size: 0.9rem;
    color: #6c757d;
    margin-left: 0.25rem;
}

/* Parameter Table */
.parameter-table-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.responsive-table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.parameter-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.parameter-table th {
    background-color: #f8f9fa;
    padding: 0.75rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
}

.parameter-table td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid #e9ecef;
}

.parameter-index {
    font-weight: 500;
    color: #6c757d;
    width: 50px;
}

.parameter-name {
    font-weight: 500;
    min-width: 200px;
}

.parameter-unit {
    color: #6c757d;
    font-size: 0.85em;
    margin-left: 0.25rem;
}

.parameter-input-cell {
    min-width: 200px;
}

.input-group {
    display: flex;
    align-items: stretch;
}

.parameter-input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 4px 0 0 4px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.parameter-input:focus {
    border-color: #86b7fe;
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.unit-display {
    background-color: #e9ecef;
    border: 1px solid #ced4da;
    border-left: 0;
    padding: 0.5rem 0.75rem;
    border-radius: 0 4px 4px 0;
    color: #495057;
}

/* Results Section */
.results-container {
    display: grid;
    gap: 1rem;
}

.result-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 1.25rem;
    border-left: 4px solid #198754;
}

.result-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.result-title {
    font-weight: 600;
    color: #212529;
    display: flex;
    align-items: center;
}

.result-title i {
    color: #198754;
    margin-right: 0.5rem;
    font-size: 1.1rem;
}

.result-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #198754;
    background-color: rgba(25, 135, 84, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    min-width: 80px;
    text-align: center;
}

.result-notes {
    background-color: #f8f9fa;
    border-radius: 6px;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.notes-header {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 0.25rem;
}

.notes-header i {
    margin-right: 0.5rem;
}

.notes-content {
    font-size: 0.9rem;
    color: #495057;
    line-height: 1.5;
}

.result-footer {
    display: flex;
    justify-content: flex-end;
}

.calculation-method {
    font-size: 0.8rem;
    color: #6c757d;
}

.method-label {
    font-weight: 500;
    margin-right: 0.25rem;
}

/* Highlight Effect */
.parameter-row.highlighted {
    background-color: rgba(13, 110, 253, 0.05);
    transition: background-color 0.3s ease;
}

/* Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.result-card {
    animation: fadeIn 0.3s ease-out forwards;
}

/* Panel Styles */
.panel-header {
    padding: 1.25rem 1.5rem;
    background-color: #f1f5f9;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header.with-tabs {
    border-bottom: none;
}

.panel-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.panel-body {
    padding: 1.5rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #f1f5f9;
}

.btn-submit {
    background-color: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-submit:hover {
    background-color: #2563eb;
}

.btn-save {
    background-color: #8b5cf6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.btn-save:hover {
    background-color: #7c3aed;
}

.modern-form {
    font-family: "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    margin: 0 auto;
}

.sample-info-card {
    background: #f8f9ff;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    gap: 32px;
    border: 1px solid #e0e7ff;
}

.info-item {
    display: flex;
    align-items: center;
}

.info-label {
    font-weight: 500;
    color: #4b5563;
    margin-right: 8px;
}

.info-value {
    font-weight: 600;
    color: #1e293b;
}

@media print {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }

    .parameter-table-container,
    .result-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

@media (max-width: 650px) {
    .modern-form {
        width: 100%;
    }

    .sample-info-card {
        width: 100%;
        flex-direction: column;
    }

    .panel-header {
        display: flex;
        gap: 1rem;
        font-size: 0.6rem;
    }

    .nameValue {
        font-size: 0.5rem !important;
    }
}

/* Responsive Layout */
@media (min-width: 992px) {
    .calculation-container {
        grid-template-columns: 1fr 1fr;
    }
}

@media (min-width: 1200px) {
    .calculation-container {
        grid-template-columns: 2fr 1fr;
    }
}
</style>
