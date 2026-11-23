<?php
declare(strict_types=1);

define('APP_ROOT', __DIR__);

// Deploy manual: pastikan semua file inti ada sebelum dimuat, agar paket yang belum lengkap
// menghasilkan pesan yang jelas, bukan halaman kosong/error 500.
const CORE_FILES = ['security.php', 'helpers.php', 'db.php', 'auth.php', 'upload.php', 'image.php', 'project.php', 'expense.php', 'margin.php'];
$__missing = array_values(array_filter(CORE_FILES, fn($f) => !is_file(APP_ROOT . '/' . $f)));
if ($__missing) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pemasangan belum lengkap</title>'
       . '<body style="font-family:system-ui,sans-serif;max-width:560px;margin:12vh auto;padding:0 20px;line-height:1.6;color:#14222D">'
       . '<h1 style="font-size:1.4rem">Pemasangan belum lengkap</h1><p>File berikut tidak ditemukan di folder <code>app/</code> server:</p><ul>'
       . implode('', array_map(fn($f) => '<li><code>' . htmlspecialchars($f) . '</code></li>', $__missing))
       . '</ul><p>Unggah ulang <b>paket lengkap</b> (ctu-deploy-….zip) ke folder <code>domains/</code> lalu ekstrak. Konfigurasi dan dokumen yang sudah ada tidak akan tertimpa.</p>';
    exit;
}
unset($__missing);

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
require APP_ROOT . '/image.php';
require APP_ROOT . '/project.php';
require APP_ROOT . '/expense.php';
require APP_ROOT . '/margin.php';
if (is_file(APP_ROOT . '/version.php')) $GLOBALS['APP_VERSION'] = require APP_ROOT . '/version.php';

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
