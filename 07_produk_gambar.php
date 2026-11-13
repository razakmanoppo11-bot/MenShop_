<?php
/* ======================================================================
 *  inc/07_produk_gambar.php — GAMBAR PRODUK
 *
 *  Menyimpan & menghapus file gambar di folder uploads/ (nama file disimpan di products.gambar).
 *
 *  MAU MENGUBAH?
 *    • Ukuran maksimal gambar → MAKS_GAMBAR (juga dicek di js/5-bersama.js → panelProduk).
 *    • Jenis file yang boleh → array di gambar_simpan().
 * ====================================================================== */

// ---------------- Gambar produk (disimpan sebagai file di folder uploads/, nama file di kolom products.gambar)
const MAKS_GAMBAR = 2 * 1024 * 1024;   // 2 MB
function gambar_hapus($nama) {
    if ($nama && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp|gif)$/', $nama) && is_file(BASE_DIR . '/uploads/' . $nama)) @unlink(BASE_DIR . '/uploads/' . $nama);
}
function gambar_simpan($f) {
    if (!$f || !isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) err('Pilih file gambar dulu.');
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > MAKS_GAMBAR) err('Ukuran gambar maksimal 2 MB.');
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) err('Upload gambar gagal. Coba lagi.');
    $info = @getimagesize($f['tmp_name']);   // isi file diperiksa, bukan hanya ekstensinya
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2] ?? 0] ?? null;
    if (!$ext) err('File harus berupa gambar JPG, PNG, WEBP, atau GIF.');
    $dir = BASE_DIR . '/uploads';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) err('Folder uploads tidak bisa dibuat. Buat folder "uploads" manual di sebelah api.php.', 500);
    if (!is_file("$dir/.htaccess"))   // folder ini hanya boleh menyajikan gambar, tidak pernah menjalankan skrip
        @file_put_contents("$dir/.htaccess", "<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\nRequire all denied\n</FilesMatch>\nOptions -Indexes\n");
    $nama = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$nama")) err('Gagal menyimpan gambar ke folder uploads.', 500);
    return $nama;
}
