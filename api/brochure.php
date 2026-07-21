<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Brochure Download API
//
//  PUBLIC:
//    POST /api/brochure.php   → capture a brochure-download lead
//        Body: { name, mobile, email, product, purpose, type }
//        type: 'product' → products page brochure
//        type: 'lab'     → AICTE-IDEA Lab brochure
//
//  ADMIN (requires Bearer token):
//    GET    /api/brochure.php        → all leads
//    DELETE /api/brochure.php?id=1   → delete a lead
//
//  Leads are saved to the database and shown in the admin panel's
//  "Brochure Leads" tab (no email notification — that turned out to be
//  unreliable on this host, so the admin panel is now the source of
//  truth instead).
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

$db->exec("
    CREATE TABLE IF NOT EXISTS brochure_leads (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255)  NOT NULL,
        mobile     VARCHAR(20)   NOT NULL,
        email      VARCHAR(255)  NOT NULL,
        product    VARCHAR(255)  NOT NULL,
        purpose    TEXT,
        type       VARCHAR(20)   DEFAULT 'product',
        created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    )
");

// ── GET (admin) ───────────────────────────────────────────────
if ($method === 'GET') {
    requireAdminAuth();
    $stmt = $db->query('SELECT * FROM brochure_leads ORDER BY created_at DESC');
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (public) ─────────────────────────────────────────────
if ($method === 'POST') {
    $input   = getInput();
    $name    = sanitize($input['name']    ?? '');
    $mobile  = sanitize($input['mobile']  ?? '');
    $email   = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $product = sanitize($input['product'] ?? '');
    $purpose = sanitize($input['purpose'] ?? '');
    $type    = sanitize($input['type']    ?? 'product'); // 'product' or 'lab'

    if (!$name)    respondError('Name is required');
    if (!$mobile)  respondError('Mobile number is required');
    if (!$email)   respondError('Valid email is required');
    if (!$product) respondError('Product is required');
    if (!$purpose) respondError('Purpose is required');

    $stmt = $db->prepare(
        'INSERT INTO brochure_leads (name, mobile, email, product, purpose, type)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $mobile, $email, $product, $purpose, $type]);

    // ── Return brochure file path ────────────────────────────────
    $brochureMap = [
        'ZwelDAQ'          => 'brochures/ZwelDAQ.pdf',
        'Zeekers IoT Kit'  => 'brochures/Zeekers-IoT-Kit.pdf',
        'ZeekWeigh'        => 'brochures/ZeekWeigh.pdf',
        'Zeevior'          => 'brochures/Zeevior.pdf',
        'Zeeclean'         => 'brochures/Zeeclean.pdf',
        'AICTE IDEA Lab'   => 'brochures/AICTE-LAB.pdf',
    ];

    $file = $brochureMap[$product] ?? null;

    respond([
        'success'  => true,
        'message'  => 'Lead captured',
        'file'     => $file,
    ], 201);
}

// ── DELETE (admin) ────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdminAuth();
    if (!$id) respondError('Lead ID required');

    $stmt = $db->prepare('DELETE FROM brochure_leads WHERE id = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) respondError('Lead not found', 404);

    respond(['success' => true, 'message' => 'Lead deleted']);
}

respondError('Method not allowed', 405);
