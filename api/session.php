<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (empty($_SESSION['user_id'])) {
    json_out(['success' => true, 'authenticated' => false]);
}

json_out([
    'success' => true,
    'authenticated' => true,
    'user' => [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ],
]);
