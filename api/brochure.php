<?php
// ─────────────────────────────────────────────────────────────
//  Zeekers Technology Solutions — Brochure Download API
//  POST /api/brochure.php
//
//  Body: { name, mobile, email, product, purpose, type }
//  type: 'product'  → products page brochure
//  type: 'lab'      → AICTE-IDEA Lab brochure
//
//  Saves lead to DB + sends email notification to Zeekers team
// ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php';
setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respondError('Method not allowed', 405);

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

// ── Save lead to DB ──────────────────────────────────────────
$db = getDB();
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

$stmt = $db->prepare(
    'INSERT INTO brochure_leads (name, mobile, email, product, purpose, type)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$name, $mobile, $email, $product, $purpose, $type]);

// ── Send email notification ───────────────────────────────────
$subject = ($type === 'lab')
    ? "New AICTE-IDEA Lab Brochure Download — $name"
    : "New Product Brochure Download — $product by $name";

$typeLabel = ($type === 'lab') ? 'AICTE-IDEA Lab Brochure' : 'Product Brochure';

$sent = sendLeadEmail(
    $subject,
    "📥 New $typeLabel Lead",
    'Someone downloaded a brochure from zeekerstechnology.com',
    [
        'Name'               => $name,
        'Email'              => "<a href='mailto:$email' style='color:#ff6b2b'>$email</a>",
        'Mobile'             => "<a href='tel:$mobile' style='color:#ff6b2b'>$mobile</a>",
        'Product / Brochure' => "<span style='display:inline-block;background:#fff3ec;color:#ff6b2b;border:1px solid #ffd5bc;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600'>$product</span>",
        'Purpose'            => $purpose,
        'Type'               => ucfirst($type) . ' Page',
    ],
    $email
);

// ── Return brochure file path ────────────────────────────────
$brochureMap = [
    'ZwelDAQ'          => 'brochures/ZwelDAQ.pdf',
    'ZweldMET'         => 'brochures/ZweldMET.pdf',
    'Zeekers IoT Kit'  => 'brochures/Zeekers-IoT-Kit.pdf',
    'ZeekWeigh'        => 'brochures/ZeekWeigh.pdf',
    'Zeevior'          => 'brochures/Zeevior.pdf',
    'ASSET TRACKING'   => 'brochures/Asset-Tracking.pdf',
    'ZEEBOT'           => 'brochures/ZEEBOT.pdf',
    'AICTE IDEA Lab'   => 'brochures/AICTE-LAB.pdf',
];

$file = $brochureMap[$product] ?? null;

respond([
    'success'  => true,
    'message'  => 'Lead captured',
    'file'     => $file,
    'emailed'  => $sent
], 201);
