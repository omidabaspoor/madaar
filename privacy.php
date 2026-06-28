<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
page_head('حریم خصوصی', 'سیاست حفظ حریم خصوصی و حفاظت از اطلاعات کاربران سامانه مَدار', ['public.css']);
?>
<nav class="public-nav"><div class="inner"><?= brand_block() ?><div class="public-links"><a href="<?= url('') ?>">خانه</a><a href="<?= url('services.php') ?>">خدمات</a><a href="<?= url('about.php') ?>">درباره ما</a><a href="<?= url('contact.php') ?>">تماس با ما</a><a class="active" href="<?= url('privacy.php') ?>">حریم خصوصی</a></div></div></nav>
<header class="public-hero"><div class="public-container"><span class="public-badge"><?= icon('shield',16) ?> حریم خصوصی</span><h1 class="public-title">حفظ اطلاعات کاربران در مَدار</h1><p class="public-lead">اطلاعات کاربران فقط برای ارائه خدمات آموزشی، برنامه‌ریزی، گزارش‌گیری و ارتباط میان مشاور و دانش‌آموز استفاده می‌شود.</p></div></header>
<main><section class="public-section"><div class="public-container public-grid"><article class="public-card"><h2>اطلاعات دریافتی</h2><p>نام، نام کاربری، شماره تماس، پایه، رشته، برنامه‌ها، گزارش‌ها و پیام‌های آموزشی برای ارائه خدمات سامانه نگهداری می‌شود.</p></article><article class="public-card"><h2>هدف استفاده</h2><p>این اطلاعات برای ساخت برنامه هفتگی، پیگیری عملکرد، ارسال اعلان‌های آموزشی، پیام‌رسانی و هماهنگی جلسات استفاده می‌شود.</p></article><article class="public-card"><h2>امنیت</h2><p>گذرواژه‌ها به‌صورت رمزنگاری‌شده ذخیره می‌شوند و دسترسی به بخش‌های مدیریتی فقط برای نقش‌های مجاز امکان‌پذیر است.</p></article></div></section></main>
<footer class="public-footer"><div class="inner"><span>© <?= fa_num(date('Y')) ?> <?= e(APP_NAME) ?></span><span><a href="<?= url('terms.php') ?>">قوانین استفاده</a><a href="<?= url('contact.php') ?>">تماس با ما</a></span></div></footer>
<?php page_foot(); ?>
