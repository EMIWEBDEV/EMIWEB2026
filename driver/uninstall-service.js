var Service = require('node-windows').Service;
var path = require('path');

console.log('Memulai proses uninstall...');

var svcTimbangan = new Service({
  name: 'Service Driver Timbangan Wss Cloud',
  script: path.join(__dirname, 'driver-timbangan-cloud.js')
});

var svcPrinter = new Service({
  name: 'Service Driver Printer Tsc Cloud',
  script: path.join(__dirname, 'driver-printer-cloud.js')
});

svcTimbangan.on('uninstall', function () {
  console.log('Service Timbangan berhasil dihapus.');
  svcPrinter.uninstall();
});

svcTimbangan.on('invalidinstallation', function () {
  console.log('Service Timbangan tidak ditemukan, lanjut hapus Printer...');
  svcPrinter.uninstall();
});

svcPrinter.on('uninstall', function () {
  console.log('Service Printer berhasil dihapus.');
  console.log('Semua service sudah dihapus.');
});

svcPrinter.on('invalidinstallation', function () {
  console.log('Service Printer tidak ditemukan.');
  console.log('Selesai.');
});

svcTimbangan.uninstall();
