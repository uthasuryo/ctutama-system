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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas server (' . ini_get('upload_max_filesize') . '). Muat ulang halaman lalu coba lagi agar file dikirim bertahap.',
            UPLOAD_ERR_PARTIAL => 'File hanya terunggah sebagian. Coba lagi.',
            default => 'Gagal mengunggah file (kode ' . $file['error'] . ').',
        };
        return [null, $msg];
    }
    $max = (int)config('max_upload_mb', 25) * 1024 * 1024;
    if ($file['size'] > $max) {
        return [null, 'Ukuran file ' . filesize_h((int)$file['size']) . ', maksimal ' . config('max_upload_mb', 25) . ' MB.'];
    }
    // File biasa harus berasal dari unggahan PHP; file rakitan (upload bertahap) harus berada di folder sementara milik pengguna ini
    $chunked = !empty($file['chunked']);
    if ($chunked ? !is_chunk_file((string)$file['tmp_name']) : !is_uploaded_file($file['tmp_name'])) {
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
    $moved = $chunked ? @rename($file['tmp_name'], $dir . '/' . $stored) : move_uploaded_file($file['tmp_name'], $dir . '/' . $stored);
    if (!$moved) {
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


/* =============================================================
   Upload bertahap (chunked) untuk file besar.
   Browser memotong file menjadi potongan kecil dan mengirimnya satu per satu
   ke portal/upload-chunk.php, sehingga tidak terkena batas post_max_size /
   upload_max_filesize server dan bisa diulang per potongan bila koneksi putus.
   Formulir lalu mengirim "token" pengganti file: <nama-field>__chunked[].
   ============================================================= */

/** Ubah nilai ini seperti "8M" / "1G" menjadi byte. */
function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') return PHP_INT_MAX;
    $n = (float)$v;
    return (int)match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
}

function max_upload_bytes(): int
{
    return (int)config('max_upload_mb', 25) * 1048576;
}

/** Ukuran potongan: maks. 4 MB, dan selalu di bawah batas unggah/POST server. */
function chunk_size_bytes(): int
{
    $limit = min(ini_bytes((string)ini_get('upload_max_filesize')), ini_bytes((string)ini_get('post_max_size')) - 65536);
    return max(262144, min(4 * 1048576, (int)($limit * 0.9)));
}

function chunk_dir(): string
{
    $d = rtrim((string)config('storage_path'), '/') . '/tmp-uploads';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return $d;
}

function chunk_paths(string $id): array
{
    $u = (int)(current_user()['id'] ?? 0);
    $b = chunk_dir() . '/u' . $u . '-' . $id;
    return ['part' => $b . '.part', 'meta' => $b . '.json', 'done' => $b . '.done'];
}

function is_chunk_file(string $path): bool
{
    $real = realpath($path);
    $dir = realpath(chunk_dir());
    $u = (int)(current_user()['id'] ?? 0);
    return $real && $dir && str_starts_with($real, $dir . DIRECTORY_SEPARATOR)
        && str_starts_with(basename($real), 'u' . $u . '-') && str_ends_with($real, '.done');
}

/**
 * Semua file untuk satu field formulir, dari unggahan biasa maupun upload bertahap,
 * dalam format seragam [name, tmp_name, error, size, (chunked)].
 */
function request_files(string $field): array
{
    $out = [];
    $f = $_FILES[$field] ?? null;
    if ($f) {
        if (is_array($f['name'])) {
            foreach (array_keys($f['name']) as $i) {
                if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
            }
        } elseif (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $out[] = $f;
        }
    }
    foreach ((array)($_POST[$field . '__chunked'] ?? []) as $id) {
        if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) continue;
        $p = chunk_paths($id);
        $meta = is_file($p['meta']) ? json_decode((string)file_get_contents($p['meta']), true) : null;
        if (!$meta || !is_file($p['done'])) {
            $out[] = ['name' => 'file', 'tmp_name' => '', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 0];
            continue;
        }
        @unlink($p['meta']);
        register_shutdown_function(fn() => @unlink($p['done'])); // sisa yang tidak jadi disimpan ikut dibersihkan
        $out[] = ['name' => (string)$meta['name'], 'tmp_name' => $p['done'], 'error' => UPLOAD_ERR_OK, 'size' => (int)filesize($p['done']), 'chunked' => true];
    }
    return $out;
}

/** Bila request POST melebihi post_max_size, PHP mengosongkan $_POST & $_FILES. */
function post_too_large(): bool
{
    $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$_POST && !$_FILES && $len > ini_bytes((string)ini_get('post_max_size'));
}
