module.exports = {
  apps: [
    {
      name: "driver-printer",
      script: "driver-printer.js",
      autorestart: true,
      restart_delay: 5000,
      max_restarts: 0
    },
    {
      name: "driver-timbangan",
      script: "driver-timbangan.js",
      autorestart: true,
      restart_delay: 5000,
      max_restarts: 0
    }
  ]
};
