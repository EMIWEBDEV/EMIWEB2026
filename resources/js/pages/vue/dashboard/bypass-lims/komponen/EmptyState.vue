<template>
    <div class="bp-empty">
        <div class="bp-empty__ic"><i :class="ikon"></i></div>
        <h4>{{ judul }}</h4>
        <p>{{ pesan }}</p>
    </div>
</template>

<script>
export default {
    name: "EmptyState",

    props: {
        ikon: { type: String, default: "ri-inbox-line" },
        judul: { type: String, required: true },
        pesan: { type: String, default: "" },
    },
};
</script>
