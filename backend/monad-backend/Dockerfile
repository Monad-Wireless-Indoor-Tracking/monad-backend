FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip

# Configure PHP
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies (production mode, skip scripts that need DB)
ENV APP_ENV=prod
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts && \
    composer dump-autoload --optimize

# Expose port
EXPOSE 8000

# Create entrypoint script
RUN echo '#!/bin/sh\n\
# Generate JWT keys if they dont exist\n\
if [ ! -f config/jwt/private.pem ]; then\n\
    echo "Generating JWT keypair..."\n\
    php bin/console lexik:jwt:generate-keypair --skip-if-exists\n\
fi\n\
\n\
# Start Symfony server\n\
exec symfony server:start --no-tls --port=8000 --allow-http --listen-ip=0.0.0.0\n\
' > /usr/local/bin/docker-entrypoint.sh && chmod +x /usr/local/bin/docker-entrypoint.sh

# Start Symfony server
CMD ["/usr/local/bin/docker-entrypoint.sh"]
