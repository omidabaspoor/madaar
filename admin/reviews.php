<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/review_scheduler.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();
$studentId = (int)($_GET['student'] ?? 0);

$scope = $_GET['scope'] ?? 'due';
if (!in_array($scope, ['due','upcoming','done'], true)) {
    $scope = 'due';
}

if (!review_schema_ready()) {
    panel_start('مرورهای دانش‌آموزان','خطا در آماده‌سازی', 'admin', 'reviews', ['student.css']);
    echo '<div class="alert alert-error">خطا در آماده‌سازی مرورها. لطفاً install.php را یک‌بار باز کنید تا جداول به‌روزرسانی شوند.</div>';
    panel_end(); exit;
}

if (!$studentId) {
    $students = advisor_students((int)$u['id'], 'active');
    panel_start('مرورهای دانش‌آموزان','نمای جداگانه مرورهای فاصله‌دار هر دانش‌آموز', 'admin', 'reviews', ['student.css']);
    ?>
    <div class="student-grid">
      <?php foreach($students as $s): review_backfill_for_student((int)$s['id']); $c=review_counts((int)$s['id']); ?>
      <a class="panel student-card" href="?student=<?= (int)$s['id'] ?>&scope=due" style="text-decoration:none;color:inherit">
        <div class="sc-top"><span class="u-ava"><?= e(avatar_letters($s['full_name'])) ?></span><div><b><?= e($s['full_name']) ?></b><div class="muted">برنامه مرور اختصاصی</div></div></div>
        <div class="sc-meta"><span class="badge badge-gold">موعد: <?= fa_num($c['due']) ?></span><span class="badge">آینده: <?= fa_num($c['upcoming']) ?></span><span class="badge badge-sage">انجام‌شده: <?= fa_num($c['done']) ?></span></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php panel_end(); exit;
}

$student = get_user($studentId);
if (!$student || $student['role'] !== 'student') { flash('error','دانش‌آموز یافت نشد'); redirect('admin/reviews.php'); }
if ($u['role'] !== 'admin' && (int)($student['advisor_id'] ?? 0) !== (int)$u['id']) { http_response_code(403); require __DIR__ . '/../403.php'; exit; }
review_backfill_for_student($studentId);
$counts = review_counts($studentId);
$items = review_items_for_advisor($studentId, $scope);
panel_start('مرورهای دانش‌آموز', $student['full_name'], 'admin', 'reviews', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="builder-student flex gap-3" style="align-items:center">
    <a href="<?= url('admin/reviews.php') ?>" class="btn btn-ghost btn-icon"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div><div style="font-weight:900;font-size:1.1rem"><?= e($student['full_name']) ?></div><div class="muted">مرورهای ساخته‌شده از تسک‌های مطالعه، درسنامه و کتاب درسی</div></div>
  </div>
  <a class="btn btn-gold btn-sm flex gap-2" style="align-items:center" href="<?= url('admin/plan_builder.php?student='.$studentId) ?>"><?= icon('calendar',16) ?> برنامه‌ریز هفتگی</a>
</div>

<div class="report-tabs mb-4">
  <a class="chip <?= $scope==='due'?'active':'' ?>" href="?student=<?= $studentId ?>&scope=due">
    <?= icon('bell',15) ?> موعد امروز / عقب‌افتاده · <?= fa_num($counts['due']) ?>
  </a>
  <a class="chip <?= $scope==='upcoming'?'active':'' ?>" href="?student=<?= $studentId ?>&scope=upcoming">
    <?= icon('calendar',15) ?> روزهای آینده · <?= fa_num($counts['upcoming']) ?>
  </a>
  <a class="chip <?= $scope==='done'?'active':'' ?>" href="?student=<?= $studentId ?>&scope=done">
    <?= icon('check-circle',15) ?> انجام‌شده · <?= fa_num($counts['done']) ?>
  </a>
</div>

<?php if(!$items): ?>
  <!-- Premium Empty State -->
  <div class="panel empty-state-premium text-c" style="padding:56px 24px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--r-lg);box-shadow:0 8px 24px rgba(0,0,0,0.12)">
    <div class="es-ico-wrapper" style="width:80px;height:80px;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;background:rgba(107,136,114,0.12);color:var(--sage);border-radius:50%">
      <?= icon('repeat',40) ?>
    </div>
    
    <?php if($scope==='due'): ?>
      <h3 style="font-size:1.3۵rem;font-weight:800;margin-bottom:10px;color:var(--text-1)">همه‌چیز مرتب است! هیچ مروری برای این دانش‌آموز عقب نمانده 🎉</h3>
      <p class="muted" style="max-width:480px;margin:0 auto 24px;font-size:.9۵rem;line-height:1.6">
        به‌محض اینکه دانش‌آموز تسک‌های خواندنی یا کتاب درسی برنامه‌اش را کامل کند، یادآورهای فواصل بعدی به‌طور خودکار در این بخش فعال می‌شوند.
      </p>
      <a href="<?= url('admin/plan_builder.php?student='.$studentId) ?>" class="btn btn-gold btn-lg text-c" style="display:inline-flex;align-items:center;gap:8px;padding:12px 28px;font-weight:800">
        <?= icon('calendar',18) ?> برنامه‌ریزی تسک‌های جدید
      </a>
    <?php elseif($scope==='upcoming'): ?>
      <h3 style="font-size:1.3۵rem;font-weight:800;margin-bottom:10px;color:var(--text-1)">هیچ مرور فاصله‌داری برای روزهای آینده ثبت نشده است 📅</h3>
      <p class="muted" style="max-width:480px;margin:0 auto 24px;font-size:.9۵rem;line-height:1.6">
        هنگامی که دانش‌آموز تسک‌های مطالعه‌ی جدیدی را به پایان برساند، زمان‌های بعدی مرور در این بخش زمان‌بندی خواهند شد.
      </p>
    <?php else: ?>
      <h3 style="font-size:1.3۵rem;font-weight:800;margin-bottom:10px;color:var(--text-1)">دانش‌آموز هنوز هیچ آیتم مروری را به پایان نرسانده است ✨</h3>
      <p class="muted" style="max-width:480px;margin:0 auto 24px;font-size:.9۵rem;line-height:1.6">
        آیتم‌هایی که دانش‌آموز از پنل خود مرور و تایید می‌کند، در این تب بایگانی می‌شوند تا بتوانی بر روند تثبیت مطالب نظارت داشته باشی.
      </p>
    <?php endif; ?>
  </div>
<?php else: ?>
<!-- لیست کارت‌های مرور -->
<div class="review-list admin-review-list grid gap-4" style="grid-template-columns:repeat(auto-fill, minmax(min(100%, 340px), 1fr))">
<?php foreach($items as $r): 
    $late = $scope==='due' ? max(0, (int)floor((time()-strtotime($r['due_date']))/86400)) : 0; 
    $subjName = trim((string)($r['subject_name'] ?? ''));
    $color = $r['subject_color'] ?? '#6b8872';
?>
  <div class="panel review-card <?= $scope==='due'?'due':'' ?>" style="display:flex;flex-direction:column;justify-content:space-between;border:1px solid var(--border-soft);padding:24px;border-radius:var(--r-lg);background:var(--surface-2);position:relative;overflow:hidden;min-height:100%;word-break:break-word">
    <!-- نوار رنگی درس بالای کارت -->
    <div style="position:absolute;top:0;right:0;left:0;height:4px;background:<?= e($color) ?>"></div>
    
    <div class="review-main mb-4 mt-1" style="flex:1;display:flex;flex-direction:column">
      <div class="between mb-3 wrap gap-2" style="align-items:center">
        <span class="badge" style="background:<?= e($color) ?>22;color:<?= e($color) ?>;font-size:.8۵rem;padding:6px 12px;font-weight:900;border-radius:var(--r-pill)">
          <?= $subjName ? icon('book',15).' '.e($subjName) : 'عمومی / سایر' ?>
        </span>
        <span class="badge badge-gold" style="font-size:.8۵rem;font-weight:800;padding:4px 10px">
          <?= (int)$r['interval_days']===0 ? 'مرور تقویتی' : 'فاصِله '.fa_num($r['interval_days']).' روزه' ?>
        </span>
      </div>

      <h3 style="font-size:1.2۵rem;font-weight:900;color:var(--text-1);margin-bottom:12px;line-height:1.7;word-break:break-word;overflow-wrap:break-word;white-space:normal"><?= e($r['topic_title']) ?></h3>
      
      <div class="muted mt-auto" style="font-size:.88rem;line-height:1.7;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <?php if($r['profile_label']): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;background:var(--surface-1);padding:4px 10px;border-radius:6px;font-weight:700">
            <?= icon('sparkles',14) ?> <?= e($r['profile_label']) ?>
          </span>
        <?php endif; ?>
        
        <?php if($r['source']): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;color:var(--text-2);background:var(--surface-1);padding:4px 10px;border-radius:6px;font-weight:700">
            <?= icon('paperclip',14) ?> منبع: <?= e($r['source']) ?>
          </span>
        <?php endif; ?>
        
        <span style="display:inline-flex;align-items:center;gap:6px;font-weight:800;color:var(--gold);background:var(--gold-glass);padding:4px 10px;border-radius:6px">
          <?= icon('clock',14) ?> <?= fa_num((int)($r['suggested_minutes']??15)) ?> دقیقه
        </span>
      </div>

      <div class="mt-4 between wrap gap-2" style="font-size:.8۵rem;border-top:1px dashed var(--border-soft);padding-top:14px;align-items:center">
        <span class="muted" style="font-weight:700">موعد: <b style="color:var(--text-1)"><?= jalali_date($r['due_date']) ?></b></span>
        <?php if($late > 0): ?>
          <span style="color:var(--danger);font-weight:900;background:rgba(217,116,116,0.15);padding:4px 10px;border-radius:6px">
            ⚠️ <?= fa_num($late) ?> روز تأخیر
          </span>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="mt-auto pt-3 text-l between wrap gap-2" style="border-top:1px solid var(--border-soft);align-items:center">
      <span class="muted" style="font-size:.8rem;font-weight:700">کیفیت: <b style="color:var(--gold-light)"><?= e($r['quality']==='hard'?'سخت':($r['quality']==='easy'?'آسان':($r['quality']==='good'?'خوب':'—'))) ?></b></span>
      <span class="badge <?= $r['status']==='done'?'badge-sage':'badge-gold' ?>" style="padding:6px 12px;font-weight:900"><?= e($r['status']==='done'?'✓ انجام‌شده':($scope==='upcoming'?'📅 آینده':'⏳ در انتظار')) ?></span>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php panel_end(); ?>
