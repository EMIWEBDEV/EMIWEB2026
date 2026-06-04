# ============================================================
# SETUP DRIVER EMI LAB - Jalankan sebagai Administrator
# ============================================================

$ErrorActionPreference = "Stop"
$driverDir = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  SETUP DRIVER EMI LAB" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Folder: $driverDir" -ForegroundColor Gray
Write-Host ""

# ---- Cek Admin ----
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "[ERROR] Script ini harus dijalankan sebagai Administrator!" -ForegroundColor Red
    Write-Host "        Klik kanan PowerShell -> Run as Administrator" -ForegroundColor Yellow
    pause
    exit 1
}

# ---- Cek Node.js ----
Write-Host "[1/6] Mengecek Node.js..." -ForegroundColor Yellow
try {
    $nodeVersion = node --version
    Write-Host "      Node.js ditemukan: $nodeVersion" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Node.js tidak ditemukan!" -ForegroundColor Red
    Write-Host "        Download dan install dari: https://nodejs.org" -ForegroundColor Yellow
    pause
    exit 1
}

# ---- Cek mkcert ----
Write-Host "[2/6] Mengecek mkcert..." -ForegroundColor Yellow
$mkcertFound = $false
try {
    mkcert --version | Out-Null
    $mkcertFound = $true
    Write-Host "      mkcert ditemukan." -ForegroundColor Green
} catch {
    Write-Host "      mkcert tidak ditemukan, mencoba install via winget..." -ForegroundColor Yellow
    try {
        winget install FiloSottile.mkcert --silent
        $mkcertFound = $true
        Write-Host "      mkcert berhasil diinstall." -ForegroundColor Green
    } catch {
        Write-Host "[ERROR] Gagal install mkcert otomatis." -ForegroundColor Red
        Write-Host "        Download manual dari: https://github.com/FiloSottile/mkcert/releases" -ForegroundColor Yellow
        Write-Host "        Letakkan mkcert.exe di folder ini atau tambahkan ke PATH" -ForegroundColor Yellow
        pause
        exit 1
    }
}

# ---- Install mkcert CA ke sistem ----
Write-Host "[3/6] Menginstall mkcert CA ke sistem (agar browser mempercayai cert)..." -ForegroundColor Yellow
Set-Location $driverDir
mkcert -install
Write-Host "      CA berhasil diinstall." -ForegroundColor Green

# ---- Generate cert.pem dan key.pem ----
Write-Host "[4/6] Membuat SSL Certificate untuk localhost..." -ForegroundColor Yellow
mkcert -key-file key.pem -cert-file cert.pem localhost 127.0.0.1 ::1
Write-Host "      cert.pem dan key.pem berhasil dibuat." -ForegroundColor Green

# ---- npm install ----
Write-Host "[5/6] Menginstall Node.js packages (npm install)..." -ForegroundColor Yellow
Set-Location $driverDir
npm install
Write-Host "      Packages berhasil diinstall." -ForegroundColor Green

# ---- Stop service lama jika ada ----
Write-Host "[6/6] Uninstall service lama dan install ulang..." -ForegroundColor Yellow

$services = @("Service Driver Timbangan Wss Cloud", "Service Driver Printer Tsc Cloud")
foreach ($svc in $services) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($s) {
        Write-Host "      Menghentikan service: $svc" -ForegroundColor Gray
        Stop-Service -Name $svc -Force -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 2
    }
}

# Uninstall service lama
node uninstall-service.js
Start-Sleep -Seconds 5

# Install service baru
Write-Host "      Menginstall service baru..." -ForegroundColor Gray
node install-service-cloud.js
Start-Sleep -Seconds 5

# ---- Verifikasi ----
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  VERIFIKASI" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

foreach ($svc in $services) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($s) {
        $status = $s.Status
        $color = if ($status -eq "Running") { "Green" } else { "Red" }
        Write-Host "  $svc : $status" -ForegroundColor $color
    } else {
        Write-Host "  $svc : TIDAK DITEMUKAN" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "  Setelah selesai, buka browser dan cek:" -ForegroundColor White
Write-Host "  -> https://localhost:9778  (Printer)" -ForegroundColor Cyan
Write-Host "  -> https://localhost:9956  (Timbangan)" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Jika browser menampilkan peringatan keamanan," -ForegroundColor Yellow
Write-Host "  klik 'Advanced' -> 'Proceed to localhost'" -ForegroundColor Yellow
Write-Host "  lalu refresh halaman aplikasi." -ForegroundColor Yellow
Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  SETUP SELESAI!" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host ""
pause
