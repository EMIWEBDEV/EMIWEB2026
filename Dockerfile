FROM node:18-alpine AS node-builder
WORKDIR /app
COPY package*.json ./

# npm registry sesekali gagal; beri retry agar build tidak jatuh karenanya.
RUN npm config set fetch-retries 5 \
    && npm config set fetch-retry-maxtimeout 120000 \
    && npm ci --no-audit --no-fund

COPY . .
RUN npm run build

FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    git curl wget unzip zip \
    libpng-dev libonig-dev libxml2-dev libzip-dev \
    gnupg2 ca-certificates apt-transport-https \
    fontconfig libfreetype6 libjpeg62-turbo libpng16-16 \
    libx11-6 libxcb1 libxext6 libxrender1 \
    xfonts-base xfonts-75dpi \
    build-essential autoconf pkg-config unixodbc-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd xml zip \
    && rm -rf /var/lib/apt/lists/*

# wkhtmltopdf diunduh dari GitHub Releases yang sesekali membalas 5xx.
# Tanpa --retry-on-http-error, wget langsung menyerah pada error 500 dan
# seluruh build ikut gagal padahal berkasnya baik-baik saja.
ARG WKHTMLTOPDF_URL=https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb

RUN wget --tries=5 --waitretry=15 --timeout=60 --retry-connrefused \
        --retry-on-http-error=408,429,500,502,503,504 \
        -O /tmp/wkhtmltox.deb "${WKHTMLTOPDF_URL}" \
    && apt-get update \
    && apt-get install -y /tmp/wkhtmltox.deb \
    && rm /tmp/wkhtmltox.deb \
    && ln -sf /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf \
    && ln -sf /usr/local/bin/wkhtmltoimage /usr/bin/wkhtmltoimage \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL --retry 5 --retry-delay 5 --retry-all-errors --connect-timeout 30 \
        https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php

RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSL --retry 5 --retry-delay 5 --retry-all-errors --connect-timeout 30 \
        https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /etc/apt/keyrings/microsoft.gpg \
    && echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 \
    && pecl install sqlsrv-5.11.1 pdo_sqlsrv-5.11.1 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && apt-get purge -y --auto-remove build-essential autoconf pkg-config \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY composer.json composer.lock ./

# COMPOSER_MAX_PARALLEL_HTTP diturunkan agar unduhan paket tidak mudah
# kena rate-limit, dan seluruh perintah diulang bila jaringan bermasalah.
RUN COMPOSER_MAX_PARALLEL_HTTP=6 \
    sh -c 'for i in 1 2 3; do \
        composer install --no-interaction --no-scripts --optimize-autoloader --prefer-dist && exit 0; \
        echo "[retry] composer install gagal (percobaan $i), ulangi dalam 15 detik..."; \
        sleep 15; \
    done; exit 1'

COPY . .
COPY --from=node-builder /app/public/build ./public/build

RUN cp .env.example .env \
    && php artisan key:generate --ansi \
    && php artisan storage:link \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/php.ini        /usr/local/etc/php/conf.d/custom.ini
COPY docker/entrypoint.sh  /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

EXPOSE 8080

# entrypoint.sh: clear cache lama → rebuild cache dgn env asli → start Apache
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]