<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review_scheduler.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

$webNotifEnabled = advisor_feature_enabled((int)($u['advisor_id'] ?? 0), 'web_notifications');
$reviewsEnabled  = advisor_feature_enabled((int)($u['advisor_id'] ?? 0), 'review_enabled');

$scope = $_GET['scope'] ?? 'due';
if (!in_array($scope, ['due','upcoming','done'], true)) {
    $scope = 'due';
}

$reviewError = '';
try {
    if (!$reviewsEnabled) { throw new RuntimeException('DISABLED_REVIEWS'); }
    if (!review_schema_ready()) { throw new RuntimeException('REVIEW_SCHEMA_NOT_READY'); }
    review_due_notifications((int)$u['id']);
    $counts = review_counts((int)$u['id']);
    $items = review_items((int)$u['id'], $scope);
    
    // محاسبه کیفیت مرورها (آسان/خوب در مقابل سخت) برای کارت آمار
    $qualityStats = ['good'=>0, 'hard'=>0, 'total'=>0];
    $doneItems = review_items((int)$u['id'], 'done');
    foreach ($doneItems as $di) {
        $qualityStats['total']++;
        if ($di['quality'] === 'hard') $qualityStats['hard']++;
        else $qualityStats['good']++;
    }
    $retrievalScore = $qualityStats['total'] > 0 ? round(($qualityStats['good'] / $qualityStats['total']) * 100) : 100;
} catch (Throwable $e) {
    error_log('Madar reviews page error: '.$e->getMessage());
    $reviewError = $e->getMessage()==='DISABLED_REVIEWS' ? 'مرورهای هوشمند فعلاً توسط مشاور غیرفعال شده است.' : (APP_ENV === 'development' ? $e->getMessage() : 'مرورها فعلاً آماده نیستند. لطفاً کمی بعد دوباره تلاش کن.');
    $counts = ['due'=>0,'upcoming'=>0,'done'=>0];
    $items = [];
    $retrievalScore = 100;
}

// استخراج لیست درس‌های موجود برای فیلتر هوشمند
$availableSubjects = [];
foreach ($items as $it) {
    $sname = trim((string)($it['subject_name'] ?? ''));
    if ($sname) $availableSubjects[$sname] = true;
}
ksort($availableSubjects);
$availableSubjects = array_keys($availableSubjects);

panel_start('سیستم مرور هوشمند', 'موتور قدرتمند بازیابی فاصله‌دار (Spaced Repetition) برای جلوگیری از فراموشی', 'student', 'reviews', ['student.css']);
?>

<?php if($webNotifEnabled): ?>
<div class="panel notif-permission mb-4" style="display:none;align-items:center;justify-content:space-between;background:var(--surface-2);border:1px solid var(--gold);padding:14px 22px;border-radius:var(--r-lg)">
  <div class="flex gap-3" style="align-items:center">
    <span style="font-size:1.8rem"><?= icon('bell',32) ?></span>
    <div>
      <b style="color:var(--text-1);font-size:1.05rem">یادآوری مرورها روی دستگاه</b>
      <p class="muted mt-1" style="font-size:.85rem">برای اینکه سیستم زمان دقیق مرورهای مهم را روی صفحه گوشی یا کامپیوترت یادآوری کند، اجازه اعلان را فعال کن.</p>
    </div>
  </div>
  <button class="btn btn-gold btn-sm" type="button" data-notif-enable style="white-space:nowrap;padding:10px 20px;font-weight:800">فعال‌سازی اعلان</button>
</div>
<script>if('Notification' in window && Notification.permission==='default') document.currentScript.previousElementSibling.style.display='flex';</script>
<?php endif; ?>

<?php if($reviewError): ?><div class="alert alert-error mb-4"><?= e($reviewError) ?></div><?php endif; ?>

<!-- ===== Premium interactive Statistics Dashboard ===== -->
<div class="stat-cards mb-4" style="grid-template-columns:repeat(auto-fit, minmax(240px, 1fr))">
  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:var(--gold-glass);color:var(--gold);width:48px;height:48px"><?= icon('bell',24) ?></span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--gold)"><?= fa_num($counts['due']) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:700">موعد مرور امروز</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile sage" style="width:48px;height:48px"><?= icon('calendar',24) ?></span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900"><?= fa_num($counts['upcoming']) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:700">مرورهای زمان‌بندی‌شده آینده</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:rgba(95,174,123,0.15);color:#5fae7b;width:48px;height:48px"><?= icon('check-circle',24) ?></span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:#5fae7b"><?= fa_num($counts['done']) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:700">مرورهای انجام‌شده</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:rgba(217,178,95,0.15);color:var(--gold);width:48px;height:48px"><?= icon('sparkles',24) ?></span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900"><?= fa_num($retrievalScore) ?>٪</div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:700">شاخص موفقیت بازیابی</div>
    </div>
  </div>
</div>

<!-- ===== Navigation Tabs ===== -->
<div class="report-tabs mb-4" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:6px;border-radius:var(--r-pill)">
  <a class="chip <?= $scope==='due'?'active':'' ?>" href="?scope=due" style="flex:1;text-align:center;font-size:.95rem;padding:10px;border-radius:var(--r-pill)">
    <?= icon('bell',16) ?> موعد امروز / عقب‌افتاده (<?= fa_num($counts['due']) ?>)
  </a>
  <a class="chip <?= $scope==='upcoming'?'active':'' ?>" href="?scope=upcoming" style="flex:1;text-align:center;font-size:.95rem;padding:10px;border-radius:var(--r-pill)">
    <?= icon('calendar',16) ?> فواصل آینده (<?= fa_num($counts['upcoming']) ?>)
  </a>
  <a class="chip <?= $scope==='done'?'active':'' ?>" href="?scope=done" style="flex:1;text-align:center;font-size:.95rem;padding:10px;border-radius:var(--r-pill)">
    <?= icon('check-circle',16) ?> انجام‌شده (<?= fa_num($counts['done']) ?>)
  </a>
</div>

<!-- ===== Interactive Smart Filter & Search Bar ===== -->
<?php if ($items): ?>
<div class="panel mb-4 flex wrap gap-3 between" style="align-items:center;background:var(--surface-2);border:1px solid var(--border-soft);padding:16px 20px;border-radius:var(--r-lg)">
  <div class="flex wrap gap-2" style="align-items:center">
    <span class="muted mr-1" style="font-size:.85rem;font-weight:800"><?= icon('filter',16) ?> درس:</span>
    <button class="badge badge-sage subj-filter-btn active" type="button" data-filter="all" style="padding:6px 12px;font-size:.85rem;cursor:pointer">همه درس‌ها</button>
    <?php foreach ($availableSubjects as $subj): ?>
      <button class="badge subj-filter-btn" type="button" data-filter="<?= e($subj) ?>" style="padding:6px 12px;font-size:.85rem;background:var(--surface-1);color:var(--text-2);border:1px solid var(--border-soft);cursor:pointer"><?= e($subj) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="search-box relative" style="flex:1;min-width:240px;max-width:340px">
    <input class="input" id="reviewSearchInput" type="search" placeholder="جستجوی عنوان مبحث یا منبع..." style="padding-right:38px;margin-bottom:0;border-radius:var(--r-pill);height:40px;font-size:.9rem">
    <span class="absolute" style="right:12px;top:10px;color:var(--text-3)"><?= icon('search',18) ?></span>
  </div>
</div>
<?php endif; ?>

<!-- ===== Reviews Container ===== -->
<div id="reviewCardsContainer" class="review-list grid gap-4 mb-4" style="grid-template-columns:repeat(auto-fill, minmax(min(100%, 340px), 1fr))">
<?php foreach($items as $r): 
    $late = $scope==='due' ? max(0, (int)floor((time()-strtotime($r['due_date']))/86400)) : 0; 
    $subjName = trim((string)($r['subject_name'] ?? ''));
    $color = $r['subject_color'] ?? '#6b8872';
?>
  <div class="panel review-card <?= $scope==='due'?'due':'' ?>" data-review="<?= (int)$r['id'] ?>" data-subject="<?= e($subjName) ?>" data-title="<?= e(mb_strtolower($r['topic_title'].' '.($r['source']??''))) ?>" style="display:flex;flex-direction:column;justify-content:space-between;border:1px solid var(--border-soft);padding:24px;border-radius:var(--r-lg);background:var(--surface-2);position:relative;overflow:hidden;transition:all 0.3s cubic-bezier(0.16, 1, 0.3, 1);min-height:100%;word-break:break-word">
    <!-- نوار رنگی درس بالای کارت -->
    <div style="position:absolute;top:0;right:0;left:0;height:4px;background:<?= e($color) ?>"></div>
    
    <div class="review-main mb-4 mt-1" style="flex:1;display:flex;flex-direction:column">
      <div class="between mb-3 wrap gap-2" style="align-items:center">
        <span class="badge" style="background:<?= e($color) ?>22;color:<?= e($color) ?>;font-size:.85rem;padding:6px 12px;font-weight:900;border-radius:var(--r-pill)">
          <?= $subjName ? icon('book',15).' '.e($subjName) : 'عمومی / سایر' ?>
        </span>
        <span class="badge badge-gold" style="font-size:.85rem;font-weight:800;padding:4px 10px">
          <?= (int)$r['interval_days']===0 ? 'مرور تقویتی' : 'فاصِله '.fa_num($r['interval_days']).' روزه' ?>
        </span>
      </div>

      <h3 style="font-size:1.2۵rem;font-weight:900;color:var(--text-1);margin-bottom:12px;line-height:1.7;word-break:break-word;overflow-wrap:break-word;white-space:normal"><?= e($r['topic_title']) ?></h3>
      
      <div class="muted mt-auto" style="font-size:.88rem;line-height:1.7;display:flex;flex-wrap:wrap;gap:12px;align-items:center">
        <?php if($r['profile_label']): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;background:var(--surface-1);padding:4px 10px;border-radius:6px;font-weight:700">
            <?= icon('sparkles',14) ?> <?= e($r['profile_label']) ?>
          </span>
        <?php endif; ?>
        
        <?php if($r['source']): ?>
          <span style="display:inline-flex;align-items:center;gap:6px;color:var(--text-2);background:var(--surface-1);padding:4px 10px;border-radius:6px;font-weight:700">
            <?= icon('paperclip',14) ?> منبع: <?= e($r['source']) ?>
          </span>
        <?php endif; ?>
        
        <span style="display:inline-flex;align-items:center;gap:6px;font-weight:800;color:var(--gold);background:var(--gold-glass);padding:4px 10px;border-radius:6px">
          <?= icon('clock',14) ?> <?= fa_num((int)($r['suggested_minutes']??15)) ?> دقیقه
        </span>
      </div>

      <div class="mt-4 between wrap gap-2" style="font-size:.85rem;border-top:1px dashed var(--border-soft);padding-top:14px;align-items:center">
        <span class="muted" style="font-weight:700">موعد: <b style="color:var(--text-1)"><?= jalali_date($r['due_date']) ?></b></span>
        <?php if($late > 0): ?>
          <span style="color:var(--danger);font-weight:900;background:rgba(217,116,116,0.15);padding:4px 10px;border-radius:6px">
            ⚠️ <?= fa_num($late) ?> روز تأخیر
          </span>
        <?php endif; ?>
      </div>
    </div>
    
    <?php if($scope!=='done'): ?>
      <div class="review-actions mt-auto flex wrap gap-2" style="border-top:1px solid var(--border-soft);padding-top:16px">
        <button class="btn btn-ghost btn-sm" style="flex:1;min-width:90px;color:var(--danger);font-size:.85rem;font-weight:800;border:1px solid rgba(217,116,116,0.25);height:40px" data-review-done data-quality="hard" title="مبحث سخت بود؛ فردا مجدد یادآوری شود">سخت بود</button>
        <button class="btn btn-sage btn-sm" style="flex:1;min-width:90px;font-size:.85rem;font-weight:800;height:40px" data-review-done data-quality="good" title="مرور طبق برنامه با موفقیت انجام شد">مرور کردم</button>
        <button class="btn btn-gold btn-sm" style="flex:1;min-width:90px;font-size:.85rem;font-weight:800;height:40px" data-review-done data-quality="easy" title="مبحث کاملاً تثبیت شده و آسان بود">آسون بود</button>
        <button class="btn btn-ghost btn-sm" style="width:40px;height:40px;padding:0;color:var(--text-3);border:1px solid var(--border-soft);display:flex;align-items:center;justify-content:center" data-review-dismiss title="کنار گذاشتن از لیست امروز"><?= icon('close',16) ?></button>
      </div>
    <?php else: ?>
      <div class="mt-auto pt-3 between wrap gap-2" style="border-top:1px solid var(--border-soft);align-items:center;font-size:.85rem">
        <span class="muted">کیفیت ثبت‌شده: <b style="color:var(--text-1)"><?= e(['hard'=>'سخت و چالش‌برانگیز','good'=>'خوب و استاندارد','easy'=>'آسان و مسلط'][$r['quality']??'good'] ?? 'خوب') ?></b></span>
        <span class="badge badge-sage" style="padding:6px 12px;font-weight:900"><?= icon('check',15) ?> بایگانی‌شده</span>
      </div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<!-- ===== Premium Ultimate Empty State (Rendered dynamically via PHP or JS) ===== -->
<div id="reviewEmptyState" class="panel empty-state-premium text-c <?= $items?'hidden':'' ?>" style="padding:64px 24px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--r-lg);box-shadow:0 12px 32px rgba(0,0,0,0.18)">
  <div class="es-ico-wrapper" style="width:88px;height:88px;margin:0 auto 24px;display:flex;align-items:center;justify-content:center;background:rgba(217,178,95,0.12);color:var(--gold);border-radius:50%">
    <?= icon('repeat',44) ?>
  </div>
  
  <?php if($scope==='due'): ?>
    <h3 id="esTitle" style="font-size:1.4۵rem;font-weight:900;margin-bottom:12px;color:var(--text-1)">همه‌چیز مرتب است! هیچ مروری برای امروز عقب نمانده 🎉</h3>
    <p id="esDesc" class="muted" style="max-width:520px;margin:0 auto 28px;font-size:.9۵rem;line-height:1.6">
      به‌محض اینکه تسک‌های خواندنی یا کتاب درسی برنامه‌ات را کامل کنی، موتور هوشمند بازیابی فاصله‌دار، زمان‌های بعدی مرور را بر اساس منحنی فراموشی در این بخش برایت چیده و یادآوری می‌کند.
    </p>
    <a href="<?= url('student/plan.php') ?>" class="btn btn-gold btn-lg text-c" style="display:inline-flex;align-items:center;gap:10px;padding:14px 32px;font-weight:900;font-size:1.05rem">
      <?= icon('calendar',20) ?> رفتن به برنامه‌ی هفتگی و اجرای تسک‌ها
    </a>
  <?php elseif($scope==='upcoming'): ?>
    <h3 id="esTitle" style="font-size:1.4۵rem;font-weight:900;margin-bottom:12px;color:var(--text-1)">هیچ مرور فاصله‌داری برای روزهای آینده زمان‌بندی نشده است 📅</h3>
    <p id="esDesc" class="muted" style="max-width:520px;margin:0 auto 28px;font-size:.9۵rem;line-height:1.6">
      هنگامی که مباحث جدیدی را مطالعه و کامل کنی، فواصل بعدی مرور (مانند ۳ روز، ۷ روز یا ۱۶ روز بعد) بر اساس ماهیت هر درس در این بخش قرار می‌گیرند.
    </p>
    <a href="<?= url('student/plan.php') ?>" class="btn btn-sage btn-lg text-c" style="display:inline-flex;align-items:center;gap:10px;padding:14px 32px;font-weight:900;font-size:1.05rem">
      <?= icon('book',20) ?> شروع مطالعه‌ی مطالب جدید
    </a>
  <?php else: ?>
    <h3 id="esTitle" style="font-size:1.4۵rem;font-weight:900;margin-bottom:12px;color:var(--text-1)">هنوز هیچ آیتم مروری را به پایان نرسانده‌ای ✨</h3>
    <p id="esDesc" class="muted" style="max-width:520px;margin:0 auto 28px;font-size:.9۵rem;line-height:1.6">
      تمامی مرورهــایی که از تب «موعد امروز» انجام داده و تایید می‌کنی، در این تب ذخیره و بایگانی می‌شوند تا بتوانی روند تثبیت مطالب را بررسی کنی.
    </p>
    <a href="?scope=due" class="btn btn-gold btn-lg text-c" style="display:inline-flex;align-items:center;gap:10px;padding:14px 32px;font-weight:900;font-size:1.05rem">
      <?= icon('repeat',20) ?> مشاهده‌ی مرورهای امروز
    </a>
  <?php endif; ?>
</div>

<script>
window.API_REVIEWS = '<?= url('api/reviews.php') ?>';

// Interactive Front-end JS for Smart Search & Filtering
const searchInput = document.getElementById('reviewSearchInput');
const filterBtns  = document.querySelectorAll('.subj-filter-btn');
const cards       = document.querySelectorAll('.review-card');
const container   = document.getElementById('reviewCardsContainer');
const emptyState  = document.getElementById('reviewEmptyState');
const esTitle     = document.getElementById('esTitle');
const esDesc      = document.getElementById('esDesc');

let currentSubjFilter = 'all';

function runFilterAndSearch() {
  const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
  let visibleCount = 0;

  cards.forEach(card => {
    const cardSubj  = card.dataset.subject;
    const cardTitle = card.dataset.title;
    let matchSubj   = (currentSubjFilter === 'all') || (cardSubj === currentSubjFilter);
    let matchQuery  = (!query) || cardTitle.includes(query);

    if (matchSubj && matchQuery) {
      card.style.display = 'flex';
      visibleCount++;
    } else {
      card.style.display = 'none';
    }
  });

  if (visibleCount === 0) {
    if (container) container.style.display = 'none';
    if (emptyState) {
      emptyState.classList.remove('hidden');
      if (currentSubjFilter !== 'all' || query) {
        if (esTitle) esTitle.textContent = `هیچ مروری با فیلتر «${currentSubjFilter !== 'all' ? currentSubjFilter : query}» یافت نشد 🔍`;
        if (esDesc) esDesc.textContent = 'می‌توانی فیلتر درس را روی «همه درس‌ها» بگذاری یا متن جستجو را پاک کنی.';
      }
    }
  } else {
    if (container) container.style.display = 'grid';
    if (emptyState) emptyState.classList.add('hidden');
  }
}

searchInput?.addEventListener('input', runFilterAndSearch);

filterBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    filterBtns.forEach(b => {
      b.classList.remove('active', 'badge-sage');
      b.classList.add('badge');
      b.style.background = 'var(--surface-1)';
      b.style.color = 'var(--text-2)';
    });
    btn.classList.add('active', 'badge-sage');
    btn.style.background = '';
    btn.style.color = '';
    currentSubjFilter = btn.dataset.filter;
    runFilterAndSearch();
  });
});

// Real-time API calls for clicking action buttons
document.addEventListener('click', async e => {
  const card = e.target.closest('[data-review]'); 
  if (!card) return;
  const actionBtn = e.target.closest('[data-review-done]') || e.target.closest('[data-review-dismiss]');
  if (!actionBtn) return;
  
  const action  = actionBtn.hasAttribute('data-review-done') ? 'done' : 'dismiss';
  const quality = actionBtn.dataset.quality || 'good';
  
  // دکمه‌ها را غیرفعال کن تا دوباره کلیک نشود
  card.querySelectorAll('button').forEach(b => b.disabled = true);
  actionBtn.innerHTML = '<span class="spinner" style="width:14px;height:14px"></span>';

  try {
    await api(window.API_REVIEWS, { method: 'POST', body: { action, id: card.dataset.review, quality } });
    toast(action==='done'?(quality==='hard'?'مرور ثبت شد؛ یک مرور تقویتی هم برای فردا اضافه شد':'مرور مبحث با موفقیت ثبت شد'):'آیتم از لیست امروز کنار گذاشته شد', 'success');
    
    // انیمیشن خروج نرم و زیبا
    card.style.transform = 'scale(0.92) translateY(10px)';
    card.style.opacity = '0';
    
    setTimeout(() => {
      card.remove();
      runFilterAndSearch();
      
      // آپدیت آنی شمارشگر بالای صفحه
      const dueBadge = document.querySelector('.report-tabs .chip.active');
      if (dueBadge && '<?= $scope ?>' === 'due') {
        const remaining = document.querySelectorAll('#reviewCardsContainer .review-card').length;
        dueBadge.innerHTML = `<?= icon('bell',16) ?> موعد امروز / عقب‌افتاده (${faNum(remaining)})`;
        const statDue = document.querySelector('.stat-cards .stat .v');
        if (statDue) statDue.textContent = faNum(remaining);
      }
    }, 280);
  } catch(err) {
    toast(err.error || 'خطا در برقراری ارتباط با سرور', 'error');
    card.querySelectorAll('button').forEach(b => b.disabled = false);
    actionBtn.textContent = actionBtn.dataset.originalText || (action === 'done' ? 'مرور کردم' : '×');
  }
});
</script>
<?php panel_end(['student.js']); ?>
