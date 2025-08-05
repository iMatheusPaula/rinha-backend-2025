FROM php:8.3-cli

# Instalar dependências para o swoole
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && pecl install swoole \
    && docker-php-ext-enable swoole

# Copia o código para o container
WORKDIR /app
COPY . .

# Expõe a porta usada pelo Swoole
EXPOSE 9501

# Comando para rodar o servidor
CMD ["php", "server.php"]
