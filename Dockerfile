FROM php:8.2-apache

# Instalar dependências do sistema e extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    default-mysql-client

# Instalar extensões PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets

# Ativar mod_rewrite do Apache (essencial para Laravel)
RUN a2enmod rewrite

# Instalar Composer dentro do container
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar diretório de trabalho
WORKDIR /var/www/html

# Ajustar permissões
RUN chown -R www-data:www-data /var/www/html