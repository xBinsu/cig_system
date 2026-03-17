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
$storedPath = $row['file_path'];
$userId     = (int)$row['user_id'];
$ext        = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$base       = basename($fileName);
$sep        = DIRECTORY_SEPARATOR;

// Build absolute htdocs path by splitting __DIR__
// __DIR__ = C:\xampp\htdocs\cig_system\pages
$parts  = explode($sep, rtrim(__DIR__, $sep));
array_pop($parts); // remove 'pages'
array_pop($parts); // remove 'cig_system'
$htdocs = implode($sep, $parts); // C:\xampp\htdocs

// All sibling project folders to search
$searchRoots = [
    $htdocs . $sep . 'cig_user'        . $sep . 'org-dashboard',
    $htdocs . $sep . 'cig_user',
    $htdocs . $sep . 'cig_system'      . $sep . 'pages',
    $htdocs . $sep . 'cig_system',
    $htdocs . $sep . 'cig_superadmin'  . $sep . 'pages',
    $htdocs . $sep . 'cig_superadmin',
];

// Recursive search for the exact filename
function findFile($dirs, $filename, $sep) {
    foreach ($dirs as $root) {
        if (!is_dir($root)) continue;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }
    }
    return null;
}

$diskPath = findFile($searchRoots, $base, $sep);

if (!$diskPath) {
    // Show debug with a directory listing of all 'accreditation' folders found
    $accredDirs = [];
    foreach ($searchRoots as $root) {
        if (!is_dir($root)) continue;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $f) {
            if ($f->isDir() && strtolower($f->getFilename()) === 'accreditation') {
                $accredDirs[] = $f->getPathname();
            }
        }
    }

    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>body{font-family:Arial,sans-serif;padding:1.5rem;background:#f9f9f9;}
    h3{color:#c0392b;}h4{color:#333;margin:1rem 0 .3rem;}
    code{background:#f0f0f0;padding:2px 5px;border-radius:3px;font-size:.82em;display:block;margin:2px 0;word-break:break-all;}
    .ok{color:green;}.no{color:#999;}</style></head><body>
    <h3>File not found: ' . htmlspecialchars($base) . '</h3>
    <p><b>Searched in:</b></p>';
    foreach ($searchRoots as $r) {
        echo '<code class="' . (is_dir($r)?'ok':'no') . '">' . htmlspecialchars($r) . ' [' . (is_dir($r)?'exists':'NOT FOUND') . ']</code>';
    }
    echo '<h4>All "accreditation" folders found:</h4>';
    if (empty($accredDirs)) {
        echo '<code>None found</code>';
    } else {
        foreach ($accredDirs as $d) {
            echo '<code>' . htmlspecialchars($d) . '</code>';
            $files = glob($d . $sep . '*');
            foreach (($files ?: []) as $f) {
                echo '<code>&nbsp;&nbsp;&nbsp;↳ ' . htmlspecialchars(basename($f)) . '</code>';
            }
            // Also check subdirs
            $subdirs = glob($d . $sep . '*', GLOB_ONLYDIR);
            foreach (($subdirs ?: []) as $sd) {
                $subfiles = glob($sd . $sep . '*');
                echo '<code>&nbsp;&nbsp;&nbsp;/' . htmlspecialchars(basename($sd)) . '/</code>';
                foreach (($subfiles ?: []) as $sf) {
                    echo '<code>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;↳ ' . htmlspecialchars(basename($sf)) . '</code>';
                }
            }
        }
    }
    echo '</body></html>';
    exit;
}

// ── Found — stream it ─────────────────────────────────────────────────────────
$mimeMap = ['pdf'=>'application/pdf',
    'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'=>'application/msword',
    'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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