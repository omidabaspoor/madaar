<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();
$exams = advisor_exams((int)$u['id']);

panel_start('آزمون‌ها', 'طراحی و مدیریت آزمون‌ها', 'admin', 'exams', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <span class="badge badge-sage" style="padding:9px 14px"><?= icon('clipboard',15) ?> <?= fa_num(count($exams)) ?> آزمون</span>
  <a href="<?= url('admin/exam_builder.php') ?>" class="btn btn-gold"><?= icon('plus',16) ?> آزمون جدید</a>
</div>

<?php if (!$exams): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('clipboard',34) ?></div><p>هنوز آزمونی نساخته‌اید</p><p class="muted" style="font-size:.85rem">با دکمه‌ی «آزمون جدید» اولین آزمون را طراحی کنید.</p></div></div>
<?php else: ?>
<div class="exam-grid">
  <?php foreach ($exams as $e):
    $stColor = ['draft'=>'badge-gold','published'=>'badge-sage','closed'=>'badge']['' . $e['status']];
    $stText  = ['draft'=>'پیش‌نویس','published'=>'منتشر شده','closed'=>'بسته‌شده'][$e['status']];
    $tf = !empty($e['target_fields_json']) ? (json_decode((string)$e['target_fields_json'], true) ?: []) : [];
    $tg = !empty($e['target_grades_json']) ? (json_decode((string)$e['target_grades_json'], true) ?: []) : [];
    $targetText = (!$tf && !$tg) ? 'همه دانش‌آموزان' : (($tf ? 'رشته: '.implode('، ',$tf) : 'همه رشته‌ها') . ' · ' . ($tg ? 'پایه: '.implode('، ',$tg) : 'همه پایه‌ها')); ?>
  <div class="panel card-glow exam-card reveal">
    <div class="between" style="align-items:flex-start">
      <div style="flex:1;min-width:0">
        <div class="flex gap-2" style="align-items:center;margin-bottom:4px">
          <span class="badge <?= $e['exam_type']==='comprehensive'?'badge-gold':'' ?>" style="font-size:.7rem"><?= $e['exam_type']==='comprehensive'?'جامع':'تکی' ?></span>
          <span class="badge <?= $stColor ?>" style="font-size:.7rem"><?= e($stText) ?></span>
        </div>
        <div style="font-weight:800;font-size:1.05rem"><?= e($e['title']) ?></div>
        <div class="muted" style="font-size:.8rem;margin-top:3px"><?= e($e['description'] ?: '') ?></div>
      </div>
      <span class="icon-tile" style="width:44px;height:44px"><?= icon('clipboard',22) ?></span>
    </div>
    <div class="flex gap-2 wrap" style="margin-top:12px">
      <span class="badge" style="font-size:.72rem"><?= icon('list',12) ?> <?= fa_num($e['q_count']) ?> سوال</span>
      <span class="badge" style="font-size:.72rem"><?= icon('clock',12) ?> <?= fa_num($e['duration_min']) ?> دقیقه</span>
      <span class="badge" style="font-size:.72rem"><?= icon('users',12) ?> <?= fa_num($e['taken_count']) ?> شرکت‌کننده</span>
      <span class="badge badge-sage" style="font-size:.72rem" title="<?= e($targetText) ?>"><?= icon('target',12) ?> <?= e($targetText) ?></span>
    </div>
    <div class="flex gap-2 mt-4">
      <a href="<?= url('admin/exam_builder.php?id='.(int)$e['id']) ?>" class="btn btn-gold btn-sm" style="flex:1"><?= icon('edit',15) ?> ویرایش</a>
      <a href="<?= url('admin/exam_results.php?id='.(int)$e['id']) ?>" class="btn btn-ghost btn-sm"><?= icon('chart',15) ?> نتایج</a>
      <button class="btn btn-ghost btn-sm btn-icon" style="color:var(--danger)" data-del-exam="<?= (int)$e['id'] ?>" data-tip="حذف"><?= icon('trash',16) ?></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
  window.API_EXAM='<?= url('api/exam_builder.php') ?>';
  document.addEventListener('click',async(e)=>{
    const b=e.target.closest('[data-del-exam]'); if(!b)return;
    if(!confirm('این آزمون و همه‌ی سوالات و نتایجش حذف شود؟'))return;
    try{ await api(window.API_EXAM,{method:'POST',body:{action:'delete_exam',exam_id:b.dataset.delExam}});
      toast('آزمون حذف شد','success'); setTimeout(()=>location.reload(),600);
    }catch(err){ toast(err.error||'خطا','error'); }
  });
</script>
<?php panel_end(); ?>
