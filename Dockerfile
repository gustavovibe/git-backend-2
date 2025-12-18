FROM php:8.3-fpm

# Install required dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip

WORKDIR /var/www/html

# Copy app code into the container
COPY . .

# Copy composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer update
RUN composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

# Set proper file permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Start PHP-FPM (this is handled by the base image, so no need for `php-fpm` here)
CMD ["php-fpm"]

# La seccion From usa una imagen php pero con soporte FPM.
# La seccion Run se encarga de instalar las dependencias y extensiones php
# El workdir indica el directorio principal de trabajao para la aplicacion
# La seccion Copy copia el codigo duente al contenedor, usando composer
# desde una imagen base para gestionar dependencias
# El primer Run sirve para instalar las dependencias de composer
# La segunda seccion de RUn habilita los permisos de lectura y escritura
# la seccion CMD define php-fpm como el proceso principal del contenedor.

#Construye la imagen sel servicio app que ejecuta php ára la logica de la aplicacion laravel.
