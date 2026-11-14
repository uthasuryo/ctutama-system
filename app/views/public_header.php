<?php
/** @var string $pageTitle @var string $pageDesc @var string $activeNav @var string $bodyClass */
$pageTitle = $pageTitle ?? config('app_name');
$pageDesc  = $pageDesc ?? 'PT Candaka Tiga Utama — mitra terpadu bidang Konstruksi, Pengadaan Barang, dan Teknologi Informasi. Berkedudukan di Kota Bandung, Jawa Barat.';
$activeNav = $activeNav ?? '';
$c = config('company');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#0E1A24">
<link rel="icon" type="image/png" href="<?= asset('img/favicon.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('img/apple-touch-icon.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip" href="#main">Lewati ke konten</a>

<header class="nav" id="nav">
  <div class="wrap nav__inner">
    <a class="brand" href="<?= url('') ?>" aria-label="Beranda <?= e($c['legal_name']) ?>">
      <img src="<?= asset('img/logo-mark.svg') ?>" alt="" width="34" height="38">
      <span class="brand__text"><b class="c-blue">CANDAKA</b> <b class="c-taupe">TIGA</b> <b class="c-olive">UTAMA</b></span>
    </a>
    <nav class="nav__links" id="navLinks" aria-label="Navigasi utama">
      <a href="<?= url('') ?>" class="<?= $activeNav === 'home' ? 'is-active' : '' ?>">Beranda</a>
      <div class="nav__drop">
        <button type="button" class="<?= $activeNav === 'divisi' ? 'is-active' : '' ?>" aria-haspopup="true">Divisi <span aria-hidden="true">▾</span></button>
        <div class="nav__menu">
          <?php foreach (divisions() as $navSlug => $navDiv): ?>
            <a href="<?= url('divisi.php?d=' . $navSlug) ?>" style="--dc:<?= e($navDiv['color']) ?>">
              <span class="dot"></span><?= e($navDiv['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <a href="<?= url('portofolio.php') ?>" class="<?= $activeNav === 'portofolio' ? 'is-active' : '' ?>">Portofolio</a>
      <a href="<?= url('index.php#tentang') ?>">Tentang</a>
      <a href="<?= url('kontak.php') ?>" class="<?= $activeNav === 'kontak' ? 'is-active' : '' ?>">Kontak</a>
    </nav>
    <button class="nav__toggle" id="navToggle" aria-label="Buka menu" aria-expanded="false" aria-controls="navLinks"><?= icon('menu', 24) ?></button>
  </div>
</header>

<main id="main">
<?php foreach (flashes() as $f): ?>
  <div class="toast toast--<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
<?php endforeach; ?>
