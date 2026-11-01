<?php
// Kasir: catat top up e-money yang sudah dikirim lewat aplikasi agen. Tercatat sebagai transaksi harian.
Auth::require('pos');
if (is_post()) {
    csrf_check();
    try {
        $id = Emoney::topup((int) in_str('produk_id', '0'), in_str('no_tujuan'), in_str('nama'), (int) preg_replace('/\D/', '', in_str('nominal', '0')), in_str('metode'), in_str('ref'), Auth::id());
        flash('Top up tercatat.');
        redirect('?p=struk&id=' . $id);
    } catch (InvalidArgumentException $ex) {
        flash($ex->getMessage(), 'err');
        redirect('?p=topup');
    }
}
$produk = Emoney::produk(true);
$riwayat = Emoney::hariIni(Pos::hanyaKasir());
$dataProduk = array_map(static fn($x) => ['id' => (int) $x['id'], 'admin' => (int) $x['biaya_admin'], 'min' => (int) $x['nominal_min'], 'maks' => (int) $x['nominal_maks'], 'saldo' => (int) $x['sumber_saldo']], $produk);
ob_start(); ?>
<div class="page-h"><div><h1>Top Up E-Money</h1><p>Kirim saldo lewat aplikasi agen dulu, lalu catat di sini. Transaksi masuk riwayat harian.</p></div>
  <?php if (Auth::can('emoney.deposit') || Auth::can('emoney.produk')): ?><div class="acts"><a class="btn ghost" href="?p=emoney"><?= icon('wallet') ?> Deposit &amp; produk</a></div><?php endif; ?></div>
<?php if (!$produk): ?>
  <div class="card"><div class="empty">Belum ada produk e-money aktif.<?= Auth::can('emoney.produk') ? ' <a href="?p=emoney">Tambah produk dan saldo deposit</a>.' : ' Hubungi admin untuk menambah produk.' ?></div></div>
<?php else: ?>
<div class="grid g2">
  <form method="post" class="card form" id="tu-form" autocomplete="off" data-confirm="Saldo sudah dikirim lewat aplikasi agen dan pembayaran sudah diterima?">
    <?= csrf_field() ?>
    <h2>Catat top up</h2>
    <label>Produk <select name="produk_id" id="tu-produk" required><option value="">- pilih -</option>
      <?php foreach ($produk as $x): ?><option value="<?= (int) $x['id'] ?>"><?= e($x['nama']) ?> (admin <?= e(rp($x['biaya_admin'])) ?>)</option><?php endforeach; ?></select></label>
    <label>Nomor tujuan (no. HP / no. kartu) <input name="no_tujuan" required inputmode="numeric" maxlength="40" placeholder="08xxxxxxxxxx"></label>
    <label>Nama pelanggan (opsional) <input name="nama" maxlength="80"></label>
    <label>Nominal top up <input name="nominal" id="tu-nominal" data-rupiah inputmode="numeric" required placeholder="50.000"></label>
    <label>Cara bayar <select name="metode"><?= options(Emoney::METODE, 'tunai') ?></select></label>
    <label>No. referensi dari aplikasi agen (opsional) <input name="ref" maxlength="80"></label>
    <div class="note" id="tu-info" style="margin:6px 0 10px">Pilih produk dan isi nominal.</div>
    <button class="btn"><?= icon('check') ?> Simpan top up</button>
  </form>
  <div class="card"><h2>Top up hari ini<?= Pos::hanyaKasir() ? ' (Anda)' : '' ?></h2>
    <div class="scroll"><table class="rt"><thead><tr><th>Waktu</th><th>Produk</th><th>Tujuan</th><th class="r">Nominal</th><th class="r">Bayar</th><th></th></tr></thead><tbody>
    <?php foreach ($riwayat as $r): ?><tr><td><?= e(substr($r['waktu'], 11, 5)) ?></td><td data-label="Produk"><?= e($r['produk_nama']) ?></td><td data-label="Tujuan"><?= e($r['no_tujuan']) ?></td>
      <td class="r" data-label="Nominal"><?= rp($r['nominal']) ?></td><td class="r" data-label="Bayar"><?= rp($r['total']) ?><?= (int) $r['dibatalkan'] ? ' <span class="badge b-belum">Batal</span>' : '' ?></td>
      <td class="r"><a class="btn sm ghost" href="?p=struk&amp;id=<?= (int) $r['penjualan_id'] ?>">Struk</a></td></tr><?php endforeach; ?>
    <?php if (!$riwayat): ?><tr><td colspan="6" class="empty">Belum ada top up hari ini.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
<script>
(function () {
  var P = <?= json_encode($dataProduk) ?>, sel = document.getElementById('tu-produk'), nom = document.getElementById('tu-nominal'), info = document.getElementById('tu-info');
  function rp(n) { return 'Rp ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
  function hitung() {
    var p = P.filter(function (x) { return String(x.id) === sel.value; })[0], n = parseInt((nom.value || '').replace(/\D/g, '') || '0', 10);
    if (!p) { info.textContent = 'Pilih produk dan isi nominal.'; return; }
    var t = 'Nominal ' + rp(p.min) + ' s/d ' + rp(p.maks) + '. ';
    if (n > 0) { t += 'Pelanggan bayar ' + rp(n + p.admin) + ' (nominal ' + rp(n) + ' + admin ' + rp(p.admin) + ').'; }
    if (n > 0 && n > p.saldo) { t += ' Saldo deposit tidak cukup (sisa ' + rp(p.saldo) + ').'; }
    info.textContent = t;
  }
  sel.addEventListener('change', hitung); nom.addEventListener('input', hitung);
})();
</script>
<?php endif; ?>
<?php render('Top Up E-Money', ob_get_clean());
