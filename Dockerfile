# ---------- Base PHP with extensions ----------
FROM php:8.3-fpm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
    git curl unzip zip libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libzip-dev libicu-dev libpq-dev libxml2-dev \
    supervisor nginx cron \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    bcmath gd intl pdo_mysql pdo_pgsql zip opcache \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------- PHP Dependencies ----------
FROM php-base AS php-deps
COPY composer.json composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ---------- Frontend build ----------
FROM node:22-alpine AS frontend-build
WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci

# Copy semua file yang dibutuhkan untuk build
COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.js ./

# >>> PENTING: Copy vendor dari php-deps agar Vite bisa resolve import Filament
COPY --from=php-deps /var/www/html/vendor ./vendor

# Build assets
RUN npm run build

# ---------- Production ----------
FROM php-base AS prod

# Nginx config
COPY deploy/nginx.conf /etc/nginx/nginx.conf

# Supervisor config
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

# Copy app source
COPY . .

# Copy vendor dari php-deps stage
COPY --from=php-deps /var/www/html/vendor ./vendor

# Copy built assets dari frontend-build stage
COPY --from=frontend-build /app/public/build ./public/build

# Setup Laravel (cukup package discovery)
RUN composer dump-autoload --optimize && \
    php artisan package:discover --ansi

# Laravel permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
 && find storage -type d -exec chmod 775 {} \; \
 && find storage -type f -exec chmod 664 {} \; \
 && chmod -R 775 bootstrap/cache

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
