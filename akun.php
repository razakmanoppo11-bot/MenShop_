<?php
/* ======================================================================
 *  aksi/akun.php — LOGIN, DAFTAR, OTP, LUPA SANDI, KUNCI AKUN
 *
 *  Semua aksi yang berhubungan dengan masuk ke akun.
 *  Fungsi diberi nama aksi_<nama> dan dipanggil dari daftar di api.php.
 *  Mau mengubah alur OTP? → aksi_login (kirim OTP), aksi_verify_otp (periksa kode).
 *  Mau mematikan OTP? → cukup ubah saklar $FITUR di config.php.
 * ====================================================================== */

// ---------------------------------------------------------------- Status login

// ?a=me — cek siapa yang login + isi keranjang + kategori + pengaturan toko (dipanggil setiap halaman dibuka)
function aksi_me() {
    $u = me(); $n = 0;
    if ($u && $u['role'] === 'user') $n = (int) q('SELECT COALESCE(SUM(qty),0) FROM cart WHERE user_id=?', [$u['id']])->fetchColumn();
    out(['user' => $u, 'cart' => $n, 'cats' => $u ? cats() : [], 'cfg' => $u ? cfg() : null]);
}

// ---------------------------------------------------------------- Daftar & login

// ?a=register — daftar akun pembeli (OTP ke email, atau langsung jadi bila OTP daftar dimatikan di config.php)
function aksi_register() {
    global $in;
    $nama = trim($in['nama'] ?? ''); $e = strtolower(trim($in['email'] ?? '')); $pw = $in['password'] ?? '';
    if (!$nama || !filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6) err('Isi nama, email valid, dan sandi minimal 6 karakter.');
    if (q('SELECT 1 FROM users WHERE email=?', [$e])->fetch()) err('Email sudah terdaftar. Silakan login.');
    if (!otp_wajib('daftar')) {   // OTP pendaftaran dimatikan di config.php → akun langsung dibuat
        q("INSERT INTO users(nama,email,password,role) VALUES(?,?,?,'user')", [$nama, $e, password_hash($pw, PASSWORD_DEFAULT)]);
        catat('daftar', '', null, $e);
        out(['ok' => 1, 'langsung' => 1]);
    }
    tunggu($e, 'register');
    buat_otp($e, 'register', ['nama' => $nama, 'password' => password_hash($pw, PASSWORD_DEFAULT)]);
}

// ?a=login — cek email+sandi+peran, lalu OTP (atau langsung masuk bila OTP login dimatikan di config.php)
function aksi_login() {
    global $in;
    $role = $in['role'] ?? ''; $e = strtolower(trim($in['email'] ?? ''));
    if (!filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($e) > 120) err('Masukkan email yang valid.');
    q('DELETE FROM login_lock WHERE updated<DATE_SUB(NOW(),INTERVAL 1 DAY) AND (kunci_sampai IS NULL OR kunci_sampai<NOW())');   // bersihkan catatan lama
    if ($k = kunci_aktif($e)) kunci_tolak($e, $k['sisa']);   // selama dikunci, sandi tidak diperiksa sama sekali
    $u = q('SELECT * FROM users WHERE email=?', [$e])->fetch();
    if (!$u || $u['role'] !== $role || !password_verify($in['password'] ?? '', $u['password'])) {
        catat('login_gagal', "peran $role", null, $e);
        $sisa = gagal_login($e);
        if ($sisa <= 0) kunci_tolak($e, MENIT_KUNCI * 60);
        err("Email/sandi salah, atau akun ini bukan $role. Sisa percobaan: $sisa.");
    }
    q('DELETE FROM login_lock WHERE email=?', [$e]);   // sandi benar: hitungan gagal kembali ke nol
    if (!(int) $u['aktif']) { catat('login_diblokir', '', $u['id'], $e); err('Akun ini diblokir. Hubungi Super Admin.', 403); }
    if (!otp_wajib('login', $role)) {   // OTP login dimatikan di config.php → sandi benar langsung masuk
        buat_sesi_login($u, $e);
        out(['role' => $u['role']]);
    }
    tunggu($e, 'login');
    buat_otp($e, 'login', ['role' => $role]);
}

// ---------------------------------------------------------------- OTP

// ?a=otp_resend — kirim ulang kode OTP
function aksi_otp_resend() {
    global $in;
    $e = strtolower(trim($in['email'] ?? '')); $p = $in['purpose'] ?? '';
    if ($p === 'reset') proses_lupa($e);
    if ($p === 'aktivasi') { $k = kunci_aktif($e); if (!$k || !(int) $k['setuju']) err('Permintaan aktivasi belum disetujui Super Admin.', 403); }
    $r = q('SELECT * FROM otp_codes WHERE email=? AND purpose=?', [$e, $p])->fetch();
    if (!$r) err('Sesi OTP tidak ditemukan. Silakan ulangi dari awal.');
    tunggu($e, $p);
    buat_otp($e, $p, ['role' => $r['role'], 'nama' => $r['nama'], 'password' => $r['password']]);
}

// ?a=verify_otp — periksa kode OTP yang diketik (login / pendaftaran / aktivasi akun terkunci)
function aksi_verify_otp() {
    global $in;
    $e = strtolower(trim($in['email'] ?? '')); $p = $in['purpose'] ?? ''; $kode = preg_replace('/\D/', '', $in['code'] ?? '');
    if (!in_array($p, ['login', 'register', 'aktivasi'])) err('Permintaan tidak valid.');
    $r = q('SELECT * FROM otp_codes WHERE email=? AND purpose=? AND expires_at>NOW()', [$e, $p])->fetch();
    if (!$r) err('Kode sudah kedaluwarsa atau tidak ditemukan. Silakan kirim ulang kode.');
    if ($r['attempts'] >= 5) { q('DELETE FROM otp_codes WHERE id=?', [$r['id']]); err('Terlalu banyak percobaan salah. Silakan minta kode baru.'); }
    if (!password_verify($kode, $r['code_hash'])) {
        q('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?', [$r['id']]);
        catat('otp_salah', $p, null, $e);
        err('Kode OTP salah.');
    }
    q('DELETE FROM otp_codes WHERE id=?', [$r['id']]);
    if ($p === 'aktivasi') {   // OTP benar: kunci dibuka, hitungan gagal dihapus. Pemilik lalu login seperti biasa.
        q('DELETE FROM login_lock WHERE email=?', [$e]);
        catat('aktivasi_berhasil', 'kunci dibuka lewat OTP', null, $e);
        out(['ok' => 1, 'aktivasi' => 1]);
    }
    if ($p === 'login') {
        $u = q('SELECT * FROM users WHERE email=? AND role=? AND aktif=1', [$e, $r['role']])->fetch();
        if (!$u) err('Akun tidak ditemukan atau diblokir.');
        buat_sesi_login($u, $e);
        out(['role' => $u['role']]);
    }
    if (q('SELECT 1 FROM users WHERE email=?', [$e])->fetch()) err('Email sudah terdaftar. Silakan login.');
    q("INSERT INTO users(nama,email,password,role) VALUES(?,?,?,'user')", [$r['nama'], $e, $r['password']]);
    catat('daftar', '', null, $e);
    out(['ok' => 1]);
}

// ---------------------------------------------------------------- Akun terkunci & aktivasi ulang

// ?a=kunci_info — status kunci untuk layar "Akun Dikunci" (tidak membocorkan apakah email terdaftar)
function aksi_kunci_info() {
    global $in;
    $e = strtolower(trim($in['email'] ?? ''));
    $k = kunci_aktif($e);
    if (!$k) out(['terkunci' => 0]);
    out(['terkunci' => 1, 'sisa' => (int) $k['sisa'], 'diminta' => $k['minta'] ? 1 : 0, 'disetujui' => (int) $k['setuju'], 'masked' => mask($e)]);
}

// ?a=reaktivasi_minta — pemilik akun meminta Super Admin membuka kunci
function aksi_reaktivasi_minta() {
    global $in;
    $e = strtolower(trim($in['email'] ?? ''));
    $k = kunci_aktif($e);
    if (!$k) err('Akun ini tidak sedang dikunci. Silakan login seperti biasa.');
    if (!$k['minta']) {   // hanya diproses sekali per kunci supaya tidak bisa dipakai membanjiri email Super Admin
        q('UPDATE login_lock SET minta=NOW() WHERE email=?', [$e]);
        $u = q('SELECT id,nama,role FROM users WHERE email=? AND aktif=1', [$e])->fetch();
        if ($u && $u['role'] === 'superadmin') {   // tidak ada pihak di atas Super Admin: cukup bukti memegang emailnya lewat OTP
            q('UPDATE login_lock SET setuju=1 WHERE email=?', [$e]);
            catat('minta_aktivasi', 'Super Admin: aktivasi mandiri lewat OTP', $u['id'], $e);
        } elseif ($u) {
            catat('minta_aktivasi', 'meminta aktivasi ulang ke Super Admin', $u['id'], $e);
            try {
                $toko = S('nama_toko'); $n = htmlspecialchars($u['nama']); $em = htmlspecialchars($e);
                foreach (q("SELECT email FROM users WHERE role='superadmin' AND aktif=1")->fetchAll(PDO::FETCH_COLUMN) as $sa)
                    kirim_email($sa, "Permintaan aktivasi ulang akun - $toko",
                        "<div style='font-family:Arial,sans-serif'><h2 style='color:#ee4d2d'>" . htmlspecialchars($toko) . "</h2><p><b>$n</b> ($em) terkunci karena salah sandi " . MAKS_GAGAL
                        . " kali dan meminta aktivasi ulang.</p><p>Buka panel Super Admin → tab Akun → <b>Akun Terkunci Sementara</b> → Setujui. Jika bukan permintaan yang wajar, abaikan; kunci terbuka sendiri dalam 1 hari.</p></div>",
                        "$u[nama] ($e) terkunci dan meminta aktivasi ulang. Setujui di panel Super Admin → Akun → Akun Terkunci Sementara.");
            } catch (Throwable $t) { error_log('Men\'Shop notif: ' . $t->getMessage()); }
        }
    }
    out(['ok' => 1]);
}

// ?a=reaktivasi_otp — setelah Super Admin menyetujui, kode OTP dikirim ke email pemilik akun
function aksi_reaktivasi_otp() {
    global $in;
    $e = strtolower(trim($in['email'] ?? ''));
    $k = kunci_aktif($e);
    if (!$k) err('Akun ini tidak sedang dikunci. Silakan login seperti biasa.');
    if (!(int) $k['setuju']) err('Permintaan aktivasi belum disetujui Super Admin.', 403);
    tunggu($e, 'aktivasi');
    catat('aktivasi_otp_dikirim', '', null, $e);
    buat_otp($e, 'aktivasi');
}

// ---------------------------------------------------------------- Lupa sandi

// ?a=forgot — lupa sandi: kirim kode OTP reset
function aksi_forgot() {
    global $in;
    proses_lupa(strtolower(trim($in['email'] ?? '')));
}

// ?a=reset_password — simpan sandi baru setelah kode OTP reset benar
function aksi_reset_password() {
    global $in;
    $e = strtolower(trim($in['email'] ?? '')); $kode = preg_replace('/\D/', '', $in['code'] ?? ''); $pw = $in['password'] ?? '';
    if (strlen($pw) < 6) err('Sandi baru minimal 6 karakter.');
    $r = q("SELECT * FROM otp_codes WHERE email=? AND purpose='reset' AND expires_at>NOW()", [$e])->fetch();
    if (!$r) err('Kode sudah kedaluwarsa atau tidak ditemukan. Silakan kirim ulang kode.');
    if ($r['attempts'] >= 5) { q('DELETE FROM otp_codes WHERE id=?', [$r['id']]); err('Terlalu banyak percobaan salah. Silakan minta kode baru.'); }
    if (!password_verify($kode, $r['code_hash'])) {
        q('UPDATE otp_codes SET attempts=attempts+1 WHERE id=?', [$r['id']]);
        catat('otp_salah', 'reset', null, $e);
        err('Kode OTP salah.');
    }
    $u = q('SELECT id,nama FROM users WHERE email=? AND aktif=1', [$e])->fetch();
    q('DELETE FROM otp_codes WHERE id=?', [$r['id']]);
    if (!$u) err('Akun tidak ditemukan atau diblokir.');
    q('UPDATE users SET password=? WHERE id=?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
    catat('sandi_direset', 'lewat lupa sandi', $u['id'], $e);
    try {   // pemberitahuan keamanan ke pemilik akun
        $toko = S('nama_toko');
        kirim_email($e, "Sandi akun $toko telah diubah",
            "<div style='font-family:Arial,sans-serif'><h2 style='color:#ee4d2d'>" . htmlspecialchars($toko) . "</h2><p>Halo " . htmlspecialchars($u['nama'])
            . ", sandi akun Anda baru saja diubah. Jika ini bukan Anda, segera hubungi admin toko.</p></div>",
            "Sandi akun Anda baru saja diubah. Jika ini bukan Anda, segera hubungi admin toko.");
    } catch (Throwable $t) { error_log('Men\'Shop notif: ' . $t->getMessage()); }
    out(['ok' => 1]);
}

// ---------------------------------------------------------------- Keluar

// ?a=logout — keluar (hapus sesi)
function aksi_logout() {
    $_SESSION = []; session_destroy(); out(['ok' => 1]);
}
