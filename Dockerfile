# Base image: PHP + Apache prefork-compatible
FROM php:8.2-apache-bullseye

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Install PHP dependencies if composer.json exists
RUN if [ -f composer.json ]; then composer install --no-dev --optimize-autoloader; fi

# Serve /public as DocumentRoot safely
RUN mkdir -p /var/www/html/public \
 && rm -f /var/www/html/index.html \
 && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
 && find /var/www/html/public -type d -exec chmod 755 {} \; \
 && find /var/www/html/public -type f -exec chmod 644 {} \;

# Expose port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
