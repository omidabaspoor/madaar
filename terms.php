<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
page_head('قوانین استفاده', 'قوانین و شرایط استفاده از خدمات سامانه مَدار', ['public.css']);
?>
<nav class="public-nav"><div class="inner"><?= brand_block() ?><div class="public-links"><a href="<?= url('') ?>">خانه</a><a href="<?= url('services.php') ?>">خدمات</a><a href="<?= url('about.php') ?>">درباره ما</a><a href="<?= url('contact.php') ?>">تماس با ما</a><a class="active" href="<?= url('terms.php') ?>">قوانین</a></div></div></nav>
<header class="public-hero"><div class="public-container"><span class="public-badge"><?= icon('clipboard',16) ?> قوانین استفاده</span><h1 class="public-title">شرایط استفاده از سامانه مَدار</h1><p class="public-lead">استفاده از مَدار به معنای پذیرش قوانین مربوط به حساب کاربری، حفظ محرمانگی اطلاعات ورود و استفاده صحیح از خدمات آموزشی سامانه است.</p></div></header>
<main><section class="public-section"><div class="public-container"><div class="public-card"><h2>موارد اصلی</h2><ul><li>کاربر موظف است اطلاعات ورود خود را در اختیار دیگران قرار ندهد.</li><li>اطلاعات آموزشی ثبت‌شده در سامانه برای ارائه خدمات مشاوره و برنامه‌ریزی استفاده می‌شود.</li><li>ارسال محتوای نامرتبط، مخرب یا ناقض حقوق دیگران در بخش پیام‌ها مجاز نیست.</li><li>هماهنگی جلسات و اعلان‌ها مطابق اطلاعات ثبت‌شده توسط مشاور انجام می‌شود.</li><li>مَدار می‌تواند برای بهبود کیفیت خدمات، امکانات سامانه را به‌روزرسانی کند.</li></ul></div></div></section></main>
<footer class="public-footer"><div class="inner"><span>© <?= fa_num(date('Y')) ?> <?= e(APP_NAME) ?></span><span><a href="<?= url('privacy.php') ?>">حریم خصوصی</a><a href="<?= url('contact.php') ?>">تماس با ما</a></span></div></footer>
<?php page_foot(); ?>
