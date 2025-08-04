# Usa una imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instala extensiones necesarias y ffmpeg y jq
RUN apt-get update && \
    apt-get install -y ffmpeg unzip jq && \
    docker-php-ext-install mysqli

RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Copia el contenido del proyecto al directorio de Apache
COPY . /var/www/html/

# Da permisos al proyecto
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Da permisos al script de reinicio
RUN chmod +x /var/www/html/scripts/auto_restart_streams.sh

# Expone el puerto 80
EXPOSE 80

# Ejecuta el script de reinicio y luego Apache
CMD ["/bin/bash", "-c", "/var/www/html/scripts/auto_restart_streams.sh && apache2-foreground"]
