FROM dunglas/frankenphp:1.2-php8.3

# Install required PHP extensions
RUN install-php-extensions mysqli pdo pdo_mysql

# Copy application files
COPY . /app/public

# Set working directory
WORKDIR /app/public

# Create uploads directory and make start.sh executable
RUN mkdir -p /app/public/uploads && chmod 755 /app/public/uploads && \
    chmod +x /app/public/start.sh

# Set default PORT for Railway
ENV PORT=80

# Expose port (Railway will override this with its own PORT)
EXPOSE 80

# Use start.sh to initialize database and start FrankenPHP
CMD ["/app/public/start.sh"]
