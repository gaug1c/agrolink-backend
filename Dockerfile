FROM php:8.2-fpm

# Installer les dépendances système
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libssl-dev \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Installer l'extension MongoDB et PHP extensions
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
RUN docker-php-ext-install pdo pdo_mysql opcache

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /app

# Copier tout le projet **avant** de lancer composer
COPY . .

# Installer les dépendances PHP
# Autoriser l'exécution en root pour Composer si nécessaire
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Exposer le port PHP-FPM (optionnel)
EXPOSE 9000

# Commande par défaut
CMD ["php-fpm"]
