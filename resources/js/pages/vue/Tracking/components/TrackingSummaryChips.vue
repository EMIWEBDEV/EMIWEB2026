<template>
    <div class="row g-2 mb-3">
        <div
            class="col-12 col-sm-6 col-xl-3"
            v-for="chip in chips"
            :key="chip.key"
        >
            <div class="tracking-summary-chip" :class="chip.className">
                <div class="tracking-summary-chip__label">{{ chip.label }}</div>
                <div class="tracking-summary-chip__value">
                    {{ loading ? "..." : chip.value }}
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        summary: {
            type: Object,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        chips() {
            return [
                {
                    key: "total",
                    label: "Total PRD",
                    value: this.summary.total ?? 0,
                    className: "is-blue",
                },
                {
                    key: "done",
                    label: "Selesai",
                    value: this.summary.done ?? 0,
                    className: "is-green",
                },
                {
                    key: "running",
                    label: "Running",
                    value: this.summary.running ?? 0,
                    className: "is-teal",
                },
                {
                    key: "pending",
                    label: "Pending",
                    value: this.summary.pending ?? 0,
                    className: "is-amber",
                },
            ];
        },
    },
};
</script>

<style scoped>
.tracking-summary-chip {
    height: 100%;
    padding: 0.9rem 1rem;
    border-radius: 0.95rem;
    border: 1px solid #e5eaf2;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
}

.tracking-summary-chip__label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #8b93a5;
    font-weight: 800;
}

.tracking-summary-chip__value {
    margin-top: 0.35rem;
    font-size: 1.55rem;
    line-height: 1;
    font-weight: 800;
    color: #182030;
}

.tracking-summary-chip.is-blue {
    background: linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
}

.tracking-summary-chip.is-green {
    background: linear-gradient(180deg, #f7fff8 0%, #eefdf2 100%);
}

.tracking-summary-chip.is-teal {
    background: linear-gradient(180deg, #f4fffd 0%, #e8fbf8 100%);
}

.tracking-summary-chip.is-amber {
    background: linear-gradient(180deg, #fffdfa 0%, #fff6e7 100%);
}
</style>
