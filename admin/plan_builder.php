<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$studentId = (int)($_GET['student'] ?? 0);
$student = get_user($studentId);
if (!$student || $student['role'] !== 'student') { flash('error','دانش‌آموز یافت نشد'); redirect('admin/students.php'); }

$plannerCfg = planner_config_js((int)$u['id']);

// هفته (شنبه)
$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
task_status_schema_ready();
auto_mark_missed_tasks($studentId);
$plan = find_or_create_plan($studentId, (int)$u['id'], $weekStart);
ensure_special_defaults_for_empty_plan((int)$plan['id']);
$grid = tasks_grid((int)$plan['id']);
$progress = plan_progress((int)$plan['id']);
$redTasks = array_values(array_filter(plan_tasks((int)$plan['id']), fn($rt)=>task_status($rt)==='missed'));
$subjects = all_subjects();
$copyTargets = array_values(array_filter(advisor_students((int)$u['id'], 'active'), fn($s) => (int)$s['id'] !== $studentId));

$prevWeek = date('Y-m-d', strtotime($weekStart.' -7 day'));
$nextWeek = date('Y-m-d', strtotime($weekStart.' +7 day'));
$weekEnd  = date('Y-m-d', strtotime($weekStart.' +6 day'));

panel_start('برنامه‌ریز هفتگی', '', 'admin', 'plans', ['builder.css']);
?>
<div class="builder-top">
  <div class="builder-student">
    <a href="<?= url('admin/students.php') ?>" class="btn btn-ghost btn-icon" data-tip="بازگشت"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div>
      <div style="font-weight:800;font-size:1.05rem"><?= e($student['full_name']) ?></div>
      <div class="muted" style="font-size:.8rem"><?= e($student['field'] ?: '') ?> <?= $student['grade']?'· '.e($student['grade']):'' ?></div>
    </div>
  </div>
  <div class="builder-toolbar">
    <span class="save-status" id="saveStatus"><?= icon('check-circle',16) ?> ذخیره خودکار فعال</span>
    <div class="week-nav">
      <a href="?student=<?= $studentId ?>&week=<?= $prevWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته قبل"><?= icon('chevron-right',18) ?></a>
      <span class="wk"><?= jalali_date($weekStart) ?> تا <?= jalali_date($weekEnd) ?></span>
      <a href="?student=<?= $studentId ?>&week=<?= $nextWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته بعد"><?= icon('chevron-left',18) ?></a>
    </div>
  </div>
</div>

<div class="flex gap-2 wrap mb-4">
  <button class="btn btn-ghost btn-sm" id="copyWeekBtn"><?= icon('copy',15) ?> کپی از هفته قبل</button>
  <button class="btn btn-ghost btn-sm" id="seedSpecialBtn"><?= icon('sparkles',15) ?> واحد ویژه پیش‌فرض</button>
  <span class="copy-hint" id="copyHint" style="display:none">
    <?= icon('copy',14) ?> <span id="copyHintText">برای کپی، خانه مقصد را بزنید</span>
    <button type="button" id="stopPasteBtn" class="copy-hint-stop" data-tip="پایان کپی (Esc)"><?= icon('close',13) ?></button>
  </span>
  <span class="badge <?= $plan['status']==='published'?'badge-sage':'badge-gold' ?>" id="statusBadge">
    <?= $plan['status']==='published' ? icon('check-circle',13).' منتشر شده' : icon('edit',13).' پیش‌نویس' ?>
  </span>
  <button class="btn btn-gold btn-sm" id="publishBtn" data-status="<?= e($plan['status']) ?>" style="margin-right:auto">
    <?= $plan['status']==='published' ? icon('edit',15).' بازگشت به پیش‌نویس' : icon('rocket',15).' انتشار برنامه' ?>
  </button>
</div>

<?php if ($copyTargets): ?>
<div class="copy-plan-panel">
  <div>
    <b><?= icon('copy',16) ?> کپی کل برنامه هفته</b>
    <span class="muted">همه تسک‌های این هفته را برای دانش‌آموز دیگر می‌سازد (در مقصد به حالت پیش‌نویس).</span>
  </div>
  <div class="copy-plan-actions">
    <select class="select" id="copyTargetStudent">
      <option value="">انتخاب دانش‌آموز مقصد…</option>
      <?php foreach ($copyTargets as $cs): ?>
      <option value="<?= (int)$cs['id'] ?>"><?= e($cs['full_name']) ?><?= $cs['field']?' · '.e($cs['field']):'' ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sage btn-sm" id="copyToStudentBtn" type="button"><?= icon('copy',15) ?> انتقال برنامه</button>
  </div>
</div>
<?php endif; ?>

<!-- grid -->
<div class="plan-wrap" data-density="<?= e($plannerCfg['gridDensity']) ?>">
  <table class="plan-grid" id="planGrid" data-plan="<?= (int)$plan['id'] ?>">
    <thead>
      <tr>
        <th class="day-col">روز / واحد</th>
        <?php foreach (UNIT_NAMES as $ui=>$un): ?>
        <th class="<?= $ui===8?'special':'' ?>">
          <div class="unit-head">
            <span class="unit-name"><?= e($un) ?></span>
            <button type="button" class="unit-clear" data-clear-unit="<?= $ui ?>" data-tip="حذف این واحد در کل هفته" aria-label="حذف کل واحد">
              <?= icon('trash',13) ?>
            </button>
          </div>
        </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach (DAY_NAMES as $di=>$dn): ?>
      <tr>
        <td class="day-cell">
          <?= e($dn) ?>
          <span class="dnum"><?= jalali_date(date('Y-m-d', strtotime($weekStart." +$di day"))) ?></span>
          <button class="btn-icon btn btn-ghost btn-sm" style="margin-top:6px;width:26px;height:26px" data-clear-day="<?= $di ?>" data-tip="پاک‌کردن روز"><?= icon('trash',13) ?></button>
        </td>
        <?php foreach (UNIT_NAMES as $ui=>$un): ?>
        <td class="cell <?= $ui===8?'special':'' ?>" data-day="<?= $di ?>" data-unit="<?= $ui ?>">
          <div class="cell-tasks">
          <?php foreach (($grid[$di][$ui] ?? []) as $t): ?>
            <?= builder_task_pill($t) ?>
          <?php endforeach; ?>
          </div>
          <div class="cell-add"><?= icon('plus',18) ?></div>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="plan-summary">
  <div class="ps-item"><span class="icon-tile sage" style="width:38px;height:38px"><?= icon('list',18) ?></span><div><div class="v" id="sumTotal"><?= fa_num($progress['total']) ?></div><div class="k">کل تسک‌ها</div></div></div>
  <div class="ps-item"><span class="icon-tile" style="width:38px;height:38px"><?= icon('check-circle',18) ?></span><div><div class="v" id="sumDone"><?= fa_num($progress['done_display']) ?></div><div class="k">انجام‌شده</div></div></div>
  <div class="ps-item" style="flex:1;min-width:200px"><div style="flex:1;width:100%"><div class="between" style="font-size:.78rem;margin-bottom:5px"><span class="k">پیشرفت دانش‌آموز</span><span class="v" id="sumPct" style="font-size:.95rem"><?= fa_num($progress['percent']) ?>٪</span></div><div class="progress"><span id="sumBar" style="width:<?= $progress['percent'] ?>%"></span></div></div></div>
</div>

<div class="copy-plan-panel red-tasks-mini red-tasks-closed">
  <div><b>× تسک‌های قرمز این هفته</b><span class="muted">در یک پنجره مرتب نمایش داده می‌شود.</span></div>
  <button class="btn btn-ghost btn-sm red-modal-btn" type="button" data-modal="redTasksModal">مشاهده <span><?= fa_num(count($redTasks)) ?></span></button>
</div>

<div class="modal-backdrop" id="redTasksModal">
  <div class="modal red-modal">
    <div class="modal-head">
      <h3>× تسک‌های قرمز</h3>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>
    <div class="red-modal-top">
      <a href="?student=<?= $studentId ?>&week=<?= $prevWeek ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-right',15) ?> هفته قبل</a>
      <div class="red-range"><?= jalali_date($weekStart) ?> تا <?= jalali_date($weekEnd) ?></div>
      <a href="?student=<?= $studentId ?>&week=<?= $nextWeek ?>" class="btn btn-ghost btn-sm">هفته بعد <?= icon('chevron-left',15) ?></a>
    </div>
    <?php if(!$redTasks): ?>
      <div class="empty-state" style="padding:34px"><div class="es-ico">✓</div>برای این بازه تسک قرمزی ثبت نشده</div>
    <?php else: ?>
    <div class="compact-task-list red-modal-list">
      <?php foreach ($redTasks as $rt): ?>
        <div class="compact-task red"><b><?= e(DAY_NAMES[(int)$rt['day_index']]) ?> · <?= e($rt['title']) ?></b><span><?= e(TASK_TYPES[$rt['task_type']]['label']??'') ?><?= !empty($rt['source'])?' · '.e($rt['source']):'' ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== task editor modal (full-screen, no-scroll) ===== -->
<div class="modal-backdrop" id="taskModal">
  <div class="modal task-modal-full">
    <div class="modal-head tm-head">
      <div class="tm-title">
        <span class="tm-ico" id="taskHeadIco"><?= icon('plus',20) ?></span>
        <div>
          <h3 id="taskModalTitle">افزودن تسک</h3>
          <span class="tm-sub" id="taskModalSub">درس و نوع را انتخاب کنید؛ بقیه‌ی موارد خودکار پر می‌شوند.</span>
        </div>
      </div>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>

    <form id="taskForm" class="tm-form">
      <input type="hidden" name="id" id="taskId">
      <input type="hidden" name="day_index" id="taskDay">
      <input type="hidden" name="unit_index" id="taskUnit">
      <input type="hidden" name="task_type" id="taskType" value="study_test">

      <div class="tm-grid">
        <!-- ستون راست: درس + نوع + پیش‌فرض‌ها -->
        <section class="tm-col">
          <div class="field">
            <label><?= icon('book',15) ?> درس</label>
            <div class="subj-chips" id="subjChips">
              <button type="button" class="subj-chip active" data-subject=""><?= icon('close',13) ?> بدون درس</button>
              <?php foreach ($subjects as $s): ?>
              <button type="button" class="subj-chip" data-subject="<?= (int)$s['id'] ?>" style="--c:<?= e($s['color'] ?? '#6b8872') ?>">
                <span class="subj-dot" style="background:<?= e($s['color'] ?? '#6b8872') ?>"></span><?= e($s['name']) ?>
              </button>
              <?php endforeach; ?>
            </div>
            <select class="select" id="f_subject" name="subject_id" hidden>
              <option value="">— بدون درس —</option>
              <?php foreach ($subjects as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label><?= icon('tasks',15) ?> نوع تسک</label>
            <div class="type-grid" id="typeGrid">
              <?php foreach (TASK_TYPES as $k=>$tt): ?>
              <div class="type-opt <?= $k==='study_test'?'active':'' ?>" data-type="<?= $k ?>">
                <span class="icon-tile <?= in_array($k,['test','exam'])?'':'sage' ?>"><?= icon($tt['icon'],18) ?></span>
                <div class="t"><?= e($tt['label']) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

        </section>

        <!-- ستون چپ: جزئیات (خودکار پر شده، قابل ویرایش) -->
        <section class="tm-col">
          <div class="field">
            <label for="f_title"><?= icon('book',15) ?> عنوان تسک <span class="muted">(درس + فصل)</span></label>
            <input class="input" id="f_title" name="title" placeholder="با انتخاب درس خودکار پر می‌شود — مثلاً «زیست ف۴»">
            <div class="chap-picker-wrap" id="chapPickerWrap" hidden>
              <button type="button" class="btn btn-sage btn-sm" id="chapPickerBtn">
                <?= icon('book-open',15) ?> انتخاب فصل / درس از کتاب درسی
              </button>
              <span class="muted" style="font-size:.74rem">بر اساس رشته‌ی <?= e($student['field'] ?: '—') ?> (همه پایه‌ها)</span>
            </div>
          </div>

          <div class="grid gap-3" style="grid-template-columns:1.3fr 1fr">
            <div class="field">
              <label for="f_target"><?= icon('target',15) ?> مقدار هدف</label>
              <input class="input" id="f_target" name="target_count" type="number" min="0" inputmode="numeric" placeholder="مثلاً ۴۰">
            </div>
            <div class="field">
              <label for="f_unit"><?= icon('list',15) ?> واحد</label>
              <select class="select" id="f_unit" name="target_unit">
                <?php foreach (['تست','سوال','صفحه','درسنامه','دقیقه','ساعت','فصل','مبحث'] as $tu): ?>
                <option><?= e($tu) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
            <div class="field">
              <label for="f_dur"><?= icon('clock',15) ?> مدت</label>
              <select class="select" id="f_dur" name="duration_min">
                <option value="">اختیاری</option>
                <?php foreach ([15=>'۱۵ دقیقه',30=>'۳۰ دقیقه',45=>'۴۵ دقیقه',50=>'۵۰ دقیقه',60=>'۶۰ دقیقه / ۱ ساعت',75=>'۷۵ دقیقه',90=>'۹۰ دقیقه',120=>'۱۲۰ دقیقه / ۲ ساعت',150=>'۱۵۰ دقیقه',180=>'۱۸۰ دقیقه / ۳ ساعت'] as $dm=>$dl): ?>
                <option value="<?= $dm ?>"><?= e($dl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="f_prio"><?= icon('flag',15) ?> اولویت <span class="muted">(اختیاری)</span></label>
              <select class="select" id="f_prio" name="priority">
                <option value="">بدون اولویت</option>
                <option value="normal">عادی</option>
                <option value="high">مهم</option>
                <option value="low">کم‌اهمیت</option>
              </select>
            </div>
          </div>

          <div class="field">
            <label for="f_source"><?= icon('book',15) ?> منبع <span class="muted">(اختیاری — آزاد بنویسید)</span></label>
            <input class="input" id="f_source" name="source" list="srcSuggest" placeholder="مثلاً کتاب درسی، آزمون ماز، جزوه کلاس…">
            <datalist id="srcSuggest">
              <option value="کتاب درسی"></option>
              <option value="آزمون ماز"></option>
              <option value="آزمون قلم‌چی"></option>
              <option value="آزمون گزینه دو"></option>
              <option value="جزوه کلاس"></option>
              <option value="ویدیو/فیلم آموزشی"></option>
              <option value="کتاب تست"></option>
              <option value="بانک تست"></option>
            </datalist>
          </div>

          <div class="field" style="margin-bottom:0">
            <label for="f_desc"><?= icon('note',15) ?> توضیحات <span class="muted">(اختیاری)</span></label>
            <textarea class="input" id="f_desc" name="description" rows="2" placeholder="توضیح کوتاه برای دانش‌آموز…"></textarea>
          </div>

          <!-- پیش‌نمایش زنده‌ی تسک -->
          <div class="tm-preview" id="taskPreview">
            <span class="muted" style="font-size:.74rem"><?= icon('eye',13) ?> پیش‌نمایش:</span>
            <div class="task-pill" id="previewPill" style="cursor:default">
              <span class="tp-title" id="pvTitle">تسک</span>
              <span class="tp-meta" id="pvMeta"></span>
              <span class="tp-type" id="pvType">درسنامه + تست</span>
            </div>
          </div>
        </section>
      </div>

      <div class="tm-footer">
        <button type="button" class="btn btn-ghost" id="deleteTaskBtn" style="display:none;color:var(--danger)"><?= icon('trash',16) ?> حذف</button>
        <span class="tm-hint"><?= icon('info',13) ?> با <b>Ctrl+Enter</b> سریع ذخیره کنید</span>
        <button type="submit" class="btn btn-gold btn-lg" style="min-width:170px"><?= icon('check',16) ?> ذخیره تسک</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== chapter picker modal ===== -->
<div class="modal-backdrop" id="chapterPickerModal">
  <div class="modal chapter-picker-modal">
    <div class="modal-head">
      <div>
        <h3><?= icon('book-open',20) ?> انتخاب فصل / درس</h3>
        <span class="muted" style="font-size:.78rem">کتاب و فصل یا درس‌های موردنظر را انتخاب کنید؛ عنوان تسک خودکار پر می‌شود</span>
      </div>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>
    <div class="chapter-picker-body" id="chapterPickerBody">
      <div class="empty-state" style="padding:40px"><span class="spinner" style="width:28px;height:28px"></span><span>در حال بارگذاری فصل‌ها…</span></div>
    </div>
    <div class="chapter-picker-footer">
      <button type="button" class="btn btn-ghost btn-sm" data-close>بستن</button>
      <button type="button" class="btn btn-gold btn-sm" id="applyChapterSelectionBtn"><?= icon('check',15) ?> ثبت انتخاب</button>
    </div>
  </div>
</div>

<!-- ===== right-click context menu ===== -->
<div class="ctx-menu" id="ctxMenu" role="menu" hidden>
  <button type="button" class="ctx-item" data-act="edit"><?= icon('edit',15) ?> ویرایش تسک</button>
  <button type="button" class="ctx-item" data-act="copy"><?= icon('copy',15) ?> کپی تسک</button>
  <button type="button" class="ctx-item" data-act="duplicate"><?= icon('plus',15) ?> تکثیر در همین خانه</button>
  <button type="button" class="ctx-item" data-act="done"><?= icon('check-circle',15) ?> تغییر وضعیت انجام</button>
  <div class="ctx-sep"></div>
  <button type="button" class="ctx-item danger" data-act="delete"><?= icon('trash',15) ?> حذف تسک</button>
</div>
<!-- ===== right-click menu for empty cell ===== -->
<div class="ctx-menu" id="ctxCell" role="menu" hidden>
  <button type="button" class="ctx-item" data-act="add"><?= icon('plus',15) ?> افزودن تسک</button>
  <button type="button" class="ctx-item" data-act="paste"><?= icon('copy',15) ?> پیست تسک کپی‌شده</button>
  <div class="ctx-sep"></div>
  <button type="button" class="ctx-item danger" data-act="clear_cell"><?= icon('trash',15) ?> خالی‌کردن این خانه</button>
</div>

<script>
  window.API_TASKS = '<?= url('api/tasks.php') ?>';
  window.API_CHAPTERS = '<?= url('api/chapters.php') ?>';
  window.STUDENT_ID = <?= (int)$studentId ?>;
  window.NOTIF_URL = '<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL = '<?= url('api/notifications.php?read=1') ?>';
  window.TASK_TYPES = <?= json_encode(array_map(fn($k)=>['label'=>TASK_TYPES[$k]['label']], array_combine(array_keys(TASK_TYPES),array_keys(TASK_TYPES))), JSON_UNESCAPED_UNICODE) ?>;
  window.PLANNER_CFG = <?= json_encode($plannerCfg, JSON_UNESCAPED_UNICODE) ?>;
  window.SUBJECTS = <?= json_encode(array_map(fn($s)=>[
      'id'=>(int)$s['id'],'name'=>$s['name'],'color'=>$s['color'] ?? '#6b8872',
      'testDefault'=>subject_test_default($s['name'])
  ], $subjects), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
panel_end(['builder.js']);

/* ---- seed default special unit once for empty plans ---- */
function ensure_special_defaults_for_empty_plan(int $planId): void {
    $marker = '[special_defaults_seeded]';
    $p = db()->prepare('SELECT * FROM plans WHERE id=? LIMIT 1');
    $p->execute([$planId]);
    $plan = $p->fetch();
    if (!$plan) return;
    if (str_contains((string)($plan['note'] ?? ''), $marker)) return;
    $cnt = db()->prepare('SELECT COUNT(*) FROM tasks WHERE plan_id=?');
    $cnt->execute([$planId]);
    if ((int)$cnt->fetchColumn() > 0) return;
    $cfg = advisor_settings((int)($plan['advisor_id'] ?? 0));
    $rMin = min(45, (int)$cfg['special_reading_min']); $eMin = (int)$cfg['special_exam_min'];
    $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,title,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    for ($day=0; $day<7; $day++) {
        $ins->execute([$planId,$plan['student_id'],'روزخوانی','reading',$day,8,1,'ساعت',$rMin,'normal',1]);
        $ins->execute([$planId,$plan['student_id'],'مرور ویژه','review',$day,8,15,'دقیقه',15,'normal',2]);
        $ins->execute([$planId,$plan['student_id'],'آزمونک','exam',$day,8,50,'دقیقه',$eMin,'normal',3]);
    }
    $note = trim((string)($plan['note'] ?? ''));
    $note = $note ? ($note . "\n" . $marker) : $marker;
    db()->prepare('UPDATE plans SET note=? WHERE id=?')->execute([$note,$planId]);
}

/* ---- تعداد تستِ پیش‌فرضِ هر درس (برگرفته از الگوی برنامه‌ی واقعی) ---- */
function subject_test_default(string $name): int {
    $n = trim($name);
    // زیست‌شناسی → ۴۰ تست
    if (strpos($n, 'زیست') !== false) return 40;
    // ریاضی/حسابان/هندسه/گسسته/ریاضی جامع → ۳۵ تست
    foreach (['ریاضی جامع','ریاضی','حسابان','هندسه','گسسته'] as $k) if (strpos($n, $k) !== false) return 35;
    // شیمی و فیزیک → ۳۰ تست
    foreach (['شیمی','فیزیک'] as $k) if (strpos($n, $k) !== false) return 30;
    // عمومی‌ها → ۲۰ تست
    foreach (['ادبیات','عربی','دینی','زبان','هویت','سلامت'] as $k) if (strpos($n, $k) !== false) return 20;
    return 30;
}

/* ---- helper: pill markup (server-side, mirrors JS) ---- */
function builder_task_pill(array $t): string {
    $st = task_status($t);
    $done = $st === 'full' ? 'done' : ($st === 'partial' ? 'partial' : ($st === 'missed' ? 'missed' : ''));
    $meta = [];
    if ($t['target_count']!==null) $meta[] = fa_num($t['target_count']).' '.e($t['target_unit']);
    if ($t['duration_min']) $meta[] = fa_num($t['duration_min']).' دقیقه';
    if (($t['course_percent'] ?? null) !== null) $meta[] = fa_num((int)$t['course_percent']).'٪ کورس';
    $metaStr = implode(' · ', $meta);
    $src = trim((string)($t['source'] ?? ''));
    $type = TASK_TYPES[$t['task_type']]['label'] ?? $t['task_type'];
    $statusTxt = ['full'=>'✓ کامل','partial'=>'● ناقص','missed'=>'× قرمز','pending'=>'در انتظار'][$st] ?? '';
    return '<div class="task-pill type-'.e($t['task_type']).' status-'.$st.' '.$done.'" draggable="true" data-id="'.(int)$t['id'].'"'
        .' data-json="'.e(json_encode([
            'id'=>(int)$t['id'],'title'=>$t['title'],'description'=>$t['description'],'source'=>$src,'task_type'=>$t['task_type'],
            'day_index'=>(int)$t['day_index'],'unit_index'=>(int)$t['unit_index'],
            'target_count'=>$t['target_count']!==null?(int)$t['target_count']:'','target_unit'=>$t['target_unit'],
            'duration_min'=>$t['duration_min']!==null?(int)$t['duration_min']:'','priority'=>$t['priority'],
            'subject_id'=>$t['subject_id']!==null?(int)$t['subject_id']:'',
            'is_done'=>(int)$t['is_done'],'completion_status'=>$st,
        ], JSON_UNESCAPED_UNICODE)).'">'
        .'<div class="tp-actions">'
        .'<button class="tp-copy" data-copy title="کپی این تسک">'.icon('copy',13).'</button>'
        .'<button class="tp-del" data-del title="حذف">'.icon('close',13).'</button>'
        .'</div>'
        .'<span class="tp-title">'.e($t['title']).'</span>'
        .($metaStr?'<span class="tp-meta">'.$metaStr.'</span>':'')
        .($src?'<span class="tp-src">'.icon('book',11).' '.e($src).'</span>':'')
        .'<span class="tp-type">'.e($type).' · '.$statusTxt.'</span>'
        .'</div>';
}
