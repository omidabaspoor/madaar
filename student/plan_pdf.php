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
$allTasks = [];
$usedSubjects = [];
foreach ($rows->fetchAll() as $t) {
    $grid[(int)$t['day_index']][(int)$t['unit_index']][] = $t;
    $allTasks[] = $t;
    if (!empty($t['subj_name'])) $usedSubjects[(string)$t['subj_name']] = true;
}

$template = asset('img/weekly-plan-template-ai.png');
$pdfLogo = asset('img/logo.png');
$totalTasks = count($allTasks);
$normalTasks = count(array_filter($allTasks, fn($t)=>(int)$t['unit_index'] !== 8));
$specialTasks = $totalTasks - $normalTasks;

function pp_day_total(array $grid, int $day): int { $n=0; foreach (UNIT_NAMES as $ui=>$_) $n += count($grid[$day][$ui] ?? []); return $n; }
function pp_valid_hex(?string $color, string $fallback='#6b8872'): string { $color=trim((string)$color); return preg_match('/^#[0-9a-fA-F]{6}$/',$color)?$color:$fallback; }
function pp_norm(string $s): string { return preg_replace('/\s+/u',' ',str_replace(['ي','ك','‌'],['ی','ک',' '],trim($s))) ?: ''; }
function pp_palette(): array { return ['ریاضی'=>'#6E5B9A','حسابان'=>'#6E5B9A','شیمی'=>'#B58A45','فیزیک'=>'#3F7F9F','زیست'=>'#3B8B5B','زیست‌شناسی'=>'#3B8B5B','هندسه'=>'#4F8C86','گسسته'=>'#8A6A52','ریاضی جامع'=>'#2E5A8C','فارسی'=>'#9A5A8A','ادبیات'=>'#9A5A8A','عربی'=>'#A0754C','دینی'=>'#7A5AA6','زبان انگلیسی'=>'#5578A6','زبان'=>'#5578A6','سلامت'=>'#C06C84','هویت'=>'#6F6F78']; }
function pp_subject_color(?string $name): ?string { $name=pp_norm((string)$name); if($name==='') return null; $p=pp_palette(); if(isset($p[$name])) return $p[$name]; foreach($p as $k=>$c){ if(str_contains($name,pp_norm($k))||str_contains(pp_norm($k),$name)) return $c; } return null; }
function pp_task_color(array $t): string { return pp_subject_color($t['subj_name']??'') ?: pp_valid_hex($t['subj_color']??'', match((string)$t['task_type']){'test'=>'#B58A45','exam'=>'#C9A24A','reading'=>'#D08A45','review'=>'#5D8BA8','textbook'=>'#8E6A9E','descriptive'=>'#C07A55',default=>'#6B8872'}); }
function pp_type_label(string $type): string { return TASK_TYPES[$type]['label'] ?? $type; }
function pp_meta(array $t, bool $source=true): string { $m=[]; if($t['target_count']!==null&&$t['target_count']!=='') $m[]=fa_num($t['target_count']).' '.e($t['target_unit']); if(!empty($t['duration_min'])) $m[]=fa_num($t['duration_min']).' دقیقه'; if($source&&!empty($t['source'])) $m[]=e($t['source']); return implode(' · ',$m); }
function pp_groups(string $field, array $used): array { $field=pp_norm($field); $base=['تخصصی'=>str_contains($field,'ریاضی')?['حسابان','فیزیک','شیمی','هندسه','گسسته']:['زیست‌شناسی','شیمی','فیزیک','ریاضی'],'عمومی'=>['فارسی','عربی','دینی','زبان انگلیسی','سلامت']]; return $used?array_filter($base):['درس‌ها'=>['مطالعه','تست','مرور']]; }
function pp_render_task(?array $t, bool $special=false): void {
    if(!$t){ echo '<div class="empty">آزاد</div>'; return; }
    $c=pp_task_color($t); $meta=pp_meta($t,!$special); $subj=trim((string)($t['subj_name']??''));
    ?>
    <div class="task <?= $special?'special-task':'' ?>" style="--c:<?= e($c) ?>">
      <div class="task-title"><i></i><b><?= e($t['title']) ?></b></div>
      <?php if(!$special): ?><div class="task-tags"><span><?= e(pp_type_label((string)$t['task_type'])) ?></span><?php if($subj): ?><span><?= e($subj) ?></span><?php endif; ?></div><?php endif; ?>
      <?php if($meta): ?><div class="task-meta"><?= $meta ?></div><?php endif; ?>
    </div>
    <?php
}
$subjectGroups = pp_groups((string)($u['field'] ?? ''), array_keys($usedSubjects));
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>برنامه هفتگی · <?= e(APP_NAME) ?></title>
<style>
@font-face{font-family:VazirmatnPDF;src:url('<?= asset('fonts/Vazirmatn.woff2') ?>') format('woff2');font-weight:100 900;font-display:swap}*{box-sizing:border-box}html,body{margin:0;padding:0}body{background:#101c17;color:#17251e;font-family:VazirmatnPDF,Tahoma,Arial,sans-serif;line-height:1.5;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}.screen-actions{position:sticky;top:0;z-index:100;display:flex;gap:10px;align-items:center;justify-content:center;padding:12px;background:rgba(12,21,18,.96);backdrop-filter:blur(14px)}.btn{border:none;border-radius:999px;padding:10px 18px;font:900 14px VazirmatnPDF,Tahoma;background:linear-gradient(135deg,#e8cc93,#b2945f);color:#142018;text-decoration:none;cursor:pointer}.btn.ghost{background:#25352e;color:#eef4ef;border:1px solid #41554a}.hint{color:#cbd8ce;font-size:12px}.page{width:297mm;height:210mm;margin:12px auto;background:#fffaf0;position:relative;overflow:hidden;page-break-after:always;break-after:page;box-shadow:0 24px 70px rgba(0,0,0,.42);border-radius:16px}.page:last-of-type{page-break-after:auto;break-after:auto}.bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0}.veil{position:absolute;inset:0;background:rgba(255,255,255,.78);z-index:1}.content{position:relative;z-index:2;height:100%;padding:12mm}.top{height:22mm;display:flex;align-items:flex-start;justify-content:space-between;color:#fff;margin:-12mm -12mm 8mm;padding:9mm 12mm 0;background:linear-gradient(90deg,rgba(20,36,29,.88),rgba(34,57,47,.72),rgba(178,148,95,.62))}.brand{display:flex;align-items:center;gap:9px}.logo{width:42px;height:42px;border-radius:14px;background:rgba(255,255,255,.15);overflow:hidden;display:grid;place-items:center}.logo img{width:100%;height:100%;object-fit:cover}.brand b{display:block;font-size:22px;font-weight:950}.brand small{display:block;font-size:8.5px;letter-spacing:.12em;opacity:.72;font-weight:900}.top-meta{text-align:left;font-size:10.5px;font-weight:900;opacity:.86}.cover-grid{display:grid;grid-template-columns:1.08fr .92fr;gap:10px}.card{background:rgba(255,255,255,.94);border:1px solid rgba(33,52,42,.12);border-radius:20px;padding:12px 14px;box-shadow:0 10px 24px rgba(23,34,29,.045)}.hero h1{font-size:27px;line-height:1.18;margin:0 0 5px;font-weight:950;color:#172a21}.hero p{margin:0;color:#607066;font-weight:800;font-size:12px}.kv{display:grid;grid-template-columns:repeat(2,1fr);gap:7px;margin-top:10px}.kv div{border:1px solid #e2e9df;border-radius:14px;background:#fbfdf9;padding:8px}.kv span{display:block;color:#68776f;font-size:9px;font-weight:900}.kv b{display:block;font-size:17px;color:#172a21;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.overview{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.daychip{text-align:center;border:1px solid #dfe7df;border-radius:14px;background:linear-gradient(180deg,#fff,#f7faf7);padding:7px;min-height:72px}.daychip b{display:block;font-size:10px}.daychip small{display:block;color:#68776f;font-size:7.2px;line-height:1.35}.daychip strong{display:inline-grid;place-items:center;margin-top:5px;width:26px;height:26px;border-radius:10px;background:linear-gradient(135deg,#203028,#6b8872);color:#fff;font-size:13px}.legend-title{font-size:16px;margin:0 0 8px;font-weight:950}.subject-groups{display:grid;grid-template-columns:1fr 1fr;gap:7px}.subject-group{border:1px solid #dfe7df;border-radius:14px;background:rgba(255,255,255,.78);padding:8px}.subject-group-title{display:block;color:#68776f;font-size:9px;font-weight:900;margin-bottom:5px}.subject-chips{display:flex;flex-wrap:wrap;gap:5px}.subject-chip{display:inline-flex;align-items:center;gap:4px;border:1px solid rgba(32,48,40,.10);background:#fff;border-radius:999px;padding:3px 7px;font-weight:900;font-size:8px}.subject-chip i{width:8px;height:8px;border-radius:50%;background:var(--c)}.note-box{font-size:10.5px;color:#526158;line-height:1.75}.note-box ul{margin:4px 0 0;padding:0 16px}.schedule .content{padding:9mm}.schedule .top{height:18mm;margin:-9mm -9mm 6mm;padding:6mm 9mm 0}.schedule-title h1{margin:0;font-size:20px;color:#fff}.schedule-title p{margin:2px 0 0;font-size:9px;color:rgba(255,255,255,.78);font-weight:800}.week-grid{display:grid;grid-template-columns:18mm repeat(8,1fr);grid-template-rows:9mm repeat(7,21.2mm);height:158.4mm;border:1px solid #dbe5dc;border-radius:16px;overflow:hidden;background:rgba(255,255,255,.92)}.hcell{background:linear-gradient(135deg,#1f332a,#6b8872);color:#fff;font-size:7.7px;font-weight:950;display:grid;place-items:center;text-align:center;border-left:1px solid rgba(255,255,255,.16);padding:2px}.hcell.dayh{background:#172a21}.hcell.specialh{background:linear-gradient(135deg,#8a6a3c,#c7a46a)}.dcell{background:#f1f6f1;display:grid;place-items:center;text-align:center;border-top:1px solid #e3ebe2;border-left:1px solid #e3ebe2;padding:3px}.dcell b{font-size:9.2px}.dcell small{font-size:6.3px;color:#65756b;line-height:1.25}.cell{border-top:1px solid #e3ebe2;border-left:1px solid #e3ebe2;background:rgba(255,255,255,.76);padding:2.3px;overflow:hidden;min-width:0}.cell.special{background:linear-gradient(135deg,rgba(224,197,149,.16),rgba(255,255,255,.82))}.task{height:100%;min-height:0;border:1px solid rgba(32,48,40,.10);border-right:3px solid var(--c);background:linear-gradient(90deg,color-mix(in srgb,var(--c) 10%,#fff),#fff 78%);border-radius:8px;padding:3px 4px;overflow:hidden}.task-title{display:flex;align-items:flex-start;gap:3px;min-width:0}.task-title i{width:5px;height:5px;border-radius:50%;background:var(--c);margin-top:4px;flex-shrink:0}.task-title b{font-size:7.35px;line-height:1.33;font-weight:950;color:#14211b;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.task-tags{display:flex;gap:2px;flex-wrap:wrap;margin-top:2px;max-height:12px;overflow:hidden}.task-tags span{background:#eef4ef;border-radius:999px;padding:0 4px;font-size:5.8px;font-weight:900;color:#516058}.task-meta{font-size:6.25px;color:#627169;font-weight:900;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.special-task{height:6.2mm!important;border-radius:6px;padding:1px 3px;margin-bottom:1px}.special-task .task-title b{font-size:6.35px;line-height:1.1;white-space:nowrap;display:block;text-overflow:ellipsis}.special-task .task-title i{width:4px;height:4px;margin-top:2px}.special-task .task-meta{font-size:5.5px;line-height:1;white-space:nowrap}.empty{height:100%;border:1px dashed #d7e0d7;border-radius:8px;display:grid;place-items:center;color:#a2ada6;background:#f9fbf8;font-size:8px;font-weight:900}.watermark{position:absolute;left:10mm;bottom:7mm;color:rgba(23,42,33,.10);font-size:9px;font-weight:900;z-index:2}
@media print{*{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}html,body{width:297mm;background:#fff}.screen-actions{display:none}.page{width:297mm!important;height:210mm!important;margin:0!important;box-shadow:none!important;border-radius:0!important;overflow:hidden!important}.page+.page{page-break-before:always!important;break-before:page!important}.content{padding:12mm!important}.schedule .content{padding:8mm!important}.top{margin:-12mm -12mm 8mm!important}.schedule .top{margin:-8mm -8mm 5mm!important}.week-grid{height:160mm!important;grid-template-rows:9mm repeat(7,21.57mm)!important}.bg{display:block!important}@page{size:A4 landscape;margin:0}}
@media(max-width:1000px){.screen-actions{justify-content:flex-start;overflow:auto}.page{margin:10px;width:297mm;height:210mm}}
</style>
<script>function printPlan(){const imgs=[...document.images].map(img=>img.complete?Promise.resolve():new Promise(r=>{img.onload=img.onerror=r;}));const fonts=document.fonts&&document.fonts.ready?document.fonts.ready.catch(()=>{}):Promise.resolve();Promise.all([...imgs,fonts]).then(()=>setTimeout(()=>window.print(),160));}</script>
</head>
<body>
<div class="screen-actions"><button class="btn" onclick="printPlan()">چاپ / ذخیره PDF</button><a class="btn ghost" href="<?= url('student/plan.php?week='.$weekStart) ?>">بازگشت</a><span class="hint">خروجی دقیقاً دو صفحه افقی A4 است.</span></div>

<section class="page cover">
  <img class="bg" src="<?= $template ?>" alt=""><div class="veil"></div>
  <div class="content">
    <header class="top"><div class="brand"><div class="logo"><img src="<?= $pdfLogo ?>" alt=""></div><div><b>مَدار</b><small>WEEKLY STUDY PLAN</small></div></div><div class="top-meta">صفحه ۱ از ۲<br><?= jalali_date('now', true) ?></div></header>
    <div class="cover-grid">
      <div class="card hero"><h1>برنامه اختصاصی هفته</h1><p><?= jalali_date($weekStart) ?> تا <?= jalali_date($weekEnd) ?> · طراحی‌شده برای اجرای دقیق روزانه</p><div class="kv"><div><span>کل تسک‌ها</span><b><?= fa_num($totalTasks) ?></b></div><div><span>واحدهای عادی</span><b><?= fa_num($normalTasks) ?></b></div><div><span>واحد ویژه</span><b><?= fa_num($specialTasks) ?></b></div><div><span>روزهای برنامه</span><b><?= fa_num(7) ?></b></div></div></div>
      <div class="card"><h2 class="legend-title">مشخصات</h2><div class="kv" style="grid-template-columns:1fr"><div><span>دانش‌آموز</span><b><?= e($u['full_name']) ?></b></div><div><span>رشته / پایه</span><b><?= e($u['field'] ?: '—') ?><?= $u['grade']?' · '.e($u['grade']):'' ?></b></div><div><span>عنوان برنامه</span><b><?= e($plan['title'] ?: 'برنامه هفتگی') ?></b></div></div></div>
    </div>
    <div class="card" style="margin-top:10px"><h2 class="legend-title">نمای کلی روزها</h2><div class="overview"><?php foreach(DAY_NAMES as $di=>$dn): ?><div class="daychip"><b><?= e($dn) ?></b><small><?= jalali_date(date('Y-m-d', strtotime($weekStart." +$di day"))) ?></small><strong><?= fa_num(pp_day_total($grid,$di)) ?></strong><small>تسک</small></div><?php endforeach; ?></div></div>
    <div class="cover-grid" style="grid-template-columns:1fr 1fr;margin-top:10px">
      <div class="card"><h2 class="legend-title">راهنمای رنگ درس‌ها</h2><div class="subject-groups"><?php foreach($subjectGroups as $gt=>$subjects): ?><div class="subject-group"><span class="subject-group-title"><?= e($gt) ?></span><div class="subject-chips"><?php foreach($subjects as $name): $c=pp_subject_color($name) ?: '#6b8872'; ?><span class="subject-chip" style="--c:<?= e($c) ?>"><i></i><?= e($name) ?></span><?php endforeach; ?></div></div><?php endforeach; ?></div></div>
      <div class="card note-box"><h2 class="legend-title">راهنمای اجرا</h2><ul><li>صفحه دوم، جدول کامل برنامه هفته است.</li><li>هر خانه یک واحد مطالعاتی را نشان می‌دهد.</li><li>واحد ویژه برای روزخوانی، مرور و آزمونک‌های کوتاه است.</li><li>برای خروجی بهتر، گزینه چاپ را روی Landscape قرار دهید.</li></ul></div>
    </div>
  </div><span class="watermark">Madar Study OS · <?= e(APP_DOMAIN) ?></span>
</section>

<section class="page schedule">
  <img class="bg" src="<?= $template ?>" alt=""><div class="veil"></div>
  <div class="content">
    <header class="top"><div class="brand"><div class="logo"><img src="<?= $pdfLogo ?>" alt=""></div><div><b>مَدار</b><small><?= e(APP_OWNER) ?></small></div></div><div class="schedule-title"><h1>جدول کامل برنامه هفته</h1><p><?= e($u['full_name']) ?> · <?= jalali_date($weekStart) ?> تا <?= jalali_date($weekEnd) ?></p></div><div class="top-meta">صفحه ۲ از ۲<br>A4 Landscape</div></header>
    <div class="week-grid">
      <div class="hcell dayh">روز</div><?php foreach(UNIT_NAMES as $ui=>$un): ?><div class="hcell <?= $ui===8?'specialh':'' ?>"><?= e($un) ?></div><?php endforeach; ?>
      <?php foreach(DAY_NAMES as $di=>$dn): ?>
        <div class="dcell"><div><b><?= e($dn) ?></b><small><?= jalali_date(date('Y-m-d', strtotime($weekStart." +$di day"))) ?></small></div></div>
        <?php foreach(UNIT_NAMES as $ui=>$un): $tasks=$grid[$di][$ui] ?? []; ?>
          <div class="cell <?= $ui===8?'special':'' ?>"><?php if(!$tasks) echo '<div class="empty">آزاد</div>'; elseif($ui===8) foreach(array_slice($tasks,0,3) as $task) pp_render_task($task,true); else pp_render_task($tasks[0],false); ?></div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div><span class="watermark">Madar Study OS · Weekly Plan</span>
</section>
</body>
</html>
