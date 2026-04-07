<template>
    <div class="container-fluid px-0 sample-registration-container">
        <div class="card shadow-sm border-0 w-100 main-card">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start section-header">
                    <div
                        class="d-flex justify-content-between align-items-start"
                    >
                        <div
                            class="d-flex align-items-center mb-3 header-content"
                        >
                            <i
                                class="fas fa-vial-circle-check text-primary me-3 fa-2x header-icon"
                            ></i>
                            <div>
                                <h1 class="h2 fw-bold mb-1 main-title">
                                    History Registrasi Sampel
                                </h1>
                                <p class="text-muted mb-0 subtitle">
                                    <i class="fas fa-list-check me-1"></i>
                                    Daftar lengkap registrasi sampel produksi
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <ul
                    class="nav nav-tabs nav-tabs-custom nav-success nav-justified mb-3"
                    role="tablist"
                >
                    <li class="nav-item" role="presentation">
                        <a
                            class="nav-link active"
                            data-bs-toggle="tab"
                            href="#home1"
                            role="tab"
                            aria-selected="true"
                        >
                            Detail
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a
                            class="nav-link"
                            data-bs-toggle="tab"
                            href="#profile1"
                            role="tab"
                            aria-selected="false"
                            tabindex="-1"
                        >
                            Visualisasi
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content text-muted">
                    <div
                        class="tab-pane active show"
                        id="home1"
                        role="tabpanel"
                    >
                        <!-- Search and Filter Section -->
                        <div class="row mb-4 g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Cari No Sampel, Sub Sampel, No PO..."
                                        v-model="searchQuery"
                                        @input="applyFilters"
                                    />
                                    <button
                                        class="btn btn-primary"
                                        @click="applyFilters"
                                    >
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-wrap gap-2">
                                    <div class="dropdown">
                                        <button
                                            class="btn btn-primary dropdown-toggle"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                        >
                                            <i class="fas fa-sliders me-1"></i>
                                            Filter
                                        </button>
                                        <div
                                            class="dropdown-menu dropdown-menu-end p-3 filter-dropdown"
                                        >
                                            <div class="row g-2">
                                                <div class="col-12">
                                                    <label
                                                        class="form-label small text-muted"
                                                        >Tanggal
                                                        Registrasi</label
                                                    >
                                                    <input
                                                        type="date"
                                                        class="form-control form-control-sm"
                                                        v-model="dateFilter"
                                                        @change="applyFilters"
                                                    />
                                                </div>
                                                <div class="col-12">
                                                    <label
                                                        class="form-label small text-muted"
                                                        >No PO / Split PO</label
                                                    >
                                                    <input
                                                        type="text"
                                                        class="form-control form-control-sm"
                                                        placeholder="PR0325-0001 atau PR0325-0001-1"
                                                        v-model="poFilter"
                                                        @input="applyFilters"
                                                    />
                                                </div>
                                                <div class="col-12">
                                                    <label
                                                        class="form-label small text-muted"
                                                        >Nama Mesin</label
                                                    >
                                                    <input
                                                        type="text"
                                                        class="form-control form-control-sm"
                                                        placeholder="Contoh: MIXER"
                                                        v-model="machineFilter"
                                                        @input="applyFilters"
                                                    />
                                                </div>
                                                <div class="col-12">
                                                    <label
                                                        class="form-label small text-muted"
                                                        >Status Sampel</label
                                                    >
                                                    <select
                                                        class="form-select form-select-sm"
                                                        v-model="statusFilter"
                                                        @change="applyFilters"
                                                    >
                                                        <option value="">
                                                            Semua Status
                                                        </option>
                                                        <option
                                                            value="terdaftar"
                                                        >
                                                            Terdaftar
                                                        </option>
                                                        <option
                                                            value="dibatalkan"
                                                        >
                                                            Dibatalkan
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button
                                        class="btn btn-secondary"
                                        @click="resetFilters"
                                        :disabled="!isFilterActive"
                                    >
                                        <i class="fas fa-rotate-left me-1"></i>
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4 col-6 mb-3">
                                <div
                                    class="card stat-card bg-primary bg-opacity-10 border-primary border-opacity-25"
                                >
                                    <div class="card-body py-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-primary bg-opacity-10 p-2 rounded me-3"
                                            >
                                                <i
                                                    class="fas fa-list text-primary"
                                                ></i>
                                            </div>
                                            <div>
                                                <p
                                                    class="small text-muted mb-0"
                                                >
                                                    Total Data
                                                </p>
                                                <h5 class="mb-0">
                                                    {{ totalData }}
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6 mb-3">
                                <div
                                    class="card stat-card bg-success bg-opacity-10 border-success border-opacity-25"
                                >
                                    <div class="card-body py-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-success bg-opacity-10 p-2 rounded me-3"
                                            >
                                                <i
                                                    class="fas fa-check-circle text-success"
                                                ></i>
                                            </div>
                                            <div>
                                                <p
                                                    class="small text-muted mb-0"
                                                >
                                                    Terdaftar
                                                </p>
                                                <h5 class="mb-0">
                                                    {{
                                                        statusCountsTotal.Terdaftar ||
                                                        0
                                                    }}
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 col-6 mb-3">
                                <div
                                    class="card stat-card bg-danger-soft border-danger border-opacity-25"
                                >
                                    <div class="card-body py-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-danger-soft bg-opacity-10 p-2 rounded me-3"
                                            >
                                                <i
                                                    class="fas fa-times-circle text-danger"
                                                ></i>
                                            </div>
                                            <div>
                                                <p
                                                    class="small text-muted mb-0"
                                                >
                                                    Dibatalkan
                                                </p>
                                                <h5 class="mb-0">
                                                    {{
                                                        statusCountsTotal.Dibatalkan ||
                                                        0
                                                    }}
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data List -->
                        <div class="col-12 mt-3 content-area">
                            <div
                                v-if="loading.loadingListData"
                                class="text-center py-5"
                            >
                                <div
                                    class="spinner-grow text-primary"
                                    role="status"
                                >
                                    <span class="visually-hidden"
                                        >Loading...</span
                                    >
                                </div>
                                <p class="mt-3 text-muted">
                                    Memuat data registrasi sampel...
                                </p>
                            </div>

                            <div v-else>
                                <div
                                    v-if="filteredData.length === 0"
                                    class="d-flex justify-content-center align-items-center flex-column text-center py-5"
                                    style="min-height: 300px"
                                >
                                    <DotLottieVue
                                        style="
                                            height: 100px;
                                            width: 100px;
                                            margin-bottom: 1rem;
                                        "
                                        autoplay
                                        loop
                                        src="/animation/empty2.json"
                                    />
                                    <h5 class="text-muted mb-1">
                                        Tidak ada data registrasi ditemukan
                                    </h5>
                                    <p class="text-muted small mb-2">
                                        Coba ubah kriteria pencarian atau filter
                                        Anda
                                    </p>
                                    <button
                                        class="btn btn-primary"
                                        @click="resetFilters"
                                    >
                                        <i
                                            class="fas fa-filter-circle-xmark me-1"
                                        ></i>
                                        Reset Filter
                                    </button>
                                </div>

                                <div class="accordion" id="dataAccordion">
                                    <div
                                        class="accordion-item border-0 mb-3 shadow-sm rounded-3 overflow-hidden"
                                        v-for="item in filteredData"
                                        :key="item.id"
                                    >
                                        <div
                                            class="accordion-header"
                                            :class="{
                                                'bg-success-soft':
                                                    item.Status === 'Terdaftar',
                                                'bg-danger-soft':
                                                    item.Status ===
                                                    'Dibatalkan',
                                            }"
                                        >
                                            <button
                                                class="accordion-button collapsed d-flex align-items-center"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                :data-bs-target="
                                                    '#collapse' + item.id
                                                "
                                                :class="{
                                                    'text-success':
                                                        item.Status ===
                                                        'Terdaftar',
                                                    'text-danger':
                                                        item.Status ===
                                                        'Dibatalkan',
                                                }"
                                            >
                                                <div
                                                    class="d-flex flex-column flex-md-row w-100"
                                                >
                                                    <div
                                                        class="d-flex align-items-center me-md-4 mb-2 mb-md-0"
                                                    >
                                                        <span
                                                            class="badge rounded-pill me-2"
                                                            :class="{
                                                                'bg-success-soft text-success':
                                                                    item.Status ===
                                                                    'Terdaftar',
                                                                'bg-danger-soft text-danger':
                                                                    item.Status ===
                                                                    'Dibatalkan',
                                                            }"
                                                        >
                                                            <i
                                                                class="fas me-1"
                                                                :class="{
                                                                    'fa-check-circle':
                                                                        item.Status ===
                                                                        'Terdaftar',
                                                                    'fa-ban':
                                                                        item.Status ===
                                                                        'Dibatalkan',
                                                                }"
                                                            ></i>
                                                            {{ item.Status }}
                                                        </span>
                                                        <div
                                                            class="d-flex flex-column"
                                                        >
                                                            <strong
                                                                class="text-dark"
                                                                >{{
                                                                    item.No_Sampel
                                                                }}</strong
                                                            >
                                                            <div
                                                                v-if="
                                                                    item.Sub_No_PO &&
                                                                    item
                                                                        .Sub_No_PO
                                                                        .length >
                                                                        0
                                                                "
                                                                class="d-flex flex-wrap gap-1 mt-1"
                                                            >
                                                                <span
                                                                    v-for="(
                                                                        sub,
                                                                        index
                                                                    ) in item.Sub_No_PO"
                                                                    :key="index"
                                                                    class="badge bg-primary-soft text-primary small"
                                                                >
                                                                    {{ sub }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="d-flex flex-wrap gap-3 text-muted small"
                                                    >
                                                        <span
                                                            ><i
                                                                class="fas fa-calendar-day me-1"
                                                            ></i>
                                                            {{
                                                                formatDate(
                                                                    item.Tanggal
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            ><i
                                                                class="fas fa-clock me-1"
                                                            ></i>
                                                            {{ item.Jam }}</span
                                                        >
                                                        <span v-if="item.No_PO"
                                                            ><i
                                                                class="fas fa-file-invoice me-1"
                                                            ></i>
                                                            {{
                                                                item.No_PO
                                                            }}</span
                                                        >
                                                        <span
                                                            v-if="item.Split_PO"
                                                            ><i
                                                                class="fas fa-file-invoice-dollar me-1"
                                                            ></i>
                                                            {{
                                                                item.Split_PO
                                                            }}</span
                                                        >

                                                        <span
                                                            v-if="
                                                                item.Nama_Mesin
                                                            "
                                                            class="badge badge-mesin d-inline-flex align-items-center gap-1"
                                                        >
                                                            <i
                                                                class="fas fa-cogs text-warning"
                                                            ></i>
                                                            {{
                                                                item.Nama_Mesin
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </button>
                                        </div>
                                        <div
                                            :id="'collapse' + item.id"
                                            class="accordion-collapse collapse"
                                            :data-bs-parent="'#dataAccordion'"
                                        >
                                            <div class="accordion-body pt-3">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div
                                                            class="row g-2 mb-3"
                                                        >
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-vial me-1 text-primary"
                                                                    ></i>
                                                                    No Sampel
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        item.No_Sampel ||
                                                                        "-"
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-box me-1 text-primary"
                                                                    ></i>
                                                                    Nama Barang
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        item.Nama_Barang ||
                                                                        "-"
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-file-invoice me-1 text-primary"
                                                                    ></i>
                                                                    No PO
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        item.No_PO ||
                                                                        "-"
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-file-invoice-dollar me-1 text-primary"
                                                                    ></i>
                                                                    Split PO
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        item.Split_PO ||
                                                                        "-"
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-barcode me-1 text-primary"
                                                                    ></i>
                                                                    No Batch
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        item.No_Batch ||
                                                                        0
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <div class="col-6">
                                                                <div
                                                                    v-if="
                                                                        item.Jumlah_Pcs ===
                                                                        null
                                                                    "
                                                                >
                                                                    <label
                                                                        class="small text-muted"
                                                                    >
                                                                        <i
                                                                            class="fas fa-weight me-1 text-primary"
                                                                        ></i>
                                                                        Berat
                                                                        Sampel
                                                                    </label>
                                                                    <p
                                                                        class="fw-bold"
                                                                    >
                                                                        {{
                                                                            item.Berat_Sampel ||
                                                                            0
                                                                        }}
                                                                    </p>
                                                                </div>
                                                                <div v-else>
                                                                    <label
                                                                        class="small text-muted"
                                                                    >
                                                                        <i
                                                                            class="fas fa-box me-1 text-primary"
                                                                        ></i>
                                                                        Jumlah
                                                                        Pcs
                                                                    </label>
                                                                    <p
                                                                        class="fw-bold"
                                                                    >
                                                                        {{
                                                                            item.Jumlah_Pcs ||
                                                                            0
                                                                        }}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-calendar-alt me-1 text-primary"
                                                                    ></i>
                                                                    Tanggal
                                                                    Registrasi
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        formatDate(
                                                                            item.Tanggal
                                                                        )
                                                                    }}
                                                                    {{
                                                                        item.Jam
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-user-cog me-1 text-primary"
                                                                    ></i>
                                                                    Operator
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        item.Operator ||
                                                                        "-"
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <div class="col-6">
                                                                <label
                                                                    class="small text-muted"
                                                                >
                                                                    <i
                                                                        class="fas fa-cogs me-1 text-primary"
                                                                    ></i>
                                                                    Seri Mesin
                                                                </label>
                                                                <p
                                                                    class="fw-bold"
                                                                >
                                                                    {{
                                                                        item.Seri_Mesin ||
                                                                        "-"
                                                                    }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div
                                                            class="mb-3"
                                                            v-if="
                                                                item.Sub_No_PO &&
                                                                item.Sub_No_PO
                                                                    .length > 0
                                                            "
                                                        >
                                                            <label
                                                                class="small text-muted"
                                                            >
                                                                <i
                                                                    class="fas fa-vials me-1 text-primary"
                                                                ></i>

                                                                No Sub Sampel
                                                            </label>
                                                            <div class="">
                                                                <div
                                                                    class="d-flex flex-wrap gap-1 mt-1"
                                                                >
                                                                    <span
                                                                        v-for="(
                                                                            sub,
                                                                            index
                                                                        ) in item.Sub_No_PO"
                                                                        :key="
                                                                            index
                                                                        "
                                                                        class="badge bg-primary-soft text-primary small"
                                                                    >
                                                                        {{
                                                                            sub
                                                                        }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label
                                                                class="small text-muted"
                                                            >
                                                                <i
                                                                    class="fas fa-info-circle me-1 text-primary"
                                                                ></i>
                                                                Keterangan
                                                            </label>
                                                            <div
                                                                class="card bg-light border-0 p-3 mb-3"
                                                            >
                                                                <p class="mb-0">
                                                                    {{
                                                                        item.Keterangan ||
                                                                        "Tidak ada keterangan"
                                                                    }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div
                                                    class="d-flex justify-content-end gap-2 mt-3"
                                                >
                                                    <button
                                                        :disabled="
                                                            loading.cetakUlangQrCode
                                                        "
                                                        @click="
                                                            submitCetakUlangQrCode(
                                                                item
                                                            )
                                                        "
                                                        class="btn btn-sm btn-primary"
                                                    >
                                                        <i
                                                            class="ri-qr-code-line me-1"
                                                        ></i>
                                                        Cetak Ulang QrCode
                                                    </button>
                                                </div>

                                                <div
                                                    v-if="
                                                        item.tracking &&
                                                        item.tracking.length > 0
                                                    "
                                                >
                                                    <hr class="mb-3" />
                                                    <h6>
                                                        Tracking
                                                        <i
                                                            class="fas fa-truck-fast text-primary"
                                                        ></i>
                                                    </h6>

                                                    <div
                                                        class="profile-timeline"
                                                    >
                                                        <div
                                                            class="accordion accordion-flush"
                                                            id="accordionTracking"
                                                        >
                                                            <div
                                                                class="accordion-item border-0"
                                                                v-for="(
                                                                    track, index
                                                                ) in item.tracking"
                                                                :key="index"
                                                            >
                                                                <div
                                                                    class="accordion-header"
                                                                    :id="
                                                                        'heading' +
                                                                        index
                                                                    "
                                                                >
                                                                    <a
                                                                        class="accordion-button p-2 shadow-none"
                                                                        data-bs-toggle="collapse"
                                                                        :href="
                                                                            '#collapse' +
                                                                            index
                                                                        "
                                                                        aria-expanded="true"
                                                                        :aria-controls="
                                                                            'collapse' +
                                                                            index
                                                                        "
                                                                    >
                                                                        <div
                                                                            class="d-flex align-items-center w-100"
                                                                        >
                                                                            <div
                                                                                class="flex-shrink-0"
                                                                            >
                                                                                <div
                                                                                    class="avatar-xs"
                                                                                >
                                                                                    <div
                                                                                        class="avatar-title rounded-circle"
                                                                                        :class="{
                                                                                            'bg-success text-white':
                                                                                                track.Status_Aktivitas &&
                                                                                                track.Status_Aktivitas.toLowerCase() ===
                                                                                                    'berhasil',
                                                                                            'bg-danger text-white':
                                                                                                track.Status_Aktivitas &&
                                                                                                track.Status_Aktivitas.toLowerCase() ===
                                                                                                    'gagal',
                                                                                            'bg-primary text-white':
                                                                                                track.Status_Aktivitas &&
                                                                                                track.Status_Aktivitas.toLowerCase() ===
                                                                                                    'progress',
                                                                                            'bg-secondary text-white':
                                                                                                ![
                                                                                                    'berhasil',
                                                                                                    'gagal',
                                                                                                    'progress',
                                                                                                ].includes(
                                                                                                    track.Status_Aktivitas &&
                                                                                                        track.Status_Aktivitas.toLowerCase()
                                                                                                ),
                                                                                        }"
                                                                                    ></div>
                                                                                </div>
                                                                            </div>

                                                                            <div
                                                                                class="d-flex flex-column gap-1 ms-3 flex-grow-1"
                                                                            >
                                                                                <div
                                                                                    class="d-flex justify-content-between align-items-center"
                                                                                >
                                                                                    <h6
                                                                                        class="fs-14 mb-0 fw-semibold"
                                                                                    >
                                                                                        {{
                                                                                            track.Jenis_Aktivitas
                                                                                        }}
                                                                                    </h6>
                                                                                    <small
                                                                                        class="text-muted fw-normal"
                                                                                        >{{
                                                                                            formatDate(
                                                                                                track.Tanggal
                                                                                            )
                                                                                        }}
                                                                                        {{
                                                                                            track.Jam
                                                                                        }}</small
                                                                                    >
                                                                                </div>
                                                                                <div>
                                                                                    <span
                                                                                        v-if="
                                                                                            track.Status_Aktivitas
                                                                                        "
                                                                                        class="badge"
                                                                                        :class="{
                                                                                            'bg-success-subtle text-success':
                                                                                                track.Status_Aktivitas.toLowerCase() ===
                                                                                                'berhasil',
                                                                                            'bg-danger-subtle text-danger':
                                                                                                track.Status_Aktivitas.toLowerCase() ===
                                                                                                'gagal',
                                                                                            'bg-primary-subtle text-primary':
                                                                                                track.Status_Aktivitas.toLowerCase() ===
                                                                                                'progress',
                                                                                            'bg-secondary-subtle text-secondary':
                                                                                                ![
                                                                                                    'berhasil',
                                                                                                    'gagal',
                                                                                                    'progress',
                                                                                                ].includes(
                                                                                                    track.Status_Aktivitas.toLowerCase()
                                                                                                ),
                                                                                        }"
                                                                                    >
                                                                                        {{
                                                                                            track.Status_Aktivitas
                                                                                        }}
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </a>
                                                                </div>

                                                                <div
                                                                    :id="
                                                                        'collapse' +
                                                                        index
                                                                    "
                                                                    class="accordion-collapse collapse"
                                                                    :aria-labelledby="
                                                                        'heading' +
                                                                        index
                                                                    "
                                                                    data-bs-parent="#accordionTracking"
                                                                >
                                                                    <div
                                                                        class="accordion-body ms-2 ps-5 pt-0"
                                                                    >
                                                                        <p
                                                                            class="mb-1 text-dark"
                                                                        >
                                                                            {{
                                                                                track.Keterangan
                                                                            }}
                                                                        </p>

                                                                        <p
                                                                            class="mb-1 text-muted"
                                                                        >
                                                                            Petugas:
                                                                            <strong
                                                                                >{{
                                                                                    track.Petugas
                                                                                }}</strong
                                                                            >
                                                                        </p>

                                                                        <template
                                                                            v-if="
                                                                                track.Jenis_Aktivitas ===
                                                                                    'Pengambilan Sampel' ||
                                                                                track.Jenis_Aktivitas ===
                                                                                    'Cetak QrCode'
                                                                            "
                                                                        >
                                                                            <p
                                                                                v-if="
                                                                                    track.No_Po
                                                                                "
                                                                                class="mb-1 text-muted"
                                                                            >
                                                                                No
                                                                                PO:
                                                                                <strong
                                                                                    >{{
                                                                                        track.No_Po
                                                                                    }}<template
                                                                                        v-if="
                                                                                            track.No_Split_Po
                                                                                        "
                                                                                        >/{{
                                                                                            track.No_Split_Po
                                                                                        }}</template
                                                                                    ></strong
                                                                                >
                                                                            </p>
                                                                            <p
                                                                                v-if="
                                                                                    track.Nama_Mesin
                                                                                "
                                                                                class="mb-0 text-muted"
                                                                            >
                                                                                Mesin:
                                                                                <strong
                                                                                    >{{
                                                                                        track.Nama_Mesin
                                                                                    }}</strong
                                                                                >
                                                                            </p>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="d-flex justify-content-between align-items-center mt-4"
                                    v-if="filteredData.length > 0"
                                >
                                    <div class="text-muted small">
                                        Menampilkan
                                        {{ (currentPage - 1) * perPage + 1 }} -
                                        {{
                                            Math.min(
                                                currentPage * perPage,
                                                totalData
                                            )
                                        }}
                                        dari {{ totalData }} data
                                    </div>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination pagination-sm">
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled: currentPage === 1,
                                                }"
                                            >
                                                <button
                                                    class="page-link"
                                                    @click="
                                                        changePage(
                                                            currentPage - 1
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="fas fa-chevron-left"
                                                    ></i>
                                                </button>
                                            </li>
                                            <li
                                                class="page-item"
                                                v-for="page in visiblePages"
                                                :key="page"
                                                :class="{
                                                    active:
                                                        currentPage === page,
                                                }"
                                            >
                                                <button
                                                    class="page-link"
                                                    @click="changePage(page)"
                                                >
                                                    {{ page }}
                                                </button>
                                            </li>
                                            <li
                                                class="page-item"
                                                :class="{
                                                    disabled:
                                                        currentPage ===
                                                        totalPages,
                                                }"
                                            >
                                                <button
                                                    class="page-link"
                                                    @click="
                                                        changePage(
                                                            currentPage + 1
                                                        )
                                                    "
                                                >
                                                    <i
                                                        class="fas fa-chevron-right"
                                                    ></i>
                                                </button>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="profile1" role="tabpanel">
                        <VisualisasiQuisy />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import VisualisasiQuisy from "../../components/VisualisasiQuisy.vue";

export default {
    components: {
        DotLottieVue,
        VisualisasiQuisy,
    },
    data() {
        return {
            filteredData: [],
            loading: {
                loadingListData: false,
                cetakUlangQrCode: false,
            },
            searchQuery: "",
            dateFilter: "",
            poFilter: "",
            statusFilter: "",
            machineFilter: "",
            currentPage: 1,
            perPage: 10,
            maxVisiblePages: 5,
            totalPages: 1,
            totalData: 0,
            statusCountsTotal: {},
        };
    },
    computed: {
        paginatedData() {
            const start = (this.currentPage - 1) * this.perPage;
            const end = start + this.perPage;
            return this.filteredData.slice(start, end);
        },
        visiblePages() {
            const range = [];
            const half = Math.floor(this.maxVisiblePages / 2);
            let start = Math.max(this.currentPage - half, 1);
            let end = Math.min(
                start + this.maxVisiblePages - 1,
                this.totalPages
            );

            if (end - start + 1 < this.maxVisiblePages) {
                start = Math.max(end - this.maxVisiblePages + 1, 1);
            }

            for (let i = start; i <= end; i++) {
                range.push(i);
            }

            return range;
        },
        isFilterActive() {
            return (
                this.searchQuery !== "" ||
                this.dateFilter !== "" ||
                this.poFilter !== "" ||
                this.statusFilter !== "" ||
                this.machineFilter !== ""
            );
        },
    },
    methods: {
        formatDate(dateString) {
            const options = { year: "numeric", month: "long", day: "numeric" };
            return new Date(dateString).toLocaleDateString("id-ID", options);
        },
        applyFilters() {
            this.currentPage = 1;
            this.fetchData();
        },
        resetFilters() {
            this.searchQuery = "";
            this.dateFilter = "";
            this.poFilter = "";
            this.statusFilter = "";
            this.machineFilter = "";
            this.applyFilters();
        },
        changePage(page) {
            if (page > 0 && page <= this.totalPages) {
                this.currentPage = page;
                this.fetchData();
            }
        },
        fetchData() {
            this.loading.loadingListData = true;

            axios
                .get("/api/v1/history/registrasi-sampel", {
                    params: {
                        page: this.currentPage,
                        limit: this.perPage,
                        searchQuery: this.searchQuery,
                        dateFilter: this.dateFilter,
                        poFilter: this.poFilter,
                        statusFilter: this.statusFilter,
                        machineFilter: this.machineFilter,
                    },
                })
                .then((response) => {
                    const data = response.data;
                    if (data.success) {
                        const rawData = data.result || [];

                        this.filteredData = rawData.map((item) => ({
                            ...item,
                            id: item.No_Sampel,
                            No_PO: item.No_Po,
                            Split_PO: item.No_Split_Po,
                            Operator: item.Petugas,
                            Status:
                                item.Status === "Y"
                                    ? "Dibatalkan"
                                    : "Terdaftar",
                            Sub_No_PO: Array.isArray(item.sub_no_po)
                                ? item.sub_no_po.map((sub) => sub.No_Po_Multi)
                                : [],
                        }));
                        this.statusCountsTotal = data.status_counts || {};
                        this.totalData = data.total_data || 0;
                        this.totalPages = data.total_page || 1;
                        this.totalData = data.total_data || 0;
                    } else {
                        // Jika API mengembalikan success:false, kosongkan data
                        this.filteredData = [];
                        this.totalData = 0;
                        this.totalPages = 1;
                        console.error("Gagal mengambil data:", data.message);
                    }
                })
                .catch((error) => {
                    this.filteredData = [];
                    this.totalData = 0;
                    this.totalPages = 1;
                    console.error(
                        "Terjadi kesalahan saat mengambil data:",
                        error
                    );
                })
                .finally(() => {
                    this.loading.loadingListData = false;
                });
        },

        async submitCetakUlangQrCode(data) {
            this.loading.cetakUlangQrCode = true;

            try {
                let jumlahPrint = 1;

                // Kalau flag multi qrcode aktif, munculkan input jumlah print
                if (data.Flag_Multi_QrCode === "Y") {
                    const { value: inputJumlah } = await Swal.fire({
                        title: "Jumlah Cetak QR Code",
                        input: "number",
                        inputLabel: "Masukkan jumlah print yang diinginkan",
                        inputAttributes: {
                            min: 1,
                            step: 1,
                        },
                        inputValue: 1,
                        showCancelButton: true,
                        confirmButtonText: "Cetak",
                        cancelButtonText: "Batal",
                        inputValidator: (value) => {
                            if (!value || value <= 0) {
                                return "Jumlah print harus lebih dari 0";
                            }
                        },
                    });

                    if (!inputJumlah) {
                        this.loading.cetakUlangQrCode = false;
                        return; // kalau user batal
                    }

                    jumlahPrint = parseInt(inputJumlah);
                }

                Swal.fire({
                    title: "Mohon tunggu...",
                    text: "Sedang memproses permintaan...",
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });

                const response = await axios.post(
                    `/api/v1/cetak-ulang/qrcode/${data.No_Sampel}/${data.Id_Mesin}`,
                    {
                        Flag_Multi_QrCode: data.Flag_Multi_QrCode, // selalu dikirim
                        Jumlah_Print: jumlahPrint, // default 1 atau input user
                    },
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                Swal.close();
                if (response.status === 200 && response.data) {
                    const printJobs = response.data.print_jobs || [];
                    const printerUrl = response.data.printer_url;

                    for (const job of printJobs) {
                        await axios.post(`${printerUrl}/print`, job);
                    }

                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: response.data.message,
                    });
                } else {
                    throw new Error(
                        response.data.message ||
                            response.data.message?.error ||
                            "Gagal menyimpan data."
                    );
                }
            } catch (error) {
                Swal.close();
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text:
                        error.response?.data?.message ||
                        error.response?.data?.message?.error ||
                        "Terjadi Kesalahan",
                });
            } finally {
                this.loading.cetakUlangQrCode = false;
            }
        },
    },
    mounted() {
        this.fetchData();
    },
};
</script>

<style scoped>
.sample-registration-container {
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
        Oxygen, Ubuntu, Cantarell, sans-serif;
}

.main-card {
    border-radius: 12px;
    overflow: hidden;
    background-color: #ffffff;
    box-shadow: 0 0.125rem 0.375rem rgba(0, 0, 0, 0.05);
}

.section-header {
    padding: 0 1.5rem;
}

.header-content {
    padding-top: 1rem;
}

.main-title {
    font-size: 1.75rem;
    letter-spacing: -0.5px;
    color: #405189;
}

.subtitle {
    font-size: 0.95rem;
    color: #718096;
}

.divider {
    height: 1px;
    background: linear-gradient(
        90deg,
        rgba(226, 232, 240, 0) 0%,
        #e2e8f0 50%,
        rgba(226, 232, 240, 0) 100%
    );
}

.filter-dropdown {
    width: 300px;
}

.accordion-button:not(.collapsed) {
    background-color: transparent;
    box-shadow: none;
}

.accordion-button:focus {
    box-shadow: none;
    border-color: rgba(0, 0, 0, 0.05);
}

.accordion-item {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.accordion-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

/* Custom soft colors */
.bg-primary-soft {
    background-color: rgba(94, 114, 228, 0.1);
}

.bg-success-soft {
    background-color: rgba(72, 187, 120, 0.1);
}

.text-success {
    color: #48bb78 !important;
}

.bg-danger-soft {
    background-color: rgba(245, 101, 101, 0.1);
}

.text-danger {
    color: #f56565 !important;
}

/* Status badges */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
    transition: all 0.2s ease;
}
.badge-mesin {
    background-color: #fff4e5; /* oranye muda */
    color: #d17f00; /* amber */
    border: 1px solid #ffe3b3;
    font-weight: 600;
    font-size: 10px;
    padding: 0.4rem;
    border-radius: 0.75rem;
}

/* Animation for status badges */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(2px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.content-area {
    animation: fadeIn 0.4s ease-out;
}

.spinner-grow {
    width: 2.5rem;
    height: 2.5rem;
    opacity: 0.7;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .main-title {
        font-size: 1.5rem;
    }

    .accordion-button {
        padding: 0.8rem 1rem;
        flex-direction: column;
        align-items: flex-start;
    }

    .accordion-body {
        padding: 1rem;
    }

    .filter-dropdown {
        width: 100%;
    }

    .badge {
        padding: 0.35rem 0.75rem;
    }
}

/* Hover effects */
.btn-outline-primary:hover {
    background-color: rgba(94, 114, 228, 0.05);
}

.btn-outline-secondary:hover {
    background-color: rgba(160, 174, 192, 0.05);
}

/* Focus states */
.btn:focus,
.form-control:focus,
.form-select:focus {
    box-shadow: 0 0 0 3px rgba(94, 114, 228, 0.2);
}

/* Card in accordion body */
.card {
    border-radius: 8px;
}
</style>
