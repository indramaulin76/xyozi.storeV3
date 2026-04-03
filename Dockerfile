# ===================================================================
# PHP 8.2 Apache Dockerfile - CodeIgniter 4 Compatible
# ===================================================================

FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libicu-dev \
    libfreetype-dev \
    libjpeg-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by CodeIgniter 4
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    gd \
    exif

# Install intl extension separately (needs ICU libraries)
RUN docker-php-ext-install intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# ===================================================================
# Document Root Security - CodeIgniter 4
# ===================================================================
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
# ===================================================================

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

# Set permissions
RUN mkdir -p /var/www/html/writable /var/www/html/public \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/writable \
    && chmod -R 755 /var/www/html/public

# Configure PHP
RUN echo "[PHP]" > /usr/local/etc/php/conf.d/local.ini && \
    echo "display_errors = Off" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "post_max_filesize = 25M" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/local.ini && \
    echo "date.timezone = Asia/Jakarta" >> /usr/local/etc/php/conf.d/local.ini

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
