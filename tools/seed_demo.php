<?php
declare(strict_types=1);
// Data contoh untuk uji coba (khusus CLI): php tools/seed_demo.php --yes
// Hanya berjalan bila tabel products dan santri masih kosong. JANGAN dipakai di data produksi.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/pos.php';

if (!in_array('--yes', $argv, true)) { fwrite(STDERR, "Tambahkan --yes untuk mengisi data contoh.\n"); exit(1); }
if ((int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn() > 0 || (int)db()->query('SELECT COUNT(*) FROM santri')->fetchColumn() > 0) {
    fwrite(STDERR, "Tabel sudah berisi data. Dibatalkan.\n");
    exit(1);
}
$products = [
    ['KTN-001', 'Air Mineral 600 ml', 'Kantin', 3500, 120, 40, 'semua'],
    ['KTN-002', 'Mie Instan Goreng', 'Kantin', 3800, 96, 30, 'semua'],
    ['KTN-003', 'Roti Sobek Cokelat', 'Kantin', 9000, 18, 20, 'semua'],
    ['KTN-004', 'Susu UHT 200 ml', 'Kantin', 6500, 48, 24, 'semua'],
    ['KTN-005', 'Kurma Sukari 250 g', 'Kantin', 38000, 14, 6, 'semua'],
    ['ATK-001', 'Buku Tulis 38 Lembar', 'Alat Tulis', 5000, 200, 60, 'semua'],
    ['ATK-002', 'Pulpen Hitam', 'Alat Tulis', 3000, 150, 50, 'semua'],
    ['ATK-003', 'Pensil 2B', 'Alat Tulis', 2500, 8, 40, 'semua'],
    ['PRL-001', 'Sabun Mandi Batang', 'Perlengkapan', 4500, 70, 30, 'semua'],
    ['PRL-002', 'Sikat Gigi', 'Perlengkapan', 7000, 55, 20, 'semua'],
    ['PRL-004', 'Sajadah Travel', 'Perlengkapan', 45000, 22, 8, 'semua'],
    ['BSN-001', 'Sarung Dewasa', 'Busana', 85000, 25, 10, 'ikhwan'],
    ['BSN-002', 'Peci Hitam', 'Busana', 25000, 40, 15, 'ikhwan'],
    ['BSN-004', 'Mukena Dewasa', 'Busana', 150000, 14, 6, 'akhwat'],
    ['BSN-005', 'Jilbab Segi Empat Putih', 'Busana', 35000, 45, 20, 'akhwat'],
    ['KTB-001', "Mushaf Al-Qur'an Saku", 'Kitab & Buku', 65000, 20, 8, 'semua'],
];
foreach ($products as [$sku, $nama, $kat, $harga, $stok, $min, $pondok]) {
    save_product(['sku' => $sku, 'nama' => $nama, 'kategori' => $kat, 'harga' => (string)$harga, 'stok_min' => (string)$min, 'pondok' => $pondok, 'stok_awal' => (string)$stok], 0);
}
foreach ([['CTH-001', 'Santri Contoh Ikhwan 1', 'ikhwan'], ['CTH-002', 'Santri Contoh Ikhwan 2', 'ikhwan'], ['CTH-101', 'Santri Contoh Akhwat 1', 'akhwat'], ['CTH-102', 'Santri Contoh Akhwat 2', 'akhwat']] as [$no, $nama, $pondok]) {
    add_santri(['nomor_induk' => $no, 'nama' => $nama, 'pondok' => $pondok]);
}
echo count($products) . " barang dan 4 santri contoh ditambahkan. Top-up saldo lewat menu Santri.\n";
