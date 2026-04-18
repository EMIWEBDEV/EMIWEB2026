<template>
    <div class="board-wrapper">
        <!-- Scroll Indicators -->
        <button
            type="button"
            class="scroll-indicator indicator-left"
            :class="{ 'is-visible': canScrollLeft }"
            @click="scrollBoard('left')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <button
            type="button"
            class="scroll-indicator indicator-right"
            :class="{ 'is-visible': canScrollRight }"
            @click="scrollBoard('right')"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </button>

        <div
            class="board"
            :class="{ 'is-loading': loading }"
            :style="boardStyle"
            ref="boardScroll"
            @scroll="checkScroll"
        >
            <!-- Loading Overlay -->
            <div v-if="loading" class="board-loading-overlay">
                <div class="spinner-large"></div>
                <span>Memuat Data...</span>
            </div>

            <div v-if="!loading && !columns.length" class="no-result">
                Tidak ada data
            </div>

            <template v-if="localColumns.length">
                <TrackingColumn
                    v-for="(column, index) in localColumns"
                    :key="column.key"
                    :column="column"
                    :is-first="index === 0"
                    :is-last="index === localColumns.length - 1"
                    :flow-mode="flowMode"
                    draggable="true"
                    class="sortable-column"
                    :class="{ 'is-dragging': draggedColumnIndex === index }"
                    @dragstart="onDragStart(index, $event)"
                    @dragover.prevent
                    @dragenter.prevent="onDragEnter(index)"
                    @dragend="onDragEnd($event)"
                    @move-left="moveColumn(index, -1)"
                    @move-right="moveColumn(index, 1)"
                />
            </template>
        </div>
    </div>
</template>

<script>
import TrackingColumn from "./TrackingColumn.vue";

export default {
    components: {
        TrackingColumn,
    },
    props: {
        columns: {
            type: Array,
            default: () => [],
        },
        loading: {
            type: Boolean,
            default: false,
        },
        flowMode: {
            type: String,
            default: "full_process",
        },
    },
    data() {
        return {
            canScrollLeft: false,
            canScrollRight: false,
            localColumns: [],
            draggedColumnIndex: null,
        };
    },
    watch: {
        columns: {
            immediate: true,
            handler(newVal) {
                this.localColumns = [...newVal];
            },
        },
    },
    computed: {
        boardStyle() {
            const columnCount = Math.max(this.localColumns.length, 1);

            return {
                gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))`,
            };
        },
    },
    mounted() {
        this.checkScroll();
        window.addEventListener("resize", this.checkScroll);
    },
    updated() {
        // Use nextTick to ensure the DOM is painted before checking structural width
        this.$nextTick(() => {
            this.checkScroll();
        });
    },
    beforeUnmount() {
        window.removeEventListener("resize", this.checkScroll);
    },
    methods: {
        checkScroll() {
            const el = this.$refs.boardScroll;
            if (!el) return;
            this.canScrollLeft = el.scrollLeft > 5;
            this.canScrollRight =
                el.scrollLeft + el.clientWidth < el.scrollWidth - 5;
        },
        scrollBoard(direction) {
            const el = this.$refs.boardScroll;
            if (!el) return;
            const amount = el.clientWidth * 0.7; // scroll by 70% of screen
            el.scrollBy({
                left: direction === "left" ? -amount : amount,
                behavior: "smooth",
            });
        },
        onDragStart(index, event) {
            this.draggedColumnIndex = index;
            event.dataTransfer.effectAllowed = "move";
            // Drag transparency effect is handled via css class 'is-dragging'
            // which dynamically applies opacity: 0.4
        },
        onDragEnter(targetIndex) {
            if (
                this.draggedColumnIndex === null ||
                this.draggedColumnIndex === targetIndex
            )
                return;

            const draggedItem = this.localColumns[this.draggedColumnIndex];
            this.localColumns.splice(this.draggedColumnIndex, 1);
            this.localColumns.splice(targetIndex, 0, draggedItem);
            this.draggedColumnIndex = targetIndex;
        },
        onDragEnd(event) {
            this.draggedColumnIndex = null;
        },
        moveColumn(index, direction) {
            const targetIndex = index + direction;
            if (targetIndex < 0 || targetIndex >= this.localColumns.length)
                return;

            const item = this.localColumns[index];
            this.localColumns.splice(index, 1);
            this.localColumns.splice(targetIndex, 0, item);
        },
    },
};
</script>

<style scoped>
.board-wrapper {
    position: relative;
    z-index: 50;
    width: 100%;
}

.board {
    padding: 16px 24px 24px;
    display: grid;
    gap: 12px;
    align-items: start;
}

.no-result {
    text-align: center;
    padding: 20px;
    font-size: 12px;
    color: #9399aa;
    font-style: italic;
    grid-column: 1 / -1;
}

@media (max-width: 1200px) {
    .board {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 24px; /* Room for scrollbar */
        align-items: flex-start;
    }

    .board > * {
        flex: 0 0 calc(33.333% - 8px);
        min-width: 280px;
    }
}

@media (max-width: 768px) {
    .board > * {
        flex: 0 0 calc(50% - 6px);
        min-width: 260px;
    }
}

@media (max-width: 576px) {
    .board {
        padding: 16px 16px 16px;
        overflow-y: visible;
    }
    .board > * {
        flex: 0 0 85%;
        min-width: 240px;
    }
}

/* Loading States */
.board.is-loading {
    min-height: 250px;
}

/* Draggable Overrides */
.sortable-column {
    cursor: grab;
    transition: transform 0.2s cubic-bezier(0.2, 0, 0, 1), opacity 0.2s;
}

.sortable-column:active {
    cursor: grabbing;
}

.sortable-column.is-dragging {
    opacity: 0.3;
    transform: scale(0.98);
}

.board-loading-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(245, 246, 250, 0.6);
    backdrop-filter: blur(2px);
    z-index: 10;
    color: #4f46e5;
    font-size: 13px;
    font-weight: 600;
    border-radius: 12px;
}

.spinner-large {
    width: 36px;
    height: 36px;
    border: 3px solid rgba(79, 70, 229, 0.2);
    border-radius: 50%;
    border-top-color: #4f46e5;
    animation: spin-board 1s linear infinite;
    margin-bottom: 12px;
}

@keyframes spin-board {
    to {
        transform: rotate(360deg);
    }
}

/* Scroll Indicators Styling */
.scroll-indicator {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #ffffff;
    border: 1px solid #dce1eb;
    color: #4f46e5;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    cursor: pointer;
    opacity: 0;
    pointer-events: none;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.scroll-indicator svg {
    width: 28px;
    height: 28px;
}

.scroll-indicator.is-visible {
    opacity: 0.7;
    pointer-events: auto;
}

.scroll-indicator:hover {
    background: #f8fafc;
    color: #4338ca;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.2);
    transform: translateY(-50%) scale(1.08);
}

.scroll-indicator:active {
    transform: translateY(-50%) scale(0.95);
}

.indicator-left {
    left: 20px;
    animation: intermittentBounceLeft 4s infinite;
}

.indicator-right {
    right: 20px;
    animation: intermittentBounceRight 4s infinite;
}

/* Animasi jeda, bergerak hanya 10% dari siklus (mirip debaran yg jarang) */
@keyframes intermittentBounceLeft {
    0%,
    85%,
    100% {
        transform: translate(0, -50%);
    }
    90%,
    95% {
        transform: translate(-8px, -50%);
    }
    92.5%,
    97.5% {
        transform: translate(0, -50%);
    }
}

@keyframes intermittentBounceRight {
    0%,
    85%,
    100% {
        transform: translate(0, -50%);
    }
    90%,
    95% {
        transform: translate(8px, -50%);
    }
    92.5%,
    97.5% {
        transform: translate(0, -50%);
    }
}

@media (max-width: 992px) {
    .scroll-indicator {
        width: 50px;
        height: 50px;
    }
    .scroll-indicator svg {
        width: 24px;
        height: 24px;
    }
    .indicator-left {
        left: 15px;
    }
    .indicator-right {
        right: 15px;
    }
}

@media (max-width: 576px) {
    .scroll-indicator {
        width: 40px;
        height: 40px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
    }
    .scroll-indicator svg {
        width: 18px;
        height: 18px;
    }
    .indicator-left {
        left: 10px;
    }
    .indicator-right {
        right: 10px;
    }
}
</style>
