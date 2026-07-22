<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Otp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$body = read_json_body();
$username = trim($body['username'] ?? '');
$password = (string)($body['password'] ?? '');

if ($username === '' || $password === '') {
    json_error('Username and password are required.', 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, email, role, is_active FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
    json_error('Invalid username or password.', 401);
}

// Password is correct. Don't fully log in yet — issue an email OTP and
// hold the user in a "pending" state until it's verified.
try {
    $otpInfo = Otp::issue($pdo, $user);
} catch (Exception $e) {
    error_log('OTP send failed: ' . $e->getMessage());
    json_error('Could not send your verification code. Please try again in a moment.', 502);
}

session_regenerate_id(true);
$_SESSION['otp_pending_user_id'] = $user['id'];
// Clear anything left over from a previous, unrelated session
unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['full_name'], $_SESSION['role']);

$response = [
    'success'      => true,
    'otp_required' => true,
    'expires_in'   => $otpInfo['expires_in'],
    'email_hint'   => mask_email($user['email']),
];

// Dev convenience only: makes the flow testable without real SMTP configured.
// This block is inert unless MAIL_DEV_MODE is true or SMTP_HOST is blank.
if ($otpInfo['dev_mode']) {
    $response['dev_note'] = 'MAIL_DEV_MODE is on — the code was written to logs/otp_dev.log instead of being emailed.';
}

json_out($response);

/** Turns "jkamara@pharmos.local" into "j******a@pharmos.local" for display. */
function mask_email(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if (strlen($local) <= 2) {
        $masked = str_repeat('*', strlen($local));
    } else {
        $masked = $local[0] . str_repeat('*', strlen($local) - 2) . substr($local, -1);
    }
    return $masked . '@' . $domain;
}
