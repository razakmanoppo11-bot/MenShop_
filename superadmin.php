<?php
/* ======================================================================
 *  aksi/superadmin.php — PANEL SUPER ADMIN (akun, kategori, pengaturan)
 *
 *  Aksi yang HANYA boleh dipakai Super Admin.
 * ====================================================================== */

// ---------------------------------------------------------------- Akun

// ?a=users — daftar semua akun (+ status kunci)
function aksi_users() {
    need('superadmin');
    out(q('SELECT u.id,u.nama,u.email,u.role,u.aktif,u.akses,u.created, COALESCE(GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),l.kunci_sampai)),0) terkunci_sisa,
            l.minta diminta, COALESCE(l.setuju,0) disetujui
        FROM users u LEFT JOIN login_lock l ON l.email=u.email AND l.kunci_sampai>NOW()
        ORDER BY FIELD(u.role,"superadmin","admin","user"),u.id')->fetchAll());
}

// ?a=kunci_setuju — Super Admin menyetujui aktivasi ulang (dari permintaan pemilik, atau setelah dihubungi langsung)
function aksi_kunci_setuju() {
    global $in;
    $u = need('superadmin'); $t = target($in['id'] ?? 0);
    if (!kunci_aktif($t['email'])) err('Akun ini sudah tidak terkunci.');
    q('UPDATE login_lock SET setuju=1, setuju_oleh=? WHERE email=?', [$u['id'], $t['email']]);
    catat('aktivasi_disetujui', $t['email']);
    try {
        $toko = S('nama_toko');
        kirim_email($t['email'], "Aktivasi akun disetujui - $toko",
            "<div style='font-family:Arial,sans-serif'><h2 style='color:#ee4d2d'>" . htmlspecialchars($toko) . "</h2><p>Halo " . htmlspecialchars($t['nama'])
            . ", Super Admin sudah menyetujui aktivasi ulang akun Anda.</p><p>Buka halaman login → <b>Akun terkunci? Aktifkan kembali</b> → masukkan email → <b>Kirim kode OTP</b>, lalu masukkan kode 6 digit yang dikirim ke email ini.</p></div>",
            "Aktivasi ulang akun Anda disetujui. Buka halaman login -> Akun terkunci? Aktifkan kembali -> masukkan email -> Kirim kode OTP.");
    } catch (Throwable $x) { error_log('Men\'Shop notif: ' . $x->getMessage()); }
    out(['ok' => 1]);
}

// ?a=user_add — buat akun admin / pembeli
function aksi_user_add() {
    global $in;
    need('superadmin'); $e = strtolower(trim($in['email'] ?? ''));
    if (!trim($in['nama'] ?? '') || !filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($in['password'] ?? '') < 6 || !in_array($in['role'] ?? '', ['admin','user'])) err('Data tidak valid (sandi min. 6 karakter).');
    if (q('SELECT 1 FROM users WHERE email=?', [$e])->fetch()) err('Email sudah terdaftar.');
    $ak = ($in['role'] === 'admin' && array_key_exists('akses', $in)) ? norm_akses($in['akses']) : null;
    q('INSERT INTO users(nama,email,password,role,akses) VALUES(?,?,?,?,?)', [trim($in['nama']), $e, password_hash($in['password'], PASSWORD_DEFAULT), $in['role'], $ak]);
    catat('user_tambah', "$e ({$in['role']})");
    out(['ok' => 1]);
}

// ?a=user_edit — ubah nama/email/sandi/akses panel akun
function aksi_user_edit() {
    global $in;
    need('superadmin'); $t = target($in['id'] ?? 0);
    $nama = trim($in['nama'] ?? ''); $e = strtolower(trim($in['email'] ?? '')); $pw = $in['password'] ?? '';
    if (!$nama || !filter_var($e, FILTER_VALIDATE_EMAIL) || ($pw !== '' && strlen($pw) < 6)) err('Data tidak valid (sandi baru min. 6 karakter).');
    if (q('SELECT 1 FROM users WHERE email=? AND id<>?', [$e, $t['id']])->fetch()) err('Email sudah dipakai akun lain.');
    $ak = ($t['role'] === 'admin' && array_key_exists('akses', $in)) ? [norm_akses($in['akses'])] : null;   // dibungkus array karena null berarti akses penuh
    if ($pw !== '') q('UPDATE users SET nama=?,email=?,password=? WHERE id=?', [$nama, $e, password_hash($pw, PASSWORD_DEFAULT), $t['id']]);
    else q('UPDATE users SET nama=?,email=? WHERE id=?', [$nama, $e, $t['id']]);
    if ($ak) q('UPDATE users SET akses=? WHERE id=?', [$ak[0], $t['id']]);
    catat('user_edit', "{$t['email']}" . ($pw !== '' ? ' (sandi direset)' : '') . ($ak ? ' akses: ' . ($ak[0] ?? 'semua') : ''));
    out(['ok' => 1]);
}

// ?a=user_role — ubah peran akun (admin ↔ user)
function aksi_user_role() {
    global $in;
    $u = need('superadmin'); $t = target($in['id'] ?? 0);
    if ($t['id'] == $u['id'] || $t['role'] === 'superadmin' || !in_array($in['role'] ?? '', ['admin','user'])) err('Tidak diizinkan.');
    q('UPDATE users SET role=? WHERE id=?', [$in['role'], $t['id']]); catat('user_role', "{$t['email']}: {$t['role']} → {$in['role']}");
    out(['ok' => 1]);
}

// ?a=user_block — blokir / aktifkan akun
function aksi_user_block() {
    global $in;
    $u = need('superadmin'); $t = target($in['id'] ?? 0); $akt = empty($in['aktif']) ? 0 : 1;
    if ($t['id'] == $u['id'] || $t['role'] === 'superadmin') err('Akun Super Admin tidak bisa diblokir.');
    q('UPDATE users SET aktif=? WHERE id=?', [$akt, $t['id']]); catat($akt ? 'user_aktif' : 'user_blokir', $t['email']);
    out(['ok' => 1]);
}

// ?a=user_del — hapus akun
function aksi_user_del() {
    global $in;
    $u = need('superadmin'); $t = target($in['id'] ?? 0);
    if ($t['id'] == $u['id']) err('Tidak bisa menghapus akun sendiri.');
    if ($t['role'] === 'superadmin') err('Akun Super Admin tidak bisa dihapus.');
    q('DELETE FROM users WHERE id=?', [$t['id']]); catat('user_hapus', $t['email']);
    out(['ok' => 1]);
}

// ---------------------------------------------------------------- Kategori

// ?a=cat_add — tambah kategori
function aksi_cat_add() {
    global $in;
    need('superadmin'); $n = trim($in['nama'] ?? ''); $em = mb_substr(trim($in['emoji'] ?? '') ?: '🛍️', 0, 8);
    if ($n === '' || mb_strlen($n) > 50) err('Nama kategori wajib diisi (maks. 50 karakter).');
    if (q('SELECT 1 FROM categories WHERE nama=?', [$n])->fetch()) err('Kategori sudah ada.');
    q('INSERT INTO categories(nama,emoji,urut) VALUES(?,?,(SELECT COALESCE(MAX(urut),0)+1 FROM (SELECT urut FROM categories) x))', [$n, $em]);
    catat('kategori_tambah', $n); out(['ok' => 1]);
}

// ?a=cat_edit — ubah kategori
function aksi_cat_edit() {
    global $in, $pdo;
    need('superadmin'); $id = (int) ($in['id'] ?? 0); $n = trim($in['nama'] ?? ''); $em = mb_substr(trim($in['emoji'] ?? '') ?: '🛍️', 0, 8);
    $old = q('SELECT nama FROM categories WHERE id=?', [$id])->fetchColumn();
    if ($old === false) err('Kategori tidak ditemukan.', 404);
    if ($n === '' || mb_strlen($n) > 50) err('Nama kategori wajib diisi (maks. 50 karakter).');
    if (q('SELECT 1 FROM categories WHERE nama=? AND id<>?', [$n, $id])->fetch()) err('Nama kategori sudah dipakai.');
    $pdo->beginTransaction();
    q('UPDATE categories SET nama=?,emoji=? WHERE id=?', [$n, $em, $id]);
    q('UPDATE products SET kategori=? WHERE kategori=?', [$n, $old]);
    $pdo->commit();
    catat('kategori_edit', "$old → $n"); out(['ok' => 1]);
}

// ?a=cat_del — hapus kategori
function aksi_cat_del() {
    global $in;
    need('superadmin'); $id = (int) ($in['id'] ?? 0);
    $old = q('SELECT nama FROM categories WHERE id=?', [$id])->fetchColumn();
    if ($old === false) err('Kategori tidak ditemukan.', 404);
    $n = (int) q('SELECT COUNT(*) FROM products WHERE kategori=?', [$old])->fetchColumn();
    if ($n) err("Kategori masih dipakai $n produk. Pindahkan atau hapus produknya dulu.");
    q('DELETE FROM categories WHERE id=?', [$id]); catat('kategori_hapus', $old); out(['ok' => 1]);
}

// ---------------------------------------------------------------- Pengaturan toko

// ?a=settings_get — baca semua pengaturan toko
function aksi_settings_get() {
    need('superadmin'); out(S());
}

// ?a=settings_set — simpan pengaturan toko
function aksi_settings_set() {
    global $in;
    need('superadmin'); $new = [];
    foreach (PENGATURAN_BAWAAN as $k => $d) {
        if (!array_key_exists($k, $in)) continue;
        $v = $in[$k];
        if (in_array($k, PENGATURAN_ANGKA)) $v = (string) max(0, min(100000000, (int) $v));
        elseif (in_array($k, PENGATURAN_SAKLAR)) $v = $v ? '1' : '0';
        else { $v = mb_substr(trim((string) $v), 0, 100); if ($v === '') err("Kolom $k tidak boleh kosong."); }
        $new[$k] = $v;
    }
    $akhir = array_merge(S(), $new);
    if (!array_filter(['metode_qris', 'metode_dana', 'metode_gopay', 'metode_bank', 'metode_cod'], fn($k) => $akhir[$k] === '1')) err('Minimal satu metode pembayaran harus aktif.');
    foreach ($new as $k => $v) q('INSERT INTO settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', [$k, $v]);
    catat('pengaturan', implode(', ', array_keys($new))); out(['ok' => 1]);
}
