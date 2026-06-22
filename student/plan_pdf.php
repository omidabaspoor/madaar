<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_role('student');
$u = current_user();

$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
$weekEnd = date('Y-m-d', strtotime($weekStart.' +6 day'));
$st = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? AND status="published" LIMIT 1');
$st->execute([$u['id'], $weekStart]);
$plan = $st->fetch();
if (!$plan) { flash('error','برای این هفته برنامه‌ای منتشر نشده'); redirect('student/plan.php?week='.$weekStart); }

$rows = db()->prepare('SELECT t.*, s.name subj_name, s.color subj_color FROM tasks t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.plan_id=? ORDER BY t.day_index, t.unit_index, t.sort_order, t.id');
$rows->execute([$plan['id']]);
$grid = [];
$usedSubjects = [];
foreach ($rows->fetchAll() as $t) {
    $grid[(int)$t['day_index']][(int)$t['unit_index']][] = $t;
    if (!empty($t['subj_name'])) $usedSubjects[(string)$t['subj_name']] = true;
}
$progress = plan_progress((int)$plan['id']);
$totalTasks = (int)$progress['total'];
$template = asset('img/plan-pdf-template.png');
$pdfLogo = asset('img/logo.png');

function pdf_day_total(array $grid, int $day): int { $n = 0; foreach (UNIT_NAMES as $ui=>$_) $n += count($grid[$day][$ui] ?? []); return $n; }
function pdf_task_label(string $type): string { return TASK_TYPES[$type]['label'] ?? $type; }
function pdf_task_class(string $type): string { return preg_replace('/[^a-z_]/','',$type) ?: 'custom'; }
function pdf_valid_hex(?string $color, string $fallback = '#6b8872'): string {
    $color = trim((string)$color);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $fallback;
}
function pdf_subject_palette(): array {
    return [
        // رنگ‌ها عمداً ملایم ولی کاملاً متمایز انتخاب شده‌اند.
        'ریاضی' => '#6E5B9A',        // بنفش ریاضی
        'حسابان' => '#6E5B9A',
        'شیمی' => '#B58A45',         // طلایی/کهربایی
        'فیزیک' => '#3F7F9F',        // آبی نفتی
        'زیست' => '#3B8B5B',         // سبز زیستی
        'زیست‌شناسی' => '#3B8B5B',
        'هندسه' => '#4F8C86',        // فیروزه‌ای خاکی
        'گسسته' => '#8A6A52',        // قهوه‌ای ملایم
        'هویت' => '#6F6F78',         // خاکستری-اسلیت
        'سلامت' => '#C06C84',        // رز/گلبهی ملایم، متفاوت از زیست
        'عربی' => '#A0754C',         // آجری ملایم
        'دینی' => '#7A5AA6',         // بنفش سرد، متفاوت از سلامت/زیست
        'ادبیات' => '#9A5A8A',       // ارغوانی ادبیات
        'زبان انگلیسی' => '#5578A6', // آبی زبان
        'زبان' => '#5578A6',
    ];
}
function pdf_norm_subject(string $name): string {
    $name = str_replace(['ي','ك','‌'], ['ی','ک',' '], trim($name));
    return preg_replace('/\s+/u', ' ', $name) ?: '';
}
function pdf_subject_color_by_name(?string $name): ?string {
    $name = pdf_norm_subject((string)$name);
    if ($name === '') return null;
    $palette = pdf_subject_palette();
    if (isset($palette[$name])) return $palette[$name];
    foreach ($palette as $key=>$color) {
        if (str_contains($name, $key) || str_contains($key, $name)) return $color;
    }
    return null;
}
function pdf_subject_groups(): array {
    return [
        'رشته تجربی' => ['ریاضی','شیمی','فیزیک','زیست'],
        'رشته ریاضی' => ['حسابان','شیمی','فیزیک','هندسه','گسسته'],
        'عمومی‌ها' => ['هویت','سلامت','عربی','دینی','ادبیات','زبان انگلیسی'],
    ];
}
function pdf_legend_groups(string $field, array $usedSubjects): array {
    $field = pdf_norm_subject($field);
    $groups = pdf_subject_groups();
    if (str_contains($field, 'تجربی')) return ['رشته تجربی'=>$groups['رشته تجربی'], 'عمومی‌ها'=>$groups['عمومی‌ها']];
    if (str_contains($field, 'ریاضی')) return ['رشته ریاضی'=>$groups['رشته ریاضی'], 'عمومی‌ها'=>$groups['عمومی‌ها']];
    $out = [];
    foreach ($groups as $g=>$subjects) {
        $items = [];
        foreach ($subjects as $s) {
            foreach ($usedSubjects as $u) {
                $nu = pdf_norm_subject($u);
                if ($nu === pdf_norm_subject($s) || str_contains($nu, pdf_norm_subject($s)) || str_contains(pdf_norm_subject($s), $nu)) { $items[] = $s; break; }
            }
        }
        if ($items) $out[$g] = array_values(array_unique($items));
    }
    if (!$out && $usedSubjects) $out['درس‌های این برنامه'] = array_values($usedSubjects);
    return $out;
}
function pdf_hex_rgb(string $hex): array {
    $hex = ltrim(pdf_valid_hex($hex), '#');
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}
function pdf_task_color(array $t): string {
    $mapped = pdf_subject_color_by_name($t['subj_name'] ?? '');
    if ($mapped) return $mapped;
    if (!empty($t['subj_color'])) return pdf_valid_hex($t['subj_color']);
    return match ((string)$t['task_type']) {
        'test' => '#B58A45', 'exam' => '#C9A24A', 'reading' => '#D08A45', 'review' => '#5D8BA8',
        'textbook' => '#8E6A9E', 'descriptive' => '#C07A55', default => '#6B8872',
    };
}
function pdf_task_meta(array $t): array {
    $meta = [];
    if ($t['target_count'] !== null) $meta[] = fa_num($t['target_count']) . ' ' . e($t['target_unit']);
    if (!empty($t['duration_min'])) $meta[] = fa_num($t['duration_min']) . ' دقیقه';
    if (!empty($t['source'])) $meta[] = 'منبع: ' . e($t['source']);
    return $meta;
}
function pdf_render_task(array $t, int $idx): void {
    $cls = pdf_task_class((string)$t['task_type']);
    $meta = pdf_task_meta($t);
    $taskColor = pdf_task_color($t);
    [$cr,$cg,$cb] = pdf_hex_rgb($taskColor);
    $subjColor = pdf_subject_color_by_name($t['subj_name'] ?? '') ?: pdf_valid_hex($t['subj_color'] ?? $taskColor, $taskColor);
    ?>
    <article class="task <?= e($cls) ?> subject-colored" style="--accent:<?= e($taskColor) ?>;--accent-bg:rgba(<?= $cr ?>,<?= $cg ?>,<?= $cb ?>,.10)">
      <span class="task-no"><?= fa_num($idx) ?></span>
      <div class="task-content">
        <div class="task-tags">
          <span class="tag type"><?= e(pdf_task_label((string)$t['task_type'])) ?></span>
          <?php if($t['subj_name']): ?><span class="tag subj" style="--c:<?= e($subjColor) ?>"><?= e($t['subj_name']) ?></span><?php else: ?><span class="tag subj" style="--c:<?= e($taskColor) ?>">بدون درس</span><?php endif; ?>
        </div>
        <h4><?= e($t['title']) ?></h4>
        <?php if($meta): ?><div class="task-meta"><?= implode(' · ', $meta) ?></div><?php endif; ?>
        <?php if($t['description']): ?><p><?= e($t['description']) ?></p><?php endif; ?>
      </div>
      <span class="check"></span>
    </article>
    <?php
}
$subjectLegendGroups = pdf_legend_groups((string)($u['field'] ?? ''), array_keys($usedSubjects));
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>چاپ برنامه هفتگی · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
@font-face{font-family:Vazirmatn;src:local('Vazirmatn');font-display:swap}@font-face{font-family:MadarPDF;src:url('../assets/fonts/DejaVuSans.ttf') format('truetype');font-weight:400}@font-face{font-family:MadarPDF;src:url('../assets/fonts/DejaVuSans-Bold.ttf') format('truetype');font-weight:800}
:root{--ink:#14211b;--muted:#627169;--line:#dfe7df;--paper:#fcfdf9;--sage:#6b8872;--gold:#b2945f;--dark:#172a21;--soft:#f5f8f4;--info:#6f9bc0;--purple:#b88fc0;--orange:#d99f6f;--warn:#d9b25f}
*{box-sizing:border-box}html,body{margin:0;padding:0}body{background:#101c17;color:var(--ink);font-family:Vazirmatn,MadarPDF,Tahoma,"Segoe UI",sans-serif;line-height:1.55;-webkit-font-smoothing:antialiased;text-rendering:geometricPrecision}.screen-actions{position:sticky;top:0;z-index:50;display:flex;gap:10px;align-items:center;justify-content:center;padding:12px;background:rgba(12,21,18,.94);backdrop-filter:blur(14px)}.btn{border:none;border-radius:999px;padding:10px 18px;font:900 14px Vazirmatn,Tahoma;background:linear-gradient(135deg,#e0c595,#b2945f);color:#142018;text-decoration:none;cursor:pointer}.btn.ghost{background:#25352e;color:#eef4ef;border:1px solid #41554a}.hint{color:#cbd8ce;font-size:12px}.page{width:210mm;height:297mm;margin:14px auto;background:var(--paper);position:relative;overflow:hidden;page-break-after:always;break-after:page;isolation:isolate;box-shadow:0 24px 70px rgba(0,0,0,.38);border-radius:18px}.page:last-of-type{page-break-after:auto;break-after:auto}.tpl{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-5}.page::after{content:'مَدار';position:absolute;left:14mm;bottom:21mm;z-index:-2;color:rgba(32,48,40,.045);font-size:34mm;font-weight:1000;transform:rotate(-18deg);letter-spacing:-.08em}.inner{position:relative;z-index:2;padding:18mm 15mm 14mm;height:100%}.top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18mm}.brand{display:flex;gap:10px;align-items:center;color:#fff}.logo{width:54px;height:54px;border-radius:18px;background:rgba(255,255,255,.14);display:grid;place-items:center;overflow:hidden;box-shadow:inset 0 0 0 1px rgba(255,255,255,.15),0 8px 22px rgba(0,0,0,.18)}.logo img{width:100%;height:100%;object-fit:cover;display:block}.brand b{font-size:24px}.brand small{display:block;color:rgba(255,255,255,.62);font-weight:900;font-size:10px;letter-spacing:.12em}.top-meta{text-align:left;color:rgba(255,255,255,.78);font-weight:900;font-size:12px}.cover-hero{display:grid;grid-template-columns:1.35fr .65fr;gap:12px;margin-bottom:9mm}.card{background:rgba(255,255,255,.97);border:1px solid rgba(107,136,114,.18);border-radius:24px;padding:15px 17px;box-shadow:0 10px 28px rgba(20,33,27,.045)}.hero h1{font-size:28px;line-height:1.25;margin:0 0 6px;font-weight:900}.hero p{margin:0;color:var(--muted);font-weight:900}.student-card{background:linear-gradient(135deg,rgba(224,197,149,.22),rgba(255,255,255,.92));border-color:rgba(178,148,95,.28)}.student-card .k{display:block;color:var(--muted);font-weight:900;font-size:11px}.student-card .v{display:block;font-weight:1000;font-size:19px}.summary{display:grid;grid-template-columns:.8fr 1.2fr;gap:12px;margin-bottom:9mm}.total .k{display:block;color:var(--muted);font-weight:900;font-size:11px}.total .v{display:block;color:var(--sage);font-size:38px;font-weight:1000}.week h2{font-size:18px;margin:0 0 10px;font-weight:1000}.overview{display:grid;grid-template-columns:repeat(7,1fr);gap:7px}.daychip{min-height:32mm;text-align:center;border:1px solid var(--line);border-radius:16px;background:linear-gradient(180deg,#fff,#f8faf8);padding:8px}.daychip b{display:block;font-size:12px;font-weight:1000}.daychip span{display:block;color:var(--muted);font-weight:900;font-size:9px}.daychip strong{display:inline-grid;place-items:center;margin-top:6px;width:31px;height:31px;border-radius:12px;background:linear-gradient(135deg,#203028,#6b8872);color:#fff;font-size:16px}.legend{display:flex;gap:6px;flex-wrap:wrap}.legend span{border:1px solid var(--line);background:#fff;border-radius:999px;padding:4px 9px;font-weight:1000;font-size:9px}.subject-guide{margin-top:8mm}.subject-guide h2{font-size:18px;margin:0 0 9px;font-weight:1000}.subject-groups{display:grid;grid-template-columns:1fr 1fr;gap:8px}.subject-group{border:1px solid var(--line);border-radius:16px;background:rgba(255,255,255,.78);padding:8px}.subject-group-title{display:block;color:var(--muted);font-weight:1000;font-size:10px;margin-bottom:6px}.subject-chips{display:flex;gap:6px;flex-wrap:wrap}.subject-chip{display:inline-flex;align-items:center;gap:5px;border:1px solid rgba(32,48,40,.10);background:#fff;color:#14211b;border-radius:999px;padding:4px 8px;font-weight:1000;font-size:9.2px}.subject-chip i{width:9px;height:9px;border-radius:50%;background:var(--c);box-shadow:0 0 0 2px rgba(32,48,40,.05)}.task.subject-colored{border-right-color:var(--accent)!important;background:linear-gradient(90deg,var(--accent-bg),#fff 78%)!important}.task.subject-colored .task-no{background:var(--accent)!important}.day-head{display:flex;align-items:center;justify-content:space-between;margin:16mm 0 7mm}.day-head h1{font-size:27px;margin:0;font-weight:900}.day-head small{color:var(--muted);font-size:13px}.badge{background:linear-gradient(135deg,#203028,#6b8872);color:#fff;border-radius:999px;padding:8px 14px;font-weight:1000;font-size:12px}.unit-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.unit{min-height:50mm;background:rgba(255,255,255,.94);border:1px solid var(--line);border-radius:18px;padding:9px;break-inside:avoid;box-shadow:0 6px 18px rgba(23,34,29,.035)}.unit.special{background:linear-gradient(135deg,rgba(224,197,149,.17),rgba(255,255,255,.95));border-color:rgba(178,148,95,.34)}.unit-title{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed var(--line);padding-bottom:5px;margin-bottom:7px}.unit-title b{font-size:12.5px;font-weight:900}.unit-title span{font-size:9px;color:var(--muted);font-weight:900}.empty{height:30mm;border:1px dashed #d8ded8;border-radius:14px;display:grid;place-items:center;background:#f8faf8;color:#99a69f;font-weight:900;font-size:11px}.task{display:grid;grid-template-columns:24px 1fr 18px;gap:7px;margin-bottom:7px;padding:8px;border-radius:15px;border:1px solid #e3e9e3;border-right:5px solid var(--sage);background:#f7faf7;break-inside:avoid}.task.test{border-right-color:var(--gold);background:#fff8ec}.task.exam{border-right-color:var(--warn);background:#fff7df}.task.reading{border-right-color:#8aa791;background:#f1f8f3}.task.review{border-right-color:var(--info);background:#eff7fb}.task.textbook{border-right-color:var(--purple);background:#fbf3fd}.task.descriptive{border-right-color:var(--orange);background:#fff2ea}.task-no{width:22px;height:22px;border-radius:8px;background:#203028;color:#fff;display:grid;place-items:center;font-weight:1000;font-size:10px}.task-tags{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:3px}.tag{border-radius:999px;padding:1px 7px;font-weight:800;font-size:8.5px}.tag.type{background:#203028;color:#fff}.tag.subj{background:#fff;border:1px solid var(--c);color:var(--c)}.task h4{margin:0;color:#102018;font-size:14px;font-weight:900;line-height:1.55;letter-spacing:-.01em}.task-meta{color:#4f5e56;font-size:10.5px;font-weight:800;margin-top:1px}.task p{font-size:10px;color:#68776f;margin:2px 0 0}.check{width:15px;height:15px;border-radius:5px;border:2px solid #aab6af;background:#fff;margin-top:5px}.page-number{display:none}.cover-note{display:none}
@media print{*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}html,body{width:210mm;background:#fff;overflow:visible}.screen-actions{display:none}.page{margin:0 auto!important;width:190mm!important;height:267mm!important;box-shadow:none!important;border-radius:0!important;overflow:hidden!important;page-break-after:auto!important;break-after:auto!important;page-break-inside:avoid!important;break-inside:avoid!important}.page+.page{page-break-before:always!important;break-before:page!important}.inner{padding:14mm 10mm 9mm!important}.top{margin-bottom:10mm!important}.subject-guide{margin-top:5mm!important}.subject-groups{gap:5px!important}.subject-group{padding:6px!important}.subject-chip{font-size:8px!important;padding:3px 7px!important}.subject-group-title{font-size:9px!important;margin-bottom:4px!important}.day-head{margin:10mm 0 5mm!important}.unit-grid{gap:6px!important}.unit{height:43mm!important;min-height:43mm!important;overflow:hidden!important;padding:7px!important}.unit-title{margin-bottom:5px!important;padding-bottom:4px!important}.task{margin-bottom:5px!important;padding:6px!important;border-radius:12px!important;grid-template-columns:20px 1fr 15px!important}.task-no{width:19px!important;height:19px!important;font-size:9px!important}.task h4{font-size:11.5px!important;line-height:1.35!important}.task-meta{font-size:9px!important}.tag{font-size:7.5px!important;padding:0 6px!important}.empty{height:24mm!important}.tpl{display:block!important}@page{size:A4 portrait;margin:10mm}}
@media(max-width:900px){.screen-actions{justify-content:flex-start;overflow-x:auto}.page{margin:10px;width:210mm;height:297mm}}
</style>
<script>
function printPlan(){
  const imgs=[...document.images].map(img=>img.complete?Promise.resolve():new Promise(r=>{img.onload=img.onerror=r;}));
  const fonts=document.fonts&&document.fonts.ready?document.fonts.ready.catch(()=>{}):Promise.resolve();
  Promise.all([...imgs,fonts]).then(()=>setTimeout(()=>window.print(),150));
}
</script>
</head>
<body>
<div class="screen-actions">
  <button class="btn" onclick="printPlan()">چاپ / ذخیره PDF</button>
  <a class="btn ghost" href="<?= url('student/plan.php?week='.$weekStart) ?>">بازگشت به برنامه</a>
  <span class="hint">کیفیت متن در PDF پرینت، برداری و شفاف است.</span>
</div>

<section class="page cover">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top"><div class="brand"><div class="logo"><img src="<?= $pdfLogo ?>" alt="<?= e(APP_NAME) ?>"></div><div><b>مَدار</b><small>STUDY OS</small></div></div><div class="top-meta">PDF برنامه هفتگی<br><?= jalali_date('now', true) ?></div></header>
    <div class="cover-hero"><div class="card hero"><h1>برنامه اختصاصی هفته</h1><p><?= jalali_date($weekStart) ?> تا <?= jalali_date($weekEnd) ?> · مسیر دقیق اجرای روزانه</p></div><div class="card student-card"><span class="k">دانش‌آموز</span><span class="v"><?= e($u['full_name']) ?></span><span class="k"><?= e($u['field'] ?: 'دانش‌آموز') ?><?= $u['grade']?' · '.e($u['grade']):'' ?></span></div></div>
    <div class="summary"><div class="card total"><span class="k">کل تسک‌های هفته</span><span class="v"><?= fa_num($totalTasks) ?></span><span class="k">تسک برنامه‌ریزی‌شده</span></div><div class="card week"><h2>نمای هفتگی</h2><div class="overview"><?php foreach(DAY_NAMES as $di=>$dn): ?><div class="daychip"><b><?= e($dn) ?></b><span><?= jalali_date(date('Y-m-d', strtotime($weekStart." +$di day"))) ?></span><strong><?= fa_num(pdf_day_total($grid,$di)) ?></strong><span>تسک</span></div><?php endforeach; ?></div></div></div>
    <div class="card subject-guide"><h2>راهنمای رنگ درس‌ها</h2><div class="subject-groups">
      <?php foreach($subjectLegendGroups as $groupTitle=>$subjects): ?>
      <div class="subject-group"><span class="subject-group-title"><?= e($groupTitle) ?></span><div class="subject-chips">
        <?php foreach($subjects as $name): $c = pdf_subject_color_by_name($name) ?: '#6b8872'; ?>
        <span class="subject-chip" style="--c:<?= e($c) ?>"><i></i><?= e($name) ?></span>
        <?php endforeach; ?>
      </div></div>
      <?php endforeach; ?>
    </div></div>
  </div>
</section>

<?php foreach(DAY_NAMES as $di=>$dn): ?>
<section class="page day">
  <img class="tpl" src="<?= $template ?>" alt="">
  <div class="inner">
    <header class="top"><div class="brand"><div class="logo"><img src="<?= $pdfLogo ?>" alt="<?= e(APP_NAME) ?>"></div><div><b>مَدار</b><small><?= e(APP_OWNER) ?></small></div></div><div class="top-meta"><?= jalali_date($weekStart) ?> تا <?= jalali_date($weekEnd) ?></div></header>
    <div class="day-head"><h1><?= e($dn) ?> <small>· <?= jalali_date(date('Y-m-d', strtotime($weekStart." +$di day"))) ?></small></h1><span class="badge"><?= fa_num(pdf_day_total($grid,$di)) ?> تسک</span></div>
    <div class="unit-grid">
      <?php foreach(UNIT_NAMES as $ui=>$un): $tasks=$grid[$di][$ui] ?? []; ?>
      <section class="unit <?= $ui===8?'special':'' ?>"><div class="unit-title"><b><?= e($un) ?></b><span><?= $tasks?fa_num(count($tasks)).' تسک':'بدون تسک' ?></span></div><?php if(!$tasks): ?><div class="empty">فضای آزاد / استراحت</div><?php else: foreach($tasks as $i=>$task): pdf_render_task($task,$i+1); endforeach; endif; ?></section>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>
</body>
</html>
