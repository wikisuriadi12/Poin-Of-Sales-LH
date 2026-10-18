<?php
require_can('stok');
expire_due_orders();
$write = can('stok_tulis');
$products = sql('SELECT id,sku,nama,kategori,harga,stok,stok_min,pondok,aktif FROM products ORDER BY aktif DESC,kategori,nama')->fetchAll();
$moves = sql('SELECT m.created_at,m.perubahan,m.alasan,m.catatan,p.nama FROM stock_movements m JOIN products p ON p.id=m.product_id ORDER BY m.id DESC LIMIT 30')->fetchAll();
$low = array_filter($products, fn($p) => (int)$p['aktif'] === 1 && (int)$p['stok'] <= (int)$p['stok_min']);
?>
<h1>Stok barang</h1>
<p class="muted"><?= count($low) ?> barang aktif berada di batas minimum atau habis. Stok berkurang otomatis setiap penjualan; pesanan QRIS yang menunggu menahan stok.</p>

<?php if ($write): ?>
<section class="card">
  <h2>Restok dari gudang</h2>
  <form method="post" class="formgrid">
    <?= token_field() ?>
    <label>Barang<select name="product_id" required><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>"><?= esc($p['sku']) ?> · <?= esc($p['nama']) ?></option><?php endforeach; ?></select></label>
    <label>Jumlah masuk<input name="qty" type="number" min="1" max="1000000" required></label>
    <label>Catatan<input name="catatan" maxlength="255" placeholder="Nomor surat jalan, pemasok, dll."></label>
    <button name="action" value="restock">Catat restok</button>
  </form>
</section>
<section class="card">
  <h2>Tambah barang</h2>
  <form method="post" class="formgrid">
    <?= token_field() ?>
    <label>SKU<input name="sku" maxlength="60" required></label>
    <label>Nama<input name="nama" maxlength="180" required></label>
    <label>Kategori<input name="kategori" maxlength="100" required></label>
    <label>Harga (Rp)<input name="harga" type="number" min="1" required></label>
    <label>Stok minimum<input name="stok_min" type="number" min="0" value="0"></label>
    <label>Stok awal<input name="stok_awal" type="number" min="0" value="0"></label>
    <label>Dijual di<select name="pondok"><option value="semua">Semua pondok</option><option value="ikhwan">Ikhwan</option><option value="akhwat">Akhwat</option></select></label>
    <input type="hidden" name="aktif" value="1">
    <button name="action" value="save_product">Simpan barang</button>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <h2>Daftar barang</h2>
  <div class="tablewrap"><table>
    <thead><tr><th>SKU</th><th>Barang</th><th>Kategori</th><th>Dijual di</th><th class="r">Harga</th><th class="r">Stok</th><th class="r">Min</th><th>Status</th><?php if ($write): ?><th>Ubah</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($products as $p):
        $state = !(int)$p['aktif'] ? 'nonaktif' : ((int)$p['stok'] <= 0 ? 'habis' : ((int)$p['stok'] <= (int)$p['stok_min'] ? 'menipis' : 'aman')); ?>
      <tr>
        <td class="mono"><?= esc($p['sku']) ?></td><td><?= esc($p['nama']) ?></td><td><?= esc($p['kategori']) ?></td><td><?= esc($p['pondok']) ?></td>
        <td class="r"><?= rupiah($p['harga']) ?></td><td class="r"><?= (int)$p['stok'] ?></td><td class="r"><?= (int)$p['stok_min'] ?></td>
        <td><span class="pill st-<?= esc($state) ?>"><?= esc($state) ?></span></td>
        <?php if ($write): ?><td>
          <details><summary>Ubah</summary>
            <form method="post" class="formgrid">
              <?= token_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <label>SKU<input name="sku" value="<?= esc($p['sku']) ?>" maxlength="60" required></label>
              <label>Nama<input name="nama" value="<?= esc($p['nama']) ?>" maxlength="180" required></label>
              <label>Kategori<input name="kategori" value="<?= esc($p['kategori']) ?>" maxlength="100" required></label>
              <label>Harga (Rp)<input name="harga" type="number" min="1" value="<?= (int)$p['harga'] ?>" required></label>
              <label>Stok minimum<input name="stok_min" type="number" min="0" value="<?= (int)$p['stok_min'] ?>"></label>
              <label>Dijual di<select name="pondok"><?php foreach (['semua', 'ikhwan', 'akhwat'] as $v): ?><option value="<?= $v ?>"<?= $p['pondok'] === $v ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
              <label>Status<select name="aktif"><option value="1"<?= (int)$p['aktif'] ? ' selected' : '' ?>>Aktif</option><option value="0"<?= !(int)$p['aktif'] ? ' selected' : '' ?>>Nonaktif</option></select></label>
              <button name="action" value="save_product">Simpan</button>
            </form>
          </details>
        </td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="card">
  <h2>30 pergerakan stok terakhir</h2>
  <div class="tablewrap"><table>
    <thead><tr><th>Waktu</th><th>Barang</th><th class="r">Perubahan</th><th>Alasan</th><th>Catatan</th></tr></thead>
    <tbody><?php foreach ($moves as $m): ?>
      <tr><td class="mono"><?= esc($m['created_at']) ?></td><td><?= esc($m['nama']) ?></td><td class="r"><?= (int)$m['perubahan'] > 0 ? '+' : '' ?><?= (int)$m['perubahan'] ?></td><td><?= esc($m['alasan']) ?></td><td><?= esc($m['catatan'] ?? '') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
