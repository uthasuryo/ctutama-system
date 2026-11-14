<?php
// Dipakai oleh ErrorDocument (.htaccess) dan oleh not_found() dari halaman portal.
if (!defined('APP_ROOT')) {
    require __DIR__ . '/_boot.php';
}
http_response_code(404);
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Tidak ditemukan · Portal CTU</title>
<link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="auth">
  <div class="auth__art" aria-hidden="true">
    <div class="auth__glow"></div>
    <img src="<?= asset('img/logo-mark.svg') ?>" alt="">
    <p><b>404</b><br>Halaman tidak ditemukan.</p>
  </div>
  <main class="auth__box">
    <h1>Halaman tidak ditemukan</h1>
    <p class="muted">Halaman atau dokumen yang Anda cari tidak ada, sudah dihapus, atau Anda tidak memiliki akses ke sana.</p>
    <div class="hello__actions">
      <a class="btn btn--primary" href="<?= url('index.php') ?>"><?= icon('home', 18) ?> Ke Dasbor</a>
      <a class="btn btn--line" href="<?= url('proyek.php') ?>"><?= icon('folder', 18) ?> Daftar Proyek</a>
    </div>
  </main>
</body>
</html>
