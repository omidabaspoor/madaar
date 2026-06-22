<?php
/** سیستم گزارش‌دهی پیشرفته دانش‌آموز */
declare(strict_types=1);
require_once __DIR__ . '/models.php';

function report_schema_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS student_reports (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          student_id INT UNSIGNED NOT NULL,
          report_type ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
          period_start DATE NOT NULL,
          period_end DATE NOT NULL,
          auto_snapshot_json LONGTEXT NULL,
          advanced_json LONGTEXT NULL,
          status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
          submitted_at DATETIME DEFAULT NULL,
          advisor_note TEXT NULL,
          reviewed_by INT UNSIGNED DEFAULT NULL,
          reviewed_at DATETIME DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_student_report (student_id, report_type, period_start),
          KEY idx_report_student (student_id, report_type, period_start),
          KEY idx_report_status (status, submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // برای نصب‌هایی که جدول را با نسخه قدیمی/ناقص ساخته‌اند، ستون‌های ضروری را ترمیم کن.
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM student_reports')->fetchAll() as $c) $cols[$c['Field']] = true;
        $adds = [
            'auto_snapshot_json' => 'ALTER TABLE student_reports ADD COLUMN auto_snapshot_json LONGTEXT NULL AFTER period_end',
            'advanced_json' => 'ALTER TABLE student_reports ADD COLUMN advanced_json LONGTEXT NULL AFTER auto_snapshot_json',
            'status' => "ALTER TABLE student_reports ADD COLUMN status ENUM('draft','submitted') NOT NULL DEFAULT 'draft' AFTER advanced_json",
            'submitted_at' => 'ALTER TABLE student_reports ADD COLUMN submitted_at DATETIME NULL AFTER status',
            'advisor_note' => 'ALTER TABLE student_reports ADD COLUMN advisor_note TEXT NULL AFTER submitted_at',
            'reviewed_by' => 'ALTER TABLE student_reports ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER advisor_note',
            'reviewed_at' => 'ALTER TABLE student_reports ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by',
        ];
        foreach ($adds as $col=>$sql) if (empty($cols[$col])) db()->exec($sql);
        try { db()->exec('ALTER TABLE student_reports ADD UNIQUE KEY uq_student_report (student_id, report_type, period_start)'); } catch (Throwable $e) {}
        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}


function report_jalali_is_leap(int $jy): bool
{
    // الگوریتم رایج تقویم جلالی (دوره ۲۸۲۰ ساله)
    $a = $jy - (($jy >= 0) ? 474 : 473);
    $b = 474 + ($a % 2820);
    return ((($b + 38) * 682) % 2816) < 682;
}
function report_jalali_month_length(int $jy, int $jm): int
{
    if ($jm <= 6) return 31;
    if ($jm <= 11) return 30;
    return report_jalali_is_leap($jy) ? 30 : 29;
}
function report_jalali_to_gregorian_date(int $jy, int $jm, int $jd): string
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + intdiv($jy, 33) * 8 + intdiv((($jy % 33) + 3), 4) + $jd;
    $days += ($jm < 7) ? (($jm - 1) * 31) : ((($jm - 7) * 30) + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = [0,31, (($gy % 4 === 0 && $gy % 100 !== 0) || ($gy % 400 === 0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
    for ($gm = 1; $gm <= 12 && $gd > $sal_a[$gm]; $gm++) $gd -= $sal_a[$gm];
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}
function report_is_jalali_month_last_day(string $date): bool
{
    $ts = strtotime($date);
    if ($ts === false) $ts = time();
    [$jy, $jm, $jd] = gregorian_to_jalali((int)date('Y',$ts), (int)date('n',$ts), (int)date('j',$ts));
    return (int)$jd === report_jalali_month_length((int)$jy, (int)$jm);
}

function report_period(string $type, string $date = 'now'): array
{
    $ts = $date === 'now' ? time() : strtotime($date);
    if ($ts === false) $ts = time();
    if ($type === 'weekly') {
        $s = week_saturday(date('Y-m-d', $ts));
        return [$s, date('Y-m-d', strtotime($s.' +6 day'))];
    }
    if ($type === 'monthly') {
        // ماهانه بر اساس ماه شمسی محاسبه می‌شود تا «آخر ماه» برای کاربر ایرانی دقیق باشد.
        [$jy, $jm] = gregorian_to_jalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
        $startG = report_jalali_to_gregorian_date($jy, $jm, 1);
        $endG = report_jalali_to_gregorian_date($jy, $jm, report_jalali_month_length($jy, $jm));
        return [$startG, $endG];
    }
    return [date('Y-m-d', $ts), date('Y-m-d', $ts)];
}
function report_type_label(string $type): string
{
    return ['daily'=>'روزانه','weekly'=>'هفتگی','monthly'=>'ماهانه'][$type] ?? $type;
}

function report_auto_snapshot(int $studentId, string $start, string $end): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $effective = task_effective_condition_sql('t','p','u');
    $st = db()->prepare("SELECT t.*, s.name subj_name, s.color subj_color, DATE_ADD(p.week_start, INTERVAL t.day_index DAY) task_date
        FROM tasks t JOIN plans p ON p.id=t.plan_id
        JOIN users u ON u.id=t.student_id
        LEFT JOIN subjects s ON s.id=t.subject_id
        WHERE t.student_id=? AND p.status='published'
          AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) BETWEEN ? AND ?
          AND $effective
        ORDER BY task_date, t.unit_index, t.sort_order, t.id");
    $st->execute([$studentId,$start,$end]);
    $rows = $st->fetchAll();
    $out = [
        'generated_at'=>date('Y-m-d H:i:s'), 'period_start'=>$start, 'period_end'=>$end,
        'total_tasks'=>0, 'full'=>0, 'partial'=>0, 'missed'=>0, 'pending'=>0,
        'score'=>0.0, 'progress_percent'=>0, 'tests_done'=>0, 'planned_minutes'=>0, 'effective_minutes'=>0,
        'study_hours'=>0, 'course_avg'=>null, 'target_tests'=>0, 'extra_tests'=>0, 'by_subject'=>[], 'by_day'=>[], 'by_type'=>[], 'red_tasks'=>[],
    ];
    $courses = [];
    foreach ($rows as $t) {
        $status = task_status($t);
        $score = task_score($t);
        $date = (string)$t['task_date'];
        $subj = trim((string)($t['subj_name'] ?: $t['title'] ?: 'بدون درس'));
        $dur = (int)($t['duration_min'] ?? 0);
        $doneCount = (int)($t['done_count'] ?? 0);
        $typeKey = (string)($t['task_type'] ?? 'custom');
        $isTestish = str_contains((string)($t['target_unit'] ?? ''), 'تست') || in_array($typeKey, ['test','exam','mock'], true);
        if (!isset($out['by_type'][$typeKey])) $out['by_type'][$typeKey] = ['tasks'=>0,'score'=>0,'tests'=>0,'target_tests'=>0,'minutes'=>0];
        $out['by_type'][$typeKey]['tasks']++;
        $out['by_type'][$typeKey]['score'] += $score;
        $out['by_type'][$typeKey]['minutes'] += (int)round($dur * min($score, 1));
        $out['total_tasks']++;
        if (isset($out[$status])) $out[$status]++;
        $out['score'] += $score;
        $out['planned_minutes'] += $dur;
        $out['effective_minutes'] += (int)round($dur * min($score, 1));
        if ($isTestish) {
            $out['tests_done'] += $doneCount;
            $targetCnt = (int)($t['target_count'] ?? 0);
            $out['target_tests'] += $targetCnt;
            $out['extra_tests'] += max(0, $doneCount - $targetCnt);
            $out['by_type'][$typeKey]['tests'] += $doneCount;
            $out['by_type'][$typeKey]['target_tests'] += $targetCnt;
        }
        if ($t['course_percent'] !== null) $courses[] = (int)$t['course_percent'];
        if (!isset($out['by_subject'][$subj])) $out['by_subject'][$subj] = ['tasks'=>0,'score'=>0,'tests'=>0,'minutes'=>0,'full'=>0,'partial'=>0,'missed'=>0];
        $out['by_subject'][$subj]['tasks']++;
        $out['by_subject'][$subj]['score'] += $score;
        $out['by_subject'][$subj]['minutes'] += (int)round($dur * min($score, 1));
        if ($isTestish) $out['by_subject'][$subj]['tests'] += $doneCount;
        if (isset($out['by_subject'][$subj][$status])) $out['by_subject'][$subj][$status]++;
        if (!isset($out['by_day'][$date])) $out['by_day'][$date] = ['tasks'=>0,'score'=>0,'tests'=>0,'minutes'=>0,'full'=>0,'partial'=>0,'missed'=>0];
        $out['by_day'][$date]['tasks']++;
        $out['by_day'][$date]['score'] += $score;
        $out['by_day'][$date]['minutes'] += (int)round($dur * min($score, 1));
        if ($isTestish) $out['by_day'][$date]['tests'] += $doneCount;
        if (isset($out['by_day'][$date][$status])) $out['by_day'][$date][$status]++;
        if ($status === 'missed') $out['red_tasks'][] = ['date'=>$date,'title'=>$t['title'],'subject'=>$t['subj_name'],'type'=>$t['task_type']];
    }
    $out['progress_percent'] = $out['total_tasks'] ? round($out['score']/$out['total_tasks']*100) : 0;
    $out['study_hours'] = round($out['effective_minutes']/60, 1);
    $out['course_avg'] = $courses ? round(array_sum($courses)/count($courses)) : null;
    return $out;
}

function report_get_or_create(int $studentId, string $type, string $date = 'now'): array
{
    report_schema_ready();
    [$start,$end] = report_period($type,$date);
    $snap = report_auto_snapshot($studentId,$start,$end);
    $st = db()->prepare('SELECT * FROM student_reports WHERE student_id=? AND report_type=? AND period_start=? LIMIT 1');
    $st->execute([$studentId,$type,$start]);
    $r = $st->fetch();
    $json = json_encode($snap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (!$r) {
        db()->prepare('INSERT INTO student_reports (student_id,report_type,period_start,period_end,auto_snapshot_json) VALUES (?,?,?,?,?)')
            ->execute([$studentId,$type,$start,$end,$json]);
        $st->execute([$studentId,$type,$start]);
        $r = $st->fetch();
    } else {
        db()->prepare('UPDATE student_reports SET period_end=?, auto_snapshot_json=? WHERE id=?')->execute([$end,$json,$r['id']]);
        $r['auto_snapshot_json'] = $json; $r['period_end']=$end;
    }
    $r['snapshot'] = $snap;
    $r['advanced'] = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
    return $r;
}

function report_submit(int $studentId, string $type, string $date, array $advanced): array
{
    $r = report_get_or_create($studentId,$type,$date);
    $clean = report_clean_advanced($type,$advanced);
    db()->prepare('UPDATE student_reports SET advanced_json=?, status="submitted", submitted_at=COALESCE(submitted_at, NOW()) WHERE id=?')
        ->execute([json_encode($clean, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $r['id']]);
    return report_get_or_create($studentId,$type,$date);
}

function report_clean_advanced(string $type, array $a): array
{
    $num = fn($k,$min,$max,$def=null) => isset($a[$k]) && $a[$k] !== '' ? max($min, min($max, (float)$a[$k])) : $def;
    $txt = fn($k,$max=800) => mb_substr(trim((string)($a[$k] ?? '')),0,$max);
    $base = [
        'sleep_hours'=>$num('sleep_hours',0,16), 'sleep_quality'=>$num('sleep_quality',1,5),
        'focus_score'=>$num('focus_score',1,10), 'energy_score'=>$num('energy_score',1,10), 'stress_score'=>$num('stress_score',1,10),
        'phone_minutes'=>$num('phone_minutes',0,1440,0), 'wasted_minutes'=>$num('wasted_minutes',0,1440,0),
        'mood'=>$txt('mood',60), 'best_win'=>$txt('best_win'), 'main_challenge'=>$txt('main_challenge'),
        'challenge_reason'=>$txt('challenge_reason'), 'solution'=>$txt('solution'), 'advisor_question'=>$txt('advisor_question'),
        'next_priority'=>$txt('next_priority'), 'self_score'=>$num('self_score',1,20),
        'main_reason'=>$txt('main_reason',120), 'week_rating'=>$txt('week_rating',60), 'plan_fit'=>$txt('plan_fit',60),
        'advisor_followup'=>$txt('advisor_followup',60), 'month_satisfaction'=>$num('month_satisfaction',1,10),
        'monthly_mindset'=>$txt('monthly_mindset',80), 'next_month_goal_type'=>$txt('next_month_goal_type',120),
    ];
    if ($type !== 'daily') {
        $base += [
            'best_subject'=>$txt('best_subject',120), 'weak_subject'=>$txt('weak_subject',120),
            'exam_count'=>$num('exam_count',0,30,0), 'exam_analysis_quality'=>$num('exam_analysis_quality',1,5),
            'catchup_hours'=>$num('catchup_hours',0,80,0), 'next_period_goal'=>$txt('next_period_goal'),
        ];
    }
    return $base;
}


function report_period_has_tasks(int $studentId, string $start, string $end): bool
{
    task_status_schema_ready();
    $effective = task_effective_condition_sql('t','p','u');
    $st = db()->prepare("SELECT COUNT(*) FROM tasks t JOIN plans p ON p.id=t.plan_id JOIN users u ON u.id=t.student_id WHERE t.student_id=? AND p.status='published' AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) BETWEEN ? AND ? AND $effective");
    $st->execute([$studentId,$start,$end]);
    return (int)$st->fetchColumn() > 0;
}
function report_period_is_closed(int $studentId, string $start, string $end): bool
{
    task_status_schema_ready();
    // بسته یعنی همه‌ی تسک‌های بازه واقعاً تعیین‌وضعیت شده‌اند: کامل، ناقص یا قرمز.
    // داده‌های قدیمی ممکن است completion_status خالی/NULL داشته باشند؛ اگر is_done=1 باشد کامل حساب می‌شوند، وگرنه pending.
    $effective = task_effective_condition_sql('t','p','u');
    $st = db()->prepare("SELECT COUNT(*) FROM tasks t JOIN plans p ON p.id=t.plan_id JOIN users u ON u.id=t.student_id
        WHERE t.student_id=? AND p.status='published'
          AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) BETWEEN ? AND ?
          AND $effective
          AND COALESCE(NULLIF(t.completion_status,''), IF(t.is_done=1,'full','pending')) NOT IN ('full','partial','missed')");
    $st->execute([$studentId,$start,$end]);
    return (int)$st->fetchColumn() === 0;
}
function report_is_submitted(int $studentId, string $type, string $start): bool
{
    report_schema_ready();
    $st = db()->prepare('SELECT status FROM student_reports WHERE student_id=? AND report_type=? AND period_start=? LIMIT 1');
    $st->execute([$studentId,$type,$start]);
    return (($st->fetchColumn() ?: 'draft') === 'submitted');
}


function report_deadline_passed(string $type, string $start, string $end): bool
{
    $today = date('Y-m-d');
    // روزانه: ساعت ۰۰:۰۰ روز بعد قفل می‌شود.
    if ($type === 'daily') return $start < $today;
    // هفتگی: جمعه ساعت ۲۴ تمام می‌شود؛ از شنبه قفل است.
    if ($type === 'weekly') return $end < $today;
    // ماهانه: آخرین روز ماه شمسی ساعت ۲۴ تمام می‌شود؛ از روز بعد قفل است.
    if ($type === 'monthly') return $end < $today;
    return false;
}

function report_is_locked(string $type, string $date = 'now'): bool
{
    [$s,$e] = report_period($type,$date);
    return report_deadline_passed($type,$s,$e);
}

function report_due_daily_after_tasks(int $studentId, ?string $date = null): bool
{
    $date = $date ?: date('Y-m-d');
    [$start,$end] = report_period('daily',$date);
    if (!report_period_has_tasks($studentId,$start,$end)) return false;
    if (report_is_submitted($studentId,'daily',$start)) return false;

    // حالت ۱: هر زمان همه تسک‌ها تعیین‌وضعیت شدند (کامل/ناقص/قرمز)
    if (report_period_is_closed($studentId,$start,$end)) return true;

    // حالت ۲: از ساعت ۲۳ تا ۲۴ همان روز حتی اگر هنوز تسک pending دارد، گزارش روزانه باید گرفته شود.
    return $start === date('Y-m-d') && (int)date('H') === 23;
}

function report_daily_has_pending_tasks(int $studentId, string $date): bool
{
    task_status_schema_ready();
    [$start,$end] = report_period('daily',$date);
    $effective = task_effective_condition_sql('t','p','u');
    $st = db()->prepare("SELECT COUNT(*) FROM tasks t JOIN plans p ON p.id=t.plan_id JOIN users u ON u.id=t.student_id
        WHERE t.student_id=? AND p.status='published'
          AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) BETWEEN ? AND ?
          AND $effective
          AND COALESCE(NULLIF(t.completion_status,''), IF(t.is_done=1,'full','pending')) NOT IN ('full','partial','missed')");
    $st->execute([$studentId,$start,$end]);
    return (int)$st->fetchColumn() > 0;
}

function report_mark_night_reminder_sent(int $studentId, string $date): bool
{
    [$s,$e] = report_period('daily',$date);
    $r = report_get_or_create($studentId,'daily',$s);
    $adv = $r['advanced'] ?? [];
    if (!empty($adv['student_night_notified'])) return false;
    $adv['student_night_notified'] = date('Y-m-d H:i:s');
    db()->prepare('UPDATE student_reports SET advanced_json=? WHERE id=?')
        ->execute([json_encode($adv, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $r['id']]);
    return true;
}

function report_weekly_due_after_daily(int $studentId, ?string $date = null): bool
{
    $date = $date ?: date('Y-m-d');
    if (persian_day_index($date) !== 6) return false; // جمعه
    [$ds] = report_period('daily',$date);
    if (!report_is_submitted($studentId,'daily',$ds)) return false;
    [$s,$e] = report_period('weekly',$date);
    if (!report_period_has_tasks($studentId,$s,$e)) return false;
    return !report_is_submitted($studentId,'weekly',$s);
}

function report_monthly_due_after_daily(int $studentId, ?string $date = null): bool
{
    $date = $date ?: date('Y-m-d');
    if (!report_is_jalali_month_last_day($date)) return false;
    [$ds] = report_period('daily',$date);
    if (!report_is_submitted($studentId,'daily',$ds)) return false;
    [$s,$e] = report_period('monthly',$date);
    if (!report_period_has_tasks($studentId,$s,$e)) return false;
    return !report_is_submitted($studentId,'monthly',$s);
}

function report_next_due_after_submit(int $studentId, string $submittedType, string $date = 'now'): ?array
{
    $date = $date === 'now' ? date('Y-m-d') : $date;
    if ($submittedType === 'daily') {
        if (report_weekly_due_after_daily($studentId,$date)) {
            [$s,$e] = report_period('weekly',$date);
            report_get_or_create($studentId,'weekly',$s);
            return ['type'=>'weekly','label'=>'هفتگی','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=weekly&date='.$s), 'message'=>'گزارش روزانه ثبت شد؛ حالا گزارش هفتگی جمعه را تکمیل کن.'];
        }
        if (report_monthly_due_after_daily($studentId,$date)) {
            [$s,$e] = report_period('monthly',$date);
            report_get_or_create($studentId,'monthly',$s);
            return ['type'=>'monthly','label'=>'ماهانه','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=monthly&date='.$s), 'message'=>'گزارش روزانه ثبت شد؛ امروز آخر ماه است، گزارش ماهانه را هم تکمیل کن.'];
        }
    }
    if ($submittedType === 'weekly' && report_monthly_due_after_daily($studentId,$date)) {
        [$s,$e] = report_period('monthly',$date);
        report_get_or_create($studentId,'monthly',$s);
        return ['type'=>'monthly','label'=>'ماهانه','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=monthly&date='.$s), 'message'=>'گزارش هفتگی ثبت شد؛ حالا گزارش ماهانه را تکمیل کن.'];
    }
    return null;
}


function report_can_submit_now(int $studentId, string $type, string $date = 'now'): bool
{
    [$s,$e] = report_period($type,$date);
    if (report_deadline_passed($type,$s,$e)) return false;
    // گزارش ثبت‌شده تا قبل از ددلاین قابل ویرایش/ارسال دوباره است.
    if (report_is_submitted($studentId,$type,$s)) return true;

    if ($type === 'daily') return report_due_daily_after_tasks($studentId,$s);
    if ($type === 'weekly') return report_weekly_due_after_daily($studentId,$e);
    if ($type === 'monthly') return report_monthly_due_after_daily($studentId,$e);
    return false;
}

function report_pending_items(int $studentId): array
{
    $items = [];
    $today = date('Y-m-d');

    // روزانه امروز: یا همه تسک‌ها تعیین‌وضعیت شده‌اند، یا ساعت ۲۳ تا ۲۴ رسیده و هنوز گزارش ثبت نشده.
    [$s,$e] = report_period('daily',$today);
    if (report_due_daily_after_tasks($studentId,$today)) {
        $isNight = ((int)date('H') === 23) && report_daily_has_pending_tasks($studentId,$today);
        report_get_or_create($studentId,'daily',$s);
        if ($isNight && report_mark_night_reminder_sent($studentId,$today)) {
            notify($studentId, '⏰ کجایی؟ گزارش امروز هنوز ثبت نشده', 'بین ساعت ۱۱ تا ۱۲ شب هستیم؛ حتی اگر همه تسک‌ها کامل نشده، گزارش روزانه امروزت را همین الان ثبت کن.', 'warning', 'student/reports.php?type=daily&date='.$s);
        }
        $items[] = ['type'=>'daily','label'=>$isNight?'روزانه فوری':'روزانه','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=daily&date='.$s), 'urgent'=>$isNight];
    }

    // هفتگی: فقط جمعه بعد از ثبت گزارش روزانه همان روز.
    if (report_weekly_due_after_daily($studentId,$today)) {
        [$s,$e] = report_period('weekly',$today);
        report_get_or_create($studentId,'weekly',$s);
        $items[] = ['type'=>'weekly','label'=>'هفتگی جمعه','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=weekly&date='.$s)];
    }

    // ماهانه: آخرین روز ماه شمسی، بعد از ثبت گزارش روزانه همان روز.
    if (report_monthly_due_after_daily($studentId,$today)) {
        [$s,$e] = report_period('monthly',$today);
        report_get_or_create($studentId,'monthly',$s);
        $items[] = ['type'=>'monthly','label'=>'ماهانه آخر ماه','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=monthly&date='.$s)];
    }

    // گزارش‌های عقب‌افتاده دیگر قابل تکمیل نیستند: روزانه، هفتگی و ماهانه همگی ساعت ۲۴ ددلاین خود قفل می‌شوند.

    $seen = []; $out = [];
    foreach ($items as $it) { $k=$it['type'].'-'.$it['start']; if(isset($seen[$k])) continue; $seen[$k]=1; $out[]=$it; }
    return $out;
}

function reports_for_student(int $studentId, string $type='daily', int $limit=30): array
{
    report_schema_ready();
    $st = db()->prepare('SELECT * FROM student_reports WHERE student_id=? AND report_type=? ORDER BY period_start DESC LIMIT ?');
    $st->bindValue(1,$studentId,PDO::PARAM_INT); $st->bindValue(2,$type); $st->bindValue(3,$limit,PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['snapshot'] = $r['auto_snapshot_json'] ? (json_decode($r['auto_snapshot_json'], true) ?: []) : [];
        $r['advanced'] = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
    }
    return $rows;
}

function report_clamp(float $v, float $min=0, float $max=100): float { return max($min, min($max, $v)); }
function report_score_label(float $score): string
{
    if ($score >= 85) return 'عالی';
    if ($score >= 70) return 'خوب';
    if ($score >= 50) return 'متوسط';
    return 'نیازمند پیگیری';
}
function report_risk_label(float $risk): string
{
    if ($risk >= 70) return 'زیاد';
    if ($risk >= 45) return 'متوسط';
    return 'کم';
}
function report_avg(array $vals, ?float $default=null): ?float
{
    $vals = array_values(array_filter($vals, fn($v)=>$v !== null && $v !== '' && is_numeric($v)));
    return $vals ? array_sum(array_map('floatval',$vals))/count($vals) : $default;
}
function report_previous_period(string $type, string $start): array
{
    if ($type === 'monthly') return report_period('monthly', date('Y-m-d', strtotime($start.' -1 month')));
    if ($type === 'weekly') return report_period('weekly', date('Y-m-d', strtotime($start.' -7 day')));
    return report_period('daily', date('Y-m-d', strtotime($start.' -1 day')));
}
function report_advanced_aggregate(int $studentId, string $type, string $start, string $end, array $currentAdvanced=[]): array
{
    $rows = [];
    if ($type === 'daily') {
        $rows[] = $currentAdvanced;
    } else {
        report_schema_ready();
        $st = db()->prepare("SELECT advanced_json FROM student_reports WHERE student_id=? AND report_type='daily' AND period_start BETWEEN ? AND ? AND status='submitted'");
        $st->execute([$studentId,$start,$end]);
        foreach ($st->fetchAll() as $r) {
            $a = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
            if ($a) $rows[] = $a;
        }
        if ($currentAdvanced) $rows[] = $currentAdvanced;
    }
    $get = fn($key)=>array_map(fn($r)=>$r[$key] ?? null, $rows);
    return [
        'sleep_hours'=>report_avg($get('sleep_hours')),
        'sleep_quality'=>report_avg($get('sleep_quality')),
        'focus_score'=>report_avg($get('focus_score')),
        'energy_score'=>report_avg($get('energy_score')),
        'stress_score'=>report_avg($get('stress_score')),
        'phone_minutes'=>report_avg($get('phone_minutes'),0),
        'wasted_minutes'=>report_avg($get('wasted_minutes'),0),
        'self_score'=>report_avg($get('self_score')),
        'count'=>count($rows),
    ];
}
function report_trend_label(float $cur, float $prev): string
{
    if ($cur <= 0 && $prev <= 0) return 'در دو بازه اخیر اجرای مؤثری ثبت نشده است';
    if ($cur <= 0 && $prev > 0) return 'متأسفانه نسبت به بازه قبل افت کامل داشته‌اید';
    if ($cur > 0 && $prev <= 0) return 'بازگشت موفق و شروع قدرتمند نسبت به بازه قبل';
    $d = $cur - $prev;
    if ($d >= 8) return 'پیشرفت محسوس و رو به رشد';
    if ($d <= -8) return 'روند کاهشی و نیازمند مراقبت';
    return 'روند باثبات و پایدار';
}

function report_pick(array $items, string $seed): string
{
    if (!$items) return '';
    return $items[abs(crc32($seed)) % count($items)];
}
function report_method_notes(string $type, float $progress, float $testRatio, int $reviewTasks, ?float $sleep, ?float $stress): array
{
    $notes = [];
    if ($testRatio < 70) $notes[] = 'یادآوری فعال/تست آموزشی را به مطالعه اضافه کن؛ فقط دوباره‌خوانی معمولاً ماندگاری کمتری می‌دهد.';
    if ($reviewTasks <= 0) $notes[] = 'برای مطالب خواندنی، مرور فاصله‌دار ۱، ۳، ۷ و ۱۴ روزه باعث افت کمتر روی منحنی فراموشی می‌شود.';
    if ($sleep !== null && $sleep < 6.5) $notes[] = 'خواب کم، تثبیت حافظه را ضعیف می‌کند؛ شب قبل و بعد از یادگیری را جدی بگیر.';
    if ($stress !== null && $stress >= 7) $notes[] = 'استرس بالا را با آزمونک‌های کوتاه و قابل کنترل پایین بیاور؛ سنجش‌های کوچک اضطراب آزمون را کمتر می‌کنند.';
    if ($progress >= 70 && $testRatio >= 70) $notes[] = 'الگوی خوب فعلی را با تست زمان‌دار، تحلیل غلط‌ها و مرور فاصله‌دار حفظ کن.';
    return array_slice(array_values(array_unique($notes)), 0, 4);
}

function report_build_analysis(int $studentId, string $type, string $start, string $end, array $snapshot, array $advanced=[]): array
{
    $prev = report_previous_period($type, $start);
    $prevSnap = report_auto_snapshot($studentId, $prev[0], $prev[1]);
    $agg = report_advanced_aggregate($studentId,$type,$start,$end,$advanced);

    $totalReal = (int)($snapshot['total_tasks'] ?? 0);
    $total = max(1, $totalReal);
    $progress = (float)($snapshot['progress_percent'] ?? 0);
    $prevProgress = (float)($prevSnap['progress_percent'] ?? 0);
    $full = (int)($snapshot['full'] ?? 0);
    $partial = (int)($snapshot['partial'] ?? 0);
    $missed = (int)($snapshot['missed'] ?? 0);
    $pending = (int)($snapshot['pending'] ?? max(0, $totalReal-$full-$partial-$missed));
    $missedRate = ($missed / $total) * 100;
    $partialRate = ($partial / $total) * 100;
    $pendingRate = ($pending / $total) * 100;
    $targetTests = (int)($snapshot['target_tests'] ?? 0);
    $testsDone = (int)($snapshot['tests_done'] ?? 0);
    $testRatio = $targetTests > 0 ? min(140, $testsDone / max(1,$targetTests) * 100) : ($testsDone > 0 ? 70 : 0);

    // اگر اصلاً داده اجرایی نداریم، تحلیل باید صادقانه بگوید داده کافی نیست؛ امتیاز مصنوعی ندهد.
    if ($totalReal === 0) {
        return [
            'beta'=>true, 'overall'=>0, 'overall_label'=>'بدون داده کافی', 'trend'=>'بدون داده کافی', 'prev_progress'=>round($prevProgress),
            'scores'=>['execution'=>0,'consistency'=>0,'tests'=>0,'study_quality'=>0,'recovery'=>0,'subject_balance'=>0,'distraction_control'=>0,'exam_analysis'=>0,'burnout_risk'=>0],
            'weak_subjects'=>[], 'strong_subjects'=>[],
            'alerts'=>[['level'=>'warn','title'=>'داده کافی وجود ندارد','text'=>'برای این بازه هنوز تسک یا اجرای قابل تحلیل ثبت نشده است.']],
            'recommendations'=>['ابتدا برنامه یا تسک‌های این بازه را ثبت و اجرا کنید تا تحلیل قابل اعتماد ساخته شود.'],
            'summary'=>'برای این بازه هنوز داده کافی برای تحلیل وجود ندارد.',
            'method_notes'=>['تحلیل هوشمند فقط وقتی قابل اعتماد است که برنامه و وضعیت اجرای واقعی ثبت شده باشد.'],
            'action_plan'=>['ثبت وضعیت تسک‌های بازه','نوشتن علت اصلی اجرا نشدن','چیدن یک برنامه سبک برای بازگشت به جریان'],
        ];
    }

    $dayPcts = [];
    foreach (($snapshot['by_day'] ?? []) as $d=>$r) $dayPcts[] = !empty($r['tasks']) ? (($r['score'] ?? 0)/max(1,$r['tasks'])*100) : 0;
    if (!$dayPcts) $dayPcts = [$progress];
    $activeDays = count(array_filter($dayPcts, fn($p)=>$p >= 35));
    $plannedDays = max(1, count($dayPcts));
    $avgDay = array_sum($dayPcts)/count($dayPcts);
    $variance = array_sum(array_map(fn($p)=>($p-$avgDay)**2, $dayPcts))/count($dayPcts);
    $std = sqrt($variance);
    if ($progress <= 0) $consistency = 0;
    elseif ($type === 'daily') $consistency = $progress;
    else $consistency = report_clamp(($activeDays/$plannedDays)*55 + ($avgDay*.30) + max(0, 100-$std*2)*.15);

    $sleep = $agg['sleep_hours']; $sleepQ = $agg['sleep_quality']; $energy = $agg['energy_score']; $focus = $agg['focus_score']; $stress = $agg['stress_score'];
    $hasRecoveryData = ($sleep !== null || $sleepQ !== null || $energy !== null || $stress !== null);
    $recovery = $hasRecoveryData ? 55 : 0;
    if ($sleep !== null) $recovery += ($sleep >= 7 ? 18 : ($sleep >= 6 ? 6 : -18));
    if ($sleepQ !== null) $recovery += (($sleepQ-3)*5);
    if ($energy !== null) $recovery += (($energy-6)*4);
    if ($stress !== null) $recovery -= max(0, $stress-5)*6;
    $recovery = report_clamp($recovery);

    $hasDistractionData = (($agg['wasted_minutes'] ?? null) !== null || ($agg['phone_minutes'] ?? null) !== null);
    $waste = (float)($agg['wasted_minutes'] ?? 0) + (float)($agg['phone_minutes'] ?? 0) * .55;
    $distractionScore = $hasDistractionData ? report_clamp(100 - min(100, $waste / 2.0)) : 0;

    $subjects = $snapshot['by_subject'] ?? [];
    $weakSubjects = [];
    $strongSubjects = [];
    foreach ($subjects as $name=>$r) {
        $p = !empty($r['tasks']) ? (($r['score'] ?? 0)/max(1,$r['tasks'])*100) : 0;
        if ($p < 60 || !empty($r['missed'])) $weakSubjects[] = ['name'=>$name,'score'=>round($p),'missed'=>(int)($r['missed']??0)];
        if ($p >= 85 && ($r['tasks']??0) >= 1) $strongSubjects[] = ['name'=>$name,'score'=>round($p)];
    }
    usort($weakSubjects, fn($a,$b)=>($b['missed']<=>$a['missed']) ?: ($a['score']<=>$b['score']));
    usort($strongSubjects, fn($a,$b)=>$b['score']<=>$a['score']);
    $balance = ($progress <= 0 || empty($subjects)) ? 0 : report_clamp(100 - min(65, count($weakSubjects)*14) - ($missedRate*.45));

    $byType = $snapshot['by_type'] ?? [];
    $reviewTasks = (int)(($byType['review']['tasks'] ?? 0) + ($byType['reading']['tasks'] ?? 0));
    $studyTasks = (int)(($byType['study']['tasks'] ?? 0) + ($byType['textbook']['tasks'] ?? 0) + ($byType['custom']['tasks'] ?? 0));
    $activeRecallMix = $studyTasks > 0 ? min(100, (($byType['test']['tasks'] ?? 0) + $reviewTasks + ($byType['analysis']['tasks'] ?? 0)) / max(1,$studyTasks) * 100) : 0;
    $examLike = (int)(($byType['exam']['tasks'] ?? 0) + ($byType['mock']['tasks'] ?? 0));
    $analysisTasks = (int)($byType['analysis']['tasks'] ?? 0);
    $analysisQuality = $advanced['exam_analysis_quality'] ?? null;
    $examScore = ($examLike || $analysisTasks || $analysisQuality) ? report_clamp(($examLike ? 40 : 10) + min(35,$analysisTasks*18) + ($analysisQuality ? (((float)$analysisQuality-3)*10+25) : 0)) : 0;

    $studyQuality = ($progress <= 0) ? 0 : report_clamp(($progress*.50)+(($focus??5)*5)+(($snapshot['course_avg']??60)*.20));

    // ریسک افت عملکرد/فرسودگی: صفر بودن اجرا خودش هشدار جدی است، حتی اگر داده خواب خوب باشد.
    $burnout = 15;
    if ($progress <= 0 && $pendingRate >= 80) $burnout += 45;
    if ($progress <= 0 && $missedRate >= 50) $burnout += 65;
    if ($sleep !== null && $sleep < 6) $burnout += 20;
    if ($stress !== null && $stress >= 7) $burnout += 22;
    if ($energy !== null && $energy <= 4) $burnout += 18;
    if ($missedRate >= 25) $burnout += 20;
    if ($progress < $prevProgress - 10) $burnout += 14;
    if ($partialRate >= 35) $burnout += 8;
    $burnout = report_clamp($burnout);

    $scores = [
        'execution'=>round(report_clamp($progress)),
        'consistency'=>round($consistency),
        'tests'=>round(report_clamp($testRatio)),
        'study_quality'=>round($studyQuality),
        'recovery'=>round($recovery),
        'subject_balance'=>round($balance),
        'distraction_control'=>round($distractionScore),
        'exam_analysis'=>round($examScore),
        'active_recall_mix'=>round(report_clamp($activeRecallMix)),
        'burnout_risk'=>round($burnout),
    ];

    // امتیاز کل نباید با خواب/تعادل مصنوعی بالا برود وقتی اجرا صفر است.
    $overall = round(($scores['execution']*.42)+($scores['consistency']*.20)+($scores['tests']*.14)+($scores['study_quality']*.12)+($scores['subject_balance']*.06)+($scores['recovery']*.04)+($scores['distraction_control']*.02));
    if ($progress <= 0) $overall = min($overall, $missedRate >= 50 ? 8 : 12);
    elseif ($progress < 30) $overall = min($overall, 35);

    $alerts = [];
    if ($progress <= 0 && $pendingRate >= 80) $alerts[] = ['level'=>'danger','title'=>'هیچ اجرای مؤثری ثبت نشده','text'=>'برای این بازه تسک وجود دارد، اما هنوز اجرای کامل یا ناقص قابل قبول ثبت نشده است.'];
    if ($progress <= 0 && $missedRate >= 50) $alerts[] = ['level'=>'danger','title'=>'برنامه عملاً اجرا نشده','text'=>'بیشتر تسک‌های این بازه قرمز یا بدون امتیاز هستند و نیاز به اقدام فوری دارد.'];
    if ($burnout >= 70) $alerts[] = ['level'=>'danger','title'=>'ریسک افت جدی','text'=>'ترکیب اجرای پایین، تسک قرمز، فشار یا افت نسبت به قبل نیاز به اقدام سریع دارد.'];
    elseif ($burnout >= 45) $alerts[] = ['level'=>'warn','title'=>'ریسک افت متوسط','text'=>'بهتر است فشار برنامه، خواب و علت اجرا نشدن بررسی شود.'];
    if ($missedRate >= 25) $alerts[] = ['level'=>'danger','title'=>'تسک قرمز زیاد','text'=>'بخش قابل توجهی از برنامه اجرا نشده و باید اولویت‌بندی مجدد شود.'];
    if ($targetTests > 0 && $testsDone < $targetTests*.7) $alerts[] = ['level'=>'warn','title'=>'تست کمتر از هدف','text'=>'تعداد تست‌ها نسبت به هدف برنامه پایین‌تر است.'];
    if ($studyTasks >= 3 && $activeRecallMix < 35 && $progress > 0) $alerts[] = ['level'=>'warn','title'=>'مطالعه غیرفعال زیاد','text'=>'نسبت تست/مرور/تحلیل به مطالعه پایین است؛ برای ماندگاری باید یادآوری فعال اضافه شود.'];
    if ($hasDistractionData && $waste >= 120) $alerts[] = ['level'=>'warn','title'=>'اتلاف وقت قابل توجه','text'=>'زمان موبایل یا حاشیه روی بازده مطالعه اثر گذاشته است.'];
    if ($progress < $prevProgress - 10) $alerts[] = ['level'=>'warn','title'=>'افت نسبت به بازه قبل','text'=>'عملکرد این بازه نسبت به بازه قبل کاهش محسوسی داشته است.'];
    if (!$hasRecoveryData) $alerts[] = ['level'=>'warn','title'=>'داده خواب و انرژی ثبت نشده','text'=>'برای تحلیل دقیق‌تر، خواب، انرژی، تمرکز و استرس این بازه باید ثبت شود.'];

    $longTerm = ['label'=>null,'avg'=>null,'months'=>0];
    if ($type === 'monthly') {
        try {
            $stLt = db()->prepare("SELECT auto_snapshot_json FROM student_reports WHERE student_id=? AND report_type='monthly' AND period_start < ? ORDER BY period_start DESC LIMIT 3");
            $stLt->execute([$studentId,$start]);
            $vals = [];
            foreach ($stLt->fetchAll() as $rr) {
                $ss = $rr['auto_snapshot_json'] ? (json_decode($rr['auto_snapshot_json'], true) ?: []) : [];
                if (isset($ss['progress_percent'])) $vals[] = (float)$ss['progress_percent'];
            }
            if ($vals) {
                $avgLt = array_sum($vals)/count($vals);
                $longTerm = ['label'=>report_trend_label($progress,$avgLt),'avg'=>round($avgLt),'months'=>count($vals)];
                if ($progress < $avgLt - 12) $alerts[] = ['level'=>'danger','title'=>'افت ماهانه نسبت به روند قبلی','text'=>'عملکرد این ماه نسبت به میانگین ماه‌های اخیر کاهش محسوسی دارد.'];
                elseif ($progress > $avgLt + 12) $alerts[] = ['level'=>'warn','title'=>'رشد خوب ماهانه','text'=>'این ماه نسبت به روند ماه‌های اخیر رشد قابل توجهی دیده می‌شود؛ علت‌های موفقیت را حفظ کنید.'];
            }
        } catch (Throwable $e) { /* تحلیل بلندمدت اختیاری است */ }
    }

    $recommendations = [];
    $methodNotes = report_method_notes($type, $progress, $testRatio, $reviewTasks, $sleep, $stress);

    if ($progress <= 0) {
        if ($pendingRate >= 80) $recommendations[] = 'اولین اقدام: وضعیت واقعی تسک‌های این بازه را ثبت کن؛ اگر انجام نشده‌اند، علت اصلی را مشخص کن.';
        if ($missedRate >= 50) $recommendations[] = 'برای تسک‌های قرمز، یک برنامه جبرانی کوتاه با حداکثر ۲ تا ۳ اولویت اصلی بچینید.';
        $recommendations[] = 'حجم برنامه بعدی را موقتاً سبک‌تر و قابل اجرا کنید تا دوباره جریان اجرا شروع شود.';
        $recommendations[] = 'یک هدف کوچک فوری تعریف شود: فقط یک واحد کامل + یک بسته تست کوتاه.';
    } else {
        if ($weakSubjects) $recommendations[] = 'برای '. $weakSubjects[0]['name'] .' یک برنامه جبرانی کوتاه و پیوسته بچینید.';
        if ($targetTests > 0 && $testsDone < $targetTests) $recommendations[] = report_pick(['تعداد تست را مرحله‌ای بالا ببرید و تست‌های زمان‌دار را جداگانه ثبت کنید.','برای هر مبحث، یک بسته تست آموزشی کوتاه بعد از مطالعه و یک بسته زمان‌دار آخر هفته بگذارید.','کمبود تست را با جبران سنگین یک‌روزه حل نکنید؛ در چند روز پخش کنید تا کیفیت تحلیل حفظ شود.'], $start.'tests');
        if ($analysisTasks < $examLike) $recommendations[] = 'بعد از آزمون/آزمونک، تحلیل غلط‌ها را به سه دسته تقسیم کن: بی‌دقتی، ضعف مفهوم، ضعف زمان.';
        if ($studyTasks >= 3 && $activeRecallMix < 35) $recommendations[] = 'برای هر جلسه مطالعه، ۱۰ دقیقه بازیابی از حافظه یا چند تست بدون نگاه به درسنامه اضافه کن.';
        if ($reviewTasks <= 0 && $progress > 0) $recommendations[] = 'برای مطالب خواندنی همین بازه، مرورهای فاصله‌دار کوتاه در روزهای ۱، ۳ و ۷ ثبت شود.';
        if ($burnout >= 55) $recommendations[] = 'حجم برنامه بعدی کمی متعادل‌تر شود و خواب/استراحت جدی‌تر پیگیری شود.';
        if ($hasDistractionData && $distractionScore < 55) $recommendations[] = 'برای موبایل و حاشیه، بازه‌های بدون گوشی تعریف شود.';
    }
    if ($type === 'monthly' && !empty($longTerm['avg'])) {
        if ($progress < $longTerm['avg'] - 12) $recommendations[] = 'برای ماه بعد، هدف را روی بازگشت به میانگین قبلی بگذارید نه جهش سنگین؛ برنامه باید قابل اجرا و پایدار باشد.';
        elseif ($progress > $longTerm['avg'] + 12) $recommendations[] = 'عوامل رشد این ماه را مشخص کنید و همان الگو را در ماه بعد تکرار کنید.';
        $recommendations[] = 'در پایان هر هفته ماه بعد، یک اصلاح کوچک برنامه انجام شود تا افت ماهانه دیر تشخیص داده نشود.';
    }
    if (!$recommendations) $recommendations[] = 'مسیر کلی قابل قبول است؛ برای رشد بیشتر روی کیفیت تحلیل و استمرار تمرکز شود.';

    $summaryBits = [];
    if ($progress <= 0) {
        $summaryBits[] = 'در این بازه اجرای مؤثری ثبت نشده و وضعیت نیازمند اقدام و پیگیری فوری است.';
        if ($pendingRate >= 80) $summaryBits[] = 'بخش اصلی تسک‌های برنامه هنوز تعیین وضعیت نشده‌اند.';
        if ($missedRate >= 50) $summaryBits[] = 'تعداد تسک‌های قرمز بسیار بالاست.';
    } else {
        $summaryBits[] = 'بازدهی اجرای برنامه در این بازه «'.report_score_label($scores['execution']).'» بوده است.';
        $summaryBits[] = 'مقایسه با عملکرد بازه قبل نشان‌دهنده‌ی «'.report_trend_label($progress,$prevProgress).'» است.';
    }
    if ($type === 'monthly' && !empty($longTerm['avg'])) $summaryBits[] = 'وضعیت کلی نسبت به میانگین '.fa_num($longTerm['months']).' ماه اخیر: '.$longTerm['label'].'.';
    
    // ارزیابی هوشمند تمامی دروس ضعیف (به‌جای فقط یک درس)
    if ($weakSubjects) {
        $weakNames = array_map(fn($w) => $w['name'], array_slice($weakSubjects, 0, 5));
        $summaryBits[] = 'دروس نیازمند تمرکز، رفع اشکال و تست‌زنیِ بیشتر: ' . implode('، ', $weakNames) . '.';
    }
    if ($strongSubjects && $progress > 0) {
        $strongNames = array_map(fn($s) => $s['name'], array_slice($strongSubjects, 0, 3));
        $summaryBits[] = 'نقاط قوت و پایدار مطالعاتی: ' . implode('، ', $strongNames) . '.';
    }
    $summaryBits[] = 'ریسک فرسودگی یا افت عملکرد در حال حاضر «'.report_risk_label($burnout).'» ارزیابی می‌شود.';

    $actionPlan = [];
    if ($weakSubjects) {
        $weakNames = array_map(fn($w) => $w['name'], array_slice($weakSubjects, 0, 3));
        $actionPlan[] = 'اولویت ۱: تقویت فوری دروس ('. implode('، ', $weakNames) . ') با پارت‌های کوتاه و پیوسته + تست آموزشی';
    }
    if ($targetTests > 0 && $testsDone < $targetTests) $actionPlan[] = 'اولویت ۲: جبران کمبود تست‌ها در بسته‌های کوچک ۲۰ تا ۳۰تایی نه به‌صورت یک‌جا';
    if ($reviewTasks <= 0 && $progress > 0) $actionPlan[] = 'اولویت ۳: زمان‌بندی مرورهای فاصله‌دار برای مطالب خواندنی و فرّار';
    if ($burnout >= 55) $actionPlan[] = 'اولویت کنترل فشار: تنظیم ساعت خواب و استراحت کافی قبل از شروع پارت‌های سنگین';
    if (!$actionPlan) $actionPlan[] = 'حفظ استمرار فعلی + افزایش گام‌به‌گام تست‌های زمان‌دار و تحلیل دقیق آزمون‌ها';

    return [
        'beta'=>true,
        'overall'=>$overall,
        'overall_label'=>report_score_label($overall),
        'trend'=>report_trend_label($progress,$prevProgress),
        'prev_progress'=>round($prevProgress),
        'scores'=>$scores,
        'weak_subjects'=>array_slice($weakSubjects,0,4),
        'strong_subjects'=>array_slice($strongSubjects,0,3),
        'alerts'=>$alerts,
        'recommendations'=>array_slice(array_values(array_unique($recommendations)),0,7),
        'method_notes'=>$methodNotes,
        'action_plan'=>array_slice($actionPlan,0,4),
        'summary'=>implode(' ', $summaryBits),
    ];
}

function report_lock_past_daily_reports(int $studentId): void
{
    report_schema_ready();
    $student = get_user($studentId);
    $advisorId = (int)($student['advisor_id'] ?? 0);
    if (!$advisorId) return;

    // Check the last 5 days
    for ($i = 1; $i <= 5; $i++) {
        $date = date('Y-m-d', strtotime("-$i day"));
        [$s, $e] = report_period('daily', $date);
        
        // If there were published tasks scheduled for this student on that day
        if (report_period_has_tasks($studentId, $s, $e)) {
            $st = db()->prepare('SELECT * FROM student_reports WHERE student_id=? AND report_type="daily" AND period_start=? LIMIT 1');
            $st->execute([$studentId, $s]);
            $r = $st->fetch();
            
            if (!$r) {
                // If no report existed, we create a locked draft report
                $adv = ['advisor_notified_missed' => true];
                $json_adv = json_encode($adv, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $snap = report_auto_snapshot($studentId, $s, $e);
                $json_snap = json_encode($snap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                
                db()->prepare('INSERT INTO student_reports (student_id,report_type,period_start,period_end,auto_snapshot_json,advanced_json,status) VALUES (?, "daily", ?, ?, ?, ?, "draft")')
                    ->execute([$studentId, $s, $e, $json_snap, $json_adv]);
                    
                // Notify the advisor
                notify($advisorId, '⚠️ عدم ثبت گزارش روزانه', $student['full_name'] . ' گزارش روزانه خود را برای تاریخ ' . jalali_date($s) . ' تا پایان شب تکمیل نکرد و سیستم آن را قفل نمود.', 'chart', 'admin/student_reports.php?student=' . $studentId . '&type=daily');
            } else {
                // If a report exists but is still in draft state
                $adv = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
                if ($r['status'] === 'draft' && empty($adv['advisor_notified_missed'])) {
                    $adv['advisor_notified_missed'] = true;
                    $json_adv = json_encode($adv, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                    
                    db()->prepare('UPDATE student_reports SET advanced_json=? WHERE id=?')
                        ->execute([$json_adv, $r['id']]);
                        
                    // Notify the advisor
                    notify($advisorId, '⚠️ عدم ثبت گزارش روزانه', $student['full_name'] . ' گزارش روزانه خود را برای تاریخ ' . jalali_date($s) . ' تا پایان شب تکمیل نکرد و سیستم آن را قفل نمود.', 'chart', 'admin/student_reports.php?student=' . $studentId . '&type=daily');
                }
            }
        }
    }
}
