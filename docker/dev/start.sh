#!/bin/bash

# Exit on any error
set -e

echo "Starting Gaia application..."

# Change to the application directory
cd /var/www/html

# Run database migrations
echo "Running database migrations..."
php cli.php migration migrate

# Check if migration was successful
if [ $? -eq 0 ]; then
    echo "Database migrations completed successfully"
else
    echo "Database migration failed"
    exit 1
fi

# Store the semantic search text and hash of records that have none or an outdated one. A failure is logged and
# the container still starts: search reindex can be run by hand later.
echo "Backfilling semantic search text..."
if php cli.php search backfill; then
    echo "Semantic search backfill completed"
else
    echo "Semantic search backfill failed (exit $?); continuing"
fi

# Start Apache2 in foreground
echo "Starting Apache2..."
exec apache2-foreground
