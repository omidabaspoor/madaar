<?php
/**
 * Web Push helper for real browser push notifications.
 *
 * Current in-app notifications are polling-based and only work while the app is open.
 * This helper stores Push API subscriptions and sends standards-based Web Push messages
 * when VAPID keys are configured in config/config.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

function web_push_enabled(): bool
{
    return defined('VAPID_PUBLIC_KEY') && defined('VAPID_PRIVATE_KEY')
        && trim((string)VAPID_PUBLIC_KEY) !== '' && trim((string)VAPID_PRIVATE_KEY) !== '';
}

function web_push_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function web_push_b64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    $out = base64_decode($data, true);
    return $out === false ? '' : $out;
}

function web_push_ensure_schema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS web_push_subscriptions (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          endpoint TEXT NOT NULL,
          p256dh VARCHAR(255) NOT NULL,
          auth VARCHAR(255) NOT NULL,
          user_agent VARCHAR(255) DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          last_success_at DATETIME DEFAULT NULL,
          last_error VARCHAR(255) DEFAULT NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          PRIMARY KEY (id),
          KEY idx_wps_user (user_id, is_active),
          UNIQUE KEY uniq_wps_endpoint_hash (endpoint(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

function web_push_save_subscription(int $userId, array $sub, string $userAgent = ''): bool
{
    web_push_ensure_schema();
    $endpoint = trim((string)($sub['endpoint'] ?? ''));
    $keys = is_array($sub['keys'] ?? null) ? $sub['keys'] : [];
    $p256dh = trim((string)($keys['p256dh'] ?? ''));
    $auth = trim((string)($keys['auth'] ?? ''));
    if ($endpoint === '' || $p256dh === '' || $auth === '') return false;

    $st = db()->prepare("INSERT INTO web_push_subscriptions
        (user_id, endpoint, p256dh, auth, user_agent, is_active, last_error)
        VALUES (?, ?, ?, ?, ?, 1, NULL)
        ON DUPLICATE KEY UPDATE
          user_id=VALUES(user_id), p256dh=VALUES(p256dh), auth=VALUES(auth),
          user_agent=VALUES(user_agent), is_active=1, last_error=NULL, updated_at=CURRENT_TIMESTAMP");
    return $st->execute([$userId, $endpoint, $p256dh, $auth, substr($userAgent, 0, 250)]);
}

function web_push_delete_subscription(int $userId, string $endpoint): void
{
    web_push_ensure_schema();
    if ($endpoint === '') return;
    try {
        db()->prepare('UPDATE web_push_subscriptions SET is_active=0 WHERE user_id=? AND endpoint=?')
            ->execute([$userId, $endpoint]);
    } catch (Throwable $e) {}
}

function web_push_hkdf(string $salt, string $ikm, string $info, int $length): string
{
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $t = '';
    $last = '';
    for ($i = 1; strlen($t) < $length; $i++) {
        $last = hash_hmac('sha256', $last . $info . chr($i), $prk, true);
        $t .= $last;
    }
    return substr($t, 0, $length);
}

function web_push_public_key_pem_from_raw(string $raw): string
{
    // SubjectPublicKeyInfo for id-ecPublicKey + prime256v1 + uncompressed 65-byte point
    $der = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function web_push_private_key_pem_from_raw(string $privateRaw, string $publicRaw): string
{
    // ECPrivateKey ASN.1 for prime256v1 with public key included.
    $der = hex2bin('30770201010420') . $privateRaw . hex2bin('A00A06082A8648CE3D030107A144034200') . $publicRaw;
    return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
}

function web_push_der_signature_to_raw(string $der): string
{
    $pos = 0;
    if (ord($der[$pos++] ?? "\0") !== 0x30) return $der;
    $seqLen = ord($der[$pos++] ?? "\0");
    if ($seqLen & 0x80) { $n = $seqLen & 0x7f; $pos += $n; }
    if (ord($der[$pos++] ?? "\0") !== 0x02) return $der;
    $rLen = ord($der[$pos++] ?? "\0");
    $r = substr($der, $pos, $rLen); $pos += $rLen;
    if (ord($der[$pos++] ?? "\0") !== 0x02) return $der;
    $sLen = ord($der[$pos++] ?? "\0");
    $s = substr($der, $pos, $sLen);
    $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    return substr($r, -32) . substr($s, -32);
}

function web_push_vapid_auth(string $audience): string
{
    $publicRaw = web_push_b64url_decode((string)VAPID_PUBLIC_KEY);
    $privateRaw = web_push_b64url_decode((string)VAPID_PRIVATE_KEY);
    if (strlen($publicRaw) !== 65 || strlen($privateRaw) !== 32) return '';

    $header = web_push_b64url_encode(json_encode(['typ'=>'JWT','alg'=>'ES256'], JSON_UNESCAPED_SLASHES));
    $payload = web_push_b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 60 * 60,
        'sub' => defined('VAPID_SUBJECT') && VAPID_SUBJECT ? VAPID_SUBJECT : ('mailto:admin@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')),
    ], JSON_UNESCAPED_SLASHES));
    $pem = web_push_private_key_pem_from_raw($privateRaw, $publicRaw);
    $sig = '';
    if (!openssl_sign($header . '.' . $payload, $sig, $pem, OPENSSL_ALGO_SHA256)) return '';
    return 'vapid t=' . $header . '.' . $payload . '.' . web_push_b64url_encode(web_push_der_signature_to_raw($sig)) . ', k=' . VAPID_PUBLIC_KEY;
}

function web_push_encrypt_payload(string $payload, string $receiverP256dh, string $receiverAuth): ?array
{
    $receiverPub = web_push_b64url_decode($receiverP256dh);
    $authSecret = web_push_b64url_decode($receiverAuth);
    if (strlen($receiverPub) !== 65 || strlen($authSecret) < 16) return null;

    $priv = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    if (!$priv) return null;
    $details = openssl_pkey_get_details($priv);
    $x = $details['ec']['x'] ?? '';
    $y = $details['ec']['y'] ?? '';
    if ($x === '' || $y === '') return null;
    $senderPub = "\x04" . $x . $y;

    $receiverPem = web_push_public_key_pem_from_raw($receiverPub);
    $receiverKey = openssl_pkey_get_public($receiverPem);
    if (!$receiverKey) return null;
    $shared = openssl_pkey_derive($receiverKey, $priv, 32);
    if (!is_string($shared) || strlen($shared) === 0) return null;

    $ikm = web_push_hkdf($authSecret, $shared, 'WebPush: info' . "\0" . $receiverPub . $senderPub, 32);
    $salt = random_bytes(16);
    $cek = web_push_hkdf($salt, $ikm, 'Content-Encoding: aes128gcm' . "\0", 16);
    $nonce = web_push_hkdf($salt, $ikm, 'Content-Encoding: nonce' . "\0", 12);

    $plaintext = $payload . "\x02";
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if (!is_string($cipher)) return null;

    $rs = pack('N', 4096);
    $body = $salt . $rs . chr(strlen($senderPub)) . $senderPub . $cipher . $tag;
    return ['body' => $body, 'encoding' => 'aes128gcm'];
}

function web_push_send_subscription(array $sub, array $payload): bool
{
    if (!web_push_enabled() || !function_exists('curl_init')) return false;
    $endpoint = (string)($sub['endpoint'] ?? '');
    if ($endpoint === '') return false;
    $urlParts = parse_url($endpoint);
    if (!$urlParts || empty($urlParts['scheme']) || empty($urlParts['host'])) return false;
    $audience = $urlParts['scheme'] . '://' . $urlParts['host'];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) return false;
    $encrypted = web_push_encrypt_payload($json, (string)$sub['p256dh'], (string)$sub['auth']);
    if (!$encrypted) return false;
    $auth = web_push_vapid_auth($audience);
    if ($auth === '') return false;

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => $encrypted['body'],
        CURLOPT_HTTPHEADER => [
            'TTL: 2419200',
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: ' . $auth,
            'Content-Length: ' . strlen($encrypted['body']),
        ],
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}

function web_push_send_to_user(int $userId, array $payload): void
{
    if (!web_push_enabled()) return;
    web_push_ensure_schema();
    try {
        $st = db()->prepare('SELECT * FROM web_push_subscriptions WHERE user_id=? AND is_active=1 ORDER BY updated_at DESC LIMIT 10');
        $st->execute([$userId]);
        foreach ($st->fetchAll() as $sub) {
            $ok = web_push_send_subscription($sub, $payload);
            if ($ok) {
                db()->prepare('UPDATE web_push_subscriptions SET last_success_at=NOW(), last_error=NULL WHERE id=?')->execute([$sub['id']]);
            } else {
                db()->prepare('UPDATE web_push_subscriptions SET last_error=? WHERE id=?')->execute(['send_failed', $sub['id']]);
            }
        }
    } catch (Throwable $e) {}
}
