FROM php:8.2-apache

# تثبيت متطلبات النظام وحزم PHP المطلوبة للارافل
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unifaul \
    git \
    curl \
    libonig-dev \
    libxml2-dev

# تمكين إضافات أباتشي و PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# إعداد المجلد الرئيسي للعمل داخل الحاوية
WORKDIR /var/www/html

# نسخ ملفات المشروع إلى الحاوية
COPY . /var/www/html

# ضبط مسار الوثيقة لجعل الأباتشي يشير إلى مجلد public الخاص بـ Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# تفعيل مود رايت (mod_rewrite) لأباتشي
RUN a2enmod rewrite

# ضبط الصلاحيات للمجلدات
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تثبيت حزم المشروع عبر Composer
RUN composer install --no-dev --optimize-autoloader