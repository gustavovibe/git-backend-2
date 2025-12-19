# Use php-fpm base
FROM php:8.3-fpm

# prevent interactive apt prompts
ENV DEBIAN_FRONTEND=noninteractive

# Install nginx, php extensions and utilities (single RUN to keep layers small)
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
      nginx \
      gettext-base \
      ca-certificates \
      libpng-dev \
      libjpeg-dev \
      libfreetype6-dev \
      libxml2-dev \
      libzip-dev \
      zip \
      unzip \
      mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Set working dir and copy app
WORKDIR /var/www/html
COPY . /var/www/html

# Copy composer binary from official composer image
# (this is allowed: copy single binary from composer image)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP deps: prefer install (requires composer.lock present in repo)
# --no-interaction to avoid prompts
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --working-dir=/var/www/html

# Copy nginx config template and start script
COPY nginx.conf.template /etc/nginx/nginx.conf.template
COPY start.sh /start.sh
RUN chmod +x /start.sh

# Ensure proper permissions (adjust as needed)
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true

# Expose and set default PORT env
ENV PORT=8080
EXPOSE 8080


# ...
CMD ["/start.sh"]
