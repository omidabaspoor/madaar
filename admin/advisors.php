<?php
/**
 * مَدار · Madar Study OS — Professional Advisor Management Panel
 * -------------------------------------------------------------------
 * اختصاصی دکتر سجاد صیادی (مشاور ارشد / مالک سامانه)
 * طراحی بر اساس سیستم طراحی تاریک و لوکس مَدار (Native Madar Design)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/log.php';

boot_session();
require_chief_advisor(); // فقط سجاد صیادی (مشاور ارشد) دسترسی دارد

$u = current_user();
$adminId = (int)$u['id'];

$action = $_POST['action'] ?? '';
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // 1. ایجاد مشاور جدید
    if ($action === 'create_advisor') {
        $full_name   = trim($_POST['full_name'] ?? '');
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $email       = trim($_POST['email'] ?? '') ?: null;
        $phone       = trim($_POST['phone'] ?? '') ?: null;
        $field       = trim($_POST['field'] ?? 'مشاور تحصیلی');
        $access_mode = in_array($_POST['access_mode'] ?? '', ['all','restricted']) ? $_POST['access_mode'] : 'all';

        if (!$full_name || !$username || !$password) {
            $err = 'پر کردن فیلدهای نام، نام کاربری و گذرواژه الزامی است.';
        } elseif (mb_strlen($password) < 6) {
            $err = 'گذرواژه باید حداقل ۶ کاراکتر باشد.';
        } else {
            $chk = db()->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                $err = 'این نام کاربری قبلاً در سامانه ثبت شده است.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $st = db()->prepare("
                    INSERT INTO users (role, full_name, username, password_hash, email, phone, field, status, access_mode) 
                    VALUES ('advisor', ?, ?, ?, ?, ?, ?, 'active', ?)
                ");
                $st->execute([$full_name, $username, $hash, $email, $phone, $field, $access_mode]);
                $newId = (int)db()->lastInsertId();
                log_activity($adminId, 'advisor_created', 'user', $newId, [
                    'full_name' => $full_name, 'username' => $username, 'field' => $field, 'mode' => $access_mode === 'all' ? 'کامل (کل سیستم)' : 'محدود (اختصاصی)'
                ]);
                $msg = 'مشاور جدید با موفقیت به سامانه افزوده شد.';
            }
        }
    }

    // 2. ویرایش مشخصات مشاور
    if ($action === 'edit_advisor') {
        $id          = (int)($_POST['id'] ?? 0);
        $full_name   = trim($_POST['full_name'] ?? '');
        $username    = trim($_POST['username'] ?? '');
        $password    = $_POST['password'] ?? '';
        $email       = trim($_POST['email'] ?? '') ?: null;
        $phone       = trim($_POST['phone'] ?? '') ?: null;
        $field       = trim($_POST['field'] ?? 'مشاور تحصیلی');
        $access_mode = in_array($_POST['access_mode'] ?? '', ['all','restricted']) ? $_POST['access_mode'] : 'all';

        if ($id && $full_name && $username) {
            $chk = db()->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$username, $id]);
            if ($chk->fetch()) {
                $err = 'نام کاربری وارد شده توسط کاربر دیگری استفاده می‌شود.';
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                    $st = db()->prepare("
                    UPDATE users 
                    SET full_name=?, username=?, password_hash=?, email=?, phone=?, field=?, access_mode=? 
                    WHERE id=? AND role IN ('admin', 'advisor')
                ");
                $st->execute([$full_name, $username, $hash, $email, $phone, $field, $access_mode, $id]);
            } else {
                $st = db()->prepare("
                    UPDATE users 
                    SET full_name=?, username=?, email=?, phone=?, field=?, access_mode=? 
                    WHERE id=? AND role IN ('admin', 'advisor')
                ");
                $st->execute([$full_name, $username, $email, $phone, $field, $access_mode, $id]);
            }
                log_activity($adminId, 'ویرایش مشخصات مشاور', 'user', $id, [
                    'full_name' => $full_name, 'username' => $username, 'field' => $field, 'mode' => $access_mode === 'all' ? 'کامل' : 'محدود'
                ]);
                $msg = 'مشخصات مشاور با موفقیت به‌روزرسانی شد.';
            }
        } else {
            $err = 'اطلاعات ارسالی نامعتبر است.';
        }
    }

    // 3. تغییر وضعیت (فعال / مسدود)
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        if ($id && $id !== $adminId && in_array($status, ['active','suspended'])) {
            db()->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'advisor'")->execute([$status, $id]);
            log_activity($adminId, $status === 'active' ? 'فعال‌سازی حساب مشاور' : 'مسدودسازی حساب مشاور', 'user', $id, ['وضعیت' => $status === 'active' ? 'فعال' : 'مسدود']);
            $msg = 'وضعیت حساب مشاور به‌روزرسانی شد.';
        }
    }

    // 4. تغییر نوع دسترسی (کامل / محدود)
    if ($action === 'toggle_access') {
        $id = (int)($_POST['id'] ?? 0);
        $mode = $_POST['mode'] ?? 'all';
        if ($id && in_array($mode, ['all','restricted'])) {
            db()->prepare("UPDATE users SET access_mode = ? WHERE id = ? AND role IN ('admin', 'advisor')")->execute([$mode, $id]);
            log_activity($adminId, 'تغییر سطح دسترسی مشاور', 'user', $id, ['سطح دسترسی' => $mode === 'all' ? 'کامل (کل سامانه)' : 'محدود (دانش‌آموزان اختصاصی)']);
            $msg = 'سطح دسترسی مشاور به‌روزرسانی شد.';
        }
    }

    // 5. تخصیص دانش‌آموزان اختصاصی
    if ($action === 'assign_students') {
        $advisorId = (int)($_POST['advisor_id'] ?? 0);
        $studentIds = $_POST['student_ids'] ?? [];
        if (!is_array($studentIds)) $studentIds = [];

        if ($advisorId) {
            db()->beginTransaction();
            try {
                db()->prepare("DELETE FROM advisor_student_access WHERE advisor_id = ?")->execute([$advisorId]);
                if (!empty($studentIds)) {
                    $stIns = db()->prepare("INSERT INTO advisor_student_access (advisor_id, student_id) VALUES (?, ?)");
                    foreach ($studentIds as $stId) {
                        $stIns->execute([$advisorId, (int)$stId]);
                    }
                }
                db()->commit();
                log_activity($adminId, 'تخصیص دانش‌آموزان به مشاور', 'user', $advisorId, ['تعداد اختصاص‌یافته' => count($studentIds)]);
                $msg = 'لیست دانش‌آموزان تحت نظارت این مشاور با موفقیت ذخیره شد.';
            } catch (Exception $e) {
                db()->rollBack();
                $err = 'خطا در ثبت تخصیص دانش‌آموزان.';
            }
        }
    }

    // 6. حذف مشاور
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== $adminId) {
            $advName = db()->query("SELECT full_name FROM users WHERE id = $id")->fetchColumn() ?: 'مشاور';
            db()->prepare("UPDATE users SET advisor_id = NULL WHERE advisor_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM advisor_student_access WHERE advisor_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM users WHERE id = ? AND role = 'advisor'")->execute([$id]);
            log_activity($adminId, 'حذف مشاور از سامانه', 'user', $id, ['مشاور حذف‌شده' => $advName]);
            $msg = 'مشاور با موفقیت از سامانه حذف شد.';
        }
    }
}

// دریافت لیست تمام مشاوران و مدیران
$advisors = db()->query("
    SELECT u.id, u.full_name, u.username, u.email, u.phone, u.field, u.status, u.access_mode, u.role, u.created_at,
           (SELECT COUNT(*) FROM users WHERE (advisor_id = u.id OR id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id = u.id)) AND role = 'student') AS student_count
    FROM users u 
    WHERE u.role IN ('admin', 'advisor') 
    ORDER BY FIELD(u.role, 'admin', 'advisor'), u.created_at DESC
")->fetchAll();

// دریافت لیست دانش‌آموزان جهت فرم تخصیص
$allStudents = db()->query("
    SELECT id, full_name, username, grade, field, advisor_id 
    FROM users 
    WHERE role = 'student' 
    ORDER BY created_at DESC
")->fetchAll();

// دریافت لیست تخصیص‌ها
$assignmentRows = db()->query("SELECT advisor_id, student_id FROM advisor_student_access")->fetchAll();
$assignments = [];
foreach ($assignmentRows as $row) {
    $assignments[$row['advisor_id']][] = (int)$row['student_id'];
}

$totalAdv = count($advisors);
$activeAdv = count(array_filter($advisors, fn($a) => $a['status'] === 'active'));
$restrictedAdv = count(array_filter($advisors, fn($a) => $a['access_mode'] === 'restricted'));
$totalStudentsManaged = array_sum(array_column($advisors, 'student_count'));

panel_start('مدیریت حرفه‌ای مشاوران', 'نظارت کلان و کنترل سطح دسترسی مشاوران سامانه', 'admin', 'advisors');
?>

<!-- استایل‌های اختصاصی تکمیلی برای زیبایی خیره‌کننده عناصر پنل مشاوران -->
<style>
.master-security-header {
  background: linear-gradient(135deg, var(--surface-2), var(--surface));
  border: 1px solid var(--gold);
  border-radius: var(--r-lg);
  padding: 32px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.45);
  position: relative;
  overflow: hidden;
}
.master-security-header::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
  background: var(--grad-gold);
}
.adv-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 12px; border-radius: var(--r-pill); font-size: .78rem; font-weight: 800;
}
.adv-badge.gold { background: var(--gold-glass); color: var(--gold-light); border: 1px solid rgba(203,172,128,0.3); }
.adv-badge.sage { background: var(--sage-glass); color: var(--sage-light); border: 1px solid rgba(107,136,114,0.3); }
.adv-badge.danger { background: rgba(217,116,116,0.15); color: var(--danger); border: 1px solid rgba(217,116,116,0.3); }
.adv-badge.info { background: rgba(111,155,192,0.15); color: var(--info); border: 1px solid rgba(111,155,192,0.3); }

.search-filter-bar {
  background: var(--surface-1);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  padding: 20px 24px;
}
.adv-table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 0;
  overflow: hidden;
  box-shadow: var(--sh-md);
}

.action-btn {
  background: var(--surface-2);
  border: 1px solid var(--border-soft);
  color: var(--text-2);
  padding: 8px 12px;
  border-radius: 12px;
  font-size: .85rem;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all .2s var(--ease);
}
.action-btn:hover {
  background: var(--surface-3);
  color: var(--text);
  border-color: var(--border);
  transform: translateY(-2px);
}
.action-btn.gold:hover { color: var(--gold-light); border-color: var(--gold); background: var(--gold-glass); }
.action-btn.sage:hover { color: var(--sage-light); border-color: var(--sage); background: var(--sage-glass); }
.action-btn.danger:hover { color: var(--danger); border-color: var(--danger); background: rgba(217,116,116,0.15); }

.modal-custom-body { padding: 32px; background: var(--surface); color: var(--text); }
.student-select-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px; border-radius: 14px; background: var(--surface-2);
  border: 1px solid var(--border); margin-bottom: 8px; cursor: pointer;
  transition: all .2s var(--ease);
}
.student-select-item:hover { border-color: var(--gold-light); background: var(--surface-3); }
.student-select-item:has(input:checked) {
  border-color: var(--gold); background: var(--gold-glass); font-weight: 800;
}
</style>

<!-- 1. Header -->
<div class="master-security-header mb-6">
  <div class="between wrap gap-4">
    <div>
      <span class="adv-badge gold mb-3"><?= icon('shield', 15) ?> بخش امنیتی اختصاصی مشاور ارشد (دکتر سجاد صیادی)</span>
      <h1 class="display" style="font-size: clamp(1.8rem, 4vw, 2.4rem);">مدیریت و راهبری مشاوران</h1>
      <p class="lead mt-1" style="font-size: 1.05rem;">شما می‌توانید حساب‌های جدید ایجاد کنید، مشخصات مشاوران را تغییر دهید و محدوده دسترسی هر مشاور را تعیین کنید.</p>
    </div>
    <button onclick="openCreateAdvModal()" class="btn btn-gold btn-lg" style="font-weight: 900; px-6 py-4 shadow-lg">
      <?= icon('user-plus', 22) ?> <span>ثبت مشاور جدید</span>
    </button>
  </div>
</div>

<!-- 2. KPI Summary Cards -->
<div class="stat-cards grid gap-4 mb-6" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
  <div class="card stat">
    <div class="icon-tile" style="width: 56px; height: 56px; border-radius: 16px;"><?= icon('users', 28) ?></div>
    <div>
      <div class="v" style="font-size: 2rem; color: var(--text);"><?= fa_num($totalAdv) ?> <span style="font-size: 1rem; font-weight: normal; color: var(--text-3);">نفر</span></div>
      <div class="k" style="font-size: .9۵rem; font-weight: 700; mt-1">کل مشاوران سامانه</div>
    </div>
  </div>
  
  <div class="card stat">
    <div class="icon-tile sage" style="width: 56px; height: 56px; border-radius: 16px;"><?= icon('check-circle', 28) ?></div>
    <div>
      <div class="v" style="font-size: 2rem; color: var(--success);"><?= fa_num($activeAdv) ?> <span style="font-size: 1rem; font-weight: normal; color: var(--text-3);">نفر</span></div>
      <div class="k" style="font-size: .9۵rem; font-weight: 700; mt-1">مشاوران فعال</div>
    </div>
  </div>

  <div class="card stat">
    <div class="icon-tile" style="width: 56px; height: 56px; border-radius: 16px; background: rgba(217,178,95,0.15); color: var(--warn);"><?= icon('shield', 28) ?></div>
    <div>
      <div class="v" style="font-size: 2rem; color: var(--warn);"><?= fa_num($restrictedAdv) ?> <span style="font-size: 1rem; font-weight: normal; color: var(--text-3);">نفر</span></div>
      <div class="k" style="font-size: .9۵rem; font-weight: 700; mt-1">دسترسی اختصاصی (محدود)</div>
    </div>
  </div>

  <div class="card stat">
    <div class="icon-tile" style="width: 56px; height: 56px; border-radius: 16px; background: rgba(111,155,192,0.15); color: var(--info);"><?= icon('graduation', 28) ?></div>
    <div>
      <div class="v" style="font-size: 2rem; color: var(--info);"><?= fa_num($totalStudentsManaged) ?> <span style="font-size: 1rem; font-weight: normal; color: var(--text-3);">دانش‌آموز</span></div>
      <div class="k" style="font-size: .9۵rem; font-weight: 700; mt-1">دانش‌آموزان تحت پوشش</div>
    </div>
  </div>
</div>

<!-- Alert Flashes -->
<?php if ($msg): ?>
  <div class="card glass mb-6" style="border-color: var(--success); background: rgba(95,174,123,0.15); color: var(--text); padding: 16px 24px;">
    <div class="flex items-center gap-3 font-bold text-base">
      <span style="color: var(--success);"><?= icon('check-circle', 24) ?></span>
      <span><?= e($msg) ?></span>
    </div>
  </div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="card glass mb-6" style="border-color: var(--danger); background: rgba(217,116,116,0.15); color: var(--text); padding: 16px 24px;">
    <div class="flex items-center gap-3 font-bold text-base">
      <span style="color: var(--danger);"><?= icon('info', 24) ?></span>
      <span><?= e($err) ?></span>
    </div>
  </div>
<?php endif; ?>

<!-- 3. Search & Filters Bar -->
<div class="search-filter-bar mb-6 between wrap gap-4">
  <div class="input-group" style="flex: 1; min-width: 280px; max-width: 420px;">
    <span class="ig-icon"><?= icon('search', 20) ?></span>
    <input type="text" id="searchInput" onkeyup="filterAdvisors()" placeholder="جستجو در نام مشاور، نام کاربری، ایمیل یا تخصص..." class="input" style="height: 48px; font-weight: bold; font-size: .95rem;">
  </div>
  
  <div class="flex items-center gap-3 wrap">
    <span class="muted font-bold" style="font-size: .95rem;">فیلتر نمایش:</span>
    <select id="filterStatus" onchange="filterAdvisors()" class="select" style="width: auto; min-width: 200px; height: 48px; font-weight: bold; font-size: .95rem;">
      <option value="all">👑 همه مشاوران</option>
      <option value="active">✓ مشاوران فعال</option>
      <option value="suspended">✕ مسدود شده‌ها</option>
      <option value="access_all">🌐 دسترسی کل سامانه</option>
      <option value="access_restricted">🛡️ دسترسی محدود (اختصاصی)</option>
    </select>
  </div>
</div>

<!-- 4. Main Advisors Table Card -->
<div class="adv-table-card">
  <div class="table-wrap" style="overflow-x: auto;">
    <table class="tbl" id="advisorsTable" style="min-width: 880px;">
      <thead>
        <tr style="background: var(--surface-2); border-bottom: 2px solid var(--border);">
          <th style="padding: 16px 24px; font-size: .9rem;">مشاور و سمت تخصصی</th>
          <th style="padding: 16px 24px; font-size: .9rem;">نام کاربری و اطلاعات ارتباطی</th>
          <th style="padding: 16px 24px; text-align: center; font-size: .9rem;">دانش‌آموزان</th>
          <th style="padding: 16px 24px; text-align: center; font-size: .9rem;">سطح دسترسی</th>
          <th style="padding: 16px 24px; text-align: center; font-size: .9rem;">وضعیت حساب</th>
          <th style="padding: 16px 24px; font-size: .9rem;">تاریخ ثبت</th>
          <th style="padding: 16px 24px; text-align: center; width: 340px; font-size: .9rem;">عملیات راهبری</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($advisors)): ?>
          <tr>
            <td colspan="7" class="text-c muted" style="padding: 64px 24px;">
              <div class="center mb-4"><?= icon('users', 56, 'muted opacity-50') ?></div>
              <p style="font-size: 1.1rem; font-weight: bold;">هیچ مشاوری در سیستم ثبت نشده است.</p>
              <p class="muted mt-1" style="font-size: .9rem;">با استفاده از دکمه‌ی «ثبت مشاور جدید» اولین مشاور را به سامانه اضافه کنید.</p>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($advisors as $adv): 
              $assignedList = $assignments[$adv['id']] ?? [];
              $advJson = json_encode([
                  'id' => $adv['id'],
                  'full_name' => $adv['full_name'],
                  'username' => $adv['username'],
                  'email' => $adv['email'] ?? '',
                  'phone' => $adv['phone'] ?? '',
                  'field' => $adv['field'] ?? '',
                  'access_mode' => $adv['access_mode'],
                  'assigned_students' => $assignedList
              ], JSON_UNESCAPED_UNICODE);
          ?>
          <tr class="advisor-row" 
              data-name="<?= e($adv['full_name']) ?>" 
              data-username="<?= e($adv['username']) ?>" 
              data-specialty="<?= e($adv['field']) ?>" 
              data-email="<?= e($adv['email'] ?? '') ?>"
              data-status="<?= $adv['status'] ?>"
              data-access="<?= $adv['access_mode'] ?>">
            
            <td style="padding: 18px 24px;">
              <div class="u-row">
                <div class="u-ava gold" style="width: 46px; height: 46px; font-size: 1.1rem; box-shadow: var(--sh-glow-gold);">
                  <?= e(avatar_letters($adv['full_name'])) ?>
                </div>
                <div>
                  <div style="font-weight: 800; font-size: 1.1rem; color: var(--text);"><?= e($adv['full_name']) ?></div>
                  <div class="muted mt-1" style="font-size: .85rem; font-weight: 600;"><?= e($adv['field'] ?: 'مشاور تحصیلی') ?></div>
                </div>
              </div>
            </td>

            <td style="padding: 18px 24px;">
              <code style="background: var(--surface-3); padding: 4px 10px; border-radius: 8px; font-family: monospace; font-size: .95rem; font-weight: bold; color: var(--gold-light); display: inline-block; direction: ltr;"><?= e($adv['username']) ?></code>
              <div class="muted mt-1.5 flex items-center gap-3" style="font-size: .82rem;">
                <?php if ($adv['phone']): ?>
                  <span class="flex items-center gap-1 font-mono dir-ltr"><?= icon('phone', 14) ?> <?= e($adv['phone']) ?></span>
                <?php endif; ?>
                <?php if ($adv['email']): ?>
                  <span class="flex items-center gap-1 opacity-80" title="<?= e($adv['email']) ?>"><?= icon('message', 14) ?> <?= e(mb_strimwidth($adv['email'], 0, 20, '...')) ?></span>
                <?php endif; ?>
              </div>
            </td>

            <td style="padding: 18px 24px; text-align: center;">
              <a href="students.php?advisor=<?= $adv['id'] ?>" class="adv-badge sage hover-lift" style="padding: 6px 14px; background: var(--surface-3); color: var(--sage-light); border-color: var(--border);">
                <?= icon('users', 16) ?>
                <span><?= fa_num($adv['student_count']) ?> دانش‌آموز</span>
              </a>
            </td>

            <td style="padding: 18px 24px; text-align: center;">
              <?php if ($adv['access_mode'] === 'all'): ?>
                <span class="adv-badge gold" style="padding: 6px 14px;">
                  <?= icon('globe', 16) ?>
                  <span>کل سامانه</span>
                </span>
              <?php else: ?>
                <button onclick='openAssignAdvModal(<?= $advJson ?>)' class="adv-badge" style="padding: 6px 14px; background: rgba(217,178,95,0.15); color: var(--warn); border: 1px solid rgba(217,178,95,0.4); cursor: pointer;" title="کلیک برای تعیین دانش‌آموزان مجاز">
                  <?= icon('shield', 16) ?>
                  <span>محدود (اختصاصی)</span>
                </button>
              <?php endif; ?>
            </td>

            <td style="padding: 18px 24px; text-align: center;">
              <?php if ($adv['status'] === 'active'): ?>
                <span class="adv-badge" style="background: rgba(95,174,123,0.18); color: var(--success); border: 1px solid rgba(95,174,123,0.4); padding: 6px 14px;">
                  <span>فعال</span>
                </span>
              <?php else: ?>
                <span class="adv-badge danger" style="padding: 6px 14px;">
                  <span>مسدود</span>
                </span>
              <?php endif; ?>
            </td>

            <td style="padding: 18px 24px; font-size: .9rem; color: var(--text-3); font-weight: bold;">
              <?= jalali_date($adv['created_at']) ?>
            </td>

            <td style="padding: 18px 24px; text-align: center;">
              <div class="center gap-2 wrap">
                <!-- ویرایش مشخصات -->
                <button onclick='openEditAdvModal(<?= $advJson ?>)' class="action-btn gold" data-tip="ویرایش مشخصات">
                  <?= icon('edit', 18) ?>
                  <span>ویرایش</span>
                </button>

                <!-- تخصیص دانش‌آموز -->
                <button onclick='openAssignAdvModal(<?= $advJson ?>)' class="action-btn <?= $adv['access_mode'] === 'restricted' ? 'gold' : '' ?>" data-tip="دانش‌آموزان اختصاصی">
                  <?= icon('user-plus', 18) ?>
                  <span>دسترسی</span>
                </button>

                <!-- مشاهده لاگ‌ها -->
                <a href="logs.php?advisor_id=<?= $adv['id'] ?>" class="action-btn sage" data-tip="لاگ فعالیت‌ها">
                  <?= icon('history', 18) ?>
                  <span>لاگ</span>
                </a>

                <!-- تغییر وضعیت -->
                <?php if ($adv['id'] !== $adminId): ?>
                <form method="post" style="display: inline;">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                  <input type="hidden" name="status" value="<?= $adv['status'] === 'active' ? 'suspended' : 'active' ?>">
                  <?= csrf_field(); ?>
                  <button type="submit" class="action-btn <?= $adv['status'] === 'active' ? 'danger' : 'sage' ?>" data-tip="<?= $adv['status'] === 'active' ? 'مسدودسازی حساب' : 'فعال‌سازی حساب' ?>">
                    <?= $adv['status'] === 'active' ? icon('lock', 18) : icon('unlock', 18) ?>
                  </button>
                </form>
                <?php endif; ?>

                <!-- حذف مشاور -->
                <?php if ($adv['id'] !== $adminId): ?>
                  <form method="post" style="display: inline;" onsubmit="return confirm('آیا از حذف کامل مشاور «<?= e($adv['full_name']) ?>» مطمئن هستید؟')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                    <?= csrf_field(); ?>
                    <button type="submit" class="action-btn danger" data-tip="حذف مشاور">
                      <?= icon('trash', 18) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>

          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. Modal: ثبت مشاور جدید -->
<div class="modal-backdrop" id="createModal">
  <div class="modal" style="max-width: 640px; padding: 0; overflow: hidden; background: var(--surface);">
    <div class="between" style="padding: 24px 32px; background: var(--surface-2); border-bottom: 1px solid var(--border);">
      <h3 style="font-size: 1.35rem; color: var(--gold); font-weight: 900; display: flex; align-items: center; gap: 10px;">
        <?= icon('user-plus', 26) ?> ثبت مشاور جدید در سامانه
      </h3>
      <button class="modal-close" onclick="closeModal('createModal')" style="width: 36px; height: 36px;"><?= icon('close', 18) ?></button>
    </div>

    <form method="post" class="modal-custom-body grid gap-4">
      <input type="hidden" name="action" value="create_advisor">
      <?= csrf_field(); ?>

      <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">نام و نام خانوادگی مشاور *</label>
          <input type="text" name="full_name" class="input" placeholder="مثال: دکتر علیرضا بهرامی" required>
        </div>
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">سمت / حوزه تخصصی</label>
          <input type="text" name="field" class="input" placeholder="مثال: مشاور ارشد تجربی" value="مشاور تحصیلی">
        </div>
      </div>

      <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">نام کاربری جهت ورود *</label>
          <input type="text" name="username" class="input" dir="ltr" placeholder="alireza_adv" required>
        </div>
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">گذرواژه اولیه *</label>
          <input type="password" name="password" class="input" dir="ltr" placeholder="حداقل ۶ کاراکتر" minlength="6" required>
        </div>
      </div>

      <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">شماره موبایل</label>
          <input type="tel" name="phone" class="input" dir="ltr" placeholder="09123456789">
        </div>
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">ایمیل ارتباطی</label>
          <input type="email" name="email" class="input" dir="ltr" placeholder="advisor@example.com">
        </div>
      </div>

      <div class="field m-0">
        <label style="font-size: .95rem; color: var(--text); mb-2">سطح دسترسی و محدوده نظارت</label>
        <div class="grid gap-3" style="grid-template-columns: 1fr 1fr;">
          <label class="card hover-lift m-0" style="padding: 16px; cursor: pointer; display: flex; items-center; gap: 12px; border: 1px solid var(--border);">
            <input type="radio" name="access_mode" value="all" checked style="accent-color: var(--gold); width: 20px; height: 20px;">
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--text);">دسترسی کامل</div>
              <div class="muted mt-1" style="font-size: .8rem;">کل سامانه و تمام دانش‌آموزان</div>
            </div>
          </label>
          <label class="card hover-lift m-0" style="padding: 16px; cursor: pointer; display: flex; items-center; gap: 12px; border: 1px solid var(--border);">
            <input type="radio" name="access_mode" value="restricted" style="accent-color: var(--gold); width: 20px; height: 20px;">
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--text);">دسترسی محدود</div>
              <div class="muted mt-1" style="font-size: .8rem;">فقط دانش‌آموزان اختصاصی</div>
            </div>
          </label>
        </div>
      </div>

      <div class="between pt-4 mt-2" style="border-top: 1px solid var(--border-soft); justify-content: flex-end; gap: 12px;">
        <button type="button" onclick="closeModal('createModal')" class="btn btn-ghost px-6">انصراف</button>
        <button type="submit" class="btn btn-gold px-8 py-3" style="font-weight: 900;">ثبت نهایی مشاور</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. Modal: ویرایش مشخصات مشاور -->
<div class="modal-backdrop" id="editModal">
  <div class="modal" style="max-width: 640px; padding: 0; overflow: hidden; background: var(--surface);">
    <div class="between" style="padding: 24px 32px; background: var(--surface-2); border-bottom: 1px solid var(--border);">
      <h3 style="font-size: 1.35rem; color: var(--sage-light); font-weight: 900; display: flex; align-items: center; gap: 10px;">
        <?= icon('edit', 26) ?> <span id="editModalSubtitle">ویرایش مشخصات مشاور</span>
      </h3>
      <button class="modal-close" onclick="closeModal('editModal')" style="width: 36px; height: 36px;"><?= icon('close', 18) ?></button>
    </div>

    <form method="post" class="modal-custom-body grid gap-4">
      <input type="hidden" name="action" value="edit_advisor">
      <input type="hidden" name="id" id="edit_id" value="">
      <?= csrf_field(); ?>

      <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">نام و نام خانوادگی مشاور *</label>
          <input type="text" name="full_name" id="edit_full_name" class="input" required>
        </div>
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">سمت / حوزه تخصصی</label>
          <input type="text" name="field" id="edit_field" class="input">
        </div>
      </div>

      <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">نام کاربری جهت ورود *</label>
          <input type="text" name="username" id="edit_username" class="input" dir="ltr" required>
        </div>
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">گذرواژه جدید (اختیاری)</label>
          <input type="password" name="password" class="input" dir="ltr" placeholder="در صورت عدم تغییر خالی بگذارید">
        </div>
      </div>

      <div class="grid gap-4" style="grid-template-columns: 1fr 1fr;">
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">شماره موبایل</label>
          <input type="tel" name="phone" id="edit_phone" class="input" dir="ltr">
        </div>
        <div class="field m-0">
          <label style="font-size: .9rem; color: var(--text);">ایمیل ارتباطی</label>
          <input type="email" name="email" id="edit_email" class="input" dir="ltr">
        </div>
      </div>

      <div class="field m-0">
        <label style="font-size: .95rem; color: var(--text); mb-2">سطح دسترسی و محدوده نظارت</label>
        <div class="grid gap-3" style="grid-template-columns: 1fr 1fr;">
          <label class="card hover-lift m-0" style="padding: 16px; cursor: pointer; display: flex; items-center; gap: 12px; border: 1px solid var(--border);">
            <input type="radio" name="access_mode" id="edit_access_all" value="all" style="accent-color: var(--sage-light); width: 20px; height: 20px;">
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--text);">دسترسی کامل</div>
              <div class="muted mt-1" style="font-size: .8rem;">کل سامانه و تمام دانش‌آموزان</div>
            </div>
          </label>
          <label class="card hover-lift m-0" style="padding: 16px; cursor: pointer; display: flex; items-center; gap: 12px; border: 1px solid var(--border);">
            <input type="radio" name="access_mode" id="edit_access_restricted" value="restricted" style="accent-color: var(--sage-light); width: 20px; height: 20px;">
            <div>
              <div style="font-weight: 800; font-size: 1rem; color: var(--text);">دسترسی محدود</div>
              <div class="muted mt-1" style="font-size: .8rem;">فقط دانش‌آموزان اختصاصی</div>
            </div>
          </label>
        </div>
      </div>

      <div class="between pt-4 mt-2" style="border-top: 1px solid var(--border-soft); justify-content: flex-end; gap: 12px;">
        <button type="button" onclick="closeModal('editModal')" class="btn btn-ghost px-6">انصراف</button>
        <button type="submit" class="btn btn-sage px-8 py-3" style="font-weight: 900;">ذخیره تغییرات</button>
      </div>
    </form>
  </div>
</div>

<!-- 3. Modal: مدیریت تخصیص دانش‌آموزان به مشاور در حالت محدود -->
<div class="modal-backdrop" id="assignModal">
  <div class="modal" style="max-width: 720px; padding: 0; overflow: hidden; background: var(--surface);">
    <div class="between" style="padding: 24px 32px; background: var(--surface-2); border-bottom: 1px solid var(--border);">
      <h3 style="font-size: 1.35rem; color: var(--warn); font-weight: 900; display: flex; align-items: center; gap: 10px;">
        <?= icon('shield', 26) ?> <span id="assignModalTitle">تخصیص دانش‌آموزان اختصاصی</span>
      </h3>
      <button class="modal-close" onclick="closeModal('assignModal')" style="width: 36px; height: 36px;"><?= icon('close', 18) ?></button>
    </div>

    <form method="post" class="modal-custom-body grid gap-4">
      <input type="hidden" name="action" value="assign_students">
      <input type="hidden" name="advisor_id" id="assign_advisor_id" value="">
      <?= csrf_field(); ?>

      <div class="input-group">
        <span class="ig-icon"><?= icon('search', 18) ?></span>
        <input type="text" id="studentSearchInput" onkeyup="filterAssignStudents()" placeholder="جستجوی سریع دانش‌آموز (نام، رشته، پایه)..." class="input" style="height: 48px; font-weight: bold;">
      </div>

      <div class="between px-2" style="font-size: .9rem; font-weight: bold; color: var(--text-2);">
        <span>انتخاب دانش‌آموزان تحت نظارت مشاور:</span>
        <div class="flex items-center gap-3">
          <button type="button" onclick="selectAllStudents(true)" class="btn btn-ghost btn-sm" style="color: var(--gold-light);">✓ انتخاب همه</button>
          <button type="button" onclick="selectAllStudents(false)" class="btn btn-ghost btn-sm" style="color: var(--danger);">✕ لغو همه</button>
        </div>
      </div>

      <!-- لیست دانش‌آموزان -->
      <div class="card m-0" style="padding: 16px; max-height: 380px; overflow-y: auto; background: var(--surface-1); border-color: var(--border);">
        <?php if (empty($allStudents)): ?>
          <div class="text-c muted py-8">هیچ دانش‌آموزی در سیستم ثبت نشده است.</div>
        <?php else: ?>
          <?php foreach ($allStudents as $st): ?>
          <label class="student-select-item student-assign-item" data-text="<?= e($st['full_name'] . ' ' . $st['username'] . ' ' . $st['field'] . ' ' . $st['grade']) ?>">
            <div class="flex items-center gap-3 font-bold">
              <input type="checkbox" name="student_ids[]" value="<?= $st['id'] ?>" class="student-checkbox" style="accent-color: var(--gold); width: 22px; height: 22px;">
              <div>
                <div style="font-size: 1.05rem; color: var(--text);"><?= e($st['full_name']) ?> <code class="muted ml-2 font-normal text-xs">(<?= e($st['username']) ?>)</code></div>
                <div class="muted mt-0.5 font-normal" style="font-size: .85rem;">
                  <?= e($st['field'] ?: 'رشته نامشخص') ?> • <?= e($st['grade'] ?: 'پایه نامشخص') ?>
                </div>
              </div>
            </div>
            <?php if ($st['advisor_id']): 
                $advNm = db()->query("SELECT full_name FROM users WHERE id = " . (int)$st['advisor_id'])->fetchColumn();
            ?>
              <span class="badge" style="font-size: .75rem; background: var(--surface-3); border-color: var(--border);">
                مشاور اصلی: <?= e($advNm ?: 'نامشخص') ?>
              </span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="between pt-4 mt-2" style="border-top: 1px solid var(--border-soft); justify-content: flex-end; gap: 12px;">
        <button type="button" onclick="closeModal('assignModal')" class="btn btn-ghost px-6">انصراف</button>
        <button type="submit" class="btn btn-gold px-8 py-3" style="font-weight: 900;">ذخیره تخصیص دانش‌آموزان</button>
      </div>
    </form>
  </div>
</div>

<script>
// جستجو و فیلتر مشاوران
function filterAdvisors() {
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const rows = document.querySelectorAll('.advisor-row');

    rows.forEach(row => {
        const name = row.dataset.name.toLowerCase();
        const username = row.dataset.username.toLowerCase();
        const specialty = row.dataset.specialty.toLowerCase();
        const email = row.dataset.email.toLowerCase();
        const status = row.dataset.status;
        const access = row.dataset.access;

        let matchesSearch = name.includes(q) || username.includes(q) || specialty.includes(q) || email.includes(q);
        let matchesStatus = true;

        if (statusFilter === 'active') matchesStatus = status === 'active';
        if (statusFilter === 'suspended') matchesStatus = status === 'suspended';
        if (statusFilter === 'access_all') matchesStatus = access === 'all';
        if (statusFilter === 'access_restricted') matchesStatus = access === 'restricted';

        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
}

// توابع امن برای نمایش و بستن مودال‌ها
function openCreateAdvModal() {
    document.getElementById('createModal').classList.add('open');
}
function openEditAdvModal(adv) {
    document.getElementById('edit_id').value = adv.id;
    document.getElementById('edit_full_name').value = adv.full_name;
    document.getElementById('edit_username').value = adv.username;
    document.getElementById('edit_field').value = adv.field;
    document.getElementById('edit_phone').value = adv.phone;
    document.getElementById('edit_email').value = adv.email;
    document.getElementById('editModalSubtitle').innerText = `ویرایش مشخصات «${adv.full_name}»`;

    if (adv.access_mode === 'all') {
        document.getElementById('edit_access_all').checked = true;
    } else {
        document.getElementById('edit_access_restricted').checked = true;
    }

    document.getElementById('editModal').classList.add('open');
}
function openAssignAdvModal(adv) {
    document.getElementById('assign_advisor_id').value = adv.id;
    document.getElementById('assignModalTitle').innerText = `تخصیص دانش‌آموزان • ${adv.full_name}`;

    const assignedIds = adv.assigned_students || [];
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = assignedIds.includes(parseInt(cb.value));
    });

    document.getElementById('studentSearchInput').value = '';
    filterAssignStudents();

    document.getElementById('assignModal').classList.add('open');
}

window.closeModal = function(id) {
    const el = typeof id === 'string' ? document.getElementById(id) : id;
    if (el) el.classList.remove('open');
};

function filterAssignStudents() {
    const q = document.getElementById('studentSearchInput').value.trim().toLowerCase();
    const items = document.querySelectorAll('.student-assign-item');
    items.forEach(item => {
        const txt = item.dataset.text.toLowerCase();
        item.style.display = txt.includes(q) ? '' : 'none';
    });
}

function selectAllStudents(select) {
    const checkboxes = document.querySelectorAll('.student-assign-item:not([style*="display: none"]) .student-checkbox');
    checkboxes.forEach(cb => { cb.checked = select; });
}
</script>

<?php panel_end(); ?>