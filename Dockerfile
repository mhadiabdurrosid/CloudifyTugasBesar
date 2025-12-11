FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Configure Apache to listen on the PORT environment variable
RUN echo "Listen \${PORT:-8080}" > /etc/apache2/ports.conf && \
    sed -i 's/80/\${PORT:-8080}/g' /etc/apache2/sites-enabled/000-default.conf

# Set ServerName to prevent Apache warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/

# Create uploads directory
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads

# Run database setup
RUN php setup_users.php

# Expose port
EXPOSE ${PORT:-8080}

# Start Apache
CMD apache2-foreground
