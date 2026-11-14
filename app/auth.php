<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $row = q_one('SELECT id, name, email, phone, role, is_active, password_hash FROM users WHERE id = ?', [$_SESSION['uid']]);
            $idle = time() - (int)($_SESSION['_last'] ?? 0);
            // Sesi berakhir bila: akun hilang/nonaktif, kata sandi diganti di tempat lain, atau 2 jam tidak aktif
            if (!$row || !$row['is_active'] || !hash_equals((string)($_SESSION['_pwv'] ?? ''), password_fingerprint($row['password_hash'])) || $idle > SESSION_IDLE_SECONDS) {
                if ($row && $idle > SESSION_IDLE_SECONDS) flash('error', 'Sesi berakhir karena tidak aktif. Silakan masuk kembali.');
                unset($_SESSION['uid'], $_SESSION['_pwv'], $_SESSION['_last']);
            } else {
                unset($row['password_hash']);
                $user = $row;
                $_SESSION['_last'] = time();
            }
        }
    }
    return $user;
}

const SESSION_IDLE_SECONDS = 7200;
const LOGIN_MAX_PER_ACCOUNT = 5;   // gagal per akun / 15 menit
const LOGIN_MAX_PER_IP = 20;       // gagal per IP / 15 menit

function password_fingerprint(string $hash): string
{
    return substr(hash('sha256', $hash), 0, 24);
}

/** Tandai sesi milik pengguna (dipanggil setelah login atau ganti sandi). */
function bind_session(int $uid, string $hash): void
{
    $_SESSION['uid'] = $uid;
    $_SESSION['_pwv'] = password_fingerprint($hash);
    $_SESSION['_last'] = time();
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['_intended'] = safe_local_path($_SERVER['REQUEST_URI'] ?? null);
        redirect('login.php');
    }
    ensure_schema();
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        flash('error', 'Halaman tersebut hanya untuk admin.');
        redirect('index.php');
    }
    return $u;
}

/**
 * Percobaan login dibatasi di database (tidak bisa diakali dengan membuang cookie):
 * maks. 5 gagal per akun dan 20 gagal per IP dalam 15 menit.
 */
function attempt_login(string $email, string $password): array
{
    ensure_schema();
    $email = strtolower(trim($email));
    $ip = client_ip();
    $locked = 'Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.';
    if (throttle_count('login_fail', 'e:' . $email, 900) >= LOGIN_MAX_PER_ACCOUNT
        || throttle_count('login_fail', 'i:' . $ip, 900) >= LOGIN_MAX_PER_IP) {
        log_activity('login_diblokir', $email);
        return [false, $locked];
    }

    $u = $email !== '' ? q_one('SELECT * FROM users WHERE email = ?', [$email]) : null;
    // Selalu jalankan password_verify agar waktu respons tidak membocorkan apakah email terdaftar
    $hash = $u['password_hash'] ?? '$2y$10$Ao6wxmtdtsAD6G0Z/2A5ieYeUenX8bFxPSefJRn4JapGs94APL6Fa';
    $ok = password_verify($password, $hash) && $u;
    if (!$ok) {
        throttle_hit('login_fail', 'e:' . $email);
        throttle_hit('login_fail', 'i:' . $ip);
        log_activity('login_gagal', mb_substr($email, 0, 160));
        return [false, 'Email atau kata sandi salah.'];
    }
    if (!$u['is_active']) {
        return [false, 'Akun ini dinonaktifkan. Hubungi admin.'];
    }

    throttle_clear('login_fail', 'e:' . $email);
    session_regenerate_id(true);
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        $u['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        q('UPDATE users SET password_hash = ? WHERE id = ?', [$u['password_hash'], $u['id']]);
    }
    bind_session((int)$u['id'], $u['password_hash']);
    q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$u['id']]);
    log_activity('login', $u['email']);
    return [true, null];
}

function logout(): void
{
    log_activity('logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Apakah pengguna saat ini boleh membuka proyek tertentu? */
function can_view_project(int $projectId): bool
{
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    return (bool)q_val('SELECT 1 FROM project_investors WHERE project_id = ? AND user_id = ?', [$projectId, $u['id']]);
}

function validate_password(string $p): ?string
{
    if (strlen($p) < 8) return 'Kata sandi minimal 8 karakter.';
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) return 'Kata sandi harus mengandung huruf dan angka.';
    return null;
}
