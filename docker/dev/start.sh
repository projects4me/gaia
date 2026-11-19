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

# Start Apache2 in foreground, with the scheduler for docker/cron/crontab beside it
echo "Starting Apache2..."
exec /bin/bash /var/www/html/docker/cron/scheduler.sh apache2-foreground
