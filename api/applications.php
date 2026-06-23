<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Job Applications API
//
//  PUBLIC:
//    POST /api/applications.php  → submit application
//
//  ADMIN (requires Bearer token):
//    GET  /api/applications.php         → all applications
//    GET  /api/applications.php?id=1    → single application
//    PUT  /api/applications.php?id=1    → update status
//    DELETE /api/applications.php?id=1  → delete
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET (admin) ───────────────────────────────────────────────
if ($method === 'GET') {
    requireAdminAuth();

    if ($id) {
        $stmt = $db->prepare('SELECT a.*, j.title as job_title FROM applications a LEFT JOIN jobs j ON a.job_id = j.id WHERE a.id = ?');
        $stmt->execute([$id]);
        $app = $stmt->fetch();
        if (!$app) respondError('Application not found', 404);
        respond(['success' => true, 'data' => $app]);
    }

    $stmt = $db->query(
        'SELECT a.*, j.title as job_title, j.department
         FROM applications a
         LEFT JOIN jobs j ON a.job_id = j.id
         ORDER BY a.created_at DESC'
    );
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (public submit) ──────────────────────────────────────
if ($method === 'POST') {
    $input = getInput();

    $job_id       = (int)($input['job_id'] ?? 0);
    $name         = sanitize($input['name'] ?? '');
    $email        = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone        = sanitize($input['phone'] ?? '');
    $cover_letter = sanitize($input['cover_letter'] ?? '');
    $resume_url   = sanitize($input['resume_url'] ?? '');

    if (!$name)  respondError('Name is required');
    if (!$email) respondError('Valid email is required');

    $stmt = $db->prepare(
        'INSERT INTO applications (job_id, name, email, phone, resume_url, cover_letter)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$job_id ?: null, $name, $email, $phone, $resume_url, $cover_letter]);

    respond(['success' => true, 'message' => 'Application submitted successfully'], 201);
}

// ── PUT (admin: update status) ────────────────────────────────
if ($method === 'PUT') {
    requireAdminAuth();
    if (!$id) respondError('Application ID required');

    $input  = getInput();
    $status = sanitize($input['status'] ?? '');
    if (!in_array($status, ['pending', 'reviewing', 'shortlisted', 'rejected', 'hired'])) {
        respondError('Invalid status');
    }

    $stmt = $db->prepare('UPDATE applications SET status=? WHERE id=?');
    $stmt->execute([$status, $id]);

    respond(['success' => true, 'message' => 'Status updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdminAuth();
    if (!$id) respondError('Application ID required');

    $stmt = $db->prepare('DELETE FROM applications WHERE id = ?');
    $stmt->execute([$id]);

    respond(['success' => true, 'message' => 'Application deleted']);
}

respondError('Method not allowed', 405);
