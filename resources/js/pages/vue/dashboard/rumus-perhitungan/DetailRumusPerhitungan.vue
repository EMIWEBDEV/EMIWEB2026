<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Rumus Perhitungan
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Rumus Perhitungan Berdasarkan Jenis Analisa PT.
                        Evo Manufacturing Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

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
                    <div v-if="loading.loadingRumusPerhitungan">
                        <div class="table-wrapper">
                            <table
                                class="skeleton-table"
                                aria-busy="true"
                                aria-label="Loading data"
                            >
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Jenis Analisa</th>
                                        <th>Rumus</th>
                                        <th>Digit Belakang Koma</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="skeleton-row">
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                    </tr>
                                    <tr class="skeleton-row">
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                    </tr>
                                    <tr class="skeleton-row">
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                    </tr>
                                    <tr class="skeleton-row">
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                    </tr>
                                    <tr class="skeleton-row">
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                        <td>
                                            <div class="skeleton-cell"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else>
                        <div
                            v-if="detailRumusPerhitungan.length"
                            class="table-responsive"
                        >
                            <table
                                class="table table-bordered text-center align-middle"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Jenis Analisa</th>
                                        <th>Nama Perhitungan</th>
                                        <th>Rumus</th>
                                        <th>Pembulatan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(
                                            item, index
                                        ) in detailRumusPerhitungan"
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
                                            {{ item.jenis_analisa ?? "-" }}
                                        </td>
                                        <td>{{ item.Nama_Kolom ?? "-" }}</td>

                                        <td>
                                            {{ item.Formula_Label ?? "-" }}
                                        </td>
                                        <td>
                                            {{ item.Hasil_Perhitungan ?? 0 }}
                                            Angka Belakang Koma
                                        </td>
                                        <td>
                                            <a
                                                :href="
                                                    '/perhitungan/rumus/edit/' +
                                                    item.id
                                                "
                                                class="btn btn-warning"
                                                >Edit</a
                                            >
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
                                            active: page === pagination.page,
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
                            v-if="!detailRumusPerhitungan.length"
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
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import { debounce } from "lodash";

export default {
    components: {
        DotLottieVue,
    },
    props: {
        id: {
            type: [String, Number],
            required: true,
        },
        item: Object,
        index: Number,
    },
    data() {
        return {
            searchQuery: "",
            detailRumusPerhitungan: [],
            loading: {
                loadingRumusPerhitungan: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
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
    },
    methods: {
        async fetchDetailRumusPerhitungan(page = 1, query = "") {
            this.loading.loadingRumusPerhitungan = true;
            try {
                if (query) {
                    const idJenisAnalisa = this.id;
                    const response = await axios.get(
                        `/search/perhitungan-rumus/show/${idJenisAnalisa}`,
                        {
                            params: { q: query },
                            withCredentials: true,
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailRumusPerhitungan = response.data.result;
                        this.pagination.totalPage = 1;
                        this.pagination.totalData =
                            this.detailRumusPerhitungan.length;
                    } else {
                        this.detailRumusPerhitungan = [];
                    }
                } else {
                    const idJenisAnalisa = this.id;
                    const response = await axios.get(
                        `/fetch/perhitungan-rumus/show/${idJenisAnalisa}`,
                        {
                            params: {
                                limit: this.pagination.limit,
                                page,
                            },
                            withCredentials: true,
                        }
                    );

                    if (response.status === 200 && response.data?.result) {
                        this.detailRumusPerhitungan = response.data.result;
                        this.pagination.page = page;
                        this.pagination.totalPage = response.data.total_page;
                        this.pagination.totalData = response.data.total_data;
                    } else {
                        this.detailRumusPerhitungan = [];
                    }
                }
            } catch (error) {
                this.detailRumusPerhitungan = [];
            } finally {
                this.loading.loadingRumusPerhitungan = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchDetailRumusPerhitungan(
                this.pagination.page,
                this.searchQuery
            );
        }, 500),

        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchIdentityKomputer(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchIdentityKomputer(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchIdentityKomputer(page, this.searchQuery);
            }
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        console.log(this.id);
        this.fetchDetailRumusPerhitungan();
    },
};
</script>

<style>
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
