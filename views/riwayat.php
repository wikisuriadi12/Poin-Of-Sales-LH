<?php
require_can('riwayat');
expire_due_orders();
$day = valid_date(query('tanggal')) ? query('tanggal') : date('Y-m-d');
$orders = sql('SELECT o.id,o.nomor,o.pondok,o.status,o.metode,o.total,o.catatan,o.created_at,u.username FROM orders o JOIN users u ON u.id=o.created_by WHERE o.created_at BETWEEN ? AND ? ORDER BY o.id DESC LIMIT 500', [$day . ' 00:00:00', $day . ' 23:59:59'])->fetchAll();
$items = [];
if ($orders) {
    $ids = array_map(fn($o) => (int)$o['id'], $orders);
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (sql("SELECT order_id,nama,qty,subtotal FROM order_items WHERE order_id IN ($in) ORDER BY id", $ids)->fetchAll() as $it) $items[(int)$it['order_id']][] = $it;
}
$sum = ['omzet' => 0, 'jumlah' => 0, 'tunai' => 0, 'qris' => 0, 'saldo' => 0];
foreach ($orders as $o) {
    if ($o['status'] !== 'lunas') continue;
    $sum['omzet'] += (int)$o['total'];
    $sum['jumlah']++;
    $sum[$o['metode']] += (int)$o['total'];
}
$labels = ['tunai' => 'Tunai', 'qris' => 'BNI QRIS', 'saldo' => 'Saldo Santri'];
?>
<h1>Riwayat penjualan</h1>
<form method="get" class="inline">
  <input type="hidden" name="page" value="riwayat">
  <label>Tanggal <input type="date" name="tanggal" value="<?= esc($day) ?>"></label>
  <button>Tampilkan</button>
</form>
<dl class="stats">
  <div><dt>Omzet (lunas)</dt><dd><?= rupiah($sum['omzet']) ?></dd></div>
  <div><dt>Transaksi lunas</dt><dd><?= $sum['jumlah'] ?></dd></div>
  <?php foreach ($labels as $k => $label): ?><div><dt><?= esc($label) ?></dt><dd><?= rupiah($sum[$k]) ?></dd></div><?php endforeach; ?>
</dl>
<section class="card">
  <?php if (!$orders): ?><p class="muted">Belum ada transaksi pada tanggal ini.</p><?php endif; ?>
  <?php foreach ($orders as $o): ?>
    <details class="order">
      <summary>
        <span class="mono"><?= esc(substr((string)$o['created_at'], 11, 5)) ?></span>
        <span class="mono"><?= esc($o['nomor']) ?></span>
        <span class="pill st-<?= esc($o['status']) ?>"><?= esc($o['status']) ?></span>
        <span class="muted"><?= esc(ucfirst((string)$o['pondok'])) ?> · <?= esc($labels[$o['metode']] ?? $o['metode']) ?> · <?= esc($o['username']) ?></span>
        <strong><?= rupiah($o['total']) ?></strong>
      </summary>
      <ul>
        <?php foreach ($items[(int)$o['id']] ?? [] as $it): ?><li><?= (int)$it['qty'] ?> × <?= esc($it['nama']) ?> <span class="muted">(<?= rupiah($it['subtotal']) ?>)</span></li><?php endforeach; ?>
      </ul>
      <?php if ($o['catatan']): ?><p class="muted"><?= esc($o['catatan']) ?></p><?php endif; ?>
      <?php if ($o['status'] === 'menunggu' && can('kasir')): ?>
        <form method="post"><?= token_field() ?><input type="hidden" name="nomor" value="<?= esc($o['nomor']) ?>"><button name="action" value="cancel_order" class="ghost">Batalkan pesanan menunggu</button></form>
      <?php endif; ?>
    </details>
  <?php endforeach; ?>
</section>
