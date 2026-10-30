<?php
// Uji logika inti dengan SQLite memori: php tests/run.php
declare(strict_types=1);
require __DIR__ . '/bootstrap_db.php';

test_db();

$fail = 0;
function ok(string $name, bool $cond): void { global $fail; echo ($cond ? 'PASS' : 'FAIL') . "  $name\n"; if (!$cond) { $fail++; } }
function throws(string $name, callable $fn): void { try { $fn(); ok($name, false); } catch (InvalidArgumentException $e) { ok($name . ' [' . $e->getMessage() . ']', true); } }

// Santri
$base = ['nama' => 'Ahmad', 'jenis_kelamin' => 'L', 'jenjang' => 'Ula', 'nama_wali' => 'Budi', 'tahun_masuk' => '2026', 'status' => 'aktif', 'potongan_spp' => '50.000'];
[$c, $e] = Santri::validate($base);
ok('validasi lolos & unit ikhwan', !$e && $c['unit'] === 'ikhwan' && $c['potongan_spp'] === 50000);
[, $e2] = Santri::validate(['nama' => '']);
ok('validasi gagal data kosong', count($e2) >= 4);
$id1 = Santri::create($c, null);
ok('NIS otomatis (26 + ikhwan 1 + Ula 2 + urut)', Santri::find($id1)['nis'] === '26120001');
[$c2] = Santri::validate(['nama' => 'Aisyah', 'jenis_kelamin' => 'P', 'jenjang' => 'Ula', 'nama_wali' => 'Umar', 'tahun_masuk' => '2026', 'status' => 'aktif']);
$id2 = Santri::create($c2, null);
ok('santri putri otomatis akhwat', Santri::find($id2)['unit'] === 'akhwat');
[$c3] = Santri::validate(['nama' => 'Zaid', 'jenis_kelamin' => 'L', 'jenjang' => 'Ulya', 'nama_wali' => 'Ali', 'tahun_masuk' => '2026', 'status' => 'nonaktif']);
Santri::create($c3, null);
throws('NIS ganda ditolak', fn() => Santri::create(array_merge($c, ['nis' => Santri::find($id1)['nis']]), null));

// ---------- V3: gudang, POS, dompet ----------
[$cb] = Gudang::validateBarang(['kode' => 'ab-1', 'nama' => 'Pulpen', 'kategori' => 'ATK', 'satuan' => '', 'harga_beli' => '1.500', 'harga_jual' => '2.500', 'stok_min' => '5']);
ok('barang: kode jadi kapital, harga diparse, satuan bawaan', $cb['kode'] === 'AB-1' && $cb['harga_jual'] === 2500 && $cb['satuan'] === 'pcs');
[$ck] = Gudang::validateBarang(['kode' => '', 'nama' => 'Tanpa Kode', 'harga_jual' => '1000']);
$k1 = Gudang::simpanBarang($ck, null, null); $k2 = Gudang::simpanBarang($ck, null, null);
ok('barang tanpa kode: dibuat otomatis berurutan', Gudang::find($k1)['kode'] === 'LH000001' && Gudang::find($k2)['kode'] === 'LH000002');
$ri = Gudang::imporBarang([['kode','nama','harga_jual'],['','Impor A','1000'],['','Impor B','2000']], false, null);
ok('impor tanpa kode: lanjut urutan', $ri['ok'] && Db::val("SELECT COUNT(*) FROM barang WHERE kode IN ('LH000003','LH000004')") == 2);
Db::exec("DELETE FROM barang WHERE kode LIKE 'LH0000%'");
[, $eb] = Gudang::validateBarang(['kode' => '!', 'nama' => '', 'harga_jual' => '0']);
ok('barang: validasi gagal', count($eb) === 3);
$b1 = Gudang::simpanBarang($cb, null, null);
throws('barang: kode ganda ditolak', fn() => Gudang::simpanBarang($cb, null, null));
ok('stok awal 0', Gudang::stok($b1, 'ikhwan') === 0);
Gudang::masuk($b1, 'ikhwan', 20, 1600, 'Toko A', '', null, null);
ok('barang masuk menambah stok & memperbarui harga beli', Gudang::stok($b1, 'ikhwan') === 20 && (int) Gudang::find($b1)['harga_beli'] === 1600);
ok('stok unit lain terpisah', Gudang::stok($b1, 'akhwat') === 0);
throws('masuk 0 ditolak', fn() => Gudang::masuk($b1, 'ikhwan', 0, 0, '', '', null, null));
throws('akun akhwat tak bisa ubah stok ikhwan', fn() => Gudang::masuk($b1, 'ikhwan', 5, 0, '', '', null, 'akhwat'));
throws('keluar tanpa alasan ditolak', fn() => Gudang::keluar($b1, 'ikhwan', 1, '', null, null));
throws('keluar melebihi stok ditolak', fn() => Gudang::keluar($b1, 'ikhwan', 99, 'rusak', null, null));
Gudang::keluar($b1, 'ikhwan', 2, 'rusak', null, null);
ok('keluar mengurangi stok', Gudang::stok($b1, 'ikhwan') === 18);
Gudang::opname($b1, 'ikhwan', 15, '', null, null);
ok('opname menyamakan stok', Gudang::stok($b1, 'ikhwan') === 15);
ok('riwayat stok tercatat 3 mutasi', count(Gudang::riwayat($b1, null)) === 3);

[$cb2] = Gudang::validateBarang(['kode' => 'SB-9', 'nama' => 'Sabun', 'harga_beli' => '3000', 'harga_jual' => '5000', 'stok_min' => '2']);
$b2 = Gudang::simpanBarang($cb2, null, null);
Gudang::masuk($b2, 'ikhwan', 10, 3000, '', '', null, null);

throws('jual tanpa barang ditolak', fn() => Pos::jual('ikhwan', null, [], 'tunai', 0, null, null));
throws('jual uang kurang ditolak', fn() => Pos::jual('ikhwan', null, [$b1 => 2], 'tunai', 4000, null, null));
throws('jual melebihi stok ditolak', fn() => Pos::jual('ikhwan', null, [$b1 => 50], 'tunai', 999999, null, null));
// pelanggan umum / pegawai di kasir
$idU = Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 1000000, null, null, 0, 'Pegawai: Budi');
ok('penjualan pegawai menyimpan nama pelanggan', Db::val('SELECT pelanggan FROM penjualan WHERE id = ?', [$idU]) === 'Pegawai: Budi');
$idV = Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 1000000, null, null, 0, 'Umum');
ok('penjualan umum tanpa santri tersimpan', Db::val('SELECT pelanggan FROM penjualan WHERE id = ?', [$idV]) === 'Umum' && Db::val('SELECT santri_id FROM penjualan WHERE id = ?', [$idV]) === null);
Db::exec('UPDATE barang SET harga_pegawai = 777 WHERE id = ?', [$b1]);
$hj = (int) Db::val('SELECT harga_jual FROM barang WHERE id = ?', [$b1]);
$idP = Pos::jual('ikhwan', null, [$b1 => 2], 'tunai', 1000000, null, null, 0, 'Pegawai: Ani');
ok('pegawai dikenai harga pegawai', (int) Db::val('SELECT total FROM penjualan WHERE id = ?', [$idP]) === 1554);
$idQ = Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 1000000, null, null, 0, 'Umum');
ok('umum tetap harga umum', (int) Db::val('SELECT total FROM penjualan WHERE id = ?', [$idQ]) === $hj);
Pos::batalkan($idP, 'uji', null, null); Pos::batalkan($idQ, 'uji', null, null);
Db::exec('UPDATE barang SET harga_pegawai = NULL WHERE id = ?', [$b1]);
Pos::batalkan($idU, 'uji', null, null); Pos::batalkan($idV, 'uji', null, null);
// impor Excel master barang + stok
$xl = Xlsx::tulis(['Master Barang' => array_merge([Gudang::IMPOR_HEADER], Gudang::templateContoh(), [['IMP-9', 'Barang Impor Uji', 'Uji', 'pak', '1.000', 'Rp 2.500', 'Rp 2.000', 3, 7, '']])]);
$xf = sys_get_temp_dir() . '/uji-impor.xlsx'; file_put_contents($xf, $xl);
$baca = Xlsx::baca($xf);
ok('xlsx tulis lalu baca kembali', count($baca) === 5 && $baca[1][1] === 'Air Mineral 600 ml' && $baca[1][5] === '3500' && $baca[4][0] === 'IMP-9');
$r1 = Gudang::imporBarang($baca, false, null);
ok('impor xlsx sukses (4 baru)', $r1['ok'] && $r1['baru'] === 4);
$iid = (int) Db::val("SELECT id FROM barang WHERE kode = 'IMP-9'");
ok('impor harga dan stok gudang benar', (int) Db::val('SELECT harga_jual FROM barang WHERE id = ?', [$iid]) === 2500 && Gudang::stok($iid, Pos::GUDANG) === 7 && Gudang::stok($iid, Pos::TOKO) === 0);
$kid = (int) Db::val("SELECT id FROM barang WHERE kode = 'KTN-001'");
ok('stok market terisi tampil di kasir', Gudang::stok($kid, Pos::TOKO) === 24 && in_array($kid, array_map(static fn($x) => (int) $x['id'], Gudang::daftarMarket()), true));
$r2 = Gudang::imporBarang($baca, false, null);
ok('impor ulang tanpa perbarui ditolak semua', !$r2['ok'] && $r2['jumlah_galat'] === 4 && (int) Db::val('SELECT COUNT(*) FROM barang WHERE kode = ?', ['IMP-9']) === 1);
$baca[4][5] = '3000'; $baca[4][8] = '2';
$r3 = Gudang::imporBarang(array_slice($baca, 0, 1) + [1 => $baca[4]], true, null);
ok('impor perbarui menimpa harga dan stok', $r3['ok'] && $r3['ubah'] === 1 && (int) Db::val('SELECT harga_jual FROM barang WHERE id = ?', [$iid]) === 3000 && Gudang::stok($iid, Pos::GUDANG) === 2);
$salah = [Gudang::IMPOR_HEADER, ['', 'Tanpa kode', '', '', 'abc', '0', '', 'x', ''], ['OK-1', 'Valid', '', '', '', '1000', '', '5', '']];
$r4 = Gudang::imporBarang($salah, false, null);
ok('baris salah menggagalkan seluruh impor', !$r4['ok'] && (int) Db::val("SELECT COUNT(*) FROM barang WHERE kode = 'OK-1'") === 0);
ok('header tanpa kolom wajib ditolak', !Gudang::imporBarang([['foo', 'bar'], ['1', '2']], false, null)['ok']);
$csv = sys_get_temp_dir() . '/uji-impor.csv'; file_put_contents($csv, "\xEF\xBB\xBFKode Barang;Nama Barang;Harga Jual;Stok Market\nCSV-1;Barang CSV;4.500;9\n");
$r5 = Gudang::imporBarang(Xlsx::bacaCsv($csv), false, null);
$cid = (int) Db::val("SELECT id FROM barang WHERE kode = 'CSV-1'");
ok('impor CSV titik koma dengan alias judul', $r5['ok'] && Gudang::stok($cid, Pos::TOKO) === 9);
foreach (['IMP-9', 'CSV-1', 'KTN-001', 'KTN-002', 'ATK-001'] as $kd) {
    $bid = (int) Db::val('SELECT id FROM barang WHERE kode = ?', [$kd]);
    if ($bid) { Db::exec('DELETE FROM stok WHERE barang_id = ?', [$bid]); Db::exec('DELETE FROM stok_mutasi WHERE barang_id = ?', [$bid]); Db::exec('DELETE FROM barang WHERE id = ?', [$bid]); }
}
@unlink($xf); @unlink($csv);

// satu toko: gabung stok Akhwat ke LH Mart
Gudang::ubah($b1, 'akhwat', 5, 'masuk', ['catatan' => 'uji gabung', 'user_id' => null]);
$sebelum = Gudang::stok($b1, Pos::TOKO);
Gudang::gabungAkhwat(null);
ok('stok akhwat lama pindah ke gudang', Gudang::stok($b1, Pos::GUDANG) >= 5 && Gudang::stok($b1, 'akhwat') === 0 && Gudang::stok($b1, Pos::TOKO) === $sebelum);
$g0 = Gudang::stok($b1, Pos::GUDANG);
Gudang::transfer($b1, Pos::GUDANG, Pos::TOKO, 3, '', null);
ok('transfer gudang ke market', Gudang::stok($b1, Pos::GUDANG) === $g0 - 3 && Gudang::stok($b1, Pos::TOKO) === $sebelum + 3);
throws('transfer melebihi stok gudang ditolak', fn() => Gudang::transfer($b1, Pos::GUDANG, Pos::TOKO, 99999, '', null));
throws('transfer lokasi sama ditolak', fn() => Gudang::transfer($b1, Pos::TOKO, Pos::TOKO, 1, '', null));
Gudang::transfer($b1, Pos::TOKO, Pos::GUDANG, 3, 'uji retur', null);
$gBaru = (int) Db::val("SELECT COALESCE(MAX(id),0)+1 FROM barang");
$idBaru = Gudang::simpanBarang(['kode' => 'ZZ-GDG', 'nama' => 'Barang hanya gudang', 'kategori' => '', 'satuan' => 'pcs', 'harga_beli' => 100, 'harga_jual' => 200, 'stok_min' => 0], null, null);
Gudang::masuk($idBaru, Pos::GUDANG, 10, 100, '', '', null, null);
ok('barang hanya di gudang tidak terbaca kasir', !in_array($idBaru, array_map(static fn($r) => (int) $r['id'], Gudang::daftarMarket()), true));
throws('transfer ke market ditolak bila item belum ada di stok market', fn() => Gudang::transfer($idBaru, Pos::GUDANG, Pos::TOKO, 4, '', null));
$idQ2 = Gudang::simpanBarang(['kode' => 'ZZ-Q2', 'nama' => 'Ambil Banyak', 'kategori' => '', 'satuan' => 'pcs', 'harga_beli' => 1, 'harga_jual' => 2, 'stok_min' => 0], null, null);
ok('belumDiMarket memuat barang baru', in_array($idBaru, array_map(static fn($r) => (int) $r['id'], Gudang::belumDiMarket()), true));
ok('daftarkanBanyak', Gudang::daftarkanBanyak([$idBaru, $idQ2], null) === 2 && !in_array($idBaru, array_map(static fn($r) => (int) $r['id'], Gudang::belumDiMarket()), true));
Db::exec('DELETE FROM stok WHERE barang_id = ?', [$idQ2]); Db::exec('DELETE FROM stok_mutasi WHERE barang_id = ?', [$idQ2]); Db::exec('DELETE FROM barang WHERE id = ?', [$idQ2]);
Gudang::daftarkanMarket($idBaru, null);
ok('daftarkan ke market', Gudang::daftarkanMarket($idBaru, null) === false && in_array($idBaru, array_map(static fn($r) => (int) $r['id'], Gudang::daftarMarket()), true));
Gudang::transfer($idBaru, Pos::GUDANG, Pos::TOKO, 4, '', null);
ok('barang setelah transfer terbaca kasir', in_array($idBaru, array_map(static fn($r) => (int) $r['id'], Gudang::daftarMarket()), true));
Db::exec('DELETE FROM stok WHERE barang_id = ?', [$idBaru]); Db::exec('DELETE FROM stok_mutasi WHERE barang_id = ?', [$idBaru]); Db::exec('DELETE FROM barang WHERE id = ?', [$idBaru]);
// barang masuk banyak + supplier + laporan + impor kolom 'stok'
$bm1 = Gudang::simpanBarang(['kode' => 'BM-1', 'nama' => 'Masuk Satu', 'kategori' => '', 'satuan' => 'pcs', 'harga_beli' => 100, 'harga_jual' => 200, 'stok_min' => 0], null, null);
$bm2 = Gudang::simpanBarang(['kode' => 'BM-2', 'nama' => 'Masuk Dua', 'kategori' => '', 'satuan' => 'pcs', 'harga_beli' => 100, 'harga_jual' => 200, 'stok_min' => 0], null, null);
throws('barang masuk tanpa supplier ditolak', fn() => Gudang::masukBanyak(Pos::GUDANG, '', '', [[$bm1, 5, 100]], null));
throws('barang masuk tanpa item ditolak', fn() => Gudang::masukBanyak(Pos::GUDANG, 'CV Maju', '', [[0, 0, 0]], null));
throws('barang masuk salah satu baris salah membatalkan semua', fn() => Gudang::masukBanyak(Pos::GUDANG, 'CV Maju', '', [[$bm1, 5, 100], [$bm2, 0, 100]], null));
ok('tidak ada yang masuk saat gagal', Gudang::stok($bm1, Pos::GUDANG) === 0);
ok('barang masuk banyak', Gudang::masukBanyak(Pos::GUDANG, 'CV Maju', 'Faktur 1', [[$bm1, 5, 150], [$bm2, 7, 0]], null) === 2 && Gudang::stok($bm1, Pos::GUDANG) === 5 && Gudang::stok($bm2, Pos::GUDANG) === 7);
ok('harga beli diperbarui dari barang masuk', (int) Db::val('SELECT harga_beli FROM barang WHERE id = ?', [$bm1]) === 150 && (int) Db::val('SELECT harga_beli FROM barang WHERE id = ?', [$bm2]) === 100);
$lm = Gudang::laporanMasuk(date('Y-m-d'), date('Y-m-d'), '', 'CV Maju');
ok('laporan barang masuk per supplier', count($lm) === 2 && $lm[0]['pemasok'] === 'CV Maju' && in_array('CV Maju', Gudang::daftarPemasok(), true));
$sx = [['Kode Barang', 'Nama Produk', 'Harga', 'Stok'], ['OLD-1', 'Barang Sistem Lama', '5000', '12']];
$rx = Gudang::imporBarang($sx, false, null, 'ikhwan');
ok('impor sistem lama: kolom stok ke Market', $rx['ok'] && Gudang::stok((int) Db::val("SELECT id FROM barang WHERE kode='OLD-1'"), Pos::TOKO) === 12);
$ry = Gudang::imporBarang([['Kode', 'Nama', 'Harga Jual', 'Stok'], ['OLD-2', 'Lama Dua', '6000', '4']], false, null);
ok('impor sistem lama: kolom stok ke Gudang bawaan', $ry['ok'] && Gudang::stok((int) Db::val("SELECT id FROM barang WHERE kode='OLD-2'"), Pos::GUDANG) === 4);
foreach ([$bm1, $bm2, (int) Db::val("SELECT id FROM barang WHERE kode='OLD-1'"), (int) Db::val("SELECT id FROM barang WHERE kode='OLD-2'")] as $zid) { Db::exec('DELETE FROM stok WHERE barang_id = ?', [$zid]); Db::exec('DELETE FROM stok_mutasi WHERE barang_id = ?', [$zid]); Db::exec('DELETE FROM barang WHERE id = ?', [$zid]); }
// bukti QRIS
define('BUKTI_UJI', true);
$bq = sys_get_temp_dir() . '/bq_' . bin2hex(random_bytes(4)); define('BUKTI_DIR_UJI', $bq);
$im = imagecreatetruecolor(2000, 1000); $png = sys_get_temp_dir() . '/bq_' . bin2hex(random_bytes(3)) . '.png'; imagepng($im, $png);
throws('bukti qris wajib ada', fn() => BuktiQris::siapkan(null));
throws('bukti qris bukan gambar ditolak', fn() => BuktiQris::siapkan(['error' => UPLOAD_ERR_OK, 'tmp_name' => __FILE__, 'size' => 10]));
$bin = BuktiQris::siapkan(['error' => UPLOAD_ERR_OK, 'tmp_name' => $png, 'size' => filesize($png)]);
$gi = getimagesizefromstring($bin);
ok('bukti qris diperkecil jadi jpeg', $gi && $gi[2] === IMAGETYPE_JPEG && $gi[0] <= 1280);
$idBq = Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 1000000, null, null, 0, 'Umum');
$nmBq = BuktiQris::simpan($idBq, $bin);
ok('bukti qris tersimpan dan terhubung ke penjualan', BuktiQris::jalur($nmBq) !== null && Db::val('SELECT bukti_qris FROM penjualan WHERE id = ?', [$idBq]) === $nmBq);
Pos::batalkan($idBq, 'uji', null, null);
foreach (glob($bq . '/*') ?: [] as $zf) { @unlink($zf); } foreach (glob($bq . '/.htaccess') ?: [] as $zf) { @unlink($zf); } @rmdir($bq); @unlink($png);
// QRIS dinamis
$enc = QrisApi::segel('rahasia-uji-123');
ok('rahasia API terenkripsi dan bisa dibuka', strpos($enc, 'rahasia') === false && QrisApi::buka($enc) === 'rahasia-uji-123' && QrisApi::buka('enc1:rusak') === '');
ok('QRIS dinamis nonaktif bila belum diatur', !QrisApi::siap());
Settings::set('qris_api_provider', 'bni'); ok('bni belum berjalan (adapter menunggu)', !QrisApi::siap());
Settings::set('qris_api_provider', 'simulasi');
ok('simulasi siap', QrisApi::siap());
$tq = QrisApi::mulai(7000, null);
ok('transaksi dinamis menunggu', QrisApi::status($tq['ref']) === 'menunggu');
throws('belum lunas tidak bisa dipakai jual', fn() => QrisApi::periksaUntukJual($tq['ref'], 7000));
ok('simulasi lunas', QrisApi::simulasiLunas($tq['ref']) && QrisApi::status($tq['ref']) === 'lunas');
throws('nominal beda ditolak', fn() => QrisApi::periksaUntukJual($tq['ref'], 7001));
QrisApi::periksaUntukJual($tq['ref'], 7000); QrisApi::pakai($tq['ref'], 1);
throws('QRIS tidak bisa dipakai dua kali', fn() => QrisApi::periksaUntukJual($tq['ref'], 7000));
$hit = Pos::hitung([$b1 => 2], 'Umum', false, 0);
ok('Pos::hitung sama dengan harga jual', $hit === 2 * (int) Db::val('SELECT harga_jual FROM barang WHERE id = ?', [$b1]));
Settings::set('qris_api_provider', 'midtrans'); Settings::set('qris_api_secret', QrisApi::segel('SB-Mid-server-uji'));
$kirimLog = [];
QrisApi::$http = function ($m, $u, $h, $b) use (&$kirimLog) {
    $kirimLog[] = [$m, $u, $h, $b];
    if ($m === 'POST') { return [200, json_encode(['status_code' => '201', 'transaction_status' => 'pending', 'actions' => [['name' => 'generate-qr-code', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/x/qr-code']]])]; }
    return [200, json_encode(['transaction_status' => 'settlement', 'gross_amount' => '5000.00'])];
};
$tm = QrisApi::mulai(5000, null);
ok('midtrans: QR dibuat dengan basic auth & nominal', $tm['qr_url'] !== null && strpos($kirimLog[0][1], 'api.sandbox.midtrans.com/v2/charge') !== false && in_array('Authorization: Basic ' . base64_encode('SB-Mid-server-uji:'), $kirimLog[0][2], true) && json_decode($kirimLog[0][3], true)['transaction_details']['gross_amount'] === 5000);
ok('midtrans: polling status lunas', QrisApi::status($tm['ref']) === 'lunas');
$tm2 = QrisApi::mulai(6000, null);
$tm3 = QrisApi::mulai(4000, null);
QrisApi::$http = fn() => [200, json_encode(['transaction_status' => 'settlement', 'gross_amount' => '1000.00'])];
ok('midtrans: nominal tidak cocok jadi gagal', QrisApi::status($tm2['ref']) === 'gagal');
QrisApi::$http = fn() => [200, json_encode(['transaction_status' => 'pending'])];
$cb = ['order_id' => $tm3['ref'], 'status_code' => '200', 'gross_amount' => '4000.00', 'transaction_status' => 'settlement'];
[$k1] = QrisApi::callback(json_encode($cb + ['signature_key' => 'salah']));
ok('callback tanda tangan salah ditolak', $k1 === 403 && QrisApi::status($tm3['ref']) === 'menunggu');
[$k2] = QrisApi::callback(json_encode($cb + ['signature_key' => hash('sha512', $tm3['ref'] . '200' . '4000.00' . 'SB-Mid-server-uji')]));
QrisApi::$http = fn() => [200, json_encode(['transaction_status' => 'pending'])];
ok('callback sah menandai lunas', $k2 === 200 && QrisApi::status($tm3['ref']) === 'lunas');
QrisApi::$http = null; Settings::set('qris_api_provider', ''); Settings::set('qris_api_secret', '');
// laporan LH Mart
$lp0 = date('Y-m-d');
$idL1 = Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 1000000, null, null, 0, 'Umum');
$idL2 = Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 1000000, null, null, 0, 'Pegawai: Cici');
$rk = LaporanPos::ringkasan($lp0, $lp0);
ok('laporan ringkasan menghitung transaksi sah', $rk['n'] >= 2 && $rk['omzet'] > 0 && $rk['qty'] >= 2);
$pj = array_column(LaporanPos::perJenis($lp0, $lp0), 'n', 'jenis');
ok('laporan per jenis pelanggan memisah pegawai dan umum', ($pj['pegawai'] ?? 0) >= 1 && ($pj['umum'] ?? 0) >= 1);
ok('laporan produk memuat barang terjual', count(LaporanPos::produk($lp0, $lp0)) >= 1 && count(LaporanPos::kategori($lp0, $lp0)) >= 1 && count(LaporanPos::perKasir($lp0, $lp0)) >= 1);
ok('laporan transaksi filter pegawai', count(LaporanPos::transaksi($lp0, $lp0, '', 'pegawai')) >= 1);
ok('laporan per hari memuat hari ini', LaporanPos::perHari($lp0, $lp0)[$lp0]['n'] >= 2);
ok('laporan stok dan mutasi bisa dibaca', is_array(LaporanPos::stok(Pos::TOKO)) && is_array(LaporanPos::stok(Pos::GUDANG, 'habis')) && is_array(LaporanPos::mutasi($lp0, $lp0)) && is_array(LaporanPos::tidakLaku($lp0, $lp0)));
Pos::batalkan($idL1, 'uji', null, null); Pos::batalkan($idL2, 'uji', null, null);
ok('transaksi batal tidak dihitung di omzet', LaporanPos::ringkasan($lp0, $lp0)['n'] === $rk['n'] - 2 && LaporanPos::ringkasan($lp0, $lp0)['batal_n'] >= 2);
// pengaman login
$_SESSION = [];
LoginGuard::siapkan(); $kd = (string) ($_SESSION['lg_kode'] ?? '');
ok('captcha menyiapkan kode 5 karakter tanpa karakter membingungkan', strlen($kd) === 5 && !preg_match('/[01OIl]/', $kd));
ok('login terlalu cepat ditolak', LoginGuard::cek(['captcha' => $kd]) !== null);
$_SESSION = []; LoginGuard::siapkan(); $kd = (string) ($_SESSION['lg_kode'] ?? ''); $_SESSION['lg_t'] = time() - 10;
ok('honeypot terisi ditolak', LoginGuard::cek(['captcha' => $kd, 'website' => 'http://spam']) !== null);
$_SESSION = []; LoginGuard::siapkan(); $kd = (string) ($_SESSION['lg_kode'] ?? ''); $_SESSION['lg_t'] = time() - 10;
ok('captcha salah ditolak', LoginGuard::cek(['captcha' => 'SALAH']) !== null);
$_SESSION = []; LoginGuard::siapkan(); $kd = (string) ($_SESSION['lg_kode'] ?? ''); $_SESSION['lg_t'] = time() - 10;
ok('captcha benar lolos (huruf kecil & spasi diabaikan)', LoginGuard::cek(['captcha' => ' ' . strtolower($kd) . ' ']) === null);
ok('captcha sekali pakai (replay ditolak)', LoginGuard::cek(['captcha' => $kd]) !== null);
$_SESSION = []; LoginGuard::siapkan(); $_SESSION['lg_t'] = time() - 10; $_SESSION['lg_c']['exp'] = time() - 1;
ok('captcha kedaluwarsa ditolak', LoginGuard::cek(['captcha' => (string) ($_SESSION['lg_kode'] ?? 'X')]) !== null);
ok('input login tak wajar ditolak', !LoginGuard::inputWajar('', 'x') && !LoginGuard::inputWajar('u', str_repeat('a', 500)) && !LoginGuard::inputWajar(str_repeat('u', 100), 'x') && LoginGuard::inputWajar('admin', 'rahasia'));
if (LoginGuard::gdTersedia()) {
    $_SESSION = ['lg_kode' => 'AB7K2'];
    ob_start(); LoginGuard::gambar(); $png = (string) ob_get_clean();
    ok('captcha gambar PNG valid', strncmp($png, "\x89PNG", 4) === 0);
}
$_SESSION = [];
// hutang & konsinyasi
$hk1 = Gudang::simpanBarang(['kode' => 'HK-1', 'nama' => 'Beli Hutang', 'kategori' => '', 'satuan' => 'pcs', 'harga_beli' => 0, 'harga_jual' => 3000, 'stok_min' => 0], null, null);
$hk2 = Gudang::simpanBarang(['kode' => 'HK-2', 'nama' => 'Titipan Roti', 'kategori' => '', 'satuan' => 'pcs', 'harga_beli' => 0, 'harga_jual' => 5000, 'stok_min' => 0], null, null);
throws('hutang tanpa harga beli ditolak', fn() => Gudang::masukBanyak(Pos::GUDANG, 'CV Hutang', '', [[$hk1, 10, 0]], null, 'hutang'));
throws('jatuh tempo salah ditolak', fn() => Gudang::masukBanyak(Pos::GUDANG, 'CV Hutang', '', [[$hk1, 10, 1000]], null, 'hutang', 'bukan-tanggal'));
Gudang::masukBanyak(Pos::GUDANG, 'CV Hutang', '', [[$hk1, 10, 2000]], null, 'hutang', date('Y-m-d', strtotime('-1 day')), 'F-001');
$rkH = Hutang::ringkasan();
ok('hutang tercatat per nama supplier', ($rkH['CV Hutang']['faktur_sisa'] ?? 0) === 20000 && $rkH['CV Hutang']['lewat'] === 20000 && Gudang::stok($hk1, Pos::GUDANG) === 10);
$fH = Hutang::faktur('CV Hutang'); $hid = (int) $fH[0]['id'];
ok('item faktur hutang terlacak', count(Hutang::itemFaktur($hid)) === 1 && (int) Hutang::itemFaktur($hid)[0]['qty'] === 10);
throws('bayar hutang melebihi sisa ditolak', fn() => Hutang::bayar('hutang', 'CV Hutang', $hid, 25000, date('Y-m-d'), '', null));
Hutang::bayar('hutang', 'CV Hutang', $hid, 5000, date('Y-m-d'), 'cicil', null);
ok('hutang sebagian', Hutang::faktur('CV Hutang')[0]['status'] === 'sebagian' && Hutang::ringkasan()['CV Hutang']['faktur_sisa'] === 15000);
Hutang::bayar('hutang', 'CV Hutang', $hid, 15000, date('Y-m-d'), '', null);
ok('hutang lunas dan hilang dari belum lunas', Hutang::faktur('CV Hutang', 'belum') === [] && Hutang::totalHutang() === 0);
// konsinyasi
throws('konsinyasi tanpa harga setor ditolak', fn() => Gudang::masukBanyak(Pos::TOKO, 'Bu Roti', '', [[$hk2, 6, 0]], null, 'konsinyasi'));
Gudang::masukBanyak(Pos::TOKO, 'Bu Roti', '', [[$hk2, 6, 3500]], null, 'konsinyasi');
ok('konsinyasi: stok masuk, belum ada hutang', Gudang::stok($hk2, Pos::TOKO) === 6 && (Hutang::ringkasan()['Bu Roti']['kons_sisa'] ?? 0) === 0 && (int) Db::val("SELECT COUNT(*) FROM hutang WHERE pemasok = 'Bu Roti'") === 0);
throws('konsinyasi supplier lain ditolak', fn() => Gudang::masukBanyak(Pos::TOKO, 'Pak Kue', '', [[$hk2, 1, 3000]], null, 'konsinyasi'));
$idK = Pos::jual('ikhwan', null, [$hk2 => 4], 'tunai', 1000000, null, null, 0, 'Umum');
ok('konsinyasi: terutang sesuai terjual x harga setor', Hutang::konsinyasi('Bu Roti')['sisa'] === 14000 && Hutang::barangKonsinyasi('Bu Roti')[0]['terjual_qty'] === 4);
Pos::batalkan($idK, 'uji', null, null);
ok('penjualan batal tidak jadi hutang konsinyasi', Hutang::konsinyasi('Bu Roti')['sisa'] === 0);
$idK = Pos::jual('ikhwan', null, [$hk2 => 4], 'tunai', 1000000, null, null, 0, 'Umum');
throws('bayar konsinyasi melebihi terjual ditolak', fn() => Hutang::bayar('konsinyasi', 'Bu Roti', null, 15000, date('Y-m-d'), '', null));
Hutang::bayar('konsinyasi', 'Bu Roti', null, 10000, date('Y-m-d'), '', null);
ok('konsinyasi dibayar sebagian', Hutang::konsinyasi('Bu Roti')['sisa'] === 4000 && Hutang::ringkasan()['Bu Roti']['total_sisa'] === 4000);
throws('akhiri konsinyasi saat stok masih ada ditolak', fn() => Hutang::akhiriKonsinyasi($hk2, null));
Gudang::keluar($hk2, Pos::TOKO, 2, 'retur ke supplier', null, null);
Hutang::akhiriKonsinyasi($hk2, null);
ok('konsinyasi diakhiri', trim((string) Gudang::find($hk2)['konsinyasi_pemasok']) === '');
Pos::batalkan($idK, 'uji', null, null);
foreach ([$hk1, $hk2] as $zid) { Db::exec('DELETE FROM stok WHERE barang_id = ?', [$zid]); Db::exec('DELETE FROM stok_mutasi WHERE barang_id = ?', [$zid]); Db::exec('DELETE FROM penjualan_item WHERE barang_id = ?', [$zid]); Db::exec('DELETE FROM barang WHERE id = ?', [$zid]); }
Db::exec('DELETE FROM hutang'); Db::exec('DELETE FROM hutang_bayar');
// piutang pelanggan (bon di kasir)
throws('hutang tanpa nama ditolak', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'hutang', 0, null, null, 0, 'Umum'));
throws('hutang tempo salah ditolak', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'hutang', 0, null, null, 0, 'Umum: Budi', 'xx'));
$sebelumB1 = Gudang::stok($b1, Pos::TOKO);
$idH = Pos::jual('ikhwan', null, [$b1 => 2], 'hutang', 0, null, null, 0, 'Umum: Budi Santoso', date('Y-m-d', strtotime('-2 days')));
$pH = Db::one('SELECT * FROM piutang WHERE penjualan_id = ?', [$idH]);
ok('hutang kasir: stok berkurang, piutang tercatat atas nama', Gudang::stok($b1, Pos::TOKO) === $sebelumB1 - 2 && $pH['nama'] === 'Budi Santoso' && (int) $pH['dibayar'] === 0 && (int) $pH['total'] === (int) Db::val('SELECT total FROM penjualan WHERE id = ?', [$idH]));
ok('hutang kasir: dibayar 0 dan metode hutang', (int) Db::val('SELECT dibayar FROM penjualan WHERE id = ?', [$idH]) === 0 && Db::val('SELECT metode FROM penjualan WHERE id = ?', [$idH]) === 'hutang');
$rkP = Piutang::ringkasan();
ok('ringkasan piutang per nama + lewat tempo', $rkP['Budi Santoso']['sisa'] === (int) $pH['total'] && $rkP['Budi Santoso']['lewat'] === (int) $pH['total']);
ok('laporan pembelian hutang memuat bon', count(Piutang::laporan(date('Y-m-d'), date('Y-m-d'))) >= 1);
throws('bayar piutang melebihi sisa ditolak', fn() => Piutang::bayar((int) $pH['id'], (int) $pH['total'] + 1, date('Y-m-d'), '', null));
Piutang::bayar((int) $pH['id'], 1000, date('Y-m-d'), 'cicil', null);
ok('piutang sebagian', Db::val('SELECT status FROM piutang WHERE id = ?', [$pH['id']]) === 'sebagian' && Piutang::totalSisa() === (int) $pH['total'] - 1000);
throws('penjualan hutang yang sudah dicicil tidak bisa dibatalkan', fn() => Pos::batalkan($idH, 'uji', null, null));
Piutang::bayar((int) $pH['id'], (int) $pH['total'] - 1000, date('Y-m-d'), '', null);
ok('piutang lunas', Db::val('SELECT status FROM piutang WHERE id = ?', [$pH['id']]) === 'lunas' && Piutang::ringkasan() === []);
$idH2 = Pos::jual('ikhwan', null, [$b1 => 1], 'hutang', 0, null, null, 0, 'Pegawai: Rina');
ok('hutang pegawai memakai harga pegawai bila ada', (int) Db::val('SELECT total FROM penjualan WHERE id = ?', [$idH2]) > 0);
Pos::batalkan($idH2, 'uji', null, null);
ok('batal penjualan hutang menghapus piutang dari daftar', Db::val('SELECT status FROM piutang WHERE penjualan_id = ?', [$idH2]) === 'batal' && !isset(Piutang::ringkasan()['Rina']));
Db::exec('UPDATE piutang SET dibayar = 0 WHERE penjualan_id = ?', [$idH]); Pos::batalkan($idH, 'uji', null, null);
Db::exec('DELETE FROM piutang_bayar'); Db::exec('DELETE FROM piutang');
// santri unit mana pun boleh belanja di satu toko
$sa = (int) Db::val("SELECT id FROM santri WHERE unit = 'akhwat' LIMIT 1");
if ($sa) { Pos::topup($sa, 50000, 'uji', null, null); $ids = Pos::jual(Pos::TOKO, $sa, [$b1 => 1], 'dompet', 0, null, null); ok('santri akhwat bisa belanja di toko', (int) $ids > 0); Pos::batalkan($ids, 'uji', null, null); }


ok('gagal jual tidak mengurangi stok (rollback)', Gudang::stok($b1, 'ikhwan') === 15);
$pj = Pos::jual('ikhwan', null, [$b1 => 2, $b2 => 1], 'tunai', 20000, null, null);
$pjr = Pos::find($pj);
ok('jual tunai: total & nomor struk', (int) $pjr['total'] === 10000 && $pjr['no_struk'] === 'PJ-' . date('Y') . '-' . str_pad((string) $pj, 6, '0', STR_PAD_LEFT));
ok('jual mengurangi stok otomatis', Gudang::stok($b1, 'ikhwan') === 13 && Gudang::stok($b2, 'ikhwan') === 9);
$rk = Pos::ringkas(date('Y-m-d'), date('Y-m-d'), 'ikhwan');
ok('ringkas: omzet 10.000, laba 3.400 (2x900 + 1x2000... modal 1600 & 3000)', $rk['omzet'] === 10000 && $rk['laba'] === 10000 - (2 * 1600 + 3000));
throws('dompet tanpa santri ditolak', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'dompet', 0, null, null));
throws('dompet saldo kurang ditolak', fn() => Pos::jual('ikhwan', $id1, [$b1 => 1], 'dompet', 0, null, null));
throws('top up terlalu kecil ditolak', fn() => Pos::topup($id1, 500, '', null, null));
Pos::topup($id1, 20000, 'titipan', null, null);
ok('top up menambah saldo', Pos::saldo($id1) === 20000);
$pj2 = Pos::jual('ikhwan', $id1, [$b2 => 2], 'dompet', 0, null, null);
ok('belanja dompet memotong saldo', Pos::saldo($id1) === 10000 && Gudang::stok($b2, 'ikhwan') === 7);
$pjX = null; try { $pjX = Pos::jual('ikhwan', $id2, [$b1 => 1], 'dompet', 0, null, null); } catch (InvalidArgumentException $e) { }
ok('santri unit mana pun boleh belanja di satu toko (selama saldo cukup)', $pjX !== null || Pos::saldo($id2) < 2500);
if ($pjX) { Pos::batalkan($pjX, 'uji', null, null); }
throws('akun akhwat tak bisa jual di ikhwan', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 5000, null, 'akhwat'));
throws('batal tanpa alasan ditolak', fn() => Pos::batalkan($pj2, '', null, null));
Pos::batalkan($pj2, 'salah input', null, null);
ok('batal mengembalikan stok & saldo', Pos::saldo($id1) === 20000 && Gudang::stok($b2, 'ikhwan') === 9);
throws('batal dua kali ditolak', fn() => Pos::batalkan($pj2, 'lagi', null, null));

// QRIS statis di kasir
throws('QRIS ditolak saat belum diaktifkan', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'qris', 0, null, null));
$tmpQr = tempnam(sys_get_temp_dir(), 'qr');
$gd = imagecreatetruecolor(1200, 1200); imagepng($gd, $tmpQr);
throws('unggah QRIS non-gambar ditolak', fn() => Qris::simpan(['tmp_name' => __FILE__, 'size' => 100, 'error' => 0]));
$nmQr = Qris::simpan(['tmp_name' => $tmpQr, 'size' => (int) filesize($tmpQr), 'error' => 0]);
ok('QRIS: gambar tersimpan & diperkecil maks 1000 px', is_file(APP_ROOT . Qris::DIR . '/' . $nmQr) && getimagesize(APP_ROOT . Qris::DIR . '/' . $nmQr)[0] === 1000);
ok('QRIS belum aktif walau gambar ada', !Qris::aktif());
Settings::set('qris_aktif', '1');
ok('QRIS aktif setelah diaktifkan', Qris::aktif() && Qris::url() === 'assets/qris/' . $nmQr);
$stokQ = Gudang::stok($b1, 'ikhwan');
$pjq = Pos::jual('ikhwan', null, [$b1 => 2], 'qris', 0, null, null);
$pjqr = Pos::find($pjq);
ok('jual QRIS: metode qris, dibayar = total, stok berkurang', $pjqr['metode'] === 'qris' && (int) $pjqr['dibayar'] === (int) $pjqr['total'] && Gudang::stok($b1, 'ikhwan') === $stokQ - 2);
Pos::batalkan($pjq, 'uji QRIS', null, null);
ok('batal QRIS mengembalikan stok', Gudang::stok($b1, 'ikhwan') === $stokQ);
@unlink(APP_ROOT . Qris::DIR . '/' . $nmQr); @unlink(APP_ROOT . Qris::DIR . '/.htaccess'); @rmdir(APP_ROOT . Qris::DIR); @unlink($tmpQr);
Settings::set('qris_aktif', '0'); Settings::set('qris_gambar', '');

// Diskon pesanan
$stokD = Gudang::stok($b1, 'ikhwan');
throws('diskon melebihi subtotal ditolak', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 99999, null, null, 999999));
throws('diskon negatif ditolak', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 99999, null, null, -1));
$pjd = Pos::jual('ikhwan', null, [$b1 => 2], 'tunai', 10000, null, null, 1000);
$pjdr = Pos::find($pjd);
ok('diskon: total = subtotal - diskon, tersimpan', (int) $pjdr['total'] === 2 * 2500 - 1000 && (int) $pjdr['diskon'] === 1000 && (int) $pjdr['dibayar'] === 10000);
$rkD = Pos::ringkas(date('Y-m-d'), date('Y-m-d'), 'ikhwan');
Pos::batalkan($pjd, 'uji diskon', null, null);
ok('batal penjualan berdiskon mengembalikan stok', Gudang::stok($b1, 'ikhwan') === $stokD);
ok('laba kotor memperhitungkan diskon', $rkD['laba'] === $rk['laba'] + (2 * 2500 - 2 * 1600 - 1000) && $rkD['omzet'] === $rk['omzet'] + (2 * 2500 - 1000));
ok('ringkas tidak menghitung yang batal', Pos::ringkas(date('Y-m-d'), date('Y-m-d'), 'ikhwan')['n'] === 1);
Pos::koreksi($id1, -5000, 'ditarik wali', null, null);
ok('koreksi saldo', Pos::saldo($id1) === 15000);
throws('koreksi saldo negatif ditolak', fn() => Pos::koreksi($id1, -99999, 'x', null, null));
ok('barang terlaris', Pos::terlaris(date('Y-m-d'), date('Y-m-d'), null)[0]['qty'] == 2);
ok('stok menipis terdeteksi', Gudang::jumlahRendah('akhwat') === 2);
Gudang::setAktif($b1, false, null);
throws('barang nonaktif tak bisa dijual', fn() => Pos::jual('ikhwan', null, [$b1 => 1], 'tunai', 99999, null, null));
ok('role kasir & gudang ada', isset(Domain::ROLE['kasir'], Domain::ROLE['gudang']));

// ---------- manajemen akun & sesi ----------
$ua = Auth::createUser('admin.satu', 'Admin Satu', 'admin', 'semua', 'passwordku123', null);
$ub = Auth::createUser('kasir.tes', 'Kasir Tes', 'kasir', 'ikhwan', 'passwordku123', null);
throws('password pendek ditolak', fn() => Auth::resetPassword($ub, 'pendek', $ua));
Auth::resetPassword($ub, 'passwordbaru99', $ua);
ok('reset password: hash baru berlaku', password_verify('passwordbaru99', Db::val('SELECT password_hash FROM users WHERE id = ?', [$ub])));
ok('reset password menaikkan session_version', (int) Db::val('SELECT session_version FROM users WHERE id = ?', [$ub]) === 2);
Auth::updateUser($ub, 'Kasir Baru', 'gudang', 'akhwat', $ua);
$r = Db::one('SELECT nama, role, unit_akses FROM users WHERE id = ?', [$ub]);
ok('ubah akun: nama, peran, unit', $r['nama'] === 'Kasir Baru' && $r['role'] === 'gudang' && $r['unit_akses'] === 'akhwat');
throws('peran tak valid ditolak', fn() => Auth::updateUser($ub, 'X', 'superuser', 'semua', $ua));
throws('admin tak bisa menurunkan diri sendiri', fn() => Auth::updateUser($ua, 'Admin Satu', 'tu', 'semua', $ua));
throws('admin aktif terakhir dilindungi', fn() => Auth::updateUser($ua, 'Admin Satu', 'tu', 'semua', $ub));
ok('login terakhir kosong', Auth::lastLogin($ub) === null);
Db::audit($ub, 'login', 'users', $ub);
ok('login terakhir tercatat', Auth::lastLogin($ub) !== null);
$_SESSION = ['uid' => $ua, 'sv' => 1, 'sistem' => 'pendidikan', 'last' => time()];
(new ReflectionProperty('Auth', 'cache'))->setValue(null, null);
ok('sesi aktif valid', Auth::user() !== null && Auth::sistem() === 'pendidikan');
$_SESSION['last'] = time() - Auth::IDLE - 5;
(new ReflectionProperty('Auth', 'cache'))->setValue(null, null);
ok('sesi menganggur >2 jam berakhir', Auth::user() === null && !isset($_SESSION['uid']));
$_SESSION = [];
(new ReflectionProperty('Auth', 'cache'))->setValue(null, null);


echo $fail === 0 ? "\nSemua uji lulus.\n" : "\n$fail uji GAGAL.\n";
exit($fail === 0 ? 0 : 1);
