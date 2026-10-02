<template>
    <div ref="akar" class="vs" :class="kelasTata" :style="{ '--bawah': bawah + 'px' }">
        <!--
            VALIDASI TRIAL PRODUKSI — modul pembaharuan (sandbox).

            Tahap pertama dari tiga layar sandbox: Validasi -> Verifikasi ->
            Finalisasi. Tampilannya mengikuti layar Verifikasi:
              KIRI   : antrean per sampel + klasifikasi
              TENGAH : tabel hasil yang SAMA dengan Verifikasi & Finalisasi
                       (kriteria kelayakan, dasar penilaian, belum ada di
                       master), foto, parameter perhitungan, lalu aksi
              KANAN  : Sample Lifecycle sampai tahap validasi, dengan
                       pengambil keputusan berikutnya di atasnya

            Penyimpanan memakai fungsi validasi modul lama apa adanya; uji
            ulang memakai endpoint resampling modul lama.

            RESPONSIF — mengikuti lebar halaman ini sendiri (ResizeObserver):
              <  768px : satu panel — daftar ATAU detail; panel kanan layar penuh
              < 1024px : dua panel  — panel kanan menjadi laci di atas detail
              >= 1024px: tiga panel — panel kanan di samping, dapat ditarik

            Komentar ini sengaja berada di DALAM elemen akar: komentar di luar
            akar membuat komponen ber-akar fragment pada mode dev.
        -->

        <!-- ================= TOPBAR ================= -->
        <div class="vs-top">
            <div class="vs-top-l">
                <h4 class="vs-top-t">Validasi Trial Produksi</h4>
                <p class="vs-top-s">
                    Periksa hasil uji tiap analisa sebelum diteruskan ke verifikasi.
                </p>
            </div>

            <div class="vs-top-r">
                <!-- Hak validasi akun ini: klasifikasi yang analisanya boleh divalidasi. -->
                <span v-for="h in hak" :key="h.kode" class="vs-hak"
                      :title="'Hak validasi: ' + h.jumlah + ' jenis analisa ' + h.nama">
                    <i :class="ikonAkt(h.kode)"></i>{{ h.nama }}<em>{{ h.jumlah }}</em>
                </span>
                <span class="vs-sandbox"><i class="ri-flask-line me-1"></i>Sandbox</span>
                <span v-if="cakupanMesin.length" class="vs-cakupan"
                      title="Validasi sandbox hanya untuk sampel dari mesin ini">
                    <i class="ri-settings-3-line me-1"></i>{{ cakupanMesin.join(' · ') }}
                </span>

                <!-- Contoh data: sampel siap validasi per skenario penilaian,
                     untuk mencoba satu siklus. Hanya di database demo. -->
                <div v-if="dummy.tersedia" ref="menuCth" class="vs-cth">
                    <button type="button" class="vs-dummy" :disabled="dummyProses"
                            :aria-expanded="menuCth ? 'true' : 'false'" @click="menuCth = !menuCth">
                        <span v-if="dummyProses" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="ri-add-circle-line"></i>
                        <span class="vs-dummy-t">Contoh data</span>
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div v-if="menuCth" class="vs-cth-m" role="menu">
                        <span class="vs-cth-h">Buat sampel contoh siap validasi</span>
                        <button v-for="(s, kode) in dummy.skenario || {}" :key="kode" type="button"
                                class="vs-cth-i" role="menuitem" @click="buatDummy(kode)">
                            <b>{{ s.judul }}</b><em>{{ s.keterangan }}</em>
                        </button>
                        <button type="button" class="vs-cth-i is-semua" role="menuitem" @click="buatDummy('semua')">
                            <b><i class="ri-stack-line me-1"></i>Buat keempatnya</b>
                            <em>Satu sampel untuk tiap skenario</em>
                        </button>
                        <button type="button" class="vs-cth-i is-hapus" role="menuitem" @click="hapusDummy">
                            <b><i class="ri-delete-bin-line me-1"></i>Hapus semua contoh</b>
                            <em>Beserta jejak validasi / verifikasinya</em>
                        </button>
                    </div>
                </div>
                <button class="vs-ico" title="Muat ulang" @click="muat">
                    <i class="ri-refresh-line"></i>
                </button>
            </div>
        </div>

        <!-- ================= BODY ================= -->
        <div class="vs-body">
            <!-- ===== KIRI: antrean ===== -->
            <aside ref="kiri" class="vs-left" :class="{ 'is-sembunyi': tunggal && terpilih }">
                <!-- Mencari sambil mengetik (jeda singkat); Enter langsung
                     mencari; tombol × bawaan mengosongkan pencarian. -->
                <div class="vs-search" role="search">
                    <div class="vs-search-i">
                        <i class="ri-search-line"></i>
                        <input v-model="cari" type="search" class="form-control form-control-sm"
                               placeholder="Cari sampel, PO, barang, FRM…"
                               aria-label="Cari no. sampel, PO, split PO, batch, barang, FRM, atau jenis analisa"
                               autocomplete="off" spellcheck="false" enterkeyhint="search"
                               @input="cariTunda" @keydown.enter.prevent="cariSekarang" />
                    </div>
                    <button type="button" class="vs-fbtn" :class="{ 'is-on': filterBuka || jumlahFilter }"
                            :aria-expanded="filterBuka ? 'true' : 'false'" title="Saring tanggal, QR, status"
                            @click="filterBuka = !filterBuka">
                        <i class="ri-filter-3-line"></i>
                        <span v-if="jumlahFilter" class="vs-fbtn-n">{{ jumlahFilter }}</span>
                    </button>
                </div>

                <!-- Saringan yang sama dengan modul lama. -->
                <div v-if="filterBuka" class="vs-filter">
                    <label class="vs-f-l" for="vs-f-mulai">Tanggal uji</label>
                    <div class="vs-f-tgl">
                        <input id="vs-f-mulai" v-model="filter.mulai" type="date"
                               class="form-control form-control-sm" aria-label="Dari tanggal" />
                        <span>&ndash;</span>
                        <input v-model="filter.selesai" type="date" :min="filter.mulai || null"
                               class="form-control form-control-sm" aria-label="Sampai tanggal" />
                    </div>
                    <small v-if="(filter.mulai && !filter.selesai) || (!filter.mulai && filter.selesai)"
                           class="vs-f-hint">Isi kedua tanggal untuk menyaring.</small>
                    <div class="vs-f-2">
                        <select v-model="filter.qrcode" class="form-select form-select-sm" aria-label="Jenis QR">
                            <option value="">Semua QR</option>
                            <option value="multi">Multi QR</option>
                            <option value="single">Single QR</option>
                        </select>
                        <select v-model="filter.status" class="form-select form-select-sm" aria-label="Status uji">
                            <option value="">Semua status</option>
                            <option value="lolos">Lolos uji</option>
                            <option value="tidak_lolos">Ada yang tidak layak</option>
                        </select>
                    </div>
                    <button v-if="jumlahFilter" type="button" class="vs-f-reset" @click="resetFilter">
                        <i class="ri-close-line"></i>Hapus saringan
                    </button>
                </div>

                <div v-if="!galat && (totalAntrean || !loading)" class="vs-lhead">
                    <label v-if="daftar.length && !loading" class="vs-cball" title="Pilih semua di halaman ini">
                        <input type="checkbox" :checked="semuaDipilih"
                               :indeterminate.prop="sebagianDipilih && !semuaDipilih"
                               aria-label="Pilih semua sampel di halaman ini" @change="pilihSemua" />
                    </label>
                    <span aria-live="polite">
                        <template v-if="adaSaringan && loading">Mencari&hellip;</template>
                        <template v-else-if="adaSaringan">
                            <b>{{ pagination.totalData }}</b> dari {{ totalAntrean }} sampel cocok
                        </template>
                        <template v-else>
                            <b>{{ totalAntrean }}</b> sampel menunggu validasi
                        </template>
                    </span>
                </div>

                <div class="vs-list">
                    <div v-if="loading" class="vs-state">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <span>Memuat…</span>
                    </div>

                    <div v-else-if="galat" class="vs-state vs-state--empty">
                        <i class="ri-error-warning-line text-danger"></i>
                        <p>{{ galat }}</p>
                        <button class="btn btn-sm btn-light mt-2" @click="muat">
                            <i class="ri-refresh-line me-1"></i>Coba lagi
                        </button>
                    </div>

                    <div v-else-if="!daftar.length" class="vs-state vs-state--empty">
                        <template v-if="adaSaringan">
                            <i class="ri-search-line"></i>
                            <p>Tidak ada sampel yang cocok dengan pencarian atau saringan.</p>
                            <button class="btn btn-sm btn-light mt-2" @click="hapusSemuaSaringan">
                                <i class="ri-close-line me-1"></i>Hapus pencarian &amp; saringan
                            </button>
                        </template>
                        <template v-else>
                            <i class="ri-inbox-line"></i>
                            <p>{{ pesanKosong || 'Tidak ada sampel yang menunggu validasi.' }}</p>
                            <button v-if="dummy.tersedia && hak.length" class="btn btn-sm btn-light mt-2"
                                    :disabled="dummyProses" @click="buatDummy('semua')">
                                <i class="ri-add-circle-line me-1"></i>Buat 4 contoh data
                            </button>
                        </template>
                    </div>

                    <!-- Satu kartu = satu klasifikasi pada satu sampel, sama
                         dengan layar Verifikasi. Kotak centang untuk validasi
                         massal. -->
                    <div v-for="it in daftar" :key="kunci(it)" v-else
                         class="vs-li" :class="{ 'is-active': aktif(it), 'is-cek': dipilihKah(it) }">
                        <span class="vs-li-acc" :class="'st-' + nada(it)"></span>
                        <label class="vs-li-cb" :title="dipilihKah(it) ? 'Batal pilih' : 'Pilih untuk validasi massal'">
                            <input type="checkbox" :checked="dipilihKah(it)"
                                   :aria-label="'Pilih ' + it.No_Sampel + ' ' + it.Nama_Aktivitas"
                                   @change="togglePilih(it)" />
                        </label>
                        <button type="button" class="vs-li-b" @click="buka(it)">
                            <span class="vs-li-r1">
                                <b class="vs-li-no">{{ it.No_Sampel }}</b>
                                <span class="vs-kl" :title="it.Nama_Aktivitas">
                                    <i :class="ikonAkt(it.Kode_Aktivitas_Lab)"></i>{{ it.Kode_Aktivitas_Lab }}
                                </span>
                                <span class="vs-li-tgl" title="Tanggal uji terakhir">{{ stamp(it.Tanggal) }}</span>
                            </span>
                            <span class="vs-li-nm" :title="it.Nama_Barang">{{ it.Nama_Barang || '-' }}</span>
                            <span class="vs-li-id">
                                <span title="Kode barang"><i class="ri-barcode-line"></i>{{ it.Kode_Barang || '-' }}</span>
                                <span class="vs-li-frm" :class="{ 'is-kosong': !it.Kode_Formula }"
                                      :title="it.Kode_Formula ? 'Nomor formula' : 'Nomor formula tidak ditemukan'">
                                    {{ it.Kode_Formula || 'FRM -' }}
                                </span>
                            </span>
                            <span class="vs-li-id">
                                <span title="Nomor PO"><i class="ri-file-list-3-line"></i>{{ it.No_Po || '-' }}</span>
                                <span class="vs-li-msn" title="Mesin"><i class="ri-settings-3-line"></i>{{ it.Nama_Mesin || '-' }}</span>
                                <span :title="it.Multi ? 'Multi QR' : 'Single QR'">
                                    <i class="ri-qr-code-line"></i>{{ it.Multi ? 'Multi' : 'Single' }}
                                </span>
                            </span>
                            <span class="vs-li-r4">
                                <span class="vs-tag">{{ it.Jumlah_Analisa }} analisa</span>
                                <span v-if="!it.Jumlah_Tidak_Layak && !it.Jumlah_Tanpa_Master && !it.Butuh_Pembanding"
                                      class="vs-tag is-ok"><i class="ri-check-line"></i>Semua layak</span>
                                <span v-if="it.Putaran > 1" class="vs-tag is-info">
                                    <i class="ri-refresh-line"></i>Putaran {{ it.Putaran }}
                                </span>
                            </span>
                            <!-- Analisa yang perlu diperhatikan, disebut namanya. -->
                            <span v-if="(it.Analisa_Tidak_Layak || []).length" class="vs-li-ket is-bad"
                                  :title="'Tidak layak: ' + it.Analisa_Tidak_Layak.join(', ')">
                                <i class="ri-close-circle-line"></i><b>{{ it.Jumlah_Tidak_Layak }} tidak layak</b>
                                &middot; {{ ringkasNama(it.Analisa_Tidak_Layak) }}
                            </span>
                            <span v-if="(it.Analisa_Tanpa_Master || []).length" class="vs-li-ket is-warn"
                                  :title="'Belum ada di master: ' + it.Analisa_Tanpa_Master.join(', ')">
                                <i class="ri-error-warning-line"></i><b>{{ it.Jumlah_Tanpa_Master }} belum ada di master</b>
                                &middot; {{ ringkasNama(it.Analisa_Tanpa_Master) }}
                            </span>
                        </button>
                        <i class="ri-arrow-right-s-line vs-li-go"></i>
                    </div>
                </div>

                <div v-if="dipilih.length" class="vs-bulk">
                    <span><i class="ri-checkbox-multiple-line"></i><b>{{ dipilih.length }}</b> sampel dipilih</span>
                    <span class="vs-bulk-b">
                        <button class="btn btn-sm btn-light" @click="dipilih = []">Batal</button>
                        <button class="btn btn-sm btn-success" @click="bukaValidasi(dipilih)">
                            <i class="ri-check-double-line me-1"></i>Validasi
                        </button>
                    </span>
                </div>

                <div v-if="!loading && pagination.totalData" class="vs-pg">
                    <span>{{ dariKe }}</span>
                    <div class="vs-pg-b">
                        <button :disabled="pagination.page <= 1" aria-label="Halaman sebelumnya"
                                @click="keHal(pagination.page - 1)">
                            <i class="ri-arrow-left-s-line"></i>
                        </button>
                        <span>{{ pagination.page }} / {{ pagination.totalPage || 1 }}</span>
                        <button :disabled="pagination.page >= pagination.totalPage" aria-label="Halaman berikutnya"
                                @click="keHal(pagination.page + 1)">
                            <i class="ri-arrow-right-s-line"></i>
                        </button>
                    </div>
                    <select v-model.number="limit" class="form-select form-select-sm" aria-label="Baris per halaman"
                            @change="keHal(1)">
                        <option :value="12">12</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                    </select>
                </div>
            </aside>

            <!-- ===== TENGAH ===== -->
            <section class="vs-center" :class="{ 'is-sembunyi': tunggal && !terpilih }">
                <div v-if="!terpilih" class="vs-state vs-state--empty vs-center-empty">
                    <i class="ri-test-tube-line"></i>
                    <p>Pilih sampel untuk divalidasi</p>
                    <small>Hasil uji, kriteria kelayakan, dan foto analisa akan ditampilkan di sini.</small>
                </div>

                <template v-else>
                    <div class="vs-dh">
                        <button v-if="tunggal" class="vs-back" title="Kembali ke daftar" @click="kembali">
                            <i class="ri-arrow-left-line"></i>
                        </button>
                        <div class="vs-dh-l">
                            <div class="vs-dh-t">
                                <b>{{ terpilih.No_Sampel }}</b>
                                <span class="badge bg-primary-subtle text-primary">
                                    <i :class="ikonAkt(terpilih.Kode_Aktivitas_Lab)" class="me-1"></i>{{ terpilih.Nama_Aktivitas }}
                                </span>
                                <span class="badge bg-warning-subtle text-warning">Trial Produksi</span>
                                <span class="badge bg-light text-muted">
                                    <i class="ri-time-line me-1"></i>Menunggu validasi
                                </span>
                                <span class="badge bg-light text-muted">
                                    <i class="ri-qr-code-line me-1"></i>{{ terpilih.Multi ? 'Multi QR' : 'Single QR' }}
                                </span>
                            </div>
                            <dl class="vs-dh-meta">
                                <div>
                                    <dt>No. PO</dt>
                                    <dd>
                                        {{ terpilih.No_Po || '-' }}
                                        <small class="vs-dh-kd">split {{ terpilih.No_Split_Po || '-' }} · batch {{ terpilih.No_Batch ?? '-' }}</small>
                                    </dd>
                                </div>
                                <div><dt>Formula</dt><dd>{{ terpilih.Kode_Formula || '-' }}</dd></div>
                                <div>
                                    <dt>Mesin</dt>
                                    <dd class="vs-mesin"><i class="ri-settings-3-line me-1"></i>{{ terpilih.Nama_Mesin || '-' }}</dd>
                                </div>
                                <div>
                                    <dt>Produk</dt>
                                    <dd>
                                        {{ terpilih.Nama_Barang || '-' }}
                                        <small class="vs-dh-kd">{{ terpilih.Kode_Barang }}</small>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Diinput</dt>
                                    <dd>
                                        {{ (terpilih.Penguji || []).join(', ') || '-' }}
                                        <small class="vs-dh-kd">{{ stamp(terpilih.Tanggal, terpilih.Jam) }}</small>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <button class="vs-lcbtn" :class="{ 'is-on': panelKanan }"
                                title="Sample Lifecycle" @click="toggleKanan">
                            <i class="ri-route-line"></i><span class="vs-lcbtn-t">Lifecycle</span>
                            <i :class="panelKanan ? 'ri-contract-right-line' : 'ri-expand-left-line'" class="vs-lcbtn-c"></i>
                        </button>
                    </div>

                    <div class="vs-dbody">
                        <div v-if="muatRincian" class="vs-state">
                            <div class="spinner-border spinner-border-sm text-primary"></div>
                            <span>Memuat hasil uji…</span>
                        </div>

                        <div v-else-if="galatRincian" class="vs-state vs-state--empty">
                            <i class="ri-error-warning-line text-danger"></i>
                            <p>{{ galatRincian }}</p>
                            <button class="btn btn-sm btn-light mt-2" @click="muatRincian_()">
                                <i class="ri-refresh-line me-1"></i>Coba lagi
                            </button>
                        </div>

                        <template v-else-if="rinci">
                            <div v-if="rinci.Jumlah_Tidak_Layak > 0" class="vs-al vs-al--bad">
                                <i class="ri-close-circle-line"></i>
                                <span>
                                    <b>{{ rinci.Jumlah_Tidak_Layak }} hasil tidak layak.</b>
                                    Periksa sebelum memvalidasi — gunakan <b>Uji Ulang</b> bila hasilnya perlu diuji kembali.
                                </span>
                            </div>
                            <div v-if="rinci.Jumlah_Tanpa_Master > 0" class="vs-al vs-al--warn">
                                <i class="ri-error-warning-line"></i>
                                <span>
                                    <b>{{ rinci.Jumlah_Tanpa_Master }} hasil belum dapat dinilai</b>
                                    karena kriterianya belum ada di master.
                                </span>
                            </div>
                            <div v-if="terpilih.Putaran > 1" class="vs-al vs-al--info">
                                <i class="ri-refresh-line"></i>
                                <span>
                                    <b>Ada hasil uji ulang (putaran {{ terpilih.Putaran }}).</b>
                                    Hasil putaran sebelumnya dan alasan resamplingnya tercatat di Sample Lifecycle.
                                </span>
                            </div>

                            <label class="vs-lb">Hasil analisa <em>{{ jumlahAnalisa }} analisa menunggu validasi</em></label>
                            <!-- Tabel yang sama dengan Verifikasi & Finalisasi. -->
                            <TabelHasilAnalisa :analisa="rinci.analisa"
                                               :butuh-pembanding="rinci.Butuh_Pembanding"
                                               :pembanding="rinci.Pembanding"
                                               :matriks="rinci.Matriks_Pembanding"
                                               menunggu
                                               @foto="bukaFotoAnalisa" />

                            <!-- Foto analisa: kartu bergambar, ketuk untuk memperbesar. -->
                            <div v-if="foto.length" class="vs-gal">
                                <div class="vs-gal-h">
                                    <i class="ri-image-2-line"></i>
                                    <b>Foto analisa</b>
                                    <em>{{ foto.length }} foto &middot; ketuk untuk memperbesar</em>
                                </div>
                                <div class="vs-gal-g">
                                    <button v-for="(f, i) in foto" :key="f.key" type="button" class="vs-gal-c"
                                            @click="bukaGaleri(i)">
                                        <span class="vs-gal-img">
                                            <img v-if="fotoUrl[f.key]" :src="fotoUrl[f.key]" :alt="'Foto ' + f.analisa" />
                                            <span v-else-if="fotoGagal[f.key]" class="vs-gal-x">
                                                <i class="ri-image-line"></i>Gagal memuat
                                            </span>
                                            <span v-else class="spinner-border spinner-border-sm text-primary"></span>
                                            <span class="vs-gal-zoom"><i class="ri-zoom-in-line"></i></span>
                                        </span>
                                        <span class="vs-gal-t">
                                            <span class="vs-gal-t1">
                                                <b>{{ f.analisa }}</b>
                                                <span class="badge" :class="f.layak.cls">{{ f.layak.label }}</span>
                                            </span>
                                            <span class="vs-gal-t2"><code>{{ f.sampel }}</code> &middot; {{ f.hasil }}</span>
                                            <span v-if="f.keterangan" class="vs-gal-t3">&ldquo;{{ f.keterangan }}&rdquo;</span>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <!-- Parameter masukan analisa perhitungan, untuk
                                 memeriksa hasil akhir terhadap angka asalnya. -->
                            <div v-if="(rinci.parameter || []).length" class="vs-sec">
                                <button type="button" class="vs-sec-h" :aria-expanded="paramBuka ? 'true' : 'false'"
                                        @click="paramBuka = !paramBuka">
                                    <i class="ri-calculator-line"></i>
                                    <span>Parameter perhitungan <em>{{ rinci.parameter.length }} analisa</em></span>
                                    <i :class="paramBuka ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'" class="vs-sec-k"></i>
                                </button>
                                <div v-if="paramBuka" class="vs-sec-b">
                                    <div v-for="p in rinci.parameter" :key="p.Id_Jenis_Analisa" class="vs-pr">
                                        <span class="vs-pr-n">{{ p.Nama_Jenis_Analisa }}</span>
                                        <div class="vs-tw">
                                            <table class="vs-t">
                                                <thead>
                                                    <tr>
                                                        <th>No. Transaksi</th>
                                                        <th>Sampel</th>
                                                        <th v-for="(pr, i) in p.parameter" :key="i" class="is-num">
                                                            {{ pr.nama }}<small v-if="pr.satuan"> {{ pr.satuan }}</small>
                                                        </th>
                                                        <th class="is-num is-rumus">Hasil</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="b in p.baris" :key="b.No_Faktur">
                                                        <td class="vs-mono is-kd">{{ b.No_Faktur }}</td>
                                                        <td class="vs-mono is-kd">{{ b.No_Sampel_Uji }}</td>
                                                        <td v-for="(v, i) in b.nilai" :key="i" class="is-num">
                                                            {{ v === null ? '—' : angka(v) }}
                                                        </td>
                                                        <td class="is-num is-rumus">{{ angka(b.Hasil) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="vs-dfoot">
                        <span class="vs-dfoot-i">
                            <i class="ri-information-line me-1"></i>
                            Validasi tersimpan sebagai jejak audit, lalu diteruskan ke verifikasi
                        </span>
                        <span class="vs-dfoot-b">
                            <button class="btn btn-sm btn-warning text-white" :disabled="!rinci || simpan"
                                    @click="bukaUjiUlang">
                                <i class="ri-refresh-line me-1"></i>Uji Ulang
                            </button>
                            <button class="btn btn-sm btn-success" :disabled="!rinci || simpan"
                                    @click="bukaValidasi([terpilih])">
                                <i class="ri-check-double-line me-1"></i>Validasi
                            </button>
                        </span>
                    </div>
                </template>
            </section>

            <!-- ===== PEMISAH PANEL (dapat ditarik) — hanya tiga panel ===== -->
            <div v-if="panelKanan && terpilih && !laci"
                 class="vs-split" :class="{ 'is-drag': geser }"
                 title="Tarik untuk mengubah lebar — klik dua kali untuk lebar semula"
                 @mousedown.prevent="mulaiGeser" @dblclick="resetLebar">
                <span class="vs-split-grip"></span>
            </div>

            <transition name="vs-pudar">
                <div v-if="panelKanan && terpilih && laci && !tunggal" class="vs-laci-bd"
                     @click="tutupKanan"></div>
            </transition>

            <!-- ===== KANAN: Sample Lifecycle sampai tahap validasi ===== -->
            <Teleport to="body" :disabled="!tunggal">
            <transition name="vs-geser">
                <aside v-if="panelKanan && terpilih" class="vs-right"
                       :class="{ 'is-max': lcMaks, 'is-laci': laci && !tunggal, 'is-lembar': tunggal }"
                       :style="gayaKanan">
                    <div class="vs-rh">
                        <span><i class="ri-route-line me-2"></i>Sample Lifecycle</span>
                        <span class="vs-rh-act">
                            <button v-if="!tunggal" class="vs-rh-x" :title="lcMaks ? 'Kecilkan' : 'Perbesar'"
                                    @click="lcMaks = !lcMaks">
                                <i :class="lcMaks ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'"></i>
                            </button>
                            <button class="vs-rh-x" title="Tutup panel" @click="tutupKanan">
                                <i class="ri-close-line"></i>
                            </button>
                        </span>
                    </div>
                    <div class="vs-rb">
                        <LifecycleSampel :data="lcData" batas="validasi" :keputusan="keputusan" />
                    </div>
                </aside>
            </transition>
            </Teleport>
        </div>

        <!-- ================= LIGHTBOX FOTO ================= -->
        <Teleport to="body">
        <div v-if="galeri.show && fotoAktif" class="vs-zm" @click.self="tutupGaleri">
            <div class="vs-zm-box">
                <div class="vs-zm-top">
                    <span class="vs-zm-ttl">
                        <i class="ri-image-2-line"></i>
                        <b>{{ fotoAktif.analisa }}</b>
                        <code>{{ fotoAktif.sampel }}</code>
                    </span>
                    <span class="vs-zm-act">
                        <span v-if="foto.length > 1" class="vs-zm-n">{{ galeri.i + 1 }} / {{ foto.length }}</span>
                        <button v-if="fotoUrl[fotoAktif.key]" type="button" class="vs-zm-b"
                                title="Buka ukuran penuh di tab baru" @click="bukaTab(fotoUrl[fotoAktif.key])">
                            <i class="ri-external-link-line"></i>
                        </button>
                        <button type="button" class="vs-zm-b" title="Tutup (Esc)" @click="tutupGaleri">
                            <i class="ri-close-line"></i>
                        </button>
                    </span>
                </div>
                <div class="vs-zm-stage" @touchstart.passive="mulaiUsap" @touchend="akhiriUsap">
                    <button v-if="foto.length > 1" type="button" class="vs-zm-nav is-prev"
                            title="Sebelumnya" @click="geserGaleri(-1)">
                        <i class="ri-arrow-left-s-line"></i>
                    </button>
                    <img v-if="fotoUrl[fotoAktif.key]" :src="fotoUrl[fotoAktif.key]" :alt="'Foto ' + fotoAktif.analisa" />
                    <div v-else-if="fotoGagal[fotoAktif.key]" class="vs-zm-x">
                        <i class="ri-image-line"></i>
                        <span>Foto gagal dimuat dari penyimpanan.</span>
                    </div>
                    <div v-else class="spinner-border text-light"></div>
                    <button v-if="foto.length > 1" type="button" class="vs-zm-nav is-next"
                            title="Berikutnya" @click="geserGaleri(1)">
                        <i class="ri-arrow-right-s-line"></i>
                    </button>
                </div>
                <div class="vs-zm-cap">
                    <div class="vs-zm-ci">
                        <small>Hasil</small>
                        <span>
                            <b>{{ fotoAktif.hasil }}</b>
                            <span class="badge ms-1" :class="fotoAktif.layak.cls">{{ fotoAktif.layak.label }}</span>
                        </span>
                    </div>
                    <div v-if="fotoAktif.keterangan" class="vs-zm-ci">
                        <small>Keterangan foto</small>
                        <span>{{ fotoAktif.keterangan }}</span>
                    </div>
                    <div class="vs-zm-ci">
                        <small>Diinput</small>
                        <span>
                            {{ fotoAktif.input ? fotoAktif.input.nama : '-' }}
                            <em v-if="fotoAktif.input">{{ stamp(fotoAktif.input.tanggal, fotoAktif.input.jam) }}</em>
                        </span>
                    </div>
                    <div class="vs-zm-ci">
                        <small>Divalidasi</small>
                        <span class="vs-zm-none">menunggu validasi</span>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= MODAL VALIDASI ================= -->
        <Teleport to="body">
        <div v-if="val.show" class="vs-bd" @click.self="tutupValidasi">
            <div class="vs-md" role="dialog" aria-modal="true" aria-labelledby="vs-val-judul">
                <div class="vs-md-h bg-success">
                    <i class="ri-check-double-line"></i>
                    <div>
                        <b id="vs-val-judul">Simpan Validasi</b>
                        <em v-if="val.satu">{{ val.item.No_Sampel }} · {{ val.item.Nama_Aktivitas }}</em>
                        <em v-else>{{ val.items.length }} sampel dipilih</em>
                    </div>
                    <button class="vs-md-x" title="Tutup" @click="tutupValidasi"><i class="ri-close-line"></i></button>
                </div>
                <div class="vs-md-b">
                    <p class="vs-md-p">
                        Analisa yang dicentang disimpan sebagai <b>hasil validasi</b> dan diteruskan ke
                        verifikasi. Hasil yang perlu diuji kembali jangan dicentang — gunakan <b>Uji Ulang</b>.
                    </p>

                    <!-- Satu sampel: pilih analisa (dan sub sampel multi QR). -->
                    <div v-if="val.satu" class="vs-ms">
                        <label v-for="a in val.analisa" :key="a.id" class="vs-ms-i vs-ms-cek"
                               :class="{ 'is-off': !val.centang[a.id] }">
                            <input v-model="val.centang[a.id]" type="checkbox" />
                            <span class="vs-ms-b">
                                <span class="vs-ms-t">
                                    <b>{{ a.nama }}</b>
                                    <span v-if="a.layak" class="vs-st" :class="'is-' + nadaLayak(a.layak)">
                                        {{ labelLayak(a.layak) }}
                                    </span>
                                </span>
                                <span class="vs-ms-m">
                                    {{ a.multi ? 'Multi QR' : 'Single QR' }}
                                    <template v-if="a.putaran > 1"> · putaran {{ a.putaran }}</template>
                                    <template v-if="a.sub.length === 1"> · <code>{{ a.sub[0] }}</code></template>
                                </span>
                                <select v-if="a.sub.length > 1" v-model="val.sub[a.id]"
                                        class="form-select form-select-sm mt-1" :aria-label="'Sub sampel ' + a.nama"
                                        @click.stop>
                                    <option value="">Semua sub sampel</option>
                                    <option v-for="s in a.sub" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </span>
                        </label>
                    </div>

                    <!-- Massal: seluruh analisa yang menunggu pada tiap sampel. -->
                    <div v-else class="vs-ms">
                        <div v-for="it in val.items" :key="kunci(it)" class="vs-ms-i">
                            <span class="vs-ms-t">
                                <b>{{ it.No_Sampel }}</b>
                                <span class="vs-kl"><i :class="ikonAkt(it.Kode_Aktivitas_Lab)"></i>{{ it.Kode_Aktivitas_Lab }}</span>
                            </span>
                            <span class="vs-ms-m">
                                {{ it.Jumlah_Analisa }} analisa
                                <template v-if="it.Jumlah_Tidak_Layak"> · <b class="text-danger">{{ it.Jumlah_Tidak_Layak }} tidak layak</b></template>
                                <template v-if="it.Jumlah_Tanpa_Master"> · {{ it.Jumlah_Tanpa_Master }} belum ada di master</template>
                                · {{ it.Nama_Barang }}
                            </span>
                        </div>
                    </div>

                    <div v-if="!val.satu && adaTidakLayakMassal" class="vs-al vs-al--bad mt-3 mb-0">
                        <i class="ri-close-circle-line"></i>
                        <span>Ada sampel dengan hasil tidak layak. Buka sampelnya bila ingin memilih analisa tertentu.</span>
                    </div>
                    <div v-if="val.err" class="vs-al vs-al--bad mt-3 mb-0">
                        <i class="ri-error-warning-line"></i><span>{{ val.err }}</span>
                    </div>
                </div>
                <div class="vs-md-f">
                    <button class="btn btn-sm btn-light" :disabled="val.kirim" @click="tutupValidasi">Batal</button>
                    <button class="btn btn-sm btn-success" :disabled="val.kirim || !jumlahDicentang" @click="kirimValidasi">
                        <span v-if="val.kirim" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-check-double-line me-1"></i>Validasi {{ jumlahDicentang }} analisa
                    </button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= MODAL UJI ULANG ================= -->
        <Teleport to="body">
        <div v-if="ujiUlang.show" class="vs-bd" @click.self="tutupUjiUlang">
            <div class="vs-md" role="dialog" aria-modal="true" aria-labelledby="vs-uu-judul">
                <div class="vs-md-h bg-warning">
                    <i class="ri-refresh-line"></i>
                    <div>
                        <b id="vs-uu-judul">Uji Ulang (Resampling)</b>
                        <em>{{ terpilih ? terpilih.No_Sampel + ' · ' + terpilih.Nama_Aktivitas : '' }}</em>
                    </div>
                    <button class="vs-md-x" title="Tutup" @click="tutupUjiUlang"><i class="ri-close-line"></i></button>
                </div>
                <div class="vs-md-b">
                    <label class="vs-lb" for="vs-uu-an">Analisa yang diuji ulang</label>
                    <select id="vs-uu-an" v-model="ujiUlang.id" class="form-select form-select-sm mb-3"
                            @change="pilihAnalisaUlang">
                        <option value="" disabled>— Pilih analisa —</option>
                        <option v-for="a in (rinci ? rinci.daftar : [])" :key="a.id" :value="a.id">
                            {{ a.nama }}{{ a.layak === 'T' ? ' — tidak layak' : '' }}
                        </option>
                    </select>

                    <template v-if="ujiUlangAnalisa">
                        <div class="vs-al vs-al--info">
                            <i class="ri-qr-code-line"></i>
                            <span v-if="!ujiUlangAnalisa.multi">
                                <b>Single QR.</b> Pengujian ulang memakai nomor sampel yang sama.
                                Hasil sekarang tetap tersimpan sebagai putaran yang ditolak.
                            </span>
                            <span v-else>
                                <b>Multi QR.</b> Pilih sub sampel baru yang akan diuji menggantikan
                                pengujian sekarang. Hasil sekarang tetap tersimpan sebagai putaran yang ditolak.
                            </span>
                        </div>

                        <label class="vs-lb">{{ ujiUlangAnalisa.multi ? 'No. uji sebelumnya' : 'No. sampel (diuji ulang)' }}</label>
                        <select v-if="ujiUlangAnalisa.multi && ujiUlangAnalisa.sub.length > 1" v-model="ujiUlang.asal"
                                class="form-select form-select-sm mb-3" aria-label="No. uji sebelumnya">
                            <option v-for="s in ujiUlangAnalisa.sub" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <input v-else :value="ujiUlang.asal" type="text" disabled
                               class="form-control form-control-sm bg-light mb-3" />

                        <template v-if="ujiUlangAnalisa.multi">
                            <label class="vs-lb" for="vs-uu-sub">Sub sampel uji ulang</label>
                            <div v-if="ujiUlang.muatOpsi" class="vs-md-ld">
                                <span class="spinner-border spinner-border-sm text-primary me-2"></span>Memuat sub sampel…
                            </div>
                            <select v-else id="vs-uu-sub" v-model="ujiUlang.pilih" class="form-select form-select-sm mb-1">
                                <option value="" disabled>— Pilih sub sampel —</option>
                                <option v-for="o in ujiUlang.opsi" :key="o" :value="o">{{ o }}</option>
                            </select>
                            <small v-if="!ujiUlang.muatOpsi && !ujiUlang.opsi.length" class="vs-md-warn">
                                Tidak ada sub sampel QR yang tersisa untuk analisa ini.
                            </small>
                            <div class="mb-3"></div>
                        </template>

                        <label class="vs-lb" for="vs-uu-alasan">Alasan resampling <span class="text-danger">*</span></label>
                        <textarea id="vs-uu-alasan" v-model="ujiUlang.alasan" rows="3" maxlength="500"
                                  class="form-control form-control-sm"
                                  placeholder="Contoh: hasil ASH 11,2% di atas batas 10%, duplo konsisten, dugaan sampel tidak homogen."></textarea>
                        <div class="vs-count" :class="{ 'is-ok': alasanValid }">
                            {{ alasanValid ? 'Tercatat di riwayat sampel sebagai alasan resampling'
                                           : 'Wajib diisi, minimal 5 karakter' }}
                        </div>
                    </template>

                    <div v-if="ujiUlang.err" class="vs-al vs-al--bad mt-3 mb-0">
                        <i class="ri-error-warning-line"></i><span>{{ ujiUlang.err }}</span>
                    </div>
                </div>
                <div class="vs-md-f">
                    <button class="btn btn-sm btn-light" :disabled="ujiUlang.kirim" @click="tutupUjiUlang">Batal</button>
                    <button class="btn btn-sm btn-warning text-white" :disabled="!bisaUjiUlang" @click="kirimUjiUlang">
                        <span v-if="ujiUlang.kirim" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-send-plane-line me-1"></i>Lakukan Uji Ulang
                    </button>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>

<script>
import axios from "axios";
import LifecycleSampel from "./components/LifecycleSampel.vue";
import TabelHasilAnalisa from "./components/TabelHasilAnalisa.vue";

const API = "/api/v1/sandbox/validasi-trial-produksi";

export default {
    name: "ValidasiSandbox",
    components: { LifecycleSampel, TabelHasilAnalisa },
    data() {
        return {
            // Antrean
            daftar: [],
            pagination: { page: 1, limit: 12, totalPage: 0, totalData: 0 },
            limit: 12,
            totalAntrean: 0,
            hak: [],
            cakupanMesin: [],
            dummy: { tersedia: false, skenario: {} },
            dummyProses: false,
            menuCth: false,
            pesanKosong: "",
            loading: false,
            galat: "",
            urutMuat: 0,

            cari: "",
            cariAktif: "",
            tundaCari: null,
            filter: { mulai: "", selesai: "", qrcode: "", status: "" },
            filterBuka: false,
            tundaFilter: null,

            // Pilihan validasi massal (kartu antrean).
            dipilih: [],

            // Sampel + klasifikasi terpilih
            terpilih: null,
            rinci: null,
            muatRincian: false,
            galatRincian: "",
            urutRincian: 0,
            paramBuka: true,
            simpan: false,

            fotoUrl: {},
            fotoGagal: {},
            galeri: { show: false, i: 0 },
            usapX: null,

            val: { show: false, satu: true, item: null, items: [], analisa: [], centang: {}, sub: {}, kirim: false, err: "" },
            ujiUlang: { show: false, id: "", asal: "", opsi: [], pilih: "", alasan: "", muatOpsi: false, kirim: false, err: "" },

            // Tata letak — sama dengan Verifikasi & Finalisasi sandbox.
            panelKanan: true,
            lcData: null,
            lebar: 0,
            gulirDaftar: 0,
            bawah: 0,
            lcLebar: 380,
            lcMaks: false,
            geser: false,
        };
    },
    computed: {
        tunggal() { return this.lebar > 0 && this.lebar < 768; },
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
        gayaKanan() {
            if (this.lcMaks || this.laci) return {};
            return { width: this.lcLebar + "px" };
        },
        kunciGulir() {
            return this.galeri.show || this.ujiUlang.show || this.val.show
                || (this.tunggal && this.panelKanan && !!this.terpilih);
        },

        tanggalAktif() { return !!(this.filter.mulai && this.filter.selesai); },
        jumlahFilter() {
            return (this.tanggalAktif ? 1 : 0) + (this.filter.qrcode ? 1 : 0) + (this.filter.status ? 1 : 0);
        },
        adaSaringan() { return !!this.cariAktif || this.jumlahFilter > 0; },
        dariKe() {
            const p = this.pagination;
            if (!p.totalData) return "0";
            const dari = (p.page - 1) * p.limit + 1;
            return `${dari}–${Math.min(dari + this.daftar.length - 1, p.totalData)} / ${p.totalData}`;
        },
        semuaDipilih() {
            return this.daftar.length > 0 && this.daftar.every((it) => this.dipilihKah(it));
        },
        sebagianDipilih() { return this.daftar.some((it) => this.dipilihKah(it)); },

        /** Jumlah jenis analisa di tabel (palatabilitas: beberapa baris per analisa). */
        jumlahAnalisa() { return this.rinci ? (this.rinci.daftar || []).length : 0; },

        /** Pengambil keputusan berikutnya — untuk kepala Sample Lifecycle. */
        keputusan() {
            if (!this.rinci || !this.terpilih) return null;
            return {
                klasifikasi: this.terpilih.Nama_Aktivitas,
                verifikasi: this.rinci.keputusan?.verifikasi || [],
                finalisasi: this.rinci.keputusan?.finalisasi || [],
            };
        },

        /**
         * Foto analisa, satu kartu per berkas, beserta konteks analisanya
         * (hasil, kelayakan, penginput) — sama dengan galeri Finalisasi.
         */
        foto() {
            const sudah = new Set();
            const hasil = [];
            ((this.rinci && this.rinci.analisa) || []).forEach((a) => {
                (a.Foto || []).forEach((f) => {
                    if (!f.key || sudah.has(f.key)) return;
                    sudah.add(f.key);
                    hasil.push({
                        key: f.key,
                        keterangan: f.keterangan && f.keterangan !== "Tidak Ada Keterangan" ? f.keterangan : "",
                        idAnalisa: a.Id_Jenis_Analisa,
                        analisa: a.Nama_Jenis_Analisa,
                        sampel: a.No_Sampel_Uji,
                        hasil: a.Nilai_Hasil_String || this.angka(a.Hasil),
                        layak: this.layakFoto(a),
                        input: a.Input,
                    });
                });
            });
            return hasil;
        },
        fotoAktif() { return this.foto[this.galeri.i] || null; },

        jumlahDicentang() {
            if (!this.val.show) return 0;
            if (!this.val.satu) return this.val.items.reduce((n, it) => n + (it.Jumlah_Analisa || 0), 0);
            return this.val.analisa.filter((a) => this.val.centang[a.id]).length;
        },
        adaTidakLayakMassal() { return this.val.items.some((it) => it.Jumlah_Tidak_Layak > 0); },

        ujiUlangAnalisa() {
            return ((this.rinci && this.rinci.daftar) || []).find((a) => a.id === this.ujiUlang.id) || null;
        },
        alasanValid() { return (this.ujiUlang.alasan || "").trim().length >= 5; },
        bisaUjiUlang() {
            const u = this.ujiUlang, a = this.ujiUlangAnalisa;
            return !!a && !u.kirim && !u.muatOpsi && this.alasanValid && !!u.asal && (!a.multi || !!u.pilih);
        },
    },
    watch: {
        kunciGulir(v) { document.body.style.overflow = v ? "hidden" : ""; },
        filter: {
            deep: true,
            handler() {
                clearTimeout(this.tundaFilter);
                this.tundaFilter = setTimeout(() => {
                    this.pagination.page = 1;
                    this.muat();
                }, 300);
            },
        },
    },
    async mounted() {
        this.panelKanan = this.pilihanKanan();
        this.ukurUlang(this.$refs.akar.getBoundingClientRect().width);
        this.pengamat = new ResizeObserver((e) => this.ukurUlang(e[0].contentRect.width));
        this.pengamat.observe(this.$refs.akar);
        this.muatLebar();
        window.addEventListener("keydown", this.tombolPintas);
        document.addEventListener("pointerdown", this.tutupMenuLuar);
        await this.muat();
    },
    beforeUnmount() {
        if (this.pengamat) this.pengamat.disconnect();
        clearTimeout(this.tundaCari);
        clearTimeout(this.tundaFilter);
        document.body.style.overflow = "";
        window.removeEventListener("keydown", this.tombolPintas);
        document.removeEventListener("pointerdown", this.tutupMenuLuar);
        if (this.geser) this.selesaiGeser();
        this.bersihkanFoto();
    },
    methods: {
        /* ---------- antrean ---------- */
        async muat() {
            const urut = ++this.urutMuat;
            this.loading = true;
            this.galat = "";
            try {
                const f = this.filter;
                const r = await axios.get(`${API}/daftar`, {
                    params: {
                        q: this.cariAktif || undefined,
                        page: this.pagination.page,
                        limit: this.limit,
                        tanggal_mulai: this.tanggalAktif ? f.mulai : undefined,
                        tanggal_selesai: this.tanggalAktif ? f.selesai : undefined,
                        qrcode: f.qrcode || undefined,
                        status: f.status || undefined,
                    },
                });
                if (urut !== this.urutMuat) return;
                const res = r.data?.result || {};
                this.daftar = res.data || [];
                this.pagination = res.pagination || this.pagination;
                this.totalAntrean = res.total_antrean ?? this.totalAntrean;
                this.hak = res.hak || [];
                this.cakupanMesin = res.cakupan_mesin || this.cakupanMesin;
                this.dummy = res.dummy || { tersedia: false, skenario: {} };
                this.pesanKosong = res.pesan || "";

                if (this.terpilih) {
                    const baru = this.daftar.find((d) => this.kunci(d) === this.kunci(this.terpilih));
                    if (baru) this.terpilih = baru;
                    else this.tutupTerpilih();
                }
                // Layar lebar: buka sampel pertama agar panel tengah tidak kosong.
                if (!this.terpilih && this.daftar.length && !this.tunggal) this.buka(this.daftar[0]);
            } catch (e) {
                if (urut !== this.urutMuat) return;
                this.daftar = [];
                const kode = e.response?.status;
                this.galat = (kode === 401 || kode === 419 || typeof e.response?.data === "string")
                    ? "Sesi Anda telah berakhir. Muat ulang halaman dan login kembali."
                    : (e.response?.data?.message || "Gagal memuat antrean validasi. Periksa koneksi lalu coba lagi.");
            } finally {
                if (urut === this.urutMuat) this.loading = false;
            }
        },
        cariTunda() {
            clearTimeout(this.tundaCari);
            if (!this.cari.trim()) { this.cariSekarang(); return; }
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
        resetFilter() { this.filter = { mulai: "", selesai: "", qrcode: "", status: "" }; },
        hapusSemuaSaringan() {
            this.cari = "";
            this.cariAktif = "";
            this.resetFilter();
        },
        keHal(n) { this.pagination.page = n; this.muat(); },

        kunci(it) { return it.No_Sampel + "|" + it.Kode_Aktivitas_Lab; },
        aktif(it) { return !!this.terpilih && this.kunci(this.terpilih) === this.kunci(it); },
        /** Warna aksen kartu: tidak layak > belum ada di master > layak. */
        nada(it) {
            if (it.Jumlah_Tidak_Layak > 0) return "bad";
            if (it.Jumlah_Tanpa_Master > 0) return "warn";
            return "ok";
        },
        dipilihKah(it) { return this.dipilih.some((d) => this.kunci(d) === this.kunci(it)); },
        togglePilih(it) {
            const i = this.dipilih.findIndex((d) => this.kunci(d) === this.kunci(it));
            if (i > -1) this.dipilih.splice(i, 1);
            else this.dipilih.push(it);
        },
        pilihSemua() {
            if (this.semuaDipilih) {
                const di = new Set(this.daftar.map(this.kunci));
                this.dipilih = this.dipilih.filter((d) => !di.has(this.kunci(d)));
            } else {
                this.daftar.forEach((it) => { if (!this.dipilihKah(it)) this.dipilih.push(it); });
            }
        },

        /* ---------- sampel terpilih ---------- */
        buka(it) {
            if (this.tunggal && !this.terpilih) this.gulirDaftar = window.scrollY;
            const sama = this.terpilih && this.kunci(this.terpilih) === this.kunci(it);
            this.terpilih = it;
            if (sama && this.rinci) return;
            this.muatRincian_();
            if (this.panelKanan) this.muatLifecycle(it.No_Sampel);
            if (this.tunggal) window.scrollTo({ top: 0 });
        },
        tutupTerpilih() {
            this.terpilih = null;
            this.rinci = null;
            this.lcData = null;
            this.urutRincian++;
            this.galeri.show = false;
            this.bersihkanFoto();
        },
        kembali() {
            this.terpilih = null;
            this.tutupKanan();
            this.$nextTick(() => window.scrollTo({ top: this.gulirDaftar }));
        },

        /** Rincian — tabel sama dengan Verifikasi, disusun di server. */
        async muatRincian_() {
            const it = this.terpilih;
            if (!it) return;
            const urut = ++this.urutRincian;
            this.rinci = null;
            this.muatRincian = true;
            this.galatRincian = "";
            this.galeri.show = false;
            this.bersihkanFoto();
            try {
                const r = await axios.get(`${API}/rincian`, {
                    params: { no_sampel: it.No_Sampel, klasifikasi: it.Kode_Aktivitas_Lab },
                });
                if (urut !== this.urutRincian) return;
                this.rinci = r.data?.result || null;
                this.muatFoto();
            } catch (e) {
                if (urut !== this.urutRincian) return;
                this.galatRincian = e.response?.data?.message || "Gagal memuat hasil uji. Coba lagi.";
            } finally {
                if (urut === this.urutRincian) this.muatRincian = false;
            }
        },

        /* ---------- validasi ---------- */
        /** Satu sampel: pilih analisa. Beberapa kartu: seluruh analisanya. */
        bukaValidasi(items) {
            const satu = items.length === 1 && this.terpilih && this.kunci(items[0]) === this.kunci(this.terpilih) && !!this.rinci;
            const analisa = satu ? this.rinci.daftar || [] : [];
            const centang = {}, sub = {};
            analisa.forEach((a) => { centang[a.id] = true; sub[a.id] = ""; });
            this.val = { show: true, satu, item: items[0], items: [...items], analisa, centang, sub, kirim: false, err: "" };
        },
        tutupValidasi() { if (!this.val.kirim) this.val.show = false; },
        async kirimValidasi() {
            const v = this.val;
            v.kirim = true;
            v.err = "";
            try {
                const items = v.satu
                    ? [{
                        No_Sampel: v.item.No_Sampel,
                        Kode_Aktivitas_Lab: v.item.Kode_Aktivitas_Lab,
                        analisa: v.analisa.filter((a) => v.centang[a.id]).map((a) => a.id),
                        sub: Object.fromEntries(Object.entries(v.sub).filter(([, s]) => s)),
                    }]
                    : v.items.map((it) => ({ No_Sampel: it.No_Sampel, Kode_Aktivitas_Lab: it.Kode_Aktivitas_Lab }));
                const r = await axios.post(`${API}/validasi`, { items });
                v.kirim = false;
                v.show = false;
                const tervalidasi = new Set(v.satu ? [] : v.items.map(this.kunci));
                this.dipilih = this.dipilih.filter((d) => !tervalidasi.has(this.kunci(d)));
                this.kabar("success", "Tervalidasi", r.data?.message || "");
                this.segarkanSetelahAksi();
            } catch (e) {
                v.err = e.response?.data?.message || e.message || "Gagal menyimpan.";
                v.kirim = false;
            }
        },

        /* ---------- uji ulang ---------- */
        bukaUjiUlang() {
            const daftar = (this.rinci && this.rinci.daftar) || [];
            // Bawaan: analisa tidak layak pertama, bila ada.
            const awal = daftar.find((a) => a.layak === "T") || daftar[0];
            this.ujiUlang = { show: true, id: awal ? awal.id : "", asal: "", opsi: [], pilih: "", alasan: "",
                              muatOpsi: false, kirim: false, err: "" };
            this.pilihAnalisaUlang();
        },
        async pilihAnalisaUlang() {
            const u = this.ujiUlang, a = this.ujiUlangAnalisa, it = this.terpilih;
            u.opsi = [];
            u.pilih = "";
            u.err = "";
            if (!a) return;
            u.asal = a.multi ? (a.sub[0] || "") : it.No_Sampel;
            if (!a.multi) return;
            u.muatOpsi = true;
            const id = a.id;
            try {
                const r = await axios.get(`/api/v1/lab/laboratorium/no-uji/sampel/sub/all/${it.No_Sampel}/${id}`);
                if (this.ujiUlang.id !== id) return;
                u.opsi = (r.data?.result || []).map((x) => x.No_Po_Multi);
            } catch {
                u.err = "Gagal memuat sub sampel. Pilih ulang analisanya.";
            } finally {
                if (this.ujiUlang.id === id) u.muatOpsi = false;
            }
        },
        tutupUjiUlang() { if (!this.ujiUlang.kirim) this.ujiUlang.show = false; },
        /** Endpoint resampling modul lama, isi permintaan sama persis. */
        async kirimUjiUlang() {
            const it = this.terpilih, u = this.ujiUlang, a = this.ujiUlangAnalisa;
            u.kirim = true;
            u.err = "";
            try {
                const alasan = u.alasan.trim();
                const r = a.multi
                    ? await axios.post("/api/v1/lab/resampeling/reanalisis", {
                        No_Po_Sampel: it.No_Sampel,
                        No_Sampel_Resampling_Origin: u.asal,
                        No_Sampel_Resampling: u.pilih,
                        Id_Jenis_Analisa: a.id,
                        Alasan: alasan,
                    })
                    : await axios.post("/api/v1/lab/resampling-single/reanalisis", {
                        No_Po_Sampel: it.No_Sampel,
                        No_Sampel: u.asal,
                        Id_Jenis_Analisa: a.id,
                        Alasan: alasan,
                    });
                if (!r.data?.success) throw new Error(r.data?.message || "Gagal menyimpan.");
                u.kirim = false;
                u.show = false;
                this.kabar("success", "Uji ulang diminta", `${a.nama} dikembalikan untuk diuji ulang.`);
                this.segarkanSetelahAksi();
            } catch (e) {
                u.err = e.response?.data?.message || e.message || "Gagal menyimpan.";
                u.kirim = false;
            }
        },

        /**
         * Setelah validasi / uji ulang: antrean disegarkan. Sampel yang
         * selesai keluar dan sampel berikutnya terbuka; bila masih ada
         * analisa yang menunggu, rincian dan lifecycle-nya dimuat ulang.
         */
        async segarkanSetelahAksi() {
            const lama = this.terpilih && this.kunci(this.terpilih);
            await this.muat();
            const it = this.terpilih;
            if (!it || this.kunci(it) !== lama) return;
            this.muatRincian_();
            if (this.panelKanan) this.muatLifecycle(it.No_Sampel);
        },

        /* ---------- contoh data ---------- */
        tutupMenuLuar(e) {
            if (this.menuCth && this.$refs.menuCth && !this.$refs.menuCth.contains(e.target)) this.menuCth = false;
        },
        async buatDummy(skenario) {
            this.menuCth = false;
            this.dummyProses = true;
            try {
                const r = await axios.post(`${API}/sampel-dummy`, { skenario });
                this.kabar("success", "Contoh data dibuat", r.data?.message || "");
                this.pagination.page = 1;
                await this.muat();
            } catch (e) {
                this.kabar("error", "Gagal membuat contoh data", e.response?.data?.message || e.message);
            } finally {
                this.dummyProses = false;
            }
        },
        async hapusDummy() {
            this.menuCth = false;
            const ya = await this.tanya("Hapus semua contoh data?",
                "Seluruh sampel contoh beserta jejak validasi, verifikasi, dan finalisasinya dihapus dari database demo.",
                "Ya, hapus", "#f06548");
            if (!ya) return;
            this.dummyProses = true;
            try {
                const r = await axios.post(`${API}/sampel-dummy/hapus`);
                this.kabar("success", "Contoh data dihapus", r.data?.message || "");
                this.tutupTerpilih();
                this.dipilih = [];
                this.pagination.page = 1;
                await this.muat();
            } catch (e) {
                this.kabar("error", "Gagal menghapus", e.response?.data?.message || e.message);
            } finally {
                this.dummyProses = false;
            }
        },

        /** Notifikasi & konfirmasi memakai SweetAlert layout, seperti modul lama. */
        kabar(ikon, judul, teks) {
            const S = window.Swal;
            if (S) {
                S.fire({ icon: ikon, title: judul, text: teks || "",
                         timer: ikon === "success" ? 2600 : undefined,
                         showConfirmButton: ikon !== "success" });
            } else {
                window.alert(judul + (teks ? "\n" + teks : ""));
            }
        },
        async tanya(judul, teks, tombol, warna = "#0ab39c") {
            const S = window.Swal;
            if (!S) return window.confirm(judul + "\n" + teks);
            const r = await S.fire({ title: judul, text: teks, icon: "question", showCancelButton: true,
                                     confirmButtonText: tombol, cancelButtonText: "Batal",
                                     confirmButtonColor: warna, reverseButtons: true });
            return !!r.isConfirmed;
        },

        /* ---------- foto ---------- */
        /**
         * Token foto berlaku 30 detik dan sekali pakai, sedangkan satu foto
         * dari GCS butuh ±2 detik: token diminta per kelompok kecil tepat
         * sebelum diunduh (paralel) dan sekali diulang dengan token baru —
         * meminta semua token di awal membuat antrean belakang kedaluwarsa.
         */
        async muatFoto() {
            const kunci = this.foto.map((f) => f.key).filter((k) => !this.fotoUrl[k]);
            if (!kunci.length) return;
            const urut = this.urutRincian;
            const token = async (keys) => {
                try { return (await axios.post("/api/v1/lab/hasil-uji/berkas/foto/token/bulk", { keys })).data || {}; } catch { return {}; }
            };
            const unduh = async (k, t) => {
                const r = await axios.get(`/api/v1/lab/berkas/stream/foto-uji/${k}`, { params: { token: t }, responseType: "blob" });
                if (urut === this.urutRincian) this.fotoUrl = { ...this.fotoUrl, [k]: URL.createObjectURL(r.data) };
            };
            for (let i = 0; i < kunci.length; i += 4) {
                if (urut !== this.urutRincian) return;
                const bagian = kunci.slice(i, i + 4);
                const tok = await token(bagian);
                await Promise.all(bagian.map(async (k) => {
                    try {
                        await unduh(k, tok[k]);
                    } catch {
                        try { await unduh(k, (await token([k]))[k]); } catch {
                            if (urut === this.urutRincian) this.fotoGagal = { ...this.fotoGagal, [k]: true };
                        }
                    }
                }));
            }
        },
        bersihkanFoto() {
            Object.values(this.fotoUrl).forEach((u) => URL.revokeObjectURL(u));
            this.fotoUrl = {};
            this.fotoGagal = {};
        },
        /** Dari penanda kamera di tabel: buka foto pertama analisa itu. */
        bukaFotoAnalisa(a) {
            const i = this.foto.findIndex((f) => f.idAnalisa === a.Id_Jenis_Analisa && f.sampel === a.No_Sampel_Uji);
            this.bukaGaleri(i > -1 ? i : 0);
        },
        bukaGaleri(i) { if (this.foto.length) this.galeri = { show: true, i }; },
        tutupGaleri() { this.galeri.show = false; },
        geserGaleri(d) {
            const n = this.foto.length;
            if (n) this.galeri.i = (this.galeri.i + d + n) % n;
        },
        mulaiUsap(e) { this.usapX = e.changedTouches[0].clientX; },
        akhiriUsap(e) {
            if (this.usapX === null) return;
            const dx = e.changedTouches[0].clientX - this.usapX;
            this.usapX = null;
            if (Math.abs(dx) > 50) this.geserGaleri(dx < 0 ? 1 : -1);
        },
        bukaTab(url) { if (url) window.open(url, "_blank"); },

        /* ---------- tata letak (sama dengan Finalisasi sandbox) ---------- */
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
            if (this.menuCth) this.menuCth = false;
            else if (this.ujiUlang.show) this.tutupUjiUlang();
            else if (this.val.show) this.tutupValidasi();
            else if (this.lcMaks) this.lcMaks = false;
            else if (this.laci && this.panelKanan) this.tutupKanan();
        },
        toggleKanan() {
            this.panelKanan = !this.panelKanan;
            if (!this.panelKanan) this.lcMaks = false;
            if (this.panelKanan && this.terpilih) this.muatLifecycle(this.terpilih.No_Sampel);
            this.simpanPilihanKanan();
        },
        tutupKanan() { this.panelKanan = false; this.lcMaks = false; this.simpanPilihanKanan(); },
        pilihanKanan() {
            try { return localStorage.getItem("vs-lc-buka") !== "0"; } catch (e) { return true; }
        },
        simpanPilihanKanan() {
            if (this.laci) return;
            try { localStorage.setItem("vs-lc-buka", this.panelKanan ? "1" : "0"); } catch (e) { /* abaikan */ }
        },
        /** Jejak sampel — endpoint yang sama dengan Verifikasi & Finalisasi. */
        async muatLifecycle(noSampel) {
            this.lcData = "loading";
            try {
                const r = await axios.get(`/api/v1/verifikasi-hasil-analisa/lifecycle/${noSampel}`,
                    { params: { semua: 1 } });
                if (this.terpilih && this.terpilih.No_Sampel !== noSampel) return;
                this.lcData = r.data?.result || null;
            } catch {
                this.lcData = null;
            }
        },
        batasLebar(px) {
            const maks = Math.max(340, this.lebar - this.lebarKiri() - 440);
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
        lebarBawaan() { return this.lebar >= 1440 ? 380 : 340; },
        simpanLebar() {
            try { localStorage.setItem("vs-lc-lebar", String(this.lcLebar)); } catch (e) { /* abaikan */ }
        },
        muatLebar() {
            try {
                const n = parseInt(localStorage.getItem("vs-lc-lebar"), 10);
                this.lcLebar = this.batasLebar(isNaN(n) ? this.lebarBawaan() : n);
            } catch (e) { this.lcLebar = this.batasLebar(this.lebarBawaan()); }
        },

        /* ---------- tampilan ---------- */
        ikonAkt(k) {
            return { ANL: "ri-flask-line", LCKV: "ri-eye-line", PLT: "ri-heart-pulse-line" }[k] || "ri-test-tube-line";
        },
        /** Dua nama pertama, sisanya sebagai jumlah: "ASH, PROTEIN +2". */
        ringkasNama(daftar) {
            const n = (daftar || []).length;
            return daftar.slice(0, 2).join(", ") + (n > 2 ? ` +${n - 2}` : "");
        },
        labelLayak(l) { return { Y: "Layak", T: "Tidak layak", N: "Belum dinilai" }[l] || "-"; },
        nadaLayak(l) { return { Y: "ok", T: "bad", N: "warn" }[l] || "ok"; },
        /** Label kelayakan kartu foto — aturannya sama dengan tabel. */
        layakFoto(a) {
            if (a.Dasar_Kelayakan === "TANPA_MASTER" || a.Dasar_Kelayakan === "KRITERIA_TIDAK_COCOK") {
                return { label: "Belum dinilai", cls: "bg-warning-subtle text-warning" };
            }
            return a.Flag_Layak === "T"
                ? { label: "Tidak layak", cls: "bg-danger-subtle text-danger" }
                : { label: "Layak", cls: "bg-success-subtle text-success" };
        },
        angka(v) {
            if (v === null || v === undefined || v === "") return "-";
            const n = Number(v);
            if (isNaN(n)) return v;
            return String(Math.round(n * 10000) / 10000).replace(".", ",");
        },
        /** Format tetap: 24 Sep 2026 09:49 — tanggal diurai dari YYYY-MM-DD. */
        stamp(tgl, jam) {
            if (!tgl || tgl === "—") return "-";
            const [y, m, d] = String(tgl).slice(0, 10).split("-").map(Number);
            if (!y || !m || !d) return String(tgl);
            const bln = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            const t = `${String(d).padStart(2, "0")} ${bln[m - 1]} ${y}`;
            return jam ? `${t} ${String(jam).slice(0, 5)}` : t;
        },
    },
};
</script>

<style scoped>
/* Kerangka tata letak — sama dengan Finalisasi sandbox. */
.vs { display: flex; flex-direction: column;
    height: calc(100vh - 70px); height: calc(100dvh - 70px);
    overflow: hidden; background: #f3f3f9; margin: -12px -12px 0;
    --lebar-kiri: 320px; }
.vs.uk-lg { --lebar-kiri: 280px; }
.vs.uk-md { --lebar-kiri: 250px; }

/* TOPBAR */
.vs-top { display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px 16px; padding: 12px 18px;
    background: #fff; border-bottom: 1px solid #e9ebec; flex-shrink: 0; }
.vs-top-l { min-width: 0; flex: 1 1 240px; }
.vs-top-t { margin: 0; font-size: 1.05rem; font-weight: 600; color: #405189; }
.vs-top-s { margin: 2px 0 0; font-size: .74rem; color: #878a99; }
.vs-top-r { display: flex; align-items: center; justify-content: flex-end; gap: 8px 10px;
    flex-wrap: wrap; min-width: 0; max-width: 100%; }
.vs-top-a { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.vs-sandbox { font-size: .66rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; color: #b8891b; background: #fff8e6;
    border: 1px solid #f5e0a3; border-radius: 4px; padding: 3px 8px; }
.vs-cakupan { display: inline-flex; align-items: center; font-size: .68rem; font-weight: 700;
    color: #405189; background: #eef1fb; border: 1px solid #dde3f4;
    border-radius: 4px; padding: 3px 9px; }

.vs-ico { border: 1px solid #e9ebec; background: #fff; border-radius: 5px;
    width: 30px; height: 30px; color: #878a99; cursor: pointer; flex-shrink: 0; }

/* BODY — acuan laci dan panel yang diperbesar. */
.vs-body { display: flex; flex: 1; min-height: 0; overflow: hidden; position: relative; }

/* KIRI */
.vs-left { width: var(--lebar-kiri); min-width: 0; display: flex; flex-direction: column;
    background: #fff; border-right: 1px solid #e9ebec; overflow: hidden; flex-shrink: 0; }
/* Jumlah antrean */
.vs-lhead { padding: 6px 12px; font-size: .66rem; color: #878a99; border-bottom: 1px solid #f3f3f9;
    background: #fcfcfd; }
.vs-lhead b { color: #495057; font-weight: 700; }
.vs-list { flex: 1; overflow-y: auto; }

/* Pagination */
.vs-pg { display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 8px 12px; border-top: 1px solid #e9ebec; font-size: .68rem; color: #878a99;
    background: #fff; flex-shrink: 0; }
.vs-pg-b { display: flex; align-items: center; gap: 5px; }
.vs-pg-b button { border: 1px solid #e9ebec; background: #fff; border-radius: 4px;
    width: 24px; height: 24px; color: #495057; cursor: pointer; }
.vs-pg-b button:disabled { opacity: .4; cursor: default; }
.vs-pg select { width: auto; font-size: .68rem; padding: 2px 20px 2px 6px; }

/* TENGAH */
.vs-center { flex: 1; min-width: 0; display: flex; flex-direction: column;
    overflow: hidden; background: #f3f3f9; }
.vs-center-empty { flex: 1; }
.vs-dh { display: flex; align-items: flex-start; gap: 10px 12px;
    padding: 13px 16px; background: #fff; border-bottom: 1px solid #e9ebec; flex-shrink: 0; }
.vs-back { background: #f3f6f9; border: none; border-radius: 6px; width: 34px; height: 34px;
    flex-shrink: 0; cursor: pointer; color: #495057; }
.vs-dh-l { flex: 1; min-width: 0; }
.vs-dh-t { display: flex; align-items: center; gap: 6px 8px; flex-wrap: wrap; }
.vs-dh-t b { font-family: ui-monospace, monospace; font-size: .95rem; color: #405189; }
/* Kolom meta menyesuaikan lebar: tertata sebagai grid, tidak patah acak. */
.vs-dh-meta { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px 18px; margin: 9px 0 0; }
.vs-dh-meta > div { min-width: 0; }
.vs-dh-meta dt { font-size: .6rem; text-transform: uppercase; letter-spacing: .05em;
    color: #adb5bd; font-weight: 600; margin: 0; }
.vs-dh-meta dd { font-size: .76rem; color: #495057; font-weight: 600; margin: 1px 0 0;
    overflow-wrap: anywhere; }
.vs-mesin { color: #405189; }
.vs-dh-kd { display: block; margin-top: 1px; font-family: ui-monospace, monospace; font-size: .64rem;
    font-weight: 400; color: #878a99; }
.vs-lcbtn { border: 1px solid #e9ebec; background: #fff; border-radius: 5px; gap: 5px;
    padding: 5px 11px; font-size: .72rem; font-weight: 600; color: #878a99; flex-shrink: 0;
    cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; }
.vs-lcbtn.is-on { background: #405189; border-color: #405189; color: #fff; }

.vs-dbody { flex: 1; overflow-y: auto; padding: 14px 16px; }
.vs-lb { font-size: .74rem; font-weight: 600; color: #495057; margin-bottom: 8px; display: block; }

/* Widget angka — petak melebar mengisi baris, sehingga baris terakhir
   yang tidak penuh tidak menyisakan lubang. */
.vs-w { display: flex; flex-wrap: wrap; gap: 1px;
    background: #e9ebec; border-radius: 7px; overflow: hidden; margin-bottom: 14px; }
.vs-w-i { flex: 1 1 100px; background: #fff; padding: 10px 13px; min-width: 0; }
.vs-w-n { display: block; font-size: 1.35rem; font-weight: 700; color: #405189; line-height: 1.1; }
.vs-w-n.is-ok   { color: #0ab39c; }
.vs-w-n.is-warn { color: #f7b84b; }
.vs-w-n.is-bad  { color: #f06548; }
.vs-w-l { font-size: .62rem; color: #adb5bd; text-transform: uppercase; letter-spacing: .04em; }

.vs-dfoot { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
    gap: 10px 12px; padding: 11px 16px; background: #fff; border-top: 1px solid #e9ebec; flex-shrink: 0; }
.vs-dfoot-i { font-size: .68rem; color: #adb5bd; }

/* PEMISAH */
.vs-split { width: 5px; flex-shrink: 0; cursor: col-resize; background: #e9ebec;
    position: relative; transition: background .15s; }
.vs-split:hover, .vs-split.is-drag { background: #405189; }
.vs-split::before { content: ""; position: absolute; inset: 0 -4px; }
.vs-split-grip { position: absolute; top: 50%; left: 50%; width: 3px; height: 30px;
    transform: translate(-50%, -50%); border-radius: 2px; background: #adb5bd; }
.vs-split:hover .vs-split-grip, .vs-split.is-drag .vs-split-grip { background: #fff; }

/* KANAN */
.vs-right { width: 380px; min-width: 0; display: flex; flex-direction: column;
    background: #fff; border-left: 1px solid #e9ebec; overflow: hidden; flex-shrink: 0; }
.vs-right.is-max { position: absolute; inset: 0; width: auto !important;
    z-index: 30; border-left: none; box-shadow: 0 0 0 100vmax rgba(0,0,0,.12); }
.vs-rh { display: flex; align-items: center; justify-content: space-between;
    padding: 11px 14px; background: #405189; color: #fff;
    font-size: .82rem; font-weight: 600; flex-shrink: 0; }
.vs-rh-act { display: flex; gap: 6px; }
.vs-rh-x { background: rgba(255,255,255,.18); border: none; color: #fff;
    border-radius: 4px; padding: 3px 7px; cursor: pointer; }
.vs-rb { flex: 1; overflow-y: auto; padding: 13px 14px 30px; }
.vs-rb-sub { display: flex; align-items: center; gap: 6px; margin: 16px 0 10px;
    font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #878a99; }
.vs-rb-sub i { font-size: .8rem; }

/* Laci: panel kanan mengambang di atas detail (dua panel). */
.vs-right.is-laci { position: absolute; top: 0; right: 0; bottom: 0; z-index: 40;
    width: min(420px, 100%) !important; border-left: none; box-shadow: -12px 0 32px rgba(0,0,0,.16); }
.vs-right.is-laci.is-max { width: 100% !important; box-shadow: none; }
.vs-laci-bd { position: absolute; inset: 0; z-index: 35; background: rgba(33,37,41,.28); }
/* Lembar layar penuh (satu panel) — sudah dipindah ke <body>. Di atas
   navigasi bawah aplikasi (z-index 1055), di bawah menu sampingnya (1060). */
.vs-right.is-lembar { position: fixed; inset: 0; z-index: 1058; width: 100% !important;
    border-left: none; box-shadow: none; }
.vs-right.is-lembar .vs-rb { padding-bottom: calc(30px + env(safe-area-inset-bottom)); }
.vs-geser-enter-active.is-laci, .vs-geser-leave-active.is-laci,
.vs-geser-enter-active.is-lembar, .vs-geser-leave-active.is-lembar { transition: transform .22s ease; }
.vs-geser-enter-from.is-laci, .vs-geser-leave-to.is-laci,
.vs-geser-enter-from.is-lembar, .vs-geser-leave-to.is-lembar { transform: translateX(100%); }
.vs-pudar-enter-active, .vs-pudar-leave-active { transition: opacity .2s; }
.vs-pudar-enter-from, .vs-pudar-leave-to { opacity: 0; }

/* Lightbox foto — sudah dipindah ke <body>. */
.vs-zm { position: fixed; inset: 0; z-index: 10060; background: rgba(15,17,20,.82);
    display: flex; align-items: center; justify-content: center; padding: 24px; }
.vs-zm-box { width: 100%; max-width: 980px; max-height: 94vh; display: flex; flex-direction: column;
    background: #1b1e22; border-radius: 9px; overflow: hidden; box-shadow: 0 18px 50px rgba(0,0,0,.4); }
.vs-zm-top { display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 14px; color: #e9ecef; border-bottom: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }
.vs-zm-ttl { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px; min-width: 0; font-size: .82rem; }
.vs-zm-ttl i { color: #38bdf8; }
.vs-zm-ttl code { font-size: .7rem; color: #adb5bd; background: rgba(255,255,255,.07);
    padding: 1px 6px; border-radius: 3px; }
.vs-zm-act { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.vs-zm-n { font-size: .7rem; color: #adb5bd; margin-right: 4px; }
.vs-zm-b { background: rgba(255,255,255,.1); border: none; color: #fff; border-radius: 5px;
    width: 30px; height: 30px; cursor: pointer; }
.vs-zm-b:hover { background: rgba(255,255,255,.2); }
.vs-zm-stage { position: relative; flex: 1; min-height: 240px; display: flex;
    align-items: center; justify-content: center; background: #0f1114; }
.vs-zm-stage img { max-width: 100%; max-height: 66vh; object-fit: contain; display: block; }
.vs-zm-x { display: flex; flex-direction: column; align-items: center; gap: 6px;
    color: #868e96; font-size: .78rem; }
.vs-zm-x i { font-size: 2rem; }
.vs-zm-nav { position: absolute; top: 50%; transform: translateY(-50%);
    width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer;
    background: rgba(255,255,255,.14); color: #fff; font-size: 1.3rem; }
.vs-zm-nav:hover { background: rgba(255,255,255,.26); }
.vs-zm-nav.is-prev { left: 12px; }
.vs-zm-nav.is-next { right: 12px; }
.vs-zm-cap { display: flex; flex-wrap: wrap; gap: 10px 26px; padding: 11px 16px 13px;
    color: #dee2e6; font-size: .74rem; border-top: 1px solid rgba(255,255,255,.08); flex-shrink: 0; }
.vs-zm-ci { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.vs-zm-ci small { font-size: .6rem; text-transform: uppercase; letter-spacing: .06em; color: #868e96; }
.vs-zm-ci b { color: #fff; font-family: ui-monospace, monospace; }
.vs-zm-ci em { font-style: normal; font-family: ui-monospace, monospace; font-size: .68rem;
    color: #adb5bd; margin-left: 4px; }
.vs-zm-none { color: #868e96; font-style: italic; }

/* MODAL — sudah dipindah ke <body>. */
.vs-bd { position: fixed; inset: 0; background: rgba(33,37,41,.45); z-index: 10070;
    display: flex; align-items: center; justify-content: center; padding: 20px; }
.vs-md { background: #fff; border-radius: 8px; width: 100%; max-width: 620px;
    max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
.vs-md-h { display: flex; align-items: center; gap: 11px; padding: 13px 16px; color: #fff; }
.vs-md-h i { font-size: 1.2rem; }
.vs-md-h b { display: block; font-size: .88rem; }
.vs-md-h em { font-style: normal; font-size: .7rem; opacity: .85; }
.vs-md-x { margin-left: auto; background: rgba(255,255,255,.2); border: none;
    color: #fff; border-radius: 4px; padding: 3px 7px; cursor: pointer; }
.vs-md-b { flex: 1; overflow-y: auto; padding: 16px; }
.vs-md-f { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px;
    background: #f8f9fc; border-top: 1px solid #e9ebec; }

.vs-count { font-size: .68rem; color: #f06548; text-align: right; margin-top: 4px; font-weight: 600; }
.vs-count.is-ok { color: #0ab39c; }

/* Umum */
.vs-state { display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 7px; padding: 40px 20px; color: #878a99; font-size: .78rem; }
.vs-state i { font-size: 1.8rem; color: #ced4da; }
.vs-state .btn i { font-size: inherit; color: inherit; }
.vs-state p { margin: 0; }
.vs-state small { color: #adb5bd; font-size: .72rem; }

.vs-al { display: flex; gap: 9px; align-items: flex-start; border-radius: 6px;
    padding: 10px 13px; font-size: .74rem; margin-bottom: 14px; }
.vs-al i { font-size: 1rem; margin-top: 1px; flex-shrink: 0; }
.vs-al--warn { background: #fff8e6; border: 1px solid #f5e0a3; color: #8a6d1f; }
.vs-al--bad  { background: #fde4df; border: 1px solid #f7c4b8; color: #b03826; }

/* ===================== SATU PANEL (< 768px) ===================== */
/* Halaman mengikuti guliran dokumen; daftar dan detail bergantian,
   panel kanan tampil layar penuh. */
.vs.is-tunggal { height: auto; min-height: calc(100vh - 70px); min-height: calc(100dvh - 70px);
    overflow: visible; }
.vs.is-tunggal .vs-top { padding: 10px 12px; }
.vs.is-tunggal .vs-top-s { display: none; }
.vs.is-tunggal .vs-top-r { flex: 1 1 100%; justify-content: flex-start; }
.vs.is-tunggal .vs-body { display: block; overflow: visible; }
.vs.is-tunggal .is-sembunyi { display: none !important; }
.vs.is-tunggal .vs-left { width: 100%; border-right: none; overflow: visible; }
.vs.is-tunggal .vs-list { overflow: visible; }
.vs.is-tunggal .vs-pg { position: sticky; bottom: var(--bawah, 0px); z-index: 3; }
.vs.is-tunggal .vs-center { overflow: visible; min-height: calc(100dvh - 150px); }
.vs.is-tunggal .vs-dh { padding: 10px 12px; }
.vs.is-tunggal .vs-dh-meta { grid-template-columns: repeat(auto-fill, minmax(115px, 1fr)); gap: 8px 12px; }
.vs.is-tunggal .vs-lcbtn { padding: 0; width: 34px; height: 34px; justify-content: center; }
.vs.is-tunggal .vs-lcbtn-t, .vs.is-tunggal .vs-lcbtn-c { display: none; }
.vs.is-tunggal .vs-dbody { overflow: visible; padding: 12px; }
.vs.is-tunggal .vs-w-i { flex-basis: 90px; padding: 9px 10px; }
.vs.is-tunggal .vs-w-n { font-size: 1.15rem; }
.vs.is-tunggal .vs-w-l { font-size: .56rem; letter-spacing: .02em; overflow-wrap: anywhere; }
/* Tombol aksi selalu terjangkau di bawah layar, di atas navigasi bawah
   aplikasi. */
.vs.is-tunggal .vs-dfoot { position: sticky; bottom: var(--bawah, 0px); z-index: 5; padding: 10px 12px;
    padding-bottom: calc(10px + env(safe-area-inset-bottom)); box-shadow: 0 -6px 16px rgba(0,0,0,.06); }
.vs.is-tunggal .vs-dfoot-i { display: none; }
.vs.is-tunggal .vs-dfoot .btn { flex: 1; padding-top: 9px; padding-bottom: 9px; }

/* Perangkat sentuh: sasaran ketuk lebih besar. */
@media (pointer: coarse) {
    .vs-ico { width: 36px; height: 36px; }
    .vs-pg-b button { width: 32px; height: 32px; }
    .vs-rh-x { padding: 6px 10px; }
    .vs-zm-b { width: 38px; height: 38px; }
    .vs-zm-nav { width: 44px; height: 44px; }
}

/* HP: lightbox layar penuh, modal menjadi lembar dari bawah. */
@media (max-width: 767px) {
    .vs-zm { padding: 0; }
    .vs-zm-box { max-width: 100%; height: 100%; max-height: 100%; border-radius: 0; }
    .vs-zm-stage { min-height: 0; }
    .vs-zm-stage img { max-height: 100%; }
    .vs-zm-nav.is-prev { left: 8px; }
    .vs-zm-nav.is-next { right: 8px; }
    .vs-zm-cap { max-height: 38vh; overflow-y: auto; gap: 8px 18px;
        padding-bottom: calc(13px + env(safe-area-inset-bottom)); }

    .vs-bd { padding: 0; align-items: flex-end; }
    .vs-md { max-width: 100%; max-height: 94vh; max-height: 94dvh; border-radius: 14px 14px 0 0; }
    .vs-md-b { padding: 14px; }
    .vs-md-f { padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); }
    .vs-md-f .btn { flex: 1; padding-top: 9px; padding-bottom: 9px; }
}

/* ===================== KHUSUS VALIDASI ===================== */

/* Topbar: hak validasi akun & menu contoh data */
.vs-hak { display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 600;
    color: #495057; background: #f3f6f9; border: 1px solid #e9ebec; border-radius: 4px; padding: 3px 8px; }
.vs-hak i { color: #405189; }
.vs-hak em { font-style: normal; font-size: .62rem; color: #878a99; font-weight: 700;
    background: #fff; border-radius: 8px; padding: 0 5px; }
.vs-cth { position: relative; }
.vs-dummy { display: inline-flex; align-items: center; gap: 5px; border: 1px dashed #0ab39c;
    background: #eefaf6; color: #0c7a68; border-radius: 5px; padding: 4px 8px 4px 10px; font-size: .7rem;
    font-weight: 600; cursor: pointer; white-space: nowrap; }
.vs-dummy:hover:not(:disabled) { background: #d9f3ee; }
.vs-dummy:disabled { opacity: .6; cursor: default; }
.vs-cth-m { position: absolute; right: 0; top: calc(100% + 6px); z-index: 50; width: 290px;
    background: #fff; border: 1px solid #e9ebec; border-radius: 8px; padding: 6px;
    box-shadow: 0 12px 30px rgba(33,37,41,.14); display: flex; flex-direction: column; gap: 2px; }
.vs-cth-h { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
    color: #adb5bd; padding: 5px 8px 4px; }
.vs-cth-i { display: flex; flex-direction: column; gap: 1px; text-align: left; border: none; background: none;
    border-radius: 5px; padding: 7px 9px; cursor: pointer; }
.vs-cth-i:hover { background: #f3f6f9; }
.vs-cth-i b { font-size: .74rem; color: #495057; }
.vs-cth-i em { font-style: normal; font-size: .64rem; color: #878a99; line-height: 1.35; }
.vs-cth-i.is-semua { border-top: 1px solid #f3f3f9; margin-top: 3px; padding-top: 9px; }
.vs-cth-i.is-semua b { color: #0c7a68; }
.vs-cth-i.is-hapus b { color: #f06548; }

/* Pencarian + tombol saring */
.vs-search { display: flex; gap: 6px; padding: 11px 12px; border-bottom: 1px solid #f3f3f9; }
.vs-search-i { position: relative; flex: 1; min-width: 0; }
.vs-search-i i { position: absolute; left: 9px; top: 50%; transform: translateY(-50%);
    color: #adb5bd; font-size: .85rem; pointer-events: none; }
.vs-search-i input { padding-left: 28px; font-size: .78rem; }
.vs-search-i input::-webkit-search-cancel-button { cursor: pointer; }
.vs-fbtn { position: relative; flex-shrink: 0; width: 31px; border: 1px solid #ced4da; background: #fff;
    border-radius: 4px; color: #878a99; cursor: pointer; }
.vs-fbtn.is-on { border-color: #405189; color: #405189; background: #eef1fb; }
.vs-fbtn-n { position: absolute; top: -6px; right: -6px; min-width: 16px; height: 16px; border-radius: 8px;
    background: #405189; color: #fff; font-size: .58rem; font-weight: 700; line-height: 16px; padding: 0 3px; }
.vs-filter { padding: 9px 12px 11px; border-bottom: 1px solid #f3f3f9; background: #fcfcfd; }
.vs-f-l { display: block; font-size: .6rem; text-transform: uppercase; letter-spacing: .05em;
    color: #adb5bd; font-weight: 600; margin-bottom: 4px; }
.vs-f-tgl { display: flex; align-items: center; gap: 5px; }
.vs-f-tgl span { color: #adb5bd; font-size: .7rem; }
.vs-f-tgl input { font-size: .72rem; min-width: 0; }
.vs-f-hint { display: block; font-size: .64rem; color: #9a6405; margin-top: 3px; }
.vs-f-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 7px; }
.vs-f-2 select { font-size: .72rem; }
.vs-f-reset { display: inline-flex; align-items: center; gap: 3px; margin-top: 7px; border: none;
    background: none; color: #f06548; font-size: .68rem; font-weight: 600; padding: 0; cursor: pointer; }

.vs-lhead { display: flex; align-items: center; gap: 8px; }
.vs-cball { display: inline-flex; margin: 0; cursor: pointer; }
.vs-cball input, .vs-li-cb input { width: 14px; height: 14px; cursor: pointer; accent-color: #405189; }

/* Kartu antrean: satu klasifikasi pada satu sampel */
.vs-li { position: relative; display: flex; align-items: stretch; border-bottom: 1px solid #f3f3f9; background: #fff; }
.vs-li:hover { background: #f8f9fc; }
.vs-li.is-active { background: #f6f8fd; }
.vs-li.is-cek { background: #f3f7ff; }
.vs-li-acc { width: 3px; flex-shrink: 0; background: #e9ebec; }
.vs-li-acc.st-ok   { background: #0ab39c; }
.vs-li-acc.st-warn { background: #f7b84b; }
.vs-li-acc.st-bad  { background: #f06548; }
.vs-li-cb { display: flex; align-items: flex-start; padding: 11px 0 0 10px; margin: 0; cursor: pointer; }
.vs-li-b { flex: 1; min-width: 0; display: block; text-align: left; border: none; background: none;
    padding: 9px 12px 10px 9px; cursor: pointer; color: inherit; }
.vs-li-r1 { display: flex; align-items: center; gap: 6px; }
.vs-li-no { font-family: ui-monospace, monospace; font-size: .78rem; color: #405189; }
.vs-kl { display: inline-flex; align-items: center; gap: 3px; font-size: .6rem; font-weight: 700;
    color: #405189; background: #eef1fb; border-radius: 3px; padding: 1px 5px; }
.vs-li-tgl { margin-left: auto; font-size: .62rem; color: #adb5bd; white-space: nowrap; }
.vs-li-nm { display: block; margin-top: 3px; font-size: .72rem; color: #495057; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vs-li-id { display: flex; flex-wrap: wrap; align-items: center; gap: 2px 9px; margin-top: 2px;
    font-size: .62rem; color: #878a99; }
.vs-li-id > span { display: inline-flex; align-items: center; gap: 2px; }
.vs-li-id i { color: #adb5bd; font-size: .7rem; }
.vs-li-frm { font-family: ui-monospace, monospace; color: #405189; font-weight: 600; }
.vs-li-frm.is-kosong { color: #ced4da; font-weight: 400; }
.vs-li-msn { color: #405189; font-weight: 600; }
.vs-li-msn i { color: #405189 !important; }
.vs-li-ket { display: block; margin-top: 4px; font-size: .64rem; line-height: 1.35;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vs-li-ket i { margin-right: 3px; }
.vs-li-ket b { font-weight: 700; }
.vs-li-ket.is-bad  { color: #b03826; }
.vs-li-ket.is-warn { color: #9a6405; }
.vs-li-r4 { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.vs-tag { display: inline-flex; align-items: center; gap: 2px; font-size: .6rem; font-weight: 700;
    border-radius: 3px; padding: 1px 6px; background: #f3f3f9; color: #6c757d; }
.vs-tag.is-bad  { background: #fde4df; color: #b03826; }
.vs-tag.is-warn { background: #fff3d9; color: #9a6f14; }
.vs-tag.is-info { background: #e0f4fb; color: #0b7fa6; }
.vs-tag.is-ok   { background: #d9f3ee; color: #0c7a68; }
.vs-li-go { color: #ced4da; align-self: center; padding-right: 8px; display: none; }

/* Aksi massal */
.vs-bulk { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px 10px;
    padding: 8px 12px; background: #eef1fb; border-top: 1px solid #dde3f4; font-size: .72rem;
    color: #405189; flex-shrink: 0; }
.vs-bulk > span:first-child { display: inline-flex; align-items: center; gap: 5px; }
.vs-bulk-b { display: flex; gap: 6px; }

.vs-mono { font-family: ui-monospace, monospace; }
.vs-al--info { background: #e0f4fb; border: 1px solid #b9e4f5; color: #0b6a8b; }
.vs-lb em { font-style: normal; font-weight: 400; color: #adb5bd; font-size: .68rem; margin-left: 5px; }

/* Galeri foto analisa — sama dengan Finalisasi. */
.vs-gal { margin-top: 14px; background: #fff; border: 1px solid #e9ebec; border-radius: 7px; padding: 11px 13px 13px; }
.vs-gal-h { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 6px; margin-bottom: 9px; font-size: .72rem; }
.vs-gal-h i { color: #0891b2; }
.vs-gal-h b { color: #495057; }
.vs-gal-h em { font-style: normal; font-size: .66rem; color: #adb5bd; }
.vs-gal-g { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(170px, 100%), 1fr)); gap: 10px; }
.vs-gal-c { display: flex; flex-direction: column; padding: 0; text-align: left;
    border: 1px solid #e9ebec; border-radius: 7px; overflow: hidden; background: #fff;
    cursor: zoom-in; transition: border-color .14s, box-shadow .14s; min-width: 0; }
.vs-gal-c:hover { border-color: #0891b2; box-shadow: 0 4px 14px rgba(8,145,178,.14); }
.vs-gal-img { position: relative; display: flex; align-items: center; justify-content: center;
    aspect-ratio: 4 / 3; background: #f3f6f9; overflow: hidden; }
.vs-gal-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.vs-gal-x { display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: .66rem; color: #adb5bd; }
.vs-gal-x i { font-size: 1.4rem; }
.vs-gal-zoom { position: absolute; right: 7px; bottom: 7px; width: 26px; height: 26px;
    border-radius: 50%; background: rgba(33,37,41,.55); color: #fff; font-size: .85rem;
    display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .14s; }
.vs-gal-c:hover .vs-gal-zoom, .vs-gal-c:focus-visible .vs-gal-zoom { opacity: 1; }
.vs-gal-t { display: flex; flex-direction: column; gap: 3px; padding: 8px 10px 9px; min-width: 0; }
.vs-gal-t1 { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
.vs-gal-t1 b { font-size: .72rem; color: #495057; }
.vs-gal-t1 .badge { font-size: .6rem; flex-shrink: 0; }
.vs-gal-t2 { font-size: .66rem; color: #6c757d; }
.vs-gal-t2 code { font-size: .64rem; color: #878a99; }
.vs-gal-t3 { font-size: .65rem; color: #878a99; font-style: italic;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Parameter perhitungan */
.vs-sec { background: #fff; border: 1px solid #e9ebec; border-radius: 7px; margin-top: 14px; overflow: hidden; }
.vs-sec-h { width: 100%; display: flex; align-items: center; gap: 7px; border: none; background: none;
    padding: 10px 13px; font-size: .74rem; font-weight: 600; color: #495057; cursor: pointer; text-align: left; }
.vs-sec-h > i:first-child { color: #405189; }
.vs-sec-h em { font-style: normal; font-weight: 400; color: #adb5bd; font-size: .66rem; margin-left: 4px; }
.vs-sec-k { margin-left: auto; color: #adb5bd; }
.vs-sec-b { padding: 4px 13px 12px; border-top: 1px solid #f3f3f9; }
.vs-pr { margin-top: 9px; }
.vs-pr-n { display: block; font-size: .7rem; font-weight: 600; color: #495057; margin-bottom: 5px; }
/* Bordered: setiap sel bergaris supaya angka mudah dirunut per kolom. */
.vs-tw { overflow-x: auto; }
.vs-t { width: 100%; border-collapse: collapse; font-size: .72rem; }
.vs-t th { font-size: .6rem; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; font-weight: 700;
    background: #f3f6f9; padding: 7px 10px; border: 1px solid #dfe3ea; white-space: nowrap; text-align: left; }
.vs-t th small { text-transform: none; letter-spacing: 0; font-weight: 400; }
.vs-t td { padding: 6px 10px; border: 1px solid #dfe3ea; color: #495057; vertical-align: middle; background: #fff; }
.vs-t tbody tr:nth-child(even) td { background: #fbfcfd; }
.vs-t .is-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.vs-t .is-rumus { font-weight: 700; color: #405189; }
.vs-t .is-kd { white-space: nowrap; }

.vs-dfoot-b { display: flex; gap: 8px; }

/* Modal: validasi & uji ulang */
.vs-md-ld { display: flex; align-items: center; font-size: .74rem; color: #878a99; padding: 6px 0 10px; }
.vs-md-warn { display: block; font-size: .68rem; color: #b03826; }
.vs-md-p { font-size: .74rem; color: #6c757d; margin: 0 0 12px; line-height: 1.55; }
.vs-ms { display: flex; flex-direction: column; gap: 7px; }
.vs-ms-i { border: 1px solid #e9ebec; border-radius: 6px; padding: 9px 12px; margin: 0; }
.vs-ms-cek { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; transition: opacity .14s, background .14s; }
.vs-ms-cek:hover { background: #f8f9fc; }
.vs-ms-cek input { margin-top: 3px; width: 15px; height: 15px; flex-shrink: 0; accent-color: #0ab39c; cursor: pointer; }
.vs-ms-cek.is-off { opacity: .55; }
.vs-ms-b { flex: 1; min-width: 0; }
.vs-ms-t { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.vs-ms-t b { font-size: .76rem; color: #495057; }
.vs-ms-m { display: block; font-size: .66rem; color: #878a99; margin-top: 2px; }
.vs-ms-m code { font-size: .66rem; color: #405189; background: none; padding: 0; }
.vs-st { flex-shrink: 0; font-size: .6rem; font-weight: 700; border-radius: 3px; padding: 1px 6px; }
.vs-st.is-ok   { background: #d9f3ee; color: #0c7a68; }
.vs-st.is-bad  { background: #fde4df; color: #b03826; }
.vs-st.is-warn { background: #fff3d9; color: #9a6f14; }

/* Satu panel (HP) */
.vs.is-tunggal .vs-li-go { display: block; }
.vs.is-tunggal .vs-bulk { position: sticky; bottom: calc(var(--bawah, 0px) + 41px); z-index: 4; }
.vs.is-tunggal .vs-dfoot-b { flex: 1; }
.vs.is-tunggal .vs-cth-m { right: auto; left: 0; width: min(290px, calc(100vw - 32px)); }

@media (pointer: coarse) {
    .vs-li-cb { padding: 9px 2px 0 10px; }
    .vs-cball input, .vs-li-cb input { width: 18px; height: 18px; }
    .vs-fbtn { width: 36px; }
    .vs-cth-i { padding: 10px 11px; }
}
</style>
