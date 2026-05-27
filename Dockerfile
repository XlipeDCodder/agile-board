# syntax=docker/dockerfile:1.6

# =============================================================================
# Stage 1: build do frontend com Node + Vite
# =============================================================================
# Os VITE_REVERB_* SÃO RESOLVIDOS EM BUILD-TIME (Vite injeta esses valores
# no bundle final). Pra trocar o IP/porta do Reverb visto pelo navegador,
# precisa rebuildar a imagem. Por isso os args.
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

RUN npm run build

# =============================================================================
# Stage 2: runtime PHP 8.3 CLI (php artisan serve + reverb + queue)
# =============================================================================
FROM php:8.3-cli-alpine AS runtime

# Extensões PHP necessárias:
# - pdo_pgsql: conexão com Postgres
# - redis (PECL): cache/queue/session
# - gd, exif: thumbnails/imagens em uploads
# - zip, mbstring, bcmath, pcntl: padrão Laravel
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

# Copia código fonte (respeitando .dockerignore)
COPY . .
# Traz o build do front gerado no stage anterior
COPY --from=frontend /app/public/build ./public/build

# Composer em modo produção: sem dev deps, autoload otimizado
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
    && composer clear-cache

# Storage precisa ser gravável pelo PHP. Usamos UID/GID 1000 (default
# em distribuições desktop) pra alinhar com volumes mapeados do host.
RUN chown -R 1000:1000 storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000 8080

# tini garante que SIGTERM é encaminhado corretamente pros processos PHP,
# permitindo shutdown limpo no docker-compose down.
ENTRYPOINT ["/sbin/tini", "--"]

# Default: roda o servidor HTTP. O docker-compose sobrescreve esse CMD
# pros containers de reverb e queue worker.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--workers=4"]
