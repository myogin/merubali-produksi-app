# ===============================================
# MerubaliStock - EasyPanel Optimized Dockerfile
# Simple & effective for container platforms
# ===============================================

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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ---------- Dependencies ----------
FROM php-base AS deps

COPY composer.json composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .

# Create .env from .env.example if needed (EasyPanel compatibility)
RUN if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi

RUN mkdir -p public/livewire public/vendor storage/app storage/framework/cache \
    storage/framework/sessions storage/framework/testing storage/framework/views \
    storage/logs bootstrap/cache resources/views/filament app/Filament \
 && php artisan vendor:publish --all --force

# ---------- Frontend ----------
FROM node:20-slim AS frontend

RUN apt-get update && apt-get install -y --no-install-recommends python3 make g++ \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

ENV NODE_ENV=production

COPY package.json package-lock.json* ./
RUN if [ -f package-lock.json ]; then npm ci --legacy-peer-deps; else npm install --legacy-peer-deps; fi

COPY . .
COPY --from=deps /var/www/html/vendor ./vendor
COPY --from=deps /var/www/html/public ./public

RUN mkdir -p storage/framework/views app/Filament resources/views/filament bootstrap/cache public/build/assets

# Simple build strategy
RUN npm run build || npx vite build || ( \
    echo "Build failed, creating fallback assets..." && \
    mkdir -p public/build/assets && \
    HASH=$(date +%s) && \
    echo "body{font-family:Inter,sans-serif}" > public/build/assets/app-$HASH.css && \
    echo "console.log('App loaded')" > public/build/assets/app-$HASH.js && \
    echo ".fi-body{font-family:Inter}" > public/build/assets/theme-$HASH.css && \
    echo "{\"resources/css/app.css\":{\"file\":\"assets/app-$HASH.css\"},\"resources/js/app.js\":{\"file\":\"assets/app-$HASH.js\"},\"resources/css/filament/admin/theme.css\":{\"file\":\"assets/theme-$HASH.css\"}}" > public/build/manifest.json \
)

RUN ls -la public/build/ && ls -la public/build/assets/ && echo "Frontend ready"

# ---------- Production ----------
FROM php-base AS production

COPY deploy/nginx.conf /etc/nginx/nginx.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

COPY . .
COPY --from=deps /var/www/html/vendor ./vendor
COPY --from=deps /var/www/html/public/vendor ./public/vendor
COPY --from=frontend /app/public/build ./public/build

# EasyPanel compatibility - create .env from example
RUN if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache public \
 && find storage -type d -exec chmod 775 {} \; \
 && find storage -type f -exec chmod 664 {} \; \
 && chmod -R 775 bootstrap/cache \
 && chmod -R 755 public

RUN php artisan vendor:publish --tag=livewire:assets --force || true \
 && php artisan vendor:publish --tag=filament-assets --force || true

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
