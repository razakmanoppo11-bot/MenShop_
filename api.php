<?php
/* ======================================================================
 *  api.php — PINTU MASUK SERVER + DAFTAR AKSI
 *
 *  Browser (js/*.js) memanggil:  api.php?a=NAMA_AKSI
 *  File ini hanya memuat berkas lain lalu menjalankan fungsi aksi_<NAMA_AKSI>.
 *  
 *  PETA BERKAS:
 *    config.php            pengaturan: database, email, saklar OTP
 *    inc/00_dasar.php      pondasi (sesi, out/err)
 *    inc/01_database.php   koneksi & tabel otomatis
 *    inc/02_pengaturan.php pengaturan toko & kategori
 *    inc/03_akun_akses.php siapa yang login, hak akses, log
 *    inc/04_email.php      kirim email
 *    inc/05_otp.php        kode OTP
 *    inc/06_kunci_login.php kunci 3x salah sandi
 *    inc/07_produk_gambar.php gambar produk
 *    inc/08_pesanan.php    status pesanan
 *    inc/09_layanan.php    tanya admin
 *    inc/10_laporan.php    pembantu laporan
 *    aksi/*.php            isi setiap aksi, dikelompokkan per area
 *
 *  MAU MENGUBAH?
 *    • Menambah aksi baru → tulis function aksi_namanya() di salah satu berkas aksi/, lalu daftarkan di $AKSI di bawah.
 * ====================================================================== */

define('BASE_DIR', __DIR__);                    // folder utama proyek (dipakai berkas di inc/ & aksi/ untuk uploads/, phpmailer/, otp_log.txt)
require __DIR__ . '/config.php';              // pengaturan database, email, saklar OTP

// ---- Memuat semua bagian (urutan penting: dari pondasi ke atas)
require __DIR__ . '/inc/00_dasar.php';
require __DIR__ . '/inc/01_database.php';
require __DIR__ . '/inc/02_pengaturan.php';
require __DIR__ . '/inc/03_akun_akses.php';
require __DIR__ . '/inc/04_email.php';
require __DIR__ . '/inc/05_otp.php';
require __DIR__ . '/inc/06_kunci_login.php';
require __DIR__ . '/inc/07_produk_gambar.php';
require __DIR__ . '/inc/08_pesanan.php';
require __DIR__ . '/inc/09_layanan.php';
require __DIR__ . '/inc/10_laporan.php';
foreach (['akun', 'toko', 'layanan', 'admin', 'superadmin', 'laporan'] as $berkas) require __DIR__ . "/aksi/$berkas.php";

try { skema(); } catch (Exception $e) { err('Gagal menyiapkan database: ' . $e->getMessage(), 500); }

// ---- Data kiriman browser & nama aksi
$in = json_decode(file_get_contents('php://input'), true) ?: [];   // isi JSON dari browser
$a = $_GET['a'] ?? '';
if (!is_string($a)) $a = '';

// ---- DAFTAR AKSI:  'nama_di_url' => 'fungsi yang dijalankan'
$AKSI = [

    // ---- AKUN: login, daftar, OTP, lupa sandi
    'me'                    => 'aksi_me',
    'register'              => 'aksi_register',
    'login'                 => 'aksi_login',
    'otp_resend'            => 'aksi_otp_resend',
    'verify_otp'            => 'aksi_verify_otp',
    'kunci_info'            => 'aksi_kunci_info',
    'reaktivasi_minta'      => 'aksi_reaktivasi_minta',
    'reaktivasi_otp'        => 'aksi_reaktivasi_otp',
    'forgot'                => 'aksi_forgot',
    'reset_password'        => 'aksi_reset_password',
    'logout'                => 'aksi_logout',

    // ---- TOKO (pembeli)
    'products'              => 'aksi_products',
    'cart'                  => 'aksi_cart',
    'cart_add'              => 'aksi_cart_add',
    'cart_set'              => 'aksi_cart_set',
    'checkout'              => 'aksi_checkout',
    'orders'                => 'aksi_orders',

    // ---- LAYANAN (tanya admin)
    'ticket_create'         => 'aksi_ticket_create',
    'my_tickets'            => 'aksi_my_tickets',
    'tickets'               => 'aksi_tickets',
    'ticket'                => 'aksi_ticket',
    'ticket_reply'          => 'aksi_ticket_reply',
    'ticket_close'          => 'aksi_ticket_close',

    // ---- PANEL ADMIN
    'all_orders'            => 'aksi_all_orders',
    'order_status'          => 'aksi_order_status',
    'order_paid'            => 'aksi_order_paid',
    'stock_set'             => 'aksi_stock_set',
    'admin_ringkasan'       => 'aksi_admin_ringkasan',
    'product_add'           => 'aksi_product_add',
    'product_edit'          => 'aksi_product_edit',
    'product_del'           => 'aksi_product_del',
    'product_image'         => 'aksi_product_image',
    'product_image_del'     => 'aksi_product_image_del',

    // ---- SUPER ADMIN
    'users'                 => 'aksi_users',
    'kunci_setuju'          => 'aksi_kunci_setuju',
    'user_add'              => 'aksi_user_add',
    'user_edit'             => 'aksi_user_edit',
    'user_role'             => 'aksi_user_role',
    'user_block'            => 'aksi_user_block',
    'user_del'              => 'aksi_user_del',
    'cat_add'               => 'aksi_cat_add',
    'cat_edit'              => 'aksi_cat_edit',
    'cat_del'               => 'aksi_cat_del',
    'settings_get'          => 'aksi_settings_get',
    'settings_set'          => 'aksi_settings_set',
    'report'                => 'aksi_report',
    'report_csv'            => 'aksi_report_csv',
    'security'              => 'aksi_security',
    'backup'                => 'aksi_backup',
];

// ---- Jalankan
if (!isset($AKSI[$a])) err('Aksi tidak dikenal.', 404);
try {
    $AKSI[$a]();
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    err('Terjadi kesalahan pada server.', 500);
}
