/* ======================================================================
 *  js/5-bersama.js — BAGIAN YANG DIPAKAI ADMIN & SUPER ADMIN
 *
 *  unduh()         mengunduh file (CSV / backup)
 *  panelProduk()   tambah/ubah/hapus produk + gambar
 *  panelPesanan()  daftar pesanan + ubah status
 *  panelLayanan()  daftar & isi percakapan dengan pembeli (renderThread)
 *
 *  MAU MENGUBAH?
 *    • Kolom form produk → panelProduk().
 *    • Kolom tabel pesanan → panelPesanan().
 * ====================================================================== */

// ---------------------------------------------------------------- Pembantu umum
async function unduh(a, qs, nama) {
  const r = await fetch(`api.php?a=${a}${qs || ""}`);
  if (!r.ok) { let m = "Gagal mengunduh."; try { m = (await r.json()).error; } catch {} throw new Error(m); }
  const u = URL.createObjectURL(await r.blob()), l = document.createElement("a");
  l.href = u; l.download = nama; l.click(); URL.revokeObjectURL(u);
}
const izinAdmin = k => user.role === "superadmin" || !!CFG.izin[k];
const merah = t => `<span style="color:#d0011b"><b>${t}</b></span>`;

// ---------------------------------------------------------------- Produk
async function panelProduk(el) {
  const prods = await api("products");
  window._prods = prods;
  const canAdd = izinAdmin("tambah"), canEdit = izinAdmin("edit"), canDel = izinAdmin("hapus");
  el.innerHTML = (canAdd || canEdit ? `<div class="box"><h3 id="pt">Tambah Produk</h3><form id="pf" class="fg"><input type="hidden" name="pid">
  <input name="nama" placeholder="Nama produk" required><select name="kategori">${CATS.map(([c]) => `<option>${esc(c)}</option>`).join("")}</select>
  <input name="harga" type="number" min="0" placeholder="Harga" required><input name="stok" type="number" min="0" placeholder="Stok" required>
  <input name="deskripsi" placeholder="Deskripsi">
  <div style="display:flex;gap:8px;align-items:center"><img id="pv" alt="" style="display:none;width:44px;height:44px;object-fit:cover;border-radius:4px;border:1px solid #ddd">
  <input name="gambar" id="gb" type="file" accept="image/jpeg,image/png,image/webp,image/gif" title="Gambar produk (JPG/PNG/WEBP/GIF, maks. 2 MB)"></div>
  <label id="hg" class="sm" style="display:none"><input type="checkbox" name="hapus_gambar" style="width:auto"> Hapus gambar saat ini</label>
  <button class="btn">Simpan</button> <button class="btn g" type="button" id="pc" style="display:none">Batal edit</button></form></div>`
    : `<div class="box sm">Super Admin menonaktifkan izin tambah/ubah produk untuk Admin.</div>`) +
  `<div class="box"><h3>Daftar Produk <span class="sm">(${prods.length})</span></h3><div class="sc"><table><tr><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th></th></tr>
  ${prods.map(p => `<tr><td>${p.gambar ? `<img class="th" src="uploads/${esc(p.gambar)}" alt="">` : `<span class="sm">${EM[p.kategori] || "🛍️"}</span>`}</td><td>${esc(p.nama)}</td><td>${esc(p.kategori)}</td><td>${rp(p.harga)}</td>
  <td>${p.stok <= CFG.stok_min ? merah(p.stok) : p.stok}</td><td>${canEdit ? `<button class="btn g" onclick="editProduk(${Number(p.id)})">Edit</button> ` : ""}
  ${canDel ? `<button class="btn r" onclick="delProduk(${Number(p.id)})">Hapus</button>` : ""}</td></tr>`).join("")}</table></div>
  <p class="sm">Stok berwarna merah = di bawah batas stok minimum (${CFG.stok_min}).</p></div>`;
  const pf = $("#pf");
  if (pf) {
    pf.onsubmit = e => act(async () => {
      const f = form(e), edit = !!f.pid, file = pf.gambar.files[0], hapus = !!f.hapus_gambar;
      delete f.gambar; delete f.hapus_gambar;
      if (file && file.size > 2 * 1024 * 1024) throw new Error("Ukuran gambar maksimal 2 MB.");
      const r = await api(edit ? "product_edit" : "product_add", { ...f, id: f.pid });
      const id = edit ? f.pid : r.id;
      try {
        if (file) { const fd = new FormData(); fd.append("id", id); fd.append("gambar", file); await apiForm("product_image", fd); }
        else if (hapus) await api("product_image_del", { id });
      } catch (er) { toast("Data produk tersimpan, tetapi gambar gagal: " + er.message, "err"); setTimeout(route, 2500); return; }
      toast(edit ? "Produk diperbarui." : "Produk ditambahkan."); route();
    });
    $("#gb").onchange = () => { const x = $("#gb").files[0]; if (x) { $("#pv").src = URL.createObjectURL(x); $("#pv").style.display = ""; } };
    $("#pc").onclick = () => route();
  }
  return prods;
}
function editProduk(id) {
  const p = window._prods.find(x => x.id == id), f = $("#pf");
  if (!p || !f) return;
  f.pid.value = p.id; f.nama.value = p.nama; f.kategori.value = p.kategori; f.harga.value = p.harga; f.stok.value = p.stok; f.deskripsi.value = p.deskripsi || "";
  $("#pv").style.display = p.gambar ? "" : "none"; if (p.gambar) $("#pv").src = "uploads/" + p.gambar;
  $("#hg").style.display = p.gambar ? "" : "none"; f.hapus_gambar.checked = false; f.gambar.value = "";
  $("#pt").textContent = `Edit Produk #${p.id}`; $("#pc").style.display = ""; f.scrollIntoView({ behavior: "smooth", block: "center" });
}
const delProduk = id => confirm("Hapus produk ini?") && act(async () => { await api("product_del", { id }); toast("Produk dihapus."); route(); });

// ---------------------------------------------------------------- Pesanan
async function panelPesanan(el) {
  const os = await api("all_orders"), canSt = izinAdmin("status");
  el.innerHTML = `<div class="box"><h3>Pesanan Masuk <span class="sm">(${os.length})</span></h3><div class="fg"><select id="of"><option value="">Semua status</option>
  ${STATUS.map(s => `<option>${s}</option>`).join("")}</select><input id="oq" placeholder="Cari ID / pembeli / alamat"></div>
  ${canSt ? "" : `<p class="sm">Super Admin menonaktifkan izin ubah status untuk Admin.</p>`}<div class="sc"><table id="ot"></table></div></div>`;
  const draw = () => {
    const s = $("#of").value, k = $("#oq").value.toLowerCase();
    const rows = os.filter(o => (!s || o.status === s) && (!k || `${o.id} ${o.pembeli} ${o.alamat}`.toLowerCase().includes(k)));
    $("#ot").innerHTML = `<tr><th>#</th><th>Tanggal</th><th>Pembeli</th><th>Barang</th><th>Total</th><th>Metode</th><th>Alamat</th><th>Status</th></tr>` +
      (rows.map(o => `<tr><td>${o.id}</td><td>${esc(o.created)}</td><td>${esc(o.pembeli)}</td><td>${esc(o.barang)}</td><td>${rp(o.total)}</td><td>${esc(o.metode)}</td><td>${esc(o.alamat)}</td>
      <td>${canSt && o.status !== "Dibatalkan" ? `<select onchange="setStatus(${Number(o.id)},this.value)">${STATUS.map(x => `<option ${x === o.status ? "selected" : ""}>${x}</option>`).join("")}</select>` : esc(o.status)}
      ${canSt && o.status === "Menunggu Pembayaran" ? `<br><button class="btn" style="margin-top:4px" onclick="bayarOk(${Number(o.id)})">✓ Pembayaran diterima</button>` : ""}</td></tr>`).join("")
      || `<tr><td colspan="8">Tidak ada pesanan.</td></tr>`);
  };
  $("#of").onchange = draw; $("#oq").oninput = draw; draw();
}
const setStatus = (id, status) => act(async () => {
  if (status === "Dibatalkan" && !confirm("Batalkan pesanan ini? Stok barang akan dikembalikan dan pesanan tidak bisa diubah lagi.")) return route();
  await api("order_status", { id, status }); toast(status === "Dibatalkan" ? "Pesanan dibatalkan, stok dikembalikan." : "Status pesanan diperbarui."); if (status === "Dibatalkan") route();
});
const bayarOk = id => confirm("Pastikan uang sudah masuk ke rekening/e-wallet toko. Lanjutkan konfirmasi pembayaran?") && act(async () => { await api("order_paid", { id }); toast("Pembayaran dikonfirmasi, pesanan diproses."); route(); });

// ---------------------------------------------------------------- Layanan pembeli (percakapan)
const terkait = t => [t.order_id ? "Pesanan #" + t.order_id : "", t.produk ? "Produk: " + esc(t.produk) : ""].filter(Boolean).join(" • ") || "—";
async function renderThread(el, id, back) {
  const { t, msgs, order, produk } = await api("ticket", null, `&id=${Number(id)}`), staff = user.role !== "user";
  el.innerHTML = `<div class="box"><a href="${back}" class="sm" style="color:var(--o)">← Kembali</a><h3>#${t.id} ${esc(t.subjek)} <span class="sm">[${esc(t.status)}]</span></h3>
  <p class="sm">${staff ? `Pembeli: ${esc(t.pembeli)} (${esc(t.email)}) • ` : ""}${t.order_id ? `Pesanan #${t.order_id}${order ? ` • ${rp(order.total)} • ${esc(order.status)} • ${esc(order.metode)}` : ""}` : produk ? "" : "Tidak terkait pesanan"}${produk ? `${t.order_id ? " • " : ""}Produk: ${esc(produk.nama)}` : ""} • dibuat ${esc(t.created)}</p>
  ${msgs.map(m => `<div style="margin:8px 0;padding:8px 10px;border-radius:6px;background:${+m.staff === 2 ? "#eef6ff" : +m.staff ? "#fff3f0" : "#f1f1f1"}"><div class="sm"><b>${esc(m.nama)}</b>${+m.staff === 2 ? " 🤖 balasan otomatis" : +m.staff ? " (Staf toko)" : ""} • ${esc(m.created)}</div>
  <div style="white-space:pre-wrap">${esc(m.pesan)}</div></div>`).join("")}
  <form id="rpf"><textarea name="pesan" rows="3" maxlength="1000" required placeholder="Tulis balasan..."></textarea><button class="btn">Kirim</button>
  ${t.status !== "Selesai" ? ` <button class="btn g" type="button" id="tc">Tandai selesai</button>` : ""}</form></div>`;
  $("#rpf").onsubmit = e => act(async () => { await api("ticket_reply", { id: t.id, pesan: form(e).pesan }); toast("Pesan terkirim."); renderThread(el, id, back); });
  if ($("#tc")) $("#tc").onclick = () => act(async () => { await api("ticket_close", { id: t.id }); toast("Percakapan ditandai selesai."); renderThread(el, id, back); });
}
async function panelLayanan(el, P) {
  const base = user.role === "admin" ? "#/admin/layanan" : "#/superadmin/layanan";
  if (P.get("id")) return renderThread(el, P.get("id"), base);
  const ts = await api("tickets");
  el.innerHTML = `<div class="box"><h3>Layanan Pembeli <span class="sm">(${ts.length})</span></h3><select id="tf" style="max-width:220px"><option value="">Semua status</option><option>Terbuka</option><option>Dijawab</option><option>Selesai</option></select>
  <div class="sc"><table id="tt"></table></div></div>`;
  const draw = () => {
    const f = $("#tf").value, rows = ts.filter(t => !f || t.status === f);
    $("#tt").innerHTML = `<tr><th>#</th><th>Pembeli</th><th>Judul</th><th>Terkait</th><th>Status</th><th>Terakhir</th><th></th></tr>` + (rows.map(t => `<tr><td>${t.id}</td><td>${esc(t.pembeli)}</td><td>${esc(t.subjek)}</td>
      <td>${terkait(t)}</td><td>${t.status === "Terbuka" ? merah("Perlu dibalas") : esc(t.status)}</td><td>${esc(t.updated)}</td><td><a class="btn g" href="${base}?id=${t.id}">Buka</a></td></tr>`).join("")
      || `<tr><td colspan="7">Belum ada percakapan.</td></tr>`);
  };
  $("#tf").onchange = draw; draw();
}
