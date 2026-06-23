<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Admin Users API
//  (Admin panel user management only — not public)
//
//  GET    /api/admins.php        → list admins
//  POST   /api/admins.php        → create admin
//  PUT    /api/admins.php?id=1   → update admin
//  DELETE /api/admins.php?id=1   → delete admin
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

requireAdminAuth();  // All routes protected

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $db->query('SELECT id, username, email, role, created_at FROM admins ORDER BY created_at DESC');
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (create) ─────────────────────────────────────────────
if ($method === 'POST') {
    $input    = getInput();
    $username = sanitize($input['username'] ?? '');
    $email    = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';
    $role     = sanitize($input['role'] ?? 'admin');

    if (!$username) respondError('Username is required');
    if (!$email)    respondError('Valid email is required');
    if (strlen($password) < 8) respondError('Password must be at least 8 characters');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO admins (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
    try {
        $stmt->execute([$username, $email, $hash, $role]);
        respond(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Admin created'], 201);
    } catch (PDOException $e) {
        respondError('Username or email already exists');
    }
}

// ── PUT (update) ──────────────────────────────────────────────
if ($method === 'PUT') {
    if (!$id) respondError('Admin ID required');
    $input    = getInput();
    $username = sanitize($input['username'] ?? '');
    $email    = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $role     = sanitize($input['role'] ?? 'admin');
    $password = $input['password'] ?? '';

    if (!$username) respondError('Username is required');
    if (!$email)    respondError('Valid email is required');

    if ($password) {
        if (strlen($password) < 8) respondError('Password must be at least 8 characters');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE admins SET username=?, email=?, password_hash=?, role=? WHERE id=?');
        $stmt->execute([$username, $email, $hash, $role, $id]);
    } else {
        $stmt = $db->prepare('UPDATE admins SET username=?, email=?, role=? WHERE id=?');
        $stmt->execute([$username, $email, $role, $id]);
    }

    respond(['success' => true, 'message' => 'Admin updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) respondError('Admin ID required');

    // Prevent deleting last admin
    $count = $db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count <= 1) respondError('Cannot delete the last admin account');

    $stmt = $db->prepare('DELETE FROM admins WHERE id = ?');
    $stmt->execute([$id]);
    respond(['success' => true, 'message' => 'Admin deleted']);
}

respondError('Method not allowed', 405);
