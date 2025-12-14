FROM php:8.2-apache

# Force prefork MPM (must come before PHP module)
RUN a2dismod mpm_event \
 && a2enmod mpm_prefork

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Serve /public as DocumentRoot safely
RUN mkdir -p /var/www/html/public \
 && rm -f /var/www/html/index.html \
 && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
