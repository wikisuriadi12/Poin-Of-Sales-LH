<?php
declare(strict_types=1);
/*
 * Penerima callback pembayaran dari penyedia QRIS (alamat ini yang didaftarkan ke BNI).
 * Tanpa sesi dan tanpa CSRF: keaslian diperiksa lewat tanda tangan oleh penyedia (lihat src/qris.php).
 * Harus dapat diakses publik lewat HTTPS.
 */
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/pos.php';
require __DIR__ . '/src/qris.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'error' => 'Gunakan POST.'], 405);
$raw = file_get_contents('php://input', false, null, 0, 65536) ?: '';
$headers = function_exists('getallheaders') ? array_change_key_case(getallheaders() ?: [], CASE_LOWER) : [];

try {
    $event = qris_provider()->parseWebhook($raw, $headers);
    if ($event === null) json_out(['ok' => true, 'diabaikan' => true]);
    json_out(['ok' => true, 'hasil' => mark_paid($event['ref'], $event['amount'], $raw)]);
} catch (PDOException $e) {
    error_log('Callback pembayaran: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Kesalahan server.'], 500); // penyedia akan mencoba kirim ulang
} catch (RuntimeException $e) {
    $code = in_array($e->getCode(), [401, 404, 422], true) ? $e->getCode() : 400;
    error_log('Callback pembayaran ditolak (' . $code . '): ' . $e->getMessage());
    json_out(['ok' => false, 'error' => $e->getMessage()], $code);
} catch (Throwable $e) {
    error_log('Callback pembayaran: ' . $e->getMessage());
    json_out(['ok' => false, 'error' => 'Kesalahan server.'], 500);
}
