<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/online_sessions.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/log.php';

boot_session();
require_role('advisor', 'admin');
$u = current_user();
$me = (int)$u['id'];

online_sessions_schema_ready();

function os_flash_redirect(string $type, string $msg, string $to = 'admin/online_sessions.php'): void {
    flash($type, $msg);
    redirect($to);
}

function os_owned_session(int $id, int $me, string $role): ?array {
    $s = online_session_get($id);
    if (!$s) return null;
    if ($role !== 'admin' && (int)$s['advisor_id'] !== $me) return null;
    return $s;
}

function os_post_student_ids(): array {
    $ids = $_POST['student_ids'] ?? [];
    if (!is_array($ids)) $ids = [];
    return array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));
}

function os_allowed_student_ids(int $advisorId): array {
    return array_map(fn($row) => (int)$row['id'], advisor_students($advisorId, 'active'));
}

function os_datetime_from_post(): ?string {
    $date = trim((string)($_POST['scheduled_date'] ?? ''));
    $time = trim((string)($_POST['scheduled_time'] ?? ''));
    if ($date === '' || $time === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) return null;
    return $date . ' ' . $time . ':00';
}

$action = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        if ($action === 'create' || $action === 'update') {
            $title = trim((string)($_POST['title'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $scheduledAt = os_datetime_from_post();
            $duration = max(15, min(240, (int)($_POST['duration_min'] ?? 60)));
            $maxParticipants = max(2, min(6, (int)($_POST['max_participants'] ?? 6)));
            $studentIds = os_post_student_ids();
            $allowedStudentIds = os_allowed_student_ids($me);
            $studentIds = array_values(array_intersect($studentIds, $allowedStudentIds));

            if ($title === '') throw new RuntimeException('عنوان کلاس را وارد کنید.');
            if (!$scheduledAt) throw new RuntimeException('تاریخ و ساعت کلاس را درست وارد کنید.');
            if (!$studentIds) throw new RuntimeException('حداقل یک دانش‌آموز را برای کلاس انتخاب کنید.');
            if (count($studentIds) > $maxParticipants) throw new RuntimeException('تعداد دانش‌آموزان از ظرفیت انتخاب‌شده بیشتر است.');

            // پیش‌فرض امن: دانش‌آموز برای میکروفون/دوربین/اشتراک صفحه/تخته باید اجازه بگیرد.
            $permissions = [
                'mic' => !empty($_POST['allow_student_mic']) ? 1 : 0,
                'cam' => !empty($_POST['allow_student_cam']) ? 1 : 0,
                'screen' => !empty($_POST['allow_screen_share']) ? 1 : 0,
                'whiteboard' => !empty($_POST['allow_whiteboard']) ? 1 : 0,
                'chat' => !empty($_POST['allow_chat']) ? 1 : 0,
            ];

            if ($action === 'create') {
                $newId = online_session_create($me, $title, $description ?: null, $scheduledAt, $duration, $maxParticipants, $studentIds, $permissions);
                if (!$newId) throw new RuntimeException('ساخت کلاس آنلاین ناموفق بود.');
                log_activity($me, 'online_session_created', 'online_session', $newId, ['عنوان' => $title, 'دانش‌آموزان' => count($studentIds)]);
                os_flash_redirect('success', 'کلاس آنلاین ساخته شد و برای دانش‌آموزان اعلان ارسال شد.');
            }

            $id = (int)($_POST['id'] ?? 0);
            $session = os_owned_session($id, $me, (string)$u['role']);
            if (!$session) throw new RuntimeException('کلاس آنلاین یافت نشد یا دسترسی ندارید.');
            if (in_array($session['status'], ['live','ended','cancelled'], true)) throw new RuntimeException('کلاس فعال، پایان‌یافته یا لغوشده قابل ویرایش نیست.');

            $ok = online_session_update($id, (int)$session['advisor_id'], [
                'title' => $title,
                'description' => $description,
                'scheduled_at' => $scheduledAt,
                'duration_min' => $duration,
                'max_participants' => $maxParticipants,
                'allow_student_mic' => $permissions['mic'],
                'allow_student_cam' => $permissions['cam'],
                'allow_screen_share' => $permissions['screen'],
                'allow_whiteboard' => $permissions['whiteboard'],
                'allow_chat' => $permissions['chat'],
            ]);
            online_session_update_participants($id, (int)$session['advisor_id'], $studentIds);
            log_activity($me, 'online_session_updated', 'online_session', $id, ['عنوان' => $title, 'دانش‌آموزان' => count($studentIds)]);
            os_flash_redirect($ok ? 'success' : 'info', 'تنظیمات کلاس آنلاین به‌روزرسانی شد.');
        }

        $id = (int)($_POST['id'] ?? 0);
        $session = os_owned_session($id, $me, (string)$u['role']);
        if (!$session) throw new RuntimeException('کلاس آنلاین یافت نشد یا دسترسی ندارید.');
        $ownerId = (int)$session['advisor_id'];

        if ($action === 'start') {
            if (!online_session_start($id, $ownerId)) throw new RuntimeException('شروع کلاس ناموفق بود.');
            log_activity($me, 'online_session_started', 'online_session', $id, ['عنوان' => $session['title']]);
            redirect('online_room.php?session=' . $id);
        } elseif ($action === 'end') {
            online_session_end($id, $ownerId);
            log_activity($me, 'online_session_ended', 'online_session', $id, ['عنوان' => $session['title']]);
            os_flash_redirect('success', 'کلاس برای همه پایان یافت.');
        } elseif ($action === 'cancel') {
            online_session_cancel($id, $ownerId);
            log_activity($me, 'online_session_cancelled', 'online_session', $id, ['عنوان' => $session['title']]);
            os_flash_redirect('success', 'کلاس آنلاین لغو شد.');
        } elseif ($action === 'delete') {
            online_session_delete($id, $ownerId);
            log_activity($me, 'online_session_deleted', 'online_session', $id, ['عنوان' => $session['title']]);
            os_flash_redirect('success', 'کلاس آنلاین حذف شد.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

$students = advisor_students($me, 'active');
$sessions = online_sessions_for_advisor($me);

$editId = (int)($_GET['edit'] ?? 0);
$editSession = $editId ? os_owned_session($editId, $me, (string)$u['role']) : null;
$editParticipants = [];
if ($editSession) {
    $editParticipants = array_map(fn($p) => (int)$p['student_id'], online_session_participants((int)$editSession['id']));
}

$stats = ['scheduled'=>0,'live'=>0,'ended'=>0,'cancelled'=>0];
foreach ($sessions as $s) if (isset($stats[$s['status']])) $stats[$s['status']]++;

$formDate = date('Y-m-d');
$formTime = date('H:i', strtotime('+30 minutes'));
if ($editSession && !empty($editSession['scheduled_at'])) {
    $formDate = substr((string)$editSession['scheduled_at'], 0, 10);
    $formTime = substr((string)$editSession['scheduled_at'], 11, 5);
}

panel_start('جلسات آنلاین', 'کلاس سریع، رایگان و کنترل‌شده با P2P داخلی + مسیر جایگزین', 'admin', 'online_sessions', ['student.css']);
?>
<style>
.os-hero{position:relative;overflow:hidden;margin-bottom:20px;border-radius:28px;border:1px solid rgba(110,231,160,.24);background:radial-gradient(circle at 12% 0%,rgba(110,231,160,.18),transparent 38%),linear-gradient(135deg,rgba(16,28,23,.96),rgba(7,13,11,.98));box-shadow:0 24px 80px rgba(0,0,0,.32);padding:24px}.os-hero h2{margin:8px 0 8px;font-size:1.45rem;font-weight:1000;color:#dfffe8}.os-hero p{max-width:850px;color:var(--text-2);line-height:2;margin:0}.os-badges{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.os-badges span{padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.08);font-size:.78rem;font-weight:900;color:var(--text-2)}.os-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:20px}.os-stat{background:var(--card);border:1px solid var(--border-soft);border-radius:18px;padding:14px}.os-stat b{font-size:1.45rem;color:var(--gold-light)}.os-stat span{display:block;font-size:.78rem;color:var(--text-3);font-weight:900;margin-top:3px}.os-layout{display:grid;grid-template-columns:minmax(0,.9fr) minmax(360px,1.1fr);gap:18px;align-items:start}.os-panel{background:linear-gradient(160deg,var(--card),var(--surface));border:1px solid var(--border-soft);border-radius:24px;padding:20px;box-shadow:0 14px 44px rgba(0,0,0,.22)}.os-panel h3{margin:0 0 14px;display:flex;align-items:center;gap:9px;font-size:1.05rem;color:var(--gold-light)}.os-student-list{max-height:290px;overflow:auto;display:grid;gap:7px;background:rgba(255,255,255,.025);border:1px solid var(--border-soft);border-radius:16px;padding:8px}.os-student{display:flex;align-items:center;gap:9px;padding:9px;border-radius:13px;border:1px solid transparent;background:rgba(255,255,255,.03);cursor:pointer}.os-student:hover,.os-student:has(input:checked){border-color:rgba(110,231,160,.28);background:rgba(110,231,160,.08)}.os-student input{width:17px;height:17px;accent-color:#6ee7a0}.os-student .nm{flex:1;font-weight:850;font-size:.88rem}.os-perms{display:grid;grid-template-columns:1fr 1fr;gap:8px}.os-perm{display:flex;align-items:flex-start;gap:9px;padding:10px;border:1px solid var(--border-soft);border-radius:15px;background:rgba(255,255,255,.035);font-size:.84rem;font-weight:850}.os-perm small{display:block;color:var(--text-3);font-size:.72rem;margin-top:2px;font-weight:700;line-height:1.55}.os-perm input{margin-top:3px;accent-color:#6ee7a0}.os-list{display:grid;gap:12px}.os-card{position:relative;overflow:hidden;border:1px solid var(--border-soft);border-radius:20px;background:linear-gradient(135deg,rgba(255,255,255,.045),rgba(255,255,255,.02));padding:15px}.os-card.live{border-color:rgba(110,231,160,.45);box-shadow:0 0 0 1px rgba(110,231,160,.12) inset,0 18px 54px rgba(110,231,160,.08)}.os-card.cancelled,.os-card.ended{opacity:.72}.os-card-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.os-title{font-weight:1000;color:var(--text-1);display:flex;gap:8px;align-items:center;flex-wrap:wrap}.os-meta{display:flex;gap:12px;flex-wrap:wrap;color:var(--text-3);font-size:.8rem;margin-top:8px}.os-students-line{margin-top:10px;padding:9px 10px;border-radius:14px;background:rgba(255,255,255,.035);color:var(--text-2);font-size:.82rem;line-height:1.8}.os-actions{display:flex;gap:7px;flex-wrap:wrap;margin-top:13px;padding-top:13px;border-top:1px solid var(--border-soft)}.os-actions form{display:inline-flex}.os-actions .btn{font-weight:900}.os-live-dot{width:8px;height:8px;border-radius:50%;background:#6ee7a0;display:inline-block;box-shadow:0 0 0 0 rgba(110,231,160,.7);animation:osPulse 1.5s infinite}@keyframes osPulse{70%{box-shadow:0 0 0 10px rgba(110,231,160,0)}}@media(max-width:1020px){.os-layout{grid-template-columns:1fr}.os-stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.os-stats{grid-template-columns:1fr}.os-perms{grid-template-columns:1fr}.os-card-top{flex-direction:column}.os-actions .btn{width:100%;justify-content:center}.os-actions form{width:100%}}
</style>

<section class="os-hero">
  <span class="badge badge-sage" style="font-weight:1000">🎥 Online Class · Hybrid Free</span>
  <h2>کلاس آنلاین مَدار، هماهنگ با پنل اصلی</h2>
  <p>اتصال اصلی با WebRTC P2P داخلی انجام می‌شود؛ یعنی تصویر/صدا مستقیم بین مرورگرها رد و بدل می‌شود و روی هاست اشتراکی فشار رسانه‌ای نمی‌آورد. در صورت مشکل شبکه، مسیر جایگزین رایگان هم قابل استفاده است. دانش‌آموز برای میکروفون، دوربین، اشتراک صفحه و تخته باید از شما اجازه بگیرد.</p>
  <div class="os-badges"><span>بدون Node/WebSocket</span><span>سازگار با cPanel و XAMPP</span><span>حداکثر پیشنهادی ۶ نفر</span><span>تخته ذخیره‌شونده</span><span>چت و دست‌بلندکردن</span></div>
</section>

<div class="os-stats">
  <div class="os-stat"><b><?= fa_num($stats['scheduled']) ?></b><span>زمان‌بندی‌شده</span></div>
  <div class="os-stat"><b><?= fa_num($stats['live']) ?></b><span>در حال برگزاری</span></div>
  <div class="os-stat"><b><?= fa_num($stats['ended']) ?></b><span>پایان‌یافته</span></div>
  <div class="os-stat"><b><?= fa_num($stats['cancelled']) ?></b><span>لغوشده</span></div>
</div>

<div class="os-layout">
  <section class="os-panel">
    <h3><?= icon($editSession ? 'edit' : 'plus', 18) ?> <?= $editSession ? 'ویرایش کلاس آنلاین' : 'تنظیم کلاس آنلاین جدید' ?></h3>
    <?php if (!$students): ?>
      <div class="empty-state" style="padding:30px"><div class="es-ico"><?= icon('users',32) ?></div><p>دانش‌آموز فعالی برای دعوت وجود ندارد.</p><a class="btn btn-gold btn-sm mt-3" href="<?= url('admin/students.php') ?>">مدیریت دانش‌آموزان</a></div>
    <?php else: ?>
    <form method="post" data-loading>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $editSession ? 'update' : 'create' ?>">
      <?php if ($editSession): ?><input type="hidden" name="id" value="<?= (int)$editSession['id'] ?>"><?php endif; ?>

      <div class="field"><label>عنوان کلاس</label><input class="input" name="title" required value="<?= e($editSession['title'] ?? '') ?>" placeholder="مثلاً کلاس جمع‌بندی ریاضی"></div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="field"><label>تاریخ</label><input class="input" type="date" name="scheduled_date" required value="<?= e($formDate) ?>"></div>
        <div class="field"><label>ساعت شروع</label><input class="input" type="time" name="scheduled_time" required value="<?= e($formTime) ?>"></div>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="field"><label>مدت کلاس</label><select class="select" name="duration_min">
          <?php foreach ([30,45,60,75,90,120,150,180,240] as $d): ?><option value="<?= $d ?>" <?= (int)($editSession['duration_min'] ?? 60)===$d?'selected':'' ?>><?= fa_num($d) ?> دقیقه</option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>ظرفیت</label><select class="select" name="max_participants">
          <?php for($i=2;$i<=6;$i++): ?><option value="<?= $i ?>" <?= (int)($editSession['max_participants'] ?? 6)===$i?'selected':'' ?>><?= fa_num($i) ?> نفر</option><?php endfor; ?>
        </select></div>
      </div>
      <div class="field"><label>توضیحات کوتاه / موضوع</label><textarea class="input" name="description" rows="3" placeholder="اختیاری؛ برای دانش‌آموزان نمایش داده می‌شود"><?= e($editSession['description'] ?? '') ?></textarea></div>

      <div class="field"><label>دانش‌آموزان دعوت‌شده</label>
        <div class="os-student-list">
          <?php foreach ($students as $s): $checked = $editSession ? in_array((int)$s['id'], $editParticipants, true) : false; ?>
          <label class="os-student"><input type="checkbox" name="student_ids[]" value="<?= (int)$s['id'] ?>" <?= $checked?'checked':'' ?>><span class="nm"><?= e($s['full_name']) ?></span><span class="badge" style="font-size:.68rem"><?= e($s['field'] ?: '—') ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field"><label>دسترسی‌های اولیه دانش‌آموز</label>
        <div class="os-perms">
          <label class="os-perm"><input type="checkbox" name="allow_student_mic" value="1" <?= !empty($editSession['allow_student_mic'])?'checked':'' ?>><span>میکروفون آزاد<small>خاموش باشد: درخواست اجازه می‌دهد.</small></span></label>
          <label class="os-perm"><input type="checkbox" name="allow_student_cam" value="1" <?= !empty($editSession['allow_student_cam'])?'checked':'' ?>><span>دوربین آزاد<small>پیشنهاد: خاموش، برای کنترل کلاس.</small></span></label>
          <label class="os-perm"><input type="checkbox" name="allow_screen_share" value="1" <?= !empty($editSession['allow_screen_share'])?'checked':'' ?>><span>اشتراک صفحه آزاد<small>معمولاً فقط با اجازه مشاور.</small></span></label>
          <label class="os-perm"><input type="checkbox" name="allow_whiteboard" value="1" <?= !empty($editSession['allow_whiteboard'])?'checked':'' ?>><span>تخته آزاد<small>دانش‌آموز برای نوشتن روی تخته اجازه می‌گیرد.</small></span></label>
          <label class="os-perm"><input type="checkbox" name="allow_chat" value="1" <?= $editSession ? (!empty($editSession['allow_chat'])?'checked':'') : 'checked' ?>><span>چت کلاس<small>چت آموزشی داخل جلسه.</small></span></label>
        </div>
      </div>

      <div class="flex gap-2 wrap mt-4">
        <button class="btn btn-gold btn-lg" style="font-weight:1000"><?= icon($editSession?'check':'rocket',18) ?> <?= $editSession ? 'ذخیره تغییرات' : 'ساخت کلاس' ?></button>
        <?php if ($editSession): ?><a class="btn btn-ghost btn-lg" href="<?= url('admin/online_sessions.php') ?>">انصراف</a><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </section>

  <section class="os-panel">
    <h3><?= icon('video', 18) ?> کلاس‌های آنلاین شما</h3>
    <?php if (!$sessions): ?>
      <div class="empty-state" style="padding:36px"><div class="es-ico"><?= icon('video',34) ?></div><p>هنوز کلاس آنلاینی ساخته نشده است.</p></div>
    <?php else: ?>
    <div class="os-list">
      <?php foreach ($sessions as $s):
        $parts = online_session_participants((int)$s['id']);
        $names = array_map(fn($p) => (string)$p['full_name'], $parts);
        $status = (string)$s['status'];
        $isPast = !empty($s['scheduled_at']) && substr((string)$s['scheduled_at'], 0, 10) < date('Y-m-d') && $status === 'scheduled';
      ?>
      <article class="os-card <?= e($status) ?>">
        <div class="os-card-top">
          <div>
            <div class="os-title">
              <?php if ($status==='live'): ?><span class="os-live-dot"></span><?php endif; ?>
              <?= e($s['title']) ?>
              <span class="badge <?= $status==='live'?'badge-sage':($status==='scheduled'?'badge-gold':'') ?>" style="font-size:.7rem"><?= e(online_session_status_label($status)) ?></span>
              <?php if ($isPast): ?><span class="badge" style="font-size:.7rem;color:var(--warn)">زمان گذشته</span><?php endif; ?>
            </div>
            <div class="os-meta">
              <span><?= icon('calendar',13) ?> <?= $s['scheduled_at'] ? jalali_date(substr((string)$s['scheduled_at'],0,10)) : '—' ?></span>
              <span><?= icon('clock',13) ?> <?= $s['scheduled_at'] ? fa_num(substr((string)$s['scheduled_at'],11,5)) : '—' ?></span>
              <span><?= icon('timer',13) ?> <?= fa_num((int)$s['duration_min']) ?> دقیقه</span>
              <span><?= icon('users',13) ?> <?= fa_num(count($parts)) ?>/<?= fa_num((int)$s['max_participants']) ?></span>
            </div>
          </div>
        </div>
        <?php if (!empty($s['description'])): ?><div class="muted" style="margin-top:9px;font-size:.82rem;line-height:1.8"><?= e($s['description']) ?></div><?php endif; ?>
        <div class="os-students-line"><b><?= icon('users',14) ?> دانش‌آموزان:</b> <?= e($names ? implode('، ', array_slice($names,0,6)) : 'دعوت‌شده‌ای ثبت نشده') ?></div>
        <div class="os-actions">
          <?php if (in_array($status, ['scheduled','draft'], true)): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="start"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sage btn-sm"><?= icon('play',15) ?> شروع کلاس</button></form>
            <a class="btn btn-ghost btn-sm" href="<?= url('admin/online_sessions.php?edit='.(int)$s['id']) ?>"><?= icon('edit',15) ?> ویرایش</a>
            <form method="post" onsubmit="return confirm('این کلاس لغو شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-ghost btn-sm" style="color:var(--warn)"><?= icon('close',15) ?> لغو</button></form>
          <?php elseif ($status === 'live'): ?>
            <a class="btn btn-gold btn-sm" href="<?= url('online_room.php?session='.(int)$s['id']) ?>"><?= icon('login',15) ?> ورود به اتاق</a>
            <form method="post" onsubmit="return confirm('کلاس برای همه پایان یابد؟')"><?= csrf_field() ?><input type="hidden" name="action" value="end"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-ghost btn-sm" style="color:var(--danger)"><?= icon('phone-off',15) ?> پایان برای همه</button></form>
          <?php endif; ?>
          <?php if (in_array($status, ['ended','cancelled'], true)): ?>
            <form method="post" onsubmit="return confirm('حذف کامل این کلاس و داده‌های چت/تخته؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-ghost btn-sm" style="color:var(--danger)"><?= icon('trash',15) ?> حذف</button></form>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
<?php panel_end(); ?>
