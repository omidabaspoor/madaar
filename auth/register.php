<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();

if (is_logged_in()) {
    redirect(user_role() === 'student' ? 'student/dashboard.php' : 'admin/dashboard.php');
}

$err = '';
$old = ['full_name'=>'','username'=>'','phone'=>'','field'=>'','grade'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['full_name'] = trim((string)input('full_name'));
    $old['username']  = trim((string)input('username'));
    $old['phone']     = trim((string)input('phone'));
    $old['field']     = trim((string)input('field'));
    $old['grade']     = trim((string)input('grade'));
    $pass    = (string)input('password');
    $pass2   = (string)input('password2');

    // اعتبارسنجی
    if (mb_strlen($old['full_name']) < 3)            $err = 'نام و نام خانوادگی را کامل وارد کنید.';
    elseif (!preg_match('/^[a-zA-Z0-9_\.]{4,30}$/', $old['username'])) $err = 'نام کاربری باید ۴ تا ۳۰ کاراکتر انگلیسی/عدد/خط‌تیره باشد.';
    elseif ($old['phone'] !== '' && !preg_match('/^09\d{9}$/', $old['phone'])) $err = 'شماره موبایل باید با ۰۹ و ۱۱ رقم باشد.';
    elseif (strlen($pass) < 6)                       $err = 'گذرواژه باید حداقل ۶ کاراکتر باشد.';
    elseif ($pass !== $pass2)                        $err = 'تکرار گذرواژه مطابقت ندارد.';
    else {
        // یکتایی نام کاربری
        $chk = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $chk->execute([$old['username']]);
        if ($chk->fetch()) {
            $err = 'این نام کاربری قبلاً استفاده شده است.';
        } else {
            // مشاور پیش‌فرض = اولین advisor/admin
            $adv = db()->query("SELECT id FROM users WHERE role IN ('advisor','admin') ORDER BY id ASC LIMIT 1")->fetchColumn();
            $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $ins = db()->prepare('INSERT INTO users (role,full_name,username,phone,field,grade,advisor_id,password_hash,status) VALUES ("student",?,?,?,?,?,?,?,"pending")');
            $ins->execute([$old['full_name'],$old['username'],$old['phone'] ?: null,$old['field'] ?: null,$old['grade'] ?: null,$adv ?: null,$hash]);
            $newId = (int)db()->lastInsertId();
            // اعلان به مشاور
            if ($adv) notify((int)$adv, 'درخواست عضویت جدید', $old['full_name'] . ' منتظر تأیید شماست.', 'user', 'admin/students.php');
            log_activity($newId, 'student_registered', 'user', $newId, ['نام' => $old['full_name'], 'رشته' => $old['field'], 'پایه' => $old['grade']]);
            // لاگین و هدایت به صفحه انتظار
            $u = ['id'=>$newId,'role'=>'student','status'=>'pending'];
            login_user($u);
            flash('success', 'حساب شما ساخته شد! منتظر تأیید مشاور بمانید.');
            redirect('auth/pending.php');
        }
    }
}

page_head('ثبت‌نام', '', ['auth.css']);
?>
<div class="auth-wrap">
  <aside class="auth-aside">
    <?= brand_block() ?>
    <div class="a-content">
      <span class="badge badge-sage"><?= icon('rocket',14) ?> شروع مسیر</span>
      <h2>به <span class="gradient-text">مَدار</span> بپیوند<br>و منظم پیش برو.</h2>
      <p>حساب دانش‌آموزی‌ات را بساز. پس از تأیید مشاور، برنامه‌ی هفتگی اختصاصی‌ات فعال می‌شود.</p>
      <div class="auth-points">
        <div class="auth-point"><span class="icon-tile sage"><?= icon('user',20) ?></span><span>۱. ثبت‌نام و تأیید مشاور</span></div>
        <div class="auth-point"><span class="icon-tile"><?= icon('calendar',20) ?></span><span>۲. دریافت برنامه هفتگی</span></div>
        <div class="auth-point"><span class="icon-tile sage"><?= icon('trophy',20) ?></span><span>۳. پیشرفت و دستاورد</span></div>
      </div>
    </div>
    <span class="muted" style="font-size:.82rem">© <?= e(APP_OWNER) ?></span>
  </aside>

  <main class="auth-main">
    <a href="<?= url('') ?>" class="btn btn-ghost btn-sm back-home"><?= icon('arrow-right',16) ?> خانه</a>
    <div class="auth-card">
      <div class="brand" style="justify-content:center"><?= logo_svg(44) ?></div>
      <div class="auth-head text-c">
        <h1>ساخت حساب دانش‌آموز</h1>
        <p>چند لحظه‌ی کوتاه تا شروع مسیر</p>
      </div>

      <?php if ($err): ?>
      <div class="alert alert-error"><?= icon('info',18) ?><span><?= e($err) ?></span></div>
      <?php endif; ?>

      <form method="post" data-loading novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="full_name">نام و نام خانوادگی</label>
          <div class="input-group"><span class="ig-icon"><?= icon('user',18) ?></span>
          <input class="input" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" placeholder="مثلاً علی رضایی" required></div>
        </div>
        <div class="field">
          <label for="username">نام کاربری (انگلیسی)</label>
          <div class="input-group"><span class="ig-icon"><?= icon('login',18) ?></span>
          <input class="input" id="username" name="username" dir="ltr" value="<?= e($old['username']) ?>" placeholder="ali_rezaei" autocomplete="username" required></div>
        </div>
        <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
          <div class="field">
            <label for="field">رشته</label>
            <select class="select" id="field" name="field">
              <option value="">انتخاب…</option>
              <?php foreach (['تجربی','ریاضی','انسانی','هنر','زبان'] as $f): ?>
              <option <?= $old['field']===$f?'selected':'' ?>><?= e($f) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="grade">پایه</label>
            <select class="select" id="grade" name="grade">
              <option value="">انتخاب…</option>
              <?php foreach (['دهم','یازدهم','دوازدهم','کنکوری'] as $g): ?>
              <option <?= $old['grade']===$g?'selected':'' ?>><?= e($g) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label for="phone">موبایل (اختیاری)</label>
          <div class="input-group"><span class="ig-icon"><?= icon('phone',18) ?></span>
          <input class="input" id="phone" name="phone" dir="ltr" inputmode="numeric" value="<?= e($old['phone']) ?>" placeholder="09xxxxxxxxx"></div>
        </div>
        <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
          <div class="field">
            <label for="password">گذرواژه</label>
            <div class="input-group"><span class="ig-icon" data-toggle-pass="password"><span class="eye-on"><?= icon('eye',18) ?></span><span class="eye-off"><?= icon('eye-off',18) ?></span></span>
            <input class="input" id="password" name="password" type="password" placeholder="••••••" autocomplete="new-password" required></div>
          </div>
          <div class="field">
            <label for="password2">تکرار گذرواژه</label>
            <div class="input-group"><span class="ig-icon"><?= icon('lock',18) ?></span>
            <input class="input" id="password2" name="password2" type="password" placeholder="••••••" autocomplete="new-password" required></div>
          </div>
        </div>
        <button type="submit" class="btn btn-gold btn-block btn-lg"><?= icon('check',18) ?> ساخت حساب</button>
      </form>

      <div class="auth-foot">قبلاً ثبت‌نام کرده‌ای؟ <a href="<?= url('auth/login.php') ?>">وارد شو</a></div>
    </div>
  </main>
</div>
<?php page_foot(); ?>
