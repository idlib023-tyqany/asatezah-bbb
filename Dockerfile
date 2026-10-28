FROM php:8.2-apache

# تثبيت متطلبات النظام وحزم PHP المطلوبة للارافل
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    libonig-dev \
    libxml2-dev

# تمكين تفعيل إعادة كتابة الروابط في أباتشي
RUN a2enmod rewrite

# ضبط مجلد العمل الافتراضي
WORKDIR /var/www/html

# نسخ ملفات المشروع إلى الحاوية
COPY . /var/www/html

# ضبط صلاحيات مجلدات التخزين والكاش
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تثبيت حزم المشروع عبر Composer بدون ملفات التطوير
RUN composer install --no-dev --optimize-autoloader

# تعديل مسار المستندات في أباتشي ليشير إلى مجلد public الخاص بلارافل
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -s 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -s 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# فتح البورت 80
EXPOSE 80

# تشغيل خادم أباتشي
CMD ["apache2-foreground"]