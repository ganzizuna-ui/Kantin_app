<?php
require 'config.php';
$id = (int)($_GET['id'] ?? 0);
$p = null;
if ($id) {
  $st = $pdo->prepare("SELECT * FROM pesanan WHERE id = ?");
  $st->execute([$id]);
  $p = $st->fetch();
  if ($p && !pesanan_milik($p)) $p = null; // detail hanya untuk pemilik / staf
}
head('Antrean', 'status.php');

if ($id && $p) {
  $d = $pdo->prepare("SELECT m.nama, d.jumlah, d.subtotal FROM detail_pesanan d JOIN menu m ON m.id = d.menu_id WHERE d.pesanan_id = ?");
  $d->execute([$id]);
  $alur = ['menunggu', 'diproses', 'siap', 'selesai'];
  $now = array_search($p['status'], $alur); ?>
  <section class="card" id="trk" data-id="<?= $id ?>" style="margin-bottom:var(--s5)">
    <p class="muted" style="text-align:center">Nomor antrean kamu</p>
    <div class="nomor">#<?= (int)$p['nomor'] ?></div>
    <p style="text-align:center">Atas nama <b><?= e($p['nama_pemesan']) ?></b></p>
    <ol class="track" aria-label="Tahap pesanan">
      <?php foreach ($alur as $i => $s): ?>
        <li data-s="<?= $s ?>" class="<?= $i < $now ? 'done' : ($i === $now ? 'now' : '') ?>"><span><?= ico($i < $now ? 'check' : [ 'clock', 'utensils', 'check', 'check'][$i]) ?></span><?= $s === 'siap' ? 'Siap diambil' : ucfirst($s) ?></li>
      <?php endforeach; ?>
    </ol>
    <div id="info" aria-live="polite"></div>
    <div class="tbl-wrap"><table>
      <tr><th>Menu</th><th class="num">Jumlah</th><th class="num">Subtotal</th></tr>
      <?php foreach ($d as $r): ?><tr><td><?= e($r['nama']) ?></td><td class="num"><?= (int)$r['jumlah'] ?></td><td class="num"><?= rp($r['subtotal']) ?></td></tr><?php endforeach; ?>
      <tr><th colspan="2">Total (bayar di kantin)</th><th class="num"><?= rp($p['total']) ?></th></tr>
    </table></div>
  </section>
<?php } elseif ($id) echo '<div class="alert err" role="alert">' . ico('alert') . '<span>Pesanan tidak ditemukan.</span></div>'; ?>
<div class="page-head"><h1>Papan Antrean</h1><span class="live" id="live"><i></i><span id="liveT">Diperbarui otomatis</span></span></div>
<div class="papan"><div class="board" id="papan"><?php for ($i = 0; $i < 3; $i++) echo '<div class="skeleton" style="height:120px"></div>'; ?></div></div>
<noscript><p class="alert err">Aktifkan JavaScript agar papan antrean diperbarui otomatis.</p></noscript>
<?php foot(['status.js']);
