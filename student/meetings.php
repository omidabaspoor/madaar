<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/meetings.php';
boot_session();
require_role('student');
$u = current_user();

meetings_schema_ready();

// Handle cancellation/declination by student
if (isset($_GET['cancel'])) {
    $cancelId = (int)$_GET['cancel'];
    if (meetings_cancel($cancelId, (int)$u['id'], 'student')) {
        flash('success', 'شما انصراف خود را از این جلسه ثبت کردید و به مشاور اطلاع داده شد.');
    } else {
        flash('error', 'خطا در لغو جلسه.');
    }
    redirect('student/meetings.php');
}

// Fetch scheduled sessions
$sessions = meetings_for_student((int)$u['id']);

panel_start('جلسات مشاوره من', 'برنامه جلسات همفکری و مشاوره‌های اختصاصی شما با دکتر', 'student', 'meetings', ['student.css']);
?>
<div class="greet-card reveal in" style="margin-bottom: 24px; background: linear-gradient(135deg, #182a20, #0c1512); border: 1px solid rgba(178, 148, 95, 0.35); box-shadow: 0 8px 32px rgba(0,0,0,0.35);">
  <div class="between" style="align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
      <div class="gc-sub">اتاق جلسات مشاوره 📅</div>
      <h2 style="margin-top: 4px; color: var(--gold-light);">جلسات مشاوره پیش‌رو و هماهنگ‌شده</h2>
      <p class="muted" style="font-size: 13.5px; margin-top: 6px; line-height: 1.6; max-width: 650px;">
        برنامه‌ی جلسات مشاوره تلفنی، تصویری یا حضوری خود با مشاور محترم را از این صفحه دنبال کنید. سر زمان جلسه با شما تماس گرفته خواهد شد یا از بستر اعلام‌شده استفاده کنید.
      </p>
    </div>
  </div>
</div>

<div class="panel" style="background: rgba(20,32,27,0.7); border: 1px solid rgba(107, 136, 114, 0.25); border-radius: 22px; padding: 26px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); margin-bottom: 24px;">
  <div class="panel-head" style="margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">
    <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-1); display: flex; align-items: center; gap: 8px;">
      <?= icon('calendar', 18) ?> برنامه‌ی زمانی جلسات شما
    </h3>
  </div>
  
  <?php if (empty($sessions)): ?>
    <div class="empty-state" style="padding: 50px 20px;">
      <div class="es-ico"><?= icon('calendar', 38) ?></div>
      <p>هیچ جلسه‌ی مشاوره‌ای برای شما تنظیم نشده است 📅</p>
      <p class="muted" style="font-size: 13px; margin-top: 6px;">به محض اینکه مشاورتان جلسه‌ای برای شما تنظیم کند، به همراه اعلان در این بخش نمایش داده می‌شود.</p>
    </div>
  <?php else: ?>
    <div class="mock-list" style="display: flex; flex-direction: column; gap: 14px;">
      <?php foreach ($sessions as $s):
        $isToday = $s['session_date'] === date('Y-m-d');
        $isPast = $s['session_date'] < date('Y-m-d');
        $isClass = ($s['session_type'] ?? 'consultation') === 'class';
        $typeLabel = $isClass ? 'کلاس درسی' : 'جلسه مشاوره';
        $status = $s['status'] ?? 'scheduled';
        if ($status === 'draft') {
          $statusLabel = 'در انتظار تأیید مشاور';
          $statusBg = 'rgba(217,178,95,.18)';
          $statusColor = '#e0c595';
          $statusBorder = 'rgba(217,178,95,.4)';
        } elseif ($status === 'cancelled') {
          $statusLabel = 'لغو شده';
          $statusBg = 'rgba(220, 53, 69, 0.15)';
          $statusColor = '#ea868f';
          $statusBorder = 'rgba(220, 53, 69, 0.3)';
        } elseif ($isPast) {
          $statusLabel = 'برگزار شده';
          $statusBg = 'rgba(255,255,255,0.08)';
          $statusColor = '#8e9c96';
          $statusBorder = 'rgba(255,255,255,0.1)';
        } elseif ($isToday) {
          $statusLabel = 'امروز';
          $statusBg = 'rgba(203,172,128,.18)';
          $statusColor = '#e0c595';
          $statusBorder = 'rgba(203,172,128,.5)';
        } else {
          $statusLabel = 'تأیید شده';
          $statusBg = 'rgba(46, 68, 56, 0.2)';
          $statusColor = '#9fc7a8';
          $statusBorder = 'rgba(107,136,114,.3)';
        }
      ?>
        <div class="panel" style="background: rgba(255, 255, 255, 0.02); border: 1px solid <?= $isToday?'var(--gold)':'rgba(255, 255, 255, 0.06)' ?>; border-radius: 16px; padding: 16px 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; align-items: center; position: relative; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.04)'; this.style.borderColor='rgba(178,148,95,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.02)'; this.style.borderColor='<?= $isToday?'var(--gold)':'rgba(255, 255, 255, 0.06)' ?>';">
          
          <?php if($isToday && $status === 'scheduled'): ?>
            <div style="position: absolute; top: -1px; left: 24px; background: var(--gold); color: #111; font-size: 11px; font-weight: 1000; padding: 3px 12px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 10px rgba(178,148,95,0.25);">
              امروز برگزار می‌شود 🔥
            </div>
          <?php endif; ?>

            <div style="flex: 1; min-width: 250px;">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <b style="font-size: 15px; color: var(--text-1);"><?= e($s['title']) ?></b>
                <span class="badge <?= $isClass ? 'badge-sage' : 'badge-gold' ?>" style="font-size: 10.5px; font-weight: 800; padding: 3px 9px;">
                  <?= $isClass ? '👥 کلاس درسی' : '👤 جلسه مشاوره' ?>
                </span>
                <span class="badge" style="border:1px solid <?= $statusBorder ?>;background:<?= $statusBg ?>;color:<?= $statusColor ?>;font-size:10.5px;font-weight:bold;padding:3px 9px">
                  <?= $statusLabel ?>
                </span>
              </div>
            
            <div class="muted" style="font-size: 13px; margin-top: 10px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
              <span style="font-weight: 800; color: var(--text-2); display: flex; align-items: center; gap: 4px;"><?= icon('user', 14) ?> مشاور: <?= e($s['advisor_name']) ?></span>
              <span style="display: flex; align-items: center; gap: 4px;"><?= icon('calendar', 14) ?> <?= jalali_date($s['session_date']) ?></span>
              <span style="display: flex; align-items: center; gap: 4px;"><?= icon('clock', 14) ?> <?= $s['session_time'] ? 'ساعت ' . fa_num(substr((string)$s['session_time'], 0, 5)) : 'ساعت توافقی 🕒' ?></span>
              <?php if(!empty($s['notes'])): ?>
                <span style="color: var(--gold-light); font-weight: 800; display: flex; align-items: center; gap: 4px;"><?= icon('note', 14) ?> بستر جلسه: <?= e($s['notes']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          
          <div>
            <?php if($s['status'] === 'scheduled' && !$isPast): ?>
              <a href="?cancel=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm" style="color: var(--danger); border-color: rgba(220, 53, 69, 0.25); font-weight: 800; border-radius: 10px; padding: 6px 12px;" onclick="return confirm('آیا از اعلام انصراف و لغو حضور در این جلسه مطمئن هستید؟')">
                انصراف و لغو حضور
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php panel_end(); ?>
