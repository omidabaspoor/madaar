<?php
/** مدیریت کاربران فقط-چت */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
require_chief_advisor();
advisor_page_access_schema_ready();
$u = current_user();
$adminId = (int)$u['id'];
$msg = null; $err = null;

function chat_user_mark(int $userId): void {
    set_advisor_setting($userId, 'chat_only_user', '1');
    db()->prepare('DELETE FROM advisor_page_access WHERE advisor_id=?')->execute([$userId]);
    db()->prepare('INSERT INTO advisor_page_access (advisor_id,page_key) VALUES (?,?) ON DUPLICATE KEY UPDATE page_key=VALUES(page_key)')->execute([$userId, 'messages']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $full = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
        if (!$full || !$username || !$password) $err = 'نام، نام کاربری و رمز عبور الزامی است.';
        elseif (mb_strlen($password) < 6) $err = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        else {
            $chk = db()->prepare('SELECT id FROM users WHERE username=?'); $chk->execute([$username]);
            if ($chk->fetch()) $err = 'این نام کاربری قبلاً ثبت شده است.';
            else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]);
                db()->prepare("INSERT INTO users (role,full_name,username,password_hash,phone,field,status,access_mode) VALUES ('advisor',?,?,?,?, 'پشتیبان چت', 'active', 'all')")
                    ->execute([$full,$username,$hash,$phone]);
                $id = (int)db()->lastInsertId();
                chat_user_mark($id);
                log_activity($adminId, 'chat_user_created', 'user', $id, ['نام'=>$full,'نام کاربری'=>$username]);
                $msg = 'کاربر چت با موفقیت ساخته شد.';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $full = trim((string)($_POST['full_name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
        if (!$id || !$full || !$username) $err = 'اطلاعات ارسالی کامل نیست.';
        else {
            $chk = db()->prepare('SELECT id FROM users WHERE username=? AND id<>?'); $chk->execute([$username,$id]);
            if ($chk->fetch()) $err = 'این نام کاربری توسط کاربر دیگری استفاده شده است.';
            else {
                if ($password !== '') {
                    if (mb_strlen($password) < 6) $err = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
                    else db()->prepare('UPDATE users SET full_name=?, username=?, password_hash=?, phone=?, field="پشتیبان چت", access_mode="all" WHERE id=? AND role="advisor"')
                        ->execute([$full,$username,password_hash($password, PASSWORD_BCRYPT, ['cost'=>BCRYPT_COST]),$phone,$id]);
                } else {
                    db()->prepare('UPDATE users SET full_name=?, username=?, phone=?, field="پشتیبان چت", access_mode="all" WHERE id=? AND role="advisor"')
                        ->execute([$full,$username,$phone,$id]);
                }
                if (!$err) { chat_user_mark($id); $msg = 'اطلاعات کاربر چت ذخیره شد.'; }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0); $status = ($_POST['status'] ?? '') === 'suspended' ? 'suspended' : 'active';
        db()->prepare('UPDATE users SET status=? WHERE id=? AND role="advisor"')->execute([$status,$id]);
        $msg = 'وضعیت کاربر به‌روزرسانی شد.';
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            db()->prepare('DELETE FROM advisor_page_access WHERE advisor_id=?')->execute([$id]);
            db()->prepare('DELETE FROM advisor_settings WHERE advisor_id=? AND skey="chat_only_user"')->execute([$id]);
            db()->prepare('DELETE FROM users WHERE id=? AND role="advisor"')->execute([$id]);
            $msg = 'کاربر چت حذف شد.';
        }
    }
}

$rows = db()->query("SELECT u.* FROM users u JOIN advisor_settings s ON s.advisor_id=u.id AND s.skey='chat_only_user' AND s.svalue='1' WHERE u.role='advisor' ORDER BY u.created_at DESC")->fetchAll();
panel_start('کاربران چت', 'ساخت کاربرانی که فقط به پیام‌ها دسترسی دارند', 'admin', 'chat_users', ['student.css']);
?>
<style>
.chat-user-hero{background:radial-gradient(circle at 12% 0%,rgba(203,172,128,.18),transparent 36%),linear-gradient(135deg,rgba(107,136,114,.16),rgba(12,21,18,.42));border:1px solid rgba(203,172,128,.28);border-radius:26px;padding:26px;margin-bottom:20px;display:flex;justify-content:space-between;gap:18px;align-items:center;flex-wrap:wrap}.cu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px}.cu-card{background:linear-gradient(160deg,var(--card),var(--surface));border:1px solid var(--border-soft);border-radius:22px;padding:18px}.cu-top{display:flex;gap:12px;align-items:center}.cu-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft)}.cu-actions .btn{flex:1}.chat-only-badge{background:rgba(95,174,123,.14);border:1px solid rgba(95,174,123,.30);color:var(--sage-light);border-radius:999px;padding:5px 10px;font-weight:900;font-size:.76rem}.cu-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:640px){.cu-modal-grid{grid-template-columns:1fr}}
</style>
<div class="chat-user-hero">
  <div>
    <span class="chat-only-badge"><?= icon('message',14) ?> فقط چت</span>
    <h2 style="margin:8px 0 4px;color:var(--gold-light);font-weight:1000">کاربران مخصوص پاسخ‌گویی پیام‌ها</h2>
    <p class="muted" style="line-height:1.8;margin:0">این کاربران پس از ورود مستقیم وارد صفحه پیام‌ها می‌شوند و به هیچ بخش دیگری از پنل دسترسی ندارند.</p>
  </div>
  <button class="btn btn-gold btn-lg" onclick="openChatUserCreate()" style="font-weight:900"><?= icon('user-plus',18) ?> افزودن کاربر چت</button>
</div>
<?php if($msg): ?><div class="alert alert-success mb-4"><?= icon('check-circle',18) ?><span><?= e($msg) ?></span></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-error mb-4"><?= icon('info',18) ?><span><?= e($err) ?></span></div><?php endif; ?>
<?php if(!$rows): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('message',34) ?></div>هنوز کاربر چت ساخته نشده است.</div></div>
<?php else: ?>
<div class="cu-grid">
<?php foreach($rows as $r): $json=e(json_encode(['id'=>(int)$r['id'],'full_name'=>$r['full_name'],'username'=>$r['username'],'phone'=>$r['phone']??''], JSON_UNESCAPED_UNICODE)); ?>
  <div class="cu-card">
    <div class="between gap-2">
      <div class="cu-top"><span class="u-ava gold"><?= e(avatar_letters($r['full_name'])) ?></span><div><b><?= e($r['full_name']) ?></b><div class="muted" dir="ltr">@<?= e($r['username']) ?></div></div></div>
      <span class="badge <?= $r['status']==='active'?'badge-sage':'badge-danger' ?>"><?= $r['status']==='active'?'فعال':'مسدود' ?></span>
    </div>
    <?php if($r['phone']): ?><div class="muted mt-3" dir="ltr"><?= icon('phone',14) ?> <?= e($r['phone']) ?></div><?php endif; ?>
    <div class="cu-actions">
      <button class="btn btn-ghost btn-sm" onclick='openChatUserEdit(<?= $json ?>)'><?= icon('edit',15) ?> ویرایش</button>
      <form method="post" style="flex:1"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="status" value="<?= $r['status']==='active'?'suspended':'active' ?>"><button class="btn btn-ghost btn-sm btn-block"><?= $r['status']==='active'?'مسدود':'فعال' ?></button></form>
      <form method="post" onsubmit="return confirm('این کاربر حذف شود؟')" style="flex:0 0 auto"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-ghost btn-sm" style="color:var(--danger)"><?= icon('trash',15) ?></button></form>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-backdrop" id="chatUserModal">
  <div class="modal" style="max-width:620px;padding:0;overflow:hidden">
    <div class="modal-head"><h3 id="cuModalTitle"><?= icon('user-plus',20) ?> افزودن کاربر چت</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <form method="post" style="padding:22px" class="grid gap-3">
      <?= csrf_field() ?><input type="hidden" name="action" id="cuAction" value="create"><input type="hidden" name="id" id="cuId">
      <div class="cu-modal-grid"><div class="field"><label>نام و نام خانوادگی</label><input class="input" name="full_name" id="cuFull" required></div><div class="field"><label>شماره تلفن</label><input class="input" name="phone" id="cuPhone" dir="ltr"></div></div>
      <div class="cu-modal-grid"><div class="field"><label>نام کاربری</label><input class="input" name="username" id="cuUsername" dir="ltr" required></div><div class="field"><label>رمز عبور</label><input class="input" name="password" id="cuPass" type="password" dir="ltr" minlength="6" required><small class="muted" id="cuPassHint" style="display:none">برای عدم تغییر رمز، خالی بگذارید.</small></div></div>
      <div class="alert alert-info" style="margin:0"><?= icon('shield',16) ?><span>دسترسی این کاربر به‌صورت خودکار فقط روی صفحه پیام‌ها تنظیم می‌شود.</span></div>
      <div class="flex gap-2" style="justify-content:flex-end"><button type="button" class="btn btn-ghost" data-close>انصراف</button><button class="btn btn-gold" style="font-weight:900">ذخیره</button></div>
    </form>
  </div>
</div>
<script>
function openChatUserCreate(){document.getElementById('cuModalTitle').innerHTML='<?= icon('user-plus',20) ?> افزودن کاربر چت';document.getElementById('cuAction').value='create';document.getElementById('cuId').value='';document.getElementById('cuFull').value='';document.getElementById('cuPhone').value='';document.getElementById('cuUsername').value='';document.getElementById('cuPass').value='';document.getElementById('cuPass').required=true;document.getElementById('cuPassHint').style.display='none';openModal('chatUserModal')}
function openChatUserEdit(u){document.getElementById('cuModalTitle').innerHTML='<?= icon('edit',20) ?> ویرایش کاربر چت';document.getElementById('cuAction').value='edit';document.getElementById('cuId').value=u.id;document.getElementById('cuFull').value=u.full_name||'';document.getElementById('cuPhone').value=u.phone||'';document.getElementById('cuUsername').value=u.username||'';document.getElementById('cuPass').value='';document.getElementById('cuPass').required=false;document.getElementById('cuPassHint').style.display='block';openModal('chatUserModal')}
</script>
<?php panel_end(); ?>
