<?php
require __DIR__ . '/_boot.php';
require_admin();

$labels = [
    'login' => 'Masuk', 'login_gagal' => 'Gagal masuk', 'login_diblokir' => 'Login diblokir (terlalu banyak percobaan)', 'instalasi' => 'Instalasi admin pertama', 'logout' => 'Keluar',
    'unggah_legal' => 'Unggah dokumen legal', 'ubah_legal' => 'Ubah dokumen legal', 'hapus_legal' => 'Hapus dokumen legal', 'unduh_legal' => 'Unduh dokumen legal',
    'tambah_proyek' => 'Tambah proyek', 'ubah_proyek' => 'Ubah proyek', 'hapus_proyek' => 'Hapus proyek',
    'unggah_dok_proyek' => 'Unggah dokumen proyek', 'hapus_dok_proyek' => 'Hapus dokumen proyek', 'unduh_dok_proyek' => 'Unduh dokumen proyek', 'unduh_zip_proyek' => 'Unduh ZIP proyek',
    'akses_investor' => 'Ubah akses investor', 'tambah_pengguna' => 'Tambah pengguna', 'ubah_pengguna' => 'Ubah pengguna',
    'hapus_pengguna' => 'Hapus pengguna', 'status_pengguna' => 'Ubah status pengguna', 'ganti_sandi' => 'Ganti kata sandi',
    'kompres_foto' => 'Kompres foto', 'tambah_biaya' => 'Catat biaya', 'ubah_biaya' => 'Ubah biaya', 'hapus_biaya' => 'Hapus biaya', 'ekspor_biaya' => 'Ekspor CSV biaya', 'album_baru' => 'Album foto baru', 'album_tambah_foto' => 'Tambah foto ke album', 'album_hapus' => 'Hapus album',
    'album_visibilitas' => 'Ubah visibilitas album', 'checklist_tambah' => 'Tambah item checklist', 'simpan_margin' => 'Simpan perhitungan margin', 'hapus_margin' => 'Hapus perhitungan margin', 'simpan_pajak_tahunan' => 'Simpan data pajak tahunan',
];
$page = max(1, (int)($_GET['hal'] ?? 1));
$per = 50;
$total = (int)q_val('SELECT COUNT(*) FROM activity_log');
$rows = q_all('SELECT a.*, u.name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$pages = max(1, (int)ceil($total / $per));

$pageTitle = 'Log Aktivitas';
$active = 'aktivitas';
require APP_ROOT . '/views/app_header.php';
?>
<section class="card card--flush">
  <?php if ($rows): ?>
  <div class="tablewrap">
    <table class="table">
      <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th><th>Detail</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="nowrap small"><?= tgl($r['created_at'], true) ?></td>
          <td class="small"><?= e($r['name'] ?: '–') ?></td>
          <td><span class="tag <?= str_contains($r['action'], 'hapus') || str_starts_with($r['action'], 'login_') ? 'tag--red' : '' ?>"><?= e($labels[$r['action']] ?? $r['action']) ?></span></td>
          <td class="small"><?= e($r['detail'] ?: '') ?></td>
          <td class="small muted"><?= e($r['ip_address'] ?: '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?><a href="?hal=<?= $i ?>" class="<?= $i === $page ? 'is-on' : '' ?>"><?= $i ?></a><?php endfor; ?>
    </nav>
  <?php endif; ?>
  <?php else: ?>
    <div class="empty"><?= icon('clock', 30) ?><p>Belum ada aktivitas.</p></div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/views/app_footer.php'; ?>
