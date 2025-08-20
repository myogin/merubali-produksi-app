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
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

# ---------- Frontend build ----------
FROM node:20 AS frontend
WORKDIR /app
COPY package.json package-lock.json* pnpm-lock.yaml* yarn.lock* .npmrc* ./
RUN npm ci || npm i
COPY . .
RUN npm run build

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

# Copy built assets (Vite) dari stage frontend
COPY --from=frontend /app/public/build ./public/build

# Laravel permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
 && find storage -type d -exec chmod 775 {} \; \
 && find storage -type f -exec chmod 664 {} \; \
 && chmod -R 775 bootstrap/cache

# Expose HTTP
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD curl -f http://localhost/health || exit 1

ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
