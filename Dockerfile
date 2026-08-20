FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql mysqli zip \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

# نسخ ملفات التطبيق
COPY . /var/www/html/

# تأمين صلاحيات
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

# تنظيف
RUN rm -rf /var/lib/apt/lists/*

EXPOSE 80

# Render يستخدم $PORT
ENV PORT=80

CMD ["apache2-foreground"]
