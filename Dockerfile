FROM php:8.2-apache

# ----------------------------------
# Apache MPM FIX (IMPORTANT)
# ----------------------------------
RUN a2dismod mpm_event mpm_worker || true \
 && a2enmod mpm_prefork

# ----------------------------------
# PHP Extensions
# ----------------------------------
RUN docker-php-ext-install pdo pdo_mysql

# ----------------------------------
# Apache Modules
# ----------------------------------
RUN a2enmod rewrite

# ----------------------------------
# Working directory
# ----------------------------------
WORKDIR /var/www/html

# ----------------------------------
# Copy project files
# ----------------------------------
COPY . /var/www/html/

# ----------------------------------
# Make Apache serve /public
# ----------------------------------
RUN rm -rf /var/www/html/index.html \
 && sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# ----------------------------------
# Permissions
# ----------------------------------
RUN chown -R www-data:www-data /var/www/html

# ----------------------------------
# Expose port & start Apache
# ----------------------------------
EXPOSE 80
CMD ["apache2-foreground"]
