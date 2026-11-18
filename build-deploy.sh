#!/usr/bin/env bash
# =============================================================
#  Buat paket ZIP untuk deploy MANUAL lewat File Manager Hostinger.
#  Hasil: dist/ctu-deploy-YYYYMMDD-HHMM.zip berisi folder "ctutama.com/"
#  yang strukturnya sama persis dengan ~/domains/ctutama.com di server.
#
#  Isi paket TIDAK menyertakan: app/config.php, dokumen unggahan,
#  foto sampul, .git, README, skrip, dan file tersembunyi lain.
# =============================================================
set -euo pipefail
SRC="$(cd "$(dirname "$0")" && pwd)"
STAMP="$(date +%Y%m%d-%H%M)"
WORK="$(mktemp -d)"
OUT="$SRC/dist/ctu-deploy-$STAMP.zip"
DEST="$WORK/ctutama.com"

mkdir -p "$DEST" "$SRC/dist"
rsync -a --exclude 'config.php' "$SRC/app/" "$DEST/app/"
rsync -a "$SRC/database/" "$DEST/database/"
mkdir -p "$DEST/storage/uploads/legal" "$DEST/storage/uploads/projects"
cp "$SRC/storage/.htaccess" "$DEST/storage/.htaccess"
rsync -a --exclude 'uploads/covers/*' "$SRC/public_html/" "$DEST/public_html/"
mkdir -p "$DEST/public_html/uploads/covers"
rsync -a "$SRC/portal/" "$DEST/portal/"

# Buang file yang tidak boleh ikut ke server
find "$DEST" \( -name '.gitkeep' -o -name '.DS_Store' -o -name 'Thumbs.db' -o -name '*.log' \) -delete

(cd "$WORK" && zip -rqX "$OUT" ctutama.com)
rm -rf "$WORK"
echo "✓ $OUT"
unzip -l "$OUT" | tail -1
