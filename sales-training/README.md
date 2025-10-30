# Sales Training Portal - Setup & Usage

## Overview
Internal sales training portal restricted to @collagendirect.health email addresses.

**Portal URL:** https://collagendirect.health/sales-training/

---

## How to Login

### For Team Members:

1. Go to: **https://collagendirect.health/sales-training/**
2. You'll be redirected to the login page
3. Enter your **@collagendirect.health** email address
4. Enter your password
5. Click "Sign In"

**Example:**
- Email: `john@collagendirect.health`
- Password: Your portal password

### Current Authentication Setup (TEMPORARY):

**⚠️ IMPORTANT - FOR DEVELOPMENT ONLY:**

The current system accepts **any password** as long as the email ends with `@collagendirect.health`. This is for initial testing purposes.

**Before going live with your sales team, you MUST integrate with your real authentication system.**

---

## Integration with Your Existing Auth System

You need to connect this to your main portal authentication. Here are your options:

### Option 1: Use Existing Portal Credentials (RECOMMENDED)

If your sales reps already have accounts in your main portal (the physician-facing one), modify `login.php` to check against that user database:

```php
// In login.php, replace the TODO section with:

// Connect to your database
$db = new PDO('mysql:host=localhost;dbname=your_db', 'user', 'pass');

// Check if user exists and verify password
$stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'sales'");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash'])) {
    // Login successful
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $user['name'];
    header('Location: index.php');
    exit;
} else {
    $error = 'Invalid email or password.';
}
```

### Option 2: Use Single Sign-On (SSO)

If you have Google Workspace or Microsoft 365 for your @collagendirect.health emails:

1. Install an OAuth library (e.g., `google/apiclient` for Google)
2. Replace the login form with "Sign in with Google" button
3. Verify the email domain after OAuth returns user info

### Option 3: Create Separate User Database

Create a `sales_users` table:

```sql
CREATE TABLE sales_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Then modify `login.php` to check this table.

---

## Adding New Sales Team Members

### Method 1: Through Your Main Portal
1. Create a user account in your main portal
2. Set their role to "sales" or "internal"
3. They can immediately log in to the training portal

### Method 2: Direct Database Entry (if using separate DB)
```sql
INSERT INTO sales_users (email, password_hash, name)
VALUES (
    'newrep@collagendirect.health',
    '$2y$10$...', -- Generate with password_hash() in PHP
    'New Rep Name'
);
```

---

## Available Training Modules

Once logged in, sales reps have access to:

1. **Quick Reference Guide** - One-page product/pricing cheat sheet
2. **Competitive Battle Cards** - Intel on Smith & Nephew, 3M, Integra, DME suppliers
3. **Sales Scripts** - Cold calls, demos, follow-ups, voicemails
4. **Product Training** (Coming Soon)
5. **Objection Handling** (Coming Soon)
6. **Success Stories** (Coming Soon)

---

## Session Management

- Sessions last until browser is closed or user clicks "Logout"
- Session timeout: Currently none (add one for security)
- Multiple devices: Users can be logged in on multiple devices simultaneously

### To Add Session Timeout:

Add this to the top of each protected page:

```php
// Logout after 8 hours of inactivity
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 28800)) {
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['login_time'] = time(); // Reset timer on each page load
```

---

## Security Checklist for Production

- [ ] Remove development `?email=` bypass in all files
- [ ] Integrate with real user database/authentication
- [ ] Add HTTPS enforcement (redirect HTTP to HTTPS)
- [ ] Implement session timeout (8 hours recommended)
- [ ] Add CSRF protection to login form
- [ ] Enable rate limiting on login attempts
- [ ] Add audit logging (who logged in when)
- [ ] Set secure session cookies (`httponly`, `secure` flags)
- [ ] Remove development warning box from login.php

---

## Troubleshooting

### "Access Denied" Error
- **Cause:** Email doesn't end with @collagendirect.health
- **Solution:** Verify email domain is correct

### Can't Login with Correct Email
- **Cause:** Session cookies disabled or authentication not connected
- **Solution:** Check browser cookies enabled, verify auth integration

### Logout Doesn't Work
- **Cause:** Session not being destroyed
- **Solution:** Check `login.php?logout=1` is being called, verify session_destroy() runs

### Redirected to Login After Already Logged In
- **Cause:** Session not persisting across pages
- **Solution:** Verify `session_start()` is at top of every PHP file

---

## Development/Testing

For local testing without real authentication:

1. Access login page
2. Enter any email ending with `@collagendirect.health`
3. Enter any password
4. You'll be logged in (temporary dev mode)

**OR** use the URL parameter bypass:
```
https://collagendirect.health/sales-training/?email=test@collagendirect.health
```

**⚠️ REMOVE BOTH OF THESE BEFORE PRODUCTION LAUNCH**

---

## Support

Questions about the training portal?
- Technical issues: Contact your IT administrator
- Content questions: sales-support@collagendirect.health
- Access requests: Contact sales leadership

---

## File Structure

```
/sales-training/
├── login.php              # Login page with auth
├── index.php              # Main training hub dashboard
├── quick-reference.php    # One-page sales guide
├── battle-cards.php       # Competitive intelligence
├── scripts.php            # Call scripts and emails
└── README.md             # This file
```

---

## Changelog

**v1.0 - Initial Release (2025-01-30)**
- Email-based authentication
- Quick Reference Guide
- Competitive Battle Cards
- Sales Scripts & Talk Tracks
- Login/logout functionality

**Coming Soon:**
- Product Training Module
- Objection Handling Library
- Customer Success Stories
- Integration with main portal auth
