# Use the official PHP image
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Install composer and dependencies, then copy app
RUN apt-get update && apt-get install -y git unzip && rm -rf /var/lib/apt/lists/*
COPY composer.json /app/composer.json
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
 && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
 && rm composer-setup.php \
 && composer install --no-dev --prefer-dist --no-interaction --working-dir=/app

COPY . /app

# Expose port
EXPOSE 10000

# Start PHP built-in server
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t ."]
