<?php
require __DIR__ . '/_boot.php';

$slug = (string)($_GET['d'] ?? '');
$all = divisions();
if (!isset($all[$slug])) {
    not_found();
}
$d = $all[$slug];
$projects = q_all('SELECT * FROM projects WHERE is_public = 1 AND division = ? ORDER BY COALESCE(end_date, start_date, created_at) DESC LIMIT 9', [$slug]);
$count = (int)q_val("SELECT COUNT(*) FROM projects WHERE division = ? AND status <> 'batal'", [$slug]);

$pageTitle = 'Divisi ' . $d['name'] . ' — PT Candaka Tiga Utama';
$pageDesc  = $d['intro'];
$activeNav = 'divisi';
$bodyClass = 'is-division';
require APP_ROOT . '/views/public_header.php';
?>
<section class="dhero" style="--dc:<?= e($d['color']) ?>">
  <div class="hero__bg" aria-hidden="true"><span class="blob blob--div"></span><div class="grid-lines"></div></div>
  <div class="wrap dhero__inner">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= url('') ?>">Beranda</a> / <a href="<?= url('index.php#divisi') ?>">Divisi</a> / <span><?= e($d['name']) ?></span></nav>
    <span class="dhero__icon"><?= icon($d['icon'], 40) ?></span>
    <h1>Divisi <?= e($d['name']) ?></h1>
    <p class="dhero__tag"><?= e($d['tagline']) ?></p>
    <p class="dhero__lead"><?= e($d['intro']) ?></p>
    <div class="hero__cta">
      <a class="btn btn--primary" href="<?= url('kontak.php?divisi=' . $slug) ?>">Minta Penawaran <?= icon('arrow', 18) ?></a>
      <?php if ($count): ?><span class="dhero__count"><b><?= $count ?></b> proyek ditangani</span><?php endif; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section__head reveal">
      <p class="kicker" style="color:<?= e($d['color']) ?>">Layanan</p>
      <h2>Apa yang kami kerjakan.</h2>
    </div>
    <div class="svcgrid" style="--dc:<?= e($d['color']) ?>">
      <?php foreach ($d['services'] as $i => $s): ?>
        <div class="svc reveal" style="--delay:<?= $i * 80 ?>ms">
          <span class="svc__n"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <h3><?= e($s[0]) ?></h3>
          <p><?= e($s[1]) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section--soft">
  <div class="wrap">
    <div class="section__head section__head--row reveal">
      <div>
        <p class="kicker" style="color:<?= e($d['color']) ?>">Portofolio</p>
        <h2>Proyek divisi <?= e(mb_strtolower($d['name'])) ?>.</h2>
      </div>
      <a class="btn btn--line" href="<?= url('portofolio.php?d=' . $slug) ?>">Semua Proyek <?= icon('arrow', 16) ?></a>
    </div>
    <?php if ($projects): ?>
      <div class="pgrid">
        <?php foreach ($projects as $p) include APP_ROOT . '/views/project_card.php'; ?>
      </div>
    <?php else: ?>
      <div class="empty reveal">
        <?= icon('folder', 36) ?>
        <p>Portofolio divisi ini sedang kami siapkan. Hubungi kami untuk referensi pekerjaan.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section__head reveal"><p class="kicker">Divisi Lain</p><h2>Butuh lebih dari satu bidang?</h2></div>
    <div class="divgrid divgrid--two">
      <?php $n = 0; foreach ($all as $s2 => $o): $n++; if ($s2 === $slug) continue; ?>
        <a class="divcard reveal" href="<?= url('divisi.php?d=' . $s2) ?>" style="--dc:<?= e($o['color']) ?>">
          <span class="divcard__num">0<?= $n ?></span>
          <span class="divcard__icon"><?= icon($o['icon'], 30) ?></span>
          <h3><?= e($o['name']) ?></h3>
          <p><?= e($o['tagline']) ?></p>
          <span class="divcard__more">Lihat divisi <?= icon('arrow', 16) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require APP_ROOT . '/views/public_footer.php'; ?>
