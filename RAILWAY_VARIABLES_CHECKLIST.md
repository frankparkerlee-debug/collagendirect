# Railway Variables Checklist

Use this checklist when setting up your Railway deployment. Check off each variable as you add it.

## 📋 Setup Order

1. ✅ Add PostgreSQL database to project
2. ✅ Add database reference variables (5 variables)
3. ✅ Add SendGrid email variables (3 variables)
4. ✅ Add SendGrid template IDs (7 variables)
5. ⬜ (Optional) Add Twilio SMS variables (3 variables)
6. ✅ Add persistent volume for uploads

---

## 🗄️ Database Reference Variables (from PostgreSQL service)

In Railway Dashboard: **Variables → New Variable → Add Reference**

- [ ] `DB_HOST` → Reference `PGHOST` from PostgreSQL service
- [ ] `DB_PORT` → Reference `PGPORT` from PostgreSQL service
- [ ] `DB_NAME` → Reference `PGDATABASE` from PostgreSQL service
- [ ] `DB_USER` → Reference `PGUSER` from PostgreSQL service
- [ ] `DB_PASS` → Reference `PGPASSWORD` from PostgreSQL service

**Auto-provided by Railway:**
- ✅ `DATABASE_URL` (automatically set when PostgreSQL is added)

---

## 📧 SendGrid Email Variables

In Railway Dashboard: **Variables → New Variable → Add Variable**

- [ ] `SENDGRID_API_KEY` = `your_sendgrid_api_key_here`
- [ ] `SMTP_FROM` = `no-reply@collagendirect.health`
- [ ] `SMTP_FROM_NAME` = `CollagenDirect`

---

## 📨 SendGrid Template IDs

- [ ] `SG_TMPL_PASSWORD_RESET` = `d-41ea629107c54e0abc1dcbd654c9e498`
- [ ] `SG_TMPL_ACCOUNT_CONFIRM` = `d-c33b0338c94544bda58c885892ce2f53`
- [ ] `SG_TMPL_PHYSACCOUNT_CONFIRM` = `d-12d5c5a34f5f4fe19424db7d88f44ab5`
- [ ] `SG_TMPL_ORDER_RECEIVED` = `d-32c6aee2093b4363b10a5ab4f23c9230`
- [ ] `SG_TMPL_ORDER_APPROVED` = `d-e73bec2b87bf45ba9108eb9c1fcf850b`
- [ ] `SG_TMPL_ORDER_SHIPPED` = `d-0b24b64993e149329a7d0702b0db4c65`
- [ ] `SG_TMPL_MANUFACTURER_ORDER` = `d-67cf6288aacd45b9a55a8d84fe0d2917`

---

## 📱 Twilio SMS Variables (Optional)

Only needed if using SMS delivery confirmations:

- [ ] `TWILIO_ACCOUNT_SID` = `your_twilio_account_sid_here`
- [ ] `TWILIO_AUTH_TOKEN` = `your_twilio_auth_token_here`
- [ ] `TWILIO_PHONE_NUMBER` = `+1234567890`

---

## 💾 Persistent Volume Setup

In Railway Dashboard: **Service Settings → Volumes**

- [ ] Click "Add Volume"
- [ ] Mount path: `/var/data/uploads`
- [ ] Size: `1GB` (or more as needed)

---

## ✅ Verification Steps

After adding all variables:

1. [ ] Check deployment logs for errors
2. [ ] Visit health check endpoint: `https://your-app.railway.app/portal/health.php`
3. [ ] Test database connection
4. [ ] Test email sending (password reset, etc.)
5. [ ] Test file uploads
6. [ ] Verify cron jobs are running (check logs for "Cron jobs initialized successfully")

---

## 🔍 Quick Reference: Total Variables

- **Database**: 5 reference variables + 1 auto-provided
- **SendGrid**: 10 variables (3 config + 7 templates)
- **Twilio** (optional): 3 variables
- **Total Required**: 15 variables
- **Total with Twilio**: 18 variables

---

## 📝 Notes

- Variables can be added/edited at any time
- Changes trigger automatic redeployment
- Use Railway CLI for bulk variable import: `railway variables set KEY=value`
- Secret variables are encrypted and never exposed in logs
