FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    zip unzip curl git \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    oniguruma-dev libzip-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo pdo_mysql \
    mbstring \
    zip \
    gd \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
