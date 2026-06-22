<?php
/**
 * مَدار · Madar Study OS — Activity Logging Panel
 * -------------------------------------------------------------------
 * اختصاصی دکتر سجاد صیادی (مشاور ارشد / مالک سامانه)
 * نمایش کاملاً خوانا، بدون نمایش کدهای خام و با استایل لوکس مَدار
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/log.php';

boot_session();
require_chief_advisor(); // فقط دکتر سجاد صیادی دسترسی دارد

$u = current_user();
$adminId = (int)$u['id'];

// عملیات پاکسازی لاگ‌ها
if (($_POST['action'] ?? '') === 'purge_logs') {
    require_csrf();
    $months = (int)($_POST['months'] ?? 6);
    if ($months > 0) {
        $st = db()->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? MONTH)");
        $st->execute([$months]);
        $deleted = $st->rowCount();
        log_activity($adminId, 'settings_updated', 'system', null, ['پاکسازی لاگ‌های قدیمی' => "قدیمی‌تر از $months ماه", 'تعداد حذف‌شده' => $deleted]);
        redirect('admin/logs.php?purged=' . $deleted);
    }
}

// فیلترها
$selectedCat  = $_GET['category'] ?? 'all';
$selectedUser = (int)($_GET['user_id'] ?? 0);
$searchQ      = trim((string)($_GET['q'] ?? ''));
$limit        = (int)($_GET['limit'] ?? 100);
if (!in_array($limit, [50, 100, 200, 500])) $limit = 100;

// دریافت لاگ‌ها با فیلتر
$logs = get_recent_logs($limit, null, $selectedUser ?: null, $selectedCat, $searchQ);

// دریافت لیست کاربران جهت فیلتر دراپ‌داون
$logUsers = db()->query("
    SELECT DISTINCT u.id, u.full_name, u.role, u.username 
    FROM activity_logs l
    JOIN users u ON u.id = l.user_id
    ORDER BY u.role, u.full_name
")->fetchAll();

// آمار کلی
$totalLogsCount = (int)db()->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$todayLogsCount = (int)db()->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$authLogsCount  = (int)db()->query("SELECT COUNT(*) FROM activity_logs WHERE action IN ('user_login','login_failed','user_logout')")->fetchColumn();

panel_start('مرکز مانیتورینگ و رهگیری فعالیت‌ها', 'نظارت شفاف و دقیق بر تمامی رخدادهای سامانه (بدون نمایش کدهای خام)', 'admin', 'logs');
?>

<!-- استایل‌های اختصاصی تکمیلی برای شکوه و خوانایی بی‌نقص صفحه لاگ‌ها -->
<style>
.master-security-header {
  background: linear-gradient(135deg, var(--surface-2), var(--surface));
  border: 1px solid var(--gold);
  border-radius: var(--r-lg);
  padding: 32px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.45);
  position: relative;
  overflow: hidden;
}
.master-security-header::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
  background: var(--grad-gold);
}
.adv-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 12px; border-radius: var(--r-pill); font-size: .78rem; font-weight: 800;
}
.adv-badge.gold { background: var(--gold-glass); color: var(--gold-light); border: 1px solid rgba(203,172,128,0.3); }
.adv-badge.sage { background: var(--sage-glass); color: var(--sage-light); border: 1px solid rgba(107,136,114,0.3); }
.adv-badge.danger { background: rgba(217,116,116,0.15); color: var(--danger); border: 1px solid rgba(217,116,116,0.3); }
.adv-badge.info { background: rgba(111,155,192,0.15); color: var(--info); border: 1px solid rgba(111,155,192,0.3); }
.adv-badge.warn { background: rgba(217,178,95,0.15); color: var(--warn); border: 1px solid rgba(217,178,95,0.3); }

.search-filter-bar {
  background: var(--surface-1);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  padding: 20px 24px;
}
.logs-table-card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: var(--r-lg);
  padding: 0;
  overflow: hidden;
  box-shadow: var(--sh-md);
}

.modal-custom-body { padding: 32px; background: var(--surface); color: var(--text); }
</style>

<!-- 1. Header -->
<div class="master-security-header mb-6">
  <div class="between wrap gap-4">
    <div>
      <span class="adv-badge gold mb-3"><?= icon('shield', 15) ?> مرکز مانیتورینگ امنیتی مشاور ارشد (دکتر سجاد صیادی)</span>
      <h1 class="display" style="font-size: clamp(1.8rem, 4vw, 2.4rem);">رهگیری فعالیت کاربران و سیستم</h1>
      <p class="lead mt-1" style="font-size: 1.05rem;">تمامی رخدادهای حیاتی از جمله ورود و خروج، ویرایش برنامه‌ها، تصحیح آزمون‌ها و تغییر دسترسی‌ها به‌صورت خودکار و کاملاً خوانا ثبت و رهگیری می‌شوند.</p>
    </div>
    
    <div class="flex items-center gap-3 wrap">
      <button onclick="openPurgeModal()" class="btn btn-ghost btn-lg" style="color: var(--danger); border-color: var(--danger);">
        <?= icon('trash', 20) ?> <span>پاکسازی لاگ‌ها</span>
      </button>
      <a href="?limit=<?= $limit === 500 ? 50 : ($limit === 100 ? 200 : 500) ?>" class="btn btn-gold btn-lg" style="font-weight: 900;">
        <?= icon('refresh', 20) ?> <span>نمایش <?= fa_num($limit) ?> مورد</span>
      </a>
    </div>
  </div>
</div>

<!-- 2. Statistics Grid -->
<div class="stat-cards grid gap-4 mb-6" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
  <div class="card stat">
    <div class="icon-tile sage" style="width: 56px; height: 56px; border-radius: 16px;"><?= icon('database', 28) ?></div>
    <div>
      <div class="v" style="font-size: 2rem; color: var(--text);"><?= fa_num($totalLogsCount) ?> <span style="font-size: 1rem; font-weight: normal; color: var(--text-3);">عملیات</span></div>
      <div class="k" style="font-size: .9۵rem; font-weight: 700; mt-1">کل رخدادهای ثبت‌شده</div>
    </div>
  </div>
  
  <div class="card stat">
    <div class="icon-tile" style="width: 56px; height: 56px; border-radius: 16px; background: rgba(217,178,95,0.15); color: var(--warn);"><?= icon('fire', 28) ?></div>
    <div>
      <div class="v" style="font-size: 2rem; color: var(--warn);"><?= fa_num($todayLogsCount) ?> <span style="font-size: 1rem; font-weight: normal; color: var(--text-3);">مورد</span></div>
      <div class="k" style="font-size: .9۵rem; font-weight: 700; mt-1">فعالیت‌های امروز</div>
    </div>
  </div>

  <div class="card stat">
    <div class="icon-tile" style="width: 56px; height: 56px; border-radius: 16px; background: rgba(111,155,192,0.15); color: var(--info);"><?= icon('lock', 28) ?></div>
    <div>
      <div class="v" style="font-size: 2rem; color: var(--info);"><?= fa_num($authLogsCount) ?> <span style="font-size: 1rem; font-weight: normal; color: var(--text-3);">مورد</span></div>
      <div class="k" style="font-size: .9۵rem; font-weight: 700; mt-1">لاگ‌های احراز هویت</div>
    </div>
  </div>
</div>

<!-- Alert for Purges -->
<?php if (isset($_GET['purged'])): ?>
  <div class="card glass mb-6" style="border-color: var(--success); background: rgba(95,174,123,0.15); color: var(--text); padding: 16px 24px;">
    <div class="flex items-center gap-3 font-bold text-base">
      <span style="color: var(--success);"><?= icon('check-circle', 24) ?></span>
      <span>پاکسازی با موفقیت انجام شد. <?= fa_num((int)$_GET['purged']) ?> رخداد قدیمی از سیستم حذف گردید.</span>
    </div>
  </div>
<?php endif; ?>

<!-- 3. Control & Search Filters Container -->
<div class="search-filter-bar mb-6 between wrap gap-4">
  <div class="input-group" style="flex: 1; min-width: 280px; max-width: 420px;">
    <span class="ig-icon"><?= icon('search', 20) ?></span>
    <input type="text" id="liveLogSearch" onkeyup="filterLogRows()" placeholder="جستجوی آنی در عملیات، جزئیات، نام کاربر یا IP..." class="input" style="height: 48px; font-weight: bold; font-size: .95rem;">
  </div>

  <form method="get" class="flex items-center gap-3 wrap">
    <?php if ($searchQ): ?><input type="hidden" name="q" value="<?= e($searchQ) ?>"><?php endif; ?>
    <input type="hidden" name="limit" value="<?= $limit ?>">

    <select name="category" onchange="this.form.submit()" class="select" style="width: auto; min-width: 200px; height: 48px; font-weight: bold; font-size: .95rem;">
      <option value="all" <?= $selectedCat === 'all' ? 'selected' : '' ?>>📂 همه‌ی دسته‌بندی‌ها</option>
      <option value="auth" <?= $selectedCat === 'auth' ? 'selected' : '' ?>>🔐 ورود و خروج</option>
      <option value="users" <?= $selectedCat === 'users' ? 'selected' : '' ?>>👥 مدیریت کاربران</option>
      <option value="plans" <?= $selectedCat === 'plans' ? 'selected' : '' ?>>📅 برنامه‌ریزی تحصیلی</option>
      <option value="study" <?= $selectedCat === 'study' ? 'selected' : '' ?>>📝 وضعیت مطالعه و Mood</option>
      <option value="exams" <?= $selectedCat === 'exams' ? 'selected' : '' ?>>🏆 آزمون و سنجش</option>
      <option value="messages" <?= $selectedCat === 'messages' ? 'selected' : '' ?>>💬 پیام‌ها و ارتباطات</option>
      <option value="system" <?= $selectedCat === 'system' ? 'selected' : '' ?>>⚙️ تنظیمات و سیستم</option>
      <option value="achieves" <?= $selectedCat === 'achieves' ? 'selected' : '' ?>>🎖️ دستاوردها</option>
    </select>

    <select name="user_id" onchange="this.form.submit()" class="select" style="width: auto; min-width: 200px; height: 48px; font-weight: bold; font-size: .95rem;">
      <option value="0">👤 همه‌ی کاربران</option>
      <?php foreach ($logUsers as $lu): ?>
        <option value="<?= $lu['id'] ?>" <?= $selectedUser === (int)$lu['id'] ? 'selected' : '' ?>>
          <?= $lu['role'] === 'admin' ? '👑' : ($lu['role'] === 'advisor' ? '🛡️' : '🎓') ?>
          <?= e($lu['full_name']) ?> (<?= e($lu['username']) ?>)
        </option>
      <?php endforeach; ?>
    </select>

    <?php if ($selectedCat !== 'all' || $selectedUser !== 0 || $searchQ): ?>
      <a href="admin/logs.php" class="btn btn-ghost btn-sm" style="color: var(--danger); border-color: var(--danger); height: 48px; font-weight: bold;">✕ لغو فیلتر</a>
    <?php endif; ?>
  </form>
</div>

<!-- 4. Main Activity Logs Table Card -->
<div class="logs-table-card">
  <div class="table-wrap" style="overflow-x: auto;">
    <table class="tbl" id="logsTable" style="min-width: 880px;">
      <thead>
        <tr style="background: var(--surface-2); border-bottom: 2px solid var(--border);">
          <th style="padding: 16px 24px; width: 170px; font-size: .9rem;">تاریخ و زمان</th>
          <th style="padding: 16px 24px; width: 250px; font-size: .9rem;">کاربر عامل</th>
          <th style="padding: 16px 24px; width: 260px; font-size: .9rem;">نوع عملیات</th>
          <th style="padding: 16px 24px; font-size: .9rem;">توضیحات و جزئیات تکمیلی (خوانا)</th>
          <th style="padding: 16px 24px; text-align: left; width: 220px; font-size: .9rem;">مشخصات شبکه</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr>
            <td colspan="5" class="text-c muted" style="padding: 64px 24px;">
              <div class="center mb-4"><?= icon('list', 56, 'muted opacity-40') ?></div>
              <p style="font-size: 1.1rem; font-weight: bold;">هیچ رخدادی با فیلترهای انتخابی یافت نشد.</p>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $log): 
              $hum = parse_human_log($log);
              
              $badgeType = match($hum['category_color']) {
                  'gold'   => 'gold',
                  'sage'   => 'sage',
                  'danger' => 'danger',
                  'warn'   => 'warn',
                  'info'   => 'info',
                  default  => 'sage'
              };

              $roleName = match($log['user_role'] ?? '') {
                  'admin'   => 'مشاور ارشد (مالک)',
                  'advisor' => 'مشاور تحصیلی',
                  'student' => match($log['user_field'] ?? '') {
                      'تجربی'  => 'دانش‌آموز تجربی',
                      'ریاضی'  => 'دانش‌آموز ریاضی',
                      'انسانی' => 'دانش‌آموز انسانی',
                      default => 'دانش‌آموز'
                  },
                  default   => 'سیستم'
              };
              $roleBadge = match($log['user_role'] ?? '') {
                  'admin'   => 'gold',
                  'advisor' => 'info',
                  'student' => 'sage',
                  default   => ''
              };

              // تشخیص خلاصه User Agent
              $ua = $log['user_agent'] ?? '';
              $browser = 'مرورگر';
              if (str_contains($ua, 'Chrome')) $browser = 'Chrome';
              elseif (str_contains($ua, 'Firefox')) $browser = 'Firefox';
              elseif (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) $browser = 'Safari';
              elseif (str_contains($ua, 'Edge')) $browser = 'Edge';

              $os = 'سیستم';
              if (str_contains($ua, 'Windows')) $os = 'Windows';
              elseif (str_contains($ua, 'Android')) $os = 'Android';
              elseif (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) $os = 'Mac / iOS';
              elseif (str_contains($ua, 'Linux')) $os = 'Linux';
          ?>
          <tr class="log-row"
              data-search="<?= e($log['user_name'] . ' ' . $log['user_username'] . ' ' . $hum['persian_action'] . ' ' . ($log['details'] ?? '') . ' ' . ($log['ip_address'] ?? '')) ?>">
            
            <!-- 1. زمان -->
            <td style="padding: 18px 24px; direction: rtl;">
              <div style="font-weight: 800; font-size: 1.02rem; color: var(--text);"><?= jalali_date($log['created_at'], true) ?></div>
              <div class="muted mt-0.5" style="font-size: .8rem;"><?= jalali_date($log['created_at']) ?></div>
            </td>

            <!-- 2. کاربر -->
            <td style="padding: 18px 24px;">
              <div class="u-row">
                <div class="u-ava <?= $roleBadge ?>" style="width: 42px; height: 42px; font-size: 1rem;">
                  <?= e(avatar_letters($log['user_name'] ?? 'س')) ?>
                </div>
                <div>
                  <div style="font-weight: 800; font-size: 1.05rem; color: var(--text);">
                    <?= e($log['user_name'] ?? 'سیستم / ناشناس') ?>
                  </div>
                  <div class="flex items-center gap-2 mt-1 wrap">
                    <span class="adv-badge <?= $roleBadge ?>" style="padding: 2px 8px; font-size: .7rem;"><?= e($roleName) ?></span>
                    <?php if (!empty($log['user_username'])): ?>
                      <code class="muted font-normal dir-ltr" style="font-size: .75rem;">(<?= e($log['user_username']) ?>)</code>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </td>

            <!-- 3. عملیات -->
            <td style="padding: 18px 24px;">
              <div class="adv-badge <?= $badgeType ?> mb-1.5" style="padding: 3px 10px; font-size: .75rem;">
                <?= icon($hum['category_icon'], 14) ?>
                <span><?= e($hum['category_name']) ?></span>
              </div>
              <div style="font-weight: 900; font-size: 1.02rem; color: var(--text);">
                <?= e($hum['persian_action']) ?>
              </div>
            </td>

            <!-- 4. جزئیات -->
            <td style="padding: 18px 24px; line-height: 1.8;">
              <div class="flex wrap gap-2" style="align-items: center;">
                <?= $hum['rich_details_html'] ?>
              </div>
            </td>

            <!-- 5. شبکه -->
            <td style="padding: 18px 24px; text-align: left; direction: ltr; font-family: monospace;">
              <div style="background: var(--surface-3); color: var(--gold-light); padding: 3px 8px; border-radius: 6px; font-size: .88rem; font-weight: bold; display: inline-block; margin-bottom: 4px;" title="آدرس IP">
                <?= e($log['ip_address'] ?? '127.0.0.1') ?>
              </div>
              <?php if ($ua): ?>
                <div class="muted font-sans flex items-center justify-end gap-1.5" style="font-size: .78rem;" title="<?= e($ua) ?>">
                  <?= icon('desktop', 14) ?>
                  <span><?= e($browser) ?> • <?= e($os) ?></span>
                </div>
              <?php endif; ?>
            </td>

          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Footer Note -->
  <div class="between wrap gap-3" style="padding: 16px 24px; background: var(--surface-2); border-top: 1px solid var(--border); font-size: .85rem; color: var(--text-3); font-weight: bold;">
    <div>نمایش <?= fa_num(count($logs)) ?> رخداد سیستم بر اساس فیلترهای فعال</div>
    <div class="flex items-center gap-3">
      <span>راهنمای رنگی:</span>
      <span class="adv-badge gold">تغییرات مدیریتی</span>
      <span class="adv-badge sage">روند تحصیلی</span>
      <span class="adv-badge danger">خطا / مسدودسازی</span>
    </div>
  </div>
</div>

<!-- Modal پاکسازی لاگ‌ها -->
<div class="modal-backdrop" id="purgeModal">
  <div class="modal" style="max-width: 520px; padding: 0; overflow: hidden; background: var(--surface);">
    <div class="between" style="padding: 24px 32px; background: rgba(217,116,116,0.25); border-bottom: 1px solid var(--danger);">
      <h3 style="font-size: 1.35rem; color: var(--danger); font-weight: 900; display: flex; align-items: center; gap: 10px;">
        <?= icon('trash', 26) ?> پاکسازی لاگ‌های قدیمی سیستم
      </h3>
      <button class="modal-close" onclick="closeModal('purgeModal')" style="width: 36px; height: 36px;"><?= icon('close', 18) ?></button>
    </div>

    <form method="post" class="modal-custom-body grid gap-4">
      <input type="hidden" name="action" value="purge_logs">
      <?= csrf_field(); ?>

      <div class="field m-0">
        <label style="font-size: .95rem; color: var(--text); mb-2">حذف همیشگی رخدادهای قدیمی‌تر از:</label>
        <select name="months" class="select" style="height: 48px; font-weight: bold; font-size: 1rem;">
          <option value="3">۳ ماه گذشته (حفظ فصلی)</option>
          <option value="6" selected>۶ ماه گذشته (پیش‌فرض استاندارد)</option>
          <option value="12">۱ سال گذشته (بایگانی سالانه)</option>
          <option value="24">۲ سال گذشته</option>
        </select>
        <p class="muted mt-3 leading-relaxed" style="font-size: .88rem;">با تایید و اجرای این عملیات، تمامی رخدادهای ثبت‌شده که تاریخ وقوع آن‌ها قدیمی‌تر از بازه انتخابی باشد، به‌طور دائم و غیرقابل‌بازگشت از پایگاه داده حذف خواهند شد.</p>
      </div>

      <div class="between pt-4 mt-2" style="border-top: 1px solid var(--border-soft); justify-content: flex-end; gap: 12px;">
        <button type="button" onclick="closeModal('purgeModal')" class="btn btn-ghost px-6">انصراف</button>
        <button type="submit" class="btn px-8 py-3" style="background: var(--danger); color: #fff; font-weight: 900;">تایید و حذف قطعی</button>
      </div>
    </form>
  </div>
</div>

<script>
// جستجوی بلادرنگ در جدول لاگ‌ها
function filterLogRows() {
    const q = document.getElementById('liveLogSearch').value.trim().toLowerCase();
    const rows = document.querySelectorAll('.log-row');

    rows.forEach(row => {
        const txt = row.dataset.search.toLowerCase();
        row.style.display = txt.includes(q) ? '' : 'none';
    });
}

function openPurgeModal() {
    document.getElementById('purgeModal').classList.add('open');
}
window.closeModal = function(id) {
    const el = typeof id === 'string' ? document.getElementById(id) : id;
    if (el) el.classList.remove('open');
};
</script>

<?php panel_end(); ?>