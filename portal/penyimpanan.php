<?php
require __DIR__ . '/_boot.php';
$me = require_admin();

/** Semua gambar yang belum pernah dikompres: dokumen proyek, dokumen legal, dan sampul proyek. */
function pending_images(int $limit = 500): array
{
    $rows = q_all("SELECT 'proyek' kind, d.id, d.file_path, d.mime_type, d.file_size FROM project_documents d
                   LEFT JOIN media_optimized m ON m.file_path = d.file_path
                   WHERE d.mime_type IN ('image/jpeg','image/png','image/webp') AND m.file_path IS NULL
                   UNION ALL
                   SELECT 'legal' kind, l.id, l.file_path, l.mime_type, l.file_size FROM legal_documents l
                   LEFT JOIN media_optimized m ON m.file_path = l.file_path
                   WHERE l.mime_type IN ('image/jpeg','image/png','image/webp') AND m.file_path IS NULL
                   ORDER BY file_size DESC LIMIT " . (int)$limit);
    $done = array_flip(array_column(q_all("SELECT file_path FROM media_optimized WHERE kind = 'sampul'"), 'file_path'));
    foreach (glob(rtrim((string)config('cover_path'), '/') . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) {
        $key = 'covers/' . basename($f);
        if (isset($done[$key])) continue;
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f);
        if (is_image_mime($mime)) $rows[] = ['kind' => 'sampul', 'id' => 0, 'file_path' => $key, 'mime_type' => $mime, 'file_size' => filesize($f), 'full' => $f];
    }
    return $rows;
}

function dir_size(string $dir): int
{
    if (!is_dir($dir)) return 0;
    $n = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) $n += $f->getSize();
    return $n;
}

if (is_post()) {
    csrf_check();
    if (!gd_ready()) {
        flash('error', 'Ekstensi GD tidak aktif di server. Aktifkan lewat hPanel » Konfigurasi PHP » Ekstensi.');
        redirect('penyimpanan.php');
    }
    @set_time_limit(60);
    $t0 = microtime(true);
    $done = $saved = 0;
    foreach (pending_images(200) as $r) {
        if (microtime(true) - $t0 > 12) break; // jaga agar tidak kena batas waktu hosting
        $isCover = $r['kind'] === 'sampul';
        $full = $isCover ? $r['full'] : storage_file($r['file_path']);
        $res = $full && is_file($full)
            ? optimize_image($full, $r['mime_type'], $isCover ? IMG_COVER_MAX_SIDE : IMG_DOC_MAX_SIDE, IMG_DOC_QUALITY, $isCover)
            : null;
        $before = (int)($res['before'] ?? $r['file_size']);
        $after = (int)($res['after'] ?? $r['file_size']);
        if ($res && $res['changed']) {
            if ($r['kind'] === 'proyek') q('UPDATE project_documents SET file_size = ? WHERE id = ?', [$after, $r['id']]);
            if ($r['kind'] === 'legal') q('UPDATE legal_documents SET file_size = ? WHERE id = ?', [$after, $r['id']]);
            if (!$isCover) delete_thumb($r['file_path']);
        }
        // Dicatat juga bila gagal/tidak lebih kecil, agar tidak diproses ulang terus-menerus
        q('INSERT INTO media_optimized (file_path, kind, before_size, after_size) VALUES (?,?,?,?)
           ON DUPLICATE KEY UPDATE before_size = VALUES(before_size), after_size = VALUES(after_size)', [$r['file_path'], $r['kind'], $before, $after]);
        $done++;
        $saved += max(0, $before - $after);
    }
    $left = count(pending_images());
    log_activity('kompres_foto', $done . ' file, hemat ' . filesize_h($saved));
    flash('success', $done . ' foto diproses, hemat ' . filesize_h($saved) . '.' . ($left ? ' Melanjutkan ' . $left . ' foto lagi…' : ' Semua foto sudah optimal.'));
    redirect('penyimpanan.php' . ($left ? '?lanjut=1' : ''));
}

$pending = pending_images();
$pendingSize = array_sum(array_column($pending, 'file_size'));
$stats = [
    ['Dokumen proyek', (int)q_val('SELECT COALESCE(SUM(file_size),0) FROM project_documents'), (int)q_val('SELECT COUNT(*) FROM project_documents'), 'folder'],
    ['Dokumen legal', (int)q_val('SELECT COALESCE(SUM(file_size),0) FROM legal_documents'), (int)q_val('SELECT COUNT(*) FROM legal_documents'), 'shield'],
    ['Foto / scan bon', (int)q_val('SELECT COALESCE(SUM(file_size),0) FROM expenses WHERE file_path IS NOT NULL'), (int)q_val('SELECT COUNT(*) FROM expenses WHERE file_path IS NOT NULL'), 'wallet'],
    ['Foto sampul (publik)', dir_size((string)config('cover_path')), count(glob(rtrim((string)config('cover_path'), '/') . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []), 'globe'],
    ['Thumbnail galeri', dir_size(rtrim((string)config('storage_path'), '/') . '/thumbs'), null, 'eye'],
];
$total = array_sum(array_column($stats, 1));
$opt = q_one('SELECT COUNT(*) n, COALESCE(SUM(before_size),0) b, COALESCE(SUM(after_size),0) a FROM media_optimized');
$auto = !empty($_GET['lanjut']) && $pending;

$pageTitle = 'Penyimpanan';
$active = 'penyimpanan';
require APP_ROOT . '/views/app_header.php';
?>
<section class="kpis">
  <div class="kpi"><span class="kpi__ic" style="--c:#14222D"><?= icon('folder', 20) ?></span><div><small>Total terpakai</small><b><?= filesize_h($total) ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#2F8A57"><?= icon('check', 20) ?></span><div><small>Sudah dihemat</small><b><?= filesize_h(max(0, (int)$opt['b'] - (int)$opt['a'])) ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#29AAE3"><?= icon('eye', 20) ?></span><div><small>Foto teroptimasi</small><b><?= (int)$opt['n'] ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#B7791F"><?= icon('clock', 20) ?></span><div><small>Belum dikompres</small><b><?= count($pending) ?></b></div></div>
</section>

<div class="cols">
  <section class="card">
    <div class="card__head"><h3>Kompres foto</h3></div>
    <?php if (!gd_ready()): ?>
      <div class="alert alert--error"><?= icon('alert', 18) ?> <span>Ekstensi PHP <b>GD</b> tidak aktif, jadi foto tidak bisa dikompres. Aktifkan di hPanel » Konfigurasi PHP » Ekstensi PHP.</span></div>
    <?php elseif ($pending): ?>
      <p><b><?= count($pending) ?> foto</b> (<?= filesize_h($pendingSize) ?>) belum dikompres. Foto akan:</p>
      <ul class="bullets">
        <li>diperkecil ke maks. <?= IMG_DOC_MAX_SIDE ?> px (sampul <?= IMG_COVER_MAX_SIDE ?> px) — tetap tajam untuk dibaca dan dicetak,</li>
        <li>dikompres dengan kualitas <?= IMG_DOC_QUALITY ?>% — perbedaannya tidak terlihat oleh mata,</li>
        <li>diputar tegak sesuai orientasi kamera, dan metadata (termasuk lokasi GPS) dibuang.</li>
      </ul>
      <p class="muted small">File asli hanya diganti bila hasilnya lebih kecil. Proses berjalan bertahap (±12 detik per tahap) dan berlanjut otomatis — biarkan halaman ini terbuka sampai selesai.</p>
      <form method="post" <?= $auto ? 'data-autosubmit' : '' ?>>
        <?= csrf_field() ?>
        <button class="btn btn--primary"><?= icon('check', 18) ?> <?= $auto ? 'Melanjutkan…' : 'Kompres sekarang' ?></button>
      </form>
    <?php else: ?>
      <div class="empty"><?= icon('check', 30) ?><p>Semua foto sudah optimal. Foto yang diunggah berikutnya otomatis dikompres.</p></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <h3 class="card__title">Rincian</h3>
    <dl class="kv">
      <?php foreach ($stats as [$label, $size, $n, $ic]): ?>
        <div><dt><?= e($label) ?></dt><dd><?= filesize_h($size) ?><?= $n !== null ? ' <small class="muted">· ' . $n . ' file</small>' : '' ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <p class="muted small">Foto baru otomatis dikompres saat diunggah. PDF, Word, dan Excel disimpan apa adanya.</p>
  </section>
</div>
<?php require APP_ROOT . '/views/app_footer.php'; ?>
