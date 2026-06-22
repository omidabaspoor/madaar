<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

// Fetch statistics
$internalCount = (int)db()->query('SELECT COUNT(*) FROM internal_exam_analyses WHERE student_id='.(int)$u['id'])->fetchColumn();
$mockCount = (int)db()->query('SELECT COUNT(*) FROM mock_exam_reports WHERE student_id='.(int)$u['id'])->fetchColumn();

// Fetch latest unified analyses
$unified = [];

// Get latest 5 internal analyses
$stInt = db()->prepare('SELECT ia.id, ia.attempt_id, e.title exam_title, a.submitted_at, a.total_score, "internal" as exam_type FROM internal_exam_analyses ia JOIN exams e ON e.id=ia.exam_id JOIN exam_attempts a ON a.id=ia.attempt_id WHERE ia.student_id=? ORDER BY a.submitted_at DESC LIMIT 5');
$stInt->execute([$u['id']]);
foreach ($stInt->fetchAll() as $row) {
    $unified[] = [
        'title' => $row['exam_title'],
        'date' => $row['submitted_at'],
        'score' => (float)$row['total_score'],
        'type' => 'internal',
        'url' => url('student/internal_exam_analysis.php?attempt=' . $row['attempt_id'])
    ];
}

// Get latest 5 mock analyses
$stMock = db()->prepare('SELECT id, exam_title, exam_date, total_percent, "mock" as exam_type FROM mock_exam_reports WHERE student_id=? ORDER BY exam_date DESC, id DESC LIMIT 5');
$stMock->execute([$u['id']]);
foreach ($stMock->fetchAll() as $row) {
    $unified[] = [
        'title' => $row['exam_title'] ?: 'آزمون آزمایشی',
        'date' => $row['exam_date'],
        'score' => (float)$row['total_percent'],
        'type' => 'mock',
        'url' => url('student/mock_exam.php?id=' . $row['id'])
    ];
}

// Sort unified by date descending
usort($unified, fn($a, $b) => strcmp($b['date'], $a['date']));
$latestAnalyses = array_slice($unified, 0, 6);

panel_start('تحلیل آزمون‌ها', 'میز کار هوشمند ریشه‌‌یابی خطاها و برنامه‌ریزی اقدام آزمون', 'student', 'exam_analyses', ['student.css']);
?>
<div class="greet-card reveal in" style="margin-bottom: 24px; background: linear-gradient(135deg, #182a20, #0c1512); border: 1px solid rgba(178, 148, 95, 0.35);">
  <div class="between" style="align-items: center; flex-wrap: wrap; gap: 16px;">
    <div>
      <div class="gc-sub">پنل هوشمند مَدار 🧠</div>
      <h2 style="margin-top: 4px; color: var(--gold-light);">کدام آزمون را تحلیل کنیم؟</h2>
      <p class="muted" style="font-size: 13.5px; margin-top: 6px; line-height: 1.6; max-width: 600px;">
        رتبه‌های برتر کنکور معتقدند «ارزش تحلیل آزمون از خود آزمون بیشتر است». مَدار به شما کمک می‌کند تمام خطاهای علمی و بی‌دقتی‌های رفتاری خود را ریشه‌یابی کرده و برای هفته‌های بعد نقشه اقدام بسازید.
      </p>
    </div>
    <div style="display: flex; gap: 12px; background: rgba(12, 21, 18, 0.6); padding: 12px 18px; border-radius: 18px; border: 1px solid rgba(255,255,255,0.06);">
      <div style="text-align: center; border-left: 1px solid rgba(255,255,255,0.08); padding-left: 12px;">
        <span class="muted" style="font-size: 11px; font-weight: 800; display: block; margin-bottom: 2px;">آزمون‌های داخلی</span>
        <b style="font-size: 22px; color: var(--gold-light);"><?= fa_num($internalCount) ?></b>
      </div>
      <div style="text-align: center; padding-right: 6px;">
        <span class="muted" style="font-size: 11px; font-weight: 800; display: block; margin-bottom: 2px;">آزمون‌های بیرونی</span>
        <b style="font-size: 22px; color: var(--gold-light);"><?= fa_num($mockCount) ?></b>
      </div>
    </div>
  </div>
</div>

<div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); margin-bottom: 24px;">
  
  <!-- CARD 1: Internal Exam Analysis -->
  <div class="panel" style="background: rgba(20,32,27,0.7); border: 1px solid rgba(107, 136, 114, 0.25); border-radius: 22px; padding: 24px; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15);" onmouseover="this.style.borderColor='rgba(178, 148, 95, 0.5)'; this.style.transform='translateY(-4px)';" onmouseout="this.style.borderColor='rgba(107, 136, 114, 0.25)'; this.style.transform='none';">
    <div>
      <div class="between" style="align-items: center; margin-bottom: 18px;">
        <div style="background: rgba(107, 136, 114, 0.15); color: var(--sage-light); width: 54px; height: 50px; border-radius: 14px; display: grid; place-items: center;">
          <?= icon('chart', 26) ?>
        </div>
        <span class="badge badge-sage" style="font-weight: 800; font-size: 11px; padding: 4px 10px;">آزمون‌های داخل مَدار</span>
      </div>
      <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-1); margin-bottom: 8px;">تحلیل آزمون داخلی مَدار</h3>
      <p class="muted" style="font-size: 13px; line-height: 1.6; margin-bottom: 20px;">
        ویژه آزمون‌هایی که مستقیماً در همین سامانه برگزار کرده‌اید. کارنامه، درصدها و تعداد غیبت‌ها خودکار خوانده شده و شما با ریشه‌یابی تک‌تک تست‌های غلط/نزده، یک تحلیل ۳۶۰ درجه می‌سازید.
      </p>
      
      <ul style="list-style: none; padding: 0; margin: 0 0 24px 0; font-size: 12.5px; color: var(--text-2); display: flex; flex-direction: column; gap: 8px;">
        <li style="display: flex; align-items: center; gap: 8px;"><?= icon('check', 14) ?> استخراج خودکار ریزنتایج آزمون‌های سامانه</li>
        <li style="display: flex; align-items: center; gap: 8px;"><?= icon('check', 14) ?> تحلیل و دسته‌بندی تک‌تک سوالات نادرست و سفید</li>
        <li style="display: flex; align-items: center; gap: 8px;"><?= icon('check', 14) ?> ایجاد نقشه اقدام کوتاه برای آزمون‌های هفته بعد</li>
      </ul>
    </div>
    
    <a href="<?= url('student/internal_exam_analysis.php') ?>" class="btn btn-gold btn-block" style="font-weight: 900; padding: 12px; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 8px;">
      ورود به تحلیل آزمون داخلی <?= icon('arrow-left', 16) ?>
    </a>
  </div>

  <!-- CARD 2: Mock Exam Analysis -->
  <div class="panel" style="background: rgba(20,32,27,0.7); border: 1px solid rgba(178, 148, 95, 0.20); border-radius: 22px; padding: 24px; display: flex; flex-direction: column; justify-content: space-between; transition: all 0.3s; box-shadow: 0 10px 30px rgba(0,0,0,0.15);" onmouseover="this.style.borderColor='rgba(178, 148, 95, 0.6)'; this.style.transform='translateY(-4px)';" onmouseout="this.style.borderColor='rgba(178, 148, 95, 0.20)'; this.style.transform='none';">
    <div>
      <div class="between" style="align-items: center; margin-bottom: 18px;">
        <div style="background: rgba(178, 148, 95, 0.15); color: var(--gold-light); width: 54px; height: 50px; border-radius: 14px; display: grid; place-items: center;">
          <?= icon('target', 26) ?>
        </div>
        <span class="badge badge-gold" style="font-weight: 800; font-size: 11px; padding: 4px 10px;">تحلیل آزمون آزمایشی/کنکور</span>
      </div>
      <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-1); margin-bottom: 8px;">تحلیل آزمون آزمایشی/کنکور</h3>
      <p class="muted" style="font-size: 13px; line-height: 1.6; margin-bottom: 20px;">
        مخصوص ثبت دستی و تحلیل پیشرفته‌ی آزمون‌های آزمایشی مطرح کشور (قلم‌چی، ماز، گزینه دو، سنجش و گاج) یا کنکورهای سراسری شبیه‌سازی‌شده در خانه برای بهینه‌سازی تراز و رتبه.
      </p>
      
      <ul style="list-style: none; padding: 0; margin: 0 0 24px 0; font-size: 12.5px; color: var(--text-2); display: flex; flex-direction: column; gap: 8px;">
        <li style="display: flex; align-items: center; gap: 8px;"><?= icon('check', 14) ?> پشتیبانی کامل از تمامی آزمون‌های آزمایشی کشوری</li>
        <li style="display: flex; align-items: center; gap: 8px;"><?= icon('check', 14) ?> ارزیابی درصدها، شک‌ها، تله‌های تستی و مدیریت زمان</li>
        <li style="display: flex; align-items: center; gap: 8px;"><?= icon('check', 14) ?> تحلیل هوشمند بر مبنای خواب، استرس و تمرکز روز آزمون</li>
      </ul>
    </div>
    
    <a href="<?= url('student/mock_exam.php') ?>" class="btn btn-gold btn-block" style="font-weight: 900; padding: 12px; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 8px;">
      ورود به تحلیل آزمون آزمایشی <?= icon('arrow-left', 16) ?>
    </a>
  </div>

</div>

<!-- UNIFIED RECENT ANALYSES LIST -->
<div class="panel">
  <div class="panel-head" style="margin-bottom: 16px;">
    <h3><?= icon('list', 20) ?> آخرین تحلیل‌های ثبت‌شده‌ی شما</h3>
  </div>
  <?php if (empty($latestAnalyses)): ?>
    <div class="empty-state">
      <div class="es-ico"><?= icon('chart', 32) ?></div>
      <p>هنوز تحلیلی ثبت نکرده‌اید 🌱</p>
      <p class="muted" style="font-size: 12px;">پس از اولین آزمون، تحلیل آن را در مَدار ثبت کنید تا در این بخش نمایش داده شود.</p>
    </div>
  <?php else: ?>
    <div class="mock-list">
      <?php foreach ($latestAnalyses as $it): ?>
        <a class="panel" href="<?= e($it['url']) ?>" style="background: rgba(240, 244, 241, 0.4); border: 1px solid #dfe7df; display: flex; justify-content: space-between; align-items: center; border-radius: 14px; padding: 14px 20px; text-decoration: none; color: inherit; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--gold)';" onmouseout="this.style.borderColor='#dfe7df';">
          <div>
            <b style="font-size: 14.5px; color: var(--text-1);"><?= e($it['title']) ?></b>
            <div class="muted" style="font-size: 11.5px; margin-top: 4px;">
              <?= jalali_date($it['date'], true) ?> · 
              <span class="badge" style="font-size: 10px; background: <?= $it['type']==='internal'?'rgba(107, 136, 114, 0.15)':'rgba(178, 148, 95, 0.15)' ?>; color: <?= $it['type']==='internal'?'#6b8872':'#b2945f' ?>; border: none; font-weight: 800;">
                <?= $it['type']==='internal'?'آزمون داخلی':'آزمون بیرونی' ?>
              </span>
            </div>
          </div>
          <div style="text-align: left; display: flex; align-items: center; gap: 14px;">
            <div style="background: rgba(12,21,18,0.06); padding: 6px 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.03);">
              <span style="font-size: 11px; color: var(--text-3); display: block;">درصد کل</span>
              <b style="font-size: 16px; color: var(--text-1);"><?= fa_num(round($it['score'], 1)) ?>٪</b>
            </div>
            <?= icon('chevron-left', 16, 'text-muted') ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php panel_end(); ?>
