<?php
require __DIR__ . '/_boot.php';
$me = require_login();
$admin = $me['role'] === 'admin';

if ($admin) {
    $byDiv = q_all("SELECT division, COUNT(*) n, SUM(status='berjalan') aktif, COALESCE(SUM(contract_value),0) nilai
                    FROM projects WHERE status <> 'batal' GROUP BY division");
    $recent = q_all('SELECT * FROM projects ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 6');
    $expiring = q_all('SELECT * FROM legal_documents WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY expiry_date ASC LIMIT 6');
    $legalCount = (int)q_val('SELECT COUNT(*) FROM legal_documents');
    $docCount = (int)q_val('SELECT COUNT(*) FROM project_documents');
    $msgs = q_all('SELECT * FROM contact_messages WHERE is_read = 0 ORDER BY created_at DESC LIMIT 4');
} else {
    $byDiv = q_all("SELECT p.division, COUNT(*) n, SUM(p.status='berjalan') aktif, COALESCE(SUM(p.contract_value),0) nilai
                    FROM projects p JOIN project_investors pi ON pi.project_id = p.id AND pi.user_id = ?
                    WHERE p.status <> 'batal' GROUP BY p.division", [$me['id']]);
    $recent = q_all('SELECT p.* FROM projects p JOIN project_investors pi ON pi.project_id = p.id AND pi.user_id = ?
                     ORDER BY COALESCE(p.updated_at, p.created_at) DESC LIMIT 6', [$me['id']]);
    $legalCount = (int)q_val('SELECT COUNT(*) FROM legal_documents WHERE visible_to_investor = 1');
    $docCount = (int)q_val('SELECT COUNT(*) FROM project_documents d JOIN project_investors pi ON pi.project_id = d.project_id AND pi.user_id = ? WHERE d.visible_to_investor = 1', [$me['id']]);
    $expiring = [];
    $msgs = [];
}
$divStats = [];
foreach ($byDiv as $r) $divStats[$r['division']] = $r;
$totalProj = array_sum(array_column($byDiv, 'n'));
$totalVal = array_sum(array_column($byDiv, 'nilai'));

$pageTitle = 'Dasbor';
$active = 'dashboard';
require APP_ROOT . '/views/app_header.php';
?>
<section class="hello">
  <div>
    <h2>Halo, <?= e(explode(' ', $me['name'])[0]) ?> 👋</h2>
    <p class="muted"><?= $admin ? 'Ringkasan aktivitas PT Candaka Tiga Utama hari ini.' : 'Berikut proyek yang dibagikan kepada Anda.' ?></p>
  </div>
  <?php if ($admin): ?>
    <div class="hello__actions">
      <a class="btn btn--primary" href="<?= url('proyek-form.php') ?>"><?= icon('plus', 18) ?> Proyek Baru</a>
      <a class="btn btn--line" href="<?= url('legal.php#unggah') ?>"><?= icon('upload', 18) ?> Unggah Dokumen Legal</a>
    </div>
  <?php endif; ?>
</section>

<section class="kpis">
  <div class="kpi"><span class="kpi__ic" style="--c:#29AAE3"><?= icon('folder', 20) ?></span><div><small>Total proyek</small><b><?= $totalProj ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#908D0A"><?= icon('chart', 20) ?></span><div><small>Nilai kontrak</small><b><?= rupiah($totalVal, true) ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#998675"><?= icon('file', 20) ?></span><div><small>Dokumen proyek</small><b><?= $docCount ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#14222D"><?= icon('shield', 20) ?></span><div><small>Dokumen legal</small><b><?= $legalCount ?></b></div></div>
</section>

<section class="divstats">
  <?php foreach (divisions() as $slug => $d): $s = $divStats[$slug] ?? ['n' => 0, 'aktif' => 0, 'nilai' => 0];
    $pct = $totalProj ? round($s['n'] / $totalProj * 100) : 0; ?>
    <a class="divstat" style="--dc:<?= e($d['color']) ?>" href="<?= url('proyek.php?divisi=' . $slug) ?>">
      <div class="divstat__top"><span class="divstat__ic"><?= icon($d['icon'], 20) ?></span><b><?= e($d['name']) ?></b></div>
      <div class="divstat__nums"><div><strong><?= (int)$s['n'] ?></strong><small>proyek</small></div><div><strong><?= (int)$s['aktif'] ?></strong><small>berjalan</small></div><div><strong><?= rupiah($s['nilai'], true) ?></strong><small>nilai kontrak</small></div></div>
      <div class="bar"><i style="width:<?= $pct ?>%"></i></div>
    </a>
  <?php endforeach; ?>
</section>

<div class="cols">
  <section class="card">
    <div class="card__head"><h3>Proyek terbaru</h3><a href="<?= url('proyek.php') ?>" class="link">Semua →</a></div>
    <?php if ($recent): ?>
      <ul class="plist">
        <?php foreach ($recent as $p): $d = divisions()[$p['division']]; ?>
          <li>
            <a href="<?= url('proyek-detail.php?id=' . $p['id']) ?>">
              <span class="dot" style="--dc:<?= e($d['color']) ?>"></span>
              <div class="plist__main"><b><?= e($p['name']) ?></b><small><?= e($p['client'] ?: $d['name']) ?></small></div>
              <span class="status status--<?= e($p['status']) ?>"><?= e(project_statuses()[$p['status']]) ?></span>
              <span class="plist__val"><?= rupiah($p['contract_value'], true) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty"><?= icon('folder', 30) ?><p><?= $admin ? 'Belum ada proyek. Tambahkan proyek pertama Anda.' : 'Belum ada proyek yang dibagikan kepada Anda.' ?></p></div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <?php if ($admin): ?>
      <section class="card">
        <div class="card__head"><h3>Masa berlaku dokumen</h3><a href="<?= url('legal.php') ?>" class="link">Legal →</a></div>
        <?php if ($expiring): ?>
          <ul class="mini">
            <?php foreach ($expiring as $l): $days = (int)floor((strtotime($l['expiry_date']) - strtotime('today')) / 86400); ?>
              <li><div><b><?= e($l['title']) ?></b><small><?= e($l['category']) ?></small></div>
                <span class="pill <?= $days < 0 ? 'pill--red' : 'pill--amber' ?>"><?= $days < 0 ? 'Kedaluwarsa' : $days . ' hari lagi' ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted small">Tidak ada dokumen yang kedaluwarsa dalam 60 hari ke depan.</p>
        <?php endif; ?>
      </section>
      <section class="card">
        <div class="card__head"><h3>Pesan baru</h3><a href="<?= url('pesan.php') ?>" class="link">Kotak masuk →</a></div>
        <?php if ($msgs): ?>
          <ul class="mini">
            <?php foreach ($msgs as $m): ?>
              <li><div><b><?= e($m['name']) ?></b><small><?= e(mb_strimwidth($m['message'], 0, 70, '…')) ?></small></div><span class="muted small"><?= tgl($m['created_at']) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted small">Tidak ada pesan baru dari website.</p>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <section class="card">
        <div class="card__head"><h3>Akses cepat</h3></div>
        <div class="quick">
          <a href="<?= url('kalkulator.php') ?>"><?= icon('calc', 22) ?> Kalkulator Margin</a>
          <a href="<?= url('legal.php') ?>"><?= icon('shield', 22) ?> Dokumen Legal</a>
          <a href="<?= url('akun.php') ?>"><?= icon('user', 22) ?> Akun Saya</a>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php require APP_ROOT . '/views/app_footer.php'; ?>
