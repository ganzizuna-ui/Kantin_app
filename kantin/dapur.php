<?php
require 'config.php';
wajib_login();
head('Dapur', 'dapur.php', ['full' => true, 'css' => []]); ?>
<div style="padding:var(--s5)" class="dapur">
  <div class="page-head">
    <h1>Dapur</h1>
    <span class="live" id="live"><i></i><span id="liveT">Menyambung…</span></span>
    <button type="button" class="btn btn-sm" id="bunyi" aria-pressed="true"><?= ico('volume') ?><span>Bunyi aktif</span></button>
  </div>
  <div class="board" id="kolom"></div>
</div>
<noscript><p class="alert err">Aktifkan JavaScript untuk memakai halaman dapur.</p></noscript>
<?php foot(['dapur.js']);
