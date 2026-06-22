<?php
require_once __DIR__ . '/includes/layout.php';
http_response_code(403);
page_head('دسترسی غیرمجاز');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px;text-align:center">
  <div class="reveal in" style="max-width:480px">
    <div class="brand" style="justify-content:center;margin-bottom:26px"><?= logo_svg(46) ?></div>
    <div class="icon-tile" style="width:90px;height:90px;border-radius:50%;margin:0 auto 18px"><?= icon('lock',42) ?></div>
    <div style="font-size:clamp(3.5rem,12vw,6rem);font-weight:800;line-height:1" class="gradient-text">۴۰۳</div>
    <h1 style="font-size:1.5rem;margin:10px 0">دسترسی غیرمجاز</h1>
    <p class="muted" style="margin-bottom:28px">با احترام، شما اجازه‌ی دیدن این بخش را ندارید. اگر فکر می‌کنید اشتباهی پیش آمده، با مشاورتان در ارتباط باشید.</p>
    <div class="flex gap-3" style="justify-content:center">
      <a href="<?= url('') ?>" class="btn btn-gold btn-lg"><?= icon('home',18) ?> صفحه اصلی</a>
      <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost btn-lg"><?= icon('login',16) ?> ورود</a>
    </div>
  </div>
</div>
<?php page_foot(); ?>
