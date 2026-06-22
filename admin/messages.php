<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/chat_view.php';
boot_session();
require_role('advisor','admin');
panel_start('پیام‌ها', 'گفتگو با دانش‌آموزان', 'admin', 'messages', ['student.css','chat.css']);
render_chat('admin');
panel_end(['chat.js']);
