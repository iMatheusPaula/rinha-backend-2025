FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    supervisor \
    unzip \
    libcurl4-openssl-dev \
    libssl-dev \
    pkg-config \
    libbrotli-dev

RUN pecl install swoole redis \
    && docker-php-ext-enable swoole redis

COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /app

COPY ./app .

EXPOSE 9501

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]