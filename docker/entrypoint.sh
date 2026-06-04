#!/bin/sh
set -e

# ── Sesuaikan port Apache dengan Cloud Run ──────────────────────────────────
PORT="${PORT:-8080}"
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf

echo "[startup] Port: ${PORT}"

# ── Pastikan storage bisa ditulis ───────────────────────────────────────────
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
mkdir -p /var/www/html/storage/logs /var/www/html/storage/app/temp

echo "[startup] Memulai Apache..."
exec "$@"
