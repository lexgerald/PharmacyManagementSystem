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

$body = read_json_body();
$code = trim((string)($body['code'] ?? ''));

if (!preg_match('/^\d{' . OTP_LENGTH . '}$/', $code)) {
    json_error('Enter the ' . OTP_LENGTH . '-digit code from your email.', 422);
}

$pdo = get_db();
$userId = (int)$_SESSION['otp_pending_user_id'];

$result = Otp::verify($pdo, $userId, $code);
if (!$result['ok']) {
    json_error($result['error'], 401);
}

$stmt = $pdo->prepare('SELECT id, username, full_name, role, is_active FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !$user['is_active']) {
    unset($_SESSION['otp_pending_user_id']);
    json_error('This account is no longer active.', 401);
}

// Code verified — complete the login.
session_regenerate_id(true);
unset($_SESSION['otp_pending_user_id']);
$_SESSION['user_id']   = $user['id'];
$_SESSION['username']  = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

json_out([
    'success' => true,
    'user' => [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ],
]);
