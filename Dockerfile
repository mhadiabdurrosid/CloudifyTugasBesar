FROM php:8.2-apache

# 🚀 Paksa Railway rebuild — ubah angka jika perlu
ARG CACHEBUSTER=202501

# --- APACHE CONFIG ---
RUN a2enmod rewrite

# FIX ERROR MPM: pilih hanya prefork (modul aman untuk PHP)
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork

# Bersihkan modul MPM lain agar tidak bentrok
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    && rm -f /etc/apache2/mods-enabled/mpm_*.conf \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Railway memakai random PORT → patch Apache
RUN sed -i "s/Listen 80/Listen \$PORT/" /etc/apache2/ports.conf \
    && sed -i "s/:80/:\$PORT/" /etc/apache2/sites-enabled/000-default.conf

# Allow .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# --- PHP EXTENSIONS ---
RUN docker-php-ext-install mysqli pdo pdo_mysql

# --- COPY PROJECT ---
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
