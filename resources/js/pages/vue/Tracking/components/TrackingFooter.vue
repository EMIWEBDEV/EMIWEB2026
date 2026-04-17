<template>
    <footer>
        <div class="legend">
            <div class="leg">
                <div class="ldot" style="background:#22c55e"></div>
                Running
            </div>
            <div class="leg">
                <div class="ldot" style="background:#94a3b8"></div>
                Selesai (sebagian)
            </div>
            <div class="leg">
                <div class="ldot" style="background:#f59e0b"></div>
                Pending
            </div>
            <div class="leg">
                <div class="ldot" style="background:#4ade80;border:1px solid #bbf7d0"></div>
                Selesai Semua Lane
            </div>
        </div>
        <div class="foot-meta">
            Supervisor: {{ meta.supervisor || '—' }} · {{ meta.shift || '—' }} · {{ meta.line || '—' }}
        </div>
    </footer>
</template>

<script>
export default {
    props: {
        meta: {
            type: Object,
            default: () => ({}),
        },
    },
};
</script>

<style scoped>
footer {
    padding: 14px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: #64748b;
    border-top: 1px solid #e2e8f0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    margin-top: auto;
    flex-wrap: wrap;
    gap: 12px;
    
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 100;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.06);
}

.legend {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}

.leg {
    display: flex;
    align-items: center;
    gap: 5px;
}

.ldot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.foot-meta {
    font-size: 11px;
    background: #f1f5f9;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 500;
    color: #475569;
    display: flex;
    align-items: center;
    border: 1px solid #e2e8f0;
}

@media (max-width: 768px) {
    footer {
        flex-direction: column;
        align-items: stretch;
        padding: 12px 16px 14px;
        gap: 12px;
        background: #fcfcfd;
        position: static;
        box-shadow: none;
    }
    .legend {
        gap: 10px 14px;
        border-bottom: 1px dashed #eef0f4;
        padding-bottom: 12px;
    }
    .leg {
        font-size: 10px;
    }
    .foot-meta {
        border-radius: 6px;
        text-align: center;
        justify-content: center;
    }
}
</style>
