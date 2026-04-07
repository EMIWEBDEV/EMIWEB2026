# =================================================================
# Stage 1: Build front-end assets 
# =================================================================
FROM node:18-alpine AS node-builder
WORKDIR /app

# Salin file package untuk caching
COPY package*.json ./

# Install node dependencies
RUN npm ci

# Salin SEMUA file proyek. Ini memastikan semua file konfigurasi (vite, postcss, tailwind)
# dan source code (resources/js) tersedia untuk proses build.
COPY . .

# Jalankan build. Sekarang, build akan berjalan dengan semua file yang diperlukan.
RUN npm run build

# =================================================================
# Stage 2: PHP Runtime dengan Apache (Lingkungan Produksi)
# =================================================================
FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies & PHP extensions (Tidak ada perubahan)
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev gnupg2 \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip

# Install dependency dulu
RUN apt-get update \
    && apt-get install -y \
        wget \
        ca-certificates \
        fontconfig \
        libfreetype6 \
        libjpeg62-turbo \
        libpng16-16 \
        libx11-6 \
        libxcb1 \
        libxext6 \
        libxrender1 \
        xfonts-base \
        xfonts-75dpi \
    && wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && apt-get install -y ./wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && ln -sf /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf \
    && ln -sf /usr/local/bin/wkhtmltoimage /usr/bin/wkhtmltoimage


# Install Composer (Tidak ada perubahan)
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer


# Install Microsoft ODBC Driver & SQL Server extensions (Tidak ada perubahan)
RUN curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /etc/apt/keyrings/microsoft.gpg \
    && echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/11/prod bullseye main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 unixodbc-dev \
    && rm -rf /var/lib/apt/lists/* \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Set working directory
WORKDIR /var/www/html

# Salin file composer dan install dependencies tanpa menjalankan skrip
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader

# Salin semua file aplikasi
COPY . .

COPY --from=node-builder /app/public/build ./public/build

RUN cp .env.example .env \
    && php artisan key:generate --ansi \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# =================================================================
# Konfigurasi Final Server & Entrypoint
# =================================================================
# Salin konfigurasi PHP kustom
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Arahkan DocumentRoot Apache ke folder public Laravel dan aktifkan rewrite
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

# Biarkan CMD default dari base image php:apache yang akan berjalan
EXPOSE 80