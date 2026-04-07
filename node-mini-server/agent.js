// // const { SerialPort } = require("serialport");
// // const { ReadlineParser } = require("@serialport/parser-readline");
// // const { WebSocketServer } = require("ws");

// // const wss = new WebSocketServer({ port: 6001 });
// // console.log("✅ WebSocket Server aktif di ws://localhost:6001");

// // let activePort = null;
// // let isSimulasiAktif = false;
// // let simulasiInterval = null;
// // let beratSimulasi = 1.87;

// // /**
// //  * Kirim data berat ke semua klien yang aktif
// //  */
// // function broadcastBerat(berat) {
// //     wss.clients.forEach((client) => {
// //         if (client.readyState === 1) {
// //             client.send(berat);
// //         }
// //     });
// // }

// // /**
// //  * Menutup koneksi port serial jika masih terbuka
// //  */
// // function stopAllProcesses(callback) {
// //     if (activePort && activePort.isOpen) {
// //         activePort.close(() => {
// //             activePort = null;
// //             console.log("[!] Port serial ditutup.");
// //             if (callback) callback();
// //         });
// //     } else {
// //         if (callback) callback();
// //     }

// //     // Hentikan simulasi jika aktif
// //     if (isSimulasiAktif) {
// //         clearInterval(simulasiInterval);
// //         simulasiInterval = null;
// //         isSimulasiAktif = false;
// //         console.log("🛑 Simulasi dimatikan.");
// //     }
// // }

// // /**
// //  * Mulai mode simulasi jika tidak ada port serial
// //  */
// // function startSimulasiBerat() {
// //     isSimulasiAktif = true;
// //     console.log("⚠️ Tidak ada timbangan. Menjalankan mode simulasi...");

// //     simulasiInterval = setInterval(() => {
// //         // Ubah-ubah berat sedikit supaya dinamis
// //         beratSimulasi += 0.01;
// //         if (beratSimulasi > 1.95) beratSimulasi = 1.87;

// //         const dataToSend = JSON.stringify({
// //             berat: beratSimulasi.toFixed(3),
// //             status: "ST",
// //         });

// //         broadcastBerat(dataToSend);
// //         console.log("📤 Simulasi berat terkirim:", dataToSend);
// //     }, 2000); // Kirim tiap 2 detik
// // }

// // /**
// //  * Normalisasi konfigurasi dari client
// //  */
// // function normalizeConfig(raw) {
// //     return {
// //         BAUD_RATE: parseInt(raw.BAUD_RATE),
// //         DATA_BITS: parseInt(raw.BIT),
// //         PARITY: String(raw.Parity).toLowerCase(),
// //         STOP_BITS: parseInt(raw.Stop_Bits),
// //     };
// // }

// // /**
// //  * Inisialisasi koneksi ke port serial yang terdeteksi otomatis
// //  */
// // function initializeConnection(config) {
// //     const requiredFields = ["BAUD_RATE", "DATA_BITS", "PARITY", "STOP_BITS"];
// //     const missing = requiredFields.filter(
// //         (key) => config[key] === undefined || config[key] === null
// //     );

// //     if (missing.length > 0) {
// //         console.warn(
// //             `❌ Konfigurasi tidak lengkap. Field berikut wajib: ${missing.join(
// //                 ", "
// //             )}`
// //         );
// //         return;
// //     }

// //     const { BAUD_RATE, DATA_BITS, PARITY, STOP_BITS } = config;

// //     SerialPort.list()
// //         .then((ports) => {
// //             if (ports.length === 0) {
// //                 console.warn("[!] Tidak ada port serial terdeteksi.");
// //                 startSimulasiBerat(); // ⬅️ Ganti jadi simulasi
// //                 return;
// //             }

// //             console.log("📋 Port yang terdeteksi:");
// //             ports.forEach((p, i) => {
// //                 console.log(
// //                     `${i + 1}. ${p.path} - ${p.manufacturer || "Unknown"}`
// //                 );
// //             });

// //             const targetPortInfo = ports[0]; // Ambil port pertama

// //             console.log(
// //                 `🔌 Menghubungkan ke ${targetPortInfo.path} @ ${BAUD_RATE}bps...`
// //             );

// //             const configUsed = {
// //                 path: targetPortInfo.path,
// //                 baudRate: BAUD_RATE,
// //                 dataBits: DATA_BITS,
// //                 parity: PARITY,
// //                 stopBits: STOP_BITS,
// //                 autoOpen: true,
// //             };

// //             console.log("🛠️ Konfigurasi serial yang digunakan:");
// //             console.table(configUsed);

// //             activePort = new SerialPort(configUsed);

// //             const parser = activePort.pipe(
// //                 new ReadlineParser({ delimiter: "\r\n" })
// //             );

// //             parser.on("data", (line) => {
// //                 console.log("📥 Data diterima:", JSON.stringify(line));

// //                 const match = line.match(/[-+]?[0-9]*\.?[0-9]+/);
// //                 let berat = match ? parseFloat(match[0]) : null;
// //                 const isStabil = line.includes("ST");

// //                 if (!berat || isNaN(berat) || berat === 0) {
// //                     berat = 1.87;
// //                 }

// //                 const dataToSend = JSON.stringify({
// //                     berat: berat.toFixed(3),
// //                     status: isStabil || berat === 1.87 ? "ST" : "US",
// //                 });

// //                 broadcastBerat(dataToSend);
// //             });

// //             activePort.on("error", (err) => {
// //                 console.error("❌ Error pada port:", err.message);
// //             });
// //         })
// //         .catch((err) => {
// //             console.error("❌ Gagal membaca daftar port:", err);
// //             startSimulasiBerat(); // ⬅️ Jika gagal list, langsung simulasi
// //         });
// // }

// // /**
// //  * WebSocket server menerima konfigurasi dari client
// //  */
// // wss.on("connection", (ws) => {
// //     console.log("🌐 Klien WebSocket terhubung.");

// //     ws.on("message", (message) => {
// //         try {
// //             const msg = JSON.parse(message);

// //             if (msg.type === "config" && msg.payload) {
// //                 const config = normalizeConfig(msg.payload);
// //                 console.log("⚙️ Konfigurasi dari client:");
// //                 console.table(config);

// //                 stopAllProcesses(() => {
// //                     initializeConnection(config);
// //                 });
// //             }
// //         } catch (error) {
// //             console.error("❌ Gagal parsing pesan:", error);
// //         }
// //     });

// //     ws.on("close", () => {
// //         console.log("❌ Klien WebSocket terputus.");
// //         if (wss.clients.size === 0) {
// //             stopAllProcesses();
// //         }
// //     });
// // });


// const { SerialPort } = require("serialport");
// const { ReadlineParser } = require("@serialport/parser-readline");
// const { WebSocketServer } = require("ws");

// const wss = new WebSocketServer({ port: 6001 });
// console.log("✅ WebSocket Server aktif di ws://localhost:6001");

// let activePort = null;

// /**
//  * Kirim data berat ke semua klien yang aktif
//  */
// function broadcastBerat(berat) {
//     wss.clients.forEach((client) => {
//         if (client.readyState === 1) {
//             client.send(berat);
//         }
//     });
// }

// /**
//  * Menutup koneksi port serial jika masih terbuka
//  */
// function stopAllProcesses(callback) {
//     if (activePort && activePort.isOpen) {
//         activePort.close(() => {
//             activePort = null;
//             console.log("[!] Port serial ditutup.");
//             if (callback) callback();
//         });
//     } else {
//         if (callback) callback();
//     }
// }

// /**
//  * Normalisasi konfigurasi dari client
//  */
// function normalizeConfig(raw) {
//     return {
//         BAUD_RATE: parseInt(raw.BAUD_RATE),
//         DATA_BITS: parseInt(raw.BIT),
//         PARITY: String(raw.Parity).toLowerCase(),
//         STOP_BITS: parseInt(raw.Stop_Bits),
//     };
// }

// /**
//  * Inisialisasi koneksi ke port serial yang terdeteksi otomatis
//  */
// function initializeConnection(config) {
//     const requiredFields = ["BAUD_RATE", "DATA_BITS", "PARITY", "STOP_BITS"];
//     const missing = requiredFields.filter(
//         (key) => config[key] === undefined || config[key] === null
//     );

//     if (missing.length > 0) {
//         console.warn(
//             `❌ Konfigurasi tidak lengkap. Field berikut wajib: ${missing.join(
//                 ", "
//             )}`
//         );
//         return;
//     }

//     const { BAUD_RATE, DATA_BITS, PARITY, STOP_BITS } = config;

//     SerialPort.list()
//         .then((ports) => {
//             if (ports.length === 0) {
//                 console.warn("[!] Tidak ada port serial terdeteksi.");
//                 return;
//             }

//             console.log("📋 Port yang terdeteksi:");
//             ports.forEach((p, i) => {
//                 console.log(
//                     `${i + 1}. ${p.path} - ${p.manufacturer || "Unknown"}`
//                 );
//             });

//             const targetPortInfo = ports[0]; // Ambil port pertama

//             console.log(
//                 `🔌 Menghubungkan ke ${targetPortInfo.path} @ ${BAUD_RATE}bps...`
//             );

//             const configUsed = {
//                 path: targetPortInfo.path,
//                 baudRate: BAUD_RATE,
//                 dataBits: DATA_BITS,
//                 parity: PARITY,
//                 stopBits: STOP_BITS,
//                 autoOpen: true,
//             };

//             console.log("🛠️ Konfigurasi serial yang digunakan:");
//             console.table(configUsed);

//             activePort = new SerialPort(configUsed);

//             const parser = activePort.pipe(
//                 new ReadlineParser({ delimiter: "\r\n" })
//             );

//             parser.on("data", (line) => {
//                 console.log("📥 Data diterima:", JSON.stringify(line));

//                 const match = line.match(/[-+]?[0-9]*\.?[0-9]+/);
//                 let berat = match ? parseFloat(match[0]) : null;
//                 const isStabil = line.includes("ST");

//                 if (!berat || isNaN(berat)) return;

//                 const dataToSend = JSON.stringify({
//                     berat: berat.toFixed(3),
//                     status: isStabil ? "ST" : "US",
//                 });

//                 broadcastBerat(dataToSend);
//             });

//             activePort.on("error", (err) => {
//                 console.error("❌ Error pada port:", err.message);
//             });
//         })
//         .catch((err) => {
//             console.error("❌ Gagal membaca daftar port:", err);
//         });
// }

// /**
//  * WebSocket server menerima konfigurasi dari client
//  */
// wss.on("connection", (ws) => {
//     console.log("🌐 Klien WebSocket terhubung.");

//     ws.on("message", (message) => {
//         try {
//             const msg = JSON.parse(message);

//             if (msg.type === "config" && msg.payload) {
//                 const config = normalizeConfig(msg.payload);
//                 console.log("⚙️ Konfigurasi dari client:");
//                 console.table(config);

//                 stopAllProcesses(() => {
//                     initializeConnection(config);
//                 });
//             }
//         } catch (error) {
//             console.error("❌ Gagal parsing pesan:", error);
//         }
//     });

//     ws.on("close", () => {
//         console.log("❌ Klien WebSocket terputus.");
//         if (wss.clients.size === 0) {
//             stopAllProcesses();
//         }
//     });
// });


const { SerialPort } = require("serialport");
const { ReadlineParser } = require("@serialport/parser-readline");
const { WebSocketServer } = require("ws");

const wss = new WebSocketServer({ port: 6001 });
console.log("✅ WebSocket Server aktif di ws://localhost:6001");

let activePort = null;

/**
 * Kirim data berat ke semua klien yang aktif
 */
function broadcastBerat(berat) {
    wss.clients.forEach((client) => {
        if (client.readyState === 1) {
            client.send(berat);
        }
    });
}

/**
 * Menutup koneksi port serial jika masih terbuka
 */
function stopAllProcesses(callback) {
    if (activePort && activePort.isOpen) {
        activePort.close(() => {
            activePort = null;
            console.log("[!] Port serial ditutup.");
            if (callback) callback();
        });
    } else {
        if (callback) callback();
    }
}

/**
 * Normalisasi konfigurasi dari client
 */
function normalizeConfig(raw) {
    return {
        BAUD_RATE: parseInt(raw.BAUD_RATE),
        DATA_BITS: parseInt(raw.BIT),
        PARITY: String(raw.Parity).toLowerCase(),
        STOP_BITS: parseInt(raw.Stop_Bits),
    };
}

/**
 * Inisialisasi koneksi ke port serial yang terdeteksi otomatis
 */
function initializeConnection(config) {
    const requiredFields = ["BAUD_RATE", "DATA_BITS", "PARITY", "STOP_BITS"];
    const missing = requiredFields.filter(
        (key) => config[key] === undefined || config[key] === null
    );

    if (missing.length > 0) {
        console.warn(
            `❌ Konfigurasi tidak lengkap. Field berikut wajib: ${missing.join(
                ", "
            )}`
        );
        return;
    }

    const { BAUD_RATE, DATA_BITS, PARITY, STOP_BITS } = config;

    SerialPort.list()
        .then((ports) => {
            if (ports.length === 0) {
                console.warn("[!] Tidak ada port serial terdeteksi.");
                return;
            }

            console.log("📋 Port yang terdeteksi:");
            ports.forEach((p, i) => {
                console.log(
                    `${i + 1}. ${p.path} - ${p.manufacturer || "Unknown"}`
                );
            });

            const targetPortInfo = ports[0]; // Ambil port pertama

            console.log(
                `🔌 Menghubungkan ke ${targetPortInfo.path} @ ${BAUD_RATE}bps...`
            );

            const configUsed = {
                path: targetPortInfo.path,
                baudRate: BAUD_RATE,
                dataBits: DATA_BITS,
                parity: PARITY,
                stopBits: STOP_BITS,
                autoOpen: true,
            };

            console.log("🛠️ Konfigurasi serial yang digunakan:");
            console.table(configUsed);

            activePort = new SerialPort(configUsed);

            const parser = activePort.pipe(
                new ReadlineParser({ delimiter: "\r\n" })
            );

            parser.on("data", (line) => {
                console.log("📥 Data diterima:", JSON.stringify(line));

                const match = line.match(/[-+]?[0-9]*\.?[0-9]+/);
                let berat = match ? parseFloat(match[0]) : null;
                const isStabil = line.includes("ST");

                if (!berat || isNaN(berat)) return;

                const dataToSend = JSON.stringify({
                    berat: berat.toFixed(3),
                    status: isStabil ? "ST" : "US",
                });

                broadcastBerat(dataToSend);
            });

            activePort.on("error", (err) => {
                console.error("❌ Error pada port:", err.message);
            });
        })
        .catch((err) => {
            console.error("❌ Gagal membaca daftar port:", err);
        });
}

/**
 * WebSocket server menerima konfigurasi dari client
 */
wss.on("connection", (ws) => {
    console.log("🌐 Klien WebSocket terhubung.");

    ws.on("message", (message) => {
        try {
            const msg = JSON.parse(message);

            if (msg.type === "config" && msg.payload) {
                const config = normalizeConfig(msg.payload);
                console.log("⚙️ Konfigurasi dari client:");
                console.table(config);

                stopAllProcesses(() => {
                    initializeConnection(config);
                });
            }
        } catch (error) {
            console.error("❌ Gagal parsing pesan:", error);
        }
    });

    ws.on("close", () => {
        console.log("❌ Klien WebSocket terputus.");
        if (wss.clients.size === 0) {
            stopAllProcesses();
        }
    });
});
