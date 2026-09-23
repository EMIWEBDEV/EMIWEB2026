<template>
    <div :class="kolom">
        <div class="bp-cell" :class="{ 'bp-cell--main': utama }">
            <span class="bp-cell__ic"><i :class="ikon"></i></span>
            <div class="bp-cell__body">
                <span class="bp-cell__lbl">{{ label }}</span>
                <span class="bp-cell__val" :title="String(nilai ?? '')">
                    {{ tampil }}
                </span>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "InfoCell",

    props: {
        label: { type: String, required: true },
        nilai: { type: [String, Number], default: null },
        ikon: { type: String, default: "ri-information-line" },
        utama: { type: Boolean, default: false },
        kolom: { type: String, default: "col-6 col-lg-4 col-xxl-3" },
    },

    computed: {
        tampil() {
            const v = this.nilai;

            // Nilai 0 tetap ditampilkan; hanya null/kosong yang jadi strip.
            return v === null || v === undefined || v === "" ? "—" : v;
        },
    },
};
</script>
