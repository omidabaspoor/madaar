<?php
/** مرورهای فاصله‌دار برای جلوگیری از فراموشی */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';

const REVIEW_INTERVALS = [1, 3, 7, 14, 30, 60];

function review_norm(string $s): string
{
    $s = str_replace(['ي','ك','‌'], ['ی','ک',' '], trim($s));
    return preg_replace('/\s+/u', ' ', $s) ?: '';
}
function review_profile_for_subject(?string $subjectName, string $taskType='study'): array
{
    $n = review_norm((string)$subjectName);

    // ۱. دروس محاسباتی، تمرینی و تحلیلی (فیزیک، ریاضی، حسابان، هندسه، گسسته):
    // دانش‌آموز کنکوری در این دروس نیازی به مرور حفظیِ فردای آن روز ندارد؛ بلکه باید چند روز بعد با تست آموزشی و زمان‌دار سنجیده شود.
    foreach (['ریاضی','حسابان','هندسه','گسسته','فیزیک'] as $k) {
        if ($n !== '' && mb_strpos($n, $k) !== false) {
            return [
                'key'      => 'problem_solving',
                'label'    => 'تمرینی / محاسباتی کنکور',
                'intervals'=> [3, 10, 25, 60], // فواصل بازتر و منطقی برای حل تست
                'minutes'  => 20
            ];
        }
    }

    // ۲. دروس به شدت فرّار و پرنکته (زیست‌شناسی، شیمی):
    // نیازمند مرور سریع تصاویر/جداول بعد از ۲ روز، سپس بازیابی فاصله‌دار.
    if ($n !== '' && (mb_strpos($n, 'زیست') !== false || mb_strpos($n, 'شیمی') !== false)) {
        return [
            'key'      => 'volatile_science',
            'label'    => 'علوم فرّار / تحلیلی',
            'intervals'=> [2, 7, 16, 35, 75],
            'minutes'  => 15
        ];
    }

    // ۳. دروس متنی و حفظیات ناب (دینی، ادبیات، عربی، زبان، لغت):
    // بازیابی استاندارد کنکوری.
    foreach (['دینی','دین','ادبیات','عربی','زبان','لغت'] as $k) {
        if ($n !== '' && mb_strpos($n, $k) !== false) {
            return [
                'key'      => 'konkur_memory',
                'label'    => 'حفظی کنکوری',
                'intervals'=> [1, 5, 14, 30, 60],
                'minutes'  => 12
            ];
        }
    }

    // ۴. دروس عمومی سبک (هویت اجتماعی، سلامت و بهداشت):
    // فواصل کاملاً باز تا وقت دروس اصلی را نگیرد.
    foreach (['هویت','سلامت'] as $k) {
        if ($n !== '' && mb_strpos($n, $k) !== false) {
            return [
                'key'      => 'light_memory',
                'label'    => 'عمومی سبک',
                'intervals'=> [7, 21, 45],
                'minutes'  => 10
            ];
        }
    }

    // پیش‌فرض استاندارد برای سایر موارد
    return [
        'key'      => 'standard',
        'label'    => 'مرور استاندارد',
        'intervals'=> [2, 8, 20, 45],
        'minutes'  => 12
    ];
}
function review_profile_for_task(array $t): array
{
    $subject = $t['subj_name'] ?? '';
    if (!$subject && !empty($t['subject_id'])) {
        try { $q=db()->prepare('SELECT name FROM subjects WHERE id=?'); $q->execute([(int)$t['subject_id']]); $subject=(string)$q->fetchColumn(); } catch(Throwable $e) {}
    }
    return review_profile_for_subject($subject, (string)($t['task_type'] ?? 'study'));
}

function review_schema_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS review_reminders (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          student_id INT UNSIGNED NOT NULL,
          source_task_id INT UNSIGNED NOT NULL,
          subject_id INT UNSIGNED DEFAULT NULL,
          topic_title VARCHAR(180) NOT NULL,
          source VARCHAR(160) DEFAULT NULL,
          first_studied_at DATETIME NOT NULL,
          interval_days INT UNSIGNED NOT NULL,
          review_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
          profile_key VARCHAR(40) DEFAULT NULL,
          profile_label VARCHAR(80) DEFAULT NULL,
          suggested_minutes INT UNSIGNED DEFAULT 15,
          due_date DATE NOT NULL,
          status ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending',
          notified_at DATETIME DEFAULT NULL,
          completed_at DATETIME DEFAULT NULL,
          quality ENUM('hard','good','easy') DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_review_step (source_task_id, interval_days),
          KEY idx_review_student_due (student_id, status, due_date),
          KEY idx_review_source (source_task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ترمیم کامل برای نصب‌هایی که migration قدیمی/نیمه‌کاره اجرا شده و جدول ناقص مانده است.
        $cols=[]; foreach(db()->query('SHOW COLUMNS FROM review_reminders')->fetchAll() as $c) $cols[$c['Field']]=true;
        $adds=[
          'student_id'=>'ALTER TABLE review_reminders ADD COLUMN student_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id',
          'source_task_id'=>'ALTER TABLE review_reminders ADD COLUMN source_task_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER student_id',
          'subject_id'=>'ALTER TABLE review_reminders ADD COLUMN subject_id INT UNSIGNED DEFAULT NULL AFTER source_task_id',
          'topic_title'=>"ALTER TABLE review_reminders ADD COLUMN topic_title VARCHAR(180) NOT NULL DEFAULT '' AFTER subject_id",
          'source'=>'ALTER TABLE review_reminders ADD COLUMN source VARCHAR(160) DEFAULT NULL AFTER topic_title',
          'first_studied_at'=>'ALTER TABLE review_reminders ADD COLUMN first_studied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER source',
          'interval_days'=>'ALTER TABLE review_reminders ADD COLUMN interval_days INT UNSIGNED NOT NULL DEFAULT 1 AFTER first_studied_at',
          'review_no'=>'ALTER TABLE review_reminders ADD COLUMN review_no TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER interval_days',
          'profile_key'=>'ALTER TABLE review_reminders ADD COLUMN profile_key VARCHAR(40) DEFAULT NULL AFTER review_no',
          'profile_label'=>'ALTER TABLE review_reminders ADD COLUMN profile_label VARCHAR(80) DEFAULT NULL AFTER profile_key',
          'suggested_minutes'=>'ALTER TABLE review_reminders ADD COLUMN suggested_minutes INT UNSIGNED DEFAULT 15 AFTER profile_label',
          'due_date'=>"ALTER TABLE review_reminders ADD COLUMN due_date DATE NOT NULL DEFAULT '1970-01-01' AFTER suggested_minutes",
          'status'=>"ALTER TABLE review_reminders ADD COLUMN status ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending' AFTER due_date",
          'notified_at'=>'ALTER TABLE review_reminders ADD COLUMN notified_at DATETIME DEFAULT NULL AFTER status',
          'completed_at'=>'ALTER TABLE review_reminders ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER notified_at',
          'quality'=>"ALTER TABLE review_reminders ADD COLUMN quality ENUM('hard','good','easy') DEFAULT NULL AFTER completed_at",
          'created_at'=>'ALTER TABLE review_reminders ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER quality',
          'updated_at'=>'ALTER TABLE review_reminders ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
        ];
        foreach($adds as $col=>$sql) {
            if(empty($cols[$col])) {
                try { db()->exec($sql); } catch (Throwable $e) {}
            }
        }

        // ایندکس‌ها اگر از migration قبلی جا مانده باشند.
        $idx=[]; foreach(db()->query('SHOW INDEX FROM review_reminders')->fetchAll() as $i) $idx[$i['Key_name']]=true;
        if (empty($idx['uq_review_step'])) {
            try { db()->exec('ALTER TABLE review_reminders ADD UNIQUE KEY uq_review_step (source_task_id, interval_days)'); } catch (Throwable $e) {}
        }
        if (empty($idx['idx_review_student_due'])) {
            try { db()->exec('ALTER TABLE review_reminders ADD KEY idx_review_student_due (student_id, status, due_date)'); } catch (Throwable $e) {}
        }
        if (empty($idx['idx_review_source'])) {
            try { db()->exec('ALTER TABLE review_reminders ADD KEY idx_review_source (source_task_id)'); } catch (Throwable $e) {}
        }
        return $ok = true;
    } catch (Throwable $e) { error_log('Madar review schema error: '.$e->getMessage()); return $ok = false; }
}

function review_is_eligible_task(array $t): bool
{
    $type = (string)($t['task_type'] ?? '');
    if (!in_array($type, ['study','textbook','reading','custom'], true)) return false;
    $title = trim((string)($t['title'] ?? ''));
    if ($title === '') return false;
    
    // تسک‌های تستی/آزمونی و روتین‌های عمومی/ویژه را کاملاً فیلتر کن تا اسپم نشود
    $bad = ['تست','آزمون','آزمونک','تحلیل','روزخوانی','مرور ویژه','غلط‌نامه','special','mock'];
    foreach ($bad as $b) {
        if (mb_strpos($title, $b) !== false) return false;
    }
    if ((int)($t['unit_index'] ?? 0) === 8) return false; // واحد ویژه کلاً استثناست و نباید وارد سیستم مرور شود
    return true;
}

function review_create_for_task(int $taskId): int
{
    if (!review_schema_ready()) return 0;
    $st = db()->prepare('SELECT t.*, p.status plan_status, p.advisor_id, s.name subj_name FROM tasks t JOIN plans p ON p.id=t.plan_id LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.id=? LIMIT 1');
    $st->execute([$taskId]);
    $t = $st->fetch();
    if (!$t || !review_is_eligible_task($t)) return 0;
    $advisorId = (int)($t['advisor_id'] ?? 0);
    if ($advisorId && function_exists('advisor_feature_enabled') && !advisor_feature_enabled($advisorId, 'review_enabled')) return 0;
    $status = task_status($t);
    if (!in_array($status, ['full','partial'], true)) return 0;

    // مکانیزم Debounce / De-duplication: جلوگیری از ثبت زنجیره‌ی مرور تکراری برای یک مبحث در بازه‌ی ۴ روزه
    $dupCheck = db()->prepare('SELECT id FROM review_reminders WHERE student_id=? AND topic_title=? AND status="pending" AND first_studied_at >= DATE_SUB(NOW(), INTERVAL 4 DAY) LIMIT 1');
    $dupCheck->execute([(int)$t['student_id'], trim((string)$t['title'])]);
    if ($dupCheck->fetch()) {
        return 0; // قبلاً برای این مبحث در همین چند روز اخیر زنجیره‌ی مرور ساخته شده است
    }

    $first = $t['completed_at'] ?: date('Y-m-d H:i:s');
    $baseDate = date('Y-m-d', strtotime($first));
    $profile = review_profile_for_task($t);
    
    // هوشمندسازی پیشرفته بر اساس حس و حال دانش‌آموز و جزئیات اجرا (Granular Task Feeling Awareness)
    $feeling = (string)($t['student_feeling'] ?? '');
    if ($feeling === 'hard' || $feeling === 'bad' || $status === 'partial') {
        // مبحث چالش‌برانگیز یا ناقص بوده؛ نیازمند مرور نجات فوری در روز ۱، سپس فواصل فشرده‌تر
        $profile['intervals'] = [1, 4, 10, 20, 40];
        $profile['label']     = 'مرور نجات (' . ($status==='partial'?'اجرای ناقص':'مبحث سخت') . ')';
        $profile['minutes']   = (int)round($profile['minutes'] * 1.5);
    } elseif ($feeling === 'tired') {
        // مطالعه همراه با خستگی؛ نیازمند بازیابی تازه
        $profile['intervals'] = [2, 8, 18, 35];
        $profile['label']     = e($profile['label']) . ' (بازیابی خستگی)';
    } elseif ($feeling === 'great' || $feeling === 'great') {
        // تسلط عالی؛ فواصل بازتر و زمان بهینه‌تر
        $profile['intervals'] = array_map(fn($d)=>(int)round($d*1.2), $profile['intervals']);
        $profile['label']     = e($profile['label']) . ' (تسلط کامل)';
        $profile['minutes']   = max(10, (int)round($profile['minutes'] * 0.85));
    }

    $exists = db()->prepare('SELECT id FROM review_reminders WHERE source_task_id=? AND interval_days=? LIMIT 1');
    $ins = db()->prepare('INSERT IGNORE INTO review_reminders (student_id,source_task_id,subject_id,topic_title,source,first_studied_at,interval_days,review_no,profile_key,profile_label,suggested_minutes,due_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $n = 0; $i = 1;
    foreach ($profile['intervals'] as $days) {
        $exists->execute([(int)$t['id'], $days]);
        if ($exists->fetch()) { $i++; continue; }
        $due = date('Y-m-d', strtotime($baseDate.' +'.$days.' day'));
        $ins->execute([(int)$t['student_id'], (int)$t['id'], $t['subject_id'] ?: null, (string)$t['title'], $t['source'] ?? null, $first, $days, $i++, $profile['key'], $profile['label'], (int)$profile['minutes'], $due]);
        $n += $ins->rowCount();
    }
    return $n;
}

function review_due_notifications(int $studentId): int
{
    $stu = get_user($studentId);
    $advisorId = (int)($stu['advisor_id'] ?? 0);
    if ($advisorId && function_exists('advisor_feature_enabled') && !advisor_feature_enabled($advisorId, 'review_enabled')) return 0;
    if (!review_schema_ready()) return 0;
    $st = db()->prepare("SELECT * FROM review_reminders WHERE student_id=? AND status='pending' AND due_date<=CURDATE() AND notified_at IS NULL ORDER BY due_date, interval_days LIMIT 20");
    $st->execute([$studentId]);
    $rows = $st->fetchAll();
    $up = db()->prepare('UPDATE review_reminders SET notified_at=NOW() WHERE id=?');
    $n = 0;
    foreach ($rows as $r) {
        $days = (int)$r['interval_days'];
        $msg = $days===1 ? 'یک روز از مطالعه این مبحث گذشته؛ مرور کوتاه کمک می‌کند تثبیت شود.' : ($days===7 ? 'یک هفته از مطالعه این مبحث گذشته؛ وقت مرور فاصله‌دار است.' : ($days===10 ? 'ده روز از مطالعه این مبحث گذشته؛ مرور الان جلوی فراموشی را می‌گیرد.' : 'سی روز از مطالعه این مبحث گذشته؛ مرور جمع‌بندی انجام بده.'));
        notify($studentId, 'وقت مرور «'.$r['topic_title'].'» 🔁', $msg, 'review', 'student/reviews.php');
        $up->execute([$r['id']]);
        $n++;
    }
    return $n;
}

function review_counts(int $studentId): array
{
    if (!review_schema_ready()) return ['due'=>0,'upcoming'=>0,'done'=>0];
    $st = db()->prepare("SELECT COUNT(*) c FROM review_reminders WHERE student_id=?");
    $st->execute([$studentId]);
    if ((int)$st->fetchColumn() === 0) {
        review_backfill_for_student($studentId);
    }

    $st = db()->prepare("SELECT
      SUM(status='pending' AND due_date<=CURDATE()) due_count,
      SUM(status='pending' AND due_date>CURDATE()) upcoming_count,
      SUM(status='done') done_count
      FROM review_reminders WHERE student_id=?");
    $st->execute([$studentId]);
    $r = $st->fetch() ?: [];
    return ['due'=>(int)($r['due_count']??0),'upcoming'=>(int)($r['upcoming_count']??0),'done'=>(int)($r['done_count']??0)];
}

function review_items(int $studentId, string $scope='due'): array
{
    if (!review_schema_ready()) return [];
    $st = db()->prepare("SELECT COUNT(*) c FROM review_reminders WHERE student_id=?");
    $st->execute([$studentId]);
    if ((int)$st->fetchColumn() === 0) {
        review_backfill_for_student($studentId);
    }

    if ($scope === 'upcoming') $where = "status='pending' AND due_date>CURDATE()";
    elseif ($scope === 'done') $where = "status='done'";
    else $where = "status='pending' AND due_date<=CURDATE()";
    $st = db()->prepare("SELECT rr.*, s.name subject_name, s.color subject_color FROM review_reminders rr LEFT JOIN subjects s ON s.id=rr.subject_id WHERE rr.student_id=? AND $where ORDER BY rr.due_date ASC, rr.interval_days ASC LIMIT 80");
    $st->execute([$studentId]);
    return $st->fetchAll();
}


function review_complete_item(int $reviewId, int $studentId, string $quality='good'): bool
{
    if (!review_schema_ready()) return false;
    $quality = in_array($quality, ['hard','good','easy'], true) ? $quality : 'good';
    $st = db()->prepare('SELECT * FROM review_reminders WHERE id=? AND student_id=? LIMIT 1');
    $st->execute([$reviewId,$studentId]);
    $r = $st->fetch();
    if (!$r) return false;
    db()->prepare("UPDATE review_reminders SET status='done', completed_at=NOW(), quality=? WHERE id=?")->execute([$quality,$reviewId]);
    // اگر مرور سخت بود، یک یادآور تقویتی کوتاه برای فردا بساز.
    if ($quality === 'hard') {
        $due = date('Y-m-d', strtotime('+1 day'));
        $chk = db()->prepare('SELECT id FROM review_reminders WHERE source_task_id=? AND interval_days=0 AND status="pending" LIMIT 1');
        $chk->execute([(int)$r['source_task_id']]);
        if (!$chk->fetch()) {
            $ins = db()->prepare('INSERT IGNORE INTO review_reminders (student_id,source_task_id,subject_id,topic_title,source,first_studied_at,interval_days,review_no,profile_key,profile_label,suggested_minutes,due_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([$studentId,(int)$r['source_task_id'],$r['subject_id'] ?: null,$r['topic_title'],$r['source'] ?? null,$r['first_studied_at'],0,99,$r['profile_key'] ?: 'reinforce','مرور تقویتی',10,$due]);
        }
    }
    return true;
}

function review_backfill_for_student(int $studentId): int
{
    if (!review_schema_ready()) return 0;
    $st = db()->prepare("SELECT t.id FROM tasks t JOIN plans p ON p.id=t.plan_id
        WHERE t.student_id=? AND p.status='published'
          AND (t.completion_status IN ('full','partial') OR t.is_done=1)
        ORDER BY t.completed_at DESC, t.id DESC LIMIT 250");
    $st->execute([$studentId]);
    $n = 0;
    foreach ($st->fetchAll() as $r) $n += review_create_for_task((int)$r['id']);
    return $n;
}

function review_items_for_advisor(int $studentId, string $scope='due'): array
{
    return review_items($studentId, $scope);
}
