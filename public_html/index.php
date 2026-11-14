<?php
require __DIR__ . '/_boot.php';

$c = config('company');
$stats = q_one("SELECT COUNT(*) total, SUM(status='selesai') selesai, SUM(status='berjalan') berjalan FROM projects WHERE status <> 'batal'") ?? [];
$featured = q_all('SELECT * FROM projects WHERE is_public = 1 ORDER BY COALESCE(end_date, start_date, created_at) DESC LIMIT 6');

$pageTitle = 'PT Candaka Tiga Utama — Konstruksi · Pengadaan Barang · Teknologi Informasi';
$activeNav = 'home';
$bodyClass = 'is-home';
require APP_ROOT . '/views/public_header.php';
?>

<!-- ============ HERO ============ -->
<section class="hero">
  <div class="hero__bg" aria-hidden="true">
    <span class="blob blob--blue"></span><span class="blob blob--taupe"></span><span class="blob blob--olive"></span>
    <div class="grid-lines"></div>
  </div>
  <div class="wrap hero__inner">
    <div class="hero__copy">
      <p class="eyebrow"><span class="pulse"></span> Bandung · Jawa Barat</p>
      <h1>Satu mitra,<br><span class="hero__three">tiga keahlian.</span></h1>
      <p class="hero__rotator">Kami mengerjakan <span class="rot" id="rot" aria-live="polite">
        <span data-c="#C2B3A5">konstruksi gedung & sipil</span>
        <span data-c="#C9C64A">pengadaan barang BUMN</span>
        <span data-c="#5CC4F0">sistem informasi & jaringan</span>
      </span></p>
      <p class="hero__lead">PT Candaka Tiga Utama menyatukan konstruksi, pengadaan barang, dan teknologi informasi dalam satu manajemen — sehingga proyek Anda cukup ditangani oleh satu mitra yang tertib administrasi.</p>
      <div class="hero__cta">
        <a class="btn btn--primary" href="<?= url('kontak.php') ?>">Diskusikan Proyek <?= icon('arrow', 18) ?></a>
        <a class="btn btn--ghost" href="#divisi">Lihat Divisi</a>
      </div>
      <div class="hero__chips">
        <?php foreach (divisions() as $slug => $d): ?>
          <a class="chip" href="<?= url('divisi.php?d=' . $slug) ?>" style="--dc:<?= e($d['color']) ?>"><?= icon($d['icon'], 16) ?> <?= e($d['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="hero__art" aria-hidden="true">
      <svg class="mark" viewBox="-16 -22 324 362">
        <polyline class="mark__a" points="15,195 15,62 112,5 207,62 207,197"/>
        <polyline class="mark__b" points="105,108 105,245 200,305 292,245 292,108"/>
      </svg>
      <div class="orbit orbit--1"><span style="--dc:#998675"><?= icon('build', 22) ?></span></div>
      <div class="orbit orbit--2"><span style="--dc:#908D0A"><?= icon('box', 22) ?></span></div>
      <div class="orbit orbit--3"><span style="--dc:#29AAE3"><?= icon('chip', 22) ?></span></div>
    </div>
  </div>
  <a href="#divisi" class="hero__scroll" aria-label="Gulir ke bawah"><span></span></a>
</section>

<!-- ============ TICKER ============ -->
<div class="ticker" aria-hidden="true">
  <div class="ticker__track">
    <?php for ($i = 0; $i < 2; $i++): foreach (divisions() as $d): foreach ($d['services'] as $s): ?>
      <span style="--dc:<?= e($d['color']) ?>"><i></i><?= e($s[0]) ?></span>
    <?php endforeach; endforeach; endfor; ?>
  </div>
</div>

<!-- ============ DIVISI ============ -->
<section class="section" id="divisi">
  <div class="wrap">
    <div class="section__head reveal">
      <p class="kicker">Tiga Divisi</p>
      <h2>Tiga bidang usaha,<br>satu standar kerja.</h2>
      <p>Setiap divisi punya tim dan keahliannya sendiri, namun berbagi sistem administrasi, kontrol mutu, dan pelaporan yang sama.</p>
    </div>
    <div class="divgrid">
      <?php $n = 0; foreach (divisions() as $slug => $d): $n++; ?>
        <a class="divcard reveal" href="<?= url('divisi.php?d=' . $slug) ?>" style="--dc:<?= e($d['color']) ?>; --delay:<?= $n * 90 ?>ms">
          <span class="divcard__num">0<?= $n ?></span>
          <span class="divcard__icon"><?= icon($d['icon'], 30) ?></span>
          <h3><?= e($d['name']) ?></h3>
          <p><?= e($d['tagline']) ?></p>
          <ul>
            <?php foreach (array_slice($d['services'], 0, 4) as $s): ?><li><?= e($s[0]) ?></li><?php endforeach; ?>
          </ul>
          <span class="divcard__more">Selengkapnya <?= icon('arrow', 16) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ TENTANG ============ -->
<section class="section section--dark" id="tentang">
  <div class="wrap about">
    <div class="about__copy reveal">
      <p class="kicker">Tentang Kami</p>
      <h2>Didirikan untuk bekerja <em>rapi</em> dari awal sampai serah terima.</h2>
      <p>PT Candaka Tiga Utama berkedudukan di Kota Bandung dan bergerak di tiga bidang yang saling melengkapi. Bangunan membutuhkan barang, operasional membutuhkan teknologi — kami menyatukan ketiganya agar klien tidak perlu mengoordinasikan banyak vendor sekaligus.</p>
      <p>Nama <b>Candaka Tiga Utama</b> mencerminkan tiga pilar utama tersebut, yang juga tampil pada tiga warna logo kami.</p>
      <dl class="facts">
        <div><dt>Badan Usaha</dt><dd>Perseroan Terbatas</dd></div>
        <div><dt>Akta Terakhir</dt><dd><?= e($c['deed']) ?></dd></div>
        <div><dt>Bidang Usaha</dt><dd>Konstruksi · Pengadaan Barang · Teknologi Informasi</dd></div>
        <div><dt>Domisili</dt><dd><?= e($c['address']) ?>, <?= e($c['city']) ?></dd></div>
      </dl>
    </div>
    <div class="values">
      <?php
      $values = [
          ['Tertib', 'Setiap proyek tercatat lengkap: kontrak, berita acara, invoice, hingga dokumen pajak.', 'shield', '#29AAE3'],
          ['Tepat', 'Spesifikasi, jadwal, dan anggaran dikendalikan sejak perencanaan, bukan setelah masalah muncul.', 'check', '#998675'],
          ['Terpadu', 'Satu titik koordinasi untuk pekerjaan fisik, pasokan barang, dan sistem digital.', 'link', '#908D0A'],
      ];
      foreach ($values as $i => $v): ?>
        <div class="value reveal" style="--dc:<?= $v[3] ?>; --delay:<?= $i * 120 ?>ms">
          <span class="value__icon"><?= icon($v[2], 24) ?></span>
          <div><h3><?= e($v[0]) ?></h3><p><?= e($v[1]) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ ANGKA ============ -->
<section class="stats">
  <div class="stats__grid">
    <div class="stat reveal"><b data-count="3">3</b><span>Divisi usaha</span></div>
    <?php if ((int)($stats['total'] ?? 0) > 0): ?>
      <div class="stat reveal"><b data-count="<?= (int)$stats['total'] ?>"><?= (int)$stats['total'] ?></b><span>Proyek ditangani</span></div>
      <div class="stat reveal"><b data-count="<?= (int)$stats['selesai'] ?>"><?= (int)$stats['selesai'] ?></b><span>Proyek selesai</span></div>
      <div class="stat reveal"><b data-count="<?= (int)$stats['berjalan'] ?>"><?= (int)$stats['berjalan'] ?></b><span>Sedang berjalan</span></div>
    <?php else: ?>
      <div class="stat reveal"><b>1</b><span>Kontrak untuk semua kebutuhan</span></div>
      <div class="stat reveal"><b>BDG</b><span>Basis operasional di Bandung</span></div>
    <?php endif; ?>
  </div>
</section>

<!-- ============ CARA KERJA ============ -->
<section class="section">
  <div class="wrap">
    <div class="section__head reveal">
      <p class="kicker">Cara Kami Bekerja</p>
      <h2>Alur yang jelas,<br>tanpa kejutan.</h2>
    </div>
    <ol class="steps">
      <?php
      $steps = [
          ['Konsultasi Kebutuhan', 'Kami memahami lingkup, spesifikasi, anggaran, dan tenggat Anda.'],
          ['Penawaran & Kontrak', 'Penawaran rinci dan transparan, lalu dituangkan dalam kontrak atau SPK.'],
          ['Pelaksanaan Terukur', 'Pekerjaan dijalankan dengan laporan progres berkala dan kontrol mutu.'],
          ['Serah Terima & Dukungan', 'BAST, dokumen penagihan dan pajak lengkap, serta dukungan purna-proyek.'],
      ];
      foreach ($steps as $i => $s): ?>
        <li class="step reveal" style="--delay:<?= $i * 100 ?>ms">
          <span class="step__n"><?= $i + 1 ?></span>
          <h3><?= e($s[0]) ?></h3>
          <p><?= e($s[1]) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<?php if ($featured): ?>
<!-- ============ PORTOFOLIO ============ -->
<section class="section section--soft">
  <div class="wrap">
    <div class="section__head section__head--row reveal">
      <div>
        <p class="kicker">Portofolio</p>
        <h2>Proyek yang telah kami kerjakan.</h2>
      </div>
      <a class="btn btn--line" href="<?= url('portofolio.php') ?>">Semua Proyek <?= icon('arrow', 16) ?></a>
    </div>
    <div class="pgrid">
      <?php foreach ($featured as $p) include APP_ROOT . '/views/project_card.php'; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ CTA ============ -->
<section class="cta">
  <div class="wrap cta__inner reveal">
    <div>
      <h2>Punya rencana proyek?</h2>
      <p>Ceritakan kebutuhan Anda — konstruksi, pengadaan, atau sistem IT. Kami balas dengan langkah konkret.</p>
    </div>
    <div class="cta__btns">
      <a class="btn btn--primary" href="<?= url('kontak.php') ?>">Kirim Pesan <?= icon('arrow', 18) ?></a>
      <a class="btn btn--ghost" href="https://wa.me/<?= e($c['whatsapp']) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 18) ?> WhatsApp</a>
    </div>
  </div>
</section>

<?php require APP_ROOT . '/views/public_footer.php'; ?>
