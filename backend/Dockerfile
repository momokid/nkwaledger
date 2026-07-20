FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev libpng-dev libonig-dev curl \
    nginx supervisor gettext-base \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN npm ci && npm run build

RUN rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx.conf /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 8080

CMD envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan db:seed --class=RolesAndPermissionsSeeder --force \
    && supervisord -c /etc/supervisor/conf.d/supervisord.conf