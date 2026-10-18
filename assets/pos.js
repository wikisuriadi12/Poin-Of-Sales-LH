/* Halaman kasir. Semua teks dari server dipasang lewat textContent (bukan innerHTML). */
(() => {
  'use strict';
  const app = document.getElementById('app');
  if (!app) return;
  const CSRF = app.dataset.csrf;
  const MOCK = app.dataset.mock === '1';
  const MERCHANT = app.dataset.merchant || 'Koperasi';
  const METODE = { tunai: 'Tunai', qris: 'BNI QRIS', saldo: 'Saldo Santri' };

  const $ = (s, r = document) => r.querySelector(s);
  const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
  const num = v => Number(String(v).replace(/\D/g, '')) || 0;
  const fmtInput = n => (n ? n.toLocaleString('id-ID') : '');

  function h(tag, attrs, ...kids) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === false || v == null) continue;
      if (k === 'class') el.className = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v === true ? '' : v);
    }
    for (const kid of kids.flat()) {
      if (kid == null || kid === false) continue;
      el.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
    }
    return el;
  }

  async function api(route, { query = '', body } = {}) {
    const init = { method: body ? 'POST' : 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin' };
    if (body) {
      init.headers['Content-Type'] = 'application/json';
      init.headers['X-CSRF-Token'] = CSRF;
      init.body = JSON.stringify(body);
    }
    const res = await fetch('api.php?r=' + route + query, init);
    let data = null;
    try { data = await res.json(); } catch (e) { /* respons bukan JSON */ }
    if (!res.ok || !data || data.ok === false) throw new Error((data && data.error) || 'Permintaan gagal (' + res.status + ').');
    return data;
  }

  const st = { pondok: 'ikhwan', products: [], santri: [], cat: 'Semua', q: '', cart: new Map(), disc: 0, metode: 'tunai', uang: 0, santriId: 0, busy: false };
  let pollTimer = null;
  let tickTimer = null;
  const byId = id => st.products.find(p => p.id === id);

  function totals() {
    let sub = 0, count = 0;
    for (const [id, q] of st.cart) {
      const p = byId(id);
      if (p) { sub += p.harga * q; count += q; }
    }
    const disc = Math.min(st.disc, sub);
    return { sub, disc, total: sub - disc, count };
  }

  /* ---------- Pesan ---------- */
  function showErr(msg) { const e = $('#err'); e.textContent = msg; e.hidden = false; }
  function hideErr() { $('#err').hidden = true; }

  /* ---------- Katalog ---------- */
  function renderChips() {
    const cats = ['Semua', ...new Set(st.products.map(p => p.kategori))];
    $('#chips').replaceChildren(...cats.map(c => h('button', { type: 'button', class: 'chip', 'aria-pressed': String(c === st.cat), onclick: () => { st.cat = c; renderChips(); renderGrid(); } }, c)));
  }

  function renderGrid() {
    const q = st.q.trim().toLowerCase();
    const list = st.products.filter(p => (st.cat === 'Semua' || p.kategori === st.cat) && (!q || p.nama.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)));
    if (!list.length) { $('#grid').replaceChildren(h('p', { class: 'muted' }, 'Tidak ada barang yang cocok.')); return; }
    $('#grid').replaceChildren(...list.map(p => {
      const inCart = st.cart.get(p.id) || 0;
      const low = p.stok <= p.stok_min;
      const pillClass = p.stok <= 0 ? 'pill st-habis' : (low ? 'pill st-menipis' : 'pill');
      const label = p.stok <= 0 ? 'Habis' : 'Stok ' + p.stok + (low ? ' · menipis' : '');
      return h('button', { type: 'button', class: 'card-p' + (inCart ? ' in' : ''), disabled: p.stok <= 0 || inCart >= p.stok, onclick: () => addItem(p.id) },
        h('span', { class: 'top-row' }, h('span', { class: 'ini' }, p.nama.slice(0, 2).toUpperCase()), inCart ? h('span', { class: 'qty' }, '×' + inCart) : null),
        h('span', { class: 'nm' }, p.nama),
        h('span', { class: 'sku' }, p.sku),
        h('span', { class: 'bottom' }, h('span', { class: 'price' }, rp(p.harga)), h('span', { class: pillClass }, label)));
    }));
  }

  async function loadProducts() {
    try {
      const { products } = await api('products', { query: '&pondok=' + st.pondok });
      st.products = products;
      for (const id of [...st.cart.keys()]) {
        const p = byId(id);
        if (!p) st.cart.delete(id);
        else if (st.cart.get(id) > p.stok) st.cart.set(id, p.stok);
      }
      renderChips(); renderGrid(); renderLines(); update();
    } catch (e) { showErr(e.message); }
  }

  async function loadSantri() {
    try { st.santri = (await api('santri', { query: '&pondok=' + st.pondok })).santri; renderPayExtra(); } catch (e) { showErr(e.message); }
  }

  /* ---------- Keranjang ---------- */
  function addItem(id) {
    const p = byId(id);
    const cur = st.cart.get(id) || 0;
    if (!p || cur >= p.stok) { showErr('Stok ' + (p ? p.nama : 'barang') + ' tidak cukup.'); return; }
    hideErr();
    st.cart.set(id, cur + 1);
    renderGrid(); renderLines(); update();
  }
  function changeQty(id, delta) {
    const p = byId(id);
    const next = (st.cart.get(id) || 0) + delta;
    if (next <= 0) st.cart.delete(id);
    else if (p && next > p.stok) { showErr('Stok ' + p.nama + ' tidak cukup.'); return; }
    else st.cart.set(id, next);
    renderGrid(); renderLines(); update();
  }
  function renderLines() {
    const ul = $('#lines');
    if (!st.cart.size) { ul.replaceChildren(h('li', { class: 'empty' }, 'Keranjang kosong. Pilih barang dari katalog.')); return; }
    ul.replaceChildren(...[...st.cart].map(([id, q]) => {
      const p = byId(id);
      return h('li', { class: 'ln' },
        h('span', { class: 'info' }, h('b', {}, p.nama), h('span', {}, rp(p.harga))),
        h('span', { class: 'step' },
          h('button', { type: 'button', 'aria-label': 'Kurangi ' + p.nama, onclick: () => changeQty(id, -1) }, '−'),
          h('span', {}, q),
          h('button', { type: 'button', 'aria-label': 'Tambah ' + p.nama, onclick: () => changeQty(id, 1) }, '+')),
        h('span', { class: 'tot' }, rp(p.harga * q)));
    }));
  }
  function resetSale() {
    st.cart.clear(); st.disc = 0; st.uang = 0; st.santriId = 0;
    $('#disc').value = '';
    renderGrid(); renderLines(); renderPayExtra(); update();
  }

  /* ---------- Pembayaran ---------- */
  function renderPayExtra() {
    const box = $('#payextra');
    if (st.metode === 'tunai') {
      const input = h('input', { id: 'uang', class: 'field', inputmode: 'numeric', placeholder: '0', autocomplete: 'off', value: fmtInput(st.uang),
        oninput: e => { st.uang = num(e.target.value); e.target.value = fmtInput(st.uang); update(); } });
      const quick = [['Uang pas', () => totals().total], ['50.000', () => 50000], ['100.000', () => 100000]].map(([label, fn]) =>
        h('button', { type: 'button', onclick: () => { st.uang = fn(); input.value = fmtInput(st.uang); update(); } }, label));
      box.replaceChildren(
        h('div', { class: 'row' }, h('label', { for: 'uang' }, 'Uang diterima'), input),
        h('div', { class: 'quick' }, quick),
        h('div', { class: 'row' }, h('span', {}, 'Kembalian'), h('span', { class: 'out', id: 'change' }, 'Rp 0')));
    } else if (st.metode === 'qris') {
      box.replaceChildren(h('p', { class: 'muted' }, 'QR dibuat oleh BNI untuk pesanan ini. Pembeli memindai dengan BNI Mobile Banking atau aplikasi lain yang mendukung QRIS.'));
    } else {
      const sel = h('select', { id: 'santri', 'aria-label': 'Pilih santri', onchange: e => { st.santriId = Number(e.target.value) || 0; update(); } },
        h('option', { value: '' }, 'Pilih santri'),
        st.santri.map(s => h('option', { value: s.id, selected: s.id === st.santriId }, s.nama + ' · ' + s.nomor_induk)));
      box.replaceChildren(sel,
        h('div', { class: 'row' }, h('span', {}, 'Saldo uang saku'), h('span', { class: 'out', id: 'saldo-now' }, '-')),
        h('div', { class: 'row' }, h('span', {}, 'Sisa setelah bayar'), h('span', { class: 'out', id: 'saldo-after' }, '-')));
    }
  }

  function update() {
    const t = totals();
    $('#subtotal').textContent = rp(t.sub);
    $('#total').textContent = rp(t.total);
    $('#count').textContent = t.count + ' item';
    let ok = t.count > 0 && t.total > 0;
    if (st.metode === 'tunai') {
      const c = $('#change');
      if (c) {
        if (st.uang >= t.total) { c.textContent = rp(st.uang - t.total); c.classList.remove('bad'); }
        else { c.textContent = 'Kurang ' + rp(t.total - st.uang); c.classList.add('bad'); }
      }
      ok = ok && st.uang >= t.total;
    } else if (st.metode === 'saldo') {
      const s = st.santri.find(x => x.id === st.santriId);
      const now = $('#saldo-now'), after = $('#saldo-after');
      if (s && now) {
        now.textContent = rp(s.saldo);
        const rest = s.saldo - t.total;
        after.textContent = rest >= 0 ? rp(rest) : 'Saldo kurang ' + rp(-rest);
        after.classList.toggle('bad', rest < 0);
        ok = ok && rest >= 0;
      } else {
        if (now) { now.textContent = '-'; after.textContent = '-'; after.classList.remove('bad'); }
        ok = false;
      }
    }
    const btn = $('#pay');
    btn.disabled = !ok || st.busy;
    btn.textContent = st.busy ? 'Memproses...' : (t.total > 0 ? 'Bayar ' + rp(t.total) : 'Bayar');
  }

  async function pay() {
    if (st.busy) return;
    hideErr();
    const t = totals();
    const body = { pondok: st.pondok, metode: st.metode, diskon: t.disc, items: [...st.cart].map(([product_id, qty]) => ({ product_id, qty })) };
    if (st.metode === 'tunai') body.uang_diterima = st.uang;
    if (st.metode === 'saldo') body.santri_id = st.santriId;
    st.busy = true; update();
    try {
      const { order } = await api('checkout', { body });
      resetSale();
      if (order.status === 'menunggu') showQris(order); else showReceipt(order);
      if (st.metode === 'saldo' || order.metode === 'saldo') loadSantri();
      loadProducts();
    } catch (e) {
      showErr(e.message);
      loadProducts();
    } finally {
      st.busy = false; update();
    }
  }

  /* ---------- Modal ---------- */
  function stopTimers() { clearInterval(pollTimer); clearInterval(tickTimer); pollTimer = tickTimer = null; }
  function openModal(...nodes) {
    $('#sheet').replaceChildren(...nodes);
    $('#modal').hidden = false;
    const first = $('#sheet button');
    if (first) first.focus();
  }
  function closeModal() { stopTimers(); $('#modal').hidden = true; }

  function showQris(order) {
    stopTimers();
    const pay = order.pembayaran;
    const deadline = Date.now() + pay.detik_sisa * 1000;
    const qrbox = h('div', { class: 'qrbox', role: 'img', 'aria-label': 'Kode QR pembayaran' });
    const timer = h('p', { class: 'timer' });
    const status = h('p', { class: 'center muted', 'aria-live': 'polite' }, 'Menunggu pembayaran. Halaman ini diperbarui otomatis.');
    const actions = [h('button', { type: 'button', class: 'secondary', onclick: cancel }, 'Batalkan pesanan')];
    if (MOCK) actions.unshift(h('button', { type: 'button', class: 'demo', onclick: simulate }, 'Simulasikan pembayaran diterima (uji)'));
    openModal(
      h('h2', {}, 'Bayar dengan BNI QRIS'),
      h('p', { class: 'center muted' }, MERCHANT + ' · ' + order.nomor),
      h('p', { class: 'big' }, rp(order.total)),
      qrbox, timer,
      MOCK ? h('p', { class: 'center muted' }, 'Mode uji: kode ini bukan QRIS sungguhan dan tidak dapat dipindai untuk membayar.') : null,
      status,
      h('div', { class: 'btns', style: 'flex-direction:column' }, actions));
    if (window.QRCode) new window.QRCode(qrbox, { text: pay.qr_string, width: 200, height: 200, correctLevel: window.QRCode.CorrectLevel.M });
    else qrbox.append(h('code', {}, pay.qr_string));

    const tick = () => {
      const s = Math.max(0, Math.round((deadline - Date.now()) / 1000));
      timer.textContent = 'Berlaku ' + String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
    };
    tick();
    tickTimer = setInterval(tick, 1000);
    const poll = async () => {
      try {
        const { order: o } = await api('order', { query: '&nomor=' + encodeURIComponent(order.nomor) });
        if (o.status === 'lunas') { stopTimers(); showReceipt(o); loadProducts(); }
        else if (o.status !== 'menunggu') { stopTimers(); showEnded(o); loadProducts(); }
      } catch (e) { /* coba lagi pada putaran berikutnya */ }
    };
    pollTimer = setInterval(poll, 2500);

    async function cancel() {
      try { await api('cancel', { body: { nomor: order.nomor } }); closeModal(); loadProducts(); }
      catch (e) { status.textContent = e.message; poll(); }
    }
    async function simulate() {
      try { await api('mock_pay', { body: { nomor: order.nomor } }); poll(); }
      catch (e) { status.textContent = e.message; }
    }
  }

  function showEnded(o) {
    openModal(
      h('h2', {}, 'Pesanan ' + o.status),
      h('p', { class: 'center muted' }, o.nomor + '. Stok barang sudah dikembalikan. Bila pembeli sudah terlanjur membayar, hubungi administrator untuk pengembalian dana.'),
      h('button', { type: 'button', class: 'primary', onclick: closeModal }, 'Tutup'));
  }

  function showReceipt(o) {
    const when = new Date((o.paid_at || o.created_at).replace(' ', 'T'));
    const rows = o.items.map(i => h('div', { class: 'rl' }, h('span', {}, i.qty + ' × ' + i.nama), h('span', {}, rp(i.subtotal))));
    const extra = [];
    if (o.metode === 'tunai') extra.push(h('div', { class: 'rl' }, h('span', {}, 'Tunai'), h('span', {}, rp(o.uang_diterima))), h('div', { class: 'rl' }, h('span', {}, 'Kembalian'), h('span', {}, rp(o.kembalian))));
    if (o.metode === 'saldo') extra.push(h('div', { class: 'rl' }, h('span', {}, 'Santri'), h('span', {}, o.santri_nama || '-')), h('div', { class: 'rl' }, h('span', {}, 'Sisa saldo'), h('span', {}, rp(o.santri_saldo || 0))));
    openModal(
      h('h2', {}, 'Pembayaran diterima'),
      h('div', { class: 'receipt' },
        h('div', { class: 'c' }, MERCHANT, h('br'), 'Pondok ' + o.pondok, h('br'), when.toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }), h('br'), o.nomor),
        h('hr'), rows, h('hr'),
        h('div', { class: 'rl' }, h('span', {}, 'Subtotal'), h('span', {}, rp(o.subtotal))),
        o.diskon ? h('div', { class: 'rl' }, h('span', {}, 'Diskon'), h('span', {}, '− ' + rp(o.diskon))) : null,
        h('div', { class: 'rl' }, h('b', {}, 'Total'), h('b', {}, rp(o.total))),
        h('div', { class: 'rl' }, h('span', {}, 'Metode'), h('span', {}, METODE[o.metode] || o.metode)),
        extra, h('hr'), h('div', { class: 'c' }, 'Jazakumullahu khairan')),
      h('div', { class: 'btns' },
        h('button', { type: 'button', class: 'secondary', onclick: () => window.print() }, 'Cetak struk'),
        h('button', { type: 'button', class: 'primary', onclick: closeModal }, 'Transaksi baru')));
  }

  /* ---------- Pemasangan event ---------- */
  $('#q').addEventListener('input', e => { st.q = e.target.value; renderGrid(); });
  $('#disc').addEventListener('input', e => { st.disc = num(e.target.value); e.target.value = fmtInput(st.disc); update(); });
  $('#clear').addEventListener('click', () => { if (st.cart.size) { resetSale(); hideErr(); } });
  $('#pay').addEventListener('click', pay);
  $('#methods').addEventListener('click', e => {
    const b = e.target.closest('[data-m]');
    if (!b) return;
    st.metode = b.dataset.m;
    document.querySelectorAll('#methods [data-m]').forEach(x => x.setAttribute('aria-pressed', String(x.dataset.m === st.metode)));
    hideErr();
    if (st.metode === 'saldo') loadSantri(); else renderPayExtra();
    update();
  });
  $('#pondok').addEventListener('click', e => {
    const b = e.target.closest('[data-pondok]');
    if (!b || b.dataset.pondok === st.pondok) return;
    const had = st.cart.size > 0;
    st.pondok = b.dataset.pondok; st.cat = 'Semua'; st.q = ''; $('#q').value = '';
    document.querySelectorAll('#pondok [data-pondok]').forEach(x => x.setAttribute('aria-pressed', String(x.dataset.pondok === st.pondok)));
    st.cart.clear(); st.disc = 0; st.uang = 0; st.santriId = 0; $('#disc').value = '';
    renderLines();
    loadProducts();
    if (st.metode === 'saldo') loadSantri(); else renderPayExtra();
    if (had) showErr('Keranjang dikosongkan karena pondok berganti.'); else hideErr();
  });

  renderLines();
  renderPayExtra();
  update();
  loadProducts();
})();
