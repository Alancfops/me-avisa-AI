# Bases oficiais multi-arquitetura: a mesma receita serve amd64 e arm64.
FROM node:22 AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc vite.config.ts tsconfig.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY public ./public
RUN VITE_APP_NAME="Me Avisa Aí" npm run build

FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Mesmo UID do dono do projeto, para gravar nos arquivos montados pelo Compose.
ARG APP_UID=1000
RUN useradd --create-home --uid "${APP_UID}" app

# O usuário entra antes das cópias: um chown -R depois duplicaria o vendor em outra camada.
WORKDIR /app
RUN chown app:app /app
USER app
COPY --chown=app:app composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=app:app . .
COPY --chown=app:app --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-interaction

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
