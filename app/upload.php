<?php
declare(strict_types=1);

const DOC_TYPES = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls'  => ['application/vnd.ms-excel', 'application/octet-stream', 'application/x-ole-storage', 'application/CDFV2'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'csv'  => ['text/csv', 'text/plain', 'application/csv'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
    'zip'  => ['application/zip', 'application/x-zip-compressed'],
];

const IMAGE_TYPES = [
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'webp' => ['image/webp'],
];

/**
 * Simpan file unggahan dengan aman.
 * @return array{0: ?array, 1: ?string}  [data file, pesan error]
 */
function store_upload(?array $file, string $baseDir, string $subDir, array $allowed = DOC_TYPES): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, 'Pilih file yang akan diunggah.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas server.',
            UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian. Coba lagi.',
            default => 'Gagal mengunggah file (kode ' . $file['error'] . ').',
        };
        return [null, $msg];
    }
    $max = (int)config('max_upload_mb', 25) * 1024 * 1024;
    if ($file['size'] > $max) {
        return [null, 'Ukuran file maksimal ' . config('max_upload_mb', 25) . ' MB.'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'Unggahan tidak valid.'];
    }

    $original = basename((string)$file['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        return [null, 'Jenis file .' . e($ext) . ' tidak diizinkan. Gunakan: ' . implode(', ', array_keys($allowed)) . '.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
    if (!in_array($mime, $allowed[$ext], true)) {
        return [null, 'Isi file tidak sesuai dengan ekstensinya (' . e($mime) . ').'];
    }

    $subDir = trim(preg_replace('#[^a-z0-9/_-]#i', '', $subDir), '/');
    $dir = rtrim($baseDir, '/') . ($subDir !== '' ? '/' . $subDir : '');
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        return [null, 'Folder penyimpanan tidak dapat dibuat. Periksa izin folder storage.'];
    }
    $stored = date('Ymd') . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
        return [null, 'File tidak dapat disimpan. Periksa izin folder storage.'];
    }
    @chmod($dir . '/' . $stored, 0640);

    return [[
        'path'     => ($subDir !== '' ? $subDir . '/' : '') . $stored,
        'original' => mb_substr($original, 0, 250),
        'mime'     => $mime,
        'size'     => (int)$file['size'],
    ], null];
}

/**
 * Kompres file gambar yang baru diunggah ke storage privat.
 * Mengubah $file['size'] menjadi ukuran baru dan mencatatnya agar tidak diproses ulang.
 */
function optimize_stored_upload(array &$file, string $kind): void
{
    if (!is_image_mime($file['mime'])) return;
    $full = storage_file($file['path']);
    if (!$full) return;
    $r = optimize_image($full, $file['mime']);
    if ($r === null) return;
    $file['size'] = $r['after'];
    try {
        q('INSERT IGNORE INTO media_optimized (file_path, kind, before_size, after_size) VALUES (?,?,?,?)', [$file['path'], $kind, $r['before'], $r['after']]);
    } catch (Throwable) {
    }
}

function storage_file(string $relPath): string
{
    $base = realpath(config('storage_path'));
    $full = realpath($base . '/' . $relPath);
    if (!$base || !$full || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
        return '';
    }
    return $full;
}

function delete_stored(string $relPath): void
{
    $f = storage_file($relPath);
    if ($f && is_file($f)) {
        @unlink($f);
    }
}

function send_file(string $fullPath, string $downloadName, string $mime, bool $inline = false): never
{
    if (!is_file($fullPath)) {
        http_response_code(404);
        exit('File tidak ditemukan.');
    }
    $inlineOk = $inline && in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    $safe = preg_replace('/[^\w.\- ()]/u', '_', $downloadName);
    header('Content-Disposition: ' . ($inlineOk ? 'inline' : 'attachment') . '; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Download-Options: noopen');
    // File unggahan tidak boleh menjalankan skrip atau memuat sumber lain
    if (str_starts_with($mime, 'image/')) {
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
    } elseif (!$inlineOk) {
        header("Content-Security-Policy: default-src 'none'; sandbox");
    }
    readfile($fullPath);
    exit;
}
