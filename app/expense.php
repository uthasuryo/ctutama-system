<?php
declare(strict_types=1);

/* =============================================================
   Biaya: bon pembelian per proyek & pengeluaran operasional.
   Satu tabel `expenses`; project_id NULL = biaya operasional kantor.
   ============================================================= */

const EXPENSE_CATS_PROJECT = ['Pembelian barang', 'Ongkos kirim & logistik', 'Upah, jasa & subkon', 'Sewa alat', 'Lain-lain'];
const EXPENSE_CATS_OPS     = ['Utilitas', 'Transportasi', 'ATK & perlengkapan', 'Administrasi & perizinan', 'Lain-lain'];
const EXPENSE_FILE_TYPES   = [
    'pdf'  => ['application/pdf'],
    'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'], 'webp' => ['image/webp'],
];
const PPN_EFFECTIVE = 0.11; // 12% × DPP 11/12

/** PPN di dalam nominal yang sudah termasuk PPN (efektif 11%). */
function ppn_included(float $amount): float
{
    return round($amount - $amount / (1 + PPN_EFFECTIVE));
}

/**
 * Baca & simpan biaya dari formulir (tambah atau ubah).
 * @return array{0:?int,1:array} [id biaya, daftar error]
 */
function save_expense_from_post(?int $projectId, ?array $existing): array
{
    $cats = $projectId ? EXPENSE_CATS_PROJECT : EXPENSE_CATS_OPS;
    $date = nullable(input('expense_date')) ?? date('Y-m-d');
    $amount = parse_rupiah(input('amount'));
    $faktur = input('has_tax_invoice') ? 1 : 0;
    $ppn = $faktur ? (parse_rupiah(input('ppn_amount')) ?? ($amount ? ppn_included($amount) : 0)) : 0;
    $data = [
        'expense_date'    => $date,
        'category'        => (string)input('category', ''),
        'vendor'          => mb_substr(trim((string)input('vendor', '')), 0, 150) ?: null,
        'description'     => mb_substr(trim((string)input('description', '')), 0, 255) ?: null,
        'amount'          => $amount,
        'has_tax_invoice' => $faktur,
        'ppn_amount'      => $ppn,
    ];
    $errors = [];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $errors[] = 'Tanggal tidak valid.';
    if (!in_array($data['category'], $cats, true)) $errors[] = 'Pilih kategori.';
    if (!$amount || $amount <= 0) $errors[] = 'Isi nominal yang dibayar.';
    if ($ppn < 0 || ($amount && $ppn > $amount)) $errors[] = 'PPN tidak boleh melebihi nominal.';

    $file = null;
    $receipt = request_files('receipt')[0] ?? null;
    if (!$errors && $receipt) {
        [$file, $err] = store_upload($receipt, config('storage_path'), 'expenses/' . substr($date, 0, 4), EXPENSE_FILE_TYPES);
        if ($err) $errors[] = 'Bon: ' . $err;
        elseif ($file) optimize_stored_upload($file, 'bon');
    }
    if ($errors) {
        if ($file) delete_stored($file['path']);
        return [null, $errors];
    }

    if ($existing) {
        $sql = 'UPDATE expenses SET expense_date=?, category=?, vendor=?, description=?, amount=?, has_tax_invoice=?, ppn_amount=?';
        $params = array_values($data);
        if ($file) {
            $sql .= ', file_path=?, original_name=?, mime_type=?, file_size=?';
            array_push($params, $file['path'], $file['original'], $file['mime'], $file['size']);
        }
        if (input('remove_receipt') && !$file) {
            $sql .= ', file_path=NULL, original_name=NULL, mime_type=NULL, file_size=NULL';
        }
        $params[] = $existing['id'];
        q($sql . ' WHERE id = ?', $params);
        if (($file || input('remove_receipt')) && $existing['file_path']) {
            delete_stored($existing['file_path']);
            delete_thumb($existing['file_path']);
        }
        return [(int)$existing['id'], []];
    }
    q('INSERT INTO expenses (project_id, expense_date, category, vendor, description, amount, has_tax_invoice, ppn_amount, file_path, original_name, mime_type, file_size, created_by)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $projectId, ...array_values($data),
        $file['path'] ?? null, $file['original'] ?? null, $file['mime'] ?? null, $file['size'] ?? null, current_user()['id'] ?? null,
    ]);
    return [(int)db()->lastInsertId(), []];
}

function delete_expense(array $e): void
{
    q('DELETE FROM expenses WHERE id = ?', [$e['id']]);
    if ($e['file_path']) {
        delete_stored($e['file_path']);
        delete_thumb($e['file_path']);
    }
}

/** Ringkasan: total, PPN masukan berfaktur, biaya tanpa PPN berfaktur, per kategori. */
function expense_totals(array $rows): array
{
    $t = ['total' => 0.0, 'ppn' => 0.0, 'net' => 0.0, 'count' => count($rows), 'cats' => [], 'noReceipt' => 0];
    foreach ($rows as $r) {
        $t['total'] += (float)$r['amount'];
        $t['ppn'] += $r['has_tax_invoice'] ? (float)$r['ppn_amount'] : 0;
        $t['cats'][$r['category']] = ($t['cats'][$r['category']] ?? 0) + (float)$r['amount'];
        if (!$r['file_path']) $t['noReceipt']++;
    }
    $t['net'] = $t['total'] - $t['ppn']; // PPN berfaktur bisa dikreditkan, bukan biaya
    arsort($t['cats']);
    return $t;
}

/** Formulir tambah/ubah biaya. */
function render_expense_form(array $o): string
{
    $e = $o['expense'] ?? null;
    $cats = $o['categories'];
    $v = fn($k, $d = '') => old($k, $e[$k] ?? $d);
    $money = fn($n) => ($n === '' || $n === null) ? '' : number_format((float)$n, 0, ',', '.');
    ob_start(); ?>
    <form method="post" enctype="multipart/form-data" class="form upbox <?= e($o['class'] ?? '') ?>" id="<?= e($o['id']) ?>" <?= !empty($o['hidden']) ? 'hidden' : '' ?>>
      <?= csrf_field() ?>
      <?php foreach ($o['fields'] as $k => $val): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($val) ?>"><?php endforeach; ?>
      <?php if (!empty($o['title'])): ?><h3 class="card__title"><?= e($o['title']) ?></h3><?php endif; ?>
      <div class="fgrid">
        <label class="f"><span>Tanggal</span><input type="date" name="expense_date" required max="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= e($v('expense_date', date('Y-m-d'))) ?>"></label>
        <label class="f"><span>Kategori</span>
          <select name="category" required>
            <?php foreach ($cats as $c): ?><option <?= $v('category', $cats[0]) === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
          </select></label>
        <label class="f"><span>Nominal dibayar (Rp)</span><input name="amount" required inputmode="numeric" data-rupiah data-amount placeholder="0" value="<?= e($money($v('amount'))) ?>"></label>
        <label class="f"><span>Toko / vendor</span><input name="vendor" maxlength="150" value="<?= e($v('vendor')) ?>" placeholder="<?= e($o['vendorHint'] ?? 'Nama toko atau penyedia') ?>"></label>
      </div>
      <label class="f"><span>Keterangan</span><input name="description" maxlength="255" value="<?= e($v('description')) ?>" placeholder="<?= e($o['descHint'] ?? 'Untuk apa pengeluaran ini') ?>"></label>
      <div class="fakt">
        <label class="switch"><input type="checkbox" name="has_tax_invoice" value="1" data-faktur <?= $v('has_tax_invoice') ? 'checked' : '' ?>><i></i><span>Ada faktur pajak dari vendor PKP</span></label>
        <label class="f fakt__ppn" <?= $v('has_tax_invoice') ? '' : 'hidden' ?>><span>PPN di faktur (Rp)</span><input name="ppn_amount" inputmode="numeric" data-rupiah data-ppn value="<?= e($money($v('ppn_amount'))) ?>" placeholder="otomatis 11/111 dari nominal"></label>
      </div>
      <label class="drop drop--sm" data-drop>
        <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp" capture="environment">
        <?= icon('image', 20) ?><b data-drop-label><?= !empty($e['file_path']) ? 'Ganti foto bon (opsional)' : 'Foto / scan bon (opsional)' ?></b><small>Bisa langsung memotret dari HP · foto otomatis dikompres</small>
      </label>
      <?php if (!empty($e['file_path'])): ?><label class="check"><input type="checkbox" name="remove_receipt" value="1"> Hapus bon terlampir (<?= e($e['original_name']) ?>)</label><?php endif; ?>
      <div class="upbox__foot">
        <?php if (!empty($o['cancel'])): ?><a class="btn btn--ghost btn--sm" href="<?= e($o['cancel']) ?>">Batal</a><?php else: ?><span></span><?php endif; ?>
        <button class="btn btn--primary btn--sm"><?= icon('check', 16) ?> <?= $e ? 'Simpan perubahan' : 'Simpan biaya' ?></button>
      </div>
    </form>
    <?php return ob_get_clean();
}

/** Daftar biaya dengan menu ⋯ (ubah, lihat bon, hapus). */
function render_expense_list(array $rows, array $o): string
{
    ob_start(); ?>
    <ul class="xlist">
      <?php foreach ($rows as $r): ?>
        <li class="xrow">
          <span class="xrow__date"><b><?= date('j', strtotime($r['expense_date'])) ?></b><small><?= e(['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][(int)date('n', strtotime($r['expense_date']))]) ?></small></span>
          <div class="xrow__main">
            <b><?= e($r['description'] ?: $r['category']) ?></b>
            <small>
              <?= e($r['category']) ?><?= $r['vendor'] ? ' · ' . e($r['vendor']) : '' ?>
              <?php if (!empty($o['showProject']) && !empty($r['project_name'])): ?> · <a class="link" href="<?= url('proyek-detail.php?id=' . $r['project_id'] . '&tab=biaya') ?>"><?= e($r['project_name']) ?></a><?php endif; ?>
            </small>
          </div>
          <div class="xrow__amt">
            <b><?= rupiah($r['amount']) ?></b>
            <small>
              <?php if ($r['has_tax_invoice']): ?><span class="tag tag--blue" title="PPN masukan <?= e(rupiah($r['ppn_amount'])) ?>">Faktur</span><?php endif; ?>
              <?php if ($r['file_path']): ?><span class="tag" title="Bon terlampir"><?= icon('file', 11) ?> Bon</span><?php else: ?><span class="tag tag--warn" title="Belum ada foto bon">Tanpa bon</span><?php endif; ?>
            </small>
          </div>
          <details class="dd">
            <summary class="iconbtn" aria-label="Opsi biaya"><?= icon('dots', 18) ?></summary>
            <div class="dd__menu">
              <?php if ($r['file_path']): ?><a class="dd__item" href="<?= url('unduh.php?t=bon&id=' . $r['id'] . '&lihat=1') ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?> Lihat bon</a><?php endif; ?>
              <a class="dd__item" href="<?= e($o['editUrl']($r)) ?>"><?= icon('edit', 16) ?> Ubah</a>
              <form method="post" data-confirm="Hapus biaya <?= e(rupiah($r['amount'])) ?><?= $r['description'] ? ' (' . e($r['description']) . ')' : '' ?>?">
                <?= csrf_field() ?>
                <?php foreach ($o['deleteFields'] as $k => $val): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($val) ?>"><?php endforeach; ?>
                <input type="hidden" name="expense_id" value="<?= (int)$r['id'] ?>">
                <button class="dd__item dd__item--danger"><?= icon('trash', 16) ?> Hapus</button>
              </form>
            </div>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php return ob_get_clean();
}

/** Bar per kategori. */
function render_category_bars(array $cats, float $total): string
{
    if (!$cats) return '';
    ob_start(); ?>
    <ul class="catbars">
      <?php foreach ($cats as $c => $amt): ?>
        <li><span><?= e($c) ?></span><b><?= rupiah($amt) ?></b><i style="--w:<?= $total ? round($amt / $total * 100, 1) : 0 ?>%"></i></li>
      <?php endforeach; ?>
    </ul>
    <?php return ob_get_clean();
}
