<?php
declare(strict_types=1);

/* =============================================================
   Halaman proyek: checklist dokumen per tahap, album foto,
   dan unggah file bersama.
   ============================================================= */

const PHOTO_DOC_TYPE = 'Foto Dokumentasi';

function checklist_stages(): array
{
    return [
        'awal'        => 'Penawaran & kontrak',
        'pelaksanaan' => 'Pelaksanaan',
        'serah'       => 'Serah terima',
        'tagihan'     => 'Penagihan & pajak',
        'bayar'       => 'Pembayaran',
    ];
}

/**
 * Template standar per divisi. 'type' = nilai doc_type yang disimpan (cocok dengan data lama),
 * 'also' = jenis lama lain yang dianggap memenuhi item ini.
 */
function checklist_template(string $division): array
{
    $it = fn($stage, $type, $label = null, $optional = false, $also = []) =>
        ['stage' => $stage, 'type' => $type, 'label' => $label ?? $type, 'optional' => $optional, 'also' => $also];
    $tagihan = [
        $it('tagihan', 'Invoice'),
        $it('tagihan', 'Faktur Pajak', 'Faktur pajak'),
        $it('tagihan', 'Bukti Potong', 'Bukti potong PPh'),
        $it('bayar', 'Bukti Pembayaran', 'Bukti pembayaran'),
    ];
    $bast = $it('serah', 'Berita Acara (BAST)', 'Berita acara serah terima (BAST)');
    return match ($division) {
        'konstruksi' => [
            $it('awal', 'Surat Penawaran', 'Surat penawaran'),
            $it('awal', 'RAB / Perhitungan Biaya', 'RAB / perhitungan biaya'),
            $it('awal', 'Kontrak / SPK', 'Kontrak / SPK', false, ['Purchase Order']),
            $it('awal', 'Addendum', 'Addendum', true),
            $it('pelaksanaan', 'Laporan Progres', 'Laporan progres / opname'),
            $bast, ...$tagihan,
        ],
        'it' => [
            $it('awal', 'Surat Penawaran', 'Surat penawaran'),
            $it('awal', 'Kontrak / SPK', 'Kontrak / SPK / PO', false, ['Purchase Order']),
            $it('pelaksanaan', 'Berita Acara Instalasi / UAT', 'Berita acara instalasi / UAT'),
            $it('pelaksanaan', 'Laporan Progres', 'Laporan progres', true),
            $bast, ...$tagihan,
        ],
        default => [ // pengadaan barang
            $it('awal', 'Surat Penawaran', 'Surat penawaran'),
            $it('awal', 'Kontrak / SPK', 'Kontrak / SPK / PO', false, ['Purchase Order']),
            $it('pelaksanaan', 'Surat Jalan', 'Surat jalan'),
            $bast, ...$tagihan,
        ],
    };
}

/**
 * Susun checklist proyek: template + item tambahan − item yang tidak diperlukan,
 * lalu pasangkan dengan dokumen yang sudah diunggah.
 *
 * @param array $docs  semua dokumen non-album proyek (untuk status), sudah termasuk yang tersembunyi
 * @return array{stages: array, others: array, done: int, total: int, missing: array, types: array}
 */
function build_checklist(array $project, array $docs, bool $admin): array
{
    $adj = q_all('SELECT doc_type, kind, stage FROM project_checklist WHERE project_id = ? ORDER BY id', [$project['id']]);
    $skipped = array_column(array_filter($adj, fn($a) => $a['kind'] === 'skip'), 'doc_type');
    $items = checklist_template($project['division']);
    foreach ($adj as $a) {
        if ($a['kind'] === 'custom') {
            $items[] = ['stage' => isset(checklist_stages()[$a['stage']]) ? $a['stage'] : 'pelaksanaan', 'type' => $a['doc_type'], 'label' => $a['doc_type'], 'optional' => false, 'also' => [], 'custom' => true];
        }
    }

    $byType = [];
    foreach ($docs as $d) $byType[$d['doc_type']][] = $d;
    $claimed = [];
    $stages = array_fill_keys(array_keys(checklist_stages()), []);
    $done = $total = 0;
    $missing = [];
    foreach ($items as $it) {
        $all = [];
        foreach (array_merge([$it['type']], $it['also']) as $t) {
            if (!empty($byType[$t])) { $all = array_merge($all, $byType[$t]); $claimed[$t] = true; }
        }
        $it['skipped'] = in_array($it['type'], $skipped, true);
        $it['custom'] = $it['custom'] ?? false;
        $it['count'] = count($all);
        $it['docs'] = $admin ? $all : array_values(array_filter($all, fn($d) => $d['visible_to_investor']));
        $it['hidden'] = $it['count'] - count($it['docs']);
        $it['slug'] = 'item-' . substr(md5($it['type']), 0, 8);
        if (!$it['skipped'] && (!$it['optional'] || $it['count'])) {
            $total++;
            if ($it['count']) $done++; else $missing[] = $it;
        }
        $stages[$it['stage']][] = $it;
    }
    $others = [];
    foreach ($byType as $t => $list) {
        if (isset($claimed[$t])) continue;
        foreach ($list as $d) if ($admin || $d['visible_to_investor']) $others[] = $d;
    }
    return ['stages' => $stages, 'others' => $others, 'done' => $done, 'total' => $total, 'missing' => $missing, 'types' => array_column($items, 'type')];
}

/**
 * Simpan beberapa file unggahan ke proyek.
 * @return array{0:int, 1:array} [jumlah tersimpan, daftar error]
 */
/** $files: daftar dari request_files('files'). */
function save_project_uploads(int $projectId, array $files, array $meta, array $allowed = DOC_TYPES): array
{
    $n = count($files);
    if ($n === 0) {
        return [0, ['Pilih file yang akan diunggah.']];
    }
    $saved = 0;
    $errors = [];
    $title = trim((string)($meta['title'] ?? ''));
    $offset = (int)($meta['number_from'] ?? 0);
    foreach ($files as $one) {
        [$file, $err] = store_upload($one, config('storage_path'), 'projects/' . $projectId, $allowed);
        if ($err) { $errors[] = basename((string)$one['name']) . ': ' . $err; continue; }
        optimize_stored_upload($file, 'proyek');
        $num = $offset + $saved + 1;
        $docTitle = $title !== '' ? (($n > 1 || $offset) ? $title . ' (' . $num . ')' : $title) : pathinfo($file['original'], PATHINFO_FILENAME);
        q('INSERT INTO project_documents (project_id, album_id, title, doc_type, doc_date, notes, visible_to_investor, file_path, original_name, mime_type, file_size, uploaded_by)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [
            $projectId, $meta['album_id'] ?? null, mb_substr($docTitle, 0, 200), mb_substr((string)$meta['doc_type'], 0, 100),
            $meta['doc_date'] ?? null, $meta['notes'] ?? null, !empty($meta['visible']) ? 1 : 0,
            $file['path'], $file['original'], $file['mime'], $file['size'], current_user()['id'] ?? null,
        ]);
        $saved++;
    }
    if ($saved) q('UPDATE projects SET updated_at = NOW() WHERE id = ?', [$projectId]);
    return [$saved, $errors];
}

function delete_project_doc(array $doc): void
{
    q('DELETE FROM project_documents WHERE id = ?', [$doc['id']]);
    delete_stored($doc['file_path']);
    delete_thumb($doc['file_path']);
}
