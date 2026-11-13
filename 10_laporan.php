<?php
/* ======================================================================
 *  inc/10_laporan.php — PEMBANTU LAPORAN & CSV
 *
 *  rentang()  = membaca tanggal ?from= & ?to= (bawaan: 30 hari terakhir)
 *  csv_aman() = mencegah rumus Excel berbahaya di file CSV
 * ====================================================================== */

function rentang() {
    $ok = fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
    $a = $_GET['from'] ?? ''; $b = $_GET['to'] ?? '';
    if (!$ok($b)) $b = date('Y-m-d');
    if (!$ok($a)) $a = date('Y-m-d', strtotime('-29 days'));
    if ($a > $b) [$a, $b] = [$b, $a];
    return [$a, $b];
}
function csv_aman($v) { $v = (string) $v; return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v; }   // cegah formula Excel
