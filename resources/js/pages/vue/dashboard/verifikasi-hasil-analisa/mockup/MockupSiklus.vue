<template>
    <div ref="akar" class="vs" :class="kelasTata" :style="{ '--bawah': bawah + 'px' }">
        <!--
            MOCKUP SIKLUS — Validasi -> Verifikasi -> Finalisasi -> Hasil Analisa.

            Satu halaman untuk keempat tahap (prop 'tahap'). Datanya disimpan
            di BROWSER (localStorage, mockupSiklus.js): bertahan saat berganti
            akun lewat /trial-ui dan antar-tab, sampai dikosongkan. Server
            hanya membuat contoh data dari master asli dan menyajikan master
            keputusan — database tidak ditulis.

            Hak tetap mengikuti akun: validasi per hak jenis analisa,
            verifikasi per kewenangan klasifikasi, finalisasi oleh akun FIN.

            Komentar ini sengaja berada di DALAM elemen akar: komentar di luar
            akar membuat komponen ber-akar fragment pada mode dev.
        -->

        <!-- ================= TOPBAR + STEPPER ================= -->
        <div class="vs-top mk-top">
            <div class="vs-top-l">
                <h4 class="vs-top-t">{{ judul.t }}</h4>
                <p class="vs-top-s">{{ judul.s }}</p>
            </div>
            <div class="vs-top-r">
                <span class="mk-akun" :title="'Login sebagai ' + pengguna.nama">
                    <i class="ri-user-3-line"></i><b>{{ pengguna.nama }}</b><em>{{ peranTeks }}</em>
                </span>
                <a href="/trial-ui" class="vs-ico mk-ganti" title="Ganti akun (data mockup tetap di tab ini)">
                    <i class="ri-user-shared-line"></i>
                </a>
                <span class="mk-ss" title="Data mockup tersimpan di browser ini (local storage) — tidak ke database. Kosongkan lewat menu Contoh data.">
                    <i class="ri-database-2-line me-1"></i>Data browser
                </span>
                <div v-if="tahap === 'validasi'" ref="menuCth" class="vs-cth">
                    <button type="button" class="vs-dummy" :disabled="contoh.proses" @click="menuCth = !menuCth">
                        <span v-if="contoh.proses" class="spinner-border spinner-border-sm"></span>
                        <i v-else class="ri-add-circle-line"></i>
                        <span class="vs-dummy-t">Contoh data</span><i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div v-if="menuCth" class="vs-cth-m" role="menu">
                        <span class="vs-cth-h">Tambah sampel contoh</span>
                        <button v-for="p in preset" :key="p.kode" type="button" class="vs-cth-i" role="menuitem"
                                @click="bukaContoh(p.kode)">
                            <b>{{ p.judul }}</b><em>{{ p.ket }}</em>
                        </button>
                        <button type="button" class="vs-cth-i is-semua" role="menuitem" @click="bukaContoh('atur')">
                            <b><i class="ri-equalizer-line me-1"></i>Atur sendiri…</b>
                            <em>Pilih skenario tiap klasifikasi, jumlah foto, dan jumlah sampel</em>
                        </button>
                        <button type="button" class="vs-cth-i is-hapus" role="menuitem" @click="hapusSemua">
                            <b><i class="ri-delete-bin-line me-1"></i>Kosongkan data mockup</b>
                            <em>Menghapus seluruh sampel mockup di browser ini</em>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stepper siklus: angka = antrean seluruh akun. -->
            <nav class="mk-step" aria-label="Tahap siklus">
                <template v-for="(st, i) in langkah" :key="st.kode">
                    <i v-if="i" class="ri-arrow-right-s-line mk-step-p"></i>
                    <a v-if="st.boleh" :href="st.url" class="mk-step-i"
                       :class="{ 'is-on': st.kode === tahap }" :aria-current="st.kode === tahap ? 'page' : null">
                        <i :class="st.ikon"></i><span>{{ st.label }}</span><em>{{ st.n }}</em>
                    </a>
                    <span v-else class="mk-step-i is-kunci" :title="'Akun ini tidak mendapat tahap ' + st.label + ' — ganti akun di /trial-ui'">
                        <i class="ri-lock-line"></i><span>{{ st.label }}</span><em>{{ st.n }}</em>
                    </span>
                </template>
            </nav>
        </div>

        <!-- ================= BODY ================= -->
        <div class="vs-body">
            <!-- ===== KIRI ===== -->
            <aside ref="kiri" class="vs-left" :class="{ 'is-sembunyi': tunggal && terpilih }">
                <div class="vs-search" role="search">
                    <div class="vs-search-i">
                        <i class="ri-search-line"></i>
                        <input v-model="cari" type="search" class="form-control form-control-sm"
                               placeholder="Cari sampel, PO, barang, FRM…" aria-label="Cari sampel"
                               autocomplete="off" spellcheck="false" enterkeyhint="search" />
                    </div>
                </div>

                <!-- Saringan tahap (Verifikasi). Finalisasi hanya memuat sampel yang siap difinalisasi. -->
                <div v-if="tahap === 'verifikasi'" class="mk-seg" role="tablist">
                    <button v-for="o in opsiSaring" :key="o.kode" type="button" role="tab"
                            :aria-selected="saring === o.kode ? 'true' : 'false'"
                            :class="{ 'is-on': saring === o.kode }" @click="gantiSaring(o.kode)">
                        {{ o.label }}<em>{{ o.n }}</em>
                    </button>
                </div>

                <div class="vs-lhead">
                    <label v-if="bisaMassal && daftar.length" class="vs-cball" title="Pilih semua">
                        <input type="checkbox" :checked="semuaDipilih" :indeterminate.prop="sebagianDipilih && !semuaDipilih"
                               aria-label="Pilih semua" @change="pilihSemua" />
                    </label>
                    <span aria-live="polite">
                        <template v-if="cari.trim()"><b>{{ daftar.length }}</b> dari {{ daftarSemua.length }} cocok</template>
                        <template v-else><b>{{ daftarSemua.length }}</b> {{ judul.hitung }}</template>
                    </span>
                </div>

                <div class="vs-list">
                    <div v-if="!daftar.length" class="vs-state vs-state--empty">
                        <template v-if="cari.trim()">
                            <i class="ri-search-line"></i>
                            <p>Tidak ada yang cocok dengan pencarian.</p>
                        </template>
                        <template v-else>
                            <i class="ri-inbox-line"></i>
                            <p>{{ judul.kosong }}</p>
                            <!-- Antrean akun lain: supaya jelas siapa yang harus login. -->
                            <div v-if="menungguLain.length" class="mk-lain">
                                <span class="mk-lain-h">Menunggu akun lain</span>
                                <span v-for="m in menungguLain" :key="m.kunci" class="mk-lain-i">
                                    <code>{{ m.no }}</code> · {{ m.klas }}
                                    <b>{{ m.akun || '—' }}</b>
                                </span>
                                <a href="/trial-ui" class="btn btn-sm btn-light mt-2">
                                    <i class="ri-user-shared-line me-1"></i>Ganti akun
                                </a>
                            </div>
                            <button v-else-if="tahap === 'validasi'" class="btn btn-sm btn-light mt-2"
                                    @click="bukaContoh('atur')">
                                <i class="ri-add-circle-line me-1"></i>Buat contoh data
                            </button>
                        </template>
                    </div>

                    <div v-for="it in daftar" :key="it.kunci" v-else
                         class="vs-li" :class="{ 'is-active': terpilihKunci === it.kunci, 'is-cek': dipilih.includes(it.kunci) }">
                        <span class="vs-li-acc" :class="'st-' + nadaItem(it)"></span>
                        <label v-if="bisaMassal" class="vs-li-cb">
                            <input type="checkbox" :checked="dipilih.includes(it.kunci)"
                                   :disabled="tahap === 'finalisasi' && !it.Siap"
                                   :aria-label="'Pilih ' + it.No_Sampel" @change="togglePilih(it)" />
                        </label>
                        <button type="button" class="vs-li-b" :class="{ 'no-cb': !bisaMassal }" @click="buka(it)">
                            <span class="vs-li-r1">
                                <b class="vs-li-no">{{ it.No_Sampel }}</b>
                                <span v-if="it.Kode_Aktivitas_Lab" class="vs-kl" :title="it.Nama_Aktivitas">
                                    <i :class="ikonAkt(it.Kode_Aktivitas_Lab)"></i>{{ it.Kode_Aktivitas_Lab }}
                                </span>
                                <span class="vs-li-tgl">{{ stamp(it.Final_Waktu || it.Waktu) }}</span>
                            </span>
                            <span class="vs-li-nm" :title="it.Nama_Barang">{{ it.Nama_Barang || '-' }}</span>
                            <span class="vs-li-id">
                                <span><i class="ri-barcode-line"></i>{{ it.Kode_Barang }}</span>
                                <span class="vs-li-frm">{{ it.Kode_Formula || 'FRM -' }}</span>
                            </span>
                            <span class="vs-li-id">
                                <span><i class="ri-file-list-3-line"></i>{{ it.No_Po }}</span>
                                <span class="vs-li-msn"><i class="ri-settings-3-line"></i>{{ it.Nama_Mesin || '-' }}</span>
                                <span v-if="it.Kode_Aktivitas_Lab"><i class="ri-qr-code-line"></i>{{ it.Multi ? 'Multi' : 'Single' }}</span>
                            </span>

                            <!-- Validasi & verifikasi: satu klasifikasi -->
                            <template v-if="it.Kode_Aktivitas_Lab">
                                <span class="vs-li-r4">
                                    <span v-if="it.Jumlah_Analisa" class="vs-tag">{{ it.Jumlah_Analisa }} analisa</span>
                                    <span v-if="it.Jumlah_Analisa && !it.Analisa_Tidak_Layak.length && !it.Analisa_Tanpa_Master.length && !it.Butuh_Pembanding"
                                          class="vs-tag is-ok"><i class="ri-check-line"></i>Semua layak</span>
                                    <span v-if="it.Putaran > 1" class="vs-tag is-info"><i class="ri-refresh-line"></i>Putaran {{ it.Putaran }}</span>
                                    <span v-if="it.Verifikasi" class="vs-tag" :class="'is-' + it.Verifikasi.warna">
                                        {{ it.Verifikasi.nama }}
                                    </span>
                                </span>
                                <span v-if="(it.Menunggu_Ulang || []).length" class="vs-li-ket is-info">
                                    <i class="ri-time-line"></i><b>Menunggu uji ulang</b> &middot; {{ ringkasNama(it.Menunggu_Ulang) }}
                                </span>
                            </template>
                            <!-- Finalisasi & hasil: satu sampel -->
                            <span v-else class="vs-li-r4">
                                <span v-for="k in it.klasifikasi" :key="k.kode" class="vs-tag"
                                      :class="k.verifikasi ? 'is-' + k.verifikasi.warna : ''"
                                      :title="k.nama + ' — ' + (k.verifikasi ? k.verifikasi.nama : 'menunggu verifikasi')">
                                    <i :class="k.verifikasi ? 'ri-shield-check-line' : 'ri-time-line'"></i>{{ k.kode }}
                                </span>
                                <span v-if="it.Finalisasi" class="vs-tag" :class="'is-' + it.Finalisasi.warna">
                                    <i class="ri-flag-2-line"></i>{{ it.Finalisasi.nama }}
                                </span>
                            </span>
                            <span v-if="it.Analisa_Tidak_Layak && it.Analisa_Tidak_Layak.length" class="vs-li-ket is-bad"
                                  :title="'Tidak layak: ' + it.Analisa_Tidak_Layak.join(', ')">
                                <i class="ri-close-circle-line"></i><b>{{ it.Analisa_Tidak_Layak.length }} tidak layak</b>
                                &middot; {{ ringkasNama(it.Analisa_Tidak_Layak) }}
                            </span>
                            <span v-if="it.Analisa_Tanpa_Master && it.Analisa_Tanpa_Master.length" class="vs-li-ket is-warn"
                                  :title="'Belum ada di master: ' + it.Analisa_Tanpa_Master.join(', ')">
                                <i class="ri-error-warning-line"></i><b>{{ it.Analisa_Tanpa_Master.length }} belum ada di master</b>
                                &middot; {{ ringkasNama(it.Analisa_Tanpa_Master) }}
                            </span>
                        </button>
                        <i class="ri-arrow-right-s-line vs-li-go"></i>
                    </div>
                </div>

                <div v-if="dipilih.length" class="vs-bulk">
                    <span><i class="ri-checkbox-multiple-line"></i><b>{{ dipilih.length }}</b> dipilih</span>
                    <span class="vs-bulk-b">
                        <button class="btn btn-sm btn-light" @click="dipilih = []">Batal</button>
                        <button class="btn btn-sm btn-success" @click="aksiMassal">
                            <i :class="tahap === 'validasi' ? 'ri-check-double-line' : 'ri-shield-check-line'" class="me-1"></i>{{ judul.aksi }}
                        </button>
                    </span>
                </div>
            </aside>

            <!-- ===== TENGAH ===== -->
            <section class="vs-center" :class="{ 'is-sembunyi': tunggal && !terpilih }">
                <div v-if="!terpilih || !sampel" class="vs-state vs-state--empty vs-center-empty">
                    <i :class="judul.ikon"></i>
                    <p>{{ judul.pilih }}</p>
                    <small>{{ judul.pilihKet }}</small>
                </div>

                <template v-else>
                    <div class="vs-dh">
                        <button v-if="tunggal" class="vs-back" title="Kembali ke daftar" @click="kembali">
                            <i class="ri-arrow-left-line"></i>
                        </button>
                        <div class="vs-dh-l">
                            <div class="vs-dh-t">
                                <b>{{ sampel.No_Sampel }}</b>
                                <span v-if="klas" class="badge bg-primary-subtle text-primary">
                                    <i :class="ikonAkt(klas.kode)" class="me-1"></i>{{ klas.nama }}
                                </span>
                                <span class="badge bg-warning-subtle text-warning">Trial Produksi</span>
                                <span class="badge" :class="badgePosisi.cls"><i :class="badgePosisi.ikon" class="me-1"></i>{{ badgePosisi.label }}</span>
                                <span class="badge bg-light text-muted" :title="sampel.Judul"><i class="ri-flask-line me-1"></i>Contoh</span>
                            </div>
                            <dl class="vs-dh-meta">
                                <div>
                                    <dt>No. PO</dt>
                                    <dd>{{ sampel.No_Po }}<small class="vs-dh-kd">split {{ sampel.No_Split_Po }} · batch {{ sampel.No_Batch }}</small></dd>
                                </div>
                                <div><dt>Formula</dt><dd>{{ sampel.Kode_Formula || '-' }}</dd></div>
                                <div><dt>Mesin</dt><dd class="vs-mesin"><i class="ri-settings-3-line me-1"></i>{{ sampel.Nama_Mesin || '-' }}</dd></div>
                                <div><dt>Produk</dt><dd>{{ sampel.Nama_Barang }}<small class="vs-dh-kd">{{ sampel.Kode_Barang }}</small></dd></div>
                                <div>
                                    <dt>Registrasi</dt>
                                    <dd>{{ sampel.Registrasi.nama }}<small class="vs-dh-kd">{{ stamp(sampel.Registrasi.waktu, true) }}</small></dd>
                                </div>
                            </dl>
                        </div>
                        <button class="vs-lcbtn" :class="{ 'is-on': panelKanan }" title="Sample Lifecycle" @click="toggleKanan">
                            <i class="ri-route-line"></i><span class="vs-lcbtn-t">Lifecycle</span>
                            <i :class="panelKanan ? 'ri-contract-right-line' : 'ri-expand-left-line'" class="vs-lcbtn-c"></i>
                        </button>
                    </div>

                    <div class="vs-dbody">
                        <!-- ============ VALIDASI & VERIFIKASI: satu klasifikasi ============ -->
                        <template v-if="klas">
                            <div v-if="jumlahTL" class="vs-al vs-al--bad">
                                <i class="ri-close-circle-line"></i>
                                <span><b>{{ jumlahTL }} hasil tidak layak.</b>&nbsp;
                                    <template v-if="tahap === 'validasi'">Periksa sebelum memvalidasi — gunakan <b>Uji Ulang</b> bila perlu diuji kembali.</template>
                                    <template v-else>Pertimbangkan dalam rekomendasi.</template>
                                </span>
                            </div>
                            <div v-if="jumlahTM" class="vs-al vs-al--warn">
                                <i class="ri-error-warning-line"></i>
                                <span><b>{{ jumlahTM }} hasil belum dapat dinilai</b> karena kriterianya belum ada di master.</span>
                            </div>

                            <!-- Uji ulang yang menunggu hasil dari analis (simulasi). -->
                            <div v-if="tahap === 'validasi' && ulangTertunda.length" class="mk-ulang">
                                <div class="mk-ulang-h"><i class="ri-time-line"></i><b>Menunggu uji ulang</b>
                                    <em>Simulasikan hasil uji ulang dari analis untuk melanjutkan.</em></div>
                                <div v-for="r in ulangTertunda" :key="r.id + '-' + r.putaran" class="mk-ulang-i">
                                    <span class="mk-ulang-n">
                                        <b>{{ r.nama }}</b>
                                        <em>{{ r.sama ? r.asal + ' (nomor sama)' : r.asal + ' → ' + r.baru }} · diminta {{ r.oleh.nama }} {{ stamp(r.waktu, true) }}</em>
                                        <em class="mk-ulang-a">&ldquo;{{ r.alasan }}&rdquo;</em>
                                    </span>
                                    <span class="mk-ulang-b">
                                        <button class="btn btn-sm btn-outline-success" @click="hasilUlang(r, 'Y')">
                                            <i class="ri-checkbox-circle-line me-1"></i>Hasil layak
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" @click="hasilUlang(r, 'T')">
                                            <i class="ri-close-circle-line me-1"></i>Hasil tidak layak
                                        </button>
                                    </span>
                                </div>
                            </div>

                            <div v-if="tahap === 'verifikasi' && klas.verifikasi" class="mk-kep" :class="'is-' + klas.verifikasi.warna">
                                <i class="ri-shield-check-line"></i>
                                <span>
                                    <b>{{ klas.verifikasi.nama }}</b>
                                    <em>{{ klas.verifikasi.oleh.nama }} · {{ stamp(klas.verifikasi.waktu, true) }}<template v-if="klas.verifikasi.revisi"> · rev.{{ klas.verifikasi.revisi }}</template></em>
                                    <IsiCatatan v-if="klas.verifikasi.catatan" :isi="klas.verifikasi.catatan"
                                                :judul="judulCatatan(klas.verifikasi.kode, 'verifikasi')" class="mk-kep-c" />
                                </span>
                            </div>

                            <template v-if="barisTabel.length">
                                <label class="vs-lb">Hasil analisa
                                    <em>{{ jumlahJenis }} analisa {{ tahap === 'validasi' ? 'menunggu validasi' : 'tervalidasi' }}</em>
                                </label>
                                <TabelHasilAnalisa :analisa="barisTabel" :butuh-pembanding="klas.Butuh_Pembanding"
                                                   :pembanding="klas.Pembanding" :matriks="matriksTabel"
                                                   :menunggu="tahap === 'validasi'" @foto="bukaFotoAnalisa" />
                            </template>
                            <div v-else-if="tahap === 'validasi'" class="vs-state">
                                <small>Seluruh analisa klasifikasi ini sedang menunggu uji ulang.</small>
                            </div>

                            <div v-if="parameterTabel.length" class="vs-sec">
                                <button type="button" class="vs-sec-h" @click="paramBuka = !paramBuka">
                                    <i class="ri-calculator-line"></i>
                                    <span>Parameter perhitungan <em>{{ parameterTabel.length }} analisa</em></span>
                                    <i :class="paramBuka ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'" class="vs-sec-k"></i>
                                </button>
                                <div v-if="paramBuka" class="vs-sec-b">
                                    <div v-for="p in parameterTabel" :key="p.Id_Jenis_Analisa" class="vs-pr">
                                        <span class="vs-pr-n">{{ p.Nama_Jenis_Analisa }}</span>
                                        <div class="vs-tw">
                                            <table class="vs-t">
                                                <thead><tr>
                                                    <th>No. Transaksi</th><th>Sampel</th>
                                                    <th v-for="(pr, i) in p.parameter" :key="i" class="is-num">{{ pr.nama }}<small v-if="pr.satuan"> {{ pr.satuan }}</small></th>
                                                    <th class="is-num is-rumus">Hasil</th>
                                                </tr></thead>
                                                <tbody>
                                                    <tr v-for="b in p.baris" :key="b.No_Faktur">
                                                        <td class="vs-mono is-kd">{{ b.No_Faktur }}</td>
                                                        <td class="vs-mono is-kd">{{ b.No_Sampel_Uji }}</td>
                                                        <td v-for="(v, i) in b.nilai" :key="i" class="is-num">{{ v === null ? '—' : angka(v) }}</td>
                                                        <td class="is-num is-rumus">{{ angka(b.Hasil) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- ============ FINALISASI & HASIL: satu sampel ============ -->
                        <template v-else>
                            <!-- Hasil analisa: keputusan akhir di atas -->
                            <div v-if="sampel.finalisasi" class="mk-final" :class="'is-' + sampel.finalisasi.warna">
                                <span class="mk-final-i"><i class="ri-flag-2-fill"></i></span>
                                <span class="mk-final-t">
                                    <small>Keputusan akhir</small>
                                    <b>{{ sampel.finalisasi.nama }}</b>
                                    <em>{{ sampel.finalisasi.oleh.nama }} · {{ stamp(sampel.finalisasi.waktu, true) }}</em>
                                    <IsiCatatan v-if="sampel.finalisasi.catatan" :isi="sampel.finalisasi.catatan"
                                                :judul="judulCatatan(sampel.finalisasi.kode, 'finalisasi')" class="mk-kep-c" />
                                </span>
                            </div>

                            <!-- Rekomendasi tiap klasifikasi: status ringkas; catatannya dibuka di jendela baca. -->
                            <div class="mk-rk">
                                <div class="mk-rk-h">
                                    <span class="mk-rk-j"><i class="ri-shield-check-line"></i>Rekomendasi verifikasi</span>
                                    <span v-if="tahap === 'finalisasi' && terpilih.Saran" class="mk-rk-saran" :class="'is-' + warnaKep(terpilih.Saran)"
                                          title="Tingkat terendah dari seluruh rekomendasi verifikator.">
                                        <i class="ri-lightbulb-line"></i>Saran sistem: <b>{{ namaKep(terpilih.Saran) }}</b>
                                    </span>
                                </div>
                                <div class="mk-rk-g">
                                    <div v-for="k in sampel.klasifikasi" :key="k.kode" class="mk-rk-c" :class="k.verifikasi ? 'is-' + k.verifikasi.warna : 'is-wait'">
                                        <span class="mk-rk-t"><i :class="ikonAkt(k.kode)"></i><b>{{ k.nama }}</b></span>
                                        <span class="mk-rk-st"><i :class="ikonStatus(k)"></i>{{ k.verifikasi ? k.verifikasi.nama : 'Menunggu verifikasi' }}</span>
                                        <span class="mk-rk-o">
                                            <template v-if="k.verifikasi">
                                                {{ k.verifikasi.oleh.nama }} · {{ stamp(k.verifikasi.waktu, true) }}<template v-if="k.verifikasi.revisi"> · revisi {{ k.verifikasi.revisi }}</template>
                                            </template>
                                            <template v-else>{{ (k.petugas_verifikasi || []).map((p) => p.nama).join(', ') || 'Verifikator' }}</template>
                                        </span>
                                        <span class="mk-rk-n">
                                            <span>{{ ringkasKlas[k.kode].analisa }} analisa</span>
                                            <span v-if="ringkasKlas[k.kode].tl" class="is-bad">{{ ringkasKlas[k.kode].tl }} tidak layak</span>
                                            <span v-if="ringkasKlas[k.kode].tm" class="is-warn">{{ ringkasKlas[k.kode].tm }} belum di master</span>
                                            <span v-if="ringkasKlas[k.kode].rs">{{ ringkasKlas[k.kode].rs }} resampling</span>
                                        </span>
                                        <button v-if="k.verifikasi && k.verifikasi.catatan" type="button" class="mk-rk-b" @click="bacaCatatan(k)">
                                            <i class="ri-file-text-line"></i>Lihat catatan
                                        </button>
                                        <span v-else-if="k.verifikasi" class="mk-rk-no">Tanpa catatan</span>
                                    </div>
                                </div>
                            </div>

                            <div v-for="k in sampel.klasifikasi" :key="k.kode" class="mk-kl">
                                <div class="mk-kl-h">
                                    <i :class="ikonAkt(k.kode)"></i>
                                    <b>{{ k.nama }}</b><em>{{ k.kode }} · {{ jumlahJenisDari(k.analisa) }} analisa</em>
                                    <span class="mk-kl-v" :class="k.verifikasi ? 'is-' + k.verifikasi.warna : 'is-wait'">
                                        <i :class="k.verifikasi ? 'ri-shield-check-line' : 'ri-time-line'"></i>
                                        {{ k.verifikasi ? k.verifikasi.nama : 'Menunggu verifikasi' }}
                                    </span>
                                </div>
                                <div v-if="k.verifikasi" class="mk-kl-s">
                                    <i class="ri-user-3-line"></i>{{ k.verifikasi.oleh.nama }} · {{ stamp(k.verifikasi.waktu, true) }}
                                    <button v-if="k.verifikasi.catatan" type="button" class="mk-kl-cat" @click="bacaCatatan(k)">
                                        <i class="ri-file-text-line"></i>Lihat catatan
                                    </button>
                                </div>
                                <div v-else class="mk-kl-s is-tunggu">
                                    Menunggu rekomendasi dari {{ (k.petugas_verifikasi || []).map((p) => p.nama).join(', ') || 'verifikator' }}
                                </div>
                                <TabelHasilAnalisa :analisa="k.analisa" :butuh-pembanding="k.Butuh_Pembanding"
                                                   :pembanding="k.Pembanding" :matriks="k.Matriks_Pembanding"
                                                   tanpa-bingkai @foto="bukaFotoAnalisa" />
                                <!-- Foto tepat di bawah tabel klasifikasi yang analisanya berfoto. -->
                                <GaleriFotoAnalisa v-if="fotoPerKlas[k.kode]" :grup="fotoPerKlas[k.kode]" :url="fotoUrl" :gagal="fotoGagal"
                                                   :judul="'Foto analisa · ' + k.nama" tanpa-bingkai @buka="bukaFotoKey" />
                            </div>
                        </template>

                        <!-- Validasi & Verifikasi: foto di bawah tabel klasifikasi yang dibuka. -->
                        <GaleriFotoAnalisa v-if="klas && fotoPerKlas[klas.kode]" :grup="fotoPerKlas[klas.kode]"
                                           :url="fotoUrl" :gagal="fotoGagal" @buka="bukaFotoKey" />
                    </div>

                    <!-- Aksi -->
                    <div v-if="tahap !== 'hasil'" class="vs-dfoot">
                        <span v-if="!terkunci" class="vs-dfoot-i"><i class="ri-information-line me-1"></i>{{ judul.jejak }}</span>
                        <span class="vs-dfoot-b">
                            <template v-if="tahap === 'validasi'">
                                <button class="btn btn-sm btn-warning text-white" :disabled="!barisTabel.length" @click="bukaUjiUlang">
                                    <i class="ri-refresh-line me-1"></i>Uji Ulang
                                </button>
                                <button class="btn btn-sm btn-success" :disabled="!barisTabel.length" @click="bukaValidasi([terpilih])">
                                    <i class="ri-check-double-line me-1"></i>Validasi
                                </button>
                            </template>
                            <template v-else-if="tahap === 'verifikasi'">
                                <!-- Rekomendasi yang sudah tercatat terkunci, kecuali untuk akun berhak revisi. -->
                                <span v-if="terkunci" class="mk-kunci">
                                    <i class="ri-lock-line"></i>{{ alasanKunci }}
                                </span>
                                <button v-else class="btn btn-sm btn-primary" @click="bukaKeputusan([terpilih])">
                                    <i :class="klas && klas.verifikasi ? 'ri-edit-circle-line' : 'ri-shield-check-line'" class="me-1"></i>{{ klas && klas.verifikasi ? 'Revisi Rekomendasi' : 'Beri Rekomendasi' }}
                                </button>
                            </template>
                            <button v-else class="btn btn-sm btn-primary" :disabled="!terpilih.Siap" @click="bukaKeputusan([terpilih])">
                                <i class="ri-flag-2-line me-1"></i>Finalisasi
                            </button>
                        </span>
                    </div>
                    <div v-else class="vs-dfoot">
                        <span class="vs-dfoot-i"><i class="ri-information-line me-1"></i>Hasil analisa final — hanya baca</span>
                    </div>
                </template>
            </section>

            <div v-if="panelKanan && terpilih && !laci" class="vs-split" :class="{ 'is-drag': geser }"
                 title="Tarik untuk mengubah lebar — klik dua kali untuk lebar semula"
                 @mousedown.prevent="mulaiGeser" @dblclick="resetLebar">
                <span class="vs-split-grip"></span>
            </div>
            <transition name="vs-pudar">
                <div v-if="panelKanan && terpilih && laci && !tunggal" class="vs-laci-bd" @click="tutupKanan"></div>
            </transition>

            <!-- ===== KANAN: Sample Lifecycle ===== -->
            <Teleport to="body" :disabled="!tunggal">
            <transition name="vs-geser">
                <aside v-if="panelKanan && terpilih && sampel" class="vs-right"
                       :class="{ 'is-max': lcMaks, 'is-laci': laci && !tunggal, 'is-lembar': tunggal }" :style="gayaKanan">
                    <div class="vs-rh">
                        <span><i class="ri-route-line me-2"></i>Sample Lifecycle</span>
                        <span class="vs-rh-act">
                            <button v-if="!tunggal" class="vs-rh-x" :title="lcMaks ? 'Kecilkan' : 'Perbesar'" @click="lcMaks = !lcMaks">
                                <i :class="lcMaks ? 'ri-fullscreen-exit-line' : 'ri-fullscreen-line'"></i>
                            </button>
                            <button class="vs-rh-x" title="Tutup panel" @click="tutupKanan"><i class="ri-close-line"></i></button>
                        </span>
                    </div>
                    <div class="vs-rb">
                        <LifecycleSampel :data="lcData" :batas="tahap === 'validasi' ? 'validasi' : null"
                                         :keputusan="tahap === 'validasi' ? keputusanLc : null" />
                    </div>
                </aside>
            </transition>
            </Teleport>
        </div>

        <!-- ================= LIGHTBOX ================= -->
        <Teleport to="body">
        <div v-if="galeri.show && fotoAktif" class="vs-zm" @click.self="galeri.show = false">
            <div class="vs-zm-box">
                <div class="vs-zm-top">
                    <span class="vs-zm-ttl"><i class="ri-image-2-line"></i><b>{{ fotoAktif.analisa }}</b><code>{{ fotoAktif.sampel }}</code></span>
                    <span class="vs-zm-act">
                        <span v-if="foto.length > 1" class="vs-zm-n">{{ galeri.i + 1 }} / {{ foto.length }}</span>
                        <button type="button" class="vs-zm-b" title="Tutup (Esc)" @click="galeri.show = false"><i class="ri-close-line"></i></button>
                    </span>
                </div>
                <div class="vs-zm-stage" @touchstart.passive="mulaiUsap" @touchend="akhiriUsap">
                    <button v-if="foto.length > 1" type="button" class="vs-zm-nav is-prev" @click="geserGaleri(-1)"><i class="ri-arrow-left-s-line"></i></button>
                    <img v-if="fotoUrl[fotoAktif.key]" :src="fotoUrl[fotoAktif.key]" :alt="'Foto ' + fotoAktif.analisa" />
                    <div v-else-if="fotoGagal[fotoAktif.key]" class="vs-zm-x"><i class="ri-image-line"></i><span>Foto gagal dimuat.</span></div>
                    <div v-else class="spinner-border text-light"></div>
                    <button v-if="foto.length > 1" type="button" class="vs-zm-nav is-next" @click="geserGaleri(1)"><i class="ri-arrow-right-s-line"></i></button>
                </div>
                <div class="vs-zm-cap">
                    <div class="vs-zm-ci"><small>Hasil</small><span><b>{{ fotoAktif.hasil }}</b>
                        <span class="badge ms-1" :class="fotoAktif.layak.cls">{{ fotoAktif.layak.label }}</span></span></div>
                    <div v-if="fotoAktif.keterangan" class="vs-zm-ci"><small>Keterangan foto</small><span>{{ fotoAktif.keterangan }}</span></div>
                    <div class="vs-zm-ci"><small>Diinput</small><span>{{ fotoAktif.input ? fotoAktif.input.nama : '-' }}</span></div>
                    <div class="vs-zm-ci"><small>Divalidasi</small>
                        <span v-if="fotoAktif.validasi">{{ fotoAktif.validasi.nama }}</span>
                        <span v-else class="vs-zm-none">menunggu validasi</span></div>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= MODAL: CONTOH DATA ================= -->
        <Teleport to="body">
        <div v-if="contoh.show" class="vs-bd" @click.self="contoh.show = false">
            <div class="vs-md" role="dialog" aria-modal="true" aria-labelledby="mk-cth-j">
                <div class="vs-md-h bg-success">
                    <i class="ri-add-circle-line"></i>
                    <div><b id="mk-cth-j">Tambah contoh data</b><em>Sampel trial produksi siap divalidasi</em></div>
                    <button class="vs-md-x" title="Tutup" @click="contoh.show = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="vs-md-b">
                    <label class="vs-lb">Cepat</label>
                    <div class="mk-preset">
                        <button v-for="p in preset" :key="p.kode" type="button" :class="{ 'is-on': contohCocok(p) }"
                                @click="terapkanPreset(p)"><b>{{ p.judul }}</b><em>{{ p.ket }}</em></button>
                    </div>

                    <label class="vs-lb mt-3">Skenario tiap klasifikasi</label>
                    <div class="mk-opsi">
                        <label><span><i class="ri-eye-line"></i>Look View</span>
                            <select v-model="contoh.opsi.LCKV" class="form-select form-select-sm">
                                <option v-for="(n, k) in skenario" :key="k" :value="k">{{ n }}</option>
                                <option value="tidak">Tidak diikutkan</option>
                            </select></label>
                        <label><span><i class="ri-flask-line"></i>Analisa Lab</span>
                            <select v-model="contoh.opsi.ANL" class="form-select form-select-sm">
                                <option v-for="(n, k) in skenario" :key="k" :value="k">{{ n }}</option>
                                <option value="tidak">Tidak diikutkan</option>
                            </select></label>
                        <label><span><i class="ri-heart-pulse-line"></i>Palatabilitas</span>
                            <select v-model="contoh.opsi.PLT" class="form-select form-select-sm">
                                <option value="angka">Hasil angka (tanpa layak / tidak layak)</option>
                                <option value="tidak">Tidak diikutkan</option>
                            </select></label>
                    </div>

                    <label class="vs-lb mt-3">Foto Look View &amp; jumlah sampel</label>
                    <div class="mk-opsi">
                        <label><span><i class="ri-image-line"></i>Foto WARNA</span>
                            <select v-model.number="contoh.opsi.foto_warna" class="form-select form-select-sm" :disabled="contoh.opsi.LCKV === 'tidak'">
                                <option v-for="n in [0, 1, 2, 5]" :key="n" :value="n">{{ n }} foto</option>
                            </select></label>
                        <label><span><i class="ri-image-line"></i>Foto TEKSTUR</span>
                            <select v-model.number="contoh.opsi.foto_tekstur" class="form-select form-select-sm" :disabled="contoh.opsi.LCKV === 'tidak'">
                                <option v-for="n in [0, 1, 2, 5]" :key="n" :value="n">{{ n }} foto</option>
                            </select></label>
                        <label><span><i class="ri-stack-line"></i>Jumlah sampel</span>
                            <select v-model.number="contoh.jumlah" class="form-select form-select-sm">
                                <option v-for="n in 5" :key="n" :value="n">{{ n }} sampel</option>
                            </select></label>
                    </div>
                    <p class="vs-md-p mt-3 mb-0">
                        Data dibuat dari master asli (produk, analisa, kriteria, pembanding, foto) lalu disimpan
                        di <b>browser ini</b> (local storage) — database tidak ditulis.
                    </p>
                    <div v-if="contoh.err" class="vs-al vs-al--bad mt-3 mb-0"><i class="ri-error-warning-line"></i><span>{{ contoh.err }}</span></div>
                </div>
                <div class="vs-md-f">
                    <button class="btn btn-sm btn-light" :disabled="contoh.proses" @click="contoh.show = false">Batal</button>
                    <button class="btn btn-sm btn-success" :disabled="contoh.proses || semuaTidak" @click="kirimContoh">
                        <span v-if="contoh.proses" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-add-line me-1"></i>Buat {{ contoh.jumlah }} sampel
                    </button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= MODAL: VALIDASI ================= -->
        <Teleport to="body">
        <div v-if="val.show" class="vs-bd" @click.self="val.show = false">
            <div class="vs-md" role="dialog" aria-modal="true" aria-labelledby="mk-val-j">
                <div class="vs-md-h bg-success">
                    <i class="ri-check-double-line"></i>
                    <div><b id="mk-val-j">Simpan Validasi</b>
                        <em>{{ val.satu ? val.items[0].No_Sampel + ' · ' + val.items[0].Nama_Aktivitas : val.items.length + ' klasifikasi dipilih' }}</em></div>
                    <button class="vs-md-x" title="Tutup" @click="val.show = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="vs-md-b">
                    <p class="vs-md-p">Analisa yang dicentang disimpan sebagai <b>hasil validasi</b> dan diteruskan ke verifikasi.
                        Hasil yang perlu diuji kembali jangan dicentang — gunakan <b>Uji Ulang</b>.</p>
                    <div class="vs-ms">
                        <template v-if="val.satu">
                            <label v-for="a in val.analisa" :key="a.id" class="vs-ms-i vs-ms-cek" :class="{ 'is-off': !val.centang[a.id] }">
                                <input v-model="val.centang[a.id]" type="checkbox" />
                                <span class="vs-ms-b">
                                    <span class="vs-ms-t"><b>{{ a.nama }}</b>
                                        <span v-if="a.layak" class="vs-st" :class="'is-' + nadaLayak(a.layak)">{{ labelLayak(a.layak) }}</span></span>
                                    <span class="vs-ms-m">{{ a.sub }}<template v-if="a.putaran > 1"> · putaran {{ a.putaran }}</template></span>
                                </span>
                            </label>
                        </template>
                        <div v-for="it in val.items" v-else :key="it.kunci" class="vs-ms-i">
                            <span class="vs-ms-t"><b>{{ it.No_Sampel }}</b><span class="vs-kl"><i :class="ikonAkt(it.Kode_Aktivitas_Lab)"></i>{{ it.Kode_Aktivitas_Lab }}</span></span>
                            <span class="vs-ms-m">{{ it.Jumlah_Analisa }} analisa
                                <template v-if="it.Analisa_Tidak_Layak.length"> · <b class="text-danger">{{ it.Analisa_Tidak_Layak.length }} tidak layak</b></template>
                                <template v-if="it.Analisa_Tanpa_Master.length"> · {{ it.Analisa_Tanpa_Master.length }} belum ada di master</template></span>
                        </div>
                    </div>
                </div>
                <div class="vs-md-f">
                    <button class="btn btn-sm btn-light" @click="val.show = false">Batal</button>
                    <button class="btn btn-sm btn-success" :disabled="!jumlahDicentang" @click="kirimValidasi">
                        <i class="ri-check-double-line me-1"></i>Validasi {{ jumlahDicentang }} analisa
                    </button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= MODAL: UJI ULANG ================= -->
        <Teleport to="body">
        <div v-if="ulang.show" class="vs-bd" @click.self="ulang.show = false">
            <div class="vs-md" role="dialog" aria-modal="true" aria-labelledby="mk-uu-j">
                <div class="vs-md-h bg-warning">
                    <i class="ri-refresh-line"></i>
                    <div><b id="mk-uu-j">Uji Ulang (Resampling)</b><em>{{ sampel ? sampel.No_Sampel : '' }} · {{ klas ? klas.nama : '' }}</em></div>
                    <button class="vs-md-x" title="Tutup" @click="ulang.show = false"><i class="ri-close-line"></i></button>
                </div>
                <div class="vs-md-b">
                    <label class="vs-lb" for="mk-uu-an">Analisa yang diuji ulang</label>
                    <select id="mk-uu-an" v-model="ulang.id" class="form-select form-select-sm mb-3" @change="ulang.sub = opsiSub[0] || ''">
                        <option v-for="a in daftarUlang" :key="a.id" :value="a.id">{{ a.nama }}{{ a.layak === 'T' ? ' — tidak layak' : '' }}</option>
                    </select>
                    <template v-if="ulangAnalisa">
                        <div class="vs-al vs-al--info">
                            <i class="ri-qr-code-line"></i>
                            <span v-if="!klas.Multi"><b>Single QR.</b> Diuji ulang dengan nomor sampel yang sama.</span>
                            <span v-else><b>Multi QR.</b> Diuji ulang pada sub sampel baru.</span>
                        </div>
                        <template v-if="klas.Multi">
                            <label class="vs-lb" for="mk-uu-sub">Sub sampel uji ulang</label>
                            <select id="mk-uu-sub" v-model="ulang.sub" class="form-select form-select-sm mb-3">
                                <option v-for="s in opsiSub" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </template>
                        <label class="vs-lb" for="mk-uu-al">Alasan resampling <span class="text-danger">*</span></label>
                        <textarea id="mk-uu-al" v-model="ulang.alasan" rows="3" maxlength="500" class="form-control form-control-sm"
                                  placeholder="Contoh: hasil ASH 11,2% di atas batas 10%, duplo konsisten, dugaan sampel tidak homogen."></textarea>
                        <div class="vs-count" :class="{ 'is-ok': ulang.alasan.trim().length >= 5 }">
                            {{ ulang.alasan.trim().length >= 5 ? 'Tercatat di riwayat sampel sebagai alasan resampling' : 'Wajib diisi, minimal 5 karakter' }}
                        </div>
                    </template>
                </div>
                <div class="vs-md-f">
                    <button class="btn btn-sm btn-light" @click="ulang.show = false">Batal</button>
                    <button class="btn btn-sm btn-warning text-white" :disabled="!ulangAnalisa || ulang.alasan.trim().length < 5 || (klas.Multi && !ulang.sub)"
                            @click="kirimUjiUlang">
                        <i class="ri-send-plane-line me-1"></i>Lakukan Uji Ulang
                    </button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= MODAL: KEPUTUSAN (verifikasi / finalisasi) ================= -->
        <Teleport to="body">
        <div v-if="kep.show" class="vs-bd" @click.self="tutupKeputusan">
            <div class="vs-md" role="dialog" aria-modal="true" aria-labelledby="mk-kep-j">
                <div class="vs-md-h" :class="'bg-' + warnaBs(kepAktif && kepAktif.Warna_Badge)">
                    <i :class="(kepAktif && kepAktif.Ikon) || 'ri-shield-check-line'"></i>
                    <div><b id="mk-kep-j">{{ tahap === 'verifikasi' ? (kep.revisi ? 'Revisi Rekomendasi' : 'Rekomendasi Verifikasi') : 'Keputusan Finalisasi' }}</b>
                        <em>{{ kep.items.length }} {{ tahap === 'verifikasi' ? 'klasifikasi' : 'sampel' }} akan diproses</em></div>
                    <button class="vs-md-x" title="Tutup" @click="tutupKeputusan"><i class="ri-close-line"></i></button>
                </div>
                <div class="vs-md-b">
                    <div class="mk-cfm">
                        <div v-for="it in kep.items" :key="it.kunci">
                            <b>{{ it.No_Sampel }}</b>
                            <span>{{ it.Nama_Aktivitas || (it.klasifikasi || []).map((k) => k.kode).join(' · ') }} · {{ it.Nama_Barang }}</span>
                        </div>
                    </div>
                    <!-- Revisi: rekomendasi lama disebut, dan tetap tersimpan di riwayat. -->
                    <div v-if="kep.revisi" class="vs-al mk-revisi">
                        <i class="ri-history-line"></i>
                        <span v-if="kep.lama">
                            <b>Merevisi rekomendasi {{ kep.lama.nama }}</b> dari {{ kep.lama.oleh.nama }} · {{ stamp(kep.lama.waktu, true) }}.
                            Rekomendasi sebelumnya tetap tersimpan di riwayat sampel.
                        </span>
                        <span v-else>
                            <b>Merevisi {{ kep.items.filter((it) => it.Verifikasi).length }} rekomendasi yang sudah tercatat.</b>
                            Rekomendasi sebelumnya tetap tersimpan di riwayat sampel.
                        </span>
                    </div>
                    <div v-if="tahap === 'finalisasi' && kep.items.length === 1 && kep.items[0].Saran" class="vs-al" :class="'mk-saran is-' + warnaKep(kep.items[0].Saran)">
                        <i class="ri-lightbulb-line"></i><span><b>Saran sistem: {{ namaKep(kep.items[0].Saran) }}.</b></span>
                    </div>
                    <label class="vs-lb">Tingkat keputusan</label>
                    <div v-if="!master.length" class="mk-kep-st" role="status">
                        <template v-if="masterStatus === 'memuat'">
                            <span class="spinner-border spinner-border-sm"></span>Memuat tingkat keputusan…
                        </template>
                        <template v-else>
                            <i class="ri-error-warning-line"></i>Tingkat keputusan belum dapat dimuat.
                            <button type="button" class="btn btn-link btn-sm p-0" @click="muatMaster">Coba lagi</button>
                        </template>
                    </div>
                    <div class="mk-keps">
                        <label v-for="k in master" :key="k.Kode_Keputusan" class="mk-kp" :class="['is-' + k.Warna_Badge, { 'is-on': kep.kode === k.Kode_Keputusan }]">
                            <input v-model="kep.kode" type="radio" :value="k.Kode_Keputusan" />
                            <span><b><i :class="k.Ikon"></i>{{ k.Nama_Keputusan }}</b>
                                <span class="mk-kp-tag" :class="{ 'is-opt': k.Flag_Wajib_Catatan !== 'Y' }">{{ k.Flag_Wajib_Catatan === 'Y' ? 'catatan wajib' : 'catatan opsional' }}</span>
                                <em>{{ k.Keterangan }}</em></span>
                        </label>
                    </div>
                    <!-- Catatan mengikuti bobot keputusan: ringkas / kondisi / justifikasi (aturanCatatan). -->
                    <div class="mk-cat-h">
                        <label class="vs-lb" @click="$refs.edCatatan && $refs.edCatatan.fokuskan()">
                            {{ aturan.judul }} <span v-if="aturan.wajib" class="text-danger">*</span>
                        </label>
                        <span v-if="kepAktif" class="mk-cat-tag" :class="'is-' + aturan.jenis">
                            {{ aturan.wajib ? 'Wajib · min. ' + angka(aturan.min) + ' karakter' : 'Opsional' }}
                        </span>
                    </div>
                    <p class="mk-cat-b" :class="'is-' + aturan.jenis">
                        <i :class="aturan.jenis === 'justifikasi' ? 'ri-file-shield-2-line' : aturan.jenis === 'kondisi' ? 'ri-error-warning-line' : 'ri-chat-3-line'"></i>
                        <span>{{ aturan.bantuan }}</span>
                    </p>
                    <EditorCatatan ref="edCatatan" v-model="kep.catatan" :min="aturan.min" :maks="aturan.maks" :wajib="aturan.wajib"
                                   :jenis="aturan.jenis" :istilah="istilahCatatan" :label="kepAktif ? kepAktif.Nama_Keputusan : ''"
                                   :placeholder="aturan.contoh || 'Pilih tingkat keputusan terlebih dahulu.'" />
                </div>
                <div class="vs-md-f">
                    <button class="btn btn-sm btn-light" @click="tutupKeputusan">Batal</button>
                    <button class="btn btn-sm btn-primary" :disabled="!kepAktif || !catatanCukup" @click="kirimKeputusan">
                        <i class="ri-send-plane-line me-1"></i>Simpan
                    </button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ================= JENDELA BACA: catatan rekomendasi ================= -->
        <Teleport to="body">
        <div v-if="bacaan" class="vs-bd" @click.self="bacaan = null">
            <div class="vs-md mk-baca" role="dialog" aria-modal="true" aria-labelledby="mk-baca-j">
                <div class="vs-md-h" :class="'bg-' + warnaBs(bacaan.warna)">
                    <i :class="ikonAkt(bacaan.kode)"></i>
                    <div><b id="mk-baca-j">{{ bacaan.judul }}</b><em>{{ bacaan.klasifikasi }} · {{ bacaan.keputusan }}</em></div>
                    <button class="vs-md-x" title="Tutup" @click="bacaan = null"><i class="ri-close-line"></i></button>
                </div>
                <div class="vs-md-b">
                    <div class="mk-baca-m">
                        <span><i class="ri-user-3-line"></i>{{ bacaan.oleh }}</span>
                        <span><i class="ri-time-line"></i>{{ bacaan.waktu }}</span>
                        <span v-if="bacaan.revisi"><i class="ri-history-line"></i>Revisi {{ bacaan.revisi }}</span>
                    </div>
                    <IsiCatatan :isi="bacaan.catatan" class="mk-baca-isi" />
                </div>
                <div class="vs-md-f">
                    <button class="btn btn-sm btn-light" @click="bacaan = null">Tutup</button>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>

<script>
import axios from "axios";
import LifecycleSampel from "../components/LifecycleSampel.vue";
import TabelHasilAnalisa from "../components/TabelHasilAnalisa.vue";
import EditorCatatan from "../components/EditorCatatan.vue";
import IsiCatatan from "../components/IsiCatatan.vue";
import GaleriFotoAnalisa from "../components/GaleriFotoAnalisa.vue";
import { aturanCatatan, bersihkanHtml, htmlKeTeks, istilahAnalisa, judulRiwayatCatatan, periksaCatatanHtml } from "../components/catatan";
import * as M from "./mockupSiklus";

const PRESET = [
    { kode: "layak", judul: "Semua layak", ket: "Seluruh hasil di dalam standar; WARNA 2 foto",
      opsi: { LCKV: "layak", ANL: "layak", PLT: "angka", foto_warna: 2, foto_tekstur: 0 } },
    { kode: "tidak_layak", judul: "Ada yang tidak layak", ket: "ASH & PROTEIN di luar batas, 2 Look View tidak layak; 5 foto",
      opsi: { LCKV: "tidak_layak", ANL: "tidak_layak", PLT: "angka", foto_warna: 5, foto_tekstur: 0 } },
    { kode: "tanpa_master", judul: "Belum ada di master", ket: "Tanpa batas min–max & hasil Look View tidak terdaftar",
      opsi: { LCKV: "tanpa_master", ANL: "tanpa_master", PLT: "angka", foto_warna: 1, foto_tekstur: 0 } },
    { kode: "campuran", judul: "Campuran", ket: "Layak, tidak layak, dan belum ada di master sekaligus",
      opsi: { LCKV: "campuran", ANL: "campuran", PLT: "angka", foto_warna: 2, foto_tekstur: 5 } },
];

const JUDUL = {
    validasi: { t: "Validasi Trial Produksi", s: "Periksa hasil uji tiap analisa sebelum diteruskan ke verifikasi.",
        hitung: "klasifikasi menunggu validasi", kosong: "Tidak ada analisa yang menunggu validasi akun ini.",
        pilih: "Pilih sampel untuk divalidasi", pilihKet: "Hasil uji, kriteria kelayakan, dan foto analisa tampil di sini.",
        ikon: "ri-test-tube-line", aksi: "Validasi", jejak: "Validasi tercatat di riwayat sampel, lalu diteruskan ke verifikasi" },
    verifikasi: { t: "Verifikasi Hasil Analisa", s: "Beri rekomendasi tiap klasifikasi yang sudah tervalidasi.",
        hitung: "klasifikasi", kosong: "Tidak ada klasifikasi yang menunggu rekomendasi akun ini.",
        pilih: "Pilih klasifikasi untuk diverifikasi", pilihKet: "Hasil tervalidasi beserta validatornya tampil di sini.",
        ikon: "ri-shield-check-line", aksi: "Rekomendasi", jejak: "Rekomendasi tercatat di riwayat sampel" },
    finalisasi: { t: "Finalisasi Trial Produksi", s: "Tetapkan keputusan akhir sampel yang seluruh klasifikasinya sudah direkomendasikan.",
        hitung: "sampel", kosong: "Belum ada sampel yang siap difinalisasi.",
        pilih: "Pilih sampel untuk difinalisasi", pilihKet: "Rekomendasi tiap klasifikasi dan hasil analisanya tampil di sini.",
        ikon: "ri-flag-2-line", aksi: "Finalisasi", jejak: "Keputusan akhir tercatat di riwayat sampel" },
    hasil: { t: "Hasil Analisa", s: "Hasil analisa sampel trial produksi yang sudah difinalisasi.",
        hitung: "hasil analisa final", kosong: "Belum ada sampel yang difinalisasi.",
        pilih: "Pilih hasil analisa", pilihKet: "Keputusan akhir, rekomendasi, dan seluruh hasil uji tampil di sini.",
        ikon: "ri-file-chart-line", aksi: "", jejak: "" },
};

export default {
    name: "MockupSiklus",
    components: {
        LifecycleSampel,
        TabelHasilAnalisa,
        EditorCatatan,
        IsiCatatan,
        GaleriFotoAnalisa,
    },
    props: {
        tahap: { type: String, required: true },
        pengguna: { type: Object, required: true },
        tahapan: { type: Array, default: () => [] },
        hakValidasi: { type: Array, default: () => [] },
        kewenangan: { type: [Object, Array], default: () => ({}) },
        akun: { type: Array, default: () => [] },
    },
    data() {
        return {
            state: M.baca(),
            master: [],
            masterStatus: "memuat", // 'memuat' | 'siap' | 'gagal'
            skenario: { layak: "Semua layak", tidak_layak: "Ada yang tidak layak", tanpa_master: "Belum ada di master", campuran: "Campuran" },
            preset: PRESET,
            cari: "",
            saring: this.tahap === "finalisasi" ? "siap" : "menunggu",
            terpilihKunci: null,
            dipilih: [],
            menuCth: false,
            paramBuka: true,

            contoh: { show: false, proses: false, err: "", jumlah: 1, opsi: { ...PRESET[0].opsi } },
            val: { show: false, satu: true, items: [], analisa: [], centang: {} },
            ulang: { show: false, id: null, sub: "", alasan: "" },
            kep: { show: false, items: [], kode: "", catatan: "" },

            fotoUrl: {}, fotoGagal: {}, fotoProses: {},
            galeri: { show: false, i: 0 },
            bacaan: null, // jendela baca catatan rekomendasi
            usapX: null,

            panelKanan: true,
            lebar: 0, gulirDaftar: 0, bawah: 0, lcLebar: 380, lcMaks: false, geser: false,
        };
    },
    computed: {
        judul() { return JUDUL[this.tahap] || JUDUL.validasi; },
        tunggal() { return this.lebar > 0 && this.lebar < 768; },
        laci() { return this.lebar > 0 && this.lebar < 1024; },
        ukuran() {
            const w = this.lebar;
            if (!w || w >= 1440) return "xl";
            if (w >= 1100) return "lg";
            if (w >= 768) return "md";
            return w >= 576 ? "sm" : "xs";
        },
        kelasTata() { return ["uk-" + this.ukuran, { "is-tunggal": this.tunggal, "is-laci": this.laci }]; },
        gayaKanan() { return this.lcMaks || this.laci ? {} : { width: this.lcLebar + "px" }; },
        kunciGulir() {
            return this.galeri.show || this.contoh.show || this.val.show || this.ulang.show || this.kep.show || !!this.bacaan
                || (this.tunggal && this.panelKanan && !!this.terpilih);
        },

        peranTeks() {
            const n = { UJI: "Uji", VAL: "Validasi", VER: "Verifikasi", FIN: "Finalisasi", REV: "Hak revisi" };
            return this.tahapan.filter((t) => t !== "UJI").map((t) => n[t]).join(" · ") || "—";
        },
        hitung() { return M.hitungTahap(this.state); },
        langkah() {
            const t = this.tahapan;
            return [
                { kode: "validasi", label: "Validasi", url: "/mockup/validasi", ikon: "ri-test-tube-line", n: this.hitung.validasi, boleh: t.includes("VAL") },
                { kode: "verifikasi", label: "Verifikasi", url: "/mockup/verifikasi", ikon: "ri-shield-check-line", n: this.hitung.verifikasi, boleh: t.includes("VER") },
                { kode: "finalisasi", label: "Finalisasi", url: "/mockup/finalisasi", ikon: "ri-flag-2-line", n: this.hitung.finalisasi, boleh: t.includes("FIN") },
                { kode: "hasil", label: "Hasil Analisa", url: "/mockup/hasil-analisa", ikon: "ri-file-chart-line", n: this.hitung.hasil, boleh: true },
            ];
        },
        kewenanganObj() { return Array.isArray(this.kewenangan) ? {} : this.kewenangan; },
        /** Saringan Verifikasi. Finalisasi tidak bersaringan: hanya sampel yang siap difinalisasi. */
        opsiSaring() {
            return [
                { kode: "menunggu", label: "Menunggu", n: M.antreanVerifikasi(this.state, this.kewenanganObj, "menunggu").length },
                { kode: "sudah", label: "Sudah diverifikasi", n: M.antreanVerifikasi(this.state, this.kewenanganObj, "sudah").length },
            ];
        },
        daftarSemua() {
            if (this.tahap === "validasi") return M.antreanValidasi(this.state, this.hakValidasi.map(Number));
            if (this.tahap === "verifikasi") return M.antreanVerifikasi(this.state, this.kewenanganObj, this.saring);
            if (this.tahap === "finalisasi") return M.antreanFinalisasi(this.state, this.saring);
            return M.daftarHasil(this.state);
        },
        daftar() {
            const q = this.cari.trim().toLowerCase();
            if (!q) return this.daftarSemua;
            const kata = q.split(/\s+/);
            return this.daftarSemua.filter((it) => {
                const teks = [it.No_Sampel, it.No_Po, it.Kode_Barang, it.Nama_Barang, it.Kode_Formula,
                    it.Nama_Aktivitas, it.Judul].join(" | ").toLowerCase();
                return kata.every((k) => teks.includes(k));
            });
        },
        bisaMassal() {
            if (this.tahap === "verifikasi") return this.saring !== "sudah" || this.hakRevisi;
            return this.tahap === "validasi" || (this.tahap === "finalisasi" && this.saring === "siap");
        },
        /** Hak merevisi rekomendasi yang sudah tercatat (peran sandbox 'REV'). */
        hakRevisi() { return this.tahapan.includes("REV"); },
        /** Rekomendasi klasifikasi terpilih boleh direvisi: berhak & sampel belum difinalisasi. */
        bolehRevisi() { return this.hakRevisi && !!this.sampel && !this.sampel.finalisasi; },
        /** Klasifikasi terpilih sudah direkomendasikan dan akun ini tidak boleh merevisinya. */
        terkunci() { return this.tahap === "verifikasi" && !!this.klas && !!this.klas.verifikasi && !this.bolehRevisi; },
        alasanKunci() {
            if (this.sampel && this.sampel.finalisasi) return "Sampel sudah difinalisasi — rekomendasi tidak dapat diubah.";
            return "Rekomendasi sudah tercatat. Revisi hanya untuk akun dengan hak revisi.";
        },
        semuaDipilih() { return this.daftar.length > 0 && this.daftar.every((it) => this.dipilih.includes(it.kunci)); },
        sebagianDipilih() { return this.daftar.some((it) => this.dipilih.includes(it.kunci)); },

        terpilih() { return this.daftarSemua.find((it) => it.kunci === this.terpilihKunci) || null; },
        sampel() { return this.terpilih ? M.cariSampel(this.state, this.terpilih.No_Sampel) : null; },
        klas() {
            return this.terpilih && this.terpilih.Kode_Aktivitas_Lab
                ? M.cariKlasifikasi(this.sampel, this.terpilih.Kode_Aktivitas_Lab) : null;
        },
        hakSet() { return new Set(this.hakValidasi.map(Number)); },
        /** Baris tabel: validasi = yang menunggu & dalam hak; verifikasi = seluruh baris tervalidasi. */
        barisTabel() {
            if (!this.klas) return [];
            if (this.tahap !== "validasi") return this.klas.analisa;
            return this.klas.analisa.filter((a) => a._status === "menunggu" && this.hakSet.has(Number(a.Id_Jenis_Analisa)));
        },
        idTabel() { return new Set(this.barisTabel.map((a) => Number(a.Id_Jenis_Analisa))); },
        matriksTabel() {
            return (this.klas && this.klas.Matriks_Pembanding || []).filter((m) => this.idTabel.has(Number(m.Id_Jenis_Analisa)));
        },
        jumlahJenis() { return M.perAnalisa(this.barisTabel).length; },
        jumlahTL() {
            if (!this.klas || this.klas.Butuh_Pembanding) return 0;
            return M.perAnalisa(this.barisTabel).filter((g) => M.layakAnalisa(g.baris, false) === "T").length;
        },
        jumlahTM() {
            if (!this.klas || this.klas.Butuh_Pembanding) return 0;
            return M.perAnalisa(this.barisTabel).filter((g) => M.layakAnalisa(g.baris, false) === "N").length;
        },
        ulangTertunda() {
            if (!this.klas) return [];
            return this.klas.resampling.filter((r) => !r.selesai && this.hakSet.has(Number(r.id)));
        },
        /** Parameter perhitungan baris yang tampil (uji ulang memakai parameter asalnya). */
        parameterTabel() {
            if (!this.klas) return [];
            return (this.klas.parameter || [])
                .filter((p) => this.idTabel.has(Number(p.Id_Jenis_Analisa)))
                .map((p) => {
                    const baris = [];
                    this.barisTabel.filter((a) => Number(a.Id_Jenis_Analisa) === Number(p.Id_Jenis_Analisa))
                        .forEach((a) => {
                            if (baris.some((b) => b.No_Faktur === a.No_Faktur)) return;
                            const asal = p.baris.find((b) => b.No_Faktur === String(a.No_Faktur).replace(/-R\d+$/, ""));
                            if (asal) baris.push({ ...asal, No_Faktur: a.No_Faktur, No_Sampel_Uji: a.No_Sampel_Uji, Hasil: a.Hasil });
                        });
                    return { ...p, baris };
                })
                .filter((p) => p.baris.length);
        },
        badgePosisi() {
            if (!this.sampel) return {};
            const lc = this.lcData;
            const t = lc ? lc.posisi.tingkat : "run";
            return {
                label: lc ? lc.posisi.label : "",
                cls: { ok: "bg-success-subtle text-success", warn: "bg-warning-subtle text-warning", bad: "bg-danger-subtle text-danger" }[t] || "bg-light text-muted",
                ikon: this.sampel.finalisasi ? "ri-flag-2-line" : "ri-time-line",
            };
        },
        /** Validasi & Verifikasi: hanya klasifikasi yang dibuka. Finalisasi & Hasil: seluruhnya. */
        lcData() {
            if (!this.sampel) return null;
            const perKlasifikasi = this.tahap === "validasi" || this.tahap === "verifikasi";
            return M.lifecycle(this.sampel, perKlasifikasi && this.klas ? this.klas.kode : null);
        },
        keputusanLc() {
            if (!this.klas) return null;
            return {
                klasifikasi: this.klas.nama,
                verifikasi: this.klas.petugas_verifikasi || [],
                finalisasi: this.akun.filter((a) => (a.tahapan || []).includes("FIN")).map((a) => ({ id: a.id, nama: a.nama })),
            };
        },
        /** Antrean yang menunggu akun lain — ditampilkan saat antrean akun ini kosong. */
        menungguLain() {
            if (this.tahap === "validasi") {
                const saya = new Set(this.daftarSemua.map((x) => x.kunci));
                return M.antreanValidasi(this.state, null).filter((x) => !saya.has(x.kunci)).map((x) => {
                    const k = M.cariKlasifikasi(M.cariSampel(this.state, x.No_Sampel), x.Kode_Aktivitas_Lab);
                    const ids = new Set([...k.analisa.filter((a) => a._status === "menunggu").map((a) => Number(a.Id_Jenis_Analisa)),
                        ...k.resampling.filter((r) => !r.selesai).map((r) => r.id)]);
                    const akun = this.akun.filter((a) => (a.validasi || []).some((id) => ids.has(Number(id)))).map((a) => a.nama);
                    return { kunci: x.kunci, no: x.No_Sampel, klas: x.Nama_Aktivitas, akun: akun.join(", ") };
                });
            }
            if (this.tahap === "verifikasi" && this.saring === "menunggu") {
                const saya = new Set(this.daftarSemua.map((x) => x.kunci));
                return M.antreanVerifikasi(this.state, { "*": null }, "menunggu").filter((x) => !saya.has(x.kunci)).map((x) => {
                    const k = M.cariKlasifikasi(M.cariSampel(this.state, x.No_Sampel), x.Kode_Aktivitas_Lab);
                    return { kunci: x.kunci, no: x.No_Sampel, klas: x.Nama_Aktivitas,
                             akun: (k.petugas_verifikasi || []).map((p) => p.nama).join(", ") };
                });
            }
            if (this.tahap === "finalisasi") {
                // Sampel yang belum masuk antrean finalisasi: klasifikasi yang masih
                // menunggu validasi atau verifikasi, beserta akun yang harus bertindak.
                return this.state.sampel.filter((s) => !s.finalisasi && !M.siapFinal(s)).map((s) => {
                    const belum = s.klasifikasi.filter((k) => !k.verifikasi);
                    const akun = new Set();
                    belum.forEach((k) => {
                        if (M.tervalidasi(k)) (k.petugas_verifikasi || []).forEach((p) => akun.add(p.nama));
                        else {
                            const ids = new Set(k.analisa.filter((a) => a._status === "menunggu").map((a) => Number(a.Id_Jenis_Analisa)));
                            this.akun.filter((a) => (a.validasi || []).some((id) => ids.has(Number(id)))).forEach((a) => akun.add(a.nama));
                        }
                    });
                    return { kunci: s.No_Sampel, no: s.No_Sampel, akun: [...akun].join(", "),
                             klas: belum.map((k) => `${k.nama} (${M.tervalidasi(k) ? "verifikasi" : "validasi"})`).join(", ") };
                });
            }
            return [];
        },

        foto() {
            const sumber = this.klas
                ? this.barisTabel.map((a) => ({ a, kode: this.klas.kode }))
                : (this.sampel ? this.sampel.klasifikasi.flatMap((k) => k.analisa.map((a) => ({ a, kode: k.kode }))) : []);
            const sudah = new Set();
            const hasil = [];
            sumber.forEach(({ a, kode }) => (a.Foto || []).forEach((f) => {
                if (!f.key || sudah.has(f.key)) return;
                sudah.add(f.key);
                hasil.push({
                    key: f.key, kode, keterangan: f.keterangan || "", idAnalisa: a.Id_Jenis_Analisa,
                    analisa: a.Nama_Jenis_Analisa, sampel: a.No_Sampel_Uji,
                    hasil: a.Nilai_Hasil_String || this.angka(a.Hasil), layak: this.layakFoto(a),
                    input: a.Input, validasi: a.Validasi,
                });
            }));
            return hasil;
        },
        /** Foto per klasifikasi, dikelompokkan per analisa berfoto (urutan tabel). */
        fotoPerKlas() {
            const hasil = {};
            this.foto.forEach((f) => {
                const grup = (hasil[f.kode] = hasil[f.kode] || []);
                let g = grup.find((x) => x.id === f.idAnalisa);
                if (!g) {
                    g = { id: f.idAnalisa, analisa: f.analisa, layak: f.layak, hasil: f.hasil, foto: [] };
                    grup.push(g);
                }
                g.foto.push(f);
            });
            return hasil;
        },
        /** Ringkasan per klasifikasi untuk kartu rekomendasi (Finalisasi & Hasil). */
        ringkasKlas() {
            const hasil = {};
            (this.sampel ? this.sampel.klasifikasi : []).forEach((k) => {
                const g = M.perAnalisa(k.analisa);
                const nilai = k.Butuh_Pembanding ? [] : g.map((x) => M.layakAnalisa(x.baris, false));
                hasil[k.kode] = {
                    analisa: g.length, rs: k.resampling.length,
                    tl: nilai.filter((v) => v === "T").length, tm: nilai.filter((v) => v === "N").length,
                };
            });
            return hasil;
        },
        fotoAktif() { return this.foto[this.galeri.i] || null; },

        semuaTidak() { const o = this.contoh.opsi; return o.LCKV === "tidak" && o.ANL === "tidak" && o.PLT === "tidak"; },
        jumlahDicentang() {
            if (!this.val.show) return 0;
            if (!this.val.satu) return this.val.items.reduce((n, it) => n + it.Jumlah_Analisa, 0);
            return this.val.analisa.filter((a) => this.val.centang[a.id]).length;
        },
        daftarUlang() {
            return M.perAnalisa(this.barisTabel).map((g) => ({
                id: g.id, nama: g.nama, layak: M.layakAnalisa(g.baris, this.klas && this.klas.Butuh_Pembanding),
            }));
        },
        ulangAnalisa() { return this.daftarUlang.find((a) => a.id === this.ulang.id) || null; },
        /** Sub sampel QR yang belum dipakai sampel ini. */
        opsiSub() {
            if (!this.sampel) return [];
            const dipakai = new Set(this.sampel.klasifikasi.flatMap((k) => [...k.analisa, ...k.riwayat.flatMap((r) => r.baris)])
                .map((a) => a.No_Sampel_Uji));
            this.sampel.klasifikasi.forEach((k) => k.resampling.forEach((r) => dipakai.add(r.baru)));
            return Array.from({ length: 9 }, (_, i) => `${this.sampel.No_Sampel}-${i + 1}`).filter((s) => !dipakai.has(s)).slice(0, 5);
        },
        kepAktif() { return this.master.find((k) => k.Kode_Keputusan === this.kep.kode) || null; },
        /**
         * Aturan catatan untuk keputusan terpilih: Direkomendasikan opsional
         * (maks. 500), Bersyarat wajib 50–1.000, Tidak Direkomendasikan wajib
         * 100–1.500 — beserta judul, teks bantuan, dan contohnya.
         */
        aturan() { return aturanCatatan(this.kepAktif, this.tahap === "finalisasi" ? "finalisasi" : "verifikasi"); },
        /** Kata kunci nama analisa yang diputuskan — untuk mengenali temuan pada poin panduan. */
        istilahCatatan() {
            const nama = [];
            this.kep.items.forEach((it) => {
                const s = M.cariSampel(this.state, it.No_Sampel);
                if (!s) return;
                s.klasifikasi.filter((k) => !it.Kode_Aktivitas_Lab || k.kode === it.Kode_Aktivitas_Lab)
                    .forEach((k) => k.analisa.forEach((a) => nama.push(a.Nama_Jenis_Analisa)));
            });
            return istilahAnalisa(nama);
        },
        /** Penilaian yang sama dengan yang tampil di bawah editor. */
        statusCatatan() {
            return periksaCatatanHtml(this.kep.catatan, { min: this.aturan.min, maks: this.aturan.maks, wajib: this.aturan.wajib,
                label: this.kepAktif ? this.kepAktif.Nama_Keputusan : "" });
        },
        catatanCukup() { return this.statusCatatan.ok; },
    },
    watch: {
        kunciGulir(v) { document.body.style.overflow = v ? "hidden" : ""; },
        // Daftar berubah (aksi / saringan): pastikan ada yang terbuka di layar lebar.
        daftarSemua() { this.pastikanTerpilih(); },
        foto() { this.muatFoto(); },
    },
    async mounted() {
        this.panelKanan = this.pilihanKanan();
        this.ukurUlang(this.$refs.akar.getBoundingClientRect().width);
        this.pengamat = new ResizeObserver((e) => this.ukurUlang(e[0].contentRect.width));
        this.pengamat.observe(this.$refs.akar);
        this.muatLebar();
        window.addEventListener("keydown", this.tombolPintas);
        document.addEventListener("pointerdown", this.tutupMenuLuar);
        // Tab lain mengubah data mockup: ikut diperbarui.
        window.addEventListener("storage", this.dataBerubah);
        this.pastikanTerpilih();
        await this.muatMaster();
    },
    beforeUnmount() {
        if (this.pengamat) this.pengamat.disconnect();
        document.body.style.overflow = "";
        window.removeEventListener("keydown", this.tombolPintas);
        document.removeEventListener("pointerdown", this.tutupMenuLuar);
        window.removeEventListener("storage", this.dataBerubah);
        if (this.geser) this.selesaiGeser();
        Object.values(this.fotoUrl).forEach((u) => URL.revokeObjectURL(u));
    },
    methods: {
        /* ---------- penyimpanan ---------- */
        simpan() {
            if (!M.simpan(this.state)) {
                this.kabar("error", "Penyimpanan penuh", "Penyimpanan browser penuh. Kosongkan data mockup lalu coba lagi.");
            }
        },

        dataBerubah(e) {
            if (e.key === M.KUNCI || e.key === null) this.state = M.baca();
        },

        /* ---------- daftar ---------- */
        pastikanTerpilih() {
            if (this.terpilihKunci && this.terpilih) return;
            this.terpilihKunci = !this.tunggal && this.daftarSemua.length ? this.daftarSemua[0].kunci : null;
            this.dipilih = this.dipilih.filter((k) => this.daftarSemua.some((x) => x.kunci === k));
        },
        gantiSaring(k) { this.saring = k; this.dipilih = []; this.terpilihKunci = null; this.pastikanTerpilih(); },
        buka(it) {
            if (this.tunggal && !this.terpilih) this.gulirDaftar = window.scrollY;
            this.terpilihKunci = it.kunci;
            if (this.tunggal) window.scrollTo({ top: 0 });
        },
        kembali() {
            this.terpilihKunci = null;
            this.tutupKanan();
            this.$nextTick(() => window.scrollTo({ top: this.gulirDaftar }));
        },
        togglePilih(it) {
            const i = this.dipilih.indexOf(it.kunci);
            if (i > -1) this.dipilih.splice(i, 1); else this.dipilih.push(it.kunci);
        },
        pilihSemua() {
            if (this.semuaDipilih) this.dipilih = this.dipilih.filter((k) => !this.daftar.some((x) => x.kunci === k));
            else this.daftar.forEach((it) => {
                if (!this.dipilih.includes(it.kunci) && !(this.tahap === "finalisasi" && !it.Siap)) this.dipilih.push(it.kunci);
            });
        },
        aksiMassal() {
            const items = this.daftarSemua.filter((x) => this.dipilih.includes(x.kunci));
            if (!items.length) return;
            if (this.tahap === "validasi") this.bukaValidasi(items);
            else this.bukaKeputusan(items);
        },

        /* ---------- contoh data ---------- */
        tutupMenuLuar(e) {
            if (this.menuCth && this.$refs.menuCth && !this.$refs.menuCth.contains(e.target)) this.menuCth = false;
        },
        bukaContoh(kode) {
            this.menuCth = false;
            const p = PRESET.find((x) => x.kode === kode);
            this.contoh = { show: true, proses: false, err: "", jumlah: 1, opsi: { ...(p || PRESET[0]).opsi } };
        },
        terapkanPreset(p) { this.contoh.opsi = { ...p.opsi }; },
        contohCocok(p) { return Object.keys(p.opsi).every((k) => p.opsi[k] === this.contoh.opsi[k]); },
        /** Frontend -> backend: server menyusun contoh dari master, browser menyimpannya. */
        async kirimContoh() {
            this.contoh.proses = true;
            this.contoh.err = "";
            try {
                const nomor = M.nomorBaru(this.state, this.contoh.jumlah);
                const r = await axios.post("/api/v1/mockup/contoh", { nomor, sekarang: M.sekarang(), opsi: this.contoh.opsi });
                const baru = r.data?.result || [];
                M.tambahContoh(this.state, baru);
                this.simpan();
                this.contoh.show = false;
                this.terpilihKunci = null;
                this.$nextTick(() => {
                    const pertama = this.daftarSemua.find((x) => baru.some((b) => b.No_Sampel === x.No_Sampel));
                    if (pertama) this.terpilihKunci = pertama.kunci; else this.pastikanTerpilih();
                });
                this.kabar("success", "Contoh data dibuat", `${nomor.join(", ")} menunggu validasi.`);
            } catch (e) {
                this.contoh.err = e.response?.data?.message || "Gagal membuat contoh data.";
            } finally {
                this.contoh.proses = false;
            }
        },
        async hapusSemua() {
            this.menuCth = false;
            if (!(await this.tanya("Kosongkan data mockup?", "Seluruh sampel mockup di browser ini dihapus.", "Ya, kosongkan", "#f06548"))) return;
            this.state = M.kosongkan();
            this.terpilihKunci = null;
            this.dipilih = [];
        },

        /* ---------- validasi ---------- */
        bukaValidasi(items) {
            const satu = items.length === 1 && this.terpilih && items[0].kunci === this.terpilih.kunci;
            const analisa = satu ? M.perAnalisa(this.barisTabel).map((g) => ({
                id: g.id, nama: g.nama, layak: M.layakAnalisa(g.baris, this.klas.Butuh_Pembanding),
                sub: g.baris[0].No_Sampel_Uji, putaran: g.baris[0]._putaran,
            })) : [];
            const centang = {};
            analisa.forEach((a) => { centang[a.id] = true; });
            this.val = { show: true, satu, items: [...items], analisa, centang };
        },
        kirimValidasi() {
            let n = 0;
            if (this.val.satu) {
                const it = this.val.items[0];
                n = M.validasi(this.state, it.No_Sampel, it.Kode_Aktivitas_Lab,
                    this.val.analisa.filter((a) => this.val.centang[a.id]).map((a) => a.id), this.pengguna);
            } else {
                this.val.items.forEach((it) => {
                    const k = M.cariKlasifikasi(M.cariSampel(this.state, it.No_Sampel), it.Kode_Aktivitas_Lab);
                    const ids = M.perAnalisa(k.analisa.filter((a) => a._status === "menunggu" && this.hakSet.has(Number(a.Id_Jenis_Analisa))))
                        .map((g) => g.id);
                    n += M.validasi(this.state, it.No_Sampel, it.Kode_Aktivitas_Lab, ids, this.pengguna);
                });
                this.dipilih = [];
            }
            this.simpan();
            this.val.show = false;
            this.kabar("success", "Tervalidasi", `${n} analisa divalidasi dan diteruskan ke verifikasi.`);
        },

        /* ---------- uji ulang ---------- */
        bukaUjiUlang() {
            const awal = this.daftarUlang.find((a) => a.layak === "T") || this.daftarUlang[0];
            this.ulang = { show: true, id: awal ? awal.id : null, sub: this.opsiSub[0] || "", alasan: "" };
        },
        kirimUjiUlang() {
            const u = this.ulang;
            const ok = M.ujiUlang(this.state, this.sampel.No_Sampel, this.klas.kode, u.id, u.alasan.trim(),
                this.klas.Multi ? u.sub : null, this.pengguna);
            if (!ok) return;
            this.simpan();
            u.show = false;
            this.kabar("success", "Uji ulang diminta", `${this.ulangAnalisa ? this.ulangAnalisa.nama : "Analisa"} menunggu hasil uji ulang dari analis.`);
        },
        hasilUlang(r, hasil) {
            if (M.masukkanUjiUlang(this.state, this.sampel.No_Sampel, this.klas.kode, r.id, hasil)) {
                this.simpan();
                this.kabar("success", "Hasil uji ulang masuk", `${r.nama} putaran ${r.putaran + 1} menunggu validasi.`);
            }
        },

        /* ---------- keputusan (verifikasi / finalisasi) ---------- */
        bukaKeputusan(items) {
            // Rekomendasi yang sudah tercatat hanya boleh diubah akun berhak revisi,
            // dan tidak lagi setelah sampel difinalisasi.
            const revisi = this.tahap === "verifikasi" && items.some((it) => it.Verifikasi);
            if (revisi && !this.hakRevisi) {
                this.kabar("warning", "Revisi tidak diizinkan", "Rekomendasi yang sudah tercatat hanya dapat direvisi oleh akun dengan hak revisi.");
                return;
            }
            if (revisi && items.some((it) => (M.cariSampel(this.state, it.No_Sampel) || {}).finalisasi)) {
                this.kabar("warning", "Revisi tidak diizinkan", "Sampel sudah difinalisasi — rekomendasinya tidak dapat diubah.");
                return;
            }
            const saran = this.tahap === "finalisasi" && items.length === 1 ? items[0].Saran : null;
            const lama = this.tahap === "verifikasi" && items.length === 1 ? items[0].Verifikasi : null;
            const catatan = (lama && lama.catatan) || "";
            // Master yang masih dimuat tidak menghalangi: pilihan (saran/keputusan
            // lama) langsung aktif begitu master tiba; bila gagal, modal
            // menampilkan tombol muat ulang.
            this.kep = { show: true, items: [...items], kode: (lama && lama.kode) || saran || "", catatan, catatanAwal: catatan,
                revisi, lama };
            if (!this.master.length && this.masterStatus !== "memuat") this.muatMaster();
        },
        /** Master tingkat keputusan (dan skenario contoh data). */
        async muatMaster() {
            this.masterStatus = "memuat";
            try {
                const r = await axios.get("/api/v1/mockup/master");
                this.master = r.data?.result?.keputusan || [];
                this.skenario = r.data?.result?.skenario || this.skenario;
                this.masterStatus = this.master.length ? "siap" : "gagal";
            } catch {
                this.masterStatus = "gagal";
            }
        },
        /** Tutup modal keputusan; catatan yang sudah ditulis tidak hilang tanpa konfirmasi. */
        async tutupKeputusan() {
            const berubah = (this.kep.catatan || "") !== (this.kep.catatanAwal || "") && htmlKeTeks(this.kep.catatan).trim();
            if (berubah && !(await this.tanya("Tutup tanpa menyimpan?",
                "Catatan yang sudah ditulis belum tersimpan dan akan hilang.", "Ya, tutup", "#f06548"))) return;
            this.kep.show = false;
        },
        judulCatatan(kode, tahap) {
            return judulRiwayatCatatan(kode, tahap);
        },
        kirimKeputusan() {
            const k = this.kepAktif;
            if (!k || !this.catatanCukup) return;
            // Disimpan sebagai HTML yang sudah disaring; kosong bila tidak diisi.
            const catatan = bersihkanHtml(this.kep.catatan);
            let n = 0;
            this.kep.items.forEach((it) => {
                const ok = this.tahap === "verifikasi"
                    ? M.verifikasi(this.state, it.No_Sampel, it.Kode_Aktivitas_Lab, k, catatan, this.pengguna)
                    : M.finalisasi(this.state, it.No_Sampel, k, catatan, this.pengguna);
                if (ok) n++;
            });
            this.simpan();
            this.kep.show = false;
            this.dipilih = [];
            this.kabar("success", this.tahap === "verifikasi" ? (this.kep.revisi ? "Rekomendasi direvisi" : "Rekomendasi tersimpan") : "Sampel difinalisasi",
                `${n} ${this.tahap === "verifikasi" ? "klasifikasi" : "sampel"}: ${k.Nama_Keputusan}.`);
        },

        /* ---------- notifikasi ---------- */
        kabar(ikon, judul, teks) {
            const S = window.Swal;
            if (S) S.fire({ icon: ikon, title: judul, text: teks || "", timer: ikon === "success" ? 2200 : undefined,
                showConfirmButton: ikon !== "success", willOpen: this.swalDiAtas });
            else window.alert(judul + (teks ? "\n" + teks : ""));
        },
        async tanya(judul, teks, tombol, warna = "#0ab39c") {
            const S = window.Swal;
            if (!S) return window.confirm(judul + "\n" + teks);
            const r = await S.fire({ title: judul, text: teks, icon: "question", showCancelButton: true,
                confirmButtonText: tombol, cancelButtonText: "Batal", confirmButtonColor: warna, reverseButtons: true,
                willOpen: this.swalDiAtas });
            return !!r.isConfirmed;
        },
        /** SweetAlert (z-index 1060) harus tampil di atas modal halaman ini (.vs-bd, 10070). */
        swalDiAtas() {
            const c = window.Swal && window.Swal.getContainer();
            if (c) c.style.zIndex = "10090";
        },

        /* ---------- foto (endpoint stream modul lab) ---------- */
        /**
         * Token foto hanya berlaku 30 detik dan sekali pakai, sedangkan satu
         * foto dari GCS butuh ±2 detik. Karena itu token diminta per kelompok
         * kecil tepat sebelum diunduh (paralel) — bukan sekaligus di awal, yang
         * membuat antrean belakang kedaluwarsa (401) — dan sekali diulang
         * dengan token baru bila gagal.
         */
        async muatFoto() {
            const kunci = this.foto.map((f) => f.key).filter((k) => !this.fotoUrl[k] && !this.fotoGagal[k] && !this.fotoProses[k]);
            if (!kunci.length) return;
            kunci.forEach((k) => { this.fotoProses[k] = true; });
            const token = async (keys) => {
                try { return (await axios.post("/api/v1/lab/hasil-uji/berkas/foto/token/bulk", { keys })).data || {}; } catch { return {}; }
            };
            const unduh = async (k, t) => {
                const r = await axios.get(`/api/v1/lab/berkas/stream/foto-uji/${k}`, { params: { token: t }, responseType: "blob" });
                this.fotoUrl = { ...this.fotoUrl, [k]: URL.createObjectURL(r.data) };
            };
            for (let i = 0; i < kunci.length; i += 4) {
                const bagian = kunci.slice(i, i + 4);
                const tok = await token(bagian);
                await Promise.all(bagian.map(async (k) => {
                    try {
                        await unduh(k, tok[k]);
                    } catch {
                        try { await unduh(k, (await token([k]))[k]); } catch { this.fotoGagal = { ...this.fotoGagal, [k]: true }; }
                    }
                }));
            }
        },
        bukaFotoAnalisa(a) {
            const i = this.foto.findIndex((f) => f.idAnalisa === a.Id_Jenis_Analisa);
            this.bukaGaleri(i > -1 ? i : 0);
        },
        bukaFotoKey(key) {
            const i = this.foto.findIndex((f) => f.key === key);
            this.bukaGaleri(i > -1 ? i : 0);
        },

        /* ---------- jendela baca catatan rekomendasi ---------- */
        bacaCatatan(k) {
            const v = k.verifikasi;
            if (!v || !v.catatan) return;
            this.bacaan = {
                kode: k.kode, klasifikasi: k.nama, keputusan: v.nama, warna: v.warna, catatan: v.catatan,
                judul: judulRiwayatCatatan(v.kode, "verifikasi"), oleh: v.oleh.nama,
                waktu: this.stamp(v.waktu, true), revisi: v.revisi || 0,
            };
        },
        ikonStatus(k) {
            if (!k.verifikasi) return "ri-time-line";
            return { ok: "ri-checkbox-circle-line", warn: "ri-error-warning-line", bad: "ri-close-circle-line" }[k.verifikasi.warna]
                || "ri-shield-check-line";
        },
        bukaGaleri(i) { if (this.foto.length) this.galeri = { show: true, i }; },
        geserGaleri(d) { const n = this.foto.length; if (n) this.galeri.i = (this.galeri.i + d + n) % n; },
        mulaiUsap(e) { this.usapX = e.changedTouches[0].clientX; },
        akhiriUsap(e) {
            if (this.usapX === null) return;
            const dx = e.changedTouches[0].clientX - this.usapX;
            this.usapX = null;
            if (Math.abs(dx) > 50) this.geserGaleri(dx < 0 ? 1 : -1);
        },

        /* ---------- tata letak ---------- */
        ukurUlang(w) {
            const laciSebelum = this.laci;
            this.lebar = Math.round(w);
            const nav = document.getElementById("mobileBottomNav");
            this.bawah = nav && getComputedStyle(nav).display !== "none" ? Math.round(nav.getBoundingClientRect().height) : 0;
            if (this.laci && !laciSebelum) { this.panelKanan = false; this.lcMaks = false; }
            else if (!this.laci && laciSebelum) this.panelKanan = this.pilihanKanan();
            this.lcLebar = this.batasLebar(this.lcLebar);
        },
        tombolPintas(e) {
            // Selama SweetAlert terbuka, tombol (termasuk Esc) miliknya.
            if (window.Swal && window.Swal.isVisible()) return;
            if (this.galeri.show) {
                if (e.key === "Escape") this.galeri.show = false;
                else if (e.key === "ArrowLeft") this.geserGaleri(-1);
                else if (e.key === "ArrowRight") this.geserGaleri(1);
                return;
            }
            if (e.key !== "Escape") return;
            if (this.menuCth) this.menuCth = false;
            else if (this.contoh.show) this.contoh.show = false;
            else if (this.val.show) this.val.show = false;
            else if (this.ulang.show) this.ulang.show = false;
            else if (this.bacaan) this.bacaan = null;
            else if (this.kep.show) this.tutupKeputusan();
            else if (this.lcMaks) this.lcMaks = false;
            else if (this.laci && this.panelKanan) this.tutupKanan();
        },
        toggleKanan() { this.panelKanan = !this.panelKanan; if (!this.panelKanan) this.lcMaks = false; this.simpanPilihanKanan(); },
        tutupKanan() { this.panelKanan = false; this.lcMaks = false; this.simpanPilihanKanan(); },
        pilihanKanan() { try { return localStorage.getItem("mk-lc-buka") !== "0"; } catch (e) { return true; } },
        simpanPilihanKanan() {
            if (this.laci) return;
            try { localStorage.setItem("mk-lc-buka", this.panelKanan ? "1" : "0"); } catch (e) { /* abaikan */ }
        },
        batasLebar(px) { return Math.min(Math.max(px, 340), Math.max(340, this.lebar - this.lebarKiri() - 440)); },
        lebarKiri() { return this.$refs.kiri ? this.$refs.kiri.offsetWidth : 320; },
        mulaiGeser() {
            if (this.lcMaks) return;
            this.geser = true;
            document.addEventListener("mousemove", this.saatGeser);
            document.addEventListener("mouseup", this.selesaiGeser);
            document.body.style.userSelect = "none";
            document.body.style.cursor = "col-resize";
        },
        saatGeser(e) { if (this.geser) this.lcLebar = this.batasLebar(this.$refs.akar.getBoundingClientRect().right - e.clientX); },
        selesaiGeser() {
            this.geser = false;
            document.removeEventListener("mousemove", this.saatGeser);
            document.removeEventListener("mouseup", this.selesaiGeser);
            document.body.style.userSelect = "";
            document.body.style.cursor = "";
            try { localStorage.setItem("mk-lc-lebar", String(this.lcLebar)); } catch (e) { /* abaikan */ }
        },
        resetLebar() { this.lcLebar = this.batasLebar(this.lebar >= 1440 ? 380 : 340); },
        muatLebar() {
            try {
                const n = parseInt(localStorage.getItem("mk-lc-lebar"), 10);
                this.lcLebar = this.batasLebar(isNaN(n) ? (this.lebar >= 1440 ? 380 : 340) : n);
            } catch (e) { /* abaikan */ }
        },

        /* ---------- tampilan ---------- */
        ikonAkt(k) { return { ANL: "ri-flask-line", LCKV: "ri-eye-line", PLT: "ri-heart-pulse-line" }[k] || "ri-test-tube-line"; },
        nadaItem(it) {
            if (it.Finalisasi) return { ok: "ok", warn: "warn", bad: "bad" }[it.Finalisasi.warna] || "ok";
            if (it.Verifikasi) return { ok: "ok", warn: "warn", bad: "bad" }[it.Verifikasi.warna] || "ok";
            if ((it.Analisa_Tidak_Layak || []).length) return "bad";
            if ((it.Analisa_Tanpa_Master || []).length) return "warn";
            return "ok";
        },
        ringkasNama(d) { return d.slice(0, 2).join(", ") + (d.length > 2 ? ` +${d.length - 2}` : ""); },
        jumlahJenisDari(rows) { return M.perAnalisa(rows).length; },
        labelLayak(l) { return { Y: "Layak", T: "Tidak layak", N: "Belum dinilai" }[l] || "-"; },
        nadaLayak(l) { return { Y: "ok", T: "bad", N: "warn" }[l] || "ok"; },
        layakFoto(a) {
            if (a.Dasar_Kelayakan === "TANPA_MASTER" || a.Dasar_Kelayakan === "KRITERIA_TIDAK_COCOK") return { label: "Belum dinilai", cls: "bg-warning-subtle text-warning" };
            return a.Flag_Layak === "T" ? { label: "Tidak layak", cls: "bg-danger-subtle text-danger" } : { label: "Layak", cls: "bg-success-subtle text-success" };
        },
        /** Nama keputusan dari master; sebelum master termuat (atau gagal), nama bakunya — bukan kode mentah. */
        namaKep(kode) {
            const k = this.master.find((x) => x.Kode_Keputusan === kode);
            if (k) return k.Nama_Keputusan;
            return { REKOMENDASI: "Direkomendasikan", REKOM_BERSYARAT: "Direkomendasikan Bersyarat",
                TIDAK_REKOM: "Tidak Direkomendasikan" }[kode] || kode;
        },
        warnaKep(kode) { return { REKOMENDASI: "ok", REKOM_BERSYARAT: "warn", TIDAK_REKOM: "bad" }[kode] || "ok"; },
        warnaBs(w) { return { ok: "success", warn: "warning", bad: "danger" }[w] || "primary"; },
        angka(v) {
            if (v === null || v === undefined || v === "") return "-";
            const n = Number(v);
            return isNaN(n) ? v : String(Math.round(n * 10000) / 10000).replace(".", ",");
        },
        stamp(w, jam = false) {
            if (!w) return "-";
            const [y, m, d] = String(w).slice(0, 10).split("-").map(Number);
            if (!y) return String(w);
            const bln = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
            const t = `${String(d).padStart(2, "0")} ${bln[m - 1]} ${y}`;
            return jam && String(w).length > 10 ? `${t} ${String(w).slice(11, 16)}` : t;
        },
    },
};
</script>

<style scoped>
/* Kerangka & komponen — sama dengan Validasi sandbox. */
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

/* ===================== KHUSUS MOCKUP ===================== */

/* Topbar: akun, session storage, stepper siklus */
.mk-top { row-gap: 8px; }
.mk-akun { display: inline-flex; align-items: center; gap: 5px; font-size: .7rem; color: #495057;
    background: #f3f6f9; border: 1px solid #e9ebec; border-radius: 4px; padding: 3px 9px; max-width: 100%; }
.mk-akun i { color: #405189; }
.mk-akun b { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.mk-akun em { font-style: normal; font-size: .62rem; color: #878a99; white-space: nowrap; }
.mk-ganti { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
.mk-ss { display: inline-flex; align-items: center; font-size: .66rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; color: #0b7fa6; background: #e0f4fb; border: 1px solid #b9e4f5; border-radius: 4px; padding: 3px 8px; }
.mk-step { flex: 1 1 100%; display: flex; align-items: center; gap: 4px; overflow-x: auto; scrollbar-width: none;
    padding-top: 2px; }
.mk-step::-webkit-scrollbar { display: none; }
.mk-step-p { color: #ced4da; flex-shrink: 0; }
.mk-step-i { display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; padding: 5px 11px; border-radius: 6px;
    font-size: .74rem; font-weight: 600; color: #6c757d; background: #f3f3f9; text-decoration: none; border: 1px solid transparent; }
.mk-step-i:hover { color: #405189; background: #eef1fb; }
.mk-step-i em { font-style: normal; font-size: .64rem; font-weight: 700; min-width: 18px; text-align: center;
    border-radius: 9px; padding: 0 6px; background: #fff; color: #495057; }
.mk-step-i.is-on { background: #405189; color: #fff; border-color: #405189; }
.mk-step-i.is-on em { background: rgba(255,255,255,.22); color: #fff; }
.mk-step-i.is-kunci { opacity: .55; cursor: not-allowed; }

/* Saringan tahap */
.mk-seg { display: flex; gap: 4px; padding: 8px 12px; border-bottom: 1px solid #f3f3f9; }
.mk-seg button { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 5px; border: 1px solid #e9ebec;
    background: #fff; color: #6c757d; border-radius: 5px; padding: 4px 6px; font-size: .68rem; font-weight: 600; cursor: pointer; }
.mk-seg button em { font-style: normal; font-size: .6rem; background: #f3f3f9; border-radius: 8px; padding: 0 5px; }
.mk-seg button.is-on { border-color: #405189; background: #eef1fb; color: #405189; }

.vs-li-b.no-cb { padding-left: 12px; }
.vs-li-ket.is-info { color: #0b7fa6; }

/* Antrean akun lain */
.mk-lain { display: flex; flex-direction: column; align-items: stretch; gap: 4px; width: 100%; max-width: 280px;
    margin-top: 10px; text-align: left; }
.mk-lain-h { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #adb5bd; }
.mk-lain-i { font-size: .68rem; color: #6c757d; background: #f8f9fc; border-radius: 5px; padding: 5px 8px; }
.mk-lain-i code { color: #405189; font-size: .68rem; background: none; padding: 0; }
.mk-lain-i b { display: block; color: #495057; font-weight: 600; }

/* Uji ulang yang menunggu hasil analis */
.mk-ulang { border: 1px dashed #7cc7e3; background: #f2fafd; border-radius: 7px; padding: 10px 12px; margin-bottom: 14px; }
.mk-ulang-h { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 7px; font-size: .74rem; color: #0b6a8b; margin-bottom: 6px; }
.mk-ulang-h em { font-style: normal; font-size: .66rem; color: #6c9fb3; }
.mk-ulang-i { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
    background: #fff; border: 1px solid #d7eef7; border-radius: 6px; padding: 8px 10px; margin-top: 6px; }
.mk-ulang-n { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.mk-ulang-n b { font-size: .76rem; color: #495057; }
.mk-ulang-n em { font-style: normal; font-size: .64rem; color: #878a99; }
.mk-ulang-n .mk-ulang-a { font-style: italic; }
.mk-ulang-b { display: flex; gap: 6px; flex-wrap: wrap; }

/* Keputusan yang sudah ada */
.mk-kep { display: flex; gap: 10px; align-items: flex-start; border-radius: 7px; padding: 10px 13px; margin-bottom: 14px;
    font-size: .74rem; border: 1px solid; }
.mk-kep > i { font-size: 1.1rem; margin-top: 1px; }
.mk-kep b { display: block; font-size: .8rem; }
.mk-kep em { font-style: normal; font-size: .66rem; opacity: .85; }
/* Catatan keputusan (IsiCatatan): terformat, bukan HTML mentah */
.mk-kep-c { margin-top: 6px; padding: 5px 9px; border-left: 2px solid rgba(0, 0, 0, .14); border-radius: 0 5px 5px 0;
    background: rgba(255, 255, 255, .65); color: #495057; font-size: .72rem; }
.mk-kl-s .mk-kep-c { background: #f8f9fc; }
/* Rekomendasi terkunci (tanpa hak revisi / sampel sudah difinalisasi) */
.mk-kunci { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 6px;
    background: #f3f4f7; color: #6c757d; font-size: .72rem; line-height: 1.35; }
.mk-kunci i { color: #878a99; font-size: .9rem; }
.vs.is-tunggal .mk-kunci { flex: 1; justify-content: center; text-align: center; }
.vs-al.mk-revisi { background: #eef1fb; border: 1px solid #d5dbf2; color: #3b4a82; margin-bottom: 12px; }
.mk-kep-st { display: flex; align-items: center; gap: 7px; padding: 10px 12px; margin-bottom: 10px; border-radius: 6px;
    background: #f8f9fc; border: 1px dashed #dfe3eb; color: #6c757d; font-size: .74rem; }
.mk-kep-st i { color: #f06548; }

/* Kolom catatan di modal keputusan: judul, penanda, dan bantuan mengikuti bobot keputusan */
.mk-cat-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: 4px; }
.mk-cat-h .vs-lb { margin-bottom: 0; cursor: text; }
.mk-cat-tag { flex-shrink: 0; padding: 2px 7px; border-radius: 4px; background: #f3f4f7; color: #878a99;
    font-size: .58rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; white-space: nowrap; }
.mk-cat-tag.is-kondisi { background: #fef3dc; color: #a2710b; }
.mk-cat-tag.is-justifikasi { background: #fde8e4; color: #c8412a; }
.mk-cat-b { display: flex; gap: 6px; align-items: flex-start; margin: 5px 0 8px; font-size: .7rem; line-height: 1.45; color: #6c757d; }
.mk-cat-b i { font-size: .85rem; line-height: 1.2; color: #adb5bd; }
.mk-cat-b.is-kondisi i { color: #f7b84b; }
.mk-cat-b.is-justifikasi i { color: #f06548; }
.mk-kep.is-ok   { background: #eefaf6; border-color: #b6e6da; color: #0c7a68; }
.mk-kep.is-warn { background: #fff8e6; border-color: #f5e0a3; color: #8a6d1f; }
.mk-kep.is-bad  { background: #fdeeea; border-color: #f7c4b8; color: #b03826; }

/* Keputusan akhir (hasil analisa) */
.mk-final { display: flex; gap: 13px; align-items: center; border-radius: 9px; padding: 13px 16px; margin-bottom: 14px; color: #fff; }
.mk-final-i { width: 42px; height: 42px; border-radius: 50%; background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0; }
.mk-final-t { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.mk-final-t small { font-size: .6rem; text-transform: uppercase; letter-spacing: .06em; opacity: .85; }
.mk-final-t b { font-size: 1.02rem; }
.mk-final-t em { font-style: normal; font-size: .68rem; opacity: .9; }
.mk-final .mk-kep-c { color: #fff; background: rgba(255, 255, 255, .14); border-left-color: rgba(255, 255, 255, .7); }
.mk-final.is-ok   { background: linear-gradient(135deg, #0ab39c, #0c8f7c); }
.mk-final.is-warn { background: linear-gradient(135deg, #f7b84b, #d99a22); }
.mk-final.is-bad  { background: linear-gradient(135deg, #f06548, #cf4a2e); }

.mk-saran.is-ok   { background: #eefaf6; border: 1px solid #b6e6da; color: #0c7a68; }
.mk-saran.is-warn { background: #fff8e6; border: 1px solid #f5e0a3; color: #8a6d1f; }
.mk-saran.is-bad  { background: #fdeeea; border: 1px solid #f7c4b8; color: #b03826; }

/* Klasifikasi pada finalisasi & hasil */
.mk-kl { background: #fff; border: 1px solid #e9ebec; border-radius: 7px; margin-bottom: 12px; overflow: hidden; }
.mk-kl-h { display: flex; align-items: center; flex-wrap: wrap; gap: 5px 8px; padding: 10px 13px; font-size: .78rem; }
.mk-kl-h > i { color: #405189; }
.mk-kl-h b { color: #495057; }
.mk-kl-h em { font-style: normal; font-size: .64rem; color: #adb5bd; }
.mk-kl-v { margin-left: auto; display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 700;
    border-radius: 10px; padding: 2px 10px; }
.mk-kl-v.is-ok   { background: #d9f3ee; color: #0c7a68; }
.mk-kl-v.is-warn { background: #fff3d9; color: #9a6f14; }
.mk-kl-v.is-bad  { background: #fde4df; color: #b03826; }
.mk-kl-v.is-wait { background: #fff4de; color: #9a6405; }
.mk-kl-s { padding: 0 13px 9px; font-size: .68rem; color: #6c757d; }
.mk-kl-s i { color: #adb5bd; margin-right: 3px; }
.mk-kl-s.is-tunggu { color: #9a6405; }
.mk-kl-cat { margin-left: 8px; padding: 0; border: 0; background: none; color: #405189; font-size: .68rem; font-weight: 600; }
.mk-kl-cat:hover, .mk-kl-cat:focus-visible { text-decoration: underline; }
.mk-kl-cat i { color: inherit; margin-right: 3px; }

/* Rekomendasi per klasifikasi (Finalisasi & Hasil) */
.mk-rk { background: #fff; border: 1px solid #e9ebec; border-radius: 7px; padding: 11px 13px 13px; margin-bottom: 14px; }
.mk-rk-h { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 6px 10px; margin-bottom: 10px; }
.mk-rk-j { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 600; color: #495057; }
.mk-rk-j i { color: #405189; }
.mk-rk-saran { display: inline-flex; align-items: center; gap: 5px; font-size: .7rem; color: #6c757d; cursor: default; }
.mk-rk-saran b { font-weight: 700; }
.mk-rk-saran.is-ok b, .mk-rk-saran.is-ok i { color: #0a8f7c; }
.mk-rk-saran.is-warn b, .mk-rk-saran.is-warn i { color: #a2710b; }
.mk-rk-saran.is-bad b, .mk-rk-saran.is-bad i { color: #d0472b; }
.mk-rk-g { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(170px, 100%), 1fr)); gap: 10px; }
.mk-rk-c { display: flex; flex-direction: column; gap: 4px; min-width: 0; padding: 10px 12px 11px; border-radius: 7px;
    border: 1px solid #e9ebec; border-top: 3px solid #ced4da; background: #fcfcfd; }
.mk-rk-c.is-ok { border-top-color: #0ab39c; }
.mk-rk-c.is-warn { border-top-color: #f7b84b; }
.mk-rk-c.is-bad { border-top-color: #f06548; }
.mk-rk-t { display: flex; align-items: center; gap: 6px; font-size: .74rem; color: #495057; }
.mk-rk-t i { color: #405189; }
.mk-rk-st { display: inline-flex; align-items: center; gap: 5px; font-size: .78rem; font-weight: 700; color: #9a6405; }
.mk-rk-c.is-ok .mk-rk-st { color: #0a8f7c; }
.mk-rk-c.is-warn .mk-rk-st { color: #a2710b; }
.mk-rk-c.is-bad .mk-rk-st { color: #c8412a; }
.mk-rk-o { font-size: .66rem; color: #878a99; }
.mk-rk-n { display: flex; flex-wrap: wrap; gap: 2px 8px; font-size: .66rem; color: #6c757d; }
.mk-rk-n .is-bad { color: #d0472b; }
.mk-rk-n .is-warn { color: #a2710b; }
.mk-rk-b { align-self: flex-start; display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; padding: 4px 9px;
    border: 1px solid #dfe3eb; border-radius: 5px; background: #fff; color: #405189; font-size: .68rem; font-weight: 600; }
.mk-rk-b:hover, .mk-rk-b:focus-visible { background: #eef1fb; border-color: #c5cdea; }
.mk-rk-no { margin-top: 5px; font-size: .66rem; font-style: italic; color: #adb5bd; }

/* Jendela baca catatan */
.vs-md.mk-baca { max-width: 660px; }
.mk-baca-m { display: flex; flex-wrap: wrap; gap: 4px 14px; margin-bottom: 10px; font-size: .7rem; color: #878a99; }
.mk-baca-m i { margin-right: 4px; }
.mk-baca-isi { font-size: .82rem; line-height: 1.65; color: #343a40; }

/* Modal: contoh data */
.mk-preset { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 7px; }
.mk-preset button { display: flex; flex-direction: column; gap: 2px; text-align: left; border: 1px solid #e9ebec;
    background: #fff; border-radius: 7px; padding: 8px 10px; cursor: pointer; }
.mk-preset button b { font-size: .74rem; color: #495057; }
.mk-preset button em { font-style: normal; font-size: .64rem; color: #878a99; line-height: 1.35; }
.mk-preset button.is-on { border-color: #0ab39c; background: #eefaf6; }
.mk-opsi { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 8px; }
.mk-opsi label { display: flex; flex-direction: column; gap: 3px; margin: 0; }
.mk-opsi label > span { font-size: .66rem; font-weight: 600; color: #6c757d; display: inline-flex; align-items: center; gap: 4px; }
.mk-opsi label > span i { color: #405189; }
.mk-opsi select { font-size: .72rem; }

/* Modal: keputusan */
.mk-cfm { background: #f8f9fc; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; max-height: 150px; overflow-y: auto; }
.mk-cfm > div { padding: 3px 0; }
.mk-cfm b { font-family: ui-monospace, monospace; font-size: .76rem; color: #405189; }
.mk-cfm span { display: block; font-size: .66rem; color: #878a99; }
.mk-keps { display: flex; flex-direction: column; gap: 7px; margin-bottom: 14px; }
.mk-kp { display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e9ebec; border-radius: 7px;
    padding: 10px 12px; cursor: pointer; margin: 0; }
.mk-kp:hover { background: #f8f9fc; }
.mk-kp input { margin-top: 3px; flex-shrink: 0; cursor: pointer; }
.mk-kp > span { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.mk-kp b { font-size: .78rem; color: #495057; display: inline-flex; align-items: center; gap: 5px; }
.mk-kp em { font-style: normal; font-size: .68rem; color: #878a99; line-height: 1.45; }
.mk-kp.is-ok b i { color: #0ab39c; }
.mk-kp.is-warn b i { color: #f7b84b; }
.mk-kp.is-bad b i { color: #f06548; }
.mk-kp.is-on.is-ok   { border-color: #0ab39c; background: rgba(10,179,156,.05); }
.mk-kp.is-on.is-warn { border-color: #f7b84b; background: rgba(247,184,75,.07); }
.mk-kp.is-on.is-bad  { border-color: #f06548; background: rgba(240,101,72,.05); }
.mk-kp-tag { align-self: flex-start; font-size: .56rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
    padding: 1px 6px; border-radius: 3px; background: #fde4df; color: #b03826; }
.mk-kp-tag.is-opt { background: #eff2f7; color: #878a99; }

.vs.is-tunggal .mk-step { padding-bottom: 2px; }
.vs.is-tunggal .mk-akun b { max-width: 140px; }
</style>
