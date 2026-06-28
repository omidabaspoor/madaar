<?php
/** Global live heartbeat for panels — max 2s polling */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_login();
$u = current_user();
$uid = (int)$u['id'];
$role = (string)$u['role'];

$out = [
    'ok' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'notif_count' => unread_notif_count($uid),
    'msg_count' => unread_msg_count($uid),
    'role' => $role,
];

try {
    if ($role === 'student') {
        task_status_schema_ready();
        $todayTasks = student_today_tasks($uid);
        $total = count($todayTasks);
        $score = 0; $full = 0; $partial = 0; $missed = 0; $pending = 0;
        $taskSigParts = [];
        foreach ($todayTasks as $t) {
            $s = task_status($t);
            $score += task_score($t);
            if ($s === 'full') $full++;
            elseif ($s === 'partial') $partial++;
            elseif ($s === 'missed') $missed++;
            else $pending++;
            $taskSigParts[] = $t['id'] . ':' . $s . ':' . (int)($t['done_count'] ?? 0) . ':' . (string)($t['course_percent'] ?? '');
        }
        $out['student'] = [
            'streak' => (int)($u['streak'] ?? 0),
            'today_total' => $total,
            'today_pct' => $total ? round($score / $total * 100) : 0,
            'today_full' => $full,
            'today_partial' => $partial,
            'today_missed' => $missed,
            'today_pending' => $pending,
            'task_signature' => md5(implode('|', $taskSigParts)),
        ];
    } elseif (in_array($role, ['advisor','admin'], true)) {
        $stats = advisor_stats($uid);
        $out['advisor'] = [
            'students_total' => (int)$stats['total'],
            'students_active' => (int)$stats['active'],
            'students_pending' => (int)$stats['pending'],
            'tasks_total' => (int)$stats['tasksTotal'],
            'completion_rate' => (int)$stats['rate'],
        ];
    }
} catch (Throwable $e) {
    $out['soft_error'] = APP_ENV === 'development' ? $e->getMessage() : true;
}

json_out($out);
