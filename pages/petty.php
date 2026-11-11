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
            Petty::belanja($num('jumlah'), in_str('kategori'), in_str('keterangan'), in_str('pemasok'), Auth::id());
            flash('Belanja ' . rp($num('jumlah')) . ' dicatat. Sisa saldo ' . rp(Petty::saldo()) . '.');
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
  <div class="acts"><?php if ($bolehBelanja): ?><a class="btn" href="?p=barang_masuk&amp;jenis=petty"><?= icon('plus') ?> Belanja barang (masuk stok)</a><?php endif; ?><a class="btn ghost" href="?p=petty&amp;csv=1&amp;dari=<?= e($dari) ?>&amp;sampai=<?= e($sampai) ?>&amp;tipe=<?= e($tipe) ?>"><?= icon('download') ?> Unduh CSV</a></div></div>
<div class="kpis"><?php foreach ($kpi as [$lb, $nilai, $ic, $c]): ?><div class="kpi"><div class="chip <?= e($c) ?>"><?= icon($ic, 22) ?></div><div><span><?= e($lb) ?></span><b><?= e($nilai) ?></b></div></div><?php endforeach; ?></div>
<div class="grid g2">
  <?php if ($bolehIsi): ?><form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="aksi" value="isi"><h2>Isi saldo</h2>
    <label>Jumlah (Rp) <input name="jumlah" inputmode="numeric" required placeholder="mis. 500000"></label>
    <label>Keterangan <input name="keterangan" maxlength="200" placeholder="mis. Pengambilan dari kas besar"></label>
    <div class="acts"><button class="btn"><?= icon('check') ?> Tambah saldo</button></div></form><?php endif; ?>
  <?php if ($bolehBelanja): ?><form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="aksi" value="belanja"><h2>Catat belanja (bukan stok)</h2>
    <label>Kategori <select name="kategori"><?php foreach (Petty::KATEGORI as $k => $v): if ($k === 'Belanja stok') { continue; } ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label>Jumlah (Rp) <input name="jumlah" inputmode="numeric" required></label>
    <label>Apa yang dibeli <input name="keterangan" maxlength="200" required placeholder="mis. Kertas struk 5 roll"></label>
    <label>Toko / supplier <input name="pemasok" maxlength="100" placeholder="Opsional"></label>
    <div class="acts"><button class="btn"><?= icon('check') ?> Kurangi saldo</button></div></form><?php endif; ?>
</div>
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
