FROM php:8.3-cli-bookworm

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libsqlite3-dev \
        unzip \
        zip \
    && docker-php-ext-install pdo pdo_sqlite \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN composer run-script post-autoload-dump \
    && npm run build \
    && chmod +x docker/start.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database/database.sqlite

EXPOSE 10000

CMD ["./docker/start.sh"]
