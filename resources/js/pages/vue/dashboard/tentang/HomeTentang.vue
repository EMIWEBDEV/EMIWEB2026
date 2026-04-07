<template>
    <section
        class="min-vh-100 py-5 px-3"
        style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%)"
    >
        <div class="container">
            <!-- Header Section -->
            <div class="text-center mb-5">
                <div
                    class="badge mb-3 px-3 py-2"
                    style="
                        background-color: rgba(63, 81, 137, 0.1);
                        color: #3f5189;
                        font-weight: 500;
                        letter-spacing: 1px;
                    "
                >
                    <i class="bi bi-megaphone me-2"></i>Catatan Pembaruan Sistem
                    Informasi Manajemen Analisa & Kontrol Produksi Evo
                    Manufacturing Indonesia
                </div>
                <h1 class="display-5 fw-bold mb-3" style="color: #2c3e50">
                    Evolusi Sistem
                    <span class="gradient-text"
                        >Informasi Manajemen Analisa & Kontrol Produksi Evo
                        Manufacturing Indonesia</span
                    >
                </h1>
                <p
                    class="lead text-center"
                    style="
                        color: #6c757d;
                        max-width: 720px;
                        margin: 0 auto;
                        line-height: 1.6;
                    "
                >
                    Pembaruan sistem ini bertujuan meningkatkan efisiensi
                    pengelolaan dan akurasi hasil analisa pada proses produksi
                    secara menyeluruh.
                </p>
            </div>

            <!-- Interactive Tabs -->
            <div class="d-flex justify-content-center mb-5">
                <div class="btn-group shadow-sm" role="group">
                    <button
                        class="btn btn-tab"
                        :class="{ 'active-tab': activeTab === 'latest' }"
                        @click="activeTab = 'latest'"
                    >
                        <i class="bi bi-stars me-2"></i>Pembaruan Utama
                    </button>
                    <button
                        class="btn btn-tab"
                        :class="{ 'active-tab': activeTab === 'previous' }"
                        @click="activeTab = 'previous'"
                    >
                        <i class="bi bi-clock-history me-2"></i>Riwayat Versi
                    </button>
                </div>
            </div>

            <!-- Update Cards with Animated Entrance -->
            <transition-group name="list" tag="div" class="row g-4">
                <div
                    v-for="(update, index) in updates[activeTab]"
                    :key="update.version + index"
                    class="col-12"
                >
                    <div class="card border-0 shadow-sm update-card">
                        <div
                            class="card-header p-4"
                            style="
                                background-color: #3f5189;
                                border-radius: 0.375rem 0.375rem 0 0 !important;
                            "
                        >
                            <div
                                class="d-flex flex-column flex-md-row justify-content-between align-items-md-center"
                            >
                                <div
                                    class="d-flex align-items-center mb-2 mb-md-0"
                                >
                                    <span
                                        class="badge bg-white text-dark me-3 px-3 py-2"
                                    >
                                        v{{ update.version }}
                                    </span>
                                    <!-- <span
                                        class="badge bg-warning text-dark me-3 px-3 py-2 d-flex align-items-center"
                                    >
                                        <i class="fas fa-flask me-1"></i> Beta
                                    </span> -->

                                    <small class="text-white-50">{{
                                        formatDate(update.date)
                                    }}</small>
                                    <span
                                        v-if="activeTab === 'latest'"
                                        class="badge bg-success ms-3 d-flex align-items-center"
                                        style="
                                            background-color: #4caf50 !important;
                                            padding: 0.35em 0.65em;
                                        "
                                    >
                                        <i
                                            class="bi bi-patch-check-fill me-1"
                                        ></i
                                        >Versi Terkini
                                    </span>
                                </div>
                                <h5 class="card-title mb-0 text-white">
                                    {{ update.title }}
                                </h5>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <p
                                v-if="update.description"
                                class="text-muted mb-4"
                            >
                                {{ update.description }}
                            </p>

                            <div class="col-lg-12 mb-4 mb-lg-0">
                                <div
                                    class="feature-section p-3 h-100"
                                    style="
                                        background-color: rgba(
                                            63,
                                            81,
                                            137,
                                            0.03
                                        );
                                        border-radius: 0.5rem;
                                    "
                                >
                                    <h6 class="section-title mb-3">
                                        <i
                                            class="bi bi-list-check me-2"
                                            style="color: #3f5189"
                                        ></i>
                                        <span style="color: #3f5189"
                                            >Fitur & Penyempurnaan</span
                                        >
                                    </h6>
                                    <ul class="list-unstyled">
                                        <li
                                            v-for="(
                                                feature, i
                                            ) in update.features"
                                            :key="'feature' + i"
                                            class="mb-2 d-flex"
                                        >
                                            <span
                                                class="me-2"
                                                style="color: #3f5189"
                                                >•</span
                                            >
                                            <span>{{ feature }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Important Notes (only for latest version) -->
                            <!-- <div
                                v-if="activeTab === 'latest'"
                                class="alert mt-4 mb-0"
                                style="
                                    background-color: rgba(63, 81, 137, 0.05);
                                    border-left: 4px solid #3f5189;
                                "
                            >
                                <h6
                                    class="d-flex align-items-center mb-2"
                                    style="color: #3f5189"
                                >
                                    <i
                                        class="bi bi-exclamation-octagon-fill me-2"
                                    ></i
                                    >Catatan Penting
                                </h6>
                                <div class="row">
                                    <div class="col-12 mb-2 mb-md-0">
                                        <p class="d-flex mb-2">
                                            <span
                                                class="me-2"
                                                style="color: #3f5189"
                                                >🔄</span
                                            >
                                            <span
                                                ><strong
                                                    >Migrasi Sistem:</strong
                                                >

                                                <p class="mb-2 d-flex">
                                                    <span
                                                        class="me-2"
                                                        style="color: #3f5189"
                                                        >🚧</span
                                                    >
                                                    <span>
                                                        <strong
                                                            >Fase Beta:</strong
                                                        >
                                                        Versi ini merupakan
                                                        transisi dari v1.4.0 ke
                                                        v2.0.0. Sistem sudah
                                                        dapat digunakan secara
                                                        umum, namun **masih
                                                        dalam pengujian dan
                                                        pengembangan**, terutama
                                                        pada sisi UI, pengalaman
                                                        pengguna, serta performa
                                                        data.
                                                    </span>
                                                </p>
                                                <p class="mb-2 d-flex">
                                                    <span
                                                        class="me-2"
                                                        style="color: #3f5189"
                                                        >⚙️</span
                                                    >
                                                    <span>
                                                        Beberapa fitur bersifat
                                                        eksperimental dan dapat
                                                        mengalami perubahan
                                                        tampilan atau alur kerja
                                                        secara drastis pada
                                                        rilis berikutnya.
                                                    </span>
                                                </p>
                                                <p class="mb-0 d-flex">
                                                    <span
                                                        class="me-2"
                                                        style="color: #3f5189"
                                                        >🧠</span
                                                    >
                                                    <span>
                                                        Meskipun demikian,
                                                        **akurasi perhitungan
                                                        dan logika analisa telah
                                                        distandarisasi** dan
                                                        dipastikan tetap 100%
                                                        akurat. Kami mengajak
                                                        semua pengguna untuk
                                                        ikut serta memberikan
                                                        masukan demi
                                                        penyempurnaan sistem ini
                                                        ke versi final.
                                                    </span>
                                                </p>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div> -->
                        </div>

                        <!-- Footer with interactive elements -->
                        <!-- <div
                            class="card-footer bg-transparent px-4 py-3 d-flex justify-content-between align-items-center border-top-0"
                        >
                            <small class="text-muted"
                                >Terakhir diperbarui:
                                {{ getCurrentDate() }}</small
                            >
                            <button
                                class="btn btn-sm d-flex align-items-center"
                                style="
                                    background-color: rgba(63, 81, 137, 0.1);
                                    color: #3f5189;
                                "
                                @click="toggleDetails(index)"
                            >
                                <i
                                    class="bi"
                                    :class="{
                                        'bi-chevron-down':
                                            !expandedCards[index],
                                        'bi-chevron-up': expandedCards[index],
                                    }"
                                ></i>
                                <span class="ms-1">{{
                                    expandedCards[index]
                                        ? "Sembunyikan"
                                        : "Detail"
                                }}</span>
                            </button>
                        </div> -->
                    </div>
                </div>
            </transition-group>

            <!-- Version Timeline (for previous versions tab) -->
            <!-- <div v-if="activeTab === 'previous'" class="mt-5">
                <h5 class="mb-4 text-center" style="color: #3f5189">
                    <i class="bi bi-diagram-3 me-2"></i>Lini Masa Pembaruan
                </h5>
                <div class="timeline">
                    <div
                        v-for="(update, index) in updates.previous"
                        :key="'timeline' + index"
                        class="timeline-item"
                        :class="{
                            'timeline-item-left': index % 2 === 0,
                            'timeline-item-right': index % 2 !== 0,
                        }"
                    >
                        <div class="timeline-content shadow-sm">
                            <div
                                class="timeline-header"
                                style="background-color: #3f5189"
                            >
                                <span class="badge bg-white text-dark me-2"
                                    >v{{ update.version }}</span
                                >
                                <span class="text-white">{{
                                    update.date
                                }}</span>
                            </div>
                            <div class="timeline-body">
                                <h6>{{ update.title }}</h6>
                                <p class="text-muted small">
                                    {{
                                        update.description ||
                                        "Pembaruan sistem untuk peningkatan performa"
                                    }}
                                </p>
                                <div class="d-flex flex-wrap">
                                    <span
                                        v-for="(
                                            feat, i
                                        ) in update.features.slice(0, 3)"
                                        :key="'feat' + i"
                                        class="badge bg-light text-dark me-2 mb-2"
                                    >
                                        {{ feat.substring(0, 20)
                                        }}{{ feat.length > 20 ? "..." : "" }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->
        </div>
    </section>
</template>

<script>
export default {
    data() {
        return {
            activeTab: "latest",
            expandedCards: {},
            updates: {
                latest: [
                    {
                        version: "2.0.0",
                        date: "29 Agustus 2025",
                        title: "Rilis Stabil – Standarisasi Analisa & Peningkatan Produksi",
                        description:
                            "Rilis stabil 2.0.0 menandai transisi resmi dari versi Beta menuju versi produksi penuh. Sistem telah melalui tahap pengujian intensif dan siap digunakan secara luas, dengan peningkatan signifikan di sisi analisa, produksi, serta pengalaman pengguna. Versi ini menegaskan konsistensi hasil analisa, optimalisasi alur kerja, serta penambahan fitur yang mendukung operasional laboratorium secara lebih efisien.",
                        features: [
                            "🎨 Penyesuaian tampilan UI agar lebih konsisten dan ramah pengguna",
                            "📦 Penambahan fitur daftar produk rilis untuk transparansi pengembangan",
                            "ℹ️ Penambahan informasi detail di status analisa untuk monitoring yang lebih jelas",
                            "🏭 Fitur produksi baru: tombol trigger untuk PO yang sudah selesai diinput",
                            "🔒 Penguncian analisa guna menjaga konsistensi dan validitas hasil",
                            "🧾 Perbaikan tampilan data barang uji laboratorium agar lebih rapi dan mudah dibaca",
                            "📑 Penambahan template laporan hasil analisa untuk standarisasi dokumen",
                            "📍 Penambahan UI tracking informasi untuk memantau aktivitas dan progres kegiatan",
                        ],
                    },
                ],
                previous: [
                    {
                        version: "2.0.0-Beta",
                        date: "05 Agustus 2025",
                        title: "Rilis Beta – Standarisasi Analisa & Resampling",
                        description:
                            "Rilis Beta ini menandai transisi besar dari versi 1.4.0 ke 2.0.0, dengan fokus pada standarisasi logika analisa dan peningkatan fitur resampling. Meskipun sistem sudah stabil dan siap digunakan secara umum, seluruh elemen masih dalam fase pengujian dan pengembangan, terutama dari sisi tampilan, pengalaman pengguna, serta performa pengolahan data.",
                        features: [
                            "🧮 Penambahan kriteria kelayakan hasil analisa berdasarkan rentang standar yang telah ditentukan",
                            "🧪 Fitur validasi hasil analisa dengan UI baru dan tahapan sistematis",
                            "♻️ Penambahan fitur resampling / pengujian ulang dengan pembatasan sesuai standar",
                            "🔐 Penutupan tahapan input jika sampel sebelumnya belum selesai",
                            "✅ Finalisasi data yang sudah melalui proses validasi terpusat",
                            "📊 Tampilan baru (UI) untuk hasil analisa agar lebih informatif dan konsisten",
                            "⏳ Pelepasan sampel yang kadaluarsa atau terkunci, serta dukungan perpanjangan",
                            "🧱 Penyesuaian layout dan peningkatan pengalaman pengguna",
                        ],
                    },
                    {
                        version: "1.4.0",
                        date: "25 Juli 2025",
                        title: "Pembaruan Minor – Kriteria Kelayakan Analisa & Tampilan Warna",
                        description:
                            "Pembaruan minor dengan penambahan logika kelayakan hasil analisa, indikator warna status kelayakan barang, serta perbaikan tampilan dan pemeliharaan sistem berkala.",
                        features: [
                            "✅ Penambahan fitur kriteria kelayakan untuk hasil analisa berdasarkan standar rentang nilai",
                            "🎨 Penambahan warna indikator data: merah untuk barang tidak layak, hijau untuk barang layak",
                            "🧱 Penyesuaian tata letak dan perbaikan tampilan untuk pengalaman pengguna yang lebih baik",
                            "🛠️ Pemeliharaan sistem secara berkala untuk menjaga performa dan stabilitas",
                        ],
                    },
                    {
                        version: "1.3.0",
                        date: "24 Juli 2025",
                        title: "Pembaruan Minor – Peningkatan Fitur Uji Berkala & Notifikasi",
                        description:
                            "Pembaruan minor yang memperkuat sistem uji berkala dengan sub-klasifikasi, format laporan PDF, serta sistem notifikasi masuknya sampel dan peningkatan keamanan menyeluruh.",
                        features: [
                            "🧬 Penambahan fitur sub-klasifikasi uji berkala untuk pengelompokan lebih detail",
                            "🧠 UI klasifikasi analisa kini menyesuaikan secara otomatis jika terdapat sub-analisa",
                            "🔄 Penyesuaian logika uji sampel agar mendukung proses berkala dengan lebih baik",
                            "📄 Fitur cetak laporan hasil analisa dalam format PDF resmi",
                            "🔔 Notifikasi otomatis saat sampel baru terdaftar dalam sistem",
                            "🛡️ Perbaikan menyeluruh untuk meningkatkan stabilitas dan keamanan sistem",
                        ],
                    },
                    {
                        version: "1.2.0",
                        date: "20 Juli 2025",
                        title: "Pembaruan Minor – Dukungan Satuan PCS & Peningkatan QR Code",
                        description:
                            "Pembaruan minor yang menambahkan fleksibilitas satuan PCS/KG, penyempurnaan cetak QR Code, serta visualisasi tambahan pada modul pencetakan ulang.",
                        features: [
                            "⚙️ Penambahan opsi satuan input PCS dan KG pada sistem",
                            "🧾 Penyesuaian proses registrasi sampel berkala agar lebih fleksibel terhadap satuan",
                            "📄 Cetak QR Code kini mendukung format PCS secara dinamis",
                            "🔄 Perbaikan sistem cetak ulang QR Code agar lebih stabil dan akurat",
                            "📈 Penambahan grafik line visualisasi Pada Registrasi Sampel",
                        ],
                    },
                    {
                        version: "1.1.1",
                        date: "17 Juli 2025",
                        title: "Patch Koneksi Perangkat",
                        description:
                            "Pembaruan patch untuk meningkatkan kestabilan koneksi perangkat pada registrasi sampel, serta penyederhanaan proses cetak QR Code.",
                        features: [
                            "⚖️ Perbaikan kestabilan koneksi timbangan digital agar hasil penimbangan lebih akurat dan konsisten",
                            "🖨️ Perubahan metode cetak QR Code registrasi sampel dari IP address ke koneksi USB untuk kompatibilitas printer lokal",
                        ],
                    },
                    {
                        version: "1.1.0",
                        date: "15 Juli 2025",
                        title: "Pembaruan Minor & Visualisasi Dashboard",
                        description:
                            "Peningkatan minor dengan penambahan integrasi perangkat, fitur edit, dan visualisasi dashboard untuk pengalaman pengguna yang lebih interaktif dan informatif.",
                        features: [
                            "⚖️ Integrasi penimbangan otomatis via koneksi RS232 untuk input berat sampel",
                            "✏️ Penambahan fitur edit untuk pengaturan parameter uji laboratorium",
                            "🔧 Penambahan fitur edit untuk data barang uji laboratorium",
                            "🖥️ Penyegaran UI pada halaman dashboard laboratorium",
                            "📊 Penambahan 6 widget 'data hari ini' untuk memantau aktivitas laboratorium secara real-time",
                            "📈 Penambahan 4 grafik visualisasi (line/bar) pada dashboard untuk tren hasil uji dan status operasional",
                            "📦 Penambahan 4 widget informasi keseluruhan (total sampel, jumlah uji, status validasi, dsb)",
                        ],
                    },
                    {
                        version: "1.0.1",
                        date: "10 Juli 2025",
                        title: "Stabilisasi Sistem",
                        description:
                            "Pembaruan Patch untuk pengalaman lebih stabil dan aman",
                        features: [
                            "🛡️ Penambahan middleware keamanan untuk perlindungan akses route sistem",
                            "🛠️ Perbaikan error penyimpanan data mesin analisa (beberapa data gagal tersimpan)",
                            "🧮 Penyesuaian validasi agar rumus standar seperti SUM() dan AVG() dapat digunakan tanpa error",
                            "🧾 Optimalisasi registrasi sampel agar lebih stabil saat input dalam jumlah besar",
                            "🧪 Perbaikan tampilan dan keakuratan data pada modul validasi hasil analisa",
                            "📊 Penyesuaian UI dan struktur tampilan pada halaman hasil analisa",
                            "🧠 Perbaikan logika perhitungan hasil analisa agar sesuai dengan data dan rumus yang ditentukan",
                        ],
                    },
                    {
                        version: "1.0.0",
                        date: "07 Juli 2025",
                        title: "Peluncuran Perdana",
                        description:
                            "Rilis perdana sistem yang dirancang khusus untuk mendukung proses analisa laboratorium dan kontrol produksi secara terpadu dalam satu platform terintegrasi dan Sistem dikembangkan dengan fokus utama pada tampilan desktop dan belum dioptimalkan untuk tampilan mobile",
                        features: [
                            "✅ Registrasi sampel laboratorium",
                            "✅ Sistem Mendukung Otomatis Mengprint QrCode Menggunakan Printer Thermal Menggunakan IP Address",
                            "✅ Cetak ulang QR Code untuk identifikasi sampel",
                            "✅ Kontrol unit mesin analisa",
                            "✅ Manajemen klasifikasi analisa",
                            "✅ Pengaturan parameter uji laboratorium",
                            "✅ Pengaturan rumus dan formula perhitungan",
                            "✅ Manajemen data barang uji laboratorium",
                            "✅ Proses uji sampel terintegrasi",
                            "✅ Validasi dan verifikasi hasil sampel",
                            "✅ Penyajian hasil analisa uji",
                            "✅ Finalisasi dan penguncian data uji",
                            "✅ Pencetakan laporan hasil analisa dalam bentuk Excel",
                            "✅ Dukungan sistem kalkulasi dengan rumus dinamis dan scalable sesuai SOP laboratorium",
                            "✅ Dukungan sistem log aktivitas pengguna dan sistem pelacakan perubahan data",
                            "✅ Dukungan sistem informasi visualisai trend analisa uji laboratorium",
                        ],
                    },
                ],
            },
        };
    },
    methods: {
        toggleDetails(index) {
            this.$set(this.expandedCards, index, !this.expandedCards[index]);
        },
        formatDate(dateString) {
            const bulan = {
                Januari: "01",
                Februari: "02",
                Maret: "03",
                April: "04",
                Mei: "05",
                Juni: "06",
                Juli: "07",
                Agustus: "08",
                September: "09",
                Oktober: "10",
                November: "11",
                Desember: "12",
            };

            // Pisahkan tanggal
            const [day, namaBulan, year] = dateString.split(" ");
            const bulanAngka = bulan[namaBulan];

            // Buat format ISO (yyyy-mm-dd)
            const isoDate = `${year}-${bulanAngka}-${day}`;

            const options = { day: "numeric", month: "long", year: "numeric" };
            return new Date(isoDate).toLocaleDateString("id-ID", options);
        },
        getCurrentDate() {
            return new Date().toLocaleDateString("id-ID", {
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric",
            });
        },
    },
};
</script>

<style scoped>
/* Base Styles */
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
@import url("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css");

body {
    font-family: "Inter", sans-serif;
}

/* Gradient Text */
.gradient-text {
    background: linear-gradient(90deg, #3f5189 0%, #5c6bc0 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    display: inline;
}

/* Tab Styles */
.btn-tab {
    background-color: white;
    color: #6c757d;
    border: 1px solid #dee2e6;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
}

.btn-tab:hover {
    color: #3f5189;
    border-color: #3f5189;
}

.active-tab {
    background-color: #3f5189 !important;
    color: white !important;
    border-color: #3f5189 !important;
    box-shadow: 0 2px 8px rgba(63, 81, 137, 0.2);
}

/* Update Card Styles */
.update-card {
    border-radius: 0.5rem;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}

.update-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
    border-bottom: none;
}

/* Timeline Styles */
.timeline {
    position: relative;
    max-width: 1200px;
    margin: 0 auto;
    padding: 40px 0;
}

.timeline::after {
    content: "";
    position: absolute;
    width: 3px;
    background-color: #3f5189;
    top: 0;
    bottom: 0;
    left: 50%;
    margin-left: -1.5px;
    opacity: 0.2;
}

.timeline-item {
    padding: 10px 40px;
    position: relative;
    width: 50%;
    box-sizing: border-box;
}

.timeline-item-left {
    left: 0;
}

.timeline-item-right {
    left: 50%;
}

.timeline-content {
    padding: 0;
    position: relative;
    border-radius: 0.5rem;
    background-color: white;
}

.timeline-header {
    padding: 15px 20px;
    color: white;
    border-radius: 0.5rem 0.5rem 0 0;
}

.timeline-body {
    padding: 20px;
}

.timeline-item::after {
    content: "";
    position: absolute;
    width: 20px;
    height: 20px;
    background-color: white;
    border: 4px solid #3f5189;
    border-radius: 50%;
    top: 20px;
    z-index: 1;
}

.timeline-item-left::after {
    right: -10px;
}

.timeline-item-right::after {
    left: -10px;
}

/* Animation Effects */
.list-enter-active,
.list-leave-active {
    transition: all 0.5s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateY(30px);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .timeline::after {
        left: 31px;
    }

    .timeline-item {
        width: 100%;
        padding-left: 70px;
        padding-right: 25px;
    }

    .timeline-item-right {
        left: 0;
    }

    .timeline-item::after {
        left: 21px;
    }

    .timeline-item-left::after,
    .timeline-item-right::after {
        left: 21px;
    }
}

/* Section Title */
.section-title {
    font-size: 1rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Badge Styles */
.badge {
    font-weight: 500;
    letter-spacing: 0.3px;
}
</style>
