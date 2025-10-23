#!/bin/bash
# This script should be run once after deployment to initialize the database

if [ -f "schema.sql" ]; then
    mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME < schema.sql
    echo "Database schema imported successfully"
else
    echo "schema.sql not found - skipping database initialization"
fi
