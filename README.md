# POS LH - LH Mart & Gudang (Yayasan Luqmanul Hakim)

Aplikasi kasir, stok Gudang/Market, barang masuk, hutang/bon, QRIS, dan laporan. PHP 8.2+ dan MySQL, tanpa framework.
Khusus POS. Sistem akademik berada di repositori terpisah (`akademiklh`) dan memakai database yang sama.

## Alur kerja
1. Edit kode di GitHub (cabang `main`, atau Pull Request lalu merge).
2. cPanel > Git Version Control > repositori ini > Pull or Deploy > Update from Remote > Deploy HEAD Commit.
3. `.cpanel.yml` menyalin berkas ke `/home/yayas183/poslh_app/` tanpa menimpa `config.php` dan `storage/`.
4. Bila ada migrasi baru (`database/migrasi-*.sql`), login admin, buka menu Periksa Database, klik Perbarui database sekarang.

## Uji
`php tests/run.php` (SQLite memori) dan `php tests/smoke.php <halaman>`.

## Rahasia
`config.php`, kunci API, dan folder `storage/` tidak pernah masuk Git.
