# Use official PHP-Apache image
FROM php:8.2-apache

# Enable extensions
RUN docker-php-ext-install pdo pdo_mysql

# Copy project
COPY . /var/www/html/

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html/

# Permissions (optional but safe)
RUN chown -R www-data:www-data /var/www/html
