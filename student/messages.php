<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/chat_view.php';
boot_session();
require_role('student');
panel_start('پیام‌ها', 'گفتگو با مشاورت', 'student', 'messages', ['student.css','chat.css']);
render_chat('student');
panel_end(['chat.js']);
