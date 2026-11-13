<?php
/* ======================================================================
 *  config.php — PUSAT PENGATURAN MEN'SHOP
 *  Hampir semua hal yang ingin Anda ubah cepat ada di file ini.
 *
 *    BAGIAN 1  Database MySQL
 *    BAGIAN 2  Email (Gmail SMTP) untuk kirim kode OTP
 *    BAGIAN 3  SAKLAR FITUR  ← mematikan/menyalakan OTP ada di sini
 * ====================================================================== */


// ======================================================================
//  BAGIAN 1 — DATABASE (XAMPP bawaan: user root, tanpa sandi)
// ======================================================================
$DB = ['host' => '127.0.0.1', 'name' => 'menshop', 'user' => 'root', 'pass' => ''];


// ======================================================================
//  BAGIAN 2 — EMAIL (Gmail SMTP)
// ======================================================================
$SMTP = [
    'host'      => 'smtp.gmail.com',
    'port'      => 587,
    'secure'    => 'tls',
    'user'      => 'razakmanoppo11@gmail.com',   // Gmail pengirim (ganti kalau pakai Gmail lain)
    'pass'      => 'bhsimmwjvoxnytot',           // Sandi Aplikasi 16 huruf, tanpa spasi & tanpa tanda baca
    'from_name' => "Men'Shop",

    // true = tampilkan penyebab error asli di layar (aktifkan saat uji, matikan saat dipakai sungguhan)
    'debug'     => true,

    // Ubah ke true HANYA jika muncul error "certificate verify failed" di XAMPP
    'skip_ssl_verify' => false,

    // Mode uji tanpa email: true = kode OTP ditulis ke file otp_log.txt, bukan dikirim.
    // WAJIB false saat dipakai sungguhan (file itu bisa dibuka lewat browser).
    'dev_log'   => false,
];


// ======================================================================
//  BAGIAN 3 — SAKLAR FITUR
//  true = menyala, false = mati.
// ======================================================================
$FITUR = [

    // OTP saat LOGIN, diatur per pintu masuk.
    //   false → email + sandi benar langsung masuk, tanpa kode OTP.
    //   Contoh: hanya pembeli yang tanpa OTP →  'user' => false, 'admin' => true, 'superadmin' => true
    // Saran: biarkan 'admin' dan 'superadmin' tetap true agar akun pengelola lebih aman.
    'otp_login' => [
        'user'       => true,    // login pembeli      (index.html)
        'admin'      => true,    // login Admin        (admin.html)
        'superadmin' => false,    // login Super Admin  (superadmin.html)
    ],

    // OTP saat PENDAFTARAN pembeli baru.
    //   false → akun langsung jadi tanpa verifikasi email (email tidak dibuktikan milik pendaftar).
    'otp_daftar' => true,

    // Catatan: OTP untuk "Lupa sandi" dan "Aktifkan akun terkunci" SELALU wajib dan tidak bisa dimatikan,
    // karena itulah satu-satunya bukti bahwa yang meminta adalah pemilik email.
];
