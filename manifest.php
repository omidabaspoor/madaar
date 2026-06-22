<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$base = BASE_URL;
echo json_encode([
  'name' => 'مَدار · سامانه هوشمند برنامه‌ریزی کنکور',
  'short_name' => 'مَدار',
  'description' => 'سامانه هوشمند برنامه‌ریزی هفتگی، تسک منیجر و آزمون‌ساز آنلاین کنکور — دکتر سجاد صیادی',
  'lang' => 'fa',
  'dir' => 'rtl',
  'start_url' => $base . '/',
  'scope' => $base . '/',
  'display' => 'standalone',
  'orientation' => 'portrait',
  'background_color' => '#0c1512',
  'theme_color' => '#0c1512',
  'categories' => ['education', 'productivity'],
  'icons' => [
    ['src'=>$base.'/assets/icons/favicon-16.png','sizes'=>'16x16','type'=>'image/png'],
    ['src'=>$base.'/assets/icons/favicon-32.png','sizes'=>'32x32','type'=>'image/png'],
    ['src'=>$base.'/assets/icons/favicon-64.png','sizes'=>'64x64','type'=>'image/png'],
    ['src'=>$base.'/assets/icons/icon-192.png','sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'],
    ['src'=>$base.'/assets/icons/icon-512.png','sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable'],
  ],
  'screenshots' => [
    ['src'=>$base.'/assets/img/og-banner.jpg','sizes'=>'1200x630','type'=>'image/jpeg','label'=>'مَدار — سامانه هوشمند برنامه‌ریزی کنکور'],
  ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
