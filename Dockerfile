FROM php:8.4-fpm-alpine AS php-base

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    curl \
    curl-dev \
    git \
    icu-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    zlib-dev \
    unzip \
    zip \
    $PHPIZE_DEPS \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install \
    bcmath \
    curl \
    exif \
    gd \
    intl \
    mbstring \
    opcache \
    pdo_mysql \
    pcntl \
    zip \
  && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM php-base AS vendor

COPY composer.json composer.lock ./
RUN composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

FROM php-base AS production

RUN apk add --no-cache nginx supervisor

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

COPY --from=vendor /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY .env /var/www/html/.env
COPY . .

COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/conf.d/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
  && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
  && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
