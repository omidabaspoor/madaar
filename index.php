<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();

// اگر وارد شده، به داشبورد مربوطه برو
if (is_logged_in()) {
    $r = user_role();
    if ($r === 'student') redirect('student/dashboard.php');
    if (in_array($r, ['advisor','admin'], true)) redirect('admin/dashboard.php');
}

/* ---------- موکاپ موبایلِ پنل دانش‌آموز (HTML/CSS خالص) ---------- */
function mock_phone(): string {
    ob_start(); ?>
    <div class="phone">
      <div class="phone-screen">
        <div class="mock-top">
          <div>
            <span class="mt-name">سلام، علی 👋</span>
            <span class="mt-sub">برنامه‌ی امروز شما</span>
          </div>
          <span class="mock-ava">ع‌ر</span>
        </div>
        <div class="mock-body">
          <div class="mock-hero-card">
            <div class="mock-ring"><span>۷۵٪</span></div>
            <div>
              <div class="mh-t">پیشرفت امروز</div>
              <div class="mh-d">۶ تسک از ۸ تسک انجام شد</div>
            </div>
          </div>
          <div class="mock-daylabel">شنبه · ۲۴ خرداد</div>
          <div class="mock-task done">
            <span class="mock-check on"><?= icon('check',13) ?></span>
            <div class="mtk-body"><div class="mtk-title">زیست‌شناسی فصل ۴</div><div class="mtk-meta">۴۰ تست · ۶۰ دقیقه</div></div>
            <span class="mtk-pill">تست</span>
          </div>
          <div class="mock-task t-gold done">
            <span class="mock-check on"><?= icon('check',13) ?></span>
            <div class="mtk-body"><div class="mtk-title">شیمی استوکیومتری</div><div class="mtk-meta">درسنامه + ۳۰ تست</div></div>
            <span class="mtk-pill">مطالعه</span>
          </div>
          <div class="mock-task t-blue">
            <span class="mock-check"></span>
            <div class="mtk-body"><div class="mtk-title">ریاضی فصل ۲</div><div class="mtk-meta">۳۵ تست · ۶۰ دقیقه</div></div>
            <span class="mtk-pill">تست</span>
          </div>
          <div class="mock-streak"><?= icon('fire',18) ?> استریک ۱۲ روزه</div>
        </div>
        <div class="mock-tabs">
          <span class="on"><?= icon('home',19) ?></span>
          <span><?= icon('calendar',19) ?></span>
          <span><?= icon('chart',19) ?></span>
          <span><?= icon('message',19) ?></span>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

/* ---------- موکاپ پنجره‌ی پنل مشاور (HTML/CSS خالص) ---------- */
function mock_window(): string {
    ob_start(); ?>
    <div class="win">
      <div class="win-bar">
        <span class="win-dot r"></span><span class="win-dot y"></span><span class="win-dot g"></span>
        <span class="win-url">madaar.app/admin</span>
      </div>
      <div class="win-body">
        <div class="win-side">
          <div class="ws-logo"></div>
          <div class="ws-i on"></div><div class="ws-i"></div><div class="ws-i"></div><div class="ws-i"></div>
        </div>
        <div class="win-main">
          <div class="win-stats">
            <div class="win-stat"><div class="v gold">۲۴</div><div class="k">دانش‌آموز فعال</div></div>
            <div class="win-stat"><div class="v sage">۸۵٪</div><div class="k">میانگین پیشرفت</div></div>
            <div class="win-stat"><div class="v">۱۲</div><div class="k">برنامه‌ی این هفته</div></div>
          </div>
          <div class="win-grid">
            <div class="wg-head"><b>برنامه‌ی هفتگی · علی رضایی</b><span class="wg-badge">منتشر شده</span></div>
            <div class="win-row">
              <div class="win-cell f-gold">زیست ف۴</div>
              <div class="win-cell f-gold">شیمی</div>
              <div class="win-cell f-sage">فیزیک</div>
              <div class="win-cell f-gold">ریاضی</div>
              <div class="win-cell empty"></div>
            </div>
            <div class="win-row">
              <div class="win-cell f-sage">ادبیات</div>
              <div class="win-cell f-gold">زیست ف۷</div>
              <div class="win-cell empty"></div>
              <div class="win-cell f-gold">شیمی</div>
              <div class="win-cell f-sage">عربی</div>
            </div>
            <div class="win-row">
              <div class="win-cell f-gold">ریاضی</div>
              <div class="win-cell f-sage">فیزیک</div>
              <div class="win-cell f-gold">زیست ف۴</div>
              <div class="win-cell empty"></div>
              <div class="win-cell f-gold">شیمی</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}

page_head('', '', ['landing.css']);
?>
<!-- ============ NAV ============ -->
<nav class="site-nav">
  <div class="container inner">
    <?= brand_block() ?>
    <ul class="nav-links">
      <li><a href="#features"><?= icon('grid',16) ?> امکانات</a></li>
      <li><a href="#flow">مسیر کاربری</a></li>
      <li><a href="#panels">نمای سامانه</a></li>
      <li><a href="#tech">امنیت</a></li>
    </ul>
    <div class="flex gap-2" style="align-items:center">
      <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost btn-sm"><?= icon('login',16) ?> ورود</a>
      <a href="<?= url('auth/register.php') ?>" class="btn btn-gold btn-sm">ثبت‌نام</a>
      <button class="nav-toggle btn btn-icon btn-ghost" aria-label="منو"><?= icon('menu') ?></button>
    </div>
  </div>
</nav>

<!-- ============ HERO ============ -->
<header class="hero">
  <div class="hero-blob b1"></div>
  <div class="hero-blob b2"></div>
  <div class="container hero-grid">
    <div class="reveal in">
      <span class="badge badge-gold hero-badge"><?= icon('sparkles',14) ?> سامانه‌ی هوشمند برنامه‌ریزی کنکور</span>
      <h1 class="display">برنامه‌ریزی منظم،<br><span class="gradient-text">پیشرفت واقعی.</span></h1>
      <p class="lead">مَدار فضای مشترک دانش‌آموز و مشاور است. <?= e(APP_OWNER) ?> برای هر دانش‌آموز برنامه‌ی هفتگی می‌چیند و دانش‌آموز تسک‌ها را روزبه‌روز انجام می‌دهد — همراه با گزارش پیشرفت زنده، یادآوری و انگیزه‌بخشی.</p>
      <div class="hero-cta">
        <a href="<?= url('auth/register.php') ?>" class="btn btn-gold btn-lg"><?= icon('rocket',18) ?> همین حالا شروع کنید</a>
        <a href="#flow" class="btn btn-ghost btn-lg"><?= icon('play',16) ?> چطور کار می‌کند؟</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><div class="num">۸×۷</div><div class="lbl">جدول روز × واحد</div></div>
        <div class="hero-stat"><div class="num"><span data-count="100" data-suffix="٪">۰</span></div><div class="lbl">فارسی و موبایل‌محور</div></div>
        <div class="hero-stat"><div class="num">PWA</div><div class="lbl">نصب روی گوشی</div></div>
      </div>
    </div>
    <div class="hero-visual reveal" data-d="2">
      <div class="hero-stage">
        <?= mock_phone() ?>
        <div class="float-card fc1"><span class="icon-tile sage" style="width:30px;height:30px;border-radius:9px"><?= icon('fire',16) ?></span> استریک ۱۲ روزه</div>
        <div class="float-card fc2"><span class="icon-tile" style="width:30px;height:30px;border-radius:9px"><?= icon('check-circle',16) ?></span> ۷۵٪ تسک‌های امروز</div>
      </div>
    </div>
  </div>
</header>

<!-- ============ FEATURES ============ -->
<section class="section" id="features">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">امکانات سامانه</span>
      <h2 class="section-title">هر چیزی که برای یک سال منظم لازم است</h2>
      <p>از برنامه‌ریزی هفتگی تا گزارش دقیق و ارتباط مستقیم — همه در یک سامانه‌ی یکپارچه و ساده.</p>
    </div>
    <div class="feat-grid">
      <?php
      $feats = [
        ['calendar','برنامه‌ی هفتگی روز × واحد','جدول کامل روزهای هفته و واحدهای مطالعاتی؛ درست مثل فرم برنامه‌ریزی کاغذی، اما هوشمند و زنده.'],
        ['tasks','تسک‌های دقیق و متنوع','مطالعه، تست، مرور، روزخوانی، آزمون و تحلیل آزمون — هرکدام با مقدار هدف، مدت و منبع مشخص.'],
        ['fire','استریک و دستاورد','هر روز فعالیت یعنی یک شعله؛ نشان‌ها و سیستم انگیزشی که دانش‌آموز را پای کار نگه می‌دارد.'],
        ['message','پیام‌رسانی مستقیم','گفتگوی دوطرفه‌ی مشاور و دانش‌آموز، بازخورد روی هر تسک و اعلان‌های به‌موقع.'],
        ['chart','گزارش‌ پیشرفت','نمودار روزانه، درصد پیشرفت هر درس و نمای کلی عملکرد همه‌ی دانش‌آموزان برای مشاور.'],
        ['shield','امن و سریع','رمزنگاری گذرواژه، محافظت در برابر حملات، نشست امن و طراحی کامل برای موبایل (PWA).'],
      ];
      $i=0; foreach ($feats as [$ic,$t,$d]): $i++; ?>
      <div class="card card-glow hover-lift feat-card reveal" data-d="<?= min($i,6) ?>">
        <span class="icon-tile <?= $i%2? 'sage':'' ?>"><?= icon($ic,24) ?></span>
        <h3><?= e($t) ?></h3>
        <p><?= e($d) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ USER FLOW ============ -->
<section class="section" id="flow" style="background:linear-gradient(180deg,transparent,var(--bg-2),transparent)">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">تجربه‌ی دانش‌آموز و مشاور</span>
      <h2 class="section-title">یک مسیر روشن، دو نقش هماهنگ</h2>
    </div>
    <div class="flow-cols">
      <div class="flow-col student">
        <h3><?= icon('user',20) ?> دانش‌آموز</h3>
        <?php foreach ([
          ['ثبت‌نام و تأیید','حساب می‌سازید و منتظر تأیید مشاور می‌مانید.'],
          ['دریافت برنامه','برنامه‌ی هفتگی شما توسط مشاور آماده می‌شود.'],
          ['انجام تسک‌ها','هر روز تسک‌ها را انجام و تیک می‌زنید.'],
          ['دریافت بازخورد','نظر و راهنمایی مشاور را روی تسک‌ها می‌بینید.'],
          ['پیشرفت و دستاورد','نمودار رشد و نشان‌هایتان را دنبال می‌کنید.'],
        ] as $n=>$s): ?>
        <div class="flow-step reveal" data-d="<?= min($n+1,6) ?>"><span class="n"><?= fa_num($n+1) ?></span><div><div class="t"><?= e($s[0]) ?></div><div class="d"><?= e($s[1]) ?></div></div></div>
        <?php endforeach; ?>
      </div>
      <div class="flow-mid"><div class="conn"><?= icon('repeat',24) ?></div></div>
      <div class="flow-col advisor">
        <h3><?= icon('graduation',20) ?> مشاور</h3>
        <?php foreach ([
          ['ورود به پنل','به پنل مدیریت اختصاصی خود وارد می‌شوید.'],
          ['مدیریت دانش‌آموزان','درخواست‌ها را تأیید و دانش‌آموزان را مدیریت می‌کنید.'],
          ['ساخت برنامه','جدول هفتگی را با چند کلیک و تسک‌های آماده می‌سازید.'],
          ['رصد پیشرفت','عملکرد همه‌ی دانش‌آموزان را زنده می‌بینید.'],
          ['ارتباط مستقیم','بازخورد و پیام مستقیم می‌فرستید.'],
        ] as $n=>$s): ?>
        <div class="flow-step reveal" data-d="<?= min($n+1,6) ?>"><span class="n"><?= fa_num($n+1) ?></span><div><div class="t"><?= e($s[0]) ?></div><div class="d"><?= e($s[1]) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ============ PANELS PREVIEW ============ -->
<section class="section" id="panels">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">نمایی از سامانه</span>
      <h2 class="section-title">طراحی تمیز، حرفه‌ای و موبایل‌محور</h2>
      <p>دارک‌مود زیبا، فونت فارسی وزیرمتن و ریسپانسیو کامل روی موبایل، تبلت و دسکتاپ.</p>
    </div>
    <div class="panels-pair reveal">
      <div>
        <?= mock_window() ?>
        <div class="text-c muted mt-4" style="font-size:.86rem"><?= icon('graduation',15) ?> پنل مشاور — برنامه‌ریز هفتگی</div>
      </div>
      <div>
        <?= mock_phone() ?>
        <div class="text-c muted mt-4" style="font-size:.86rem"><?= icon('user',15) ?> پنل دانش‌آموز روی موبایل</div>
      </div>
    </div>
    <div class="flex gap-3 wrap mt-6" style="justify-content:center">
      <span class="tech-tag"><?= icon('moon',16) ?> دارک‌مود زیبا</span>
      <span class="tech-tag"><?= icon('desktop',16) ?> ریسپانسیو کامل</span>
      <span class="tech-tag"><?= icon('lang',16) ?> فونت فارسی وزیرمتن</span>
      <span class="tech-tag"><?= icon('pwa',16) ?> قابل نصب روی گوشی</span>
    </div>
  </div>
</section>

<!-- ============ SECURITY / TECH ============ -->
<section class="section" id="tech">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">امنیت و فناوری</span>
      <h2 class="section-title">ساخته‌شده تا قابل‌اعتماد باشد</h2>
    </div>
    <div class="tech-grid">
      <?php foreach ([
        ['lock','رمزنگاری گذرواژه','گذرواژه‌ها با bcrypt محافظت می‌شوند و هرگز فاش نمی‌شوند.'],
        ['shield','محافظت در برابر حملات','هر فرم با توکن امن CSRF محافظت می‌شود.'],
        ['clock','محدودیت تلاش ورود','جلوگیری از حملات حدس گذرواژه.'],
        ['globe','نشست امن','کوکی‌های امن با httpOnly و SameSite.'],
      ] as $n=>$t): ?>
      <div class="card hover-lift tech-item reveal" data-d="<?= $n+1 ?>">
        <span class="icon-tile <?= $n%2?'sage':'' ?>"><?= icon($t[0],24) ?></span>
        <div class="t"><?= e($t[1]) ?></div>
        <div class="d"><?= e($t[2]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-c mt-6"><span class="tech-tag" style="font-size:.95rem;padding:10px 22px"><?= icon('zap',16) ?> ساخته‌شده با PHP 8 + MySQL + PWA</span></div>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="section">
  <div class="container">
    <div class="cta-band reveal">
      <h2>آماده‌اید مسیر کنکورتان را <span class="gradient-text">منظم</span> کنید؟</h2>
      <p class="lead" style="margin:0 auto 26px;max-width:520px">همین حالا حساب دانش‌آموزی بسازید و پس از تأیید مشاور، برنامه‌ی هفتگی‌تان را دریافت کنید.</p>
      <div class="flex gap-3 wrap" style="justify-content:center">
        <a href="<?= url('auth/register.php') ?>" class="btn btn-gold btn-lg"><?= icon('user',18) ?> ثبت‌نام دانش‌آموز</a>
        <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost btn-lg"><?= icon('login',18) ?> ورود به حساب</a>
      </div>
    </div>
  </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <?= brand_block() ?>
        <p class="muted mt-4" style="max-width:320px"><?= e(APP_TAGLINE) ?>؛ طراحی‌شده برای دانش‌آموزان کنکور زیر نظر <?= e(APP_OWNER) ?>.</p>
      </div>
      <div>
        <h4>دسترسی سریع</h4>
        <a href="#features">امکانات</a>
        <a href="#flow">مسیر کاربری</a>
        <a href="#panels">نمای سامانه</a>
        <a href="#tech">امنیت</a>
      </div>
      <div>
        <h4>حساب کاربری</h4>
        <a href="<?= url('auth/login.php') ?>">ورود</a>
        <a href="<?= url('auth/register.php') ?>">ثبت‌نام دانش‌آموز</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= fa_num(date('Y')) ?> · <?= e(APP_OWNER) ?> — همه‌ی حقوق محفوظ است.</span>
      <span class="flex gap-2" style="align-items:center;flex-wrap:wrap;justify-content:flex-end">
        <a href="https://webmania.ir" target="_blank" rel="noopener" title="Web Mania" style="font-size:.66rem;line-height:1.7;opacity:.48;color:inherit;text-decoration:none;font-weight:500">
          طراحی و توسعه توسط تیم وب مانیا · Design &amp; Development by Web Mania
        </a>
        <span class="tech-tag" style="padding:5px 12px;font-size:.74rem">نسخه <?= fa_num(APP_VERSION) ?></span>
      </span>
    </div>
  </div>
</footer>
<?php page_foot(); ?>
