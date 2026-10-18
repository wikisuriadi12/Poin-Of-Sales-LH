<?php
declare(strict_types=1);
// Pemasangan awal (khusus CLI): php tools/install.php
// Membuat tabel yang belum ada (CREATE TABLE IF NOT EXISTS) dan akun pengguna. Tidak menghapus data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';

function ask(string $label, bool $hidden = false): string {
    echo $label;
    if ($hidden && stripos(PHP_OS_FAMILY, 'Windows') === false) system('stty -echo');
    $v = trim((string)fgets(STDIN));
    if ($hidden && stripos(PHP_OS_FAMILY, 'Windows') === false) { system('stty echo'); echo "\n"; }
    return $v;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "Tidak dapat terhubung ke database: " . $e->getMessage() . "\nPeriksa config.php.\n");
    exit(1);
}
$name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
echo "Database: $name\n";
if (strtolower(ask("Buat tabel yang belum ada di database ini? (ketik ya): ")) !== 'ya') { echo "Dibatalkan.\n"; exit(1); }

$file = $name === 'mysql' ? dirname(__DIR__) . '/database/schema.sql' : dirname(__DIR__) . '/tests/schema.sqlite.sql';
foreach (preg_split('/;\s*\n/', (string)file_get_contents($file)) as $stmt) {
    $stmt = trim((string)preg_replace('/^--.*$/m', '', $stmt));
    if ($stmt !== '') $pdo->exec($stmt);
}
echo "Tabel siap.\n";

while (strtolower(ask("Tambah pengguna? (ya/tidak): ")) === 'ya') {
    $username = ask('Username (3-80: huruf, angka, _ . -): ');
    $role = ask('Peran (admin/kasir/gudang/pimpinan): ');
    $pw = ask('Password (minimal 12 karakter): ', true);
    $pw2 = ask('Ulangi password: ', true);
    if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/D', $username) || !in_array($role, ['admin', 'kasir', 'gudang', 'pimpinan'], true) || strlen($pw) < 12 || strlen($pw) > 72 || $pw !== $pw2) {
        echo "Data tidak valid atau password tidak cocok. Ulangi.\n";
        continue;
    }
    try {
        sql('INSERT INTO users (username,password_hash,role) VALUES (?,?,?)', [$username, password_hash($pw, PASSWORD_DEFAULT), $role]);
        echo "Pengguna $username ($role) dibuat.\n";
    } catch (PDOException $e) {
        echo "Gagal: username mungkin sudah ada.\n";
    }
}
echo "Selesai.\n";
