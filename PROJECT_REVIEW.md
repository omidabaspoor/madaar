# گزارش بررسی کامل پروژه مَدار (Madar Study OS)

> تاریخ بررسی: ۱۴۰۳/۰۴/۰۸ (۲۰۲۶-۰۶-۲۸)  
> محل: Workspace روت `/home/user/madaar`  
> وضعیت: ✅ کلون کامل + حذف `.git` + فقط فولدر اصلی

---

## ۱. آنچه انجام شد

- ریپازیتوری `https://github.com/omidabaspoor/madaar` کلون شد.
- فولدر `.git` حذف شد (فقط فولدر اصلی `madaar/` باقی ماند).
- تمام فایل‌ها، مستندات، SQL، اسکریپت‌ها و assets بررسی شدند.
- همه تغییرات و تحلیل‌ها روی خود پوشه‌ی اصلی `madaar/` انجام شده‌اند (بدون ساخت پوشه `home`).

---

## ۲. اطلاعات کلی پروژه

| مورد | مقدار |
|---|---|
| نام | مَدار · Madar Study OS |
| مالک محصول | دکتر سجاد صیادی |
| دامنه‌ی تنظیم‌شده | https://madaar-edu.ir |
| تکنولوژی | PHP 8 + MySQL/MariaDB + PWA |
| زبان | فارسی (RTL) |
| فونت | Vazirmatn |
| تعداد کل فایل‌ها | ~۱۷۲ فایل (PHP/JS/CSS/SQL/MD) |
| خطوط PHP | ~۲۲,۸۸۶ خط |
| خطوط JS | ~۵,۰۸۸ خط |
| خطوط CSS | ~۵,۴۹۷ خط |

---

## ۳. ساختار فولدرها

```
madaar/
├── admin/          # پنل مشاور/مدیر (۲۴+ فایل)
├── student/        # پنل دانش‌آموز (۲۴+ فایل)
├── auth/           # ورود، ثبت‌نام، logout (۴ فایل)
├── api/            # endpointهای AJAX (۱۴ فایل)
├── includes/       # هسته‌ی سیستم (models, auth, helpers, layout, ...)
├── config/         # config.php
├── sql/            # schema + ۲۲ migration
├── scripts/        # generate_vapid_keys.php
├── assets/         # css, js, fonts, icons, images, pdf.js vendor
├── index.php       # landing page
├── install.php     # نصب‌کننده‌ی خودکار
├── online_room.php # اتاق جلسه آنلاین (P2P/Jitsi)
├── sw.js           # Service Worker
└── *.md            # مستندات فنی
```

---

## ۴. ویژگی‌های پیاده‌سازی‌شده

### ✅ کاملاً پیاده‌سازی و کاربردی

- **احراز هویت**: ورود/ثبت‌نام، bcrypt، CSRF، Session امن، Remember Me.
- **RBAC**: سه نقش admin / advisor / student با کنترل صفحه‌ی مشاور.
- **برنامه‌ریزی هفتگی**: ۷ روز × ۷ واحد، drag-drop، ۱۱ نوع تسک، کپی از هفته قبل.
- **تسک‌های سه‌حالته**: full / partial / missed + قرمز خودکار برای عقب‌افتاده‌ها.
- **گزارش و پیشرفت**: نمودار، استریک، mood، پیشرفت درس‌به‌درس.
- **دستاوردها (Gamification)**: ۸ دستاورد پیش‌فرض + قابل تعریف.
- **سیستم آزمون**: quick_sheet (آپلود PDF/عکس + کلید) و standard (ساخت دستی)، تایمر سمت سرور، کارنامه PDF.
- **مرور هوشمند**: Spaced Repetition با فاصله‌های ۱/۳/۷/۱۶/۳۰ روز.
- **پیام‌رسانی**: چت با ضمیمه (عکس/ویس/PDF/فایل).
- **PWA**: Service Worker، manifest، آفلاین.
- **Web Push**: VAPID keys، جدول subscriptions، ارسال اعلان.
- **گزارش‌های PDF**: برنامه، کارنامه، آزمون آزمایشی، گزارش جامع.
- **جلسات مشاوره**: سیستم پیش‌نویس، تأیید نهایی، ارسال SMS، نوع مشاوره/کلاس.
- **Mobile-First**: اصلاحات گسترده برای موبایل (mock_exam، app.css، ...).

### ⚠️ پیاده‌سازی شده اما با ناقصی یا تناقض

- **جلسات آنلاین (Online Sessions)**:
  - بک‌اند کامل است (`includes/online_sessions.php`، `online_room.php`، `api/online_room.php`، `api/online_p2p.php`).
  - جداول دیتابیس وجود دارند (`sql/upgrade_online_sessions.sql`).
  - اما صفحات `admin/online_sessions.php` و `student/online_sessions.php` فقط **«به‌زودی»** هستند.
  - یعنی رابط کاربری **ساخت/مدیریت** جلسات آنلاین وجود ندارد؛ فقط می‌توان با دسترسی مستقیم به `online_room.php` وارد اتاق شد.
  - داشبورد دانش‌آموز بنر «LIVE» را نشان می‌دهد، اما نحوه‌ی ایجاد جلسه توسط مشاور نامشخص است.
  - تناقض با `ONLINE_SESSIONS_DOCS.md` که ادعا می‌کند ۱۰۰٪ آماده است.

- **Whiteboard در اتاق آنلاین**:
  - قلم، پاک‌کن، شکل، رنگ، دانلود PNG/PDF، آپلود PDF روی تخته وجود دارد.
  - اما همگام‌سازی فقط از طریق **snapshot تصویر** است، نه stroke object؛ یعنی همگام‌سازی سنگین و کند است.

- **سیستم SMS**:
  - پیامک جلسه‌ی مشاوره کار می‌کند.
  - اما متن پیامک برای نوع «کلاس درسی» هم هنوز می‌نویسد «جلسه‌ی مشاوره» (غلط).
  - تنظیمات SMS در `config.php` به‌صورت sandbox/خالی هستند.

---

## ۵. مشکلات و باگ‌های مهم شناسایی‌شده

### 🔴 امنیتی

| # | مشکل | فایل |
|---|---|---|
| ۱ | کلیدهای VAPID private/public در متن باز در `config.php` | `config/config.php` |
| ۲ | گذرواژه‌ی مشاور اصلی (`82437683Ss@`) به‌صورت hardcode در `install.php` | `install.php` |
| ۳ | `api/online_room.php` CSRF را با «soft mode» دور می‌زند (`$_SESSION['soft_online_access']`) | `api/online_room.php` |
| ۴ | `online_room.php` از `user-scalable=no` استفاده می‌کند (accessibility) | `online_room.php` |
| ۵ | در `online_room.php` و `api/online_p2p.php` دانش‌آموز می‌تواند با ساخت چند peer id وارد شود؟ بررسی نشده. | `api/online_p2p.php` |

### 🟡 باگ‌های عملکردی

| # | مشکل | فایل/تابع |
|---|---|---|
| ۱ | تابع `meetings_confirm` دو بار `return $results;` دارد (کد مرده) | `includes/meetings.php` |
| ۲ | `meetings_drafts_for_advisor` یک بلوک بی‌استفاده/مشکوک دارد که با `? [] : []` ردیف‌ها را خالی می‌کند ولی سپس بازنویسی می‌شود. | `includes/meetings.php` |
| ۳ | `sms_build_meeting_message` همیشه «جلسه‌ی مشاوره» می‌نویسد حتی برای `session_type='class'` | `includes/sms.php` |
| ۴ | `admin/schedule_meeting.php` در پیش‌نمایش SMS هم متن را مشاوره نشان می‌دهد | `admin/schedule_meeting.php` |
| ۵ | `admin/dashboard.php` کد مرده `rand(0,0)` دارد | `admin/dashboard.php` |
| ۶ | `online_room.php` ثابت `JITSI_SERVER_URL` تعریف نشده؛ fallback به `meet.jit.si` | `online_room.php` |
| ۷ | `online_room.js` برای بارگذاری Jitsi API به `meet.jit.si/external_api.js` متکی است؛ در برخی محیط‌ها fail می‌شود و به P2P fallback می‌کند. | `assets/js/online_room.js` |
| ۸ | P2P mesh فقط تا ۶ نفر پایدار است؛ ولی `max_participants` پیش‌فرض ۲۰ است. | `includes/online_sessions.php` |
| ۹ | `whiteboard_save` تا ۱۵MB snapshot می‌پذیرد در حالی که `MAX_UPLOAD` ۲MB است. | `api/online_room.php` |
| ۱۰ | `online_room.php` از `layout.php` و `panel_layout.php` استفاده نمی‌کند؛ رندر مستقل و طولانی دارد. | `online_room.php` |
| ۱۱ | `student/meetings.php` هیچ لینکی به «ورود به اتاق جلسه» ندارد (فقط notes را نشان می‌دهد). | `student/meetings.php` |
| ۱۲ | `admin/schedule_meeting.php` جلسات آنلاین را از جلسات مشاوره جدا می‌کند؛ لینک مستقیم به online_room ندارد. | `admin/schedule_meeting.php` |
| ۱۳ | در `online_room.js` تابع `downloadBoardPdf` تصویر canvas را به PDF تبدیل می‌کند اما در صورت نبود `canvas.toDataURL` کرش می‌کند. | `assets/js/online_room.js` |
| ۱۴ | `webrtc_p2p.js` برای دانش‌آموزان `getUserMedia` را با `audio:false` و `video:false` شروع می‌کند؛ اما وقتی دانش‌آموز میکروفون می‌خواهد، دوباره `getUserMedia` می‌گیرد. در iOS Safari ممکن است مشکل داشته باشد. | `assets/js/webrtc_p2p.js` |
| ۱۵ | `api/online_room.php` چت پس از `ended`/`cancelled` را بلاک می‌کند اما `whiteboard_save` هم باید همینطور باشد. | `api/online_room.php` |
| ۱۶ | `online_room.php` اگر `icon()` نباشد fallback ساده می‌سازد؛ اما `includes/icons.php` را require کرده است. | `online_room.php` |

### 🟠 ناسازگاری مستندات

| # | تناقض | توضیح |
|---|---|---|
| ۱ | `ONLINE_SESSIONS_DOCS.md` می‌گوید پنل مدیریت جلسات آنلاین کامل است. | در حالی که `admin/online_sessions.php` و `student/online_sessions.php` فقط Coming Soon هستند. |
| ۲ | `MEETING_INTEGRATION_PLAN.md` می‌گوید نیاز به ساخت صفحه‌ی Meeting Room دارد. | در حالی که `online_room.php` و `api/online_room.php` و `webrtc_p2p.js` ساخته شده‌اند. |
| ۳ | `SYSTEM_MAP.md` می‌گوید «سیستم جلسه‌ی آنلاین ۸۰٪ آماده» است. | با توجه به پیاده‌سازی گسترده، به نظر می‌رسد بیش از ۸۰٪ است، فقط UI مدیریت ناقص است. |

---

## ۶. پیش‌نیازهای نصب و اجرا

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- اکستنشن‌ها: PDO, mbstring, gd, json, fileinfo, curl
- تنظیمات PHP: `post_max_size`, `upload_max_filesize` حداقل ۲۲۰M
- HTTPS برای PWA و Web Push
- اجرای `install.php` برای ساخت دیتابیس و حساب مشاور اصلی

---

## ۷. گام‌های پیشنهادی برای ادامه (قبل از درخواست کاربر)

بر اساس بررسی، مهم‌ترین کارهایی که می‌توان انجام داد:

### الف) اتمام جلسات آنلاین (اولویت بالا)
1. ساخت رابط مدیریت جلسات در `admin/online_sessions.php` (لیست، ایجاد، ویرایش، حذف، شروع، پایان).
2. ساخت رابط دانش‌آموز در `student/online_sessions.php` (لیست جلسات، ورود).
3. اضافه کردن لینک «ورود به اتاق» در `student/meetings.php` برای جلسات آنلاین.
4. اضافه کردن دکمه‌ی «جلسه آنلاین» در `admin/schedule_meeting.php` یا `admin/dashboard.php`.

### ب) رفع باگ‌های امنیتی و عملکردی
1. خارج کردن VAPID keys و SMS keys از repo و گذاشتن در env/config خارجی.
2. تغییر گذرواژه‌ی hardcode در `install.php` یا حذف آن در production.
3. اصلاح CSRF در `api/online_room.php` (حذف soft mode).
4. اصلاح متن پیامک برای کلاس درسی در `sms.php` و `schedule_meeting.php`.
5. رفع کدهای مرده و تکراری در `includes/meetings.php` و `admin/dashboard.php`.

### ج) بهبود اتاق آنلاین
1. محدود کردن `max_participants` P2P به ۶ یا تغییر معماری به SFU/Jitsi.
2. بهبود whiteboard sync (ارسال stroke object به جای snapshot تصویر کامل).
3. اضافه کردن تست موبایل برای P2P.
4. اضافه کردن ضبط جلسه (اختیاری).

### د) تمیزکاری و مستندات
1. حذف فایل‌های `.bak` و `.fix` (مثل `exam_builder.js.bak`، `exam_builder.php.fix`).
2. یکسان‌سازی مستندات با وضعیت واقعی کد.
3. اضافه کردن README اصلی در ریشه.

---

## ۸. خلاصه‌ی نهایی

سیستم مَدار **بسیار کامل و حرفه‌ای** است. بخش‌های اصلی (برنامه‌ریزی، آزمون، پیام‌رسانی، گزارش، PWA) آماده‌ی production هستند.  
بزرگ‌ترین ناقصی فعلی **اتمام رابط کاربری جلسات آنلاین** است: بک‌اند و اتاق (room) ساخته شده، اما صفحات مدیریت و لیست جلسات هنوز Coming Soon هستند.

---

**آماده‌ی دستور بعدی شما هستم.**  
بفرمایید دقیقاً می‌خواهید روی کدام بخش کار کنیم.
