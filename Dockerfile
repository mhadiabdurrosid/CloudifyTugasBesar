FROM dunglas/frankenphp:1.2-php8.3

# Install required PHP extensions
RUN install-php-extensions mysqli pdo pdo_mysql

# Copy application files to /app (NOT /app/public)
COPY . /app

# Set working directory
WORKDIR /app

# Fix permissions
RUN mkdir -p /app/uploads && chmod -R 755 /app/uploads && \
    chmod +x /app/start.sh

# Set default PORT (Railway overrides this)
ENV PORT=80

EXPOSE 80

# Start FrankenPHP using custom start.sh
CMD ["/app/start.sh"]
