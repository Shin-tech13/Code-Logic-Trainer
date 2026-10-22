#!/bin/sh
set -e

echo "Preparing Laravel writable directories..."
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache

echo "Running database migrations..."
su -s /bin/sh www-data -c 'php artisan migrate --force --seed'

echo "Clearing and caching configuration..."
su -s /bin/sh www-data -c 'php artisan config:cache'
su -s /bin/sh www-data -c 'php artisan route:cache'
su -s /bin/sh www-data -c 'php artisan view:cache'

echo "Starting Apache..."
apache2-foreground
