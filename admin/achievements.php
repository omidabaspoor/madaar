<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$achs = all_achievements();
$counts = achievement_award_counts();
$students = advisor_students((int)$u['id'], 'active');
$totalStudents = count($students);
$ICON_OPTS = ['trophy','star','fire','target','rocket','zap','heart','book','check-circle','sparkles','flag','graduation'];
$CT = ['tasks_done'=>'تعداد تسک انجام‌شده','streak'=>'استریک (روز پیاپی)','manual'=>'دستی (اعطای مشاور)'];

panel_start('دستاوردها', 'تعریف و مدیریت نشان‌ها', 'admin', 'achievements', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <span class="badge badge-sage" style="padding:9px 14px"><?= icon('trophy',15) ?> <?= fa_num(count($achs)) ?> دستاورد تعریف‌شده</span>
  <button class="btn btn-gold" id="newAchBtn"><?= icon('plus',16) ?> دستاورد جدید</button>
</div>

<?php if (!$achs): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('trophy',34) ?></div><p>هنوز دستاوردی تعریف نکرده‌اید</p><p class="muted" style="font-size:.85rem">با دکمه‌ی «دستاورد جدید» اولین نشان را بسازید.</p></div></div>
<?php else: ?>
<div class="ach-admin-grid">
  <?php foreach ($achs as $a): $cnt = $counts[(int)$a['id']] ?? 0; ?>
  <div class="panel ach-admin-card reveal <?= $a['is_active']?'':'inactive' ?>">
    <div class="flex gap-3" style="align-items:flex-start">
      <span class="ach-ico" style="width:50px;height:50px"><?= icon($a['icon'],24) ?></span>
      <div style="flex:1;min-width:0">
        <div class="between"><div style="font-weight:800"><?= e($a['title']) ?></div>
          <?php if(!$a['is_active']):?><span class="badge badge-danger" style="font-size:.68rem">غیرفعال</span><?php endif;?>
        </div>
        <div class="muted" style="font-size:.82rem;margin-top:2px"><?= e($a['description'] ?: '—') ?></div>
        <div class="flex gap-2 wrap" style="margin-top:8px">
          <span class="badge" style="font-size:.7rem">
            <?php if($a['condition_type']==='manual'):?><?= icon('user',12) ?> دستی
            <?php elseif($a['condition_type']==='tasks_done'):?><?= icon('check-circle',12) ?> <?= fa_num($a['threshold']) ?> تسک
            <?php else:?><?= icon('fire',12) ?> <?= fa_num($a['threshold']) ?> روز استریک<?php endif;?>
          </span>
          <span class="badge badge-gold" style="font-size:.7rem"><?= icon('users',12) ?> <?= fa_num($cnt) ?> نفر کسب کرده</span>
        </div>
      </div>
    </div>
    <div class="flex gap-2 mt-4">
      <button class="btn btn-ghost btn-sm" style="flex:1" data-recipients="<?= (int)$a['id'] ?>" data-title="<?= e($a['title']) ?>"><?= icon('users',15) ?> دریافت‌کنندگان</button>
      <?php if($a['condition_type']==='manual'):?>
      <button class="btn btn-sage btn-sm btn-icon" data-award="<?= (int)$a['id'] ?>" data-title="<?= e($a['title']) ?>" data-tip="اعطای دستی"><?= icon('plus',16) ?></button>
      <?php endif;?>
      <button class="btn btn-ghost btn-sm btn-icon" data-edit='<?= e(json_encode($a, JSON_UNESCAPED_UNICODE)) ?>' data-tip="ویرایش"><?= icon('edit',16) ?></button>
      <button class="btn btn-ghost btn-sm btn-icon" style="color:var(--danger)" data-del-ach="<?= (int)$a['id'] ?>" data-tip="حذف"><?= icon('trash',16) ?></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== create/edit modal ===== -->
<div class="modal-backdrop" id="achModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-head"><h3 id="achModalTitle"><?= icon('trophy',18) ?> دستاورد جدید</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <form id="achForm">
      <input type="hidden" name="id" id="ach_id">
      <div class="field"><label>عنوان دستاورد *</label><input class="input" id="ach_title" name="title" placeholder="مثلاً قهرمان هفته" required></div>
      <div class="field"><label>توضیح کوتاه</label><input class="input" id="ach_desc" name="description" placeholder="مثلاً ۷ روز پیاپی فعالیت"></div>
      <div class="field">
        <label>آیکون</label>
        <div class="icon-pick" id="iconPick">
          <?php foreach ($ICON_OPTS as $ic): ?>
          <button type="button" class="icon-opt <?= $ic==='trophy'?'active':'' ?>" data-icon="<?= $ic ?>"><?= icon($ic,20) ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="icon" id="ach_icon" value="trophy">
      </div>
      <div class="field">
        <label>شرط کسب</label>
        <select class="select" id="ach_ctype" name="condition_type">
          <?php foreach ($CT as $k=>$v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
        </select>
        <p class="help" id="ctypeHelp">به‌صورت دستی توسط شما به دانش‌آموز اعطا می‌شود.</p>
      </div>
      <div class="field" id="thrField" style="display:none">
        <label id="thrLabel">مقدار آستانه</label>
        <input class="input" id="ach_thr" name="threshold" type="number" min="0" inputmode="numeric" placeholder="مثلاً ۵۰">
      </div>
      <label class="checkbox" style="margin-bottom:16px"><input type="checkbox" id="ach_active" name="is_active" value="1" checked><span class="box"><?= icon('check',14) ?></span><span style="font-size:.9rem">فعال باشد</span></label>
      <div class="flex gap-3">
        <button type="submit" class="btn btn-gold" style="flex:1"><?= icon('check',16) ?> ذخیره</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== recipients / award modal ===== -->
<div class="modal-backdrop" id="recipModal">
  <div class="modal" style="max-width:460px">
    <div class="modal-head"><h3 id="recipTitle"><?= icon('users',18) ?> دریافت‌کنندگان</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <div id="recipBody"><div class="empty-state"><span class="spinner"></span></div></div>
  </div>
</div>

<!-- ===== award-to-student modal ===== -->
<div class="modal-backdrop" id="awardModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-head"><h3><?= icon('plus',18) ?> اعطای دستاورد</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <p class="muted" style="font-size:.86rem;margin-bottom:12px">دستاورد «<b id="awardAchTitle" class="gold"></b>» را به کدام دانش‌آموز می‌دهید؟</p>
    <input type="hidden" id="award_ach_id">
    <div class="field"><input class="input" id="awardSearch" placeholder="جستجوی دانش‌آموز…"></div>
    <div id="awardList" style="max-height:300px;overflow-y:auto"></div>
  </div>
</div>

<script>
  window.API_ACH = '<?= url('api/achievements.php') ?>';
  window.API_ACH_RECIP = '<?= url('api/achievement_recipients.php') ?>';
  window.STUDENTS = <?= json_encode(array_map(fn($s)=>['id'=>(int)$s['id'],'name'=>$s['full_name'],'field'=>$s['field']], $students), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php panel_end(['admin_ach.js']); ?>
