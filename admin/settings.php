<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

// مدیریت درس‌ها و پیش‌فرض‌ها
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf();
    $act = (string)input('s_action');
    if ($act==='add_subject') {
        $name=trim((string)input('name')); $color=trim((string)input('color')) ?: '#6b8872';
        if ($name!=='') { 
            db()->prepare('INSERT INTO subjects (advisor_id,name,color) VALUES (?,?,?)')->execute([$u['id'],$name,$color]); 
            log_activity((int)$u['id'], 'settings_updated', 'system', null, ['عملیات' => 'افزودن درس تحصیلی', 'نام درس' => $name, 'رنگ' => $color]);
            flash('success','درس با موفقیت اضافه شد'); 
        }
    } elseif ($act==='del_subject') {
        $delId = (int)input('id');
        $subjName = db()->query("SELECT name FROM subjects WHERE id=$delId")->fetchColumn() ?: 'درس';
        db()->prepare('DELETE FROM subjects WHERE id=?')->execute([$delId]); 
        log_activity((int)$u['id'], 'settings_updated', 'system', null, ['عملیات' => 'حذف درس تحصیلی', 'نام درس' => $subjName]);
        flash('success','درس حذف شد');
    } elseif ($act==='planner_defaults') {
        if (!settings_table_ready()) {
            flash('error','جدول تنظیمات ساخته نشد. لطفاً دسترسی CREATE TABLE دیتابیس را بررسی کنید.');
        } else {
            save_planner_settings((int)$u['id'], $_POST);
            log_activity((int)$u['id'], 'settings_updated', 'system', null, ['عملیات' => 'ذخیره تنظیمات کلان برنامه‌ریز', 'مدت پیش‌فرض' => $_POST['default_duration'] ?? '60']);
            $saved = advisor_settings((int)$u['id']);
            flash('success','تنظیمات کلان مَدار با موفقیت ذخیره و به‌روزرسانی شد 🚀');
        }
    }
    redirect('admin/settings.php');
}
$subjects = all_subjects();
$pset = advisor_settings((int)$u['id']);
$durOptions = [30=>'۳۰ دقیقه',45=>'۴۵ دقیقه',50=>'۵۰ دقیقه',60=>'۶۰ دقیقه (۱ ساعت)',75=>'۷۵ دقیقه',90=>'۹۰ دقیقه',120=>'۱۲۰ دقیقه (۲ ساعت)',150=>'۱۵۰ دقیقه',180=>'۱۸۰ دقیقه (۳ ساعت)'];

panel_start('مرکز فرماندهی و تنظیمات مَدار', 'پیکربندی جامع حساب، درس‌ها، ماتریس برنامه‌ریز و ماژول‌های هوشمند', 'admin', 'settings', ['student.css','builder.css']);
?>

<!-- ===== PANEL 1: Account Profile & Subjects ===== -->
<div class="panel-grid grid gap-4 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(min(100%, 450px), 1fr))">
  
  <!-- Account & Password -->
  <div class="panel flex" style="flex-direction:column;justify-content:space-between;background:var(--surface-1);border:1px solid var(--border-soft);padding:32px;border-radius:var(--r-lg);box-shadow:0 8px 24px rgba(0,0,0,0.3)">
    <div>
      <div class="panel-head mb-4 between" style="align-items:center;border-bottom:1px solid var(--surface-2);padding-bottom:16px">
        <h3 style="font-size:1.25rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:10px"><?= icon('user',22) ?> مشخصات کاربری مشاور</h3>
        <span class="badge badge-gold">اطلاعات حساب</span>
      </div>

      <form method="post" action="<?= url('api/profile.php') ?>" class="mb-4">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="field mb-3"><label style="font-weight:800;color:var(--text-2)">نام نمایشی مشاور</label><input class="input" name="full_name" value="<?= e($u['full_name']) ?>" required style="font-size:1.05rem;font-weight:bold"></div>
        <div class="grid gap-3 mb-3" style="grid-template-columns:1fr 1fr">
          <div class="field"><label style="font-weight:800;color:var(--text-2)">تخصص / عنوان</label><input class="input" name="field" value="<?= e($u['field']) ?>" placeholder="مثلاً مشاور کنکور"></div>
          <div class="field"><label style="font-weight:800;color:var(--text-2)">شماره موبایل</label><input class="input" name="phone" dir="ltr" value="<?= e($u['phone']) ?>"></div>
        </div>
        <button class="btn btn-gold btn-lg btn-block" style="font-weight:900"><?= icon('check',18) ?> ذخیره تغییرات حساب</button>
      </form>
    </div>

    <div class="mt-4 pt-4" style="border-top:1px solid var(--surface-2)">
      <h3 style="font-size:1.1rem;font-weight:900;color:var(--danger);margin-bottom:14px;display:flex;align-items:center;gap:8px"><?= icon('lock',18) ?> تغییر گذرواژه‌ی عبور</h3>
      <form method="post" action="<?= url('api/profile.php') ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="grid gap-3 mb-3" style="grid-template-columns:repeat(auto-fit, minmax(130px, 1fr))">
          <div class="field"><label style="font-size:.85rem">گذرواژه فعلی</label><input class="input" name="current" type="password" required></div>
          <div class="field"><label style="font-size:.85rem">گذرواژه جدید</label><input class="input" name="new" type="password" required></div>
          <div class="field"><label style="font-size:.85rem">تکرار جدید</label><input class="input" name="new2" type="password" required></div>
        </div>
        <button class="btn btn-ghost btn-sm btn-block" style="color:var(--danger);border:1px solid rgba(217,116,116,0.3);font-weight:800"><?= icon('lock',16) ?> به‌روزرسانی گذرواژه</button>
      </form>
    </div>
  </div>

  <!-- Subjects management -->
  <div class="panel flex" style="flex-direction:column;justify-content:space-between;background:var(--surface-1);border:1px solid var(--border-soft);padding:32px;border-radius:var(--r-lg);box-shadow:0 8px 24px rgba(0,0,0,0.3)">
    <div>
      <div class="panel-head mb-4 between" style="align-items:center;border-bottom:1px solid var(--surface-2);padding-bottom:16px">
        <h3 style="font-size:1.25rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:10px"><?= icon('book',22) ?> مدیریت تخصصی درس‌ها</h3>
        <span class="badge badge-sage">پالت رنگی برنامه‌ریز</span>
      </div>

      <form method="post" class="flex gap-2 mb-4 wrap" style="align-items:flex-end">
        <?= csrf_field() ?><input type="hidden" name="s_action" value="add_subject">
        <div class="field" style="flex:1;min-width:180px;margin:0"><label style="font-weight:800;color:var(--text-2)">نام درس جدید</label><input class="input" name="name" placeholder="مثلاً زمین‌شناسی یا فیزیک پایه" required style="font-weight:bold"></div>
        <div class="field" style="margin:0;width:80px"><label style="font-weight:800;color:var(--text-2)">رنگ</label><input class="input" type="color" name="color" value="#6b8872" style="padding:4px;height:44px;width:100%;cursor:pointer"></div>
        <button class="btn btn-gold btn-lg" type="submit" style="font-weight:900;height:44px;padding:0 24px"><?= icon('plus',18) ?> افزودن</button>
      </form>

      <div class="flex gap-2 wrap mt-3">
        <?php foreach($subjects as $s): ?>
          <span class="badge flex gap-2" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:8px 14px;font-size:.9۵rem;font-weight:800;align-items:center;border-radius:10px">
            <span class="subj-dot" style="background:<?= e($s['color']) ?>;width:12px;height:12px;border-radius:50%;box-shadow:0 0 8px <?= e($s['color']) ?>"></span>
            <?= e($s['name']) ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="s_action" value="del_subject"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;margin-right:8px;font-weight:bold" onclick="return confirm('آیا از حذف این درس مطمئنی؟')">×</button></form>
          </span>
        <?php endforeach; ?>
        <?php if(!$subjects): ?><span class="muted" style="font-size:.9rem">هنوز درسی ثبت نشده است.</span><?php endif; ?>
      </div>
    </div>

    <div class="mt-4 pt-4 muted text-c" style="border-top:1px solid var(--surface-2);font-size:.82rem;line-height:1.6">
      <?= icon('info',16) ?> تگ‌های رنگیِ بالا در استودیوی طراحی آزمون، پنل برنامه‌ریز هفتگی و خروجی‌های PDF دانش‌آموزان اعمال می‌شوند.
    </div>
  </div>

</div>

<!-- ===== PANEL 2: Master Study OS Control Center (Enterprise Suite) ===== -->
<div class="panel mb-4" style="background:var(--surface-2);border:1px solid var(--gold);padding:36px;border-radius:var(--r-lg);box-shadow:0 12px 36px rgba(0,0,0,0.4)">
  
  <div class="panel-head mb-4 between wrap gap-3" style="align-items:center;border-bottom:1px solid var(--border-soft);padding-bottom:20px">
    <div class="flex gap-3" style="align-items:center">
      <span style="font-size:2.5rem;color:var(--gold)"><?= icon('settings',40) ?></span>
      <div>
        <h3 style="font-size:1.5rem;font-weight:900;color:var(--text-1)">مرکز فرماندهی کلان مَدار (Enterprise Control Center)</h3>
        <p class="muted mt-1" style="font-size:.9rem">کنترل یک‌پارچه‌ی بیش از ۹۰٪ قابلیت‌های سامانه، ماتریس برنامه‌ریزی، هوش مصنوعی و گیمیفیکیشن</p>
      </div>
    </div>
    <span class="badge badge-gold flex gap-1" style="padding:8px 16px;font-weight:900;font-size:1rem;align-items:center"><?= icon('fire',18) ?> مَدار نسخه ۴.۰ لوکس</span>
  </div>

  <form method="post" class="settings-master-form">
    <?= csrf_field() ?><input type="hidden" name="s_action" value="planner_defaults">
    
    <!-- بخش الف: پیش‌فرض‌های ماتریس برنامه‌ریز -->
    <h4 style="font-size:1.15rem;font-weight:900;color:var(--gold-light);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <?= icon('calendar',20) ?> الف) پیکربندی پیش‌فرض‌های برنامه‌ریز هفتگی
    </h4>
    
    <div class="grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(280px, 1fr))">
      
      <div class="field panel" style="background:var(--surface-1);padding:16px;border-radius:12px;margin:0">
        <label for="d_dur" style="font-size:.9۵rem;font-weight:800;color:var(--text-1)"><?= icon('clock',16) ?> مدت پیش‌فرض هر تسک</label>
        <select class="select mt-2" id="d_dur" name="default_duration" style="font-weight:bold;font-size:.9۵rem">
          <?php foreach ($durOptions as $m=>$lbl): ?>
            <option value="<?= $m ?>" <?= (int)$pset['default_duration']===$m?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint mt-2 muted" style="font-size:.78rem">هنگام افزودن تسک جدید، این زمان خودکار درج می‌شود.</span>
      </div>

      <div class="field panel" style="background:var(--surface-1);padding:16px;border-radius:12px;margin:0">
        <label for="d_test" style="font-size:.9۵rem;font-weight:800;color:var(--text-1)"><?= icon('check',16) ?> تعداد تست پیش‌فرض</label>
        <input class="input mt-2" id="d_test" name="default_test_count" type="number" min="0" max="600" inputmode="numeric" value="<?= e($pset['default_test_count']) ?>" style="font-weight:bold;font-size:1.05rem">
        <span class="hint mt-2 muted" style="font-size:.78rem">هنگام گزینش نوع «تست»، این تعداد تست خودکار پیشنهاد می‌گردد.</span>
      </div>

      <div class="field panel" style="background:var(--surface-1);padding:16px;border-radius:12px;margin:0">
        <label for="d_grid" style="font-size:.9۵rem;font-weight:800;color:var(--text-1)"><?= icon('grid',16) ?> چیدمان و تراکم جدول برنامه‌ریز</label>
        <select class="select mt-2" id="d_grid" name="grid_density" style="font-weight:bold;font-size:.9۵rem">
          <option value="comfortable" <?= $pset['grid_density']==='comfortable'?'selected':'' ?>>راحت و مرتب (استاندارد کنکور)</option>
          <option value="compact"     <?= $pset['grid_density']==='compact'?'selected':'' ?>>فشرده (نمایش حداکثری کارت‌ها در دید)</option>
        </select>
        <span class="hint mt-2 muted" style="font-size:.78rem">تراکم بصری کارت‌های تسک در صفحه مشاور.</span>
      </div>

    </div>

    <!-- ماتریس تنظیمات کپی و واحد ویژه (Redesigned robust interactive paste mode options) -->
    <div class="grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(280px, 1fr))">
      
      <div class="panel" style="background:var(--surface-1);padding:16px;border-radius:12px">
        <b style="font-size:.9۵rem;font-weight:800;color:var(--text-1);display:flex;align-items:center;gap:6px;margin-bottom:8px">
          <?= icon('glasses',16) ?> مقادیر پیش‌فرض واحد ویژه‌ی روزانه
        </b>
        <div class="grid gap-2 mt-3" style="grid-template-columns:1fr 1fr">
          <div><label style="font-size:.8rem;color:var(--text-2)">روزخوانی (دقیقه):</label><input class="input" name="special_reading_min" type="number" value="<?= e($pset['special_reading_min']) ?>" style="font-weight:bold;margin-bottom:0"></div>
          <div><label style="font-size:.8rem;color:var(--text-2)">آزمونک (دقیقه):</label><input class="input" name="special_exam_min" type="number" value="<?= e($pset['special_exam_min']) ?>" style="font-weight:bold;margin-bottom:0"></div>
        </div>
      </div>

      <div class="panel" style="background:var(--surface-1);padding:16px;border-radius:12px">
        <b style="font-size:.9۵rem;font-weight:800;color:var(--text-1);display:flex;align-items:center;gap:6px;margin-bottom:12px">
          <?= icon('copy',16) ?> رفتار کپی و پرکردن خودکار تسک‌ها
        </b>
        
        <div class="paste-mode-options flex gap-3 my-2 wrap">
          <label class="panel mode-opt <?= $pset['paste_mode']==='single'?'active':'' ?>" style="flex:1;cursor:pointer;border:2px solid <?= $pset['paste_mode']==='single'?'var(--gold)':'var(--border-soft)' ?>;background:<?= $pset['paste_mode']==='single'?'var(--gold-glass)':'var(--surface-2)' ?>;padding:10px 14px;border-radius:10px;text-align:center;min-width:130px;transition:all 0.2s">
            <input type="radio" name="paste_mode" value="single" <?= $pset['paste_mode']==='single'?'checked':'' ?> style="margin-left:6px">
            <b style="color:var(--text-1);font-size:.9۵rem">✓ یک‌بار پیست</b>
            <div class="muted mt-1" style="font-size:.78rem">پیست می‌شود و پایان می‌یابد</div>
          </label>
          
          <label class="panel mode-opt <?= $pset['paste_mode']==='sticky'?'active':'' ?>" style="flex:1;cursor:pointer;border:2px solid <?= $pset['paste_mode']==='sticky'?'var(--sage)':'var(--border-soft)' ?>;background:<?= $pset['paste_mode']==='sticky'?'var(--sage-glass)':'var(--surface-2)' ?>;padding:10px 14px;border-radius:10px;text-align:center;min-width:130px;transition:all 0.2s">
            <input type="radio" name="paste_mode" value="sticky" <?= $pset['paste_mode']==='sticky'?'checked':'' ?> style="margin-left:6px">
            <b style="color:var(--text-1);font-size:.9۵rem">🔁 پیست چسبان</b>
            <div class="muted mt-1" style="font-size:.78rem">چندین خانه پیست پشت‌سرهم</div>
          </label>
        </div>

        <label class="toggle-row between mt-3 pt-3" style="align-items:center;border-top:1px solid var(--surface-2)"><span><b>پرکردن خودکار هوشمند</b></span>
          <label class="switch"><input type="checkbox" name="smart_autofill" value="1" <?= $pset['smart_autofill']==='1'?'checked':'' ?>><span class="slider"></span></label>
        </label>
      </div>

    </div>

    <div class="divider my-4"></div>

    <!-- بخش ب: فرماندهی ماژول‌های هوشمند و گیمیفیکیشن -->
    <h4 style="font-size:1.15rem;font-weight:900;color:var(--sage);margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <?= icon('sparkles',20) ?> ب) فرماندهی ماژول‌های هوشمند، ماتریس آزمون‌ها و گیمیفیکیشن (Enterprise Modules)
    </h4>

    <div class="panel mb-4" style="background:linear-gradient(135deg,rgba(203,172,128,.10),rgba(107,136,114,.06));border:1px solid rgba(203,172,128,.26);padding:18px;border-radius:16px">
      <h4 style="color:var(--gold-light);font-weight:1000;margin-bottom:12px"><?= icon('sparkles',18) ?> تحلیل‌های هوشمند مَدار <span class="badge badge-gold">Beta</span></h4>
      <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
        <label class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:14px;border-radius:12px"><span><b>تحلیل گزارش پیشرفته <span class="badge badge-gold">بتا</span></b><br><small class="muted">تحلیل عملکرد روزانه/هفتگی/ماهانه</small></span><span class="switch"><input type="checkbox" name="insight_enabled" value="1" <?= ($pset['insight_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></span></label>
        <label class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:14px;border-radius:12px"><span><b>تحلیل آزمون آزمایشی/کنکور <span class="badge badge-gold">بتا</span></b><br><small class="muted">تحلیل آزمون‌های قلمچی، سنجش، ماز و...</small></span><span class="switch"><input type="checkbox" name="mock_analysis_enabled" value="1" <?= ($pset['mock_analysis_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></span></label>
      </div>
    </div>

    <div class="enterprise-modules-grid grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(280px, 1fr))">
      
      <!-- تحلیل هوشمند -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('sparkles',18) ?> موتور تحلیل هوشمند مَدار</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">ارزیابی خودکار پارت‌ها، ثبات و نقشه اقدام در گزارش‌ها</span>
        </div>
        <span class="badge badge-gold">از بخش تحلیل‌های هوشمند کنترل می‌شود</span>
      </div>

      <!-- مرور فاصله‌دار -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('repeat',18) ?> سیستم مرور فاصله‌دار هوشمند</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">ساخت خودکار یادآور بر اساس منحنی ابینگهاوس و حس دانش‌آموز</span>
        </div>
        <label class="switch"><input type="checkbox" name="review_enabled" value="1" <?= ($pset['review_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>

      <!-- سیستم دستاوردها -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('trophy',18) ?> سیستم گیمیفیکیشن و دستاوردها</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">اعطای نشان‌های افتخار و رهگیری استریک پیاپی مطالعه</span>
        </div>
        <label class="switch"><input type="checkbox" name="achievements_enabled" value="1" <?= ($pset['achievements_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>

      <!-- ترازسنج کنکور -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('chart',18) ?> ترازسنج کشوری و کنکوری مَدار</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">محاسبه و نمایش تراز تخمینی کنکور در کارنامه آزمون‌ها</span>
        </div>
        <label class="switch"><input type="checkbox" name="taraz_samurai_enabled" value="1" <?= ($pset['taraz_samurai_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>

      <!-- تحلیل ضریب دقت -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('target',18) ?> آسیب‌شناسی تعاملی و ضریب دقت</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">فعال‌سازی کادرهای ریشه‌یابی اشتباه و محاسبه ضریب دقت</span>
        </div>
        <label class="switch"><input type="checkbox" name="precision_samurai_enabled" value="1" <?= ($pset['precision_samurai_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>

      <!-- ثبت حال روزانه -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('mood',18) ?> ثبت روزانه حال و روحیه (Mood)</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">امکان ثبت ایموجی روحیه روزانه در داشبورد دانش‌آموز</span>
        </div>
        <label class="switch"><input type="checkbox" name="mood_logging_enabled" value="1" <?= ($pset['mood_logging_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>

      <!-- اعلان‌های PWA -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('bell',18) ?> یادآوری‌های پوش‌نوتیفیکیشن PWA</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">نمایش یادآور زمان مرورها روی صفحه گوشی و کامپیوتر</span>
        </div>
        <label class="switch"><input type="checkbox" name="web_notifications" value="1" <?= ($pset['web_notifications']??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>

      <!-- گارد تسک‌های منقضی‌شده -->
      <div class="panel between flex" style="align-items:center;background:var(--surface-1);border:1px solid var(--border-soft);padding:16px;border-radius:12px">
        <div>
          <b style="font-size:1rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:8px"><?= icon('lock',18) ?> محافظ تسک‌های روزهای گذشته</b>
          <span class="muted mt-1" style="font-size:.8rem;line-height:1.5">قرمزکردن خودکار تسک‌های اجرا‌نشده‌ی منقضی‌شده در سیستم</span>
        </div>
        <label class="switch"><input type="checkbox" name="auto_mark_missed_enabled" value="1" <?= ($pset['auto_mark_missed_enabled']??'1')==='1'?'checked':'' ?>><span class="slider"></span></label>
      </div>

    </div>

    <div class="mt-4 pt-4 text-l" style="border-top:1px solid var(--surface-1)">
      <button type="submit" class="btn btn-gold btn-lg flex gap-2" style="padding:16px 48px;font-weight:900;font-size:1.15rem;display:inline-flex;align-items:center">
        <?= icon('rocket',20) ?> ثبت نهایی و اعمال تنظیمات کلان مَدار
      </button>
    </div>
  </form>
</div>

<!-- ===== PANEL 3: Chapter Management ===== -->
<div class="panel mb-4" style="background:var(--surface-2);border:1px solid var(--sage);padding:36px;border-radius:var(--r-lg);box-shadow:0 12px 36px rgba(0,0,0,0.4)">
  <div class="panel-head mb-4 between wrap gap-3" style="align-items:center;border-bottom:1px solid var(--border-soft);padding-bottom:20px">
    <div class="flex gap-3" style="align-items:center">
      <span style="font-size:2.5rem;color:var(--sage)"><?= icon('book-open',40) ?></span>
      <div>
        <h3 style="font-size:1.5rem;font-weight:900;color:var(--text-1)">مدیریت فصل‌های کتاب درسی</h3>
        <p class="muted mt-1" style="font-size:.9rem">افزودن، ویرایش، حذف و بازیابی فصل‌های درسی برای برنامه‌ریز هوشمند</p>
      </div>
    </div>
    <div class="flex gap-2">
      <button type="button" class="btn btn-sage btn-sm" id="resetChaptersBtn" onclick="return confirm('تمام فصل‌های سیستمی دوباره بازیابی شوند؟') || event.preventDefault()">
        <?= icon('repeat',16) ?> بازیابی پیش‌فرض‌ها
      </button>
      <button type="button" class="btn btn-gold btn-sm" id="addChapterBtn" onclick="openChapterModal()">
        <?= icon('plus',16) ?> افزودن فصل
      </button>
    </div>
  </div>

  <!-- Filter bar -->
  <div class="flex gap-3 wrap mb-4" style="align-items:center">
    <div class="field" style="margin:0;flex:1;min-width:160px">
      <label style="font-size:.78rem;font-weight:800">رشته</label>
      <select class="select" id="chapFilterField" style="height:40px">
        <option value="">همه</option>
        <option value="tajrobi">تجربی</option>
        <option value="riazi">ریاضی</option>
        <option value="omumi">عمومی</option>
      </select>
    </div>
    <div class="field" style="margin:0;flex:1;min-width:120px">
      <label style="font-size:.78rem;font-weight:800">پایه</label>
      <select class="select" id="chapFilterGrade" style="height:40px">
        <option value="">همه</option>
        <option value="10">دهم</option>
        <option value="11">یازدهم</option>
        <option value="12">دوازدهم</option>
      </select>
    </div>
    <div class="field" style="margin:0;flex:2;min-width:200px">
      <label style="font-size:.78rem;font-weight:800">جستجو در نام کتاب/فصل</label>
      <input class="input" id="chapFilterSearch" type="text" placeholder="مثلاً ریاضی یا فصل ۲…" style="height:40px">
    </div>
    <button type="button" class="btn btn-sage btn-sm" id="loadChaptersBtn" style="height:40px;margin-top:auto">
      <?= icon('search',16) ?> بارگذاری
    </button>
  </div>

  <!-- Chapters table -->
  <div class="panel" style="background:var(--surface-1);border:1px solid var(--border-soft);border-radius:var(--r-md);overflow:hidden">
    <div class="table-wrap" style="overflow-x:auto">
      <table class="tbl" id="chaptersTable" style="min-width:680px">
        <thead>
          <tr><th>شناسه</th><th>رشته</th><th>پایه</th><th>درس</th><th>کتاب</th><th>فصل/درس</th><th style="width:100px"></th></tr>
        </thead>
        <tbody>
          <tr><td colspan="7" class="text-c muted" style="padding:24px">دکمه‌ی «بارگذاری» را بزنید یا فیلترها را تنظیم کنید.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Chapter Add/Edit Modal -->
<div class="modal-backdrop" id="chapterModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head">
      <h3 id="chapterModalTitle">افزودن فصل جدید</h3>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>
    <form id="chapterForm" class="grid gap-3" style="padding:20px">
      <input type="hidden" id="chapterId" value="">
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="field">
          <label>رشته</label>
          <select class="select" id="chapField" required>
            <option value="tajrobi">تجربی</option>
            <option value="riazi">ریاضی</option>
            <option value="omumi">عمومی</option>
          </select>
        </div>
        <div class="field">
          <label>پایه</label>
          <select class="select" id="chapGrade" required>
            <option value="10">دهم</option>
            <option value="11">یازدهم</option>
            <option value="12">دوازدهم</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label>نام درس (کلید)</label>
        <input class="input" id="chapSubjectName" type="text" placeholder="مثلاً ریاضی، شیمی، زیست‌شناسی…" required>
      </div>
      <div class="field">
        <label>نام کتاب</label>
        <input class="input" id="chapBookName" type="text" placeholder="مثلاً ریاضی (۱) یا شیمی (۲)…" required>
      </div>
      <div class="field">
        <label>نام فصل/درس</label>
        <input class="input" id="chapChapterName" type="text" placeholder="نام فصل یا درس…" required>
      </div>
      <div class="field">
        <label>ترتیب</label>
        <input class="input" id="chapSortOrder" type="number" value="0" min="0">
      </div>
      <div class="flex gap-2" style="justify-content:flex-end;margin-top:8px">
        <button type="button" class="btn btn-ghost" data-close>انصراف</button>
        <button type="submit" class="btn btn-gold"><?= icon('check',16) ?> ذخیره</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Interactive Front-end JS for Paste Mode Radio Options
  document.querySelectorAll('.mode-opt input[type="radio"]').forEach(rad => {
    rad.addEventListener('change', () => {
      document.querySelectorAll('.mode-opt').forEach(opt => {
        opt.classList.remove('active');
        opt.style.borderColor = 'var(--border-soft)';
        opt.style.background  = 'var(--surface-2)';
      });
      const parent = rad.closest('.mode-opt');
      parent.classList.add('active');
      parent.style.borderColor = rad.value === 'single' ? 'var(--gold)' : 'var(--sage)';
      parent.style.background  = rad.value === 'single' ? 'var(--gold-glass)' : 'var(--sage-glass)';
    });
  });

  // Chapter management JS
  const API_CHAPTERS = '<?= url('api/chapters.php') ?>';
  const fieldLabels = { tajrobi:'تجربی', riazi:'ریاضی', omumi:'عمومی' };
  const gradeLabels = {10:'دهم', 11:'یازدهم', 12:'دوازدهم'};
  const esc = (s)=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  async function loadChapters() {
    const tbody = document.querySelector('#chaptersTable tbody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-c" style="padding:24px"><span class="spinner"></span> در حال بارگذاری…</td></tr>';
    try {
      const body = { action: 'list' };
      const field = document.getElementById('chapFilterField').value;
      const grade = document.getElementById('chapFilterGrade').value;
      const search = document.getElementById('chapFilterSearch').value.trim();
      if (field) body.field = field;
      if (grade) body.grade = grade;
      if (search) body.search = search;
      const d = await api(API_CHAPTERS, { method: 'POST', body });
      if (!d.items || !d.items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-c muted" style="padding:24px">فصلی یافت نشد. از فیلترهای دیگر استفاده کنید یا فصل جدید اضافه کنید.</td></tr>';
        return;
      }
      let html = '';
      const chapterMap = new Map();
      for (const r of d.items) {
        chapterMap.set(String(r.id), r);
        html += '<tr data-chid="' + esc(String(r.id)) + '">';
        html += '<td>' + esc(String(r.id)) + '</td>';
        html += '<td><span class="badge">' + esc(fieldLabels[r.field] || r.field) + '</span></td>';
        html += '<td>' + esc(String(gradeLabels[r.grade] || r.grade)) + '</td>';
        html += '<td>' + esc(r.subject_name) + '</td>';
        html += '<td>' + esc(r.book_name) + '</td>';
        html += '<td>' + esc(r.chapter_name) + '</td>';
        html += '<td><div class="flex gap-1">';
        html += '<button type="button" class="btn btn-ghost btn-sm chapter-edit-btn" data-chid="' + esc(String(r.id)) + '"><?= icon('edit',14) ?></button>';
        html += '<button type="button" class="btn btn-ghost btn-sm chapter-delete-btn" style="color:var(--danger)" data-chid="' + esc(String(r.id)) + '"><?= icon('trash',14) ?></button>';
        html += '</div></td>';
        html += '</tr>';
      }
      tbody.innerHTML = html;
      tbody.querySelectorAll('.chapter-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const row = chapterMap.get(btn.dataset.chid);
          if (row) openChapterModal(row);
        });
      });
      tbody.querySelectorAll('.chapter-delete-btn').forEach(btn => {
        btn.addEventListener('click', () => window.deleteChapter(btn.dataset.chid) );
      });
    } catch(err) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-c" style="padding:24px;color:var(--danger)">' + (err.error || 'خطا در بارگذاری') + '</td></tr>';
    }
  }

  function openChapterModal(data = null) {
    document.getElementById('chapterModalTitle').textContent = data ? 'ویرایش فصل' : 'افزودن فصل جدید';
    document.getElementById('chapterId').value = data ? data.id : '';
    document.getElementById('chapField').value = data ? data.field : 'tajrobi';
    document.getElementById('chapGrade').value = data ? data.grade : '10';
    document.getElementById('chapSubjectName').value = data ? data.subject_name : '';
    document.getElementById('chapBookName').value = data ? data.book_name : '';
    document.getElementById('chapChapterName').value = data ? data.chapter_name : '';
    document.getElementById('chapSortOrder').value = data ? data.sort_order : '0';
    openModal('chapterModal');
  }
  window.editChapter = function(id, data) { openChapterModal(data); };
  window.deleteChapter = async function(id) {
    if (!confirm('این فصل حذف شود؟')) return;
    try {
      await api(API_CHAPTERS, { method: 'POST', body: { action: 'delete', id } });
      toast('فصل حذف شد', 'success');
      loadChapters();
    } catch(err) { toast(err.error || 'خطا', 'error'); }
  };
  window.openChapterModal = openChapterModal;

  document.getElementById('loadChaptersBtn').addEventListener('click', loadChapters);
  document.getElementById('resetChaptersBtn').addEventListener('click', async function() {
    if (!this.dataset.confirmed) { this.dataset.confirmed = '1'; toast('دوباره بزنید تا بازیابی شود', 'info', 3000); return; }
    delete this.dataset.confirmed;
    try {
      const d = await api(API_CHAPTERS, { method: 'POST', body: { action: 'reset_system' } });
      toast('بازیابی انجام شد: ' + d.added + ' فصل', 'success');
      loadChapters();
    } catch(err) { toast(err.error || 'خطا', 'error'); }
  });
  document.getElementById('chapterForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const body = {
      action: 'save',
      id: document.getElementById('chapterId').value,
      field: document.getElementById('chapField').value,
      grade: document.getElementById('chapGrade').value,
      subject_name: document.getElementById('chapSubjectName').value.trim(),
      book_name: document.getElementById('chapBookName').value.trim(),
      chapter_name: document.getElementById('chapChapterName').value.trim(),
      sort_order: document.getElementById('chapSortOrder').value,
    };
    try {
      await api(API_CHAPTERS, { method: 'POST', body });
      closeModal('chapterModal');
      toast('فصل ذخیره شد', 'success');
      loadChapters();
    } catch(err) { toast(err.error || 'خطا', 'error'); }
  });

  // Load initially
  loadChapters();
</script>
<?php panel_end(); ?>
