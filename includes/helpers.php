<?php
/** توابع کمکی عمومی */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/* ---------- پلی‌فیل mbstring (در صورت نبود اکستنشن روی هاست) ---------- */
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $enc = 'UTF-8') {
        $chars = preg_split('//u', (string)$str, -1, PREG_SPLIT_NO_EMPTY);
        $slice = array_slice($chars, $start, $length);
        return implode('', $slice);
    }
}

if (!function_exists('mb_strpos')) {
    function mb_strpos($haystack, $needle, $offset = 0, $enc = 'UTF-8') {
        return strpos((string)$haystack, (string)$needle, (int)$offset);
    }
}

if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $enc = 'UTF-8') {
        return count(preg_split('//u', (string)$str, -1, PREG_SPLIT_NO_EMPTY));
    }
}

if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth($str, $start, $width, $trim_marker = '', $enc = 'UTF-8') {
        $str = (string)$str;
        $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $slice = array_slice($chars, (int)$start, max(0, (int)$width));
        $out = implode('', $slice);
        return count($chars) > ((int)$start + (int)$width) ? $out . (string)$trim_marker : $out;
    }
}

/* ---------- خروجی امن ---------- */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }

function asset(string $path): string
{
    $path = ltrim($path, '/');
    // نسخه‌گذاری خودکار برای جلوگیری از کش قدیمی (cache busting)
    $full = __DIR__ . '/../assets/' . $path;
    $v = @filemtime($full);
    $q = $v ? ('?v=' . $v) : '';
    return url('assets/' . $path) . $q;
}

function local_image_data_uri(?string $path): string
{
    if (empty($path)) return '';
    $path = ltrim(trim($path), '/');
    if (($pos = strpos($path, '?')) !== false) {
        $path = substr($path, 0, $pos);
    }
    
    $full = __DIR__ . '/../' . $path;
    if (!file_exists($full) && !str_starts_with($path, 'assets/')) {
        $full = __DIR__ . '/../assets/' . $path;
    }
    
    if (file_exists($full) && is_file($full)) {
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'png' => 'image/png',
            'jpg','jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png'
        };
        $base64 = base64_encode(file_get_contents($full));
        return "data:$mime;base64,$base64";
    }

    return url($path);
}

function redirect(string $path): never { header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path))); exit; }

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------- ورودی ---------- */
function input(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}
function body_json(): array
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}
function clamp_int($v, int $min, int $max): int
{
    $v = (int)$v;
    return max($min, min($max, $v));
}

/* ---------- فایل‌های دفترچه آزمون (عکس/PDF) ---------- */
function sheet_asset_type(?string $path): string
{
    $ext = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) return 'image';
    return 'file';
}
function is_pdf_asset(?string $path): bool { return sheet_asset_type($path) === 'pdf'; }
function sheet_view_url(string $path, ?int $examId = null): string
{
    if (is_pdf_asset($path) && $examId) {
        return url('api/exam_file.php?exam_id=' . $examId . '&file=' . rawurlencode(basename($path)));
    }
    return url($path);
}
function human_file_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024 * 1024) return fa_num(round($bytes / (1024 * 1024 * 1024), 1)) . ' گیگابایت';
    if ($bytes >= 1024 * 1024) return fa_num(round($bytes / (1024 * 1024), 1)) . ' مگابایت';
    if ($bytes >= 1024) return fa_num(round($bytes / 1024, 1)) . ' کیلوبایت';
    return fa_num($bytes) . ' بایت';
}

/* ---------- نمایش اعداد فارسی ---------- */
function fa_num($n): string
{
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($en, $fa, (string)$n);
}

/* ---------- تاریخ شمسی (الگوریتم گریگوری→جلالی) ---------- */
function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
          + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) { $jy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + intdiv($days, 31); $jd = 1 + ($days % 31); }
    else { $jm = 7 + intdiv($days - 186, 30); $jd = 1 + (($days - 186) % 30); }
    return [$jy, $jm, $jd];
}
function jalali_date(string $datetime = 'now', bool $withTime = false): string
{
    $ts = ($datetime === 'now') ? time() : strtotime($datetime);
    if ($ts === false) return '';
    [$jy, $jm, $jd] = gregorian_to_jalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $out = fa_num($jd) . ' ' . $months[$jm - 1] . ' ' . fa_num($jy);
    if ($withTime) $out .= ' - ' . fa_num(date('H:i', $ts));
    return $out;
}
function persian_weekday(string $date): string
{
    // 0=شنبه ... 6=جمعه
    $w = ['شنبه','یک‌شنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه'];
    $idx = (int)date('w', strtotime($date)); // 0=یکشنبه(sun)..6=شنبه(sat)
    $map = [0=>1,1=>2,2=>3,3=>4,4=>5,5=>6,6=>0];
    return $w[$map[$idx]];
}
/** شنبه‌ی هفته‌ی شامل تاریخ داده‌شده (Y-m-d) */
function week_saturday(string $date = 'now'): string
{
    $ts = ($date === 'now') ? time() : strtotime($date);
    $w = (int)date('w', $ts);              // 0=Sun..6=Sat
    $back = ($w + 1) % 7;                   // فاصله تا شنبه قبلی
    return date('Y-m-d', strtotime("-$back day", $ts));
}
/** اندیس روز هفته‌ی شمسی: 0=شنبه ... 6=جمعه */
function persian_day_index(string $date): int
{
    $w = (int)date('w', strtotime($date)); // 0=Sun..6=Sat
    $map = [6=>0,0=>1,1=>2,2=>3,3=>4,4=>5,5=>6];
    return $map[$w];
}

/* ---------- زمان نسبی ---------- */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'همین حالا';
    if ($diff < 3600) return fa_num(intdiv($diff, 60)) . ' دقیقه پیش';
    if ($diff < 86400) return fa_num(intdiv($diff, 3600)) . ' ساعت پیش';
    if ($diff < 604800) return fa_num(intdiv($diff, 86400)) . ' روز پیش';
    return jalali_date($datetime);
}

/* ---------- پیام فلش ---------- */
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg]; }
function get_flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

/* ---------- ابعاد اولیه ---------- */
const DAY_NAMES  = ['شنبه','یک‌شنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنج‌شنبه','جمعه'];
const UNIT_NAMES = [1=>'واحد اول',2=>'واحد دوم',3=>'واحد سوم',4=>'واحد چهارم',5=>'واحد پنجم',6=>'واحد ششم',7=>'واحد هفتم',8=>'واحد ویژه'];
const TASK_TYPES = [
  'study'       => ['label'=>'مطالعه/درسنامه','icon'=>'book'],
  'test'        => ['label'=>'تست','icon'=>'check'],
  'review'      => ['label'=>'مرور','icon'=>'repeat'],
  'textbook'    => ['label'=>'کتاب درسی','icon'=>'book'],
  'descriptive' => ['label'=>'سوال تشریحی','icon'=>'edit'],
  'reading'     => ['label'=>'روزخوانی','icon'=>'glasses'],
  'exam'        => ['label'=>'آزمونک','icon'=>'clipboard'],
  'analysis'    => ['label'=>'تحلیل آزمون','icon'=>'chart'],
  'special'     => ['label'=>'واحد ویژه','icon'=>'sparkles'],
  'mock'        => ['label'=>'آزمون','icon'=>'clipboard'],
  'custom'      => ['label'=>'دلخواه','icon'=>'star'],
];
