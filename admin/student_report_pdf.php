<?php
/**
 * مَدار · Madar Study OS — Professional Guaranteed Advanced Progress Report PDF Export
 * -------------------------------------------------------------------------------------
 * خروجی PDF گزارش پیشرفته تحصیلی و تحلیل هوشمند مَدار (Advanced Report & AI Insight Suite)
 * ارائه حجم عظیمی از اطلاعات مستند (روندها، آزمون‌ها، رخدادها و درس‌ها) با چیدمان چندصفحه‌ای بی‌نقص
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/reporting.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_role('student','advisor','admin');
$u = current_user();

$reportId = (int)($_GET['report_id'] ?? 0);
$studentId = (int)($_GET['student'] ?? ($u['role'] === 'student' ? $u['id'] : 0));
$type = in_array($_GET['type'] ?? 'weekly', ['daily','weekly','monthly'], true) ? $_GET['type'] : 'weekly';

// 1. دریافت داده‌ی گزارش
if ($reportId) {
    $stmt = db()->prepare("SELECT * FROM student_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $r = $stmt->fetch() ?: null;
    if (!$r) { flash('error','گزارش یافت نشد'); redirect('index.php'); }
    $studentId = (int)$r['student_id'];
    $type = (string)$r['report_type'];
} elseif ($studentId) {
    $stmt = db()->prepare("SELECT * FROM student_reports WHERE student_id = ? AND report_type = ? ORDER BY period_start DESC LIMIT 1");
    $stmt->execute([$studentId, $type]);
    $r = $stmt->fetch() ?: null;
    if (!$r) { flash('error','گزارشی برای این دانش‌آموز ثبت نشده است'); redirect('index.php'); }
} else {
    flash('error','پارامترهای گزارش نامعتبر است'); redirect('index.php');
}

// بررسی دسترسی
if ($u['role'] === 'student' && $studentId !== (int)$u['id']) {
    flash('error','دسترسی غیرمجاز است'); redirect('index.php');
}

$student = get_user($studentId);
if (!$student || ($student['role'] ?? '') !== 'student') {
    flash('error','دانش‌آموز یافت نشد');
    redirect('index.php');
}

// مشاور فقط به دانش‌آموزان خودش دسترسی داشته باشد؛ ادمین ارشد به همه دسترسی دارد.
if ($u['role'] === 'advisor' && (int)($student['advisor_id'] ?? 0) !== (int)$u['id']) {
    http_response_code(403);
    require __DIR__ . '/../403.php';
    exit;
}

$advisor = get_user((int)($student['advisor_id'] ?? 0));

$s = $r['auto_snapshot_json'] ? (json_decode($r['auto_snapshot_json'], true) ?: []) : [];
$a = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
if (!is_array($s)) $s = [];
if (!is_array($a)) $a = [];

$s += ['progress_percent'=>0,'full'=>0,'partial'=>0,'missed'=>0,'study_hours'=>0,'tests_done'=>0,'target_tests'=>0,'extra_tests'=>0,'by_subject'=>[]];

// 2. ساخت تحلیل هوشمند مَدار
$showInsight = advisor_feature_enabled((int)($student['advisor_id'] ?? 0), 'insight_enabled');
$analysis = $showInsight ? report_build_analysis($studentId, $type, (string)$r['period_start'], (string)$r['period_end'], $s, $a) : null;
if ($analysis) {
    // نسخه چاپی باید خوانا و بدون سرریز باشد؛ مهم‌ترین موارد را نگه می‌داریم.
    $analysis['alerts'] = array_slice($analysis['alerts'] ?? [], 0, 4);
    $analysis['recommendations'] = array_slice($analysis['recommendations'] ?? [], 0, 5);
    $analysis['action_plan'] = array_slice($analysis['action_plan'] ?? [], 0, 3);
}

// 3. دریافت گزارش‌های دوره‌های گذشته جهت مقایسه و تحلیل روند رشد
$stmtLt = db()->prepare("SELECT * FROM student_reports WHERE student_id = ? AND report_type = ? AND period_start <= ? ORDER BY period_start DESC LIMIT 4");
$stmtLt->execute([$studentId, $type, $r['period_start']]);
$historyReports = $stmtLt->fetchAll();

// 4. دریافت کارنامه‌های آزمون کسب‌شده در این بازه
// این بخش‌ها باید اختیاری و مقاوم باشند؛ در بعضی نصب‌ها upgrade مربوط به آزمون/لاگ/دستاورد هنوز اجرا نشده است.
$periodExams = [];
try {
    $stmtExams = db()->prepare("SELECT a.*, e.title, e.exam_type, e.duration_min FROM exam_attempts a JOIN exams e ON e.id = a.exam_id WHERE a.student_id = ? AND a.status = 'submitted' AND a.submitted_at BETWEEN ? AND ? ORDER BY a.total_score DESC LIMIT 10");
    $stmtExams->execute([$studentId, $r['period_start'] . ' 00:00:00', $r['period_end'] . ' 23:59:59']);
    $periodExams = $stmtExams->fetchAll();
} catch (Throwable $e) {
    $periodExams = [];
}

// 5. دریافت لاگ رخدادها و ریزفعالیت‌های ثبت‌شده در این بازه
$periodLogs = [];
try {
    $stmtLogs = db()->prepare("SELECT l.*, u.full_name FROM activity_logs l JOIN users u ON u.id = l.user_id WHERE l.user_id = ? AND l.created_at BETWEEN ? AND ? ORDER BY l.created_at DESC LIMIT 30");
    $stmtLogs->execute([$studentId, $r['period_start'] . ' 00:00:00', $r['period_end'] . ' 23:59:59']);
    $periodLogs = $stmtLogs->fetchAll();
} catch (Throwable $e) {
    $periodLogs = [];
}

// 6. دریافت دستاوردها و نشان‌های کسب‌شده
$periodAchs = [];
try {
    // نام ستون درست در schema اصلی و upgrade_achievements برابر earned_at است؛ برای سازگاری با قالب، آن را awarded_at هم alias می‌کنیم.
    $stmtAchs = db()->prepare("SELECT sa.*, sa.earned_at AS awarded_at, a.title, a.icon, a.condition_type FROM student_achievements sa JOIN achievements a ON a.id = sa.achievement_id WHERE sa.student_id = ? ORDER BY sa.earned_at DESC LIMIT 8");
    $stmtAchs->execute([$studentId]);
    $periodAchs = $stmtAchs->fetchAll();
} catch (Throwable $e) {
    $periodAchs = [];
}

$template = local_image_data_uri('assets/img/plan-pdf-template.png');
$pdfLogo  = local_image_data_uri('assets/img/logo.png');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>گزارش پیشرفته تحصیلی · <?= e($student['full_name']) ?></title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
@font-face{font-family:Vazirmatn;src:local('Vazirmatn'),url('../assets/fonts/Vazirmatn.woff2') format('woff2');font-weight:100 900;font-style:normal;font-display:swap}
@font-face{font-family:MadarPDF;src:url('../assets/fonts/Vazirmatn.woff2') format('woff2'),url('../assets/fonts/DejaVuSans.ttf') format('truetype');font-weight:100 900;font-style:normal;font-display:swap}
@font-face{font-family:MadarFallback;src:url('../assets/fonts/DejaVuSans.ttf') format('truetype');font-weight:400}
@font-face{font-family:MadarFallback;src:url('../assets/fonts/DejaVuSans-Bold.ttf') format('truetype');font-weight:800}

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

/* ساختار استاندارد چاپی A4 جهت جلوگیری از بریدگی و تداخل */
.page {
  width: 210mm; min-height: 297mm; margin: 16px auto;
  background: var(--paper); position: relative; overflow: hidden;
  page-break-after: always; break-after: page; isolation: isolate;
  box-shadow: 0 24px 70px rgba(0,0,0,0.4); border-radius: 20px;
}
.page:last-of-type { page-break-after: auto; break-after: auto; }
.tpl { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; opacity:.22; pointer-events:none; }
.page::after {
  content: 'مَدار'; position: absolute; left: 14mm; bottom: 21mm; z-index: 1;
  color: rgba(32,48,40,0.04); font-size: 34mm; font-weight: 1000;
  transform: rotate(-18deg); letter-spacing: -.08em; pointer-events: none;
}
.inner { position: relative; z-index: 2; padding: 16mm 14mm 12mm; min-height: 100%; display: flex; flex-direction: column; }

.top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8mm; border-bottom: 2px solid rgba(107,136,114,0.2); padding-bottom: 4mm; flex-shrink: 0; }
.brand { display: flex; gap: 12px; align-items: center; color: #172a21; }
.logo {
  width: 50px; height: 50px; border-radius: 16px; background: #fff;
  display: grid; place-items: center; overflow: hidden;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12); border: 2px solid var(--gold);
}
.logo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.brand b { font-size: 22px; font-weight: 900; line-height: 1; }
.brand small { display: block; color: var(--gold); font-weight: 900; font-size: 10px; letter-spacing: .12em; margin-top: 2px; }
.top-meta { text-align: left; color: var(--muted); font-weight: 900; font-size: 12px; line-height: 1.6; }

/* مشخصات پرونده */
.report-hero { display: grid; grid-template-columns: 1.35fr .65fr; gap: 12px; margin-bottom: 6mm; }
.card {
  background: rgba(255,255,255,0.95); border: 1px solid rgba(107,136,114,0.25);
  border-radius: 20px; padding: 18px 20px; box-shadow: 0 6px 20px rgba(20,33,27,0.04);
}
.hero h1 { font-size: 26px; line-height: 1.25; margin: 0 0 4px; font-weight: 900; color: var(--dark); }
.hero p { margin: 0; color: var(--muted); font-weight: 800; font-size: 13.5px; }

.score-card { background: linear-gradient(135deg, #112a1e 0%, #1f4230 100%); border: 2px solid #5fae7b; color: #fff; text-align: center; display: flex; flex-direction: column; justify-content: center; }
.score-card .k { display: block; color: #a3c9b1; font-weight: 900; font-size: 11px; text-transform: uppercase; }
.score-card .v { display: block; font-weight: 1000; font-size: 36px; color: #8ae6ab; margin: 2px 0; font-family: monospace; line-height: 1; }
.score-card .sub { display: block; font-size: 10px; color: #dfe7df; font-weight: bold; }

/* باک جدید و شیک جهت جایگزینی استایل بدِ قبلی */
.report-meta-box {
  display: flex; flex-wrap: wrap; gap: 16px; align-items: center; justify-content: space-between;
  background: linear-gradient(135deg, #1a241f 0%, #15201b 100%);
  border: 1px solid #cbac80; border-radius: 16px; padding: 12px 20px;
  color: #fff; font-size: 13.5px; font-weight: bold; margin-bottom: 8mm;
  box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
.report-meta-box .meta-item { display: flex; align-items: center; gap: 8px; }
.report-meta-box .status-badge {
  padding: 4px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 900; display: inline-block;
}
.report-meta-box .status-badge.submitted {
  background: rgba(95,174,123,0.25); border: 1px solid #5fae7b; color: #8ae6ab;
}
.report-meta-box .status-badge.draft {
  background: rgba(217,178,95,0.25); border: 1px solid #cbac80; color: #e0c595;
}

.stats-matrix { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 8mm; }
.stat-box { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.stat-box .k { display: block; color: var(--muted); font-size: 11px; font-weight: 900; }
.stat-box .v { display: block; font-size: 26px; font-weight: 1000; font-family: monospace; margin: 2px 0; }
.stat-box .v.green { color: var(--success); }
.stat-box .v.red { color: var(--danger); }
.stat-box .v.slate { color: var(--dark); }
.stat-box .v.gold { color: var(--gold); }

/* تحلیل هوشمند مَدار */
.insight-vip-suite {
  background: linear-gradient(140deg, #fffcf5 0%, #fffbc8 100%);
  border: 2px solid var(--gold); border-radius: 20px; padding: 22px 24px;
  margin-bottom: 6mm; box-shadow: 0 10px 30px rgba(203,172,128,0.18);
  position: relative; overflow: hidden;
}
.insight-vip-suite::before {
  content: ''; position: absolute; top: 0; right: 0; width: 8px; bottom: 0;
  background: var(--grad-gold);
}
.insight-header { display: flex; flex-wrap: wrap; gap: 16px; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(203,172,128,0.3); padding-bottom: 14px; margin-bottom: 14px; }
.insight-score-badge {
  width: 60px; height: 60px; border-radius: 50%; background: var(--dark);
  color: var(--gold-light); font-weight: 1000; font-size: 22px; font-family: monospace;
  display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}
.status-pill { background: var(--gold); color: #000; font-size: 13.5px; font-weight: 900; padding: 5px 16px; border-radius: 999px; }

.insight-summary { font-size: 14.5px; font-weight: 900; color: #302005; line-height: 1.8; margin-bottom: 16px; }
.sub-scores-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
.sub-score-cell { background: rgba(255,255,255,0.85); border: 1px solid rgba(203,172,128,0.4); padding: 8px 12px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; font-weight: bold; }
.sub-score-cell b { font-family: monospace; font-size: 13.5px; color: var(--dark); }

.alerts-box { background: #fff; border: 1px solid rgba(217,116,116,0.4); border-radius: 14px; padding: 12px 16px; margin-bottom: 14px; border-right: 4px solid var(--danger); }
.alerts-box b { color: var(--danger); font-size: 13px; display: block; margin-bottom: 2px; }

.recs-list { list-style: disc; margin-right: 20px; font-size: 13.5px; color: #40300a; line-height: 1.8; font-weight: 700; space-y-1.5 }

.section-title { font-size: 18px; font-weight: 900; color: var(--dark); margin: 6mm 0 4mm; border-bottom: 2px solid var(--line); padding-bottom: 4px; display: flex; justify-content: space-between; align-items: center; }

/* جداول تحلیلی روندها و آزمون‌ها */
.trend-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 6mm; }
.trend-card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.015); }
.trend-card .t-date { font-size: 11px; color: var(--muted); font-weight: 900; display: block; margin-bottom: 4px; }
.trend-card .t-pct { font-size: 24px; font-weight: 1000; color: var(--sage); font-family: monospace; display: block; margin: 4px 0; }
.trend-card .t-meta { font-size: 11px; color: var(--ink); font-weight: bold; display: block; }

.table-card { background: #fff; border: 1px solid var(--line); border-radius: 18px; overflow: hidden; margin-bottom: 8mm; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
.tbl { width: 100%; border-collapse: collapse; text-align: right; }
.tbl th { background: var(--dark); color: #fff; font-size: 13px; font-weight: 900; padding: 12px 16px; }
.tbl td { padding: 12px 16px; border-bottom: 1px solid var(--line); font-size: 13px; font-weight: bold; color: var(--ink); }
.tbl tr:last-child td { border-bottom: none; }
.tbl tr:hover td { background: #f0f4f1; }

/* ارزیابی رفتاری و خودارزیابی */
.behavioral-matrix { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 8mm; }
.beh-box { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 18px; box-shadow: 0 3px 15px rgba(0,0,0,0.015); }
.beh-box h4 { font-size: 15px; font-weight: 900; color: var(--dark); margin-bottom: 10px; border-bottom: 1px dashed var(--line); padding-bottom: 6px; }
.beh-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: bold; color: var(--muted); padding: 6px 0; }
.beh-row b { font-family: monospace; font-size: 14px; color: var(--ink); }

/* یادداشت‌ها و جمع‌بندی داوطلب */
.reflections-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 8mm; }
.ref-card { background: #f8faf8; border: 1px solid var(--line); border-radius: 18px; padding: 16px 20px; border-right: 5px solid var(--sage); }
.ref-card h5 { font-size: 14px; font-weight: 900; color: var(--sage); margin-bottom: 6px; }
.ref-card p { font-size: 13.5px; color: var(--ink); line-height: 1.7; margin: 0; font-weight: 700; }

.ach-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 8mm; }
.ach-card { background: linear-gradient(135deg, #fffbc8 0%, #fff 100%); border: 1px solid #cbac80; border-radius: 16px; padding: 12px; text-align: center; }
.ach-card b { font-size: 13px; color: #9a761e; display: block; margin-top: 4px; font-weight: 900; }

@media print {
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; box-shadow: none !important; }
  @page { size: A4 portrait; margin: 0; }
  html, body {
    margin: 0 !important; padding: 0 !important; width: 210mm !important;
    background: #fff !important; color: #172a21 !important; overflow: visible !important;
    font-family: Vazirmatn, MadarPDF, MadarFallback, Tahoma, sans-serif !important;
    font-feature-settings: "ss01" 1;
  }
  .screen-actions { display: none !important; }
  .page {
    width: 210mm !important; height: 297mm !important; min-height: 297mm !important;
    margin: 0 !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important;
    overflow: hidden !important; background: #fbfaf4 !important;
    page-break-after: always !important; break-after: page !important; page-break-inside: avoid !important; break-inside: avoid-page !important;
  }
  .page:last-of-type { page-break-after: auto !important; break-after: auto !important; }
  .page::before {
    content: '' !important; position: absolute !important; inset: 5.5mm !important; z-index: 1 !important;
    border: .45mm solid rgba(178,148,95,.42) !important; border-radius: 7mm !important;
    box-shadow: inset 0 0 0 .35mm rgba(107,136,114,.18) !important; pointer-events: none !important;
  }
  .tpl {
    display: block !important; position: absolute !important; inset: 0 !important; width: 100% !important; height: 100% !important;
    object-fit: cover !important; z-index: 0 !important; opacity: .30 !important; filter: saturate(1.08) contrast(1.02) !important;
  }
  .inner {
    position: relative !important; z-index: 2 !important;
    height: 297mm !important; min-height: 0 !important; padding: 9.5mm 10.5mm 8.5mm !important;
    display: flex !important; flex-direction: column !important; overflow: hidden !important;
  }
  .page::after { font-size: 24mm !important; left: 12mm !important; bottom: 14mm !important; z-index: 1 !important; color: rgba(32,48,40,.045) !important; }

  .top {
    margin-bottom: 4mm !important; padding: 3mm 4mm 3mm !important;
    background: linear-gradient(135deg, rgba(23,42,33,.96), rgba(46,72,58,.92)) !important;
    border: .35mm solid rgba(203,172,128,.55) !important; border-radius: 5mm !important;
    color: #fff !important;
  }
  .logo { width: 38px !important; height: 38px !important; border-radius: 12px !important; border-color: #e0c595 !important; background:#fff !important; }
  .brand { gap: 9px !important; }
  .brand b { font-size: 18px !important; color:#fff !important; letter-spacing:-.03em !important; }
  .brand small { color:#e0c595 !important; }
  .brand small, .top-meta { font-size: 9.5px !important; line-height: 1.45 !important; }
  .top-meta { color:#eef4ef !important; }

  .report-hero { gap: 8px !important; margin-bottom: 3.5mm !important; }
  .card {
    padding: 10px 12px !important; border-radius: 4.2mm !important;
    background: rgba(255,255,255,.82) !important; border: .25mm solid rgba(107,136,114,.28) !important;
    backdrop-filter: none !important;
  }
  .hero { background: linear-gradient(135deg, rgba(255,255,255,.92), rgba(249,246,235,.86)) !important; border-right: 1.1mm solid #b2945f !important; }
  .hero h1 { font-size: 19px !important; line-height: 1.25 !important; margin-bottom: 2px !important; color:#172a21 !important; letter-spacing:-.03em !important; }
  .hero p, .hero div { font-size: 10.5px !important; line-height: 1.55 !important; }
  .score-card { background: linear-gradient(145deg, #132a20, #6b8872) !important; border: .45mm solid #d8bd86 !important; }
  .score-card .v { font-size: 29px !important; color:#f4d790 !important; text-shadow:0 1px 0 rgba(0,0,0,.22) !important; }
  .score-card .k, .score-card .sub { font-size: 9px !important; color:#eef4ef !important; }

  .report-meta-box {
    margin-bottom: 4mm !important; padding: 8px 12px !important; border-radius: 3.5mm !important; font-size: 10.5px !important; gap: 8px !important;
    background: linear-gradient(135deg, #172a21, #243f32) !important; border: .35mm solid #d4b77b !important;
  }
  .report-meta-box .status-badge { font-size: 10px !important; padding: 2px 9px !important; }
  .stats-matrix { gap: 6px !important; margin-bottom: 4mm !important; }
  .stat-box {
    padding: 8px !important; border-radius: 3.4mm !important;
    background: linear-gradient(180deg, rgba(255,255,255,.94), rgba(250,247,238,.90)) !important;
    border-color: rgba(178,148,95,.28) !important;
  }
  .stat-box .k { font-size: 9px !important; color:#52665b !important; }
  .stat-box .v { font-size: 20px !important; }

  .insight-vip-suite {
    padding: 11px 13px !important; margin-bottom: 0 !important; border-radius: 4mm !important;
    background: linear-gradient(135deg, rgba(255,252,244,.96), rgba(255,246,218,.92)) !important;
    border: .45mm solid #c8a76a !important;
  }
  .insight-header { padding-bottom: 7px !important; margin-bottom: 7px !important; gap: 8px !important; }
  .insight-score-badge { width: 42px !important; height: 42px !important; font-size: 16px !important; background:#172a21 !important; color:#f4d790 !important; }
  .insight-header h3 { font-size: 14px !important; color:#172a21 !important; }
  .status-pill { font-size: 10px !important; padding: 3px 9px !important; background:#b2945f !important; color:#fff !important; }
  .insight-summary { font-size: 10.8px !important; line-height: 1.55 !important; margin-bottom: 8px !important; }
  .sub-scores-grid { gap: 5px !important; margin-bottom: 8px !important; }
  .sub-score-cell { padding: 5px 7px !important; border-radius: 8px !important; font-size: 9.6px !important; }
  .sub-score-cell b { font-size: 10px !important; }
  .alerts-box { padding: 7px 9px !important; margin-bottom: 7px !important; border-radius: 8px !important; }
  .alerts-box b, .alerts-box li, .recs-list, .recs-list li { font-size: 10px !important; line-height: 1.55 !important; }
  .recs-list { margin-right: 14px !important; }

  .section-title {
    font-size: 13.5px !important; margin: 4mm 0 2.5mm !important; padding: 2mm 3mm !important;
    border-bottom: 0 !important; border-radius: 3.5mm !important;
    background: linear-gradient(135deg, rgba(23,42,33,.94), rgba(107,136,114,.86)) !important;
    color:#fff !important;
  }
  .section-title .badge { background: rgba(255,255,255,.18) !important; color:#fff !important; border: .2mm solid rgba(255,255,255,.25) !important; }
  .trend-grid, .behavioral-matrix, .reflections-grid, .ach-grid { gap: 7px !important; margin-bottom: 4mm !important; }
  .trend-card { padding: 8px !important; border-radius: 3.5mm !important; background: rgba(255,255,255,.88) !important; border-color: rgba(178,148,95,.30) !important; }
  .trend-card .t-date, .trend-card .t-meta { font-size: 9px !important; }
  .trend-card .t-pct { font-size: 18px !important; color:#6b8872 !important; }
  .table-card, .subjects-table-card { border-radius: 3.8mm !important; margin-bottom: 4mm !important; overflow: hidden !important; border-color: rgba(178,148,95,.32) !important; }
  .tbl th { font-size: 10px !important; padding: 7px 8px !important; background: linear-gradient(135deg,#172a21,#2f5341) !important; color:#fff !important; }
  .tbl td { font-size: 10px !important; padding: 6px 8px !important; line-height: 1.45 !important; background: rgba(255,255,255,.78) !important; }
  .tbl, .tbl * { page-break-inside: avoid !important; break-inside: avoid !important; }

  .beh-box { padding: 10px !important; border-radius: 10px !important; }
  .beh-box h4 { font-size: 11.5px !important; margin-bottom: 5px !important; padding-bottom: 4px !important; }
  .beh-row { font-size: 10px !important; padding: 3px 0 !important; }
  .beh-row b { font-size: 10px !important; }
  .ref-card { padding: 9px 11px !important; border-radius: 10px !important; border-right-width: 3px !important; }
  .ref-card h5 { font-size: 10.5px !important; margin-bottom: 3px !important; }
  .ref-card p { font-size: 10px !important; line-height: 1.55 !important; }
  .ach-card { padding: 7px !important; border-radius: 10px !important; }
  .ach-card b { font-size: 10px !important; }

  .logs-page .inner { padding: 8mm 8mm 7mm !important; }
  .logs-page .tbl th { font-size: 8.5px !important; padding: 5px 6px !important; }
  .logs-page .tbl td { font-size: 8.5px !important; padding: 4px 6px !important; line-height: 1.32 !important; }

  a { color: inherit !important; text-decoration: none !important; }
}
</style>
<script>
function printReport() {
    document.body.classList.add('printing');
    const imgs = [...document.images].map(i => i.complete ? Promise.resolve() : new Promise(r => { i.onload = i.onerror = r; }));
    Promise.all(imgs).then(() => setTimeout(() => window.print(), 250));
}
window.addEventListener('afterprint', () => document.body.classList.remove('printing'));
</script>
</head>
<body>

<div class="screen-actions">
  <button class="btn flex items-center gap-2" onclick="printReport()">
    <span>🖨️ چاپ / ذخیره PDF گزارش پیشرفته و تحلیل مَدار</span>
  </button>
  <a class="btn ghost" href="<?= url($u['role']==='student' ? ('student/reports.php?type=' . $type) : ('admin/reports.php?student=' . $studentId)) ?>">بازگشت به گزارش‌ها</a>
  <span class="hint">قالب چاپی هوشمند مَدار · معماری چندصفحه‌ای و ضدتداخل چاپی</span>
</div>

<!-- ================= PAGE 1: EXECUTIVE OVERVIEW & AI INSIGHT Suite ================= -->
<section class="page cover">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top">
      <div class="brand">
        <div class="logo"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
        <div><b>مَدار</b><small>STUDY OS</small></div>
      </div>
      <div class="top-meta">
        گزارش پیشرفته تحصیلی<br>
        <?= jalali_date('now', true) ?>
      </div>
    </header>

    <div class="report-hero">
      <div class="card hero">
        <div style="font-size: 12px; font-weight: bold; color: var(--sage); margin-bottom: 4px;">گزارش حرفه‌ای و ارزیابی بازدهی مطالعاتی</div>
        <h1>پرونده <?= e(report_type_label($type)) ?> دانش‌آموز</h1>
        <p class="mt-1">دانش‌آموز: <?= e($student['full_name']) ?> (<?= e($student['field'] ?: 'رشته نامشخص') ?> <?= $student['grade'] ? ' · ' . e($student['grade']) : '' ?>)</p>
        <div style="margin-top: 10px; font-size: 13px; color: var(--muted); font-weight: bold;">
          مشاور راهبر: <?= e($advisor ? $advisor['full_name'] : APP_OWNER) ?>
        </div>
      </div>
      
      <!-- Box پیشرفت وزنی -->
      <div class="card score-card">
        <span class="k">پیشرفت وزنی برنامه</span>
        <b class="v"><?= fa_num($s['progress_percent']) ?>٪</b>
        <span class="sub">شاخص کل اجرای تسک‌ها</span>
      </div>
    </div>

    <!-- باکس شیک و جدید جهت جایگزینی استایل بدِ درخواستی -->
    <div class="report-meta-box">
      <div class="meta-item">
        <span>📅 بازه ارزیابی:</span>
        <b style="color: #e0c595; font-family: monospace; font-size: 14.5px;"><?= jalali_date($r['period_start']) ?><?= $r['period_start'] !== $r['period_end'] ? ' تا ' . jalali_date($r['period_end']) : '' ?></b>
      </div>
      <div class="meta-item">
        <span>🛡️ وضعیت نهایی:</span>
        <span class="status-badge <?= $r['status'] === 'submitted' ? 'submitted' : 'draft' ?>">
          <?= $r['status'] === 'submitted' ? '✓ تایید نهایی (ارسال‌شده)' : '✎ پیش‌نویس (نیازمند تکمیل)' ?>
        </span>
      </div>
    </div>

    <!-- ماتریس عملکرد خام -->
    <div class="stats-matrix">
      <div class="stat-box">
        <span class="k">تسک کامل ✓</span>
        <b class="v green"><?= fa_num($s['full']) ?></b>
      </div>
      <div class="stat-box">
        <span class="k">تسک ناقص ●</span>
        <b class="v gold"><?= fa_num($s['partial']) ?></b>
      </div>
      <div class="stat-box">
        <span class="k">تسک انجام‌نشده ✗</span>
        <b class="v red"><?= fa_num($s['missed']) ?></b>
      </div>
      <div class="stat-box">
        <span class="k">ساعت مطالعه مؤثر ⏳</span>
        <b class="v slate"><?= fa_num($s['study_hours']) ?></b>
      </div>
    </div>

    <!-- ================= MADAR AI INSIGHT Suite ================= -->
    <?php if ($analysis): ?>
    <div class="insight-vip-suite">
      <div class="insight-header">
        <div style="display: flex; align-items: center; gap: 16px;">
          <div class="insight-score-badge"><?= fa_num($analysis['overall']) ?>٪</div>
          <div>
            <div style="font-size: 11px; color: var(--gold); font-weight: 900;">شاخص بازدهی و کیفیت مطالعاتی</div>
            <h3 style="font-size: 19px; font-weight: 900; color: var(--dark); margin: 0;">تحلیل هوشمند مَدار (Madar AI Insight) · بتا</h3>
          </div>
        </div>
        <span class="status-pill"><?= e($analysis['overall_label']) ?></span>
      </div>

      <p class="insight-summary"><?= e($analysis['summary']) ?></p>

      <!-- Sub scores grid -->
      <div class="sub-scores-grid">
        <?php foreach (['execution'=>'اجرای برنامه','consistency'=>'ثبات عملکرد','tests'=>'کیفیت تست‌زنی','study_quality'=>'کیفیت مطالعه','recovery'=>'خواب و ریکاوری','subject_balance'=>'تعادل درس‌ها'] as $k => $lbl): $v = (int)($analysis['scores'][$k] ?? 0); ?>
          <div class="sub-score-cell">
            <span><?= e($lbl) ?></span>
            <b style="color: <?= $v >= 75 ? 'var(--success)' : ($v >= 50 ? 'var(--dark)' : 'var(--danger)') ?>;"><?= fa_num($v) ?>٪</b>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Alerts -->
      <?php if (!empty($analysis['alerts'])): ?>
        <div class="alerts-box">
          <b>⚠️ هشدارهای حیاتی سیستم:</b>
          <ul style="list-style: circle; margin-right: 20px; font-size: 13px; color: var(--dark); font-weight: bold;">
            <?php foreach ($analysis['alerts'] as $al): ?>
              <li><?= e($al['title']) ?>: <span style="font-weight: normal; color: var(--muted);"><?= e($al['text']) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Practical Recommendations -->
      <?php if (!empty($analysis['recommendations'])): ?>
        <div style="margin-top: 12px;">
          <b style="font-size: 13.5px; color: var(--dark); display: block; margin-bottom: 4px;">💡 پیشنهادهای عملی و استراتژیک:</b>
          <ul class="recs-list">
            <?php foreach ($analysis['recommendations'] as $rec): ?>
              <li><?= e($rec) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Action Plan -->
      <?php if (!empty($analysis['action_plan'])): ?>
        <div style="margin-top: 12px; border-top: 1px solid rgba(203,172,128,0.3); pt-2.5">
          <b style="font-size: 13.5px; color: var(--gold-dark); display: block; margin-bottom: 4px;">🚀 نقشه اقدام کوتاه (Action Plan):</b>
          <ul class="recs-list">
            <?php foreach ($analysis['action_plan'] as $rec): ?>
              <li><?= e($rec) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<!-- ================= PAGE 2: HISTORICAL TRENDS & EXAM PERFORMANCE ================= -->
<section class="page">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top">
      <div class="brand">
        <div class="logo" style="width: 44px; height: 44px;"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
        <div><b style="font-size: 18px;">مَدار</b><small style="font-size: 9px;"><?= e(APP_OWNER) ?></small></div>
      </div>
      <div class="top-meta" style="font-size: 12px;">
        تحلیل رشد و آزمون‌ها · ص ۲
      </div>
    </header>

    <div class="section-title">
      <span>📈 مقایسه با دوره‌های گذشته (روند رشد تحصیلی)</span>
      <span class="badge" style="background:var(--sage); color:#fff; font-size:12px;">۴ دوره اخیر</span>
    </div>

    <?php if (empty($historyReports)): ?>
      <div class="card text-center py-8 text-muted font-bold">داده‌ی کافی از دوره‌های گذشته برای رسم نمودار روند وجود ندارد.</div>
    <?php else: ?>
      <div class="trend-grid">
        <?php foreach (array_reverse($historyReports) as $hr): 
            $hSnap = $hr['auto_snapshot_json'] ? json_decode($hr['auto_snapshot_json'], true) : [];
            $hPct  = (int)($hSnap['progress_percent'] ?? 0);
            $hHrs  = (float)($hSnap['study_hours'] ?? 0);
            $hTst  = (int)($hSnap['tests_done'] ?? 0);
        ?>
          <div class="trend-card">
            <span class="t-date"><?= jalali_date($hr['period_start']) ?></span>
            <span class="t-pct"><?= fa_num($hPct) ?>٪</span>
            <div style="width: 100%; height: 6px; background: #e0e6e2; border-radius: 999px; margin: 6px 0; overflow: hidden;">
              <div style="width: <?= min(100, $hPct) ?>%; height: 100%; background: var(--sage); border-radius: 999px;"></div>
            </div>
            <span class="t-meta"><?= fa_num($hHrs) ?> ساعت · <?= fa_num($hTst) ?> تست</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="section-title" style="margin-top: 10mm;">
      <span>🏆 نتایج و کارنامه‌های آزمون در این بازه</span>
      <span class="badge" style="background:var(--gold); color:#000; font-size:12px;">آزمون‌های آنلاین</span>
    </div>

    <?php if (empty($periodExams)): ?>
      <div class="card text-center py-10 text-muted font-bold">دانش‌آموز در این بازه زمانی در هیچ آزمون آنلاینی شرکت نکرده است.</div>
    <?php else: ?>
      <div class="table-card">
        <table class="tbl">
          <thead>
            <tr>
              <th>عنوان آزمون سنجش</th>
              <th style="text-align: center;">نوع آزمون</th>
              <th style="text-align: center;">زمان اتمام</th>
              <th style="text-align: center;">نمره کل (درصد)</th>
              <th style="text-align: center;">تراز تخمینی</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($periodExams as $pex): 
                $pScore = round((float)$pex['total_score'], 1);
                $pTaraz = 5000 + (($pScore - 18.5) / 16.2) * 1000;
                $pTaraz = max(2500, min(11500, (int)round($pTaraz)));
            ?>
            <tr>
              <td><b><?= e($pex['title']) ?></b></td>
              <td style="text-align: center;"><span class="badge" style="padding:2px 8px; font-size:11px; background:var(--surface-3);"><?= $pex['exam_type']==='comprehensive'?'جامع':'تکی' ?></span></td>
              <td style="text-align: center; font-family: monospace; font-size: 13px;"><?= jalali_date($pex['submitted_at'], true) ?></td>
              <td style="text-align: center; font-family: monospace; font-size: 15px; color: var(--success); font-weight: 1000;"><?= fa_num($pScore) ?>٪</td>
              <td style="text-align: center; font-family: monospace; font-size: 15px; color: var(--gold); font-weight: 1000;"><?= fa_num($pTaraz) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- بخش دستاوردها -->
    <?php if (!empty($periodAchs)): ?>
      <div class="section-title">
        <span>🎖️ نشان‌ها و دستاوردهای کسب‌شده</span>
        <span class="badge" style="background:var(--dark); color:#gold; font-size:12px;">گیمیفیکیشن</span>
      </div>
      <div class="ach-grid">
        <?php foreach ($periodAchs as $ach): ?>
          <div class="ach-card">
            <span style="font-size: 26px; display: block;"><?= icon($ach['icon'] ?: 'trophy', 28) ?></span>
            <b><?= e($ach['title']) ?></b>
            <span style="font-size: 11px; color: var(--muted); display: block; margin-top: 2px;">تاریخ اعطا: <?= jalali_date($ach['awarded_at']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- ================= PAGE 3: BEHAVIORAL METRICS & REFLECTIONS ================= -->
<section class="page">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top">
      <div class="brand">
        <div class="logo" style="width: 44px; height: 44px;"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
        <div><b style="font-size: 18px;">مَدار</b><small style="font-size: 9px;"><?= e(APP_OWNER) ?></small></div>
      </div>
      <div class="top-meta" style="font-size: 12px;">
        سنجه‌های رفتاری · ص ۳
      </div>
    </header>

    <div class="section-title">
      <span>🧠 سنجه‌های زیستی-رفتاری و مدیریت زمان</span>
      <span class="badge" style="background:var(--info); color:#fff; font-size:12px;">ثبت تایم‌شیت</span>
    </div>

    <div class="behavioral-matrix">
      <div class="beh-box">
        <h4>شاخص‌های ریکاوری و انرژی</h4>
        <div class="beh-row"><span>میانگین خواب مفید:</span><b><?= isset($a['sleep_hours']) ? fa_num($a['sleep_hours']) . ' ساعت' : 'ثبت نشده' ?></b></div>
        <div class="beh-row"><span>کیفیت خواب:</span><b><?= isset($a['sleep_quality']) ? fa_num($a['sleep_quality']) . ' از ۵' : 'ثبت نشده' ?></b></div>
        <div class="beh-row"><span>سطح تمرکز مطالعاتی:</span><b><?= isset($a['focus_score']) ? fa_num($a['focus_score']) . ' از ۱۰' : '—' ?></b></div>
        <div class="beh-row"><span>سطح انرژی روزانه:</span><b><?= isset($a['energy_score']) ? fa_num($a['energy_score']) . ' از ۱۰' : '—' ?></b></div>
        <div class="beh-row"><span>کنترل استرس:</span><b><?= isset($a['stress_score']) ? fa_num($a['stress_score']) . ' از ۱۰' : '—' ?></b></div>
      </div>

      <div class="beh-box">
        <h4>مدیریت زمان و حواشی</h4>
        <div class="beh-row"><span>زمان استفاده از موبایل:</span><b style="color: <?= (int)($a['phone_minutes'] ?? 0) > 60 ? 'var(--danger)' : 'var(--ink)' ?>;"><?= fa_num($a['phone_minutes'] ?? 0) ?> دقیقه</b></div>
        <div class="beh-row"><span>اتلاف وقت تخمینی:</span><b style="color: <?= (int)($a['wasted_minutes'] ?? 0) > 45 ? 'var(--danger)' : 'var(--ink)' ?>;"><?= fa_num($a['wasted_minutes'] ?? 0) ?> دقیقه</b></div>
        <div class="beh-row"><span>نمره خودارزیابی دانش‌آموز:</span><b style="color: var(--sage); font-size: 15px;"><?= isset($a['self_score']) ? fa_num($a['self_score']) . ' از ۲۰' : '—' ?></b></div>
        <div class="beh-row"><span>ارزیابی کلی بازه:</span><b><?= e($a['week_rating'] ?? $a['monthly_mindset'] ?? $a['main_reason'] ?? '—') ?></b></div>
        <div class="beh-row"><span>نیاز به پیگیری مشاور:</span><b><?= e($a['advisor_followup'] ?? 'خیر') ?></b></div>
      </div>
    </div>

    <div class="section-title">
      <span>📝 یادداشت‌ها و جمع‌بندی کیفی داوطلب</span>
      <span class="badge" style="background:var(--sage); color:#fff; font-size:12px;">خودارزیابی</span>
    </div>

    <div class="reflections-grid">
      <?php foreach ([
        'best_win'         => '🌟 بهترین برد / نقطه قوت در این بازه',
        'main_challenge'   => '⚠️ چالش اصلی و مانع مطالعاتی',
        'challenge_reason' => '🔍 علت احتمالی بروز چالش',
        'solution'         => '💡 راهکار پیشنهادی خودِ دانش‌آموز',
        'next_priority'    => '🎯 اولویت اصلی برای بازه بعدی',
        'advisor_question' => '❓ سؤال یا درخواست تخصصی از مشاور'
      ] as $k => $lbl): if (!empty($a[$k])): ?>
        <div class="ref-card">
          <h5><?= e($lbl) ?></h5>
          <p><?= nl2br(e($a[$k])) ?></p>
        </div>
      <?php endif; endforeach; ?>
    </div>

  </div>
</section>

<!-- ================= PAGE 4: LESSON PERFORMANCE TABLE ================= -->
<section class="page">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top">
      <div class="brand">
        <div class="logo" style="width: 44px; height: 44px;"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
        <div><b style="font-size: 18px;">مَدار</b><small style="font-size: 9px;"><?= e(APP_OWNER) ?></small></div>
      </div>
      <div class="top-meta" style="font-size: 12px;">
        عملکرد درس‌ها · ص ۴
      </div>
    </header>

    <div class="section-title">
      <span>📚 ریز عملکرد مطالعاتی و تستی درس‌ها</span>
      <span class="badge" style="background:var(--dark); color:#fff; font-size:12px;">تایم‌شیت هفتگی</span>
    </div>

    <?php if (empty($s['by_subject'])): ?>
      <div class="card text-center py-12 text-muted font-bold">داده‌ی تفکیکی درس‌ها برای این پرونده ثبت نشده است.</div>
    <?php else: ?>
      <div class="subjects-table-card">
        <table class="tbl">
          <thead>
            <tr>
              <th>نام درس تحصیلی</th>
              <th style="text-align: center;">زمان مطالعه (ساعت)</th>
              <th style="text-align: center;">تست‌های حل‌شده</th>
              <th style="text-align: center;">تسک‌های قرمز (انجام‌نشده)</th>
              <th style="text-align: center;">پوشش کیفی</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($s['by_subject'] as $sname => $sx): 
                $hrs = round(($sx['minutes'] ?? 0) / 60, 1);
                $tst = (int)($sx['tests'] ?? 0);
                $msd = (int)($sx['missed'] ?? 0);
                $tsk = (int)($sx['tasks'] ?? 0);
                $pct = $tsk > 0 ? min(100, round(($sx['score'] ?? 0) / $tsk * 100)) : 0;
            ?>
            <tr>
              <td><b style="font-size: 14px;"><?= e($sname) ?></b></td>
              <td style="text-align: center; font-family: monospace; font-size: 14px;"><?= fa_num($hrs) ?> ساعت</td>
              <td style="text-align: center; font-family: monospace; font-size: 14px;"><span style="color: var(--sage); font-weight:1000;"><?= fa_num($tst) ?> تست</span></td>
              <td style="text-align: center; font-family: monospace; font-size: 14px;"><span style="color: <?= $msd > 0 ? 'var(--danger)' : 'var(--muted)' ?>; font-weight:1000;"><?= fa_num($msd) ?> مورد</span></td>
              <td style="text-align: center;">
                <div style="display: flex; align-items: center; gap: 10px; justify-content: center;">
                  <b style="font-family: monospace; font-size: 13px; width: 36px;"><?= fa_num($pct) ?>٪</b>
                  <div style="width: 80px; height: 8px; background: #e0e6e2; border-radius: 999px; overflow: hidden; display: inline-block;">
                    <div style="width: <?= min(100, $pct) ?>%; height: 100%; background: <?= $pct >= 75 ? 'var(--success)' : ($pct >= 50 ? 'var(--dark)' : 'var(--danger)') ?>; border-radius: 999px;"></div>
                  </div>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- Verification Footer Box -->
    <div class="card" style="margin-top: auto; background: #fcfdf9; border: 2px dashed #cbac80; padding: 20px;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <b style="font-size: 15px; color: var(--dark); display: block;">صحت‌سنجی و تایید رسمی گزارش تحلیلی</b>
          <span style="font-size: 12.5px; color: var(--muted); display: block; margin-top: 2px;">این پرونده بر مبنای الگوریتم‌های هوش مصنوعی مَدار و نظارت مستقیم مشاور ارشد صادر گردیده است.</span>
        </div>
        <div style="text-align: left; direction: ltr;">
          <b style="color: var(--gold); font-family: monospace; font-size: 16px; display: block;">VERIFIED STUDY OS</b>
          <span style="font-size: 11px; color: var(--dark); font-weight: bold;">Dr. Sajjad Sayyadi</span>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- ================= PAGE 5+: RECENT ACTIVITY LOGS ================= -->
<?php if (!empty($periodLogs)): ?>
<section class="page logs-page">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top" style="margin-bottom: 6mm;">
      <div class="brand">
        <div class="logo" style="width: 44px; height: 44px;"><img src="<?= $pdfLogo ?>" alt="مَدار"></div>
        <div><b style="font-size: 18px;">مَدار</b><small style="font-size: 9px;"><?= e(APP_OWNER) ?></small></div>
      </div>
      <div class="top-meta" style="font-size: 12px;">
        ریزفعالیت‌های بازه · ص ۵
      </div>
    </header>

    <div class="section-title mb-4">
      <span>🔎 جدول رخدادها و ریزفعالیت‌های ثبت‌شده در این بازه</span>
      <span class="badge" style="background:var(--dark); color:#fff; font-size:12px;"><?= fa_num(count($periodLogs)) ?> رخداد اخیر</span>
    </div>

    <div class="table-card">
      <table class="tbl text-sm">
        <thead>
          <tr>
            <th style="width: 150px;">تاریخ و زمان</th>
            <th style="width: 180px;">دسته‌بندی عملیات</th>
            <th>شرح رخداد (کاملاً خوانا)</th>
            <th style="text-align: left; width: 140px;">IP شبکه</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($periodLogs as $log): 
              $hum = parse_human_log($log);
          ?>
          <tr>
            <td style="font-family: monospace; font-size: 12.5px;"><?= jalali_date($log['created_at'], true) ?></td>
            <td><span class="badge" style="padding: 2px 8px; font-size: 11px; background: var(--surface-3);"><?= e($hum['category_name']) ?></span></td>
            <td><b style="color: var(--dark); font-size: 13px;"><?= e($hum['persian_action']) ?></b></td>
            <td style="text-align: left; font-family: monospace; font-size: 12px; color: var(--muted);"><?= e($log['ip_address'] ?? '127.0.0.1') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</section>
<?php endif; ?>

</body>
</html>