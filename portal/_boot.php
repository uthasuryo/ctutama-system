<?php
// Titik masuk portal (portal.ctutama.com). Mencari folder app/ di luar docroot (disarankan) atau di dalamnya.
define('DOC_ROOT', __DIR__);
define('APP_SIDE', 'portal');
// Lokasi app/ yang dicoba berurutan:
//   1. ../app                         (portal di ~/portal, folder home biasa)
//   2. ../../ctutama.com/app          (Hostinger: portal = website terpisah di domains/portal.ctutama.com)
//   3. ../../app                      (portal di public_html/portal)
foreach ([__DIR__ . '/../app/bootstrap.php', dirname(__DIR__, 2) . '/ctutama.com/app/bootstrap.php', __DIR__ . '/../../app/bootstrap.php', __DIR__ . '/app/bootstrap.php'] as $__f) {
    if (is_file($__f)) { require $__f; return; }
}
http_response_code(500);
exit('Folder app/ tidak ditemukan. Lihat README bagian Instalasi.');
