# syntax=docker/dockerfile:1.6

# =============================================================================
# Stage 1: composer install (gera vendor/ sem rodar scripts nem autoload).
# Separado pra ser cacheado quando composer.json/lock não mudam.
# =============================================================================
FROM composer:2 AS composer-deps

WORKDIR /app
COPY composer.json composer.lock ./
# --no-scripts: não roda artisan que tenta acessar .env / app/ que ainda não existem
# --no-autoloader: deixa o dump pra depois, quando app/ estiver presente
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# =============================================================================
# Stage 2: build do frontend com Node + Vite.
# Precisa do vendor (Ziggy é PHP-vendored mas importado pelo JS via Vite).
# =============================================================================
FROM node:20-alpine AS frontend

ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME=http

ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY jsconfig.json* ./
COPY routes ./routes
# Ziggy é PHP vendored mas é importado pelo app.js via '../../vendor/tightenco/ziggy'.
# Sem essa cópia, npm run build falha com "Could not resolve ../../vendor/...".
COPY --from=composer-deps /app/vendor ./vendor

RUN npm run build

# =============================================================================
# Stage 3: runtime PHP 8.4 CLI Alpine (php artisan serve / reverb / queue).
# 8.4 alinha com a versão usada pra gerar o composer.lock — sem isso,
# composer dump-autoload falha com "Composer dependencies require PHP >= 8.4.0".
# =============================================================================
FROM php:8.4-cli-alpine AS runtime

# Extensões PHP:
# - pdo_pgsql: Postgres
# - redis (PECL): cache/queue/session
# - gd, exif: imagens
# - zip, mbstring, bcmath, pcntl, intl: padrão Laravel
RUN apk add --no-cache \
        bash git curl tini \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        libzip-dev oniguruma-dev postgresql-dev icu-dev \
    && apk add --no-cache --virtual .build-deps autoconf gcc g++ make linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_pgsql zip mbstring exif pcntl bcmath intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 1) Código fonte (sem vendor — bloqueado pelo .dockerignore)
COPY . .

# 2) Vendor já instalado pelo stage composer-deps
COPY --from=composer-deps /app/vendor ./vendor

# 3) Build do Vite gerado pelo stage frontend
COPY --from=frontend /app/public/build ./public/build

# 4) Regenera o autoload otimizado agora que app/ está disponível.
#    Esse é o passo que o --no-autoloader do stage 1 deixou pendente.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && composer clear-cache

# Storage gravável (UID 1000 alinhado com volumes do host).
RUN chown -R 1000:1000 storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000 8080

# tini encaminha SIGTERM corretamente — shutdown limpo no docker compose down.
ENTRYPOINT ["/sbin/tini", "--"]

# Default: HTTP server. Compose sobrescreve pros containers reverb e queue.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--workers=4"]
