<?php
/* ======================================================================
 *  aksi/toko.php — TOKO (sisi pembeli)
 *
 *  Produk, keranjang, checkout, dan pesanan milik pembeli.
 *  Ongkir & metode bayar diambil dari pengaturan toko (inc/02_pengaturan.php).
 * ====================================================================== */

// ---------------------------------------------------------------- Produk

// ?a=products — daftar produk (filter ?kat= dan pencarian ?q=)
function aksi_products() {
    need();
    $sql = 'SELECT * FROM products WHERE 1=1'; $p = [];
    if (!empty($_GET['kat'])) { $sql .= ' AND kategori=?'; $p[] = $_GET['kat']; }
    if (!empty($_GET['q'])) { $sql .= ' AND (nama LIKE ? OR deskripsi LIKE ?)'; $p[] = $p[] = '%' . $_GET['q'] . '%'; }
    out(q($sql . ' ORDER BY id DESC', $p)->fetchAll());
}

// ---------------------------------------------------------------- Keranjang

// ?a=cart — isi keranjang pembeli
function aksi_cart() {
    $u = need('user');
    out(q('SELECT c.id,c.qty,p.nama,p.harga,p.kategori,p.stok,p.gambar FROM cart c JOIN products p ON p.id=c.product_id WHERE c.user_id=? ORDER BY c.id', [$u['id']])->fetchAll());
}

// ?a=cart_add — tambah produk ke keranjang
function aksi_cart_add() {
    global $in;
    $u = need('user'); $pid = (int) ($in['id'] ?? 0);
    $p = q('SELECT * FROM products WHERE id=?', [$pid])->fetch();
    if (!$p) err('Produk tidak ditemukan.', 404);
    if ($p['stok'] < 1) err('Stok habis.');
    q('INSERT INTO cart(user_id,product_id,qty) VALUES(?,?,1) ON DUPLICATE KEY UPDATE qty=LEAST(qty+1,?)', [$u['id'], $pid, $p['stok']]);
    out(['nama' => $p['nama']]);
}

// ?a=cart_set — ubah jumlah di keranjang (0 = hapus)
function aksi_cart_set() {
    global $in;
    $u = need('user'); $qty = (int) ($in['qty'] ?? 0); $cid = (int) ($in['id'] ?? 0);
    if ($qty < 1) q('DELETE FROM cart WHERE id=? AND user_id=?', [$cid, $u['id']]);
    else q('UPDATE cart c JOIN products p ON p.id=c.product_id SET c.qty=LEAST(?,p.stok) WHERE c.id=? AND c.user_id=?', [$qty, $cid, $u['id']]);
    out(['ok' => 1]);
}

// ---------------------------------------------------------------- Checkout & pesanan

// ?a=checkout — buat pesanan dari keranjang
function aksi_checkout() {
    global $in, $pdo;
    $u = need('user');
    $items = q('SELECT c.product_id,c.qty,p.nama,p.harga,p.stok FROM cart c JOIN products p ON p.id=c.product_id WHERE c.user_id=?', [$u['id']])->fetchAll();
    if (!$items) err('Keranjang masih kosong.');
    $alamat = trim($in['alamat'] ?? ''); $m = $in['metode'] ?? ''; $bank = $in['bank'] ?? '';
    $aktif = cfg()['metode'];
    if (!$alamat || !in_array($m, ['QRIS','DANA','GoPay','Bank','COD'])) err('Isi alamat dan pilih metode pembayaran.');
    if (!in_array($m, $aktif)) err('Metode pembayaran ini sedang tidak tersedia.');
    if ($m === 'Bank') { if (!in_array($bank, ['BCA','BRI','BNI','Mandiri'])) err('Pilih bank tujuan transfer.'); $m = "Bank $bank"; }
    $sub = 0;
    foreach ($items as $i) { if ($i['qty'] > $i['stok']) err("Stok {$i['nama']} tidak mencukupi."); $sub += $i['qty'] * $i['harga']; }
    $c = cfg(); $ongkir = ($c['gratis_min'] > 0 && $sub >= $c['gratis_min']) ? 0 : $c['ongkir'];
    $pdo->beginTransaction();
    q('INSERT INTO orders(user_id,total,ongkir,alamat,metode,status) VALUES(?,?,?,?,?,?)', [$u['id'], $sub + $ongkir, $ongkir, $alamat, $m, $m === 'COD' ? 'Diproses' : 'Menunggu Pembayaran']);
    $oid = (int) $pdo->lastInsertId();
    foreach ($items as $i) {
        q('INSERT INTO order_items(order_id,product_id,nama,harga,qty) VALUES(?,?,?,?,?)', [$oid, $i['product_id'], $i['nama'], $i['harga'], $i['qty']]);
        q('UPDATE products SET stok=stok-? WHERE id=?', [$i['qty'], $i['product_id']]);
    }
    q('DELETE FROM cart WHERE user_id=?', [$u['id']]);
    $pdo->commit();
    catat('checkout', "pesanan #$oid", $u['id']);
    out(['order' => $oid]);
}

// ?a=orders — daftar pesanan milik pembeli yang login
function aksi_orders() {
    $u = need('user');
    $o = q('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC', [$u['id']])->fetchAll();
    foreach ($o as &$r) $r['items'] = q('SELECT nama,harga,qty FROM order_items WHERE order_id=?', [$r['id']])->fetchAll();
    out($o);
}
