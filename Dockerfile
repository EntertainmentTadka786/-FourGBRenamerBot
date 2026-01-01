# ===== BASE IMAGE =====
FROM php:8.2-apache

# ===== INSTALL SYSTEM DEPENDENCIES =====
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    ffmpeg \
    libmagickwand-dev \
    libzip-dev \
    wget \
    nano \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ===== INSTALL PHP EXTENSIONS =====
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    sockets \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# ===== INSTALL COMPOSER =====
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ===== SET WORKING DIRECTORY =====
WORKDIR /var/www/html

# ===== COPY APPLICATION FILES =====
COPY . .

# ===== INSTALL DEPENDENCIES =====
# First, generate composer.json if missing
RUN if [ ! -f composer.json ]; then \
    echo '{"name":"video-renamer-bot","require":{"danog/madelineproto":"^8.0","vlucas/phpdotenv":"^5.5"}}' > composer.json; \
    fi

# Install without scripts to avoid errors
RUN composer install --no-dev --no-scripts --optimize-autoloader

# ===== SET PERMISSIONS =====
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p downloads thumbs logs \
    && chmod -R 777 downloads thumbs logs

# ===== APACHE CONFIGURATION =====
RUN a2enmod rewrite headers expires deflate \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ===== HEALTH CHECK =====
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# ===== EXPOSE PORT =====
EXPOSE 80

# ===== START APACHE =====
CMD ["apache2-foreground"]
