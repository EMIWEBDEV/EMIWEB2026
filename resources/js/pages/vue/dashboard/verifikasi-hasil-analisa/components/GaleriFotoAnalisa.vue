<template>
    <!--
        Foto analisa, dikelompokkan per analisa yang memiliki foto — tampil
        tepat di bawah tabel klasifikasinya, bukan terkumpul di akhir halaman.
        Gambar dimuat induknya (endpoint stream modul lab); komponen ini hanya
        menampilkan dan meneruskan klik untuk memperbesar.
    -->
    <div class="gf" :class="{ 'is-rata': tanpaBingkai }">
        <div class="gf-h">
            <i class="ri-image-2-line"></i><b>{{ judul }}</b>
            <em>{{ jumlah }} foto &middot; ketuk untuk memperbesar</em>
        </div>
        <div v-for="g in grup" :key="g.id" class="gf-a">
            <div class="gf-a-h">
                <b>{{ g.analisa }}</b>
                <span class="badge" :class="g.layak.cls">{{ g.layak.label }}</span>
                <em>{{ g.foto.length }} foto<template v-if="g.hasil"> &middot; {{ g.hasil }}</template></em>
            </div>
            <div class="gf-g">
                <button v-for="f in g.foto" :key="f.key" type="button" class="gf-c"
                        :title="'Perbesar foto ' + g.analisa" @click="$emit('buka', f.key)">
                    <span class="gf-img">
                        <img v-if="url[f.key]" :src="url[f.key]" :alt="'Foto ' + g.analisa" />
                        <span v-else-if="gagal[f.key]" class="gf-x"><i class="ri-image-line"></i>Foto tidak dapat dimuat</span>
                        <span v-else class="spinner-border spinner-border-sm text-primary"></span>
                        <span v-if="url[f.key]" class="gf-zoom"><i class="ri-zoom-in-line"></i></span>
                    </span>
                    <span class="gf-t">
                        <code>{{ f.sampel }}</code>
                        <span v-if="f.keterangan">{{ f.keterangan }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "GaleriFotoAnalisa",
    props: {
        // [{ id, analisa, layak: { cls, label }, hasil, foto: [{ key, sampel, keterangan }] }]
        grup: { type: Array, default: () => [] },
        url: { type: Object, default: () => ({}) },
        gagal: { type: Object, default: () => ({}) },
        judul: { type: String, default: "Foto analisa" },
        // Di dalam kartu klasifikasi: tanpa bingkai sendiri.
        tanpaBingkai: { type: Boolean, default: false },
    },
    emits: ["buka"],
    computed: {
        jumlah() { return this.grup.reduce((n, g) => n + g.foto.length, 0); },
    },
};
</script>

<style scoped>
.gf { margin-top: 14px; background: #fff; border: 1px solid #e9ebec; border-radius: 7px; padding: 11px 13px 13px; }
.gf.is-rata { margin-top: 0; border: 0; border-top: 1px solid #eef0f4; border-radius: 0; }
.gf-h { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 6px; margin-bottom: 8px; font-size: .72rem; }
.gf-h i { color: #0891b2; }
.gf-h b { color: #495057; }
.gf-h em { font-style: normal; font-size: .66rem; color: #adb5bd; }

.gf-a + .gf-a { margin-top: 12px; }
.gf-a-h { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 7px; margin-bottom: 6px; font-size: .7rem; }
.gf-a-h b { color: #343a40; }
.gf-a-h .badge { font-size: .6rem; }
.gf-a-h em { font-style: normal; font-size: .66rem; color: #878a99; }

.gf-g { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(140px, 100%), 1fr)); gap: 9px; }
.gf-c { display: flex; flex-direction: column; padding: 0; text-align: left; min-width: 0;
    border: 1px solid #e9ebec; border-radius: 7px; overflow: hidden; background: #fff;
    cursor: zoom-in; transition: border-color .14s, box-shadow .14s; }
.gf-c:hover, .gf-c:focus-visible { border-color: #0891b2; box-shadow: 0 4px 14px rgba(8, 145, 178, .14); }
.gf-img { position: relative; display: flex; align-items: center; justify-content: center;
    aspect-ratio: 4 / 3; background: #f3f6f9; overflow: hidden; }
.gf-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.gf-x { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 0 8px; text-align: center;
    font-size: .64rem; color: #adb5bd; }
.gf-x i { font-size: 1.3rem; }
.gf-zoom { position: absolute; right: 7px; bottom: 7px; width: 26px; height: 26px; border-radius: 50%;
    background: rgba(33, 37, 41, .55); color: #fff; font-size: .85rem;
    display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .14s; }
.gf-c:hover .gf-zoom, .gf-c:focus-visible .gf-zoom { opacity: 1; }
.gf-t { display: flex; flex-direction: column; gap: 2px; padding: 6px 9px 7px; min-width: 0; font-size: .64rem; color: #878a99; }
.gf-t code { font-size: .62rem; color: #6c757d; }
.gf-t span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-style: italic; }
</style>
