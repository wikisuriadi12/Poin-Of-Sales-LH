<?php
declare(strict_types=1);
/*
 * Uji alur dengan database sungguhan.
 *   SQLite (bawaan):  php tests/flow.php
 *   MySQL/MariaDB:    POS_DB_DSN='mysql:host=127.0.0.1;dbname=pos_test;charset=utf8mb4' POS_DB_USER=... POS_DB_PASS=... php tests/flow.php
 * Jalankan hanya pada database uji yang KOSONG: skrip membuat semua tabel.
 */
putenv('POS_DB_DSN=' . (getenv('POS_DB_DSN') ?: 'sqlite::memory:'));
putenv('POS_WEBHOOK_SECRET=rahasia-uji-yang-cukup-panjang');
putenv('POS_QRIS_PROVIDER=mock');
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/pos.php';
require dirname(__DIR__) . '/src/qris.php';

$failed = 0;
function check(bool $ok, string $name): void { global $failed; if (!$ok) $failed++; echo ($ok ? "  ok   " : "  GAGAL ") . $name . "\n"; }
function fails(callable $fn, string $contains, ?int $code = null): bool {
    try { $fn(); } catch (RuntimeException $e) { return str_contains($e->getMessage(), $contains) && ($code === null || $e->getCode() === $code); }
    return false;
}
function stok(int $id): int { return (int)sql('SELECT stok FROM products WHERE id=?', [$id])->fetchColumn(); }
function saldo(int $id): int { return (int)sql('SELECT saldo FROM santri WHERE id=?', [$id])->fetchColumn(); }
function count_rows(string $table): int { return (int)db()->query("SELECT COUNT(*) FROM $table")->fetchColumn(); }
function line(int $id, int $qty): array { return ['product_id' => $id, 'qty' => $qty]; }

/* Siapkan skema dan data uji */
$file = is_mysql() ? dirname(__DIR__) . '/database/schema.sql' : __DIR__ . '/schema.sqlite.sql';
foreach (preg_split('/;\s*\n/', (string)file_get_contents($file)) as $stmt) {
    $stmt = trim((string)preg_replace('/^--.*$/m', '', $stmt));
    if ($stmt !== '') db()->exec($stmt);
}
sql("INSERT INTO users (username,password_hash,role) VALUES ('uji','x','kasir')");
$uid = (int)db()->lastInsertId();
foreach ([
    ['KTN-001', 'Air Mineral 600 ml', 3500, 20, 'semua'],
    ['KTN-002', 'Mie Instan Goreng', 3800, 10, 'semua'],
    ['BSN-001', 'Sarung Dewasa', 85000, 5, 'ikhwan'],
    ['BSN-004', 'Mukena Dewasa', 150000, 5, 'akhwat'],
] as $p) sql('INSERT INTO products (sku,nama,kategori,harga,stok,stok_min,pondok,aktif) VALUES (?,?,?,?,?,5,?,1)', [$p[0], $p[1], 'Uji', $p[2], $p[3], $p[4]]);
sql("INSERT INTO santri (nomor_induk,nama,pondok,saldo) VALUES ('S-1','Santri Ikhwan','ikhwan',50000)");
sql("INSERT INTO santri (nomor_induk,nama,pondok,saldo) VALUES ('S-2','Santri Akhwat','akhwat',50000)");
[$AIR, $MIE, $SARUNG, $MUKENA] = [1, 2, 3, 4];
[$S1, $S2] = [1, 2];

echo "Tunai\n";
$o = create_order(['pondok' => 'ikhwan', 'metode' => 'tunai', 'diskon' => 500, 'uang_diterima' => 20000, 'items' => [line($AIR, 2), line($MIE, 1)]], $uid);
check($o['status'] === 'lunas' && $o['total'] === 10300 && $o['kembalian'] === 9700, 'pesanan tunai lunas, total dan kembalian benar');
check(stok($AIR) === 18 && stok($MIE) === 9, 'stok berkurang');
check(preg_match('/^TRX-\d{8}-\d{5}$/', $o['nomor']) === 1, 'format nomor transaksi');
check(count_rows('stock_movements') === 2, 'riwayat stok tercatat');
check(fails(fn() => create_order(['pondok' => 'ikhwan', 'metode' => 'tunai', 'uang_diterima' => 1000, 'items' => [line($AIR, 1)]], $uid), 'kurang'), 'uang kurang ditolak');
check(stok($AIR) === 18 && count_rows('orders') === 1, 'penolakan tidak mengubah stok maupun pesanan');

echo "Validasi\n";
check(fails(fn() => create_order(['pondok' => 'ikhwan', 'metode' => 'tunai', 'uang_diterima' => 999999, 'items' => [line($MIE, 50)]], $uid), 'Stok'), 'stok tidak cukup ditolak');
check(fails(fn() => create_order(['pondok' => 'akhwat', 'metode' => 'tunai', 'uang_diterima' => 999999, 'items' => [line($SARUNG, 1)]], $uid), 'tidak dijual'), 'barang pondok lain ditolak');
check(fails(fn() => create_order(['pondok' => 'ikhwan', 'metode' => 'kredit', 'items' => [line($AIR, 1)]], $uid), 'Metode'), 'metode tidak dikenal ditolak');
check(fails(fn() => create_order(['pondok' => 'ikhwan', 'metode' => 'tunai', 'uang_diterima' => 99999, 'items' => [line($AIR, 1)], 'harga' => 1], $uid), '') === false, 'harga dari klien diabaikan');
$last = sql('SELECT total FROM orders ORDER BY id DESC LIMIT 1')->fetchColumn();
check((int)$last === 3500, 'total memakai harga database');

echo "Saldo santri\n";
$o = create_order(['pondok' => 'ikhwan', 'metode' => 'saldo', 'santri_id' => $S1, 'items' => [line($AIR, 4), line($MIE, 4)]], $uid);
check($o['status'] === 'lunas' && $o['total'] === 29200 && saldo($S1) === 20800, 'saldo berkurang sebesar total');
check(count_rows('santri_ledger') === 1, 'buku saldo tercatat');
check(fails(fn() => create_order(['pondok' => 'ikhwan', 'metode' => 'saldo', 'santri_id' => $S1, 'items' => [line($AIR, 7), line($MIE, 1)]], $uid), 'Saldo santri tidak cukup'), 'saldo kurang ditolak');
check(saldo($S1) === 20800, 'saldo tidak berubah setelah penolakan');
check(fails(fn() => create_order(['pondok' => 'ikhwan', 'metode' => 'saldo', 'santri_id' => $S2, 'items' => [line($AIR, 1)]], $uid), 'pondok lain'), 'santri pondok lain ditolak');
topup_santri($S1, 10000, 'uji', $uid);
check(saldo($S1) === 30800, 'top-up menambah saldo');

echo "QRIS\n";
$before = stok($AIR);
$o = create_order(['pondok' => 'ikhwan', 'metode' => 'qris', 'items' => [line($AIR, 3)]], $uid);
check($o['status'] === 'menunggu' && $o['pembayaran']['status'] === 'menunggu' && $o['pembayaran']['detik_sisa'] > 0, 'pesanan QRIS menunggu, QR dibuat');
check(stok($AIR) === $before - 3, 'stok ditahan selama menunggu');
$ref = (string)sql('SELECT provider_ref FROM payments ORDER BY id DESC LIMIT 1')->fetchColumn();
check(fails(fn() => mark_paid($ref, 1), 'Nominal', 422), 'nominal tidak cocok ditolak');
check(fails(fn() => mark_paid('TIDAK-ADA', 10500), 'tidak dikenal', 404), 'referensi tidak dikenal ditolak');
check(mark_paid($ref, 10500, '{"x":1}') === 'ok', 'pembayaran valid menandai lunas');
check(order_view(order_id_by_nomor($o['nomor']))['status'] === 'lunas', 'status pesanan lunas');
check(mark_paid($ref, 10500) === 'sudah', 'callback ganda aman (idempoten)');
check(stok($AIR) === $before - 3, 'stok tidak berubah oleh callback ganda');

echo "Batal dan kedaluwarsa\n";
$before = stok($AIR);
$o = create_order(['pondok' => 'ikhwan', 'metode' => 'qris', 'items' => [line($AIR, 5)]], $uid);
cancel_order($o['nomor']);
check(order_view((int)order_id_by_nomor($o['nomor']))['status'] === 'batal' && stok($AIR) === $before, 'batal mengembalikan stok');
check(fails(fn() => cancel_order($o['nomor']), 'tidak dapat dibatalkan', 409), 'batal dua kali ditolak');

$o = create_order(['pondok' => 'ikhwan', 'metode' => 'qris', 'items' => [line($AIR, 2)]], $uid);
$ref2 = (string)sql('SELECT provider_ref FROM payments ORDER BY id DESC LIMIT 1')->fetchColumn();
check(stok($AIR) === $before - 2, 'stok ditahan');
sql('UPDATE payments SET expires_at=? WHERE provider_ref=?', ['2000-01-01 00:00:00', $ref2]);
check(expire_due_orders() === 1, 'pesanan lewat batas waktu ditutup');
check(order_view(order_id_by_nomor($o['nomor']))['status'] === 'kedaluwarsa' && stok($AIR) === $before, 'kedaluwarsa mengembalikan stok');
check(mark_paid($ref2, 7000) === 'tinjau', 'dana masuk setelah kedaluwarsa ditandai untuk ditinjau');
check(str_contains((string)order_view(order_id_by_nomor($o['nomor']))['catatan'], 'pengembalian dana'), 'catatan pengembalian dana tersimpan');
check(stok($AIR) === $before, 'stok tetap setelah dana terlambat');

echo "Callback penyedia (simulasi)\n";
$p = new MockQris();
$body = json_encode(['ref' => 'MOCK-ABC', 'amount' => 5000, 'status' => 'PAID']);
$sig = hash_hmac('sha256', $body, 'rahasia-uji-yang-cukup-panjang');
check($p->parseWebhook($body, ['x-signature' => $sig]) === ['ref' => 'MOCK-ABC', 'amount' => 5000], 'callback bertanda tangan benar dibaca');
check(fails(fn() => $p->parseWebhook($body, ['x-signature' => 'salah']), 'Tanda tangan', 401), 'tanda tangan salah ditolak');
check(fails(fn() => $p->parseWebhook($body, []), 'Tanda tangan', 401), 'tanpa tanda tangan ditolak');
$pending = json_encode(['ref' => 'MOCK-ABC', 'amount' => 5000, 'status' => 'PENDING']);
check($p->parseWebhook($pending, ['x-signature' => hash_hmac('sha256', $pending, 'rahasia-uji-yang-cukup-panjang')]) === null, 'status selain PAID diabaikan');

echo "Gudang\n";
$before = stok($MIE);
restock($MIE, 25, 'Kiriman gudang', $uid);
check(stok($MIE) === $before + 25, 'restok menambah stok');
check(fails(fn() => restock($MIE, 0, '', $uid), 'tidak valid'), 'restok nol ditolak');
save_product(['sku' => 'ATK-009', 'nama' => 'Penghapus', 'kategori' => 'Alat Tulis', 'harga' => '2000', 'stok_min' => '5', 'pondok' => 'semua', 'stok_awal' => '12'], $uid);
$newId = (int)sql("SELECT id FROM products WHERE sku='ATK-009'")->fetchColumn();
check(stok($newId) === 12, 'barang baru dengan stok awal tercatat');
check(fails(fn() => save_product(['sku' => 'a b', 'nama' => 'X', 'kategori' => 'Y', 'harga' => '1', 'pondok' => 'semua'], $uid), 'SKU'), 'SKU tidak valid ditolak');

echo $failed === 0 ? "\nSemua lulus\n" : "\n$failed pengujian gagal\n";
exit($failed === 0 ? 0 : 1);
