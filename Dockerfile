# FINAL Dockerfile untuk deploy Cloudify (PHP Native + Apache)

FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy Cloudify project ke webroot
COPY . /var/www/html/

# Set workdir
WORKDIR /var/www/html

# Uploads folder permission
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/uploads

EXPOSE 80

CMD ["apache2-foreground"]
