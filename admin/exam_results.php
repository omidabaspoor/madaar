<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/result_view.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$examId = (int)($_GET['id'] ?? 0);
$exam = get_exam($examId);
if (!$exam || ($exam['advisor_id'] != $u['id'] && $u['role']!=='admin')) { flash('error','آزمون یافت نشد'); redirect('admin/exams.php'); }

// نمایش کارنامه‌ی یک دانش‌آموز خاص
$viewAttempt = (int)($_GET['attempt'] ?? 0);
if ($viewAttempt) {
    $rep = attempt_report($viewAttempt);
    if (!$rep || (int)$rep['attempt']['exam_id'] !== $examId) { flash('error','کارنامه یافت نشد'); redirect('admin/exam_results.php?id='.$examId); }
    panel_start('کارنامه تحلیلی دانش‌آموز', $rep['attempt']['full_name'].' · '.$exam['title'], 'admin', 'exams', ['student.css','exam.css','result.css']);
    ?>
    <div class="between wrap gap-3 mb-6" style="background:var(--surface-1); padding:16px 24px; border-radius:var(--r-md); border:1px solid var(--border);">
      <a href="<?= url('admin/exam_results.php?id='.$examId) ?>" class="btn btn-ghost btn-sm flex gap-1 font-bold" style="align-items:center"><?= icon('arrow-right',16) ?> بازگشت به لیست نتایج</a>
      <div class="flex items-center gap-2.5 wrap">
        <a href="<?= url('student/exam_pdf.php?id=' . $examId) ?>" target="_blank" class="btn btn-ghost btn-sm flex items-center gap-1.5" style="border-color:var(--sage); color:var(--sage-light); font-weight:bold;">
          <?= icon('clipboard', 16) ?> <span>PDF دفترچه سوالات</span>
        </a>
        <a href="<?= url('student/exam_solution_pdf.php?attempt=' . $viewAttempt) ?>" target="_blank" class="btn btn-sage btn-sm flex items-center gap-1.5" style="font-weight:bold;">
          <?= icon('star', 16) ?> <span>PDF کارنامه و پاسخنامه</span>
        </a>
        <button type="button" class="btn btn-gold btn-sm flex gap-1 reset-attempt-btn" data-att="<?= (int)$viewAttempt ?>" data-exam="<?= (int)$examId ?>" style="align-items:center;font-weight:900 shadow-md">
          🔄 صدور مجوز شرکت مجدد (حذف کارنامه فعلی)
        </button>
      </div>
    </div>
    <?php
    render_result($rep, true);
    ?>
    <script>
      document.querySelector('.reset-attempt-btn')?.addEventListener('click', async b => {
        const btn = b.currentTarget;
        if (!confirm('آیا از حذف این کارنامه و صدور مجوز شرکت مجدد برای این دانش‌آموز مطمئنی؟ دانش‌آموز می‌تواند آزمون را دوباره از صفر بدهد.')) return;
        
        btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:14px;height:14px"></span> در حال حذف…';
        try {
          await api('<?= url('api/exam_builder.php') ?>', { method: 'POST', body: { action: 'reset_attempt', exam_id: btn.dataset.exam, attempt_id: btn.dataset.att } });
          toast('مجوز شرکت مجدد صادر شد. پاسخ‌برگ قبلی پاک گردید 🔄', 'success');
          setTimeout(() => location.href = '<?= url('admin/exam_results.php?id='.$examId) ?>', 700);
        } catch(err) {
          toast(err.error || 'خطا در صدور مجوز', 'error');
          btn.disabled = false; btn.innerHTML = '🔄 صدور مجوز شرکت مجدد';
        }
      });
    </script>
    <?php
    panel_end();
    exit;
}

$results = exam_results($examId);
$qCount = exam_question_count($examId);
$avg = $results ? round(array_sum(array_map(fn($r)=>(float)$r['total_score'],$results))/count($results),1) : 0;

// کنترل کامل مشاور روی شرکت دانش‌آموزها: وضعیت همه‌ی دانش‌آموزان + ریست آزمون‌های ناتمام/ثبت‌شده
if ($u['role'] === 'admin') {
    $ctrl = db()->prepare('SELECT s.id,s.full_name,s.field,s.status,a.id attempt_id,a.status attempt_status,a.started_at,a.submitted_at,a.total_score
        FROM users s LEFT JOIN exam_attempts a ON a.student_id=s.id AND a.exam_id=?
        WHERE s.role="student" AND s.status="active" ORDER BY s.full_name');
    $ctrl->execute([$examId]);
} else {
    $accessMode = $u['access_mode'] ?? 'all';
    if ($accessMode === 'restricted') {
        $ctrl = db()->prepare('SELECT s.id,s.full_name,s.field,s.status,a.id attempt_id,a.status attempt_status,a.started_at,a.submitted_at,a.total_score
            FROM users s LEFT JOIN exam_attempts a ON a.student_id=s.id AND a.exam_id=?
            WHERE s.role="student" AND s.status="active" AND s.id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id=?)
            ORDER BY s.full_name');
        $ctrl->execute([$examId, (int)$u['id']]);
    } else {
        $ctrl = db()->prepare('SELECT s.id,s.full_name,s.field,s.status,a.id attempt_id,a.status attempt_status,a.started_at,a.submitted_at,a.total_score
            FROM users s LEFT JOIN exam_attempts a ON a.student_id=s.id AND a.exam_id=?
            WHERE s.role="student" AND s.status="active" AND (s.advisor_id=? OR s.id IN (SELECT student_id FROM advisor_student_access WHERE advisor_id=?))
            ORDER BY s.full_name');
        $ctrl->execute([$examId, (int)$u['id'], (int)$u['id']]);
    }
}
$controlRows = $ctrl->fetchAll();

panel_start('نتایج و رتبه‌بندی آزمون', $exam['title'], 'admin', 'exams', ['student.css','result.css']);
?>
<div class="mb-4 flex gap-2 wrap between" style="align-items:center">
  <div class="flex gap-2 wrap" style="align-items:center">
    <a href="<?= url('admin/exams.php') ?>" class="btn btn-ghost btn-sm flex gap-1" style="align-items:center"><?= icon('arrow-right',16) ?> آزمون‌ها</a>
    <a href="<?= url('admin/exam_builder.php?id='.$examId) ?>" class="btn btn-ghost btn-sm flex gap-1" style="align-items:center"><?= icon('edit',15) ?> ویرایش آزمون</a>
  </div>
  <span class="badge badge-sage" style="padding:6px 14px;font-weight:900;display:inline-flex;align-items:center">✓ امکان صدور مجوز شرکت مجدد فعال است</span>
</div>

<div class="stat-cards mb-4">
  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft)"><span class="icon-tile sage"><?= icon('users',24) ?></span><div><div class="v"><?= fa_num(count($results)) ?></div><div class="k">شرکت‌کننده</div></div></div>
  <div class="panel stat reveal in" data-d="1" style="background:var(--surface-2);border:1px solid var(--border-soft)"><span class="icon-tile"><?= icon('target',24) ?></span><div><div class="v"><?= fa_num($avg) ?>٪</div><div class="k">میانگین درصد</div></div></div>
  <div class="panel stat reveal in" data-d="2" style="background:var(--surface-2);border:1px solid var(--border-soft)"><span class="icon-tile sage"><?= icon('list',24) ?></span><div><div class="v"><?= fa_num($qCount) ?></div><div class="k">تعداد سوال</div></div></div>
  <div class="panel stat reveal in" data-d="3" style="background:var(--surface-2);border:1px solid var(--border-soft)"><span class="icon-tile" style="background:rgba(217,178,95,.14);color:var(--warn)"><?= icon('trophy',24) ?></span><div><div class="v"><?= $results?fa_num(round((float)$results[0]['total_score'])).'٪':'—' ?></div><div class="k">بالاترین درصد</div></div></div>
</div>

<div class="panel reveal in mb-4 exam-control-panel" style="background:linear-gradient(135deg,rgba(203,172,128,.08),var(--surface-1));border:1px solid rgba(203,172,128,.20);padding:24px;border-radius:var(--r-lg);overflow-x:auto">
  <div class="panel-head mb-4">
    <h3><?= icon('settings',22) ?> کنترل شرکت دانش‌آموزها</h3>
    <span class="badge badge-gold">ریست آزمون = حذف پاسخ‌برگ فعلی و امکان شروع دوباره</span>
  </div>
  <?php if(!$controlRows): ?>
    <div class="empty-state">دانش‌آموز فعالی برای این مشاور یافت نشد.</div>
  <?php else: ?>
  <table class="tbl" style="width:100%;border-collapse:collapse;text-align:right">
    <thead><tr><th>دانش‌آموز</th><th>وضعیت آزمون</th><th>زمان شروع/ثبت</th><th>نمره</th><th>کنترل</th></tr></thead>
    <tbody>
    <?php foreach($controlRows as $cr):
      $attStatus = $cr['attempt_status'] ?: 'not_started';
      $badgeCls = ['submitted'=>'badge-sage','in_progress'=>'badge-gold','not_started'=>'badge'][$attStatus] ?? 'badge';
      $label = ['submitted'=>'ثبت‌شده','in_progress'=>'ناتمام / در حال انجام','not_started'=>'شروع نکرده'][$attStatus] ?? $attStatus;
    ?>
      <tr>
        <td><div class="u-row"><span class="u-ava" style="width:34px;height:34px"><?= e(avatar_letters($cr['full_name'])) ?></span><b><?= e($cr['full_name']) ?></b><?= $cr['field']?'<span class="badge">'.e($cr['field']).'</span>':'' ?></div></td>
        <td><span class="badge <?= $badgeCls ?>"><?= e($label) ?></span></td>
        <td class="muted" style="font-size:.82rem"><?= $cr['started_at']?jalali_date($cr['started_at'],true):'—' ?><?= $cr['submitted_at']?' · ثبت: '.jalali_date($cr['submitted_at'],true):'' ?></td>
        <td><?= $cr['total_score']!==null?fa_num(round((float)$cr['total_score'])).'٪':'—' ?></td>
        <td>
          <?php if($cr['attempt_id']): ?>
            <button type="button" class="btn btn-gold btn-sm reset-att-list-btn" data-att="<?= (int)$cr['attempt_id'] ?>" data-exam="<?= (int)$examId ?>">🔄 اجازه شرکت مجدد</button>
          <?php else: ?>
            <span class="muted" style="font-size:.82rem">نیازی به ریست نیست</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="panel reveal in" data-d="2" style="background:var(--surface-1);border:1px solid var(--border-soft);padding:24px;border-radius:var(--r-lg);overflow-x:auto">
  <div class="panel-head mb-4"><h3><?= icon('trophy',22) ?> جدول رتبه‌بندی و صدور کارنامه‌ها</h3></div>
  <?php if(!$results): ?>
    <div class="empty-state text-c" style="padding:40px"><span style="font-size:3rem;color:var(--gold);display:block;margin-bottom:12px"><?= icon('users',48) ?></span>هنوز کسی در این آزمون شرکت نکرده است.</div>
  <?php else: ?>
  <table class="tbl" style="width:100%;border-collapse:collapse;text-align:right">
    <thead>
      <tr style="border-bottom:2px solid var(--surface-2);color:var(--text-2);font-size:.9rem">
        <th style="padding:12px 8px">رتبه</th>
        <th style="padding:12px 8px">دانش‌آموز</th>
        <th style="padding:12px 8px text-c">درست ✓</th>
        <th style="padding:12px 8px text-c">غلط ×</th>
        <th style="padding:12px 8px text-c">نزده ⚪</th>
        <th style="padding:12px 8px text-c">درصد کنکور</th>
        <th style="padding:12px 8px text-c">عملیات کارنامه</th>
        <th style="padding:12px 8px text-c">شرکت مجدد</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($results as $i=>$r): ?>
      <tr style="border-bottom:1px solid var(--surface-2);transition:all 0.15s" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background=''">
        <td style="padding:14px 8px"><span class="badge <?= $i<3?'badge-gold':'' ?>" style="font-weight:900;font-family:monospace;font-size:1rem"><?= fa_num($i+1) ?></span></td>
        <td style="padding:14px 8px"><div class="u-row flex gap-2" style="align-items:center"><span class="u-ava <?= $i==0?'gold':'' ?>" style="width:34px;height:34px"><?= e(avatar_letters($r['full_name'])) ?></span><b style="font-size:1.05rem;color:var(--text-1)"><?= e($r['full_name']) ?></b></div></td>
        <td style="padding:14px 8px text-c;color:var(--sage);font-weight:900;font-size:1.1rem"><?= fa_num($r['correct_count']) ?></td>
        <td style="padding:14px 8px text-c;color:var(--danger);font-weight:900;font-size:1.1rem"><?= fa_num($r['wrong_count']) ?></td>
        <td style="padding:14px 8px text-c" class="muted font-weight-bold"><?= fa_num($r['blank_count']) ?></td>
        <td style="padding:14px 8px text-c"><span class="badge" style="background:var(--gold-glass);color:var(--gold-light);font-size:1.05rem;font-weight:900;padding:4px 12px"><?= fa_num(round((float)$r['total_score'])) ?>٪</span></td>
        <td style="padding:14px 8px text-c"><a href="?id=<?= $examId ?>&attempt=<?= (int)$r['id'] ?>" class="btn btn-ghost btn-sm flex gap-1" style="display:inline-flex;align-items:center;font-weight:800"><?= icon('eye',16) ?> مشاهده</a></td>
        <td style="padding:14px 8px text-c">
          <button type="button" class="btn btn-ghost btn-sm reset-att-list-btn" data-att="<?= (int)$r['id'] ?>" data-exam="<?= (int)$examId ?>" title="پاک‌کردن نمره و بازکردن آزمون برای این دانش‌آموز" style="color:var(--gold);border:1px solid var(--gold-glass);padding:4px 10px;font-weight:800">
            🔄 تکرار آزمون
          </button>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<script>
  document.querySelectorAll('.reset-att-list-btn').forEach(btn => {
    btn.addEventListener('click', async b => {
      const el = b.currentTarget;
      if (!confirm('آیا از حذف پاسخ‌برگ و صدور مجوز شرکت مجدد برای این دانش‌آموز مطمئنی؟')) return;

      el.disabled = true; el.innerHTML = '<span class="spinner" style="width:12px;height:12px"></span>';
      try {
        await api('<?= url('api/exam_builder.php') ?>', { method: 'POST', body: { action: 'reset_attempt', exam_id: el.dataset.exam, attempt_id: el.dataset.att } });
        toast('پاسخ‌برگ دانش‌آموز پاک شد. آزمون مجدداً برایش باز است 🔄', 'success');
        el.closest('tr').style.opacity = '0';
        setTimeout(() => location.reload(), 500);
      } catch(err) {
        toast(err.error || 'خطا در عملیات', 'error');
        el.disabled = false; el.innerHTML = '🔄 تکرار';
      }
    });
  });
</script>
<?php panel_end(); ?>
