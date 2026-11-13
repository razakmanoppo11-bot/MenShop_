<?php
/* ======================================================================
 *  aksi/admin.php — PANEL ADMIN (pesanan, stok, produk)
 *
 *  Aksi yang boleh dipakai Admin (dan Super Admin) sesuai bagian panel & izinnya.
 * ====================================================================== */

// ---------------------------------------------------------------- Pesanan

// ?a=all_orders — semua pesanan (panel Admin/Super Admin)
function aksi_all_orders() {
    panel('pesanan');
    $o = q('SELECT o.*,u.nama pembeli FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.id DESC')->fetchAll();
    $it = [];
    foreach (q('SELECT order_id,nama,qty FROM order_items')->fetchAll() as $x) $it[$x['order_id']][] = $x['nama'] . ' ×' . $x['qty'];
    foreach ($o as &$r) $r['barang'] = implode(', ', $it[$r['id']] ?? []);
    out($o);
}

// ?a=order_status — ubah status pesanan
function aksi_order_status() {
    global $in;
    need_izin('izin_admin_status', 'mengubah status pesanan', 'pesanan');
    $st = $in['status'] ?? '';
    if (!in_array($st, STATUS_PESANAN)) err('Status tidak valid.');
    ubah_status((int) ($in['id'] ?? 0), $st);
    out(['ok' => 1]);
}

// ?a=order_paid — admin memeriksa pembayaran lalu mengonfirmasi
function aksi_order_paid() {
    global $in;
    need_izin('izin_admin_status', 'mengonfirmasi pembayaran', 'pesanan');
    $id = (int) ($in['id'] ?? 0);
    $st = q('SELECT status FROM orders WHERE id=?', [$id])->fetchColumn();
    if ($st === false) err('Pesanan tidak ditemukan.', 404);
    if ($st !== 'Menunggu Pembayaran') err('Pesanan ini tidak sedang menunggu pembayaran.');
    ubah_status($id, 'Diproses', 'pembayaran_dikonfirmasi');
    out(['ok' => 1]);
}

// ---------------------------------------------------------------- Stok & ringkasan

// ?a=stock_set — ubah stok satu produk
function aksi_stock_set() {
    global $in;
    need_izin('izin_admin_edit', 'mengubah stok', 'stok');
    $id = (int) ($in['id'] ?? 0); $stok = $in['stok'] ?? '';
    if (!is_numeric($stok) || (int) $stok < 0) err('Stok harus angka 0 atau lebih.');
    $p = q('SELECT nama,stok FROM products WHERE id=?', [$id])->fetch();
    if (!$p) err('Produk tidak ditemukan.', 404);
    q('UPDATE products SET stok=? WHERE id=?', [(int) $stok, $id]);
    catat('stok_ubah', "#$id {$p['nama']}: {$p['stok']} → " . (int) $stok);
    out(['ok' => 1]);
}

// ?a=admin_ringkasan — angka ringkasan di halaman depan panel Admin
function aksi_admin_ringkasan() {
    panel('ringkasan');
    $c = q("SELECT SUM(status='Menunggu Pembayaran') bayar, SUM(status='Diproses') proses, SUM(status='Dikirim') kirim FROM orders")->fetch();
    out(['bayar' => (int) $c['bayar'], 'proses' => (int) $c['proses'], 'kirim' => (int) $c['kirim'],
         'tiket' => (int) q("SELECT COUNT(*) FROM tickets WHERE status='Terbuka'")->fetchColumn(),
         'habis' => (int) q('SELECT COUNT(*) FROM products WHERE stok=0')->fetchColumn(),
         'menipis' => q('SELECT id,nama,kategori,stok FROM products WHERE stok<=? ORDER BY stok,id LIMIT 50', [(int) S('stok_minimum')])->fetchAll()]);
}

// ---------------------------------------------------------------- Produk

// ?a=product_add — tambah produk
function aksi_product_add() {
    global $in, $pdo;
    need_izin('izin_admin_tambah', 'menambah produk', 'produk');
    $nama = trim($in['nama'] ?? '');
    if (!$nama || !in_array($in['kategori'] ?? '', catnames()) || (int) ($in['harga'] ?? -1) < 0 || (int) ($in['stok'] ?? -1) < 0) err('Data produk tidak valid.');
    q('INSERT INTO products(nama,kategori,harga,stok,deskripsi) VALUES(?,?,?,?,?)', [$nama, $in['kategori'], (int) $in['harga'], (int) $in['stok'], trim($in['deskripsi'] ?? '')]);
    catat('produk_tambah', $nama);
    out(['ok' => 1, 'id' => (int) $pdo->lastInsertId()]);
}

// ?a=product_edit — ubah produk
function aksi_product_edit() {
    global $in;
    need_izin('izin_admin_edit', 'mengubah produk', 'produk');
    $id = (int) ($in['id'] ?? 0); $nama = trim($in['nama'] ?? '');
    if (!q('SELECT 1 FROM products WHERE id=?', [$id])->fetch()) err('Produk tidak ditemukan.', 404);
    if (!$nama || !in_array($in['kategori'] ?? '', catnames()) || (int) ($in['harga'] ?? -1) < 0 || (int) ($in['stok'] ?? -1) < 0) err('Data produk tidak valid.');
    q('UPDATE products SET nama=?,kategori=?,harga=?,stok=?,deskripsi=? WHERE id=?', [$nama, $in['kategori'], (int) $in['harga'], (int) $in['stok'], trim($in['deskripsi'] ?? ''), $id]);
    catat('produk_edit', "#$id $nama");
    out(['ok' => 1]);
}

// ?a=product_del — hapus produk
function aksi_product_del() {
    global $in;
    need_izin('izin_admin_hapus', 'menghapus produk', 'produk');
    $id = (int) ($in['id'] ?? 0);
    $p = q('SELECT nama,gambar FROM products WHERE id=?', [$id])->fetch();
    q('DELETE FROM products WHERE id=?', [$id]); catat('produk_hapus', "#$id " . ($p['nama'] ?? ''));
    gambar_hapus($p['gambar'] ?? null);
    out(['ok' => 1]);
}

// ?a=product_image — unggah / ganti gambar produk (multipart/form-data: id + gambar)
function aksi_product_image() {
    $u = panel('produk');
    if ($u['role'] === 'admin' && empty(S('izin_admin_tambah')) && empty(S('izin_admin_edit'))) err('Izin mengubah produk untuk Admin dinonaktifkan oleh Super Admin.', 403);
    $id = (int) ($_POST['id'] ?? 0);
    $p = q('SELECT nama,gambar FROM products WHERE id=?', [$id])->fetch();
    if (!$p) err('Produk tidak ditemukan.', 404);
    $nama = gambar_simpan($_FILES['gambar'] ?? null);
    q('UPDATE products SET gambar=? WHERE id=?', [$nama, $id]);
    gambar_hapus($p['gambar']);
    catat('produk_gambar', "#$id {$p['nama']}");
    out(['ok' => 1, 'gambar' => $nama]);
}

// ?a=product_image_del — hapus gambar produk
function aksi_product_image_del() {
    global $in;
    need_izin('izin_admin_edit', 'mengubah produk', 'produk');
    $id = (int) ($in['id'] ?? 0);
    $p = q('SELECT nama,gambar FROM products WHERE id=?', [$id])->fetch();
    if (!$p) err('Produk tidak ditemukan.', 404);
    q('UPDATE products SET gambar=NULL WHERE id=?', [$id]);
    gambar_hapus($p['gambar']);
    catat('produk_gambar_hapus', "#$id {$p['nama']}");
    out(['ok' => 1]);
}
