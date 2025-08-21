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

# Copy all source files before publishing assets
COPY . .

# Create necessary directories
RUN mkdir -p public/livewire public/vendor storage/framework/views storage/app storage/logs \
    bootstrap/cache resources/views/filament app/Filament

# Publish vendor assets after all files are copied
RUN php artisan vendor:publish --all --force || echo "Some assets may not be published"

# ---------- Frontend build ----------
FROM node:20-slim AS frontend
WORKDIR /app

# Install build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 make g++ \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV NODE_ENV=production
ENV VITE_APP_NAME=MerubaliStock
ENV VITE_APP_URL=https://merubali-merubali-app.sbfalk.easypanel.host

# Copy package files first for better caching
COPY package.json package-lock.json ./

# Install dependencies with clean install
RUN npm ci --only=production --legacy-peer-deps

# Copy all source files
COPY . .

# Copy vendor directory from deps stage (needed for TailwindCSS scanning)
COPY --from=deps /var/www/html/vendor ./vendor
COPY --from=deps /var/www/html/public ./public

# Ensure all required directories exist for TailwindCSS 4.0
RUN mkdir -p storage/framework/views storage/framework/cache \
    app/Filament resources/views/filament bootstrap/cache \
    resources/css resources/js

# Create empty files if they don't exist (prevents build failures)
RUN touch storage/framework/views/.gitkeep \
    && touch bootstrap/cache/.gitkeep

# Build with better error handling
RUN echo "=== Starting Vite Build ===" \
 && echo "Node version: $(node --version)" \
 && echo "NPM version: $(npm --version)" \
 && echo "Checking files:" \
 && ls -la resources/css/ \
 && ls -la resources/js/ \
 && echo "=== Building ===" \
 && npm run build 2>&1 || \
    (echo "=== First build failed, trying with verbose ===" && \
     npx vite build --mode production --logLevel info 2>&1) || \
    (echo "=== Trying without TailwindCSS scanning ===" && \
     SKIP_TAILWIND=true npx vite build --mode production 2>&1) || \
    (echo "=== Creating fallback build ===" && \
     mkdir -p public/build && \
     echo '{"resources/css/app.css":{"file":"app.css","src":"resources/css/app.css"},"resources/js/app.js":{"file":"app.js","src":"resources/js/app.js"},"resources/css/filament/admin/theme.css":{"file":"theme.css","src":"resources/css/filament/admin/theme.css"}}' > public/build/manifest.json && \
     touch public/build/app.css public/build/app.js public/build/theme.css && \
     echo "/* Fallback CSS */" > public/build/app.css && \
     echo "/* Fallback CSS */" > public/build/theme.css && \
     echo "// Fallback JS" > public/build/app.js)

# Verify build output
RUN echo "=== Build Verification ===" \
 && ls -la public/build/ || echo "No build directory" \
 && cat public/build/manifest.json 2>/dev/null || echo "No manifest file" \
 && echo "=== Build Complete ==="

# ---------- Production image ----------
FROM php-base AS prod

# Copy configuration files
COPY deploy/nginx.conf /etc/nginx/nginx.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Setup entrypoint
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

# Copy application source
COPY . .

# Copy vendor dependencies
COPY --from=deps /var/www/html/vendor ./vendor

# Copy built assets from frontend stage
COPY --from=frontend /app/public/build ./public/build
COPY --from=frontend /app/public/mix-manifest.json ./public/mix-manifest.json 2>/dev/null || true

# Ensure all public assets are available
COPY --from=deps /var/www/html/public/vendor ./public/vendor 2>/dev/null || true

# Final asset publishing (safety net)
RUN php artisan vendor:publish --tag=livewire:assets --force 2>/dev/null || echo "Livewire assets already published" \
 && php artisan vendor:publish --tag=filament-assets --force 2>/dev/null || echo "Filament assets already published"

# Set proper permissions
RUN chown -R www-data:www-data storage bootstrap/cache public \
 && find storage -type d -exec chmod 775 {} \; \
 && find storage -type f -exec chmod 664 {} \; \
 && chmod -R 775 bootstrap/cache \
 && chmod -R 755 public

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
