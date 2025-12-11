FROM dunglas/frankenphp:1.2-php8.3

RUN install-php-extensions mysqli pdo pdo_mysql

# Copy semua file Cloudify ke /app
COPY . /app

WORKDIR /app

RUN mkdir -p /app/uploads && chmod -R 775 /app/uploads
RUN chmod +x /app/start.sh

ENV PORT=80
EXPOSE 80

CMD ["/app/start.sh"]
