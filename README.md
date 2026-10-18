# Point of Sales — Yayasan Luqmanul Hakim

Kasir koperasi/toko pesantren dengan pembayaran QRIS (BNI), stok gudang yang
terhubung, dan pemisahan pondok **ikhwan** / **akhwat**. PHP 8.2+ dan MySQL/MariaDB,
cocok untuk shared hosting cPanel (IDwebhost). Tanpa framework dan tanpa composer.

## Status jujur

| Bagian | Status |
|---|---|
| Kasir, stok, riwayat, saldo santri | Selesai, diuji (SQLite) |
| QRIS **simulasi** (`MockQris`) | Berfungsi untuk uji coba |
| QRIS **BNI sungguhan** (`BniQris`) | **Belum diimplementasikan** — kerangka saja |
| MySQL/MariaDB | Skema dan SQL dibuat portabel, tetapi belum dijalankan di MySQL sungguhan. Job `mysql` di CI akan menguji. |
| Retur/refund, shift kas | Belum ada |
| Data santri | Berdiri sendiri; belum terhubung ke modul pendaftaran/SPP |

## Peran

- `admin`: semua fitur
- `kasir`: kasir, riwayat, lihat stok, santri + top-up saldo
- `gudang`: stok + restok, tidak bisa membuka kasir
- `pimpinan`: riwayat dan stok (hanya lihat)

## Pasang

1. Unggah semua berkas ke hosting (folder `public_html/pos`, misalnya).
2. Buat database MySQL kosong di cPanel.
3. `cp config.example.php config.php`, isi kredensial database dan **ganti `webhook_secret`** dengan string acak panjang.
4. Lewat terminal/SSH: `php tools/install.php` (membuat tabel dan akun admin pertama, kata sandi minimal 12 karakter).
5. Opsional untuk uji coba: `php tools/seed_demo.php --yes` (16 barang dan 4 santri contoh; hanya berjalan pada tabel kosong).

`config.php` tidak masuk git. Folder `src/`, `views/`, `database/`, `tools/`, `tests/` dilindungi `.htaccess` (Apache).

## Integrasi QRIS BNI

`qris_provider` di `config.php` bernilai `mock` secara bawaan. **Jangan dipakai di produksi** — pembayaran simulasi bisa ditekan siapa pun yang login sebagai kasir.

Langkah ke BNI sungguhan:

1. Daftar sebagai merchant QRIS BNI dan minta dokumentasi API resmi serta kredensial (merchant id, api key, secret, base url).
2. Isi `bni_*` di `config.php`.
3. Implementasikan `BniQris` di `src/qris.php` (buat tagihan, ambil string QRIS, verifikasi callback) mengikuti dokumentasi resmi BNI. Kelas ini sengaja melempar error sampai diisi, agar tidak ada pembayaran palsu.
4. Daftarkan URL `https://domain-anda/webhook-bni.php` (wajib HTTPS publik) sebagai callback. Callback diverifikasi tanda tangan; format tanda tangan harus disesuaikan dengan BNI di `webhook_signature_valid()`.
5. Uji di sandbox BNI sebelum memakai uang sungguhan.

Perilaku pembayaran: stok dipesan saat QRIS dibuat dan dikembalikan bila dibatalkan/kedaluwarsa (`qris_expiry_minutes`); callback ganda aman (idempoten); nominal yang tidak cocok ditolak; dana yang masuk setelah pesanan kedaluwarsa ditandai **tinjau** untuk pengembalian manual.

## Uji

```
php tests/unit.php        # logika murni
php tests/flow.php        # alur database (SQLite)
# MySQL (database uji KOSONG):
POS_DB_DSN='mysql:host=127.0.0.1;dbname=pos_test;charset=utf8mb4' POS_DB_USER=root POS_DB_PASS=... php tests/flow.php
```

`tests/schema.sqlite.sql` harus tetap sama dengan `database/schema.sql` bila skema berubah.

## Catatan

- Semua uang disimpan sebagai bilangan bulat rupiah; harga dan stok selalu dibaca ulang dari database saat transaksi.
- Pustaka `qrcodejs` dimuat dari CDN; unduh dan simpan lokal bila perangkat kasir perlu bekerja tanpa internet (tanpa pustaka, UI menampilkan string QR).
