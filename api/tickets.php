<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Helpdesk Tickets API
//
//  PUBLIC (user session token):
//    GET  /api/tickets.php              → user's own tickets
//    POST /api/tickets.php              → create ticket
//
//  ADMIN (admin Bearer token):
//    GET  /api/tickets.php?all=1        → all tickets
//    PUT  /api/tickets.php?id=ZTS-001   → update status/response
//    DELETE /api/tickets.php?id=ZTS-001 → delete
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? null;  // ticket_number e.g. ZTS-123456

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    if (isset($_GET['all'])) {
        requireAdminAuth();
        $stmt = $db->query('SELECT * FROM tickets ORDER BY created_at DESC');
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // User: get their tickets by email (passed as query param)
    $email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) respondError('Email required');

    $stmt = $db->prepare('SELECT * FROM tickets WHERE email = ? ORDER BY created_at DESC');
    $stmt->execute([$email]);
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (create ticket) ──────────────────────────────────────
if ($method === 'POST') {
    $input = getInput();

    $name     = sanitize($input['name'] ?? '');
    $email    = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject  = sanitize($input['subject'] ?? '');
    $message  = sanitize($input['message'] ?? '');
    $priority = sanitize($input['priority'] ?? 'medium');

    if (!$name)    respondError('Name is required');
    if (!$email)   respondError('Valid email is required');
    if (!$subject) respondError('Subject is required');
    if (!$message) respondError('Message is required');

    if (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
        $priority = 'medium';
    }

    // Generate ticket number: ZTS-XXXXXX
    $ticket_number = 'ZTS-' . strtoupper(substr(md5(uniqid()), 0, 6));

    $stmt = $db->prepare(
        'INSERT INTO tickets (ticket_number, name, email, subject, message, priority)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$ticket_number, $name, $email, $subject, $message, $priority]);

    respond([
        'success'       => true,
        'ticket_number' => $ticket_number,
        'message'       => 'Ticket created successfully'
    ], 201);
}

// ── PUT (admin: update ticket) ────────────────────────────────
if ($method === 'PUT') {
    requireAdminAuth();
    if (!$id) respondError('Ticket number required');

    $input    = getInput();
    $status   = sanitize($input['status'] ?? '');
    $priority = sanitize($input['priority'] ?? '');
    $response = $input['admin_response'] ?? null;

    // Build dynamic update
    $sets   = [];
    $params = [];

    if ($status && in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
        $sets[] = 'status=?';
        $params[] = $status;
    }
    if ($priority && in_array($priority, ['low', 'medium', 'high', 'critical'])) {
        $sets[] = 'priority=?';
        $params[] = $priority;
    }
    if ($response !== null) {
        // Store admin response in message column with prefix (no extra column needed)
        $sets[] = 'message=CONCAT(message, ?)';
        $params[] = "\n\n[Admin Response]: " . $response;
    }

    if (empty($sets)) respondError('Nothing to update');

    $params[] = $id;
    $sql = 'UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE ticket_number=?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    respond(['success' => true, 'message' => 'Ticket updated']);
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAdminAuth();
    if (!$id) respondError('Ticket number required');

    $stmt = $db->prepare('DELETE FROM tickets WHERE ticket_number = ?');
    $stmt->execute([$id]);
    respond(['success' => true, 'message' => 'Ticket deleted']);
}

respondError('Method not allowed', 405);
