<?php
/**
 * Database connection configuration.
 * Edit these values to match your local MySQL/MariaDB setup (XAMPP/WAMP/MAMP defaults shown).
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'pms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Low stock / near-expiry thresholds used across the API
define('NEAR_EXPIRY_DAYS', 30);

/**
 * Secret used to HMAC-hash OTP codes before storing them (never store a
 * plain code). Change this to a long random string in production, e.g.
 * generate one with: php -r "echo bin2hex(random_bytes(32));"
 */
define('APP_KEY', 'change-this-to-a-long-random-string-before-deploying');

// ---- One-time passcode (login 2FA) settings --------------------------
define('OTP_LENGTH', 6);
define('OTP_TTL_SECONDS', 600);            // code is valid for 10 minutes
define('OTP_MAX_ATTEMPTS', 5);             // wrong guesses allowed per code
define('OTP_RESEND_COOLDOWN_SECONDS', 45); // minimum gap between resend requests

// ---- SMTP settings for sending the OTP email --------------------------
// Leave SMTP_HOST blank (or keep MAIL_DEV_MODE true) to skip real email
// sending during local development — codes are written to logs/otp_dev.log
// instead so you can finish testing the flow without a mail provider.
define('MAIL_DEV_MODE', false);

define('SMTP_HOST', 'smtp.gmail.com');           // e.g. 'smtp.gmail.com' or 'sandbox.smtp.mailtrap.io'
define('SMTP_PORT', 587);          // 587 = STARTTLS, 465 = implicit TLS, 25/2525 = none
define('SMTP_SECURE', 'tls');      // 'tls' | 'ssl' | ''
define('SMTP_USERNAME', 'alexandregeraldwilliams@gmail.com');
define('SMTP_PASSWORD', 'osxeqfnyznbqztag');
define('MAIL_FROM_EMAIL', 'alexandregeraldwilliams@gmail.com');
define('MAIL_FROM_NAME', 'PharmOS');

/**
 * Returns a shared PDO connection using prepared-statement-friendly defaults.
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database connection failed. Check api/config.php.']);
            exit;
        }
    }

    return $pdo;
}
