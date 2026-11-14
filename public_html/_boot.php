<?php
// Titik masuk website (ctutama.com). Mencari folder app/ di luar docroot (disarankan) atau di dalamnya.
define('DOC_ROOT', __DIR__);
define('APP_SIDE', 'site');
foreach ([__DIR__ . '/../app/bootstrap.php', __DIR__ . '/app/bootstrap.php'] as $__f) {
    if (is_file($__f)) { require $__f; return; }
}
http_response_code(500);
exit('Folder app/ tidak ditemukan. Lihat README bagian Instalasi.');
