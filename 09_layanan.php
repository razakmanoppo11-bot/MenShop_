<?php
/* ======================================================================
 *  inc/09_layanan.php — LAYANAN PEMBELI (tiket tanya admin)
 *
 *  tiket_akses()        = cek siapa boleh membuka percakapan
 *  balasan_otomatis()   = pesan otomatis "Asisten Otomatis" saat pembeli mulai bertanya
 *  isi_pesan()          = validasi isi pesan (maks. 1000 karakter)
 *
 *  MAU MENGUBAH?
 *    • Teks balasan otomatis → balasan_otomatis().
 * ====================================================================== */

function tiket_akses($id) {
    $u = need();
    if ($u['role'] === 'admin') panel('layanan');
    $t = q('SELECT t.*,u.nama pembeli,u.email FROM tickets t JOIN users u ON u.id=t.user_id WHERE t.id=?', [(int) $id])->fetch();
    if (!$t) err('Percakapan tidak ditemukan.', 404);
    if ($u['role'] === 'user' && $t['user_id'] != $u['id']) err('Akses ditolak.', 403);
    return [$u, $t];
}
function balasan_otomatis($produk = null) {   // pesan sopan dari "Asisten Otomatis" (staff=2) saat pembeli memulai percakapan
    $toko = S('nama_toko');
    return "Halo, terima kasih telah menghubungi $toko 🙏\n\n"
        . ($produk ? "Pertanyaan Anda mengenai produk \"$produk\" sudah kami terima. " : "Pesan Anda sudah kami terima. ")
        . "Admin layanan kami akan segera membacanya dan membalas di halaman ini, mohon ditunggu ya.\n\n"
        . "Supaya kami bisa membantu lebih cepat: untuk pertanyaan ukuran, mohon sebutkan tinggi dan berat badan Anda; untuk kendala pesanan, sertakan nomor pesanannya.\n\n"
        . "Salam hangat,\nAsisten Otomatis $toko";
}
function isi_pesan($v) {
    $v = trim((string) $v);
    if ($v === '' || mb_strlen($v) > 1000) err('Pesan wajib diisi (maksimal 1000 karakter).');
    return $v;
}
