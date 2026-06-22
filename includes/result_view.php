<?php
/** نمای کارنامه پیشرفته + پاسخنامه تحلیلی و آسیب‌شناسی هوشمند (Smart Exam Report Card & Diagnostic Post-Mortem) */
declare(strict_types=1);

function render_result(array $rep, bool $showAnswers = true): void
{
    $att       = $rep['attempt']; 
    $exam      = $rep['exam']; 
    $sections  = $rep['sections']; 
    $questions = $rep['questions'];

    $correct = (int)$att['correct_count'];
    $wrong   = (int)$att['wrong_count'];
    $blank   = (int)$att['blank_count'];
    $total   = $correct + $wrong + $blank;

    $konkurPct = round((float)$att['total_score'], 1);
    $rawPct    = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
    $answered  = $correct + $wrong;
    $precision = $answered > 0 ? round(($correct / $answered) * 100) : 0;
    
    // محاسبه آمار کاملاً واقعی از پایگاه داده (رتبه واقعی داوطلب در این آزمون و میانگین کل)
    $stmtRank = db()->prepare("SELECT student_id, total_score FROM exam_attempts WHERE exam_id = ? AND status = 'submitted' ORDER BY total_score DESC, submitted_at ASC");
    $stmtRank->execute([(int)$exam['id']]);
    $allSubmissions = $stmtRank->fetchAll();
    
    $totalSubmissionsCount = count($allSubmissions);
    $actualRank = 1;
    $sumScores = 0.0;
    foreach ($allSubmissions as $idx => $sub) {
        $sumScores += (float)$sub['total_score'];
        if ((int)$sub['student_id'] === (int)$att['student_id']) {
            $actualRank = $idx + 1;
        }
    }
    $actualAvgPct = $totalSubmissionsCount > 0 ? round($sumScores / $totalSubmissionsCount, 1) : 0;

    // توصیه‌ها و تحلیل‌های کاملاً واقعی مبتنی بر آمار دقیق همین آزمون
    $smartAdvice = [];
    if ($total === 0) {
        $smartAdvice = ['title'=>'داده جهت ارزیابی کافی نیست','text'=>'آزمون فاقد پاسخ‌برگ یا سوال ثبت‌شده است.','class'=>'info'];
    } elseif ($wrong === 0 && $correct > 0) {
        $smartAdvice = ['title'=>'عملکرد بی‌نقص و بدون نمره منفی! 🏆','text'=>'تبریک! شما هیچ پاسخ غلطی در این آزمون نداشتید و کنترل نمره منفی شما ۱۰۰٪ موفق بوده است. استراتژی گزینش سوالات شما کاملاً علمی و دقیق است.','class'=>'success'];
    } elseif ($konkurPct >= $actualAvgPct && $precision >= 75) {
        $smartAdvice = ['title'=>'عملکرد قدرتمند و بالاتر از میانگین 🌟','text'=>'نمره کل شما از میانگین کل شرکت‌کنندگان بالاتر است و ضریب دقت مطلوبی (بالای ۷۵٪) ثبت کرده‌اید. با بررسی و تحلیل دقیق تست‌های غلط، عملکرد خود را ارتقاء دهید.','class'=>'success'];
    } elseif ($wrong > $correct) {
        $smartAdvice = ['title'=>'هشدار: نمره منفی سنگین و پیشی‌گرفتن پاسخ‌های غلط! ⚠️','text'=>'تعداد پاسخ‌های اشتباه شما از پاسخ‌های صحیح بیشتر است. این موضوع به دلیل حدس‌زنی گزینه‌ها یا بی‌دقتی در محاسبات رخ داده و باعث کاهش درصد کل شده است.','class'=>'warn'];
    } elseif ($blank > $total * 0.5) {
        $smartAdvice = ['title'=>'نیاز به ارتقاء سرعت و حل تست‌های آموزشی بیشتر ⚡','text'=>'شما به بیش از نیمی از سوالات آزمون پاسخ نداده‌اید. هرچند از نمره منفی جلوگیری شده، اما برای کسب نمرات برتر باید سرعت انتقال و تسلط خود را افزایش دهید.','class'=>'warn'];
    } else {
        $smartAdvice = ['title'=>'نیازمند بازنگری در شیوه مطالعه و مدیریت زمان ⚠️','text'=>'پیشنهاد فوری: خطاهای خود را ریشه‌یابی کرده و قبل از شرکت در آزمون بعدی، حداقل ۳۰ تست آموزشی با تحلیل تک‌به‌تک انجام دهید.','class'=>'warn'];
    }

    $sheetPath = $exam['sheet_path'] ?? null;

    // استخراج آسیب‌شناسی‌های ثبت‌شده‌ی دانش‌آموز از دیتابیس
    $ansMap = [];
    $stmt = db()->prepare('SELECT question_id, diagnostic_reason, diagnostic_takeaway FROM exam_answers WHERE attempt_id=?');
    $stmt->execute([(int)$att['id']]);
    foreach ($stmt->fetchAll() as $rr) {
        $ansMap[(int)$rr['question_id']] = $rr;
    }

    // آمار کلان آسیب‌شناسی
    $diagCounts = [
      'بی‌دقتی / شتاب‌زدگی' => 0,
      'نقص علمی / ضعف مفهوم' => 0,
      'فراموشی فرمول یا نکته' => 0,
      'دام طراح سوال' => 0,
      'کمبود وقت / استرس' => 0
    ];
    foreach ($ansMap as $ans) {
        $r = (string)($ans['diagnostic_reason'] ?? '');
        if ($r && isset($diagCounts[$r])) $diagCounts[$r]++;
    }
    ?>

<!-- ===== REPORT CARD HERO ===== -->
<div class="panel mb-4 flex" style="flex-direction:column;justify-content:space-between;background:linear-gradient(135deg, rgba(111,155,192,0.12) 0%, rgba(12,21,18,0.7) 100%);border:1px solid var(--info);padding:32px;border-radius:var(--r-lg);box-shadow:0 12px 36px rgba(0,0,0,0.4)">
  <div class="between wrap gap-4 mb-4" style="align-items:center">
    
    <div class="hero-titles" style="flex:1;min-width:280px">
      <div class="flex gap-2 mb-2" style="align-items:center">
        <span class="badge" style="background:var(--info);color:#fff;padding:4px 12px;font-weight:900">کارنامه رسمی و تحلیلی آزمون</span>
        <span class="muted" style="font-size:.85rem">الگوریتم ارزیابی مَدار</span>
      </div>
      <h1 style="font-size:2rem;font-weight:900;color:var(--text-1);margin-bottom:6px"><?= e($exam['title']) ?></h1>
      <p class="muted" style="font-size:.95rem;font-weight:700"><?= e($att['full_name'] ?? 'دانش‌آموز') ?> · ثبت نهایی: <?= jalali_date($att['submitted_at'] ?? '', true) ?></p>
    </div>

    <div class="flex wrap gap-4" style="align-items:center;justify-content:center">
      
      <!-- رتبه واقعی در آزمون -->
      <div class="text-c" style="background:var(--surface-1);border:1px solid var(--border-soft);padding:16px 28px;border-radius:20px;min-width:160px;box-shadow:0 8px 24px rgba(0,0,0,0.3)">
        <span class="muted" style="font-size:.8rem;font-weight:800;display:block;text-transform:uppercase;letter-spacing:1px">رتبه در این آزمون</span>
        <b style="font-size:2.4rem;font-weight:900;color:var(--info);font-family:monospace;margin:4px 0;display:block;letter-spacing:1px"><?= fa_num($actualRank) ?></b>
        <span class="badge flex items-center justify-center gap-1" style="background:rgba(111,155,192,0.15);color:var(--info);font-size:.7۵rem">از <?= fa_num($totalSubmissionsCount) ?> شرکت‌کننده</span>
      </div>

      <!-- درصد واقعی نمره کل -->
      <div class="text-c relative" style="background:var(--surface-1);border:2px solid <?= $konkurPct>=50 ? 'var(--sage)' : ($konkurPct>=25 ? 'var(--gold)' : 'var(--danger)') ?>;padding:16px 28px;border-radius:20px;min-width:160px;box-shadow:0 8px 24px rgba(0,0,0,0.3)">
        <span class="muted" style="font-size:.8rem;font-weight:800;display:block">نمره کل (با نمره منفی)</span>
        <b style="font-size:2.4rem;font-weight:900;color:<?= $konkurPct>=50 ? '#8ae6ab' : ($konkurPct>=25 ? 'var(--gold-light)' : '#ff9a9a') ?>;font-family:monospace;margin:4px 0;display:block"><?= fa_num($konkurPct) ?>٪</b>
        <span style="font-size:.7۵rem;color:var(--text-3);font-weight:bold">میانگین کل: <?= fa_num($actualAvgPct) ?>٪</span>
      </div>

    </div>

  </div>

  <?php if(!empty($smartAdvice['title'])): ?>
    <div class="coaching-alert mt-4" style="background:var(--surface-1);border-right:4px solid <?= $smartAdvice['class']==='success'?'var(--sage)':($smartAdvice['class']==='warn'?'var(--gold)':'var(--cyan)') ?>;padding:18px 24px;border-radius:12px;border:1px solid var(--border-soft)">
      <b style="font-size:1.1rem;font-weight:900;color:<?= $smartAdvice['class']==='success'?'#8ae6ab':($smartAdvice['class']==='warn'?'var(--gold-light)':'var(--text-1)') ?>;display:block;margin-bottom:6px">
        <?= $smartAdvice['title'] ?>
      </b>
      <p style="font-size:.95rem;color:var(--text-2);line-height:1.6;margin:0">
        <?= e($smartAdvice['text']) ?>
      </p>
    </div>
  <?php endif; ?>
</div>

<!-- ===== RAW PERFORMANCE METRICS ===== -->
<div class="stat-cards mb-4" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr))">
  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile sage" style="width:48px;height:48px;font-size:1.4rem">✓</span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--sage)"><?= fa_num($correct) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">پاسخ صحیح</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:rgba(217,116,116,0.18);color:#ff9a9a;width:48px;height:48px;font-size:1.4rem">✗</span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--danger)"><?= fa_num($wrong) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">پاسخ غلط (نمره منفی)</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:rgba(255,255,255,0.1);color:var(--text-3);width:48px;height:48px;font-size:1.4rem">⚪</span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--text-1)"><?= fa_num($blank) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">سوال بی‌پاسخ (نزده)</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:rgba(111,155,192,0.15);color:var(--info);width:48px;height:48px"><?= icon('target',24) ?></span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--info)"><?= fa_num($precision) ?>٪</div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">ضریب دقت (صحیح از کل‌زده)</div>
    </div>
  </div>
</div>

<!-- ===== DIAGNOSTIC ROOT CAUSE MASTER SUMMARY ===== -->
<?php if(false && array_sum($diagCounts) > 0): ?>
<div class="panel mb-4" style="background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:24px">
  <div class="panel-head mb-4 between wrap gap-2" style="align-items:center">
    <h3 style="font-size:1.25rem;font-weight:900;color:var(--info);display:flex;align-items:center;gap:10px"><?= icon('chart',22) ?> نمودار خلاصه ریشه‌یابی و آسیب‌شناسی اشتباهات آزمون</h3>
    <span class="badge" style="background:var(--surface-3);color:var(--text)">ثبت‌شده توسط دانش‌آموز</span>
  </div>
  
  <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr))">
    <?php foreach($diagCounts as $dlbl => $dcnt): ?>
      <div class="panel stat flex" style="flex-direction:row;align-items:center;justify-content:space-between;background:var(--surface-1);border:1px solid <?= $dcnt?'var(--info)':'var(--border-soft)' ?>;padding:14px 18px;border-radius:12px">
        <span style="font-size:.9rem;font-weight:800;color:var(--text-2)"><?= e($dlbl) ?></span>
        <b style="font-family:monospace;font-size:1.1rem;color:<?= $dcnt?'var(--info)':'var(--text-3)' ?>"><?= fa_num($dcnt) ?></b>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ===== INTERACTIVE ANALYTICAL SOLUTIONS LIST ===== -->
<div class="between mb-4 wrap gap-2" style="align-items:center">
  <h3 style="font-size:1.4rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:10px">
    <?= icon('list',24) ?> پاسخنامه تشریحی آزمون
  </h3>
  <div class="flex gap-1 wrap ans-filters-group" style="background:var(--surface-2);padding:4px;border-radius:999px;border:1px solid var(--border-soft)">
    <button type="button" class="ans-filter-btn active badge" data-filter="all" style="padding:6px 14px;cursor:pointer;font-weight:800;border:none">همه‌ی سوالات</button>
    <button type="button" class="ans-filter-btn badge" data-filter="correct" style="padding:6px 14px;cursor:pointer;font-weight:800;border:none;color:var(--success)">✓ صحیح‌ها</button>
    <button type="button" class="ans-filter-btn badge" data-filter="wrong" style="padding:6px 14px;cursor:pointer;font-weight:800;border:none;color:var(--danger)">✗ غلط‌ها</button>
    <button type="button" class="ans-filter-btn badge" data-filter="blank" style="padding:6px 14px;cursor:pointer;font-weight:800;border:none;color:var(--text-3)">⚪ نزده‌ها</button>
  </div>
</div>

<div id="answerSheetContainer">
  <?php foreach ($questions as $qItem): 
    $q    = $qItem['q']; 
    $gnum = $qItem['gnum']; 
    $st   = $qItem['state']; 
    $sel  = $qItem['selected'];
    $cOpt = (int)($q['correct_opt'] ?? 0);

    $diag = $ansMap[(int)$q['id']] ?? [];
    $dReason   = (string)($diag['diagnostic_reason'] ?? '');
    $dTakeaway = (string)($diag['diagnostic_takeaway'] ?? '');

    $stBadgeBg = match($st) { 'correct'=>'rgba(95,174,123,0.18)', 'wrong'=>'rgba(217,116,116,0.18)', default=>'var(--surface-3)' };
    $stBadgeTxt = match($st) { 'correct'=>'var(--success)', 'wrong'=>'var(--danger)', default=>'var(--text-2)' };
    $stBadgeBd = match($st) { 'correct'=>'rgba(95,174,123,0.4)', 'wrong'=>'rgba(217,116,116,0.4)', default=>'var(--border)' };
    $stBadgeLbl = match($st) { 'correct'=>'✓ پاسخ صحیح', 'wrong'=>'✗ پاسخ غلط', default=>'⚪ بی‌پاسخ (نزده)' };
  ?>
    <article class="ans-q-item panel mb-4 reveal flex" data-qid="<?= (int)$q['id'] ?>" data-state="<?= e($st) ?>" style="flex-direction:column;background:var(--surface-1);border:1px solid var(--border-soft);padding:26px;border-radius:var(--r-lg);position:relative">
      
      <!-- Head -->
      <div class="between wrap gap-2 mb-4" style="border-bottom:1px solid var(--surface-2);padding-bottom:16px;align-items:center">
        <div class="flex gap-3" style="align-items:center">
          <span class="q-num-box" style="width:36px;height:36px;border-radius:12px;background:var(--surface-3);color:var(--text-1);display:grid;place-items:center;font-weight:900;font-family:monospace;font-size:1.1rem;border:1px solid var(--border)">
            <?= fa_num($gnum) ?>
          </span>
          <div>
            <b style="font-size:1.1rem;color:var(--text-1)">سوال <?= fa_num($gnum) ?></b>
            <span class="muted" style="font-size:.85rem;margin-right:8px">سرفصل: <?= e($qItem['sec']) ?></span>
          </div>
        </div>
        
        <span class="badge" style="background:<?= $stBadgeBg ?>;color:<?= $stBadgeTxt ?>;border:1px solid <?= $stBadgeBd ?>;padding:6px 16px;font-size:.9rem;font-weight:900">
          <?= $stBadgeLbl ?>
        </span>
      </div>

      <!-- Question Content -->
      <div class="ans-q-body">
        <?php if($q['q_text']): ?>
          <div class="ans-text mb-4" style="font-size:1rem;font-weight:800;color:var(--text-1);line-height:1.7">
            <?= nl2br(e($q['q_text'])) ?>
          </div>
        <?php endif; ?>

        <?php if($q['q_image']): ?>
          <img src="<?= e(sheet_view_url((string)$q['q_image'])) ?>" alt="" class="mb-4" style="max-width:80%;max-height:280px;border-radius:14px;border:1px solid var(--border);margin:0 auto;display:block">
        <?php endif; ?>

        <div class="ans-options-matrix grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));direction:ltr">
          <?php for($o=1; $o<=4; $o++): 
            $isCor = ($cOpt === $o);
            $isSel = ($sel   === $o);
            $oBg = $isCor ? 'var(--sage)' : ($isSel ? 'var(--danger)' : 'var(--surface-2)');
            $oTxt = $isCor ? '#000' : ($isSel ? '#fff' : 'var(--text-2)');
            $oBd  = $isCor ? 'var(--sage)' : ($isSel ? 'var(--danger)' : 'var(--border-soft)');
          ?>
            <div class="opt-pill flex gap-2" style="background:<?= $oBg ?>;color:<?= $oTxt ?>;border:1px solid <?= $oBd ?>;padding:8px 14px;border-radius:10px;align-items:center;font-weight:900;font-size:.9rem;<?= ($isCor || $isSel)?'box-shadow:0 0 16px '.$oBg.'44':'' ?>">
              <span style="font-family:monospace;background:rgba(0,0,0,0.3);padding:2px 8px;border-radius:6px;font-size:.85rem"><?= $o ?></span>
              <span style="direction:rtl;text-align:right;flex:1;word-break:break-word;overflow-wrap:break-word;white-space:normal;font-size:.85rem;line-height:1.5">
                <?= e($q['opt'.$o] ?? ($isCor ? '✓ کلید صحیح' : ($isSel ? '✗ پاسخ شما' : 'گزینه '.$o))) ?>
              </span>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- تحلیل علت‌های غلط/نزده از کارنامه حذف شد؛ در صفحه «تحلیل آزمون داخلی مَدار» انجام می‌شود. -->
      <?php if(false && ($st==='wrong' || $st==='blank')): ?>
        <div class="diagnostic-box mt-3 panel flex" style="flex-direction:column;gap:14px;background:var(--surface-2);border:2px solid <?= $dReason?'var(--info)':'var(--border)' ?>;padding:20px;border-radius:16px">
          <div class="between wrap gap-2" style="align-items:center;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:12px">
            <b style="color:var(--info);font-size:.9۵rem;display:flex;align-items:center;gap:8px">
              <?= icon('sparkles',18) ?> ریشه‌یابی و آسیب‌شناسی (چرا این تست <?= $st==='wrong'?'اشتباه زده شد':'نزده ماند' ?>؟)
            </b>
            <span class="save-diag-status badge <?= $dReason?'badge-sage':'' ?>" style="font-size:.8rem;padding:4px 10px;font-weight:900">
              <?= $dReason ? '✓ علت ثبت شده' : 'انتخاب نشده' ?>
            </span>
          </div>

          <div class="flex wrap gap-2 diag-reasons-group">
            <?php foreach([
              'بی‌دقتی / شتاب‌زدگی' => '⚠️ بی‌دقتی / شتاب‌زدگی',
              'نقص علمی / ضعف مفهوم' => '📖 ضعف علمی / نقص مفهوم',
              'فراموشی فرمول یا نکته' => '❓ فراموشی فرمول یا نکته',
              'دام طراح سوال' => '🕸️ دام طراح سوال',
              'کمبود وقت / استرس' => '⏳ کمبود وقت / استرس'
            ] as $dk => $dlbl): ?>
              <button type="button" class="diag-reason-btn badge" data-dreason="<?= e($dk) ?>" style="padding:8px 14px;font-size:.8۵rem;cursor:pointer;font-weight:800;background:<?= $dReason===$dk?'var(--info)':'var(--surface-1)' ?>;color:<?= $dReason===$dk?'#fff':'var(--text-2)' ?>;border:1px solid var(--border-soft);border-radius:8px;transition:all 0.15s">
                <?= $dlbl ?>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="flex wrap gap-2 mt-2 pt-2 between" style="border-top:1px solid rgba(255,255,255,0.05);align-items:center">
            <span class="muted" style="font-size:.85rem;font-weight:700">💡 نکته‌ی طلایی:</span>
            <input class="input diag-takeaway-input" value="<?= $dTakeaway ?>" placeholder="قاعده کنکوری یا نکته‌ای که از این سوال یاد گرفتی را بنویس..." style="flex:1;min-width:240px;height:40px;background:var(--surface-1);border-radius:8px;margin:0;font-size:.9rem;font-weight:bold">
            <button type="button" class="btn text-c save-diagnostic-btn" style="background:var(--info);color:#fff;height:40px;font-weight:900;padding:0 20px;font-size:.9rem">
              ✓ ثبت تحلیل
            </button>
          </div>
        </div>
      <?php endif; ?>

      <?php if(!empty($q['explanation'])): ?>
        <details class="ans-exp-details mt-4 panel" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:12px 18px;border-radius:12px">
          <summary style="font-weight:900;cursor:pointer;color:var(--sage-light);font-size:.9rem;display:flex;align-items:center;gap:8px">
            <?= icon('sparkles',18) ?> پاسخ تشریحی و نکات آموزشی طراح
          </summary>
          <div class="mt-3 pt-3" style="border-top:1px solid var(--border-soft);font-size:.9rem;color:var(--text-1);line-height:1.8">
            <?= nl2br(e($q['explanation'])) ?>
          </div>
        </details>
      <?php endif; ?>

    </article>
  <?php endforeach; ?>
</div>

<script>
  const TA_API = '<?= url('api/exam_take.php') ?>';
  const attId  = <?= (int)$att['id'] ?>;

  document.querySelectorAll('.ans-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.ans-filter-btn').forEach(b => {
        b.style.background = 'transparent';
        b.style.color      = 'var(--text-2)';
      });
      btn.style.background = 'var(--surface-3)';
      btn.style.color      = '#fff';
      
      const filter = btn.dataset.filter;
      document.querySelectorAll('#answerSheetContainer .ans-q-item').forEach(item => {
        if (filter === 'all' || item.dataset.state === filter) {
          item.style.display = 'flex';
        } else {
          item.style.display = 'none';
        }
      });
    });
  });

  document.querySelectorAll('#answerSheetContainer .ans-q-item').forEach(item => {
    const qid = item.dataset.qid;
    
    item.querySelectorAll('.diag-reason-btn').forEach(rBtn => {
      rBtn.addEventListener('click', async () => {
        item.querySelectorAll('.diag-reason-btn').forEach(b => {
          b.style.background = 'var(--surface-1)';
          b.style.color      = 'var(--text-2)';
        });
        rBtn.style.background = 'var(--info)';
        rBtn.style.color      = '#fff';

        const dReason   = rBtn.dataset.dreason;
        const dTakeaway = item.querySelector('.diag-takeaway-input')?.value.trim() || '';
        const statusBadge = item.querySelector('.save-diag-status');
        if (statusBadge) { statusBadge.textContent = 'در حال ثبت...'; }

        try {
          await api(TA_API, { method: 'POST', body: { action: 'save_diagnostic', attempt_id: attId, question_id: qid, diagnostic_reason: dReason, diagnostic_takeaway: dTakeaway } });
          toast('علت اشتباه با موفقیت ثبت شد 🎯', 'success');
          if (statusBadge) { statusBadge.textContent = '✓ علت ثبت شد'; statusBadge.classList.add('badge-sage'); }
        } catch(err) {
          toast(err.error || 'خطا در ثبت علت اشتباه', 'error');
          if (statusBadge) { statusBadge.textContent = 'خطا در ثبت'; }
        }
      });
    });

    item.querySelector('.save-diagnostic-btn')?.addEventListener('click', async b => {
      const btn = b.currentTarget;
      btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:14px;height:14px"></span>';

      const activeR = item.querySelector('.diag-reason-btn[style*="var(--info)"]')?.dataset.dreason || '';
      const dTakeaway = item.querySelector('.diag-takeaway-input')?.value.trim() || '';

      try {
        await api(TA_API, { method: 'POST', body: { action: 'save_diagnostic', attempt_id: attId, question_id: qid, diagnostic_reason: activeR, diagnostic_takeaway: dTakeaway } });
        toast('نکته آموزشی با موفقیت ثبت شد 🌟', 'success');
        btn.disabled = false; btn.innerHTML = '✓ ثبت تحلیل';
      } catch(err) {
        toast(err.error || 'خطا در ثبت', 'error');
        btn.disabled = false; btn.innerHTML = '✓ ثبت تحلیل';
      }
    });
  });
</script>
<?php
}
