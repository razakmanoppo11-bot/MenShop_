<?php
/* ======================================================================
 *  inc/04_email.php — PENGIRIMAN EMAIL
 *
 *  kirim_email()  = kirim satu email lewat Gmail SMTP (PHPMailer). Dipakai semua notifikasi.
 *  kirim_otp()    = membuat isi email kode OTP lalu mengirimnya.
 *  Akun Gmail & sandi aplikasi ada di config.php ($SMTP).
 *
 *  MAU MENGUBAH?
 *    • Tampilan/isi email OTP → kirim_otp().
 *    • Email tidak terkirim? set $SMTP['debug']=true di config.php untuk melihat penyebabnya.
 * ====================================================================== */

// ---------------------------------------------------------------- Kirim email umum
function kirim_email($to, $subjek, $html, $alt) {
    global $SMTP;
    if (!empty($SMTP['dev_log'])) { file_put_contents(BASE_DIR . '/otp_log.txt', date('c') . " $to | $subjek | $alt\n", FILE_APPEND); return; }
    $SMTP['pass'] = preg_replace('/[^A-Za-z0-9]/', '', $SMTP['pass']);   // buang spasi/koma/tanda lain yang ikut tersalin
    if (!$SMTP['user'] || !$SMTP['pass']) throw new Exception('SMTP belum diisi di config.php (user & pass).');
    // PHPMailer boleh di folder phpmailer/ ATAU sejajar dengan api.php
    $dir = null;
    foreach ([BASE_DIR . '/phpmailer', BASE_DIR] as $d) { if (is_file("$d/PHPMailer.php") && is_file("$d/SMTP.php") && is_file("$d/Exception.php")) { $dir = $d; break; } }
    if (!$dir) throw new Exception('File PHPMailer.php / SMTP.php / Exception.php tidak ditemukan.');
    require_once "$dir/Exception.php";
    require_once "$dir/PHPMailer.php";
    require_once "$dir/SMTP.php";
    session_write_close();   // lepas kunci sesi supaya halaman lain tidak ikut menunggu selama email dikirim
    // Coba alamat IPv4 Gmail lebih dulu. Banyak jaringan/XAMPP di Windows mencoba IPv6 dulu, menunggu sampai timeout,
    // baru pindah ke IPv4 - inilah penyebab email OTP terlambat belasan detik. Jika gagal, otomatis coba nama host biasa.
    $hosts = []; $ip = gethostbyname($SMTP['host']);
    if ($ip !== $SMTP['host'] && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $hosts[] = $ip;
    $hosts[] = $SMTP['host'];
    $mulai = microtime(true); $gagal = null;
    foreach ($hosts as $h) {
        try {
            $m = new \PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP(); $m->Host = $h; $m->Port = $SMTP['port']; $m->SMTPAuth = true;
            $m->Username = $SMTP['user']; $m->Password = $SMTP['pass']; $m->Timeout = 12;
            if ($SMTP['secure']) $m->SMTPSecure = $SMTP['secure']; else $m->SMTPAutoTLS = false;
            $ssl = ['peer_name' => $SMTP['host']];   // sertifikat tetap diperiksa terhadap nama host asli
            // Perbaikan umum XAMPP/Windows: sertifikat SSL tidak dikenali PHP (hanya untuk localhost)
            if (!empty($SMTP['skip_ssl_verify'])) $ssl += ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true];
            $m->SMTPOptions = ['ssl' => $ssl];
            $m->CharSet = 'UTF-8'; $m->setFrom($SMTP['user'], $SMTP['from_name']); $m->addAddress($to);
            $m->Subject = $subjek; $m->isHTML(true); $m->Body = $html; $m->AltBody = $alt;
            $m->send();
            error_log(sprintf("Men'Shop email ke %s terkirim lewat %s dalam %.1f detik", $to, $h, microtime(true) - $mulai));
            return;
        } catch (\Throwable $t) {
            $gagal = $t;
            error_log(sprintf("Men'Shop email gagal lewat %s setelah %.1f detik: %s", $h, microtime(true) - $mulai, $t->getMessage()));
            if (stripos($t->getMessage(), 'authenticate') !== false) break;   // salah sandi: mencoba alamat lain tidak membantu
        }
    }
    throw $gagal;
}

// ---------------------------------------------------------------- Email kode OTP
function kirim_otp($to, $kode, $tujuan) {
    $toko = htmlspecialchars(S('nama_toko'));
    $ket = ['register' => 'pendaftaran akun', 'login' => 'login', 'reset' => 'reset sandi', 'aktivasi' => 'aktivasi ulang akun'][$tujuan] ?? 'verifikasi';
    $judul = ['register' => 'Kode verifikasi pendaftaran', 'login' => 'Kode login', 'reset' => 'Kode reset sandi', 'aktivasi' => 'Kode aktivasi akun'][$tujuan] ?? 'Kode verifikasi';
    $catatan = $tujuan === 'reset' ? ' Jika Anda tidak meminta reset sandi, abaikan email ini; sandi Anda tidak akan berubah.' : '';
    kirim_email($to, "$judul " . S('nama_toko') . ": $kode",
        "<div style='font-family:Arial,sans-serif'><h2 style='color:#ee4d2d'>$toko</h2>"
        . "<p>Kode OTP Anda untuk $ket:</p>"
        . "<p style='font-size:32px;font-weight:bold;letter-spacing:6px'>$kode</p>"
        . "<p>Berlaku 5 menit. Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku dari $toko.$catatan</p></div>",
        "Kode OTP " . S('nama_toko') . " Anda untuk $ket: $kode (berlaku 5 menit). Jangan bagikan kepada siapa pun.$catatan");
}
