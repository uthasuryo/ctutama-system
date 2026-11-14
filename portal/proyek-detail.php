<?php
require __DIR__ . '/_boot.php';
$me = require_login();
$admin = $me['role'] === 'admin';

$id = (int)($_GET['id'] ?? $_POST['project_id'] ?? 0);
$p = q_one('SELECT p.*, u.name creator FROM projects p LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ?', [$id]);
if (!$p || !can_view_project($id)) {
    flash('error', 'Proyek tidak ditemukan atau Anda tidak memiliki akses.');
    redirect('proyek.php');
}
$self = 'proyek-detail.php?id=' . $id;

/* ---------- Aksi admin ---------- */
if (is_post()) {
    require_admin();
    csrf_check();
    $action = (string)input('action', '');

    if ($action === 'upload_doc') {
        $title = (string)input('title', '');
        $type = (string)input('doc_type', '');
        $errors = [];
        if (!in_array($type, project_doc_types(), true)) $errors[] = 'Pilih jenis dokumen.';
        $files = $_FILES['files'] ?? null;
        $n = is_array($files['name'] ?? null) ? count($files['name']) : 0;
        if (!$errors && $n === 0) $errors[] = 'Pilih file yang akan diunggah.';
        $saved = 0;
        if (!$errors) {
            for ($i = 0; $i < $n; $i++) {
                $one = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                [$file, $err] = store_upload($one, config('storage_path'), 'projects/' . $id);
                if ($err) { $errors[] = $files['name'][$i] . ': ' . $err; continue; }
                $docTitle = $title !== '' ? ($n > 1 ? $title . ' (' . ($i + 1) . ')' : $title) : pathinfo($file['original'], PATHINFO_FILENAME);
                q('INSERT INTO project_documents (project_id, title, doc_type, doc_date, notes, visible_to_investor, file_path, original_name, mime_type, file_size, uploaded_by)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
                    $id, mb_substr($docTitle, 0, 200), $type, nullable(input('doc_date')), nullable(input('notes')),
                    input('visible_to_investor') ? 1 : 0, $file['path'], $file['original'], $file['mime'], $file['size'], $me['id'],
                ]);
                $saved++;
            }
            q('UPDATE projects SET updated_at = NOW() WHERE id = ?', [$id]);
        }
        if ($saved) {
            log_activity('unggah_dok_proyek', $p['name'] . ' (' . $saved . ' file)');
            flash('success', $saved . ' dokumen berhasil diunggah.');
        }
        if ($errors) flash('error', implode(' · ', $errors));
        redirect($self . '#dokumen');
    }

    if ($action === 'delete_doc') {
        $doc = q_one('SELECT * FROM project_documents WHERE id = ? AND project_id = ?', [(int)input('doc_id'), $id]);
        if ($doc) {
            q('DELETE FROM project_documents WHERE id = ?', [$doc['id']]);
            delete_stored($doc['file_path']);
            log_activity('hapus_dok_proyek', $doc['title']);
            flash('success', 'Dokumen dihapus.');
        }
        redirect($self . '#dokumen');
    }

    if ($action === 'toggle_doc') {
        q('UPDATE project_documents SET visible_to_investor = 1 - visible_to_investor WHERE id = ? AND project_id = ?', [(int)input('doc_id'), $id]);
        redirect($self . '#dokumen');
    }

    if ($action === 'investors') {
        $ids = array_map('intval', (array)($_POST['investor_ids'] ?? []));
        q('DELETE FROM project_investors WHERE project_id = ?', [$id]);
        foreach ($ids as $uid) {
            q("INSERT IGNORE INTO project_investors (project_id, user_id) SELECT ?, id FROM users WHERE id = ? AND role = 'investor'", [$id, $uid]);
        }
        log_activity('akses_investor', $p['name'] . ' → ' . count($ids) . ' investor');
        flash('success', 'Akses investor diperbarui.');
        redirect($self . '#akses');
    }

    if ($action === 'delete_project') {
        if (input('confirm_name') !== $p['name']) {
            flash('error', 'Nama proyek yang diketik tidak sama. Proyek tidak dihapus.');
            redirect($self . '#bahaya');
        }
        $docs = q_all('SELECT file_path FROM project_documents WHERE project_id = ?', [$id]);
        q('DELETE FROM projects WHERE id = ?', [$id]);
        foreach ($docs as $d) delete_stored($d['file_path']);
        @rmdir(config('storage_path') . '/projects/' . $id);
        if ($p['cover_image']) @unlink(rtrim(config('cover_path'), '/') . '/' . basename($p['cover_image']));
        log_activity('hapus_proyek', $p['name']);
        flash('success', 'Proyek "' . $p['name'] . '" beserta dokumennya dihapus.');
        redirect('proyek.php');
    }
    redirect($self);
}

/* ---------- Data ---------- */
$docs = q_all('SELECT d.*, u.name uploader FROM project_documents d LEFT JOIN users u ON u.id = d.uploaded_by
               WHERE d.project_id = ?' . ($admin ? '' : ' AND d.visible_to_investor = 1') . ' ORDER BY d.doc_type, COALESCE(d.doc_date, d.created_at) DESC', [$id]);
$grouped = [];
foreach ($docs as $d) $grouped[$d['doc_type']][] = $d;
$investors = $admin ? q_all("SELECT u.id, u.name, u.email, pi.user_id IS NOT NULL has_access
                             FROM users u LEFT JOIN project_investors pi ON pi.user_id = u.id AND pi.project_id = ?
                             WHERE u.role = 'investor' AND u.is_active = 1 ORDER BY u.name", [$id]) : [];
$dv = divisions()[$p['division']];
$calc = project_margin($id);
$duration = ($p['start_date'] && $p['end_date']) ? (new DateTime($p['start_date']))->diff(new DateTime($p['end_date']))->days + 1 : null;

$pageTitle = 'Detail Proyek';
$active = 'proyek';
require APP_ROOT . '/views/app_header.php';
?>
<a class="backlink" href="<?= url('proyek.php') ?>">← Semua proyek</a>

<section class="phero" style="--dc:<?= e($dv['color']) ?>">
  <div class="phero__main">
    <div class="phero__tags">
      <span class="divtag"><?= icon($dv['icon'], 15) ?> <?= e($dv['name']) ?></span>
      <span class="status status--<?= e($p['status']) ?>"><?= e(project_statuses()[$p['status']]) ?></span>
      <?php if ($admin && $p['is_public']): ?><span class="tag tag--blue"><?= icon('globe', 13) ?> Tampil di website</span><?php endif; ?>
    </div>
    <h2><?= e($p['name']) ?></h2>
    <p class="muted"><?= e($p['code'] ?: '') ?><?= $p['code'] && $p['client'] ? ' · ' : '' ?><?= e($p['client'] ?: '') ?></p>
    <div class="progress"><div class="bar"><i style="width:<?= (int)$p['progress'] ?>%"></i></div><b><?= (int)$p['progress'] ?>%</b></div>
  </div>
  <?php if ($admin): ?>
    <div class="phero__act"><a class="btn btn--line" href="<?= url('proyek-form.php?id=' . $id) ?>"><?= icon('edit', 17) ?> Ubah</a></div>
  <?php endif; ?>
</section>

<div class="cols cols--detail">
  <div class="stack">
    <section class="card" id="dokumen">
      <div class="card__head"><h3>Dokumen proyek <span class="count"><?= count($docs) ?></span></h3></div>
      <?php if ($grouped): ?>
        <?php foreach ($grouped as $type => $list): ?>
          <div class="dgroup">
            <h4><?= e($type) ?> <small><?= count($list) ?></small></h4>
            <ul class="dlist">
              <?php foreach ($list as $d): ?>
                <li>
                  <span class="fi fi--<?= file_icon($d['original_name']) ?>"><?= strtoupper(pathinfo($d['original_name'], PATHINFO_EXTENSION)) ?></span>
                  <div class="dlist__main">
                    <b><?= e($d['title']) ?></b>
                    <small><?= $d['doc_date'] ? tgl($d['doc_date']) . ' · ' : '' ?><?= filesize_h((int)$d['file_size']) ?><?= $admin ? ' · ' . e($d['uploader'] ?? '') : '' ?><?= $d['notes'] ? ' · ' . e($d['notes']) : '' ?></small>
                  </div>
                  <div class="actions">
                    <?php if ($admin): ?>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_doc"><input type="hidden" name="doc_id" value="<?= $d['id'] ?>"><input type="hidden" name="project_id" value="<?= $id ?>">
                        <button class="vis <?= $d['visible_to_investor'] ? 'is-on' : '' ?>" title="<?= $d['visible_to_investor'] ? 'Terlihat oleh investor — klik untuk sembunyikan' : 'Tersembunyi dari investor — klik untuk tampilkan' ?>"><?= icon($d['visible_to_investor'] ? 'eye' : 'lock', 15) ?></button></form>
                    <?php endif; ?>
                    <a class="iconbtn" title="Lihat" target="_blank" href="<?= url('unduh.php?t=proyek&id=' . $d['id'] . '&lihat=1') ?>"><?= icon('eye', 17) ?></a>
                    <a class="iconbtn" title="Unduh" href="<?= url('unduh.php?t=proyek&id=' . $d['id']) ?>"><?= icon('download', 17) ?></a>
                    <?php if ($admin): ?>
                      <form method="post" data-confirm="Hapus dokumen &quot;<?= e($d['title']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_doc"><input type="hidden" name="doc_id" value="<?= $d['id'] ?>"><input type="hidden" name="project_id" value="<?= $id ?>">
                        <button class="iconbtn iconbtn--danger" title="Hapus"><?= icon('trash', 17) ?></button></form>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
        <?php if (count($docs) > 1): ?>
          <a class="btn btn--line btn--sm" href="<?= url('unduh.php?t=zip&id=' . $id) ?>"><?= icon('download', 16) ?> Unduh semua (ZIP)</a>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty"><?= icon('file', 30) ?><p>Belum ada dokumen untuk proyek ini.</p></div>
      <?php endif; ?>

      <?php if ($admin): ?>
        <form method="post" enctype="multipart/form-data" class="form upform">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload_doc"><input type="hidden" name="project_id" value="<?= $id ?>">
          <h4><?= icon('upload', 18) ?> Unggah dokumen</h4>
          <div class="fgrid fgrid--3">
            <label class="f"><span>Jenis *</span><select name="doc_type" required><option value="">— Pilih —</option><?php foreach (project_doc_types() as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></label>
            <label class="f"><span>Judul</span><input name="title" maxlength="200" placeholder="Otomatis dari nama file"></label>
            <label class="f"><span>Tanggal dokumen</span><input type="date" name="doc_date"></label>
          </div>
          <label class="f"><span>Catatan</span><input name="notes" maxlength="300"></label>
          <label class="drop" data-drop>
            <input type="file" name="files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp,.zip">
            <?= icon('upload', 26) ?><b data-drop-label>Pilih atau seret file (bisa lebih dari satu)</b>
            <small>PDF, Word, Excel, gambar, ZIP · maks. <?= (int)config('max_upload_mb') ?> MB per file</small>
          </label>
          <div class="upform__foot">
            <label class="switch"><input type="checkbox" name="visible_to_investor" value="1" checked><i></i><span>Terlihat oleh investor proyek ini</span></label>
            <button class="btn btn--primary"><?= icon('upload', 18) ?> Unggah</button>
          </div>
        </form>
      <?php endif; ?>
    </section>

    <?php if ($p['description']): ?>
      <section class="card"><h3 class="card__title">Deskripsi</h3><div class="prose"><?= nl2br(e($p['description'])) ?></div></section>
    <?php endif; ?>
  </div>

  <div class="stack">
    <section class="card">
      <h3 class="card__title">Ringkasan</h3>
      <dl class="kv">
        <div><dt>Nilai kontrak</dt><dd class="big"><?= rupiah($p['contract_value']) ?></dd></div>
        <div><dt>Nomor kontrak</dt><dd><?= e($p['contract_number'] ?: '–') ?></dd></div>
        <div><dt>Klien</dt><dd><?= e($p['client'] ?: '–') ?></dd></div>
        <div><dt>Lokasi</dt><dd><?= e($p['location'] ?: '–') ?></dd></div>
        <div><dt>Mulai</dt><dd><?= tgl($p['start_date']) ?></dd></div>
        <div><dt>Selesai</dt><dd><?= tgl($p['end_date']) ?><?= $duration ? ' <small class="muted">(' . $duration . ' hari)</small>' : '' ?></dd></div>
        <?php if ($admin): ?><div><dt>Dibuat oleh</dt><dd><?= e($p['creator'] ?: '–') ?> · <?= tgl($p['created_at']) ?></dd></div><?php endif; ?>
      </dl>
    </section>

    <section class="card mcard">
      <div class="card__head"><h3><?= icon('calc', 18) ?> Margin &amp; net profit</h3>
        <?php if ($calc): ?><span class="mpill <?= $calc['net_profit'] < 0 ? 'mpill--neg' : '' ?>"><?= $calc['net_margin'] !== null ? number_format((float)$calc['net_margin'], 1, ',', '.') . '%' : '–' ?></span><?php endif; ?>
      </div>
      <?php if ($calc): ?>
        <small class="muted">Net profit (setelah PPh)</small>
        <div class="mcard__big <?= $calc['net_profit'] < 0 ? 'neg' : '' ?>"><?= rupiah($calc['net_profit']) ?></div>
        <div class="mcard__grid">
          <div><small>Penjualan (DPP)</small><b><?= rupiah($calc['net_sales']) ?></b></div>
          <div><small>Total biaya</small><b><?= rupiah($calc['total_cost']) ?></b></div>
          <div><small>Laba sblm pajak</small><b class="<?= $calc['ebt'] < 0 ? 'neg' : '' ?>"><?= rupiah($calc['ebt']) ?></b></div>
          <div><small><?= $calc['is_final'] ? 'PPh final' : 'PPh' ?></small><b><?= rupiah($calc['pph']) ?></b></div>
        </div>
        <p class="muted small">Tahun pajak <?= (int)$calc['tax_year'] ?> · diperbarui <?= tgl($calc['updated_at']) ?></p>
        <a class="btn btn--line btn--block btn--sm" href="<?= url('kalkulator.php?proyek=' . $id) ?>"><?= icon('calc', 16) ?> <?= $admin ? 'Ubah perhitungan' : 'Lihat rincian perhitungan' ?></a>
      <?php else: ?>
        <p class="muted small">Belum ada perhitungan margin untuk proyek ini.</p>
        <a class="btn btn--<?= $admin ? 'primary' : 'line' ?> btn--block btn--sm" href="<?= url('kalkulator.php?proyek=' . $id) ?>"><?= icon('calc', 16) ?> <?= $admin ? 'Hitung margin sekarang' : 'Coba simulasi' ?></a>
      <?php endif; ?>
    </section>

    <?php if ($admin): ?>
      <section class="card" id="akses">
        <h3 class="card__title">Akses investor</h3>
        <?php if ($investors): ?>
          <form method="post" class="form">
            <?= csrf_field() ?><input type="hidden" name="action" value="investors"><input type="hidden" name="project_id" value="<?= $id ?>">
            <div class="checklist">
              <?php foreach ($investors as $iv): ?>
                <label class="check"><input type="checkbox" name="investor_ids[]" value="<?= $iv['id'] ?>" <?= $iv['has_access'] ? 'checked' : '' ?>><span><b><?= e($iv['name']) ?></b><small><?= e($iv['email']) ?></small></span></label>
              <?php endforeach; ?>
            </div>
            <button class="btn btn--primary btn--sm btn--block">Simpan akses</button>
          </form>
        <?php else: ?>
          <p class="muted small">Belum ada akun investor. <a class="link" href="<?= url('pengguna.php') ?>">Tambah investor →</a></p>
        <?php endif; ?>
      </section>

      <section class="card card--danger" id="bahaya">
        <h3 class="card__title">Hapus proyek</h3>
        <p class="small muted">Semua dokumen proyek akan ikut terhapus permanen. Ketik nama proyek untuk konfirmasi.</p>
        <form method="post" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete_project"><input type="hidden" name="project_id" value="<?= $id ?>">
          <label class="f"><input name="confirm_name" placeholder="<?= e($p['name']) ?>" autocomplete="off"></label>
          <button class="btn btn--danger btn--sm btn--block"><?= icon('trash', 16) ?> Hapus proyek</button>
        </form>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php require APP_ROOT . '/views/app_footer.php'; ?>
