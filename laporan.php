<?php
/* ======================================================================
 *  aksi/laporan.php — LAPORAN, KEAMANAN, BACKUP (Super Admin)
 *
 *  Laporan penjualan, log keamanan, dan unduh backup database.
 * ====================================================================== */

// ---------------------------------------------------------------- Laporan

// ?a=report — data laporan penjualan
function aksi_report() {
    $OKSTATUS = SQL_STATUS_PENJUALAN;   // pesanan yang dihitung sebagai penjualan
    need('superadmin'); [$d1, $d2] = rentang();
    $w = 'o.created>=? AND o.created<DATE_ADD(?,INTERVAL 1 DAY)'; $p = [$d1, $d2];
    $r = q("SELECT COUNT(*) pesanan, COUNT(DISTINCT o.user_id) pembeli,
            COALESCE(SUM(CASE WHEN {$OKSTATUS} THEN o.total END),0) omzet,
            COALESCE(SUM(CASE WHEN {$OKSTATUS} THEN 1 ELSE 0 END),0) berhasil FROM orders o WHERE $w", $p)->fetch();
    $r = array_map('intval', $r);
    $r['from'] = $d1; $r['to'] = $d2;
    $r['status'] = q("SELECT o.status, COUNT(*) n, COALESCE(SUM(o.total),0) t FROM orders o WHERE $w GROUP BY o.status", $p)->fetchAll();
    $r['harian'] = q("SELECT DATE(o.created) tgl, COUNT(*) n, SUM(o.total) omzet FROM orders o WHERE $w AND {$OKSTATUS} GROUP BY DATE(o.created) ORDER BY tgl", $p)->fetchAll();
    $r['terlaris'] = q("SELECT i.nama, SUM(i.qty) qty, SUM(i.qty*i.harga) omzet FROM order_items i JOIN orders o ON o.id=i.order_id
            WHERE $w AND {$OKSTATUS} GROUP BY i.nama ORDER BY qty DESC, omzet DESC LIMIT 10", $p)->fetchAll();
    $r['metode'] = q("SELECT o.metode, COUNT(*) n, SUM(o.total) t FROM orders o WHERE $w AND {$OKSTATUS} GROUP BY o.metode ORDER BY t DESC", $p)->fetchAll();
    $r['stok_menipis'] = q('SELECT id,nama,kategori,stok FROM products WHERE stok<=? ORDER BY stok,id LIMIT 30', [(int) S('stok_minimum')])->fetchAll();
    $r['produk'] = (int) q('SELECT COUNT(*) FROM products')->fetchColumn();
    $r['nilai_stok'] = (int) q('SELECT COALESCE(SUM(stok*harga),0) FROM products')->fetchColumn();
    $r['user'] = (int) q("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    out($r);
}

// ?a=report_csv — unduh laporan sebagai CSV
function aksi_report_csv() {
    need('superadmin'); [$d1, $d2] = rentang();
    $rows = q("SELECT o.id,o.created,u.nama,u.email,
        (SELECT GROUP_CONCAT(CONCAT(i.nama,' x',i.qty) SEPARATOR '; ') FROM order_items i WHERE i.order_id=o.id) barang,
        o.total-o.ongkir subtotal,o.ongkir,o.total,o.metode,o.status,o.alamat
        FROM orders o JOIN users u ON u.id=o.user_id WHERE o.created>=? AND o.created<DATE_ADD(?,INTERVAL 1 DAY) ORDER BY o.id", [$d1, $d2])->fetchAll(PDO::FETCH_NUM);
    catat('laporan_csv', "$d1 s/d $d2");
    header('Content-Type: text/csv; charset=utf-8'); header("Content-Disposition: attachment; filename=\"laporan_{$d1}_{$d2}.csv\"");
    echo "\xEF\xBB\xBF"; $f = fopen('php://output', 'w');
    fputcsv($f, ['ID', 'Tanggal', 'Pembeli', 'Email', 'Barang', 'Subtotal', 'Ongkir', 'Total', 'Metode', 'Status', 'Alamat']);
    foreach ($rows as $row) fputcsv($f, array_map('csv_aman', $row));
    fclose($f); exit;
}

// ---------------------------------------------------------------- Keamanan & backup

// ?a=security — data tab Keamanan (aktivitas mencurigakan + log)
function aksi_security() {
    need('superadmin'); $f = $_GET['aksi'] ?? '';
    global $SMTP;
    out([
        'curiga' => q("SELECT l.email, COUNT(*) n, MAX(l.created) terakhir, MAX(u.id) uid, MAX(u.aktif) aktif, MAX(u.role) role
            FROM activity_log l LEFT JOIN users u ON u.email=l.email
            WHERE l.aksi IN ('login_gagal','otp_salah') AND l.email IS NOT NULL AND l.created>DATE_SUB(NOW(),INTERVAL 1 HOUR)
            GROUP BY l.email HAVING n>=3 ORDER BY n DESC")->fetchAll(),
        'log' => q('SELECT l.id,l.created,l.aksi,l.detail,l.ip,COALESCE(l.email,u.email) email FROM activity_log l LEFT JOIN users u ON u.id=l.user_id
            WHERE (?=\'\' OR l.aksi=?) ORDER BY l.id DESC LIMIT 200', [$f, $f])->fetchAll(),
        'aksi' => q('SELECT DISTINCT aksi FROM activity_log ORDER BY aksi')->fetchAll(PDO::FETCH_COLUMN),
        'diblokir' => (int) q('SELECT COUNT(*) FROM users WHERE aktif=0')->fetchColumn(),
        'config' => ['dev_log' => !empty($SMTP['dev_log']), 'debug' => !empty($SMTP['debug'])],
    ]);
}

// ?a=backup — unduh backup database (.sql)
function aksi_backup() {
    global $pdo;
    need('superadmin'); catat('backup', 'unduh backup database');
    $nm = 'backup_menshop_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8'); header("Content-Disposition: attachment; filename=\"$nm\"");
    echo "-- Backup Men'Shop " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type="BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as [$t]) {
        $c = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        echo "DROP TABLE IF EXISTS `$t`;\n{$c[1]};\n\n";
        if ($t === 'otp_codes') continue;   // kode OTP sementara tidak perlu dibackup
        $st = $pdo->query("SELECT * FROM `$t`");
        while ($row = $st->fetch(PDO::FETCH_NUM)) echo "INSERT INTO `$t` VALUES(" . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ");\n";
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n"; exit;
}
