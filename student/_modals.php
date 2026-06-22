<?php /** مودال‌های مشترک پنل دانش‌آموز (ثبت وضعیت سه‌حالته + یادداشت) */ ?>
<!-- task status modal -->
<div class="modal-backdrop" id="taskStatusModal">
  <div class="modal status-modal">
    <div class="modal-head">
      <h3><span id="smIcon">✓</span> ثبت وضعیت تسک</h3>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>
    <input type="hidden" id="smTaskId">
    <input type="hidden" id="smStatus">
    <p class="muted" style="margin-bottom:12px;font-size:.9rem"><span id="smTitle" style="color:var(--text);font-weight:800"></span></p>

    <div class="field" id="smAmountWrap">
      <label>تعداد انجام‌شده <span class="gold" id="smTargetText"></span> <small class="muted">اگر بیشتر زدی، عدد بیشتر وارد کن</small></label>
      <div class="amount-control">
        <input type="range" id="smRange" min="0" value="0" step="1" class="am-range">
        <input type="number" id="smCount" min="0" value="0" inputmode="numeric" class="input am-num" required>
      </div>
    </div>

    <div class="field">
      <label>درصد پوشش/کورس <span class="muted">(اجباری)</span></label>
      <div class="course-control">
        <input type="range" id="smCourseRange" min="0" max="100" value="100" step="5" class="am-range">
        <input type="number" id="smCourse" min="0" max="100" value="100" inputmode="numeric" class="input am-num" required>
        <span class="course-suffix">٪</span>
      </div>
    </div>

    <div class="field" id="smFeelingWrap" style="display:none">
      <label>حست نسبت به این تسک چی بود؟ <span class="muted">(اجباری برای تسک‌های خواندنی)</span></label>
      <div class="task-feelings">
        <button type="button" data-feeling="great">😄<span>عالی</span></button>
        <button type="button" data-feeling="good">🙂<span>خوب</span></button>
        <button type="button" data-feeling="hard">😵‍💫<span>سخت</span></button>
        <button type="button" data-feeling="tired">😴<span>خسته</span></button>
        <button type="button" data-feeling="bad">😣<span>بد</span></button>
      </div>
    </div>

    <button class="btn btn-gold btn-block mt-4" id="smConfirm"><?= icon('check',16) ?> ثبت وضعیت</button>
  </div>
</div>

<!-- note modal -->
<div class="modal-backdrop" id="noteModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-head"><h3><?= icon('note',18) ?> یادداشت تسک</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <p class="muted" style="font-size:.84rem;margin-bottom:12px">می‌توانی نکته، سؤال یا مشکلت روی این تسک را بنویسی؛ مشاورت آن را می‌بیند.</p>
    <input type="hidden" id="noteTaskId">
    <textarea class="input" id="noteText" rows="4" placeholder="مثلاً: تست‌های ترکیبی این مبحث سخت بود…"></textarea>
    <button class="btn btn-gold btn-block mt-4" id="saveNoteBtn"><?= icon('check',16) ?> ذخیره یادداشت</button>
  </div>
</div>
