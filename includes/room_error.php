<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'خطا') ?> · مَدار</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>
body{display:grid;place-items:center;min-height:100vh;background:#0c1512;color:#eef2ee;font-family:'Vazirmatn',sans-serif;padding:20px}
.err-box{background:rgba(217,116,116,.12);border:1px solid rgba(217,116,116,.4);border-radius:18px;padding:36px;max-width:480px;text-align:center}
.err-box h1{color:#ff9a9a;font-size:1.3rem;margin-bottom:14px}
.err-box p{color:#b9c4bd;font-size:.92rem;line-height:1.7;margin-bottom:24px}
.err-box a{display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#e0c595,#b2945f);color:#1a1206;border-radius:12px;text-decoration:none;font-weight:800}
</style>
</head>
<body>
<div class="err-box">
  <h1><?= icon('alert-circle',32) ?> <?= e($title ?? 'خطا') ?></h1>
  <p><?= e($error ?? 'خطای ناشناخته رخ داد.') ?></p>
  <a href="<?= e($redirectUrl) ?>"><?= e($redirectText ?? 'بازگشت') ?></a>
</div>
</body>
</html>
