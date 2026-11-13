<?php
/* ======================================================================
 *  inc/00_dasar.php — PONDASI
 *
 *  Zona waktu, sesi login (cookie), dan dua fungsi jawaban JSON:
 *    out($data)  = kirim jawaban sukses lalu berhenti
 *    err($pesan) = kirim jawaban error lalu berhenti
 *  Juga mask() untuk menyamarkan email (ra****@gmail.com).
 *
 *  MAU MENGUBAH?
 *    • Zona waktu server → ubah date_default_timezone_set di bawah.
 * ====================================================================== */

// ---------------------------------------------------------------- Zona waktu & cadangan fungsi mb_*
date_default_timezone_set('Asia/Makassar');
if (!function_exists('mb_substr')) {   // cadangan jika ekstensi mbstring PHP belum aktif
    function mb_substr($s, $a, $l = null) { return preg_match_all('/./us', $s, $m) ? implode('', array_slice($m[0], $a, $l)) : ''; }
    function mb_strlen($s) { return preg_match_all('/./us', $s); }
}

// ---------------------------------------------------------------- Sesi login (cookie httponly)
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// ---------------------------------------------------------------- Jawaban JSON
function out($d, $c = 200) { http_response_code($c); header('Content-Type: application/json; charset=utf-8'); echo json_encode($d); exit; }
function err($m, $c = 400) { out(['error' => $m], $c); }

// ---------------------------------------------------------------- Penyamar email
function mask($e) { $p = strpos($e, '@'); return substr($e, 0, 2) . str_repeat('*', max(1, $p - 2)) . substr($e, $p); }
