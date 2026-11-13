<?php
/* ======================================================================
 *  aksi/layanan.php — LAYANAN PEMBELI (tanya admin / masalah pesanan)
 *
 *  Percakapan pembeli ↔ admin. Fungsi pembantunya ada di inc/09_layanan.php.
 * ====================================================================== */

// ---------------------------------------------------------------- Pembeli

// ?a=ticket_create — pembeli memulai percakapan baru ke admin
function aksi_ticket_create() {
    global $in, $pdo;
    $u = need('user'); $subjek = trim($in['subjek'] ?? ''); $pesan = isi_pesan($in['pesan'] ?? ''); $oid = (int) ($in['order_id'] ?? 0);
    if ($subjek === '' || mb_strlen($subjek) > 100) err('Judul wajib diisi (maksimal 100 karakter).');
    if ($oid && !q('SELECT 1 FROM orders WHERE id=? AND user_id=?', [$oid, $u['id']])->fetch()) err('Pesanan tidak ditemukan.');
    $pid = (int) ($in['product_id'] ?? 0); $pn = null;
    if ($pid) { $pn = q('SELECT nama FROM products WHERE id=?', [$pid])->fetchColumn(); if ($pn === false) err('Produk tidak ditemukan.'); }
    if ((int) q("SELECT COUNT(*) FROM tickets WHERE user_id=? AND status<>'Selesai'", [$u['id']])->fetchColumn() >= 5)
        err('Masih ada 5 percakapan yang belum selesai. Tunggu balasan admin atau tandai selesai dulu.');
    $pdo->beginTransaction();
    q('INSERT INTO tickets(user_id,order_id,product_id,subjek,status,created,updated) VALUES(?,?,?,?,?,NOW(),NOW())', [$u['id'], $oid ?: null, $pid ?: null, $subjek, 'Terbuka']);
    $tid = (int) $pdo->lastInsertId();
    q('INSERT INTO ticket_msgs(ticket_id,user_id,staff,pesan,created) VALUES(?,?,0,?,NOW())', [$tid, $u['id'], $pesan]);
    q('INSERT INTO ticket_msgs(ticket_id,user_id,staff,pesan,created) VALUES(?,0,2,?,NOW())', [$tid, balasan_otomatis($pn)]);   // balasan otomatis; status tetap 'Terbuka' supaya admin tahu masih perlu dibalas
    $pdo->commit();
    catat('tiket_baru', "#$tid $subjek"); out(['id' => $tid]);
}

// ?a=my_tickets — daftar percakapan milik pembeli
function aksi_my_tickets() {
    $u = need('user');
    out(q('SELECT id,order_id,subjek,status,created,updated,(SELECT nama FROM products p WHERE p.id=tickets.product_id) produk FROM tickets WHERE user_id=? ORDER BY updated DESC', [$u['id']])->fetchAll());
}

// ---------------------------------------------------------------- Admin

// ?a=tickets — daftar semua percakapan (untuk admin)
function aksi_tickets() {
    panel('layanan');
    out(q("SELECT t.id,t.order_id,t.subjek,t.status,t.created,t.updated,u.nama pembeli,(SELECT nama FROM products p WHERE p.id=t.product_id) produk FROM tickets t JOIN users u ON u.id=t.user_id
           ORDER BY FIELD(t.status,'Terbuka','Dijawab','Selesai'), t.updated DESC LIMIT 200")->fetchAll());
}

// ---------------------------------------------------------------- Pembeli & admin

// ?a=ticket — isi satu percakapan
function aksi_ticket() {
    [$u, $t] = tiket_akses($_GET['id'] ?? 0);
    $msgs = q("SELECT m.id,m.pesan,m.staff,m.created,COALESCE(u.nama,'Asisten Otomatis') nama FROM ticket_msgs m LEFT JOIN users u ON u.id=m.user_id WHERE m.ticket_id=? ORDER BY m.id", [$t['id']])->fetchAll();
    $o = $t['order_id'] ? q('SELECT id,total,status,metode FROM orders WHERE id=?', [$t['order_id']])->fetch() : null;
    $pr = $t['product_id'] ? q('SELECT id,nama FROM products WHERE id=?', [$t['product_id']])->fetch() : null;
    out(['t' => $t, 'msgs' => $msgs, 'order' => $o ?: null, 'produk' => $pr ?: null]);
}

// ?a=ticket_reply — kirim balasan di percakapan
function aksi_ticket_reply() {
    global $in;
    [$u, $t] = tiket_akses($in['id'] ?? 0); $pesan = isi_pesan($in['pesan'] ?? ''); $staf = $u['role'] === 'user' ? 0 : 1;
    q('INSERT INTO ticket_msgs(ticket_id,user_id,staff,pesan,created) VALUES(?,?,?,?,NOW())', [$t['id'], $u['id'], $staf, $pesan]);
    q('UPDATE tickets SET status=?,updated=NOW() WHERE id=?', [$staf ? 'Dijawab' : 'Terbuka', $t['id']]);
    catat($staf ? 'tiket_dibalas' : 'tiket_balasan_pembeli', "#{$t['id']}");
    if ($staf && !empty(S('email_notif'))) {
        try {
            $toko = S('nama_toko');
            kirim_email($t['email'], "Balasan untuk: {$t['subjek']} - $toko",
                "<div style='font-family:Arial,sans-serif'><h2 style='color:#ee4d2d'>" . htmlspecialchars($toko) . "</h2><p>Halo " . htmlspecialchars($t['pembeli'])
                . ", admin sudah membalas pertanyaan Anda <b>" . htmlspecialchars($t['subjek']) . "</b>. Silakan buka menu Bantuan untuk membacanya.</p></div>",
                "Admin sudah membalas pertanyaan Anda: {$t['subjek']}. Buka menu Bantuan untuk membacanya.");
        } catch (Throwable $x) { error_log('Men\'Shop notif: ' . $x->getMessage()); }
    }
    out(['ok' => 1]);
}

// ?a=ticket_close — tandai percakapan selesai
function aksi_ticket_close() {
    global $in;
    [$u, $t] = tiket_akses($in['id'] ?? 0);
    q("UPDATE tickets SET status='Selesai',updated=NOW() WHERE id=?", [$t['id']]); catat('tiket_selesai', "#{$t['id']}");
    out(['ok' => 1]);
}
