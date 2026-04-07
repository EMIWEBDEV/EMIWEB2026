<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1
                        class="text-2xl md:text-3xl font-bold text-primary"
                        style="color: #2c3e50"
                    >
                        Hasil Trial Dibatalkan
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Hasil Trial Dibatalkan PT. Evo Manufacturing
                        Indonesia
                    </p>
                    <div class="divider my-3"></div>
                </div>

                <div class="row g-4 mb-3">
                    <div class="col-12">
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
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div v-if="loading.loadingDataList">
                        <div class="table-responsive">
                            <table
                                class="table table-bordered text-center align-middle skeleton-table border-light"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th width="50"></th>
                                        <th>Nama Barang</th>
                                        <th>No PO</th>
                                        <th>No Split</th>
                                        <th>No Batch</th>
                                        <th>No Sampel</th>
                                        <th>Status</th>
                                        <th>Tgl Dibatalkan</th>
                                        <th>User</th>
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
                            v-if="paginatedData.length"
                            class="table-responsive"
                        >
                            <table
                                class="table table-bordered text-center align-middle mb-0"
                            >
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Nama Barang</th>
                                        <th>No PO</th>
                                        <th>No Split</th>
                                        <th>No Batch</th>
                                        <th>No Sampel</th>
                                        <th>Status</th>
                                        <th>Tgl Dibatalkan</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="item in paginatedData"
                                        :key="item.id"
                                    >
                                        <tr class="master-row">
                                            <td>
                                                <button
                                                    class="btn btn-sm btn-light border toggle-btn"
                                                    :class="{
                                                        expanded:
                                                            expandedRows.includes(
                                                                item.id
                                                            ),
                                                    }"
                                                    @click="
                                                        toggleDetail(item.id)
                                                    "
                                                >
                                                    <span class="toggle-icon"
                                                        >▶</span
                                                    >
                                                </button>
                                            </td>
                                            <td class="fw-bold">
                                                {{ item.Nama_Barang }}
                                            </td>
                                            <td>{{ item.No_PO }}</td>
                                            <td>{{ item.No_Split_PO }}</td>
                                            <td>{{ item.No_Batch }}</td>
                                            <td class="fw-bold">
                                                {{ item.No_Sampel }}
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-danger p-2"
                                                    >{{ item.Status }}</span
                                                >
                                            </td>
                                            <td>{{ item.Tgl_Dibatalkan }}</td>
                                            <td>{{ item.User }}</td>
                                        </tr>

                                        <tr
                                            v-show="
                                                expandedRows.includes(item.id)
                                            "
                                            class="detail-row"
                                        >
                                            <td
                                                colspan="9"
                                                class="text-start p-0 bg-light border-bottom"
                                            >
                                                <div
                                                    class="detail-wrapper bg-white p-4 border rounded shadow-sm detail-scrollable"
                                                >
                                                    <div class="row g-4 mb-4">
                                                        <div class="col-12">
                                                            <div
                                                                class="info-box p-3 border rounded h-100"
                                                            >
                                                                <h6
                                                                    class="fw-bold mb-3 pb-2 border-bottom"
                                                                    style="
                                                                        color: #2c3e50;
                                                                    "
                                                                >
                                                                    🚨 Histori &
                                                                    Alasan
                                                                    Penolakan
                                                                </h6>
                                                                <div
                                                                    class="reason-box p-3 border-start border-danger border-4 rounded bg-danger bg-opacity-10"
                                                                >
                                                                    <div
                                                                        class="text-danger"
                                                                    >
                                                                        <strong
                                                                            >Alasan:</strong
                                                                        >
                                                                    </div>
                                                                    <div
                                                                        class="text-danger mt-1"
                                                                    >
                                                                        {{
                                                                            item.Alasan
                                                                        }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="analysis-section border rounded p-3"
                                                    >
                                                        <h6
                                                            class="fw-bold mb-3 pb-2 border-bottom"
                                                            style="
                                                                color: #2c3e50;
                                                            "
                                                        >
                                                            🔬 Detail Data
                                                            Analisa Terekam
                                                        </h6>

                                                        <div
                                                            v-if="
                                                                !item.Analisa ||
                                                                item.Analisa
                                                                    .length ===
                                                                    0
                                                            "
                                                            class="text-center p-4 text-muted bg-light border rounded border-dashed"
                                                        >
                                                            Belum ada data
                                                            analisa masuk
                                                            sebelum dibatalkan.
                                                        </div>

                                                        <div
                                                            v-else
                                                            v-for="(
                                                                analisa, idx
                                                            ) in item.Analisa"
                                                            :key="idx"
                                                            class="mb-4"
                                                        >
                                                            <div
                                                                class="fw-bold text-dark mb-2 text-uppercase"
                                                                style="
                                                                    font-size: 14px;
                                                                "
                                                            >
                                                                {{
                                                                    analisa.Nama_Analisa
                                                                }}
                                                            </div>
                                                            <div
                                                                class="table-responsive"
                                                            >
                                                                <table
                                                                    class="table table-bordered text-center align-middle mb-0"
                                                                    :style="{
                                                                        width:
                                                                            analisa
                                                                                .Kolom
                                                                                .length <=
                                                                            2
                                                                                ? '50%'
                                                                                : '100%',
                                                                    }"
                                                                >
                                                                    <thead
                                                                        class="table-light"
                                                                    >
                                                                        <tr>
                                                                            <th
                                                                                width="5%"
                                                                            >
                                                                                No
                                                                            </th>
                                                                            <th
                                                                                v-for="(
                                                                                    col,
                                                                                    cIdx
                                                                                ) in analisa.Kolom"
                                                                                :key="
                                                                                    cIdx
                                                                                "
                                                                            >
                                                                                {{
                                                                                    col
                                                                                }}
                                                                            </th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <tr
                                                                            v-for="(
                                                                                row,
                                                                                rIdx
                                                                            ) in analisa.Data"
                                                                            :key="
                                                                                rIdx
                                                                            "
                                                                        >
                                                                            <td>
                                                                                {{
                                                                                    rIdx +
                                                                                    1
                                                                                }}
                                                                            </td>
                                                                            <td
                                                                                v-for="(
                                                                                    col,
                                                                                    cIdx
                                                                                ) in analisa.Kolom"
                                                                                :key="
                                                                                    cIdx
                                                                                "
                                                                            >
                                                                                {{
                                                                                    row[
                                                                                        col
                                                                                    ]
                                                                                }}
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                    <tfoot
                                                                        v-if="
                                                                            analisa.Has_RataRata
                                                                        "
                                                                        style="
                                                                            background-color: #fdf2e9;
                                                                        "
                                                                    >
                                                                        <tr>
                                                                            <td
                                                                                :colspan="
                                                                                    analisa
                                                                                        .Kolom
                                                                                        .length
                                                                                "
                                                                                class="text-end fw-bold"
                                                                            >
                                                                                Rata-Rata
                                                                            </td>
                                                                            <td
                                                                                class="fw-bold"
                                                                            >
                                                                                {{
                                                                                    analisa.RataRata
                                                                                }}
                                                                            </td>
                                                                        </tr>
                                                                    </tfoot>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="align-items-center mt-3 row g-3 text-center text-sm-start"
                            v-if="pagination.totalData > 0"
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
                                            @click.prevent="prevPage"
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
                                            @click.prevent="changePage(page)"
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
                                            >→</a
                                        >
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div
                            v-if="!paginatedData.length"
                            class="d-flex justify-content-center mt-5"
                        >
                            <div class="flex-column align-content-center">
                                <DotLottieVue
                                    style="
                                        height: 100px;
                                        width: 100px;
                                        margin: 0 auto;
                                    "
                                    autoplay
                                    loop
                                    src="/animation/empty2.json"
                                />
                                <p class="text-center mt-3 fw-medium">
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
import { debounce } from "lodash";
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";

export default {
    components: {
        DotLottieVue,
    },
    data() {
        return {
            searchQuery: "",
            expandedRows: [],
            loading: {
                loadingDataList: false,
            },
            pagination: {
                page: 1,
                limit: 10,
                totalPage: 0,
                totalData: 0,
            },
            paginatedData: [],
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
        toggleDetail(id) {
            const index = this.expandedRows.indexOf(id);
            if (index > -1) {
                this.expandedRows.splice(index, 1);
            } else {
                this.expandedRows.push(id);
            }
        },
        async fetchData(page = 1) {
            this.loading.loadingDataList = true;
            try {
                const response = await axios.get(
                    "/api/v1/hasil-trial/dibatalkan/current",
                    {
                        params: {
                            page: page,
                            limit: this.pagination.limit,
                            search: this.searchQuery,
                        },
                    }
                );

                if (response.data.success) {
                    this.paginatedData = response.data.result;
                    this.pagination.page =
                        response.data.pagination.current_page;
                    this.pagination.totalPage =
                        response.data.pagination.total_pages;
                    this.pagination.totalData = response.data.pagination.total;
                } else {
                    this.paginatedData = [];
                }
            } catch (error) {
                console.error("Error fetching data:", error);
                this.paginatedData = [];
            } finally {
                this.loading.loadingDataList = false;
            }
        },
        handleSearch: debounce(function () {
            this.pagination.page = 1;
            this.fetchData(this.pagination.page);
        }, 500),
        nextPage() {
            if (this.pagination.page < this.pagination.totalPage) {
                this.fetchData(this.pagination.page + 1);
            }
        },
        prevPage() {
            if (this.pagination.page > 1) {
                this.fetchData(this.pagination.page - 1);
            }
        },
        changePage(page) {
            if (page !== this.pagination.page) {
                this.fetchData(page);
            }
        },
    },
    mounted() {
        this.fetchData();
    },
};
</script>

<style scoped>
.toggle-btn {
    transition: all 0.3s ease;
}
.toggle-icon {
    display: inline-block;
    transition: transform 0.3s ease;
    font-size: 10px;
    color: #6c757d;
}
.toggle-btn.expanded .toggle-icon {
    transform: rotate(90deg);
}

.border-dashed {
    border-style: dashed !important;
}

.detail-scrollable {
    max-height: 400px;
    overflow-y: auto;
}

.detail-scrollable::-webkit-scrollbar {
    width: 6px;
}
.detail-scrollable::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.detail-scrollable::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.detail-scrollable::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

table.skeleton-table {
    table-layout: fixed;
}
.skeleton-row .skeleton-cell {
    position: relative;
    height: 25px;
    background: #e2e8f0;
    border-radius: 4px;
    margin: 10px auto;
    width: 80%;
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
        rgba(255, 255, 255, 0.5),
        transparent
    );
    animation: shimmer 1.5s infinite;
}
@keyframes shimmer {
    100% {
        left: 100%;
    }
}
</style>
