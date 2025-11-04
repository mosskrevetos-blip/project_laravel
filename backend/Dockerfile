# Файл: e-commerce-project/backend/Dockerfile

FROM php:8.3-fpm

# Устанавливаем нужные инструменты и расширения для PHP
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo pdo_mysql zip

# Устанавливаем Composer - менеджер пакетов для PHP
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Копируем наш кастомный .ini файл в папку, где PHP ищет доп. настройки
COPY php-custom.ini /usr/local/etc/php/conf.d/php-custom.ini
# ---

WORKDIR /var/www/html

# Копируем существующие файлы проекта
COPY . .

# Устанавливаем зависимости
RUN composer install --no-interaction --no-plugins --no-scripts --prefer-dist

# Меняем владельца файлов на www-data
RUN chown -R www-data:www-data /var/www/html

# Открываем порт
EXPOSE 9000