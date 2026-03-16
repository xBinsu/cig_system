<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(401); die("Unauthorized"); }

$docId    = isset($_GET['doc_id']) ? (int)$_GET['doc_id'] : 0;
$download = isset($_GET['download']) && $_GET['download'] === '1';
if (!$docId) { http_response_code(400); die("Invalid document ID"); }

$conn = mysqli_connect("localhost", "root", "", "cig_system");
if (!$conn) { http_response_code(500); die("DB error"); }

$stmt = $conn->prepare("SELECT file_name, file_path, mime_type, user_id FROM documents WHERE document_id = ? LIMIT 1");
$stmt->bind_param("i", $docId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) { http_response_code(404); die("Document not found"); }

$fileName   = $row['file_name'];
$storedPath = $row['file_path'];   // "uploads/accreditation/16/file.pdf"
$userId     = (int)$row['user_id'];
$ext        = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$base       = basename($fileName);
$sep        = DIRECTORY_SEPARATOR;

// __DIR__ = C:\xampp\htdocs\cig_system\pages
// Resolve WITHOUT using ".." — build absolute path directly
// Split __DIR__ by separator, remove last 2 parts (pages, cig_system), add cig_user/org-dashboard
$parts   = explode($sep, rtrim(__DIR__, $sep));   // [..., 'cig_system', 'pages']
array_pop($parts);   // remove 'pages'
array_pop($parts);   // remove 'cig_system'
$htdocs  = implode($sep, $parts);                 // C:\xampp\htdocs

$orgDash = $htdocs . $sep . 'cig_user' . $sep . 'org-dashboard';

// Build candidates — all absolute, no ".." anywhere
$candidates = [];

// 1. orgDash + stored path exactly
if ($storedPath) {
    $candidates[] = $orgDash . $sep . str_replace(['/', '\\'], $sep, ltrim($storedPath, '/\\'));
}

// 2. orgDash + uploads/accreditation/{uid}/{base}
$candidates[] = $orgDash . $sep . 'uploads' . $sep . 'accreditation' . $sep . $userId . $sep . $base;

// 3. orgDash + uploads/accreditation/{base}
$candidates[] = $orgDash . $sep . 'uploads' . $sep . 'accreditation' . $sep . $base;

// 4. orgDash + uploads/{base}
$candidates[] = $orgDash . $sep . 'uploads' . $sep . $base;

// Find file
$diskPath = null;
foreach ($candidates as $c) {
    if (file_exists($c) && is_file($c)) { $diskPath = $c; break; }
}

if (!$diskPath) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    // Also check if the file exists using glob as an alternative test
    $globTest = glob($orgDash . $sep . 'uploads' . $sep . 'accreditation' . $sep . $userId . $sep . '*');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>body{font-family:Arial,sans-serif;padding:1.5rem;background:#f9f9f9;}
    h3{color:#c0392b;}code{background:#f0f0f0;padding:2px 5px;border-radius:3px;font-size:.82em;display:block;margin:2px 0;word-break:break-all;}</style></head><body>
    <h3>File not found</h3>
    <p><b>file_name:</b> <code>' . htmlspecialchars($fileName) . '</code></p>
    <p><b>file_path:</b> <code>' . htmlspecialchars($storedPath ?? '') . '</code></p>
    <p><b>htdocs:</b> <code>' . htmlspecialchars($htdocs) . '</code></p>
    <p><b>orgDash:</b> <code>' . htmlspecialchars($orgDash) . '</code></p>
    <p><b>orgDash exists:</b> <code>' . (is_dir($orgDash) ? 'YES' : 'NO — wrong path!') . '</code></p>
    <p><b>Tried:</b></p>';
    foreach ($candidates as $c) { echo '<code>' . htmlspecialchars($c) . ' [' . (file_exists($c)?'EXISTS':'missing') . ']</code>'; }
    if ($globTest !== false) {
        echo '<p><b>Files in accreditation/' . $userId . '/:</b></p>';
        if (empty($globTest)) { echo '<code>(folder empty or not found)</code>'; }
        foreach ($globTest as $g) { echo '<code>' . htmlspecialchars($g) . '</code>'; }
    }
    echo '</body></html>';
    exit;
}

$mimeMap = ['pdf'=>'application/pdf','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'=>'application/msword','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'];
$mime = $row['mime_type'] ?: ($mimeMap[$ext] ?? 'application/octet-stream');

while (ob_get_level()) ob_end_clean();
header('Content-Type: '   . $mime);
header('Content-Length: ' . filesize($diskPath));
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, max-age=300');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode($fileName) . '"');
readfile($diskPath);
exit;