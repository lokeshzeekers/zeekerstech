<?php

require_once __DIR__ . '/config.local.php';

// JWT Secret — change this to a long random string
define('JWT_SECRET', 'ZTS_SUPER_SECRET_KEY_CHANGE_THIS_2025');

// Admin credentials (used for the admin panel login)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('admin@zts2025', PASSWORD_DEFAULT));
// ↑ Change 'admin@zts2025' to your real password, then
//   replace the define value with the output of:
//   php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT);"

// ─── PDO Connection ──────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ─── CORS + JSON Headers ─────────────────────────────────────
function setHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ─── Respond helpers ─────────────────────────────────────────
function respond(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function respondError(string $msg, int $code = 400): never {
    respond(['success' => false, 'error' => $msg], $code);
}

// ─── Simple JWT (no lib needed) ──────────────────────────────
function jwtEncode(array $payload): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$sig";
}

function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64url_decode($payload), true);
    if (isset($data['exp']) && $data['exp'] < time()) return null;
    return $data;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

// ─── Auth middleware ─────────────────────────────────────────

// Reads the Authorization header, if any, without forcing an error.
// Returns the decoded JWT payload, or null if absent/invalid/expired.
function getBearerPayload(): ?array {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!str_starts_with($auth, 'Bearer ')) return null;
    return jwtDecode(substr($auth, 7));
}

function requireAdminAuth(): array {
    $payload = getBearerPayload();
    if (!$payload || ($payload['role'] ?? '') !== 'admin') respondError('Unauthorized', 401);
    return $payload;
}

// Requires a logged-in helpdesk portal user (role=user).
function requireUserAuth(): array {
    $payload = getBearerPayload();
    if (!$payload || ($payload['role'] ?? '') !== 'user') respondError('Unauthorized', 401);
    return $payload;
}

// Requires either an admin or a helpdesk user token; returns the payload.
function requireAnyAuth(): array {
    $payload = getBearerPayload();
    if (!$payload || !in_array($payload['role'] ?? '', ['admin', 'user'], true)) {
        respondError('Unauthorized', 401);
    }
    return $payload;
}

// ─── Input helper ────────────────────────────────────────────
function getInput(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// ─── Lead notification email ─────────────────────────────────
// Shared by every public form that should notify the team: brochure
// downloads, contact/demo requests, job applications, and blog article
// submissions. Uses PHP's built-in mail() — best-effort; failures are
// swallowed (never blocks the form itself) since the lead is always
// saved to the database regardless of whether the email goes out.
define('LEAD_NOTIFICATION_EMAIL', 'hrzeekers@gmail.com');

function sendLeadEmail(string $subject, string $heading, string $subtitle, array $rows, ?string $replyTo = null): bool {
    $rowsHtml = '';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') continue;
        $rowsHtml .= "<div class='row'><span class='label'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</span><span class='value'>$value</span></div>";
    }

    $body = "
<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<style>
  body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
  .wrap { max-width: 560px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
  .header { background: linear-gradient(135deg, #ff6b2b, #ff8c00); padding: 28px 32px; }
  .header h2 { color: #fff; margin: 0; font-size: 20px; }
  .header p  { color: rgba(255,255,255,.85); margin: 6px 0 0; font-size: 13px; }
  .body { padding: 28px 32px; }
  .row { display: flex; border-bottom: 1px solid #f0f0f0; padding: 10px 0; }
  .row:last-child { border-bottom: none; }
  .label { color: #888; font-size: 12px; width: 130px; flex-shrink: 0; padding-top: 2px; }
  .value { color: #222; font-size: 14px; font-weight: 500; white-space: pre-wrap; }
  .footer { background: #fafafa; padding: 14px 32px; font-size: 11px; color: #aaa; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class='wrap'>
  <div class='header'>
    <h2>$heading</h2>
    <p>$subtitle</p>
  </div>
  <div class='body'>
    $rowsHtml
    <div class='row'><span class='label'>Time</span><span class='value'>" . date('d M Y, h:i A') . " IST</span></div>
  </div>
  <div class='footer'>Zeekers Technology Solutions · zeekerstechnology.com · Auto-generated notification</div>
</div>
</body>
</html>
";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Zeekers Website <noreply@zeekerstechnology.com>\r\n";
    if ($replyTo) $headers .= "Reply-To: $replyTo\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return @mail(LEAD_NOTIFICATION_EMAIL, $subject, $body, $headers);
}
