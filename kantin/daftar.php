<?php
require 'config.php';
if (siswa()) { header('Location: index.php'); exit; }
$err = '';
$v = ['nama' => '', 'kelas' => '', 'nis' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $v = [
    'nama'  => mb_substr(trim((string)($_POST['nama'] ?? '')), 0, 60),
    'kelas' => mb_substr(trim((string)($_POST['kelas'] ?? '')), 0, 20),
    'nis'   => trim((string)($_POST['nis'] ?? '')),
  ];
  $pw = (string)($_POST['password'] ?? '');
  if (!csrf_ok($_POST['csrf'] ?? '')) $err = 'Sesi habis. Muat ulang halaman lalu coba lagi.';
  elseif (mb_strlen($v['nama']) < 2) $err = 'Nama minimal 2 karakter.';
  elseif (!preg_match('/^[A-Za-z0-9]{4,20}$/', $v['nis'])) $err = 'NIS harus 4–20 karakter, hanya huruf dan angka.';
  elseif (strlen($pw) < 6) $err = 'Kata sandi minimal 6 karakter.';
  elseif (strlen($pw) > 72) $err = 'Kata sandi terlalu panjang (maksimal 72 karakter).';
  elseif ($pw !== (string)($_POST['konfirmasi'] ?? '')) $err = 'Konfirmasi kata sandi tidak sama.';
  else {
    try {
      $pdo->prepare("INSERT INTO siswa (nis, nama, kelas, password_hash) VALUES (?,?,?,?)")
          ->execute([$v['nis'], $v['nama'], $v['kelas'], password_hash($pw, PASSWORD_DEFAULT)]);
      session_regenerate_id(true);
      $_SESSION['siswa'] = ['id' => (int)$pdo->lastInsertId(), 'nama' => $v['nama'], 'kelas' => $v['kelas']];
      header('Location: index.php');
      exit;
    } catch (PDOException $x) {
      if ($x->getCode() === '23000') $err = 'NIS ini sudah terdaftar. Silakan masuk.';
      elseif ($x->getCode() === '42S02') $err = 'Pendaftaran belum aktif. Minta admin menjalankan migrasi_siswa.sql.';
      else { $err = 'Terjadi kesalahan pada server. Coba lagi.'; error_log('[kantin daftar] ' . $x->getMessage()); }
    }
  }
}
head('Daftar Siswa', 'daftar.php'); ?>
<div class="login-wrap">
  <form method="post" class="card login" id="f" novalidate>
    <div class="logo"><?= ico('user') ?></div>
    <h1>Daftar Siswa</h1>
    <p class="sub">Buat akun untuk memesan makanan dan memantau antrean.</p>
    <?php if ($err): ?><div class="alert err shake" role="alert"><?= ico('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
    <div class="field"><label for="nama">Nama lengkap</label><input class="input" id="nama" name="nama" required maxlength="60" autocomplete="name" autofocus value="<?= e($v['nama']) ?>"></div>
    <div class="field"><label for="kelas">Kelas <span class="muted">(opsional)</span></label><input class="input" id="kelas" name="kelas" maxlength="20" placeholder="contoh: XII RPL" value="<?= e($v['kelas']) ?>"></div>
    <div class="field"><label for="nis">NIS</label><input class="input" id="nis" name="nis" required maxlength="20" autocomplete="username" value="<?= e($v['nis']) ?>"><span class="hint">Dipakai untuk masuk. Huruf/angka, 4–20 karakter.</span></div>
    <div class="field"><label for="sandi">Kata sandi</label><div class="pw"><input class="input" id="sandi" name="password" type="password" required autocomplete="new-password"><button type="button" id="lihat" aria-pressed="false">Tampilkan</button></div><span class="hint">Minimal 6 karakter.</span></div>
    <div class="field"><label for="konfirmasi">Ulangi kata sandi</label><input class="input" id="konfirmasi" name="konfirmasi" type="password" required autocomplete="new-password"></div>
    <button class="btn btn-primary btn-lg btn-block" id="go">Daftar</button>
    <p class="alt">Sudah punya akun? <a href="login.php?sebagai=siswa">Masuk</a></p>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const f = document.getElementById('f'), p = document.getElementById('sandi'), l = document.getElementById('lihat'), g = document.getElementById('go');
  l.onclick = () => { const s = p.type === 'password'; p.type = s ? 'text' : 'password'; l.textContent = s ? 'Sembunyikan' : 'Tampilkan'; l.setAttribute('aria-pressed', s); };
  f.onsubmit = e => {
    const kosong = [...f.querySelectorAll('[required]')].find(i => !i.value.trim());
    if (kosong) { e.preventDefault(); kosong.focus(); return; }
    g.classList.add('loading'); g.textContent = 'Mendaftar';
  };
});
</script>
<?php foot();
