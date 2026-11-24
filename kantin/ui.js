/* Helper bersama semua halaman: DOM, format, API, toast, tema, polling, papan antrean. */
const $ = (s, r = document) => r.querySelector(s);
const rp = n => 'Rp' + Number(n).toLocaleString('id-ID');
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ico = (n, c = '') => `<svg class="ic ${c}" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const LABEL = { menunggu: 'Menunggu', diproses: 'Diproses', siap: 'Siap diambil', selesai: 'Selesai' };
const STIKON = { menunggu: 'clock', diproses: 'utensils', siap: 'check', selesai: 'check' };
const ACT = { menunggu: 'Proses', diproses: 'Tandai siap', siap: 'Selesai / diambil' };
const csrfToken = () => (document.querySelector('meta[name=csrf]') || {}).content || '';

async function api(aksi, body, opt = {}) {
  const ctl = new AbortController(), to = setTimeout(() => ctl.abort(), 10000);
  try {
    const r = await fetch('api.php?aksi=' + aksi + (opt.q || ''), {
      signal: ctl.signal,
      ...(body ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': csrfToken() }, body: JSON.stringify(body) } : {})
    });
    if (r.status === 401 && !opt.publik) { location.href = 'login.php?next=' + encodeURIComponent(location.pathname.split('/').pop() || 'index.php'); return null; }
    let d = {}; try { d = await r.json(); } catch (_) { }
    if (!r.ok) { const e = new Error(d.error || 'Terjadi kesalahan'); e.status = r.status; throw e; }
    return d;
  } catch (e) {
    if (e.name === 'AbortError') throw new Error('Koneksi lambat atau terputus. Coba lagi.');
    if (e instanceof TypeError) throw new Error('Tidak dapat terhubung ke server.');
    throw e;
  } finally { clearTimeout(to); }
}

let _tt;
function toast(msg, err) {
  const t = $('#toast'); if (!t) return;
  t.innerHTML = ico(err ? 'alert' : 'check') + '<span>' + esc(msg) + '</span>';
  t.className = 'toast on' + (err ? ' err' : '');
  clearTimeout(_tt); _tt = setTimeout(() => t.className = 'toast', 3400);
}

document.addEventListener('click', e => {
  if (!e.target.closest('[data-theme-toggle]')) return;
  const d = document.documentElement, n = d.dataset.theme === 'dark' ? 'light' : 'dark';
  d.dataset.theme = n; try { localStorage.setItem('kantin-tema', n); } catch (_) { }
});

/* Polling hemat: tidak tumpang tindih, berhenti saat tab tersembunyi, segera jalan saat kembali. */
function poll(fn, ms) {
  let busy = false, timer;
  const run = async () => { if (busy || document.hidden) return; busy = true; try { await fn(); } finally { busy = false; } };
  timer = setInterval(run, ms);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) run(); });
  return run;
}

/* Papan antrean 3 kolom. reload() dipanggil setelah status berubah. */
function makeBoard(el, { big = false, reload, onlyStatus }) {
  let list = [], sig = '', maxId = 0, first = true; const busy = new Set();
  const cols = onlyStatus || ['menunggu', 'diproses', 'siap'];
  const waktu = m => m == null ? '' : m < 1 ? 'baru saja' : m + ' mnt lalu';
  const tiket = (p, s) => {
    const baru = p.menit != null && p.menit < 1, lama = s === 'menunggu' && p.menit >= 15;
    return `<article class="tk"><div class="no">#${p.nomor}</div><div>
      <div class="who">${esc(p.nama_pemesan)}${baru ? '<span class="flag">Baru</span>' : ''}${lama ? '<span class="flag late">Lama</span>' : ''}</div>
      <ul>${p.items.map(i => `<li>${esc(i)}</li>`).join('')}</ul>
      <div class="meta">${ico('clock')}${waktu(p.menit)}</div></div>
      <button class="btn ${s === 'siap' ? '' : 'btn-primary'} btn-sm act${busy.has(p.id) ? ' loading' : ''}" data-id="${p.id}" data-dari="${s}" ${busy.has(p.id) ? 'disabled' : ''}>${busy.has(p.id) ? '' : ico(s === 'siap' ? 'check' : 'arrow') + ACT[s]}</button></article>`;
  };
  function draw() {
    el.classList.toggle('big', big);
    el.innerHTML = cols.map(s => {
      const l = list.filter(p => p.status === s);
      return `<section class="col ${s}" aria-label="${LABEL[s]}"><h3>${ico(STIKON[s])}${LABEL[s]}<em>${l.length}</em></h3>` +
        (l.map(p => tiket(p, s)).join('') || `<div class="empty">${ico('list')}<span>Tidak ada</span></div>`) + '</section>';
    }).join('');
  }
  el.addEventListener('click', async e => {
    const b = e.target.closest('button[data-id]'); if (!b || b.disabled) return;
    const id = +b.dataset.id; busy.add(id); draw();
    try { await api('status', { id, dari: b.dataset.dari }); }
    catch (x) { toast(x.message, true); }
    try { await reload(); } finally { busy.delete(id); draw(); }
  });
  return {
    set(l) { const s = JSON.stringify(l); if (s === sig) return []; sig = s; list = l; draw(); const baru = first ? [] : l.filter(p => p.id > maxId); first = false; maxId = Math.max(maxId, ...l.map(p => p.id), 0); return baru; },
    init() { draw(); }
  };
}
