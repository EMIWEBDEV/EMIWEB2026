var Service = require('node-windows').Service;
var path = require('path');

console.log('🚀 Memulai Instalasi Semua Service...');

// ==========================================
// 1. KONFIGURASI SERVICE TIMBANGAN
// ==========================================
var svcTimbangan = new Service({
  name: 'Service Driver Timbangan Wss Cloud',
  description: 'WebSocket Server Timbangan (Port 6001)',
  script: path.join(__dirname, 'driver-timbangan-cloud.js'), // Pastikan nama file benar
  nodeOptions: [
    '--harmony',
    '--max_old_space_size=4096'
  ]
});

// ==========================================
// 2. KONFIGURASI SERVICE PRINTER
// ==========================================
var svcPrinter = new Service({
  name: 'Service Driver Printer Tsc Cloud',
  description: 'Driver Printer Thermal TSC (Port 6002)',
  script: path.join(__dirname, 'driver-printer-cloud.js'), // Pastikan nama file benar
  nodeOptions: [
    '--harmony',
    '--max_old_space_size=4096'
  ]
});

// ==========================================
// 3. LOGIC EKSEKUSI BERURUTAN (CHAINING)
// ==========================================

// Event A: Kalau Timbangan selesai install -> Start Timbangan -> Lanjut Install Printer
svcTimbangan.on('install', function(){
  console.log('✅ Service TIMBANGAN Berhasil Diinstall!');
  svcTimbangan.start();

  console.log('⏳ Sedang menginstall Service Printer...');
  svcPrinter.install(); // Pemicu install printer
});

// Event B: Kalau Printer selesai install -> Start Printer -> Selesai
svcPrinter.on('install', function(){
  console.log('✅ Service PRINTER Berhasil Diinstall!');
  svcPrinter.start();
  
  console.log('🎉 SEMUA SERVICE SUDAH JALAN!');
});

// Event C: Kalau sudah ada (Error Already Exists), lanjut aja
svcTimbangan.on('alreadyinstalled', function(){
  console.log('⚠️ Service Timbangan sudah ada, lanjut ke Printer...');
  svcPrinter.install();
});

svcPrinter.on('alreadyinstalled', function(){
  console.log('⚠️ Service Printer sudah ada.');
  console.log('🎉 SEMUA SELESAI.');
});

// ==========================================
// 4. PEMICU UTAMA
// ==========================================
console.log('⏳ Sedang menginstall Service Timbangan...');
svcTimbangan.install();