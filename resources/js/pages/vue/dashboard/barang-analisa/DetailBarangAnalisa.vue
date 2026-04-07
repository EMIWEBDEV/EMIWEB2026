<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1 class="text-2xl md:text-3xl font-bold text-primary">
                        Barang Analisa
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Barang Analisa PT. Evo Manufacturing Indonesia
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
                                        <th>Kode Barang</th>
                                        <th>Nama Barang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="n in 5"
                                        :key="n"
                                        class="skeleton-row"
                                    >
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
                                        <th>Kode Barang</th>
                                        <th>Nama Barang</th>
                                        <th>Nama Mesin</th>
                                        <th>Pengguna</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, index) in detailDataList"
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
                                            {{ item.kode_analisa ?? "-" }}
                                        </td>
                                        <td>{{ item.jenis_analisa ?? "-" }}</td>
                                        <td>{{ item.kode_barang ?? "-" }}</td>
                                        <td>{{ item.nama_barang ?? "-" }}</td>
                                        <td>{{ item.nama_mesin ?? "-" }}</td>
                                        <td>{{ item.Id_User ?? "-" }}</td>
                                        <td>
                                            <span
                                                v-if="item.Flag_Aktif === 'Y'"
                                                class="badge bg-success"
                                            >
                                                Aktif
                                            </span>

                                            <span
                                                v-else
                                                class="badge bg-danger"
                                            >
                                                Tidak Aktif
                                            </span>
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
            detailDataList: [],
            loading: {
                loadingDataList: false,
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
        async fetchDetailBindingIdentity(page = 1, query = "") {
            this.loading.loadingDataList = true;
            try {
                if (query) {
                    const idJenisAnalisa = this.id;
                    const response = await axios.get(
                        `/api/v1/barang-jenis-analisa/${idJenisAnalisa}/search`,
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
                        `/api/v1/detail/barang-jenis-analisa/${idJenisAnalisa}`,
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
                    } else {
                        this.detailDataList = [];
                    }
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                this.detailDataList = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        debouncedSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchDetailBindingIdentity(
                this.pagination.page,
                this.searchQuery
            );
        }, 500),
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchDetailBindingIdentity(
                    this.pagination.page + 1,
                    this.searchQuery
                );
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchDetailBindingIdentity(
                    this.pagination.page - 1,
                    this.searchQuery
                );
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchDetailBindingIdentity(page, this.searchQuery);
            }
        },
    },
    watch: {
        searchQuery() {
            this.debouncedSearch();
        },
    },
    mounted() {
        this.fetchDetailBindingIdentity();
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
