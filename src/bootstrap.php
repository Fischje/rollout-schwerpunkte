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
require_once __DIR__ . '/rv_rueckmeldungen.php';
$config['app_version'] = app_version($config['app_version'] ?? null);

$db = Database::connect($config['db_path']);
try {
    Database::initialize($db, __DIR__ . '/schema.sql');
} catch (DatabaseVersionException $error) {
    // Wartungshinweis statt Fehlerseite, bis die Datenbank einmalig umgestellt ist
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    $message = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wartung</title></head>'
        . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:3rem auto;padding:0 1rem;line-height:1.5;color:#212529;background:#fff">'
        . '<h1 style="font-size:1.4rem">Die Datenbank muss einmalig umgestellt werden</h1>'
        . '<p>' . $message . '</p>'
        . '<p>Bitte im Anwendungsordner auf dem Server ausführen, während niemand die Anwendung nutzt:</p>'
        . '<pre style="background:#f1f3f5;padding:.75rem;border-radius:.375rem;overflow:auto">php tools/datenbank-bereinigen.php</pre>'
        . '<p>Details stehen in <code>tools/ANLEITUNG-DATENBANK-BEREINIGUNG.txt</code>.</p></body></html>';
    exit;
}
fi_class_registry(provider_class_levels($db, 'FI'));
