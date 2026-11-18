#!/usr/bin/env bash
# =============================================================
#  Deploy website + portal PT Candaka Tiga Utama (Hostinger)
#  Jalankan lewat SSH dari folder repo:   ./deploy.sh
#
#  Yang TIDAK pernah ditimpa/dihapus:
#    app/config.php, storage/uploads/, public_html/uploads/covers/
# =============================================================
set -euo pipefail

SRC="$(cd "$(dirname "$0")" && pwd)"

# Lokasi di server (bawaan Hostinger). Ubah bila struktur akun Anda berbeda.
BASE="${BASE:-$HOME/domains/ctutama.com}"                       # app/, storage/, database/ di sini
SITE_DIR="${SITE_DIR:-$BASE/public_html}"                       # ctutama.com
PORTAL_DIR="${PORTAL_DIR:-$BASE/portal}"                        # portal.ctutama.com
# (public_html portal.ctutama.com adalah symlink ke folder ini:
#  ln -s ~/domains/ctutama.com/portal ~/domains/portal.ctutama.com/public_html)

echo "▸ Sumber   : $SRC"
echo "▸ App      : $BASE/app"
echo "▸ Website  : $SITE_DIR"
echo "▸ Portal   : $PORTAL_DIR"

[ -d "$SITE_DIR" ] || { echo "✗ Folder $SITE_DIR tidak ada. Pastikan website-nya sudah dibuat di hPanel." >&2; exit 1; }

mkdir -p "$PORTAL_DIR" "$BASE/app" "$BASE/database" "$BASE/storage/uploads/legal" "$BASE/storage/uploads/projects" "$SITE_DIR/uploads/covers"

rsync -a --delete --exclude 'config.php' "$SRC/app/" "$BASE/app/"
rsync -a --delete "$SRC/database/" "$BASE/database/"
rsync -a "$SRC/storage/.htaccess" "$BASE/storage/.htaccess"
rsync -a --delete \
  --exclude 'uploads/covers/' --exclude '.well-known/' --exclude 'cgi-bin/' --exclude '.htaccess.hostinger' \
  "$SRC/public_html/" "$SITE_DIR/"
rsync -a --delete --exclude '.well-known/' "$SRC/portal/" "$PORTAL_DIR/"

chmod 750 "$BASE/storage" "$BASE/storage/uploads" "$BASE/storage/uploads/legal" "$BASE/storage/uploads/projects" 2>/dev/null || true

if [ ! -f "$BASE/app/config.php" ]; then
  echo "! app/config.php belum ada. Salin dulu:"
  echo "    cp $BASE/app/config.sample.php $BASE/app/config.php && nano $BASE/app/config.php"
fi
echo "✓ Deploy selesai — $(git -C "$SRC" log -1 --format='%h %s' 2>/dev/null || echo 'tanpa git')"
