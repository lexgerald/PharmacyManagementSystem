# PharmOS — Pharmacy Management System

A barcode-driven dispensing and inventory system for a pharmacy counter. Vanilla HTML/CSS/JS front end, PHP 7.4+ REST-style API, MySQL/MariaDB storage.

## Features

- **Scan Out console** — look up a drug by barcode (keyboard-wedge scanner, manual entry, or phone/webcam camera via [html5-qrcode](https://github.com/mebjas/html5-qrcode)), see live stock/expiry status, and confirm a dispensation in one click.
- **Dashboard** — total stock items, low-stock alert count, today's sales count & revenue, near-expiry count, recent activity, and an attention list.
- **Inventory management** — search, filter to low stock, add/edit/delete drugs.
- **Sales log** — searchable, paginated, filterable by date.
- **Login-gated with email OTP** — username/password, then a 6-digit code emailed to the account before a session is granted. Codes expire in 10 minutes, allow 5 attempts, and are rate-limited on resend. Session-based auth, bcrypt password hashes, prepared statements everywhere.
- **Edge cases handled** — drug not found, out of stock, insufficient stock, expired batch, near-expiry warning (≤30 days).

## Requirements

- PHP 7.4 or later with the `pdo_mysql` extension enabled
- MySQL 5.7+ or MariaDB 10.2+
- Any local stack works: XAMPP, WAMP, MAMP, or the PHP built-in server

## 1. Set up the database

1. Start MySQL/MariaDB (e.g. via XAMPP's control panel).
2. Open phpMyAdmin (or the `mysql` CLI) and import `database/schema.sql`:
   - **phpMyAdmin:** create nothing manually — just click *Import*, choose `database/schema.sql`, and run it. It creates the `pharmacy_pms` database, tables, indexes, and sample data.
   - **CLI:**
     ```bash
     mysql -u root -p < database/schema.sql
     ```
3. This creates two demo accounts (see below) and 10 sample drugs.

## 2. Configure the database connection

Edit `api/config.php` if your MySQL credentials differ from the XAMPP defaults:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pharmacy_pms');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 3. Configure email OTP (second sign-in factor)

Every login now requires a 6-digit code emailed to the user before a session is created. `api/config.php` controls this:
Go to your Google Account → Security → make sure 2-Step Verification is turned on (required for app passwords)
Go to https://myaccount.google.com/apppasswords
Create a new app password (name it anything, e.g. "PharmOS")
Google gives you a 16-character password like abcd efgh ijkl mnop — copy it (remove the spaces)

2. Edit api/config.php:

php
define('MAIL_DEV_MODE', false);          // turn off dev-mode logging

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', 'youraddress@gmail.com');
define('SMTP_PASSWORD', 'abcdefghijklmnop');   // the 16-char app password, no spaces
define('MAIL_FROM_EMAIL', 'youraddress@gmail.com');
define('MAIL_FROM_NAME', 'PharmOS');

**For local testing (no mail server needed):** leave `MAIL_DEV_MODE` as `true`. Codes are appended to `logs/otp_dev.log` instead of being emailed — open that file after signing in to grab the code. No composer/PHPMailer dependency is needed; `api/lib/SmtpMailer.php` is a small self-contained SMTP client.

**To send real email:** set `MAIL_DEV_MODE` to `false` and fill in `SMTP_HOST`/`SMTP_USERNAME`/`SMTP_PASSWORD`. Works with:
- **Gmail** — `smtp.gmail.com`, port `587`, `tls`, and a [Google App Password](https://support.google.com/accounts/answer/185833) (not your normal password).
- **Mailtrap** (safe for testing — emails never leave a sandbox inbox) — use the SMTP credentials from your Mailtrap inbox settings.
- Any other SMTP provider (SendGrid, Postmark, your own mail server, etc.) — use the host/port/credentials they give you.

Also change `APP_KEY` in `api/config.php` to a long random string before deploying — it's used to hash OTP codes at rest:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

**Upgrading an existing install:** if you already ran an older `schema.sql` (before OTP existed), run `database/migration_add_otp.sql` instead of re-importing the whole schema — it adds the `email` column and `login_otps` table without touching your existing drugs/sales data.

## 4. Deploy the app

**XAMPP/WAMP/MAMP:**
Copy the entire `pms/` folder into your web root (e.g. `htdocs/pms` on XAMPP), then visit:

```
http://localhost/pms/login.html
```

**PHP built-in server (quick local test, no Apache needed):**
```bash
cd pms
php -S localhost:8000
```
Then visit `http://localhost:8000/login.html`.

## 5. Log in

| Username  | Password  | Email                    | Role        |
|-----------|-----------|--------------------------|-------------|
| admin     | admin123  | admin@pharmos.local      | admin       |
| jkamara   | admin123  | jkamara@pharmos.local    | pharmacist  |

After entering the password you'll be asked for a 6-digit code. With `MAIL_DEV_MODE` on (the default), open `logs/otp_dev.log` and copy the latest code in there — nothing needs to be emailed for local testing.

**Change these passwords, and point the emails at real inboxes, before using this anywhere near real data.** To set a new password, generate a hash with:
```bash
php -r "echo password_hash('your-new-password', PASSWORD_BCRYPT), PHP_EOL;"
```
and update the `password_hash` column for that user; update the `email` column the same way via `UPDATE users SET email = '...' WHERE username = '...'`.

## Try it out

Sample barcodes to scan or type into the Scan Out console:

| Barcode         | Drug                    | Notes                          |
|-----------------|-------------------------|---------------------------------|
| 8901030875021   | Paracetamol 500mg       | Healthy stock                   |
| 8901030875038   | Amoxicillin 250mg       | Below reorder level (low stock) |
| 8901030875052   | Cough Syrup 100ml       | Low stock                       |
| 8901030875076   | Omeprazole 20mg         | Out of stock (quantity = 0)     |
| 8901030875113   | Artemether/Lumefantrine | Expires within 30 days          |

The camera scanner needs HTTPS or `localhost` to access the device camera (a browser requirement, not specific to this app) — `http://localhost/...` works fine for local testing.

## Project structure

```
pms/
├── login.html              Login screen
├── index.html               Main application shell (dashboard/scan/inventory/sales)
├── css/style.css            Design system + all styling
├── js/app.js                Application logic (routing, API calls, rendering)
├── js/scanner.js             Camera barcode scanning (html5-qrcode)
├── api/
│   ├── config.php           DB connection + OTP/SMTP settings
│   ├── auth.php              Session helpers + JSON response helpers
│   ├── login.php             POST — verify password, issue email OTP
│   ├── verify_otp.php         POST — verify OTP, complete login
│   ├── resend_otp.php         POST — resend OTP (rate-limited)
│   ├── logout.php            POST — end session
│   ├── session.php           GET  — check current session
│   ├── dashboard.php         GET  — dashboard metrics
│   ├── drugs.php             GET/POST/PUT/DELETE — inventory CRUD
│   ├── scan.php              POST — barcode lookup
│   ├── sell.php               POST — process a dispensation
│   ├── sales.php              GET  — paginated sales history
│   └── lib/
│       ├── Otp.php            Generates/hashes/verifies OTP codes
│       └── SmtpMailer.php      Dependency-free SMTP client (+ dev-log fallback)
├── logs/                    otp_dev.log written here in MAIL_DEV_MODE
│   └── .htaccess             Blocks direct web access to this folder
├── database/
│   ├── schema.sql            Tables, indexes, and sample data (fresh install)
│   ├── migration_add_otp.sql Adds email + login_otps to an existing DB
│   └── .htaccess             Blocks direct web access to this folder
```

## Security notes

- All SQL uses PDO prepared statements — no string-concatenated queries anywhere.
- Passwords are stored as bcrypt hashes (`password_hash()` / `password_verify()`).
- Login is two-factor: a correct password only grants a *pending* state, not a session — a valid OTP is also required. Codes are hashed with HMAC-SHA256 (`APP_KEY`) before storage, never kept in plain text, compared with `hash_equals()`, expire after 10 minutes, allow 5 wrong guesses before requiring a fresh code, and are rate-limited on resend (45s cooldown).
- Every API endpoint except `login.php`, `verify_otp.php`, and `resend_otp.php` requires an active, fully-verified session.
- Session cookies are set `HttpOnly` and `SameSite=Lax`; the session ID is regenerated both when the pending OTP state starts and again when it's completed, to resist session fixation.
- Input is validated server-side on every write (`drugs.php`, `sell.php`).
- `logs/` and `database/` each ship with an `.htaccess` denying all direct web access (Apache/XAMPP). If you deploy on **Nginx** instead, Apache `.htaccess` files are ignored — add an equivalent `location { deny all; }` block for both paths in your server config, since the dev OTP log and raw SQL/schema files must never be reachable by URL.
- For production use, also add: HTTPS, CSRF tokens on state-changing requests, rate limiting on the password step itself (not just OTP resend), and a dedicated (non-root) MySQL user with least-privilege grants. Also set `MAIL_DEV_MODE` to `false` and configure real SMTP — dev mode must never be left on in production, since it writes codes to a log file instead of emailing them.
