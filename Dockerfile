FROM php:8.2-apache

# Install dependencies for Laravel and MongoDB
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Fix Apache MPM conflict on Railway and enable mod_rewrite
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite headers

# Update Apache DocumentRoot to point to Laravel's public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy ONLY composer files first so Docker caches all 91 PHP packages across deployments
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./

# Install PHP dependencies via Composer (cached layer + git fallback if GitHub zipball API returns 504)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction \
    || composer install --no-dev --no-scripts --no-autoloader --prefer-source --no-interaction

# Copy the rest of the application
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize --no-dev

# Set proper permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80 8000

CMD ["sh", "-c", "a2dismod mpm_event mpm_worker 2>/dev/null || true; a2enmod mpm_prefork 2>/dev/null || true; if [ -n \"$PORT\" ] && [ \"$PORT\" != \"80\" ]; then sed -i \"s/Listen 80/Listen 80\\nListen $PORT/g\" /etc/apache2/ports.conf; sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:80 *:$PORT>/g\" /etc/apache2/sites-available/000-default.conf; fi; apache2-foreground"]
