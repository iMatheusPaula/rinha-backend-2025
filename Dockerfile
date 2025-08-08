FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    unzip \
    libcurl4-openssl-dev \
    libssl-dev \
    pkg-config \
    libbrotli-dev \
    && pecl install swoole \
    && docker-php-ext-enable swoole \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.* ./

RUN composer install

COPY . .

EXPOSE 9501

CMD ["php", "server.php"]
