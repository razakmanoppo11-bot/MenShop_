/* ======================================================================
 *  js/1-dasar.js — DASAR: konstanta, pembantu kecil, komunikasi ke server
 *
 *  Dimuat PERTAMA. Berisi hal yang dipakai semua halaman:
 *    AREA        = pintu masuk yang sedang dibuka (user / admin / superadmin), dibaca dari <body data-area>
 *    AREA_INFO   = judul & ikon halaman login tiap pintu masuk
 *    api()       = memanggil api.php (dikirim JSON)       apiForm() = kirim file/gambar
 *    toast()     = pesan hijau/merah di atas halaman       act() = bungkus aksi agar error tampil sebagai toast
 *    esc()       = pengaman teks sebelum ditaruh ke HTML   rp() = format Rupiah
 *
 *  MAU MENGUBAH?
 *    • Daftar status pesanan → STATUS di bawah (harus sama dengan STATUS_PESANAN di inc/08_pesanan.php).
 *    • Judul/teks halaman login → AREA_INFO.
 * ====================================================================== */

// ---------------------------------------------------------------- Data yang dipakai bersama
let CATS = [], CATLIST = [], EM = {}, CFG = { izin: {}, metode: [] };
const STATUS = ["Menunggu Pembayaran","Diproses","Dikirim","Selesai","Dibatalkan"];
const HOME = { user: "#/home", admin: "#/admin", superadmin: "#/superadmin" };

// ---------------------------------------------------------------- Tiga pintu masuk: index.html (pembeli), admin.html, superadmin.html
// ---- Tiga pintu masuk terpisah: index.html (pembeli), admin.html (admin), superadmin.html (super admin)
const AREA = ["admin", "superadmin"].includes(document.body.dataset.area) ? document.body.dataset.area : "user";
const AREA_URL = { user: "index.html", admin: "admin.html", superadmin: "superadmin.html" };
const LOGIN = "#/login", START = AREA === "user" ? "#/register" : LOGIN;
const GUEST_PAGES = AREA === "user" ? ["register", "login", "forgot", "aktivasi"] : ["login", "forgot", "aktivasi"];
const AREA_INFO = {
  user: { ikon: "🛍️", judul: "Masuk untuk Belanja", label: "", sub: "Login sebagai pembeli untuk berbelanja, melihat pesanan, dan menghubungi admin." },
  admin: { ikon: "🧑‍💼", judul: "Login Admin", label: "Admin", sub: "Khusus pengelola toko. Bagian panel yang bisa dibuka sesuai pembagian dari Super Admin.",
    catatan: "Halaman ini khusus Admin. Pembeli silakan masuk lewat halaman toko." },
  superadmin: { ikon: "🛡️", judul: "Login Super Admin", label: "Super Admin", sub: "Area terbatas dengan kendali penuh atas sistem. Setiap login tercatat.",
    catatan: "Jangan bagikan alamat halaman ini dan sandi Anda kepada siapa pun." },
};
function pindahArea(url) { location.replace(url); }

// ---------------------------------------------------------------- Pembantu kecil
const $ = s => document.querySelector(s);
const rp = n => "Rp" + Number(n).toLocaleString("id-ID");
const esc = s => String(s ?? "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const form = e => { e.preventDefault(); return Object.fromEntries(new FormData(e.target)); };
let user = null, cartN = 0;

// ---------------------------------------------------------------- Pindah halaman, pesan, dan komunikasi ke server
function go(h) { if (location.hash === h) route(); else location.hash = h; }
function toast(m, t = "ok") {
  $("#msg").innerHTML = `<div class="msg ${t}">${esc(m)}</div>`;
  setTimeout(() => $("#msg").innerHTML = "", 4000);
}
async function api(a, body, qs = "") {
  let r, d;
  try {
    r = await fetch(`api.php?a=${a}${qs}`, { method: body ? "POST" : "GET", headers: { "Content-Type": "application/json" }, body: body ? JSON.stringify(body) : undefined });
    d = await r.json();
  } catch { throw Object.assign(new Error("Server tidak merespons. Pastikan Apache & MySQL XAMPP menyala dan buka lewat http://localhost/menshop/"), { status: 0 }); }
  if (!r.ok) throw Object.assign(new Error(d.error || "Terjadi kesalahan"), { status: r.status, data: d });
  return d;
}
async function apiForm(a, fd) {   // kirim file (multipart); Content-Type diatur otomatis oleh browser
  let r, d;
  try { r = await fetch(`api.php?a=${a}`, { method: "POST", body: fd }); d = await r.json(); }
  catch { throw Object.assign(new Error("Upload gagal. Pastikan ukuran gambar tidak lebih dari 2 MB dan server menyala."), { status: 0 }); }
  if (!r.ok) throw Object.assign(new Error(d.error || "Terjadi kesalahan"), { status: r.status });
  return d;
}
async function act(fn) {
  try { await fn(); } catch (e) { toast(e.message, "err"); if (e.status === 401) go(LOGIN); }
}
