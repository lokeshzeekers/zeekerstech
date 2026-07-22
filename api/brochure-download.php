<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Secure Brochure Download
//
//  GET /api/brochure-download.php?token=<token>
//
//  The token is issued by brochure.php only after a visitor
//  successfully submits the brochure request form (name, mobile,
//  email, purpose). It's a random 48-char value, valid for 30
//  minutes, tied to one specific brochure file. The PDFs themselves
//  live in /api/uploads/brochures/ — outside the public web root —
//  so they can no longer be downloaded via a guessed or shared
//  static URL like /brochures/ZwelDAQ.pdf.
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';

if (!$token || !preg_match('/^[a-f0-9]{48}$/', $token)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid download link.';
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    'SELECT file FROM brochure_downloads WHERE token = ? AND expires_at > NOW()'
);
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'This download link has expired. Please fill out the form again to get a fresh link.';
    exit;
}

// Filename is our own DB value (never user input at this point), but
// validate strictly anyway before touching the filesystem.
$file = $row['file'];
if (!preg_match('/^[A-Za-z0-9_\-]+\.pdf$/', $file)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Invalid file reference.';
    exit;
}

$path = __DIR__ . '/uploads/brochures/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Brochure file not found. Our team will email it to you shortly.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
