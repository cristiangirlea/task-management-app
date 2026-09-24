#!/bin/sh
# Production entrypoint. Caches are built here rather than in the image
# because they capture the environment (config) the container starts with.
# Migrations run on every start; they are a no-op when nothing is pending.
# Never seeds: the seeders need dev-only packages and create a demo account.
set -e

if [ -n "$STRIPE_SECRET" ] && [ -z "$STRIPE_WEBHOOK_SECRET" ]; then
    echo "warning: STRIPE_WEBHOOK_SECRET is empty, so the Stripe webhook is disabled and plans will not update." >&2
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan migrate --force

exec "$@"
