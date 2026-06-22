<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
page_head('راهنمای نصب وب‌اپ', '', ['student.css']);
$back = is_logged_in() ? (user_role()==='student' ? 'student/dashboard.php' : 'admin/dashboard.php') : '';
?>
<main class="pwa-page-v2">
  <section class="pwa-hero-v2 panel">
    <div class="pwa-orb one"></div><div class="pwa-orb two"></div>
    <div class="pwa-hero-copy">
      <div class="pwa-logo-row"><?= logo_svg(62) ?><span class="pwa-kicker"><?= icon('phone',15) ?> نصب وب‌اپ مَدار</span></div>
      <h1>مَدار را مثل یک اپ واقعی روی گوشی نصب کن</h1>
      <p>بدون دانلود از مارکت؛ با آیکن اختصاصی، اجرای تمام‌صفحه، دسترسی سریع به برنامه و امکان فعال‌سازی اعلان‌های مرور.</p>
      <div class="pwa-actions-v2">
        <button class="btn btn-gold btn-lg" type="button" data-pwa-install><?= icon('pwa',18) ?> نصب مستقیم</button>
        <button class="btn btn-ghost btn-lg" type="button" data-notif-enable><?= icon('bell',18) ?> فعال‌سازی اعلان‌ها</button>
        <a class="btn btn-ghost btn-lg" href="<?= url($back) ?>"><?= icon('arrow-right',18) ?> بازگشت</a>
      </div>
      <div class="pwa-benefits">
        <span><?= icon('check-circle',15) ?> سریع‌تر از مرورگر</span>
        <span><?= icon('bell',15) ?> یادآوری مرورها</span>
        <span><?= icon('sparkles',15) ?> تجربه تمام‌صفحه</span>
      </div>
    </div>
    <div class="pwa-phone" aria-hidden="true">
      <div class="pwa-phone-top"></div>
      <div class="pwa-app-card"><b>برنامه امروز</b><span>۳ تسک آماده انجام</span><div class="mini-progress"><i style="width:68%"></i></div></div>
      <div class="pwa-app-row"><span>✓ زیست فصل ۴</span><b>کامل</b></div>
      <div class="pwa-app-row warn"><span>🔁 مرور شیمی</span><b>امروز</b></div>
      <div class="pwa-dock"><i></i><i></i><i></i><i></i></div>
    </div>
  </section>

  <section class="pwa-install-grid">
    <article class="panel pwa-step-card android">
      <span class="step-badge">Android</span>
      <div class="step-icon"><?= icon('phone',26) ?></div>
      <h3>Chrome / Samsung Internet</h3>
      <ol>
        <li>همین صفحه را در مرورگر باز کن.</li>
        <li>اگر دکمه «نصب مستقیم» فعال شد، روی آن بزن.</li>
        <li>اگر فعال نبود: منوی سه‌نقطه مرورگر را باز کن.</li>
        <li>گزینه <b>Add to Home screen</b> یا <b>Install app</b> را انتخاب کن.</li>
      </ol>
    </article>
    <article class="panel pwa-step-card ios">
      <span class="step-badge">iPhone</span>
      <div class="step-icon"><?= icon('arrow-left',26) ?></div>
      <h3>Safari</h3>
      <ol>
        <li>سایت را حتماً با Safari باز کن.</li>
        <li>دکمه Share پایین صفحه را بزن.</li>
        <li>گزینه <b>Add to Home Screen</b> را انتخاب کن.</li>
        <li>در صفحه بعد روی <b>Add</b> بزن.</li>
      </ol>
      <p class="hint-box">در iOS نصب مستقیم با دکمه محدود است؛ مسیر Share مطمئن‌ترین روش است.</p>
    </article>
    <article class="panel pwa-step-card desktop">
      <span class="step-badge">Desktop</span>
      <div class="step-icon"><?= icon('desktop',26) ?></div>
      <h3>Chrome / Edge</h3>
      <ol>
        <li>آیکن نصب کنار نوار آدرس را بزن.</li>
        <li>اگر آیکن را نمی‌بینی، از منوی مرورگر گزینه Install app را انتخاب کن.</li>
        <li>بعد از نصب، مَدار مثل یک برنامه مستقل باز می‌شود.</li>
      </ol>
    </article>
  </section>

  <section class="panel pwa-notice-v2">
    <div class="notice-icon"><?= icon('bell',24) ?></div>
    <div><h3>اعلان مرورها را فراموش نکن</h3><p>اگر اجازه اعلان را قبلاً رد کرده‌ای، از تنظیمات مرورگر بخش Notifications اجازه سایت مَدار را دوباره فعال کن تا مرورهای فاصله‌دار را از دست ندهی.</p></div>
  </section>
</main>
<style>
.pwa-page-v2{max-width:1120px;margin:0 auto;padding:28px;overflow:hidden}.pwa-hero-v2{position:relative;display:grid;grid-template-columns:1.35fr .65fr;gap:26px;align-items:center;min-height:390px;padding:34px;background:radial-gradient(circle at 12% 8%,rgba(203,172,128,.28),transparent 34%),radial-gradient(circle at 88% 12%,rgba(107,136,114,.25),transparent 32%),linear-gradient(145deg,var(--card),var(--surface));overflow:hidden}.pwa-orb{position:absolute;border-radius:50%;filter:blur(2px);opacity:.55;pointer-events:none}.pwa-orb.one{width:170px;height:170px;background:rgba(203,172,128,.16);right:-55px;bottom:-60px}.pwa-orb.two{width:120px;height:120px;background:rgba(107,136,114,.18);left:35%;top:-45px}.pwa-hero-copy{position:relative;z-index:2}.pwa-logo-row{display:flex;gap:12px;align-items:center;margin-bottom:18px}.pwa-kicker{display:inline-flex;gap:7px;align-items:center;border:1px solid rgba(203,172,128,.28);background:rgba(203,172,128,.10);color:var(--gold-light);border-radius:999px;padding:7px 12px;font-weight:900;font-size:.82rem}.pwa-hero-v2 h1{font-size:clamp(2rem,4vw,3.2rem);line-height:1.2;margin:0 0 14px}.pwa-hero-v2 p{color:var(--text-2);font-size:1.05rem;line-height:2;max-width:650px;margin:0}.pwa-actions-v2{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.pwa-benefits{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.pwa-benefits span{display:inline-flex;align-items:center;gap:6px;background:var(--surface-2);border:1px solid var(--border-soft);border-radius:999px;padding:7px 11px;color:var(--text-2);font-size:.82rem;font-weight:800}.pwa-phone{position:relative;z-index:2;width:min(270px,100%);height:350px;margin:auto;border:10px solid #0a120f;border-radius:38px;background:linear-gradient(180deg,#203028,#101a16);box-shadow:0 30px 80px rgba(0,0,0,.35),inset 0 0 0 1px rgba(255,255,255,.08);padding:32px 16px 16px}.pwa-phone-top{position:absolute;top:10px;right:50%;transform:translateX(50%);width:74px;height:7px;border-radius:999px;background:rgba(255,255,255,.16)}.pwa-app-card{border-radius:22px;padding:16px;background:linear-gradient(135deg,#e0c595,#b2945f);color:#142018;margin-bottom:12px}.pwa-app-card b,.pwa-app-card span{display:block}.pwa-app-card span{font-size:.78rem;opacity:.75;margin-top:3px}.mini-progress{height:8px;background:rgba(20,32,24,.22);border-radius:999px;margin-top:13px;overflow:hidden}.mini-progress i{display:block;height:100%;background:#142018;border-radius:999px}.pwa-app-row{display:flex;justify-content:space-between;gap:8px;padding:12px;border-radius:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.08);font-size:.78rem;margin-bottom:9px}.pwa-app-row b{color:var(--sage-light)}.pwa-app-row.warn b{color:var(--gold-light)}.pwa-dock{position:absolute;left:18px;right:18px;bottom:16px;height:48px;border-radius:18px;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:space-around}.pwa-dock i{width:22px;height:22px;border-radius:8px;background:rgba(255,255,255,.16)}.pwa-install-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:18px 0}.pwa-step-card{position:relative;overflow:hidden;padding:22px}.step-badge{display:inline-flex;background:var(--gold-glass);border:1px solid rgba(203,172,128,.24);color:var(--gold-light);border-radius:999px;padding:4px 10px;font-size:.76rem;font-weight:900}.step-icon{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;background:var(--sage-glass);color:var(--sage-light);margin:16px 0 12px}.pwa-step-card h3{margin:0 0 12px}.pwa-step-card ol{counter-reset:pwa;margin:0;padding:0;list-style:none;display:grid;gap:9px}.pwa-step-card li{position:relative;padding-right:34px;color:var(--text-2);line-height:1.8}.pwa-step-card li::before{counter-increment:pwa;content:counter(pwa);position:absolute;right:0;top:2px;width:24px;height:24px;border-radius:9px;display:grid;place-items:center;background:var(--surface-3);color:var(--gold-light);font-weight:900;font-size:.75rem}.hint-box{margin:14px 0 0;padding:10px 12px;border-radius:14px;background:rgba(217,178,95,.08);border:1px solid rgba(217,178,95,.20);color:var(--text-2);font-size:.82rem;line-height:1.8}.pwa-notice-v2{display:flex;gap:14px;align-items:flex-start;background:linear-gradient(135deg,rgba(107,136,114,.12),var(--card))}.notice-icon{width:54px;height:54px;border-radius:18px;display:grid;place-items:center;background:var(--gold-glass);color:var(--gold-light);flex-shrink:0}.pwa-notice-v2 h3{margin:0 0 6px}.pwa-notice-v2 p{margin:0;color:var(--text-2);line-height:1.9}[data-pwa-install]:not(.ready)::after{content:' (اگر پشتیبانی شود)';font-size:.72rem;opacity:.75}@media(max-width:900px){.pwa-hero-v2{grid-template-columns:1fr}.pwa-phone{display:none}.pwa-install-grid{grid-template-columns:1fr}}@media(max-width:620px){.pwa-page-v2{padding:16px}.pwa-hero-v2{padding:24px}.pwa-actions-v2 .btn{width:100%}.pwa-benefits span{width:100%;justify-content:center}.pwa-notice-v2{flex-direction:column}}
</style>
<?php page_foot(); ?>
