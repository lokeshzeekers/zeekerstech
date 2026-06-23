<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Database Configuration
//  File: api/config.php
//  ⚠ Update DB_HOST, DB_NAME, DB_USER, DB_PASS with your
//    Hostinger MySQL credentials before deploying.
// ─────────────────────────────────────────────────────────────

define('DB_HOST', 'localhost');       // Hostinger: usually 'localhost'
define('DB_NAME', 'your_db_name');   // e.g. u123456789_zeekers
define('DB_USER', 'your_db_user');   // e.g. u123456789_admin
define('DB_PASS', 'your_db_pass');   // Your MySQL password
define('DB_CHARSET', 'utf8mb4');

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
function requireAdminAuth(): array {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($auth, 'Bearer ')) respondError('Unauthorized', 401);
    $token = substr($auth, 7);
    $payload = jwtDecode($token);
    if (!$payload || ($payload['role'] ?? '') !== 'admin') respondError('Unauthorized', 401);
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
