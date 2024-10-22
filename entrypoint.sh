#!/bin/sh

set -e

echo "Collecting static files..."
python manage.py collectstatic --noinput
echo "Static files collected"

echo "Running migrations..."
python manage.py migrate
echo "Migrations complete"

echo "Creating superuser..."
python manage.py createsuperuser --noinput --username $DJANGO_SUPERUSER_USERNAME --email $DJANGO_SUPERUSER_EMAIL || echo "Superuser already exists.. Skipping"

exec "$@"
