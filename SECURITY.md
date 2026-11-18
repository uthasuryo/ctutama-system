# Audit Keamanan — Website & Portal PT Candaka Tiga Utama

**Tanggal audit:** 14 November 2026
**Cakupan:** `ctutama.com` (website publik) dan `portal.ctutama.com` (portal internal), seluruh kode PHP, `.htaccess`, dan alur unggah/unduh dokumen.
**Metode:** tinjauan kode baris per baris, lalu serangan nyata dari luar terhadap server Apache + PHP 8.3 + MariaDB dengan aturan `.htaccess` yang sama seperti di Hostinger. Setiap perbaikan dibuktikan dengan tes otomatis (60 skenario serangan, semuanya lolos).

---

## Ringkasan temuan

| # | Tingkat | Temuan | Status |
|---|---|---|---|
| 1 | **Tinggi** | Pembatasan login disimpan di cookie sesi. Penyerang cukup membuang cookie untuk menebak kata sandi tanpa batas (terbukti: 30 tebakan tidak pernah diblokir). | Diperbaiki — dibatasi di database: 5 gagal per akun dan 20 gagal per IP dalam 15 menit. |
| 2 | **Tinggi** | `setup.php` terbuka untuk siapa saja selama belum ada admin. Bila diunggah tapi instalasi belum dilakukan, orang lain bisa membuat akun admin lebih dulu. | Diperbaiki — wajib memasukkan `setup_key` dari `config.php`, dengan batas percobaan. |
| 3 | **Tinggi** | Deploy manual berisiko mengunggah `.git`, `README`, `config.php`, file `.sql`/`.zip` backup ke `public_html` yang lalu bisa diunduh siapa saja. | Diperbaiki — `.htaccess` memblokir path berawalan titik, dokumentasi, backup, arsip, konfigurasi. Paket deploy manual dibuat tanpa file tersebut. |
| 4 | Sedang | Open redirect setelah login: URL seperti `portal.ctutama.com//evil.com/../proyek.php` bisa membelokkan korban ke situs palsu setelah login. | Diperbaiki — hanya path lokal yang diizinkan. |
| 5 | Sedang | Spam formulir kontak tanpa batas (batas disimpan di cookie). | Diperbaiki — maks. 3 pesan per 10 menit dan 15 per hari per IP, plus honeypot dan penolakan pesan berisi banyak tautan. |
| 6 | Sedang | Tidak ada Content-Security-Policy; JavaScript inline di portal. Bila suatu saat ada celah XSS, skrip penyerang langsung jalan. | Diperbaiki — CSP ketat `script-src 'self'`, semua JavaScript inline dipindah ke file `.js`. |
| 7 | Sedang | Sesi tidak pernah kedaluwarsa dan tetap aktif setelah kata sandi diganti. | Diperbaiki — sesi berakhir setelah 2 jam tidak aktif; ganti kata sandi otomatis mengeluarkan semua perangkat lain. |
| 8 | Sedang | Gambar sampul disimpan apa adanya: metadata EXIF (termasuk lokasi GPS foto ponsel) ikut publik, dan file "polyglot" (gambar + kode) tersimpan utuh. | Diperbaiki — gambar dibuat ulang lewat GD (metadata & kode tersisip terbuang, maks. 2000 px). |
| 9 | Rendah | Versi PHP terlihat di header `X-Powered-By`. | Diperbaiki. |
| 10 | Rendah | Waktu respons login berbeda untuk email terdaftar vs tidak (bisa dipakai menebak email admin). | Diperbaiki — waktu respons disamakan. |
| 11 | Rendah | Status `419` untuk CSRF diubah Apache menjadi `500`. | Diperbaiki — memakai `403` dengan pesan yang jelas. |
| 12 | Rendah | Belum ada halaman 404; error server bisa menampilkan detail teknis. | Diperbaiki — halaman 404 untuk website & portal, 403 disamarkan sebagai 404, error produksi hanya dicatat ke log. |
| 13 | Info | Portal bisa dimuat di dalam iframe situs lain (clickjacking). | Diperbaiki — `X-Frame-Options: DENY` + `frame-ancestors 'none'`. |

## Yang sudah aman sejak awal (diverifikasi ulang)

- **SQL injection** — semua query memakai prepared statement; diuji dengan payload `' OR 1=1 --`, `UNION SELECT`, `DROP TABLE`.
- **XSS tersimpan** — semua output di-escape; payload `<script>` dan `<img onerror>` diuji di nama proyek, klien, ringkasan, dan pesan kontak.
- **CSRF** — semua formulir POST memakai token; tanpa token atau token salah ditolak.
- **Hak akses (IDOR)** — investor tidak bisa membuka proyek, dokumen, atau perhitungan milik proyek lain, maupun dokumen yang ditandai tersembunyi; aksi admin ditolak untuk investor.
- **Unggahan** — whitelist ekstensi + pemeriksaan isi file (bukan sekadar nama). HTML, SVG, PHTML, dan PHP berkedok gambar ditolak. Dokumen disimpan di luar `public_html` dan hanya bisa diunduh lewat `unduh.php` yang memeriksa login.
- **Eksekusi skrip di folder uploads** — PHP di `uploads/` tidak bisa dijalankan.
- **Kata sandi** — bcrypt, minimal 8 karakter berisi huruf dan angka; pesan login tidak membedakan email terdaftar/tidak.
- **Cookie sesi** — `HttpOnly`, `SameSite=Lax`, `Secure` saat HTTPS, ID sesi palsu ditolak (strict mode), dan cookie website terpisah dari portal.

---

## Checklist pengaturan server (lakukan di hPanel)

Kode sudah aman, tapi beberapa hal hanya bisa diatur di hosting:

- [ ] **Paksa HTTPS** — hPanel » *Keamanan* » *SSL* » aktifkan *Force HTTPS* untuk `ctutama.com` **dan** `portal.ctutama.com`.
- [ ] **`config.php`** — `'env' => 'production'`; `setup_key` diisi hanya saat instalasi, lalu **dikosongkan lagi**. Ubah izin file menjadi `600` lewat File Manager.
- [ ] **Versi PHP** — pakai PHP 8.2 atau lebih baru untuk kedua website, dan perbarui bila Hostinger menawarkan versi baru.
- [ ] **Akun hPanel** — aktifkan autentikasi dua langkah (2FA) di akun Hostinger. Siapa pun yang masuk hPanel bisa membaca `config.php` dan database.
- [ ] **User database** — jangan pakai user MySQL ini untuk aplikasi lain.
- [ ] **Kata sandi admin portal** — panjang dan unik (password manager disarankan); jangan dipakai di tempat lain.
- [ ] **Backup** — pastikan backup harian Hostinger aktif; unduh backup database secara berkala ke tempat terpisah.
- [ ] *(Opsional)* **Lapisan kedua untuk portal** — *Password Protected Directories* atau batasi IP lewat blok `RequireAny` di `portal/.htaccess` / *Manajer IP*.
- [ ] *(Bila memakai Cloudflare)* isi `'client_ip_header' => 'HTTP_CF_CONNECTING_IP'` di `config.php`. Tanpa ini semua pengunjung terlihat ber-IP sama dan pembatasan per-IP bisa memblokir semua orang.

## Risiko yang tersisa (diterima / rencana berikutnya)

- **Belum ada 2FA untuk login portal.** Bila kata sandi admin bocor (misalnya karena phishing), penyerang mendapat akses penuh. Rekomendasi: tambahkan 2FA berbasis aplikasi authenticator untuk akun admin.
- **`style-src 'unsafe-inline'`** masih diizinkan karena warna divisi memakai atribut `style`. Risikonya rendah (CSS tidak bisa menjalankan skrip), tapi bisa diketatkan nanti.
- **Serangan volume besar (DDoS)** di luar jangkauan kode; mengandalkan proteksi Hostinger (atau Cloudflare bila dipakai).
- **Tidak ada notifikasi email** saat login dari perangkat baru atau saat dokumen dihapus. Log aktivitas di portal mencatat semuanya, tapi harus dicek manual.

## Menjalankan ulang audit

Setelah mengubah kode, ulangi pemeriksaan minimal ini:
1. Login salah 6 kali berturut-turut dari jendela penyamaran berbeda → harus diblokir.
2. Buka `https://ctutama.com/.git/config`, `/README.md`, `/config.php` → harus 404.
3. Buka DevTools » Console di portal → tidak boleh ada error "Content Security Policy".
4. Login sebagai investor → hanya proyek yang dibagikan yang terlihat.
