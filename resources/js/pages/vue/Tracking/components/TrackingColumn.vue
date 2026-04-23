<template>
    <div class="col">
        <div class="col-sticky-head">
            <div class="drag-handle-hint" title="Geser untuk mengubah urutan">
                <button
                    class="move-btn"
                    :class="{ invisible: isFirst }"
                    @click.stop="$emit('move-left')"
                    title="Pindah Kiri"
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="12"
                        height="12"
                        stroke="currentColor"
                        fill="none"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <div class="handle-bar"></div>
                <button
                    class="move-btn"
                    :class="{ invisible: isLast }"
                    @click.stop="$emit('move-right')"
                    title="Pindah Kanan"
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="12"
                        height="12"
                        stroke="currentColor"
                        fill="none"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
                <span class="tooltip"
                    >Tahan bar untuk geser. Tap panah pindah urutan.</span
                >
            </div>

            <div class="col-head">
                <div class="col-name-row">
                    <div class="col-name">
                        <div class="indicator" :style="indicatorStyle"></div>
                        {{ column.icon }} {{ column.label }}
                    </div>
                    <!-- <div class="col-count">{{ column.count || 0 }}</div> -->
                    <div
                        class="col-count"
                        v-if="
                            flowMode?.trim().toLowerCase() == 'live_running' &&
                            column?.count > 0
                        "
                    >
                        Sedang Berjalan
                    </div>
                    <div
                        v-else-if="
                            flowMode?.trim().toLowerCase() == 'live_running' &&
                            column?.count <= 0
                        "
                        class="col-count"
                    >
                        Tidak Ada Aktivitas
                    </div>
                </div>
                <div class="col-meta" v-html="column.meta || '—'"></div>
            </div>
        </div>

        <div class="col-body">
            <TrackingCard
                v-for="card in column.cards"
                :key="`${card.recordId}-${card.lane.column_key}`"
                :card="card"
            />
            <div v-if="!column.cards.length" class="empty">
                Tidak ada Aktivitas
            </div>
        </div>
    </div>
</template>

<script>
import TrackingCard from "./TrackingCard.vue";

export default {
    components: {
        TrackingCard,
    },
    props: {
        column: {
            type: Object,
            required: true,
        },
        isFirst: {
            type: Boolean,
            default: false,
        },
        isLast: {
            type: Boolean,
            default: false,
        },
        flowMode: {
            type: String,
            default: "full_process",
        },
    },
    computed: {
        indicatorStyle() {
            return {
                background: this.column.indicator || "#38bdf8",
            };
        },
    },
};
</script>

<style scoped>
.col {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e8eaf0;
    overflow: visible;
    display: flex;
    flex-direction: column;
    position: relative;
    align-self: start;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.col-sticky-head {
    position: -webkit-sticky;
    position: sticky;
    top: 0;
    z-index: 40;
    background: #fff;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

.drag-handle-hint {
    height: 24px;
    background: #ffffff;
    border-bottom: 1px solid #fbfcfd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 4px;
    position: relative;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}

.move-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 4px;
    color: #9399aa;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: background 0.2s, color 0.2s;
}

.move-btn.invisible {
    visibility: hidden;
    pointer-events: none;
}

.move-btn:hover,
.move-btn:active {
    background: #eef2ff;
    color: #4f46e5;
}

.handle-bar {
    width: 36px;
    height: 4px;
    background: #e2e4ed;
    border-radius: 4px;
    transition: background 0.2s;
    margin: 0 auto;
}

.col:hover .handle-bar {
    background: #c3c8d8;
}

.drag-handle-hint:hover .handle-bar {
    background: #818cf8;
}

.tooltip {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%) translateY(2px);
    background: #1e2330;
    color: #fff;
    font-size: 10px;
    font-weight: 500;
    padding: 5px 9px;
    border-radius: 5px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.2s ease-in-out;
    z-index: 100;
}

.tooltip::after {
    content: "";
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 4px;
    border-style: solid;
    border-color: transparent transparent #1e2330 transparent;
}

.drag-handle-hint:hover .tooltip {
    opacity: 1;
    transform: translateX(-50%) translateY(6px);
}

.col-head {
    padding: 12px 14px 10px;
    border-bottom: 1px solid #f0f1f5;
    background: #fff;
}

.col-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 3px;
}

.col-name {
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
}

.indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.col-count {
    font-size: 10px;
    color: #9399aa;
    background: #f5f6fa;
    padding: 2px 7px;
    border-radius: 99px;
}

.col-meta {
    font-size: 11px;
    color: #9399aa;
}

:deep(.col-meta b) {
    color: #5a607a;
    font-weight: 600;
}

.col-body {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    background: #f9fafc;
    flex: 1;
    min-height: 60px;
}

.empty {
    border: 1.5px dashed #dde0ea;
    border-radius: 7px;
    padding: 16px;
    text-align: center;
    font-size: 11px;
    color: #c0c5d0;
}
</style>
