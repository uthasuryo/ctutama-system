<?php
/** Penyaji file privat dengan pemeriksaan hak akses. */
require __DIR__ . '/_boot.php';
$me = require_login();
$admin = $me['role'] === 'admin';

$type = (string)($_GET['t'] ?? '');
$id = (int)($_GET['id'] ?? 0);
$inline = !empty($_GET['lihat']);

$deny = function (): never {
    http_response_code(404);
    exit('Dokumen tidak ditemukan atau Anda tidak memiliki akses.');
};

if ($type === 'legal') {
    $doc = q_one('SELECT * FROM legal_documents WHERE id = ?', [$id]);
    if (!$doc || (!$admin && !$doc['visible_to_investor'])) $deny();
    log_activity('unduh_legal', $doc['title']);
    send_file(storage_file($doc['file_path']), $doc['original_name'], $doc['mime_type'], $inline);
}

if ($type === 'proyek') {
    $doc = q_one('SELECT * FROM project_documents WHERE id = ?', [$id]);
    if (!$doc || !can_view_project((int)$doc['project_id']) || (!$admin && !$doc['visible_to_investor'])) $deny();
    log_activity('unduh_dok_proyek', $doc['title']);
    send_file(storage_file($doc['file_path']), $doc['original_name'], $doc['mime_type'], $inline);
}

if ($type === 'zip') {
    if (!class_exists('ZipArchive')) exit('Ekstensi ZIP tidak tersedia di server.');
    $p = q_one('SELECT * FROM projects WHERE id = ?', [$id]);
    if (!$p || !can_view_project($id)) $deny();
    $docs = q_all('SELECT * FROM project_documents WHERE project_id = ?' . ($admin ? '' : ' AND visible_to_investor = 1'), [$id]);
    if (!$docs) $deny();
    $tmp = tempnam(sys_get_temp_dir(), 'ctu');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $used = [];
    foreach ($docs as $d) {
        $f = storage_file($d['file_path']);
        if (!$f) continue;
        $name = preg_replace('#[\\\\/:*?"<>|]#', '_', $d['doc_type']) . '/' . $d['original_name'];
        $base = $name; $n = 1;
        while (isset($used[$name])) { $name = preg_replace('/(\.\w+)$/', " ($n)$1", $base); $n++; }
        $used[$name] = true;
        $zip->addFile($f, $name);
    }
    $zip->close();
    log_activity('unduh_zip_proyek', $p['name']);
    $fname = 'Dokumen - ' . preg_replace('#[\\\\/:*?"<>|]#', '', $p['name']) . '.zip';
    register_shutdown_function(fn() => @unlink($tmp));
    send_file($tmp, $fname, 'application/zip');
}

$deny();
