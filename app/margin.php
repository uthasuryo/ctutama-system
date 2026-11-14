<?php
declare(strict_types=1);

/**
 * Kalkulator Margin & Net Profit.
 * Rumus di file ini adalah salinan 1:1 dari assets/js/kalkulator.js (fungsi ctuCalc).
 * Bila mengubah salah satunya, ubah juga yang lain.
 */

const MARGIN_JENIS = [
    'b' => 'Pengadaan barang',
    'k' => 'Konstruksi',
    'j' => 'Jasa / IT',
];

const MARGIN_K_RATES = [
    '1.75' => '1,75% – pelaksana, SBU kecil',
    '2.65' => '2,65% – pelaksana menengah/besar, atau terintegrasi ber-SBU',
    '4'    => '4% – tanpa SBU/SKK, atau terintegrasi tanpa SBU',
    '3.5'  => '3,5% – konsultansi ber-SBU',
    '6'    => '6% – konsultansi tanpa SBU',
];

/** Baris biaya bawaan per jenis proyek. */
function margin_default_rows(string $jenis, float $base = 100000000): array
{
    $r = fn($pct) => round($base * $pct);
    return match ($jenis) {
        'k' => [
            ['n' => 'Material (beli dari vendor PKP)', 'a' => $r(.40), 'f' => true],
            ['n' => 'Upah tukang & subkontraktor', 'a' => $r(.25), 'f' => false],
            ['n' => 'Sewa alat & biaya lain', 'a' => $r(.05), 'f' => false],
        ],
        'j' => [
            ['n' => 'Perangkat / lisensi (vendor PKP)', 'a' => $r(.35), 'f' => true],
            ['n' => 'Tenaga ahli / developer', 'a' => $r(.25), 'f' => false],
            ['n' => 'Biaya lain (cloud, transport, dll.)', 'a' => $r(.05), 'f' => false],
        ],
        default => [
            ['n' => 'Harga barang (HPP)', 'a' => $r(.60), 'f' => true],
            ['n' => 'Ongkos kirim / logistik', 'a' => $r(.02), 'f' => false],
            ['n' => 'Biaya lain (admin, garansi, dll.)', 'a' => $r(.01), 'f' => false],
        ],
    };
}

/** Isian awal untuk proyek yang belum pernah dihitung. */
function margin_defaults(?array $project = null): array
{
    $jenis = match ($project['division'] ?? 'pengadaan') {
        'konstruksi' => 'k',
        'it'         => 'j',
        default      => 'b',
    };
    $jual = (float)($project['contract_value'] ?? 0) ?: 100000000;
    $date = $project['start_date'] ?? $project['created_at'] ?? null;
    return [
        'name'     => $project['name'] ?? 'Simulasi',
        'jenis'    => $jenis,
        'jual'     => $jual,
        'jualMode' => 'ex',
        'ppn'      => 11,
        'wh'       => $jenis === 'j' ? 2 : 0,
        'kRate'    => '2.65',
        'pem'      => 0,
        'costMode' => 'ex',
        'rows'     => margin_default_rows($jenis, $jual),
        'scheme'   => 'badan',
        'rate'     => 22,
        'target'   => 10,
        'year'     => (int)($date ? date('Y', strtotime((string)$date)) : date('Y')),
    ];
}

/** Bersihkan isian dari browser agar aman disimpan & dihitung. */
function margin_normalize(array $in): array
{
    $num = fn($v) => is_numeric($v) ? (float)$v : 0.0;
    $rows = [];
    foreach (array_slice((array)($in['rows'] ?? []), 0, 60) as $row) {
        if (!is_array($row)) continue;
        $rows[] = [
            'n' => mb_substr(trim((string)($row['n'] ?? '')), 0, 120),
            'a' => max(0, $num($row['a'] ?? 0)),
            'f' => !empty($row['f']),
        ];
    }
    $jenis = isset(MARGIN_JENIS[$in['jenis'] ?? '']) ? $in['jenis'] : 'b';
    $year = (int)($in['year'] ?? date('Y'));
    return [
        'name'     => mb_substr(trim((string)($in['name'] ?? '')), 0, 200),
        'jenis'    => $jenis,
        'jual'     => max(0, $num($in['jual'] ?? 0)),
        'jualMode' => ($in['jualMode'] ?? '') === 'in' ? 'in' : 'ex',
        'ppn'      => (int)($in['ppn'] ?? 11) === 12 ? 12 : 11,
        'wh'       => min(100, max(0, $num($in['wh'] ?? 0))),
        'kRate'    => isset(MARGIN_K_RATES[(string)($in['kRate'] ?? '')]) ? (string)$in['kRate'] : '2.65',
        'pem'      => !empty($in['pem']) ? 1 : 0,
        'costMode' => ($in['costMode'] ?? '') === 'in' ? 'in' : 'ex',
        'rows'     => $rows,
        'scheme'   => ($in['scheme'] ?? '') === 'final' ? 'final' : 'badan',
        'rate'     => min(100, max(0, $num($in['rate'] ?? 22))),
        'target'   => min(99, max(0, $num($in['target'] ?? 10))),
        'year'     => ($year >= 2000 && $year <= 2100) ? $year : (int)date('Y'),
    ];
}

/** Hitung margin, pajak, dan arus kas satu proyek. */
function margin_calc(array $i): array
{
    $r = $i['ppn'] / 100;
    $S = $i['jual'];
    $net = $i['jualMode'] === 'in' ? $S / (1 + $r) : $S;
    $ppnOut = $net * $r;
    $total = $net + $ppnOut;
    $inc = $i['costMode'] === 'in';

    $cost = $ppnIn = $hpp = $cashOut = 0.0;
    foreach ($i['rows'] as $k => $x) {
        $a = (float)$x['a'];
        $n = $a;
        $p = 0.0;
        if ($x['f']) {
            if ($inc) { $n = $a / (1 + $r); $p = $a - $n; } else { $p = $a * $r; }
        }
        $cost += $n; $ppnIn += $p; $cashOut += $n + $p;
        if ($k === 0) $hpp = $n;
    }

    $gp = $net - $hpp;
    $ebt = $net - $cost;
    $kon = $i['jenis'] === 'k';
    $scheme = $kon ? 'final' : $i['scheme'];
    $t = ($kon ? (float)$i['kRate'] : $i['rate']) / 100;
    $pph = $scheme === 'badan' ? max(0, $ebt) * $t : $net * $t;
    $np = $ebt - $pph;
    $wh = $net * ($kon ? $t : $i['wh'] / 100);
    $m = $i['target'] / 100;

    $pem = (bool)$i['pem'];
    $pn = $pem ? -$ppnIn : $ppnOut - $ppnIn;
    $cashIn = ($pem ? $net : $total) - $wh;
    $setor = $pem ? 0 : max(0, $ppnOut - $ppnIn);
    $held = $pem ? $ppnIn : max(0, $ppnIn - $ppnOut);
    $saldo = $cashIn - $cashOut - $setor;
    $pphLeft = $kon ? 0 : ($scheme === 'badan' ? $pph - $wh : $pph);

    $den = 1 - $t - $m;
    $need = $den > 0 ? ($scheme === 'badan' ? $cost * (1 - $t) / $den : $cost / $den) : null;
    $pct = fn($a, $b) => $b ? $a / $b * 100 : null;

    return [
        'net' => $net, 'ppnOut' => $ppnOut, 'total' => $total, 'cost' => $cost, 'ppnIn' => $ppnIn,
        'hpp' => $hpp, 'cashOut' => $cashOut, 'gp' => $gp, 'gm' => $pct($gp, $net),
        'ebt' => $ebt, 'ebtm' => $pct($ebt, $net), 'markup' => $pct($ebt, $cost),
        'kon' => $kon, 'scheme' => $scheme, 't' => $t, 'pph' => $pph, 'np' => $np, 'npm' => $pct($np, $net),
        'wh' => $wh, 'pem' => $pem, 'pn' => $pn, 'ppnByClient' => $pem ? $ppnOut : 0,
        'cashIn' => $cashIn, 'setor' => $setor, 'held' => $held,
        'restMasa' => $pem ? $held : 0, 'restAkhir' => $pem ? 0 : $held,
        'saldo' => $saldo, 'ret' => $cashOut ? min(100, max(0, ($cashIn - $setor) / $cashOut * 100)) : null,
        'pphLeft' => $pphLeft, 'fin' => $saldo + $held - $pphLeft,
        'need' => $need, 'needIncl' => $need !== null ? $need * (1 + $r) : null,
    ];
}

/** Bawaan isian tahunan. */
function annual_defaults(): array
{
    return ['omLain' => 0, 'opex' => 0, 'kor' => 0, 'komp' => 0, 'p23' => 0, 'p25' => 0, 'rate' => 22];
}

function annual_normalize(array $in): array
{
    $out = [];
    foreach (annual_defaults() as $k => $d) {
        $v = $in[$k] ?? $d;
        $v = is_numeric($v) ? (float)$v : (float)$d;
        $out[$k] = $k === 'kor' ? $v : max(0, $v);
    }
    $out['rate'] = min(100, $out['rate']);
    return $out;
}

/** Buat tabel kalkulator bila database dipasang sebelum fitur ini ada. */
function ensure_schema(): void
{
    if (!empty($_SESSION['_schema_v2'])) return;
    $has = (bool)q_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'project_calculations'");
    if (!$has) {
        db()->exec(file_get_contents(dirname(APP_ROOT) . '/database/schema.sql'));
    }
    $_SESSION['_schema_v2'] = true;
}

/** Ringkasan margin tersimpan untuk satu proyek (atau null). */
function project_margin(int $projectId): ?array
{
    return q_one('SELECT * FROM project_calculations WHERE project_id = ?', [$projectId]);
}
