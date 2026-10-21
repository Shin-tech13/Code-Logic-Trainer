#!/bin/sh

echo "Running database migrations..."
php artisan migrate --force --seed

echo "Clearing and caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Starting Apache..."
apache2-foreground
