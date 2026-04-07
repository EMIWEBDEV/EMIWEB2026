<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="mb-4 text-center text-md-start">
                    <h1
                        class="text-2xl md:text-3xl font-bold text-primary"
                        style="color: #2c3e50"
                    >
                        Status Sampel Trial
                    </h1>
                    <p class="text-sm md:text-base text-muted">
                        Daftar Status Sampel Trial PT. Evo Manufacturing
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
                                        <th>Nama Mesin</th>
                                        <th>Nama Barang</th>
                                        <th>No PO</th>
                                        <th>No Split</th>
                                        <th>No Batch</th>
                                        <th>No Sampel</th>
                                        <th>Status</th>
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
                                                {{ item.Nama_Mesin }}
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
                                                    class="badge p-2"
                                                    :class="
                                                        getBadgeClass(
                                                            item.Status_Sampel ||
                                                                item.Status
                                                        )
                                                    "
                                                >
                                                    {{
                                                        item.Status_Sampel ||
                                                        item.Status
                                                    }}
                                                </span>
                                            </td>

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
                                                    <div
                                                        v-if="item.Alasan"
                                                        class="row g-4 mb-4"
                                                    >
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
                                                        class="analysis-section border rounded p-3 bg-light"
                                                    >
                                                        <h6
                                                            class="fw-bold mb-3 pb-2 border-bottom d-flex justify-content-between align-items-center"
                                                            style="
                                                                color: #2c3e50;
                                                            "
                                                        >
                                                            <span
                                                                >🔬 Status per
                                                                Jenis
                                                                Analisa</span
                                                            >
                                                            <span
                                                                v-if="
                                                                    item.Analisa
                                                                "
                                                                class="badge bg-secondary rounded-pill"
                                                                style="
                                                                    font-size: 12px;
                                                                "
                                                            >
                                                                Total:
                                                                {{
                                                                    item.Analisa
                                                                        .length
                                                                }}
                                                                Analisa
                                                            </span>
                                                        </h6>

                                                        <div
                                                            v-if="
                                                                !item.Analisa ||
                                                                item.Analisa
                                                                    .length ===
                                                                    0
                                                            "
                                                            class="text-center p-4 text-muted bg-white border rounded border-dashed"
                                                        >
                                                            Belum ada data
                                                            analisa terekam.
                                                        </div>
                                                        <div
                                                            v-else
                                                            class="row g-3"
                                                        >
                                                            <div
                                                                class="col-lg-4 col-md-6 col-sm-12"
                                                                v-for="(
                                                                    analisa, idx
                                                                ) in item.Analisa"
                                                                :key="idx"
                                                            >
                                                                <div
                                                                    class="card h-100 shadow-sm"
                                                                    style="
                                                                        border-radius: 12px;
                                                                        border: none;
                                                                        border-left: 4px
                                                                            solid
                                                                            #3a0ca3;
                                                                        background: linear-gradient(
                                                                            to
                                                                                right,
                                                                            rgba(
                                                                                58,
                                                                                12,
                                                                                163,
                                                                                0.05
                                                                            ),
                                                                            white
                                                                        );
                                                                        transition: transform
                                                                            0.2s;
                                                                    "
                                                                >
                                                                    <div
                                                                        class="card-body d-flex align-items-center p-3"
                                                                    >
                                                                        <div
                                                                            class="d-flex justify-content-center align-items-center flex-shrink-0 shadow-sm"
                                                                            style="
                                                                                width: 52px;
                                                                                height: 52px;
                                                                                background-color: #3a0ca3;
                                                                                border-radius: 12px;
                                                                            "
                                                                        >
                                                                            <svg
                                                                                xmlns="http://www.w3.org/2000/svg"
                                                                                width="24"
                                                                                height="24"
                                                                                viewBox="0 0 24 24"
                                                                                fill="white"
                                                                            >
                                                                                <path
                                                                                    d="M21.57 18.06L14.43 5.4C14.17 4.94 14 4.43 14 3.9V3h1c.55 0 1-.45 1-1s-.45-1-1-1H9c-.55 0-1 .45-1 1s.45 1 1 1h1v.9c0 .53-.17 1.04-.43 1.5l-7.14 12.66c-.37.66-.41 1.46-.1 2.15.31.69 1 1.13 1.77 1.13h14.8c.77 0 1.46-.44 1.77-1.13.31-.69.27-1.49-.1-2.15z"
                                                                                ></path>
                                                                            </svg>
                                                                        </div>

                                                                        <div
                                                                            class="ms-3 text-start w-100 overflow-hidden"
                                                                        >
                                                                            <span
                                                                                class="badge mb-1 px-2 py-1"
                                                                                :class="
                                                                                    getBadgeClass(
                                                                                        analisa.Ket_Status
                                                                                    )
                                                                                "
                                                                                style="
                                                                                    font-size: 11px;
                                                                                    font-weight: 600;
                                                                                    border-radius: 6px;
                                                                                    letter-spacing: 0.2px;
                                                                                "
                                                                            >
                                                                                {{
                                                                                    analisa.Ket_Status
                                                                                }}
                                                                            </span>

                                                                            <h6
                                                                                class="fw-bolder text-dark mb-0 text-truncate"
                                                                                style="
                                                                                    font-size: 14px;
                                                                                    letter-spacing: -0.2px;
                                                                                "
                                                                                :title="
                                                                                    analisa.Nama_Analisa
                                                                                "
                                                                            >
                                                                                {{
                                                                                    analisa.Nama_Analisa
                                                                                }}
                                                                            </h6>
                                                                        </div>
                                                                    </div>
                                                                </div>
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
        // Fungsi tambahan untuk mewarnai badge status secara dinamis
        getBadgeClass(status) {
            if (!status) return "bg-secondary";
            const s = status.toLowerCase();
            if (s.includes("belum")) return "bg-secondary";
            if (s.includes("sedang")) return "bg-primary";
            if (s.includes("selesai")) return "bg-success";
            if (s.includes("batal") || s.includes("tolak")) return "bg-danger";
            if (s.includes("menunggu")) return "bg-warning text-dark";
            return "bg-info text-dark";
        },
        async fetchData(page = 1) {
            this.loading.loadingDataList = true;
            try {
                const response = await axios.get(
                    "/api/v1/formulator/status-data/sampel/current",
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
