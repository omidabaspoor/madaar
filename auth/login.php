<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();

if (is_logged_in()) {
    
    $cu = current_user();
    if (user_role() === 'student') redirect('student/dashboard.php');
    redirect(($cu['role'] ?? '') === 'advisor' ? advisor_first_allowed_admin_path((int)$cu['id']) : 'admin/dashboard.php');
}

$err = '';
$old = ['username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // rate limit ساده مبتنی بر نشست
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0);
    $_SESSION['login_lock'] = $_SESSION['login_lock'] ?? 0;
    if (time() < $_SESSION['login_lock']) {
        $err = 'تلاش‌های زیاد. لطفاً ' . fa_num(ceil(($_SESSION['login_lock'] - time()))) . ' ثانیه صبر کنید.';
    } else {
        $username = trim((string)input('username'));
        $password = (string)input('password');
        $remember = (bool)input('remember');
        $old['username'] = $username;

        if ($username === '' || $password === '') {
            $err = 'نام کاربری و گذرواژه را وارد کنید.';
        } else {
            $st = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
            $st->execute([$username]);
            $u = $st->fetch();
            if ($u && password_verify($password, $u['password_hash'])) {
                if ($u['status'] === 'suspended') {
                    $err = 'حساب شما مسدود شده است. با مشاور خود تماس بگیرید.';
                    log_activity((int)$u['id'], 'login_failed', 'user', (int)$u['id'], ['علت' => 'حساب کاربری مسدود است']);
                } else {
                    $_SESSION['login_attempts'] = 0;
                    // به‌روزرسانی هش در صورت نیاز
                    if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
                        $up = db()->prepare('UPDATE users SET password_hash=? WHERE id=?');
                        $up->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]), $u['id']]);
                    }
                    login_user($u, $remember);
                    log_activity((int)$u['id'], 'user_login', 'user', (int)$u['id'], ['نقش' => $u['role'] === 'admin' ? 'مشاور ارشد' : ($u['role'] === 'advisor' ? 'مشاور تحصیلی' : 'دانش‌آموز')]);
                    if ($u['role'] === 'student' && $u['status'] === 'pending') redirect('auth/pending.php');
                    
                    if ($u['role'] === 'student') redirect('student/dashboard.php');
                    redirect($u['role'] === 'advisor' ? advisor_first_allowed_admin_path((int)$u['id']) : 'admin/dashboard.php');
                }
            } else {
                $_SESSION['login_attempts']++;
                $failUid = $u ? (int)$u['id'] : 0;
                log_activity($failUid, 'login_failed', 'user', $failUid, ['نام کاربری وارد شده' => $username, 'خطا' => 'رمز عبور یا نام کاربری نادرست']);
                if ($_SESSION['login_attempts'] >= 5) {
                    $_SESSION['login_lock'] = time() + 30;
                    $_SESSION['login_attempts'] = 0;
                    $err = 'تلاش‌های ناموفق زیاد. ۳۰ ثانیه صبر کنید.';
                } else {
                    $err = 'نام کاربری یا گذرواژه نادرست است.';
                }
            }
        }
    }
}

page_head('ورود', '', ['auth.css']);
?>
<div class="auth-wrap">
  <aside class="auth-aside">
    <?= brand_block() ?>
    <div class="a-content">
      <span class="badge badge-gold"><?= icon('sparkles',14) ?> خوش آمدید</span>
      <h2>دوباره <span class="gradient-text">سلام!</span><br>مسیرت منتظرته.</h2>
      <p>وارد حساب کاربری‌ات شو و تسک‌های امروز را ببین. هر قدم کوچک، یک گام به سمت هدف بزرگ است.</p>
      <div class="auth-points">
        <div class="auth-point"><span class="icon-tile sage"><?= icon('check-circle',20) ?></span><span>تسک‌های روزانه‌ات را تکمیل کن</span></div>
        <div class="auth-point"><span class="icon-tile"><?= icon('fire',20) ?></span><span>استریک مطالعه‌ات را حفظ کن</span></div>
        <div class="auth-point"><span class="icon-tile sage"><?= icon('message',20) ?></span><span>با مشاورت در ارتباط باش</span></div>
      </div>
    </div>
    <span class="muted" style="font-size:.82rem">© <?= e(APP_OWNER) ?></span>
  </aside>

  <main class="auth-main">
    <a href="<?= url('') ?>" class="btn btn-ghost btn-sm back-home"><?= icon('arrow-right',16) ?> خانه</a>
    <div class="auth-card">
      <div class="brand" style="justify-content:center"><?= logo_svg(44) ?></div>
      <div class="auth-head text-c">
        <h1>ورود به حساب</h1>
        <p>نام کاربری و گذرواژه‌ات را وارد کن</p>
      </div>

      <?php if ($err): ?>
      <div class="alert alert-error"><?= icon('info',18) ?><span><?= e($err) ?></span></div>
      <?php endif; ?>
      <?php foreach (get_flashes() as $f): ?>
      <div class="alert alert-<?= $f['type']==='success'?'success':'info' ?>"><?= icon('info',18) ?><span><?= e($f['msg']) ?></span></div>
      <?php endforeach; ?>

      <form method="post" data-loading novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="username">نام کاربری</label>
          <div class="input-group">
            <span class="ig-icon"><?= icon('user',18) ?></span>
            <input class="input" id="username" name="username" value="<?= e($old['username']) ?>" placeholder="مثلاً student_1404" autocomplete="username" required>
          </div>
        </div>
        <div class="field">
          <label for="password">گذرواژه</label>
          <div class="input-group">
            <span class="ig-icon" data-toggle-pass="password"><span class="eye-on"><?= icon('eye',18) ?></span><span class="eye-off"><?= icon('eye-off',18) ?></span></span>
            <input class="input" id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
          </div>
        </div>
        <div class="between" style="margin-bottom:20px">
          <label class="checkbox"><input type="checkbox" name="remember" value="1"><span class="box"><?= icon('check',14) ?></span><span style="font-size:.88rem">مرا به خاطر بسپار</span></label>
        </div>
        <button type="submit" class="btn btn-gold btn-block btn-lg"><?= icon('login',18) ?> ورود</button>
      </form>

      <div class="auth-foot">حساب نداری؟ <a href="<?= url('auth/register.php') ?>">ثبت‌نام کن</a></div>
    </div>
  </main>
</div>
<?php page_foot(); ?>
