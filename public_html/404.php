<?php
// Dipakai oleh ErrorDocument (.htaccess) dan oleh not_found() dari halaman lain.
if (!defined('APP_ROOT')) {
    require __DIR__ . '/_boot.php';
}
http_response_code(404);
header('Cache-Control: no-store');

$pageTitle = 'Halaman tidak ditemukan — PT Candaka Tiga Utama';
$pageDesc  = 'Halaman yang Anda cari tidak tersedia.';
$bodyClass = 'is-404';
$activeNav = '';
require APP_ROOT . '/views/public_header.php';
?>
<section class="e404">
  <div class="hero__bg" aria-hidden="true">
    <span class="blob blob--blue"></span><span class="blob blob--taupe"></span><span class="blob blob--olive"></span>
    <div class="grid-lines"></div>
  </div>
  <div class="wrap e404__inner">
    <div class="e404__art" aria-hidden="true">
      <span class="e404__num">4</span>
      <svg class="e404__mark" viewBox="-16 -22 324 362">
        <polyline class="e404__a" points="15,195 15,62 112,5 207,62 207,197"/>
        <polyline class="e404__b" points="105,108 105,245 200,305 292,245 292,108"/>
      </svg>
      <span class="e404__num">4</span>
    </div>
    <p class="eyebrow"><span class="pulse"></span> Error 404</p>
    <h1>Halaman ini belum <span class="hero__three">dibangun.</span></h1>
    <p class="e404__lead">Alamat yang Anda buka tidak ada atau sudah dipindahkan. Silakan kembali ke beranda atau langsung menuju divisi yang Anda butuhkan.</p>
    <div class="hero__cta e404__cta">
      <a class="btn btn--primary" href="<?= url('') ?>"><?= icon('home', 18) ?> Ke Beranda</a>
      <a class="btn btn--ghost" href="<?= url('kontak.php') ?>"><?= icon('mail', 18) ?> Hubungi Kami</a>
    </div>
    <div class="hero__chips e404__chips">
      <?php foreach (divisions() as $errSlug => $errDiv): ?>
        <a class="chip" href="<?= url('divisi.php?d=' . $errSlug) ?>" style="--dc:<?= e($errDiv['color']) ?>"><?= icon($errDiv['icon'], 16) ?> <?= e($errDiv['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php require APP_ROOT . '/views/public_footer.php';
