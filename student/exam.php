<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/layout.php';
boot_session();
require_role('student');
$u = current_user();

$examId = (int)($_GET['id'] ?? 0);
$exam = get_exam($examId);
if (!$exam || $exam['status'] !== 'published' || !student_exam_is_visible($examId, (int)$u['id'])) { flash('error','آزمون در دسترس نیست'); redirect('student/exams.php'); }
$now = time();
if ($exam['start_at'] && strtotime($exam['start_at']) > $now) { flash('error','آزمون هنوز شروع نشده'); redirect('student/exams.php'); }
if ($exam['end_at'] && strtotime($exam['end_at']) < $now) { flash('error','مهلت آزمون تمام شده'); redirect('student/exams.php'); }

$attempt = get_or_create_attempt($examId, (int)$u['id']);
if ($attempt['status'] === 'submitted') redirect('student/exam_result.php?attempt='.$attempt['id']);

$sections  = exam_sections($examId);
$questions = exam_questions($examId);
$answers   = attempt_answers((int)$attempt['id']);

// گروه‌بندی سوالات بر اساس بخش، با شماره‌گذاری سراسری
$qBySection = [];
$globalNum  = 0;
$flatQ      = []; 
foreach ($sections as $sec) {
    foreach ($questions as $q) {
        if ((int)$q['section_id'] !== (int)$sec['id']) continue;
        $globalNum++;
        $q['gnum'] = $q['question_number'] !== null ? (int)$q['question_number'] : $globalNum;
        $qBySection[(int)$sec['id']][] = $q;
        $flatQ[] = $q;
    }
}
$totalQ = count($flatQ);
$remain = $attempt['deadline_at'] ? max(0, strtotime($attempt['deadline_at']) - $now) : null;

// داده‌ی اولیه برای JS
$initAnswers = [];
foreach ($answers as $qid=>$a) {
    $initAnswers[$qid] = ['s'=>$a['selected_opt']!==null?(int)$a['selected_opt']:null,'f'=>(int)$a['flagged']];
}

$mode = $exam['creation_mode'] ?? 'quick_sheet';

// استخراج صفحات/فایل‌های دفترچه (عکس یا PDF)
$sheetArr = $exam['sheet_paths_json'] ? (json_decode($exam['sheet_paths_json'], true) ?: []) : [];
if (!empty($exam['sheet_path']) && !in_array($exam['sheet_path'], $sheetArr, true)) {
    array_unshift($sheetArr, $exam['sheet_path']);
}
$sheetItems = array_values(array_map(fn($p)=>[
    'rel'=>(string)$p,
    'url'=>sheet_view_url((string)$p, $examId),
    'type'=>sheet_asset_type((string)$p),
], $sheetArr));
$firstSheet = $sheetItems[0] ?? ['rel'=>'','url'=>'','type'=>'image'];

page_head('آزمون: ' . $exam['title'], '', ['exam.css']);
?>
<div class="exam-env" id="examEnv" data-attempt="<?= (int)$attempt['id'] ?>" data-total="<?= $totalQ ?>" data-review="<?= 0 ?>">

  <!-- ===== Top Bar ===== -->
  <header class="exam-bar between wrap gap-3" style="align-items:center;background:var(--surface-2);border-bottom:1px solid var(--border-soft);padding:12px 20px;transition:all 0.2s">
    <div class="exam-bar-info flex gap-3 wrap" style="align-items:center">
      <a href="<?= url('student/exams.php') ?>" class="btn btn-ghost btn-sm flex gap-1" style="color:var(--text-2);align-items:center"><?= icon('arrow-right',16) ?> خروج</a>
      <a href="<?= url('student/exam_pdf.php?id=' . $examId) ?>" target="_blank" class="btn btn-ghost btn-sm flex gap-1" style="border-color:var(--sage); color:var(--sage-light); font-weight:bold; align-items:center" title="چاپ یا دریافت PDF دفترچه سوالات"><?= icon('clipboard',16) ?> <span>خروجی PDF سوالات</span></a>
      <div class="eb-title" style="font-size:1.2rem;font-weight:900;color:var(--gold-light)"><?= e($exam['title']) ?></div>
      <div class="eb-sub badge badge-sage"><span id="answeredCount" style="font-weight:900">۰</span> / <?= fa_num($totalQ) ?> پاسخ‌داده</div>
    </div>
    
    <div class="flex gap-3" style="align-items:center">
      <?php if($remain !== null): ?>
      <div class="exam-timer badge badge-gold flex gap-2" id="examTimer" data-remain="<?= $remain ?>" style="padding:8px 16px;font-size:1.05rem;font-weight:900;direction:ltr">
        <?= icon('clock',20) ?> <span id="timerText">--:--</span>
      </div>
      <?php endif; ?>
      <button type="button" class="btn btn-ghost btn-sm flex gap-1 text-c" id="replayExamTour" style="font-weight:900"><?= icon('info',16) ?> راهنما</button>
      <button class="btn btn-gold btn-lg flex gap-1 text-c" id="finishBtn" style="font-weight:900;padding:0 24px"><?= icon('check',18) ?> پایان و ثبت آزمون</button>
    </div>
  </header>

  <!-- =================================================================
       SMART DUAL-PANEL LAYOUT (For Batch Quick Sheet Exams)
       ================================================================= -->
  <?php if ($mode==='quick_sheet' || !empty($sheetArr)): ?>
    <div class="grid gap-4 exam-smart-layout exam-samurai-layout" style="grid-template-columns:repeat(auto-fit, minmax(min(100%, 450px), 1fr));height:calc(100vh - 75px);padding:20px;max-width:1600px;margin:0 auto">
      
      <!-- پنل راست: دفترچه سوالات تعاملی با زوم و اسکرول و ورق‌زدن -->
      <section class="booklet-viewer-panel panel flex" style="flex-direction:column;padding:0;background:var(--surface-1);border:1px solid var(--border-soft);border-radius:var(--r-lg);overflow:hidden;position:relative;transition:all 0.2s">
        <div class="booklet-toolbar between panel-head wrap gap-2" style="padding:10px 16px;background:var(--surface-2);border-bottom:1px solid var(--border-soft);margin:0;align-items:center">
          <div class="flex gap-2" style="align-items:center">
            <span class="badge badge-gold flex gap-1" style="align-items:center;font-weight:900;font-size:.85rem" id="bookletTitleBadge"><?= icon($firstSheet['type']==='pdf'?'paperclip':'image',16) ?> دفترچه‌ی سوالات (ص ۱ از <?= count($sheetItems) ?>)</span>
            <span class="muted" id="bookletHint" style="font-size:.78rem"><?= $firstSheet['type']==='pdf'?'PDF داخل همین باکس نمایش داده می‌شود':'با موس/انگشت بکشید' ?></span>
          </div>

          <!-- ناوبری صفحات/فایل‌های چندتایی -->
          <?php if(count($sheetItems) > 1): ?>
            <div class="flex gap-1 booklet-pages-nav" style="align-items:center;background:var(--surface-1);padding:2px 6px;border-radius:6px;direction:ltr">
              <button type="button" class="btn btn-ghost btn-sm" id="prevSheetPageBtn" title="صفحه قبل" style="padding:0 8px;font-weight:bold" disabled>◀ قبلی</button>
              <select id="sheetPageSelect" class="select" style="margin:0;height:28px;padding:0 10px;font-size:.85rem;font-weight:bold;width:auto">
                <?php foreach($sheetItems as $si => $item): ?>
                  <option value="<?= (int)$si ?>" data-src="<?= e($item['url']) ?>" data-type="<?= e($item['type']) ?>">ص <?= $si+1 ?><?= $item['type']==='pdf'?' · PDF':'' ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-ghost btn-sm" id="nextSheetPageBtn" title="صفحه بعد" style="padding:0 8px;font-weight:bold">بعدی ▶</button>
            </div>
          <?php endif; ?>

          <div class="flex gap-1" id="imageZoomControls" style="align-items:center;direction:ltr;background:var(--surface-1);padding:2px 8px;border-radius:6px">
            <button type="button" class="btn btn-ghost btn-sm" id="zoomOutBtn" title="کوچک‌نمایی" style="font-weight:900;font-size:1.2rem;width:28px;height:28px;padding:0">-</button>
            <span id="zoomLevelText" style="font-family:monospace;font-size:.85rem;font-weight:bold;width:44px;text-align:center;color:var(--text-1)">100%</span>
            <button type="button" class="btn btn-ghost btn-sm" id="zoomInBtn" title="بزرگ‌نمایی" style="font-weight:900;font-size:1.2rem;width:28px;height:28px;padding:0">+</button>
            <button type="button" class="btn btn-ghost btn-sm" id="zoomResetBtn" title="اندازه اصلی" style="font-weight:900;font-size:1.1rem;width:28px;height:28px;padding:0;color:var(--gold)">↺</button>
          </div>
          <div class="flex gap-1 <?= $firstSheet['type']==='pdf'?'':'hidden' ?>" id="pdfPageControls" style="align-items:center;direction:ltr;background:var(--surface-1);padding:2px 8px;border-radius:6px">
            <button type="button" class="btn btn-ghost btn-sm" id="pdfPrevPage" style="padding:0 8px;font-weight:bold">◀</button>
            <span id="pdfPageText" style="font-family:monospace;font-size:.82rem;font-weight:900;min-width:62px;text-align:center;color:var(--text-1)">1/1</span>
            <button type="button" class="btn btn-ghost btn-sm" id="pdfNextPage" style="padding:0 8px;font-weight:bold">▶</button>
          </div>
        </div>
        
        <div class="booklet-scroll-area <?= $firstSheet['type']==='pdf'?'pdf-mode':'' ?>" id="bookletScrollArea" data-type="<?= e($firstSheet['type']) ?>" style="flex:1;overflow:hidden;padding:24px;background:#060a08;position:relative;cursor:grab;user-select:none;display:flex;align-items:center;justify-content:center">
          <img id="bookletImg" class="<?= $firstSheet['type']==='pdf'?'hidden':'' ?>" src="<?= $firstSheet['type']==='image' ? e($firstSheet['url']) : '' ?>" alt="Exam Booklet Sheet" style="max-width:none;max-height:none;transition:transform 0.08s ease;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,0.8);position:absolute;transform:translate(0px, 0px) scale(1)">
          <div id="bookletPdf" class="booklet-pdf-canvas-wrap <?= $firstSheet['type']==='pdf'?'':'hidden' ?>" data-src="<?= $firstSheet['type']==='pdf' ? e($firstSheet['url']) : '' ?>">
            <canvas id="bookletPdfCanvas"></canvas>
            <div class="pdf-page-loading hidden" id="pdfPageLoading"><span class="spinner"></span> در حال آماده‌سازی PDF…</div>
          </div>
          <div id="bookletPdfFallback" class="booklet-pdf-fallback hidden">
            <b>PDF داخل مرورگر آماده نشد.</b>
            <button type="button" id="bookletPdfRetry" class="btn btn-gold btn-sm">تلاش دوباره</button>
          </div>
        </div>
      </section>

      <!-- پنل چپ: پاسخ‌برگ حبابی تعاملی کنکور (Interactive 100-Bubble Sheet) -->
      <section class="bubble-sheet-panel panel flex" style="flex-direction:column;padding:0;background:var(--surface-1);border:1px solid var(--border-soft);border-radius:var(--r-lg);overflow:hidden;transition:all 0.2s">
        <div class="between panel-head wrap gap-2" style="padding:12px 20px;background:var(--surface-2);border-bottom:1px solid var(--border-soft);margin:0;align-items:center">
          <b style="color:#8ae6ab;font-size:1.1rem;display:flex;align-items:center;gap:8px"><?= icon('target',20) ?> پاسخ‌برگ حبابی کنکور</b>
          <span class="muted" style="font-size:.85rem">حباب گزینه‌ی صحیح را لمس کنید</span>
        </div>

        <div class="bubble-rows-container" style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:12px;direction:ltr">
          <?php foreach($flatQ as $qi => $q): 
              $sel = $initAnswers[$q['id']]['s'] ?? null;
              $fl  = $initAnswers[$q['id']]['f'] ?? 0;
          ?>
            <div class="bubble-row-item" data-q="<?= (int)$q['id'] ?>" data-gnum="<?= $qi+1 ?>" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:10px 20px;border-radius:12px;display:flex;align-items:center;justify-content:space-between;gap:16px;box-shadow:0 4px 12px rgba(0,0,0,0.1)">
              
              <div class="flex gap-3" style="align-items:center">
                <span style="font-family:monospace;font-size:1.1rem;font-weight:900;color:var(--text-1);width:40px">Q<?= $qi+1 ?></span>
                <button type="button" class="q-bookmark-btn <?= $fl?'active':'' ?>" data-flag title="علامت‌گذاری سوال برای مرور" style="border:none;background:<?= $fl?'var(--gold)':'var(--surface-1)' ?>;color:<?= $fl?'#000':'var(--text-2)' ?>;width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all 0.15s">
                  <?= icon('flag',16) ?>
                </button>
              </div>

              <div class="flex gap-2 bubble-options-group">
                <?php for($oi=1; $oi<=4; $oi++): ?>
                  <button type="button" class="bubble-opt-btn <?= $sel===$oi?'selected':'' ?>" data-opt="<?= $oi ?>" style="width:36px;height:36px;border-radius:50%;border:1px solid var(--border-soft);background:<?= $sel===$oi?'var(--gold)':'var(--surface-1)' ?>;color:<?= $sel===$oi?'#000':'var(--text-2)' ?>;font-size:.95rem;font-weight:900;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.1s">
                    <?= $oi ?>
                  </button>
                <?php endfor; ?>
              </div>
              
              <div style="width:36px;display:flex;justify-content:center">
                <button type="button" class="q-clear-btn <?= $sel?'':'hidden' ?>" data-clear title="پاک‌کردن پاسخ" style="background:rgba(217,116,116,0.15);border:1px solid rgba(217,116,116,0.3);color:var(--danger);width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;cursor:pointer">
                  <?= icon('close',16) ?>
                </button>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
        
        <div style="padding:16px 20px;background:var(--surface-2);border-top:1px solid var(--border-soft)">
          <button type="button" class="btn btn-gold btn-block btn-lg" id="finishSmartExamBtn" style="font-weight:900;font-size:1.1rem;padding:14px">
            <?= icon('check',20) ?> اتمام و ثبت نهایی پاسخ‌برگ
          </button>
        </div>
      </section>
    </div>

  <!-- =================================================================
       STANDARD TRADITIONAL EXAM LAYOUT
       ================================================================= -->
  <?php else: ?>
    <div class="exam-main">
      <div class="exam-questions" id="examQuestions">
        <?php $i=0; foreach ($flatQ as $q): $sel = $initAnswers[$q['id']]['s'] ?? null; $fl = $initAnswers[$q['id']]['f'] ?? 0;
          $secName=''; foreach($sections as $s){ if((int)$s['id']===(int)$q['section_id']){ $secName=$s['name']; break; } }
        ?>
        <div class="exam-q <?= $i===0?'active':'' ?>" data-q="<?= (int)$q['id'] ?>" data-index="<?= $i ?>" data-section="<?= (int)$q['section_id'] ?>">
          <div class="eq-head">
            <span class="eq-sec"><?= e($secName) ?></span>
            <span class="eq-num">سوال <?= fa_num($q['gnum']) ?> از <?= fa_num($totalQ) ?></span>
            <button class="eq-flag <?= $fl?'on':'' ?>" data-flag data-tip="علامت برای مرور"><?= icon('flag',16) ?></button>
          </div>
          <?php if($q['q_image']):?><div class="eq-image"><img src="<?= url($q['q_image']) ?>" alt="" loading="lazy"></div><?php endif;?>
          <div class="eq-text"><?= nl2br(e($q['q_text'] ?: '—')) ?></div>
          <div class="eq-options">
            <?php for($o=1;$o<=4;$o++): if($q['opt'.$o]===null || $q['opt'.$o]==='') continue; ?>
            <button class="eq-opt <?= $sel===$o?'selected':'' ?>" data-opt="<?= $o ?>">
              <span class="eqo-marker"><?= fa_num($o) ?></span>
              <span class="eqo-text"><?= e($q['opt'.$o]) ?></span>
            </button>
            <?php endfor; ?>
          </div>
          <button class="eq-clear <?= $sel?'':'hidden' ?>" data-clear><?= icon('close',14) ?> پاک‌کردن پاسخ</button>
        </div>
        <?php $i++; endforeach; ?>
      </div>

      <div class="exam-nav">
        <button class="btn btn-ghost" id="prevBtn"><?= icon('chevron-right',18) ?> قبلی</button>
        <button class="btn btn-ghost btn-icon" id="gridToggle" data-tip="فهرست سوالات"><?= icon('grid',18) ?></button>
        <button class="btn btn-gold" id="nextBtn">بعدی <?= icon('chevron-left',18) ?></button>
      </div>
    </div>

    <!-- Drawer -->
    <div class="qgrid-overlay" id="qgridOverlay"></div>
    <aside class="qgrid-panel" id="qgridPanel">
      <div class="qgrid-head"><h3><?= icon('grid',18) ?> فهرست سوالات</h3><button class="modal-close" id="gridClose"><?= icon('close',18) ?></button></div>
      <div class="qgrid-legend">
        <span><span class="lg answered"></span> پاسخ‌داده</span>
        <span><span class="lg flagged"></span> علامت‌دار</span>
        <span><span class="lg blank"></span> بی‌پاسخ</span>
      </div>
      <div class="qgrid-scroll">
        <?php foreach ($sections as $sec): if(empty($qBySection[(int)$sec['id']])) continue; ?>
        <div class="qgrid-sec-title"><?= e($sec['name']) ?></div>
        <div class="qgrid">
          <?php foreach ($qBySection[(int)$sec['id']] as $q): $sel=$initAnswers[$q['id']]['s']??null; $fl=$initAnswers[$q['id']]['f']??0; ?>
          <button class="qg-cell <?= $sel?'answered':'' ?> <?= $fl?'flagged':'' ?>" data-goto="<?= $q['gnum']-1 ?>" data-q="<?= (int)$q['id'] ?>"><?= fa_num($q['gnum']) ?></button>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-gold btn-block" id="finishBtn2" style="margin-top:14px"><?= icon('check',16) ?> پایان و ثبت آزمون</button>
    </aside>
  <?php endif; ?>
</div>

<!-- ===== Clean desktop/mobile onboarding tour ===== -->
<div id="examOnboardingTourOverlay" class="exam-tour-overlay hidden" aria-modal="true" role="dialog">
  <div class="exam-tour-card panel">
    <div class="exam-tour-top">
      <span class="badge badge-gold"><?= icon('sparkles',16) ?> راهنمای سریع آزمون</span>
      <button type="button" class="btn btn-ghost btn-sm skip-tour-btn" id="skipTourBtn" title="بستن راهنما">×</button>
    </div>
    <div class="tour-content">
      <div id="tourIco" class="exam-tour-ico"><?= icon('image',48) ?></div>
      <h3 id="tourTitle"></h3>
      <p id="tourText" class="muted"></p>
    </div>
    <div class="exam-tour-foot">
      <div class="tour-dots" id="tourDots" aria-label="مراحل راهنما"></div>
      <div class="flex gap-2">
        <button type="button" class="btn btn-ghost btn-sm" id="prevTourStepBtn" disabled>قبلی</button>
        <button type="button" class="btn btn-gold" id="nextTourStepBtn">بعدی</button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Confirm Submit Modal ===== -->
<div class="modal-backdrop" id="submitModal">
  <div class="modal panel" style="max-width:440px;background:var(--surface-1);border:1px solid var(--border-soft);border-radius:20px;padding:32px">
    <div class="modal-head between mb-3" style="align-items:center;border-bottom:1px solid var(--surface-2);padding-bottom:12px">
      <h3 style="font-size:1.3rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:10px"><?= icon('check-circle',22) ?> ثبت نهایی و پایان آزمون</h3>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>
    <p class="muted" style="margin-bottom:18px;font-size:.9۵rem;line-height:1.6">آیا از ثبت نهایی پاسخ‌برگ خود مطمئن هستید؟ پس از ثبت، خروجی‌های تحلیلی و ترازسنج کنکور آماده می‌شوند.</p>
    
    <div class="submit-summary between" style="background:var(--surface-2);padding:16px 24px;border-radius:12px;border:1px solid var(--border-soft)">
      <div class="ss-item text-c"><span class="v" id="ssAnswered" style="font-size:1.6rem;font-weight:900;color:var(--sage)">۰</span><div class="k muted mt-1" style="font-size:.8rem;font-weight:bold">پاسخ‌داده</div></div>
      <div class="ss-item text-c"><span class="v" id="ssBlank" style="font-size:1.6rem;font-weight:900;color:var(--text-3)">۰</span><div class="k muted mt-1" style="font-size:.8rem;font-weight:bold">بی‌پاسخ</div></div>
      <div class="ss-item text-c"><span class="v" id="ssFlagged" style="font-size:1.6rem;font-weight:900;color:var(--gold)">۰</span><div class="k muted mt-1" style="font-size:.8rem;font-weight:bold">علامت‌دار</div></div>
    </div>
    
    <div class="flex wrap gap-3 mt-4">
      <button class="btn btn-gold btn-lg text-c" style="flex:2;font-weight:900;padding:14px" id="confirmSubmit"><?= icon('check',18) ?> ثبت نهایی و صدور کارنامه</button>
      <button class="btn btn-ghost btn-lg text-c" style="flex:1;padding:14px" data-close>ادامه آزمون</button>
    </div>
  </div>
</div>

<script>
  window.API_EXAM_TAKE = '<?= url('api/exam_take.php') ?>';
  window.EXAM_INIT     = <?= json_encode($initAnswers, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
  window.EXAM_ID_PARAM = <?= (int)$examId ?>;
  window.PDFJS_URL     = '<?= asset('js/vendor/pdf.min.mjs') ?>';
  window.PDFJS_WORKER  = '<?= asset('js/vendor/pdf.worker.min.mjs') ?>';
</script>
<?php page_foot(['exam_take.js']); ?>
