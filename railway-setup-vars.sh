#!/bin/bash
# Railway Variables Setup Script
# This script helps you set up all required environment variables in Railway

echo "============================================"
echo "Railway Environment Variables Setup"
echo "============================================"
echo ""
echo "Prerequisites:"
echo "1. Railway CLI installed: npm i -g @railway/cli"
echo "2. Logged in: railway login"
echo "3. Project linked: railway link"
echo ""
echo "This script will guide you through setting up variables."
echo ""

# Check if railway CLI is installed
if ! command -v railway &> /dev/null
then
    echo "❌ Railway CLI not found. Install it with: npm i -g @railway/cli"
    exit 1
fi

echo "✅ Railway CLI found"
echo ""

# Function to set a variable
set_var() {
    local key=$1
    local description=$2
    local default=$3

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "Setting: $key"
    echo "Description: $description"

    if [ -n "$default" ]; then
        echo "Default: $default"
        read -p "Enter value (press Enter for default): " value
        value=${value:-$default}
    else
        read -p "Enter value: " value
    fi

    if [ -n "$value" ]; then
        railway variables set "$key=$value"
        echo "✅ Set $key"
    else
        echo "⏭️  Skipped $key"
    fi
    echo ""
}

echo "============================================"
echo "📧 SendGrid Configuration"
echo "============================================"
echo ""

set_var "SENDGRID_API_KEY" "Your SendGrid API key" ""
set_var "SMTP_FROM" "Email address for sending" "no-reply@collagendirect.health"
set_var "SMTP_FROM_NAME" "Display name for emails" "CollagenDirect"

echo "============================================"
echo "📨 SendGrid Template IDs"
echo "============================================"
echo ""

set_var "SG_TMPL_PASSWORD_RESET" "Password reset template" "d-41ea629107c54e0abc1dcbd654c9e498"
set_var "SG_TMPL_ACCOUNT_CONFIRM" "Self-registration confirmation" "d-c33b0338c94544bda58c885892ce2f53"
set_var "SG_TMPL_PHYSACCOUNT_CONFIRM" "Admin-created account confirmation" "d-12d5c5a34f5f4fe19424db7d88f44ab5"
set_var "SG_TMPL_ORDER_RECEIVED" "Order confirmation for patients" "d-32c6aee2093b4363b10a5ab4f23c9230"
set_var "SG_TMPL_ORDER_APPROVED" "Order approved notification" "d-e73bec2b87bf45ba9108eb9c1fcf850b"
set_var "SG_TMPL_ORDER_SHIPPED" "Shipping notification" "d-0b24b64993e149329a7d0702b0db4c65"
set_var "SG_TMPL_MANUFACTURER_ORDER" "New order notification for manufacturer" "d-67cf6288aacd45b9a55a8d84fe0d2917"

echo "============================================"
echo "📱 Twilio SMS Configuration (Optional)"
echo "============================================"
echo ""

read -p "Do you want to set up Twilio SMS? (y/N): " setup_twilio
if [[ $setup_twilio =~ ^[Yy]$ ]]; then
    set_var "TWILIO_ACCOUNT_SID" "Twilio Account SID" ""
    set_var "TWILIO_AUTH_TOKEN" "Twilio Auth Token" ""
    set_var "TWILIO_PHONE_NUMBER" "Twilio phone number (format: +1234567890)" ""
else
    echo "⏭️  Skipped Twilio configuration"
fi

echo ""
echo "============================================"
echo "✅ Variable Setup Complete!"
echo "============================================"
echo ""
echo "📋 Next Steps:"
echo ""
echo "1. Add PostgreSQL database in Railway dashboard:"
echo "   Project → New → Database → PostgreSQL"
echo ""
echo "2. Add database reference variables:"
echo "   Service → Variables → New Variable → Add Reference"
echo "   - DB_HOST → PGHOST"
echo "   - DB_PORT → PGPORT"
echo "   - DB_NAME → PGDATABASE"
echo "   - DB_USER → PGUSER"
echo "   - DB_PASS → PGPASSWORD"
echo ""
echo "3. Add persistent volume:"
echo "   Service → Settings → Volumes → Add Volume"
echo "   Mount path: /var/data/uploads"
echo "   Size: 1GB (or more)"
echo ""
echo "4. Deploy your application!"
echo ""
echo "📚 See RAILWAY_SETUP.md for detailed instructions"
echo "✅ See RAILWAY_VARIABLES_CHECKLIST.md to track progress"
echo ""
