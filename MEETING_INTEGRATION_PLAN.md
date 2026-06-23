# 🎥 برنامه‌ی یکپارچه‌سازی Meeting Room با مَدار

> بررسی نقاط ادغام با سیستم فعلی مَدار قبل از شروع پیاده‌سازی

---

## 📋 خلاصه‌ی نیازها

| مورد | مشخصات |
|---|---|
| **استفاده‌ی روزانه** | بله |
| **نیاز به ضبط** | ❌ خیر |
| **مشارکت دانش‌آموزان در تخته** | بله (گاهی) |
| **۲۰ نفر همزمان** | ✅ بله |
| **هزینه** | 💯 صفر |
| **از ایران قابل دسترس** | ✅ بله |
| **شامل صفحه‌نمایش** | ✅ ۸۰٪ مواقع فقط مشاور |
| **تخته سفید سفارشی روی سایت** | ✅ الزامی |

---

## 🏗 معماری پیشنهادی

```
┌─────────────────────────────────────────────────────────┐
│  لایه‌ی ۱: مَدار روی هاست اشتراکی فعلی                  │
│  ┌──────────────────────────────────────────────────┐  │
│  │  • داشبورد مَدار                                 │  │
│  │  • Whiteboard سفارشی (Collaborative Canvas)      │  │
│  │  • چت متنی فارسی                                 │  │
│  │  • لینک دعوت اختصاصی                            │  │
│  │  • شمارنده‌ی شرکت‌کنندگان                        │  │
│  │  • مدیریت کاربران جلسه                          │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────┬───────────────────────────────────────┘
                  │ Embed (iframe)
                  ▼
┌─────────────────────────────────────────────────────────┐
│  لایه‌ی ۲: meet.jit.si (رایگان، بدون زیرساخت)        │
│  ┌──────────────────────────────────────────────────┐  │
│  │  • ویدئو + صدا                                   │  │
│  │  • اشتراک صفحه (Screen Share)                   │  │
│  │  • بدون نیاز به ثبت‌نام                          │  │
│  │  • از ایران قابل دسترس (بدون فیلتر)             │  │
│  │  • ۲۰ نفر webinar: قابل قبول                     │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## 🔌 نقاط ادغام با سیستم فعلی

### ۱. جدول `meeting_rooms` (جدید)

```sql
CREATE TABLE meeting_rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  advisor_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT,
  
  -- Jitsi integration
  jitsi_room_name VARCHAR(80) NOT NULL UNIQUE,  -- مثلاً: madar-x7f9a2b
  jitsi_password VARCHAR(40),                    -- رمز اتاق (اختیاری)
  
  -- زمان‌بندی
  scheduled_at DATETIME,
  started_at DATETIME,
  ended_at DATETIME,
  duration_min INT UNSIGNED DEFAULT 60,
  
  -- وضعیت
  status ENUM('draft','scheduled','live','ended','cancelled') DEFAULT 'draft',
  
  -- دسترسی
  is_public TINYINT(1) DEFAULT 0,                -- فقط برای دعوت‌شدگان؟
  allowed_students JSON,                         -- آرایه‌ی student_id
  
  -- Whiteboard data
  board_snapshot JSON,                           -- JSON تخته (ذخیره در پایان)
  board_auto_save TINYINT(1) DEFAULT 1,
  
  -- آمار
  max_participants INT UNSIGNED DEFAULT 20,
  actual_participants INT UNSIGNED DEFAULT 0,
  
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (advisor_id) REFERENCES users(id),
  KEY idx_meeting_advisor_status (advisor_id, status),
  KEY idx_meeting_scheduled (scheduled_at)
);
```

### ۲. جدول `meeting_attendees` (جدید)

```sql
CREATE TABLE meeting_attendees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,    -- دانش‌آموز یا خود مشاور
  joined_at DATETIME,
  left_at DATETIME,
  duration_seconds INT UNSIGNED,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  
  UNIQUE KEY uq_attendee (meeting_id, user_id),
  FOREIGN KEY (meeting_id) REFERENCES meeting_rooms(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### ۳. جدول `meeting_whiteboard_snapshots` (جدید)

```sql
CREATE TABLE whiteboard_snapshots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  
  -- Canvas state (فشرده‌شده)
  snapshot_json LONGTEXT NOT NULL,    -- JSON از Fabric.js
  snapshot_data_url LONGTEXT,         -- تصویر PNG (برای دانلود)
  
  saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  
  KEY idx_wb_meeting (meeting_id, saved_at),
  FOREIGN KEY (meeting_id) REFERENCES meeting_rooms(id) ON DELETE CASCADE
);
```

---

## 📂 فایل‌های جدیدی که باید ساخته شوند

### Backend (PHP)
```
✅ /admin/meeting_room.php          ← ساخت + مدیریت جلسه (مشاور)
✅ /student/meeting_room.php        ← ورود دانش‌آموز به جلسه
✅ /api/meeting_create.php          ← ایجاد meeting + تولید jitsi_room_name
✅ /api/meeting_start.php           ← شروع رسمی (status=live)
✅ /api/meeting_end.php             ← پایان + ذخیره whiteboard
✅ /api/meeting_join.php            ← ثبت حضور دانش‌آموز
✅ /api/whiteboard_save.php         ← ذخیره snapshot تخته (هر ۳۰ ثانیه)
✅ /api/whiteboard_load.php         ← دریافت آخرین snapshot
✅ /api/whiteboard_export.php       ← خروجی PNG/PDF
```

### Frontend (JS/CSS)
```
✅ /assets/js/meeting_room.js       ← هماهنگی کلی صفحه + Jitsi iframe API
✅ /assets/js/whiteboard.js         ← تخته سفید Collaborative (Fabric.js + Yjs)
✅ /assets/css/meeting.css          ← استایل صفحه جلسه
✅ /assets/css/whiteboard.css       ← استایل تخته
```

### تغییرات در فایل‌های موجود
```
✏️ /includes/meetings.php           ← اضافه کردن توابع meeting_rooms
✏️ /includes/panel_layout.php       ← اضافه کردن "جلسات آنلاین" به سایدبار
✏️ /admin/dashboard.php             ← اضافه کردن quick action "جلسه فوری"
✏️ /student/dashboard.php           ← اضافه کردن "جلسات فعال"
✏️ /admin/schedule_meeting.php      ← یکپارچه‌سازی با meeting_rooms
✏️ /student/meetings.php            ← لینک به meeting_room
```

### Migration
```
✅ /sql/upgrade_meeting_rooms.sql   ← Schema جدید
```

---

## 🎨 طراحی UI صفحه‌ی Meeting Room

### Desktop Layout
```
┌─────────────────────────────────────────────────────────┐
│ Header: مَدار · جلسه ریاضی ۱۴۰۳/۰۳/۲۵     🔴 LIVE  │
├─────────────────────────────────┬───────────────────────┤
│                                 │                       │
│   📹 Video (iframe Jitsi)      │   🎨 Whiteboard       │
│   [60% width × Full height]     │   [40% width]         │
│                                 │   [Tools at top]      │
│   📺 Screen Share              │   [Canvas]            │
│                                 │   [Color picker]      │
│                                 │   [Pen/Eraser]        │
│                                 │   [Shapes]            │
│                                 │   [Text]              │
│                                 │   [Image upload]      │
│                                 │                       │
├─────────────────────────────────┴───────────────────────┤
│ 👥 ۲۰ شرکت‌کننده  •  💬 چت  •  ⏱ ۰۱:۲۳:۴۵          │
└─────────────────────────────────────────────────────────┘
```

### Mobile Layout (Tab-based)
```
┌─────────────────────────┐
│ Header + Status          │
├─────────────────────────┤
│ Tab Bar:                  │
│ [📹 Video][🎨 Board][💬 Chat]│
├─────────────────────────┤
│ Content (switchable)     │
│                         │
│                         │
│                         │
├─────────────────────────┤
│ 👥 ۲۰ · ⏱ ۰۱:۲۳:۴۵    │
└─────────────────────────┘
```

---

## 🎨 ویژگی‌های Whiteboard

### ابزارها
```
✏️ قلم (۸ رنگ + ۵ سایز)
🧽 پاک‌کن (۲ حالت: دقیق + کلی)
📝 متن (با فونت فارسی Vazirmatn)
⬜ شکل: خط، مستطیل، دایره، مثلث
➡️ فلش
📏 ابزار ریاضی: خط‌کش، گونیا، نقاله (اختیاری)
📸 آپلود عکس روی تخته (با drag-drop)
↩️ Undo / Redo (۲۰ مرحله)
🗑️ پاک‌کردن همه (فقط برای ادمین)
💾 ذخیره خودکار (هر ۳۰ ثانیه)
📥 خروجی PNG / PDF
```

### همگام‌سازی (Collaborative)
```
⚡ روش: AJAX Polling هر ۲ ثانیه (به جای WebSocket)
💾 هر تغییر → batch → POST /api/whiteboard_save.php
📥 هر دانش‌آموز → GET /api/whiteboard_load.php → render diff
🎯 نرخ همگام‌سازی: ۱.۵ تا ۲ ثانیه تأخیر (قابل قبول)
🔒 Optimistic locking با version_id
⚠️ محدودیت: ۲۰ کاربر همزمان = حدود ۲۰ KB state در ثانیه
```

### کتابخانه‌ی پیشنهادی
```
Fabric.js (https://fabricjs.com/)
├── ✅ Canvas-based rendering
├── ✅ Object model (Line, Rect, Circle, Text, Image)
├── ✅ Serialization به JSON
├── ✅ Event-driven (mouse, touch, keyboard)
├── ✅ Free & Open Source
├── ✅ حجم: ~۳۰۰KB minified
└── ✅ بدون نیاز به WebSocket
```

---

## 📐 فلوی کاربر

### مشاور:
```
1. لاگین → پنل مَدار
2. کلیک روی "جلسات" → "جلسه‌ی جدید"
3. وارد کردن:
   - عنوان (مثلاً "جلسه‌ی ریاضی ۱۴۰۳/۰۳/۲۵")
   - توضیحات
   - زمان شروع (پیش‌فرض: همین الان)
   - مدت (پیش‌فرض: ۶۰ دقیقه)
   - لیست دانش‌آموزان دعوت‌شده
4. کلیک "ایجاد جلسه"
   → سیستم: تولید jitsi_room_name (مثل: madar-a7f9b2)
   → لینک دعوت: madaar-edu.ir/student/meeting_room.php?id=...
   → اعلان به همه‌ی دانش‌آموزان دعوت‌شده
5. در زمان جلسه → کلیک "ورود به اتاق"
   → باز شدن صفحه‌ی meeting_room
   → iframe Jitsi + Whiteboard فعال
6. در طول جلسه:
   → اشتراک صفحه از Jitsi
   → نوشتن روی تخته
   → مدیریت دانش‌آموزان
7. پایان جلسه → "پایان جلسه"
   → ذخیره whiteboard snapshot
   → ثبت حضور
   → اعلان "جلسه پایان یافت" به دانش‌آموزان
```

### دانش‌آموز:
```
1. اعلان روی داشبورد: "جلسه‌ی جدید: ریاضی فردا ساعت ۱۸:۰۰"
2. کلیک روی اعلان → صفحه‌ی meetings.php
3. دیدن لیست جلسات + کلیک "ورود" در زمان جلسه
4. صفحه‌ی meeting_room باز می‌شود:
   → Jitsi iframe فعال
   → Whiteboard آماده
   → چت متنی
5. در طول جلسه:
   → دیدن ویدئو + اشتراک صفحه‌ی مشاور
   → نوشتن روی تخته (با اجازه)
   → چت متنی
6. خروج از جلسه → ثبت خودکار
```

---

## ⚙️ تنظیمات Jitsi

### لینک embed (ساده):
```html
<iframe 
  src="https://meet.jit.si/madar-x7f9a2b#config.startWithAudioMuted=true&config.prejoinPageEnabled=false"
  allow="camera; microphone; display-capture; autoplay"
  style="width:100%; height:100%; border:none;">
</iframe>
```

### تنظیمات پیشنهادی (URL hash params):
```
#config.callDisplayName='دکتر سجاد صیادی'
#config.startWithAudioMuted=true
#config.startWithVideoMuted=true
#config.prejoinPageEnabled=false
#config.disableDeepLinking=true
#userInfo.displayName='{{student.full_name}}'
#config.requireDisplayName=true
```

### محدودیت‌های رایگان:
```
⚠️ meet.jit.si = best-effort، بدون SLA
⚠️ اوج ترافیک جهانی: ممکنه کند بشه
⚠️ بیشتر از ۱۰۰ نفر: ممکنه reject بشه
⚠️ recording: ❌ (رایگان)
⚠️ لوگوی Jitsi: ✅ نمایش داده میشه
```

---

## 🛡 امنیت

### دسترسی‌ها
```
✅ فقط دانش‌آموزان دعوت‌شده در allowed_students
✅ لینک مخفی با توکن random (طول ۲۰ کاراکتر)
✅ IP + User-Agent ثبت می‌شود
✅ تأیید session مَدار قبل از دسترسی به meeting room
✅ CSRF token در تمام APIها
```

### Whiteboard Sync
```
✅ Rate limit: هر کاربر حداکثر ۱ save در ۲ ثانیه
✅ JSON size limit: حداکثر ۵۰KB هر snapshot
✅ Snapshot فقط توسط مالک جلسه قابل پاک شدن
✅ تاریخچه‌ی whiteboard (۵ snapshot آخر)
```

---

## 📊 تخمین حجم و پیچیدگی

| بخش | تخمین کد | زمان |
|---|---|---|
| **Backend PHP** | ~۱٬۵۰۰ خط | ۲ روز |
| **Frontend JS** | ~۱٬۲۰۰ خط | ۲ روز |
| **CSS** | ~۴۰۰ خط | ۰.۵ روز |
| **Migration SQL** | ~۱۰۰ خط | ۰.۵ روز |
| **تست و رفع باگ** | - | ۱ روز |
| **مستندات** | - | ۰.۵ روز |
| **جمع** | ~۳٬۲۰۰ خط | **۶.۵ روز کاری** |

---

## ✅ چک‌لیست قبل از شروع

- [ ] تأیید نهایی کارفرما روی معماری
- [ ] تأیید انتخاب Fabric.js برای Whiteboard
- [ ] تأیید AJAX Polling (به جای WebSocket)
- [ ] تعیین دقیق ابزارهای Whiteboard
- [ ] تعیین سطح دسترسی: همه بنویسن یا فقط مشاور؟
- [ ] تأیید اینکه لوگوی Jitsi پایین صفحه OK است
- [ ] تأیید رنگ‌بندی Whiteboard با تم مَدار
- [ ] تست با حداقل ۳ مرورگر (Chrome, Firefox, Safari)
- [ ] تست روی موبایل (iOS + Android)

---

## 🎯 خلاصه‌ی اجرایی

```
✅ کاملاً رایگان
✅ بدون نیاز به زیرساخت اضافی
✅ از ایران قابل دسترس
✅ یکپارچه با مَدار
✅ Whiteboard اختصاصی
✅ ۲۰ نفر همزمان (webinar mode)
✅ با Polling ساده به جای WebSocket

⏳ زمان: ۶-۷ روز کاری
📊 حجم کد: ~۳٬۲۰۰ خط
🎯 پیچیدگی: متوسط
```

**آماده شروع؟** فقط بگو **«بریم»** 🚀
