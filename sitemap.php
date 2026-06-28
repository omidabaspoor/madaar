<?php
/**
 * Sitemap داینامیک — حل مشکل Content-Type روی هاست‌های ایرانی
 */
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
// تاریخ آخرین تغییر
$lastmod = date('Y-m-d');
$domain = 'https://madaar-edu.ir';
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc><?= $domain ?>/</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
    <image:image>
      <image:loc><?= $domain ?>/assets/img/og-banner.jpg</image:loc>
      <image:title>مَدار — سامانه هوشمند برنامه‌ریزی کنکور</image:title>
    </image:image>
    <image:image>
      <image:loc><?= $domain ?>/assets/img/logo.png</image:loc>
      <image:title>لوگوی مَدار</image:title>
    </image:image>
  </url>

  <url>
    <loc><?= $domain ?>/services.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?= $domain ?>/about.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= $domain ?>/contact.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= $domain ?>/privacy.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc><?= $domain ?>/terms.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>yearly</changefreq>
    <priority>0.5</priority>
  </url>
  <url>
    <loc><?= $domain ?>/auth/login.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= $domain ?>/auth/register.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= $domain ?>/pwa_help.php</loc>
    <lastmod><?= $lastmod ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.4</priority>
  </url>
</urlset>
