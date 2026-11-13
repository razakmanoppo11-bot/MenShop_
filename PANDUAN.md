# Panduan Kode Men'Shop

Dokumen ini membantu Anda menemukan **bagian kode mana untuk apa**, supaya mengubah sesuatu
(misalnya mematikan OTP) tidak membingungkan.

---

## 1. Peta folder

```
menshop/
├── index.html          Pintu masuk PEMBELI
├── admin.html          Pintu masuk ADMIN
├── superadmin.html     Pintu masuk SUPER ADMIN
├── style.css           Tampilan (warna, tata letak)
├── menshop.sql         Struktur database awal (jangan di-import ulang jika data sudah ada!)
│
├── config.php          ★ PUSAT PENGATURAN: database, email, SAKLAR OTP
│
├── js/                 Kode yang jalan di browser (urutan dimuat 1 → 7)
│   ├── 1-dasar.js        konstanta, api(), toast(), pembantu kecil
│   ├── 2-router.js       pengatur halaman (#/...) + menu atas
│   ├── 3-akun.js         daftar, login, layar OTP, lupa sandi, akun terkunci
│   ├── 4-toko.js         beranda, keranjang, checkout, pesanan, bantuan (pembeli)
│   ├── 5-bersama.js      produk, pesanan, layanan (dipakai Admin & Super Admin)
│   ├── 6-admin.js        panel Admin
│   └── 7-superadmin.js   panel Super Admin
│
├── api.php             Pintu masuk server + DAFTAR AKSI (peta semua aksi)
├── inc/                Fungsi-fungsi pendukung server
│   ├── 00_dasar.php        sesi, out()/err()
│   ├── 01_database.php     koneksi MySQL, tabel otomatis
│   ├── 02_pengaturan.php   pengaturan toko & kategori
│   ├── 03_akun_akses.php   siapa yang login, hak akses, log aktivitas
│   ├── 04_email.php        kirim email
│   ├── 05_otp.php          kode OTP
│   ├── 06_kunci_login.php  kunci 3x salah sandi
│   ├── 07_produk_gambar.php gambar produk
│   ├── 08_pesanan.php      status pesanan
│   ├── 09_layanan.php      tanya admin
│   └── 10_laporan.php      pembantu laporan
├── aksi/               Isi setiap aksi API, per area
│   ├── akun.php  toko.php  layanan.php  admin.php  superadmin.php  laporan.php
└── phpmailer/          Pustaka pengirim email (jangan diubah)
```

## 2. Cara kerja (alur satu fitur)

Contoh: **login**.

1. Pengguna mengisi form di `login()` → **js/3-akun.js**
2. Browser memanggil `api("login", …)` → **js/1-dasar.js** → `api.php?a=login`
3. **api.php** mencari `login` di daftar `$AKSI` → menjalankan `aksi_login()` → **aksi/akun.php**
4. `aksi_login()` memeriksa sandi, lalu bertanya `otp_wajib('login', $role)` → **inc/05_otp.php**
5. Jika OTP wajib → `buat_otp()` → `kirim_otp()` → **inc/04_email.php**; browser menampilkan `showOtp()`
6. Pengguna mengetik kode → `aksi_verify_otp()` → **aksi/akun.php** → `buat_sesi_login()`

Pola ini sama untuk semua fitur: **nama aksi di URL = nama fungsi `aksi_<nama>`**.
Mau tahu kode sebuah tombol? Cari nama aksinya di `js/`, lalu buka `aksi_<nama>` di folder `aksi/`.

---

## 3. Mematikan OTP (contoh yang Anda tanyakan)

Buka **config.php**, bagian 3 `$FITUR`:

```php
$FITUR = [
    'otp_login' => [
        'user'       => true,   // ← ubah ke false = pembeli login tanpa OTP
        'admin'      => true,   // ← ubah ke false = Admin login tanpa OTP
        'superadmin' => true,   // ← ubah ke false = Super Admin login tanpa OTP
    ],
    'otp_daftar' => true,       // ← false = daftar langsung jadi akun, tanpa verifikasi email
];
```

Selesai, tidak ada file lain yang perlu diedit. Halaman login & daftar menyesuaikan sendiri.

Yang **tidak** bisa dimatikan: OTP untuk *Lupa sandi* dan *Aktifkan akun terkunci*
(itu satu-satunya bukti bahwa peminta adalah pemilik email).

> Saran: matikan OTP hanya untuk `user` bila perlu. Biarkan `admin` dan `superadmin` tetap `true`
> karena akun itu bisa mengubah harga, pesanan, dan akun orang lain.

---

## 4. Mau ubah apa → buka mana

| Ingin… | Buka | Bagian |
|---|---|---|
| Mematikan / menyalakan OTP | `config.php` | `$FITUR` |
| Ganti Gmail pengirim / sandi aplikasi | `config.php` | `$SMTP` |
| Melihat penyebab email gagal terkirim | `config.php` | `$SMTP['debug'] = true` |
| Uji tanpa email (kode ditulis ke file) | `config.php` | `$SMTP['dev_log']` (wajib `false` saat dipakai sungguhan) |
| Masa berlaku OTP (5 menit) | `inc/05_otp.php` | `buat_otp()` → `INTERVAL 5 MINUTE` (juga teks "5 menit" di `inc/04_email.php` & `js/3-akun.js`) |
| Jeda kirim ulang OTP (60 detik) | `inc/05_otp.php` + `js/3-akun.js` | `tunggu()` + `hitungMundurKirimUlang()` |
| Batas salah ketik OTP (5 kali) | `aksi/akun.php` | `aksi_verify_otp()` dan `aksi_reset_password()` → `attempts` |
| Isi/tampilan email OTP | `inc/04_email.php` | `kirim_otp()` |
| Tampilan layar OTP | `js/3-akun.js` | `showOtp()` |
| Berapa kali salah sandi sebelum dikunci | `inc/06_kunci_login.php` | `MAKS_GAGAL` (teks "3 kali" di `js/3-akun.js` diubah manual) |
| Lama akun dikunci | `inc/06_kunci_login.php` | `MENIT_KUNCI` (teks "1 hari" di `js/3-akun.js` diubah manual) |
| Teks & ikon halaman login | `js/1-dasar.js` | `AREA_INFO` |
| Ongkir, metode bayar, stok minimum, izin Admin | Panel Super Admin → **Pengaturan** | disimpan di database; nilai awal di `inc/02_pengaturan.php` |
| Nama/tambah status pesanan | `inc/08_pesanan.php` **dan** `js/1-dasar.js` | `STATUS_PESANAN` dan `STATUS` (harus sama) |
| Tampilan kartu produk | `js/4-toko.js` | `home()` |
| Form & tabel produk | `js/5-bersama.js` | `panelProduk()` |
| Tabel pesanan di panel | `js/5-bersama.js` | `panelPesanan()` |
| Menu bar atas | `js/2-router.js` | `header()` |
| Menambah halaman baru | `js/2-router.js` | `const views` di dalam `route()` |
| Tab panel Admin | `js/6-admin.js` **dan** `inc/03_akun_akses.php` | `AD_TABS` dan `PANEL` (harus sama) |
| Tab panel Super Admin | `js/7-superadmin.js` | `SA_TABS` + `superadmin()` |
| Ukuran/jenis gambar produk | `inc/07_produk_gambar.php` (+ `js/5-bersama.js`) | `MAKS_GAMBAR`, `gambar_simpan()` |
| Teks balasan otomatis "Asisten" | `inc/09_layanan.php` | `balasan_otomatis()` |
| Warna | `style.css` | `:root` (oranye pembeli) dan bagian TEMA di bawah (admin hijau, super admin ungu) |
| Menambah aksi API baru | `aksi/*.php` + `api.php` | tulis `function aksi_nama()`, lalu daftarkan di `$AKSI` |
| Menambah tabel/kolom database | `inc/01_database.php` + `menshop.sql` | `skema()` |

---

## 5. Cara memasang

1. **Cadangkan** folder lama Anda (terutama folder `uploads/` bila sudah ada gambar produk).
2. Salin seluruh isi folder `menshop` ini ke `htdocs/menshop/`, timpa file lama.
3. **Hapus** file lama yang sudah tidak dipakai: `app.js` (diganti folder `js/`).
   File `PHPMailer.php`, `SMTP.php`, `Exception.php` di folder utama juga boleh dihapus
   (salinan baru ada di `phpmailer/`).
4. **Jangan import `menshop.sql` lagi**: file itu menghapus semua data. Database Anda tetap dipakai apa adanya.
5. Buka `http://localhost/menshop/` dan coba login.

---

## 6. Catatan keamanan penting

* **Sandi aplikasi Gmail** (`$SMTP['pass']`) tertulis di `config.php` yang Anda kirim. Anggap sudah bocor:
  cabut di akun Google (Keamanan → Sandi aplikasi) dan buat yang baru.
* `menshop.sql` memuat komentar berisi **sandi awal** semua akun. Ganti sandi akun itu lewat "Lupa sandi"
  atau menu Akun di panel Super Admin, dan hapus komentar tersebut jika file ini dibagikan.
* Saat dipakai sungguhan: `$SMTP['debug'] = false` dan `$SMTP['dev_log'] = false`.
* Jangan membagikan `config.php`.
