<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');

panel_start('جلسات آنلاین', 'این بخش به‌زودی فعال می‌شود', 'student', 'online_sessions', ['student.css']);
?>
<style>
.coming-wrap{min-height:62vh;display:grid;place-items:center;padding:24px}.coming-card{width:min(820px,100%);text-align:center;background:radial-gradient(circle at 20% 0%,rgba(111,155,192,.20),transparent 36%),linear-gradient(160deg,var(--card),var(--surface));border:1px solid rgba(111,155,192,.32);border-radius:28px;padding:42px 28px;box-shadow:0 22px 70px rgba(0,0,0,.34)}.coming-ico{width:88px;height:88px;border-radius:28px;margin:0 auto 22px;display:grid;place-items:center;background:rgba(111,155,192,.18);color:#a0d2eb;border:1px solid rgba(111,155,192,.34)}.coming-card h1{font-size:1.5rem;font-weight:1000;color:var(--text-1);margin:0 0 10px}.coming-card p{max-width:620px;margin:0 auto;color:var(--text-2);line-height:2;font-size:.95rem}.coming-badges{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:24px}.coming-badges span{padding:8px 14px;border-radius:999px;background:var(--surface-2);border:1px solid var(--border-soft);color:var(--text-2);font-weight:850;font-size:.84rem}
</style>
<div class="coming-wrap"><section class="coming-card"><div class="coming-ico"><?= icon('video',42) ?></div><span class="badge" style="background:rgba(111,155,192,.18);color:#a0d2eb;border:1px solid rgba(111,155,192,.32);font-weight:900">Coming Soon</span><h1>جلسات آنلاین به‌زودی فعال می‌شود</h1><p>امکان ورود به کلاس و جلسه آنلاین در حال آماده‌سازی نهایی است. زمان جلسات مشاوره شما همچنان از بخش «جلسات من» قابل مشاهده است.</p><div class="coming-badges"><span>کلاس آنلاین</span><span>تخته آموزشی</span><span>چت جلسه</span><span>ورود امن</span></div><div style="margin-top:26px"><a href="<?= url('student/meetings.php') ?>" class="btn btn-gold btn-lg"><?= icon('calendar',18) ?> مشاهده جلسات من</a></div></section></div>
<?php panel_end(); ?>
