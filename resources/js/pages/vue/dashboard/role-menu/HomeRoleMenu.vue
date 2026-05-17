<template>
    <div>
        <!-- Page Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="fs-16 fw-semibold mb-1">
                    <i class="ri-menu-2-line me-2 text-primary"></i>Pengaturan Menu Akses
                </h4>
                <p class="text-muted small mb-0">
                    Pengguna: <strong class="text-dark">{{ UserId }}</strong>
                    &mdash; Susun menu dalam grup bersarang (Nama Header → Sub Header → Menu)
                </p>
            </div>
            <a href="/role/home-menu" class="btn btn-light btn-sm">
                <i class="ri-arrow-left-line me-1"></i>Kembali
            </a>
        </div>

        <!-- Loading -->
        <div v-if="loading.page" class="row g-3">
            <div class="col-12 col-md-5 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="skeleton mb-3" style="height:34px;border-radius:6px"></div>
                        <div v-for="i in 8" :key="i" class="skeleton mb-2" style="height:44px;border-radius:6px"></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-7 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div v-for="i in 3" :key="i" class="skeleton mb-3" style="height:120px;border-radius:10px"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Builder -->
        <div v-else class="row g-3 align-items-start">

            <!-- ══════════ LEFT: Available menus ══════════ -->
            <div class="col-12 col-md-5 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent border-bottom-0 pt-3 pb-0 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="card-title mb-0 fw-semibold">
                                <i class="ri-list-check-2 me-2 text-primary"></i>Menu Tersedia
                            </h6>
                            <span class="badge bg-primary-subtle text-primary rounded-pill">{{ leftItems.length }}</span>
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="ri-search-line text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 bg-light"
                                   placeholder="Cari menu..." v-model="searchQuery" />
                        </div>
                    </div>

                    <div class="card-body p-2 left-panel-body">
                        <div v-if="filteredLeft.length === 0" class="text-center py-4">
                            <i class="ri-checkbox-circle-line fs-32 text-success mb-2 d-block"></i>
                            <p class="text-muted small mb-0">
                                {{ searchQuery ? 'Menu tidak ditemukan' : 'Semua menu sudah ditambahkan' }}
                            </p>
                        </div>
                        <div v-for="menu in filteredLeft" :key="menu.Id_Menu"
                             class="left-item"
                             :class="{ selected: selectedLeft.includes(menu.Id_Menu), 'drag-out': drag.type === 'left' && drag.item && drag.item.Id_Menu === menu.Id_Menu }"
                             draggable="true"
                             @dragstart="onDragStartLeft($event, menu)"
                             @dragend="resetDrag">
                            <div class="d-flex align-items-center gap-2 w-100">
                                <input type="checkbox" class="form-check-input mt-0 flex-shrink-0"
                                       :checked="selectedLeft.includes(menu.Id_Menu)"
                                       @change="toggleSelect(menu.Id_Menu)" @click.stop />
                                <i :class="menu.Icon_Menu || 'ri-circle-line'" class="fs-15 text-primary flex-shrink-0"></i>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="fw-semibold small lh-sm text-truncate">{{ menu.Nama_Menu }}</div>
                                    <div class="text-muted" style="font-size:11px">{{ menu.Url_Menu }}</div>
                                </div>
                                <i class="ri-drag-move-2-fill text-muted fs-16 flex-shrink-0 left-drag-hint"></i>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-top py-2 px-3">
                        <!-- Cascading: pilih grup lalu sub-grup -->
                        <div v-if="groups.length > 0" class="mb-2">
                            <select class="form-select form-select-sm mb-1" v-model="tgtGi" @change="onTargetGroupChange">
                                <option v-for="(g, gi) in groups" :key="g._id" :value="gi">
                                    {{ g.nama_header || '(Standalone / Tanpa Header)' }}
                                </option>
                            </select>
                            <select v-if="tgtSubs.length > 0" class="form-select form-select-sm" v-model="tgtSi">
                                <option v-for="(sg, si) in tgtSubs" :key="sg._id" :value="si">
                                    {{ sg.sub_header || '(Langsung di Grup)' }}
                                </option>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-sm w-100 fw-medium"
                                @click="addSelectedToTarget"
                                :disabled="selectedLeft.length === 0 || groups.length === 0">
                            <i class="ri-add-line me-1"></i>Tambahkan ke Grup
                            <span v-if="selectedLeft.length" class="badge bg-white text-primary ms-1 rounded-pill">{{ selectedLeft.length }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══════════ RIGHT: Tree structure ══════════ -->
            <div class="col-12 col-md-7 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent border-bottom py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
                            <h6 class="card-title mb-0 fw-semibold">
                                <i class="ri-sitemap-line me-2 text-primary"></i>Struktur Menu (Tree)
                            </h6>
                            <div class="d-flex gap-2">
                                <span class="badge bg-success-subtle text-success rounded-pill">{{ totalItems }} menu</span>
                                <button class="btn btn-outline-primary btn-sm" @click="addGroup">
                                    <i class="ri-add-line me-1"></i>Tambah Grup
                                </button>
                            </div>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="ri-lightbulb-line me-1 text-warning"></i>
                            Klik nama untuk edit &bull; Seret <i class="ri-drag-move-2-fill"></i> untuk atur urutan &bull;
                            Drag menu dari kiri ke drop zone
                        </p>
                    </div>

                    <div class="card-body p-3">
                        <div v-if="groups.length === 0" class="text-center py-5">
                            <i class="ri-inbox-line fs-40 text-muted mb-3 d-block"></i>
                            <p class="text-muted mb-3">Belum ada grup. Buat grup terlebih dahulu.</p>
                            <button class="btn btn-primary" @click="addGroup">
                                <i class="ri-add-line me-1"></i>Buat Grup Pertama
                            </button>
                        </div>

                        <!-- GROUPS (Nama_Header level) -->
                        <div v-for="(group, gi) in groups" :key="group._id" class="mb-3">

                            <!-- ── Standalone section (Nama_Header = null) ── -->
                            <div v-if="group.nama_header === null" class="standalone-section">
                                <div class="standalone-label">
                                    <i class="ri-layout-left-2-line me-1"></i>Standalone
                                    <span class="text-muted ms-1">(tanpa header)</span>
                                    <button class="btn btn-sm p-0 ms-auto text-danger" @click="deleteGroup(gi)" title="Hapus bagian ini">
                                        <i class="ri-delete-bin-line fs-14"></i>
                                    </button>
                                </div>
                                <!-- null sub-group items rendered inline -->
                                <div v-for="(sg, si) in group.sub_groups" :key="sg._id">
                                    <div class="items-drop-zone"
                                         @dragover.prevent="onDragOverSG(gi, si)"
                                         @dragleave.self="clearHover()"
                                         @drop="onDropSG($event, gi, si)"
                                         :class="{ 'drop-active': drag.hGi === gi && drag.hSi === si && drag.type !== 'group' && drag.type !== 'sg' }">
                                        <div v-if="sg.items.length === 0" class="empty-drop-hint"
                                             :class="{ active: drag.hGi === gi && drag.hSi === si }">
                                            <i class="ri-drag-drop-line me-1"></i>Seret menu ke sini
                                        </div>
                                        <div v-for="(item, ii) in sg.items" :key="item.Id_Menu"
                                             class="tree-item"
                                             :class="{ 'item-dragging': drag.type === 'item' && drag.fromGi === gi && drag.fromSi === si && drag.fromIi === ii,
                                                        'item-over': drag.hGi === gi && drag.hSi === si && drag.hIi === ii }"
                                             draggable="true"
                                             @dragstart="onDragStartItem($event, gi, si, ii)"
                                             @dragover.prevent.stop="onDragOverItem(gi, si, ii)"
                                             @dragleave.stop="clearHoverItem(gi, si, ii)"
                                             @drop.stop="onDropItem($event, gi, si, ii)"
                                             @dragend="resetDrag">
                                            <span class="item-handle"><i class="ri-drag-move-2-fill"></i></span>
                                            <span class="item-pos">{{ ii + 1 }}</span>
                                            <i :class="item.Icon_Menu || 'ri-circle-line'" class="fs-14 text-primary flex-shrink-0"></i>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <div class="fw-semibold small text-truncate lh-sm">{{ item.Nama_Menu }}</div>
                                                <div class="text-muted" style="font-size:11px">{{ item.Url_Menu }}</div>
                                            </div>
                                            <button class="btn btn-sm item-del" @click.stop="removeItem(gi, si, ii)" title="Hapus">
                                                <i class="ri-close-line text-danger fs-15"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Named group (Nama_Header) ── -->
                            <div v-else class="group-box"
                                 :class="{
                                     'group-over': drag.hGi === gi && drag.type === 'group' && drag.fromGi !== gi,
                                     'group-self-drag': drag.type === 'group' && drag.fromGi === gi
                                 }">

                                <!-- Group header -->
                                <div class="group-header"
                                     draggable="true"
                                     @dragstart="onDragStartGroup($event, gi)"
                                     @dragover.prevent="onDragOverGroup(gi)"
                                     @drop="onDropGroup($event, gi)"
                                     @dragend="resetDrag">
                                    <span class="group-handle"><i class="ri-drag-move-2-fill"></i></span>

                                    <div class="flex-grow-1 mx-2">
                                        <input v-if="group._editing"
                                               type="text" class="form-control form-control-sm group-input"
                                               v-model="group._tmp"
                                               placeholder="Nama Header (contoh: MASTER DATA)..."
                                               @blur="saveGroup(gi)" @keyup.enter="saveGroup(gi)" @keyup.escape="group._editing=false"
                                               :ref="'gi_' + gi" @click.stop />
                                        <div v-else class="group-name-display" @click.stop="editGroup(gi)">
                                            <span class="group-name-text">{{ group.nama_header }}</span>
                                            <i class="ri-pencil-line ms-2 group-edit-icon text-primary"></i>
                                        </div>
                                    </div>

                                    <span class="badge bg-white border text-secondary me-1" style="font-size:11px">{{ countGroupItems(gi) }}</span>
                                    <button class="btn btn-sm p-1 ms-1 group-del-btn" @click.stop="addSubGroup(gi)" title="Tambah Sub-Grup">
                                        <i class="ri-folder-add-line fs-14 text-primary"></i>
                                    </button>
                                    <button class="btn btn-sm p-1 ms-1 group-del-btn" @click.stop="deleteGroup(gi)" title="Hapus grup">
                                        <i class="ri-delete-bin-line fs-14 text-danger"></i>
                                    </button>
                                </div>

                                <!-- Group body: sub-groups (Sub_Header level) -->
                                <div class="group-body">
                                    <div v-for="(sg, si) in group.sub_groups" :key="sg._id" class="mb-2">

                                        <!-- ── Null sub-header: items directly in group ── -->
                                        <div v-if="sg.sub_header === null">
                                            <div class="items-drop-zone"
                                                 @dragover.prevent="onDragOverSG(gi, si)"
                                                 @dragleave.self="clearHover()"
                                                 @drop="onDropSG($event, gi, si)"
                                                 :class="{ 'drop-active': drag.hGi === gi && drag.hSi === si && drag.type !== 'group' && drag.type !== 'sg' }">
                                                <div v-if="sg.items.length === 0" class="empty-drop-hint"
                                                     :class="{ active: drag.hGi === gi && drag.hSi === si }">
                                                    <i class="ri-drag-drop-line me-1"></i>Seret menu ke sini
                                                </div>
                                                <div v-for="(item, ii) in sg.items" :key="item.Id_Menu"
                                                     class="tree-item"
                                                     :class="{ 'item-dragging': drag.type === 'item' && drag.fromGi === gi && drag.fromSi === si && drag.fromIi === ii,
                                                                'item-over': drag.hGi === gi && drag.hSi === si && drag.hIi === ii }"
                                                     draggable="true"
                                                     @dragstart="onDragStartItem($event, gi, si, ii)"
                                                     @dragover.prevent.stop="onDragOverItem(gi, si, ii)"
                                                     @dragleave.stop="clearHoverItem(gi, si, ii)"
                                                     @drop.stop="onDropItem($event, gi, si, ii)"
                                                     @dragend="resetDrag">
                                                    <span class="item-handle"><i class="ri-drag-move-2-fill"></i></span>
                                                    <span class="item-pos">{{ ii + 1 }}</span>
                                                    <i :class="item.Icon_Menu || 'ri-circle-line'" class="fs-14 text-primary flex-shrink-0"></i>
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <div class="fw-semibold small text-truncate lh-sm">{{ item.Nama_Menu }}</div>
                                                        <div class="text-muted" style="font-size:11px">{{ item.Url_Menu }}</div>
                                                    </div>
                                                    <button class="btn btn-sm item-del" @click.stop="removeItem(gi, si, ii)" title="Hapus">
                                                        <i class="ri-close-line text-danger fs-15"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ── Named sub-header (Sub_Header) ── -->
                                        <div v-else class="sub-group-box"
                                             :class="{
                                                 'sg-over': drag.hGi === gi && drag.hSi === si && drag.type === 'sg' && drag.fromSi !== si,
                                                 'sg-self-drag': drag.type === 'sg' && drag.fromGi === gi && drag.fromSi === si
                                             }">

                                            <!-- Sub-group header -->
                                            <div class="sub-group-header"
                                                 draggable="true"
                                                 @dragstart="onDragStartSG($event, gi, si)"
                                                 @dragover.prevent="onDragOverSGHeader(gi, si)"
                                                 @drop="onDropSGHeader($event, gi, si)"
                                                 @dragend="resetDrag">
                                                <span class="sg-handle"><i class="ri-drag-move-2-fill"></i></span>

                                                <div class="flex-grow-1 mx-1">
                                                    <input v-if="sg._editing"
                                                           type="text" class="form-control form-control-sm sg-input"
                                                           v-model="sg._tmp"
                                                           placeholder="Nama Sub-Header..."
                                                           @blur="saveSG(gi, si)" @keyup.enter="saveSG(gi, si)" @keyup.escape="sg._editing=false"
                                                           :ref="'si_' + gi + '_' + si" @click.stop />
                                                    <div v-else class="sg-name-display" @click.stop="editSG(gi, si)">
                                                        <span class="sg-name-text">{{ sg.sub_header }}</span>
                                                        <i class="ri-pencil-line ms-1 sg-edit-icon text-primary"></i>
                                                    </div>
                                                </div>

                                                <span class="badge bg-white border text-secondary me-1" style="font-size:10px">{{ sg.items.length }}</span>
                                                <button class="btn btn-sm p-1 sg-del-btn" @click.stop="deleteSG(gi, si)" title="Hapus sub-grup">
                                                    <i class="ri-close-line fs-14 text-danger"></i>
                                                </button>
                                            </div>

                                            <!-- Sub-group items drop zone -->
                                            <div class="items-drop-zone sub-items"
                                                 @dragover.prevent="onDragOverSG(gi, si)"
                                                 @dragleave.self="clearHover()"
                                                 @drop="onDropSG($event, gi, si)"
                                                 :class="{ 'drop-active': drag.hGi === gi && drag.hSi === si && drag.type !== 'group' && drag.type !== 'sg' }">
                                                <div v-if="sg.items.length === 0" class="empty-drop-hint"
                                                     :class="{ active: drag.hGi === gi && drag.hSi === si }">
                                                    <i class="ri-drag-drop-line me-1"></i>Seret menu ke sini
                                                </div>
                                                <div v-for="(item, ii) in sg.items" :key="item.Id_Menu"
                                                     class="tree-item"
                                                     :class="{ 'item-dragging': drag.type === 'item' && drag.fromGi === gi && drag.fromSi === si && drag.fromIi === ii,
                                                                'item-over': drag.hGi === gi && drag.hSi === si && drag.hIi === ii }"
                                                     draggable="true"
                                                     @dragstart="onDragStartItem($event, gi, si, ii)"
                                                     @dragover.prevent.stop="onDragOverItem(gi, si, ii)"
                                                     @dragleave.stop="clearHoverItem(gi, si, ii)"
                                                     @drop.stop="onDropItem($event, gi, si, ii)"
                                                     @dragend="resetDrag">
                                                    <span class="item-handle"><i class="ri-drag-move-2-fill"></i></span>
                                                    <span class="item-pos">{{ ii + 1 }}</span>
                                                    <i :class="item.Icon_Menu || 'ri-circle-line'" class="fs-14 text-primary flex-shrink-0"></i>
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <div class="fw-semibold small text-truncate lh-sm">{{ item.Nama_Menu }}</div>
                                                        <div class="text-muted" style="font-size:11px">{{ item.Url_Menu }}</div>
                                                    </div>
                                                    <button class="btn btn-sm item-del" @click.stop="removeItem(gi, si, ii)" title="Hapus">
                                                        <i class="ri-close-line text-danger fs-15"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-top py-3 px-3">
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <small class="text-muted">
                                <i class="ri-information-line me-1"></i>
                                {{ groups.length }} grup &bull; {{ totalItems }} menu total
                            </small>
                            <button class="btn btn-success fw-medium px-4"
                                    @click="saveMenu" :disabled="loading.saving || totalItems === 0">
                                <span v-if="loading.saving">
                                    <span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...
                                </span>
                                <span v-else>
                                    <i class="ri-save-2-line me-1"></i>Simpan Menu
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";

let _uid = 0;
const uid = () => `u${++_uid}`;

export default {
    props: { UserId: { type: [String, Number], default: null } },

    data() {
        return {
            groups: [],       // [ { _id, nama_header, _editing, _tmp, sub_groups: [ { _id, sub_header, _editing, _tmp, items:[] } ] } ]
            leftItems: [],
            selectedLeft: [],
            searchQuery: "",

            tgtGi: 0,        // selected group index for left-panel add button
            tgtSi: 0,        // selected sub-group index

            drag: {
                type: null,  // 'left' | 'item' | 'sg' | 'group'
                item: null,
                fromGi: null, fromSi: null, fromIi: null,
                hGi: null, hSi: null, hIi: null,
            },

            loading: { page: false, saving: false },
        };
    },

    computed: {
        filteredLeft() {
            if (!this.searchQuery) return this.leftItems;
            const q = this.searchQuery.toLowerCase();
            return this.leftItems.filter(m =>
                (m.Nama_Menu || "").toLowerCase().includes(q) ||
                (m.Url_Menu || "").toLowerCase().includes(q)
            );
        },
        totalItems() {
            return this.groups.reduce((s, g) => s + g.sub_groups.reduce((ss, sg) => ss + sg.items.length, 0), 0);
        },
        tgtSubs() {
            return this.groups[this.tgtGi]?.sub_groups ?? [];
        },
    },

    methods: {
        /* ── Data ── */
        async fetchData() {
            this.loading.page = true;
            try {
                const [aRes, vRes] = await Promise.all([
                    axios.get(`/api/v1/role-menu/all/${this.UserId}`).catch(() => ({ data: {} })),
                    axios.get(`/api/v1/role-menu/available/${this.UserId}`).catch(() => ({ data: {} })),
                ]);
                this.groups = this.buildTree(aRes.data?.data || []);
                this.leftItems = vRes.data?.data || [];
                this.tgtGi = 0;
                this.tgtSi = 0;
            } finally {
                this.loading.page = false;
            }
        },

        buildTree(items) {
            const gMap = new Map();
            const gOrder = [];

            for (const item of items) {
                const nh = item.Nama_Header ?? null;
                const sh = item.Sub_Header ?? null;

                if (!gMap.has(nh)) {
                    gMap.set(nh, { _id: uid(), nama_header: nh, _editing: false, _tmp: nh || "", sub_groups: new Map(), _sgOrder: [] });
                    gOrder.push(nh);
                }
                const g = gMap.get(nh);

                if (!g.sub_groups.has(sh)) {
                    g.sub_groups.set(sh, { _id: uid(), sub_header: sh, _editing: false, _tmp: sh || "", items: [] });
                    g._sgOrder.push(sh);
                }
                g.sub_groups.get(sh).items.push({ ...item });
            }

            return gOrder.map(nh => {
                const g = gMap.get(nh);
                return { ...g, sub_groups: g._sgOrder.map(sh => g.sub_groups.get(sh)) };
            });
        },

        makeGroup(nh = "") {
            return { _id: uid(), nama_header: nh, _editing: !nh, _tmp: nh, sub_groups: [this.makeSG(null)] };
        },
        makeSG(sh = null) {
            return { _id: uid(), sub_header: sh, _editing: false, _tmp: sh || "", items: [] };
        },

        /* ── Left panel ── */
        toggleSelect(Id_Menu) {
            const idx = this.selectedLeft.indexOf(Id_Menu);
            if (idx > -1) this.selectedLeft.splice(idx, 1);
            else this.selectedLeft.push(Id_Menu);
        },

        onTargetGroupChange() {
            this.tgtSi = 0;
        },

        addSelectedToTarget() {
            const group = this.groups[this.tgtGi];
            if (!group) return;

            const subs = group.sub_groups;
            let sg = subs[this.tgtSi] ?? subs[0];
            if (!sg) {
                sg = this.makeSG(null);
                group.sub_groups.push(sg);
            }

            const toAdd = this.leftItems.filter(m => this.selectedLeft.includes(m.Id_Menu));
            sg.items.push(...toAdd);
            this.leftItems = this.leftItems.filter(m => !this.selectedLeft.includes(m.Id_Menu));
            this.selectedLeft = [];
        },

        /* ── Group CRUD ── */
        addGroup() {
            const g = this.makeGroup("");
            g._editing = true;
            this.groups.push(g);
            this.tgtGi = this.groups.length - 1;
            this.tgtSi = 0;
            const gi = this.groups.length - 1;
            this.$nextTick(() => { const el = this.$refs["gi_" + gi]; (Array.isArray(el) ? el[0] : el)?.focus(); });
        },

        editGroup(gi) {
            const g = this.groups[gi];
            g._tmp = g.nama_header || "";
            g._editing = true;
            this.$nextTick(() => { const el = this.$refs["gi_" + gi]; (Array.isArray(el) ? el[0] : el)?.focus(); });
        },

        saveGroup(gi) {
            const g = this.groups[gi];
            g.nama_header = g._tmp.trim() || null;
            g._editing = false;
        },

        deleteGroup(gi) {
            const g = this.groups[gi];
            for (const sg of g.sub_groups) this.leftItems.push(...sg.items);
            this.groups.splice(gi, 1);
            this.tgtGi = Math.min(this.tgtGi, this.groups.length - 1);
        },

        countGroupItems(gi) {
            return this.groups[gi].sub_groups.reduce((s, sg) => s + sg.items.length, 0);
        },

        /* ── Sub-group CRUD ── */
        addSubGroup(gi) {
            const sg = this.makeSG("");
            sg._editing = true;
            this.groups[gi].sub_groups.push(sg);
            const si = this.groups[gi].sub_groups.length - 1;
            this.$nextTick(() => { const el = this.$refs["si_" + gi + "_" + si]; (Array.isArray(el) ? el[0] : el)?.focus(); });
        },

        editSG(gi, si) {
            const sg = this.groups[gi].sub_groups[si];
            sg._tmp = sg.sub_header || "";
            sg._editing = true;
            this.$nextTick(() => { const el = this.$refs["si_" + gi + "_" + si]; (Array.isArray(el) ? el[0] : el)?.focus(); });
        },

        saveSG(gi, si) {
            const sg = this.groups[gi].sub_groups[si];
            sg.sub_header = sg._tmp.trim() || null;
            sg._editing = false;
        },

        deleteSG(gi, si) {
            const [sg] = this.groups[gi].sub_groups.splice(si, 1);
            this.leftItems.push(...sg.items);
        },

        /* ── Item remove ── */
        removeItem(gi, si, ii) {
            const [item] = this.groups[gi].sub_groups[si].items.splice(ii, 1);
            this.leftItems.push(item);
        },

        /* ── Drag: Left panel ── */
        onDragStartLeft(e, item) {
            this.drag.type = "left";
            this.drag.item = item;
            e.dataTransfer.effectAllowed = "copy";
        },

        /* ── Drag: Group reorder ── */
        onDragStartGroup(e, gi) {
            if (this.groups[gi]._editing) { e.preventDefault(); return; }
            this.drag.type = "group";
            this.drag.fromGi = gi;
            e.dataTransfer.effectAllowed = "move";
        },
        onDragOverGroup(gi) { if (this.drag.type === "group") this.drag.hGi = gi; },
        onDropGroup(e, gi) {
            if (this.drag.type === "group" && this.drag.fromGi !== gi) {
                const gs = [...this.groups];
                const [moved] = gs.splice(this.drag.fromGi, 1);
                gs.splice(this.drag.fromGi < gi ? gi - 1 : gi, 0, moved);
                this.groups = gs;
            }
            this.resetDrag();
        },

        /* ── Drag: Sub-group reorder ── */
        onDragStartSG(e, gi, si) {
            if (this.groups[gi].sub_groups[si]._editing) { e.preventDefault(); return; }
            this.drag.type = "sg";
            this.drag.fromGi = gi;
            this.drag.fromSi = si;
            e.dataTransfer.effectAllowed = "move";
        },
        onDragOverSGHeader(gi, si) { if (this.drag.type === "sg") { this.drag.hGi = gi; this.drag.hSi = si; } },
        onDropSGHeader(e, gi, si) {
            if (this.drag.type === "sg" && this.drag.fromGi === gi && this.drag.fromSi !== si) {
                const sgs = [...this.groups[gi].sub_groups];
                const [moved] = sgs.splice(this.drag.fromSi, 1);
                sgs.splice(this.drag.fromSi < si ? si - 1 : si, 0, moved);
                this.groups[gi].sub_groups = sgs;
            }
            this.resetDrag();
        },

        /* ── Drag: Sub-group area (receive items) ── */
        onDragOverSG(gi, si) {
            if (this.drag.type === "group" || this.drag.type === "sg") return;
            this.drag.hGi = gi;
            this.drag.hSi = si;
            this.drag.hIi = null;
        },
        onDropSG(e, gi, si) {
            if (this.drag.type === "group" || this.drag.type === "sg") return;
            const tg = this.groups[gi].sub_groups[si];
            if (this.drag.type === "left" && this.drag.item) {
                tg.items.push({ ...this.drag.item });
                this.leftItems = this.leftItems.filter(m => m.Id_Menu !== this.drag.item.Id_Menu);
                this.selectedLeft = this.selectedLeft.filter(id => id !== this.drag.item.Id_Menu);
            } else if (this.drag.type === "item") {
                const src = this.groups[this.drag.fromGi].sub_groups[this.drag.fromSi];
                const [item] = src.items.splice(this.drag.fromIi, 1);
                tg.items.push(item);
            }
            this.resetDrag();
        },

        /* ── Drag: Item (reorder or cross-group move) ── */
        onDragStartItem(e, gi, si, ii) {
            this.drag.type = "item";
            this.drag.fromGi = gi; this.drag.fromSi = si; this.drag.fromIi = ii;
            e.dataTransfer.effectAllowed = "move";
        },
        onDragOverItem(gi, si, ii) {
            if (this.drag.type !== "item" && this.drag.type !== "left") return;
            this.drag.hGi = gi; this.drag.hSi = si; this.drag.hIi = ii;
        },
        clearHoverItem(gi, si, ii) {
            if (this.drag.hGi === gi && this.drag.hSi === si && this.drag.hIi === ii) this.drag.hIi = null;
        },
        onDropItem(e, gi, si, ii) {
            const tItems = this.groups[gi].sub_groups[si].items;
            if (this.drag.type === "left" && this.drag.item) {
                tItems.splice(ii, 0, { ...this.drag.item });
                this.leftItems = this.leftItems.filter(m => m.Id_Menu !== this.drag.item.Id_Menu);
                this.selectedLeft = this.selectedLeft.filter(id => id !== this.drag.item.Id_Menu);
            } else if (this.drag.type === "item") {
                const sgi = this.drag.fromGi, ssi = this.drag.fromSi, sii = this.drag.fromIi;
                if (sgi === gi && ssi === si) {
                    if (sii !== ii) {
                        const arr = [...tItems];
                        const [m] = arr.splice(sii, 1);
                        arr.splice(sii < ii ? ii - 1 : ii, 0, m);
                        this.groups[gi].sub_groups[si].items = arr;
                    }
                } else {
                    const [item] = this.groups[sgi].sub_groups[ssi].items.splice(sii, 1);
                    tItems.splice(ii, 0, item);
                }
            }
            this.resetDrag();
        },

        clearHover() {
            this.drag.hGi = null; this.drag.hSi = null; this.drag.hIi = null;
        },

        resetDrag() {
            this.drag = { type: null, item: null, fromGi: null, fromSi: null, fromIi: null, hGi: null, hSi: null, hIi: null };
        },

        /* ── Save ── */
        async saveMenu() {
            if (this.totalItems === 0) { Swal.fire("Peringatan", "Tidak ada menu.", "warning"); return; }
            this.loading.saving = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
                const items = [];
                let order = 1;
                for (const g of this.groups) {
                    for (const sg of g.sub_groups) {
                        for (const item of sg.items) {
                            items.push({
                                Id_Menu: item.Id_Menu,
                                Urutan_Menu: order++,
                                Nama_Header: g.nama_header || null,
                                Sub_Header: sg.sub_header || null,
                                Sub_Sub_Header: null,
                            });
                        }
                    }
                }
                const res = await axios.post(`/api/v1/page-access/batch-save/${this.UserId}`, { items }, { headers: { "X-CSRF-TOKEN": csrf } });
                if (res.data?.success) {
                    await this.fetchData();
                    Swal.fire({ icon: "success", title: "Berhasil", text: res.data.message || "Menu berhasil disimpan", timer: 1500, showConfirmButton: false });
                } else {
                    throw new Error(res.data?.message || "Gagal menyimpan");
                }
            } catch (err) {
                Swal.fire("Error", err.response?.data?.message || err.message || "Terjadi kesalahan", "error");
            } finally {
                this.loading.saving = false;
            }
        },
    },

    mounted() { this.fetchData(); },
};
</script>

<style scoped>
/* ══ LEFT ══ */
.left-panel-body { max-height: 520px; overflow-y: auto; }

.left-item {
    display: flex; align-items: center; padding: 8px 10px;
    border: 1px solid #e9ebec; border-radius: 6px; margin-bottom: 4px;
    cursor: grab; background: #fff;
    transition: border-color 0.15s, background 0.15s;
    user-select: none;
}
.left-item:hover { border-color: #405189; background: #f3f6f9; }
.left-item.selected { border-color: #405189; background: #eef1fd; }
.left-item.drag-out { opacity: 0.3; }
.left-item:active { cursor: grabbing; }
.left-drag-hint { opacity: 0.2; transition: opacity 0.15s; }
.left-item:hover .left-drag-hint { opacity: 0.7; }

/* ══ STANDALONE SECTION ══ */
.standalone-section {
    border: 1px dashed #ced4da; border-radius: 8px;
    padding: 8px; background: #fafbfc;
}
.standalone-label {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600; color: #6c757d;
    text-transform: uppercase; letter-spacing: 0.5px;
    padding: 4px 6px; margin-bottom: 6px;
}

/* ══ GROUP BOX (Nama_Header) ══ */
.group-box {
    border: 1px solid #dee2e6; border-radius: 10px; overflow: hidden;
    transition: box-shadow 0.15s, border-color 0.15s;
}
.group-box.group-over { border-color: #405189; box-shadow: 0 0 0 3px rgba(64,81,137,.12); }
.group-box.group-self-drag { opacity: 0.4; }

.group-header {
    display: flex; align-items: center; padding: 10px 12px;
    background: linear-gradient(135deg, #eef1fd 0%, #e4e9f8 100%);
    border-bottom: 1px solid #dee2e6;
    cursor: grab; user-select: none;
}
.group-header:hover { background: linear-gradient(135deg, #e4e9f8 0%, #dae0f5 100%); }
.group-header:active { cursor: grabbing; }

.group-handle { color: #adb5bd; font-size: 18px; flex-shrink: 0; transition: color 0.15s; }
.group-header:hover .group-handle { color: #405189; }

.group-name-display {
    display: inline-flex; align-items: center; cursor: pointer;
    padding: 3px 8px; border-radius: 4px; border: 1px dashed transparent;
    transition: all 0.15s;
}
.group-name-display:hover { border-color: #405189; background: rgba(64,81,137,.06); }
.group-name-text { font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 0.8px; color: #344563; }
.group-edit-icon { font-size: 11px; opacity: 0; transition: opacity 0.15s; }
.group-name-display:hover .group-edit-icon { opacity: 1; }
.group-input { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; max-width: 260px; border-color: #405189; }
.group-del-btn { opacity: 0.3; transition: opacity 0.15s; }
.group-header:hover .group-del-btn { opacity: 1; }

.group-body { padding: 8px; background: #fafbfc; }

/* ══ SUB-GROUP BOX (Sub_Header) ══ */
.sub-group-box {
    border: 1px solid #e9ebec; border-radius: 8px; overflow: hidden;
    background: #fff; transition: border-color 0.15s;
}
.sub-group-box.sg-over { border-color: #0ab39c; box-shadow: 0 0 0 2px rgba(10,179,156,.12); }
.sub-group-box.sg-self-drag { opacity: 0.35; }

.sub-group-header {
    display: flex; align-items: center; padding: 7px 10px;
    background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f9 100%);
    border-bottom: 1px solid #e9ebec;
    cursor: grab; user-select: none;
}
.sub-group-header:hover { background: linear-gradient(135deg, #eef1fd 0%, #e8ecf8 100%); }
.sub-group-header:active { cursor: grabbing; }

.sg-handle { color: #adb5bd; font-size: 16px; flex-shrink: 0; transition: color 0.15s; }
.sub-group-header:hover .sg-handle { color: #405189; }

.sg-name-display {
    display: inline-flex; align-items: center; cursor: pointer;
    padding: 2px 6px; border-radius: 4px; border: 1px dashed transparent; transition: all 0.15s;
}
.sg-name-display:hover { border-color: #405189; background: rgba(64,81,137,.06); }
.sg-name-text { font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #495057; }
.sg-edit-icon { font-size: 11px; opacity: 0; transition: opacity 0.15s; }
.sg-name-display:hover .sg-edit-icon { opacity: 1; }
.sg-input { font-size: 11px; font-weight: 600; text-transform: uppercase; max-width: 200px; border-color: #405189; }
.sg-del-btn { opacity: 0.25; transition: opacity 0.15s; }
.sub-group-header:hover .sg-del-btn { opacity: 1; }

/* ══ ITEMS DROP ZONE ══ */
.items-drop-zone {
    padding: 6px; min-height: 44px;
    transition: background 0.15s;
}
.items-drop-zone.sub-items { padding: 6px 8px 6px 12px; }
.items-drop-zone.drop-active { background: rgba(10,179,156,.05); }

.empty-drop-hint {
    border: 2px dashed #dee2e6; border-radius: 6px; padding: 12px;
    text-align: center; color: #adb5bd; font-size: 12px; transition: all 0.2s;
}
.empty-drop-hint.active { border-color: #0ab39c; color: #0ab39c; background: #e8f8f6; }

/* ══ TREE ITEM ══ */
.tree-item {
    display: flex; align-items: center; gap: 7px;
    padding: 7px 9px; border: 1px solid #e9ebec; border-radius: 6px; margin-bottom: 4px;
    background: #fff; cursor: grab;
    transition: border-color 0.15s, background 0.15s, opacity 0.15s;
    user-select: none;
}
.tree-item:last-child { margin-bottom: 0; }
.tree-item:hover { border-color: #405189; background: #f3f6f9; }
.tree-item:active { cursor: grabbing; }
.tree-item.item-dragging { opacity: 0.2; }
.tree-item.item-over { border-color: #405189; border-style: dashed; background: #eef1fd; }

.item-handle { color: #adb5bd; font-size: 16px; flex-shrink: 0; transition: color 0.15s; }
.tree-item:hover .item-handle { color: #405189; }

.item-pos {
    width: 19px; height: 19px; border-radius: 50%;
    background: #f1f3f9; color: #64748b; font-size: 10px; font-weight: 700;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

.item-del { flex-shrink: 0; padding: 2px 5px; opacity: 0.2; transition: opacity 0.15s; line-height: 1; }
.tree-item:hover .item-del { opacity: 1; }

/* ══ SKELETON ══ */
.skeleton {
    display: block;
    background: linear-gradient(90deg, #e9ebec 25%, #f3f6f9 50%, #e9ebec 75%);
    background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 6px;
}
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

@media (max-width: 767.98px) { .left-panel-body { max-height: 300px; } }
</style>
