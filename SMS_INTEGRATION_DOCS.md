# 📱 سیستم پیامک مَدار — راهنمای کامل

> **تاریخ پیاده‌سازی**: ۲۰۲۶-۰۶-۲۳
> **پنل SMS**: sms.ir
> **Endpoint**: `/v1/send/array`
> **وضعیت**: ✅ آماده برای تست Sandbox

---

## 🎯 قابلیت‌های پیاده‌سازی‌شده

### ۱. ارسال پیامک هنگام تنظیم جلسه
```
✅ وقتی مشاور جلسه تنظیم می‌کنه → یک پیامک برای دانش‌آموز می‌ره
✅ فقط یک‌بار ارسال می‌شه (نه duplicate)
✅ اگه دانش‌آموز شماره نداشته باشه → فقط اعلان درون‌برنامه‌ای
✅ اگه SMS خطا بده → جلسه ثبت می‌شه ولی پیامک ارسال نمی‌شه (warning)
```

### ۲. نوع جلسه: مشاوره یا کلاس
```
✅ مشاور انتخاب می‌کنه: جلسه‌ی مشاوره (یک‌به‌یک) یا کلاس درسی (گروهی)
✅ در کلاس گروهی: چند دانش‌آموز انتخاب می‌کنه → همه SMS دریافت می‌کنن
✅ متن پیامک بر اساس نوع تغییر می‌کنه
✅ Badge رنگی در لیست جلسات
✅ Badge رنگی در داشبورد دانش‌آموز
```

### ۳. لاگ کامل SMS
```
✅ جدول sms_log با فیلدهای:
   ├── user_id (دانش‌آموز دریافت‌کننده)
   ├── phone (شماره نرمال‌شده)
   ├── message (متن کامل پیامک)
   ├── template_type (meeting_consultation / meeting_class)
   ├── related_id (شناسه جلسه)
   ├── status (sent / failed)
   ├── api_response (پاسخ کامل sms.ir)
   ├── api_message_id (شناسه پیامک از sms.ir)
   ├── error_message (پیام خطا)
   └── sent_at (زمان ارسال)
```

---

## 📱 متن پیامک

### جلسه‌ی مشاوره:
```
با سلام و احترام،

جلسه‌ی مشاوره شما در سامانه مَدار تنظیم شد.

📅 تاریخ: ۲۵ خرداد ۱۴۰۳
🕐 ساعت: ۱۸:۰۰

لطفاً پنل کاربری خود را در مَدار بررسی فرمایید.

madaar-edu.ir
```

### کلاس درسی:
```
با سلام و احترام،

کلاس درسی شما در سامانه مَدار تنظیم شد.

📅 تاریخ: ۲۵ خرداد ۱۴۰۳
🕐 ساعت: ۱۸:۰۰

لطفاً پنل کاربری خود را در مَدار بررسی فرمایید.

madaar-edu.ir
```

---

## 🔧 تنظیمات (config/config.php)

```php
define('SMS_ENABLED', true);
define('SMS_API_KEY', 'PN1TVeBeaAehFLJAKU4XdfpsFXsQguYfleO0bV4ceh6diTZid2hRXza3uSkBbDef'); // ⬅️ Sandbox
define('SMS_API_URL', 'https://api.sms.ir/v1/send/array');
define('SMS_LINE_NUMBER', '30004505000027'); // ⬅️ با خط واقعی جایگزین کن
define('SMS_TIMEOUT', 10);
```

### ⚠️ برای Production:
```
1. برو پنل sms.ir → برنامه‌نویسان → لیست کلیدهای API
2. یک کلید Production (نه Sandbox) بساز
3. در config.php فقط مقدار SMS_API_KEY رو عوض کن
4. SMS_LINE_NUMBER رو با خط واقعی خودت عوض کن
```

---

## 📂 فایل‌های تغییر یافته

### ✅ جدید:
- `/includes/sms.php` - سرویس wrapper
- `/sql/upgrade_meeting_sms.sql` - Migration

### ✏️ تغییر یافته:
- `/config/config.php` - تنظیمات SMS
- `/includes/meetings.php` - نوع جلسه + SMS hook
- `/admin/schedule_meeting.php` - UI جدید با انتخاب نوع
- `/student/meetings.php` - نمایش نوع جلسه
- `/student/dashboard.php` - هشدار هوشمند
- `/install.php` - اضافه شدن migration جدید

---

## 🚀 مراحل اجرا

### ۱. اجرای Migration
```
برو به آدرس: https://madaar-edu.ir/install.php?update=1
یا اگه نصب نشده: https://madaar-edu.ir/install.php

این کار رو می‌کنه:
- ALTER TABLE consultation_sessions ADD session_type
- CREATE TABLE sms_log
```

### ۲. تست Sandbox
```
1. لاگین به پنل مشاور
2. برو به "جلسات"
3. تنظیم جلسه‌ی جدید
4. نوع: "جلسه مشاوره" یا "کلاس درسی"
5. دانش‌آموز انتخاب کن
6. تاریخ و ساعت
7. Submit
8. ✅ باید پیامک Sandbox ارسال بشه (در پنل sms.ir لاگ می‌بینی)
```

### ۳. بررسی لاگ
```sql
SELECT * FROM sms_log ORDER BY created_at DESC LIMIT 20;

-- بررسی ارسال‌های موفق:
SELECT * FROM sms_log WHERE status='sent' ORDER BY created_at DESC LIMIT 20;

-- بررسی خطاها:
SELECT * FROM sms_log WHERE status='failed' ORDER BY created_at DESC LIMIT 20;
```

### ۴. رفتن به Production
```
1. ساخت کلید API جدید در sms.ir (Production)
2. تنظیم SMS_LINE_NUMBER واقعی
3. تغییر SMS_API_KEY در config.php
4. خاموش کردن حالت Sandbox
5. تست با یک شماره واقعی
6. فعال‌سازی برای همه
```

---

## 🎯 فلوی کامل

```
1️⃣ مشاور لاگین می‌کنه
2️⃣ میره به /admin/schedule_meeting.php
3️⃣ انتخاب نوع جلسه:
   ├── 👤 جلسه‌ی مشاوره → یک دانش‌آموز
   └── 👥 کلاس درسی → چند دانش‌آموز (checkbox)
4️⃣ تاریخ و ساعت تعیین می‌کنه
5️⃣ Submit
6️⃣ meetings_save() اجرا می‌شه:
   ├── INSERT در consultation_sessions (با session_type)
   ├── NOTIFY به هر دانش‌آموز (in-app + Web Push)
   └── sms_send() به هر دانش‌آموز (اگر شماره داشته باشه)
7️⃣ پیامک به sms.ir API ارسال می‌شه
8️⃣ پاسخ در sms_log ثبت می‌شه
9️⃣ Flash message به مشاور نمایش داده می‌شه:
   ├── "جلسه برای X دانش‌آموز ثبت شد ✅"
   ├── "X پیامک ارسال شد 📱"
   ├── "Y پیامک خطا داد ⚠️"
   └── "Z دانش‌آموز شماره موبایل ندارند 📞"
```

---

## 🔐 نکات امنیتی

```
✅ کلید API در فایل config (نه در کد یا DB)
✅ امکان غیرفعال‌سازی SMS با SMS_ENABLED=false
✅ تمام درخواست‌ها در sms_log ثبت می‌شن
✅ پاسخ کامل API در DB ذخیره می‌شه (debug)
✅ نرمال‌سازی شماره موبایل قبل از ارسال
✅ Timeout: 10 ثانیه (جلوگیری از hang)
✅ اعتبارسنجی CSRF در تمام فرم‌ها
✅ اعتبارسنجی نقش (فقط مشاور/ادمین)
```

---

## 📊 آمار کار انجام‌شده

```
📝 خطوط کد جدید: ~۵۰۰ خط
📂 فایل جدید: ۲
✏️ فایل تغییر یافته: ۶
🗄 جدول DB جدید: ۱ (sms_log)
🗄 ستون DB جدید: ۱ (session_type)
⏱ زمان تخمینی اجرا: ۱۵ دقیقه
```

---

## 🎁 نکات کاربردی

### فعال/غیرفعال کردن SMS موقتاً:
```php
// در config.php
define('SMS_ENABLED', false);  // همه SMS ها غیرفعال می‌شن
```

### تغییر متن پیامک:
```php
// در includes/sms.php → تابع sms_build_meeting_message()
return "متن سفارشی شما...";
```

### اضافه کردن نوع جلسه جدید:
```sql
ALTER TABLE consultation_sessions 
MODIFY session_type ENUM('consultation', 'class', 'workshop') NOT NULL DEFAULT 'consultation';
```

---

**✅ همه چیز آماده‌ست! فقط Migration رو اجرا کن و تست کن.** 🚀
