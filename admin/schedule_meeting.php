<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/meetings.php';
boot_session();
require_role('advisor', 'admin');
$u = current_user();

meetings_schema_ready();

// Handle cancellation
if (isset($_GET['cancel'])) {
    $cancelId = (int)$_GET['cancel'];
    if (meetings_cancel($cancelId, (int)$u['id'], 'advisor')) {
        flash('success', 'جلسه مشاوره با موفقیت لغو شد.');
    } else {
        flash('error', 'خطا در لغو جلسه یا عدم دسترسی.');
    }
    redirect('admin/schedule_meeting.php');
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $date = trim((string)($_POST['session_date'] ?? ''));
        $time = trim((string)($_POST['session_time'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        
        if (!$studentId || !$title || !$date) {
            throw new RuntimeException('لطفاً تمامی فیلدهای اجباری (دانش‌آموز، موضوع و تاریخ) را تکمیل فرمایید.');
        }
        
        meetings_save((int)$u['id'], $studentId, $title, $date, $time, $notes);
        flash('success', 'جلسه مشاوره با موفقیت تنظیم و برای دانش‌آموز ارسال شد.');
        redirect('admin/schedule_meeting.php');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }
}

// Fetch advisor's students
$students = advisor_students((int)$u['id'], 'active');
// Fetch scheduled sessions
$sessions = meetings_for_advisor((int)$u['id']);

panel_start('برنامه‌ریزی جلسات', 'تنظیم و زمان‌بندی جلسات مشاوره هفتگی و ماهانه با دانش‌آموزان', 'admin', 'meetings', ['student.css']);
?>
<div class="greet-card reveal in" style="margin-bottom: 24px; background: linear-gradient(135deg, #1c2823, #0c1512); border: 1px solid rgba(178, 148, 95, 0.35); box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
  <div class="between" style="align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
      <div class="gc-sub">پنل برنامه‌ریزی جلسات مَدار 📅</div>
      <h2 style="margin-top: 4px; color: var(--gold-light);">اتاق جلسات و مشاوره‌های اختصاصی</h2>
      <p class="muted" style="font-size: 13.5px; margin-top: 6px; line-height: 1.6; max-width: 650px;">
        از این بخش می‌توانید برای دانش‌آموزان خود جلسات هماهنگی، مشاوره‌ی درسی یا بررسی پیشرفت تنظیم کنید. به محض فرارسیدن روز جلسه، زنگ هشدار هوشمند و متمایز روی داشبورد شما و دانش‌آموز فعال خواهد شد.
      </p>
    </div>
  </div>
</div>

<div class="grid gap-4" style="grid-template-columns: 1fr 1.6fr; align-items: start; margin-bottom: 24px;">
  
  <!-- FORM CARD -->
  <div class="panel" style="background: rgba(20,32,27,0.7); border: 1px solid rgba(178, 148, 95, 0.25); border-radius: 22px; padding: 26px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
    <div class="panel-head" style="margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">
      <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-1); display: flex; align-items: center; gap: 8px;">
        <?= icon('plus',18) ?> تنظیم جلسه جدید
      </h3>
    </div>
    <form method="post" id="meetingForm">
      <?= csrf_field() ?>
      <div class="field" style="margin-bottom: 18px;">
        <label style="font-weight: 900; margin-bottom: 6px; display: block; font-size: 12.5px; color: var(--gold-light);">دانش‌آموز هدف <span style="color:var(--danger)">*</span></label>
        <select class="select" name="student_id" required style="width: 100%; height: 42px; border-radius: 10px; background: rgba(12,21,18,0.5); border: 1px solid rgba(255,255,255,0.08); color: var(--text-1); padding: 0 12px;">
          <option value="">انتخاب کنید...</option>
          <?php foreach ($students as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['full_name']) ?> (<?= e($s['field'] ?: 'رشته نامشخص') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="field" style="margin-bottom: 18px;">
        <label style="font-weight: 900; margin-bottom: 6px; display: block; font-size: 12.5px; color: var(--gold-light);">عنوان یا موضوع جلسه <span style="color:var(--danger)">*</span></label>
        <input class="input" name="title" required placeholder="مثلاً بررسی کارنامه و رفع اشکال تستی" style="width: 100%; height: 42px; border-radius: 10px; background: rgba(12,21,18,0.5); border: 1px solid rgba(255,255,255,0.08); color: var(--text-1); padding: 0 12px;">
      </div>
      
      <div class="grid gap-3" style="grid-template-columns: 1.2fr 1fr; margin-bottom: 18px;">
        <div class="field">
          <label style="font-weight: 900; margin-bottom: 6px; display: block; font-size: 12.5px; color: var(--gold-light);">تاریخ جلسه <span style="color:var(--danger)">*</span></label>
          <input class="input" type="date" name="session_date" required placeholder="انتخاب تاریخ شمسی" style="width: 100%; height: 42px; border-radius: 10px; background: rgba(12,21,18,0.5); border: 1px solid rgba(255,255,255,0.08); color: var(--text-1); padding: 0 12px;">
        </div>
        <div class="field">
          <label style="font-weight: 900; margin-bottom: 6px; display: block; font-size: 12.5px; color: var(--gold-light);">ساعت دقیق <span class="muted" style="font-size: 10px; font-weight: normal;">(اختیاری)</span></label>
          <input class="input" type="time" name="session_time" placeholder="ساعت توافقی" style="width: 100%; height: 42px; border-radius: 10px; background: rgba(12,21,18,0.5); border: 1px solid rgba(255,255,255,0.08); color: var(--text-1); padding: 0 12px;">
        </div>
      </div>
      
      <div class="field" style="margin-bottom: 22px;">
        <label style="font-weight: 900; margin-bottom: 6px; display: block; font-size: 12.5px; color: var(--gold-light);">توضیحات و بستر برگزاری <span class="muted" style="font-size: 10px; font-weight: normal;">(اختیاری)</span></label>
        <textarea class="input" name="notes" rows="3" placeholder="مثلاً: تماس تلفنی با اولیا، یا لینک گوگل میت..." style="width: 100%; border-radius: 10px; background: rgba(12,21,18,0.5); border: 1px solid rgba(255,255,255,0.08); color: var(--text-1); padding: 10px 12px;"></textarea>
      </div>
      
      <button class="btn btn-gold btn-block" type="submit" style="font-weight: 900; padding: 14px; border-radius: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; box-shadow: 0 4px 16px rgba(178,148,95,0.2);">
        <?= icon('check', 16) ?> ثبت و ابلاغ جلسه مشاوره
      </button>
    </form>
  </div>

  <!-- SESSIONS LIST -->
  <div class="panel" style="background: rgba(20,32,27,0.7); border: 1px solid rgba(107, 136, 114, 0.25); border-radius: 22px; padding: 26px; box-shadow: 0 10px 30px rgba(0,0,0,0.25);">
    <div class="panel-head" style="margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 12px;">
      <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-1); display: flex; align-items: center; gap: 8px;">
        <?= icon('calendar',18) ?> جلسات تنظیم‌شده‌ی شما
      </h3>
    </div>
    
    <?php if (empty($sessions)): ?>
      <div class="empty-state" style="padding: 50px 20px;">
        <div class="es-ico"><?= icon('calendar', 38) ?></div>
        <p>هنوز جلسه‌ای برنامه‌ریزی نکرده‌اید 📅</p>
        <p class="muted" style="font-size: 12.5px; margin-top: 6px;">می‌توانید از فرم سمت راست اولین جلسه مشاوره‌ی درسی خود را برای دانش‌آموزان تنظیم کنید.</p>
      </div>
    <?php else: ?>
      <div class="mock-list" style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($sessions as $s): 
          $isToday = $s['session_date'] === date('Y-m-d');
          $isPast = $s['session_date'] < date('Y-m-d');
        ?>
          <div class="panel" style="background: rgba(255, 255, 255, 0.02); border: 1px solid <?= $isToday?'var(--gold)':'rgba(255, 255, 255, 0.06)' ?>; border-radius: 16px; padding: 16px 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; align-items: center; position: relative; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.04)'; this.style.borderColor='rgba(178,148,95,0.4)';" onmouseout="this.style.background='rgba(255,255,255,0.02)'; this.style.borderColor='<?= $isToday?'var(--gold)':'rgba(255, 255, 255, 0.06)' ?>';">
            
            <?php if($isToday && $s['status'] === 'scheduled'): ?>
              <div style="position: absolute; top: -1px; left: 24px; background: var(--gold); color: #111; font-size: 10px; font-weight: 1000; padding: 3px 12px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 10px rgba(178,148,95,0.25);">
                جلسه امروز 🔥
              </div>
            <?php endif; ?>
            
            <div style="flex: 1; min-width: 250px;">
              <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <b style="font-size: 15px; color: var(--text-1);"><?= e($s['title']) ?></b>
                <span class="badge badge-sage" style="font-size: 11px; font-weight: 800; padding: 3px 8px;"><?= e($s['student_name']) ?></span>
                <span class="badge" style="border: none; font-size: 10.5px; background: <?= $s['status']==='scheduled'?($isPast?'rgba(255,255,255,0.08)':'rgba(46, 68, 56, 0.2)'):'rgba(220, 53, 69, 0.15)' ?>; color: <?= $s['status']==='scheduled'?($isPast?'#8e9c96':'#9fc7a8'):'#ea868f' ?>; font-weight: bold;">
                  <?= $s['status']==='scheduled'?($isPast?'برگزار شده':'در انتظار برگزاری'):'لغو شده' ?>
                </span>
              </div>
              
              <div class="muted" style="font-size: 12.5px; margin-top: 10px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                <span style="display: flex; align-items: center; gap: 4px; color: var(--text-2);"><?= icon('calendar', 13) ?> <?= jalali_date($s['session_date']) ?></span>
                <span style="display: flex; align-items: center; gap: 4px; color: var(--text-2);"><?= icon('clock', 13) ?> <?= $s['session_time'] ? fa_num(substr((string)$s['session_time'], 0, 5)) : 'ساعت توافقی 🕒' ?></span>
                <?php if(!empty($s['notes'])): ?>
                  <span style="color: var(--gold-light); display: flex; align-items: center; gap: 4px; font-weight: 800;"><?= icon('note', 13) ?> <?= e($s['notes']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            
            <div class="flex gap-2" style="align-items: center;">
              <?php if($s['status'] === 'scheduled' && !$isPast): ?>
                <a href="?cancel=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm" style="color: var(--danger); border-color: rgba(220, 53, 69, 0.25); font-weight: bold; border-radius: 10px; padding: 6px 12px;" onclick="return confirm('آیا از لغو این جلسه مطمئن هستید؟')">
                  لغو جلسه
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
<?php panel_end(); ?>
