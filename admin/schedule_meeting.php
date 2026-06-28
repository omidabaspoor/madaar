<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/meetings.php';
boot_session();
require_role('advisor', 'admin');
$u = current_user();

meetings_schema_ready();

// ==== Handle Actions ====
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// تأیید نهایی پیش‌نویس
if ($action === 'confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $draftGroupId = trim((string)($_POST['draft_group_id'] ?? ''));
    if ($draftGroupId) {
        $results = meetings_confirm((int)$u['id'], $draftGroupId);
        if ($results['ok']) {
            $typeLabel = 'جلسه/کلاس';
            $msg = "✅ پیش‌نویس {$typeLabel} تأیید و زمان‌بندی شد.";
            if ($results['sent'] > 0) $msg .= " 📱 " . fa_num($results['sent']) . " پیامک ارسال شد.";
            if ($results['failed'] > 0) $msg .= " ⚠️ " . fa_num($results['failed']) . " پیامک خطا داد.";
            if ($results['no_phone'] > 0) $msg .= " 📞 " . fa_num($results['no_phone']) . " دانش‌آموز شماره موبایل ندارند.";
            flash($results['failed'] === 0 && $results['no_phone'] === 0 ? 'success' : 'info', $msg);
        } else {
            flash('error', $results['error'] ?? 'خطا در تأیید');
        }
    }
    redirect('admin/schedule_meeting.php');
}

// حذف پیش‌نویس
if ($action === 'delete_draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $draftGroupId = trim((string)($_POST['draft_group_id'] ?? ''));
    if ($draftGroupId && meetings_delete_draft((int)$u['id'], $draftGroupId)) {
        flash('success', 'پیش‌نویس با موفقیت حذف شد.');
    } else {
        flash('error', 'خطا در حذف پیش‌نویس.');
    }
    redirect('admin/schedule_meeting.php');
}

// لغو جلسه تأییدشده
if (isset($_GET['cancel'])) {
    $cancelId = (int)$_GET['cancel'];
    if (meetings_cancel($cancelId, (int)$u['id'], 'advisor')) {
        flash('success', 'جلسه/کلاس لغو شد.');
    } else {
        flash('error', 'خطا در لغو یا عدم دسترسی.');
    }
    redirect('admin/schedule_meeting.php');
}

// ذخیره پیش‌نویس (جدید یا ویرایش)
if ($action === 'save_draft' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $sessionType = ($_POST['session_type'] ?? 'consultation') === 'class' ? 'class' : 'consultation';
        $title = trim((string)($_POST['title'] ?? ''));
        $date = trim((string)($_POST['session_date'] ?? ''));
        $time = trim((string)($_POST['session_time'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($sessionType === 'class') {
            $studentIds = $_POST['student_ids'] ?? [];
            if (!is_array($studentIds)) $studentIds = [];
            $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        } else {
            $studentIds = [(int)($_POST['student_id'] ?? 0)];
        }

        // اعتبارسنجی: تاریخ و ساعت اجباری، عنوان اختیاری
        if (!$studentIds || !$date || !$time) {
            throw new RuntimeException('لطفاً دانش‌آموز، تاریخ و ساعت را تکمیل کنید (عنوان اختیاری است).');
        }

        // اعتبارسنجی تاریخ و ساعت معتبر
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateTime) throw new RuntimeException('تاریخ نامعتبر است.');
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) throw new RuntimeException('ساعت نامعتبر است.');

        $existingDraftGroupId = !empty($_POST['draft_group_id']) ? trim((string)$_POST['draft_group_id']) : null;

        $result = meetings_save_draft((int)$u['id'], $studentIds, $title, $date, $time, $notes ?: null, $sessionType, $existingDraftGroupId);

        if ($result['student_count'] === 0) {
            throw new RuntimeException('هیچ دانش‌آموز معتبری انتخاب نشده است.');
        }

        $msg = $existingDraftGroupId
            ? '✏️ پیش‌نویس به‌روزرسانی شد. اکنون می‌توانید آن را ویرایش یا تأیید کنید.'
            : '💾 پیش‌نویس با موفقیت ذخیره شد. اکنون می‌توانید آن را ویرایش، حذف یا تأیید نهایی کنید.';

        flash('success', $msg);
        redirect('admin/schedule_meeting.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

// ==== Load data ====
$students = advisor_students((int)$u['id'], 'active');
$drafts = meetings_drafts_for_advisor((int)$u['id']);
$scheduledSessions = meetings_for_advisor((int)$u['id']);

// ==== Edit Mode ====
$editDraft = null;
$editDraftGroupId = $_GET['edit'] ?? '';
if ($editDraftGroupId) {
    $editDraft = meetings_get_draft_for_edit((int)$u['id'], $editDraftGroupId);
    if (!$editDraft) {
        flash('error', 'پیش‌نویس یافت نشد یا قبلاً تأیید شده.');
        redirect('admin/schedule_meeting.php');
    }
}

panel_start('برنامه‌ریزی جلسات', 'سیستم پیش‌نویس، ویرایش، تأیید نهایی و ارسال پیامک', 'admin', 'meetings', ['student.css']);
?>

<style>
.sm-hero{
  background:radial-gradient(circle at 12% 0%,rgba(203,172,128,.18),transparent 36%),
             linear-gradient(135deg,rgba(107,136,114,.16),rgba(12,21,18,.4));
  border:1px solid rgba(178,148,95,.3);
  border-radius:var(--r-xl); padding:26px; margin-bottom:24px;
  box-shadow:0 12px 36px rgba(0,0,0,.3);
}
.sm-hero h2{font-size:1.4rem;color:var(--gold-light);font-weight:1000;margin:8px 0 6px}
.sm-hero p{color:var(--text-2);font-size:.94rem;line-height:1.8;max-width:760px;margin:0}
.sm-steps{display:flex;gap:14px;margin-top:18px;flex-wrap:wrap}
.sm-step{display:flex;align-items:center;gap:10px;padding:10px 16px;border-radius:14px;background:rgba(178,148,95,.08);border:1px solid rgba(178,148,95,.18);font-size:.86rem;font-weight:800;color:var(--text-2)}
.sm-step .num{width:28px;height:28px;border-radius:50%;background:var(--grad-gold);color:#1a1206;display:grid;place-items:center;font-weight:1000;flex-shrink:0;font-size:.86rem}

.sm-layout{display:grid;gap:20px;grid-template-columns:1.2fr 1fr;margin-bottom:24px}
@media(max-width:900px){.sm-layout{grid-template-columns:1fr}}

.sm-form-panel,.sm-preview-panel{
  background:linear-gradient(160deg,var(--card),var(--surface));
  border:1px solid var(--border-soft);
  border-radius:var(--r-lg);
  padding:24px;
  box-shadow:0 10px 30px rgba(0,0,0,.2);
}
.sm-form-panel h3,.sm-preview-panel h3{font-size:1.05rem;font-weight:900;color:var(--text-1);margin-bottom:18px;display:flex;align-items:center;gap:9px}
.sm-form-panel h3{color:var(--gold-light)}
.sm-preview-panel h3{color:var(--sage-light)}

.sm-section{margin-bottom:18px}
.sm-section-label{display:flex;align-items:center;gap:8px;font-size:.78rem;font-weight:900;color:var(--text-2);margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em}
.sm-section-label .ic{font-size:1.1rem}

.sm-type-toggle{display:grid;grid-template-columns:1fr 1fr;gap:8px;background:var(--surface-2);padding:6px;border-radius:16px;border:1px solid var(--border-soft)}
.sm-type-toggle label{cursor:pointer;padding:10px 14px;border-radius:12px;text-align:center;font-weight:900;font-size:.92rem;transition:.2s;color:var(--text-3);display:flex;align-items:center;justify-content:center;gap:8px}
.sm-type-toggle label.active{background:var(--grad-gold);color:#1a1206;box-shadow:var(--sh-glow-gold)}
.sm-type-toggle input{display:none}

.sm-students-list{
  background:var(--surface-2);border:1px solid var(--border-soft);
  border-radius:var(--r-sm);padding:8px;max-height:280px;overflow-y:auto;
}
.sm-students-list:empty::before{content:'هیچ دانش‌آموز فعالی ندارید';display:block;padding:14px;text-align:center;color:var(--text-3);font-size:.86rem}
.sm-student-item{
  display:flex;align-items:center;gap:12px;padding:9px 12px;border-radius:10px;
  cursor:pointer;transition:.15s;
}
.sm-student-item:hover{background:var(--surface-3)}
.sm-student-item.checked{background:var(--gold-glass);border:1px solid rgba(203,172,128,.3)}
.sm-student-item input{accent-color:var(--gold);width:18px;height:18px;flex-shrink:0}
.sm-student-item .nm{font-weight:700;font-size:.92rem;flex:1}
.sm-student-item .badge{font-size:.7rem;padding:2px 7px}
.sm-student-item .ph{font-size:.78rem;color:var(--text-3)}
.sm-student-item .ph-miss{color:var(--warn)}

.sm-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;padding-top:18px;border-top:1px solid var(--border-soft)}
.sm-actions .btn{flex:1;min-width:140px;justify-content:center;font-weight:900;min-height:48px}

.phone-preview{
  background:linear-gradient(160deg,#0e1915,#15201b);
  border:2px solid var(--border);
  border-radius:28px;
  padding:20px 16px;
  font-family:'Vazirmatn',monospace;
  position:relative;
  min-height:340px;
}
.phone-preview::before{
  content:'';position:absolute;top:8px;left:50%;transform:translateX(-50%);
  width:60px;height:5px;background:var(--border);border-radius:99px;
}
.phone-preview .pp-head{
  display:flex;align-items:center;justify-content:space-between;
  font-size:.7rem;color:var(--text-3);font-weight:800;margin-top:14px;margin-bottom:14px;
  padding:0 8px;
}
.phone-preview .pp-bubble{
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
  border-radius:18px;padding:14px 16px;line-height:1.95;
  font-size:.92rem;color:var(--text);
  white-space:pre-wrap;word-wrap:break-word;
  margin-bottom:10px;
}
.phone-preview .pp-bubble .emoji{font-size:1.05rem;margin-right:4px}
.phone-preview .pp-meta{
  display:flex;justify-content:space-between;align-items:center;
  font-size:.66rem;color:var(--text-3);font-weight:800;
  padding:0 8px;
}
.phone-preview .pp-status{
  font-size:.66rem;color:var(--sage-light);font-weight:800;display:inline-flex;align-items:center;gap:4px;
}

.draft-card{
  background:linear-gradient(160deg,var(--card),var(--surface));
  border:2px solid rgba(178,148,95,.35);
  border-radius:var(--r-lg);
  padding:18px 20px;
  position:relative;
  transition:.2s;
}
.draft-card:hover{border-color:var(--gold);transform:translateY(-2px)}
.draft-card .dc-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px}
.draft-card .dc-title{display:flex;align-items:center;gap:8px;font-weight:900;font-size:1rem;color:var(--text-1);margin-bottom:4px}
.draft-card .dc-meta{display:flex;flex-wrap:wrap;gap:14px;margin:8px 0;font-size:.86rem;color:var(--text-2)}
.draft-card .dc-meta span{display:inline-flex;align-items:center;gap:5px}
.draft-card .dc-students{margin:10px 0;padding:10px 12px;background:var(--surface-2);border-radius:12px;border:1px solid var(--border-soft);font-size:.86rem}
.draft-card .dc-students b{color:var(--gold-light);font-weight:900}
.draft-card .dc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft)}
.draft-card .dc-actions .btn{flex:1;min-height:42px;font-weight:900;font-size:.86rem;justify-content:center}
.draft-card .dc-badge{position:absolute;top:-10px;right:14px;background:var(--grad-gold);color:#1a1206;font-size:.7rem;font-weight:1000;padding:3px 10px;border-radius:99px;letter-spacing:.04em}

.sessions-list{display:flex;flex-direction:column;gap:10px}
.session-row{
  background:var(--surface-2);border:1px solid var(--border-soft);
  border-radius:var(--r-md);padding:14px 16px;
  display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;align-items:center;
}
.session-row:hover{border-color:var(--border)}
.session-row .sr-info{flex:1;min-width:240px}
.session-row .sr-title{font-weight:800;font-size:.95rem;margin-bottom:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.session-row .sr-meta{display:flex;flex-wrap:wrap;gap:14px;font-size:.82rem;color:var(--text-3)}
.session-row .sr-meta span{display:inline-flex;align-items:center;gap:5px}
.session-row.is-today{border-color:var(--gold);background:linear-gradient(135deg,rgba(203,172,128,.10),var(--surface-2))}
</style>

<!-- ============== HERO ============== -->
<div class="sm-hero">
  <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap">
    <span class="icon-tile" style="width:56px;height:56px;font-size:1.5rem;border-radius:16px;background:var(--gold-glass);color:var(--gold-light)"><?= icon('calendar',28) ?></span>
    <div style="flex:1;min-width:0">
      <span class="badge badge-gold" style="font-weight:900"><?= icon('sparkles',13) ?> سیستم پیش‌نویس هوشمند</span>
      <h2>جلسات مشاوره و کلاس‌های درسی</h2>
      <p>با سیستم پیش‌نویس، آزادی کامل دارید: ابتدا ذخیره کنید، ویرایش یا حذف کنید، در نهایت با یک کلیک تأیید کنید تا پیامک رسمی ارسال شود. هیچ پیامکی قبل از تأیید نهایی شما ارسال نمی‌شود.</p>
    </div>
  </div>
  <div class="sm-steps">
    <div class="sm-step"><span class="num">۱</span><span>پر کردن فرم</span></div>
    <div class="sm-step"><span class="num">۲</span><span>ذخیره پیش‌نویس</span></div>
    <div class="sm-step"><span class="num">۳</span><span>ویرایش یا حذف</span></div>
    <div class="sm-step"><span class="num">۴</span><span>تأیید نهایی + ارسال پیامک</span></div>
  </div>
</div>

<?php if ($editDraft): ?>
<div class="alert" style="background:var(--gold-glass);border:1px solid var(--gold);padding:14px 18px;border-radius:14px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <span class="icon-tile" style="background:var(--grad-gold);color:#1a1206"><?= icon('edit',18) ?></span>
  <div style="flex:1">
    <b>در حال ویرایش پیش‌نویس:</b>
    <span style="font-size:.92rem;color:var(--text-2)"><?= e($editDraft['title']) ?> · <?= jalali_date($editDraft['session_date']) ?> · <?= e($editDraft['session_time'] ? fa_num(substr($editDraft['session_time'], 0, 5)) : '—') ?></span>
  </div>
  <a href="admin/schedule_meeting.php" class="btn btn-ghost btn-sm"><?= icon('close',14) ?> انصراف از ویرایش</a>
</div>
<?php endif; ?>

<!-- ============== فرم + پیش‌نمایش ============== -->
<div class="sm-layout">
  <!-- FORM -->
  <div class="sm-form-panel">
    <h3><?= icon('plus',18) ?> <?= $editDraft ? 'ویرایش' : 'تنظیم' ?> جلسه/کلاس جدید</h3>
    <form method="post" id="meetingForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_draft">
      <?php if ($editDraft): ?>
        <input type="hidden" name="draft_group_id" value="<?= e($editDraft['draft_group_id']) ?>">
      <?php endif; ?>

      <!-- نوع جلسه -->
      <div class="sm-section">
        <div class="sm-section-label"><span class="ic">🎯</span> نوع جلسه</div>
        <div class="sm-type-toggle">
          <label class="<?= (!$editDraft || $editDraft['session_type']==='consultation') ? 'active' : '' ?>">
            <input type="radio" name="session_type" value="consultation" <?= (!$editDraft || $editDraft['session_type']==='consultation') ? 'checked' : '' ?> onchange="updatePreview()">
            <?= icon('user',15) ?> مشاوره (یک‌به‌یک)
          </label>
          <label class="<?= ($editDraft && $editDraft['session_type']==='class') ? 'active' : '' ?>">
            <input type="radio" name="session_type" value="class" <?= ($editDraft && $editDraft['session_type']==='class') ? 'checked' : '' ?> onchange="updatePreview()">
            <?= icon('users',15) ?> کلاس (گروهی)
          </label>
        </div>
      </div>

      <!-- دانش‌آموز -->
      <div class="sm-section">
        <div class="sm-section-label"><span class="ic">👤</span> دانش‌آموز(ها) هدف</div>

        <!-- تک‌دانش‌آموز -->
        <select class="select" name="student_id" id="singleStudentSelect" onchange="updatePreview()" style="display:none;height:46px">
          <?php foreach ($students as $s): ?>
            <option value="<?= (int)$s['id'] ?>" data-phone="<?= !empty($s['phone']) ? '1' : '0' ?>"
              <?= ($editDraft && $editDraft['session_type']==='consultation' && count($editDraft['students'])===1 && (int)$editDraft['students'][0]['id']===(int)$s['id']) ? 'selected' : '' ?>>
              <?= e($s['full_name']) ?><?= !empty($s['phone']) ? ' 📱' : ' ⚠️' ?>
            </option>
          <?php endforeach; ?>
        </select>

        <!-- چند‌دانش‌آموز -->
        <div class="sm-students-list" id="multiStudentsList" style="display:none">
          <?php
            $selectedIds = [];
            if ($editDraft && $editDraft['session_type']==='class') {
              $selectedIds = array_column($editDraft['students'], 'id');
            }
            foreach ($students as $s):
              $isChecked = in_array((int)$s['id'], $selectedIds, true);
          ?>
            <label class="sm-student-item <?= $isChecked ? 'checked' : '' ?>" data-phone="<?= !empty($s['phone']) ? '1' : '0' ?>">
              <input type="checkbox" name="student_ids[]" value="<?= (int)$s['id'] ?>" <?= $isChecked ? 'checked' : '' ?> onchange="updatePreview(); updateStudentItem(this)">
              <span class="nm"><?= e($s['full_name']) ?></span>
              <span class="badge"><?= e($s['field'] ?: '—') ?></span>
              <?php if (!empty($s['phone'])): ?>
                <span class="ph">📱</span>
              <?php else: ?>
                <span class="ph ph-miss">⚠️ بدون موبایل</span>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- تاریخ و ساعت -->
      <div class="sm-section">
        <div class="sm-section-label"><span class="ic">📅</span> تاریخ و ساعت</div>
        <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
          <div class="field" style="margin:0">
            <label for="session_date">📅 تاریخ <span style="color:var(--danger)">*</span></label>
            <input class="input" type="date" name="session_date" id="session_date" required
              value="<?= e($editDraft['session_date'] ?? date('Y-m-d')) ?>" onchange="updatePreview()" style="height:46px">
          </div>
          <div class="field" style="margin:0">
            <label for="session_time">🕐 ساعت شروع <span style="color:var(--danger)">*</span></label>
            <input class="input" type="time" name="session_time" id="session_time" required
              value="<?= e($editDraft['session_time'] ?? '') ?>" onchange="updatePreview()" style="height:46px">
          </div>
        </div>
        <p class="help muted" style="font-size:.78rem;margin-top:8px">
          <?= icon('info',13) ?> تاریخ و ساعت برای ثبت جلسه الزامی است و همان زمان در پیامک ارسال می‌شود.
        </p>
      </div>

      <!-- عنوان اختیاری -->
      <div class="sm-section">
        <div class="sm-section-label"><span class="ic">📝</span> عنوان <span class="muted" style="font-weight:600">(اختیاری)</span></div>
        <input class="input" name="title" id="title" placeholder="اگر خالی بذارید، خودکار 'جلسه مشاوره' یا 'کلاس درسی' تنظیم می‌شود"
          value="<?= e($editDraft['title'] ?? '') ?>" oninput="updatePreview()" style="height:46px">
      </div>

      <!-- توضیحات اختیاری -->
      <div class="sm-section">
        <div class="sm-section-label"><span class="ic">📋</span> توضیحات و بستر برگزاری <span class="muted" style="font-weight:600">(اختیاری)</span></div>
        <textarea class="input" name="notes" rows="3" placeholder="مثلاً: لینگ گوگل‌میت، کلاس آنلاین، آدرس حضوری..." style="min-height:80px"><?= e($editDraft['notes'] ?? '') ?></textarea>
      </div>

      <!-- Actions -->
      <div class="sm-actions">
        <button type="submit" class="btn btn-gold btn-lg" id="saveBtn">
          <?= icon($editDraft?'check':'save',18) ?> <?= $editDraft ? 'به‌روزرسانی پیش‌نویس' : 'ذخیره پیش‌نویس' ?>
        </button>
        <?php if ($editDraft): ?>
          <a href="admin/schedule_meeting.php" class="btn btn-ghost btn-lg"><?= icon('close',15) ?> انصراف</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- SMS PREVIEW -->
  <div class="sm-preview-panel">
    <h3><?= icon('phone',18) ?> پیش‌نمایش پیامک</h3>
    <p class="muted" style="font-size:.82rem;margin-bottom:14px">
      <?= icon('eye',13) ?> این متن قبل از تأیید نهایی برای دانش‌آموزان ارسال می‌شود. تا زمانی که دکمه‌ی «تأیید نهایی» را نزنید، هیچ پیامکی ارسال نمی‌شود.
    </p>
    <div class="phone-preview">
      <div class="pp-head">
        <span><?= icon('signal',12) ?> سرویس پیامک مَدار</span>
        <span><?= icon('check',12) ?> آماده ارسال</span>
      </div>
      <div class="pp-bubble" id="smsPreview">
        <!-- پیش‌نمایش زنده اینجا -->
      </div>
      <div class="pp-meta">
        <span><span class="pp-status"><span id="smsStatus">۰</span> دانش‌آموز</span>
        <span id="smsTime">—:—</span>
      </div>
    </div>
    <div style="margin-top:14px;padding:10px 12px;background:var(--sage-glass);border:1px solid rgba(107,136,114,.3);border-radius:12px;font-size:.8rem;color:var(--sage-light);display:flex;align-items:center;gap:8px">
      <?= icon('shield-check',16) ?>
      <span>پیامک فقط پس از تأیید نهایی شما ارسال می‌شود. شما کنترل کامل دارید.</span>
    </div>
  </div>
</div>

<!-- ============== پیش‌نویس‌ها ============== -->
<?php if ($drafts): ?>
<div class="panel" style="background:linear-gradient(160deg,var(--card),var(--surface));border:1px solid rgba(178,148,95,.3);padding:24px;margin-bottom:24px">
  <div class="between wrap gap-3 mb-4" style="align-items:center">
    <h3 style="display:flex;align-items:center;gap:10px;font-size:1.15rem;color:var(--gold-light);margin:0">
      <?= icon('edit',20) ?> پیش‌نویس‌های در انتظار تایید
      <span class="badge badge-gold"><?= fa_num(count($drafts)) ?> مورد</span>
    </h3>
    <span class="muted" style="font-size:.82rem">ذخیره شده ولی هنوز پیامک ارسال نشده</span>
  </div>

  <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(380px,1fr))">
    <?php foreach ($drafts as $d):
      $isClass = $d['session_type'] === 'class';
      $studentCount = $d['total_students'];
      $hasPhoneCount = $d['has_phone_count'];
      $willSend = $hasPhoneCount;
      $wontSend = $studentCount - $hasPhoneCount;
    ?>
    <div class="draft-card">
      <div class="dc-badge">پیش‌نویس</div>

      <div class="dc-top">
        <div style="flex:1;min-width:0">
          <div class="dc-title">
            <?= icon($isClass?'users':'user',16) ?>
            <?= e($d['title']) ?>
            <span class="badge <?= $isClass?'badge-sage':'' ?>" style="font-size:.7rem">
              <?= $isClass?'کلاس':'مشاوره' ?>
            </span>
          </div>
          <div class="dc-meta">
            <span><?= icon('calendar',13) ?> <?= jalali_date($d['session_date']) ?></span>
            <span><?= icon('clock',13) ?> <?= $d['session_time'] ? fa_num(substr($d['session_time'], 0, 5)) : '—' ?></span>
          </div>
        </div>
      </div>

      <div class="dc-students">
        <b><?= icon('users',14) ?> <?= fa_num($studentCount) ?> دانش‌آموز:</b>
        <?= e(implode('، ', array_slice(array_column($d['students'], 'name'), 0, 4))) ?>
        <?php if ($studentCount > 4): ?>
          <span class="muted">و <?= fa_num($studentCount - 4) ?> نفر دیگر</span>
        <?php endif; ?>
        <div style="margin-top:6px;font-size:.78rem;display:flex;gap:10px;flex-wrap:wrap">
          <span class="badge badge-sage">📱 <?= fa_num($willSend) ?> پیامک ارسال می‌شود</span>
          <?php if ($wontSend > 0): ?>
            <span class="badge" style="background:rgba(217,178,95,.14);color:var(--warn);border:1px solid rgba(217,178,95,.3)">⚠️ <?= fa_num($wontSend) ?> بدون موبایل</span>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($d['notes'])): ?>
      <div style="padding:8px 10px;background:var(--surface-2);border-radius:10px;font-size:.82rem;color:var(--text-2);margin-bottom:8px">
        <?= icon('note',13) ?> <?= e(mb_substr($d['notes'], 0, 80)) ?><?= mb_strlen($d['notes']) > 80 ? '…' : '' ?>
      </div>
      <?php endif; ?>

      <div class="dc-actions">
        <form method="post" style="flex:2" onsubmit="return confirm('تأیید و ارسال پیامک؟')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="confirm">
          <input type="hidden" name="draft_group_id" value="<?= e($d['draft_group_id']) ?>">
          <button type="submit" class="btn btn-gold" style="width:100%;background:var(--grad-sage);color:#0c1512">
            <?= icon('send',16) ?> تأیید نهایی و ارسال پیامک
          </button>
        </form>
        <a href="?edit=<?= e($d['draft_group_id']) ?>" class="btn btn-ghost" style="flex:1;justify-content:center">
          <?= icon('edit',15) ?> ویرایش
        </a>
        <form method="post" onsubmit="return confirm('حذف این پیش‌نویس؟')" style="flex:0 0 auto">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_draft">
          <input type="hidden" name="draft_group_id" value="<?= e($d['draft_group_id']) ?>">
          <button type="submit" class="btn btn-ghost btn-icon" style="color:var(--danger);width:46px;height:46px;border-radius:14px;padding:0" data-tip="حذف پیش‌نویس">
            <?= icon('trash',16) ?>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ============== جلسات تأییدشده ============== -->
<div class="panel" style="background:var(--card);border:1px solid var(--border-soft);padding:24px">
  <div class="between wrap gap-3 mb-4" style="align-items:center">
    <h3 style="display:flex;align-items:center;gap:10px;font-size:1.15rem;color:var(--sage-light);margin:0">
      <?= icon('check-circle',20) ?> جلسات تأییدشده
      <span class="badge badge-sage"><?= fa_num(count($scheduledSessions)) ?> مورد</span>
    </h3>
    <span class="muted" style="font-size:.82rem">ارسال پیامک انجام شده</span>
  </div>

  <?php if (empty($scheduledSessions)): ?>
    <div class="empty-state">
      <div class="es-ico"><?= icon('calendar',36) ?></div>
      <p>هنوز جلسه‌ی تأییدشده‌ای ندارید</p>
      <p class="muted" style="font-size:.86rem;margin-top:6px">پیش‌نویس‌هایی که تأیید کنید در اینجا نمایش داده می‌شوند.</p>
    </div>
  <?php else: ?>
    <div class="sessions-list">
      <?php foreach ($scheduledSessions as $s):
        $isToday = $s['session_date'] === date('Y-m-d');
        $isPast = $s['session_date'] < date('Y-m-d');
        $isClass = ($s['session_type'] ?? 'consultation') === 'class';
        $hasPhone = !empty($s['student_phone']);
      ?>
      <div class="session-row <?= $isToday ? 'is-today' : '' ?>" style="<?= $s['status']==='cancelled' ? 'opacity:.5' : '' ?>">
        <?php if($isToday && $s['status']==='scheduled'): ?>
          <div style="position:absolute;top:-1px;left:14px;background:var(--gold);color:#111;font-size:10px;font-weight:1000;padding:3px 12px;border-radius:0 0 10px 10px;box-shadow:0 4px 10px rgba(178,148,95,.25)">
            امروز 🔥
          </div>
        <?php endif; ?>

        <div class="sr-info">
          <div class="sr-title">
            <b><?= e($s['title']) ?></b>
            <span class="badge <?= $isClass ? 'badge-sage' : 'badge-gold' ?>" style="font-size:.7rem">
              <?= $isClass ? icon('users',11).' کلاس' : icon('user',11).' مشاوره' ?>
            </span>
            <span class="badge badge-sage" style="font-size:.7rem"><?= e($s['student_name']) ?></span>
            <?php
              $smsStatus = $s['sms_status'] ?? null;
              $smsLabel = !$hasPhone ? 'بدون موبایل' : (!$smsStatus ? 'ثبت نشده' : ($smsStatus === 'sent' ? 'پیامک ارسال شد' : 'خطای پیامک'));
              $smsBg = !$hasPhone ? 'rgba(217,178,95,.14)' : ($smsStatus === 'sent' ? 'rgba(95,174,123,.16)' : 'rgba(217,116,116,.14)');
              $smsColor = !$hasPhone ? 'var(--warn)' : ($smsStatus === 'sent' ? 'var(--sage-light)' : 'var(--danger)');
            ?>
            <span class="badge" title="<?= e($s['sms_error'] ?: $smsLabel) ?>" style="font-size:.7rem;background:<?= $smsBg ?>;color:<?= $smsColor ?>;border:1px solid currentColor">
              <?= $smsStatus === 'sent' ? '✓' : ($hasPhone ? '!' : '—') ?> <?= e($smsLabel) ?>
            </span>
            <span class="badge" style="font-size:.7rem;border:none;background:<?= $s['status']==='scheduled'?($isPast?'rgba(255,255,255,.08)':'rgba(46, 68, 56, 0.2)'):'rgba(220, 53, 69, 0.15)' ?>;color:<?= $s['status']==='scheduled'?($isPast?'#8e9c96':'#9fc7a8'):'#ea868f' ?>">
              <?= $s['status']==='scheduled'?($isPast?'برگزار شده':'تأیید شده'):'لغو شده' ?>
            </span>
          </div>
          <div class="sr-meta">
            <span><?= icon('calendar',13) ?> <?= jalali_date($s['session_date']) ?></span>
            <span><?= icon('clock',13) ?> <?= $s['session_time'] ? fa_num(substr((string)$s['session_time'], 0, 5)) : '—' ?></span>
            <?php if(!empty($s['notes'])): ?>
              <span><?= icon('note',13) ?> <?= e(mb_substr($s['notes'], 0, 50)) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <?php if($s['status'] === 'scheduled' && !$isPast): ?>
          <a href="?cancel=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--danger);border-color:rgba(220, 53, 69, .25);font-weight:800" onclick="return confirm('لغو این جلسه؟')">
            لغو
          </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
const STUDENTS_DATA = <?= json_encode(array_map(fn($s)=>[
    'id' => (int)$s['id'],
    'name' => $s['full_name'],
    'phone' => !empty($s['phone']),
], $students), JSON_UNESCAPED_UNICODE) ?>;

function toPersianDigits(str){
  const fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
  return String(str).replace(/\d/g, d => fa[d]);
}

function formatPersianDate(iso){
  if(!iso) return '—';
  try {
    const d = new Date(iso);
    const months = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    const gy = d.getFullYear();
    const gd = d.getDate();
    const gm = d.getMonth();
    // تبدیل ساده میلادی به شمسی (تقریبی - 22 مارس اول فروردین)
    // برای دقت بیشتر از Intl استفاده می‌کنیم
    const fmt = new Intl.DateTimeFormat('fa-IR', {year:'numeric',month:'long',day:'numeric'});
    return fmt.format(d);
  } catch(e){ return iso; }
}

function formatTime(time){
  if(!time) return '—';
  const parts = time.split(':');
  return toPersianDigits(parts[0] + ':' + parts[1]);
}

function buildSmsMessage(){
  const date = document.getElementById('session_date')?.value || '';
  const time = document.getElementById('session_time')?.value || '';

  const persianDate = formatPersianDate(date);
  const persianTime = time ? formatTime(time) : '—';

  return 'با سلام و احترام،\n\n'
    + 'جلسه‌ی مشاوره شما در سامانه مَدار تنظیم شد.\n\n'
    + '📅 تاریخ: ' + persianDate + '\n'
    + '🕐 ساعت: ' + persianTime + '\n\n'
    + 'لطفاً پنل کاربری خود را در مَدار بررسی فرمایید.\n\n'
    + 'madaar-edu.ir';
}

function updatePreview(){
  const msg = buildSmsMessage();
  const preview = document.getElementById('smsPreview');
  if(preview){
    preview.innerHTML = msg
      .replace(/📅/g,'<span class="emoji">📅</span>')
      .replace(/🕐/g,'<span class="emoji">🕐</span>')
      .replace(/\n/g,'<br>');
  }

  // شمارنده دانش‌آموزان
  const type = document.querySelector('input[name="session_type"]:checked')?.value || 'consultation';
  let count = 0;
  if(type === 'consultation'){
    count = document.getElementById('singleStudentSelect')?.value ? 1 : 0;
  } else {
    count = document.querySelectorAll('#multiStudentsList input[type="checkbox"]:checked').length;
  }
  const statusEl = document.getElementById('smsStatus');
  if(statusEl) statusEl.textContent = toPersianDigits(count);

  // زمان
  const time = document.getElementById('session_time')?.value || '';
  const timeEl = document.getElementById('smsTime');
  if(timeEl) timeEl.textContent = time ? formatTime(time) : '—:—';
}

function updateStudentItem(checkbox){
  if(checkbox.checked){
    checkbox.closest('.sm-student-item')?.classList.add('checked');
  } else {
    checkbox.closest('.sm-student-item')?.classList.remove('checked');
  }
}

// تغییر بین تک‌دانش‌آموز و چند‌دانش‌آموز
function syncStudentMode(){
  const type = document.querySelector('input[name="session_type"]:checked')?.value || 'consultation';
  const singleSelect = document.getElementById('singleStudentSelect');
  const multiList = document.getElementById('multiStudentsList');

  if(type === 'class'){
    singleSelect.style.display = 'none';
    singleSelect.removeAttribute('required');
    multiList.style.display = 'block';
  } else {
    singleSelect.style.display = 'block';
    singleSelect.setAttribute('required','required');
    multiList.style.display = 'none';
  }
  updatePreview();
}

document.querySelectorAll('input[name="session_type"]').forEach(r => {
  r.addEventListener('change', syncStudentMode);
});

// Initial preview
setTimeout(updatePreview, 100);
syncStudentMode();

// جلوگیری از ارسال فرم بدون انتخاب دانش‌آموز
document.getElementById('meetingForm')?.addEventListener('submit', function(e){
  const type = document.querySelector('input[name="session_type"]:checked')?.value || 'consultation';
  const date = document.getElementById('session_date')?.value;
  const time = document.getElementById('session_time')?.value;

  if(!date){ e.preventDefault(); toast('تاریخ الزامی است', 'error'); return false; }
  if(!time){ e.preventDefault(); toast('ساعت شروع الزامی است', 'error'); return false; }

  if(type === 'consultation'){
    const val = document.getElementById('singleStudentSelect')?.value;
    if(!val){ e.preventDefault(); toast('یک دانش‌آموز انتخاب کنید', 'error'); return false; }
  } else {
    const count = document.querySelectorAll('#multiStudentsList input[type="checkbox"]:checked').length;
    if(count === 0){ e.preventDefault(); toast('حداقل یک دانش‌آموز انتخاب کنید', 'error'); return false; }

    const noPhone = Array.from(document.querySelectorAll('#multiStudentsList input[type="checkbox"]:checked'))
      .filter(cb => cb.closest('.sm-student-item').dataset.phone === '0').length;
    if(noPhone > 0 && !confirm(noPhone + ' دانش‌آموز شماره موبایل ندارند و پیامکی دریافت نخواهند کرد.\nادامه می‌دهید؟')){
      e.preventDefault();
      return false;
    }
  }
});
</script>
<?php panel_end(); ?>
