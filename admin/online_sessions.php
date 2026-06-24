<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/online_sessions.php';
boot_session();
require_role('advisor', 'admin');
$u = current_user();

online_sessions_schema_ready();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ایجاد جلسه
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $date = trim((string)($_POST['date'] ?? ''));
        $time = trim((string)($_POST['time'] ?? ''));
        $duration = (int)($_POST['duration'] ?? 60);
        $maxP = (int)($_POST['max_participants'] ?? 6);
        $studentIds = $_POST['student_ids'] ?? [];
        if (!is_array($studentIds)) $studentIds = [];

        $perms = [
            'mic' => (int)($_POST['allow_mic'] ?? 0),
            'cam' => (int)($_POST['allow_cam'] ?? 0),
            'screen' => (int)($_POST['allow_screen'] ?? 0),
            'whiteboard' => (int)($_POST['allow_whiteboard'] ?? 0),
            'chat' => (int)($_POST['allow_chat'] ?? 1),
        ];

        if (!$title) throw new RuntimeException('عنوان جلسه الزامی است.');
        if (empty($studentIds)) throw new RuntimeException('حداقل یک دانش‌آموز انتخاب کنید.');

        $scheduledAt = null;
        if ($date && $time) {
            $scheduledAt = $date . ' ' . $time . ':00';
        } elseif ($date) {
            $scheduledAt = $date . ' 20:00:00';
        }

        $sid = online_session_create((int)$u['id'], $title, $description, $scheduledAt, $duration, $maxP, $studentIds, $perms);
        flash('success', '🎥 جلسه آنلاین ایجاد شد. ' . count($studentIds) . ' دانش‌آموز دعوت شدند.');
        redirect('admin/online_sessions.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

// حذف جلسه
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (online_session_delete($sessionId, (int)$u['id'])) {
        flash('success', 'جلسه آنلاین حذف شد.');
    } else {
        flash('error', 'خطا در حذف.');
    }
    redirect('admin/online_sessions.php');
}

// شروع جلسه
if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (online_session_start($sessionId, (int)$u['id'])) {
        flash('success', 'جلسه شروع شد! اکنون می‌توانید وارد اتاق شوید.');
        redirect('online_room.php?session=' . $sessionId);
    }
}

// پایان جلسه
if ($action === 'end' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (online_session_end($sessionId, (int)$u['id'])) {
        flash('success', 'جلسه پایان یافت.');
    }
    redirect('admin/online_sessions.php');
}

// لغو جلسه
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $sessionId = (int)($_POST['session_id'] ?? 0);
    if (online_session_cancel($sessionId, (int)$u['id'])) {
        flash('success', 'جلسه لغو شد.');
    }
    redirect('admin/online_sessions.php');
}

$sessions = online_sessions_for_advisor((int)$u['id']);
$students = advisor_students((int)$u['id'], 'active');

panel_start('جلسات آنلاین', 'سیستم برگزاری جلسات تصویری با تخته و چت - نسخه‌ی بتا', 'admin', 'online_sessions', ['student.css']);
?>

<style>
.os-hero{background:radial-gradient(circle at 12% 0%,rgba(107,155,192,.20),transparent 38%),linear-gradient(135deg,rgba(111,155,192,.16),rgba(12,21,18,.4));border:1px solid rgba(111,155,192,.3);border-radius:var(--r-xl);padding:26px;margin-bottom:24px;box-shadow:0 12px 36px rgba(0,0,0,.3)}
.os-hero h2{font-size:1.45rem;color:#a0d2eb;font-weight:1000;margin:8px 0 6px}
.os-hero p{color:var(--text-2);font-size:.94rem;line-height:1.85;max-width:800px;margin:0}
.os-features{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.os-feature{display:flex;align-items:center;gap:8px;padding:8px 14px;background:rgba(111,155,192,.08);border:1px solid rgba(111,155,192,.2);border-radius:14px;font-size:.84rem;font-weight:800;color:var(--text-2)}
.os-feature .ic{color:#a0d2eb}

.os-form{background:linear-gradient(160deg,var(--card),var(--surface));border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:24px;margin-bottom:24px}
.os-form h3{display:flex;align-items:center;gap:10px;font-size:1.1rem;font-weight:900;color:var(--gold-light);margin-bottom:18px}
.os-section{margin-bottom:18px}
.os-section-label{display:flex;align-items:center;gap:8px;font-size:.78rem;font-weight:900;color:var(--text-2);margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em}

.os-perms{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
.os-perm{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:14px;cursor:pointer;transition:.2s;font-size:.86rem}
.os-perm:hover{border-color:var(--sage)}
.os-perm input{accent-color:var(--gold);width:18px;height:18px;flex-shrink:0}
.os-perm label{cursor:pointer;flex:1;font-weight:700}

.os-students-pick{background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--r-md);padding:8px;max-height:240px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px}
.os-student-pick{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;cursor:pointer;transition:.15s;background:rgba(0,0,0,.1);font-size:.84rem}
.os-student-pick:hover{background:var(--surface-3)}
.os-student-pick.checked{background:var(--gold-glass);border:1px solid rgba(203,172,128,.3)}
.os-student-pick input{accent-color:var(--gold);width:16px;height:16px}
.os-student-pick .nm{font-weight:700;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.os-student-pick .badge{font-size:.65rem;padding:1px 5px}

.os-session-card{background:linear-gradient(160deg,var(--card),var(--surface));border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:18px;position:relative;transition:.2s}
.os-session-card:hover{border-color:var(--border);transform:translateY(-2px)}
.os-session-card.is-live{border-color:var(--success);background:linear-gradient(160deg,rgba(95,174,123,.08),var(--surface))}
.os-session-card.is-scheduled{border-color:rgba(111,155,192,.3)}
.os-session-card.is-ended{opacity:.7}

.os-sc-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:12px}
.os-sc-icon{width:48px;height:48px;border-radius:14px;background:var(--info);color:#0c1512;display:grid;place-items:center;flex-shrink:0;font-size:1.3rem}
.os-sc-info{flex:1;min-width:0}
.os-sc-title{font-weight:900;font-size:1rem;color:var(--text-1);margin-bottom:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.os-sc-meta{font-size:.82rem;color:var(--text-3);display:flex;flex-wrap:wrap;gap:12px}
.os-sc-meta span{display:inline-flex;align-items:center;gap:5px}
.os-sc-participants{margin:10px 0;padding:8px 10px;background:var(--surface-2);border-radius:10px;font-size:.82rem}
.os-sc-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft)}
.os-sc-actions .btn{flex:1;min-height:42px;font-weight:800;font-size:.85rem;justify-content:center}

.os-status-badge{position:absolute;top:-10px;right:14px;font-size:.7rem;font-weight:1000;padding:4px 12px;border-radius:99px;letter-spacing:.04em}
.os-status-live{background:linear-gradient(135deg,#5fae7b,#3d8a5c);color:#07120b;animation:pulse 2s infinite}
.os-status-scheduled{background:rgba(111,155,192,.2);color:#a0d2eb;border:1px solid rgba(111,155,192,.4)}
.os-status-ended{background:rgba(127,141,134,.18);color:var(--text-3);border:1px solid var(--border-soft)}
.os-status-cancelled{background:rgba(217,116,116,.18);color:var(--danger);border:1px solid rgba(217,116,116,.4)}
.os-status-draft{background:linear-gradient(135deg,#cbac80,#b2945f);color:#1a1206}

.os-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:16px}
@media(max-width:430px){.os-grid{grid-template-columns:1fr}}
</style>

<div class="os-hero">
  <div style="display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap">
    <span class="icon-tile" style="width:60px;height:60px;font-size:1.6rem;border-radius:18px;background:var(--info);color:#0c1512"><?= icon('video',30) ?></span>
    <div style="flex:1;min-width:0">
      <span class="badge" style="background:rgba(111,155,192,.2);color:#a0d2eb;border:1px solid rgba(111,155,192,.3);font-weight:900;display:inline-flex;align-items:center;gap:6px">
        <?= icon('sparkles',13) ?> نسخه‌ی بتا
      </span>
      <h2>جلسات آنلاین مَدار</h2>
      <p>سیستم برگزاری جلسات تصویری با امکانات کامل: اشتراک صفحه، تخته سفید تعاملی، چت زنده، دست بلند کردن و واکنش‌ها. ظرفیت حداکثر ۶ نفر. رابط کاربری فارسی، ریسپانسیو و سریع.</p>
    </div>
  </div>
  <div class="os-features">
    <div class="os-feature"><span class="ic">📹</span> تصویر و صدا با کیفیت بالا</div>
    <div class="os-feature"><span class="ic">🖥️</span> اشتراک صفحه نمایش</div>
    <div class="os-feature"><span class="ic">✏️</span> تخته سفید تعاملی</div>
    <div class="os-feature"><span class="ic">💬</span> چت زنده فارسی</div>
    <div class="os-feature"><span class="ic">✋</span> دست بلند کردن</div>
    <div class="os-feature"><span class="ic">👏</span> واکنش‌های زنده</div>
  </div>
</div>

<!-- ============== فرم ساخت جلسه ============== -->
<div class="os-form">
  <h3><?= icon('plus',18) ?> ایجاد جلسه آنلاین جدید</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div class="os-section">
      <div class="os-section-label">📋 اطلاعات پایه</div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="field" style="margin:0">
          <label>📌 عنوان جلسه <span style="color:var(--danger)">*</span></label>
          <input class="input" name="title" required placeholder="مثلاً: کلاس ریاضی - حل تست‌های کنکور" style="height:46px">
        </div>
        <div class="field" style="margin:0">
          <label>📝 توضیحات کوتاه</label>
          <input class="input" name="description" placeholder="اختیاری" style="height:46px">
        </div>
      </div>
    </div>

    <div class="os-section">
      <div class="os-section-label">📅 زمان‌بندی</div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr 1fr 1fr">
        <div class="field" style="margin:0">
          <label>📅 تاریخ</label>
          <input class="input" type="date" name="date" style="height:46px">
        </div>
        <div class="field" style="margin:0">
          <label>🕐 ساعت شروع</label>
          <input class="input" type="time" name="time" style="height:46px">
        </div>
        <div class="field" style="margin:0">
          <label>⏱ مدت (دقیقه)</label>
          <input class="input" type="number" name="duration" value="60" min="15" max="480" style="height:46px">
        </div>
        <div class="field" style="margin:0">
          <label>👥 حداکثر شرکت‌کننده</label>
          <input class="input" type="number" name="max_participants" value="6" min="2" max="6" style="height:46px" readonly>
          <p class="help muted" style="font-size:.74rem;margin-top:4px">حداکثر ۶ نفر (محدودیت سیستم)</p>
        </div>
      </div>
    </div>

    <div class="os-section">
      <div class="os-section-label">👥 دانش‌آموزان دعوت‌شده <span style="color:var(--danger)">*</span></div>
      <?php if (!$students): ?>
        <div class="empty-state" style="padding:20px">هیچ دانش‌آموز فعالی ندارید.</div>
      <?php else: ?>
        <div class="os-students-pick">
          <?php foreach ($students as $s): ?>
            <label class="os-student-pick">
              <input type="checkbox" name="student_ids[]" value="<?= (int)$s['id'] ?>" data-phone="<?= !empty($s['phone']) ? '1' : '0' ?>">
              <span class="nm"><?= e($s['full_name']) ?></span>
              <span class="badge"><?= e($s['field'] ?: '—') ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="os-section">
      <div class="os-section-label">⚙️ دسترسی‌های دانش‌آموزان</div>
      <div class="os-perms">
        <label class="os-perm">
          <input type="checkbox" name="allow_mic" value="1">
          <span>🎤 میکروفون</span>
        </label>
        <label class="os-perm">
          <input type="checkbox" name="allow_cam" value="1">
          <span>📹 دوربین</span>
        </label>
        <label class="os-perm">
          <input type="checkbox" name="allow_screen" value="1">
          <span>🖥️ اشتراک صفحه</span>
        </label>
        <label class="os-perm">
          <input type="checkbox" name="allow_whiteboard" value="1">
          <span>✏️ تخته سفید</span>
        </label>
        <label class="os-perm">
          <input type="checkbox" name="allow_chat" value="1" checked>
          <span>💬 چت</span>
        </label>
      </div>
    </div>

    <div class="os-section" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
      <button type="submit" class="btn btn-gold btn-lg" style="flex:1;min-height:52px;font-weight:900">
        <?= icon('video',18) ?> ایجاد جلسه و دعوت دانش‌آموزان
      </button>
    </div>
  </form>
</div>

<!-- ============== لیست جلسات ============== -->
<div class="panel" style="background:var(--card);border:1px solid var(--border-soft);padding:24px">
  <div class="between wrap gap-3 mb-4" style="align-items:center">
    <h3 style="display:flex;align-items:center;gap:10px;font-size:1.15rem;color:var(--info);margin:0">
      <?= icon('video',20) ?> جلسات آنلاین شما
      <span class="badge"><?= fa_num(count($sessions)) ?> مورد</span>
    </h3>
  </div>

  <?php if (empty($sessions)): ?>
    <div class="empty-state">
      <div class="es-ico"><?= icon('video',38) ?></div>
      <p>هنوز جلسه آنلاینی ندارید</p>
      <p class="muted" style="font-size:.86rem;margin-top:6px">با فرم بالا اولین جلسه آنلاین را ایجاد کنید.</p>
    </div>
  <?php else: ?>
    <div class="os-grid">
      <?php foreach ($sessions as $s):
        $status = $s['status'] ?? 'draft';
        $participantCount = (int)($s['participant_count'] ?? 0);
        $isLive = $status === 'live';
        $isScheduled = $status === 'scheduled';
        $isEnded = $status === 'ended';
        $isCancelled = $status === 'cancelled';

        $statusClass = [
          'live' => 'os-status-live',
          'scheduled' => 'os-status-scheduled',
          'ended' => 'os-status-ended',
          'cancelled' => 'os-status-cancelled',
          'draft' => 'os-status-draft',
        ][$status] ?? 'os-status-draft';

        $statusText = [
          'live' => '🔴 در حال برگزاری',
          'scheduled' => '📅 برنامه‌ریزی شده',
          'ended' => '✓ پایان یافته',
          'cancelled' => '✕ لغو شده',
          'draft' => '📝 پیش‌نویس',
        ][$status] ?? $status;
      ?>
        <div class="os-session-card is-<?= $status ?>">
          <div class="os-status-badge <?= $statusClass ?>"><?= $statusText ?></div>

          <div class="os-sc-head">
            <div class="os-sc-icon"><?= icon('video',24) ?></div>
            <div class="os-sc-info">
              <div class="os-sc-title">
                <span><?= e($s['title']) ?></span>
                <?php if ($status === 'live'): ?>
                  <span class="badge" style="background:var(--success);color:#0c1512"><?= icon('circle',11) ?> LIVE</span>
                <?php endif; ?>
              </div>
              <div class="os-sc-meta">
                <?php if ($s['scheduled_at']): ?>
                  <span><?= icon('calendar',13) ?> <?= jalali_date(date('Y-m-d', strtotime($s['scheduled_at']))) ?> · <?= fa_num(date('H:i', strtotime($s['scheduled_at']))) ?></span>
                <?php endif; ?>
                <span><?= icon('clock',13) ?> <?= fa_num($s['duration_min']) ?> دقیقه</span>
                <span><?= icon('users',13) ?> <?= fa_num($participantCount) ?> نفر</span>
              </div>
            </div>
          </div>

          <div class="os-sc-participants">
            <b style="color:var(--gold-light);font-size:.78rem"><?= icon('key',12) ?> کد جلسه:</b>
            <code style="background:var(--bg);padding:2px 8px;border-radius:6px;font-size:.78rem;color:var(--gold-light);margin-right:6px"><?= e($s['jitsi_room_name']) ?></code>
            <span class="muted" style="font-size:.74rem">(مخفی است، فقط شرکت‌کنندگان دعوت‌شده دسترسی دارند)</span>
          </div>

          <div class="os-sc-actions">
            <?php if ($status === 'live'): ?>
              <a href="<?= url('online_room.php?session='.(int)$s['id']) ?>" class="btn btn-gold" style="flex:2;background:var(--success);color:#0c1512">
                <?= icon('login',15) ?> ورود به اتاق
              </a>
              <form method="post" style="flex:1" onsubmit="return confirm('پایان جلسه؟')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="end">
                <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-block" style="color:var(--warn);border-color:rgba(217,178,95,.4)"><?= icon('stop',14) ?> پایان</button>
              </form>
            <?php elseif ($status === 'scheduled'): ?>
              <form method="post" style="flex:1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start">
                <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-gold btn-block"><?= icon('play',15) ?> شروع جلسه</button>
              </form>
              <form method="post" style="flex:1" onsubmit="return confirm('لغو این جلسه؟')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-block" style="color:var(--danger);border-color:rgba(217,116,116,.3)"><?= icon('close',14) ?> لغو</button>
              </form>
            <?php elseif ($status === 'ended'): ?>
              <a href="<?= url('online_room.php?session='.(int)$s['id']) ?>" class="btn btn-ghost btn-block" style="color:var(--text-3)">
                <?= icon('eye',14) ?> مشاهده گزارش
              </a>
              <form method="post" style="flex:0 0 auto" onsubmit="return confirm('حذف این جلسه؟')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-icon" style="width:42px;height:42px;color:var(--danger)" data-tip="حذف"><?= icon('trash',15) ?></button>
              </form>
            <?php elseif ($status === 'cancelled'): ?>
              <form method="post" style="flex:0 0 auto" onsubmit="return confirm('حذف این جلسه؟')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="session_id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-block" style="color:var(--danger)"><?= icon('trash',14) ?> حذف</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.os-student-pick input[type="checkbox"]').forEach(cb => {
  cb.addEventListener('change', function(){
    if(this.checked) this.closest('.os-student-pick').classList.add('checked');
    else this.closest('.os-student-pick').classList.remove('checked');
  });
});

document.querySelector('form[method="post"]')?.addEventListener('submit', function(e){
  const checked = document.querySelectorAll('.os-students-pick input[type="checkbox"]:checked').length;
  if(checked === 0){
    e.preventDefault();
    toast('حداقل یک دانش‌آموز انتخاب کنید', 'error');
    return false;
  }
  const title = document.querySelector('input[name="title"]')?.value?.trim();
  if(!title){
    e.preventDefault();
    toast('عنوان جلسه الزامی است', 'error');
    return false;
  }
});
</script>
<?php panel_end(); ?>
