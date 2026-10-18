<?php
declare(strict_types=1);

/*
 * Logika penjualan. Seluruh nominal adalah rupiah bulat (tanpa desimal).
 * Harga dan stok selalu dibaca ulang dari database di dalam transaksi; angka dari browser tidak dipercaya.
 * Stok dikurangi saat pesanan dibuat (QRIS: ditahan selama menunggu) dan dikembalikan bila batal/kedaluwarsa.
 */

const PONDOK = ['ikhwan', 'akhwat'];
const METODE = ['tunai', 'qris', 'saldo'];

/** @return array<int,int> id barang => jumlah, digabung dan diurutkan menurut id */
function normalize_lines(mixed $items): array {
    if (!is_array($items) || $items === [] || count($items) > 100) throw new RuntimeException('Keranjang kosong atau terlalu besar.');
    $merged = [];
    foreach ($items as $row) {
        if (!is_array($row)) throw new RuntimeException('Isi keranjang tidak valid.');
        $id = to_int($row['product_id'] ?? null, 1, PHP_INT_MAX, 'Barang');
        $qty = to_int($row['qty'] ?? null, 1, 999, 'Jumlah');
        $merged[$id] = ($merged[$id] ?? 0) + $qty;
        if ($merged[$id] > 999) throw new RuntimeException('Jumlah satu barang terlalu besar.');
    }
    ksort($merged);
    return $merged;
}

/**
 * Hitung keranjang dari data barang di database. Fungsi murni (tanpa akses database).
 * @param array<int,int> $qtyById
 * @param array<int,array<string,mixed>> $products
 * @return array{items:list<array<string,mixed>>,subtotal:int,diskon:int,total:int}
 */
function price_cart(array $qtyById, array $products, int $discount): array {
    $items = [];
    $subtotal = 0;
    foreach ($qtyById as $id => $qty) {
        $p = $products[$id] ?? null;
        if (!$p || !(int)$p['aktif']) throw new RuntimeException('Ada barang yang tidak ditemukan atau sudah nonaktif.');
        if ((int)$p['stok'] < $qty) throw new RuntimeException('Stok ' . $p['nama'] . ' tidak cukup (sisa ' . (int)$p['stok'] . ').');
        $price = (int)$p['harga'];
        $items[] = ['product_id' => (int)$id, 'nama' => (string)$p['nama'], 'harga' => $price, 'qty' => $qty, 'subtotal' => $price * $qty];
        $subtotal += $price * $qty;
    }
    if ($discount < 0 || $discount > $subtotal) throw new RuntimeException('Diskon tidak boleh melebihi subtotal.');
    $total = $subtotal - $discount;
    if ($total < 1) throw new RuntimeException('Total pembayaran harus lebih dari nol.');
    return ['items' => $items, 'subtotal' => $subtotal, 'diskon' => $discount, 'total' => $total];
}

/** @param list<int> $ids */
function locked_products(array $ids): array {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = sql("SELECT id,sku,nama,harga,stok,aktif,pondok FROM products WHERE id IN ($in) ORDER BY id" . lock_clause(), array_values($ids))->fetchAll();
    $map = [];
    foreach ($rows as $r) $map[(int)$r['id']] = $r;
    return $map;
}

/** Buat pesanan. Tunai/saldo langsung lunas; QRIS berstatus menunggu sampai pembayaran dikonfirmasi. */
function create_order(array $in, int $userId): array {
    $pondok = (string)($in['pondok'] ?? '');
    $metode = (string)($in['metode'] ?? '');
    if (!in_array($pondok, PONDOK, true)) throw new RuntimeException('Pondok tidak valid.');
    if (!in_array($metode, METODE, true)) throw new RuntimeException('Metode pembayaran tidak valid.');
    $discount = to_int($in['diskon'] ?? 0, 0, 1000000000, 'Diskon');
    $qtyById = normalize_lines($in['items'] ?? null);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $products = locked_products(array_keys($qtyById));
        foreach ($products as $p) {
            if (!in_array($p['pondok'], ['semua', $pondok], true)) throw new RuntimeException($p['nama'] . ' tidak dijual di pondok ini.');
        }
        $cart = price_cart($qtyById, $products, $discount);
        $total = $cart['total'];

        $received = null;
        $change = null;
        if ($metode === 'tunai') {
            $received = to_int($in['uang_diterima'] ?? null, 0, 1000000000000, 'Uang diterima');
            if ($received < $total) throw new RuntimeException('Uang yang diterima kurang dari total.');
            $change = $received - $total;
        }

        $santriId = null;
        if ($metode === 'saldo') {
            $santriId = to_int($in['santri_id'] ?? null, 1, PHP_INT_MAX, 'Santri');
            $s = sql('SELECT id,saldo,aktif,pondok FROM santri WHERE id=?' . lock_clause(), [$santriId])->fetch();
            if (!$s || !(int)$s['aktif']) throw new RuntimeException('Santri tidak ditemukan atau nonaktif.');
            if ($s['pondok'] !== $pondok) throw new RuntimeException('Santri berasal dari pondok lain.');
            if ((int)$s['saldo'] < $total) throw new RuntimeException('Saldo santri tidak cukup.');
        }

        $now = now();
        $status = $metode === 'qris' ? 'menunggu' : 'lunas';
        sql('INSERT INTO orders (nomor,pondok,status,metode,subtotal,diskon,total,santri_id,uang_diterima,kembalian,created_by,created_at,paid_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            'TMP-' . bin2hex(random_bytes(8)), $pondok, $status, $metode, $cart['subtotal'], $cart['diskon'], $total,
            $santriId, $received, $change, $userId, $now, $status === 'lunas' ? $now : null,
        ]);
        $orderId = (int)$pdo->lastInsertId();
        $nomor = sprintf('TRX-%s-%05d', date('Ymd'), $orderId);
        sql('UPDATE orders SET nomor=? WHERE id=?', [$nomor, $orderId]);

        foreach ($cart['items'] as $it) {
            sql('INSERT INTO order_items (order_id,product_id,nama,harga,qty,subtotal) VALUES (?,?,?,?,?,?)',
                [$orderId, $it['product_id'], $it['nama'], $it['harga'], $it['qty'], $it['subtotal']]);
            $u = sql('UPDATE products SET stok=stok-? WHERE id=? AND stok>=?', [$it['qty'], $it['product_id'], $it['qty']]);
            if ($u->rowCount() !== 1) throw new RuntimeException('Stok ' . $it['nama'] . ' berubah. Coba lagi.');
            sql('INSERT INTO stock_movements (product_id,perubahan,alasan,order_id,catatan,created_by,created_at) VALUES (?,?,?,?,?,?,?)',
                [$it['product_id'], -$it['qty'], 'penjualan', $orderId, null, $userId, $now]);
        }
        if ($santriId !== null) {
            $u = sql('UPDATE santri SET saldo=saldo-? WHERE id=? AND saldo>=?', [$total, $santriId, $total]);
            if ($u->rowCount() !== 1) throw new RuntimeException('Saldo santri berubah. Coba lagi.');
            sql('INSERT INTO santri_ledger (santri_id,perubahan,jenis,order_id,catatan,created_by,created_at) VALUES (?,?,?,?,?,?,?)',
                [$santriId, -$total, 'belanja', $orderId, $nomor, $userId, $now]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    if ($metode === 'qris') {
        try {
            open_charge($orderId, $nomor, $total);
        } catch (Throwable $e) {
            error_log('Gagal membuat QR untuk ' . $nomor . ': ' . $e->getMessage());
            release_order($orderId, 'batal', 'Gagal membuat QR pembayaran.');
            throw ($e instanceof RuntimeException && !($e instanceof PDOException)) ? $e : new RuntimeException('QR pembayaran belum dapat dibuat. Coba lagi.');
        }
    }
    return order_view($orderId);
}

function open_charge(int $orderId, string $nomor, int $amount): void {
    $minutes = max(1, min(60, (int)cfg('qris_expiry_minutes', 5)));
    $expires = (new DateTimeImmutable('now'))->modify('+' . $minutes . ' minutes');
    $charge = qris_provider()->createCharge($nomor, $amount, $expires);
    sql('INSERT INTO payments (order_id,provider,provider_ref,qr_string,status,amount,expires_at,created_at) VALUES (?,?,?,?,?,?,?,?)',
        [$orderId, (string)cfg('qris_provider', 'mock'), $charge['ref'], $charge['qr_string'], 'menunggu', $amount, $expires->format('Y-m-d H:i:s'), now()]);
}

/** Batalkan/kedaluwarsakan pesanan yang masih menunggu dan kembalikan stok. @return bool true bila ada perubahan */
function release_order(int $orderId, string $newStatus, string $note): bool {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = sql('SELECT id,status FROM orders WHERE id=?' . lock_clause(), [$orderId])->fetch();
        if (!$o || $o['status'] !== 'menunggu') { $pdo->commit(); return false; }
        sql('UPDATE orders SET status=?,catatan=? WHERE id=?', [$newStatus, $note, $orderId]);
        sql("UPDATE payments SET status=? WHERE order_id=? AND status='menunggu'", [$newStatus === 'kedaluwarsa' ? 'kedaluwarsa' : 'gagal', $orderId]);
        $now = now();
        foreach (sql('SELECT product_id,qty FROM order_items WHERE order_id=? ORDER BY product_id', [$orderId])->fetchAll() as $it) {
            sql('UPDATE products SET stok=stok+? WHERE id=?', [(int)$it['qty'], (int)$it['product_id']]);
            sql('INSERT INTO stock_movements (product_id,perubahan,alasan,order_id,catatan,created_by,created_at) VALUES (?,?,?,?,?,?,?)',
                [(int)$it['product_id'], (int)$it['qty'], 'retur', $orderId, $note, null, $now]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cancel_order(string $nomor): void {
    if (!release_order(order_id_by_nomor($nomor), 'batal', 'Dibatalkan kasir.')) {
        throw new RuntimeException('Pesanan ini tidak dapat dibatalkan karena sudah dibayar atau ditutup.', 409);
    }
}

/** Tutup pesanan QRIS yang melewati batas waktu. Dipanggil saat halaman/polling dibuka. */
function expire_due_orders(): int {
    $n = 0;
    $due = sql("SELECT o.id FROM orders o JOIN payments p ON p.order_id=o.id WHERE o.status='menunggu' AND p.status='menunggu' AND p.expires_at < ?", [now()])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($due as $id) if (release_order((int)$id, 'kedaluwarsa', 'QR kedaluwarsa tanpa pembayaran.')) $n++;
    // Pesanan menunggu tanpa data pembayaran (proses terputus saat membuat QR).
    $stuck = sql("SELECT o.id FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.status='menunggu' AND p.id IS NULL AND o.created_at < ?", [date('Y-m-d H:i:s', time() - 600)])->fetchAll(PDO::FETCH_COLUMN);
    foreach ($stuck as $id) if (release_order((int)$id, 'batal', 'Pembuatan QR terputus.')) $n++;
    return $n;
}

/**
 * Tandai pembayaran QRIS lunas. Aman dipanggil berulang (callback ganda).
 * @return string 'ok' | 'sudah' | 'tinjau' (dana masuk setelah pesanan batal/kedaluwarsa)
 */
function mark_paid(string $ref, int $amount, ?string $raw = null): string {
    $first = sql('SELECT order_id FROM payments WHERE provider_ref=?', [$ref])->fetch();
    if (!$first) throw new RuntimeException('Referensi pembayaran tidak dikenal.', 404);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $o = sql('SELECT id,status FROM orders WHERE id=?' . lock_clause(), [$first['order_id']])->fetch();
        $p = sql('SELECT id,status,amount FROM payments WHERE provider_ref=?' . lock_clause(), [$ref])->fetch();
        if ((int)$p['amount'] !== $amount) throw new RuntimeException('Nominal pembayaran tidak cocok.', 422);
        if ($p['status'] === 'lunas') { $pdo->commit(); return 'sudah'; }
        $now = now();
        sql('UPDATE payments SET status=?,paid_at=?,raw_payload=? WHERE id=?', ['lunas', $now, $raw, $p['id']]);
        if ($o['status'] === 'menunggu') {
            sql('UPDATE orders SET status=?,paid_at=? WHERE id=?', ['lunas', $now, $o['id']]);
            $result = 'ok';
        } else {
            sql('UPDATE orders SET catatan=? WHERE id=?', ['Dana masuk setelah pesanan ' . $o['status'] . '. Perlu pengembalian dana manual.', $o['id']]);
            $result = 'tinjau';
        }
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function order_id_by_nomor(string $nomor): int {
    $r = sql('SELECT id FROM orders WHERE nomor=?', [$nomor])->fetch();
    if (!$r) throw new RuntimeException('Pesanan tidak ditemukan.', 404);
    return (int)$r['id'];
}

function order_view(int $orderId): array {
    $o = sql('SELECT o.id,o.nomor,o.pondok,o.status,o.metode,o.subtotal,o.diskon,o.total,o.uang_diterima,o.kembalian,o.catatan,o.created_at,o.paid_at,s.nama AS santri_nama,s.saldo AS santri_saldo FROM orders o LEFT JOIN santri s ON s.id=o.santri_id WHERE o.id=?', [$orderId])->fetch();
    if (!$o) throw new RuntimeException('Pesanan tidak ditemukan.', 404);
    $items = sql('SELECT product_id,nama,harga,qty,subtotal FROM order_items WHERE order_id=? ORDER BY id', [$orderId])->fetchAll();
    $pay = sql('SELECT provider,qr_string,status,amount,expires_at,paid_at FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$orderId])->fetch() ?: null;
    $left = ($pay && $pay['status'] === 'menunggu' && $pay['expires_at']) ? max(0, strtotime((string)$pay['expires_at']) - time()) : 0;
    foreach (['subtotal', 'diskon', 'total'] as $k) $o[$k] = (int)$o[$k];
    foreach (['uang_diterima', 'kembalian', 'santri_saldo'] as $k) if ($o[$k] !== null) $o[$k] = (int)$o[$k];
    foreach ($items as &$it) { $it['harga'] = (int)$it['harga']; $it['qty'] = (int)$it['qty']; $it['subtotal'] = (int)$it['subtotal']; }
    unset($it);
    return $o + ['items' => $items, 'pembayaran' => $pay ? ['provider' => $pay['provider'], 'qr_string' => $pay['qr_string'], 'status' => $pay['status'], 'detik_sisa' => $left] : null];
}

/* ---------- Gudang dan santri ---------- */
function restock(int $productId, int $qty, string $note, int $userId): void {
    $qty = to_int($qty, 1, 1000000, 'Jumlah restok');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $p = sql('SELECT id FROM products WHERE id=?' . lock_clause(), [$productId])->fetch();
        if (!$p) throw new RuntimeException('Barang tidak ditemukan.');
        sql('UPDATE products SET stok=stok+? WHERE id=?', [$qty, $productId]);
        sql('INSERT INTO stock_movements (product_id,perubahan,alasan,order_id,catatan,created_by,created_at) VALUES (?,?,?,?,?,?,?)',
            [$productId, $qty, 'restok', null, $note === '' ? null : $note, $userId, now()]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function save_product(array $in, int $userId): void {
    $id = ($in['id'] ?? '') === '' ? null : to_int($in['id'], 1, PHP_INT_MAX, 'Barang');
    $sku = text_field($in['sku'] ?? null, 60, 'SKU');
    if (!preg_match('/^[A-Za-z0-9._-]+$/D', $sku)) throw new RuntimeException('SKU hanya boleh huruf, angka, titik, garis bawah, dan strip.');
    $nama = text_field($in['nama'] ?? null, 180, 'Nama barang');
    $kategori = text_field($in['kategori'] ?? null, 100, 'Kategori');
    $harga = to_int($in['harga'] ?? null, 1, 1000000000, 'Harga');
    $min = to_int($in['stok_min'] ?? 0, 0, 1000000, 'Stok minimum');
    $pondok = (string)($in['pondok'] ?? '');
    if (!in_array($pondok, ['semua', 'ikhwan', 'akhwat'], true)) throw new RuntimeException('Pondok tidak valid.');
    $aktif = ($in['aktif'] ?? '1') === '1' ? 1 : 0;
    if ($id !== null) {
        if (!sql('SELECT id FROM products WHERE id=?', [$id])->fetch()) throw new RuntimeException('Barang tidak ditemukan.');
        sql('UPDATE products SET sku=?,nama=?,kategori=?,harga=?,stok_min=?,pondok=?,aktif=? WHERE id=?', [$sku, $nama, $kategori, $harga, $min, $pondok, $aktif, $id]);
        return;
    }
    $initial = to_int($in['stok_awal'] ?? 0, 0, 1000000, 'Stok awal');
    sql('INSERT INTO products (sku,nama,kategori,harga,stok,stok_min,pondok,aktif) VALUES (?,?,?,?,0,?,?,?)', [$sku, $nama, $kategori, $harga, $min, $pondok, $aktif]);
    if ($initial > 0) restock((int)db()->lastInsertId(), $initial, 'Stok awal', $userId);
}

function add_santri(array $in): void {
    $pondok = (string)($in['pondok'] ?? '');
    if (!in_array($pondok, PONDOK, true)) throw new RuntimeException('Pondok tidak valid.');
    sql('INSERT INTO santri (nomor_induk,nama,pondok,saldo,aktif) VALUES (?,?,?,0,1)',
        [text_field($in['nomor_induk'] ?? null, 50, 'Nomor induk'), text_field($in['nama'] ?? null, 180, 'Nama'), $pondok]);
}

function topup_santri(int $santriId, int $amount, string $note, int $userId): void {
    $amount = to_int($amount, 1, 100000000, 'Nominal top-up');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (!sql('SELECT id FROM santri WHERE id=? AND aktif=1' . lock_clause(), [$santriId])->fetch()) throw new RuntimeException('Santri tidak ditemukan atau nonaktif.');
        sql('UPDATE santri SET saldo=saldo+? WHERE id=?', [$amount, $santriId]);
        sql('INSERT INTO santri_ledger (santri_id,perubahan,jenis,order_id,catatan,created_by,created_at) VALUES (?,?,?,?,?,?,?)',
            [$santriId, $amount, 'topup', null, $note === '' ? null : $note, $userId, now()]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
