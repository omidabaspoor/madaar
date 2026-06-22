<?php
/**
 * Generate VAPID keys for Web Push.
 * Usage from project root:
 *   php scripts/generate_vapid_keys.php
 * Then copy the two values into config/config.php.
 */
declare(strict_types=1);

function b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

$key = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name' => 'prime256v1',
]);
if (!$key) {
    fwrite(STDERR, "OpenSSL could not generate an EC key.\n");
    exit(1);
}
$details = openssl_pkey_get_details($key);
$ec = $details['ec'] ?? [];
$private = $ec['d'] ?? '';
$x = $ec['x'] ?? '';
$y = $ec['y'] ?? '';
if ($private === '' || $x === '' || $y === '') {
    fwrite(STDERR, "This PHP/OpenSSL build does not expose EC key details.\n");
    exit(1);
}
$public = "\x04" . $x . $y;

echo "VAPID_PUBLIC_KEY=" . b64url($public) . PHP_EOL;
echo "VAPID_PRIVATE_KEY=" . b64url($private) . PHP_EOL;
