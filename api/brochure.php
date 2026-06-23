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
$to      = 'contact@zeekerstechnology.com'; // ← your email
$subject = ($type === 'lab')
    ? "New AICTE-IDEA Lab Brochure Download — $name"
    : "New Product Brochure Download — $product by $name";

$typeLabel = ($type === 'lab') ? 'AICTE-IDEA Lab Brochure' : 'Product Brochure';

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
  .value { color: #222; font-size: 14px; font-weight: 500; }
  .badge { display: inline-block; background: #fff3ec; color: #ff6b2b; border: 1px solid #ffd5bc; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
  .footer { background: #fafafa; padding: 14px 32px; font-size: 11px; color: #aaa; border-top: 1px solid #eee; }
</style>
</head>
<body>
<div class='wrap'>
  <div class='header'>
    <h2>📥 New $typeLabel Lead</h2>
    <p>Someone downloaded a brochure from zeekerstechnology.com</p>
  </div>
  <div class='body'>
    <div class='row'><span class='label'>Name</span><span class='value'>$name</span></div>
    <div class='row'><span class='label'>Email</span><span class='value'><a href='mailto:$email' style='color:#ff6b2b'>$email</a></span></div>
    <div class='row'><span class='label'>Mobile</span><span class='value'><a href='tel:$mobile' style='color:#ff6b2b'>$mobile</a></span></div>
    <div class='row'><span class='label'>Product / Brochure</span><span class='value'><span class='badge'>$product</span></span></div>
    <div class='row'><span class='label'>Purpose</span><span class='value'>$purpose</span></div>
    <div class='row'><span class='label'>Type</span><span class='value'>" . ucfirst($type) . " Page</span></div>
    <div class='row'><span class='label'>Time</span><span class='value'>" . date('d M Y, h:i A') . " IST</span></div>
  </div>
  <div class='footer'>Zeekers Technology Solutions · zeekerstechnology.com · Auto-generated lead notification</div>
</div>
</body>
</html>
";

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Zeekers Website <noreply@zeekerstechnology.com>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = mail($to, $subject, $body, $headers);

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
