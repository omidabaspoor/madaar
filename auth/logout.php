<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
boot_session();
if (is_logged_in()) {
    log_activity((int)current_user()['id'], 'user_logout', 'user', (int)current_user()['id']);
}
logout_user();
flash('info', 'با موفقیت خارج شدید. به امید دیدار!');
redirect('auth/login.php');
