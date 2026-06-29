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
            allow_student_mic TINYINT(1) DEFAULT 0,
            allow_student_cam TINYINT(1) DEFAULT 0,
            allow_screen_share TINYINT(1) DEFAULT 0,
            allow_whiteboard TINYINT(1) DEFAULT 0,
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

        'session_permission_requests' => "CREATE TABLE IF NOT EXISTS session_permission_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            user_name VARCHAR(120) NOT NULL,
            permission_type VARCHAR(20) NOT NULL,
            status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME NULL,
            decided_by INT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY idx_perm_session (session_id, status, created_at),
            KEY idx_perm_user (session_id, user_id, permission_type, created_at)
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
        error_log('Online sessions schema warnings: ' . implode('; ', $failed));
    }

    // Online sessions schema self-healing: ستون‌های نصب‌های قبلی را اضافه/اصلاح می‌کند.
    try {
        $cols = [];
        foreach (db()->query("SHOW COLUMNS FROM online_sessions")->fetchAll() as $c) { $cols[$c['Field']] = true; }
        $defs = [
            'description' => "TEXT NULL AFTER title",
            'scheduled_at' => "DATETIME NULL AFTER description",
            'duration_min' => "INT UNSIGNED DEFAULT 60 AFTER scheduled_at",
            'max_participants' => "INT UNSIGNED DEFAULT 6 AFTER duration_min",
            'jitsi_password' => "VARCHAR(40) NULL AFTER jitsi_room_name",
            'allow_student_mic' => "TINYINT(1) DEFAULT 0 AFTER jitsi_password",
            'allow_student_cam' => "TINYINT(1) DEFAULT 0 AFTER allow_student_mic",
            'allow_screen_share' => "TINYINT(1) DEFAULT 0 AFTER allow_student_cam",
            'allow_whiteboard' => "TINYINT(1) DEFAULT 0 AFTER allow_screen_share",
            'allow_chat' => "TINYINT(1) DEFAULT 1 AFTER allow_whiteboard",
            'pinned_user_id' => "INT UNSIGNED DEFAULT 0 AFTER allow_chat",
            'started_at' => "DATETIME NULL AFTER status",
            'ended_at' => "DATETIME NULL AFTER started_at",
        ];
        foreach ($defs as $col => $def) {
            if (empty($cols[$col])) {
                try { db()->exec("ALTER TABLE online_sessions ADD COLUMN $col $def"); } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) { error_log('Online sessions schema self-healing failed: ' . $e->getMessage()); }

    // Self-healing for child tables too (old/beta installs on XAMPP/cPanel).
    try {
        $chatCols = [];
        foreach (db()->query("SHOW COLUMNS FROM session_chat_messages")->fetchAll() as $c) { $chatCols[$c['Field']] = true; }
        $chatDefs = [
            'user_name' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER user_id",
            'user_role' => "VARCHAR(20) NOT NULL DEFAULT 'student' AFTER user_name",
            'message' => "TEXT NOT NULL AFTER user_role",
            'message_type' => "ENUM('text','emoji','system','file') DEFAULT 'text' AFTER message",
            'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER message_type",
        ];
        foreach ($chatDefs as $col => $def) if (empty($chatCols[$col])) { try { db()->exec("ALTER TABLE session_chat_messages ADD COLUMN $col $def"); } catch (Throwable $e) {} }
        try {
            $mt = db()->query("SHOW COLUMNS FROM session_chat_messages LIKE 'message_type'")->fetch();
            if ($mt && strpos((string)($mt['Type'] ?? ''), "'system'") === false) {
                db()->exec("ALTER TABLE session_chat_messages MODIFY message_type ENUM('text','emoji','system','file') DEFAULT 'text'");
            }
        } catch (Throwable $e) {}
    } catch (Throwable $e) { error_log('Online chat schema self-healing failed: '.$e->getMessage()); }
    try {
        $wbCols = [];
        foreach (db()->query("SHOW COLUMNS FROM whiteboard_snapshots")->fetchAll() as $c) { $wbCols[$c['Field']] = true; }
        if (empty($wbCols['version'])) { try { db()->exec("ALTER TABLE whiteboard_snapshots ADD COLUMN version INT UNSIGNED DEFAULT 1 AFTER snapshot_json"); } catch (Throwable $e) {} }
        if (empty($wbCols['saved_at'])) { try { db()->exec("ALTER TABLE whiteboard_snapshots ADD COLUMN saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"); } catch (Throwable $e) {} }
    } catch (Throwable $e) { error_log('Online whiteboard schema self-healing failed: '.$e->getMessage()); }

    // بررسی نهایی: همه جداول واقعاً ساخته شدن؟
    try {
        foreach (array_keys($tables) as $name) {
            db()->query("SHOW TABLES LIKE " . db()->quote($name))->fetch();
        }
    } catch (Throwable $e) {}

    return $ok = true;
}

/**
 * Wrapper امن برای query - خطا رو خفه می‌کنه و null برمی‌گردونه
 */
function online_query(string $sql, array $params = [], string $fetchMode = 'fetch'): mixed {
    try {
        $stmt = db()->prepare($sql);
        foreach (array_values($params) as $i => $val) {
            $type = is_int($val) ? PDO::PARAM_INT : (is_bool($val) ? PDO::PARAM_BOOL : (is_null($val) ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $stmt->bindValue($i + 1, $val, $type);
        }
        $stmt->execute();
        return $fetchMode === 'fetchAll' ? $stmt->fetchAll() : ($fetchMode === 'fetchColumn' ? $stmt->fetchColumn() : $stmt->fetch());
    } catch (Throwable $e) {
        error_log('Online query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
        return null;
    }
}

function ensure_online_sessions_pinned_col(): void {
    static $done = false;
    if ($done) return;
    try {
        $cols = [];
        foreach (db()->query("SHOW COLUMNS FROM online_sessions")->fetchAll() as $c) { $cols[$c['Field']] = true; }
        if (empty($cols['pinned_user_id'])) {
            db()->exec("ALTER TABLE online_sessions ADD COLUMN pinned_user_id INT UNSIGNED DEFAULT 0");
        }
        $done = true;
    } catch (Throwable $e) {}
}

function online_session_pin_stage(int $sessionId, int $userId): bool {
    ensure_online_sessions_pinned_col();
    try {
        $st = db()->prepare('UPDATE online_sessions SET pinned_user_id=? WHERE id=?');
        return $st->execute([$userId, $sessionId]);
    } catch (Throwable $e) { return false; }
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
    ensure_online_sessions_pinned_col();
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
    $s = online_session_get($sessionId);
    if (!$s || ((int)$s['advisor_id'] !== $advisorId && (current_user()['role'] ?? '') !== 'admin')) return false;
    try {
        // در بعضی نصب‌ها FK وجود ندارد؛ بنابراین پاک‌سازی وابسته‌ها دستی انجام می‌شود.
        foreach (['session_participants','whiteboard_snapshots','session_chat_messages','session_reactions','session_hand_raises','session_permission_requests','session_peers','session_signals','session_peer_commands'] as $tbl) {
            try {
                if ($tbl === 'session_peers') db()->prepare('DELETE FROM session_peers WHERE room_id=?')->execute([$sessionId]);
                elseif ($tbl === 'session_signals') db()->prepare('DELETE FROM session_signals WHERE room_id=?')->execute([$sessionId]);
                elseif ($tbl === 'session_peer_commands') db()->prepare('DELETE FROM session_peer_commands WHERE room_id=?')->execute([$sessionId]);
                else db()->prepare("DELETE FROM `$tbl` WHERE session_id=?")->execute([$sessionId]);
            } catch (Throwable $e) {}
        }
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
    $session = online_session_get($sessionId);
    if (!$session || ((int)$session['advisor_id'] !== $advisorId && (current_user()['role'] ?? '') !== 'admin')) return false;
    if (($session['status'] ?? '') === 'live') return true;
    if (in_array(($session['status'] ?? ''), ['ended','cancelled'], true)) return false;
    try {
        $stmt = db()->prepare('UPDATE online_sessions SET status="live", started_at=COALESCE(started_at,NOW()), ended_at=NULL WHERE id=? AND advisor_id=? AND status IN ("draft","scheduled")');
        $stmt->execute([$sessionId, (int)$session['advisor_id']]);
        $changed = $stmt->rowCount() > 0;
        if ($changed) {
            session_chat_send($sessionId, $advisorId, (string)($session['advisor_name'] ?? 'مشاور'), 'system', 'کلاس شروع شد ✅', 'system');
            foreach (online_session_participants($sessionId) as $p) {
                notify((int)$p['student_id'], 'کلاس آنلاین شروع شد 🔴', 'کلاس «' . $session['title'] . '» اکنون فعال است.', 'video', 'online_room.php?session=' . $sessionId);
            }
        }
        return $changed || (($session['status'] ?? '') === 'live');
    } catch (Throwable $e) {
        error_log('online_session_start failed: ' . $e->getMessage());
        return false;
    }
}

function online_session_end(int $sessionId, int $advisorId): bool {
    if (!online_sessions_schema_ready()) return false;
    $session = online_session_get($sessionId);
    if (!$session || ((int)$session['advisor_id'] !== $advisorId && (current_user()['role'] ?? '') !== 'admin')) return false;
    if (($session['status'] ?? '') === 'ended') return true;
    try {
        $stmt = db()->prepare('UPDATE online_sessions SET status="ended", ended_at=NOW() WHERE id=? AND advisor_id=? AND status<>"cancelled"');
        $stmt->execute([$sessionId, (int)$session['advisor_id']]);
        try { session_chat_send($sessionId, $advisorId, (string)($session['advisor_name'] ?? 'مشاور'), 'system', 'کلاس پایان یافت.', 'system'); } catch (Throwable $e) {}
        try { db()->prepare('DELETE FROM session_peers WHERE room_id=?')->execute([$sessionId]); } catch (Throwable $e) {}
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

function ensure_chat_table_columns(): void {
    static $done = false;
    if ($done) return;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS session_chat_messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, session_id INT, user_id INT, user_name VARCHAR(120) DEFAULT '', user_role VARCHAR(20) DEFAULT 'student', message TEXT, message_type VARCHAR(20) DEFAULT 'text', created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
        $cols = [];
        foreach (db()->query("SHOW COLUMNS FROM session_chat_messages")->fetchAll() as $c) { $cols[$c['Field']] = true; }
        if (empty($cols['user_name'])) db()->exec("ALTER TABLE session_chat_messages ADD COLUMN user_name VARCHAR(120) DEFAULT ''");
        if (empty($cols['user_role'])) db()->exec("ALTER TABLE session_chat_messages ADD COLUMN user_role VARCHAR(20) DEFAULT 'student'");
        if (empty($cols['message_type'])) db()->exec("ALTER TABLE session_chat_messages ADD COLUMN message_type VARCHAR(20) DEFAULT 'text'");
        $done = true;
    } catch (Throwable $e) {}
}

function session_chat_send(int $sessionId, int $userId, string $userName, string $userRole, string $message, string $type = 'text'): int {
    ensure_chat_table_columns();
    try {
        $stmt = db()->prepare('INSERT INTO session_chat_messages (session_id, user_id, user_name, user_role, message, message_type, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$sessionId, $userId, $userName, $userRole, $message, $type]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) {
        try {
            $stmt = db()->prepare('INSERT INTO session_chat_messages (session_id, user_id, user_name, user_role, message) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$sessionId, $userId, $userName, $userRole, $message]);
            return (int)db()->lastInsertId();
        } catch (Throwable $e2) {
            return 0;
        }
    }
}

function session_chat_list(int $sessionId, ?int $afterId = null, int $limit = 50): array {
    ensure_chat_table_columns();
    try {
        if ($afterId && $afterId > 0) {
            $st = db()->prepare('SELECT * FROM session_chat_messages WHERE session_id=? AND id>? ORDER BY id ASC LIMIT 100');
            $st->execute([$sessionId, $afterId]);
            return $st->fetchAll() ?: [];
        } else {
            $st = db()->prepare('SELECT * FROM session_chat_messages WHERE session_id=? ORDER BY id DESC LIMIT 50');
            $st->execute([$sessionId]);
            $rows = $st->fetchAll() ?: [];
            return array_reverse($rows);
        }
    } catch (Throwable $e) {
        return [];
    }
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


/* ===================================================================
   Permission requests (student asks host for mic/cam/screen/whiteboard)
   =================================================================== */

function online_room_permission_schema(): void {
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS session_permission_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            user_name VARCHAR(120) NOT NULL,
            permission_type VARCHAR(20) NOT NULL,
            status ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            decided_at DATETIME NULL,
            decided_by INT UNSIGNED NULL,
            PRIMARY KEY (id),
            KEY idx_perm_session (session_id, status, created_at),
            KEY idx_perm_user (session_id, user_id, permission_type, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) { error_log('permission schema failed: '.$e->getMessage()); }
}

function online_session_is_host(array $session, int $userId, string $role): bool {
    return $role === 'admin' || ($role === 'advisor' && (int)($session['advisor_id'] ?? 0) === $userId);
}

function online_permission_latest_decisions(int $sessionId, int $userId): array {
    online_room_permission_schema();
    $out = [];
    try {
        $st = db()->prepare('SELECT permission_type,status,id FROM session_permission_requests WHERE session_id=? AND user_id=? AND status<>"pending" ORDER BY id DESC');
        $st->execute([$sessionId, $userId]);
        foreach ($st->fetchAll() as $r) {
            $type = (string)$r['permission_type'];
            if (!isset($out[$type])) $out[$type] = (string)$r['status'];
        }
    } catch (Throwable $e) {}
    return $out;
}

function online_permission_effective_state(array $session, int $userId, string $role): array {
    $host = online_session_is_host($session, $userId, $role);
    if ($host) {
        return ['mic'=>true, 'cam'=>true, 'screen'=>true, 'whiteboard'=>true, 'chat'=>true];
    }
    $base = [
        'mic'        => !empty($session['allow_student_mic']),
        'cam'        => !empty($session['allow_student_cam']),
        'screen'     => !empty($session['allow_screen_share']),
        'whiteboard' => !empty($session['allow_whiteboard']),
        // چت آموزشی طبق درخواست کارفرما همیشه در اتاق فعال است؛ کنترل‌های اجازه فقط برای صدا/تصویر/اسکرین/تخته است.
        'chat'       => true,
    ];
    $decisions = online_permission_latest_decisions((int)$session['id'], $userId);
    foreach (['mic','cam','screen','whiteboard'] as $type) {
        if (($decisions[$type] ?? '') === 'approved') $base[$type] = true;
        if (($decisions[$type] ?? '') === 'denied') $base[$type] = false;
    }
    return $base;
}

function online_permission_user_allowed(array $session, int $userId, string $role, string $permission): bool {
    $state = online_permission_effective_state($session, $userId, $role);
    return !empty($state[$permission]);
}

function online_session_status_label(string $status): string {
    return match ($status) {
        'draft' => 'پیش‌نویس',
        'scheduled' => 'زمان‌بندی‌شده',
        'live' => 'در حال برگزاری',
        'ended' => 'پایان‌یافته',
        'cancelled' => 'لغوشده',
        default => $status,
    };
}
