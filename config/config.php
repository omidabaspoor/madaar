<?php
/**
 * مَدار · Madar Study OS — Central Configuration
 * ----------------------------------------------------
 * تنظیمات اصلی پروژه. روی هاست واقعی فقط همین فایل را ویرایش کنید.
 */
declare(strict_types=1);

// --- محیط ---
define('APP_ENV', 'development');          // production | development
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

// --- Web Push Notifications ---
// برای اعلان واقعی حتی وقتی وب‌اپ باز نیست، VAPID را فعال کنید.
// ساخت کلیدها: php scripts/generate_vapid_keys.php
// تا وقتی این دو مقدار خالی هستند، سیستم قبلی اعلان‌ها بدون تغییر کار می‌کند.
define('VAPID_PUBLIC_KEY', 'BLB-0UuL_wwDGfDzO5KGPtslum7oB_uR1NPXkoi49PKZgmZpCOXNdy15Al8SHRLCEempdYtNm48LJy93vDv-NlM');
define('VAPID_PRIVATE_KEY', 'SzBh4I-RnzKx4IUDlAjBFz0d6kAEZqVYh0YIEl1hc7E');
define('VAPID_SUBJECT', 'mailto:info@webmania.ir');

// --- آپلود ---
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_UPLOAD', 2 * 1024 * 1024);

// --- پیامک (sms.ir) ---
// ⚠️ موقتاً غیرفعال است تا شماره خط و کلید Production تنظیم شود.
// وقتی آماده شد:
//   ۱. SMS_ENABLED را true کن
//   ۲. SMS_API_KEY را با کلید Production جایگزین کن
//   ۳. SMS_LINE_NUMBER را با شماره خط واقعی جایگزین کن
define('SMS_ENABLED', false);
define('SMS_API_KEY', '');
define('SMS_API_URL', 'https://api.sms.ir/v1/send/array');
define('SMS_LINE_NUMBER', '');
define('SMS_TIMEOUT', 10);

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
