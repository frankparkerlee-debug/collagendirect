#!/bin/bash
# Script to safely copy environment variables from old Render service to new one
# This will NOT overwrite database credentials (they're auto-configured)

set -e

echo "=== Render Environment Variable Migration ==="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if render CLI is logged in
if ! render whoami &>/dev/null; then
    echo -e "${RED}Error: You need to login to Render CLI first${NC}"
    echo "Run: render login"
    exit 1
fi

echo -e "${GREEN}✓ Render CLI authenticated${NC}"
echo ""

# List all services
echo "Fetching your Render services..."
render services list

echo ""
echo "Please provide the following information:"
echo ""

# Get old service ID
read -p "Enter OLD service ID (the existing collagendirect service): " OLD_SERVICE_ID
read -p "Enter NEW service ID (the new collagendirect-qorw service): " NEW_SERVICE_ID

echo ""
echo -e "${YELLOW}Warning: This will copy environment variables from${NC}"
echo "  FROM: $OLD_SERVICE_ID"
echo "  TO:   $NEW_SERVICE_ID"
echo ""
echo -e "${YELLOW}Database credentials (DB_*) will be SKIPPED (auto-configured)${NC}"
echo ""
read -p "Continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo "Aborted."
    exit 0
fi

# Variables to skip (already auto-configured from database)
SKIP_VARS=("DB_HOST" "DB_PORT" "DB_NAME" "DB_USER" "DB_PASS")

# Get environment variables from old service
echo ""
echo "Fetching environment variables from old service..."

# Export old service env vars to a temporary file
OLD_ENV_FILE=$(mktemp)
render env-vars get --service "$OLD_SERVICE_ID" > "$OLD_ENV_FILE"

if [ ! -s "$OLD_ENV_FILE" ]; then
    echo -e "${RED}Error: Could not fetch environment variables from old service${NC}"
    rm "$OLD_ENV_FILE"
    exit 1
fi

echo -e "${GREEN}✓ Fetched environment variables${NC}"
echo ""

# Parse and copy each variable
echo "Copying environment variables..."
COPIED=0
SKIPPED=0

while IFS='=' read -r key value; do
    # Skip empty lines and comments
    [[ -z "$key" || "$key" =~ ^# ]] && continue

    # Skip database credentials
    skip=false
    for skip_var in "${SKIP_VARS[@]}"; do
        if [ "$key" = "$skip_var" ]; then
            echo -e "${YELLOW}⊘ Skipping $key (auto-configured)${NC}"
            ((SKIPPED++))
            skip=true
            break
        fi
    done

    if [ "$skip" = true ]; then
        continue
    fi

    # Copy the variable to new service
    echo "→ Copying $key"
    if render env-vars set "$key=$value" --service "$NEW_SERVICE_ID" 2>/dev/null; then
        echo -e "${GREEN}  ✓ Copied $key${NC}"
        ((COPIED++))
    else
        echo -e "${RED}  ✗ Failed to copy $key${NC}"
    fi
done < "$OLD_ENV_FILE"

# Clean up
rm "$OLD_ENV_FILE"

echo ""
echo "=== Migration Complete ==="
echo -e "${GREEN}Copied: $COPIED variables${NC}"
echo -e "${YELLOW}Skipped: $SKIPPED variables (auto-configured)${NC}"
echo ""
echo "The new service will redeploy automatically with the new environment variables."
