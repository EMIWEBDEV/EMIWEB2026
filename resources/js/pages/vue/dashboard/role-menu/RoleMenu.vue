<template>
    <div class="container-fluid px-0">

        <!-- ═══ Page Header ═══ -->
        <div class="page-header-card card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h1 class="fw-bold mb-1" style="font-size: 1.4rem; color: #1e293b">
                            <i class="fas fa-sitemap me-2 text-primary"></i>
                            Manajemen Akses Menu
                        </h1>
                        <p class="text-muted mb-0 small">
                            Kelola hak akses menu sidebar untuk setiap pengguna sistem EMI LAB
                        </p>
                    </div>
                    <button
                        class="btn btn-primary d-flex align-items-center gap-2"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#offcanvasAddAccess"
                        aria-controls="offcanvasAddAccess"
                    >
                        <i class="ri-add-circle-line fs-5"></i>
                        <span>Tambah Akses Menu</span>
                    </button>
                </div>

                <!-- ─── Stats Row ─── -->
                <div class="row g-3 mt-3">
                    <div class="col-12 col-sm-4">
                        <div class="stat-card stat-primary">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-body">
                                <div class="stat-value">
                                    {{ loading.loadingListData ? "—" : meta.total_users }}
                                </div>
                                <div class="stat-label">Pengguna dengan Akses</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card stat-success">
                            <div class="stat-icon">
                                <i class="fas fa-bars"></i>
                            </div>
                            <div class="stat-body">
                                <div class="stat-value">
                                    {{ loading.loadingListData ? "—" : meta.total_menu_available }}
                                </div>
                                <div class="stat-label">Total Menu Tersedia</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="stat-card stat-info">
                            <div class="stat-icon">
                                <i class="fas fa-link"></i>
                            </div>
                            <div class="stat-body">
                                <div class="stat-value">
                                    {{ loading.loadingListData ? "—" : meta.total_assignments }}
                                </div>
                                <div class="stat-label">Total Penetapan Menu</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ User Grid ═══ -->
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h5 class="mb-0 fw-semibold" style="color: #1e293b">
                        <i class="fas fa-user-shield me-2 text-primary"></i>
                        Daftar Pengguna & Akses Menu
                    </h5>
                    <span
                        v-if="!loading.loadingListData"
                        class="badge bg-primary rounded-pill px-3 py-2"
                    >
                        {{ listData.length }} pengguna
                    </span>
                </div>

                <!-- Loading skeleton -->
                <div v-if="loading.loadingListData" class="row g-3">
                    <div v-for="i in 4" :key="i" class="col-md-6 col-lg-4 col-xl-3">
                        <div class="user-card-skeleton rounded-3" style="height: 170px"></div>
                    </div>
                </div>

                <!-- User cards grid -->
                <div v-else-if="listData.length" class="row g-3">
                    <div
                        v-for="(item, index) in listData"
                        :key="index"
                        class="col-md-6 col-lg-4 col-xl-3"
                    >
                        <div class="user-card h-100">
                            <div class="user-card-header">
                                <!-- Avatar with initials -->
                                <div
                                    class="user-avatar"
                                    :style="{ background: getAvatarColor(item.Id_User) }"
                                >
                                    {{ item.Id_User.charAt(0).toUpperCase() }}
                                </div>
                                <div class="user-info">
                                    <div class="user-id" :title="item.Id_User">
                                        {{ item.Id_User }}
                                    </div>
                                    <div class="user-count">
                                        {{ item.total_data }}
                                        <span class="text-muted">/ {{ meta.total_menu_available }} menu</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div class="mt-3">
                                <div
                                    class="d-flex justify-content-between align-items-center mb-1"
                                >
                                    <span class="small text-muted">Cakupan Akses</span>
                                    <span
                                        class="small fw-semibold"
                                        :class="getProgressTextClass(item.total_data)"
                                    >
                                        {{ getProgressPct(item.total_data) }}%
                                    </span>
                                </div>
                                <div class="progress" style="height: 6px; border-radius: 4px">
                                    <div
                                        class="progress-bar"
                                        :class="getProgressBarClass(item.total_data)"
                                        :style="{ width: getProgressPct(item.total_data) + '%' }"
                                        role="progressbar"
                                    ></div>
                                </div>
                            </div>

                            <!-- Access badge + action -->
                            <div class="d-flex align-items-center justify-content-between mt-3">
                                <span
                                    class="access-badge"
                                    :class="getAccessBadgeClass(item.total_data)"
                                >
                                    <i class="me-1" :class="getAccessIcon(item.total_data)"></i>
                                    {{ getAccessLabel(item.total_data) }}
                                </span>
                                <a
                                    :href="'/role/menu/' + item.Id_User"
                                    class="btn btn-sm btn-primary d-flex align-items-center gap-1"
                                >
                                    <i class="ri-settings-3-line"></i>
                                    <span>Kelola</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="d-flex justify-content-center py-5">
                    <div class="text-center">
                        <DotLottieVue
                            style="height: 180px; width: 180px"
                            autoplay
                            loop
                            src="/animation/empty.lottie"
                        />
                        <p class="text-muted mt-2">
                            Belum ada pengguna dengan akses menu yang ditetapkan.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Offcanvas: Tambah Akses ═══ -->
        <div
            class="offcanvas offcanvas-end"
            tabindex="-1"
            id="offcanvasAddAccess"
            aria-labelledby="offcanvasAddAccessLabel"
            style="width: 420px"
        >
            <div class="offcanvas-header border-bottom">
                <div>
                    <h5 id="offcanvasAddAccessLabel" class="mb-0 fw-semibold">
                        <i class="ri-add-circle-line text-primary me-2"></i>
                        Tambah Akses Menu
                    </h5>
                    <p class="text-muted small mb-0 mt-1">
                        Tetapkan menu sidebar untuk pengguna
                    </p>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Close"
                ></button>
            </div>
            <div class="offcanvas-body">
                <div
                    class="alert alert-info border-0 d-flex gap-2 py-2 px-3 small rounded-3 mb-4"
                    role="alert"
                >
                    <i class="ri-information-2-line flex-shrink-0 mt-1"></i>
                    <span>
                        Pilih pengguna dan menu yang akan ditetapkan.
                        Atur urutan tampil pada kolom <strong>Urutan</strong>.
                        <br />
                        <strong>Catatan:</strong> Hanya gunakan icon
                        <em>Font Awesome</em> pada master menu.
                    </span>
                </div>

                <form @submit.prevent="submitForm">
                    <!-- Pengguna -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Pengguna (User)
                            <span class="text-danger">*</span>
                        </label>
                        <el-select
                            v-if="userCurrentList && userCurrentList.length"
                            v-model="selectedOptionUser"
                            multiple
                            collapse-tags
                            collapse-tags-tooltip
                            :max-collapse-tags="2"
                            placeholder="--- Pilih Pengguna ---"
                            style="width: 100%"
                            clearable
                        >
                            <el-option
                                v-for="item in userCurrentList"
                                :key="item.value"
                                :label="item.name"
                                :value="item.value"
                            />
                        </el-select>
                    </div>

                    <!-- Menu -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Menu Sidebar
                            <span class="text-danger">*</span>
                        </label>
                        <div
                            v-if="selectedOptionUser.length === 0"
                            class="text-muted small mb-2"
                        >
                            <i class="ri-arrow-up-line me-1"></i>Pilih pengguna
                            terlebih dahulu.
                        </div>
                        <el-select
                            v-if="menuCurrentList && menuCurrentList.length"
                            v-model="selectedOptionIdentity"
                            multiple
                            collapse-tags
                            collapse-tags-tooltip
                            :max-collapse-tags="2"
                            placeholder="--- Pilih Menu Sidebar ---"
                            style="width: 100%"
                            clearable
                            @change="handleMenuChange"
                            :disabled="selectedOptionUser.length === 0"
                        >
                            <el-option
                                label="— Pilih Semua Menu —"
                                value="ALL"
                                class="fw-bold text-primary"
                            />
                            <el-option
                                v-for="item in processedMenuOptions"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                                :disabled="item.disabled"
                            />
                        </el-select>
                    </div>

                    <!-- Atur Urutan -->
                    <div v-if="selectedOptionIdentity.length > 0" class="mb-4">
                        <label class="form-label fw-semibold">
                            Atur Urutan Menu
                        </label>
                        <div
                            v-for="menuId in selectedOptionIdentity"
                            :key="menuId"
                            class="urutan-row d-flex align-items-center gap-2 mb-2 p-2 rounded-3 border"
                        >
                            <span class="flex-grow-1 small fw-medium text-truncate">
                                <i class="fas fa-bars text-muted me-1"></i>
                                {{ getMenuName(menuId) }}
                            </span>
                            <input
                                type="number"
                                class="form-control form-control-sm"
                                style="width: 80px"
                                v-model.number="urutanMenu[menuId]"
                                placeholder="Urutan"
                                required
                                min="1"
                            />
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger flex-shrink-0"
                                @click="removeMenu(menuId)"
                                title="Hapus menu ini"
                            >
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button
                            :disabled="
                                loading.menuSaveToDatabase ||
                                selectedOptionUser.length === 0 ||
                                selectedOptionIdentity.length === 0
                            "
                            type="submit"
                            class="btn btn-primary"
                        >
                            <span
                                v-if="loading.menuSaveToDatabase"
                                class="spinner-border spinner-border-sm me-2"
                            ></span>
                            <i v-else class="ri-save-line me-2"></i>
                            {{
                                loading.menuSaveToDatabase
                                    ? "Menyimpan..."
                                    : "Simpan Akses Menu"
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import Swal from "sweetalert2";
import { ElSelect, ElOption } from "element-plus";

const AVATAR_COLORS = [
    "#4f6ef7", "#10b981", "#f59e0b", "#ef4444",
    "#8b5cf6", "#06b6d4", "#f97316", "#84cc16",
];

export default {
    components: { DotLottieVue, ElSelect, ElOption },

    data() {
        return {
            listData: [],
            menuCurrentList: [],
            userCurrentList: [],
            selectedOptionIdentity: [],
            selectedOptionUser: [],
            urutanMenu: {},
            meta: {
                total_users: 0,
                total_menu_available: 0,
                total_assignments: 0,
            },
            loading: {
                loadingListData: false,
                menuSaveToDatabase: false,
            },
            errors: {},
        };
    },

    computed: {
        processedMenuOptions() {
            return this.menuCurrentList.map((menu) => {
                if (!this.selectedOptionUser || !this.selectedOptionUser.length) {
                    return { ...menu, label: menu.name, disabled: false };
                }

                const ownedBy = this.selectedOptionUser.filter((userId) =>
                    this.listData.some(
                        (d) => d.Id_User === userId && d.Id_Menu === menu.value
                    )
                );

                let label = menu.name;
                let disabled = false;

                if (ownedBy.length > 0) {
                    if (ownedBy.length === this.selectedOptionUser.length) {
                        label = `${menu.name} (Sudah dimiliki semua user terpilih)`;
                        disabled = true;
                    } else {
                        label = `${menu.name} (Sudah: ${ownedBy.join(", ")})`;
                    }
                }

                return { ...menu, label, disabled };
            });
        },
    },

    methods: {
        /* ─── Helpers ─── */
        getMenuName(id) {
            return this.menuCurrentList.find((m) => m.value === id)?.name ?? "";
        },

        getAvatarColor(userId) {
            let hash = 0;
            for (let i = 0; i < userId.length; i++) {
                hash = userId.charCodeAt(i) + ((hash << 5) - hash);
            }
            return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
        },

        getProgressPct(count) {
            if (!this.meta.total_menu_available) return 0;
            return Math.min(
                Math.round((count / this.meta.total_menu_available) * 100),
                100
            );
        },

        getProgressBarClass(count) {
            const pct = this.getProgressPct(count);
            if (pct >= 70) return "bg-success";
            if (pct >= 40) return "bg-primary";
            if (pct >= 20) return "bg-warning";
            return "bg-danger";
        },

        getProgressTextClass(count) {
            const pct = this.getProgressPct(count);
            if (pct >= 70) return "text-success";
            if (pct >= 40) return "text-primary";
            if (pct >= 20) return "text-warning";
            return "text-danger";
        },

        getAccessLabel(count) {
            const pct = this.getProgressPct(count);
            if (pct >= 80) return "Full Access";
            if (pct >= 50) return "Partial Access";
            if (pct >= 20) return "Limited Access";
            return "Minimal Access";
        },

        getAccessBadgeClass(count) {
            const pct = this.getProgressPct(count);
            if (pct >= 80) return "badge-full";
            if (pct >= 50) return "badge-partial";
            if (pct >= 20) return "badge-limited";
            return "badge-minimal";
        },

        getAccessIcon(count) {
            const pct = this.getProgressPct(count);
            if (pct >= 80) return "fas fa-shield-alt";
            if (pct >= 50) return "fas fa-user-check";
            if (pct >= 20) return "fas fa-user-clock";
            return "fas fa-user-lock";
        },

        /* ─── Menu select helpers ─── */
        updateUrutan() {
            const sorted = this.selectedOptionIdentity
                .map((id) => ({ id, urutan: this.urutanMenu[id] || 999999 }))
                .sort((a, b) => a.urutan - b.urutan);

            const newUrutan = {};
            sorted.forEach((item, i) => { newUrutan[item.id] = i + 1; });
            this.urutanMenu = newUrutan;
            this.selectedOptionIdentity = sorted.map((item) => item.id);
        },

        handleMenuChange(val) {
            if (val.includes("ALL")) {
                this.selectedOptionIdentity = this.menuCurrentList.map(
                    (item) => item.value
                );
            }
            this.updateUrutan();
        },

        removeMenu(id) {
            this.selectedOptionIdentity = this.selectedOptionIdentity.filter(
                (menuId) => menuId !== id
            );
            this.updateUrutan();
        },

        /* ─── Data fetching ─── */
        async fetchMenuListCurrent() {
            try {
                const res = await axios.get("/api/v1/master-menu");
                if (res.data?.result) {
                    this.menuCurrentList = res.data.result.map((item) => ({
                        value: item.Id_Menu,
                        name: item.Nama_Menu,
                    }));
                }
            } catch {
                this.menuCurrentList = [];
            }
        },

        async fetchPenggunaListCurrent() {
            try {
                const res = await axios.get("/api/v1/pengguna/current");
                if (res.data?.result) {
                    this.userCurrentList = res.data.result.map((item) => ({
                        value: item.UserId,
                        name: `${item.UserId} — ${item.Nama}`,
                    }));
                }
            } catch {
                this.userCurrentList = [];
            }
        },

        async fetchListData() {
            this.loading.loadingListData = true;
            try {
                const res = await axios.get("/api/v1/role-menu/home-current");
                if (res.data?.result) {
                    this.listData = res.data.result;
                    if (res.data?.meta) {
                        this.meta = res.data.meta;
                    }
                } else {
                    this.listData = [];
                }
            } catch {
                this.listData = [];
            } finally {
                this.loading.loadingListData = false;
            }
        },

        /* ─── Form submit ─── */
        async submitForm() {
            this.errors = {};
            this.loading.menuSaveToDatabase = true;
            try {
                const payload = [];
                this.selectedOptionIdentity.forEach((menuId) => {
                    this.selectedOptionUser.forEach((userId) => {
                        payload.push({
                            Id_Menu: menuId,
                            Id_User: userId,
                            Urutan_Menu: this.urutanMenu[menuId] || 1,
                        });
                    });
                });

                const res = await axios.post(
                    "/api/v1/role-menu/store",
                    { data: payload },
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (!res.data.success) {
                    throw new Error(res.data.message || "Gagal menyimpan data");
                }

                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: res.data.message,
                    timer: 1500,
                    showConfirmButton: false,
                }).then(() => location.reload());
            } catch (error) {
                Swal.fire("Error", error.message, "error");
            } finally {
                this.loading.menuSaveToDatabase = false;
            }
        },
    },

    mounted() {
        Promise.all([
            this.fetchListData(),
            this.fetchMenuListCurrent(),
            this.fetchPenggunaListCurrent(),
        ]);
    },
};
</script>

<style scoped>
/* ═══ Page Header ═══ */
.page-header-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8faff 100%);
}

/* ═══ Stat Cards ═══ */
.stat-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    border-radius: 12px;
    border-left: 4px solid;
}

.stat-card.stat-primary {
    background: rgba(79, 110, 247, 0.07);
    border-color: #4f6ef7;
}

.stat-card.stat-success {
    background: rgba(16, 185, 129, 0.07);
    border-color: #10b981;
}

.stat-card.stat-info {
    background: rgba(6, 182, 212, 0.07);
    border-color: #06b6d4;
}

.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.stat-primary .stat-icon {
    background: rgba(79, 110, 247, 0.12);
    color: #4f6ef7;
}

.stat-success .stat-icon {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}

.stat-info .stat-icon {
    background: rgba(6, 182, 212, 0.12);
    color: #06b6d4;
}

.stat-value {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1;
    color: #1e293b;
}

.stat-label {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

/* ═══ User Cards ═══ */
.user-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.user-card:hover {
    border-color: #4f6ef7;
    box-shadow: 0 4px 16px rgba(79, 110, 247, 0.12);
    transform: translateY(-2px);
}

.user-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    color: #fff;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-id {
    font-weight: 700;
    font-size: 14px;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-count {
    font-size: 12px;
    color: #475569;
    margin-top: 2px;
    font-weight: 600;
}

/* ─── Access badge ─── */
.access-badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
}

.badge-full {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
}

.badge-partial {
    background: rgba(79, 110, 247, 0.12);
    color: #4f6ef7;
}

.badge-limited {
    background: rgba(245, 158, 11, 0.12);
    color: #d97706;
}

.badge-minimal {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
}

/* ─── Loading skeleton ─── */
.user-card-skeleton {
    position: relative;
    background: #e2e8f0;
    overflow: hidden;
}

.user-card-skeleton::after {
    content: "";
    position: absolute;
    top: 0;
    left: -150px;
    width: 150px;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.5),
        transparent
    );
    animation: shimmer 1.4s infinite;
}

@keyframes shimmer {
    100% { left: 100%; }
}

/* ─── Offcanvas form ─── */
.urutan-row {
    background: #f8fafc;
    transition: background 0.15s;
}

.urutan-row:hover {
    background: #f0f4ff;
}
</style>
