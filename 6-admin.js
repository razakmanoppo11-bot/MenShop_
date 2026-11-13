/* ======================================================================
 *  js/6-admin.js — PANEL ADMIN
 *
 *  AD_TABS  = daftar tab panel Admin (harus sama dengan PANEL di inc/03_akun_akses.php).
 *  admin()  = menampilkan tab sesuai hak akses akun.
 *  panelRingkasan() · panelStok()   (pesanan/produk/layanan ada di 5-bersama.js)
 *
 *  MAU MENGUBAH?
 *    • Menambah/mengganti nama tab → AD_TABS.
 * ====================================================================== */

// ---------------------------------------------------------------- Tab & pembuka panel
const AD_TABS = [["ringkasan", "🏠 Ringkasan"], ["pesanan", "🧾 Pesanan"], ["produk", "📦 Produk"], ["stok", "📉 Pantau Stok"], ["layanan", "💬 Layanan Pembeli"]];
async function admin(tab, P) {
  const boleh = AD_TABS.filter(t => !user.akses || user.akses.split(",").includes(t[0]));   // bagian yang boleh dibuka akun ini
  if (!boleh.some(t => t[0] === tab)) tab = boleh[0][0];
  $("#app").innerHTML = `<div class="w"><div class="tabs" style="flex-wrap:wrap">${boleh.map(([k, l]) => `<a class="${k === tab ? "on" : ""}" href="#/admin/${k}">${l}</a>`).join("")}</div><div id="ad"></div></div>`;
  await ({ ringkasan: panelRingkasan, pesanan: panelPesanan, produk: panelProduk, stok: panelStok, layanan: panelLayanan })[tab]($("#ad"), P);
}


// ---------------------------------------------------------------- Ringkasan
async function panelRingkasan(el) {
  const r = await api("admin_ringkasan");
  const card = (t, v, href, warn) => `<a href="${href}" class="box" style="flex:1;min-width:160px;margin:0;display:block"><div class="sm">${t}</div><div class="tot" ${warn && v ? 'style="color:#d0011b"' : ""}>${v}</div></a>`;
  const base = user.role === "admin" ? "#/admin" : "#/superadmin";
  el.innerHTML = `<div style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0">
  ${card("Menunggu pembayaran", r.bayar, `${base}/pesanan`, 1)}${card("Perlu dikirim (Diproses)", r.proses, `${base}/pesanan`, 1)}${card("Sedang dikirim", r.kirim, `${base}/pesanan`)}
  ${card("Pertanyaan belum dibalas", r.tiket, `${base}/layanan`, 1)}${card("Stok habis", r.habis, user.role === "admin" ? "#/admin/stok" : "#/superadmin/produk", 1)}</div>
  <div class="box"><h3>Barang Hampir Habis <span class="sm">(stok ≤ ${CFG.stok_min})</span></h3><div class="sc"><table><tr><th>Produk</th><th>Kategori</th><th>Stok</th></tr>
  ${r.menipis.map(p => `<tr><td>${esc(p.nama)}</td><td>${esc(p.kategori)}</td><td>${+p.stok === 0 ? merah("Habis") : merah(p.stok)}</td></tr>`).join("") || `<tr><td colspan="3">Semua stok aman. 👍</td></tr>`}</table></div></div>`;
}


// ---------------------------------------------------------------- Pantau stok
async function panelStok(el) {
  const prods = (await api("products")).sort((a, b) => a.stok - b.stok || a.id - b.id), canEdit = izinAdmin("edit");
  const lbl = s => +s === 0 ? merah("Habis") : +s <= CFG.stok_min ? merah("Menipis") : "Aman";
  el.innerHTML = `<div class="box"><h3>Pantau Stok</h3><label class="pay" style="max-width:340px"><input type="checkbox" id="sl"> Tampilkan hanya yang menipis / habis (≤ ${CFG.stok_min})</label>
  ${canEdit ? "" : `<p class="sm">Super Admin menonaktifkan izin ubah stok untuk Admin.</p>`}<div class="sc"><table id="stt"></table></div></div>`;
  const draw = () => {
    const rows = prods.filter(p => !$("#sl").checked || +p.stok <= CFG.stok_min);
    $("#stt").innerHTML = `<tr><th>Produk</th><th>Kategori</th><th>Status</th><th>Stok</th></tr>` + (rows.map(p => `<tr><td>${esc(p.nama)}</td><td>${esc(p.kategori)}</td><td>${lbl(p.stok)}</td>
      <td>${canEdit ? `<input type="number" min="0" id="st${Number(p.id)}" value="${Number(p.stok)}" style="width:90px"> <button class="btn g" onclick="simpanStok(${Number(p.id)})">Simpan</button>` : p.stok}</td></tr>`).join("")
      || `<tr><td colspan="4">Tidak ada produk pada filter ini.</td></tr>`);
  };
  $("#sl").onchange = draw; draw();
}
const simpanStok = id => act(async () => { await api("stock_set", { id, stok: $("#st" + id).value }); toast("Stok diperbarui."); route(); });
