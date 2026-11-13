<?php
/* ======================================================================
 *  inc/02_pengaturan.php — PENGATURAN TOKO & KATEGORI
 *
 *  Pengaturan toko yang disimpan di tabel `settings` dan diubah Super Admin lewat panel
 *  (ongkir, metode bayar, stok minimum, izin Admin, dst).
 *  S() membaca satu nilai · cfg() menyusun paket pengaturan untuk browser.
 *
 *  MAU MENGUBAH?
 *    • Nilai awal pengaturan → PENGATURAN_BAWAAN di bawah (hanya berlaku saat pertama dibuat).
 *    • Mau menambah pengaturan baru: tambah di PENGATURAN_BAWAAN, lalu di PENGATURAN_ANGKA (angka)
 *    • atau PENGATURAN_SAKLAR (centang ya/tidak), lalu tampilkan di js/7-superadmin.js → saPengaturan().
 * ====================================================================== */

// ---------------------------------------------------------------- Nilai awal pengaturan
const PENGATURAN_BAWAAN = [
    'nama_toko' => "Men'Shop", 'ongkir' => '15000', 'gratis_ongkir_min' => '500000',
    'metode_qris' => '1', 'metode_dana' => '1', 'metode_gopay' => '1', 'metode_bank' => '1', 'metode_cod' => '1',
    'info_dana' => '0812-3456-7890', 'info_gopay' => '0812-3456-7890', 'info_bank' => '1234567890', 'atas_nama' => "Men'Shop",
    'email_notif' => '1', 'stok_minimum' => '10',
    'izin_admin_tambah' => '1', 'izin_admin_edit' => '1', 'izin_admin_hapus' => '1', 'izin_admin_status' => '1',
];
const PENGATURAN_ANGKA = ['ongkir', 'gratis_ongkir_min', 'stok_minimum'];
const PENGATURAN_SAKLAR = ['metode_qris', 'metode_dana', 'metode_gopay', 'metode_bank', 'metode_cod', 'email_notif',
    'izin_admin_tambah', 'izin_admin_edit', 'izin_admin_hapus', 'izin_admin_status'];

// ---------------------------------------------------------------- Membaca pengaturan
function S($k = null) {
    static $c = null;
    if ($c === null) { $c = []; foreach (q('SELECT k,v FROM settings')->fetchAll() as $r) $c[$r['k']] = $r['v']; }
    return $k === null ? $c : ($c[$k] ?? '');
}
function cfg() {
    $s = S(); $m = [];
    foreach (['qris' => 'QRIS', 'dana' => 'DANA', 'gopay' => 'GoPay', 'bank' => 'Bank', 'cod' => 'COD'] as $k => $l) if (!empty($s["metode_$k"])) $m[] = $l;
    return [
        'nama_toko' => $s['nama_toko'], 'ongkir' => (int) $s['ongkir'], 'gratis_min' => (int) $s['gratis_ongkir_min'], 'metode' => $m,
        'info' => ['dana' => $s['info_dana'], 'gopay' => $s['info_gopay'], 'bank' => $s['info_bank'], 'atas_nama' => $s['atas_nama']],
        'izin' => ['tambah' => !empty($s['izin_admin_tambah']), 'edit' => !empty($s['izin_admin_edit']),
                   'hapus' => !empty($s['izin_admin_hapus']), 'status' => !empty($s['izin_admin_status'])],
        'stok_min' => (int) $s['stok_minimum'],
    ];
}

// ---------------------------------------------------------------- Kategori produk
function cats() { return q('SELECT id,nama,emoji FROM categories ORDER BY urut,id')->fetchAll(); }
function catnames() { return q('SELECT nama FROM categories')->fetchAll(PDO::FETCH_COLUMN); }
