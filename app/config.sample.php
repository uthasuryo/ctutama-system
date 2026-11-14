<?php
/**
 * Salin file ini menjadi config.php lalu sesuaikan nilainya.
 * Folder app/ dan storage/ sebaiknya berada DI LUAR public_html.
 */
return [
    'app_name'   => 'PT Candaka Tiga Utama',
    'short_name' => 'CTU',

    // Alamat lengkap website dan portal (tanpa garis miring di akhir)
    'site_url'   => 'https://ctutama.com',
    'portal_url' => 'https://portal.ctutama.com',

    // Path dasar bila dipasang di subfolder (mis. '/ctu'). Biarkan '' untuk root domain/subdomain.
    'base_url'   => '',

    'env'        => 'production',   // 'development' untuk menampilkan error — JANGAN dipakai di server

    // Kode untuk membuka portal/setup.php saat membuat admin pertama.
    // Isi dengan teks acak panjang (mis. hasil: openssl rand -hex 16), lalu kosongkan lagi setelah instalasi.
    'setup_key'  => '',

    // Kosongkan, kecuali situs di belakang CDN/proxy. Contoh Cloudflare: 'HTTP_CF_CONNECTING_IP'
    'client_ip_header' => '',
    'timezone'   => 'Asia/Jakarta',

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'ctu_db',
        'user'    => 'ctu_user',
        'pass'    => 'GANTI_PASSWORD_DB',
        'charset' => 'utf8mb4',
    ],

    // Lokasi penyimpanan dokumen privat (tidak bisa diakses langsung dari browser)
    'storage_path' => __DIR__ . '/../storage/uploads',

    // Lokasi gambar sampul proyek publik. Diunggah lewat portal, ditampilkan di website.
    'cover_path'   => __DIR__ . '/../public_html/uploads/covers',
    'cover_url'    => '/uploads/covers',

    'max_upload_mb' => 25,

    // Data perusahaan untuk tampilan website
    'company' => [
        'legal_name' => 'PT Candaka Tiga Utama',
        'director'   => 'Rahmat Suryoputro, S.Kom., M.M.',
        'address'    => 'Jalan Mengger Tengah No. 31, Kel. Mengger, Kec. Bandung Kidul',
        'city'       => 'Kota Bandung, Jawa Barat 40267',
        'phone'      => '+62 813-8910-6969',
        'whatsapp'   => '6281389106969',
        'email'      => 'manajemen@ctutama.com',
        'email_alt'  => 'candakatigautama21@gmail.com',
        'deed'       => 'Akta No. 21 tanggal 22 September 2025, Notaris Prila Claudia Syamtizard, S.H., M.Kn.',
        'maps_query' => 'Jalan Mengger Tengah 31 Bandung Kidul Bandung',
    ],
];
