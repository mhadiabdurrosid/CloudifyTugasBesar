FROM php:8.2-apache

# Enable Apache Rewrite
RUN a2enmod rewrite

# Allow .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project
COPY . /var/www/html/

# Adjust permissions
RUN chown -R www-data:www-data /var/www/html

# Railway uses $PORT
RUN sed -i "s/Listen 80/Listen \${PORT}/" /etc/apache2/ports.conf \
 && sed -i "s/:80/:${PORT}/" /etc/apache2/sites-enabled/000-default.conf

EXPOSE 80

CMD ["apache2-foreground"]
