FROM php:8.2-apache

# -------------------------------
# Force prefork MPM (required for PHP module)
# -------------------------------
RUN a2dismod mpm_event \
 && rm -f /etc/apache2/mods-enabled/mpm_* \
 && a2enmod mpm_prefork

# -------------------------------
# Install PHP extensions
# -------------------------------
RUN docker-php-ext-install pdo pdo_mysql

# -------------------------------
# Enable Apache rewrite
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
RUN rm -rf /var/www/html/index.html \
 && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf \
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
# Start Apache in foreground
# -------------------------------
CMD ["apac]()
