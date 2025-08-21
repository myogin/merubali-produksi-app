#!/usr/bin/env bash

# MerubaliStock Production Entrypoint
# Fixed .env file handling

set -e

# Colors
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

cd /var/www/html

# ===== STEP 1: CREATE .env FILE =====
print_status "Setting up environment file..."

if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        print_warning ".env missing, copying from .env.example..."
        cp .env.example .env
    else
        print_error "Neither .env nor .env.example found!"
        print_status "Creating minimal .env file..."

        cat > .env << 'ENV_EOF'
APP_NAME=MerubaliStock
APP_ENV=production
APP_DEBUG=false
APP_URL=https://merubali-merubali-app.sbfalk.easypanel.host
APP_TIMEZONE=Asia/Makassar
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=merubali_merubali-app-db
DB_PORT=3306
DB_DATABASE=merubaliapp
DB_USERNAME=mariadb
DB_PASSWORD=d36505d65ed9d71c2d8b

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.sbfalk.easypanel.host
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
CACHE_PREFIX=merubali_cache

QUEUE_CONNECTION=database

ASSET_URL=https://merubali-merubali-app.sbfalk.easypanel.host

MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_FROM_ADDRESS=no-reply@merubali.com
MAIL_FROM_NAME=MerubaliStock

PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

VITE_APP_NAME=MerubaliStock
VITE_APP_URL=https://merubali-merubali-app.sbfalk.easypanel.host

TRUSTED_PROXIES=*
TRUSTED_HOSTS=merubali-merubali-app.sbfalk.easypanel.host

FILESYSTEM_DISK=local
ENV_EOF

        print_status "Basic .env file created"
    fi
fi

# ===== STEP 2: OVERRIDE WITH ENVIRONMENT VARIABLES =====
print_status "Applying environment variable overrides..."

# Override key values from environment variables if they exist
[ ! -z "$APP_NAME" ] && sed -i "s/APP_NAME=.*/APP_NAME=$APP_NAME/" .env
[ ! -z "$APP_ENV" ] && sed -i "s/APP_ENV=.*/APP_ENV=$APP_ENV/" .env
[ ! -z "$APP_DEBUG" ] && sed -i "s/APP_DEBUG=.*/APP_DEBUG=$APP_DEBUG/" .env
[ ! -z "$APP_URL" ] && sed -i "s|APP_URL=.*|APP_URL=$APP_URL|" .env

[ ! -z "$DB_HOST" ] && sed -i "s/DB_HOST=.*/DB_HOST=$DB_HOST/" .env
[ ! -z "$DB_DATABASE" ] && sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_DATABASE/" .env
[ ! -z "$DB_USERNAME" ] && sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USERNAME/" .env
[ ! -z "$DB_PASSWORD" ] && sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env

print_status ".env file ready!"

# ===== STEP 3: APP_KEY HANDLING =====
print_status "Checking APP_KEY..."

if [ ! -f /var/www/html/storage/app/.app_key_initialized ]; then
    APP_KEY_IN_ENV=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
    if [ -z "$APP_KEY_IN_ENV" ] || [ "$APP_KEY_IN_ENV" = "base64:" ] || [ "$APP_KEY_IN_ENV" = "" ]; then
        print_warning "APP_KEY missing or empty, generating new one..."
        php artisan key:generate --force
    fi
    mkdir -p /var/www/html/storage/app
    touch /var/www/html/storage/app/.app_key_initialized
    print_status "APP_KEY verified"
fi

# ===== STEP 4: DATABASE CONNECTION =====
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

# ===== STEP 5: VERIFY FRONTEND ASSETS =====
print_status "Verifying frontend assets..."

if [ ! -f "public/build/manifest.json" ]; then
    print_error "Frontend build manifest missing!"
    print_warning "Creating emergency assets..."

    mkdir -p public/build/assets
    HASH=$(date +%s | tail -c 6)

    # Emergency CSS
    cat > public/build/assets/app-$HASH.css << 'CSS_EOF'
/* Emergency CSS - MerubaliStock */
body{font-family:Inter,sans-serif;background:#f8fafc;color:#1e293b}
*{box-sizing:border-box}
.flex{display:flex}.block{display:block}.text-sm{font-size:.875rem}
.p-4{padding:1rem}.bg-white{background:#fff}.rounded{border-radius:.375rem}
.shadow{box-shadow:0 1px 3px rgba(0,0,0,.1)}
@media(prefers-color-scheme:dark){body{background:#0f172a;color:#f1f5f9}}
CSS_EOF

    # Emergency JS
    echo "console.log('MerubaliStock Emergency JS loaded');" > public/build/assets/app-$HASH.js

    # Emergency Theme
    cat > public/build/assets/theme-$HASH.css << 'THEME_EOF'
/* Emergency Filament Theme */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
.fi-body{font-family:Inter,sans-serif;background:#f8fafc}
.fi-sidebar{width:16rem;background:white;border-right:1px solid #e5e7eb}
.fi-main{margin-left:16rem;padding:1.5rem}
THEME_EOF

    # Emergency Manifest
    cat > public/build/manifest.json << EOF
{
  "resources/css/app.css": {
    "file": "assets/app-$HASH.css",
    "src": "resources/css/app.css",
    "isEntry": true
  },
  "resources/js/app.js": {
    "file": "assets/app-$HASH.js",
    "src": "resources/js/app.js",
    "isEntry": true
  },
  "resources/css/filament/admin/theme.css": {
    "file": "assets/theme-$HASH.css",
    "src": "resources/css/filament/admin/theme.css",
    "isEntry": true
  }
}
EOF

    print_warning "Emergency assets created! Rebuild container to fix properly."
else
    print_status "Frontend assets verified ✅"
fi

# ===== STEP 6: DIRECTORIES & PERMISSIONS =====
print_status "Setting up directories and permissions..."

mkdir -p storage/app/public storage/framework/cache/data \
    storage/framework/sessions storage/framework/testing \
    storage/framework/views storage/logs bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache public
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 664 {} \;
chmod -R 775 bootstrap/cache
chmod -R 755 public

# ===== STEP 7: LARAVEL SETUP =====
print_status "Setting up Laravel..."

# Storage link
php artisan storage:link || print_warning "Storage link might already exist"

# Migrations
php artisan migrate --force --no-interaction

# Asset publishing
php artisan vendor:publish --tag=livewire:assets --force || print_warning "Livewire assets"
php artisan vendor:publish --tag=filament-assets --force || print_warning "Filament assets"

# Cache optimization
php artisan config:cache || print_warning "Config cache failed"
php artisan route:cache || print_warning "Route cache failed"
php artisan view:cache || print_warning "View cache failed"
php artisan optimize

# ===== STEP 8: FINAL CHECKS =====
print_status "Final health check..."

if php artisan tinker --execute="echo 'Laravel OK';" >/dev/null 2>&1; then
    print_status "Application health check passed ✅"
else
    print_warning "Application health check failed"
fi

# Start queue worker if needed
if [ "$QUEUE_CONNECTION" != "sync" ] && [ "${START_QUEUE_WORKER:-false}" = "true" ]; then
    print_status "Starting queue worker..."
    php artisan queue:work --daemon --sleep=3 --tries=3 &
fi

print_status "🎉 Container initialization complete!"
print_status "Application: $APP_URL"
print_status "Admin Panel: $APP_URL/admin"

if [ -f "public/build/manifest.json" ]; then
    ASSET_COUNT=$(find public/build -type f | wc -l)
    print_status "Frontend assets: $ASSET_COUNT files ready"
else
    print_error "Frontend assets still missing!"
fi

print_status "Starting services..."
exec "$@"
