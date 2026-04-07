FROM node:18-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
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

RUN wget https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && apt-get update \
    && apt-get install -y ./wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && rm wkhtmltox_0.12.6.1-3.bookworm_amd64.deb \
    && ln -sf /usr/local/bin/wkhtmltopdf /usr/bin/wkhtmltopdf \
    && ln -sf /usr/local/bin/wkhtmltoimage /usr/bin/wkhtmltoimage \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /etc/apt/keyrings/microsoft.gpg \
    && echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18 \
    && pecl install sqlsrv-5.11.1 pdo_sqlsrv-5.11.1 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && apt-get purge -y --auto-remove build-essential autoconf pkg-config \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --optimize-autoloader

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

COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

EXPOSE 80