<?php
require 'config.php';
wajib_login();
$tgl = $_GET['tanggal'] ?? date('Y-m-d');
$dt = DateTime::createFromFormat('Y-m-d', $tgl);
if (!$dt || $dt->format('Y-m-d') !== $tgl) { $tgl = date('Y-m-d'); $dt = new DateTime($tgl); }
$prev = (clone $dt)->modify('-1 day')->format('Y-m-d');
$next = (clone $dt)->modify('+1 day')->format('Y-m-d');
$hariIni = $tgl === date('Y-m-d');

$s = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM transaksi WHERE DATE(tanggal) = ?");
$s->execute([$tgl]); $lap = $s->fetch();
$s = $pdo->prepare("SELECT COALESCE(SUM(d.jumlah),0) FROM detail_transaksi d JOIN transaksi t ON t.id = d.transaksi_id WHERE DATE(t.tanggal) = ?");
$s->execute([$tgl]); $item = (int)$s->fetchColumn();
$s = $pdo->prepare("SELECT m.nama, SUM(d.jumlah) j, SUM(d.subtotal) s FROM detail_transaksi d JOIN transaksi t ON t.id = d.transaksi_id JOIN menu m ON m.id = d.menu_id WHERE DATE(t.tanggal) = ? GROUP BY m.id, m.nama ORDER BY j DESC, m.nama LIMIT 10");
$s->execute([$tgl]); $top = $s->fetchAll();
$s = $pdo->prepare("SELECT * FROM transaksi WHERE DATE(tanggal) = ? ORDER BY id DESC LIMIT 100");
$s->execute([$tgl]); $riw = $s->fetchAll();
$s = $pdo->prepare("SELECT DATE(tanggal) d, SUM(total) t FROM transaksi WHERE DATE(tanggal) BETWEEN DATE_SUB(?, INTERVAL 6 DAY) AND ? GROUP BY DATE(tanggal)");
$s->execute([$tgl, $tgl]);
$per = array_column($s->fetchAll(), 't', 'd');
$hari = []; for ($i = 6; $i >= 0; $i--) { $d = (clone $dt)->modify("-$i day")->format('Y-m-d'); $hari[$d] = (int)($per[$d] ?? 0); }
$maks = max($hari) ?: 1; $maksJ = $top ? max(array_column($top, 'j')) : 1;
$avg = $lap['n'] ? intdiv((int)$lap['t'], (int)$lap['n']) : 0;

head('Laporan', 'laporan.php'); ?>
<div class="page-head">
  <h1>Laporan Penjualan</h1>
  <a class="btn btn-sm noprint" href="?tanggal=<?= $prev ?>" aria-label="Hari sebelumnya">‹</a>
  <form method="get" class="noprint"><input class="input" type="date" name="tanggal" value="<?= e($tgl) ?>" max="<?= date('Y-m-d') ?>" aria-label="Tanggal laporan" onchange="this.form.submit()" style="width:auto"></form>
  <?php if (!$hariIni): ?><a class="btn btn-sm noprint" href="?tanggal=<?= $next ?>" aria-label="Hari berikutnya">›</a><?php endif; ?>
  <button type="button" class="btn btn-sm noprint" onclick="print()"><?= ico('printer') ?>Cetak</button>
</div>
<p class="muted" style="margin:-12px 0 var(--s4)"><?= e($dt->format('d-m-Y')) ?><?= $hariIni ? ' (hari ini)' : '' ?></p>
<div class="stats">
  <div class="stat"><small>Transaksi</small><b><?= (int)$lap['n'] ?></b></div>
  <div class="stat"><small>Pendapatan</small><b><?= rp($lap['t']) ?></b></div>
  <div class="stat"><small>Item terjual</small><b><?= $item ?></b></div>
  <div class="stat"><small>Rata-rata / transaksi</small><b><?= rp($avg) ?></b></div>
</div>
<div class="card" style="margin-bottom:var(--s4)">
  <h2>7 hari terakhir</h2>
  <div class="bars" role="img" aria-label="Grafik pendapatan 7 hari terakhir">
    <?php foreach ($hari as $d => $t): ?><div class="bar<?= $d === $tgl ? ' on' : '' ?>"><i style="height:<?= max(2, round($t / $maks * 100)) ?>%"></i><b><?= $t ? number_format($t / 1000, $t % 1000 ? 1 : 0, ',', '.') . 'k' : '0' ?></b><span><?= substr($d, 8, 2) ?>/<?= substr($d, 5, 2) ?></span></div><?php endforeach; ?>
  </div>
</div>
<div class="two">
  <div>
    <h2 class="section-title" style="margin-top:0">Menu terlaris</h2>
    <?php if (!$top): ?><div class="card empty"><?= ico('chart') ?><span>Belum ada penjualan.</span></div><?php else: ?>
    <ul class="rank card"><?php foreach ($top as $t): ?><li><span><b><?= e($t['nama']) ?></b></span><span><?= (int)$t['j'] ?> terjual · <?= rp($t['s']) ?></span><div class="meter"><i style="width:<?= round($t['j'] / $maksJ * 100) ?>%"></i></div></li><?php endforeach; ?></ul><?php endif; ?>
  </div>
  <div>
    <h2 class="section-title" style="margin-top:0">Transaksi</h2>
    <?php if (!$riw): ?><div class="card empty"><?= ico('cash') ?><span>Belum ada transaksi.</span></div><?php else: ?>
    <div class="tbl-wrap"><table><tr><th>No</th><th>Jam</th><th class="num">Total</th><th class="num">Bayar</th><th class="num">Kembali</th></tr>
      <?php foreach ($riw as $r): ?><tr><td>#<?= $r['id'] ?></td><td><?= e(substr($r['tanggal'], 11, 5)) ?></td><td class="num"><?= rp($r['total']) ?></td><td class="num"><?= rp($r['bayar']) ?></td><td class="num"><?= rp($r['kembali']) ?></td></tr><?php endforeach; ?>
    </table></div><?php endif; ?>
  </div>
</div>
<p class="hint" style="margin-top:var(--s3)">Pendapatan dihitung dari transaksi yang diproses di kasir.</p>
<?php foot();
