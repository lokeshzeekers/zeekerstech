<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Helpdesk Portal Users API
//  (Admin panel management of the public helpdesk login accounts)
//
//  GET    /api/helpdesk-users.php        → list all portal users
//  GET    /api/helpdesk-users.php?id=1   → single user
//  POST   /api/helpdesk-users.php        → create user
//  PUT    /api/helpdesk-users.php?id=1   → update user (+ optional password reset)
//  DELETE /api/helpdesk-users.php?id=1   → delete user
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

requireAdminAuth();  // All routes protected — admin only

$db = getDB();

// Ensure the table exists even if no one has logged into the helpdesk portal yet.
$db->exec("
    CREATE TABLE IF NOT EXISTS helpdesk_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name  VARCHAR(100) NOT NULL,
        email      VARCHAR(255) UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        org        VARCHAR(255) DEFAULT '',
        avatar     VARCHAR(255) DEFAULT '',
        created_at TIMESTAMP DEFAULT NOW()
    )
");

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare('SELECT id, first_name, last_name, email, org, avatar, created_at FROM helpdesk_users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) respondError('User not found', 404);
        respond(['success' => true, 'data' => $user]);
    }

    $stmt = $db->query('SELECT id, first_name, last_name, email, org, avatar, created_at FROM helpdesk_users ORDER BY created_at DESC');
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (create) ─────────────────────────────────────────────
if ($method === 'POST') {
    $input = getInput();
    $first    = sanitize($input['first_name'] ?? '');
    $last     = sanitize($input['last_name'] ?? '');
    $email    = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';
    $org      = sanitize($input['org'] ?? '');

    if (!$first)   respondError('First name is required');
    if (!$last)    respondError('Last name is required');
    if (!$email)   respondError('Valid email is required');
    if (strlen($password) < 8) respondError('Password must be at least 8 characters');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO helpdesk_users (first_name, last_name, email, password_hash, org) VALUES (?, ?, ?, ?, ?)');
    try {
        $stmt->execute([$first, $last, $email, $hash, $org]);
        respond(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'User created'], 201);
    } catch (PDOException $e) {
        respondError('An account with this email already exists');
    }
}

// ── PUT (update, optional password reset) ─────────────────────
if ($method === 'PUT') {
    if (!$id) respondError('User ID required');

    $exists = $db->prepare('SELECT id FROM helpdesk_users WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetch()) respondError('User not found', 404);

    $input    = getInput();
    $first    = sanitize($input['first_name'] ?? '');
    $last     = sanitize($input['last_name'] ?? '');
    $email    = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $org      = sanitize($input['org'] ?? '');
    $password = $input['password'] ?? '';

    if (!$first) respondError('First name is required');
    if (!$last)  respondError('Last name is required');
    if (!$email) respondError('Valid email is required');

    try {
        if ($password) {
            if (strlen($password) < 8) respondError('Password must be at least 8 characters');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE helpdesk_users SET first_name=?, last_name=?, email=?, org=?, password_hash=? WHERE id=?');
            $stmt->execute([$first, $last, $email, $org, $hash, $id]);
        } else {
            $stmt = $db->prepare('UPDATE helpdesk_users SET first_name=?, last_name=?, email=?, org=? WHERE id=?');
            $stmt->execute([$first, $last, $email, $org, $id]);
        }
    } catch (PDOException $e) {
        respondError('An account with this email already exists');
    }

    respond(['success' => true, 'message' => 'User updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respondError('User ID required');

    $stmt = $db->prepare('DELETE FROM helpdesk_users WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) respondError('User not found', 404);

    respond(['success' => true, 'message' => 'User deleted']);
}

respondError('Method not allowed', 405);
