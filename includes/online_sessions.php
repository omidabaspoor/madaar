<?php
/** هسته سیستم جلسات آنلاین مَدار (Online Sessions) - نسخه‌ی ۲ با Error Handling */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/helpers.php';

/**
 * ساخت جداول جلسات آنلاین - هر جدول مستقل
 * اگر یکی fail بشه، بقیه همچنان امتحان می‌شوند
 */
function online_sessions_schema_ready(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;

    $tables = [
        'online_sessions' => "CREATE TABLE IF NOT EXISTS online_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            advisor_id INT UNSIGNED NOT NULL,
            title VARCHAR(180) NOT NULL,
            description TEXT,
            scheduled_at DATETIME,
            duration_min INT UNSIGNED DEFAULT 60,
            max_participants INT UNSIGNED DEFAULT 6,
            jitsi_room_name VARCHAR(80) NOT NULL,
            jitsi_password VARCHAR(40),
            allow_student_mic TINYINT(1) DEFAULT 1,
            allow_student_cam TINYINT(1) DEFAULT 1,
            allow_screen_share TINYINT(1) DEFAULT 1,
            allow_whiteboard TINYINT(1) DEFAULT 1,
            allow_chat TINYINT(1) DEFAULT 1,
            status ENUM('draft','scheduled','live','ended','cancelled') NOT NULL DEFAULT 'draft',
            started_at DATETIME,
            ended_at DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_jitsi_room (jitsi_room_name),
            KEY idx_online_advisor (advisor_id, status, scheduled_at),
            KEY idx_online_status_sched (status, scheduled_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'session_participants' => "CREATE TABLE IF NOT EXISTS session_participants (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            student_id INT UNSIGNED NOT NULL,
            joined_at DATETIME,
            left_at DATETIME,
            duration_seconds INT UNSIGNED,
            is_present TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uq_participant (session_id, student_id),
            KEY idx_part_session (session_id),
            KEY idx_part_student (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'whiteboard_snapshots' => "CREATE TABLE IF NOT EXISTS whiteboard_snapshots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            version INT UNSIGNED DEFAULT 1,
            saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wb_session (session_id, saved_at),
            KEY idx_wb_version (session_id, version DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'session_chat_messages' => "CREATE TABLE IF NOT EXISTS session_chat_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            user_name VARCHAR(120) NOT NULL,
            user_role VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            message_type ENUM('text','emoji','system','file') DEFAULT 'text',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_chat_session (session_id, created_at),
            KEY idx_chat_user (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'session_reactions' => "CREATE TABLE IF NOT EXISTS session_reactions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            user_name VARCHAR(120) NOT NULL,
            reaction_type VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_react_session (session_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'session_hand_raises' => "CREATE TABLE IF NOT EXISTS session_hand_raises (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            user_name VARCHAR(120) NOT NULL,
            user_role VARCHAR(20) NOT NULL,
            raised_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            acknowledged_at DATETIME,
            PRIMARY KEY (id),
            KEY idx_hand_session_raised (session_id, raised_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    $failed = [];
    foreach ($tables as $name => $sql) {
        try {
            db()->exec($sql);
        } catch (Throwable $e) {
            $failed[] = $name . ': ' . $e->getMessage();
            error_log("Online sessions schema - $name failed: " . $e->getMessage());
        }
    }

    if (!empty($failed)) {
        error_log('Online sessions schema failed: ' . implode('; ', $failed));
        return $ok = false;
    }

    // بررسی نهایی: همه جداول واقعاً ساخته شدن؟
    try {
        foreach (array_keys($tables) as $name) {
            $r = db()->query("SHOW TABLES LIKE " . db()->quote($name))->fetch();
            if (!$r) {
                error_log("Online sessions schema: Table $name not found after creation");
                return $ok = false;
            }
        }
    } catch (Throwable $e) {
        error_log('Online sessions schema verification failed: ' . $e->getMessage());
        return $ok = false;
    }

    return $ok = true;
}

/**
 * Wrapper امن برای query - خطا رو خفه می‌کنه و null برمی‌گردونه
 */
function online_query(string $sql, array $params = [], string $fetchMode = 'fetch'): mixed {
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $fetchMode === 'fetchAll' ? $stmt->fetchAll() : ($fetchMode === 'fetchColumn' ? $stmt->fetchColumn() : $stmt->fetch());
    } catch (Throwable $e) {
        error_log('Online query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return null;
    }
}

/* ===================================================================
   توابع اصلی - همه با try-catch
   =================================================================== */

/**
 * ساخت یک جلسه‌ی آنلاین جدید
 */
function online_session_create(int $advisorId, string $title, ?string $description, ?string $scheduledAt, int $duration, int $maxParticipants, array $studentIds, array $permissions): int {
    if (!online_sessions_schema_ready()) return 0;

    $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
    $jitsiRoom = 'madar-' . substr(bin2hex(random_bytes(4)), 0, 8);

    try {
        db()->prepare('INSERT INTO online_sessions
            (advisor_id, title, description, scheduled_at, duration_min, max_participants, jitsi_room_name, jitsi_password, allow_student_mic, allow_student_cam, allow_screen_share, allow_whiteboard, allow_chat, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "scheduled")')
            ->execute([
                $advisorId, $title, $description ?: null, $scheduledAt ?: null,
                max(15, min(480, $duration)),
                max(2, min(20, $maxParticipants)),
                $jitsiRoom, null,
                (int)($permissions['mic'] ?? 1),
                (int)($permissions['cam'] ?? 1),
                (int)($permissions['screen'] ?? 1),
                (int)($permissions['whiteboard'] ?? 1),
                (int)($permissions['chat'] ?? 1),
            ]);
        $sessionId = (int)db()->lastInsertId();
    } catch (Throwable $e) {
        error_log('online_session_create failed: ' . $e->getMessage());
        return 0;
    }

    if ($sessionId && $studentIds) {
        try {
            $ins = db()->prepare('INSERT IGNORE INTO session_participants (session_id, student_id) VALUES (?, ?)');
            foreach ($studentIds as $sid) {
                $ins->execute([$sessionId, $sid]);
                // اعلان به دانش‌آموز
                $timeText = $scheduledAt ? ' در ' . jalali_date(date('Y-m-d', strtotime($scheduledAt))) . ' ساعت ' . fa_num(date('H:i', strtotime($scheduledAt))) : '';
                notify($sid, '🎥 جلسه آنلاین جدید', 'جلسه آنلاین «' . $title . '» توسط مشاور شما برنامه‌ریزی شد' . $timeText . '. برای ورود به پنل مَدار مراجعه کنید.', 'video', 'student/online_sessions.php');
            }
        } catch (Throwable $e) {
            error_log('Participants insert failed: ' . $e->getMessage());
        }
    }

    return $sessionId;
}

function online_session_get(int $sessionId): ?array {
    if ($sessionId <= 0 || !online_sessions_schema_ready()) return null;
    $result = online_query(
        'SELECT s.*, u.full_name advisor_name FROM online_sessions s JOIN users u ON u.id=s.advisor_id WHERE s.id=?',
        [$sessionId],
        'fetch'
    );
    return $result ?: null;
}

function online_sessions_for_advisor(int $advisorId, ?string $status = null): array {
    if (!online_sessions_schema_ready()) return [];
    $sql = 'SELECT s.*, (SELECT COUNT(*) FROM session_participants WHERE session_id=s.id) AS participant_count
            FROM online_sessions s WHERE s.advisor_id=?';
    $params = [$advisorId];
    if ($status) {
        $sql .= ' AND s.status=?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY s.scheduled_at DESC, s.created_at DESC';
    $result = online_query($sql, $params, 'fetchAll');
    return $result ?: [];
}

function online_sessions_for_student(int $studentId): array {
    if (!online_sessions_schema_ready()) return [];
    $result = online_query(
        'SELECT s.*, u.full_name advisor_name, p.is_present, p.joined_at AS participant_joined_at
        FROM online_sessions s
        JOIN session_participants p ON p.session_id=s.id AND p.student_id=?
        JOIN users u ON u.id=s.advisor_id
        ORDER BY s.scheduled_at DESC, s.created_at DESC',
        [$studentId],
        'fetchAll'
    );
    return $result ?: [];
}

function online_session_student_can_access(int $sessionId, int $studentId): bool {
    if ($sessionId <= 0 || $studentId <= 0 || !online_sessions_schema_ready()) return false;
    $result = online_query(
        'SELECT 1 FROM session_participants WHERE session_id=? AND student_id=?',
        [$sessionId, $studentId],
        'fetchColumn'
    );
    return (bool)$result;
}

function online_session_delete(int $sessionId, int $advisorId): bool {
    if (!online_sessions_schema_ready()) return false;
    try {
        $stmt = db()->prepare('DELETE FROM online_sessions WHERE id=? AND advisor_id=?');
        $stmt->execute([$sessionId, $advisorId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('online_session_delete failed: ' . $e->getMessage());
        return false;
    }
}

function online_session_update(int $sessionId, int $advisorId, array $data): bool {
    if (!online_sessions_schema_ready()) return false;
    $fields = [];
    $params = [];
    foreach (['title','description','scheduled_at','duration_min','max_participants'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f=?";
            $params[] = $data[$f];
        }
    }
    foreach (['allow_student_mic','allow_student_cam','allow_screen_share','allow_whiteboard','allow_chat'] as $f) {
        if (isset($data[$f])) {
            $fields[] = "$f=?";
            $params[] = (int)$data[$f];
        }
    }
    if (!$fields) return false;
    $params[] = $sessionId;
    $params[] = $advisorId;
    try {
        $stmt = db()->prepare('UPDATE online_sessions SET ' . implode(',', $fields) . ' WHERE id=? AND advisor_id=?');
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('online_session_update failed: ' . $e->getMessage());
        return false;
    }
}

function online_session_start(int $sessionId, int $advisorId): bool {
    if (!online_sessions_schema_ready()) return false;
    try {
        $stmt = db()->prepare('UPDATE online_sessions SET status="live", started_at=NOW() WHERE id=? AND advisor_id=?');
        $stmt->execute([$sessionId, $advisorId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('online_session_start failed: ' . $e->getMessage());
        return false;
    }
}

function online_session_end(int $sessionId, int $advisorId): bool {
    if (!online_sessions_schema_ready()) return false;
    try {
        $stmt = db()->prepare('UPDATE online_sessions SET status="ended", ended_at=NOW() WHERE id=? AND advisor_id=?');
        $stmt->execute([$sessionId, $advisorId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('online_session_end failed: ' . $e->getMessage());
        return false;
    }
}

function online_session_cancel(int $sessionId, int $advisorId): bool {
    if (!online_sessions_schema_ready()) return false;
    try {
        db()->prepare('UPDATE online_sessions SET status="cancelled" WHERE id=? AND advisor_id=?')
            ->execute([$sessionId, $advisorId]);

        // اطلاع به همه
        $stmt = db()->prepare('SELECT student_id FROM session_participants WHERE session_id=?');
        $stmt->execute([$sessionId]);
        foreach ($stmt->fetchAll() as $p) {
            notify((int)$p['student_id'], '❌ جلسه آنلاین لغو شد', 'جلسه آنلاین توسط مشاور لغو گردید.', 'video', 'student/online_sessions.php');
        }
        return true;
    } catch (Throwable $e) {
        error_log('online_session_cancel failed: ' . $e->getMessage());
        return false;
    }
}

function online_session_participants(int $sessionId): array {
    if (!online_sessions_schema_ready()) return [];
    $result = online_query(
        'SELECT p.*, u.full_name, u.username, u.phone, u.avatar FROM session_participants p JOIN users u ON u.id=p.student_id WHERE p.session_id=? ORDER BY u.full_name',
        [$sessionId],
        'fetchAll'
    );
    return $result ?: [];
}

function online_session_update_participants(int $sessionId, int $advisorId, array $studentIds): bool {
    if (!online_sessions_schema_ready()) return false;
    $s = online_session_get($sessionId);
    if (!$s || (int)$s['advisor_id'] !== $advisorId) return false;

    try {
        db()->prepare('DELETE FROM session_participants WHERE session_id=?')->execute([$sessionId]);
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($studentIds) {
            $ins = db()->prepare('INSERT INTO session_participants (session_id, student_id) VALUES (?, ?)');
            foreach ($studentIds as $sid) $ins->execute([$sessionId, $sid]);
        }
        return true;
    } catch (Throwable $e) {
        error_log('online_session_update_participants failed: ' . $e->getMessage());
        return false;
    }
}

/* ===================================================================
   Whiteboard
   =================================================================== */

function whiteboard_save(int $sessionId, int $userId, string $json): int {
    if (!online_sessions_schema_ready()) return 0;
    try {
        $stmt = db()->prepare('SELECT COALESCE(MAX(version),0)+1 FROM whiteboard_snapshots WHERE session_id=?');
        $stmt->execute([$sessionId]);
        $version = (int)$stmt->fetchColumn();
        db()->prepare('INSERT INTO whiteboard_snapshots (session_id, user_id, snapshot_json, version) VALUES (?, ?, ?, ?)')
            ->execute([$sessionId, $userId, $json, $version]);
        return $version;
    } catch (Throwable $e) {
        error_log('whiteboard_save failed: ' . $e->getMessage());
        return 0;
    }
}

function whiteboard_load_latest(int $sessionId): ?array {
    if (!online_sessions_schema_ready()) return null;
    $result = online_query(
        'SELECT * FROM whiteboard_snapshots WHERE session_id=? ORDER BY version DESC LIMIT 1',
        [$sessionId],
        'fetch'
    );
    return $result ?: null;
}

/* ===================================================================
   Chat
   =================================================================== */

function session_chat_send(int $sessionId, int $userId, string $userName, string $userRole, string $message, string $type = 'text'): int {
    if (!online_sessions_schema_ready()) return 0;
    try {
        db()->prepare('INSERT INTO session_chat_messages (session_id, user_id, user_name, user_role, message, message_type) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$sessionId, $userId, $userName, $userRole, $message, $type]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) {
        error_log('session_chat_send failed: ' . $e->getMessage());
        return 0;
    }
}

function session_chat_list(int $sessionId, ?int $afterId = null, int $limit = 50): array {
    if (!online_sessions_schema_ready()) return [];
    if ($afterId) {
        $result = online_query(
            'SELECT * FROM session_chat_messages WHERE session_id=? AND id>? ORDER BY id ASC LIMIT ?',
            [$sessionId, $afterId, $limit],
            'fetchAll'
        );
    } else {
        $result = online_query(
            'SELECT * FROM session_chat_messages WHERE session_id=? ORDER BY id DESC LIMIT ?',
            [$sessionId, $limit],
            'fetchAll'
        );
        return array_reverse($result ?: []);
    }
    return $result ?: [];
}

/* ===================================================================
   Hand Raise & Reactions
   =================================================================== */

function session_hand_toggle(int $sessionId, int $userId, string $userName, string $userRole): bool {
    if (!online_sessions_schema_ready()) return false;
    try {
        $stmt = db()->prepare('SELECT id, acknowledged_at FROM session_hand_raises WHERE session_id=? AND user_id=? AND acknowledged_at IS NULL');
        $stmt->execute([$sessionId, $userId]);
        $existing = $stmt->fetch();

        if ($existing) {
            db()->prepare('DELETE FROM session_hand_raises WHERE id=?')->execute([$existing['id']]);
            return false;
        } else {
            db()->prepare('INSERT INTO session_hand_raises (session_id, user_id, user_name, user_role) VALUES (?, ?, ?, ?)')
                ->execute([$sessionId, $userId, $userName, $userRole]);
            return true;
        }
    } catch (Throwable $e) {
        error_log('session_hand_toggle failed: ' . $e->getMessage());
        return false;
    }
}

function session_hand_raised_list(int $sessionId): array {
    if (!online_sessions_schema_ready()) return [];
    $result = online_query(
        'SELECT * FROM session_hand_raises WHERE session_id=? AND acknowledged_at IS NULL ORDER BY raised_at ASC',
        [$sessionId],
        'fetchAll'
    );
    return $result ?: [];
}

function session_hand_acknowledge(int $sessionId, int $userId): bool {
    if (!online_sessions_schema_ready()) return false;
    try {
        $stmt = db()->prepare('UPDATE session_hand_raises SET acknowledged_at=NOW() WHERE session_id=? AND user_id=? AND acknowledged_at IS NULL');
        $stmt->execute([$sessionId, $userId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('session_hand_acknowledge failed: ' . $e->getMessage());
        return false;
    }
}

function session_reaction_send(int $sessionId, int $userId, string $userName, string $type): int {
    if (!online_sessions_schema_ready()) return 0;
    $allowed = ['clap','heart','thumbs','fire','star','laugh','wow','sad'];
    if (!in_array($type, $allowed, true)) $type = 'clap';
    try {
        db()->prepare('INSERT INTO session_reactions (session_id, user_id, user_name, reaction_type) VALUES (?, ?, ?, ?)')
            ->execute([$sessionId, $userId, $userName, $type]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) {
        error_log('session_reaction_send failed: ' . $e->getMessage());
        return 0;
    }
}

function session_reactions_recent(int $sessionId, int $sinceMinutes = 5): array {
    if (!online_sessions_schema_ready()) return [];
    $result = online_query(
        'SELECT * FROM session_reactions WHERE session_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) ORDER BY created_at DESC LIMIT 20',
        [$sessionId, $sinceMinutes],
        'fetchAll'
    );
    return $result ?: [];
}
