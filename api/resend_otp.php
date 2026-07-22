<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lib/Otp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

if (empty($_SESSION['otp_pending_user_id'])) {
    json_error('No sign-in in progress. Please log in again.', 401);
}

$pdo = get_db();
$userId = (int)$_SESSION['otp_pending_user_id'];

$wait = Otp::secondsUntilResendAllowed($pdo, $userId);
if ($wait > 0) {
    json_out(['success' => false, 'error' => "Please wait {$wait}s before requesting another code.", 'retry_in' => $wait], 429);
}

$stmt = $pdo->prepare('SELECT id, full_name, email, is_active FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !$user['is_active']) {
    unset($_SESSION['otp_pending_user_id']);
    json_error('This account is no longer active.', 401);
}

try {
    $otpInfo = Otp::issue($pdo, $user);
} catch (Exception $e) {
    error_log('OTP resend failed: ' . $e->getMessage());
    json_error('Could not resend your verification code. Please try again shortly.', 502);
}

$response = ['success' => true, 'expires_in' => $otpInfo['expires_in']];
if ($otpInfo['dev_mode']) {
    $response['dev_note'] = 'MAIL_DEV_MODE is on — the code was written to logs/otp_dev.log instead of being emailed.';
}

json_out($response);
