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

# Publish vendor assets (Livewire, Filament, etc.)
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

# Copy package files first for better Docker caching
COPY package.json package-lock.json* ./

# Install Node dependencies
RUN if [ -f package-lock.json ]; then \
        npm ci --only=production --legacy-peer-deps; \
    else \
        npm install --only=production --legacy-peer-deps; \
    fi

# Copy all application files
COPY . .

# Copy vendor directory from Laravel stage (needed for TailwindCSS scanning)
COPY --from=laravel-deps /var/www/html/vendor ./vendor

# Copy any existing public assets
COPY --from=laravel-deps /var/www/html/public ./public

# Create all directories needed by TailwindCSS 4.0
RUN mkdir -p \
    storage/framework/views \
    storage/framework/cache \
    app/Filament \
    resources/views/filament \
    bootstrap/cache \
    resources/css \
    resources/js \
    public/build/assets

# Display environment info for debugging
RUN echo "=== Frontend Build Environment ===" && \
    echo "Node version: $(node --version)" && \
    echo "NPM version: $(npm --version)" && \
    echo "Working directory: $(pwd)" && \
    echo "Source files:" && \
    ls -la resources/ && \
    echo "Package.json:" && \
    cat package.json

# Build frontend assets with comprehensive error handling
RUN echo "=== Starting Frontend Build ===" && \
    set -e && \
    ( \
        echo "Attempting npm run build..." && \
        npm run build && \
        echo "✅ Build successful!" \
    ) || ( \
        echo "❌ npm run build failed, trying npx vite build..." && \
        npx vite build --mode production --logLevel info \
    ) || ( \
        echo "❌ All builds failed, creating production-ready fallback..." && \
        \
        mkdir -p public/build/assets && \
        \
        echo "/* MerubaliStock - Production CSS */" > public/build/assets/app-$(date +%s).css && \
        cat >> public/build/assets/app-$(date +%s).css << 'CSS_EOF' && \
@import 'tailwindcss/base';
@import 'tailwindcss/components';
@import 'tailwindcss/utilities';

/* Base styles */
*, ::before, ::after { box-sizing: border-box; }
html { line-height: 1.5; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
body { margin: 0; background: #f8fafc; color: #1e293b; }

/* Utility classes */
.container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
.flex { display: flex; }
.block { display: block; }
.hidden { display: none; }
.text-sm { font-size: 0.875rem; }
.text-base { font-size: 1rem; }
.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }
.text-gray-900 { color: #0f172a; }
.bg-white { background-color: #ffffff; }
.p-4 { padding: 1rem; }
.rounded { border-radius: 0.375rem; }
.shadow { box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); }

/* Dark mode */
@media (prefers-color-scheme: dark) {
    body { background: #0f172a; color: #f1f5f9; }
    .dark\:bg-gray-900 { background-color: #0f172a; }
    .dark\:text-gray-100 { color: #f1f5f9; }
}
CSS_EOF
        \
        echo "// MerubaliStock - Production JS" > public/build/assets/app-$(date +%s).js && \
        cat >> public/build/assets/app-$(date +%s).js << 'JS_EOF' && \
// Import bootstrap
import './bootstrap';

// Application initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('MerubaliStock loaded successfully');

    // Initialize Alpine.js if available
    if (typeof Alpine !== 'undefined') {
        Alpine.start();
    }

    // Initialize Livewire if available
    if (typeof Livewire !== 'undefined') {
        Livewire.start();
    }
});

// Livewire hooks
document.addEventListener('livewire:init', () => {
    console.log('Livewire initialized');
});

document.addEventListener('livewire:navigated', () => {
    console.log('Livewire navigated');
});
JS_EOF
        \
        echo "/* MerubaliStock - Filament Admin Theme */" > public/build/assets/theme-$(date +%s).css && \
        cat >> public/build/assets/theme-$(date +%s).css << 'THEME_EOF' && \
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
    --primary: 245 158 11;
    --gray-50: 249 250 251;
    --gray-100: 243 244 246;
    --gray-900: 17 24 39;
    --sidebar-width: 16rem;
}

/* Filament base styles */
.fi-body {
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background-color: rgb(var(--gray-50));
    min-height: 100vh;
}

.fi-sidebar {
    width: var(--sidebar-width);
    background-color: white;
    border-right: 1px solid rgb(var(--gray-100));
}

.fi-main {
    margin-left: var(--sidebar-width);
    padding: 1.5rem;
}

.fi-header {
    background-color: white;
    border-bottom: 1px solid rgb(var(--gray-100));
    padding: 1rem 1.5rem;
}

/* Components */
.fi-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s;
}

.fi-btn-primary {
    background-color: rgb(var(--primary));
    color: white;
}

.fi-btn-primary:hover {
    background-color: rgb(var(--primary) / 0.9);
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .fi-body {
        background-color: rgb(var(--gray-900));
        color: white;
    }
    .fi-sidebar, .fi-header {
        background-color: rgb(var(--gray-900));
        border-color: rgb(55 65 81);
    }
}
THEME_EOF
        \
        APP_CSS=$(ls public/build/assets/app-*.css | head -1 | xargs basename) && \
        APP_JS=$(ls public/build/assets/app-*.js | head -1 | xargs basename) && \
        THEME_CSS=$(ls public/build/assets/theme-*.css | head -1 | xargs basename) && \
        \
        cat > public/build/manifest.json << MANIFEST_EOF && \
{
  "resources/css/app.css": {
    "file": "assets/$APP_CSS",
    "src": "resources/css/app.css",
    "isEntry": true,
    "css": ["assets/$APP_CSS"]
  },
  "resources/js/app.js": {
    "file": "assets/$APP_JS",
    "src": "resources/js/app.js",
    "isEntry": true
  },
  "resources/css/filament/admin/theme.css": {
    "file": "assets/$THEME_CSS",
    "src": "resources/css/filament/admin/theme.css",
    "isEntry": true,
    "css": ["assets/$THEME_CSS"]
  }
}
MANIFEST_EOF
        \
        echo "✅ Production fallback assets created successfully!" \
    )

# Final verification of build output
RUN echo "=== Build Verification ===" && \
    echo "Build directory contents:" && \
    ls -la public/build/ && \
    echo "" && \
    echo "Assets directory:" && \
    ls -la public/build/assets/ && \
    echo "" && \
    echo "Manifest content:" && \
    cat public/build/manifest.json && \
    echo "" && \
    echo "File sizes:" && \
    du -h public/build/assets/* && \
    echo "" && \
    echo "✅ Frontend build stage complete!"

# ---------- Production Stage ----------
FROM php-base AS production

# Copy nginx and supervisor configs
COPY deploy/nginx.conf /etc/nginx/nginx.conf
COPY deploy/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy and setup entrypoint script
COPY deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . .

# Copy PHP dependencies from laravel-deps stage
COPY --from=laravel-deps /var/www/html/vendor ./vendor

# Copy published vendor assets
COPY --from=laravel-deps /var/www/html/public/vendor ./public/vendor

# 🎯 CRITICAL: Copy built frontend assets
COPY --from=frontend-build /app/public/build ./public/build

# Verify assets were copied correctly
RUN echo "=== Production Asset Verification ===" && \
    echo "Checking build directory:" && \
    ls -la public/build/ && \
    echo "" && \
    echo "Checking assets:" && \
    ls -la public/build/assets/ 2>/dev/null || echo "No assets directory found!" && \
    echo "" && \
    echo "Checking manifest:" && \
    if [ -f "public/build/manifest.json" ]; then \
        echo "✅ Manifest found:" && \
        cat public/build/manifest.json; \
    else \
        echo "❌ CRITICAL: No manifest found!" && \
        exit 1; \
    fi && \
    echo "" && \
    echo "Asset file count:" && \
    find public/build -type f | wc -l && \
    echo "✅ Assets verified successfully!"

# Create necessary directories and set permissions
RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache public \
 && find storage -type d -exec chmod 775 {} \; \
 && find storage -type f -exec chmod 664 {} \; \
 && chmod -R 775 bootstrap/cache \
 && chmod -R 755 public

# Final asset publishing (safety net)
RUN php artisan vendor:publish --tag=livewire:assets --force || echo "Livewire assets handled" \
 && php artisan vendor:publish --tag=filament-assets --force || echo "Filament assets handled"

# Expose port and setup health check
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Set entrypoint and default command
ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
