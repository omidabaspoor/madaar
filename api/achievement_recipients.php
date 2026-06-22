<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_role('advisor','admin');
$id = (int)($_GET['id'] ?? 0);
$rows = achievement_recipients($id);
$out = array_map(fn($r)=>[
    'id'=>(int)$r['id'],
    'name'=>$r['full_name'],
    'field'=>$r['field'] ?? '',
    'ago'=>time_ago($r['earned_at']),
    'manual'=>!empty($r['awarded_by']),
], $rows);
json_out(['ok'=>true,'items'=>$out]);
