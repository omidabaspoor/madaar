<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();
$advisor = $u['advisor_id'] ? get_user((int)$u['advisor_id']) : null;

$at = db()->prepare('SELECT COUNT(*) total, COALESCE(SUM(is_done),0) done FROM tasks WHERE student_id=?');
$at->execute([$u['id']]); $stats=$at->fetch();

panel_start('پروفایل', 'اطلاعات حساب تو', 'student', 'profile', ['auth.css','student.css']);
?>
<div class="panel reveal in mb-4">
  <div class="flex gap-4 wrap" style="align-items:center">
    <span class="u-ava" style="width:72px;height:72px;font-size:1.6rem"><?= e(avatar_letters($u['full_name'])) ?></span>
    <div style="flex:1">
      <h2 style="font-size:1.4rem"><?= e($u['full_name']) ?></h2>
      <div class="flex gap-2 wrap mt-2">
        <span class="badge">@<?= e($u['username']) ?></span>
        <?php if($u['field']):?><span class="badge badge-sage"><?= e($u['field']) ?></span><?php endif;?>
        <?php if($u['grade']):?><span class="badge"><?= e($u['grade']) ?></span><?php endif;?>
        <span class="badge badge-gold"><?= icon('fire',13) ?> <?= fa_num($u['streak']) ?> روز</span>
      </div>
    </div>
    <div class="flex gap-4">
      <div class="text-c"><div class="v" style="font-size:1.5rem;font-weight:800"><?= fa_num($stats['done']) ?></div><div class="muted" style="font-size:.78rem">تسک انجام‌شده</div></div>
      <?php if($advisor):?><div class="text-c"><div style="font-weight:700"><?= e($advisor['full_name']) ?></div><div class="muted" style="font-size:.78rem">مشاور تو</div></div><?php endif;?>
    </div>
  </div>
</div>

<div class="panel-grid cols-2">
  <div class="panel reveal" data-d="1">
    <div class="panel-head"><h3><?= icon('user',20) ?> اطلاعات شخصی</h3></div>
    <form method="post" action="<?= url('api/profile.php') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="field"><label>نام و نام خانوادگی</label><input class="input" name="full_name" value="<?= e($u['full_name']) ?>" required></div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="field"><label>رشته</label><select class="select" name="field"><option value="">—</option><?php foreach(['تجربی','ریاضی','انسانی','هنر','زبان'] as $f):?><option <?= $u['field']===$f?'selected':'' ?>><?= e($f) ?></option><?php endforeach;?></select></div>
        <div class="field"><label>پایه</label><select class="select" name="grade"><option value="">—</option><?php foreach(['دهم','یازدهم','دوازدهم','کنکوری'] as $g):?><option <?= $u['grade']===$g?'selected':'' ?>><?= e($g) ?></option><?php endforeach;?></select></div>
      </div>
      <div class="field"><label>موبایل</label><input class="input" name="phone" dir="ltr" value="<?= e($u['phone']) ?>" placeholder="09xxxxxxxxx"></div>
      <button class="btn btn-gold"><?= icon('check',16) ?> ذخیره تغییرات</button>
    </form>
  </div>

  <div class="panel reveal" data-d="2">
    <div class="panel-head"><h3><?= icon('lock',20) ?> تغییر گذرواژه</h3></div>
    <form method="post" action="<?= url('api/profile.php') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="field"><label>گذرواژه فعلی</label><input class="input" name="current" type="password" required></div>
      <div class="field"><label>گذرواژه جدید</label><input class="input" name="new" type="password" required></div>
      <div class="field"><label>تکرار گذرواژه جدید</label><input class="input" name="new2" type="password" required></div>
      <button class="btn btn-ghost"><?= icon('lock',16) ?> تغییر گذرواژه</button>
    </form>
  </div>
</div>
<?php panel_end(); ?>
