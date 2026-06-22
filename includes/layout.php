<?php
/** قطعات لایه‌بندی مشترک (head / toast / scripts) — نسخه Production SEO */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';

/** آدرس کانونیکال (بدون دابل‌اسلش) */
function canonical_url(): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
    return rtrim(BASE_URL, '/') . $path;
}

function page_head(string $title, string $desc = '', array $extraCss = []): void
{
    $full = $title ? ($title . ' · ' . APP_NAME) : (APP_NAME . ' · ' . APP_TAGLINE);
    $desc = $desc ?: APP_TAGLINE . ' — ' . APP_OWNER;
    $canonical = canonical_url();
    $ogImage = url('assets/img/og-banner.jpg');
    $siteName = APP_NAME . ' · Madar Study OS';
    ?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($full) ?></title>
  <meta name="description" content="<?= e($desc) ?>">
  <meta name="keywords" content="برنامه ریزی کنکور, تسک منیجر کنکور, مَدار, دکتر سجاد صیادی, مشاور کنکور, برنامه ریزی هفتگی, آزمون آنلاین کنکور, مدار, madar, madaar, Spaced Repetition, کنکور سراسری, مشاوره کنکور, برنامه مطالعاتی">
  <meta name="author" content="دکتر سجاد صیادی">
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
  <meta name="theme-color" content="#0c1512">
  <link rel="canonical" href="<?= e($canonical) ?>">

  <!-- ===== Open Graph (Facebook, LinkedIn, Rubika, Telegram, WhatsApp, iMessage) ===== -->
  <meta property="og:site_name" content="<?= e($siteName) ?>">
  <meta property="og:title" content="<?= e($full) ?>">
  <meta property="og:description" content="<?= e($desc) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <meta property="og:image" content="<?= e($ogImage) ?>">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:type" content="image/jpeg">
  <meta property="og:image:alt" content="مَدار — سامانه هوشمند برنامه‌ریزی کنکور">
  <meta property="og:locale" content="fa_IR">

  <!-- ===== Twitter Card (X / Twitter) ===== -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($full) ?>">
  <meta name="twitter:description" content="<?= e($desc) ?>">
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
  <meta name="twitter:image:alt" content="مَدار — سامانه هوشمند برنامه‌ریزی کنکور">

  <!-- ===== Telegram specific ===== -->
  <meta name="telegram:channel" content="@madaar_edu">

  <!-- ===== Structured Data (Google Rich Results) ===== -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "مَدار — سامانه هوشمند برنامه‌ریزی کنکور",
    "alternateName": "Madar Study OS",
    "url": "https://madaar-edu.ir",
    "description": "سامانه هوشمند برنامه‌ریزی هفتگی، تسک منیجر، آزمون‌ساز آنلاین و تحلیل رفتاری آزمون‌های کنکور زیر نظر دکتر سجاد صیادی",
    "applicationCategory": "EducationalApplication",
    "operatingSystem": "All",
    "browserRequirements": "Requires JavaScript. Requires HTML5.",
    "softwareVersion": "<?= APP_VERSION ?>",
    "inLanguage": "fa",
    "image": "<?= e($ogImage) ?>",
    "screenshot": "<?= e($ogImage) ?>",
    "creator": {
      "@type": "Person",
      "name": "دکتر سجاد صیادی",
      "jobTitle": "مشاور کنکور"
    },
    "offers": {
      "@type": "Offer",
      "price": "0",
      "priceCurrency": "IRR"
    }
  }
  </script>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "مَدار",
    "alternateName": "Madar Study OS",
    "url": "https://madaar-edu.ir",
    "logo": "https://madaar-edu.ir/assets/img/logo.png",
    "description": "سامانه هوشمند برنامه‌ریزی کنکور",
    "founder": {
      "@type": "Person",
      "name": "دکتر سجاد صیادی"
    }
  }
  </script>

  <!-- ===== Favicons (همه‌ی سایزها برای Google, iOS, Android, Windows) ===== -->
  <link rel="icon" href="<?= url('favicon.ico') ?>" sizes="48x48">
  <link rel="icon" href="<?= asset('icons/favicon-16.png') ?>" type="image/png" sizes="16x16">
  <link rel="icon" href="<?= asset('icons/favicon-32.png') ?>" type="image/png" sizes="32x32">
  <link rel="icon" href="<?= asset('icons/favicon-64.png') ?>" type="image/png" sizes="64x64">
  <link rel="apple-touch-icon" href="<?= asset('icons/icon-180.png') ?>">
  <link rel="manifest" href="<?= url('manifest.php') ?>">
  <meta name="sw-url" content="<?= url('sw.js') ?>">

  <!-- ===== Styles ===== -->
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/persian_datepicker.css') ?>">
  <?php foreach ($extraCss as $c): ?>
  <link rel="stylesheet" href="<?= asset('css/' . $c) ?>">
  <?php endforeach; ?>
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <meta name="csrf-token" content="<?= function_exists('csrf_token') ? e(csrf_token()) : '' ?>">
  <script>
    window.MADAR_ICON='<?= asset('icons/icon-192.png') ?>';
    window.MADAR_BADGE='<?= asset('icons/favicon-64.png') ?>';
    window.MADAR_PUSH_PUBLIC_KEY='<?= defined('VAPID_PUBLIC_KEY') ? e((string)VAPID_PUBLIC_KEY) : '' ?>';
    window.MADAR_PUSH_SUB_URL='<?= url('api/push_subscription.php') ?>';
  </script>
</head>
<body><?php
}

function logo_svg(int $size = 38): string
{
    return '<img class="brand-logo-img" src="' . asset('img/logo.png') . '" alt="' . e(APP_NAME) . ' — سامانه هوشمند برنامه‌ریزی کنکور" width="' . $size . '" height="' . $size . '">';
}

function brand_block(): string
{
    return '<a href="' . url('') . '" class="brand">' . logo_svg(40)
        . '<span><span class="b-name gradient-text">' . e(APP_NAME) . '</span>'
        . '<span class="b-sub">STUDY OS</span></span></a>';
}

function page_foot(array $extraJs = []): void
{
    ?>
  <div id="toast-wrap"></div>
  <script src="<?= asset('js/app.js') ?>"></script>
  <script src="<?= asset('js/persian_datepicker.js') ?>"></script>
  <?php foreach ($extraJs as $j): ?>
  <script src="<?= asset('js/' . $j) ?>"></script>
  <?php endforeach; ?>
</body>
</html><?php
}
