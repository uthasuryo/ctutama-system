<?php
require __DIR__ . '/_boot.php';
require_admin();

if (is_post()) {
    csrf_check();
    $id = (int)input('id', 0);
    match ((string)input('action')) {
        'read'   => q('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$id]),
        'unread' => q('UPDATE contact_messages SET is_read = 0 WHERE id = ?', [$id]),
        'delete' => q('DELETE FROM contact_messages WHERE id = ?', [$id]),
        'readall'=> q('UPDATE contact_messages SET is_read = 1'),
        default  => null,
    };
    redirect('pesan.php' . (input('action') === 'delete' || input('action') === 'readall' ? '' : '?id=' . $id));
}

$msgs = q_all('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 300');
$open = null;
if (isset($_GET['id'])) {
    $open = q_one('SELECT * FROM contact_messages WHERE id = ?', [(int)$_GET['id']]);
    if ($open && !$open['is_read']) {
        q('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$open['id']]);
        $open['is_read'] = 1;
        foreach ($msgs as &$m) if ($m['id'] == $open['id']) $m['is_read'] = 1;
        unset($m);
    }
}

$pageTitle = 'Pesan Masuk';
$active = 'pesan';
require APP_ROOT . '/views/app_header.php';
?>
<div class="toolbar">
  <p class="muted grow">Pesan dari formulir kontak website.</p>
  <?php if ($msgs): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="readall"><button class="btn btn--line btn--sm"><?= icon('check', 16) ?> Tandai semua dibaca</button></form><?php endif; ?>
</div>
<div class="cols cols--inbox">
  <section class="card card--flush">
    <?php if ($msgs): ?>
      <ul class="inbox">
        <?php foreach ($msgs as $m): $d = $m['division'] ? (divisions()[$m['division']] ?? null) : null; ?>
          <li class="<?= $m['is_read'] ? '' : 'is-unread' ?> <?= $open && $open['id'] == $m['id'] ? 'is-open' : '' ?>">
            <a href="?id=<?= $m['id'] ?>">
              <div class="inbox__top"><b><?= e($m['name']) ?></b><small><?= tgl($m['created_at'], true) ?></small></div>
              <div class="inbox__sub"><?php if ($d): ?><span class="dot" style="--dc:<?= e($d['color']) ?>"></span><?php endif; ?><?= e($m['company'] ?: $m['email']) ?></div>
              <p><?= e(mb_strimwidth($m['message'], 0, 110, '…')) ?></p>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty"><?= icon('mail', 32) ?><p>Belum ada pesan dari website.</p></div>
    <?php endif; ?>
  </section>

  <section class="card">
    <?php if ($open): $d = $open['division'] ? (divisions()[$open['division']] ?? null) : null; ?>
      <div class="card__head">
        <h3><?= e($open['name']) ?></h3>
        <div class="actions">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unread"><input type="hidden" name="id" value="<?= $open['id'] ?>"><button class="iconbtn" title="Tandai belum dibaca"><?= icon('mail', 17) ?></button></form>
          <form method="post" data-confirm="Hapus pesan ini?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $open['id'] ?>"><button class="iconbtn iconbtn--danger" title="Hapus"><?= icon('trash', 17) ?></button></form>
        </div>
      </div>
      <dl class="kv kv--row">
        <div><dt>Email</dt><dd><a class="link" href="mailto:<?= e($open['email']) ?>"><?= e($open['email']) ?></a></dd></div>
        <?php if ($open['phone']): ?><div><dt>Telepon</dt><dd><?= e($open['phone']) ?></dd></div><?php endif; ?>
        <?php if ($open['company']): ?><div><dt>Instansi</dt><dd><?= e($open['company']) ?></dd></div><?php endif; ?>
        <?php if ($d): ?><div><dt>Bidang</dt><dd><span class="divtag" style="--dc:<?= e($d['color']) ?>"><?= e($d['name']) ?></span></dd></div><?php endif; ?>
        <div><dt>Diterima</dt><dd><?= tgl($open['created_at'], true) ?></dd></div>
      </dl>
      <div class="prose msgbody"><?= nl2br(e($open['message'])) ?></div>
      <div class="hello__actions">
        <a class="btn btn--primary btn--sm" href="mailto:<?= e($open['email']) ?>?subject=<?= rawurlencode('Re: Pertanyaan ke PT Candaka Tiga Utama') ?>"><?= icon('mail', 16) ?> Balas via email</a>
        <?php if ($open['phone'] && ($wa = preg_replace('/\D/', '', preg_replace('/^0/', '62', $open['phone'])))): ?>
          <a class="btn btn--line btn--sm" target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>"><?= icon('whatsapp', 16) ?> WhatsApp</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="empty"><?= icon('mail', 30) ?><p>Pilih pesan untuk membaca.</p></div>
    <?php endif; ?>
  </section>
</div>
<?php require APP_ROOT . '/views/app_footer.php'; ?>
