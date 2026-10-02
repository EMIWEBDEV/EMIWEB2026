<template>
    <div ref="akar" class="fs" :class="kelasTata" :style="{ '--bawah': bawah + 'px' }">
        <!--
            FINALISASI TRIAL PRODUKSI — modul pembaharuan.

            Tiga panel seperti layar Verifikasi:
              KIRI   : antrean sampel — nomor sampel, nama & kode barang, nomor
                       formula, PO, dan lencana klasifikasi. Tanpa tab status:
                       semua yang ada di sini butuh finalisasi, dan sampel yang
                       sudah difinalisasi keluar dari antrean.
              TENGAH : widget ringkasan, rincian tiap klasifikasi (tabel hasil
                       yang sama dengan Verifikasi, galeri foto), lalu keputusan.
              KANAN  : status verifikasi tiap klasifikasi (ya / tidak) dan
                       Sample Lifecycle — bentuknya sama dengan layar Verifikasi.

            RESPONSIF — mengikuti lebar halaman ini sendiri (ResizeObserver),
            bukan lebar jendela, karena sidebar aplikasi ikut memakan ruang:
              <  768px : satu panel — daftar ATAU detail; panel kanan layar penuh
              < 1024px : dua panel  — panel kanan menjadi laci di atas detail
              >= 1024px: tiga panel — panel kanan di samping, dapat ditarik

            Komentar ini sengaja berada di DALAM elemen akar: komentar di luar
            akar membuat komponen ber-akar fragment pada mode dev.
        -->

        <!-- ================= TOPBAR ================= -->
        <div class="fs-top">
            <div class="fs-top-l">
                <h4 class="fs-top-t">Finalisasi Trial Produksi</h4>
                <p class="fs-top-s">
                    Tinjau rekomendasi tiap klasifikasi sebelum menetapkan keputusan akhir.
                </p>
            </div>

            <div class="fs-top-r">
                <div class="fs-top-a">
                    <span class="fs-sandbox"><i class="ri-flask-line me-1"></i>Sandbox</span>
                    <!-- Cakupan mesin: finalisasi hanya untuk sampel AUTOCLAVE. -->
                    <span v-if="cakupanMesin.length" class="fs-cakupan"
                          title="Finalisasi hanya untuk sampel dari mesin ini">
                        <i class="ri-settings-3-line me-1"></i>{{ cakupanMesin.join(' · ') }}
                    </span>
                    <button class="fs-ico" title="Muat ulang" @click="muat">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================= BODY ================= -->
        <div class="fs-body">
            <!-- ===== KIRI: antrean ===== -->
            <aside ref="kiri" class="fs-left" :class="{ 'is-sembunyi': tunggal && terpilih }">
                <!-- Mencari sambil mengetik (jeda singkat); Enter langsung
                     mencari; tombol × bawaan mengosongkan pencarian. -->
                <div class="fs-search" role="search">
                    <i class="ri-search-line"></i>
                    <input v-model="cari" type="search" class="form-control form-control-sm"
                           placeholder="Cari sampel, PO, barang, FRM…"
                           aria-label="Cari no. sampel, PO, kode atau nama barang, formula"
                           autocomplete="off" spellcheck="false" enterkeyhint="search"
                           @input="cariTunda" @keydown.enter.prevent="cariSekarang" />
                </div>
                <div v-if="!galat && (totalAntrean || !loading)" class="fs-lhead" aria-live="polite">
                    <!-- Selama mencari, angka lama tidak ditampilkan. -->
                    <template v-if="cariAktif && loading">Mencari&hellip;</template>
                    <template v-else-if="cariAktif">
                        <b>{{ pagination.total_data }}</b> dari {{ totalAntrean }} sampel cocok
                    </template>
                    <template v-else>
                        <b>{{ totalAntrean }}</b> sampel menunggu finalisasi
                    </template>
                </div>

                <div class="fs-list">
                    <div v-if="loading" class="fs-state">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span>Memuat…</span>
                    </div>

                    <div v-else-if="galat" class="fs-state fs-state--empty">
                        <i class="ri-error-warning-line text-danger"></i>
                        <p>{{ galat }}</p>
                        <button class="btn btn-sm btn-light mt-2" @click="muat">
                            <i class="ri-refresh-line me-1"></i>Coba lagi
                        </button>
                    </div>

                    <div v-else-if="!daftar.length" class="fs-state fs-state--empty">
                        <template v-if="cariAktif">
                            <i class="ri-search-line"></i>
                            <p>Tidak ada sampel yang cocok dengan &ldquo;{{ cariAktif }}&rdquo;.</p>
                            <button class="btn btn-sm btn-light mt-2" @click="hapusCari">
                                <i class="ri-close-line me-1"></i>Hapus pencarian
                            </button>
                        </template>
                        <template v-else>
                            <i class="ri-inbox-line"></i>
                            <p>Tidak ada sampel yang menunggu finalisasi.</p>
                        </template>
                    </div>

                    <!-- Kartu sampel: identitas barang lengkap (nama, kode,
                         formula, PO) dan lencana klasifikasi — cukup untuk
                         memilih tanpa membuka detail. -->
                    <button v-for="it in daftar" :key="it.No_Sampel" v-else
                            class="fs-li" :class="{ 'is-active': terpilih && terpilih.No_Sampel === it.No_Sampel }"
                            @click="buka(it)">
                        <span class="fs-li-acc" :class="'st-' + it.Saran.warna"></span>
                        <span class="fs-li-b">
                            <span class="fs-li-r1">
                                <b>{{ it.No_Sampel }}</b>
                                <i class="ri-checkbox-blank-circle-fill fs-dot"
                                   :class="'st-' + it.Saran.warna" :title="it.Saran.teks"></i>
                            </span>
                            <span class="fs-li-nm" :title="it.Nama_Barang">{{ it.Nama_Barang }}</span>
                            <span class="fs-li-id">
                                <span class="fs-li-kd" title="Kode barang">
                                    <i class="ri-barcode-line"></i>{{ it.Kode_Barang || '-' }}
                                </span>
                                <span class="fs-li-frm" :class="{ 'is-kosong': !it.Kode_Formula }"
                                      :title="it.Kode_Formula ? 'Nomor formula' : 'Nomor formula tidak ditemukan'">
                                    {{ it.Kode_Formula || 'FRM -' }}
                                </span>
                            </span>
                            <span class="fs-li-r3">
                                <span class="fs-chip" title="Nomor PO">
                                    <i class="ri-file-list-3-line"></i>{{ it.No_Po }}
                                </span>
                                <span v-for="k in it.klasifikasi" :key="k.Kode_Aktivitas_Lab"
                                      class="fs-kl" :class="'st-' + cls(k.Kode_Status)"
                                      :title="k.Nama_Aktivitas + ' — ' + labelStatus(k.Kode_Status)">
                                    {{ k.Kode_Aktivitas_Lab }}<em>{{ k.Jumlah_Analisa }}</em>
                                </span>
                                <!-- Mesin hanya perlu disebut bila cakupannya lebih dari satu. -->
                                <span v-if="cakupanMesin.length > 1" class="fs-chip is-mesin">
                                    <i class="ri-settings-3-line"></i>{{ it.Nama_Mesin || '-' }}
                                </span>
                            </span>
                        </span>
                        <i class="ri-arrow-right-s-line fs-li-go"></i>
                    </button>
                </div>

                <!-- Pagination -->
                <div v-if="!loading && pagination.total_data" class="fs-pg">
                    <span>{{ pagination.dari }}–{{ pagination.sampai }} / {{ pagination.total_data }}</span>
                    <div class="fs-pg-b">
                        <button :disabled="pagination.page <= 1" @click="keHal(pagination.page - 1)">
                            <i class="ri-arrow-left-s-line"></i>
                        </button>
                        <span>{{ pagination.page }} / {{ pagination.total_page }}</span>
                        <button :disabled="pagination.page >= pagination.total_page"
                                @click="keHal(pagination.page + 1)">
                            <i class="ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                    <select v-model.number="limit" class="form-select form-select-sm" @change="keHal(1)">
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
            </aside>

            <!-- ===== TENGAH ===== -->
            <section class="fs-center" :class="{ 'is-sembunyi': tunggal && !terpilih }">
                <div v-if="!terpilih" class="fs-state fs-state--empty fs-center-empty">
                    <i class="ri-file-list-3-line"></i>
                    <p>Pilih sampel untuk ditinjau</p>
                    <small>Ringkasan tiap klasifikasi akan ditampilkan di sini.</small>
                </div>

                <template v-else>
                    <div class="fs-dh">
                        <button v-if="tunggal" class="fs-back" title="Kembali ke daftar" @click="kembali">
                            <i class="ri-arrow-left-line"></i>
                        </button>
                        <div class="fs-dh-l">
                            <div class="fs-dh-t">
                                <b>{{ terpilih.No_Sampel }}</b>
                                <span class="badge bg-warning-subtle text-warning">Trial Produksi</span>
                                <span class="badge" :class="badgeStatus(terpilih.Kode_Status)">
                                    <i :class="ikonStatus(terpilih.Kode_Status)" class="me-1"></i>
                                    {{ labelStatus(terpilih.Kode_Status) }}
                                </span>
                                <span v-if="!terpilih.Siap" class="badge bg-light text-muted">
                                    {{ terpilih.Belum_Verifikasi }} klasifikasi belum diverifikasi
                                </span>
                            </div>
                            <dl class="fs-dh-meta">
                                <div><dt>No. PO</dt><dd>{{ terpilih.No_Po }}</dd></div>
                                <div><dt>Formula</dt><dd>{{ terpilih.Kode_Formula || '-' }}</dd></div>
                                <div><dt>Batch</dt><dd>{{ terpilih.No_Batch ?? '-' }}</dd></div>
                                <div>
                                    <dt>Mesin</dt>
                                    <dd class="fs-mesin">
                                        <i class="ri-settings-3-line me-1"></i>{{ terpilih.Nama_Mesin || '-' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Produk</dt>
                                    <dd>
                                        {{ terpilih.Nama_Barang }}
                                        <small class="fs-dh-kd">{{ terpilih.Kode_Barang }}</small>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <button class="fs-lcbtn" :class="{ 'is-on': panelKanan }"
                                title="Status verifikasi & Sample Lifecycle" @click="toggleKanan">
                            <i class="ri-route-line"></i><span class="fs-lcbtn-t">Lifecycle</span>
                            <i :class="panelKanan ? 'ri-contract-right-line' : 'ri-expand-left-line'" class="fs-lcbtn-c"></i>
                        </button>
                    </div>

                    <div class="fs-dbody">
                        <!-- Widget: jawaban cepat "isi sampel ini apa". -->
                        <div class="fs-w">
                            <div class="fs-w-i">
                                <span class="fs-w-n">{{ terpilih.klasifikasi.length }}</span>
                                <span class="fs-w-l">Klasifikasi</span>
                            </div>
                            <div class="fs-w-i">
                                <span class="fs-w-n">{{ terpilih.Jumlah_Analisa }}</span>
                                <span class="fs-w-l">Total Analisa</span>
                            </div>
                            <div class="fs-w-i">
                                <span class="fs-w-n is-ok">{{ hitungStatus('REKOMENDASI') }}</span>
                                <span class="fs-w-l">Direkomendasikan</span>
                            </div>
                            <div class="fs-w-i">
                                <span class="fs-w-n is-warn">{{ hitungStatus('REKOM_BERSYARAT') }}</span>
                                <span class="fs-w-l">Bersyarat</span>
                            </div>
                            <div class="fs-w-i">
                                <span class="fs-w-n is-bad">{{ hitungStatus('TIDAK_REKOM') }}</span>
                                <span class="fs-w-l">Tidak</span>
                            </div>
                        </div>

                        <div v-if="!terpilih.Siap" class="fs-al fs-al--warn">
                            <i class="ri-time-line"></i>
                            <span>
                                <b>{{ terpilih.Belum_Verifikasi }} klasifikasi belum diverifikasi.</b>
                                Sampel baru dapat difinalisasi setelah seluruh klasifikasinya
                                mendapat rekomendasi verifikator.
                            </span>
                        </div>

                        <label class="fs-lb">Rekomendasi tiap klasifikasi</label>
                        <!-- Daftar klasifikasi bergaya modul ERP: baris rapat
                             yang dapat dibuka untuk melihat rincian analisanya.
                             Kepalanya menata ulang diri saat sempit (container
                             query 'acc'). -->
                        <div class="fs-acc">
                            <div v-for="k in terpilih.klasifikasi" :key="k.Kode_Aktivitas_Lab"
                                 :data-kl="k.Kode_Aktivitas_Lab"
                                 class="fs-ac" :class="['st-' + cls(k.Kode_Status),
                                                        { 'is-open': terbuka.includes(k.Kode_Aktivitas_Lab) }]">
                                <button class="fs-ac-h" @click="toggleKlasifikasi(k.Kode_Aktivitas_Lab)">
                                    <i class="fs-ac-kar ri-arrow-right-s-line"></i>
                                    <i :class="ikonAkt(k.Kode_Aktivitas_Lab)" class="fs-ac-ik"></i>
                                    <span class="fs-ac-nm">
                                        {{ k.Nama_Aktivitas }}
                                        <em>{{ k.Kode_Aktivitas_Lab }}</em>
                                    </span>

                                    <span class="fs-ac-st">
                                        <b>{{ k.Jumlah_Analisa }}</b> analisa
                                        <em v-if="k.Jumlah_Tidak_Layak > 0" class="is-bad">
                                            · {{ k.Jumlah_Tidak_Layak }} tidak layak
                                        </em>
                                        <!-- Palatabilitas dinilai komparatif; ketiadaan
                                             kriteria di sana memang wajar. -->
                                        <em v-if="!k.Butuh_Pembanding && k.Jumlah_Tanpa_Master > 0" class="is-warn">
                                            · {{ k.Jumlah_Tanpa_Master }} belum dinilai
                                        </em>
                                        <em v-if="(fotoSampel[k.Kode_Aktivitas_Lab] || []).length" class="is-foto">
                                            · <i class="ri-camera-line"></i>{{ fotoSampel[k.Kode_Aktivitas_Lab].length }} foto
                                        </em>
                                    </span>

                                    <span class="badge fs-ac-bd" :class="badgeStatus(k.Kode_Status)">
                                        <i :class="ikonStatus(k.Kode_Status)" class="me-1"></i>
                                        {{ labelStatus(k.Kode_Status) }}
                                    </span>

                                    <span class="fs-ac-u">
                                        <template v-if="k.Nama_User">
                                            {{ k.Nama_User }}
                                            <em>{{ stamp(k.Tanggal_Keputusan, k.Jam_Keputusan) }}</em>
                                        </template>
                                        <em v-else class="is-kosong">Belum ada rekomendasi</em>
                                    </span>
                                </button>

                                <div v-if="terbuka.includes(k.Kode_Aktivitas_Lab)" class="fs-ac-b">
                                    <!-- Tabel hasil sama persis dengan layar Verifikasi:
                                         kriteria kelayakan beserta dasar penilaiannya,
                                         resampling, penginput, dan validator. -->
                                    <TabelHasilAnalisa :analisa="k.analisa"
                                                       :butuh-pembanding="k.Butuh_Pembanding"
                                                       :pembanding="k.Pembanding"
                                                       :matriks="k.Matriks_Pembanding"
                                                       tanpa-bingkai
                                                       @foto="bukaFotoAnalisa(k, $event)" />

                                    <!-- Foto analisa tampil sebagai galeri kartu di
                                         bawah tabel — bukan sebagai kolom — supaya
                                         gambarnya langsung terlihat tanpa dibuka satu
                                         per satu. -->
                                    <div v-if="(fotoSampel[k.Kode_Aktivitas_Lab] || []).length" class="fs-gal">
                                        <div class="fs-gal-h">
                                            <i class="ri-image-2-line"></i>
                                            <b>Foto analisa</b>
                                            <em>{{ fotoSampel[k.Kode_Aktivitas_Lab].length }} foto
                                                &middot; ketuk untuk memperbesar</em>
                                        </div>
                                        <div class="fs-gal-g">
                                            <button v-for="(f, i) in fotoSampel[k.Kode_Aktivitas_Lab]" :key="f.key"
                                                    type="button" class="fs-gal-c"
                                                    @click="bukaGaleri(fotoSampel[k.Kode_Aktivitas_Lab], i)">
                                                <span class="fs-gal-img">
                                                    <img v-if="fotoUrl[f.key]" :src="fotoUrl[f.key]"
                                                         :alt="'Foto ' + f.analisa" />
                                                    <span v-else-if="fotoGagal[f.key]" class="fs-gal-x">
                                                        <i class="ri-image-line"></i>Gagal memuat
                                                    </span>
                                                    <span v-else class="spinner-border spinner-border-sm text-primary"></span>
                                                    <span class="fs-gal-zoom"><i class="ri-zoom-in-line"></i></span>
                                                </span>
                                                <span class="fs-gal-t">
                                                    <span class="fs-gal-t1">
                                                        <b>{{ f.analisa }}</b>
                                                        <span class="badge" :class="f.layak.cls">{{ f.layak.label }}</span>
                                                    </span>
                                                    <span class="fs-gal-t2">
                                                        <code>{{ f.sampel }}</code> &middot; {{ f.hasil }}
                                                    </span>
                                                    <span v-if="f.keterangan" class="fs-gal-t3">
                                                        &ldquo;{{ f.keterangan }}&rdquo;
                                                    </span>
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Catatan leader atas klasifikasi ini. -->
                                    <div v-if="k.Catatan" class="fs-ac-c">
                                        <i class="ri-chat-quote-line"></i>
                                        <span><b>{{ k.Nama_User }}:</b> {{ k.Catatan }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="fs-dfoot">
                        <span class="fs-dfoot-i">
                            <i class="ri-information-line me-1"></i>
                            Keputusan finalisasi tersimpan sebagai jejak audit
                        </span>
                        <button class="btn btn-sm btn-primary" @click="bukaModal(terpilih)">
                            <i class="ri-shield-check-line me-1"></i>Beri Rekomendasi
                        </button>
                    </div>
                </template>
            </section>

            <!-- ===== PEMISAH PANEL (dapat ditarik) — hanya tiga panel ===== -->
            <div v-if="panelKanan && terpilih && !laci"
                 class="fs-split" :class="{ 'is-drag': geser }"
                 title="Tarik untuk mengubah lebar — klik dua kali untuk lebar semula"
                 @mousedown.prevent="mulaiGeser" @dblclick="resetLebar">
                <span class="fs-split-grip"></span>
            </div>

            <!-- Latar laci: ketukan di luar laci menutupnya. -->
            <transition name="fs-pudar">
                <div v-if="panelKanan && terpilih && laci && !tunggal" class="fs-laci-bd"
                     @click="tutupKanan"></div>
            </transition>

            <!-- ===== KANAN: Status verifikasi + Sample Lifecycle ===== -->
            <!-- Mode satu panel: lembar layar penuh, dipindah ke <body> —
                 .page-content milik layout aplikasi memakai transform,
                 sehingga elemen fixed di dalamnya tidak menutupi layar. -->
            <Teleport to="body" :disabled="!tunggal">
            <transition name="fs-geser">
                <aside v-if="panelKanan && terpilih" class="fs-right"
                       :class="{ 'is-max': lcMaks, 'is-laci': laci && !tunggal, 'is-lembar': tunggal }"
                       :style="gayaKanan">
                    <div class="fs-rh">
                        <span><i class="ri-route-line me-2"></i>Sample Lifecycle</span>
                        <span class="fs-rh-act">
                            <button v-if="!tunggal" class="fs-rh-x" :title="lcMaks ? 'Kecilkan' : 'Perbesar'"
                                    @click="lcMaks = !lcMaks">
                                <i :class="lcMaks ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'"></i>
                            </button>
                            <button class="fs-rh-x" title="Tutup panel" @click="tutupKanan">
                                <i class="ri-close-line"></i>
                            </button>
                        </span>
                    </div>

                    <div class="fs-rb">
                        <!-- Status verifikasi tiap klasifikasi: sudah atau
                             belum, langsung terjawab ya / tidak. -->
                        <div class="fs-sv">
                            <div class="fs-sv-h">
                                <b><i class="ri-shield-check-line"></i>Status verifikasi</b>
                                <span class="fs-sv-n">
                                    {{ jumlahTerverifikasi }} / {{ terpilih.klasifikasi.length }} klasifikasi
                                </span>
                            </div>
                            <div class="fs-sv-siap" :class="terpilih.Siap ? 'is-ya' : 'is-belum'">
                                <i :class="terpilih.Siap ? 'ri-checkbox-circle-fill' : 'ri-time-line'"></i>
                                <span v-if="terpilih.Siap">
                                    <b>Siap difinalisasi.</b> Saran sistem: {{ terpilih.Saran.teks }}.
                                </span>
                                <span v-else>
                                    <b>Belum siap difinalisasi.</b>
                                    {{ terpilih.Belum_Verifikasi }} klasifikasi belum diverifikasi.
                                </span>
                            </div>

                            <table class="fs-sv-t">
                                <thead>
                                    <tr><th>Klasifikasi</th><th>Diverifikasi</th><th>Rekomendasi</th></tr>
                                </thead>
                                <tbody>
                                    <template v-for="k in terpilih.klasifikasi" :key="k.Kode_Aktivitas_Lab">
                                        <tr class="fs-sv-r" title="Tampilkan rincian klasifikasi ini"
                                            @click="keKlasifikasi(k.Kode_Aktivitas_Lab)">
                                            <td>
                                                <span class="fs-sv-k">
                                                    <i :class="ikonAkt(k.Kode_Aktivitas_Lab)"></i>
                                                    <b>{{ k.Nama_Aktivitas }}</b>
                                                </span>
                                                <em>{{ k.Kode_Aktivitas_Lab }} &middot; {{ k.Jumlah_Analisa }} analisa</em>
                                            </td>
                                            <td>
                                                <!-- "Menunggu", bukan "Tidak": belum diverifikasi
                                                     bukan berarti ditolak. -->
                                                <span class="fs-yt" :class="sudahVerif(k) ? 'is-ya' : 'is-menunggu'">
                                                    <i :class="sudahVerif(k) ? 'ri-check-line' : 'ri-time-line'"></i>
                                                    {{ sudahVerif(k) ? 'Sudah' : 'Menunggu' }}
                                                </span>
                                            </td>
                                            <td>
                                                <span v-if="sudahVerif(k)" class="badge" :class="badgeStatus(k.Kode_Status)">
                                                    {{ labelStatus(k.Kode_Status) }}
                                                </span>
                                                <span v-else class="fs-sv-kosong">&mdash;</span>
                                            </td>
                                        </tr>
                                        <tr class="fs-sv-d">
                                            <td colspan="3">
                                                <template v-if="sudahVerif(k)">
                                                    <span><i class="ri-user-3-line"></i>{{ k.Nama_User || '-' }}</span>
                                                    <span><i class="ri-calendar-line"></i>{{ stamp(k.Tanggal_Keputusan, k.Jam_Keputusan) }}</span>
                                                    <span v-if="k.Revisi_Ke > 0" class="text-primary">rev.{{ k.Revisi_Ke }}</span>
                                                    <span v-if="k.Catatan" class="fs-sv-c">&ldquo;{{ k.Catatan }}&rdquo;</span>
                                                </template>
                                                <!-- Belum diverifikasi: sebut akun yang bertugas,
                                                     supaya jelas rekomendasinya ditunggu dari siapa. -->
                                                <template v-else-if="(k.Petugas_Verifikasi || []).length">
                                                    <span class="fs-sv-tunggu">Menunggu rekomendasi dari</span>
                                                    <span v-for="p in k.Petugas_Verifikasi" :key="p.id" class="fs-sv-p">
                                                        <i class="ri-user-3-line"></i>{{ p.nama }}
                                                        <em v-if="p.sebagian">({{ p.jumlah }} dari {{ k.Jumlah_Analisa }} analisa)</em>
                                                    </span>
                                                </template>
                                                <span v-else class="fs-sv-tunggu is-kosong">
                                                    Belum ada verifikator yang ditugaskan untuk klasifikasi ini
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="fs-rb-sub"><i class="ri-history-line"></i>Riwayat sampel</div>
                        <LifecycleSampel :data="lcData" />
                    </div>
                </aside>
            </transition>
            </Teleport>
        </div>

        <!-- ================= LIGHTBOX FOTO ================= -->
        <!-- Foto diperbesar beserta konteks analisanya. Dapat digeser antar
             foto dengan tombol panah, tombol ← → pada papan ketik, atau
             usapan pada layar sentuh. Dipindah ke <body> agar menutupi layar. -->
        <Teleport to="body">
        <div v-if="galeri.show && fotoAktif" class="fs-zm" @click.self="tutupGaleri">
            <div class="fs-zm-box">
                <div class="fs-zm-top">
                    <span class="fs-zm-ttl">
                        <i class="ri-image-2-line"></i>
                        <b>{{ fotoAktif.analisa }}</b>
                        <code>{{ fotoAktif.sampel }}</code>
                    </span>
                    <span class="fs-zm-act">
                        <span v-if="galeri.daftar.length > 1" class="fs-zm-n">
                            {{ galeri.i + 1 }} / {{ galeri.daftar.length }}
                        </span>
                        <button v-if="fotoUrl[fotoAktif.key]" type="button" class="fs-zm-b"
                                title="Buka ukuran penuh di tab baru" @click="bukaTab(fotoUrl[fotoAktif.key])">
                            <i class="ri-external-link-line"></i>
                        </button>
                        <button type="button" class="fs-zm-b" title="Tutup (Esc)" @click="tutupGaleri">
                            <i class="ri-close-line"></i>
                        </button>
                    </span>
                </div>

                <div class="fs-zm-stage" @touchstart.passive="mulaiUsap" @touchend="akhiriUsap">
                    <button v-if="galeri.daftar.length > 1" type="button" class="fs-zm-nav is-prev"
                            title="Sebelumnya" @click="geserGaleri(-1)">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>
                    <img v-if="fotoUrl[fotoAktif.key]" :src="fotoUrl[fotoAktif.key]"
                         :alt="'Foto ' + fotoAktif.analisa" />
                    <div v-else-if="fotoGagal[fotoAktif.key]" class="fs-zm-x">
                        <i class="ri-image-line"></i>
                        <span>Foto gagal dimuat dari penyimpanan.</span>
                    </div>
                    <div v-else class="spinner-border text-light"></div>
                    <button v-if="galeri.daftar.length > 1" type="button" class="fs-zm-nav is-next"
                            title="Berikutnya" @click="geserGaleri(1)">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>

                <div class="fs-zm-cap">
                    <div class="fs-zm-ci">
                        <small>Hasil</small>
                        <span>
                            <b>{{ fotoAktif.hasil }}</b>
                            <span class="badge ms-1" :class="fotoAktif.layak.cls">{{ fotoAktif.layak.label }}</span>
                        </span>
                    </div>
                    <div v-if="fotoAktif.keterangan" class="fs-zm-ci">
                        <small>Keterangan foto</small>
                        <span>{{ fotoAktif.keterangan }}</span>
                    </div>
                    <div class="fs-zm-ci">
                        <small>Diinput</small>
                        <span>
                            {{ fotoAktif.input ? fotoAktif.input.nama : '-' }}
                            <em v-if="fotoAktif.input">{{ stamp(fotoAktif.input.tanggal, fotoAktif.input.jam) }}</em>
                        </span>
                    </div>
                    <div class="fs-zm-ci">
                        <small>Divalidasi</small>
                        <span v-if="fotoAktif.validasi">
                            {{ fotoAktif.validasi.nama }}
                            <em>{{ stamp(fotoAktif.validasi.tanggal, fotoAktif.validasi.jam) }}</em>
                        </span>
                        <span v-else class="fs-zm-none">tidak tercatat</span>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= MODAL KEPUTUSAN ================= -->
        <Teleport to="body">
        <div v-if="modal.show" class="fs-bd" @click.self="modal.show = false">
            <div class="fs-md">
                <div class="fs-md-h" :class="'bg-' + warnaBs(kepAktif && kepAktif.Warna_Badge)">
                    <i :class="(kepAktif && kepAktif.Ikon) || 'ri-shield-check-line'"></i>
                    <div>
                        <b>Keputusan Finalisasi</b>
                        <em>{{ modal.items.length }} sampel akan diproses</em>
                    </div>
                    <button class="fs-md-x" @click="modal.show = false"><i class="ri-close-line"></i></button>
                </div>

                <div class="fs-md-b">
                    <div class="fs-cfm">
                        <div v-for="i in modal.items" :key="i.No_Sampel" class="fs-cfm-r">
                            <b>{{ i.No_Sampel }}</b>
                            <span>{{ i.Nama_Mesin }} · {{ i.Jumlah_Analisa }} analisa · {{ i.Nama_Barang }}</span>
                        </div>
                    </div>

                    <!-- Rekomendasi tiap leader, apa adanya. -->
                    <label class="fs-lb">Rekomendasi verifikator</label>
                    <div class="fs-vr">
                        <div v-for="k in (modal.items[0] ? modal.items[0].klasifikasi : [])"
                             :key="k.Kode_Aktivitas_Lab" class="fs-vr-i">
                            <i :class="ikonAkt(k.Kode_Aktivitas_Lab)"></i>
                            <b>{{ k.Nama_Aktivitas }}</b>
                            <span class="badge" :class="badgeStatus(k.Kode_Status)">
                                {{ labelStatus(k.Kode_Status) }}
                            </span>
                            <em v-if="k.Nama_User">{{ k.Nama_User }}</em>
                        </div>
                    </div>

                    <div class="fs-reko" :class="'is-' + (modal.items[0] ? modal.items[0].Saran.warna : 'n')">
                        <i class="ri-lightbulb-line"></i>
                        <span>
                            <b>Saran sistem:</b>
                            {{ modal.items[0] ? modal.items[0].Saran.teks : '-' }}
                            <em>Tingkat terendah dari seluruh klasifikasi yang menentukan.</em>
                        </span>
                    </div>

                    <div v-if="!masterKeputusan.length" class="fs-al fs-al--bad">
                        <i class="ri-error-warning-line"></i>
                        <span>
                            <b>Pilihan rekomendasi gagal dimuat.</b>
                            <button class="btn btn-sm btn-light ms-2" @click="ulangiMaster">
                                <i class="ri-refresh-line me-1"></i>Coba lagi
                            </button>
                        </span>
                    </div>

                    <template v-else>
                        <label class="fs-lb">Tingkat rekomendasi</label>
                        <div class="fs-keps">
                            <label v-for="k in masterKeputusan" :key="k.Kode_Keputusan"
                                   class="fs-kep" :class="['is-' + k.Warna_Badge,
                                                           { 'is-on': modal.keputusan === k.Kode_Keputusan }]">
                                <input type="radio" :value="k.Kode_Keputusan" v-model="modal.keputusan" />
                                <span class="fs-kep-b">
                                    <span class="fs-kep-h">
                                        <i :class="k.Ikon"></i>
                                        <b>{{ k.Nama_Keputusan }}</b>
                                        <span v-if="k.Flag_Wajib_Catatan === 'Y'" class="fs-kep-tag">catatan wajib</span>
                                        <span v-else class="fs-kep-tag is-opt">catatan opsional</span>
                                    </span>
                                    <span class="fs-kep-k">{{ k.Keterangan }}</span>
                                </span>
                            </label>
                        </div>

                        <label class="fs-lb">
                            Justifikasi / catatan
                            <span v-if="wajibCatatan" class="text-danger">
                                wajib &mdash; minimal {{ kepAktif.Panjang_Min_Catatan || 1 }} karakter
                            </span>
                            <span v-else class="text-muted">opsional</span>
                        </label>
                        <textarea v-model="modal.catatan" rows="3" class="form-control"
                                  :placeholder="placeholderCatatan"></textarea>
                        <div v-if="wajibCatatan" class="fs-count"
                             :class="{ 'is-ok': modal.catatan.trim().length >= (kepAktif.Panjang_Min_Catatan || 1) }">
                            {{ modal.catatan.trim().length }} / {{ kepAktif.Panjang_Min_Catatan || 1 }} karakter
                        </div>
                    </template>

                    <div v-if="modal.err" class="fs-al fs-al--bad mt-2">
                        <i class="ri-error-warning-line"></i><span>{{ modal.err }}</span>
                    </div>
                </div>

                <div class="fs-md-f">
                    <button class="btn btn-sm btn-light" @click="modal.show = false">Batal</button>
                    <button class="btn btn-sm" :class="'btn-' + warnaBs(kepAktif && kepAktif.Warna_Badge)"
                            :disabled="simpan || !modal.keputusan" @click="kirim">
                        <span v-if="simpan" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-check-line me-1"></i>
                        Simpan Keputusan
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
    name: "FinalisasiSandbox",
    components: { TabelHasilAnalisa, LifecycleSampel },
    data() {
        return {
            daftar: [],
            terpilih: null,
            // Jumlah seluruh antrean (sebelum pencarian), dari server.
            totalAntrean: 0,
            pagination: { page: 1, limit: 10, total_data: 0, total_page: 1, dari: 0, sampai: 0 },
            loading: false,
            galat: "",
            // Mesin yang dilayani finalisasi, dari server (AUTOCLAVE).
            cakupanMesin: [],
            // Isi kolom pencarian, dan kata kunci yang sudah diterapkan ke
            // daftar (bisa tertinggal sesaat selama jeda mengetik).
            cari: "",
            cariAktif: "",
            tundaCari: null,
            // Nomor permintaan daftar terakhir: jawaban yang datang
            // terlambat (pencarian lama) diabaikan.
            urutMuat: 0,
            limit: 10,

            panelKanan: true,
            lcData: null,

            // Lebar halaman ini (px), diukur ResizeObserver. Seluruh mode
            // tata letak diturunkan dari sini — bukan dari lebar jendela,
            // karena sidebar aplikasi ikut memakan ruang.
            lebar: 0,
            // Posisi gulir daftar sebelum detail dibuka pada mode satu panel.
            gulirDaftar: 0,
            // Tinggi navigasi bawah aplikasi (hanya ada di HP). Elemen yang
            // menempel di bawah layar digeser setinggi ini agar tidak tertutup.
            bawah: 0,
            // Lebar panel kanan dalam piksel, supaya hasil tarikan tetap
            // sama ketika halaman diubah ukurannya.
            lcLebar: 380,
            lcMaks: false,
            geser: false,

            // Klasifikasi yang sedang dibuka rinciannya.
            terbuka: [],
            // Galeri foto yang sedang dibuka di lightbox.
            galeri: { show: false, daftar: [], i: 0 },
            // Titik awal usapan pada lightbox (layar sentuh).
            usapX: null,
            // Gambar per kunci berkas: URL blob, penanda gagal, dan penanda
            // sedang diambil agar satu gambar tidak diminta dua kali.
            fotoUrl: {},
            fotoGagal: {},
            fotoProses: {},
            masterKeputusan: [],
            simpan: false,
            modal: { show: false, keputusan: "", items: [], catatan: "", err: "" },

        };
    },
    computed: {
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

        kepAktif() {
            return this.masterKeputusan.find((k) => k.Kode_Keputusan === this.modal.keputusan) || null;
        },
        wajibCatatan() {
            return !!(this.kepAktif && this.kepAktif.Flag_Wajib_Catatan === "Y");
        },
        placeholderCatatan() {
            if (!this.kepAktif) return "Pilih tingkat rekomendasi terlebih dahulu";
            if (this.kepAktif.Kode_Keputusan === "TIDAK_REKOM")
                return "Jelaskan mengapa sampel tidak dapat diteruskan dan tindak lanjutnya";
            if (this.kepAktif.Kode_Keputusan === "REKOM_BERSYARAT")
                return "Jelaskan hal yang perlu diperhatikan dan dasar pertimbangannya";
            return "Catatan tambahan bila ada (opsional)";
        },
        gayaKanan() {
            if (this.lcMaks || this.laci) return {};
            return { width: this.lcLebar + "px" };
        },

        /** Berapa klasifikasi sampel terpilih yang sudah diverifikasi. */
        jumlahTerverifikasi() {
            return (this.terpilih?.klasifikasi || []).filter((k) => this.sudahVerif(k)).length;
        },

        /** Lembar layar penuh, lightbox, atau modal sedang menutupi halaman. */
        kunciGulir() {
            return this.modal.show || this.galeri.show
                || (this.tunggal && this.panelKanan && !!this.terpilih);
        },

        /**
         * Foto sampel terpilih, dikelompokkan per klasifikasi.
         *
         * Setiap foto membawa konteks analisanya — hasil, kelayakan,
         * penginput, validator — supaya kartu galeri dan lightbox tidak
         * perlu merujuk balik ke tabel.
         */
        fotoSampel() {
            const peta = {};
            (this.terpilih?.klasifikasi || []).forEach((k) => {
                const sudah = new Set();
                peta[k.Kode_Aktivitas_Lab] = [];
                (k.analisa || []).forEach((a) => {
                    (a.Foto || []).forEach((f) => {
                        // Palatabilitas menyimpan beberapa baris per faktur;
                        // fotonya cukup tampil sekali.
                        if (sudah.has(f.key)) return;
                        sudah.add(f.key);
                        peta[k.Kode_Aktivitas_Lab].push({
                            key: f.key,
                            keterangan: f.keterangan,
                            idAnalisa: a.Id_Jenis_Analisa,
                            analisa: a.Nama_Jenis_Analisa,
                            sampel: a.No_Sampel_Uji,
                            hasil: a.Nilai_Hasil_String || this.nilai(a.Hasil),
                            layak: this.layakAnalisa(a),
                            input: a.Input,
                            validasi: a.Validasi,
                        });
                    });
                });
            });
            return peta;
        },
        fotoAktif() {
            return this.galeri.daftar[this.galeri.i] || null;
        },
    },
    watch: {
        // Halaman di belakang lembar, lightbox, atau modal tidak ikut tergulir.
        kunciGulir(v) { document.body.style.overflow = v ? "hidden" : ""; },
    },
    async mounted() {
        // Lebar diukur lebih dulu, sebelum data dimuat, supaya keputusan
        // "buka sampel pertama" sudah tahu apakah layar ini satu panel.
        // Panel kanan tampil secara bawaan; pengguna yang menentukan bila
        // ingin menutupnya, dan pilihan itu diingat.
        this.panelKanan = this.pilihanKanan();
        this.ukurUlang(this.$refs.akar.getBoundingClientRect().width);
        this.pengamat = new ResizeObserver((e) => this.ukurUlang(e[0].contentRect.width));
        this.pengamat.observe(this.$refs.akar);
        this.muatLebar();
        window.addEventListener("keydown", this.tombolPintas);

        // Berurutan, bukan serentak: sesi berbasis file mengunci berkasnya
        // selama satu permintaan berjalan.
        await this.muatMasterKeputusan();
        await this.muat();
    },
    beforeUnmount() {
        if (this.pengamat) this.pengamat.disconnect();
        clearTimeout(this.tundaCari);
        document.body.style.overflow = "";
        window.removeEventListener("keydown", this.tombolPintas);
        if (this.geser) this.selesaiGeser();
        this.bersihkanFoto();
    },
    methods: {
        async muat() {
            const urut = ++this.urutMuat;
            this.loading = true;
            this.galat = "";
            try {
                const r = await axios.get(
                    "/api/v1/sandbox/finalisasi-trial-produksi/daftar",
                    {
                        params: {
                            search: this.cariAktif || undefined,
                            page: this.pagination.page,
                            limit: this.limit,
                        },
                    }
                );
                if (urut !== this.urutMuat) return;
                const res = r.data?.result || {};
                this.daftar = res.data || [];
                this.totalAntrean = res.total_antrean ?? this.totalAntrean;
                this.pagination = res.pagination || this.pagination;
                this.cakupanMesin = res.cakupan_mesin || this.cakupanMesin;

                if (this.terpilih) {
                    this.terpilih = this.daftar.find(
                        (d) => d.No_Sampel === this.terpilih.No_Sampel
                    ) || null;
                    if (this.terpilih) this.muatFotoSampel(this.terpilih);
                }
                // Di layar lebar, buka sampel pertama agar panel tengah tidak kosong.
                if (!this.terpilih && this.daftar.length && !this.tunggal) {
                    this.buka(this.daftar[0]);
                }
            } catch (e) {
                if (urut !== this.urutMuat) return;
                this.daftar = [];
                const kode = e.response?.status;
                this.galat = (kode === 401 || kode === 419 || typeof e.response?.data === "string")
                    ? "Sesi Anda telah berakhir. Muat ulang halaman dan login kembali."
                    : "Gagal memuat daftar finalisasi. Periksa koneksi lalu coba lagi.";
            } finally {
                if (urut === this.urutMuat) this.loading = false;
            }
        },

        async muatMasterKeputusan() {
            try {
                const r = await axios.get(
                    "/api/v1/sandbox/finalisasi-trial-produksi/master-keputusan");
                this.masterKeputusan = r.data?.result || [];
            } catch {
                this.masterKeputusan = [];
            }
        },

        /** Mencari setelah jeda singkat mengetik; dikosongkan = langsung. */
        cariTunda() {
            clearTimeout(this.tundaCari);
            if (!this.cari.trim()) {
                this.cariSekarang();
                return;
            }
            this.tundaCari = setTimeout(this.cariSekarang, 350);
        },
        cariSekarang() {
            clearTimeout(this.tundaCari);
            const kunci = this.cari.trim();
            if (kunci === this.cariAktif) return;
            this.cariAktif = kunci;
            this.pagination.page = 1;
            this.muat();
        },
        hapusCari() {
            this.cari = "";
            this.cariSekarang();
        },
        keHal(n) { this.pagination.page = n; this.muat(); },
        buka(it) {
            // Satu panel: detail menggantikan daftar, dimulai dari atas.
            if (this.tunggal && !this.terpilih) this.gulirDaftar = window.scrollY;
            this.terpilih = it;
            // Klasifikasi pertama langsung terbuka: satu klik untuk memilih
            // sampel sudah cukup untuk melihat isinya.
            this.terbuka = it.klasifikasi?.length
                ? [it.klasifikasi[0].Kode_Aktivitas_Lab] : [];
            if (this.panelKanan) this.muatLifecycle(it.No_Sampel);
            this.muatFotoSampel(it);
            if (this.tunggal) window.scrollTo({ top: 0 });
        },
        /** Mode satu panel: kembali ke daftar, di posisi gulir semula. */
        kembali() {
            this.terpilih = null;
            this.tutupKanan();
            this.$nextTick(() => window.scrollTo({ top: this.gulirDaftar }));
        },

        /* ---------- tata letak ---------- */
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
                if (this.panelKanan && this.terpilih) this.muatLifecycle(this.terpilih.No_Sampel);
            }
            this.lcLebar = this.batasLebar(this.lcLebar);
        },
        /** Tinggi #mobileBottomNav milik layout aplikasi, 0 bila tidak tampil. */
        tinggiNavBawah() {
            const nav = document.getElementById("mobileBottomNav");
            if (!nav || getComputedStyle(nav).display === "none") return 0;
            return Math.round(nav.getBoundingClientRect().height);
        },
        tombolPintas(e) {
            if (this.galeri.show) {
                if (e.key === "Escape") this.tutupGaleri();
                else if (e.key === "ArrowLeft") this.geserGaleri(-1);
                else if (e.key === "ArrowRight") this.geserGaleri(1);
                return;
            }
            if (e.key !== "Escape") return;
            if (this.lcMaks) this.lcMaks = false;
            else if (this.laci && this.panelKanan) this.tutupKanan();
        },

        /* ---------- panel kanan ---------- */
        toggleKanan() {
            this.panelKanan = !this.panelKanan;
            if (!this.panelKanan) this.lcMaks = false;
            if (this.panelKanan && this.terpilih) this.muatLifecycle(this.terpilih.No_Sampel);
            this.simpanPilihanKanan();
        },
        tutupKanan() { this.panelKanan = false; this.lcMaks = false; this.simpanPilihanKanan(); },
        /**
         * Lifecycle tampil secara bawaan di layar lebar; hanya pengguna yang
         * dapat menutupnya, dan keputusan itu diingat antar kunjungan.
         * Penutupan otomatis di mode laci / satu panel tidak ikut disimpan.
         */
        pilihanKanan() {
            try { return localStorage.getItem("fs-lc-buka") !== "0"; } catch (e) { return true; }
        },
        simpanPilihanKanan() {
            if (this.laci) return;
            try { localStorage.setItem("fs-lc-buka", this.panelKanan ? "1" : "0"); } catch (e) { /* abaikan */ }
        },

        /**
         * Jejak sampel diambil dari endpoint modul verifikasi — rangkaian
         * tahapannya sama, jadi tidak perlu disusun dua kali. Finalisasi
         * menilai sampel secara utuh, sehingga seluruh klasifikasi diminta.
         */
        async muatLifecycle(noSampel) {
            this.lcData = "loading";
            try {
                const r = await axios.get(
                    `/api/v1/verifikasi-hasil-analisa/lifecycle/${noSampel}`,
                    { params: { semua: 1 } }
                );
                this.lcData = r.data?.result || null;
            } catch {
                this.lcData = null;
            }
        },

        /** Klasifikasi sudah mendapat rekomendasi verifikator. */
        sudahVerif(k) {
            return !!k.Kode_Status && k.Kode_Status !== "MENUNGGU";
        },

        /**
         * Dari tabel status: buka rincian klasifikasi di panel tengah lalu
         * gulir ke sana. Laci dan lembar layar penuh menutupi isi, jadi
         * ditutup lebih dulu.
         */
        keKlasifikasi(kode) {
            if (!this.terbuka.includes(kode)) this.terbuka.push(kode);
            if (this.laci) this.tutupKanan();
            this.$nextTick(() => {
                const el = this.$refs.akar.querySelector(`[data-kl="${kode}"]`);
                if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        },

        /* ---------- pemisah panel ---------- */
        batasLebar(px) {
            // Panel tengah disisakan sekurang-kurangnya 420 px agar kartu
            // klasifikasi tetap terbaca.
            const maks = Math.max(340, this.lebar - this.lebarKiri() - 420);
            return Math.min(Math.max(px, 340), maks);
        },
        lebarKiri() {
            const el = this.$refs.kiri;
            return el ? el.offsetWidth : 320;
        },
        mulaiGeser() {
            if (this.lcMaks) return;
            this.geser = true;
            document.addEventListener("mousemove", this.saatGeser);
            document.addEventListener("mouseup", this.selesaiGeser);
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
        resetLebar() { this.lcLebar = this.batasLebar(this.lebarBawaan()); this.simpanLebar(); },
        /** Lebar awal panel kanan: lebih ramping di layar menengah. */
        lebarBawaan() { return this.lebar >= 1440 ? 380 : 340; },
        simpanLebar() {
            try { localStorage.setItem("fs-lc-lebar", String(this.lcLebar)); } catch (e) { /* abaikan */ }
        },
        muatLebar() {
            try {
                const n = parseInt(localStorage.getItem("fs-lc-lebar"), 10);
                this.lcLebar = this.batasLebar(isNaN(n) ? this.lebarBawaan() : n);
            } catch (e) { this.lcLebar = this.batasLebar(this.lebarBawaan()); }
        },

        /* ---------- modal keputusan ---------- */
        async bukaModal(item) {
            this.modal = { show: true, keputusan: "", items: [item], catatan: "", err: "" };
            if (!this.masterKeputusan.length) await this.muatMasterKeputusan();
            this.pilihSaran();
        },
        async ulangiMaster() {
            await this.muatMasterKeputusan();
            if (this.masterKeputusan.length) this.pilihSaran();
        },
        pilihSaran() {
            const kode = this.modal.items[0]?.Saran?.kode;
            const ada = this.masterKeputusan.find((k) => k.Kode_Keputusan === kode);
            if (ada) this.modal.keputusan = ada.Kode_Keputusan;
        },
        async kirim() {
            this.simpan = true;
            this.modal.err = "";
            try {
                const r = await axios.post(
                    "/api/v1/sandbox/finalisasi-trial-produksi/keputusan",
                    {
                        keputusan: this.modal.keputusan,
                        catatan: this.modal.catatan,
                        items: this.modal.items.map((i) => ({ No_Sampel: i.No_Sampel })),
                    }
                );
                this.modal.show = false;
                alert(r.data?.message || "Keputusan diproses.");
                await this.muat();
            } catch (e) {
                this.modal.err = e.response?.data?.message
                    || "Gagal menyimpan keputusan. Coba lagi.";
            } finally {
                this.simpan = false;
            }
        },

        /* ---------- foto ---------- */
        /**
         * Ambil seluruh foto sampel yang dipilih.
         *
         * Gambar diambil lewat endpoint stream modul lab, yang sudah
         * menangani token dan perizinannya. Token berlaku singkat dan hanya
         * sekali pakai, sehingga diminta tepat sebelum tiap gambar diambil —
         * bukan sekaligus di depan lalu kedaluwarsa di tengah antrean.
         */
        async muatFotoSampel(it) {
            const keys = new Set();
            (it.klasifikasi || []).forEach((k) =>
                (k.analisa || []).forEach((a) => (a.Foto || []).forEach((f) => keys.add(f.key))));

            for (const key of keys) {
                if (this.fotoUrl[key] || this.fotoGagal[key] || this.fotoProses[key]) continue;
                this.fotoProses[key] = true;
                try {
                    const tok = await axios.post(
                        "/api/v1/lab/hasil-uji/berkas/foto/token/bulk", { keys: [key] });
                    const res = await axios.get(
                        `/api/v1/lab/berkas/stream/foto-uji/${encodeURIComponent(key)}`,
                        { params: { token: tok.data?.[key] }, responseType: "blob" });
                    this.fotoUrl[key] = URL.createObjectURL(res.data);
                } catch {
                    this.fotoGagal[key] = true;
                } finally {
                    delete this.fotoProses[key];
                }
            }
        },

        /** Buka lightbox pada foto pertama milik satu analisa. */
        bukaFotoAnalisa(k, a) {
            const daftar = this.fotoSampel[k.Kode_Aktivitas_Lab] || [];
            const i = daftar.findIndex((f) => (a.Foto || []).some((x) => x.key === f.key));
            if (i > -1) this.bukaGaleri(daftar, i);
        },
        bukaGaleri(daftar, i) {
            this.galeri = { show: true, daftar, i };
        },
        geserGaleri(arah) {
            const n = this.galeri.daftar.length;
            if (n > 1) this.galeri.i = (this.galeri.i + arah + n) % n;
        },
        tutupGaleri() { this.galeri.show = false; },
        /** Usapan mendatar pada layar sentuh berpindah antar foto. */
        mulaiUsap(e) {
            this.usapX = e.changedTouches[0].clientX;
        },
        akhiriUsap(e) {
            if (this.usapX === null) return;
            const dx = e.changedTouches[0].clientX - this.usapX;
            this.usapX = null;
            if (Math.abs(dx) > 50) this.geserGaleri(dx < 0 ? 1 : -1);
        },

        /** Buka gambar ukuran penuh di tab baru. */
        bukaTab(url) {
            if (url) window.open(url, "_blank");
        },

        /** Lepas blob agar tidak menumpuk di memori peramban. */
        bersihkanFoto() {
            Object.values(this.fotoUrl).forEach((u) => URL.revokeObjectURL(u));
            this.fotoUrl = {};
        },

        /**
         * Buka/tutup rincian satu klasifikasi. Saat sampel berganti,
         * klasifikasi pertama dibuka otomatis supaya isinya langsung
         * terbaca tanpa klik tambahan.
         */
        toggleKlasifikasi(kode) {
            const i = this.terbuka.indexOf(kode);
            if (i > -1) this.terbuka.splice(i, 1);
            else this.terbuka.push(kode);
        },

        /* ---------- tampilan ---------- */
        hitungStatus(kode) {
            if (!this.terpilih) return 0;
            return this.terpilih.klasifikasi.filter((k) => k.Kode_Status === kode).length;
        },
        cls(s) {
            return {
                REKOMENDASI: "ok", REKOM_BERSYARAT: "warn", TIDAK_REKOM: "bad",
                MENUNGGU: "wait", SELESAI: "ok", LOLOS: "ok", TIDAK_LOLOS: "bad",
            }[s] || "wait";
        },
        badgeStatus(s) {
            return {
                ok: "bg-success-subtle text-success",
                warn: "bg-warning-subtle text-warning",
                bad: "bg-danger-subtle text-danger",
            }[this.cls(s)] || "bg-light text-muted";
        },
        /**
         * Label kelayakan satu analisa untuk kartu foto — aturannya sama
         * dengan tabel hasil: tanpa master berarti belum dapat dinilai.
         */
        layakAnalisa(a) {
            if (a.Dasar_Kelayakan === "TANPA_MASTER" || a.Dasar_Kelayakan === "KRITERIA_TIDAK_COCOK") {
                return { label: "Belum Dinilai", cls: "bg-warning-subtle text-warning" };
            }
            return a.Flag_Layak === "T"
                ? { label: "Tidak Layak", cls: "bg-danger-subtle text-danger" }
                : { label: "Layak", cls: "bg-success-subtle text-success" };
        },

        /** Angka dipangkas agar tidak menampilkan ekor pecahan panjang. */
        nilai(v) {
            if (v === null || v === undefined || v === "") return "-";
            if (typeof v === "string" && isNaN(Number(v))) return v;
            const n = Number(v);
            if (isNaN(n)) return v;
            return String(Math.round(n * 10000) / 10000).replace(".", ",");
        },

        ikonStatus(s) {
            return {
                REKOMENDASI: "ri-checkbox-circle-line",
                REKOM_BERSYARAT: "ri-error-warning-line",
                TIDAK_REKOM: "ri-close-circle-line",
                MENUNGGU: "ri-time-line",
            }[s] || "ri-time-line";
        },
        labelStatus(s) {
            return {
                REKOMENDASI: "Direkomendasikan",
                REKOM_BERSYARAT: "Bersyarat",
                TIDAK_REKOM: "Tidak Direkomendasikan",
                MENUNGGU: "Menunggu",
                SELESAI: "Selesai", LOLOS: "Lolos Uji", TIDAK_LOLOS: "Tidak Lolos",
            }[s] || s;
        },
        ikonAkt(k) {
            return {
                ANL: "ri-flask-line", LCKV: "ri-eye-line", PLT: "ri-heart-pulse-line",
            }[k] || "ri-test-tube-line";
        },
        warnaBs(w) {
            return { ok: "success", warn: "warning", bad: "danger" }[w] || "primary";
        },

        /** Format tetap: 24 Sep 2026 09:49 — tanggal diurai dari YYYY-MM-DD. */
        stamp(tgl, jam) {
            if (!tgl) return "";
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
.fs { display: flex; flex-direction: column;
    height: calc(100vh - 70px); height: calc(100dvh - 70px);
    overflow: hidden; background: #f3f3f9; margin: -12px -12px 0;
    --lebar-kiri: 320px; }
.fs.uk-lg { --lebar-kiri: 280px; }
.fs.uk-md { --lebar-kiri: 250px; }

/* TOPBAR */
.fs-top { display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px 16px; padding: 12px 18px;
    background: #fff; border-bottom: 1px solid #e9ebec; flex-shrink: 0; }
.fs-top-l { min-width: 0; flex: 1 1 240px; }
.fs-top-t { margin: 0; font-size: 1.05rem; font-weight: 600; color: #405189; }
.fs-top-s { margin: 2px 0 0; font-size: .74rem; color: #878a99; }
.fs-top-r { display: flex; align-items: center; justify-content: flex-end; gap: 8px 10px;
    flex-wrap: wrap; min-width: 0; max-width: 100%; }
.fs-top-a { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.fs-sandbox { font-size: .66rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; color: #b8891b; background: #fff8e6;
    border: 1px solid #f5e0a3; border-radius: 4px; padding: 3px 8px; }
.fs-cakupan { display: inline-flex; align-items: center; font-size: .68rem; font-weight: 700;
    color: #405189; background: #eef1fb; border: 1px solid #dde3f4;
    border-radius: 4px; padding: 3px 9px; }

.fs-ico { border: 1px solid #e9ebec; background: #fff; border-radius: 5px;
    width: 30px; height: 30px; color: #878a99; cursor: pointer; flex-shrink: 0; }

/* BODY — acuan laci dan panel yang diperbesar. */
.fs-body { display: flex; flex: 1; min-height: 0; overflow: hidden; position: relative; }

/* KIRI */
.fs-left { width: var(--lebar-kiri); min-width: 0; display: flex; flex-direction: column;
    background: #fff; border-right: 1px solid #e9ebec; overflow: hidden; flex-shrink: 0; }
.fs-search { position: relative; padding: 11px 12px; border-bottom: 1px solid #f3f3f9; }
.fs-search i { position: absolute; left: 21px; top: 50%; transform: translateY(-50%);
    color: #adb5bd; font-size: .85rem; }
.fs-search input { padding-left: 30px; font-size: .78rem; }
.fs-search input::-webkit-search-cancel-button { cursor: pointer; }
/* Jumlah antrean — pengganti angka pada tab status yang dihapus. */
.fs-lhead { padding: 6px 12px; font-size: .66rem; color: #878a99; border-bottom: 1px solid #f3f3f9;
    background: #fcfcfd; }
.fs-lhead b { color: #495057; font-weight: 700; }
.fs-list { flex: 1; overflow-y: auto; }

.fs-li { width: 100%; display: flex; align-items: center; text-align: left; border: none; background: #fff;
    border-bottom: 1px solid #f3f3f9; padding: 0; cursor: pointer; }
.fs-li:hover { background: #f8f9fc; }
.fs-li.is-active { background: #f6f8fd; }
.fs-li-acc { width: 3px; align-self: stretch; flex-shrink: 0; background: #e9ebec; }
.fs-li-acc.st-ok   { background: #0ab39c; }
.fs-li-acc.st-warn { background: #f7b84b; }
.fs-li-acc.st-bad  { background: #f06548; }
.fs-li-acc.st-n    { background: #ced4da; }
.fs-li-b { flex: 1; padding: 9px 12px; min-width: 0; }
.fs-li-r1 { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.fs-li-r1 b { font-family: ui-monospace, monospace; font-size: .78rem; color: #405189; }
.fs-li-go { color: #ced4da; flex-shrink: 0; padding-right: 8px; display: none; }
.fs-dot { font-size: .5rem; }
.fs-dot.st-ok   { color: #0ab39c; }
.fs-dot.st-warn { color: #f7b84b; }
.fs-dot.st-bad  { color: #f06548; }
.fs-dot.st-n    { color: #ced4da; }
/* Nama barang satu baris; lengkapnya pada tooltip dan panel tengah. */
.fs-li-nm { display: block; margin-top: 3px; font-size: .72rem; font-weight: 600; color: #495057;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fs-li-id { display: flex; flex-wrap: wrap; align-items: center; gap: 3px 10px; margin-top: 2px;
    font-family: ui-monospace, monospace; font-size: .64rem; }
.fs-li-kd { display: inline-flex; align-items: center; gap: 3px; color: #878a99; }
.fs-li-kd i { font-size: .72rem; color: #adb5bd; }
.fs-li-frm { color: #405189; font-weight: 600; }
.fs-li-frm.is-kosong { color: #ced4da; font-weight: 400; }
.fs-li-r3 { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-top: 6px; }
.fs-chip { font-size: .62rem; background: #f3f3f9; color: #878a99; border-radius: 3px;
    padding: 1px 6px; display: inline-flex; align-items: center; gap: 3px; }
.fs-chip.is-mesin { background: #eef1fb; color: #405189; font-weight: 600; }

/* Lencana klasifikasi: kode + jumlah analisa, warna mengikuti statusnya. */
.fs-kl { font-size: .6rem; font-weight: 700; border-radius: 3px; padding: 1px 5px;
    display: inline-flex; align-items: center; gap: 3px; }
.fs-kl em { font-style: normal; opacity: .75; }
.fs-kl.st-ok   { background: #d9f3ee; color: #0c7a68; }
.fs-kl.st-warn { background: #fff3d9; color: #9a6f14; }
.fs-kl.st-bad  { background: #fde4df; color: #b03826; }
.fs-kl.st-wait { background: #eff2f7; color: #878a99; }

/* Pagination */
.fs-pg { display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 8px 12px; border-top: 1px solid #e9ebec; font-size: .68rem; color: #878a99;
    background: #fff; flex-shrink: 0; }
.fs-pg-b { display: flex; align-items: center; gap: 5px; }
.fs-pg-b button { border: 1px solid #e9ebec; background: #fff; border-radius: 4px;
    width: 24px; height: 24px; color: #495057; cursor: pointer; }
.fs-pg-b button:disabled { opacity: .4; cursor: default; }
.fs-pg select { width: auto; font-size: .68rem; padding: 2px 20px 2px 6px; }

/* TENGAH */
.fs-center { flex: 1; min-width: 0; display: flex; flex-direction: column;
    overflow: hidden; background: #f3f3f9; }
.fs-center-empty { flex: 1; }
.fs-dh { display: flex; align-items: flex-start; gap: 10px 12px;
    padding: 13px 16px; background: #fff; border-bottom: 1px solid #e9ebec; flex-shrink: 0; }
.fs-back { background: #f3f6f9; border: none; border-radius: 6px; width: 34px; height: 34px;
    flex-shrink: 0; cursor: pointer; color: #495057; }
.fs-dh-l { flex: 1; min-width: 0; }
.fs-dh-t { display: flex; align-items: center; gap: 6px 8px; flex-wrap: wrap; }
.fs-dh-t b { font-family: ui-monospace, monospace; font-size: .95rem; color: #405189; }
/* Kolom meta menyesuaikan lebar: tertata sebagai grid, tidak patah acak. */
.fs-dh-meta { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px 18px; margin: 9px 0 0; }
.fs-dh-meta > div { min-width: 0; }
.fs-dh-meta dt { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em;
    color: #adb5bd; font-weight: 600; margin: 0; }
.fs-dh-meta dd { font-size: .76rem; color: #495057; font-weight: 600; margin: 1px 0 0;
    overflow-wrap: anywhere; }
.fs-mesin { color: #405189; }
.fs-dh-kd { display: block; margin-top: 1px; font-family: ui-monospace, monospace; font-size: .64rem;
    font-weight: 400; color: #878a99; }
.fs-lcbtn { border: 1px solid #e9ebec; background: #fff; border-radius: 5px; gap: 5px;
    padding: 5px 11px; font-size: .72rem; font-weight: 600; color: #878a99; flex-shrink: 0;
    cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; }
.fs-lcbtn.is-on { background: #405189; border-color: #405189; color: #fff; }

.fs-dbody { flex: 1; overflow-y: auto; padding: 14px 16px; }
.fs-lb { font-size: .74rem; font-weight: 600; color: #495057; margin-bottom: 8px; display: block; }

/* Widget angka — petak melebar mengisi baris, sehingga baris terakhir
   yang tidak penuh tidak menyisakan lubang. */
.fs-w { display: flex; flex-wrap: wrap; gap: 1px;
    background: #e9ebec; border-radius: 7px; overflow: hidden; margin-bottom: 14px; }
.fs-w-i { flex: 1 1 100px; background: #fff; padding: 10px 13px; min-width: 0; }
.fs-w-n { display: block; font-size: 1.35rem; font-weight: 700; color: #405189; line-height: 1.1; }
.fs-w-n.is-ok   { color: #0ab39c; }
.fs-w-n.is-warn { color: #f7b84b; }
.fs-w-n.is-bad  { color: #f06548; }
.fs-w-l { font-size: .62rem; color: #adb5bd; text-transform: uppercase; letter-spacing: .04em; }

/* Daftar klasifikasi — baris rapat bergaya modul ERP. Menjadi wadah
   container query agar kepalanya dapat menata ulang diri saat sempit. */
.fs-acc { display: flex; flex-direction: column; gap: 7px; container: acc / inline-size; }
.fs-ac { background: #fff; border: 1px solid #e9ebec; border-radius: 6px;
    border-left: 3px solid #ced4da; overflow: hidden; scroll-margin-top: 12px; }
.fs-ac.st-ok   { border-left-color: #0ab39c; }
.fs-ac.st-warn { border-left-color: #f7b84b; }
.fs-ac.st-bad  { border-left-color: #f06548; }

.fs-ac-h { width: 100%; display: flex; align-items: center; gap: 10px;
    border: none; background: #fff; padding: 10px 13px; cursor: pointer;
    text-align: left; }
.fs-ac-h:hover { background: #f8f9fc; }
.fs-ac-kar { color: #adb5bd; font-size: 1rem; flex-shrink: 0;
    transition: transform .15s; }
.fs-ac.is-open .fs-ac-kar { transform: rotate(90deg); }
.fs-ac-ik { color: #405189; flex-shrink: 0; }
.fs-ac-nm { font-size: .78rem; font-weight: 600; color: #495057;
    min-width: 150px; flex-shrink: 0; }
.fs-ac-nm em { font-style: normal; font-size: .6rem; color: #adb5bd;
    font-family: ui-monospace, monospace; margin-left: 5px; }
.fs-ac-st { font-size: .7rem; color: #878a99; flex: 1; min-width: 0; }
.fs-ac-st b { color: #405189; font-size: .82rem; }
.fs-ac-st em { font-style: normal; }
.fs-ac-st em.is-bad { color: #e14a2b; font-weight: 600; }
.fs-ac-st em.is-warn { color: #b8891b; font-weight: 600; }
.fs-ac-st em.is-foto { color: #0369a1; font-weight: 600; }
.fs-ac-st em.is-foto i { margin-right: 2px; }
.fs-ac-bd { flex-shrink: 0; }
.fs-ac-u { font-size: .66rem; color: #878a99; text-align: right;
    min-width: 130px; flex-shrink: 0; }
.fs-ac-u em { display: block; font-style: normal; font-size: .62rem; color: #adb5bd; }
.fs-ac-u em.is-kosong { color: #ced4da; font-style: italic; }

/* Kepala klasifikasi saat sempit: nama & status di baris pertama,
   ringkasan angka dan verifikator di bawahnya. */
@container acc (max-width: 640px) {
    .fs-ac-h { flex-wrap: wrap; row-gap: 3px; column-gap: 8px; }
    .fs-ac-nm { flex: 1 1 0; min-width: 0; }
    .fs-ac-bd { order: 2; }
    .fs-ac-st { order: 3; flex: 1 1 100%; padding-left: 44px; }
    .fs-ac-u { order: 4; flex: 1 1 100%; min-width: 0; text-align: left; padding-left: 44px; }
    .fs-ac-u em { display: inline; margin-left: 5px; }
}

.fs-ac-b { border-top: 1px solid #f3f3f9; background: #fbfcfe; }

/* Galeri foto analisa — kartu bergambar di bawah tabel klasifikasi. */
.fs-gal { border-top: 1px dashed #e9ebec; padding: 11px 13px 13px; background: #fff; }
.fs-gal-h { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 6px; margin-bottom: 9px; font-size: .72rem; }
.fs-gal-h i { color: #0891b2; }
.fs-gal-h b { color: #495057; }
.fs-gal-h em { font-style: normal; font-size: .66rem; color: #adb5bd; }
.fs-gal-g { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(190px, 100%), 1fr)); gap: 10px; }
.fs-gal-c { display: flex; flex-direction: column; padding: 0; text-align: left;
    border: 1px solid #e9ebec; border-radius: 7px; overflow: hidden; background: #fff;
    cursor: zoom-in; transition: border-color .14s, box-shadow .14s; min-width: 0; }
.fs-gal-c:hover { border-color: #0891b2; box-shadow: 0 4px 14px rgba(8,145,178,.14); }
.fs-gal-img { position: relative; display: flex; align-items: center; justify-content: center;
    aspect-ratio: 4 / 3; background: #f3f6f9; overflow: hidden; }
.fs-gal-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.fs-gal-x { display: flex; flex-direction: column; align-items: center; gap: 4px;
    font-size: .66rem; color: #adb5bd; }
.fs-gal-x i { font-size: 1.4rem; }
.fs-gal-zoom { position: absolute; right: 7px; bottom: 7px; width: 26px; height: 26px;
    border-radius: 50%; background: rgba(33,37,41,.55); color: #fff; font-size: .85rem;
    display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .14s; }
.fs-gal-c:hover .fs-gal-zoom { opacity: 1; }
.fs-gal-t { display: flex; flex-direction: column; gap: 3px; padding: 8px 10px 9px; min-width: 0; }
.fs-gal-t1 { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.fs-gal-t1 b { font-size: .72rem; color: #495057; }
.fs-gal-t1 .badge { font-size: .6rem; flex-shrink: 0; }
.fs-gal-t2 { font-size: .66rem; color: #6c757d; }
.fs-gal-t2 code { font-size: .64rem; color: #878a99; }
.fs-gal-t3 { font-size: .65rem; color: #878a99; font-style: italic;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.fs-ac-c { display: flex; gap: 6px; font-size: .68rem; color: #6c757d;
    padding: 9px 13px; border-top: 1px dashed #e9ebec; background: #fff; }
.fs-ac-c i { color: #adb5bd; flex-shrink: 0; }
.fs-ac-c b { color: #495057; }

.fs-dfoot { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
    gap: 10px 12px; padding: 11px 16px; background: #fff; border-top: 1px solid #e9ebec; flex-shrink: 0; }
.fs-dfoot-i { font-size: .68rem; color: #adb5bd; }

/* PEMISAH */
.fs-split { width: 5px; flex-shrink: 0; cursor: col-resize; background: #e9ebec;
    position: relative; transition: background .15s; }
.fs-split:hover, .fs-split.is-drag { background: #405189; }
.fs-split::before { content: ""; position: absolute; inset: 0 -4px; }
.fs-split-grip { position: absolute; top: 50%; left: 50%; width: 3px; height: 30px;
    transform: translate(-50%, -50%); border-radius: 2px; background: #adb5bd; }
.fs-split:hover .fs-split-grip, .fs-split.is-drag .fs-split-grip { background: #fff; }

/* KANAN */
.fs-right { width: 380px; min-width: 0; display: flex; flex-direction: column;
    background: #fff; border-left: 1px solid #e9ebec; overflow: hidden; flex-shrink: 0; }
.fs-right.is-max { position: absolute; inset: 0; width: auto !important;
    z-index: 30; border-left: none; box-shadow: 0 0 0 100vmax rgba(0,0,0,.12); }
.fs-rh { display: flex; align-items: center; justify-content: space-between;
    padding: 11px 14px; background: #405189; color: #fff;
    font-size: .82rem; font-weight: 600; flex-shrink: 0; }
.fs-rh-act { display: flex; gap: 6px; }
.fs-rh-x { background: rgba(255,255,255,.18); border: none; color: #fff;
    border-radius: 4px; padding: 3px 7px; cursor: pointer; }
.fs-rb { flex: 1; overflow-y: auto; padding: 13px 14px 30px; }
.fs-rb-sub { display: flex; align-items: center; gap: 6px; margin: 16px 0 10px;
    font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #878a99; }
.fs-rb-sub i { font-size: .8rem; }

/* Laci: panel kanan mengambang di atas detail (dua panel). */
.fs-right.is-laci { position: absolute; top: 0; right: 0; bottom: 0; z-index: 40;
    width: min(420px, 100%) !important; border-left: none; box-shadow: -12px 0 32px rgba(0,0,0,.16); }
.fs-right.is-laci.is-max { width: 100% !important; box-shadow: none; }
.fs-laci-bd { position: absolute; inset: 0; z-index: 35; background: rgba(33,37,41,.28); }
/* Lembar layar penuh (satu panel) — sudah dipindah ke <body>. Di atas
   navigasi bawah aplikasi (z-index 1055), di bawah menu sampingnya (1060). */
.fs-right.is-lembar { position: fixed; inset: 0; z-index: 1058; width: 100% !important;
    border-left: none; box-shadow: none; }
.fs-right.is-lembar .fs-rb { padding-bottom: calc(30px + env(safe-area-inset-bottom)); }
.fs-geser-enter-active.is-laci, .fs-geser-leave-active.is-laci,
.fs-geser-enter-active.is-lembar, .fs-geser-leave-active.is-lembar { transition: transform .22s ease; }
.fs-geser-enter-from.is-laci, .fs-geser-leave-to.is-laci,
.fs-geser-enter-from.is-lembar, .fs-geser-leave-to.is-lembar { transform: translateX(100%); }
.fs-pudar-enter-active, .fs-pudar-leave-active { transition: opacity .2s; }
.fs-pudar-enter-from, .fs-pudar-leave-to { opacity: 0; }

/* Status verifikasi tiap klasifikasi */
.fs-sv { border: 1px solid #e9ebec; border-radius: 7px; overflow: hidden; container: sv / inline-size; }
.fs-sv-h { display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 9px 11px; background: #f8f9fc; border-bottom: 1px solid #e9ebec; }
.fs-sv-h b { display: inline-flex; align-items: center; gap: 6px; font-size: .76rem; color: #495057; }
.fs-sv-h b i { color: #405189; }
.fs-sv-n { font-size: .66rem; color: #878a99; font-weight: 600; white-space: nowrap; }
.fs-sv-siap { display: flex; gap: 7px; align-items: flex-start; padding: 8px 11px;
    font-size: .7rem; line-height: 1.4; }
.fs-sv-siap i { margin-top: 1px; flex-shrink: 0; }
.fs-sv-siap.is-ya { background: #eefaf6; color: #0c7a68; }
.fs-sv-siap.is-belum { background: #fff8e6; color: #8a6d1f; }
.fs-sv-t { width: 100%; border-collapse: collapse; font-size: .72rem; }
.fs-sv-t th { font-size: .58rem; text-transform: uppercase; letter-spacing: .05em; color: #adb5bd;
    font-weight: 700; padding: 7px 11px 5px; border-top: 1px solid #f0f2f5; white-space: nowrap; }
.fs-sv-t th:not(:first-child), .fs-sv-t .fs-sv-r td:not(:first-child) { text-align: center; }
.fs-sv-r { cursor: pointer; }
.fs-sv-r:hover td, .fs-sv-r:hover + .fs-sv-d td { background: #f6f8fd; }
.fs-sv-r td { padding: 8px 11px 2px; border-top: 1px solid #f0f2f5; vertical-align: top; }
.fs-sv-k { display: inline-flex; align-items: center; gap: 5px; }
.fs-sv-k i { color: #405189; }
.fs-sv-k b { color: #495057; font-weight: 600; }
.fs-sv-r em { display: block; font-style: normal; font-size: .6rem; color: #adb5bd;
    font-family: ui-monospace, monospace; margin: 1px 0 0 19px; white-space: nowrap; }
.fs-sv-d td { padding: 3px 11px 8px 30px; font-size: .64rem; color: #878a99; }
.fs-sv-d td > span { display: inline-flex; align-items: center; gap: 3px; margin-right: 9px; }
.fs-sv-d .fs-sv-c { display: block; margin: 4px 0 0; color: #6c757d; font-style: italic; }
.fs-sv-tunggu { color: #8a6d1f; }
.fs-sv-tunggu.is-kosong { font-style: italic; color: #adb5bd; }
.fs-sv-d .fs-sv-p { color: #495057; font-weight: 600; }
.fs-sv-p em { font-style: normal; font-weight: 400; color: #878a99; }
.fs-sv-kosong { color: #ced4da; }
/* Ya / Tidak — jawaban utama tabel ini, dibuat paling mudah dibaca. */
.fs-yt { display: inline-flex; align-items: center; gap: 3px; font-size: .68rem; font-weight: 700;
    border-radius: 10px; padding: 1px 9px 1px 6px; white-space: nowrap; }
.fs-yt.is-ya { background: #d9f3ee; color: #0c7a68; }
.fs-yt.is-menunggu { background: #fff4de; color: #9a6405; }
/* Panel kanan bisa sesempit 340px: rapatkan tabel agar tiga kolomnya tetap muat. */
@container sv (max-width: 400px) {
    .fs-sv-t th, .fs-sv-r td { padding-left: 7px; padding-right: 7px; }
    .fs-sv-t th { letter-spacing: .02em; }
    .fs-sv-r em { white-space: normal; margin-left: 0; }
    .fs-sv-d td { padding-left: 7px; padding-right: 7px; }
    .fs-yt { padding: 1px 7px 1px 5px; }
}

/* Lightbox foto — sudah dipindah ke <body>. */
.fs-zm { position: fixed; inset: 0; z-index: 10060; background: rgba(15,17,20,.82);
    display: flex; align-items: center; justify-content: center; padding: 24px; }
.fs-zm-box { width: 100%; max-width: 980px; max-height: 94vh; display: flex; flex-direction: column;
    background: #1b1e22; border-radius: 9px; overflow: hidden; box-shadow: 0 18px 50px rgba(0,0,0,.4); }
.fs-zm-top { display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 14px; color: #e9ecef; border-bottom: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }
.fs-zm-ttl { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px; min-width: 0; font-size: .82rem; }
.fs-zm-ttl i { color: #38bdf8; }
.fs-zm-ttl code { font-size: .7rem; color: #adb5bd; background: rgba(255,255,255,.07);
    padding: 1px 6px; border-radius: 3px; }
.fs-zm-act { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.fs-zm-n { font-size: .7rem; color: #adb5bd; margin-right: 4px; }
.fs-zm-b { background: rgba(255,255,255,.1); border: none; color: #fff; border-radius: 5px;
    width: 30px; height: 30px; cursor: pointer; }
.fs-zm-b:hover { background: rgba(255,255,255,.2); }
.fs-zm-stage { position: relative; flex: 1; min-height: 240px; display: flex;
    align-items: center; justify-content: center; background: #0f1114; }
.fs-zm-stage img { max-width: 100%; max-height: 66vh; object-fit: contain; display: block; }
.fs-zm-x { display: flex; flex-direction: column; align-items: center; gap: 6px;
    color: #868e96; font-size: .78rem; }
.fs-zm-x i { font-size: 2rem; }
.fs-zm-nav { position: absolute; top: 50%; transform: translateY(-50%);
    width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer;
    background: rgba(255,255,255,.14); color: #fff; font-size: 1.3rem; }
.fs-zm-nav:hover { background: rgba(255,255,255,.26); }
.fs-zm-nav.is-prev { left: 12px; }
.fs-zm-nav.is-next { right: 12px; }
.fs-zm-cap { display: flex; flex-wrap: wrap; gap: 10px 26px; padding: 11px 16px 13px;
    color: #dee2e6; font-size: .74rem; border-top: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }
.fs-zm-ci { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.fs-zm-ci small { font-size: .6rem; text-transform: uppercase; letter-spacing: .06em; color: #868e96; }
.fs-zm-ci b { color: #fff; font-family: ui-monospace, monospace; }
.fs-zm-ci em { font-style: normal; font-family: ui-monospace, monospace; font-size: .68rem;
    color: #adb5bd; margin-left: 4px; }
.fs-zm-none { color: #868e96; font-style: italic; }

/* MODAL — sudah dipindah ke <body>. */
.fs-bd { position: fixed; inset: 0; background: rgba(33,37,41,.45); z-index: 10070;
    display: flex; align-items: center; justify-content: center; padding: 20px; }
.fs-md { background: #fff; border-radius: 8px; width: 100%; max-width: 620px;
    max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
.fs-md-h { display: flex; align-items: center; gap: 11px; padding: 13px 16px; color: #fff; }
.fs-md-h i { font-size: 1.2rem; }
.fs-md-h b { display: block; font-size: .88rem; }
.fs-md-h em { font-style: normal; font-size: .7rem; opacity: .85; }
.fs-md-x { margin-left: auto; background: rgba(255,255,255,.2); border: none;
    color: #fff; border-radius: 4px; padding: 3px 7px; cursor: pointer; }
.fs-md-b { flex: 1; overflow-y: auto; padding: 16px; }
.fs-md-f { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px;
    background: #f8f9fc; border-top: 1px solid #e9ebec; }

.fs-cfm { background: #f8f9fc; border-radius: 6px; padding: 10px 13px; margin-bottom: 14px; }
.fs-cfm-r b { font-family: ui-monospace, monospace; font-size: .8rem; color: #405189; }
.fs-cfm-r span { display: block; font-size: .68rem; color: #878a99; margin-top: 2px; }

.fs-vr { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.fs-vr-i { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 7px; font-size: .72rem;
    background: #fbfcfe; border: 1px solid #f0f2f5; border-radius: 5px; padding: 6px 10px; }
.fs-vr-i i { color: #405189; }
.fs-vr-i b { color: #495057; flex: 1; }
.fs-vr-i em { font-style: normal; font-size: .64rem; color: #adb5bd; }

.fs-reko { display: flex; gap: 9px; align-items: flex-start; padding: 10px 13px;
    border-radius: 6px; font-size: .74rem; margin-bottom: 15px; }
.fs-reko i { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
.fs-reko em { display: block; font-style: normal; font-size: .68rem; opacity: .85; margin-top: 2px; }
.fs-reko.is-ok   { background: #d9f3ee; color: #0c7a68; }
.fs-reko.is-warn { background: #fff3d9; color: #9a6f14; }
.fs-reko.is-bad  { background: #fde4df; color: #b03826; }
.fs-reko.is-n    { background: #eff2f7; color: #6c757d; }

.fs-keps { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.fs-kep { display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e9ebec;
    border-radius: 7px; padding: 11px 13px; cursor: pointer; margin: 0;
    transition: border-color .14s, background .14s; }
.fs-kep:hover { background: #f8f9fc; }
.fs-kep input { margin-top: 3px; cursor: pointer; flex-shrink: 0; }
.fs-kep.is-on.is-ok   { border-color: #0ab39c; background: rgba(10,179,156,.05); }
.fs-kep.is-on.is-warn { border-color: #f7b84b; background: rgba(247,184,75,.07); }
.fs-kep.is-on.is-bad  { border-color: #f06548; background: rgba(240,101,72,.05); }
.fs-kep-b { flex: 1; min-width: 0; }
.fs-kep-h { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 3px; }
.fs-kep-h b { font-size: .79rem; color: #495057; }
.fs-kep.is-ok   .fs-kep-h i { color: #0ab39c; }
.fs-kep.is-warn .fs-kep-h i { color: #f7b84b; }
.fs-kep.is-bad  .fs-kep-h i { color: #f06548; }
.fs-kep-tag { font-size: .58rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; padding: 2px 6px; border-radius: 3px;
    background: #fde4df; color: #b03826; }
.fs-kep-tag.is-opt { background: #eff2f7; color: #878a99; }
.fs-kep-k { display: block; font-size: .71rem; color: #878a99; line-height: 1.5; }
.fs-count { font-size: .68rem; color: #f06548; text-align: right; margin-top: 4px; font-weight: 600; }
.fs-count.is-ok { color: #0ab39c; }

/* Umum */
.fs-state { display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 7px; padding: 40px 20px; color: #878a99; font-size: .78rem; }
.fs-state i { font-size: 1.8rem; color: #ced4da; }
.fs-state .btn i { font-size: inherit; color: inherit; }
.fs-state p { margin: 0; }
.fs-state small { color: #adb5bd; font-size: .72rem; }

.fs-al { display: flex; gap: 9px; align-items: flex-start; border-radius: 6px;
    padding: 10px 13px; font-size: .74rem; margin-bottom: 14px; }
.fs-al i { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
.fs-al--warn { background: #fff8e6; border: 1px solid #f5e0a3; color: #8a6d1f; }
.fs-al--bad  { background: #fde4df; border: 1px solid #f7c4b8; color: #b03826; }

/* ===================== SATU PANEL (< 768px) ===================== */
/* Halaman mengikuti guliran dokumen; daftar dan detail bergantian,
   panel kanan tampil layar penuh. */
.fs.is-tunggal { height: auto; min-height: calc(100vh - 70px); min-height: calc(100dvh - 70px);
    overflow: visible; }
.fs.is-tunggal .fs-top { padding: 10px 12px; }
.fs.is-tunggal .fs-top-s { display: none; }
.fs.is-tunggal .fs-top-r { flex: 1 1 100%; justify-content: flex-start; }
.fs.is-tunggal .fs-body { display: block; overflow: visible; }
.fs.is-tunggal .is-sembunyi { display: none !important; }
.fs.is-tunggal .fs-left { width: 100%; border-right: none; overflow: visible; }
.fs.is-tunggal .fs-list { overflow: visible; }
.fs.is-tunggal .fs-li-go { display: block; }
.fs.is-tunggal .fs-pg { position: sticky; bottom: var(--bawah, 0px); z-index: 3; }
.fs.is-tunggal .fs-center { overflow: visible; min-height: calc(100dvh - 150px); }
.fs.is-tunggal .fs-dh { padding: 10px 12px; }
.fs.is-tunggal .fs-dh-meta { grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap: 8px 12px; }
.fs.is-tunggal .fs-lcbtn { padding: 0; width: 34px; height: 34px; justify-content: center; }
.fs.is-tunggal .fs-lcbtn-t, .fs.is-tunggal .fs-lcbtn-c { display: none; }
.fs.is-tunggal .fs-dbody { overflow: visible; padding: 12px; }
.fs.is-tunggal .fs-w-i { flex-basis: 90px; padding: 9px 10px; }
.fs.is-tunggal .fs-w-n { font-size: 1.15rem; }
.fs.is-tunggal .fs-w-l { font-size: .56rem; letter-spacing: .02em; overflow-wrap: anywhere; }
/* Tombol aksi selalu terjangkau di bawah layar, di atas navigasi bawah
   aplikasi. */
.fs.is-tunggal .fs-dfoot { position: sticky; bottom: var(--bawah, 0px); z-index: 5; padding: 10px 12px;
    padding-bottom: calc(10px + env(safe-area-inset-bottom)); box-shadow: 0 -6px 16px rgba(0,0,0,.06); }
.fs.is-tunggal .fs-dfoot-i { display: none; }
.fs.is-tunggal .fs-dfoot .btn { flex: 1; padding-top: 9px; padding-bottom: 9px; }

/* Perangkat sentuh: sasaran ketuk lebih besar. */
@media (pointer: coarse) {
    .fs-ico { width: 36px; height: 36px; }
    .fs-pg-b button { width: 32px; height: 32px; }
    .fs-rh-x { padding: 6px 10px; }
    .fs-zm-b { width: 38px; height: 38px; }
    .fs-zm-nav { width: 44px; height: 44px; }
}

/* HP: lightbox layar penuh, modal menjadi lembar dari bawah. */
@media (max-width: 767px) {
    .fs-zm { padding: 0; }
    .fs-zm-box { max-width: 100%; height: 100%; max-height: 100%; border-radius: 0; }
    .fs-zm-stage { min-height: 0; }
    .fs-zm-stage img { max-height: 100%; }
    .fs-zm-nav.is-prev { left: 8px; }
    .fs-zm-nav.is-next { right: 8px; }
    .fs-zm-cap { max-height: 38vh; overflow-y: auto; gap: 8px 18px;
        padding-bottom: calc(13px + env(safe-area-inset-bottom)); }

    .fs-bd { padding: 0; align-items: flex-end; }
    .fs-md { max-width: 100%; max-height: 94vh; max-height: 94dvh; border-radius: 14px 14px 0 0; }
    .fs-md-b { padding: 14px; }
    .fs-md-f { padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); }
    .fs-md-f .btn { flex: 1; padding-top: 9px; padding-bottom: 9px; }
}
</style>
