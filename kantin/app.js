/* Kasir (POS). Memakai helper dari ui.js. */
const S = { menu: [], cart: {}, antrean: [], lap: { n: 0, t: 0 }, f: 'semua', paying: false, sig: '', err: false };
const LOW = 5, QUICK = [5000, 10000, 20000, 50000, 100000];
const byId = id => S.menu.find(m => m.id == id);
const total = () => Object.entries(S.cart).reduce((s, [id, n]) => s + n * Number((byId(id) || {}).harga || 0), 0);
const count = () => Object.values(S.cart).reduce((s, n) => s + n, 0);
const uang = () => Number($('#bayar').value.replace(/\D/g, '')) || 0;
const pos = $('#pos');

const saveCart = () => { try { sessionStorage.setItem('kantin-cart', JSON.stringify(S.cart)); } catch (_) { } };
try { S.cart = JSON.parse(sessionStorage.getItem('kantin-cart')) || {}; } catch (_) { S.cart = {}; }

/* ---------- data ---------- */
let seq = 0;
async function load() {
  if (S.paying) return;
  const my = ++seq;
  try {
    const d = await api('data');
    if (!d || my !== seq) return;
    if (S.err) { S.err = false; toast('Tersambung kembali'); }
    S.antrean = d.antrean; S.lap = d.laporan;
    const sig = JSON.stringify(d.menu);
    if (sig !== S.sig) {
      S.sig = sig; S.menu = d.menu;
      const fix = [];
      for (const id in S.cart) {
        const m = byId(id), max = m ? Number(m.stok) : 0;
        if (S.cart[id] > max) { fix.push(m ? m.nama : 'menu'); max > 0 ? S.cart[id] = max : delete S.cart[id]; }
      }
      if (fix.length) { toast('Keranjang disesuaikan dengan stok: ' + fix.join(', '), true); saveCart(); }
      renderMenu(); renderCart();
    }
    board.set(S.antrean);
    $('#qCount').textContent = S.antrean.length || '';
    $('#sN').textContent = S.lap.n; $('#sT').textContent = rp(S.lap.t);
  } catch (e) {
    if (!S.err) toast(e.message, true);
    S.err = true;
    if (!S.menu.length) $('#produk').innerHTML = `<div class="empty" style="grid-column:1/-1">${ico('alert')}<b>Gagal memuat menu</b><span>${esc(e.message)}</span><button class="btn btn-sm" onclick="load()">Coba lagi</button></div>`;
  }
}

/* ---------- produk ---------- */
function cardHTML(m) {
  const n = S.cart[m.id] || 0, stok = Number(m.stok), habis = stok <= 0, low = !habis && stok <= LOW;
  return `<button type="button" class="item${habis ? ' habis' : ''}${n ? ' pilih' : ''}" data-id="${m.id}" ${habis ? 'disabled' : ''} aria-label="${esc(m.nama)}, ${rp(m.harga)}, ${habis ? 'habis' : 'stok ' + stok}${n ? ', di keranjang ' + n : ''}">
    ${n ? `<span class="qty">${n}</span>` : ''}<img class="thumb" src="${esc(m.gambar || 'img/menu/_umum.svg')}" alt="" loading="lazy" width="160" height="120"><b>${esc(m.nama)}</b><span class="hrg">${rp(m.harga)}</span>
    <span class="stk${low ? ' low' : ''}">${habis ? ico('x') + 'Habis' : (low ? ico('alert') + 'Sisa ' : 'Stok ') + stok}</span></button>`;
}
function renderMenu() {
  const q = $('#cari').value.trim().toLowerCase();
  const l = S.menu.filter(m => {
    const s = Number(m.stok);
    return m.nama.toLowerCase().includes(q) && (S.f === 'semua' || (S.f === 'tersedia' && s > 0) || (S.f === 'rendah' && s > 0 && s <= LOW) || (S.f === 'habis' && s <= 0));
  });
  $('#produk').innerHTML = l.map(cardHTML).join('') || `<div class="empty" style="grid-column:1/-1">${ico('search')}<b>Menu tidak ditemukan</b><span>Ubah kata kunci atau filter.</span></div>`;
}
function updateCard(id, flash) {
  const el = $(`#produk .item[data-id="${id}"]`); if (!el) return;
  const focus = document.activeElement === el; el.outerHTML = cardHTML(byId(id));
  const n = $(`#produk .item[data-id="${id}"]`); if (focus) n.focus();
  if (flash) { n.classList.add('added'); setTimeout(() => n.classList.remove('added'), 400); }
}

/* ---------- keranjang ---------- */
function ubah(id, d) {
  const m = byId(id); if (!m) return;
  const n = (S.cart[id] || 0) + d;
  if (n > Number(m.stok)) return toast(`Stok ${m.nama} hanya ${m.stok}`, true);
  n <= 0 ? delete S.cart[id] : S.cart[id] = n;
  saveCart(); updateCard(id, d > 0); renderCart(); bump(id);
}
function bump(id) { const q = $(`#cartList li[data-id="${id}"] .q`); if (q) q.classList.add('bump'); }
function renderCart() {
  const ids = Object.keys(S.cart), t = total(), cnt = count();
  const fa = document.activeElement, key = fa && fa.closest('#cartList') ? fa.dataset.id + '|' + fa.dataset.act : null;
  $('#cartList').innerHTML = ids.length ? ids.map(id => {
    const m = byId(id);
    return `<li data-id="${id}"><div class="nm"><b>${esc(m.nama)}</b><small>${rp(m.harga)}</small></div><div class="sub">${rp(m.harga * S.cart[id])}</div>
      <div class="row2"><div class="stepper"><button type="button" data-id="${id}" data-act="dec" aria-label="Kurangi ${esc(m.nama)}">${ico('minus')}</button><span class="q" aria-live="polite">${S.cart[id]}</span><button type="button" data-id="${id}" data-act="inc" aria-label="Tambah ${esc(m.nama)}">${ico('plus')}</button></div>
      <button type="button" class="del" data-id="${id}" data-act="del" aria-label="Hapus ${esc(m.nama)}">${ico('trash')}</button></div></li>`;
  }).join('') : `<li class="kosong">${ico('cart')}<b>Keranjang kosong</b><span>Ketuk menu untuk menambah pesanan.</span></li>`;
  if (key) { const [i, a] = key.split('|'); const el = $(`#cartList [data-id="${i}"][data-act="${a}"]`) || $('#cartList [data-act]'); if (el) el.focus(); }
  $('#total').textContent = rp(t);
  $('#cartCount').textContent = cnt || '';
  $('#cartInfo').textContent = cnt ? `${cnt} item` : 'Keranjang kosong';
  $('#cartTotal').textContent = cnt ? rp(t) : '';
  $('#cartBar').classList.toggle('kosong', !cnt);
  const opts = t ? [t, ...QUICK.filter(v => v > t)].slice(0, 4) : [];
  $('#quick').innerHTML = opts.map((v, i) => `<button type="button" data-v="${v}">${i === 0 ? 'Uang pas' : rp(v)}</button>`).join('');
  renderPay();
}
function renderPay() {
  const t = total(), b = uang(), k = b - t;
  const kmb = $('#kmb'); kmb.className = 'kmb' + (b && t ? (k < 0 ? ' kurang' : ' cukup') : '');
  $('#kLabel').textContent = b && k < 0 ? 'Uang kurang' : 'Kembalian';
  $('#kembali').textContent = rp(b && t ? Math.abs(k) : 0);
  $('#bayarBtn').disabled = S.paying || !(t > 0 && b >= t);
}

/* ---------- pembayaran ---------- */
async function bayar() {
  const t = total(), b = uang();
  if (S.paying || !(t > 0 && b >= t)) return;
  const snap = Object.entries(S.cart).map(([id, n]) => ({ nama: byId(id).nama, harga: Number(byId(id).harga), n }));
  const btn = $('#bayarBtn'), nama = $('#nama').value.trim();
  S.paying = true; btn.disabled = true; btn.classList.add('loading'); btn.textContent = 'Memproses';
  try {
    const r = await api('transaksi', { items: S.cart, bayar: b, antre: $('#antre').checked, nama });
    S.cart = {}; saveCart(); $('#bayar').value = ''; $('#nama').value = ''; S.sig = '';
    closeSheet(); showDone(r, snap, b, nama);
  } catch (e) { toast(e.message, true); S.sig = ''; }
  S.paying = false; btn.classList.remove('loading'); btn.textContent = 'Bayar';
  await load(); renderPay();
}
function showDone(r, snap, b, nama) {
  const d = $('#done');
  d.innerHTML = `<div class="done"><div class="okc">${ico('check')}</div><h2 id="doneT">Transaksi berhasil</h2>
    ${r.nomor ? `<div><span class="muted">Nomor antrean</span><div class="nomor">#${r.nomor}</div></div>` : ''}
    <dl><dt>Total</dt><dd>${rp(r.total)}</dd><dt>Dibayar</dt><dd>${rp(b)}</dd><dt>Kembalian</dt><dd class="big-k">${rp(r.kembali)}</dd></dl>
    <div class="acts"><button type="button" class="btn btn-primary btn-lg" id="newTx" autofocus>Transaksi baru</button><button type="button" class="btn" id="prn">${ico('printer')}Cetak struk</button></div></div>`;
  $('#newTx').onclick = () => { d.close(); $('#cari').focus(); };
  $('#prn').onclick = () => cetak(r, snap, b, nama);
  d.showModal();
}
function cetak(r, snap, b, nama) {
  const w = window.open('', '_blank', 'width=360,height=600');
  if (!w) return toast('Pop-up diblokir. Izinkan pop-up untuk mencetak.', true);
  w.document.write(`<!DOCTYPE html><meta charset="utf-8"><title>Struk #${r.id}</title><style>body{font:13px/1.4 monospace;width:280px;margin:8px auto}h3,p{text-align:center;margin:2px}hr{border:0;border-top:1px dashed #000}table{width:100%}td:last-child{text-align:right}</style>
    <h3>KANTIN BU RINA</h3><p>${new Date().toLocaleString('id-ID')}</p><p>Transaksi #${r.id}${r.nomor ? ' · Antrean #' + r.nomor : ''}${nama ? '<br>' + esc(nama) : ''}</p><hr>
    <table>${snap.map(i => `<tr><td colspan="2">${esc(i.nama)}</td></tr><tr><td>${i.n} × ${rp(i.harga)}</td><td>${rp(i.n * i.harga)}</td></tr>`).join('')}</table><hr>
    <table><tr><td>Total</td><td>${rp(r.total)}</td></tr><tr><td>Bayar</td><td>${rp(b)}</td></tr><tr><td>Kembali</td><td>${rp(r.kembali)}</td></tr></table><hr><p>Terima kasih!</p>`);
  w.document.close(); w.focus(); w.print();
}

/* ---------- panel: tab & sheet mobile ---------- */
function setTab(t) {
  document.querySelectorAll('.ptabs [data-tab]').forEach(b => b.setAttribute('aria-selected', b.dataset.tab === t));
  $('#paneCart').hidden = t !== 'cart'; $('#paneAntre').hidden = t !== 'antre';
}
const openSheet = () => pos.classList.add('open'), closeSheet = () => pos.classList.remove('open');

/* ---------- event (didaftarkan sekali) ---------- */
const board = makeBoard($('#kolom'), { reload: () => load() });
$('#produk').addEventListener('click', e => { const b = e.target.closest('.item'); if (b) ubah(b.dataset.id, 1); });
$('#cartList').addEventListener('click', e => {
  const b = e.target.closest('button[data-act]'); if (!b) return;
  const id = b.dataset.id; b.dataset.act === 'del' ? ubah(id, -S.cart[id]) : ubah(id, b.dataset.act === 'inc' ? 1 : -1);
});
$('#quick').addEventListener('click', e => { const b = e.target.closest('button'); if (b) { $('#bayar').value = Number(b.dataset.v).toLocaleString('id-ID'); renderPay(); } });
$('#filter').addEventListener('click', e => {
  const b = e.target.closest('button'); if (!b) return;
  S.f = b.dataset.f; document.querySelectorAll('#filter button').forEach(x => x.setAttribute('aria-pressed', x === b)); renderMenu();
});
$('#cari').addEventListener('input', renderMenu);
$('#bayar').addEventListener('input', e => { const d = e.target.value.replace(/\D/g, '').slice(0, 10); e.target.value = d ? Number(d).toLocaleString('id-ID') : ''; renderPay(); });
$('#bayar').addEventListener('keydown', e => { if (e.key === 'Enter') bayar(); });
$('#antre').addEventListener('change', e => { $('#nama').hidden = !e.target.checked; });
$('#bayarBtn').addEventListener('click', bayar);
$('#cartBar').addEventListener('click', openSheet);
$('#sheetClose').addEventListener('click', closeSheet);
$('#scrim').addEventListener('click', closeSheet);
document.querySelector('.ptabs').addEventListener('click', e => { const b = e.target.closest('[data-tab]'); if (b) setTab(b.dataset.tab); });
document.addEventListener('keydown', e => {
  const typing = /^(INPUT|TEXTAREA)$/.test(document.activeElement.tagName);
  if (e.key === '/' && !typing && !$('#done').open) { e.preventDefault(); $('#cari').focus(); }
  else if (e.key === 'F2') { e.preventDefault(); openSheet(); setTab('cart'); $('#bayar').focus(); }
  else if (e.key === 'Escape') { if (document.activeElement === $('#cari') && $('#cari').value) { $('#cari').value = ''; renderMenu(); } else closeSheet(); }
});

$('#produk').innerHTML = Array(8).fill('<div class="skeleton" style="height:140px"></div>').join('');
board.init(); renderPay();
poll(load, 5000)();
