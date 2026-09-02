# Production image for Trickle Hub (PHP 8.3-FPM + the app).
# Build from the repo root:  docker build -f docker/Dockerfile -t trickle-hub .
FROM php:8.3-fpm-alpine

# --- System + PHP extensions the app needs --------------------------------
RUN apk add --no-cache \
        bash git icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        oniguruma-dev mysql-client supervisor nginx $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip intl exif pcntl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# --- Composer -------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Opcache tuned for production -----------------------------------------
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=0'; \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.interned_strings_buffer=16'; \
    } > /usr/local/etc/php/conf.d/opcache.ini \
    && { echo 'upload_max_filesize=25M'; echo 'post_max_size=30M'; echo 'memory_limit=512M'; } \
       > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# --- Install PHP deps first (better layer caching) ------------------------
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# --- App code -------------------------------------------------------------
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache \
    && cp -n .env.example .env 2>/dev/null || true

# nginx + supervisor (php-fpm + queue worker + scheduler) config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
