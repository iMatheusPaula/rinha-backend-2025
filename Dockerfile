FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    supervisor \
    $PHPIZE_DEPS \
    build-base \
    curl-dev \
    openssl-dev

RUN pecl install swoole redis \
    && docker-php-ext-enable swoole redis

RUN { \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.memory_consumption=16"; \
    echo "opcache.interned_strings_buffer=4"; \
    echo "opcache.max_accelerated_files=20"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.jit_buffer_size=16M"; \
    echo "opcache.jit=tracing"; \
} > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /app

COPY ./app .

EXPOSE 9501