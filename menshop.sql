-- ==========================================================
--  DATABASE MEN'SHOP  (MySQL / MariaDB - XAMPP)
--  Cara import: XAMPP -> Start Apache & MySQL -> buka
--  http://localhost/phpmyadmin -> tab "Import" -> pilih file ini -> Go
--  PERHATIAN: file ini MENGHAPUS & membuat ulang tabel users, products, cart, orders, dll.
--  Semua data lama (produk, akun, pesanan) diganti data awal di bawah. Backup dulu jika perlu.
-- ==========================================================
CREATE DATABASE IF NOT EXISTS menshop CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE menshop;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_lock, otp_codes, order_items, orders, cart, products, users;
SET FOREIGN_KEY_CHECKS = 1;

-- Tabel akun: peran superadmin / admin / user
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,            -- hash, bukan sandi asli
  role ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user',
  aktif TINYINT(1) NOT NULL DEFAULT 1,       -- 0 = akun diblokir
  akses VARCHAR(100) NULL,                   -- bagian panel yang boleh dibuka Admin (NULL = semua)
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(150) NOT NULL,
  kategori VARCHAR(50) NOT NULL,
  harga INT NOT NULL,
  stok INT NOT NULL DEFAULT 0,
  deskripsi TEXT,
  gambar VARCHAR(255) NULL,                  -- nama file gambar di folder uploads/
  INDEX idx_kategori (kategori)
) ENGINE=InnoDB;

CREATE TABLE cart (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cart (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  total INT NOT NULL,
  alamat TEXT NOT NULL,
  metode VARCHAR(40) NOT NULL,               -- QRIS, DANA, GoPay, Bank BCA, COD, dst.
  status VARCHAR(30) NOT NULL DEFAULT 'Menunggu Pembayaran',
  created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  nama VARCHAR(150) NOT NULL,
  harga INT NOT NULL,
  qty INT NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Kode OTP sementara (login & pendaftaran). Kode disimpan dalam bentuk hash, berlaku 5 menit.
CREATE TABLE otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(120) NOT NULL,
  purpose ENUM('login','register','reset','aktivasi') NOT NULL,
  role VARCHAR(20) NULL,                     -- peran yang dipakai saat login
  nama VARCHAR(100) NULL,                    -- data pendaftaran yang menunggu verifikasi
  password VARCHAR(255) NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created DATETIME NOT NULL,
  INDEX idx_otp (email, purpose)
) ENGINE=InnoDB;

-- Kunci sementara login: 3x salah sandi -> email dikunci 1 hari (akun TIDAK dihapus).
-- Dibuka lebih cepat lewat persetujuan Super Admin (kolom setuju) + kode OTP ke email pemilik akun.
CREATE TABLE login_lock (
  email VARCHAR(120) NOT NULL PRIMARY KEY,
  gagal INT NOT NULL DEFAULT 0,
  kunci_sampai DATETIME NULL,
  minta DATETIME NULL,                       -- kapan pemilik meminta aktivasi ulang
  setuju TINYINT(1) NOT NULL DEFAULT 0,      -- 1 = Super Admin sudah menyetujui
  setuju_oleh INT NULL,
  updated DATETIME NOT NULL,
  INDEX idx_kunci (kunci_sampai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data awal: 1 Super Admin + 5 Admin (masing-masing hanya untuk satu bagian panel).
-- Login memakai OTP ke email masing-masing.
-- Sandi: Super Admin = super123 | manopporazak = admin123 | ryukosensu = user123 | tiga admin lainnya = MenShop2026
-- (segera ganti lewat "Lupa sandi" atau reset oleh Super Admin)
INSERT INTO users (nama, email, password, role, akses) VALUES
('Super Admin','razakmanoppo11@gmail.com','$2y$10$PXaFX3K4lpFySB5hePh7uudNFKDAlNmDPJ5ox4YkfqRsOraLGA9Wa','superadmin',NULL),
('Admin Ringkasan','manopporazak@gmail.com','$2y$10$rsUezwKiBz5OHKl/tImvM.HMyfj.z4ZgmO1xB3IrGHv7Ynx4HPoRu','admin','ringkasan'),
('Admin Pesanan','ryukosensu@gmail.com','$2y$10$NffN23.Vxqm6XwBCgmIa/OYV0c3TTsKAAkCoaaYAyZo53mMNmw.oG','admin','pesanan'),
('Admin Produk','clanmnppo@gmail.com','$2y$10$G9MrQytnhHcarQ99B2bmmucnjQrHWlP9IRk8om6F1xKrEG1alR6Cq','admin','produk'),
('Admin Pantau Stok','suckssryan@gmail.com','$2y$10$G9MrQytnhHcarQ99B2bmmucnjQrHWlP9IRk8om6F1xKrEG1alR6Cq','admin','stok'),
('Admin Layanan','benimarumaru90@gmail.com','$2y$10$G9MrQytnhHcarQ99B2bmmucnjQrHWlP9IRk8om6F1xKrEG1alR6Cq','admin','layanan');

-- Data awal: produk contoh per kategori
INSERT INTO products (nama, kategori, harga, stok, deskripsi) VALUES
('Kaos Polos Cotton Combed','Baju Kaos Pria',55000,50,'Kaos Polos Cotton Combed - kualitas bagus, cocok untuk pria.'),
('Kaos Oversize Streetwear','Baju Kaos Pria',89000,50,'Kaos Oversize Streetwear - kualitas bagus, cocok untuk pria.'),
('Kemeja Flanel Kotak','Kemeja Pria',135000,50,'Kemeja Flanel Kotak - kualitas bagus, cocok untuk pria.'),
('Kemeja Putih Slimfit','Kemeja Pria',149000,50,'Kemeja Putih Slimfit - kualitas bagus, cocok untuk pria.'),
('Celana Chino Slimfit','Celana Panjang Pria',175000,50,'Celana Chino Slimfit - kualitas bagus, cocok untuk pria.'),
('Jeans Denim Biru','Celana Panjang Pria',215000,50,'Jeans Denim Biru - kualitas bagus, cocok untuk pria.'),
('Celana Pendek Chino','Celana Pendek Pria',85000,50,'Celana Pendek Chino - kualitas bagus, cocok untuk pria.'),
('Boxer Santai Katun','Celana Pendek Pria',49000,50,'Boxer Santai Katun - kualitas bagus, cocok untuk pria.'),
('Kacamata Hitam Aviator','Kacamata',79000,50,'Kacamata Hitam Aviator - kualitas bagus, cocok untuk pria.'),
('Kacamata Frame Retro','Kacamata',95000,50,'Kacamata Frame Retro - kualitas bagus, cocok untuk pria.'),
('Topi Baseball Polos','Topi',45000,50,'Topi Baseball Polos - kualitas bagus, cocok untuk pria.'),
('Topi Bucket Hat','Topi',59000,50,'Topi Bucket Hat - kualitas bagus, cocok untuk pria.'),
('Cincin Titanium Hitam','Cincin',65000,50,'Cincin Titanium Hitam - kualitas bagus, cocok untuk pria.'),
('Cincin Perak Polos','Cincin',120000,50,'Cincin Perak Polos - kualitas bagus, cocok untuk pria.'),
('Kalung Rantai Stainless','Kalung',85000,50,'Kalung Rantai Stainless - kualitas bagus, cocok untuk pria.'),
('Kalung Liontin Salib','Kalung',99000,50,'Kalung Liontin Salib - kualitas bagus, cocok untuk pria.'),
('Gelang Kulit Anyaman','Gelang',39000,50,'Gelang Kulit Anyaman - kualitas bagus, cocok untuk pria.'),
('Gelang Stainless Rantai','Gelang',75000,50,'Gelang Stainless Rantai - kualitas bagus, cocok untuk pria.'),
('Sneakers Putih Casual','Sepatu',245000,50,'Sneakers Putih Casual - kualitas bagus, cocok untuk pria.'),
('Sepatu Pantofel Kulit','Sepatu',325000,50,'Sepatu Pantofel Kulit - kualitas bagus, cocok untuk pria.'),
('Sandal Jepit Premium','Sandal',35000,50,'Sandal Jepit Premium - kualitas bagus, cocok untuk pria.'),
('Sandal Gunung Outdoor','Sandal',115000,50,'Sandal Gunung Outdoor - kualitas bagus, cocok untuk pria.');