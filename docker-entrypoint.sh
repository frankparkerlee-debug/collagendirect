#!/bin/bash
set -e

# Initialize persistent disk directories if they don't exist
# Render mounts the disk at /var/www/html/uploads
if [ -d "/var/www/html/uploads" ]; then
  echo "Initializing persistent disk directories..."
  mkdir -p /var/www/html/uploads/ids \
           /var/www/html/uploads/insurance \
           /var/www/html/uploads/notes \
           /var/www/html/uploads/aob \
           /var/www/html/uploads/rx \
           /var/www/html/uploads/wound-photos

  # Set proper ownership and permissions
  chown -R www-data:www-data /var/www/html/uploads
  chmod -R 755 /var/www/html/uploads

  echo "Persistent disk directories initialized at /var/www/html/uploads"
else
  echo "WARNING: Persistent disk not found at /var/www/html/uploads"
  echo "Creating local uploads directory (files will be lost on container restart)"
  mkdir -p /var/www/html/uploads/ids \
           /var/www/html/uploads/insurance \
           /var/www/html/uploads/notes \
           /var/www/html/uploads/aob \
           /var/www/html/uploads/rx \
           /var/www/html/uploads/wound-photos
  chown -R www-data:www-data /var/www/html/uploads
  chmod -R 755 /var/www/html/uploads
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
