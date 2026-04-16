<template>
    <div>
        <!-- Floating Action Button for Mobile -->
        <button class="mobile-filter-fab" @click="isMobileOpen = true">
            <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polygon
                    points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"
                ></polygon>
            </svg>
            Filter
        </button>

        <!-- Backdrop for off-canvas -->
        <div
            class="filter-backdrop"
            :class="{ 'is-open': isMobileOpen }"
            @click="isMobileOpen = false"
        ></div>

        <!-- Filter Bar -->
        <div class="filter-bar" :class="{ 'is-open': isMobileOpen }">
            <div class="filter-header-mobile">
                <span class="f-title">Filter / Summary</span>
                <button class="f-close" @click="isMobileOpen = false">
                    &times;
                </button>
            </div>

            <span class="filter-label hidden-mobile">Filter</span>

            <div class="view-toggle">
                <span class="view-label hidden-mobile">Tampilan</span>
                <button
                    v-for="mode in flowFilterModes"
                    :key="mode.value"
                    type="button"
                    class="view-btn"
                    :class="{ 'is-active': flowFilter === mode.value }"
                    :disabled="loading"
                    @click="$emit('set-flow-filter', mode.value)"
                >
                    {{ mode.label }}
                </button>
            </div>

            <div class="filter-group">
                <span class="filter-text">Dari</span>
                <input
                    type="date"
                    class="finput"
                    :value="filters.from"
                    :disabled="loading"
                    @input="emitFilter('from', $event.target.value)"
                />
                <span class="filter-text">s/d</span>
                <input
                    type="date"
                    class="finput"
                    :value="filters.to"
                    :disabled="loading"
                    @input="emitFilter('to', $event.target.value)"
                />
            </div>

            <div class="filter-sep hidden-mobile"></div>

            <select
                class="finput f-select-prd"
                :value="filters.prd"
                :disabled="loading"
                @change="emitFilter('prd', $event.target.value)"
            >
                <option value="">Semua Nomor PRD</option>
                <option v-for="item in prdOptions" :key="item" :value="item">
                    {{ item }}
                </option>
            </select>

            <select
                class="finput f-select-prd"
                :value="filters.no_split"
                :disabled="loading"
                @change="emitFilter('no_split', $event.target.value)"
            >
                <option value="">Semua Nomor Split</option>
                <option v-for="item in splitOptions" :key="item" :value="item">
                    {{ item }}
                </option>
            </select>

            <select
                class="finput f-select-line"
                :value="filters.line"
                :disabled="loading"
                @change="emitFilter('line', $event.target.value)"
            >
                <option value="">Semua Routing/Line</option>
                <option v-for="item in lineOptions" :key="item" :value="item">
                    {{ item }}
                </option>
            </select>

            <select
                class="finput f-select-batch"
                :value="filters.batch"
                :disabled="loading"
                @change="emitFilter('batch', $event.target.value)"
            >
                <option value="">Semua Batch</option>
                <option v-for="item in batchOptions" :key="item" :value="item">
                    {{ item }}
                </option>
            </select>

            <div class="filter-sep hidden-mobile"></div>

            <div class="filter-group-btn">
                <button
                    v-for="item in statusOptions"
                    :key="item.value"
                    type="button"
                    class="stbtn"
                    :class="statusButtonClass(item.value)"
                    :disabled="loading"
                    @click="$emit('set-status', item.value)"
                >
                    {{
                        item.value === "running"
                            ? "● "
                            : item.value === "selesai"
                            ? "✓ "
                            : ""
                    }}{{ item.label }}
                </button>
            </div>

            <button
                class="fbtn fbtn-apply"
                :disabled="loading"
                @click="applyAndClose"
            >
                <span v-if="loading" class="btn-spinner"></span>
                {{ loading ? "Memuat..." : "Terapkan" }}
            </button>
            <button
                class="fbtn fbtn-reset"
                :disabled="loading"
                @click="resetAndClose"
            >
                Reset
            </button>

            <!-- Summary Chips -->
            <div class="summary-chips" v-if="summary">
                <div class="chip chip-blue">
                    {{ totalChipLabel }}: {{ summary.total ?? 0 }}
                </div>
                <div class="chip chip-teal">
                    Selesai: {{ summary.done ?? 0 }}
                </div>
                <div class="chip chip-green">
                    Running: {{ summary.running ?? 0 }}
                </div>
                <!-- <div class="chip chip-amber">Pending: {{ summary.pending ?? 0 }}</div> -->
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        filters: { type: Object, required: true },
        flowFilter: { type: String, default: "full_process" },
        runningScope: { type: String, default: "record" },
        flowFilterModes: { type: Array, default: () => [] },
        prdOptions: { type: Array, default: () => [] },
        splitOptions: { type: Array, default: () => [] },
        lineOptions: { type: Array, default: () => [] },
        batchOptions: { type: Array, default: () => [] },
        statusOptions: { type: Array, default: () => [] },
        activeStatus: { type: String, default: "all" },
        loading: { type: Boolean, default: false },
        summary: { type: Object, required: false },
    },
    data() {
        return {
            isMobileOpen: false,
        };
    },
    emits: ["update-filter", "set-status", "apply", "reset", "set-flow-filter"],
    computed: {
        totalChipLabel() {
            if (this.flowFilter !== "live_running") {
                return "Total Record";
            }

            return "Total Mesin Running";
        },
    },
    methods: {
        emitFilter(field, value) {
            this.$emit("update-filter", { field, value });
        },
        statusButtonClass(value) {
            return {
                "active-all": value === "all" && this.activeStatus === "all",
                "active-running":
                    value === "running" && this.activeStatus === "running",
                "active-selesai":
                    value === "selesai" && this.activeStatus === "selesai",
            };
        },
        applyAndClose() {
            this.$emit("apply");
            this.isMobileOpen = false;
        },
        resetAndClose() {
            this.$emit("reset");
            this.isMobileOpen = false;
        },
    },
};
</script>

<style scoped>
/* BASE STYLES */
.filter-bar {
    background: #fff;
    border-bottom: 1px solid #e8eaf0;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.mobile-filter-fab {
    display: none;
}
.filter-backdrop {
    display: none;
}
.filter-header-mobile {
    display: none;
}

.filter-label {
    font-size: 11px;
    font-weight: 600;
    color: #9399aa;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.view-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px;
    background: #f8fafc;
    border: 1px solid #e8eaf0;
    border-radius: 999px;
}

.view-label {
    font-size: 11px;
    font-weight: 600;
    color: #9399aa;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    padding-left: 4px;
}

.view-btn {
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 999px;
    cursor: pointer;
    transition: all 0.18s ease;
}

.view-btn.is-active {
    background: #4f46e5;
    color: #fff;
    box-shadow: 0 4px 10px rgba(79, 70, 229, 0.22);
}

.view-btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-text {
    font-size: 11px;
    color: #5a607a;
    font-weight: 500;
}

.filter-sep {
    width: 1px;
    height: 22px;
    background: #e8eaf0;
}

.finput {
    border: 1px solid #e8eaf0;
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 12px;
    font-family: "Inter", sans-serif;
    color: #1e2330;
    background: #f9fafc;
    outline: none;
    transition: border-color 0.15s;
}

.finput:focus {
    border-color: #a5b4fc;
    background: #fff;
}

select.finput {
    cursor: pointer;
    padding-right: 26px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%239399aa'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
}

.f-select-prd {
    min-width: 170px;
}
.f-select-batch {
    min-width: 120px;
}

.filter-group-btn {
    display: flex;
    gap: 5px;
}

.stbtn {
    padding: 5px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    font-family: "Inter", sans-serif;
    cursor: pointer;
    border: 1.5px solid transparent;
    transition: all 0.15s;
    background: #f1f2f6;
    color: #5a607a;
}

.stbtn:hover {
    background: #e2e4ed;
}
.stbtn.active-all {
    background: #eff6ff;
    color: #2563eb;
    border-color: #bfdbfe;
}
.stbtn.active-selesai {
    background: #f0fdf4;
    color: #16a34a;
    border-color: #bbf7d0;
}
.stbtn.active-running {
    background: #ecfdf5;
    color: #059669;
    border-color: #6ee7b7;
}

.fbtn {
    padding: 5px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    font-family: "Inter", sans-serif;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.fbtn:disabled,
.finput:disabled,
.stbtn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-spinner {
    width: 12px;
    height: 12px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: btn-spin 0.8s linear infinite;
}

@keyframes btn-spin {
    to {
        transform: rotate(360deg);
    }
}

.fbtn-apply {
    background: #4f46e5;
    color: #fff;
}
.fbtn-apply:hover {
    background: #4338ca;
}
.fbtn-reset {
    background: #f1f2f6;
    color: #5a607a;
}
.fbtn-reset:hover {
    background: #e2e4ed;
}

.summary-chips {
    display: flex;
    gap: 7px;
    margin-left: auto;
    flex-wrap: wrap;
}

.chip {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 99px;
    white-space: nowrap;
}

.chip-blue {
    background: #eff6ff;
    color: #2563eb;
}
.chip-green {
    background: #ecfdf5;
    color: #059669;
}
.chip-teal {
    background: #f0fdf4;
    color: #16a34a;
}
.chip-amber {
    background: #fffbeb;
    color: #d97706;
}

@media (max-width: 992px) {
    .summary-chips {
        margin-left: 0;
        width: 100%;
        justify-content: flex-start;
        padding-top: 5px;
        border-top: 1px dashed #e8eaf0;
    }
}

/* OFF-CANVAS MOBILE STYLING */
@media (max-width: 576px) {
    .mobile-filter-fab {
        padding: 7px 12px;
        display: flex;
        align-items: center;
        gap: 6px;
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 900;
        background: #4f46e5;
        color: #fff;
        border: none;
        border-radius: 99px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
        cursor: pointer;
        transition: transform 0.2s, background 0.2s;
    }

    .mobile-filter-fab:active {
        transform: scale(0.95);
        background: #4338ca;
    }

    .filter-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(2px);
        z-index: 998;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }

    .filter-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
    }

    .filter-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 999;
        flex-direction: column;
        align-items: stretch;
        padding: 24px 24px;
        background: #fff;
        border-top-left-radius: 24px;
        border-top-right-radius: 24px;
        transform: translateY(110%);
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
        gap: 14px;
        border-bottom: none;
    }

    .filter-bar.is-open {
        transform: translateY(0);
    }

    .filter-header-mobile {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .f-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e2330;
    }

    .f-close {
        background: #f1f2f6;
        border: none;
        color: #5a607a;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        font-size: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .hidden-mobile {
        display: none;
    }

    .finput,
    .f-select-prd,
    .f-select-batch {
        width: 100% !important;
        min-width: 100% !important;
        padding: 10px 14px;
        font-size: 14px;
    }

    .filter-group {
        display: grid;
        grid-template-columns: auto 1fr auto 1fr;
        gap: 8px;
        align-items: center;
    }

    .filter-group-btn {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
        width: 100%;
        margin-top: 5px;
    }

    .view-toggle {
        width: 100%;
        justify-content: space-between;
        border-radius: 18px;
        padding: 8px;
    }

    .view-btn {
        flex: 1;
        padding: 10px 0;
    }

    .stbtn {
        width: 100%;
        padding: 10px 0;
        text-align: center;
        font-size: 13px;
    }

    .fbtn-apply,
    .fbtn-reset {
        width: 100%;
        padding: 12px 0;
        font-size: 14px;
    }

    .summary-chips {
        margin-top: 10px;
        border-top: 1px solid #e8eaf0;
        padding-top: 16px;
        gap: 10px;
        justify-content: center;
    }

    .chip {
        padding: 6px 14px;
        font-size: 12px;
    }
}
</style>
