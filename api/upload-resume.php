<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Resume Upload API
//
//  POST /api/upload-resume.php   (multipart/form-data, field name "file")
//    → { success: true, url: '/api/resume.php?file=resume_xxx.pdf', name: 'original.pdf' }
//
//  Saves the file to disk and returns a small URL string instead of an
//  embedded base64 data URL. Real multipart uploads go through PHP's
//  own upload handling (upload_max_filesize / post_max_size) rather
//  than the JSON request body, which is what was hitting a 413 from
//  a web-server/WAF body-size cap even on a modestly-sized resume.
//
//  The file is saved under api/uploads/ (blocked from direct access —
//  see api/uploads/.htaccess) and can only be retrieved through
//  resume.php, which validates the filename before streaming it back.
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed', 405);

if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
    respondError('No file uploaded');
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE  => 'File exceeds the server upload limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload limit',
        UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded — please try again',
        UPLOAD_ERR_NO_FILE   => 'No file was uploaded',
    ];
    respondError($errors[$file['error']] ?? 'Upload failed', 400);
}

$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    respondError('File is too large (max 5MB)');
}

$originalName = basename($file['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedExt = ['pdf', 'doc', 'docx'];
if (!in_array($ext, $allowedExt, true)) {
    respondError('Only PDF, DOC, or DOCX files are allowed');
}

// Basic real-content check (not foolproof, but catches an obviously
// mismatched file — e.g. a renamed executable).
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',              // .docx is a zip container and can sniff as this
        'application/octet-stream',     // some servers report this for valid docs
    ];
    if ($mime && !in_array($mime, $allowedMimes, true)) {
        respondError('That file does not look like a valid PDF/DOC/DOCX');
    }
}

$uploadDir = __DIR__ . '/uploads/resumes/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$safeName = 'resume_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$destPath = $uploadDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    respondError('Failed to save the uploaded file', 500);
}

respond([
    'success' => true,
    'url'     => '/api/resume.php?file=' . urlencode($safeName),
    'name'    => $originalName,
], 201);
