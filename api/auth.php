<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Admin Auth API
//  POST /api/auth.php  { username, password } → { token }
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed', 405);

$input = getInput();
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (!$username || !$password) respondError('Username and password required');

// Check against DB admins table
$db = getDB();
$stmt = $db->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
$stmt->execute([$username, $username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    respondError('Invalid credentials', 401);
}

$token = jwtEncode([
    'id'   => $admin['id'],
    'role' => 'admin',
    'user' => $admin['username'],
    'exp'  => time() + 86400 * 7  // 7 days
]);

respond(['success' => true, 'token' => $token, 'user' => $admin['username']]);
