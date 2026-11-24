<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
const PIN_STAF = '1234'; // ganti PIN untuk dapur & kasir

$pdo = new PDO('mysql:host=localhost;dbname=kantin;charset=utf8mb4', 'root', '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

/* ---------- helper umum ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp($n) { return 'Rp' . number_format((int)$n, 0, ',', '.'); }
function staf() { return !empty($_SESSION['staf']); }
function siswa() { return $_SESSION['siswa'] ?? null; }
function wajib_siswa() {
  if (!siswa()) {
    header('Location: login.php?sebagai=siswa&next=' . urlencode(basename($_SERVER['PHP_SELF'])));
    exit;
  }
}
function csrf() { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function csrf_ok($t) { return is_string($t) && hash_equals(csrf(), $t); }
function asset($f) { return $f . '?v=' . (@filemtime(__DIR__ . '/' . $f) ?: 1); }

// Foto menu: taruh file img/menu/<nama-menu>.jpg|webp|png (contoh: nasi-goreng.jpg). Jika tidak ada, dipakai ilustrasi bawaan.
const GAMBAR_KATA = ['nasi' => 'nasi-goreng', 'mie' => 'mie-ayam', 'bakso' => 'mie-ayam', 'soto' => 'mie-ayam', 'keripik' => 'keripik', 'kripik' => 'keripik', 'teh' => 'es-teh', 'kopi' => 'es-teh', 'jus' => 'es-teh', 'air' => 'air-mineral', 'mineral' => 'air-mineral'];
function gambar_menu($nama) {
  $n = strtolower((string)$nama);
  $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $n), '-');
  foreach (['jpg', 'jpeg', 'webp', 'png', 'svg'] as $x) if ($slug !== '' && is_file(__DIR__ . "/img/menu/$slug.$x")) return "img/menu/$slug.$x";
  foreach (GAMBAR_KATA as $k => $f) if (strpos($n, $k) !== false) return "img/menu/_$f.svg";
  return 'img/menu/_umum.svg';
}

function wajib_login() {
  if (!staf()) {
    header('Location: login.php?sebagai=staf&next=' . urlencode(basename($_SERVER['PHP_SELF'])));
    exit;
  }
}

// Panggil di dalam transaksi DB agar nomor antrean tidak bentrok.
function nomor_berikutnya($pdo) {
  $n = $pdo->query("SELECT nomor FROM pesanan WHERE tanggal = CURDATE() ORDER BY nomor DESC LIMIT 1 FOR UPDATE")->fetchColumn();
  return 1 + (int)$n;
}

/* ---------- ikon (sprite SVG, gaya garis) ---------- */
const IKON = [
  'store' => '<path d="M3 9l1-5h16l1 5"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/><path d="M5 12v8h14v-8"/>',
  'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
  'cash' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
  'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6M9 16h4"/>',
  'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
  'utensils' => '<path d="M3 2v7a3 3 0 0 0 3 3 3 3 0 0 0 3-3V2"/><path d="M6 2v20"/><path d="M17 22V2c-2.5 1.5-4 4.5-4 8v4h4"/>',
  'chart' => '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
  'plus' => '<path d="M12 5v14M5 12h14"/>',
  'minus' => '<path d="M5 12h14"/>',
  'trash' => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 14h10l1-14"/><path d="M10 11v6M14 11v6"/>',
  'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
  'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
  'check' => '<path d="M20 6L9 17l-5-5"/>',
  'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
  'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
  'printer' => '<path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>',
  'cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
  'alert' => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
  'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  'arrow' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
  'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
  'volume' => '<path d="M11 5L6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/>',
  'mute' => '<path d="M11 5L6 9H2v6h4l5 4z"/><path d="M22 9l-6 6M16 9l6 6"/>',
];
function ico($n, $cls = '') { return '<svg class="ic ' . e($cls) . '" aria-hidden="true"><use href="#i-' . e($n) . '"/></svg>'; }
function sprite() {
  $s = '<svg width="0" height="0" style="position:absolute" aria-hidden="true">';
  foreach (IKON as $k => $p) $s .= '<symbol id="i-' . $k . '" viewBox="0 0 24 24">' . $p . '</symbol>';
  return $s . '</svg>';
}

/* ---------- kerangka halaman ---------- */
const NAV = [
  ['index.php', 'Dashboard', 'home'],
  ['terpadu.php', 'Kasir', 'cash'],
  ['status.php', 'Antrean', 'list'],
  ['dapur.php', 'Dapur', 'utensils'],
  ['laporan.php', 'Laporan', 'chart'],
];

function head($judul, $aktif = '', $o = []) {
  $staf = staf();
  echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8">';
  echo '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">';
  echo '<meta name="theme-color" content="#4F5330">';
  if ($staf) echo '<meta name="csrf" content="' . e(csrf()) . '">';
  echo '<title>' . e($judul) . ' · Kantin</title>';
  echo '<script>try{var t=localStorage.getItem("kantin-tema");if(t)document.documentElement.dataset.theme=t}catch(e){}</script>';
  foreach (array_merge(['style.css'], $o['css'] ?? []) as $c) echo '<link rel="stylesheet" href="' . asset($c) . '">';
  echo '</head><body class="' . ($staf ? 'shell' : 'pub') . '">' . sprite();
  $tema = '<button type="button" class="icon-btn" data-theme-toggle aria-label="Ganti tema terang/gelap">' . ico('moon', 'to-dark') . ico('sun', 'to-light') . '</button>';
  $brand = '<a class="brand" href="index.php"><span class="logo">' . ico('store') . '</span><span><b>Kantin</b><small>Bu Rina</small></span></a>';

  if ($staf) {
    echo '<aside class="side">' . $brand . '<nav class="side-nav" aria-label="Menu utama">';
    foreach (NAV as [$f, $l, $i]) echo '<a href="' . $f . '"' . ($aktif === $f ? ' class="on" aria-current="page"' : '') . '>' . ico($i) . '<span>' . $l . '</span></a>';
    echo '</nav><div class="side-foot"><button type="button" class="side-btn" data-theme-toggle>' . ico('moon', 'to-dark') . ico('sun', 'to-light') . '<span>Ganti tema</span></button>'
       . '<form method="post" action="logout.php"><input type="hidden" name="csrf" value="' . e(csrf()) . '"><button class="side-btn">' . ico('logout') . '<span>Keluar</span></button></form></div></aside>';
    echo '<div class="content"><header class="topbar">' . $brand . '<h1 class="topbar-title">' . e($judul) . '</h1>' . $tema
       . '<form method="post" action="logout.php"><input type="hidden" name="csrf" value="' . e(csrf()) . '"><button class="icon-btn" aria-label="Keluar">' . ico('logout') . '</button></form></header>';
    echo '<main class="page' . (!empty($o['full']) ? ' full' : '') . '">';
  } else {
    $s = siswa();
    echo '<header class="pubbar">' . $brand . '<nav aria-label="Menu">';
    $tautan = [['pesan.php', 'Pesan', 'clipboard']];
    if ($s) $tautan[] = ['riwayat.php', 'Pesanan saya', 'clock'];
    $tautan[] = ['status.php', 'Antrean', 'list'];
    foreach ($tautan as [$f, $l, $i]) echo '<a href="' . $f . '"' . ($aktif === $f ? ' class="on" aria-current="page"' : '') . '>' . ico($i) . '<span>' . $l . '</span></a>';
    if ($s) echo '<form method="post" action="logout.php"><input type="hidden" name="csrf" value="' . e(csrf()) . '"><button class="btn btn-sm" aria-label="Keluar (' . e($s['nama']) . ')">' . ico('logout') . '<span>Keluar</span></button></form>';
    else echo '<a class="btn btn-sm" href="login.php" aria-label="Masuk">' . ico('user') . '<span>Masuk</span></a>';
    echo $tema . '</nav></header><main class="page narrow">';
  }
}

function foot($js = []) {
  if (staf()) {
    echo '</main></div><nav class="bnav" aria-label="Menu utama">';
    foreach (NAV as [$f, $l, $i]) if ($f !== 'pesan.php') echo '<a href="' . $f . '"' . (basename($_SERVER['PHP_SELF']) === $f ? ' class="on" aria-current="page"' : '') . '>' . ico($i) . '<span>' . $l . '</span></a>';
    echo '</nav>';
  } else {
    echo '</main><footer class="pubfoot">Kantin Bu Rina</footer>';
  }
  echo '<div class="toast" id="toast" role="status" aria-live="polite"></div>';
  foreach (array_merge(['ui.js'], $js) as $s) echo '<script src="' . asset($s) . '"></script>';
  echo '</body></html>';
}

// Pesanan boleh dilihat staf, atau siswa pemiliknya (dibuat di sesi ini / atas nama + kelas yang sama).
function pesanan_milik($p) {
  if (staf()) return true;
  $s = siswa();
  if (!$s || !$p) return false;
  return in_array((int)$p['id'], $_SESSION['pesanan_saya'] ?? [], true)
    || ($p['nama_pemesan'] === $s['nama'] && (string)$p['kelas'] === (string)$s['kelas']);
}
