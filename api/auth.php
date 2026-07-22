<?php
/**
 * Shared session/auth helpers + small JSON response utilities.
 * Every protected endpoint should require_once this file after config.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json');

/** Send a JSON payload and stop execution. */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/** Send a standard error payload and stop execution. */
function json_error(string $message, int $status = 400): void
{
    json_out(['success' => false, 'error' => $message], $status);
}

/** Require an active login; otherwise respond 401 and stop. */
function require_login(): array
{
    if (empty($_SESSION['user_id'])) {
        json_error('Not authenticated. Please log in.', 401);
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}

/** Read and decode a JSON request body into an associative array. */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
