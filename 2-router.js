/* ======================================================================
 *  js/2-router.js — ROUTER & KEPALA HALAMAN
 *
 *  route()   = dijalankan setiap alamat (#/...) berubah: cek login, lalu menampilkan halaman yang cocok.
 *              DAFTAR HALAMAN ada di const views di dalam route().
 *  header()  = bilah atas (logo, pencarian, menu, tombol Log out).
 *  enhancePw = tombol 👁️ lihat sandi di semua kolom sandi.
 *
 *  MAU MENGUBAH?
 *    • Menambah halaman baru → buat fungsinya lalu daftarkan di const views di route().
 *    • Menu bilah atas → header().
 * ====================================================================== */

// ---------------------------------------------------------------- Router
async function route() {
  const [path, qs] = location.hash.slice(1).split("?");
  const [pg = "", arg] = path.split("/").filter(Boolean);
  const P = new URLSearchParams(qs || "");
  try { const m = await api("me"); user = m.user; cartN = m.cart;
    if (m.cfg) { CATLIST = m.cats; CATS = m.cats.map(c => [c.nama, c.emoji]); EM = Object.fromEntries(CATS); CFG = m.cfg; } }
  catch (e) { $("#head").innerHTML = ""; $("#app").innerHTML = `<div class="box auth"><div class="msg err">${esc(e.message)}</div></div>`; return; }
  document.body.classList.toggle("guest", !user);
  if (user && user.role !== AREA) return pindahArea(AREA_URL[user.role] + HOME[user.role]);   // peran lain diarahkan ke pintu masuknya sendiri
  if (!user && !GUEST_PAGES.includes(pg)) return go(START);
  if (user && ["register", "login", "forgot", "aktivasi", ""].includes(pg)) return go(HOME[user.role]);
  header();
  const views = { register, login, forgot, aktivasi, home, cart, checkout, pay, orders, help, admin, superadmin };
  if (!views[pg]) return go(user ? HOME[user.role] : START);
  await act(() => views[pg](arg, P));
}
// ---------------------------------------------------------------- Tombol lihat sandi
// Tombol 👁️ lihat / 🙈 sembunyikan sandi untuk semua kolom sandi
function enhancePw() {
  document.querySelectorAll('input[type="password"]:not([data-pw])').forEach(i => {
    i.dataset.pw = "1";
    const w = document.createElement("div"); w.className = "pwbox";
    i.parentNode.insertBefore(w, i); w.appendChild(i);
    const b = document.createElement("button"); b.type = "button"; b.className = "pweye"; b.textContent = "👁️";
    b.title = "Lihat sandi"; b.setAttribute("aria-label", b.title);
    b.onclick = () => {
      const show = i.type === "password"; i.type = show ? "text" : "password";
      b.textContent = show ? "🙈" : "👁️"; b.title = show ? "Sembunyikan sandi" : "Lihat sandi"; b.setAttribute("aria-label", b.title); i.focus();
    };
    w.appendChild(b);
  });
}
new MutationObserver(enhancePw).observe(document.body, { childList: true, subtree: true });
window.addEventListener("hashchange", route);
window.addEventListener("load", route);

// ---------------------------------------------------------------- Kepala halaman (menu atas)
function header() {
  if (!user) { $("#head").innerHTML = ""; return; }
  const P = new URLSearchParams(location.hash.split("?")[1] || "");
  const isUser = user.role === "user";
  $("#head").innerHTML = `<header><div class="bar"><a class="logo" href="${HOME[user.role]}">🛍️ ${esc(CFG.nama_toko || "Men'Shop")}${AREA !== "user" ? ` <span class="sm" style="background:#fff3;border-radius:4px;padding:1px 7px;font-size:12px">${AREA_INFO[AREA].label}</span>` : ""}</a>
  <form class="search" id="sf"><select name="kat"><option value="">Semua Kategori</option>
  ${CATS.map(([c]) => `<option ${P.get("kat") === c ? "selected" : ""}>${c}</option>`).join("")}</select>
  <input name="q" value="${esc(P.get("q") || "")}" placeholder="Cari barang di Men'Shop..."><button>Cari</button></form>
  <nav><a href="#/home">Beranda</a>
  ${isUser ? `<a href="#/cart">🛒 Keranjang <span class="badge">${cartN}</span></a><a href="#/orders">Pesanan</a><a href="#/help">Bantuan</a>` : user.role === "admin" ? `<a href="#/admin">Panel Admin</a>` : `<a href="#/superadmin">Panel Super Admin</a>`}
  
  <span class="sm">${esc(user.nama)} (${user.role})</span><a href="#" id="lo"><b>Log out</b></a></nav></div></header>`;
  $("#sf").onsubmit = e => { const f = form(e); location.hash = `#/home?kat=${encodeURIComponent(f.kat)}&q=${encodeURIComponent(f.q)}`; };
  $("#lo").onclick = e => { e.preventDefault(); act(async () => { await api("logout", {}); toast("Anda sudah logout."); go(LOGIN); }); };
}

// ---------------------------------------------------------------- Tautan ke halaman yang sama
// Tautan ke halaman yang sedang dibuka (mis. "Kembali ke login" saat layar OTP/terkunci masih di #/login)
// tidak memicu hashchange, jadi dimuat ulang manual supaya tombolnya tetap berfungsi.
document.addEventListener("click", e => {
  const a = e.target.closest && e.target.closest('a[href^="#/"]');
  if (a && a.getAttribute("href") === location.hash) { e.preventDefault(); route(); }
});
