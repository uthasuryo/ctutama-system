<?php
require __DIR__ . '/_boot.php';
$me = require_login();
$admin = $me['role'] === 'admin';

/* ---------- Aksi simpan (admin) ---------- */
if (is_post()) {
    require_admin();
    csrf_check();
    $action = (string)input('action', '');

    if ($action === 'save_calc') {
        $pid = (int)input('project_id', 0);
        $p = q_one('SELECT * FROM projects WHERE id = ?', [$pid]);
        $raw = json_decode((string)($_POST['inputs'] ?? ''), true);
        if (!$p || !is_array($raw)) {
            flash('error', 'Data perhitungan tidak valid.');
            redirect('kalkulator.php');
        }
        $in = margin_normalize($raw);
        $in['name'] = $p['name'];
        $c = margin_calc($in);
        q('INSERT INTO project_calculations (project_id, tax_year, inputs, net_sales, total_cost, gross_profit, ebt, pph, net_profit, net_margin, withholding, ppn_out, ppn_in, ppn_by_client, is_final, updated_by)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
           ON DUPLICATE KEY UPDATE tax_year=VALUES(tax_year), inputs=VALUES(inputs), net_sales=VALUES(net_sales), total_cost=VALUES(total_cost),
             gross_profit=VALUES(gross_profit), ebt=VALUES(ebt), pph=VALUES(pph), net_profit=VALUES(net_profit), net_margin=VALUES(net_margin),
             withholding=VALUES(withholding), ppn_out=VALUES(ppn_out), ppn_in=VALUES(ppn_in), ppn_by_client=VALUES(ppn_by_client),
             is_final=VALUES(is_final), updated_by=VALUES(updated_by), updated_at=NOW()', [
            $pid, $in['year'], json_encode($in, JSON_UNESCAPED_UNICODE), round($c['net'], 2), round($c['cost'], 2), round($c['gp'], 2),
            round($c['ebt'], 2), round($c['pph'], 2), round($c['np'], 2), $c['npm'] !== null ? round($c['npm'], 4) : null,
            round($c['wh'], 2), round($c['ppnOut'], 2), round($c['ppnIn'], 2), $c['pem'] ? 1 : 0, $c['kon'] ? 1 : 0, $me['id'],
        ]);
        log_activity('simpan_margin', $p['name'] . ' · net ' . rupiah($c['np']));
        flash('success', 'Perhitungan margin "' . $p['name'] . '" disimpan (tahun pajak ' . $in['year'] . ').');
        redirect('kalkulator.php?proyek=' . $pid);
    }

    if ($action === 'delete_calc') {
        $pid = (int)input('project_id', 0);
        q('DELETE FROM project_calculations WHERE project_id = ?', [$pid]);
        log_activity('hapus_margin', (string)$pid);
        flash('success', 'Perhitungan margin proyek dihapus.');
        redirect('kalkulator.php?proyek=' . $pid);
    }

    if ($action === 'save_annual') {
        $year = (int)input('year', date('Y'));
        $in = annual_normalize((array)(json_decode((string)($_POST['inputs'] ?? ''), true) ?: []));
        q('INSERT INTO annual_tax_inputs (tax_year, inputs, updated_by) VALUES (?,?,?)
           ON DUPLICATE KEY UPDATE inputs = VALUES(inputs), updated_by = VALUES(updated_by), updated_at = NOW()', [$year, json_encode($in), $me['id']]);
        log_activity('simpan_pajak_tahunan', (string)$year);
        flash('success', 'Data pajak tahunan ' . $year . ' disimpan.');
        redirect('kalkulator.php?tab=tahunan&tahun=' . $year);
    }
    redirect('kalkulator.php');
}

$tab = ($admin && ($_GET['tab'] ?? '') === 'tahunan') ? 'tahunan' : 'proyek';

/* ---------- Data tab per proyek ---------- */
$projects = $admin
    ? q_all('SELECT p.id, p.name, p.division, p.status, c.net_margin FROM projects p LEFT JOIN project_calculations c ON c.project_id = p.id ORDER BY p.division, p.name')
    : q_all('SELECT p.id, p.name, p.division, p.status, c.net_margin FROM projects p JOIN project_investors pi ON pi.project_id = p.id AND pi.user_id = ?
             LEFT JOIN project_calculations c ON c.project_id = p.id ORDER BY p.division, p.name', [$me['id']]);

$project = null;
$saved = null;
if (!empty($_GET['proyek'])) {
    $pid = (int)$_GET['proyek'];
    if (can_view_project($pid)) {
        $project = q_one('SELECT * FROM projects WHERE id = ?', [$pid]);
        $saved = q_one('SELECT c.*, u.name editor FROM project_calculations c LEFT JOIN users u ON u.id = c.updated_by WHERE c.project_id = ?', [$pid]);
    }
}
$inputs = $saved ? margin_normalize(json_decode($saved['inputs'], true) ?: []) : margin_defaults($project);
if ($project) $inputs['name'] = $project['name'];
$canSave = $admin && $project;

/* ---------- Data tab tahunan ---------- */
if ($tab === 'tahunan') {
    $years = array_map('intval', array_column(q_all('SELECT DISTINCT tax_year FROM project_calculations UNION SELECT tax_year FROM annual_tax_inputs'), 'tax_year'));
    $years[] = (int)date('Y');
    $years = array_values(array_unique($years));
    rsort($years);
    $year = (int)($_GET['tahun'] ?? $years[0]);
    if ($year < 2000 || $year > 2100) $year = (int)date('Y');
    if (!in_array($year, $years, true)) { $years[] = $year; rsort($years); }
    $annualRows = q_all('SELECT c.*, p.name, p.division FROM project_calculations c JOIN projects p ON p.id = c.project_id WHERE c.tax_year = ? ORDER BY p.name', [$year]);
    $annualIn = annual_normalize(json_decode((string)q_val('SELECT inputs FROM annual_tax_inputs WHERE tax_year = ?', [$year]), true) ?: []);
    $uncalc = (int)q_val("SELECT COUNT(*) FROM projects p LEFT JOIN project_calculations c ON c.project_id = p.id
                          WHERE c.project_id IS NULL AND p.status <> 'batal' AND YEAR(COALESCE(p.start_date, p.created_at)) = ?", [$year]);
}

$pageTitle = 'Kalkulator Margin';
$active = 'kalkulator';
require APP_ROOT . '/views/app_header.php';
?>
<?php if ($admin): ?>
<div class="tabs">
  <a href="<?= url('kalkulator.php' . ($project ? '?proyek=' . $project['id'] : '')) ?>" class="<?= $tab === 'proyek' ? 'is-on' : '' ?>"><?= icon('calc', 16) ?> Per proyek</a>
  <a href="<?= url('kalkulator.php?tab=tahunan') ?>" class="<?= $tab === 'tahunan' ? 'is-on' : '' ?>"><?= icon('chart', 16) ?> Pajak tahunan</a>
</div>
<?php endif; ?>

<?php if ($tab === 'proyek'): ?>
<!-- =================== PER PROYEK =================== -->
<div class="toolbar">
  <label class="pickproj">
    <?= icon('folder', 18) ?>
    <select class="select" onchange="location='?proyek='+this.value">
      <option value="">— Simulasi bebas (tidak terhubung proyek) —</option>
      <?php $lastDiv = null; foreach ($projects as $pp):
        if ($pp['division'] !== $lastDiv) { if ($lastDiv) echo '</optgroup>'; echo '<optgroup label="' . e(division_name($pp['division'])) . '">'; $lastDiv = $pp['division']; } ?>
        <option value="<?= $pp['id'] ?>" <?= $project && $project['id'] == $pp['id'] ? 'selected' : '' ?>><?= e($pp['name']) ?><?= $pp['net_margin'] !== null ? ' · ' . number_format((float)$pp['net_margin'], 1, ',', '.') . '%' : ' · belum dihitung' ?></option>
      <?php endforeach; if ($lastDiv) echo '</optgroup>'; ?>
    </select>
  </label>
  <?php if ($project): ?>
    <a class="btn btn--line" href="<?= url('proyek-detail.php?id=' . $project['id']) ?>"><?= icon('eye', 17) ?> Buka proyek</a>
  <?php endif; ?>
</div>

<?php if ($project && $saved): ?>
  <div class="alert alert--info"><?= icon('check', 18) ?> <span>Perhitungan tersimpan · terakhir diubah <?= tgl($saved['updated_at'], true) ?><?= $saved['editor'] ? ' oleh ' . e($saved['editor']) : '' ?>.<?= !$admin ? ' Anda bisa mencoba angka lain, tetapi perubahan tidak disimpan.' : '' ?></span></div>
<?php elseif ($project && !$saved): ?>
  <div class="alert alert--info"><?= icon('alert', 18) ?> <span><?= $admin ? 'Proyek ini belum punya perhitungan. Angka di bawah diisi otomatis dari nilai kontrak dan divisi proyek — sesuaikan biayanya lalu simpan.' : 'Admin belum menyimpan perhitungan untuk proyek ini. Angka di bawah hanya contoh.' ?></span></div>
<?php elseif (!$project): ?>
  <div class="alert alert--info"><?= icon('calc', 18) ?> <span>Mode simulasi: hitung cepat tanpa menyimpan. Pilih proyek di atas untuk menyimpan perhitungan ke proyek tersebut.</span></div>
<?php endif; ?>

<div class="calc" id="calc">
  <div class="calc__in">
    <section class="card">
      <h3 class="card__title"><span class="stepn">1</span> Penjualan ke klien</h3>
      <div class="fgrid">
        <label class="f"><span>Nama proyek</span><input id="name" <?= $project ? 'readonly' : '' ?>></label>
        <label class="f"><span>Jenis proyek</span><select id="jenis"><?php foreach (MARGIN_JENIS as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="f"><span>Nilai kontrak (Rp)</span><input id="jual" class="money" inputmode="numeric"></label>
        <label class="f"><span>Nilai tersebut</span><select id="jualMode"><option value="ex">Belum termasuk PPN</option><option value="in">Sudah termasuk PPN</option></select></label>
        <label class="f"><span>PPN efektif</span><select id="ppn"><option value="11">11% (barang/jasa non-mewah)</option><option value="12">12% (barang mewah)</option></select></label>
        <label class="f" id="whW"><span id="whL">PPh 22 dipungut klien (%)</span><input id="wh" type="number" step="0.1" min="0"></label>
        <label class="f" id="kW" hidden><span>Tarif PPh final konstruksi</span><select id="kRate"><?php foreach (MARGIN_K_RATES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
        <label class="f"><span>Tahun pajak</span><input id="year" type="number" min="2000" max="2100"></label>
      </div>
      <label class="f"><span>Siapa yang menyetor PPN?</span>
        <select id="pem"><option value="0">Saya sendiri (klien swasta biasa)</option><option value="1">Klien memungut &amp; setor (instansi pemerintah / BUMN)</option></select>
      </label>
      <p class="note" id="whN"></p>
    </section>

    <section class="card">
      <div class="card__head"><h3><span class="stepn">2</span> Biaya proyek</h3>
        <select id="costMode" class="select select--sm"><option value="ex">Nominal berfaktur: belum PPN</option><option value="in">Nominal berfaktur: sudah PPN</option></select>
      </div>
      <div class="costs" id="rows"></div>
      <button class="btn btn--line btn--sm btn--block" id="addRow" type="button"><?= icon('plus', 16) ?> Tambah biaya</button>
      <p class="note">Nyalakan <b>Faktur</b> bila vendor PKP dan faktur pajaknya sah, agar PPN masukan bisa dikreditkan. Tanpa faktur, PPN dianggap beban. Baris pertama dianggap harga pokok (untuk laba kotor).</p>
    </section>

    <section class="card">
      <h3 class="card__title"><span class="stepn">3</span> Pajak penghasilan &amp; target</h3>
      <div class="fgrid">
        <label class="f" id="schW"><span>Skema PPh</span><select id="scheme"><option value="badan">PPh Badan (atas laba)</option><option value="final">PPh Final 0,5% (hanya jika masih berhak)</option></select></label>
        <label class="f" id="rateW"><span id="rateL">Tarif PPh Badan (%)</span><input id="rate" type="number" step="0.1" min="0"></label>
        <label class="f"><span>Target net margin (%)</span><input id="target" type="number" step="0.5" min="0"></label>
      </div>
    </section>
  </div>

  <div class="calc__out">
    <section class="card net" id="netCard">
      <small>Net profit</small>
      <div class="net__big" id="oNet">–</div>
      <div class="net__row">
        <div><small>Net margin</small><b id="oNetM">–</b></div>
        <div><small>Status PPN</small><b id="hPN">–</b></div>
      </div>
      <div class="gauge"><i id="gauge"></i><span id="gaugeT"></span></div>
      <?php if ($canSave): ?>
        <form method="post" id="saveForm" class="net__save">
          <?= csrf_field() ?><input type="hidden" name="action" value="save_calc"><input type="hidden" name="project_id" value="<?= $project['id'] ?>"><input type="hidden" name="inputs" id="inputsField">
          <button class="btn btn--primary btn--block" id="saveBtn"><?= icon('check', 18) ?> <?= $saved ? 'Simpan perubahan' : 'Simpan ke proyek' ?></button>
        </form>
        <span class="dirty" id="dirty" hidden>Ada perubahan yang belum disimpan</span>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="card__title">Rincian</h3>
      <dl class="res">
        <div><dt>Penjualan bersih (DPP)</dt><dd id="oS"></dd></div>
        <div><dt>Total biaya (di luar PPN masukan)</dt><dd id="oC"></dd></div>
        <div><dt>Laba kotor (penjualan − harga pokok)</dt><dd id="oGP"></dd></div>
        <div><dt>Margin kotor</dt><dd id="oGM"></dd></div>
        <div><dt>Laba sebelum pajak</dt><dd id="oEBT"></dd></div>
        <div><dt>Margin sebelum pajak</dt><dd id="oEBTM"></dd></div>
        <div><dt>Markup atas biaya</dt><dd id="oMU"></dd></div>
        <div><dt id="oPPhL">PPh terutang</dt><dd id="oPPh"></dd></div>
      </dl>
    </section>

    <section class="card">
      <h3 class="card__title">PPN &amp; arus kas</h3>
      <dl class="res">
        <div><dt>PPN keluaran</dt><dd id="oPO"></dd></div>
        <div><dt>PPN masukan (dikreditkan)</dt><dd id="oPI"></dd></div>
        <div><dt>PPN disetor klien langsung ke negara</dt><dd id="oPS"></dd></div>
        <div><dt id="oPNl">PPN kurang bayar</dt><dd id="oPN"></dd></div>
        <div><dt>Total tagihan ke klien</dt><dd id="oT"></dd></div>
        <div><dt id="oWHl">PPh 22 dipungut klien</dt><dd id="oWH"></dd></div>
        <div class="res__tot"><dt>Kas diterima dari klien</dt><dd id="oCash"></dd></div>
      </dl>
      <p class="note">PPh 22/23 adalah kredit pajak (tidak mengurangi laba, hanya kas yang diterima). Untuk konstruksi, yang dipotong adalah PPh final yang menjadi beban proyek.</p>
    </section>

    <section class="card">
      <h3 class="card__title">Posisi kas &amp; PPN tertahan</h3>
      <dl class="res">
        <div><dt>Uang masuk ke rekening dari klien</dt><dd id="kIn"></dd></div>
        <div><dt>Modal keluar (barang &amp; biaya, termasuk PPN masukan)</dt><dd id="kOut"></dd></div>
        <div><dt>PPN yang Anda setor sendiri</dt><dd id="kSet"></dd></div>
        <div><dt id="kSaldoL">Sisa uang di rekening</dt><dd id="kSaldo"></dd></div>
        <div><dt>Modal yang sudah kembali (maks. 100%)</dt><dd id="kRet"></dd></div>
      </dl>
      <h4 class="res__sec">PPN tertahan di DJP</h4>
      <dl class="res">
        <div><dt>Total PPN masukan tertahan</dt><dd id="kHeld"></dd></div>
        <div><dt>Bisa diajukan restitusi tiap masa</dt><dd id="kRes"></dd></div>
        <div><dt>Dikompensasi / restitusi akhir tahun buku</dt><dd id="kWait"></dd></div>
      </dl>
      <h4 class="res__sec">Setelah semua pajak beres</h4>
      <dl class="res">
        <div><dt id="kPphL">PPh yang masih harus dibayar</dt><dd id="kPph"></dd></div>
        <div class="res__tot"><dt>Uang bersih akhir</dt><dd id="kFin"></dd></div>
      </dl>
      <p class="note">Restitusi tiap masa berlaku bagi PKP yang menjual ke pemungut PPN. Jalur cepat (sekitar 1 bulan) hanya untuk yang memenuhi kriteria, misalnya PKP berisiko rendah; jalur biasa bisa sampai 12 bulan. Jika tidak semua penjualan ke pemungut, bagian yang bisa dicairkan per masa bisa lebih kecil.</p>
    </section>

    <section class="card">
      <h3 class="card__title">Harga jual untuk target margin</h3>
      <dl class="res">
        <div><dt>Nilai kontrak minimum (belum PPN)</dt><dd id="oTS"></dd></div>
        <div><dt>Nilai kontrak minimum (termasuk PPN)</dt><dd id="oTI"></dd></div>
      </dl>
      <p class="note">Estimasi per proyek berdasarkan aturan PPN dengan DPP nilai lain 11/12 (tarif 12% × 11/12 = 11% efektif). Hasil bukan pengganti pertimbangan konsultan pajak.</p>
    </section>

    <?php if ($canSave && $saved): ?>
      <form method="post" data-confirm="Hapus perhitungan margin proyek ini?" class="calc__del">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_calc"><input type="hidden" name="project_id" value="<?= $project['id'] ?>">
        <button class="link link--danger"><?= icon('trash', 15) ?> Hapus perhitungan tersimpan</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script>window.CALC = <?= json_encode(['mode' => 'proyek', 'inputs' => $inputs, 'defaults' => ['b' => margin_default_rows('b'), 'k' => margin_default_rows('k'), 'j' => margin_default_rows('j')]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>

<?php else: ?>
<!-- =================== TAHUNAN =================== -->
<div class="toolbar">
  <label class="pickproj"><?= icon('clock', 18) ?>
    <select class="select" onchange="location='?tab=tahunan&tahun='+this.value">
      <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>>Tahun pajak <?= $y ?></option><?php endforeach; ?>
      <option value="<?= max($years) + 1 ?>">Tahun pajak <?= max($years) + 1 ?></option>
    </select>
  </label>
</div>

<div class="calc" id="annual">
  <div class="calc__in">
    <section class="card card--flush">
      <div class="card__head card__head--pad"><h3><span class="stepn">1</span> Proyek tahun <?= $year ?> <span class="count"><?= count($annualRows) ?></span></h3></div>
      <?php if ($annualRows): ?>
        <div class="tablewrap">
          <table class="table table--sm">
            <thead><tr><th>Proyek</th><th class="r">Omzet (DPP)</th><th class="r">Laba sblm pajak</th><th class="r">PPh dipotong</th><th class="r">PPN K / M</th></tr></thead>
            <tbody>
              <?php foreach ($annualRows as $a): $dv = divisions()[$a['division']]; ?>
                <tr>
                  <td><a href="<?= url('kalkulator.php?proyek=' . $a['project_id']) ?>" class="doc"><span class="dot" style="--dc:<?= e($dv['color']) ?>"></span><div><b><?= e($a['name']) ?></b>
                    <small><?= $a['is_final'] ? 'PPh final konstruksi' : 'PPh Badan' ?><?= $a['ppn_by_client'] ? ' · PPN dipungut klien' : '' ?></small></div></a></td>
                  <td class="r nowrap"><?= rupiah($a['net_sales']) ?></td>
                  <td class="r nowrap <?= $a['ebt'] < 0 ? 'neg' : '' ?>"><?= rupiah($a['ebt']) ?></td>
                  <td class="r nowrap"><?= rupiah($a['withholding']) ?></td>
                  <td class="r nowrap small"><?= rupiah($a['ppn_out'], true) ?><br><span class="muted"><?= rupiah($a['ppn_in'], true) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="empty"><?= icon('calc', 28) ?><p>Belum ada perhitungan proyek dengan tahun pajak <?= $year ?>. Hitung dan simpan proyek di tab "Per proyek".</p></div>
      <?php endif; ?>
      <?php if ($uncalc): ?><p class="note note--pad"><?= icon('alert', 15) ?> <?= $uncalc ?> proyek yang dimulai tahun <?= $year ?> belum punya perhitungan margin, jadi belum ikut dihitung di sini.</p><?php endif; ?>
    </section>

    <section class="card">
      <h3 class="card__title"><span class="stepn">2</span> Data tahunan lain</h3>
      <form method="post" id="annualForm" class="form">
        <?= csrf_field() ?><input type="hidden" name="action" value="save_annual"><input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="inputs" id="annualField">
        <div class="fgrid">
          <label class="f"><span>Peredaran bruto lain (di luar proyek)</span><input id="omLain" class="money" inputmode="numeric"></label>
          <label class="f"><span>Biaya operasional setahun (gaji, sewa, dll.)</span><input id="opex" class="money" inputmode="numeric"></label>
          <label class="f"><span>Koreksi fiskal (+/−)</span><input id="kor" class="money" data-neg="1"></label>
          <label class="f"><span>Kompensasi rugi tahun lalu</span><input id="komp" class="money" inputmode="numeric"></label>
          <label class="f"><span>PPh 23 lain yang dipotong (di luar proyek)</span><input id="p23" class="money" inputmode="numeric"></label>
          <label class="f"><span>PPh 25 sudah dibayar setahun</span><input id="p25" class="money" inputmode="numeric"></label>
          <label class="f"><span>Tarif PPh Badan (%)</span><input id="arate" type="number" step="0.1" min="0"></label>
        </div>
        <p class="note">Koreksi fiskal: tambahkan (+) biaya yang tidak boleh dikurangkan menurut aturan pajak, kurangkan (−) penghasilan yang sudah kena pajak final.</p>
        <button class="btn btn--primary"><?= icon('check', 18) ?> Simpan data tahun <?= $year ?></button>
      </form>
    </section>
  </div>

  <div class="calc__out">
    <section class="card net">
      <small id="aHL">PPh Pasal 29</small>
      <div class="net__big" id="aSel">–</div>
      <div class="net__row"><div><small>Kurang bayar?</small><b id="aSt">–</b></div><div><small>PPh Badan terutang</small><b id="aTax2">–</b></div></div>
    </section>
    <section class="card">
      <h3 class="card__title">Rincian</h3>
      <dl class="res">
        <div><dt>Peredaran bruto total</dt><dd id="aOm"></dd></div>
        <div><dt>&nbsp;&nbsp;di antaranya proyek konstruksi (PPh final)</dt><dd id="aOmK"></dd></div>
        <div><dt>Laba proyek non-final (sebelum pajak)</dt><dd id="aEb"></dd></div>
        <div><dt>Penghasilan neto fiskal</dt><dd id="aNeto"></dd></div>
        <div><dt>PKP (setelah kompensasi rugi)</dt><dd id="aPkp"></dd></div>
        <div><dt>Bagian PKP yang dapat diskon Pasal 31E</dt><dd id="aFas"></dd></div>
        <div><dt>PPh Badan terutang</dt><dd id="aTax"></dd></div>
        <div><dt>Hemat berkat Pasal 31E</dt><dd id="aHemat"></dd></div>
      </dl>
      <h4 class="res__sec">Kredit pajak</h4>
      <dl class="res">
        <div><dt>PPh 22/23 (dari proyek)</dt><dd id="aK22"></dd></div>
        <div><dt>PPh 23 lain</dt><dd id="aK23"></dd></div>
        <div><dt>PPh 25</dt><dd id="aK25"></dd></div>
        <div><dt>PPh final konstruksi (sudah dipotong, bukan kredit)</dt><dd id="aFin"></dd></div>
      </dl>
      <p class="note" id="aNote"></p>
    </section>
    <section class="card">
      <h3 class="card__title">PPN semua proyek</h3>
      <dl class="res">
        <div><dt>PPN keluaran yang Anda setor sendiri</dt><dd id="aPK"></dd></div>
        <div><dt>PPN keluaran yang dipungut klien</dt><dd id="aPKV"></dd></div>
        <div><dt>PPN masukan (dikreditkan)</dt><dd id="aPM"></dd></div>
        <div class="res__tot"><dt id="aPNL">PPN kurang/lebih bayar</dt><dd id="aPN"></dd></div>
      </dl>
      <h4 class="res__sec">Jika lebih bayar</h4>
      <dl class="res">
        <div><dt>Bisa diajukan restitusi tiap masa</dt><dd id="aPR"></dd></div>
        <div><dt>Dikompensasi / restitusi akhir tahun buku</dt><dd id="aPC"></dd></div>
      </dl>
      <p class="note">Ini posisi akumulasi setahun. SPT Masa PPN dilaporkan bulanan, jadi angka per bulan bisa berbeda. PPN masukan dari proyek berklien pemungut dianggap bagian yang bisa direstitusi tiap masa.</p>
    </section>
  </div>
</div>
<script>window.CALC = <?= json_encode([
    'mode' => 'tahunan',
    'annual' => $annualIn,
    'projects' => array_map(fn($a) => [
        'o' => (float)$a['net_sales'], 'e' => (float)$a['ebt'], 't' => (float)$a['withholding'], 'k' => (bool)$a['is_final'],
        'po' => (float)$a['ppn_out'], 'pi' => (float)$a['ppn_in'], 'pv' => (bool)$a['ppn_by_client'],
    ], $annualRows),
], JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php endif; ?>

<script src="<?= asset('js/kalkulator.js') ?>" defer></script>
<?php require APP_ROOT . '/views/app_footer.php'; ?>
