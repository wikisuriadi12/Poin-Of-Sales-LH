<?php require_can('kasir'); ?>
<div id="app"
     data-csrf="<?= esc(csrf()) ?>"
     data-merchant="<?= esc(cfg('merchant_name', 'Koperasi')) ?>"
     data-mock="<?= cfg('qris_provider', 'mock') === 'mock' ? '1' : '0' ?>">
  <div class="pos">
    <section class="catalog" aria-label="Katalog barang">
      <div class="catalog-head">
        <div class="seg" id="pondok" role="group" aria-label="Pilih pondok">
          <button type="button" data-pondok="ikhwan" aria-pressed="true">Pondok Ikhwan</button>
          <button type="button" data-pondok="akhwat" aria-pressed="false">Pondok Akhwat</button>
        </div>
        <input id="q" type="search" placeholder="Cari nama barang atau SKU" aria-label="Cari barang" autocomplete="off">
      </div>
      <div class="chips" id="chips"></div>
      <div class="grid" id="grid" aria-live="polite"></div>
    </section>

    <aside class="cart" aria-label="Keranjang">
      <div class="cart-head">
        <h2>Pesanan baru <span class="pill" id="count">0 item</span></h2>
        <button type="button" class="link" id="clear">Kosongkan</button>
      </div>
      <ul class="lines" id="lines"></ul>
      <div class="sum">
        <div class="row"><span>Subtotal</span><span id="subtotal">Rp 0</span></div>
        <div class="row"><label for="disc">Diskon (Rp)</label><input id="disc" class="field" inputmode="numeric" placeholder="0" autocomplete="off"></div>
        <div class="row total"><span>Total</span><b id="total">Rp 0</b></div>
      </div>
      <div class="methods" id="methods" role="group" aria-label="Metode pembayaran">
        <button type="button" data-m="tunai" aria-pressed="true">Tunai</button>
        <button type="button" data-m="qris" aria-pressed="false">BNI QRIS</button>
        <button type="button" data-m="saldo" aria-pressed="false">Saldo Santri</button>
      </div>
      <div id="payextra" class="payextra"></div>
      <p id="err" class="error" role="alert" hidden></p>
      <button type="button" class="primary" id="pay" disabled>Bayar</button>
    </aside>
  </div>
  <div id="modal" class="modal" hidden><div class="sheet" id="sheet" role="dialog" aria-modal="true"></div></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="assets/pos.js?v=1" defer></script>
