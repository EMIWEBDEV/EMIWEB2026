<template>
    <article class="card" :class="cardClass" :style="cardStyle">
        <div class="card-head">
            <div class="prd">{{ card.prd }}</div>
            <div class="badge" :class="badgeClass">{{ badgeText }}</div>
        </div>

        <div class="batch-lbl">{{ card.batch }}</div>

        <!-- ✅ GLOBAL UNIT STYLE -->
        <div :class="unitStyle.row">
            <div :class="unitStyle.num">
                {{ quantityLabel }}
            </div>
            <div :class="unitStyle.lbl">
                {{ unitLabel }}
            </div>
        </div>

        <div class="suhu-row">
            <div class="sl">Suhu Rata-rata</div>
            <div class="sv">{{ temperatureLabel }}</div>
        </div>

        <div class="sep"></div>

        <div class="times">
            <div class="ti">
                <div class="tl">Jam Awal</div>
                <div class="tv time-compact">{{ lane.jam_awal || "—:——" }}</div>
            </div>
            <div class="arrow">→</div>
            <div class="ti">
                <div class="tl">Jam Akhir</div>
                <div class="tv time-compact">
                    {{ lane.jam_akhir || "—:——" }}
                </div>
            </div>
        </div>

        <div class="dur">
            <div class="dl">Total Waktu</div>
            <div :class="durationClass">{{ durationLabel }}</div>
        </div>
    </article>
</template>

<script>
export default {
    props: {
        card: {
            type: Object,
            required: true,
        },
    },
    computed: {
        lane() {
            return this.card.lane || {};
        },

        // 🔥 GLOBAL UNIT STYLE
        unitStyle() {
            const unit = (this.lane.unit || "").toLowerCase();

            const styles = {
                pcs: {
                    row: "metric-row",
                    num: "mnum",
                    lbl: "mlbl",
                },
                kg: {
                    row: "metric-row",
                    num: "mnum",
                    lbl: "mlbl",
                },
                liter: {
                    row: "metric-row",
                    num: "mnum",
                    lbl: "mlbl",
                },
                box: {
                    row: "box-row",
                    num: "bnum",
                    lbl: "blbl",
                },
                default: {
                    row: "box-row",
                    num: "bnum",
                    lbl: "blbl",
                },
            };

            return styles[unit] || styles.default;
        },

        progressState() {
            return (
                this.lane.progress_state || this.lane.status || "not_started"
            );
        },

        cardClass() {
            return {
                completed: this.card.fullDone,
                "not-started": this.progressState === "not_started",
            };
        },

        cardStyle() {
            const accent = this.card.column?.indicator || "#38bdf8";

            return {
                borderTop: this.card.fullDone
                    ? "3px solid #22c55e"
                    : this.progressState === "not_started"
                    ? "3px solid #94a3b8"
                    : `3px solid ${accent}`,
            };
        },

        // 🔥 UNIT LABEL GLOBAL
        unitLabel() {
            return (this.lane.unit || "box").toLowerCase();
        },

        quantityLabel() {
            if (this.lane.qty === null || this.lane.qty === undefined) {
                return "—";
            }

            const unit = (this.lane.unit || "").toLowerCase();

            // metric → pakai format angka
            if (["pcs", "kg", "liter"].includes(unit)) {
                return new Intl.NumberFormat("id-ID").format(this.lane.qty);
            }

            return this.lane.qty;
        },

        temperatureLabel() {
            if (this.lane.suhu === null || this.lane.suhu === undefined) {
                return "—";
            }

            return `${this.lane.suhu}°C`;
        },

        badgeText() {
            if (this.progressState === "not_started") {
                return "Belum Mulai";
            }

            if (this.card.fullDone) {
                return "Selesai";
            }

            if (this.lane.status === "run") {
                return "Running";
            }

            if (this.lane.status === "done") {
                return "Selesai";
            }

            return "Pending";
        },

        badgeClass() {
            if (this.progressState === "not_started") {
                return "b-not-started";
            }

            if (this.card.fullDone) {
                return "b-full";
            }

            if (this.lane.status === "run") {
                return "b-run";
            }

            if (this.lane.status === "done") {
                return "b-done";
            }

            return "b-wait";
        },

        durationLabel() {
            return this.calculateDuration(
                this.lane.jam_awal,
                this.lane.jam_akhir
            );
        },

        durationClass() {
            if (this.progressState === "not_started") {
                return "dv dv-not-started";
            }

            if (this.card.fullDone) {
                return "dv";
            }

            if (this.lane.status === "run") {
                return "dv dv-run";
            }

            if (this.lane.status === "done") {
                return "dv dv-done";
            }

            return "dv dv-wait";
        },
    },

    methods: {
        calculateDuration(startTime, endTime) {
            if (
                !startTime ||
                !endTime ||
                startTime === "—:——" ||
                endTime === "—:——"
            ) {
                return "—";
            }

            const startParts = startTime.split(" ");
            const endParts = endTime.split(" ");

            if (startParts.length < 2 || endParts.length < 2) {
                return "—";
            }

            const [startHour, startMinute] = startParts[1]
                .split(":")
                .map(Number);
            const [endHour, endMinute] = endParts[1].split(":").map(Number);

            let totalMinutes =
                endHour * 60 + endMinute - (startHour * 60 + startMinute);

            if (totalMinutes < 0) totalMinutes += 1440;

            const hours = Math.floor(totalMinutes / 60);
            const minutes = String(totalMinutes % 60).padStart(2, "0");

            return `${hours}j ${minutes}m`;
        },
    },
};
</script>
<style scoped>
.card {
    background: #fff;
    border-radius: 7px;
    border: 1px solid #e8eaf0;
    padding: 11px 12px;
    transition: box-shadow 0.15s;
    position: relative;
    overflow: hidden;
}

.card:hover {
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
}

.card.completed {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.card.not-started {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.card.completed :deep(.sep) {
    background: #d1fae5;
}

.card.completed :deep(.dur) {
    background: #dcfce7;
}

.card.completed :deep(.mnum),
.card.completed :deep(.bnum) {
    color: #15803d;
}

.card.completed :deep(.tv) {
    color: #166534;
}

.card.completed :deep(.dv) {
    color: #15803d !important;
}

.card.completed :deep(.prd) {
    color: #166534;
}

.card.completed :deep(.batch-lbl) {
    color: #4ade80;
}

.card.not-started :deep(.sep) {
    background: #e2e8f0;
}

.card.not-started :deep(.dur) {
    background: #f8fafc;
}

.card.not-started :deep(.mnum),
.card.not-started :deep(.bnum),
.card.not-started :deep(.tv),
.card.not-started :deep(.prd),
.card.not-started :deep(.sv) {
    color: #64748b;
}

.card.not-started :deep(.batch-lbl),
.card.not-started :deep(.sl) {
    color: #94a3b8;
}

.card.completed :deep(.suhu-row) {
    background: #dcfce7;
    border-color: #bbf7d0;
}

.card.completed :deep(.sl) {
    color: #4ade80;
}

.card.completed :deep(.sv) {
    color: #15803d;
}

.card-head,
.batch-lbl,
.box-row,
.metric-row,
.suhu-row,
.sep,
.times,
.dur {
    position: relative;
    z-index: 1;
}

.card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
}

.prd {
    font-size: 11px;
    font-weight: 700;
}

.batch-lbl {
    font-size: 10px;
    color: #9399aa;
    margin-bottom: 8px;
}

.badge {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 7px;
    border-radius: 99px;
}

.b-run {
    background: #dcfce7;
    color: #16a34a;
}

.b-done {
    background: #f1f5f9;
    color: #64748b;
}

.b-wait {
    background: #fffbeb;
    color: #d97706;
}

.b-not-started {
    background: #e2e8f0;
    color: #475569;
}

.b-full {
    background: #22c55e;
    color: #fff;
}

.box-row,
.metric-row {
    display: flex;
    align-items: baseline;
    gap: 3px;
    margin-bottom: 8px;
}

.bnum,
.mnum {
    font-size: 22px;
    font-weight: 700;
    line-height: 1;
}

.blbl,
.mlbl {
    font-size: 10px;
    color: #9399aa;
}

.suhu-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f5f6fa;
    border: 1px solid #eaecf2;
    border-radius: 5px;
    padding: 4px 9px;
    margin-bottom: 7px;
}

.flow-row {
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    padding: 5px 9px 6px;
    margin-bottom: 7px;
    min-height: 40px;
}

.fl {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    color: #94a3b8;
}

.fv {
    font-size: 10px;
    line-height: 1.35;
    color: #334155;
    word-break: break-word;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.fv-empty {
    color: #94a3b8;
}

.sl {
    font-size: 10px;
    color: #9399aa;
}

.sv {
    font-size: 12px;
    font-weight: 700;
    color: #1e2330;
}

.sep {
    height: 1px;
    background: #f0f1f5;
    margin-bottom: 8px;
}

.times {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 7px;
    flex-wrap: wrap;
}

.ti {
    flex: 1;
}

.tl {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #b0b6c3;
    margin-bottom: 2px;
}

.tv {
    font-size: 13px;
    font-weight: 700;
}

.tv.time-compact {
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
}

.arrow {
    color: #c8cdd8;
    font-size: 11px;
    padding-top: 10px;
}

.dur {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f5f6fa;
    border-radius: 5px;
    padding: 5px 9px;
}

.dl {
    font-size: 10px;
    color: #9399aa;
}

.dv {
    font-size: 12px;
    font-weight: 700;
}

.dv-run {
    color: #0ea5e9;
}

.dv-done {
    color: #9399aa;
}

.dv-wait {
    color: #f59e0b;
}

.dv-not-started {
    color: #64748b;
}
</style>
