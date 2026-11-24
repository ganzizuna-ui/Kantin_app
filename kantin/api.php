<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($d, $c = 200) { http_response_code($c); echo json_encode($d); exit; }

$aksi = $_GET['aksi'] ?? '';
$post = $_SERVER['REQUEST_METHOD'] === 'POST';

try {
  /* ---- publik: hanya nomor & status (tanpa nama/total) untuk papan antrean ---- */
  if ($aksi === 'publik') {
    $list = $pdo->query("SELECT nomor, status FROM pesanan WHERE tanggal = CURDATE() AND status <> 'selesai' ORDER BY id")->fetchAll();
    $res = ['antrean' => $list];
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
      $s = $pdo->prepare("SELECT status FROM pesanan WHERE id = ?");
      $s->execute([$id]);
      $st = $s->fetchColumn();
      if ($st) {
        $d = $pdo->prepare("SELECT COUNT(*) FROM pesanan WHERE tanggal = CURDATE() AND status IN ('menunggu','diproses') AND id < ?");
        $d->execute([$id]);
        $res['pesanan'] = ['status' => $st, 'depan' => (int)$d->fetchColumn()];
      }
    }
    out($res);
  }

  /* ---- selebihnya khusus staf ---- */
  if (!staf()) out(['error' => 'Belum login'], 401);
  $in = json_decode(file_get_contents('php://input'), true);
  if (!is_array($in)) $in = [];
  if ($post && !csrf_ok($_SERVER['HTTP_X_CSRF'] ?? '')) out(['error' => 'Sesi tidak valid. Muat ulang halaman.'], 403);

  if ($aksi === 'data') {
    $menu = $pdo->query("SELECT id, nama, harga, stok FROM menu ORDER BY nama")->fetchAll();
    foreach ($menu as $i => $m) $menu[$i]['gambar'] = gambar_menu($m['nama']);
    $ps = $pdo->query("SELECT id, nomor, nama_pemesan, total, status, TIMESTAMPDIFF(MINUTE, dibuat, NOW()) AS menit FROM pesanan WHERE tanggal = CURDATE() AND status <> 'selesai' ORDER BY id")->fetchAll();
    $idx = [];
    foreach ($ps as $i => $p) { $ps[$i]['items'] = []; $idx[$p['id']] = $i; }
    $rows = $pdo->query("SELECT d.pesanan_id, m.nama, d.jumlah FROM detail_pesanan d JOIN menu m ON m.id = d.menu_id JOIN pesanan p ON p.id = d.pesanan_id WHERE p.tanggal = CURDATE() AND p.status <> 'selesai' ORDER BY d.id")->fetchAll();
    foreach ($rows as $r) $ps[$idx[$r['pesanan_id']]]['items'][] = $r['jumlah'] . '× ' . $r['nama'];
    $lap = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM transaksi WHERE DATE(tanggal) = CURDATE()")->fetch();
    out(['menu' => $menu, 'antrean' => $ps, 'laporan' => $lap]);
  }

  if ($aksi === 'status' && $post) {
    $alur = ['menunggu' => 'diproses', 'diproses' => 'siap', 'siap' => 'selesai'];
    $id = (int)($in['id'] ?? 0);
    $dari = $in['dari'] ?? null; // status yang dilihat pengguna; mencegah lompat tahap
    $s = $pdo->prepare("SELECT status FROM pesanan WHERE id = ?");
    $s->execute([$id]);
    $st = $s->fetchColumn();
    if (!isset($alur[$st])) out(['error' => 'Pesanan tidak ditemukan atau sudah selesai.'], 404);
    if ($dari !== null && $dari !== $st) out(['error' => 'Status pesanan sudah berubah. Daftar diperbarui.'], 409);
    $pdo->prepare("UPDATE pesanan SET status = ? WHERE id = ? AND status = ?")->execute([$alur[$st], $id, $st]);
    out(['ok' => true, 'status' => $alur[$st]]);
  }

  if ($aksi === 'transaksi' && $post) {
    $items = is_array($in['items'] ?? null) ? $in['items'] : [];
    $qty = [];
    foreach ($items as $id => $n) {
      $id = (int)$id; $n = (int)$n;
      if ($id > 0 && $n > 0) $qty[$id] = min($n, 999);
    }
    ksort($qty); // urutan lock konsisten → mengurangi deadlock
    $bayar = (int)($in['bayar'] ?? 0);
    if (!$qty) out(['error' => 'Keranjang kosong.'], 422);

    $pdo->beginTransaction();
    try {
      $by = []; $total = 0;
      foreach ($qty as $id => $n) {
        $s = $pdo->prepare("SELECT * FROM menu WHERE id = ? FOR UPDATE");
        $s->execute([$id]);
        $m = $s->fetch();
        if (!$m) throw new Exception('Menu tidak ditemukan.');
        if ($m['stok'] < $n) throw new Exception('Stok ' . $m['nama'] . ' tidak cukup (sisa ' . $m['stok'] . ').');
        $by[$id] = $m;
        $total += $m['harga'] * $n;
      }
      if ($bayar < $total) throw new Exception('Uang diterima kurang dari total.');

      $pdo->prepare("INSERT INTO transaksi (total, bayar, kembali) VALUES (?,?,?)")->execute([$total, $bayar, $bayar - $total]);
      $tid = $pdo->lastInsertId();

      $nomor = null; $pid = null;
      if (!empty($in['antre'])) {
        $nomor = nomor_berikutnya($pdo);
        $nama = mb_substr(trim((string)($in['nama'] ?? '')), 0, 60) ?: 'Pelanggan';
        $pdo->prepare("INSERT INTO pesanan (nomor, tanggal, nama_pemesan, total) VALUES (?, CURDATE(), ?, ?)")->execute([$nomor, $nama, $total]);
        $pid = $pdo->lastInsertId();
      }

      foreach ($qty as $id => $n) {
        $sub = $by[$id]['harga'] * $n;
        $pdo->prepare("INSERT INTO detail_transaksi (transaksi_id, menu_id, jumlah, subtotal) VALUES (?,?,?,?)")->execute([$tid, $id, $n, $sub]);
        if ($pid) $pdo->prepare("INSERT INTO detail_pesanan (pesanan_id, menu_id, jumlah, subtotal) VALUES (?,?,?,?)")->execute([$pid, $id, $n, $sub]);
        $pdo->prepare("UPDATE menu SET stok = stok - ? WHERE id = ?")->execute([$n, $id]);
      }
      $pdo->commit();
      out(['ok' => true, 'id' => (int)$tid, 'total' => $total, 'kembali' => $bayar - $total, 'nomor' => $nomor]);
    } catch (PDOException $x) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $x; // ditangani di bawah: pesan DB tidak dikirim ke klien
    } catch (Exception $x) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      out(['error' => $x->getMessage()], 422);
    }
  }

  out(['error' => 'Aksi tidak dikenal.'], 400);
} catch (Throwable $t) {
  error_log('[kantin api] ' . $t->getMessage());
  out(['error' => 'Terjadi kesalahan pada server.'], 500);
}
