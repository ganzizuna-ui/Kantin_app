<?php
require 'config.php';
$q = fn($sql) => $pdo->query($sql)->fetchAll();
$aktif = array_column($q("SELECT status, COUNT(*) n FROM pesanan WHERE tanggal = CURDATE() AND status <> 'selesai' GROUP BY status"), 'n', 'status');
$nAntre = array_sum($aktif);

if (!staf()) {
  $sw = siswa();
  if ($sw) {
    $s = $pdo->prepare("SELECT id, nomor, status FROM pesanan WHERE tanggal = CURDATE() AND nama_pemesan = ? AND COALESCE(kelas,'') = ? AND status <> 'selesai' ORDER BY id DESC LIMIT 5");
    $s->execute([$sw['nama'], (string)$sw['kelas']]);
    $mine = $s->fetchAll();
    head('Beranda', 'index.php'); ?>
    <section class="hero">
      <h1>Halo, <?= e($sw['nama']) ?></h1>
      <p class="muted"><?= $sw['kelas'] ? 'Kelas ' . e($sw['kelas']) . ' · ' : '' ?>Mau makan apa hari ini?</p>
      <div class="actions"><a class="btn btn-primary btn-lg" href="pesan.php"><?= ico('clipboard') ?>Pesan makanan</a><a class="btn btn-lg" href="status.php"><?= ico('list') ?>Papan antrean</a></div>
    </section>
    <h2 class="section-title">Pesanan aktif saya</h2>
    <?php if (!$mine): ?><div class="card empty"><?= ico('clipboard') ?><span>Belum ada pesanan aktif.</span></div>
    <?php else: foreach ($mine as $o): ?>
      <a class="card ord" href="status.php?id=<?= (int)$o['id'] ?>"><span class="no">#<?= (int)$o['nomor'] ?></span><span><b>Lihat detail & posisi antrean</b></span><span class="badge st-<?= e($o['status']) ?>"><?= $o['status'] === 'siap' ? 'Siap diambil' : e(ucfirst($o['status'])) ?></span></a>
    <?php endforeach; endif; ?>
    <p class="alt"><a href="riwayat.php">Semua pesanan saya hari ini</a></p>
  <?php foot(); exit; }
  head('Beranda', 'index.php'); ?>
  <section class="hero">
    <h1>Kantin Bu Rina</h1>
    <p class="muted">Pesan dari tempat dudukmu, pantau nomor antreanmu, ambil saat siap. Masuk sebagai siapa?</p>
  </section>
  <div class="role-grid">
    <a class="card role" href="login.php?sebagai=siswa"><span class="logo"><?= ico('user') ?></span><h2>Saya Siswa</h2><span class="muted">Pesan makanan dan pantau antrean.</span><span class="btn btn-primary">Masuk sebagai siswa</span></a>
    <a class="card role" href="login.php?sebagai=staf"><span class="logo"><?= ico('store') ?></span><h2>Staf Kantin</h2><span class="muted">Kasir, dapur, dan laporan. Perlu PIN.</span><span class="btn">Masuk sebagai staf</span></a>
  </div>
  <p class="alt" style="margin-top:0">Siswa baru? <a href="daftar.php">Daftar akun</a> · <a href="status.php">Lihat papan antrean</a></p>
  <div class="stats three" style="margin-top:var(--s5)">
    <div class="stat"><small><?= ico('clock') ?>Menunggu</small><b><?= (int)($aktif['menunggu'] ?? 0) ?></b></div>
    <div class="stat"><small><?= ico('utensils') ?>Diproses</small><b><?= (int)($aktif['diproses'] ?? 0) ?></b></div>
    <div class="stat"><small><?= ico('check') ?>Siap</small><b><?= (int)($aktif['siap'] ?? 0) ?></b></div>
  </div>
<?php foot(); exit; }

$lap = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM transaksi WHERE DATE(tanggal) = CURDATE()")->fetch();
$rendah = $q("SELECT nama, stok FROM menu WHERE stok <= 5 ORDER BY stok, nama");
$riw = $q("SELECT id, tanggal, total FROM transaksi ORDER BY id DESC LIMIT 5");
head('Dashboard', 'index.php'); ?>
<div class="page-head"><h1>Dashboard</h1><a class="btn btn-primary" href="terpadu.php"><?= ico('cash') ?>Buka kasir</a></div>
<div class="stats">
  <div class="stat"><small><?= ico('cash') ?>Transaksi hari ini</small><b><?= (int)$lap['n'] ?></b></div>
  <div class="stat"><small><?= ico('chart') ?>Pendapatan</small><b><?= rp($lap['t']) ?></b></div>
  <div class="stat"><small><?= ico('list') ?>Antrean aktif</small><b><?= $nAntre ?></b></div>
  <div class="stat"><small><?= ico('alert') ?>Stok menipis</small><b><?= count($rendah) ?></b></div>
</div>
<h2 class="section-title">Pintasan</h2>
<div class="grid">
  <?php foreach ([
    ['terpadu.php', 'cash', 'Kasir', 'Transaksi & keranjang'],
    ['dapur.php', 'utensils', 'Dapur', ($aktif['menunggu'] ?? 0) . ' menunggu · ' . ($aktif['diproses'] ?? 0) . ' diproses'],
    ['status.php', 'list', 'Papan antrean', ($aktif['siap'] ?? 0) . ' siap diambil'],
    ['laporan.php', 'chart', 'Laporan', 'Penjualan harian'],
  ] as [$h, $i, $t, $d]): ?>
    <a class="card shortcut" href="<?= $h ?>"><span class="logo"><?= ico($i) ?></span><span><b><?= $t ?></b><span class="muted"><?= e($d) ?></span></span></a>
  <?php endforeach; ?>
</div>
<div class="two">
  <div>
    <h2 class="section-title">Stok menipis</h2>
    <?php if (!$rendah): ?><div class="card empty"><?= ico('check') ?><span>Semua stok aman.</span></div><?php else: ?>
    <div class="tbl-wrap"><table><tr><th>Menu</th><th class="num">Sisa</th></tr>
      <?php foreach ($rendah as $m): ?><tr><td><?= e($m['nama']) ?></td><td class="num"><?= $m['stok'] > 0 ? '<span class="badge st-menunggu">' . ico('alert') . $m['stok'] . '</span>' : '<span class="badge st-selesai">Habis</span>' ?></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
  </div>
  <div>
    <h2 class="section-title">Transaksi terakhir</h2>
    <?php if (!$riw): ?><div class="card empty"><?= ico('cash') ?><span>Belum ada transaksi.</span></div><?php else: ?>
    <div class="tbl-wrap"><table><tr><th>No</th><th>Waktu</th><th class="num">Total</th></tr>
      <?php foreach ($riw as $r): ?><tr><td>#<?= $r['id'] ?></td><td><?= e($r['tanggal']) ?></td><td class="num"><?= rp($r['total']) ?></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
  </div>
</div>
<?php foot();
