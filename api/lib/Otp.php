<?php
require_once __DIR__ . '/SmtpMailer.php';

/**
 * Otp — generates, emails, and verifies one-time login codes.
 * Codes are never stored in plaintext: only an HMAC-SHA256 digest is kept,
 * compared with hash_equals() to avoid timing side-channels.
 */
class Otp
{
    private static function hash(string $code, int $userId): string
    {
        return hash_hmac('sha256', $userId . ':' . $code, APP_KEY);
    }

    /**
     * Invalidate any outstanding codes for this user, generate and store a
     * fresh one, and send it by email (or dev-log it — see SmtpMailer).
     * Returns ['expires_in' => seconds, 'dev_mode' => bool].
     */
    public static function issue(PDO $pdo, array $user): array
    {
        $pdo->prepare('UPDATE login_otps SET is_used = 1 WHERE user_id = ? AND is_used = 0')
            ->execute([$user['id']]);

        $code = str_pad((string)random_int(0, (int)str_repeat('9', OTP_LENGTH)), OTP_LENGTH, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + OTP_TTL_SECONDS);

        $stmt = $pdo->prepare(
            'INSERT INTO login_otps (user_id, otp_hash, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['id'], self::hash($code, $user['id']), $expiresAt]);

        $minutes = (int)ceil(OTP_TTL_SECONDS / 60);
        $subject = 'Your PharmOS verification code';
        $body = "Hi {$user['full_name']},\n\n"
            . "Your PharmOS sign-in code is: {$code}\n\n"
            . "This code expires in {$minutes} minutes. If you didn't request this, you can ignore this email.\n\n"
            . "— PharmOS";

        SmtpMailer::send($user['email'], $user['full_name'], $subject, $body);

        return [
            'expires_in' => OTP_TTL_SECONDS,
            'dev_mode'   => MAIL_DEV_MODE || SMTP_HOST === '',
        ];
    }

    /**
     * Verify a submitted code for the given user.
     * Returns ['ok' => bool, 'error' => string|null].
     */
    public static function verify(PDO $pdo, int $userId, string $code): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM login_otps WHERE user_id = ? AND is_used = 0 ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['ok' => false, 'error' => 'No active code found. Please request a new one.'];
        }

        if (strtotime($row['expires_at']) < time()) {
            $pdo->prepare('UPDATE login_otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);
            return ['ok' => false, 'error' => 'That code has expired. Please request a new one.'];
        }

        if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
            $pdo->prepare('UPDATE login_otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);
            return ['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.'];
        }

        $matches = hash_equals($row['otp_hash'], self::hash($code, $userId));

        if (!$matches) {
            $pdo->prepare('UPDATE login_otps SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
            $remaining = OTP_MAX_ATTEMPTS - ((int)$row['attempts'] + 1);
            return ['ok' => false, 'error' => "Incorrect code. {$remaining} attempt(s) remaining."];
        }

        $pdo->prepare('UPDATE login_otps SET is_used = 1 WHERE id = ?')->execute([$row['id']]);
        return ['ok' => true, 'error' => null];
    }

    /**
     * Returns seconds the caller must wait before another resend is allowed
     * (0 if they may resend immediately).
     */
    public static function secondsUntilResendAllowed(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            'SELECT created_at FROM login_otps WHERE user_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return 0;
        }
        $elapsed = time() - strtotime($row['created_at']);
        $remaining = OTP_RESEND_COOLDOWN_SECONDS - $elapsed;
        return $remaining > 0 ? $remaining : 0;
    }
}
