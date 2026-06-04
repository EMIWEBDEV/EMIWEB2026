const express = require("express");
const fs = require("fs");
const os = require("os");
const path = require("path");
const { exec } = require("child_process");

const app = express();
const PORT = 3000;

const getLocalIp = () => {
    const networkInterfaces = os.networkInterfaces();
    for (const iface of Object.values(networkInterfaces)) {
        for (const config of iface || []) {
            if (
                config.family === "IPv4" &&
                !config.internal &&
                config.address.startsWith("192.168.")
            ) {
                return config.address;
            }
        }
    }
    return "127.0.0.1";
};

const localIp = getLocalIp();
const PRINTER_NAME = "\\\\localhost\\TSC TE210";

app.use(express.json({ limit: "5mb" }));

app.get("/", (req, res) => {
    res.json({
        success: true,
        message: "Printer Server Running",
        ip: localIp,
        port: PORT,
    });
});

app.post("/print", (req, res) => {
    const { data, width, height, gap, direction } = req.body;

    if (!data) {
        return res
            .status(400)
            .json({ success: false, message: "Data konten kosong." });
    }

    const setWidth = width || 100;
    const setHeight = height || 50;
    const setGap = gap || 3;
    const setDirection = direction !== undefined ? direction : 1;

    const headerConfig = `
SIZE ${setWidth} mm, ${setHeight} mm
GAP ${setGap} mm, 0 mm
DIRECTION ${setDirection}
CLS
`;

    const finalTSPL = headerConfig + data + "\r\nPRINT 1\r\n";

    const tempDir = os.tmpdir();
    const tempFile = path.join(tempDir, `label_${Date.now()}.txt`);

    fs.writeFile(tempFile, finalTSPL, (err) => {
        if (err) {
            return res
                .status(500)
                .json({ success: false, message: "Server Error (Write)" });
        }

        exec(
            `print /D:"${PRINTER_NAME}" "${tempFile}"`,
            (error, stdout, stderr) => {
                fs.unlink(tempFile, () => {});

                if (error) {
                    return res
                        .status(500)
                        .json({ success: false, message: "Printer Error" });
                }

                return res.json({
                    success: true,
                    message: `Berhasil dicetak ${setWidth}x${setHeight}mm`,
                });
            }
        );
    });
});

app.listen(PORT, "0.0.0.0", () => {
    console.log(`Printer Server aktif di http://${localIp}:${PORT}`);
});
