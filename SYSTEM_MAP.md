# 🗺 مَدار (Madar Study OS) — نقشه‌ی کامل سیستم

> **تاریخ بررسی**: ۲۰۲۶-۰۶-۲۳
> **نسخه**: 1.0.0
> **وضعیت**: کلون کامل + حذف `.git` ✅

---

## 📌 اطلاعات کلی

| مورد | مقدار |
|---|---|
| **نام** | مَدار · Madar Study OS |
| **صاحب** | دکتر سجاد صیادی |
| **دامنه** | https://madaar-edu.ir |
| **تکنولوژی** | PHP 8 + MySQL/MariaDB + PWA |
| **معماری** | رندر سمت سرور (SSR) + AJAX polling |
| **زبان** | فارسی (RTL کامل) |
| **UI** | دارک‌مود، ریسپانسیو، فونت وزیرمتن |

---

## 🏗 ساختار کلی فولدرها

```
madaar/                                    # ریشه‌ی پروژه (۲۲MB فایل)
│
├── 📁 admin/      (۲۴ فایل) ─ پنل مشاور و مدیر ارشد
├── 📁 student/    (۲۴ فایل) ─ پنل دانش‌آموز
├── 📁 auth/       (۴ فایل)  ─ ورود، ثبت‌نام، pending، logout
├── 📁 api/        (۱۴ فایل) ─ endpointهای AJAX
├── 📁 includes/   (۱۸ فایل) ─ توابع مشترک، models، layout
├── 📁 assets/
│   ├── css/      (۱۱ فایل، ۲۴۱KB)
│   ├── js/       (۱۰ فایل، ۳۸۱KB)
│   ├── icons/    (۶ فایل PNG)
│   ├── fonts/    (Vazirmatn.woff2 + DejaVuSans)
│   ├── img/      (لوگو + بنر OG)
│   └── js/vendor/(PDF.js)
├── 📁 sql/        (۲۳ فایل، شامل schema + ۲۲ migration)
├── 📁 config/     (config.php)
├── 📁 scripts/    (تولید کلید VAPID)
├── favicon.ico
├── index.php      ← صفحه‌ی فرود (landing)
├── install.php    ← نصب‌کننده‌ی خودکار
├── manifest.php   ← PWA manifest
├── sw.js          ← Service Worker
├── sitemap.php + sitemap.xml
├── robots.txt
├── offline.php    ← صفحه‌ی آفلاین PWA
└── pwa_help.php   ← راهنمای نصب وب‌اپ
```

---

## 👥 نقش‌ها و دسترسی‌ها

| نقش | دسترسی |
|---|---|
| **`admin`** (مشاور ارشد) | مدیریت کل سیستم + همه‌ی دانش‌آموزان + مدیریت مشاوران |
| **`advisor`** (مشاور) | دانش‌آموزان اختصاصی (یا همه با حالت `all`) |
| **`student`** (دانش‌آموز) | فقط برنامه‌ی خودش + آزمون‌های مجاز |

### کنترل دسترسی پیشرفته
- جدول `advisor_student_access` → مشاور محدود (`restricted`) فقط دانش‌آموزان تخصیص‌یافته را می‌بیند
- مشاور ارشد (`admin`) با حالت `all` همه‌ی سیستم را می‌بیند
- سطوح دسترسی قابل تغییر از پنل `admin/advisors.php`

---

## 🗄 ساختار دیتابیس (۲۸ جدول)

### جداول اصلی
| جدول | کاربرد |
|---|---|
| **`users`** | کاربران (admin/advisor/student) با role + status + access_mode |
| **`subjects`** | درس‌ها با رنگ و آیکون |
| **`plans`** | برنامه‌ی هفتگی هر دانش‌آموز (draft/published/archived) |
| **`tasks`** | تسک‌های هر برنامه (۸ واحد × ۷ روز) |
| **`chapters`** | فصل‌های کتاب‌های درسی (دهم تا دوازدهم، ۳ رشته) |
| **`achievements`** | دستاوردهای قابل تعریف |
| **`student_achievements`** | دستاوردهای کسب‌شده |
| **`daily_logs`** | لاگ روزانه (برای استریک و نمودار) |
| **`messages`** | پیام‌های چت با ضمیمه |
| **`notifications`** | اعلان‌های سیستم |
| **`web_push_subscriptions`** | اشتراک‌های Web Push (VAPID) |
| **`advisor_settings`** | تنظیمات هر مشاور (مثلاً planner memory) |
| **`planner_memory`** | حافظه‌ی هوشمند برنامه‌ریزی |

### سیستم آزمون (۵ جدول)
| جدول | کاربرد |
|---|---|
| **`exams`** | آزمون (single/comprehensive) |
| **`exam_sections`** | بخش‌های آزمون (هر درس) |
| **`exam_questions`** | سوالات ۴ گزینه‌ای |
| **`exam_attempts`** | شرکت هر دانش‌آموز |
| **`exam_answers`** | پاسخ هر سوال + diagnostic |

### سیستم مشاوره و جلسات
| جدول | کاربرد |
|---|---|
| **`consultation_sessions`** | جلسات هماهنگ‌شده (موجود، ۸۰٪ آماده) |

### سیستم گزارش و مرور
| جدول | کاربرد |
|---|---|
| **`student_reports`** | گزارش روزانه/هفتگی/ماهانه دانش‌آموز |
| **`mock_exam_reports`** | تحلیل آزمون‌های آزمایشی بیرونی |
| **`review_reminders`** | مرورهای فاصله‌دار (Spaced Repetition) |

### جداول کمکی
| جدول | کاربرد |
|---|---|
| **`advisor_student_access`** | تخصیص دانش‌آموزان به مشاور |
| **`activity_log`** | لاگ ممیزی تمام اقدامات |

---

## 🎯 ویژگی‌های پیاده‌سازی‌شده (فهرست کامل)

### 🔐 احراز هویت و امنیت
- ✅ ورود با bcrypt + rate limit (۵ تلاش → ۳۰ ثانیه قفل)
- ✅ ثبت‌نام دانش‌آموز → وضعیت `pending` → تأیید توسط مشاور
- ✅ CSRF Token در تمام فرم‌ها + APIها
- ✅ Session با httpOnly + SameSite=Lax + Secure (HTTPS)
- ✅ Remember Me با توکن hashed
- ✅ RBAC با ۳ نقش + `require_role()`, `require_chief_advisor()`
- ✅ لاگ ممیزی تمام اقدامات مهم

### 📚 سیستم برنامه‌ریزی
- ✅ برنامه‌ی هفتگی: ۷ روز × ۷ واحد (+ واحد ویژه)
- ✅ **سه‌حالته‌ی هوشمند تسک**: pending → full / partial / missed
- ✅ **خودکار-قرمز**: تسک‌های عقب‌مانده قبل از دسترسی دانش‌آموز، قرمز نمی‌شوند
- ✅ **Drag & Drop** تسک‌ها بین خانه‌ها
- ✅ **پرکردن هوشمند** با AI suggestions (یادگیری انتخاب‌های قبلی)
- ✅ کپی از هفته‌ی قبل + کپی به دانش‌آموز دیگر
- ✅ پیش‌فرض‌های واحد ویژه (روزخوانی، مرور، آزمونک)
- ✅ ۱۱ نوع تسک: مطالعه، تست، مرور، کتاب، تشریحی، روزخوانی، آزمونک، تحلیل، ویژه، آزمون، دلخواه

### 📊 گزارش و پیشرفت
- ✅ نمودار هفتگی + فعالیت روزانه
- ✅ پیشرفت به تفکیک درس (با رنگ اختصاصی)
- ✅ استریک روزانه (روزهای پیاپی فعالیت)
- ✅ حال روزانه (mood) با ۵ emoji
- ✅ یادداشت شخصی دانش‌آموز روی هر تسک
- ✅ بازخورد مشاور روی هر تسک

### 🎖 دستاوردها (Gamification)
- ✅ ۸ دستاورد پیش‌فرض + قابل تعریف نامحدود
- ✅ ۳ نوع شرط: tasks_done / streak / manual (دستی)
- ✅ اعطای خودکار + اعطای دستی توسط مشاور
- ✅ اعلان خودکار هنگام کسب

### 🎓 سیستم آزمون (بسیار پیشرفته!)
- ✅ **دو حالت ساخت**:
  - **Quick Sheet**: آپلود عکس/PDF دفترچه + ورود کلید (مثل کنکور واقعی)
  - **Standard**: ساخت دستی با بخش‌بندی + تایپ سوالات
- ✅ **پنل دانش‌آموز**:
  - پاسخ‌برگ حبابی تعاملی
  - نمایش دفترچه با زوم و scroll
  - تایمر امن (سمت سرور) + deadline_at
  - پشتیبان‌گیری خودکار هر ۵ ثانیه
  - Flag برای مرور سوالات
  - تشخیص PDF با PDF.js
- ✅ **نمره‌دهی کنکوری**: `(3×درست - غلط) / (3×کل) × 100`
- ✅ **صدور مجوز شرکت مجدد** توسط مشاور
- ✅ **کارنامه‌ی تحلیلی** با PDF (دفترچه + پاسخنامه)
- ✅ **انتخاب هدف**: برای همه / فقط رشته خاص / فقط پایه خاص
- ✅ **حالت تکی / جامع**

### 🔄 سیستم مرور هوشمند (Spaced Repetition)
- ✅ ساخت خودکار reminder از هر تسک مطالعه/کتاب
- ✅ فاصله‌های ۱، ۳، ۷، ۱۶، ۳۰ روزه بر اساس نوع درس
- ✅ کیفیت مرور (hard/good/easy) → تنظیم فاصله‌ی بعدی
- ✅ ۳ تب: موعد امروز / آینده / انجام‌شده
- ✅ فیلتر بر اساس درس + جستجو

### 💬 پیام‌رسانی
- ✅ چت دوطرفه با ضمیمه
- ✅ ۴ نوع ضمیمه: عکس، ویس، PDF، فایل
- ✅ ضبط ویس از مرورگر
- ✅ Mobile-first با drag-sheet
- ✅ اعلان خوانده‌نشده

### 🏆 سیستم دستاورد + گزارش حرفه‌ای
- ✅ گزارش روزانه/هفتگی/ماهانه با snapshot خودکار
- ✅ تحلیل پیشرفته (subject breakdown)
- ✅ یادآوری ثبت گزارش

### 📅 جلسات مشاوره
- ✅ جدول `consultation_sessions` ساخته شده (migration خودکار)
- ✅ پنل مشاور: `admin/schedule_meeting.php` (تنظیم، لغو، اعلان)
- ✅ پنل دانش‌آموز: `student/meetings.php`
- ✅ اعلان روی داشبورد روز جلسه
- ⚠️ **اما**: دکمه‌ی "ورود به اتاق جلسه" به صفحه‌ای واقعی متصل نیست (فقط UI)

### 📲 PWA (Progressive Web App)
- ✅ Service Worker (`sw.js`) با offline-first
- ✅ Manifest با آیکون‌های ۱۶/۳۲/۶۴/۱۹۲/۵۱۲
- ✅ صفحه‌ی آفلاین (`offline.php`)
- ✅ قابل نصب روی موبایل

### 🔔 Web Push Notifications
- ✅ VAPID keys فعال (public/private در config)
- ✅ جدول اشتراک‌ها
- ✅ Service Worker هندلر push
- ✅ ارسال خودکار هنگام `notify()`

### 📊 تحلیل آزمون آزمایشی (Mock Exam)
- ✅ تحلیل خودکار: overall score، نقاط ضعف/قوت، alerts، recommendations
- ✅ ۱۶ نوع ریشه‌یابی خطا (careless_calc، concept، time_rush و...)
- ✅ رفتارشناسی (خواب، استرس، تمرکز، مدیریت زمان)
- ✅ خروجی PDF تحلیلی

### 📄 گزارش‌های PDF (با فونت فارسی)
- ✅ برنامه‌ی هفتگی (`plan_pdf.php`)
- ✅ کارنامه‌ی آزمون (`exam_pdf.php` + `exam_solution_pdf.php`)
- ✅ تحلیل آزمون آزمایشی (`mock_exam_pdf.php`)
- ✅ گزارش جامع دانش‌آموز (`student_report_pdf.php`)
- ✅ تحلیل آزمون داخلی (`internal_exam_analysis_pdf.php`)

---

## 🧩 فایل‌های مهم به تفکیک عملکرد

### 📁 `/admin` — پنل مشاور
| فایل | عملکرد |
|---|---|
| `dashboard.php` | داشبورد با KPI + نمودار + اعلان جلسات امروز |
| `students.php` | لیست + تأیید + مسدودسازی دانش‌آموزان |
| `plan_builder.php` | ⭐ سازنده‌ی برنامه هفتگی (drag-drop + task editor) |
| `plans.php` | لیست برنامه‌های همه‌ی دانش‌آموزان |
| `exams.php` | لیست آزمون‌ها + ساخت جدید |
| `exam_builder.php` | ⭐ استودیوی ساخت آزمون (quick_sheet / standard) |
| `exam_results.php` | نتایج + رتبه‌بندی + صدور مجوز شرکت مجدد |
| `reports.php` | گزارش عملکرد دانش‌آموز (با بازخورد) |
| `student_reports.php` | گزارش حرفه‌ای (daily/weekly/monthly) |
| `reviews.php` | مرورهای فاصله‌دار دانش‌آموزان |
| `achievements.php` | تعریف + اعطای دستاورد |
| `advisors.php` | 👑 مدیریت مشاوران (فقط سجاد صیادی) |
| `schedule_meeting.php` | تنظیم جلسات مشاوره |
| `messages.php` | چت با دانش‌آموزان |
| `logs.php` | لاگ ممیزی فعالیت‌ها |
| `settings.php` | تنظیمات سیستم |
| `guide.php` | راهنمای مشاور |

### 📁 `/student` — پنل دانش‌آموز
| فایل | عملکرد |
|---|---|
| `dashboard.php` | خانه با سلام، تسک‌های امروز، mood، نمودار |
| `plan.php` | برنامه‌ی هفتگی با تب‌های روزانه |
| `reports.php` | ثبت گزارش روزانه/هفتگی/ماهانه |
| `progress.php` | نمودار پیشرفت بلندمدت |
| `reviews.php` | ⭐ مرورهای هوشمند (فیلتر درس + جستجو) |
| `exams.php` | لیست آزمون‌های مجاز |
| `exam.php` | ⭐ محیط آزمون (پاسخ‌برگ حبابی + PDF viewer) |
| `exam_result.php` | کارنامه + diagnostic |
| `exam_analyses.php` | تحلیل شخصی آزمون‌ها |
| `mock_exam.php` | ثبت + تحلیل آزمون آزمایشی بیرونی |
| `internal_exam_analysis.php` | تحلیل آزمون داخلی |
| `meetings.php` | جلسات مشاوره من |
| `messages.php` | چت با مشاور |
| `achievements.php` | دستاوردهای من |
| `profile.php` | تنظیمات حساب |

### 📁 `/api` — endpointهای AJAX
| فایل | عمل |
|---|---|
| `tasks.php` | CRUD تسک + toggle سه‌حالته + feedback |
| `exam_take.php` | پاسخ/flag/submit/timeout (سمت سرور امن) |
| `exam_builder.php` | ساخت آزمون + آپلود sheet + publish |
| `chapters.php` | دریافت فصل‌های کتاب درسی |
| `reviews.php` | ثبت/لغو مرور فاصله‌دار |
| `messages.php` | ارسال/دریافت پیام + ضمیمه |
| `notifications.php` | اعلان‌های realtime |
| `mood.php` | ثبت حال روزانه |
| `profile.php` | آپدیت پروفایل |
| `push_subscription.php` | اشتراک Web Push |
| `achievements.php` | CRUD دستاوردها |
| `achievement_recipients.php` | لیست دریافت‌کنندگان |
| `reports.php` | ثبت گزارش دانش‌آموز |
| `check_status.php` | health check |

### 📁 `/includes` — هسته‌ی سیستم
| فایل | عملکرد |
|---|---|
| `db.php` | PDO singleton با utf8mb4 |
| `auth.php` | Session, CSRF, RBAC, Notify, Web Push |
| `helpers.php` | تاریخ شمسی، اعداد فارسی، URL، JSON |
| `layout.php` | `<head>` + SEO + OG + Schema.org |
| `panel_layout.php` | سایدبار + topbar + bottom-nav موبایل |
| `models.php` | ۹۰۷ خط! تمام کوئری‌های داده‌ای + Self-Healing DB |
| `chat_view.php` | UI چت مشترک (admin/student) |
| `reporting.php` | سیستم گزارش‌دهی حرفه‌ای |
| `review_scheduler.php` | موتور Spaced Repetition |
| `mock_exam.php` | تحلیل آزمون آزمایشی با AI heuristics |
| `planner_settings.php` | حافظه‌ی هوشمند برنامه‌ریز |
| `web_push.php` | ارسال Web Push با VAPID |
| `log.php` | لاگ ممیزی |
| `icons.php` | SVG آیکون‌ها |
| `meetings.php` | ⭐ جلسات مشاوره (فقط ذخیره‌سازی، بدون UI meeting room) |
| `chapter_data.php` | داده‌ی فصل‌های کتاب (PHP seed) |
| `internal_exam_analysis.php` | تحلیل آزمون داخلی |
| `result_view.php` | رندر کارنامه مشترک |

---

## 🚧 نقاط نیمه‌تمام / نیازمند توسعه

### ۱. ⏳ سیستم جلسه‌ی آنلاین (Meeting Room)
- ✅ جدول `consultation_sessions` ساخته شده + اعلان
- ✅ UI تنظیم جلسه (`schedule_meeting.php`)
- ✅ UI نمایش جلسه (`meetings.php`)
- ❌ **اما صفحه‌ی واقعی "ورود به اتاق جلسه" وجود ندارد**
- ❌ هیچ integration با WebRTC/Jitsi/Anything

### ۲. ⚠️ سیستم Whiteboard
- ❌ اصلاً وجود ندارد — باید از صفر ساخته شود

### ۳. 💡 سیستم گزارش حرفه‌ای
- ✅ جداول + helper functions آماده
- ✅ UI ثبت گزارش روزانه
- ⚠️ گزارش هفتگی/ماهانه ساده‌تر از حد انتظار

### ۴. 🎯 پیشنهادهای هوشمند برنامه‌ریز
- ✅ `planner_memory` کار می‌کنه
- ⚠️ AI پیشنهاد کامل تسک فقط بر اساس انتخاب‌های قبلی (نه AI واقعی)

---

## 📊 خلاصه‌ی آمار پروژه

```
📦 کل فایل‌ها: ~۱۰۰ فایل PHP + ۳۵ فایل asset
📝 کل کد PHP: ~۱۷٬۷۸۷ خط
📦 کل فایل‌های CSS/JS: ۳۸۱KB
🗄 جداول DB: ۲۸ جدول (Self-Healing)
🌍 چند زبانه: ❌ فقط فارسی
📱 PWA: ✅ کامل
🔐 امنیت: ✅ بسیار بالا (CSRF + RBAC + bcrypt + Web Push)
🎨 UI/UX: ✅ حرفه‌ای، دارک‌مود، ریسپانسیو
📚 مستندات: ❌ فقط README در SQL ها
🧪 تست: ❌ تست واحد ندارد
```

---

## 🛠 پیش‌نیازهای نصب

```
✅ PHP 8.0+
✅ MySQL 5.7+ / MariaDB 10.3+
✅ اکستنشن‌های: PDO, mbstring, gd, json, fileinfo
✅ post_max_size = 220M (برای آپلود PDF آزمون)
✅ upload_max_filesize = 220M
✅ max_execution_time = 120 ثانیه
✅ فضای دیسک: حداقل ۲GB (برای فایل‌های آپلود)
✅ HTTPS (برای Service Worker + Web Push)
```

---

## 📞 اطلاعات تماس و اعتبارات

- **طراح و توسعه‌دهنده**: تیم **وب مانیا** (webmania.ir)
- **صاحب محصول**: دکتر سجاد صیادی (مشاور کنکور)
- **سال تولید**: ۱۴۰۳ شمسی (۲۰۲۴-۲۰۲۵ میلادی)
- **نسخه**: 1.0.0
- **دامنه‌ی تولید**: https://madaar-edu.ir

---

## ✅ وضعیت کلی

**سیستم آماده‌ی Production است** ولی برای **جلسات آنلاین** نیاز به کار دارد.
تمام زیرساخت لازم (جلسه‌ی مشاوره، اعلان، پیام‌رسانی، برنامه‌ریزی، آزمون) وجود دارد.
فقط لایه‌ی **WebRTC/Video Conference + Whiteboard** باید اضافه شود.

✅ اگر سؤالی درباره‌ی هر بخش داری، بپرس.
🎯 اگر آماده‌ای بریم سراغ ساخت Meeting Room، بگو **«بریم»**.
