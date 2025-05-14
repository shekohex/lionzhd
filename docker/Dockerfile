# syntax=docker/dockerfile:experimental
# Frontend stage
FROM node:22-alpine as frontend
WORKDIR /app
RUN npm install -g pnpm
COPY package*.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY ./ /app/
ARG ENVIRONMENT=production
ARG APP_NAME=Lionz
ENV VITE_APP_NAME=$APP_NAME
ENV APP_ENV=$ENVIRONMENT
ENV TELESCOPE_ENABLED=false
RUN pnpm build

# Base stage
FROM dunglas/frankenphp:1.5.0-php8.4.5-bookworm as base

# Install PHP Extensions
RUN install-php-extensions \
    pdo \
    pdo_mysql \
    gd \
    bcmath \
    redis \
    intl \
    zip \
    gmp \
    apcu \
    opcache \
    memcached \
    imagick \
    sockets \
    pcntl \
    zstd \
    @composer

# Update PHP configurations
RUN sed -e 's/^expose_php.*/expose_php = Off/' "$PHP_INI_DIR/php.ini-production" > "$PHP_INI_DIR/php.ini" \
    && sed -i -e 's/^upload_max_filesize.*/upload_max_filesize = 600M/' -e 's/^post_max_size.*/post_max_size = 0/' \
    -e 's/^zend.exception_ignore_args.*/zend.exception_ignore_args = Off/' \
    -e 's/^max_execution_time.*/max_execution_time = 120/' \
    -e 's/^max_input_time.*/max_input_time = 60/' \
    -e 's/^memory_limit.*/memory_limit = 1024M/' "$PHP_INI_DIR/php.ini"

# Set some build ENV variables
ENV LOG_CHANNEL=stdout
ENV CACHE_DRIVER=null
ENV BROADCAST_DRIVER=log
ENV QUEUE_CONNECTION=database

ENV CADDYFILE_PATH=/lionz/Caddyfile

# Set environment
ARG ENVIRONMENT=production
ENV APP_ENV=$ENVIRONMENT

# Create /lionz directory and set correct permissions
RUN mkdir -p /lionz && chown -R www-data:www-data /lionz

# Set working directory
WORKDIR /lionz

# Prepare composer cache directory
RUN mkdir -p /var/www/.cache/composer && chown -R www-data:www-data /var/www/.cache/composer

# Optimize Composer Dependency Installation
COPY --chown=www-data:www-data ./composer.json ./composer.lock /lionz/

# Pre-install Composer dependencies
RUN su www-data -s /bin/sh -c "composer install --no-scripts --optimize-autoloader --no-dev --no-cache"

# Setup application
COPY --chown=www-data:www-data ./ /lionz/

# Dump autoload
RUN su www-data -s /bin/sh -c "composer dumpautoload"

# Setup composer root directory
RUN mkdir -p /root/.composer
RUN mkdir -p /lionz/.composer && chown www-data:www-data /lionz/.composer

# Setup logging
RUN mkdir -p /lionz/storage/logs/ && touch /lionz/storage/logs/laravel-$(date +'%Y-%m-%d').log
RUN chown -R www-data:www-data /lionz/storage
RUN chmod -R 755 /lionz/storage

# Copy the initialization script
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy Caddyfile
COPY --chown=www-data:www-data ./docker/Caddyfile $CADDYFILE_PATH

# Copy frontend build
COPY --chown=www-data:www-data --from=frontend /app/public/build /lionz/public/build

# Scheduler stage
FROM base as scheduler
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "schedule:work", "--no-interaction"]

# Queue stage
FROM base as queue
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "queue:work", "--memory=2048", "--timeout=0", "--tries=3", "--no-interaction"]

# Application stage
FROM base as app
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
EXPOSE 80 443
HEALTHCHECK --interval=10m --timeout=30s --start-period=10s --retries=5 CMD [ "curl", "-f", "http://localhost/up" ]
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--caddyfile=/lionz/Caddyfile", "--no-interaction"]
