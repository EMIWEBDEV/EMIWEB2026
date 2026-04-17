<template>
    <section class="history-wrap">
        <div class="history-head">
            <div>
                <h3 class="history-title">Riwayat Tracking</h3>
                <p class="history-subtitle">Data selesai atau ditutup ditampilkan bertahap agar tetap ringan.</p>
            </div>
            <div class="history-count">Total {{ pagination.total || 0 }}</div>
        </div>

        <div class="history-table-wrap">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>PRD</th>
                        <th>Batch</th>
                        <th>Box</th>
                        <th>Tahap Terakhir</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="empty-cell">Belum ada data riwayat.</td>
                    </tr>
                    <tr v-for="row in rows" :key="`${row.id}-${row.last_activity_date}`">
                        <td>{{ row.last_activity_date || row.date }}</td>
                        <td class="cell-prd">{{ row.id }}</td>
                        <td>{{ row.batch }}</td>
                        <td>{{ row.box_count }}</td>
                        <td>{{ row.last_stage }}</td>
                        <td>
                            <span class="status-pill" :class="statusClass(row.status)">
                                {{ row.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="history-pagination">
            <button class="page-btn" :disabled="pagination.page <= 1 || loading" @click="$emit('change-page', pagination.page - 1)">
                Sebelumnya
            </button>
            <span class="page-label">Halaman {{ pagination.page || 1 }} / {{ pagination.last_page || 1 }}</span>
            <button class="page-btn" :disabled="(pagination.page || 1) >= (pagination.last_page || 1) || loading" @click="$emit('change-page', (pagination.page || 1) + 1)">
                Berikutnya
            </button>
        </div>
    </section>
</template>

<script>
export default {
    props: {
        rows: { type: Array, default: () => [] },
        pagination: { type: Object, default: () => ({}) },
        loading: { type: Boolean, default: false },
    },
    emits: ["change-page"],
    methods: {
        statusClass(status) {
            if (status === "Selesai") return "is-done";
            if (status === "Running") return "is-run";
            return "is-closed";
        },
    },
};
</script>

<style scoped>
.history-wrap {
    padding: 16px 24px 24px;
}

.history-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.history-title {
    margin: 0 0 4px;
    font-size: 18px;
    font-weight: 700;
}

.history-subtitle {
    margin: 0;
    font-size: 12px;
    color: #70788c;
}

.history-count {
    font-size: 12px;
    font-weight: 700;
    color: #4f46e5;
    background: #eef2ff;
    padding: 6px 10px;
    border-radius: 999px;
}

.history-table-wrap {
    background: #fff;
    border: 1px solid #e8eaf0;
    border-radius: 12px;
    overflow: auto;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

.history-table th,
.history-table td {
    padding: 12px 14px;
    text-align: left;
    border-bottom: 1px solid #eef1f5;
    font-size: 12px;
}

.history-table th {
    position: sticky;
    top: 0;
    background: #f8fafc;
    z-index: 1;
    color: #5a607a;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 11px;
}

.cell-prd {
    font-weight: 700;
    color: #202637;
}

.status-pill {
    display: inline-flex;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.status-pill.is-done {
    background: #f0fdf4;
    color: #15803d;
}

.status-pill.is-run {
    background: #ecfeff;
    color: #0f766e;
}

.status-pill.is-closed {
    background: #f1f5f9;
    color: #64748b;
}

.empty-cell {
    text-align: center !important;
    color: #94a3b8;
    font-style: italic;
}

.history-pagination {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 14px;
}

.page-btn {
    border: 1px solid #dbe1ea;
    background: #fff;
    color: #364152;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.page-label {
    font-size: 12px;
    color: #64748b;
}

@media (max-width: 576px) {
    .history-wrap {
        padding: 16px;
    }

    .history-head {
        flex-direction: column;
        align-items: flex-start;
    }

    .history-pagination {
        justify-content: space-between;
    }
}
</style>
