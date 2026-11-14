<?php
/**
 * Instalasi awal: membuat tabel (bila belum ada) dan akun admin pertama.
 * Halaman ini otomatis terkunci setelah ada satu pengguna.
 */
require __DIR__ . '/_boot.php';

// Buat tabel bila belum ada
$hasUsers = (bool)q_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
if (!$hasUsers) {
    $sql = file_get_contents(dirname(APP_ROOT) . '/database/schema.sql');
    db()->exec($sql);
}
if ((int)q_val('SELECT COUNT(*) FROM users') > 0) {
    flash('error', 'Instalasi sudah selesai. Silakan masuk.');
    redirect('login.php');
}

$setupKey = (string)config('setup_key', '');
$keyReady = strlen($setupKey) >= 16;

$errors = [];
if (is_post() && $keyReady) {
    csrf_check();
    ensure_schema();
    if (throttle_count('setup_fail', client_ip(), 900) >= 5) {
        http_response_code(429);
        exit('Terlalu banyak percobaan. Coba lagi nanti.');
    }
    if (!hash_equals($setupKey, (string)($_POST['setup_key'] ?? ''))) {
        throttle_hit('setup_fail', client_ip());
        $errors[] = 'Kode instalasi salah.';
    }
    $name = (string)input('name', '');
    $email = strtolower((string)input('email', ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!$errors && $name === '') $errors[] = 'Nama wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
    if ($pe = validate_password($pass)) $errors[] = $pe;
    if ($pass !== ($_POST['password2'] ?? '')) $errors[] = 'Konfirmasi kata sandi tidak sama.';
    if (!$errors) {
        q('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)', [$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
        foreach (['legal', 'projects'] as $d) {
            @mkdir(config('storage_path') . '/' . $d, 0750, true);
        }
        @mkdir(config('cover_path'), 0755, true);
        log_activity('instalasi', $email);
        flash('success', 'Akun admin berhasil dibuat. Silakan masuk, lalu kosongkan kembali setup_key di config.php.');
        redirect('login.php');
    }
}

$checks = [
    ['PHP ≥ 8.1', version_compare(PHP_VERSION, '8.1', '>=')],
    ['Ekstensi pdo_mysql', extension_loaded('pdo_mysql')],
    ['Ekstensi fileinfo', extension_loaded('fileinfo')],
    ['Folder storage dapat ditulis', is_writable(config('storage_path')) || (@mkdir(config('storage_path'), 0750, true) && is_writable(config('storage_path')))],
    ['Folder sampul dapat ditulis', is_writable(config('cover_path')) || (@mkdir(config('cover_path'), 0755, true) && is_writable(config('cover_path')))],
];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Instalasi · Portal CTU</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth">
  <div class="auth__art" aria-hidden="true"><div class="auth__glow"></div><img src="<?= asset('img/logo-mark.svg') ?>" alt=""><p><b>Instalasi Awal</b><br>Buat akun administrator pertama.</p></div>
  <main class="auth__box">
    <h1>Instalasi</h1>
    <ul class="checks">
      <?php foreach ($checks as [$label, $ok]): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= icon($ok ? 'check' : 'x', 16) ?> <?= e($label) ?></li><?php endforeach; ?>
    </ul>
    <?php if (!$keyReady): ?>
      <div class="alert alert--error"><span>Instalasi terkunci. Isi <code>'setup_key'</code> di <code>app/config.php</code> dengan kode acak minimal 16 karakter, lalu muat ulang halaman ini.</span></div>
    <?php endif; ?>
    <?php foreach ($errors as $er): ?><div class="alert alert--error"><span><?= e($er) ?></span></div><?php endforeach; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label class="f"><span>Kode instalasi (setup_key di config.php)</span><input type="password" name="setup_key" required autocomplete="off" <?= $keyReady ? '' : 'disabled' ?>></label>
      <label class="f"><span>Nama</span><input name="name" required value="<?= e(input('name', '')) ?>"></label>
      <label class="f"><span>Email</span><input type="email" name="email" required value="<?= e(input('email', '')) ?>"></label>
      <label class="f"><span>Kata sandi (min. 8, huruf + angka)</span><input type="password" name="password" required minlength="8"></label>
      <label class="f"><span>Ulangi kata sandi</span><input type="password" name="password2" required minlength="8"></label>
      <button class="btn btn--primary btn--block" <?= $keyReady ? '' : 'disabled' ?>>Buat Akun Admin</button>
    </form>
  </main>
</body>
</html>
