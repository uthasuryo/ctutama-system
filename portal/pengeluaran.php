<?php
require __DIR__ . '/_boot.php';
$me = require_admin();

$year = (int)($_GET['tahun'] ?? date('Y'));
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
$month = (int)($_GET['bulan'] ?? 0);
if ($month < 0 || $month > 12) $month = 0;
$base = 'pengeluaran.php?tahun=' . $year . ($month ? '&bulan=' . $month : '');

/* ---------- Aksi ---------- */
if (is_post()) {
    csrf_check();
    $action = (string)input('action', '');
    if ($action === 'add_expense' || $action === 'update_expense') {
        $existing = $action === 'update_expense' ? q_one('SELECT * FROM expenses WHERE id = ? AND project_id IS NULL', [(int)input('expense_id')]) : null;
        if ($action === 'update_expense' && !$existing) redirect($base);
        [$eid, $errors] = save_expense_from_post(null, $existing);
        if ($errors) {
            flash('error', implode(' ', $errors));
            keep_old($_POST);
            redirect($base . ($existing ? '&edit=' . $existing['id'] : '&baru=1') . '#form-ops');
        }
        log_activity($existing ? 'ubah_biaya' : 'tambah_biaya', 'Operasional · ' . rupiah(parse_rupiah(input('amount'))));
        flash('success', $existing ? 'Pengeluaran diperbarui.' : 'Pengeluaran ' . rupiah(parse_rupiah(input('amount'))) . ' dicatat.');
        $d = (string)input('expense_date', date('Y-m-d'));
        redirect('pengeluaran.php?tahun=' . substr($d, 0, 4) . ($month ? '&bulan=' . (int)substr($d, 5, 2) : ''));
    }
    if ($action === 'delete_expense') {
        $ex = q_one('SELECT * FROM expenses WHERE id = ? AND project_id IS NULL', [(int)input('expense_id')]);
        if ($ex) {
            delete_expense($ex);
            log_activity('hapus_biaya', 'Operasional · ' . rupiah($ex['amount']));
            flash('success', 'Pengeluaran ' . rupiah($ex['amount']) . ' dihapus.');
        }
        redirect($base);
    }
    redirect($base);
}

/* ---------- Ekspor CSV (semua biaya setahun: operasional + proyek) ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    $rows = q_all('SELECT e.*, p.name project_name FROM expenses e LEFT JOIN projects p ON p.id = e.project_id
                   WHERE YEAR(e.expense_date) = ? ORDER BY e.expense_date, e.id', [$year]);
    // Cegah CSV injection: sel yang diawali = + - @ diberi tanda kutip tunggal
    $cell = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="biaya-ctu-' . $year . '.csv"');
    header('Cache-Control: private, no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['Tanggal', 'Jenis', 'Proyek', 'Kategori', 'Vendor', 'Keterangan', 'Nominal', 'Faktur pajak', 'PPN masukan', 'Biaya tanpa PPN berfaktur', 'Bon terlampir'], ';');
    foreach ($rows as $r) {
        $ppn = $r['has_tax_invoice'] ? (float)$r['ppn_amount'] : 0;
        fputcsv($out, array_map($cell, [
            $r['expense_date'], $r['project_id'] ? 'Proyek' : 'Operasional', $r['project_name'] ?? '', $r['category'], $r['vendor'] ?? '', $r['description'] ?? '',
            number_format((float)$r['amount'], 0, ',', ''), $r['has_tax_invoice'] ? 'Ya' : 'Tidak', number_format($ppn, 0, ',', ''),
            number_format((float)$r['amount'] - $ppn, 0, ',', ''), $r['file_path'] ? 'Ya' : 'Tidak',
        ]), ';');
    }
    fclose($out);
    log_activity('ekspor_biaya', (string)$year);
    exit;
}

/* ---------- Data ---------- */
$yearRows = q_all('SELECT * FROM expenses WHERE project_id IS NULL AND YEAR(expense_date) = ? ORDER BY expense_date DESC, id DESC', [$year]);
$rows = $month ? array_values(array_filter($yearRows, fn($r) => (int)date('n', strtotime($r['expense_date'])) === $month)) : $yearRows;
$t = expense_totals($yearRows);
$tm = expense_totals($rows);
$perMonth = array_fill(1, 12, 0.0);
foreach ($yearRows as $r) $perMonth[(int)date('n', strtotime($r['expense_date']))] += (float)$r['amount'];
$maxMonth = max($perMonth) ?: 1;
$projectCost = (float)q_val('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id IS NOT NULL AND YEAR(expense_date) = ?', [$year]);
$years = array_map('intval', array_column(q_all('SELECT DISTINCT YEAR(expense_date) y FROM expenses ORDER BY y DESC'), 'y'));
if (!in_array((int)date('Y'), $years, true)) $years[] = (int)date('Y');
if (!in_array($year, $years, true)) $years[] = $year;
rsort($years);
$edit = !empty($_GET['edit']) ? q_one('SELECT * FROM expenses WHERE id = ? AND project_id IS NULL', [(int)$_GET['edit']]) : null;
$bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$bulanPanjang = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$pageTitle = 'Pengeluaran';
$active = 'pengeluaran';
require APP_ROOT . '/views/app_header.php';
?>
<div class="toolbar">
  <label class="pickproj"><?= icon('clock', 18) ?>
    <select class="select" data-nav aria-label="Tahun">
      <?php foreach ($years as $y): ?><option value="?tahun=<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>>Tahun <?= $y ?></option><?php endforeach; ?>
    </select>
  </label>
  <a class="btn btn--line" href="?tahun=<?= $year ?>&amp;export=csv"><?= icon('download', 17) ?> Ekspor CSV</a>
  <?php if (!$edit): ?><button type="button" class="btn btn--primary" data-toggle="form-ops"><?= icon('plus', 17) ?> Catat pengeluaran</button><?php endif; ?>
</div>

<?= render_expense_form([
    'id' => 'form-ops', 'hidden' => !$edit && empty($_GET['baru']), 'class' => 'upbox--card card',
    'title' => $edit ? 'Ubah pengeluaran' : 'Catat pengeluaran operasional',
    'fields' => ['action' => $edit ? 'update_expense' : 'add_expense'] + ($edit ? ['expense_id' => $edit['id']] : []),
    'categories' => EXPENSE_CATS_OPS, 'expense' => $edit, 'cancel' => $edit ? url($base) : '',
    'vendorHint' => 'PLN, Telkomsel, toko ATK…', 'descHint' => 'mis. Token listrik kantor November',
]) ?>

<section class="kpis">
  <div class="kpi"><span class="kpi__ic" style="--c:#14222D"><?= icon('file', 20) ?></span><div><small>Operasional <?= $year ?></small><b><?= rupiah($t['total'], true) ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#29AAE3"><?= icon('check', 20) ?></span><div><small>PPN masukan berfaktur</small><b><?= rupiah($t['ppn'], true) ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#998675"><?= icon('folder', 20) ?></span><div><small>Biaya proyek <?= $year ?></small><b><?= rupiah($projectCost, true) ?></b></div></div>
  <div class="kpi"><span class="kpi__ic" style="--c:#B7791F"><?= icon('alert', 20) ?></span><div><small>Tanpa foto bon</small><b><?= $t['noReceipt'] ?></b></div></div>
</section>

<div class="cols">
  <section class="card">
    <div class="card__head">
      <h3><?= $month ? e($bulanPanjang[$month]) . ' ' . $year : 'Semua bulan ' . $year ?> <span class="count"><?= $tm['count'] ?></span></h3>
      <b><?= rupiah($tm['total']) ?></b>
    </div>
    <nav class="months" aria-label="Pilih bulan">
      <a href="?tahun=<?= $year ?>" class="<?= !$month ? 'is-on' : '' ?>">Semua</a>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <a href="?tahun=<?= $year ?>&amp;bulan=<?= $m ?>" class="<?= $month === $m ? 'is-on' : '' ?> <?= $perMonth[$m] ? '' : 'is-empty' ?>" title="<?= e(rupiah($perMonth[$m])) ?>">
          <span><?= $bulan[$m] ?></span><i style="--h:<?= round($perMonth[$m] / $maxMonth * 100) ?>%"></i>
        </a>
      <?php endfor; ?>
    </nav>
    <?php if ($rows): ?>
      <?= render_expense_list($rows, [
          'editUrl' => fn($r) => url('pengeluaran.php?tahun=' . $year . ($month ? '&bulan=' . $month : '') . '&edit=' . $r['id'] . '#form-ops'),
          'deleteFields' => ['action' => 'delete_expense'],
      ]) ?>
    <?php else: ?>
      <div class="empty"><?= icon('file', 30) ?><p><?= $month ? 'Belum ada pengeluaran di bulan ini.' : 'Belum ada pengeluaran operasional tahun ' . $year . '. Catat listrik, internet, transportasi, ATK, dan biaya administrasi di sini.' ?></p></div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <section class="card">
      <h3 class="card__title">Per kategori <?= $year ?></h3>
      <?= render_category_bars($t['cats'], $t['total']) ?: '<p class="muted small">Belum ada data.</p>' ?>
    </section>
    <section class="card">
      <h3 class="card__title">Untuk pajak tahunan</h3>
      <p class="small">Total pengeluaran operasional <?= $year ?> tanpa PPN berfaktur: <b><?= rupiah($t['net']) ?></b>.</p>
      <p class="muted small">Angka ini bisa langsung dipakai sebagai "Biaya operasional setahun" di kalkulator pajak tahunan.</p>
      <a class="btn btn--line btn--sm btn--block" href="<?= url('kalkulator.php?tab=tahunan&tahun=' . $year) ?>"><?= icon('calc', 16) ?> Buka pajak tahunan <?= $year ?></a>
    </section>
  </div>
</div>
<?php require APP_ROOT . '/views/app_footer.php'; ?>
