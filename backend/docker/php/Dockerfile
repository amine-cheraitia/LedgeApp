FROM php:8.3-fpm

# Dépendances système
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# Répertoire de travail
WORKDIR /var/www

# Permissions
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

COPY --chown=www:www . /var/www

USER www

EXPOSE 9000
