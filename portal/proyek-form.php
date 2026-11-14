<?php
require __DIR__ . '/_boot.php';
$me = require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = $id ? q_one('SELECT * FROM projects WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    flash('error', 'Proyek tidak ditemukan.');
    redirect('proyek.php');
}

if (is_post()) {
    csrf_check();
    $data = [
        'code'            => nullable(input('code')),
        'name'            => (string)input('name', ''),
        'division'        => (string)input('division', ''),
        'client'          => nullable(input('client')),
        'location'        => nullable(input('location')),
        'contract_number' => nullable(input('contract_number')),
        'contract_value'  => parse_rupiah(input('contract_value')),
        'start_date'      => nullable(input('start_date')),
        'end_date'        => nullable(input('end_date')),
        'status'          => (string)input('status', 'berjalan'),
        'progress'        => max(0, min(100, (int)input('progress', 0))),
        'summary'         => nullable(input('summary')),
        'description'     => nullable(input('description')),
        'is_public'       => input('is_public') ? 1 : 0,
    ];
    if ($data['status'] === 'selesai') $data['progress'] = 100;

    $errors = [];
    if ($data['name'] === '') $errors[] = 'Nama proyek wajib diisi.';
    if (!isset(divisions()[$data['division']])) $errors[] = 'Pilih divisi.';
    if (!isset(project_statuses()[$data['status']])) $errors[] = 'Status tidak valid.';
    if ($data['start_date'] && $data['end_date'] && $data['end_date'] < $data['start_date']) $errors[] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';

    $cover = null;
    if (!$errors && ($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$cover, $err] = store_upload($_FILES['cover'], config('cover_path'), '', IMAGE_TYPES);
        if ($err) $errors[] = 'Sampul: ' . $err;
        $coverFile = $cover ? rtrim(config('cover_path'), '/') . '/' . $cover['path'] : null;
        if ($cover && !reencode_image($coverFile, $cover['mime'])) {
            @unlink($coverFile);
            $cover = null;
            $errors[] = 'Sampul: file gambar rusak atau tidak dapat dibaca.';
        }
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
        keep_old(array_merge($data, ['contract_value' => input('contract_value')]));
        redirect('proyek-form.php' . ($id ? '?id=' . $id : ''));
    }

    $removeCover = input('remove_cover') && $p && $p['cover_image'];
    $oldCover = $p['cover_image'] ?? null;
    if ($cover) $data['cover_image'] = $cover['path'];
    elseif ($removeCover) $data['cover_image'] = null;

    $cols = array_keys($data);
    if ($id) {
        q('UPDATE projects SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($data), $id]);
        log_activity('ubah_proyek', $data['name']);
        flash('success', 'Proyek diperbarui.');
    } else {
        $data['created_by'] = $me['id'];
        $cols = array_keys($data);
        q('INSERT INTO projects (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($data));
        $id = (int)db()->lastInsertId();
        log_activity('tambah_proyek', $data['name']);
        flash('success', 'Proyek ditambahkan. Sekarang Anda bisa mengunggah dokumennya.');
    }
    if (($cover || $removeCover) && $oldCover) {
        @unlink(rtrim(config('cover_path'), '/') . '/' . basename($oldCover));
    }
    redirect('proyek-detail.php?id=' . $id);
}

$f = fn($k, $d = '') => old($k, $p[$k] ?? $d);
$cv = old('contract_value', $p && $p['contract_value'] !== null ? number_format((float)$p['contract_value'], 0, ',', '.') : '');

$pageTitle = $p ? 'Ubah Proyek' : 'Proyek Baru';
$active = 'proyek';
require APP_ROOT . '/views/app_header.php';
?>
<a class="backlink" href="<?= url($p ? 'proyek-detail.php?id=' . $p['id'] : 'proyek.php') ?>">← Kembali</a>

<form method="post" enctype="multipart/form-data" class="form formpage">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">

  <section class="card">
    <h3 class="card__title">Divisi</h3>
    <div class="divpick">
      <?php $curDiv = $f('division', $_GET['divisi'] ?? ''); foreach (divisions() as $slug => $d): ?>
        <label style="--dc:<?= e($d['color']) ?>"><input type="radio" name="division" value="<?= e($slug) ?>" required <?= $curDiv === $slug ? 'checked' : '' ?>>
          <span><?= icon($d['icon'], 24) ?><b><?= e($d['name']) ?></b></span></label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <h3 class="card__title">Informasi proyek</h3>
    <div class="fgrid fgrid--3">
      <label class="f f--span2"><span>Nama proyek *</span><input name="name" required maxlength="200" value="<?= e($f('name')) ?>"></label>
      <label class="f"><span>Kode internal</span><input name="code" maxlength="40" value="<?= e($f('code')) ?>" placeholder="CTU-PB-2026-001"></label>
      <label class="f"><span>Klien / Pemberi kerja</span><input name="client" maxlength="200" value="<?= e($f('client')) ?>"></label>
      <label class="f"><span>Lokasi</span><input name="location" maxlength="200" value="<?= e($f('location')) ?>"></label>
      <label class="f"><span>Nomor kontrak / SPK / PO</span><input name="contract_number" maxlength="120" value="<?= e($f('contract_number')) ?>"></label>
      <label class="f"><span>Nilai kontrak (Rp)</span><input name="contract_value" inputmode="numeric" data-rupiah value="<?= e($cv) ?>" placeholder="0"></label>
      <label class="f"><span>Tanggal mulai</span><input type="date" name="start_date" value="<?= e($f('start_date')) ?>"></label>
      <label class="f"><span>Tanggal selesai</span><input type="date" name="end_date" value="<?= e($f('end_date')) ?>"></label>
      <label class="f"><span>Status</span>
        <select name="status"><?php foreach (project_statuses() as $k => $v): ?><option value="<?= $k ?>" <?= $f('status', 'berjalan') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
      </label>
      <label class="f f--span2"><span>Progres: <output data-out><?= (int)$f('progress', 0) ?></output>%</span><input type="range" name="progress" min="0" max="100" step="5" value="<?= (int)$f('progress', 0) ?>" data-range></label>
    </div>
    <label class="f"><span>Deskripsi internal</span><textarea name="description" rows="4" placeholder="Lingkup pekerjaan, catatan teknis, kendala…"><?= e($f('description')) ?></textarea></label>
  </section>

  <section class="card">
    <h3 class="card__title">Tampilan di website</h3>
    <label class="switch"><input type="checkbox" name="is_public" value="1" <?= $f('is_public') ? 'checked' : '' ?>><i></i><span>Tampilkan di halaman portofolio publik</span></label>
    <p class="muted small">Yang tampil di website hanya nama, divisi, klien, lokasi, tahun, ringkasan, dan sampul. Nilai kontrak dan dokumen tidak pernah ditampilkan.</p>
    <div class="fgrid">
      <label class="f"><span>Ringkasan publik (maks. 300 karakter)</span><textarea name="summary" rows="3" maxlength="300"><?= e($f('summary')) ?></textarea></label>
      <div class="f">
        <span>Foto sampul</span>
        <?php if (!empty($p['cover_image'])): ?>
          <img class="coverprev" src="<?= e(site_url(trim((string)config('cover_url'), '/') . '/' . $p['cover_image'])) ?>" alt="">
          <label class="check"><input type="checkbox" name="remove_cover" value="1"> Hapus sampul</label>
        <?php endif; ?>
        <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp">
      </div>
    </div>
  </section>

  <div class="formbar">
    <a class="btn btn--line" href="<?= url('proyek.php') ?>">Batal</a>
    <button class="btn btn--primary"><?= icon('check', 18) ?> <?= $p ? 'Simpan Perubahan' : 'Simpan Proyek' ?></button>
  </div>
</form>

<?php require APP_ROOT . '/views/app_footer.php'; ?>
