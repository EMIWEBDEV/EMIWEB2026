/* driver-timbangan-cloud.js */
require("dotenv").config();
const { SerialPort } = require("serialport");
const { ReadlineParser } = require("@serialport/parser-readline");
const { WebSocketServer } = require("ws");
const os = require("os");
const fs = require("fs"); // TAMBAHAN: Untuk baca file
const https = require("https"); // TAMBAHAN: Untuk server secure

/* ================================
   CONFIGURATION
================================ */
const HOST = process.env.WS_HOST || "0.0.0.0";
const PORT = process.env.WS_PORT || 6001;

/* ================================
   LOAD SSL CERTIFICATES (MKCERT)
================================ */
// Pastikan file cert.pem dan key.pem ada di folder yang sama dengan script ini
const serverOptions = {
    cert: fs.readFileSync("cert.pem"),
    key: fs.readFileSync("key.pem"),
};

/* ================================
   SECURE HTTPS SERVER
================================ */
// Kita buat HTTPS server dulu
const server = https.createServer(serverOptions, (req, res) => {
    res.writeHead(200);
    res.end("WSS Secure Server Timbangan Online");
});

// Lalu WebSocket "menumpang" di atas HTTPS server tersebut
const wss = new WebSocketServer({ server });

let activePort = null;
let simulationInterval = null;

/* ================================
   STARTUP LOG
================================ */
// Ubah dari wss.on('listening') menjadi server.listen
server.listen(PORT, HOST, () => {
    const ips = getLocalIPs();

    console.log("==================================================");
    console.log("🚀 SECURE WEBSOCKET (WSS) BERJALAN");
    console.log("==================================================");
    console.log(`🔒 Status       : SECURE (HTTPS/WSS)`);
    console.log(`🔌 Port         : ${PORT}`);
    console.log("");
    console.log("🌐 Masukkan URL ini ke ENV Cloud Run:");
    console.log(`   ➜ wss://localhost:${PORT}`);
    console.log("==================================================\n");
});

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
   BROADCAST FUNCTION
================================ */
function broadcastBerat(data) {
    wss.clients.forEach((client) => {
        if (client.readyState === 1) {
            // 1 = OPEN
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

            console.log(`🔌 Menghubungkan ke ${targetPortInfo.path}...`);

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

            activePort.on("open", () =>
                console.log("✅ Serial Port berhasil dibuka.\n")
            );
            activePort.on("error", (err) =>
                console.error("❌ Error serial:", err.message)
            );
        })
        .catch((err) => console.error("❌ Gagal list port:", err));
}

/* ================================
   CLIENT CONNECTION
================================ */
wss.on("connection", (ws, req) => {
    console.log(`🌐 Client Secure terhubung!`);

    ws.on("message", (message) => {
        try {
            const msg = JSON.parse(message);
            if (msg.type === "config" && msg.payload) {
                console.log("📥 Konfigurasi diterima");
                stopAllProcesses(() => {
                    initializeConnection(msg.payload);
                });
            }
        } catch (error) {
            console.error("❌ Gagal parsing pesan:", error.message);
        }
    });

    ws.on("close", () => {
        if (wss.clients.size === 0) stopAllProcesses();
    });
});
