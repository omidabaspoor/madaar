<?php
/** راهنمای کامل پنل دانش‌آموز مَدار */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/models.php';

boot_session();
require_role('student');
$u = current_user();
user_mood_schema_ready();

panel_start('راهنمای پنل دانش‌آموز', 'آموزش گام‌به‌گام تمام بخش‌های پنل شما', 'student', 'guide');
?>

<style>
.guide-shell { max-width: 1100px; margin: 0 auto; }
.guide-hero {
  background: linear-gradient(135deg, rgba(107,136,114,.12), rgba(95,174,123,.08));
  border: 1px solid rgba(107,136,114,.2);
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
  background: radial-gradient(ellipse, rgba(107,136,114,.08) 0%, transparent 70%);
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
  color: var(--sage-light);
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
  width: 28px; height: 28px;
  border-radius: 8px;
  background: var(--sage-glass);
  color: var(--sage-light);
  display: grid;
  place-items: center;
  font-size: .78rem;
  font-weight: 900;
  flex-shrink: 0;
}

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
  background: var(--grad-sage);
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
  background: var(--sage-glass);
  color: var(--sage-light);
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
  background: var(--gold-glass);
  color: var(--gold-light);
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
  background: rgba(107,136,114,.08);
  border-radius: 8px;
  border-right: 3px solid var(--sage);
  font-size: .82rem;
  color: var(--sage-light);
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

@media (max-width: 768px) {
  .guide-section { padding: 22px; }
  .guide-toc-grid { grid-template-columns: 1fr; }
  .guide-hero { padding: 28px 20px; }
  .guide-step { flex-direction: column; gap: 8px; }
  .guide-section .sec-subtitle { padding-right: 0; }
}
</style>

<div class="guide-shell">

  <div class="guide-hero">
    <h1>🎓 راهنمای جامع پنل دانش‌آموز مَدار</h1>
    <p>تمام بخش‌های سامانه را گام‌به‌گام یاد بگیرید. از مشاهده برنامه و انجام تسک‌ها تا آزمون دادن و مرور فاصله‌دار.</p>
  </div>

  <div class="guide-toc">
    <h3><?= icon('list', 20) ?> فهرست بخش‌ها</h3>
    <div class="guide-toc-grid">
      <a href="#sec-dashboard" class="guide-toc-item"><span class="toc-num">۱</span> داشبورد (خانه)</a>
      <a href="#sec-plan" class="guide-toc-item"><span class="toc-num">۲</span> برنامه هفتگی</a>
      <a href="#sec-reports" class="guide-toc-item"><span class="toc-num">۳</span> گزارش‌های حرفه‌ای</a>
      <a href="#sec-meetings" class="guide-toc-item"><span class="toc-num">۴</span> جلسات مشاوره</a>
      <a href="#sec-exams" class="guide-toc-item"><span class="toc-num">۵</span> آزمون‌های آنلاین</a>
      <a href="#sec-analysis" class="guide-toc-item"><span class="toc-num">۶</span> تحلیل آزمون‌ها</a>
      <a href="#sec-messages" class="guide-toc-item"><span class="toc-num">۷</span> پیام با مشاور</a>
      <a href="#sec-progress" class="guide-toc-item"><span class="toc-num">۸</span> نمودار پیشرفت</a>
      <a href="#sec-reviews" class="guide-toc-item"><span class="toc-num">۹</span> برنامه مرور</a>
      <a href="#sec-achievements" class="guide-toc-item"><span class="toc-num">۱۰</span> دستاوردها</a>
      <a href="#sec-profile" class="guide-toc-item"><span class="toc-num">۱۱</span> پروفایل من</a>
      <a href="#sec-tips" class="guide-toc-item"><span class="toc-num">💡</span> نکات طلایی</a>
    </div>
  </div>

  <!-- ===== 1. داشبورد ===== -->
  <div class="guide-section" id="sec-dashboard">
    <h2><span class="sec-icon"><?= icon('home', 20) ?></span> داشبورد (خانه)</h2>
    <p class="sec-subtitle">صفحه اول شما — نمای کلی وضعیت امروز و هفته</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>خوش‌آمدگویی و حال روزانه</h4>
        <p>بالای صفحه، پیام خوش‌آمدگویی متناسب با ساعت روز (صبح بخیر / ظهر بخیر / عصر بخیر / شب بخیر) و تعداد روزهای پیاپی فعالیت (<b>استریک 🔥</b>) را می‌بینید.</p>
        <p>در بخش <b>«حال امروزت چطوره؟»</b>، با کلیک روی ایموجی‌ها (😄🙂😐😴😣) حال و روحیه امروز خود را ثبت کنید. مشاور این اطلاعات را می‌بیند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>هشدار جلسه امروز</h4>
        <p>اگر جلسه مشاوره‌ای برای امروز تنظیم شده باشد، یک بنر طلایی با جزئیات جلسه نمایش داده می‌شود. دکمه <span class="guide-key">ورود به اتاق جلسه</span> شما را به صفحه جلسات می‌برد.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>نوار پیشرفت امروز</h4>
        <p>در کارت خوش‌آمدگویی، نوار پیشرفت نشان می‌دهد چند درصد از تسک‌های امروز را انجام داده‌اید و چند تا کامل/ناقص/مانده دارید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>تسک‌های امروز</h4>
        <p>مهم‌ترین بخش داشبورد. هر تسک شامل: عنوان درس، نوع، مقدار هدف و مدت. سه دکمه وضعیت برای هر تسک:</p>
        <p>• <span class="guide-key">✓</span> <b>کامل</b> → تسک را با موفقیت انجام داده‌اید</p>
        <p>• <span class="guide-key">●</span> <b>ناقص</b> → بخشی از تسک انجام شده (۰.۵ امتیاز)</p>
        <p>• <span class="guide-key">×</span> <b>عدم اجرا</b> → تسک انجام نشده (بدون امتیاز)</p>
        <p>اگر تسک مقدار هدف دارد (مثلاً ۴۰ تست)، بعد از زدن دکمه وضعیت، تعداد انجام‌شده و درصد پوشش کورس را وارد کنید.</p>
        <div class="tip">💡 یادداشت: روی دکمه «📝 افزودن یادداشت» کلیک کنید تا توضیح یا سؤال خود را برای مشاور بنویسید.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۵</span>
      <div class="step-content">
        <h4>یادآوری مرور فاصله‌دار</h4>
        <p>اگر مروری موعدش رسیده باشد، بنری با عنوان <b>«🔁 وقت مرور فاصله‌دار»</b> نمایش داده می‌شود. با کلیک روی آن به صفحه مرورها هدایت می‌شوید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۶</span>
      <div class="step-content">
        <h4>کارت‌های آمار و نمودار هفته</h4>
        <p>۴ کارت: تسک‌های هفته، درصد پیشرفت هفته، استریک و درصد امروز. نمودار ستونی ۷ روز اخیر با ستون طلایی برای روز جاری.</p>
      </div>
    </div>
  </div>

  <!-- ===== 2. برنامه هفتگی ===== -->
  <div class="guide-section" id="sec-plan">
    <h2><span class="sec-icon"><?= icon('calendar', 20) ?></span> برنامه هفتگی</h2>
    <p class="sec-subtitle">مشاهده برنامه هفتگی طراحی‌شده توسط مشاور</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>ساختار جدول</h4>
        <p>برنامه به‌صورت جدول <b>۷ روز × ۸ واحد</b> (واحد اول تا هفتم + واحد ویژه) نمایش داده می‌شود. هر سلول شامل تسک‌های آن روز و واحد است.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>وضعیت رنگی تسک‌ها</h4>
        <p>• 🟢 <b>کامل</b> → انجام شده</p>
        <p>• 🟡 <b>ناقص</b> → بخشی انجام شده</p>
        <p>• 🔴 <b>عدم اجرا</b> → منقضی و انجام نشده</p>
        <p>• ⚪ <b>در انتظار</b> → هنوز نوبتش نرسیده</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>ثبت یادداشت و بازخورد</h4>
        <p>روی دکمه <span class="guide-key">📝 افزودن یادداشت</span> هر تسک کلیک کنید. یادداشت شما برای مشاور نمایش داده می‌شود. اگر مشاور بازخورد گذاشته باشد، زیر تسک با عنوان <b>«بازخورد مشاور»</b> می‌بینید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>تغییر هفته</h4>
        <p>با فلش‌های چپ و راست بالای صفحه، هفته‌های قبل و بعد را ببینید. همچنین می‌توانید <span class="guide-key">📋 PDF برنامه</span> را دانلود و چاپ کنید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۵</span>
      <div class="step-content">
        <h4>واحد ویژه</h4>
        <p>ستون آخر شامل سه تسک پیش‌فرض است: <b>روزخوانی</b> (مرور سبک)، <b>مرور ویژه ۱۵د</b> و <b>آزمونک</b>. این تسک‌ها برای تثبیت مطالب حیاتی هستند.</p>
      </div>
    </div>
  </div>

  <!-- ===== 3. گزارش‌ها ===== -->
  <div class="guide-section" id="sec-reports">
    <h2><span class="sec-icon"><?= icon('edit', 20) ?></span> گزارش‌های حرفه‌ای</h2>
    <p class="sec-subtitle">ثبت گزارش روزانه، هفتگی و ماهانه برای مشاور</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>انواع گزارش</h4>
        <p>• <b>روزانه:</b> ساعت مطالعه، تعداد تست، تسک‌های قرمز، ساعت خواب، تمرکز، انرژی، استرس، اتلاف وقت</p>
        <p>• <b>هفتگی:</b> مرور کلی هفته، نقاط قوت و ضعف، چالش‌ها و راهکارها</p>
        <p>• <b>ماهانه:</b> ارزیابی کلی ماه، هدف‌گذاری ماه بعد</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>ثبت گزارش</h4>
        <p>فرم گزارش شامل فیلدهای مختلف است. فرم را پر کنید و <span class="guide-key">ارسال</span> بزنید. مشاور گزارش را می‌بیند و می‌تواند یادداشت بگذارد.</p>
        <div class="tip">⚠️ گزارش روزانه پس از پایان شب قفل می‌شود. حتماً قبل از خواب گزارش را ثبت کنید!</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>تحلیل هوشمند (بتا)</h4>
        <p>اگر مشاور ماژول تحلیل هوشمند را فعال کرده باشد، پس از ثبت گزارش، نمره ارزیابی کلی، شاخص بازدهی، هشدارها و پیشنهادهای سیستمی نمایش داده می‌شود.</p>
      </div>
    </div>
  </div>

  <!-- ===== 4. جلسات ===== -->
  <div class="guide-section" id="sec-meetings">
    <h2><span class="sec-icon"><?= icon('calendar', 20) ?></span> جلسات مشاوره</h2>
    <p class="sec-subtitle">مشاهده جلسات تنظیم‌شده توسط مشاور</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>لیست جلسات</h4>
        <p>تمام جلسات آینده و گذشته نمایش داده می‌شوند: عنوان، تاریخ، ساعت و توضیحات. جلسات امروز با برچسب طلایی مشخص هستند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>هشدار جلسه</h4>
        <p>در روز جلسه، بنر طلایی در داشبورد + پیام اعلان دریافت می‌کنید. فراموش نکنید!</p>
      </div>
    </div>
  </div>

  <!-- ===== 5. آزمون‌ها ===== -->
  <div class="guide-section" id="sec-exams">
    <h2><span class="sec-icon"><?= icon('clipboard', 20) ?></span> آزمون‌های آنلاین</h2>
    <p class="sec-subtitle">شرکت در آزمون‌هایی که مشاور طراحی کرده</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>لیست آزمون‌ها</h4>
        <p>آزمون‌های منتشرشده برای شما: نام آزمون، تعداد سوال، مدت، وضعیت (شروع‌نشده / در حال انجام / ثبت‌شده). هر آزمون تگ رشته و پایه هم دارد.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>شرکت در آزمون</h4>
        <p>روی آزمون کلیک و <span class="guide-key">شروع آزمون</span> بزنید:</p>
        <p>• تایمر هماهنگ با سرور (غیرقابل تقلب)</p>
        <p>• علامت‌گذاری سوال برای مرور بعدی 🚩</p>
        <p>• ذخیره خودکار هر ۵ ثانیه</p>
        <p>• محیط تمام‌صفحه و موبایل‌محور</p>
        <p>• با اتمام زمان، آزمون خودکار ثبت می‌شود</p>
        <div class="tip">⚡ نگران قطع اینترنت نباشید! پاسخ‌ها هر ۵ ثانیه ذخیره می‌شوند. اگر صفحه بسته شود، از همان‌جا ادامه می‌دهید.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>آزمون‌های چندبخشی (جامع)</h4>
        <p>هر بخش = یک درس. هر بخش تایمر جداگانه دارد. با اتمام وقت یک بخش، به بخش بعدی می‌روید. نمی‌توانید به بخش قبلی برگردید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۴</span>
      <div class="step-content">
        <h4>مشاهده کارنامه</h4>
        <p>بعد از ثبت آزمون: درصد کل، درصد هر درس، پاسخنامه با گزینه صحیح و پاسخ تشریحی. امکان دانلود <span class="guide-key">📋 PDF دفترچه</span> و <span class="guide-key">⭐ PDF کارنامه</span>.</p>
      </div>
    </div>
  </div>

  <!-- ===== 6. تحلیل آزمون‌ها ===== -->
  <div class="guide-section" id="sec-analysis">
    <h2><span class="sec-icon"><?= icon('chart', 20) ?></span> تحلیل آزمون‌ها</h2>
    <p class="sec-subtitle">تحلیل عملکرد پس از آزمون — ریشه‌یابی اشتباهات</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>ثبت تحلیل پس از آزمون</h4>
        <p>بعد از هر آزمون، فرم تحلیل شامل:</p>
        <p>• ساعت خواب و کیفیت خواب</p>
        <p>• سطح استرس و تمرکز</p>
        <p>• مدیریت زمان در آزمون</p>
        <p>• دلیل اصلی اشتباهات هر سوال (بی‌دقتی، عدم مطالعه، کمبود وقت...)</p>
        <p>• نکته طلایی یادگرفته‌شده</p>
        <p>• استراتژی برای آزمون بعدی</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>مشاهده تحلیل‌های قبلی</h4>
        <p>لیست تمام تحلیل‌ها: نام آزمون، نمره کل، درصد ارزیابی. مشاور تحلیل‌های شما را می‌بیند.</p>
        <div class="tip">💡 تحلیل دقیق آزمون = کلید پیشرفت! هر اشتباه یک فرصت یادگیری است.</div>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>خروجی PDF</h4>
        <p>دکمه <span class="guide-key">📋 PDF</span> روی هر تحلیل آزمون، کارنامه کامل با تحلیل هوشمند تولید می‌کند.</p>
      </div>
    </div>
  </div>

  <!-- ===== 7. پیام‌رسانی ===== -->
  <div class="guide-section" id="sec-messages">
    <h2><span class="sec-icon"><?= icon('message', 20) ?></span> پیام با مشاور</h2>
    <p class="sec-subtitle">ارتباط مستقیم با مشاور از طریق چت داخلی</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>ارسال پیام</h4>
        <p>• <b>متن:</b> تایپ کنید و ارسال بزنید</p>
        <p>• <b>عکس:</b> از دوربین یا گالری (📎 → دوربین/گالری)</p>
        <p>• <b>فایل/PDF:</b> آپلود فایل (PDF، ورد، اکسل، پاورپوینت، txt، zip)</p>
        <p>• <b>ویس:</b> ضبط صدا با میکروفون (🎤 → حداکثر ۵ دقیقه)</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>به‌روزرسانی خودکار</h4>
        <p>پیام‌ها هر ۵ ثانیه به‌صورت خودکار بارگذاری می‌شوند. نیازی به رفرش صفحه نیست.</p>
      </div>
    </div>
  </div>

  <!-- ===== 8. نمودار پیشرفت ===== -->
  <div class="guide-section" id="sec-progress">
    <h2><span class="sec-icon"><?= icon('chart', 20) ?></span> نمودار پیشرفت</h2>
    <p class="sec-subtitle">نمای گرافیکی از روند پیشرفت تحصیلی</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>پیشرفت به تفکیک درس</h4>
        <p>نمودار دایره‌ای که درصد پیشرفت هر درس را نشان می‌دهد. دروس با رنگ‌های مختلف مشخص شده‌اند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>روند هفتگی</h4>
        <p>نمودار ستونی تغییرات پیشرفت شما در هفته‌های اخیر. با نگاه سریع متوجه می‌شوید آیا روند صعودی دارید یا نزولی.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>استریک</h4>
        <p>تعداد روزهایی که پشت‌سرهم فعالیت داشته‌اید. هرچه بالاتر، بهتر! استریک در داشبورد و بالای تمام صفحات نمایش داده می‌شود.</p>
      </div>
    </div>
  </div>

  <!-- ===== 9. مرور فاصله‌دار ===== -->
  <div class="guide-section" id="sec-reviews">
    <h2><span class="sec-icon"><?= icon('repeat', 20) ?></span> برنامه مرور</h2>
    <p class="sec-subtitle">مرور فاصله‌دار بر اساس منحنی فراموشی ابینگهاوس</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>چطور کار می‌کند؟</h4>
        <p>هر زمان تسکی از نوع مطالعه، درسنامه یا کتاب درسی را کامل کنید، سیستم به‌صورت خودکار یادآورهای مرور در فواصل مشخص (۱ روز، ۳ روز، ۷ روز، ۱۴ روز و...) ایجاد می‌کند.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>سه تب مرور</h4>
        <p>• <b>🔔 موعد امروز / عقب‌افتاده:</b> مرورهای فوری</p>
        <p>• <b>📅 روزهای آینده:</b> مرورهای برنامه‌ریزی‌شده</p>
        <p>• <b>✓ انجام‌شده:</b> مرورهای تکمیل‌شده</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>ثبت کیفیت مرور</h4>
        <p>بعد از انجام هر مرور، کیفیت را ثبت کنید: <b>سخت</b>، <b>خوب</b> یا <b>آسان</b>. سیستم بر اساس این اطلاعات، فواصل مرور بعدی را تنظیم می‌کند.</p>
        <div class="tip">💡 مرور فاصله‌دار = بهترین روش علمی برای تثبیت مطالب در حافظه بلندمدت!</div>
      </div>
    </div>
  </div>

  <!-- ===== 10. دستاوردها ===== -->
  <div class="guide-section" id="sec-achievements">
    <h2><span class="sec-icon"><?= icon('trophy', 20) ?></span> دستاوردها</h2>
    <p class="sec-subtitle">نشان‌ها و جوایزی که کسب کرده‌اید</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>دستاوردهای خودکار</h4>
        <p>برخی نشان‌ها خودکار اعطا می‌شوند:</p>
        <p>• <b>شروع‌کننده 🚀</b> → اولین تسک انجام‌شده</p>
        <p>• <b>استمرار 🔥</b> → ۳ روز پیاپی فعالیت</p>
        <p>• <b>جنگجوی هفته 🔥</b> → ۷ روز استریک</p>
        <p>• <b>نیم‌قرن 🎯</b> → ۵۰ تسک انجام‌شده</p>
        <p>• <b>صدتایی 🏆</b> → ۱۰۰ تسک انجام‌شده</p>
        <p>• <b>حرفه‌ای ⭐</b> → ۲۵۰ تسک انجام‌شده</p>
        <p>• <b>وفادار ❤️</b> → ۳۰ روز استریک</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>دستاوردهای دستی</h4>
        <p>نشان <b>«منتخب مشاور ✨»</b> فقط توسط مشاور و به‌صورت دستی اعطا می‌شود. وقتی مشاور این نشان را به شما بدهد، اعلان دریافت می‌کنید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۳</span>
      <div class="step-content">
        <h4>مشاهده نشان‌ها</h4>
        <p>نشان‌های کسب‌شده با رنگ روشن و نشان‌های باقی‌مانده با رنگ محو نمایش داده می‌شوند.</p>
      </div>
    </div>
  </div>

  <!-- ===== 11. پروفایل ===== -->
  <div class="guide-section" id="sec-profile">
    <h2><span class="sec-icon"><?= icon('user', 20) ?></span> پروفایل من</h2>
    <p class="sec-subtitle">مشاهده و ویرایش اطلاعات شخصی</p>

    <div class="guide-step">
      <span class="step-num">۱</span>
      <div class="step-content">
        <h4>اطلاعات نمایشی</h4>
        <p>نام، نام‌کاربری، رشته، پایه و مشاور شما نمایش داده می‌شود.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">۲</span>
      <div class="step-content">
        <h4>تغییر گذرواژه</h4>
        <p>گذرواژه فعلی و گذرواژه جدید را وارد کنید و تغییر دهید.</p>
      </div>
    </div>
  </div>

  <!-- ===== نکات طلایی ===== -->
  <div class="guide-section" id="sec-tips" style="border-color: var(--gold);">
    <h2><span class="sec-icon" style="background: var(--gold-glass); color: var(--gold-light);"><?= icon('sparkles', 20) ?></span> نکات طلایی و ترفندها</h2>
    <p class="sec-subtitle">کلیدهای موفقیت در استفاده از مَدار</p>

    <div class="guide-step">
      <span class="step-num">🔑</span>
      <div class="step-content">
        <h4>قانون ۵ دقیقه</h4>
        <p>اگر حس درس خواندن ندارید، فقط ۵ دقیقه شروع کنید. بعد از ۵ دقیقه، مغزتان وارد فاز تمرکز می‌شود و ادامه دادن آسان‌تر خواهد بود.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">🔑</span>
      <div class="step-content">
        <h4>استریک را حفظ کنید</h4>
        <p>حتی یک روز فعالیت ساده (حتی ۱ تسک کوچک) استریک شما را زنده نگه می‌دارد. استریک = انگیزه!</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">🔑</span>
      <div class="step-content">
        <h4>گزارش روزانه را فراموش نکنید</h4>
        <p>گزارش‌های روزانه بهترین ابزار مشاور برای درک وضعیت شما هستند. صادقانه و کامل پر کنید.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">🔑</span>
      <div class="step-content">
        <h4>مرور فاصله‌دار را جدی بگیرید</h4>
        <p>۸۰٪ فراموشی مطالب در ۲۴ ساعت اول رخ می‌دهد. مرور فاصله‌دار این فراموشی را تا ۹۰٪ کاهش می‌دهد.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">🔑</span>
      <div class="step-content">
        <h4>تحلیل آزمون مهم‌تر از خود آزمون است</h4>
        <p>بعد از هر آزمون، تحلیل کامل ثبت کنید. ریشه‌یابی اشتباهات = جلوگیری از تکرار آن‌ها در کنکور اصلی.</p>
      </div>
    </div>

    <div class="guide-step">
      <span class="step-num">📱</span>
      <div class="step-content">
        <h4>نصب PWA روی گوشی</h4>
        <p>از منوی سایدبار، <span class="guide-key">📱 نصب وب‌اپ</span> را بزنید. مَدار مثل یک اپلیکیشن روی گوشی نصب می‌شود و حتی بدون اینترنت هم کار می‌کند.</p>
      </div>
    </div>
  </div>

</div>

<?php panel_end(); ?>
