<?php
/* ======================================================================
 *  inc/06_kunci_login.php — KUNCI SEMENTARA LOGIN (salah sandi)
 *
 *  Setelah MAKS_GAGAL kali salah sandi, email itu dikunci. Akun TIDAK dihapus.
 *  Terbuka otomatis, atau lebih cepat lewat persetujuan Super Admin + OTP.
 *
 *  MAU MENGUBAH?
 *    • Berapa kali boleh salah sandi → MAKS_GAGAL.
 *    • Berapa lama dikunci          → MENIT_KUNCI (1440 menit = 1 hari).
 *    • Jendela hitung salah sandi   → MENIT_JENDELA.
 *    • Tulisan "3 kali"/"1 hari" di pesan tampilan ikut disesuaikan manual di js/3-akun.js.
 * ====================================================================== */

// ---------------------------------------------------------------- Angka pengaturan kunci
// Kunci sementara: setelah MAKS_GAGAL kali salah sandi, login untuk email itu dikunci selama MENIT_KUNCI menit.
// Akun TIDAK dihapus. Kunci terbuka sendiri setelah waktunya habis, atau lebih cepat lewat persetujuan Super Admin + kode OTP.
const MAKS_GAGAL = 3;
const MENIT_KUNCI = 1440;   // lama kunci: 1 hari
const MENIT_JENDELA = 30;   // salah sandi yang berselang lebih dari ini tidak dijumlahkan

// ---------------------------------------------------------------- Fungsi kunci
function kunci_aktif($e) {   // baris kunci bila email ini sedang dikunci, selain itu null
    $k = q('SELECT *, COALESCE(GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),kunci_sampai)),0) sisa FROM login_lock WHERE email=?', [$e])->fetch();
    return ($k && (int) $k['sisa'] > 0) ? $k : null;
}
function gagal_login($e) {   // catat satu salah sandi. Hasil: sisa percobaan, atau 0 jika akun baru saja dikunci
    $m = (int) MENIT_KUNCI; $w = (int) MENIT_JENDELA;
    q("INSERT INTO login_lock(email,gagal,updated) VALUES(?,1,NOW())
       ON DUPLICATE KEY UPDATE gagal=IF(updated<DATE_SUB(NOW(),INTERVAL $w MINUTE),1,gagal+1), updated=NOW()", [$e]);   // hitungan gagal direset bila percobaan sebelumnya sudah lewat $w menit
    $g = (int) q('SELECT gagal FROM login_lock WHERE email=?', [$e])->fetchColumn();
    if ($g < MAKS_GAGAL) return MAKS_GAGAL - $g;
    q("UPDATE login_lock SET kunci_sampai=DATE_ADD(NOW(),INTERVAL $m MINUTE), minta=NULL, setuju=0, setuju_oleh=NULL WHERE email=?", [$e]);
    catat('akun_terkunci', "$g kali salah sandi, dikunci 1 hari", null, $e);
    return 0;
}
function kunci_tolak($e, $sisa) {   // jawaban 423 "terkunci" untuk percobaan login
    out(['error' => 'Akun dikunci sementara karena ' . MAKS_GAGAL . ' kali salah sandi. Akun TIDAK dihapus. Kunci terbuka otomatis, atau minta aktivasi ulang ke Super Admin.',
         'terkunci' => 1, 'sisa' => (int) $sisa, 'email' => $e], 423);
}
