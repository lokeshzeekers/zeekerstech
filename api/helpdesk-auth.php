<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Helpdesk User Auth API
//  POST /api/helpdesk-auth.php
//
//  Actions:
//    login    { email, password }             → { user, token }
//    register { first_name, last_name, email,
//               password, org }               → { user, token }
//
//  Uses the helpdesk_users table (separate from admins table).
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed', 405);

$input  = getInput();
$action = $input['action'] ?? '';

// ── Ensure helpdesk_users table exists ───────────────────────
$db = getDB();
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

// ── LOGIN ─────────────────────────────────────────────────────
if ($action === 'login') {
    $email    = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';

    if (!$email || !$password) respondError('Email and password required');

    $stmt = $db->prepare('SELECT * FROM helpdesk_users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        respondError('Invalid email or password', 401);
    }

    $token = jwtEncode([
        'id'    => $user['id'],
        'email' => $user['email'],
        'role'  => 'user',
        'exp'   => time() + 86400 * 30  // 30 days
    ]);

    respond([
        'success' => true,
        'token'   => $token,
        'user'    => [
            'userId' => $user['id'],
            'name'   => $user['first_name'] . ' ' . $user['last_name'],
            'email'  => $user['email'],
            'org'    => $user['org'],
            'avatar' => $user['avatar'],
            'token'  => $token
        ]
    ]);
}

// ── REGISTER ──────────────────────────────────────────────────
if ($action === 'register') {
    $first    = sanitize($input['first_name'] ?? '');
    $last     = sanitize($input['last_name'] ?? '');
    $email    = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';
    $org      = sanitize($input['org'] ?? '');

    if (!$first || !$last) respondError('First and last name required');
    if (!$email)           respondError('Valid email required');
    if (strlen($password) < 8) respondError('Password must be at least 8 characters');

    // Check duplicate
    $check = $db->prepare('SELECT id FROM helpdesk_users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) respondError('An account with this email already exists');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        'INSERT INTO helpdesk_users (first_name, last_name, email, password_hash, org)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$first, $last, $email, $hash, $org]);
    $newId = $db->lastInsertId();

    $token = jwtEncode([
        'id'    => $newId,
        'email' => $email,
        'role'  => 'user',
        'exp'   => time() + 86400 * 30
    ]);

    respond([
        'success' => true,
        'token'   => $token,
        'user'    => [
            'userId' => $newId,
            'name'   => "$first $last",
            'email'  => $email,
            'org'    => $org,
            'avatar' => '',
            'token'  => $token
        ]
    ], 201);
}

respondError('Invalid action. Use login or register.');
