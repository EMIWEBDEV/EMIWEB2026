<template>
    <div class="container-fluid px-0">
        <div class="card shadow-sm border-0 w-100">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Akses Halaman
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Akses Halaman Website Lab PT. Evo Manufacturing
                        Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>
                <div
                    class="d-flex justify-content-center justify-content-lg-start"
                >
                    <button
                        class="btn btn-primary"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#aksesHalamanModal"
                    >
                        + Tambah Akses Halaman
                    </button>
                </div>

                <div
                    class="modal fade"
                    id="aksesHalamanModal"
                    tabindex="-1"
                    aria-labelledby="aksesHalamanModalLabel"
                    aria-hidden="true"
                >
                    <div
                        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
                    >
                        <div class="modal-content">
                            <div class="modal-header bg-light p-3">
                                <h5
                                    class="modal-title"
                                    id="aksesHalamanModalLabel"
                                >
                                    Konfigurasi Akses Halaman
                                </h5>
                                <button
                                    type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"
                                    id="close-modal"
                                ></button>
                            </div>

                            <div class="modal-body bg-light-subtle">
                                <form
                                    @submit.prevent="submitForm"
                                    novalidate
                                    autocomplete="off"
                                >
                                    <div class="row g-4">
                                        <!-- BAGIAN PILIH AKSES HALAMAN -->
                                        <div class="col-12">
                                            <div
                                                class="card shadow-none border mb-0"
                                            >
                                                <div class="card-body">
                                                    <label class="form-label"
                                                        >Akses Halaman</label
                                                    >
                                                    <el-select
                                                        v-model="selectedPages"
                                                        multiple
                                                        filterable
                                                        collapse-tags
                                                        collapse-tags-tooltip
                                                        :placeholder="
                                                            pageAccessUser.length >
                                                            0
                                                                ? '--- Pilih satu atau lebih Akses Halaman ---'
                                                                : 'Data Halaman Kosong'
                                                        "
                                                        no-data-text="Tidak ada data halaman tersedia"
                                                        class="w-100"
                                                        value-key="value"
                                                    >
                                                        <el-option
                                                            v-for="item in pageAccessUser"
                                                            :key="item.value"
                                                            :label="item.name"
                                                            :value="item"
                                                        />
                                                    </el-select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- BAGIAN LOOPING KONFIGURASI PER HALAMAN YANG DIPILIH -->
                                        <div
                                            class="col-12"
                                            v-if="selectedPages.length > 0"
                                        >
                                            <div
                                                v-for="page in selectedPages"
                                                :key="page.value"
                                                class="mb-4"
                                            >
                                                <h5 class="mb-3">
                                                    Konfigurasi:
                                                    <span
                                                        class="text-primary"
                                                        >{{ page.name }}</span
                                                    >
                                                </h5>

                                                <!-- ========================================== -->
                                                <!-- 1. BAGIAN KLASIFIKASI AKSI                 -->
                                                <!-- ========================================== -->
                                                <div
                                                    class="card shadow-none border mb-4"
                                                >
                                                    <!-- HEADER KLASIFIKASI AKSI + CHECK ALL -->
                                                    <div
                                                        class="card-header bg-white border-bottom d-flex justify-content-between align-items-center"
                                                    >
                                                        <h6
                                                            class="card-title mb-0"
                                                        >
                                                            Klasifikasi Aksi
                                                        </h6>
                                                        <div
                                                            class="form-check form-switch mb-0 form-switch-success"
                                                        >
                                                            <input
                                                                class="form-check-input border-success"
                                                                type="checkbox"
                                                                role="switch"
                                                                :id="
                                                                    'check-all-aksi-' +
                                                                    page.value
                                                                "
                                                                :checked="
                                                                    isAllActionsSelected(
                                                                        page.value
                                                                    )
                                                                "
                                                                @change="
                                                                    toggleAllActions(
                                                                        page.value,
                                                                        $event
                                                                            .target
                                                                            .checked
                                                                    )
                                                                "
                                                            />
                                                            <label
                                                                class="form-check-label text-success"
                                                                :for="
                                                                    'check-all-aksi-' +
                                                                    page.value
                                                                "
                                                                style="
                                                                    font-size: 12px;
                                                                    cursor: pointer;
                                                                "
                                                            >
                                                                Pilih Semua
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- BODY KLASIFIKASI AKSI -->
                                                    <div
                                                        class="card-body bg-light-subtle"
                                                    >
                                                        <div class="row g-3">
                                                            <div
                                                                v-for="aksi in klasifikasiAksi"
                                                                :key="
                                                                    aksi.value
                                                                "
                                                                class="col-md-6 col-lg-4"
                                                            >
                                                                <div
                                                                    class="card shadow-none border mb-0 h-100"
                                                                    :class="{
                                                                        'border-primary bg-primary-subtle':
                                                                            pageActionMapping[
                                                                                page
                                                                                    .value
                                                                            ] &&
                                                                            pageActionMapping[
                                                                                page
                                                                                    .value
                                                                            ].includes(
                                                                                aksi.value
                                                                            ),
                                                                    }"
                                                                >
                                                                    <div
                                                                        class="card-body p-3 d-flex justify-content-between align-items-start"
                                                                    >
                                                                        <div
                                                                            class="pe-2"
                                                                        >
                                                                            <h6
                                                                                class="mb-1 text-dark fw-bold text-uppercase"
                                                                            >
                                                                                {{
                                                                                    aksi.name
                                                                                }}
                                                                            </h6>
                                                                            <small
                                                                                class="text-muted d-block"
                                                                                style="
                                                                                    font-size: 11px;
                                                                                    line-height: 1.3;
                                                                                "
                                                                            >
                                                                                {{
                                                                                    getAksiDescription(
                                                                                        aksi.name
                                                                                    )
                                                                                }}
                                                                            </small>
                                                                        </div>
                                                                        <div
                                                                            class="form-check form-switch mb-0 flex-shrink-0 mt-1"
                                                                        >
                                                                            <input
                                                                                class="form-check-input"
                                                                                type="checkbox"
                                                                                role="switch"
                                                                                :value="
                                                                                    aksi.value
                                                                                "
                                                                                v-model="
                                                                                    pageActionMapping[
                                                                                        page
                                                                                            .value
                                                                                    ]
                                                                                "
                                                                            />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- ========================================== -->
                                                <!-- 2. BAGIAN MANAJEMEN AKSES TES (ANALISA)    -->
                                                <!-- ========================================== -->
                                                <div
                                                    v-if="
                                                        isAnalisaPage(page.name)
                                                    "
                                                    class="card shadow-none border border-success-subtle mb-0"
                                                >
                                                    <!-- HEADER MANAJEMEN AKSES TES + CHECK ALL -->
                                                    <div
                                                        class="card-header bg-success-subtle border-bottom border-success-subtle d-flex justify-content-between align-items-center"
                                                    >
                                                        <div
                                                            class="d-flex align-items-center"
                                                        >
                                                            <i
                                                                class="ri-flask-fill text-success fs-18 me-2"
                                                            ></i>
                                                            <h6
                                                                class="card-title text-success mb-0"
                                                            >
                                                                Manajemen Akses
                                                                Tes (Jenis
                                                                Analisa)
                                                            </h6>
                                                        </div>
                                                        <div
                                                            class="form-check form-switch mb-0 form-switch-success"
                                                        >
                                                            <input
                                                                class="form-check-input border-success"
                                                                type="checkbox"
                                                                role="switch"
                                                                :id="
                                                                    'check-all-analisa-' +
                                                                    page.value
                                                                "
                                                                :checked="
                                                                    isAllAnalisaSelected(
                                                                        page.value,
                                                                        page.name
                                                                    )
                                                                "
                                                                @change="
                                                                    toggleAllAnalisa(
                                                                        page.value,
                                                                        $event
                                                                            .target
                                                                            .checked,
                                                                        page.name
                                                                    )
                                                                "
                                                            />
                                                            <label
                                                                class="form-check-label text-success"
                                                                :for="
                                                                    'check-all-analisa-' +
                                                                    page.value
                                                                "
                                                                style="
                                                                    font-size: 12px;
                                                                    cursor: pointer;
                                                                "
                                                            >
                                                                Pilih Semua
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- BODY MANAJEMEN AKSES TES -->
                                                    <div class="card-body">
                                                        <div class="row g-3">
                                                            <!-- Looping menggunakan filteredJenisAnalisa(page.name) -->
                                                            <div
                                                                v-for="item in filteredJenisAnalisa(
                                                                    page.name
                                                                )"
                                                                :key="
                                                                    item.value
                                                                "
                                                                class="col-md-6 col-lg-4"
                                                            >
                                                                <div
                                                                    class="card shadow-none border mb-0 h-100"
                                                                    :class="{
                                                                        'border-success bg-success-subtle':
                                                                            pageAnalisaMapping[
                                                                                page
                                                                                    .value
                                                                            ] &&
                                                                            pageAnalisaMapping[
                                                                                page
                                                                                    .value
                                                                            ].includes(
                                                                                item.value
                                                                            ),
                                                                    }"
                                                                >
                                                                    <div
                                                                        class="card-body p-3 d-flex justify-content-between align-items-center"
                                                                    >
                                                                        <div
                                                                            class="d-flex align-items-center pe-2"
                                                                        >
                                                                            <i
                                                                                class="ri-checkbox-circle-fill fs-20 me-2"
                                                                                :class="
                                                                                    pageAnalisaMapping[
                                                                                        page
                                                                                            .value
                                                                                    ] &&
                                                                                    pageAnalisaMapping[
                                                                                        page
                                                                                            .value
                                                                                    ].includes(
                                                                                        item.value
                                                                                    )
                                                                                        ? 'text-success'
                                                                                        : 'text-muted'
                                                                                "
                                                                            ></i>
                                                                            <div>
                                                                                <h6
                                                                                    class="mb-0 text-dark fw-bold"
                                                                                    style="
                                                                                        font-size: 13px;
                                                                                    "
                                                                                >
                                                                                    {{
                                                                                        item.name
                                                                                    }}
                                                                                </h6>
                                                                                <small
                                                                                    class="text-muted"
                                                                                    style="
                                                                                        font-size: 11px;
                                                                                    "
                                                                                >
                                                                                    Akses
                                                                                    Spesifik
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                        <div
                                                                            class="form-check form-switch mb-0 form-switch-success flex-shrink-0"
                                                                        >
                                                                            <input
                                                                                class="form-check-input"
                                                                                type="checkbox"
                                                                                role="switch"
                                                                                :value="
                                                                                    item.value
                                                                                "
                                                                                v-model="
                                                                                    pageAnalisaMapping[
                                                                                        page
                                                                                            .value
                                                                                    ]
                                                                                "
                                                                            />
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- SELESAI MANAJEMEN AKSES TES -->
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <div class="modal-footer bg-light">
                                <button
                                    type="button"
                                    class="btn btn-light border"
                                    data-bs-dismiss="modal"
                                >
                                    Tutup
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    @click="submitForm"
                                    :disabled="loading.menuSaveToDatabase"
                                >
                                    <span
                                        v-if="loading.menuSaveToDatabase"
                                        class="spinner-border spinner-border-sm me-1"
                                        role="status"
                                        aria-hidden="true"
                                    ></span>
                                    Simpan Konfigurasi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <ListSkeleton
                        :page="5"
                        v-if="loading.loadinMenuCurrentList"
                    />
                    <div class="accordion-wrapper" v-else>
                        <div
                            class="accordion"
                            id="accordionRoleAccess"
                            v-if="listData.length"
                        >
                            <div
                                class="accordion-item shadow-sm mb-3 border rounded"
                                v-for="(user, index) in listData"
                                :key="user.Id_User"
                            >
                                <h2
                                    class="accordion-header"
                                    :id="'heading' + index"
                                >
                                    <button
                                        class="accordion-button collapsed bg-white rounded"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        :data-bs-target="'#collapse' + index"
                                    >
                                        <i
                                            class="fas fa-user text-primary me-3 fs-5"
                                        ></i>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                {{ user.Nama }}
                                            </div>
                                            <small class="text-muted"
                                                ><i class="ri-user-line"></i>
                                                {{ user.Username }}</small
                                            >
                                        </div>
                                    </button>
                                </h2>

                                <div
                                    :id="'collapse' + index"
                                    class="accordion-collapse collapse"
                                    data-bs-parent="#accordionRoleAccess"
                                >
                                    <div
                                        class="accordion-body bg-light-subtle p-4"
                                    >
                                        <div
                                            v-for="page in user.Pages"
                                            :key="page.Id_Page_Access"
                                            class="card shadow-none border mb-4"
                                        >
                                            <div
                                                class="card-header bg-white border-bottom"
                                            >
                                                <h6
                                                    class="mb-0 fw-bold text-primary"
                                                >
                                                    <i
                                                        class="ri-pages-line me-1"
                                                    ></i>
                                                    {{ page.Nama_Menu }}
                                                </h6>
                                            </div>

                                            <div
                                                class="card-body"
                                                style="overflow-x: auto"
                                            >
                                                <div style="min-width: 600px">
                                                    <div class="mb-4">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-3"
                                                        >
                                                            <h6
                                                                class="mb-0 fw-bold text-dark fs-13"
                                                            >
                                                                Klasifikasi Aksi
                                                            </h6>
                                                            <div
                                                                class="form-check form-switch mb-0 d-flex align-items-center"
                                                            >
                                                                <input
                                                                    class="form-check-input me-2"
                                                                    type="checkbox"
                                                                    role="switch"
                                                                    :checked="
                                                                        isAllGlobalAksiChecked(
                                                                            page.Akses
                                                                        )
                                                                    "
                                                                    @change="
                                                                        toggleAllGlobalAksi(
                                                                            user.Id_User,
                                                                            page.Id_Page_Access,
                                                                            page,
                                                                            $event
                                                                                .target
                                                                                .checked
                                                                        )
                                                                    "
                                                                />
                                                                <label
                                                                    class="form-check-label text-muted"
                                                                    style="
                                                                        font-size: 12px;
                                                                        cursor: pointer;
                                                                    "
                                                                    >Pilih
                                                                    Semua</label
                                                                >
                                                            </div>
                                                        </div>
                                                        <div class="row g-3">
                                                            <div
                                                                class="col-4"
                                                                v-for="globalAksi in klasifikasiAksi"
                                                                :key="
                                                                    globalAksi.value
                                                                "
                                                            >
                                                                <div
                                                                    class="p-3 border rounded d-flex justify-content-between align-items-center bg-white"
                                                                    :class="{
                                                                        'border-primary bg-primary-subtle':
                                                                            checkAksiAccess(
                                                                                page.Akses,
                                                                                globalAksi.value
                                                                            ),
                                                                    }"
                                                                >
                                                                    <div>
                                                                        <div
                                                                            class="fw-bold text-uppercase text-dark"
                                                                            style="
                                                                                font-size: 14px;
                                                                            "
                                                                        >
                                                                            {{
                                                                                globalAksi.name
                                                                            }}
                                                                        </div>
                                                                        <div
                                                                            class="text-muted mt-1"
                                                                            style="
                                                                                font-size: 12px;
                                                                                white-space: normal;
                                                                            "
                                                                        >
                                                                            {{
                                                                                getAksiDescription(
                                                                                    globalAksi.name
                                                                                )
                                                                            }}
                                                                        </div>
                                                                    </div>
                                                                    <div
                                                                        class="form-check form-switch mb-0"
                                                                        style="
                                                                            font-size: 1.25rem;
                                                                        "
                                                                    >
                                                                        <input
                                                                            class="form-check-input"
                                                                            type="checkbox"
                                                                            role="switch"
                                                                            :checked="
                                                                                checkAksiAccess(
                                                                                    page.Akses,
                                                                                    globalAksi.value
                                                                                )
                                                                            "
                                                                            @change="
                                                                                toggleSingleAccessGlobal(
                                                                                    user.Id_User,
                                                                                    page.Id_Page_Access,
                                                                                    globalAksi.value,
                                                                                    $event
                                                                                        .target
                                                                                        .checked,
                                                                                    'aksi',
                                                                                    page
                                                                                )
                                                                            "
                                                                        />
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        v-if="
                                                            isAnalisaPage(
                                                                page.Nama_Menu
                                                            )
                                                        "
                                                    >
                                                        <hr
                                                            class="border-secondary-subtle mb-4"
                                                        />
                                                        <div
                                                            class="d-flex justify-content-between align-items-center mb-3"
                                                        >
                                                            <h6
                                                                class="mb-0 fw-bold text-success fs-13"
                                                            >
                                                                <i
                                                                    class="ri-flask-fill me-1"
                                                                ></i>
                                                                Manajemen Akses
                                                                Tes
                                                            </h6>
                                                            <div
                                                                class="form-check form-switch form-switch-success mb-0 d-flex align-items-center"
                                                            >
                                                                <input
                                                                    class="form-check-input border-success me-2"
                                                                    type="checkbox"
                                                                    role="switch"
                                                                    :checked="
                                                                        isAllGlobalAnalisaChecked(
                                                                            page.Analisa
                                                                        )
                                                                    "
                                                                    @change="
                                                                        toggleAllGlobalAnalisa(
                                                                            user.Id_User,
                                                                            page.Id_Page_Access,
                                                                            page,
                                                                            $event
                                                                                .target
                                                                                .checked
                                                                        )
                                                                    "
                                                                />
                                                                <label
                                                                    class="form-check-label text-success"
                                                                    style="
                                                                        font-size: 12px;
                                                                        cursor: pointer;
                                                                    "
                                                                    >Pilih
                                                                    Semua</label
                                                                >
                                                            </div>
                                                        </div>
                                                        <div class="row g-3">
                                                            <div
                                                                class="col-4"
                                                                v-for="globalAnalisa in jenisAnalisa"
                                                                :key="
                                                                    globalAnalisa.value
                                                                "
                                                            >
                                                                <div
                                                                    class="p-3 border rounded d-flex justify-content-between align-items-center bg-white"
                                                                    :class="{
                                                                        'border-success bg-success-subtle':
                                                                            checkAnalisaAccess(
                                                                                page.Analisa,
                                                                                globalAnalisa.value
                                                                            ),
                                                                    }"
                                                                >
                                                                    <div>
                                                                        <div
                                                                            class="fw-bold text-dark"
                                                                            style="
                                                                                font-size: 14px;
                                                                            "
                                                                        >
                                                                            {{
                                                                                globalAnalisa.name
                                                                            }}
                                                                        </div>
                                                                    </div>
                                                                    <div
                                                                        class="form-check form-switch form-switch-success mb-0"
                                                                        style="
                                                                            font-size: 1.25rem;
                                                                        "
                                                                    >
                                                                        <input
                                                                            class="form-check-input border-success"
                                                                            type="checkbox"
                                                                            role="switch"
                                                                            :checked="
                                                                                checkAnalisaAccess(
                                                                                    page.Analisa,
                                                                                    globalAnalisa.value
                                                                                )
                                                                            "
                                                                            @change="
                                                                                toggleSingleAccessGlobal(
                                                                                    user.Id_User,
                                                                                    page.Id_Page_Access,
                                                                                    globalAnalisa.value,
                                                                                    $event
                                                                                        .target
                                                                                        .checked,
                                                                                    'analisa',
                                                                                    page
                                                                                )
                                                                            "
                                                                        />
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
                            </div>
                        </div>

                        <div
                            v-if="
                                !listData.length &&
                                !loading.loadinMenuCurrentList
                            "
                            class="d-flex justify-content-center"
                        >
                            <div class="flex-column align-content-center py-5">
                                <DotLottieVue
                                    style="height: 200px; width: 200px"
                                    autoplay
                                    loop
                                    src="/animation/empty.lottie"
                                />
                                <p
                                    class="text-center mt-3 text-muted fw-medium"
                                >
                                    Data Tidak Ditemukan!
                                </p>
                            </div>
                        </div>

                        <div
                            class="d-flex justify-content-between align-items-center mt-4"
                            v-if="listData.length && pagination.totalPage > 1"
                        >
                            <small class="text-muted"
                                >Total Data: {{ pagination.totalData }}</small
                            >
                            <ul class="pagination pagination-sm mb-0">
                                <li
                                    class="page-item"
                                    :class="{
                                        disabled: pagination.currentPage === 1,
                                    }"
                                >
                                    <button
                                        class="page-link"
                                        @click="
                                            changePage(
                                                pagination.currentPage - 1
                                            )
                                        "
                                    >
                                        Prev
                                    </button>
                                </li>
                                <li
                                    class="page-item"
                                    v-for="p in pagination.totalPage"
                                    :key="p"
                                    :class="{
                                        active: pagination.currentPage === p,
                                    }"
                                >
                                    <button
                                        class="page-link"
                                        @click="changePage(p)"
                                    >
                                        {{ p }}
                                    </button>
                                </li>
                                <li
                                    class="page-item"
                                    :class="{
                                        disabled:
                                            pagination.currentPage ===
                                            pagination.totalPage,
                                    }"
                                >
                                    <button
                                        class="page-link"
                                        @click="
                                            changePage(
                                                pagination.currentPage + 1
                                            )
                                        "
                                    >
                                        Next
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import ListSkeleton from "@/pages/vue/ui/ListSkeleton.vue";
import Swal from "sweetalert2";
import vSelect from "vue-select";
import { ElSelect, ElOption } from "element-plus";
import "element-plus/dist/index.css";

export default {
    components: {
        ListSkeleton,
        DotLottieVue,
        vSelect,
        ElSelect,
        ElOption,
    },
    data() {
        return {
            pageActionMapping: {},
            pageAnalisaMapping: {},
            klasifikasiAksi: [],
            jenisAnalisa: [],
            pageAccessUser: [],
            listData: [],
            selectedPages: [],
            loading: {
                loadinMenuCurrentList: false,
                menuSaveToDatabase: false,
            },
            errors: {},
            pagination: {
                currentPage: 1,
                totalPage: 1,
                totalData: 0,
                limit: 10,
            },
        };
    },
    // --- DITAMBAHKAN: Watcher agar v-model checkbox mengenali array sejak awal ---
    watch: {
        selectedPages: {
            handler(pages) {
                const newActionMapping = { ...this.pageActionMapping };
                const newAnalisaMapping = { ...this.pageAnalisaMapping };

                pages.forEach((page) => {
                    // Jika belum ada, inisialisasi sebagai array kosong
                    if (!newActionMapping[page.value]) {
                        newActionMapping[page.value] = [];
                    }
                    if (!newAnalisaMapping[page.value]) {
                        newAnalisaMapping[page.value] = [];
                    }
                });

                this.pageActionMapping = newActionMapping;
                this.pageAnalisaMapping = newAnalisaMapping;
            },
            deep: true,
            immediate: true,
        },
    },
    methods: {
        getRoleByMenu(pageName) {
            if (!pageName) return null;
            const lowerPageName = pageName.toLowerCase();

            if (
                lowerPageName.includes("validasi hasil analisa") ||
                lowerPageName.includes("validasi trial produksi") ||
                lowerPageName.includes("finalisasi trial produksi") ||
                lowerPageName.includes("finalisasi sampel") ||
                lowerPageName.includes("hasil analisa")
            ) {
                return "LAB";
            }
            if (
                lowerPageName.includes("validasi hasil trial") ||
                lowerPageName.includes("finalisasi trial") ||
                lowerPageName.includes("hasil trial")
            ) {
                return "FLM";
            }
            return null;
        },
        filteredJenisAnalisa(pageName) {
            const role = this.getRoleByMenu(pageName);
            if (!role) return []; // Jika bukan menu analisa, kembalikan kosong
            return this.jenisAnalisa.filter((item) => item.role === role);
        },
        async submitForm() {
            this.errors = {};
            this.loading.menuSaveToDatabase = true;

            try {
                // --- STRUKTUR PAYLOAD BARU ---
                let accessRules = [];

                this.selectedPages.forEach((page) => {
                    const pageId = page.value;

                    const actions = this.pageActionMapping[pageId] || [];
                    actions.forEach((actionId) => {
                        accessRules.push({
                            ID_Page_Access: pageId,
                            Flag_Diizinkan: "Y",
                            ID_Klasifikasi_Actions: actionId,
                            Id_Jenis_Indikator: null,
                            Id_Jenis_Soal: null,
                            Kategori: null,
                        });
                    });

                    if (this.isAnalisaPage(page.name)) {
                        const analisas = this.pageAnalisaMapping[pageId] || [];

                        if (analisas.length > 0) {
                            analisas.forEach((analisaId) => {
                                accessRules.push({
                                    ID_Page_Access: pageId,
                                    Flag_Diizinkan: "Y",
                                    ID_Klasifikasi_Actions: null,
                                    Id_Jenis_Indikator: analisaId,
                                    Id_Jenis_Soal: null,
                                    Kategori: null,
                                });
                            });
                        } else {
                            accessRules.push({
                                ID_Page_Access: pageId,
                                Flag_Diizinkan: "Y",
                                ID_Klasifikasi_Actions: null,
                                Id_Jenis_Indikator: null,
                                Id_Jenis_Soal: null,
                                Kategori: null,
                            });
                        }
                    }
                });

                const payload = { access_rules: accessRules };

                const response = await axios.post(
                    "/api/v1/management-role-akses/store",
                    payload, // Kirim payload yang sudah disesuaikan
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (response.status !== 201 && !response.data.success) {
                    throw new Error(
                        response.data.message || "Gagal menyimpan data"
                    );
                }

                const modalElement = document.getElementById("close-modal");
                if (modalElement) modalElement.click();

                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text:
                        response.data.message ||
                        "Akses halaman berhasil disimpan",
                    timer: 1500,
                    showConfirmButton: false,
                }).then(() => {
                    window.location.reload();
                });
            } catch (error) {
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.errors
                ) {
                    this.errors = error.response.data.errors;
                }
                Swal.fire(
                    "Error",
                    error.message || "Terjadi kesalahan sistem",
                    "error"
                );
            } finally {
                this.loading.menuSaveToDatabase = false;
            }
        },

        // --- DIPERBAIKI: Gunakan spread operator agar Vue re-render checkbox ---
        toggleAllAnalisa(pageId, isChecked, pageName) {
            const newMapping = { ...this.pageAnalisaMapping };
            if (isChecked) {
                const availableItems = this.filteredJenisAnalisa(pageName);
                newMapping[pageId] = availableItems.map(
                    (analisa) => analisa.value
                );
            } else {
                newMapping[pageId] = [];
            }
            this.pageAnalisaMapping = newMapping;
        },

        // --- DIPERBAIKI: Gunakan spread operator agar Vue re-render checkbox ---
        toggleAllActions(pageId, isChecked) {
            const newMapping = { ...this.pageActionMapping };

            if (isChecked) {
                newMapping[pageId] = this.klasifikasiAksi.map(
                    (aksi) => aksi.value
                );
            } else {
                newMapping[pageId] = [];
            }

            this.pageActionMapping = newMapping;
        },

        isAllActionsSelected(pageId) {
            const mappedActions = this.pageActionMapping[pageId] || [];
            return (
                mappedActions.length === this.klasifikasiAksi.length &&
                this.klasifikasiAksi.length > 0
            );
        },

        isAllAnalisaSelected(pageId, pageName) {
            const mappedAnalisa = this.pageAnalisaMapping[pageId] || [];
            const availableItems = this.filteredJenisAnalisa(pageName);
            return (
                mappedAnalisa.length === availableItems.length &&
                availableItems.length > 0
            );
        },

        async fetchCurrentData(page = 1) {
            this.loading.loadinMenuCurrentList = true;
            try {
                const response = await axios.get(
                    `/api/v1/management-role-akses/current?page=${page}&limit=${this.pagination.limit}`
                );
                if (response.status === 200 && response.data.success) {
                    this.listData = response.data.result;
                    this.pagination.currentPage = response.data.page;
                    this.pagination.totalPage = response.data.total_page;
                    this.pagination.totalData = response.data.total_data;
                }
            } catch (error) {
                this.listData = [];
            } finally {
                this.loading.loadinMenuCurrentList = false;
            }
        },

        changePage(page) {
            if (page >= 1 && page <= this.pagination.totalPage) {
                this.fetchCurrentData(page);
            }
        },

        checkAksiAccess(pageAksesList, idAksiGlobal) {
            if (!pageAksesList) return false;
            const found = pageAksesList.find((a) => a.Id_Aksi === idAksiGlobal);
            return found ? found.Flag_Diizinkan === "Y" : false;
        },

        checkAnalisaAccess(pageAnalisaList, idAnalisaGlobal) {
            if (!pageAnalisaList) return false;
            const found = pageAnalisaList.find(
                (a) => a.Id_Jenis_Analisa === idAnalisaGlobal
            );
            return found ? found.Flag_Diizinkan === "Y" : false;
        },

        isAllGlobalAksiChecked(pageAksesList) {
            if (!this.klasifikasiAksi || this.klasifikasiAksi.length === 0)
                return false;
            return this.klasifikasiAksi.every((globalAksi) =>
                this.checkAksiAccess(pageAksesList, globalAksi.value)
            );
        },

        isAllGlobalAnalisaChecked(pageAnalisaList, pageName) {
            const availableItems = this.filteredJenisAnalisa(pageName);
            if (!availableItems || availableItems.length === 0) return false;

            return availableItems.every((globalAnalisa) =>
                this.checkAnalisaAccess(pageAnalisaList, globalAnalisa.value)
            );
        },

        async toggleSingleAccessGlobal(
            userId,
            pageId,
            itemId,
            isChecked,
            type,
            pageContext,
            isBulk = false
        ) {
            const flag = isChecked ? "Y" : "T";
            const endpoint =
                type === "aksi"
                    ? "/api/v1/management-role-akses/toggle"
                    : "/api/v1/management-role-akses/toggle-content";

            let payload = {};

            if (type === "aksi") {
                if (!pageContext.Akses) pageContext.Akses = [];
                let existing = pageContext.Akses.find(
                    (a) => a.Id_Aksi === itemId
                );
                if (existing) {
                    existing.Flag_Diizinkan = flag;
                } else {
                    pageContext.Akses.push({
                        Id_Aksi: itemId,
                        Flag_Diizinkan: flag,
                    });
                }

                payload = {
                    Id_Page_Access: pageId,
                    Id_Aksi: itemId,
                    is_active: isChecked,
                };
            } else {
                if (!pageContext.Analisa) pageContext.Analisa = [];
                let existing = pageContext.Analisa.find(
                    (a) => a.Id_Jenis_Analisa === itemId
                );
                if (existing) {
                    existing.Flag_Diizinkan = flag;
                } else {
                    pageContext.Analisa.push({
                        Id_Jenis_Analisa: itemId,
                        Flag_Diizinkan: flag,
                    });
                }

                payload = {
                    Id_Page_Access: pageId,
                    Id_Jenis_Analisa: itemId,
                    is_active: isChecked,
                };
            }

            try {
                await axios.post(endpoint, payload, {
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                    },
                });
            } catch (error) {
                if (type === "aksi") {
                    let existing = pageContext.Akses.find(
                        (a) => a.Id_Aksi === itemId
                    );
                    if (existing)
                        existing.Flag_Diizinkan = isChecked ? "T" : "Y";
                } else {
                    let existing = pageContext.Analisa.find(
                        (a) => a.Id_Jenis_Analisa === itemId
                    );
                    if (existing)
                        existing.Flag_Diizinkan = isChecked ? "T" : "Y";
                }

                let errorMsg = "Terjadi kesalahan saat mengubah akses";
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.message
                ) {
                    errorMsg = error.response.data.message;
                }

                if (isBulk) {
                    throw new Error(errorMsg);
                } else {
                    Swal.fire("Gagal", errorMsg, "error");
                }
            }
        },

        async toggleAllGlobalAksi(userId, pageId, pageContext, isChecked) {
            Swal.fire({
                title: "Memproses Akses...",
                text: "Mohon tunggu sebentar.",
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                for (let globalAksi of this.klasifikasiAksi) {
                    if (
                        this.checkAksiAccess(
                            pageContext.Akses,
                            globalAksi.value
                        ) !== isChecked
                    ) {
                        await this.toggleSingleAccessGlobal(
                            userId,
                            pageId,
                            globalAksi.value,
                            isChecked,
                            "aksi",
                            pageContext,
                            true
                        );
                    }
                }
                Swal.close();
            } catch (error) {
                Swal.fire(
                    "Gagal",
                    "Proses terhenti. " + error.message,
                    "error"
                );
            }
        },

        async toggleAllGlobalAnalisa(
            userId,
            pageId,
            pageContext,
            isChecked,
            pageName
        ) {
            Swal.fire({
                title: "Memproses Akses Tes...",
                text: "Mohon tunggu sebentar.",
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                const availableItems = this.filteredJenisAnalisa(pageName);
                for (let globalAnalisa of availableItems) {
                    if (
                        this.checkAnalisaAccess(
                            pageContext.Analisa,
                            globalAnalisa.value
                        ) !== isChecked
                    ) {
                        await this.toggleSingleAccessGlobal(
                            userId,
                            pageId,
                            globalAnalisa.value,
                            isChecked,
                            "analisa",
                            pageContext,
                            true
                        );
                    }
                }
                Swal.close();
            } catch (error) {
                Swal.fire(
                    "Gagal",
                    "Proses terhenti. " + error.message,
                    "error"
                );
            }
        },
        getAksiDescription(namaAksi) {
            const label = namaAksi ? namaAksi.toUpperCase() : "";
            if (label.includes("CREATE") || label.includes("TAMBAH"))
                return "Memberikan hak akses untuk menambah data baru pada halaman ini.";
            if (
                label.includes("VIEW") ||
                label.includes("READ") ||
                label.includes("LIHAT")
            )
                return "Memberikan hak akses untuk melihat daftar atau informasi data.";
            if (
                label.includes("EDIT") ||
                label.includes("UPDATE") ||
                label.includes("UBAH")
            )
                return "Memberikan hak akses untuk mengubah data yang sudah ada.";
            if (label.includes("DELETE") || label.includes("HAPUS"))
                return "Memberikan hak akses untuk menghapus data.";
            if (
                label.includes("PRINT") ||
                label.includes("CETAK") ||
                label.includes("DOWNLOAD")
            )
                return "Memberikan hak akses untuk mencetak atau mengunduh laporan data.";
            if (label.includes("DETAIL") || label.includes("RINCIAN"))
                return "Memberikan hak akses untuk melihat rincian informasi spesifik data.";
            if (label.includes("FINALISASI") || label.includes("SELESAI"))
                return "Memberikan hak akses untuk menyelesaikan dan mengunci seluruh tahapan proses/uji.";
            if (label.includes("VALIDASI"))
                return "Memberikan hak akses untuk memvalidasi kelengkapan atau kebenaran data.";
            return "Memberikan hak akses fungsionalitas sistem terkait.";
        },
        isAnalisaPage(pageName) {
            if (!pageName) return false;
            const targetMenus = [
                "validasi hasil analisa",
                "validasi trial produksi",
                "finalisasi trial produksi",
                "finalisasi sampel",
                "hasil analisa",
                "validasi hasil trial",
                "finalisasi trial",
                "hasil trial",
            ];
            const lowerPageName = pageName.toLowerCase();
            return targetMenus.some((menu) => lowerPageName.includes(menu));
        },

        async fetchKlasifikasiAksi() {
            try {
                const response = await axios.get(
                    "/api/v1/management-role-akses/options/klasifikasi"
                );
                if (response.status === 200 && response.data?.result) {
                    this.klasifikasiAksi = response.data.result.map((item) => ({
                        value: item.Id_Klasifikasi_Actions,
                        name: `${item.Nama_Aksi}`,
                    }));
                } else {
                    this.klasifikasiAksi = [];
                }
            } catch (error) {
                this.klasifikasiAksi = [];
            }
        },

        async fetchJenisAnalisa() {
            try {
                const response = await axios.get(
                    "/api/v1/management-role-akses/options/jenis-analisa"
                );
                if (response.status === 200 && response.data?.result) {
                    this.jenisAnalisa = response.data.result.map((item) => ({
                        value: item.id,
                        name: item.Jenis_Analisa,
                        role: item.Kode_Role, // <-- SIMPAN Kode_Role DARI DB
                    }));
                } else {
                    this.jenisAnalisa = [];
                }
            } catch (error) {
                this.jenisAnalisa = [];
            }
        },

        async fetchPagesAccess() {
            try {
                const response = await axios.get(
                    "/api/v1/management-role-akses/options/pageaccess"
                );
                if (response.status === 200 && response.data?.result) {
                    this.pageAccessUser = response.data.result.map((item) => ({
                        value: item.Id_Page_Access,
                        name: `${item.Nama_Menu}-${item.Nama}`,
                    }));
                } else {
                    this.pageAccessUser = [];
                }
            } catch (error) {
                this.pageAccessUser = [];
            }
        },
    },
    mounted() {
        this.fetchKlasifikasiAksi();
        this.fetchJenisAnalisa();
        this.fetchPagesAccess();
        this.fetchCurrentData();
    },
};
</script>
