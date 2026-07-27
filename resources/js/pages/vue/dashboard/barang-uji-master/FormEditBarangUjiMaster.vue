<template>
    <div class="container-fluid mx-auto px-0">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3 p-md-4 p-lg-5">
                <div class="mb-4 d-flex justify-content-between align-items-start">
                    <div>
                        <h1 class="text-xl md:text-3xl font-bold text-primary">Edit Aturan Master</h1>
                        <p class="text-sm md:text-base text-muted mb-0">Ubah user, analisa, mesin, atau status aktif.</p>
                    </div>
                    <a href="/barang-uji-master" class="btn btn-light border">Kembali</a>
                </div>
                <div class="divider mb-4"></div>

                <div v-if="loading.init" class="text-center py-5"><div class="spinner-border text-primary"></div></div>

                <form v-else @submit.prevent="updateData">
                    <div class="row g-3">
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
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Jenis Analisa <span class="text-danger">*</span></label>
                            <el-select v-model="form.Id_Jenis_Analisa" placeholder="-- Pilih Analisa --" class="w-100" filterable clearable>
                                <el-option v-for="a in options.jenisAnalisa" :key="a.id" :label="`${a.Kode_Analisa} ~ ${a.Jenis_Analisa}`" :value="a.id" />
                            </el-select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Mesin <span class="text-danger">*</span></label>
                            <el-select v-model="form.Id_Master_Mesin" placeholder="-- Pilih Mesin --" class="w-100" filterable clearable>
                                <el-option label="-- SEMUA MESIN (ALL) --" value="ALL" class="fw-bold text-primary bg-light" />
                                <el-option v-for="m in options.mesin" :key="m.Id_Master_Mesin" :label="m.Nama_Mesin" :value="m.Id_Master_Mesin" />
                            </el-select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Status</label>
                            <el-select v-model="form.Flag_Aktif" class="w-100">
                                <el-option label="Aktif" value="Y" />
                                <el-option label="Nonaktif" value="N" />
                            </el-select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4 border-top pt-3">
                        <button type="submit" class="btn btn-success px-5" :disabled="loading.save">
                            <span v-if="loading.save" class="spinner-border spinner-border-sm me-2"></span>
                            <span v-else>Perbarui</span>
                        </button>
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
    props: { roles: { type: Array, default: () => [] }, id: { type: String, required: true } },
    data() {
        return {
            options: { user: [], jenisAnalisa: [], mesin: [] },
            form: { Id_User: "", Id_Jenis_Analisa: "", Id_Master_Mesin: "", Kode_Role: "", Flag_Aktif: "Y" },
            loading: { init: true, save: false },
        };
    },
    async mounted() {
        await this.fetchOptions();
        await this.fetchDetail();
        this.loading.init = false;
    },
    methods: {
        async fetchOptions() {
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
        },
        async fetchDetail() {
            try {
                const { data } = await axios.get("/api/v1/barang-uji-master/detail/" + this.id);
                const r = data.result;
                this.form.Id_User = r.Id_User;
                this.form.Id_Jenis_Analisa = r.Id_Jenis_Analisa;
                this.form.Id_Master_Mesin = r.Id_Master_Mesin;
                this.form.Kode_Role = r.Kode_Role || "";
                this.form.Flag_Aktif = r.Flag_Aktif || "Y";
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal memuat detail.");
            }
        },
        async updateData() {
            if (!this.form.Id_User || !this.form.Id_Jenis_Analisa || !this.form.Id_Master_Mesin) {
                return ElMessage.warning("User, analisa, dan mesin wajib diisi.");
            }
            if (this.roles.length > 1 && !this.form.Kode_Role) return ElMessage.warning("Role wajib dipilih.");
            this.loading.save = true;
            try {
                const { data } = await axios.put("/api/v1/barang-uji-master/update/" + this.id, this.form);
                ElMessage.success(data.message || "Diperbarui.");
                // Tandai ada job latar belakang -> widget di Home akan muncul.
                localStorage.setItem("bm_bg_job", JSON.stringify({
                    t: Date.now(),
                    msg: "Menyesuaikan data barang uji (edit aturan)...",
                }));
                window.location.href = "/barang-uji-master";
            } catch (e) {
                ElMessage.error(e.response?.data?.message || "Gagal memperbarui.");
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
