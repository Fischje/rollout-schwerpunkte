<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone']);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Angemeldet bleiben, bis so lange nichts mehr geklickt wurde (Standard 12 Stunden, in config.php: session_lifetime_hours).
    // Ohne diese Einstellung gälten die PHP-Vorgaben: 24 Minuten Inaktivität und Abmeldung beim Schließen des Browsers.
    $sessionLifetime = max(900, (int)round(((float)($config['session_lifetime_hours'] ?? 12)) * 3600));
    $sessionSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    // Eigener Ablageort, damit der System-Aufräumjob (Debian/Ubuntu: nach 24 Minuten) die Sitzungen nicht vorzeitig löscht
    $sessionPath = dirname(__DIR__) . '/data/sessions';
    if (!is_dir($sessionPath)) @mkdir($sessionPath, 0700, true);
    if (is_dir($sessionPath) && is_writable($sessionPath)) session_save_path($sessionPath);
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $sessionSecure,
    ]);
    session_start();
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_seen']) && time() - (int)$_SESSION['last_seen'] > $sessionLifetime) {
            session_unset();
            session_destroy();
            session_start();
        } else {
            $_SESSION['last_seen'] = time();
            // Ablaufzeit des Cookies bei jedem Aufruf nach vorn schieben
            setcookie(session_name(), session_id(), ['expires' => time() + $sessionLifetime, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $sessionSecure]);
        }
    }
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Xlsx.php';
require_once __DIR__ . '/Pptx.php';
$config['app_version'] = app_version($config['app_version'] ?? null);

$db = Database::connect($config['db_path']);
Database::initialize($db, __DIR__ . '/schema.sql');
fi_class_registry(provider_class_levels($db, 'FI'));
