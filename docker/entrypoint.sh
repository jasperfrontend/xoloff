#!/bin/sh
set -e

# Boot steps that must happen once per container, before anything serves.
#
# Each is behind a flag so a second role added later (a worker, a scheduler)
# can run the same image without racing this one to migrate the database.
# Today there is exactly one role and it does both.

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "==> migrating"
    # Shipping a migration IS the deployment step. DB::prohibitDestructiveCommands
    # is on in production, so migrate:fresh would refuse here - that is correct
    # and should stay.
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_OPTIMIZE:-false}" = "true" ]; then
    echo "==> caching config, routes and views"
    # Cleared first: the image may carry caches built at a different commit,
    # and a stale route cache fails in ways that look like a routing bug.
    php artisan optimize:clear
    php artisan optimize
fi

exec "$@"
