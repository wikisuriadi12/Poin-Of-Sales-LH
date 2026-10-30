<?php
Auth::require('gudang.view');
$scope = null;
$unit = isset(Pos::LOKASI[in_str('lok')]) ? in_str('lok') : Pos::GUDANG;
$q = in_str('q');
$rendah = in_str('rendah') === '1';
$edit = Auth::can('gudang.edit');
if (is_post() && in_str('aksi') === 'gabung' && $edit) {
    csrf_check();
    $n = Gudang::gabungAkhwat(Auth::id());
    flash($n . ' barang dipindah dari stok lama (Akhwat) ke Gudang.');
    redirect('?p=gudang&lok=gudang');
}
if (is_post() && in_str('aksi') === 'ambil' && $edit) {
    csrf_check();
    $ids = in_str('semua') === '1' ? array_column(Gudang::belumDiMarket(), 'id') : (array) ($_POST['ids'] ?? []);
    $n = Gudang::daftarkanBanyak($ids, Auth::id());
    flash($n > 0 ? $n . ' item diambil ke Stok Market. Isi stoknya lewat Barang masuk atau Transfer dari Gudang.' : 'Tidak ada item yang dipilih.', $n > 0 ? 'ok' : 'warn');
    redirect('?p=gudang&lok=ikhwan');
}
if (is_post() && in_str('aksi') === 'input_market' && $edit) {
    csrf_check();
    try {
        $bid = (int) in_str('id', '0'); $qty = (int) preg_replace('/\D/', '', in_str('qty', '0'));
        Gudang::masuk($bid, Pos::TOKO, $qty, 0, '', 'Input stok Market', Auth::id(), null);
        flash($qty . ' pcs stok Market ditambahkan.');
    } catch (InvalidArgumentException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('?p=gudang&lok=ikhwan');
}
if (is_post() && in_str('aksi') === 'tarik' && $edit) {
    csrf_check();
    try {
        $bid = (int) in_str('id', '0'); $qty = (int) preg_replace('/\D/', '', in_str('qty', '0'));
        Gudang::transfer($bid, Pos::GUDANG, Pos::TOKO, $qty, 'Tarik dari Gudang', Auth::id());
        flash($qty . ' pcs ditarik dari Gudang ke Market. Barang siap dijual di kasir.');
    } catch (InvalidArgumentException $ex) { flash($ex->getMessage(), 'err'); }
    redirect('?p=gudang&lok=ikhwan');
}
$belum = $edit && $unit === Pos::TOKO ? Gudang::belumDiMarket() : [];
$sisaAkhwat = (int) Db::val('SELECT COUNT(*) FROM stok WHERE unit = ? AND qty > 0', ['akhwat']);
$onlyMarket = $unit === Pos::TOKO;
$rows = Gudang::daftar($unit, $q, $rendah, false, $onlyMarket);
$nilai = Gudang::nilaiPersediaan($unit);
$nRendah = Gudang::jumlahRendah($unit);
$jenis = count(Gudang::daftar($unit, '', false, true, $onlyMarket));
ob_start(); ?>
<div class="page-h"><div><h1>Stok <?= e(Pos::LOKASI[$unit]) ?></h1><p><?= $onlyMarket ? 'Barang yang tampil di kasir. Stok berkurang otomatis saat kasir menjual.' : 'Persediaan di gudang. Pindahkan ke Market lewat Transfer agar barang muncul di kasir.' ?></p></div>
  <div style="display:flex;gap:8px"><a class="btn ghost" href="?p=label"><?= icon('tag') ?> Cetak label</a>
  <?php if ($edit): ?><a class="btn ghost" href="?p=impor_stok"><?= icon('upload') ?> Impor Excel</a><?php endif; ?>
  <?php if ($edit): ?><a class="btn" href="?p=barang"><?= icon('plus') ?> Barang baru</a><?php endif; ?></div></div>
<?php if ($edit && $sisaAkhwat > 0): ?>
<div class="card" style="margin-bottom:16px"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="gabung">
  <b>Ada stok lama di unit Akhwat (<?= $sisaAkhwat ?> barang).</b> Stok itu belum terhitung di Gudang maupun Market.
  <button class="btn sm" data-confirm="Pindahkan semua stok unit Akhwat ke Gudang?">Pindahkan ke Gudang</button></form></div>
<?php endif; ?>
<div class="tabs" style="margin-bottom:12px"><?php foreach (Pos::LOKASI as $k => $v): ?><a class="<?= $unit === $k ? 'on' : '' ?>" href="?p=gudang&amp;lok=<?= e($k) ?>">Stok <?= e($v) ?></a><?php endforeach; ?></div>
<?php if ($belum): ?>
<div class="card" style="margin-bottom:16px"><h2>Ambil item dari Master</h2>
  <p class="mut" style="margin:0 0 10px">Master barang hanya satu. Centang item yang mau dijual di Market, lalu klik Ambil. Stok awalnya 0.</p>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="ambil">
    <div class="scroll" style="max-height:240px;overflow:auto;margin-bottom:10px">
    <?php foreach ($belum as $x): ?><label class="chk" style="display:block;padding:4px 0"><input type="checkbox" name="ids[]" value="<?= (int) $x['id'] ?>"> <b><?= e($x['nama']) ?></b> <small class="mut"><?= e($x['kode']) ?> &middot; <?= rp($x['harga_jual']) ?></small></label><?php endforeach; ?>
    </div>
    <button class="btn"><?= icon('plus') ?> Ambil yang dicentang</button>
    <button class="btn ghost" name="semua" value="1" data-confirm="Ambil semua <?= count($belum) ?> item ke Stok Market?">Ambil semua (<?= count($belum) ?>)</button>
  </form></div>
<?php endif; ?>
<div class="kpis">
  <div class="kpi"><div class="chip"><?= icon('box', 22) ?></div><div><span>Jenis barang aktif</span><b><?= (int) $jenis ?></b></div></div>
  <div class="kpi"><div class="chip c-acc"><?= icon('alert', 22) ?></div><div><span>Stok menipis / habis</span><b><?= (int) $nRendah ?></b></div></div>
  <?php if (Auth::can('harga_beli')): ?><div class="kpi"><div class="chip c-ok"><?= icon('wallet', 22) ?></div><div><span>Nilai persediaan (modal)</span><b><?= rp($nilai) ?></b></div></div><?php endif; ?>
</div>
<div class="card">
  <form method="get" class="filters"><input type="hidden" name="p" value="gudang"><input type="hidden" name="lok" value="<?= e($unit) ?>">
    <input name="q" placeholder="Cari nama, kode, kategori..." value="<?= e($q) ?>">
    <label class="chk"><input type="checkbox" name="rendah" value="1" <?= $rendah ? 'checked' : '' ?>> Hanya stok menipis</label>
    <button class="btn"><?= icon('search') ?> Tampilkan</button>
    <button type="button" class="btn ghost" id="stok-excel">Export Excel</button>
    <button type="button" class="btn ghost" id="stok-print">Print</button>
    <small>Export dan Print mengikuti data yang sudah ditampilkan. Setelah mengubah filter, klik Tampilkan dahulu.</small>
  </form>
  <div class="scroll"><table class="rt">
    <thead><tr><th>Barang</th><th>Kategori</th><th class="r">Harga jual</th><th class="r">Stok</th><?php if ($onlyMarket && $edit): ?><th>Input stok Market</th><th class="r">Stok Gudang</th><th>Tarik dari Gudang</th><?php endif; ?><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $b):
        $habis = (int) $b['qty'] <= 0; $tipis = (int) $b['qty'] <= (int) $b['stok_min']; ?>
      <tr>
        <td><b><?= e($b['nama']) ?></b><br><small><?= e($b['kode']) ?></small></td>
        <td data-label="Kategori"><?= e($b['kategori'] ?: '-') ?></td>
        <td class="r" data-label="Harga jual"><?= rp($b['harga_jual']) ?></td>
        <td class="r" data-label="Stok"><b><?= (int) $b['qty'] ?></b> <?= e($b['satuan']) ?></td>
        <?php if ($onlyMarket && $edit): $sg = Gudang::stok((int) $b['id'], Pos::GUDANG); ?>
        <td data-label="Input stok"><form method="post" class="inline" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="aksi" value="input_market"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><input name="qty" inputmode="numeric" placeholder="+ Jumlah" style="width:84px" required><button class="btn sm">Tambah</button></form></td>
        <td class="r" data-label="Stok Gudang"><b><?= $sg ?></b></td>
        <td data-label="Tarik"><?php if ($sg > 0): ?><form method="post" class="inline" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="aksi" value="tarik"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><input name="qty" inputmode="numeric" placeholder="Jumlah" style="width:84px" required><button class="btn sm">Tarik</button></form><?php else: ?><small class="mut">Gudang kosong</small><?php endif; ?></td>
        <?php endif; ?>
        <td data-label="Status"><?php if (!(int) $b['aktif']): ?><span class="badge">Nonaktif</span><?php elseif ($habis): ?><span class="badge b-belum">Habis</span><?php elseif ($tipis): ?><span class="badge b-sebagian">Menipis</span><?php else: ?><span class="badge b-lunas">Aman</span><?php endif; ?></td>
        <td class="r"><a class="btn sm ghost" href="?p=gudang_item&amp;id=<?= (int) $b['id'] ?>&amp;lok=<?= e($unit) ?>"><?= $edit ? 'Kelola' : 'Detail' ?></a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="empty">Belum ada barang<?= $edit ? '. Klik "Barang baru" untuk menambah.' : '.' ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php
$laporanStok = [
    'judul' => 'Stok ' . Pos::LOKASI[$unit],
    'filter' => 'Pencarian: ' . ($q !== '' ? $q : 'Semua') . ' | ' . ($rendah ? 'Hanya stok menipis / habis' : 'Semua stok'),
    'waktu' => date('d-m-Y H:i'),
    'kolom' => ['No', 'Kode / Barcode', 'Nama barang', 'Kategori', 'Harga jual', 'Stok', 'Satuan', 'Stok minimum', 'Status'],
    'baris' => [],
];
foreach ($rows as $i => $b) {
    $status = !(int) $b['aktif'] ? 'Nonaktif' : ((int) $b['qty'] <= 0 ? 'Habis' : ((int) $b['qty'] <= (int) $b['stok_min'] ? 'Menipis' : 'Aman'));
    $laporanStok['baris'][] = [count($laporanStok['baris']) + 1, (string) $b['kode'], (string) $b['nama'], (string) ($b['kategori'] ?: '-'), (int) $b['harga_jual'], (int) $b['qty'], (string) $b['satuan'], (int) $b['stok_min'], $status];
}
?>
<script type="application/json" id="stok-report-data"><?= json_encode($laporanStok, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script>
(function () {
  'use strict';
  var data = JSON.parse(document.getElementById('stok-report-data').textContent);
  function escapeXml(v) {
    return String(v).replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f]/g, '').replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&apos;'}[c];
    });
  }
  // ZIP non-compressed: XLSX without a CDN or server extension.
  function makeZip(files) {
    var enc = new TextEncoder(), chunks = [], directory = [], offset = 0;
    function crc(bytes) {
      var c = 0xffffffff;
      bytes.forEach(function (b) { c ^= b; for (var k=0;k<8;k++) c=(c>>>1)^((c&1)?0xedb88320:0); });
      return (c ^ 0xffffffff) >>> 0;
    }
    function header(size) { return new Uint8Array(size); }
    Object.keys(files).forEach(function (name) {
      var n=enc.encode(name), b=enc.encode(files[name]), sum=crc(b);
      var h=header(30), v=new DataView(h.buffer);
      v.setUint32(0,0x04034b50,true); v.setUint16(4,20,true); v.setUint16(12,33,true);
      v.setUint32(14,sum,true); v.setUint32(18,b.length,true); v.setUint32(22,b.length,true); v.setUint16(26,n.length,true);
      chunks.push(h,n,b);
      var d=header(46), w=new DataView(d.buffer);
      w.setUint32(0,0x02014b50,true); w.setUint16(4,20,true); w.setUint16(6,20,true); w.setUint16(14,33,true);
      w.setUint32(16,sum,true); w.setUint32(20,b.length,true); w.setUint32(24,b.length,true); w.setUint16(28,n.length,true); w.setUint32(42,offset,true);
      directory.push(d,n); offset+=h.length+n.length+b.length;
    });
    var size=directory.reduce(function(a,b){return a+b.length;},0), end=header(22), e=new DataView(end.buffer);
    e.setUint32(0,0x06054b50,true); e.setUint16(8,Object.keys(files).length,true); e.setUint16(10,Object.keys(files).length,true);
    e.setUint32(12,size,true); e.setUint32(16,offset,true);
    return new Blob(chunks.concat(directory,[end]),{type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
  }
  function workbook() {
    var rows=[[data.judul],[data.filter],['Dicetak: '+data.waktu],[data.baris.length+' barang'],data.kolom].concat(data.baris);
    var xml=rows.map(function(row,i){return '<row r="'+(i+1)+'">'+row.map(function(value,j){
      var ref=String.fromCharCode(65+j)+(i+1);
      return typeof value==='number' ? '<c r="'+ref+'"><v>'+value+'</v></c>' : '<c r="'+ref+'" t="inlineStr"><is><t xml:space="preserve">'+escapeXml(value)+'</t></is></c>';
    }).join('')+'</row>';}).join('');
    return makeZip({
      '[Content_Types].xml':'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
      '_rels/.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
      'xl/workbook.xml':'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Stok" sheetId="1" r:id="rId1"/></sheets></workbook>',
      'xl/_rels/workbook.xml.rels':'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
      'xl/worksheets/sheet1.xml':'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/><col min="3" max="3" width="42" customWidth="1"/><col min="4" max="9" width="18" customWidth="1"/></cols><sheetData>'+xml+'</sheetData><autoFilter ref="A5:I'+rows.length+'"/></worksheet>'
    });
  }
  document.getElementById('stok-excel').addEventListener('click',function(){
    var url=URL.createObjectURL(workbook()), a=document.createElement('a');
    a.href=url; a.download=data.judul.replace(/[^a-z0-9]+/gi,'-')+'-'+new Date().toISOString().slice(0,10)+'.xlsx';
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(function(){URL.revokeObjectURL(url);},60000);
  });
  document.getElementById('stok-print').addEventListener('click',function(){
    var w=window.open('','_blank');
    if(!w){alert('Izinkan pop-up untuk membuka halaman cetak.');return;}
    var html='<!doctype html><html lang="id"><head><meta charset="utf-8"><title>'+escapeXml(data.judul)+'</title><style>body{font:12px Arial;color:#182e25;margin:24px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #aaa;padding:7px;text-align:left}th{background:#e5f1eb}thead{display:table-header-group}tr{break-inside:avoid}button{padding:10px 20px;margin-bottom:14px}@page{size:A4 landscape;margin:12mm}@media print{button{display:none}body{margin:0}}</style></head><body><button onclick="window.print()">Print / Simpan PDF</button><h1>'+escapeXml(data.judul)+'</h1><p>'+escapeXml(data.filter)+'</p><p>'+escapeXml(data.waktu)+' · '+data.baris.length+' barang</p><table><thead><tr>'+data.kolom.map(function(x){return '<th>'+escapeXml(x)+'</th>';}).join('')+'</tr></thead><tbody>'+data.baris.map(function(row){return '<tr>'+row.map(function(x){return '<td>'+escapeXml(x)+'</td>';}).join('')+'</tr>';}).join('')+'</tbody></table>'+(data.baris.length?'':'<p>Tidak ada barang yang sesuai filter.</p>')+'</body></html>';
    w.document.write(html);w.document.close();w.focus();
  });
})();
</script>
<?php render('Stok Gudang', ob_get_clean());
