<?php
require_once __DIR__ . '/includes/layout.php';
page_head('آفلاین');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px;text-align:center">
  <div class="reveal in" style="max-width:460px">
    <div class="brand" style="justify-content:center;margin-bottom:26px"><?= logo_svg(46) ?></div>
    <div class="icon-tile sage float" style="width:96px;height:96px;border-radius:50%;margin:0 auto 20px"><?= icon('wifi-off',44) ?></div>
    <h1 style="font-size:1.6rem;margin-bottom:10px">اتصال اینترنت قطع است</h1>
    <p class="muted" style="margin-bottom:28px">به نظر می‌رسد آفلاین هستید. نگران نباشید — به‌محض برقراری اتصال، همه‌چیز همگام‌سازی می‌شود.</p>
    <button onclick="location.reload()" class="btn btn-gold btn-lg"><?= icon('repeat',18) ?> تلاش دوباره</button>
  </div>
</div>
<script>
  window.addEventListener('online', ()=>location.reload());
  setInterval(()=>{ if(navigator.onLine) location.reload(); }, 5000);
</script>
<?php page_foot(); ?>
