FROM php:8.4-fpm

RUN apt-get update --fix-missing

RUN apt-get install -y --no-install-recommends \
    apt-utils \
    vim \
    curl \
    debconf \
    subversion \
    git \
    apt-transport-https

RUN apt-get install -y --no-install-recommends \
    build-essential \
    locales \
    acl \
    mailutils \
    wget \
    zip \
    unzip

RUN apt-get install -y --no-install-recommends \
    gnupg \
    gnupg1 \
    gnupg2

RUN apt-get install -y --no-install-recommends \
    iputils-ping \
    zlib1g-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev

# Install Node.js
# RUN apt-get remove cmdtest -y cmdtest yarn
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash -

# RUN curl -sS https://get.symfony.com/cli/installer | bash \
#     && mv /root/.symfony/bin/symfony /usr/local/bin

RUN apt-get install -y nodejs
RUN npm -g install yarn

# PHP extensions
RUN docker-php-ext-install pdo_mysql

# GD with JPEG, FreeType and WebP support
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install gd

# ZIP
RUN apt-get install -y \
        libzip-dev \
        zip \
    && docker-php-ext-install zip

# Intl
RUN apt-get install -y \
        zlib1g-dev \
        libicu-dev \
        g++ \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-enable intl

# Override/add php.ini config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY custom.php.ini $PHP_INI_DIR/conf.d/

# Override FPM config
COPY php-fpm-pool.conf /usr/local/etc/php-fpm.d/

# Composer
RUN curl -sS https://getcomposer.org/installer | php \
    -- --install-dir=/usr/local/bin \
    --filename=composer

RUN composer self-update --2
RUN composer --version

# APCu
RUN pecl channel-update pecl.php.net
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# OPcache
RUN docker-php-ext-enable opcache

WORKDIR /var/www/app

ARG USER_ID
ARG GROUP_ID

RUN if [ ${USER_ID:-0} -ne 0 ] && [ ${GROUP_ID:-0} -ne 0 ]; then \
    userdel -f www-data && \
    if getent group www-data ; then groupdel www-data; fi && \
    groupadd -g ${GROUP_ID} www-data && \
    useradd -l -u ${USER_ID} -g www-data www-data && \
    install -d -m 0755 -o www-data -g www-data /home/www-data && \
    chown --changes --silent --no-dereference --recursive \
        --from=33:33 ${USER_ID}:${GROUP_ID} \
        /home/www-data \
        /var/www \
        /var/www/app \
; fi

EXPOSE 9000

USER www-data

CMD ["php-fpm"]