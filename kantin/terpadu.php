<?php
require 'config.php';
wajib_login();
head('Kasir', 'terpadu.php', ['full' => true, 'css' => ['app.css']]); ?>
<div class="pos" id="pos">
  <section class="pos-main">
    <div class="pos-head">
      <h1>Kasir</h1>
      <div class="pos-stats">
        <div><small>Transaksi hari ini</small><b id="sN">0</b></div>
        <div><small>Pendapatan</small><b id="sT">Rp0</b></div>
      </div>
    </div>
    <div class="pos-tools">
      <div class="search"><?= ico('search') ?><input id="cari" class="input" type="search" placeholder="Cari menu…  (tekan /)" aria-label="Cari menu" autocomplete="off"></div>
      <div class="seg" id="filter" role="group" aria-label="Filter stok">
        <button type="button" data-f="semua" aria-pressed="true">Semua</button>
        <button type="button" data-f="tersedia" aria-pressed="false">Tersedia</button>
        <button type="button" data-f="rendah" aria-pressed="false">Menipis</button>
        <button type="button" data-f="habis" aria-pressed="false">Habis</button>
      </div>
    </div>
    <div class="produk" id="produk" aria-live="polite"></div>
  </section>

  <div class="scrim" id="scrim"></div>
  <aside class="panel" id="panel" aria-label="Keranjang dan antrean">
    <div class="ptabs" role="tablist">
      <button type="button" role="tab" data-tab="cart" aria-selected="true"><?= ico('cart') ?>Keranjang <em id="cartCount"></em></button>
      <button type="button" role="tab" data-tab="antre" aria-selected="false"><?= ico('list') ?>Antrean <em id="qCount"></em></button>
      <button type="button" class="sheet-close" id="sheetClose" aria-label="Tutup keranjang"><?= ico('x') ?></button>
    </div>
    <div class="pbody" id="paneCart" role="tabpanel">
      <ul class="clist" id="cartList"></ul>
      <div class="pay">
        <div class="tot"><span>Total</span><b id="total">Rp0</b></div>
        <label class="sw"><input type="checkbox" id="antre" checked> Masukkan ke antrean dapur</label>
        <input id="nama" class="input" placeholder="Nama pemesan (opsional)" maxlength="60" aria-label="Nama pemesan" autocomplete="off">
        <div class="quick" id="quick"></div>
        <input id="bayar" class="input" inputmode="numeric" placeholder="Uang diterima (Rp)  — F2" aria-label="Uang diterima" autocomplete="off">
        <div class="kmb" id="kmb"><span id="kLabel">Kembalian</span><b id="kembali">Rp0</b></div>
        <button type="button" class="btn btn-primary btn-lg btn-block" id="bayarBtn" disabled>Bayar</button>
      </div>
    </div>
    <div class="pbody" id="paneAntre" role="tabpanel" hidden><div class="board" id="kolom"></div></div>
  </aside>

  <button type="button" class="cartbar kosong" id="cartBar"><?= ico('cart') ?><span id="cartInfo">Keranjang kosong</span><b id="cartTotal"></b></button>
</div>
<dialog id="done" aria-labelledby="doneT"></dialog>
<?php foot(['app.js']);
