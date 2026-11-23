<?php
require __DIR__ . '/_boot.php';
require_admin();

$ver = app_version();
$base = dirname(APP_ROOT);                       // ~/domains/ctutama.com
$roots = [
    'app'         => APP_ROOT,
    'database'    => $base . '/database',
    'public_html' => $base . '/public_html',
    'portal'      => DOC_ROOT,                   // lokasi asli folder portal (mengikuti symlink)
];

/* ---------- Integritas file terhadap manifest paket ---------- */
$manifestFile = APP_ROOT . '/manifest.php';
$manifest = is_file($manifestFile) ? (require $manifestFile) : null;
$missing = $changed = [];
$checked = 0;
if (is_array($manifest)) {
    foreach ($manifest as $rel => $hash) {
        [$top, $rest] = array_pad(explode('/', $rel, 2), 2, '');
        if (!isset($roots[$top])) continue;
        $path = $roots[$top] . '/' . $rest;
        $checked++;
        if (!is_file($path)) $missing[] = $rel;
        elseif (sha1_file($path) !== $hash) $changed[] = $rel;
    }
}

/* ---------- Lingkungan server ---------- */
$checks = [
    ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>='), 'Butuh PHP 8.1 atau lebih baru (hPanel » Konfigurasi PHP).'],
];
foreach (['pdo_mysql' => 'database', 'fileinfo' => 'validasi file unggahan', 'gd' => 'kompres foto & thumbnail', 'zip' => 'unduh dokumen ZIP', 'mbstring' => 'teks', 'exif' => 'orientasi foto (opsional)'] as $ext => $why) {
    $checks[] = ['Ekstensi ' . $ext, extension_loaded($ext), 'Untuk ' . $why . '. Aktifkan di hPanel » Konfigurasi PHP » Ekstensi.', $ext === 'exif'];
}
$checks[] = ['Mode produksi', config('env') === 'production', "Set 'env' => 'production' di app/config.php."];
$checks[] = ['setup_key kosong', strlen((string)config('setup_key', '')) === 0, "Kosongkan 'setup_key' di app/config.php setelah instalasi.", true];
$checks[] = ['HTTPS', is_https(), 'Aktifkan SSL dan Force HTTPS di hPanel.'];
foreach (['storage/uploads' => config('storage_path'), 'uploads/covers' => config('cover_path')] as $label => $dir) {
    $checks[] = ['Folder ' . $label . ' bisa ditulis', is_dir((string)$dir) && is_writable((string)$dir), 'Periksa izin folder (755/750) lewat File Manager.'];
}
$upMax = ini_get('upload_max_filesize');
$checks[] = ['Batas unggah ' . $upMax . ' / POST ' . ini_get('post_max_size'), (int)$upMax >= 10, 'Naikkan upload_max_filesize & post_max_size di Konfigurasi PHP website portal.', true];

/* ---------- Database ---------- */
$tables = array_column(q_all('SELECT table_name t FROM information_schema.tables WHERE table_schema = DATABASE()'), 't');
$needTables = ['users', 'legal_documents', 'projects', 'project_documents', 'project_investors', 'contact_messages', 'activity_log',
               'project_calculations', 'annual_tax_inputs', 'security_events', 'media_optimized', 'project_albums', 'project_checklist', 'expenses'];
$missingTables = array_values(array_diff($needTables, $tables));

$okAll = !$missing && !$changed && !$missingTables && is_array($manifest);

$pageTitle = 'Info Sistem';
$active = 'sistem';
require APP_ROOT . '/views/app_header.php';
?>
<section class="card sysver <?= $okAll ? 'is-ok' : 'is-bad' ?>">
  <span class="sysver__ic"><?= icon($okAll ? 'check-circle' : 'alert', 26) ?></span>
  <div>
    <h3><?= $okAll ? 'Semua file sesuai dengan paket terpasang' : (is_array($manifest) ? 'Ada file yang tidak sesuai' : 'Daftar file paket tidak ditemukan') ?></h3>
    <p class="muted">Versi <b><?= e($ver['commit']) ?></b><?= $ver['date'] ? ' · dibuat ' . e(tgl($ver['date'], true)) : '' ?><?= is_array($manifest) ? ' · ' . $checked . ' file diperiksa' : '' ?></p>
  </div>
</section>

<div class="cols">
  <div class="stack">
    <section class="card">
      <h3 class="card__title">File aplikasi</h3>
      <?php if (!is_array($manifest)): ?>
        <p class="small">File <code>app/manifest.php</code> belum ada, jadi kelengkapan file tidak bisa diperiksa. Pasang <b>paket lengkap</b> terbaru sekali agar pemeriksaan ini aktif.</p>
      <?php elseif ($okAll || (!$missing && !$changed)): ?>
        <p class="small">Semua <?= $checked ?> file aplikasi ada dan isinya sama persis dengan paket versi <b><?= e($ver['commit']) ?></b>.</p>
      <?php else: ?>
        <?php if ($missing): ?>
          <p class="small"><b class="neg"><?= count($missing) ?> file tidak ada di server:</b></p>
          <ul class="syslist"><?php foreach ($missing as $f): ?><li><?= icon('x', 14) ?> <code><?= e($f) ?></code></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($changed): ?>
          <p class="small"><b style="color:var(--amber)"><?= count($changed) ?> file berbeda dari paket</b> (versi lama tertinggal atau diubah manual):</p>
          <ul class="syslist"><?php foreach ($changed as $f): ?><li><?= icon('alert', 14) ?> <code><?= e($f) ?></code></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <div class="alert alert--info"><?= icon('upload', 18) ?> <span>Cara memperbaiki: unggah <b>paket lengkap</b> (<code>ctu-deploy-….zip</code>) ke folder <code>domains/</code> lalu ekstrak dan pilih timpa. <code>config.php</code>, dokumen, dan foto yang sudah ada tidak ikut tertimpa.</span></div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="card__title">Database</h3>
      <?php if ($missingTables): ?>
        <p class="small neg">Tabel belum ada: <?= e(implode(', ', $missingTables)) ?>. Biasanya terbentuk otomatis saat login ulang; bila tetap ada, file <code>database/schema.sql</code> kemungkinan belum terunggah.</p>
      <?php else: ?>
        <p class="small">Semua <?= count($needTables) ?> tabel lengkap · MySQL/MariaDB <?= e((string)q_val('SELECT VERSION()')) ?></p>
      <?php endif; ?>
    </section>
  </div>

  <section class="card">
    <h3 class="card__title">Server</h3>
    <ul class="syschk">
      <?php foreach ($checks as $c): [$label, $ok, $fix] = $c; $optional = $c[3] ?? false; ?>
        <li class="<?= $ok ? 'ok' : ($optional ? 'warn' : 'bad') ?>">
          <?= icon($ok ? 'check' : ($optional ? 'alert' : 'x'), 16) ?>
          <div><b><?= e($label) ?></b><?php if (!$ok): ?><small><?= e($fix) ?></small><?php endif; ?></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php require APP_ROOT . '/views/app_footer.php'; ?>
