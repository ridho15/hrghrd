# ==============================================================================
# TAHAP 1: Build Aset Frontend (Node 22 & Vite)
# ==============================================================================
FROM node:22-alpine AS frontend-builder
WORKDIR /app

# Salin manifest dependensi npm
COPY package.json package-lock.json ./
RUN npm ci

# Salin sumber daya frontend & konfigurasi
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# ==============================================================================
# TAHAP 2: Runtime Aplikasi PHP 8.4 Apache
# ==============================================================================
FROM php:8.4-apache

# Pasang dependensi sistem & utilitas database
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        bcmath \
        intl \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Salin Composer binary resmi
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Konfigurasi Apache DocumentRoot ke folder public Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

WORKDIR /var/www/html

# Salin composer dependencies terlebih dahulu untuk caching layer Docker
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist

# Salin seluruh source code aplikasi
COPY . .

# Salin aset frontend hasil kompilasi dari tahap 1
COPY --from=frontend-builder /app/public/build ./public/build

# Optimasi classmap autoloader
RUN composer dump-autoload --optimize

# Atur kepemilikan dan hak akses direktori storage & bootstrap cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Konfigurasi script entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
