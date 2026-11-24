const trk = $('#trk'), pid = trk ? trk.dataset.id : 0, ORDER = ['menunggu', 'diproses', 'siap', 'selesai'];
let sig = '';
function draw(list) {
  const s = JSON.stringify(list); if (s === sig) return; sig = s;
  $('#papan').innerHTML = ['menunggu', 'diproses', 'siap'].map(k => {
    const l = list.filter(p => p.status === k);
    return `<section class="col ${k}" aria-label="${LABEL[k]}"><h3>${ico(STIKON[k])}${LABEL[k]}<em>${l.length}</em></h3><div class="nums">${l.map(p => `<div class="n">#${p.nomor}</div>`).join('') || `<div class="empty"><span>Tidak ada</span></div>`}</div></section>`;
  }).join('');
}
function track(p) {
  const now = ORDER.indexOf(p.status);
  document.querySelectorAll('.track li').forEach((li, i) => { li.className = i < now ? 'done' : i === now ? 'now' : ''; });
  $('#info').innerHTML = p.status === 'siap' ? `<div class="callout siap">${ico('check')} Pesananmu siap diambil!</div>`
    : p.status === 'selesai' ? '<div class="callout info">Pesanan selesai. Terima kasih!</div>'
    : `<div class="callout info">Pesanan di depanmu: <b>${p.depan}</b></div>`;
}
async function load() {
  try {
    const d = await api('publik', null, { publik: true, q: pid ? '&id=' + pid : '' });
    draw(d.antrean); if (d.pesanan) track(d.pesanan);
    $('#live').classList.remove('off'); $('#liveT').textContent = 'Diperbarui otomatis';
  } catch (e) { $('#live').classList.add('off'); $('#liveT').textContent = 'Terputus, mencoba lagi…'; }
}
poll(load, 8000)();
