<?php
/** Secure inline streaming for large exam PDF booklets (supports Range requests). */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_login();

$u = current_user();
$examId = (int)($_GET['exam_id'] ?? 0);
$file = basename((string)($_GET['file'] ?? ''));
if (!$examId || $file === '' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(404); exit;
}
$exam = get_exam($examId);
if (!$exam) { http_response_code(404); exit; }

$allowed = false;
if (in_array($u['role'], ['advisor','admin'], true)) {
    $allowed = $u['role'] === 'admin' || (int)$exam['advisor_id'] === (int)$u['id'];
} elseif ($u['role'] === 'student') {
    $allowed = student_exam_is_visible($examId, (int)$u['id']);
}
if (!$allowed) { http_response_code(403); exit; }

$paths = [];
if (!empty($exam['sheet_path'])) $paths[] = (string)$exam['sheet_path'];
if (!empty($exam['sheet_paths_json'])) {
    $decoded = json_decode((string)$exam['sheet_paths_json'], true);
    if (is_array($decoded)) foreach ($decoded as $p) $paths[] = (string)$p;
}
$paths = array_values(array_unique($paths));
$rel = null;
foreach ($paths as $p) {
    if (basename($p) === $file && is_pdf_asset($p)) { $rel = $p; break; }
}
if (!$rel) { http_response_code(404); exit; }

$full = realpath(__DIR__ . '/../' . $rel);
$base = realpath(UPLOAD_DIR . '/exams');
if (!$full || !$base || !str_starts_with($full, $base) || !is_file($full)) { http_response_code(404); exit; }

$size = filesize($full);
$start = 0; $end = $size - 1; $status = 200;
$range = $_SERVER['HTTP_RANGE'] ?? '';
if (preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
    if ($m[1] !== '') $start = (int)$m[1];
    if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
    if ($m[1] === '' && $m[2] !== '') { $suffix = (int)$m[2]; $start = max(0, $size - $suffix); $end = $size - 1; }
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        http_response_code(416); exit;
    }
    $status = 206;
}

$length = $end - $start + 1;
http_response_code($status);
header('Content-Type: application/pdf');
header('Accept-Ranges: bytes');
header('Content-Length: ' . $length);
$download = isset($_GET['download']) && (string)$_GET['download'] === '1';
$disp = $download ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disp . '; filename="booklet.pdf"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
if ($status === 206) header("Content-Range: bytes $start-$end/$size");

$fp = fopen($full, 'rb');
if (!$fp) { http_response_code(500); exit; }
fseek($fp, $start);
$remaining = $length;
while ($remaining > 0 && !feof($fp)) {
    $chunk = fread($fp, min(1024 * 1024, $remaining));
    if ($chunk === false) break;
    echo $chunk;
    flush();
    $remaining -= strlen($chunk);
}
fclose($fp);
exit;
