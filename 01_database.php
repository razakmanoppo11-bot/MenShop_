<?php
/* ======================================================================
 *  inc/01_database.php — DATABASE
 *
 *  Koneksi MySQL ($pdo), fungsi q() untuk menjalankan query, dan skema()
 *  yang otomatis menambah tabel/kolom baru bila belum ada (tanpa import ulang).
 *  Data koneksi (host/user/sandi) ada di config.php, BUKAN di sini.
 *
 *  MAU MENGUBAH?
 *    • Tabel/kolom baru → tambahkan di skema() (dan di menshop.sql untuk instalasi baru).
 *    • Contoh pakai q():  q('SELECT * FROM users WHERE id=?', [$id])->fetch();
 * ====================================================================== */

// ---------------------------------------------------------------- Koneksi
try {
    $pdo = new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Exception $e) {
    err('Database belum bisa diakses. Start MySQL di XAMPP dan import database/menshop.sql.', 500);
}

// ---------------------------------------------------------------- Pembantu query (prepared statement)
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s; }

// ---------------------------------------------------------------- Tabel & kolom tambahan otomatis
function kolom($t, $c, $def) {
    global $pdo;
    if (!q('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', [$t, $c])->fetch())
        $pdo->exec("ALTER TABLE $t ADD COLUMN $c $def");
}
function skema() {
    global $pdo;
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k VARCHAR(50) PRIMARY KEY, v TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, nama VARCHAR(50) NOT NULL UNIQUE,
        emoji VARCHAR(16) NOT NULL DEFAULT '🛍️', urut INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, email VARCHAR(120) NULL,
        aksi VARCHAR(40) NOT NULL, detail VARCHAR(255) NULL, ip VARCHAR(45) NULL, created DATETIME NOT NULL,
        INDEX idx_aksi (aksi, created), INDEX idx_email (email, created)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tickets (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, order_id INT NULL,
        subjek VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'Terbuka', created DATETIME NOT NULL, updated DATETIME NOT NULL,
        INDEX idx_tk_user (user_id), INDEX idx_tk_status (status, updated)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_msgs (id INT AUTO_INCREMENT PRIMARY KEY, ticket_id INT NOT NULL, user_id INT NOT NULL,
        staff TINYINT(1) NOT NULL DEFAULT 0, pesan TEXT NOT NULL, created DATETIME NOT NULL, INDEX idx_tm (ticket_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Catatan salah sandi & kunci sementara per email (juga untuk email yang tidak terdaftar, supaya orang luar tidak bisa menebak email mana yang ada)
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_lock (email VARCHAR(120) NOT NULL PRIMARY KEY, gagal INT NOT NULL DEFAULT 0, kunci_sampai DATETIME NULL,
        minta DATETIME NULL, setuju TINYINT(1) NOT NULL DEFAULT 0, setuju_oleh INT NULL, updated DATETIME NOT NULL,
        INDEX idx_kunci (kunci_sampai)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    kolom('order_items', 'product_id', 'INT NULL');
    $ct = q("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='otp_codes' AND COLUMN_NAME='purpose'")->fetchColumn();
    if ($ct && strpos($ct, "'aktivasi'") === false) $pdo->exec("ALTER TABLE otp_codes MODIFY purpose ENUM('login','register','reset','aktivasi') NOT NULL");
    kolom('users', 'aktif', 'TINYINT(1) NOT NULL DEFAULT 1');
    kolom('tickets', 'product_id', 'INT NULL');   // produk yang ditanyakan pembeli (opsional)
    kolom('users', 'akses', 'VARCHAR(100) NULL');   // bagian panel admin yang boleh dibuka (NULL = semua)
    kolom('products', 'gambar', 'VARCHAR(255) NULL');   // nama file gambar produk (folder uploads/)
    kolom('orders', 'ongkir', 'INT NOT NULL DEFAULT 0');
    foreach (PENGATURAN_BAWAAN as $k => $v) q('INSERT IGNORE INTO settings(k,v) VALUES(?,?)', [$k, $v]);
    if (!(int) q('SELECT COUNT(*) FROM categories')->fetchColumn()) {
        $n = 0;
        foreach ([['Baju Kaos Pria','👕'],['Kemeja Pria','👔'],['Celana Panjang Pria','👖'],['Celana Pendek Pria','🩳'],['Kacamata','🕶️'],
                  ['Topi','🧢'],['Cincin','💍'],['Kalung','📿'],['Gelang','⌚'],['Sepatu','👟'],['Sandal','🩴']] as $c)
            q('INSERT INTO categories(nama,emoji,urut) VALUES(?,?,?)', [$c[0], $c[1], ++$n]);
    }
}
