<?php
/** اتصال PDO تک‌نمونه (singleton) */
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+03:30'",
        ]);
    } catch (PDOException $e) {
        if (APP_ENV === 'development') {
            die('DB Error: ' . $e->getMessage());
        }
        http_response_code(500);
        die('خطا در اتصال به پایگاه داده. لطفاً کمی بعد دوباره تلاش کنید.');
    }
    return $pdo;
}
