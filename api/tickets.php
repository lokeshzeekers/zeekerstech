<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Helpdesk Tickets API
//
//  PUBLIC:
//    POST /api/tickets.php              → create ticket
//    GET  /api/tickets.php?email=...    → guest lookup by email (no login)
//
//  HELPDESK USER (user Bearer token):
//    GET    /api/tickets.php            → my tickets (by token email)
//    PUT    /api/tickets.php?id=ZTS-001 → update MY ticket (message/status=closed only)
//    DELETE /api/tickets.php?id=ZTS-001 → delete MY ticket
//
//  ADMIN (admin Bearer token):
//    GET    /api/tickets.php?all=1      → all tickets
//    PUT    /api/tickets.php?id=ZTS-001 → update any ticket (status/priority/response)
//    DELETE /api/tickets.php?id=ZTS-001 → delete any ticket
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = $_GET['id'] ?? null;  // ticket_number e.g. ZTS-123456

// The original tickets table (see setup.sql) only has name/email/subject/
// message/priority/status. Category, affected products, and attachments
// were previously kept client-side only, which is why they never showed
// up on the admin side. Add the columns here (idempotently) so both the
// admin panel and the helpdesk dashboard read/write the same data.
function ensureTicketColumns(PDO $db): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $existing = $db->query('SHOW COLUMNS FROM tickets')->fetchAll(PDO::FETCH_COLUMN);
    $columns = [
        'category'        => "VARCHAR(150) DEFAULT ''",
        'sub_category'    => "VARCHAR(150) DEFAULT ''",
        'products'        => 'TEXT NULL',
        'attachment_name' => "VARCHAR(255) DEFAULT ''",
        'attachment_data' => 'LONGTEXT NULL',
    ];
    foreach ($columns as $name => $definition) {
        if (!in_array($name, $existing, true)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN `$name` $definition");
        }
    }
}
ensureTicketColumns($db);

function findTicket(PDO $db, string $ticketNumber): ?array {
    $stmt = $db->prepare('SELECT * FROM tickets WHERE ticket_number = ?');
    $stmt->execute([$ticketNumber]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    if (isset($_GET['all'])) {
        requireAdminAuth();
        $stmt = $db->query('SELECT * FROM tickets ORDER BY created_at DESC');
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Logged-in helpdesk user: always use the email from their token,
    // never trust a client-supplied email for this branch.
    $payload = getBearerPayload();
    if ($payload && ($payload['role'] ?? '') === 'user') {
        $stmt = $db->prepare('SELECT * FROM tickets WHERE email = ? ORDER BY created_at DESC');
        $stmt->execute([$payload['email']]);
        respond(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // Guest/public: lookup by email query param (no login yet)
    $email = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) respondError('Email required');

    $stmt = $db->prepare('SELECT * FROM tickets WHERE email = ? ORDER BY created_at DESC');
    $stmt->execute([$email]);
    respond(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ── POST (create ticket) ──────────────────────────────────────
if ($method === 'POST') {
    $input = getInput();

    $name         = sanitize($input['name'] ?? '');
    $email        = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject      = sanitize($input['subject'] ?? '');
    $message      = sanitize($input['message'] ?? '');
    $priority     = sanitize($input['priority'] ?? 'medium');
    $category     = sanitize($input['category'] ?? '');
    $subCategory  = sanitize($input['sub_category'] ?? '');
    $products     = $input['products'] ?? [];
    $attachName   = sanitize($input['attachment_name'] ?? '');
    $attachData   = $input['attachment_data'] ?? '';

    if (!$name)    respondError('Name is required');
    if (!$email)   respondError('Valid email is required');
    if (!$subject) respondError('Subject is required');
    if (!$message) respondError('Message is required');

    if (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
        $priority = 'medium';
    }

    // Attachments are stored inline as a data URL. Cap the size so a large
    // photo/video can't blow past the DB's max packet size or PHP's post limits.
    // Base64 inflates size by ~33%, so this matches the frontend's 5MB raw-file limit.
    if ($attachData && strlen($attachData) > 7 * 1024 * 1024) {
        respondError('Attachment is too large (max 5MB). Please attach a smaller file.');
    }

    $productsJson = is_array($products) ? json_encode(array_values($products)) : null;

    // Generate ticket number: ZTS-XXXXXX
    $ticket_number = 'ZTS-' . strtoupper(substr(md5(uniqid()), 0, 6));

    $stmt = $db->prepare(
        'INSERT INTO tickets (ticket_number, name, email, subject, message, priority, category, sub_category, products, attachment_name, attachment_data)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $ticket_number, $name, $email, $subject, $message, $priority,
        $category, $subCategory, $productsJson, $attachName, $attachData ?: null
    ]);

    respond([
        'success'       => true,
        'ticket_number' => $ticket_number,
        'message'       => 'Ticket created successfully'
    ], 201);
}

// ── PUT (update) ────────────────────────────────────────────
if ($method === 'PUT') {
    $payload = requireAnyAuth();
    if (!$id) respondError('Ticket number required');

    $ticket = findTicket($db, $id);
    if (!$ticket) respondError('Ticket not found', 404);

    $isAdmin = ($payload['role'] ?? '') === 'admin';
    $isOwner = ($payload['role'] ?? '') === 'user' && strcasecmp($payload['email'] ?? '', $ticket['email']) === 0;
    if (!$isAdmin && !$isOwner) respondError('Unauthorized', 403);

    $input  = getInput();
    $sets   = [];
    $params = [];

    if ($isAdmin) {
        // Admin can change status, priority, and append an official response.
        $status   = sanitize($input['status'] ?? '');
        $priority = sanitize($input['priority'] ?? '');
        $response = $input['admin_response'] ?? null;

        if ($status && in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            $sets[] = 'status=?';
            $params[] = $status;
        }
        if ($priority && in_array($priority, ['low', 'medium', 'high', 'critical'])) {
            $sets[] = 'priority=?';
            $params[] = $priority;
        }
        if ($response !== null && trim($response) !== '') {
            $sets[] = 'message=CONCAT(message, ?)';
            $params[] = "\n\n[Admin Response]: " . sanitize($response);
        }
    } else {
        // Ticket owner (helpdesk user): can add a follow-up message,
        // or close their own ticket. Cannot touch priority or impersonate an admin response.
        $followUp = $input['message'] ?? null;
        $status   = sanitize($input['status'] ?? '');

        if ($followUp !== null && trim($followUp) !== '') {
            $sets[] = 'message=CONCAT(message, ?)';
            $params[] = "\n\n[User Update]: " . sanitize($followUp);
        }
        if ($status === 'closed' && $ticket['status'] !== 'closed') {
            $sets[] = 'status=?';
            $params[] = 'closed';
        }
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
    $payload = requireAnyAuth();
    if (!$id) respondError('Ticket number required');

    $ticket = findTicket($db, $id);
    if (!$ticket) respondError('Ticket not found', 404);

    $isAdmin = ($payload['role'] ?? '') === 'admin';
    $isOwner = ($payload['role'] ?? '') === 'user' && strcasecmp($payload['email'] ?? '', $ticket['email']) === 0;
    if (!$isAdmin && !$isOwner) respondError('Unauthorized', 403);

    $stmt = $db->prepare('DELETE FROM tickets WHERE ticket_number = ?');
    $stmt->execute([$id]);
    respond(['success' => true, 'message' => 'Ticket deleted']);
}

respondError('Method not allowed', 405);
