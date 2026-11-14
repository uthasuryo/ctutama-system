<?php
require __DIR__ . '/_boot.php';

$projects = q_all('SELECT * FROM projects WHERE is_public = 1 ORDER BY COALESCE(end_date, start_date, created_at) DESC');
$active = isset(divisions()[$_GET['d'] ?? '']) ? $_GET['d'] : 'all';

$pageTitle = 'Portofolio Proyek — PT Candaka Tiga Utama';
$activeNav = 'portofolio';
require APP_ROOT . '/views/public_header.php';
?>
<section class="phead">
  <div class="hero__bg" aria-hidden="true"><span class="blob blob--blue"></span><span class="blob blob--olive"></span><div class="grid-lines"></div></div>
  <div class="wrap">
    <nav class="crumbs"><a href="<?= url('') ?>">Beranda</a> / <span>Portofolio</span></nav>
    <h1>Portofolio Proyek</h1>
    <p>Pekerjaan yang telah dan sedang kami tangani di ketiga divisi.</p>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="filters" role="tablist" aria-label="Filter divisi">
      <button class="filter <?= $active === 'all' ? 'is-on' : '' ?>" data-filter="all">Semua <small><?= count($projects) ?></small></button>
      <?php foreach (divisions() as $slug => $d):
        $n = count(array_filter($projects, fn($p) => $p['division'] === $slug)); ?>
        <button class="filter <?= $active === $slug ? 'is-on' : '' ?>" data-filter="<?= e($slug) ?>" style="--dc:<?= e($d['color']) ?>"><?= e($d['name']) ?> <small><?= $n ?></small></button>
      <?php endforeach; ?>
    </div>

    <?php if ($projects): ?>
      <div class="pgrid" id="pgrid">
        <?php foreach ($projects as $p) include APP_ROOT . '/views/project_card.php'; ?>
      </div>
      <div class="empty" id="pEmpty" hidden><?= icon('folder', 36) ?><p>Belum ada proyek yang ditampilkan untuk divisi ini.</p></div>
    <?php else: ?>
      <div class="empty"><?= icon('folder', 36) ?><p>Portofolio sedang kami siapkan. Hubungi kami untuk daftar referensi pekerjaan.</p></div>
    <?php endif; ?>
  </div>
</section>

<?php require APP_ROOT . '/views/public_footer.php'; ?>
