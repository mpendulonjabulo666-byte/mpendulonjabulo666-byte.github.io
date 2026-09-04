# Runs NutriTale with PHP's built-in server — the same command used
# throughout this app's own development/testing. No Apache/nginx vhost
# config needed: every page is a plain .php file hit directly (e.g.
# recipe.php?id=...), no rewrite rules required. Meant for Railway (see
# "Path C — Railway (for testing)" in DEPLOYMENT.md) but works on any
# platform that runs a Dockerfile and passes a PORT env var.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        pkg-config \
    && docker-php-ext-install pdo_mysql curl mbstring \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .

EXPOSE 8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
