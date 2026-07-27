<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="text-xl md:text-3xl font-bold text-primary">Tambah Aturan Master</h1>
                        <p class="text-sm md:text-base text-muted mb-0">
                            Pilih user, lalu daftar analisa + mesin. Varian selalu <b>ALL</b> saat di-Sync.
                        </p>
                    </div>
                    <a href="/barang-uji-master" class="btn btn-light border">Kembali</a>
                </div>
                <div class="divider mb-4"></div>

                <form @submit.prevent="saveData">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">User <span class="text-danger">*</span></label>
                            <el-select v-model="form.Id_User" placeholder="-- Pilih User --" class="w-100" filterable clearable>
                                <el-option v-for="u in options.user" :key="u.UserId" :label="`${u.UserId} ~ ${u.Nama}`" :value="u.UserId" />
                            </el-select>
                        </div>
                        <div class="col-md-6" v-if="roles && roles.length > 1">
                            <label class="form-label fw-bold">Penempatan / Role <span class="text-danger">*</span></label>
                            <el-select v-model="form.Kode_Role" placeholder="-- Pilih Role --" class="w-100" filterable>
                                <el-option v-for="(r, i) in roles" :key="i" :label="r.Kode_Role" :value="r.Kode_Role" />
                            </el-select>
                        </div>
                    </div>

                    <div v-if="loading.init" class="text-center py-4"><div class="spinner-border text-primary"></div></div>

                    <div v-else>
                        <label class="form-label fw-bold">Daftar Analisa &amp; Mesin</label>
                        <div v-for="(item, index) in form.items" :key="item.key" class="card mb-3 border">
                            <div class="card-body py-3">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Jenis Analisa <span class="text-danger">*</span></label>
                                        <el-select v-model="item.Id_Jenis_Analisa" placeholder="-- Pilih Analisa --" class="w-100" filterable clearable>
                                            <el-option v-for="a in options.jenisAnalisa" :key="a.id" :label="`${a.Kode_Analisa} ~ ${a.Jenis_Analisa}`" :value="a.id" />
                                        </el-select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small fw-semibold">Mesin <span class="text-danger">*</span></label>
                                        <el-select v-model="item.Id_Master_Mesin" placeholder="-- Pilih Mesin --" class="w-100" filterable clearable>
                                            <el-option label="-- SEMUA MESIN (ALL) --" value="ALL" class="fw-bold text-primary bg-light" />
                                            <el-option v-for="m in options.mesin" :key="m.Id_Master_Mesin" :label="m.Nama_Mesin" :value="m.Id_Master_Mesin" />
                                        </el-select>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button v-if="form.items.length > 1" type="button" class="btn btn-outline-danger btn-sm" @click="removeItem(index)">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-outline-primary btn-sm" @click="addItem">
                            <i class="ri-add-line"></i> Tambah Analisa
                        </button>

                        <div class="d-flex justify-content-end mt-4 border-top pt-3">
                            <button type="submit" class="btn btn-success px-5" :disabled="loading.save">
                                <span v-if="loading.save" class="spinner-border spinner-border-sm me-2"></span>
                                <span v-else>Simpan</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import { ElMessage, ElSelect, ElOption } from "element-plus";

export default {
    components: { ElSelect, ElOption },
    props: { roles: { type: Array, default: () => [] } },
    data() {
        return {
            options: { user: [], jenisAnalisa: [], mesin: [] },
            form: { Id_User: "", Kode_Role: "", items: [] },
            loading: { init: true, save: false },
        };
    },
    mounted() {
        this.fetchOptions();
        this.addItem();
    },
    methods: {
        async fetchOptions() {
            this.loading.init = true;
            try {
                const [u, a, m] = await Promise.all([
                    axios.get("/api/v1/barang-uji-master/option/user"),
                    axios.get("/api/v1/barang-uji-master/option/jenis-analisa"),
                    axios.get("/api/v1/barang-uji-master/option/mesin"),
                ]);
                this.options.user = u.data.result;
                this.options.jenisAnalisa = a.data.result;
                this.options.mesin = m.data.result;
            } catch { ElMessage.error("Gagal memuat opsi."); }
            finally { this.loading.init = false; }
        },
        addItem() {
            this.form.items.push({ key: Date.now() + Math.floor(Math.random() * 1000), Id_Jenis_Analisa: "", Id_Master_Mesin: "" });
        },
        removeItem(i) { if (this.form.items.length > 1) this.form.items.splice(i, 1); },
        async saveData() {
            if (!this.form.Id_User) return ElMessage.warning("User wajib dipilih.");
            if (this.roles.length > 1 && !this.form.Kode_Role) return ElMessage.warning("Role wajib dipilih.");
            for (let i = 0; i < this.form.items.length; i++) {
                const it = this.form.items[i];
                if (!it.Id_Jenis_Analisa || !it.Id_Master_Mesin) return ElMessage.warning(`Baris #${i + 1}: analisa & mesin wajib diisi.`);
            }
            this.loading.save = true;
            try {
                const payload = {
                    Id_User: this.form.Id_User,
                    Kode_Role: this.form.Kode_Role,
                    items: this.form.items.map((it) => ({ Id_Jenis_Analisa: it.Id_Jenis_Analisa, Id_Master_Mesin: it.Id_Master_Mesin })),
                };
                const { data } = await axios.post("/api/v1/barang-uji-master/store", payload);
                ElMessage.success(data.message || "Tersimpan.");
                window.location.href = "/barang-uji-master";
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal menyimpan.");
            } finally { this.loading.save = false; }
        },
    },
};
</script>

<style scoped>
.card { border-radius: 12px; overflow: hidden; }
.divider { height: 2px; background: linear-gradient(90deg, rgba(13,110,253,.1) 0%, rgba(13,110,253,.5) 50%, rgba(13,110,253,.1) 100%); }
.btn { border-radius: 8px; font-weight: 500; }
</style>
