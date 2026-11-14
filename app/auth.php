<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $user = q_one('SELECT id, name, email, phone, role, is_active FROM users WHERE id = ?', [$_SESSION['uid']]);
            if (!$user || !$user['is_active']) {
                $user = null;
                unset($_SESSION['uid']);
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? null;
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

/** Percobaan login dibatasi: 5 kali gagal per 15 menit per email+IP. */
function attempt_login(string $email, string $password): array
{
    $key = 'login_' . sha1(strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $a = $_SESSION[$key] ?? ['n' => 0, 't' => time()];
    if (time() - $a['t'] > 900) {
        $a = ['n' => 0, 't' => time()];
    }
    if ($a['n'] >= 5) {
        return [false, 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.'];
    }

    $u = q_one('SELECT * FROM users WHERE email = ?', [strtolower($email)]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        $a['n']++;
        $_SESSION[$key] = $a;
        log_activity('login_gagal', $email);
        return [false, 'Email atau kata sandi salah.'];
    }
    if (!$u['is_active']) {
        return [false, 'Akun ini dinonaktifkan. Hubungi admin.'];
    }

    unset($_SESSION[$key]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];

    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
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
