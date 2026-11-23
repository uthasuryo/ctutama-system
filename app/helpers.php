<?php
declare(strict_types=1);

function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string)config('base_url', ''), '/') . '/' . ltrim($path, '/');
}

/** URL absolut ke website publik (ctutama.com). */
function site_url(string $path = ''): string
{
    return rtrim((string)config('site_url', ''), '/') . '/' . ltrim($path, '/');
}

/** URL absolut ke portal (portal.ctutama.com). */
function portal_url(string $path = ''): string
{
    return rtrim((string)config('portal_url', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = (defined('DOC_ROOT') ? DOC_ROOT : '') . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . ltrim($path, '/')) . $v;
}

function redirect(string $path): never
{
    $to = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $to);
    exit;
}

/** Versi kode yang terpasang (dibuat otomatis oleh build-deploy.sh). */
function app_version(): array
{
    return $GLOBALS['APP_VERSION'] ?? ['commit' => 'dev', 'date' => null, 'files' => 0];
}

/** Tampilkan halaman 404 milik website/portal yang sedang berjalan, lalu berhenti. */
function not_found(): never
{
    $page = defined('DOC_ROOT') ? DOC_ROOT . '/404.php' : '';
    if ($page !== '' && is_file($page)) {
        require $page;
    } else {
        http_response_code(404);
        echo 'Halaman tidak ditemukan.';
    }
    exit;
}

function back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

/* ---------- Flash message ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/* ---------- Old input ---------- */
function keep_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function old(string $key, $default = '')
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
    }
    return $old[$key] ?? $default;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        // 403 (bukan 419): Apache/LiteSpeed tidak mengenal 419 dan akan mengubahnya menjadi 500
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sesi kedaluwarsa</title>'
           . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center">'
           . '<div><h1>Sesi formulir kedaluwarsa</h1><p>Muat ulang halaman sebelumnya, lalu coba kirim lagi.</p>'
           . '<p><a href="' . e(url('')) . '">Kembali ke halaman utama</a></p></div>';
        exit;
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = null)
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function nullable(?string $v): ?string
{
    $v = $v === null ? null : trim($v);
    return ($v === '' || $v === null) ? null : $v;
}

/* ---------- Format ---------- */
function rupiah($n, bool $short = false): string
{
    if ($n === null || $n === '') {
        return '–';
    }
    $n = (float)$n;
    if ($short) {
        $f = fn($v) => preg_replace('/,0$/', '', number_format($v, 1, ',', '.'));
        if ($n >= 1e12) return 'Rp ' . $f($n / 1e12) . ' T';
        if ($n >= 1e9)  return 'Rp ' . $f($n / 1e9) . ' M';
        if ($n >= 1e6)  return 'Rp ' . $f($n / 1e6) . ' jt';
    }
    return 'Rp ' . number_format($n, 0, ',', '.');
}

/** Ubah input "1.250.000,50" atau "1250000" menjadi float. */
function parse_rupiah(?string $v): ?float
{
    $v = nullable($v);
    if ($v === null) return null;
    $v = preg_replace('/[^\d,\.]/', '', $v);
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? (float)$v : null;
}

function tgl($date, bool $withTime = false): string
{
    if (!$date) return '–';
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime((string)$date);
    if ($ts === false) return '–';
    $s = date('j', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    return $withTime ? $s . ', ' . date('H:i', $ts) : $s;
}

function filesize_h(int $bytes): string
{
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float)$bytes;
    while ($n >= 1024 && $i < count($u) - 1) { $n /= 1024; $i++; }
    return number_format($n, $i ? 1 : 0, ',', '.') . ' ' . $u[$i];
}

function file_icon(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match (true) {
        $ext === 'pdf'                          => 'pdf',
        in_array($ext, ['doc', 'docx'])         => 'doc',
        in_array($ext, ['xls', 'xlsx', 'csv'])  => 'xls',
        in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) => 'img',
        in_array($ext, ['zip', 'rar'])          => 'zip',
        default                                 => 'file',
    };
}

/* ---------- Data referensi ---------- */
function divisions(): array
{
    return [
        'konstruksi' => [
            'name'    => 'Konstruksi',
            'color'   => '#998675',
            'tagline' => 'Membangun fondasi yang kokoh untuk pertumbuhan.',
            'intro'   => 'Divisi Konstruksi menangani pekerjaan bangunan gedung, renovasi, dan pekerjaan sipil dengan perencanaan yang rapi, pengawasan mutu yang ketat, serta penyelesaian tepat waktu.',
            'icon'    => 'build',
            'services' => [
                ['Bangunan Gedung', 'Pembangunan gedung kantor, sekolah, ruko, dan fasilitas umum dari struktur hingga finishing.'],
                ['Renovasi & Interior', 'Peremajaan bangunan, penataan ulang ruang, dan pekerjaan interior fungsional.'],
                ['Pekerjaan Sipil', 'Jalan lingkungan, drainase, pagar, dan infrastruktur pendukung kawasan.'],
                ['Mekanikal & Elektrikal', 'Instalasi listrik, plumbing, tata udara, dan sistem pendukung bangunan.'],
                ['Manajemen & Pengawasan', 'Penjadwalan, pengendalian biaya, dan pengawasan mutu di lapangan.'],
            ],
        ],
        'pengadaan' => [
            'name'    => 'Pengadaan Barang',
            'color'   => '#908D0A',
            'tagline' => 'Rantai pasok yang andal untuk BUMN, instansi, dan korporasi.',
            'intro'   => 'Divisi Pengadaan Barang menjadi mitra pasokan yang tertib administrasi — dari penawaran, kontrak, pengiriman, hingga dokumen perpajakan — bekerja sama langsung dengan principal terpercaya.',
            'icon'    => 'box',
            'services' => [
                ['Pengadaan BUMN & Pemerintah', 'Pemenuhan kebutuhan barang sesuai kontrak dengan administrasi dan perpajakan yang tertib.'],
                ['Perlengkapan Kantor & Operasional', 'Peralatan kantor, furnitur, dan kebutuhan operasional harian.'],
                ['Material & Peralatan Teknis', 'Material bangunan, peralatan kerja, dan suku cadang teknis.'],
                ['Distribusi & Logistik', 'Pengiriman terjadwal sampai lokasi dengan dokumen serah terima lengkap.'],
                ['Kemitraan Principal', 'Kerja sama dengan principal dan distributor resmi untuk harga dan keaslian produk.'],
            ],
        ],
        'it' => [
            'name'    => 'Teknologi Informasi',
            'color'   => '#29AAE3',
            'tagline' => 'Solusi digital yang bekerja, bukan sekadar terlihat bagus.',
            'intro'   => 'Divisi IT membangun dan merawat sistem informasi, infrastruktur jaringan, serta lingkungan server — berbekal pengalaman langsung di lingkungan perbankan dan perguruan tinggi.',
            'icon'    => 'chip',
            'services' => [
                ['Sistem Informasi & Aplikasi Web', 'Pengembangan aplikasi sesuai proses bisnis: akademik, administrasi, dan internal perusahaan.'],
                ['Infrastruktur Jaringan & Server', 'Perancangan jaringan, instalasi server, dan konfigurasi keamanan dasar.'],
                ['DevOps & Cloud', 'Otomasi deployment, lingkungan pengujian, dan pemantauan sistem.'],
                ['Pengadaan Perangkat TI', 'Laptop, PC, perangkat jaringan, dan lisensi perangkat lunak.'],
                ['Pemeliharaan & Dukungan', 'Dukungan teknis berkala, pemeliharaan sistem, dan pelatihan pengguna.'],
            ],
        ],
    ];
}

function division_name(string $slug): string
{
    return divisions()[$slug]['name'] ?? ucfirst($slug);
}

function project_statuses(): array
{
    return [
        'persiapan' => 'Persiapan',
        'berjalan'  => 'Berjalan',
        'selesai'   => 'Selesai',
        'tertunda'  => 'Tertunda',
        'batal'     => 'Batal',
    ];
}

function legal_categories(): array
{
    return [
        'Akta Pendirian', 'Akta Perubahan', 'SK Kemenkumham', 'NIB / OSS', 'NPWP',
        'Surat Pengukuhan PKP', 'SBU / Sertifikat Badan Usaha', 'SKK / Sertifikat Tenaga Ahli',
        'Izin Usaha Lainnya', 'Laporan Keuangan', 'SPT Tahunan', 'Rekening Koran / Referensi Bank',
        'Kontrak Kerja Sama', 'Lainnya',
    ];
}

function project_doc_types(): array
{
    return [
        'Kontrak / SPK', 'Surat Penawaran', 'Purchase Order', 'Addendum', 'Berita Acara (BAST)',
        'Berita Acara Instalasi / UAT', 'Invoice', 'Faktur Pajak', 'Bukti Potong', 'Bukti Pembayaran', 'Surat Jalan',
        'RAB / Perhitungan Biaya', 'Laporan Progres', 'Foto Dokumentasi', 'Lainnya',
    ];
}

function log_activity(string $action, ?string $detail = null): void
{
    try {
        q('INSERT INTO activity_log (user_id, action, detail, ip_address) VALUES (?,?,?,?)', [
            current_user()['id'] ?? null, $action, $detail ? mb_substr($detail, 0, 300) : null,
            client_ip(),
        ]);
    } catch (Throwable) {
        // log tidak boleh mengganggu alur utama
    }
}

/** Ikon SVG sederhana (stroke). */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'build'  => '<path d="M3 21h18M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/>',
        'box'    => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'chip'   => '<rect x="6" y="6" width="12" height="12" rx="2"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
        'home'   => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
        'folder' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
        'calc'   => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 11h2M12 11h2M16 11v6M8 15h2M12 15h2"/>',
        'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 010 7M18 14.8c1.8.8 3 2.6 3.5 5.2"/>',
        'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/>',
        'logout' => '<path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"/><path d="M10 17l5-5-5-5M15 12H3"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>',
        'eye'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'edit'   => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>',
        'trash'  => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
        'pin'    => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0112 2.5a7 7 0 017 7C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'menu'   => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'x'      => '<path d="M6 6l12 12M18 6L6 18"/>',
        'check'  => '<path d="M5 12l5 5 9-10"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'alert'  => '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18v.5"/>',
        'chart'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'link'   => '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/>',
        'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/>',
        'file'   => '<path d="M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8l-5-5z"/><path d="M14 3v5h5"/>',
        'wallet' => '<path d="M4 7a2 2 0 012-2h11v4"/><path d="M4 7v10a2 2 0 002 2h13a1 1 0 001-1v-8a1 1 0 00-1-1H6a2 2 0 01-2-2z"/><circle cx="16" cy="14" r="1.2"/>',
        'dots'   => '<circle cx="5" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="19" cy="12" r="1.3"/>',
        'image'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
        'circle' => '<circle cx="12" cy="12" r="8.5" stroke-dasharray="3 2.6"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.8"/>',
        'minus-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
        'disk'   => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'lock'   => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
        'whatsapp' => '<path d="M4 20l1.3-4A8 8 0 1112 20a8 8 0 01-4-1.1L4 20z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 .8a4 4 0 01-1.8-1.8l.8-1-1-2L9 9.5z"/>',
    ];
    $p = $paths[$name] ?? $paths['file'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
