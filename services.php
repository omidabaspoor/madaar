<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
page_head('خدمات مَدار', 'خدمات سامانه مَدار شامل برنامه‌ریزی هفتگی، پیگیری عملکرد، آزمون، گزارش و پیام‌رسانی آموزشی', ['public.css']);
?>
<nav class="public-nav"><div class="inner"><?= brand_block() ?><div class="public-links"><a href="<?= url('') ?>">خانه</a><a class="active" href="<?= url('services.php') ?>">خدمات</a><a href="<?= url('about.php') ?>">درباره ما</a><a href="<?= url('contact.php') ?>">تماس با ما</a><a href="<?= url('auth/login.php') ?>">ورود</a></div></div></nav>
<header class="public-hero"><div class="public-container"><span class="public-badge"><?= icon('grid',16) ?> خدمات سامانه</span><h1 class="public-title">خدمات آموزشی و نرم‌افزاری مَدار</h1><p class="public-lead">مَدار برای مدیریت برنامه مطالعاتی دانش‌آموزان، ارتباط با مشاور، ثبت عملکرد و ارائه گزارش‌های آموزشی طراحی شده است.</p></div></header>
<main>
<section class="public-section"><div class="public-container public-grid">
<?php $items = [
 ['calendar','برنامه‌ریزی هفتگی','تعریف برنامه هفتگی در قالب روزها و واحدهای مطالعاتی، ثبت درس، فصل، مقدار هدف، زمان و منبع.'],
 ['check-circle','پیگیری اجرای برنامه','امکان ثبت وضعیت اجرای هر تسک توسط دانش‌آموز و مشاهده روند انجام برنامه توسط مشاور.'],
 ['chart','گزارش عملکرد','نمایش آمار پیشرفت، تسک‌های انجام‌شده، ناقص و عقب‌مانده برای تصمیم‌گیری دقیق‌تر مشاور.'],
 ['clipboard','آزمون و کارنامه','ساخت آزمون، پاسخ‌برگ، کارنامه تحلیلی و بررسی نتیجه آزمون‌های داخلی.'],
 ['repeat','مرورهای آموزشی','مدیریت مرورهای فاصله‌دار برای تثبیت یادگیری و پیگیری مباحث مهم.'],
 ['message','پیام‌رسان آموزشی','گفتگوی مستقیم دانش‌آموز و مشاور همراه با ارسال متن، تصویر، فایل و ویس.'],
 ['bell','اعلان و یادآوری','ارسال اعلان‌های داخل سامانه و در صورت تنظیم، پیامک‌های اطلاع‌رسانی مربوط به جلسات.'],
 ['trophy','انگیزش و دستاورد','نمایش استریک، نشان‌های انگیزشی و رتبه‌بندی برای تقویت استمرار مطالعاتی.'],
]; foreach($items as $i): ?>
<article class="public-card"><div class="public-icon <?= $i[0]==='calendar'?'gold':'' ?>"><?= icon($i[0],24) ?></div><h3><?= e($i[1]) ?></h3><p><?= e($i[2]) ?></p></article>
<?php endforeach; ?>
</div></section>
<section class="public-section"><div class="public-container"><div class="public-card"><h2>فرآیند استفاده از خدمات</h2><ul><li>دانش‌آموز ثبت‌نام می‌کند و پس از تأیید مشاور وارد پنل می‌شود.</li><li>مشاور برنامه هفتگی را تنظیم و منتشر می‌کند.</li><li>دانش‌آموز هر روز وضعیت تسک‌ها را ثبت می‌کند.</li><li>مشاور گزارش‌ها را بررسی کرده و در صورت نیاز برنامه را اصلاح می‌کند.</li></ul><div class="public-cta"><a class="btn" href="<?= url('auth/register.php') ?>">ثبت‌نام دانش‌آموز</a><a class="btn ghost" href="<?= url('contact.php') ?>">ارتباط با ما</a></div></div></div></section>
</main>
<footer class="public-footer"><div class="inner"><span>© <?= fa_num(date('Y')) ?> <?= e(APP_NAME) ?></span><span><a href="<?= url('about.php') ?>">درباره ما</a><a href="<?= url('contact.php') ?>">تماس با ما</a></span></div></footer>
<?php page_foot(); ?>
