# Dockerfile — PHP Apache (final)
FROM php:8.2-apache

ARG CACHEBUSTER=1000001

# force cache-bust harmless layer
RUN echo "cachebust:${CACHEBUSTER}"

# Pastikan hanya satu MPM aktif (safety)
RUN set -eux; \
    a2dismod mpm_event mpm_worker || true; \
    a2enmod mpm_prefork || true; \
    rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf || true; \
    ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load || true; \
    ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf || true

# Enable mod_rewrite & .htaccess
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy app to webroot
COPY . /var/www/html/
WORKDIR /var/www/html

# Permissions
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html && chmod -R 775 /var/www/html/uploads

EXPOSE 80
CMD ["apache2-foreground"]
