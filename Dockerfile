FROM dunglas/frankenphp:1.2-php8.3

RUN install-php-extensions mysqli pdo pdo_mysql

COPY . /

WORKDIR /

ENV PORT=80
EXPOSE 80

CMD ["./start.sh"]
