<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

if (!is_file(APP_ROOT . '/config.php')) {
    http_response_code(500);
    exit('Aplikasi belum dikonfigurasi: salin config.example.php menjadi config.php.');
}
$cfg = require APP_ROOT . '/config.php';
date_default_timezone_set($cfg['timezone'] ?? 'Asia/Jakarta');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name('sppsess');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$routes = [
    'login' => 'login', 'logout' => 'logout', 'akun' => 'akun', 'captcha' => 'captcha',
    'pos' => 'pos', 'pos_cari' => 'pos_cari', 'struk' => 'struk', 'penjualan' => 'penjualan', 'dompet' => 'dompet',
    'gudang' => 'gudang', 'gudang_item' => 'gudang_item', 'barang' => 'barang', 'akun_pos' => 'akun_pos', 'label' => 'label',
    'impor_stok' => 'impor_stok', 'barang_masuk' => 'barang_masuk', 'bukti_qris' => 'bukti_qris', 'qris_buat' => 'qris_buat',
    'qris_status' => 'qris_status', 'qris_callback' => 'qris_callback', 'piutang' => 'piutang', 'hutang' => 'hutang', 'db_cek' => 'db_cek',
    'laporan_pos' => 'laporan_pos', 'laporan_stok' => 'laporan_stok',
];
$hostSis = Host::sistem();
$bawaan = 'pos';
$p = $_GET['p'] ?? $bawaan;
if (!isset($routes[$p])) {
    http_response_code(404);
    $p = $bawaan;
}
if (($ke = Host::periksa($p, $hostSis)) !== null) {
    header('Location: ' . $ke, true, 302);
    exit;
}
try {
    require APP_ROOT . '/pages/' . $routes[$p] . '.php';
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    $detail = '';
    if (Auth::user() && Auth::can('users')) {
        $detail = '<p><a class="btn" href="?p=db_cek">Periksa &amp; perbarui database</a></p><p class="mut"><small>Detail (hanya terlihat admin): ' . e(mb_substr($e->getMessage(), 0, 300)) . '</small></p>';
    }
    render('Kesalahan', '<div class="card"><h2>Kesalahan basis data</h2><p>Periksa konfigurasi dan pastikan skema database sudah diimpor.</p>' . $detail . '</div>');
}
