<?php
require __DIR__ . '/_boot.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $action = (string)input('action', '');
    $id = (int)input('id', 0);

    if ($action === 'save') {
        $name = (string)input('name', '');
        $email = strtolower((string)input('email', ''));
        $phone = nullable(input('phone'));
        $role = input('role') === 'admin' ? 'admin' : 'investor';
        $pass = (string)($_POST['password'] ?? '');
        $errors = [];
        if ($name === '') $errors[] = 'Nama wajib diisi.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
        elseif (q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) $errors[] = 'Email sudah dipakai akun lain.';
        if (!$id || $pass !== '') { if ($pe = validate_password($pass)) $errors[] = $pe; }
        if ($id === (int)$me['id'] && $role !== 'admin') $errors[] = 'Anda tidak bisa menurunkan peran akun sendiri.';
        if ($errors) {
            flash('error', implode(' ', $errors));
            keep_old(compact('name', 'email', 'phone', 'role'));
            redirect('pengguna.php' . ($id ? '?edit=' . $id : '?baru=1') . '#form');
        }
        if ($id) {
            q('UPDATE users SET name=?, email=?, phone=?, role=? WHERE id=?', [$name, $email, $phone, $role, $id]);
            if ($pass !== '') {
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                q('UPDATE users SET password_hash=? WHERE id=?', [$newHash, $id]); // sesi pengguna tsb. otomatis berakhir
                if ($id === (int)$me['id']) bind_session($id, $newHash);
            }
            log_activity('ubah_pengguna', $email);
            flash('success', 'Akun ' . $name . ' diperbarui.');
        } else {
            q('INSERT INTO users (name, email, phone, role, password_hash) VALUES (?,?,?,?,?)', [$name, $email, $phone, $role, password_hash($pass, PASSWORD_DEFAULT)]);
            log_activity('tambah_pengguna', $email . ' (' . $role . ')');
            flash('success', 'Akun ' . $name . ' dibuat. Bagikan email dan kata sandinya secara langsung kepada yang bersangkutan.');
        }
        redirect('pengguna.php');
    }

    if ($action === 'toggle' && $id !== (int)$me['id']) {
        q('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);
        log_activity('status_pengguna', (string)$id);
        flash('success', 'Status akun diperbarui.');
        redirect('pengguna.php');
    }

    if ($action === 'delete' && $id !== (int)$me['id']) {
        $u = q_one('SELECT * FROM users WHERE id = ?', [$id]);
        if ($u) {
            q('DELETE FROM users WHERE id = ?', [$id]);
            log_activity('hapus_pengguna', $u['email']);
            flash('success', 'Akun ' . $u['name'] . ' dihapus.');
        }
        redirect('pengguna.php');
    }
    redirect('pengguna.php');
}

$users = q_all('SELECT u.*, (SELECT COUNT(*) FROM project_investors pi WHERE pi.user_id = u.id) projects FROM users u ORDER BY u.role, u.name');
$edit = isset($_GET['edit']) ? q_one('SELECT * FROM users WHERE id = ?', [(int)$_GET['edit']]) : null;
$showForm = $edit || isset($_GET['baru']);
$f = fn($k, $d = '') => old($k, $edit[$k] ?? $d);

$pageTitle = 'Pengguna';
$active = 'pengguna';
require APP_ROOT . '/views/app_header.php';
?>
<div class="toolbar">
  <p class="muted grow">Admin memiliki akses penuh. Investor hanya melihat proyek yang dibagikan, dokumen yang ditandai untuk investor, dan kalkulator margin.</p>
  <a class="btn btn--primary" href="?baru=1#form"><?= icon('plus', 18) ?> Tambah Pengguna</a>
</div>

<div class="cols <?= $showForm ? 'cols--legal' : '' ?>">
  <section class="card card--flush">
    <div class="tablewrap">
      <table class="table">
        <thead><tr><th>Nama</th><th>Peran</th><th>Proyek</th><th>Login terakhir</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr class="<?= $u['is_active'] ? '' : 'is-dim' ?>">
            <td><div class="doc"><span class="avatar avatar--sm"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span><div><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?></small></div></div></td>
            <td><span class="tag <?= $u['role'] === 'admin' ? 'tag--dark' : 'tag--olive' ?>"><?= $u['role'] === 'admin' ? 'Admin' : 'Investor' ?></span></td>
            <td class="small"><?= $u['role'] === 'admin' ? 'Semua' : (int)$u['projects'] ?></td>
            <td class="small nowrap"><?= $u['last_login_at'] ? tgl($u['last_login_at'], true) : '<span class="muted">Belum pernah</span>' ?></td>
            <td><span class="pill <?= $u['is_active'] ? 'pill--green' : 'pill--grey' ?>"><?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td class="actions">
              <a class="iconbtn" href="?edit=<?= $u['id'] ?>#form" title="Ubah"><?= icon('edit', 17) ?></a>
              <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="iconbtn" title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"><?= icon($u['is_active'] ? 'lock' : 'check', 17) ?></button></form>
                <form method="post" data-confirm="Hapus akun <?= e($u['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="iconbtn iconbtn--danger" title="Hapus"><?= icon('trash', 17) ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($showForm): ?>
  <section class="card" id="form">
    <div class="card__head"><h3><?= $edit ? 'Ubah akun' : 'Akun baru' ?></h3><a class="link" href="<?= url('pengguna.php') ?>">Tutup</a></div>
    <form method="post" class="form" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <label class="f"><span>Nama *</span><input name="name" required value="<?= e($f('name')) ?>"></label>
      <label class="f"><span>Email *</span><input type="email" name="email" required value="<?= e($f('email')) ?>"></label>
      <label class="f"><span>Telepon</span><input name="phone" value="<?= e($f('phone')) ?>"></label>
      <div class="f"><span>Peran</span>
        <div class="seg seg--app">
          <label><input type="radio" name="role" value="investor" <?= $f('role', 'investor') === 'investor' ? 'checked' : '' ?>><span>Investor</span></label>
          <label><input type="radio" name="role" value="admin" <?= $f('role') === 'admin' ? 'checked' : '' ?>><span>Admin</span></label>
        </div>
      </div>
      <label class="f"><span><?= $edit ? 'Kata sandi baru (kosongkan bila tidak diganti)' : 'Kata sandi awal *' ?></span>
        <div class="pw"><input type="text" name="password" <?= $edit ? '' : 'required' ?> minlength="8" autocomplete="new-password" data-pw><button type="button" class="link" data-genpw>Buat acak</button></div>
      </label>
      <button class="btn btn--primary btn--block"><?= icon('check', 18) ?> Simpan</button>
    </form>
  </section>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/views/app_footer.php'; ?>
