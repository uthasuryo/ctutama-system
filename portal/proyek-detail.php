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
$tabs = ['ringkasan' => 'Ringkasan', 'dokumen' => 'Dokumen', 'foto' => 'Foto'] + ($admin ? ['biaya' => 'Biaya'] : []);
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'ringkasan';
$self = fn(string $t = 'ringkasan', string $hash = '') => 'proyek-detail.php?id=' . $id . '&tab=' . $t . ($hash ? '#' . $hash : '');

/* =============================================================
   Aksi (admin)
   ============================================================= */
if (is_post()) {
    require_admin();
    csrf_check();
    $action = (string)input('action', '');
    $flashUpload = function (int $saved, array $errors, string $what) {
        if ($saved) flash('success', $saved . ' ' . $what . ' diunggah.');
        if ($errors) flash('error', implode(' · ', $errors));
    };

    /* ---------- Dokumen ---------- */
    if ($action === 'upload_doc') {
        $type = (string)input('doc_type', '');
        $customTypes = array_column(q_all("SELECT doc_type FROM project_checklist WHERE project_id = ? AND kind = 'custom'", [$id]), 'doc_type');
        $allowedTypes = array_diff(array_merge(project_doc_types(), $customTypes), [PHOTO_DOC_TYPE]);
        if (!in_array($type, $allowedTypes, true)) {
            flash('error', 'Pilih jenis dokumen.');
            redirect($self('dokumen'));
        }
        [$saved, $errors] = save_project_uploads($id, request_files('files'), [
            'doc_type' => $type, 'title' => input('title', ''), 'doc_date' => nullable(input('doc_date')),
            'notes' => nullable(input('notes')), 'visible' => (bool)input('visible_to_investor'),
        ]);
        if ($saved) log_activity('unggah_dok_proyek', $p['name'] . ' · ' . $type . ' (' . $saved . ' file)');
        $flashUpload($saved, $errors, 'dokumen');
        redirect($self('dokumen', (string)input('return_to', '')));
    }

    if ($action === 'delete_doc' || $action === 'toggle_doc') {
        $doc = q_one('SELECT * FROM project_documents WHERE id = ? AND project_id = ? AND album_id IS NULL', [(int)input('doc_id'), $id]);
        if ($doc && $action === 'delete_doc') {
            delete_project_doc($doc);
            log_activity('hapus_dok_proyek', $doc['title']);
            flash('success', 'Dokumen "' . $doc['title'] . '" dihapus.');
        } elseif ($doc) {
            q('UPDATE project_documents SET visible_to_investor = 1 - visible_to_investor WHERE id = ?', [$doc['id']]);
        }
        redirect($self('dokumen', (string)input('return_to', '')));
    }

    /* ---------- Checklist ---------- */
    if ($action === 'add_item') {
        $label = trim(preg_replace('/\s+/', ' ', (string)input('label', '')));
        $stage = isset(checklist_stages()[input('stage')]) ? input('stage') : 'pelaksanaan';
        if ($label === '' || mb_strlen($label) > 60) {
            flash('error', 'Nama item wajib diisi (maks. 60 karakter).');
        } elseif (in_array(mb_strtolower($label), array_map('mb_strtolower', array_merge(project_doc_types(), array_column(checklist_template($p['division']), 'type'))), true)
                  || q_val("SELECT 1 FROM project_checklist WHERE project_id = ? AND kind = 'custom' AND doc_type = ?", [$id, $label])) {
            flash('error', 'Item "' . $label . '" sudah ada di checklist atau daftar jenis dokumen.');
        } else {
            q("INSERT INTO project_checklist (project_id, doc_type, kind, stage) VALUES (?, ?, 'custom', ?)", [$id, $label, $stage]);
            log_activity('checklist_tambah', $p['name'] . ' · ' . $label);
            flash('success', 'Item "' . $label . '" ditambahkan ke checklist.');
        }
        redirect($self('dokumen'));
    }
    if ($action === 'skip_item' || $action === 'unskip_item' || $action === 'remove_item') {
        $type = mb_substr((string)input('doc_type', ''), 0, 100);
        if ($action === 'skip_item') {
            q("INSERT IGNORE INTO project_checklist (project_id, doc_type, kind) VALUES (?, ?, 'skip')", [$id, $type]);
        } elseif ($action === 'unskip_item') {
            q("DELETE FROM project_checklist WHERE project_id = ? AND doc_type = ? AND kind = 'skip'", [$id, $type]);
        } else {
            q("DELETE FROM project_checklist WHERE project_id = ? AND doc_type = ?", [$id, $type]);
            flash('success', 'Item "' . $type . '" dihapus dari checklist. Dokumennya (bila ada) tetap tersimpan di "Dokumen lain".');
        }
        redirect($self('dokumen'));
    }

    /* ---------- Album foto ---------- */
    if ($action === 'create_album') {
        $name = trim((string)input('name', ''));
        if ($name === '') {
            flash('error', 'Beri nama album, misalnya "Pengiriman barang".');
            redirect($self('foto'));
        }
        $visible = input('visible_to_investor') ? 1 : 0;
        q('INSERT INTO project_albums (project_id, name, album_date, visible_to_investor, created_by) VALUES (?,?,?,?,?)',
            [$id, mb_substr($name, 0, 150), nullable(input('album_date')) ?: date('Y-m-d'), $visible, $me['id']]);
        $aid = (int)db()->lastInsertId();
        [$saved, $errors] = save_project_uploads($id, request_files('files'), [
            'doc_type' => PHOTO_DOC_TYPE, 'title' => $name, 'album_id' => $aid, 'visible' => $visible,
            'doc_date' => nullable(input('album_date')),
        ], IMAGE_TYPES);
        if (!$saved) q('DELETE FROM project_albums WHERE id = ?', [$aid]);
        else log_activity('album_baru', $p['name'] . ' · ' . $name . ' (' . $saved . ' foto)');
        $flashUpload($saved, $errors, 'foto');
        redirect($self('foto', $saved ? 'album-' . $aid : ''));
    }

    $album = null;
    if (in_array($action, ['add_photos', 'update_album', 'toggle_album', 'delete_album'], true)) {
        $album = q_one('SELECT * FROM project_albums WHERE id = ? AND project_id = ?', [(int)input('album_id'), $id]);
        if (!$album) redirect($self('foto'));
    }
    if ($action === 'add_photos') {
        $existing = (int)q_val('SELECT COUNT(*) FROM project_documents WHERE album_id = ?', [$album['id']]);
        [$saved, $errors] = save_project_uploads($id, request_files('files'), [
            'doc_type' => PHOTO_DOC_TYPE, 'title' => $album['name'], 'album_id' => $album['id'],
            'visible' => $album['visible_to_investor'], 'doc_date' => $album['album_date'], 'number_from' => $existing,
        ], IMAGE_TYPES);
        if ($saved) log_activity('album_tambah_foto', $album['name'] . ' (' . $saved . ' foto)');
        $flashUpload($saved, $errors, 'foto');
        redirect($self('foto', 'album-' . $album['id']));
    }
    if ($action === 'update_album') {
        $name = trim((string)input('name', '')) ?: $album['name'];
        q('UPDATE project_albums SET name = ?, album_date = ? WHERE id = ?', [mb_substr($name, 0, 150), nullable(input('album_date')), $album['id']]);
        flash('success', 'Album diperbarui.');
        redirect($self('foto', 'album-' . $album['id']));
    }
    if ($action === 'toggle_album') {
        $v = $album['visible_to_investor'] ? 0 : 1;
        q('UPDATE project_albums SET visible_to_investor = ? WHERE id = ?', [$v, $album['id']]);
        q('UPDATE project_documents SET visible_to_investor = ? WHERE album_id = ?', [$v, $album['id']]);
        log_activity('album_visibilitas', $album['name'] . ($v ? ' → terlihat investor' : ' → internal'));
        flash('success', 'Album "' . $album['name'] . '" sekarang ' . ($v ? 'terlihat oleh investor.' : 'hanya untuk internal.'));
        redirect($self('foto', 'album-' . $album['id']));
    }
    if ($action === 'delete_album') {
        $photos = q_all('SELECT * FROM project_documents WHERE album_id = ?', [$album['id']]);
        foreach ($photos as $ph) delete_project_doc($ph);
        q('DELETE FROM project_albums WHERE id = ?', [$album['id']]);
        log_activity('album_hapus', $album['name'] . ' (' . count($photos) . ' foto)');
        flash('success', 'Album "' . $album['name'] . '" dan ' . count($photos) . ' fotonya dihapus.');
        redirect($self('foto'));
    }
    if ($action === 'bulk_photos') {
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['doc_ids'] ?? []))));
        $photos = $ids ? q_all('SELECT * FROM project_documents WHERE project_id = ? AND album_id IS NOT NULL AND id IN (' . implode(',', $ids) . ')', [$id]) : [];
        if (!$photos) {
            flash('error', 'Pilih foto terlebih dahulu.');
        } elseif (input('op') === 'delete') {
            foreach ($photos as $ph) delete_project_doc($ph);
            log_activity('hapus_dok_proyek', count($photos) . ' foto dari ' . $p['name']);
            flash('success', count($photos) . ' foto dihapus.');
        } elseif (input('op') === 'move') {
            $target = q_one('SELECT * FROM project_albums WHERE id = ? AND project_id = ?', [(int)input('target_album'), $id]);
            if ($target) {
                q('UPDATE project_documents SET album_id = ?, visible_to_investor = ? WHERE id IN (' . implode(',', array_column($photos, 'id')) . ')', [$target['id'], $target['visible_to_investor']]);
                flash('success', count($photos) . ' foto dipindahkan ke "' . $target['name'] . '".');
            } else {
                flash('error', 'Pilih album tujuan.');
            }
        }
        // Album yang jadi kosong ikut dihapus
        q('DELETE a FROM project_albums a LEFT JOIN project_documents d ON d.album_id = a.id WHERE a.project_id = ? AND d.id IS NULL', [$id]);
        redirect($self('foto'));
    }

    /* ---------- Biaya proyek (bon pembelian) ---------- */
    if ($action === 'add_expense' || $action === 'update_expense') {
        $existing = $action === 'update_expense' ? q_one('SELECT * FROM expenses WHERE id = ? AND project_id = ?', [(int)input('expense_id'), $id]) : null;
        if ($action === 'update_expense' && !$existing) redirect($self('biaya'));
        [$eid, $errors] = save_expense_from_post($id, $existing);
        if ($errors) {
            flash('error', implode(' ', $errors));
            keep_old($_POST);
            redirect('proyek-detail.php?id=' . $id . '&tab=biaya' . ($existing ? '&edit_biaya=' . $existing['id'] : '&baru=1') . '#form-biaya');
        }
        log_activity($existing ? 'ubah_biaya' : 'tambah_biaya', $p['name'] . ' · ' . rupiah(parse_rupiah(input('amount'))));
        flash('success', $existing ? 'Biaya diperbarui.' : 'Biaya ' . rupiah(parse_rupiah(input('amount'))) . ' dicatat.');
        redirect($self('biaya'));
    }
    if ($action === 'delete_expense') {
        $ex = q_one('SELECT * FROM expenses WHERE id = ? AND project_id = ?', [(int)input('expense_id'), $id]);
        if ($ex) {
            delete_expense($ex);
            log_activity('hapus_biaya', $p['name'] . ' · ' . rupiah($ex['amount']));
            flash('success', 'Biaya ' . rupiah($ex['amount']) . ' dihapus.');
        }
        redirect($self('biaya'));
    }
    if ($action === 'toggle_costs') {
        q('UPDATE projects SET costs_visible_to_investor = 1 - costs_visible_to_investor WHERE id = ?', [$id]);
        flash('success', $p['costs_visible_to_investor'] ? 'Total biaya disembunyikan dari investor.' : 'Total biaya sekarang terlihat oleh investor.');
        redirect($self((string)input('return_tab', 'biaya')));
    }

    /* ---------- Akses investor ---------- */
    if ($action === 'investors') {
        $ids = array_map('intval', (array)($_POST['investor_ids'] ?? []));
        q('DELETE FROM project_investors WHERE project_id = ?', [$id]);
        foreach ($ids as $uid) {
            q("INSERT IGNORE INTO project_investors (project_id, user_id) SELECT ?, id FROM users WHERE id = ? AND role = 'investor'", [$id, $uid]);
        }
        log_activity('akses_investor', $p['name'] . ' → ' . count($ids) . ' investor');
        flash('success', 'Akses investor diperbarui.');
        redirect($self('ringkasan', 'akses'));
    }
    redirect($self($tab));
}

/* =============================================================
   Data
   ============================================================= */
$fileDocs = q_all('SELECT d.*, u.name uploader FROM project_documents d LEFT JOIN users u ON u.id = d.uploaded_by
                   WHERE d.project_id = ? AND d.album_id IS NULL ORDER BY COALESCE(d.doc_date, d.created_at) DESC, d.id DESC', [$id]);
$ck = build_checklist($p, $fileDocs, $admin);

$albums = q_all('SELECT a.*, COUNT(d.id) n FROM project_albums a LEFT JOIN project_documents d ON d.album_id = a.id
                 WHERE a.project_id = ?' . ($admin ? '' : ' AND a.visible_to_investor = 1') . '
                 GROUP BY a.id HAVING n > 0 ORDER BY COALESCE(a.album_date, a.created_at) DESC, a.id DESC', [$id]);
$photos = q_all('SELECT d.id, d.album_id, d.title, d.file_size, d.created_at FROM project_documents d JOIN project_albums a ON a.id = d.album_id
                 WHERE d.project_id = ?' . ($admin ? '' : ' AND a.visible_to_investor = 1') . ' ORDER BY d.id', [$id]);
$byAlbum = [];
foreach ($photos as $ph) $byAlbum[$ph['album_id']][] = $ph;
$photoCount = count($photos);
$recent = array_slice(array_reverse($photos), 0, 8);

$expenses = q_all('SELECT * FROM expenses WHERE project_id = ? ORDER BY expense_date DESC, id DESC', [$id]);
$xt = expense_totals($expenses);
$editExpense = ($admin && !empty($_GET['edit_biaya'])) ? q_one('SELECT * FROM expenses WHERE id = ? AND project_id = ?', [(int)$_GET['edit_biaya'], $id]) : null;

$investors = $admin ? q_all("SELECT u.id, u.name, u.email, pi.user_id IS NOT NULL has_access
                             FROM users u LEFT JOIN project_investors pi ON pi.user_id = u.id AND pi.project_id = ?
                             WHERE u.role = 'investor' AND u.is_active = 1 ORDER BY u.name", [$id]) : [];
$dv = divisions()[$p['division']];
$calc = project_margin($id);
$duration = ($p['start_date'] && $p['end_date']) ? (new DateTime($p['start_date']))->diff(new DateTime($p['end_date']))->days + 1 : null;
$pct = $ck['total'] ? (int)round($ck['done'] / $ck['total'] * 100) : 0;

/* ---------- Potongan tampilan ---------- */
$hidden = fn(array $f) => implode('', array_map(fn($k, $v) => '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">', array_keys($f), $f));
$postBtn = fn(string $label, array $fields, string $class = 'dd__item', string $confirm = '') =>
    '<form method="post"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . $hidden($fields + ['project_id' => $id]) . '<button class="' . $class . '">' . $label . '</button></form>';
$fileRow = function (array $d, string $returnTo) use ($admin, $postBtn) {
    $view = url('unduh.php?t=proyek&id=' . $d['id'] . '&lihat=1');
    ob_start(); ?>
    <li class="frow">
      <span class="fi fi--sm fi--<?= file_icon($d['original_name']) ?>"><?= e(strtoupper(pathinfo($d['original_name'], PATHINFO_EXTENSION))) ?></span>
      <a class="frow__main" href="<?= $view ?>" target="_blank" rel="noopener">
        <b><?= e($d['title']) ?></b>
        <small><?= $d['doc_date'] ? tgl($d['doc_date']) : tgl($d['created_at']) ?> · <?= filesize_h((int)$d['file_size']) ?><?= $d['notes'] ? ' · ' . e($d['notes']) : '' ?></small>
      </a>
      <?php if ($admin): ?>
        <span class="vis-dot <?= $d['visible_to_investor'] ? 'is-on' : '' ?>" title="<?= $d['visible_to_investor'] ? 'Terlihat investor' : 'Hanya internal' ?>"><?= icon($d['visible_to_investor'] ? 'users' : 'lock', 14) ?></span>
      <?php endif; ?>
      <details class="dd" data-title="<?= e($d['title']) ?>">
        <summary class="iconbtn" aria-label="Opsi dokumen"><?= icon('dots', 18) ?></summary>
        <div class="dd__menu">
          <a class="dd__item" href="<?= $view ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?> Lihat</a>
          <a class="dd__item" href="<?= url('unduh.php?t=proyek&id=' . $d['id']) ?>"><?= icon('download', 16) ?> Unduh</a>
          <?php if ($admin): ?>
            <?= $postBtn(icon($d['visible_to_investor'] ? 'lock' : 'users', 16) . ($d['visible_to_investor'] ? ' Jadikan internal' : ' Tampilkan ke investor'), ['action' => 'toggle_doc', 'doc_id' => $d['id'], 'return_to' => $returnTo]) ?>
            <?= $postBtn(icon('trash', 16) . ' Hapus', ['action' => 'delete_doc', 'doc_id' => $d['id'], 'return_to' => $returnTo], 'dd__item dd__item--danger', 'Hapus dokumen "' . $d['title'] . '"?') ?>
          <?php endif; ?>
        </div>
      </details>
    </li>
    <?php return ob_get_clean();
};
$uploadForm = function (string $formId, string $docType, string $returnTo, string $titleHint = '') use ($id, $hidden) {
    ob_start(); ?>
    <form method="post" enctype="multipart/form-data" class="form upbox" id="<?= e($formId) ?>" hidden>
      <?= csrf_field() ?><?= $hidden(['action' => 'upload_doc', 'project_id' => $id, 'doc_type' => $docType, 'return_to' => $returnTo]) ?>
      <label class="drop drop--sm" data-drop>
        <input type="file" name="files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp,.zip">
        <?= icon('upload', 20) ?><b data-drop-label>Pilih file <?= e($titleHint) ?></b><small>PDF, Word, Excel, foto/scan · maks. <?= (int)config('max_upload_mb') ?> MB</small>
      </label>
      <div class="fgrid">
        <label class="f"><span>Judul (opsional)</span><input name="title" maxlength="200" placeholder="Otomatis dari nama file"></label>
        <label class="f"><span>Tanggal dokumen</span><input type="date" name="doc_date"></label>
      </div>
      <div class="upbox__foot">
        <label class="switch"><input type="checkbox" name="visible_to_investor" value="1" checked><i></i><span>Terlihat investor</span></label>
        <button class="btn btn--primary btn--sm"><?= icon('upload', 16) ?> Unggah</button>
      </div>
    </form>
    <?php return ob_get_clean();
};
$tile = fn(array $ph, bool $selectable) =>
    '<li class="ptile">' . ($selectable ? '<label class="ptile__check"><input type="checkbox" name="doc_ids[]" value="' . $ph['id'] . '" form="bulkForm" aria-label="Pilih foto"><i></i></label>' : '')
    . '<a href="' . url('unduh.php?t=proyek&id=' . $ph['id'] . '&lihat=1') . '" target="_blank" rel="noopener" data-full="' . url('unduh.php?t=proyek&id=' . $ph['id'] . '&lihat=1') . '" data-dl="' . url('unduh.php?t=proyek&id=' . $ph['id']) . '" data-caption="' . e($ph['title']) . '">'
    . '<img src="' . url('unduh.php?t=thumb&id=' . $ph['id']) . '" alt="' . e($ph['title']) . '" loading="lazy" decoding="async"></a></li>';

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

<nav class="ptabs" aria-label="Bagian proyek">
  <?php foreach ($tabs as $k => $label):
    $badge = match ($k) { 'dokumen' => $ck['done'] . '/' . $ck['total'], 'foto' => $photoCount, 'biaya' => $xt['count'] ?: '', default => '' }; ?>
    <a href="<?= url($self($k)) ?>" class="<?= $tab === $k ? 'is-on' : '' ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>>
      <?= e($label) ?><?php if ($badge !== ''): ?> <span class="ptabs__n <?= $k === 'dokumen' && $ck['done'] < $ck['total'] ? 'is-warn' : '' ?>"><?= e((string)$badge) ?></span><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<div class="cols cols--detail" data-tab="<?= e($tab) ?>">
  <div class="stack">

<?php if ($tab === 'ringkasan'): /* ======================= RINGKASAN ======================= */ ?>
    <section class="card">
      <div class="card__head">
        <h3><?= icon('check', 18) ?> Kelengkapan dokumen</h3>
        <a class="link" href="<?= url($self('dokumen')) ?>">Semua dokumen →</a>
      </div>
      <div class="kpl">
        <div class="kpl__num"><b><?= $ck['done'] ?></b><span>/<?= $ck['total'] ?></span></div>
        <div class="kpl__bar"><div class="bar <?= $ck['done'] === $ck['total'] ? 'bar--ok' : '' ?>"><i style="width:<?= $pct ?>%"></i></div><small><?= $ck['done'] === $ck['total'] ? 'Semua dokumen wajib sudah lengkap' : ($ck['total'] - $ck['done']) . ' dokumen belum ada' ?></small></div>
      </div>
      <?php if ($ck['missing']): ?>
        <ul class="miss">
          <?php foreach (array_slice($ck['missing'], 0, 5) as $m): ?>
            <li><span class="ck__ic is-missing"><?= icon('circle', 16) ?></span><span><?= e($m['label']) ?><small><?= e(checklist_stages()[$m['stage']]) ?></small></span>
              <?php if ($admin): ?><a class="link" href="<?= url($self('dokumen', $m['slug'])) ?>">Unggah</a><?php endif; ?></li>
          <?php endforeach; ?>
          <?php if (count($ck['missing']) > 5): ?><li class="muted small">dan <?= count($ck['missing']) - 5 ?> lainnya…</li><?php endif; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card__head">
        <h3><?= icon('image', 18) ?> Foto terbaru</h3>
        <?php if ($photoCount): ?><a class="link" href="<?= url($self('foto')) ?>">Lihat <?= $photoCount ?> foto →</a><?php endif; ?>
      </div>
      <?php if ($recent): ?>
        <ul class="pstrip" data-gallery><?php foreach ($recent as $ph) echo $tile($ph, false); ?></ul>
      <?php else: ?>
        <p class="muted small"><?= $admin ? 'Belum ada foto. Buat album dokumentasi di tab Foto.' : 'Belum ada foto yang dibagikan.' ?></p>
        <?php if ($admin): ?><a class="btn btn--line btn--sm" href="<?= url($self('foto')) ?>"><?= icon('plus', 16) ?> Buat album</a><?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if ($p['description']): ?>
      <section class="card"><h3 class="card__title">Deskripsi</h3><div class="prose"><?= nl2br(e($p['description'])) ?></div></section>
    <?php endif; ?>

<?php elseif ($tab === 'dokumen'): /* ======================= DOKUMEN ======================= */ ?>
    <section class="card ck">
      <div class="card__head">
        <h3>Checklist dokumen <span class="count <?= $ck['done'] < $ck['total'] ? 'count--warn' : 'count--ok' ?>"><?= $ck['done'] ?>/<?= $ck['total'] ?></span></h3>
        <?php if ($fileDocs): ?><a class="btn btn--line btn--sm" href="<?= url('unduh.php?t=zip&id=' . $id) ?>"><?= icon('download', 16) ?> <span class="hide-sm">Unduh semua</span> ZIP</a><?php endif; ?>
      </div>

      <?php $n = 0; foreach (checklist_stages() as $sk => $sl): $items = $ck['stages'][$sk]; if (!$items) continue; $n++;
        $sDone = count(array_filter($items, fn($i) => $i['count'] && !$i['skipped']));
        $sTot = count(array_filter($items, fn($i) => !$i['skipped'] && (!$i['optional'] || $i['count']))); ?>
        <div class="ck__stage">
          <h4><span class="ck__n <?= $sTot && $sDone >= $sTot ? 'is-done' : '' ?>"><?= $n ?></span> <?= e($sl) ?> <small><?= $sDone ?>/<?= $sTot ?></small></h4>
          <ul class="ck__list">
            <?php foreach ($items as $it):
              $state = $it['skipped'] ? 'skipped' : ($it['count'] ? 'done' : ($it['optional'] ? 'optional' : 'missing')); ?>
              <li class="ck__item is-<?= $state ?>" id="<?= e($it['slug']) ?>">
                <div class="ck__row">
                  <span class="ck__ic is-<?= $state ?>"><?= icon($state === 'done' ? 'check-circle' : ($state === 'skipped' ? 'minus-circle' : 'circle'), 18) ?></span>
                  <div class="ck__label">
                    <b><?= e($it['label']) ?></b>
                    <small>
                      <?php if ($state === 'skipped'): ?>Tidak diperlukan untuk proyek ini
                      <?php elseif ($it['count']): ?><?= $it['count'] ?> file<?= !$admin && $it['hidden'] ? ' · ' . $it['hidden'] . ' internal' : '' ?>
                      <?php else: ?>Belum ada<?php endif; ?>
                      <?php if ($it['optional']): ?><span class="tag">opsional</span><?php endif; ?>
                      <?php if ($it['custom']): ?><span class="tag tag--olive">tambahan</span><?php endif; ?>
                    </small>
                  </div>
                  <?php if ($admin): ?>
                    <?php if (!$it['skipped']): ?><button type="button" class="btn btn--sm <?= $it['count'] ? 'btn--ghost' : 'btn--line' ?>" data-toggle="up-<?= e($it['slug']) ?>"><?= icon($it['count'] ? 'plus' : 'upload', 15) ?><span class="hide-sm"><?= $it['count'] ? ' Tambah' : ' Unggah' ?></span></button><?php endif; ?>
                    <details class="dd" data-title="<?= e($it['label']) ?>">
                      <summary class="iconbtn" aria-label="Opsi item"><?= icon('dots', 18) ?></summary>
                      <div class="dd__menu">
                        <?php if ($it['skipped']): ?>
                          <?= $postBtn(icon('check', 16) . ' Perlukan lagi', ['action' => 'unskip_item', 'doc_type' => $it['type']]) ?>
                        <?php elseif (!$it['custom']): ?>
                          <?= $postBtn(icon('minus-circle', 16) . ' Tidak diperlukan', ['action' => 'skip_item', 'doc_type' => $it['type']]) ?>
                        <?php endif; ?>
                        <?php if ($it['custom']): ?>
                          <?= $postBtn(icon('trash', 16) . ' Hapus item', ['action' => 'remove_item', 'doc_type' => $it['type']], 'dd__item dd__item--danger', 'Hapus item "' . $it['label'] . '" dari checklist?') ?>
                        <?php endif; ?>
                      </div>
                    </details>
                  <?php endif; ?>
                </div>
                <?php if ($it['docs']): ?><ul class="flist"><?php foreach ($it['docs'] as $d) echo $fileRow($d, $it['slug']); ?></ul><?php endif; ?>
                <?php if ($admin && !$it['skipped']) echo $uploadForm('up-' . $it['slug'], $it['type'], $it['slug'], '· ' . $it['label']); ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>

      <?php if ($ck['others']): ?>
        <div class="ck__stage">
          <h4><span class="ck__n"><?= icon('folder', 13) ?></span> Dokumen lain <small><?= count($ck['others']) ?></small></h4>
          <ul class="flist flist--flat"><?php foreach ($ck['others'] as $d) echo $fileRow($d, 'lain'); ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($admin): ?>
        <div class="ck__tools">
          <button type="button" class="btn btn--line btn--sm" data-toggle="addItem"><?= icon('plus', 15) ?> Tambah item checklist</button>
          <button type="button" class="btn btn--line btn--sm" data-toggle="upOther"><?= icon('upload', 15) ?> Unggah dokumen lain</button>
        </div>
        <form method="post" class="form upbox" id="addItem" hidden>
          <?= csrf_field() ?><?= $hidden(['action' => 'add_item', 'project_id' => $id]) ?>
          <div class="fgrid">
            <label class="f"><span>Nama dokumen</span><input name="label" required maxlength="60" placeholder="Surat garansi, SP2D, dll."></label>
            <label class="f"><span>Tahap</span><select name="stage"><?php foreach (checklist_stages() as $sk => $sl): ?><option value="<?= $sk ?>" <?= $sk === 'pelaksanaan' ? 'selected' : '' ?>><?= e($sl) ?></option><?php endforeach; ?></select></label>
          </div>
          <p class="muted small">Item tambahan hanya berlaku untuk proyek ini.</p>
          <div class="upbox__foot"><span></span><button class="btn btn--primary btn--sm"><?= icon('check', 16) ?> Tambahkan</button></div>
        </form>
        <form method="post" enctype="multipart/form-data" class="form upbox" id="upOther" hidden>
          <?= csrf_field() ?><?= $hidden(['action' => 'upload_doc', 'project_id' => $id, 'return_to' => 'lain']) ?>
          <label class="f"><span>Jenis dokumen</span>
            <select name="doc_type" required><option value="">— Pilih —</option>
              <?php foreach (array_diff(project_doc_types(), [PHOTO_DOC_TYPE]) as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
            </select></label>
          <label class="drop drop--sm" data-drop><input type="file" name="files[]" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp,.zip"><?= icon('upload', 20) ?><b data-drop-label>Pilih file</b><small>Foto dokumentasi lapangan sebaiknya lewat tab Foto</small></label>
          <div class="fgrid">
            <label class="f"><span>Judul (opsional)</span><input name="title" maxlength="200"></label>
            <label class="f"><span>Tanggal dokumen</span><input type="date" name="doc_date"></label>
          </div>
          <div class="upbox__foot"><label class="switch"><input type="checkbox" name="visible_to_investor" value="1" checked><i></i><span>Terlihat investor</span></label><button class="btn btn--primary btn--sm"><?= icon('upload', 16) ?> Unggah</button></div>
        </form>
        <p class="legend"><?= icon('users', 13) ?> terlihat investor &nbsp;·&nbsp; <?= icon('lock', 13) ?> hanya internal</p>
      <?php endif; ?>
    </section>

<?php elseif ($tab === 'biaya'): /* ======================= BIAYA ======================= */
    $plan = $calc ? (float)$calc['total_cost'] : null;
    $sales = $calc ? (float)$calc['net_sales'] : (float)$p['contract_value'];
    $usage = $plan ? $xt['net'] / $plan * 100 : null; ?>
    <section class="card">
      <div class="card__head">
        <h3><?= icon('chart', 18) ?> Rencana vs aktual</h3>
        <a class="link" href="<?= url('kalkulator.php?proyek=' . $id) ?>">Kalkulator →</a>
      </div>
      <div class="xsum">
        <div><small>Biaya aktual</small><b><?= rupiah($xt['net']) ?></b><span class="muted small"><?= $xt['count'] ?> transaksi<?= $xt['ppn'] ? ' · tanpa PPN berfaktur' : '' ?></span></div>
        <div><small>Rencana (kalkulator)</small><b><?= $plan !== null ? rupiah($plan) : '–' ?></b><span class="muted small"><?= $plan !== null ? 'biaya di luar PPN masukan' : 'belum dihitung' ?></span></div>
        <div><small>PPN masukan berfaktur</small><b><?= rupiah($xt['ppn']) ?></b><span class="muted small">bisa dikreditkan</span></div>
      </div>
      <?php if ($plan): ?>
        <div class="budget">
          <div class="bar <?= $usage > 100 ? 'bar--over' : ($usage > 85 ? 'bar--warn' : 'bar--ok') ?>"><i style="width:<?= min(100, round($usage)) ?>%"></i></div>
          <p class="small"><b><?= number_format($usage, 0, ',', '.') ?>%</b> dari rencana terpakai<?= $usage > 100 ? ' — <span class="neg">melebihi rencana ' . rupiah($xt['net'] - $plan) . '</span>' : ' · sisa ' . rupiah($plan - $xt['net']) ?>.
            <?php if ($sales > 0): ?><br>Margin sebelum pajak: rencana <b><?= number_format(($sales - $plan) / $sales * 100, 1, ',', '.') ?>%</b> · dari biaya yang sudah tercatat <b class="<?= $sales - $xt['net'] < 0 ? 'neg' : '' ?>"><?= number_format(($sales - $xt['net']) / $sales * 100, 1, ',', '.') ?>%</b><?= $usage < 100 ? ' <span class="muted">(akan turun bila masih ada biaya yang belum dicatat)</span>' : '' ?>.<?php endif; ?></p>
        </div>
      <?php elseif ($xt['count']): ?>
        <p class="muted small">Hitung proyek ini di <a class="link" href="<?= url('kalkulator.php?proyek=' . $id) ?>">kalkulator margin</a> untuk membandingkan rencana dengan biaya aktual.</p>
      <?php endif; ?>
      <?= render_category_bars($xt['cats'], $xt['total']) ?>
      <?php if ($xt['noReceipt']): ?><p class="note"><?= icon('alert', 14) ?> <?= $xt['noReceipt'] ?> biaya belum ada foto bonnya.</p><?php endif; ?>
    </section>

    <section class="card">
      <div class="card__head">
        <h3>Daftar biaya <span class="count"><?= $xt['count'] ?></span></h3>
        <?php if (!$editExpense): ?><button type="button" class="btn btn--primary btn--sm" data-toggle="form-biaya"><?= icon('plus', 16) ?> Catat biaya</button><?php endif; ?>
      </div>
      <?= render_expense_form([
          'id' => 'form-biaya', 'hidden' => !$editExpense && empty($_GET['baru']), 'class' => 'upbox--flush',
          'title' => $editExpense ? 'Ubah biaya' : '',
          'fields' => ['action' => $editExpense ? 'update_expense' : 'add_expense', 'project_id' => $id] + ($editExpense ? ['expense_id' => $editExpense['id']] : []),
          'categories' => EXPENSE_CATS_PROJECT, 'expense' => $editExpense,
          'cancel' => $editExpense ? url($self('biaya')) : '',
          'vendorHint' => 'Principal, toko, ekspedisi…', 'descHint' => 'mis. 20 unit kursi kerja',
      ]) ?>
      <?php if ($expenses): ?>
        <?= render_expense_list($expenses, [
            'editUrl' => fn($r) => url('proyek-detail.php?id=' . $id . '&tab=biaya&edit_biaya=' . $r['id'] . '#form-biaya'),
            'deleteFields' => ['action' => 'delete_expense', 'project_id' => $id],
        ]) ?>
      <?php else: ?>
        <div class="empty"><?= icon('file', 30) ?><p>Belum ada biaya. Catat setiap pembelian barang, ongkos kirim, atau jasa untuk proyek ini — lengkap dengan foto bonnya.</p></div>
      <?php endif; ?>
    </section>

<?php else: /* ======================= FOTO ======================= */ ?>
    <?php if ($admin): ?>
      <div class="ftools">
        <button type="button" class="btn btn--primary" data-toggle="newAlbum"><?= icon('plus', 17) ?> Album baru</button>
        <?php if ($photoCount): ?><button type="button" class="btn btn--line" data-select-toggle><?= icon('check', 17) ?> Pilih</button><?php endif; ?>
      </div>
      <form method="post" enctype="multipart/form-data" class="card form upbox upbox--card" id="newAlbum" <?= $albums ? 'hidden' : '' ?>>
        <?= csrf_field() ?><?= $hidden(['action' => 'create_album', 'project_id' => $id]) ?>
        <h3 class="card__title">Album baru</h3>
        <div class="fgrid">
          <label class="f"><span>Nama album / kegiatan</span><input name="name" required maxlength="150" placeholder="Pengiriman barang, pemasangan, serah terima…"></label>
          <label class="f"><span>Tanggal kegiatan</span><input type="date" name="album_date" value="<?= date('Y-m-d') ?>"></label>
        </div>
        <label class="drop" data-drop><input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp"><?= icon('image', 24) ?><b data-drop-label>Pilih foto (bisa banyak sekaligus)</b><small>Foto otomatis dikompres tanpa mengurangi kualitas yang terlihat</small></label>
        <div class="upbox__foot"><label class="switch"><input type="checkbox" name="visible_to_investor" value="1" checked><i></i><span>Album terlihat investor</span></label><button class="btn btn--primary btn--sm"><?= icon('upload', 16) ?> Buat album</button></div>
      </form>
      <form method="post" id="bulkForm" class="selbar" hidden>
        <?= csrf_field() ?><?= $hidden(['action' => 'bulk_photos', 'project_id' => $id]) ?>
        <b><span data-sel-count>0</span> dipilih</b>
        <?php if (count($albums) > 1): ?>
          <select name="target_album" aria-label="Album tujuan"><option value="">Pindah ke…</option><?php foreach ($albums as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['name']) ?></option><?php endforeach; ?></select>
          <button class="btn btn--line btn--sm" name="op" value="move"><?= icon('folder', 15) ?> Pindahkan</button>
        <?php endif; ?>
        <button class="btn btn--danger btn--sm" name="op" value="delete"><?= icon('trash', 15) ?> Hapus</button>
        <button type="button" class="btn btn--ghost btn--sm" data-select-toggle>Batal</button>
      </form>
    <?php endif; ?>

    <?php if (!$albums): ?>
      <section class="card"><div class="empty"><?= icon('image', 32) ?><p><?= $admin ? 'Belum ada foto. Buat album pertama di atas, misalnya "Pengiriman barang".' : 'Belum ada foto yang dibagikan untuk proyek ini.' ?></p></div></section>
    <?php endif; ?>

    <?php foreach ($albums as $a): $list = $byAlbum[$a['id']] ?? []; ?>
      <section class="card album" id="album-<?= $a['id'] ?>">
        <div class="album__head">
          <div class="album__title">
            <h3><?= e($a['name']) ?></h3>
            <small><?= $a['album_date'] ? tgl($a['album_date']) : '' ?> · <?= count($list) ?> foto</small>
          </div>
          <?php if ($admin): ?>
            <?= $postBtn(icon($a['visible_to_investor'] ? 'users' : 'lock', 13) . ' ' . ($a['visible_to_investor'] ? 'Investor' : 'Internal'), ['action' => 'toggle_album', 'album_id' => $a['id']], 'vispill' . ($a['visible_to_investor'] ? ' is-on' : '')) ?>
            <details class="dd" data-title="<?= e($a['name']) ?>">
              <summary class="iconbtn" aria-label="Opsi album"><?= icon('dots', 18) ?></summary>
              <div class="dd__menu">
                <button type="button" class="dd__item" data-toggle="add-<?= $a['id'] ?>"><?= icon('plus', 16) ?> Tambah foto</button>
                <button type="button" class="dd__item" data-toggle="edit-<?= $a['id'] ?>"><?= icon('edit', 16) ?> Ubah nama / tanggal</button>
                <?= $postBtn(icon('trash', 16) . ' Hapus album', ['action' => 'delete_album', 'album_id' => $a['id']], 'dd__item dd__item--danger', 'Hapus album "' . $a['name'] . '" beserta ' . count($list) . ' fotonya?') ?>
              </div>
            </details>
          <?php endif; ?>
        </div>
        <?php if ($admin): ?>
          <form method="post" enctype="multipart/form-data" class="form upbox" id="add-<?= $a['id'] ?>" hidden>
            <?= csrf_field() ?><?= $hidden(['action' => 'add_photos', 'project_id' => $id, 'album_id' => $a['id']]) ?>
            <label class="drop drop--sm" data-drop><input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp"><?= icon('image', 20) ?><b data-drop-label>Tambah foto ke album ini</b></label>
            <div class="upbox__foot"><span></span><button class="btn btn--primary btn--sm"><?= icon('upload', 16) ?> Unggah</button></div>
          </form>
          <form method="post" class="form upbox" id="edit-<?= $a['id'] ?>" hidden>
            <?= csrf_field() ?><?= $hidden(['action' => 'update_album', 'project_id' => $id, 'album_id' => $a['id']]) ?>
            <div class="fgrid">
              <label class="f"><span>Nama album</span><input name="name" required maxlength="150" value="<?= e($a['name']) ?>"></label>
              <label class="f"><span>Tanggal</span><input type="date" name="album_date" value="<?= e($a['album_date']) ?>"></label>
            </div>
            <div class="upbox__foot"><span></span><button class="btn btn--primary btn--sm"><?= icon('check', 16) ?> Simpan</button></div>
          </form>
        <?php endif; ?>
        <ul class="pgrid-a <?= count($list) > 8 ? 'is-collapsed' : '' ?>" data-gallery>
          <?php foreach ($list as $ph) echo $tile($ph, $admin); ?>
        </ul>
        <?php if (count($list) > 8): ?>
          <button type="button" class="album__more" data-expand><?= icon('chevron-down', 16) ?> Tampilkan semua <?= count($list) ?> foto</button>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
<?php endif; ?>
  </div>

  <!-- ============ Kolom samping (di HP hanya tampil di tab Ringkasan) ============ -->
  <div class="stack side-info">
    <section class="card">
      <h3 class="card__title">Info proyek</h3>
      <dl class="kv">
        <div><dt>Nilai kontrak</dt><dd class="big"><?= rupiah($p['contract_value']) ?></dd></div>
        <div><dt>Nomor kontrak</dt><dd><?= e($p['contract_number'] ?: '–') ?></dd></div>
        <div><dt>Klien</dt><dd><?= e($p['client'] ?: '–') ?></dd></div>
        <div><dt>Lokasi</dt><dd><?= e($p['location'] ?: '–') ?></dd></div>
        <div><dt>Periode</dt><dd><?= tgl($p['start_date']) ?> – <?= tgl($p['end_date']) ?><?= $duration ? ' <small class="muted">(' . $duration . ' hari)</small>' : '' ?></dd></div>
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
        <a class="btn btn--line btn--block btn--sm" href="<?= url('kalkulator.php?proyek=' . $id) ?>"><?= icon('calc', 16) ?> <?= $admin ? 'Ubah perhitungan' : 'Lihat rincian' ?></a>
      <?php else: ?>
        <p class="muted small">Belum ada perhitungan margin.</p>
        <a class="btn btn--<?= $admin ? 'primary' : 'line' ?> btn--block btn--sm" href="<?= url('kalkulator.php?proyek=' . $id) ?>"><?= icon('calc', 16) ?> <?= $admin ? 'Hitung margin' : 'Coba simulasi' ?></a>
      <?php endif; ?>
    </section>

    <?php if ($admin || $p['costs_visible_to_investor']): ?>
      <section class="card">
        <div class="card__head"><h3><?= icon('file', 18) ?> Biaya proyek</h3><?php if ($admin): ?><a class="link" href="<?= url($self('biaya')) ?>">Rincian →</a><?php endif; ?></div>
        <div class="mcard__big"><?= rupiah($xt['total']) ?></div>
        <p class="muted small"><?= $admin ? $xt['count'] . ' transaksi tercatat' : 'Total biaya yang sudah dikeluarkan untuk proyek ini' ?></p>
        <?php if ($admin): ?>
          <form method="post" class="costvis">
            <?= csrf_field() ?><?= $hidden(['action' => 'toggle_costs', 'project_id' => $id, 'return_tab' => $tab]) ?>
            <button class="vispill <?= $p['costs_visible_to_investor'] ? 'is-on' : '' ?>"><?= icon($p['costs_visible_to_investor'] ? 'users' : 'lock', 13) ?> <?= $p['costs_visible_to_investor'] ? 'Total terlihat investor' : 'Hanya internal' ?></button>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($admin): ?>
      <section class="card" id="akses">
        <h3 class="card__title">Akses investor</h3>
        <?php if ($investors): ?>
          <form method="post" class="form">
            <?= csrf_field() ?><?= $hidden(['action' => 'investors', 'project_id' => $id]) ?>
            <div class="checklist">
              <?php foreach ($investors as $iv): ?>
                <label class="check"><input type="checkbox" name="investor_ids[]" value="<?= $iv['id'] ?>" <?= $iv['has_access'] ? 'checked' : '' ?>><span><b><?= e($iv['name']) ?></b><small><?= e($iv['email']) ?></small></span></label>
              <?php endforeach; ?>
            </div>
            <button class="btn btn--line btn--sm btn--block">Simpan akses</button>
          </form>
        <?php else: ?>
          <p class="muted small">Belum ada akun investor. <a class="link" href="<?= url('pengguna.php') ?>">Tambah investor →</a></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</div>

<?php require APP_ROOT . '/views/app_footer.php'; ?>
