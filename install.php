<?php
/**
 * مَدار · Madar Study OS — Production Installer
 * ----------------------------------------------------------------
 * یک‌بار اجرا = کل سیستم آماده می‌شود.
 *  ✅ ساخت/بروزرسانی دیتابیس، جداول، ستون‌ها
 *  ✅ درس‌ها، فصل‌ها، دستاوردهای پیش‌فرض
 *  ✅ حساب مشاور اصلی (اگر نباشد)
 *  ✅ همه‌چیز idempotent: اجرای چندباره هیچ داده‌ای را تکرار نمی‌کند
 *
 *  اطلاعات ورود مشاور اصلی (پس از نصب):
 *      نام‌کاربری: sajjad_sayyadi
 *      گذرواژه:    82437683Ss@
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

// ---------------------------------------------------------------------------
// تنظیمات مشاور اصلی — این حساب در اولین نصب ساخته می‌شود.
// اگر قبلاً با این نام‌کاربری وجود داشته باشد، گذرواژه به مقدار زیر
// به‌روزرسانی می‌شود تا بازیابی دسترسی همیشه ممکن باشد.
// ---------------------------------------------------------------------------
const ROOT_ADVISOR_USERNAME = 'sajjad_sayyadi';
const ROOT_ADVISOR_PASSWORD = '82437683Ss@';

$messages = [];
$err      = null;

// ---------------------------------------------------------------------------
// توابع کمکی
// ---------------------------------------------------------------------------
function pdo_root(): PDO {
    return new PDO(sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET),
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/**
 * اجرای فایل SQL چنددستوری، با بلوک‌بندی روی ;
 */
function execute_sql_file(PDO $pdo, string $path): array {
    if (!is_file($path)) return ['statements' => 0, 'ok' => 0, 'fail' => 0];
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') return ['statements' => 0, 'ok' => 0, 'fail' => 0];
    // حذف کامنت‌های تک‌خطی و چندخطی
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $ok = 0; $fail = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try { $pdo->exec($stmt); $ok++; }
        catch (Throwable $e) {
            // خطاهای تکراری (مثل IF NOT EXISTS) بی‌صدا رد می‌شوند ولی شمارش می‌شوند
            $fail++;
        }
    }
    return ['statements' => count($statements), 'ok' => $ok, 'fail' => $fail];
}

/** بررسی اینکه ستونی در جدول وجود دارد یا نه */
function column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($column));
        return (bool)$stmt->fetch();
    } catch (Throwable $e) { return false; }
}

/** افزودن ستون در صورت نبود (سازگار با MySQL 5.7+ و 8.0) */
function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    if (column_exists($pdo, $table, $column)) return;
    try {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    } catch (Throwable $e) {
        // ignored
    }
}

// ---------------------------------------------------------------------------
// گام ۱ — همگام‌سازی ساختار (Self-Healing)
// ---------------------------------------------------------------------------
function synchronize_database_schema(PDO $pdo, array &$messages): void {
    // ۱. اجرای schema.sql
    $r = execute_sql_file($pdo, __DIR__ . '/sql/schema.sql');
    if ($r['ok'] > 0) {
        $messages[] = "ساختار جداول اصلی: " . fa_num($r['ok']) . " دستور اجرا شد.";
    }

    // ۲. همگام‌سازی ستون‌های جداول (Self-Healing — همه چیز در یک‌جا)
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
            'activated_at'   => "DATETIME DEFAULT NULL AFTER status",
            'mood'           => "VARCHAR(20) DEFAULT NULL AFTER activated_at",
            'mood_date'      => "DATE DEFAULT NULL AFTER mood",
            'streak'         => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER mood_date",
            'last_active'    => "DATE DEFAULT NULL AFTER streak",
            'remember_token' => "VARCHAR(64) DEFAULT NULL AFTER last_active",
            'access_mode'    => "ENUM('all','restricted') NOT NULL DEFAULT 'all' AFTER status",
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
        'exams' => [
            'creation_mode'      => "VARCHAR(30) NOT NULL DEFAULT 'standard' AFTER description",
            'sheet_path'         => "VARCHAR(255) DEFAULT NULL AFTER creation_mode",
            'sheet_paths_json'   => "TEXT DEFAULT NULL AFTER sheet_path",
            'answer_key'         => "VARCHAR(500) DEFAULT NULL AFTER sheet_paths_json",
            'target_fields_json' => "TEXT DEFAULT NULL AFTER assign_all",
            'target_grades_json' => "TEXT DEFAULT NULL AFTER target_fields_json",
        ],
        'exam_answers' => [
            'diagnostic_reason'   => "VARCHAR(60) DEFAULT NULL AFTER flagged",
            'diagnostic_takeaway' => "VARCHAR(500) DEFAULT NULL AFTER diagnostic_reason",
        ],
        'exam_questions' => [
            'is_cancelled' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER question_number",
            'cancelled_at' => "DATETIME DEFAULT NULL AFTER is_cancelled",
        ],
        'mock_exam_reports' => [
            'total_questions' => "INT UNSIGNED DEFAULT NULL AFTER participants",
            'issues_json'     => "LONGTEXT NULL AFTER analysis_json",
        ],
    ];
    $added = 0;
    foreach ($tableColumns as $table => $cols) {
        foreach ($cols as $col => $def) {
            // فقط اگر ستون وجود نداشته باشد اضافه کن
            $before = column_exists($pdo, $table, $col);
            if (!$before) {
                add_column_if_missing($pdo, $table, $col, $def);
                $after = column_exists($pdo, $table, $col);
                if ($after) $added++;
            }
        }
    }
    if ($added > 0) {
        $messages[] = "ستون‌های جدید به جداول اضافه شد: " . fa_num($added) . " مورد.";
    } else {
        $messages[] = "همه‌ی ستون‌ها به‌روز و سالم هستند.";
    }

    // ۳. تبدیل ENUM نوع تسک به نسخه‌ی کامل (باید روی ستون موجود اجرا شود)
    try {
        $pdo->exec("ALTER TABLE tasks MODIFY task_type ENUM(
            'test','study','review','textbook','descriptive','exam','reading','custom','analysis','special','mock'
        ) NOT NULL DEFAULT 'study'");
    } catch (Throwable $e) {}

    // ۴. تبدیل داده‌های قدیمی برای ستون‌های جدید
    try {
        $pdo->exec("UPDATE tasks SET completion_status=IF(is_done=1,'full','pending')
                    WHERE completion_status IS NULL OR completion_status=''");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("UPDATE users SET mood_date=CURDATE() WHERE mood IS NOT NULL AND mood_date IS NULL");
    } catch (Throwable $e) {}
}

// ---------------------------------------------------------------------------
// گام ۲ — اطمینان از حساب مشاور اصلی
// (همیشه اجرا می‌شود — اگر نباشد ساخته، اگر باشد گذرواژه هم‌گام می‌شود)
// ---------------------------------------------------------------------------
function ensure_root_advisor(PDO $pdo, array &$messages): void {
    $username = ROOT_ADVISOR_USERNAME;
    $password = ROOT_ADVISOR_PASSWORD;
    $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        // ساخت مشاور اصلی
        $pdo->prepare('INSERT INTO users (role, full_name, username, password_hash, status, field, activated_at)
                       VALUES ("admin", ?, ?, ?, "active", "مشاور کنکور", NOW())')
            ->execute([APP_OWNER, $username, $hash]);
        $messages[] = 'حساب مشاور اصلی «' . APP_OWNER . '» ساخته شد.';
    } else {
        // اگر قبلاً بود ولی نقش admin نبود، ارتقا بده
        $pdo->prepare('UPDATE users SET role="admin", status="active" WHERE id=? AND role<>"admin"')
            ->execute([$existing['id']]);
        // هم‌گام‌سازی گذرواژه با مقدار قطعی (برای بازیابی)
        if (!password_verify($password, (string)$existing['password_hash'])) {
            $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
                ->execute([$hash, $existing['id']]);
            $messages[] = 'گذرواژه‌ی مشاور اصلی به‌روزرسانی شد.';
        }
    }
}

// ---------------------------------------------------------------------------
// گام ۳ — اجرای فایل‌های آپگرید (Schema + Idempotent Seeds)
// ---------------------------------------------------------------------------
function apply_upgrades(PDO $pdo, array &$messages): void {
    $files = [
        'sql/upgrade_exams.sql'    => 'سیستم آزمون (exams, attempts, questions, answers)',
        'sql/upgrade_meetings.sql' => 'جلسات مشاوره + آنلاین + پیامک',
        'sql/upgrade_misc.sql'     => 'جداول متفرقه (planner, reviews, reports, push, activity)',
        'sql/seed_curriculum.sql'  => 'درس‌ها، فصل‌های سیستم، دستاوردها',
    ];
    foreach ($files as $file => $label) {
        $path = __DIR__ . '/' . $file;
        if (!is_file($path)) continue;
        $r = execute_sql_file($pdo, $path);
        $messages[] = $label . ' → ' . fa_num($r['ok']) . ' دستور موفق';
    }
}


// ---------------------------------------------------------------------------
// گام ۳.۵ — پاک‌سازی داده‌های تکراری فصل‌ها و درس‌ها
// ---------------------------------------------------------------------------
function normalize_curriculum_duplicates(PDO $pdo, array &$messages): void {
    $removedSubjects = 0;
    $removedChapters = 0;

    try {
        // قبل از حذف درس‌های تکراری، ارجاع تسک‌ها و حافظه برنامه‌ریز به ردیف اصلی منتقل می‌شود.
        $pdo->exec("UPDATE tasks t
            JOIN subjects s1 ON s1.id=t.subject_id
            JOIN subjects s2 ON s1.id > s2.id
             AND s1.name = s2.name
             AND (s1.advisor_id <=> s2.advisor_id)
            SET t.subject_id=s2.id");
        $pdo->exec("UPDATE planner_memory pm
            JOIN subjects s1 ON s1.id=pm.subject_id
            JOIN subjects s2 ON s1.id > s2.id
             AND s1.name = s2.name
             AND (s1.advisor_id <=> s2.advisor_id)
            SET pm.subject_id=s2.id");
        // از هر درس برای هر advisor فقط یک ردیف نگه داشته می‌شود؛ ردیف قدیمی‌تر باقی می‌ماند.
        $removedSubjects = (int)$pdo->exec("DELETE s1 FROM subjects s1
            JOIN subjects s2 ON s1.id > s2.id
             AND s1.name = s2.name
             AND (s1.advisor_id <=> s2.advisor_id)");
    } catch (Throwable $e) {}

    try {
        // از هر فصل/درس برای هر ترکیب درس، پایه، رشته، کتاب و advisor فقط یک ردیف نگه داشته می‌شود.
        $removedChapters = (int)$pdo->exec("DELETE c1 FROM chapters c1
            JOIN chapters c2 ON c1.id > c2.id
             AND c1.subject_name = c2.subject_name
             AND c1.grade = c2.grade
             AND c1.field = c2.field
             AND c1.book_name = c2.book_name
             AND c1.chapter_name = c2.chapter_name
             AND (c1.advisor_id <=> c2.advisor_id)");
    } catch (Throwable $e) {}

    if ($removedSubjects || $removedChapters) {
        $messages[] = 'داده‌های تکراری برنامه درسی پاک‌سازی شد: '
            . fa_num($removedSubjects) . ' درس · '
            . fa_num($removedChapters) . ' فصل.';
    } else {
        $messages[] = 'درس‌ها و فصل‌ها بدون تکرار هستند.';
    }
}

// ---------------------------------------------------------------------------
// اجرای نصب (وقتی فرم POST شد، یا ?update=1، یا CLI)
// ---------------------------------------------------------------------------
$run = ($_SERVER['REQUEST_METHOD'] === 'POST')
    || (PHP_SAPI === 'cli')
    || isset($_GET['update']);

if ($run) {
    try {
        // ۱) ساخت/بررسی دیتابیس
        $root = pdo_root();
        $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`
                      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $messages[] = 'پایگاه داده: ' . DB_NAME . ' بررسی شد.';

        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // ۲) همگام‌سازی ساختار جداول و ستون‌ها
        synchronize_database_schema($pdo, $messages);

        // ۳) اجرای فایل‌های ارتقا و seed
        apply_upgrades($pdo, $messages);

        // ۳.۵) پاک‌سازی تکرارهای احتمالی فصل‌ها/درس‌ها
        normalize_curriculum_duplicates($pdo, $messages);

        // ۴) اطمینان از حساب مشاور اصلی (همیشه)
        ensure_root_advisor($pdo, $messages);

        // ۵) خلاصه‌ی نهایی
        $userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $subjCount = (int)$pdo->query('SELECT COUNT(*) FROM subjects WHERE advisor_id IS NULL')->fetchColumn();
        $chapCount = (int)$pdo->query('SELECT COUNT(*) FROM chapters WHERE is_system=1')->fetchColumn();

        $messages[] = '✅ نصب کامل شد. آمار: '
            . fa_num($userCount) . ' کاربر · '
            . fa_num($subjCount) . ' درس سیستمی · '
            . fa_num($chapCount) . ' فصل سیستمی';

        $messages[] = '👤 ورود مشاور →  نام‌کاربری: <b>' . ROOT_ADVISOR_USERNAME . '</b>  ·  گذرواژه: <b>' . ROOT_ADVISOR_PASSWORD . '</b>';

    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// ---------------------------------------------------------------------------
// رابط کاربری نصب‌کننده
// ---------------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    foreach ($messages as $m) echo strip_tags($m) . "\n";
    if ($err) { echo "ERROR: $err\n"; exit(1); }
    exit(0);
}

require_once __DIR__ . '/includes/layout.php';
page_head('نصب و به‌روزرسانی مَدار');
?>
<style>
.install-shell{min-height:100vh;display:grid;place-items:start center;padding:32px 16px;background:radial-gradient(circle at 20% 0%, rgba(203,172,128,.10), transparent 40%), var(--bg)}
.install-card{max-width:640px;width:100%;background:linear-gradient(160deg,var(--surface-1),var(--surface));border:1px solid rgba(255,255,255,.06);border-radius:22px;padding:32px 28px;box-shadow:0 24px 60px rgba(0,0,0,.32)}
.install-card h1{font-size:1.5rem;font-weight:1000;margin:0 0 6px;text-align:center}
.install-card .lead{text-align:center;color:var(--text-2);font-size:.92rem;margin:0 0 24px}
.install-steps{display:flex;flex-direction:column;gap:10px;margin:18px 0}
.install-step{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-soft);font-size:.92rem}
.install-step .dot{width:22px;height:22px;border-radius:50%;background:var(--gold);color:#171006;display:grid;place-items:center;font-weight:1000;font-size:.74rem;flex-shrink:0}
.install-step .step-text{flex:1}
.install-step .step-tag{font-size:.7rem;background:rgba(203,172,128,.18);color:var(--gold-light);padding:2px 8px;border-radius:99px;font-weight:900}
.install-cred{margin-top:14px;padding:14px 16px;border-radius:14px;background:linear-gradient(135deg,rgba(95,174,123,.14),rgba(107,136,114,.06));border:1px solid rgba(95,174,123,.30)}
.install-cred b{color:#85d89f;font-family:'Vazirmatn',monospace}
</style>
<div class="install-shell">
  <div class="install-card">
    <div style="display:flex;justify-content:center;margin-bottom:14px"><?= logo_svg(56) ?></div>
    <h1>نصب و ارتقای <span class="gradient-text"><?= e(APP_NAME) ?></span></h1>
    <p class="lead">ساخت دیتابیس، جداول، درس‌ها، فصل‌ها، دستاوردها و حساب مشاور اصلی. <b>همه‌ی مراحل idempotent هستند</b> — اجرای چندباره هیچ داده‌ای را تکرار نمی‌کند.</p>

    <?php if ($err): ?>
      <div class="alert alert-error" style="margin-bottom:16px"><?= icon('alert-circle',18) ?><span><?= e($err) ?></span></div>
    <?php endif; ?>

    <?php if ($messages): ?>
      <div class="install-steps">
        <?php foreach ($messages as $m): ?>
          <div class="install-step">
            <span class="dot">✓</span>
            <div class="step-text"><?= $m ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="install-cred">
        <div style="font-size:.86rem;color:var(--text-2);line-height:1.7">
          🔐 اطلاعات ورود مشاور اصلی:
          <br>نام‌کاربری: <b><?= e(ROOT_ADVISOR_USERNAME) ?></b>
          <br>گذرواژه: <b><?= e(ROOT_ADVISOR_PASSWORD) ?></b>
        </div>
      </div>
      <div class="alert alert-info" style="margin:18px 0 14px"><?= icon('shield',18) ?><span>برای امنیت بیشتر در محیط واقعی، فایل <b>install.php</b> را تغییرنام یا حذف کنید.</span></div>
      <a href="<?= url('auth/login.php') ?>" class="btn btn-gold btn-block btn-lg" style="margin-top:6px"><?= icon('rocket',18) ?> ورود به سامانه</a>
    <?php else: ?>
      <div class="install-steps" style="opacity:.82">
        <div class="install-step"><span class="dot">۱</span><div class="step-text">بررسی/ساخت دیتابیس <b><?= e(DB_NAME) ?></b></div></div>
        <div class="install-step"><span class="dot">۲</span><div class="step-text">همگام‌سازی ساختار جداول + ستون‌های جدید</div></div>
        <div class="install-step"><span class="dot">۳</span><div class="step-text">اجرای فایل‌های ارتقا (آزمون، جلسات، سایر)</div></div>
        <div class="install-step"><span class="dot">۴</span><div class="step-text">افزودن درس‌ها، فصل‌ها و دستاوردهای پیش‌فرض</div></div>
        <div class="install-step"><span class="dot">۵</span><div class="step-text">ساخت/به‌روزرسانی حساب مشاور اصلی</div></div>
      </div>
      <div class="install-cred" style="background:linear-gradient(135deg,rgba(111,155,192,.12),rgba(107,136,114,.05));border-color:rgba(111,155,192,.30)">
        <div style="font-size:.86rem;color:var(--text-2);line-height:1.7">
          ℹ️ پس از نصب، مشاور اصلی با این اطلاعات وارد می‌شود:
          <br>نام‌کاربری: <b style="color:#a0d2eb"><?= e(ROOT_ADVISOR_USERNAME) ?></b>
          <br>گذرواژه: <b style="color:#a0d2eb"><?= e(ROOT_ADVISOR_PASSWORD) ?></b>
        </div>
      </div>
      <form method="post" style="margin-top:18px">
        <button class="btn btn-gold btn-block btn-lg"><?= icon('zap',18) ?> شروع نصب / به‌روزرسانی</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php page_foot(); ?>
