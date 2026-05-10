FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libzip-dev \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client

RUN docker-php-ext-install pdo pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

ENV APP_ENV=production
ENV APP_DEBUG=false

RUN composer install --no-dev --optimize-autoloader --no-scripts

RUN php artisan config:clear || true
RUN php artisan cache:clear || true
RUN php artisan optimize:clear || true

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "-t", "public"]