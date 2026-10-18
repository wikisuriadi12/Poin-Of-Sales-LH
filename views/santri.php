<?php
require_can('santri');
$write = can('santri_tulis');
$list = sql('SELECT id,nomor_induk,nama,pondok,saldo,aktif FROM santri ORDER BY pondok,nama')->fetchAll();
$ledger = sql('SELECT l.created_at,l.perubahan,l.jenis,l.catatan,s.nama FROM santri_ledger l JOIN santri s ON s.id=l.santri_id ORDER BY l.id DESC LIMIT 30')->fetchAll();
?>
<h1>Saldo santri</h1>
<p class="muted">Saldo uang saku untuk belanja di koperasi. Data santri di sini sementara berdiri sendiri; sambungan ke modul pendaftaran santri menyusul.</p>

<?php if ($write): ?>
<section class="card">
  <h2>Top-up saldo</h2>
  <form method="post" class="formgrid">
    <?= token_field() ?>
    <label>Santri<select name="santri_id" required><?php foreach ($list as $s): if ((int)$s['aktif']): ?><option value="<?= (int)$s['id'] ?>"><?= esc($s['nama']) ?> (<?= esc($s['pondok']) ?>)</option><?php endif; endforeach; ?></select></label>
    <label>Nominal (Rp)<input name="nominal" type="number" min="1" max="100000000" required></label>
    <label>Catatan<input name="catatan" maxlength="255" placeholder="Setoran wali, dll."></label>
    <button name="action" value="topup">Tambah saldo</button>
  </form>
</section>
<section class="card">
  <h2>Tambah santri</h2>
  <form method="post" class="formgrid">
    <?= token_field() ?>
    <label>Nomor induk<input name="nomor_induk" maxlength="50" required></label>
    <label>Nama<input name="nama" maxlength="180" required></label>
    <label>Pondok<select name="pondok"><option value="ikhwan">Ikhwan</option><option value="akhwat">Akhwat</option></select></label>
    <button name="action" value="add_santri">Simpan santri</button>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <h2>Daftar santri</h2>
  <div class="tablewrap"><table>
    <thead><tr><th>Nomor induk</th><th>Nama</th><th>Pondok</th><th class="r">Saldo</th></tr></thead>
    <tbody><?php foreach ($list as $s): ?>
      <tr><td class="mono"><?= esc($s['nomor_induk']) ?></td><td><?= esc($s['nama']) ?></td><td><?= esc($s['pondok']) ?></td><td class="r"><?= rupiah($s['saldo']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>

<section class="card">
  <h2>30 mutasi saldo terakhir</h2>
  <div class="tablewrap"><table>
    <thead><tr><th>Waktu</th><th>Santri</th><th>Jenis</th><th class="r">Perubahan</th><th>Catatan</th></tr></thead>
    <tbody><?php foreach ($ledger as $l): ?>
      <tr><td class="mono"><?= esc($l['created_at']) ?></td><td><?= esc($l['nama']) ?></td><td><?= esc($l['jenis']) ?></td><td class="r"><?= (int)$l['perubahan'] > 0 ? '+' : '' ?><?= rupiah($l['perubahan']) ?></td><td><?= esc($l['catatan'] ?? '') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
