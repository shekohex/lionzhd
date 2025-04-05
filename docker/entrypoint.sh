#!/bin/sh
set -e

# Create the database if it doesn't exist
# -----------------------------------------------------------
# Ensure the database is created before running migrations.
# -----------------------------------------------------------
touch /data/database/database.sqlite

# Run Laravel migrations
# -----------------------------------------------------------
# Ensure the database schema is up to date.
# -----------------------------------------------------------
php artisan migrate --no-interaction

# Run Our Custom Commands
# -----------------------------------------------------------
# Ensure the database is seeded with initial data.
# -----------------------------------------------------------
php artisan lionz:configure

# Restart the queue worker
# -----------------------------------------------------------
# Ensure the queue worker is restarted to pick up any changes.
# -----------------------------------------------------------
php artisan queue:restart

# Clear the cache
# -----------------------------------------------------------
# Ensure the application cache is cleared.
# -----------------------------------------------------------
php artisan cache:clear

# Optimize the application
# -----------------------------------------------------------
# Ensure the application is optimized for performance.
# -----------------------------------------------------------
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run default commands
exec "$@"
