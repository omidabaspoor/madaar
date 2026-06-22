<?php
/**
 * مَدار · Madar Study OS — Professional Genuine Exam Solution & Report Card PDF Export
 * -------------------------------------------------------------------------------------
 * خروجی PDF کارنامه واقعی و پاسخنامه کلیدی آزمون (بدون نمایش تصاویر و متن‌های اضافی)
 * تمامی آمارها از جمله رتبه در آزمون، میانگین کل و درصدها ۱۰۰٪ واقعی و محاسبه‌شده از دیتابیس هستند.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/helpers.php';
boot_session();
require_role('student','advisor','admin');
$u = current_user();

$attemptId = (int)($_GET['attempt'] ?? 0);
$rep = attempt_report($attemptId);
if (!$rep) { flash('error','کارنامه یا پاسخنامه یافت نشد'); redirect('student/exams.php'); }

if ($u['role'] === 'student' && (int)$rep['attempt']['student_id'] !== (int)$u['id']) {
    flash('error','دسترسی به کارنامه غیرمجاز است'); redirect('student/exams.php');
}

$showAnswers = (int)$rep['exam']['show_review'] === 1 || $u['role'] !== 'student';

$att       = $rep['attempt']; 
$exam      = $rep['exam']; 
$secStats  = $rep['sections']; 
$questions = $rep['questions'];

$correct = (int)$att['correct_count'];
$wrong   = (int)$att['wrong_count'];
$blank   = (int)$att['blank_count'];
$total   = $correct + $wrong + $blank;

$konkurPct = round((float)$att['total_score'], 1);
$answered  = $correct + $wrong;
$precision = $answered > 0 ? round(($correct / $answered) * 100) : 0;

// محاسبه آمار کاملاً واقعی از پایگاه داده (رتبه واقعی داوطلب در این آزمون و میانگین کل شرکت‌کنندگان)
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

// استخراج آسیب‌شناسی‌های ثبت‌شده از دیتابیس
$ansMap = [];
$stmt = db()->prepare('SELECT question_id, diagnostic_reason, diagnostic_takeaway FROM exam_answers WHERE attempt_id=?');
$stmt->execute([(int)$att['id']]);
foreach ($stmt->fetchAll() as $rr) {
    $ansMap[(int)$rr['question_id']] = $rr;
}

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

$template = local_image_data_uri('assets/img/plan-pdf-template.png');
$pdfLogo  = local_image_data_uri('assets/img/logo.png');

// تفکیک سوالات بر اساس بخش
$questionsBySec = [];
foreach ($questions as $qItem) {
    $questionsBySec[$qItem['sec']][] = $qItem;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>کارنامه واقعی و پاسخنامه کلیدی · <?= e($exam['title']) ?></title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
@font-face{font-family:Vazirmatn;src:local('Vazirmatn');font-display:swap}
@font-face{font-family:MadarPDF;src:url('../assets/fonts/DejaVuSans.ttf') format('truetype');font-weight:400}
@font-face{font-family:MadarPDF;src:url('../assets/fonts/DejaVuSans-Bold.ttf') format('truetype');font-weight:800}

:root {
  --ink: #14211b; --muted: #627169; --line: #dfe7df; --paper: #fcfdf9;
  --sage: #6b8872; --gold: #b2945f; --dark: #172a21; --info: #6f9bc0;
  --danger: #d97474; --success: #5fae7b; --warn: #d9b25f;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: #101c17; color: var(--ink);
  font-family: Vazirmatn, MadarPDF, Tahoma, sans-serif;
  line-height: 1.6; -webkit-font-smoothing: antialiased;
}

.screen-actions {
  position: sticky; top: 0; z-index: 50; display: flex; gap: 12px;
  align-items: center; justify-content: center; padding: 14px;
  background: rgba(12,21,18,0.94); backdrop-filter: blur(14px);
  border-bottom: 1px solid #283530;
}
.btn {
  border: none; border-radius: 999px; padding: 11px 22px;
  font: 900 14px Vazirmatn, Tahoma;
  background: linear-gradient(135deg, #e0c595, #b2945f);
  color: #142018; text-decoration: none; cursor: pointer;
  box-shadow: 0 8px 24px rgba(203,172,128,0.25); transition: all .2s;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(203,172,128,0.4); }
.btn.ghost { background: #25352e; color: #eef4ef; border: 1px solid #41554a; box-shadow: none; }
.btn.ghost:hover { background: #32463e; color: #fff; }
.hint { color: #cbd8ce; font-size: 12px; font-weight: bold; }

.page {
  width: 210mm; min-height: 297mm; margin: 16px auto;
  background: var(--paper); position: relative; overflow: hidden;
  page-break-after: always; break-after: page; isolation: isolate;
  box-shadow: 0 24px 70px rgba(0,0,0,0.4); border-radius: 20px;
}
.page:last-of-type { page-break-after: auto; break-after: auto; }
.tpl { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: -5; }
.page::after {
  content: 'مَدار'; position: absolute; left: 14mm; bottom: 21mm; z-index: -2;
  color: rgba(32,48,40,0.04); font-size: 34mm; font-weight: 1000;
  transform: rotate(-18deg); letter-spacing: -.08em; pointer-events: none;
}
.inner { position: relative; z-index: 2; padding: 18mm 15mm 14mm; min-height: 100%; display: flex; flex-direction: column; }

.top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 14mm; border-bottom: 2px solid rgba(107,136,114,0.2); padding-bottom: 5mm; flex-shrink: 0; }
.brand { display: flex; gap: 12px; align-items: center; color: #172a21; }
.logo {
  width: 56px; height: 56px; border-radius: 18px; background: #fff;
  display: grid; place-items: center; overflow: hidden;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12); border: 2px solid var(--gold);
}
.logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.brand b { font-size: 24px; font-weight: 900; line-height: 1; }
.brand small { display: block; color: var(--gold); font-weight: 900; font-size: 11px; letter-spacing: .12em; margin-top: 2px; }
.top-meta { text-align: left; color: var(--muted); font-weight: 900; font-size: 13px; line-height: 1.6; }

/* کارنامه در حالت Cover */
.cover-hero { display: grid; grid-template-columns: 1fr 190px 190px; gap: 12px; margin-bottom: 10mm; }
.card {
  background: rgba(255,255,255,0.95); border: 1px solid rgba(107,136,114,0.25);
  border-radius: 22px; padding: 20px 22px; box-shadow: 0 10px 30px rgba(20,33,27,0.06);
}
.hero h1 { font-size: 28px; line-height: 1.25; margin: 0 0 6px; font-weight: 900; color: var(--dark); }
.hero p { margin: 0; color: var(--muted); font-weight: 800; font-size: 13.5px; }
.score-card { background: linear-gradient(135deg, rgba(111,155,192,0.15), rgba(255,255,255,0.95)); border-color: rgba(111,155,192,0.4); text-align: center; display: flex; flex-direction: column; justify-content: center; }
.score-card .k { display: block; color: var(--muted); font-weight: 900; font-size: 11px; text-transform: uppercase; }
.score-card .v { display: block; font-weight: 1000; font-size: 34px; color: var(--info); margin: 4px 0; font-family: monospace; line-height: 1; }

.stats-matrix { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10mm; }
.stat-box { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.stat-box .k { display: block; color: var(--muted); font-size: 11px; font-weight: 900; }
.stat-box .v { display: block; font-size: 26px; font-weight: 1000; font-family: monospace; margin: 2px 0; }
.stat-box .v.green { color: var(--success); }
.stat-box .v.red { color: var(--danger); }
.stat-box .v.slate { color: var(--dark); }
.stat-box .v.gold { color: var(--gold); }

.coaching-box {
  background: #fff; border: 1px solid var(--line); border-radius: 20px;
  padding: 18px 22px; margin-bottom: 10mm; border-right: 6px solid var(--info);
  box-shadow: 0 6px 20px rgba(0,0,0,0.03);
}
.coaching-box.warn { border-right-color: var(--warn); }
.coaching-box.success { border-right-color: var(--success); }
.coaching-box h3 { font-size: 18px; font-weight: 900; color: var(--dark); margin-bottom: 6px; }
.coaching-box p { font-size: 14px; color: var(--muted); line-height: 1.7; font-weight: 600; margin: 0; }

/* ساختار پاسخنامه کلیدی فشرده و زیبا */
.lesson-section-block { margin-bottom: 12mm; page-break-inside: avoid; break-inside: avoid; }
.lesson-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6mm; border-bottom: 2px solid var(--dark); padding-bottom: 6px; }
.lesson-head h2 { font-size: 20px; font-weight: 900; color: var(--dark); margin: 0; }
.lesson-stats { display: flex; gap: 8px; font-size: 12px; font-weight: bold; }

.lesson-stats span { padding: 3px 11px; border-radius: 999px; }
.lesson-stats span.green { background: rgba(95,174,123,0.18); color: var(--success); border: 1px solid rgba(95,174,123,0.4); }
.lesson-stats span.red   { background: rgba(217,116,116,0.18); color: var(--danger); border: 1px solid rgba(217,116,116,0.4); }
.lesson-stats span.gold  { background: var(--dark); color: #fff; }

.key-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  direction: rtl;
}

.key-cell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1px solid var(--line);
  background: #fff;
  box-shadow: 0 3px 10px rgba(0,0,0,0.015);
  transition: all .2s;
}
.key-cell.st-correct { background: #f0f8f2; border-color: var(--success); }
.key-cell.st-wrong   { background: #fdf2f2; border-color: var(--danger); }
.key-cell.st-blank   { background: #f8faf8; border-color: var(--line); opacity: 0.9; }

.q-number {
  width: 28px; height: 28px; border-radius: 8px; background: var(--dark);
  color: #fff; display: grid; place-items: center; font-weight: 900;
  font-size: 13px; font-family: monospace; flex-shrink: 0;
}

.q-answers {
  flex: 1; margin: 0 12px; font-size: 12.5px; font-weight: 800; color: var(--ink); line-height: 1.6;
}
.q-answers b { font-family: monospace; font-size: 14px; }
.q-answers .user-ans { color: var(--muted); }
.q-answers .correct-key { color: var(--dark); }

.st-icon {
  font-size: 11px; font-weight: 900; padding: 3px 9px; border-radius: 8px; flex-shrink: 0;
}
.key-cell.st-correct .st-icon { background: var(--success); color: #fff; }
.key-cell.st-wrong   .st-icon { background: var(--danger); color: #fff; }
.key-cell.st-blank   .st-icon { background: #e0e6e2; color: var(--muted); }

@media print {
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
  html, body { background: #fff !important; color: #000 !important; width: 100%; }
  .screen-actions { display: none !important; }
  @page { size: A4 portrait; margin: 10mm; }
  .page {
    margin: 0 !important; width: 100% !important; min-height: 0 !important; height: auto !important;
    box-shadow: none !important; border-radius: 0 !important;
    border: none !important; page-break-after: always !important; break-after: page !important;
    overflow: visible !important;
  }
  .inner { padding: 5mm 0 !important; }
  .tpl { display: none !important; }
}
</style>
<script>
function printSolution() {
    const imgs = [...document.images].map(i => i.complete ? Promise.resolve() : new Promise(r => { i.onload = i.onerror = r; }));
    Promise.all(imgs).then(() => setTimeout(() => window.print(), 200));
}
</script>
</head>
<body>

<div class="screen-actions">
  <button class="btn flex items-center gap-2" onclick="printSolution()">
    <span>🖨️ چاپ / ذخیره PDF کارنامه واقعی و پاسخنامه کلیدی</span>
  </button>
  <a class="btn ghost" href="<?= url('student/exam_result.php?attempt=' . $att['id']) ?>">بازگشت به کارنامه آنلاین</a>
  <span class="hint">قالب آماری واقعی مَدار · طراحی‌شده جهت پرینت سریع، تمیز و اقتصادی</span>
</div>

<!-- ================= COVER PAGE / REPORT CARD SUMMARY ================= -->
<section class="page cover">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top">
      <div class="brand">
        <div class="logo"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
        <div><b>مَدار</b><small>STUDY OS</small></div>
      </div>
      <div class="top-meta">
        کارنامه واقعی و پاسخنامه کلیدی<br>
        <?= jalali_date('now', true) ?>
      </div>
    </header>

    <div class="cover-hero">
      <div class="card hero">
        <div style="font-size: 11px; font-weight: bold; color: var(--info); margin-bottom: 4px;">گزارش آماری ۱۰۰٪ واقعی دیتابیس</div>
        <h1><?= e($exam['title']) ?></h1>
        <p class="mt-1">داوطلب: <?= e($att['full_name'] ?? $u['full_name']) ?> · ثبت: <?= jalali_date($att['submitted_at'] ?? '', true) ?></p>
        <div style="margin-top: 14px; display: flex; gap: 8px;">
          <span class="sec-badge" style="background: var(--info);">میانگین کل داوطلبان: <?= fa_num($actualAvgPct) ?>٪</span>
        </div>
      </div>
      
      <!-- Box نمره واقعی کسب‌شده -->
      <div class="card score-card" style="background: linear-gradient(135deg, #112a1e 0%, #1f4230 100%); border: 2px solid #5fae7b; color: #fff;">
        <span class="k" style="color: #a3c9b1; font-size: 11px;">نمره واقعی کسب‌شده</span>
        <b class="v" style="color: #8ae6ab; font-size: 36px; margin: 4px 0;"><?= fa_num($konkurPct) ?>٪</b>
        <span style="font-size: 10px; color: #dfe7df; font-weight: bold;">با نمره منفی</span>
      </div>

      <!-- Box رتبه -->
      <div class="card score-card">
        <span class="k">رتبه واقعی در آزمون</span>
        <b class="v"><?= fa_num($actualRank) ?></b>
        <span class="badge flex items-center justify-center gap-1 mt-1" style="background:rgba(111,155,192,0.15); color:var(--info); font-size:10px;">از <?= fa_num($totalSubmissionsCount) ?> نفر</span>
      </div>
    </div>

    <!-- ماتریس عملکرد خام -->
    <div class="stats-matrix">
      <div class="stat-box">
        <span class="k">پاسخ صحیح ✓</span>
        <b class="v green"><?= fa_num($correct) ?></b>
      </div>
      <div class="stat-box">
        <span class="k">پاسخ غلط ✗ (منفی)</span>
        <b class="v red"><?= fa_num($wrong) ?></b>
      </div>
      <div class="stat-box">
        <span class="k">بی‌پاسخ ⚪ (نزده)</span>
        <b class="v slate"><?= fa_num($blank) ?></b>
      </div>
      <div class="stat-box">
        <span class="k">ضریب دقت 🎯</span>
        <b class="v gold"><?= fa_num($precision) ?>٪</b>
      </div>
    </div>

    <!-- توصیه تحلیلی واقعی -->
    <?php if (!empty($smartAdvice['title'])): ?>
      <div class="coaching-box <?= $smartAdvice['class'] ?>">
        <h3><?= $smartAdvice['title'] ?></h3>
        <p><?= e($smartAdvice['text']) ?></p>
      </div>
    <?php endif; ?>

    <!-- نمودار کلان آسیب‌شناسی -->
    <?php if (false && array_sum($diagCounts) > 0): ?>
    <div class="card" style="margin-top: 4mm; background: rgba(255,255,255,0.9);">
      <h3 style="font-size: 16px; color: var(--dark); margin-bottom: 10px;">خلاصه ریشه‌یابی و آسیب‌شناسی اشتباهات آزمون</h3>
      <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
        <?php foreach ($diagCounts as $dlbl => $dcnt): ?>
          <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #fff; border: 1px solid var(--line); border-radius: 12px; font-size: 13px; font-weight: bold;">
            <span style="color: var(--muted);"><?= e($dlbl) ?></span>
            <span style="background: <?= $dcnt ? 'var(--info)' : '#f0f4f1' ?>; color: <?= $dcnt ? '#fff' : 'var(--muted)' ?>; padding: 2px 10px; border-radius: 8px; font-family: monospace; font-size: 14px;"><?= fa_num($dcnt) ?> مورد</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ================= ANSWER KEY COMPARATIVE TABLES (NO IMAGES) ================= -->
    <div class="answer-key-tables-container" style="margin-top: 14mm;">
      <?php if (!$showAnswers): ?>
        <div class="card text-center py-12">
          <div style="font-size: 36px; color: var(--danger); margin-bottom: 12px;">🔒</div>
          <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 6px;">پاسخنامه کلیدی قفل است</h3>
          <p style="font-size: 14px; color: var(--muted); margin: 0;">دسترسی به پاسخنامه‌ی این آزمون توسط مشاور (دکتر سجاد صیادی) غیرفعال شده است.</p>
        </div>
      <?php else: ?>

        <?php foreach ($secStats as $sid => $secInfo): 
            $secQuestions = $questionsBySec[$secInfo['name']] ?? [];
            if (empty($secQuestions)) continue;
        ?>
          <div class="lesson-section-block">
            <div class="lesson-head">
              <h2><?= e($secInfo['name']) ?></h2>
              <div class="lesson-stats">
                <span class="green">✓ صحیح: <?= fa_num($secInfo['correct']) ?></span>
                <span class="red">✗ غلط: <?= fa_num($secInfo['wrong']) ?></span>
                <span style="background:#e8ece9; color:var(--dark)">⚪ نزده: <?= fa_num($secInfo['blank']) ?></span>
                <span class="gold font-mono">درصد: <?= fa_num($secInfo['percent']) ?>٪</span>
              </div>
            </div>

            <!-- جدول ۳ ستونه پاسخ‌های کلیدی -->
            <div class="key-grid">
              <?php foreach ($secQuestions as $qObj): 
                  $q     = $qObj['q'];
                  $gnum  = $qObj['gnum'];
                  $st    = $qObj['state'];
                  $sel   = $qObj['selected'];
                  $cOpt  = (int)($q['correct_opt'] ?? 0);

                  $stClass = match($st) {
                      'correct' => 'st-correct',
                      'wrong'   => 'st-wrong',
                      default   => 'st-blank'
                  };
                  $stLabel = match($st) {
                      'correct' => '✓ صحیح',
                      'wrong'   => '✗ غلط',
                      default   => '⚪ نزده'
                  };
              ?>
                <div class="key-cell <?= $stClass ?>">
                  <span class="q-number"><?= fa_num($gnum) ?></span>
                  <div class="q-answers">
                    <div class="user-ans">پاسخ شما: <b><?= $sel ? fa_num($sel) : '—' ?></b></div>
                    <div class="correct-key">کلید درست: <b style="color:var(--success)"><?= $cOpt ? fa_num($cOpt) : '؟' ?></b></div>
                  </div>
                  <span class="st-icon"><?= $stLabel ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </div>

  </div>
</section>

</body>
</html>