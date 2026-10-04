#!/bin/sh
# Production entrypoint. Caches are built here rather than in the image
# because they capture the environment (config) the container starts with.
# The web container (php-fpm) runs migrations on every start; they are a
# no-op when nothing is pending. Other containers from this image, such as
# the scheduler, skip them so two never migrate at once.
# Never seeds: the seeders need dev-only packages and create a demo account.
set -e

if [ -n "$STRIPE_SECRET" ] && [ -z "$STRIPE_WEBHOOK_SECRET" ]; then
    echo "warning: STRIPE_WEBHOOK_SECRET is empty, so the Stripe webhook is disabled and plans will not update." >&2
fi

if { [ -z "$PASSPORT_PRIVATE_KEY" ] || [ -z "$PASSPORT_PUBLIC_KEY" ]; } && [ ! -f storage/oauth-private.key ]; then
    echo "warning: PASSPORT_PRIVATE_KEY or PASSPORT_PUBLIC_KEY is empty, so OAuth for MCP clients is off; the MCP server takes API tokens only." >&2
fi

if [ "$BROADCAST_CONNECTION" = "reverb" ] && { [ -z "$REVERB_APP_ID" ] || [ -z "$REVERB_APP_KEY" ] || [ -z "$REVERB_APP_SECRET" ]; }; then
    echo "error: BROADCAST_CONNECTION is reverb, but REVERB_APP_ID, REVERB_APP_KEY or REVERB_APP_SECRET is empty. Set all three, or set BROADCAST_CONNECTION=null to turn live board updates off." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
if [ "$1" = "php-fpm" ]; then
    php artisan migrate --force
fi

exec "$@"
