<?php
/** راهنمای کامل پنل مشاور مَدار */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/models.php';

boot_session();
require_role('advisor', 'admin');
$u = current_user();
$isChief = is_chief_advisor($u);

panel_start('راهنمای پنل مشاور', 'آموزش گام‌به‌گام تمام بخش‌های سامانه', 'admin', 'guide');
?>

<style>
.guide-shell { max-width: 1100px; margin: 0 auto; }
.guide-hero {
  background: linear-gradient(135deg, rgba(203,172,128,.12), rgba(107,136,114,.08));
  border: 1px solid rgba(203,172,128,.2);
  border-radius: var(--r-xl);
  padding: 40px 32px;
  text-align: center;
  margin-bottom: 32px;
  position: relative;
  overflow: hidden;
}
.guide-hero::before {
  content: '';
  position: absolute;
  top: -60%; left: -20%;
  width: 140%; height: 160%;
  background: radial-gradient(ellipse, rgba(203,172,128,.08) 0%, transparent 70%);
  pointer-events: none;
}
.guide-hero h1 {
  font-size: clamp(1.6rem, 3.5vw, 2.4rem);
  margin-bottom: 12px;
  position: relative;
}
.guide-hero p {
  color: var(--text-2);
  font-size: 1.05rem;
  max-width: 600px;
  margin: 0 auto;
  position: relative;
}

/* Table of contents */
.guide-toc {
  background: var(--surface-1);
  border: 1px solid var(--border-soft);
  border-radius: var(--r-lg);
  padding: 28px;
  margin-bottom: 32px;
}
.guide-toc h3 {
  font-size: 1.15rem;
  margin-bottom: 16px;
  color: var(--gold-light);
  display: flex;
  align-items: center;
  gap: 8px;
}
.guide-toc-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 8px;
}
.guide-toc-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-radius: 12px;
  color: var(--text-2);
  font-size: .9rem;
  font-weight: 600;
  transition: all .2s;
  cursor: pointer;
  text-decoration: none;
}
.guide-toc-item:hover {
  background: var(--sage-glass);
  color: var(--sage-light);
}
.guide-toc-item .toc-num {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--gold-glass);
  color: var(--gold-light);
  display: grid;
  place-items: center;
  font-size: .78rem;
  font-weight: 900;
  flex-shrink: 0;
}

/* Section */
.guide-section {
  background: var(--surface-1);
  border: 1px solid var(--border-soft);
  border-radius: var(--r-lg);
  padding: 32px;
  margin-bottom: 24px;
  position: relative;
  overflow: hidden;
}
.guide-section::before {
  content: '';
  position: absolute;
  top: 0; right: 0;
  width: 4px; height: 100%;
  background: var(--grad-gold);
  border-radius: 0 0 4px 0;
}
.guide-section h2 {
  font-size: 1.35rem;
  margin-bottom: 6px;
  color: var(--text-1);
  display: flex;
  align-items: center;
  gap: 10px;
}
.guide-section h2 .sec-icon {
  width: 42px; height: 42px;
  border-radius: 12px;
  background: var(--gold-glass);
  color: var(--gold-light);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}
.guide-section .sec-subtitle {
  color: var(--text-3);
  font-size: .85rem;
  margin-bottom: 20px;
  padding-right: 52px;
}

.guide-step {
  display: flex;
  gap: 14px;
  padding: 14px 0;
  border-bottom: 1px solid var(--border-soft);
  align-items: flex-start;
}
.guide-step:last-child { border-bottom: none; }
.step-num {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--sage-glass);
  color: var(--sage-light);
  display: grid;
  place-items: center;
  font-weight: 900;
  font-size: .85rem;
  flex-shrink: 0;
  margin-top: 2px;
}
.step-content h4 {
  font-size: .95rem;
  font-weight: 800;
  color: var(--text-1);
  margin-bottom: 4px;
}
.step-content p {
  font-size: .88rem;
  color: var(--text-2);
  line-height: 1.7;
}
.step-content .tip {
  margin-top: 8px;
  padding: 8px 14px;
  background: rgba(203,172,128,.08);
  border-radius: 8px;
  border-right: 3px solid var(--gold);
  font-size: .82rem;
  color: var(--gold-light);
}

.guide-key {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  background: var(--surface-3);
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: .78rem;
  font-weight: 700;
  color: var(--text-2);
  font-family: monospace;
}

.guide-screenshot {
  background: var(--surface-2);
  border: 1px solid var(--border-soft);
  border-radius: 12px;
  padding: 16px;
  margin: 12px 0;
  text-align: center;
  color: var(--text-3);
  font-size: .85rem;
}

@media (max-width: 768px) {
  .guide-section { padding: 22px; }
  .guide-toc-grid { grid-template-columns: 1fr; }
  .guide-hero { padding: 28px 20px; }
  .guide-step { flex-direction: column; gap: 8px; }
  .guide-section .sec-subtitle { padding-right: 0; }
}
</style>

<div class="guide-shell">

  <!-- Hero -->
  <div class="guide-hero">
    <h1>📖 راهنمای جامع پنل مشاور مَدار</h1>
    <p>تمام بخش‌های سامانه را گام‌به‌گام یاد بگیرید. از مدیریت دانش‌آموزان تا ساخت آزمون و تحلیل گزارش‌ها.</p>
  </div>

  <!-- Table of Contents -->
  <div class="guide-toc">
    <h3><?= icon('list', 20) ?> فهرست بخش‌ها</h3>
    <div class="guide-toc-grid">
      <a href="#sec-dashboard" class="guide-toc-item"><span class="toc-num">۱</span> داشبورد کلان</a>
      <a href="#sec-students" class="guide-toc-item"><span class="toc-num">۲</span> مدیریت دانش‌آموزان</a>
      <a href="#sec-planner" class="guide-toc-item"><span class="toc-num">۳</span> برنامه‌ریز هفتگی</a>
      <a href="#sec-meetings" class="guide-toc-item"><span class="toc-num">۴</span> برنامه‌ریزی جلسات</a>
      <a href="#sec-exams" class="guide-toc-item"><span class="toc-num">۵</span> آزمون‌ساز آنلاین</a>
      <a href="#sec-reports" class="guide-toc-item"><span class="toc-num">۶</span> گزارش‌های حرفه‌ای</a>
      <a href="#sec-analysis" class="guide-toc-item"><span class="toc-num">۷</span> تحلیل آزمون داخلی</a>
      <a href="#sec-messages" class="guide-toc-item"><span class="toc-num">۸</span> پیام‌رسانی و چت</a>
      <a href="#sec-achievements" class="guide-toc-item"><span class="toc-num">۹</span> دستاوردها و نشان‌ها</a>
      <a href="#sec-settings" class="guide-toc-item"><span class="toc-num">۱۰</span> تنظیمات سامانه</a>
    </div>
  </div>

  <!-- ======================== 1. داشبورد ======================== -->
  <div class="guide-section" id="sec-dashboard">
    <h2><span class="sec-icon"><?= icon('home', 20) ?></span> داشبورد کلان</h2>
    <p class="sec-subtitle">نمای کلی وضعیت تمام دانش‌آموزان و فعالیت‌های روزانه</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>کارت‌های آمار</h4>
        <p>در بالای داشبورد، ۴ کارت آماری می‌بینید: <b>کل دانش‌آموزان</b>، <b>فعال</b>، <b>در انتظار تأیید</b> و <b>نرخ تکمیل تسک‌ها</b>. این اعداد در لحظه به‌روز می‌شوند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>فعالیت هفتگی (نمودار)</h4>
        <p>نمودار ستونی فعالیت ۷ روز هفته را نشان می‌دهد. هر ستون نشان‌دهنده تعداد تسک‌های تکمیل‌شده در آن روز است. با نگاه سریع می‌فهمید کدام روزها دانش‌آموزان فعال‌تر بوده‌اند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>درخواست‌های در انتظار تأیید</h4>
        <p>اگر دانش‌آموزی ثبت‌نام کرده و هنوز تأیید نشده، در این بخش نمایش داده می‌شود. با دکمه <span class="guide-key">✓ تأیید</span> دانش‌آموز را فعال کنید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>رتبه‌بندی دانش‌آموزان</h4>
        <p>جدول رتبه‌بندی، دانش‌آموزان را بر اساس امتیاز تسک مرتب می‌کند. ستون‌ها: نام، رشته، استریک (روزهای پیاپی فعالیت)، درصد پیشرفت. با کلیک روی <span class="guide-key">برنامه</span> مستقیم به صفحه برنامه‌ریزی همان دانش‌آموز می‌روید.</p>
        <div class="tip">💡 استریک = تعداد روزهایی که دانش‌آموز پشت‌سرهم تسک انجام داده. هرچه بالاتر، یعنی دانش‌آموز منظم‌تر است.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۵</span>
      <div class="step-content">
        <h4>هشدار جلسات امروز</h4>
        <p>اگر جلسه‌ای برای امروز تنظیم شده باشد، یک بنر طلایی با جزئیات جلسه در بالای داشبورد نمایش داده می‌شود.</p>
      </div>
    </div>
  </div>

  <!-- ======================== 2. دانش‌آموزان ======================== -->
  <div class="guide-section" id="sec-students">
    <h2><span class="sec-icon"><?= icon('users', 20) ?></span> مدیریت دانش‌آموزان</h2>
    <p class="sec-subtitle">مشاهده، تأیید، مدیریت و دسترسی به اطلاعات هر دانش‌آموز</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>فیلتر و جستجو</h4>
        <p>بالای صفحه، فیلترهای <b>همه</b>، <b>فعال</b>، <b>در انتظار</b> و <b>مسدود</b> وجود دارد. همچنین می‌توانید با نوار جستجو، نام یا نام‌کاربری دانش‌آموز را تایپ کنید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>کارت هر دانش‌آموز</h4>
        <p>هر دانش‌آموز یک کارت دارد که شامل: آواتار، نام، نام‌کاربری، رشته، پایه، استریک، حال امروز (mood)، و نوار پیشرفت کل تسک‌ها.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>منوی عملیات (⋯)</h4>
        <p>روی دکمه <span class="guide-key">⋯</span> هر کارت کلیک کنید تا منوی عملیات باز شود:</p>
        <p>• <b>گزارش</b> → مشاهده گزارش کامل هفتگی دانش‌آموز</p>
        <p>• <b>مسدودسازی</b> → غیرفعال کردن حساب دانش‌آموز</p>
        <p>• <b>فعال‌سازی</b> → فعال کردن حساب مسدودشده</p>
        <p>• <b>حذف</b> → حذف کامل دانش‌آموز و تمام برنامه‌هایش</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>حالت‌های دسترسی مشاور</h4>
        <p>در <b>تنظیمات سامانه</b>، مشاور می‌تواند حالت دسترسی خود را انتخاب کند:</p>
        <p>• <b>دسترسی کامل (All)</b> → همه دانش‌آموزان مشاور نمایش داده می‌شوند</p>
        <p>• <b>دسترسی محدود (Restricted)</b> → فقط دانش‌آموزانی که صریحاً به مشاور اختصاص داده شده‌اند نمایش داده می‌شوند</p>
        <div class="tip">🔒 این حالت برای سیستم‌های چندمشاوری کاربرد دارد و در تمام بخش‌ها (گزارش‌ها، آزمون‌ها، پیام‌ها، جلسات و...) اعمال می‌شود.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۵</span>
      <div class="step-content">
        <h4>تأیید دانش‌آموز جدید</h4>
        <p>وقتی دانش‌آموزی ثبت‌نام می‌کند، وضعیتش <b>در انتظار</b> است. در داشبورد یا صفحه دانش‌آموزان، دکمه <span class="guide-key">✓ تأیید</span> را بزنید تا فعال شود و بتواند برنامه ببیند.</p>
      </div>
    </div>
  </div>

  <!-- ======================== 3. برنامه‌ریز ======================== -->
  <div class="guide-section" id="sec-planner">
    <h2><span class="sec-icon"><?= icon('calendar', 20) ?></span> برنامه‌ریز هفتگی</h2>
    <p class="sec-subtitle">طراحی و مدیریت برنامه هفتگی هر دانش‌آموز — هسته اصلی مَدار</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>ساختار جدول برنامه</h4>
        <p>جدول ۸ ستون (۷ واحد + واحد ویژه) × ۷ سطر (شنبه تا جمعه) دارد. هر سلول می‌تواند یک یا چند تسک داشته باشد.</p>
        <p><b>واحدها:</b> واحد اول تا هفتم + واحد ویژه (برای روزخوانی، آزمونک و مرور)</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>افزودن تسک</h4>
        <p>روی هر سلول خالی کلیک کنید یا از <span class="guide-key">+</span> داخل سلول استفاده کنید. مودال تسک باز می‌شود:</p>
        <p>• <b>درس</b> → چیپ رنگی درس را انتخاب کنید (زیست، شیمی، ریاضی و...)</p>
        <p>• <b>نوع تسک</b> → مطالعه، تست، مرور، آزمونک، روزخوانی، تحلیل آزمون و...</p>
        <p>• <b>عنوان</b> → با انتخاب درس خودکار پر می‌شود اما قابل ویرایش است</p>
        <p>• <b>مقدار هدف</b> → مثلاً ۴۰ تست، ۳۰ صفحه (تعداد پیش‌فرض هر درس متفاوت است)</p>
        <p>• <b>مدت</b> → مدت پیشنهادی به دقیقه</p>
        <p>• <b>اولویت</b> → عادی، مهم، کم‌اهمیت (پیش‌فرض: بدون اولویت)</p>
        <p>• <b>منبع</b> → کتاب، آزمون ماز، جزوه کلاس و...</p>
        <div class="tip">⚡ کلید میانبر: <span class="guide-key">Ctrl+Enter</span> برای ذخیره سریع تسک</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>تسک‌های آماده (پریست)</h4>
        <p>در مودال تسک، دکمه‌های پیش‌فرض سریع وجود دارد:</p>
        <p>• درسنامه + ۳۰/۳۵/۴۰ تست</p>
        <p>• مطابق کلاس/ویدیو</p>
        <p>• بانک تست</p>
        <p>• تحلیل آزمون</p>
        <p>• غلط‌نامه</p>
        <p>• مرور ویژه ۱۵ دقیقه</p>
        <p>با یک کلیک، تمام فیلدها به‌صورت هوشمند پر می‌شوند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>کپی / تکثیر تسک</h4>
        <p>روی هر تسک دکمه <span class="guide-key">📋 کپی</span> وجود دارد. بعد از کپی، روی هر سلول خالی کلیک کنید تا تسک آنجا پیست شود. همچنین می‌توانید <b>راست‌کلیک</b> کنید و از منوی سریع استفاده کنید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۵</span>
      <div class="step-content">
        <h4>حذف تسک و واحد</h4>
        <p>• حذف یک تسک: دکمه <span class="guide-key">×</span> روی هر تسک</p>
        <p>• حذف کل یک واحد: دکمه <span class="guide-key">🗑</span> کنار نام واحد</p>
        <p>• حذف کل یک روز: دکمه <span class="guide-key">🗑</span> کنار نام روز</p>
        <p>• حذف کل یک واحد در تمام روزها: از منوی راست‌کلیک</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۶</span>
      <div class="step-content">
        <h4>کپی برنامه هفته قبل</h4>
        <p>دکمه <span class="guide-key">📋 کپی از هفته قبل</span> تمام تسک‌های هفته قبلی را کپی می‌کند. دو حالت: <b>یک‌بار پیست</b> یا <b>پیست چسبان</b> (چندباره) — قابل تنظیم در تنظیمات.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۷</span>
      <div class="step-content">
        <h4>واحد ویژه پیش‌فرض</h4>
        <p>دکمه <span class="guide-key">✨ واحد ویژه پیش‌فرض</span> سه تسک استاندارد (روزخوانی، مرور ویژه ۱۵د، آزمونک) را به ستون ویژه تمام روزها اضافه می‌کند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۸</span>
      <div class="step-content">
        <h4>انتشار برنامه</h4>
        <p>بعد از تکمیل برنامه، دکمه <span class="guide-key">🚀 انتشار برنامه</span> را بزنید. برنامه به حالت <b>منتشرشده</b> درمی‌آید و دانش‌آموز آن را در پنل خود می‌بیند.</p>
        <p>برای ویرایش مجدد، <span class="guide-key">✏️ بازگشت به پیش‌نویس</span> را بزنید.</p>
        <div class="tip">⚠️ تا وقتی برنامه «پیش‌نویس» باشد، دانش‌آموز آن را نمی‌بیند. حتماً انتشار بزنید!</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۹</span>
      <div class="step-content">
        <h4>کپی برنامه به دانش‌آموز دیگر</h4>
        <p>در پنل بالای صفحه، از منوی کشویی یک دانش‌آموز دیگر انتخاب کنید. تمام تسک‌های هفته جاری به همان شکل (بدون تغییر) برای دانش‌آموز مقصد کپی می‌شود.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۱۰</span>
      <div class="step-content">
        <h4>تسک‌های قرمز (عدم اجرا)</h4>
        <p>تسک‌هایی که دانش‌آموز در روز مقرر انجام نداده، به‌صورت خودکار <b>قرمز</b> می‌شوند. دکمه «× تسک‌های قرمز» در پایین صفحه، لیست تمام تسک‌های عقب‌افتاده را نشان می‌دهد.</p>
      </div>
    </div>
  </div>

  <!-- ======================== 4. جلسات ======================== -->
  <div class="guide-section" id="sec-meetings">
    <h2><span class="sec-icon"><?= icon('calendar', 20) ?></span> برنامه‌ریزی جلسات</h2>
    <p class="sec-subtitle">زمان‌بندی جلسات مشاوره با دانش‌آموزان</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>تنظیم جلسه جدید</h4>
        <p>از فرم سمت راست: دانش‌آموز هدف را انتخاب کنید، موضوع جلسه را بنویسید، تاریخ و ساعت (اختیاری) را تنظیم کنید و توضیحات اضافه بنویسید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>مشاهده جلسات تنظیم‌شده</h4>
        <p>سمت چپ، لیست تمام جلسات آینده و گذشته را می‌بینید. جلسات امروز با برچسب طلایی «جلسه امروز 🔔» مشخص می‌شوند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>لغو جلسه</h4>
        <p>روی دکمه <b>لغو جلسه</b> کلیک کنید و تأیید نمایید. جلسه از حالت «در انتظار برگزاری» به «لغو شده» تغییر می‌کند.</p>
        <div class="tip">🔔 وقتی روز جلسه فرا برسد، هشدار زنگ روی داشبورد شما و دانش‌آموز فعال می‌شود.</div>
      </div>
    </div>
  </div>

  <!-- ======================== 5. آزمون‌ساز ======================== -->
  <div class="guide-section" id="sec-exams">
    <h2><span class="sec-icon"><?= icon('clipboard', 20) ?></span> آزمون‌ساز آنلاین</h2>
    <p class="sec-subtitle">طراحی آزمون تکی یا جامع، با نمره‌دهی کنکوری و پاسخ تشریحی</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>ایجاد آزمون جدید</h4>
        <p>دکمه <span class="guide-key">+ آزمون جدید</span> را بزنید. دو حالت:</p>
        <p>• <b>استاندارد</b> → سوال به سوال وارد می‌کنید</p>
        <p>• <b>دفترچه سریع</b> → فایل PDF یا عکس دفترچه آپلود و کلید پاسخنامه وارد می‌کنید</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>تنظیمات آزمون</h4>
        <p>• <b>عنوان و توضیح</b></p>
        <p>• <b>نوع آزمون:</b> تکی (یک درس) یا جامع (چند درس)</p>
        <p>• <b>زمان‌بندی:</b> کل آزمون یکجا، یا جداگانه هر بخش</p>
        <p>• <b>نمره منفی:</b> فعال/غیرفعال (هر ۳ غلط = ۱ درست خنثی)</p>
        <p>• <b>بازپاسخ‌دهی:</b> آیا دانش‌آموز بعد از آزمون پاسخنامه ببیند؟</p>
        <p>• <b>محدودیت رشته/پایه:</b> آزمون فقط برای رشته یا پایه خاصی باشد</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>اضافه کردن بخش و سوال</h4>
        <p>• <b>بخش جدید</b> → هر بخش = یک درس (مثلاً زیست، شیمی)</p>
        <p>• <b>سوال جدید</b> → متن سوال + ۴ گزینه + پاسخ صحیح + پاسخ تشریحی (اختیاری)</p>
        <p>• <b>آپلود عکس</b> → می‌توانید برای هر سوال عکس آپلود کنید (برای سوالات تصویری)</p>
        <div class="tip">⚡ ذخیره خودکار هر ۵ ثانیه فعال است. اگر مرورگر بسته شود یا اینترنت قطع شود، هیچ سوالی از دست نمی‌رود.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>انتشار آزمون</h4>
        <p>آزمون را از حالت <b>پیش‌نویس</b> به <b>منتشرشده</b> تغییر دهید. به دانش‌آموزان فعال اعلان ارسال می‌شود.</p>
        <p>برای بستن آزمون (دیگر قابل انجام نباشد)، حالت را به <b>بسته‌شده</b> تغییر دهید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۵</span>
      <div class="step-content">
        <h4>مشاهده نتایج و کارنامه</h4>
        <p>دکمه <span class="guide-key">📊 نتایج</span> روی هر آزمون:</p>
        <p>• جدول رتبه‌بندی با درصد کنکوری هر دانش‌آموز</p>
        <p>• مشاهده کارنامه تحلیلی هر نفر (درصد هر درس، درست/غلط/نزده)</p>
        <p>• صدور مجوز شرکت مجدد (حذف پاسخ‌برگ قبلی)</p>
        <p>• کنترل پنل: وضعیت آزمون همه دانش‌آموزان + امکان ریست</p>
      </div>
    </div>
  </div>

  <!-- ======================== 6. گزارش‌ها ======================== -->
  <div class="guide-section" id="sec-reports">
    <h2><span class="sec-icon"><?= icon('edit', 20) ?></span> گزارش‌های حرفه‌ای</h2>
    <p class="sec-subtitle">مشاهده گزارش‌های روزانه، هفتگی و ماهانه‌ی دانش‌آموزان</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>رتبه‌بندی کلی</h4>
        <p>ابتدا لیست دانش‌آموزان با رتبه‌بندی بر اساس امتیاز تسک نمایش داده می‌شود. ستون‌ها: رتبه، نام، امتیاز، کامل/ناقص/قرمز، استریک، درصد پیشرفت.</p>
        <p>روی <span class="guide-key">جزئیات</span> کلیک کنید تا گزارش تکمیلی همان دانش‌آموز باز شود.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>گزارش هفتگی دانش‌آموز</h4>
        <p>• <b>کارت‌های آمار:</b> کامل، ناقص، تسک قرمز، پیشرفت وزنی، میانگین درصد پوشش کورس</p>
        <p>• <b>نمودار هفته:</b> فعالیت ۷ روزه با ستون‌های طلایی</p>
        <p>• <b>تسک‌های قرمز:</b> مودال جداگانه با لیست تسک‌های انجام‌نشده</p>
        <p>• <b>برنامه هفتگی:</b> جزئیات تسک‌های هر روز با امکان ثبت بازخورد</p>
        <div class="tip">💡 با دکمه «گزارش حرفه‌ای» می‌توانید گزارش‌های روزانه/هفتگی/ماهانه دانش‌آموز را با جزئیات کامل ببینید.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>ثبت بازخورد روی تسک</h4>
        <p>روی دکمه <span class="guide-key">💬 ثبت/ویرایش بازخورد</span> هر تسک کلیک کنید. پیام خود را بنویسید و ارسال کنید. دانش‌آموز بازخورد را در پنل خود می‌بیند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>خروجی PDF</h4>
        <p>دکمه <span class="guide-key">📊 دریافت PDF گزارش پیشرفته</span> یک کارنامه حرفه‌ای با تحلیل هوشمند مَدار تولید می‌کند (در صورت فعال بودن ماژول تحلیل).</p>
      </div>
    </div>
  </div>

  <!-- ======================== 7. تحلیل آزمون ======================== -->
  <div class="guide-section" id="sec-analysis">
    <h2><span class="sec-icon"><?= icon('chart', 20) ?></span> تحلیل آزمون داخلی</h2>
    <p class="sec-subtitle">مرور و تحلیل آزمون‌هایی که دانش‌آموزان در سامانه داده‌اند</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>لیست تحلیل‌ها</h4>
        <p>تمام تحلیل‌های آزمون داخلی تمام دانش‌آموزان در یک لیست نمایش داده می‌شود. هر آیتم شامل: نام آزمون، نام دانش‌آموز، نمره کل و درصد ارزیابی.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>فیلتر بر اساس دانش‌آموز</h4>
        <p>با کلیک روی «همه تحلیل‌های دانش‌آموز»، فقط تحلیل‌های همان شخص نمایش داده می‌شود. این برای پیگیری پیشرفت فردی مفید است.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>خروجی PDF</h4>
        <p>هر تحلیل آزمون قابل خروجی‌گیری به‌صورت PDF است. روی دکمه <span class="guide-key">📋 PDF</span> کلیک کنید.</p>
      </div>
    </div>
  </div>

  <!-- ======================== 8. پیام‌رسانی ======================== -->
  <div class="guide-section" id="sec-messages">
    <h2><span class="sec-icon"><?= icon('message', 20) ?></span> پیام‌رسانی و چت</h2>
    <p class="sec-subtitle">ارتباط مستقیم با دانش‌آموزان از طریق چت داخلی</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>لیست گفتگوها (سمت راست)</h4>
        <p>لیست دانش‌آموزانی که با آنها گفتگو داشته‌اید. هر گفتگو شامل: آواتار، نام، آخرین پیام، زمان آخرین پیام و تعداد پیام‌های خوانده‌نشده.</p>
        <p>با نوار جستجو، نام دانش‌آموز را فیلتر کنید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>ارسال پیام</h4>
        <p>• <b>متن:</b> تایپ کنید و <span class="guide-key">ارسال</span> بزنید</p>
        <p>• <b>عکس:</b> از دوربین یا گالری (آیکون 📎)</p>
        <p>• <b>فایل/PDF:</b> آپلود فایل (آیکون 📎 → PDF و فایل)</p>
        <p>• <b>ویس:</b> ضبط صدا با میکروفون (آیکون 🎤)</p>
        <div class="tip">📱 در موبایل، لیست گفتگوها به‌صورت صفحه‌ی جداگانه (پایین صفحه) نمایش داده می‌شود.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>به‌روزرسانی خودکار</h4>
        <p>پیام‌ها هر ۵ ثانیه به‌صورت خودکار بارگذاری می‌شوند. لیست گفتگوها هر ۱۵ ثانیه به‌روز می‌شود. نیاز به رفرش صفحه نیست.</p>
      </div>
    </div>
  </div>

  <!-- ======================== 9. دستاوردها ======================== -->
  <div class="guide-section" id="sec-achievements">
    <h2><span class="sec-icon"><?= icon('trophy', 20) ?></span> دستاوردها و نشان‌ها</h2>
    <p class="sec-subtitle">سیستم گیمیفیکیشن برای انگیزه‌بخشی به دانش‌آموزان</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>ساخت دستاورد جدید</h4>
        <p>دکمه <span class="guide-key">+ دستاورد جدید</span> را بزنید:</p>
        <p>• <b>عنوان:</b> نام نشان (مثلاً «قهرمان هفته»)</p>
        <p>• <b>توضیح:</b> شرط کسب (مثلاً «۷ روز استریک»)</p>
        <p>• <b>آیکون:</b> انتخاب از ۱۲ آیکون موجود</p>
        <p>• <b>شرط کسب:</b></p>
        <p>  - <b>تعداد تسک:</b> خودکار بعد از انجام X تسک</p>
        <p>  - <b>استریک:</b> خودکار بعد از X روز فعالیت پیاپی</p>
        <p>  - <b>دستی:</b> فقط مشاور می‌تواند اعطا کند</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>مشاهده دریافت‌کنندگان</h4>
        <p>روی دکمه <span class="guide-key">👥 دریافت‌کنندگان</span> هر دستاورد کلیک کنید. لیست دانش‌آموزانی که آن نشان را کسب کرده‌اند نمایش داده می‌شود.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>اعطای دستی</h4>
        <p>برای دستاوردهای با شرط «دستی»، دکمه <span class="guide-key">+</span> سبز کنار آن‌ها را بزنید و دانش‌آموز مورد نظر را انتخاب کنید.</p>
        <p>دانش‌آموز اعلان «دستاورد جدید! 🏆» دریافت می‌کند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>ویرایش و حذف</h4>
        <p>• <b>ویرایش:</b> دکمه ✏️ → تغییر عنوان، توضیح، آیکون، شرط و فعال/غیرفعال</p>
        <p>• <b>حذف:</b> دکمه 🗑 قرمز → حذف دائمی دستاورد (تأیید لازم)</p>
      </div>
    </div>
  </div>

  <!-- ======================== 10. تنظیمات ======================== -->
  <div class="guide-section" id="sec-settings">
    <h2><span class="sec-icon"><?= icon('settings', 20) ?></span> تنظیمات سامانه</h2>
    <p class="sec-subtitle">پیکربندی حساب، درس‌ها، پیش‌فرض‌های برنامه‌ریز و ماژول‌های هوشمند</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>مشخصات کاربری و تغییر گذرواژه</h4>
        <p>نام نمایشی، تخصص، شماره موبایل را ویرایش کنید. همچنین می‌توانید گذرواژه خود را تغییر دهید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>مدیریت درس‌ها (پالت رنگی)</h4>
        <p>درس‌های جدید اضافه کنید و رنگ اختصاصی هر درس را انتخاب کنید. این رنگ‌ها در پنل برنامه‌ریز، آزمون‌ساز و خروجی PDF استفاده می‌شوند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>پیش‌فرض‌های برنامه‌ریز</h4>
        <p>• <b>مدت پیش‌فرض تسک:</b> مثلاً ۶۰ دقیقه به‌جای ۹۰</p>
        <p>• <b>تعداد تست پیش‌فرض:</b> مثلاً ۳۰ تست</p>
        <p>• <b>تراکم جدول:</b> راحت یا فشرده</p>
        <p>• <b>رفتار کپی:</b> یک‌بار پیست یا پیست چسبان (چندباره)</p>
        <p>• <b>پرکردن خودکار هوشمند:</b> خانه‌های بعدی بر اساس آخرین انتخاب مشاور پر می‌شوند</p>
        <p>• <b>مدت روزخوانی و آزمونک:</b> مقادیر واحد ویژه</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>فرماندهی ماژول‌های هوشمند</h4>
        <p>با سوئیچ‌های روشن/خاموش، قابلیت‌های زیر را فعال یا غیرفعال کنید:</p>
        <p>• <b>تحلیل هوشمند گزارش‌ها</b> (بتا)</p>
        <p>• <b>تحلیل آزمون آزمایشی/کنکور</b> (بتا)</p>
        <p>• <b>سیستم مرور فاصله‌دار</b></p>
        <p>• <b>سیستم دستاوردها و گیمیفیکیشن</b></p>
        <p>• <b>ترازسنج کشوری و کنکوری</b></p>
        <p>• <b>آسیب‌شناسی تعاملی و ضریب دقت</b></p>
        <p>• <b>ثبت حال روزانه (Mood)</b></p>
        <p>• <b>اعلان‌های PWA</b></p>
        <p>• <b>محافظ تسک‌های منقضی‌شده (قرمز خودکار)</b></p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۵</span>
      <div class="step-content">
        <h4>مدیریت فصل‌های کتاب درسی</h4>
        <p>فصل‌های درسی پایه‌های ۱۰ تا ۱۲ برای رشته‌های تجربی، ریاضی و عمومی قابل افزودن، ویرایش و حذف هستند. همچنین با دکمه «بازیابی پیش‌فرض‌ها» می‌توانید فصل‌های سیستمی را دوباره بارگذاری کنید.</p>
      </div>
    </div>
  </div>

  <!-- ===== Quick Reference ===== -->
  <div class="guide-section" style="border-color: var(--sage);">
    <h2><span class="sec-icon" style="background: var(--sage-glass); color: var(--sage-light);"><?= icon('zap', 20) ?></span> میانبرها و نکات سریع</h2>
    <p class="sec-subtitle">کلیدهای ترکیبی و ترفندهای کاربردی برای کار سریع‌تر</p>

    <div class="guide-step">
      <span class="step-num">⌨️</span>
      <div class="step-content">
        <h4>میانبرهای صفحه‌کلید</h4>
        <p>• <span class="guide-key">Ctrl + Enter</span> → ذخیره سریع تسک (در مودال برنامه‌ریز)</p>
        <p>• <span class="guide-key">Enter</span> روی گزینه چهارم → اضافه کردن خودکار سوال بعدی (در آزمون‌ساز)</p>
        <p>• <span class="guide-key">Esc</span> → بستن مودال‌ها و منوها</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">🖱️</span>
      <div class="step-content">
        <h4>راست‌کلیک در برنامه‌ریز</h4>
        <p>• روی تسک → ویرایش، کپی، تکثیر، تغییر وضعیت، حذف</p>
        <p>• روی سلول خالی → افزودن تسک، پیست تسک کپی‌شده، خالی‌کردن خانه</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">📱</span>
      <div class="step-content">
        <h4>نصب PWA روی گوشی</h4>
        <p>از منوی سایدبار، <span class="guide-key">📱 نصب وب‌اپ</span> را بزنید. در مرورگر موبایل، «Add to Home Screen» را انتخاب کنید. سامانه مانند یک اپلیکیشن روی گوشی نصب می‌شود.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">💡</span>
      <div class="step-content">
        <h4>نکات طلایی</h4>
        <p>• <b>ذخیره خودکار:</b> تمام تغییرات در برنامه‌ریز و آزمون‌ساز به‌صورت خودکار ذخیره می‌شوند</p>
        <p>• <b>حافظه هوشمند:</b> سیستم انتخاب‌های قبلی مشاور را یاد می‌گیرد و پیش‌فرض‌ها را پیشنهاد می‌دهد</p>
        <p>• <b>نمره‌دهی وزنی:</b> تسک کامل = ۱ امتیاز، ناقص = ۰.۵، قرمز = ۰ (با تشویقی تا ۱.۲۵)</p>
        <p>• <b>مرور فاصله‌دار:</b> بر اساس منحنی ابینگهاوس، یادآورهای خودکار برای تثبیت مطالب ساخته می‌شود</p>
        <p>• <b>وضعیت سه‌حالته:</b> هر تسک سه وضعیت دارد: ✓ کامل، ● ناقص، × عدم اجرا</p>
      </div>
    </div>
  </div>

</div>

<?php panel_end(); ?>
