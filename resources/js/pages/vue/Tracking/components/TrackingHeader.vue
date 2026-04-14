<template>
    <header class="tracking-header">
        <div class="tracking-header__left">
            <div class="h-title">🏭 {{ title }}</div>
            <div class="h-sub">{{ dateLabel }}</div>
        </div>
        <div class="tracking-header__right">
            <div class="live">
                <div class="dot"></div>
                {{ liveText }}
            </div>
            <div class="clock">{{ clock }}</div>
        </div>
    </header>
</template>

<script>
export default {
    props: {
        title: {
            type: String,
            required: true,
        },
        subtitle: {
            type: String,
            default: "",
        },
        dateLabel: {
            type: String,
            default: "—",
        },
        clock: {
            type: String,
            default: "--:--:--",
        },
        liveText: {
            type: String,
            default: "LIVE",
        },
    },
};
</script>

<style scoped>
.tracking-header {
    background: #fff;
    border-bottom: 1px solid #e8eaf0;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.tracking-header__left {
    display: flex;
    flex-direction: column;
}

.h-title {
    font-size: 15px;
    font-weight: 700;
}

.h-sub {
    font-size: 11px;
    color: #9399aa;
    margin-top: 2px;
}

.tracking-header__right {
    display: flex;
    align-items: center;
    gap: 14px;
}

.live {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    font-weight: 600;
    color: #22c55e;
}

.dot {
    width: 7px;
    height: 7px;
    background: #22c55e;
    border-radius: 50%;
    animation: trackingPulse 1.5s ease infinite;
}

@keyframes trackingPulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.4;
    }
}

.clock {
    font-size: 13px;
    font-weight: 600;
    min-width: 60px;
    text-align: right;
}

@media (max-width: 576px) {
    .tracking-header {
        padding: 12px 16px;
        /* Keep horizontal layout, just tighten gaps */
    }
    
    .h-title {
        font-size: 13px;
    }
    
    .h-sub {
        font-size: 10px;
    }

    .tracking-header__right {
        gap: 6px;
    }
    
    .live {
        font-size: 10px;
    }
    
    .clock {
        font-size: 12px;
        min-width: auto;
    }
}
</style>
