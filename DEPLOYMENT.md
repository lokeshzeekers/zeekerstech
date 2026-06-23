# Zeekers Technology Solutions — MySQL Deployment Guide
# From localStorage → MySQL on Hostinger

---

## WHAT CHANGED (Summary)

| File | What changed |
|------|-------------|
| `src/js/api.js` | NEW — shared API client used by all pages |
| `api/config.php` | NEW — DB credentials + JWT + helpers |
| `api/auth.php` | NEW — admin login → JWT token |
| `api/blogs.php` | NEW — CRUD for blog posts |
| `api/jobs.php` | NEW — CRUD for job listings |
| `api/applications.php` | NEW — job application submit + admin view |
| `api/contact.php` | NEW — contact form submit + admin view |
| `api/tickets.php` | NEW — helpdesk ticket CRUD |
| `api/helpdesk-auth.php` | NEW — public user register/login |
| `api/admins.php` | NEW — admin user management |
| `api/setup.sql` | NEW — run once to create all tables |
| `api/.htaccess` | NEW — CORS + security for API folder |
| `.htaccess` | NEW — root routing |
| `blog.html` | loadAdminPosts now calls BlogAPI.getPublished() |
| `career.html` | loadJobs calls JobsAPI.getActive(); apply → ApplicationsAPI.submit() |
| `contact.html` | submitForm calls ContactAPI.submit() |
| `helpdesk-login.html` | doLogin/doRegister call /api/helpdesk-auth.php |
| `helpdesk-dashboard.html` | submitIssue calls TicketsAPI.create() |
| `admin.html` | All CRUD uses API; _db cache replaces localStorage |

---

## STEP 1 — Create MySQL Database on Hostinger

1. Log into Hostinger hPanel
2. Go to **Databases → MySQL Databases**
3. Create a new database (e.g. `u123456789_zeekers`)
4. Create a database user with a strong password
5. Assign the user to the database (All Privileges)
6. Note down: database name, username, password

---

## STEP 2 — Update api/config.php

Open `api/config.php` and update these 4 lines:

```php
define('DB_HOST', 'localhost');        // usually 'localhost' on Hostinger
define('DB_NAME', 'u123456789_zeekers'); // your database name
define('DB_USER', 'u123456789_admin');   // your database user
define('DB_PASS', 'YourStrongPassword'); // your database password
```

Also change the JWT secret:
```php
define('JWT_SECRET', 'some-long-random-string-here-change-me');
```

---

## STEP 3 — Generate Admin Password Hash

The `setup.sql` file has a placeholder password hash. Replace it with your real password:

**Option A — On your computer (PHP must be installed):**
```bash
php -r "echo password_hash('YOUR_REAL_PASSWORD', PASSWORD_DEFAULT);"
```

**Option B — Use Hostinger's phpMyAdmin:**
Run this SQL to update after setup:
```sql
UPDATE admins 
SET password_hash = '$2y$10$PASTE_YOUR_HASH_HERE'
WHERE username = 'admin';
```

Or just re-run:
```sql
UPDATE admins 
SET password_hash = PASSWORD('your_password')  -- NOT recommended, use PHP hash
```

Best practice: use the PHP command and paste the output into config.php:
```php
define('ADMIN_PASSWORD_HASH', '$2y$10$...your-hash-here...');
```

---

## STEP 4 — Run setup.sql

1. In Hostinger hPanel → **Databases → phpMyAdmin**
2. Select your database
3. Click **Import** tab
4. Upload `api/setup.sql`
5. Click **Go**

This creates all tables and inserts a sample admin, blog post, and job.

---

## STEP 5 — Update API_BASE in src/js/api.js

Open `src/js/api.js` and change line 9:

```javascript
// Development (local):
const API_BASE = '/api';

// Production (Hostinger — change to your domain):
const API_BASE = 'https://zeekerstechnology.com/api';
```

---

## STEP 6 — Upload Files to Hostinger

Via Hostinger File Manager or FTP (FileZilla):

1. Upload everything to `public_html/` (or your domain's root folder)
2. Make sure the folder structure looks like:

```
public_html/
├── .htaccess              ← root htaccess
├── index.html
├── blog.html
├── career.html
├── contact.html
├── helpdesk-login.html
├── helpdesk-dashboard.html
├── admin.html
├── about.html
├── lab.html
├── products.html
├── assets/
├── src/
│   ├── js/
│   │   ├── api.js         ← NEW
│   │   └── main.js
│   └── style.scss
└── api/
    ├── .htaccess          ← api htaccess
    ├── config.php         ← update credentials here
    ├── auth.php
    ├── blogs.php
    ├── jobs.php
    ├── applications.php
    ├── contact.php
    ├── tickets.php
    ├── helpdesk-auth.php
    ├── admins.php
    └── setup.sql          ← already run, can delete after
```

---

## STEP 7 — Test Each Endpoint

Open your browser console or use curl to test:

```bash
# Test blogs API (should return empty array or sample post)
curl https://yourdomain.com/api/blogs.php

# Test jobs API
curl https://yourdomain.com/api/jobs.php

# Test admin login
curl -X POST https://yourdomain.com/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Admin@ZTS2025"}'
```

A successful login returns: `{"success":true,"token":"eyJ...","user":"admin"}`

---

## STEP 8 — First Login to Admin Panel

1. Go to `https://yourdomain.com/admin.html`
2. Login with: `admin@zeekerstechnology.com` / `Admin@ZTS2025`
3. The panel now loads data from MySQL
4. Create your first real blog post, job listing etc.

**⚠ Change the admin password immediately after first login!**
(Admin panel → Users/Admins section → Edit)

---

## TROUBLESHOOTING

**"Database connection failed"**
→ Check DB_HOST, DB_NAME, DB_USER, DB_PASS in config.php
→ On Hostinger, the host is almost always `localhost`

**"Unauthorized" on admin panel**
→ JWT token may have expired — log out and log back in
→ Check JWT_SECRET in config.php

**CORS errors in browser console**
→ Make sure api/.htaccess is uploaded
→ Check API_BASE in src/js/api.js matches your domain

**Blog/jobs not showing on public pages**
→ Check browser console for API errors
→ Verify the API_BASE URL is correct
→ Make sure blog posts have `published = 1` and jobs have `active = 1`

**Helpdesk login fails**
→ Users now need to register first (account created in MySQL)
→ Old localStorage accounts won't work — they need to re-register

---

## WHAT STILL USES localStorage

These features still use browser localStorage (intentionally, no DB table needed):
- **Products** in admin panel (AICTE-IDEA Lab equipment list) → admin_products key
- **Helpdesk dashboard** local issue cache → zts_issues (synced with server on ticket submit)
- **User session** (name/email after login) → zts_session

---

## API REFERENCE QUICK GUIDE

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/auth.php` | POST | — | Admin login |
| `/api/blogs.php` | GET | — | Get published posts |
| `/api/blogs.php?all=1` | GET | Admin | Get all posts |
| `/api/blogs.php` | POST | Admin | Create post |
| `/api/blogs.php?id=X` | PUT | Admin | Update post |
| `/api/blogs.php?id=X` | DELETE | Admin | Delete post |
| `/api/jobs.php` | GET | — | Get active jobs |
| `/api/jobs.php?all=1` | GET | Admin | Get all jobs |
| `/api/jobs.php` | POST | Admin | Create job |
| `/api/applications.php` | POST | — | Submit application |
| `/api/applications.php` | GET | Admin | View all applications |
| `/api/contact.php` | POST | — | Submit contact form |
| `/api/contact.php` | GET | Admin | View all messages |
| `/api/tickets.php` | POST | — | Create helpdesk ticket |
| `/api/tickets.php?all=1` | GET | Admin | View all tickets |
| `/api/helpdesk-auth.php` | POST | — | User login/register |
| `/api/admins.php` | GET/POST/PUT/DELETE | Admin | Manage admin users |
