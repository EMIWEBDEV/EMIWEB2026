const https = require("https");
const express = require("express");
const fs = require("fs");
const os = require("os");
const path = require("path");
const { exec } = require("child_process");
const cors = require("cors");

const app = express();
const PORT = 9778;

const sslOptions = {
    key: fs.readFileSync(path.join(__dirname, "key.pem")),
    cert: fs.readFileSync(path.join(__dirname, "cert.pem")),
};

app.use(cors());
app.use(express.json({ limit: "5mb" }));

const PRINTER_NAME = "\\\\localhost\\TSC TE210";

app.get("/", (req, res) => {
    res.json({
        success: true,
        message: "Printer Server Running (HTTPS)",
        port: PORT,
    });
});

app.post("/print", (req, res) => {
    const { data, width, height, gap, direction } = req.body;

    if (!data) {
        return res.status(400).json({
            success: false,
            message: "Data konten kosong.",
        });
    }

    const headerConfig = `SIZE ${width || 100} mm, ${height || 50} mm\r\nGAP ${gap || 3} mm, 0 mm\r\nDIRECTION ${direction ?? 1}\r\nREFERENCE 0,0\r\nOFFSET 0 mm\r\nCLS\r\n`;
    const finalTSPL = headerConfig + data + "\r\nPRINT 1\r\n";
    const tempFile = path.join(os.tmpdir(), `label_${Date.now()}.txt`);

    fs.writeFile(tempFile, finalTSPL, (err) => {
        if (err) {
            return res.status(500).json({ success: false, message: "Server Error (Write)" });
        }

        exec(`print /D:"${PRINTER_NAME}" "${tempFile}"`, (error) => {
            fs.unlink(tempFile, () => {});

            if (error) {
                return res.status(500).json({ success: false, message: "Printer Error: " + error.message });
            }

            return res.json({ success: true, message: "Berhasil dicetak" });
        });
    });
});

https.createServer(sslOptions, app).listen(PORT, "0.0.0.0", () => {
    console.log("==================================================");
    console.log("Printer Server HTTPS aktif");
    console.log("==================================================");
    console.log("Port   : " + PORT);
    console.log("URL    : https://localhost:" + PORT);
    console.log("==================================================");
});
