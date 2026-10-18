<?php
declare(strict_types=1);
// API JSON untuk halaman kasir. Wajib login; permintaan POST wajib header X-CSRF-Token.
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/pos.php';
require __DIR__ . '/src/qris.php';

start_session();
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function api_require(string $perm): void { if (!can($perm)) json_out(['ok' => false, 'error' => 'Akses ditolak.'], 403); }

try {
    if (!load_user()) json_out(['ok' => false, 'error' => 'Sesi berakhir. Muat ulang halaman dan masuk lagi.'], 401);
    $route = query('r');
    $body = [];
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) json_out(['ok' => false, 'error' => 'Form kedaluwarsa. Muat ulang halaman.'], 419);
        $body = json_decode(file_get_contents('php://input', false, null, 0, 65536) ?: '', true);
        if (!is_array($body)) json_out(['ok' => false, 'error' => 'Permintaan tidak valid.'], 400);
    }

    switch ($route) {
        case 'products':
            api_require('kasir');
            $pondok = query('pondok');
            if (!in_array($pondok, PONDOK, true)) throw new RuntimeException('Pondok tidak valid.');
            $rows = sql("SELECT id,sku,nama,kategori,harga,stok,stok_min FROM products WHERE aktif=1 AND pondok IN ('semua',?) ORDER BY kategori,nama", [$pondok])->fetchAll();
            foreach ($rows as &$r) foreach (['id', 'harga', 'stok', 'stok_min'] as $k) $r[$k] = (int)$r[$k];
            unset($r);
            json_out(['ok' => true, 'products' => $rows]);

        case 'santri':
            api_require('kasir');
            $pondok = query('pondok');
            if (!in_array($pondok, PONDOK, true)) throw new RuntimeException('Pondok tidak valid.');
            $rows = sql('SELECT id,nomor_induk,nama,saldo FROM santri WHERE aktif=1 AND pondok=? ORDER BY nama', [$pondok])->fetchAll();
            foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['saldo'] = (int)$r['saldo']; }
            unset($r);
            json_out(['ok' => true, 'santri' => $rows]);

        case 'checkout':
            api_require('kasir');
            json_out(['ok' => true, 'order' => create_order($body, (int)$_SESSION['user_id'])]);

        case 'order':
            if (!can('kasir') && !can('riwayat')) api_require('kasir');
            expire_due_orders();
            json_out(['ok' => true, 'order' => order_view(order_id_by_nomor(query('nomor')))]);

        case 'cancel':
            api_require('kasir');
            cancel_order(is_string($body['nomor'] ?? null) ? $body['nomor'] : '');
            json_out(['ok' => true]);

        case 'mock_pay':
            // Hanya untuk uji coba. Dimatikan otomatis bila penyedia bukan 'mock'.
            api_require('kasir');
            if (cfg('qris_provider', 'mock') !== 'mock') json_out(['ok' => false, 'error' => 'Simulasi pembayaran hanya tersedia pada mode uji.'], 403);
            $view = order_view(order_id_by_nomor(is_string($body['nomor'] ?? null) ? $body['nomor'] : ''));
            $ref = sql('SELECT provider_ref FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$view['id']])->fetchColumn();
            if (!$ref) throw new RuntimeException('Pesanan ini tidak memiliki tagihan QR.');
            json_out(['ok' => true, 'hasil' => mark_paid((string)$ref, $view['total'], '{"sumber":"simulasi-kasir"}')]);

        default:
            json_out(['ok' => false, 'error' => 'Rute tidak dikenal.'], 404);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    json_out(['ok' => false, 'error' => 'Database bermasalah. Hubungi administrator.'], 500);
} catch (RuntimeException $e) {
    $code = in_array($e->getCode(), [404, 409, 422], true) ? $e->getCode() : 422;
    json_out(['ok' => false, 'error' => $e->getMessage()], $code);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_out(['ok' => false, 'error' => 'Terjadi kesalahan di server.'], 500);
}
