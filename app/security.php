<?php
declare(strict_types=1);

/* =============================================================
   Keamanan: header HTTP, pembatasan percobaan (rate limit),
   redirect aman, dan penanganan error.
   ============================================================= */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * IP pengunjung. Secara bawaan hanya REMOTE_ADDR yang dipercaya, karena header
 * seperti X-Forwarded-For bisa dipalsukan. Bila situs di belakang CDN/proxy
 * (mis. Cloudflare), isi config 'client_ip_header' => 'HTTP_CF_CONNECTING_IP'.
 */
function client_ip(): string
{
    $h = (string)config('client_ip_header', '');
    if ($h !== '' && !empty($_SERVER[$h])) {
        $ip = trim(explode(',', (string)$_SERVER[$h])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Header keamanan untuk setiap halaman PHP. */
function send_security_headers(): void
{
    if (headers_sent()) return;
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }

    $portal = defined('APP_SIDE') && APP_SIDE === 'portal';
    $siteOrigin = '';
    if ($portal && ($u = parse_url((string)config('site_url', '')))) {
        $siteOrigin = isset($u['scheme'], $u['host']) ? ' ' . $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '') : '';
    }
    $csp = [
        "default-src 'self'",
        "script-src 'self'",
        // 'unsafe-inline' hanya untuk atribut style (warna divisi); skrip tetap ketat
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data:" . $siteOrigin,
        "connect-src 'self'",
        $portal ? "frame-src 'none'" : "frame-src https://www.google.com https://maps.google.com",
        $portal ? "frame-ancestors 'none'" : "frame-ancestors 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
    ];
    if (is_https()) $csp[] = 'upgrade-insecure-requests';
    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Frame-Options: ' . ($portal ? 'DENY' : 'SAMEORIGIN'));
    if ($portal) {
        header('X-Robots-Tag: noindex, nofollow');
    }
}

/* ---------- Rate limit berbasis database (tidak bisa diakali dengan menghapus cookie) ---------- */

function throttle_key(string $key): string
{
    return hash('sha256', strtolower($key));
}

function throttle_count(string $kind, string $key, int $seconds): int
{
    return (int)q_val('SELECT COUNT(*) FROM security_events WHERE kind = ? AND subject = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)',
        [$kind, throttle_key($key), $seconds]);
}

function throttle_hit(string $kind, string $key): void
{
    q('INSERT INTO security_events (kind, subject) VALUES (?, ?)', [$kind, throttle_key($key)]);
    if (random_int(1, 50) === 1) {
        q('DELETE FROM security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
    }
}

function throttle_clear(string $kind, string $key): void
{
    q('DELETE FROM security_events WHERE kind = ? AND subject = ?', [$kind, throttle_key($key)]);
}

/**
 * Redirect setelah login hanya ke path lokal di portal ini.
 * Menolak "//evil.com", "/\evil.com", URL absolut, dan karakter kontrol.
 */
function safe_local_path(?string $to): ?string
{
    if (!is_string($to) || $to === '' || strlen($to) > 500) return null;
    if ($to[0] !== '/' || str_starts_with($to, '//') || str_contains($to, '\\') || preg_match('/[\x00-\x1F\x7F]/', $to)) return null;
    $path = (string)parse_url($to, PHP_URL_PATH);
    if ($path === '' || str_contains($path, '//') || str_contains($path, '/../') || str_contains($path, '/./')) return null;
    return $to;
}

/** Penanganan error produksi: catat ke log server, tampilkan pesan umum tanpa detail teknis. */
function install_error_handler(): void
{
    if (config('env') === 'development') return;
    set_exception_handler(function (Throwable $e) {
        error_log('[CTU] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Terjadi kesalahan</title>'
           . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#0A1520;color:#fff;text-align:center">'
           . '<div><h1 style="margin:0 0 8px">Terjadi kesalahan</h1><p style="opacity:.7">Silakan coba lagi beberapa saat lagi.</p><p><a href="/" style="color:#29AAE3">Kembali</a></p></div>';
    });
}
