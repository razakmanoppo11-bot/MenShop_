<?php
/* ======================================================================
 *  inc/08_pesanan.php — PESANAN
 *
 *  STATUS_PESANAN        = daftar status yang boleh dipilih.
 *  SQL_STATUS_PENJUALAN  = status yang dihitung sebagai penjualan (laporan omzet).
 *  ubah_status()         = ganti status + kembalikan stok bila dibatalkan + email ke pembeli.
 *
 *  MAU MENGUBAH?
 *    • Nama status baru → tambahkan di STATUS_PESANAN DAN di js/1-dasar.js (const STATUS).
 * ====================================================================== */

// ---------------------------------------------------------------- Daftar status
const STATUS_PESANAN = ['Menunggu Pembayaran', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'];
const SQL_STATUS_PENJUALAN = "o.status IN ('Diproses','Dikirim','Selesai')";   // pesanan yang dihitung sebagai penjualan

// ---------------------------------------------------------------- Mengubah status pesanan
function ubah_status($id, $st, $aksi = 'status_pesanan') {
    global $pdo;
    $o = q('SELECT o.id,o.status,u.email,u.nama FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?', [$id])->fetch();
    if (!$o) err('Pesanan tidak ditemukan.', 404);
    if ($o['status'] === $st) return;
    if ($o['status'] === 'Dibatalkan') err('Pesanan yang sudah dibatalkan tidak bisa diubah lagi.');
    $pdo->beginTransaction();
    q('UPDATE orders SET status=? WHERE id=?', [$st, $id]);
    if ($st === 'Dibatalkan')   // stok barang dikembalikan otomatis
        q('UPDATE products p JOIN order_items i ON i.product_id=p.id SET p.stok=p.stok+i.qty WHERE i.order_id=?', [$id]);
    $pdo->commit();
    catat($aksi, "#$id: {$o['status']} → $st");
    if (!empty(S('email_notif'))) {   // notifikasi email ke pembeli (gagal kirim tidak membatalkan perubahan status)
        try {
            $toko = S('nama_toko');
            kirim_email($o['email'], "Status pesanan #$id: $st - $toko",
                "<div style='font-family:Arial,sans-serif'><h2 style='color:#ee4d2d'>" . htmlspecialchars($toko) . "</h2><p>Halo " . htmlspecialchars($o['nama'])
                . ",</p><p>Status pesanan <b>#$id</b> Anda sekarang: <b>" . htmlspecialchars($st) . "</b>.</p></div>",
                "Status pesanan #$id Anda sekarang: $st.");
        } catch (Throwable $t) { error_log('Men\'Shop notif: ' . $t->getMessage()); }
    }
}
