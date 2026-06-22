<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reporting.php';
boot_session();
require_login();
require_csrf();
$u = current_user();
$in = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? body_json() : $_POST;
$action = (string)($in['action'] ?? '');
try {
    if ($action === 'submit') {
        require_role('student');
        $type = in_array($in['report_type'] ?? 'daily', ['daily','weekly','monthly'], true) ? $in['report_type'] : 'daily';
        $date = (string)($in['date'] ?? 'now');
        
        // قفل گزارش‌ها: روزانه ساعت ۱۲ شب، هفتگی بعد از جمعه، ماهانه بعد از آخرین روز ماه شمسی
        [$start, $end] = report_period($type, $date);
        if (report_deadline_passed($type, $start, $end)) {
            json_out(['ok'=>false, 'error'=>'مهلت ثبت این گزارش به پایان رسیده است و قفل شده است.'], 400);
        }
        if (!report_can_submit_now((int)$u['id'], $type, $start)) {
            json_out(['ok'=>false, 'error'=>'این گزارش هنوز قابل ثبت نیست.'], 400);
        }
        
        $advanced = is_array($in['advanced'] ?? null) ? $in['advanced'] : [];
        $r = report_submit((int)$u['id'], $type, $date, $advanced);
        notify((int)$u['advisor_id'], 'گزارش جدید دانش‌آموز ثبت شد', $u['full_name'].' گزارش '.report_type_label($type).' را ارسال کرد.', 'chart', 'admin/student_reports.php?student='.(int)$u['id'].'&type='.$type);
        $next = report_next_due_after_submit((int)$u['id'], $type, $type === 'daily' ? $start : date('Y-m-d'));
        json_out(['ok'=>true,'report_id'=>(int)$r['id'],'status'=>$r['status'],'next_report'=>$next]);
    }
    json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=>APP_ENV==='development'?$e->getMessage():'خطای سرور'],500);
}
