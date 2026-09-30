<template>
    <div class="bp-phead" :class="`bp-phead--${kartu.warna}`">
        <button class="bp-phead__back" title="Kembali ke menu" @click="$emit('kembali')">
            <i class="ri-arrow-left-line"></i>
        </button>

        <div class="bp-phead__ic"><i :class="kartu.ikonRi"></i></div>

        <div class="bp-phead__txt">
            <h2>{{ kartu.judul }}</h2>
            <p>{{ kartu.deskripsi }}</p>
        </div>

        <span class="bp-phead__tag">
            <i class="ri-database-2-line"></i>
            {{ kartu.tabel }}
        </span>
    </div>
</template>

<script>
export default {
    name: "PanelHead",
    props: {
        kartu: { type: Object, required: true },
    },
    emits: ["kembali"],
};
</script>
