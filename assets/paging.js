// Paginasi otomatis untuk tabel daftar: 10 baris per halaman, bisa pilih halaman.
// Lewati tabel dengan data-nopage atau yang barisnya berisi isian form.
(function () {
  'use strict';
  var UKURAN = 10;
  function pasang(tabel) {
    if (tabel.hasAttribute('data-nopage') || tabel.closest('[data-nopage]')) { return; }
    var tb = tabel.tBodies[0];
    if (!tb) { return; }
    var baris = Array.prototype.filter.call(tb.rows, function (tr) { return !tr.querySelector('td.empty') && !tr.querySelector('input:not([type=hidden]),select,textarea'); });
    var ukuran = parseInt(tabel.getAttribute('data-page-size') || UKURAN, 10) || UKURAN;
    if (baris.length <= ukuran) { return; }
    var jml = Math.ceil(baris.length / ukuran), hal = 1;
    var bar = document.createElement('div');
    bar.className = 'pg-bar';
    bar.innerHTML = '<span class="pg-info"></span><span class="pg-ctl"><button type="button" class="btn ghost sm pg-prev">&lsaquo; Sebelumnya</button> <label class="pg-pilih">Halaman <select class="pg-sel"></select> dari ' + jml + '</label> <button type="button" class="btn ghost sm pg-next">Berikutnya &rsaquo;</button></span>';
    var sel = bar.querySelector('.pg-sel');
    for (var i = 1; i <= jml; i++) { var o = document.createElement('option'); o.value = i; o.textContent = i; sel.appendChild(o); }
    function tampil(h) {
      hal = Math.min(Math.max(h, 1), jml);
      baris.forEach(function (tr, i) { tr.classList.toggle('pg-h', Math.floor(i / ukuran) + 1 !== hal); });
      sel.value = hal;
      bar.querySelector('.pg-prev').disabled = hal === 1;
      bar.querySelector('.pg-next').disabled = hal === jml;
      bar.querySelector('.pg-info').textContent = 'Menampilkan ' + ((hal - 1) * ukuran + 1) + '-' + Math.min(hal * ukuran, baris.length) + ' dari ' + baris.length;
    }
    bar.querySelector('.pg-prev').addEventListener('click', function () { tampil(hal - 1); });
    bar.querySelector('.pg-next').addEventListener('click', function () { tampil(hal + 1); });
    sel.addEventListener('change', function () { tampil(parseInt(sel.value, 10)); });
    var kotak = tabel.closest('.scroll') || tabel;
    kotak.parentNode.insertBefore(bar, kotak.nextSibling);
    tampil(1);
  }
  function mulai() { Array.prototype.forEach.call(document.querySelectorAll('table.rt'), pasang); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', mulai); } else { mulai(); }
})();
