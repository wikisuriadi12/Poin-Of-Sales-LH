<?php
Auth::require('petty.view');
$bolehBelanja = Auth::can('petty.belanja'); $bolehIsi = Auth::can('petty.isi');
$num = static fn(string $k): int => (int) preg_replace('/\D/', '', in_str($k, '0'));
if (is_post()) {
    csrf_check();
    try {
        $aksi = in_str('aksi');
        if ($aksi === 'isi' && $bolehIsi) {
            Petty::isi($num('jumlah'), in_str('keterangan'), Auth::id());
            flash('Saldo Petty Cash bertambah ' . rp($num('jumlah')) . '.');
        } elseif ($aksi === 'belanja' && $bolehBelanja) {
            $ids = (array) ($_POST['barang_id'] ?? []); $kets = (array) ($_POST['ket'] ?? []); $qs = (array) ($_POST['qty'] ?? []); $hs = (array) ($_POST['harga'] ?? []);
            $stok = []; $lain = []; $kat = in_str('kategori');
            foreach ($ids as $i => $bid) {
                $q = (int) preg_replace('/\D/', '', (string) ($qs[$i] ?? '0')); $h = (int) preg_replace('/\D/', '', (string) ($hs[$i] ?? '0')); $kt = trim((string) ($kets[$i] ?? ''));
                if ((int) $bid > 0) { $stok[] = [(int) $bid, $q, $h]; }
                elseif ($kt !== '' || $q > 0 || $h > 0) { $lain[] = [$kt, max($q, 1) * $h, $kat]; }
            }
            $lok = isset(Pos::LOKASI[in_str('lok')]) ? in_str('lok') : Pos::GUDANG;
            if ($stok) {
                $n = Gudang::masukBanyak($lok, in_str('pemasok'), trim((in_str('faktur') !== '' ? 'Faktur ' . in_str('faktur') . '. ' : '') . 'Belanja Petty Cash'), $stok, Auth::id(), 'petty', null, in_str('faktur'), $lain);
                flash($n . ' barang masuk stok ' . Pos::LOKASI[$lok] . ($lain ? ' dan ' . count($lain) . ' belanja lain dicatat' : '') . '. Sisa saldo Petty Cash ' . rp(Petty::saldo()) . '.');
            } else {
                $n = Petty::belanjaBanyak($lain, in_str('pemasok'), Auth::id());
                flash($n . ' belanja dicatat (bukan stok). Sisa saldo ' . rp(Petty::saldo()) . '.');
            }
        } elseif ($aksi === 'koreksi' && $bolehIsi) {
            Petty::koreksi($num('jumlah') * (in_str('arah') === 'kurang' ? -1 : 1), in_str('alasan'), Auth::id());
            flash('Saldo dikoreksi dan tercatat di jejak audit.', 'warn');
        } elseif ($aksi === 'batal' && $bolehIsi) {
            Petty::batalkan((int) in_str('id', '0'), Auth::id());
            flash('Catatan dibatalkan.', 'warn');
        } else { throw new InvalidArgumentException('Akses ditolak.'); }
    } catch (InvalidArgumentException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('?p=petty');
}
$dari = Domain::validDate(in_str('dari')) ? in_str('dari') : date('Y-m-01');
$sampai = Domain::validDate(in_str('sampai')) ? in_str('sampai') : date('Y-m-d');
$tipe = in_array(in_str('tipe'), ['isi', 'belanja', 'belanja_stok', 'koreksi'], true) ? in_str('tipe') : '';
$saldo = Petty::saldo();
$rk = Petty::ringkasan($dari, $sampai);
$rows = Petty::daftar($dari, $sampai, $tipe);
$namaTipe = ['isi' => 'Isi saldo', 'belanja' => 'Belanja', 'belanja_stok' => 'Belanja stok', 'koreksi' => 'Koreksi'];
if (in_str('csv') === '1') {
    csv_out('petty-cash-' . $dari . '-sd-' . $sampai . '.csv', ['Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Supplier/toko', 'Jumlah', 'Saldo setelah', 'Petugas', 'Status'],
        array_map(static fn($r) => [$r['tanggal'], $namaTipe[$r['tipe']], $r['kategori'], $r['keterangan'], $r['pemasok'], $r['jumlah'], $r['saldo_setelah'], $r['petugas'], $r['batal'] ? 'Dibatalkan' : ''], $rows));
    exit;
}
$kpi = [['Saldo Petty Cash', rp($saldo), 'wallet', 'c-ok'], ['Uang masuk periode', rp($rk['isi']), 'trending-up', 'c-info'],
    ['Belanja stok', rp(-$rk['belanja_stok']), 'box', ''], ['Belanja lain', rp(-$rk['belanja']), 'cart', 'c-acc']];
ob_start(); ?>
<div class="page-h"><div><h1>Petty Cash</h1><p>Uang kas kecil untuk belanja. Setiap belanja memotong saldo, termasuk belanja barang yang langsung masuk stok.</p></div>
  <div class="acts"><a class="btn ghost" href="?p=petty&amp;csv=1&amp;dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>&amp;tipe=<?= e($tipe) ?>"><?= icon('download') ?> Unduh CSV</a></div></div>
<div class="kpis"><?php foreach ($kpi as [$lb, $nilai, $ic, $c]): ?><div class="kpi"><div class="chip <?= e($c) ?>"><?= icon($ic, 22) ?></div><div><span><?= e($lb) ?></span><b><?= e($nilai) ?></b></div></div><?php endforeach; ?></div>
<div class="grid g2">
  <?php if ($bolehIsi): ?><form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="aksi" value="isi"><h2>Isi saldo</h2>
    <label>Jumlah (Rp) <input name="jumlah" inputmode="numeric" required placeholder="mis. 500000"></label>
    <label>Keterangan <input name="keterangan" maxlength="200" placeholder="mis. Pengambilan dari kas besar"></label>
    <div class="acts"><button class="btn"><?= icon('check') ?> Tambah saldo</button></div></form><?php endif; ?>
  <?php if ($bolehBelanja): ?><div class="card"><h2>Catat belanja</h2><p class="mut">Barang yang dipilih dari daftar barang langsung masuk stok. Baris tanpa barang (ATK, kebersihan, dll) hanya mengurangi saldo.</p><p class="mut" style="margin-top:-6px">Saldo sekarang <b><?= rp($saldo) ?></b>. Total belanja akan memotong saldo ini.</p></div><?php endif; ?>
</div>
<?php if ($bolehBelanja): ?>
<form method="post" class="card form" id="pb" style="margin-top:16px"><?= csrf_field() ?><input type="hidden" name="aksi" value="belanja"><h2>Form belanja</h2>
  <div class="grid g3">
    <label>Toko / supplier <input name="pemasok" list="dl-pemasok" maxlength="100" placeholder="Wajib bila ada barang masuk stok"></label>
    <label>No. nota / faktur <input name="faktur" maxlength="60" placeholder="Opsional"></label>
    <label>Barang stok masuk ke <select name="lok"><?= options(Pos::LOKASI, Pos::GUDANG) ?></select></label>
    <label>Kategori untuk baris non-stok <select name="kategori"><?php foreach (Petty::KATEGORI as $k => $v): if ($k === 'Belanja stok') { continue; } ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
  </div>
  <datalist id="dl-pemasok"><?php foreach (Gudang::daftarPemasok() as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist>
  <div class="scroll"><table class="rt" id="pb-t"><thead><tr><th>Barang (masuk stok)</th><th>Atau keterangan non-stok</th><th class="r">Jumlah</th><th class="r">Harga satuan</th><th class="r">Subtotal</th></tr></thead><tbody></tbody>
    <tfoot><tr><th colspan="4" class="r">Total belanja (memotong saldo)</th><th class="r" id="pb-total">Rp 0</th></tr></tfoot></table></div>
  <div class="acts" style="margin:8px 0"><button type="button" class="btn ghost sm" id="pb-tambah"><?= icon('plus') ?> Tambah baris</button></div>
  <div class="acts"><button class="btn"><?= icon('check') ?> Simpan belanja dan kurangi saldo</button></div>
</form>
<?php
$opsiBarang = '<option value="0">- bukan stok / ketik keterangan -</option>';
foreach (Db::all('SELECT id, kode, nama, harga_beli FROM barang WHERE aktif = 1 ORDER BY nama') as $b) { $opsiBarang .= '<option value="' . (int) $b['id'] . '" data-hb="' . (int) $b['harga_beli'] . '">' . e($b['nama'] . ' (' . $b['kode'] . ')') . '</option>'; }
?>
<script>
(function () {
  var opsi = <?= json_encode($opsiBarang) ?>, tb = document.querySelector('#pb-t tbody'), tot = document.getElementById('pb-total');
  function n(v) { return parseInt(String(v || '').replace(/\D/g, ''), 10) || 0; }
  function rp(v) { return 'Rp ' + v.toLocaleString('id-ID'); }
  function hitung() {
    var t = 0;
    tb.querySelectorAll('tr').forEach(function (tr) {
      var q = n(tr.querySelector('[name="qty[]"]').value), h = n(tr.querySelector('[name="harga[]"]').value), st = (q || (h ? 1 : 0)) * h;
      tr.querySelector('.sub').textContent = rp(st); t += st;
    });
    tot.textContent = rp(t);
  }
  function baris() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select name="barang_id[]">' + opsi + '</select></td><td><input name="ket[]" maxlength="150" placeholder="mis. Kertas struk 5 roll"></td>' +
      '<td class="r"><input name="qty[]" inputmode="numeric" style="width:80px;text-align:right"></td><td class="r"><input name="harga[]" inputmode="numeric" style="width:120px;text-align:right"></td><td class="r sub">Rp 0</td>';
    var sel = tr.querySelector('select'), ket = tr.querySelector('[name="ket[]"]');
    sel.addEventListener('change', function () {
      var o = sel.options[sel.selectedIndex], h = tr.querySelector('[name="harga[]"]');
      ket.disabled = sel.value !== '0'; if (sel.value !== '0') { ket.value = ''; if (o.dataset.hb && !h.value) { h.value = o.dataset.hb; } }
      hitung();
    });
    tr.addEventListener('input', hitung);
    tb.appendChild(tr);
  }
  for (var i = 0; i < 5; i++) { baris(); }
  document.getElementById('pb-tambah').addEventListener('click', baris);
})();
</script>
<?php endif; ?>
<div class="card" style="margin-top:16px"><h2>Riwayat Petty Cash</h2>
  <form method="get" class="filters"><input type="hidden" name="p" value="petty">
    <label>Dari <input type="date" name="dari" value="<?= e($dari) ?>"></label><label>Sampai <input type="date" name="sampai" value="<?= e($sampai) ?>"></label>
    <select name="tipe" aria-label="Jenis"><option value="">Semua jenis</option><?php foreach ($namaTipe as $k => $v): ?><option value="<?= e($k) ?>" <?= $tipe === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <button class="btn ghost"><?= icon('search') ?> Tampilkan</button></form>
  <?php if ($rk['kategori']): ?><p class="mut">Belanja per kategori: <?php $o = []; foreach ($rk['kategori'] as $k => $v) { $o[] = e($k) . ' ' . rp(-$v); } echo implode(' · ', $o); ?></p><?php endif; ?>
  <div class="scroll"><table class="rt"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="r">Jumlah</th><th class="r">Saldo</th><th>Petugas</th><?php if ($bolehIsi): ?><th></th><?php endif; ?></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr<?= $r['batal'] ? ' style="opacity:.55"' : '' ?>>
    <td data-label="Tanggal"><?= e(date('d/m/Y', strtotime($r['tanggal']))) ?></td><td data-label="Jenis"><span class="badge <?= $r['jumlah'] > 0 ? 'b-lunas' : 'b-sebagian' ?>"><?= e($namaTipe[$r['tipe']]) ?></span><?= $r['batal'] ? ' <small class="mut">dibatalkan</small>' : '' ?></td>
    <td data-label="Keterangan"><?= e($r['keterangan']) ?><?php if ($r['pemasok'] !== ''): ?><br><small class="mut"><?= e($r['pemasok']) ?><?= $r['kategori'] !== '' ? ' · ' . e($r['kategori']) : '' ?></small><?php endif; ?></td>
    <td class="r" data-label="Jumlah"><b><?= ($r['jumlah'] > 0 ? '+' : '-') . rp(abs($r['jumlah'])) ?></b></td><td class="r" data-label="Saldo"><?= rp($r['saldo_setelah']) ?></td><td data-label="Petugas"><?= e($r['petugas'] ?? '-') ?></td>
    <?php if ($bolehIsi): ?><td><?php if (!$r['batal'] && in_array($r['tipe'], ['isi', 'belanja'], true)): ?><form method="post" onsubmit="return confirm('Batalkan catatan ini? Saldo akan dikembalikan.')"><?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn ghost sm">Batalkan</button></form><?php endif; ?></td><?php endif; ?></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="empty">Belum ada catatan pada periode ini.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php if ($bolehIsi): ?><details class="card" style="margin-top:16px"><summary><b>Koreksi saldo (selisih hitung uang fisik)</b></summary>
  <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="aksi" value="koreksi"><div class="grid g3">
    <label>Arah <select name="arah"><option value="tambah">Saldo bertambah</option><option value="kurang">Saldo berkurang</option></select></label>
    <label>Jumlah (Rp) <input name="jumlah" inputmode="numeric" required></label><label>Alasan <input name="alasan" maxlength="180" required placeholder="mis. Selisih hitung fisik"></label></div>
  <div class="acts"><button class="btn ghost">Simpan koreksi</button></div></form></details><?php endif; ?>
<?php render('Petty Cash', ob_get_clean());
