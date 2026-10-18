<?php
declare(strict_types=1);
// Uji logika murni (tanpa database): php tests/unit.php
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/pos.php';
require dirname(__DIR__) . '/src/qris.php';

$failed = 0;
function check(bool $ok, string $name): void { global $failed; if (!$ok) $failed++; echo ($ok ? "  ok   " : "  GAGAL ") . $name . "\n"; }
function fails(callable $fn, string $contains): bool {
    try { $fn(); } catch (RuntimeException $e) { return str_contains($e->getMessage(), $contains); }
    return false;
}

$products = [
    1 => ['id' => 1, 'nama' => 'Air Mineral', 'harga' => 3500, 'stok' => 10, 'aktif' => 1],
    2 => ['id' => 2, 'nama' => 'Mie Instan', 'harga' => 3800, 'stok' => 2, 'aktif' => 1],
    3 => ['id' => 3, 'nama' => 'Barang Nonaktif', 'harga' => 1000, 'stok' => 5, 'aktif' => 0],
];

echo "price_cart\n";
$c = price_cart([1 => 2, 2 => 1], $products, 500);
check($c['subtotal'] === 10800 && $c['total'] === 10300 && $c['diskon'] === 500, 'subtotal, diskon, total dihitung dari harga database');
check(count($c['items']) === 2 && $c['items'][0]['subtotal'] === 7000, 'rincian barang benar');
check(fails(fn() => price_cart([2 => 3], $products, 0), 'Stok Mie Instan tidak cukup'), 'stok kurang ditolak');
check(fails(fn() => price_cart([3 => 1], $products, 0), 'nonaktif'), 'barang nonaktif ditolak');
check(fails(fn() => price_cart([99 => 1], $products, 0), 'tidak ditemukan'), 'barang tidak ada ditolak');
check(fails(fn() => price_cart([1 => 1], $products, 3501), 'Diskon'), 'diskon melebihi subtotal ditolak');
check(fails(fn() => price_cart([1 => 1], $products, 3500), 'lebih dari nol'), 'total nol ditolak');

echo "normalize_lines\n";
check(normalize_lines([['product_id' => 2, 'qty' => 1], ['product_id' => 1, 'qty' => 2], ['product_id' => 2, 'qty' => 3]]) === [1 => 2, 2 => 4], 'barang sama digabung dan diurutkan');
check(fails(fn() => normalize_lines([]), 'kosong'), 'keranjang kosong ditolak');
check(fails(fn() => normalize_lines([['product_id' => 1, 'qty' => 0]]), 'Jumlah'), 'jumlah nol ditolak');
check(fails(fn() => normalize_lines([['product_id' => 1, 'qty' => -2]]), 'Jumlah'), 'jumlah negatif ditolak');
check(fails(fn() => normalize_lines([['product_id' => 1, 'qty' => 1.5]]), 'Jumlah'), 'jumlah desimal ditolak');
check(fails(fn() => normalize_lines([['product_id' => '1; DROP', 'qty' => 1]]), 'Barang'), 'id non-angka ditolak');
check(fails(fn() => normalize_lines([['product_id' => 1, 'qty' => 600], ['product_id' => 1, 'qty' => 600]]), 'terlalu besar'), 'total jumlah satu barang dibatasi');

echo "to_int dan text_field\n";
check(to_int('0', 0, 10, 'x') === 0 && to_int('7', 0, 10, 'x') === 7 && to_int(5, 0, 10, 'x') === 5, 'angka valid');
check(fails(fn() => to_int('007', 0, 10, 'x'), 'tidak valid'), 'nol di depan ditolak');
check(fails(fn() => to_int('1e3', 0, 5000, 'x'), 'tidak valid'), 'notasi ilmiah ditolak');
check(fails(fn() => to_int(11, 0, 10, 'x'), 'tidak valid'), 'di atas batas ditolak');
check(fails(fn() => text_field('   ', 10, 'Nama'), 'kosong'), 'teks kosong ditolak');
check(fails(fn() => text_field(str_repeat('a', 11), 10, 'Nama'), 'terlalu panjang'), 'teks terlalu panjang ditolak');
check(fails(fn() => text_field("\xC3\x28", 10, 'Nama'), 'kosong'), 'UTF-8 rusak ditolak');

echo "rupiah\n";
check(rupiah(1234567) === 'Rp 1.234.567' && rupiah('0') === 'Rp 0', 'format rupiah');

echo "tanda tangan callback\n";
$secret = 'rahasia-uji-yang-cukup-panjang';
$body = '{"ref":"MOCK-1","amount":5000,"status":"PAID"}';
$sig = hash_hmac('sha256', $body, $secret);
check(webhook_signature_valid($body, $sig, $secret), 'tanda tangan benar diterima');
check(!webhook_signature_valid($body . ' ', $sig, $secret), 'isi diubah ditolak');
check(!webhook_signature_valid($body, str_repeat('0', 64), $secret), 'tanda tangan salah ditolak');
check(!webhook_signature_valid($body, hash_hmac('sha256', $body, 'ISI_RAHASIA_ACAK_PANJANG'), 'ISI_RAHASIA_ACAK_PANJANG'), 'rahasia bawaan config.example ditolak');
check(!webhook_signature_valid($body, hash_hmac('sha256', $body, ''), ''), 'rahasia kosong ditolak');

echo $failed === 0 ? "\nSemua lulus\n" : "\n$failed pengujian gagal\n";
exit($failed === 0 ? 0 : 1);
