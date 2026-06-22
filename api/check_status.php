<?php
require_once __DIR__ . '/../includes/auth.php';
boot_session();
header('Content-Type: application/json; charset=utf-8');
$u = current_user();
echo json_encode(['status' => $u['status'] ?? 'guest'], JSON_UNESCAPED_UNICODE);
