<?php
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/pos.php';
require __DIR__ . '/src/qris.php';
require __DIR__ . '/src/actions.php';

start_session();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

try {
    $loggedIn = load_user();
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit('Database belum siap. Administrator perlu memeriksa konfigurasi dan tabel sistem.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') handle_post($loggedIn);
if (!$loggedIn) { require __DIR__ . '/views/login.php'; exit; }

$page = query('page', home_page());
if (!in_array($page, ['kasir', 'riwayat', 'stok', 'santri'], true)) $page = home_page();
if (!can($page)) { http_response_code(403); exit('Akses ditolak.'); }
require __DIR__ . '/views/layout.php';
