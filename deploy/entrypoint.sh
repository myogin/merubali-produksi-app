#!/usr/bin/env bash

# MerubaliStock - Simple Entrypoint for EasyPanel
# Environment variables are handled by EasyPanel platform

set -e

echo "[$(date +'%H:%M:%S')] 🚀 Starting MerubaliStock..."

cd /var/www/html

# Basic directory setup
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/testing storage/framework/views storage/logs bootstrap/cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache public
chmod -R 775 storage bootstrap/cache
chmod -R 755 public

# Generate APP_KEY if needed (one-time only)
if [ ! -f /var/www/html/storage/app/.app_key_initialized ]; then
    if [ -z "$APP_KEY" ]; then
        echo "[$(date +'%H:%M:%S')] Generating APP_KEY..."
        php artisan key:generate --force
    fi
    touch /var/www/html/storage/app/.app_key_initialized
fi

# Wait for database (simple check)
if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ]; then
    echo "[$(date +'%H:%M:%S')] Waiting for database..."
    for i in {1..30}; do
        if php artisan tinker --execute="DB::connection()->getPdo();" >/dev/null 2>&1; then
            echo "[$(date +'%H:%M:%S')] Database connected"
            break
        fi
        sleep 2
    done
fi

# Storage link
php artisan storage:link >/dev/null 2>&1 || true

# Run migrations
echo "[$(date +'%H:%M:%S')] Running migrations..."
php artisan migrate --force --no-interaction

# Publish assets
php artisan vendor:publish --tag=livewire:assets --force >/dev/null 2>&1 || true
php artisan vendor:publish --tag=filament-assets --force >/dev/null 2>&1 || true

# Cache optimization
echo "[$(date +'%H:%M:%S')] Optimizing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[$(date +'%H:%M:%S')] ✅ MerubaliStock ready!"

# Start services
exec "$@"
