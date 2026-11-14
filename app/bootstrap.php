<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Konfigurasi belum ada. Salin app/config.sample.php menjadi app/config.php lalu sesuaikan.');
}
$GLOBALS['CONFIG'] = require $configFile;

date_default_timezone_set(config('timezone', 'Asia/Jakarta'));

if (config('env') === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require APP_ROOT . '/security.php';
install_error_handler();
send_security_headers();

// Sesi yang aman
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = is_https();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_name(defined('APP_SIDE') && APP_SIDE === 'portal' ? 'CTUPORTAL' : 'CTUSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (config('base_url') ?: '') . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require APP_ROOT . '/helpers.php';
require APP_ROOT . '/db.php';
require APP_ROOT . '/auth.php';
require APP_ROOT . '/upload.php';
require APP_ROOT . '/margin.php';

function config(string $key, $default = null)
{
    $value = $GLOBALS['CONFIG'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
