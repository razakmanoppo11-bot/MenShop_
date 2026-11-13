/* ======================================================================
 *  js/4-toko.js — HALAMAN PEMBELI: beranda, keranjang, checkout, pesanan, bantuan
 *
 *  home() daftar produk · cart() keranjang · checkout() · pay() instruksi bayar
 *  orders() pesanan saya · help() tanya admin / laporkan masalah
 *
 *  MAU MENGUBAH?
 *    • Tampilan kartu produk → home().
 *    • Metode pembayaran tampil di checkout() (aktif/tidaknya diatur Super Admin).
 * ====================================================================== */

// ---------------------------------------------------------------- Beranda & produk
async function home(_, P) {
  const kat = P.get("kat") || "", kw = P.get("q") || "";
  const prods = await api("products", null, `&kat=${encodeURIComponent(kat)}&q=${encodeURIComponent(kw)}`);
  $("#app").innerHTML = `<div class="cats"><div class="w"><a class="chip ${kat ? "" : "on"}" href="#/home">Semua</a>
  ${CATS.map(([c, e]) => `<a class="chip ${c === kat ? "on" : ""}" href="#/home?kat=${encodeURIComponent(c)}">${e} ${c}</a>`).join("")}</div></div>
  <div class="w"><h3>${esc(kat || "Semua Produk")}${kw ? ` — hasil "${esc(kw)}"` : ""} <span class="sm">(${prods.length} barang)</span></h3>
  <div class="grid">${prods.map(p => `<div class="card"><div class="pic">${p.gambar ? `<img src="uploads/${esc(p.gambar)}" alt="${esc(p.nama)}" loading="lazy">` : (EM[p.kategori] || "🛍️")}</div>
  <div class="b"><div class="n">${esc(p.nama)}</div><div class="price">${rp(p.harga)}</div><div class="sm">${esc(p.kategori)} • stok ${p.stok}</div></div>
  ${user.role === "user" ? `<div style="padding:0 10px 10px"><button class="btn" style="width:100%" ${p.stok < 1 ? "disabled" : ""} onclick="addCart(${Number(p.id)})">+ Keranjang</button><a class="btn g" style="width:100%;margin-top:6px;text-align:center" href="#/help?produk=${Number(p.id)}">💬 Tanya Admin</a></div>` : ""}</div>`).join("") || "<p>Barang tidak ditemukan.</p>"}</div></div>`;
}
const addCart = id => act(async () => { const r = await api("cart_add", { id }); cartN++; header(); toast(`${r.nama} masuk keranjang.`); });

// ---------------------------------------------------------------- Keranjang
async function cart() {
  const items = await api("cart");
  const total = items.reduce((s, i) => s + i.qty * i.harga, 0);
  $("#app").innerHTML = `<div class="box"><h3>🛒 Keranjang Belanja</h3>` + (items.length ? `<div class="sc"><table>
  <tr><th>Barang</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th></tr>${items.map(i => `<tr><td>${i.gambar ? `<img class="th" src="uploads/${esc(i.gambar)}" alt="">` : (EM[i.kategori] || "")} ${esc(i.nama)}</td><td>${rp(i.harga)}</td>
  <td><input type="number" value="${i.qty}" min="0" max="${i.stok}" style="width:70px" onchange="setQty(${Number(i.id)},this.value)"></td><td>${rp(i.harga * i.qty)}</td></tr>`).join("")}</table></div>
  <p class="sm">Isi jumlah 0 untuk menghapus barang.</p><p>Total: <span class="tot">${rp(total)}</span></p>
  <a class="btn" href="#/checkout">Check Out</a> <a class="btn g" href="#/home">Lanjut Belanja</a>`
    : `<p>Keranjang kosong. <a href="#/home" style="color:var(--o)">Belanja sekarang</a></p>`) + `</div>`;
}
const setQty = (id, qty) => act(async () => { await api("cart_set", { id, qty: Number(qty) }); cartN = (await api("me")).cart; header(); cart(); });

// ---------------------------------------------------------------- Checkout & pembayaran
async function checkout() {
  const items = await api("cart");
  if (!items.length) { toast("Keranjang masih kosong.", "err"); return go("#/home"); }
  const sub = items.reduce((s, i) => s + i.qty * i.harga, 0);
  const ong = CFG.gratis_min > 0 && sub >= CFG.gratis_min ? 0 : CFG.ongkir;
  const M = { QRIS: "🔳 QRIS", DANA: "💙 DANA", GoPay: "💚 GoPay", COD: "📦 COD (Bayar di tempat)" };
  $("#app").innerHTML = `<form class="box" id="f"><h3>Check Out</h3><div class="sc"><table>
  ${items.map(i => `<tr><td>${esc(i.nama)} × ${i.qty}</td><td>${rp(i.qty * i.harga)}</td></tr>`).join("")}
  <tr><td>Subtotal</td><td>${rp(sub)}</td></tr><tr><td>Ongkos kirim${ong === 0 && CFG.ongkir > 0 ? " (gratis)" : ""}</td><td>${rp(ong)}</td></tr></table></div>
  <p>Total bayar: <span class="tot">${rp(sub + ong)}</span></p>
  ${CFG.gratis_min > 0 && sub < CFG.gratis_min ? `<p class="sm">Belanja ${rp(CFG.gratis_min - sub)} lagi untuk gratis ongkir.</p>` : ""}
  <label><b>Alamat pengiriman</b></label>
  <textarea name="alamat" rows="3" required placeholder="Nama penerima, alamat lengkap, no. HP"></textarea><b>Metode Pembayaran</b>
  ${CFG.metode.map(m => m === "Bank"
    ? `<label class="pay"><input type="radio" name="metode" value="Bank" required> 🏦 Transfer Bank
       <select name="bank">${["BCA", "BRI", "BNI", "Mandiri"].map(b => `<option>${b}</option>`).join("")}</select></label>`
    : `<label class="pay"><input type="radio" name="metode" value="${m}" required> ${M[m]}</label>`).join("")}
  <button class="btn" style="margin-top:10px">Buat Pesanan</button></form>`;
  $("#f").onsubmit = e => act(async () => { const r = await api("checkout", form(e)); go(`#/pay/${r.order}`); });
}
async function pay(id) {
  const o = (await api("orders")).find(x => x.id == id);
  if (!o) return go("#/orders");
  const I = CFG.info;
  const info = o.metode === "QRIS" ? `<p>Scan QRIS berikut (contoh tampilan):</p><div class="qr"></div>`
    : o.metode === "DANA" ? `<p>Kirim ke nomor DANA: <b>${esc(I.dana)}</b> a.n. ${esc(I.atas_nama)}</p>`
    : o.metode === "GoPay" ? `<p>Kirim ke nomor GoPay: <b>${esc(I.gopay)}</b> a.n. ${esc(I.atas_nama)}</p>`
    : o.metode.startsWith("Bank") ? `<p>Transfer ke <b>${esc(o.metode)}</b> no. rek <b>${esc(I.bank)}</b> a.n. ${esc(I.atas_nama)}</p>`
    : `<p>Siapkan uang tunai saat kurir tiba. Pesanan langsung diproses.</p>`;
  $("#app").innerHTML = `<div class="box" style="text-align:center"><h3>✅ Pesanan #${o.id} berhasil dibuat</h3>
  <p>Total: <span class="tot">${rp(o.total)}</span> (termasuk ongkir ${rp(o.ongkir)}) • Metode: <b>${esc(o.metode)}</b></p>${info}
  <a class="btn" href="#/orders">Lihat Pesanan Saya</a></div>`;
}

// ---------------------------------------------------------------- Pesanan saya
async function orders() {
  const os = await api("orders");
  $("#app").innerHTML = `<div class="w"><h3>Pesanan Saya</h3>` + (os.map(o => `<div class="box"><b>Pesanan #${o.id}</b> • ${esc(o.created)} • <span style="color:var(--o)">${esc(o.status)}</span>
  <ul>${o.items.map(i => `<li>${esc(i.nama)} × ${i.qty} — ${rp(i.harga * i.qty)}</li>`).join("")}</ul>
  <div class="sm">${esc(o.metode)} • ${esc(o.alamat)}${Number(o.ongkir) ? ` • ongkir ${rp(o.ongkir)}` : ""}</div><b>Total ${rp(o.total)}</b> <a class="btn g" style="float:right" href="#/help?order=${o.id}">Ada masalah?</a></div>`).join("") || `<div class="box">Belum ada pesanan.</div>`) + `</div>`;
}


// ---------------------------------------------------------------- Bantuan (tanya admin)
async function help(arg, P) {
  if (user.role !== "user") return go(HOME[user.role]);
  if (arg) { $("#app").innerHTML = `<div class="w" id="th"></div>`; return renderThread($("#th"), arg, "#/help"); }
  const [ts, os, ps] = await Promise.all([api("my_tickets"), api("orders"), api("products")]);
  const pSel = ps.find(p => p.id == P.get("produk"));
  $("#app").innerHTML = `<div class="w"><div class="box"><h3>Bantuan & Pertanyaan</h3><p class="sm">Tanyakan soal produk (ukuran, bahan, stok) atau laporkan masalah pesanan. Admin akan membalas di halaman ini.</p>
  <form id="hf"><label class="sm">Terkait pesanan (opsional)</label><select name="order_id"><option value="">— Tidak terkait pesanan —</option>
  ${os.map(o => `<option value="${o.id}" ${P.get("order") == o.id ? "selected" : ""}>Pesanan #${o.id} • ${esc(o.status)} • ${rp(o.total)}</option>`).join("")}</select>
  <label class="sm">Tanya soal produk (opsional)</label><select name="product_id"><option value="">— Tidak terkait produk —</option>${ps.map(p => `<option value="${p.id}" ${pSel && pSel.id == p.id ? "selected" : ""}>${esc(p.nama)} • ${rp(p.harga)}</option>`).join("")}</select>
  <input name="subjek" maxlength="100" value="${esc(pSel ? ("Tanya produk: " + pSel.nama).slice(0, 100) : "")}" placeholder="Judul (mis. Ukuran M cocok untuk tinggi 170 cm?)" required>
  <textarea name="pesan" rows="4" maxlength="1000" required placeholder="Tulis pertanyaan Anda, mis. ukuran M apakah pas untuk tinggi 170 cm dan berat 65 kg?"></textarea><button class="btn">Kirim ke Admin</button></form></div>
  <div class="box"><h3>Percakapan Saya</h3><div class="sc"><table><tr><th>#</th><th>Judul</th><th>Terkait</th><th>Status</th><th>Terakhir</th><th></th></tr>
  ${ts.map(t => `<tr><td>${t.id}</td><td>${esc(t.subjek)}</td><td>${terkait(t)}</td><td>${t.status === "Dijawab" ? `<b style="color:var(--o)">Dijawab admin</b>` : esc(t.status)}</td><td>${esc(t.updated)}</td>
  <td><a class="btn g" href="#/help/${t.id}">Buka</a></td></tr>`).join("") || `<tr><td colspan="6">Belum ada percakapan.</td></tr>`}</table></div></div></div>`;
  $("#hf").onsubmit = e => act(async () => { const r = await api("ticket_create", form(e)); toast("Pesan terkirim ke admin."); go(`#/help/${r.id}`); });
}
