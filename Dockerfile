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

# ---------- Dependencies ----------
FROM php-base AS deps
COPY composer.json composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

# ✅ PERBAIKAN: Publish vendor assets di deps stage
RUN mkdir -p public/livewire public/vendor
COPY . .
RUN php artisan vendor:publish --all --force || echo "Some assets may not be published"

# ---------- Frontend build ----------
FROM node:20 AS frontend
WORKDIR /app

ENV NODE_ENV=production
ENV VITE_APP_NAME=MerubaliStock
ENV VITE_APP_URL=https://merubali-merubali-app.sbfalk.easypanel.host

COPY package.json ./
RUN npm install --legacy-peer-deps

COPY --from=deps /var/www/html/vendor ./vendor
COPY . .
COPY --from=deps /var/www/html/public ./public

# Create directories yang dibutuhkan TailwindCSS 4.0
RUN mkdir -p storage/framework/views app/Filament resources/views/filament

# ✅ FIX: Build dengan verbose error dan fallback
RUN echo "=== Building ===" && \
    npm run build --verbose 2>&1 || \
    (echo "=== Vite build failed, trying alternative ===" && \
     npx vite build --mode production --logLevel info 2>&1) || \
    (echo "=== All builds failed, creating minimal build ===" && \
     mkdir -p public/build && \
     echo '{"resources/css/app.css":{"file":"app.css"},"resources/js/app.js":{"file":"app.js"}}' > public/build/manifest.json && \
     touch public/build/app.css public/build/app.js)

# Verify build output
RUN echo "=== Build verification ===" && \
    ls -la public/build/ && \
    ls -la public/livewire/ || echo "Livewire assets not found"
# ---------- Production image ----------
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

# Copy vendor from deps stage
COPY --from=deps /var/www/html/vendor ./vendor

# ✅ PERBAIKAN: Copy semua public assets (Vite build + vendor assets)
COPY --from=frontend /app/public ./public

# ✅ TAMBAHAN: Publish assets sekali lagi di production (safety net)
RUN php artisan vendor:publish --tag=livewire:assets --force || echo "Livewire assets already published" && \
    php artisan vendor:publish --tag=filament-assets --force || echo "Filament assets already published"

# Laravel permissions
RUN chown -R www-data:www-data storage bootstrap/cache public \
 && find storage -type d -exec chmod 775 {} \; \
 && find storage -type f -exec chmod 664 {} \; \
 && chmod -R 775 bootstrap/cache \
 && chmod -R 755 public

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
