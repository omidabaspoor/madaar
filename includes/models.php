<?php
/** توابع داده‌ای (کوئری‌های پرکاربرد) */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/planner_settings.php';


/* ---------- سیستم سه‌حالته وضعیت تسک ---------- */
const TASK_STATUS_LABELS = [
    'pending' => 'در انتظار',
    'full'    => 'اجرای کامل',
    'partial' => 'اجرای ناقص',
    'missed'  => 'عدم اجرا',
];
const TASK_FEELINGS = [
    'great' => ['emoji'=>'😄','label'=>'عالی'],
    'good'  => ['emoji'=>'🙂','label'=>'خوب'],
    'hard'  => ['emoji'=>'😵‍💫','label'=>'سخت بود'],
    'tired' => ['emoji'=>'😴','label'=>'خسته‌کننده'],
    'bad'   => ['emoji'=>'😣','label'=>'بد/نامفهوم'],
];
function task_status_schema_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM tasks')->fetchAll() as $c) $cols[$c['Field']] = true;
        if (empty($cols['completion_status'])) db()->exec("ALTER TABLE tasks ADD COLUMN completion_status ENUM('pending','full','partial','missed') NOT NULL DEFAULT 'pending' AFTER is_done");
        if (empty($cols['course_percent'])) db()->exec("ALTER TABLE tasks ADD COLUMN course_percent TINYINT UNSIGNED DEFAULT NULL AFTER completion_status");
        if (empty($cols['student_feeling'])) db()->exec("ALTER TABLE tasks ADD COLUMN student_feeling VARCHAR(30) DEFAULT NULL AFTER course_percent");
        if (empty($cols['status_updated_at'])) db()->exec("ALTER TABLE tasks ADD COLUMN status_updated_at DATETIME DEFAULT NULL AFTER completed_at");
        // تبدیل داده‌های قدیمی: تیک‌های قبلی = کامل، بقیه = در انتظار
        db()->exec("UPDATE tasks SET completion_status=IF(is_done=1,'full','pending') WHERE completion_status IS NULL OR completion_status='' ");
        return $ready = true;
    } catch (Throwable $e) { return $ready = false; }
}
function task_status(array $t): string
{
    $s = (string)($t['completion_status'] ?? '');
    if (isset(TASK_STATUS_LABELS[$s])) return $s;
    return !empty($t['is_done']) ? 'full' : 'pending';
}

function score_display($v): string
{
    $f = (float)$v;
    if (abs($f - round($f)) < 0.001) return (string)(int)round($f);
    return rtrim(rtrim(number_format($f, 1, '.', ''), '0'), '.');
}

function task_score_sql(string $alias='t'): string
{
    // کامل = ۱ امتیاز؛ اگر تست/مقدار بیشتر از هدف زده شده باشد تا سقف ۰.۲۵ امتیاز تشویقی می‌گیرد.
    // ناقص = ۰.۵ امتیاز ثابت؛ قرمز/درانتظار = ۰
    return "CASE
        WHEN {$alias}.completion_status='full' OR ({$alias}.completion_status IS NULL AND {$alias}.is_done=1) THEN
            CASE WHEN {$alias}.target_count IS NOT NULL AND {$alias}.target_count > 0 AND {$alias}.done_count > {$alias}.target_count
                 THEN 1 + LEAST(0.25, (({$alias}.done_count - {$alias}.target_count) / {$alias}.target_count) * 0.25)
                 ELSE 1 END
        WHEN {$alias}.completion_status='partial' THEN 0.5
        ELSE 0 END";
}
function task_score(array $t): float
{
    $status = task_status($t);
    if ($status === 'partial') return 0.5;
    if ($status !== 'full') return 0.0;
    $target = isset($t['target_count']) ? (int)$t['target_count'] : 0;
    $done = isset($t['done_count']) ? (int)$t['done_count'] : 0;
    if ($target > 0 && $done > $target) return 1.0 + min(0.25, (($done - $target) / $target) * 0.25);
    return 1.0;
}
function is_feeling_task(string $type): bool
{
    return in_array($type, ['study','review','textbook','reading','analysis','custom'], true);
}
function feeling_info(?string $key): ?array
{
    return $key && isset(TASK_FEELINGS[$key]) ? TASK_FEELINGS[$key] : null;
}
/** تسک‌های روزهای گذشته که هنوز ثبت نشده‌اند، خودکار قرمز می‌شوند. */
function auto_mark_missed_tasks(?int $studentId = null): int
{
    if (!task_status_schema_ready()) return 0;
    $sql = "UPDATE tasks t JOIN plans p ON p.id=t.plan_id
            SET t.completion_status='missed', t.is_done=0, t.done_count=0, t.course_percent=0,
                t.completed_at=NULL, t.status_updated_at=NOW()
            WHERE p.status='published' AND t.completion_status='pending'
              AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) < CURDATE()";
    $params = [];
    if ($studentId !== null) { $sql .= ' AND t.student_id=?'; $params[] = $studentId; }
    $st = db()->prepare($sql); $st->execute($params);
    return $st->rowCount();
}
function task_status_badge(array $t): string
{
    $s = task_status($t);
    $label = TASK_STATUS_LABELS[$s] ?? $s;
    $icon = ['full'=>'✓','partial'=>'●','missed'=>'×','pending'=>'…'][$s] ?? '…';
    return '<span class="task-status-badge ts-'.$s.'"><b>'.$icon.'</b> '.e($label).'</span>';
}

/* ---------- دانش‌آموزان یک مشاور ---------- */
function advisor_students(int $advisorId, ?string $status = null, string $q = ''): array
{
    task_status_schema_ready();
    // خودترمیم برای نصب‌های قدیمی که upgrade_advisor_access.sql روی آن‌ها اجرا نشده است.
    try {
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $c) $cols[$c['Field']] = true;
        if (empty($cols['access_mode'])) {
            db()->exec("ALTER TABLE users ADD COLUMN access_mode ENUM('all','restricted') NOT NULL DEFAULT 'all' AFTER status");
        }
        db()->exec("CREATE TABLE IF NOT EXISTS advisor_student_access (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            advisor_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_access (advisor_id, student_id),
            KEY idx_asa_advisor (advisor_id),
            KEY idx_asa_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { /* اگر مجوز ALTER نبود، کوئری اصلی خطای قابل مشاهده می‌دهد */ }
    
    $adv = get_user($advisorId);
    $accessMode = $adv['access_mode'] ?? 'all';
    $role = $adv['role'] ?? 'advisor';
    
    $score = task_score_sql('t');
    $sql = 'SELECT u.*,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id) AS total_tasks,
            (SELECT COALESCE(SUM('.$score.'),0) FROM tasks t WHERE t.student_id=u.id) AS done_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id AND t.completion_status="full") AS full_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id AND t.completion_status="partial") AS partial_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id AND t.completion_status="missed") AS missed_tasks
            FROM users u WHERE u.role="student"';
            
    $params = [];
    
    if ($accessMode === 'restricted') {
        // If restricted, ONLY show students explicitly allocated in advisor_student_access
        $sql .= ' AND u.id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id = ?)';
        $params[] = $advisorId;
    } else {
        // If access mode is 'all'
        if ($role === 'admin') {
            // Admin (Dr Sajjad) with 'all' sees all students in the system
            $sql .= ' AND 1=1';
        } else {
            // Advisor with 'all' sees students directly assigned or in advisor_student_access
            $sql .= ' AND (u.advisor_id = ? OR u.id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id = ?))';
            $params[] = $advisorId;
            $params[] = $advisorId;
        }
    }
    
    if ($status) { $sql .= ' AND u.status=?'; $params[] = $status; }
    if ($q !== '') { $sql .= ' AND (u.full_name LIKE ? OR u.username LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    $sql .= ' ORDER BY FIELD(u.status,"pending","active","suspended"), u.created_at DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function get_user(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ---------- آمار مشاور ---------- */
function advisor_stats(int $advisorId): array
{
    $pdo = db();
    $adv = get_user($advisorId);
    $accessMode = $adv['access_mode'] ?? 'all';
    $role = $adv['role'] ?? 'advisor';

    // ساختار where برای فیلتر دانش‌آموزان قابل دسترسی
    $where = "role='student'";
    $params = [];

    if ($accessMode === 'restricted') {
        $where .= ' AND id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id = ?)';
        $params[] = $advisorId;
    } else {
        if ($role !== 'admin') {
            $where .= ' AND (advisor_id = ? OR id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id = ?))';
            $params[] = $advisorId;
            $params[] = $advisorId;
        }
    }

    // آمار با فیلتر دسترسی
    $totalSql = "SELECT COUNT(*) FROM users WHERE $where";
    $st = $pdo->prepare($totalSql);
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $activeSql = "SELECT COUNT(*) FROM users WHERE $where AND status='active'";
    $st = $pdo->prepare($activeSql);
    $st->execute($params);
    $active = (int)$st->fetchColumn();

    $pendingSql = "SELECT COUNT(*) FROM users WHERE $where AND status='pending'";
    $st = $pdo->prepare($pendingSql);
    $st->execute($params);
    $pending = (int)$st->fetchColumn();

    // تسک‌ها فقط برای دانش‌آموزان قابل دسترسی
    $studentIds = [];
    $stIds = $pdo->prepare("SELECT id FROM users WHERE $where");
    $stIds->execute($params);
    $studentIds = $stIds->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($studentIds)) {
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE student_id IN ($placeholders) AND is_done=1");
        $st->execute($studentIds);
        $tasksDone = (int)$st->fetchColumn();
        $st = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE student_id IN ($placeholders)");
        $st->execute($studentIds);
        $tasksTotal = (int)$st->fetchColumn();
    } else {
        $tasksDone = 0;
        $tasksTotal = 0;
    }
    $rate = $tasksTotal ? round($tasksDone / $tasksTotal * 100) : 0;
    return compact('total','active','pending','tasksDone','tasksTotal','rate');
}

/* ---------- برنامه‌ها ---------- */
function find_or_create_plan(int $studentId, int $advisorId, string $weekStart): array
{
    $st = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? LIMIT 1');
    $st->execute([$studentId, $weekStart]);
    $plan = $st->fetch();
    if ($plan) return $plan;
    $ins = db()->prepare('INSERT INTO plans (student_id,advisor_id,week_start,status) VALUES (?,?,?,"draft")');
    $ins->execute([$studentId, $advisorId, $weekStart]);
    return find_or_create_plan($studentId, $advisorId, $weekStart);
}

function plan_tasks(int $planId): array
{
    $st = db()->prepare('SELECT * FROM tasks WHERE plan_id=? ORDER BY day_index, unit_index, sort_order, id');
    $st->execute([$planId]);
    return $st->fetchAll();
}

/** تسک‌ها به‌صورت گرید [day][unit] = [tasks] */
function tasks_grid(int $planId): array
{
    $grid = [];
    foreach (plan_tasks($planId) as $t) {
        $grid[(int)$t['day_index']][(int)$t['unit_index']][] = $t;
    }
    return $grid;
}

function plan_progress(int $planId): array
{
    task_status_schema_ready();
    $score = task_score_sql('t');
    $st = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM($score),0) score,
        SUM(t.completion_status='full') full_count,
        SUM(t.completion_status='partial') partial_count,
        SUM(t.completion_status='missed') missed_count
        FROM tasks t WHERE t.plan_id=?");
    $st->execute([$planId]);
    $r = $st->fetch();
    $total = (int)$r['total']; $scoreVal = (float)$r['score'];
    return [
        'total'=>$total,
        'done'=>$scoreVal,
        'done_display'=>score_display($scoreVal),
        'full'=>(int)$r['full_count'], 'partial'=>(int)$r['partial_count'], 'missed'=>(int)$r['missed_count'],
        'percent'=>$total?round($scoreVal/$total*100):0
    ];
}

/* ---------- درس‌ها ---------- */
function all_subjects(): array
{
    return db()->query('SELECT * FROM subjects ORDER BY id')->fetchAll();
}

/* ---------- آمار دانش‌آموز ---------- */
function student_today_tasks(int $studentId): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $weekStart = week_saturday();
    $todayIdx = persian_day_index(date('Y-m-d'));
    $st = db()->prepare('SELECT t.*, s.color subj_color, s.name subj_name FROM tasks t
        LEFT JOIN subjects s ON s.id=t.subject_id
        LEFT JOIN plans p ON p.id=t.plan_id
        WHERE t.student_id=? AND p.week_start=? AND t.day_index=? AND p.status="published"
        ORDER BY t.unit_index, t.sort_order, t.id');
    $st->execute([$studentId, $weekStart, $todayIdx]);
    return $st->fetchAll();
}

function student_week_stats(int $studentId): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $weekStart = week_saturday();
    $score = task_score_sql('t');
    $st = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM($score),0) score,
        SUM(t.completion_status='full') full_count,
        SUM(t.completion_status='partial') partial_count,
        SUM(t.completion_status='missed') missed_count
        FROM tasks t JOIN plans p ON p.id=t.plan_id
        WHERE t.student_id=? AND p.week_start=? AND p.status=\"published\"");
    $st->execute([$studentId, $weekStart]);
    $r = $st->fetch();
    $total=(int)$r['total']; $scoreVal=(float)$r['score'];
    return [
        'total'=>$total,'done'=>$scoreVal,
        'done_display'=>score_display($scoreVal),
        'full'=>(int)$r['full_count'], 'partial'=>(int)$r['partial_count'], 'missed'=>(int)$r['missed_count'],
        'percent'=>$total?round($scoreVal/$total*100):0
    ];
}

/** نمودار ۷ روز اخیر دانش‌آموز */
function student_week_chart(int $studentId): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $out = [];
    $weekStart = week_saturday();
    $score = task_score_sql('t');
    for ($i=0; $i<7; $i++) {
        $st = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM($score),0) score,
            SUM(t.completion_status='full') full_count,
            SUM(t.completion_status='partial') partial_count,
            SUM(t.completion_status='missed') missed_count
            FROM tasks t JOIN plans p ON p.id=t.plan_id
            WHERE t.student_id=? AND p.week_start=? AND t.day_index=? AND p.status=\"published\"");
        $st->execute([$studentId, $weekStart, $i]);
        $r = $st->fetch();
        $total=(int)$r['total']; $scoreVal=(float)$r['score'];
        $out[] = ['day'=>DAY_NAMES[$i],'total'=>$total,'done'=>$scoreVal,
            'done_display'=>score_display($scoreVal),
            'full'=>(int)$r['full_count'], 'partial'=>(int)$r['partial_count'], 'missed'=>(int)$r['missed_count'],
            'pct'=>$total?round($scoreVal/$total*100):0];
    }
    return $out;
}

/** پیشرفت به تفکیک درس */
function student_subject_progress(int $studentId): array
{
    task_status_schema_ready();
    $score = task_score_sql('t');
    $st = db()->prepare("SELECT COALESCE(s.name,t.title) name, COALESCE(s.color,\"#6b8872\") color,
        COUNT(*) total, COALESCE(SUM($score),0) done
        FROM tasks t LEFT JOIN subjects s ON s.id=t.subject_id
        WHERE t.student_id=? GROUP BY COALESCE(s.id,t.title) ORDER BY total DESC LIMIT 8");
    $st->execute([$studentId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) { $r['pct'] = (int)$r['total'] ? round(((float)$r['done'])/(int)$r['total']*100) : 0; }
    return $rows;
}

/* ---------- به‌روزرسانی استریک ---------- */
function touch_streak(int $studentId): void
{
    $u = get_user($studentId);
    if (!$u) return;
    $today = date('Y-m-d');
    if ($u['last_active'] === $today) return;
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $streak = ($u['last_active'] === $yesterday) ? ((int)$u['streak'] + 1) : 1;
    $st = db()->prepare('UPDATE users SET streak=?, last_active=? WHERE id=?');
    $st->execute([$streak, $today, $studentId]);
}

/* ---------- پیام‌ها ---------- */
function conversation(int $a, int $b, int $limit = 200): array
{
    $st = db()->prepare('SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC LIMIT ?');
    $st->bindValue(1,$a,PDO::PARAM_INT); $st->bindValue(2,$b,PDO::PARAM_INT);
    $st->bindValue(3,$b,PDO::PARAM_INT); $st->bindValue(4,$a,PDO::PARAM_INT);
    $st->bindValue(5,$limit,PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function user_mood_schema_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $st = db()->query("SHOW COLUMNS FROM users LIKE 'mood_date'");
        if (!$st->fetch()) db()->exec("ALTER TABLE users ADD COLUMN mood_date DATE DEFAULT NULL AFTER mood");
        return $ready = true;
    } catch (Throwable $e) { return $ready = false; }
}

/* ======================= mood ======================= */
const MOODS = [
  'happy'    => ['emoji'=>'😄','label'=>'عالی','color'=>'#5fae7b'],
  'ok'       => ['emoji'=>'🙂','label'=>'خوب','color'=>'#8aa791'],
  'meh'      => ['emoji'=>'😐','label'=>'معمولی','color'=>'#cbac80'],
  'tired'    => ['emoji'=>'😴','label'=>'خسته','color'=>'#d9b25f'],
  'stressed' => ['emoji'=>'😣','label'=>'پراسترس','color'=>'#d97474'],
];
function mood_info(?string $key): ?array
{
    return $key && isset(MOODS[$key]) ? MOODS[$key] : null;
}
function current_mood_info(array $user): ?array
{
    user_mood_schema_ready();
    $date = (string)($user['mood_date'] ?? '');
    if ($date !== date('Y-m-d')) return null;
    return mood_info($user['mood'] ?? null);
}
function current_mood_key(array $user): ?string
{
    user_mood_schema_ready();
    return ((string)($user['mood_date'] ?? '') === date('Y-m-d')) ? ($user['mood'] ?? null) : null;
}

/* ======================= achievements ======================= */
/** همه‌ی دستاوردهای تعریف‌شده */
function all_achievements(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM achievements';
    if ($activeOnly) $sql .= ' WHERE is_active=1';
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/** دستاوردهای کسب‌شده توسط یک دانش‌آموز (آرایه‌ی achievement_id => earned_at) */
function student_earned_ids(int $studentId): array
{
    $st = db()->prepare('SELECT achievement_id, earned_at FROM student_achievements WHERE student_id=?');
    $st->execute([$studentId]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['achievement_id']] = $r['earned_at'];
    return $out;
}

/** اعطای دستاورد به دانش‌آموز (در صورت نداشتن). برمی‌گرداند true اگر جدید اعطا شد */
function award_achievement(int $studentId, int $achId, ?int $by = null): bool
{
    $chk = db()->prepare('SELECT id FROM student_achievements WHERE student_id=? AND achievement_id=?');
    $chk->execute([$studentId, $achId]);
    if ($chk->fetch()) return false;
    $ins = db()->prepare('INSERT INTO student_achievements (student_id,achievement_id,awarded_by) VALUES (?,?,?)');
    $ins->execute([$studentId, $achId, $by]);
    $a = db()->prepare('SELECT title FROM achievements WHERE id=?'); $a->execute([$achId]);
    $title = (string)$a->fetchColumn();
    notify($studentId, 'دستاورد جدید! 🏆', 'نشان «' . $title . '» را کسب کردی.', 'trophy', 'student/achievements.php');
    return true;
}

/** ارزیابی دستاوردهای خودکار برای یک دانش‌آموز و اعطای موارد واجد شرایط */
function evaluate_achievements(int $studentId): int
{
    $u = get_user($studentId);
    if (!$u) return 0;
    $st = db()->prepare('SELECT COUNT(*) total, COALESCE(SUM(is_done),0) done FROM tasks WHERE student_id=?');
    $st->execute([$studentId]);
    $done = (int)$st->fetch()['done'];
    $streak = (int)$u['streak'];
    $earned = student_earned_ids($studentId);
    $count = 0;
    foreach (all_achievements(true) as $a) {
        if (isset($earned[(int)$a['id']])) continue;
        $ok = false;
        if ($a['condition_type'] === 'tasks_done') $ok = $done >= (int)$a['threshold'];
        elseif ($a['condition_type'] === 'streak')  $ok = $streak >= (int)$a['threshold'];
        // manual => اعطای دستی، خودکار نمی‌شود
        if ($ok && award_achievement($studentId, (int)$a['id'])) $count++;
    }
    return $count;
}

/** آمار: چند دانش‌آموز هر دستاورد را گرفته‌اند */
function achievement_award_counts(): array
{
    $rows = db()->query('SELECT achievement_id, COUNT(*) c FROM student_achievements GROUP BY achievement_id')->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[(int)$r['achievement_id']] = (int)$r['c'];
    return $out;
}

/** دانش‌آموزانی که یک دستاورد خاص را گرفته‌اند */
function achievement_recipients(int $achId): array
{
    $st = db()->prepare('SELECT u.id,u.full_name,u.field,sa.earned_at,sa.awarded_by FROM student_achievements sa JOIN users u ON u.id=sa.student_id WHERE sa.achievement_id=? ORDER BY sa.earned_at DESC');
    $st->execute([$achId]);
    return $st->fetchAll();
}

/* ======================= exams ======================= */
function get_exam(int $id): ?array {
    $st = db()->prepare('SELECT * FROM exams WHERE id=? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function exam_sections(int $examId): array {
    $st = db()->prepare('SELECT * FROM exam_sections WHERE exam_id=? ORDER BY sort_order, id');
    $st->execute([$examId]);
    return $st->fetchAll();
}
function ensure_question_number_column(): void {
    static $done = null;
    if ($done !== null) return;
    try {
        db()->exec("ALTER TABLE exam_questions ADD COLUMN question_number INT UNSIGNED NULL DEFAULT NULL AFTER section_id");
        $done = true;
    } catch (Throwable $e) { $done = true; }
}

function exam_questions(int $examId): array {
    ensure_question_number_column();
    $st = db()->prepare('SELECT * FROM exam_questions WHERE exam_id=? ORDER BY section_id, COALESCE(question_number, sort_order), sort_order, id');
    $st->execute([$examId]);
    return $st->fetchAll();
}
function section_question_count(int $sectionId): int {
    $st = db()->prepare('SELECT COUNT(*) FROM exam_questions WHERE section_id=?');
    $st->execute([$sectionId]);
    return (int)$st->fetchColumn();
}
function exam_question_count(int $examId): int {
    $st = db()->prepare('SELECT COUNT(*) FROM exam_questions WHERE exam_id=?');
    $st->execute([$examId]);
    return (int)$st->fetchColumn();
}

function ensure_exam_target_schema(): void {
    try {
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM exams')->fetchAll() as $c) $cols[$c['Field']] = true;
        $adds = [];
        if (empty($cols['target_fields_json'])) $adds[] = "ADD COLUMN target_fields_json TEXT DEFAULT NULL AFTER assign_all";
        if (empty($cols['target_grades_json'])) $adds[] = "ADD COLUMN target_grades_json TEXT DEFAULT NULL AFTER target_fields_json";
        if ($adds) db()->exec('ALTER TABLE exams ' . implode(', ', $adds));
    } catch (Throwable $e) { /* old installs without ALTER permission will simply behave as all */ }
}

/** آزمون‌های قابل مشاهده برای یک دانش‌آموز (منتشرشده و در بازه) */
function student_exam_is_visible(int $examId, int $studentId): bool {
    ensure_exam_target_schema();
    $sql = "SELECT COUNT(*) FROM exams e JOIN users su ON su.id=?
        WHERE e.id=? AND e.status='published'
          AND (su.advisor_id IS NULL OR e.advisor_id=su.advisor_id OR e.advisor_id IN (SELECT id FROM users WHERE role='admin'))
          AND (e.target_fields_json IS NULL OR e.target_fields_json='' OR (su.field IS NOT NULL AND e.target_fields_json LIKE CONCAT('%\"', su.field, '\"%')))
          AND (e.target_grades_json IS NULL OR e.target_grades_json='' OR (su.grade IS NOT NULL AND e.target_grades_json LIKE CONCAT('%\"', su.grade, '\"%')))";
    $st = db()->prepare($sql);
    $st->execute([$studentId, $examId]);
    return (int)$st->fetchColumn() > 0;
}

function student_exams(int $studentId): array {
    ensure_exam_target_schema();
    $sql = "SELECT e.*,
        (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id=e.id) AS q_count,
        a.id AS attempt_id, a.status AS attempt_status, a.total_score
        FROM exams e
        JOIN users su ON su.id=?
        LEFT JOIN exam_attempts a ON a.exam_id=e.id AND a.student_id=su.id
        WHERE e.status='published'
          AND (su.advisor_id IS NULL OR e.advisor_id=su.advisor_id OR e.advisor_id IN (SELECT id FROM users WHERE role='admin'))
          AND (e.target_fields_json IS NULL OR e.target_fields_json='' OR (su.field IS NOT NULL AND e.target_fields_json LIKE CONCAT('%\"', su.field, '\"%')))
          AND (e.target_grades_json IS NULL OR e.target_grades_json='' OR (su.grade IS NOT NULL AND e.target_grades_json LIKE CONCAT('%\"', su.grade, '\"%')))
        ORDER BY e.created_at DESC";
    $st = db()->prepare($sql);
    $st->execute([$studentId]);
    return $st->fetchAll();
}

/** ساخت/گرفتن attempt یک دانش‌آموز */
function get_or_create_attempt(int $examId, int $studentId): array {
    $st = db()->prepare('SELECT * FROM exam_attempts WHERE exam_id=? AND student_id=? LIMIT 1');
    $st->execute([$examId, $studentId]);
    $a = $st->fetch();
    if ($a) return $a;
    $exam = get_exam($examId);
    $deadline = null;
    if ($exam['timing_mode'] === 'total' && $exam['duration_min']) {
        $deadline = date('Y-m-d H:i:s', time() + (int)$exam['duration_min']*60);
    } elseif ($exam['timing_mode'] === 'per_section') {
        $secs = exam_sections($examId);
        $mins = 0;
        foreach ($secs as $sec) $mins += (int)($sec['duration_min'] ?? 0);
        if ($mins <= 0) $mins = (int)($exam['duration_min'] ?? 60);
        $deadline = date('Y-m-d H:i:s', time() + max(1, $mins)*60);
    }
    $ins = db()->prepare('INSERT INTO exam_attempts (exam_id,student_id,deadline_at) VALUES (?,?,?)');
    $ins->execute([$examId, $studentId, $deadline]);
    return get_or_create_attempt($examId, $studentId);
}

/** پاسخ‌های یک attempt به‌صورت [question_id => row] */
function attempt_answers(int $attemptId): array {
    $st = db()->prepare('SELECT * FROM exam_answers WHERE attempt_id=?');
    $st->execute([$attemptId]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['question_id']] = $r;
    return $out;
}

/** محاسبه و ثبت نمره (سبک کنکور) */
function grade_attempt(int $attemptId): array {
    $att = db()->prepare('SELECT * FROM exam_attempts WHERE id=?'); $att->execute([$attemptId]); $a = $att->fetch();
    if (!$a) return [];
    $exam = get_exam((int)$a['exam_id']);
    $questions = exam_questions((int)$a['exam_id']);
    $answers = attempt_answers($attemptId);
    $neg = (int)$exam['negative_marking'];

    $secStats = []; // section_id => [correct,wrong,blank,total]
    $correct=$wrong=$blank=0;
    foreach ($questions as $q) {
        $sid = (int)$q['section_id'];
        $secStats[$sid] ??= ['correct'=>0,'wrong'=>0,'blank'=>0,'total'=>0];
        $secStats[$sid]['total']++;
        $sel = $answers[(int)$q['id']]['selected_opt'] ?? null;
        if ($sel === null || $sel === '') { $blank++; $secStats[$sid]['blank']++; }
        elseif ((int)$sel === (int)$q['correct_opt']) { $correct++; $secStats[$sid]['correct']++; }
        else { $wrong++; $secStats[$sid]['wrong']++; }
    }
    // درصد کنکوری هر بخش: (3*درست - غلط) / (3*کل) * 100
    foreach ($secStats as $sid=>&$s) {
        $den = 3 * $s['total'];
        $num = $neg ? (3*$s['correct'] - $s['wrong']) : (3*$s['correct']);
        $s['percent'] = $den>0 ? round(max(-100, $num/$den*100), 2) : 0;
    }
    unset($s);
    $totalQ = count($questions);
    $denAll = 3*$totalQ;
    $numAll = $neg ? (3*$correct - $wrong) : (3*$correct);
    $totalPct = $denAll>0 ? round(max(-100,$numAll/$denAll*100),2) : 0;

    $up = db()->prepare('UPDATE exam_attempts SET status="submitted", submitted_at=NOW(), total_score=?, correct_count=?, wrong_count=?, blank_count=? WHERE id=?');
    $up->execute([$totalPct,$correct,$wrong,$blank,$attemptId]);

    return ['total'=>$totalPct,'correct'=>$correct,'wrong'=>$wrong,'blank'=>$blank,'sections'=>$secStats];
}

/** نتایج یک آزمون برای رتبه‌بندی (مشاور) */
function exam_results(int $examId): array {
    $st = db()->prepare('SELECT a.*, u.full_name, u.field FROM exam_attempts a JOIN users u ON u.id=a.student_id WHERE a.exam_id=? AND a.status="submitted" ORDER BY a.total_score DESC');
    $st->execute([$examId]);
    return $st->fetchAll();
}
function advisor_exams(int $advisorId): array {
    ensure_exam_target_schema();
    $st = db()->prepare("SELECT e.*,
        (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id=e.id) AS q_count,
        (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id=e.id AND a.status='submitted') AS taken_count
        FROM exams e WHERE e.advisor_id=? ORDER BY e.created_at DESC");
    $st->execute([$advisorId]);
    return $st->fetchAll();
}

/** ساخت کارنامه‌ی کامل یک attempt (برای دانش‌آموز و مشاور) */
function attempt_report(int $attemptId): ?array {
    $st = db()->prepare('SELECT a.*, u.full_name FROM exam_attempts a JOIN users u ON u.id=a.student_id WHERE a.id=?');
    $st->execute([$attemptId]);
    $att = $st->fetch();
    if (!$att) return null;
    $exam = get_exam((int)$att['exam_id']);
    $sections = exam_sections((int)$att['exam_id']);
    $questions = exam_questions((int)$att['exam_id']);
    $answers = attempt_answers($attemptId);
    $neg = (int)$exam['negative_marking'];

    $secMap = []; foreach ($sections as $s) $secMap[(int)$s['id']] = $s['name'];
    $secStats = []; $byList = [];
    $gnum = 0;
    foreach ($sections as $sec) {
        foreach ($questions as $q) {
            if ((int)$q['section_id'] !== (int)$sec['id']) continue;
            $gnum++;
            $sid = (int)$sec['id'];
            $secStats[$sid] ??= ['name'=>$sec['name'],'correct'=>0,'wrong'=>0,'blank'=>0,'total'=>0];
            $secStats[$sid]['total']++;
            $sel = $answers[(int)$q['id']]['selected_opt'] ?? null;
            $sel = ($sel===null||$sel==='') ? null : (int)$sel;
            $st2 = $sel===null ? 'blank' : ((int)$sel===(int)$q['correct_opt'] ? 'correct' : 'wrong');
            $secStats[$sid][$st2]++;
            $actualNum = $q['question_number'] !== null ? (int)$q['question_number'] : $gnum;
            $byList[] = ['q'=>$q,'gnum'=>$actualNum,'sec'=>$sec['name'],'selected'=>$sel,'state'=>$st2];
        }
    }
    foreach ($secStats as &$s) {
        $den = 3*$s['total'];
        $num = $neg ? (3*$s['correct'] - $s['wrong']) : (3*$s['correct']);
        $s['percent'] = $den>0 ? round(max(-100,$num/$den*100),1) : 0;
    }
    unset($s);
    return ['attempt'=>$att,'exam'=>$exam,'sections'=>$secStats,'questions'=>$byList];
}

/* ======================= chapters ======================= */
/** آیا جدول chapters وجود دارد؟ */
function chapters_table_ready(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try { db()->query('SELECT 1 FROM chapters LIMIT 1'); return $ok = true; }
    catch (Throwable $e) { return $ok = false; }
}

/** نگاشت نام درس دیتابیس به کلید فصل‌ها */
function normalize_subject_for_chapters(string $name): ?string {
    $n = trim($name);
    // ترتیب مهم است: «فیزیک (۱) ریاضی» نباید ریاضی تشخیص داده شود،
    // و «ریاضی (۱) — به‌جای حسابان در دهم» نباید حسابان تشخیص داده شود.
    if (mb_strpos($n, 'زیست') !== false) return 'زیست‌شناسی';
    if (mb_strpos($n, 'فیزیک') !== false) return 'فیزیک';
    if (mb_strpos($n, 'شیمی') !== false) return 'شیمی';
    if (mb_strpos($n, 'گسسته') !== false) return 'ریاضیات گسسته';
    if (mb_strpos($n, 'هندسه') !== false) return 'هندسه';
    if (mb_strpos($n, 'ریاضی جامع') !== false) return 'ریاضی جامع';
    if (mb_strpos($n, 'ریاضی') === 0 || mb_strpos($n, 'ریاضی') !== false) return 'ریاضی';
    if (mb_strpos($n, 'حسابان') !== false) return 'حسابان';
    if (mb_strpos($n, 'عربی') !== false) return 'عربی، زبان قرآن';
    if (mb_strpos($n, 'دین') !== false || mb_strpos($n, 'دینی') !== false) return 'دین و زندگی';
    if (mb_strpos($n, 'ادبیات') !== false || mb_strpos($n, 'فارسی') !== false) return 'فارسی';
    if (mb_strpos($n, 'انگلیسی') !== false) return 'زبان انگلیسی';
    if (mb_strpos($n, 'هویت') !== false) return 'هویت اجتماعی';
    if (mb_strpos($n, 'سلامت') !== false) return 'سلامت و بهداشت';
    return null;
}

/** نگاشت رشته و پایه به مقادیر استاندارد */
function student_field_key(string $field): string {
    $f = trim($field);
    if ($f === 'تجربی' || $f === 'tajrobi') return 'tajrobi';
    if ($f === 'ریاضی' || $f === 'riazi') return 'riazi';
    if ($f === 'انسانی' || $f === 'omumi') return 'omumi';
    return 'omumi';
}
function student_grade_number(string $grade): int {
    $g = trim($grade);
    if ($g === 'دهم' || $g === '10') return 10;
    if ($g === 'یازدهم' || $g === '11') return 11;
    if ($g === 'دوازدهم' || $g === '12' || $g === 'کنکوری') return 12;
    if (preg_match('/(\d+)/', $g, $m)) return (int)$m[1];
    return 12;
}

/** آیا درس از عمومی‌هاست و باید مستقل از رشته دانش‌آموز از field=omumi خوانده شود؟ */
function is_common_chapter_subject(string $subjectKey): bool {
    return in_array($subjectKey, [
        'عربی، زبان قرآن', 'دین و زندگی', 'فارسی', 'زبان انگلیسی', 'هویت اجتماعی', 'سلامت و بهداشت',
        'ریاضی جامع'
    ], true);
}

/** گرفتن فصل‌ها بر اساس درس و رشته دانش‌آموز (عمومی‌ها برای همه رشته‌ها از omumi خوانده می‌شوند) */
function chapters_for_subject(string $subjectName, string $studentField): array {
    if (!chapters_table_ready()) return [];
    $key = normalize_subject_for_chapters($subjectName);
    if (!$key) return [];

    $field = is_common_chapter_subject($key) ? 'omumi' : student_field_key($studentField);

    $sql = 'SELECT * FROM chapters WHERE subject_name=? AND field=? AND is_active=1 ORDER BY grade, book_name, sort_order';
    $st = db()->prepare($sql);
    $st->execute([$key, $field]);
    $rows = $st->fetchAll();

    // اگر برای رشته اختصاصی ردیفی نبود، یک fallback به عمومی داشته باشیم تا پنل خالی نماند.
    if (!$rows && $field !== 'omumi') {
        $st->execute([$key, 'omumi']);
        $rows = $st->fetchAll();
    }

    $out = [];
    foreach ($rows as $r) {
        $out[$r['book_name']][] = $r;
    }
    return $out;
}

/** همه فصل‌ها (برای مدیریت) */
function all_chapters(?string $field = null, ?string $subjectName = null, ?int $grade = null): array {
    if (!chapters_table_ready()) return [];
    $sql = 'SELECT * FROM chapters WHERE 1=1';
    $params = [];
    if ($field) { $sql .= ' AND field=?'; $params[] = $field; }
    if ($subjectName) { $sql .= ' AND subject_name=?'; $params[] = $subjectName; }
    if ($grade) { $sql .= ' AND grade=?'; $params[] = $grade; }
    $sql .= ' ORDER BY field, grade, subject_name, sort_order';
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** افزودن/ویرایش فصل */
function save_chapter(array $data): int {
    if (!chapters_table_ready()) return 0;
    $id = (int)($data['id'] ?? 0);
    $subjectName = normalize_subject_for_chapters($data['subject_name'] ?? '') ?? trim($data['subject_name'] ?? '');
    $grade = max(10, min(12, (int)($data['grade'] ?? 12)));
    $field = student_field_key($data['field'] ?? 'omumi');
    $bookName = trim($data['book_name'] ?? '');
    $chapterName = trim($data['chapter_name'] ?? '');
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $advisorId = isset($data['advisor_id']) ? (int)$data['advisor_id'] : null;

    if ($id) {
        $st = db()->prepare('UPDATE chapters SET subject_name=?, grade=?, field=?, book_name=?, chapter_name=?, sort_order=?, is_system=0, advisor_id=?, updated_at=NOW() WHERE id=?');
        $st->execute([$subjectName, $grade, $field, $bookName, $chapterName, $sortOrder, $advisorId, $id]);
        return $id;
    } else {
        $st = db()->prepare('INSERT INTO chapters (subject_name, grade, field, book_name, chapter_name, sort_order, is_system, advisor_id) VALUES (?,?,?,?,?,?,0,?)');
        $st->execute([$subjectName, $grade, $field, $bookName, $chapterName, $sortOrder, $advisorId]);
        return (int)db()->lastInsertId();
    }
}

function delete_chapter(int $id): bool {
    if (!chapters_table_ready()) return false;
    $st = db()->prepare('DELETE FROM chapters WHERE id=?');
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

function seed_system_chapters(): int {
    require_once __DIR__ . '/chapter_data.php';
    if (!chapters_table_ready()) return 0;
    $data = chapter_seed_data();
    $count = 0;
    foreach ($data as $field => $books) {
        foreach ($books as $book) {
            $subjectKey = normalize_subject_for_chapters($book['book_name'] ?? '') ?? $book['book_name'];
            foreach ($book['chapters'] as $i => $chName) {
                $chk = db()->prepare('SELECT id FROM chapters WHERE subject_name=? AND grade=? AND field=? AND book_name=? AND chapter_name=?');
                $chk->execute([$subjectKey, $book['grade'], $field, $book['book_name'], $chName]);
                if (!$chk->fetch()) {
                    $st = db()->prepare('INSERT INTO chapters (subject_name, grade, field, book_name, chapter_name, sort_order, is_system) VALUES (?,?,?,?,?,?,1)');
                    $st->execute([$subjectKey, $book['grade'], $field, $book['book_name'], $chName, $i]);
                    $count++;
                }
            }
        }
    }
    return $count;
}
