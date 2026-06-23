<?php
/**
 * مَدار · Madar Study OS — SMS Service (sms.ir)
 * ------------------------------------------------
 * سرویس ارسال پیامک از طریق پنل sms.ir
 * فقط برای ارسال اعلان تنظیم جلسه (مشاوره/کلاس) استفاده می‌شود.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * نرمال‌سازی شماره موبایل ایران به فرمت بین‌المللی (989xxxxxxxxx)
 * @param string|null $phone شماره خام
 * @return string|null شماره نرمال‌شده یا null در صورت نامعتبر بودن
 */
function sms_normalize_phone(?string $phone): ?string {
    if (empty($phone)) return null;
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!$phone) return null;

    if (strlen($phone) === 11 && str_starts_with($phone, '09')) {
        return '98' . substr($phone, 1);
    }
    if (strlen($phone) === 10 && str_starts_with($phone, '9')) {
        return '98' . $phone;
    }
    if (strlen($phone) === 12 && str_starts_with($phone, '98')) {
        return $phone;
    }
    return null;
}

/**
 * بررسی فعال بودن سرویس SMS
 */
function sms_is_enabled(): bool {
    return defined('SMS_ENABLED') && SMS_ENABLED === true
        && defined('SMS_API_KEY') && !empty(SMS_API_KEY)
        && defined('SMS_API_URL') && !empty(SMS_API_URL);
}

/**
 * ارسال پیامک از طریق sms.ir
 *
 * @param string      $phone        شماره موبایل (09xxxxxxxxx یا 989xxxxxxxxx)
 * @param string      $message      متن پیامک (حداکثر ~۳۲۰ کاراکتر فارسی)
 * @param string      $templateType نوع رویداد (meeting_consultation, meeting_class, ...)
 * @param int         $relatedId    شناسه‌ی مرتبط (مثلاً consultation_session.id)
 * @param int|null    $userId       شناسه‌ی کاربر مَدار
 * @return array ['ok'=>bool, 'status'=>'sent|failed|no_phone|disabled', 'message_id'=>?int, 'error'=>?string]
 */
function sms_send(string $phone, string $message, string $templateType = 'general', int $relatedId = 0, ?int $userId = null): array {

    // ۱. بررسی فعال بودن
    if (!sms_is_enabled()) {
        return ['ok' => false, 'status' => 'disabled', 'error' => 'سرویس پیامک غیرفعال است'];
    }

    // ۲. نرمال‌سازی شماره
    $normalized = sms_normalize_phone($phone);
    if (!$normalized) {
        return ['ok' => false, 'status' => 'failed', 'error' => 'شماره موبایل نامعتبر است'];
    }

    // ۳. محدودیت طول پیام (۲ سگمنت = ~۳۲۰ کاراکتر فارسی)
    if (mb_strlen($message, 'UTF-8') > 320) {
        $message = mb_substr($message, 0, 318, 'UTF-8') . '…';
    }

    // ۴. ارسال درخواست به sms.ir
    $url = SMS_API_URL;
    $lineNumber = defined('SMS_LINE_NUMBER') ? SMS_LINE_NUMBER : '';
    $payload = [
        'lineNumber' => $lineNumber,
        'mobile'     => [$normalized],
        'message'    => $message,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-KEY: ' . SMS_API_KEY,
        ],
        CURLOPT_TIMEOUT        => defined('SMS_TIMEOUT') ? SMS_TIMEOUT : 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // ۵. تحلیل پاسخ
    $result = $response !== false ? json_decode($response, true) : null;
    $success = ($httpCode === 200 && is_array($result) && (int)($result['status'] ?? 0) === 1);

    // استخراج شناسه پیامک از data (ممکن است آرایه یا آبجکت باشد)
    $messageId = null;
    if (is_array($result) && isset($result['data'])) {
        if (is_array($result['data'])) {
            $messageId = $result['data'][0] ?? ($result['data']['messageId'] ?? null);
        } elseif (is_object($result['data'])) {
            $messageId = $result['data']->messageId ?? null;
        }
    }

    $errorMsg = null;
    if (!$success) {
        if ($curlError) {
            $errorMsg = 'خطای ارتباط: ' . $curlError;
        } elseif (is_array($result) && isset($result['message'])) {
            $errorMsg = (string)$result['message'];
        } else {
            $errorMsg = 'خطای ناشناخته (HTTP ' . $httpCode . ')';
        }
    }

    // ۶. ثبت در لاگ DB
    try {
        $logUserId = $userId ?? (int)(current_user()['id'] ?? 0);
        db()->prepare('INSERT INTO sms_log
            (user_id, phone, message, template_type, related_id, status, api_response, api_message_id, error_message, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $logUserId,
                $normalized,
                $message,
                $templateType,
                $relatedId,
                $success ? 'sent' : 'failed',
                $response !== false ? substr((string)$response, 0, 4000) : null,
                $messageId !== null ? (string)$messageId : null,
                $errorMsg,
                $success ? date('Y-m-d H:i:s') : null,
            ]);
    } catch (Throwable $e) {
        error_log('Madar SMS log error: ' . $e->getMessage());
    }

    return [
        'ok'         => $success,
        'status'     => $success ? 'sent' : 'failed',
        'message_id' => $messageId,
        'error'      => $errorMsg,
        'http_code'  => $httpCode,
    ];
}

/**
 * ساخت متن پیامک جلسه (مشاوره یا کلاس)
 *
 * @param string $title       عنوان جلسه
 * @param string $date        تاریخ میلادی (Y-m-d)
 * @param string|null $time   ساعت (HH:MM:SS) یا null
 * @param string $sessionType consultation | class
 * @return string متن پیامک
 */
function sms_build_meeting_message(string $title, string $date, ?string $time, string $sessionType = 'consultation'): string {
    $persianDate = jalali_date($date); // مثلاً: ۲۵ خرداد ۱۴۰۳
    $persianTime = $time ? fa_num(substr((string)$time, 0, 5)) : 'ساعت توافقی';

    $typeText = $sessionType === 'class' ? 'کلاس درسی' : 'جلسه‌ی مشاوره';

    return "با سلام و احترام،\n\n"
         . "{$typeText} شما در سامانه مَدار تنظیم شد.\n\n"
         . "📅 تاریخ: {$persianDate}\n"
         . "🕐 ساعت: {$persianTime}\n\n"
         . "لطفاً پنل کاربری خود را در مَدار بررسی فرمایید.\n\n"
         . "madaar-edu.ir";
}
