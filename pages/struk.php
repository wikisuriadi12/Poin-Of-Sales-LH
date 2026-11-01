<?php
Auth::require('penjualan');
$scope = Auth::scope();
$p = Pos::find((int) in_str('id', '0'));
if ($p && ($milik = Pos::hanyaKasir()) && (int) $p['user_id'] !== $milik) { $p = null; } // kasir hanya struk miliknya
if (!$p) { http_response_code(404); render('Tidak ditemukan', '<div class="card"><div class="empty">Struk tidak ditemukan.</div></div>'); exit; }
Auth::assertUnit($p['unit']);
$items = Pos::items((int) $p['id']);
$tu = ($p['jenis'] ?? 'barang') === 'topup' ? Emoney::trx((int) $p['id']) : null;
$cfgS = Settings::all();
$batal = (int) $p['dibatalkan'];
if (is_post()) {
    csrf_check();
    if (!Pos::bolehBatal($p)) {
        flash('Kasir hanya boleh membatalkan penjualannya sendiri pada hari yang sama. Hubungi admin atau bendahara.', 'err');
        redirect('?p=struk&id=' . (int) $p['id']);
    }
    try {
        Pos::batalkan((int) $p['id'], in_str('alasan'), Auth::id(), $scope);
        flash('Penjualan dibatalkan; stok dan saldo dikembalikan.', 'warn');
    } catch (InvalidArgumentException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('?p=struk&id=' . (int) $p['id']);
}
$bisaBatal = !$batal && Pos::bolehBatal($p);
ob_start(); ?>
<div class="page-h noprint"><div><h1><?= $tu ? 'Struk top up' : 'Struk penjualan' ?></h1></div>
  <div class="acts"><button class="btn" data-print><?= icon('printer') ?> Cetak</button>
    <?php if (Auth::can('pos')): ?><a class="btn ghost" href="?p=<?= $tu ? 'topup' : 'pos&amp;unit=' . e($p['unit']) ?>"><?= $tu ? 'Top up baru' : 'Transaksi baru' ?></a><?php endif; ?>
    <a class="btn ghost" href="?p=penjualan">Daftar penjualan</a></div></div>
<div class="card struk">
  <div class="rh"><b style="font-size:1.05rem">Koperasi <?= e($cfgS['nama_lembaga']) ?></b><br><small>LH Mart<?= $cfgS['alamat'] ? ' &middot; ' . e($cfgS['alamat']) : '' ?></small>
    <?php if ($batal): ?><br><span class="badge b-belum">DIBATALKAN</span><?php endif; ?></div>
  <small><?= e($p['no_struk']) ?> &middot; <?= e(substr($p['created_at'], 0, 16)) ?><br>Kasir: <?= e($p['kasir'] ?: '-') ?><?= $p['santri_nama'] ? '<br>Santri: ' . e($p['santri_nama']) . ' (' . e($p['nis']) . ')' : '<br>Pelanggan: ' . e(($p['pelanggan'] ?? '') !== '' ? $p['pelanggan'] : 'Umum') ?></small>
  <?php if ($tu): ?>
  <table style="margin-top:10px">
    <tr><td>Top up <?= e($tu['produk_nama']) ?><br><small>Tujuan: <?= e($tu['no_tujuan']) ?><?= $tu['nama_pelanggan'] !== '' ? ' (' . e($tu['nama_pelanggan']) . ')' : '' ?></small></td><td class="r"><?= rp($tu['nominal']) ?></td></tr>
    <tr><td>Biaya admin</td><td class="r"><?= rp($tu['biaya_admin']) ?></td></tr>
    <?php if ($tu['ref_penyedia'] !== ''): ?><tr><td colspan="2"><small>Ref: <?= e($tu['ref_penyedia']) ?></small></td></tr><?php endif; ?>
  </table>
  <?php else: ?>
  <table style="margin-top:10px"><?php foreach ($items as $i): ?>
    <tr><td><?= e($i['nama']) ?><br><small><?= (int) $i['qty'] ?> &times; <?= rp($i['harga']) ?></small></td><td class="r"><?= rp($i['subtotal']) ?></td></tr>
  <?php endforeach; ?></table>
  <?php endif; ?>
  <div class="sum">
    <?php $dk = (int) ($p['diskon'] ?? 0); if ($dk > 0): ?><div><span>Subtotal</span><span><?= rp((int) $p['total'] + $dk) ?></span></div><div><span>Diskon</span><span>&minus; <?= rp($dk) ?></span></div><?php endif; ?>
    <div><b>Total</b><b><?= rp($p['total']) ?></b></div>
    <div><span>Metode</span><span><?= e(LaporanPos::CARA_BAYAR[$p['metode']] ?? $p['metode']) ?></span></div>
    <?php if ($p['metode'] === 'qris' && !empty($p['bukti_qris'])): ?><div><span>Bukti QRIS</span><span><a href="?p=bukti_qris&amp;id=<?= (int) $p['id'] ?>" target="_blank" rel="noopener">Lihat foto</a></span></div><?php endif; ?>
    <?php if ($p['metode'] === 'hutang'): $pt = Db::one('SELECT * FROM piutang WHERE penjualan_id = ?', [(int) $p['id']]); if ($pt): ?><div><span>Dibayar / sisa hutang</span><span><?= rp($pt['dibayar']) ?> / <?= rp((int) $pt['total'] - (int) $pt['dibayar']) ?></span></div><?php endif; endif; ?>
    <?php if ($p['metode'] === 'tunai'): ?><div><span>Diterima</span><span><?= rp($p['dibayar']) ?></span></div><div><span>Kembali</span><span><?= rp($p['dibayar'] - $p['total']) ?></span></div>
    <?php elseif ($p['metode'] === 'dompet'): ?><div><span>Sisa saldo</span><span><?= rp(Pos::saldo((int) $p['santri_id'])) ?></span></div><?php endif; ?>
  </div>
  <p style="text-align:center;margin:14px 0 0"><small><?= $tu ? 'Terima kasih. Simpan struk ini sebagai bukti top up.' : 'Terima kasih. Barang yang sudah dibeli tidak dapat dikembalikan.' ?></small></p>
  <?php if ($batal): ?><p class="note err" style="margin-top:12px">Dibatalkan: <?= e($p['alasan_batal']) ?></p><?php endif; ?>
</div>
<?php if ($bisaBatal): ?>
<form method="post" class="card form noprint struk" data-confirm="Batalkan penjualan ini? Stok dan saldo dikembalikan."><?= csrf_field() ?>
  <h2>Batalkan <?= $tu ? 'top up' : 'penjualan' ?></h2><?php if ($tu): ?><p class="note">Saldo deposit agen dikembalikan sebesar modal. Pastikan saldo sudah ditarik kembali dari pelanggan lewat aplikasi agen dan uang dikembalikan.</p><?php endif; ?><?php if ($p['metode'] === 'qris'): ?><p class="note">Pembayaran QRIS tidak dikembalikan otomatis. Kembalikan uang ke pembeli lewat aplikasi merchant.</p><?php endif; ?><label>Alasan <input name="alasan" required placeholder="Salah input, barang dikembalikan..."></label>
  <button class="btn danger">Batalkan</button></form>
<?php endif; ?>
<?php render('Struk', ob_get_clean());
