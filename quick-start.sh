#!/bin/bash

# CollagenDirect Quick Start Script
# This script helps you get the application running

set -e  # Exit on error

echo "=================================="
echo "CollagenDirect Quick Start"
echo "=================================="
echo ""

# Check if MySQL is installed
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL not found. Installing via Homebrew..."
    if command -v brew &> /dev/null; then
        brew install mysql
        brew services start mysql
        echo "✓ MySQL installed and started"
    else
        echo "❌ Homebrew not found. Please install MySQL manually:"
        echo "   https://dev.mysql.com/downloads/mysql/"
        exit 1
    fi
else
    echo "✓ MySQL is installed"
fi

# Offer Docker alternative
echo ""
read -p "Would you like to use Docker instead? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Starting MySQL in Docker..."
    docker run --name collagen-mysql \
      -e MYSQL_ROOT_PASSWORD=root \
      -e MYSQL_DATABASE=frxnaisp_collagendirect \
      -e MYSQL_USER=frxnaisp_collagendirect \
      -e MYSQL_PASSWORD="YEW!ad10jeo" \
      -p 3306:3306 \
      -d mysql:8.0

    echo "Waiting for MySQL to start..."
    sleep 10

    echo "✓ MySQL container started"

    # Import database
    echo "Importing database schema..."
    docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect < frxnaisp_collagendirect.sql

    # Apply fixes
    echo "Applying SQL fixes..."
    docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect < SQL_FIXES.sql

    echo "✓ Database imported and fixed"
fi

# Test database connection
echo ""
echo "Testing database connection..."
if node test-db-connection.js; then
    echo "✓ Database connection successful"
else
    echo "❌ Database connection failed. Please check your MySQL configuration."
    echo "   See SETUP.md for detailed instructions."
    exit 1
fi

# Check PHP installation
echo ""
if ! command -v php &> /dev/null; then
    echo "❌ PHP not found. Please install PHP 8.3 or higher."
    exit 1
else
    PHP_VERSION=$(php -r 'echo PHP_VERSION;')
    echo "✓ PHP $PHP_VERSION is installed"
fi

# Create necessary directories
echo ""
echo "Creating upload directories..."
mkdir -p uploads/ids uploads/insurance uploads/notes uploads/aob
chmod 755 uploads
chmod 755 uploads/*
echo "✓ Upload directories created"

# Start PHP development server
echo ""
echo "=================================="
echo "Starting PHP development server..."
echo "=================================="
echo ""
echo "Access the application at:"
echo "  - Home:         http://localhost:8000/"
echo "  - Portal Login: http://localhost:8000/portal"
echo "  - Admin Login:  http://localhost:8000/admin"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

php -S localhost:8000
