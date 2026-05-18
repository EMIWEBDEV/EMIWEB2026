<template>
    <div class="container-fluid qlms-wrap">
        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- HERO BANNER                                               -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="qlms-hero mb-4">
            <div class="qlms-hero-bg"></div>
            <div class="qlms-hero-mesh"></div>

            <div class="qlms-hero-inner">
                <!-- Kiri: identitas -->
                <div class="qlms-hero-left">
                    <div class="qlms-hero-avatar">
                        <i class="ri-microscope-line"></i>
                    </div>
                    <div>
                        <div class="qlms-hero-tag">
                            LIMS · Executive Monitoring
                        </div>
                        <h2 class="qlms-hero-name text-white">
                            Selamat, {{ namaPengguna }}
                        </h2>
                        <p class="qlms-hero-desc">
                            Panel Kinerja Operasional Laboratorium —
                            <strong>PT. Evo Manufacturing Indonesia</strong>
                        </p>
                    </div>
                </div>

                <!-- Kanan: jam + kontrol -->
                <div class="qlms-hero-right">
                    <div class="qlms-hero-clock-block">
                        <div class="qlms-clock">{{ liveClock }}</div>
                        <div class="qlms-date">{{ currentDate }}</div>
                    </div>
                    <div
                        class="d-flex align-items-center gap-2 flex-wrap justify-content-end"
                    >
                        <span class="qlms-periode-pill" v-if="kpi.periode">
                            <i class="ri-calendar-event-line"></i>
                            Periode: <strong>{{ kpi.periode }}</strong>
                        </span>
                        <button
                            class="qlms-refresh-btn"
                            @click="refreshAll"
                            :disabled="anyLoading"
                        >
                            <i
                                :class="
                                    anyLoading
                                        ? 'ri-loader-4-line qlms-spin'
                                        : 'ri-refresh-line'
                                "
                            ></i>
                            Segarkan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- OVERVIEW STRIP — seperti panel attendance HCIS            -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body pb-0">
                <div
                    class="d-flex align-items-center justify-content-between mb-3"
                >
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-xs">
                            <span
                                class="avatar-title bg-primary-subtle rounded fs-5"
                            >
                                <i class="ri-pulse-line text-primary"></i>
                            </span>
                        </span>
                        <div>
                            <h6 class="mb-0 fw-semibold fs-14">
                                Ringkasan Operasional Bulan Berjalan
                            </h6>
                            <small class="text-muted"
                                >Data kumulatif sejak awal bulan sampai hari
                                ini</small
                            >
                        </div>
                    </div>
                    <span
                        v-if="kpi.periode"
                        class="badge bg-primary rounded-pill fs-12"
                    >
                        {{ kpi.periode }}
                    </span>
                </div>

                <!-- Skeleton strip -->
                <div v-if="loading.kpi" class="qlms-strip-grid pb-3">
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="qlms-strip-box qlms-sk-box"
                    >
                        <div class="qlms-sk-line w-50 mb-2 qlms-shimmer"></div>
                        <div class="qlms-sk-line w-75 qlms-shimmer"></div>
                    </div>
                </div>

                <!-- Data strip -->
                <div v-else class="qlms-strip-grid pb-3">
                    <div
                        v-for="(c, i) in kpiCards"
                        :key="i"
                        class="qlms-strip-box"
                        :style="{ '--strip-c': c.color }"
                    >
                        <div class="qlms-strip-val">{{ c.value }}</div>
                        <div class="qlms-strip-lbl">{{ c.label }}</div>
                        <div class="qlms-strip-sub">{{ c.sub }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ALERT CARDS (4)                                           -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <div
                class="col-xl-3 col-sm-6"
                v-for="(a, i) in alertCards"
                :key="i"
            >
                <div
                    class="card card-animate border-0 shadow-sm h-100"
                    :class="a.cardClass"
                >
                    <div class="card-body">
                        <div
                            class="d-flex align-items-start justify-content-between"
                        >
                            <div class="flex-grow-1">
                                <p
                                    class="text-uppercase fw-medium fs-11 mb-1"
                                    :class="a.labelClass"
                                >
                                    {{ a.tag }}
                                </p>
                                <div v-if="loading.kpi">
                                    <div
                                        class="qlms-sk-line w-50 qlms-shimmer mb-1"
                                        style="height: 28px; border-radius: 6px"
                                    ></div>
                                </div>
                                <h3
                                    v-else
                                    class="fw-bold mb-1"
                                    :class="a.valClass"
                                >
                                    {{ a.value }}
                                </h3>
                                <p class="text-muted fs-12 mb-0">
                                    {{ a.desc }}
                                </p>
                            </div>
                            <div
                                class="qlms-icon-sm flex-shrink-0 ms-2"
                                :style="{ background: a.iconBgHex }"
                            >
                                <i :class="a.icon" :style="{ color: a.iconFgHex }"></i>
                            </div>
                        </div>
                        <!-- Progress bar -->
                        <div
                            class="mt-3"
                            v-if="!loading.kpi && a.pct !== undefined"
                        >
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">{{
                                    a.pctLabel
                                }}</small>
                                <small :class="a.labelClass"
                                    >{{ a.pct }}%</small
                                >
                            </div>
                            <div class="progress" style="height: 5px">
                                <div
                                    class="progress-bar"
                                    :class="a.barClass"
                                    :style="{
                                        width: Math.min(a.pct, 100) + '%',
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- TREN AKTIVITAS — full width                               -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <div
                    class="d-flex align-items-start justify-content-between flex-wrap gap-2"
                >
                    <div>
                        <h6 class="card-title fw-semibold mb-1">
                            Tren Aktivitas Laboratorium {{ currentYear }}
                        </h6>
                        <p class="text-muted fs-12 mb-0">
                            Registrasi sampel masuk, pengujian selesai, dan
                            validasi final per bulan
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="qlms-legend-pill" style="--lc: #405189">
                            <span class="qlms-dot"></span> Registrasi Sampel
                        </span>
                        <span class="qlms-legend-pill" style="--lc: #0ab39c">
                            <span class="qlms-dot"></span> Pengujian Selesai
                        </span>
                        <span class="qlms-legend-pill" style="--lc: #f7b84b">
                            <span class="qlms-dot"></span> Validasi Final
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body pt-2">
                <div
                    v-if="loading.tren"
                    class="qlms-chart-sk qlms-shimmer"
                ></div>
                <apexchart
                    v-else
                    type="area"
                    height="300"
                    :options="trenOptions"
                    :series="trenSeries"
                />
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ROW: Beban Mesin (6) + Status Keseluruhan (4)             -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <!-- Beban Mesin -->
            <div class="col-xl-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="card-title fw-semibold mb-1">
                            Beban Kerja Per Mesin Analisa
                        </h6>
                        <p class="text-muted fs-12 mb-0">
                            Total sampel terdaftar vs. pengujian selesai per
                            mesin
                        </p>
                    </div>
                    <div class="card-body pt-2">
                        <div
                            v-if="loading.beban"
                            class="qlms-chart-sk qlms-shimmer"
                            style="height: 280px"
                        ></div>
                        <apexchart
                            v-else
                            type="bar"
                            height="280"
                            :options="bebanOptions"
                            :series="bebanSeries"
                        />
                    </div>
                </div>
            </div>

            <!-- Status Donut -->
            <div class="col-xl-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="card-title fw-semibold mb-1">
                            Distribusi Status Sampel
                        </h6>
                        <p class="text-muted fs-12 mb-0">
                            Proporsi seluruh status sampel sejak awal
                            operasional
                        </p>
                    </div>
                    <div
                        class="card-body pt-2 d-flex align-items-center justify-content-center"
                    >
                        <div
                            v-if="loading.kpi"
                            class="qlms-donut-sk qlms-shimmer"
                        ></div>
                        <apexchart
                            v-else
                            type="donut"
                            height="280"
                            :options="statusDonutOptions"
                            :series="statusDonutSeries"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ROW: Pass Rate + Produktivitas Analis                     -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="row g-3 mb-4">
            <!-- Pass Rate per Jenis Analisa -->
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <div
                            class="d-flex align-items-start justify-content-between"
                        >
                            <div>
                                <h6 class="card-title fw-semibold mb-1">
                                    Pass Rate per Jenis Analisa
                                </h6>
                                <p class="text-muted fs-12 mb-0">
                                    Persentase hasil layak terhadap total
                                    pengujian selesai
                                </p>
                            </div>
                            <span
                                class="badge bg-danger-subtle text-danger fs-11"
                            >
                                <i class="ri-flag-2-line me-1"></i>Target ≥ 95%
                            </span>
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <div
                            v-if="loading.passRate"
                            class="qlms-chart-sk qlms-shimmer"
                            style="height: 300px"
                        ></div>
                        <apexchart
                            v-else
                            type="bar"
                            height="300"
                            :options="passRateOptions"
                            :series="passRateSeries"
                        />
                    </div>
                </div>
            </div>

            <!-- Top Analis -->
            <div class="col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="card-title fw-semibold mb-1">
                            Produktivitas Analis — Bulan Ini
                        </h6>
                        <p class="text-muted fs-12 mb-0">
                            Peringkat analis berdasarkan jumlah pengujian yang
                            diselesaikan
                        </p>
                    </div>
                    <div class="card-body pt-2">
                        <div
                            v-if="loading.topAnalis"
                            class="qlms-chart-sk qlms-shimmer"
                            style="height: 300px"
                        ></div>
                        <apexchart
                            v-else
                            type="bar"
                            height="300"
                            :options="topAnalisOptions"
                            :series="topAnalisSeries"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- RINGKASAN VALIDASI FINAL                                  -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                <h6 class="card-title fw-semibold mb-1">
                    Ringkasan Validasi Final — Bulan Ini
                </h6>
                <p class="text-muted fs-12 mb-0">
                    Status persetujuan dan kualitas hasil pengujian akhir oleh
                    Quality Control
                </p>
            </div>
            <div class="card-body">
                <!-- Skeleton -->
                <div v-if="loading.kpi" class="d-flex gap-3">
                    <div
                        v-for="n in 6"
                        :key="n"
                        class="flex-fill qlms-shimmer rounded-3"
                        style="height: 80px"
                    ></div>
                </div>

                <!-- Data -->
                <div v-else class="qlms-validasi-grid">
                    <div
                        class="qlms-vs-item"
                        v-for="(v, i) in validasiItems"
                        :key="i"
                    >
                        <div
                            class="qlms-vs-icon"
                            :style="{ background: v.bg, color: v.color }"
                        >
                            <i :class="v.icon"></i>
                        </div>
                        <div>
                            <div class="qlms-vs-val">{{ v.value }}</div>
                            <div class="qlms-vs-lbl">{{ v.label }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════ -->
        <!-- ALL-TIME REKAP                                            -->
        <!-- ══════════════════════════════════════════════════════════ -->
        <div class="qlms-section-label mb-3">
            <i class="ri-database-2-line"></i>
            Rekap Kumulatif Keseluruhan
        </div>
        <div class="row g-3 mb-4">
            <div
                class="col-xl-3 col-sm-6"
                v-for="(s, i) in allTimeCards"
                :key="i"
            >
                <div class="card card-animate border-0 shadow-sm h-100">
                    <div class="card-body text-center py-4">
                        <div
                            class="qlms-icon-lg mx-auto mb-3"
                            :style="{ background: s.iconBgHex }"
                        >
                            <i :class="s.icon" :style="{ color: s.iconFgHex, fontSize: '2rem' }"></i>
                        </div>
                        <div v-if="loading.kpi">
                            <div
                                class="qlms-sk-line w-50 qlms-shimmer mx-auto mb-2"
                                style="height: 28px; border-radius: 6px"
                            ></div>
                        </div>
                        <h3 v-else class="fw-bold mb-1" :class="s.valClass">
                            {{ s.value.toLocaleString("id-ID") }}
                        </h3>
                        <p class="text-muted fs-13 mb-0">{{ s.label }}</p>
                        <div class="mt-2">
                            <span
                                class="badge rounded-pill fs-11"
                                :class="s.badgeClass"
                                >{{ s.badge }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import VueApexCharts from "vue3-apexcharts";

export default {
    name: "DashboardAtasanLab",
    components: { apexchart: VueApexCharts },

    props: {
        namaPengguna: { type: String, default: "Pimpinan" },
    },

    data() {
        const now = new Date();
        return {
            currentDate: now.toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            }),
            currentYear: now.getFullYear(),
            liveClock: "",
            clockTimer: null,

            kpi: {},
            tren: { categories: [], series: [] },
            beban: { categories: [], total: [], selesai: [] },
            passRate: { categories: [], data: [] },
            topAnalis: { categories: [], data: [] },

            loading: {
                kpi: true,
                tren: true,
                beban: true,
                passRate: true,
                topAnalis: true,
            },
        };
    },

    computed: {
        anyLoading() {
            return Object.values(this.loading).some(Boolean);
        },

        /* ── Strip KPI ── */
        kpiCards() {
            const k = this.kpi;
            return [
                {
                    color: "#405189",
                    label: "Sampel Masuk",
                    sub: "Registrasi bulan ini",
                    value: (k.sampel_bulan_ini ?? 0).toLocaleString("id-ID"),
                },
                {
                    color: "#0ab39c",
                    label: "Pengujian Selesai",
                    sub: "Selesai dianalisa",
                    value: (k.uji_selesai ?? 0).toLocaleString("id-ID"),
                },
                {
                    color: "#4b93f7",
                    label: "Pass Rate",
                    sub: "Hasil layak / total uji",
                    value: (k.pass_rate_pct ?? 0) + "%",
                },
                {
                    color: "#f7b84b",
                    label: "Rata-rata TAT",
                    sub: "Turn Around Time uji",
                    value: `${k.tat_hours ?? 0}j ${k.tat_minutes ?? 0}m`,
                },
                {
                    color: "#6f42c1",
                    label: "Validasi Final",
                    sub: "Divalidasi QC bulan ini",
                    value: (k.validasi_bulan ?? 0).toLocaleString("id-ID"),
                },
                {
                    color: "#f06548",
                    label: "Trial Produksi",
                    sub: "Sampel trial bulan ini",
                    value: (k.sampel_trial ?? 0).toLocaleString("id-ID"),
                },
            ];
        },

        /* ── Alert Cards ── */
        alertCards() {
            const k = this.kpi;
            const pr = k.pass_rate_pct ?? 0;
            const prOk = pr >= 95;
            const selesaiPct =
                (k.sampel_bulan_ini ?? 0) > 0
                    ? Math.round(
                          ((k.sampel_selesai ?? 0) / k.sampel_bulan_ini) * 100
                      )
                    : 0;
            const validasiPct =
                (k.validasi_bulan ?? 0) > 0
                    ? Math.round(
                          ((k.validasi_ok ?? 0) / k.validasi_bulan) * 100
                      )
                    : 0;

            return [
                {
                    tag: "Sampel Proses",
                    value: (k.sampel_bulan_ini ?? 0).toLocaleString("id-ID"),
                    desc: "Total registrasi masuk bulan ini",
                    icon: "ri-flask-2-line",
                    iconBgHex: "rgba(64,81,137,.14)",
                    iconFgHex: "#405189",
                    cardClass: "",
                    labelClass: "text-primary",
                    valClass: "text-primary",
                    pct: selesaiPct,
                    pctLabel: "Telah selesai diuji",
                    barClass: "bg-primary",
                },
                {
                    tag: "Pass Rate Uji",
                    value: pr + "%",
                    desc: prOk ? "Persentase baik" : "Perlu ditingkatkan",
                    icon: prOk ? "ri-shield-check-line" : "ri-alert-line",
                    iconBgHex: prOk ? "rgba(10,179,156,.14)" : "rgba(240,101,72,.14)",
                    iconFgHex: prOk ? "#0ab39c" : "#f06548",
                    cardClass: "",
                    labelClass: prOk ? "text-success" : "text-danger",
                    valClass: prOk ? "text-success" : "text-danger",
                    pct: pr,
                    pctLabel: "Actual",
                    barClass: prOk ? "bg-success" : "bg-danger",
                },
                {
                    tag: "Validasi Disetujui",
                    value: (k.validasi_ok ?? 0).toLocaleString("id-ID"),
                    desc: `Approval rate ${validasiPct}% dari ${k.validasi_bulan ?? 0} validasi`,
                    icon: "ri-check-double-line",
                    iconBgHex: "rgba(10,179,156,.14)",
                    iconFgHex: "#0ab39c",
                    cardClass: "",
                    labelClass: "text-success",
                    valClass: "text-success",
                    pct: validasiPct,
                    pctLabel: "Persetujuan QC",
                    barClass: "bg-success",
                },
                {
                    tag: "Trial Produksi",
                    value: (k.sampel_trial ?? 0).toLocaleString("id-ID"),
                    desc: "Sampel uji untuk trial batch produksi",
                    icon: "ri-flask-line",
                    iconBgHex: "rgba(247,184,75,.14)",
                    iconFgHex: "#d08f00",
                    cardClass: "",
                    labelClass: "text-warning",
                    valClass: "text-warning",
                },
            ];
        },

        /* ── Validasi Items ── */
        validasiItems() {
            const k = this.kpi;
            const total = k.validasi_bulan ?? 0;
            const ok = k.validasi_ok ?? 0;
            const okPct = total > 0 ? ((ok / total) * 100).toFixed(1) : "0.0";
            return [
                {
                    icon: "ri-file-list-3-line",
                    bg: "rgba(64,81,137,.12)",
                    color: "#405189",
                    label: "Total Validasi",
                    value: total.toLocaleString("id-ID"),
                },
                {
                    icon: "ri-checkbox-circle-line",
                    bg: "rgba(10,179,156,.12)",
                    color: "#0ab39c",
                    label: "Disetujui (OK)",
                    value: ok.toLocaleString("id-ID"),
                },
                {
                    icon: "ri-trophy-line",
                    bg: "rgba(75,147,247,.12)",
                    color: "#4b93f7",
                    label: "Finish Good (FG)",
                    value: (k.validasi_fg ?? 0).toLocaleString("id-ID"),
                },
                {
                    icon: "ri-percent-line",
                    bg: "rgba(247,184,75,.12)",
                    color: "#f7b84b",
                    label: "Approval Rate",
                    value: okPct + "%",
                },
                {
                    icon: "ri-timer-2-line",
                    bg: "rgba(111,66,193,.12)",
                    color: "#6f42c1",
                    label: "Rata-rata TAT",
                    value: `${k.tat_hours ?? 0}j ${k.tat_minutes ?? 0}m`,
                },
                {
                    icon: "ri-shield-check-line",
                    bg: "rgba(240,101,72,.12)",
                    color: "#f06548",
                    label: "Pass Rate Uji",
                    value: (k.pass_rate_pct ?? 0) + "%",
                },
            ];
        },

        /* ── All-time cards ── */
        allTimeCards() {
            const k = this.kpi;
            return [
                {
                    icon: "ri-checkbox-circle-line",
                    iconBgHex: "rgba(10,179,156,.14)",
                    iconFgHex: "#0ab39c",
                    label: "Total Sampel Selesai",
                    value: k.selesai_all ?? 0,
                    valClass: "text-success",
                    badge: "Selesai Dianalisa",
                    badgeClass: "bg-success-subtle text-success",
                },
                {
                    icon: "ri-time-line",
                    iconBgHex: "rgba(247,184,75,.14)",
                    iconFgHex: "#d08f00",
                    label: "Sampel Menunggu Proses",
                    value: k.pending_all ?? 0,
                    valClass: "text-warning",
                    badge: "Belum Selesai",
                    badgeClass: "bg-warning-subtle text-warning",
                },
                {
                    icon: "ri-flask-line",
                    iconBgHex: "rgba(75,147,247,.14)",
                    iconFgHex: "#4b93f7",
                    label: "Trial Produksi",
                    value: k.trial_all ?? 0,
                    valClass: "text-info",
                    badge: "Batch Trial",
                    badgeClass: "bg-info-subtle text-info",
                },
                {
                    icon: "ri-lock-2-line",
                    iconBgHex: "rgba(240,101,72,.14)",
                    iconFgHex: "#f06548",
                    label: "Close PO",
                    value: k.close_po_all ?? 0,
                    valClass: "text-danger",
                    badge: "PO Ditutup",
                    badgeClass: "bg-danger-subtle text-danger",
                },
            ];
        },

        /* ── Tren ── */
        trenOptions() {
            return {
                chart: {
                    type: "area",
                    toolbar: { show: false },
                    fontFamily: 'Inter, "Segoe UI", sans-serif',
                    animations: {
                        enabled: true,
                        easing: "easeinout",
                        speed: 600,
                    },
                },
                colors: ["#405189", "#0ab39c", "#f7b84b"],
                fill: {
                    type: "gradient",
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.02,
                        stops: [0, 100],
                    },
                },
                stroke: { curve: "smooth", width: [2.5, 2.5, 2] },
                xaxis: {
                    categories: this.tren.categories,
                    labels: {
                        style: {
                            fontSize: "12px",
                            fontFamily: "Inter, sans-serif",
                        },
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: { labels: { style: { fontSize: "11px" } } },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: { formatter: (v) => v.toLocaleString("id-ID") },
                },
                legend: { show: false },
                grid: {
                    borderColor: "#f1f5f9",
                    strokeDashArray: 4,
                    padding: { left: 0, right: 0 },
                },
                dataLabels: { enabled: false },
                markers: { size: 0, hover: { size: 5 } },
            };
        },
        trenSeries() {
            return this.tren.series;
        },

        /* ── Beban Mesin ── */
        bebanOptions() {
            return {
                chart: {
                    type: "bar",
                    toolbar: { show: false },
                    fontFamily: 'Inter, "Segoe UI", sans-serif',
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: "60%",
                        dataLabels: { position: "top" },
                    },
                },
                colors: ["#405189", "#0ab39c"],
                xaxis: {
                    categories: this.beban.categories,
                    labels: {
                        style: { fontSize: "11px" },
                        formatter: (v) => v.toLocaleString("id-ID"),
                    },
                },
                yaxis: {
                    labels: { style: { fontSize: "11px" }, maxWidth: 130 },
                },
                legend: {
                    position: "top",
                    fontSize: "12px",
                    fontFamily: "Inter, sans-serif",
                },
                dataLabels: { enabled: false },
                grid: { borderColor: "#f1f5f9", strokeDashArray: 3 },
                tooltip: {
                    shared: true,
                    intersect: false,
                    y: {
                        formatter: (v) => v.toLocaleString("id-ID") + " sampel",
                    },
                },
            };
        },
        bebanSeries() {
            return [
                { name: "Total Sampel", data: this.beban.total },
                { name: "Pengujian Selesai", data: this.beban.selesai },
            ];
        },

        /* ── Status Donut ── */
        statusDonutOptions() {
            return {
                chart: {
                    type: "donut",
                    fontFamily: 'Inter, "Segoe UI", sans-serif',
                },
                labels: [
                    "Selesai",
                    "Menunggu Proses",
                    "Trial Produksi",
                    "Close PO",
                ],
                colors: ["#0ab39c", "#f7b84b", "#4b93f7", "#f06548"],
                legend: {
                    position: "bottom",
                    fontSize: "12px",
                    fontFamily: "Inter, sans-serif",
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: "72%",
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: "Total Sampel",
                                    fontSize: "12px",
                                    fontWeight: 700,
                                    formatter: (w) =>
                                        w.globals.seriesTotals
                                            .reduce((a, b) => a + b, 0)
                                            .toLocaleString("id-ID"),
                                },
                            },
                        },
                    },
                },
                dataLabels: { enabled: false },
                stroke: { width: 2, colors: ["#fff"] },
                tooltip: {
                    y: {
                        formatter: (v) => v.toLocaleString("id-ID") + " sampel",
                    },
                },
            };
        },
        statusDonutSeries() {
            const k = this.kpi;
            return [
                k.selesai_all ?? 0,
                k.pending_all ?? 0,
                k.trial_all ?? 0,
                k.close_po_all ?? 0,
            ];
        },

        /* ── Pass Rate ── */
        passRateOptions() {
            return {
                chart: {
                    type: "bar",
                    toolbar: { show: false },
                    fontFamily: 'Inter, "Segoe UI", sans-serif',
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: "55%",
                        dataLabels: { position: "top" },
                    },
                },
                colors: [
                    ({ value }) =>
                        value >= 95
                            ? "#0ab39c"
                            : value >= 80
                            ? "#f7b84b"
                            : "#f06548",
                ],
                xaxis: {
                    categories: this.passRate.categories,
                    min: 0,
                    max: 100,
                    labels: {
                        formatter: (v) => v + "%",
                        style: { fontSize: "11px" },
                    },
                },
                yaxis: {
                    labels: { style: { fontSize: "11px" }, maxWidth: 140 },
                },
                dataLabels: {
                    enabled: true,
                    formatter: (v) => v + "%",
                    style: { fontSize: "11px", colors: ["#495057"] },
                    offsetX: 28,
                },
                annotations: {
                    xaxis: [
                        {
                            x: 95,
                            borderColor: "#f06548",
                            strokeDashArray: 5,
                            label: {
                                text: "Target 95%",
                                style: {
                                    color: "#f06548",
                                    fontSize: "11px",
                                    background: "transparent",
                                    fontWeight: 600,
                                },
                            },
                        },
                    ],
                },
                grid: { borderColor: "#f1f5f9", strokeDashArray: 3 },
                legend: { show: false },
            };
        },
        passRateSeries() {
            return [{ name: "Pass Rate", data: this.passRate.data }];
        },

        /* ── Top Analis ── */
        topAnalisOptions() {
            return {
                chart: {
                    type: "bar",
                    toolbar: { show: false },
                    fontFamily: 'Inter, "Segoe UI", sans-serif',
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        borderRadius: 4,
                        barHeight: "55%",
                        distributed: true,
                    },
                },
                colors: [
                    "#405189",
                    "#0ab39c",
                    "#f7b84b",
                    "#f06548",
                    "#4b93f7",
                    "#6f42c1",
                    "#e83e8c",
                    "#20c997",
                ],
                xaxis: {
                    categories: this.topAnalis.categories,
                    labels: {
                        style: { fontSize: "11px" },
                        formatter: (v) => v + " uji",
                    },
                },
                yaxis: {
                    labels: {
                        style: { fontSize: "11px", fontWeight: 600 },
                        maxWidth: 110,
                    },
                },
                dataLabels: {
                    enabled: true,
                    formatter: (v) => v + " uji",
                    style: {
                        fontSize: "11px",
                        colors: ["#fff"],
                        fontWeight: 600,
                    },
                },
                legend: { show: false },
                grid: { borderColor: "#f1f5f9", strokeDashArray: 3 },
                tooltip: {
                    y: {
                        formatter: (v) =>
                            v.toLocaleString("id-ID") +
                            " pengujian diselesaikan",
                    },
                },
            };
        },
        topAnalisSeries() {
            return [{ name: "Pengujian Selesai", data: this.topAnalis.data }];
        },
    },

    methods: {
        tickClock() {
            this.liveClock = new Date().toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
                second: "2-digit",
            });
        },

        async fetchKpi() {
            this.loading.kpi = true;
            try {
                const r = await axios.get("/api/v1/lims/kpi-rekap");
                this.kpi = r.data?.result ?? {};
            } catch {
                this.kpi = {};
            } finally {
                this.loading.kpi = false;
            }
        },

        async fetchTren() {
            this.loading.tren = true;
            try {
                const r = await axios.get("/api/v1/lims/tren-aktivitas");
                this.tren = r.data?.result ?? { categories: [], series: [] };
            } catch {
                this.tren = { categories: [], series: [] };
            } finally {
                this.loading.tren = false;
            }
        },

        async fetchBeban() {
            this.loading.beban = true;
            try {
                const r = await axios.get("/api/v1/lims/beban-mesin");
                const d = r.data?.result ?? {};
                this.beban = {
                    categories: d.categories ?? [],
                    total: d.total ?? [],
                    selesai: d.selesai ?? [],
                };
            } catch {
                this.beban = { categories: [], total: [], selesai: [] };
            } finally {
                this.loading.beban = false;
            }
        },

        async fetchPassRate() {
            this.loading.passRate = true;
            try {
                const r = await axios.get("/api/v1/lims/pass-rate-jenis");
                this.passRate = r.data?.result ?? { categories: [], data: [] };
            } catch {
                this.passRate = { categories: [], data: [] };
            } finally {
                this.loading.passRate = false;
            }
        },

        async fetchTopAnalis() {
            this.loading.topAnalis = true;
            try {
                const r = await axios.get("/api/v1/lims/top-analis");
                this.topAnalis = r.data?.result ?? { categories: [], data: [] };
            } catch {
                this.topAnalis = { categories: [], data: [] };
            } finally {
                this.loading.topAnalis = false;
            }
        },

        refreshAll() {
            /* KPI + status dalam 1 request, chart endpoints paralel */
            Promise.all([
                this.fetchKpi(),
                this.fetchTren(),
                this.fetchBeban(),
                this.fetchPassRate(),
                this.fetchTopAnalis(),
            ]);
        },
    },

    mounted() {
        this.tickClock();
        this.clockTimer = setInterval(this.tickClock, 1000);
        this.refreshAll();
    },

    beforeUnmount() {
        clearInterval(this.clockTimer);
    },
};
</script>

<style scoped>
/* ═══════════════════════════════════════════════════
   BASE
═══════════════════════════════════════════════════ */
.qlms-wrap {
    font-family: "Inter", "Segoe UI", sans-serif;
}

/* ═══════════════════════════════════════════════════
   HERO BANNER
═══════════════════════════════════════════════════ */
.qlms-hero {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    padding: 30px 28px;
    color: #fff;
    min-height: 130px;
}

.qlms-hero-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    background: linear-gradient(135deg, #405189 0%, #2c3b74 55%, #19254d 100%);
}

/* Mesh dot overlay */
.qlms-hero-mesh {
    position: absolute;
    inset: 0;
    z-index: 0;
    background-image: radial-gradient(
        circle,
        rgba(255, 255, 255, 0.06) 1px,
        transparent 1px
    );
    background-size: 28px 28px;
}

.qlms-hero-inner {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}

.qlms-hero-left {
    display: flex;
    align-items: center;
    gap: 18px;
    flex: 1;
    min-width: 0;
}

.qlms-hero-avatar {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    flex-shrink: 0;
}

.qlms-hero-tag {
    display: inline-block;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 20px;
    padding: 2px 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 6px;
}

.qlms-hero-name {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 4px;
    line-height: 1.25;
}

.qlms-hero-desc {
    font-size: 12.5px;
    opacity: 0.75;
    margin: 0;
}

.qlms-hero-right {
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 10px;
    flex-shrink: 0;
}

.qlms-hero-clock-block {
    text-align: right;
}
.qlms-clock {
    font-size: 28px;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.5px;
    line-height: 1;
}
.qlms-date {
    font-size: 11px;
    opacity: 0.65;
    margin-top: 2px;
}

.qlms-periode-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 14px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 20px;
    font-size: 12px;
}

.qlms-refresh-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 18px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    backdrop-filter: blur(4px);
}
.qlms-refresh-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.27);
}
.qlms-refresh-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* ═══════════════════════════════════════════════════
   OVERVIEW STRIP
═══════════════════════════════════════════════════ */
.qlms-strip-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0;
}

.qlms-strip-box {
    padding: 12px 16px;
    border-right: 1px solid #f0f0f0;
    border-top: 3px solid var(--strip-c, #405189);
    background: #fff;
    transition: background 0.15s;
}
.qlms-strip-box:first-child {
    border-radius: 0 0 0 8px;
}
.qlms-strip-box:last-child {
    border-right: none;
    border-radius: 0 0 8px 0;
}
.qlms-strip-box:hover {
    background: #f8f9fc;
}

.qlms-strip-val {
    font-size: 24px;
    font-weight: 800;
    color: var(--strip-c, #405189);
    line-height: 1.1;
    margin-bottom: 3px;
}
.qlms-strip-lbl {
    font-size: 12px;
    font-weight: 600;
    color: #343a40;
    margin-bottom: 1px;
}
.qlms-strip-sub {
    font-size: 10.5px;
    color: #adb5bd;
}

/* ═══════════════════════════════════════════════════
   VALIDASI GRID
═══════════════════════════════════════════════════ */
.qlms-validasi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    background: #f8f9fc;
    border-radius: 12px;
    padding: 20px;
}

.qlms-vs-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.qlms-vs-icon {
    width: 44px;
    height: 44px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.qlms-vs-val {
    font-size: 20px;
    font-weight: 800;
    color: #212529;
}
.qlms-vs-lbl {
    font-size: 11px;
    color: #878a99;
    margin-top: 2px;
}

/* ═══════════════════════════════════════════════════
   LEGEND PILLS
═══════════════════════════════════════════════════ */
.qlms-legend-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: color-mix(in srgb, var(--lc, #405189) 10%, transparent);
    color: var(--lc, #405189);
}
.qlms-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--lc, #405189);
    display: inline-block;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════════
   SECTION LABEL
═══════════════════════════════════════════════════ */
.qlms-section-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #878a99;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ═══════════════════════════════════════════════════
   ICON BOXES (pengganti avatar-title Velzon)
═══════════════════════════════════════════════════ */
.qlms-icon-sm {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.qlms-icon-lg {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ═══════════════════════════════════════════════════
   SKELETON / SHIMMER
═══════════════════════════════════════════════════ */
@keyframes qlms-shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
.qlms-shimmer {
    background: linear-gradient(90deg, #f0f2f5 25%, #e6e9ef 50%, #f0f2f5 75%);
    background-size: 200% 100%;
    animation: qlms-shimmer 1.4s infinite;
    border-radius: 6px;
}

.qlms-sk-box {
    background: #fff !important;
    border-top-color: #e9ecef !important;
}
.qlms-sk-line {
    height: 12px;
    border-radius: 4px;
}

.qlms-chart-sk {
    height: 300px;
}
.qlms-donut-sk {
    width: 210px;
    height: 210px;
    border-radius: 50%;
}

/* ═══════════════════════════════════════════════════
   SPINNER
═══════════════════════════════════════════════════ */
@keyframes qlms-spin {
    to {
        transform: rotate(360deg);
    }
}
.qlms-spin {
    display: inline-block;
    animation: qlms-spin 0.7s linear infinite;
}

/* ═══════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════ */
@media (max-width: 1400px) {
    .qlms-strip-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .qlms-validasi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    .qlms-strip-box:nth-child(3) {
        border-right: none;
    }
    .qlms-strip-box:nth-child(3),
    .qlms-strip-box:nth-child(4),
    .qlms-strip-box:nth-child(5),
    .qlms-strip-box:nth-child(6) {
        border-top-width: 2px;
    }
}

@media (max-width: 992px) {
    .qlms-strip-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .qlms-validasi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .qlms-strip-box:nth-child(2n) {
        border-right: none;
    }
    .qlms-strip-box:nth-child(3),
    .qlms-strip-box:nth-child(4) {
        border-top-width: 2px;
    }
    .qlms-hero-name {
        font-size: 17px;
    }
    .qlms-clock {
        font-size: 22px;
    }
}

@media (max-width: 768px) {
    .qlms-strip-val {
        font-size: 20px;
    }
    .qlms-hero {
        padding: 22px 18px;
    }
    .qlms-hero-avatar {
        width: 48px;
        height: 48px;
        font-size: 24px;
    }
    .qlms-hero-name {
        font-size: 16px;
    }
    .qlms-clock {
        font-size: 20px;
    }
    .qlms-hero-right {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
        align-content: flex-start;
        gap: 8px;
    }
}

@media (max-width: 576px) {
    .qlms-strip-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .qlms-validasi-grid {
        grid-template-columns: repeat(1, 1fr);
    }
    .qlms-strip-val {
        font-size: 18px;
    }
    .qlms-hero-left {
        gap: 12px;
    }
    .qlms-hero-tag {
        display: none;
    }
}
</style>
