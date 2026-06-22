<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
user_mood_schema_ready();
$u = get_user((int)current_user()['id']);

$status = in_array($_GET['status'] ?? '', ['active','pending','suspended'], true) ? $_GET['status'] : null;
$q = trim((string)($_GET['q'] ?? ''));
$students = advisor_students((int)$u['id'], $status, $q);

panel_start('دانش‌آموزان', fa_num(count($students)) . ' دانش‌آموز', 'admin', 'students');
?>
<!-- filters -->
<div class="panel reveal in" style="margin-bottom:18px">
  <form method="get" class="between wrap gap-3">
    <div class="flex gap-2 wrap">
      <a href="?" class="chip <?= !$status?'active':'' ?>">همه</a>
      <a href="?status=active" class="chip <?= $status==='active'?'active':'' ?>"><?= icon('check-circle',14) ?> فعال</a>
      <a href="?status=pending" class="chip <?= $status==='pending'?'active':'' ?>"><?= icon('clock',14) ?> در انتظار</a>
      <a href="?status=suspended" class="chip <?= $status==='suspended'?'active':'' ?>">مسدود</a>
    </div>
    <div class="input-group" style="max-width:260px">
      <span class="ig-icon"><?= icon('search',18) ?></span>
      <input class="input" name="q" value="<?= e($q) ?>" placeholder="جستجوی نام…">
      <?php if($status):?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif;?>
    </div>
  </form>
</div>

<?php if (!$students): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('users',34) ?></div><p>دانش‌آموزی یافت نشد</p></div></div>
<?php else: ?>
<div class="student-grid">
  <?php foreach ($students as $i=>$s):
    $pct = $s['total_tasks'] ? round($s['done_tasks']/$s['total_tasks']*100) : 0;
    $stColor = ['active'=>'badge-sage','pending'=>'badge-gold','suspended'=>'badge-danger'][$s['status']];
    $stText  = ['active'=>'فعال','pending'=>'در انتظار','suspended'=>'مسدود'][$s['status']]; ?>
  <div class="panel card-glow student-card reveal" data-d="<?= min($i+1,6) ?>">
    <div class="sc-top">
      <span class="u-ava <?= $s['status']==='pending'?'gold':'' ?>" style="width:46px;height:46px"><?= e(avatar_letters($s['full_name'])) ?></span>
      <div style="flex:1;min-width:0">
        <div style="font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($s['full_name']) ?></div>
        <div class="muted" style="font-size:.78rem">@<?= e($s['username']) ?></div>
      </div>
      <span class="badge <?= $stColor ?>"><?= e($stText) ?></span>
    </div>
    <div class="sc-meta">
      <?php if($s['field']):?><span class="badge"><?= e($s['field']) ?></span><?php endif;?>
      <?php if($s['grade']):?><span class="badge"><?= e($s['grade']) ?></span><?php endif;?>
      <span class="badge"><?= icon('fire',12) ?> <?= fa_num($s['streak']) ?></span>
      <?php $m = current_mood_info($s); if($m): ?>
      <span class="badge" style="border-color:<?= e($m['color']) ?>55"><?= $m['emoji'] ?> <?= e($m['label']) ?></span>
      <?php endif; ?>
    </div>
    <div>
      <div class="between" style="font-size:.78rem;margin-bottom:6px"><span class="muted">پیشرفت کل</span><span class="fw-700"><?= fa_num($pct) ?>٪ · <?= fa_num(score_display((float)$s['done_tasks'])) ?>/<?= fa_num($s['total_tasks']) ?></span></div>
      <div class="progress"><span data-w="<?= $pct ?>" style="width:0"></span></div>
    </div>
    <div class="sc-actions">
      <?php if ($s['status']==='pending'): ?>
        <form method="post" action="<?= url('admin/student_action.php') ?>" style="flex:1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="approve"><button type="submit" class="btn btn-sage btn-sm btn-block"><?= icon('check',15) ?> تأیید</button></form>
        <div class="sc-menu">
          <button type="button" class="btn btn-ghost btn-sm btn-icon" data-menu-toggle><?= icon('dots',16) ?></button>
          <div class="sc-dropdown hidden">
            <form method="post" action="<?= url('admin/student_action.php') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="approve"><button type="submit" class="side-link" style="width:100%;background:none;border:none;color:var(--sage-light)"><?= icon('check-circle',16) ?> تأیید عضویت</button></form>
            <form method="post" action="<?= url('admin/student_action.php') ?>" onsubmit="return confirm('حذف این درخواست؟')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="side-link" style="width:100%;background:none;border:none;color:var(--danger)"><?= icon('trash',16) ?> رد و حذف</button></form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= url('admin/plan_builder.php?student='.(int)$s['id']) ?>" class="btn btn-gold btn-sm" style="flex:1"><?= icon('calendar',15) ?> برنامه</a>
        <a href="<?= url('admin/messages.php?with='.(int)$s['id']) ?>" class="btn btn-ghost btn-sm btn-icon" data-tip="پیام"><?= icon('message',16) ?></a>
        <div class="sc-menu">
          <button type="button" class="btn btn-ghost btn-sm btn-icon" data-menu-toggle><?= icon('dots',16) ?></button>
          <div class="sc-dropdown hidden">
            <a href="<?= url('admin/reports.php?student='.(int)$s['id']) ?>" class="side-link"><?= icon('chart',16) ?> گزارش</a>
            <?php if($s['status']==='active'):?>
            <form method="post" action="<?= url('admin/student_action.php') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="suspend"><button type="submit" class="side-link" style="width:100%;color:var(--warn);background:none;border:none"><?= icon('lock',16) ?> مسدودسازی</button></form>
            <?php else:?>
            <form method="post" action="<?= url('admin/student_action.php') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="activate"><button type="submit" class="side-link" style="width:100%;color:var(--sage-light);background:none;border:none"><?= icon('check-circle',16) ?> فعال‌سازی</button></form>
            <?php endif;?>
            <form method="post" action="<?= url('admin/student_action.php') ?>" onsubmit="return confirm('حذف این دانش‌آموز؟ همه برنامه‌ها هم حذف می‌شوند.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="side-link" style="width:100%;color:var(--danger);background:none;border:none"><?= icon('trash',16) ?> حذف</button></form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<script>
(function(){
  function closeAll(except){
    document.querySelectorAll('.sc-dropdown').forEach(function(x){ if(x!==except) x.classList.add('hidden'); });
    document.querySelectorAll('.student-card.menu-open').forEach(function(c){
      if(!except || !c.contains(except)) c.classList.remove('menu-open');
    });
  }
  document.addEventListener('click', function(e){
    var tg = e.target.closest('[data-menu-toggle]');
    if (tg) {
      e.preventDefault(); e.stopPropagation();
      var menu = tg.parentElement;                 // .sc-menu
      var dd = menu.querySelector('.sc-dropdown');
      var card = tg.closest('.student-card');
      var willOpen = dd.classList.contains('hidden');
      closeAll(dd);
      if (willOpen) {
        dd.classList.remove('up','hidden');
        if (card) card.classList.add('menu-open');
        // flip up if there is no room below
        var r = dd.getBoundingClientRect();
        if (r.bottom > window.innerHeight - 12) dd.classList.add('up');
      } else {
        dd.classList.add('hidden');
        if (card) card.classList.remove('menu-open');
      }
      return;
    }
    if (!e.target.closest('.sc-dropdown')) closeAll(null);
  });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeAll(null); });
})();
</script>
<?php panel_end(); ?>
