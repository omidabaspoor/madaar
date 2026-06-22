<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
$prevWeek = date('Y-m-d', strtotime($weekStart.' -7 day'));
$nextWeek = date('Y-m-d', strtotime($weekStart.' +7 day'));

$students = advisor_students((int)$u['id'], 'active');
// برنامه‌ی هر دانش‌آموز برای این هفته
$rows = [];
foreach ($students as $s) {
    $p = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? LIMIT 1');
    $p->execute([$s['id'],$weekStart]);
    $plan = $p->fetch();
    $prog = $plan ? plan_progress((int)$plan['id']) : ['total'=>0,'done'=>0,'percent'=>0];
    $rows[] = ['student'=>$s,'plan'=>$plan,'prog'=>$prog];
}

panel_start('برنامه‌ها', 'مدیریت برنامه‌های هفتگی', 'admin', 'plans', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="week-nav flex gap-2" style="align-items:center">
    <a href="?week=<?= $prevWeek ?>" class="btn btn-ghost btn-icon"><?= icon('chevron-right',18) ?></a>
    <span class="fw-700"><?= jalali_date($weekStart) ?> تا <?= jalali_date(date('Y-m-d', strtotime($weekStart.' +6 day'))) ?></span>
    <a href="?week=<?= $nextWeek ?>" class="btn btn-ghost btn-icon"><?= icon('chevron-left',18) ?></a>
  </div>
</div>

<?php if (!$rows): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('users',32) ?></div>دانش‌آموز فعالی نیست. اول دانش‌آموزان را تأیید کنید.</div></div>
<?php else: ?>
<div class="panel">
  <table class="tbl">
    <thead><tr><th>دانش‌آموز</th><th>وضعیت برنامه</th><th>تسک‌ها</th><th>پیشرفت</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $s=$r['student']; $plan=$r['plan']; $prog=$r['prog']; ?>
      <tr>
        <td><div class="u-row"><span class="u-ava"><?= e(avatar_letters($s['full_name'])) ?></span><div><div style="font-weight:700"><?= e($s['full_name']) ?></div><div class="muted" style="font-size:.76rem"><?= e($s['field'] ?: '') ?></div></div></div></td>
        <td>
          <?php if(!$plan):?><span class="badge">ساخته نشده</span>
          <?php elseif($plan['status']==='published'):?><span class="badge badge-sage"><?= icon('check-circle',13) ?> منتشر شده</span>
          <?php else:?><span class="badge badge-gold"><?= icon('edit',13) ?> پیش‌نویس</span><?php endif;?>
        </td>
        <td><?= fa_num($prog['total']) ?> تسک</td>
        <td style="min-width:160px"><div class="between" style="gap:10px"><div class="progress" style="flex:1"><span data-w="<?= $prog['percent'] ?>" style="width:0"></span></div><span style="font-size:.82rem;font-weight:700"><?= fa_num($prog['percent']) ?>٪</span></div></td>
        <td class="flex gap-2"><a href="<?= url('admin/plan_builder.php?student='.(int)$s['id'].'&week='.$weekStart) ?>" class="btn btn-gold btn-sm"><?= icon('edit',15) ?> ویرایش</a>
          <a href="<?= url('admin/reports.php?student='.(int)$s['id']) ?>" class="btn btn-ghost btn-sm btn-icon" data-tip="گزارش"><?= icon('chart',16) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
<?php panel_end(); ?>
