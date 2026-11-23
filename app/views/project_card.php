<?php
/** @var array $p */
$pd = divisions()[$p['division']] ?? ['name' => $p['division'], 'color' => '#888'];
$cover = $p['cover_image'] ? url(trim((string)config('cover_url'), '/') . '/' . $p['cover_image']) : null;
$year = $p['end_date'] ?: $p['start_date'];
?>
<article class="pcard reveal" style="--dc:<?= e($pd['color']) ?>" data-division="<?= e($p['division']) ?>">
  <div class="pcard__media">
    <?php if ($cover): ?>
      <img src="<?= e($cover) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php else: ?>
      <div class="pcard__ph" aria-hidden="true"><?= icon(divisions()[$p['division']]['icon'] ?? 'folder', 44) ?></div>
    <?php endif; ?>
    <span class="pcard__tag"><?= e($pd['name']) ?></span>
  </div>
  <div class="pcard__body">
    <h3><?= e($p['name']) ?></h3>
    <?php if ($p['summary']): ?><p><?= e($p['summary']) ?></p><?php endif; ?>
    <div class="pcard__meta">
      <?php if ($p['client']): ?><span><?= icon('build', 15) ?> <?= e($p['client']) ?></span><?php endif; ?>
      <?php if ($p['location']): ?><span><?= icon('pin', 15) ?> <?= e($p['location']) ?></span><?php endif; ?>
      <?php if ($year): ?><span><?= icon('clock', 15) ?> <?= e(date('Y', strtotime($year))) ?></span><?php endif; ?>
    </div>
  </div>
</article>
