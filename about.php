<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
page_head('درباره ما', 'درباره مَدار؛ سامانه هوشمند برنامه‌ریزی کنکور زیر نظر دکتر سجاد صیادی', ['public.css']);
?>
<nav class="public-nav"><div class="inner"><?= brand_block() ?><div class="public-links"><a href="<?= url('') ?>">خانه</a><a href="<?= url('services.php') ?>">خدمات</a><a class="active" href="<?= url('about.php') ?>">درباره ما</a><a href="<?= url('contact.php') ?>">تماس با ما</a><a href="<?= url('auth/login.php') ?>">ورود</a></div></div></nav>
<header class="public-hero"><div class="public-container"><span class="public-badge"><?= icon('info',16) ?> درباره مَدار</span><h1 class="public-title">مَدار؛ همراه برنامه‌ریزی و پیگیری آموزشی</h1><p class="public-lead">مَدار یک سامانه تخصصی برای برنامه‌ریزی، پیگیری و مدیریت فرآیند مطالعاتی دانش‌آموزان کنکوری است. این سامانه با هدف ایجاد ارتباط منظم میان مشاور و دانش‌آموز، ثبت دقیق برنامه‌ها و مشاهده روند پیشرفت طراحی شده است.</p></div></header>
<main>
<section class="public-section"><div class="public-container public-grid">
  <article class="public-card"><div class="public-icon gold"><?= icon('graduation',24) ?></div><h2>مالک و راهبر آموزشی</h2><p>سامانه مَدار زیر نظر <?= e(APP_OWNER) ?> فعالیت می‌کند و تمرکز آن بر نظم مطالعاتی، برنامه‌ریزی هفتگی، پایش عملکرد و ارتباط مستمر مشاور با دانش‌آموز است.</p></article>
  <article class="public-card"><div class="public-icon"><?= icon('target',24) ?></div><h2>مأموریت ما</h2><p>هدف مَدار این است که برنامه مطالعاتی از حالت کاغذی و پراکنده خارج شود و به یک فرآیند قابل پیگیری، قابل گزارش‌گیری و قابل اصلاح تبدیل شود.</p></article>
  <article class="public-card"><div class="public-icon"><?= icon('shield',24) ?></div><h2>تعهد به کیفیت</h2><p>در طراحی مَدار، دسترسی امن، تجربه کاربری ساده، سازگاری با موبایل، و ثبت شفاف فعالیت‌ها برای مشاور و دانش‌آموز در اولویت قرار گرفته است.</p></article>
</div></section>
<section class="public-section"><div class="public-container"><div class="public-card"><h2>مخاطبان سامانه</h2><ul><li>دانش‌آموزان کنکوری که نیاز به برنامه منظم و قابل پیگیری دارند.</li><li>مشاوران آموزشی که می‌خواهند روند اجرای برنامه دانش‌آموزان را دقیق‌تر رصد کنند.</li><li>مجموعه‌های آموزشی که به پنل برنامه‌ریزی، پیام‌رسانی و گزارش‌گیری نیاز دارند.</li></ul></div></div></section>
</main>
<footer class="public-footer"><div class="inner"><span>© <?= fa_num(date('Y')) ?> <?= e(APP_NAME) ?> · <?= e(APP_OWNER) ?></span><span><a href="<?= url('services.php') ?>">خدمات</a><a href="<?= url('contact.php') ?>">تماس با ما</a></span></div></footer>
<?php page_foot(); ?>
