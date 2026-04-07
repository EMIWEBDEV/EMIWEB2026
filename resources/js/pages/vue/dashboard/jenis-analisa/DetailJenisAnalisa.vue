<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Jenis Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Jenis Analisa PT. Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div v-if="hasSubAnalisa">
                    <ul class="nav nav-pills nav-justified mb-3" role="tablist">
                        <li
                            class="nav-item waves-effect waves-light"
                            role="presentation"
                        >
                            <a
                                class="nav-link active"
                                data-bs-toggle="tab"
                                href="#pill-justified-home-1"
                                role="tab"
                                aria-selected="true"
                            >
                                Jenis Analisa
                            </a>
                        </li>
                        <li
                            class="nav-item waves-effect waves-light"
                            role="presentation"
                        >
                            <a
                                class="nav-link"
                                data-bs-toggle="tab"
                                href="#pill-justified-profile-1"
                                role="tab"
                                aria-selected="false"
                                tabindex="-1"
                            >
                                Sub Jenis Analisa
                            </a>
                        </li>
                    </ul>
                    <!-- Tab panes -->
                    <div class="tab-content text-muted">
                        <div
                            class="tab-pane active"
                            id="pill-justified-home-1"
                            role="tabpanel"
                        >
                            <div class="search-box">
                                <input
                                    type="search"
                                    class="form-control search"
                                    placeholder="Search..."
                                    v-model="searchQuery"
                                    @input="handleSearch"
                                />
                                <i class="ri-search-line search-icon"></i>
                            </div>

                            <div class="col-12 mt-3">
                                <div v-if="loading.loadingDataList">
                                    <div class="table-wrapper">
                                        <table
                                            class="skeleton-table"
                                            aria-busy="true"
                                            aria-label="Loading data"
                                        >
                                            <thead>
                                                <tr class="text-center">
                                                    <th>No</th>
                                                    <th>Kode Analisa</th>
                                                    <th>Jenis Analisa</th>
                                                    <th>Nama Mesin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    class="skeleton-row"
                                                    v-for="(item, index) in 5"
                                                    :key="index"
                                                >
                                                    <td>
                                                        <div
                                                            class="skeleton-cell"
                                                        ></div>
                                                    </td>
                                                    <td>
                                                        <div
                                                            class="skeleton-cell"
                                                        ></div>
                                                    </td>
                                                    <td>
                                                        <div
                                                            class="skeleton-cell"
                                                        ></div>
                                                    </td>
                                                    <td>
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
                                        class="table-responsive"
                                    >
                                        <table
                                            class="table table-bordered text-center align-middle"
                                        >
                                            <thead class="table-light">
                                                <tr>
                                                    <th>No</th>
                                                    <th>Kode Analisa</th>
                                                    <th>Jenis Analisa</th>
                                                    <th>Nama Mesin</th>
                                                    <th>Sifat Kegiatan</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(
                                                        item, index
                                                    ) in detailDataList"
                                                    :key="index"
                                                >
                                                    <td>
                                                        {{
                                                            (pagination.page -
                                                                1) *
                                                                pagination.limit +
                                                            index +
                                                            1
                                                        }}
                                                    </td>
                                                    <td>
                                                        {{
                                                            item.Kode_Analisa ??
                                                            "-"
                                                        }}
                                                    </td>
                                                    <td>
                                                        {{
                                                            item.Jenis_Analisa ??
                                                            "-"
                                                        }}
                                                    </td>
                                                    <td>
                                                        {{
                                                            item.Nama_Mesin ??
                                                            "-"
                                                        }}
                                                    </td>
                                                    <td>
                                                        {{
                                                            item.Sifat_Kegiatan ??
                                                            "-"
                                                        }}
                                                    </td>
                                                    <td>
                                                        <button
                                                            @click="
                                                                editData(item)
                                                            "
                                                            class="btn btn-warning"
                                                            type="button"
                                                        >
                                                            Edit
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div
                                        class="align-items-center mt-2 row g-3 text-center text-sm-start"
                                    >
                                        <div class="col-sm">
                                            <div class="text-muted">
                                                Total Data
                                                <span class="fw-semibold">{{
                                                    pagination.totalData
                                                }}</span>
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
                                                            pagination.page ===
                                                            1,
                                                    }"
                                                >
                                                    <a
                                                        href="#"
                                                        class="page-link"
                                                        @click="prevPage"
                                                        >←</a
                                                    >
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
                                                        @click="
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
                                                        @click="nextPage"
                                                        >→</a
                                                    >
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div
                                        v-if="!detailDataList.length"
                                        class="d-flex justify-content-center"
                                    >
                                        <div
                                            class="flex-column align-content-center"
                                        >
                                            <DotLottieVue
                                                style="
                                                    height: 100px;
                                                    width: 100px;
                                                "
                                                autoplay
                                                loop
                                                src="/animation/empty2.json"
                                            />
                                            <p class="text-center">
                                                Data Tidak Ditemukan !
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="tab-pane"
                            id="pill-justified-profile-1"
                            role="tabpanel"
                        >
                            <div
                                v-if="
                                    detailDataList.length &&
                                    detailDataList[0].Sub_Analisa?.length
                                "
                                class="table-responsive"
                            >
                                <table
                                    class="table table-bordered text-center align-middle"
                                >
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Sub Analisa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(
                                                sub, index
                                            ) in detailDataList[0].Sub_Analisa"
                                            :key="index"
                                        >
                                            <td>{{ index + 1 }}</td>
                                            <td>{{ sub }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else>
                                <p class="text-muted text-center">
                                    Tidak ada Sub Analisa ditemukan.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else>
                    <div class="search-box">
                        <input
                            type="search"
                            class="form-control search"
                            placeholder="Search..."
                            v-model="searchQuery"
                            @input="handleSearch"
                        />
                        <i class="ri-search-line search-icon"></i>
                    </div>

                    <div class="col-12 mt-3">
                        <div v-if="loading.loadingDataList">
                            <div class="table-wrapper">
                                <table
                                    class="skeleton-table"
                                    aria-busy="true"
                                    aria-label="Loading data"
                                >
                                    <thead>
                                        <tr class="text-center">
                                            <th>No</th>
                                            <th>Kode Analisa</th>
                                            <th>Jenis Analisa</th>
                                            <th>Nama Mesin</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            class="skeleton-row"
                                            v-for="(item, index) in 5"
                                            :key="index"
                                        >
                                            <td>
                                                <div
                                                    class="skeleton-cell"
                                                ></div>
                                            </td>
                                            <td>
                                                <div
                                                    class="skeleton-cell"
                                                ></div>
                                            </td>
                                            <td>
                                                <div
                                                    class="skeleton-cell"
                                                ></div>
                                            </td>
                                            <td>
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
                                class="table-responsive"
                            >
                                <table
                                    class="table table-bordered text-center align-middle"
                                >
                                    <thead class="table-light">
                                        <tr>
                                            <th>No</th>
                                            <th>Kode Analisa</th>
                                            <th>Jenis Analisa</th>
                                            <th>Nama Mesin</th>
                                            <th>Sifat Kegiatan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(
                                                item, index
                                            ) in detailDataList"
                                            :key="index"
                                        >
                                            <td>
                                                {{
                                                    (pagination.page - 1) *
                                                        pagination.limit +
                                                    index +
                                                    1
                                                }}
                                            </td>
                                            <td>
                                                {{ item.Kode_Analisa ?? "-" }}
                                            </td>
                                            <td>
                                                {{ item.Jenis_Analisa ?? "-" }}
                                            </td>
                                            <td>
                                                {{ item.Nama_Mesin ?? "-" }}
                                            </td>
                                            <td>
                                                {{ item.Sifat_Kegiatan ?? "-" }}
                                            </td>
                                            <td>
                                                <button
                                                    @click="editData(item)"
                                                    class="btn btn-warning"
                                                    type="button"
                                                >
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                class="align-items-center mt-2 row g-3 text-center text-sm-start"
                            >
                                <div class="col-sm">
                                    <div class="text-muted">
                                        Total Data
                                        <span class="fw-semibold">{{
                                            pagination.totalData
                                        }}</span>
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
                                                disabled: pagination.page === 1,
                                            }"
                                        >
                                            <a
                                                href="#"
                                                class="page-link"
                                                @click="prevPage"
                                                >←</a
                                            >
                                        </li>

                                        <li
                                            class="page-item"
                                            v-for="page in visiblePages"
                                            :key="page"
                                            :class="{
                                                active:
                                                    page === pagination.page,
                                            }"
                                        >
                                            <a
                                                href="#"
                                                class="page-link"
                                                @click="changePage(page)"
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
                                                @click="nextPage"
                                                >→</a
                                            >
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div
                                v-if="!detailDataList.length"
                                class="d-flex justify-content-center"
                            >
                                <div class="flex-column align-content-center">
                                    <DotLottieVue
                                        style="height: 100px; width: 100px"
                                        autoplay
                                        loop
                                        src="/animation/empty2.json"
                                    />
                                    <p class="text-center">
                                        Data Tidak Ditemukan !
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div
        class="modal fade"
        id="modalEditAnalisa"
        tabindex="-1"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Jenis Analisa</h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" v-if="roles && roles.length > 1">
                        <label for="Kode_Role" class="form-label fw-semibold">
                            Pilih Role <span class="text-danger">*</span>
                        </label>
                        <el-select
                            v-model="form.Kode_Role"
                            placeholder="--- Pilih Role ---"
                            class="w-100"
                            size="large"
                            @change="handleRoleChange"
                        >
                            <el-option
                                v-for="role in roles"
                                :key="role.Kode_Role"
                                :label="role.Nama_Role || role.Kode_Role"
                                :value="role.Kode_Role"
                            />
                        </el-select>
                    </div>

                    <div class="mb-3" v-if="isFLM">
                        <label class="form-label fw-semibold">
                            Kategori Analisa <span class="text-danger">*</span>
                        </label>
                        <el-select
                            v-model="form.Kode_Aktivitas_Lab"
                            placeholder="-- Pilih Kategori Analisa --"
                            clearable
                            class="w-100"
                            size="large"
                        >
                            <el-option
                                v-for="kat in optionsKategori"
                                :key="kat.Id_Klasifikasi_Aktivitas_Lab"
                                :label="kat.Nama_Aktivitas"
                                :value="kat.Kode_Aktivitas_Lab"
                            >
                                <span
                                    style="
                                        font-weight: bold;
                                        color: var(--el-text-color-primary);
                                    "
                                >
                                    {{ kat.Nama_Aktivitas }}
                                </span>
                            </el-option>
                        </el-select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label
                                for="Kode_Analisa"
                                class="form-label fw-semibold"
                            >
                                Kode Analisa <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="Kode_Analisa"
                                placeholder="Masukkan Kode Analisa"
                                v-model="form.Kode_Analisa"
                            />
                        </div>

                        <div class="col-md-6 mb-3">
                            <label
                                for="Jenis_Analisa"
                                class="form-label fw-semibold"
                            >
                                Jenis Analisa <span class="text-danger">*</span>
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="Jenis_Analisa"
                                placeholder="Masukkan Jenis Analisa"
                                v-model="form.Jenis_Analisa"
                            />
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="Id_Mesin" class="form-label fw-semibold">
                            Nama Mesin
                        </label>
                        <v-select
                            v-if="optionsMesinList && optionsMesinList.length"
                            v-model="selectedMesinList"
                            :options="optionsMesinList"
                            label="name"
                            placeholder="--- Pilih Mesin  ---"
                        />
                    </div>

                    <div
                        class="toggle-card border rounded p-3 mb-3"
                        :class="{
                            'active-toggle': form.Sifat_Kegiatan === 'Rutin',
                        }"
                    >
                        <div
                            class="d-flex align-items-center justify-content-between"
                        >
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="icon-box"
                                    :class="
                                        form.Sifat_Kegiatan === 'Rutin'
                                            ? 'text-success'
                                            : 'text-secondary'
                                    "
                                >
                                    <svg
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <rect
                                            x="3"
                                            y="4"
                                            width="18"
                                            height="18"
                                            rx="2"
                                            ry="2"
                                        ></rect>
                                        <line
                                            x1="16"
                                            y1="2"
                                            x2="16"
                                            y2="6"
                                        ></line>
                                        <line
                                            x1="8"
                                            y1="2"
                                            x2="8"
                                            y2="6"
                                        ></line>
                                        <line
                                            x1="3"
                                            y1="10"
                                            x2="21"
                                            y2="10"
                                        ></line>
                                    </svg>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">
                                        Sifat Kegiatan Analisa
                                    </h6>
                                    <span class="text-muted small d-block">
                                        Saat ini:
                                        <strong
                                            :class="
                                                form.Sifat_Kegiatan === 'Rutin'
                                                    ? 'text-success'
                                                    : ''
                                            "
                                        >
                                            {{
                                                form.Sifat_Kegiatan === "Rutin"
                                                    ? "Rutin"
                                                    : "Berkala"
                                            }} </strong
                                        >.
                                        {{
                                            form.Sifat_Kegiatan === "Rutin"
                                                ? "Dilakukan jadwal harian/mingguan/bulanan."
                                                : "Dilakukan sesekali sesuai kebutuhan."
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <el-switch
                                    v-model="form.Sifat_Kegiatan"
                                    active-value="Rutin"
                                    inactive-value="Berkala"
                                    style="--el-switch-on-color: #198754"
                                    size="large"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="toggle-card border rounded p-3 mb-3"
                        :class="{
                            'active-toggle': form.Flag_Perhitungan === 'Y',
                        }"
                    >
                        <div
                            class="d-flex align-items-center justify-content-between"
                        >
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="icon-box"
                                    :class="
                                        form.Flag_Perhitungan === 'Y'
                                            ? 'text-success'
                                            : 'text-secondary'
                                    "
                                >
                                    <svg
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <rect
                                            x="4"
                                            y="2"
                                            width="16"
                                            height="20"
                                            rx="2"
                                            ry="2"
                                        ></rect>
                                        <line
                                            x1="8"
                                            y1="6"
                                            x2="16"
                                            y2="6"
                                        ></line>
                                        <line
                                            x1="16"
                                            y1="14"
                                            x2="16"
                                            y2="18"
                                        ></line>
                                        <path d="M16 10h.01"></path>
                                        <path d="M12 10h.01"></path>
                                        <path d="M8 10h.01"></path>
                                        <path d="M12 14h.01"></path>
                                        <path d="M8 14h.01"></path>
                                        <path d="M12 18h.01"></path>
                                        <path d="M8 18h.01"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">
                                        Memakai Perhitungan Berdasarkan
                                        Parameter?
                                    </h6>
                                    <span class="text-muted small d-block">
                                        Saat ini:
                                        <strong
                                            :class="
                                                form.Flag_Perhitungan === 'Y'
                                                    ? 'text-success'
                                                    : ''
                                            "
                                        >
                                            {{
                                                form.Flag_Perhitungan === "Y"
                                                    ? "YA"
                                                    : "TIDAK"
                                            }} </strong
                                        >.
                                        {{
                                            form.Flag_Perhitungan === "Y"
                                                ? "Analisis membutuhkan kalkulasi sistem."
                                                : "Hanya menginputkan hasil akhir saja."
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <el-switch
                                    v-model="form.Flag_Perhitungan"
                                    active-value="Y"
                                    :inactive-value="null"
                                    style="--el-switch-on-color: #198754"
                                    size="large"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="isFLM"
                        class="toggle-card border rounded p-3 mb-3"
                        :class="{ 'active-toggle': form.Flag_Foto === 'Y' }"
                    >
                        <div
                            class="d-flex align-items-center justify-content-between"
                        >
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="icon-box"
                                    :class="
                                        form.Flag_Foto === 'Y'
                                            ? 'text-success'
                                            : 'text-secondary'
                                    "
                                >
                                    <svg
                                        width="24"
                                        height="24"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"
                                        ></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold">
                                        Membutuhkan Foto Bukti?
                                    </h6>
                                    <span class="text-muted small d-block">
                                        Saat ini:
                                        <strong
                                            :class="
                                                form.Flag_Foto === 'Y'
                                                    ? 'text-success'
                                                    : ''
                                            "
                                        >
                                            {{
                                                form.Flag_Foto === "Y"
                                                    ? "YA"
                                                    : "TIDAK"
                                            }} </strong
                                        >.
                                        {{
                                            form.Flag_Foto === "Y"
                                                ? "Wajib melampirkan foto saat melakukan penginputan hasil."
                                                : "Tidak diwajibkan untuk melampirkan foto."
                                        }}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <el-switch
                                    v-model="form.Flag_Foto"
                                    active-value="Y"
                                    inactive-value="T"
                                    style="--el-switch-on-color: #198754"
                                    size="large"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="btn btn-success"
                        @click="saveToDatabase"
                        :disabled="loading.loadingSaveToDatabase"
                    >
                        <span
                            v-if="loading.loadingSaveToDatabase"
                            class="spinner-border spinner-border-sm me-1"
                            role="status"
                            aria-hidden="true"
                        ></span>

                        {{
                            loading.loadingSaveToDatabase
                                ? "Menyimpan..."
                                : "Update"
                        }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import { debounce } from "lodash";
import vSelect from "vue-select";
import { ElSelect, ElOption, ElSwitch } from "element-plus";

export default {
    components: {
        DotLottieVue,
        vSelect,
        ElSelect,
        ElOption,
        ElSwitch,
    },
    props: {
        id: {
            type: [String, Number],
            required: true,
        },
        item: Object,
        index: Number,
        roles: {
            type: Array,
            default: () => [],
        },
    },
    data() {
        return {
            selectedMesinList: null,
            optionsMesinList: [],
            optionsKategori: [],
            searchQuery: "",
            hasSubAnalisa: false,
            detailDataList: [],
            loading: {
                loadingDataList: false,
                loadingSaveToDatabase: false,
                loadingOptionMesinList: false,
                loadingOptionKategori: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            form: {
                id: "",
                Kode_Analisa: "",
                Jenis_Analisa: "",
                Sifat_Kegiatan: "Rutin",
                Flag_Perhitungan: null,
                Kode_Role: "",
                Flag_Foto: "T",
                Kode_Aktivitas_Lab: "",
            },
        };
    },
    computed: {
        visiblePages() {
            const total = this.pagination.totalPage;
            const current = this.pagination.page;
            let start = current - 2;
            let end = current + 2;

            if (start < 1) {
                start = 1;
                end = Math.min(5, total);
            }

            if (end > total) {
                end = total;
                start = Math.max(1, total - 4);
            }

            const pages = [];
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },
        isFLM() {
            if (this.roles.length === 1 && this.roles[0].Kode_Role === "FLM")
                return true;
            return this.form.Kode_Role === "FLM";
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
        isFLM: {
            immediate: true,
            handler(val) {
                if (val && this.optionsKategori.length === 0) {
                    this.fetchKategoriAnalisa();
                }
            },
        },
    },
    methods: {
        async fetchDetailJenisAnalisa(page = 1, query = "") {
            this.loading.loadingDataList = true;
            try {
                if (query) {
                    const idJenisAnalisa = this.id;
                    const response = await axios.get(
                        `/search/detail/jenis-analisa/${idJenisAnalisa}`,
                        {
                            params: { q: query },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailDataList = response.data.result;
                        this.pagination.totalPage = 1;
                        this.pagination.totalData = this.detailDataList.length;
                    } else {
                        this.detailDataList = [];
                    }
                } else {
                    const idJenisAnalisa = this.id;
                    const response = await axios.get(
                        `/fetch/detail/jenis-analisa/${idJenisAnalisa}`,
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
                            },
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailDataList = response.data.result;
                        this.pagination.page = page;
                        this.pagination.totalPage = response.data.total_page;
                        this.pagination.totalData = response.data.total_data;
                        this.hasSubAnalisa = this.detailDataList.some(
                            (item) =>
                                Array.isArray(item.Sub_Analisa) &&
                                item.Sub_Analisa.length > 0
                        );
                    } else {
                        this.detailDataList = [];
                        this.hasSubAnalisa = false;
                    }
                }
            } catch (error) {
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        async fetchMesinList() {
            this.loading.loadingOptionMesinList = true;
            try {
                const response = await axios.get("/api/v1/mesin-analisa/list");
                if (response.status === 200 && response.data?.result) {
                    this.optionsMesinList = response.data.result.map(
                        (item) => ({
                            value: item.No_Urut,
                            name: `${item.Divisi_Mesin} ~ ${item.Nama_Mesin}`,
                        })
                    );
                } else {
                    this.optionsMesinList = [];
                }
            } catch (error) {
                this.optionsMesinList = [];
            } finally {
                this.loading.loadingOptionMesinList = false;
            }
        },
        async fetchKategoriAnalisa() {
            this.loading.loadingOptionKategori = true;
            try {
                const response = await axios.get(
                    "/api/v1/klasifikasi-analisa/option/current"
                );
                if (response.status === 200 && response.data?.result) {
                    this.optionsKategori = response.data.result;
                } else {
                    this.optionsKategori = [];
                }
            } catch (error) {
                this.optionsKategori = [];
            } finally {
                this.loading.loadingOptionKategori = false;
            }
        },
        handleRoleChange() {
            if (this.isFLM && this.optionsKategori.length === 0) {
                this.fetchKategoriAnalisa();
            }
        },
        async saveToDatabase() {
            this.loading.loadingSaveToDatabase = true;
            const isRoleRequiredButEmpty =
                this.roles && this.roles.length > 1 && !this.form.Kode_Role;

            try {
                if (
                    !this.form.Kode_Analisa ||
                    !this.form.Jenis_Analisa ||
                    isRoleRequiredButEmpty
                ) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Data yang dipilih tidak lengkap atau kosong.",
                    });
                    this.loading.loadingSaveToDatabase = false;
                    return;
                }

                if (
                    this.isFLM &&
                    (!this.form.Kode_Aktivitas_Lab || !this.form.Flag_Foto)
                ) {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Kategori Analisa atau Flag Foto wajib dipilih untuk role FLM.",
                    });
                    this.loading.loadingSaveToDatabase = false;
                    return;
                }

                const payload = {
                    Kode_Analisa: this.form.Kode_Analisa,
                    Jenis_Analisa: this.form.Jenis_Analisa,
                    Flag_Perhitungan: this.form.Flag_Perhitungan,
                    Sifat_Kegiatan: this.form.Sifat_Kegiatan,
                    Kode_Role: this.form.Kode_Role,
                    Flag_Foto: this.isFLM ? this.form.Flag_Foto : "T",
                    Kode_Aktivitas_Lab: this.isFLM
                        ? this.form.Kode_Aktivitas_Lab
                        : null,
                };

                if (this.selectedMesinList?.value) {
                    payload.Id_Mesin = this.selectedMesinList.value;
                }

                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content");

                const response = await axios.put(
                    `/jenis-analisa/update/${this.form.id}`,
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                if (response.status === 200 && response.data) {
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil",
                        text: response.data.message,
                    }).then(() => {
                        window.location.href = "/jenis-analisa";
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: "Gagal menyimpan data.",
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: "error",
                    title: "Opss...",
                    text: error.response?.data?.message || "Terjadi Kesalahan",
                });
            } finally {
                this.loading.loadingSaveToDatabase = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchDetailJenisAnalisa(
                this.pagination.page,
                this.searchQuery
            );
        }, 500),
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchDetailJenisAnalisa(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchDetailJenisAnalisa(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchDetailJenisAnalisa(page, this.searchQuery);
            }
        },
        editData(item) {
            this.form = {
                id: item.id,
                Kode_Analisa: item.Kode_Analisa || "",
                Jenis_Analisa: item.Jenis_Analisa || "",
                Sifat_Kegiatan: item.Sifat_Kegiatan || "Rutin",
                Flag_Perhitungan: item.Flag_Perhitungan ?? null,
                Kode_Role: item.Kode_Role || "",
                Flag_Foto: item.Flag_Foto || "T",
                Kode_Aktivitas_Lab: item.Kode_Aktivitas_Lab || "",
            };

            let mesinDitemukan = this.optionsMesinList.find(
                (mesin) => mesin.value === item.Id_Mesin
            );

            if (mesinDitemukan) {
                this.selectedMesinList = mesinDitemukan;
            } else if (item.Id_Mesin) {
                this.selectedMesinList = {
                    value: item.Id_Mesin,
                    name: item.Nama_Mesin || "Mesin Terpilih",
                };
            } else {
                this.selectedMesinList = null;
            }

            this.isEdit = true;

            const modal = new bootstrap.Modal(
                document.getElementById("modalEditAnalisa")
            );
            modal.show();
        },
    },
    mounted() {
        this.fetchDetailJenisAnalisa();
        this.fetchMesinList();
        if (this.roles.length === 1 && this.roles[0].Kode_Role === "FLM") {
            this.fetchKategoriAnalisa();
        }
    },
};
</script>

<style>
/* CSS Custom Agar Mirip Design UI-nya */
.border-dashed {
    border-style: dashed !important;
    border-width: 2px !important;
}

.toggle-card {
    transition: all 0.3s ease;
    background-color: #ffffff;
    border-color: #e5e7eb !important;
}

/* State ketika ON (Aktif) */
.toggle-card.active-toggle {
    background-color: #f2fbf7 !important; /* Warna hijau muda transparan mirip gambar */
    border-color: #198754 !important; /* Border hijau */
}

.icon-box {
    display: flex;
    align-items: center;
    justify-content: center;
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
