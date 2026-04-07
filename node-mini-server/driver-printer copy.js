const express = require("express");
const fs = require("fs");
const os = require("os");
const path = require("path");
const { exec } = require("child_process");
const app = express();
const PORT = 3000;

// Gunakan IP komputer secara eksplisit
const networkInterfaces = os.networkInterfaces();
let localIp = "127.0.0.1";

// Ambil IP LAN (misal 192.168.x.x)
for (const iface of Object.values(networkInterfaces)) {
    for (const config of iface) {
        if (
            config.family === "IPv4" &&
            !config.internal &&
            config.address.startsWith("192.168.")
        ) {
            localIp = config.address;
        }
    }
}

// Gunakan IP, bukan localhost
const PRINTER_NAME = "\\\\localhost\\TSC TE210";

app.use(express.json());

app.post("/print", (req, res) => {
    const { data } = req.body;

    if (!data) {
        return res.status(400).json({
            success: false,
            message: "Data TSPL tidak ditemukan.",
        });
    }

    const tempDir = os.tmpdir();
    const tempFile = path.join(tempDir, `label_${Date.now()}.txt`);

    fs.writeFile(tempFile, data, (err) => {
        if (err) {
            console.error("❌ Gagal menulis file sementara:", err);
            return res.status(500).json({
                success: false,
                message: "Gagal menulis file sementara.",
            });
        }

        exec(
            `print /D:"${PRINTER_NAME}" "${tempFile}"`,
            (error, stdout, stderr) => {
                fs.unlink(tempFile, () => {});

                if (error) {
                    console.error("❌ Gagal mencetak:", error.message);
                    return res.status(500).json({
                        success: false,
                        message: "Gagal mencetak label.",
                    });
                }

                if (stderr) console.warn("⚠️ STDERR:", stderr);

                console.log("✅ Sukses mencetak label");
                return res.json({
                    success: true,
                    message: "Berhasil mencetak label.",
                });
            }
        );
    });
});

// Ganti dari localhost ke 0.0.0.0 agar bisa diakses dari jaringan
app.listen(PORT, "0.0.0.0", () => {
    console.log(`✅ USB Printer Server aktif di http://${localIp}:${PORT}`);
});
