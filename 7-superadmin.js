/* ======================================================================
 *  js/7-superadmin.js — PANEL SUPER ADMIN
 *
 *  SA_TABS        = daftar tab Super Admin
 *  saLaporan()    laporan penjualan          saAkun()      akun & hak akses & akun terkunci
 *  saProduk()     produk & kategori          saPengaturan() pengaturan toko
 *  saKeamanan()   log aktivitas & backup
 *
 *  MAU MENGUBAH?
 *    • Menambah tab → SA_TABS, buat fungsinya, lalu daftarkan di superadmin().
 * ====================================================================== */

const SA_TABS = [["laporan", "📊 Laporan"], ["akun", "👥 Akun & Hak Akses"], ["produk", "📦 Produk & Kategori"],
  ["pesanan", "🧾 Pesanan"], ["layanan", "💬 Layanan Pembeli"], ["pengaturan", "⚙️ Pengaturan"], ["keamanan", "🔒 Keamanan"]];
async function superadmin(tab, P) {
  if (user.role !== "superadmin") return go(HOME[user.role]);
  if (!SA_TABS.some(t => t[0] === tab)) tab = "laporan";
  $("#app").innerHTML = `<div class="w"><div class="tabs" style="flex-wrap:wrap">${SA_TABS.map(([k, l]) => `<a class="${k === tab ? "on" : ""}" href="#/superadmin/${k}">${l}</a>`).join("")}</div><div id="sa"></div></div>`;
  await ({ laporan: saLaporan, akun: saAkun, produk: saProduk, pesanan: panelPesanan, layanan: panelLayanan, pengaturan: saPengaturan, keamanan: saKeamanan })[tab]($("#sa"), P);
}


// ---------------------------------------------------------------- Laporan
async function saLaporan(el, P) {
  const r = await api("report", null, `&from=${encodeURIComponent(P.get("from") || "")}&to=${encodeURIComponent(P.get("to") || "")}`);
  const max = Math.max(1, ...r.harian.map(d => +d.omzet));
  const card = (t, v, s = "") => `<div class="box" style="flex:1;min-width:170px;margin:0"><div class="sm">${t}</div><div class="tot" style="font-size:20px">${v}</div><div class="sm">${s}</div></div>`;
  const tbl = (h, rows) => `<div class="sc"><table><tr>${h.map(x => `<th>${x}</th>`).join("")}</tr>${rows.join("") || `<tr><td colspan="${h.length}">Belum ada data.</td></tr>`}</table></div>`;
  el.innerHTML = `<div class="box"><h3>Laporan Penjualan</h3><form id="rf" class="fg"><label class="sm">Dari <input type="date" name="from" value="${r.from}"></label>
  <label class="sm">Sampai <input type="date" name="to" value="${r.to}"></label><button class="btn">Tampilkan</button>
  <button class="btn g" type="button" id="csv">⬇ Unduh CSV</button></form>
  <p class="sm">Omzet dihitung dari pesanan berstatus Diproses, Dikirim, dan Selesai (sudah termasuk ongkir).</p></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0">
  ${card("Omzet", rp(r.omzet), `${r.berhasil} pesanan berhasil`)}${card("Total pesanan", r.pesanan, `${r.pembeli} pembeli`)}
  ${card("Rata-rata / pesanan", rp(r.berhasil ? Math.round(r.omzet / r.berhasil) : 0))}${card("Nilai stok", rp(r.nilai_stok), `${r.produk} produk • ${r.user} akun pembeli`)}</div>
  <div class="box"><h3>Penjualan per Hari</h3>${r.harian.map(d => `<div style="display:flex;align-items:center;gap:8px;margin:3px 0">
  <span class="sm" style="width:90px">${esc(d.tgl)}</span><div style="background:var(--o);height:14px;width:${Math.max(1, Math.round(d.omzet / max * 60))}%;border-radius:3px"></div>
  <span class="sm">${rp(d.omzet)} (${d.n})</span></div>`).join("") || "<p class='sm'>Belum ada penjualan pada rentang ini.</p>"}</div>
  <div class="box"><h3>Produk Terlaris</h3>${tbl(["Produk", "Terjual", "Omzet"], r.terlaris.map(t => `<tr><td>${esc(t.nama)}</td><td>${t.qty}</td><td>${rp(t.omzet)}</td></tr>`))}</div>
  <div class="box"><h3>Status Pesanan</h3>${tbl(["Status", "Jumlah", "Nilai"], r.status.map(t => `<tr><td>${esc(t.status)}</td><td>${t.n}</td><td>${rp(t.t)}</td></tr>`))}</div>
  <div class="box"><h3>Metode Pembayaran</h3>${tbl(["Metode", "Pesanan", "Nilai"], r.metode.map(t => `<tr><td>${esc(t.metode)}</td><td>${t.n}</td><td>${rp(t.t)}</td></tr>`))}</div>
  <div class="box"><h3>Stok Menipis <span class="sm">(≤ ${CFG.stok_min})</span></h3>${tbl(["Produk", "Kategori", "Stok"], r.stok_menipis.map(t => `<tr><td>${esc(t.nama)}</td><td>${esc(t.kategori)}</td><td>${+t.stok === 0 ? merah("Habis") : merah(t.stok)}</td></tr>`))}</div>`;
  $("#rf").onsubmit = e => { const f = form(e); go(`#/superadmin/laporan?from=${f.from}&to=${f.to}`); };
  $("#csv").onclick = () => act(() => unduh("report_csv", `&from=${r.from}&to=${r.to}`, `laporan_${r.from}_${r.to}.csv`));
}


// ---------------------------------------------------------------- Akun & hak akses
const aksesCb = sel => AD_TABS.map(([k, l]) => `<label class="sm" style="white-space:nowrap;margin-right:10px"><input type="checkbox" name="ak_${k}" ${sel.includes(k) ? "checked" : ""} style="width:auto"> ${l}</label>`).join("");
const aksesDari = f => AD_TABS.map(t => t[0]).filter(k => f["ak_" + k]).join(",");
async function saAkun(el) {
  const us = await api("users"); window._users = us;
  const nm = { superadmin: "Super Admin", admin: "Admin", user: "Pembeli" };
  const kunciList = us.filter(u => +u.terkunci_sisa > 0);
  const stKunci = u => !+u.diminta ? "Belum meminta aktivasi" : +u.disetujui ? "✅ Disetujui, menunggu pemilik memasukkan OTP" : "⏳ Meminta aktivasi ulang";
  el.innerHTML = `<div class="box"><h3>🔒 Akun Terkunci Sementara <span class="sm">(3× salah sandi, akun tidak dihapus)</span></h3>
  ${kunciList.length ? `<div class="sc"><table><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Terbuka otomatis</th><th>Status</th><th>Aksi</th></tr>
  ${kunciList.map(u => `<tr><td>${esc(u.nama)}</td><td>${esc(u.email)}</td><td>${nm[u.role]}</td><td>${u.terkunci_sisa >= 3600 ? Math.ceil(u.terkunci_sisa / 3600) + " jam" : Math.ceil(u.terkunci_sisa / 60) + " menit"} lagi</td>
  <td>${u.role === "superadmin" ? "Dapat aktivasi mandiri lewat OTP" : stKunci(u)}</td>
  <td>${u.role === "superadmin" || +u.disetujui ? "—" : `<button class="btn" onclick="setujuAktivasi(${Number(u.id)})">Setujui aktivasi</button>`}</td></tr>`).join("")}</table></div>`
  : `<p class="sm">Tidak ada akun yang sedang terkunci. 👍</p>`}
  <p class="sm">Setelah disetujui, pemilik akun meminta kode OTP di halaman login (Akun terkunci? Aktifkan kembali), memasukkannya, lalu login seperti biasa. Anda juga bisa menyetujui tanpa menunggu permintaan, misalnya bila pemilik menghubungi Anda langsung.</p></div>
  <div class="box"><h3>Buat Akun Admin / Pembeli</h3><form id="f" class="fg">
  <input name="nama" placeholder="Nama" required><input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="Sandi (min. 6)" minlength="6" required>
  <select name="role"><option>admin</option><option>user</option></select>
  <div style="grid-column:1/-1"><b class="sm">Bagian panel yang boleh dibuka (khusus Admin):</b><br>${aksesCb(AD_TABS.map(t => t[0]))}</div><button class="btn">Buat</button></form>
  <p class="sm">Izin apa saja yang boleh dilakukan Admin diatur di tab <a href="#/superadmin/pengaturan" style="color:var(--o)">Pengaturan</a>.</p></div>
  <div class="box" id="eb" style="display:none"><h3>Ubah Akun</h3><form id="ef" class="fg"><input type="hidden" name="uid">
  <input name="nama" placeholder="Nama" required><input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="Sandi baru (kosongkan jika tidak diubah)" minlength="6">
  <div id="eak" style="grid-column:1/-1"><b class="sm">Bagian panel yang boleh dibuka (khusus Admin):</b><br>${aksesCb([])}</div>
  <button class="btn">Simpan</button> <button class="btn g" type="button" id="ec">Batal</button></form></div>
  <div class="box"><h3>Semua Akun <span class="sm">(${us.length})</span></h3><div class="sc"><table><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Akses panel</th><th>Status</th><th>Aksi</th></tr>
  ${us.map(u => {
    const self = u.id == user.id, sa = u.role === "superadmin";
    return `<tr><td>${esc(u.nama)}</td><td>${esc(u.email)}</td><td>${nm[u.role]}</td><td class="sm">${u.role === "admin" ? (u.akses ? u.akses.split(",").map(k => (AD_TABS.find(t => t[0] === k) || [k, k])[1]).join(", ") : "Semua") : "—"}</td><td>${!+u.aktif ? merah("Diblokir") : +u.terkunci_sisa > 0 ? merah("🔒 Terkunci") : "Aktif"}</td><td>
    <button class="btn g" onclick="editUser(${Number(u.id)})">Ubah / Reset sandi</button>
    ${self || sa ? `<span class="sm">${self ? "(akun Anda)" : ""}</span>` :
      `<select onchange="setRole(${Number(u.id)},this.value)">${["user", "admin"].map(r => `<option value="${r}" ${r === u.role ? "selected" : ""}>${nm[r]}</option>`).join("")}</select>
      <button class="btn g" onclick="setBlok(${Number(u.id)},${+u.aktif ? 0 : 1})">${+u.aktif ? "Blokir" : "Aktifkan"}</button>
      <button class="btn r" onclick="delUser(${Number(u.id)})">Hapus</button>`}</td></tr>`; }).join("")}</table></div></div>`;
  $("#f").onsubmit = e => act(async () => { const f = form(e); await api("user_add", { ...f, akses: aksesDari(f) }); toast("Akun dibuat."); route(); });
  $("#ef").onsubmit = e => act(async () => { const f = form(e); await api("user_edit", { ...f, id: f.uid, akses: aksesDari(f) }); toast("Akun diperbarui."); route(); });
  $("#ec").onclick = () => { $("#eb").style.display = "none"; };
}
function editUser(id) {
  const u = window._users.find(x => x.id == id), f = $("#ef");
  f.uid.value = u.id; f.nama.value = u.nama; f.email.value = u.email; f.password.value = "";
  const sel = u.akses ? u.akses.split(",") : AD_TABS.map(t => t[0]);
  AD_TABS.forEach(([k]) => { f["ak_" + k].checked = sel.includes(k); });
  $("#eak").style.display = u.role === "admin" ? "" : "none";
  $("#eb").style.display = ""; $("#eb").scrollIntoView({ behavior: "smooth", block: "center" });
}
const setujuAktivasi = id => confirm("Setujui aktivasi ulang akun ini? Pemilik lalu diminta memasukkan kode OTP yang dikirim ke emailnya.") && act(async () => { await api("kunci_setuju", { id }); toast("Disetujui. Pemilik bisa meminta kode OTP sekarang."); route(); });
const setRole = (id, role) => act(async () => { await api("user_role", { id, role }); toast("Peran diubah."); route(); });
const setBlok = (id, aktif) => (aktif || confirm("Blokir akun ini? Pemilik tidak bisa login sampai diaktifkan lagi.")) && act(async () => { await api("user_block", { id, aktif }); toast(aktif ? "Akun diaktifkan." : "Akun diblokir."); route(); });
const delUser = id => confirm("Hapus akun ini beserta keranjang dan pesanannya?") && act(async () => { await api("user_del", { id }); toast("Akun dihapus."); route(); });


// ---------------------------------------------------------------- Produk & kategori
async function saProduk(el) {
  el.innerHTML = `<div id="kb"></div><div id="pp"></div>`;
  const prods = await panelProduk($("#pp"));
  const cnt = c => prods.filter(p => p.kategori === c).length;
  $("#kb").innerHTML = `<div class="box"><h3>Kategori Produk</h3><form id="kf" class="fg"><input name="nama" placeholder="Nama kategori baru" required>
  <input name="emoji" placeholder="Emoji (mis. 🎒)" maxlength="8" style="max-width:140px"><button class="btn">Tambah</button></form>
  <div class="sc"><table><tr><th></th><th>Kategori</th><th>Produk</th><th>Aksi</th></tr>${CATLIST.map(c => `<tr><td>${esc(c.emoji)}</td><td>${esc(c.nama)}</td><td>${cnt(c.nama)}</td>
  <td><button class="btn g" onclick="editKat(${Number(c.id)})">Ubah</button> <button class="btn r" onclick="delKat(${Number(c.id)})">Hapus</button></td></tr>`).join("")}</table></div></div>`;
  $("#kf").onsubmit = e => act(async () => { await api("cat_add", form(e)); toast("Kategori ditambahkan."); route(); });
}
const editKat = id => {
  const c = CATLIST.find(x => x.id == id), n = prompt("Nama kategori:", c.nama); if (n === null) return;
  const em = prompt("Emoji:", c.emoji); if (em === null) return;
  act(async () => { await api("cat_edit", { id, nama: n, emoji: em }); toast("Kategori diperbarui."); route(); });
};
const delKat = id => confirm("Hapus kategori ini?") && act(async () => { await api("cat_del", { id }); toast("Kategori dihapus."); route(); });


// ---------------------------------------------------------------- Pengaturan toko & izin admin
async function saPengaturan(el) {
  const s = await api("settings_get");
  const ck = (k, l) => `<label class="pay"><input type="checkbox" name="${k}" ${s[k] === "1" ? "checked" : ""}> ${l}</label>`;
  const tx = (k, l, t = "text") => `<label class="sm">${l}</label><input name="${k}" type="${t}" ${t === "number" ? 'min="0"' : ""} value="${esc(s[k])}" required>`;
  el.innerHTML = `<form class="box" id="sf2"><h3>Toko</h3>${tx("nama_toko", "Nama toko")}
  <h3>Ongkos kirim</h3>${tx("ongkir", "Ongkos kirim (Rp)", "number")}${tx("gratis_ongkir_min", "Gratis ongkir untuk belanja minimal (Rp, isi 0 untuk menonaktifkan)", "number")}
  <h3>Metode pembayaran yang tersedia</h3>${ck("metode_qris", "🔳 QRIS")}${ck("metode_dana", "💙 DANA")}${ck("metode_gopay", "💚 GoPay")}${ck("metode_bank", "🏦 Transfer Bank")}${ck("metode_cod", "📦 COD")}
  ${tx("info_dana", "Nomor DANA")}${tx("info_gopay", "Nomor GoPay")}${tx("info_bank", "Nomor rekening bank")}${tx("atas_nama", "Atas nama")}
  <h3>Notifikasi & stok</h3>${ck("email_notif", "Kirim email ke pembeli saat status pesanan berubah")}${tx("stok_minimum", "Batas stok menipis (ditandai merah & masuk laporan)", "number")}
  <h3>Hak akses Admin</h3><p class="sm">Hilangkan centang untuk melarang Admin melakukan hal berikut (Super Admin selalu boleh).</p>
  ${ck("izin_admin_tambah", "Admin boleh menambah produk")}${ck("izin_admin_edit", "Admin boleh mengubah produk (harga, stok, dll.)")}
  ${ck("izin_admin_hapus", "Admin boleh menghapus produk")}${ck("izin_admin_status", "Admin boleh mengubah status pesanan")}
  <button class="btn" style="margin-top:12px">Simpan Pengaturan</button></form>`;
  $("#sf2").onsubmit = e => { e.preventDefault(); act(async () => {
    const d = {}; e.target.querySelectorAll("[name]").forEach(i => d[i.name] = i.type === "checkbox" ? (i.checked ? 1 : 0) : i.value);
    await api("settings_set", d); toast("Pengaturan disimpan."); route();
  }); };
}


// ---------------------------------------------------------------- Keamanan & backup
async function saKeamanan(el, P) {
  const aksi = P.get("aksi") || "";
  const r = await api("security", null, `&aksi=${encodeURIComponent(aksi)}`);
  const warn = [r.config.dev_log && "'dev_log' di config.php masih TRUE: kode OTP tersimpan di otp_log.txt yang bisa dibuka lewat browser. Ubah ke false.",
    r.config.debug && "'debug' di config.php masih TRUE: pesan error teknis ditampilkan ke pengguna. Ubah ke false saat dipakai sungguhan."].filter(Boolean);
  el.innerHTML = `${warn.map(w => `<div class="msg err" style="margin-bottom:8px">⚠️ ${esc(w)}</div>`).join("")}
  <div class="box"><h3>Backup Database</h3><p class="sm">Unduh salinan seluruh data (akun, produk, pesanan, pengaturan) sebagai file .sql. Simpan di tempat aman karena berisi data pembeli. Pulihkan lewat phpMyAdmin → Import.</p>
  <button class="btn" id="bk">⬇ Unduh Backup (.sql)</button> <span class="sm">${r.diblokir} akun sedang diblokir (kelola di tab Akun).</span></div>
  <div class="box"><h3>Aktivitas Mencurigakan <span class="sm">(≥3 login/OTP gagal dalam 1 jam)</span></h3><div class="sc"><table><tr><th>Email</th><th>Gagal</th><th>Terakhir</th><th>Aksi</th></tr>
  ${r.curiga.map(c => `<tr><td>${esc(c.email)}</td><td>${c.n}</td><td>${esc(c.terakhir)}</td><td>${c.uid && +c.aktif && c.role !== "superadmin" ? `<button class="btn r" onclick="setBlok(${Number(c.uid)},0)">Blokir akun</button>` : c.uid && !+c.aktif ? "Sudah diblokir" : "—"}</td></tr>`).join("")
    || `<tr><td colspan="4">Tidak ada aktivitas mencurigakan. 👍</td></tr>`}</table></div>
  <p class="sm">Setelah 3 kali salah sandi, login untuk email tersebut dikunci 1 hari (akun tidak dihapus). Kelola akun yang terkunci di tab Akun.</p></div>
  <div class="box"><h3>Log Aktivitas <span class="sm">(200 terakhir)</span></h3><select id="lf"><option value="">Semua aktivitas</option>${r.aksi.map(a => `<option ${a === aksi ? "selected" : ""}>${esc(a)}</option>`).join("")}</select>
  <div class="sc"><table><tr><th>Waktu</th><th>Aktivitas</th><th>Email</th><th>Detail</th><th>IP</th></tr>
  ${r.log.map(l => `<tr><td>${esc(l.created)}</td><td>${/gagal|salah|blokir|terkunci/.test(l.aksi) ? merah(esc(l.aksi)) : esc(l.aksi)}</td><td>${esc(l.email || "")}</td><td>${esc(l.detail || "")}</td><td>${esc(l.ip || "")}</td></tr>`).join("")
    || `<tr><td colspan="5">Belum ada aktivitas.</td></tr>`}</table></div></div>`;
  $("#bk").onclick = () => act(() => unduh("backup", "", `backup_menshop_${new Date().toISOString().slice(0, 10)}.sql`));
  $("#lf").onchange = e => go(`#/superadmin/keamanan?aksi=${encodeURIComponent(e.target.value)}`);
}
