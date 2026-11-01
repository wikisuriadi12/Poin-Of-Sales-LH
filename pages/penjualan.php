<?php
Auth::require('penjualan');
$scope = null;
$dari = Domain::validDate(in_str('dari')) ? in_str('dari') : date('Y-m-01');
$sampai = Domain::validDate(in_str('sampai')) ? in_str('sampai') : date('Y-m-d');
$unit = '';
$metode = in_array(in_str('metode'), ['tunai', 'dompet', 'qris', 'hutang'], true) ? in_str('metode') : '';
$jenisNama = ['barang' => 'Barang', 'topup' => 'Top up'];
$jenis = isset($jenisNama[in_str('jenis')]) ? in_str('jenis') : '';
$milik = Pos::hanyaKasir(); // kasir: hanya transaksinya sendiri; admin/bendahara/pimpinan: semua
$rows = Pos::daftar(['dari' => $dari, 'sampai' => $sampai, 'unit' => $unit, 'metode' => $metode, 'user' => $milik, 'jenis' => $jenis], $scope, 300);
if (in_str('csv') === '1') {
    csv_out('penjualan-' . $dari . '-' . $sampai . '.csv', ['No struk', 'Tanggal', 'Jenis', 'Pelanggan', 'Metode', 'Total', 'Status', 'Kasir'],
        array_map(static fn($r) => [$r['no_struk'], $r['tanggal'], $jenisNama[$r['jenis'] ?? 'barang'] ?? '', $r['santri_nama'] ?? ($r['pelanggan'] ?? ''), $r['metode'], $r['total'], $r['dibatalkan'] ? 'batal' : 'sah', $r['kasir'] ?? ''], $rows));
    exit;
}
LaporanPos::$kasir = $milik;
$rTu = LaporanPos::ringkasan($dari, $sampai);
$rk = Pos::ringkas($dari, $sampai, $unit ?: null, $milik);
$top = Pos::terlaris($dari, $sampai, $unit ?: null, 8, $milik);
$hari = Pos::perHari(7, $unit ?: null, $milik);
$lbl = [];
foreach ($hari as $d => $v) { $lbl[substr($d, 8) . '/' . substr($d, 5, 2)] = $v; }
$topMap = [];
foreach ($top as $t) { $topMap[$t['nama']] = (int) $t['qty']; }
ob_start(); ?>
<div class="page-h"><div><h1>Penjualan Koperasi</h1><p><?= $milik ? 'Riwayat transaksi Anda dan omzetnya' : 'Riwayat transaksi semua kasir' . (Auth::can('harga_beli') ? ', omzet, dan laba kotor' : ' dan omzet') ?>.</p></div>
  <div class="acts"><a class="btn ghost" href="?<?= e(http_build_query(['p' => 'penjualan', 'dari' => $dari, 'sampai' => $sampai, 'unit' => $unit, 'metode' => $metode, 'jenis' => $jenis, 'csv' => 1])) ?>"><?= icon('download') ?> Ekspor CSV</a><?php if (Auth::can('pos')): ?><a class="btn" href="?p=pos"><?= icon('cart') ?> Ke kasir</a><?php endif; ?></div></div>
<div class="card">
  <form method="get" class="filters"><input type="hidden" name="p" value="penjualan">
    <input type="date" name="dari" value="<?= e($dari) ?>"><input type="date" name="sampai" value="<?= e($sampai) ?>">
    <select name="jenis"><option value="">Semua transaksi</option><?= options($jenisNama, $jenis) ?></select>
    <select name="metode"><option value="">Semua metode</option><?= options(LaporanPos::CARA_BAYAR, $metode) ?></select>
    <button class="btn"><?= icon('search') ?> Tampilkan</button>
  </form>
  <div class="kpis" style="margin-bottom:0">
    <div class="kpi"><div class="chip"><?= icon('receipt', 22) ?></div><div><span>Transaksi barang</span><b><?= $rk['n'] ?></b></div></div>
    <div class="kpi"><div class="chip c-ok"><?= icon('wallet', 22) ?></div><div><span>Omzet barang</span><b><?= rp($rk['omzet']) ?></b></div></div>
    <?php if (Auth::can('harga_beli')): ?><div class="kpi"><div class="chip c-info"><?= icon('trending-up', 22) ?></div><div><span>Laba kotor</span><b><?= rp($rk['laba']) ?></b></div></div><?php endif; ?>
    <div class="kpi"><div class="chip c-info"><?= icon('credit-card', 22) ?></div><div><span>Top up e-money</span><b><?= (int) $rTu['topup_n'] ?> &middot; <?= rp($rTu['topup_total']) ?></b></div></div>
  </div>
</div>
<div class="grid g2" style="margin-bottom:16px">
  <div class="card"><h2>Omzet 7 hari terakhir</h2><?= Charts::bars($lbl) ?></div>
  <div class="card"><h2>Barang terlaris</h2><?= $topMap ? Charts::hbars($topMap, static fn($v) => $v . ' terjual') : '<div class="empty">Belum ada penjualan.</div>' ?></div>
</div>
<div class="card"><h2>Transaksi</h2>
  <div class="scroll"><table class="rt">
    <thead><tr><th>No. struk</th><th>Waktu</th><th>Jenis</th><th>Pelanggan</th><th>Metode</th><th class="r">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $isTu = ($r['jenis'] ?? 'barang') === 'topup'; ?>
      <tr><td><b><?= e($r['no_struk']) ?></b></td><td data-label="Waktu"><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
        <td data-label="Jenis"><?= $isTu ? '<span class="badge b-sebagian">Top up</span>' : '<span class="badge">Barang</span>' ?></td>
        <td data-label="Pelanggan"><?= e($r['santri_nama'] ?? (($r['pelanggan'] ?? '') !== '' ? $r['pelanggan'] : 'Umum')) ?></td><td data-label="Metode"><?= e(LaporanPos::CARA_BAYAR[$r['metode']] ?? $r['metode']) ?></td>
        <td class="r" data-label="Total"><?= rp($r['total']) ?></td>
        <td data-label="Status"><?= $r['dibatalkan'] ? '<span class="badge b-belum">Batal</span>' : '<span class="badge b-lunas">Sah</span>' ?></td>
        <td class="r"><a class="btn sm ghost" href="?p=struk&amp;id=<?= (int) $r['id'] ?>">Struk</a></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="empty">Tidak ada transaksi pada rentang ini.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?= rincian_bayar($rows) ?>
</div>
<?php render('Penjualan', ob_get_clean());
