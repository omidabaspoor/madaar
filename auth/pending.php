<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
boot_session();
require_login();
$u = current_user();
// اگر تأیید شده، مستقیم به داشبورد
if ($u['role'] === 'student' && $u['status'] === 'active') redirect('student/dashboard.php');
if (in_array($u['role'], ['advisor','admin'], true)) redirect('admin/dashboard.php');

page_head('در انتظار تأیید', '', ['auth.css']);
?>
<div class="pending-screen">
  <div class="card pending-card reveal in">
    <div class="pending-icon"><?= icon('clock',40) ?></div>
    <h1 style="font-size:1.6rem;margin-bottom:10px"><?= e(explode(' ', (string)$u['full_name'])[0]) ?> عزیز، خوش آمدی! 👋</h1>
    <p class="lead" style="margin-bottom:8px">حساب شما با موفقیت ساخته شد.</p>
    <p class="muted" style="margin-bottom:24px">اکنون منتظر <strong class="gold">تأیید مشاور</strong> هستید. به‌محض تأیید، برنامه‌ی هفتگی شما فعال می‌شود و می‌توانید وارد داشبورد شوید.</p>
    <div class="alert alert-info" style="text-align:right"><?= icon('info',18) ?><span>این صفحه هر ۲۰ ثانیه به‌صورت خودکار وضعیت را بررسی می‌کند.</span></div>
    <div class="flex gap-3" style="justify-content:center;margin-top:20px">
      <a href="<?= url('auth/pending.php') ?>" class="btn btn-gold"><?= icon('repeat',16) ?> بررسی مجدد</a>
      <a href="<?= url('auth/logout.php') ?>" class="btn btn-ghost"><?= icon('logout',16) ?> خروج</a>
    </div>
  </div>
</div>
<script>
  // بررسی خودکار وضعیت تأیید
  setInterval(async () => {
    try {
      const r = await fetch('<?= url('api/check_status.php') ?>');
      const d = await r.json();
      if (d.status === 'active') { toast('حساب شما تأیید شد! در حال انتقال…','success'); setTimeout(()=>location.href='<?= url('student/dashboard.php') ?>',1200); }
    } catch(e){}
  }, 20000);
</script>
<?php page_foot(); ?>
