let muted = false, ctx;
try { muted = localStorage.getItem('kantin-bunyi') === '0'; } catch (_) { }
const board = makeBoard($('#kolom'), { big: true, reload: () => load() });
function bunyiUI() { $('#bunyi').setAttribute('aria-pressed', !muted); $('#bunyi').innerHTML = ico(muted ? 'mute' : 'volume') + '<span>' + (muted ? 'Bunyi mati' : 'Bunyi aktif') + '</span>'; }
function beep() {
  if (muted) return;
  try {
    ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator(), g = ctx.createGain(); o.connect(g); g.connect(ctx.destination);
    o.frequency.value = 880; g.gain.setValueAtTime(.15, ctx.currentTime); g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + .4);
    o.start(); o.stop(ctx.currentTime + .4);
  } catch (_) { }
}
$('#bunyi').onclick = () => { muted = !muted; try { localStorage.setItem('kantin-bunyi', muted ? '0' : '1'); } catch (_) { } bunyiUI(); beep(); };
let seq = 0;
async function load() {
  const my = ++seq;
  try {
    const d = await api('data'); if (!d || my !== seq) return;
    const baru = board.set(d.antrean);
    if (baru.length) { toast('Pesanan baru: ' + baru.map(p => '#' + p.nomor).join(', ')); beep(); }
    const n = d.antrean.filter(p => p.status === 'menunggu').length;
    document.title = (n ? `(${n}) ` : '') + 'Dapur · Kantin';
    $('#live').classList.remove('off'); $('#liveT').textContent = 'Langsung · ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch (e) { $('#live').classList.add('off'); $('#liveT').textContent = 'Terputus — mencoba lagi'; }
}
bunyiUI(); board.init(); poll(load, 4000)();
