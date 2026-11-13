<?php
/* ======================================================================
 *  inc/03_akun_akses.php — SIAPA YANG LOGIN & HAK AKSES
 *
 *  me()        = data akun yang sedang login (atau null)
 *  need(...)   = wajib login (dan wajib peran tertentu), kalau tidak → error 401/403
 *  panel(..)   = wajib boleh membuka bagian panel Admin tertentu
 *  need_izin() = izin Admin yang bisa dimatikan Super Admin (tambah/edit/hapus/status)
 *  catat()     = menulis log aktivitas (tab Keamanan)
 *
 *  MAU MENGUBAH?
 *    • Bagian panel Admin → daftar PANEL di bawah (harus sama dengan AD_TABS di js/6-admin.js).
 * ====================================================================== */

// ---------------------------------------------------------------- Log aktivitas
function catat($aksi, $detail = '', $uid = null, $email = null) {   // log aktivitas, tidak boleh mengganggu proses utama
    try {
        if ($uid === null && !empty($_SESSION['uid'])) $uid = $_SESSION['uid'];
        q('INSERT INTO activity_log(user_id,email,aksi,detail,ip,created) VALUES(?,?,?,?,?,NOW())',
          [$uid, $email, $aksi, mb_substr($detail, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $t) { error_log('Men\'Shop log: ' . $t->getMessage()); }
}

// ---------------------------------------------------------------- Akun yang sedang login
function me() {
    if (empty($_SESSION['uid'])) return null;
    return q('SELECT id,nama,email,role,akses FROM users WHERE id=? AND aktif=1', [$_SESSION['uid']])->fetch() ?: null;
}
function need(...$roles) {
    $u = me();
    if (!$u) err('Silakan login dulu.', 401);
    if ($roles && !in_array($u['role'], $roles)) err('Akses ditolak.', 403);
    return $u;
}

// ---------------------------------------------------------------- Akses bagian panel Admin
const PANEL = ['ringkasan', 'pesanan', 'produk', 'stok', 'layanan'];   // bagian panel admin
function panel($p) {   // Admin hanya boleh membuka bagian yang diberikan Super Admin (kolom users.akses; kosong = semua)
    $u = need('admin', 'superadmin');
    if ($u['role'] === 'admin' && !empty($u['akses']) && !in_array($p, explode(',', $u['akses']))) err('Akun Admin Anda tidak punya akses ke bagian ini.', 403);
    return $u;
}
function norm_akses($v) {
    if (is_string($v)) $v = explode(',', $v);
    $v = array_values(array_intersect(PANEL, array_map('trim', (array) $v)));   // hanya nilai valid, urutan tetap
    if (!$v) err('Pilih minimal satu bagian panel untuk Admin.');
    return count($v) === count(PANEL) ? null : implode(',', $v);   // null = akses penuh
}
function need_izin($kunci, $label, $bagian = null) {   // izin admin yang bisa dimatikan oleh super admin
    $u = $bagian ? panel($bagian) : need('admin', 'superadmin');
    if ($u['role'] === 'admin' && empty(S($kunci))) err("Izin $label untuk Admin dinonaktifkan oleh Super Admin.", 403);
    return $u;
}

// ---------------------------------------------------------------- Mengambil akun target (untuk aksi Super Admin)
function target($id) {
    $t = q('SELECT id,nama,email,role,aktif FROM users WHERE id=?', [(int) $id])->fetch();
    if (!$t) err('Akun tidak ditemukan.', 404);
    return $t;
}
