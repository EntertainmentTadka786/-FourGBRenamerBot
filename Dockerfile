# ===== MULTI-STAGE BUILD =====
FROM php:8.2-apache AS builder

# Install system dependencies
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
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p downloads thumbs logs \
    && chmod -R 777 downloads thumbs logs

# Enable Apache modules
RUN a2enmod rewrite headers expires deflate

# ===== PRODUCTION IMAGE =====
FROM php:8.2-apache

# Copy from builder
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /var/www/html /var/www/html
COPY --from=builder /usr/bin/composer /usr/bin/composer

# Install runtime dependencies only
RUN apt-get update && apt-get install -y \
    ffmpeg \
    libmagickwand-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set up Apache
RUN a2enmod rewrite headers expires deflate \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/default-ssl.conf

# Create non-root user
RUN useradd -m -u 1000 -s /bin/bash appuser \
    && chown -R appuser:appuser /var/www/html

# Switch to non-root user
USER appuser

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose port
EXPOSE 80
EXPOSE 443

# Start Apache
CMD ["apache2-foreground"]