<?php
require 'config.php';
$halamanStaf = ['dapur.php', 'terpadu.php', 'kasir.php', 'laporan.php'];
$ok = array_merge($halamanStaf, ['index.php', 'pesan.php', 'riwayat.php', 'status.php']);
$next = in_array($_GET['next'] ?? '', $ok, true) ? $_GET['next'] : 'index.php';
if ($next === 'kasir.php') $next = 'terpadu.php';
$role = $_GET['sebagai'] ?? (in_array($next, $halamanStaf, true) ? 'staf' : 'siswa');
$role = $role === 'staf' ? 'staf' : 'siswa';
if (($role === 'staf' && staf()) || ($role === 'siswa' && siswa())) { header("Location: $next"); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $berhasil = false;
  $terkunci = ($_SESSION['kunci'] ?? 0) > time();
  if ($terkunci) {
    $err = 'Terlalu banyak percobaan. Coba lagi dalam ' . ($_SESSION['kunci'] - time()) . ' detik.';
  } elseif (!csrf_ok($_POST['csrf'] ?? '')) {
    $err = 'Sesi habis. Muat ulang halaman lalu coba lagi.';
  } elseif ($role === 'staf') {
    $berhasil = hash_equals(PIN_STAF, (string)($_POST['pin'] ?? ''));
    if ($berhasil) { session_regenerate_id(true); $_SESSION['staf'] = true; }
    else $err = 'PIN salah. Periksa kembali lalu coba lagi.';
  } else {
    try {
      $s = $pdo->prepare("SELECT id, nama, kelas, password_hash FROM siswa WHERE nis = ?");
      $s->execute([trim((string)($_POST['nis'] ?? ''))]);
      $r = $s->fetch();
      $berhasil = $r && password_verify((string)($_POST['password'] ?? ''), $r['password_hash']);
      if ($berhasil) { session_regenerate_id(true); $_SESSION['siswa'] = ['id' => (int)$r['id'], 'nama' => $r['nama'], 'kelas' => $r['kelas']]; }
      else $err = 'NIS atau kata sandi salah.';
    } catch (PDOException $x) {
      $err = $x->getCode() === '42S02' ? 'Akun siswa belum aktif. Minta admin menjalankan migrasi_siswa.sql.' : 'Terjadi kesalahan pada server. Coba lagi.';
      error_log('[kantin login] ' . $x->getMessage());
    }
  }
  if ($berhasil) {
    unset($_SESSION['gagal'], $_SESSION['kunci']);
    header("Location: $next");
    exit;
  }
  if ($err && !$terkunci) {
    usleep(500000);
    $_SESSION['gagal'] = ($_SESSION['gagal'] ?? 0) + 1;
    if ($_SESSION['gagal'] >= 5) { $_SESSION['kunci'] = time() + 60; $_SESSION['gagal'] = 0; }
  }
}
$q = '&next=' . urlencode($next);
head('Masuk', 'login.php'); ?>
<div class="login-wrap">
  <div class="card login">
    <div class="logo"><?= ico($role === 'staf' ? 'lock' : 'user') ?></div>
    <h1>Masuk</h1>
    <p class="sub">Pilih masuk sebagai siapa.</p>
    <nav class="roles" aria-label="Masuk sebagai">
      <a href="login.php?sebagai=siswa<?= e($q) ?>"<?= $role === 'siswa' ? ' aria-current="page"' : '' ?>><?= ico('user') ?>Siswa</a>
      <a href="login.php?sebagai=staf<?= e($q) ?>"<?= $role === 'staf' ? ' aria-current="page"' : '' ?>><?= ico('store') ?>Staf Kantin</a>
    </nav>
    <?php if ($err): ?><div class="alert err shake" role="alert"><?= ico('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
    <form method="post" id="f" novalidate>
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <?php if ($role === 'siswa'): ?>
      <div class="field"><label for="nis">NIS</label><input class="input" id="nis" name="nis" autocomplete="username" required autofocus maxlength="20" value="<?= e($_POST['nis'] ?? '') ?>"></div>
      <div class="field"><label for="sandi">Kata sandi</label>
        <div class="pw"><input class="input" id="sandi" name="password" type="password" autocomplete="current-password" required><button type="button" id="lihat" aria-pressed="false">Tampilkan</button></div></div>
      <?php else: ?>
      <div class="field"><label for="sandi">PIN staf</label>
        <div class="pw"><input class="input" id="sandi" name="pin" type="password" inputmode="numeric" autocomplete="current-password" autofocus required placeholder="••••"><button type="button" id="lihat" aria-pressed="false">Tampilkan</button></div></div>
      <?php endif; ?>
      <button class="btn btn-primary btn-lg btn-block" id="go">Masuk</button>
    </form>
    <?php if ($role === 'siswa'): ?><p class="alt">Belum punya akun? <a href="daftar.php">Daftar sebagai siswa</a></p><?php endif; ?>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const f = document.getElementById('f'), p = document.getElementById('sandi'), l = document.getElementById('lihat'), g = document.getElementById('go');
  l.onclick = () => { const s = p.type === 'password'; p.type = s ? 'text' : 'password'; l.textContent = s ? 'Sembunyikan' : 'Tampilkan'; l.setAttribute('aria-pressed', s); };
  f.onsubmit = e => { if ([...f.querySelectorAll('[required]')].some(i => !i.value.trim())) { e.preventDefault(); f.querySelector('[required]:invalid, [required]').focus(); return; } g.classList.add('loading'); g.textContent = 'Memeriksa'; };
});
</script>
<?php foot();
