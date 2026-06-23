<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/online_sessions.php';
boot_session();
require_role('student');
$u = current_user();

online_sessions_schema_ready();

$sessions = online_sessions_for_student((int)$u['id']);

panel_start('جلسات آنلاین', 'کلاس‌های مجازی که توسط مشاور شما برگزار می‌شود - نسخه‌ی بتا', 'student', 'online_sessions', ['student.css']);
?>

<style>
.os-hero{background:radial-gradient(circle at 12% 0%,rgba(111,155,192,.18),transparent 38%),linear-gradient(135deg,rgba(111,155,192,.14),rgba(12,21,18,.4));border:1px solid rgba(111,155,192,.3);border-radius:var(--r-xl);padding:24px;margin-bottom:24px}
.os-hero h2{font-size:1.35rem;color:#a0d2eb;font-weight:1000;margin:8px 0 6px}
.os-hero p{color:var(--text-2);font-size:.92rem;line-height:1.85;max-width:740px;margin:0}

.os-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:16px;margin-top:8px}
@media(max-width:430px){.os-grid{grid-template-columns:1fr}}

.os-card{background:linear-gradient(160deg,var(--card),var(--surface));border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:20px;position:relative;transition:.2s;overflow:hidden}
.os-card:hover{border-color:var(--border);transform:translateY(-2px)}
.os-card.is-live{border-color:var(--success);box-shadow:0 0 24px rgba(95,174,123,.15)}
.os-card.is-live::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 0 0,rgba(95,174,123,.1),transparent 60%);pointer-events:none}

.os-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
.os-icon{width:50px;height:50px;border-radius:15px;background:var(--info);color:#0c1512;display:grid;place-items:center;flex-shrink:0;font-size:1.4rem}
.os-info{flex:1;min-width:0}
.os-title{font-weight:900;font-size:1.05rem;color:var(--text-1);margin-bottom:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.os-meta{font-size:.82rem;color:var(--text-3);display:flex;flex-wrap:wrap;gap:12px}
.os-meta span{display:inline-flex;align-items:center;gap:5px}

.os-status{position:absolute;top:14px;right:14px;font-size:.7rem;font-weight:1000;padding:4px 11px;border-radius:99px;letter-spacing:.04em}
.os-status-live{background:linear-gradient(135deg,#5fae7b,#3d8a5c);color:#07120b;animation:pulse 2s infinite}
.os-status-scheduled{background:rgba(111,155,192,.2);color:#a0d2eb;border:1px solid rgba(111,155,192,.4)}
.os-status-ended{background:rgba(127,141,134,.15);color:var(--text-3)}
.os-status-cancelled{background:rgba(217,116,116,.15);color:var(--danger)}

.os-perms{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0;padding:10px;background:var(--surface-2);border-radius:12px}
.os-perm-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;background:var(--bg);border:1px solid var(--border-soft);border-radius:99px;font-size:.72rem;font-weight:800;color:var(--text-2)}
.os-perm-pill.disabled{opacity:.4;text-decoration:line-through}

.os-join-btn{display:flex;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-soft)}
.os-join-btn .btn{flex:1;min-height:48px;font-weight:900;justify-content:center;font-size:.92rem}
</style>

<div class="os-hero">
  <span class="badge" style="background:rgba(111,155,192,.2);color:#a0d2eb;border:1px solid rgba(111,155,192,.3);font-weight:900;display:inline-flex;align-items:center;gap:6px;margin-bottom:8px">
    <?= icon('sparkles',13) ?> نسخه‌ی بتا
  </span>
  <h2>🎥 جلسات آنلاین</h2>
  <p>اینجا جلسات تصویری‌ای که مشاور شما برگزار می‌کند نمایش داده می‌شود. برای ورود، فقط روی «ورود به اتاق» کلیک کنید. جلسات فعال با نور سبز مشخص هستند.</p>
</div>

<?php if (empty($sessions)): ?>
  <div class="panel" style="padding:60px 20px;text-align:center">
    <div class="empty-state">
      <div class="es-ico" style="width:90px;height:90px;border-radius:50%;background:var(--surface-2);display:grid;place-items:center;margin:0 auto 20px">
        <?= icon('video',48) ?>
      </div>
      <p style="font-size:1.05rem;font-weight:700">هنوز جلسه آنلاینی برای شما ایجاد نشده</p>
      <p class="muted" style="margin-top:10px">به محض اینکه مشاور شما جلسه‌ای تنظیم کند، در اینجا نمایش داده می‌شود.</p>
    </div>
  </div>
<?php else: ?>
  <div class="os-grid">
    <?php foreach ($sessions as $s):
      $status = $s['status'] ?? 'scheduled';
      $isLive = $status === 'live';
      $isScheduled = $status === 'scheduled';
      $isEnded = $status === 'ended';
      $isCancelled = $status === 'cancelled';

      $statusClass = [
        'live' => 'os-status-live',
        'scheduled' => 'os-status-scheduled',
        'ended' => 'os-status-ended',
        'cancelled' => 'os-status-cancelled',
      ][$status] ?? '';
      $statusText = [
        'live' => '🔴 LIVE',
        'scheduled' => 'برنامه‌ریزی',
        'ended' => 'پایان یافته',
        'cancelled' => 'لغو شده',
      ][$status] ?? $status;
    ?>
      <div class="os-card is-<?= $status ?>">
        <div class="os-status <?= $statusClass ?>"><?= $statusText ?></div>

        <div class="os-head">
          <div class="os-icon"><?= icon('video',24) ?></div>
          <div class="os-info">
            <div class="os-title"><?= e($s['title']) ?></div>
            <div class="os-meta">
              <span><?= icon('user',13) ?> مشاور: <?= e($s['advisor_name']) ?></span>
              <?php if ($s['scheduled_at']): ?>
                <span><?= icon('calendar',13) ?> <?= jalali_date(date('Y-m-d', strtotime($s['scheduled_at']))) ?> · <?= fa_num(date('H:i', strtotime($s['scheduled_at']))) ?></span>
              <?php endif; ?>
              <span><?= icon('clock',13) ?> <?= fa_num($s['duration_min']) ?> دقیقه</span>
            </div>
          </div>
        </div>

        <?php if (!empty($s['description'])): ?>
          <div style="padding:10px 12px;background:var(--surface-2);border-radius:10px;font-size:.86rem;color:var(--text-2);margin-bottom:10px;line-height:1.7">
            <?= e($s['description']) ?>
          </div>
        <?php endif; ?>

        <!-- دسترسی‌ها -->
        <div class="os-perms">
          <?php
          $perms = [
            'allow_student_mic' => ['🎤', 'میکروفون'],
            'allow_student_cam' => ['📹', 'دوربین'],
            'allow_screen_share' => ['🖥️', 'اشتراک صفحه'],
            'allow_whiteboard' => ['✏️', 'تخته'],
            'allow_chat' => ['💬', 'چت'],
          ];
          foreach ($perms as $key => $info):
            $enabled = !empty($s[$key]);
          ?>
            <span class="os-perm-pill <?= $enabled ? '' : 'disabled' ?>"><?= $info[0] ?> <?= $info[1] ?></span>
          <?php endforeach; ?>
        </div>

        <div class="os-join-btn">
          <?php if ($isLive): ?>
            <a href="../online_room.php?session=<?= (int)$s['id'] ?>" class="btn btn-gold" style="background:var(--success);color:#0c1512">
              <?= icon('login',16) ?> ورود به اتاق جلسه
            </a>
          <?php elseif ($isScheduled): ?>
            <a href="../online_room.php?session=<?= (int)$s['id'] ?>" class="btn btn-gold">
              <?= icon('calendar',16) ?> مشاهده و ورود
            </a>
            <span class="badge badge-gold" style="display:inline-flex;align-items:center;padding:8px 14px">
              <?= icon('clock',13) ?> منتظر شروع
            </span>
          <?php elseif ($isEnded): ?>
            <span class="btn btn-ghost" style="flex:1;color:var(--text-3);cursor:not-allowed">
              <?= icon('check',14) ?> جلسه برگزار شد
            </span>
          <?php elseif ($isCancelled): ?>
            <span class="btn btn-ghost" style="flex:1;color:var(--danger);cursor:not-allowed">
              <?= icon('close',14) ?> لغو شده
            </span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php panel_end(); ?>
