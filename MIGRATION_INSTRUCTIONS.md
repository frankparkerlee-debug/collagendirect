# PreAuth Agent - Migration Instructions

## Quick Start

The easiest way to run the migrations is to use the provided script:

```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect/admin
./run-preauth-migrations.sh
```

This script will automatically:
1. Detect your PostgreSQL installation
2. Run all 4 migration files in order
3. Show success/failure for each migration
4. Provide next steps

## Manual Migration (Alternative)

If you prefer to run migrations manually or the script doesn't work:

### Option 1: Local PostgreSQL

```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect

# Set database credentials (if not in environment)
export DB_HOST="127.0.0.1"
export DB_PORT="5432"
export DB_NAME="collagen_db"
export DB_USER="postgres"
export DB_PASS="your_password"

# Find psql location
PSQL=/usr/local/Cellar/libpq/18.0/bin/psql

# Run migrations in order
$PSQL -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f admin/migrations/001_create_preauth_requests_table.sql
$PSQL -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f admin/migrations/002_create_preauth_rules_table.sql
$PSQL -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f admin/migrations/003_create_preauth_audit_log_table.sql
$PSQL -h $DB_HOST -p $DB_PORT -U $DB_USER -d $DB_NAME -f admin/migrations/004_create_eligibility_cache_table.sql
```

### Option 2: Remote PostgreSQL via SSH

```bash
# Copy migration files to remote server
scp -r admin/migrations/ user@collagendirect.health:/var/www/html/admin/

# SSH to server and run migrations
ssh user@collagendirect.health << 'EOF'
cd /var/www/html
sudo -u postgres psql collagen_db -f admin/migrations/001_create_preauth_requests_table.sql
sudo -u postgres psql collagen_db -f admin/migrations/002_create_preauth_rules_table.sql
sudo -u postgres psql collagen_db -f admin/migrations/003_create_preauth_audit_log_table.sql
sudo -u postgres psql collagen_db -f admin/migrations/004_create_eligibility_cache_table.sql
EOF
```

### Option 3: Using psql from Remote Server

If you have SSH access to the production server:

```bash
# Upload migration files (if not already there via git)
cd /Users/parkerlee/CollageDirect2.1/collagendirect
rsync -avz admin/migrations/ user@collagendirect.health:/var/www/html/admin/migrations/

# SSH and run
ssh user@collagendirect.health
cd /var/www/html
sudo -u postgres psql collagen_db < admin/migrations/001_create_preauth_requests_table.sql
sudo -u postgres psql collagen_db < admin/migrations/002_create_preauth_rules_table.sql
sudo -u postgres psql collagen_db < admin/migrations/003_create_preauth_audit_log_table.sql
sudo -u postgres psql collagen_db < admin/migrations/004_create_eligibility_cache_table.sql
```

## Verify Migrations

After running migrations, verify tables were created:

```bash
psql -d collagen_db -c "\dt preauth*"
```

Expected output:
```
                  List of relations
 Schema |         Name          | Type  |  Owner
--------+-----------------------+-------+----------
 public | preauth_audit_log     | table | postgres
 public | preauth_requests      | table | postgres
 public | preauth_rules         | table | postgres
 public | eligibility_cache     | table | postgres
```

Check that default rules were inserted:

```bash
psql -d collagen_db -c "SELECT carrier_name, hcpcs_code, requires_preauth FROM preauth_rules LIMIT 10;"
```

Expected output showing rules for Medicare, Medicaid, BCBS, UHC, Aetna, Cigna, Humana.

## Troubleshooting

### psql command not found

Install PostgreSQL client:

**macOS (Homebrew):**
```bash
brew install libpq
echo 'export PATH="/usr/local/opt/libpq/bin:$PATH"' >> ~/.zshrc
source ~/.zshrc
```

**Ubuntu/Debian:**
```bash
sudo apt-get install postgresql-client
```

**CentOS/RHEL:**
```bash
sudo yum install postgresql
```

### Connection refused

If you get "Connection refused" errors:

1. **Check if PostgreSQL is running:**
   ```bash
   ps aux | grep postgres
   ```

2. **Check if it's listening on the right port:**
   ```bash
   sudo lsof -i :5432
   ```

3. **Your database might be remote.** Check your application's database configuration or use SSH tunnel:
   ```bash
   ssh -L 5432:localhost:5432 user@collagendirect.health
   # Then in another terminal run migrations with DB_HOST=127.0.0.1
   ```

### Permission denied

If you get permission errors:

```bash
# Try running as postgres user
sudo -u postgres psql -d collagen_db -f admin/migrations/001_create_preauth_requests_table.sql

# Or grant permissions to your user
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE collagen_db TO your_user;"
```

### Table already exists

If you see "table already exists" errors, that's okay! The migrations use `CREATE TABLE IF NOT EXISTS` so they're safe to run multiple times.

To force recreate (CAUTION: This will delete data):

```sql
DROP TABLE IF EXISTS preauth_audit_log CASCADE;
DROP TABLE IF EXISTS preauth_requests CASCADE;
DROP TABLE IF EXISTS preauth_rules CASCADE;
DROP TABLE IF EXISTS eligibility_cache CASCADE;

-- Then run migrations again
```

## Next Steps After Migration

1. **Verify the data:**
   ```bash
   psql -d collagen_db -c "SELECT COUNT(*) FROM preauth_rules;"
   # Should show 21 (3 HCPCS codes × 7 carriers)
   ```

2. **Customize carrier rules:**
   ```sql
   -- Example: Update UnitedHealthcare to use API submission
   UPDATE preauth_rules
   SET submission_method = 'api',
       api_endpoint = 'https://api.uhc.com/preauth',
       auto_approval_eligible = TRUE
   WHERE carrier_name = 'UnitedHealthcare';
   ```

3. **Set up cron jobs** (see [PREAUTH_AGENT_README.md](PREAUTH_AGENT_README.md))

4. **Test the agent:**
   ```bash
   php admin/cron/preauth-agent.php --task=all
   ```

5. **Access admin dashboard:**
   Navigate to: `https://your-domain.com/admin/preauth-dashboard.php`

## Migration Files Reference

| File | Description | Creates |
|------|-------------|---------|
| `001_create_preauth_requests_table.sql` | Main preauth tracking | `preauth_requests` table + indexes + triggers |
| `002_create_preauth_rules_table.sql` | Carrier configuration | `preauth_rules` table + 21 default rules |
| `003_create_preauth_audit_log_table.sql` | HIPAA audit trail | `preauth_audit_log` table + helper function + view |
| `004_create_eligibility_cache_table.sql` | Eligibility caching | `eligibility_cache` table (optional) |

## Support

If you encounter issues:

1. Check the PostgreSQL error logs
2. Verify database connection settings in `api/db.php`
3. Ensure PostgreSQL version is 12+ (for `gen_random_uuid()` support)
4. Review the migration SQL files for any syntax errors

For further assistance, contact the development team.
