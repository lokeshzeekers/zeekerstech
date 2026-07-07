<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Contact API
//
//  PUBLIC:
//    POST /api/contact.php   → submit contact message
//
//  ADMIN (requires Bearer token):
//    GET  /api/contact.php           → all messages
//    PUT  /api/contact.php?id=1      → mark read/unread
//    DELETE /api/contact.php?id=1    → delete
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── GET (admin) ───────────────────────────────────────────────
if ($method === 'GET') {
    requireAdminAuth();
    $stmt = $db->query('SELECT * FROM contacts ORDER BY created_at DESC');
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (public) ─────────────────────────────────────────────
if ($method === 'POST') {
    $input = getInput();

    $name    = sanitize($input['name'] ?? '');
    $email   = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone   = sanitize($input['phone'] ?? '');
    $subject = sanitize($input['subject'] ?? 'General Enquiry');
    $message = sanitize($input['message'] ?? '');

    if (!$name)    respondError('Name is required');
    if (!$email)   respondError('Valid email is required');
    if (!$message) respondError('Message is required');

    $stmt = $db->prepare(
        'INSERT INTO contacts (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $phone, $subject, $message]);

    $isDemo = stripos($subject, 'demo') !== false;
    $isArticle = stripos($subject, 'article') !== false;
    $heading = $isDemo ? '🖥️ New Product Demo Request' : ($isArticle ? '📝 New Blog Article Submission' : '✉️ New Contact Enquiry');
    $subtitle = $isArticle
        ? 'Someone pitched an article idea for the ZTS Blog'
        : 'Someone submitted the contact form on zeekerstechnology.com';

    sendLeadEmail(
        "New Contact Form Submission — $subject",
        $heading,
        $subtitle,
        [
            'Name'    => $name,
            'Email'   => "<a href='mailto:$email' style='color:#ff6b2b'>$email</a>",
            'Phone'   => $phone ? "<a href='tel:$phone' style='color:#ff6b2b'>$phone</a>" : null,
            'Subject' => $subject,
            'Message' => nl2br($message),
        ],
        $email
    );

    respond(['success' => true, 'message' => 'Message sent successfully'], 201);
}

// ── PUT (admin: update status) ────────────────────────────────
if ($method === 'PUT') {
    requireAdminAuth();
    if (!$id) respondError('Contact ID required');

    $input  = getInput();
    $status = sanitize($input['status'] ?? 'read');
    if (!in_array($status, ['read', 'unread', 'replied'])) respondError('Invalid status');

    $stmt = $db->prepare('UPDATE contacts SET status=? WHERE id=?');
    $stmt->execute([$status, $id]);
    respond(['success' => true, 'message' => 'Status updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdminAuth();
    if (!$id) respondError('Contact ID required');

    $stmt = $db->prepare('DELETE FROM contacts WHERE id = ?');
    $stmt->execute([$id]);
    respond(['success' => true, 'message' => 'Message deleted']);
}

respondError('Method not allowed', 405);
