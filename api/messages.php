<?php
/** API پیام‌ها: contacts, list, send — متن + عکس/دوربین + فایل/PDF + ویس */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_login();
$u = current_user();
$me = (int)$u['id'];
$json = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? body_json() : [];
$action = (string)(input('action') ?: ($json['action'] ?? 'list'));
$in = array_merge($_GET, $_POST, $json);

const CHAT_MAX_IMAGE = 10 * 1024 * 1024; // 10MB
const CHAT_MAX_AUDIO = 15 * 1024 * 1024; // 15MB
const CHAT_MAX_FILE  = 20 * 1024 * 1024; // 20MB

/** ستون‌های مدیا را برای نصب‌های قبلی به صورت خودکار اضافه می‌کند. */
function ensure_message_media_columns(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM messages')->fetchAll() as $c) {
            $cols[$c['Field']] = true;
        }
        $alter = [];
        if (empty($cols['attachment_type'])) $alter[] = "ADD COLUMN attachment_type VARCHAR(20) NOT NULL DEFAULT 'none'";
        if (empty($cols['attachment_path'])) $alter[] = "ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL";
        if (empty($cols['attachment_name'])) $alter[] = "ADD COLUMN attachment_name VARCHAR(190) DEFAULT NULL";
        if (empty($cols['attachment_mime'])) $alter[] = "ADD COLUMN attachment_mime VARCHAR(80) DEFAULT NULL";
        if (empty($cols['attachment_size'])) $alter[] = "ADD COLUMN attachment_size INT UNSIGNED DEFAULT NULL";
        if ($alter) db()->exec('ALTER TABLE messages ' . implode(', ', $alter));
        return $ready = true;
    } catch (Throwable $e) {
        return $ready = false;
    }
}

/** بررسی مجاز بودن گفتگو (مشاور↔دانش‌آموز) */
function can_chat(array $u, int $other): bool {
    $o = get_user($other);
    if (!$o) return false;
    if (in_array($u['role'], ['advisor','admin'], true)) return $o['role'] === 'student';
    return in_array($o['role'], ['advisor','admin'], true);
}

function chat_letters(string $name): string {
    $p = preg_split('/\s+/u', trim($name));
    $s = mb_substr($p[0] ?? '', 0, 1);
    if (count($p) > 1) $s .= mb_substr($p[1], 0, 1);
    return $s ?: 'م';
}

function chat_preview(?array $m): string {
    if (!$m) return 'بدون پیام';
    $body = trim((string)($m['body'] ?? ''));
    $type = (string)($m['attachment_type'] ?? 'none');
    if ($type === 'image') return '📷 ' . ($body !== '' ? $body : 'عکس');
    if ($type === 'audio') return '🎙 ' . ($body !== '' ? $body : 'ویس');
    if ($type === 'pdf') return '📄 ' . ($body !== '' ? $body : 'PDF');
    if ($type === 'file') return '📎 ' . ($body !== '' ? $body : 'فایل');
    return $body !== '' ? $body : 'پیام';
}

function normalize_upload_error(int $err): string {
    return match ($err) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز است',
        UPLOAD_ERR_PARTIAL => 'فایل کامل آپلود نشد؛ دوباره تلاش کنید',
        UPLOAD_ERR_NO_FILE => 'فایلی ارسال نشد',
        default => 'خطا در آپلود فایل',
    };
}

function mime_of(string $tmp): string
{
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = (string)finfo_file($fi, $tmp);
            finfo_close($fi);
            return $mime;
        }
    }
    return '';
}

function infer_image_ext(string $mime): string
{
    return match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default => '',
    };
}

function safe_original_name(string $name): string
{
    $name = trim(str_replace(["\0", '/', '\\'], '', $name));
    return mb_substr($name !== '' ? $name : 'file', 0, 180);
}

function max_size_for(string $type): int
{
    return match ($type) {
        'image' => CHAT_MAX_IMAGE,
        'audio' => CHAT_MAX_AUDIO,
        default => CHAT_MAX_FILE,
    };
}

function size_error_for(string $type): string
{
    return match ($type) {
        'image' => 'حجم عکس باید کمتر از ۱۰ مگابایت باشد',
        'audio' => 'حجم ویس باید کمتر از ۱۵ مگابایت باشد',
        default => 'حجم فایل باید کمتر از ۲۰ مگابایت باشد',
    };
}

/** ذخیره فایل ارسالی: image | audio | file */
function save_chat_upload(string $field, string $type): ?array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) return null;
    $f = $_FILES[$field];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_out(['ok'=>false,'error'=>normalize_upload_error((int)$f['error'])], 422);
    }

    $size = (int)($f['size'] ?? 0);
    if ($size <= 0 || $size > max_size_for($type)) {
        json_out(['ok'=>false,'error'=>size_error_for($type)], 422);
    }

    $orig = safe_original_name((string)($f['name'] ?? 'file'));
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $tmp = (string)$f['tmp_name'];
    $mime = mime_of($tmp);
    $finalType = $type;

    if ($type === 'image') {
        $info = @getimagesize($tmp);
        if (!$info) json_out(['ok'=>false,'error'=>'فایل عکس معتبر نیست'],422);
        if ($ext === '') $ext = infer_image_ext((string)($info['mime'] ?? $mime));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) json_out(['ok'=>false,'error'=>'فرمت عکس مجاز نیست'],422);
        $mime = (string)($info['mime'] ?? $mime ?: 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
    } elseif ($type === 'audio') {
        $allowedExt = ['webm','ogg','mp3','wav','m4a','mp4','aac'];
        if ($ext === '') $ext = 'webm';
        $allowedMime = str_starts_with($mime, 'audio/') || in_array($mime, ['video/webm','video/mp4','application/ogg','application/octet-stream',''], true);
        if (!in_array($ext, $allowedExt, true) || !$allowedMime) {
            json_out(['ok'=>false,'error'=>'فرمت ویس/صدا مجاز نیست'],422);
        }
        $mime = $mime ?: 'audio/' . $ext;
    } else {
        $allowedExt = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','zip','rar','7z'];
        if (!in_array($ext, $allowedExt, true)) {
            json_out(['ok'=>false,'error'=>'فقط PDF و فایل‌های رایج مجاز هستند'],422);
        }
        $finalType = $ext === 'pdf' ? 'pdf' : 'file';
        $mime = $mime ?: match ($ext) {
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }

    $dir = UPLOAD_DIR . '/messages';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = $finalType . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) json_out(['ok'=>false,'error'=>'خطا در ذخیره فایل'],500);

    return [
        'type' => $finalType,
        'path' => 'uploads/messages/' . $name,
        'name' => $orig,
        'mime' => $mime,
        'size' => $size,
    ];
}

function format_message(array $m, int $me): array
{
    $type = (string)($m['attachment_type'] ?? 'none');
    $path = (string)($m['attachment_path'] ?? '');
    $attachment = null;
    if ($path !== '' && $type !== 'none') {
        $attachment = [
            'type' => $type,
            'url'  => url($path),
            'name' => $m['attachment_name'] ?? '',
            'mime' => $m['attachment_mime'] ?? '',
            'size' => isset($m['attachment_size']) ? (int)$m['attachment_size'] : 0,
        ];
    }
    return [
        'id'=>(int)$m['id'],
        'mine'=>(int)$m['sender_id']===$me,
        'body'=>(string)($m['body'] ?? ''),
        'time'=>fa_num(date('H:i',strtotime($m['created_at']))),
        'date'=>jalali_date($m['created_at']),
        'attachment'=>$attachment,
    ];
}

$mediaReady = ensure_message_media_columns();

try {
switch ($action) {

case 'contacts': {
    if (in_array($u['role'], ['advisor','admin'], true)) {
        $accessMode = $u['access_mode'] ?? 'all';
        $role = $u['role'];
        $where = 'role="student" AND status="active"';
        if ($accessMode === 'restricted') {
            $where .= ' AND id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id=' . (int)$me . ')';
        } elseif ($role !== 'admin') {
            $where .= ' AND (advisor_id=' . (int)$me . ' OR id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id=' . (int)$me . '))';
        }
        $rows = db()->query('SELECT id, full_name, field, status FROM users WHERE ' . $where . ' ORDER BY full_name')->fetchAll();
    } else {
        $rows = db()->query('SELECT id, full_name, field, status FROM users WHERE role IN ("advisor","admin") ORDER BY id')->fetchAll();
    }

    foreach ($rows as &$r) {
        $last = db()->prepare('SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at DESC, id DESC LIMIT 1');
        $last->execute([$me,$r['id'],$r['id'],$me]);
        $lm = $last->fetch();
        $r['avatar'] = chat_letters((string)$r['full_name']);
        $r['last'] = chat_preview($lm ?: null);
        $r['last_ago'] = $lm ? time_ago($lm['created_at']) : '';
        $unr = db()->prepare('SELECT COUNT(*) FROM messages WHERE sender_id=? AND receiver_id=? AND is_read=0');
        $unr->execute([$r['id'],$me]);
        $r['unread'] = (int)$unr->fetchColumn();
    }
    json_out(['ok'=>true,'items'=>$rows]);
}

case 'list': {
    $other = (int)($in['with'] ?? 0);
    if (!can_chat($u,$other)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $msgs = conversation($me,$other);
    db()->prepare('UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?')->execute([$other,$me]);
    $out = array_map(fn($m)=>format_message($m, $me), $msgs);
    json_out(['ok'=>true,'items'=>$out]);
}

case 'send': {
    require_csrf();
    $other = (int)($in['with'] ?? 0);
    $body = trim((string)($in['body'] ?? ''));
    if (!can_chat($u,$other)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    if (mb_strlen($body) > 2000) $body = mb_substr($body,0,2000);

    $hasUpload = false;
    foreach (['image','audio','file'] as $field) {
        if (!empty($_FILES[$field]) && (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $hasUpload = true;
    }
    if ($hasUpload && !$mediaReady) json_out(['ok'=>false,'error'=>'ساختار دیتابیس برای ارسال فایل آماده نیست. فایل sql/upgrade_messages_media.sql را اجرا کنید.'],500);

    $media = save_chat_upload('image', 'image') ?: save_chat_upload('audio', 'audio') ?: save_chat_upload('file', 'file');
    if ($body === '' && !$media) json_out(['ok'=>false,'error'=>'پیام خالی است'],422);

    if ($media) {
        db()->prepare('INSERT INTO messages (sender_id,receiver_id,body,attachment_type,attachment_path,attachment_name,attachment_mime,attachment_size) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$me,$other,$body,$media['type'],$media['path'],$media['name'],$media['mime'],$media['size']]);
    } else {
        db()->prepare('INSERT INTO messages (sender_id,receiver_id,body) VALUES (?,?,?)')->execute([$me,$other,$body]);
    }

    if ($media) {
        $preview = match ($media['type']) {
            'image' => '📷 عکس',
            'audio' => '🎙 ویس',
            'pdf'   => '📄 PDF',
            default => '📎 فایل',
        };
    } else {
        $preview = mb_substr($body,0,60);
    }
    if ($body !== '' && $media) $preview .= ' · ' . mb_substr($body,0,45);
    
    $recipName = db()->query("SELECT full_name FROM users WHERE id=$other")->fetchColumn() ?: 'کاربر';
    $msgId = (int)db()->lastInsertId();
    log_activity($me, 'message_sent', 'message', $msgId, ['گیرنده' => $recipName, 'محتوا' => $preview]);

    notify($other, 'پیام جدید 💬', $preview, 'message', $u['role']==='student'?'admin/messages.php?with='.$me:'student/messages.php');
    json_out(['ok'=>true,'time'=>fa_num(date('H:i')),'id'=>$msgId]);
}

default: json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
}
} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور'],500);
}
