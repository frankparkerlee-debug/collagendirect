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

# Start Apache
exec apache2-foreground
