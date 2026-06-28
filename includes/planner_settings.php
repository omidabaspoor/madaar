<?php
/**
 * تنظیمات پیش‌فرض مشاور + حافظه‌ی هوشمند برنامه‌ریز
 * این فایل از includes/models.php بارگذاری می‌شود.
 */
declare(strict_types=1);

/** پیش‌فرض‌های کارخانه‌ای برنامه‌ریز (وقتی مشاور چیزی تنظیم نکرده) */
function planner_settings_defaults(): array {
    return [
        'default_duration'    => '90',          // مدت پیش‌فرض تسک (دقیقه)
        'default_test_count'  => '40',          // تعداد تست پیش‌فرض
        'default_priority'    => 'normal',      // اولویت پیش‌فرض
        'paste_mode'          => 'single',      // single = یک‌بار | sticky = چندبار پشت‌سرهم
        'grid_density'        => 'comfortable', // comfortable | compact
        'smart_autofill'      => '1',           // پرکردن خودکار بر اساس انتخاب‌های قبلی
        'special_reading_min' => '45',          // مدت روزخوانی پیش‌فرض واحد ویژه
        'special_exam_min'    => '50',          // مدت آزمونک پیش‌فرض واحد ویژه
        'insight_enabled'     => '1',           // نمایش تحلیل هوشمند گزارش پیشرفته (بتا)
        'mock_analysis_enabled'=> '1',       // تحلیل هوشمند آزمون آزمایشی/کنکور (بتا)
        'review_enabled'      => '1',           // سیستم مرور فاصله‌دار
        'web_notifications'   => '1',           // پیشنهاد اعلان وب‌اپ
        'achievements_enabled'=> '1',           // سیستم گیمیفیکیشن و دستاوردها
        'mood_logging_enabled'=> '1',           // ثبت روزانه حال و ایموجی روحیه
        'taraz_samurai_enabled'=> '1',          // ترازسنج کشوری و کنکوری مَدار
        'precision_samurai_enabled'=> '1',      // آسیب‌شناسی تعاملی و تحلیل ضریب دقت
        'auto_mark_missed_enabled'=> '1',       // قرمزکردن خودکار تسک‌های منقضی‌شده
        'sms_enabled'          => '0',           // فعال‌سازی ارسال پیامک sms.ir
        'sms_api_key'          => '',            // کلید API پنل sms.ir
        'sms_line_number'      => '',            // شماره خط ارسال‌کننده
        'sms_template_id'      => '',            // شناسه قالب خدماتی sms.ir برای عبور از بلک‌لیست
        'sms_param_date'       => 'DATE',        // نام متغیر تاریخ در قالب
        'sms_param_time'       => 'TIME',        // نام متغیر ساعت در قالب
    ];
}

/** آیا جدول تنظیمات وجود دارد؟ اگر نبود، خودکار می‌سازد (خود-ترمیم‌گر). */
function settings_table_ready(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try { db()->query('SELECT 1 FROM advisor_settings LIMIT 1'); return $ok = true; }
    catch (Throwable $e) {
        // تلاش برای ساخت خودکار جدول (تا نیازی به اجرای دستی install.php نباشد)
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS advisor_settings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                advisor_id INT UNSIGNED NOT NULL,
                skey VARCHAR(60) NOT NULL,
                svalue VARCHAR(255) DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_advisor_key (advisor_id, skey),
                KEY idx_setting_advisor (advisor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return $ok = true;
        } catch (Throwable $e2) {
            return $ok = false;
        }
    }
}

/** همه‌ی تنظیمات یک مشاور (ادغام‌شده با پیش‌فرض‌ها) */
function advisor_settings(int $advisorId): array {
    $cfg = planner_settings_defaults();
    if (!$advisorId || !settings_table_ready()) return $cfg;
    try {
        $st = db()->prepare('SELECT skey, svalue FROM advisor_settings WHERE advisor_id=?');
        $st->execute([$advisorId]);
        foreach ($st->fetchAll() as $r) {
            if (array_key_exists($r['skey'], $cfg) && $r['svalue'] !== null && $r['svalue'] !== '') {
                $cfg[$r['skey']] = (string)$r['svalue'];
            }
        }
    } catch (Throwable $e) { /* defaults */ }
    return $cfg;
}

/** پیکربندی آماده برای فرانت (با تبدیل نوع‌ها) */
function planner_config_js(int $advisorId): array {
    $c = advisor_settings($advisorId);
    return [
        'defaultDuration'  => (int)$c['default_duration'],
        'defaultTestCount' => (int)$c['default_test_count'],
        'defaultPriority'  => $c['default_priority'],
        'pasteMode'        => $c['paste_mode'],
        'gridDensity'      => $c['grid_density'],
        'smartAutofill'    => $c['smart_autofill'] === '1',
        'specialReading'   => (int)$c['special_reading_min'],
        'specialExam'      => (int)$c['special_exam_min'],
        'insightEnabled'   => $c['insight_enabled'] === '1',
        'reviewEnabled'    => $c['review_enabled'] === '1',
    ];
}

/** ذخیره/به‌روزرسانی یک تنظیم مشاور */
function set_advisor_setting(int $advisorId, string $key, string $value): void {
    if (!settings_table_ready()) return;
    $st = db()->prepare('INSERT INTO advisor_settings (advisor_id,skey,svalue) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)');
    $st->execute([$advisorId, $key, $value]);
}

/** ذخیره‌ی گروهی تنظیمات با اعتبارسنجی */
function save_planner_settings(int $advisorId, array $in): void {
    $allowed = planner_settings_defaults();
    $enums = [
        'default_priority' => ['low','normal','high'],
        'paste_mode'       => ['single','sticky'],
        'grid_density'     => ['comfortable','compact'],
        'smart_autofill'   => ['0','1'],
    ];
    $numeric  = ['default_duration','default_test_count','special_reading_min','special_exam_min'];
    $checkbox = ['smart_autofill','insight_enabled','mock_analysis_enabled','review_enabled','web_notifications','achievements_enabled','mood_logging_enabled','taraz_samurai_enabled','precision_samurai_enabled','auto_mark_missed_enabled']; // چک‌باکس‌ها: نبودشان در POST یعنی خاموش
    foreach ($allowed as $k => $def) {
        if (in_array($k, $checkbox, true)) {
            // اگر چک‌باکس تیک نخورده باشد اصلاً در POST نمی‌آید → '0'
            $v = array_key_exists($k, $in) ? '1' : '0';
            set_advisor_setting($advisorId, $k, $v);
            continue;
        }
        if (!array_key_exists($k, $in)) continue;
        $v = trim((string)$in[$k]);
        if (in_array($k, $numeric, true)) {
            $v = (string)max(0, min(600, (int)$v));
        } elseif (isset($enums[$k]) && !in_array($v, $enums[$k], true)) {
            continue;
        }
        set_advisor_setting($advisorId, $k, $v);
    }
}

/* ---------- حافظه‌ی هوشمند: یادگیری آخرین انتخاب‌های مشاور ---------- */
function memory_table_ready(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try { db()->query('SELECT 1 FROM planner_memory LIMIT 1'); return $ok = true; }
    catch (Throwable $e) {
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS planner_memory (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                advisor_id INT UNSIGNED NOT NULL,
                scope VARCHAR(20) NOT NULL DEFAULT 'global',
                ctx_key VARCHAR(60) NOT NULL DEFAULT '*',
                task_type VARCHAR(20) DEFAULT NULL,
                subject_id INT UNSIGNED DEFAULT NULL,
                target_count INT DEFAULT NULL,
                target_unit VARCHAR(20) DEFAULT NULL,
                duration_min INT DEFAULT NULL,
                priority VARCHAR(10) DEFAULT NULL,
                source VARCHAR(120) DEFAULT NULL,
                hits INT UNSIGNED NOT NULL DEFAULT 1,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_mem (advisor_id, scope, ctx_key),
                KEY idx_mem_advisor (advisor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return $ok = true;
        } catch (Throwable $e2) {
            return $ok = false;
        }
    }
}

/** آیا ستون source در حافظه هست؟ */
function memory_has_source(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try { $c = db()->query("SHOW COLUMNS FROM planner_memory LIKE 'source'")->fetch(); return $ok = (bool)$c; }
    catch (Throwable $e) { return $ok = false; }
}

/** ثبت یک انتخاب در حافظه (global + per-unit + per-subject) */
function remember_task_choice(int $advisorId, array $t): void {
    if (!$advisorId || !memory_table_ready()) return;
    $scopes = [['global', '*']];
    if (isset($t['unit_index']) && $t['unit_index'] !== '') $scopes[] = ['unit', (string)(int)$t['unit_index']];
    if (!empty($t['subject_id'])) $scopes[] = ['subject', (string)(int)$t['subject_id']];

    $type = (string)($t['task_type'] ?? 'study');
    $subj = !empty($t['subject_id']) ? (int)$t['subject_id'] : null;
    $tc   = ($t['target_count'] ?? '') !== '' ? (int)$t['target_count'] : null;
    $tu   = trim((string)($t['target_unit'] ?? '')) ?: null;
    $dur  = ($t['duration_min'] ?? '') !== '' ? (int)$t['duration_min'] : null;
    $prio = in_array($t['priority'] ?? '', ['low','normal','high'], true) ? $t['priority'] : null;
    $src  = trim((string)($t['source'] ?? '')) ?: null;

    if (memory_has_source()) {
        $sql = 'INSERT INTO planner_memory
                  (advisor_id,scope,ctx_key,task_type,subject_id,target_count,target_unit,duration_min,priority,source,hits)
                VALUES (?,?,?,?,?,?,?,?,?,?,1)
                ON DUPLICATE KEY UPDATE
                  task_type=VALUES(task_type), subject_id=VALUES(subject_id),
                  target_count=VALUES(target_count), target_unit=VALUES(target_unit),
                  duration_min=VALUES(duration_min), priority=VALUES(priority), source=VALUES(source), hits=hits+1';
        $st = db()->prepare($sql);
        foreach ($scopes as [$scope, $ctx]) {
            try { $st->execute([$advisorId, $scope, $ctx, $type, $subj, $tc, $tu, $dur, $prio, $src]); }
            catch (Throwable $e) { /* ignore */ }
        }
    } else {
        $sql = 'INSERT INTO planner_memory
                  (advisor_id,scope,ctx_key,task_type,subject_id,target_count,target_unit,duration_min,priority,hits)
                VALUES (?,?,?,?,?,?,?,?,?,1)
                ON DUPLICATE KEY UPDATE
                  task_type=VALUES(task_type), subject_id=VALUES(subject_id),
                  target_count=VALUES(target_count), target_unit=VALUES(target_unit),
                  duration_min=VALUES(duration_min), priority=VALUES(priority), hits=hits+1';
        $st = db()->prepare($sql);
        foreach ($scopes as [$scope, $ctx]) {
            try { $st->execute([$advisorId, $scope, $ctx, $type, $subj, $tc, $tu, $dur, $prio]); }
            catch (Throwable $e) { /* ignore */ }
        }
    }
}

/**
 * بهترین حدسِ هوشمند برای پرکردن خودکار یک خانه.
 * اولویت: درس (اگر داده شده) → واحد → سراسری.
 * خروجی: آرایه‌ی فیلدها یا null اگر چیزی در حافظه نباشد.
 */
function suggest_task_defaults(int $advisorId, ?int $unitIndex = null, ?int $subjectId = null): ?array {
    if (!$advisorId || !memory_table_ready()) return null;
    $candidates = [];
    if ($subjectId) $candidates[] = ['subject', (string)$subjectId];
    if ($unitIndex !== null) $candidates[] = ['unit', (string)$unitIndex];
    $candidates[] = ['global', '*'];

    $st = db()->prepare('SELECT * FROM planner_memory WHERE advisor_id=? AND scope=? AND ctx_key=? LIMIT 1');
    foreach ($candidates as [$scope, $ctx]) {
        try {
            $st->execute([$advisorId, $scope, $ctx]);
            $row = $st->fetch();
            if ($row) {
                return [
                    'task_type'    => $row['task_type'],
                    'subject_id'   => $row['subject_id'] !== null ? (int)$row['subject_id'] : null,
                    'target_count' => $row['target_count'] !== null ? (int)$row['target_count'] : null,
                    'target_unit'  => $row['target_unit'],
                    'duration_min' => $row['duration_min'] !== null ? (int)$row['duration_min'] : null,
                    'priority'     => $row['priority'],
                    'source'       => $row['source'] ?? null,
                    '_scope'       => $scope,
                ];
            }
        } catch (Throwable $e) { /* try next */ }
    }
    return null;
}


function advisor_feature_enabled(int $advisorId, string $key): bool {
    $cfg = advisor_settings($advisorId);
    return ($cfg[$key] ?? '1') === '1';
}
