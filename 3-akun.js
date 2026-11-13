/* ======================================================================
 *  js/3-akun.js — HALAMAN AKUN: daftar, login, OTP, lupa sandi, akun terkunci
 *
 *  register()    halaman daftar               login()      halaman login (tampilannya mengikuti AREA)
 *  showOtp()     layar memasukkan kode OTP    forgot() / resetForm()  lupa sandi
 *  aktivasi() / terkunci()   akun terkunci karena 3x salah sandi
 *  hitungMundurKirimUlang()  tombol "Kirim ulang (60d)" di layar OTP
 *
 *  MAU MENGUBAH?
 *    • Mematikan OTP → ubah saklar di config.php (halaman ini otomatis menyesuaikan, tidak perlu diedit).
 *    • Teks/tampilan layar OTP → showOtp().
 *    • Lama jeda kirim ulang → hitungMundurKirimUlang() (samakan dengan tunggu() di inc/05_otp.php).
 * ====================================================================== */

// ---------------------------------------------------------------- Tombol "Kirim ulang" dengan hitung mundur (dipakai layar OTP & reset sandi)
function hitungMundurKirimUlang(tombol, detik = 60) {
  let t = detik;
  const tick = () => { tombol.textContent = t > 0 ? `Kirim ulang (${t}d)` : "Kirim ulang kode"; tombol.disabled = t > 0; };
  tick();
  const iv = setInterval(() => { if (!document.body.contains(tombol)) return clearInterval(iv); t--; tick(); if (t <= 0) clearInterval(iv); }, 1000);
}

function register() {
  $("#app").innerHTML = `<div class="box auth"><h2>🛍️ Buat Akun Men'Shop</h2><form id="f">
  <label>Nama lengkap</label><input name="nama" required><label>Email</label><input name="email" type="email" required>
  <label>Sandi (min. 6 karakter)</label><input name="password" type="password" minlength="6" required>
  <button class="btn" style="width:100%">Daftar</button></form>
  <p class="sm">Sudah punya akun? <a href="#/login" style="color:var(--o)"><b>Login di sini</b></a></p></div>`;
  $("#f").onsubmit = e => act(async () => {
    const r = await api("register", form(e));
    if (r.otp) showOtp(r);                                              // OTP aktif → layar kode
    else { toast("Akun berhasil dibuat. Silakan login."); go(LOGIN); }  // OTP daftar dimatikan di config.php
  });
}
function login() {
  const role = AREA, I = AREA_INFO[role];
  $("#app").innerHTML = `<div class="box auth login-${role}"><div class="lg-ikon">${I.ikon}</div><h2>${I.judul}</h2><p class="sm lg-sub">${I.sub}</p>
  <form id="f"><label>Email</label><input name="email" type="email" required autocomplete="username"><label>Sandi</label><input name="password" type="password" required autocomplete="current-password">
  <button class="btn" style="width:100%">Masuk</button></form>
  <p class="sm"><a href="#/forgot" style="color:var(--o)"><b>Lupa sandi?</b></a> &nbsp;·&nbsp; <a href="#/aktivasi" style="color:var(--o)"><b>Akun terkunci? Aktifkan kembali</b></a></p>
  ${role === "user" ? `<p class="sm">Belum punya akun? <a href="#/register" style="color:var(--o)"><b>Buat akun</b></a></p>` : `<p class="sm lg-note">${I.catatan}</p>`}</div>`;
  $("#f").onsubmit = e => act(async () => {
    const f = form(e);
    try {
      const r = await api("login", { ...f, role });
      if (r.otp) showOtp({ ...r, role });   // OTP aktif → tampilkan layar kode
      else go(HOME[r.role]);                // OTP login dimatikan di config.php → langsung masuk
    }
    catch (x) {
      if (x.status === 423 && x.data && x.data.terkunci) { toast(x.message, "err"); return terkunci({ email: x.data.email, role, sisa: x.data.sisa }); }
      throw x;
    }
  });
}

// ---- Akun dikunci sementara (3x salah sandi) & aktivasi ulang lewat Super Admin + OTP
function aktivasi() {
  $("#app").innerHTML = `<div class="box auth"><h2>🔓 Aktifkan Kembali Akun</h2>
  <p class="sm">Akun yang dikunci karena salah sandi 3 kali tidak dihapus. Masukkan email akun Anda untuk melihat status kunci dan meminta aktivasi ulang.</p>
  <form id="f"><label>Email</label><input name="email" type="email" required autofocus><button class="btn" style="width:100%">Lanjut</button></form>
  <p class="sm"><a href="#/login" style="color:var(--o)"><b>Kembali ke login</b></a></p></div>`;
  $("#f").onsubmit = e => act(async () => {
    const email = form(e).email.trim().toLowerCase(), st = await api("kunci_info", { email });
    if (!st.terkunci) throw new Error("Akun ini tidak sedang dikunci. Silakan login seperti biasa.");
    terkunci({ email, role: AREA, sisa: st.sisa });
  });
}
async function terkunci(i) {
  const back = LOGIN; let sisa = Number(i.sisa) || 0;
  $("#app").innerHTML = `<div class="box auth"><h2>🔒 Akun Dikunci Sementara</h2>
  <p>Sandi salah 3 kali, jadi akun <b>${esc(i.email)}</b> dikunci demi keamanan. <b>Akun tidak dihapus.</b> Kunci terbuka otomatis dalam <b id="ks"></b>.</p>
  <div id="kb"></div><p class="sm"><a href="${back}" style="color:var(--o)"><b>Kembali ke login</b></a></p></div>`;
  const ks = $("#ks"), p2 = x => String(x).padStart(2, "0"), mmss = n => `${Math.floor(n / 3600)} jam ${p2(Math.floor(n % 3600 / 60))} menit ${p2(n % 60)} detik`;
  ks.textContent = mmss(sisa);
  const iv = setInterval(() => {
    if (!document.body.contains(ks)) return clearInterval(iv);
    if (--sisa <= 0) { clearInterval(iv); toast("Kunci sudah terbuka. Silakan login lagi."); go(back); } else ks.textContent = mmss(sisa);
  }, 1000);
  const gambar = st => {
    const kb = $("#kb");
    if (!st.terkunci) { toast("Kunci sudah terbuka. Silakan login lagi."); return go(back); }
    if (st.disetujui) {
      kb.innerHTML = `<div class="msg ok">✅ Super Admin sudah menyetujui. Kirim kode OTP ke email ${esc(st.masked)}, lalu masukkan kodenya untuk mengaktifkan akun.</div>
      <button class="btn" id="ob" style="width:100%">Kirim kode OTP</button>`;
      $("#ob").onclick = () => act(async () => showOtp({ ...(await api("reaktivasi_otp", { email: i.email })), purpose: "aktivasi", role: i.role }));
    } else if (st.diminta) {
      kb.innerHTML = `<p class="sm">Permintaan Anda sudah dikirim ke Super Admin. Menunggu persetujuan. Anda juga bisa menghubungi Super Admin langsung.</p>
      <button class="btn g" id="cb" style="width:100%">Cek status persetujuan</button>`;
      $("#cb").onclick = () => act(async () => gambar(await api("kunci_info", { email: i.email })));
    } else {
      kb.innerHTML = `<p class="sm">Tidak mau menunggu? Minta Super Admin membuka kunci. Setelah disetujui, kode OTP dikirim ke email akun ini.</p>
      <button class="btn" id="mb" style="width:100%">Minta aktivasi ulang ke Super Admin</button>`;
      $("#mb").onclick = () => act(async () => { await api("reaktivasi_minta", { email: i.email }); toast("Permintaan dikirim. Tunggu persetujuan Super Admin."); gambar(await api("kunci_info", { email: i.email })); });
    }
  };
  await act(async () => gambar(await api("kunci_info", { email: i.email })));
}


function forgot() {
  $("#app").innerHTML = `<div class="box auth"><h2>🔑 Lupa Sandi</h2><p class="sm">Masukkan email akun Anda. Kami kirim kode 6 digit untuk membuat sandi baru.</p>
  <form id="f"><label>Email</label><input name="email" type="email" required autofocus><button class="btn" style="width:100%">Kirim Kode</button></form>
  <p class="sm"><a href="#/login" style="color:var(--o)"><b>Kembali ke login</b></a></p></div>`;
  $("#f").onsubmit = e => act(async () => resetForm(await api("forgot", form(e))));
}
function resetForm(i) {
  $("#app").innerHTML = `<div class="box auth"><h2>🔑 Buat Sandi Baru</h2>
  <p>Jika email <b>${esc(i.masked)}</b> terdaftar, kode 6 digit sudah dikirim ke sana. Berlaku 5 menit. Cek juga folder Spam.</p>
  <form id="f"><label>Kode OTP</label><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required autofocus style="text-align:center;font-size:24px;letter-spacing:8px">
  <label>Sandi baru (min. 6 karakter)</label><input name="password" type="password" minlength="6" autocomplete="new-password" required>
  <label>Ulangi sandi baru</label><input name="password2" type="password" minlength="6" autocomplete="new-password" required>
  <button class="btn" style="width:100%">Simpan Sandi Baru</button></form>
  <p><button class="btn g" id="rs" type="button" disabled></button> <a href="#/login" class="sm" style="color:var(--o)">Batal</a></p></div>`;
  const rs = $("#rs"); hitungMundurKirimUlang(rs);
  rs.onclick = () => act(async () => { await api("otp_resend", { email: i.email, purpose: "reset" }); toast("Jika email terdaftar, kode baru sudah dikirim."); resetForm(i); });
  $("#f").onsubmit = e => act(async () => {
    const f = form(e);
    if (f.password !== f.password2) throw new Error("Konfirmasi sandi tidak sama.");
    await api("reset_password", { email: i.email, code: f.code, password: f.password });
    toast("Sandi berhasil diubah. Silakan login dengan sandi baru."); go(LOGIN);
  });
}

function showOtp(i) {
  const back = i.purpose === "login" ? LOGIN : i.purpose === "aktivasi" ? "#/aktivasi" : "#/register";
  $("#app").innerHTML = `<div class="box auth"><h2>Verifikasi OTP</h2>
  <p>Kode 6 digit sudah dikirim ke <b>${esc(i.masked)}</b>. Berlaku 5 menit. Cek juga folder Spam.</p>
  <form id="f"><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required autofocus style="text-align:center;font-size:24px;letter-spacing:8px">
  <button class="btn" style="width:100%">Verifikasi</button></form>
  <p><button class="btn g" id="rs" type="button" disabled></button> <a href="${back}" class="sm" style="color:var(--o)">Batal</a></p></div>`;
  const rs = $("#rs"); hitungMundurKirimUlang(rs);
  rs.onclick = () => act(async () => { await api("otp_resend", { email: i.email, purpose: i.purpose }); toast("Kode baru sudah dikirim."); showOtp(i); });
  $("#f").onsubmit = e => act(async () => {
    const r = await api("verify_otp", { email: i.email, purpose: i.purpose, code: form(e).code });
    if (i.purpose === "login") go(HOME[r.role]);
    else if (i.purpose === "aktivasi") { toast("Akun aktif kembali. Silakan login."); go(LOGIN); }
    else { toast("Akun berhasil diverifikasi dan dibuat. Silakan login."); go(LOGIN); }
  });
}
