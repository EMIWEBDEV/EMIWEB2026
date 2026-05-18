<template>
    <div class="container-fluid">
        <!-- ─── Stats Cards ─── -->
        <div class="row g-3 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="card card-animate border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p
                                    class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"
                                >
                                    Total Menu
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <span
                                    class="badge bg-primary-subtle text-primary fs-12"
                                >
                                    <i class="ri-bar-chart-fill me-1"></i>All
                                </span>
                            </div>
                        </div>
                        <div
                            class="d-flex align-items-end justify-content-between mt-4"
                        >
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-3">
                                    <span v-if="loadingStats">
                                        <span
                                            class="placeholder placeholder-wave col-3 bg-primary opacity-25 rounded"
                                        ></span>
                                    </span>
                                    <span v-else class="counter-value">{{
                                        stats.total
                                    }}</span>
                                </h4>
                                <span
                                    class="badge bg-primary-subtle text-primary rounded-pill"
                                >
                                    Menu Terdaftar
                                </span>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span
                                    class="avatar-title bg-primary-subtle rounded fs-3"
                                >
                                    <i
                                        class="ri-list-settings-line text-primary"
                                    ></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card card-animate border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p
                                    class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"
                                >
                                    Menu Aktif
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <span
                                    class="badge bg-success-subtle text-success fs-12"
                                >
                                    <i class="ri-checkbox-circle-line me-1"></i
                                    >URL Tersedia
                                </span>
                            </div>
                        </div>
                        <div
                            class="d-flex align-items-end justify-content-between mt-4"
                        >
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-3">
                                    <span v-if="loadingStats">
                                        <span
                                            class="placeholder placeholder-wave col-3 bg-success opacity-25 rounded"
                                        ></span>
                                    </span>
                                    <span v-else class="counter-value">{{
                                        stats.withUrl
                                    }}</span>
                                </h4>
                                <span
                                    class="badge bg-success-subtle text-success rounded-pill"
                                >
                                    Siap Digunakan
                                </span>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span
                                    class="avatar-title bg-success-subtle rounded fs-3"
                                >
                                    <i class="ri-link text-success"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="card card-animate border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1 overflow-hidden">
                                <p
                                    class="text-uppercase fw-medium text-muted text-truncate mb-0 fs-12"
                                >
                                    Draft / Tanpa URL
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <span
                                    class="badge bg-warning-subtle text-warning fs-12"
                                >
                                    <i class="ri-error-warning-line me-1"></i
                                    >Perlu Konfigurasi
                                </span>
                            </div>
                        </div>
                        <div
                            class="d-flex align-items-end justify-content-between mt-4"
                        >
                            <div>
                                <h4 class="fs-22 fw-semibold ff-secondary mb-3">
                                    <span v-if="loadingStats">
                                        <span
                                            class="placeholder placeholder-wave col-3 bg-warning opacity-25 rounded"
                                        ></span>
                                    </span>
                                    <span v-else class="counter-value">{{
                                        stats.withoutUrl
                                    }}</span>
                                </h4>
                                <span
                                    class="badge bg-warning-subtle text-warning rounded-pill"
                                >
                                    Belum Aktif
                                </span>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                                <span
                                    class="avatar-title bg-warning-subtle rounded fs-3"
                                >
                                    <i
                                        class="ri-error-warning-line text-warning"
                                    ></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Main Table Card ─── -->
        <div class="card border-0 shadow-sm">
            <!-- Card Header / Toolbar -->
            <div class="card-header bg-white border-bottom py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-sm">
                                <span
                                    class="avatar-title bg-primary-subtle rounded fs-4"
                                >
                                    <i class="ri-menu-2-line text-primary"></i>
                                </span>
                            </span>
                            <div>
                                <h5 class="card-title mb-0 fw-semibold">
                                    Daftar Menu Website
                                </h5>
                                <small class="text-muted"
                                    >Konfigurasi navigasi sistem LIMS</small
                                >
                            </div>
                        </div>
                    </div>
                    <div class="col-sm ms-auto">
                        <div
                            class="d-flex gap-2 justify-content-sm-end flex-wrap"
                        >
                            <div class="search-box">
                                <input
                                    type="search"
                                    class="form-control form-control-sm search"
                                    placeholder="Cari nama menu..."
                                    v-model="searchQuery"
                                    style="min-width: 200px"
                                />
                                <i class="ri-search-line search-icon"></i>
                            </div>
                            <select
                                class="form-select form-select-sm"
                                v-model="pagination.limit"
                                @change="onLimitChange"
                                style="width: auto"
                            >
                                <option :value="10">10 / hal</option>
                                <option :value="25">25 / hal</option>
                                <option :value="50">50 / hal</option>
                            </select>
                            <button
                                class="btn btn-primary btn-sm d-flex align-items-center gap-1"
                                type="button"
                                @click="openAddForm"
                            >
                                <i class="ri-add-line"></i>
                                <span>Tambah Menu</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Body -->
            <div class="card-body p-0">
                <!-- Skeleton Loading -->
                <div v-if="loading.menuLoading" class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" width="50">No</th>
                                <th width="64">Icon</th>
                                <th>Nama Menu</th>
                                <th>URL Route</th>
                                <th>Grup</th>
                                <th width="90">Status</th>
                                <th class="text-center" width="100">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="n in pagination.limit" :key="n">
                                <td class="ps-4">
                                    <div
                                        class="skeleton-cell"
                                        style="width: 28px"
                                    ></div>
                                </td>
                                <td>
                                    <div
                                        class="skeleton-cell rounded-2"
                                        style="width: 36px; height: 36px"
                                    ></div>
                                </td>
                                <td>
                                    <div
                                        class="skeleton-cell mb-1"
                                        style="width: 55%"
                                    ></div>
                                    <div
                                        class="skeleton-cell"
                                        style="width: 30%; height: 10px"
                                    ></div>
                                </td>
                                <td>
                                    <div
                                        class="skeleton-cell"
                                        style="width: 48%"
                                    ></div>
                                </td>
                                <td>
                                    <div
                                        class="skeleton-cell rounded-pill"
                                        style="width: 70px; height: 22px"
                                    ></div>
                                </td>
                                <td>
                                    <div
                                        class="skeleton-cell rounded-pill"
                                        style="width: 55px; height: 22px"
                                    ></div>
                                </td>
                                <td>
                                    <div
                                        class="skeleton-cell rounded"
                                        style="
                                            width: 30px;
                                            height: 30px;
                                            margin: 0 auto;
                                        "
                                    ></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Data Table -->
                <div v-else-if="menuList.length" class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4" width="50">No</th>
                                <th width="64">Icon</th>
                                <th>Nama Menu</th>
                                <th>URL Route</th>
                                <th>Grup</th>
                                <th width="90">Status</th>
                                <th class="text-center" width="100">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(item, index) in menuList"
                                :key="item.Id_Menu"
                            >
                                <!-- No -->
                                <td class="ps-4 text-muted fw-medium">
                                    {{
                                        (pagination.page - 1) *
                                            pagination.limit +
                                        index +
                                        1
                                    }}
                                </td>

                                <!-- Icon -->
                                <td>
                                    <div class="avatar-xs">
                                        <span
                                            class="avatar-title bg-primary-subtle rounded-2 fs-5"
                                        >
                                            <i
                                                :class="
                                                    item.Icon_Menu ||
                                                    'fas fa-circle-dot'
                                                "
                                                class="text-primary"
                                            ></i>
                                        </span>
                                    </div>
                                </td>

                                <!-- Nama Menu -->
                                <td>
                                    <p class="fw-medium mb-0 text-dark">
                                        {{ item.Nama_Menu }}
                                    </p>
                                    <small
                                        v-if="item.Sub_Header"
                                        class="text-muted"
                                        >{{ item.Sub_Header }}</small
                                    >
                                </td>

                                <!-- URL -->
                                <td>
                                    <code
                                        v-if="item.Url_Menu"
                                        class="text-primary bg-primary-subtle px-2 py-1 rounded fs-12"
                                        >{{ item.Url_Menu }}</code
                                    >
                                    <span
                                        v-else
                                        class="text-muted fst-italic fs-12"
                                        >— belum ada URL</span
                                    >
                                </td>

                                <!-- Grup Header -->
                                <td>
                                    <template
                                        v-if="
                                            item.Nama_Header ||
                                            item.Sub_Header ||
                                            item.Sub_Sub_Header
                                        "
                                    >
                                        <span
                                            v-if="item.Nama_Header"
                                            class="badge bg-info-subtle text-info me-1 rounded-pill"
                                            >{{ item.Nama_Header }}</span
                                        >
                                        <span
                                            v-if="item.Sub_Header"
                                            class="badge bg-secondary-subtle text-secondary rounded-pill"
                                            >{{ item.Sub_Header }}</span
                                        >
                                    </template>
                                    <span
                                        v-else
                                        class="text-muted fst-italic fs-12"
                                        >—</span
                                    >
                                </td>

                                <!-- Status -->
                                <td>
                                    <span
                                        v-if="item.Url_Menu"
                                        class="badge bg-success-subtle text-success rounded-pill"
                                    >
                                        <i
                                            class="ri-checkbox-circle-line me-1"
                                        ></i
                                        >Aktif
                                    </span>
                                    <span
                                        v-else
                                        class="badge bg-warning-subtle text-warning rounded-pill"
                                    >
                                        <i
                                            class="ri-error-warning-line me-1"
                                        ></i
                                        >Draft
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="text-center">
                                    <button
                                        class="btn btn-sm btn-soft-warning waves-effect waves-light"
                                        @click="editData(item)"
                                        title="Edit Menu"
                                    >
                                        <i class="ri-edit-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="d-flex flex-column align-items-center justify-content-center py-5 my-2"
                >
                    <DotLottieVue
                        style="height: 180px; width: 180px"
                        autoplay
                        loop
                        src="/animation/empty.lottie"
                    />
                    <p class="text-muted mt-1 mb-1 fw-medium">
                        Tidak ada data menu ditemukan
                    </p>
                    <small class="text-muted mb-3">
                        {{
                            searchQuery
                                ? "Coba kata kunci lain"
                                : "Mulai dengan menambahkan menu pertama"
                        }}
                    </small>
                    <button
                        v-if="!searchQuery"
                        class="btn btn-primary btn-sm"
                        @click="openAddForm"
                    >
                        <i class="ri-add-line me-1"></i>Tambah Menu Pertama
                    </button>
                </div>
            </div>

            <!-- Card Footer – Pagination -->
            <div
                v-if="menuList.length && !loading.menuLoading"
                class="card-footer bg-white border-top py-3"
            >
                <div class="row align-items-center g-2">
                    <div class="col-sm">
                        <p class="text-muted mb-0 fs-12">
                            Menampilkan
                            <span class="fw-semibold text-dark">
                                {{
                                    (pagination.page - 1) * pagination.limit + 1
                                }}
                            </span>
                            –
                            <span class="fw-semibold text-dark">
                                {{
                                    Math.min(
                                        pagination.page * pagination.limit,
                                        pagination.totalData
                                    )
                                }}
                            </span>
                            dari
                            <span class="fw-semibold text-dark">{{
                                pagination.totalData
                            }}</span>
                            menu
                        </p>
                    </div>
                    <div class="col-sm-auto">
                        <ul
                            class="pagination pagination-separated pagination-sm mb-0"
                        >
                            <li
                                class="page-item"
                                :class="{ disabled: pagination.page === 1 }"
                            >
                                <a
                                    href="#"
                                    class="page-link"
                                    @click.prevent="prevPage"
                                >
                                    <i class="ri-arrow-left-s-line"></i>
                                </a>
                            </li>
                            <li
                                class="page-item"
                                v-for="p in visiblePages"
                                :key="p"
                                :class="{ active: p === pagination.page }"
                            >
                                <a
                                    href="#"
                                    class="page-link"
                                    @click.prevent="changePage(p)"
                                    >{{ p }}</a
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
                                >
                                    <i class="ri-arrow-right-s-line"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Offcanvas: Add / Edit Menu ─── -->
        <div
            class="offcanvas offcanvas-end"
            tabindex="-1"
            id="offcanvasMenu"
            aria-labelledby="offcanvasMenuLabel"
            style="width: 460px"
        >
            <!-- Offcanvas Header -->
            <div class="offcanvas-header bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm">
                        <span
                            class="avatar-title bg-white bg-opacity-25 rounded"
                        >
                            <i
                                :class="
                                    isEdit
                                        ? 'ri-edit-box-line'
                                        : 'ri-add-box-line'
                                "
                                class="fs-5"
                            ></i>
                        </span>
                    </div>
                    <div>
                        <h5
                            id="offcanvasMenuLabel"
                            class="mb-0 text-white fw-semibold fs-15"
                        >
                            {{ isEdit ? "Edit Menu" : "Tambah Menu Baru" }}
                        </h5>
                        <small class="opacity-75">
                            {{
                                isEdit
                                    ? "Perbarui informasi menu yang ada"
                                    : "Isi detail menu navigasi baru"
                            }}
                        </small>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="offcanvas"
                    @click="resetForm"
                    aria-label="Close"
                ></button>
            </div>

            <!-- Live Preview Banner -->
            <div class="border-bottom bg-light px-4 py-3">
                <p
                    class="text-muted fs-11 text-uppercase fw-semibold mb-2 ls-1"
                >
                    Pratinjau
                </p>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-md flex-shrink-0">
                        <span
                            class="avatar-title bg-primary-subtle rounded-2 fs-2"
                        >
                            <i
                                :class="form.Icon_Menu || 'fas fa-circle-dot'"
                                class="text-primary"
                            ></i>
                        </span>
                    </div>
                    <div class="min-w-0">
                        <p class="fw-semibold text-dark mb-0 text-truncate">
                            {{ form.Nama_Menu || "Nama Menu" }}
                        </p>
                        <small class="text-muted">
                            <code v-if="form.Url_Menu" class="fs-11"
                                >/{{ form.Url_Menu }}</code
                            >
                            <span v-else class="fst-italic"
                                >URL belum diisi</span
                            >
                        </small>
                        <div class="mt-1" v-if="form.Nama_Header">
                            <span
                                class="badge bg-info-subtle text-info rounded-pill fs-11"
                            >
                                {{ form.Nama_Header }}
                            </span>
                            <span
                                v-if="form.Sub_Header"
                                class="badge bg-secondary-subtle text-secondary rounded-pill fs-11 ms-1"
                                >{{ form.Sub_Header }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Offcanvas Body / Form -->
            <div class="offcanvas-body p-0">
                <form @submit.prevent="submitForm">
                    <div class="p-4">
                        <!-- Info Alert -->
                        <div
                            class="alert alert-info border-0 d-flex gap-2 py-2 mb-4 rounded-3"
                            role="alert"
                        >
                            <i
                                class="ri-information-line flex-shrink-0 fs-16 mt-1"
                            ></i>
                            <small>
                                Icon menggunakan kelas
                                <strong>Font Awesome</strong> (contoh:
                                <code>fas fa-home</code>). Remix Icon dan
                                Bootstrap Icon tidak didukung.
                            </small>
                        </div>

                        <!-- ── Core Fields ── -->
                        <p
                            class="text-muted fs-11 text-uppercase fw-semibold mb-3 ls-1"
                        >
                            <i class="ri-settings-3-line me-1"></i>Informasi
                            Utama
                        </p>

                        <!-- Nama Menu -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-13">
                                Nama Menu
                                <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.Nama_Menu }"
                                placeholder="Contoh: Dashboard, Master Barang..."
                                v-model="form.Nama_Menu"
                                required
                            />
                            <div
                                v-if="errors.Nama_Menu"
                                class="invalid-feedback"
                            >
                                {{ errors.Nama_Menu }}
                            </div>
                        </div>

                        <!-- Icon Menu -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-13">
                                Icon Menu
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span
                                    class="input-group-text bg-white border-end-0 pe-2"
                                >
                                    <i
                                        :class="
                                            form.Icon_Menu ||
                                            'fas fa-circle-dot'
                                        "
                                        class="text-primary fs-16"
                                    ></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control border-start-0 ps-1"
                                    :class="{ 'is-invalid': errors.Icon_Menu }"
                                    placeholder="fas fa-home"
                                    v-model="form.Icon_Menu"
                                    required
                                />
                                <div
                                    v-if="errors.Icon_Menu"
                                    class="invalid-feedback"
                                >
                                    {{ errors.Icon_Menu }}
                                </div>
                            </div>
                            <div class="form-text">
                                Kelas Font Awesome lengkap, contoh:
                                <code>fas fa-cogs</code>,
                                <code>fas fa-chart-bar</code>
                            </div>
                        </div>

                        <!-- URL Route -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold fs-13">
                                URL Route
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span
                                    class="input-group-text bg-white text-muted fw-semibold"
                                    >/</span
                                >
                                <input
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': errors.Url_Menu }"
                                    placeholder="lab/dashboard"
                                    v-model="form.Url_Menu"
                                    required
                                />
                                <div
                                    v-if="errors.Url_Menu"
                                    class="invalid-feedback"
                                >
                                    {{ errors.Url_Menu }}
                                </div>
                            </div>
                            <div class="form-text">
                                Tanpa slash di awal, contoh:
                                <code>master/barang</code>
                            </div>
                        </div>

                        <hr class="border-dashed my-4" />

                        <!-- ── Header Grouping ── -->
                        <p
                            class="text-muted fs-11 text-uppercase fw-semibold mb-3 ls-1"
                        >
                            <i class="ri-folder-3-line me-1"></i>Pengelompokan
                            Sidebar
                            <span
                                class="badge bg-secondary-subtle text-secondary rounded-pill ms-1 fw-normal"
                                style="font-size: 10px"
                                >Opsional</span
                            >
                        </p>

                        <!-- Nama Header -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-13"
                                >Nama Header</label
                            >
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Contoh: MASTER DATA, LAPORAN, PENGATURAN..."
                                v-model="form.Nama_Header"
                            />
                            <div class="form-text">
                                Grup utama yang terlihat di sidebar navigasi
                            </div>
                        </div>

                        <!-- Sub Header -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold fs-13"
                                >Sub Header</label
                            >
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Contoh: Data Barang, Transaksi..."
                                v-model="form.Sub_Header"
                            />
                        </div>

                        <!-- Sub Sub Header -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold fs-13"
                                >Sub Sub Header</label
                            >
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Contoh: Barang Jadi, Barang WIP..."
                                v-model="form.Sub_Sub_Header"
                            />
                        </div>
                    </div>

                    <!-- Sticky Footer Actions -->
                    <div
                        class="border-top bg-light px-4 py-3 d-flex gap-2 justify-content-end sticky-bottom"
                    >
                        <button
                            type="button"
                            class="btn btn-light btn-sm"
                            data-bs-dismiss="offcanvas"
                            @click="resetForm"
                        >
                            <i class="ri-close-line me-1"></i>Batal
                        </button>
                        <button
                            type="submit"
                            class="btn btn-primary btn-sm"
                            :disabled="loading.menuSaveToDatabase"
                        >
                            <span v-if="loading.menuSaveToDatabase">
                                <span
                                    class="spinner-border spinner-border-sm me-1"
                                    role="status"
                                    aria-hidden="true"
                                ></span>
                                Menyimpan...
                            </span>
                            <span v-else>
                                <i
                                    :class="
                                        isEdit
                                            ? 'ri-save-3-line'
                                            : 'ri-add-circle-line'
                                    "
                                    class="me-1"
                                ></i>
                                {{
                                    isEdit ? "Simpan Perubahan" : "Tambah Menu"
                                }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import { debounce } from "lodash";
import Swal from "sweetalert2";

export default {
    name: "HomeMenu",

    components: { DotLottieVue },

    data() {
        return {
            menuList: [],
            searchQuery: "",

            stats: { total: 0, withUrl: 0, withoutUrl: 0 },
            loadingStats: true,

            loading: {
                menuLoading: false,
                menuSaveToDatabase: false,
            },

            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },

            form: {
                id: null,
                Nama_Menu: "",
                Icon_Menu: "",
                Url_Menu: "",
                Nama_Header: "",
                Sub_Header: "",
                Sub_Sub_Header: "",
            },

            errors: {},
            isEdit: false,
            offcanvasInstance: null,
        };
    },

    computed: {
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = Math.max(1, current - 2);
            let end = Math.min(total, start + 4);
            start = Math.max(1, end - 4);

            const pages = [];
            for (let i = start; i <= end; i++) pages.push(i);
            return pages;
        },
    },

    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },

    mounted() {
        this.fetchStats();
        this.fetchMasterMenu();
        const el = document.getElementById("offcanvasMenu");
        if (el) {
            this.offcanvasInstance = new bootstrap.Offcanvas(el, {
                backdrop: true,
                keyboard: false,
            });
        }
    },

    methods: {
        /* ── Stats ── */
        async fetchStats() {
            this.loadingStats = true;
            try {
                const { data } = await axios.get("/api/v1/master-menu/stats");
                if (data.success) this.stats = data.data;
            } catch {
                // silent – stats non-critical
            } finally {
                this.loadingStats = false;
            }
        },

        /* ── Data Fetch ── */
        async fetchMasterMenu(page = 1, query = "") {
            this.loading.menuLoading = true;
            try {
                if (query) {
                    const { data } = await axios.get(
                        "/api/v1/master-menu/search",
                        {
                            params: { q: query },
                        }
                    );
                    this.menuList = data.success ? data.result : [];
                    this.pagination.totalPage = 1;
                    this.pagination.totalData = this.menuList.length;
                } else {
                    const { data } = await axios.get(
                        "/api/v1/master-menu/current",
                        {
                            params: { limit: this.pagination.limit, page },
                        }
                    );
                    if (data.success) {
                        this.menuList = data.data;
                        this.pagination.page = page;
                        this.pagination.totalPage = data.total_page;
                        this.pagination.totalData = data.total_data;
                    } else {
                        this.menuList = [];
                    }
                }
            } catch {
                this.menuList = [];
            } finally {
                this.loading.menuLoading = false;
            }
        },

        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchMasterMenu(1, this.searchQuery);
        }, 500),

        onLimitChange() {
            this.pagination.page = 1;
            this.fetchMasterMenu(1, this.searchQuery);
        },

        /* ── Form ── */
        openAddForm() {
            this.resetForm();
            this.offcanvasInstance?.show();
        },

        editData(item) {
            this.form = {
                id: item.Id_Menu,
                Nama_Menu: item.Nama_Menu || "",
                Icon_Menu: item.Icon_Menu || "",
                Url_Menu: item.Url_Menu || "",
                Nama_Header: item.Nama_Header || "",
                Sub_Header: item.Sub_Header || "",
                Sub_Sub_Header: item.Sub_Sub_Header || "",
            };
            this.errors = {};
            this.isEdit = true;
            this.offcanvasInstance?.show();
        },

        resetForm() {
            this.form = {
                id: null,
                Nama_Menu: "",
                Icon_Menu: "",
                Url_Menu: "",
                Nama_Header: "",
                Sub_Header: "",
                Sub_Sub_Header: "",
            };
            this.errors = {};
            this.isEdit = false;
        },

        async submitForm() {
            this.errors = {};
            this.loading.menuSaveToDatabase = true;

            try {
                let response;

                if (this.isEdit) {
                    response = await axios.put(
                        `/api/v1/master-menu/update/${this.form.id}`,
                        this.form
                    );
                } else {
                    response = await axios.post(
                        "/api/v1/master-menu/store",
                        this.form
                    );
                }

                if (!response.data.success) {
                    if (response.data.errors)
                        this.errors = response.data.errors;
                    throw new Error(
                        response.data.message || "Gagal menyimpan data"
                    );
                }

                // Tutup offcanvas
                this.offcanvasInstance?.hide();

                await Swal.fire({
                    icon: "success",
                    title: "Berhasil!",
                    text: response.data.message,
                    timer: 1800,
                    showConfirmButton: false,
                    timerProgressBar: true,
                });

                this.resetForm();
                await Promise.all([
                    this.fetchMasterMenu(this.pagination.page),
                    this.fetchStats(),
                ]);
            } catch (error) {
                const msg =
                    error.response?.data?.message ||
                    error.message ||
                    "Terjadi kesalahan, coba lagi.";

                Swal.fire({
                    icon: "error",
                    title: "Gagal Menyimpan",
                    text: typeof msg === "object" ? JSON.stringify(msg) : msg,
                });
            } finally {
                this.loading.menuSaveToDatabase = false;
            }
        },

        /* ── Pagination ── */
        prevPage() {
            if (this.pagination.page > 1)
                this.fetchMasterMenu(
                    this.pagination.page - 1,
                    this.searchQuery
                );
        },
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage)
                this.fetchMasterMenu(
                    this.pagination.page + 1,
                    this.searchQuery
                );
        },
        changePage(page) {
            if (page !== this.pagination.page)
                this.fetchMasterMenu(page, this.searchQuery);
        },
    },
};
</script>

<style scoped>
/* ── Skeleton shimmer ── */
.skeleton-cell {
    position: relative;
    height: 18px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.skeleton-cell::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 25%,
        rgba(255, 255, 255, 0.55) 50%,
        transparent 75%
    );
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
}

@keyframes shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* ── Letter spacing utility ── */
.ls-1 {
    letter-spacing: 0.06em;
}

/* ── Disable state ── */
button:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

/* ── Dashed divider ── */
.border-dashed {
    border-style: dashed !important;
    border-color: #dee2e6 !important;
}

/* ── Code tag style ── */
code {
    font-size: 0.8em;
    background: rgba(64, 81, 137, 0.08);
    color: #405189;
    padding: 1px 6px;
    border-radius: 4px;
}

/* ── Table row hover ── */
.table-hover tbody tr:hover {
    background-color: rgba(64, 81, 137, 0.03);
}

/* ── Soft button (Velzon pattern) ── */
.btn-soft-warning {
    color: #f1963b;
    background-color: rgba(241, 150, 59, 0.1);
    border-color: transparent;
}
.btn-soft-warning:hover {
    color: #fff;
    background-color: #f1963b;
    border-color: #f1963b;
}

/* ── Sticky footer in offcanvas ── */
.sticky-bottom {
    position: sticky;
    bottom: 0;
    z-index: 10;
}
</style>
