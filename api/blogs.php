<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Blog Posts API
//
//  PUBLIC:
//    GET  /api/blogs.php              → all published posts
//    GET  /api/blogs.php?id=1         → single post
//
//  ADMIN (requires Bearer token):
//    GET  /api/blogs.php?all=1        → all posts (incl. drafts)
//    POST /api/blogs.php              → create post
//    PUT  /api/blogs.php?id=1         → update post
//    DELETE /api/blogs.php?id=1       → delete post
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// image_url was defined as TEXT (64KB limit), which is too small for an
// embedded cover-image data URL. Widen it once, idempotently.
function ensureBlogColumns(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $col = $db->query("SHOW COLUMNS FROM blog_posts LIKE 'image_url'")->fetch();
    if ($col && stripos($col['Type'], 'longtext') === false) {
        $db->exec('ALTER TABLE blog_posts MODIFY COLUMN image_url LONGTEXT');
    }
}
ensureBlogColumns($db);

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    // Single post
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if (!$post) respondError('Post not found', 404);
        respond(['success' => true, 'data' => $post]);
    }

    // Admin: all posts
    if (isset($_GET['all'])) {
        requireAdminAuth();
        $stmt = $db->query('SELECT * FROM blog_posts ORDER BY created_at DESC');
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Public: published only
    $stmt = $db->query('SELECT * FROM blog_posts WHERE published = 1 ORDER BY created_at DESC');
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (create) ─────────────────────────────────────────────
if ($method === 'POST') {
    requireAdminAuth();
    $input = getInput();

    $title     = sanitize($input['title'] ?? '');
    $content   = $input['content'] ?? '';
    $author    = sanitize($input['author'] ?? 'Zeekers Team');
    $category  = sanitize($input['category'] ?? 'rnd');
    $image_url = $input['image_url'] ?? '';
    $published = (bool)($input['published'] ?? false);

    if (!$title) respondError('Title is required');

    // Cover image is stored inline as a data URL. Cap the size so a large
    // photo can't blow past the DB's max packet size or PHP's post limits.
    if ($image_url && strlen($image_url) > 7 * 1024 * 1024) {
        respondError('Cover image is too large (max 5MB). Please use a smaller image.');
    }

    $stmt = $db->prepare(
        'INSERT INTO blog_posts (title, content, author, category, image_url, published)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $content, $author, $category, $image_url, $published ? 1 : 0]);
    $newId = $db->lastInsertId();

    respond(['success' => true, 'id' => $newId, 'message' => 'Post created'], 201);
}

// ── PUT (update) ──────────────────────────────────────────────
if ($method === 'PUT') {
    requireAdminAuth();
    if (!$id) respondError('Post ID required');

    $input     = getInput();
    $title     = sanitize($input['title'] ?? '');
    $content   = $input['content'] ?? '';
    $author    = sanitize($input['author'] ?? '');
    $category  = sanitize($input['category'] ?? '');
    $image_url = $input['image_url'] ?? '';
    $published = (bool)($input['published'] ?? false);

    if (!$title) respondError('Title is required');

    if ($image_url && strlen($image_url) > 7 * 1024 * 1024) {
        respondError('Cover image is too large (max 5MB). Please use a smaller image.');
    }

    // Check existence separately — UPDATE's affected-row count is 0 both when
    // the row is missing AND when the new values are identical to the old ones,
    // so it can't be used alone to detect "not found".
    $exists = $db->prepare('SELECT id FROM blog_posts WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetch()) respondError('Post not found', 404);

    $stmt = $db->prepare(
        'UPDATE blog_posts
         SET title=?, content=?, author=?, category=?, image_url=?, published=?, updated_at=NOW()
         WHERE id=?'
    );
    $stmt->execute([$title, $content, $author, $category, $image_url, $published ? 1 : 0, $id]);

    respond(['success' => true, 'message' => 'Post updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdminAuth();
    if (!$id) respondError('Post ID required');

    $stmt = $db->prepare('DELETE FROM blog_posts WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) respondError('Post not found', 404);
    respond(['success' => true, 'message' => 'Post deleted']);
}

respondError('Method not allowed', 405);
