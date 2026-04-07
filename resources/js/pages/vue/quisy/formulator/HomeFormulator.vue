<template>
    <div class="user-card">
        <div class="avatar-container">
            <DotLottieVue
                style="height: 80px; width: 80px"
                autoplay
                loop
                src="/animation/user.lottie"
            />
        </div>

        <div class="user-details">
            <div class="user-header">
                <div class="user-name">{{ pengguna }}</div>
                <div class="user-meta">
                    <span class="role-badge">{{ currentTime }}</span>
                    <div class="paper-badge">
                        <span class="indicator-dot"></span>

                        <i class="ri-printer-line paper-icon"></i>

                        <span class="paper-text">{{
                            lastHistory.Nama_Template
                        }}</span>
                    </div>
                </div>
            </div>
            <div class="last-activity">
                <i class="ri-history-line"></i>
                <span class="activity-text">Aktivitas terakhir:</span>
                <span class="activity-time">
                    {{ lastActivity ? lastActivity : "Belum Ada Aktivitas" }}
                </span>
            </div>

            <div class="user-stats">
                <div class="stat-item">
                    <div class="stat-value">
                        {{ po_selesai }}
                    </div>
                    <div class="stat-label">PO Selesai</div>
                </div>

                <div class="stat-item">
                    <div class="stat-value">
                        {{ po_belum_selesai }}
                    </div>
                    <div class="stat-label">PO Belum Selesai</div>
                </div>
            </div>
        </div>
    </div>
    <div class="multi-pane-container">
        <!-- Pane 1: Production Order -->
        <div class="pane" :class="{ active: selected.productionOrder }">
            <h3>
                Production Order
                <span v-if="productionOrders.length > 0"
                    >{{ productionOrders.length }} items</span
                >
            </h3>
            <div v-if="loading.po" class="loading-container">
                <span class="loading-text"
                    >Loading<span class="dot1">.</span
                    ><span class="dot2">.</span
                    ><span class="dot3">.</span></span
                >
            </div>
            <div
                v-if="poMessage"
                :class="
                    poMessage.includes('Selesai')
                        ? 'po-message-success'
                        : 'po-message-error'
                "
            >
                <p>{{ poMessage }}</p>
            </div>

            <ul v-else>
                <li
                    v-for="item in productionOrders"
                    :key="item.No_Faktur || item.No_Faktur"
                    :class="{
                        selected: selected.productionOrder === item.No_Faktur,
                        disabled: isPoDisabled(item.No_Faktur),
                    }"
                    @click="selectProductionOrder(item.No_Faktur)"
                >
                    <div class="po-header">
                        <span class="po-code">{{ item.No_Faktur }}</span>
                        <span
                            v-if="isPoComplete(item.No_Faktur)"
                            class="status-badge complete"
                            >✓</span
                        >
                        <!-- <button
                            v-if="item.has_samples"
                            @click.stop="confirmClosePO(item.No_Faktur)"
                            class="btn btn-close-po"
                        >
                            <i class="ri-close-fill"></i> Close PO
                        </button> -->
                    </div>
                    <div class="po-details">
                        <span class="po-name">{{
                            item.Nama || "Unnamed Order"
                        }}</span>
                        <div class="po-meta">
                            <span class="po-qty"
                                >{{ formatNumber(item.Jumlah) }}
                                {{ item.Satuan }}</span
                            >
                            <span class="po-date">{{
                                formatDate(item.Tanggal)
                            }}</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Pane 2: Split PO -->
        <div
            class="pane"
            :class="{
                active: selected.splitPO,
                disabled: !selected.productionOrder,
            }"
        >
            <h3>
                Split PO
                <span v-if="selected.productionOrder"
                    >({{ selected.productionOrder }})</span
                >
            </h3>
            <div v-if="loading.splitPO" class="loading-container">
                <span class="loading-text"
                    >Loading<span class="dot1">.</span
                    ><span class="dot2">.</span
                    ><span class="dot3">.</span></span
                >
            </div>
            <div v-if="splitPOMessage" class="split-po-message">
                <p>{{ splitPOMessage }}</p>
            </div>
            <div
                v-if="poMessage"
                :class="
                    poMessage.includes('Selesai')
                        ? 'po-message-success'
                        : 'po-message-error'
                "
            >
                <p>{{ poMessage }}</p>
            </div>

            <ul v-else>
                <li
                    v-for="item in splitPOs"
                    :key="item.No_Transaksi"
                    :class="{
                        selected:
                            selected.splitPO?.No_Transaksi ===
                            item.No_Transaksi,
                        complete: item.is_complete,
                    }"
                    @click="selectSplitPO(item)"
                >
                    <div class="split-po-header">
                        <span class="split-po-code">{{
                            item.No_Transaksi
                        }}</span>
                        <span
                            v-if="item.is_complete"
                            class="status-badge complete"
                            >✓</span
                        >
                    </div>
                    <div class="po-meta">
                        {{ calculateSplitPoQty(item) }} pcs
                        <span class="po-date">{{
                            formatDate(item.Tanggal)
                        }}</span>
                    </div>
                </li>
            </ul>
        </div>
        <!-- Pane 3: Batch -->
        <div
            class="pane"
            :class="{ active: selected.batch, disabled: !selected.splitPO }"
        >
            <h3>
                Batch
                <span v-if="selected.splitPO"
                    >({{ selected.splitPO.No_Transaksi }})</span
                >
            </h3>
            <div v-if="loading.batch" class="loading-container">
                <span class="loading-text"
                    >Loading<span class="dot1">.</span
                    ><span class="dot2">.</span
                    ><span class="dot3">.</span></span
                >
            </div>
            <div
                v-if="poMessage"
                :class="
                    poMessage.includes('Selesai')
                        ? 'po-message-success'
                        : 'po-message-error'
                "
            >
                <p>{{ poMessage }}</p>
            </div>
            <ul v-else>
                <li
                    v-for="(batch, index) in batches"
                    :key="index"
                    :class="[
                        { selected: selected.batch === batch },
                        { disabled: isBatchSaved(batch) },
                        { complete: isBatchSaved(batch) },
                    ]"
                    @click="handleBatchClick(batch)"
                >
                    Batch {{ index + 1 }}
                    <span
                        v-if="isBatchSaved(batch)"
                        class="status-badge complete"
                        >✓</span
                    >
                </li>
            </ul>
        </div>

        <!-- Pane 4: Form input -->
        <div
            class="pane form-pane"
            :class="{
                active: selected.batch,
                disabled: !selected.batch,
            }"
        >
            <h3>
                Input Data
                <span v-if="selected.batch"
                    >Batch ({{ selected.batch.Jumlah_Batch }})</span
                >
            </h3>
            <div
                v-if="poMessage"
                :class="
                    poMessage.includes('Selesai')
                        ? 'po-message-success'
                        : 'po-message-error'
                "
            >
                <p>{{ poMessage }}</p>
            </div>

            <form v-if="selected.batch" @submit.prevent="openPinModal">
                <!-- Machine Type Section -->
                <div class="form-group scrollable-machine-list">
                    <label>Nama Machine</label>

                    <div v-if="loading.machine" class="loading-container">
                        <span class="loading-text"
                            >Loading<span class="dot1">.</span
                            ><span class="dot2">.</span
                            ><span class="dot3">.</span></span
                        >
                    </div>

                    <div v-if="machineMessage" class="split-po-message">
                        <p>{{ machineMessage }}</p>
                    </div>

                    <ul
                        v-if="!loading.machine && !machineMessage"
                        class="machine-list"
                    >
                        <li
                            v-for="mesin in mesinList"
                            :key="mesin.Id_Master_Mesin"
                            :class="[
                                'machine-item',
                                form.machineType === mesin.Id_Master_Mesin
                                    ? 'selected'
                                    : '',
                                mesin.is_already_input ? 'already-input' : '',
                            ]"
                            @click="selectMachine(mesin)"
                        >
                            <div class="machine-header">
                                <i class="ri-settings-3-fill machine-icon"></i>
                                <div class="machine-info">
                                    <span class="machine-name">{{
                                        mesin.Nama_Mesin
                                    }}</span>
                                    <span class="machine-serial">{{
                                        mesin.Seri_Mesin
                                    }}</span>

                                    <!-- Hanya tampilkan total_input_count jika sudah input -->
                                    <span
                                        v-if="mesin.is_already_input"
                                        class="machine-count"
                                    >
                                        Total Input:
                                        {{ mesin.total_input_count }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <label class="fw-semibold d-block mb-2">Sifat Kegiatan</label>
                <div class="form-check form-switch ms-4 custom-switch-modern">
                    <input
                        class="form-check-input custom-switch-input"
                        type="checkbox"
                        id="switchSifatKegiatan"
                        v-model="isBerkala"
                        @change="handleSwitchChange"
                    />
                    <label
                        class="form-check-label custom-switch-label"
                        for="switchSifatKegiatan"
                    >
                        {{ isBerkala ? "Berkala" : "Rutin" }}
                    </label>
                </div>

                <div v-if="selectedSifatKegiatan === 'Berkala'">
                    <div class="form-group scrollable-machine-list">
                        <label>Jenis Analisa</label>

                        <div
                            v-if="loading.jenisAnalisa"
                            class="loading-container"
                        >
                            <span class="loading-text">
                                Loading<span class="dot1">.</span
                                ><span class="dot2">.</span
                                ><span class="dot3">.</span>
                            </span>
                        </div>

                        <div v-if="machineMessage" class="split-po-message">
                            <p>{{ machineMessage }}</p>
                        </div>

                        <div
                            v-if="!loading.jenisAnalisa && !machineMessage"
                            class="scroll-wrapper"
                        >
                            <div class="d-flex flex-nowrap gap-1">
                                <div
                                    v-for="(
                                        analisa, index
                                    ) in jenisAnalisaByBerkala"
                                    :key="index"
                                >
                                    <button
                                        type="button"
                                        class="badge-item"
                                        :style="{
                                            '--bg-color':
                                                colorPalette[
                                                    index % colorPalette.length
                                                ],
                                            '--hover-color': lightenColor(
                                                colorPalette[
                                                    index % colorPalette.length
                                                ],
                                                15
                                            ),
                                            '--selected-color': darkenColor(
                                                colorPalette[
                                                    index % colorPalette.length
                                                ],
                                                15
                                            ),
                                        }"
                                        :class="{
                                            selected:
                                                form.jenisAnalisaCheckBerkala ===
                                                analisa.id,
                                        }"
                                        @click="
                                            selectJenisAnalisaBerkala(analisa)
                                        "
                                    >
                                        <i class="fas fa-flask me-1"></i>
                                        <span>{{ analisa.Jenis_Analisa }}</span>
                                        <!-- <span>HOMOGENITAS</span> -->
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Opsi Berkala -->
                    <div class="form-group opsi-berkala-group">
                        <label class="opsi-berkala-label"> Opsi Berkala </label>
                        <div class="opsi-berkala-options">
                            <!-- <label class="opsi-berkala-radio">
                                <input
                                    type="radio"
                                    name="opsi_berkala"
                                    value="ya"
                                    v-model="selectedOpsiBerkala"
                                />
                                <span>Ya, sekalian dengan Rutin</span>
                            </label> -->
                            <label class="opsi-berkala-radio">
                                <input
                                    type="radio"
                                    name="opsi_berkala"
                                    value="tidak"
                                    v-model="selectedOpsiBerkala"
                                />
                                <span>Tidak, hanya uji Berkala</span>
                            </label>
                        </div>
                    </div>
                    <div
                        v-if="selectedOpsiBerkala === 'ya' && isFlagKg !== 'Y'"
                    >
                        <div class="form-group">
                            <label for="notes">Jumlah Pcs</label>
                            <input
                                id="Jumlah_Pcs"
                                v-model="form.Jumlah_Pcs"
                                type="number"
                                required
                                placeholder="1"
                                @keydown="checkJumlahPcs"
                            />
                        </div>
                    </div>
                    <div v-if="selectedOpsiBerkala === 'tidak'">
                        <div v-if="isFlagKg !== 'Y'">
                            <label for="notes">Jumlah Pcs</label>
                            <input
                                id="Jumlah_Pcs"
                                v-model="form.Jumlah_Pcs"
                                type="number"
                                required
                                placeholder="1"
                                @keydown="checkJumlahPcs"
                            />
                        </div>
                        <div v-else>
                            <div class="form-group">
                                <label for="berat_sampel"
                                    >Jumlah Print QrCode</label
                                >
                                <input
                                    id="berat_sampel"
                                    v-model.number="form.Jumlah_Print_QRCode"
                                    type="number"
                                    min="1"
                                    max="10"
                                    required
                                    placeholder="Masukkan Maksimal 10 Kali Print"
                                    @input="validateNumberInput"
                                    class="number-input"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div v-if="isFlagKg === 'Y'">
                        <div class="timbangan-integrated-container">
                            <div class="timbangan-header">
                                <div class="d-flex">
                                    <label for="berat_sampel"
                                        >Berat Sampel (kg)</label
                                    >
                                    <div class="connection-status">
                                        <div
                                            class="status-indicator"
                                            :class="{ active: isConnected }"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <div class="input-with-timbangan">
                                <div
                                    class="berat-display-integrated"
                                    :class="{
                                        pulse: isChanging,
                                        'manual-mode': manualInput,
                                        connected: isConnected,
                                        disconnected: !isConnected,
                                    }"
                                >
                                    {{ formatBerat(form.Berat_Sampel) }}
                                    <span class="unit">kg</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else>
                        <div v-if="selectedSifatKegiatan === 'Rutin'">
                            <label for="notes">Jumlah Pcs</label>
                            <input
                                id="Jumlah_Pcs"
                                v-model="form.Jumlah_Pcs"
                                type="number"
                                required
                                placeholder="1"
                                @keydown="checkJumlahPcs"
                            />
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Catatan</label>
                    <input
                        id="notes"
                        v-model="form.notes"
                        type="text"
                        required
                        placeholder="Enter notes..."
                    />
                </div>

                <button
                    type="submit"
                    class="save-btn"
                    :disabled="
                        isFlagKg === 'Y'
                            ? loading.saveToDatabase
                            : loading.saveToDatabase
                    "
                >
                    <span class="icon" v-if="!loading.saveToDatabase">✓</span>
                    <span v-else class="spinner"></span>
                    {{ loading.saveToDatabase ? "Loading..." : "Kirimkan" }}
                </button>
            </form>
        </div>
    </div>
    <div v-if="showPinModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-title-container">
                <h3 class="modal-title">Masukkan PIN Anda</h3>
                <button
                    class="btn-change-user"
                    @click="showChangeUserModal = true"
                    title="Ganti Pengguna"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        width="16"
                        height="16"
                    >
                        <path
                            d="M10 9V5l-7 7 7 7v-4.1c5 0 8.5 1.6 11 5.1-1-5-4-10-11-11z"
                        />
                    </svg>
                    Ganti User
                </button>
            </div>
            <p class="current-user">
                Login sebagai: <strong>{{ penggunaAktif }}</strong>
            </p>

            <div class="pin-container">
                <input
                    v-for="(digit, index) in pin"
                    :key="index"
                    ref="pinInput"
                    v-model="pin[index]"
                    maxlength="1"
                    type="password"
                    class="pin-input"
                    @input="focusNext(index)"
                    @keydown.backspace="focusPrev(index, $event)"
                    inputmode="numeric"
                    pattern="[0-9]*"
                />
            </div>

            <div class="modal-actions">
                <button class="btn btn-submit" @click="verifyPin">
                    Submit
                </button>
                <button class="btn btn-cancel" @click="closePinModal">
                    Cancel
                </button>
            </div>

            <div class="security-tip">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path
                        d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11V11.99z"
                    />
                </svg>
                Pastikan tidak ada yang melihat PIN Anda
            </div>
            <p class="change-user-tip">
                Ingin masuk sebagai pengguna lain? Silakan tekan tombol
                <strong>Ganti User</strong> di atas untuk login ulang.
            </p>
        </div>
    </div>

    <div v-if="showChangeUserModal" class="modal-overlay">
        <div class="modal-box">
            <h3 class="modal-title">Ganti Pengguna</h3>

            <div class="form-group">
                <label>Username </label>
                <input
                    type="text"
                    v-model="newUsername"
                    class="form-control"
                    placeholder="Masukkan username"
                    autocomplete="username"
                />
            </div>

            <div class="form-group">
                <label>Password</label>
                <input
                    type="password"
                    v-model="newPassword"
                    class="form-control"
                    placeholder="Masukkan password"
                    autocomplete="new-password"
                />
            </div>

            <div class="modal-actions">
                <button
                    class="btn btn-submit"
                    :disabled="loading.gantiUser"
                    @click="gantiUserUntukSubmit"
                >
                    Ganti
                </button>
                <button
                    :disabled="loading.gantiUser"
                    class="btn btn-cancel"
                    @click="showChangeUserModal = false"
                >
                    Batal
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { DotLottieVue } from "@lottiefiles/dotlottie-vue";
import axios from "axios";
import CryptoJS from "crypto-js";

const STORAGE_KEY = "SSID_serial_config";
const SECRET_KEY =
    import.meta.env.VITE_SECRET_KEY || "default-secret-key-12345";

export default {
    name: "MultiPaneProduction",
    props: {
        lastActivity: {
            type: String,
            default: null,
        },
        pengguna: {
            type: String,
            default: null,
        },
        lastHistory: {
            type: Object,
            default: null,
        },
        po_selesai: {
            type: Number,
            default: 0,
        },
        po_belum_selesai: {
            type: Number,
            default: 0,
        },
    },
    components: {
        DotLottieVue,
    },
    data() {
        return {
            manualInput: false,
            colorPalette: [
                "#3B82F6",
                "#10B981", // hijau
                "#F59E0B", // kuning
                "#EF4444", // merah
                "#8B5CF6", // ungu
                "#EC4899", // pink
                "#0EA5E9", // biru muda
                "#F97316", // oranye
                "#14B8A6", // teal
                "#6366F1", // indigo
            ],
            newUsername: "",
            newPassword: "",
            count: {
                totalPOBelumSelesai: 0,
                totalSPBelumSelesai: 0,
            },
            showChangeUserModal: false,
            penggunaAktif: null,
            currentTime: this.getCurrentTime(),
            splitPOMessage: "",
            machineMessage: "",
            poMessage: "",
            stabil: false,
            isFlagKg: "Y",
            showPinModal: false,
            pin: Array(6).fill(""),
            correctPin: "123456",
            productionOrders: [],
            splitPOs: [],
            batches: [],
            mesinList: [],
            jenisAnalisaByBerkala: [],
            poList: [],
            selectedSifatKegiatan: "Rutin",
            isBerkala: false,
            selectedOpsiBerkala: "tidak",
            selected: {
                productionOrder: null,
                splitPO: null,
                batch: null,
            },
            form: {
                machineType: "",
                jenisAnalisaCheckBerkala: "",
                Flag_Multi_Qrcode: null,
                Jumlah_Print_QRCode: 1,
                notes: "",
                Berat_Sampel: "",
                Jumlah_Pcs: 0,
            },
            socket: null,
            isConnected: false,
            isChanging: false,
            lastUpdated: "-",
            changeTimeout: null,
            savedData: JSON.parse(localStorage.getItem("productionData")) || {},
            loading: {
                po: false,
                splitPO: false,
                batch: false,
                machine: false,
                jenisAnalisa: false,
                saveToDatabase: false,
                gantiUser: false,
            },
        };
    },
    computed: {
        baseClientUrl() {
            return this.$page.props.url_client;
        },

        baseWsUrl() {
            return this.$page.props.url_timbangan || null;
        },
    },

    mounted: async function () {
        try {
            await this.fetchPoList();

            this.timer = setInterval(this.updateClock, 1000);

            if (this.isFlagKg === "Y") {
                await this.connectSocket();
            }

            if (this.selected.productionOrder) {
                await this.loadSplitPO(this.selected.productionOrder);
            }

            if (this.selected.splitPO) {
                this.batches = await this.getBatchesForSplitPO(
                    this.selected.splitPO
                );
            }

            await this.setPenggunaAktif();
            window.addEventListener("storage", this.setPenggunaAktif);
        } catch (error) {
            console.error("Error in mounted:", error);
        }
    },

    methods: {
        decryptData(encryptedData) {
            try {
                const bytes = CryptoJS.AES.decrypt(encryptedData, SECRET_KEY);
                const decryptedString = bytes.toString(CryptoJS.enc.Utf8);
                return JSON.parse(decryptedString);
            } catch (error) {
                console.error("Gagal mendekripsi data:", error);
                return null;
            }
        },

        getSerialConfig() {
            const encryptedData = localStorage.getItem(STORAGE_KEY);
            if (encryptedData) {
                const configArray = this.decryptData(encryptedData);

                console.log(configArray);
                if (configArray && configArray.length > 0) {
                    console.log(
                        "Menggunakan konfigurasi dari localStorage:",
                        configArray[0]
                    );
                    return configArray[0];
                }
            }

            console.log(
                "Tidak ada konfigurasi di localStorage, menggunakan default."
            );
            return {
                COM_TARGET: "COM5",
                BAUD_RATE: 9600,
                BIT: 7,
                parity: "even",
                stopBits: 1,
            };
        },
        connectSocket() {
            if (!this.baseWsUrl) {
                console.error("URL Timbangan tidak tersedia dari server.");
                return;
            }

            console.log("Connecting to Timbangan:", this.baseWsUrl);

            this.socket = new WebSocket(this.baseWsUrl);

            this.socket.onopen = () => {
                console.log("✅ WebSocket connected");
                this.isConnected = true;
                this.modalCloseIfOpen();

                const config = this.getSerialConfig();

                this.socket.send(
                    JSON.stringify({
                        type: "config",
                        payload: config,
                    })
                );

                console.log("Mengirim konfigurasi ke agent:", config);
            };

            this.socket.onmessage = (event) => {
                try {
                    const parsed = JSON.parse(event.data);
                    const berat = parseFloat(parsed.berat);
                    const status = parsed.status;

                    if (!isNaN(berat)) {
                        this.handleWeightChange(berat, status);
                    }
                } catch (e) {
                    console.warn("Data tidak valid:", event.data);
                }
            };

            this.socket.onerror = (err) => {
                console.error("❌ WebSocket error:", err);
                this.isConnected = false;
                this.handleConnectionLost();
            };

            this.socket.onclose = () => {
                console.warn("⚠ WebSocket closed. Reconnecting 3s...");
                this.isConnected = false;
                this.handleConnectionLost();
                setTimeout(() => this.connectSocket(), 3000);
            };
        },

        formatBerat(value) {
            if (!value && value !== 0) return "0";

            const num = parseFloat(value);
            if (isNaN(num)) return "0";

            return num % 1 === 0 ? num.toString() : num.toString();
        },
        handleWeightChange(newWeight, status) {
            const now = new Date();
            this.lastUpdated = now.toLocaleTimeString();
            this.isChanging = true;

            if (this.changeTimeout) {
                clearTimeout(this.changeTimeout);
            }

            this.form.Berat_Sampel = newWeight;
            this.berat = newWeight;
            this.stabil = status === "ST"; // Tambahkan properti `stabil` di data()

            this.changeTimeout = setTimeout(() => {
                this.isChanging = false;
            }, 500);
        },

        async confirmClosePO(noFaktur) {
            const result = await Swal.fire({
                title: "Anda Yakin?",
                html: `
            <div style="text-align: center; padding: 0 1em;">
                Anda akan menutup Production Order: <br>
                <strong style="font-size: 1.3em; color: #d33;">${noFaktur}</strong>
                <hr style="margin: 1em 0;">
                <div style="text-align: left; font-size: 0.9em; color: #555;">
                    <li>PO ini akan dianggap <strong>selesai</strong>.</li>
                    <li>Tindakan ini <strong>tidak dapat dibatalkan</strong>.</li>
                    <li>Pastikan shift lain sudah tidak memerlukannya.</li>
                </div>
            </div>
        `,
                icon: "warning",

                // --- Peningkatan Visual ---
                showCancelButton: true,
                confirmButtonColor: "#d33", // Merah untuk aksi destruktif
                cancelButtonColor: "#3085d6", // Biru untuk aksi aman
                confirmButtonText: '<i class="fa fa-trash"></i> Ya, Tutup PO!',
                cancelButtonText: "Batal",

                // --- Custom Class untuk Border Merah ---
                customClass: {
                    popup: "popup-border-danger",
                },

                // --- UX Tambahan ---
                reverseButtons: true, // Tombol konfirmasi di kanan
                focusCancel: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
            });

            if (result.isConfirmed) {
                // Tampilkan loading yang lebih informatif
                Swal.fire({
                    title: "Memproses...",
                    html: `Sedang menutup PO <strong>${noFaktur}</strong>, harap tunggu.`,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                });

                try {
                    const payload = {
                        No_Po: noFaktur,
                    };

                    await axios.post("/api/v1/close-po/by-qa", payload);

                    Swal.close(); // Tutup loading setelah API selesai

                    // Tampilan sukses yang akan hilang otomatis
                    Swal.fire({
                        icon: "success",
                        title: "Berhasil!",
                        text: `Production Order ${noFaktur} telah berhasil ditutup.`,
                        timer: 2000, // Hilang setelah 2 detik
                        showConfirmButton: false,
                    });

                    // Update list PO di UI
                    this.productionOrders = this.productionOrders.filter(
                        (po) => po.No_Faktur !== noFaktur
                    );
                    window.location.reload();
                } catch (err) {
                    Swal.close();
                    Swal.fire({
                        icon: "error",
                        title: "Gagal!",
                        html: `Terjadi kesalahan saat menutup PO <strong>${noFaktur}</strong>. Silakan coba lagi.`,
                    });
                }
            }
        },
        handleConnectionLost() {
            const manualOverride = sessionStorage.getItem("manualTimbangan");
            if (manualOverride === "true") return;

            if (!this.modalOpen) {
                this.modalOpen = true;

                Swal.fire({
                    title: "Timbangan Tidak Terkoneksi!",
                    html: this.generateStatusHTML(this.isConnected),
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Gunakan Manual",
                    cancelButtonText: "Tutup",
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => {
                        this.modalInstance = Swal.getHtmlContainer();
                        this.startStatusWatcher();
                    },
                    willClose: () => {
                        this.modalOpen = false;
                        clearInterval(this.statusWatcher);
                    },
                }).then((result) => {
                    if (result.isConfirmed) {
                        sessionStorage.setItem("manualTimbangan", "true");
                        Swal.fire({
                            title: "Mode Manual Diaktifkan",
                            icon: "info",
                            text: "Silakan isi berat secara manual.",
                            timer: 1500,
                            showConfirmButton: false,
                        });
                    }
                });
            }
        },

        modalCloseIfOpen() {
            if (this.modalOpen) {
                Swal.close();
                this.modalOpen = false;
            }
        },

        generateStatusHTML(isConnected) {
            return `
        <div style="text-align: left;">
          <p>Status Koneksi Timbangan:</p>
          <div style="display: flex; align-items: center;">
            <div style="width: 12px; height: 12px; border-radius: 50%; margin-right: 8px;
                background-color: ${
                    isConnected ? "#28a745" : "#dc3545"
                };"></div>
            <span style="font-weight: bold;">${
                isConnected ? "Terhubung" : "Tidak Terhubung"
            }</span>
          </div>
          <p style="margin-top: 12px;">Pastikan kabel sudah terpasang ke port serial.</p>
        </div>
      `;
        },

        startStatusWatcher() {
            this.statusWatcher = setInterval(() => {
                if (!this.modalInstance) return;
                const container = this.modalInstance.querySelector(
                    ".swal2-html-container"
                );
                if (container) {
                    container.innerHTML = this.generateStatusHTML(
                        this.isConnected
                    );
                }

                // Jika koneksi sudah aktif, tutup modal
                if (this.isConnected) {
                    this.modalCloseIfOpen();
                }
            }, 1000);
        },

        resetManualMode() {
            localStorage.removeItem("manualTimbangan");
        },

        enableManualInput() {
            this.manualInput = true;
            this.$nextTick(() => {
                this.$refs.weightInput.focus();
                this.form.Berat_Sampel = this.form.Berat_Sampel || 0;
            });
        },

        disableManualInput() {
            const num = parseFloat(this.form.Berat_Sampel);
            this.form.Berat_Sampel = isNaN(num) ? 0 : num;
            this.manualInput = false;
        },

        async gantiUserUntukSubmit() {
            this.loading.gantiUser = true;
            localStorage.removeItem("SSID_EmI_Lab_EVO_RS");

            if (!this.newUsername || !this.newPassword) {
                Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text: "Username dan Password tidak boleh kosong!",
                    confirmButtonColor: "#d33",
                    confirmButtonText: "OK",
                });
                return;
            }

            try {
                const payload = {
                    UserId: this.newUsername,
                    Password: this.newPassword,
                };
                const response = await axios.post(
                    "/proses_change/user",
                    payload,
                    {
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                        },
                    }
                );

                if (response.status !== 200 || response.data.success !== true) {
                    if (response.data.errors) {
                        this.errors = response.data.errors;
                    }
                    throw new Error(
                        response.data.message || "Gagal menyimpan data"
                    );
                }

                const SECRET_KEY = import.meta.env.VITE_SECRET_KEY;
                const encrypted = CryptoJS.AES.encrypt(
                    JSON.stringify(response.data.result),
                    SECRET_KEY
                ).toString();

                localStorage.setItem("SSID_EmI_Lab_EVO_RS", encrypted);
                this.setPenggunaAktif();
                this.showChangeUserModal = false;

                Swal.fire({
                    icon: "success",
                    title: "Berhasil",
                    text: response.data.message,
                    timer: 1500,
                    showConfirmButton: false,
                });
            } catch (error) {
                Swal.fire(
                    "Error",
                    error.response?.data?.message || error.message,
                    "error"
                );
            } finally {
                this.loading.gantiUser = false;
            }
        },
        getCurrentTime() {
            const currentDate = new Date();

            const day = String(currentDate.getDate()).padStart(2, "0");
            const month = String(currentDate.getMonth() + 1).padStart(2, "0");
            const year = currentDate.getFullYear();

            const hours = String(currentDate.getHours()).padStart(2, "0");
            const minutes = String(currentDate.getMinutes()).padStart(2, "0");
            const seconds = String(currentDate.getSeconds()).padStart(2, "0");

            return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
        },
        updateClock() {
            this.currentTime = this.getCurrentTime();
        },
        validateNumberInput(event) {
            let value = parseInt(event.target.value);

            if (value > 10) {
                this.form.Jumlah_Print_QRCode = 10;
                Swal.fire({
                    icon: "warning",
                    title: "Peringatan",
                    text: "Nilai maksimal adalah 10",
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                });
            }
        },
        handleSwitchChange() {
            this.selectedSifatKegiatan = this.isBerkala ? "Berkala" : "Rutin";

            if (this.selectedSifatKegiatan === "Berkala") {
                this.fetchJenisAnalisaByBerkala();
            } else {
                this.selectedOpsiBerkala = "";
            }
        },
        async fetchMachines() {
            const computerKey = localStorage.getItem("SSID_EVO");
            const selectedSplitPO = this.selected.splitPO;
            const selectedBatch = this.selected.batch;
            if (!selectedSplitPO || !selectedBatch) {
                console.warn("Split PO atau Batch belum dipilih.");
                return;
            }

            const noTransaksi = selectedSplitPO.No_Transaksi;
            const batchNumber = selectedBatch.No_Batch;

            this.loading.machine = true;

            try {
                const response = await axios.get(
                    `/api/v1/formulator/registrasi-material/machine/${computerKey}/${noTransaksi}/${batchNumber}`
                );

                this.mesinList = [];
                this.machineMessage = "";

                if (
                    response.status === 200 &&
                    response.data &&
                    response.data.result
                ) {
                    const serverData = response.data.result;

                    if (serverData && serverData.length > 0) {
                        this.mesinList = serverData;
                    } else {
                        this.machineMessage =
                            "Data Mesin Untuk Komputer Anda Tidak Ditemukan";
                    }
                } else {
                    throw { status: response.status };
                }
            } catch (error) {
                this.mesinList = [];

                if (error.response) {
                    if (error.response.status === 404) {
                        this.machineMessage =
                            "Data Mesin Untuk Komputer Anda Tidak Ditemukan";
                    } else if (error.response.status === 400) {
                        this.machineMessage = "Komputer Anda belum didaftarkan";
                    } else {
                        this.machineMessage =
                            "Terjadi kesalahan saat memuat data mesin.";
                    }
                } else {
                    this.machineMessage =
                        "Terjadi kesalahan saat memuat data mesin.";
                }
            } finally {
                this.loading.machine = false;
            }
        },
        async fetchJenisAnalisaByBerkala() {
            this.loading.jenisAnalisa = true;

            try {
                const response = await axios.get(
                    `/api/v1/jenis-analisa/by-berkala`
                );

                if (
                    response.status === 200 &&
                    response.data &&
                    response.data.result
                ) {
                    this.jenisAnalisaByBerkala = response.data.result;
                } else {
                    throw { status: response.status };
                }
            } catch (error) {
                this.jenisAnalisaByBerkala = [];

                if (error.response) {
                    if (error.response.status === 404) {
                        this.machineMessage =
                            "Data Mesin Untuk Komputer Anda Tidak Ditemukan";
                    } else if (error.response.status === 400) {
                        this.machineMessage = "Komputer Anda belum didaftarkan";
                    } else {
                        this.machineMessage =
                            "Terjadi kesalahan saat memuat data mesin.";
                    }
                } else {
                    this.machineMessage =
                        "Terjadi kesalahan saat memuat data mesin.";
                }
            } finally {
                this.loading.jenisAnalisa = false;
            }
        },

        async loadSplitPO(poId) {
            this.splitPOMessage = "";
            this.loading.splitPO = true;

            const computer_keys = localStorage.getItem("SSID_EVO");
            try {
                const response = await axios.get(
                    `/api/v1/formulator/registrasi-material/split-po/${poId}/${computer_keys}`
                );

                if (response.data.success && response.data.result) {
                    this.splitPOs = response.data.result;
                } else {
                    this.splitPOs = [];
                    this.splitPOMessage = `Data dengan Nomor PO ${poId} belum memiliki Split PO.`;
                }
            } catch (error) {
                // Menangani error ketika statusnya 404 atau lainnya
                if (error.response && error.response.status === 404) {
                    this.splitPOs = [];
                    this.splitPOMessage = `Data dengan Nomor PO ${poId} belum memiliki Split PO.`;
                } else {
                    this.splitPOs = [];
                    this.splitPOMessage =
                        "Terjadi kesalahan saat memuat data Split PO.";
                }
            } finally {
                this.loading.splitPO = false;
            }
        },

        async loadBatches(splitPO) {
            this.loading.batch = true;
            const id = splitPO.No_Transaksi || splitPO.id;
            const computer_keys = localStorage.getItem("SSID_EVO");
            try {
                const response = await axios.get(
                    `/api/v1/formulator/registrasi-material/batch/${id}/${computer_keys}`
                );
                if (
                    response.data.success &&
                    Array.isArray(response.data.result)
                ) {
                    splitPO.batches = response.data.result;
                    this.batches = this.getBatchesForSplitPO(splitPO);
                } else {
                    this.batches = this.getBatchesForSplitPO(splitPO);
                }
            } catch (error) {
                this.batches = this.getBatchesForSplitPO(splitPO);
            } finally {
                this.loading.batch = false;
            }
        },

        async fetchPoList() {
            this.loading.po = true;
            this.poMessage = "";

            const computer_keys = localStorage.getItem("SSID_EVO");
            try {
                const response = await axios.get(
                    `/api/v1/formulator/registrasi-material/po/${computer_keys}`
                );

                if (response.status === 204) {
                    this.poMessage = "Sudah Selesai PO semuanya";
                    this.poList = [];
                    this.productionOrders = [];
                } else if (
                    response.data.success &&
                    response.data.result &&
                    response.data.result.length > 0
                ) {
                    this.poList = response.data.result;
                    this.productionOrders = this.poList;
                    this.poMessage = "";
                } else {
                    this.poList = [];
                    this.productionOrders = [];
                    this.poMessage = "Tidak ada data PO sama sekali";
                }
            } catch (error) {
                console.log(error.response.data.message);
                this.poList = [];
                this.productionOrders = [];
                const defaultMessage = "Terjadi kesalahan saat memuat data PO";
                this.poMessage =
                    error?.response?.data?.message || defaultMessage;
            } finally {
                this.loading.po = false;
            }
        },

        openPinModal() {
            this.showPinModal = true;
            this.pin = Array(6).fill("");
            this.$nextTick(() => this.$refs.pinInput[0].focus());
        },
        selectMachine(value) {
            this.form.machineType = value.Id_Master_Mesin;
            this.form.Flag_Multi_Qrcode = value.Flag_Multi_Qrcode;
            this.form.Jumlah_Print_QRCode = value.Jumlah_Print_QRCode;
            this.isFlagKg = value.Flag_Kg;
            this.form.Jumlah_Pcs = value.Jumlah_Print_QRCode;
        },
        selectJenisAnalisaBerkala(value) {
            this.form.jenisAnalisaCheckBerkala = value.id;
        },
        formatNumber(num) {
            return num ? num.toLocaleString() : "0";
        },
        formatDate(dateString) {
            if (!dateString) return "-";

            // Ganti spasi dengan 'T' agar sesuai dengan format ISO 8601 yang lebih andal
            const compliantDateString = dateString.replace(" ", "T");

            const options = { year: "numeric", month: "short", day: "numeric" };
            // Gunakan string yang sudah dimodifikasi
            return new Date(compliantDateString).toLocaleDateString(
                "id-ID",
                options
            ); // Menggunakan "id-ID" untuk format Indonesia
        },
        isPoComplete(poCode) {
            const relatedSplitPOs = this.splitPOs.filter(
                (item) => item.parentPoCode === poCode
            );
            if (relatedSplitPOs.length === 0) return false;
            return relatedSplitPOs.every((splitPO) =>
                this.isSplitPoComplete(splitPO)
            );
        },
        isSplitPoComplete(splitPOItem) {
            const batches = this.getBatchesForSplitPO(splitPOItem);
            return batches.every((batch) => this.isBatchSaved(batch));
        },
        isSplitPoDisabled() {
            return false;
        },
        isPoDisabled(poCode) {
            const po = this.productionOrders.find(
                (p) => p.No_Faktur === poCode
            );

            if (!po) {
                return true;
            }

            return !po.is_selectable || this.isPoComplete(poCode);
        },
        getBatchesForSplitPO(splitPOItem) {
            if (splitPOItem.batches && Array.isArray(splitPOItem.batches)) {
                const processedBatches = splitPOItem.batches.map(
                    (batch, index) => ({
                        ...batch,
                        id:
                            batch.id ||
                            `${splitPOItem.No_Transaksi}-B${index + 1}`,
                        batch_number: index + 1,
                        Jumlah_Batch:
                            batch.Jumlah_Batch || splitPOItem.Jumlah_Batch,
                    })
                );

                return processedBatches;
            }
            const batchCount =
                splitPOItem.Jumlah_Batch ??
                splitPOItem.original_data?.Jumlah_Batch ??
                1;
            const batches = [];
            const batchQty = Math.floor(splitPOItem.Jumlah / batchCount);

            for (let i = 0; i < batchCount; i++) {
                batches.push({
                    id: `${splitPOItem.No_Transaksi}-B${i + 1}`,
                    batch_number: i + 1,
                    Jumlah_Batch: batchQty,
                    original_data: splitPOItem,
                });
            }

            return batches;
        },
        calculateTotalSplitQty(poCode) {
            const po = this.productionOrders.find(
                (p) => p.No_Faktur === poCode
            );
            return po ? this.formatNumber(po.quantity || po.Jumlah) : "0";
        },
        calculateSplitPoQty(splitPOItem) {
            return this.formatNumber(Number(splitPOItem.Jumlah));
        },
        // Ganti method selectProductionOrder Anda dengan yang ini
        selectProductionOrder(poCode) {
            this.selected.splitPO = null;
            this.selected.batch = null;
            this.form.machineType = "";
            this.form.notes = "";
            this.splitPOs = [];
            this.batches = [];

            const po = this.productionOrders.find(
                (p) => p.No_Faktur === poCode
            );

            // Jika PO tidak ditemukan, keluar saja.
            if (!po) return;

            // --- PERUBAHAN DI SINI ---
            // Jika PO terdeteksi disabled oleh logika kita
            if (this.isPoDisabled(poCode)) {
                // Tampilkan alert peringatan
                Swal.fire({
                    icon: "warning",
                    title: "Aksi Tidak Diizinkan",
                    text: "Anda harus menyelesaikan atau menutup PO sebelumnya terlebih dahulu!",
                    confirmButtonColor: "#d33",
                });
                return; // Hentikan eksekusi fungsi setelah alert tampil
            }
            // --- AKHIR PERUBAHAN ---

            if (this.selected.productionOrder === poCode) {
                this.selected.productionOrder = null;
                return;
            }

            this.selected.productionOrder = poCode;
            this.loading.splitPO = true;
            this.loadSplitPO(poCode);
        },

        selectSplitPO(item) {
            if (this.isSplitPoDisabled(item)) return;

            if (this.selected.splitPO?.No_Transaksi === item.No_Transaksi) {
                this.selected.splitPO = null;
                this.selected.batch = null;
                this.form = { machineType: "", notes: "" };
                return;
            }

            this.selected.splitPO = item;
            this.selected.batch = null;
            this.form = { machineType: "", notes: "" };
            this.loading.batch = true;
            this.loadBatches(item);
        },

        handleBatchClick(item) {
            this.selectBatch(item);
            this.fetchMachines();
        },

        selectBatch(item) {
            this.selected.batch = item;
            this.form = { machineType: "", notes: "" };
        },

        penPinModal() {
            this.showPinModal = true;
            this.pin = Array(6).fill("");
            this.$nextTick(() => this.$refs.pinInput[0].focus());
        },
        closePinModal() {
            this.showPinModal = false;
        },
        focusNext(index) {
            if (this.pin[index].length === 1 && index < 5) {
                this.$refs.pinInput[index + 1].focus();
            }
        },
        focusPrev(index, event) {
            if (event.key === "Backspace" && !this.pin[index] && index > 0) {
                this.$refs.pinInput[index - 1].focus();
            }
        },
        verifyPin() {
            this.closePinModal();
            this.saveDataToDatabase();
        },
        async saveDataToDatabase() {
            const isRutin = this.selectedSifatKegiatan === "Rutin";
            const isBerkalaGabungRutin =
                this.selectedSifatKegiatan === "Berkala" &&
                this.selectedOpsiBerkala === "ya";
            const isBerkalaSaja =
                this.selectedSifatKegiatan === "Berkala" &&
                this.selectedOpsiBerkala === "tidak";

            const isMultiQRCode = this.form.Flag_Multi_Qrcode === "Y";

            // Validasi Jumlah_Pcs jika Multi QRCode
            const jumlahPcsValid =
                !isMultiQRCode || (isMultiQRCode && this.form.Jumlah_Pcs > 0);

            console.log("machineType:", this.form.machineType);
            console.log("batch:", this.selected.batch);
            console.log("jumlahPcsValid:", jumlahPcsValid);
            console.log("isRutin:", isRutin);
            console.log("isBerkalaGabungRutin:", isBerkalaGabungRutin);
            console.log("isBerkalaSaja:", isBerkalaSaja);
            console.log("notes:", this.form.notes);
            console.log(
                "jenisAnalisaCheckBerkala:",
                this.form.jenisAnalisaCheckBerkala
            );

            const isValid =
                this.form.machineType &&
                this.selected.batch &&
                jumlahPcsValid &&
                (isRutin || isBerkalaGabungRutin ? this.form.notes : true) &&
                (isBerkalaSaja || isBerkalaGabungRutin
                    ? this.form.jenisAnalisaCheckBerkala
                    : true);

            if (!isValid) {
                return Swal.fire({
                    icon: "error",
                    title: "Gagal",
                    text:
                        isMultiQRCode && this.form.Jumlah_Pcs <= 0
                            ? "Jumlah PCS harus diisi jika QRCode Multi!"
                            : "Form Tidak Lengkap!",
                });
            }

            let jumlahPrintQRCode = this.form.Jumlah_Print_QRCode;
            if (isMultiQRCode && this.form.Jumlah_Pcs > 0) {
                jumlahPrintQRCode = this.form.Jumlah_Pcs;
            }

            // Susun Payload
            const payload = {
                pin: this.pin.join(""),
                namaKaryawan: this.penggunaAktif,
                No_Split_Po: this.selected.splitPO?.No_Transaksi || "",
                No_Batch: this.selected.batch.batch_number,
                Id_Mesin: this.form.machineType,
                Sifat_Kegiatan: this.selectedSifatKegiatan,
                Tanggal: new Date().toISOString().split("T")[0],
                Kode_Barang: this.selected.splitPO?.Kode_Barang,
                No_Po: this.selected.splitPO?.No_PO,
                Flag_Multi_Qrcode: this.form.Flag_Multi_Qrcode,
                Jumlah_Print_QRCode: jumlahPrintQRCode,
                Keterangan: this.form.notes,
                Berat_Sampel: this.form.Berat_Sampel,
            };

            // Tambahkan Jumlah_Pcs jika Multi QRCode
            if (isMultiQRCode) {
                payload.Jumlah_Pcs = this.form.Jumlah_Pcs;
            }

            // Tambahan untuk Berkala
            if (isBerkalaGabungRutin || isBerkalaSaja) {
                payload.Id_Jenis_Analisa_Khusus =
                    this.form.jenisAnalisaCheckBerkala;
                payload.Opsi_Keterangan = this.selectedOpsiBerkala;
            }

            // Simpan ke backend
            this.loading.saveToDatabase = true;

            try {
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");

                const response = await axios.post(
                    "/api/v1/formulator/registrasi-material/store",
                    payload,
                    {
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": csrfToken,
                        },
                    }
                );

                const result = response.data;

                Swal.fire({
                    icon: result.success ? "success" : "warning",
                    title: result.success ? "Success!" : "Warning!",
                    text:
                        result.message || "Data berhasil disimpan ke database",
                    timer: 9000,
                    showConfirmButton: false,
                });

                if (result.success) {
                    // Reset form
                    this.form.machineType = "";
                    this.form.notes = "";
                    this.form.Berat_Sampel = "";
                    this.form.jenisAnalisaCheckBerkala = "";
                    this.selectedOpsiBerkala = "";

                    // Reload batch
                    const selectedBatchNumber =
                        this.selected.batch.batch_number;
                    this.loading.batch = true;
                    await this.loadSplitPO(this.selected.productionOrder);
                    await this.loadBatches(this.selected.splitPO);
                    this.loading.batch = false;

                    const foundBatch = this.batches.find(
                        (b) => b.batch_number === selectedBatchNumber
                    );
                    if (foundBatch?.is_complete) {
                        this.selected.batch = null;
                        await Swal.fire({
                            icon: "info",
                            title: "Batch Complete",
                            text: "Batch ini telah selesai. Panel input ditutup.",
                            confirmButtonText: "OK",
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                        });
                    } else {
                        this.selected.batch = foundBatch || null;
                    }

                    // Reload mesin
                    this.loading.machine = true;
                    await this.fetchMachines();
                    this.loading.machine = false;
                }
            } catch (error) {
                console.error("Error saving data:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error!",
                    text: "Gagal menyimpan data: " + error.message,
                    confirmButtonText: "OK",
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                });
            } finally {
                this.loading.saveToDatabase = false;
            }
        },
        isBatchSaved(batch) {
            return batch.is_complete === true;
        },
        lightenColor(color, percent) {
            // Implementasi lighten color
            // Contoh sederhana (gunakan library warna untuk implementasi nyata)
            return color;
        },
        darkenColor(color, percent) {
            // Implementasi darken color
            // Contoh sederhana (gunakan library warna untuk implementasi nyata)
            return color;
        },
        setPenggunaAktif() {
            const encrypted = localStorage.getItem("SSID_EmI_Lab_EVO_RS");
            if (encrypted) {
                try {
                    const SECRET_KEY = import.meta.env.VITE_SECRET_KEY;
                    const bytes = CryptoJS.AES.decrypt(encrypted, SECRET_KEY);
                    const decryptedData = JSON.parse(
                        bytes.toString(CryptoJS.enc.Utf8)
                    );
                    this.penggunaAktif = decryptedData;
                } catch (e) {
                    console.error("Gagal dekripsi:", e);
                    this.penggunaAktif = null;
                }
            } else {
                this.penggunaAktif = this.pengguna;
            }
        },
        checkJumlahPcs(event) {
            const allowedKeys = [
                "Backspace",
                "Delete",
                "ArrowLeft",
                "ArrowRight",
                "Tab",
            ];

            // Hanya izinkan angka 0-9 dan tombol yang diizinkan
            if (
                !/^[0-9]$/.test(event.key) &&
                !allowedKeys.includes(event.key)
            ) {
                event.preventDefault();
                return;
            }

            const input = event.target;
            const currentValue = input.value;
            const selectionStart = input.selectionStart;
            const selectionEnd = input.selectionEnd;

            const newValue =
                currentValue.substring(0, selectionStart) +
                event.key +
                currentValue.substring(selectionEnd);
        },
    },
    watch: {
        "form.machineType"(newValue) {
            console.log(`Nilai Baru (newValue): "${newValue}"`);
        },
    },
    beforeDestroy() {
        clearInterval(this.timer);
        window.removeEventListener("storage", this.setPenggunaAktif);
        if (this.socket) {
            this.socket.close();
        }
        if (this.changeTimeout) {
            clearTimeout(this.changeTimeout);
        }
    },
};
</script>

<style scoped>
/* Base Styles */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.btn-close-po {
    display: inline-flex; /* Menggunakan Flexbox untuk menata ikon dan teks */
    align-items: center; /* Menyelaraskan ikon dan teks secara vertikal */
    padding: 1px 10px;
    font-size: 0.8rem;
    height: 40px;
    font-weight: 500;
    color: white;
    background-color: #dc3545; /* Warna merah untuk aksi "danger" */
    border: none;
    border-radius: 5px; /* Sudut sedikit lebih tumpul */
    cursor: pointer;
    transition: background-color 0.2s ease;
    gap: 5px; /* Jarak antara ikon dan teks */
}

.btn-close-po:hover {
    background-color: #c82333; /* Warna merah lebih gelap saat di-hover */
}

/* CSS */
.loading-text {
    font-family: sans-serif;
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
    display: inline-flex;
    align-items: center;
}

.loading-text .dot1,
.loading-text .dot2,
.loading-text .dot3 {
    animation: blink 1.5s infinite;
    opacity: 0;
}

.loading-text .dot1 {
    animation-delay: 0s;
}

.loading-text .dot2 {
    animation-delay: 0.3s;
}

.loading-text .dot3 {
    animation-delay: 0.6s;
}

@keyframes blink {
    0% {
        opacity: 0;
    }
    30% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid white;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* loading save to database */

/* Fullscreen overlay to blur background */
.loading-container-save-to-database {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: rgba(
        0,
        0,
        0,
        0.5
    ); /* Semi-transparent black background */
    z-index: 9999; /* Ensure it is above all other content */
}

.loading-container-save-to-database .blur-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    backdrop-filter: blur(10px); /* Apply blur effect to the background */
    z-index: -1; /* Ensure the blur overlay is behind the loader */
}

.scroll-wrapper {
    overflow-x: auto;
    padding-bottom: 1rem;
    scrollbar-width: thin;
}

.scroll-wrapper::-webkit-scrollbar {
    height: 8px;
}
.scroll-wrapper::-webkit-scrollbar-track {
    background: rgba(241, 241, 241, 0.5);
    border-radius: 10px;
}
.scroll-wrapper::-webkit-scrollbar-thumb {
    background: rgba(187, 187, 187, 0.6);
    border-radius: 10px;
    backdrop-filter: blur(4px);
}
.scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: rgba(153, 153, 153, 0.8);
}

.custom-switch-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 1rem;
}

.custom-switch-input {
    width: 50px;
    height: 24px;
    cursor: pointer;
}

.custom-switch-label {
    font-weight: 600;
    font-size: 1rem;
    margin: 0;
    user-select: none;
    min-width: 70px;
    text-align: left;
}

.badge-item {
    position: relative;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 10px;
    cursor: pointer;
    text-align: center;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    overflow: hidden;
    font-weight: 500;
    color: white;
    border: none;
    background-color: var(--bg-color);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}

.badge-item:hover {
    background-color: var(--hover-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.badge-item.selected {
    background-color: var(--selected-color);
    box-shadow: 0 0 0 2px white, 0 0 0 4px var(--selected-color),
        0 4px 12px rgba(0, 0, 0, 0.2);
    font-weight: 600;
}

.badge-item.selected::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.2) 0%,
        rgba(255, 255, 255, 0) 50%,
        rgba(255, 255, 255, 0.2) 100%
    );
    animation: shimmer 3s infinite linear;
    z-index: 0;
}

.badge-item > * {
    position: relative;
    z-index: 1;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%) skewX(-15deg);
    }
    100% {
        transform: translateX(100%) skewX(-15deg);
    }
}

/* Optional styling for the loading spinner itself */
.loading-container-save-to-database .dotlottie-container {
    z-index: 1;
}
.opsi-berkala-group {
    margin-bottom: 1rem;
    font-family: Arial, sans-serif;
}

.opsi-berkala-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
    color: #333;
}

.opsi-berkala-options {
    display: flex;
    gap: 1.5rem;
    padding-left: 1rem;
    font-size: 0.85rem;
    align-items: center;
}

.opsi-berkala-radio {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
    user-select: none;
}

.opsi-berkala-radio input[type="radio"] {
    width: 14px;
    height: 14px;
    accent-color: #0d6efd; /* Warna biru */
    cursor: pointer;
    margin: 0;
}

/* Modern dot flashing loader */
.modern-loader {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
}

.loader-text {
    font-size: 14px;
    color: white;
}

.dot-flashing {
    position: relative;
    width: 50px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dot-flashing::before,
.dot-flashing::after,
.dot-flashing div {
    content: "";
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #29569f;
    animation: dot-flashing 1s infinite alternate;
}

.dot-flashing::before {
    animation-delay: 0s;
}

.dot-flashing div {
    animation-delay: 0.2s;
}

.dot-flashing::after {
    animation-delay: 0.4s;
}

@keyframes dot-flashing {
    0% {
        opacity: 0.3;
        transform: scale(0.8);
    }
    100% {
        opacity: 1;
        transform: scale(1.1);
    }
}

.po-message-error {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
    font-size: 14px;
    border: 1px solid #f5c6cb;
}

.po-message-success {
    background-color: #d4edda;
    color: #155724;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
    font-size: 14px;
    border: 1px solid #c3e6cb;
}

/* modal pin */

.btn-submit {
    background: linear-gradient(135deg, #29569f 0%, #3a7bd5 100%);
    color: white;
    border: none;
    padding: 0.7rem 1.5rem;
    font-size: 16px;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(0, 191, 255, 0.4);
    transition: transform 0.2s, box-shadow 0.2s;
}

.btn-submit:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 18px rgba(0, 191, 255, 0.5);
}

.btn-cancel {
    background-color: #ccc;
    color: #333;
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 8px;
    margin-left: 1rem;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-cancel:hover {
    background-color: #aaa;
}

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    animation: fadeIn 0.3s ease-out forwards;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}

.modal-box {
    background: linear-gradient(145deg, #ffffff, #f8f9fa);
    border-radius: 18px;
    padding: 2rem;
    width: 380px;
    text-align: center;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    transform: translateY(20px);
    animation: slideUp 0.3s ease-out forwards;
    position: relative;
    overflow: hidden;
}

@keyframes slideUp {
    to {
        transform: translateY(0);
    }
}

.modal-box::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(90deg, #405189 0%, #042bab 100%);
}

.modal-title-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.modal-title {
    font-size: 1.4rem;
    font-weight: 600;
    margin: 0;
    color: #2c3e50;
    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
}

.current-user {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin-bottom: 1.8rem;
    padding: 0.5rem;
    background: #f1f5f9;
    border-radius: 8px;
    display: inline-block;
}

.current-user strong {
    color: #3498db;
    font-weight: 600;
}

.pin-container {
    display: flex;
    justify-content: center;
    gap: 0.8rem;
    margin-bottom: 2rem;
}

.pin-input {
    width: 3rem;
    height: 3.5rem;
    font-size: 1.5rem;
    text-align: center;
    border: 1px solid #e0e6ed;
    border-radius: 10px;
    outline: none;
    transition: all 0.3s ease;
    background: #f8fafc;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    color: #2d3748;
}

.pin-input:focus {
    border-color: #405189;
    box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
    transform: translateY(-2px);
}

.modal-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn {
    padding: 0.7rem 1.5rem;
    border-radius: 10px;
    font-weight: 500;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    outline: none;
    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
}

.btn-submit {
    background: linear-gradient(135deg, #405189 0%, #1a2c67 100%);
    color: white;
    box-shadow: 0 4px 10px rgba(79, 172, 254, 0.3);
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgb(45, 117, 180);
}

.btn-submit:active {
    transform: translateY(0);
}

.btn-cancel {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}

.btn-cancel:hover {
    background: #e2e8f0;
}

.btn-change-user {
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    transition: color 0.2s;
    padding: 0.3rem 0.6rem;
    border-radius: 6px;
}

.btn-change-user:hover {
    color: #4facfe;
    background: #f1f8ff;
}

.form-group {
    margin-bottom: 1.2rem;
    text-align: left;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    color: #475569;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s;
    background: #f8fafc;
}

.form-control:focus {
    border-color: #4facfe;
    box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
    outline: none;
}

.security-tip {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 14px;
    background-color: #fff7e6;
    color: #b45309;
    border: 1px solid #fde68a;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.security-tip svg {
    width: 20px;
    height: 20px;
    fill: #b45309;
}

.change-user-tip {
    font-size: 0.85rem;
    color: #555;
    text-align: center;
    margin-bottom: 1.5rem;
}

.multi-pane-container {
    display: flex;
    width: 100%;
    overflow-x: auto;
    background: #f5f7fa;
    padding: 8px;
    gap: 8px;
    margin-bottom: 20px;
}

.pane {
    flex: 0 0 280px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 12px;
    height: 500px;
    overflow-y: auto;
    transition: all 0.3s ease;
    position: relative;
}

.loading-container {
    position: relative;
    height: 100%; /* pastikan container mengisi pane */
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Add to existing styles */
.po-total-qty {
    margin-left: 8px;
    font-size: 12px;
    font-weight: normal;
    color: #666;
    background: rgba(0, 0, 0, 0.05);
    padding: 2px 6px;
    border-radius: 10px;
}

.split-po-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.split-po-code {
    font-weight: 600;
    font-size: 13px;
    color: inherit;
}

.split-po-qty {
    font-size: 12px;
    color: #666;
    background: rgba(0, 0, 0, 0.05);
    padding: 2px 8px;
    border-radius: 10px;
    display: inline-block;
}

.split-po-header .status-badge {
    position: static;
    transform: none;
    margin-left: 8px;
}

.machine-list {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 150px;
    overflow-y: auto;
    border: 1px solid #ccc;
    border-radius: 8px;
    background-color: #f9f9f9;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    animation: fadeInSlide 0.5s ease;
    scroll-behavior: smooth;
}

/* Animasi saat masuk */
@keyframes fadeInSlide {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.machine-list li {
    padding: 12px 18px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    background-color: transparent;
    transition: background-color 0.25s ease, transform 0.2s ease;
    position: relative;
}

/* Efek klik: scale ringan */
.machine-list li:active {
    transform: scale(0.98);
}

.machine-list li:hover {
    background-color: #edf4ff;
}

.machine-list li.selected {
    background-color: #405189;
    color: white;
    font-weight: bold;
    border-left: 5px solid #3e4b79;
}

/* Gradient efek tipis saat hover */
.machine-list li:hover::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 4px;
    background-color: #576bad;
}

/* Header dalam item */
.machine-item {
    padding: 12px 16px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background-color: #fff;
    transition: all 0.3s ease;
    cursor: pointer;
}

.machine-item:hover {
    background-color: #f5faff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
}

.machine-item.selected {
    border-color: #405189;
    background-color: #e6f0ff;
}

.machine-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.machine-icon {
    font-size: 20px;
    color: #007bff;
}

.machine-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.machine-name {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.machine-serial {
    font-size: 12px;
    font-style: italic;
    color: #555;
    background-color: #eee;
    padding: 2px 8px;
    border-radius: 12px;
    width: auto;
    display: inline-block;
    transition: color 0.3s ease;
}

.scrollable-machine-list {
    margin-bottom: 1rem;
}

/* Saat LI dipilih → serial jadi putih */
.machine-list li.selected .machine-serial {
    color: #000;
}
.machine-list li.selected .machine-name {
    color: white;
}

/* Tapi kalau LI dipilih & di-hover → serial jadi hitam */
.machine-list li.selected:hover .machine-serial {
    color: #000;
}
.machine-list li.selected:hover .machine-name {
    color: #000;
}

.machine-item.already-input {
    background-color: #d4edda !important; /* Hijau muda */
    border: 1px solid #28a745;
}

.machine-item.already-input:hover {
    background-color: #c3e6cb !important;
    box-shadow: 0 2px 6px rgba(40, 167, 69, 0.2);
}
.machine-item.already-input.selected {
    background-color: #87b992 !important;
    box-shadow: 0 2px 6px rgba(40, 167, 69, 0.2);
}

.machine-item.already-input .machine-name {
    color: #155724;
}

.machine-item.already-input .machine-serial {
    background-color: #b1dfbb;
    color: #155724;
}

.machine-count {
    font-size: 12px;
    font-weight: bold;
    color: #155724;
    background-color: #c3e6cb;
    padding: 3px 10px;
    border-radius: 20px;
    display: inline-block;
    margin-top: 2px;
}

.pane::after {
    content: "";
    position: absolute;
    right: -4px;
    top: 0;
    height: 100%;
    width: 1px;
    background: rgba(0, 0, 0, 0.1);
}

.pane:last-child::after {
    display: none;
}

.pane.disabled {
    opacity: 0.6;
    pointer-events: none;
}

.pane.active {
    border-left: 4px solid #4caf50;
}

.po-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}

.po-code {
    font-weight: 600;
    font-size: 13px;
    color: inherit;
}

.po-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.po-name {
    font-size: 12px;
    color: #555;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.po-meta {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #777;
}

.po-qty {
    background: rgba(0, 0, 0, 0.05);
    padding: 2px 6px;
    border-radius: 10px;
}

.po-date {
    font-style: italic;
}

.user-card {
    display: flex;
    align-items: flex-start;
    background: linear-gradient(135deg, #405189 0%, #6b78b4 100%);
    color: #2d3748;
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 32px rgba(0, 0, 0, 0.12);
}

.avatar-container {
    margin-right: 1.5rem;
    position: relative;
    z-index: 2;
    background: linear-gradient(135deg, #405189 0%, #6b78b4 100%);
    border-radius: 12px;
    padding: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.user-details {
    flex: 1;
    position: relative;
    z-index: 2;
    min-width: 0;
}

.user-header {
    margin-bottom: 1rem;
}

.user-name {
    font-size: 1.6rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: white;
    letter-spacing: -0.5px;
}

/* Container Badge */
.paper-badge {
    display: flex;
    align-items: center;
    gap: 6px; /* Jarak antar elemen diperkecil sedikit agar compact */
    background: rgba(255, 255, 255, 0.15);
    padding: 0.35rem 0.85rem; /* Padding sedikit disesuaikan */
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); /* Sedikit bayangan */
}

/* Titik Indikator (Tetap sama) */
.indicator-dot {
    width: 8px;
    height: 8px;
    background-color: #4ade80;
    border-radius: 50%;
    box-shadow: 0 0 10px #4ade80; /* Glow statis */
    animation: pulse-green 2s infinite;
    margin-right: 4px; /* Memberi jarak khusus dengan ikon printer */
}

/* BARU: Styling Ikon Printer */
.paper-icon {
    font-size: 1.1rem; /* Ukuran ikon sedikit lebih besar dari teks */
    line-height: 1; /* Menjaga ikon tetap vertikal center */
    opacity: 0.9; /* Sedikit transparan agar elegan */
}

/* Teks Kertas */
.paper-text {
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    letter-spacing: 0.5px;
    padding-top: 1px; /* Micro-adjustment agar sejajar mata dengan ikon */
}

/* Animasi Pulse (Tetap sama) */
@keyframes pulse-green {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 6px rgba(74, 222, 128, 0);
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(74, 222, 128, 0);
    }
}

.user-meta {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 0.85rem;
    color: #64748b;
}

.user-meta {
    color: white !important;
}

.role-badge {
    background: #e0e7ff;
    color: #4f46e5;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.last-activity {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    margin-bottom: 1.5rem;
    color: #64748b;
    background: #f8fafc;
    padding: 0.75rem;
    border-radius: 8px;
}

.last-activity i {
    font-size: 1rem;
}

.activity-time {
    font-weight: 500;
    color: #475569;
    margin-left: 0.25rem;
}

.user-stats {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.stat-item {
    text-align: center;
    min-width: 100px;
    color: white;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    transition: transform 0.2s ease;
}

.stat-item:hover {
    transform: translateY(-2px);
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: white;
    letter-spacing: -1px;
}

.stat-label {
    color: white;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.btn-print-qr {
    margin-left: auto;
    padding: 0.75rem 1.25rem;
    background: #ffffff;
    color: #4f46e5;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.15);
    transition: all 0.3s ease;
    flex-shrink: 0;
    white-space: nowrap;
    user-select: none;
    border: 1px solid #e0e7ff;
    cursor: pointer;
    font-size: 0.85rem;
    gap: 0.5rem;
}

.btn-print-qr:hover {
    background: #4f46e5;
    color: white;
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.25);
    transform: translateY(-1px);
}

.btn-print-qr i {
    font-size: 1.1rem;
}

h3 {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
}

h3 span {
    margin-left: auto;
    font-size: 12px;
    color: #666;
    font-weight: normal;
}

/* List Styles */
ul {
    list-style: none;
}

li {
    padding: 12px;
    margin-bottom: 6px;
    background: #f9f9f9;
    border-radius: 6px;
    cursor: pointer;
    user-select: none;
    transition: all 0.2s;
    position: relative;
    font-size: 13px;
    color: #444;
    border: 1px solid #eee;
}

li:hover:not(.disabled) {
    background: #eef7ff;
    border-color: #d0e3ff;
}

li.selected {
    background: #e3f2fd;
    border-color: #bbdefb;
    color: #1976d2;
    font-weight: 500;
}

li.disabled {
    background: #f5f5f5;
    color: #9e9e9e;
    cursor: not-allowed;
}

li.complete {
    background: #e8f5e9;
    border-color: #c8e6c9;
    color: #2e7d32;
}

.status-badge {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}

.status-badge.complete {
    background: #4caf50;
    color: white;
}

/* Form Styles */
.form-pane {
    flex: 0 0 320px;
}

.form-group {
    margin-bottom: 16px;
}

.form-check {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #555;
}

.form-check-input.custom-radio {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid #ccc;
    appearance: none;
    -webkit-appearance: none;
    outline: none;
    cursor: pointer;
    position: relative;
    transition: border-color 0.2s ease;
}

.form-check-input.custom-radio:checked {
    border-color: #4caf50;
    background-color: #4caf50;
}

.form-check-input.custom-radio::before {
    content: "";
    position: absolute;
    top: 3px;
    left: 3px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: white;
    transform: scale(0);
    transition: transform 0.2s ease-in-out;
}

.form-check-input.custom-radio:checked::before {
    transform: scale(1);
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #e74c3c;
    margin-top: 5px;
    margin-left: 4px;
    transition: all 0.3s ease;
}

.status-indicator.active {
    background-color: #2ecc71;
    box-shadow: 0 0 10px rgba(46, 204, 113, 0.5);
}

.last-updated {
    margin-top: 20px;
    color: #95a5a6;
    font-size: 0.9rem;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
    color: #555;
}

select,
input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 13px;
    transition: border 0.2s;
}

select:focus,
input:focus {
    outline: none;
    border-color: #4caf50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
}

.save-btn {
    width: 100%;
    padding: 12px;
    background: #4caf50;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.save-btn:disabled {
    background: #a5d6a7;
    color: white;
    cursor: not-allowed;
    opacity: 0.7;
}

.save-btn:hover {
    background: #43a047;
}

.save-btn .icon {
    font-size: 16px;
}

/* Touch optimizations */
@media (hover: none) {
    li {
        padding: 14px 12px;
    }

    .save-btn {
        padding: 14px;
    }
}

/* Scrollbar styling */
.multi-pane-container::-webkit-scrollbar {
    height: 6px;
}

.multi-pane-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.multi-pane-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.multi-pane-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.pane::-webkit-scrollbar {
    width: 6px;
}

.pane::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.pane::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.pane::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.split-po-message {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px;
    border-radius: 5px;
    margin-top: 10px;
    font-size: 14px;
    border: 1px solid #f5c6cb;
}

@media (min-width: 1440px) {
    .multi-pane-container {
        gap: 30px;
    }

    .pane {
        flex: 0 0 380px;
        /* padding: 24px;  */
    }

    .pane h3 {
        font-size: 18px;
    }

    .pane ul li {
        font-size: 14px;
    }
}
</style>

<style scoped>
/* Gaya dasar tetap sama */
.input-with-timbangan {
    position: relative;
}
.popup-border-danger {
    border: 3px solid #d33 !important; /* !important untuk memastikan style diterapkan */
}

.berat-display-integrated {
    position: relative;
    font-size: 1.8rem;
    font-weight: 300;
    text-align: center;
    padding: 20px;
    background-color: white;
    border-radius: 12px;
    color: #2c3e50;
    transition: all 0.3s ease;
    margin: 10px 0;
    border: 1px solid #e0e0e0;
    cursor: pointer;
    overflow: hidden;
    z-index: 1;
}

.berat-display-integrated::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        135deg,
        rgba(46, 204, 113, 0.1) 0%,
        rgba(52, 152, 219, 0.1) 100%
    );
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
}

.berat-display-integrated.pulse::after {
    opacity: 1;
    animation: pulseAfter 1.5s infinite;
}

.berat-display-integrated.connected {
    border-color: #2ecc71;
}

.berat-display-integrated.manual-mode {
    padding: 0;
    background: transparent;
    border: none;
}

.manual-weight-input {
    width: 100%;
    font-size: 1.8rem;

    outline: none;
    transition: all 0.3s ease;
}

.manual-weight-input:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}

.input-hint {
    text-align: center;
    margin-top: -8px;
    color: #95a5a6;
    font-size: 0.75rem;
}

.unit {
    font-size: 1.2rem; /* Smaller unit size */
}

@keyframes pulseAfter {
    0% {
        opacity: 0.3;
        background: linear-gradient(
            135deg,
            rgba(46, 204, 113, 0.1) 0%,
            rgba(52, 152, 219, 0.1) 100%
        );
    }
    50% {
        opacity: 0.7;
        background: linear-gradient(
            135deg,
            rgba(46, 204, 113, 0.2) 0%,
            rgba(52, 152, 219, 0.2) 100%
        );
    }
    100% {
        opacity: 0.3;
        background: linear-gradient(
            135deg,
            rgba(46, 204, 113, 0.1) 0%,
            rgba(52, 152, 219, 0.1) 100%
        );
    }
}

/* Animasi untuk angka yang berubah */
@keyframes valueChange {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
    }
}

.berat-display-integrated.pulse {
    animation: valueChange 0.5s ease;
}
</style>
