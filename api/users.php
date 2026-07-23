<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$user = require_login();

// Allow both admin and super_admin to manage users
if ($user['role'] !== 'admin' && $user['role'] !== 'super_admin') {
    json_error('Access denied. Only admin can manage users.', 403);
}

$pdo = get_db();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($pdo);
        break;
    case 'POST':
        handle_post($pdo);
        break;
    case 'PUT':
        handle_put($pdo);
        break;
    case 'DELETE':
        handle_delete($pdo);
        break;
    default:
        json_error('Method not allowed.', 405);
}

/** Get all users */
function handle_get(PDO $pdo): void {
    $stmt = $pdo->query('SELECT id, username, full_name, email, role, is_active, created_at FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
    
    json_out(['success' => true, 'users' => $users]);
}

/** Create a new user */
function handle_post(PDO $pdo): void {
    $data = read_json_body();
    $errors = validate_user($data);
    if ($errors) {
        json_out(['success' => false, 'error' => 'Validation failed.', 'fields' => $errors], 422);
    }
    
    // Check if username already exists
    $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $check->execute([$data['username']]);
    if ($check->fetch()) {
        json_error('Username already exists.', 409);
    }
    
    // Check if email already exists
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$data['email']]);
    if ($check->fetch()) {
        json_error('Email already exists.', 409);
    }
    
    // Hash password
    $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, full_name, email, password_hash, role, is_active) 
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['username'],
        $data['full_name'],
        $data['email'],
        $passwordHash,
        $data['role'],
        $data['is_active'] ?? 1
    ]);
    
    json_out(['success' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

/** Update a user */
function handle_put(PDO $pdo): void {
    $data = read_json_body();
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        json_error('A valid user id is required.', 422);
    }
    
    // Check if user exists
    $check = $pdo->prepare('SELECT id FROM users WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        json_error('User not found.', 404);
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['username'])) {
        $updates[] = 'username = ?';
        $params[] = $data['username'];
    }
    if (isset($data['full_name'])) {
        $updates[] = 'full_name = ?';
        $params[] = $data['full_name'];
    }
    if (isset($data['email'])) {
        $updates[] = 'email = ?';
        $params[] = $data['email'];
    }
    if (isset($data['role'])) {
        $updates[] = 'role = ?';
        $params[] = $data['role'];
    }
    if (isset($data['is_active'])) {
        $updates[] = 'is_active = ?';
        $params[] = (int)$data['is_active'];
    }
    if (!empty($data['password'])) {
        $updates[] = 'password_hash = ?';
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    if (empty($updates)) {
        json_error('No fields to update.', 422);
    }
    
    $params[] = $id;
    $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    json_out(['success' => true]);
}

/** Delete a user */
function handle_delete(PDO $pdo): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('A valid user id is required.', 422);
    }
    
    // Prevent deleting self
    $user = require_login();
    if ($id === $user['id']) {
        json_error('You cannot delete your own account.', 403);
    }
    
    // Prevent deleting the main admin account
    $check = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $check->execute([$id]);
    $userToDelete = $check->fetch();
    if ($userToDelete && $userToDelete['username'] === 'admin') {
        json_error('You cannot delete the main admin account.', 403);
    }
    
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        json_error('User not found.', 404);
    }
    
    json_out(['success' => true]);
}

/** Validate user data */
function validate_user(array $data): array {
    $errors = [];
    $validRoles = ['admin', 'user'];
    
    if (empty($data['username']) || strlen($data['username']) < 3) {
        $errors['username'] = 'Username must be at least 3 characters.';
    }
    if (empty($data['full_name'])) {
        $errors['full_name'] = 'Full name is required.';
    }
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    if (empty($data['password']) || strlen($data['password']) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }
    if (!isset($data['role']) || !in_array($data['role'], $validRoles, true)) {
        $errors['role'] = 'Role must be admin or user.';
    }
    
    return $errors;
}