const { SerialPort } = require("serialport");
const { ReadlineParser } = require("@serialport/parser-readline");
const { WebSocketServer } = require("ws");

const wss = new WebSocketServer({ port: 6001 });
console.log("✅ WebSocket Server aktif di ws://localhost:6001");

let activePort = null;
let simulationInterval = null;

function broadcastBerat(berat) {
    wss.clients.forEach((client) => {
        if (client.readyState === 1) {
            client.send(berat);
        }
    });
}

function stopAllProcesses(callback) {
    if (simulationInterval) {
        clearInterval(simulationInterval);
        simulationInterval = null;
        console.log("[!] Simulasi dihentikan.");
    }
    if (activePort && activePort.isOpen) {
        activePort.close(() => {
            activePort = null;
            console.log("[!] Port serial ditutup.");
            if (callback) callback();
        });
    } else if (callback) {
        callback();
    }
}

function initializeConnection(config) {
    const { COM_TARGET, BAUD_RATE = 2400 } = config;

    SerialPort.list()
        .then((ports) => {
            const targetPortInfo = ports.find((p) =>
                p.path.includes(COM_TARGET)
            );

            if (!targetPortInfo) {
                console.warn(`[!] Port ${COM_TARGET} tidak ditemukan.`);
                simulationInterval = setInterval(() => {
                    const beratSimulasi = (Math.random() * 100).toFixed(2);
                    broadcastBerat(beratSimulasi);
                }, 1000);
                return;
            }

            console.log(
                `🔌 Menghubungkan ke ${targetPortInfo.path} @ ${BAUD_RATE}bps...`
            );
            activePort = new SerialPort({
                path: targetPortInfo.path,
                baudRate: 9600,
                dataBits: 7, // ✅ sesuai FG-60KA
                parity: "even", // ✅ sesuai FG-60KA
                stopBits: 1,
                autoOpen: true,
            });

            const parser = activePort.pipe(
                new ReadlineParser({ delimiter: "\r\n" })
            );

            parser.on("data", (line) => {
                console.log("📥 Data parser:", JSON.stringify(line));

                const match = line.match(/[-+]?[0-9]*\.?[0-9]+/);
                const berat = match ? parseFloat(match[0]) : null;

                // Deteksi apakah ada status ST atau US (Unstable)
                const isStabil = line.includes("ST"); // Atau sesuaikan dengan output aktual dari timbangan

                if (!isNaN(berat)) {
                    const dataToSend = JSON.stringify({
                        berat: berat.toFixed(3),
                        status: isStabil ? "ST" : "US",
                    });
                    broadcastBerat(dataToSend);
                }
            });

            // parser.on("data", (line) => {
            //     console.log("📥 Data parser:", JSON.stringify(line));

            //     // Ekstrak angka berat dari string seperti "+00002.120kg"
            //     const match = line.match(/[-+]?[0-9]*\.?[0-9]+/);
            //     if (match) {
            //         const berat = parseFloat(match[0]);
            //         if (!isNaN(berat)) {
            //             broadcastBerat(berat.toFixed(3));
            //         }
            //     }
            // });

            activePort.on("error", (err) => {
                console.error("❌ Error pada port:", err.message);
            });
        })
        .catch((err) => {
            console.error("❌ Gagal mendapatkan daftar port:", err);
        });
}

// WebSocket untuk menerima konfigurasi
wss.on("connection", (ws) => {
    console.log("🌐 Klien terhubung...");

    ws.on("message", (message) => {
        try {
            const msg = JSON.parse(message);

            if (msg.type === "config" && msg.payload) {
                console.log("⚙️ Konfigurasi diterima:", msg.payload);
                stopAllProcesses(() => {
                    initializeConnection(msg.payload);
                });
            }
        } catch (error) {
            console.error("❌ Gagal parsing pesan:", error);
        }
    });

    ws.on("close", () => {
        console.log("❌ Klien keluar.");
        if (wss.clients.size === 0) {
            stopAllProcesses();
        }
    });
});
