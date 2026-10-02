<template>
    <div ref="akar" class="vh" :class="kelasTata" :style="{ '--bawah': bawah + 'px' }">
        <!--
            Tata letak tiga panel, pola umum LIMS enterprise:

              KIRI   : daftar kerja (antrean sampel x klasifikasi)
              TENGAH : hasil analisa sampel terpilih + aksi keputusan
              KANAN  : sample lifecycle — dapat ditutup agar panel tengah lega

            RESPONSIF — mengikuti lebar halaman ini sendiri (ResizeObserver),
            bukan lebar jendela, karena sidebar aplikasi ikut memakan ruang:
              <  768px : satu panel — daftar ATAU detail; lifecycle layar penuh
              < 1024px : dua panel  — lifecycle menjadi laci di atas detail
              >= 1024px: tiga panel — lifecycle di samping, lebarnya dapat ditarik
            Tabel hasil menyesuaikan lebar wadahnya sendiri (lihat
            TabelHasilAnalisa).

            Komentar ini sengaja berada di DALAM elemen akar: komentar di luar
            akar membuat komponen ber-akar fragment pada mode dev.
        -->

        <!-- ================= TOPBAR ================= -->
        <div class="vh-top">
            <div class="vh-top-l">
                <h4 class="vh-top-t">Verifikasi Hasil Analisa</h4>
                <p class="vh-top-s">Tinjau hasil per klasifikasi aktivitas beserta jejak sampel sebelum finalisasi.</p>
            </div>

            <div class="vh-top-r">
                <!-- Filter status bergaya pill; digeser menyamping bila
                     layarnya sempit, bukan dipatahkan menjadi beberapa baris. -->
                <div class="vh-pills">
                    <button v-for="t in tabs" :key="t.kode" class="vh-pill"
                            :class="{ 'is-on': filterStatus === t.kode }" @click="gantiTab(t.kode)">
                        {{ t.label }}
                        <span class="vh-pill-c" :class="'c-' + t.warna">{{ ringkasan[t.hitung] }}</span>
                    </button>
                </div>

                <div class="vh-top-a">
                    <!-- Cakupan mesin: verifikasi hanya untuk sampel AUTOCLAVE. -->
                    <span v-if="profil && (profil.cakupan_mesin || []).length" class="vh-scope"
                          title="Verifikasi hanya untuk sampel dari mesin ini">
                        <i class="ri-settings-3-line"></i>
                        <span class="vh-scope-t">{{ profil.cakupan_mesin.join(' · ') }}</span>
                    </span>
                    <!-- Identitas user sudah tampil di topbar aplikasi, jadi di
                         sini cukup kewenangan klasifikasinya saja. -->
                    <span v-if="profil && (profil.aktivitas || []).length" class="vh-scope"
                          :title="profil.aktivitas.map(a => a.Nama_Aktivitas).join(' · ')">
                        <i class="ri-shield-check-line"></i>
                        <span class="vh-scope-t">{{ profil.aktivitas.map(a => a.Nama_Aktivitas).join(' · ') }}</span>
                    </span>
                    <button class="vh-ico" title="Muat ulang" @click="muat"><i class="ri-refresh-line"></i></button>
                </div>
            </div>
        </div>

        <!-- Bulk bar di atas panel, bukan di dalam panel tengah, supaya tetap
             terlihat pada mode satu panel saat yang tampil adalah daftar. -->
        <transition name="vh-fade">
            <div v-if="terpilih.length" class="vh-bulk">
                <span><i class="ri-checkbox-multiple-line me-1"></i><b>{{ terpilih.length }}</b> dipilih</span>
                <button class="vh-bulk-x" @click="terpilih = []">batalkan</button>
                <span class="vh-bulk-sp"></span>
                <button class="btn btn-sm btn-light" @click="buka()">
                    <i class="ri-shield-check-line me-1"></i>Beri Rekomendasi
                </button>
            </div>
        </transition>

        <!-- ================= BODY ================= -->
        <div class="vh-body">

            <!-- ===== PANEL KIRI: DAFTAR KERJA ===== -->
            <aside ref="kiri" class="vh-left" :class="{ 'is-sembunyi': tunggal && terpilihItem }">
                <!-- pencarian -->
                <div class="vh-lsearch">
                    <i class="ri-search-line"></i>
                    <input v-model="search" @keyup.enter="cari" placeholder="Cari no. sampel, PO, barang..." />
                    <button v-if="search" class="vh-lsearch-x" @click="search=''; cari()"><i class="ri-close-line"></i></button>
                </div>

                <!-- pilih semua -->
                <div v-if="daftar.length" class="vh-lall">
                    <label>
                        <input type="checkbox" class="form-check-input" :checked="semua" @change="toggleSemua" />
                        <span>Pilih semua</span>
                    </label>
                    <span v-if="terpilih.length" class="vh-lall-c">{{ terpilih.length }} dipilih</span>
                </div>

                <!-- daftar -->
                <div class="vh-list">
                    <div v-if="loading" class="vh-state">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span>Memuat…</span>
                    </div>

                    <!-- Kegagalan memuat dibedakan dari "memang kosong":
                         keduanya sama-sama menghasilkan daftar nol, tetapi
                         artinya jauh berbeda bagi verifikator. -->
                    <div v-else-if="galat" class="vh-state vh-state--empty">
                        <i class="ri-error-warning-line text-danger"></i>
                        <p>{{ galat }}</p>
                        <button class="btn btn-sm btn-light mt-2" @click="muat">
                            <i class="ri-refresh-line me-1"></i>Coba lagi
                        </button>
                    </div>

                    <div v-else-if="!daftar.length" class="vh-state vh-state--empty">
                        <i class="ri-inbox-line"></i>
                        <p>{{ filterStatus ? 'Tidak ada item ' + labelStatus(filterStatus).toLowerCase()
                            : 'Belum ada hasil analisa untuk diverifikasi' }}</p>
                    </div>

                    <div v-else>
                        <div v-for="it in daftar" :key="it.kunci" class="vh-li-wrap">
                            <input type="checkbox" class="form-check-input vh-li-cb"
                                   :checked="sel(it)" @change="pilih(it)" @click.stop />
                            <button class="vh-li" :class="[
                                        'st-' + it.Kode_Status.toLowerCase(),
                                        { 'is-active': terpilihItem && terpilihItem.kunci === it.kunci }]"
                                    @click="bukaItem(it)">
                                <span class="vh-li-acc"></span>
                                <span class="vh-li-b">
                                    <span class="vh-li-r1">
                                        <b>{{ it.No_Sampel }}</b>
                                        <span class="badge vh-akt" :class="'akt-' + it.Kode_Aktivitas_Lab.toLowerCase()">
                                            {{ it.Kode_Aktivitas_Lab }}
                                        </span>
                                    </span>
                                    <span class="vh-li-r2">{{ it.Nama_Barang }}</span>
                                    <span class="vh-li-r3">
                                        <span class="vh-chip"><i class="ri-file-list-3-line"></i>{{ it.No_Po }}</span>
                                        <!-- Pembeda utama antar sampel ber-PO sama. -->
                                        <span class="vh-chip is-mesin" title="Mesin">
                                            <i class="ri-settings-3-line"></i>{{ labelMesin(it) }}
                                        </span>
                                        <span class="vh-chip"><i class="ri-flask-line"></i>{{ it.Jumlah_Analisa }}</span>
                                        <span v-if="it.Jumlah_Tidak_Layak > 0" class="vh-chip is-bad">
                                            <i class="ri-alert-line"></i>{{ it.Jumlah_Tidak_Layak }} TL
                                        </span>
                                        <span v-if="it.Jumlah_Tanpa_Master > 0" class="vh-chip is-warn"
                                              title="Kriteria kelayakan belum diatur di master">
                                            <i class="ri-error-warning-line"></i>{{ it.Jumlah_Tanpa_Master }} tanpa master
                                        </span>
                                    </span>
                                    <span class="vh-li-r4">
                                        <span class="badge" :class="badgeStatus(it.Kode_Status)">
                                            {{ labelStatus(it.Kode_Status) }}
                                        </span>
                                    </span>
                                </span>
                                <i class="ri-arrow-right-s-line vh-li-go"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- pagination -->
                <div class="vh-lpg">
                    <span>{{ pagination.dari }}&ndash;{{ pagination.sampai }} / {{ pagination.total_data }}</span>
                    <div class="vh-lpg-b">
                        <button :disabled="pagination.page <= 1" @click="keHal(pagination.page - 1)">
                            <i class="ri-arrow-left-s-line"></i>
                        </button>
                        <span>{{ pagination.page }} / {{ pagination.total_page }}</span>
                        <button :disabled="pagination.page >= pagination.total_page" @click="keHal(pagination.page + 1)">
                            <i class="ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                    <select v-model.number="limit" @change="gantiLimit">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
            </aside>

            <!-- ===== PANEL TENGAH: HASIL ANALISA ===== -->
            <section class="vh-center" :class="{ 'is-sembunyi': tunggal && !terpilihItem }">

                <!-- belum memilih -->
                <div v-if="!terpilihItem" class="vh-state vh-state--empty vh-center-empty">
                    <i class="ri-file-list-3-line"></i>
                    <h6>Pilih sampel untuk ditinjau</h6>
                    <p>Hasil analisa akan ditampilkan di sini beserta dasar penilaian kelayakannya.</p>
                </div>

                <template v-else>
                    <!-- header detail -->
                    <div class="vh-dh">
                        <button v-if="tunggal" class="vh-back" title="Kembali ke daftar" @click="kembali">
                            <i class="ri-arrow-left-line"></i>
                        </button>
                        <div class="vh-dh-id">
                            <div class="vh-dh-r1">
                                <span class="vh-dh-smp">{{ terpilihItem.No_Sampel }}</span>
                                <span class="badge vh-akt" :class="'akt-' + terpilihItem.Kode_Aktivitas_Lab.toLowerCase()">
                                    {{ terpilihItem.Nama_Aktivitas }}
                                </span>
                                <span v-if="terpilihItem.Flag_Trial_Produksi === 'Y'"
                                      class="badge bg-warning-subtle text-warning">Trial Produksi</span>
                                <span class="badge" :class="badgeStatus(terpilihItem.Kode_Status)">
                                    <i :class="ikonStatus(terpilihItem.Kode_Status)" class="me-1"></i>
                                    {{ labelStatus(terpilihItem.Kode_Status) }}
                                </span>
                            </div>
                            <!-- Grid: kolomnya menyesuaikan lebar, sehingga
                                 tidak pernah patah menjadi baris-baris acak. -->
                            <dl class="vh-dh-meta">
                                <div><dt>No. PO</dt><dd>{{ terpilihItem.No_Po }}</dd></div>
                                <div><dt>Batch</dt><dd>{{ terpilihItem.No_Batch ?? '-' }}</dd></div>
                                <!-- Nomor PO dan batch kerap sama antar sampel;
                                     mesin inilah pembedanya. -->
                                <div>
                                    <dt>Mesin</dt>
                                    <dd class="vh-mesin">
                                        <i class="ri-settings-3-line me-1"></i>{{ labelMesin(terpilihItem) }}
                                    </dd>
                                </div>
                                <div><dt>Analisa</dt><dd>{{ terpilihItem.Jumlah_Analisa }}</dd></div>
                                <div><dt>Produk</dt><dd>{{ terpilihItem.Nama_Barang }}</dd></div>
                                <!-- Siapa menginput hasil dan jam berapa, serta
                                     siapa memvalidasinya — orangnya kerap
                                     berbeda. Rincian per analisa ada di tabel. -->
                                <div>
                                    <dt>Diinput</dt>
                                    <dd>{{ jejak.input.nama || '-' }}
                                        <small v-if="jejak.input.waktu">{{ jejak.input.waktu }}</small></dd>
                                </div>
                                <div>
                                    <dt>Divalidasi</dt>
                                    <dd v-if="jejak.validasi.nama">{{ jejak.validasi.nama }}
                                        <small>{{ jejak.validasi.waktu }}</small>
                                        <small v-if="jejak.validasi.kurang" class="vh-dd-warn">
                                            {{ jejak.validasi.kurang }} analisa tidak tercatat
                                        </small></dd>
                                    <dd v-else class="vh-dd-none">tidak tercatat</dd>
                                </div>
                            </dl>
                        </div>
                        <button class="vh-lcbtn" :class="{ 'is-on': panelKanan }" title="Sample Lifecycle"
                                @click="toggleKanan">
                            <i class="ri-route-line"></i><span class="vh-lcbtn-t">Lifecycle</span>
                            <i :class="panelKanan ? 'ri-contract-right-line' : 'ri-expand-left-line'" class="vh-lcbtn-c"></i>
                        </button>
                    </div>

                    <!-- isi -->
                    <div class="vh-dbody">
                        <!-- alert mutu -->
                        <div v-if="terpilihItem.Jumlah_Tidak_Layak > 0" class="vh-al vh-al--bad">
                            <i class="ri-error-warning-line"></i>
                            <span><b>{{ terpilihItem.Jumlah_Tidak_Layak }} analisa tidak layak</b>
                                &mdash; periksa sebelum memberi rekomendasi.</span>
                        </div>
                        <!-- Peringatan "belum dapat dinilai" tidak relevan untuk
                             palatabilitas: di sana ketiadaan kriteria memang
                             wajar, bukan kelalaian pengaturan master. -->
                        <div v-if="!terpilihItem.Butuh_Pembanding && terpilihItem.Jumlah_Tanpa_Master > 0"
                             class="vh-al vh-al--warn">
                            <i class="ri-error-warning-line"></i>
                            <span>
                                <b>{{ terpilihItem.Jumlah_Tanpa_Master }} analisa belum dapat dinilai.</b>
                                Kriteria kelayakannya belum diatur di master, sehingga hasilnya tidak dapat
                                dinyatakan layak maupun tidak layak. Atur di master bila analisa tersebut
                                ikut menentukan rekomendasi.
                            </span>
                        </div>
                        <div v-if="terpilihItem.Jumlah_Resampling > 0" class="vh-al vh-al--info">
                            <i class="ri-loop-left-line"></i>
                            <span><b>{{ terpilihItem.Jumlah_Resampling }}&times; resampling</b> tercatat pada sampel ini.</span>
                        </div>

                        <!-- Tabel hasil (atau matriks palatabilitas) — komponen
                             yang sama dengan layar Finalisasi. -->
                        <TabelHasilAnalisa :analisa="terpilihItem.analisa"
                                           :butuh-pembanding="terpilihItem.Butuh_Pembanding"
                                           :pembanding="terpilihItem.Pembanding"
                                           :matriks="terpilihItem.Matriks_Pembanding" />

                        <div v-if="terpilihItem.Catatan" class="vh-note"
                             :class="{ 'is-bad': terpilihItem.Kode_Status === 'TIDAK_REKOM' }">
                            <i class="ri-chat-quote-line"></i>
                            <span><b>Catatan verifikator:</b> {{ terpilihItem.Catatan }}</span>
                        </div>
                    </div>

                    <!-- footer aksi -->
                    <div class="vh-dfoot">
                        <span class="vh-dfoot-i">
                            <i class="ri-information-line me-1"></i>
                            Rekomendasi tersimpan sebagai jejak audit
                        </span>
                        <div class="vh-dfoot-b">
                            <button class="btn btn-sm btn-primary" @click="buka(terpilihItem)">
                                <i class="ri-shield-check-line me-1"></i>
                                {{ terpilihItem.Kode_Status === 'MENUNGGU' ? 'Beri Rekomendasi' : 'Ubah Rekomendasi' }}
                            </button>
                        </div>
                    </div>
                </template>
            </section>

            <!-- ===== PEMISAH PANEL (dapat ditarik) ===== -->
            <!--
                Batas antara panel tengah dan panel kanan. Ditarik seperti
                batas kolom di Excel: lebar panel kanan mengikuti posisi
                kursor, panel tengah menyesuaikan sisanya. Hanya ada pada
                tata letak tiga panel.
            -->
            <div v-if="panelKanan && terpilihItem && !laci"
                 class="vh-split" :class="{ 'is-drag': geser }"
                 title="Tarik untuk mengubah lebar — klik dua kali untuk kembali ke lebar semula"
                 @mousedown.prevent="mulaiGeser"
                 @dblclick="resetLebar">
                <span class="vh-split-grip"></span>
            </div>

            <!-- Latar laci: ketukan di luar laci menutupnya. -->
            <transition name="vh-pudar">
                <div v-if="panelKanan && terpilihItem && laci && !tunggal" class="vh-laci-bd"
                     @click="tutupKanan"></div>
            </transition>

            <!-- ===== PANEL KANAN: SAMPLE LIFECYCLE ===== -->
            <!-- Mode satu panel: lembar layar penuh, dipindah ke <body>.
                 .page-content milik layout aplikasi memakai transform,
                 sehingga elemen fixed di dalamnya diposisikan terhadap area
                 konten — bukan terhadap layar — dan tertutup topbar. -->
            <Teleport to="body" :disabled="!tunggal">
            <transition name="vh-geser">
                <aside v-if="panelKanan && terpilihItem" class="vh-right"
                       :class="{ 'is-max': lcMaks, 'is-laci': laci && !tunggal, 'is-lembar': tunggal }"
                       :style="gayaKanan">
                    <div class="vh-rh">
                        <span><i class="ri-route-line me-2"></i>Sample Lifecycle</span>
                        <span class="vh-rh-act">
                            <button v-if="!tunggal" class="vh-rh-x" :title="lcMaks ? 'Kecilkan' : 'Perbesar'"
                                    @click="lcMaks = !lcMaks">
                                <i :class="lcMaks ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'"></i>
                            </button>
                            <button class="vh-rh-x" title="Tutup panel" @click="tutupKanan">
                                <i class="ri-close-line"></i>
                            </button>
                        </span>
                    </div>

                    <div class="vh-rb">
                        <LifecycleSampel :data="lcData" />
                    </div>
                </aside>
            </transition>
            </Teleport>
        </div>

        <!-- ================= MODAL KEPUTUSAN ================= -->
        <!-- Dipindah ke <body> dengan alasan yang sama dengan lembar
             lifecycle: agar benar-benar menutupi layar. -->
        <Teleport to="body">
        <div v-if="modal.show" class="vh-bd" @click.self="modal.show = false">
            <div class="vh-md vh-md--lg">
                <div class="vh-md-h" :class="'bg-' + warnaBs(kepAktif && kepAktif.Warna_Badge)">
                    <i :class="(kepAktif && kepAktif.Ikon) || 'ri-shield-check-line'"></i>
                    <div>
                        <b>Rekomendasi Verifikasi</b>
                        <em>{{ modal.items.length }} item akan diproses</em>
                    </div>
                    <button class="vh-md-x" @click="modal.show = false"><i class="ri-close-line"></i></button>
                </div>

                <div class="vh-md-b">
                    <!-- item yang diproses -->
                    <div class="vh-cfm">
                        <div v-for="i in modal.items" :key="i.kunci" class="vh-cfm-r">
                            <span class="badge vh-akt" :class="'akt-' + i.Kode_Aktivitas_Lab.toLowerCase()">{{ i.Kode_Aktivitas_Lab }}</span>
                            <b>{{ i.No_Sampel }}</b>
                            <span>{{ labelMesin(i) }} · {{ i.Jumlah_Analisa }} analisa · {{ i.Nama_Barang }}</span>
                        </div>
                    </div>

                    <!-- rekomendasi sistem berdasarkan hasil analisa -->
                    <div class="vh-reko" :class="'is-' + reko.warna">
                        <i :class="reko.ikon"></i>
                        <span>
                            <b>Rekomendasi sistem:</b> {{ reko.teks }}
                            <em v-if="reko.rinci">{{ reko.rinci }}</em>
                        </span>
                    </div>

                    <!-- pilihan tingkat keputusan -->
                    <label class="form-label vh-lb">Tingkat rekomendasi</label>

                    <!-- Master gagal dimuat: katakan apa adanya, jangan
                         biarkan verifikator menatap daftar kosong. -->
                    <div v-if="!masterKeputusan.length" class="vh-al vh-al--bad">
                        <i class="ri-error-warning-line"></i>
                        <span>
                            <b>Pilihan rekomendasi gagal dimuat.</b>
                            Muat ulang halaman, lalu coba kembali.
                            <button class="btn btn-sm btn-light ms-2" @click="ulangiMaster">
                                <i class="ri-refresh-line me-1"></i>Coba muat ulang
                            </button>
                        </span>
                    </div>

                    <div v-else class="vh-keps">
                        <label v-for="k in masterKeputusan" :key="k.Kode_Keputusan"
                               class="vh-kep" :class="['is-' + k.Warna_Badge, { 'is-on': modal.keputusan === k.Kode_Keputusan }]">
                            <input type="radio" :value="k.Kode_Keputusan" v-model="modal.keputusan" />
                            <span class="vh-kep-b">
                                <span class="vh-kep-h">
                                    <i :class="k.Ikon"></i>
                                    <b>{{ k.Nama_Keputusan }}</b>
                                    <span v-if="k.Flag_Wajib_Catatan === 'Y'" class="vh-kep-tag">catatan wajib</span>
                                    <span v-else class="vh-kep-tag is-opt">catatan opsional</span>
                                </span>
                                <span class="vh-kep-k">{{ k.Keterangan }}</span>
                                <span class="vh-kep-f">
                                    <span :class="k.Flag_Boleh_Lanjut === 'Y' ? 'text-success' : 'text-danger'">
                                        <i :class="k.Flag_Boleh_Lanjut === 'Y' ? 'ri-arrow-right-circle-line' : 'ri-forbid-line'"></i>
                                        {{ k.Flag_Boleh_Lanjut === 'Y' ? 'Dapat diteruskan ke finalisasi' : 'Tidak dapat diteruskan' }}
                                    </span>
                                    <span v-if="k.Flag_Perlu_Tindak === 'Y'" class="text-warning">
                                        <i class="ri-tools-line"></i>Perlu tindak lanjut
                                    </span>
                                </span>
                            </span>
                        </label>
                    </div>

                    <!-- catatan -->
                    <label class="form-label vh-lb">
                        {{ kepAktif && kepAktif.Kode_Keputusan === 'TIDAK_REKOM'
                            ? 'Alasan tidak direkomendasikan' : 'Justifikasi / catatan' }}
                        <span v-if="wajibCatatan" class="text-danger">
                            wajib &mdash; minimal {{ kepAktif.Panjang_Min_Catatan || 1 }} karakter
                        </span>
                        <span v-else class="text-muted">opsional</span>
                    </label>
                    <textarea v-model="modal.catatan" rows="3" class="form-control"
                              :placeholder="placeholderCatatan"></textarea>
                    <div v-if="wajibCatatan" class="vh-count"
                         :class="{ 'is-ok': modal.catatan.trim().length >= (kepAktif.Panjang_Min_Catatan || 1) }">
                        {{ modal.catatan.trim().length }} / {{ kepAktif.Panjang_Min_Catatan || 1 }} karakter
                    </div>

                    <div v-if="modal.err" class="vh-al vh-al--bad mt-2">
                        <i class="ri-error-warning-line"></i><span>{{ modal.err }}</span>
                    </div>

                    <div class="vh-hint">
                        <i class="ri-information-line me-1"></i>
                        Seluruh rekomendasi beserta catatannya tersimpan permanen sebagai jejak audit
                        dan dapat ditinjau kembali pada Sample Lifecycle.
                    </div>
                </div>

                <div class="vh-md-f">
                    <button class="btn btn-sm btn-light" @click="modal.show = false">Batal</button>
                    <button class="btn btn-sm" :class="'btn-' + warnaBs(kepAktif && kepAktif.Warna_Badge)"
                            :disabled="simpan || !modal.keputusan" @click="kirim">
                        <span v-if="simpan" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else :class="(kepAktif && kepAktif.Ikon) || 'ri-check-line'" class="me-1"></i>
                        Simpan Rekomendasi
                    </button>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>

<script>
import axios from "axios";
import TabelHasilAnalisa from "./components/TabelHasilAnalisa.vue";
import LifecycleSampel from "./components/LifecycleSampel.vue";

export default {
    name: "VerifikasiHasilAnalisa",
    components: { TabelHasilAnalisa, LifecycleSampel },
    data() {
        return {
            profil: null,
            daftar: [],
            terpilihItem: null,
            ringkasan: { Total: 0, Menunggu: 0, Rekomendasi: 0, Bersyarat: 0, Tidak: 0 },
            pagination: { page: 1, limit: 10, total_data: 0, total_page: 1, dari: 0, sampai: 0 },
            loading: false,
            galat: "",
            search: "",
            // Default sengaja MENUNGGU: verifikator membuka layar ini untuk
            // mengerjakan antrean, bukan untuk melihat seluruh riwayat.
            filterStatus: "MENUNGGU",
            limit: 10,
            terpilih: [],
            simpan: false,
            panelKanan: true,
            lcData: null,

            // Lebar halaman ini (px), diukur ResizeObserver. Seluruh mode
            // tata letak diturunkan dari sini — bukan dari lebar jendela,
            // karena sidebar aplikasi ikut memakan ruang.
            lebar: 0,
            // Posisi gulir daftar sebelum detail dibuka pada mode satu panel,
            // supaya kembali ke daftar tidak melompat ke paling atas.
            gulirDaftar: 0,
            // Tinggi navigasi bawah aplikasi (hanya ada di HP). Elemen yang
            // menempel di bawah layar digeser setinggi ini agar tidak tertutup.
            bawah: 0,

            // --- lebar panel kanan ---
            // Lebar disimpan dalam piksel agar hasil tarikan tetap sama
            // ketika halaman diubah ukurannya, lalu dijepit ulang ke batas
            // yang masuk akal pada setiap perubahan lebar.
            lcLebar: 380,
            lcMaks: false,      // panel ditampilkan selebar area kerja
            geser: false,       // sedang menarik garis pemisah
            masterKeputusan: [],
            modal: { show: false, keputusan: "", items: [], catatan: "", err: "" },
            tabs: [
                { kode: "MENUNGGU",        label: "Menunggu",         hitung: "Menunggu",    warna: "w" },
                { kode: "REKOMENDASI",     label: "Direkomendasikan", hitung: "Rekomendasi", warna: "o" },
                { kode: "REKOM_BERSYARAT", label: "Bersyarat",        hitung: "Bersyarat",   warna: "w" },
                { kode: "TIDAK_REKOM",     label: "Tidak",            hitung: "Tidak",       warna: "b" },
                { kode: "",                label: "Semua",            hitung: "Total",       warna: "n" },
            ],
        };
    },
    computed: {
        semua() { return this.daftar.length > 0 && this.terpilih.length === this.daftar.length; },

        /** Satu panel: daftar ATAU detail (HP dan tablet kecil). */
        tunggal() { return this.lebar > 0 && this.lebar < 768; },
        /** Panel kanan menjadi laci di atas detail, bukan kolom sendiri. */
        laci() { return this.lebar > 0 && this.lebar < 1024; },
        ukuran() {
            const w = this.lebar;
            if (!w || w >= 1440) return "xl";
            if (w >= 1100) return "lg";
            if (w >= 768) return "md";
            return w >= 576 ? "sm" : "xs";
        },
        kelasTata() {
            return ["uk-" + this.ukuran, { "is-tunggal": this.tunggal, "is-laci": this.laci }];
        },

        /**
         * Ringkasan penginput dan validator item terpilih untuk kepala
         * detail. Rinciannya per analisa ada di tabel hasil.
         */
        jejak() {
            const a = this.terpilihItem?.analisa || [];
            // Dihitung per analisa, bukan per baris: palatabilitas menyimpan
            // satu baris per parameter.
            const kunci = (x) => `${x.Id_Jenis_Analisa}|${x.No_Sampel_Uji}|${x.Id_Pembanding}`;
            const tercatat = new Set(a.filter((x) => x.Validasi).map(kunci)).size;
            return {
                input: this.ringkasJejak(a.map((x) => x.Input)),
                validasi: {
                    ...this.ringkasJejak(a.map((x) => x.Validasi)),
                    kurang: new Set(a.map(kunci)).size - tercatat,
                },
            };
        },

        /** Jenis keputusan yang sedang dipilih pada modal. */
        kepAktif() {
            return this.masterKeputusan.find((k) => k.Kode_Keputusan === this.modal.keputusan) || null;
        },
        wajibCatatan() {
            return !!(this.kepAktif && this.kepAktif.Flag_Wajib_Catatan === "Y");
        },
        placeholderCatatan() {
            if (!this.kepAktif) return "Pilih tingkat rekomendasi terlebih dahulu";
            if (this.kepAktif.Kode_Keputusan === "TIDAK_REKOM")
                return "Jelaskan mengapa hasil tidak memenuhi kriteria kelayakan dan tindak lanjut yang diperlukan";
            if (this.kepAktif.Kode_Keputusan === "REKOM_BERSYARAT")
                return "Jelaskan hal yang perlu diperhatikan dan dasar pertimbangan hasil tetap dapat diteruskan";
            return "Catatan tambahan bila ada (opsional)";
        },

        /**
         * Rekomendasi sistem berdasarkan hasil analisa item terpilih.
         *
         * Hanya saran; keputusan tetap di tangan verifikator. Gunanya
         * mempercepat penilaian pada daftar yang panjang.
         */
        reko() {
            const items = this.modal.items || [];
            const tl = items.reduce((n, i) => n + (i.Jumlah_Tidak_Layak || 0), 0);
            const tm = items.reduce((n, i) => n + (i.Jumlah_Tanpa_Master || 0), 0);
            const rs = items.reduce((n, i) => n + (i.Jumlah_Resampling || 0), 0);

            if (tl > 0) {
                return {
                    warna: "bad", ikon: "ri-close-circle-line",
                    teks: "Tidak Direkomendasikan",
                    rinci: `${tl} analisa di luar kriteria kelayakan.` + (rs > 0 ? ` ${rs}x pengulangan tercatat.` : ""),
                };
            }
            if (tm > 0) {
                return {
                    warna: "warn", ikon: "ri-error-warning-line",
                    teks: "Direkomendasikan Bersyarat",
                    rinci: `${tm} analisa belum dapat dinilai karena kriteria kelayakannya `
                        + `belum diatur di master. Pastikan hal ini dapat dipertanggungjawabkan.`,
                };
            }
            return {
                warna: "ok", ikon: "ri-checkbox-circle-line",
                teks: "Direkomendasikan",
                rinci: "Seluruh analisa memenuhi kriteria kelayakan di master.",
            };
        },

        /**
         * Lebar panel kanan. Saat diperbesar atau menjadi laci, lebar
         * diserahkan ke CSS.
         */
        gayaKanan() {
            if (this.lcMaks || this.laci) return {};
            return { width: this.lcLebar + "px" };
        },

        /** Lembar layar penuh atau modal sedang menutupi halaman. */
        kunciGulir() {
            return this.modal.show || (this.tunggal && this.panelKanan && !!this.terpilihItem);
        },
    },
    watch: {
        // Halaman di belakang lembar / modal tidak ikut tergulir.
        kunciGulir(v) { document.body.style.overflow = v ? "hidden" : ""; },
    },
    async mounted() {
        // Lebar diukur lebih dulu, sebelum data dimuat, supaya keputusan
        // "buka item pertama" sudah tahu apakah layar ini satu panel.
        // Panel kanan tampil secara bawaan; pengguna yang menentukan bila
        // ingin menutupnya, dan pilihan itu diingat.
        this.panelKanan = this.pilihanKanan();
        this.ukurUlang(this.$refs.akar.getBoundingClientRect().width);
        this.pengamat = new ResizeObserver((e) => this.ukurUlang(e[0].contentRect.width));
        this.pengamat.observe(this.$refs.akar);
        this.muatLebar();
        window.addEventListener("keydown", this.tombolPintas);

        // Ketiga permintaan ini memakai sesi berbasis file, yang mengunci
        // berkas sesi selama satu permintaan berjalan. Bila dikirim serentak,
        // permintaan daftar kerja dapat terlayani sebelum sesi siap sehingga
        // kewenangan terbaca kosong dan layar tampil nol — baru terisi
        // setelah pengguna menekan tab. Karena itu dijalankan berurutan.
        await this.muatProfil();
        await this.muatMasterKeputusan();
        await this.muat();
    },
    beforeUnmount() {
        if (this.pengamat) this.pengamat.disconnect();
        document.body.style.overflow = "";
        window.removeEventListener("keydown", this.tombolPintas);
        // Penarikan yang belum selesai saat komponen ditutup harus
        // dilepas, agar listener dokumen tidak tertinggal.
        if (this.geser) this.selesaiGeser();
    },
    methods: {
        /**
         * Terapkan lebar halaman yang baru. Saat masuk mode laci, panel
         * kanan ditutup agar tidak menutupi isi; saat kembali ke layar
         * lebar, panel mengikuti pilihan terakhir pengguna.
         */
        ukurUlang(w) {
            const laciSebelum = this.laci;
            this.lebar = Math.round(w);
            this.bawah = this.tinggiNavBawah();

            if (this.laci && !laciSebelum) {
                this.panelKanan = false;
                this.lcMaks = false;
            } else if (!this.laci && laciSebelum) {
                this.panelKanan = this.pilihanKanan();
                if (this.panelKanan && this.terpilihItem) this.muatLifecycle(this.terpilihItem.No_Sampel);
            }
            // Lebar hasil tarikan dijepit ulang mengikuti lebar yang baru.
            this.lcLebar = this.batasLebar(this.lcLebar);
        },
        /** Tinggi #mobileBottomNav milik layout aplikasi, 0 bila tidak tampil. */
        tinggiNavBawah() {
            const nav = document.getElementById("mobileBottomNav");
            if (!nav || getComputedStyle(nav).display === "none") return 0;
            return Math.round(nav.getBoundingClientRect().height);
        },
        tombolPintas(e) {
            if (e.key !== "Escape") return;
            // Esc mengembalikan panel dari keadaan diperbesar, lalu menutup laci.
            if (this.lcMaks) this.lcMaks = false;
            else if (this.laci && this.panelKanan) this.tutupKanan();
        },

        async muatMasterKeputusan() {
            try {
                const r = await axios.get("/api/v1/verifikasi-hasil-analisa/master-keputusan");
                this.masterKeputusan = r.data?.result || [];
            } catch (e) {
                // Tanpa master, modal tidak punya pilihan sama sekali dan
                // verifikator tidak dapat berbuat apa-apa. Kegagalannya harus
                // terlihat, bukan berakhir sebagai daftar kosong tanpa sebab.
                this.masterKeputusan = [];
            }
        },

        async muatProfil() {
            try {
                const r = await axios.get("/api/v1/verifikasi-hasil-analisa/profil");
                this.profil = r.data?.result || null;
            } catch { this.profil = null; }
        },

        async muat() {
            this.loading = true;
            this.galat   = "";
            this.terpilih = [];
            try {
                const r = await axios.get("/api/v1/verifikasi-hasil-analisa/daftar-kerja", {
                    params: {
                        search: this.search || undefined,
                        status: this.filterStatus || undefined,
                        page: this.pagination.page,
                        limit: this.limit,
                    },
                });
                const res = r.data?.result || {};
                this.daftar = res.data || [];
                this.ringkasan = res.ringkasan || this.ringkasan;
                this.pagination = res.pagination || this.pagination;

                // Kewenangan belum terdaftar bukan berarti antrean kosong.
                if (res.tanpa_kewenangan) {
                    this.galat = "Akun Anda belum memiliki kewenangan verifikasi. "
                        + "Hubungi administrator untuk mendaftarkan klasifikasi analisa.";
                }

                // Pertahankan pilihan bila item masih ada; jika tidak, kosongkan.
                if (this.terpilihItem) {
                    const tetap = this.daftar.find((x) => x.kunci === this.terpilihItem.kunci);
                    this.terpilihItem = tetap || null;
                    if (tetap && this.panelKanan) this.muatLifecycle(tetap.No_Sampel);
                    else if (!tetap) this.lcData = null;
                }
                // Di layar lebar, buka item pertama agar panel tengah tidak kosong.
                if (!this.terpilihItem && !this.tunggal && this.daftar.length) {
                    this.bukaItem(this.daftar[0]);
                }
            } catch (e) {
                this.daftar = [];
                this.ringkasan = { Total: 0, Menunggu: 0, Rekomendasi: 0, Bersyarat: 0, Tidak: 0 };

                // Sesi habis membuat middleware 'auth' membalas 401 (XHR) atau
                // mengalihkan ke halaman login (302, terbaca sebagai HTML).
                // Keduanya harus terlihat sebagai pesan, bukan layar kosong
                // yang menyerupai "memang tidak ada data".
                const kode   = e.response?.status;
                const bukanJson = typeof e.response?.data === "string";

                if (kode === 401 || kode === 419 || bukanJson) {
                    this.galat = "Sesi Anda telah berakhir. Muat ulang halaman dan login kembali.";
                    this.toast("error", this.galat);
                } else {
                    this.galat = "Gagal memuat daftar kerja. Periksa koneksi lalu coba muat ulang.";
                    this.toast("error", this.galat);
                }
            } finally { this.loading = false; }
        },

        bukaItem(it) {
            // Satu panel: detail menggantikan daftar, dimulai dari atas.
            if (this.tunggal && !this.terpilihItem) this.gulirDaftar = window.scrollY;
            this.terpilihItem = it;
            if (this.panelKanan) this.muatLifecycle(it.No_Sampel);
            if (this.tunggal) window.scrollTo({ top: 0 });
        },
        /** Mode satu panel: kembali ke daftar, di posisi gulir semula. */
        kembali() {
            this.terpilihItem = null;
            this.tutupKanan();
            this.$nextTick(() => window.scrollTo({ top: this.gulirDaftar }));
        },
        toggleKanan() {
            this.panelKanan = !this.panelKanan;
            if (!this.panelKanan) this.lcMaks = false;
            if (this.panelKanan && this.terpilihItem) this.muatLifecycle(this.terpilihItem.No_Sampel);
            this.simpanPilihanKanan();
        },
        tutupKanan() {
            this.panelKanan = false;
            this.lcMaks = false;
            this.simpanPilihanKanan();
        },
        /**
         * Lifecycle tampil secara bawaan di layar lebar; hanya pengguna yang
         * dapat menutupnya, dan keputusan itu diingat antar kunjungan.
         * Penutupan otomatis di mode laci / satu panel tidak ikut disimpan,
         * sebab itu keputusan tata letak, bukan keputusan pengguna.
         */
        pilihanKanan() {
            try { return localStorage.getItem("vh-lc-buka") !== "0"; } catch (e) { return true; }
        },
        simpanPilihanKanan() {
            if (this.laci) return;
            try { localStorage.setItem("vh-lc-buka", this.panelKanan ? "1" : "0"); } catch (e) { /* abaikan */ }
        },

        /* ------------------------------------------------------------------
         * Pemisah panel yang dapat ditarik
         * ------------------------------------------------------------------
         * Penarikan diikuti pada level dokumen, bukan pada garisnya sendiri,
         * supaya kursor yang bergerak cepat dan sempat keluar dari garis
         * tidak memutus penarikan di tengah jalan.
         */
        batasLebar(px) {
            // Panel tengah dijaga tetap terbaca: sisakan sekurang-kurangnya
            // 420 px untuk tabel hasil analisa.
            const maks = Math.max(340, this.lebar - this.lebarKiri() - 420);
            return Math.min(Math.max(px, 340), maks);
        },
        lebarKiri() {
            const el = this.$refs.kiri;
            return el ? el.offsetWidth : 330;
        },
        mulaiGeser() {
            if (this.lcMaks) return;         // saat diperbesar, lebar tidak berlaku
            this.geser = true;
            document.addEventListener("mousemove", this.saatGeser);
            document.addEventListener("mouseup", this.selesaiGeser);
            // Cegah teks ikut tersorot selama penarikan.
            document.body.style.userSelect = "none";
            document.body.style.cursor = "col-resize";
        },
        saatGeser(e) {
            if (!this.geser) return;
            // Lebar panel kanan = jarak kursor ke tepi kanan halaman ini.
            const kanan = this.$refs.akar.getBoundingClientRect().right;
            this.lcLebar = this.batasLebar(kanan - e.clientX);
        },
        selesaiGeser() {
            this.geser = false;
            document.removeEventListener("mousemove", this.saatGeser);
            document.removeEventListener("mouseup", this.selesaiGeser);
            document.body.style.userSelect = "";
            document.body.style.cursor = "";
            this.simpanLebar();
        },
        resetLebar() {
            this.lcLebar = this.batasLebar(this.lebarBawaan());
            this.simpanLebar();
        },
        /** Lebar awal panel kanan: lebih ramping di layar menengah. */
        lebarBawaan() {
            return this.lebar >= 1440 ? 380 : 340;
        },
        simpanLebar() {
            // Lebar pilihan verifikator diingat antar kunjungan. Kegagalan
            // penyimpanan (mis. mode privat) tidak boleh mengganggu layar.
            try { localStorage.setItem("vh-lc-lebar", String(this.lcLebar)); } catch (e) { /* abaikan */ }
        },
        muatLebar() {
            try {
                const n = parseInt(localStorage.getItem("vh-lc-lebar"), 10);
                this.lcLebar = this.batasLebar(isNaN(n) ? this.lebarBawaan() : n);
            } catch (e) { this.lcLebar = this.batasLebar(this.lebarBawaan()); }
        },
        async muatLifecycle(noSampel) {
            this.lcData = "loading";
            try {
                const r = await axios.get(`/api/v1/verifikasi-hasil-analisa/lifecycle/${noSampel}`);
                this.lcData = r.data?.result || null;
            } catch { this.lcData = null; }
        },

        gantiTab(k) { this.filterStatus = k; this.pagination.page = 1; this.muat(); },
        cari() { this.pagination.page = 1; this.muat(); },
        gantiLimit() { this.pagination.page = 1; this.muat(); },
        keHal(p) {
            if (p < 1 || p > this.pagination.total_page || p === this.pagination.page) return;
            this.pagination.page = p;
            this.muat();
        },

        sel(i) { return this.terpilih.some((x) => x.kunci === i.kunci); },
        pilih(i) {
            const k = this.terpilih.findIndex((x) => x.kunci === i.kunci);
            if (k >= 0) this.terpilih.splice(k, 1); else this.terpilih.push(i);
        },
        toggleSemua() { this.terpilih = this.semua ? [] : [...this.daftar]; },

        /**
         * Buka modal keputusan.
         *
         * Jenis keputusan diisi awal mengikuti rekomendasi sistem agar
         * verifikator cukup menyetujui bila memang sudah sesuai — tetapi
         * tetap bebas mengubahnya.
         */
        async buka(item = null) {
            this.modal = {
                show: true,
                keputusan: "",
                items: item ? [item] : [...this.terpilih],
                catatan: "",
                err: "",
            };

            // Master bisa gagal dimuat saat halaman dibuka (mis. sesi belum
            // siap). Dicoba sekali lagi di sini agar modal tidak terlanjur
            // tampil tanpa satu pun pilihan.
            if (!this.masterKeputusan.length) await this.muatMasterKeputusan();

            this.pilihSaran();
        },

        /** Muat ulang master dari dalam modal, lalu pilih saran sistem. */
        async ulangiMaster() {
            await this.muatMasterKeputusan();
            if (this.masterKeputusan.length) this.pilihSaran();
        },

        /** Pilih tingkat rekomendasi sesuai saran sistem, bila tersedia. */
        pilihSaran() {
            const saran = { ok: "REKOMENDASI", warn: "REKOM_BERSYARAT",
                            bad: "TIDAK_REKOM" }[this.reko.warna];
            const ada = this.masterKeputusan.find((k) => k.Kode_Keputusan === saran);
            if (ada) this.modal.keputusan = ada.Kode_Keputusan;
        },

        /** Peta warna master -> kelas Bootstrap. */
        warnaBs(w) {
            return { ok: "success", warn: "warning", bad: "danger" }[w] || "primary";
        },
        async kirim() {
            const m = this.modal;
            const k = this.kepAktif;

            if (!k) { m.err = "Pilih jenis keputusan terlebih dahulu."; return; }

            if (k.Flag_Wajib_Catatan === "Y") {
                const min = k.Panjang_Min_Catatan || 1;
                if (m.catatan.trim().length < min) {
                    m.err = `Keputusan "${k.Nama_Keputusan}" wajib disertai catatan minimal ${min} karakter.`;
                    return;
                }
            }

            this.simpan = true; m.err = "";
            try {
                const r = await axios.post("/api/v1/verifikasi-hasil-analisa/keputusan", {
                    keputusan: m.keputusan, catatan: m.catatan,
                    items: m.items.map((i) => ({ No_Sampel: i.No_Sampel, Kode_Aktivitas_Lab: i.Kode_Aktivitas_Lab })),
                });
                if (r.data?.success) {
                    this.modal.show = false; this.terpilih = [];
                    this.toast("success", r.data.message);
                    this.muat();
                } else { m.err = r.data?.message || "Gagal menyimpan keputusan."; }
            } catch (e) { m.err = e.response?.data?.message || "Gagal menyimpan keputusan."; }
            finally { this.simpan = false; }
        },

        /**
         * Nama mesin untuk ditampilkan. Bila master tidak menyimpan namanya,
         * nomor mesin tetap disebut agar sampel tetap dapat dibedakan.
         */
        labelMesin(it) {
            if (!it) return "-";
            if (it.Nama_Mesin) return it.Nama_Mesin;
            return it.Id_Mesin ? "Mesin " + it.Id_Mesin : "-";
        },

        /**
         * Ringkas daftar jejak (penginput atau validator): nama-namanya dan
         * rentang waktunya, mis. "24 Sep 2026 16:19–16:24".
         */
        ringkasJejak(daftar) {
            const ada = daftar.filter((j) => j && j.tanggal);
            if (!ada.length) return { nama: "", waktu: "" };

            const kunci = (j) => `${String(j.tanggal).slice(0, 10)} ${String(j.jam || "").slice(0, 5)}`;
            const urut  = [...ada].sort((p, q) => kunci(p).localeCompare(kunci(q)));
            const awal  = urut[0];
            const akhir = urut[urut.length - 1];
            const nama  = [...new Set(ada.map((j) => j.nama).filter(Boolean))].join(", ");

            let waktu;
            if (kunci(awal) === kunci(akhir)) {
                waktu = this.stamp(awal.tanggal, awal.jam);
            } else if (kunci(awal).slice(0, 10) === kunci(akhir).slice(0, 10)) {
                waktu = `${this.stamp(awal.tanggal, awal.jam)}–${String(akhir.jam).slice(0, 5)}`;
            } else {
                waktu = `${this.stamp(awal.tanggal, awal.jam)} – ${this.stamp(akhir.tanggal, akhir.jam)}`;
            }

            return { nama, waktu };
        },

        badgeStatus(s) {
            return { MENUNGGU: "bg-warning-subtle text-warning",
                     REKOMENDASI: "bg-success-subtle text-success",
                     REKOM_BERSYARAT: "bg-warning-subtle text-warning",
                     TIDAK_REKOM: "bg-danger-subtle text-danger" }[s] || "bg-light text-muted";
        },
        ikonStatus(s) {
            return { MENUNGGU: "ri-time-line",
                     REKOMENDASI: "ri-checkbox-circle-line",
                     REKOM_BERSYARAT: "ri-error-warning-line",
                     TIDAK_REKOM: "ri-close-circle-line" }[s] || "";
        },
        labelStatus(s) {
            return { MENUNGGU: "Menunggu",
                     REKOMENDASI: "Direkomendasikan",
                     REKOM_BERSYARAT: "Direkomendasikan Bersyarat",
                     TIDAK_REKOM: "Tidak Direkomendasikan" }[s] || s;
        },
        /**
         * Format tetap: 24 Sep 2026 09:49. Tanggal diurai dari bagian
         * YYYY-MM-DD-nya saja: server mengirim "2026-09-24 00:00:00.000"
         * maupun "2026-09-24", dan bentuk kedua dibaca peramban sebagai UTC.
         */
        stamp(t, j) {
            if (!t) return "–";
            const [y, m, d] = String(t).slice(0, 10).split("-").map(Number);
            if (!y || !m || !d) return "–";
            const bln = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
                         "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            const s = `${String(d).padStart(2, "0")} ${bln[m - 1]} ${y}`;
            return j ? `${s} ${String(j).substring(0, 5)}` : s;
        },
        toast(tipe, pesan) {
            const ok = tipe === "success";
            const el = document.createElement("div");
            el.setAttribute("style",
                `position:fixed;bottom:20px;right:20px;z-index:10090;display:flex;align-items:center;gap:8px;` +
                `max-width:min(92vw,420px);padding:11px 17px;border-radius:6px;color:#fff;font-size:.82rem;` +
                `font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.18);background:${ok ? "#0ab39c" : "#f06548"};`);
            const i = document.createElement("i");
            i.className = ok ? "ri-checkbox-circle-line" : "ri-error-warning-line";
            const t = document.createElement("span"); t.textContent = pesan || "";
            el.appendChild(i); el.appendChild(t);
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 4000);
        },
    },
};
</script>

<style scoped>
.vh { display: flex; flex-direction: column;
    height: calc(100vh - 70px); height: calc(100dvh - 70px);
    overflow: hidden; background: #f3f3f9; font-size: 13px; margin: -12px -12px 0;
    --lebar-kiri: 330px; }
.vh.uk-lg { --lebar-kiri: 290px; }
.vh.uk-md { --lebar-kiri: 250px; }

/* ---------- topbar ---------- */
.vh-top { display: flex; align-items: center; justify-content: space-between; gap: 10px 16px;
    flex-wrap: wrap; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e9ebec; flex-shrink: 0; }
.vh-top-l { min-width: 0; flex: 1 1 240px; }
.vh-top-t { font-size: 1.05rem; font-weight: 700; color: #343a40; margin: 0; letter-spacing: -.01em; }
.vh-top-s { font-size: .74rem; color: #878a99; margin: 2px 0 0; }
.vh-top-r { display: flex; align-items: center; justify-content: flex-end; gap: 8px 10px;
    flex-wrap: wrap; min-width: 0; max-width: 100%; }
.vh-top-a { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; min-width: 0; max-width: 100%; }

/* Filter status bergaya pill — digeser menyamping bila tidak muat. */
.vh-pills { display: flex; align-items: center; gap: 2px; background: #eef0f5;
    border-radius: 8px; padding: 3px; max-width: 100%; min-width: 0;
    overflow-x: auto; scrollbar-width: none; }
.vh-pills::-webkit-scrollbar { display: none; }
.vh-pill { background: none; border: none; border-radius: 6px; padding: 6px 13px;
    font-size: .76rem; font-weight: 600; color: #878a99; cursor: pointer; flex-shrink: 0;
    white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; transition: background .14s; }
.vh-pill:hover { color: #495057; }
.vh-pill.is-on { background: #fff; color: #343a40; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.vh-pill-c { font-size: .66rem; font-weight: 700; padding: 1px 7px; border-radius: 10px;
    background: #dfe3ea; color: #878a99; }
.vh-pill.is-on .c-n { background: #405189; color: #fff; }
.vh-pill.is-on .c-w { background: #f7b84b; color: #fff; }
.vh-pill.is-on .c-o { background: #0ab39c; color: #fff; }
.vh-pill.is-on .c-b { background: #f06548; color: #fff; }
.vh-scope { display: inline-flex; align-items: center; gap: 5px; font-size: .72rem; min-width: 0;
    max-width: 100%; font-weight: 600; color: #405189; background: #eef2f9; border-radius: 5px; padding: 6px 11px; }
.vh-scope-t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.vh-ico { background: #fff; border: 1px solid #e9ebec; border-radius: 5px; color: #878a99;
    padding: 6px 9px; cursor: pointer; flex-shrink: 0; }
.vh-ico:hover { background: #f3f6f9; color: #405189; }

/* ---------- bulk bar ---------- */
.vh-bulk { display: flex; align-items: center; gap: 8px 10px; background: #405189; color: #fff;
    padding: 8px 14px; font-size: .77rem; flex-wrap: wrap; flex-shrink: 0; }
.vh-bulk-x { background: none; border: none; color: #a9c4f5; font-size: .72rem;
    text-decoration: underline; cursor: pointer; }
.vh-bulk-sp { flex: 1; }
.vh-fade-enter-active, .vh-fade-leave-active { transition: opacity .15s; }
.vh-fade-enter-from, .vh-fade-leave-to { opacity: 0; }

/* ---------- body ---------- */
/* position: relative menjadi acuan laci dan panel yang diperbesar. */
.vh-body { display: flex; flex: 1; min-height: 0; overflow: hidden; position: relative; }

/* PANEL KIRI */
.vh-left { width: var(--lebar-kiri); min-width: 0; display: flex; flex-direction: column;
    background: #fff; border-right: 1px solid #e9ebec; overflow: hidden; flex-shrink: 0; }

.vh-lsearch { position: relative; display: flex; align-items: center; padding: 9px 11px; flex-shrink: 0; }
.vh-lsearch > i { position: absolute; left: 21px; color: #878a99; font-size: .85rem; }
.vh-lsearch input { width: 100%; border: 1px solid #e9ebec; border-radius: 5px;
    padding: 7px 28px 7px 28px; font-size: .76rem; }
.vh-lsearch input:focus { outline: none; border-color: #405189; }
.vh-lsearch-x { position: absolute; right: 18px; background: none; border: none; color: #878a99; cursor: pointer; }

.vh-lall { display: flex; align-items: center; justify-content: space-between;
    padding: 6px 13px; border-top: 1px solid #f3f6f9; border-bottom: 1px solid #f3f6f9;
    font-size: .72rem; color: #878a99; flex-shrink: 0; }
.vh-lall label { display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; }
.vh-lall-c { color: #405189; font-weight: 600; }

.vh-list { flex: 1; overflow-y: auto; }
.vh-li-wrap { position: relative; }
.vh-li-cb { position: absolute; left: 11px; top: 14px; z-index: 2; cursor: pointer; }
.vh-li { display: flex; align-items: center; gap: 7px; width: 100%; text-align: left;
    background: #fff; border: none; border-bottom: 1px solid #f3f6f9; padding: 10px 8px 10px 30px;
    cursor: pointer; position: relative; }
.vh-li:hover { background: #f8f9fc; }
.vh-li.is-active { background: #eef2f9; }
.vh-li-acc { position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: transparent; }
.vh-li.st-menunggu .vh-li-acc { background: #f7b84b; }
.vh-li.st-disetujui .vh-li-acc { background: #0ab39c; }
.vh-li.st-ditolak .vh-li-acc { background: #f06548; }
.vh-li-b { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.vh-li-r1 { display: flex; align-items: center; gap: 6px; }
.vh-li-r1 b { font-size: .8rem; color: #405189; font-family: ui-monospace, monospace; }
.vh-li-r2 { font-size: .7rem; color: #878a99; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vh-li-r3 { display: flex; flex-wrap: wrap; gap: 4px; }
.vh-li-r4 { margin-top: 2px; }
.vh-li-go { color: #ced4da; flex-shrink: 0; }
.vh-chip { display: inline-flex; align-items: center; gap: 3px; font-size: .62rem; font-weight: 600;
    padding: 1px 6px; border-radius: 3px; background: #f3f6f9; color: #878a99; }
.vh-chip.is-bad { background: #fdf2f0; color: #f06548; }
.vh-chip.is-warn { background: #fff8e8; color: #b8891b; }
/* Mesin ditonjolkan karena menjadi pembeda antar sampel ber-PO sama. */
.vh-chip.is-mesin { background: #eef1fb; color: #405189; font-weight: 600; }
.vh-akt { font-size: .6rem; font-weight: 700; color: #fff; background: #878a99; }
.akt-anl { background: #405189 !important; } .akt-lckv { background: #0ab39c !important; }
.akt-plt { background: #7c3aed !important; }

.vh-lpg { display: flex; align-items: center; justify-content: space-between; gap: 6px;
    padding: 7px 11px; border-top: 1px solid #e9ebec; font-size: .7rem; color: #878a99;
    flex-shrink: 0; background: #fff; }
.vh-lpg-b { display: flex; align-items: center; gap: 5px; }
.vh-lpg-b button { width: 24px; height: 24px; border: 1px solid #e9ebec; border-radius: 4px;
    background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.vh-lpg-b button:disabled { opacity: .4; cursor: not-allowed; }
.vh-lpg select { border: 1px solid #e9ebec; border-radius: 4px; padding: 2px 4px; font-size: .7rem; }

/* PANEL TENGAH */
.vh-center { flex: 1; min-width: 0; display: flex; flex-direction: column;
    overflow: hidden; background: #f3f3f9; }
.vh-center-empty { flex: 1; }

.vh-dh { display: flex; align-items: flex-start; gap: 10px 12px; padding: 12px 16px;
    background: #fff; border-bottom: 1px solid #e9ebec; flex-shrink: 0; }
.vh-back { background: #f3f6f9; border: none; border-radius: 6px; width: 34px; height: 34px;
    flex-shrink: 0; cursor: pointer; color: #495057; }
.vh-dh-id { flex: 1; min-width: 0; }
.vh-dh-r1 { display: flex; align-items: center; gap: 6px 7px; flex-wrap: wrap; margin-bottom: 8px; }
.vh-dh-smp { font-size: 1rem; font-weight: 700; color: #405189; font-family: ui-monospace, monospace; }
/* Kolom meta menyesuaikan lebar: tertata sebagai grid, tidak patah acak. */
.vh-dh-meta { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 8px 18px; margin: 0; }
.vh-dh-meta > div { min-width: 0; }
.vh-dh-meta dt { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em;
    color: #878a99; font-weight: 700; margin: 0; }
.vh-dh-meta dd { font-size: .76rem; color: #495057; font-weight: 600; margin: 1px 0 0;
    overflow-wrap: anywhere; }
/* Ringkasan penginput & validator di kepala detail. */
.vh-dh-meta dd small { display: block; font-size: .63rem; font-weight: 500;
    color: #878a99; font-family: ui-monospace, monospace; }
.vh-dh-meta dd.vh-dd-none { color: #adb5bd; font-style: italic; font-weight: 500; }
.vh-dh-meta dd small.vh-dd-warn { color: #b8891b; font-family: inherit; }
.vh-mesin { color: #405189; }
.vh-lcbtn { background: #fff; border: 1px solid #e9ebec; border-radius: 5px; color: #878a99;
    padding: 6px 12px; font-size: .74rem; font-weight: 600; cursor: pointer; gap: 5px;
    display: inline-flex; align-items: center; flex-shrink: 0; }
.vh-lcbtn:hover { border-color: #405189; color: #405189; }
.vh-lcbtn.is-on { background: #405189; border-color: #405189; color: #fff; }

.vh-dbody { flex: 1; overflow-y: auto; padding: 12px 16px; }
.vh-al { display: flex; gap: 8px; align-items: flex-start; font-size: .76rem;
    padding: 9px 13px; border-radius: 5px; margin-bottom: 9px; }
.vh-al--bad { background: #fdf2f0; color: #c0392b; }
.vh-al--warn { background: #fff8e8; color: #b8891b; }
.vh-al--info { background: #eef4fd; color: #3577c7; }

.vh-note { display: flex; gap: 8px; padding: 9px 13px; background: #fff; border: 1px solid #e9ebec;
    border-radius: 5px; font-size: .74rem; color: #495057; margin-top: 10px; }
.vh-note.is-bad { background: #fdf2f0; border-color: #f8d7d2; color: #c0392b; }

.vh-dfoot { display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 16px; background: #fff; border-top: 1px solid #e9ebec; flex-shrink: 0; flex-wrap: wrap; }
.vh-dfoot-i { font-size: .72rem; color: #878a99; }
.vh-dfoot-b { display: flex; gap: 7px; }

/* PEMISAH PANEL — garis yang dapat ditarik seperti batas kolom Excel */
.vh-split { width: 5px; flex-shrink: 0; cursor: col-resize; background: #e9ebec;
    position: relative; transition: background .15s; }
.vh-split:hover, .vh-split.is-drag { background: #405189; }
/* Area tangkap diperlebar ke kiri dan kanan agar garis mudah diraih
   tanpa membuat garisnya sendiri terlihat tebal. */
.vh-split::before { content: ""; position: absolute; inset: 0 -4px; }
.vh-split-grip { position: absolute; top: 50%; left: 50%; width: 3px; height: 30px;
    transform: translate(-50%, -50%); border-radius: 2px; background: #adb5bd; }
.vh-split:hover .vh-split-grip, .vh-split.is-drag .vh-split-grip { background: #fff; }

/* PANEL KANAN */
.vh-right { width: 380px; min-width: 0; display: flex; flex-direction: column;
    background: #fff; border-left: 1px solid #e9ebec; overflow: hidden; flex-shrink: 0; }
/* Panel diperbesar: menutupi area kerja, tanpa mengubah tata letak dasar. */
.vh-right.is-max { position: absolute; inset: 0; width: auto !important;
    z-index: 30; border-left: none; box-shadow: 0 0 0 100vmax rgba(0,0,0,.12); }
.vh-rh-act { display: flex; gap: 6px; }
.vh-rh { display: flex; align-items: center; justify-content: space-between;
    padding: 11px 14px; background: #405189; color: #fff; font-size: .82rem; font-weight: 600; flex-shrink: 0; }
.vh-rh-x { background: rgba(255,255,255,.18); border: none; color: #fff; border-radius: 4px;
    padding: 3px 7px; cursor: pointer; }
.vh-rb { flex: 1; overflow-y: auto; padding: 13px 14px 30px; }

/* Laci: panel kanan mengambang di atas detail (dua panel). */
.vh-right.is-laci { position: absolute; top: 0; right: 0; bottom: 0; z-index: 40;
    width: min(420px, 100%) !important; border-left: none; box-shadow: -12px 0 32px rgba(0,0,0,.16); }
.vh-right.is-laci.is-max { width: 100% !important; box-shadow: none; }
.vh-laci-bd { position: absolute; inset: 0; z-index: 35; background: rgba(33,37,41,.28); }
/* Lembar layar penuh (satu panel) — sudah dipindah ke <body>. Di atas
   navigasi bawah aplikasi (z-index 1055), di bawah menu sampingnya (1060). */
.vh-right.is-lembar { position: fixed; inset: 0; z-index: 1058; width: 100% !important;
    border-left: none; box-shadow: none; }
.vh-right.is-lembar .vh-rb { padding-bottom: calc(30px + env(safe-area-inset-bottom)); }
.vh-geser-enter-active.is-laci, .vh-geser-leave-active.is-laci,
.vh-geser-enter-active.is-lembar, .vh-geser-leave-active.is-lembar { transition: transform .22s ease; }
.vh-geser-enter-from.is-laci, .vh-geser-leave-to.is-laci,
.vh-geser-enter-from.is-lembar, .vh-geser-leave-to.is-lembar { transform: translateX(100%); }
.vh-pudar-enter-active, .vh-pudar-leave-active { transition: opacity .2s; }
.vh-pudar-enter-from, .vh-pudar-leave-to { opacity: 0; }

/* ---------- state ---------- */
.vh-state { display: flex; align-items: center; justify-content: center; gap: 9px;
    padding: 40px 16px; color: #878a99; font-size: .78rem; }
.vh-state--empty { flex-direction: column; gap: 3px; text-align: center; }
.vh-state--empty i { font-size: 2.1rem; opacity: .35; }
.vh-state--empty h6 { color: #495057; margin: 10px 0 3px; font-weight: 600; }
.vh-state--empty p { font-size: .76rem; margin: 0; max-width: 320px; }

/* ===================== SATU PANEL (< 768px) ===================== */
/* Halaman mengikuti guliran dokumen seperti aplikasi HP pada umumnya;
   daftar dan detail bergantian, lifecycle tampil layar penuh. */
.vh.is-tunggal { height: auto; min-height: calc(100vh - 70px); min-height: calc(100dvh - 70px);
    overflow: visible; }
.vh.is-tunggal .vh-top { padding: 10px 12px; }
.vh.is-tunggal .vh-top-s { display: none; }
.vh.is-tunggal .vh-top-r { flex: 1 1 100%; justify-content: flex-start; }
.vh.is-tunggal .vh-pills { flex: 1 1 100%; }
.vh.is-tunggal .vh-body { display: block; overflow: visible; }
.vh.is-tunggal .is-sembunyi { display: none !important; }
.vh.is-tunggal .vh-left { width: 100%; border-right: none; overflow: visible; }
.vh.is-tunggal .vh-list { overflow: visible; }
.vh.is-tunggal .vh-lpg { position: sticky; bottom: var(--bawah, 0px); z-index: 3; }
.vh.is-tunggal .vh-center { overflow: visible; min-height: calc(100dvh - 150px); }
.vh.is-tunggal .vh-dh { padding: 10px 12px; }
.vh.is-tunggal .vh-dh-meta { grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap: 8px 12px; }
.vh.is-tunggal .vh-lcbtn { padding: 0; width: 34px; height: 34px; justify-content: center; }
.vh.is-tunggal .vh-lcbtn-t, .vh.is-tunggal .vh-lcbtn-c { display: none; }
.vh.is-tunggal .vh-dbody { overflow: visible; padding: 12px; }
/* Tombol aksi selalu terjangkau di bawah layar, di atas navigasi bawah
   aplikasi. */
.vh.is-tunggal .vh-dfoot { position: sticky; bottom: var(--bawah, 0px); z-index: 5; padding: 10px 12px;
    padding-bottom: calc(10px + env(safe-area-inset-bottom)); box-shadow: 0 -6px 16px rgba(0,0,0,.06); }
.vh.is-tunggal .vh-dfoot-i { display: none; }
.vh.is-tunggal .vh-dfoot-b, .vh.is-tunggal .vh-dfoot-b .btn { flex: 1; }
.vh.is-tunggal .vh-dfoot-b .btn { padding-top: 9px; padding-bottom: 9px; }

/* Perangkat sentuh: sasaran ketuk lebih besar. */
@media (pointer: coarse) {
    .vh-pill { padding: 8px 14px; }
    .vh-ico { padding: 8px 11px; }
    .vh-lpg-b button { width: 32px; height: 32px; }
    .vh-lpg select { padding: 5px 6px; }
    .vh-rh-x { padding: 6px 10px; }
    .vh-li-cb { width: 1.15em; height: 1.15em; }
}

/* ---------- modal ---------- */
.vh-bd { position: fixed; inset: 0; background: rgba(33,37,41,.5); display: flex;
    align-items: center; justify-content: center; z-index: 10070; padding: 20px; }
.vh-md { background: #fff; border-radius: 7px; width: 100%; max-width: 520px; max-height: 88vh;
    display: flex; flex-direction: column; overflow: hidden; }
.vh-md-h { display: flex; align-items: center; gap: 10px; padding: 13px 17px; color: #fff; }
.vh-md-h b { display: block; font-size: .87rem; }
.vh-md-h em { font-style: normal; font-size: .68rem; opacity: .87; }
.vh-md-x { margin-left: auto; background: rgba(255,255,255,.2); border: none; color: #fff;
    border-radius: 4px; padding: 4px 8px; cursor: pointer; }
.vh-md-b { padding: 16px 17px; overflow-y: auto; }
.vh-md-f { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 17px;
    border-top: 1px solid #e9ebec; background: #f8f9fc; }
.vh-cfm { max-height: 165px; overflow-y: auto; border: 1px solid #e9ebec; border-radius: 5px; margin-bottom: 13px; }
.vh-cfm-r { display: flex; align-items: center; gap: 4px 8px; padding: 7px 11px;
    border-bottom: 1px solid #f3f6f9; font-size: .74rem; flex-wrap: wrap; }
.vh-cfm-r:last-child { border-bottom: none; }
.vh-cfm-r b { font-family: ui-monospace, monospace; color: #495057; }
.vh-cfm-r span:last-child { margin-left: auto; color: #878a99; font-size: .69rem; }
.vh-lb { font-size: .74rem; font-weight: 600; }
.vh-md--lg { max-width: 620px; }

/* rekomendasi sistem */
.vh-reko { display: flex; gap: 9px; align-items: flex-start; padding: 10px 13px;
    border-radius: 6px; font-size: .76rem; margin-bottom: 15px; }
.vh-reko i { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
.vh-reko em { display: block; font-style: normal; font-size: .71rem; opacity: .85; margin-top: 2px; }
.vh-reko.is-ok { background: #d9f3ee; color: #0c7a68; }
.vh-reko.is-warn { background: #fff3d9; color: #9a6f14; }
.vh-reko.is-bad { background: #fde4df; color: #b03826; }

/* pilihan tingkat keputusan */
.vh-keps { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.vh-kep { display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e9ebec;
    border-radius: 7px; padding: 11px 13px; cursor: pointer; margin: 0; transition: border-color .14s, background .14s; }
.vh-kep:hover { background: #f8f9fc; }
.vh-kep input { margin-top: 3px; cursor: pointer; flex-shrink: 0; }
.vh-kep.is-on.is-ok { border-color: #0ab39c; background: rgba(10,179,156,.05); }
.vh-kep.is-on.is-warn { border-color: #f7b84b; background: rgba(247,184,75,.07); }
.vh-kep.is-on.is-bad { border-color: #f06548; background: rgba(240,101,72,.05); }
.vh-kep-b { flex: 1; min-width: 0; }
.vh-kep-h { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 3px; }
.vh-kep-h b { font-size: .79rem; color: #495057; }
.vh-kep.is-ok .vh-kep-h i { color: #0ab39c; }
.vh-kep.is-warn .vh-kep-h i { color: #f7b84b; }
.vh-kep.is-bad .vh-kep-h i { color: #f06548; }
.vh-kep-tag { font-size: .58rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; padding: 2px 6px; border-radius: 3px; background: #fde4df; color: #b03826; }
.vh-kep-tag.is-opt { background: #eff2f7; color: #878a99; }
.vh-kep-k { display: block; font-size: .71rem; color: #878a99; line-height: 1.5; }
.vh-kep-f { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 5px; font-size: .68rem; }
.vh-kep-f span { display: inline-flex; align-items: center; gap: 4px; }

/* penghitung karakter catatan */
.vh-count { font-size: .68rem; color: #f06548; text-align: right; margin-top: 4px; font-weight: 600; }
.vh-count.is-ok { color: #0ab39c; }
.vh-hint { font-size: .71rem; color: #878a99; background: #f3f6f9; border-left: 3px solid #e9ebec;
    padding: 8px 11px; margin-top: 12px; border-radius: 0 4px 4px 0; }

/* Modal di HP: lembar dari bawah, selebar layar. */
@media (max-width: 767px) {
    .vh-bd { padding: 0; align-items: flex-end; }
    .vh-md, .vh-md--lg { max-width: 100%; max-height: 94vh; max-height: 94dvh;
        border-radius: 14px 14px 0 0; }
    .vh-md-b { padding: 14px; }
    .vh-md-f { padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); }
    .vh-md-f .btn { flex: 1; padding-top: 9px; padding-bottom: 9px; }
    .vh-cfm-r span:last-child { margin-left: 0; flex-basis: 100%; }
}
</style>
