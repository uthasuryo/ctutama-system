# Website PT Candaka Tiga Utama

Company profile 3 divisi (Konstruksi, Pengadaan Barang, Teknologi Informasi) dan portal internal untuk dokumen legal, daftar proyek beserta dokumennya, akses investor, serta kalkulator margin.

**Kebutuhan:** PHP 8.1+ (ekstensi `pdo_mysql`, `fileinfo`, `zip`, `gd`; `exif` disarankan), MySQL 5.7+ / MariaDB 10.3+, Apache (cPanel/hosting biasa sudah cukup).

---

## Dua alamat (Hostinger)

| Alamat | Folder di server | Isi |
|---|---|---|
| `ctutama.com` | `~/domains/ctutama.com/public_html` | Company profile (publik). Tidak ada link apa pun ke portal. |
| `portal.ctutama.com` | `~/domains/ctutama.com/portal` (lewat symlink) | Portal internal: login, proyek, dokumen legal, kalkulator. |

Kode bersama, dokumen privat, dan skema database diletakkan di samping `public_html` website utama, sehingga tidak bisa dibuka lewat browser:

```
~/domains/ctutama.com/
├── app/              ← kode bersama + config.php
├── database/
├── storage/uploads/  ← dokumen legal & proyek
├── public_html/      ← ctutama.com
└── portal/           ← portal.ctutama.com
~/domains/portal.ctutama.com/
└── public_html  →  symlink ke ~/domains/ctutama.com/portal
```

Symlink dibuat sekali lewat SSH (folder `public_html` bawaan portal dihapus/dipindah dulu):

```bash
mv ~/domains/portal.ctutama.com/public_html ~/domains/portal.ctutama.com/public_html.bak
ln -s ~/domains/ctutama.com/portal ~/domains/portal.ctutama.com/public_html
```

Keduanya memakai satu database, sehingga pesan dari formulir kontak masuk ke portal, dan proyek yang ditandai publik di portal tampil di portofolio website.

## Struktur repo

```
ctu-web/
├── app/                 ← logika bersama (config.php TIDAK masuk git)
├── database/schema.sql
├── storage/uploads/     ← dokumen privat (TIDAK masuk git)
├── public_html/         ← isi untuk ctutama.com
├── portal/              ← isi untuk portal.ctutama.com
├── build-deploy.sh      ← buat ZIP untuk deploy manual
├── deploy.sh            ← deploy otomatis lewat SSH (opsional)
├── SECURITY.md          ← hasil audit keamanan & checklist server
└── .gitignore
```

## Pemasangan pertama (sekali saja)

1. **Website** — di hPanel buat `ctutama.com` dan `portal.ctutama.com` sebagai dua website, lalu buat symlink portal seperti di atas.
2. **SSL** — hPanel » *Keamanan* » *SSL*: pastikan kedua domain aktif.
3. **PHP** — hPanel » *Tingkat lanjut* » *Konfigurasi PHP*: pilih PHP 8.1+ untuk **kedua** website. Di website portal, naikkan `upload_max_filesize` (mis. 25M) dan `post_max_size` (mis. 110M).
4. **Database** — hPanel » *Database* » *Manajemen*: buat database dan user MySQL (di website `ctutama.com`).
5. **SSH** — hPanel » *Tingkat lanjut* » *SSH Access*: aktifkan, catat host, port, dan username.
6. **Kode** — lewat SSH:
   ```bash
   git clone git@github.com:USERNAME/ctu-web.git ~/ctu-web
   cd ~/ctu-web && ./deploy.sh
   cp ~/domains/ctutama.com/app/config.sample.php ~/domains/ctutama.com/app/config.php
   nano ~/domains/ctutama.com/app/config.php      # isi db, site_url, portal_url
   ```
   Untuk repo privat, buat SSH key di server (`ssh-keygen -t ed25519`) dan tambahkan isi `~/.ssh/id_ed25519.pub` ke GitHub sebagai **Deploy key** (repo » *Settings* » *Deploy keys*).
7. **Instalasi** — isi `setup_key` di `config.php` dengan kode acak minimal 16 karakter, buka `https://portal.ctutama.com/setup.php`, masukkan kode itu, lalu buat akun admin pertama. Setelah selesai, **kosongkan lagi `setup_key`**.
8. **Keamanan** — selesaikan checklist di [`SECURITY.md`](SECURITY.md) (Force HTTPS, izin `config.php`, 2FA hPanel).

## Deploy manual (File Manager hPanel)

1. Di komputer, buat paket: `./build-deploy.sh` → menghasilkan `dist/ctu-deploy-TANGGAL.zip`.
   Paket ini berisi satu folder `ctutama.com/` yang strukturnya sama persis dengan server, **tanpa** `config.php`, dokumen unggahan, foto sampul, `.git`, README, atau skrip.
2. hPanel » *File Manager* » buka folder `domains/`.
3. Unggah ZIP ke `domains/`, lalu klik kanan » *Extract* ke folder yang sama (`domains/`). Pilih timpa (overwrite) bila ditanya.
4. Hapus file ZIP dari server setelah diekstrak.
5. Buka website dan portal untuk memastikan semuanya normal.

Ekstrak hanya **menambah dan menimpa** file — file lama yang sudah tidak dipakai tidak ikut terhapus. Bila suatu versi menghapus file, hapus manual lewat File Manager.

**Jangan pernah** mengunggah isi repo apa adanya (termasuk `.git`, `README.md`, `deploy.sh`) ke `public_html` atau folder `portal`. Kalaupun tidak sengaja terunggah, `.htaccess` memblokirnya, tapi lebih aman tidak ada di server sama sekali.

## Deploy otomatis lewat SSH (opsional, nanti)

Bila nanti ingin otomatis: clone repo di server, lalu setiap update jalankan

```bash
cd ~/ctu-web && git pull && ./deploy.sh
```

`deploy.sh` menyalin `app/`, `database/`, `public_html/`, dan `portal/` ke tempatnya. `app/config.php`, `storage/uploads/`, dan `public_html/uploads/covers/` tidak pernah ditimpa atau dihapus. Bila struktur folder di akun Anda berbeda, atur lewat variabel, mis. `PORTAL_DIR=/path/lain ./deploy.sh`.

> **Jangan pakai menu GIT bawaan hPanel** untuk repo ini: menu itu menaruh seluruh repo di dalam `public_html`, sehingga folder `app/` (berisi password database) bisa terbuka di browser.

## Keamanan tambahan untuk portal (opsional)

- **Batasi IP**: buka komentar blok `RequireAny` di `portal/.htaccess` dan isi IP kantor, atau pakai hPanel » *Manajer IP*.
- **Password lapis kedua**: hPanel (website portal) » *Tingkat lanjut* » *Password Protected Directories*.
- Portal sudah memasang `X-Robots-Tag: noindex` dan `robots.txt` agar tidak diindeks mesin pencari. Ingat bahwa nama subdomain tetap bisa ditemukan lewat catatan DNS/SSL publik, jadi keamanan utama tetap login dan kata sandi yang kuat.

Batas ukuran unggahan default 25 MB per file (`max_upload_mb` di config). Sesuaikan juga `upload_max_filesize` dan `post_max_size` di *Konfigurasi PHP* website `portal.ctutama.com`.

## Peran pengguna

| | Admin | Investor |
|---|---|---|
| Dasbor ringkasan | Semua proyek | Proyek yang dibagikan |
| Proyek | Tambah, ubah, hapus, unggah dokumen | Lihat proyek yang dibagikan |
| Dokumen proyek | Semua | Hanya yang ditandai "terlihat oleh investor" |
| Dokumen legal | Unggah, ubah, hapus | Hanya yang ditandai "tampilkan ke investor" |
| Kalkulator margin | Ya | Ya |
| Pesan masuk, Pengguna, Log aktivitas | Ya | Tidak |

Akses investor diatur di halaman detail proyek → kotak **Akses investor**.

## Fitur

- **Website publik:** beranda animasi, halaman per divisi (warna masing-masing dari logo), portofolio dengan filter divisi, formulir kontak (anti-spam: honeypot + jeda waktu + batas 1 pesan/menit), tombol WhatsApp.
- **Portofolio otomatis:** proyek yang dicentang "Tampilkan di halaman portofolio publik" langsung muncul di website. Nilai kontrak dan dokumen tidak pernah tampil publik.
- **Dokumen legal:** kategori (Akta, NIB, NPWP, PKP, SBU, dll.), nomor, penerbit, tanggal terbit, masa berlaku — dasbor memberi peringatan 60 hari sebelum kedaluwarsa.
- **Proyek:** per divisi, status & progres, nilai kontrak, unggah banyak file sekaligus dikelompokkan per jenis (Kontrak/SPK, BAST, Invoice, Faktur Pajak, dll.), unduh semua dalam ZIP. Foto tampil sebagai galeri thumbnail dengan tampilan layar penuh (geser kiri/kanan di HP).
- **Kompresi foto otomatis:** foto yang diunggah diputar tegak, diperkecil ke maks. 2560 px, dikompres (kualitas 82%), dan metadata/GPS dibuang — rata-rata hemat 70–90% tanpa beda yang terlihat. Foto lama bisa dikompres sekaligus dari menu **Penyimpanan** (admin). PDF/Word/Excel tidak diubah.
- **Keamanan:** lihat [`SECURITY.md`](SECURITY.md) — rate limit login & formulir di database, CSP ketat, kunci instalasi, sesi kedaluwarsa, validasi isi file, re-encode gambar, blokir file sensitif, halaman 404.

## Kalkulator margin & net profit

Di portal, menu **Kalkulator Margin** punya dua tab:

- **Per proyek**: pilih proyek, lalu isian langsung terisi dari data proyek (nilai kontrak, jenis dari divisi: Konstruksi → PPh final, Pengadaan → PPh 22, IT → PPh 23 2%). Atur biaya dan pajak, hasilnya dihitung langsung, lalu klik **Simpan ke proyek**. Hasil yang tersimpan (net profit, net margin, laba sebelum pajak, PPh) tampil di detail proyek dan di kolom *Net Margin* daftar proyek. Opsi "Simulasi bebas" untuk hitung cepat tanpa menyimpan.
- **Pajak tahunan** (khusus admin): otomatis mengumpulkan semua proyek yang sudah dihitung pada tahun pajak yang sama, ditambah data tahunan lain (biaya operasional, koreksi fiskal, PPh 25, dll.), lalu menghitung PPh Badan dengan fasilitas Pasal 31E, PPh Pasal 29, dan posisi PPN setahun.

Investor bisa melihat perhitungan proyek yang dibagikan kepadanya dan mencoba angka lain, tetapi tidak bisa menyimpan dan tidak bisa membuka tab tahunan.

Rumus ada di dua file yang harus selalu sama: `assets/js/kalkulator.js` (perhitungan langsung di layar) dan `app/margin.php` (angka yang disimpan ke database). Keduanya sudah diuji identik satu sama lain dan dengan kalkulator HTML aslinya.

Database yang dipasang sebelum fitur ini ada akan otomatis mendapat tabel `project_calculations` dan `annual_tax_inputs` saat admin login pertama kali.

## Mengubah konten

- Teks & layanan tiap divisi: fungsi `divisions()` di `app/helpers.php`.
- Data perusahaan (alamat, telepon, email, akta): bagian `company` di `app/config.php`. Nama direktur sengaja tidak ditampilkan di website.
- Kategori dokumen legal / jenis dokumen proyek: `legal_categories()` dan `project_doc_types()` di `app/helpers.php`.

## Cadangan (backup)

Yang perlu dicadangkan rutin: database (*phpMyAdmin → Export*) dan folder `~/domains/ctutama.com/storage/uploads/` serta `~/domains/ctutama.com/public_html/uploads/covers/`. Backup harian bawaan Hostinger juga mencakup folder-folder ini.
