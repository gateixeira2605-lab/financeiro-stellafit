FROM composer:2 AS vendor

WORKDIR /app
COPY public_html/composer.json ./composer.json
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.2-apache

# Extensões necessárias para MySQL, uploads e geração de PDFs.
RUN apt-get update && apt-get install -y libonig-dev libxml2-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install mysqli pdo pdo_mysql mbstring dom

# Permite que o .htaccess e as regras de reescrita sejam utilizados.
RUN a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# public_html é a raiz pública do projeto.
COPY public_html/ /var/www/html/
COPY --from=vendor /app/vendor/ /var/www/html/vendor/

RUN mkdir -p /var/www/html/uploads /var/www/html/pdf \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/html/uploads /var/www/html/pdf

EXPOSE 80
