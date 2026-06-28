<?php
/**
 * مَدار · SMS Service — sms.ir v1
 * ------------------------------------------------
 * ارسال واقعی پیامک از طریق endpointهای رسمی sms.ir:
 * - /v1/send/bulk       برای ارسال یک متن به چند شماره
 * - /v1/send/likeToLike برای ارسال متن‌های متناظر به شماره‌های متناظر
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/planner_settings.php';

const SMSIR_BULK_URL = 'https://api.sms.ir/v1/send/bulk';
const SMSIR_LIKE_TO_LIKE_URL = 'https://api.sms.ir/v1/send/likeToLike';
const SMSIR_VERIFY_URL = 'https://api.sms.ir/v1/send/verify';

function sms_log_schema_ready(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS sms_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            template_type VARCHAR(40) NOT NULL,
            related_id INT UNSIGNED NOT NULL,
            status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            api_response TEXT NULL,
            api_message_id VARCHAR(80) NULL,
            error_message VARCHAR(255) NULL,
            sent_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_sms_user (user_id, created_at),
            INDEX idx_sms_related (related_id, template_type),
            INDEX idx_sms_status (status, created_at),
            INDEX idx_sms_template_type (template_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return $ok = true;
    } catch (Throwable $e) {
        error_log('Madar SMS schema error: ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * نرمال‌سازی شماره موبایل ایران به فرمت داخلی 09xxxxxxxxx.
 * sms.ir این فرمت را برای آرایه mobiles می‌پذیرد و خواناتر هم در لاگ ذخیره می‌شود.
 */
function sms_normalize_phone(?string $phone): ?string {
    if (empty($phone)) return null;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!$phone) return null;

    if (strlen($phone) === 11 && str_starts_with($phone, '09')) return $phone;
    if (strlen($phone) === 10 && str_starts_with($phone, '9')) return '0' . $phone;
    if (strlen($phone) === 12 && str_starts_with($phone, '98')) return '0' . substr($phone, 2);
    if (strlen($phone) === 13 && str_starts_with($phone, '098')) return '0' . substr($phone, 3);
    return null;
}

function sms_config(?int $advisorId = null): array {
    $cfg = [
        'enabled' => defined('SMS_ENABLED') ? (bool)SMS_ENABLED : false,
        'api_key' => defined('SMS_API_KEY') ? (string)SMS_API_KEY : '',
        'line_number' => defined('SMS_LINE_NUMBER') ? (string)SMS_LINE_NUMBER : '',
        'timeout' => defined('SMS_TIMEOUT') ? (int)SMS_TIMEOUT : 10,
        'template_id' => '',
        'param_date' => 'DATE',
        'param_time' => 'TIME',
    ];

    if ($advisorId && settings_table_ready()) {
        $s = advisor_settings($advisorId);
        // تنظیمات پنل مشاور اولویت دارد.
        $cfg['enabled'] = ($s['sms_enabled'] ?? '0') === '1';
        $cfg['api_key'] = trim((string)($s['sms_api_key'] ?? ''));
        $cfg['line_number'] = preg_replace('/[^0-9]/', '', (string)($s['sms_line_number'] ?? ''));
        $cfg['template_id'] = preg_replace('/[^0-9]/', '', (string)($s['sms_template_id'] ?? ''));
        $cfg['param_date'] = trim((string)($s['sms_param_date'] ?? 'DATE')) ?: 'DATE';
        $cfg['param_time'] = trim((string)($s['sms_param_time'] ?? 'TIME')) ?: 'TIME';
    }

    return $cfg;
}

function sms_is_enabled(?int $advisorId = null): bool {
    $c = sms_config($advisorId);
    return $c['enabled'] && $c['api_key'] !== '' && $c['line_number'] !== '';
}

function sms_shorten_message(string $message): string {
    $message = trim($message);
    // پیام قالب جلسه زیر ۳۲۰ کاراکتر است؛ محدودیت برای جلوگیری از متن‌های بسیار طولانی نگه داشته شده.
    if (mb_strlen($message, 'UTF-8') > 500) {
        return mb_substr($message, 0, 498, 'UTF-8') . '…';
    }
    return $message;
}

function sms_http_post(string $url, array $payload, array $cfg, bool $acceptText = false): array {
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http_code' => 0, 'response' => null, 'json' => null, 'error' => 'اکستنشن cURL روی سرور فعال نیست'];
    }

    $headers = [
        'X-API-KEY: ' . $cfg['api_key'],
        'Content-Type: application/json',
    ];
    $headers[] = $acceptText ? 'Accept: text/plain' : 'Accept: application/json';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => max(5, (int)($cfg['timeout'] ?? 10)),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $json = is_string($response) && $response !== '' ? json_decode($response, true) : null;
    $ok = $httpCode >= 200 && $httpCode < 300;
    if (is_array($json) && array_key_exists('status', $json)) {
        $ok = $ok && ((int)$json['status'] === 1 || (bool)$json['status'] === true);
    }

    $err = null;
    if (!$ok) {
        if ($curlError) $err = 'خطای ارتباط: ' . $curlError;
        elseif (is_array($json) && isset($json['message'])) $err = (string)$json['message'];
        elseif (is_string($response) && trim($response) !== '') $err = mb_substr(trim($response), 0, 250, 'UTF-8');
        else $err = 'خطای ارسال پیامک (HTTP ' . $httpCode . ')';
    }

    return ['ok' => $ok, 'http_code' => $httpCode, 'response' => $response, 'json' => $json, 'error' => $err];
}

function sms_extract_message_id(?array $json, int $index = 0): ?string {
    if (!$json || !isset($json['data'])) return null;
    $data = $json['data'];
    if (!is_array($data)) return null;

    $mid = null;
    if (isset($data[$index])) {
        if (is_array($data[$index])) {
            $mid = (string)($data[$index]['messageId'] ?? $data[$index]['id'] ?? '');
        } else {
            $mid = (string)$data[$index];
        }
    } elseif (isset($data['messageId'])) {
        $mid = (string)$data['messageId'];
    }

    $mid = trim((string)$mid);
    return $mid !== '' ? mb_substr($mid, 0, 80, 'UTF-8') : null;
}

function sms_log_result(?int $userId, string $phone, string $message, string $templateType, int $relatedId, bool $success, ?string $response, ?string $messageId, ?string $error): void {
    if (!sms_log_schema_ready()) return;
    try {
        $logUserId = $userId ?? (function_exists('current_user') ? (int)(current_user()['id'] ?? 0) : 0);
        db()->prepare('INSERT INTO sms_log
            (user_id, phone, message, template_type, related_id, status, api_response, api_message_id, error_message, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $logUserId,
                $phone,
                $message,
                $templateType,
                $relatedId,
                $success ? 'sent' : 'failed',
                $response !== null ? mb_substr($response, 0, 4000, 'UTF-8') : null,
                $messageId,
                $error,
                $success ? date('Y-m-d H:i:s') : null,
            ]);
    } catch (Throwable $e) {
        error_log('Madar SMS log error: ' . $e->getMessage());
    }
}


function sms_has_verify_template(?int $advisorId = null): bool {
    $c = sms_config($advisorId);
    return sms_is_enabled($advisorId) && !empty($c['template_id']);
}

function sms_send_verify(string $phone, string $templateId, array $parameters, string $templateType = 'general', int $relatedId = 0, ?int $userId = null, ?int $advisorId = null, ?string $logMessage = null): array {
    $cfg = sms_config($advisorId);
    if (!sms_is_enabled($advisorId)) {
        return ['ok'=>false,'status'=>'disabled','error'=>'سرویس پیامک فعال یا کامل تنظیم نشده است','http_code'=>0];
    }
    $mobile = sms_normalize_phone($phone);
    if (!$mobile) {
        return ['ok'=>false,'status'=>'no_phone','error'=>'شماره موبایل نامعتبر است','http_code'=>0];
    }

    $params = [];
    foreach ($parameters as $name => $value) {
        $name = trim((string)$name);
        if ($name === '') continue;
        $params[] = ['name' => $name, 'value' => (string)$value];
    }
    $payload = [
        'mobile' => $mobile,
        'templateId' => (int)$templateId,
        'parameters' => $params,
    ];
    $http = sms_http_post(SMSIR_VERIFY_URL, $payload, $cfg, false);
    $msg = $logMessage ?: ('template#' . $templateId . ' ' . json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    sms_log_result($userId, $mobile, $msg, $templateType, $relatedId, $http['ok'], $http['response'], sms_extract_message_id($http['json'], 0), $http['error']);

    return ['ok'=>$http['ok'], 'status'=>$http['ok']?'sent':'failed', 'error'=>$http['error'], 'http_code'=>$http['http_code'], 'response'=>$http['response']];
}

function sms_send_meeting_verify_batch(array $items, string $templateType = 'meeting_consultation', ?int $advisorId = null): array {
    $cfg = sms_config($advisorId);
    if (!sms_has_verify_template($advisorId)) {
        return ['ok'=>false,'status'=>'disabled','sent'=>0,'failed'=>count($items),'no_phone'=>0,'details'=>[],'error'=>'شناسه قالب خدماتی پیامک تنظیم نشده است'];
    }
    $sent = 0; $failed = 0; $noPhone = 0; $details = [];
    foreach ($items as $item) {
        $phone = sms_normalize_phone((string)($item['phone'] ?? ''));
        if (!$phone) {
            $noPhone++;
            $details[] = ['status'=>'no_phone','phone'=>null,'user_id'=>$item['user_id'] ?? null,'related_id'=>$item['related_id'] ?? 0,'error'=>'شماره موبایل نامعتبر است','meta'=>$item['meta'] ?? []];
            continue;
        }
        $date = (string)($item['date_text'] ?? '');
        $time = (string)($item['time_text'] ?? '');
        $res = sms_send_verify(
            $phone,
            (string)$cfg['template_id'],
            [$cfg['param_date'] => $date, $cfg['param_time'] => $time],
            $templateType,
            (int)($item['related_id'] ?? 0),
            isset($item['user_id']) ? (int)$item['user_id'] : null,
            $advisorId,
            $item['message'] ?? null
        );
        if ($res['ok']) $sent++; else $failed++;
        $details[] = ['status'=>$res['status'],'phone'=>$phone,'user_id'=>$item['user_id'] ?? null,'related_id'=>$item['related_id'] ?? 0,'error'=>$res['error'],'meta'=>$item['meta'] ?? []];
    }
    return ['ok'=>$failed===0, 'status'=>$failed===0?'sent':'failed', 'sent'=>$sent, 'failed'=>$failed, 'no_phone'=>$noPhone, 'details'=>$details, 'error'=>$failed ? 'برخی پیامک‌ها ارسال نشدند' : null];
}

/** ارسال یک متن به چند شماره با endpoint رسمی bulk. */
function sms_send_bulk(array $phones, string $message, string $templateType = 'general', int $relatedId = 0, ?int $userId = null, ?int $advisorId = null): array {
    $cfg = sms_config($advisorId);
    if (!sms_is_enabled($advisorId)) {
        return ['ok' => false, 'status' => 'disabled', 'sent' => 0, 'failed' => 0, 'error' => 'سرویس پیامک فعال یا کامل تنظیم نشده است'];
    }

    $message = sms_shorten_message($message);
    $mobiles = [];
    foreach ($phones as $phone) {
        $n = sms_normalize_phone((string)$phone);
        if ($n) $mobiles[] = $n;
    }
    $mobiles = array_values(array_unique($mobiles));
    if (!$mobiles) return ['ok' => false, 'status' => 'no_phone', 'sent' => 0, 'failed' => 0, 'error' => 'شماره موبایل معتبر وجود ندارد'];

    $payload = [
        'lineNumber' => (int)$cfg['line_number'],
        'messageText' => $message,
        'mobiles' => $mobiles,
        'sendDateTime' => null,
    ];
    $http = sms_http_post(SMSIR_BULK_URL, $payload, $cfg, false);

    foreach ($mobiles as $i => $mobile) {
        sms_log_result($userId, $mobile, $message, $templateType, $relatedId, $http['ok'], $http['response'], sms_extract_message_id($http['json'], $i), $http['error']);
    }

    return [
        'ok' => $http['ok'],
        'status' => $http['ok'] ? 'sent' : 'failed',
        'sent' => $http['ok'] ? count($mobiles) : 0,
        'failed' => $http['ok'] ? 0 : count($mobiles),
        'error' => $http['error'],
        'http_code' => $http['http_code'],
        'response' => $http['response'],
    ];
}

/** ارسال متن‌های متناظر به شماره‌های متناظر با endpoint رسمی likeToLike. */
function sms_send_like_to_like(array $items, string $templateType = 'general', ?int $advisorId = null): array {
    $cfg = sms_config($advisorId);
    if (!sms_is_enabled($advisorId)) {
        return ['ok' => false, 'status' => 'disabled', 'sent' => 0, 'failed' => 0, 'no_phone' => 0, 'details' => [], 'error' => 'سرویس پیامک فعال یا کامل تنظیم نشده است'];
    }

    $valid = [];
    $details = [];
    $noPhone = 0;
    foreach ($items as $item) {
        $phone = sms_normalize_phone((string)($item['phone'] ?? ''));
        if (!$phone) {
            $noPhone++;
            $details[] = ['status' => 'no_phone', 'phone' => null, 'user_id' => $item['user_id'] ?? null, 'related_id' => $item['related_id'] ?? 0, 'error' => 'شماره موبایل نامعتبر است'];
            continue;
        }
        $valid[] = [
            'phone' => $phone,
            'message' => sms_shorten_message((string)($item['message'] ?? '')),
            'date_text' => (string)($item['date_text'] ?? ''),
            'time_text' => (string)($item['time_text'] ?? ''),
            'user_id' => isset($item['user_id']) ? (int)$item['user_id'] : null,
            'related_id' => isset($item['related_id']) ? (int)$item['related_id'] : 0,
            'meta' => $item['meta'] ?? [],
        ];
    }

    if (!$valid) return ['ok' => false, 'status' => 'no_phone', 'sent' => 0, 'failed' => 0, 'no_phone' => $noPhone, 'details' => $details, 'error' => 'شماره موبایل معتبر وجود ندارد'];

    // برای شماره‌های موجود در لیست سیاه sms.ir باید از قالب خدماتی/verify استفاده شود.
    if (sms_has_verify_template($advisorId)) {
        $verify = sms_send_meeting_verify_batch($valid, $templateType, $advisorId);
        $verify['no_phone'] += $noPhone;
        if ($details) $verify['details'] = array_merge($details, $verify['details'] ?? []);
        return $verify;
    }

    // اگر فقط یک پیام داریم bulk هم رسمی‌تر و پایدارتر است.
    if (count($valid) === 1) {
        $one = $valid[0];
        $res = sms_send_bulk([$one['phone']], $one['message'], $templateType, $one['related_id'], $one['user_id'], $advisorId);
        $details[] = ['status' => $res['status'], 'phone' => $one['phone'], 'user_id' => $one['user_id'], 'related_id' => $one['related_id'], 'error' => $res['error'], 'meta' => $one['meta']];
        return ['ok' => $res['ok'], 'status' => $res['status'], 'sent' => $res['sent'], 'failed' => $res['failed'], 'no_phone' => $noPhone, 'details' => $details, 'error' => $res['error']];
    }

    $payload = [
        'lineNumber' => (int)$cfg['line_number'],
        'messageTexts' => array_column($valid, 'message'),
        'mobiles' => array_column($valid, 'phone'),
        'senddatetime' => null,
    ];
    $http = sms_http_post(SMSIR_LIKE_TO_LIKE_URL, $payload, $cfg, true);

    foreach ($valid as $i => $row) {
        sms_log_result($row['user_id'], $row['phone'], $row['message'], $templateType, $row['related_id'], $http['ok'], $http['response'], sms_extract_message_id($http['json'], $i), $http['error']);
        $details[] = ['status' => $http['ok'] ? 'sent' : 'failed', 'phone' => $row['phone'], 'user_id' => $row['user_id'], 'related_id' => $row['related_id'], 'error' => $http['error'], 'meta' => $row['meta']];
    }

    return [
        'ok' => $http['ok'],
        'status' => $http['ok'] ? 'sent' : 'failed',
        'sent' => $http['ok'] ? count($valid) : 0,
        'failed' => $http['ok'] ? 0 : count($valid),
        'no_phone' => $noPhone,
        'details' => $details,
        'error' => $http['error'],
        'http_code' => $http['http_code'],
        'response' => $http['response'],
    ];
}

/** سازگاری با کدهای قبلی: ارسال تکی. */
function sms_send(string $phone, string $message, string $templateType = 'general', int $relatedId = 0, ?int $userId = null, ?int $advisorId = null): array {
    $res = sms_send_bulk([$phone], $message, $templateType, $relatedId, $userId, $advisorId);
    return [
        'ok' => $res['ok'],
        'status' => $res['status'],
        'message_id' => null,
        'error' => $res['error'],
        'http_code' => $res['http_code'] ?? 0,
    ];
}

function sms_build_meeting_message(string $title, string $date, ?string $time, string $sessionType = 'consultation'): string {
    $persianDate = jalali_date($date);
    $cleanTime = trim((string)$time);
    $persianTime = $cleanTime !== '' ? fa_num(substr($cleanTime, 0, 5)) : '—';

    // طبق قالب رسمی موردنظر، متن عمومی جلسه ثابت است.
    return "با سلام و احترام،\n\n"
         . "جلسه‌ی مشاوره شما در سامانه مَدار تنظیم شد.\n\n"
         . "📅 تاریخ: {$persianDate}\n"
         . "🕐 ساعت: {$persianTime}\n\n"
         . "لطفاً پنل کاربری خود را در مَدار بررسی فرمایید.\n\n"
         . "madaar-edu.ir";
}
