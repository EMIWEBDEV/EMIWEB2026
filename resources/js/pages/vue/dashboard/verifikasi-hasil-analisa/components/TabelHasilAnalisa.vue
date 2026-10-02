<template>
    <!--
        Tabel hasil analisa — dipakai bersama layar Verifikasi dan Finalisasi,
        supaya keduanya menampilkan kriteria, kelayakan, penginput, dan
        validator dengan cara yang persis sama.

        Palatabilitas tampil sebagai matriks pembanding; klasifikasi lain
        sebagai tabel kelayakan. Datanya disusun RincianHasilAnalisaService.

        RESPONSIF — mengikuti lebar WADAHNYA sendiri (container query), bukan
        lebar layar, karena lebar yang tersedia bergantung pada sidebar dan
        panel kanan:
          >= 880px  : penuh   — delapan kolom
          540–879px : ringkas — sampel & resampling di bawah nama, penginput
                                dan validator digabung satu kolom
          <  540px  : kartu   — satu kartu berlabel per analisa
    -->
    <div class="ha" :class="{ 'ha--rata': tanpaBingkai }">

        <!-- ===== PALATABILITAS: matriks pembanding ===== -->
        <!--
            Uji palatabilitas menilai sampel dengan MEMBANDINGKAN terhadap
            produk pembanding, bukan terhadap batas mutu. Karena itu bentuknya
            berbeda: satu blok per jenis analisa, parameter menjadi kolom,
            pembanding menjadi baris, dan kolom kelayakan tidak ditampilkan.
        -->
        <div v-if="butuhPembanding" class="ha-plt">
            <div v-for="m in matriks" :key="m.Id_Jenis_Analisa" class="ha-plt-blok">
                <div class="ha-plt-h">
                    <span class="ha-plt-nm">
                        <i class="ri-flask-line"></i>
                        <b>{{ m.Nama_Jenis_Analisa }}</b>
                        <span class="ha-plt-kode">{{ m.Kode_Analisa }}</span>
                    </span>
                    <span class="ha-plt-jejak">
                        <span class="ha-jj">
                            <i class="ri-edit-2-line"></i>Diinput
                            <b>{{ m.Input ? m.Input.nama : '-' }}</b>
                            <em v-if="m.Input">{{ stamp(m.Input.tanggal, m.Input.jam) }}</em>
                        </span>
                        <span class="ha-jj" :class="{ 'is-none': !m.Validasi && !menunggu, 'is-tunggu': !m.Validasi && menunggu }"
                              :title="m.Validasi || menunggu ? '' : judulTanpaValidator">
                            <i class="ri-shield-check-line"></i>Divalidasi
                            <template v-if="m.Validasi">
                                <b>{{ m.Validasi.nama }}</b>
                                <em>{{ stamp(m.Validasi.tanggal, m.Validasi.jam) }}</em>
                            </template>
                            <em v-else>{{ menunggu ? 'menunggu' : 'tidak tercatat' }}</em>
                        </span>
                    </span>
                </div>

                <!-- Layar lebar: matriks pembanding x parameter. -->
                <div class="table-responsive ha-tw">
                    <table class="table table-sm align-middle ha-t ha-plt-t mb-0">
                        <thead>
                            <tr>
                                <th class="ha-plt-th-pb">
                                    <i class="ri-flask-line me-1"></i>Pembanding
                                </th>
                                <th v-for="(pr, i) in m.Parameter" :key="i" class="ha-plt-th">
                                    {{ pr.nama }}
                                    <small v-if="pr.satuan">{{ pr.satuan }}</small>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in pembanding" :key="p.id || '_'">
                                <td class="ha-plt-td-pb">{{ p.nama }}</td>
                                <td v-for="(pr, i) in m.Parameter" :key="i" class="ha-plt-td">
                                    <span v-if="selPembanding(m, p)[i]" class="ha-plt-nilai">
                                        {{ nilaiPlt(selPembanding(m, p)[i]) }}
                                    </span>
                                    <span v-else class="ha-plt-kosong">&mdash;</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Layar sempit: matriks dibalik menjadi daftar parameter ->
                     nilai per pembanding, supaya tidak perlu digeser menyamping. -->
                <div class="ha-plt-daftar">
                    <div v-for="p in pembanding" :key="'d' + (p.id || '_')" class="ha-plt-pb">
                        <span class="ha-plt-pb-n"><i class="ri-flask-line"></i>{{ p.nama }}</span>
                        <dl class="ha-plt-dl">
                            <template v-for="(pr, i) in m.Parameter" :key="i">
                                <dt>{{ pr.nama }}<small v-if="pr.satuan"> {{ pr.satuan }}</small></dt>
                                <dd>{{ selPembanding(m, p)[i] ? nilaiPlt(selPembanding(m, p)[i]) : '—' }}</dd>
                            </template>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== TABEL KELAYAKAN ===== -->
        <!-- Setiap sel membawa data-label: pada mode kartu label itulah yang
             tampil di kiri nilainya. -->
        <div v-else class="table-responsive ha-tw">
            <table class="table table-sm align-middle ha-t mb-0">
                <thead>
                    <tr>
                        <th>Jenis Analisa</th>
                        <th class="k-smp">Sampel</th>
                        <th class="k-hasil">Hasil</th>
                        <th>Kriteria Kelayakan</th>
                        <th>Kelayakan</th>
                        <th class="k-rs">Resampling</th>
                        <th class="k-in">Diinput</th>
                        <th class="k-val">Divalidasi</th>
                        <th class="k-jejak">Diinput / Divalidasi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(a, i) in analisa" :key="rowKey(a) + '_' + i">
                        <td class="k-nama" data-label="Jenis Analisa">
                            <div class="ha-v">
                                <span class="ha-an">{{ a.Nama_Jenis_Analisa }}</span>
                                <span v-if="a.Nama_Pembanding" class="badge bg-primary-subtle text-primary ms-1">
                                    {{ a.Nama_Pembanding }}
                                </span>
                                <!-- Penanda saja; fotonya sendiri tampil di galeri
                                     di bawah tabel, bukan sebagai kolom. -->
                                <button v-if="a.Foto && a.Foto.length" type="button" class="ha-foto"
                                        title="Lihat foto analisa" @click="$emit('foto', a)">
                                    <i class="ri-camera-line"></i>{{ a.Foto.length }}
                                </button>
                                <!-- Mode ringkas: kolom Sampel & Resampling
                                     dipindah ke sini. -->
                                <span class="ha-mini">
                                    <code class="ha-mono ha-smp">{{ a.No_Sampel_Uji }}</code>
                                    <span v-if="a.Jumlah_Resampling > 0" class="badge bg-info-subtle text-info">
                                        {{ a.Jumlah_Resampling }}&times; diulang
                                    </span>
                                </span>
                            </div>
                        </td>
                        <td class="k-smp" data-label="Sampel">
                            <div class="ha-v"><code class="ha-mono ha-smp">{{ a.No_Sampel_Uji }}</code></div>
                        </td>
                        <!-- Hasil diwarnai mengikuti putusannya, agar baris
                             yang menyimpang langsung tertangkap mata. -->
                        <td class="k-hasil" data-label="Hasil">
                            <div class="ha-v">
                                <b class="ha-mono"
                                   :class="{ 'ha-hasil-bad': a.Flag_Layak === 'T',
                                             'ha-hasil-na': tanpaMaster(a) }">{{ hasil(a) }}</b>
                            </div>
                        </td>

                        <!-- Kriteria Kelayakan
                             Bentuknya mengikuti jenis analisa:
                               perhitungan     -> batas angka min–max
                               non-perhitungan -> daftar kriteria, TANPA min–max
                             Bila master belum diatur, baris ini menyatakannya
                             terang-terangan, bukan dibiarkan kosong. -->
                        <td data-label="Kriteria Kelayakan">
                            <div class="ha-v">
                                <span v-if="a.Dasar_Kelayakan === 'RENTANG'" class="ha-spec">
                                    <span class="ha-mono">{{ num(a.Range_Min) }} &ndash; {{ num(a.Range_Max) }}</span>
                                    <em>batas min &ndash; max</em>
                                </span>
                                <!-- Non-perhitungan: tampilkan DAFTAR kriteria yang
                                     dianggap layak, dengan kriteria hasil ini
                                     ditebalkan. -->
                                <span v-else-if="a.Rincian_Kelayakan && a.Rincian_Kelayakan.daftar_layak" class="ha-spec">
                                    <span class="ha-krit">
                                        <span v-for="(k, i) in a.Rincian_Kelayakan.daftar_layak" :key="i"
                                              class="ha-krit-i"
                                              :class="{ 'is-pick': k === a.Rincian_Kelayakan.kriteria_terpilih }">
                                            <i class="ri-check-line"></i>{{ k }}
                                        </span>
                                    </span>
                                    <em>{{ a.Rincian_Kelayakan.jumlah_layak }} kriteria layak
                                        dari {{ a.Rincian_Kelayakan.jumlah_kriteria }}</em>
                                </span>
                                <span v-else class="ha-spec is-none">
                                    <span class="ha-spec-k"><i class="ri-error-warning-line me-1"></i>Tidak ada di master</span>
                                    <em>{{ a.Flag_Perhitungan === 'Y'
                                        ? 'batas min – max belum diatur'
                                        : 'daftar kriteria belum diatur' }}</em>
                                </span>
                            </div>
                        </td>
                        <td data-label="Kelayakan">
                            <div class="ha-v">
                                <!-- Tiga keadaan, bukan dua: layak, tidak layak,
                                     dan BELUM DAPAT DINILAI ketika masternya
                                     tidak ada. Keadaan ketiga tidak boleh
                                     ditampilkan sebagai "Layak".

                                     Tetikus membuka tooltip saat diarahkan;
                                     layar sentuh membukanya dengan ketukan. -->
                                <span class="badge ha-lay" :class="layakCls(a)"
                                      @pointerenter="arahkan($event, a)" @pointerleave="lepas($event)"
                                      @click.stop="ketuk($event, a)">
                                    <i :class="ikonLayak(a)" class="me-1"></i>
                                    {{ labelLayak(a) }}
                                    <i class="ri-information-line ms-1 ha-i"></i>
                                </span>
                                <!-- Mode kartu: dasar penilaian ditulis langsung,
                                     karena layar sentuh tidak mengenal hover. -->
                                <span class="ha-lay-r">{{ a.Ringkas_Kelayakan }}</span>
                            </div>
                        </td>
                        <!-- Resampling: sebutkan sampel ulangnya, mis.
                             FS0926-0001-1 → FS0926-0001-2, supaya jelas
                             hasil mana yang sedang dinilai. -->
                        <td class="k-rs" :class="{ 'is-kosong': !(a.Jumlah_Resampling > 0) }" data-label="Resampling">
                            <div class="ha-v">
                                <template v-if="a.Jumlah_Resampling > 0">
                                    <span class="badge bg-info-subtle text-info">{{ a.Jumlah_Resampling }}&times; diulang</span>
                                    <span v-for="(r, i) in (a.Detail_Resampling || [])" :key="i" class="ha-rs">
                                        <template v-if="r.asal">
                                            <code>{{ r.asal }}</code>
                                            <i class="ri-arrow-right-line"></i>
                                        </template>
                                        <code>{{ r.ulang }}</code>
                                        <em v-if="r.keterangan">{{ r.keterangan }}</em>
                                    </span>
                                </template>
                                <span v-else class="ha-rs-no">Tidak ada pengulangan</span>
                            </div>
                        </td>
                        <!-- Penginput dan validator sengaja dua kolom: orangnya
                             kerap berbeda, dan jam input adalah jam hasil
                             dicatat — bukan jam validasinya. -->
                        <td class="k-in" data-label="Diinput">
                            <div class="ha-v">
                                <template v-if="a.Input">
                                    <span class="ha-vu">{{ a.Input.nama }}</span>
                                    <span class="ha-vt">{{ stamp(a.Input.tanggal, a.Input.jam) }}</span>
                                </template>
                                <span v-else class="ha-vn">&ndash;</span>
                            </div>
                        </td>
                        <td class="k-val" data-label="Divalidasi">
                            <div class="ha-v">
                                <template v-if="a.Validasi">
                                    <span class="ha-vu">{{ a.Validasi.nama }}</span>
                                    <span class="ha-vt">{{ stamp(a.Validasi.tanggal, a.Validasi.jam) }}</span>
                                </template>
                                <!-- Layar Validasi: belum divalidasi memang sedang
                                     ditunggu, bukan jejak yang hilang. -->
                                <span v-else-if="menunggu" class="ha-vn is-tunggu">
                                    <i class="ri-time-line me-1"></i>Menunggu
                                </span>
                                <span v-else class="ha-vn" :title="judulTanpaValidator">
                                    <i class="ri-question-line me-1"></i>Tidak tercatat
                                </span>
                            </div>
                        </td>
                        <!-- Mode ringkas: penginput dan validator dalam satu
                             kolom, masing-masing bertanda ikon. -->
                        <td class="k-jejak" data-label="Diinput / Divalidasi">
                            <div class="ha-v ha-jejak">
                                <span class="ha-jr" title="Diinput">
                                    <i class="ri-edit-2-line"></i>
                                    <span>
                                        <b>{{ a.Input ? a.Input.nama : '–' }}</b>
                                        <em v-if="a.Input">{{ stamp(a.Input.tanggal, a.Input.jam) }}</em>
                                    </span>
                                </span>
                                <span class="ha-jr" :class="{ 'is-none': !a.Validasi && !menunggu, 'is-tunggu': !a.Validasi && menunggu }"
                                      :title="a.Validasi ? 'Divalidasi' : (menunggu ? 'Menunggu validasi' : judulTanpaValidator)">
                                    <i class="ri-shield-check-line"></i>
                                    <span>
                                        <b>{{ a.Validasi ? a.Validasi.nama : (menunggu ? 'Menunggu validasi' : 'Validator tidak tercatat') }}</b>
                                        <em v-if="a.Validasi">{{ stamp(a.Validasi.tanggal, a.Validasi.jam) }}</em>
                                    </span>
                                </span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tooltip dipindah ke <body>: wadah container query dapat menjadi
             acuan posisi elemen fixed, sehingga tooltip di dalamnya bisa
             meleset dari badge-nya. -->
        <Teleport to="body">
            <span v-if="tipBaris" class="ha-tip"
                  :class="{ 'is-bawah': tipBawah }"
                  :style="tipGaya">
                <!-- Master memang belum diatur sama sekali. -->
                <template v-if="tipBaris.Dasar_Kelayakan === 'TANPA_MASTER'">
                    <span class="ha-tip-h">Belum dapat dinilai</span>
                    <span class="ha-tip-b">Kriteria kelayakan belum diatur di master.</span>
                    <span class="ha-tip-s">
                        {{ tipBaris.Flag_Perhitungan === 'Y'
                            ? 'Analisa perhitungan dinilai memakai batas min – max. Batas untuk produk dan mesin ini belum diatur.'
                            : 'Analisa non-perhitungan dinilai memakai daftar kriteria. Daftar untuk analisa ini belum diatur.' }}
                    </span>
                    <span class="ha-tip-w">Atur di master agar hasil dapat dinyatakan layak atau tidak layak.</span>
                </template>

                <!-- Master ada, tetapi hasilnya tidak dikenali.
                     Daftar kriteria tetap ditampilkan supaya
                     terlihat apa saja yang seharusnya dipilih. -->
                <template v-else-if="tipBaris.Dasar_Kelayakan === 'KRITERIA_TIDAK_COCOK'">
                    <span class="ha-tip-h">Belum dapat dinilai</span>
                    <span class="ha-tip-b">Hasil tidak terdaftar di master.</span>
                    <span class="ha-tip-r">
                        <em>Tersimpan</em><b>{{ tipBaris.Rincian_Kelayakan.nilai_tersimpan }}</b>
                    </span>
                    <span class="ha-tip-s">Kriteria yang terdaftar:</span>
                    <span v-for="(p, i) in tipBaris.Rincian_Kelayakan.pilihan" :key="i" class="ha-tip-o">
                        <i :class="p.layak === 'T' ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'"
                           :style="{ color: p.layak === 'T' ? '#f87171' : '#4ade80' }"></i>{{ p.keterangan }}
                    </span>
                    <span class="ha-tip-w">Kriteria di master kemungkinan berubah setelah sampel diuji.</span>
                </template>

                <template v-else>
                    <span class="ha-tip-h">Dasar penilaian</span>
                    <span class="ha-tip-b">{{ tipBaris.Ringkas_Kelayakan }}</span>
                    <template v-if="tipBaris.Dasar_Kelayakan === 'RENTANG'">
                        <span class="ha-tip-r"><em>Nilai</em><b>{{ num(tipBaris.Rincian_Kelayakan.nilai) }}</b></span>
                        <span class="ha-tip-r"><em>Batas</em><b>{{ num(tipBaris.Rincian_Kelayakan.min) }} &ndash; {{ num(tipBaris.Rincian_Kelayakan.max) }}</b></span>
                        <span class="ha-tip-s">sumber: {{ tipBaris.Rincian_Kelayakan.sumber_batas }}</span>
                    </template>
                    <template v-else-if="tipBaris.Dasar_Kelayakan === 'KRITERIA'">
                        <span class="ha-tip-r">
                            <em>Hasil</em><b>{{ tipBaris.Rincian_Kelayakan.kriteria_terpilih }}</b>
                        </span>
                        <span class="ha-tip-s">
                            {{ tipBaris.Rincian_Kelayakan.jumlah_kriteria }} kriteria terdaftar
                            ({{ tipBaris.Rincian_Kelayakan.jumlah_layak }} layak,
                            {{ tipBaris.Rincian_Kelayakan.jumlah_tidak }} tidak layak):
                        </span>
                        <span v-for="(p, i) in tipBaris.Rincian_Kelayakan.pilihan" :key="i"
                              class="ha-tip-o" :class="{ 'is-on': p.terpilih }">
                            <i :class="p.layak === 'T' ? 'ri-close-circle-line' : 'ri-checkbox-circle-line'"
                               :style="{ color: p.layak === 'T' ? '#f87171' : '#4ade80' }"></i>{{ p.keterangan }}
                            <b v-if="p.terpilih" class="ha-tip-pick">&larr; hasil ini</b>
                        </span>
                    </template>
                </template>
            </span>
        </Teleport>
    </div>
</template>

<script>
export default {
    name: "TabelHasilAnalisa",
    props: {
        analisa: { type: Array, default: () => [] },
        butuhPembanding: { type: Boolean, default: false },
        pembanding: { type: Array, default: () => [] },
        matriks: { type: Array, default: () => [] },
        // Tanpa bingkai luar: dipakai bila tabel sudah berada di dalam
        // kartu lain (mis. panel klasifikasi pada layar Finalisasi).
        tanpaBingkai: { type: Boolean, default: false },
        // Layar Validasi: hasil memang belum divalidasi, jadi kolom
        // Divalidasi menulis "Menunggu", bukan "tidak tercatat".
        menunggu: { type: Boolean, default: false },
    },
    emits: ["foto"],
    data() {
        return {
            tipBaris: null,     // baris analisa yang tooltip-nya terbuka
            tipBawah: false,
            tipGaya: {},
            judulTanpaValidator:
                "Analisa sudah tervalidasi, tetapi validatornya tidak tercatat pada jejak validasi.",
        };
    },
    mounted() {
        // Tooltip memakai position: fixed, sehingga tidak ikut bergerak saat
        // wadahnya digulir. Guliran apa pun menutupnya agar tidak menggantung
        // di posisi lama — capture menangkap guliran elemen mana saja.
        window.addEventListener("scroll", this.tutupTip, true);
        // Ketukan di luar badge menutup tooltip yang dibuka dengan ketukan.
        document.addEventListener("pointerdown", this.tutupLuar);
    },
    beforeUnmount() {
        window.removeEventListener("scroll", this.tutupTip, true);
        document.removeEventListener("pointerdown", this.tutupLuar);
    },
    methods: {
        tutupTip() {
            if (this.tipBaris) this.tipBaris = null;
        },
        tutupLuar(e) {
            if (this.tipBaris && !(e.target.closest && e.target.closest(".ha-lay"))) {
                this.tipBaris = null;
            }
        },

        /* Tetikus: tooltip mengikuti hover. Sentuh/pena: dibuka-tutup
           dengan ketukan, karena layar sentuh tidak mengenal hover. */
        arahkan(ev, a) {
            if (ev.pointerType === "mouse") this.bukaTip(ev, a);
        },
        lepas(ev) {
            if (ev.pointerType === "mouse") this.tipBaris = null;
        },
        ketuk(ev, a) {
            if (ev.pointerType === "mouse") return;
            if (this.tipBaris && this.rowKey(this.tipBaris) === this.rowKey(a)) this.tipBaris = null;
            else this.bukaTip(ev, a);
        },

        // Ulangan pengukuran dalam satu kiriman berbagi analisa, sampel, dan
        // jam; nomor faktur dan nilainya yang membedakan.
        rowKey(a) { return `${a.Id_Jenis_Analisa}_${a.No_Sampel_Uji}_${a.Id_Pembanding}_${a.Jam}_${a.No_Faktur}_${a.Hasil}`; },

        /**
         * Buka tooltip kelayakan tepat di atas (atau di bawah) badge.
         *
         * Posisi dihitung dalam koordinat viewport karena tooltip memakai
         * position: fixed — lihat catatan pada .ha-tip. Selain memilih sisi
         * yang ruangnya cukup, posisi mendatar juga dijepit agar tooltip
         * tidak keluar dari tepi layar; ekor panahnya digeser mengikuti
         * badge supaya tetap menunjuk ke baris yang benar.
         */
        bukaTip(ev, a) {
            const r = ev.currentTarget.getBoundingClientRect();
            const M = 8;                         // jarak aman dari tepi layar
            const L = Math.min(260, window.innerWidth - M * 2);   // lebar tooltip
            const TINGGI_KIRA = 150;             // perkiraan tinggi maksimum

            // Tempatkan di bawah hanya bila ruang di atas memang tidak cukup.
            const kurangRuangAtas = r.top < TINGGI_KIRA + M;
            this.tipBawah = kurangRuangAtas;

            const tengah = r.left + r.width / 2;
            const kiri   = Math.min(Math.max(tengah - L / 2, M), window.innerWidth - L - M);

            this.tipGaya = {
                left: kiri + "px",
                width: L + "px",
                top: kurangRuangAtas ? (r.bottom + 7) + "px" : "auto",
                bottom: kurangRuangAtas ? "auto" : (window.innerHeight - r.top + 7) + "px",
                // Ekor panah mengikuti badge, bukan selalu di tengah tooltip.
                "--tip-anchor": (tengah - kiri) + "px",
            };
            this.tipBaris = a;
        },

        hasil(a) { return a.Nilai_Hasil_String ? a.Nilai_Hasil_String : this.num(a.Hasil); },
        num(v) {
            if (v === null || v === undefined) return "–";
            const n = Number(v);
            return Number.isFinite(n) ? n.toLocaleString("id-ID", { maximumFractionDigits: 4 }) : String(v);
        },

        /**
         * Nilai satu sel matriks palatabilitas: seluruh hasil jenis analisa
         * ini terhadap satu pembanding. Kunci '_' dipakai untuk hasil yang
         * tidak terikat pembanding mana pun.
         */
        selPembanding(m, p) {
            return (m.sel || {})[p.id ?? "_"] || [];
        },
        /** Tampilkan teks bacanya bila ada; selain itu angkanya. */
        nilaiPlt(n) {
            if (n.teks) return n.teks;
            return this.num(n.angka);
        },

        /**
         * Analisa tanpa dasar penilaian di master.
         *
         * Keadaan ini TIDAK BOLEH ditampilkan sebagai layak: tanpa batas
         * min–max (perhitungan) atau daftar kriteria (non-perhitungan),
         * sistem tidak punya pembanding apa pun untuk menilai hasil.
         */
        tanpaMaster(a) {
            return a.Dasar_Kelayakan === "TANPA_MASTER"
                || a.Dasar_Kelayakan === "KRITERIA_TIDAK_COCOK";
        },
        layakCls(a) {
            if (this.tanpaMaster(a)) return "bg-warning-subtle text-warning";
            return a.Flag_Layak === "T"
                ? "bg-danger-subtle text-danger"
                : "bg-success-subtle text-success";
        },
        labelLayak(a) {
            if (this.tanpaMaster(a)) return "Belum Dinilai";
            return a.Flag_Layak === "T" ? "Tidak Layak" : "Layak";
        },
        ikonLayak(a) {
            if (this.tanpaMaster(a)) return "ri-question-fill";
            return a.Flag_Layak === "T" ? "ri-close-circle-fill" : "ri-checkbox-circle-fill";
        },

        /**
         * Format tetap: 24 Sep 2026 09:49.
         *
         * Tanggal diurai dari bagian YYYY-MM-DD-nya saja. Server mengirim
         * "2026-09-24 00:00:00.000" maupun "2026-09-24"; bentuk kedua dibaca
         * peramban sebagai UTC dan dapat bergeser sehari di zona tertentu.
         */
        stamp(tgl, jam) {
            if (!tgl) return "–";
            const [y, m, d] = String(tgl).slice(0, 10).split("-").map(Number);
            if (!y || !m || !d) return String(tgl);
            const bln = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
                         "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            const t = `${String(d).padStart(2, "0")} ${bln[m - 1]} ${y}`;
            return jam ? `${t} ${String(jam).slice(0, 5)}` : t;
        },
    },
};
</script>

<style scoped>
/* Wadah container query: seluruh mode tampilan mengikuti lebar ini. */
.ha { container: ha / inline-size; }

.ha-tw { background: #fff; border: 1px solid #e9ebec; border-radius: 6px; }
.ha--rata .ha-tw { border: none; border-radius: 0; }

.ha-t { font-size: .75rem; margin: 0; }
.ha-t thead th { font-size: .62rem; text-transform: uppercase; letter-spacing: .04em;
    color: #878a99; font-weight: 700; white-space: nowrap; background: #f8f9fc;
    border-bottom: 1px solid #e9ebec; padding: 8px 10px; }
.ha-t tbody td { padding: 8px 10px; border-bottom: 1px solid #f3f6f9; }
.ha-t tbody tr:last-child td { border-bottom: none; }
.ha-t tbody tr:hover { background: #fafcff; }
.ha-t .k-hasil { text-align: right; }
.ha-an { font-weight: 600; color: #495057; }
.ha-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .73rem; }
/* Nomor sampel tidak dipatahkan di tanda hubung. */
.ha-smp { white-space: nowrap; }
.ha-spec { display: flex; flex-direction: column; line-height: 1.3; }
.ha-spec em { font-style: normal; font-size: .6rem; color: #adb5bd; }
.ha-spec.is-none { color: #b8891b; font-size: .71rem; font-weight: 600; }
.ha-spec.is-none em { color: #c7a55a; }

/* Daftar kriteria yang dianggap layak — sejajar peran min–max pada
   analisa perhitungan. Kriteria yang dipakai hasil baris ini ditebalkan,
   sehingga hasil di luar daftar terbaca sebagai tidak ada yang menyala. */
.ha-krit { display: flex; flex-direction: column; gap: 1px; }
.ha-krit-i { display: flex; align-items: flex-start; gap: 3px;
    font-size: .68rem; color: #878a99; line-height: 1.35; }
.ha-krit-i i { font-size: .72rem; color: #ced4da; margin-top: 1px; flex-shrink: 0; }
.ha-krit-i.is-pick { color: #0a9e8a; font-weight: 700; }
.ha-krit-i.is-pick i { color: #0ab39c; }

/* Nilai hasil mengikuti putusan barisnya. */
.ha-hasil-bad { color: #e14a2b; }
.ha-hasil-na  { color: #b8891b; }

/* Penanda foto di samping nama analisa. */
.ha-foto { display: inline-flex; align-items: center; gap: 3px; margin-left: 6px;
    border: 1px solid #d5ecf7; background: #f0f9ff; color: #0369a1;
    border-radius: 10px; padding: 0 7px; font-size: .64rem; font-weight: 700;
    line-height: 1.6; cursor: pointer; vertical-align: middle; }
.ha-foto:hover { background: #0891b2; border-color: #0891b2; color: #fff; }

.ha-rs { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 3px; font-size: .66rem; }
.ha-rs code { font-size: .66rem; color: #495057; background: #f3f6f9; padding: 1px 5px; border-radius: 3px; }
.ha-rs i { color: #adb5bd; }
.ha-rs em { font-style: normal; color: #878a99; }
.ha-rs-no { font-size: .7rem; color: #adb5bd; }

/* Penginput & validator: nama di atas, cap waktu di bawah. */
.ha-vu { display: block; font-size: .72rem; color: #495057; font-weight: 500; white-space: nowrap; }
.ha-vt { display: block; font-size: .63rem; color: #878a99;
    font-family: ui-monospace, monospace; white-space: nowrap; }
.ha-vn { font-size: .68rem; color: #adb5bd; font-style: italic; white-space: nowrap; cursor: help; }
.ha-vn.is-tunggu { color: #9a6405; font-style: normal; font-weight: 600; cursor: default; }

/* Kolom gabungan penginput / validator (mode ringkas). */
.ha-jejak { display: flex; flex-direction: column; gap: 4px; }
.ha-jr { display: flex; align-items: flex-start; gap: 5px; font-size: .7rem; }
.ha-jr > i { color: #adb5bd; margin-top: 1px; }
.ha-jr b { display: block; color: #495057; font-weight: 500; white-space: nowrap; }
.ha-jr em { display: block; font-style: normal; font-size: .62rem; color: #878a99;
    font-family: ui-monospace, monospace; white-space: nowrap; }
.ha-jr.is-none b { color: #adb5bd; font-style: italic; }
.ha-jr.is-tunggu b { color: #9a6405; }

/* Sampel & resampling di bawah nama analisa (mode ringkas). */
.ha-mini { gap: 5px; align-items: center; flex-wrap: wrap; margin-top: 3px; }
.ha-mini code { font-size: .64rem; }

.ha-lay { position: relative; cursor: help; font-size: .68rem; font-weight: 600; }
.ha-i { opacity: .6; }
.ha-lay-r { font-size: .66rem; color: #878a99; margin-top: 4px; line-height: 1.35; }

/* Kolom dan isi yang hanya tampil pada mode tertentu. */
.ha-t .k-jejak, .ha-mini, .ha-lay-r, .ha-plt-daftar { display: none; }

/* Tooltip memakai position: fixed, bukan absolute.
   Tabel hasil berada di dalam wadah yang menggulir (overflow-y: auto),
   sehingga tooltip yang diposisikan absolut akan terpotong oleh tepi
   kontainer — persis yang terjadi pada baris paling atas dan paling bawah.
   Dengan fixed, tooltip lepas dari kliping dan posisinya dihitung di
   bukaTip() memakai koordinat viewport. */
.ha-tip { position: fixed; z-index: 1080; width: 260px;
    background: #212529; color: #e9ecef; border-radius: 5px;
    padding: 10px 12px; font-weight: 400; font-size: .7rem; line-height: 1.45;
    box-shadow: 0 8px 20px rgba(0,0,0,.25); display: flex; flex-direction: column; gap: 3px;
    text-align: left; cursor: default; white-space: normal; pointer-events: none; }
.ha-tip::after { content: ''; position: absolute; top: 100%;
    left: var(--tip-anchor, 50%); transform: translateX(-50%);
    border: 6px solid transparent; border-top-color: #212529; }
/* Varian ke bawah bila ruang di atas tidak cukup. */
.ha-tip.is-bawah::after { top: auto; bottom: 100%;
    border-top-color: transparent; border-bottom-color: #212529; }
.ha-tip-h { font-size: .6rem; text-transform: uppercase; letter-spacing: .07em; color: #adb5bd; font-weight: 700; }
.ha-tip-b { font-weight: 700; color: #fff; margin-bottom: 3px; }
.ha-tip-r { display: flex; justify-content: space-between; gap: 10px; }
.ha-tip-r em { font-style: normal; color: #adb5bd; }
.ha-tip-r b { font-family: ui-monospace, monospace; color: #fff;
    text-align: right; word-break: break-word; }
.ha-tip-s { font-size: .64rem; color: #868e96; margin-top: 2px; }
.ha-tip-o { display: flex; align-items: center; gap: 5px; font-size: .68rem; color: #ced4da;
    padding: 1px 0; flex-wrap: wrap; }
.ha-tip-o i { flex-shrink: 0; }
.ha-tip-o.is-on { color: #fff; font-weight: 700; }
.ha-tip-w { color: #ffd43b; margin-top: 2px; }
.ha-tip-pick { color: #ffd43b; font-size: .6rem; white-space: nowrap; margin-left: 2px; }

/* ===== PALATABILITAS: matriks pembanding =====
   Palet mengikuti modul lama (#0891b2 / #0369a1) agar palatabilitas
   terbaca sebagai hal yang sama di seluruh aplikasi. */
.ha--rata .ha-plt { padding: 12px 13px 2px; }

/* Satu blok per jenis analisa, dengan kepala berisi nama, penginput,
   dan validator. */
.ha-plt-blok { border: 1px solid #e9ebec; border-radius: 6px;
    overflow: hidden; margin-bottom: 12px; background: #fff; }
.ha-plt-blok .ha-tw { border: none; border-radius: 0; }
.ha-plt-h { display: flex; align-items: center; justify-content: space-between;
    gap: 6px 14px; flex-wrap: wrap;
    padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #e9ebec; }
.ha-plt-nm { display: inline-flex; align-items: center; gap: 7px; flex-wrap: wrap; }
.ha-plt-nm i { color: #0891b2; }
.ha-plt-nm b { font-size: .78rem; color: #495057; }
.ha-plt-kode { font-size: .62rem; color: #adb5bd;
    font-family: ui-monospace, monospace; }
.ha-plt-jejak { display: inline-flex; flex-wrap: wrap; gap: 4px 14px; }
.ha-jj { display: inline-flex; align-items: center; flex-wrap: wrap; gap: 0 4px;
    font-size: .66rem; color: #878a99; }
.ha-jj i { color: #adb5bd; }
.ha-jj b { color: #495057; font-weight: 600; }
.ha-jj em { font-style: normal; font-family: ui-monospace, monospace; font-size: .62rem; white-space: nowrap; }
.ha-jj.is-none em { font-family: inherit; font-style: italic; color: #adb5bd; }
.ha-jj.is-tunggu em { font-family: inherit; color: #9a6405; font-weight: 600; }

.ha-plt-t { margin: 0; }
.ha-plt-t th { white-space: nowrap; }
/* Kolom parameter: lebar tetap agar tabel dapat digeser menyamping dengan
   rapi ketika parameternya banyak. */
.ha-plt-th { background: #0891b2 !important; color: #fff !important;
    min-width: 118px; text-align: center; font-size: .66rem; }
.ha-plt-th small { display: block; font-weight: 400; opacity: .8;
    font-size: .9em; }
.ha-plt-th-pb { background: #e0f2fe !important; color: #0369a1 !important;
    min-width: 150px; position: sticky; left: 0; z-index: 2; }

.ha-plt-td-pb { font-weight: 600; color: #0369a1; font-size: .72rem;
    background: #f0f9ff; border-left: 3px solid #0891b2;
    position: sticky; left: 0; z-index: 1; }
.ha-plt-td { text-align: center; }
.ha-plt-nilai { font-family: ui-monospace, monospace; font-size: .74rem;
    font-weight: 600; color: #495057; }
.ha-plt-kosong { color: #ced4da; }

/* Daftar parameter -> nilai (layar sempit). */
.ha-plt-pb { padding: 10px 12px; }
.ha-plt-pb + .ha-plt-pb { border-top: 1px solid #eef1f5; }
.ha-plt-pb-n { display: inline-flex; align-items: center; gap: 5px; font-size: .7rem;
    font-weight: 700; color: #0369a1; margin-bottom: 6px; }
.ha-plt-dl { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0; margin: 0; }
.ha-plt-dl dt, .ha-plt-dl dd { padding: 5px 0; border-bottom: 1px solid #f3f6f9;
    font-size: .72rem; margin: 0; }
.ha-plt-dl dt { color: #6c757d; font-weight: 500; padding-right: 10px; }
.ha-plt-dl dt small { color: #adb5bd; }
.ha-plt-dl dd { font-family: ui-monospace, monospace; font-weight: 600; color: #495057;
    text-align: right; }

/* ===================== MODE RINGKAS: 540–879px ===================== */
@container ha (max-width: 879px) {
    .ha-t .k-smp, .ha-t .k-rs, .ha-t .k-in, .ha-t .k-val { display: none; }
    .ha-t .k-jejak { display: table-cell; }
    .ha-mini { display: flex; }
    /* Lebih rapat: judul kolom boleh terlipat. */
    .ha-t thead th { white-space: normal; padding: 7px 8px; }
    .ha-t tbody td { padding: 8px; }
}

/* ===================== MODE KARTU: < 540px ===================== */
@container ha (max-width: 539px) {
    .ha-tw { overflow: visible; }
    .ha-t thead { display: none; }
    .ha-t, .ha-t tbody, .ha-t tr { display: block; width: 100%; }
    .ha-t tbody tr { padding: 10px 12px; border-bottom: 1px solid #eef1f5; }
    .ha-t tbody tr:last-child { border-bottom: none; }
    .ha-t tbody tr:hover { background: transparent; }

    /* Setiap sel: label di kiri, nilai di kanan. */
    .ha-t tbody td { display: grid; grid-template-columns: 92px minmax(0, 1fr);
        column-gap: 10px; align-items: start; padding: 4px 0; border: none; }
    .ha-t tbody td::before { content: attr(data-label); font-size: .58rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .04em; color: #adb5bd; padding-top: 2px; }
    .ha-t .k-hasil { text-align: left; }

    /* Nama analisa menjadi judul kartu. */
    .ha-t tbody td.k-nama { display: block; padding: 0 0 6px; }
    .ha-t tbody td.k-nama::before { content: none; }
    .ha-t tbody td.k-nama .ha-an { font-size: .8rem; }

    /* Kolom terpisah kembali tampil; kolom gabungan disembunyikan. */
    .ha-t .k-smp, .ha-t .k-rs, .ha-t .k-in, .ha-t .k-val { display: grid; }
    .ha-t .k-rs.is-kosong { display: none; }
    .ha-t .k-jejak, .ha-mini { display: none; }
    .ha-lay-r { display: block; }
    .ha-vu, .ha-vt { white-space: normal; }

    /* Matriks palatabilitas menjadi daftar parameter -> nilai. */
    .ha-plt-blok .ha-tw { display: none; }
    .ha-plt-daftar { display: block; }
    .ha--rata .ha-plt { padding: 10px 10px 2px; }
    .ha-plt-h { padding: 8px 10px; }
}
</style>
