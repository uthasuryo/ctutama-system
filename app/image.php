<?php
declare(strict_types=1);

/* =============================================================
   Optimasi gambar: kompres, perkecil, putar sesuai EXIF, thumbnail.
   Memakai ekstensi GD (tersedia di Hostinger).
   ============================================================= */

const IMG_DOC_MAX_SIDE   = 2560;  // sisi terpanjang foto dokumen (cukup untuk membaca nota/serial number)
const IMG_DOC_QUALITY    = 82;    // kualitas JPEG/WebP — tidak terlihat bedanya oleh mata, ukuran jauh lebih kecil
const IMG_COVER_MAX_SIDE = 2000;
const IMG_THUMB_SIDE     = 480;
const IMG_MAX_PIXELS     = 60_000_000; // lewati gambar raksasa agar memori server aman

function gd_ready(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
}

/** Orientasi EXIF (1–8) dari JPEG. Pakai ekstensi exif bila ada, bila tidak baca manual. */
function image_orientation(string $path): int
{
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($path, 'IFD0');
        if (!empty($exif['Orientation'])) return (int)$exif['Orientation'];
        return 1;
    }
    $fh = @fopen($path, 'rb');
    if (!$fh) return 1;
    $data = fread($fh, 131072);
    fclose($fh);
    if (!$data || substr($data, 0, 2) !== "\xFF\xD8") return 1;
    $pos = 2;
    $len = strlen($data);
    while ($pos + 4 < $len) {
        if ($data[$pos] !== "\xFF") return 1;
        $marker = ord($data[$pos + 1]);
        $size = unpack('n', substr($data, $pos + 2, 2))[1];
        if ($marker === 0xE1 && substr($data, $pos + 4, 6) === "Exif\0\0") {
            $tiff = $pos + 10;
            $le = substr($data, $tiff, 2) === 'II';
            $u16 = fn($o) => unpack($le ? 'v' : 'n', substr($data, $o, 2))[1];
            $u32 = fn($o) => unpack($le ? 'V' : 'N', substr($data, $o, 4))[1];
            $ifd = $tiff + $u32($tiff + 4);
            $n = $u16($ifd);
            for ($i = 0; $i < $n; $i++) {
                $e = $ifd + 2 + $i * 12;
                if ($e + 12 > $len) break;
                if ($u16($e) === 0x0112) return max(1, min(8, $u16($e + 8)));
            }
            return 1;
        }
        if ($marker === 0xDA) return 1; // awal data gambar, tidak ada EXIF
        $pos += 2 + $size;
    }
    return 1;
}

function image_load(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png'  => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default      => false,
    };
}

/** Putar/cermin gambar sesuai orientasi EXIF agar tampil tegak setelah metadata dibuang. */
function image_apply_orientation($img, int $o)
{
    if ($o === 1) return $img;
    if (in_array($o, [2, 4, 5, 7], true) && function_exists('imageflip')) {
        imageflip($img, $o === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
    }
    $angle = match ($o) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 };
    if ($angle) {
        $rot = imagerotate($img, $angle, 0);
        if ($rot) { imagedestroy($img); $img = $rot; }
    }
    return $img;
}

function image_resize($img, int $maxSide)
{
    $w = imagesx($img); $h = imagesy($img);
    $scale = $maxSide / max($w, $h);
    if ($scale >= 1) return $img;
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    return $dst;
}

function image_save($img, string $path, string $mime, int $quality): bool
{
    return match ($mime) {
        'image/png'  => (function () use ($img, $path) { imagesavealpha($img, true); return imagepng($img, $path, 9); })(),
        'image/webp' => function_exists('imagewebp') && imagewebp($img, $path, $quality),
        default      => (function () use ($img, $path, $quality) { imageinterlace($img, true); return imagejpeg($img, $path, $quality); })(),
    };
}

/**
 * Kompres gambar di tempat.
 * - Diputar sesuai EXIF, metadata (termasuk lokasi GPS) dibuang.
 * - Diperkecil bila sisi terpanjang > $maxSide.
 * - File asli hanya diganti bila hasilnya lebih kecil (min. 10%), diperkecil, diputar, atau $force.
 *
 * @return array{before:int, after:int, changed:bool}|null  null bila bukan gambar valid
 */
function optimize_image(string $path, string $mime, int $maxSide = IMG_DOC_MAX_SIDE, int $quality = IMG_DOC_QUALITY, bool $force = false): ?array
{
    $before = (int)@filesize($path);
    if (!gd_ready()) return ['before' => $before, 'after' => $before, 'changed' => false];
    $info = @getimagesize($path);
    if (!$info || $info[0] < 1 || $info[1] < 1) return null;
    if ($info[0] * $info[1] > IMG_MAX_PIXELS) return ['before' => $before, 'after' => $before, 'changed' => false];

    @ini_set('memory_limit', '512M');
    $img = image_load($path, $mime);
    if (!$img) return null;

    $orientation = $mime === 'image/jpeg' ? image_orientation($path) : 1;
    $img = image_apply_orientation($img, $orientation);
    $resized = max(imagesx($img), imagesy($img)) > $maxSide;
    $img = image_resize($img, $maxSide);

    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $ok = image_save($img, $tmp, $mime, $quality);
    imagedestroy($img);
    if (!$ok || !is_file($tmp)) { @unlink($tmp); return null; }

    $after = (int)filesize($tmp);
    $worth = $force || $resized || $orientation !== 1 || $after <= $before * 0.9;
    if ($worth && @rename($tmp, $path)) {
        @chmod($path, 0640);
        clearstatcache(true, $path);
        return ['before' => $before, 'after' => $after, 'changed' => true];
    }
    @unlink($tmp);
    return ['before' => $before, 'after' => $before, 'changed' => false];
}

/** Lokasi thumbnail untuk dokumen privat (di storage/thumbs, tidak bisa diakses langsung). */
function thumb_path(string $relPath): string
{
    return rtrim((string)config('storage_path'), '/') . '/thumbs/' . preg_replace('/\.\w+$/', '.jpg', $relPath);
}

/** Buat thumbnail JPEG kecil (latar putih untuk PNG transparan). */
function make_thumb(string $src, string $mime, string $dst, int $side = IMG_THUMB_SIDE): bool
{
    if (!gd_ready() || !is_file($src)) return false;
    $info = @getimagesize($src);
    if (!$info || $info[0] * $info[1] > IMG_MAX_PIXELS) return false;
    @ini_set('memory_limit', '512M');
    $img = image_load($src, $mime);
    if (!$img) return false;
    if ($mime === 'image/jpeg') $img = image_apply_orientation($img, image_orientation($src));
    $img = image_resize($img, $side);
    $w = imagesx($img); $h = imagesy($img);
    $out = imagecreatetruecolor($w, $h);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopy($out, $img, 0, 0, 0, 0, $w, $h);
    imagedestroy($img);
    if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0750, true);
    imageinterlace($out, true);
    $ok = imagejpeg($out, $dst, 72);
    imagedestroy($out);
    return $ok;
}

function delete_thumb(string $relPath): void
{
    $t = thumb_path($relPath);
    if (is_file($t)) @unlink($t);
}

function is_image_mime(?string $mime): bool
{
    return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
}
