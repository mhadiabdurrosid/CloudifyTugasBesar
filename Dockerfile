FROM dunglas/frankenphp:1.2-php8.3

RUN install-php-extensions mysqli pdo pdo_mysql

COPY . /app
WORKDIR /app

RUN chmod +x /app/start.sh

ENV PORT=8000
EXPOSE 8000

CMD ["/app/start.sh"]
