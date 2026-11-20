#!/bin/bash

# Exit on any error
set -e

echo "Starting Gaia application..."

# Change to the application directory
cd /var/www/html

# Run database migrations
echo "Running database migrations..."
su -s /bin/sh www-data -c "php cli.php migration migrate"

# Check if migration was successful
if [ $? -eq 0 ]; then
    echo "Database migrations completed successfully"
else
    echo "Database migration failed"
    exit 1
fi

# CLI steps run as www-data so the files they create (logs/db.log) belong to Apache.
# Store the semantic search text and hash of records that have none or an outdated one. A failure is logged and
# the container still starts: search reindex can be run by hand later.
echo "Backfilling semantic search text..."
if su -s /bin/sh www-data -c "php cli.php search backfill"; then
    echo "Semantic search backfill completed"
else
    echo "Semantic search backfill failed (exit $?); continuing"
fi

# Anything created while running as root must still be writable for Apache (agaia-4772).
chown -R www-data:www-data /var/www/html/logs

# Start Apache2 in foreground
echo "Starting Apache2..."
exec apache2-foreground
