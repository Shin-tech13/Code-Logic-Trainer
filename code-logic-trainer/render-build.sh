#!/usr/bin/env bash
# exit on error
set -o errexit

# Install composer dependencies
composer install --no-dev --optimize-autoloader

# Run database migrations and seeders safely on production
php artisan migrate --force
php artisan db:seed --class=CodeChallengeSeeder --force

# Cache routes and configuration for fast loading
php artisan config:cache
php artisan route:cache
php artisan view:cache
