<?php
require __DIR__ . '/_boot.php';
$me = require_login();
$admin = $me['role'] === 'admin';

$div = isset(divisions()[$_GET['divisi'] ?? '']) ? $_GET['divisi'] : '';
$status = isset(project_statuses()[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$search = trim((string)($_GET['q'] ?? ''));
$year = preg_match('/^\d{4}$/', (string)($_GET['tahun'] ?? '')) ? $_GET['tahun'] : '';

$where = [];
$params = [];
$join = '';
if (!$admin) {
    $join = ' JOIN project_investors pi ON pi.project_id = p.id AND pi.user_id = ?';
    $params[] = $me['id'];
}
if ($div) { $where[] = 'p.division = ?'; $params[] = $div; }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($year) { $where[] = 'YEAR(COALESCE(p.start_date, p.created_at)) = ?'; $params[] = $year; }
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.client LIKE ? OR p.code LIKE ? OR p.contract_number LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
$projects = q_all('SELECT p.*, c.net_margin, c.net_profit, (SELECT COUNT(*) FROM project_documents d WHERE d.project_id = p.id' . ($admin ? '' : ' AND d.visible_to_investor = 1') . ') docs
                   FROM projects p LEFT JOIN project_calculations c ON c.project_id = p.id' . $join . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
                   ORDER BY FIELD(p.status, \'berjalan\', \'persiapan\', \'tertunda\', \'selesai\', \'batal\'), COALESCE(p.start_date, p.created_at) DESC', $params);
$years = q_all('SELECT DISTINCT YEAR(COALESCE(start_date, created_at)) y FROM projects ORDER BY y DESC');
$sum = array_sum(array_map(fn($p) => (float)$p['contract_value'], $projects));

$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['divisi' => $div, 'status' => $status, 'q' => $search, 'tahun' => $year], $over)));

$pageTitle = 'Proyek';
$active = 'proyek';
require APP_ROOT . '/views/app_header.php';
?>
<div class="tabs">
  <a href="<?= e($qs(['divisi' => ''])) ?>" class="<?= !$div ? 'is-on' : '' ?>">Semua</a>
  <?php foreach (divisions() as $slug => $d): ?>
    <a href="<?= e($qs(['divisi' => $slug])) ?>" class="<?= $div === $slug ? 'is-on' : '' ?>" style="--dc:<?= e($d['color']) ?>"><span class="dot"></span><?= e($d['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="toolbar">
  <form class="search" method="get">
    <?= icon('search', 18) ?>
    <input name="q" placeholder="Cari nama proyek, klien, nomor kontrak…" value="<?= e($search) ?>">
    <?php foreach (['divisi' => $div, 'status' => $status, 'tahun' => $year] as $k => $v) if ($v) echo '<input type="hidden" name="' . $k . '" value="' . e($v) . '">'; ?>
  </form>
  <select class="select" data-nav>
    <option value="<?= e($qs(['status' => ''])) ?>">Semua status</option>
    <?php foreach (project_statuses() as $k => $v): ?><option value="<?= e($qs(['status' => $k])) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
  </select>
  <select class="select" data-nav>
    <option value="<?= e($qs(['tahun' => ''])) ?>">Semua tahun</option>
    <?php foreach ($years as $y): ?><option value="<?= e($qs(['tahun' => $y['y']])) ?>" <?= $year == $y['y'] ? 'selected' : '' ?>><?= e($y['y']) ?></option><?php endforeach; ?>
  </select>
  <?php if ($admin): ?><a class="btn btn--primary" href="<?= url('proyek-form.php' . ($div ? '?divisi=' . $div : '')) ?>"><?= icon('plus', 18) ?> Proyek Baru</a><?php endif; ?>
</div>

<section class="card card--flush">
  <?php if ($projects): ?>
    <div class="tablewrap">
      <table class="table table--click">
        <thead><tr><th>Proyek</th><th>Klien</th><th>Periode</th><th>Status</th><th class="r">Nilai Kontrak</th><th class="r">Net Margin</th><th class="c">Dok.</th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): $d = divisions()[$p['division']]; ?>
          <tr data-href="<?= url('proyek-detail.php?id=' . $p['id']) ?>">
            <td>
              <div class="doc">
                <span class="divmark" style="--dc:<?= e($d['color']) ?>"><?= icon($d['icon'], 17) ?></span>
                <div><a href="<?= url('proyek-detail.php?id=' . $p['id']) ?>"><b><?= e($p['name']) ?></b></a>
                  <small><?= e($p['code'] ?: $d['name']) ?><?php if ($admin && $p['is_public']): ?> · <span class="tag tag--blue">Publik</span><?php endif; ?></small></div>
              </div>
            </td>
            <td class="small"><?= e($p['client'] ?: '–') ?></td>
            <td class="nowrap small"><?= tgl($p['start_date']) ?><br><span class="muted">s.d. <?= tgl($p['end_date']) ?></span></td>
            <td><span class="status status--<?= e($p['status']) ?>"><?= e(project_statuses()[$p['status']]) ?></span>
              <?php if ($p['status'] === 'berjalan'): ?><div class="bar bar--sm"><i style="width:<?= (int)$p['progress'] ?>%"></i></div><?php endif; ?></td>
            <td class="r nowrap"><b><?= rupiah($p['contract_value']) ?></b></td>
            <td class="r nowrap"><?php if ($p['net_margin'] !== null): ?><span class="mpill <?= $p['net_profit'] < 0 ? 'mpill--neg' : '' ?>" title="Net profit <?= e(rupiah($p['net_profit'])) ?>"><?= number_format((float)$p['net_margin'], 1, ',', '.') ?>%</span><?php else: ?><a class="mpill mpill--none" href="<?= url('kalkulator.php?proyek=' . $p['id']) ?>">Hitung</a><?php endif; ?></td>
            <td class="c"><span class="count"><?= (int)$p['docs'] ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4"><?= count($projects) ?> proyek</td><td class="r nowrap"><b><?= rupiah($sum) ?></b></td><td></td><td></td></tr></tfoot>
      </table>
    </div>
  <?php else: ?>
    <div class="empty"><?= icon('folder', 32) ?><p><?= ($div || $status || $search || $year) ? 'Tidak ada proyek yang cocok dengan filter.' : ($admin ? 'Belum ada proyek. Klik "Proyek Baru" untuk menambahkan.' : 'Belum ada proyek yang dibagikan kepada Anda.') ?></p></div>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/views/app_footer.php'; ?>
