<template>
    <div class="bp-tiket" :class="`bp-tiket--${warna}`">
        <!-- Sisi kiri: identitas sampel -->
        <div class="bp-tiket__main">
            <div class="bp-tiket__top">
                <div>
                    <span class="bp-tiket__lbl">Nomor Sampel</span>
                    <h3 class="bp-tiket__no">{{ sampel.No_Sampel }}</h3>
                </div>
                <StatusPill :sampel="sampel" />
            </div>

            <div class="bp-tiket__grid">
                <div class="bp-tiket__row">
                    <i class="ri-archive-2-line"></i>
                    <div>
                        <span>Nama Barang</span>
                        <strong>{{ isi(sampel.Nama_Barang) }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-barcode-line"></i>
                    <div>
                        <span>Kode Barang</span>
                        <strong>{{ isi(sampel.Kode_Barang) }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-file-list-3-line"></i>
                    <div>
                        <span>Nomor PO</span>
                        <strong>{{ isi(sampel.No_Po) }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-git-branch-line"></i>
                    <div>
                        <span>Split PO</span>
                        <strong>{{ isi(sampel.No_Split_Po) }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-stack-line"></i>
                    <div>
                        <span>Batch</span>
                        <strong>{{ isi(sampel.No_Batch) }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-settings-5-line"></i>
                    <div>
                        <span>Mesin</span>
                        <strong>{{ isi(sampel.Nama_Mesin) }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-user-3-line"></i>
                    <div>
                        <span>Diinput Oleh</span>
                        <strong>{{ isi(sampel.Nama_User || sampel.Id_User) }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-calendar-2-line"></i>
                    <div>
                        <span>Tanggal Input</span>
                        <strong>{{ tanggal }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-time-line"></i>
                    <div>
                        <span>Jam Input</span>
                        <strong>{{ jam }}</strong>
                    </div>
                </div>
                <div class="bp-tiket__row">
                    <i class="ri-progress-4-line"></i>
                    <div>
                        <span>Flag Selesai</span>
                        <strong>
                            {{ sampel.Flag_Selesai === "Y" ? "Selesai" : "Belum" }}
                        </strong>
                    </div>
                </div>
                <div v-if="sampel.Keterangan" class="bp-tiket__row bp-tiket__row--wide">
                    <i class="ri-chat-1-line"></i>
                    <div>
                        <span>Keterangan</span>
                        <strong>{{ sampel.Keterangan }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Perforasi tiket -->
        <div class="bp-tiket__cut">
            <span class="bp-tiket__notch bp-tiket__notch--top"></span>
            <span class="bp-tiket__dash"></span>
            <span class="bp-tiket__notch bp-tiket__notch--bot"></span>
        </div>

        <!-- Sisi kanan: QR code -->
        <div class="bp-tiket__qr">
            <qrcode-vue
                :value="sampel.No_Sampel || '-'"
                :size="128"
                level="H"
                foreground="#1b1f3b"
                background="transparent"
            />
            <div class="bp-tiket__qrno">{{ sampel.No_Sampel }}</div>
            <div class="bp-tiket__qrlbl">
                <i class="ri-qr-scan-2-line"></i> SCAN ME
            </div>
        </div>
    </div>
</template>

<script>
import QrcodeVue from "qrcode.vue";
import StatusPill from "./StatusPill.vue";

export default {
    name: "KartuSampel",
    components: { QrcodeVue, StatusPill },

    props: {
        sampel: { type: Object, required: true },
        warna: { type: String, default: "primary" },
    },

    computed: {
        tanggal() {
            const v = this.sampel?.Tanggal;
            if (!v) return "—";

            const d = new Date(String(v).replace(" ", "T"));
            if (isNaN(d.getTime())) return v;

            return d.toLocaleDateString("id-ID", {
                day: "2-digit",
                month: "long",
                year: "numeric",
            });
        },

        jam() {
            const v = this.sampel?.Jam;
            if (!v) return "—";

            // Kolom Jam bisa "08:15:00" atau datetime penuh.
            const cocok = String(v).match(/\d{2}:\d{2}(:\d{2})?/);

            return cocok ? cocok[0] : String(v);
        },
    },

    methods: {
        isi(v) {
            return v === null || v === undefined || v === "" ? "—" : v;
        },
    },
};
</script>
