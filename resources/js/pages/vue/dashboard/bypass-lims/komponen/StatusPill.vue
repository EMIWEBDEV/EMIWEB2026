<template>
    <span class="bp-badge" :class="batal ? 'bp-badge--err' : 'bp-badge--ok'">
        <i :class="batal ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'"></i>
        {{ batal ? "Dibatalkan" : "Aktif" }}
    </span>
</template>

<script>
export default {
    name: "StatusPill",

    props: {
        sampel: { type: Object, default: null },
    },

    computed: {
        // Status 'Y' menandai baris yang sudah dibatalkan.
        batal() {
            return this.sampel?.Status === "Y";
        },
    },
};
</script>
