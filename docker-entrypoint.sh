#!/bin/bash
set -e

# Initialize persistent disk directories if they don't exist
if [ -d "/var/data/uploads" ]; then
  echo "Initializing persistent disk directories..."
  mkdir -p /var/data/uploads/ids \
           /var/data/uploads/insurance \
           /var/data/uploads/notes \
           /var/data/uploads/aob \
           /var/data/uploads/rx

  # Set proper ownership and permissions
  chown -R www-data:www-data /var/data/uploads
  chmod -R 755 /var/data/uploads

  echo "Persistent disk directories initialized successfully"
else
  echo "Persistent disk not found at /var/data/uploads (using local uploads/)"
fi

# Set up cron jobs
echo "Setting up cron jobs..."
# Copy crontab file
cp /var/www/html/crontab /etc/cron.d/collagendirect-cron
# Set proper permissions
chmod 0644 /etc/cron.d/collagendirect-cron
# Create log file
touch /var/log/cron.log
chmod 0666 /var/log/cron.log
# Apply cron job
crontab /etc/cron.d/collagendirect-cron
# Start cron service in background
cron
echo "Cron jobs initialized successfully"

# Start Apache in foreground
exec apache2-foreground
