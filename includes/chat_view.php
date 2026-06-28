<?php
/** نمای چت مشترک — برای پنل مدیر و دانش‌آموز */
declare(strict_types=1);

function render_chat(string $role): void
{
    $with = (int)($_GET['with'] ?? 0);
    ?>
<style>
/* Critical chat layout override — inline to beat host/CDN stale CSS */
.chat-shell{display:grid!important;grid-template-columns:minmax(285px,340px) minmax(0,1fr)!important;gap:16px!important;height:calc(100vh - 132px)!important;min-height:560px!important;position:relative!important;direction:rtl!important}
.chat-shell>.chat-list{position:relative!important;inset:auto!important;transform:none!important;width:auto!important;max-width:none!important;height:auto!important;max-height:none!important;display:flex!important;flex-direction:column!important;grid-column:auto!important;grid-row:auto!important;border-radius:24px!important;overflow:hidden!important;background:linear-gradient(180deg,rgba(28,40,35,.92),rgba(21,32,27,.98))!important;border:1px solid var(--border-soft)!important;box-shadow:var(--sh-sm)!important;padding:0!important}
.chat-shell>.chat-main{height:auto!important;min-height:0!important;display:flex!important;flex-direction:column!important;border-radius:24px!important;overflow:hidden!important;background:linear-gradient(180deg,rgba(28,40,35,.92),rgba(21,32,27,.98))!important;border:1px solid var(--border-soft)!important}
.chat-shell .chat-contacts{overflow-y:auto!important;flex:1!important;padding:8px!important}.chat-shell .chat-search-wrap{padding:12px!important;border-bottom:1px solid var(--border-soft)!important}.chat-contact-toggle{display:none!important}

@media(max-width:900px){
  .chat-shell{display:block!important;height:calc(100dvh - 142px)!important;min-height:480px!important;position:relative!important;}
  .chat-shell>.chat-main{height:100%!important;}
  .chat-shell>.chat-list{
    position:fixed!important;left:12px!important;right:12px!important;
    bottom:calc(12px + env(safe-area-inset-bottom))!important;top:auto!important;
    z-index:9999!important;max-height:min(74dvh,540px)!important;height:auto!important;
    border-radius:24px!important;box-shadow:0 -10px 40px rgba(0,0,0,0.5)!important;
    border:1px solid rgba(178,148,95,0.35)!important;
    transform:translateY(calc(100% + 40px))!important;transition:transform 0.3s cubic-bezier(0.16, 1, 0.3, 1)!important;
    background:rgba(20,32,27,0.98)!important;backdrop-filter:blur(20px)!important;-webkit-backdrop-filter:blur(20px)!important;
  }
  .chat-shell>.chat-list::before {
    content:''; display:block; width:40px; height:4px; background:rgba(255,255,255,0.15);
    border-radius:2px; margin:8px auto 0;
  }
  .chat-shell>.chat-list.open{transform:translateY(0)!important;}
  .chat-contact-toggle{
    display:inline-flex!important;align-items:center!important;gap:6px!important;
    border:1px solid rgba(178,148,95,0.4)!important;background:linear-gradient(135deg,#1c2823,#15201b)!important;
    color:var(--gold-light)!important;border-radius:999px!important;padding:8px 14px!important;
    font-size:.78rem!important;font-weight:900!important;box-shadow:0 4px 15px rgba(0,0,0,0.25)!important;
  }
  .chat-shell .chat-contacts{max-height:calc(min(74dvh,540px) - 90px)!important;}
}
</style>
<div class="chat-shell <?= $role==='student'?'student-mode':'' ?>">
  <aside class="chat-list" id="chatList" aria-label="فهرست گفتگوها">
    <div class="chat-list-head">
      <div><b>گفتگوها</b><span>پیام‌ها و فایل‌ها</span></div>
      <span class="chat-app-dot"></span>
    </div>
    <div class="chat-search-wrap">
      <div class="input-group">
        <span class="ig-icon"><?= icon('search',17) ?></span>
        <input class="input" id="chatSearch" placeholder="جستجوی گفتگو…" autocomplete="off">
      </div>
    </div>
    <div id="chatContacts" class="chat-contacts">
      <div class="empty-state" style="padding:30px"><span class="spinner"></span></div>
    </div>
  </aside>

  <section class="chat-main" id="chatMain">
    <div class="chat-head" id="chatHead">
      <span class="u-ava chat-ava" id="chatAva">—</span>
      <div class="chat-head-info">
        <div class="nm" id="chatName">یک گفتگو را انتخاب کنید</div>
        <div class="muted" id="chatSub">پیام، عکس، فایل و ویس را از همین‌جا ارسال کنید</div>
      </div>
      <span class="chat-state" id="chatState"></span>
      <button type="button" class="chat-contact-toggle" id="chatContactsToggle"><?= icon('users',16) ?> گفتگوها</button>
    </div>

    <div class="chat-body" id="chatBody">
      <div class="empty-state chat-empty">
        <div class="es-ico"><?= icon('message',30) ?></div>
        <p>برای شروع، یک گفتگو را از لیست انتخاب کن</p>
        <p class="muted" style="font-size:.82rem">ارسال متن، عکس، PDF/فایل و ویس پشتیبانی می‌شود.</p>
      </div>
    </div>

    <div class="voice-record-bar hidden" id="voiceRecordBar">
      <span class="record-dot"></span>
      <div class="vr-info">
        <b>در حال ضبط ویس</b>
        <span id="voiceTimer">۰۰:۰۰</span>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" id="voiceCancel">لغو</button>
      <button type="button" class="btn btn-gold btn-sm" id="voiceStop"><?= icon('stop',14) ?> پایان ضبط</button>
    </div>

    <div class="chat-attach-preview hidden" id="attachPreview">
      <div class="ap-content" id="attachPreviewBody"></div>
      <button type="button" class="modal-close" id="attachClear" aria-label="حذف فایل"><?= icon('close',16) ?></button>
    </div>

    <form class="chat-input" id="chatForm" style="display:none">
      <input type="file" id="chatCamera" accept="image/*" capture="environment" hidden>
      <input type="file" id="chatGallery" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
      <input type="file" id="chatFile" accept="application/pdf,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.7z" hidden>
      <button class="chat-tool" type="button" id="attachBtn" data-tip="ارسال عکس یا فایل"><?= icon('paperclip',19) ?></button>
      <button class="chat-tool" type="button" id="voiceBtn" data-tip="ضبط ویس"><?= icon('mic',19) ?></button>
      <input class="input" id="chatText" placeholder="پیام" autocomplete="off" maxlength="2000">
      <button class="btn btn-gold btn-icon" type="submit" id="chatSend" data-tip="ارسال"><?= icon('send',18) ?></button>
    </form>
  </section>
</div>

<!-- attachment action sheet -->
<div class="modal-backdrop chat-sheet-backdrop" id="attachSheet">
  <div class="modal chat-attach-sheet">
    <div class="modal-head">
      <div>
        <h3><?= icon('paperclip',20) ?> پیوست</h3>
        <p class="muted" style="font-size:.82rem;margin-top:4px">عکس، ویس یا فایل را انتخاب کنید</p>
      </div>
      <button class="modal-close" data-close><?= icon('close',18) ?></button>
    </div>
    <div class="attach-options">
      <button type="button" class="attach-option" data-pick="camera">
        <span class="attach-ico camera"><?= icon('image',24) ?></span>
        <span><b>دوربین</b><small>گرفتن عکس جدید</small></span>
      </button>
      <button type="button" class="attach-option" data-pick="gallery">
        <span class="attach-ico gallery"><?= icon('image',24) ?></span>
        <span><b>گالری</b><small>انتخاب عکس از گوشی</small></span>
      </button>
      <button type="button" class="attach-option" data-pick="file">
        <span class="attach-ico file"><?= icon('paperclip',24) ?></span>
        <span><b>PDF و فایل</b><small>PDF، ورد، اکسل، پاورپوینت، txt و zip</small></span>
      </button>
    </div>
  </div>
</div>

<script>
  window.API_MSG = '<?= url('api/messages.php') ?>';
  window.NOTIF_URL='<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL='<?= url('api/notifications.php?read=1') ?>';
  window.INIT_WITH = <?= $with ?: 'null' ?>;
  window.MY_ROLE = '<?= e($role) ?>';
  setTimeout(function(){
    if (!window.MADAR_CHAT_READY) {
      var el = document.getElementById('chatContacts');
      if (el) el.innerHTML = '<div class="empty-state" style="padding:30px">فایل chat.js لود نشده است. آدرس HTTPS/کش هاست را بررسی کنید.</div>';
    }
  }, 2500);
</script>
<?php
}
