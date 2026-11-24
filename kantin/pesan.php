<?php
require 'config.php';

if (!siswa() && !staf()) {
    header('Location: login.php?sebagai=siswa&next=pesan.php');
    exit;
}

$sw = siswa();
$menu = $pdo->query("SELECT * FROM menu WHERE stok > 0 ORDER BY nama")->fetchAll();
$err = '';
$qtyIn = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama  = $sw ? $sw['nama'] : mb_substr(trim($_POST['nama'] ?? ''), 0, 60);
  $kelas = $sw ? (string)$sw['kelas'] : mb_substr(trim($_POST['kelas'] ?? ''), 0, 20);
  $qty = [];
  foreach ((is_array($_POST['qty'] ?? null) ? $_POST['qty'] : []) as $mid => $q) {
    $mid = (int)$mid; $q = (int)$q;
    if ($mid > 0 && $q > 0) $qty[$mid] = min($q, 99);
  }
  ksort($qty);
  $qtyIn = $qty;

  if (!csrf_ok($_POST['csrf'] ?? '')) {
    $err = 'Sesi habis. Muat ulang halaman lalu coba lagi.';
  } elseif ($nama === '' || !$qty) {
    $err = 'Isi nama dan pilih minimal satu menu.';
  } else {
    $pdo->beginTransaction();
    try {
      $total = 0; $baris = [];
      foreach ($qty as $mid => $q) {
        $st = $pdo->prepare("SELECT * FROM menu WHERE id = ? FOR UPDATE");
        $st->execute([$mid]);
        $m = $st->fetch();
        if (!$m || $m['stok'] < $q) throw new Exception('Stok ' . ($m['nama'] ?? 'menu') . ' tidak cukup. Kurangi jumlahnya.');
        $baris[] = [$mid, $q, $m['harga'] * $q];
        $total += $m['harga'] * $q;
      }
      $nomor = nomor_berikutnya($pdo);
      $pdo->prepare("INSERT INTO pesanan (nomor, tanggal, nama_pemesan, kelas, total) VALUES (?, CURDATE(), ?, ?, ?)")->execute([$nomor, $nama, $kelas, $total]);
      $id = $pdo->lastInsertId();
      foreach ($baris as [$mid, $q, $sub]) {
        $pdo->prepare("INSERT INTO detail_pesanan (pesanan_id, menu_id, jumlah, subtotal) VALUES (?,?,?,?)")->execute([$id, $mid, $q, $sub]);
        $pdo->prepare("UPDATE menu SET stok = stok - ? WHERE id = ?")->execute([$q, $mid]);
      }
      $pdo->commit();
      $_SESSION['pesanan_saya'] = array_slice(array_merge($_SESSION['pesanan_saya'] ?? [], [(int)$id]), -20);
      header("Location: status.php?id=$id");
      exit;
    } catch (PDOException $x) {
      $pdo->rollBack(); error_log('[kantin pesan] ' . $x->getMessage());
      $err = 'Terjadi kesalahan pada server. Coba lagi.';
    } catch (Exception $x) {
      $pdo->rollBack();
      $err = $x->getMessage();
    }
  }
}
head('Pesan Makanan', 'pesan.php'); ?>
<div class="page-head"><h1>Pesan Makanan</h1></div>
<?php if ($err) echo '<div class="alert err" role="alert">' . ico('alert') . '<span>' . e($err) . '</span></div>'; ?>
<div class="alert err" id="clientErr" role="alert" hidden><?= ico('alert') ?><span></span></div>
<form method="post" id="f" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
  <?php if ($sw): ?>
  <div class="card who-card"><span class="logo"><?= ico('user') ?></span><div><small class="muted">Memesan sebagai</small><br><b><?= e($sw['nama']) ?></b><?= $sw['kelas'] ? ' · ' . e($sw['kelas']) : '' ?></div></div>
  <?php else: ?>
  <div class="two">
    <div class="field"><label for="nama">Nama</label><input class="input" id="nama" name="nama" required maxlength="60" autocomplete="name" value="<?= e($_POST['nama'] ?? '') ?>"></div>
    <div class="field"><label for="kelas">Kelas <span class="muted">(opsional)</span></label><input class="input" id="kelas" name="kelas" maxlength="20" placeholder="contoh: XII RPL" value="<?= e($_POST['kelas'] ?? '') ?>"></div>
  </div>
  <?php endif; ?>
  <h2 class="section-title" style="margin-top:0">Pilih menu</h2>
  <?php if (!$menu): ?><div class="card empty"><?= ico('alert') ?><b>Semua menu sedang habis</b><span>Silakan coba lagi nanti.</span></div><?php endif; ?>
  <div class="menu-list">
    <?php foreach ($menu as $m): $n = $qtyIn[$m['id']] ?? 0; ?>
    <div class="menu-row<?= $n ? ' on' : '' ?>" data-harga="<?= (int)$m['harga'] ?>">
      <img class="thumb-sm" src="<?= e(gambar_menu($m['nama'])) ?>" alt="" loading="lazy" width="72" height="72">
      <div><b><?= e($m['nama']) ?></b><small><?= rp($m['harga']) ?> · stok <?= (int)$m['stok'] ?></small></div>
      <div class="stepper">
        <button type="button" data-d="-1" aria-label="Kurangi <?= e($m['nama']) ?>"><?= ico('minus') ?></button>
        <input type="number" name="qty[<?= (int)$m['id'] ?>]" min="0" max="<?= (int)$m['stok'] ?>" value="<?= $n ?>" aria-label="Jumlah <?= e($m['nama']) ?>" inputmode="numeric">
        <button type="button" data-d="1" aria-label="Tambah <?= e($m['nama']) ?>"><?= ico('plus') ?></button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="sticky-total">
    <div><small id="cnt">0 item</small><b id="tot">Rp0</b></div>
    <button class="btn btn-primary btn-lg" id="kirim"<?= $menu ? '' : ' disabled' ?>>Kirim pesanan</button>
  </div>
  <p class="hint" style="margin-top:var(--s3)">Pembayaran dilakukan di kantin saat mengambil pesanan.</p>
</form>
<?php foot(['pesan.js']);
