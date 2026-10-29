FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip libpng-dev libjpeg-dev libfreetype6-dev \
    libxml2-dev libicu-dev libxslt1-dev libsodium-dev git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl xsl soap zip mysqli pdo pdo_mysql opcache sodium exif \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'upload_max_filesize = 1100M'; \
        echo 'post_max_size = 1100M'; \
        echo 'max_execution_time = 900'; \
        echo 'max_input_time = 900'; \
        echo 'memory_limit = 512M'; \
        echo 'max_input_vars = 5000'; \
        echo 'opcache.revalidate_freq = 0'; \
    } > /usr/local/etc/php/conf.d/moodle.ini \
 && echo 'LimitRequestBody 0' > /etc/apache2/conf-enabled/upload-limit.conf
