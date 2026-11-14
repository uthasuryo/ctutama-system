<?php $c = config('company'); ?>
</main>

<footer class="footer">
  <div class="footer__stripes" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
  <div class="wrap footer__grid">
    <div>
      <a class="brand brand--light" href="<?= url('') ?>">
        <img src="<?= asset('img/logo-mark.svg') ?>" alt="" width="34" height="38">
        <span class="brand__text"><b class="c-blue">CANDAKA</b> <b class="c-taupe">TIGA</b> <b class="c-olive">UTAMA</b></span>
      </a>
      <p class="footer__about">Mitra terpadu untuk konstruksi, pengadaan barang, dan teknologi informasi. Satu kontrak, tiga keahlian.</p>
    </div>
    <div>
      <h4>Divisi</h4>
      <?php foreach (divisions() as $navSlug => $navDiv): ?>
        <a href="<?= url('divisi.php?d=' . $navSlug) ?>"><?= e($navDiv['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Perusahaan</h4>
      <a href="<?= url('index.php#tentang') ?>">Tentang Kami</a>
      <a href="<?= url('portofolio.php') ?>">Portofolio</a>
      <a href="<?= url('kontak.php') ?>">Kontak</a>
    </div>
    <div>
      <h4>Kantor</h4>
      <p><?= e($c['address']) ?><br><?= e($c['city']) ?></p>
      <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a>
      <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
    </div>
  </div>
  <div class="wrap footer__bottom">
    <span>© <?= date('Y') ?> <?= e($c['legal_name']) ?>. Hak cipta dilindungi.</span>
    <span>Bandung, Jawa Barat</span>
  </div>
</footer>

<a class="wa-float" href="https://wa.me/<?= e($c['whatsapp']) ?>" target="_blank" rel="noopener" aria-label="Hubungi via WhatsApp"><?= icon('whatsapp', 26) ?></a>

<script src="<?= asset('js/site.js') ?>" defer></script>
</body>
</html>
