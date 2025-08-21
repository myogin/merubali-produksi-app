#!/usr/bin/env bash

# MerubaliStock Production Entrypoint
# Enhanced for better asset handling and debugging

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] ✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}[$(date +'%H:%M:%S')] ⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}[$(date +'%H:%M:%S')] ❌ $1${NC}"
}

print_status "🚀 Starting MerubaliStock container initialization..."

# Ensure we're in the right directory
cd /var/www/html

# 1. Environment validation
print_status "Validating environment..."
if [ ! -f ".env" ]; then
    print_error ".env file not found!"
    exit 1
fi

# 2. APP_KEY handling (improved from original)
print_status "Checking APP_KEY..."
if [ ! -f /var/www/html/storage/app/.app_key_initialized ]; then
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
        print_warning "APP_KEY missing, generating new one..."
        php artisan key:generate --force
    fi
    mkdir -p /var/www/html/storage/app
    touch /var/www/html/storage/app/.app_key_initialized
    print_status "APP_KEY initialized"
fi

# 3. Wait for database connection (enhanced)
if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ]; then
    print_status "Waiting for database connection..."
    max_attempts=30
    attempt=1

    while [ $attempt -le $max_attempts ]; do
        if php artisan tinker --execute="DB::connection()->getPdo();" >/dev/null 2>&1; then
            print_status "Database connection established"
            break
        fi

        if [ $attempt -eq $max_attempts ]; then
            print_error "Failed to connect to database after $max_attempts attempts"
            exit 1
        fi

        print_warning "Database not ready, attempt $attempt/$max_attempts"
        sleep 2
        attempt=$((attempt + 1))
    done
fi

# 4. Verify frontend assets (NEW - Critical for your case!)
print_status "Verifying frontend assets..."
if [ ! -f "public/build/manifest.json" ]; then
    print_error "Frontend build manifest missing!"
    print_error "This will cause CSS/JS loading issues."

    # Try to create emergency assets
    print_warning "Creating emergency frontend assets..."
    mkdir -p public/build/assets

    # Create minimal working manifest
    cat > public/build/manifest.json << 'EOF'
{
  "resources/css/app.css": {
    "file": "assets/app-emergency.css",
    "src": "resources/css/app.css",
    "isEntry": true
  },
  "resources/js/app.js": {
    "file": "assets/app-emergency.js",
    "src": "resources/js/app.js",
    "isEntry": true
  },
  "resources/css/filament/admin/theme.css": {
    "file": "assets/theme-emergency.css",
    "src": "resources/css/filament/admin/theme.css",
    "isEntry": true
  }
}
EOF

    # Create minimal CSS
    echo "/* Emergency CSS - Please rebuild assets */" > public/build/assets/app-emergency.css
    echo "body { font-family: Inter, sans-serif; background: #f8fafc; }" >> public/build/assets/app-emergency.css
    echo ".dark body { background: #1e293b; color: #f1f5f9; }" >> public/build/assets/app-emergency.css

    # Create minimal JS
    echo "// Emergency JS - Please rebuild assets" > public/build/assets/app-emergency.js
    echo "console.log('Emergency assets loaded - please rebuild frontend');" >> public/build/assets/app-emergency.js

    # Create minimal theme CSS
    echo "/* Emergency Filament Theme */" > public/build/assets/theme-emergency.css
    echo "@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');" >> public/build/assets/theme-emergency.css
    echo ".fi-body { font-family: 'Inter', sans-serif; }" >> public/build/assets/theme-emergency.css

    print_warning "Emergency assets created. Please rebuild frontend ASAP!"
else
    print_status "Frontend assets verified ✅"
    echo "Manifest preview:"
    head -10 public/build/manifest.json
fi

# 5. Directory permissions
print_status "Setting up directories and permissions..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache public
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
chmod -R 775 bootstrap/cache
chmod -R 755 public

# 6. Storage link (idempotent)
print_status "Creating storage link..."
php artisan storage:link || print_warning "Storage link might already exist"

# 7. Database migrations
print_status "Running database migrations..."
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
else
    php artisan migrate --force --no-interaction
fi

# 8. Asset publishing (enhanced)
print_status "Publishing vendor assets..."
php artisan vendor:publish --tag=livewire:assets --force || print_warning "Livewire assets issue"
php artisan vendor:publish --tag=filament-assets --force || print_warning "Filament assets issue"

# Ensure Filament assets exist
if [ ! -d "public/vendor/filament" ]; then
    print_warning "Filament vendor assets missing, attempting to publish all..."
    php artisan vendor:publish --all --force
fi

# 9. Cache optimization
print_status "Optimizing Laravel caches..."
php artisan config:cache || print_warning "Config cache failed"
php artisan route:cache || print_warning "Route cache failed"
php artisan view:cache || print_warning "View cache failed"
php artisan event:cache || print_warning "Event cache failed"

# 10. Final optimization
print_status "Final application optimization..."
php artisan optimize

# 11. Health check
print_status "Performing health check..."
if php artisan tinker --execute="echo 'Laravel OK';" >/dev/null 2>&1; then
    print_status "Application health check passed ✅"
else
    print_warning "Application health check failed - check logs"
fi

# 12. Start queue worker if configured
if [ "$QUEUE_CONNECTION" != "sync" ] && [ "${START_QUEUE_WORKER:-false}" = "true" ]; then
    print_status "Starting queue worker..."
    php artisan queue:work --daemon --sleep=3 --tries=3 &
fi

print_status "🎉 Container initialization complete!"
print_status "Application: $APP_URL"
print_status "Admin Panel: $APP_URL/admin"

# Final asset verification
if [ -f "public/build/manifest.json" ]; then
    ASSET_COUNT=$(find public/build -type f | wc -l)
    print_status "Frontend assets: $ASSET_COUNT files ready"
else
    print_error "Frontend assets still missing - CSS/JS may not load!"
fi

print_status "Starting services with supervisord..."

# Execute the main command
exec "$@"
