# Base image
FROM php:8.2-apache

# -------------------------------
# FORCE only one Apache MPM
# -------------------------------
RUN rm -f /etc/apache2/mods-enabled/mpm_* \
 && a2enmod mpm_prefork

# -------------------------------
# Install PHP extensions
# -------------------------------
RUN docker-php-ext-install pdo pdo_mysql

# -------------------------------
# Enable required Apache modules
# -------------------------------
RUN a2enmod rewrite headers

# -------------------------------
# Set working directory
# -------------------------------
WORKDIR /var/www/html

# -------------------------------
# Copy project files
# -------------------------------
COPY . /var/www/html/

# -------------------------------
# Serve /public as DocumentRoot
# -------------------------------
RUN rm -f /var/www/html/index.html \
 && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
 && mkdir -p /var/www/html/public

# -------------------------------
# Set permissions
# -------------------------------
RUN chown -R www-data:www-data /var/www/html

# -------------------------------
# Expose port
# -------------------------------
EXPOSE 80

# -------------------------------
# Start Apache in foreground with debug logs
# -------------------------------
CMD ["apache2ctl", "-DFOREGROUND", "-e", "debug"]
