<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
page_head('تماس با ما', 'راه‌های ارتباط با سامانه مَدار و پشتیبانی خدمات آموزشی', ['public.css']);
?>
<nav class="public-nav"><div class="inner"><?= brand_block() ?><div class="public-links"><a href="<?= url('') ?>">خانه</a><a href="<?= url('services.php') ?>">خدمات</a><a href="<?= url('about.php') ?>">درباره ما</a><a class="active" href="<?= url('contact.php') ?>">تماس با ما</a><a href="<?= url('auth/login.php') ?>">ورود</a></div></div></nav>
<header class="public-hero"><div class="public-container"><span class="public-badge"><?= icon('phone',16) ?> تماس با ما</span><h1 class="public-title">ارتباط با مَدار</h1><p class="public-lead">برای پیگیری امور مربوط به سامانه، خدمات آموزشی، حساب کاربری، جلسات و پیام‌های اطلاع‌رسانی می‌توانید از راه‌های زیر با ما در ارتباط باشید.</p></div></header>
<main>
<section class="public-section"><div class="public-container contact-box">
  <div class="public-card"><h2>اطلاعات تماس</h2><div class="public-kv">
    <div><span>نام سامانه</span><b><?= e(APP_NAME) ?> · Madar Study OS</b></div>
    <div><span>مالک / مسئول آموزشی</span><b style="direction:rtl;text-align:right"><?= e(APP_OWNER) ?></b></div>
    <div><span>وب‌سایت</span><b>madaar-edu.ir</b></div>
    <div><span>ایمیل پشتیبانی</span><b>info@madaar-edu.ir</b></div>
    <div><span>موضوع فعالیت</span><b style="direction:rtl;text-align:right">سامانه برنامه‌ریزی و پیگیری آموزشی کنکور</b></div>
    <div><span>محدوده فعالیت</span><b style="direction:rtl;text-align:right">ایران · خدمات آنلاین آموزشی</b></div>
  </div><p style="margin-top:14px;color:var(--text-2);line-height:2">کاربران دارای حساب می‌توانند از بخش پیام‌ها در پنل کاربری نیز با مشاور یا پشتیبانی آموزشی در ارتباط باشند.</p></div>
  <div class="public-card"><h2>ارسال درخواست</h2><form class="contact-form" action="mailto:info@madaar-edu.ir" method="post" enctype="text/plain"><input name="name" placeholder="نام و نام خانوادگی"><input name="phone" placeholder="شماره تماس" dir="ltr"><input name="subject" placeholder="موضوع پیام"><textarea name="message" placeholder="متن پیام"></textarea><button class="btn" type="submit">ارسال از طریق ایمیل</button></form><p class="muted" style="font-size:.82rem;line-height:1.8;margin-top:10px">در صورت باز نشدن نرم‌افزار ایمیل، پیام خود را مستقیم به info@madaar-edu.ir ارسال کنید.</p></div>
</div></section>
<section class="public-section"><div class="public-container"><div class="public-card"><h2>ساعات پاسخ‌گویی</h2><p>درخواست‌های مرتبط با حساب کاربری، برنامه‌ریزی، جلسات، پیامک‌های اطلاع‌رسانی و پشتیبانی فنی در اولین فرصت بررسی می‌شود. پاسخ‌گویی معمولاً در روزهای کاری انجام می‌گیرد.</p></div></div></section>
</main>
<footer class="public-footer"><div class="inner"><span>© <?= fa_num(date('Y')) ?> <?= e(APP_NAME) ?> · <?= e(APP_OWNER) ?></span><span><a href="<?= url('services.php') ?>">خدمات</a><a href="<?= url('about.php') ?>">درباره ما</a></span></div></footer>
<?php page_foot(); ?>
