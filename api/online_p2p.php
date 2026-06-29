<?php
/**
 * API سیگنالینگ P2P WebRTC برای جلسات آنلاین
 * فقط پیام‌های signaling رو رد و بدل می‌کنه - خود ویدئو P2P است
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/online_sessions.php';
require_once __DIR__ . '/../includes/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    boot_session();
}

$u = current_user();
if (!$u) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$roomId = (int)($_GET['room_id'] ?? ($body['room_id'] ?? 0));
$myId = (string)($_GET['my_id'] ?? ($body['my_id'] ?? '')); // peer id مثل p_xxx رشته است، نه عدد

// P2P CSRF: همه‌ی درخواست‌ها از صفحه‌ی اتاق با توکن session ارسال می‌شوند.
$csrfToken = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf'] ?? ($body['csrf'] ?? ($body['csrf_token'] ?? ''))));
if (!$csrfToken && empty($_SESSION['user_id'])) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'csrf_invalid']);
    exit;
}

if ($roomId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'invalid_room']);
    exit;
}

// بررسی دسترسی به جلسه
$session = online_session_get($roomId);
if (!$session) {
    echo json_encode(['ok' => false, 'error' => 'session_not_found']);
    exit;
}

$role = $u['role'];
$me = (int)$u['id'];

if (($role === 'student' && !online_session_student_can_access($roomId, $me))
    || ($role === 'advisor' && (int)($session['advisor_id'] ?? 0) !== $me)) {
    echo json_encode(['ok' => false, 'error' => 'no_access']);
    exit;
}

if (!online_sessions_schema_ready()) {
    echo json_encode(['ok' => false, 'error' => 'schema_missing']);
    exit;
}

if (($session['status'] ?? '') !== 'live' && !in_array($action, ['leave'], true)) {
    echo json_encode(['ok' => false, 'error' => 'session_not_live', 'status' => $session['status'] ?? '']);
    exit;
}

function p2p_peer_owned(string $peerId, int $roomId, int $userId): bool {
    if ($peerId === '') return false;
    try {
        $st = db()->prepare('SELECT 1 FROM session_peers WHERE room_id=? AND peer_id=? AND user_id=? LIMIT 1');
        $st->execute([$roomId, $peerId, $userId]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function p2p_effective_media(array $session, int $userId, string $role, array $body): array {
    $perm = online_permission_effective_state($session, $userId, $role);
    $isHost = online_session_is_host($session, $userId, $role);
    return [
        'mic' => ($isHost || !empty($perm['mic'])) && !empty($body['mic_on']) ? 1 : 0,
        'cam' => ($isHost || !empty($perm['cam'])) && !empty($body['cam_on']) ? 1 : 0,
        'screen' => ($isHost || !empty($perm['screen'])) && !empty($body['screen_on']) ? 1 : 0,
    ];
}

// ساخت جدول peers اگر نیست
try {
    db()->exec("CREATE TABLE IF NOT EXISTS session_peers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        peer_id VARCHAR(64) NOT NULL,
        user_id INT NOT NULL,
        user_name VARCHAR(120),
        is_host TINYINT(1) DEFAULT 0,
        last_poll DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_peer (room_id, peer_id),
        INDEX idx_peers_room (room_id, last_poll)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS session_signals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        from_peer VARCHAR(64) NOT NULL,
        to_peer VARCHAR(64) NOT NULL,
        signal_json LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        delivered_at DATETIME,
        INDEX idx_signals (room_id, to_peer, delivered_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS session_peer_commands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        room_id INT NOT NULL,
        target_user_id INT NOT NULL,
        command VARCHAR(40) NOT NULL,
        from_user_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        delivered_at DATETIME,
        INDEX idx_cmd_target (room_id, target_user_id, delivered_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach (['mic_on TINYINT(1) DEFAULT 0','cam_on TINYINT(1) DEFAULT 0','screen_on TINYINT(1) DEFAULT 0'] as $def) {
        $col = strtok($def, ' ');
        try { db()->exec("ALTER TABLE session_peers ADD COLUMN $def"); } catch (Throwable $e) {}
    }
} catch (Throwable $e) {
    // ignore if already exists
}

try {
    switch ($action) {

    case 'register': {
        $peerId = 'p_' . bin2hex(random_bytes(8));
        $name = trim((string)($body['name'] ?? $u['full_name']));
        // امنیت: کلاینت حق ندارد خودش را میزبان معرفی کند. میزبان فقط مشاور مالک جلسه یا admin است.
        $isHost = ((int)($session['advisor_id'] ?? 0) === $me || $role === 'admin') ? 1 : 0;

        $media = p2p_effective_media($session, $me, $role, $body);
        $micOn = $media['mic'];
        $camOn = $media['cam'];
        $screenOn = $media['screen'];
        db()->prepare('INSERT INTO session_peers (room_id, peer_id, user_id, user_name, is_host, mic_on, cam_on, screen_on, last_poll) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_poll=NOW(), user_name=VALUES(user_name), is_host=VALUES(is_host), mic_on=VALUES(mic_on), cam_on=VALUES(cam_on), screen_on=VALUES(screen_on)')
            ->execute([$roomId, $peerId, $me, $name, $isHost, $micOn, $camOn, $screenOn]);

        if ($role === 'student') {
            db()->prepare('UPDATE session_participants SET joined_at=COALESCE(joined_at,NOW()), is_present=1 WHERE session_id=? AND student_id=?')
                ->execute([$roomId, $me]);
        }

        echo json_encode(['ok' => true, 'my_id' => $peerId, 'name' => $name]);
        exit;
    }

    case 'poll': {
        if ($myId === '') {
            echo json_encode(['ok' => false, 'error' => 'no_my_id']);
            exit;
        }

        if (!p2p_peer_owned($myId, $roomId, $me)) {
            echo json_encode(['ok' => false, 'error' => 'peer_not_found']);
            exit;
        }

        // به‌روزرسانی last_poll
        db()->prepare('UPDATE session_peers SET last_poll=NOW() WHERE room_id=? AND peer_id=? AND user_id=?')
            ->execute([$roomId, $myId, $me]);

        // حذف peers غیرفعال (>30 ثانیه بدون poll)
        db()->prepare('DELETE FROM session_peers WHERE room_id=? AND last_poll < DATE_SUB(NOW(), INTERVAL 30 SECOND)')
            ->execute([$roomId]);

        // دریافت لیست peers (به‌جز خودم)
        $peersStmt = db()->prepare('SELECT peer_id, user_id, user_name, is_host, mic_on, cam_on, screen_on FROM session_peers
            WHERE room_id=? AND peer_id != ? AND last_poll > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            ORDER BY created_at ASC');
        $peersStmt->execute([$roomId, $myId]);
        $peers = $peersStmt->fetchAll();

        // دریافت پیام‌های signaling برای من
        $msgStmt = db()->prepare('SELECT id, from_peer, signal_json, created_at FROM session_signals
            WHERE room_id=? AND to_peer=? AND delivered_at IS NULL
            ORDER BY id ASC LIMIT 50');
        $msgStmt->execute([$roomId, $myId]);
        $rawMessages = $msgStmt->fetchAll();

        $messages = [];
        $deliveredIds = [];
        foreach ($rawMessages as $m) {
            $decoded = json_decode($m['signal_json'], true);
            $messages[] = [
                'from_peer_id' => $m['from_peer'],
                'signal' => $decoded,
                'created_at' => $m['created_at'],
            ];
            $deliveredIds[] = $m['id'];
        }

        // علامت‌گذاری به‌عنوان تحویل‌شده
        if ($deliveredIds) {
            $placeholders = implode(',', array_fill(0, count($deliveredIds), '?'));
            db()->prepare("UPDATE session_signals SET delivered_at=NOW() WHERE id IN ($placeholders)")
                ->execute($deliveredIds);
        }

        $cmdStmt = db()->prepare('SELECT id, command, from_user_id FROM session_peer_commands WHERE room_id=? AND target_user_id=? AND delivered_at IS NULL ORDER BY id ASC LIMIT 20');
        $cmdStmt->execute([$roomId, $me]);
        $commands = $cmdStmt->fetchAll();
        if ($commands) {
            db()->prepare('UPDATE session_peer_commands SET delivered_at=NOW() WHERE room_id=? AND target_user_id=? AND delivered_at IS NULL')
                ->execute([$roomId, $me]);
        }

        echo json_encode([
            'ok' => true,
            'peers' => array_map(fn($p) => [
                'peer_id' => $p['peer_id'],
                'user_id' => (int)$p['user_id'],
                'name' => $p['user_name'],
                'is_host' => (int)$p['is_host'],
                'mic_on' => (int)($p['mic_on'] ?? 0),
                'cam_on' => (int)($p['cam_on'] ?? 0),
                'screen_on' => (int)($p['screen_on'] ?? 0),
            ], $peers),
            'messages' => $messages,
            'commands' => $commands ?: [],
        ]);
        exit;
    }

    case 'state': {
        if ($myId === '') { echo json_encode(['ok'=>false,'error'=>'no_my_id']); exit; }
        if (!p2p_peer_owned($myId, $roomId, $me)) { echo json_encode(['ok'=>false,'error'=>'peer_not_found']); exit; }
        $media = p2p_effective_media($session, $me, $role, $body);
        $mic = $media['mic'];
        $cam = $media['cam'];
        $screen = $media['screen'];
        db()->prepare('UPDATE session_peers SET mic_on=?, cam_on=?, screen_on=?, last_poll=NOW() WHERE room_id=? AND peer_id=? AND user_id=?')
            ->execute([$mic,$cam,$screen,$roomId,$myId,$me]);
        echo json_encode(['ok'=>true]); exit;
    }

    case 'command': {
        if ((int)($session['advisor_id'] ?? 0) !== $me && $role !== 'admin') { echo json_encode(['ok'=>false,'error'=>'no_access']); exit; }
        $target = (int)($body['target_user_id'] ?? 0);
        $cmd = (string)($body['command'] ?? '');
        $allowed = ['mic_off','cam_off','screen_off','kick'];
        if (!$target || !in_array($cmd, $allowed, true)) { echo json_encode(['ok'=>false,'error'=>'bad_command']); exit; }
        if (!online_session_student_can_access($roomId, $target)) { echo json_encode(['ok'=>false,'error'=>'target_not_in_session']); exit; }
        db()->prepare('INSERT INTO session_peer_commands (room_id,target_user_id,command,from_user_id) VALUES (?,?,?,?)')
            ->execute([$roomId,$target,$cmd,$me]);
        echo json_encode(['ok'=>true]); exit;
    }

    case 'signal': {
        $toPeer = (string)($body['to_peer_id'] ?? '');
        if ($myId === '' || $toPeer === '') {
            echo json_encode(['ok' => false, 'error' => 'missing_params']);
            exit;
        }

        if (!p2p_peer_owned($myId, $roomId, $me)) { echo json_encode(['ok'=>false,'error'=>'peer_not_found']); exit; }
        $toChk = db()->prepare('SELECT 1 FROM session_peers WHERE room_id=? AND peer_id=? LIMIT 1');
        $toChk->execute([$roomId, $toPeer]);
        if (!$toChk->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'target_peer_not_found']); exit; }

        $signal = $body['signal'] ?? null;
        if (!$signal) {
            echo json_encode(['ok' => false, 'error' => 'no_signal']);
            exit;
        }

        db()->prepare('INSERT INTO session_signals (room_id, from_peer, to_peer, signal_json) VALUES (?, ?, ?, ?)')
            ->execute([$roomId, (string)$myId, $toPeer, json_encode($signal, JSON_UNESCAPED_UNICODE)]);

        echo json_encode(['ok' => true]);
        exit;
    }

    case 'leave': {
        if ($myId === '') {
            echo json_encode(['ok' => false, 'error' => 'no_my_id']);
            exit;
        }
        if (!p2p_peer_owned($myId, $roomId, $me)) { echo json_encode(['ok'=>true, 'already_left'=>true]); exit; }

        db()->prepare('DELETE FROM session_peers WHERE room_id=? AND peer_id=?')
            ->execute([$roomId, (string)$myId]);
        if ($role === 'student') {
            db()->prepare('UPDATE session_participants SET left_at=NOW(), duration_seconds=TIMESTAMPDIFF(SECOND, joined_at, NOW()) WHERE session_id=? AND student_id=? AND joined_at IS NOT NULL')
                ->execute([$roomId, $me]);
        }
        db()->prepare('DELETE FROM session_signals WHERE room_id=? AND (from_peer=? OR to_peer=?)')
            ->execute([$roomId, (string)$myId, (string)$myId]);

        echo json_encode(['ok' => true]);
        exit;
    }

    default:
        echo json_encode(['ok' => false, 'error' => 'invalid_action']);
        exit;
    }
} catch (Throwable $e) {
    error_log('P2P API error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'server_error: ' . $e->getMessage()]);
    exit;
}
