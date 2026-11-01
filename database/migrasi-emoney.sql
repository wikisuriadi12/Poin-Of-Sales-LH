-- Top up e-money (agen): saldo deposit agen, produk/tarif, mutasi deposit, dan rincian top up.
-- Top up yang berhasil juga dicatat sebagai baris penjualan (jenis = 'topup') agar masuk transaksi harian.
-- Aman dijalankan ulang ('Duplicate column' / 'already exists' diabaikan).
ALTER TABLE penjualan ADD COLUMN jenis ENUM('barang','topup') NOT NULL DEFAULT 'barang';
CREATE TABLE IF NOT EXISTS emoney_sumber (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(80) NOT NULL,
  saldo BIGINT NOT NULL DEFAULT 0,
  saldo_min INT UNSIGNED NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS emoney_produk (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(60) NOT NULL,
  jenis ENUM('dompet','kartu') NOT NULL DEFAULT 'dompet',
  sumber_id INT UNSIGNED NOT NULL,
  biaya_admin INT UNSIGNED NOT NULL DEFAULT 0,
  biaya_modal INT UNSIGNED NOT NULL DEFAULT 0,
  nominal_min INT UNSIGNED NOT NULL DEFAULT 10000,
  nominal_maks INT UNSIGNED NOT NULL DEFAULT 2000000,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  KEY idx_emp_sumber (sumber_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS emoney_mutasi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sumber_id INT UNSIGNED NOT NULL,
  tipe ENUM('isi','pakai','kembali','koreksi') NOT NULL,
  jumlah BIGINT NOT NULL,
  saldo_setelah BIGINT NOT NULL,
  ref_id INT UNSIGNED NULL,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_emm_sumber (sumber_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS emoney_trx (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  penjualan_id INT UNSIGNED NOT NULL UNIQUE,
  produk_id INT UNSIGNED NOT NULL,
  sumber_id INT UNSIGNED NOT NULL,
  no_tujuan VARCHAR(40) NOT NULL,
  nama_pelanggan VARCHAR(80) NOT NULL DEFAULT '',
  nominal INT UNSIGNED NOT NULL,
  biaya_admin INT UNSIGNED NOT NULL DEFAULT 0,
  modal INT UNSIGNED NOT NULL,
  ref_penyedia VARCHAR(80) NOT NULL DEFAULT '',
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_emt_produk (produk_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
