<template>
    <div class="container-fluid px-0">
        <!-- ═══ Page Header + Stats ═══ -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                    <div>
                        <h1 class="fw-bold mb-1" style="font-size: 1.4rem; color: #1e293b">
                            <i class="fas fa-user-tag me-2 text-primary"></i>
                            Manajemen User Role
                        </h1>
                        <p class="text-muted mb-0 small">
                            Kelola penetapan hak akses untuk setiap pengguna sistem
                        </p>
                    </div>
                    <button
                        class="btn btn-primary d-flex align-items-center gap-2"
                        type="button"
                        @click="openOffcanvas"
                    >
                        <i class="ri-add-circle-line fs-5"></i>
                        <span>Tambah User Role</span>
                    </button>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-sm-4">
                        <div class="ur-stat ur-stat-blue">
                            <div class="ur-stat-icon"><i class="fas fa-users"></i></div>
                            <div class="ur-stat-body">
                                <div class="ur-stat-value">{{ loading.stats ? "—" : stats.total_users }}</div>
                                <div class="ur-stat-label">Pengguna dengan Role</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="ur-stat ur-stat-green">
                            <div class="ur-stat-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="ur-stat-body">
                                <div class="ur-stat-value">{{ loading.stats ? "—" : stats.total_roles }}</div>
                                <div class="ur-stat-label">Total Role Tersedia</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-4">
                        <div class="ur-stat ur-stat-cyan">
                            <div class="ur-stat-icon"><i class="fas fa-link"></i></div>
                            <div class="ur-stat-body">
                                <div class="ur-stat-value">{{ loading.stats ? "—" : stats.total_assignments }}</div>
                                <div class="ur-stat-label">Total Penetapan Role</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Accordion Card ═══ -->
        <div class="card shadow-sm border-0">
            <!-- Toolbar -->
            <div class="card-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-sm">
                            <span class="avatar-title bg-primary-subtle rounded fs-4">
                                <i class="ri-user-settings-line text-primary"></i>
                            </span>
                        </span>
                        <div>
                            <h5 class="card-title mb-0 fw-semibold">Daftar Pengguna &amp; Role</h5>
                            <small class="text-muted">Klik pengguna untuk melihat detail hak akses</small>
                        </div>
                    </div>
                    <div class="input-group input-group-sm" style="max-width: 280px">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control border-start-0"
                            placeholder="Cari pengguna atau role..."
                            v-model="search"
                            @input="onSearch"
                        />
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <!-- Skeleton -->
                <template v-if="loading.list">
                    <div v-for="n in 5" :key="n" class="ur-acc-skeleton border-bottom"></div>
                </template>

                <!-- Empty -->
                <div v-else-if="groupedList.length === 0" class="text-center py-5 text-muted">
                    <i class="ri-inbox-line d-block mb-2" style="font-size: 2.5rem"></i>
                    <p class="mb-1 fw-medium">Belum ada penetapan role</p>
                    <small>{{ search ? "Coba kata kunci lain" : "Mulai dengan menekan tombol Tambah User Role" }}</small>
                </div>

                <!-- Accordion -->
                <div v-else class="accordion accordion-flush" id="urAccordion">
                    <div
                        v-for="(user, idx) in groupedList"
                        :key="user.Id_User"
                        class="accordion-item"
                    >
                        <h2 class="accordion-header">
                            <button
                                class="accordion-button py-3"
                                :class="{ collapsed: openAccordion !== user.Id_User }"
                                type="button"
                                @click="toggleAccordion(user.Id_User)"
                            >
                                <div class="d-flex align-items-center gap-3 w-100 pe-2">
                                    <div class="ur-avatar-circle">
                                        {{ initials(user.Nama) }}
                                    </div>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-semibold text-dark" style="font-size: 0.9rem">{{ user.Nama }}</div>
                                        <small class="text-muted">{{ user.Id_User }}</small>
                                    </div>
                                    <span class="badge bg-primary rounded-pill me-2" style="font-size: 0.75rem">
                                        {{ user.roles.length }} role
                                    </span>
                                </div>
                            </button>
                        </h2>
                        <div
                            class="accordion-collapse collapse"
                            :class="{ show: openAccordion === user.Id_User }"
                        >
                            <div class="accordion-body pt-3 pb-4" style="background: #f8fafc">
                                <p class="text-muted small mb-3">
                                    <i class="ri-shield-check-line me-1"></i>
                                    Hak akses yang dimiliki <strong>{{ user.Nama }}</strong>:
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <div
                                        v-for="role in user.roles"
                                        :key="role.Id_Role"
                                        class="ur-role-chip"
                                        :title="role.Deskripsi || role.Nama_Role"
                                    >
                                        <i class="fas fa-shield-alt me-1" style="font-size: 0.7rem; opacity: 0.7"></i>
                                        <span>{{ role.Nama_Role }}</span>
                                        <button
                                            class="ur-role-del"
                                            @click.stop="confirmDelete({ Id_User: user.Id_User, Nama: user.Nama, Id_Role: role.Id_Role, Nama_Role: role.Nama_Role })"
                                            title="Hapus role ini"
                                        >
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Offcanvas Tambah User Role (Multi-select) ═══ -->
        <div
            class="offcanvas offcanvas-end"
            tabindex="-1"
            id="offcanvasAddUserRole"
            aria-labelledby="offcanvasAddUserRoleLabel"
            style="width: 500px"
        >
            <div class="offcanvas-header border-bottom" style="background: #1e40af">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm">
                        <span class="avatar-title rounded" style="background: rgba(255,255,255,0.2)">
                            <i class="ri-user-add-line fs-5 text-white"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-semibold text-white" id="offcanvasAddUserRoleLabel">Tambah User Role</h5>
                        <small class="text-white opacity-75">Pilih satu atau banyak pengguna &amp; role</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" @click="closeOffcanvas"></button>
            </div>

            <div class="offcanvas-body p-0 d-flex flex-column">
                <div class="flex-grow-1 overflow-auto p-4">
                    <!-- Pilih Pengguna -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold mb-0">
                                Pengguna <span class="text-danger">*</span>
                            </label>
                            <span class="badge bg-primary-subtle text-primary" v-if="form.Id_Users.length > 0">
                                {{ form.Id_Users.length }} dipilih
                            </span>
                        </div>
                        <div class="ur-multiselect-box">
                            <div class="ur-multiselect-search">
                                <i class="ri-search-line text-muted"></i>
                                <input
                                    type="text"
                                    placeholder="Cari pengguna..."
                                    v-model="userSearch"
                                    class="ur-multiselect-input"
                                />
                            </div>
                            <div class="ur-multiselect-list">
                                <label
                                    v-for="u in filteredUserOptions"
                                    :key="u.UserId"
                                    class="ur-multiselect-item"
                                    :class="{ 'is-selected': form.Id_Users.includes(u.UserId) }"
                                >
                                    <input
                                        type="checkbox"
                                        :value="u.UserId"
                                        v-model="form.Id_Users"
                                        class="ur-multiselect-cb"
                                    />
                                    <div class="ur-multiselect-info">
                                        <span class="fw-medium">{{ u.Nama }}</span>
                                        <small class="text-muted">{{ u.UserId }}</small>
                                    </div>
                                    <i v-if="form.Id_Users.includes(u.UserId)" class="ri-check-line text-primary ms-auto"></i>
                                </label>
                                <div v-if="filteredUserOptions.length === 0" class="text-center text-muted py-3 small">
                                    Tidak ada pengguna ditemukan
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pilih Role -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <label class="form-label fw-semibold mb-0">
                                Role <span class="text-danger">*</span>
                            </label>
                            <span class="badge bg-success-subtle text-success" v-if="form.Id_Roles.length > 0">
                                {{ form.Id_Roles.length }} dipilih
                            </span>
                        </div>
                        <div class="ur-multiselect-box">
                            <div class="ur-multiselect-search">
                                <i class="ri-search-line text-muted"></i>
                                <input
                                    type="text"
                                    placeholder="Cari role..."
                                    v-model="roleSearch"
                                    class="ur-multiselect-input"
                                />
                            </div>
                            <div class="ur-multiselect-list">
                                <label
                                    v-for="r in filteredRoleOptions"
                                    :key="r.Id_Role"
                                    class="ur-multiselect-item"
                                    :class="{ 'is-selected': form.Id_Roles.includes(r.Id_Role) }"
                                >
                                    <input
                                        type="checkbox"
                                        :value="r.Id_Role"
                                        v-model="form.Id_Roles"
                                        class="ur-multiselect-cb"
                                    />
                                    <div class="ur-multiselect-info">
                                        <span class="fw-medium">{{ r.Nama_Role }}</span>
                                        <small class="text-muted" v-if="r.Deskripsi">{{ r.Deskripsi }}</small>
                                    </div>
                                    <i v-if="form.Id_Roles.includes(r.Id_Role)" class="ri-check-line text-success ms-auto"></i>
                                </label>
                                <div v-if="filteredRoleOptions.length === 0" class="text-center text-muted py-3 small">
                                    Tidak ada role ditemukan
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview kombinasi -->
                    <div
                        class="alert border-0 rounded-3 py-3 small"
                        :class="combinationCount > 0 ? 'alert-primary' : 'alert-secondary'"
                        v-if="form.Id_Users.length > 0 || form.Id_Roles.length > 0"
                    >
                        <div class="d-flex align-items-start gap-2">
                            <i class="ri-information-line flex-shrink-0 mt-1"></i>
                            <div>
                                <template v-if="combinationCount > 0">
                                    Akan menambahkan <strong>{{ combinationCount }} kombinasi</strong>
                                    ({{ form.Id_Users.length }} pengguna &times; {{ form.Id_Roles.length }} role).
                                    <div class="mt-1 text-muted">Kombinasi yang sudah ada akan dilewati otomatis.</div>
                                </template>
                                <template v-else>
                                    Pilih minimal 1 pengguna dan 1 role untuk melanjutkan.
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer -->
                <div class="border-top bg-light px-4 py-3 d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-light btn-sm" @click="closeOffcanvas">
                        <i class="ri-close-line me-1"></i>Batal
                    </button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="loading.submit || combinationCount === 0"
                        @click="submitAdd"
                    >
                        <span v-if="loading.submit" class="spinner-border spinner-border-sm me-1" role="status"></span>
                        <i v-else class="ri-save-3-line me-1"></i>
                        Simpan {{ combinationCount > 0 ? `(${combinationCount})` : '' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";

export default {
    name: "UserRole",

    data() {
        return {
            groupedList: [],
            optionUsers: [],
            optionRoles: [],
            stats: { total_users: 0, total_roles: 0, total_assignments: 0 },

            form: { Id_Users: [], Id_Roles: [] },
            userSearch: "",
            roleSearch: "",
            search: "",
            searchTimeout: null,

            openAccordion: null,

            loading: { list: false, stats: false, submit: false },
            offcanvasInstance: null,
        };
    },

    computed: {
        filteredUserOptions() {
            const q = this.userSearch.toLowerCase();
            if (!q) return this.optionUsers;
            return this.optionUsers.filter(
                (u) =>
                    u.Nama.toLowerCase().includes(q) ||
                    u.UserId.toLowerCase().includes(q)
            );
        },
        filteredRoleOptions() {
            const q = this.roleSearch.toLowerCase();
            if (!q) return this.optionRoles;
            return this.optionRoles.filter(
                (r) =>
                    r.Nama_Role.toLowerCase().includes(q) ||
                    (r.Deskripsi || "").toLowerCase().includes(q)
            );
        },
        combinationCount() {
            return this.form.Id_Users.length * this.form.Id_Roles.length;
        },
    },

    mounted() {
        this.fetchGroupedList();
        this.fetchStats();
        this.fetchOptionUsers();
        this.fetchOptionRoles();

        const el = document.getElementById("offcanvasAddUserRole");
        if (el) {
            this.offcanvasInstance = new bootstrap.Offcanvas(el, {
                backdrop: true,
                keyboard: false,
            });
        }
    },

    methods: {
        initials(name) {
            return name
                .split(" ")
                .slice(0, 2)
                .map((w) => w[0])
                .join("")
                .toUpperCase();
        },

        toggleAccordion(userId) {
            this.openAccordion = this.openAccordion === userId ? null : userId;
        },

        openOffcanvas() {
            this.form = { Id_Users: [], Id_Roles: [] };
            this.userSearch = "";
            this.roleSearch = "";
            this.offcanvasInstance?.show();
        },

        closeOffcanvas() {
            this.offcanvasInstance?.hide();
        },

        async fetchGroupedList() {
            this.loading.list = true;
            try {
                const res = await axios.get("/api/v1/user-role/grouped", {
                    params: { search: this.search },
                });
                this.groupedList = res.data.success ? res.data.data : [];
            } catch {
                this.groupedList = [];
            } finally {
                this.loading.list = false;
            }
        },

        async fetchStats() {
            this.loading.stats = true;
            try {
                const res = await axios.get("/api/v1/user-role/stats");
                if (res.data.success) this.stats = res.data.data;
            } finally {
                this.loading.stats = false;
            }
        },

        async fetchOptionUsers() {
            try {
                const res = await axios.get("/api/v1/user-role/options/users");
                if (res.data.success) this.optionUsers = res.data.data;
            } catch {}
        },

        async fetchOptionRoles() {
            try {
                const res = await axios.get("/api/v1/user-role/options/roles");
                if (res.data.success) this.optionRoles = res.data.data;
            } catch {}
        },

        onSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.openAccordion = null;
                this.fetchGroupedList();
            }, 400);
        },

        async submitAdd() {
            if (this.combinationCount === 0) return;
            this.loading.submit = true;
            try {
                const res = await axios.post("/api/v1/user-role/store", {
                    Id_Users: this.form.Id_Users,
                    Id_Roles: this.form.Id_Roles,
                });
                if (res.data.success) {
                    this.closeOffcanvas();
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: res.data.message,
                        timer: 2500,
                        showConfirmButton: false,
                        timerProgressBar: true,
                    });
                    this.fetchGroupedList();
                    this.fetchStats();
                } else {
                    Swal.fire("Gagal!", res.data.message, "error");
                }
            } catch (e) {
                Swal.fire(
                    "Gagal!",
                    e.response?.data?.message || "Terjadi Kesalahan",
                    "error"
                );
            } finally {
                this.loading.submit = false;
            }
        },

        confirmDelete(row) {
            Swal.fire({
                title: "Hapus Role Pengguna?",
                html: `Hapus role <strong>${row.Nama_Role}</strong> dari pengguna <strong>${row.Nama}</strong>?<br><small class="text-muted">Tindakan ini tidak dapat dibatalkan.</small>`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#ef4444",
                cancelButtonColor: "#6b7280",
                confirmButtonText: "Ya, Hapus",
                cancelButtonText: "Batal",
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                try {
                    const res = await axios.delete("/api/v1/user-role/delete", {
                        data: { Id_User: row.Id_User, Id_Role: row.Id_Role },
                    });
                    if (res.data.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil!",
                            text: res.data.message,
                            timer: 2000,
                            showConfirmButton: false,
                        });
                        this.fetchGroupedList();
                        this.fetchStats();
                    } else {
                        Swal.fire("Gagal!", res.data.message, "error");
                    }
                } catch (e) {
                    Swal.fire(
                        "Gagal!",
                        e.response?.data?.message || "Terjadi Kesalahan",
                        "error"
                    );
                }
            });
        },
    },
};
</script>

<style scoped>
/* ── Stats ── */
.ur-stat {
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.ur-stat-blue  { background: linear-gradient(135deg, #eff6ff, #dbeafe); }
.ur-stat-green { background: linear-gradient(135deg, #f0fdf4, #dcfce7); }
.ur-stat-cyan  { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); }
.ur-stat-icon  { font-size: 1.8rem; opacity: 0.75; }
.ur-stat-blue  .ur-stat-icon { color: #3b82f6; }
.ur-stat-green .ur-stat-icon { color: #22c55e; }
.ur-stat-cyan  .ur-stat-icon { color: #0ea5e9; }
.ur-stat-value { font-size: 1.5rem; font-weight: 700; color: #1e293b; line-height: 1.2; }
.ur-stat-label { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

/* ── Skeleton ── */
.ur-acc-skeleton {
    height: 64px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 37%, #f0f0f0 63%);
    background-size: 400% 100%;
    animation: ur-shimmer 1.4s infinite;
}
@keyframes ur-shimmer {
    0%   { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* ── Accordion ── */
.accordion-item { border-left: 0; border-right: 0; }
.accordion-item:first-child { border-top: 0; }
.accordion-button {
    background: #fff;
    font-size: 0.875rem;
}
.accordion-button:not(.collapsed) {
    background: #f0f6ff;
    color: #1e40af;
    box-shadow: none;
}
.accordion-button:focus { box-shadow: none; }
.accordion-button::after {
    flex-shrink: 0;
    margin-left: 8px;
}

/* ── User avatar circle ── */
.ur-avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    letter-spacing: 0.5px;
}

/* ── Role chip ── */
.ur-role-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 5px 10px 5px 12px;
    font-size: 0.8rem;
    font-weight: 500;
    color: #334155;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.ur-role-del {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border: none;
    background: #fee2e2;
    color: #ef4444;
    border-radius: 50%;
    cursor: pointer;
    font-size: 0.75rem;
    padding: 0;
    line-height: 1;
    transition: background 0.15s;
}
.ur-role-del:hover { background: #ef4444; color: #fff; }

/* ── Multi-select box ── */
.ur-multiselect-box {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}
.ur-multiselect-search {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-bottom: 1px solid #f1f5f9;
    background: #f8fafc;
}
.ur-multiselect-input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.85rem;
    flex: 1;
    color: #334155;
}
.ur-multiselect-input::placeholder { color: #94a3b8; }
.ur-multiselect-list {
    max-height: 200px;
    overflow-y: auto;
}
.ur-multiselect-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f8fafc;
    transition: background 0.1s;
    margin: 0;
}
.ur-multiselect-item:last-child { border-bottom: none; }
.ur-multiselect-item:hover { background: #f1f5f9; }
.ur-multiselect-item.is-selected { background: #eff6ff; }
.ur-multiselect-cb {
    width: 16px;
    height: 16px;
    accent-color: #3b82f6;
    flex-shrink: 0;
    cursor: pointer;
}
.ur-multiselect-info {
    display: flex;
    flex-direction: column;
    min-width: 0;
    font-size: 0.85rem;
    flex: 1;
}
.ur-multiselect-info small { font-size: 0.75rem; line-height: 1.2; margin-top: 1px; }
</style>
