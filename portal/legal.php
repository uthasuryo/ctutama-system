<?php
require __DIR__ . '/_boot.php';
$me = require_login();
$admin = $me['role'] === 'admin';

/* ---------- Aksi (admin) ---------- */
if (is_post()) {
    require_admin();
    csrf_check();
    $action = (string)input('action', '');

    if ($action === 'delete') {
        $doc = q_one('SELECT * FROM legal_documents WHERE id = ?', [(int)input('id')]);
        if ($doc) {
            q('DELETE FROM legal_documents WHERE id = ?', [$doc['id']]);
            delete_stored($doc['file_path']);
            log_activity('hapus_legal', $doc['title']);
            flash('success', 'Dokumen "' . $doc['title'] . '" dihapus.');
        }
        redirect('legal.php');
    }

    if ($action === 'save') {
        $id = (int)input('id', 0);
        $data = [
            'title'       => (string)input('title', ''),
            'category'    => (string)input('category', ''),
            'doc_number'  => nullable(input('doc_number')),
            'issuer'      => nullable(input('issuer')),
            'issued_date' => nullable(input('issued_date')),
            'expiry_date' => nullable(input('expiry_date')),
            'notes'       => nullable(input('notes')),
            'visible_to_investor' => input('visible_to_investor') ? 1 : 0,
        ];
        $errors = [];
        if ($data['title'] === '') $errors[] = 'Judul dokumen wajib diisi.';
        if (!in_array($data['category'], legal_categories(), true)) $errors[] = 'Pilih kategori dokumen.';

        $file = null;
        $upFile = request_files('file')[0] ?? null;
        $hasFile = $upFile !== null;
        if (!$errors && ($hasFile || !$id)) {
            [$file, $err] = store_upload($upFile, config('storage_path'), 'legal/' . date('Y'));
            if ($err) $errors[] = $err;
            if ($file) optimize_stored_upload($file, 'legal');
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            keep_old($data);
            redirect('legal.php' . ($id ? '?edit=' . $id : '') . '#unggah');
        }

        if ($id) {
            $old = q_one('SELECT * FROM legal_documents WHERE id = ?', [$id]);
            if (!$old) redirect('legal.php');
            $sql = 'UPDATE legal_documents SET title=?, category=?, doc_number=?, issuer=?, issued_date=?, expiry_date=?, notes=?, visible_to_investor=?';
            $params = array_values($data);
            if ($file) {
                $sql .= ', file_path=?, original_name=?, mime_type=?, file_size=?';
                array_push($params, $file['path'], $file['original'], $file['mime'], $file['size']);
            }
            $params[] = $id;
            q($sql . ' WHERE id=?', $params);
            if ($file) delete_stored($old['file_path']);
            log_activity('ubah_legal', $data['title']);
            flash('success', 'Dokumen legal diperbarui.');
        } else {
            q('INSERT INTO legal_documents (title, category, doc_number, issuer, issued_date, expiry_date, notes, visible_to_investor, file_path, original_name, mime_type, file_size, uploaded_by)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [...array_values($data), $file['path'], $file['original'], $file['mime'], $file['size'], $me['id']]);
            log_activity('unggah_legal', $data['title']);
            flash('success', 'Dokumen legal berhasil diunggah.');
        }
        redirect('legal.php');
    }
}

/* ---------- Data ---------- */
$cat = (string)($_GET['kategori'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];
if (!$admin) $where[] = 'visible_to_investor = 1';
if ($cat !== '') { $where[] = 'category = ?'; $params[] = $cat; }
if ($search !== '') { $where[] = '(title LIKE ? OR doc_number LIKE ? OR issuer LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
$docs = q_all('SELECT * FROM legal_documents' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY category, COALESCE(issued_date, created_at) DESC', $params);
$counts = q_all('SELECT category, COUNT(*) n FROM legal_documents' . ($admin ? '' : ' WHERE visible_to_investor = 1') . ' GROUP BY category');
$counts = array_column($counts, 'n', 'category');

$edit = null;
if ($admin && isset($_GET['edit'])) {
    $edit = q_one('SELECT * FROM legal_documents WHERE id = ?', [(int)$_GET['edit']]);
}
$f = fn($k, $d = '') => old($k, $edit[$k] ?? $d);

$pageTitle = 'Dokumen Legal';
$active = 'legal';
require APP_ROOT . '/views/app_header.php';
?>
<div class="toolbar">
  <form class="search" method="get">
    <?= icon('search', 18) ?>
    <input name="q" placeholder="Cari judul, nomor, penerbit…" value="<?= e($search) ?>">
    <?php if ($cat): ?><input type="hidden" name="kategori" value="<?= e($cat) ?>"><?php endif; ?>
  </form>
  <select class="select" data-nav aria-label="Filter kategori">
    <option value="?">Semua kategori (<?= array_sum($counts) ?>)</option>
    <?php foreach (legal_categories() as $c): ?>
      <option value="?kategori=<?= e(rawurlencode($c)) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?> (<?= (int)($counts[$c] ?? 0) ?>)</option>
    <?php endforeach; ?>
  </select>
  <?php if ($admin): ?><a class="btn btn--primary" href="#unggah"><?= icon('upload', 18) ?> Unggah</a><?php endif; ?>
</div>

<div class="cols cols--legal">
  <section class="card card--flush">
    <?php if ($docs): ?>
      <div class="tablewrap">
        <table class="table">
          <thead><tr><th>Dokumen</th><th>Nomor / Penerbit</th><th>Tanggal</th><th>Berlaku s.d.</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($docs as $d):
            $days = $d['expiry_date'] ? (int)floor((strtotime($d['expiry_date']) - strtotime('today')) / 86400) : null; ?>
            <tr>
              <td>
                <div class="doc">
                  <span class="fi fi--<?= file_icon($d['original_name']) ?>"><?= strtoupper(pathinfo($d['original_name'], PATHINFO_EXTENSION)) ?></span>
                  <div><b><?= e($d['title']) ?></b><small><?= e($d['category']) ?> · <?= filesize_h((int)$d['file_size']) ?>
                    <?php if ($admin && $d['visible_to_investor']): ?> · <span class="tag">Investor</span><?php endif; ?></small></div>
                </div>
              </td>
              <td><span class="small"><?= e($d['doc_number'] ?: '–') ?></span><br><small class="muted"><?= e($d['issuer'] ?: '') ?></small></td>
              <td class="nowrap small"><?= tgl($d['issued_date']) ?></td>
              <td class="nowrap">
                <?php if ($days === null): ?><span class="muted small">Tidak terbatas</span>
                <?php elseif ($days < 0): ?><span class="pill pill--red">Kedaluwarsa</span>
                <?php elseif ($days <= 60): ?><span class="pill pill--amber"><?= tgl($d['expiry_date']) ?></span>
                <?php else: ?><span class="small"><?= tgl($d['expiry_date']) ?></span><?php endif; ?>
              </td>
              <td class="actions">
                <a class="iconbtn" title="Lihat" target="_blank" href="<?= url('unduh.php?t=legal&id=' . $d['id'] . '&lihat=1') ?>"><?= icon('eye', 17) ?></a>
                <a class="iconbtn" title="Unduh" href="<?= url('unduh.php?t=legal&id=' . $d['id']) ?>"><?= icon('download', 17) ?></a>
                <?php if ($admin): ?>
                  <a class="iconbtn" title="Ubah" href="?edit=<?= $d['id'] ?>#unggah"><?= icon('edit', 17) ?></a>
                  <form method="post" data-confirm="Hapus dokumen &quot;<?= e($d['title']) ?>&quot;? File akan dihapus permanen.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <button class="iconbtn iconbtn--danger" title="Hapus"><?= icon('trash', 17) ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty"><?= icon('shield', 32) ?><p><?= $search || $cat ? 'Tidak ada dokumen yang cocok dengan filter.' : ($admin ? 'Belum ada dokumen legal. Unggah akta, NIB, NPWP, dan lainnya di formulir samping.' : 'Belum ada dokumen legal yang dibagikan.') ?></p></div>
    <?php endif; ?>
  </section>

  <?php if ($admin): ?>
  <section class="card" id="unggah">
    <div class="card__head"><h3><?= $edit ? 'Ubah dokumen' : 'Unggah dokumen legal' ?></h3><?php if ($edit): ?><a class="link" href="<?= url('legal.php') ?>">Batal</a><?php endif; ?></div>
    <form method="post" enctype="multipart/form-data" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label class="f"><span>Judul dokumen *</span><input name="title" required maxlength="200" value="<?= e($f('title')) ?>" placeholder="mis. Akta Perubahan No. 21"></label>
      <label class="f"><span>Kategori *</span>
        <select name="category" required>
          <option value="">— Pilih —</option>
          <?php foreach (legal_categories() as $c): ?><option <?= $f('category') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="fgrid">
        <label class="f"><span>Nomor dokumen</span><input name="doc_number" maxlength="120" value="<?= e($f('doc_number')) ?>"></label>
        <label class="f"><span>Penerbit</span><input name="issuer" maxlength="160" value="<?= e($f('issuer')) ?>" placeholder="Notaris / OSS / DJP"></label>
      </div>
      <div class="fgrid">
        <label class="f"><span>Tanggal terbit</span><input type="date" name="issued_date" value="<?= e($f('issued_date')) ?>"></label>
        <label class="f"><span>Berlaku sampai</span><input type="date" name="expiry_date" data-after="issued_date" data-presets="1,2,3,5" value="<?= e($f('expiry_date')) ?>"></label>
      </div>
      <label class="f"><span>Catatan</span><textarea name="notes" rows="2"><?= e($f('notes')) ?></textarea></label>
      <label class="drop" data-drop>
        <input type="file" name="file" <?= $edit ? '' : 'required' ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.zip">
        <?= icon('upload', 26) ?>
        <b data-drop-label><?= $edit ? 'Ganti file (opsional)' : 'Pilih atau seret file ke sini' ?></b>
        <small>PDF, Word, Excel, gambar, ZIP · maks. <?= (int)config('max_upload_mb') ?> MB</small>
        <?php if ($edit): ?><small class="muted">File saat ini: <?= e($edit['original_name']) ?></small><?php endif; ?>
      </label>
      <label class="switch"><input type="checkbox" name="visible_to_investor" value="1" <?= $f('visible_to_investor') ? 'checked' : '' ?>><i></i><span>Tampilkan ke investor</span></label>
      <button class="btn btn--primary btn--block"><?= icon($edit ? 'check' : 'upload', 18) ?> <?= $edit ? 'Simpan Perubahan' : 'Unggah Dokumen' ?></button>
    </form>
  </section>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/views/app_footer.php'; ?>
