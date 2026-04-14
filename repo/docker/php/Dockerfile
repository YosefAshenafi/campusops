FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libzip-dev \
    openssl-dev \
    icu-dev \
    fcgi

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        gd \
        bcmath \
        zip \
        intl \
        opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# PHP-FPM healthcheck script
RUN echo '#!/bin/sh' > /usr/local/bin/php-fpm-healthcheck \
    && echo 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 > /dev/null 2>&1' >> /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck

# Configure PHP-FPM ping endpoint for healthcheck
RUN echo '[www]' > /usr/local/etc/php-fpm.d/zz-healthcheck.conf \
    && echo 'ping.path = /ping' >> /usr/local/etc/php-fpm.d/zz-healthcheck.conf \
    && echo 'ping.response = pong' >> /usr/local/etc/php-fpm.d/zz-healthcheck.conf

WORKDIR /var/www/backend

# Set proper permissions
RUN chown -R www-data:www-data /var/www

EXPOSE 9000

CMD ["php-fpm"]
