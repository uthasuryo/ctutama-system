<?php
require __DIR__ . '/_boot.php';

if (current_user()) {
    redirect('index.php');
}
// Arahkan ke instalasi bila belum ada pengguna
try {
    $userCount = (int)q_val('SELECT COUNT(*) FROM users');
} catch (PDOException) {
    $userCount = 0; // tabel belum dibuat
}
if ($userCount === 0) {
    redirect('setup.php');
}

$error = null;
if (is_post()) {
    csrf_check();
    [$ok, $error] = attempt_login((string)input('email', ''), (string)($_POST['password'] ?? ''));
    if ($ok) {
        $to = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        if ($to && str_starts_with($to, url(''))) {
            header('Location: ' . $to);
            exit;
        }
        redirect('index.php');
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Masuk · Portal CTU</title>
<link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth">
  <div class="auth__art" aria-hidden="true">
    <div class="auth__glow"></div>
    <img src="<?= asset('img/logo-mark.svg') ?>" alt="">
    <p><b>Portal Internal</b><br>Dokumen legal, proyek, dan analisis margin dalam satu tempat.</p>
  </div>
  <main class="auth__box">
    <a href="<?= site_url('') ?>" class="auth__back">← Kembali ke website</a>
    <h1>Masuk</h1>
    <p class="muted">Gunakan akun yang diberikan oleh admin PT Candaka Tiga Utama.</p>
    <?php if ($error): ?><div class="alert alert--error"><?= icon('alert', 18) ?> <span><?= e($error) ?></span></div><?php endif; ?>
    <?php foreach (flashes() as $f): ?><div class="alert alert--<?= e($f['type']) ?>"><span><?= e($f['message']) ?></span></div><?php endforeach; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label class="f"><span>Email</span><input type="email" name="email" required autofocus autocomplete="username" value="<?= e(input('email', '')) ?>"></label>
      <label class="f"><span>Kata sandi</span><input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn--primary btn--block">Masuk</button>
    </form>
  </main>
</body>
</html>
