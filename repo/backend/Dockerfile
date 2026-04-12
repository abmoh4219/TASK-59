# ============================================
# Backend Dockerfile — PHP 8.2-FPM + Nginx
# ============================================

# ---------- Base stage ----------
FROM php:8.2-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl libicu-dev libzip-dev libpng-dev libjpeg-dev \
    libonig-dev libsodium-dev nginx supervisor \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql intl zip gd sodium opcache mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app/backend

# Copy composer files first for caching
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy full application
COPY . .

# Run post-install scripts
RUN composer run-script post-install-cmd --no-interaction 2>/dev/null || true

# Create required directories
RUN mkdir -p var/cache var/log /app/uploads \
    && chown -R www-data:www-data var /app/uploads

# Configure PHP-FPM
RUN echo '[www]' > /usr/local/etc/php-fpm.d/zz-custom.conf \
    && echo 'listen = 127.0.0.1:9000' >> /usr/local/etc/php-fpm.d/zz-custom.conf

# Configure Nginx
RUN rm -f /etc/nginx/sites-enabled/default
COPY <<'NGINX_CONF' /etc/nginx/sites-enabled/backend.conf
server {
    listen 8000;
    server_name _;
    root /app/backend/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    client_max_body_size 50M;
}
NGINX_CONF

# Configure Supervisor
COPY <<'SUPERVISOR_CONF' /etc/supervisor/conf.d/app.conf
[supervisord]
nodaemon=true
user=root

[program:php-fpm]
command=php-fpm --nodaemonize
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
SUPERVISOR_CONF

# Entrypoint: run migrations then start services
COPY <<'ENTRYPOINT_SH' /entrypoint.sh
#!/bin/sh
set -e
cd /app/backend

echo "Waiting for MySQL to be ready..."
MAX_RETRIES=30
RETRY=0
until php bin/console doctrine:query:sql "SELECT 1" --env=prod 2>/dev/null || [ $RETRY -eq $MAX_RETRIES ]; do
    RETRY=$((RETRY+1))
    echo "  Waiting for database... ($RETRY/$MAX_RETRIES)"
    sleep 2
done

echo "Clearing cache..."
php bin/console cache:clear --env=prod --no-interaction 2>&1 || true

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 || echo "Migrations skipped or failed"

echo "Loading fixtures (if database is empty)..."
USER_COUNT=$(php bin/console doctrine:query:sql "SELECT COUNT(*) as c FROM \`user\`" --env=prod 2>/dev/null | grep -o '[0-9]*' | head -1 || echo "0")
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    php bin/console doctrine:fixtures:load --no-interaction --env=prod 2>&1 || echo "Fixtures skipped"
else
    echo "Database already has data ($USER_COUNT users), skipping fixtures."
fi

echo "Starting services..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/app.conf
ENTRYPOINT_SH
RUN chmod +x /entrypoint.sh

EXPOSE 8000

CMD ["/entrypoint.sh"]

# ---------- Test stage ----------
FROM base AS test

# Install dev dependencies for testing
RUN composer install --optimize-autoloader --no-interaction

# Install Node.js for frontend tests
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app
