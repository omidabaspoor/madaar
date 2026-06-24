<?php
/** قالب یک بخش/درس + سوالاتش (در exam_builder استفاده می‌شود) */
$secQs = $qBySection[(int)$sec['id']] ?? [];
?>
<div class="exam-section panel mb-6" data-section="<?= (int)$sec['id'] ?>">
  <div class="section-head between wrap gap-3 mb-4 border-b border-surface-2 pb-3">
    <div class="flex gap-2 wrap" style="align-items:center;flex:1">
      <span class="sec-handle"><?= icon('book',18) ?></span>
      <input class="input sec-name-input font-bold" data-sec-name value="<?= e($sec['name']) ?>" placeholder="نام درس (مثلاً شیمی)" style="max-w: 240px;">
      <input class="input sec-dur-input timing-section font-mono text-center" data-sec-dur type="number" min="1" value="<?= e($sec['duration_min'] ?? '') ?>" placeholder="دقیقه" style="w: 100px;">
    </div>
    <div class="flex items-center gap-2 wrap">
      <span class="badge sec-count"><?= fa_num(count(array_filter($secQs, fn($x)=>empty($x['is_cancelled'])))) ?> سوال فعال</span>
      <button type="button" onclick="renumberSectionQuestions(<?= (int)$sec['id'] ?>)" class="btn btn-ghost btn-sm flex items-center gap-1.5" style="border-color:var(--gold); color:var(--gold-light); font-weight:bold;">
        <?= icon('settings',14) ?> <span>تنظیم شماره شروع سوالات</span>
      </button>
      <button type="button" class="btn btn-ghost btn-sm btn-icon" data-del-section data-tip="حذف درس" style="color:var(--danger)"><?= icon('trash',15) ?></button>
    </div>
  </div>

  <div class="questions-wrap space-y-4">
    <?php foreach ($secQs as $idx=>$q): 
        $realNum = $q['question_number'] !== null ? (int)$q['question_number'] : ($idx + 1);
    ?>
    <div class="q-card panel card <?= !empty($q['is_cancelled'])?'q-cancelled':'' ?>" data-question="<?= (int)$q['id'] ?>" data-cancelled="<?= !empty($q['is_cancelled'])?'1':'0' ?>" style="background:var(--surface-1);">
      <?php if(!empty($q['is_cancelled'])): ?><div class="q-cancel-banner"><?= icon('close',14) ?> این سوال خط خورده و در نمره محاسبه نمی‌شود.</div><?php endif; ?>
      <div class="q-top flex items-center gap-3 wrap mb-3 border-b border-surface-2 pb-3">
        <div class="flex items-center gap-1">
          <span style="font-size:12px; color:var(--text-3); font-weight:bold;">شماره:</span>
          <input class="input font-mono font-bold text-center text-sm p-1.5 h-9 w-20" type="number" data-q-number value="<?= $realNum ?>" title="تنظیم شماره اختصاصی این سوال" onchange="triggerQuestionAutosave(this)">
        </div>
        <textarea class="input q-text font-bold flex-1" data-q-text rows="1" placeholder="متن دقیق سوال را بنویسید…"><?= e($q['q_text']) ?></textarea>
        <div class="q-tools flex items-center gap-1">
          <button type="button" class="btn btn-ghost btn-sm" data-insert-question-after data-tip="افزودن سوال بعد از این" style="border-color:rgba(203,172,128,.35);color:var(--gold-light);font-weight:900">+ بین</button>
          <label class="btn btn-ghost btn-sm q-img-btn" data-tip="افزودن عکس ضمیمه"><?= icon('paperclip',16) ?><input type="file" accept="image/*" data-q-img hidden></label>
          <button type="button" class="btn btn-ghost btn-sm" data-restore-question data-tip="فعال‌کردن دوباره سوال" style="color:var(--sage-light);<?= !empty($q['is_cancelled'])?'':'display:none' ?>"><?= icon('refresh',15) ?> فعال</button>
          <button type="button" class="btn btn-ghost btn-sm btn-icon" data-del-question data-tip="خط‌زدن سوال" style="color:var(--danger);<?= !empty($q['is_cancelled'])?'display:none':'' ?>"><?= icon('trash',15) ?></button>
        </div>
      </div>
      <div class="q-img-preview <?= $q['q_image']?'':'hidden' ?>" data-q-img-preview>
        <?php if($q['q_image']):?><img src="<?= url($q['q_image']) ?>" alt=""><?php endif;?>
        <button class="q-img-remove" data-q-img-remove><?= icon('close',14) ?></button>
      </div>
      <div class="q-options">
        <?php for($o=1;$o<=4;$o++): ?>
        <label class="q-opt <?= (int)$q['correct_opt']===$o?'correct':'' ?>" data-opt="<?= $o ?>">
          <input type="radio" name="correct_<?= (int)$q['id'] ?>" value="<?= $o ?>" data-correct <?= (int)$q['correct_opt']===$o?'checked':'' ?>>
          <span class="opt-marker"><?= fa_num($o) ?></span>
          <input class="opt-input" data-opt-text="<?= $o ?>" value="<?= e($q['opt'.$o]) ?>" placeholder="گزینه <?= fa_num($o) ?>">
          <span class="opt-correct-badge"><?= icon('check',13) ?></span>
        </label>
        <?php endfor; ?>
      </div>
      <details class="q-exp">
        <summary><?= icon('note',14) ?> پاسخ تشریحی (اختیاری)</summary>
        <textarea class="input" data-q-exp rows="2" placeholder="توضیح پاسخ صحیح…"><?= e($q['explanation']) ?></textarea>
      </details>
    </div>
    <?php endforeach; ?>
  </div>

  <button class="add-q-btn" data-add-question><?= icon('plus',16) ?> افزودن سوال به این درس</button>
</div>
