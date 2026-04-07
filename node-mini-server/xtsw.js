const express = require("express");
const app = express();

const PORT = 4001;

app.get("/", (req, res) => {
    res.json({
        status: "success",
        message: "Server Node.js jalan di port 4001",
        port: PORT,
        timestamp: new Date(),
    });
});

app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
});
