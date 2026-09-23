<template>
    <div class="bp-metric" :class="`bp-metric--${warna}`">
        <div class="bp-metric__ic"><i :class="ikon"></i></div>
        <div class="bp-metric__body">
            <div class="bp-metric__num">{{ nilai }}</div>
            <div class="bp-metric__lbl">{{ label }}</div>
        </div>
    </div>
</template>

<script>
export default {
    name: "MetricBox",

    props: {
        nilai: { type: [String, Number], default: 0 },
        label: { type: String, required: true },
        ikon: { type: String, default: "ri-bar-chart-line" },
        warna: { type: String, default: "primary" },
    },
};
</script>
