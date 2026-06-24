<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$examId = (int)($_GET['id'] ?? 0);
$exam = $examId ? get_exam($examId) : null;
if ($exam && $exam['advisor_id'] != $u['id'] && $u['role']!=='admin') { flash('error','آزمون یافت نشد'); redirect('admin/exams.php'); }

$sections = $exam ? exam_sections($examId) : [];
$questions = $exam ? exam_questions($examId) : [];
$subjects = all_subjects();
$targetFields = $exam && !empty($exam['target_fields_json']) ? (json_decode((string)$exam['target_fields_json'], true) ?: []) : [];
$targetGrades = $exam && !empty($exam['target_grades_json']) ? (json_decode((string)$exam['target_grades_json'], true) ?: []) : [];
$targetFields = is_array($targetFields) ? $targetFields : [];
$targetGrades = is_array($targetGrades) ? $targetGrades : [];

$qBySection = [];
foreach ($questions as $q) $qBySection[(int)$q['section_id']][] = $q;

// استخراج صفحات چندتایی آپلودشده
$sheetArr = [];
if ($exam) {
    $sheetArr = !empty($exam['sheet_paths_json']) ? (json_decode((string)$exam['sheet_paths_json'], true) ?: []) : [];
    if (($exam['sheet_path'] ?? null) && !in_array($exam['sheet_path'], $sheetArr, true)) {
        array_unshift($sheetArr, $exam['sheet_path']);
    }
}

$mode = $_GET['mode'] ?? ($exam['creation_mode'] ?? 'quick_sheet');
$mode = in_array($mode, ['quick_sheet','standard','ai_bulk'], true) ? $mode : 'quick_sheet';
if ($mode === 'ai_bulk') $mode = 'quick_sheet';

// مرحله‌ی شروع: بعد از آپلود دفترچه یا ساخت سوالات، کاربر باید در استودیو بماند نه مرحله اول.
$requestedStep = (int)($_GET['step'] ?? 0);
$startStep = ($requestedStep === 2 || ($exam && (count($questions) > 0 || count($sheetArr) > 0))) ? 2 : 1;

panel_start($exam ? 'ویرایش آزمون' : 'طراحی آزمون جدید', '', 'admin', 'exams', ['builder.css','student.css']);
?>
<div class="exam-builder" id="examBuilder" data-exam="<?= $examId ?>" data-step="<?= $startStep ?>" data-mode="<?= e($mode) ?>">

  <!-- ===== Header ===== -->
  <div class="between wrap gap-3 mb-4" style="align-items:center">
    <a href="<?= url('admin/exams.php') ?>" class="btn btn-ghost btn-sm flex gap-2" style="align-items:center"><?= icon('arrow-right',16) ?> بازگشت به آزمون‌ها</a>
    <div class="stepper" style="display:flex;align-items:center;gap:12px;background:var(--surface-2);padding:4px 16px;border-radius:var(--r-pill);border:1px solid var(--border-soft)">
      <button class="step <?= $startStep===1?'active':'' ?>" data-step-to="1" style="border:none;background:none;color:inherit;cursor:pointer;font-weight:800"><span class="step-n" style="background:var(--gold);color:#000;padding:2px 8px;border-radius:50%;margin-left:6px">۱</span><span class="step-lbl">تنظیمات و شیوه طراحی</span></button>
      <span class="step-line" style="width:24px;height:2px;background:var(--border-soft)"></span>
      <button class="step <?= $startStep===2?'active':'' ?>" data-step-to="2" style="border:none;background:none;color:inherit;cursor:pointer;font-weight:800"><span class="step-n" style="background:var(--sage);color:#000;padding:2px 8px;border-radius:50%;margin-left:6px">۲</span><span class="step-lbl">ورود سوالات و پاسخنامه</span></button>
    </div>
    <span class="save-status saved badge badge-sage" id="saveStatus" style="padding:6px 14px"><?= icon('check-circle',15) ?> آماده‌ی کار</span>
  </div>

  <!-- =========================================================
       STEP 1 — Settings & Mode Selector
       ========================================================= -->
  <div class="builder-step <?= $startStep===1?'':'hidden' ?>" data-step="1">
    <div class="panel" style="background:var(--surface-1);border:1px solid var(--border-soft);padding:32px;border-radius:var(--r-lg)">
      <div class="step-intro flex gap-3 mb-4" style="align-items:center;border-bottom:1px solid var(--surface-2);padding-bottom:20px">
        <span class="icon-tile" style="background:var(--gold-glass);color:var(--gold);width:56px;height:56px;font-size:1.6rem"><?= icon('clipboard',28) ?></span>
        <div><h3 style="font-size:1.3rem;font-weight:900;color:var(--text-1)">گام ۱ · تنظیمات و انتخاب شیوه طراحی</h3><p class="muted mt-1" style="font-size:.9rem">عنوان آزمون و روش ساخت سوالات را انتخاب کنید؛ بقیه‌ی موارد حالت پیش‌فرض دارند.</p></div>
      </div>

      <form id="metaForm">
        <div class="field mb-4">
          <label style="font-size:1.05rem;font-weight:800;color:var(--gold-light)">عنوان آزمون *</label>
          <input class="input input-lg" name="title" id="m_title" value="<?= e($exam['title'] ?? '') ?>" placeholder="مثلاً آزمون جامع شماره ۱ (شیمی و فیزیک)" style="font-size:1.1rem;font-weight:800">
        </div>

        <div class="field mb-4">
          <label style="font-size:1.05rem;font-weight:800;color:var(--text-1);margin-bottom:12px;display:block">شیوه طراحی و ورود سوالات *</label>
          <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit, minmax(280px, 1fr))">
            
            <label class="panel mode-card <?= $mode==='quick_sheet'?'active':'' ?>" style="cursor:pointer;border:2px solid <?= $mode==='quick_sheet'?'var(--gold)':'var(--border-soft)' ?>;background:<?= $mode==='quick_sheet'?'var(--surface-2)':'var(--surface-1)' ?>;transition:all 0.2s">
              <input type="radio" name="creation_mode" value="quick_sheet" <?= $mode==='quick_sheet'?'checked':'' ?> hidden>
              <div class="flex gap-3" style="align-items:center">
                <span class="icon-tile" style="background:var(--gold-glass);color:var(--gold);width:48px;height:48px"><?= icon('image',24) ?></span>
                <div>
                  <h4 style="font-size:1.1rem;font-weight:900;color:var(--gold-light)">آزمون تصویرمحور سریع</h4>
                  <p class="muted mt-1" style="font-size:.82rem;line-height:1.5">آپلود عکس دفترچه سوالات کنکور + ورود سریع کلیدها (بهترین روش برای آزمون‌های ۱۰۰ سوالی)</p>
                </div>
              </div>
            </label>

            <label class="panel mode-card <?= $mode==='standard'?'active':'' ?>" style="cursor:pointer;border:2px solid <?= $mode==='standard'?'var(--cyan)':'var(--border-soft)' ?>;background:<?= $mode==='standard'?'var(--surface-2)':'var(--surface-1)' ?>;transition:all 0.2s">
              <input type="radio" name="creation_mode" value="standard" <?= $mode==='standard'?'checked':'' ?> hidden>
              <div class="flex gap-3" style="align-items:center">
                <span class="icon-tile" style="background:var(--cyan-glass);color:var(--cyan);width:48px;height:48px"><?= icon('edit',24) ?></span>
                <div>
                  <h4 style="font-size:1.1rem;font-weight:900;color:#a0d2eb">طراحی تکی استاندارد</h4>
                  <p class="muted mt-1" style="font-size:.82rem;line-height:1.5">ساخت بخش‌های مجزا و تایپ دستی سوالات و گزینه‌ها همراه با آپلود عکس مجزا</p>
                </div>
              </div>
            </label>

          </div>
        </div>

        <div class="grid gap-3 mb-4" style="grid-template-columns:1fr 1fr">
          <div class="field"><label>نوع ساختار آزمون</label>
            <div class="flex gap-3 mt-1">
              <label class="badge <?= ($exam['exam_type']??'single')==='single'?'badge-gold':'badge' ?>" style="padding:10px 20px;cursor:pointer;font-weight:800"><input type="radio" name="exam_type" value="single" <?= ($exam['exam_type']??'single')==='single'?'checked':'' ?>> تکی (یک درس)</label>
              <label class="badge <?= ($exam['exam_type']??'')==='comprehensive'?'badge-gold':'badge' ?>" style="padding:10px 20px;cursor:pointer;font-weight:800"><input type="radio" name="exam_type" value="comprehensive" <?= ($exam['exam_type']??'')==='comprehensive'?'checked':'' ?>> جامع (چند درس)</label>
            </div>
          </div>
          <div class="field"><label>توضیحات کوتاه <span class="muted">(اختیاری)</span></label><input class="input" name="description" id="m_desc" value="<?= e($exam['description'] ?? '') ?>" placeholder="مثلاً ویژه‌ی جمع‌بندی نیمسال اول"></div>
        </div>

        <div class="panel exam-target-box mb-4" style="background:linear-gradient(135deg,rgba(203,172,128,.10),rgba(107,136,114,.07));border:1px solid rgba(203,172,128,.25);border-radius:var(--r-lg);padding:16px 18px">
          <div class="between wrap gap-2 mb-3" style="align-items:center">
            <div>
              <b style="color:var(--gold-light);font-size:1rem"><?= icon('users',18) ?> انتشار برای چه دانش‌آموزانی؟</b>
              <div class="muted" style="font-size:.8rem;margin-top:3px">اگر هیچ گزینه‌ای انتخاب نشود، آزمون برای همه‌ی دانش‌آموزان مجاز منتشر می‌شود. برای مثال: «ریاضی + دوازدهم + کنکوری».</div>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" id="targetAllBtn" style="font-weight:900">انتشار برای همه</button>
          </div>
          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="field" style="margin:0">
              <label style="font-weight:900;color:var(--text-2);margin-bottom:8px;display:block">رشته‌ها</label>
              <div class="target-chip-wrap">
                <?php foreach(['تجربی','ریاضی','انسانی','هنر','زبان'] as $f): ?>
                  <label class="target-chip <?= in_array($f,$targetFields,true)?'active':'' ?>"><input type="checkbox" name="target_fields[]" value="<?= e($f) ?>" <?= in_array($f,$targetFields,true)?'checked':'' ?>><?= e($f) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="field" style="margin:0">
              <label style="font-weight:900;color:var(--text-2);margin-bottom:8px;display:block">پایه‌ها</label>
              <div class="target-chip-wrap">
                <?php foreach(['دهم','یازدهم','دوازدهم','کنکوری'] as $g): ?>
                  <label class="target-chip <?= in_array($g,$targetGrades,true)?'active':'' ?>"><input type="checkbox" name="target_grades[]" value="<?= e($g) ?>" <?= in_array($g,$targetGrades,true)?'checked':'' ?>><?= e($g) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="target-summary mt-3" id="targetSummary"></div>
        </div>

        <div class="grid gap-3 mb-4" style="grid-template-columns:1fr 1fr">
          <div class="field"><label>نحوه‌ی زمان‌بندی</label>
            <select class="select" name="timing_mode" id="m_timing">
              <option value="total" <?= ($exam['timing_mode']??'')==='total'?'selected':'' ?>>یک زمان مشترک برای کل آزمون</option>
              <option value="per_section" <?= ($exam['timing_mode']??'')==='per_section'?'selected':'' ?>>زمان‌بندی مجزا برای هر درس/بخش</option>
            </select>
          </div>
          <div class="field" id="totalDurField"><label>زمان کل آزمون (دقیقه)</label><input class="input" type="number" min="1" name="duration_min" id="m_dur" value="<?= e($exam['duration_min'] ?? 60) ?>"></div>
        </div>

        <details class="adv-settings panel" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:16px 20px;border-radius:var(--r-lg)">
          <summary style="font-weight:800;cursor:pointer;color:var(--gold-light)"><?= icon('settings',16) ?> تنظیمات پیشرفته برگزاری (اختیاری)</summary>
          <div class="adv-body grid gap-3 mt-4" style="grid-template-columns:1fr 1fr wrap">
            <div class="field"><label>بازه‌ی مجاز شروع <span class="muted">(اختیاری)</span></label>
              <input class="input" type="datetime-local" name="start_at" id="m_start" value="<?= $exam && $exam['start_at'] ? date('Y-m-d\TH:i', strtotime($exam['start_at'])) : '' ?>">
            </div>
            <label class="toggle-row between" style="align-items:center;background:var(--surface-1);padding:12px;border-radius:8px"><span><b>نمره منفی کنکور</b><br><span class="muted" style="font-size:.78rem">هر ۳ غلط = ۱ درست خنثی</span></span>
              <label class="switch"><input type="checkbox" name="negative_marking" id="m_neg" value="1" <?= ($exam['negative_marking']??1)?'checked':'' ?>><span class="slider"></span></label>
            </label>
            <label class="toggle-row between" style="align-items:center;background:var(--surface-1);padding:12px;border-radius:8px"><span><b>نمایش پاسخنامه و تحلیل</b><br><span class="muted" style="font-size:.78rem">پس از پایان آزمون فعال شود</span></span>
              <label class="switch"><input type="checkbox" name="show_review" id="m_rev" value="1" <?= ($exam['show_review']??1)?'checked':'' ?>><span class="slider"></span></label>
            </label>
          </div>
        </details>
      </form>

      <div class="step-actions mt-4 text-l">
        <button class="btn btn-gold btn-lg flex gap-2" id="toStep2Btn" style="padding:14px 36px;font-weight:900;font-size:1.1rem;display:inline-flex;align-items:center">
          ثبت تنظیمات و ورود به استودیوی طراحی <?= icon('arrow-left',20) ?>
        </button>
      </div>
    </div>
  </div>

  <!-- =========================================================
       STEP 2 — Studio Suites
       ========================================================= -->
  <div class="builder-step <?= $startStep===2?'':'hidden' ?>" data-step="2">
    
    <div class="step-intro panel flex gap-3 between mb-4 wrap" style="align-items:center;padding:16px 24px;background:var(--surface-2);border-radius:var(--r-lg)">
      <div class="flex gap-3" style="align-items:center">
        <span class="icon-tile sage"><?= icon('list',24) ?></span>
        <div>
          <h3 style="font-size:1.15rem;font-weight:900;color:var(--text-1)">گام ۲ · استودیوی ورود سوالات و پاسخنامه</h3>
          <p class="muted mt-1" style="font-size:.85rem">حالت فعال: <b style="color:var(--gold-light)"><?= e(['quick_sheet'=>'آزمون تصویرمحور سریع','standard'=>'طراحی تکی استاندارد'][$mode] ?? 'سریع') ?></b></p>
        </div>
      </div>
      
      <div class="flex gap-2 wrap">
        <button class="btn btn-ghost btn-sm flex gap-1" id="backToStep1" style="align-items:center"><?= icon('settings',16) ?> تغییر تنظیمات</button>
        <button class="btn btn-sage btn-sm flex gap-1" id="studioPublishBtn" data-status="<?= e($exam['status'] ?? 'draft') ?>" style="align-items:center;font-weight:900">
          <?= ($exam['status']??'')==='published' ? icon('edit',16).' بازگشت به پیش‌نویس' : icon('rocket',16).' انتشار نهایی آزمون' ?>
        </button>
      </div>
    </div>

    <!-- 1. استودیوی آزمون تصویرمحور سریع (Multi-Image Studio) -->
    <div class="studio-suite <?= $mode==='quick_sheet'?'':'hidden' ?>" id="suiteQuickSheet">
      <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit, minmax(min(100%, 420px), 1fr))">
        
        <!-- ستون آپلود چندین صفحه دفترچه -->
        <div class="panel flex" style="flex-direction:column;justify-content:space-between;background:var(--surface-1);padding:24px;border-radius:var(--r-lg);min-height:100%;word-break:break-word">
          <div>
            <div class="between mb-3 wrap gap-2" style="align-items:center">
              <h4 style="font-size:1.15rem;font-weight:900;color:var(--gold);display:flex;align-items:center;gap:8px">
                <?= icon('image',20) ?> ۱. آپلود دفترچه سوالات
              </h4>
              <span class="badge badge-sage">عکس یا PDF</span>
            </div>
            <p class="muted mb-4" style="font-size:.88rem;line-height:1.7">می‌توانید عکس‌های صفحات یا یک فایل PDF دفترچه را آپلود کنید؛ PDF داخل همان باکس آزمون نمایش داده می‌شود.</p>
            
            <label class="upload-zone text-c" style="display:flex;flex-direction:column;align-items:center;justify-content:center;border:2px dashed var(--gold);padding:36px;border-radius:var(--r-lg);background:var(--gold-glass);cursor:pointer;transition:all 0.3s">
              <span style="font-size:2.5rem;color:var(--gold);margin-bottom:12px"><?= icon('paperclip',40) ?></span>
              <b style="color:var(--text-1);font-size:1.05rem">برای آپلود عکس یا PDF دفترچه کلیک کنید</b>
              <span class="muted mt-1" style="font-size:.8rem">JPG, PNG, WEBP, GIF یا PDF تا ۲۰۰MB</span>
              <input type="file" id="examSheetInput" accept="image/*,application/pdf,.pdf" multiple hidden>
            </label>

            <!-- گرید صفحات آپلودشده -->
            <div id="uploadedSheetsThumbsGrid" class="grid gap-3 mt-4" style="grid-template-columns:repeat(auto-fill, minmax(120px, 1fr))">
              <?php foreach($sheetArr as $si => $sPath): $stype = sheet_asset_type($sPath); $fullPath = __DIR__ . '/../' . $sPath; ?>
                <div class="sheet-thumb-item relative panel <?= $stype==='pdf'?'pdf':'' ?>" data-spath="<?= e($sPath) ?>" data-type="<?= e($stype) ?>">
                  <span class="sheet-page-badge badge badge-gold">ص<?= fa_num($si+1) ?></span>
                  <button type="button" class="btn btn-ghost btn-sm remove-sheet-item-btn" title="حذف این فایل">×</button>
                  <?php if($stype === 'pdf'): ?>
                    <div class="sheet-pdf-thumb">
                      <span class="pdf-ico">PDF</span>
                      <b>دفترچه PDF</b>
                      <small><?= is_file($fullPath) ? human_file_size(filesize($fullPath)) : '' ?></small>
                    </div>
                  <?php else: ?>
                    <img src="<?= url($sPath) ?>" alt="صفحه دفترچه">
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- ستون پاسخنامه کلیدی هوشمند -->
        <div class="panel flex" style="flex-direction:column;justify-content:space-between;background:var(--surface-1);padding:24px;border-radius:var(--r-lg);min-height:100%;word-break:break-word">
          <div>
            <h4 style="font-size:1.15rem;font-weight:900;color:var(--sage);margin-bottom:16px;display:flex;align-items:center;gap:8px">
              <?= icon('check-circle',20) ?> ۲. ورود سریع کلید پاسخنامه
            </h4>
            <p class="muted mb-3" style="font-size:.88rem;line-height:1.7">کلیدها را یک‌جا پیست کنید یا با کارت‌های کنترلی زیر، پاسخ هر سوال را دقیق مدیریت کنید؛ امکان افزودن بین دو سوال، حذف، جابه‌جایی و تغییر شماره واقعی سوال وجود دارد.</p>
            <div class="qkey-help-panel">
              <div class="qkey-help-item"><b>افزودن بین سوال‌ها</b>روی «+قبل» یا «+بعد» هر کارت بزنید؛ شماره‌های بعدی خودکار جلو می‌روند.</div>
              <div class="qkey-help-item"><b>شماره واقعی سوال</b>عدد داخل هر کارت را تغییر دهید؛ برای دفترچه‌هایی که از ۱۰۱ یا ۱۵۱ شروع می‌شوند عالی است.</div>
              <div class="qkey-help-item"><b>کنترل خطا</b>شماره‌های تکراری قرمز می‌شوند و سوال‌های بدون پاسخ موقع ثبت هشدار می‌دهند.</div>
            </div>

            <div class="field mb-4">
              <div class="between mb-1"><label style="font-size:.9rem;color:var(--text-2)">رشته‌ی پاسخنامه (اعداد ۱ تا ۴):</label><span id="keyQCountBadge" class="badge badge-gold">۰ سوال</span></div>
              <input class="input" id="quickKeyInput" value="<?= e($exam['answer_key'] ?? '') ?>" placeholder="مثلاً 4123421142341..." style="font-family:monospace;direction:ltr;font-size:1.1rem;letter-spacing:2px;font-weight:bold">
            </div>

            <!-- نوار کنترل حرفه‌ای شماره سوالات -->
            <div class="advanced-numbering-controls mb-4 p-4" style="background: var(--surface-2); border: 1px solid var(--gold); border-radius: 16px;">
              <b style="color: var(--gold-light); font-size: 14px; display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <?= icon('settings', 18) ?> کنترل حرفه‌ای شماره سوالات (Custom Question Numbering)
              </b>
              <div class="grid cols-1 sm:cols-2 gap-3 items-end" style="grid-template-columns: 1fr 1fr;">
                <div>
                  <label style="font-size: 13px; color: var(--text-2); display: block; margin-bottom: 6px;">شروع شماره سوالات از عدد:</label>
                  <div class="input-group flex gap-2">
                    <input type="number" id="startQNumInput" value="1" min="1" class="input font-mono dir-ltr text-center" style="height: 42px;">
                    <button type="button" onclick="applyStartNumbering()" class="btn btn-gold btn-sm px-4 whitespace-nowrap font-bold" style="height: 42px;">✓ اعمال شماره‌گذاری</button>
                  </div>
                </div>
                <div>
                  <label style="font-size: 13px; color: var(--text-2); display: block; margin-bottom: 6px;">افزودن سوال با شماره دلخواه:</label>
                  <div class="input-group flex gap-2">
                    <input type="number" id="specificQNumInput" placeholder="مثلاً 155" min="1" class="input font-mono dir-ltr text-center" style="height: 42px;">
                    <button type="button" onclick="addSpecificCustomBubble()" class="btn btn-sage btn-sm px-4 whitespace-nowrap font-bold" style="height: 42px;">+ افزودن حباب</button>
                  </div>
                </div>
              </div>
            </div>

            <!-- پاسخ‌برگ حبابی تعاملی مشاور -->
            <div class="bubble-grid-studio" id="bubbleGridStudio" style="max-height:380px;overflow-y:auto;background:var(--surface-2);border:1px solid var(--border-soft);padding:16px;border-radius:var(--r-lg);display:grid;grid-template-columns:repeat(auto-fill, minmax(130px, 1fr));gap:12px;direction:ltr">
              <?php 
                $existingKey = trim((string)($exam['answer_key'] ?? ''));
                $currCount = max(30, mb_strlen($existingKey));
                $useQuestions = !empty($questions);
                if ($useQuestions):
                  foreach ($questions as $qi => $q):
                    $realNum = $q['question_number'] !== null ? (int)$q['question_number'] : ($qi + 1);
                    $cVal    = (int)$q['correct_opt'];
              ?>
                    <div class="bg-item <?= !empty($q['is_cancelled'])?'qkey-cancelled':'' ?>" data-question="<?= (int)$q['id'] ?>" data-qnum="<?= $qi + 1 ?>" data-realnum="<?= $realNum ?>" data-cancelled="<?= !empty($q['is_cancelled'])?'1':'0' ?>" style="background:var(--surface-1);padding:10px;border-radius:12px;display:flex;flex-direction:column;align-items:center;border:1px solid var(--border-soft)">
                      <div class="flex items-center gap-1 mb-2 w-full justify-center">
                        <span style="font-size:.7۵rem;color:var(--text-3);font-weight:bold;">Q</span>
                        <input type="number" class="input bubble-qnum-input font-mono font-bold text-center text-xs p-1 h-7 w-16" value="<?= $realNum ?>" title="تنظیم دستی شماره این سوال" onchange="updateBubbleRealNum(this)">
                      </div>
                      <div class="flex gap-1">
                        <?php for($oi=1; $oi<=4; $oi++): ?>
                          <button type="button" class="bubble-btn <?= $cVal===$oi?'active':'' ?>" data-opt="<?= $oi ?>" style="width:22px;height:22px;border-radius:50%;border:1px solid var(--border-soft);background:<?= $cVal===$oi?'var(--gold)':'var(--surface-2)' ?>;color:<?= $cVal===$oi?'#000':'var(--text-2)' ?>;font-size:.7rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center">
                            <?= $oi ?>
                          </button>
                        <?php endfor; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else:
                  for($qi=1; $qi<=$currCount; $qi++): 
                    $cVal = isset($existingKey[$qi-1]) ? (int)$existingKey[$qi-1] : 0;
                    $realNum = $qi;
                ?>
                    <div class="bg-item" data-qnum="<?= $qi ?>" data-realnum="<?= $realNum ?>" data-cancelled="0" style="background:var(--surface-1);padding:10px;border-radius:12px;display:flex;flex-direction:column;align-items:center;border:1px solid var(--border-soft)">
                      <div class="flex items-center gap-1 mb-2 w-full justify-center">
                        <span style="font-size:.7۵rem;color:var(--text-3);font-weight:bold;">Q</span>
                        <input type="number" class="input bubble-qnum-input font-mono font-bold text-center text-xs p-1 h-7 w-16" value="<?= $realNum ?>" title="تنظیم دستی شماره این سوال" onchange="updateBubbleRealNum(this)">
                      </div>
                      <div class="flex gap-1">
                        <?php for($oi=1; $oi<=4; $oi++): ?>
                          <button type="button" class="bubble-btn <?= $cVal===$oi?'active':'' ?>" data-opt="<?= $oi ?>" style="width:22px;height:22px;border-radius:50%;border:1px solid var(--border-soft);background:<?= $cVal===$oi?'var(--gold)':'var(--surface-2)' ?>;color:<?= $cVal===$oi?'#000':'var(--text-2)' ?>;font-size:.7rem;font-weight:bold;cursor:pointer;display:flex;align-items:center;justify-content:center">
                            <?= $oi ?>
                          </button>
                        <?php endfor; ?>
                      </div>
                    </div>
                  <?php endfor; ?>
                <?php endif; ?>
            </div>
          </div>

          <div class="mt-4 flex gap-3">
            <button type="button" id="add10BubblesBtn" class="btn btn-ghost btn-sm" style="flex:1"><?= icon('plus',14) ?> افزودن ۱۰ سوال به انتها</button>
            <button type="button" id="saveQuickSheetSuiteBtn" class="btn btn-gold btn-lg" style="flex:2;font-weight:900"><?= icon('check',18) ?> ثبت نهایی و ساخت سوالات</button>
          </div>
        </div>

      </div>
    </div>

    <!-- 2. استودیوی طراحی استاندارد تکی (Standard Studio) -->
    <div class="studio-suite <?= $mode==='standard'?'':'hidden' ?>" id="suiteStandard">
      <div class="between wrap gap-3 mb-4">
        <div class="exam-summary">
          <span class="ps-item"><span class="v" id="totalQ"><?= fa_num(count(array_filter($questions, fn($q)=>empty($q['is_cancelled'])))) ?></span><span class="k">سوال فعال</span></span>
          <span class="ps-item"><span class="v" id="totalSec"><?= fa_num(count($sections)) ?></span><span class="k">درس/بخش</span></span>
        </div>
        <button class="btn btn-ghost flex gap-1" id="addSectionBtn" style="align-items:center"><?= icon('plus',16) ?> افزودن بخش جدید</button>
      </div>

      <div id="sectionsWrap">
        <?php foreach ($sections as $sec): ?>
          <?php require __DIR__ . '/_section_tpl.php'; ?>
        <?php endforeach; ?>
      </div>

      <?php if (!$sections): ?>
      <div class="panel" id="emptySections"><div class="empty-state"><div class="es-ico"><?= icon('book',30) ?></div><p>هنوز بخشی اضافه نکرده‌اید</p><p class="muted" style="font-size:.84rem">با دکمه‌ی «افزودن بخش جدید» شروع کنید.</p>
        <button class="btn btn-gold mt-4 flex gap-1" id="addSectionBtn2" style="align-items:center"><?= icon('plus',16) ?> افزودن اولین بخش</button></div></div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
  window.API_EXAM  = '<?= url('api/exam_builder.php') ?>';
  window.SUBJECTS  = <?= json_encode(array_map(fn($s)=>['id'=>(int)$s['id'],'name'=>$s['name']], $subjects), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php panel_end(['exam_builder.js']); ?>
