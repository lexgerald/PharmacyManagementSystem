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
define('DB_NAME', 'pms');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 3. Configure email OTP (second sign-in factor)

Every login now requires a 6-digit code emailed to the user before a session is created. `api/config.php` controls this:

```php
define('MAIL_DEV_MODE', true);   // true = don't send real email; write the code to logs/otp_dev.log instead

define('SMTP_HOST', '');          // e.g. 'smtp.gmail.com' or 'sandbox.smtp.mailtrap.io'
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');     // 'tls' | 'ssl' | ''
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('MAIL_FROM_EMAIL', 'no-reply@pharmos.local');
define('MAIL_FROM_NAME', 'PharmOS');
```

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
and update the `password_hash` column for that user; update the `email` column the same way via `UPDATE users SET email = '...' WHERE username = '...          |

The camera scanner needs HTTPS or `localhost` to access the device camera (a browser requirement, not specific to this app) — `http://localhost/...` works fine for local testing.