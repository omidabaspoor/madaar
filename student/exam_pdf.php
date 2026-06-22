<?php
/**
 * مَدار · Madar Study OS — Professional Exam Booklet PDF Export
 * ------------------------------------------------------------------
 * خروجی PDF سوالات آزمون (دفترچه چاپی و برداری جهت پرینت و حل با مداد)
 * با سیستم صفحه‌بندی قدرتمند (پشتیبانی از ۲۰ صفحه و بیشتر) و قالب لوکس اختصاصی
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/helpers.php';
boot_session();
require_role('student','advisor','admin');
$u = current_user();

$examId = (int)($_GET['id'] ?? 0);
$exam = get_exam($examId);
if (!$exam || $exam['status'] !== 'published') { flash('error','آزمون یافت نشد یا منتشر نشده است'); redirect('student/exams.php'); }
if ($u['role'] === 'student' && !student_exam_is_visible($examId, (int)$u['id'])) { flash('error','آزمون در دسترس نیست'); redirect('student/exams.php'); }
if (in_array($u['role'], ['advisor','admin'], true) && $u['role'] !== 'admin' && (int)$exam['advisor_id'] !== (int)$u['id']) { flash('error','دسترسی غیرمجاز است'); redirect('admin/exams.php'); }

$sections  = exam_sections($examId);
$questions = exam_questions($examId);

// نگاشت بخش‌ها و شماره‌گذاری سراسری
$globalNum = 0;
$flatQuestions = [];
foreach ($sections as $sec) {
    foreach ($questions as $q) {
        if ((int)$q['section_id'] !== (int)$sec['id']) continue;
        $globalNum++;
        $q['gnum']     = $q['question_number'] !== null ? (int)$q['question_number'] : $globalNum;
        $q['sec_name'] = $sec['name'];
        $flatQuestions[] = $q;
    }
}
$totalQuestions = count($flatQuestions);

$mode = $exam['creation_mode'] ?? 'quick_sheet';

// استخراج صفحات دفترچه (برای آزمون‌های تصویرمحور یا آپلودی)
$sheetArr = $exam['sheet_paths_json'] ? (json_decode($exam['sheet_paths_json'], true) ?: []) : [];
if (!empty($exam['sheet_path']) && !in_array($exam['sheet_path'], $sheetArr, true)) {
    array_unshift($sheetArr, $exam['sheet_path']);
}

// 1. اگر آزمون سریع است و فایل اول یک PDF رسمی آپلودی است، مستقیماً همان PDF را استریم کن
if ($mode === 'quick_sheet' && !empty($sheetArr) && is_pdf_asset($sheetArr[0])) {
    $full = realpath(__DIR__ . '/../' . ltrim($sheetArr[0], '/'));
    if ($full && is_file($full)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="exam_' . $examId . '_booklet.pdf"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($full);
        exit;
    }
}

// 2. سیستم صفحه‌بندی هوشمند و قدرتمند برای چیدمان سوالات (پشتیبانی از ده‌ها صفحه آزمون)
$questionPages = [];
if (!empty($flatQuestions)) {
    $currentSec = $flatQuestions[0]['sec_name'];
    $currentPage = ['section_name' => $currentSec, 'questions' => [], 'weight' => 0];

    foreach ($flatQuestions as $q) {
        // محاسبه وزن عمودی سوال جهت چیدمان بسیار دقیق و شیک در A4
        $w = 1.0;
        if (mb_strlen($q['q_text'] ?: '') > 160) $w += 0.5;
        if (!empty($q['q_image']))               $w += 2.0;

        // اگر وزن صفحه تکمیل است یا تعداد سوال به 3 مورد رسیده (جهت ایجاد فضای کافی برای محاسبات چکنویس)، صفحه را ببند
        if ($currentPage['weight'] + $w > 3.6 || count($currentPage['questions']) >= 3) {
            $questionPages[] = $currentPage;
            $currentPage = ['section_name' => $q['sec_name'], 'questions' => [], 'weight' => 0];
        }

        // بروزرسانی نام بخش اگر در میانه مسیر تغییر کرده
        if ($q['sec_name'] !== $currentPage['section_name']) {
            $currentPage['section_name'] = $q['sec_name'];
        }

        $currentPage['questions'][] = $q;
        $currentPage['weight'] += $w;
    }
    if (!empty($currentPage['questions'])) {
        $questionPages[] = $currentPage;
    }
}

$template = local_image_data_uri('assets/img/plan-pdf-template.png');
$pdfLogo  = local_image_data_uri('assets/img/logo.png');
$durationMin = (int)($exam['duration_min'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دفترچه سوالات آزمون · <?= e($exam['title']) ?></title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
@font-face{font-family:Vazirmatn;src:local('Vazirmatn');font-display:swap}
@font-face{font-family:MadarPDF;src:url('../assets/fonts/DejaVuSans.ttf') format('truetype');font-weight:400}
@font-face{font-family:MadarPDF;src:url('../assets/fonts/DejaVuSans-Bold.ttf') format('truetype');font-weight:800}

:root {
  --ink: #14211b; --muted: #627169; --line: #dfe7df; --paper: #fcfdf9;
  --sage: #6b8872; --gold: #b2945f; --dark: #172a21; --info: #6f9bc0;
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
  box-shadow: 0 8px 24px rgba(203,172,128,0.25);
  transition: all .2s;
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

.top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12mm; border-bottom: 2px solid rgba(107,136,114,0.2); padding-bottom: 5mm; flex-shrink: 0; }
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

.cover-hero { display: grid; grid-template-columns: 1.3fr .7fr; gap: 14px; margin-bottom: 12mm; }
.card {
  background: rgba(255,255,255,0.95); border: 1px solid rgba(107,136,114,0.25);
  border-radius: 24px; padding: 22px 24px; box-shadow: 0 10px 30px rgba(20,33,27,0.06);
}
.hero h1 { font-size: 32px; line-height: 1.25; margin: 0 0 8px; font-weight: 900; color: var(--dark); }
.hero p { margin: 0; color: var(--muted); font-weight: 800; font-size: 14px; }
.info-card { background: linear-gradient(135deg, rgba(224,197,149,0.25), rgba(255,255,255,0.95)); border-color: rgba(178,148,95,0.4); }
.info-card .k { display: block; color: var(--muted); font-weight: 900; font-size: 12px; }
.info-card .v { display: block; font-weight: 1000; font-size: 20px; color: var(--dark); margin: 2px 0; }

.summary-matrix { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12mm; }
.sum-box { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 16px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.sum-box .k { display: block; color: var(--muted); font-size: 12px; font-weight: 900; }
.sum-box .v { display: block; font-size: 32px; font-weight: 1000; color: var(--sage); margin: 4px 0; font-family: monospace; }
.sum-box .v.gold { color: var(--gold); }

.section-head { margin-bottom: 6mm; border-bottom: 2px solid var(--line); padding-bottom: 4px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.section-head h2 { font-size: 20px; font-weight: 900; color: var(--dark); }
.sec-badge { background: var(--sage); color: #fff; font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 999px; }

/* سوالات در حالت صفحه‌بندی هوشمند */
.questions-page-content { flex: 1; display: flex; flex-direction: column; gap: 16px; justify-content: flex-start; }
.question-item {
  background: #fff; border: 1px solid var(--line); border-radius: 18px;
  padding: 18px 22px; box-shadow: 0 4px 14px rgba(20,33,27,0.03);
  width: 100%;
}
.q-header { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.q-no {
  width: 30px; height: 30px; border-radius: 8px; background: var(--dark);
  color: #fff; display: grid; place-items: center; font-weight: 900;
  font-size: 14px; flex-shrink: 0; font-family: monospace;
}
.q-text { font-size: 15px; font-weight: 800; color: var(--dark); line-height: 1.8; flex: 1; }
.q-img { max-width: 80%; max-height: 240px; border-radius: 14px; margin: 12px auto; border: 1px solid var(--line); display: block; }

.options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; direction: ltr; }
.opt-box {
  display: flex; align-items: center; gap: 10px; padding: 11px 16px;
  background: #f8faf8; border: 1px solid var(--line); border-radius: 14px;
  font-weight: 700; font-size: 14px; color: var(--ink); direction: rtl;
  text-align: right;
}
.opt-box .onum { font-family: monospace; font-weight: 900; color: var(--muted); background: rgba(0,0,0,0.06); padding: 2px 8px; border-radius: 6px; font-size: 12px; }

/* صفحات دفترچه در حالت Quick Sheet */
.sheet-page-container { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.sheet-img { max-width: 100%; max-height: 230mm; width: auto; height: auto; border-radius: 16px; border: 2px solid var(--line); box-shadow: 0 8px 28px rgba(0,0,0,0.1); margin: 0 auto; display: block; }
.sheet-caption { font-size: 14px; font-weight: bold; color: var(--muted); margin-top: 12px; display: block; }

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
  .tpl { display: none !important; } /* جهت جلوگیری از تداخل پس‌زمینه در پرینت واقعی A4 */
}
</style>
<script>
function printBooklet() {
    const imgs = [...document.images].map(i => i.complete ? Promise.resolve() : new Promise(r => { i.onload = i.onerror = r; }));
    Promise.all(imgs).then(() => setTimeout(() => window.print(), 200));
}
</script>
</head>
<body>

<div class="screen-actions">
  <button class="btn flex items-center gap-2" onclick="printBooklet()">
    <span>🖨️ چاپ / ذخیره PDF دفترچه سوالات</span>
  </button>
  <a class="btn ghost" href="<?= url('student/exams.php') ?>">بازگشت به لیست آزمون‌ها</a>
  <span class="hint">قالب اختصاصی و برداری مَدار · طراحی‌شده با سیستم صفحه‌بندی پیشرفته</span>
</div>

<!-- ================= COVER PAGE ================= -->
<section class="page cover">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top">
      <div class="brand">
        <div class="logo"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
        <div><b>مَدار</b><small>STUDY OS</small></div>
      </div>
      <div class="top-meta">
        نسخه چاپی دفترچه سوالات<br>
        <?= jalali_date('now', true) ?>
      </div>
    </header>

    <div class="cover-hero">
      <div class="card hero">
        <h1><?= e($exam['title']) ?></h1>
        <p><?= e($exam['subtitle'] ?: 'سنجش آمادگی و تسلط تحصیلی') ?></p>
        <div style="margin-top: 14px; display: flex; gap: 8px;">
          <span class="sec-badge" style="background: var(--gold);">آزمون استاندارد مَدار</span>
          <span class="sec-badge" style="background: var(--dark);">ویژه آمادگی کنکور</span>
        </div>
      </div>
      
      <div class="card info-card">
        <span class="k">مشاور طراح</span>
        <span class="v"><?= e(APP_OWNER) ?></span>
        <span class="k mt-2">داوطلب: <?= e($u['full_name']) ?></span>
      </div>
    </div>

    <div class="summary-matrix">
      <div class="sum-box">
        <span class="k">تعداد کل سوالات</span>
        <b class="v"><?= fa_num($totalQuestions) ?></b>
        <span class="k">سوال تستی</span>
      </div>
      <div class="sum-box">
        <span class="k">زمان پیشنهادی آزمون</span>
        <b class="v gold"><?= $durationMin > 0 ? fa_num($durationMin) . ' دقیقه' : 'بدون محدودیت' ?></b>
        <span class="k">وقت قانونی</span>
      </div>
      <div class="sum-box">
        <span class="k">تعداد سرفصل‌ها</span>
        <b class="v"><?= fa_num(count($sections)) ?></b>
        <span class="k">سرفصل مستقل</span>
      </div>
    </div>

    <div class="card" style="margin-top: 6mm; background: rgba(255,255,255,0.85);">
      <h3 style="font-size: 18px; color: var(--dark); margin-bottom: 10px;">راهنمای داوطلب در جلسه آزمون</h3>
      <ul style="list-style: disc; margin-right: 20px; font-size: 14px; color: var(--muted); line-height: 1.8;">
        <li style="margin-bottom: 6px;">توصیه می‌شود قبل از شروع آزمون، دفترچه را به‌طور کامل پرینت گرفته و در شرایط شبیه‌سازی‌شده (پشت میز و با لباس رسمی) پاسخ دهید.</li>
        <li style="margin-bottom: 6px;">پس از اتمام مهلت، پاسخ‌های خود را در وب‌اپلیکیشن مَدار وارد کنید تا کارنامه پیشرفته و پاسخنامه تشریحی شما فعال شود.</li>
      </ul>
    </div>
  </div>
</section>

<!-- ================= MODE 1: QUICK SHEET (BOOKLET IMAGE PAGES) ================= -->
<?php if ($mode === 'quick_sheet' && !empty($sheetArr)): ?>
  <?php foreach ($sheetArr as $index => $sheetImg): ?>
  <section class="page sheet-page">
    <img class="tpl" src="<?= $template ?>" alt="">
    <div class="inner">
      <header class="top">
        <div class="brand">
          <div class="logo" style="width: 44px; height: 44px;"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
          <div><b style="font-size: 18px;">مَدار</b><small style="font-size: 9px;"><?= e(APP_OWNER) ?></small></div>
        </div>
        <div class="top-meta" style="font-size: 12px;">
          <?= e($exam['title']) ?> · صفحه <?= fa_num($index + 1) ?> از <?= fa_num(count($sheetArr)) ?>
        </div>
      </header>

      <div class="sheet-page-container">
        <img src="<?= local_image_data_uri($sheetImg) ?>" alt="صفحه دفترچه آزمون" class="sheet-img">
        <span class="sheet-caption">برگه ضمیمه سوالات آزمون · چیدمان استاندارد چاپی</span>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

<!-- ================= MODE 2: BUILDER (SMART CHUNK PAGES - SUPPORTS 20+ PAGES) ================= -->
<?php else: ?>
  
  <?php if (empty($questionPages)): ?>
    <section class="page">
      <img class="tpl" src="<?= $template ?>" alt="">
      <div class="inner text-center py-20">
        <h3 style="font-size: 20px; color: var(--muted);">سوالات این آزمون هنوز در سیستم درج نشده است.</h3>
      </div>
    </section>
  <?php else: ?>

    <?php foreach ($questionPages as $pgIndex => $pg): ?>
    <section class="page question-page">
      <img class="tpl" src="<?= $template ?>" alt="">
      <div class="inner">
        <header class="top">
          <div class="brand">
            <div class="logo" style="width: 44px; height: 44px;"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
            <div><b style="font-size: 18px;">مَدار</b><small style="font-size: 9px;"><?= e(APP_OWNER) ?></small></div>
          </div>
          <div class="top-meta" style="font-size: 12px;">
            <?= e($exam['title']) ?> · ص <?= fa_num($pgIndex + 1) ?> از <?= fa_num(count($questionPages)) ?>
          </div>
        </header>

        <div class="section-head">
          <h2>سرفصل: <?= e($pg['section_name']) ?></h2>
          <span class="sec-badge">سوالات صفحه <?= fa_num($pgIndex + 1) ?></span>
        </div>

        <!-- سوالات چیده شده در این صفحه (حداکثر 3 سوال جهت زیبایی و فضا) -->
        <div class="questions-page-content">
          <?php foreach ($pg['questions'] as $q): ?>
          <article class="question-item">
            <div class="q-header">
              <span class="q-no"><?= fa_num($q['gnum']) ?></span>
              <div class="q-text"><?= nl2br(e($q['q_text'] ?: 'سوال شماره ' . $q['gnum'])) ?></div>
            </div>

            <?php if (!empty($q['q_image'])): ?>
              <img src="<?= local_image_data_uri($q['q_image']) ?>" alt="تصویر سوال" class="q-img">
            <?php endif; ?>

            <!-- گزینه‌های 4 گانه -->
            <div class="options-grid">
              <?php for ($o = 1; $o <= 4; $o++): ?>
                <label class="opt-box">
                  <span class="onum"><?= $o ?></span>
                  <span style="flex: 1;"><?= e($q['opt'.$o] ?: 'گزینه ' . fa_num($o)) ?></span>
                </label>
              <?php endfor; ?>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <?php endforeach; ?>

  <?php endif; ?>

<?php endif; ?>

</body>
</html>