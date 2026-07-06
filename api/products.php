<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Lab Products Catalog API
//
//  PUBLIC:
//    GET /api/products.php            → all products (used by the
//                                        helpdesk "Affected Products" picker)
//
//  ADMIN (requires Bearer token):
//    POST   /api/products.php         → create product
//    PUT    /api/products.php?id=1    → update product
//    DELETE /api/products.php?id=1    → delete product
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

$db->exec("
    CREATE TABLE IF NOT EXISTS lab_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        sub VARCHAR(255) DEFAULT '',
        category VARCHAR(50) NOT NULL DEFAULT 'electrical',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$validCategories = ['electrical', 'mandatory', 'optional', 'industrial'];

// ── GET (public — no auth required) ───────────────────────────
if ($method === 'GET') {
    $stmt = $db->query('SELECT * FROM lab_products ORDER BY category, name');
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (create) ─────────────────────────────────────────────
if ($method === 'POST') {
    requireAdminAuth();
    $input = getInput();

    $name     = sanitize($input['name'] ?? '');
    $sub      = sanitize($input['sub'] ?? '');
    $category = sanitize($input['category'] ?? 'electrical');

    if (!$name) respondError('Name is required');
    if (!in_array($category, $validCategories, true)) $category = 'electrical';

    $stmt = $db->prepare('INSERT INTO lab_products (name, sub, category) VALUES (?, ?, ?)');
    $stmt->execute([$name, $sub, $category]);
    $newId = $db->lastInsertId();

    respond(['success' => true, 'id' => $newId, 'message' => 'Product created'], 201);
}

// ── PUT (update) ──────────────────────────────────────────────
if ($method === 'PUT') {
    requireAdminAuth();
    if (!$id) respondError('Product ID required');

    $input    = getInput();
    $name     = sanitize($input['name'] ?? '');
    $sub      = sanitize($input['sub'] ?? '');
    $category = sanitize($input['category'] ?? 'electrical');

    if (!$name) respondError('Name is required');
    if (!in_array($category, $validCategories, true)) $category = 'electrical';

    $exists = $db->prepare('SELECT id FROM lab_products WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetch()) respondError('Product not found', 404);

    $stmt = $db->prepare('UPDATE lab_products SET name=?, sub=?, category=? WHERE id=?');
    $stmt->execute([$name, $sub, $category, $id]);

    respond(['success' => true, 'message' => 'Product updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdminAuth();
    if (!$id) respondError('Product ID required');

    $stmt = $db->prepare('DELETE FROM lab_products WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) respondError('Product not found', 404);

    respond(['success' => true, 'message' => 'Product deleted']);
}

respondError('Method not allowed', 405);
