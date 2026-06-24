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

        db()->prepare('INSERT INTO session_peers (room_id, peer_id, user_id, user_name, is_host, last_poll) VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_poll=NOW(), user_name=VALUES(user_name), is_host=VALUES(is_host)')
            ->execute([$roomId, $peerId, $me, $name, $isHost]);

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

        // به‌روزرسانی last_poll
        db()->prepare('UPDATE session_peers SET last_poll=NOW() WHERE room_id=? AND peer_id=?')
            ->execute([$roomId, $myId]);

        // حذف peers غیرفعال (>30 ثانیه بدون poll)
        db()->prepare('DELETE FROM session_peers WHERE room_id=? AND last_poll < DATE_SUB(NOW(), INTERVAL 30 SECOND)')
            ->execute([$roomId]);

        // دریافت لیست peers (به‌جز خودم)
        $peersStmt = db()->prepare('SELECT peer_id, user_name, is_host FROM session_peers
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

        echo json_encode([
            'ok' => true,
            'peers' => array_map(fn($p) => [
                'peer_id' => $p['peer_id'],
                'name' => $p['user_name'],
                'is_host' => (int)$p['is_host'],
            ], $peers),
            'messages' => $messages,
        ]);
        exit;
    }

    case 'signal': {
        $toPeer = (string)($body['to_peer_id'] ?? '');
        if ($myId === '' || $toPeer === '') {
            echo json_encode(['ok' => false, 'error' => 'missing_params']);
            exit;
        }

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
