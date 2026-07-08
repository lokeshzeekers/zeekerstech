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

// resume_url was TEXT (64KB limit) and there was no dedicated filename
// column; widen it and add resume_name so the actual uploaded file
// (as a data URL) and its original filename can both be stored.
function ensureApplicationColumns(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $cols = $db->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('resume_name', $cols, true)) {
        $db->exec("ALTER TABLE applications ADD COLUMN `resume_name` VARCHAR(255) DEFAULT ''");
    }
    $urlCol = $db->query("SHOW COLUMNS FROM applications LIKE 'resume_url'")->fetch();
    if ($urlCol && stripos($urlCol['Type'], 'longtext') === false) {
        $db->exec('ALTER TABLE applications MODIFY COLUMN resume_url LONGTEXT');
    }
}
ensureApplicationColumns($db);

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
    $resume_name  = sanitize($input['resume_name'] ?? '');
    $resume_url   = $input['resume_url'] ?? ''; // data URL — not run through sanitize() to avoid touching base64 content

    if (!$name)  respondError('Name is required');
    if (!$email) respondError('Valid email is required');

    // The resume is stored inline as a data URL. Cap the size so a large
    // file can't blow past the DB's max packet size or PHP's post limits.
    if ($resume_url && strlen($resume_url) > 7 * 1024 * 1024) {
        respondError('Resume file is too large (max 5MB). Please attach a smaller file.');
    }

    $stmt = $db->prepare(
        'INSERT INTO applications (job_id, name, email, phone, resume_name, resume_url, cover_letter)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$job_id ?: null, $name, $email, $phone, $resume_name, $resume_url, $cover_letter]);

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
