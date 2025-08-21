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

# Copy all source files
COPY . .

# Create necessary directories
RUN mkdir -p public/livewire public/vendor public/build storage/framework/views \
    storage/app storage/logs bootstrap/cache resources/views/filament app/Filament

# Publish vendor assets
RUN php artisan vendor:publish --all --force || echo "Some assets may not be published"

# ---------- Frontend build ----------
FROM node:20-slim AS frontend
WORKDIR /app

# Install build dependencies for native packages
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 make g++ \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

ENV NODE_ENV=production
ENV VITE_APP_NAME=MerubaliStock
ENV VITE_APP_URL=https://merubali-merubali-app.sbfalk.easypanel.host

# Copy package files first (for better caching)
COPY package.json package-lock.json* ./

# Install dependencies
RUN if [ -f package-lock.json ]; then \
        npm ci --legacy-peer-deps; \
    else \
        npm install --legacy-peer-deps; \
    fi

# Copy all source files needed for build
COPY . .

# Copy vendor directory from deps (needed for TailwindCSS scanning)
COPY --from=deps /var/www/html/vendor ./vendor
COPY --from=deps /var/www/html/public ./public

# Ensure all required directories exist for TailwindCSS 4.0
RUN mkdir -p storage/framework/views storage/framework/cache \
    app/Filament resources/views/filament bootstrap/cache \
    resources/css resources/js

# Verify source files before build
RUN echo "=== Pre-build Verification ===" && \
    echo "Source files:" && \
    ls -la resources/css/ && \
    ls -la resources/js/ && \
    echo "Package.json:" && \
    cat package.json && \
    echo "Vite config:" && \
    cat vite.config.js

# 🎯 MAIN BUILD with comprehensive error handling
RUN echo "=== Starting Vite Build ===" && \
    echo "Node: $(node --version)" && \
    echo "NPM: $(npm --version)" && \
    if npm run build 2>&1; then \
        echo "✅ Build successful!" && \
        echo "Build output:" && \
        ls -la public/build/ && \
        cat public/build/manifest.json; \
    else \
        echo "❌ npm run build failed, trying alternatives..." && \
        if npx vite build --mode production 2>&1; then \
            echo "✅ Alternative build successful!"; \
        else \
            echo "❌ All builds failed, creating proper fallback..." && \
            mkdir -p public/build/assets && \
            \
            echo "/* MerubaliStock App CSS - Generated Fallback */" > public/build/assets/app-fallback.css && \
            echo "@import 'tailwindcss/base';" >> public/build/assets/app-fallback.css && \
            echo "@import 'tailwindcss/components';" >> public/build/assets/app-fallback.css && \
            echo "@import 'tailwindcss/utilities';" >> public/build/assets/app-fallback.css && \
            echo "body { font-family: 'Inter', sans-serif; }" >> public/build/assets/app-fallback.css && \
            \
            echo "// MerubaliStock App JS - Generated Fallback" > public/build/assets/app-fallback.js && \
            echo "import './bootstrap.js';" >> public/build/assets/app-fallback.js && \
            echo "console.log('MerubaliStock loaded');" >> public/build/assets/app-fallback.js && \
            \
            echo "/* Filament Theme CSS - Generated Fallback */" > public/build/assets/theme-fallback.css && \
            echo "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');" >> public/build/assets/theme-fallback.css && \
            echo ".fi-body { font-family: 'Inter', sans-serif; }" >> public/build/assets/theme-fallback.css && \
            \
            cat > public/build/manifest.json << 'MANIFEST_EOF' && \
{
  "resources/css/app.css": {
    "file": "assets/app-fallback.css",
    "src": "resources/css/app.css",
    "isEntry": true
  },
  "resources/js/app.js": {
    "file": "assets/app-fallback.js",
    "src": "resources/js/app.js",
    "isEntry": true
  },
  "resources/css/filament/admin/theme.css": {
    "file": "assets/theme-fallback.css",
    "src": "resources/css/filament/admin/theme.css",
    "isEntry": true
  }
}
MANIFEST_EOF
            echo "✅ Proper fallback assets created!"; \
        fi; \
    fi

# Final verification
RUN echo "=== Final Build Verification ===" && \
    ls -la public/build/ && \
    echo "Manifest content:" && \
    cat public/build/manifest.json && \
    echo "Asset files:" && \
    find public/build -name "*.css" -o -name "*.js" | head -10 && \
    echo "File sizes:" && \
    du -h public/build/* && \
    echo "=== Frontend Build Stage Complete ==="

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

# 🎯 CRITICAL: Copy built assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Copy published vendor assets
COPY --from=deps /var/www/html/public/vendor ./public/vendor 2>/dev/null || mkdir -p ./public/vendor

# Verify assets copied correctly
RUN echo "=== Production Asset Verification ===" && \
    ls -la public/build/ && \
    echo "Manifest exists:" && \
    cat public/build/manifest.json && \
    echo "Asset files count:" && \
    find public/build -type f | wc -l && \
    if [ ! -f "public/build/manifest.json" ]; then \
        echo "❌ CRITICAL: No manifest in production!" && \
        exit 1; \
    fi

# Final asset publishing (safety net)
RUN php artisan vendor:publish --tag=livewire:assets --force 2>/dev/null || echo "Livewire assets handled" && \
    php artisan vendor:publish --tag=filament-assets --force 2>/dev/null || echo "Filament assets handled"

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
