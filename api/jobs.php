<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Jobs API
//
//  PUBLIC:
//    GET  /api/jobs.php               → active jobs
//    GET  /api/jobs.php?id=1          → single job
//
//  ADMIN (requires Bearer token):
//    GET  /api/jobs.php?all=1         → all jobs
//    POST /api/jobs.php               → create job
//    PUT  /api/jobs.php?id=1          → update job
//    DELETE /api/jobs.php?id=1        → delete job
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// The 'experience' field exists in the admin job-posting form but was never
// added to the schema, so it was always silently discarded. Add it here
// idempotently so existing installs pick it up without a manual migration.
function ensureJobColumns(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $existing = $db->query('SHOW COLUMNS FROM jobs')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('experience', $existing, true)) {
        $db->exec("ALTER TABLE jobs ADD COLUMN `experience` VARCHAR(100) DEFAULT ''");
    }
    if (!in_array('is_new', $existing, true)) {
        $db->exec("ALTER TABLE jobs ADD COLUMN `is_new` TINYINT(1) DEFAULT 0");
    }
}
ensureJobColumns($db);

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM jobs WHERE id = ?');
        $stmt->execute([$id]);
        $job = $stmt->fetch();
        if (!$job) respondError('Job not found', 404);
        respond(['success' => true, 'data' => $job]);
    }

    if (isset($_GET['all'])) {
        requireAdminAuth();
        $stmt = $db->query('SELECT * FROM jobs ORDER BY created_at DESC');
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Public: active jobs only
    $stmt = $db->query('SELECT * FROM jobs WHERE active = 1 ORDER BY created_at DESC');
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (create) ─────────────────────────────────────────────
if ($method === 'POST') {
    requireAdminAuth();
    $input = getInput();

    $title        = sanitize($input['title'] ?? '');
    $department   = sanitize($input['department'] ?? '');
    $location     = sanitize($input['location'] ?? 'Coimbatore, Tamil Nadu');
    $type         = sanitize($input['type'] ?? 'Full-time');
    $experience   = sanitize($input['experience'] ?? '');
    $description  = $input['description'] ?? '';
    $requirements = $input['requirements'] ?? '';
    $active       = (bool)($input['active'] ?? true);
    $isNew        = (bool)($input['is_new'] ?? false);

    if (!$title) respondError('Title is required');

    $stmt = $db->prepare(
        'INSERT INTO jobs (title, department, location, type, experience, description, requirements, active, is_new)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $department, $location, $type, $experience, $description, $requirements, $active ? 1 : 0, $isNew ? 1 : 0]);
    $newId = $db->lastInsertId();

    respond(['success' => true, 'id' => $newId, 'message' => 'Job created'], 201);
}

// ── PUT (update) ──────────────────────────────────────────────
if ($method === 'PUT') {
    requireAdminAuth();
    if (!$id) respondError('Job ID required');

    $input        = getInput();
    $title        = sanitize($input['title'] ?? '');
    $department   = sanitize($input['department'] ?? '');
    $location     = sanitize($input['location'] ?? '');
    $type         = sanitize($input['type'] ?? '');
    $experience   = sanitize($input['experience'] ?? '');
    $description  = $input['description'] ?? '';
    $requirements = $input['requirements'] ?? '';
    $active       = (bool)($input['active'] ?? true);
    $isNew        = (bool)($input['is_new'] ?? false);

    if (!$title) respondError('Title is required');

    // Check existence separately — UPDATE's affected-row count is 0 both when
    // the row is missing AND when the new values are identical to the old ones,
    // so it can't be used alone to detect "not found".
    $exists = $db->prepare('SELECT id FROM jobs WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetch()) respondError('Job not found', 404);

    $stmt = $db->prepare(
        'UPDATE jobs SET title=?, department=?, location=?, type=?, experience=?, description=?, requirements=?, active=?, is_new=?
         WHERE id=?'
    );
    $stmt->execute([$title, $department, $location, $type, $experience, $description, $requirements, $active ? 1 : 0, $isNew ? 1 : 0, $id]);

    respond(['success' => true, 'message' => 'Job updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdminAuth();
    if (!$id) respondError('Job ID required');

    $stmt = $db->prepare('DELETE FROM jobs WHERE id = ?');
    $stmt->execute([$id]);
    // DELETE's rowCount is a reliable existence check (unlike UPDATE's).
    if ($stmt->rowCount() === 0) respondError('Job not found', 404);
    respond(['success' => true, 'message' => 'Job deleted']);
}

respondError('Method not allowed', 405);
