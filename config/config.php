<?php
/**
 * مَدار · Madar Study OS — Central Configuration
 * ----------------------------------------------------
 * تنظیمات اصلی پروژه. روی هاست واقعی فقط همین فایل را ویرایش کنید.
 */
declare(strict_types=1);

// --- محیط ---
define('APP_ENV', 'production');          // production | development
define('APP_DOMAIN', 'https://madaar-edu.ir');
define('APP_NAME', 'مَدار');
define('APP_NAME_EN', 'madaar');
define('APP_TAGLINE', 'سامانه هوشمند برنامه‌ریزی کنکور');
define('APP_OWNER', 'دکتر سجاد صیادی');
define('APP_OWNER_EN', 'Dr. Sajjad Sayyadi');
define('APP_VERSION', '1.0.0');

// --- مسیر پایه (اگر در زیرپوشه نصب شده تنظیم کنید، مثلا '/konkur') ---
define('BASE_URL', rtrim((function () {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // اگر اسکریپت داخل admin/ یا student/ ... است، یک سطح بالا برو تا ریشه
    foreach (['/admin', '/student', '/auth', '/api'] as $seg) {
        if (str_ends_with($script, $seg)) { $script = substr($script, 0, -strlen($seg)); }
    }
    $proto = $https ? 'https' : 'http';
    return $proto . '://' . $host . ($script === '/' ? '' : $script);
})(), '/'));

// --- دیتابیس ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'madar_konkur');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- امنیت ---
define('SESSION_NAME', 'MADAR_SESS');
define('SESSION_LIFETIME', 60 * 60 * 8);  // 8h
define('BCRYPT_COST', 12);
define('CSRF_TOKEN_NAME', '_csrf');

// --- آپلود ---
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD', 2 * 1024 * 1024);

// --- منطقه‌زمانی ---
date_default_timezone_set('Asia/Tehran');

// --- نمایش خطا بر اساس محیط ---
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
