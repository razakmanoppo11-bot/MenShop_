<?php
/* ======================================================================
 *  inc/05_otp.php — KODE OTP
 *
 *  otp_wajib()        = apakah OTP dipakai? (dibaca dari saklar di config.php)
 *  buat_sesi_login()  = membuat sesi login setelah lolos
 *  buat_otp()         = membuat kode 6 digit, menyimpan hash-nya, mengirim ke email
 *  tunggu()           = jeda 60 detik sebelum boleh kirim ulang
 *  proses_lupa()      = alur "Lupa sandi"
 *  Pemeriksaan kode yang diketik pengguna ada di aksi/akun.php → aksi_verify_otp().
 *
 *  MAU MENGUBAH?
 *    • MATIKAN OTP LOGIN / DAFTAR → cukup ubah true jadi false di config.php ($FITUR). Tidak perlu edit file ini.
 *    • Masa berlaku kode (5 menit)  → "INTERVAL 5 MINUTE" di buat_otp() di bawah.
 *    • Jeda kirim ulang (60 detik)  → angka 60 di tunggu() di bawah (dan di js/3-akun.js → hitungMundurKirimUlang).
 *    • Batas salah ketik kode (5x)  → "attempts" di aksi/akun.php → aksi_verify_otp().
 * ====================================================================== */


// ---------------------------------------------------------------- SAKLAR OTP (dibaca dari config.php → $FITUR)
// Mengembalikan true bila OTP WAJIB untuk keperluan ini.
//   otp_wajib('login', 'admin')  → apakah login Admin pakai OTP?
//   otp_wajib('daftar')          → apakah pendaftaran pembeli pakai OTP?
// Reset sandi & aktivasi akun terkunci SELALU wajib OTP (tidak bisa dimatikan, demi keamanan).
// Jika saklar tidak ditemukan di config.php, OTP tetap WAJIB (default aman).
function otp_wajib($tujuan, $role = null) {
    global $FITUR;
    if ($tujuan === 'login')  return !isset($FITUR['otp_login'][$role]) || !empty($FITUR['otp_login'][$role]);
    if ($tujuan === 'daftar') return !isset($FITUR['otp_daftar']) || !empty($FITUR['otp_daftar']);
    return true;
}

// Membuat sesi login (dipakai setelah OTP benar, atau langsung bila OTP login dimatikan).
function buat_sesi_login($u, $email) {
    session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
    catat('login', 'peran ' . $u['role'], $u['id'], $email);
}

// ---------------------------------------------------------------- Jeda kirim ulang
function tunggu($e, $p) {   // batas kirim ulang 60 detik
    $s = q('SELECT 60-TIMESTAMPDIFF(SECOND,created,NOW()) FROM otp_codes WHERE email=? AND purpose=?', [$e, $p])->fetchColumn();
    if ($s !== false && $s > 0) err("Tunggu $s detik sebelum meminta kode baru.", 429);
}

// ---------------------------------------------------------------- Membuat & mengirim kode
function buat_otp($e, $p, $x = []) {
    $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    q('DELETE FROM otp_codes WHERE expires_at<NOW()');
    q('DELETE FROM otp_codes WHERE email=? AND purpose=?', [$e, $p]);
    q('INSERT INTO otp_codes(email,purpose,role,nama,password,code_hash,expires_at,created) VALUES(?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NOW())',
      [$e, $p, $x['role'] ?? null, $x['nama'] ?? null, $x['password'] ?? null, password_hash($kode, PASSWORD_DEFAULT)]);
    try { kirim_otp($e, $kode, $p); }
    catch (Throwable $t) {
        q('DELETE FROM otp_codes WHERE email=? AND purpose=?', [$e, $p]);
        error_log('Men\'Shop OTP: ' . $t->getMessage());
        global $SMTP;
        $detail = !empty($SMTP['debug']) ? ' Detail: ' . $t->getMessage() : '';
        err('Gagal mengirim email OTP. Periksa pengaturan email (SMTP) di config.php.' . $detail, 500);
    }
    out(['otp' => 1, 'email' => $e, 'masked' => mask($e), 'purpose' => $p]);
}

// ---------------------------------------------------------------- Alur lupa sandi
function proses_lupa($e) {   // jawaban selalu sama agar orang lain tidak bisa menebak email mana yang terdaftar
    if (!filter_var($e, FILTER_VALIDATE_EMAIL)) err('Masukkan email yang valid.');
    $u = q('SELECT id FROM users WHERE email=? AND aktif=1', [$e])->fetch();
    if (!$u) { catat('lupa_sandi_tak_dikenal', '', null, $e); out(['otp' => 1, 'email' => $e, 'masked' => mask($e), 'purpose' => 'reset']); }
    tunggu($e, 'reset');
    catat('lupa_sandi', 'minta kode reset', $u['id'], $e);
    buat_otp($e, 'reset');
}
