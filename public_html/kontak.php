<?php
require __DIR__ . '/_boot.php';

$c = config('company');

if (is_post()) {
    csrf_check();
    $data = [
        'name'     => mb_substr((string)input('name', ''), 0, 120),
        'email'    => mb_substr((string)input('email', ''), 0, 160),
        'phone'    => mb_substr((string)input('phone', ''), 0, 40),
        'company'  => mb_substr((string)input('company', ''), 0, 160),
        'division' => (string)input('division', ''),
        'message'  => mb_substr((string)input('message', ''), 0, 5000),
    ];
    $errors = [];
    // Honeypot anti-spam + jeda minimal pengisian 3 detik
    if (input('website') !== '' && input('website') !== null) $errors[] = 'spam';
    if (time() - (int)($_SESSION['_contact_t'] ?? 0) < 3) $errors[] = 'Mohon tunggu sebentar sebelum mengirim.';
    ensure_schema();
    $ip = client_ip();
    if (throttle_count('contact', $ip, 600) >= 3 || throttle_count('contact', $ip, 86400) >= 15) {
        $errors[] = 'Terlalu banyak pesan dari jaringan Anda. Silakan coba lagi nanti atau hubungi kami lewat WhatsApp.';
    }
    if ($data['name'] === '') $errors[] = 'Nama wajib diisi.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Alamat email tidak valid.';
    if (mb_strlen($data['message']) < 10) $errors[] = 'Pesan minimal 10 karakter.';
    if (preg_match_all('~https?://~i', $data['message']) > 3) $errors[] = 'Pesan berisi terlalu banyak tautan.';
    if (!isset(divisions()[$data['division']])) $data['division'] = null;

    if ($errors) {
        if (!in_array('spam', $errors, true)) {
            flash('error', implode(' ', $errors));
            keep_old($data);
        }
        redirect('kontak.php#form');
    }

    q('INSERT INTO contact_messages (name, email, phone, company, division, message, ip_address) VALUES (?,?,?,?,?,?,?)', [
        $data['name'], strtolower($data['email']), nullable($data['phone']), nullable($data['company']),
        $data['division'], $data['message'], $ip,
    ]);
    throttle_hit('contact', $ip);
    flash('success', 'Terima kasih, pesan Anda sudah kami terima. Tim kami akan segera menghubungi Anda.');
    redirect('kontak.php#form');
}

$_SESSION['_contact_t'] = time();
$preDiv = old('division', $_GET['divisi'] ?? '');

$pageTitle = 'Kontak — PT Candaka Tiga Utama';
$activeNav = 'kontak';
require APP_ROOT . '/views/public_header.php';
?>
<section class="phead">
  <div class="hero__bg" aria-hidden="true"><span class="blob blob--taupe"></span><span class="blob blob--blue"></span><div class="grid-lines"></div></div>
  <div class="wrap">
    <nav class="crumbs"><a href="<?= url('') ?>">Beranda</a> / <span>Kontak</span></nav>
    <h1>Mari bicara soal proyek Anda.</h1>
    <p>Isi formulir atau hubungi kami langsung. Kami membalas di hari kerja.</p>
  </div>
</section>

<section class="section">
  <div class="wrap contact">
    <div class="contact__info reveal">
      <a class="cinfo" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($c['maps_query']) ?>" target="_blank" rel="noopener">
        <span><?= icon('pin', 22) ?></span>
        <div><small>Alamat Kantor</small><b><?= e($c['address']) ?>, <?= e($c['city']) ?></b></div>
      </a>
      <a class="cinfo" href="tel:<?= e(preg_replace('/[^\d+]/', '', $c['phone'])) ?>">
        <span><?= icon('phone', 22) ?></span>
        <div><small>Telepon / WhatsApp</small><b><?= e($c['phone']) ?></b></div>
      </a>
      <a class="cinfo" href="mailto:<?= e($c['email']) ?>">
        <span><?= icon('mail', 22) ?></span>
        <div><small>Email</small><b><?= e($c['email']) ?></b><?php if ($c['email_alt']): ?><em><?= e($c['email_alt']) ?></em><?php endif; ?></div>
      </a>
      <div class="map">
        <iframe title="Peta lokasi kantor" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
          src="https://www.google.com/maps?q=<?= urlencode($c['maps_query']) ?>&output=embed"></iframe>
      </div>
    </div>

    <form class="cform reveal" id="form" method="post" action="<?= url('kontak.php') ?>" novalidate>
      <?= csrf_field() ?>
      <div class="hp" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <h2>Kirim pesan</h2>
      <div class="frow">
        <label class="fld"><span>Nama lengkap *</span><input name="name" required maxlength="120" value="<?= e(old('name')) ?>"></label>
        <label class="fld"><span>Instansi / Perusahaan</span><input name="company" maxlength="160" value="<?= e(old('company')) ?>"></label>
      </div>
      <div class="frow">
        <label class="fld"><span>Email *</span><input type="email" name="email" required maxlength="160" value="<?= e(old('email')) ?>"></label>
        <label class="fld"><span>Telepon / WA</span><input name="phone" maxlength="40" value="<?= e(old('phone')) ?>"></label>
      </div>
      <fieldset class="fld">
        <span>Bidang yang dibutuhkan</span>
        <div class="seg">
          <?php foreach (divisions() as $slug => $d): ?>
            <label style="--dc:<?= e($d['color']) ?>"><input type="radio" name="division" value="<?= e($slug) ?>" <?= $preDiv === $slug ? 'checked' : '' ?>><span><?= icon($d['icon'], 16) ?> <?= e($d['name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="fld"><span>Pesan *</span><textarea name="message" rows="6" required minlength="10" maxlength="5000" placeholder="Ceritakan lingkup pekerjaan, lokasi, perkiraan jadwal…"><?= e(old('message')) ?></textarea></label>
      <button class="btn btn--primary btn--block" type="submit">Kirim Pesan <?= icon('arrow', 18) ?></button>
    </form>
  </div>
</section>

<?php require APP_ROOT . '/views/public_footer.php'; ?>
