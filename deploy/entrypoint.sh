#!/usr/bin/env bash
set -e

# Failover APP_KEY
if [ ! -f /var/www/html/storage/app/.app_key_initialized ]; then
  if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --force
  fi
  mkdir -p /var/www/html/storage/app
  touch /var/www/html/storage/app/.app_key_initialized
fi

# Storage link (idempotent)
php artisan storage:link || true

# Package discovery untuk Livewire
php artisan package:discover --ansi

# Clear cache sebelum cache baru
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Cache config/routes/views
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache || true

# Jalankan migrasi jika diminta
if [ "$RUN_MIGRATIONS" = "true" ]; then
  php artisan migrate --force
fi

exec "$@"
