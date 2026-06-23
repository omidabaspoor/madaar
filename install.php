<?php
/**
 * مَدار · Madar Study OS — نصب‌کننده Production
 * ------------------------------------------------
 * با یک بار زدن:
 *  ✅ دیتابیس ساخته می‌شود
 *  ✅ همه جداول ایجاد/آپدیت می‌شوند
 *  ✅ درس‌ها و فصل‌های کامل نصب می‌شوند
 *  ✅ دستاوردهای پیش‌فرض ساخته می‌شوند
 *  ✅ حساب مشاور اصلی ساخته می‌شود
 *  ❌ هیچ دانش‌آموز نمونه‌ای ساخته نمی‌شود
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

$messages = [];
$err = null;

function pdo_root(): PDO {
    return new PDO(sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

/** اجرای فایل SQL چنددستوری */
function execute_sql_file(PDO $pdo, string $path): int {
    if (!is_file($path)) return 0;
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') return 0;
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $done = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        $done++;
    }
    return $done;
}

function synchronize_database_schema(PDO $pdo, array &$messages): void {
    // 1. اجرای schema اصلی
    $schemaSql = file_get_contents(__DIR__ . '/sql/schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try { $pdo->exec($stmt); } catch (Throwable $e) {}
    }
    $messages[] = 'ساختار جداول اصلی دیتابیس ایجاد/بررسی شد.';

    // 2. اطمینان از وجود جدول chapters
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS chapters (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          subject_name VARCHAR(80) NOT NULL,
          grade INT UNSIGNED NOT NULL,
          field VARCHAR(30) NOT NULL,
          book_name VARCHAR(120) NOT NULL,
          chapter_name VARCHAR(200) NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          is_system TINYINT(1) NOT NULL DEFAULT 1,
          advisor_id INT UNSIGNED DEFAULT NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_chap_subject (subject_name, grade, field, is_active),
          KEY idx_chap_advisor (advisor_id),
          KEY idx_chap_book (book_name, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}

    // 3. همگام‌سازی ستون‌ها (Self-Healing)
    $tableColumns = [
        'plans' => [
            'published_at' => "DATETIME DEFAULT NULL AFTER status",
        ],
        'tasks' => [
            'source'            => "VARCHAR(120) DEFAULT NULL AFTER description",
            'completion_status' => "ENUM('pending','full','partial','missed') NOT NULL DEFAULT 'pending' AFTER is_done",
            'course_percent'    => "TINYINT UNSIGNED DEFAULT NULL AFTER completion_status",
            'student_feeling'   => "VARCHAR(30) DEFAULT NULL AFTER course_percent",
            'status_updated_at' => "DATETIME DEFAULT NULL AFTER completed_at",
        ],
        'users' => [
            'activated_at'    => "DATETIME DEFAULT NULL AFTER status",
            'mood'            => "VARCHAR(20) DEFAULT NULL AFTER activated_at",
            'mood_date'       => "DATE DEFAULT NULL AFTER mood",
            'streak'          => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER mood_date",
            'last_active'     => "DATE DEFAULT NULL AFTER streak",
            'remember_token'  => "VARCHAR(64) DEFAULT NULL AFTER last_active",
        ],
        'subjects' => [
            'advisor_id' => "INT UNSIGNED DEFAULT NULL AFTER id",
            'icon'       => "VARCHAR(30) DEFAULT 'book' AFTER color",
        ],
        'messages' => [
            'attachment_type' => "VARCHAR(20) NOT NULL DEFAULT 'none' AFTER body",
            'attachment_path' => "VARCHAR(255) DEFAULT NULL AFTER attachment_type",
            'attachment_name' => "VARCHAR(190) DEFAULT NULL AFTER attachment_path",
            'attachment_mime' => "VARCHAR(80) DEFAULT NULL AFTER attachment_name",
            'attachment_size' => "INT UNSIGNED DEFAULT NULL AFTER attachment_mime",
        ],
        'planner_memory' => [
            'source' => "VARCHAR(120) DEFAULT NULL AFTER priority",
        ],
        'review_reminders' => [
            'student_id'       => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER id",
            'source_task_id'   => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER student_id",
            'subject_id'       => "INT UNSIGNED DEFAULT NULL AFTER source_task_id",
            'topic_title'      => "VARCHAR(180) NOT NULL DEFAULT '' AFTER subject_id",
            'source'           => "VARCHAR(160) DEFAULT NULL AFTER topic_title",
            'first_studied_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER source",
            'interval_days'    => "INT UNSIGNED NOT NULL DEFAULT 1 AFTER first_studied_at",
            'review_no'        => "TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER interval_days",
            'profile_key'      => "VARCHAR(40) DEFAULT NULL AFTER review_no",
            'profile_label'    => "VARCHAR(80) DEFAULT NULL AFTER profile_key",
            'suggested_minutes'=> "INT UNSIGNED DEFAULT 15 AFTER profile_label",
            'due_date'         => "DATE NOT NULL DEFAULT '1970-01-01' AFTER suggested_minutes",
            'status'           => "ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending' AFTER due_date",
            'notified_at'      => "DATETIME DEFAULT NULL AFTER status",
            'completed_at'     => "DATETIME DEFAULT NULL AFTER notified_at",
            'quality'          => "ENUM('hard','good','easy') DEFAULT NULL AFTER completed_at",
        ],
        'exams' => [
            'creation_mode'    => "VARCHAR(30) NOT NULL DEFAULT 'standard' AFTER description",
            'sheet_path'       => "VARCHAR(255) DEFAULT NULL AFTER creation_mode",
            'sheet_paths_json' => "TEXT DEFAULT NULL AFTER sheet_path",
            'answer_key'       => "VARCHAR(500) DEFAULT NULL AFTER sheet_paths_json",
            'target_fields_json' => "TEXT DEFAULT NULL AFTER assign_all",
            'target_grades_json' => "TEXT DEFAULT NULL AFTER target_fields_json",
        ],
        'exam_answers' => [
            'diagnostic_reason'   => "VARCHAR(60) DEFAULT NULL AFTER flagged",
            'diagnostic_takeaway' => "VARCHAR(500) DEFAULT NULL AFTER diagnostic_reason",
        ],
        'mock_exam_reports' => [
            'total_questions' => "INT UNSIGNED DEFAULT NULL AFTER participants",
            'issues_json' => "LONGTEXT NULL AFTER analysis_json",
        ],
    ];

    foreach ($tableColumns as $table => $cols) {
        try {
            $existing = [];
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing[$row['Field']] = true;
            }
            foreach ($cols as $col => $def) {
                if (empty($existing[$col])) {
                    try { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def"); } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
    }

    // 4. آپدیت ENUM تسک
    try {
        $pdo->exec("ALTER TABLE tasks MODIFY task_type ENUM('test','study','review','textbook','descriptive','exam','reading','custom','analysis','special','mock') NOT NULL DEFAULT 'study'");
    } catch (Throwable $e) {}

    // 5. تبدیل داده‌های قدیمی
    try {
        $pdo->exec("UPDATE tasks SET completion_status=IF(is_done=1,'full','pending') WHERE completion_status IS NULL OR completion_status='' ");
    } catch (Throwable $e) {}

    $messages[] = 'همگام‌سازی و به‌روزرسانی فیلدهای پایگاه داده انجام شد.';
}

// ============================================================
//  اجرای نصب
// ============================================================
$run = ($_SERVER['REQUEST_METHOD'] === 'POST') || (PHP_SAPI === 'cli') || isset($_GET['update']);
if ($run) {
  try {
    // ۱) ساخت دیتابیس
    $root = pdo_root();
    $root->exec('CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $messages[] = 'پایگاه داده بررسی شد: ' . DB_NAME;

    $pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // ۲) همگام‌سازی ساختار
    synchronize_database_schema($pdo, $messages);

    // ۳) اجرای فایل‌های آپگرید SQL
    $upgradeFiles = [
        'sql/seed_chapters_curriculum.sql'      => 'فصل‌های درسی پایه ۱۰ تا ۱۲',
        'sql/upgrade_riazi_jame_chapters.sql'    => 'درس و فصل‌های ریاضی جامع',
        'sql/upgrade_multi_advisor_logs.sql'     => 'سیستم چندمشاوری + لاگ فعالیت',
        'sql/upgrade_advisor_access.sql'         => 'کنترل دسترسی مشاوران',
        'sql/upgrade_web_push_subscriptions.sql' => 'اعلان واقعی Web Push',
        'sql/upgrade_meeting_sms.sql'           => 'سیستم پیامک + نوع جلسه (مشاوره/کلاس)',
        'sql/upgrade_meeting_draft.sql'         => 'سیستم پیش‌نویس جلسات + تأیید نهایی',
        'sql/upgrade_online_sessions.sql'       => 'سیستم جلسات آنلاین (Whiteboard + Chat + Reactions)',
    ];
    foreach ($upgradeFiles as $file => $label) {
        $path = __DIR__ . '/' . $file;
        if (is_file($path)) {
            execute_sql_file($pdo, $path);
            $messages[] = $label . ' فعال شد.';
        }
    }

    // ۴) Seed فصل‌های درسی از PHP (fallback)
    $chapterSeedSql = __DIR__ . '/sql/seed_chapters_curriculum.sql';
    if (!is_file($chapterSeedSql)) {
        require_once __DIR__ . '/includes/models.php';
        require_once __DIR__ . '/includes/chapter_data.php';
        $seeded = seed_system_chapters();
        $messages[] = 'فصل‌های درسی PHP-seed: ' . fa_num($seeded) . ' مورد اضافه شد.';
    }

    // ۵) ساخت حساب مشاور اصلی (فقط اگر هیچ کاربری نیست)
    $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $hash = fn($p) => password_hash($p, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        // مشاور اصلی — دکتر سجاد صیادی
        $pdo->prepare('INSERT INTO users (role, full_name, username, password_hash, status, field) VALUES ("admin", ?, ?, ?, "active", "مشاور کنکور")')
            ->execute([APP_OWNER, 'sajjad_sayyadi', $hash('82437683Ss@')]);
        $advisorId = (int)$pdo->lastInsertId();
        $messages[] = 'حساب مشاور اصلی (' . APP_OWNER . ') ساخته شد.';

        // درس‌ها (بدون دانش‌آموز)
        $subjects = [
            // اختصاصی تجربی
            ['ریاضی','#6E5B9A','target'], ['شیمی','#B58A45','book'], ['فیزیک','#3F7F9F','zap'], ['زیست‌شناسی','#3B8B5B','book'],
            // اختصاصی ریاضی
            ['حسابان','#6E5B9A','target'], ['هندسه','#4F8C86','target'], ['گسسته','#8A6A52','target'],
            // عمومی‌ها
            ['هویت','#6F6F78','user'], ['سلامت','#C06C84','heart'], ['عربی','#A0754C','book'], ['دینی','#7A5AA6','heart'], ['ادبیات','#9A5A8A','book'], ['زبان انگلیسی','#5578A6','globe'],
            // ریاضی جامع
            ['ریاضی جامع','#2E5A8C','target'],
        ];
        $sIns = $pdo->prepare('INSERT INTO subjects (advisor_id, name, color, icon) VALUES (?, ?, ?, ?)');
        foreach ($subjects as $s) {
            $sIns->execute([$advisorId, $s[0], $s[1], $s[2]]);
        }
        $messages[] = fa_num(count($subjects)) . ' درس پایه نصب شد.';

        // دستاوردهای پیش‌فرض
        $achs = [
            ['شروع‌کننده','اولین تسکت را انجام دادی','rocket','tasks_done',1],
            ['استمرار','۳ روز پیاپی فعالیت','fire','streak',3],
            ['جنگجوی هفته','۷ روز استریک','fire','streak',7],
            ['نیم‌قرن','۵۰ تسک انجام‌شده','target','tasks_done',50],
            ['صدتایی','۱۰۰ تسک انجام‌شده','trophy','tasks_done',100],
            ['حرفه‌ای','۲۵۰ تسک انجام‌شده','star','tasks_done',250],
            ['وفادار','۳۰ روز استریک','heart','streak',30],
            ['منتخب مشاور','نشان ویژه از طرف مشاور','sparkles','manual',0],
        ];
        $aIns = $pdo->prepare('INSERT INTO achievements (advisor_id, title, description, icon, condition_type, threshold, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $so = 0;
        foreach ($achs as $a) {
            $aIns->execute([$advisorId, $a[0], $a[1], $a[2], $a[3], $a[4], $so++]);
        }
        $messages[] = fa_num(count($achs)) . ' دستاورد پیش‌فرض نصب شد.';

    } else {
        $messages[] = 'کاربران موجود دست‌نخورده باقی ماندند (آپدیت ساختاری فقط).';
    }

    $messages[] = '✅ نصب و همگام‌سازی با موفقیت کامل شد!';
    $messages[] = 'ورود مشاور →  نام‌کاربری: <b>sajjad_sayyadi</b>';

  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

// ============================================================
//  رابط کاربری نصب‌کننده
// ============================================================
if (PHP_SAPI === 'cli') {
    foreach ($messages as $m) echo strip_tags($m) . "\n";
    if ($err) { echo "ERROR: $err\n"; exit(1); }
    exit(0);
}

require_once __DIR__ . '/includes/layout.php';
page_head('نصب و به‌روزرسانی مَدار');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px">
  <div class="card" style="max-width:560px;width:100%">
    <div class="brand" style="justify-content:center;margin-bottom:18px"><?= logo_svg(48) ?></div>
    <h1 class="text-c" style="font-size:1.6rem;margin-bottom:8px">نصب و ارتقای <span class="gradient-text"><?= e(APP_NAME) ?></span></h1>
    <p class="text-c muted" style="margin-bottom:24px">ایجاد پایگاه داده، ساختار جداول، درس‌ها، فصل‌ها و حساب مشاور</p>
    <?php if ($err): ?>
      <div class="alert alert-error" style="margin-bottom:16px"><?= icon('info',18) ?><span><?= e($err) ?></span></div>
    <?php endif; ?>
    <?php if ($messages): ?>
      <?php foreach ($messages as $m): ?>
      <div class="alert alert-success" style="margin-bottom:10px"><?= icon('check',18) ?><span><?= $m ?></span></div>
      <?php endforeach; ?>
      <div class="alert alert-info" style="margin:16px 0"><?= icon('info',18) ?><span>برای امنیت بیشتر در محیط واقعی، فایل <b>install.php</b> را تغییرنام یا حذف کنید.</span></div>
      <a href="<?= url('auth/login.php') ?>" class="btn btn-gold btn-block btn-lg"><?= icon('rocket',18) ?> ورود به سامانه</a>
    <?php else: ?>
      <div class="alert alert-info" style="margin-bottom:16px"><?= icon('info',18) ?><span>این اسکریپت یک‌بار کل دیتابیس را می‌سازد و آماده‌ی بهره‌برداری می‌کند. بدون هیچ داده‌ی نمونه‌ای.</span></div>
      <form method="post"><button class="btn btn-gold btn-block btn-lg"><?= icon('zap',18) ?> شروع نصب</button></form>
    <?php endif; ?>
  </div>
</div>
<?php page_foot(); ?>
