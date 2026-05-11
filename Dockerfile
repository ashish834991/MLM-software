FROM php:8.2-cli

WORKDIR /app

COPY . .

RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-install pdo pdo_mysql

RUN composer install --no-dev --optimize-autoloader --no-scripts

RUN php artisan optimize:clear || true

EXPOSE 10000

CMD ["php", "-S", "0.0.0.0:10000", "-t", "public"]