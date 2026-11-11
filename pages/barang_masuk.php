<?php
Auth::require('barang.masuk');
// Kasir (tanpa gudang.edit): hanya ke Market, tunai, dan tanpa harga beli.
$penuh = Auth::can('gudang.edit');
$modal = Auth::can('harga_beli');
$lokasi = $penuh ? Pos::LOKASI : [Pos::TOKO => Pos::LOKASI[Pos::TOKO]];
$unit = isset($lokasi[in_str('lok')]) ? in_str('lok') : array_key_first($lokasi);
$barang = Db::all('SELECT id, kode, nama, satuan, harga_beli FROM barang WHERE aktif = 1 ORDER BY nama');
$pemasokList = Gudang::daftarPemasok();
if (is_post()) {
    csrf_check();
    $lok = isset($lokasi[in_str('lok')]) ? in_str('lok') : array_key_first($lokasi);
    $ids = (array) ($_POST['barang_id'] ?? []); $qs = (array) ($_POST['qty'] ?? []); $hs = (array) ($_POST['harga_beli'] ?? []);
    $baris = []; $baru = [];
    $angka = static fn($v) => (int) preg_replace('/\D/', '', (string) $v);
    try {
    foreach ($ids as $i => $bid) {
        $hb = $modal ? $angka($hs[$i] ?? '0') : 0;
        if ($bid === 'baru') {
            if (!Auth::can('barang.tambah')) { throw new InvalidArgumentException('Akses tambah barang ditolak.'); }
            $bid = Gudang::barangBaruCepat((string) ($_POST['nama_baru'][$i] ?? ''), (string) ($_POST['satuan_baru'][$i] ?? ''), $angka($_POST['jual_baru'][$i] ?? '0'), $hb, (string) ($_POST['kat_baru'][$i] ?? ''), Auth::id());
            $baru[] = $bid;
        }
        $baris[] = [(int) $bid, $angka($qs[$i] ?? '0'), $hb];
    }
        $faktur = in_str('faktur');
        $bolehPetty = $penuh && Auth::can('petty.belanja');
        $jenis = $penuh && in_array(in_str('jenis'), ['tunai', 'hutang', 'konsinyasi', 'petty'], true) ? in_str('jenis') : 'tunai';
        if ($jenis === 'petty' && !$bolehPetty) { throw new InvalidArgumentException('Akses Petty Cash ditolak.'); }
        $n = Gudang::masukBanyak($lok, in_str('pemasok'), trim(($faktur !== '' ? 'Faktur ' . $faktur . '. ' : '') . in_str('catatan')), $baris, Auth::id(), $jenis, in_str('jatuh_tempo') ?: null, $faktur);
        flash($n . ' item masuk ke stok ' . Pos::LOKASI[$lok] . ' dari ' . trim(in_str('pemasok')) . ['tunai' => '.', 'hutang' => ' (dicatat sebagai hutang).', 'konsinyasi' => ' (konsinyasi: dibayar setelah terjual).', 'petty' => ' (dibayar dari Petty Cash, sisa saldo ' . rp(Petty::saldo()) . ').'][$jenis]);
        redirect('?p=barang_masuk&lok=' . $lok);
    } catch (InvalidArgumentException $ex) { Gudang::urungkanBarangBaru($baru); flash($ex->getMessage(), 'err'); $unit = $lok; }
}
$opsi = '<option value="">- pilih barang -</option>' . (Auth::can('barang.tambah') ? '<option value="baru">+ Barang baru (tambah manual)</option>' : '');
foreach ($barang as $b) { $opsi .= '<option value="' . (int) $b['id'] . '"' . ($modal ? ' data-hb="' . (int) $b['harga_beli'] . '"' : '') . '>' . e($b['nama'] . ' (' . $b['kode'] . ')') . '</option>'; }
$terakhir = Gudang::laporanMasuk(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'), '', '');
ob_start(); ?>
<div class="page-h"><div><h1>Barang Masuk</h1><p>Catat barang datang dari supplier. Stok langsung bertambah di lokasi yang dipilih.</p></div>
  <div class="acts"><a class="btn ghost" href="?p=laporan_pos&amp;tab=masuk">Laporan barang masuk</a><a class="btn ghost" href="?p=barang">Daftar barang</a></div></div>
<form method="post" class="card form" id="bm"><?= csrf_field() ?>
  <div class="grid g3">
    <label>Masuk ke <select name="lok"><?= options($lokasi, $unit) ?></select></label>
    <label>Nama supplier <input name="pemasok" list="dl-pemasok" required maxlength="100" value="<?= e(in_str('pemasok')) ?>" placeholder="Ketik atau pilih supplier"></label>
    <label>No. faktur / surat jalan <input name="faktur" maxlength="60" placeholder="Opsional" value="<?= e(in_str('faktur')) ?>"></label>
    <label>Jenis pembelian <select name="jenis" id="bm-jenis"><?= options($penuh ? ['tunai' => 'Tunai / lunas'] + (Auth::can('petty.belanja') ? ['petty' => 'Petty Cash (potong saldo kas kecil)'] : []) + ['hutang' => 'Hutang (bayar nanti)', 'konsinyasi' => 'Konsinyasi (titipan)'] : ['tunai' => 'Tunai / lunas'], in_str('jenis') ?: 'tunai') ?></select></label>
    <label id="bm-tempo" style="display:none">Jatuh tempo hutang <input type="date" name="jatuh_tempo" value="<?= e(in_str('jatuh_tempo')) ?>"></label>
  </div>
  <p class="mut" id="bm-info" style="margin:0 0 8px;display:none"></p>
  <datalist id="kat"><?php foreach (Gudang::kategori() as $k): ?><option value="<?= e($k) ?>"><?php endforeach; ?></datalist>
  <datalist id="dl-pemasok"><?php foreach ($pemasokList as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist>
  <div class="scroll"><table class="rt" id="bm-t">
    <thead><tr><th>Barang</th><th class="r">Jumlah</th><?php if ($modal): ?><th class="r">Harga beli satuan</th><?php endif; ?></tr></thead>
    <tbody></tbody></table></div>
  <div class="acts" style="margin:8px 0"><button type="button" class="btn ghost sm" id="bm-tambah"><?= icon('plus') ?> Tambah baris</button></div>
  <label>Catatan <input name="catatan" maxlength="200" placeholder="Opsional"></label>
  <div class="acts"><button class="btn"><?= icon('check') ?> Simpan barang masuk</button></div>
</form>
<div class="card" style="margin-top:16px"><h2>Barang masuk 30 hari terakhir</h2>
  <div class="scroll"><table class="rt"><thead><tr><th>Waktu</th><th>Supplier</th><th>Barang</th><th>Lokasi</th><th class="r">Jumlah</th><?php if ($modal): ?><th class="r">Harga beli</th><?php endif; ?></tr></thead><tbody>
  <?php foreach (array_slice($terakhir, 0, 30) as $r): ?>
    <tr><td><?= e(substr($r['created_at'], 0, 16)) ?></td><td data-label="Supplier"><?= e($r['pemasok'] ?: '-') ?></td><td data-label="Barang"><b><?= e($r['nama']) ?></b></td><td data-label="Lokasi"><?= e(Pos::LOKASI[$r['unit']] ?? $r['unit']) ?></td><td class="r" data-label="Jumlah"><?= (int) $r['qty'] ?> <?= e($r['satuan']) ?></td><?php if ($modal): ?><td class="r" data-label="Harga"><?= rp($r['harga_beli']) ?></td><?php endif; ?></tr>
  <?php endforeach; ?>
  <?php if (!$terakhir): ?><tr><td colspan="6" class="empty">Belum ada barang masuk.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<script>
(function () {
  var saldoPetty = <?= json_encode(Auth::can('petty.view') ? rp(Petty::saldo()) : '-') ?>, opsi = <?= json_encode($opsi) ?>, modal = <?= $modal ? 'true' : 'false' ?>, tb = document.querySelector('#bm-t tbody');
  function baris() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select name="barang_id[]">' + opsi + '</select>' +
      '<div class="baru" style="display:none;margin-top:6px;gap:6px;flex-wrap:wrap"><input name="nama_baru[]" maxlength="150" placeholder="Nama barang baru"><input name="kat_baru[]" maxlength="60" list="kat" placeholder="Kategori" style="width:120px"><input name="satuan_baru[]" value="pcs" style="width:70px" placeholder="Satuan"><input name="jual_baru[]" inputmode="numeric" style="width:120px" placeholder="Harga jual"></div></td><td class="r"><input name="qty[]" inputmode="numeric" style="width:90px;text-align:right"></td>' +
      (modal ? '<td class="r"><input name="harga_beli[]" inputmode="numeric" style="width:120px;text-align:right" placeholder="otomatis"></td>' : '');
    tr.querySelector('select').addEventListener('change', function () {
      tr.querySelector('.baru').style.display = this.value === 'baru' ? 'flex' : 'none';
      var o = this.options[this.selectedIndex], h = tr.querySelector('[name="harga_beli[]"]');
      if (h && o && o.dataset.hb && !h.value) { h.value = o.dataset.hb; }
    });
    tb.appendChild(tr);
  }
  var jn = document.getElementById('bm-jenis'), tp = document.getElementById('bm-tempo'), inf = document.getElementById('bm-info');
  function jenis() {
    tp.style.display = jn.value === 'hutang' ? '' : 'none';
    inf.style.display = jn.value === 'tunai' ? 'none' : '';
    inf.textContent = jn.value === 'petty' ? 'Petty Cash: harga beli wajib diisi. Total belanja memotong saldo Petty Cash (saldo sekarang ' + saldoPetty + ') dan ditolak bila saldo kurang.' : jn.value === 'hutang' ? 'Hutang: harga beli wajib diisi. Total tercatat sebagai hutang ke supplier dan dibayar lewat menu Hutang Supplier.' : (jn.value === 'konsinyasi' ? 'Konsinyasi: harga beli = harga setor per unit ke supplier. Tidak ada hutang saat barang masuk; hutang muncul hanya untuk barang yang terjual.' : '');
  }
  jn.addEventListener('change', jenis); jenis();
  for (var i = 0; i < 5; i++) { baris(); }
  document.getElementById('bm-tambah').addEventListener('click', baris);
})();
</script>
<?php render('Barang Masuk', ob_get_clean());
