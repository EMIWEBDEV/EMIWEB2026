require("dotenv").config();

const { SerialPort } = require("serialport");
const { ReadlineParser } = require("@serialport/parser-readline");
const { WebSocketServer } = require("ws");
const os = require("os");

/* ================================
   CONFIGURATION (ENV SUPPORT)
================================ */
const HOST = process.env.WS_HOST || "0.0.0.0";
const PORT = process.env.WS_PORT || 6001;

/* ================================
   WEBSOCKET SERVER
================================ */
const wss = new WebSocketServer({ host: HOST, port: PORT });

let activePort = null;
let simulationInterval = null;

/* ================================
   UTIL: GET LOCAL IPs
================================ */
function getLocalIPs() {
    const interfaces = os.networkInterfaces();
    const results = [];

    for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name]) {
            if (iface.family === "IPv4" && !iface.internal) {
                results.push(iface.address);
            }
        }
    }

    return results;
}

/* ================================
   STARTUP LOG
================================ */
wss.on("listening", () => {
    const ips = getLocalIPs();

    console.log("==================================================");
    console.log("🚀 WEBSOCKET TIMBANGAN SERVER BERJALAN");
    console.log("==================================================");
    console.log(`📡 Binding Host : ${HOST}`);
    console.log(`🔌 Port         : ${PORT}`);
    console.log("");
    console.log("🌐 Akses dari browser / client:");
    ips.forEach((ip) => {
        console.log(`   ➜ ws://${ip}:${PORT}`);
    });
    console.log("==================================================\n");
});

wss.on("error", (err) => {
    console.error("❌ WebSocket Server Error:", err.message);
});

/* ================================
   BROADCAST FUNCTION
================================ */
function broadcastBerat(data) {
    wss.clients.forEach((client) => {
        if (client.readyState === 1) {
            client.send(data);
        }
    });
}

/* ================================
   STOP ALL ACTIVE PROCESS
================================ */
function stopAllProcesses(callback) {
    if (simulationInterval) {
        clearInterval(simulationInterval);
        simulationInterval = null;
        console.log("🛑 Simulasi dihentikan.");
    }

    if (activePort && activePort.isOpen) {
        activePort.close(() => {
            console.log("🔒 Port serial ditutup.");
            activePort = null;
            if (callback) callback();
        });
    } else {
        if (callback) callback();
    }
}

/* ================================
   INITIALIZE SERIAL CONNECTION
================================ */
function initializeConnection(config) {
    const {
        COM_TARGET,
        BAUD_RATE = 9600,
        dataBits = 7,
        parity = "even",
        stopBits = 1,
    } = config;

    console.log("\n⚙️  Inisialisasi koneksi timbangan...");
    console.log("   Target COM :", COM_TARGET);
    console.log("   Baud Rate  :", BAUD_RATE);

    SerialPort.list()
        .then((ports) => {
            const targetPortInfo = ports.find((p) =>
                p.path.includes(COM_TARGET)
            );

            if (!targetPortInfo) {
                console.warn(`⚠️ Port ${COM_TARGET} tidak ditemukan.`);
                console.warn("🔄 Mengaktifkan mode simulasi...\n");

                simulationInterval = setInterval(() => {
                    const beratSimulasi = (Math.random() * 100).toFixed(3);
                    const payload = JSON.stringify({
                        berat: beratSimulasi,
                        status: "ST",
                    });
                    broadcastBerat(payload);
                }, 1000);

                return;
            }

            console.log(
                `🔌 Menghubungkan ke ${targetPortInfo.path} @ ${BAUD_RATE}bps...`
            );

            activePort = new SerialPort({
                path: targetPortInfo.path,
                baudRate: BAUD_RATE,
                dataBits,
                parity,
                stopBits,
                autoOpen: true,
            });

            const parser = activePort.pipe(
                new ReadlineParser({ delimiter: "\r\n" })
            );

            parser.on("data", (line) => {
                const match = line.match(/[-+]?[0-9]*\.?[0-9]+/);
                const berat = match ? parseFloat(match[0]) : null;
                const isStabil = line.includes("ST");

                if (!isNaN(berat)) {
                    const payload = JSON.stringify({
                        berat: berat.toFixed(3),
                        status: isStabil ? "ST" : "US",
                    });

                    broadcastBerat(payload);
                }
            });

            activePort.on("open", () => {
                console.log("✅ Serial Port berhasil dibuka.\n");
            });

            activePort.on("error", (err) => {
                console.error("❌ Error pada serial port:", err.message);
            });

            activePort.on("close", () => {
                console.warn("⚠️ Serial port tertutup.");
            });
        })
        .catch((err) => {
            console.error("❌ Gagal mendapatkan daftar port:", err);
        });
}

/* ================================
   CLIENT CONNECTION
================================ */
wss.on("connection", (ws, req) => {
    console.log(`🌐 Client terhubung dari ${req.socket.remoteAddress}`);

    ws.on("message", (message) => {
        try {
            const msg = JSON.parse(message);

            if (msg.type === "config" && msg.payload) {
                console.log("📥 Konfigurasi diterima:", msg.payload);

                stopAllProcesses(() => {
                    initializeConnection(msg.payload);
                });
            }
        } catch (error) {
            console.error("❌ Gagal parsing pesan:", error.message);
        }
    });

    ws.on("close", () => {
        console.log("❌ Client terputus.");

        if (wss.clients.size === 0) {
            console.log("ℹ️ Tidak ada client aktif.");
            stopAllProcesses();
        }
    });
});
