<?php
require __DIR__ . '/_boot.php';

if (is_post()) {
    csrf_check();
    logout();
    session_start();
    flash('success', 'Anda telah keluar.');
}
redirect('login.php');
