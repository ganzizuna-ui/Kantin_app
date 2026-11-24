const rows = [...document.querySelectorAll('.menu-row')];
function hitung() {
  let n = 0, t = 0;
  rows.forEach(r => {
    const i = r.querySelector('input'), max = +i.max; let q = Math.max(0, Math.min(max, parseInt(i.value) || 0));
    if (i.value !== '' && +i.value !== q) i.value = q;
    r.classList.toggle('on', q > 0); n += q; t += q * +r.dataset.harga;
  });
  $('#cnt').textContent = n + ' item'; $('#tot').textContent = rp(t);
  return { n, t };
}
document.querySelector('.menu-list').addEventListener('click', e => {
  const b = e.target.closest('button[data-d]'); if (!b) return;
  const i = b.parentElement.querySelector('input'), v = (parseInt(i.value) || 0) + +b.dataset.d;
  if (v > +i.max) toast('Stok hanya ' + i.max, true);
  i.value = Math.max(0, Math.min(+i.max, v)); hitung();
});
document.querySelector('.menu-list').addEventListener('input', hitung);
$('#f').addEventListener('submit', e => {
  const err = $('#clientErr'), { n } = hitung();
  const nm = $('#nama'), msg = nm && !nm.value.trim() ? 'Isi nama kamu dulu.' : !n ? 'Pilih minimal satu menu.' : '';
  if (msg) { e.preventDefault(); err.hidden = false; err.querySelector('span').textContent = msg; err.scrollIntoView({ block: 'center' }); (nm && !nm.value.trim() ? nm : rows[0].querySelector('input')).focus(); return; }
  const b = $('#kirim'); b.classList.add('loading'); b.textContent = 'Mengirim';
});
hitung();
