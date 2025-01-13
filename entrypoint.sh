#!/bin/bash

# Clear caches
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Ensure the database exists and run migrations
echo "Running migrations..."
php artisan migrate --force

# Seed the database if necessary (optional)
if [ "$SEED_DATABASE" = "true" ]; then
  echo "Seeding the database..."
  php artisan db:seed --force
fi

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm
