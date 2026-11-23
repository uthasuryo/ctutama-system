<?php
/** @var string $pageTitle @var string $active */
$me = current_user();
$active = $active ?? '';
$unread = $me['role'] === 'admin' ? (int)q_val('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0') : 0;
$menu = [
    ['index.php', 'dashboard', 'Dasbor', 'home', false],
    ['proyek.php', 'proyek', 'Proyek', 'folder', false],
    ['legal.php', 'legal', 'Dokumen Legal', 'shield', false],
    ['kalkulator.php', 'kalkulator', 'Kalkulator Margin', 'calc', false],
    ['pengeluaran.php', 'pengeluaran', 'Pengeluaran', 'wallet', true],
    ['pesan.php', 'pesan', 'Pesan Masuk', 'mail', true],
    ['pengguna.php', 'pengguna', 'Pengguna', 'users', true],
    ['penyimpanan.php', 'penyimpanan', 'Penyimpanan', 'disk', true],
    ['aktivitas.php', 'aktivitas', 'Log Aktivitas', 'clock', true],
];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(($pageTitle ?? 'Portal') . ' · Portal CTU') ?></title>
<link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
<div class="shell">
  <aside class="side" id="side">
    <a class="side__brand" href="<?= url('index.php') ?>">
      <img src="<?= asset('img/logo-mark.svg') ?>" alt="" width="30" height="34">
      <span><b>CTU</b> Portal</span>
    </a>
    <nav class="side__nav">
      <?php foreach ($menu as [$href, $key, $label, $ic, $adminOnly]):
        if ($adminOnly && $me['role'] !== 'admin') continue; ?>
        <a href="<?= url('' . $href) ?>" class="<?= $active === $key ? 'is-active' : '' ?>">
          <?= icon($ic, 19) ?><span><?= e($label) ?></span>
          <?php if ($key === 'pesan' && $unread): ?><em class="badge"><?= $unread ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="side__foot">
      <a href="<?= site_url('') ?>" target="_blank" rel="noopener"><?= icon('globe', 18) ?><span>Lihat Website</span></a>
    </div>
  </aside>
  <div class="side__scrim" id="sideScrim"></div>

  <div class="main">
    <header class="top">
      <button class="top__menu" id="sideToggle" aria-label="Menu"><?= icon('menu', 22) ?></button>
      <h1 class="top__title"><?= e($pageTitle ?? '') ?></h1>
      <div class="top__user">
        <a href="<?= url('akun.php') ?>" class="avatar" title="Akun saya"><?= e(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?></a>
        <div class="top__who"><b><?= e($me['name']) ?></b><small><?= $me['role'] === 'admin' ? 'Administrator' : 'Investor' ?></small></div>
        <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?><button class="iconbtn" title="Keluar" aria-label="Keluar"><?= icon('logout', 19) ?></button></form>
      </div>
    </header>
    <div class="content">
      <?php foreach (flashes() as $fl): ?>
        <div class="alert alert--<?= e($fl["type"]) ?>"><?= icon($fl["type"] === "success" ? "check" : "alert", 18) ?> <span><?= e($fl["message"]) ?></span></div>
      <?php endforeach; ?>
