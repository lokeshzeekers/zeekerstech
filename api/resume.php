<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Resume File Server
//
//  GET /api/resume.php?file=resume_xxx.pdf&token=<admin JWT>
//
//  Streams back a resume previously saved by upload-resume.php.
//  Admin-only: a plain link click can't send an Authorization header,
//  so the admin panel appends the admin's token as a query parameter
//  instead. The filename is strictly validated (no path traversal,
//  fixed pattern only) before touching the filesystem.
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
$payload = $token ? jwtDecode($token) : null;
if (!$payload || ($payload['role'] ?? '') !== 'admin') {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo 'Unauthorized';
    exit;
}

$file = $_GET['file'] ?? '';
if (!preg_match('/^resume_[A-Za-z0-9_]+\.(pdf|doc|docx)$/', $file)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid file reference';
    exit;
}

$path = __DIR__ . '/uploads/resumes/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'File not found';
    exit;
}

$mimes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
