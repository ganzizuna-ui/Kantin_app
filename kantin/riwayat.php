<?php
require 'config.php';
wajib_siswa();
$sw = siswa();
$list = [];
if ($sw) {
  $s = $pdo->prepare("SELECT id, nomor, total, status FROM pesanan WHERE tanggal = CURDATE() AND nama_pemesan = ? AND COALESCE(kelas,'') = ? ORDER BY id DESC");
  $s->execute([$sw['nama'], (string)$sw['kelas']]);
  $list = $s->fetchAll();
}
head('Pesanan Saya', 'riwayat.php'); ?>
<div class="page-head"><h1>Pesanan Saya</h1><a class="btn btn-primary btn-sm" href="pesan.php"><?= ico('plus') ?>Pesan lagi</a></div>
<p class="muted" style="margin:-12px 0 var(--s4)">Pesanan hari ini.</p>
<?php if (!$sw): ?>
  <div class="card empty"><?= ico('user') ?><span>Halaman ini untuk akun siswa.</span></div>
<?php elseif (!$list): ?>
  <div class="card empty"><?= ico('clipboard') ?><b>Belum ada pesanan hari ini</b><a class="btn btn-primary" href="pesan.php">Pesan sekarang</a></div>
<?php else: foreach ($list as $o): ?>
  <a class="card ord" href="status.php?id=<?= (int)$o['id'] ?>">
    <span class="no">#<?= (int)$o['nomor'] ?></span>
    <span><b><?= rp($o['total']) ?></b><br><span class="muted">Bayar di kantin</span></span>
    <span class="badge st-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'] === 'siap' ? 'siap diambil' : $o['status'])) ?></span>
  </a>
<?php endforeach; endif; ?>
<?php foot();
