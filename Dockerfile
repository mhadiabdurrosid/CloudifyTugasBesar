FROM dunglas/frankenphp:1.2-php8.3

# Install required PHP extensions
RUN install-php-extensions mysqli pdo pdo_mysql

# Copy application files
COPY . /app/public

# Set working directory
WORKDIR /app/public

# Create uploads directory
RUN mkdir -p /app/public/uploads && chmod 755 /app/public/uploads

# Run database setup
RUN php setup_users.php || true

# Expose port (FrankenPHP handles this automatically)
EXPOSE 80

# Start FrankenPHP
CMD ["frankenphp", "run"]
