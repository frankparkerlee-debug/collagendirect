#!/bin/bash
set -e

# Initialize persistent disk directories if they don't exist
# Render mounts the disk at /opt/render/project/src/uploads
if [ -d "/opt/render/project/src/uploads" ]; then
  echo "Initializing persistent disk directories..."
  mkdir -p /opt/render/project/src/uploads/ids \
           /opt/render/project/src/uploads/insurance \
           /opt/render/project/src/uploads/notes \
           /opt/render/project/src/uploads/aob \
           /opt/render/project/src/uploads/rx \
           /opt/render/project/src/uploads/wound-photos

  # Set proper ownership and permissions
  chown -R www-data:www-data /opt/render/project/src/uploads
  chmod -R 755 /opt/render/project/src/uploads

  echo "Persistent disk directories initialized at /opt/render/project/src/uploads"
else
  echo "WARNING: Persistent disk not found at /opt/render/project/src/uploads"
  echo "Creating local uploads directory (files will be lost on container restart)"
  mkdir -p /opt/render/project/src/uploads/ids \
           /opt/render/project/src/uploads/insurance \
           /opt/render/project/src/uploads/notes \
           /opt/render/project/src/uploads/aob \
           /opt/render/project/src/uploads/rx \
           /opt/render/project/src/uploads/wound-photos
  chown -R www-data:www-data /opt/render/project/src/uploads
  chmod -R 755 /opt/render/project/src/uploads
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
