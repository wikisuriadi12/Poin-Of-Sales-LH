<?php
// Admin/bendahara: saldo deposit agen (isi & koreksi) dan produk/tarif top up e-money.
$bolehProduk = Auth::can('emoney.produk'); $bolehDeposit = Auth::can('emoney.deposit');
if (!$bolehProduk && !$bolehDeposit) { Auth::require('emoney.deposit'); }
$num = static fn(string $k): int => (int) preg_replace('/\D/', '', in_str($k, '0'));
if (is_post()) {
    csrf_check();
    try {
        $aksi = in_str('aksi');
        if ($aksi === 'sumber' && $bolehDeposit) {
            Emoney::simpanSumber(in_str('nama'), $num('saldo_min'), (int) in_str('id', '0') ?: null, Auth::id());
            flash('Saldo deposit disimpan.');
        } elseif ($aksi === 'deposit' && $bolehDeposit) {
            $tipe = in_str('tipe') === 'koreksi' ? 'koreksi' : 'isi';
            $jml = $num('jumlah') * ($tipe === 'koreksi' && in_str('arah') === 'kurang' ? -1 : 1);
            Emoney::ubahDeposit((int) in_str('sumber_id', '0'), $jml, $tipe, in_str('catatan'), Auth::id());
            flash($tipe === 'isi' ? 'Deposit bertambah.' : 'Saldo deposit dikoreksi dan tercatat di jejak audit.', $tipe === 'isi' ? 'ok' : 'warn');
        } elseif ($aksi === 'produk' && $bolehProduk) {
            Emoney::simpanProduk($_POST, (int) in_str('id', '0') ?: null, Auth::id());
            flash('Produk disimpan.');
        } else {
            throw new InvalidArgumentException('Akses ditolak.');
        }
    } catch (InvalidArgumentException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('?p=emoney');
}
$sumber = Emoney::sumber();
$produk = Emoney::produk();
$mutasi = Emoney::mutasi(40);
$edP = ($eid = (int) in_str('produk', '0')) ? Emoney::findProduk($eid) : null;
$edS = ($sid = (int) in_str('sumber', '0')) ? Db::one('SELECT * FROM emoney_sumber WHERE id = ?', [$sid]) : null;
$opsiSumber = array_column($sumber, 'nama', 'id');
$tipeNama = ['isi' => 'Isi deposit', 'pakai' => 'Top up', 'kembali' => 'Batal top up', 'koreksi' => 'Koreksi'];
ob_start(); ?>
<div class="page-h"><div><h1>Deposit &amp; Produk E-Money</h1><p>Saldo deposit agen berkurang otomatis setiap top up (nominal + biaya modal) dan kembali bila top up dibatalkan.</p></div>
  <div class="acts"><a class="btn" href="?p=topup"><?= icon('credit-card') ?> Layani top up</a></div></div>

<div class="card"><h2>Saldo deposit agen</h2>
  <div class="scroll"><table class="rt"><thead><tr><th>Nama</th><th class="r">Saldo</th><th class="r">Batas peringatan</th><th></th></tr></thead><tbody>
  <?php foreach ($sumber as $s): $tipis = (int) $s['saldo'] <= (int) $s['saldo_min']; ?>
    <tr><td><b><?= e($s['nama']) ?></b></td><td class="r" data-label="Saldo"><b style="color:var(--<?= $tipis ? 'err' : 'ok' ?>)"><?= rp($s['saldo']) ?></b><?= $tipis ? ' <span class="badge b-belum">Menipis</span>' : '' ?></td>
      <td class="r" data-label="Batas"><?= rp($s['saldo_min']) ?></td><td class="r"><?php if ($bolehDeposit): ?><a class="btn sm ghost" href="?p=emoney&amp;sumber=<?= (int) $s['id'] ?>#form-sumber">Ubah</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$sumber): ?><tr><td colspan="4" class="empty">Belum ada saldo deposit. Tambahkan dulu, misalnya "Deposit Agen DANA".</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php if ($bolehDeposit): ?>
<div class="grid g2" style="margin-top:16px">
  <?php if ($sumber): ?>
  <form method="post" class="card form" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="aksi" value="deposit">
    <h2>Isi / koreksi deposit</h2>
    <label>Saldo deposit <select name="sumber_id" required><?= options($opsiSumber) ?></select></label>
    <label>Jenis <select name="tipe"><?= options(['isi' => 'Isi deposit (tambah)', 'koreksi' => 'Koreksi']) ?></select></label>
    <label>Arah koreksi <select name="arah"><?= options(['tambah' => 'Tambah', 'kurang' => 'Kurangi']) ?></select></label>
    <label>Nominal <input name="jumlah" data-rupiah inputmode="numeric" required></label>
    <label>Catatan / alasan <input name="catatan" maxlength="200" placeholder="Transfer ke akun agen, selisih saldo..."></label>
    <button class="btn"><?= icon('check') ?> Simpan</button></form>
  <?php endif; ?>
  <form method="post" class="card form" id="form-sumber" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="aksi" value="sumber"><input type="hidden" name="id" value="<?= (int) ($edS['id'] ?? 0) ?>">
    <h2><?= $edS ? 'Ubah saldo deposit' : 'Tambah saldo deposit' ?></h2>
    <label>Nama <input name="nama" required maxlength="80" value="<?= e($edS['nama'] ?? '') ?>" placeholder="Deposit Agen DANA"></label>
    <label>Peringatan bila saldo di bawah <input name="saldo_min" data-rupiah inputmode="numeric" value="<?= (int) ($edS['saldo_min'] ?? 100000) ?>"></label>
    <button class="btn ghost"><?= icon('check') ?> Simpan</button><?php if ($edS): ?> <a class="btn ghost" href="?p=emoney">Batal</a><?php endif; ?></form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:16px"><h2>Produk &amp; tarif</h2>
  <div class="scroll"><table class="rt"><thead><tr><th>Produk</th><th>Jenis</th><th>Deposit</th><th class="r">Admin (pelanggan)</th><th class="r">Biaya modal</th><th class="r">Nominal</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($produk as $x): ?>
    <tr><td><b><?= e($x['nama']) ?></b></td><td data-label="Jenis"><?= e(Emoney::JENIS[$x['jenis']] ?? $x['jenis']) ?></td><td data-label="Deposit"><?= e($x['sumber_nama']) ?></td>
      <td class="r" data-label="Admin"><?= rp($x['biaya_admin']) ?></td><td class="r" data-label="Modal"><?= rp($x['biaya_modal']) ?></td>
      <td class="r" data-label="Nominal"><?= rp($x['nominal_min']) ?> &ndash; <?= rp($x['nominal_maks']) ?></td>
      <td data-label="Status"><?= (int) $x['aktif'] ? '<span class="badge b-aktif">Aktif</span>' : '<span class="badge">Nonaktif</span>' ?></td>
      <td class="r"><?php if ($bolehProduk): ?><a class="btn sm ghost" href="?p=emoney&amp;produk=<?= (int) $x['id'] ?>#form-produk">Ubah</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$produk): ?><tr><td colspan="8" class="empty">Belum ada produk.</td></tr><?php endif; ?>
  </tbody></table></div></div>

<?php if ($bolehProduk && $sumber): ?>
<form method="post" class="card form" id="form-produk" autocomplete="off" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="aksi" value="produk"><input type="hidden" name="id" value="<?= (int) ($edP['id'] ?? 0) ?>">
  <h2><?= $edP ? 'Ubah produk: ' . e($edP['nama']) : 'Tambah produk' ?></h2>
  <label>Nama produk <input name="nama" required maxlength="60" value="<?= e($edP['nama'] ?? '') ?>" placeholder="DANA, OVO, GoPay, Mandiri e-money..."></label>
  <label>Jenis <select name="jenis"><?= options(Emoney::JENIS, $edP['jenis'] ?? 'dompet') ?></select></label>
  <label>Memakai saldo deposit <select name="sumber_id" required><?= options($opsiSumber, (string) ($edP['sumber_id'] ?? '')) ?></select></label>
  <label>Biaya admin dibayar pelanggan <input name="biaya_admin" data-rupiah inputmode="numeric" value="<?= (int) ($edP['biaya_admin'] ?? 2000) ?>"></label>
  <label>Biaya modal dipotong dari deposit <input name="biaya_modal" data-rupiah inputmode="numeric" value="<?= (int) ($edP['biaya_modal'] ?? 1000) ?>"></label>
  <label>Nominal minimal <input name="nominal_min" data-rupiah inputmode="numeric" value="<?= (int) ($edP['nominal_min'] ?? 10000) ?>"></label>
  <label>Nominal maksimal <input name="nominal_maks" data-rupiah inputmode="numeric" value="<?= (int) ($edP['nominal_maks'] ?? 2000000) ?>"></label>
  <label class="chk"><input type="checkbox" name="aktif" value="1" <?= !$edP || (int) $edP['aktif'] ? 'checked' : '' ?>> Aktif (tampil di kasir)</label>
  <button class="btn"><?= icon('check') ?> Simpan produk</button><?php if ($edP): ?> <a class="btn ghost" href="?p=emoney">Batal</a><?php endif; ?></form>
<?php endif; ?>

<div class="card" style="margin-top:16px"><h2>Mutasi deposit terakhir</h2>
  <div class="scroll"><table class="rt"><thead><tr><th>Waktu</th><th>Deposit</th><th>Jenis</th><th class="r">Jumlah</th><th class="r">Saldo akhir</th><th>Catatan</th><th>Petugas</th></tr></thead><tbody>
  <?php foreach ($mutasi as $m): ?>
    <tr><td><?= e(substr($m['created_at'], 0, 16)) ?></td><td data-label="Deposit"><?= e($m['sumber_nama']) ?></td><td data-label="Jenis"><?= e($tipeNama[$m['tipe']] ?? $m['tipe']) ?></td>
      <td class="r" data-label="Jumlah"><b style="color:var(--<?= (int) $m['jumlah'] > 0 ? 'ok' : 'err' ?>)"><?= ((int) $m['jumlah'] > 0 ? '+' : '') . rp($m['jumlah']) ?></b></td>
      <td class="r" data-label="Saldo akhir"><?= rp($m['saldo_setelah']) ?></td><td data-label="Catatan"><?= e($m['catatan'] ?: '-') ?></td><td data-label="Petugas"><?= e($m['petugas'] ?: '-') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$mutasi): ?><tr><td colspan="7" class="empty">Belum ada mutasi.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php render('Deposit & Produk E-Money', ob_get_clean());
