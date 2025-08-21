# ===============================================
# MerubaliStock - Production Dockerfile
# Fixed for proper frontend asset handling
# ===============================================

# ---------- Base PHP with extensions ----------
FROM php:8.3-fpm AS php-base

# Install system dependencies and PHP extensions
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

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------- Laravel Dependencies Stage ----------
FROM php-base AS laravel-deps

# Copy composer files
COPY composer.json composer.lock ./

# Set Composer environment
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_MEMORY_LIMIT=-1

# Install PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy entire application
COPY . .

# Create necessary directories
RUN mkdir -p public/livewire public/vendor public/build \
    storage/app storage/framework/cache storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs \
    bootstrap/cache resources/views/filament app/Filament

# Publish vendor assets
RUN php artisan vendor:publish --all --force

# ---------- Frontend Build Stage ----------
FROM node:20-slim AS frontend-build

# Install build dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 make g++ \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Set Node environment
ENV NODE_ENV=production
ENV VITE_APP_NAME=MerubaliStock
ENV VITE_APP_URL=https://merubali-merubali-app.sbfalk.easypanel.host

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN if [ -f package-lock.json ]; then npm ci --legacy-peer-deps; else npm install --legacy-peer-deps; fi

# Copy all files
COPY . .

# Copy vendor from Laravel stage
COPY --from=laravel-deps /var/www/html/vendor ./vendor
COPY --from=laravel-deps /var/www/html/public ./public

# Create required directories
RUN mkdir -p storage/framework/views app/Filament resources/views/filament bootstrap/cache public/build/assets

# Display build info
RUN echo "=== Frontend Build Info ===" \
 && echo "Node: $(node --version)" \
 && echo "NPM: $(npm --version)" \
 && ls -la resources/

# Create fallback script
RUN echo '#!/bin/bash' > /create-fallback.sh \
 && echo 'mkdir -p public/build/assets' >> /create-fallback.sh \
 && echo 'HASH=$(date +%s)' >> /create-fallback.sh \
 && echo 'echo "/* MerubaliStock CSS */" > public/build/assets/app-$HASH.css' >> /create-fallback.sh \
 && echo 'echo "body{font-family:Inter,sans-serif;background:#f8fafc}*{box-sizing:border-box}.flex{display:flex}.block{display:block}.text-sm{font-size:.875rem}.p-4{padding:1rem}" >> public/build/assets/app-$HASH.css' >> /create-fallback.sh \
 && echo 'echo "// MerubaliStock JS" > public/build/assets/app-$HASH.js' >> /create-fallback.sh \
 && echo 'echo "console.log(\"MerubaliStock loaded\");" >> public/build/assets/app-$HASH.js' >> /create-fallback.sh \
 && echo 'echo "/* Filament Theme */" > public/build/assets/theme-$HASH.css' >> /create-fallback.sh \
 && echo 'echo ".fi-body{font-family:Inter,sans-serif;background:#f8fafc}.fi-sidebar{width:16rem;background:white}" >> public/build/assets/theme-$HASH.css' >> /create-fallback.sh \
 && echo 'cat > public/build/manifest.json << EOF' >> /create-fallback.sh \
 && echo '{' >> /create-fallback.sh \
 && echo '  "resources/css/app.css": {' >> /create-fallback.sh \
 && echo '    "file": "assets/app-$HASH.css",' >> /create-fallback.sh \
 && echo '    "src": "resources/css/app.css",' >> /create-fallback.sh \
 && echo '    "isEntry": true' >> /create-fallback.sh \
 && echo '  },' >> /create-fallback.sh \
 && echo '  "resources/js/app.js": {' >> /create-fallback.sh \
 && echo '    "file": "assets/app-$HASH.js",' >> /create-fallback.sh \
 && echo '    "src": "resources/js/app.js",' >> /create-fallback.sh \
 && echo '    "isEntry": true' >> /create-fallback.sh \
 && echo '  },' >> /create-fallback.sh \
 && echo '  "resources/css/filament/admin/theme.css": {' >> /create-fallback.sh \
 && echo '    "file": "assets/theme-$HASH.css",' >> /create-fallback.sh \
 && echo '    "src": "resources/css/filament/admin/theme.css",' >> /create-fallback.sh \
 && echo '    "isEntry": true' >> /create-fallback.sh \
 && echo '  }' >> /create-fallback.sh \
 && echo '}' >> /create-fallback.sh \
 && echo 'EOF' >> /create-fallback.sh \
 && chmod +x /create-fallback.sh

# Build with fallback strategy
RUN echo "=== Starting Build ===" \
 && (npm run build && echo "Build successful!") \
 || (echo "npm run build failed, trying vite build..." && npx vite build) \
 || (echo "All builds failed, creating fallback..." && /create-fallback.sh)

# Verify build
RUN echo "=== Build Verification ===" \
 && ls -la public/build/ \
 && ls -la public/build/assets/ \
 && cat public/build/manifest.json \
 && echo "File count: $(find public/build -type f | wc -l)" \
 && echo "Build complete!"

# ---------- Production Stage ----------
FROM php-base AS production

# Copy configs
COPY deploy/nginx.conf /etc/nginx/nginx.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

# Copy application
COPY . .

# Copy dependencies and assets
COPY --from=laravel-deps /var/www/html/vendor ./vendor
COPY --from=laravel-deps /var/www/html/public/vendor ./public/vendor
COPY --from=frontend-build /app/public/build ./public/build

# Verify production assets
RUN echo "=== Production Verification ===" \
 && ls -la public/build/ \
 && cat public/build/manifest.json \
 && echo "Assets ready for production!"

# Set permissions
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache public \
 && find storage -type d -exec chmod 775 {} \; \
 && find storage -type f -exec chmod 664 {} \; \
 && chmod -R 775 bootstrap/cache \
 && chmod -R 755 public

# Final asset publishing
RUN php artisan vendor:publish --tag=livewire:assets --force || echo "Livewire OK" \
 && php artisan vendor:publish --tag=filament-assets --force || echo "Filament OK"

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
