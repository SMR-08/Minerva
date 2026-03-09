FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Create system user to run Composer and Artisan Commands
# Arguments defined in docker-compose.yml
ARG user
ARG uid

# Create system user to match host user (fix permissions)
# If arguments are not provided, fallback to standard www-data or create new
RUN if [ -n "$user" ]; then \
    useradd -G www-data,root -u $uid -d /home/$user $user && \
    mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user; \
    fi

# Configure PHP-FPM
COPY ./docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
