<?php
require_once __DIR__ . '/includes/layout.php';
if (function_exists('boot_session')) { } 
http_response_code(404);
page_head('صفحه پیدا نشد');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px;text-align:center">
  <div class="reveal in" style="max-width:480px">
    <div class="brand" style="justify-content:center;margin-bottom:26px"><?= logo_svg(46) ?></div>
    <div style="font-size:clamp(5rem,18vw,9rem);font-weight:800;line-height:1" class="gradient-text">۴۰۴</div>
    <h1 style="font-size:1.5rem;margin:10px 0">اوه! این صفحه پیدا نشد</h1>
    <p class="muted" style="margin-bottom:28px">ببخشید، صفحه‌ای که دنبالش بودید وجود ندارد یا جابه‌جا شده. بیایید به مسیر اصلی برگردیم.</p>
    <div class="flex gap-3" style="justify-content:center">
      <a href="<?= url('') ?>" class="btn btn-gold btn-lg"><?= icon('home',18) ?> صفحه اصلی</a>
      <a href="javascript:history.back()" class="btn btn-ghost btn-lg"><?= icon('arrow-right',16) ?> بازگشت</a>
    </div>
  </div>
</div>
<?php page_foot(); ?>
