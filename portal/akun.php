<?php
require __DIR__ . '/_boot.php';
$me = require_login();

if (is_post()) {
    csrf_check();
    $row = q_one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    if (!password_verify($cur, $row['password_hash'])) {
        flash('error', 'Kata sandi saat ini salah.');
    } elseif ($pe = validate_password($new)) {
        flash('error', $pe);
    } elseif ($new !== ($_POST['new2'] ?? '')) {
        flash('error', 'Konfirmasi kata sandi baru tidak sama.');
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        q('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $me['id']]);
        session_regenerate_id(true);
        bind_session((int)$me['id'], $newHash); // sesi di perangkat lain otomatis keluar
        log_activity('ganti_sandi');
        flash('success', 'Kata sandi berhasil diganti.');
    }
    redirect('akun.php');
}

$pageTitle = 'Akun Saya';
$active = '';
require APP_ROOT . '/views/app_header.php';
?>
<div class="cols cols--legal">
  <section class="card">
    <div class="profile">
      <span class="avatar avatar--lg"><?= e(mb_strtoupper(mb_substr($me['name'], 0, 1))) ?></span>
      <div><h3><?= e($me['name']) ?></h3><p class="muted"><?= e($me['email']) ?><?= $me['phone'] ? ' · ' . e($me['phone']) : '' ?></p>
        <span class="tag <?= $me['role'] === 'admin' ? 'tag--dark' : 'tag--olive' ?>"><?= $me['role'] === 'admin' ? 'Administrator' : 'Investor' ?></span></div>
    </div>
    <p class="muted small">Untuk mengubah nama atau email, hubungi admin.</p>
  </section>
  <section class="card">
    <h3 class="card__title">Ganti kata sandi</h3>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label class="f"><span>Kata sandi saat ini</span><input type="password" name="current" required autocomplete="current-password"></label>
      <label class="f"><span>Kata sandi baru (min. 8, huruf + angka)</span><input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
      <label class="f"><span>Ulangi kata sandi baru</span><input type="password" name="new2" required minlength="8" autocomplete="new-password"></label>
      <button class="btn btn--primary btn--block">Simpan</button>
    </form>
  </section>
</div>
<?php require APP_ROOT . '/views/app_footer.php'; ?>
